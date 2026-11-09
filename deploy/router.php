<?php
// Local PHP server only: php -S 127.0.0.1:8000 -t release/.../public deploy/router.php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$root=$_SERVER['DOCUMENT_ROOT'];
if($path!=='/'&&is_file($root.$path))return false;
require $root.'/index.php';
