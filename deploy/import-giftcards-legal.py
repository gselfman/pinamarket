"""Development-only import of approved DOCX sources; never run by hosting installer."""
import argparse
import hashlib
import json
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree as E

ROOT = Path(__file__).resolve().parents[1]
W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
NS = {'w': W}


def paragraph(p):
    # Keep line breaks in document titles and requisites instead of gluing words.
    return ''.join('\n' if x.tag == '{'+W+'}br' else x.text or ''
                   for x in p.iter() if x.tag in ['{'+W+'}t', '{'+W+'}br'])


def source(path):
    with ZipFile(path) as z:
        parts = {name: z.read(name) for name in z.namelist()}
    doc = E.fromstring(parts['word/document.xml'])
    if doc.xpath('//w:ins|//w:del', namespaces=NS):
        raise ValueError('Resolve text revisions before publication')
    return doc, parts


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--offer', type=Path, required=True)
    parser.add_argument('--agency', type=Path, required=True)
    args = parser.parse_args()
    offer, _ = source(args.offer)
    content = []
    for node in offer.find('{'+W+'}body'):
        if node.tag == '{'+W+'}p':
            value = paragraph(node)
            if not value.strip():
                continue
            style = node.xpath('./w:pPr/w:pStyle/@w:val', namespaces=NS)
            name = (style or ['Normal'])[0]
            name = {'Heading1': 'Heading 1', 'Heading2': 'Heading 2'}.get(name, name)
            content.append({'text': value, 'style': name})
        elif node.tag == '{'+W+'}tbl':
            content.append({'table': [
                ['\n'.join(paragraph(p) for p in cell.findall('{'+W+'}p'))
                 for cell in row.findall('{'+W+'}tc')]
                for row in node.findall('{'+W+'}tr')]})
    data = {'version': 'agency-2026-11-16', 'effective_date': '2026-11-16',
            'source_sha256': hashlib.sha256(args.offer.read_bytes()).hexdigest(),
            'content': content}
    encoded = json.dumps(data, ensure_ascii=False, indent=2)+'\n'
    for target in ['app/giftcards-offer.json', 'prototype/assets/offer.json']:
        (ROOT/target).write_text(encoded)

    doc, parts = source(args.agency)
    # Publishing strips internal editorial notes without changing legal wording.
    for node in doc.xpath('//w:commentRangeStart|//w:commentRangeEnd|//w:commentReference', namespaces=NS):
        node.getparent().remove(node)
    parts['word/document.xml'] = E.tostring(doc, xml_declaration=True, encoding='UTF-8', standalone=True)
    for name in list(parts):
        if name.startswith('word/comments') or name.startswith('word/_rels/comments'):
            del parts[name]
    for name in list(parts):
        if name.endswith('.rels') or name == '[Content_Types].xml':
            root = E.fromstring(parts[name])
            changed = False
            for node in list(root):
                if 'comments' in (node.get('Type', '')+' '+node.get('PartName', '')).lower():
                    root.remove(node)
                    changed = True
            if changed:
                parts[name] = E.tostring(root, xml_declaration=True, encoding='UTF-8', standalone=True)
    if 'docProps/core.xml' in parts:
        core = E.fromstring(parts['docProps/core.xml'])
        for node in core:
            if E.QName(node).localname in ['creator', 'lastModifiedBy']:
                node.text = 'Pina Market'
        parts['docProps/core.xml'] = E.tostring(core, xml_declaration=True, encoding='UTF-8', standalone=True)
    for target in ['app/giftcards-template.docx', 'prototype/assets/agency-template.docx']:
        with ZipFile(ROOT/target, 'w', ZIP_DEFLATED) as z:
            for name, value in parts.items():
                z.writestr(name, value)
    print('Imported offer and agency template; source documents unchanged; internal comments removed.')


if __name__ == '__main__':
    main()
