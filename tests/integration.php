<?php
declare(strict_types=1);
// Dedicated disposable MySQL database only. The test never touches an existing hosting config.
if(!getenv('PINA_CONFIG')||!str_ends_with(getenv('DB_NAME')?:'','_test'))throw new RuntimeException('Use a dedicated *_test DB and PINA_CONFIG.');
define('PINA_LIBRARY_ONLY',true);
$GLOBALS['outbox']=[];
function send_message(string $to,string $subject,string $body):void{$GLOBALS['outbox'][]=compact('to','subject','body');}
require dirname(__DIR__).'/app/api.php';
$schema=file_get_contents(dirname(__DIR__).'/database/schema.sql');foreach(explode(';',$schema) as $sql)if(trim($sql)!=='')db()->exec($sql);
$schemaTables=['pm_consents','pm_password_resets','pm_rate_buckets','pm_audit','pm_messages','pm_ledger','pm_operations','pm_services','pm_clients','pm_counters','pm_meta'];
foreach($schemaTables as $t)query('DELETE FROM '.$t);query("INSERT INTO pm_counters(name,value) VALUES('client',0),('operation',0)");
query("INSERT INTO pm_meta(name,value) VALUES('site_settings',?)",[json_encode(['cashEnabled'=>true,'cashAddress'=>'Test desk'])]);
session_init();
$_SESSION=['csrf'=>str_repeat('a',64)];$_SERVER['HTTP_X_CSRFTOKEN']=$_SESSION['csrf'];$_SERVER['REMOTE_ADDR']='127.0.0.1';
$n=0;
function expect(bool $ok,string $label):void{global $n;$n++;if(!$ok)throw new RuntimeException('FAILED: '.$label);}
function as_client(int $id):void{$_SESSION['client']=$id;$_SESSION['auth_version']=1;}
function fail_api(callable $f,int $code,string $label):void{try{$f();throw new RuntimeException('Expected error: '.$label);}catch(ApiError $e){expect($e->status===$code,$label);}}
// The routes are called directly; POST JSON is supplied through a test-only override below.
function payload(array $d):void{$GLOBALS['test_input']=$d;}
function post(string $route,array $data):mixed{if($route==='orders/')$data+=['accepted'=>true,'legalVersion'=>legal_data()['version']];if($route==='register/')$data+=['accepted'=>true,'version'=>legal_data()['version']];payload($data);$r=api_route($route,'POST');if($route==='login/'&&isset($r['csrf']))$_SERVER['HTTP_X_CSRFTOKEN']=$r['csrf'];return $r;}
$r=post('register/',['email'=>'client1@example.com']);expect(count($GLOBALS['outbox'])===1,'registration sends email');$mail=$GLOBALS['outbox'][0]['body'];expect(str_contains($mail,'/login/'),'mail includes login page');preg_match('/Временный пароль: ([a-f0-9]+)/u',$mail,$m);expect(password_verify($m[1],one('SELECT password_hash FROM pm_clients WHERE id=1000')['password_hash']),'only hash stored');
$access=post('login/',['email'=>'client1@example.com','password'=>$m[1]]);expect($access['prompt']===true,'first login offers password setup');expect(one('SELECT temporary_until FROM pm_clients WHERE id=1000')['temporary_until']===null,'temporary password activated on first login');
post('register/',['email'=>'client2@example.com']);post('register/',['email'=>'client3@example.com']);expect(array_column(rows('SELECT id FROM pm_clients ORDER BY id'),'id')==[1000,1001,1014],'ID sequence');
post('register/',['email'=>'client1@example.com']);expect(count($GLOBALS['outbox'])===3,'existing email does not reset credentials');
query('UPDATE pm_clients SET is_staff=1,password_prompt=0 WHERE id=1014');
query("INSERT INTO pm_services(code,name,description,instructions,conditions_text,product,category,status,fee_bps,min_minor,max_minor,regions_json,updated_at) VALUES('steam','Steam','','','','Wallet','Игры','available',500,100,100000000,'[\"RU\"]',?)",[now()]);
as_client(1000);$key='9e6ae0ca-cc78-4d5e-9a67-a9490ea81a1b';$deposit=post('deposits/',['amount'=>'5000','key'=>$key]);expect($deposit['id']===1000,'operation starts at 1000');$same=post('deposits/',['amount'=>'5000','key'=>$key]);expect($same['id']===$deposit['id'],'deposit idempotency');expect((int)one('SELECT balance_minor FROM pm_clients WHERE id=1000')['balance_minor']===0,'request is not money');
fail_api(fn()=>post('admin/operations/1000/',['action'=>'confirm','amount'=>'5000','bankref'=>'BANK-1']),403,'client cannot confirm deposit');
as_client(1014);fail_api(fn()=>post('admin/operations/1000/',['action'=>'confirm','amount'=>'4000','bankref'=>'BANK-1']),400,'mismatched receipt rejected');post('admin/operations/1000/',['action'=>'confirm','amount'=>'5000','bankref'=>'BANK-1']);fail_api(fn()=>post('admin/operations/1000/',['action'=>'confirm','amount'=>'5000','bankref'=>'BANK-1']),409,'repeat confirmation rejected');
as_client(1000);expect((int)current_client()['balance_minor']===500000,'receipt credited');$q=['amount'=>'1000','service'=>'steam','recipient'=>'test-user','region'=>'RU','key'=>'9e6ae0ca-cc78-4d5e-9a67-a9490ea81a1c','total'=>105000,'version'=>'1'];$order=post('orders/',$q);expect($order['id']===1001,'operation numbering continuous');expect((int)current_client()['reserved_minor']===105000,'reserve applied');$same=post('orders/',$q);expect($same['id']===1001&&(int)current_client()['reserved_minor']===105000,'order idempotency');
as_client(1001);$_GET=[];$list=api_route('operations/','GET');expect($list['count']===0,'client isolation');$_GET=['scope'=>'admin'];fail_api(fn()=>api_route('operations/','GET'),403,'client cannot list all operations');$_GET=[];
as_client(1014);post('admin/operations/1001/',['action'=>'take']);post('admin/operations/1001/',['action'=>'clarify','text'=>'Уточните регион']);expect(one('SELECT status FROM pm_operations WHERE number=1001')['status']==='needs_info','clarification status');
as_client(1000);post('orders/1001/reply/',['text'=>'Регион RU подтверждаю']);expect(one('SELECT status FROM pm_operations WHERE number=1001')['status']==='new','client reply reopens queue');
as_client(1014);post('admin/operations/1001/',['action'=>'take']);post('admin/operations/1001/',['action'=>'complete','text'=>'Зачисление подтверждено']);expect((int)one('SELECT balance_minor FROM pm_clients WHERE id=1000')['balance_minor']===395000,'final debit');expect((int)one('SELECT reserved_minor FROM pm_clients WHERE id=1000')['reserved_minor']===0,'reserve released on completion');fail_api(fn()=>post('admin/operations/1001/',['action'=>'complete','text'=>'Again']),409,'cannot debit twice');
as_client(1000);$q['key']='9e6ae0ca-cc78-4d5e-9a67-a9490ea81a1d';$o2=post('orders/',$q);as_client(1014);post('admin/operations/'.$o2['id'].'/',['action'=>'cancel','text'=>'Отмена до исполнения']);expect((int)one('SELECT balance_minor FROM pm_clients WHERE id=1000')['balance_minor']===395000&&(int)one('SELECT reserved_minor FROM pm_clients WHERE id=1000')['reserved_minor']===0,'cancel only releases reserve');
post('admin/clients/1000/notify/',['text'=>'Персональное уведомление']);expect((int)one('SELECT COUNT(*) n FROM pm_messages WHERE client_id=1000')['n']>=4,'messages persist');
$_GET=['scope'=>'admin','kind'=>'order','status'=>'completed'];$list=api_route('operations/','GET');expect($list['count']===1&&$list['items'][0]['id']===1001,'registry status filter');$_GET=['scope'=>'admin','client'=>'client2@example.com'];expect(api_route('operations/','GET')['count']===0,'registry client filter');$_GET=[];
post('admin/services/steam/',['status'=>'hidden','version'=>'1','desc'=>'New','instructions'=>'Instruction','conditions'=>'Conditions','product'=>'Wallet','regions'=>['RU'],'feeBps'=>600,'min'=>'1','max'=>'10000']);as_client(1000);expect(count(api_route('catalog/','GET')['services'])===0,'hidden service excluded');$q['key']='9e6ae0ca-cc78-4d5e-9a67-a9490ea81a1e';fail_api(fn()=>post('orders/',$q),400,'hidden service cannot be ordered');
post('password/',['skip'=>true]);expect((int)current_client()['password_prompt']===0,'password prompt can be skipped');
$_SERVER['HTTP_X_CSRFTOKEN']='bad';fail_api(fn()=>post('deposits/',['amount'=>'1','key'=>'9e6ae0ca-cc78-4d5e-9a67-a9490ea81a1f']),403,'CSRF enforced');
$_SERVER['HTTP_X_CSRFTOKEN']=$_SESSION['csrf'];as_client(1014);$refund=post('admin/clients/1000/refund/',['amount'=>'100','text'=>'Request received','recipient'=>'Client bank account','key'=>'9e6ae0ca-cc78-4d5e-9a67-a9490ea81a2a']);expect((int)one('SELECT reserved_minor FROM pm_clients WHERE id=1000')['reserved_minor']===10000,'refund reserved before bank payout');post('admin/operations/'.$refund['id'].'/',['action'=>'complete','text'=>'Bank payout completed','bankref'=>'BANK-REFUND-1']);expect((int)one('SELECT balance_minor FROM pm_clients WHERE id=1000')['balance_minor']===385000,'refund debits once');expect((int)one('SELECT COUNT(*) n FROM pm_consents')['n']===3,'accepted legal versions stored');
echo "PASS: $n MySQL integration checks\n";
