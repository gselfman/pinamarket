<?php
declare(strict_types=1);

/** Only explicit first-party action URLs become buttons. No message text is HTML. */
function pina_mail_action(string $body,string $siteUrl):?array {
 $site=parse_url($siteUrl);
 foreach(explode("\n",str_replace(["\r\n","\r"],"\n",$body)) as $line){
  $url=trim($line);if(!filter_var($url,FILTER_VALIDATE_URL))continue;$u=parse_url($url);
  if(($u['scheme']??'')!=='https'||isset($u['user'])||isset($u['pass'])||strtolower($u['host']??'')!==strtolower($site['host']??'')||($u['port']??443)!==($site['port']??443))continue;
  if(($u['path']??'')==='/reset/'&&preg_match('/^token=[a-f0-9]{64}$/D',$u['query']??'')&&!isset($u['fragment']))return ['url'=>$url,'kind'=>'reset','label'=>'Задать новый пароль','title'=>'Восстановите доступ','eyebrow'=>'БЕЗОПАСНОСТЬ АККАУНТА','preview'=>'Ссылка для установки нового пароля Pina Market.'];
  if(($u['path']??'')==='/giftcards/'&&!isset($u['query'])&&preg_match('~^/verify/[a-f0-9]{64}$~D',$u['fragment']??''))return ['url'=>$url,'kind'=>'login','label'=>'Войти в кабинет','title'=>'Ваш кабинет — по ссылке','eyebrow'=>'ВХОД В PINA MARKET','preview'=>'Откройте личный кабинет Pina Market по одноразовой ссылке.'];
 }
 return null;
}

function pina_mail_template(string $subject,string $body,string $siteUrl,?string $logoSrc=null):array {
 $e=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $text=str_replace(["\r\n","\r"],"\n",$body);$action=pina_mail_action($text,$siteUrl);
 $copy=$action?str_replace($action['url'],'',$text):$text;
 // Remove the now-empty introduction to the separately rendered action button.
 $copy=preg_replace('~^(?:Для установки нового пароля|Для входа) откройте адрес ниже целиком:\s*$~mu','',$copy);
 $paragraphs=preg_split('/\n[\t ]*\n/',trim($copy));$content='';
 foreach($paragraphs as $paragraph)if(trim($paragraph)!=='')$content.='<p style="margin:0 0 18px;color:#526259;font-size:16px;line-height:26px;overflow-wrap:anywhere;">'.nl2br($e(trim($paragraph)),false).'</p>';
 $title=$action['title']??$subject;$eyebrow=$action['eyebrow']??'ВАШ АККАУНТ PINA MARKET';$preview=$action['preview']??'Уведомление по вашему аккаунту Pina Market.';
 $logo=$logoSrc?'<img src="'.$e($logoSrc).'" width="174" alt="Pina.market" style="display:block;width:174px;max-width:100%;height:auto;border:0;">':'<span style="font-size:27px;font-weight:700;letter-spacing:-1px;color:#1e7a46;">Pina.market</span>';
 $button='';
 if($action){$url=$e($action['url']);$label=$e($action['label']);
  // A real anchor defines the exact URL. Explanatory copy can never enter href.
  $button='<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0 26px;"><tr><td bgcolor="#1e7a46" style="border-radius:12px;text-align:center;mso-padding-alt:16px 26px;"><a href="'.$url.'" style="display:inline-block;padding:16px 26px;border:1px solid #1e7a46;border-radius:12px;color:#ffffff;background:#1e7a46;font-size:16px;font-weight:700;line-height:22px;text-decoration:none;mso-padding-alt:0;">'.$label.'</a></td></tr></table><div style="padding:18px;background:#f6f8f2;border:1px solid #d8e2d8;border-radius:12px;"><p style="margin:0 0 9px;font-size:12px;line-height:19px;color:#526259;">Если кнопка не открывается, скопируйте адрес целиком:</p><a href="'.$url.'" style="font-family:Consolas,monospace;font-size:11px;line-height:19px;color:#1e7a46;text-decoration:underline;word-break:break-all;overflow-wrap:anywhere;">'.$url.'</a></div>';
 }
 $html='<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title>'.$e($subject).'</title><style>@media only screen and (max-width:600px){.mail-outer{padding:16px 10px!important}.mail-card{padding:28px 22px!important}.mail-title{font-size:28px!important;line-height:34px!important}}</style></head><body style="margin:0;padding:0;background:#f6f8f2;color:#163b2a;font-family:Manrope,Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;"><div style="display:none;font-size:1px;color:#f6f8f2;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">'.$e($preview).'</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f6f8f2"><tr><td class="mail-outer" align="center" style="padding:40px 20px;"><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;"><tr><td style="padding:0 8px 24px;">'.$logo.'</td></tr><tr><td class="mail-card" bgcolor="#ffffff" style="padding:40px;border:1px solid #d8e2d8;border-radius:24px;"><p style="margin:0 0 18px;color:#1e7a46;font-size:10px;line-height:16px;font-weight:700;letter-spacing:1.6px;">'.$e($eyebrow).'</p><h1 class="mail-title" style="margin:0 0 22px;font-size:34px;line-height:41px;font-weight:700;letter-spacing:-1px;color:#163b2a;">'.$e($title).'</h1>'.$content.$button.'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;"><tr><td style="padding:20px 0 0;border-top:1px solid #d8e2d8;color:#526259;font-size:13px;line-height:21px;">Нужна помощь? Напишите нам:<br><a href="mailto:mail@pina.market" style="color:#1e7a46;font-weight:700;text-decoration:none;">mail@pina.market</a></td></tr></table></td></tr><tr><td style="padding:24px 8px 0;color:#526259;font-size:11px;line-height:19px;">Pina Market LLC · Республика Армения<br>Сервис подарочных карт зарубежных компаний.</td></tr></table></td></tr></table></body></html>';
 return ['html'=>$html,'text'=>$text,'action'=>$action];
}

/** Multipart messages retain a genuine plain-text alternative and embedded logo. */
function pina_mail_mime(string $subject,string $body,string $siteUrl,?string $logoBytes=null):array {
 $mail=pina_mail_template($subject,$body,$siteUrl,$logoBytes!==null?'cid:pina-market-logo':null);
 $alternative='pina-alt-'.bin2hex(random_bytes(18));$related='pina-related-'.bin2hex(random_bytes(18));
 $encode=static fn(string $s):string=>chunk_split(base64_encode($s),76,"\r\n");
 $message='--'.$alternative."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".$encode($mail['text']);
 $message.='--'.$alternative."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".$encode($mail['html']).'--'.$alternative."--\r\n";
 $type='multipart/alternative; boundary="'.$alternative.'"';
 if($logoBytes!==null){$message='--'.$related."\r\nContent-Type: ".$type."\r\n\r\n".$message.'--'.$related."\r\nContent-Type: image/png; name=\"pina-market.png\"\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <pina-market-logo>\r\nContent-Disposition: inline; filename=\"pina-market.png\"\r\n\r\n".$encode($logoBytes).'--'.$related."--\r\n";$type='multipart/related; type="multipart/alternative"; boundary="'.$related.'"';}
 return ['type'=>$type,'body'=>$message]+$mail;
}
