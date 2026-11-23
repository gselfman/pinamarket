<?php
// Disposable workflow fixtures, never real signatures or legally signed documents.
if(!defined('PINA_LIBRARY_ONLY')||!isset($newCompany)||!str_contains(cfg()['db']['name'],'test'))throw new RuntimeException('Disposable integration test required');
as_client(1000);$reviewAdmin=current_client();
$made=gc_admin_route('supplier-create/','POST',['name'=>'Manual signing fixture','email'=>'manual-fixture@example.invalid'],$reviewAdmin);$reviewId=$made['issuerId'];
$reviewCompany=array_replace($newCompany,['name'=>'Manual signing fixture','email'=>'manual-fixture@example.invalid']);
gc_admin_route('supplier-save/','POST',['issuerId'=>$reviewId,'company'=>$reviewCompany],$reviewAdmin);
$prepared=giftcards_route('documents/prepare/','POST',['issuerId'=>$reviewId]);
gassert($prepared['status']==='draft','unsigned source can be prepared for external signing without a facsimile');
$reviewIssuer=one('SELECT * FROM pg_issuers WHERE id=?',[$reviewId]);$reviewVersion=$reviewIssuer['contract_version'];
$docId=gc_uuid();$docName=$docId.'.pdf';$docPath=gc_storage().'/'.$docName;$pdf="%PDF-1.4\nDisposable manual signing workflow fixture, no real signatures\n%%EOF";file_put_contents($docPath,$pdf);
query("INSERT INTO pg_documents(id,issuer_id,kind,version,storage_name,original_name,mime,size_bytes,sha256,status,uploaded_by,created_at) VALUES(?,?,'signed_contract',?,?,?,'application/pdf',?,?,'review',?,?)",[$docId,$reviewId,$reviewVersion,$docName,'manual-fixture.pdf',strlen($pdf),hash('sha256',$pdf),1000,now()]);
query("UPDATE pg_issuers SET contract_status='review',contract_version=NULL,contract_hash=NULL WHERE id=?",[$reviewId]);
$reprepared=giftcards_route('documents/prepare/','POST',['issuerId'=>$reviewId]);$reviewIssuer=one('SELECT * FROM pg_issuers WHERE id=?',[$reviewId]);
gassert($reprepared['id']===$prepared['id']&&$reviewIssuer['contract_version']===$reviewVersion&&$reviewIssuer['contract_hash']!==null,'reusing a source repairs the contract pointer after the old form reset');
$_GET=['id'=>$docId];$review=gc_admin_route('document-review/','GET',[],$reviewAdmin);$_GET=[];
gassert($review['issues']===[]&&$review['signingMethod']==='manual'&&!$review['platformSigned'],'review explicitly offers external signing for a draft source');
$approve=['documentId'=>$docId,'signingMethod'=>'manual','signaturesVerified'=>true,'finalCopyVerified'=>true];
greject(fn()=>gc_admin_route('document-approve/','POST',array_replace($approve,['signaturesVerified'=>false]),$reviewAdmin),'both signatures must be personally checked');
greject(fn()=>gc_admin_route('document-approve/','POST',array_replace($approve,['finalCopyVerified'=>false]),$reviewAdmin),'a manually signed final copy needs explicit verification of removed draft marks');
$platformRejected=false;try{gc_admin_route('document-approve/','POST',array_replace($approve,['signingMethod'=>'platform']),$reviewAdmin);}catch(ApiError $e){$platformRejected=str_contains($e->getMessage(),'вручную вне сайта');}gassert($platformRejected,'platform-signature failure tells the administrator which signing method to select');
file_put_contents($docPath,'tampered');$_GET=['id'=>$docId];$broken=gc_admin_route('document-review/','GET',[],$reviewAdmin);$_GET=[];gassert(count($broken['issues'])>0,'review detects missing or altered signed files');greject(fn()=>gc_admin_route('document-approve/','POST',$approve,$reviewAdmin),'tampered file cannot be approved');file_put_contents($docPath,$pdf);
$probeLogin='manualpoint';gc_admin_route('merchant-settings/','POST',['scopeId'=>$reviewId,'siteId'=>'77777777-7777-4777-8777-777777777777','siteLogin'=>'manualpoint','identityVerified'=>true],$reviewAdmin);
$reviewIssuer=one('SELECT * FROM pg_issuers WHERE id=?',[$reviewId]);$opening=['issuerId'=>$reviewId,'program'=>json_decode($reviewIssuer['program_json'],true),'checksVerified'=>true];
$confirmation=gc_admin_route('issuer-approve/','POST',$opening,$reviewAdmin);gassert($confirmation['status']==='active'&&count($confirmation['warnings'])===1,'programme opens before document review with a warning');$confirmedIssuer=one('SELECT * FROM pg_issuers WHERE id=?',[$reviewId]);gassert($confirmedIssuer['contract_status']!=='approved','programme confirmation does not falsely mark a contract signed');gc_issuer_sales_required($confirmedIssuer);gassert(gc_program_confirmed($confirmedIssuer),'current administrator confirmation satisfies sales readiness');
$accepted=gc_admin_route('document-approve/','POST',$approve,$reviewAdmin);gc_admin_route('document-approve/','POST',$approve,$reviewAdmin);
gassert($accepted['signingMethod']==='manual'&&(int)one('SELECT COUNT(*) n FROM pg_contract_reviews WHERE document_id=?',[$docId])['n']===1,'external review is recorded once and retries are idempotent');
$savedReview=one('SELECT * FROM pg_contract_reviews WHERE document_id=?',[$docId]);gassert((int)$savedReview['reviewed_by']===1000&&$savedReview['content_hash']===gc_contract_content_hash($reviewIssuer),'review records actual administrator and current company and programme content');
$opened=gc_admin_route('issuer-approve/','POST',$opening,$reviewAdmin);$reviewIssuer=one('SELECT * FROM pg_issuers WHERE id=?',[$reviewId]);gc_issuer_sales_required($reviewIssuer);
gassert($opened['status']==='active'&&gc_contract_readiness($reviewIssuer)['ready'],'manual signed contract opens the programme without uploading a signature image');
gassert(gc_admin_checklist()['signature']&&!gc_admin_checklist()['signatureImage'],'platform readiness recognises manually signed contracts without pretending an image exists');
$resaved=gc_admin_route('supplier-save/','POST',['issuerId'=>$reviewId,'company'=>$reviewCompany],$reviewAdmin);$afterSave=one('SELECT * FROM pg_issuers WHERE id=?',[$reviewId]);gassert($resaved['changed']===false&&$afterSave['contract_status']==='approved'&&$afterSave['contract_version']===$reviewVersion,'saving unchanged details preserves reviewed contract and programme status');
$again=giftcards_route('documents/prepare/','POST',['issuerId'=>$reviewId]);gassert($again['id']===$prepared['id']&&one('SELECT contract_status FROM pg_issuers WHERE id=?',[$reviewId])['contract_status']==='approved','downloading the same source does not revoke an approved contract');
$changed=$reviewIssuer;$p=json_decode($changed['program_json'],true);$p['request_deadline']='Changed contractual period';$changed['program_json']=json_encode($p);gassert(gc_approved_contract($changed)===null,'changed programme cannot reuse a manual review');greject(fn()=>gc_issuer_sales_required($changed),'changed programme invalidates the administrator confirmation and blocks new sales');
$changed=$reviewIssuer;$changed['company_json']=str_replace('Manual signing fixture','Changed fixture',$changed['company_json']);gassert(gc_approved_contract($changed)===null,'changed company cannot reuse a manual review');
// Installing a facsimile later must not invalidate a wet-signed contract.
$signatureBefore=rows("SELECT name,value_json FROM pg_runtime_settings WHERE name IN ('agent_signature_path','agent_signature_sha256','agent_signature_approved')");
$imagePath=gc_storage().'/fixture-signature.png';file_put_contents($imagePath,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7X8AAAAASUVORK5CYII='));
gc_setting_save('agent_signature_path',$imagePath,$reviewAdmin);gc_setting_save('agent_signature_sha256',hash_file('sha256',$imagePath),$reviewAdmin);gc_setting_save('agent_signature_approved',true,$reviewAdmin);
gassert(gc_signature_config()!==null&&gc_approved_contract($reviewIssuer)['id']===$docId,'later facsimile settings do not invalidate a reviewed manual contract');gc_issuer_sales_required($reviewIssuer);
$afterSignature=giftcards_route('documents/prepare/','POST',['issuerId'=>$reviewId]);gassert($afterSignature['id']===$prepared['id']&&one('SELECT contract_status FROM pg_issuers WHERE id=?',[$reviewId])['contract_status']==='approved','requesting the unchanged manual source after a facsimile is added preserves approval');
query("DELETE FROM pg_runtime_settings WHERE name IN ('agent_signature_path','agent_signature_sha256','agent_signature_approved')");foreach($signatureBefore as $setting)query('INSERT INTO pg_runtime_settings(name,value_json,updated_by,updated_at) VALUES(?,?,?,?)',[$setting['name'],$setting['value_json'],1000,now()]);
// Read-only diagnostics are available to operators, but accepting a contract is admin-only.
$operator=one("SELECT * FROM pm_clients WHERE email='support-ops@example.com'");greject(fn()=>gc_admin_route('document-approve/','POST',$approve,$operator),'support cannot approve a contract by calling the endpoint directly');
as_client(1000);$_GET=[];
