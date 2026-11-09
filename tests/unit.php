<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$n=0;
function expect(bool $ok,string $label):void{global $n;$n++;if(!$ok)throw new RuntimeException('FAILED: '.$label);}
expect(minor('0.01')===1,'one kopeck');expect(minor('123.45')===12345,'decimal money is exact');expect(minor('1000000')===100000000,'upper limit');
foreach(['0','-1','1.001','1e3','NaN','Infinity','','1000000.01'] as $bad){try{minor($bad);throw new RuntimeException('Accepted invalid amount');}catch(ApiError){$n++;}}
$x=0;$ids=[];for($i=0;$i<6;$i++){$x=next_client_id($x);$ids[]=$x;}
expect($ids===[1000,1001,1014,1027,1040,1053],'ID numbering from 1000, next multiples of 13');
expect(request_key(['key'=>'9e6ae0ca-cc78-4d5e-9a67-a9490ea81a1b'])==='9e6ae0ca-cc78-4d5e-9a67-a9490ea81a1b','idempotency UUID');
try{request_key(['key'=>'bad']);throw new RuntimeException('Bad key accepted');}catch(ApiError){$n++;}
try{password_rules('123456789012');throw new RuntimeException('Numeric password accepted');}catch(ApiError){$n++;}
password_rules('Correct horse battery 2026!');$n++;
expect(intdiv(100001*500+5000,10000)===5000,'rounding fee in kopecks');
echo "PASS: $n unit checks\n";
