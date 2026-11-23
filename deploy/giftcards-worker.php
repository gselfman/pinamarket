<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$config=$root.'/app/config.php';
if(!getenv('PINA_CONFIG'))putenv('PINA_CONFIG='.$config);
require $root.'/app/bootstrap.php';require $root.'/app/giftcards-worker.php';
try{pina_gc_enabled();$count=0;while($count<20&&gc_run_one())$count++;echo 'Gift card commands processed: '.$count."\n";}catch(Throwable){fwrite(STDERR,"Gift card worker failed. Check private configuration and command registry.\n");exit(1);}
