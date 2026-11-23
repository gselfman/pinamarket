<?php
// An artificial one-pixel image tests layout; it is not a legal signature.
define('PINA_LIBRARY_ONLY',true);
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/giftcards.php';require dirname(__DIR__).'/app/giftcards-documents.php';
function gift_check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);}
$international=['country'=>'TR','fulfillmentCountry'=>'TR','fulfillmentAddress'=>'Test showroom','name'=>'Тестовая компания','reg'=>'TEST','tax'=>'TEST','address'=>'Test office','role'=>'Директор','director'=>'Тестовый Подписант','authority'=>'Устава','email'=>'supplier@example.invalid','phone'=>'+90 0000000000','bank'=>'Test bank','account'=>'TEST-ACCOUNT','swift'=>'TEST','goods'=>'Test goods','rules'=>'https://example.invalid/rules','issueTime'=>'1 day','requestTime'=>'365 days','validity'=>'180 дней','redemption'=>'Full nominal','exchangeChannel'=>'Private cabinet','nominals'=>[1000000]];
$dir=getenv('PINA_PDF_TEST_DIR')?:sys_get_temp_dir();$image=$dir.'/pina-layout-fixture.png';
file_put_contents($image,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7X8AAAAASUVORK5CYII='));
$pdf=$dir.'/Pina-contract-layout-test.pdf';$sig=['path'=>$image,'hash'=>hash_file('sha256',$image),'width'=>1,'height'=>1];
gc_generate_contract_pdf(dirname(__DIR__).'/app/giftcards-template-international.docx',$pdf,['contract_number'=>'PM-PDF-LAYOUT-TEST'],$international,$sig);
$bytes=file_get_contents($pdf);gift_check(str_starts_with($bytes,'%PDF-'),'contract output is PDF');gift_check(str_contains($bytes,'/Subtype /Image'),'approved image is embedded in PDF');
preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s',$bytes,$streams);$drawings=[];
foreach($streams[1] as $stream){$decoded=@gzuncompress($stream);if($decoded===false)continue;preg_match_all('/q ([\d.]+) 0 0 ([\d.]+) [-\d.]+ [-\d.]+ cm \/I\d+ Do Q/',$decoded,$matches,PREG_SET_ORDER);foreach($matches as $match)$drawings[]=$match;}
gift_check(count($drawings)===4,'signature is drawn on main agreement and all three annexes');foreach($drawings as $draw)gift_check(abs((float)$draw[1]-(float)$draw[2])<0.02,'square image is never stretched');
$unsigned=$dir.'/Pina-contract-unsigned-test.pdf';gc_generate_contract_pdf(dirname(__DIR__).'/app/giftcards-template-international.docx',$unsigned,['contract_number'=>'PM-PDF-UNSIGNED-TEST'],$international,null);gift_check(!str_contains(file_get_contents($unsigned),'/Subtype /Image'),'unsigned PDF does not invent a signature');
unlink($image);echo "PASS: UTF-8 PDF generation, unsigned output, four signature placements and original image proportions\n";
