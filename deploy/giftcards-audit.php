<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--archive')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/giftcards.php';
$locked=false;
try{$locked=(bool)one("SELECT GET_LOCK('pina-gift-audit-archive',0) locked")['locked'];if(!$locked)exit; $total=0;for($n=0;$n<100;$n++){$r=gc_archive_audit();$total+=$r['archived'];if($r['archived']<1000)break;}echo 'Archived actual audit records: '.$total."\n";}finally{if($locked)query("SELECT RELEASE_LOCK('pina-gift-audit-archive')");}
