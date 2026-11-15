<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$sqlFile=dirname(__DIR__).'/database/giftcards.sql';$version='001-giftcards';$hash=hash_file('sha256',$sqlFile);
try{
 if(!one('SELECT GET_LOCK(?,10) AS locked',['pina-giftcards-migrate'])['locked'])throw new RuntimeException('Migration already running');
 if(!one("SHOW TABLES LIKE 'pm_clients'"))throw new RuntimeException('Install the existing Pina application first');
 $exists=one("SHOW TABLES LIKE 'pg_schema_migrations'");
 if($exists){$old=one('SELECT checksum FROM pg_schema_migrations WHERE version=?',[$version]);if($old){if(!hash_equals($old['checksum'],$hash))throw new RuntimeException('Applied migration checksum differs. Add a new migration; do not rewrite the old one.');echo "Gift cards schema is current.\n";exit;}}
 if(($argv[1]??'')!=='--apply'){echo "Ready to apply additive gift cards migration. No tables changed. Use --apply after backup.\n";exit;}
 // MySQL DDL commits implicitly. Every statement is restartable; completion is marked last.
 foreach(explode(';',file_get_contents($sqlFile)) as $statement)if(trim($statement)!=='')db()->exec($statement);
 query('INSERT INTO pg_schema_migrations(version,checksum,applied_at) VALUES(?,?,?)',[$version,$hash,now()]);
 echo "Gift cards migration applied. Existing wallet data was not converted.\n";
}catch(Throwable $e){fwrite(STDERR,'Migration failed: '.$e->getMessage()."\n");exit(1);}finally{query('SELECT RELEASE_LOCK(?)',['pina-giftcards-migrate']);}
