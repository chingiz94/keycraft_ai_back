import { api, session, ApiError } from './api.js';
import { escapeHtml as esc, money, types, options, field, select, errorSlot, title, empty, pages, notify, showError, submitForm, safeLink } from './ui.js';
import { registerBuildViews } from './builds.js';
import { registerOrderViews } from './orders.js';
import { registerAdminViews } from './admin.js';

const state = { user: null, renderId: 0 };
const main = document.querySelector('#main');
document.querySelector('.skip').addEventListener('click', event => { event.preventDefault(); main.focus(); });
const views = {};
const actions = {};
const forms = {};

export const context = {
  get user() { return state.user; },
  api, views, actions, forms, refresh: render,
  navigate: route => { location.hash = route; },
  setUser: user => { state.user = user; navigation(); },
};

function navigation() {
  const route = location.hash.split('?')[0];
  const links = [['#catalog', 'Каталог']];
  if (state.user?.role === 'buyer') links.push(['#builds', 'Мои сборки'], ['#orders', 'Заказы'], ['#ai', 'ИИ-подбор']);
  if (state.user?.role === 'admin') links.push(['#admin/components', 'Управление'], ['#orders', 'Заказы']);
  document.querySelector('#navigation').innerHTML = links.map(([url, text]) => `<a href="${url}" class="${route === url ? 'active' : ''}">${text}</a>`).join('');
  document.querySelector('#account').innerHTML = state.user
    ? `<div>${esc(state.user.name)}<small>${state.user.role === 'admin' ? 'Администратор' : 'Покупатель'}</small></div><button class="secondary small" data-action="logout">Выйти</button>`
    : '<a href="#login">Войти</a><a class="button secondary" href="#register">Регистрация</a>';
}

function authView(register = false) {
  return `<div class="auth-layout"><div class="auth-intro"><p class="eyebrow">Ваша клавиатура</p><h1>Соберите то,<br>что подходит вам.</h1><p>Подберите детали, проверьте совместимость и сохраните сборку. KeyCraft поможет перейти от идеи к готовой клавиатуре.</p></div><section class="panel"><h2>${register ? 'Создать аккаунт' : 'С возвращением'}</h2><p class="muted">${register ? 'Новый аккаунт получает роль покупателя.' : 'Войдите, чтобы продолжить свою сборку.'}</p><form data-form="${register ? 'register' : 'login'}" class="auth-form">${register ? field('name', 'Имя', '', 'required maxlength="100" autocomplete="name"') : ''}${field('email', 'Email', '', 'required maxlength="255" autocomplete="email"', 'email')}${field('password', 'Пароль', '', `required minlength="8" autocomplete="${register ? 'new-password' : 'current-password'}"`, 'password')}${register ? field('password_confirmation', 'Повторите пароль', '', 'required minlength="8" autocomplete="new-password"', 'password') : ''}${errorSlot}<button type="submit">${register ? 'Зарегистрироваться' : 'Войти'}</button><p class="switch">${register ? 'Уже есть аккаунт? <a href="#login">Войти</a>' : 'Первый раз здесь? <a href="#register">Зарегистрироваться</a>'}</p></form></section></div>`;
}

async function authenticate(data, endpoint) {
  if (endpoint === 'register' && data.password !== data.password_confirmation) {
    throw new ApiError('Пароли не совпадают.', 422, { password_confirmation: ['Повторите введённый пароль.'] });
  }
  const response = await api(`auth/${endpoint}`, { method: 'POST', body: data, authenticated: false });
  session.save(response.token);
  state.user = (await api('auth/me')).data;
  navigation();
  notify(`Вы вошли как ${state.user.name}.`);
  location.hash = state.user.role === 'admin' ? '#admin/components' : '#builds';
}
forms.login = data => authenticate(data, 'login');
forms.register = data => authenticate(data, 'register');
actions.logout = async () => {
  await api('auth/logout', { method: 'POST' });
  session.clear(); state.user = null; navigation();
  notify('Вы вышли из аккаунта.'); location.hash = '#login';
};

