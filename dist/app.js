const SERVICES = [ {
    id: "steam",
    logo: "/brand-steam-305af9d8f212.png",
    name: "Steam",
    icon: "◉",
    color: "#203b52",
    bg: "#e8eef4",
    desc: "Steam Wallet, игры и контент",
    type: "Пополнение Steam Wallet",
    cat: "Игры"
}, {
    id: "ps",
    logo: "/brand-ps-efc33490c495.png",
    name: "PlayStation",
    icon: "PS",
    color: "#3262b9",
    bg: "#eaf0ff",
    desc: "PSN Wallet и цифровые коды",
    type: "Подарочная карта / код",
    cat: "Игры"
}, {
    id: "xbox",
    logo: "/brand-xbox-62574509d0ad.png",
    name: "Xbox",
    icon: "X",
    color: "#3d783a",
    bg: "#edf6e7",
    desc: "Gift Card, игры и Game Pass",
    type: "Gift Card / Game Pass",
    cat: "Игры"
}, {
    id: "nintendo",
    logo: "/brand-nintendo-8f8fb0b5d3ea.svg",
    name: "Nintendo eShop",
    icon: "N",
    color: "#d8534e",
    bg: "#ffeded",
    desc: "Баланс, игры и коды",
    type: "Подарочная карта / код",
    cat: "Игры"
}, {
    id: "apple",
    logo: "/brand-apple-c5a768356b12.png",
    name: "Apple",
    icon: "A",
    color: "#454a49",
    bg: "#eef1ed",
    desc: "Gift Card, Apple Account, App Store",
    type: "Apple Gift Card",
    cat: "Приложения"
}, {
    id: "google",
    logo: "/brand-google-d0b932ea11a9.png",
    name: "Google Play",
    icon: "▷",
    color: "#498879",
    bg: "#edf5ed",
    desc: "Карты, приложения и контент",
    type: "Подарочная карта",
    cat: "Приложения"
}, {
    id: "roblox",
    logo: "/brand-roblox-dc26e1341826.png",
    name: "Roblox",
    icon: "◇",
    color: "#444b49",
    bg: "#eef0ec",
    desc: "Подарочные карты и Robux",
    type: "Gift Card / Robux",
    cat: "Игры"
}, {
    id: "razer",
    logo: "/brand-razer-1492b0039086.png",
    name: "Razer Gold",
    icon: "R",
    color: "#58862d",
    bg: "#edfadf",
    desc: "Предоплаченные игровые продукты",
    type: "Предоплаченный продукт",
    cat: "Игры"
}, {
    id: "discord",
    logo: "/brand-discord-5ba72d750bae.svg",
    name: "Discord",
    icon: "D",
    color: "#6464ba",
    bg: "#efefff",
    desc: "Nitro и подарочные подписки",
    type: "Nitro / подарочная подписка",
    cat: "Подписки"
}, {
    id: "spotify",
    logo: "/brand-spotify-f7934b08df36.png",
    name: "Spotify",
    icon: "≋",
    color: "#369864",
    bg: "#e8f7ed",
    desc: "Подарочные карты и подписки",
    type: "Карта / подписка",
    cat: "Подписки"
}, {
    id: "netflix",
    logo: "/brand-netflix-637c9c5261d7.png",
    name: "Netflix",
    icon: "N",
    color: "#c14148",
    bg: "#fff0f0",
    desc: "Подарочные карты и подписки",
    type: "Карта / подписка",
    cat: "Подписки"
} ], $ = s => document.querySelector(s), esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;"
}[c])), money = n => new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    maximumFractionDigits: 2
}).format((n || 0) / 100), date = s => new Date(s).toLocaleString("ru-RU", {
    day: "2-digit",
    month: "short",
    hour: "2-digit",
    minute: "2-digit", timeZone: "Europe/Moscow"
}), uid = () => {
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = 15 & b[6] | 64, b[8] = 63 & b[8] | 128;
    const h = [ ...b ].map(v => v.toString(16).padStart(2, "0")).join("");
    return h.slice(0, 8) + "-" + h.slice(8, 12) + "-" + h.slice(12, 16) + "-" + h.slice(16, 20) + "-" + h.slice(20);
}, STATUS = {
    new: "Новая",
    in_progress: "В работе",
    needs_info: "Требуется уточнение",
    completed: "Выполнено",
    cancelled: "Отменено"
};

let lastFocus, csrf = "", signed = !1, staff = !1, view = "overview", adminView = "deposits", selected = "", sessionEmail = "", firstPrompt = !1, filter = "Все", query = "", currentPage = 1, registry = {
    items: [],
    page: 1,
    pages: 1,
    count: 0
}, listFilters = {}, listLoading = !1, listToken = 0, cashAddress = "", cashEnabled = false, services = [], me = null, operations = [], messages = [];

function sicon(s) {
    const a = (s => SERVICES.find(x => x.id === s.id) || SERVICES[0])(s);
    return `<span class="service-icon brand-${a.id}"><img src="${a.logo}" alt="${esc(a.name)}" width="48" height="48" decoding="async"></span>`;
}

function serviceCards(list) {
    return (list = list || services.filter(s => "hidden" !== s.status)).map(s => `<button class="service" data-action="service" data-id="${s.id}" ${"unavailable" === s.status ? 'aria-label="' + esc(s.name) + ' — не доступен"' : ""}><span class="service-head">${sicon(s)}<span class="arrow">↗</span></span><h3>${esc(s.name)}</h3><p>${esc(s.desc)}</p>${"unavailable" === s.status ? '<span class="tag error">Не доступен</span>' : ""}</button>`).join("");
}

function toast(s) {
    $("#toast").textContent = s, $("#toast").classList.add("show"), setTimeout(() => $("#toast").classList.remove("show"), 4e3);
}

async function api(path, data) {
    const r = await fetch("/api/" + path, {
        method: data ? "POST" : "GET",
        credentials: "same-origin",
        signal: AbortSignal.timeout(15000),
        headers: data ? {
            "Content-Type": "application/json",
            "X-CSRFToken": csrf
        } : {},
        body: data ? JSON.stringify(data) : void 0
    });
    let d;
    try {
        d = await r.json();
    } catch {
        throw new Error("Сервер временно недоступен. Повторите позже.");
    }
    if (!r.ok) throw new Error(d.error || "Не удалось выполнить действие");
    return d;
}

async function sync() {
    const s = await api("state/");
    services = s.services, cashAddress = s.cashAddress, cashEnabled = s.cashEnabled, signed = s.authenticated, signed && (me = s.client, 
    staff = s.staff, firstPrompt = s.prompt, operations = s.operations, messages = s.messages);
}

const available = () => me.balance - me.reserved, titles = {
    overview: [ "Обзор", "Ваш баланс и цифровые сервисы" ],
    services: [ "Сервисы", "Цифровые товары, подписки и пополнение сервисов" ],
    account: [ "Счёт", "Заявка на внесение наличных" ],
    balance: [ "Баланс", "Реестр пополнений счёта" ],
    operations: [ "Операции", "Заявки на оплату цифровых сервисов" ],
    docs: [ "Документы", "Условия использования Pina.market" ],
    profile: [ "Профиль", "Данные учётной записи и безопасность" ],
    notifications: [ "Уведомления", "Сообщения от Pina.market" ],
    admin: [ "Панель администратора", "Поступления, заказы и управление сервисами" ]
};

