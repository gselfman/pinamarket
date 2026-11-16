// Actual deployed PHP/Apache session flow, isolated CI hosting only; no provider calls.
const assert=require('node:assert/strict');
const totp=require('./helpers/totp.cjs');
const {chromium}=require(process.env.PINA_PLAYWRIGHT);
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const context=await browser.newContext({ignoreHTTPSErrors:true}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  const base='https://pina.install.test';
  await page.goto(base+'/admin/');
  await page.locator('#live-auth').waitFor();
  assert.equal(page.url(),base+'/giftcards/#/admin');
  await page.goto(base+'/login/');
  await page.locator('#auth-form').waitFor();
  await page.locator('#email').fill('install-admin@example.com');
  await page.locator('#password').fill('PinaInstall2026-LongPassword');
  await page.locator('#auth-form').getByRole('button',{name:'Войти',exact:true}).click();
  await page.locator('[data-staff-access=setup]').waitFor();
  assert.equal(await page.locator('.admin-shell').count(),0);
  await page.locator('[data-staff-access=setup] [name=password]').fill('PinaInstall2026-LongPassword');
  await page.locator('[data-staff-access=setup] button').click();
  const secret=(await page.locator('.staff-secret').innerText()).replace(/\s/g,'');
  await page.locator('[data-staff-access=confirm] [name=code]').fill(totp(secret));
  await page.locator('[data-staff-access=confirm] button').click();
  await page.locator('.recovery-codes').waitFor();
  await page.locator('[data-action=otp-continue]').click();
  await page.getByRole('heading',{name:'Управление программой',exact:true}).waitFor();
  assert.equal(page.url(),base+'/giftcards/#/admin');
  assert.ok(await page.getByRole('link',{name:'Клиенты',exact:true}).isVisible());
  assert.ok(await page.getByRole('link',{name:'Реестры и выплаты',exact:true}).isVisible());
  await page.getByRole('link',{name:'Операции прежнего сайта'}).click();
  await page.getByRole('heading',{name:'Панель администратора',exact:true}).waitFor();
  assert.equal(new URL(page.url()).searchParams.get('legacy'),'1');
  await page.reload();
  await page.getByRole('heading',{name:'Панель администратора',exact:true}).waitFor();
  assert.equal(new URL(page.url()).searchParams.get('legacy'),'1');
  assert.deepEqual(errors,[]);
  console.log('PASS: deployed admin bookmark and password login open gift-card administration; explicit legacy history survives reload');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
