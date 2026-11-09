<?php
declare(strict_types=1);
// Enqueue inside the same transaction as the financial/status event. No SMTP in transactions.
function queue_mail(string $recipient,string $subject,string $body):void {
 query('INSERT INTO pm_mail_queue(recipient,subject,body,available_at,created_at) VALUES(?,?,?,?,?)',[$recipient,$subject,$body,now(),now()]);
}
function queue_operation_mail(int $id,string $event):void {
 $o=one(op_select().' WHERE o.number=?',[$id]);if(!$o)throw new RuntimeException('Missing mail operation');
 $s=json_decode($o['snapshot_json'],true);$labels=['new'=>'Ожидает обработки','in_progress'=>'Взята в работу','needs_info'=>'Требуется уточнение','completed'=>$o['kind']==='deposit'?'Деньги зачислены':'Выполнена','cancelled'=>'Отменена'];
 $title=match($o['kind']){'deposit'=>'Пополнение баланса','refund'=>'Возврат аванса','credit'=>'Ручное зачисление','debit'=>'Ручное списание',default=>'Оплата сервиса'}.' №'.$id;
 $status=$event==='created'?'Заявка создана':($event==='reply'?'Уточнение получено, заявка возвращена в очередь':$labels[$o['status']]);
 $body=$title."\nКлиент ID ".$o['client_id']."\nСтатус: ".$status."\nСумма заявки: ".number_format((int)$o['amount_minor']/100,2,'.','')." RUB\n";
 if($o['kind']==='deposit'){
  $company=legal_data()['company'];$body.='Касса: '.($s['cashAddress']??'')."\nПолучатель: ".$company['name']."\nБанк: ".$company['bank']."\nСчёт: ".$company['account']."\nБИК: ".$company['bik']."\nКорреспондентский счёт: ".$company['corr']."\nИНН / КПП: ".$company['inn'].' / '.$company['kpp']."\nНазначение: ".payment_purpose((int)$o['client_id'],$id)."\n";
  if($o['status']==='completed')$body.='Фактически зачислено: '.number_format((int)$o['total_minor']/100,2,'.','')." RUB\nБанковское поступление: ".$o['bank_reference']."\n";
 }else{$body.='Сервис: '.($s['name']??'Возврат аванса')."\nПолучатель: ".$o['recipient']."\nИтого: ".number_format((int)$o['total_minor']/100,2,'.','')." RUB\n";}
 if(!empty($s['executionProof']))$body.='Подтверждение исполнения: '.$s['executionProof']."\n";
 $message=one('SELECT body FROM pm_messages WHERE operation_id=? ORDER BY id DESC LIMIT 1',[$id]);
 if($event!=='created'&&$event!=='take'&&$message)$body.='Сообщение: '.$message['body']."\n";
 $base=rtrim(cfg()['base_url'],'/');queue_mail($o['email'],'Pina.market — '.$title.' — '.$status,$body."\nЛичный кабинет: ".$base."/cabinet/\nПоддержка: mail@pina.market");
 if(in_array($event,['created','reply'],true))foreach(rows('SELECT email FROM pm_clients WHERE is_staff=1') as $admin)queue_mail($admin['email'],'Pina.market — '.$title.' — '.$status,$body."\nEmail клиента: ".$o['email']."\nОбработать заявку: ".$base.'/admin/');
}
function deliver_operation_mail(int $limit=20):int {
 if(db()->inTransaction())throw new RuntimeException('Cannot send before commit');
 // One sender per database; abandoned locks are released by MySQL on disconnect.
 $lock='pina-mail-'.substr(hash('sha256',cfg()['db']['name']),0,32);
 if(!(int)one('SELECT GET_LOCK(?,0) acquired',[$lock])['acquired'])return 0;
 $sent=0;
 try{for($i=0;$i<max(1,min(50,$limit));$i++){
  $mail=one('SELECT q.* FROM pm_mail_queue q WHERE q.sent_at IS NULL AND q.available_at<=? AND NOT EXISTS (SELECT 1 FROM pm_mail_queue earlier WHERE earlier.recipient=q.recipient AND earlier.id<q.id AND earlier.sent_at IS NULL) ORDER BY q.id LIMIT 1',[now()]);if(!$mail)break;
  try{send_message($mail['recipient'],$mail['subject'],$mail['body']);query("UPDATE pm_mail_queue SET sent_at=?,attempts=attempts+1,last_error='' WHERE id=?",[now(),$mail['id']]);$sent++;}
  catch(Throwable){$delay=min(3600,60*(2**min(6,(int)$mail['attempts'])));query('UPDATE pm_mail_queue SET attempts=attempts+1,available_at=?,last_error=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()+$delay),'SMTP не подтвердил отправку. Повтор запланирован.',$mail['id']]);}
 }}finally{one('SELECT RELEASE_LOCK(?) released',[$lock]);}return $sent;
}
