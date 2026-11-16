<?php
declare(strict_types=1);

/** Capture the actual caller and transport. Background work never impersonates a browser visit. */
function audit_context_write(int $id,?int $actor,string $action,string $ref,array $data):void {
 if(!str_starts_with($action,'gift_'))return;
 static $available=null;
 if($available===null)$available=(bool)one("SHOW TABLES LIKE 'pg_audit_context'");
 if(!$available)return; // Historical releases are explicitly displayed without context.
 $person=$actor?one('SELECT is_staff FROM pm_clients WHERE id=?',[$actor]):null;
 $kind=$actor===null?'system':(!empty($person['is_staff'])?'staff':'customer');
 if($kind==='customer'&&one('SELECT issuer_id FROM pg_issuer_members WHERE client_id=? LIMIT 1',[$actor]))$kind='merchant';
 $target=$kind==='customer'?$actor:null;$issuer=null;
 if(isset($data['clientId'])&&is_int($data['clientId']))$target=$data['clientId'];
 $orderActions=['gift_order_created','gift_bank_confirmed','gift_payment_matched','gift_staff_order_created'];
 if(in_array($action,$orderActions,true)){$o=one('SELECT client_id,issuer_id FROM pg_orders WHERE id=?',[$ref]);if($o){$target=(int)$o['client_id'];$issuer=$o['issuer_id'];}}
 $cardActions=['gift_code_created','gift_code_activated','gift_code_cancelled','gift_code_revealed','gift_issue_requested','gift_staff_issue_requested','gift_refund_requested','gift_cancellation_reconciled','gift_issue_reconciled','gift_unissued_card_cancelled'];
 if(in_array($action,$cardActions,true)){$o=one('SELECT o.client_id,o.issuer_id FROM pg_cards c JOIN pg_orders o ON o.id=c.order_id WHERE c.id=?',[$ref]);if($o){$target=(int)$o['client_id'];$issuer=$o['issuer_id'];}}
 if($action==='gift_activation_requested'){$o=one('SELECT o.client_id,o.issuer_id FROM pg_activations a JOIN pg_cards c ON c.id=a.card_id JOIN pg_orders o ON o.id=c.order_id WHERE a.id=?',[$ref]);if($o){$target=(int)$o['client_id'];$issuer=$o['issuer_id'];}}
 $web=!in_array(PHP_SAPI,['cli','phpdbg'],true);
 $ua=$web?mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500):'';
 $ip=$web?(string)($_SERVER['REMOTE_ADDR']??''):'';
 $key=(string)(cfg()['giftcards']['encryption_key']??'');
 $ipHash=$ip!==''&&$key!==''?hash_hmac('sha256','audit-ip-v1|'.$ip,$key):null;
 query('INSERT INTO pg_audit_context(audit_id,actor_kind,source,target_client_id,issuer_id,user_agent,ip_hash) VALUES(?,?,?,?,?,?,?)',[$id,$kind,$web?'web':'cli',$target,$issuer,$ua,$ipHash]);
}
