// Actual deployed PHP/Apache session flow, isolated CI hosting only; no provider calls.
const assert=require('node:assert/strict');
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
  await page.getByRole('heading',{name:'Управление программой',exact:true}).waitFor();
  assert.equal(page.url(),base+'/giftcards/#/admin');
  assert.ok(await page.getByRole('heading',{name:'Настройки мерчантов V-DAR',exact:true}).isVisible());
  await page.getByRole('link',{name:'Открыть операции прежнего сайта'}).click();
  await page.getByRole('heading',{name:'Панель администратора',exact:true}).waitFor();
  assert.equal(new URL(page.url()).searchParams.get('legacy'),'1');
  await page.reload();
  await page.getByRole('heading',{name:'Панель администратора',exact:true}).waitFor();
  assert.equal(new URL(page.url()).searchParams.get('legacy'),'1');
  assert.deepEqual(errors,[]);
  console.log('PASS: deployed admin bookmark and password login open gift-card administration; explicit legacy history survives reload');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
