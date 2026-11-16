<?php
// Genuine workflow checks in a disposable database with a mocked provider only.
if(!defined('PINA_LIBRARY_ONLY')||!str_contains(cfg()['db']['name'],'test'))throw new RuntimeException('Disposable test database required');
as_client(1000);$admin=current_client();
$i=one('SELECT * FROM pg_issuers WHERE id=?',[$issuer]);gc_issuer_sales_required($i);
gassert(!one('SELECT issuer_id FROM pg_bank_approvals WHERE issuer_id=?',[$issuer]),'no bank approval record is needed on the site');
gassert(count(gc_catalog())>0,'current supplier is available without a bank review workflow');
$bad=$i;$bad['contract_version']='obsolete-contract';greject(fn()=>gc_issuer_sales_required($bad),'current issuer agreement is still required');
greject(fn()=>gc_admin_route('bank-approval/','POST',[],current_client()),'removed bank approval endpoint is unavailable');
as_client(1000);$old=one("SELECT value_json FROM pg_runtime_settings WHERE name='legal_source_sha256'")['value_json'];
gc_setting_save('legal_source_sha256','obsolete-source',$admin);gassert(!gc_legal_sales_ready(),'old administrative approval cannot approve a new offer silently');
as_client(1014);greject(fn()=>giftcards_route('orders/','POST',array_replace($request,['key'=>gc_uuid()])),'unapproved legal edition blocks new direct orders');
query("UPDATE pg_runtime_settings SET value_json=? WHERE name='legal_source_sha256'",[$old]);
$order=one('SELECT id,offer_hash FROM pg_orders WHERE client_id=1014 ORDER BY id LIMIT 1');$_GET=['orderId'=>$order['id']];
$historical=giftcards_route('orders/terms/','GET');gassert($historical['hash']===$order['offer_hash'],'customer retrieves the archived accepted offer for their own order');
as_client(1040);greject(fn()=>giftcards_route('orders/terms/','GET'),'another customer cannot read order-specific terms');
as_client(1000);$_GET=[];
