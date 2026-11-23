<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$migrations=['001-giftcards'=>'giftcards.sql','002-merchant-credentials'=>'giftcards-credentials.sql','003-operations'=>'giftcards-operations.sql','004-access-audit'=>'giftcards-access-audit.sql','005-program-controls'=>'giftcards-program-controls.sql','006-purchase-records'=>'giftcards-purchase-records.sql','007-order-payments'=>'giftcards-order-payments.sql','008-shared-points'=>'giftcards-shared-points.sql','009-contract-reviews'=>'giftcards-contract-reviews.sql','010-program-confirmations'=>'giftcards-program-confirmations.sql'];$locked=false;
try{
 $locked=(bool)one('SELECT GET_LOCK(?,10) AS locked',['pina-giftcards-migrate'])['locked'];if(!$locked)throw new RuntimeException('Migration already running');
 if(!one("SHOW TABLES LIKE 'pm_clients'"))throw new RuntimeException('Install the existing Pina application first');
 foreach($migrations as $version=>$file){
  $sqlFile=dirname(__DIR__).'/database/'.$file;$hash=hash_file('sha256',$sqlFile);
  if(one("SHOW TABLES LIKE 'pg_schema_migrations'")){$old=one('SELECT checksum FROM pg_schema_migrations WHERE version=?',[$version]);if($old){if(!hash_equals($old['checksum'],$hash))throw new RuntimeException('Applied migration checksum differs. Add a new migration; do not rewrite the old one.');echo $version." is current.\n";continue;}}
  if(($argv[1]??'')!=='--apply'){echo 'Ready to apply '.$version.". No tables changed. Use --apply after backup.\n";continue;}
  // MySQL DDL commits implicitly; mark each restartable migration only after completion.
  foreach(explode(';',file_get_contents($sqlFile)) as $statement)if(trim($statement)!=='')db()->exec($statement);
  if($version==='003-operations'){
   // Restartable removal of the old lifetime uniqueness; immutable history remains.
   $indexes=rows("SHOW INDEX FROM pg_settlement_items");$hasUnique=false;$hasLookup=false;
   foreach($indexes as $idx){if($idx['Key_name']==='activation_id'&&(int)$idx['Non_unique']===0)$hasUnique=true;if($idx['Key_name']==='pg_item_activation')$hasLookup=true;}
   if(!$hasLookup)db()->exec('ALTER TABLE pg_settlement_items ADD INDEX pg_item_activation(activation_id)');
   if($hasUnique)db()->exec('ALTER TABLE pg_settlement_items DROP INDEX activation_id');
  }
  query('INSERT INTO pg_schema_migrations(version,checksum,applied_at) VALUES(?,?,?)',[$version,$hash,now()]);
  echo $version." applied. Existing wallet data was not converted.\n";
 }
}catch(Throwable $e){fwrite(STDERR,'Migration failed: '.$e->getMessage()."\n");exit(1);}finally{if($locked)query('SELECT RELEASE_LOCK(?)',['pina-giftcards-migrate']);}
