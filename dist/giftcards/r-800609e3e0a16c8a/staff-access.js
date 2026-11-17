// Real staff authentication only. TOTP secrets and recovery codes never enter localStorage.
export function createStaffAccess({api,esc,logo,status,refresh}){
 let access=null,secret='',uri='',recoveryCodes=[];
 const h=esc,error='<p class="form-error" role="alert" hidden></p>';
 const wrap=body=>`<main class="wrap legal-page staff-access" id="main"><a class="logo" href="/">${logo()}</a><section class="panel"><p class="eyebrow">Вход сотрудника</p>${body}<p class="note">${h(access?.email||'')} · Pina Market</p></section></main>`;
 async function gate(force=false){access=await api('admin/access/status/');if(access.verified&&!force)return null;return render();}
 function render(){
  if(recoveryCodes.length)return wrap(`<h1>Сохраните резервные коды</h1><p>Каждый код используется один раз вместо кода приложения. Сохраните их отдельно от телефона. Повторно показать этот список нельзя.</p><pre class="recovery-codes">${recoveryCodes.map(h).join('\n')}</pre><button class="btn outline" data-action="otp-backup">Скачать резервные коды</button><button class="btn" data-action="otp-continue">Коды сохранены — открыть управление</button>`);
  if(secret)return wrap(`<h1>Добавьте Pina Market в приложение</h1><ol><li>В аутентификаторе выберите добавление аккаунта и ручной ввод ключа.</li><li>Название: Pina Market. Тип: по времени, 6 цифр, период 30 секунд.</li><li>Введите ключ ниже и подтвердите кодом из приложения.</li></ol><p>Ключ настройки</p><code class="staff-secret">${h(secret.match(/.{1,4}/g).join(' '))}</code><button class="btn outline small" data-action="otp-copy">Скопировать ключ</button><details><summary>Ссылка настройки для совместимого приложения</summary><a class="text-link" href="${h(uri)}">Открыть Pina Market в аутентификаторе</a></details><form data-staff-access="confirm" class="fields"><label>Код приложения<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label><button class="btn">Подтвердить подключение</button>${error}</form><p class="note">Ключ виден только во время настройки. Никому его не передавайте. Настройка действует 10 минут.</p>`);
  if(!access.enrolled)return wrap(`<h1>Подключите аутентификатор</h1><p>Для управления Pina Market нужен одноразовый код приложения на телефоне. Введите ранее установленный пароль этой учётной записи Pina Market, затем добавьте выданный ключ в приложение. Если пароль не помните, восстановите его через почту по ссылке ниже.</p><form data-staff-access="setup" class="fields"><label>${access.needsPassword?'Задайте пароль сотрудника — от 12 символов':'Пароль сотрудника'}<input name="password" type="password" autocomplete="current-password" required ${access.needsPassword?'minlength="12"':''}></label><button class="btn">Настроить приложение</button>${error}</form><a href="/reset/" target="_blank" rel="noopener">Восстановить пароль через почту</a>`);
  return wrap(`<h1>Подтвердите вход в управление</h1><p>Введите текущий шестизначный код Pina Market из вашего приложения-аутентификатора.</p><form data-staff-access="verify" class="fields"><label>Код приложения<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label><button class="btn">Войти в управление</button>${error}</form><details><summary>Нет доступа к приложению?</summary><form data-staff-access="recovery" class="fields"><label>Один резервный код<input name="code" autocomplete="off" required maxlength="40"></label><button class="btn outline">Использовать резервный код</button>${error}</form><p class="note">После входа можно заменить аутентификатор в настройках безопасности.</p></details>`);
 }
 async function submit(form,data){const mode=form.dataset.staffAccess;
  if(mode==='setup'){const r=await api('admin/access/setup/',{password:data.password});form.querySelector('[name=password]').value='';secret=r.secret;uri=r.uri;document.querySelector('#app').innerHTML=render();return;}
  const r=await api('admin/access/'+(mode==='confirm'?'confirm/':'verify/'),{code:data.code,recovery:mode==='recovery'});form.querySelector('[name=code]').value='';
  if(mode==='confirm'){secret='';uri='';recoveryCodes=r.recoveryCodes;document.querySelector('#app').innerHTML=render();return;}
  status('Вход в управление подтверждён.');await refresh();
 }
 async function click(action){
  if(action==='otp-copy'){await navigator.clipboard.writeText(secret);status('Ключ настройки скопирован.');}
  if(action==='otp-backup'){const blob=new Blob(['Pina Market — одноразовые резервные коды\n'+recoveryCodes.join('\n')],{type:'text/plain;charset=utf-8'}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='Pina-Market-recovery-codes.txt';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
  if(action==='otp-continue'){recoveryCodes=[];await refresh();}
  if(action==='otp-change'){access=await api('admin/access/status/');if(!access.verified){await refresh();return;}access.enrolled=false;secret='';uri='';document.querySelector('#app').innerHTML=render();}
 }
 return {gate,submit,click};
}