function render() {
    if (!signed) {
        $("#app").innerHTML = `<div class="status-ribbon">Оплата цифровых сервисов · Учёт в рублях</div><div class="wrap"><header class="header"><a class="logo" href="/" data-action="landing" aria-label="Pina.market — на главную"><img class="brand-logo" src="/pina-logo.png" alt="Pina.market" width="250" height="75"></a><nav class="links"><a href="#catalog">Сервисы</a><a href="#how">Как это работает</a><a href="#faq">Вопросы</a><button class="btn small" data-action="auth">Личный кабинет <span>↗</span></button></nav></header><main><section class="hero"><div><div class="eyebrow"><span class="dot"></span>Ваш доступ к цифровому миру</div><h1>Любимые сервисы.<br>Один <span>баланс.</span><br>Всё под рукой.</h1><p>Игры, приложения и подписки — в одном месте. Пополняйте баланс Pina.market и оформляйте заказы на нужные цифровые сервисы.</p><div class="actions"><button class="btn" data-action="auth">Открыть кабинет <span>↗</span></button><a class="btn light" href="#catalog">Выбрать сервис</a></div><div class="note">Учёт в рублях · История операций · Условия до оплаты</div></div><div class="hero-art" aria-hidden="true"><div class="orb"></div><div class="orbit"></div><div class="orbit two"></div><div class="floating fl1">◉</div><div class="floating fl2">X</div><div class="floating fl3">N</div><div class="glass-card"><div class="glass-head"><img class="brand-logo hero-brand" src="/pina-logo.png" alt="Pina.market" width="250" height="75"><span>↗</span></div><div class="glass-balance">12 500 <span style="font-size:23px;color:#79916d">₽</span></div><div class="note">Баланс для ваших цифровых покупок</div><div class="glass-row">${sicon(SERVICES[0])}<div><b style="font-size:12px">Steam Wallet</b><small>Цифровые товары и услуги</small></div><span style="margin-left:auto;font-size:12px">2 100 ₽</span></div></div><div class="verified"><span class="check">✓</span>Все операции в личном кабинете</div></div></section><div class="service-strip"><span>STEAM</span><span>PlayStation</span><span>⊞ Xbox</span><span>Apple</span><span>ROBLOX</span><span>≋ Spotify</span></div><section class="section" id="catalog"><div class="section-top"><div><div class="eyebrow">Каталог сервисов</div><h2>Для игр. Для жизни.<br>Для вас.</h2></div><p>Игры, приложения и подписки.<br>Вид продукта и доступный регион уточняются при оформлении.</p></div><div class="catalog">${serviceCards()}</div><div class="note" style="margin-top:19px">Доступность зависит от продукта и региона аккаунта. Точные условия указаны в карточке сервиса.</div></section><section class="section" id="how" style="padding-top:10px"><div class="eyebrow">Три простых шага</div><h2>От баланса — к новому опыту</h2><div class="steps"><article class="step"><b>01 / ЛИЧНЫЙ КАБИНЕТ</b><h3>Начните с регистрации</h3><p>Укажите email и получите временный пароль на почту. В кабинете — доступный баланс, заявки и история каждого заказа.</p></article><article class="step"><b>02 / ПОПОЛНЕНИЕ</b><h3>Внесите аванс в кассе</h3><p>Получите реквизиты Pina Market и номер операции. После сверки поступления администратор зачислит аванс.</p></article><article class="step"><b>03 / ЦИФРОВЫЕ СЕРВИСЫ</b><h3>Выберите, что нужно</h3><p>Укажите сервис и реквизиты получателя. Проверьте итоговую стоимость и подтвердите заказ с вашего баланса.</p></article></div></section><section class="section faq" id="faq" style="padding-top:0"><div class="eyebrow">На связи</div><h2>Понятно до первого заказа</h2><details><summary>Как пополнить баланс Pina.market?</summary><p>В разделе «Счёт» создайте заявку и выберите внесение наличных в кассе. Система выдаст реквизиты и назначение платежа. Адрес кассы и порядок внесения указаны в заявке. Баланс меняется только после сверки поступления администратором.</p></details><details><summary>Все сервисы пополняются напрямую?</summary><p>В зависимости от сервиса вы получаете зачисление на аккаунт, подарочную карту, цифровой код или подписку. Тип продукта показывается перед подтверждением.</p></details><details><summary>Что происходит, если заказ не выполнен?</summary><p>Сумма заказа резервируется. Если исполнение отменено, резерв освобождается. Порядок возврата неиспользованного аванса описан в <a href="/documents/refund/">правилах возврата</a>.</p></details></section><section class="dark-section"><div><h2>Ваш цифровой мир.<br>Теперь в одном кабинете.</h2><p>Создайте кабинет, пополните баланс и оформите первый заказ.</p></div><button class="btn lime" data-action="auth" data-id="register">Создать кабинет <span>↗</span></button></section></main><footer class="footer"><div class="foot-top"><a class="logo" href="/" data-action="landing" aria-label="Pina.market — на главную"><img class="brand-logo" src="/pina-logo.png" alt="Pina.market" width="250" height="75"></a><div class="foot-links">${Object.entries(LEGAL).map(([k, d]) => `<button class="textbtn" data-action="doc" data-id="${k}">${d.title}</button>`).join("")}</div></div><div class="note">© 2026 Pina Market LLC · ${COMPANY.email}<br>Оплата цифровых товаров и услуг иностранных сервисов. Названия сервисов принадлежат правообладателям; партнёрство с ними не заявляется.</div></footer></div>`;
        return;
    }
    renderCabinet();
}

