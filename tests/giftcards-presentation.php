<?php
// Branding is presentation only; changing it must not stop an already trading supplier.
if(!defined('PINA_LIBRARY_ONLY')||!str_contains(cfg()['db']['name'],'test'))throw new RuntimeException('Disposable integration database required');
as_client(1000);$brandAdmin=current_client();
$brandBefore=one('SELECT * FROM pg_issuers WHERE id=?',[$launchId]);$brandConfirmation=one('SELECT * FROM pg_program_confirmations WHERE issuer_id=?',[$launchId]);
$oldOrderSnapshot=one('SELECT snapshot_json FROM pg_orders WHERE id=?',[$launchedOrder['id']])['snapshot_json'];
$brandResult=gc_admin_route('supplier-brand/','POST',['issuerId'=>$launchId,'brandName'=>'  Юэкспо  '],$brandAdmin);
gassert($brandResult['brandName']==='Юэкспо','administrator saves a short Unicode brand on a supplier with existing orders');
$brandAfter=one('SELECT * FROM pg_issuers WHERE id=?',[$launchId]);
gassert($brandAfter===$brandBefore&&one('SELECT * FROM pg_program_confirmations WHERE issuer_id=?',[$launchId])===$brandConfirmation,'brand edit preserves legal company data, programme confirmation, contract and active sales');
gassert(one('SELECT snapshot_json FROM pg_orders WHERE id=?',[$launchedOrder['id']])['snapshot_json']===$oldOrderSnapshot,'brand edit never rewrites historical order terms');
$publicBrand=gc_public_issuer($brandAfter);gassert($publicBrand['name']==='Юэкспо'&&$publicBrand['company']['name']===$brandBefore['name'],'public brand and full legal issuer name remain separate');
$countryFixture=$brandAfter;$countryProgram=json_decode($countryFixture['program_json'],true);$countryProgram['country_code']='TR';$countryProgram['use_country']='Кыргызстан';$countryFixture['program_json']=json_encode($countryProgram);gassert(gc_public_issuer($countryFixture)['program']['use_country']==='Турция','public country name comes from the actual programme country code rather than a stale caption');
gassert(in_array('Юэкспо',array_column(gc_catalog(),'name'),true),'brand edit keeps the confirmed supplier available in the public catalogue');
as_client(1014);greject(fn()=>giftcards_route('merchant/brand/','POST',['issuerId'=>$launchId,'brandName'=>'Unrelated buyer']),'unrelated buyer cannot rename the supplier');
$brandOwner=(int)one("SELECT client_id FROM pg_issuer_members WHERE issuer_id=? AND role='owner'",[$launchId])['client_id'];as_client($brandOwner);
$brandResult=giftcards_route('merchant/brand/','POST',['issuerId'=>$launchId,'brandName'=>'UEXPO']);gassert($brandResult['displayName']==='UEXPO','supplier owner edits own brand without revising approved company details');
$brandState=giftcards_route('state/','GET');$brandMember=array_values(array_filter($brandState['issuers'],fn($i)=>$i['id']===$launchId))[0];gassert($brandMember['brandName']==='UEXPO','supplier settings expose saved brand separately from legal company details');
greject(fn()=>giftcards_route('merchant/brand/','POST',['issuerId'=>$launchId,'brandName'=>str_repeat('Я',81)]),'overlong brand rejected without altering an active programme');
giftcards_route('merchant/brand/','POST',['issuerId'=>$launchId,'brandName'=>'']);gassert(gc_public_issuer($brandAfter)['name']===$brandBefore['name'],'empty brand falls back to full legal name for older and unconfigured suppliers');
giftcards_route('merchant/brand/','POST',['issuerId'=>$launchId,'brandName'=>'UEXPO']);gc_issuer_sales_required($brandAfter);
as_client(1000);$_GET=[];
