import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
const base='http://127.0.0.1:8081';
let checks=0;function ok(value,label){assert.ok(value,label);checks++}
class Client{
 constructor(){this.cookie='';this.csrf=''}
 async request(path,data,withCsrf=true){const headers={Origin:base};if(this.cookie)headers.Cookie=this.cookie;if(data){headers['Content-Type']='application/json';if(withCsrf)headers['X-CSRFToken']=this.csrf}const r=await fetch(base+path,{method:data?'POST':'GET',headers,body:data?JSON.stringify(data):undefined});const cookies=r.headers.getSetCookie?.()||[];for(const c of cookies)if(c.startsWith('pina_session='))this.cookie=c.split(';')[0];let body;try{body=await r.json()}catch{body=await r.text().catch(()=>null)}return {status:r.status,body}}
 async login(email){const a=await this.request('/api/config/');this.csrf=a.body.csrf;const r=await this.request('/api/login/',{email,password:'PinaHttpTest2026-Secret'});assert.equal(r.status,200);this.csrf=r.body.csrf;return r}
}
const guest=new Client();let r=await guest.request('/api/admin/clients/');ok(r.status===401,'admin API requires login');r=await guest.request('/api/deposits/',{amount:'100',key:randomUUID()},false);ok(r.status===403,'CSRF rejects missing token');
for(const slug of ['terms','privacy','refund','cookies','tariffs','requisites','consent']){const r=await fetch(base+'/documents/'+slug+'/');const html=await r.text();ok(r.status===200&&html.includes('Pina.market')&&!/прототип|демонстрац|проект для/iu.test(html),'public document '+slug)}
const client=new Client(),other=new Client(),admin=new Client();await client.login('client1@example.com');await other.login('client1@example.com');await admin.login('client3@example.com');
r=await client.request('/api/admin/clients/');ok(r.status===403,'regular user cannot list all clients');
r=await client.request('/api/state/');ok(r.status===200&&r.body.client.id===1000,'authenticated server state');const initial=r.body.client.balance;
const key=randomUUID();const [a,b]=await Promise.all([client.request('/api/deposits/',{amount:'125',key}),other.request('/api/deposits/',{amount:'125',key})]);ok(a.status===200&&b.status===200&&a.body.id===b.body.id,'concurrent deposit request is idempotent');
r=await admin.request('/api/admin/operations/'+a.body.id+'/',{action:'confirm',amount:'100',amountVerified:true,bankref:'HTTP-BANK-1'});ok(r.status===200,'actual deposit confirmation endpoint');r=await admin.request('/api/admin/operations/'+a.body.id+'/',{action:'confirm',amount:'100',amountVerified:true,bankref:'HTTP-BANK-1'});ok(r.status===409,'duplicate confirmation rejected via HTTP');
r=await client.request('/api/state/');ok(r.body.client.balance===initial+10000,'deposit credited exactly once');
const service=r.body.services.find(s=>s.id==='steam');const payload={amount:'2000',service:'steam',recipient:'http-user',region:'RU',version:service.version,total:210000,legalVersion:'2026-11-09.1',accepted:true};
const orders=await Promise.all([client.request('/api/orders/',{...payload,key:randomUUID()}),other.request('/api/orders/',{...payload,key:randomUUID()})]);ok(orders.filter(o=>o.status===200).length===1&&orders.filter(o=>o.status===409).length===1,'two sessions cannot over-reserve balance');
r=await client.request('/api/state/');ok(r.body.client.balance-r.body.client.reserved>=0,'available balance nonnegative');
const record=orders.find(o=>o.status===200).body;await admin.request('/api/admin/operations/'+record.id+'/',{action:'take'});r=await admin.request('/api/admin/operations/'+record.id+'/',{action:'complete',text:'HTTP provider confirmation',proof:'HTTP-PROVIDER-1'});ok(r.status===200&&r.body.status==='completed','HTTP order completion');
r=await admin.request('/api/operations/?scope=admin&kind=order&status=completed');ok(r.status===200&&r.body.count>=2,'HTTP filtered register');
const csv=await fetch(base+'/api/operations/?scope=admin&export=csv',{headers:{Cookie:admin.cookie}});const exported=await csv.text();ok(csv.status===200&&exported.includes('Номер')&&exported.includes(String(record.id)),'CSV export authorized and contains the operation');
const filteredCsv=await fetch(base+'/api/operations/?scope=admin&kind=deposit&number='+a.body.id+'&client=1000&export=csv',{headers:{Cookie:admin.cookie}});const amounts=await filteredCsv.text();ok(amounts.includes('125.00')&&amounts.includes('100.00')&&amounts.includes('Зачислено RUB'),'CSV preserves requested and actual deposit amounts with client/number filters');
r=await client.request('/api/password/',{current:'PinaHttpTest2026-Secret',password:'PinaHttpNew2026-LongSecret'});ok(r.status===200,'password changed via authenticated session');r=await other.request('/api/state/');ok(r.body.authenticated===false,'password change revokes other sessions');
r=await client.request('/api/logout/',{});ok(r.status===200,'logout');r=await client.request('/api/state/');ok(r.body.authenticated===false,'session invalidated');
console.log(`PASS: ${checks} HTTP checks with real PHP sessions and MySQL`);
