<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Installer always uses its own configuration, never a CLI test override.
putenv('PINA_CONFIG');
$root=dirname(__DIR__);
function out(string $s):void{fwrite(STDOUT,$s."\n");}
function ask(string $label,string $default='',bool $secret=false):string{
 static $isTTY=null;if($isTTY===null)$isTTY=function_exists('stream_isatty')&&stream_isatty(STDIN);
 fwrite(STDOUT,$label.($default!==''?' ['.$default.']':'').': ');
 $settings=null;
 if($secret&&$isTTY&&function_exists('shell_exec')){$settings=trim((string)shell_exec('stty -g'));if($settings!=='')shell_exec('stty -echo');}
 try{$v=fgets(STDIN);if($v===false)throw new RuntimeException('Ввод прерван. Запустите скрипт в интерактивном терминале.');}
 finally{if($settings!==null&&$settings!==''){shell_exec('stty '.escapeshellarg($settings));out('');}}
 $v=$secret?rtrim($v,"\r\n"):trim($v);return $v!==''?$v:$default;
}
function setting(string $key,string $label,string $default=''):string{
 $preset=getenv($key);if($preset!==false)return trim($preset);
 if(getenv('PINA_PASSWORDS_ONLY')==='1')throw new RuntimeException('Не задан параметр '.$key.' для установки только по паролям.');
 return ask($label,$default);
}
function put_private(string $path,string $content):void{$tmp=$path.'.new';if(file_put_contents($tmp,$content,LOCK_EX)===false)throw new RuntimeException('Не удалось записать '.$path);chmod($tmp,0600);if(!rename($tmp,$path))throw new RuntimeException('Не удалось сохранить '.$path);}
function copy_tree(string $src,string $dst):void{if(!is_dir($dst))mkdir($dst,0755,true);chmod($dst,0755);foreach(array_diff(scandir($src),['.','..']) as $name){$from=$src.'/'.$name;$to=$dst.'/'.$name;if(is_dir($from)&&!is_link($from))copy_tree($from,$to);else{if(!copy($from,$to))throw new RuntimeException('Не удалось сохранить каталог проверки сертификата.');chmod($to,0644);}}}
function copy_file(string $src,string $dst,int $mode=0644):void{$tmp=$dst.'.pina-new';if(!copy($src,$tmp))throw new RuntimeException('Не удалось скопировать '.$dst);chmod($tmp,$mode);if(!rename($tmp,$dst))throw new RuntimeException('Не удалось заменить '.$dst);}
try{
 if(PHP_VERSION_ID<80200)throw new RuntimeException('Нужен PHP 8.2 или новее.');
 foreach(['pdo_mysql','mbstring','openssl','session','json'] as $ext)if(!extension_loaded($ext))throw new RuntimeException('В выбранном PHP отсутствует расширение '.$ext.'. Включите его в панели.');
 if(in_array('--check',$argv,true)){out('PHP '.PHP_VERSION.' и обязательные расширения доступны.');exit;}
 if(!is_writable($root.'/app'))throw new RuntimeException('Нет прав записи в приватную папку приложения.');
 $lock=fopen($root.'/app/deploy.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Другая установка уже выполняется.');
 $update=in_array('--update',$argv,true);$configFile=$root.'/app/config.php';
 if($update){if(!is_file($configFile))throw new RuntimeException('Сначала выполните первоначальную установку.');$config=require $configFile;}
 else{
  if(is_file($configFile))throw new RuntimeException('Настройки уже существуют. Для обновления используйте --update.');
  out('Установка Pina.market. Секреты не передаются в аргументах команд и не попадают в Git.');
  $url=rtrim(setting('PINA_BASE_URL','HTTPS-адрес сайта','https://pina.market'),'/');if(!preg_match('~^https://[a-zA-Z0-9.-]+$~D',$url))throw new RuntimeException('Нужен HTTPS-домен без пути.');
  $host=parse_url($url,PHP_URL_HOST);$home=rtrim((string)getenv('HOME'),'/');$candidates=[];
  foreach([$home.'/www',$home.'/data/www',$home.'/public_html',$home.'/htdocs'] as $parent){
   if(!is_dir($parent))continue;
   foreach(scandir($parent) as $name)if(strcasecmp($name,$host)===0&&is_dir($parent.'/'.$name))$candidates[]=realpath($parent.'/'.$name);
  }
  $candidates=array_values(array_unique($candidates));
  if($candidates){out('Найдены каталоги домена (сверьте с панелью):');foreach($candidates as $i=>$candidate)out('  '.($i+1).'. '.$candidate);}
  $guess=$candidates[0]??(is_dir($home.'/public_html')?$home.'/public_html':'');
  $presetPublic=getenv('PINA_PUBLIC_DIR');
  if($presetPublic!==false&&$presetPublic!=='')out('Каталог сайта задан: '.$presetPublic);
  while(true){
   $public=trim(($presetPublic!==false&&$presetPublic!=='')?$presetPublic:ask('Полный путь из панели или номер найденного каталога; Enter — предложенный путь',$guess));
   if(ctype_digit($public)&&isset($candidates[(int)$public-1]))$public=$candidates[(int)$public-1];
   if(str_starts_with($public,'~/'))$public=$home.substr($public,1);
   $public=rtrim($public,'/');
   if($public!==''&&str_starts_with($public,'/')&&is_dir($public)&&is_writable($public)&&is_writable(dirname($public)))break;
   if($presetPublic!==false&&$presetPublic!=='')throw new RuntimeException('PINA_PUBLIC_DIR: каталог не существует либо нет прав записи в него или его родителя.');
   out('Путь не найден либо нет прав записи в каталог/его родителя: '.$public);
   out('Нужен полный путь от корня сервера, например '.$home.'/www/'.$host.'. /www и '.$home.'/www — разные каталоги. Попробуйте снова.');
  }
  $public=realpath($public);if(str_starts_with($root.'/', $public.'/')||str_starts_with($public.'/', $root.'/app/'))throw new RuntimeException('Исходный код и приватная папка должны находиться за пределами каталога сайта.');
  $db=['host'=>setting('PINA_DB_HOST','Хост MySQL из панели','localhost'),'port'=>(int)setting('PINA_DB_PORT','Порт MySQL','3306'),'name'=>setting('PINA_DB_NAME','Полное имя базы с префиксом хостинга'),'user'=>setting('PINA_DB_USER','Пользователь MySQL'),'password'=>ask('Пароль MySQL','',true)];
  if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$db['host'])||!preg_match('/^[a-zA-Z0-9_-]+$/D',$db['name'])||$db['user']===''||$db['port']<1||$db['port']>65535)throw new RuntimeException('Проверьте хост и имя базы.');
  $smtp=['host'=>setting('PINA_SMTP_HOST','Сервер SMTP'),'port'=>(int)setting('PINA_SMTP_PORT','Порт SMTP','465'),'security'=>strtolower(setting('PINA_SMTP_SECURITY','Шифрование SMTP: ssl для 465, tls для 587','ssl')),'user'=>setting('PINA_SMTP_USER','Логин SMTP'),'password'=>ask('Пароль SMTP','',true),'from'=>setting('PINA_SMTP_FROM','Адрес отправителя','mail@pina.market'),'from_name'=>'Pina.market'];
  if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$smtp['host'])||!filter_var($smtp['from'],FILTER_VALIDATE_EMAIL)||!in_array($smtp['security'],['ssl','tls'],true)||!in_array($smtp['port'],[465,587],true)||$smtp['user']===''||$smtp['password']==='')throw new RuntimeException('Проверьте настройки защищённого SMTP.');
  $admin=strtolower(setting('PINA_ADMIN_EMAIL','Email администратора'));out('Email администратора: '.$admin);if(!filter_var($admin,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Некорректный email администратора.');
  $password=ask('Пароль администратора от 12 символов','',true);if(strlen($password)<12||strlen($password)>200||ctype_digit($password))throw new RuntimeException('Пароль должен содержать от 12 до 200 символов и не состоять только из цифр.');
  if($password!==ask('Повторите пароль администратора','',true))throw new RuntimeException('Пароли не совпадают.');
  $address=setting('PINA_CASH_ADDRESS','Подтверждённый банком адрес кассы и часы работы (Enter — внесение отключено)');
  if($address==='')out('Внесение наличных отключено. Адрес кассы можно настроить после установки в админке.');
  $config=['base_url'=>$url,'public_dir'=>$public,'db'=>$db,'smtp'=>$smtp,'cash_address'=>$address,'cash_enabled'=>$address!=='','max_amount_minor'=>100000000,'debug'=>false];
 }
 $public=$config['public_dir'];if(!is_dir($public)||!is_writable($public))throw new RuntimeException('Каталог сайта недоступен.');
 $fcgiWrapper=trim(getenv('PINA_FCGI_WRAPPER')?:($config['fcgi_wrapper']??''));
 if($fcgiWrapper!==''){if(!preg_match('~^/[a-zA-Z0-9_./-]+$~D',$fcgiWrapper)||!is_file($fcgiWrapper)||!is_executable($fcgiWrapper))throw new RuntimeException('Обработчик FastCGI не найден или не исполняемый: '.$fcgiWrapper);$config['fcgi_wrapper']=$fcgiWrapper;out('Веб-обработчик PHP FastCGI: '.$fcgiWrapper);}

 $initialPublish=!is_file($public.'/.pina-installed');
 if($initialPublish&&count(array_diff(scandir($public),['.','..','.well-known']))>0){
  out('В выбранном каталоге уже есть сайт. Перед заменой создаётся полный архив файлов.');
  if(getenv('PINA_REPLACE_SITE')!=='1'&&ask('Для замены выбранного сайта введите ЗАМЕНИТЬ')!=='ЗАМЕНИТЬ')throw new RuntimeException('Установка остановлена без замены сайта.');
 }
 $backups=dirname($root).'/pinamarket-backups';if(!is_dir($backups))mkdir($backups,0700,true);chmod($backups,0700);
 // No private path or backup directory is published into the document root.
 if(str_starts_with(realpath($backups).'/',realpath($public).'/'))throw new RuntimeException('Каталог резервных копий должен быть вне сайта.');
 $stamp=gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));$backup=$backups.'/files-'.$stamp.'.tar.gz';
 if(count(array_diff(scandir($public),['.','..']))>0){$command='tar -czf '.escapeshellarg($backup).' -C '.escapeshellarg($public).' .';passthru($command,$code);if($code!==0)throw new RuntimeException('Резервная копия файлов не создана; замена остановлена.');chmod($backup,0600);out('Резервная копия файлов: '.$backup);}
 $b=$config['db'];$pdo=new PDO('mysql:host='.$b['host'].';port='.$b['port'].';dbname='.$b['name'].';charset=utf8mb4',$b['user'],$b['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $pdo->exec("SET time_zone='+00:00'");$pdo->query('SELECT 1')->fetchColumn();
 if(!$update){$existing=$pdo->query("SHOW TABLES LIKE 'pm_clients'")->fetchColumn();if($existing&&$pdo->query('SELECT COUNT(*) FROM pm_clients')->fetchColumn()>0)throw new RuntimeException('База уже содержит клиентов. Используйте другую пустую базу.');}
 // SQL is committed in the repository; no remote shell installers or package managers run here.
 foreach(explode(';',file_get_contents($root.'/database/schema.sql')) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
 $metaType=$pdo->query("SHOW COLUMNS FROM pm_meta LIKE 'value'")->fetch(PDO::FETCH_ASSOC);if(str_starts_with(strtolower($metaType['Type']),'varchar'))$pdo->exec('ALTER TABLE pm_meta MODIFY value TEXT NOT NULL');
 $version=$pdo->query("SELECT value FROM pm_meta WHERE name='schema_version'")->fetchColumn();if($version&&$version!=='1')throw new RuntimeException('Неподдерживаемая версия базы; требуется отдельная миграция.');
 if(!$update){
  put_private($configFile,"<?php\nreturn ".var_export($config,true).";\n");
  require $root.'/app/bootstrap.php';require $root.'/app/mail.php';
  try{send_message($admin,'Проверка отправки Pina.market','Почтовый сервер принял проверочное письмо Pina.market. После завершения установки откройте '. $config['base_url'].'/admin/ и войдите выбранным email и паролем.');}
  catch(Throwable){unlink($configFile);throw new RuntimeException('SMTP не принял письмо. Установка остановлена до публикации. Проверьте почтовые реквизиты.');}
  $pdo->beginTransaction();
  try{
   $pdo->prepare('INSERT INTO pm_clients(id,email,password_hash,is_staff,password_prompt,created_at) VALUES(1000,?,?,1,0,?)')->execute([$admin,password_hash($password,PASSWORD_DEFAULT),gmdate('Y-m-d H:i:s')]);
   $pdo->exec("UPDATE pm_counters SET value=1000 WHERE name='client'");
   $pdo->exec("INSERT INTO pm_meta(name,value) VALUES('installed','1'),('schema_version','1')");
   $catalog=json_decode(file_get_contents($root.'/app/catalog.json'),true,512,JSON_THROW_ON_ERROR);
   $s=$pdo->prepare('INSERT IGNORE INTO pm_services(code,name,description,instructions,conditions_text,product,category,regions_json,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
   foreach($catalog as $i)$s->execute([$i['id'],$i['name'],$i['desc'],'','',$i['type'],$i['cat'],'[]',gmdate('Y-m-d H:i:s')]);
   $pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();unlink($configFile);throw $e;}
  out('MySQL подключён. SMTP принял письмо. Администратор создан.');
 }
 // Initial publication replaces the old application as a directory, retaining it for rollback.
 $oldAppPath=null;if(!$initialPublish){if(is_file($public.'/pina-path.json'))$oldAppPath=json_decode(file_get_contents($public.'/pina-path.json'),true)['app']??null;elseif(is_file($public.'/pina-path.php'))$oldAppPath=require $public.'/pina-path.php';}
 $oldHadJson=is_file($public.'/pina-path.json');
 $publishDir=$public;
 if($initialPublish){$publishDir=dirname($public).'/.pinamarket-stage-'.$stamp;if(!mkdir($publishDir,0755))throw new RuntimeException('Не удалось создать временный каталог рядом с сайтом.');if(is_dir($public.'/.well-known'))copy_tree($public.'/.well-known',$publishDir.'/.well-known');}
 // mkdir modes are restricted by the shell umask (077 in bootstrap). Nginx needs public traversal.
 if(!chmod($publishDir,0755))throw new RuntimeException('Не удалось установить права 0755 на публичный каталог.');
 $html=file_get_contents($root.'/dist/index.html');$tag=substr(hash('sha256',file_get_contents($root.'/dist/app.js').file_get_contents($root.'/dist/legal.js').file_get_contents($root.'/dist/style.css')),0,12);
 foreach(['app.js','legal.js','style.css'] as $asset)$html=str_replace('/'.$asset,'/'.$asset.'?v='.$tag,$html);
 // Keep running PHP code immutable while Git updates the source checkout.
 $releaseApp=$root.'/var/releases/'.$stamp.'/app';if(!mkdir($releaseApp,0700,true))throw new RuntimeException('Не удалось создать приватную версию приложения.');
 foreach(scandir($root.'/app') as $name){if(in_array($name,['.','..','config.php','config.php.new','template.html'],true)||str_ends_with($name,'.lock'))continue;$from=$root.'/app/'.$name;if(is_file($from))copy_file($from,$releaseApp.'/'.$name,0600);}
 put_private($releaseApp.'/config.php',"<?php\nreturn ".var_export($config,true).";\n");
 put_private($releaseApp.'/template.html',$html);
 $assetNames=['app.js','legal.js','style.css','pina-logo.png'];foreach(array_merge(glob($root.'/dist/brand-*.png'),glob($root.'/dist/brand-*.svg')) as $brandFile)$assetNames[]=basename($brandFile);
 $assetHashes=[];foreach($assetNames as $asset)$assetHashes[$asset]=hash_file('sha256',$root.'/dist/'.$asset);
 put_private($releaseApp.'/release.json',json_encode(['id'=>$stamp,'assets'=>$assetHashes],JSON_THROW_ON_ERROR));
 foreach($assetNames as $asset)copy_file($root.'/dist/'.$asset,$publishDir.'/'.$asset);
 foreach(['.htaccess','.user.ini'] as $asset)copy_file($root.'/public/'.$asset,$publishDir.'/'.$asset);
 if($fcgiWrapper!==''){
  $handlers="\n# Pina.market: explicit hosting PHP handler\nAddHandler fcgid-script .php\nFCGIWrapper ".$fcgiWrapper." .php\n";
  if(file_put_contents($publishDir.'/.htaccess',$handlers,FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('Не удалось сохранить обработчик PHP в .htaccess.');
 }

 put_private($publishDir.'/pina-path.php',"<?php\nreturn ".var_export($releaseApp,true).";\n");
 put_private($publishDir.'/pina-path.json',json_encode(['app'=>$releaseApp],JSON_THROW_ON_ERROR));
 copy_file($root.'/public/index.php',$publishDir.'/index.php');
 file_put_contents($publishDir.'/.pina-installed',gmdate('c'));chmod($publishDir.'/.pina-installed',0600);
 if($initialPublish){$previous=dirname($public).'/.pinamarket-previous-'.$stamp;if(!rename($public,$previous))throw new RuntimeException('Не удалось сохранить прежний каталог сайта. Новые файлы оставлены в '.$publishDir);if(!rename($publishDir,$public)){rename($previous,$public);throw new RuntimeException('Переключение не удалось; прежний каталог восстановлен.');}out('Предыдущий каталог сохранён: '.$previous);}
 out('Файлы установлены. Админка: '.$config['base_url'].'/admin/');
 out('Проверка HTTPS и серверного приложения:');
 require_once __DIR__.'/verify.php';
 $verifyError=null;try{$checked=verify_site($config['base_url'],$stamp,$assetHashes);foreach($checked as $route)out('OK '.$route);}catch(Throwable $error){$verifyError=$error->getMessage();}
 if($verifyError!==null){
  out($verifyError);
  if($initialPublish&&isset($previous)&&is_dir($previous)){$failed=dirname($public).'/.pinamarket-failed-'.$stamp;if(rename($public,$failed)&&rename($previous,$public))throw new RuntimeException('HTTPS-проверка не прошла. Прежний сайт восстановлен. Проверьте SSL, DNS и путь каталога, затем повторите с --update. Настройки и база сохранены.');}
  if(!$initialPublish&&$oldAppPath){put_private($public.'/pina-path.php',"<?php\nreturn ".var_export($oldAppPath,true).";\n");if($oldHadJson)put_private($public.'/pina-path.json',json_encode(['app'=>$oldAppPath],JSON_THROW_ON_ERROR));elseif(is_file($public.'/pina-path.json'))unlink($public.'/pina-path.json');$restore='tar -xzf '.escapeshellarg($backup).' -C '.escapeshellarg($public).' ./app.js ./legal.js ./style.css ./pina-logo.png ./index.php ./.htaccess ./.user.ini';passthru($restore,$restoreCode);if($restoreCode!==0)throw new RuntimeException('HTTPS-проверка не прошла. Прежний backend включён, но файлы нужно восстановить из архива '.$backup);throw new RuntimeException('HTTPS-проверка не прошла. Прежняя версия приложения восстановлена; проверьте SSL и DNS, затем повторите обновление.');}
  throw new RuntimeException('HTTPS-проверка не прошла. Проверьте настройки домена; настройки и база сохранены.');
 }
 if($fcgiWrapper!=='')put_private($configFile,"<?php\nreturn ".var_export($config,true).";\n");
 out('Сайт отвечает, база подключена. Войдите в админку и задайте реальные условия сервисов.');
 out('Если был создан архив файлов, храните его вне сайта. Сервисы включаются после заполнения условий и регионов.');
}catch(Throwable $e){fwrite(STDERR,'Ошибка: '.($e instanceof PDOException?'Не удалось выполнить операцию MySQL. Проверьте реквизиты базы, права и InnoDB.':$e->getMessage())."\n");exit(1);}
