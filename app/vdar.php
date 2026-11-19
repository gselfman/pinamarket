<?php
declare(strict_types=1);
// Server-only adapter, verified against https://api.v-dar.ru/openapi.json on 2026-11-15.
// payment_id is a correlation ID. V-DAR does not document an idempotency guarantee.
final class VdarUncertain extends RuntimeException {}
final class VdarRejected extends RuntimeException {
 public function __construct(public readonly string $safeCode='PROVIDER_REJECTED'){parent::__construct($safeCode);}
}
final class VdarClient {
 public function __construct(private string $key,private string $site,private string $base='https://api.v-dar.ru/api/v1',private mixed $transport=null) {
  if(!preg_match('/^[0-9a-f-]{36}$/iD',$site)||$key==='')throw new RuntimeException('VDAR_NOT_CONFIGURED');
  if($transport===null&&$base!=='https://api.v-dar.ru/api/v1')throw new RuntimeException('VDAR_BASE_NOT_ALLOWED');
 }
 private function call(string $method,string $path,?array $body=null):array {
  if($this->transport!==null)return ($this->transport)($method,$path,$body);
  if(!extension_loaded('curl'))throw new RuntimeException('CURL_REQUIRED');
  $ch=curl_init($this->base.$path);$response='';
  curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['X-API-Key: '.$this->key,'Content-Type: application/json','Accept: application/json'],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$response){if(strlen($response)+strlen($chunk)>2097152)return 0;$response.=$chunk;return strlen($chunk);}]);
  if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
  $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  // Never retry a mutating request, including a timeout or malformed success response.
  if($ok===false||$status>=500||$status===0)throw new VdarUncertain('PROVIDER_RESULT_UNKNOWN');
  try{$data=json_decode($response,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){throw new VdarUncertain('PROVIDER_INVALID_RESPONSE');}
  if(!is_array($data))throw new VdarUncertain('PROVIDER_INVALID_RESPONSE');
  if($status<200||$status>=300||($data['success']??true)===false){$code=$data['error_code']??'PROVIDER_REJECTED';if(!is_string($code)||!preg_match('/^[A-Z_]{1,60}$/D',$code))$code='PROVIDER_REJECTED';throw new VdarRejected($code);}
  return $data;
 }
 public function generate(int $minor,string $name,string $recipient,string $paymentId,int $expires):array {
  if($minor<1001||$minor>10000000000||$recipient===''||$expires<1||$expires>31536000)throw new InvalidArgumentException('INVALID_CODE_PARAMETERS');
  $r=$this->call('POST','/codes/generate',['amount'=>self::decimal($minor),'currency_code'=>'RUB','name'=>$name,'site_id'=>$this->site,'recipient_login'=>$recipient,'payment_id'=>$paymentId,'expires_in_seconds'=>$expires]);
  if(!preg_match('/^[A-Z0-9]{20}$/D',(string)($r['code']??''))||($r['currency_code']??'')!=='RUB'||self::minor($r['amount']??'0')!==$minor||($r['recipient_login']??'')!==$recipient||($r['payment_id']??'')!==$paymentId||!is_string($r['id']??null))throw new VdarUncertain('PROVIDER_CODE_MISMATCH');
  return $r;
 }
 public function activate(string $code):array {self::code($code);$r=$this->call('POST','/codes/activate',['code'=>$code,'site_id'=>$this->site]);if(($r['success']??false)!==true||!is_string($r['operation_id']??null)||($r['currency_code']??'')!=='RUB')throw new VdarUncertain('PROVIDER_ACTIVATION_UNKNOWN');return $r;}
 public function check(string $code):array {self::code($code);return $this->call('GET','/codes/'.$code);}
 public function cancel(string $code):array {self::code($code);return $this->call('DELETE','/codes/'.$code);}
 public function balance():array {return $this->call('GET','/merchants/balance/'.$this->site);}
 public function listCodes(string $name,int $page=1):array {if($page<1||$page>1000)throw new InvalidArgumentException('INVALID_PAGE');return $this->call('GET','/codes/?'.http_build_query(['name'=>$name,'currency_code'=>'RUB','page'=>$page,'size'=>100]));}
 public static function decimal(int $n):string {return intdiv($n,100).'.'.str_pad((string)($n%100),2,'0',STR_PAD_LEFT);}
 public static function minor(mixed $value):int {if(!is_string($value)&&!is_int($value)&&!is_float($value))throw new VdarUncertain('INVALID_PROVIDER_AMOUNT');$s=(string)$value;if(!preg_match('/^([0-9]{1,11})(?:\.([0-9]{1,2})0*)?$/D',$s,$m))throw new VdarUncertain('INVALID_PROVIDER_AMOUNT');return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');}
 private static function code(string $code):void {if(!preg_match('/^[A-Z0-9]{20}$/D',$code))throw new InvalidArgumentException('INVALID_CODE');}
}
