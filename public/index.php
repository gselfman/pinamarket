<?php
declare(strict_types=1);
$appDir=is_file(__DIR__.'/pina-path.php')?require __DIR__.'/pina-path.php':dirname(__DIR__).'/app';
if(is_file($appDir.'/config.php')){
 $config=require $appDir.'/config.php';
 if(!($config['debug']??false)&&($_SERVER['HTTPS']??'')!=='on'){http_response_code(400);exit('Используйте HTTPS-адрес сайта.');}
}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/api/')){require $appDir.'/api.php';exit;}
if(preg_match('~^/documents/([a-z]+)/?$~',$path,$m)){require $appDir.'/document.php';exit;}
if(!in_array($path,['/','/admin','/admin/','/login/','/register/','/cabinet/','/reset/'],true)){http_response_code(404);exit('Страница не найдена');}
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: strict-origin-when-cross-origin');header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
// Server-backed application. The public directory contains no account data.
$html=file_get_contents($appDir.'/template.html');echo $html;