function renderCabinet() {
    $("#app").innerHTML = `<div class="status-ribbon">Pina.market · Личный кабинет ${"admin" === view ? '<button class="textbtn" style="padding:0 5px;font-size:inherit" data-action="view" data-id="overview">В личный кабинет →</button>' : ""}</div><div class="shell"><aside class="sidebar"><a class="logo" href="/" data-action="landing" aria-label="Pina.market — на главную"><img class="brand-logo" src="/pina-logo.png" alt="Pina.market" width="250" height="75"></a><nav class="nav">${[ [ "overview", "home", "Обзор" ], [ "services", "service", "Сервисы" ], [ "account", "wallet", "Счёт" ], [ "balance", "history", "Баланс" ], [ "operations", "doc", "Операции" ], [ "notifications", "bell", "Уведомления" ], [ "docs", "doc", "Документы" ], [ "profile", "user", "Профиль" ] ].map(([k, i, n]) => {
        return `<button class="${view === k ? "active" : ""}" data-action="view" data-id="${k}">${name = i, 
        `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">${{
            home: '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
            wallet: '<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 10h18m-7 5h4"/>',
            user: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-3a8 8 0 0 1 16 0v3"/>',
            doc: '<path d="M5 3h10l4 4v14H5zm9 0v5h5M8 12h8m-8 4h6"/>',
            service: '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M8 9h8m-8 5h5"/>',
            bell: '<path d="M4 18h16l-2-4V9a6 6 0 0 0-12 0v5zm6 3h4"/>'
        }[name] || '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>'}</svg>`}${n}</button>`;
        var name;
    }).join("")}</nav><div class="side-bottom"><div class="side-box">ID клиента <b>${me.id}</b><br><a href="mailto:mail@pina.market">mail@pina.market ↗</a></div><button class="textbtn" data-action="landing">На главную</button><button class="textbtn" data-action="logout">Выйти</button></div></aside><main class="main"><header class="topbar"><span class="crumb">${"admin" === view ? "Управление Pina.market" : "Личный кабинет / " + titles[view][0]}</span><span class="mobile-logo"><a class="logo" href="/" data-action="landing" aria-label="Pina.market — на главную"><img class="brand-logo" src="/pina-logo.png" alt="Pina.market" width="250" height="75"></a></span><div style="display:flex;gap:12px;align-items:center"><button class="textbtn" data-action="view" data-id="notifications" aria-label="Уведомления">♧ ${messages.filter(m => !m.read).length || ""}</button><button class="textbtn profile" data-action="view" data-id="profile"><span>${esc(me.email)} · ID ${me.id}</span><b class="avatar">${esc(me.email[0].toUpperCase())}</b></button></div></header><div class="page-head"><div><h1>${titles[view][0]}</h1><p>${titles[view][1]}${"admin" !== view ? " · ID " + me.id : ""}</p></div>${[ "overview", "balance" ].includes(view) ? '<button class="btn small" data-action="view" data-id="account">＋ Пополнить</button>' : ""}</div><div id="content">${function() {
        switch (view) {
          case "overview":
            return `<div class="balance-row"><div class="balance-card"><small>Доступный баланс · RUB</small><strong>${money(available())}</strong><button class="btn lime small" data-action="view" data-id="account">＋ Пополнить баланс</button></div><div class="stat"><small>В резерве</small><strong>${money(me.reserved)}</strong><p>По заявкам на исполнении</p></div><div class="stat"><small>ID клиента</small><strong>${me.id}</strong><p>Указывайте при обращении в поддержку</p></div></div><div class="subhead"><h2>Сервисы</h2><button class="textbtn" data-action="view" data-id="services">Весь каталог ↗</button></div><div class="catalog">${serviceCards(services.filter(s => "hidden" !== s.status))}</div><div class="subhead"><h2>Последние заявки</h2></div><div class="panel">${operationTable(operations.filter(o => "order" === o.kind).slice(0, 5))}</div>`;

          case "services":
            return `<div class="subhead"><div class="filter">${[ "Все", "Игры", "Приложения", "Подписки" ].map(f => `<button class="chip ${filter === f ? "active" : ""}" data-action="category" data-id="${f}">${f}</button>`).join("")}</div><input class="search" id="search" value="${esc(query)}" placeholder="Найти сервис" aria-label="Найти сервис"></div><div class="catalog" id="catalog-results">${filtered()}</div>`;

          case "account":
            return `<div class="two-col"><div class="panel"><h2 style="font-size:17px;margin-top:0">Пополнение баланса</h2><form id="cash-form" class="fields"><div class="field"><label for="cash-amount">Сумма, ₽</label><input id="cash-amount" name="amount" type="number" step="0.01" min="1" max="1000000" required placeholder="5 000"></div><div class="field"><label for="cash-method">Способ пополнения</label><select id="cash-method"><option>Наличными в кассе</option></select></div><div class="notice">Заявка получает номер. Деньги появятся на балансе после проверки поступления администратором.</div><button class="btn full" ${cashEnabled?'':'disabled'}>Получить реквизиты ↗</button>${cashEnabled?'':'<div class="notice">Внесение наличных временно недоступно. Напишите в поддержку.</div>'}</form></div><div class="panel"><div class="eyebrow">Порядок зачисления</div><h2 style="font-size:23px">Заявка → касса → баланс</h2><div class="help-card">1. Получите реквизиты и номер операции.<br>2. Внесите наличные по реквизитам заявки.<br>3. Администратор проверит поступление и зачислит сумму.</div><div class="notice warn">${esc(cashAddress||"Адрес кассы появится после включения способа внесения.")}</div><button class="textbtn" data-action="doc" data-id="requisites">Реквизиты получателя →</button></div></div>`;

          case "balance":
          case "operations":
            return registryHTML();

          case "notifications":
            return `<div class="subhead"><h2>Сообщения</h2><button class="textbtn" data-action="read">Отметить прочитанными</button></div>${messages.length ? messages.map(m => `<article class="panel notification"><div class="note">${date(m.created)} ${m.read ? "" : '<span class="tag">Новое</span>'}</div><p>${esc(m.text)}</p>${m.operationId ? `<button class="textbtn" data-action="detail" data-id="${m.operationId}">Заявка №${m.operationId} →</button>` : ""}</article>`).join("") : '<div class="empty">Уведомлений пока нет</div>'}`;

          case "docs":
            return `<div class="notice warn">Правила работы Pina.market, условия заказов и обработки данных.</div><div class="doc-list">${Object.entries(LEGAL).map(([k, d]) => `<button class="doc-link" data-action="doc" data-id="${k}"><div>${d.title}<small>${d.desc}</small></div>↗</button>`).join("")}</div>`;

          case "profile":
            return `<div class="two-col"><div class="panel"><h3>ID клиента ${me.id}</h3><p>${esc(me.email)}</p><button class="btn light" data-action="password">Изменить пароль</button><button class="textbtn" data-action="logout">Выйти</button></div><div class="panel"><h3>Навигация</h3><button class="textbtn" data-action="view" data-id="notifications">Уведомления</button><button class="textbtn" data-action="view" data-id="docs">Документы</button></div></div>`;

          case "admin":
            return `<div style="margin-bottom:16px"><button class="btn light small" data-action="mail-queue">Очередь писем</button> <button class="btn light small" data-action="mail-test">Проверить SMTP письмом на мою почту</button></div><div class="admin-tabs">${[ [ "deposits", "Заявки на пополнение" ], [ "orders", "Оплата сервисов" ], [ "receipts", "Реестр поступлений" ], [ "refunds", "Возвраты" ], [ "adjustments", "Корректировки баланса" ], [ "clients", "Клиенты" ], [ "services", "Сервисы" ], [ "settings", "Настройки" ] ].map(([k, n]) => `<button class="chip ${adminView === k ? "active" : ""}" data-action="admin-tab" data-id="${k}">${n}</button>`).join("")}</div>${"settings" === adminView ? settingsHTML() : "services" === adminView ? `<div class="doc-list">${services.map((s,i) => `<div class="service-admin-row"><button class="doc-link" data-action="edit-service" data-id="${s.id}"><div>${esc(s.name)}<small>${esc(s.desc)}</small></div><span class="tag ${"available" === s.status ? "" : "hidden" === s.status ? "pending" : "error"}">${{
                available: "Доступен",
                unavailable: "Не доступен",
                hidden: "Скрыт"
            }[s.status]}</span></button><div class="service-order"><button class="btn light small" data-action="move-service" data-id="${s.id}" data-direction="up" aria-label="${esc(s.name)} — выше" ${i===0?"disabled":""}>↑ Выше</button><button class="btn light small" data-action="move-service" data-id="${s.id}" data-direction="down" aria-label="${esc(s.name)} — ниже" ${i===services.length-1?"disabled":""}>↓ Ниже</button></div></div>`).join("")}</div>` : "clients" === adminView ? `<form id="client-search" class="subhead"><input name="q" class="search" placeholder="ID или email клиента" aria-label="Поиск клиентов" value="${esc(listFilters.q || "")}"><button class="btn light small">Найти</button></form><div class="panel" style="padding:0">${listLoading ? '<div class="empty">Загрузка…</div>' : `<div class="table-wrap"><table><thead><tr><th>ID</th><th>Email</th><th>Баланс</th><th>В резерве</th><th>Регистрация</th><th></th></tr></thead><tbody>${registry.items.map(c => `<tr><td><b>${c.id}</b></td><td>${esc(c.email)}</td><td>${money(c.balance)}</td><td>${money(c.reserved)}</td><td>${date(c.created)}</td><td><button class="textbtn" data-action="notify-client" data-id="${c.id}">Написать</button><button class="textbtn" data-action="adjust-client" data-id="${c.id}">Зачислить / списать</button><button class="textbtn" data-action="refund-client" data-id="${c.id}">Возврат</button></td></tr>`).join("")}</tbody></table></div>`}</div>${pagination()}` : registryHTML()}`;
        }
    }()}</div></main></div>`;
}

function filtered() {
    return serviceCards(services.filter(s => "hidden" !== s.status && ("Все" === filter || s.cat === filter) && s.name.toLowerCase().includes(query.toLowerCase())));
}

function registryKind() {
    if(view === "balance")return "balance";
    if(view === "admin" && adminView === "adjustments")return "adjustment";
    if(view === "admin" && adminView === "refunds")return "refund";
    return "balance" === view || "admin" === view && [ "deposits", "receipts" ].includes(adminView) ? "deposit" : "order";
}

