<?php
declare(strict_types=1);
function gc_lock_pina_credentials():void {one("SELECT scope_id FROM pg_merchant_credentials WHERE scope_id='pina' FOR UPDATE");}
function gc_pina_login():string {return (string)(gc_settings()['vdar_merchant_login']??'PINA');}
function gc_raw_credentials(string $scope):array {
 $r=one('SELECT * FROM pg_merchant_credentials WHERE scope_id=?',[$scope]);
 if($r)return ['merchant_id'=>$r['merchant_id'],'site_id'=>$r['site_id'],'recipient_login'=>$r['recipient_login'],'api_key'=>gc_decrypt($r['api_key_ciphertext']),'verified_at'=>$r['verified_at']];
 return $scope==='pina'?(gc_settings()['pina']??[]):(gc_settings()['issuers'][$scope]??[]);
}
function gc_credentials(string $scope):array {
 $pina=gc_raw_credentials('pina');$merchantLogin=gc_pina_login();
 if($scope==='pina')return array_replace($pina,['mode'=>'platform','merchant_login'=>$merchantLogin,'site_login'=>$pina['recipient_login']??'']);
 $point=one('SELECT * FROM pg_vdar_supplier_points WHERE issuer_id=?',[$scope]);
 if($point)return ['mode'=>'shared','merchant_id'=>$pina['merchant_id']??'','merchant_login'=>$merchantLogin,'site_id'=>$point['site_id'],'site_login'=>$point['site_login'],'recipient_login'=>$point['recipient_login'],'api_key'=>$pina['api_key']??'','verified_at'=>$point['verified_at']];
 $old=gc_raw_credentials($scope);if($old)return $old+['mode'=>'legacy','merchant_login'=>'','site_login'=>str_contains($old['recipient_login']??'','.')?substr($old['recipient_login'],strrpos($old['recipient_login'],'.')+1):($old['recipient_login']??'')];
 return ['mode'=>'shared','merchant_id'=>$pina['merchant_id']??'','merchant_login'=>$merchantLogin,'site_id'=>'','site_login'=>'','recipient_login'=>'','api_key'=>$pina['api_key']??''];
}
function gc_credentials_public(string $scope):array {
 $c=gc_credentials($scope);return ['mode'=>$c['mode'],'merchantId'=>$c['merchant_id']??'','merchantLogin'=>$c['merchant_login']??'','siteId'=>$c['site_id']??'','siteLogin'=>$c['site_login']??'','recipientLogin'=>$c['recipient_login']??'','hasApiKey'=>!empty($c['api_key']),'verifiedAt'=>$c['verified_at']??null];
}
function gc_merchant_setup_issues(array $merchant):array {
 if(($merchant['mode']??'')==='legacy')return ['Подключите точку поставщика к общему мерчанту Pina Market: укажите её ID и короткий логин'];
 $issues=[];
 if(!is_string($merchant['merchantId']??null)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di',$merchant['merchantId']))$issues[]='Настройте общий ID мерчанта Pina Market';
 if(!is_string($merchant['siteId']??null)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di',$merchant['siteId']))$issues[]='Укажите ID торговой точки (siteId)';
 if(empty($merchant['hasApiKey']))$issues[]='Настройте общий секретный ключ Pina Market';
 if(($merchant['mode']??'')==='shared'){
  if(!preg_match('/^[\p{L}\p{N}_-]{1,64}$/uD',(string)($merchant['siteLogin']??'')))$issues[]='Укажите короткий логин торговой точки без точек';
  if(($merchant['recipientLogin']??'')!==($merchant['merchantLogin']??'').'.'.($merchant['siteLogin']??'')||empty($merchant['siteLogin']))$issues[]='Сохраните точного получателя в формате PINA.логин_точки';
 }
 return array_values(array_unique($issues));
}
function gc_merchant_overview():array {
 $items=[['id'=>'pina','name'=>'Pina Market — выпуск карт','status'=>'platform']+gc_credentials_public('pina')];
 foreach(rows('SELECT id,name,status FROM pg_issuers ORDER BY created_at DESC LIMIT 250') as $i)$items[]=$i+gc_credentials_public($i['id']);return ['items'=>$items];
}
function gc_probe_point(string $key,string $site):array {
 try{$provider=(PHP_SAPI==='cli'&&defined('PINA_LIBRARY_ONLY')&&isset($GLOBALS['gc_test_credentials_probe']))?($GLOBALS['gc_test_credentials_probe'])($key,$site):new VdarClient($key,$site);$balance=$provider->balance();}
 catch(Throwable){throw new ApiError('Общий ключ Pina Market не имеет доступа к этой торговой точке. Проверьте ID точки в V-DAR; настройки не изменены.',409);}
 if(($balance['site_id']??'')!==$site||!is_string($balance['site_login']??null)||$balance['site_login']==='')throw new ApiError('V-DAR не подтвердил ID и логин торговой точки',409);return $balance;
}
function gc_save_merchant(array $d,array $actor):array {
 $scope=txt($d,'scopeId',36);if(($d['identityVerified']??false)!==true)throw new ApiError('Подтвердите соответствие торговой точки выбранной компании');
 throttle('gift-merchant-config',(string)$actor['id'],15);
 if($scope!=='pina')return gc_save_supplier_point($scope,$d,$actor);
 $old=gc_credentials('pina');$key=txt($d,'apiKey',256,false)?:($old['api_key']??'');$merchant=strtolower(txt($d,'merchantId',36));$site=strtolower(txt($d,'siteId',36));$merchantLogin=txt($d,'merchantLogin',64,false)?:gc_pina_login();
 foreach([$merchant,$site] as $id)if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id))throw new ApiError('Проверьте UUID мерчанта и точки выпуска');
 if(!preg_match('/^[\p{L}\p{N}_-]{1,64}$/uD',$merchantLogin)||!preg_match('/^[A-Za-z0-9_-]{10,256}$/D',$key))throw new ApiError('Проверьте логин мерчанта и секретный ключ Pina Market');
 $balance=gc_probe_point($key,$site);$login=$balance['site_login'];$provided=txt($d,'siteLogin',140,false)?:txt($d,'recipientLogin',140,false);
 if($provided!==''&&$provided!==$login&&$merchantLogin.'.'.$login!==$provided)throw new ApiError('Короткий логин точки не совпадает с ответом V-DAR',409);
 return transact(function()use($merchant,$site,$login,$merchantLogin,$key,$actor,$old){
  $existing=one("SELECT * FROM pg_merchant_credentials WHERE scope_id='pina' FOR UPDATE");
  if($existing&&(!hash_equals(gc_decrypt($existing['api_key_ciphertext']),$old['api_key']??'')||$existing['site_id']!==($old['site_id']??'')||$existing['merchant_id']!==($old['merchant_id']??'')))throw new ApiError('Настройки изменены другим сотрудником. Обновите страницу.',409);
  $changed=(!empty($old['merchant_id'])&&$old['merchant_id']!==$merchant)||(!empty($old['site_id'])&&$old['site_id']!==$site)||($old['merchant_login']!==$merchantLogin);
  if(one('SELECT id FROM pg_orders LIMIT 1')&&$changed)throw new ApiError('Есть заказы: общий мерчант, логин и точка выпуска зафиксированы. Разрешена замена ключа с теми же идентификаторами.',409);
  if(one('SELECT issuer_id FROM pg_vdar_supplier_points WHERE site_id=?',[$site]))throw new ApiError('Точка выпуска уже используется поставщиком',409);
  if(one("SELECT id FROM pg_commands WHERE status IN ('sending','unknown') LIMIT 1"))throw new ApiError('Сначала завершите сверку операций с неопределённым результатом',409);
  if($existing)query("UPDATE pg_merchant_credentials SET merchant_id=?,site_id=?,recipient_login=?,api_key_ciphertext=?,verified_at=?,updated_by=?,updated_at=? WHERE scope_id='pina'",[$merchant,$site,$login,gc_encrypt($key),now(),$actor['id'],now()]);
  else query("INSERT INTO pg_merchant_credentials(scope_id,merchant_id,site_id,recipient_login,api_key_ciphertext,verified_at,updated_by,updated_at) VALUES('pina',?,?,?,?,?,?,?)",[$merchant,$site,$login,gc_encrypt($key),now(),$actor['id'],now()]);
  gc_setting_save('vdar_merchant_login',$merchantLogin,$actor);
  if($old['merchant_login']!==$merchantLogin)foreach(rows('SELECT issuer_id,site_id,site_login FROM pg_vdar_supplier_points') as $p){one('SELECT id FROM pg_issuers WHERE id=? FOR UPDATE',[$p['issuer_id']]);$recipient=$merchantLogin.'.'.$p['site_login'];query('UPDATE pg_vdar_supplier_points SET recipient_login=?,updated_at=? WHERE issuer_id=?',[$recipient,now(),$p['issuer_id']]);query('UPDATE pg_issuers SET recipient_login=? WHERE id=?',[$recipient,$p['issuer_id']]);}
  audit((int)$actor['id'],'gift_platform_merchant_saved','pina',['merchantId'=>$merchant,'siteId'=>$site,'merchantLogin'=>$merchantLogin]);return ['saved'=>true]+gc_credentials_public('pina');
 });
}
function gc_save_supplier_point(string $scope,array $d,array $actor):array {
 if(!one('SELECT id FROM pg_issuers WHERE id=?',[$scope]))throw new ApiError('Компания не найдена',404);
 $pina=gc_credentials('pina');if(empty($pina['merchant_id'])||empty($pina['site_id'])||empty($pina['api_key']))throw new ApiError('Сначала настройте общий мерчант и секретный ключ Pina Market',409);
 if(!empty($d['apiKey'])||(!empty($d['merchantId'])&&$d['merchantId']!==$pina['merchant_id']))throw new ApiError('Для поставщика используются общие ID и ключ Pina Market. Укажите только ID и короткий логин его точки');
 $site=strtolower(txt($d,'siteId',36));$short=txt($d,'siteLogin',64,false)?:txt($d,'recipientLogin',64);
 if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$site)||!preg_match('/^[\p{L}\p{N}_-]{1,64}$/uD',$short))throw new ApiError('Укажите UUID точки и короткий логин одним словом, без точек');
 if($site===$pina['site_id'])throw new ApiError('Поставщику нужна отдельная точка, отличная от точки выпуска Pina Market');$recipient=$pina['merchant_login'].'.'.$short;
 $balance=gc_probe_point($pina['api_key'],$site);if($balance['site_login']!==$short&&$balance['site_login']!==$recipient)throw new ApiError('ID точки и короткий логин не совпадают с данными V-DAR',409);
 return transact(function()use($scope,$site,$short,$recipient,$pina,$actor){
  gc_lock_pina_credentials();one('SELECT id FROM pg_issuers WHERE id=? FOR UPDATE',[$scope]);$currentPina=gc_credentials('pina');if(($currentPina['merchant_id']??'')!==$pina['merchant_id']||!hash_equals((string)($currentPina['api_key']??''),$pina['api_key'])||$currentPina['merchant_login']!==$pina['merchant_login'])throw new ApiError('Настройки Pina Market изменились. Повторите проверку точки.',409);
  $old=gc_credentials($scope);if(one('SELECT id FROM pg_orders WHERE issuer_id=? LIMIT 1',[$scope])&&($old['mode']!=='shared'||$old['site_id']!==$site||$old['recipient_login']!==$recipient))throw new ApiError('Есть заказы: точку получателя нельзя менять без отдельной сверки.',409);
  $conflict=one('SELECT issuer_id FROM pg_vdar_supplier_points WHERE issuer_id<>? AND (site_id=? OR site_login=?)',[$scope,$site,$short]);if($conflict)throw new ApiError('Эта точка уже привязана к другой компании',409);
  if(one("SELECT q.id FROM pg_commands q JOIN pg_cards c ON c.id=q.card_id JOIN pg_orders o ON o.id=c.order_id WHERE o.issuer_id=? AND q.status IN ('sending','unknown') LIMIT 1",[$scope]))throw new ApiError('Сначала завершите сверку неопределённых операций',409);
  $existing=one('SELECT issuer_id FROM pg_vdar_supplier_points WHERE issuer_id=? FOR UPDATE',[$scope]);
  try{if($existing)query('UPDATE pg_vdar_supplier_points SET site_id=?,site_login=?,recipient_login=?,verified_at=?,updated_by=?,updated_at=? WHERE issuer_id=?',[$site,$short,$recipient,now(),$actor['id'],now(),$scope]);else query('INSERT INTO pg_vdar_supplier_points(issuer_id,site_id,site_login,recipient_login,verified_at,updated_by,updated_at) VALUES(?,?,?,?,?,?,?)',[$scope,$site,$short,$recipient,now(),$actor['id'],now()]);}catch(PDOException $e){if($e->getCode()==='23000')throw new ApiError('Эта точка уже привязана к другой компании. Обновите карточку.',409);throw $e;}
  query('UPDATE pg_issuers SET vdar_site_id=?,recipient_login=?,updated_at=? WHERE id=?',[$site,$recipient,now(),$scope]);audit((int)$actor['id'],'gift_supplier_point_saved',$scope,['siteId'=>$site,'siteLogin'=>$short,'recipient'=>$recipient]);return ['saved'=>true]+gc_credentials_public($scope);
 });
}
