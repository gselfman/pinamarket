<?php
declare(strict_types=1);
$data=json_decode(file_get_contents(__DIR__.'/legal.json'),true,512,JSON_THROW_ON_ERROR);$doc=$data['documents'][$m[1]]??null;
if(!$doc){http_response_code(404);exit('Документ не найден');}
function he(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=he($doc['title'])?> — Pina.market</title><link rel="stylesheet" href="/style.css"><style>main{max-width:900px;margin:40px auto;padding:20px}h1{font-size:32px}p{white-space:pre-line;line-height:1.9}h2{font-size:19px;margin-top:30px}@media print{.no-print{display:none}main{margin:0}}</style><main><a href="/" class="no-print"><img src="/pina-logo.png" width="200" height="60" alt="Pina.market"></a><h1><?=he($doc['title'])?></h1><p class="note">Редакция от <?=he($data['effective_date'])?> · <?=he($data['version'])?></p><?php foreach($doc['sections'] as [$title,$text]):?><h2><?=he($title)?></h2><p><?=he($text)?></p><?php endforeach;?></main></html>
