<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
/** Check the deployed web server, not the source directory or PHP development router. */
function verify_site(string $base,?string $expectedRelease=null,array $assets=[]):array{
 $ctx=stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true,'header'=>"Cache-Control: no-cache\r\n"],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
 $nonce=bin2hex(random_bytes(8));$checks=[];
 $get=function(string $path)use($base,$ctx,$nonce):string{
  $body=@file_get_contents(rtrim($base,'/').$path.'?verify='.$nonce,false,$ctx);$status=0;
  foreach($http_response_header??[] as $h)if(preg_match('~^HTTP/\S+ (\d+)~',$h,$m))$status=(int)$m[1];
  if($body===false||$status!==200)throw new RuntimeException('Не прошла проверка '.$path.' (HTTP '.$status.'). Проверьте каталог домена, .htaccess, PHP и SSL.');
  return $body;
 };
 $health=json_decode($get('/api/health/'),true);$id=$health['release']??'';
 if(($health['status']??'')!=='ok'||!is_string($id)||$id===''||($expectedRelease!==null&&$id!==$expectedRelease))throw new RuntimeException('Домен отвечает другой версией приложения либо не подключён к установленной базе.');
 $checks[]='/api/health/';
 foreach(['/','/login/','/register/','/cabinet/','/admin/','/reset/','/documents/','/documents/terms/','/documents/privacy/','/documents/refund/','/documents/cookies/','/documents/tariffs/','/documents/requisites/','/documents/consent/'] as $path){
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
