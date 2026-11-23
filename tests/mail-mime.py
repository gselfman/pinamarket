"""Parse actual generated MIME with an independent standard-library parser."""
from email import policy
from email.parser import BytesParser
from pathlib import Path
import os
import tempfile

root = Path(os.environ.get('PINA_MAIL_QA_DIR', tempfile.gettempdir() + '/pina-mail-qa'))
logo = (Path(__file__).resolve().parent.parent / 'app/mail-logo.png').read_bytes()
for name in ('reset', 'login', 'notification', 'without-logo'):
    message = BytesParser(policy=policy.default).parsebytes((root / (name + '.eml')).read_bytes())
    assert not message.defects, (name, message.defects)
    text = message.get_body(preferencelist=('plain',))
    html = message.get_body(preferencelist=('html',))
    assert text and html, name + ': both plain text and HTML are required'
    assert text.get_content_charset() == html.get_content_charset() == 'utf-8'
    assert all(not part.defects for part in message.walk()), name + ': invalid MIME part'
    if name != 'without-logo':
        images = [p for p in message.walk() if p.get_content_type() == 'image/png']
        assert len(images) == 1 and images[0].get_payload(decode=True) == logo
        assert images[0]['Content-ID'] == '<pina-market-logo>'
        assert 'cid:pina-market-logo' in html.get_content()
    if name in ('reset', 'login'):
        url = text.get_content().strip().splitlines()[-1]
        assert len(url.split('token=')[-1] if name == 'reset' else url.rsplit('/', 1)[-1]) == 64
        assert html.get_content().count('href="' + url + '"') == 2
print('PASS: independent MIME parsing, Cyrillic, embedded logo and exact action links')
