<?php
declare(strict_types=1);

function gc_base32_encode(string $raw):string {
 $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$out='';$buffer=0;$bits=0;
 foreach(unpack('C*',$raw) as $byte){$buffer=($buffer<<8)|$byte;$bits+=8;while($bits>=5){$bits-=5;$out.=$alphabet[($buffer>>$bits)&31];}$buffer&=(1<<$bits)-1;}
 if($bits)$out.=$alphabet[($buffer<<(5-$bits))&31];return $out;
}
function gc_base32_decode(string $secret):string {
 if(!preg_match('/^[A-Z2-7]{16,128}$/D',$secret))throw new RuntimeException('INVALID_TOTP_SECRET');
 $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$out='';$buffer=0;$bits=0;
 foreach(str_split($secret) as $char){$buffer=($buffer<<5)|strpos($alphabet,$char);$bits+=5;if($bits>=8){$bits-=8;$out.=chr(($buffer>>$bits)&255);}$buffer&=(1<<$bits)-1;}return $out;
}
function gc_totp_code(string $secret,int $counter):string {
 $hash=hash_hmac('sha1',pack('N2',intdiv($counter,4294967296),$counter%4294967296),gc_base32_decode($secret),true);
 $offset=ord($hash[19])&15;$value=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;return str_pad((string)($value%1000000),6,'0',STR_PAD_LEFT);
}
function gc_totp_counter(string $secret,string $code,int $lastCounter=-1,?int $timestamp=null):?int {
 if(!preg_match('/^[0-9]{6}$/D',$code))return null;$step=intdiv($timestamp??time(),30);
 foreach([$step,$step-1,$step+1] as $candidate)if($candidate>=0&&$candidate>$lastCounter&&hash_equals(gc_totp_code($secret,$candidate),$code))return $candidate;
 return null;
}
// The login session owns expiry (12 hours of inactivity). No separate hourly step-up.
function gc_staff_otp_valid(array $actor):bool {
 if(!($actor['is_staff']??false)||(int)($_SESSION['gift_staff_otp_until']??0)<=0)return false;
 $s=one('SELECT factor_version,secret_ciphertext FROM pg_staff_2fa WHERE client_id=?',[$actor['id']]);
 return $s&&!empty($s['secret_ciphertext'])&&hash_equals((string)($_SESSION['gift_staff_otp_identity']??''),$actor['id'].':'.$actor['auth_version'].':'.$s['factor_version']);
}
function gc_staff_session_required(array $actor):void {
 if(PHP_SAPI==='cli')return;
 if(!gc_staff_otp_valid($actor))throw new ApiError('Подтвердите вход кодом из приложения-аутентификатора',428);
}
function gc_staff_access_grant(array $actor,int $version):array {
 session_regenerate_id(true);$_SESSION['gift_staff_otp_until']=time()+43200;$_SESSION['gift_staff_otp_identity']=$actor['id'].':'.$actor['auth_version'].':'.$version;
 return ['verified'=>true,'until'=>$_SESSION['gift_staff_otp_until']];
}
function gc_recovery_hash(int $id,string $code):string {return hash_hmac('sha256','pina-recovery-v1|'.$id.'|'.$code,gc_key());}
function gc_staff_access_route(string $route,string $method,array $d,array $actor):array {
 gc_staff_role($actor);
 if($route==='status/'&&$method==='GET'){$s=one('SELECT secret_ciphertext,pending_expires_at FROM pg_staff_2fa WHERE client_id=?',[$actor['id']]);return ['verified'=>gc_staff_otp_valid($actor),'enrolled'=>!empty($s['secret_ciphertext']),'email'=>$actor['email'],'needsPassword'=>(bool)$actor['password_prompt'],'until'=>(int)($_SESSION['gift_staff_otp_until']??0)];}
 if($route==='setup/'&&$method==='POST'){
  throttle('gift-totp-setup',(string)$actor['id'],5);
  $current=one('SELECT * FROM pg_staff_2fa WHERE client_id=?',[$actor['id']]);if(!empty($current['secret_ciphertext'])&&!gc_staff_otp_valid($actor))throw new ApiError('Для замены аутентификатора сначала войдите с текущим или резервным кодом',403);
  if(empty($current['secret_ciphertext'])){$password=txt($d,'password',200);
  if($actor['password_prompt']){password_rules($password);query('UPDATE pm_clients SET password_hash=?,password_prompt=0,auth_version=auth_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$actor['id']]);$_SESSION['auth_version']=(int)$actor['auth_version']+1;$actor=one('SELECT * FROM pm_clients WHERE id=?',[$actor['id']]);audit((int)$actor['id'],'gift_staff_password_initialized',(string)$actor['id']);}
  elseif(!password_verify($password,$actor['password_hash']))throw new ApiError('Неверный пароль сотрудника',403);
  }
  $secret=gc_base32_encode(random_bytes(20));$expires=gmdate('Y-m-d H:i:s',time()+600);
  query('INSERT INTO pg_staff_2fa(client_id,pending_ciphertext,pending_expires_at,pending_auth_version,updated_at) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE pending_ciphertext=VALUES(pending_ciphertext),pending_expires_at=VALUES(pending_expires_at),pending_auth_version=VALUES(pending_auth_version),pending_attempts=0,updated_at=VALUES(updated_at)',[$actor['id'],gc_encrypt($secret),$expires,$actor['auth_version'],now()]);
  audit((int)$actor['id'],'gift_staff_totp_setup_requested',(string)$actor['id']);
  return ['secret'=>$secret,'uri'=>'otpauth://totp/'.rawurlencode('Pina Market:'.$actor['email']).'?secret='.$secret.'&issuer=Pina%20Market&algorithm=SHA1&digits=6&period=30','expiresAt'=>$expires];
 }
 if($route==='confirm/'&&$method==='POST'){
  throttle('gift-totp-confirm',(string)$actor['id'],15);$code=txt($d,'code',6);
  $result=transact(function()use($actor,$code){$s=one('SELECT * FROM pg_staff_2fa WHERE client_id=? FOR UPDATE',[$actor['id']]);if(!$s||!$s['pending_ciphertext']||$s['pending_expires_at']<=now()||(int)$s['pending_auth_version']!==(int)$actor['auth_version']||(int)$s['pending_attempts']>=5)return null;
   $counter=gc_totp_counter(gc_decrypt($s['pending_ciphertext']),$code);if($counter===null){query('UPDATE pg_staff_2fa SET pending_attempts=pending_attempts+1 WHERE client_id=?',[$actor['id']]);audit((int)$actor['id'],'gift_staff_totp_setup_rejected',(string)$actor['id']);return null;}
   $version=(int)$s['factor_version']+1;$codes=[];for($n=0;$n<10;$n++){$raw=strtoupper(bin2hex(random_bytes(10)));$codes[]=implode('-',str_split($raw,4));query('INSERT INTO pg_staff_recovery_codes(id,client_id,factor_version,code_hash,created_at) VALUES(?,?,?,?,?)',[gc_uuid(),$actor['id'],$version,gc_recovery_hash((int)$actor['id'],$raw),now()]);}
   query('UPDATE pg_staff_2fa SET secret_ciphertext=pending_ciphertext,pending_ciphertext=NULL,pending_expires_at=NULL,pending_auth_version=NULL,pending_attempts=0,factor_version=?,last_counter=?,updated_at=? WHERE client_id=?',[$version,$counter,now(),$actor['id']]);audit((int)$actor['id'],'gift_staff_totp_enrolled',(string)$actor['id']);return ['version'=>$version,'codes'=>$codes];});
  if(!$result)throw new ApiError('Код неверен или настройка истекла. После пяти ошибок начните настройку заново.',403);
  return gc_staff_access_grant($actor,$result['version'])+['recoveryCodes'=>$result['codes']];
 }
 if($route==='verify/'&&$method==='POST'){
  throttle('gift-totp-verify',(string)$actor['id'],20);$code=txt($d,'code',40);$recovery=($d['recovery']??false)===true;
  $version=transact(function()use($actor,$code,$recovery){$s=one('SELECT * FROM pg_staff_2fa WHERE client_id=? FOR UPDATE',[$actor['id']]);if(!$s||!$s['secret_ciphertext'])return null;
   if($recovery){$normal=preg_replace('/[-\s]/','',strtoupper($code));if(!preg_match('/^[A-F0-9]{20}$/D',$normal))return null;$r=one('SELECT id FROM pg_staff_recovery_codes WHERE client_id=? AND factor_version=? AND code_hash=? AND used_at IS NULL FOR UPDATE',[$actor['id'],$s['factor_version'],gc_recovery_hash((int)$actor['id'],$normal)]);if(!$r)return null;query('UPDATE pg_staff_recovery_codes SET used_at=? WHERE id=?',[now(),$r['id']]);}
   else{$counter=gc_totp_counter(gc_decrypt($s['secret_ciphertext']),$code,(int)$s['last_counter']);if($counter===null)return null;query('UPDATE pg_staff_2fa SET last_counter=? WHERE client_id=?',[$counter,$actor['id']]);}
   audit((int)$actor['id'],$recovery?'gift_staff_recovery_used':'gift_staff_totp_verified',(string)$actor['id']);return (int)$s['factor_version'];});
  if($version===null){audit((int)$actor['id'],'gift_staff_totp_rejected',(string)$actor['id']);throw new ApiError('Код неверен или уже использован. Для TOTP дождитесь следующего кода.',403);}
  return gc_staff_access_grant($actor,$version);
 }
 throw new ApiError('Метод подтверждения доступа не найден',404);
}
