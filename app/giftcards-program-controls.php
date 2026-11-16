<?php
declare(strict_types=1);

function gc_contract_version(array $issuer,?array $signature):string {
 return hash('sha256',file_get_contents(__DIR__.'/giftcards-template.docx').$issuer['company_json'].json_encode($signature).$issuer['contract_number']);
}
function gc_issuer_sales_required(array $issuer):void {
 gc_program_location(json_decode($issuer['program_json'],true,512,JSON_THROW_ON_ERROR));
 require_once __DIR__.'/giftcards-documents.php';
 $expected=gc_contract_version($issuer,gc_signature_config());
 if($issuer['contract_status']!=='approved'||!hash_equals($expected,(string)$issuer['contract_version']))throw new ApiError('Для новых продаж необходим подписанный договор по актуальному шаблону',409);
 if(!one("SELECT id FROM pg_documents WHERE issuer_id=? AND kind='signed_contract' AND status='approved' AND version=?",[$issuer['id'],$expected]))throw new ApiError('Проверьте подписанный экземпляр текущей редакции',409);
}
function gc_legal_sales_ready():bool {
 $g=gc_settings();
 return !empty($g['legal_approved'])&&!empty($g['payment_instructions'])&&hash_equals(hash_file('sha256',__DIR__.'/giftcards-offer.json'),(string)($g['legal_source_sha256']??''));
}
function gc_archive_offer(array $offer):void {
 query('INSERT IGNORE INTO pg_legal_versions(hash,version,document_json,created_at) VALUES(?,?,?,?)',[$offer['hash'],$offer['version'],json_encode($offer,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),now()]);
}

function gc_purchase_matches(string $activation,array $purchase):bool {
 $p=one('SELECT * FROM pg_activation_purchases WHERE activation_id=?',[$activation]);
 return $p&&$p['subject']===$purchase['subject']&&$p['performance']===$purchase['performance']&&$p['document_reference']===$purchase['document']&&$p['holder_name']===$purchase['holder'];
}
function gc_purchase_record(string $activation,array $card,array $purchase,array $actor):void {
 $snapshot=json_decode($card['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$program=$snapshot['program']??[];gc_program_location($program);
 query('INSERT INTO pg_activation_purchases(activation_id,subject,country,place,performance,document_reference,holder_name,recorded_by,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$activation,$purchase['subject'],$program['use_country'],$program['fulfillment_address'],$purchase['performance'],$purchase['document'],$purchase['holder'],$actor['id'],now()]);
 audit((int)$actor['id'],'gift_purchase_declared',$activation,['performance'=>$purchase['performance']]);
}
