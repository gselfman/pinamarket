<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/giftcards.php';
require_once dirname(__DIR__).'/app/giftcards-operations.php';require_once dirname(__DIR__).'/app/giftcards-bank-import.php';
try{
 $mode=$argv[1]??'';$path=$argv[2]??'';$email=$argv[3]??'';
 if(!in_array($mode,['--preview','--apply'],true)||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Usage: php deploy/giftcards-bank-import.php --preview|--apply PRIVATE_CSV STAFF_EMAIL [REVIEWED_SHA256]');
 $actor=one('SELECT * FROM pm_clients WHERE email=? AND is_staff=1',[$email]);if(!$actor)throw new RuntimeException('Staff account not found');
 gc_admin_guard('bank-import/','POST',$actor);$entries=gc_bank_csv($path);$hash=hash_file('sha256',$path);
 if($mode==='--apply'){
  if(!preg_match('/^[a-f0-9]{64}$/D',$argv[4]??'')||!hash_equals($argv[4],$hash))throw new RuntimeException('Statement changed or no reviewed SHA256 supplied. Preview and verify it with the bank first.');
  $result=gc_bank_import($entries,$actor);audit((int)$actor['id'],'gift_bank_statement_cli_applied',$hash,$result);echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
 }else{
  $total=0;foreach($entries as $e)$total+=minor($e['amount'],100000000000);
  echo json_encode(['sha256'=>$hash,'rows'=>count($entries),'amountRUB'=>VdarClient::decimal($total),'entries'=>$entries,'notice'=>'Preview only. Verify genuine bank receipts and order references before --apply. No cards or redemptions are created.'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
 }
}catch(Throwable $e){fwrite(STDERR,'Import stopped: '.$e->getMessage()."\n");exit(1);}
