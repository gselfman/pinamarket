<?php
// Genuine workflow checks in a disposable database with a mocked provider only.
if(!defined('PINA_LIBRARY_ONLY')||!str_contains(cfg()['db']['name'],'test'))throw new RuntimeException('Disposable test database required');
as_client(1000);$admin=current_client();$approved=one('SELECT * FROM pg_bank_approvals WHERE issuer_id=?',[$issuer]);
$i=one('SELECT * FROM pg_issuers WHERE id=?',[$issuer]);gc_issuer_sales_required($i);gassert(true,'current agreement and documented bank review permit new sales');
query("UPDATE pg_bank_approvals SET reviewed_at='2000-01-01 00:00:00' WHERE issuer_id=?",[$issuer]);
gassert(gc_catalog()===[],'stale documented screening hides new sales');
as_client(1014);greject(fn()=>giftcards_route('orders/','POST',array_replace($request,['key'=>gc_uuid()])),'stale screening rejects direct order API');
// Previously paid cards remain readable; a bank review does not rewrite history.
gassert(count(giftcards_route('state/','GET')['orders'])>0,'paid orders remain accessible while new sales are held');
query("UPDATE pg_bank_approvals SET reviewed_at=?,status='revoked' WHERE issuer_id=?",[now(),$issuer]);
greject(fn()=>giftcards_route('orders/','POST',array_replace($request,['key'=>gc_uuid()])),'revoked bank approval rejects new orders');
query("UPDATE pg_bank_approvals SET status='approved',terms_hash=? WHERE issuer_id=?",[str_repeat('0',64),$issuer]);
greject(fn()=>gc_issuer_sales_required($i),'bank approval must match actual legal and programme terms');
query('UPDATE pg_bank_approvals SET terms_hash=? WHERE issuer_id=?',[$approved['terms_hash'],$issuer]);
gc_issuer_sales_required($i);gassert(true,'restored documented current approval accepted');
as_client(1000);$old=one("SELECT value_json FROM pg_runtime_settings WHERE name='legal_source_sha256'")['value_json'];
gc_setting_save('legal_source_sha256','obsolete-source',$admin);gassert(!gc_legal_sales_ready(),'old administrative approval cannot approve a new offer silently');
as_client(1014);greject(fn()=>giftcards_route('orders/','POST',array_replace($request,['key'=>gc_uuid()])),'unapproved legal edition blocks new direct orders');
query("UPDATE pg_runtime_settings SET value_json=? WHERE name='legal_source_sha256'",[$old]);
$order=one('SELECT id,offer_hash FROM pg_orders WHERE client_id=1014 ORDER BY id LIMIT 1');$_GET=['orderId'=>$order['id']];
$historical=giftcards_route('orders/terms/','GET');gassert($historical['hash']===$order['offer_hash'],'customer retrieves the archived accepted offer for their own order');
as_client(1040);greject(fn()=>giftcards_route('orders/terms/','GET'),'another customer cannot read order-specific terms');
greject(fn()=>gc_object_access('bank_approval',$issuer,current_client()),'bank approval proof is not a customer document');
as_client(1000);$_GET=[];