function registryHTML() {
    return `<form id="filters-form" class="registry-filters"><div class="field"><label>№ заявки / операции</label><input name="number" inputmode="numeric" pattern="[0-9]*" value="${esc(listFilters.number||"")}" placeholder="1000"></div><div class="field"><label>Статус</label><select name="status"><option value="">Все статусы</option>${Object.entries(STATUS).map(([k, n]) => `<option value="${k}" ${listFilters.status === k ? "selected" : ""}>${"deposit" === registryKind() && "completed" === k ? "Зачислено" : n}</option>`).join("")}</select></div><div class="field"><label>С даты</label><input name="from" type="date" value="${esc(listFilters.from || "")}"></div><div class="field"><label>По дату</label><input name="to" type="date" value="${esc(listFilters.to || "")}"></div><div class="field"><label>Сумма от, ₽</label><input name="min" type="number" min="0.01" step="0.01" value="${esc(listFilters.min || "")}"></div><div class="field"><label>Сумма до, ₽</label><input name="max" type="number" min="0.01" step="0.01" value="${esc(listFilters.max || "")}"></div>${"admin" === view ? `<div class="field"><label>Клиент ID / email</label><input name="client" value="${esc(listFilters.client || "")}" placeholder="1000 или email"></div>` : ""}<div class="filter-actions"><button class="btn small">Применить</button><button type="button" class="textbtn" data-action="clear-filters">Сбросить</button></div></form><div class="subhead"><h2>${"admin" === view && "receipts" === adminView ? "Подтверждённые поступления" : ["deposit","balance"].includes(registryKind()) ? "История пополнений и баланса" : registryKind()==="adjustment"?"Корректировки баланса":"Заявки на оплату сервисов"}</h2><button class="btn light small" data-action="export">Выгрузить CSV ↓</button></div><div class="panel" style="padding:0">${listLoading ? '<div class="empty">Загрузка…</div>' : operationTable(registry.items, "admin" === view)}</div>${pagination()}`;
}

function pagination() {
    return `<div class="pagination"><span class="note">Всего ${registry.count} · Страница ${registry.page} из ${registry.pages}</span><div><button class="btn light small" data-action="page" data-id="${registry.page - 1}" ${registry.page <= 1 ? "disabled" : ""}>←</button> <button class="btn light small" data-action="page" data-id="${registry.page + 1}" ${registry.page >= registry.pages ? "disabled" : ""}>→</button></div></div>`;
}

function tag(o) {
    return `<span class="tag ${[ "new", "in_progress", "needs_info" ].includes(o.status) ? "pending" : "cancelled" === o.status ? "error" : ""}">${"deposit" === o.kind && "completed" === o.status ? "Зачислено" : STATUS[o.status]}</span>`;
}

function operationTable(list, admin = !1) {
    return list.length ? `<div class="table-wrap"><table><thead><tr><th>№ / дата (МСК)</th>${admin ? "<th>Клиент</th>" : ""}<th>Назначение</th><th>Сумма</th><th>Статус</th><th></th></tr></thead><tbody>${list.map(o => `<tr><td><b>№${o.id}</b><div class="note">${date(o.created)}</div></td>${admin ? `<td><b>ID ${o.clientId}</b><div class="note">${esc(o.email)}</div></td>` : ""}<td>${esc("deposit" === o.kind ? "Наличными в кассе" : o.kind==="credit"?"Ручное зачисление":o.kind==="debit"?"Ручное списание":o.kind==="refund"?"Возврат аванса":o.service)}<div class="note">${esc(o.product || "Пополнение Pina.market")}</div></td><td><b>${o.kind==="debit"?"−":""}${money(o.total)}</b>${o.kind==="deposit"&&o.actualAmount!==null&&o.amount!==o.actualAmount?`<div class="note">В заявке: ${money(o.amount)}</div>`:""}</td><td>${tag(o)}</td><td><button class="textbtn" data-action="detail" data-id="${o.id}">Открыть →</button></td></tr>`).join("")}</tbody></table></div>` : '<div class="empty">Записей пока нет</div>';
}

function params() {
    const p = new URLSearchParams({
        ...listFilters,
        page: String(currentPage),
        kind: registryKind()
    });
    return "admin" === view && p.set("scope", "admin"), "admin" === view && "receipts" === adminView && p.set("status", "completed"), 
    p;
}

async function loadList() {
    const token = ++listToken;
    if ([ "admin", "balance", "operations" ].includes(view) && ("admin" !== view || !["services","settings"].includes(adminView))) {
        listLoading = !0, renderCabinet();
        try {
            let r;
            if (r = await api("admin" === view && "clients" === adminView ? "admin/clients/?" + new URLSearchParams({
                ...listFilters,
                page: currentPage
            }) : "operations/?" + params()), token !== listToken) return;
            registry = r, listLoading = !1, renderCabinet();
        } catch (e) {
            listLoading = !1, renderCabinet(), toast(e.message);
        }
    }
}

function openModal(html, wide = !1) {
    lastFocus = document.activeElement, $("#modal").innerHTML = `<section class="dialog ${wide ? "wide" : ""}" role="dialog" aria-modal="true" aria-label="Диалог Pina.market"><button class="close" data-action="close" aria-label="Закрыть">×</button>${html}</section>`, 
    $("#modal").hidden = !1, document.body.style.overflow = "hidden", setTimeout(() => $("#modal").querySelector("input,button")?.focus(), 10);
}

function closeModal() {
    $("#modal").hidden = !0, document.body.style.overflow = "", $("#modal").replaceChildren(), lastFocus?.focus();
}

function auth(mode = "login") {
    openModal(`<a class="logo" href="/" data-action="landing" aria-label="Pina.market — на главную"><img class="brand-logo" src="/pina-logo.png" alt="Pina.market" width="250" height="75"></a><div class="auth-tabs"><button class="${"login" === mode ? "active" : ""}" data-action="auth" data-id="login">Вход</button><button class="${"register" === mode ? "active" : ""}" data-action="auth" data-id="register">Регистрация</button></div><h2>${"register" === mode ? "Начните с вашей почты" : "Вход в Pina.market"}</h2><p class="intro">${"register" === mode ? "Отправим временный пароль и ссылку на страницу входа." : "Используйте email и пароль из письма."}</p><form id="auth-form" data-mode="${mode}" class="fields"><div class="field"><label for="email">Введите email</label><input id="email" name="email" type="email" autocomplete="email" required placeholder="Ваша электронная почта"></div>${"login" === mode ? '<div class="field"><label for="password">Пароль</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>' : '<label class="checkline"><input type="checkbox" required><span>Мне исполнилось 18 лет. Принимаю <a href="/documents/terms/" target="_blank" rel="noopener">условия Pina.market</a>, ознакомлен с <a href="/documents/privacy/" target="_blank" rel="noopener">политикой данных</a> и даю <a href="/documents/consent/" target="_blank" rel="noopener">согласие на обработку данных</a>.</span></label>'}<button class="btn full">${"register" === mode ? "Получить пароль на почту" : "Войти"}</button></form>${"login" === mode ? '<button class="textbtn full" data-action="forgot">Забыли пароль?</button>' : ""}`);
}

function passwordDialog(first = !1) {
    openModal(`<h2>${first ? "Установите новый пароль" : "Изменение пароля"}</h2><p class="intro">${first ? "Вы вошли по паролю из письма. Можно установить собственный пароль сейчас или пропустить этот шаг." : "Выберите пароль длиной от 12 символов."}</p><form id="password-form" data-first="${first}" class="fields">${first ? "" : '<div class="field"><label for="current">Текущий пароль</label><input id="current" name="current" type="password" autocomplete="current-password" required></div>'}<div class="field"><label for="newpass">Новый пароль</label><input id="newpass" name="password" type="password" minlength="12" autocomplete="new-password" required></div><div class="field"><label for="repeatpass">Повторите новый пароль</label><input id="repeatpass" name="repeat" type="password" minlength="12" autocomplete="new-password" required></div><button class="btn full">Сохранить пароль</button></form>${first ? '<button class="textbtn full" data-action="skip-password">Пропустить этот шаг</button>' : ""}`);
}

