<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/giftcards-worker.php';
$id=$argv[1]??'';if(!preg_match('/^[0-9a-f-]{36}$/iD',$id)){fwrite(STDERR,"Usage: php deploy/giftcards-reconcile.php CARD_UUID\n");exit(1);}
try{echo json_encode(gc_reconcile_issue($id),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";}catch(Throwable){fwrite(STDERR,"Reconciliation failed. No repeated issuance was attempted.\n");exit(1);}
