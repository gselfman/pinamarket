<?php
declare(strict_types=1);

function gc_contract_version(array $issuer,?array $signature):string {
 return hash('sha256',file_get_contents(__DIR__.'/giftcards-template.docx').$issuer['company_json'].json_encode($signature).$issuer['contract_number']);
}
function gc_bank_terms_hash(array $issuer):string {
 return hash('sha256',json_encode([$issuer['id'],$issuer['company_json'],$issuer['program_json'],hash_file('sha256',__DIR__.'/giftcards-template.docx'),hash_file('sha256',__DIR__.'/giftcards-offer.json')],JSON_THROW_ON_ERROR));
}
function gc_bank_approval_required(array $issuer):array {
 $a=one("SELECT * FROM pg_bank_approvals WHERE issuer_id=? AND status='approved'",[$issuer['id']]);
 if(!$a||!hash_equals($a['terms_hash'],gc_bank_terms_hash($issuer)))throw new ApiError('Зафиксируйте согласование АМРА для текущих условий этого эмитента',409);
 // A recorded check is not represented as an automatic regulator clearance.
 // A stale screening stops new orders; existing paid orders remain serviceable.
 if(strtotime($a['reviewed_at'].' UTC')<time()-86400)throw new ApiError('Обновите проверку эмитента по официальным источникам перед новыми заказами',409);
 gc_evidence($a['evidence_id'],'bank_approval',$issuer['id']);
 return $a;
}
function gc_issuer_sales_required(array $issuer):void {
 gc_program_location(json_decode($issuer['program_json'],true,512,JSON_THROW_ON_ERROR));
 require_once __DIR__.'/giftcards-documents.php';
 $expected=gc_contract_version($issuer,gc_signature_config());
 if($issuer['contract_status']!=='approved'||!hash_equals($expected,(string)$issuer['contract_version']))throw new ApiError('Для новых продаж необходим подписанный договор по актуальному шаблону',409);
 if(!one("SELECT id FROM pg_documents WHERE issuer_id=? AND kind='signed_contract' AND status='approved' AND version=?",[$issuer['id'],$expected]))throw new ApiError('Проверьте подписанный экземпляр текущей редакции',409);
 gc_bank_approval_required($issuer);
}
function gc_legal_sales_ready():bool {
 $g=gc_settings();
 return !empty($g['legal_approved'])&&!empty($g['payment_instructions'])&&hash_equals(hash_file('sha256',__DIR__.'/giftcards-offer.json'),(string)($g['legal_source_sha256']??''));
}
function gc_record_bank_approval(array $d,array $actor):array {
 if(gc_staff_role($actor)!=='admin')throw new ApiError('Согласование банка фиксирует администратор',403);
 return transact(function()use($d,$actor){
  $id=txt($d,'issuerId',36);$i=one('SELECT * FROM pg_issuers WHERE id=? FOR UPDATE',[$id]);if(!$i)throw new ApiError('Компания не найдена',404);
  if(($d['bankVerified']??false)!==true||($d['screeningVerified']??false)!==true)throw new ApiError('Подтвердите фактическое согласование банка и выполненную проверку по официальным источникам');
  $date=gc_date(txt($d,'approvalDate',10));if($date>gmdate('Y-m-d'))throw new ApiError('Согласование банка не может быть датировано будущим');
  $evidence=gc_evidence(txt($d,'evidenceId',36),'bank_approval',$id);$reference=txt($d,'reference',250);$note=txt($d,'screeningNote',3000);$hash=gc_bank_terms_hash($i);
  query("INSERT INTO pg_bank_approvals(issuer_id,terms_hash,reference,approval_date,evidence_id,screening_note,reviewed_at,reviewed_by,status) VALUES(?,?,?,?,?,?,?,?,'approved') ON DUPLICATE KEY UPDATE terms_hash=VALUES(terms_hash),reference=VALUES(reference),approval_date=VALUES(approval_date),evidence_id=VALUES(evidence_id),screening_note=VALUES(screening_note),reviewed_at=VALUES(reviewed_at),reviewed_by=VALUES(reviewed_by),status='approved'",[$id,$hash,$reference,$date,$evidence['id'],$note,now(),$actor['id']]);
  audit((int)$actor['id'],'gift_bank_program_reviewed',$id,['issuerId'=>$id,'reference'=>$reference,'termsHash'=>$hash,'evidenceId'=>$evidence['id']]);return ['saved'=>true];
 });
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
