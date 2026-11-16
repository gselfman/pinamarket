<?php
declare(strict_types=1);
function gc_report_cell(mixed $value):string {$s=(string)($value??'');if(preg_match('/^[\x00-\x20]*[=+@\-]/',$s))$s="'".$s;return $s;}
function gc_admin_report(array $q,array $actor):never {
 $kind=(string)($q['kind']??'activations');if(!in_array($kind,['orders','cards','payments','activations','settlements','refunds','commands','clients','audit'],true))throw new ApiError('Выберите реестр для выгрузки');
 $items=[];$q['limit']=100;for($page=1;$page<=100;$page++){$q['page']=$page;$r=gc_admin_list($kind,$q);$items=array_merge($items,$r['items']);if($page>=$r['pages'])break;}if($r['total']>10000)throw new ApiError('Более 10 000 строк: сузьте период или выберите одного поставщика');
 $definitions=[
 'orders'=>['id'=>'Заказ','email'=>'Покупатель','issuer_name'=>'Поставщик','total_minor'=>'Сумма RUB','payment_status'=>'Оплата','created_at'=>'Создан UTC'],
 'cards'=>['id'=>'Карта без секретного кода','order_id'=>'Заказ','email'=>'Покупатель','issuer_name'=>'Поставщик','nominal_minor'=>'Номинал RUB','issuance_status'=>'Выпуск','redemption_status'=>'Погашение','refund_status'=>'Возврат'],
 'payments'=>['reference'=>'Банковское основание','order_id'=>'Заказ','issuer_name'=>'Поставщик','amount_minor'=>'Поступило RUB','bank_date'=>'Дата банка','comment'=>'Комментарий'],
 'activations'=>['id'=>'Активация','card_id'=>'Карта без секретного кода','order_id'=>'Заказ','issuer_name'=>'Поставщик','buyer_name'=>'ФИО покупателя','contract_reference'=>'Договор покупателя','nominal_minor'=>'Номинал RUB','provider_commission_minor'=>'Комиссия платформы RUB','activated_at'=>'Погашена UTC','settlement_id'=>'Реестр выплат'],
 'settlements'=>['id'=>'Реестр','issuer_name'=>'Поставщик','gross_minor'=>'Номинал RUB','agent_fee_minor'=>'Агент RUB','control_fee_minor'=>'Валютный контроль RUB','transfer_fee_minor'=>'Перевод RUB','net_minor'=>'По договору RUB','expected_net_minor'=>'К перечислению с корректировками RUB','status'=>'Статус','bank_reference'=>'Зачисление','received_minor'=>'Получено RUB'],
 'refunds'=>['id'=>'Обращение','email'=>'Покупатель','issuer_name'=>'Поставщик','order_id'=>'Заказ','reason'=>'Причина','status'=>'Статус','amount_minor'=>'Возвращено покупателю RUB','recovered_minor'=>'Возмещено эмитентом RUB','due_at'=>'Срок UTC','resolution'=>'Решение'],
 'commands'=>['id'=>'Операция','order_id'=>'Заказ','issuer_name'=>'Поставщик','kind'=>'Тип','status'=>'Статус','error_code'=>'Причина','created_at'=>'Создана UTC'],
 'clients'=>['id'=>'Клиент','email'=>'Email','email_verified_at'=>'Почта подтверждена UTC','order_count'=>'Заказов','sales_blocked'=>'Новые покупки ограничены'],
 'audit'=>['id'=>'Событие','email'=>'Участник','actor_kind'=>'Роль в момент события','source'=>'Источник','target_client_id'=>'Связанный клиент','user_agent'=>'Фактический заголовок браузера','ip_hash'=>'Псевдоним сетевого адреса','action'=>'Действие','object_ref'=>'Основание','details_json'=>'Подробности','created_at'=>'Дата UTC']
 ];
 $fields=$definitions[$kind];$table=[array_values($fields)];foreach($items as $row){$line=[];foreach($fields as $key=>$label){$value=$row[$key]??'';if(str_ends_with($key,'_minor')&&$value!=='')$value=VdarClient::decimal((int)$value);$line[]=gc_report_cell($value);}$table[]=$line;}
 audit((int)$actor['id'],'gift_report_exported',$kind,['issuerId'=>$q['issuerId']??null,'rows'=>count($items),'from'=>$q['from']??null,'to'=>$q['to']??null]);
 header('Cache-Control: private, no-store');$format=(string)($q['format']??'csv');$filename='Pina-'.$kind.'-'.gmdate('Y-m-d');
 if($format==='print'){
  header('Content-Type: text/html; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'.html"');$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>'.$e($filename).'</title><style>body{font:12px Arial;margin:24px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #aaa;padding:6px;text-align:left;overflow-wrap:anywhere}h1{font-size:22px}@media print{@page{size:landscape;margin:12mm}thead{display:table-header-group}}</style><h1>Pina Market · Отчёт</h1><p>Сформирован '.$e(gmdate('d.m.Y H:i')).' UTC. Строк: '.count($items).'. Период: '.$e($q['from']??'начало').' — '.$e($q['to']??'сегодня').'</p><table><thead><tr>';foreach(array_shift($table) as $cell)echo '<th>'.$e($cell).'</th>';echo '</tr></thead><tbody>';foreach($table as $row){echo '<tr>';foreach($row as $cell)echo '<td>'.$e($cell).'</td>';echo '</tr>';}echo '</tbody></table></html>';exit;
 }
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");foreach($table as $row)fputcsv($out,$row,';','"','');fclose($out);exit;
}
