<?php
declare(strict_types=1);

// A wet-signed final copy is evidence in its own right. It must be reviewed,
// but must not depend on a facsimile image being installed on this server.
function gc_contract_content_hash(array $issuer):string {
 return hash('sha256',json_encode([hash_file('sha256',gc_contract_template($issuer)),$issuer['company_json'],$issuer['program_json'],$issuer['contract_number']],JSON_THROW_ON_ERROR));
}
function gc_approved_contract(array $issuer):?array {
 $docs=rows("SELECT d.*,r.signing_method,r.content_hash FROM pg_documents d LEFT JOIN pg_contract_reviews r ON r.document_id=d.id WHERE d.issuer_id=? AND d.kind='signed_contract' AND d.status='approved' AND d.version=? ORDER BY d.created_at DESC,d.id",[$issuer['id'],$issuer['contract_version']??'']);
 foreach($docs as $doc){
  if($doc['content_hash']!==null&&!hash_equals($doc['content_hash'],gc_contract_content_hash($issuer)))continue;
  if($doc['signing_method']==='manual')return $doc;
  require_once __DIR__.'/giftcards-documents.php';
  try{if(hash_equals(gc_contract_version($issuer,gc_signature_config()),$doc['version']))return $doc;}catch(Throwable){}
 }
 return null;
}
function gc_manual_contract_available():bool {
 foreach(rows("SELECT DISTINCT i.* FROM pg_issuers i JOIN pg_documents d ON d.issuer_id=i.id AND d.version=i.contract_version JOIN pg_contract_reviews r ON r.document_id=d.id WHERE d.status='approved' AND d.kind='signed_contract' AND r.signing_method='manual'") as $issuer){$doc=gc_approved_contract($issuer);if($doc&&$doc['signing_method']==='manual')return true;}
 return false;
}
function gc_contract_file_valid(array $doc):bool {
 $path=gc_storage().'/'.$doc['storage_name'];$hash=is_file($path)?hash_file('sha256',$path):false;
 return is_string($hash)&&hash_equals($doc['sha256'],$hash);
}
function gc_contract_readiness(array $issuer):array {
 $approved=gc_approved_contract($issuer);
 if($approved)return ['ready'=>true,'issues'=>[],'documentId'=>$approved['id'],'signingMethod'=>$approved['signing_method']??'platform'];
 $doc=one("SELECT id FROM pg_documents WHERE issuer_id=? AND kind='signed_contract' AND status='review' AND version=? ORDER BY created_at DESC,id LIMIT 1",[$issuer['id'],$issuer['contract_version']??'']);
 if($doc)return ['ready'=>false,'issues'=>['Подписанный экземпляр загружен, но ещё не принят. Откройте его проверку и укажите способ подписания.'],'documentId'=>$doc['id']];
 return ['ready'=>false,'issues'=>[empty($issuer['contract_version'])?'Сначала подготовьте договор по сохранённым реквизитам и условиям карт, затем загрузите окончательный экземпляр с обеими подписями.':'Нет принятого подписанного экземпляра текущей редакции. Подготовьте договор, подпишите его обеими сторонами и загрузите окончательный экземпляр.'],'documentId'=>null];
}
function gc_contract_review_state(array $issuer,array $doc):array {
 require_once __DIR__.'/giftcards-documents.php';
 $source=one("SELECT * FROM pg_documents WHERE issuer_id=? AND kind='generated_contract' AND version=? ORDER BY created_at DESC,id LIMIT 1",[$issuer['id'],$doc['version']]);
 $issues=[];
 if(!in_array($doc['status'],['review','approved'],true))$issues[]='Этот экземпляр отправлен на доработку. Загрузите исправленный подписанный документ.';
 if(empty($issuer['contract_version']))$issues[]='Договор сброшен после сохранения реквизитов или программы. Нажмите «Подготовить договор» в карточке поставщика, затем проверьте актуальность подписанного экземпляра.';
 elseif(!hash_equals((string)$issuer['contract_version'],$doc['version']))$issues[]='Подписанный экземпляр относится к другой редакции. Подготовьте и загрузите договор с текущими реквизитами и условиями карт.';
 if(!$source)$issues[]='Не найдена исходная редакция договора. Сначала подготовьте договор в карточке поставщика и загрузите подписанный экземпляр.';
 elseif(!gc_contract_file_valid($source))$issues[]='Исходный договор не прошёл проверку целостности. Обратитесь к администратору хранилища документов; приём экземпляра остановлен.';
 $current=hash_equals(gc_contract_version($issuer,null),$doc['version']);
 if(!$current){try{$signature=gc_signature_config();$current=$signature!==null&&hash_equals(gc_contract_version($issuer,$signature),$doc['version']);}catch(Throwable){}}
 // Reviewed manual copies stay current when only the site's facsimile changes.
 $review=one('SELECT signing_method,content_hash FROM pg_contract_reviews WHERE document_id=?',[$doc['id']]);
 if($review&&$review['signing_method']==='manual'&&hash_equals($review['content_hash'],gc_contract_content_hash($issuer)))$current=true;
 if(!$current)$issues[]='Изменились реквизиты, шаблон или способ подготовки договора. Подготовьте актуальную редакцию и загрузите соответствующий подписанный экземпляр.';
 if(!gc_contract_file_valid($doc))$issues[]='Файл подписанного экземпляра отсутствует или не прошёл проверку целостности. Загрузите его заново.';
 return ['documentId'=>$doc['id'],'issuerId'=>$issuer['id'],'issues'=>array_values(array_unique($issues)),'sourceStatus'=>$source['status']??null,'platformSigned'=>($source['status']??'')==='agent_signed','signingMethod'=>$review['signing_method']??(($source['status']??'')==='agent_signed'?'platform':'manual'),'status'=>$doc['status']];
}
function gc_approve_contract(array $data,array $actor):array {
 return transact(function()use($data,$actor){
  $id=txt($data,'documentId',36);$doc=one("SELECT * FROM pg_documents WHERE id=? AND kind='signed_contract'",[$id]);if(!$doc)throw new ApiError('Документ не найден',404);
  $issuer=one('SELECT * FROM pg_issuers WHERE id=? FOR UPDATE',[$doc['issuer_id']]);$doc=one('SELECT * FROM pg_documents WHERE id=? FOR UPDATE',[$id]);
  if(($data['signaturesVerified']??false)!==true)throw new ApiError('Подтвердите проверку обеих подписей, полномочий подписантов и текста договора');
  $method=txt($data,'signingMethod',20,false)?:'platform';if(!in_array($method,['platform','manual'],true))throw new ApiError('Выберите способ подписания договора');
  $state=gc_contract_review_state($issuer,$doc);if($state['issues'])throw new ApiError(implode(' ',$state['issues']),409);
  if($method==='platform'&&!$state['platformSigned'])throw new ApiError('В исходном договоре нет подписи Pina, добавленной сайтом. Если обе стороны подписали документ вне сайта, выберите «Подписан вручную вне сайта».',409);
  if($method==='manual'&&($data['finalCopyVerified']??false)!==true)throw new ApiError('Подтвердите, что загружен окончательный экземпляр без пометок «ПРОЕКТ», с обеими подписями и актуальными приложениями');
  $hash=gc_contract_content_hash($issuer);$old=one('SELECT * FROM pg_contract_reviews WHERE document_id=?',[$id]);
  if($old){if($old['signing_method']!==$method||!hash_equals($old['content_hash'],$hash))throw new ApiError('Принятый экземпляр нельзя привязать к другому способу подписания или условиям. Загрузите новую редакцию.',409);return ['status'=>'approved','signingMethod'=>$method];}
  query('INSERT INTO pg_contract_reviews(document_id,signing_method,content_hash,reviewed_by,reviewed_at) VALUES(?,?,?,?,?)',[$id,$method,$hash,$actor['id'],now()]);
  query("UPDATE pg_documents SET status='approved' WHERE id=?",[$id]);
  audit((int)$actor['id'],'gift_document_approved',$id,['sha256'=>$doc['sha256'],'signingMethod'=>$method,'contentHash'=>$hash]);return ['status'=>'approved','signingMethod'=>$method];
 });
}
