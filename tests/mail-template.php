<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/mail-template.php';
$checks=0;
function mail_check(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$base='https://pina.market';$token=str_repeat('0',64);$reset=$base.'/reset/?token='.$token;$login=$base.'/giftcards/#/verify/'.$token;
$logo=file_get_contents(dirname(__DIR__).'/app/mail-logo.png');
$samples=[
 'reset'=>['Восстановление доступа Pina.market',"Вы запросили восстановление доступа к Pina Market.\n\nСсылка действует 1 час и используется один раз. Если вы не запрашивали восстановление, не открывайте её.\n\nДля установки нового пароля откройте адрес ниже целиком:\n\n".$reset."\n\n"],
 'login'=>['Вход в Pina Market',"Подтвердите email для входа в Pina Market.\n\nСсылка действует 30 минут и используется один раз. Никому её не пересылайте. Если вы не запрашивали вход, не открывайте её.\n\nДля входа откройте адрес ниже целиком:\n\n".$login."\n\n"],
 'notification'=>['Статус вашего заказа обновлён',"Здравствуйте!\n\nЗаказ № 1000\nСтатус: оплата подтверждена\n\nКарты доступны в личном кабинете. Вы можете запросить выпуск кода, когда будете готовы предъявить его выбранному поставщику."]
];
$out=getenv('PINA_MAIL_QA_DIR')?:sys_get_temp_dir().'/pina-mail-qa';if(!is_dir($out)&&!mkdir($out,0700,true))throw new RuntimeException('Cannot create test preview directory');
foreach($samples as $name=>[$subject,$body]){
 $m=pina_mail_mime($subject,$body,$base,$logo);mail_check($m['text']===$body,'plain-text fallback preserves complete message');
 $dom=new DOMDocument();@$dom->loadHTML($m['html'],LIBXML_NONET);$xp=new DOMXPath($dom);
 mail_check($xp->query('//script|//iframe|//form|//link')->length===0,'email has no scripts, forms or remote styles');
 mail_check($xp->query('//img[not(starts-with(@src,"cid:"))]')->length===0,'logo is embedded and requires no external request');
 if($name!=='notification'){
  $expected=$name==='reset'?$reset:$login;mail_check($m['action']['url']===$expected,'exact action URL retained');
  mail_check($xp->query('//a[@href="'.$expected.'"]')->length===2,'button and copyable fallback have exactly the same URL');
  mail_check(!str_contains($m['html'],'token='.$token.'Ссылка'),'instructions never join the action URL');
 }else mail_check($m['action']===null,'ordinary notification has no invented authentication button');
 $preview=str_replace('cid:pina-market-logo','data:image/png;base64,'.base64_encode($logo),$m['html']);
 file_put_contents($out.'/'.$name.'.html',$preview);
 file_put_contents($out.'/'.$name.'.eml',"MIME-Version: 1.0\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\nContent-Type: ".$m['type']."\r\n\r\n".$m['body']);
}
foreach([$base.'.invalid/reset/?token='.$token,'https://pina.market@invalid.example/reset/?token='.$token,$reset.'Ссылка действует 1 час','javascript:alert(1)',$base.'/reset/?token=bad'] as $bad)mail_check(pina_mail_action($bad,$base)===null,'untrusted or malformed URL never becomes an action button');
$escaped=pina_mail_template('<script>subject</script>',"<img src=x onerror=alert(1)>\n& private text",$base);
$dom=new DOMDocument();@$dom->loadHTML($escaped['html'],LIBXML_NONET);$xp=new DOMXPath($dom);mail_check($xp->query('//script|//*[@onerror]')->length===0&&str_contains($escaped['html'],'&lt;img'),'message text cannot create HTML elements');
$withoutImage=pina_mail_mime('Test','Plain text',$base);
mail_check(str_starts_with($withoutImage['type'],'multipart/alternative'),'missing logo does not prevent delivery');
file_put_contents($out.'/without-logo.eml',"MIME-Version: 1.0\r\nContent-Type: ".$withoutImage['type']."\r\n\r\n".$withoutImage['body']);
echo 'PASS: '.$checks." branded email, exact-link, escaping and fallback checks\n";
