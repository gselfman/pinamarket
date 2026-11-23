<?php
declare(strict_types=1);

function gc_program_confirmation_hash(array $issuer):string {
 return hash('sha256',json_encode([$issuer['company_json'],$issuer['program_json'],$issuer['contract_number']],JSON_THROW_ON_ERROR));
}
function gc_program_confirmed(array $issuer):bool {
 $confirmation=one('SELECT content_hash FROM pg_program_confirmations WHERE issuer_id=?',[$issuer['id']]);
 return $confirmation!==null&&hash_equals($confirmation['content_hash'],gc_program_confirmation_hash($issuer));
}
function gc_confirm_program(array $d,array $actor):array {
 return transact(function()use($d,$actor){
  $id=txt($d,'issuerId',36);$i=one('SELECT * FROM pg_issuers WHERE id=? FOR UPDATE',[$id]);
  if(!$i)throw new ApiError('Компания не найдена',404);
  $program=json_decode($i['program_json'],true,512,JSON_THROW_ON_ERROR);
  gc_program_required($program);gc_program_location($program);
  if(isset($d['program'])&&$d['program']!==$program)throw new ApiError('Условия карт изменились. Обновите карточку поставщика.',409);
  $configured=gc_credentials($id);$issues=gc_merchant_setup_issues(gc_credentials_public($id));
  if($issues)throw new ApiError('Заполните настройки V-DAR торговой точки: '.implode('; ',$issues),409);
  if((!empty($d['siteId'])&&$d['siteId']!==$configured['site_id'])||(!empty($d['recipientLogin'])&&$d['recipientLogin']!==$configured['recipient_login']))throw new ApiError('Настройки торговой точки изменились. Обновите карточку поставщика.',409);
  $readiness=gc_contract_readiness($i);$warnings=$readiness['ready']?[]:['Программа подтверждена без принятого подписанного договора. Договор можно загрузить и проверить отдельно.'];
  $hash=gc_program_confirmation_hash($i);$old=one('SELECT * FROM pg_program_confirmations WHERE issuer_id=?',[$id]);
  if(!$old||!hash_equals($old['content_hash'],$hash)||$i['status']!=='active'){
   query('INSERT INTO pg_program_confirmations(issuer_id,content_hash,confirmed_by,confirmed_at,contract_warning) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE content_hash=VALUES(content_hash),confirmed_by=VALUES(confirmed_by),confirmed_at=VALUES(confirmed_at),contract_warning=VALUES(contract_warning)',[$id,$hash,$actor['id'],now(),implode(' ',$warnings)]);
   query("UPDATE pg_issuers SET status='active',vdar_site_id=?,recipient_login=?,updated_at=? WHERE id=?",[$configured['site_id'],$configured['recipient_login'],now(),$id]);
   audit((int)$actor['id'],'gift_program_confirmed',$id,['contentHash'=>$hash,'contractWarnings'=>$warnings]);
  }
  return ['status'=>'active','warnings'=>$warnings];
 });
}
