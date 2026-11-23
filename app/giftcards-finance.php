<?php
declare(strict_types=1);
function gc_settlement_event(array $d,array $actor):array {return transact(function()use($d,$actor){
 $id=txt($d,'settlementId',36);$kind=txt($d,'kind',30);$key=request_key($d);$old=one('SELECT * FROM pg_settlement_events WHERE request_key=?',[$key]);if($old){if($old['settlement_id']!==$id||$old['kind']!==$kind||$old['reference']!==($d['reference']??'')||$old['body']!==(($d['note']??'').($kind==='adjustment'?"\nКонтроль: ".($d['controlBasis']??''):''))||($kind!=='reopen'&&(int)$old['amount_minor']!==minor(ltrim((string)($d['amount']??''),'+-'),100000000000)*(str_starts_with((string)($d['amount']??''),'-')?-1:1)))throw new ApiError('Ключ использован для другой операции',409);return ['saved'=>true,'duplicate'=>true];}
 $s=one('SELECT * FROM pg_settlements WHERE id=? FOR UPDATE',[$id]);if(!$s)throw new ApiError('Реестр не найден');$ref=txt($d,'reference',200);$note=txt($d,'note',5000);$amount=0;$next=$s['status'];
 gc_evidence(txt($d,'evidenceId',36),'settlement',$id);
 if(($d['evidenceVerified']??false)!==true)throw new ApiError('Подтвердите проверку основания операции');
 if($kind==='adjustment'){
  if($s['status']!=='prepared'||($d['controlConfirmed']??false)!==true)throw new ApiError('Корректировка возможна до отправки с документированным контролем',409);
  $raw=txt($d,'amount',30);$negative=str_starts_with($raw,'-');$amount=minor(ltrim($raw,'+-'),100000000000)*($negative?-1:1);$expected=gc_settlement_expected($s)+$amount;
  if($amount===0||$expected<=0||$expected>(int)$s['gross_minor'])throw new ApiError('Проверьте величину корректировки');$note.="\nКонтроль: ".txt($d,'controlBasis',2000);
 }elseif($kind==='sent'){
  if($s['status']!=='prepared')throw new ApiError('Перевод уже отправлен или требует разбирательства',409);
  if(one("SELECT a.id FROM pg_settlement_items si JOIN pg_activations a ON a.id=si.activation_id JOIN pg_cards c ON c.id=a.card_id WHERE si.settlement_id=? AND c.refund_status<>'none' LIMIT 1",[$id]))throw new ApiError('В реестре появились обращения на возврат. Сначала рассмотрите их.',409);
  $amount=minor($d['amount']??'',100000000000);if($amount!==gc_settlement_expected($s))throw new ApiError('Сумма не совпадает с реестром');$next='sent';
 }elseif($kind==='returned'){
  if(!in_array($s['status'],['sent','bank_credited','received'],true))throw new ApiError('Для этого реестра нет отправленного перевода');$amount=minor($d['amount']??'',100000000000);if($amount!==gc_settlement_expected($s))throw new ApiError('Частичный возврат требует отдельного обращения и сверки; не отмечайте возврат всего перевода.');$next='returned';
  query("UPDATE pg_balance_returns SET status='manual_review' WHERE settlement_id=?",[$id]);
 }elseif($kind==='reopen'){
  if($s['status']!=='returned')throw new ApiError('Повторная подготовка доступна после подтверждённого возврата перевода');
  if($s['bank_reference']){query('INSERT INTO pg_settlement_events(id,settlement_id,kind,amount_minor,reference,body,request_key,actor_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[gc_uuid(),$id,'previous_credit',gc_settlement_expected($s),$s['bank_reference'],'Предыдущее зачисление перед возвратом',gc_uuid(),$actor['id'],now()]);}
  query('UPDATE pg_settlements SET bank_reference=NULL,bank_credited_at=NULL,received_reference=NULL,received_minor=NULL,received_by=NULL,received_at=NULL WHERE id=?',[$id]);$next='prepared';
 }else throw new ApiError('Неизвестное событие расчёта');
 if(in_array($kind,['sent','returned'],true)){if(strlen($ref)>150)throw new ApiError('Банковское основание до 150 символов');query('INSERT INTO pg_bank_references(reference,event_kind,object_id,created_at) VALUES(?,?,?,?)',[$ref,'settlement_'.$kind,$id,now()]);}
 query('INSERT INTO pg_settlement_events(id,settlement_id,kind,amount_minor,reference,body,request_key,actor_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[gc_uuid(),$id,$kind,$amount,$ref,$note,$key,$actor['id'],now()]);
 query('UPDATE pg_settlements SET status=? WHERE id=?',[$next,$id]);audit((int)$actor['id'],'gift_settlement_'.$kind,$id,['amount_minor'=>$amount,'reference'=>$ref]);return ['saved'=>true,'status'=>$next];
});}
function gc_refund_resolve(array $d,array $actor):array {return transact(function()use($d,$actor){
 $id=txt($d,'refundId',36);$r=one('SELECT * FROM pg_refunds WHERE id=? FOR UPDATE',[$id]);if(!$r)throw new ApiError('Обращение не найдено');$c=gc_card($r['card_id']);$status=txt($d,'status',24);$resolution=txt($d,'resolution',5000);
 if(in_array($r['status'],['paid','partially_paid'],true))throw new ApiError('Выполненный возврат нельзя переписать',409);
 if(!in_array($status,['review','awaiting_issuer','approved','rejected','paid'],true))throw new ApiError('Выберите допустимое решение');
 if($status==='rejected'&&$c['redemption_status']!=='redeemed')throw new ApiError('Неиспользованный аванс нельзя отклонить только по факту создания кода');
 if(in_array($status,['paid','rejected'],true)&&($d['groundsVerified']??false)!==true)throw new ApiError('Подтвердите проверку оснований решения');
 if($status==='paid'){
  if($c['redemption_status']!=='redeemed')throw new ApiError('Для неиспользованной карты сначала аннулируйте код и выполните обычный возврат');
  $amount=minor($d['amount']??'',100000000000);if($amount<=0||$amount>(int)$c['nominal_minor'])throw new ApiError('Проверьте сумму возврата');$ref=txt($d,'bankReference',150);
  gc_evidence(txt($d,'evidenceId',36),'refund',$id);
  query('INSERT INTO pg_bank_references(reference,event_kind,object_id,created_at) VALUES(?,?,?,?)',[$ref,'redeemed_card_refund',$id,now()]);$status=$amount===(int)$c['nominal_minor']?'paid':'partially_paid';query('UPDATE pg_refunds SET status=?,resolution=?,amount_minor=?,bank_reference=?,paid_at=? WHERE id=?',[$status,$resolution,$amount,$ref,now(),$id]);query('UPDATE pg_cards SET refund_status=?,updated_at=? WHERE id=?',[$status,now(),$c['id']]);
 }else{query('UPDATE pg_refunds SET status=?,resolution=? WHERE id=?',[$status,$resolution,$id]);if($status==='rejected')query("UPDATE pg_cards SET refund_status='none',updated_at=? WHERE id=?",[now(),$c['id']]);}
 gc_admin_note($actor,'refund',$id,$resolution);audit((int)$actor['id'],'gift_refund_decision',$id,['status'=>$status]);gc_admin_mail(one('SELECT email FROM pm_clients WHERE id=?',[$r['client_id']])['email'],'Решение по обращению Pina Market',$resolution."\nСтатус: ".$status."\nОбращение: ".$id);return ['status'=>$status];
});}