const keyboard = `<div class="keyboard" aria-hidden="true">${Array.from('QWERTYUIOPASDFGHJKL;ZXCVBNM,./').map((letter, index) => `<span class="key ${index === 0 || index > 26 ? 'accent' : ''}">${letter}</span>`).join('')}<span class="key">⌘</span><span class="key">⌥</span><span class="key space"></span><span class="key">◀</span><span class="key">▼</span><span class="key accent">▶</span></div>`;

views.catalog = async params => {
  const query = new URLSearchParams(params);
  if (!query.has('per_page')) query.set('per_page', '6');
  const result = await api(`components?${query}`, { authenticated: false });
  const paramsObject = Object.fromEntries(params);
  const routeForPage = page => { const next = new URLSearchParams(query); next.set('page', page); return `#catalog?${esc(next.toString())}`; };
  return `<section class="hero"><div class="hero-copy"><p class="eyebrow">Детали имеют значение</p><h1>Ваша клавиатура.<br>Ваш характер.</h1><p>Корпуса, платы и свитчи для сборки, которой приятно пользоваться каждый день.</p><a class="button" href="${state.user?.role === 'buyer' ? '#builds' : '#register'}">Начать сборку</a></div>${keyboard}</section><div class="catalog-layout"><aside class="filters"><h2>Подобрать детали</h2><form data-form="catalog">${field('search', 'Поиск', paramsObject.search || '', 'placeholder="Название детали" maxlength="100"')}${select('type', 'Компонент', { '': 'Все компоненты', ...types }, paramsObject.type)}${select('format', 'Формат', { '': 'Любой формат', 65: '65%', 75: '75%', 60: '60%', 80: 'TKL / 80%', 100: 'Полноразмерный' }, paramsObject.format)}<div class="inline-fields">${field('min_price', 'Цена от, ₸', params.has('min_price') ? Number(paramsObject.min_price) / 100 : '', 'min="0" step="0.01"', 'number')}${field('max_price', 'до, ₸', params.has('max_price') ? Number(paramsObject.max_price) / 100 : '', 'min="0" step="0.01"', 'number')}</div>${select('sort_order', 'Сортировка', { 'name:asc': 'По названию', 'price:asc': 'Сначала дешевле', 'price:desc': 'Сначала дороже', 'created_at:desc': 'Сначала новые' }, `${paramsObject.sort || 'name'}:${paramsObject.order || 'asc'}`)}<label class="check"><input type="checkbox" name="in_stock" value="1" ${paramsObject.in_stock === '1' ? 'checked' : ''}>Только в наличии</label>${errorSlot}<div class="actions"><button type="submit">Применить</button><a href="#catalog">Сбросить</a></div></form></aside><section><div class="catalog-toolbar"><h2>Каталог компонентов</h2><small>Найдено: ${result.meta.total}</small></div><div class="cards">${result.data.map(component => `<article class="card"><div class="part-visual" aria-hidden="true">${({ case: '[ ▱ ]', pcb: '▤', plate: '⊞', stabilizer: '⊓ ⊓', switch: '▣', keycap: '⌨' })[component.type] || '⌨'}</div><div><span class="tag">${types[component.type]}${component.format ? ` · ${esc(component.format)}%` : ''}</span></div><h3>${esc(component.name)}</h3><div class="price">${money(component.price)}</div><div class="card-meta"><span>${component.stock ? `В наличии: ${component.stock}` : 'Нет в наличии'}</span><span>#${component.id}</span></div><div class="actions"><button class="secondary small" data-action="component" data-id="${component.id}">Подробнее</button>${state.user?.role === 'admin' ? `<a href="#admin/component/${component.id}">Изменить</a>` : ''}</div></article>`).join('')}</div>${result.data.length ? pages(result.meta, routeForPage) : empty('По этим параметрам ничего не найдено. Измените фильтры.')}</section></div>`;
};

