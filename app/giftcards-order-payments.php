<?php
declare(strict_types=1);
function gc_cash_locations(bool $activeOnly=false):array {
 $g=gc_settings();
 if(array_key_exists('payment_cash_locations',$g))$locations=$g['payment_cash_locations'];
 else {$old=site_settings()['cashAddress']??'';$address=is_string($old)&&mb_stripos($old,'Складоч')!==false?$old:'127018, г. Москва, ул. Складочная, д. 1, стр. 1, БЦ «СТАНКОЛИТ», офис 1620';$locations=[['id'=>'amra-moscow','name'=>'ООО РНКО «АМРА» — Москва','address'=>$address,'active'=>true]];}
 return array_values(array_filter(is_array($locations)?$locations:[],fn($l)=>is_array($l)&&(!$activeOnly||($l['active']??false)===true)));
}
function gc_validate_cash_locations(mixed $locations):array {
 if(!is_array($locations)||count($locations)>20)throw new ApiError('Укажите не более 20 адресов касс');$out=[];$ids=[];
 foreach($locations as $l){if(!is_array($l))throw new ApiError('Проверьте адреса касс');$id=txt($l,'id',60);if(!preg_match('/^[A-Za-z0-9_-]{1,60}$/D',$id)||isset($ids[$id]))throw new ApiError('У касс должны быть разные идентификаторы');$ids[$id]=true;$out[]=['id'=>$id,'name'=>txt($l,'name',180),'address'=>txt($l,'address',1000),'active'=>($l['active']??false)===true];}
 return $out;
}
function gc_invoice(array $o):array {
 $snapshot=json_decode($o['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$company=legal_data()['company'];$request=one('SELECT * FROM pg_order_payment_requests WHERE order_id=?',[$o['id']]);$locations=gc_cash_locations(true);$location=null;
 foreach($locations as $l)if($l['id']===($request['cash_location_id']??'')){$location=$l;break;}$location??=$locations[0]??null;
 $events=one('SELECT COUNT(*) n,COALESCE(SUM(amount_minor),0) amount FROM pg_bank_events WHERE order_id=?',[$o['id']]);$receipt=one('SELECT amount_minor FROM pg_bank_receipts WHERE order_id=?',[$o['id']]);$paid=(int)$events['n']>0?(int)$events['amount']:(int)($receipt['amount_minor']??0);$due=max(0,(int)$o['total_minor']-$paid);
 $purpose='Оплата подарочных карт '.$snapshot['company']['name'].', заказ Pina Market № '.$o['id'].'; ID покупателя '.$o['client_id'].'.';
 $fields=['Номер заказа'=>(string)$o['id'],'ID покупателя'=>(string)$o['client_id'],'Сумма к оплате'=>VdarClient::decimal($due).' RUB','Стоимость заказа'=>VdarClient::decimal((int)$o['total_minor']).' RUB','Адрес кассы'=>$location['address']??'Касса временно недоступна. Обратитесь в поддержку.','Касса'=>$location['name']??'Не выбрана','Получатель'=>$company['name'],'Эмитент'=>$snapshot['company']['name'],'Место исполнения'=>($snapshot['program']['use_country']??'За пределами РФ').', '.($snapshot['program']['fulfillment_address']??'по сохранённым условиям заказа'),'Счёт'=>$company['account'],'Банк'=>$company['bank'],'БИК'=>$company['bik'],'Корреспондентский счёт'=>$company['corr'],'ИНН / КПП'=>$company['inn'].' / '.$company['kpp'],'Назначение'=>$purpose];
 if($paid>0)$fields['Подтверждено поступление']=VdarClient::decimal($paid).' RUB';
 return ['orderId'=>(int)$o['id'],'clientId'=>(int)$o['client_id'],'totalMinor'=>(int)$o['total_minor'],'paidMinor'=>$paid,'dueMinor'=>$due,'paymentRequestedAt'=>$request['requested_at']??null,'cashLocationId'=>$location['id']??null,'cashLocations'=>$locations,'fields'=>$fields,'instructions'=>'При обращении в кассу возьмите паспорт и сообщите кассиру номер заказа '.$o['id'].' и ID покупателя '.$o['client_id'].'. '.(string)(gc_settings()['payment_instructions']??'Наличные принимает банк; Pina Market получает банковский перевод. Возможную комиссию уточните в банке до оплаты.')];
}
function gc_request_order_payment(array $actor,array $d):array {
 return transact(function()use($actor,$d){$id=(int)($d['orderId']??0);$o=one('SELECT * FROM pg_orders WHERE id=? AND client_id=? FOR UPDATE',[$id,$actor['id']]);if(!$o)throw new ApiError('Заказ не найден',404);$invoice=gc_invoice($o);if($o['payment_status']==='confirmed'||$invoice['dueMinor']===0)return $invoice;
  $locations=$invoice['cashLocations'];if(!$locations)throw new ApiError('Оплата через кассу временно недоступна. Заказ сохранён, обратитесь в поддержку.',409);
  $location=txt($d,'cashLocationId',60,false)?:($invoice['cashLocationId']??'');if(!in_array($location,array_column($locations,'id'),true))throw new ApiError('Выберите доступную кассу банка',409);
  $old=one('SELECT * FROM pg_order_payment_requests WHERE order_id=?',[$id]);if(!$old)query('INSERT INTO pg_order_payment_requests(order_id,client_id,cash_location_id,requested_at,updated_at) VALUES(?,?,?,?,?)',[$id,$actor['id'],$location,now(),now()]);elseif($old['cash_location_id']!==$location)query('UPDATE pg_order_payment_requests SET cash_location_id=?,updated_at=? WHERE order_id=?',[$location,now(),$id]);
  if(!$old||$old['cash_location_id']!==$location)audit((int)$actor['id'],'gift_order_payment_requested',(string)$id,['clientId'=>$actor['id'],'cashLocationId'=>$location,'total_minor'=>$o['total_minor']]);return gc_invoice($o);
 });
}
