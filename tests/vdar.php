<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/vdar.php';
$calls=[];$mock=function($method,$path,$body)use(&$calls){$calls[]=[$method,$path,$body];if($path==='/codes/generate')return ['id'=>'11111111-1111-4111-8111-111111111111','code'=>'ABCDEFGHIJKLMNOPQRST','amount'=>'10000.00','currency_code'=>'RUB','recipient_login'=>'supplier.point','payment_id'=>'PINA-test'];return ['success'=>true,'operation_id'=>'22222222-2222-4222-8222-222222222222','currency_code'=>'RUB','activated_amount'=>'10000.00'];};
$c=new VdarClient('test-key','11111111-1111-4111-8111-111111111111','https://not-used.invalid',$mock);
function ok(bool $value,string $label):void{if(!$value)throw new RuntimeException($label);}
$r=$c->generate(1000000,'Pina card','supplier.point','PINA-test',15552000);ok($r['code']==='ABCDEFGHIJKLMNOPQRST','code');ok($calls[0][2]['amount']==='10000.00','decimal amount');ok($calls[0][2]['recipient_login']==='supplier.point','recipient is restricted');ok(!isset($calls[0][2]['recipient_phone']),'no buyer phone restriction');$c->activate($r['code']);ok($calls[1][2]['site_id']==='11111111-1111-4111-8111-111111111111','activation on configured site');ok(count($calls)===2,'one call per mutation');ok(VdarClient::minor('0.10')===10,'minor units');ok(VdarClient::minor('10000.0000')===1000000,'provider trailing zeroes');
$attempts=0;$broken=new VdarClient('test-key','11111111-1111-4111-8111-111111111111','https://not-used.invalid',function()use(&$attempts){$attempts++;throw new VdarUncertain();});try{$broken->generate(1000000,'Card','supplier.point','PINA-test',15552000);throw new RuntimeException('uncertain not raised');}catch(VdarUncertain){}ok($attempts===1,'no automatic retry');
echo "PASS: V-DAR server adapter request contract and no-retry behaviour\n";