forms.catalog = data => {
  const params = new URLSearchParams();
  for (const key of ['search', 'type', 'format', 'in_stock']) if (data[key]) params.set(key, data[key]);
  for (const key of ['min_price', 'max_price']) if (data[key] !== '') params.set(key, Math.round(Number(data[key]) * 100));
  const [sort, order] = data.sort_order.split(':'); params.set('sort', sort); params.set('order', order);
  location.hash = `#catalog?${params}`;
};

actions.component = async button => {
  const component = (await api(`components/${button.dataset.id}`, { authenticated: false })).data;
  const offers = component.offers || (await api(`components/${component.id}/offers`, { authenticated: false })).data;
  document.querySelector('#detail-content').innerHTML = `<p class="eyebrow">${types[component.type]}</p><h2 id="detail-title">${esc(component.name)}</h2><p class="price">${money(component.price)}</p><p class="muted">Формат: ${esc(component.format || 'универсальный')} · В наличии: ${component.stock}</p><h3>Характеристики</h3><dl class="specs">${Object.entries(component.specs).map(([key, value]) => `<dt>${esc(key)}</dt><dd>${esc(typeof value === 'object' ? JSON.stringify(value) : value)}</dd>`).join('')}</dl><h3>Предложения магазинов</h3>${offers.length ? offers.map(offer => `<p><a href="${safeLink(offer.url)}" target="_blank" rel="noopener noreferrer">${esc(offer.marketplace)}</a> · ${money(offer.price)}<br><small>Обновлено: ${esc(new Date(offer.price_updated_at).toLocaleString('ru-RU'))}</small></p>`).join('') : '<p class="muted">Предложений пока нет.</p>'}${state.user?.role === 'buyer' ? '<p><a class="button" href="#builds" data-action="close-detail">Перейти к моим сборкам</a></p>' : ''}`;
  document.querySelector('#detail-dialog').showModal();
};
actions['close-detail'] = () => document.querySelector('#detail-dialog').close();

views.login = async () => authView();
views.register = async () => authView(true);

async function render() {
  const renderId = ++state.renderId;
  const [route, query = ''] = (location.hash.slice(1) || 'catalog').split('?');
  const [section, ...parts] = route.split('/');
  navigation();
  if (!['catalog', 'login', 'register'].includes(section) && !state.user) { location.hash = '#login'; return; }
  if (section === 'admin' && state.user?.role !== 'admin') { main.innerHTML = title('Доступ запрещён', 'Управление доступно только администратору.'); return; }
  main.setAttribute('aria-busy', 'true');
  main.innerHTML = '<p class="loading">Загрузка…</p>';
  try {
    const view = views[section];
    const content = view ? await view(new URLSearchParams(query), parts) : title('Страница не найдена');
    if (renderId === state.renderId) main.innerHTML = content;
  } catch (error) {
    if (renderId === state.renderId) { main.innerHTML = title('Не удалось загрузить страницу', error.message, '<button data-action="retry">Повторить</button>'); showError(error); }
  } finally { if (renderId === state.renderId) main.removeAttribute('aria-busy'); }
}

actions.retry = render;
document.addEventListener('click', async event => {
  const button = event.target.closest('[data-action]');
  if (!button || button.disabled) return;
  if (button.tagName !== 'A') event.preventDefault();
  const handler = actions[button.dataset.action];
  if (!handler) return;
  button.disabled = true;
  try { await handler(button); } catch (error) { showError(error); }
  finally { button.disabled = button.dataset.locked === 'true'; }
});
document.addEventListener('submit', event => {
  const form = event.target.closest('form[data-form]');
  if (!form) return;
  event.preventDefault();
  const handler = forms[form.dataset.form];
  if (handler) submitForm(form, data => handler(data, form));
});
window.addEventListener('hashchange', render);
window.addEventListener('session-expired', () => {
  state.user = null; navigation();
  notify('Сессия завершилась. Войдите снова.', true);
  location.hash = '#login';
});

async function start() {
  if (session.token()) {
    try { state.user = (await api('auth/me')).data; }
    catch (error) { if (error.status !== 401) showError(error); }
  }
  await render();
}
registerBuildViews(context);
registerOrderViews(context);
registerAdminViews(context);
await start();
