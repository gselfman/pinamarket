<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/mail.php';
require dirname(__DIR__).'/app/notifications.php';
try{echo 'SMTP accepted: '.deliver_operation_mail(20)."\n";}catch(Throwable){fwrite(STDERR,"Pina.market: mail queue unavailable\n");exit(1);}
