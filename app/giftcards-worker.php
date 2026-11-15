<?php
declare(strict_types=1);
require_once __DIR__.'/giftcards.php';
function gc_run_one():bool {
 $command=transact(function(){
  $r=one("SELECT * FROM pg_commands WHERE status='queued' ORDER BY created_at,id LIMIT 1 FOR UPDATE");if(!$r)return null;
  query("UPDATE pg_commands SET status='sending',attempts=attempts+1,claimed_at=? WHERE id=?",[now(),$r['id']]);return $r;
 });
 if(!$command)return false;
 $id=$command['card_id'];$kind=$command['kind'];
 try {
  $card=gc_card($id);$issuer=one('SELECT * FROM pg_issuers WHERE id=?',[$card['issuer_id']]);
  if(!$issuer)throw new RuntimeException('ISSUER_MISSING');
  if($kind==='issue'){
   // A refund submitted before worker claim must stop issue. A concurrent later refund
   // remains a claim against an issued card; it never causes an untracked second issue.
   if($card['refund_status']!=='none')throw new VdarRejected('REFUND_PENDING');
   $snapshot=json_decode($card['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
   $recipient=$snapshot['vdar_recipient']??null;if(!$recipient)throw new RuntimeException('RECIPIENT_NOT_CONFIGURED');
   $expires=(int)($snapshot['program']['expires_seconds']??0);
   $result=gc_provider()->generate((int)$card['nominal_minor'],'PINA-'.$id,$recipient,$card['payment_id'],$expires);
   $encrypted=gc_encrypt($result['code']);$codeHash=gc_code_hash($result['code']);
   transact(function()use($id,$result,$command,$encrypted,$codeHash){query("UPDATE pg_cards SET issuance_status='issued',provider_id=?,code_ciphertext=?,code_hash=?,expires_at=?,updated_at=? WHERE id=?",[$result['id'],$encrypted,$codeHash,!empty($result['expires_at'])?gmdate('Y-m-d H:i:s',strtotime($result['expires_at'])):null,now(),$id]);query("UPDATE pg_commands SET status='completed',completed_at=? WHERE id=?",[now(),$command['id']]);gc_card_mail($id,'Код карты Pina Market готов','Доступ к коду открыт в личном кабинете. Передавайте код только выбранному поставщику.');audit(null,'gift_code_created',$id);});
  }elseif($kind==='activate'){
   $activation=one('SELECT * FROM pg_activations WHERE id=?',[$command['activation_id']]);
   if(!$activation||$activation['issuer_id']!==$card['issuer_id'])throw new RuntimeException('ACTIVATION_MISMATCH');
   if($card['refund_status']!=='none')throw new VdarRejected('REFUND_PENDING');
   $code=gc_decrypt($card['code_ciphertext']);$provider=gc_provider($card['issuer_id']);$info=$provider->check($code);
   if(($info['status']??'')==='activated')throw new VdarUncertain('EXTERNAL_ACTIVATION_REQUIRES_RECONCILIATION');
   if(($info['exists']??false)!==true||($info['is_valid']??false)!==true||($info['recipient_login']??'')!==$issuer['recipient_login']||($info['currency_code']??'')!=='RUB'||VdarClient::minor($info['amount']??'0')!==(int)$card['nominal_minor'])throw new VdarRejected('CODE_NOT_VALID_FOR_ISSUER');
   $result=$provider->activate($code);
   if(VdarClient::minor($result['activated_amount']??'0')!==(int)$card['nominal_minor'])throw new VdarUncertain('ACTIVATION_AMOUNT_MISMATCH');
   $commission=VdarClient::minor($result['commission_charged']??'0');
   transact(function()use($id,$result,$command,$commission){query("UPDATE pg_cards SET redemption_status='redeemed',updated_at=? WHERE id=?",[now(),$id]);query("UPDATE pg_activations SET status='confirmed',provider_operation_id=?,provider_commission_minor=?,activated_at=? WHERE id=?",[$result['operation_id'],$commission,!empty($result['activated_at'])?gmdate('Y-m-d H:i:s',strtotime($result['activated_at'])):now(),$command['activation_id']]);query("UPDATE pg_commands SET status='completed',completed_at=? WHERE id=?",[now(),$command['id']]);gc_card_mail($id,'Поставщик принял карту','Поставщик подтвердил приём карты в счёт вашего договора или заказа. Это не подтверждение передачи товара или оказания услуги.');audit(null,'gift_code_activated',$id);});
  }elseif($kind==='cancel'){
   if(!in_array($card['refund_status'],['requested','review','approved','awaiting_issuer'],true)||$card['redemption_status']!=='not_redeemed'||$card['issuance_status']!=='issued')throw new VdarRejected('CANCELLATION_NOT_ALLOWED');
   $result=gc_provider()->cancel(gc_decrypt($card['code_ciphertext']));if(($result['success']??false)!==true)throw new VdarUncertain('CANCELLATION_UNKNOWN');
   transact(function()use($id,$command){query("UPDATE pg_cards SET issuance_status='cancelled',updated_at=? WHERE id=?",[now(),$id]);query("UPDATE pg_commands SET status='completed',completed_at=? WHERE id=?",[now(),$command['id']]);audit(null,'gift_code_cancelled',$id);});
  }else throw new RuntimeException('INVALID_COMMAND_KIND');
 }catch(VdarRejected $e){
  // Only a definite provider rejection before any successful mutation can be failed.
  $ambiguous=in_array($e->safeCode,['CODE_ALREADY_ACTIVATED'],true);
  gc_command_problem($command,$ambiguous?'unknown':'failed',$e->safeCode);
 }catch(Throwable){gc_command_problem($command,'unknown','RESULT_REQUIRES_RECONCILIATION');}
 return true;
}
function gc_command_problem(array $command,string $status,string $safeCode):void {
 transact(function()use($command,$status,$safeCode){query('UPDATE pg_commands SET status=?,error_code=? WHERE id=?',[$status,$safeCode,$command['id']]);$column=$command['kind']==='issue'?'issuance_status':'redemption_status';if($command['kind']!=='cancel')query('UPDATE pg_cards SET '.$column.'=?,updated_at=? WHERE id=?',[$status==='unknown'?'unknown':($command['kind']==='issue'?'not_issued':'not_redeemed'),now(),$command['card_id']]);if($command['activation_id'])query('UPDATE pg_activations SET status=? WHERE id=?',[$status,$command['activation_id']]);audit(null,'gift_command_'.$status,$command['id'],['kind'=>$command['kind'],'reason'=>$safeCode]);});
}
// Read-only recovery. A zero-result or partial list never proves that generate failed.
// The original POST is not re-sent by this function or by the cron worker.
function gc_reconcile_issue(string $cardId):array {
 $card=gc_card($cardId);$command=one("SELECT * FROM pg_commands WHERE card_id=? AND kind='issue'",[$cardId]);
 if(!$command||!in_array($command['status'],['sending','unknown'],true))throw new ApiError('Нет неопределённого выпуска',409);
 $provider=gc_provider();$matches=[];
 for($page=1;$page<=100;$page++){
  $list=$provider->listCodes('PINA-'.$cardId,$page);
  foreach(($list['items']??[]) as $item){if(($item['name']??'')!=='PINA-'.$cardId||empty($item['code']))continue;$info=$provider->check($item['code']);if(($info['payment_id']??'')===$card['payment_id'])$matches[]=[$item,$info];}
  if($page>=(int)($list['pages']??1))break;
 }
 if(count($matches)!==1)return ['status'=>'unknown','matches'=>count($matches)];
 [$item,$info]=$matches[0];$issuer=one('SELECT recipient_login FROM pg_issuers WHERE id=?',[$card['issuer_id']]);
 if(($info['currency_code']??'')!=='RUB'||VdarClient::minor($info['amount']??'0')!==(int)$card['nominal_minor']||($info['recipient_login']??'')!==$issuer['recipient_login']||($info['status']??'')!=='active')return ['status'=>'unknown','reason'=>'provider_state_requires_manual_review'];
 transact(function()use($cardId,$item,$info,$command){query("UPDATE pg_cards SET issuance_status='issued',provider_id=?,code_ciphertext=?,code_hash=?,expires_at=?,updated_at=? WHERE id=?",[$item['id'],gc_encrypt($item['code']),gc_code_hash($item['code']),!empty($info['expires_at'])?gmdate('Y-m-d H:i:s',strtotime($info['expires_at'])):null,now(),$cardId]);query("UPDATE pg_commands SET status='completed',completed_at=?,error_code=NULL WHERE id=?",[now(),$command['id']]);audit(null,'gift_issue_reconciled',$cardId);});
 return ['status'=>'issued'];
}
// Recover a timed-out cancellation by reading the original code only. Never DELETE twice.
function gc_reconcile_cancel(string $id,array $actor):array {
 $c=gc_card($id);$q=one("SELECT * FROM pg_commands WHERE card_id=? AND kind='cancel'",[$id]);
 if(!$q||!in_array($q['status'],['unknown','sending'],true)||$c['redemption_status']!=='not_redeemed'||!in_array($c['refund_status'],['requested','review','approved','awaiting_issuer'],true))throw new ApiError('Нет неопределённого аннулирования неиспользованной карты',409);
 $r=gc_provider()->check(gc_decrypt($c['code_ciphertext']));
 if(($r['exists']??false)!==true||($r['status']??'')!=='deactivated'||($r['currency_code']??'')!=='RUB'||VdarClient::minor($r['amount']??'0')!==(int)$c['nominal_minor']||($r['payment_id']??'')!==$c['payment_id'])return ['status'=>'unknown'];
 return transact(function()use($c,$q,$actor){$card=one('SELECT * FROM pg_cards WHERE id=? FOR UPDATE',[$c['id']]);$cmd=one('SELECT * FROM pg_commands WHERE id=? FOR UPDATE',[$q['id']]);if($card['redemption_status']!=='not_redeemed'||!in_array($cmd['status'],['unknown','sending'],true))throw new ApiError('Данные изменились; обновите карточку',409);query("UPDATE pg_cards SET issuance_status='cancelled',updated_at=? WHERE id=?",[now(),$c['id']]);query("UPDATE pg_commands SET status='completed',error_code=NULL,completed_at=? WHERE id=?",[now(),$q['id']]);audit((int)$actor['id'],'gift_cancellation_reconciled',$c['id']);return ['status'=>'cancelled'];});
}
