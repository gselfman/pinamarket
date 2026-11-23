<?php
// Disposable database only; never calls V-DAR or a bank.
require_once dirname(__DIR__).'/app/giftcards-bank-import.php';
$csv=tempnam(sys_get_temp_dir(),'pina-bank-');
try{
 file_put_contents($csv,"reference;amount;bankDate;orderId;comment\nOPS-CSV-1;125.50;".gmdate('Y-m-d').";;Actual receipt fixture\n");
 $entries=gc_bank_csv($csv);gassert(count($entries)===1&&$entries[0]['orderId']==='','bank parser keeps unmatched receipt unassigned');
 as_client(1000);$r=gc_bank_import($entries,current_client());gassert($r['added']===1,'bank statement imports genuine receipt records');
 $r=gc_bank_import($entries,current_client());gassert($r['duplicates']===1,'bank statement replay cannot double credit');
 $cards=(int)one('SELECT COUNT(*) n FROM pg_cards')['n'];
 $mixed=[$entries[0],array_replace($entries[0],['reference'=>'OPS-CSV-ROLLBACK']),array_replace($entries[0],['amount'=>'999.00'])];
 greject(fn()=>gc_bank_import($mixed,current_client()),'bank statement conflict rolls back all rows');
 gassert(!one("SELECT id FROM pg_bank_events WHERE reference='OPS-CSV-ROLLBACK'"),'no partial statement posting');
 gassert((int)one('SELECT COUNT(*) n FROM pg_cards')['n']===$cards,'statement import never generates cards');
 as_client(1014);greject(fn()=>gc_bank_import($entries,current_client()),'customer cannot run bank import');
 file_put_contents($csv,"reference;amount;bankDate;orderId;comment\nBAD;1.00;".gmdate('Y-m-d').";12xyz;bad order\n");greject(fn()=>gc_bank_csv($csv),'ambiguous order number is rejected');
 file_put_contents($csv,"reference;amount;bankDate;orderId;comment\n");greject(fn()=>gc_bank_csv($csv),'empty statement rejected');
}finally{unlink($csv);as_client(1000);}
