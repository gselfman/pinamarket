// No network or browser required: exercise the real administration controllers.
import assert from 'node:assert/strict';
import {createAdmin} from '../prototype/admin.mjs';
import {createStaffAccess} from '../prototype/staff-access.mjs';
const calls=[],app={innerHTML:''};let modal='',closed=0;
const checklist={pina:true,legal:false,supplier:true,sales:false};
const issuer={id:'11111111-1111-4111-8111-111111111111',name:'Test supplier',status:'review',company:{},program:{nominals:[100000]},contract_number:'TEST',contract_status:'draft'};
globalThis.location={hash:''};globalThis.window={dispatchEvent(){}};globalThis.HashChangeEvent=class{};globalThis.document={querySelector:()=>app};
const esc=x=>String(x??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;');
const btn=(text,action,style='',id='')=>`<button class="btn ${style}" data-action="${action}" data-id="${id}">${text}</button>`;
async function api(route,data){calls.push({route,data});if(route==='admin/dashboard/')return {role:'admin',reauthUntil:0,checklist,counts:{},suppliers:[]};if(route.startsWith('admin/supplier/?'))return {issuer,programIssues:[],merchantIssues:[],contractReadiness:{ready:false,issues:['No signed agreement'],documentId:null},merchant:{siteId:'point',recipientLogin:'PINA.test'}};if(route==='admin/issuer-approve/')return {status:'active',warnings:['Agreement is separate']};if(route==='admin/sales-toggle/'){checklist.sales=data.enabled;return {enabled:data.enabled};}if(route==='admin/access/status/')return {verified:true,enrolled:true,email:'test@example.invalid'};if(route==='admin/access/setup/')return {secret:'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',uri:'otpauth://test'};throw Error('Unexpected request '+route);}
const admin=createAdmin({api,esc,icon:()=>'',logo:()=>'',btn,money:String,date:String,openModal:html=>{modal=html;},closeModal:()=>{closed++;},status(){},client:()=>({id:1000,email:'test@example.invalid'}),refreshConfig:async()=>{},csrf:()=>''});
let html=await admin.render('#/admin');assert.match(html,/data-action="admin-launch-task"/);assert.doesNotMatch(html,/admin-unlock|data-admin-form="reauth"|15 минут/);
await admin.click('launch-task','legal');assert.equal(location.hash,'/admin/settings?focus=legal');
checklist.legal=true;html=await admin.render('#/admin');assert.match(html,/Всё готово к продажам/);assert.match(html,/data-admin-form="sales"/);assert.doesNotMatch(html,/flowVerified|type="password"/);
await admin.click('approve',issuer.id);assert.match(modal,/data-admin-form="approve"/);assert.match(modal,/data-program-contract-warning/);assert.doesNotMatch(modal,/type="checkbox"|type="password"|name="code"/);
await admin.submit({dataset:{adminForm:'approve',id:issuer.id}},{});assert(calls.some(x=>x.route==='admin/issuer-approve/'));assert(!calls.some(x=>x.route==='admin/reauth/'));
await admin.submit({dataset:{adminForm:'sales'}},{enabled:'1'});const launch=calls.find(x=>x.route==='admin/sales-toggle/');assert.deepEqual(launch.data,{enabled:true});assert(checklist.sales);assert(closed>=2);
const access=createStaffAccess({api,esc,logo:()=>'',status(){},refresh:async()=>{}});assert.equal(await access.gate(),null);await access.click('otp-change');assert.doesNotMatch(app.innerHTML,/type="password"/);assert.match(app.innerHTML,/Показать новый ключ/);await access.submit({dataset:{staffAccess:'setup'},querySelector:()=>null},{});assert.match(app.innerHTML,/name="code"/);assert(!calls.some(x=>x.route==='admin/access/verify/'));
console.log('PASS: administration acts after one login, unsigned programme confirmation, direct sales launch, focused setup and authenticator rotation without another password');
