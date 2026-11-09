<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$path=$argv[1]??'';if($path===''){fwrite(STDERR,"Usage: php deploy/backup.php /PRIVATE/PATH/backup.json\n");exit(1);}
$handle=fopen($path,'x');if(!$handle)throw new RuntimeException('Output must be a new file');chmod($path,0600);
try{db()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');db()->beginTransaction();$tables=['pm_meta','pm_consents','pm_counters','pm_clients','pm_services','pm_operations','pm_ledger','pm_messages','pm_audit','pm_password_resets'];fwrite($handle,'{"format":"pina-backup-v1","created":'.json_encode(gmdate('c')).',"tables":{');foreach($tables as $i=>$table){if($i)fwrite($handle,',');fwrite($handle,json_encode($table).':[');$n=0;$s=query('SELECT * FROM '.$table);while($row=$s->fetch()){if($n++)fwrite($handle,',');fwrite($handle,json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));}fwrite($handle,']');}fwrite($handle,'}}');db()->commit();fclose($handle);echo "Backup created. Store outside web root.\n";}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fclose($handle);unlink($path);throw $e;}