const purpose = o => o.paymentPurpose;

function receipt(o) {
    openModal(`<div class="eyebrow">Заявка на внесение наличных</div><h2>Операция № ${o.id}</h2><p class="intro">Клиент ID ${o.clientId} · ${money(o.total)}</p>${tag(o)}<dl class="receipt"><dt>Получатель</dt><dd>${COMPANY.name}</dd><dt>Банк</dt><dd>${COMPANY.bank}</dd><dt>Счёт</dt><dd>${COMPANY.account}</dd><dt>БИК / корреспондентский счёт</dt><dd>${COMPANY.bik} / ${COMPANY.corr}</dd><dt>ИНН / КПП</dt><dd>${COMPANY.inn} / ${COMPANY.kpp}</dd><dt>Касса</dt><dd>${esc(o.cashAddress||cashAddress)}</dd><dt>Назначение</dt><dd>${esc(purpose(o))}</dd><dt>Сумма заявки</dt><dd>${money(o.amount)}</dd>${o.actualAmount!==null?`<dt>Фактически зачислено</dt><dd><b>${money(o.actualAmount)}</b></dd><dt>Банковское поступление</dt><dd>${esc(o.bankref)}</dd>`:""}${o.result?`<dt>Пояснение</dt><dd>${esc(o.result)}</dd>`:""}</dl>${"admin" === view && "new" === o.status ? `<form id="confirm-form" data-id="${o.id}" data-requested="${o.amount}" class="fields"><div class="field"><label for="bankref">Номер фактического банковского поступления</label><input id="bankref" name="bankref" required maxlength="150"></div><div class="field"><label for="actual">Фактически поступило, ₽</label><input id="actual" name="amount" type="number" step="0.01" min="0.01" required></div><div id="amount-warning" class="notice warn" hidden><b>Сумма отличается от заявки.</b><p id="amount-difference"></p><label class="checkline"><input id="amount-verified" name="amountVerified" type="checkbox"><span>Сумму поступления перепроверил. Она корректна.</span></label></div><div class="field"><label for="receipt-comment">Пояснение (необязательно)</label><textarea id="receipt-comment" name="text" maxlength="5000" rows="2"></textarea></div><label class="checkline"><input type="checkbox" required><span>Проверены поступление, сумма и получатель. Деньги действительно зачислены на счёт Pina Market.</span></label><button class="btn full">Подтвердить поступление</button></form><button class="textbtn full" data-action="cancel" data-id="${o.id}">Отклонить заявку</button>` : o.status==='new'?'<div class="notice">Баланс меняется после проверки фактического поступления администратором.</div>':o.status==='completed'?'<div class="notice">Фактически поступившая сумма зачислена на баланс.</div>':'<div class="notice">Заявка отменена.</div>'}<button class="textbtn full" data-action="copy-receipt" data-id="${o.id}">Скопировать реквизиты</button>`);
}

function openService(id) {
    if (!signed) return selected = id, void auth("register");
    const s = services.find(s => s.id === id);
    s && "hidden" !== s.status ? "available" === s.status ? openModal(`${sicon(s)}<h2>${esc(s.name)}</h2><p class="intro">${esc(s.desc)}</p><form id="order-form" data-id="${id}" class="fields"><div class="field"><label>Продукт</label><input readonly value="${esc(s.type)}"></div><div class="field"><label for="region">Регион</label><select id="region" name="region" required><option value="">Выберите регион</option>${s.regions.map(r => `<option>${esc(r)}</option>`).join("")}</select></div><div class="field"><label for="recipient">ID, логин или email получателя</label><input id="recipient" name="recipient" maxlength="150" required></div><div class="field"><label for="order-amount">Базовая стоимость продукта, ₽</label><input id="order-amount" name="amount" type="number" min="${s.min / 100}" max="${s.max / 100}" step="0.01" required><small>Валюта учёта RUB. Номинал продукта и срок указаны в условиях сервиса.</small></div><div class="notice"><b>Инструкция</b><br>${esc(s.instructions).replace(/\n/g, "<br>")}</div><div class="notice"><b>Условия</b><br>${esc(s.conditions).replace(/\n/g, "<br>")}</div><div class="summary" id="quote">Введите сумму для расчёта</div><label class="checkline"><input type="checkbox" required><span>Проверил реквизиты, условия, продукт и итоговую стоимость.</span></label><button id="order-submit" class="btn full" disabled>Подтвердить и зарезервировать</button></form>`) : openModal(`${sicon(s)}<h2>${esc(s.name)}</h2><div class="notice">Сервис временно не доступен</div><p class="intro">${esc(s.desc)}</p><div class="legal-text">${esc(s.conditions)}</div>`) : toast("Сервис не найден");
}

function findOp(id) {
    return [ ...registry.items, ...operations ].find(o => o.id === Number(id) && o.kind);
}

function orderDetail(o) {
    if(["credit","debit"].includes(o.kind)){openModal(`<h2>${o.kind==="credit"?"Зачисление":"Списание"} №${o.id}</h2><p>Клиент ID ${o.clientId} · ${money(o.total)}</p><dl class="receipt"><dt>Основание</dt><dd>${esc(o.result)}</dd><dt>Сотрудник</dt><dd>${esc(o.assigned)}</dd><dt>Дата</dt><dd>${date(o.created)}</dd></dl>`);return;}
    if(o.kind === "refund"){openModal(`<h2>Возврат №${o.id}</h2><p class="intro">Клиент ${o.clientId} · ${money(o.total)}</p>${tag(o)}<dl class="receipt"><dt>Реквизиты выплаты</dt><dd>${esc(o.recipient)}</dd><dt>Основание</dt><dd>${esc(o.product)}</dd><dt>Результат</dt><dd>${esc(o.result||"Ожидает выплаты")}</dd></dl>${view==="admin"&&o.status==="in_progress"&&o.assigned===me.email?`<form id="refund-complete-form" data-id="${o.id}" class="fields"><div class="field"><label>Номер банковской выплаты</label><input name="bankref" required maxlength="150"></div><div class="field"><label>Подтверждение результата</label><textarea name="text" required rows="3" maxlength="5000"></textarea></div><label class="checkline"><input type="checkbox" required><span>Выплата действительно проведена по проверенным реквизитам клиента.</span></label><button class="btn full">Подтвердить выплату</button></form><button class="textbtn full" data-action="cancel" data-id="${o.id}">Отменить возврат</button>`:""}`);return;}
    openModal(`<div class="eyebrow">${esc(o.service)}</div><h2>Заказ № ${o.id}</h2><p class="intro">Клиент ID ${o.clientId} · ${esc(o.email)}</p>${tag(o)}<dl class="receipt"><dt>Продукт / регион</dt><dd>${esc(o.product)} / ${esc(o.region)}</dd><dt>Получатель</dt><dd>${esc(o.recipient)}</dd><dt>Стоимость / комиссия / итого</dt><dd>${money(o.amount)} / ${money(o.fee)} / <b>${money(o.total)}</b></dd><dt>Исполнитель</dt><dd>${esc(o.assigned || "Ещё не назначен")}</dd>${o.proof?`<dt>Подтверждение исполнения</dt><dd>${esc(o.proof)}</dd>`:""}${o.result ? `<dt>Результат</dt><dd>${esc(o.result)}</dd>` : ""}</dl><details class="legal-text"><summary>Условия на момент заявки</summary><p>${esc(o.conditions || "Условия заказа")}</p><p>${esc(o.instructions || "")}</p></details><div class="conversation">${(o.messages || []).map(m => `<article class="notice ${m.staff ? "" : "client-message"}"><b>${m.staff ? "Pina.market" : "Клиент"}</b><small> · ${date(m.created)}</small><div>${esc(m.text).replace(/\n/g, "<br>")}</div></article>`).join("")}</div>${"admin" === view ? "new" === o.status ? `<button class="btn full" data-action="take" data-id="${o.id}">Взять в работу</button>` : "in_progress" === o.status && o.assigned === me.email ? `<form id="process-form" data-id="${o.id}" class="fields"><div class="field"><label for="process-status">Результат обработки</label><select id="process-status" name="action"><option value="complete">Выполнено</option><option value="clarify">Требуется уточнение</option></select></div><div class="field"><label for="execution-proof">ID операции, хэш или подтверждение исполнения</label><input id="execution-proof" name="proof" maxlength="1000" required><small>Обязательно при завершении заказа. Клиент увидит подтверждение.</small></div><div class="field"><label for="process-text">Подтверждение исполнения или вопрос клиенту</label><textarea id="process-text" name="text" maxlength="5000" required rows="4" placeholder="Укажите результат или что нужно уточнить"></textarea></div><button class="btn full">Сохранить статус</button></form>` : "" : "needs_info" === o.status ? `<form id="reply-form" data-id="${o.id}" class="fields"><div class="field"><label for="reply-text">Ответ оператору</label><textarea id="reply-text" name="text" rows="4" maxlength="5000" required></textarea></div><button class="btn full">Отправить уточнение</button></form>` : ""}${"admin" === view && [ "new", "in_progress", "needs_info" ].includes(o.status) ? `<button class="textbtn full" data-action="cancel" data-id="${o.id}">Отменить и освободить резерв</button>` : ""}`);
}

