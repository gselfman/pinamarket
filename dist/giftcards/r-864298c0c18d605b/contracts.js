// The real signing service, numbering and signature key must live on the server.
// This browser generator produces a clearly numbered DEMO draft from the agreed Word template.
export async function generateContract(company,record) {
 const source=await fetch('/giftcards/r-864298c0c18d605b/assets/agency-template.docx');if(!source.ok)throw Error('Шаблон договора временно недоступен.');
 const zip=await window.JSZip.loadAsync(await source.arrayBuffer()),path='word/document.xml',xml=await zip.file(path).async('string'),doc=new DOMParser().parseFromString(xml,'application/xml'),ns='http://schemas.openxmlformats.org/wordprocessingml/2006/main';
 if(doc.querySelector('parsererror'))throw Error('Не удалось прочитать шаблон.');
 const text=e=>[...e.getElementsByTagNameNS(ns,'t')].map(n=>n.textContent).join('');
 const setText=(el,value)=>{const nodes=[...el.getElementsByTagNameNS(ns,'t')];if(nodes.length){[...el.getElementsByTagNameNS(ns,'br')].forEach(n=>n.remove());const lines=value.split('\n');nodes[0].textContent=lines[0];nodes.slice(1).forEach(n=>n.textContent='');for(const line of lines.slice(1)){const br=doc.createElementNS(ns,'w:br'),t=doc.createElementNS(ns,'w:t');t.textContent=line;nodes[0].parentNode.append(br,t);}}};
 const formattedDate=new Date(record.date).toLocaleDateString('ru-RU',{timeZone:'Europe/Moscow'});
 const paras=[...doc.getElementsByTagNameNS(ns,'p')];
 for(const p of paras) {
  let t=text(p);
  if(t.startsWith('Договор №'))t=`Договор № ${record.number}     Место заключения Ереван     Дата ${formattedDate} — ПРОЕКТ БЕЗ ПОДПИСИ`;
  else if(t.includes('и [полное наименование эмитента]')) t=t.replace('[полное наименование эмитента]',company.name).replace('регистрационный номер [номер]','регистрационный номер '+company.reg).replace('налоговый номер [номер]','налоговый номер '+company.tax).replace('[должность и ФИО]',company.role+' '+company.director).replace('[основание полномочий]',company.authority);
  else if(t.startsWith('К Договору №'))t=t.replace('[номер]',record.number).replace('[дата]',formattedDate);
  if(t.includes('Эмитент: __________________ / [ФИО]'))t='Агент: __________________ / Усенков П. А.\nЭмитент: __________________ / '+company.director;
  if(t!==text(p))setText(p,t);
 }
 const tables=[...doc.getElementsByTagNameNS(ns,'tbl')];
 // Existing issuer requisites cell, retaining the table's layout and all other cells.
 const cells=[...tables[0].getElementsByTagNameNS(ns,'tc')];
 setText(cells[3],`${company.name}\nСтрана: Кыргызская Республика\nРег. № ${company.reg}\nНалоговый № ${company.tax}\nАдрес: ${company.address}\nБанковские реквизиты: Приложение 1\nЭлектронная почта: ${company.email}\nТелефон: ${company.phone}`);
 setText(cells[5],`${company.role}\n${company.director}\nПодпись __________________`);
 const values={
 'Эмитент и фактический поставщик':`${company.name}, рег. № ${company.reg}, налоговый № ${company.tax}, ${company.address}, ${company.email}, ${company.phone}. Эмитент является стороной основного договора и отвечает за привлеченных им исполнителей.`,
 'Товары или услуги и место исполнения':`${company.goods}. Место исполнения: Кыргызская Республика. Программа не включает электронные услуги без отдельного согласования налогового порядка.`,
 'Правила карты и основного договора':`${company.rules}. Редакция правил согласуется и фиксируется до открытия продаж; сохраняется для каждого заказа.`,
 'Номиналы и валюта':'10 000, 50 000, 100 000, 500 000, 1 000 000 и 5 000 000 RUB. Цена для покупателя соответствует номиналу. Доступные номиналы и лимиты согласуются до открытия продаж.',
 'Выпуск и действие':`Срок выпуска по запросу: ${company.issueTime}. Срок запроса выпуска: ${company.requestTime}. Срок действия после выпуска: ${company.validity}. Последствия истечения не исключают предусмотренные законом денежные требования.`,
 'Погашение':`${company.redemption}. При погашении Эмитент фиксирует ФИО покупателя и связанный основной договор / заказ. Данные отражаются в реестре. Отдельный агентский договор с покупателем не нужен.`,
 'Банк и счет Эмитента':`${company.bank}. Получатель: ${company.name}. Счет / IBAN: ${company.account}. SWIFT / банковский код: ${company.swift}. Другой получатель допускается только по пункту 6.3.`,
 'Подтверждение зачисления':'Выписка банка получателя либо банковское подтверждение. Передается в течение одного рабочего дня после получения или запроса через согласованный защищенный канал.',
 'Периодичность и комиссии':'Перечисление по пункту 6.1. Вознаграждение Агента — 0,1%. Комиссии финансовой организации: валютный контроль — 0,2%, перевод — 0,6%. База — номинал погашенных карт до удержаний. Расходы подтверждаются банковскими документами и сверяются. Конвертация не предусмотрена; расчёты в RUB. Изменения требуют письменного соглашения.',
 'Обеспечение':'Не применяется. Иные условия оформляются отдельным письменным соглашением.',
 'Связь и обмен подтверждениями':`Агент: mail@pina.market; +7 977 852 58 10; https://pina.market. Ответственный: Усенков Павел Александрович. Эмитент: ${company.email}; ${company.director}; ${company.phone}. Защищенный канал подтверждений: ${company.exchangeChannel}.`
 };
 for(const row of tables[1].getElementsByTagNameNS(ns,'tr')) {const cs=[...row.getElementsByTagNameNS(ns,'tc')];if(cs.length===2&&values[text(cs[0])])setText(cs[1],values[text(cs[0])]);}
 zip.file(path,new XMLSerializer().serializeToString(doc));
 // Remove personal editing metadata; keep numbering, styles, headers and relationships intact.
 if(zip.file('docProps/core.xml')){let core=await zip.file('docProps/core.xml').async('string');core=core.replace(/<dc:creator>.*?<\/dc:creator>/s,'<dc:creator>Pina Market</dc:creator>').replace(/<cp:lastModifiedBy>.*?<\/cp:lastModifiedBy>/s,'<cp:lastModifiedBy>Pina Market</cp:lastModifiedBy>');zip.file('docProps/core.xml',core);}
 return zip.generateAsync({type:'blob',mimeType:'application/vnd.openxmlformats-officedocument.wordprocessingml.document',compression:'DEFLATE'});
}
export function downloadBlob(blob,name) {const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
// IndexedDB keeps the uploaded test document local and outside public source/deployment archives.
async function db() {return new Promise((resolve,reject)=>{const r=indexedDB.open('pina-merchant-demo',1);r.onupgradeneeded=()=>r.result.createObjectStore('files');r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error);});}
export async function putContract(file) {const d=await db();try {await new Promise((res,rej)=>{const tx=d.transaction('files','readwrite');tx.objectStore('files').put(file,'signed');tx.oncomplete=res;tx.onerror=()=>rej(tx.error);});}finally{d.close();}}
export async function getContract() {const d=await db();try{return await new Promise((res,rej)=>{const r=d.transaction('files').objectStore('files').get('signed');r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});}finally{d.close();}}
export async function clearContract() {const d=await db();try{await new Promise((res,rej)=>{const tx=d.transaction('files','readwrite');tx.objectStore('files').delete('signed');tx.oncomplete=res;tx.onerror=()=>rej(tx.error);});}finally{d.close();}}
