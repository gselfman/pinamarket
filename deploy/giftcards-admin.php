<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/giftcards.php';
$allowed=['bank-confirm/','issuer-approve/','document-approve/','settlement-prepare/','settlement-bank-credit/','refund-cancel-code/','refund-paid/','balance-reconcile/','retry-safe-command/'];
$action=$argv[1]??'';$file=$argv[2]??'';$email=$argv[3]??'';
if(!in_array($action,$allowed,true)||!is_file($file)||!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Usage: php deploy/giftcards-admin.php ACTION/ PRIVATE_JSON_FILE ADMIN_EMAIL\n");exit(1);}
try{$actor=one('SELECT * FROM pm_clients WHERE email=? AND is_staff=1',[$email]);if(!$actor)throw new RuntimeException('Staff account not found');$data=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);if(!is_array($data))throw new RuntimeException('Expected JSON object');echo json_encode(gc_admin_route($action,'POST',$data,$actor),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";}catch(Throwable $e){fwrite(STDERR,'Operation failed: '.$e->getMessage()."\n");exit(1);}
