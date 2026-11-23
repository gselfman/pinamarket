<?php
// Complete launch on disposable data. Banking and V-DAR transports remain mocked.
if(!defined('PINA_LIBRARY_ONLY')||!str_contains(cfg()['db']['name'],'test'))throw new RuntimeException('Disposable integration test required');
as_client(1000);$launchAdmin=current_client();
$launch=gc_admin_route('supplier-create/','POST',['name'=>'Launch without document blockers','email'=>'supplier@example.com'],$launchAdmin);$launchId=$launch['issuerId'];
gc_admin_route('supplier-save/','POST',['issuerId'=>$launchId,'company'=>array_replace($newCompany,['name'=>'Launch without document blockers'])],$launchAdmin);
$probeLogin='launchpoint';gc_admin_route('merchant-settings/','POST',['scopeId'=>$launchId,'siteId'=>gc_uuid(),'siteLogin'=>'launchpoint','identityVerified'=>true],$launchAdmin);
$confirmed=gc_admin_route('issuer-approve/','POST',['issuerId'=>$launchId],$launchAdmin);gassert($confirmed['status']==='active'&&count($confirmed['warnings'])===1,'new supplier programme confirms without preparing, downloading or uploading an agreement');
gassert(!one('SELECT id FROM pg_documents WHERE issuer_id=?',[$launchId]),'launch does not fabricate a signed document');
gc_setting_save('sales_enabled',false,$launchAdmin);$sales=gc_admin_route('sales-toggle/','POST',['enabled'=>true],$launchAdmin);gassert($sales['enabled'],'sales open without a password step-up or circular full-flow attestation');
gassert(in_array($launchId,array_column(gc_catalog(),'id'),true),'confirmed supplier appears in the customer catalogue');
as_client(1014);$launchOffer=gc_offer();$launchedOrder=giftcards_route('orders/','POST',['issuerId'=>$launchId,'cards'=>[100000],'accepted'=>true,'offerVersion'=>$launchOffer['version'],'key'=>gc_uuid()]);$launchedCard=$launchedOrder['cards'][0]['id'];
$_GET=['orderId'=>$launchedOrder['id']];$launchInvoice=giftcards_route('orders/invoice/','GET');$_GET=[];gassert($launchInvoice['orderId']===$launchedOrder['id']&&$launchInvoice['dueMinor']===100000,'customer receives the invoice for the new programme');
greject(fn()=>giftcards_route('cards/issue/','POST',['cardId'=>$launchedCard]),'unpaid cards cannot accidentally issue during simplified launch');
as_client(1000);gc_admin_route('bank-confirm/','POST',['orderId'=>$launchedOrder['id'],'amount'=>'1000.00','bankReference'=>'LAUNCH-E2E-IN'],$launchAdmin);
as_client(1014);giftcards_route('cards/issue/','POST',['cardId'=>$launchedCard]);for($n=0;$n<30&&gc_card($launchedCard)['issuance_status']!=='issued';$n++)if(!gc_run_one())break;
$_GET=['cardId'=>$launchedCard];$revealed=giftcards_route('cards/code/','GET');$_GET=[];gassert(strlen($revealed['code'])===20,'paid customer requests and receives the real worker-issued fixture code');
as_client(1027);$launchedActivation=giftcards_route('merchant/activate/','POST',['issuerId'=>$launchId,'code'=>$revealed['code'],'buyer'=>'Launch Buyer','contract'=>'LAUNCH-PURCHASE-1','basis'=>'advance','purchaseSubject'=>'Test goods collected at the supplier showroom','performance'=>'future']);
for($n=0;$n<30&&gc_card($launchedCard)['redemption_status']!=='redeemed';$n++)if(!gc_run_one())break;gassert(gc_card($launchedCard)['redemption_status']==='redeemed','supplier redeems the new programme card without a document gate');
$payout=giftcards_route('merchant/settlements/request/','POST',['issuerId'=>$launchId,'activationIds'=>[$launchedActivation['activationId']],'note'=>'Launch fixture settlement','key'=>gc_uuid()]);
as_client(1000);gc_admin_route('settlement-request-review/','POST',['settlementId'=>$payout['id'],'status'=>'prepared','note'=>'Matched launch fixture register'],$launchAdmin);
$payout=one('SELECT * FROM pg_settlements WHERE id=?',[$payout['id']]);$launchNet=VdarClient::decimal((int)$payout['net_minor']);$launchProof=test_evidence('settlement',$payout['id']);
gc_admin_route('settlement-event/','POST',['settlementId'=>$payout['id'],'kind'=>'sent','amount'=>$launchNet,'reference'=>'LAUNCH-E2E-SENT','note'=>'Disposable bank dispatch confirmation','evidenceVerified'=>true,'evidenceId'=>$launchProof,'key'=>gc_uuid()],$launchAdmin);
gc_admin_route('settlement-bank-credit/','POST',['settlementId'=>$payout['id'],'amount'=>$launchNet,'bankReference'=>'LAUNCH-E2E-OUT','creditedVerified'=>true,'evidenceId'=>$launchProof],$launchAdmin);
as_client(1027);giftcards_route('merchant/settlements/ack/','POST',['settlementId'=>$payout['id'],'amount'=>VdarClient::decimal((int)$payout['net_minor']),'bankReference'=>'LAUNCH-E2E-RECEIVED']);
gassert(one('SELECT status FROM pg_settlements WHERE id=?',[$payout['id']])['status']==='received','supplier confirms receipt at the end of the complete launch-to-settlement flow');
gassert(one('SELECT contract_status FROM pg_issuers WHERE id=?',[$launchId])['contract_status']==='draft','the entire simplified flow preserves the truthful unsigned contract state');
as_client(1000);$_GET=[];
