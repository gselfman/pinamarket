<?php
declare(strict_types=1);

// Shared parser for a genuine bank statement. It never creates customers,
// funding requests, card codes or merchant redemptions.
function gc_bank_csv(string $path):array {
 if(!is_file($path)||filesize($path)>1048576)throw new ApiError('Загрузите CSV до 1 МБ');
 $f=fopen($path,'r');if(!$f)throw new ApiError('Не удалось прочитать выписку');
 try{
  $head=fgetcsv($f,0,';','"','');if(!$head)throw new ApiError('Пустой CSV');
  $head=array_map(fn($s)=>trim(ltrim($s,"\xEF\xBB\xBF")),$head);
  if($head!==['reference','amount','bankDate','orderId','comment'])throw new ApiError('Нужны колонки reference;amount;bankDate;orderId;comment');
  $entries=[];
  while(($r=fgetcsv($f,0,';','"',''))!==false){
   if(count($entries)>=500)throw new ApiError('Максимум 500 строк в одной загрузке');
   if(count($r)!==5||!mb_check_encoding(implode('',$r),'UTF-8'))throw new ApiError('Неверные колонки или кодировка UTF-8');
   $e=array_combine($head,$r)+['bankVerified'=>true];
   txt($e,'reference',150);txt($e,'comment',3000,false);gc_date(txt($e,'bankDate',10));
   if(minor($e['amount'],100000000000)<=0)throw new ApiError('Сумма должна быть положительной');
   if($e['orderId']!==''&&!preg_match('/^[1-9][0-9]{0,14}$/D',$e['orderId']))throw new ApiError('Неверный номер заказа');
   $entries[]=$e;
  }
  if(!$entries)throw new ApiError('В выписке нет поступлений');
  return $entries;
 }finally{fclose($f);}
}
function gc_bank_import(array $entries,array $actor):array {
 gc_admin_guard('bank-import/','POST',$actor);
 return transact(function()use($entries,$actor){
  $added=0;$duplicates=0;
  foreach($entries as $e){$r=gc_payment_record($e,$actor);if($r['duplicate']??false)$duplicates++;else $added++;}
  audit((int)$actor['id'],'gift_bank_statement_imported','bank-import',['added'=>$added,'duplicates'=>$duplicates]);
  return ['added'=>$added,'duplicates'=>$duplicates];
 });
}
