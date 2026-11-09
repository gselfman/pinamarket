<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
/** HTTPS requests for installation: use the system trust store through curl first. */
function deployment_http_get(string $url):array{
 $errors=[];
 if(function_exists('proc_open')){
  $args=['curl','-q','--silent','--show-error','--location','--max-redirs','5','--connect-timeout','8','--max-time','20','--proto','=https','--proto-redir','=https','--header','Cache-Control: no-cache','--write-out',"\nPINA_HTTP_STATUS:%{http_code}",$url];
  $process=@proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if(is_resource($process)){
   fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
   $status=0;$body=$output;
   if(preg_match('/\nPINA_HTTP_STATUS:(\d{3})\z/',$output,$m,PREG_OFFSET_CAPTURE)){$status=(int)$m[1][0];$body=substr($output,0,$m[0][1]);}
   if($exit===0&&$status>0)return ['status'=>$status,'body'=>$body,'error'=>'','transport'=>'curl'];
   $errors[]='curl (код '.$exit.'): '.trim($error?:'HTTP-ответ не получен');
  }else $errors[]='Не удалось запустить системный curl';
 }else $errors[]='proc_open отключён: системный curl из PHP недоступен';
 $ctx=stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true,'follow_location'=>0,'header'=>"Cache-Control: no-cache\r\n"],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
 $warnings=[];$current=$url;$body=false;$status=0;
 for($redirect=0;$redirect<=5;$redirect++){
  $http_response_header=[];set_error_handler(static function(int $severity,string $message)use(&$warnings):bool{$warnings[]=$message;return true;});
  try{$body=file_get_contents($current,false,$ctx);}catch(Throwable $e){$body=false;$warnings[]=$e->getMessage();}finally{restore_error_handler();}
  $status=0;$location='';foreach($http_response_header as $h){if(preg_match('~^HTTP/\S+ (\d+)~',$h,$m))$status=(int)$m[1];if(stripos($h,'Location:')===0)$location=trim(substr($h,9));}
  if($body===false||$status===0)break;
  if(in_array($status,[301,302,303,307,308],true)&&$location!==''){
   $parts=parse_url($current);$origin='https://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
   if(str_starts_with($location,'//'))$next='https:'.$location;
   elseif(preg_match('~^[a-z][a-z0-9+.-]*:~i',$location))$next=$location;
   elseif(str_starts_with($location,'/'))$next=$origin.$location;
   else $next=$origin.rtrim(dirname($parts['path']??'/'),'/').'/'.$location;
   if(strtolower((string)parse_url($next,PHP_URL_SCHEME))!=='https'){$warnings[]='Перенаправление вне HTTPS запрещено';$status=0;break;}
   if($redirect===5){$warnings[]='Слишком много HTTPS-перенаправлений';$status=0;break;}
   $current=$next;continue;
  }
  return ['status'=>$status,'body'=>$body,'error'=>'','transport'=>'PHP streams'];
 }
 $errors[]='PHP HTTPS: '.implode(' | ',array_unique($warnings?:['HTTP-ответ не получен']));
 return ['status'=>$status,'body'=>$body===false?'':$body,'error'=>implode("\n",$errors),'transport'=>'none'];
}

/** Check the deployed web server, not the source directory or PHP development router. */
function verify_site(string $base,?string $expectedRelease=null,array $assets=[]):array{
 $nonce=bin2hex(random_bytes(8));$checks=[];
 $get=function(string $path)use($base,$nonce):string{
  $response=deployment_http_get(rtrim($base,'/').$path.'?verify='.$nonce);
  if($response['error']!==''||$response['status']!==200)throw new RuntimeException('Не прошла проверка '.$path.' (HTTP '.$response['status'].').'.($response['error']!==''?"\n".$response['error']:' Проверьте каталог домена, .htaccess и PHP.'));
  return $response['body'];
 };
 $health=json_decode($get('/api/health/'),true);$id=$health['release']??'';
 if(($health['status']??'')!=='ok'||!is_string($id)||$id===''||($expectedRelease!==null&&$id!==$expectedRelease))throw new RuntimeException('Домен отвечает другой версией приложения либо не подключён к установленной базе.');
 $checks[]='/api/health/';
 foreach(['/','/login/','/register/','/cabinet/','/admin/','/reset/','/documents/','/documents/terms/','/documents/privacy/','/documents/refund/','/documents/cookies/','/documents/tariffs/','/documents/requisites/','/documents/consent/','/privacy','/publichnaya-oferta','/offer'] as $path){
  $body=$get($path);
  if(!str_contains($body,'name="pina-release" content="'.htmlspecialchars($id,ENT_QUOTES,'UTF-8').'"'))throw new RuntimeException('Страница '.$path.' отвечает другой версией или заглушкой хостинга.');
  $checks[]=$path;
 }
 foreach($assets as $name=>$hash){if(!hash_equals($hash,hash('sha256',$get('/'.$name))))throw new RuntimeException('Не совпадает файл /'.$name.'. Проверьте кеш и каталог домена.');$checks[]='/'.$name;}
 return $checks;
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
 try{$url=$argv[1]??'';if(!preg_match('~^https://[a-zA-Z0-9.-]+$~D',$url))throw new RuntimeException('Использование: php deploy/verify.php https://pina.market');foreach(verify_site($url) as $path)echo 'OK '.$path."\n";}
 catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
}
