<?php
declare(strict_types=1);
// Minimal text-only SMTP transport. TLS peer verification remains enabled.
function send_message(string $to,string $subject,string $body):void {
 $c=cfg()['smtp'];if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($c['from'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid mailbox');
 $mode=$c['security']??'ssl';if(!in_array($mode,['ssl','tls'],true))throw new RuntimeException('Encrypted SMTP required');
 $host=$c['host'];if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$host))throw new RuntimeException('Invalid SMTP host');
 $ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,'allow_self_signed'=>false]]);
 $socket=stream_socket_client(($mode==='ssl'?'ssl://':'tcp://').$host.':'.(int)$c['port'],$errno,$errstr,15,STREAM_CLIENT_CONNECT,$ctx);
 if(!$socket)throw new RuntimeException('SMTP unavailable');stream_set_timeout($socket,15);
 $read=function(array $codes)use($socket):void{$code=0;$lines=0;do{$line=fgets($socket,4096);if($line===false||++$lines>100)throw new RuntimeException('SMTP response failed');$code=(int)substr($line,0,3);}while(isset($line[3])&&$line[3]==='-');if(!in_array($code,$codes,true))throw new RuntimeException('SMTP command failed');};
 $write=function(string $s)use($socket):void{if(fwrite($socket,$s."\r\n")===false)throw new RuntimeException('SMTP write failed');};
 try{
  $read([220]);$write('EHLO pina.market');$read([250]);
  if($mode==='tls'){$write('STARTTLS');$read([220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS failed');$write('EHLO pina.market');$read([250]);}
  $write('AUTH LOGIN');$read([334]);$write(base64_encode($c['user']));$read([334]);$write(base64_encode($c['password']));$read([235]);
  $write('MAIL FROM:<'.$c['from'].'>');$read([250]);$write('RCPT TO:<'.$to.'>');$read([250,251]);$write('DATA');$read([354]);
  $headers=['Date: '.gmdate('r'),'Message-ID: <'.bin2hex(random_bytes(16)).'@pina.market>','From: =?UTF-8?B?'.base64_encode($c['from_name']??'Pina.market').'?= <'.$c['from'].'>','To: <'.$to.'>','Subject: =?UTF-8?B?'.base64_encode($subject).'?=','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64'];
  $payload=implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");$write($payload.'.');$read([250]);$write('QUIT');
 }finally{fclose($socket);}
}
