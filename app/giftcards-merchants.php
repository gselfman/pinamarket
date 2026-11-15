<?php
declare(strict_types=1);
function gc_credentials(string $scope):array {
 $r=one('SELECT * FROM pg_merchant_credentials WHERE scope_id=?',[$scope]);
 if($r)return ['merchant_id'=>$r['merchant_id'],'site_id'=>$r['site_id'],'recipient_login'=>$r['recipient_login'],'api_key'=>gc_decrypt($r['api_key_ciphertext']),'verified_at'=>$r['verified_at']];
 return $scope==='pina'?(gc_settings()['pina']??[]):(gc_settings()['issuers'][$scope]??[]);
}
function gc_credentials_public(string $scope):array {
 $c=gc_credentials($scope);return ['merchantId'=>$c['merchant_id']??'','siteId'=>$c['site_id']??'','recipientLogin'=>$c['recipient_login']??'','hasApiKey'=>!empty($c['api_key']),'verifiedAt'=>$c['verified_at']??null];
}
function gc_merchant_overview():array {
 $items=[['id'=>'pina','name'=>'Pina Market — выпуск карт','status'=>'platform']+gc_credentials_public('pina')];
 foreach(rows('SELECT id,name,status FROM pg_issuers ORDER BY created_at DESC LIMIT 250') as $i)$items[]=$i+gc_credentials_public($i['id']);
 return ['items'=>$items];
}
function gc_save_merchant(array $d,array $actor):array {
 $scope=txt($d,'scopeId',36);if($scope!=='pina'&&!one('SELECT id FROM pg_issuers WHERE id=?',[$scope]))throw new ApiError('Компания не найдена',404);
 $old=gc_credentials($scope);$key=txt($d,'apiKey',256,false);if($key==='')$key=$old['api_key']??'';
 $merchant=strtolower(txt($d,'merchantId',36));$site=strtolower(txt($d,'siteId',36));$login=txt($d,'recipientLogin',140);
 foreach([$merchant,$site] as $id)if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id))throw new ApiError('Проверьте UUID мерчанта и торговой точки');
 if(!preg_match('/^[A-Za-z0-9_.-]{1,140}$/D',$login)||!preg_match('/^[A-Za-z0-9_-]{10,256}$/D',$key))throw new ApiError('Проверьте логин и API-ключ');
 if(($d['identityVerified']??false)!==true)throw new ApiError('Подтвердите соответствие мерчанта выбранной компании');
 if($scope!=='pina'){$pina=gc_credentials('pina');if(($pina['merchant_id']??'')===$merchant||($pina['site_id']??'')===$site)throw new ApiError('Поставщику нужны собственные идентификаторы, отличные от Pina Market');}
 throttle('gift-merchant-config',(string)$actor['id'],15);
 try{$provider=(PHP_SAPI==='cli'&&defined('PINA_LIBRARY_ONLY')&&isset($GLOBALS['gc_test_credentials_probe']))?($GLOBALS['gc_test_credentials_probe'])($key,$site):new VdarClient($key,$site);$balance=$provider->balance();}
 catch(Throwable){throw new ApiError('Не удалось подтвердить доступ ключа к торговой точке. Настройки не изменены.',409);}
 if(($balance['site_id']??'')!==$site||($balance['site_login']??'')!==$login)throw new ApiError('Ответ V-DAR не соответствует торговой точке и логину',409);
 return transact(function()use($scope,$merchant,$site,$login,$key,$actor,$old){
  if($scope!=='pina')one('SELECT id FROM pg_issuers WHERE id=? FOR UPDATE',[$scope]);
  $existing=one('SELECT scope_id FROM pg_merchant_credentials WHERE scope_id=? FOR UPDATE',[$scope]);
  $conflict=one('SELECT scope_id FROM pg_merchant_credentials WHERE scope_id<>? AND (merchant_id=? OR site_id=? OR recipient_login=?)',[$scope,$merchant,$site,$login]);
  if($conflict)throw new ApiError('Эти идентификаторы уже привязаны к другой компании',409);
  // Freeze the payment destination once orders exist; key rotation remains possible.
  $hasOrders=$scope==='pina'?one('SELECT id FROM pg_orders LIMIT 1'):one('SELECT id FROM pg_orders WHERE issuer_id=? LIMIT 1',[$scope]);
  $changed=(!empty($old['merchant_id'])&&$old['merchant_id']!==$merchant)||(!empty($old['site_id'])&&$old['site_id']!==$site)||(!empty($old['recipient_login'])&&$old['recipient_login']!==$login);
  if($hasOrders&&$changed)throw new ApiError('По программе уже есть заказы. Получателя нельзя менять; разрешена замена ключа с теми же идентификаторами.',409);
  if($scope==='pina'){$pending=one("SELECT id FROM pg_commands WHERE status IN ('sending','unknown') LIMIT 1");}
  else $pending=one("SELECT q.id FROM pg_commands q JOIN pg_cards c ON c.id=q.card_id JOIN pg_orders o ON o.id=c.order_id WHERE o.issuer_id=? AND q.status IN ('sending','unknown') LIMIT 1",[$scope]);
  if($pending)throw new ApiError('Сначала завершите сверку операций с неопределённым результатом',409);
  if($existing)query('UPDATE pg_merchant_credentials SET merchant_id=?,site_id=?,recipient_login=?,api_key_ciphertext=?,verified_at=?,updated_by=?,updated_at=? WHERE scope_id=?',[$merchant,$site,$login,gc_encrypt($key),now(),$actor['id'],now(),$scope]);
  else query('INSERT INTO pg_merchant_credentials(scope_id,merchant_id,site_id,recipient_login,api_key_ciphertext,verified_at,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,?)',[$scope,$merchant,$site,$login,gc_encrypt($key),now(),$actor['id'],now()]);
  audit((int)$actor['id'],'gift_merchant_settings_saved',$scope,['merchantId'=>$merchant,'siteId'=>$site]);
  return ['saved'=>true]+gc_credentials_public($scope);
 });
}
