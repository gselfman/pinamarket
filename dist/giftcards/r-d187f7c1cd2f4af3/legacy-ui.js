// Adapted from dist/app.js: selectors, escaping, money formatting, modal focus lifecycle,
// delegated data-action navigation and the sidebar/topbar/page-head cabinet shell.
// The existing PHP API and production wallet are deliberately not used by this prototype.
export const $ = s => document.querySelector(s);
export const esc = s => String(s ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
export const money = n => new Intl.NumberFormat('ru-RU',{style:'currency',currency:'RUB',minimumFractionDigits:0,maximumFractionDigits:2}).format(n||0);
export const date = s => new Date(s).toLocaleDateString('ru-RU',{day:'numeric',month:'long',year:'numeric',timeZone:'Europe/Moscow'});
export const dateTime = s => new Date(s).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',timeZone:'UTC'})+' UTC';
let lastFocus;const modalStack=[];
export function openModal(html,title='Диалог Pina Market',wide=false,preserve=false) {
  if(preserve&&!$('#modal').hidden)modalStack.push($('#modal').firstElementChild);
  else modalStack.length=0;
  if($('#modal').hidden) lastFocus=document.activeElement;
  $('#modal').innerHTML=`<section class="dialog ${wide?'wide':''}" role="dialog" aria-modal="true" aria-label="${esc(title)}"><button class="close" data-action="close" aria-label="Закрыть">×</button>${html}</section>`;
  $('#modal').hidden=false;document.body.style.overflow='hidden';$('#app').inert=true;
  requestAnimationFrame(()=>($('#modal').querySelector('[autofocus]')||$('#modal').querySelector('input,button'))?.focus());
}
export function closeModal(force=false) {
  if(!force&&modalStack.length){$('#modal').replaceChildren(modalStack.pop());$('#modal').querySelector('input,button')?.focus();return;}
  modalStack.length=0;
  $('#modal').hidden=true;document.body.style.overflow='';$('#modal').replaceChildren();$('#app').inert=false;
  if(lastFocus?.isConnected) lastFocus.focus();
}
export function installModalKeys() {
  document.querySelector('.skip-link')?.addEventListener('click',e=>{e.preventDefault();const main=document.querySelector('main');if(main){main.tabIndex=-1;main.focus();main.scrollIntoView({block:'start'});}});
  document.addEventListener('keydown',e=>{
    if($('#modal').hidden) return;
    if(e.key==='Escape') closeModal();
    if(e.key==='Tab') {
      const a=[...$('#modal').querySelectorAll('button,a[href],input,select,textarea')].filter(el=>!el.disabled&&el.getClientRects().length);
      if(e.shiftKey&&document.activeElement===a[0]) {e.preventDefault();a.at(-1)?.focus();}
      else if(!e.shiftKey&&document.activeElement===a.at(-1)) {e.preventDefault();a[0]?.focus();}
    }
  });
  $('#modal').addEventListener('click',e=>{if(e.target===$('#modal')) closeModal();});
}
