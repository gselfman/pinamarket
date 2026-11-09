<?php
declare(strict_types=1);
$data=json_decode(file_get_contents(__DIR__.'/legal.json'),true,512,JSON_THROW_ON_ERROR);
function shell_escape(string $text):string{return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$titles=['/'=>'Оплата цифровых сервисов','/login/'=>'Вход в Pina.market','/register/'=>'Регистрация в Pina.market','/admin/'=>'Вход для сотрудников','/cabinet/'=>'Личный кабинет','/reset/'=>'Восстановление доступа','/documents/'=>'Документы Pina.market'];
ob_start();
?><div class="wrap" data-pina-page="<?=shell_escape($path)?>"><header class="header"><a href="/" aria-label="Pina.market — на главную"><img class="brand-logo" src="/pina-logo.png" alt="Pina.market" width="250" height="75"></a><nav class="links"><a href="/login/">Войти</a><a href="/register/">Создать кабинет</a></nav></header><main class="panel" style="max-width:680px;margin:40px auto"><h1 style="font-size:32px"><?=shell_escape($titles[$path])?></h1>
<?php if($path==='/register/'):?><p>Укажите email. Отправим временный пароль и ссылку на страницу входа.</p><?php elseif($path==='/'):?><p>Пополняйте баланс Pina.market и оформляйте заказы на игры, приложения и подписки. Условия и итоговая стоимость доступны до оплаты.</p><?php elseif($path!=='/documents/'):?><p>Используйте email и пароль вашей учётной записи.</p><?php endif;?>
<?php if(in_array($path,['/login/','/register/','/admin/','/cabinet/'],true)):?>
<fieldset disabled class="fields" style="border:0;padding:0"><div class="field"><label for="pending-email">Введите email</label><input id="pending-email" type="email" autocomplete="email" placeholder="Ваша электронная почта"></div><?php if($path!=='/register/'):?><div class="field"><label for="pending-password">Пароль</label><input id="pending-password" type="password" autocomplete="current-password"></div><?php endif;?><button type="button" class="btn full"><?=$path==='/register/'?'Получить пароль на почту':'Войти'?></button></fieldset>
<?php endif;?>
<?php if($path!=='/documents/'):?><p role="status">Загружаем приложение… Если форма не стала активной, <a href="<?=shell_escape($path)?>">обновите страницу</a> или напишите в поддержку.</p><p class="notice">Для входа и операций включите JavaScript в браузере. Документы и контакты доступны ниже.</p><noscript><p>Включите JavaScript в настройках браузера и обновите страницу.</p></noscript><?php endif;?>
<nav aria-label="Документы"><ul><?php foreach($data['documents'] as $slug=>$doc):?><li><a href="/documents/<?=shell_escape($slug)?>/"><?=shell_escape($doc['title'])?></a></li><?php endforeach;?></ul></nav><p><?=shell_escape($data['company']['name'])?> · <a href="mailto:<?=shell_escape($data['company']['email'])?>"><?=shell_escape($data['company']['email'])?></a></p></main></div><?php
$fallback=ob_get_clean();
$html=file_get_contents(__DIR__.'/template.html');
$html=str_replace('<div id="app"></div>','<div id="app">'.$fallback.'</div>',$html);
$html=str_replace('</head>','<meta name="pina-release" content="'.shell_escape($release['id']??'source').'"></head>',$html);
