<?php
declare(strict_types=1);

function gc_default_nominals():array {return [10000000,20000000,30000000,50000000,100000000,500000000];}
function gc_allowed_nominals():array {return [100000,200000,300000,500000,1000000,2000000,2500000,5000000,10000000,20000000,30000000,50000000,100000000,200000000,300000000,500000000];}
function gc_categories():array {return ['vehicles'=>'Автомобили и запчасти','food'=>'Продукты питания','produce'=>'Овощи и фрукты','clothing'=>'Одежда, обувь и вещи','consumer_goods'=>'Товары народного потребления','electronics'=>'Техника и оборудование','software'=>'Софт: установка и настройка на месте','other_services'=>'Другие услуги'];}
function gc_category_values(array $record):array {
 $selected=$record['categories']??(isset($record['category'])?[$record['category']]:[]);
 if(!is_array($selected)||!count($selected))throw new ApiError('Выберите хотя бы одну категорию товаров или услуг');
 $allowed=gc_categories();foreach($selected as $code)if(!is_string($code)||!isset($allowed[$code]))throw new ApiError('Проверьте выбранные категории товаров или услуг');
 return array_values(array_intersect(array_keys($allowed),$selected));
}
function gc_country_name(string $code):string {
 static $countries=null;if($countries===null)$countries=array_column(json_decode(file_get_contents(__DIR__.'/giftcards-countries.json'),true,512,JSON_THROW_ON_ERROR),'name','code');
 if(!isset($countries[$code]))throw new ApiError('Выберите страну за пределами Российской Федерации',409);return $countries[$code];
}
function gc_program_country(array $p):string {
 $code=$p['country_code']??null;
 if($code===null&&in_array($p['use_country']??'', ['KG','Кыргызстан','Кыргызская Республика'],true))$code='KG';
 if(!is_string($code))throw new ApiError('Укажите страну фактического исполнения за пределами РФ',409);
 gc_country_name($code);return $code;
}
function gc_company_days(array $co):int {
 if(!preg_match('/^(\d+)\s*(?:день|дня|дней|дн\.?|days?)?(?:\s+со\s+дня\s+выпуска(?:\s+кода)?)?\.?$/iu',trim($co['validity']??''),$m)||(int)$m[1]<1||(int)$m[1]>365)throw new ApiError('Укажите срок действия карты числом дней от 1 до 365, например «180 дней»');
 return (int)$m[1];
}
function gc_company_program(array $co,array $previous=[]):array {
 $days=gc_company_days($co);$country=gc_country_name($co['fulfillmentCountry']);
 $categories=gc_category_values($co);
 $p=array_replace($previous,['category'=>$categories[0],'categories'=>$categories,'goods'=>$co['goods'],'rules'=>$co['rules'],'nominals'=>$co['nominals'],'issue_deadline'=>$co['issueTime'],'request_deadline'=>$co['requestTime'],'days'=>$days,'expires_seconds'=>$days*86400,'validity'=>$co['validity'],'redemption'=>$co['redemption'],'refund'=>$previous['refund']??'По действующей оферте и обязательным требованиям закона','country_code'=>$co['fulfillmentCountry'],'use_country'=>$country,'fulfillment_address'=>$co['fulfillmentAddress'],'delivery_to_rf'=>false,'digital_services'=>false,'partial_redemption'=>false,'territoryConfirmed'=>true,'territorial_terms'=>'Материальные товары передаются и услуги оказываются за пределами РФ по согласованному фактическому адресу. Получение товаров и услуг в России и электронные услуги не предусмотрены.']);
 gc_program_required($p);return $p;
}

/** Read-only diagnostics: signing a company profile does not save a card programme. */
function gc_program_issues(mixed $program):array {
 $p=is_array($program)?$program:[];$issues=[];
 $nominals=$p['nominals']??null;
 if(!is_array($nominals)||!count($nominals))$issues[]='Выберите хотя бы один доступный номинал карты';
 elseif(array_filter($nominals,fn($n)=>!is_int($n)||!in_array($n,gc_allowed_nominals(),true)))$issues[]='Проверьте допустимые номиналы карт';
 $rules=$p['rules']??null;
 if(!is_string($rules)||!filter_var($rules,FILTER_VALIDATE_URL)||parse_url($rules,PHP_URL_SCHEME)!=='https')$issues[]='Укажите HTTPS-ссылку на оферту по картам';
 $seconds=$p['expires_seconds']??null;
 if(!is_numeric($seconds)||(int)$seconds<1||(int)$seconds>31536000)$issues[]='Укажите допустимый срок действия карты, не более 365 дней';
 foreach(['issue_deadline'=>'Укажите срок выпуска кода по запросу','request_deadline'=>'Укажите срок запроса кода после оплаты','use_country'=>'Укажите страну исполнения','redemption'=>'Заполните порядок погашения карты','refund'=>'Заполните порядок обращений и возврата'] as $key=>$message)if(!is_string($p[$key]??null)||trim($p[$key])==='')$issues[]=$message;
 try{gc_program_country($p);}catch(ApiError){$issues[]='Выберите страну фактического исполнения за пределами РФ';}
 if(!is_string($p['fulfillment_address']??null)||trim($p['fulfillment_address'])===''||mb_strlen($p['fulfillment_address'])>500)$issues[]='Укажите фактический адрес исполнения за пределами РФ';
 if(($p['delivery_to_rf']??null)!==false)$issues[]='Подтвердите отсутствие выдачи товара и оказания услуг в России';
 if(($p['digital_services']??null)!==false)$issues[]='Подтвердите исключение электронных услуг из программы';
 if(($p['partial_redemption']??false)!==false)$issues[]='Укажите однократное полное погашение карты';
 return $issues;
}
function gc_program_required(mixed $program):void {
 $issues=gc_program_issues($program);
 if($issues)throw new ApiError('Условия карт не сохранены или заполнены не полностью: '.implode('; ',$issues).'. Откройте «Условия карт» в карточке поставщика и сохраните программу.',409);
}

function gc_contract_template(array $issuer):string {
 $co=json_decode($issuer['company_json'],true);return __DIR__.'/'.(isset($co['country'])?'giftcards-template-international.docx':'giftcards-template.docx');
}
function gc_contract_version(array $issuer,?array $signature):string {
 return hash('sha256',file_get_contents(gc_contract_template($issuer)).$issuer['company_json'].json_encode($signature).$issuer['contract_number']);
}
function gc_issuer_sales_required(array $issuer):void {
 $credentials=gc_credentials($issuer['id']);if($credentials['mode']!=='shared'||($issuer['recipient_login']??'')!==$credentials['recipient_login']||($issuer['vdar_site_id']??'')!==$credentials['site_id'])throw new ApiError('Для новых заказов настройте торговую точку поставщика под общим мерчантом Pina Market',409);
 gc_program_location(json_decode($issuer['program_json'],true,512,JSON_THROW_ON_ERROR));
 if($issuer['contract_status']!=='approved'||!gc_approved_contract($issuer))throw new ApiError('Для новых продаж необходим принятый подписанный договор с текущими реквизитами и условиями карт',409);
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
