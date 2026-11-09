import assert from 'node:assert/strict';
const base=process.env.PINA_TEST_URL||'http://127.0.0.1:8081';let checks=0;
const pages=['/','/login/','/register/','/cabinet/','/admin/','/reset/','/documents/'];
const docs=['terms','privacy','refund','cookies','tariffs','requisites','consent'];
for(const path of [...pages,...docs.map(s=>'/documents/'+s+'/')]){
 const r=await fetch(base+path);const body=await r.text();assert.equal(r.status,200,path);assert.ok(body.includes('name="pina-release"'),path+' serves the application');assert.ok(body.includes('mail@pina.market'),path+' canonical contact');
 assert.ok(!/support@pina\.market|в проекте правил возврата|зарегистрирован[^.]{0,40}(?:в РФ|в России)/iu.test(body),path+' no contradictory copy');
 if(docs.some(s=>path==='/documents/'+s+'/'))assert.ok(body.includes('Pina Market LLC'),path+' canonical operator');
 if(['/login/','/register/','/admin/','/cabinet/'].includes(path)){assert.ok(body.includes('Введите email'),path+' useful HTML before JS');assert.ok(body.includes('<noscript>'),path+' no-JS guidance')}
 if(path==='/documents/')assert.ok(!body.includes('<script'), 'document index does not depend on JS');
 checks++;
 if(path!=='/'){
  const query=path==='/reset/'?'?token=test-token':'';
  const redirect=await fetch(base+path.slice(0,-1)+query,{redirect:'manual'});assert.ok([301,308].includes(redirect.status),path+' permanent slash alias');const destination=new URL(redirect.headers.get('location'),base);assert.equal(destination.origin,new URL(base).origin);assert.equal(destination.pathname+destination.search,path+query);checks++;
 }
}
for(const path of ['/missing/','/documents/missing/']){assert.equal((await fetch(base+path)).status,404,path);checks++}
for(const asset of ['app.js','legal.js','style.css','pina-logo.png']){const r=await fetch(base+'/'+asset);assert.equal(r.status,200,asset);assert.ok((await r.arrayBuffer()).byteLength>100,asset+' nonempty');checks++}
const health=await fetch(base+'/api/health/');assert.equal(health.status,200,'API reachable through web server');assert.equal((await health.json()).status,'ok','database connected through web server');checks++;
console.log(`PASS: ${checks} public routes, canonical redirects, server HTML, document consistency and assets (${base})`);
