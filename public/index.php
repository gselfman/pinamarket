<?php
declare(strict_types=1);
$appDir=is_file(__DIR__.'/pina-path.php')?require __DIR__.'/pina-path.php':dirname(__DIR__).'/app';
if(is_file($appDir.'/config.php')){
 $config=require $appDir.'/config.php';
 if(!($config['debug']??false)&&!in_array($_SERVER['HTTPS']??'',['on','1'],true)){http_response_code(400);exit('Используйте HTTPS-адрес сайта.');}
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: strict-origin-when-cross-origin');header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/';
if(str_starts_with($path,'/api/')){require $appDir.'/api.php';exit;}
// Keep previously published document links usable after replacing the old storefront.
$legacy=['/privacy'=>'/documents/privacy/','/publichnaya-oferta'=>'/documents/terms/','/offer'=>'/documents/terms/'];
if(isset($legacy[rtrim($path,'/')])){header('Location: '.$legacy[rtrim($path,'/')],true,301);exit;}

// Canonical URLs work both with and without a trailing slash; preserve reset tokens.
if(preg_match('~^/(?:admin|login|register|cabinet|reset|documents(?:/[a-z]+)?)$~D',$path)){
 $query=parse_url($_SERVER['REQUEST_URI'],PHP_URL_QUERY);
 header('Location: '.$path.'/'.($query!==null?'?'.$query:''),true,308);exit;
}
$release=is_file($appDir.'/release.json')?json_decode(file_get_contents($appDir.'/release.json'),true):[];
if(preg_match('~^/documents/([a-z]+)/$~D',$path,$m)){require $appDir.'/document.php';exit;}
if(!in_array($path,['/','/admin/','/login/','/register/','/cabinet/','/reset/','/documents/'],true)){http_response_code(404);exit('Страница не найдена');}
// Useful, readable public content is returned before JavaScript or API requests run.
require $appDir.'/shell.php';
if($path==='/documents/'){echo preg_replace('~<script src="/(?:legal|app)\.js[^"]*"></script>~','',$html);exit;}
echo $html;
