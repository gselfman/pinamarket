// Demonstration domain only. Never connect this storage or these transitions to real payments.
export const DENOMINATIONS = [1000, 2000, 3000, 5000, 10000, 20000, 25000, 50000, 100000, 200000, 300000, 500000, 1000000, 2000000, 3000000, 5000000];
export const PARTNERS = [
  {id:'auto', name:'Автомобили и подбор', issuer:'Демо-поставщик автомобилей', category:'Автомобили', mark:'АВ', color:'sage', icon:'car', description:'Подбор и покупка автомобиля у компании в Кыргызстане.', use:'Подбор автомобиля или покупка по договору с поставщиком.'},
  {id:'parts', name:'Запчасти и комплектующие', issuer:'Демо-поставщик запчастей', category:'Запчасти', mark:'ЗЧ', color:'sand', icon:'parts', description:'Детали и консультация по подбору у зарубежного поставщика.', use:'Запчасти и услуги подбора, указанные в договоре поставщика.'},
  {id:'consult', name:'Консультации по автомобилям', issuer:'Демо-компания консультаций', category:'Консультации', mark:'КС', color:'lavender', icon:'consult', description:'Консультация специалиста по покупке автомобиля в Кыргызстане.', use:'Консультационные услуги на территории Кыргызстана.'}
];
export const VERSION = 1;
const stamp = () => new Date().toISOString();
export function seedState() {
  return {version:VERSION, email:'demo@example.com', verified:true, nextId:1044, applications:[], claims:[], events:[], orders:[
    {id:'1043', partner:'auto', email:'demo@example.com', created:'2026-11-14T10:30:00Z', payment:'confirmed', verified:true, settlement:'pending', cards:[{id:'1043-1', nominal:100000, issuance:'not_issued', redemption:'not_redeemed', refund:'none', code:null},{id:'1043-2', nominal:50000, issuance:'not_issued', redemption:'not_redeemed', refund:'none', code:null}]},
    {id:'1042', partner:'parts', email:'demo@example.com', created:'2026-11-12T09:10:00Z', payment:'confirmed', verified:true, settlement:'pending', cards:[{id:'1042-1', nominal:50000, issuance:'issued', redemption:'not_redeemed', refund:'none', code:'DEMOPART000000001042'}]},
    {id:'1041', partner:'auto', email:'demo@example.com', created:'2026-11-10T12:00:00Z', payment:'confirmed', verified:true, settlement:'credited', cards:[{id:'1041-1', nominal:500000, issuance:'issued', redemption:'redeemed', refund:'none', code:'DEMOAUTO000000001041', redeemedAt:'2026-11-11T13:00:00Z', buyer:'Демонстрационный покупатель', contract:'ДЕМО-2026/11-01'}]}
  ]};
}
export const findOrder = (s,id) => s.orders.find(o=>o.id===id);
export const findCard = (s,id) => {for(const order of s.orders) { const card=order.cards.find(c=>c.id===id); if(card) return {order,card}; } throw Error('Карта не найдена. Обновите страницу.');};
export const total = o => o.cards.reduce((sum,c)=>sum+c.nominal,0);
export function cardStatus(o,c) {
  if(c.refund==='completed') return ['returned','Возврат выполнен'];
  if(c.refund==='requested') return ['review','Запрошен возврат'];
  if(o.payment!=='confirmed') return ['waiting','Ожидает оплаты'];
  if(c.redemption==='redeemed') return ['redeemed','Принята поставщиком'];
  if(c.issuance==='unknown') return ['review','Проверяем создание'];
  if(c.issuance==='issued') return ['issued','Код создан'];
  return ['ready','Можно создать код'];
}
export function createOrder(s,{partner,quantities,email}) {
  if(!PARTNERS.some(p=>p.id===partner)) throw Error('Выберите поставщика.');
  email=String(email).trim().toLowerCase();
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)||email.length>254) throw Error('Проверьте адрес электронной почты.');
  const nominals=[];
  for(const nominal of DENOMINATIONS) {
    const count=Number(quantities[nominal]||0);
    if(!Number.isInteger(count)||count<0||count>10) throw Error('Для одного номинала доступно от 0 до 10 карт.');
    for(let i=0;i<count;i++) nominals.push(nominal);
  }
  if(!nominals.length||nominals.length>10) throw Error('Выберите от 1 до 10 карт в одном заказе.');
  const id=String(s.nextId++);
  const order={id,partner,email,created:stamp(),verified:s.verified&&s.email===email,payment:'pending',settlement:'pending',cards:nominals.map((nominal,i)=>({id:id+'-'+(i+1),nominal,issuance:'not_issued',redemption:'not_redeemed',refund:'none',code:null}))};
  s.email=email;s.verified=order.verified;s.orders.unshift(order);
  return order;
}
export function transition(s,type,id,payload={}) {
  const order=findOrder(s,id);
  if(type==='verify') {
    if(!order) throw Error('Заказ не найден.');
    order.verified=true;s.verified=true;s.email=order.email;
  } else if(type==='confirm_payment') {
    if(!order?.verified) throw Error('Сначала подтвердите email.');
    if(order.payment==='confirmed') return order;
    order.payment='confirmed';order.bankReference='DEMO-BANK-'+order.id;
  } else if(type==='settle') {
    if(!order||!order.cards.some(c=>c.redemption==='redeemed')) throw Error('Сначала поставщик должен принять карту.');
    order.cards.filter(c=>c.redemption==='redeemed'&&c.refund==='none').forEach(c=>c.settlement='credited');
    order.settlement=order.cards.every(c=>c.settlement==='credited')?'credited':'partial';
  } else {
    const {order:o,card:c}=findCard(s,id);
    if(type==='request_refund') {
      if(o.payment!=='confirmed') throw Error('По этому заказу ещё нет подтверждённого поступления.');
      if(c.refund!=='none') throw Error('Обращение по этой карте уже зарегистрировано.');
      if(String(payload.reason||'').trim().length<10) throw Error('Опишите причину: не менее 10 символов.');
      c.refund='requested';
      s.claims.unshift({id:'R-'+id,cardId:id,orderId:o.id,reason:payload.reason.trim(),created:stamp(),status:'Принято к рассмотрению'});
    } else if(type==='complete_refund') {
      if(c.refund!=='requested'||c.redemption==='redeemed'||c.issuance==='unknown') throw Error('Нужна отдельная проверка обращения и статуса карты.');
      c.refund='completed';c.code=null;c.issuance='cancelled';
      s.claims.find(x=>x.cardId===id).status='Возврат выполнен';
    } else {
      if(o.payment!=='confirmed') throw Error('Создание кода доступно после подтверждения оплаты.');
      if(c.refund!=='none') throw Error('Операции по карте приостановлены на время возврата.');
      if(type==='issue') {
        if(c.issuance==='issued') return c; // same card, same code: no double issue
        if(c.issuance!=='not_issued') throw Error('Сначала проверьте результат предыдущего запроса.');
        c.issuance=payload.unknown?'unknown':'issued';
        c.issueRequest='ISSUE-'+c.id;
        if(!payload.unknown) c.code='DEMO'+c.id.replace(/\D/g,'').padStart(16,'0');
      } else if(type==='reconcile') {
        if(c.issuance!=='unknown') throw Error('Нет незавершённого запроса.');
        c.issuance='issued';c.code='DEMO'+c.id.replace(/\D/g,'').padStart(16,'0');
      } else if(type==='redeem') {
        if(c.issuance!=='issued'||c.redemption==='redeemed') throw Error('Поставщик может принять только действующий код один раз.');
        if(!String(payload.buyer||'').trim()||!String(payload.contract||'').trim()) throw Error('Нужны ФИО покупателя и номер договора или заказа.');
        c.redemption='redeemed';c.buyer=payload.buyer.trim();c.contract=payload.contract.trim();c.redeemedAt=stamp();
      } else throw Error('Неизвестная операция.');
    }
  }
  s.events.unshift({type,id,at:stamp()});
  return order;
}
export function validState(s) {
  return s?.version===VERSION&&Number.isSafeInteger(s.nextId)&&s.nextId>1043&&Array.isArray(s.orders)&&s.orders.length<500&&s.orders.every(o=>typeof o.id==='string'&&PARTNERS.some(p=>p.id===o.partner)&&Array.isArray(o.cards)&&o.cards.length>0&&o.cards.every(c=>typeof c.id==='string'&&DENOMINATIONS.includes(c.nominal)))&&Array.isArray(s.claims)&&Array.isArray(s.events)&&Array.isArray(s.applications);
}
