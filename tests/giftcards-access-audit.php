<?php
// Disposable fixtures only: authentication tests never create customer activity in production.
if(!defined('PINA_LIBRARY_ONLY')||!isset($checks)||!str_contains(cfg()['db']['name'],'test'))throw new RuntimeException('Test entry point required');
$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
foreach([59=>'287082',1111111109=>'081804',1111111111=>'050471',1234567890=>'005924',2000000000=>'279037',20000000000=>'353130'] as $at=>$expected)gassert(gc_totp_code($secret,intdiv($at,30))===$expected,'RFC6238 six-digit SHA1 vector '.$at);
gassert(gc_base32_decode(gc_base32_encode('12345678901234567890'))==='12345678901234567890','base32 round trip');
query('INSERT INTO pm_clients(id,email,password_hash,is_staff,password_prompt,created_at) VALUES(?,?,?,?,0,?)',[90000,'security-fixture@example.invalid',password_hash('SecurityFixture2026!',PASSWORD_DEFAULT),1,now()]);
query('INSERT INTO pg_profiles(client_id,email_verified_at,created_at) VALUES(?,?,?)',[90000,now(),now()]);
as_client(90000);$staff=current_client();$setup=gc_staff_access_route('setup/','POST',['password'=>'SecurityFixture2026!'],$staff);
gassert(strlen($setup['secret'])===32&&str_starts_with($setup['uri'],'otpauth://totp/'),'TOTP enrollment secret and URI');
$stored=one('SELECT * FROM pg_staff_2fa WHERE client_id=90000');gassert(!str_contains($stored['pending_ciphertext'],$setup['secret']),'pending TOTP secret encrypted at rest');
$code=gc_totp_code($setup['secret'],intdiv(time(),30));$result=gc_staff_access_route('confirm/','POST',['code'=>$code],$staff);gassert(gc_staff_otp_valid($staff)&&count($result['recoveryCodes'])===10,'confirmed authenticator grants staff access and one-time backup codes');
greject(fn()=>gc_staff_access_route('verify/','POST',['code'=>$code],$staff),'same TOTP step cannot be replayed');
$recovery=$result['recoveryCodes'][0];$backup=gc_staff_access_route('verify/','POST',['code'=>$recovery,'recovery'=>true],$staff);gassert($backup['verified'],'recovery code grants access');greject(fn()=>gc_staff_access_route('verify/','POST',['code'=>$recovery,'recovery'=>true],$staff),'recovery code is single-use');
$state=gc_staff_access_route('status/','GET',[],$staff);gassert(!isset($state['secret'],$state['recoveryCodes']),'status never returns factor secrets');
$until=$_SESSION['gift_staff_otp_until'];$_SESSION['gift_staff_otp_until']=time()-3600;gassert(gc_staff_otp_valid($staff),'active staff login is not interrupted by a separate hourly OTP expiry');$_SESSION['gift_staff_otp_until']=$until;$_SESSION['gift_staff_otp_until']=0;greject(fn()=>gc_staff_access_route('setup/','POST',['password'=>'SecurityFixture2026!'],$staff),'password alone cannot replace an enrolled authenticator');$_SESSION['gift_staff_otp_until']=$until;
$next=gc_staff_access_route('setup/','POST',[],$staff);gassert(!empty($next['secret']),'an already authenticated staff member can rotate the authenticator without another password confirmation');query("UPDATE pg_staff_2fa SET pending_expires_at='2000-01-01' WHERE client_id=90000");greject(fn()=>gc_staff_access_route('confirm/','POST',['code'=>gc_totp_code($next['secret'],intdiv(time(),30))],$staff),'expired enrollment rejected');
$all=rows('SELECT action,details_json FROM pm_audit WHERE actor_id=90000');foreach($all as $a){gassert(!str_contains($a['details_json'],$setup['secret'])&&!str_contains($a['details_json'],$recovery),'audit never contains TOTP secret or recovery code');}
as_client(1000);audit(1000,'gift_test_archive_event','test-object',['purpose'=>'disposable retention fixture']);$old=(int)db()->lastInsertId(); // Context has no autoincrement; read the authoritative event explicitly.
$old=(int)one("SELECT id FROM pm_audit WHERE action='gift_test_archive_event' ORDER BY id DESC LIMIT 1")['id'];
query("UPDATE pm_audit SET created_at='2000-01-01 00:00:00' WHERE id=?",[$old]);
query("INSERT INTO pm_audit(actor_id,action,object_ref,details_json,created_at) VALUES(1014,'gift_historical_fixture','legacy-fixture','{}','2000-01-01 00:00:00')");$historical=(int)db()->lastInsertId();
$before=one('SELECT * FROM pm_audit WHERE id=?',[$old]);$archived=gc_archive_audit(1000,current_client());gassert($archived['archived']===2,'only older-than-30-day fixture events archived');
$after=one('SELECT * FROM pg_audit_archive WHERE id=?',[$old]);foreach(['id','actor_id','action','object_ref','details_json','created_at'] as $key)gassert($before[$key]===$after[$key],'archive preserves '.$key);
gassert(!one('SELECT id FROM pm_audit WHERE id=?',[$old])&&one('SELECT audit_id FROM pg_audit_archive_context WHERE audit_id=?',[$old]),'archiving moves context and preserves history');
$archive=gc_audit_list(['archive'=>'archive','actorKind'=>'staff']);gassert($archive['total']===1&&$archive['items'][0]['source']==='cli','operator remains an operator and CLI remains CLI in archive');
$legacy=gc_audit_list(['archive'=>'archive','actorKind'=>'unknown']);gassert($legacy['total']===1&&(int)$legacy['items'][0]['id']===$historical,'missing historical context is not fabricated');
$again=gc_archive_audit(1000,current_client());gassert($again['archived']===0,'archive re-run is idempotent');
$filtered=gc_audit_list(['clientId'=>1014,'limit'=>1,'archive'=>'all']);gassert($filtered['total']>1&&count($filtered['items'])===1&&$filtered['pages']>1,'audit filtering and pagination cover retained history');
