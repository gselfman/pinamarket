<?php
declare(strict_types=1);

/** Read-only diagnostics: signing a company profile does not save a card programme. */
function gc_program_issues(mixed $program):array {
 $p=is_array($program)?$program:[];$issues=[];
 $nominals=$p['nominals']??null;
 if(!is_array($nominals)||!count($nominals))$issues[]='Выберите хотя бы один доступный номинал карты';
 elseif(array_filter($nominals,fn($n)=>!is_int($n)||!in_array($n,[1000000,5000000,10000000,50000000,100000000,500000000],true)))$issues[]='Проверьте допустимые номиналы карт';
 $rules=$p['rules']??null;
 if(!is_string($rules)||!filter_var($rules,FILTER_VALIDATE_URL)||parse_url($rules,PHP_URL_SCHEME)!=='https')$issues[]='Укажите HTTPS-ссылку на оферту по картам';
 $seconds=$p['expires_seconds']??null;
 if(!is_numeric($seconds)||(int)$seconds<1||(int)$seconds>31536000)$issues[]='Укажите допустимый срок действия карты, не более 365 дней';
 foreach(['issue_deadline'=>'Укажите срок выпуска кода по запросу','request_deadline'=>'Укажите срок запроса кода после оплаты','use_country'=>'Укажите страну исполнения','redemption'=>'Заполните порядок погашения карты','refund'=>'Заполните порядок обращений и возврата'] as $key=>$message)if(!is_string($p[$key]??null)||trim($p[$key])==='')$issues[]=$message;
 if(!is_string($p['fulfillment_address']??null)||trim($p['fulfillment_address'])===''||mb_strlen($p['fulfillment_address'])>500)$issues[]='Укажите фактический адрес исполнения в Кыргызстане';
 if(($p['delivery_to_rf']??null)!==false)$issues[]='Подтвердите отсутствие выдачи товара и оказания услуг в России';
 if(($p['digital_services']??null)!==false)$issues[]='Подтвердите исключение электронных услуг из программы';
 if(($p['partial_redemption']??false)!==false)$issues[]='Укажите однократное полное погашение карты';
 return $issues;
}
function gc_program_required(mixed $program):void {
 $issues=gc_program_issues($program);
 if($issues)throw new ApiError('Условия карт не сохранены или заполнены не полностью: '.implode('; ',$issues).'. Откройте «Условия карт» в карточке поставщика и сохраните программу.',409);
}

function gc_contract_version(array $issuer,?array $signature):string {
 return hash('sha256',file_get_contents(__DIR__.'/giftcards-template.docx').$issuer['company_json'].json_encode($signature).$issuer['contract_number']);
}
function gc_issuer_sales_required(array $issuer):void {
 gc_program_location(json_decode($issuer['program_json'],true,512,JSON_THROW_ON_ERROR));
 require_once __DIR__.'/giftcards-documents.php';
 $expected=gc_contract_version($issuer,gc_signature_config());
 if($issuer['contract_status']!=='approved'||!hash_equals($expected,(string)$issuer['contract_version']))throw new ApiError('Для новых продаж необходим подписанный договор по актуальному шаблону',409);
 if(!one("SELECT id FROM pg_documents WHERE issuer_id=? AND kind='signed_contract' AND status='approved' AND version=?",[$issuer['id'],$expected]))throw new ApiError('Проверьте подписанный экземпляр текущей редакции',409);
}
function gc_legal_sales_ready():bool {
 $g=gc_settings();
 return !empty($g['legal_approved'])&&!empty($g['payment_instructions'])&&hash_equals(hash_file('sha256',__DIR__.'/giftcards-offer.json'),(string)($g['legal_source_sha256']??''));
}
function gc_archive_offer(array $offer):void {
 query('INSERT IGNORE INTO pg_legal_versions(hash,version,document_json,created_at) VALUES(?,?,?,?)',[$offer['hash'],$offer['version'],json_encode($offer,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),now()]);
}

function gc_purchase_matches(string $activation,array $purchase):bool {
 $p=one('SELECT * FROM pg_activation_purchases WHERE activation_id=?',[$activation]);
 return $p&&$p['subject']===$purchase['subject']&&$p['performance']===$purchase['performance']&&$p['document_reference']===$purchase['document']&&$p['holder_name']===$purchase['holder'];
}
function gc_purchase_record(string $activation,array $card,array $purchase,array $actor):void {
 $snapshot=json_decode($card['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$program=$snapshot['program']??[];gc_program_location($program);
 query('INSERT INTO pg_activation_purchases(activation_id,subject,country,place,performance,document_reference,holder_name,recorded_by,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$activation,$purchase['subject'],$program['use_country'],$program['fulfillment_address'],$purchase['performance'],$purchase['document'],$purchase['holder'],$actor['id'],now()]);
 audit((int)$actor['id'],'gift_purchase_declared',$activation,['performance'=>$purchase['performance']]);
}
