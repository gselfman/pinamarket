<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!getenv('PINA_CONFIG')||!str_ends_with(getenv('DB_NAME')?:'','_test'))exit(1);
$root=dirname(__DIR__);$c=require getenv('PINA_CONFIG');$c['debug']=true;$c['base_url']='http://127.0.0.1:8081';
file_put_contents($root.'/app/config.php',"<?php return ".var_export($c,true).";");
require $root.'/app/bootstrap.php';
foreach(json_decode(file_get_contents($root.'/app/catalog.json'),true) as $service){query('INSERT IGNORE INTO pm_services(code,name,description,instructions,conditions_text,product,category,regions_json,updated_at) VALUES(?,?,?,?,?,?,?,?,?)',[$service['id'],$service['name'],$service['desc'],'','',$service['type'],$service['cat'],'[]',now()]);}

query('UPDATE pm_clients SET password_hash=?,password_prompt=0,temporary_until=NULL WHERE id IN(1000,1014)',[password_hash('PinaHttpTest2026-Secret',PASSWORD_DEFAULT)]);
query("UPDATE pm_services SET status='available',fee_bps=500,regions_json='[\"RU\"]',conditions_text='Wallet credit in RUB, manual execution within one business day',instructions='Enter the account login',version=version+1 WHERE code='steam'");
copy($root.'/dist/index.html',$root.'/app/template.html');foreach(['app.js','style.css','legal.js','pina-logo.png'] as $asset)copy($root.'/dist/'.$asset,$root.'/public/'.$asset);
foreach(array_merge(glob($root.'/dist/brand-*.png'),glob($root.'/dist/brand-*.svg')) as $asset)copy($asset,$root.'/public/'.basename($asset));
echo "HTTP test fixture prepared in disposable MySQL database.\n";
