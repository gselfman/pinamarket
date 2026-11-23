<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$file=getenv('PINA_CONFIG')?:$root.'/app/config.php';
if(!is_file($file)){fwrite(STDERR,"Сначала настройте существующее приложение Pina Market.\n");exit(1);}
function gc_prompt(string $label,bool $secret=false):string {fwrite(STDOUT,$label.': ');if($secret&&function_exists('shell_exec'))shell_exec('stty -echo');try{$v=trim((string)fgets(STDIN));}finally{if($secret&&function_exists('shell_exec')){shell_exec('stty echo');fwrite(STDOUT,"\n");}}if($v==='')throw new RuntimeException('Значение обязательно');return $v;}
try{
 $c=require $file;$g=$c['giftcards']??[];$g+=['enabled'=>false,'sales_enabled'=>false,'site_mode'=>false,'encryption_key'=>base64_encode(random_bytes(32)),'storage_path'=>$root.'/var/giftcards-documents','balance_return_mode'=>'manual','agent_signature_approved'=>false,'legal_approved'=>false];
 $action=$argv[1]??'--init';
 if($action==='--pina'){$g['pina']=['merchant_id'=>gc_prompt('UUID мерчанта Pina Market'),'api_key'=>gc_prompt('API-ключ Pina Market (скрыт)',true),'site_id'=>gc_prompt('UUID торговой точки Pina Market'),'recipient_login'=>gc_prompt('Логин получателя Pina Market точки Pina Market')];}
 elseif($action==='--issuer'){$id=$argv[2]??'';if(!preg_match('/^[a-f0-9-]{36}$/iD',$id))throw new RuntimeException('Укажите UUID компании после --issuer');$g['issuers'][$id]=['merchant_id'=>gc_prompt('UUID мерчанта поставщика'),'api_key'=>gc_prompt('API-ключ поставщика (скрыт)',true),'site_id'=>gc_prompt('UUID торговой точки поставщика'),'recipient_login'=>gc_prompt('Логин поставщика или магазин.точка')];}
 elseif($action==='--signature'){$source=gc_prompt('Абсолютный путь к утверждённому PNG подписи');if(!is_file($source)||is_link($source)||filesize($source)>1048576)throw new RuntimeException('Недопустимый файл подписи');$size=getimagesize($source);if(!$size||$size[2]!==IMAGETYPE_PNG)throw new RuntimeException('Нужен PNG');if(!is_dir($g['storage_path']))mkdir($g['storage_path'],0700,true);$target=$g['storage_path'].'/pina-approved-signature.png';if(!copy($source,$target))throw new RuntimeException('Не удалось сохранить подпись');chmod($target,0600);$g['agent_signature_path']=$target;$g['agent_signature_sha256']=hash_file('sha256',$target);$g['agent_signature_approved']=true;}
 elseif($action==='--legal'){$g['payment_instructions']=gc_prompt('Согласованная с банком инструкция оплаты без открытия счёта');$g['legal_effective_date']=gc_prompt('Дата утверждённой редакции оферты YYYY-MM-DD');if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$g['legal_effective_date']))throw new RuntimeException('Проверьте дату');$source=json_decode(file_get_contents($root.'/app/giftcards-offer.json'),true,512,JSON_THROW_ON_ERROR);if(isset($source['effective_date'])&&$g['legal_effective_date']!==$source['effective_date'])throw new RuntimeException('Дата должна соответствовать опубликованной редакции оферты');$g['legal_source_sha256']=hash_file('sha256',$root.'/app/giftcards-offer.json');$g['legal_approved']=true;}
 elseif($action==='--enable-preview'){$g['enabled']=true;$g['sales_enabled']=false;}
 elseif($action==='--disable-sales'){$g['sales_enabled']=false;}
 elseif($action==='--enable-sales'){
  require_once $root.'/app/bootstrap.php';require_once $root.'/app/giftcards.php';$pina=gc_credentials('pina');
  $checklist=gc_admin_checklist();foreach(['pina','signature','legal','supplier'] as $item)if(!$checklist[$item])throw new RuntimeException('Не завершён пункт подключения: '.$item.'. Поддерживается подпись на сайте или принятый договор с обеими подписями вне сайта.');
  if((gc_settings()['balance_return_mode']??'unconfirmed')==='unconfirmed')throw new RuntimeException('Не согласован возврат учётного остатка. Продажи не включены.');$g['enabled']=true;$g['sales_enabled']=true;
 }
 elseif($action==='--manual-balance-return'){$g['balance_return_mode']='manual';}
 elseif($action==='--switch-home'){$g['site_mode']=true;}
 elseif($action==='--legacy-home'){$g['site_mode']=false;}
 elseif($action!=='--init')throw new RuntimeException('Неизвестный режим');
 $c['giftcards']=$g;$backup=$root.'/var/config-backups';if(!is_dir($backup))mkdir($backup,0700,true);$backupFile=$backup.'/config-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.php';if(!copy($file,$backupFile))throw new RuntimeException('Не создана резервная копия настроек');chmod($backupFile,0600);
 $tmp=$file.'.giftcards-new';if(file_put_contents($tmp,"<?php\nreturn ".var_export($c,true).";\n",LOCK_EX)===false)throw new RuntimeException('Не записаны настройки');chmod($tmp,0600);if(!rename($tmp,$file))throw new RuntimeException('Не заменены настройки');
 echo "Настройки сохранены вне публичного каталога. Ключи и подпись не выводятся.\n";
 echo "Веб-приложение использует копию настроек активного релиза. Опубликуйте обновление через giftcards-release.sh, чтобы применить изменения в вебе.\n";
}catch(Throwable $e){fwrite(STDERR,'Настройка не завершена: '.$e->getMessage()."\n");exit(1);}