async function refresh() {
    if (await sync(), "admin" === view && "services" === adminView) {
        const s = await api("admin/services/");
        services = s.services;
    }
    render(), await loadList();
}

async function go(v) {
    await sync();
    view = v, history.replaceState(null,"",v==="admin"?"/admin/":"/cabinet/"), filter = "Все", query = "", currentPage = 1, listFilters = {}, closeModal(), 
    render(), await loadList(), window.scrollTo(0, 0);
}

async function processOperation(id, action, values = {}) {
    await api("admin/operations/" + id + "/", {
        action: action,
        ...values
    });
}

document.addEventListener("click", e => {
    const el = e.target.closest("[data-action]");
    el && (e.preventDefault(), async function(el) {
        const {action: a, id: id} = el.dataset;
        switch (a) {
          case "settings-form-unused": break;
      case "mail-queue": {
            const q=await api("admin/mail-queue/");openModal(`<h2>Отправка писем</h2><p>Ожидают отправки: <b>${Number(q.pending)}</b></p><p>Повторная отправка после ошибки SMTP: <b>${Number(q.retrying)}</b></p><p class="note">Отправка повторяется автоматически. Письма и деньги обрабатываются независимо.</p>`);break;
      }
      case "mail-test":
            await api("admin/mail-test/", {}), toast("Тестовое письмо передано SMTP-серверу");
            break;

          case "landing":
            signed = !1, view = "overview", closeModal(), render(), history.replaceState(null, "", "/"), 
            window.scrollTo(0, 0);
            break;

          case "auth":
            auth(id || "login");
            break;

          case "auth-tab":
            auth(id);
            break;

          case "close":
            closeModal();
            break;

          case "logout":
            await api("logout/", {}), signed = !1, staff = !1, closeModal(), render(), history.replaceState(null, "", "/");
            break;

          case "view":
            await go(id);
            break;

          case "admin":
            if (!staff) return void auth();
            history.replaceState(null, "", "/admin/"), view = "admin", adminView = "deposits", 
            listFilters = {}, currentPage = 1, closeModal(), render(), await loadList();
            break;

          case "move-service": {
            const expected=services.map(s=>s.id), ids=[...expected], index=ids.indexOf(id), direction=e.target.closest("[data-direction]").dataset.direction, target=index+(direction==="up"?-1:1);
            if(index<0||target<0||target>=ids.length)break;
            [ids[index],ids[target]]=[ids[target],ids[index]];
            services=(await api("admin/services-order/",{ids,expected})).services;renderCabinet();toast("Порядок сервисов сохранён");break;
          }
          case "admin-tab":
            adminView = id, listFilters = {}, currentPage = 1, "services" === id && (services = (await api("admin/services/")).services), 
            renderCabinet(), await loadList();
            break;

          case "category":
            filter = id, renderCabinet();
            break;

          case "service":
            openService(id);
            break;

          case "doc":
            !function(id) {
                const d = LEGAL[id];
                d && openModal(`<div class="eyebrow">Pina.market · редакция от 09.11.2026</div><h2>${d.title}</h2><div class="legal-text">${d.sections.map(([h, t]) => `<h3>${esc(h)}</h3><p>${esc(t)}</p>`).join("")}</div>`, !0);
            }(id);
            break;

          case "detail":
            {
                let o = findOp(id);
                if (o || (o = (await api("operations/?" + new URLSearchParams({
                    scope: "admin" === view ? "admin" : "own",
                    number: id
                }))).items.find(x => x.id === Number(id))), !o) return void toast("Откройте соответствующий реестр и найдите заявку по номеру");
                "deposit" === o.kind ? receipt(o) : orderDetail(o);
                break;
            }

          case "page":
            currentPage = Number(id), await loadList();
            break;

          case "clear-filters":
            listFilters = {}, currentPage = 1, await loadList();
            break;

          case "export":
            await async function() {
                {
                    const p = params();
                    return p.set("export", "csv"), void window.location.assign("/api/operations/?" + p);
                }
            }();
            break;

          case "edit-service":
            !function(id) {
                const s = services.find(s => s.id === id);
                openModal(`<h2>${esc(s.name)}</h2><p class="intro">Изменения действуют для новых заказов. Условия ранее принятых заявок сохраняются.</p><form id="service-form" data-id="${id}" class="fields"><div class="field"><label for="service-status">Статус</label><select id="service-status" name="status">${Object.entries({
                    available: "Доступен",
                    unavailable: "Не доступен",
                    hidden: "Скрыт"
                }).map(([k, v]) => `<option value="${k}" ${s.status === k ? "selected" : ""}>${v}</option>`).join("")}</select></div><div class="field"><label for="service-product">Наименование продукта</label><input id="service-product" name="product" maxlength="180" required value="${esc(s.type)}"></div>${[ [ "desc", "Описание", s.desc ], [ "instructions", "Инструкция", s.instructions ], [ "conditions", "Условия пополнения", s.conditions ] ].map(([k, label, v]) => `<div class="field"><label for="service-${k}">${label}</label><textarea id="service-${k}" name="${k}" rows="3" maxlength="${"desc" === k ? 2e3 : 5e3}">${esc(v)}</textarea></div>`).join("")}<div class="field"><label for="service-regions">Регионы — по одному в строке</label><textarea id="service-regions" name="regions" rows="3">${esc(s.regions.join("\n"))}</textarea></div><div class="field"><label for="service-fee">Комиссия, %</label><input id="service-fee" name="fee" type="number" min="0" max="100" step="0.01" required value="${s.feeBps / 100}"></div><div class="two-col"><div class="field"><label>Минимум, ₽</label><input name="min" type="number" min="0.01" step="0.01" required value="${s.min / 100}"></div><div class="field"><label>Максимум, ₽</label><input name="max" type="number" min="0.01" step="0.01" required value="${s.max / 100}"></div></div><button class="btn full">Сохранить настройки</button></form>`, !0);
            }(id);
            break;

          case "adjust-client": {
            const c=registry.items.find(c=>c.id===Number(id));
            openModal(`<h2>Баланс клиента ID ${id}</h2><p class="intro">${esc(c.email)}<br>Баланс ${money(c.balance)} · Свободно ${money(c.balance-c.reserved)}</p><form id="adjust-form" data-id="${id}" class="fields"><div class="field"><label>Операция</label><select name="kind"><option value="credit">Зачислить</option><option value="debit">Списать</option></select></div><div class="field"><label>Сумма, ₽</label><input name="amount" type="number" min="0.01" step="0.01" required></div><div class="field"><label>Основание (видно клиенту)</label><textarea name="text" required maxlength="5000" rows="3"></textarea></div><label class="checkline"><input name="verified" type="checkbox" required><span>Проверил клиента, сумму и направление операции. Изменение сразу отразится на балансе.</span></label><button class="btn full">Провести операцию</button></form>`);break;
          }
          case "refund-client":
            openModal(`<h2>Возврат аванса · ID ${id}</h2><p class="intro">Сначала сумма резервируется. Банковская выплата выполняется сотрудником, после чего подтверждается отдельным номером.</p><form id="refund-form" data-id="${id}" class="fields"><div class="field"><label>Сумма, ₽</label><input name="amount" type="number" min="0.01" step="0.01" required></div><div class="field"><label>Согласованный способ и реквизиты возврата</label><input name="recipient" maxlength="150" required></div><div class="field"><label>Основание обращения клиента</label><textarea name="text" rows="3" maxlength="5000" required></textarea></div><button class="btn full">Зарезервировать для возврата</button></form>`);break;
          case "notify-client":
            {
                const c = registry.items.find(c => c.id === Number(id));
                openModal(`<h2>Сообщение клиенту ID ${id}</h2><p class="intro">${esc(c?.email || "")} · появится в уведомлениях личного кабинета</p><form id="notify-form" data-id="${id}" class="fields"><div class="field"><label for="notify-text">Текст уведомления</label><textarea id="notify-text" name="text" rows="5" maxlength="5000" required></textarea></div><button class="btn full">Отправить уведомление</button></form>`);
                break;
            }

          case "take":
            await processOperation(id, "take"), await refresh(), orderDetail(findOp(id));
            break;

          case "cancel":
            openModal(`<h2>Отмена заявки № ${id}</h2><form id="cancel-form" data-id="${id}" class="fields"><div class="field"><label for="cancel-text">Причина для клиента</label><textarea id="cancel-text" name="text" rows="4" maxlength="5000" required></textarea></div><button class="btn full">Подтвердить отмену</button></form>`);
            break;

          case "password":
            passwordDialog(!1);
            break;

          case "skip-password":
            await api("password/", {
                skip: !0
            }), firstPrompt = !1, closeModal(), toast("Смену пароля можно выполнить позже в профиле");
            break;

          case "forgot":
            openModal('<h2>Восстановление доступа</h2><form id="forgot-form" class="fields"><div class="field"><label for="forgot-email">Email</label><input id="forgot-email" name="email" type="email" required></div><button class="btn full">Отправить ссылку</button></form>');
            break;

          case "read":
            await api("notifications/read/", {}), await refresh();
            break;

          case "copy-receipt":
            {
                const o = findOp(id), t = `Заявка №${id}\nКлиент ID ${o.clientId}\nСумма ${money(o.amount)}\n${COMPANY.name}\n${COMPANY.bank}\nСчёт ${COMPANY.account}\nБИК ${COMPANY.bik}\nК/с ${COMPANY.corr}\nИНН/КПП ${COMPANY.inn}/${COMPANY.kpp}\nКасса: ${o.cashAddress||cashAddress}\n${purpose(o)}`;
                try {
                    await navigator.clipboard.writeText(t), toast("Реквизиты скопированы");
                } catch {
                    !function(name, content) {
                        const url = URL.createObjectURL(new Blob([ content ], {
                            type: "text/csv;charset=utf-8"
                        })), a = document.createElement("a");
                        a.href = url, a.download = "pina-market-requisites.txt", a.click(), setTimeout(() => URL.revokeObjectURL(url), 1e3);
                    }(0, t);
                }
                break;
            }

          case "mail-login":
            auth("login");
        }
    }(el).catch(e => toast(e.message)));
}), document.addEventListener("change", e=>{if(e.target.id==="process-status"){$("#execution-proof").required=e.target.value==="complete";}}), document.addEventListener("input", e => {
    if(e.target.id==="actual"){
        const f=e.target.form, n=Math.round(Number(e.target.value)*100), different=e.target.value!==""&&n!==Number(f.dataset.requested);
        $("#amount-warning").hidden=!different;$("#amount-verified").required=different;$("#amount-verified").checked=false;
        $("#amount-difference").textContent=`В заявке ${money(Number(f.dataset.requested))}. Будет зачислено ${money(n||0)}. Убедитесь, что сумма поступления корректна.`;
    }
    "search" === e.target.id && (query = e.target.value, $("#catalog-results").innerHTML = filtered()), 
    "order-amount" === e.target.id && function() {
        const s = services.find(s => s.id === $("#order-form").dataset.id), n = Math.round(100 * Number($("#order-amount").value)), fee = Math.floor((n * s.feeBps + 5e3) / 1e4), total = n + fee, valid = Number.isSafeInteger(n) && n >= s.min && n <= s.max;
        $("#quote").innerHTML = `<div class="summary-row"><span>Стоимость продукта</span><b>${money(valid ? n : 0)}</b></div><div class="summary-row"><span>Комиссия ${(s.feeBps / 100).toLocaleString("ru-RU")}%</span><b>${money(valid ? fee : 0)}</b></div><div class="summary-row total"><span>К резервированию</span><b>${money(valid ? total : 0)}</b></div><div class="note">Доступно ${money(available())}${valid && total > available() ? " · Недостаточно средств" : ""}</div>`, 
        $("#order-submit").disabled = !valid || total > available();
    }();
}), document.addEventListener("submit", e => {
    const f = e.target;
    if (!f.id) return;
    if (e.preventDefault(), !f.reportValidity()) return;
    const button = f.querySelector('button[type="submit"],button:not([type])');
    button?.disabled || (button && (button.disabled = !0), async function(f) {
        const d = Object.fromEntries(new FormData(f)), id = f.dataset.id;
        switch (f.id) {
          case "auth-form":
            {
                const email = d.email.trim().toLowerCase();
                if ("register" === f.dataset.mode) {
                    {
                        const r = await api("register/", {
                            email: email,
                            accepted: !0,
                            version: LEGAL_VERSION
                        });
                        openModal(`<h2>Проверьте почту</h2><p class="intro">${esc(r.message)}</p><button class="btn full" data-action="mail-login">Перейти ко входу</button>`);
                    }
                    return;
                }
                {
                    const r = await api("login/", {
                        email: email,
                        password: d.password
                    });
                    csrf = r.csrf, firstPrompt = r.prompt, staff = r.staff;
                }
                await async function(email) {
                    if (sessionEmail = email, signed = !0, await sync(), view = location.pathname.startsWith("/admin") && staff ? "admin" : "overview", 
                    closeModal(), history.replaceState(null,"",view==="admin"?"/admin/":"/cabinet/"), render(), "admin" === view && await loadList(), firstPrompt) passwordDialog(!0); else if (selected) {
                        const id = selected;
                        selected = "", openService(id);
                    }
                }(email);
                break;
            }

          case "password-form":
            if (d.password !== d.repeat) throw Error("Пароли не совпадают");
            await api("password/", {
                password: d.password,
                current: d.current || ""
            }), firstPrompt = !1, closeModal(), toast("Пароль изменён");
            break;

          case "forgot-form":
            {
                const r = await api("forgot/", {
                    email: d.email
                });
                openModal(`<h2>Восстановление доступа</h2><p class="intro">${esc(r.message)}</p><button class="btn full" data-action="auth">Ко входу</button>`);
                break;
            }

          case "reset-password-form":
            if (d.password !== d.repeat) throw Error("Пароли не совпадают");
            {
                const p = new URLSearchParams(location.search);
                await api("reset/", {
                    uid: p.get("uid"),
                    token: p.get("token"),
                    password: d.password
                }), history.replaceState(null, "", "/login/"), toast("Пароль изменён"), auth();
                break;
            }

          case "cash-form":
            {
                let o;
                const key = f.dataset.key || (f.dataset.key = uid());
                o = await api("deposits/", {
                    amount: d.amount,
                    key: key
                }), await sync(), render(), receipt(o);
                break;
            }

          case "order-form":
            {
                const s = services.find(s => s.id === id), n = Math.round(100 * Number(d.amount)), total = n + Math.floor((n * s.feeBps + 5e3) / 1e4), key = f.dataset.key || (f.dataset.key = uid());
                if (total > available()) throw Error("Недостаточно средств");
                await api("orders/", {
                    service: id,
                    amount: d.amount,
                    region: d.region,
                    recipient: d.recipient,
                    key: key,
                    version: s.version,
                legalVersion:LEGAL_VERSION,accepted:true,
                    total: total
                }), closeModal(), await sync(), await go("operations"), toast("Заявка создана, сумма зарезервирована");
                break;
            }

          case "confirm-form":
            await processOperation(id, "confirm", {
                amount: d.amount,
                bankref: d.bankref.trim(), text:d.text||"", amountVerified:d.amountVerified==="on"
            }), closeModal(), await refresh(), toast("Поступление подтверждено. Баланс клиента увеличен.");
            break;

          case "refund-complete-form":
            await processOperation(id,"complete",{text:d.text,bankref:d.bankref});closeModal();await refresh();toast("Банковский возврат учтён");break;
          case "process-form":
            await processOperation(id, d.action, {
                text: d.text.trim(),bankref:d.bankref||"",proof:d.proof||""
            }), closeModal(), await refresh(), toast("clarify" === d.action ? "Клиенту отправлен запрос на уточнение" : "Заказ выполнен, резерв списан");
            break;

          case "cancel-form":
            await processOperation(id, "cancel", {
                text: d.text.trim()
            }), closeModal(), await refresh(), toast("Заявка отменена");
            break;

          case "reply-form":
            await api("orders/" + id + "/reply/", {
                text: d.text
            }), closeModal(), await refresh(), toast("Ответ передан оператору");
            break;

          case "adjust-form":
            await api("admin/clients/"+id+"/adjust/",{...d,verified:d.verified==="on",key:f.dataset.key||(f.dataset.key=uid())});closeModal();await refresh();toast("Операция проведена. Баланс обновлён.");break;
          case "refund-form":
            await api("admin/clients/"+id+"/refund/",{...d,key:f.dataset.key||(f.dataset.key=uid())});closeModal();await refresh();toast("Возврат создан. Обработайте его в разделе «Возвраты».");break;
          case "notify-form":
            await api("admin/clients/" + id + "/notify/", {
                text: d.text
            }), closeModal(), await refresh(), toast("Уведомление отправлено в кабинет клиента");
            break;

          case "settings-form": {
        const v = await api("admin/settings/",{cashEnabled:d.cashEnabled === "on",cashAddress:d.cashAddress});cashEnabled=v.cashEnabled;cashAddress=v.cashAddress;toast("Настройки сохранены");break;
      }
      case "service-form":
            {
                const values = {
                    status: d.status,
                    product: d.product,
                    desc: d.desc,
                    instructions: d.instructions,
                    conditions: d.conditions,
                    regions: d.regions.split("\n").map(s => s.trim()).filter(Boolean),
                    feeBps: Math.round(100 * Number(d.fee)),
                    min: d.min,
                    max: d.max
                };
                if (Number(d.min) > Number(d.max)) throw Error("Минимум не может быть больше максимума");
                if ("available" === values.status && !values.regions.length) throw Error("Для доступного сервиса укажите регионы");
                const s = services.find(s => s.id === id);
                await api("admin/services/" + id + "/", {
                    ...values,
                    version: s.version
                }), closeModal(), await refresh(), toast("Настройки сервиса сохранены");
                break;
            }

          case "filters-form":
            if (listFilters = d, currentPage = 1, d.from && d.to && d.from > d.to) throw Error("Начальная дата позже конечной");
            if (d.min && d.max && Number(d.min) > Number(d.max)) throw Error("Минимальная сумма больше максимальной");
            await loadList();
            break;

          case "client-search":
            listFilters = d, currentPage = 1, await loadList();
        }
    }(f).catch(e => toast(e.message)).finally(() => {
        button?.isConnected && (button.disabled = !1);
    }));
}), document.addEventListener("keydown", e => {
    if (!$("#modal").hidden && ("Escape" === e.key && closeModal(), "Tab" === e.key)) {
        const a = [ ...$("#modal").querySelectorAll("button,a,input,select,textarea") ].filter(el => !el.disabled);
        e.shiftKey && document.activeElement === a[0] ? (e.preventDefault(), a.at(-1)?.focus()) : e.shiftKey || document.activeElement !== a.at(-1) || (e.preventDefault(), 
        a[0]?.focus());
    }
}), $("#modal").addEventListener("click", e => {
    e.target === $("#modal") && closeModal();
}), (async () => {
    try {
        {
            const c = await api("config/");
            csrf = c.csrf, await sync();
        }
        render(), location.pathname.startsWith("/admin") ? signed && staff ? (view = "admin", 
        render(), await loadList()) : signed ? $("#app").innerHTML = '<div class="wrap"><div class="panel" style="margin:60px 0"><h1>Доступ ограничен</h1><p>Для входа в этот раздел нужна учётная запись сотрудника.</p><a class="btn" href="/cabinet/">Личный кабинет</a></div></div>' : auth() : location.pathname.startsWith("/register") ? auth("register") : (location.pathname.startsWith("/login") || location.pathname.startsWith("/cabinet") && !signed) ? auth() : location.pathname.startsWith("/reset") ? openModal('<h2>Новый пароль</h2><form id="reset-password-form" class="fields"><div class="field"><label>Новый пароль</label><input name="password" type="password" minlength="12" autocomplete="new-password" required></div><div class="field"><label>Повторите пароль</label><input name="repeat" type="password" minlength="12" autocomplete="new-password" required></div><button class="btn full">Сохранить пароль</button></form>') : signed && firstPrompt && passwordDialog(!0);
    } catch (e) {
        $("#app").innerHTML = `<div class="wrap"><div class="panel" style="margin:60px auto;max-width:600px"><h1>Сервис временно недоступен</h1><p>Не удалось подключиться к серверу. Ваши средства и заявки не изменены. Попробуйте обновить страницу.</p><a class="btn" href="${esc(location.pathname + location.search)}">Повторить загрузку</a><p><a href="/documents/">Документы</a> · <a href="mailto:mail@pina.market">mail@pina.market</a></p></div></div>`,
        toast(e.message);
    }
})();
function settingsHTML(){return `<section class="panel"><h2>Внесение наличных</h2><form id="settings-form" class="fields"><div class="field"><label for="cash-address">Подтверждённый адрес кассы и часы работы</label><textarea id="cash-address" name="cashAddress" maxlength="1000" rows="4">${esc(cashAddress)}</textarea></div><label class="checkline"><input name="cashEnabled" type="checkbox" ${cashEnabled?'checked':''}><span>Принимать заявки на внесение наличных</span></label><p class="note">Включайте способ после подтверждения адреса и условий банком. Адрес появится в заявках клиентов.</p><button class="btn">Сохранить</button></form></section>`;}

setInterval(async()=>{if(!signed||document.hidden||!$("#modal").hidden||document.activeElement?.matches("input,textarea,select"))return;try{await refresh()}catch{}},30000);
