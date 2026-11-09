import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=fs.readFileSync('dist/app.js','utf8');
for(const marker of ['seedDemo','localStorage.','demo@example.com','data-action="demo"','data-action="admin"','Прототип','Демонстрац','в проекте правил возврата','support@pina.market'])assert.ok(!source.includes(marker),`Unexpected production marker: ${marker}`);
const ctx={};vm.createContext(ctx);vm.runInContext(fs.readFileSync('dist/legal.js','utf8')+';this.result={version:LEGAL_VERSION,company:COMPANY,documents:LEGAL};',ctx);
assert.deepEqual(JSON.parse(JSON.stringify(ctx.result)),(()=>{const {version,company,documents}=JSON.parse(fs.readFileSync('app/legal.json','utf8'));return {version,company,documents}})());
assert.equal(Object.keys(ctx.result.documents).length,7);
assert.equal(JSON.parse(fs.readFileSync('app/catalog.json','utf8')).length,11);
console.log('PASS: production UI, legal versions and catalog checks');

const legal=JSON.stringify(ctx.result);
assert.ok(!/support@pina\.market|зарегистрирован[^.]{0,40}(?:в РФ|в России)/iu.test(legal),'consistent operator and support address');
assert.equal(ctx.result.company.email,'mail@pina.market');
assert.ok(legal.includes('Республика Армения'));
