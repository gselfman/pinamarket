<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);
try{
 if(!in_array($argv[1]??'',['--check','--prepare'],true))throw new RuntimeException('Use --check or --prepare');
 if(PHP_VERSION_ID<80200||PHP_INT_SIZE<8)throw new RuntimeException('Нужен 64-битный PHP 8.2+');
 foreach(['pdo_mysql','mbstring','openssl','curl','dom','zip','fileinfo'] as $ext)if(!extension_loaded($ext))throw new RuntimeException('В PHP отсутствует '.$ext);
 $public=realpath(getenv('PINA_PUBLIC_DIR')?:getenv('HOME').'/www/pina.market');
 if(!$public||!is_writable($public)||!is_writable(dirname($public))||is_link(getenv('PINA_PUBLIC_DIR')?:''))throw new RuntimeException('Каталог домена не найден, является ссылкой или недоступен');
 if(str_starts_with($root.'/',$public.'/'))throw new RuntimeException('Исходный код должен быть вне каталога сайта');
 $v0=$public.'/V0';if(file_exists($v0)||is_link($v0)){
  if(is_link($v0)||!is_dir($v0)||!is_file($v0.'/.pina-v0-archive'))throw new RuntimeException('V0 уже существует и не принадлежит установщику. Ничего не заменено.');
 }
 $app='';if(is_file($public.'/pina-path.json')){$pointer=json_decode(file_get_contents($public.'/pina-path.json'),true,512,JSON_THROW_ON_ERROR);$app=$pointer['app']??'';}
 elseif(is_file($public.'/pina-path.php'))$app=require $public.'/pina-path.php';
 if(!is_string($app)||$app==='')$app=(getenv('PINA_OLD_CODE_DIR')?:getenv('HOME').'/pinamarket-code').'/app';
 $source=realpath($app.'/config.php');
 if(!$source||str_starts_with($source,$public.'/'))throw new RuntimeException('Приватные настройки прежнего сайта не найдены. Прежний сайт не изменён.');
 $c=require $source;if(!is_array($c)||realpath($c['public_dir']??'')!==$public||rtrim($c['base_url']??'','/')!==rtrim(getenv('PINA_BASE_URL')?:'https://pina.market','/'))throw new RuntimeException('Домен или путь прежней конфигурации не совпадают с выбранным сайтом');
 $b=$c['db'];$pdo=new PDO('mysql:host='.$b['host'].';port='.($b['port']??3306).';dbname='.$b['name'].';charset=utf8mb4',$b['user'],$b['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 if(!$pdo->query("SHOW TABLES LIKE 'pm_clients'")->fetchColumn())throw new RuntimeException('Не найдена база прежнего сайта');
 foreach(['app/giftcards-shell.html','dist/giftcards/index.html','database/giftcards-credentials.sql'] as $file)if(!is_file($root.'/'.$file))throw new RuntimeException('Пакет неполон: '.$file);
 $fcgi=getenv('PINA_FCGI_WRAPPER')?:($c['fcgi_wrapper']??'');
 if($fcgi==='')foreach([getenv('HOME').'/php-bin-regru-php82/php',getenv('HOME').'/php-bin-php82/php',getenv('HOME').'/php-bin/pina.market/php'] as $candidate)if(is_file($candidate)&&is_executable($candidate)){$fcgi=$candidate;break;}
 if($fcgi===''||!is_file($fcgi)||!is_executable($fcgi))throw new RuntimeException('Не найден веб-обработчик PHP. Задайте PINA_FCGI_WRAPPER.');
 $secret=getenv('PINA_GIFT_VDAR_CONFIG')?:getenv('HOME').'/.pina-vdar.json';$seed=null;
 if(is_file($secret)){
  $secretReal=realpath($secret);if(is_link($secret)||str_starts_with($secretReal,$public.'/')||(fileperms($secret)&0077)!==0||filesize($secret)>8192)throw new RuntimeException('Файл V-DAR должен быть вне сайта, с правами 0600');
  $seed=json_decode(file_get_contents($secret),true,16,JSON_THROW_ON_ERROR);
  foreach(['site_id','merchant_id'] as $id)if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD',$seed[$id]??''))throw new RuntimeException('Неверный идентификатор в приватном файле V-DAR');
  if(!preg_match('/^[A-Za-z0-9_.-]{1,140}$/D',$seed['recipient_login']??'')||!preg_match('/^[A-Za-z0-9_-]{10,256}$/D',$seed['api_key']??''))throw new RuntimeException('Проверьте приватный файл V-DAR');
 }
 echo "Домен, каталог, прежняя база, PHP и доступ к настройкам проверены.\n";
 if(($argv[1]??'')==='--check'){echo "Проверка завершена: сайт, настройки и база не изменены.\n";exit;}
 if(is_file($root.'/app/config.php'))throw new RuntimeException('В новом каталоге уже есть настройки. Повторите --check либо запустите установщик в новом каталоге.');
 if(!is_dir($root.'/var'))mkdir($root.'/var',0700,true);
 if(!copy($source,$root.'/var/imported-config.php'))throw new RuntimeException('Не сохранены исходные настройки');chmod($root.'/var/imported-config.php',0600);
 $new=!isset($c['giftcards']);$g=$c['giftcards']??[];
 $g+=['sales_enabled'=>false,'encryption_key'=>base64_encode(random_bytes(32)),'storage_path'=>$root.'/var/giftcards-documents','agent_signature_approved'=>false,'legal_approved'=>false];
 $g['enabled']=true;$g['site_mode']=true;$g['balance_return_mode']='manual';
 if($new)$g['sales_enabled']=false;
 if($seed!==null)$g['pina']=$seed;
 $c['giftcards']=$g;$c['fcgi_wrapper']=$fcgi;
 $file=$root.'/app/config.php';if(file_put_contents($file,"<?php\nreturn ".var_export($c,true).";\n",LOCK_EX)===false)throw new RuntimeException('Не сохранены настройки');chmod($file,0600);
 echo "Настройки скопированы приватно. Пароли, пользователи и старые операции сохранены.\n";
}catch(Throwable $e){fwrite(STDERR,($e instanceof PDOException?'Не удалось подключиться к прежней базе данных.':$e->getMessage())."\n");exit(1);}
