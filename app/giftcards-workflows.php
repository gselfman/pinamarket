<?php
declare(strict_types=1);

function gc_atomic(callable $f):mixed {return db()->inTransaction()?$f():transact($f);}

/** Access to an operational object is always checked before storing or serving its files. */
function gc_object_access(string $kind,string $id,array $actor):array {
 $issuer=null;$client=null;
 if($kind==='settlement'){$r=one('SELECT issuer_id FROM pg_settlements WHERE id=?',[$id]);$issuer=$r['issuer_id']??null;}
 elseif($kind==='refund'){$r=one('SELECT r.client_id,o.issuer_id FROM pg_refunds r JOIN pg_cards c ON c.id=r.card_id JOIN pg_orders o ON o.id=c.order_id WHERE r.id=?',[$id]);$issuer=$r['issuer_id']??null;$client=$r['client_id']??null;}
 elseif($kind==='case'){$r=one('SELECT issuer_id,client_id FROM pg_support_cases WHERE id=?',[$id]);$issuer=$r['issuer_id']??null;$client=$r['client_id']??null;}
 else throw new ApiError('Недопустимый вид документа');
 if(!$r)throw new ApiError('Основание не найдено',404);
 if(!($actor['is_staff']??false)&&(int)$client!==(int)$actor['id']){if(!$issuer)throw new ApiError('Нет доступа',403);gc_member($actor,$issuer,['owner','accountant']);}
 return ['issuerId'=>$issuer,'clientId'=>$client];
}
function gc_files(string $kind,string $id):array {return rows('SELECT id,object_kind,object_id,original_name,mime,size_bytes,sha256,uploaded_by,created_at FROM pg_files WHERE object_kind=? AND object_id=? ORDER BY created_at',[$kind,$id]);}
function gc_evidence(string $id,string $kind,string $object):array {
 $f=one('SELECT * FROM pg_files WHERE id=? AND object_kind=? AND object_id=?',[$id,$kind,$object]);
 if(!$f)throw new ApiError('Приложите банковский документ к этой операции');
 require_once __DIR__.'/giftcards-documents.php';$path=gc_storage().'/'.$f['storage_name'];
 if(!is_file($path)||!hash_equals($f['sha256'],hash_file('sha256',$path)))throw new ApiError('Не удалось проверить подтверждение',409);
 return $f;
}
function gc_upload_file(array $d,array $actor):array {
 require_once __DIR__.'/giftcards-documents.php';
 $kind=txt($d,'kind',24);$object=txt($d,'objectId',100);$scope=gc_object_access($kind,$object,$actor);
 $f=$_FILES['file']??null;
 if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']<8||$f['size']>10*1024*1024||!is_uploaded_file($f['tmp_name']))throw new ApiError('Загрузите PDF, PNG, JPG или DOCX до 10 МБ');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext='';
 if($mime==='application/pdf'&&file_get_contents($f['tmp_name'],false,null,0,5)==='%PDF-')$ext='pdf';
 elseif(in_array($mime,['image/png','image/jpeg'],true)){$size=getimagesize($f['tmp_name']);if($size&&$size[0]<=10000&&$size[1]<=10000)$ext=$mime==='image/png'?'png':'jpg';}
 else{$z=new ZipArchive();if($z->open($f['tmp_name'])===true){$bytes=0;$safe=true;for($n=0;$n<$z->numFiles;$n++){$s=$z->statIndex($n);$bytes+=(int)$s['size'];if(preg_match('/vbaProject|embeddings\//i',$s['name']))$safe=false;}if($safe&&$z->numFiles<500&&$bytes<=25*1024*1024&&$z->locateName('word/document.xml')!==false){$ext='docx';$mime='application/vnd.openxmlformats-officedocument.wordprocessingml.document';}$z->close();}}
 if($ext==='')throw new ApiError('Формат документа не подтверждён');
 $id=gc_uuid();$storage=$id.'.'.$ext;$target=gc_storage().'/'.$storage;
 if(!move_uploaded_file($f['tmp_name'],$target))throw new RuntimeException('UPLOAD_FAILED');chmod($target,0600);
 $name=mb_substr(preg_replace('/[\x00-\x1f\x7f\/\\\\]/u','_',basename($f['name'])),0,160);if($name==='')$name='document.'.$ext;
 try{gc_atomic(function()use($id,$kind,$object,$scope,$storage,$target,$name,$mime,$actor){query('INSERT INTO pg_files(id,object_kind,object_id,issuer_id,client_id,storage_name,original_name,mime,size_bytes,sha256,uploaded_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$kind,$object,$scope['issuerId'],$scope['clientId'],$storage,$name,$mime,filesize($target),hash_file('sha256',$target),$actor['id'],now()]);audit((int)$actor['id'],'gift_evidence_uploaded',$object,['fileId'=>$id,'kind'=>$kind]);});}catch(Throwable $e){unlink($target);throw $e;}
 return ['id'=>$id];
}
function gc_settlement_detail(string $id):array {
 $s=one('SELECT s.*,i.name,i.company_json,b.status balance_review_status FROM pg_settlements s JOIN pg_issuers i ON i.id=s.issuer_id LEFT JOIN pg_balance_returns b ON b.settlement_id=s.id WHERE s.id=?',[$id]);
 if(!$s)throw new ApiError('Расчёт не найден',404);
 // Recipient snapshot is immutable once the batch has been prepared.
 $snap=one("SELECT body FROM pg_settlement_events WHERE settlement_id=? AND kind='recipient_snapshot' ORDER BY created_at LIMIT 1",[$id]);
 $s['company']=json_decode($snap['body']??$s['company_json'],true);unset($s['company_json']);$s['expected_net_minor']=gc_settlement_expected($s);
 return ['settlement'=>$s,'items'=>rows('SELECT a.id,a.card_id,a.buyer_name,a.contract_reference,a.basis,a.nominal_minor,a.provider_commission_minor,a.activated_at,c.refund_status,o.id order_id FROM pg_settlement_items si JOIN pg_activations a ON a.id=si.activation_id JOIN pg_cards c ON c.id=a.card_id JOIN pg_orders o ON o.id=c.order_id WHERE si.settlement_id=? ORDER BY a.activated_at,a.id',[$id]),'events'=>rows("SELECT e.*,p.email actor_email FROM pg_settlement_events e JOIN pm_clients p ON p.id=e.actor_id WHERE settlement_id=? AND kind<>'recipient_snapshot' ORDER BY e.created_at,e.id",[$id]),'files'=>gc_files('settlement',$id)];
}
function gc_settlement_log(string $id,string $kind,int $amount,string $ref,string $note,array $actor,?string $key=null):void {
 query('INSERT INTO pg_settlement_events(id,settlement_id,kind,amount_minor,reference,body,request_key,actor_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[gc_uuid(),$id,$kind,$amount,$ref,$note,$key??gc_uuid(),$actor['id'],now()]);
}
function gc_merchant_totals(string $issuer):array {
 $q="SELECT a.nominal_minor,c.refund_status,s.status settlement_status FROM pg_activations a JOIN pg_cards c ON c.id=a.card_id LEFT JOIN pg_settlement_locks l ON l.activation_id=a.id LEFT JOIN pg_settlements s ON s.id=l.settlement_id WHERE a.issuer_id=? AND a.status='confirmed'";
 $totals=['accepted_minor'=>0,'available_minor'=>0,'reserved_minor'=>0,'settled_minor'=>0,'refund_hold_minor'=>0];
 foreach(rows($q,[$issuer]) as $r){$n=(int)$r['nominal_minor'];$totals['accepted_minor']+=$n;$s=$r['settlement_status'];if(in_array($s,['sent','bank_credited','received'],true))$totals['settled_minor']+=$n;elseif($r['refund_status']!=='none')$totals['refund_hold_minor']+=$n;elseif($s)$totals['reserved_minor']+=$n;else $totals['available_minor']+=$n;}
 return $totals;
}
function gc_cases_for(array $actor,?string $issuer=null):array {
 if($issuer!==null){gc_member($actor,$issuer);$items=rows('SELECT * FROM pg_support_cases WHERE issuer_id=? ORDER BY created_at DESC LIMIT 100',[$issuer]);}
 else $items=rows('SELECT * FROM pg_support_cases WHERE client_id=? ORDER BY created_at DESC LIMIT 100',[$actor['id']]);
 foreach($items as &$c){$c['messages']=rows('SELECT m.id,m.body,m.created_at,p.email,p.is_staff FROM pg_case_messages m JOIN pm_clients p ON p.id=m.author_id WHERE case_id=? ORDER BY m.created_at,m.id',[$c['id']]);$c['files']=gc_files('case',$c['id']);}return $items;
}
function gc_workflow_route(string $route,string $method,array $d,array $actor,bool &$handled):mixed {
 $handled=true;$get=$method==='GET';$post=$method==='POST';$q=$_GET;
 if($route==='profile/'&&$get)return ['email'=>$actor['email'],'id'=>(int)$actor['id'],'contact'=>one('SELECT full_name,phone FROM pg_contacts WHERE client_id=?',[$actor['id']])];
 if($route==='profile/'&&$post){query('INSERT INTO pg_contacts(client_id,full_name,phone,updated_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),phone=VALUES(phone),updated_at=VALUES(updated_at)',[$actor['id'],txt($d,'name',180,false),txt($d,'phone',60,false),now()]);audit((int)$actor['id'],'gift_profile_updated',(string)$actor['id']);return ['saved'=>true];}
 if($route==='cards/detail/'&&$get){$id=(string)($q['id']??'');$c=gc_owned_card($actor,$id);unset($c['code_ciphertext'],$c['code_hash'],$c['payment_id'],$c['snapshot_json']);$a=one('SELECT a.id,a.buyer_name,a.contract_reference,a.basis,a.status,a.activated_at,p.email actor_email FROM pg_activations a JOIN pm_clients p ON p.id=a.actor_id WHERE a.card_id=?',[$id]);$refunds=rows('SELECT * FROM pg_refunds WHERE card_id=? AND client_id=? ORDER BY created_at',[$id,$actor['id']]);foreach($refunds as &$r)$r['files']=gc_files('refund',$r['id']);return ['card'=>$c,'activation'=>$a,'refunds'=>$refunds];}
 if($route==='files/upload/'&&$post){if($actor['is_staff'])gc_admin_guard('evidence-upload/','POST',$actor);return gc_upload_file($d,$actor);}
 if($route==='files/download/'&&$get){$f=one('SELECT * FROM pg_files WHERE id=?',[(string)($q['id']??'')]);if(!$f)throw new ApiError('Файл не найден',404);gc_object_access($f['object_kind'],$f['object_id'],$actor);require_once __DIR__.'/giftcards-documents.php';gc_send_document($f,$f['original_name']);}
 if($route==='cases/'&&$get)return ['items'=>gc_cases_for($actor,isset($q['issuerId'])?(string)$q['issuerId']:null)];
 if($route==='cases/'&&$post){$issuer=txt($d,'issuerId',36,false);if($issuer!=='')gc_member($actor,$issuer);$topic=txt($d,'topic',200);$body=txt($d,'body',5000);$id=gc_uuid();query('INSERT INTO pg_support_cases(id,issuer_id,client_id,object_ref,topic,body,due_at,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$issuer?:null,$issuer===''?$actor['id']:null,txt($d,'objectRef',100,false),$topic,$body,gmdate('Y-m-d H:i:s',time()+10*86400),$actor['id'],now(),now()]);audit((int)$actor['id'],'gift_case_requested',$id);return ['id'=>$id];}
 if($route==='cases/reply/'&&$post){$id=txt($d,'id',36);gc_object_access('case',$id,$actor);$body=txt($d,'body',5000);return gc_atomic(function()use($id,$body,$actor){$c=one('SELECT * FROM pg_support_cases WHERE id=? FOR UPDATE',[$id]);if($c['status']==='closed')throw new ApiError('Обращение закрыто. Создайте новое.',409);query('INSERT INTO pg_case_messages(id,case_id,body,author_id,created_at) VALUES(?,?,?,?,?)',[gc_uuid(),$id,$body,$actor['id'],now()]);query("UPDATE pg_support_cases SET status='answered',updated_at=? WHERE id=?",[now(),$id]);audit((int)$actor['id'],'gift_case_replied',$id);return ['saved'=>true];});}
 if($route==='merchant/registry/'&&$get){$issuer=(string)($q['issuerId']??'');gc_member($actor,$issuer);$data=gc_admin_list('activations',array_merge($q,['issuerId'=>$issuer,'limit'=>100]));return $data;}
 if($route==='merchant/balance/'&&$get){$issuer=(string)($q['issuerId']??'');gc_member($actor,$issuer);throttle('gift-balance',(string)$actor['id'],60);try{$balance=gc_provider($issuer)->balance();}catch(Throwable){$balance=null;}$ss=rows('SELECT s.*,b.status balance_review_status,b.reconciliation_note FROM pg_settlements s LEFT JOIN pg_balance_returns b ON b.settlement_id=s.id WHERE s.issuer_id=? ORDER BY s.created_at DESC LIMIT 100',[$issuer]);foreach($ss as &$s)$s['expected_net_minor']=gc_settlement_expected($s);return ['provider'=>$balance,'totals'=>gc_merchant_totals($issuer),'settlements'=>$ss];}
 if($route==='merchant/settlement/'&&$get){$data=gc_settlement_detail((string)($q['id']??''));gc_member($actor,$data['settlement']['issuer_id']);return $data;}
 if($route==='merchant/settlements/request/'&&$post){$issuer=txt($d,'issuerId',36);gc_member($actor,$issuer,['owner','accountant']);$note=txt($d,'note',3000);return gc_atomic(function()use($d,$actor,$issuer,$note){$s=gc_prepare_settlement($d,$actor);if(!one("SELECT id FROM pg_settlement_events WHERE settlement_id=? AND kind='recipient_snapshot'",[$s['id']]))gc_settlement_log($s['id'],'recipient_snapshot',0,'Реквизиты',one('SELECT company_json FROM pg_issuers WHERE id=?',[$issuer])['company_json'],$actor);if($s['status']!=='prepared'||one("SELECT id FROM pg_settlement_events WHERE settlement_id=? AND kind='requested'",[$s['id']]))return $s;query("UPDATE pg_settlements SET status='requested' WHERE id=?",[$s['id']]);gc_settlement_log($s['id'],'requested',0,'Заявка поставщика',$note,$actor);return gc_settlement_detail($s['id'])['settlement'];});}
 if($route==='merchant/settlements/ack/'&&$post){$id=txt($d,'settlementId',36);$ref=txt($d,'bankReference',150);$amount=minor($d['amount']??'',100000000000);return gc_atomic(function()use($actor,$id,$ref,$amount){$s=one('SELECT * FROM pg_settlements WHERE id=? FOR UPDATE',[$id]);if(!$s)throw new ApiError('Расчёт не найден',404);gc_member($actor,$s['issuer_id'],['owner','accountant']);if($s['received_at']){if((int)$s['received_minor']!==$amount||$s['received_reference']!==$ref)throw new ApiError('Подтверждение уже зарегистрировано с другими данными',409);return ['status'=>'received'];}if($s['status']!=='bank_credited'||gc_settlement_expected($s)!==$amount)throw new ApiError('Сверьте реестр и фактическую сумму. При расхождении создайте обращение.',409);query("UPDATE pg_settlements SET status='received',received_reference=?,received_minor=?,received_by=?,received_at=? WHERE id=?",[$ref,$amount,$actor['id'],now(),$id]);query("INSERT INTO pg_balance_returns(id,settlement_id,created_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE status='manual_review',reviewed_at=NULL,verified_by=NULL",[gc_uuid(),$id,now()]);gc_settlement_log($id,'received',$amount,$ref,'Поставщик подтвердил банковское поступление',$actor);audit((int)$actor['id'],'gift_settlement_received',$id);return ['status'=>'received','balanceReturn'=>'manual_review'];});}
 if($route==='merchant/statement/'&&$get){$id=(string)($q['id']??'');$s=gc_settlement_detail($id);gc_member($actor,$s['settlement']['issuer_id'],['owner','accountant']);gc_statement($s,$actor);}
 $handled=false;return null;
}
function gc_staff_workflow(string $route,string $method,array $d,array $actor,bool &$handled):mixed {
 $handled=true;$get=$method==='GET';$post=$method==='POST';
 if($route==='settlement/'&&$get)return gc_settlement_detail((string)($_GET['id']??''));
 if($route==='statement/'&&$get){gc_statement(gc_settlement_detail((string)($_GET['id']??'')),$actor);}
 if($route==='card/'&&$get){$id=(string)($_GET['id']??'');$c=gc_card($id);unset($c['code_ciphertext'],$c['code_hash'],$c['payment_id']);return ['card'=>$c,'activation'=>one('SELECT a.*,p.email actor_email FROM pg_activations a JOIN pm_clients p ON p.id=a.actor_id WHERE a.card_id=?',[$id]),'history'=>rows("SELECT a.*,p.email actor_email FROM pm_audit a LEFT JOIN pm_clients p ON p.id=a.actor_id WHERE a.object_ref=? AND a.action LIKE 'gift_%' ORDER BY a.created_at,a.id",[$id]),'refunds'=>rows('SELECT * FROM pg_refunds WHERE card_id=? ORDER BY created_at',[$id])];}
 if(!$post){$handled=false;return null;}
 if($route==='evidence-upload/')return gc_upload_file($d,$actor);
 if($route==='client-role/')return gc_assign_client_role($d,$actor);
 if($route==='settlement-request-review/')return gc_atomic(function()use($d,$actor){$id=txt($d,'settlementId',36);$s=one('SELECT * FROM pg_settlements WHERE id=? FOR UPDATE',[$id]);$next=txt($d,'status',24);if(!$s||!in_array($s['status'],['requested','prepared'],true)||!in_array($next,['prepared','cancelled'],true))throw new ApiError('Заявка уже изменилась',409);$note=txt($d,'note',3000);if($next==='prepared'&&one("SELECT a.id FROM pg_settlement_items i JOIN pg_activations a ON a.id=i.activation_id JOIN pg_cards c ON c.id=a.card_id WHERE i.settlement_id=? AND (a.status<>'confirmed' OR c.refund_status<>'none') LIMIT 1",[$id]))throw new ApiError('Есть спорные карты: отмените реестр и сформируйте новый после рассмотрения',409);query('UPDATE pg_settlements SET status=? WHERE id=?',[$next,$id]);if($next==='cancelled')query('DELETE FROM pg_settlement_locks WHERE settlement_id=?',[$id]);gc_settlement_log($id,$next,0,'Проверка реестра',$note,$actor);audit((int)$actor['id'],'gift_settlement_reviewed',$id,['status'=>$next]);return ['status'=>$next];});
 if($route==='settlement-bank-credit/')return gc_record_bank_credit($d,$actor);
 if($route==='refund-paid/')return gc_record_unused_refund($d,$actor);
 if($route==='settlement-prepare/'){return gc_atomic(function()use($d,$actor){$s=gc_prepare_settlement($d,$actor);if(!one("SELECT id FROM pg_settlement_events WHERE settlement_id=? AND kind='recipient_snapshot'",[$s['id']])){gc_settlement_log($s['id'],'recipient_snapshot',0,'Реквизиты',one('SELECT company_json FROM pg_issuers WHERE id=?',[$s['issuer_id']])['company_json'],$actor);}return $s;});}
 if($route==='case-save/'&&!empty($d['id'])){return gc_atomic(function()use($d,$actor){$id=txt($d,'id',36);gc_object_access('case',$id,$actor);$st=txt($d,'status',20);if(!in_array($st,['open','waiting','answered','closed'],true))throw new ApiError('Неверный статус');query('INSERT INTO pg_case_messages(id,case_id,body,author_id,created_at) VALUES(?,?,?,?,?)',[gc_uuid(),$id,txt($d,'note',5000),$actor['id'],now()]);query('UPDATE pg_support_cases SET status=?,updated_at=? WHERE id=?',[$st,now(),$id]);audit((int)$actor['id'],'gift_case_answered',$id);return ['id'=>$id];});}
 $handled=false;return null;
}
function gc_assign_client_role(array $d,array $actor):array {return gc_atomic(function()use($d,$actor){
 $id=(int)($d['clientId']??0);$c=one('SELECT id,is_staff,email FROM pm_clients WHERE id=? FOR UPDATE',[$id]);if(!$c||$c['is_staff'])throw new ApiError('Выберите учётную запись клиента');
 $role=txt($d,'role',20);$note=txt($d,'note',3000);
 if(!in_array($role,['client','merchant'],true))throw new ApiError('Выберите роль');
 if($role==='client'){query('DELETE FROM pg_issuer_members WHERE client_id=?',[$id]);}
 else{
  $issuer=txt($d,'issuerId',36,false);
  if($issuer===''){$name=txt($d,'companyName',180);$issuer=gc_uuid();$number='PM-'.gmdate('Y').'-'.str_pad((string)allocate('gift_contract'),6,'0',STR_PAD_LEFT);query("INSERT INTO pg_issuers(id,name,company_json,program_json,status,contract_number,created_at,updated_at) VALUES(?,?,?,?,'draft',?,?,?)",[$issuer,$name,json_encode(['name'=>$name,'email'=>$c['email']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'{}',$number,now(),now()]);}
  elseif(!one('SELECT id FROM pg_issuers WHERE id=? FOR UPDATE',[$issuer]))throw new ApiError('Компания не найдена');
  query("INSERT INTO pg_issuer_members(issuer_id,client_id,role) VALUES(?,?,'owner') ON DUPLICATE KEY UPDATE role='owner'",[$issuer,$id]);
 }
 gc_admin_note($actor,'client',(string)$id,$note);audit((int)$actor['id'],'gift_client_role_changed',(string)$id,['role'=>$role,'issuerId'=>$issuer??null]);return ['role'=>$role,'issuerId'=>$issuer??null];
});}
function gc_record_bank_credit(array $d,array $actor):array {return gc_atomic(function()use($d,$actor){
 $id=txt($d,'settlementId',36);$s=one('SELECT * FROM pg_settlements WHERE id=? FOR UPDATE',[$id]);if(!$s)throw new ApiError('Расчёт не найден');$ref=txt($d,'bankReference',150);$amount=minor($d['amount']??'',100000000000);
 if(in_array($s['status'],['bank_credited','received'],true)&&$s['bank_reference']===$ref)return ['status'=>$s['status']];
 if(!in_array($s['status'],['prepared','sent'],true)||($d['creditedVerified']??false)!==true||$amount!==gc_settlement_expected($s))throw new ApiError('Проверьте состояние, сумму и фактическое зачисление',409);
 gc_evidence(txt($d,'evidenceId',36),'settlement',$id);
 if($s['status']==='prepared'&&one("SELECT c.id FROM pg_settlement_items i JOIN pg_activations a ON a.id=i.activation_id JOIN pg_cards c ON c.id=a.card_id WHERE i.settlement_id=? AND c.refund_status<>'none' LIMIT 1",[$id]))throw new ApiError('В реестре есть обращение на возврат. Требуется рассмотрение до подтверждения.',409);
 query('INSERT INTO pg_bank_references(reference,event_kind,object_id,created_at) VALUES(?,?,?,?)',[$ref,'issuer_settlement',$id,now()]);query("UPDATE pg_settlements SET status='bank_credited',bank_reference=?,bank_credited_at=? WHERE id=?",[$ref,now(),$id]);gc_settlement_log($id,'bank_credited',$amount,$ref,'Зачисление проверено по банковскому документу '.$d['evidenceId'],$actor);audit((int)$actor['id'],'gift_settlement_bank_credit',$id,['fileId'=>$d['evidenceId']]);return ['status'=>'bank_credited'];
});}
function gc_record_unused_refund(array $d,array $actor):array {return gc_atomic(function()use($d,$actor){
 $id=txt($d,'refundId',36);$r=one('SELECT * FROM pg_refunds WHERE id=? FOR UPDATE',[$id]);if(!$r)throw new ApiError('Обращение не найдено');$c=gc_card($r['card_id']);$amount=minor($d['amount']??'',100000000000);$ref=txt($d,'bankReference',150);
 if($r['status']==='paid'){if((int)$r['amount_minor']!==$amount||$r['bank_reference']!==$ref)throw new ApiError('Возврат уже записан с другими реквизитами',409);return ['status'=>'paid'];}
 if($amount!==(int)$c['nominal_minor']||$c['payment_status']!=='confirmed'||$c['issuance_status']!=='cancelled'||$c['redemption_status']!=='not_redeemed')throw new ApiError('Сначала подтвердите оплату и аннулирование неиспользованной карты',409);
 gc_evidence(txt($d,'evidenceId',36),'refund',$id);query('INSERT INTO pg_bank_references(reference,event_kind,object_id,created_at) VALUES(?,?,?,?)',[$ref,'gift_refund',$id,now()]);$body='Неиспользованная предоплата возвращена. Банковское основание: '.$ref;query("UPDATE pg_refunds SET status='paid',amount_minor=?,bank_reference=?,paid_at=?,resolution=? WHERE id=?",[$amount,$ref,now(),$body,$id]);query("UPDATE pg_cards SET refund_status='paid',updated_at=? WHERE id=?",[now(),$c['id']]);audit((int)$actor['id'],'gift_refund_paid',$id,['fileId'=>$d['evidenceId']]);gc_card_mail($c['id'],'Возврат Pina Market выполнен',$body);return ['status'=>'paid'];
});}
function gc_statement(array $data,array $actor):never {
 $s=$data['settlement'];$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$money=fn($n)=>number_format((int)$n/100,2,',',' ').' RUB';
 header('Content-Type: text/html; charset=utf-8');header('Content-Disposition: attachment; filename="Pina-settlement-'.$s['id'].'.html"');header('Cache-Control: private,no-store');
 echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Расчёт Pina Market</title><style>body{font:12pt "Times New Roman",serif;max-width:180mm;margin:20mm auto;line-height:1.2}h1,h2{font-size:12pt}table{width:100%;border-collapse:collapse;margin:16px 0}td,th{border:1px solid #bbb;padding:6px;text-align:left}thead{display:table-header-group}@media print{@page{size:A4 landscape;margin:20mm}body{max-width:none;margin:0}}</style><h1>Реестр активаций и расчёт взаиморасчётов</h1><p>Реестр № '.$e($s['id']).'. Дата формирования '.$e(gmdate('d.m.Y H:i')).' UTC.</p><p>Агент: Pina Market LLC. Эмитент: '.$e($s['name']).'.</p><table><tr><th>Номинал</th><th>Вознаграждение 0,1%</th><th>Валютный контроль 0,2%</th><th>Перевод 0,6%</th><th>К перечислению</th></tr><tr>';foreach(['gross_minor','agent_fee_minor','control_fee_minor','transfer_fee_minor','expected_net_minor'] as $k)echo '<td>'.$e($money($s[$k])).'</td>';echo '</tr></table><table><thead><tr><th>Карта</th><th>Покупатель</th><th>Договор / заказ</th><th>Дата активации UTC</th><th>Номинал</th></tr></thead><tbody>';foreach($data['items'] as $a)echo '<tr><td>'.$e($a['card_id']).'</td><td>'.$e($a['buyer_name']).'</td><td>'.$e($a['contract_reference']).'</td><td>'.$e($a['activated_at']).'</td><td>'.$e($money($a['nominal_minor'])).'</td></tr>';echo '</tbody></table><p>Статус расчёта: '.$e($s['status']).'. Банковское основание: '.$e($s['bank_reference']??'пока не зарегистрировано').'.</p><p>Эмитент подтверждает поступление отдельным действием в кабинете и прикладывает свои подтверждающие документы. Этот реестр не подтверждает оказание основной услуги или передачу товара и не содержит подписи, которая не была предоставлена.</p><p>Агент __________________ / Эмитент __________________</p></html>';
 audit((int)$actor['id'],'gift_settlement_statement_exported',$s['id']);exit;
}
