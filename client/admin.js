import { api, allComponents, ApiError } from './api.js';
import { escapeHtml as esc, money, types, statuses, field, select, options, errorSlot, title, empty, pages, notify, confirmDelete, safeLink } from './ui.js';

export function registerAdminViews(context) {
  const { views, forms, actions } = context;
  const tabs = section => `<nav class="admin-tabs" aria-label="Управление">${Object.entries({ components: 'Компоненты', offers: 'Предложения', users: 'Пользователи', statistics: 'Статистика' }).map(([key, label]) => `<a class="${section === key ? 'active' : ''}" href="#admin/${key}">${label}</a>`).join('')}</nav>`;
  views.admin = async (params, [section = 'components', id]) => {
    const heading = `${title('Управление', 'Каталог, предложения магазинов и доступ пользователей.')}${tabs(section)}`;
    if (section === 'component') {
      const component = id === 'new' ? { name: '', type: 'case', format: '65', price: '', stock: 0, specs: { mount: 'gasket' } } : (await api(`admin/components/${Number(id)}`)).data;
      return `${heading}<section class="panel"><h2>${id === 'new' ? 'Новый компонент' : `Изменить компонент №${id}`}</h2><form data-form="component" data-id="${esc(id)}"><div class="inline-fields">${field('name', 'Название', component.name, 'required maxlength="150"')}${select('type', 'Тип компонента', types, component.type)}${field('format', 'Формат, % (пусто для универсального)', component.format || '', 'maxlength="20"')}${field('price', 'Цена, ₸', component.price === '' ? '' : component.price / 100, 'required min="0.01" max="1000000" step="0.01"', 'number')}${field('stock', 'Остаток, шт.', component.stock, 'required min="0" max="1000000" step="1"', 'number')}<label class="full">Характеристики (JSON)<textarea name="specs" required rows="8">${esc(JSON.stringify(component.specs, null, 2))}</textarea><small>Допустимы mount, switch_type, stem, layout, key_count и stabilizers. Неполные характеристики блокируют оформление сборки.</small></label></div>${errorSlot}<div class="actions"><button type="submit">Сохранить компонент</button><a href="#admin/components">Отмена</a></div></form></section>`;
    }
    if (section === 'components') {
      const result = await api(`admin/components?per_page=8&page=${Number(params.get('page')) || 1}`);
      return `${heading}<section class="panel"><div class="row-heading"><h2>Каталог</h2><a class="button" href="#admin/component/new">Добавить компонент</a></div><div class="table-wrap"><table><thead><tr><th>Название</th><th>Тип</th><th>Цена</th><th>Остаток</th><th>Действия</th></tr></thead><tbody>${result.data.map(component => `<tr><td>${esc(component.name)}<br><small>#${component.id} · ${esc(component.format || 'универсальный')}</small></td><td>${types[component.type]}</td><td>${money(component.price)}</td><td>${component.stock}</td><td><div class="actions"><a href="#admin/component/${component.id}">Изменить</a><button class="secondary small" data-action="delete-component" data-id="${component.id}">Удалить</button></div></td></tr>`).join('')}</tbody></table></div>${pages(result.meta, page => `#admin/components?page=${page}`)}</section>`;
    }
    if (section === 'offer') {
      const [catalog, response] = await Promise.all([allComponents(), id === 'new' ? Promise.resolve(null) : api(`admin/offers/${Number(id)}`)]);
      const offer = response?.data || { marketplace: '', url: '', price: '', component_id: '', price_updated_at: new Date().toISOString() };
      const localDate = new Date(new Date(offer.price_updated_at).getTime() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
      return `${heading}<section class="panel"><h2>${id === 'new' ? 'Новое предложение' : `Изменить предложение №${id}`}</h2><form data-form="offer" data-id="${esc(id)}"><label>Компонент<select name="component_id" required><option value="">Выберите компонент</option>${options(Object.fromEntries(catalog.map(component => [component.id, component.name])), offer.component_id)}</select></label><div class="inline-fields">${field('marketplace', 'Магазин', offer.marketplace, 'required maxlength="100"')}${field('price', 'Цена, ₸', offer.price === '' ? '' : offer.price / 100, 'required min="0.01" max="1000000" step="0.01"', 'number')}${field('url', 'Ссылка HTTPS', offer.url, 'required maxlength="2000" pattern="https://.*"', 'url')}${field('price_updated_at', 'Когда проверена цена', localDate, 'required', 'datetime-local')}</div>${errorSlot}<div class="actions"><button type="submit">Сохранить предложение</button><a href="#admin/offers">Отмена</a></div></form></section>`;
    }
    if (section === 'offers') {
      const result = await api(`admin/offers?per_page=8&page=${Number(params.get('page')) || 1}`);
      return `${heading}<section class="panel"><div class="row-heading"><h2>Предложения магазинов</h2><a class="button" href="#admin/offer/new">Добавить предложение</a></div>${result.data.length ? `<div class="table-wrap"><table><thead><tr><th>Магазин</th><th>Компонент</th><th>Цена</th><th>Обновлено</th><th>Действия</th></tr></thead><tbody>${result.data.map(offer => `<tr><td><a href="${safeLink(offer.url)}" target="_blank" rel="noopener noreferrer">${esc(offer.marketplace)}</a></td><td>#${offer.component_id}</td><td>${money(offer.price)}</td><td>${esc(new Date(offer.price_updated_at).toLocaleDateString('ru-RU'))}</td><td><div class="actions"><a href="#admin/offer/${offer.id}">Изменить</a><button class="secondary small" data-action="delete-offer" data-id="${offer.id}">Удалить</button></div></td></tr>`).join('')}</tbody></table></div>` : empty('Предложений пока нет.')}${pages(result.meta, page => `#admin/offers?page=${page}`)}</section>`;
    }
    if (section === 'users') {
      const result = await api(`admin/users?per_page=8&page=${Number(params.get('page')) || 1}`);
      return `${heading}<section class="panel"><h2>Пользователи</h2><p class="muted">Последнего администратора нельзя перевести в покупатели.</p><div class="table-wrap"><table><thead><tr><th>Имя</th><th>Email</th><th>Роль</th></tr></thead><tbody>${result.data.map(user => `<tr><td>${esc(user.name)}${user.id === context.user.id ? ' (вы)' : ''}</td><td>${esc(user.email)}</td><td><form data-form="user-role" data-id="${user.id}" class="actions"><label><span class="muted">Роль ${esc(user.name)}</span><select name="role">${options({ buyer: 'Покупатель', admin: 'Администратор' }, user.role)}</select></label><button class="secondary small" type="submit">Сохранить</button>${errorSlot}</form></td></tr>`).join('')}</tbody></table></div>${pages(result.meta, page => `#admin/users?page=${page}`)}</section>`;
    }
    if (section === 'statistics') {
      const data = (await api('admin/statistics')).data;
      return `${heading}<section class="panel"><h2>Состояние заказов</h2><p class="muted">Статистика рассчитывается по данным сервера.</p><div class="stats"><div class="stat"><span class="muted">Всего заказов</span><strong>${data.orders_by_status.reduce((sum, row) => sum + Number(row.count), 0)}</strong></div><div class="stat"><span class="muted">Выручка по выданным</span><strong>${money(data.issued_revenue)}</strong></div></div><div class="table-wrap"><table><thead><tr><th>Статус</th><th>Заказов</th><th>Сумма</th></tr></thead><tbody>${data.orders_by_status.map(row => `<tr><td>${statuses[row.status]}</td><td>${row.count}</td><td>${money(row.total)}</td></tr>`).join('')}</tbody></table></div></section>`;
    }
    return title('Раздел не найден');
  };

  forms.component = async (data, form) => {
    let specs;
    try { specs = JSON.parse(data.specs); if (!specs || Array.isArray(specs) || typeof specs !== 'object') throw new Error(); }
    catch { throw new ApiError('Проверьте характеристики.', 422, { specs: ['Введите объект JSON, например {"mount":"gasket"}.'] }); }
    const create = form.dataset.id === 'new';
    await api(`admin/components${create ? '' : `/${form.dataset.id}`}`, { method: create ? 'POST' : 'PATCH', body: { name: data.name, type: data.type, format: data.format || null, price: Math.round(Number(data.price) * 100), stock: Number(data.stock), specs } });
    notify('Компонент сохранён.'); context.navigate('#admin/components');
  };
  forms.offer = async (data, form) => {
    const create = form.dataset.id === 'new';
    await api(`admin/offers${create ? '' : `/${form.dataset.id}`}`, { method: create ? 'POST' : 'PATCH', body: { component_id: Number(data.component_id), marketplace: data.marketplace, url: data.url, price: Math.round(Number(data.price) * 100), price_updated_at: new Date(data.price_updated_at).toISOString() } });
    notify('Предложение сохранено.'); context.navigate('#admin/offers');
  };
  forms['user-role'] = async (data, form) => {
    const result = await api(`admin/users/${form.dataset.id}/role`, { method: 'PATCH', body: { role: data.role } });
    notify('Роль пользователя обновлена.');
    if (result.data.id === context.user.id) { context.setUser(result.data); if (result.data.role !== 'admin') { context.navigate('#builds'); return; } }
    await context.refresh();
  };
  actions['delete-component'] = async button => {
    if (!await confirmDelete('Удалить компонент? Сервер запретит удаление детали, которая используется в сборках или заказах.')) return;
    await api(`admin/components/${button.dataset.id}`, { method: 'DELETE' });
    notify('Компонент удалён.'); await context.refresh();
  };
  actions['delete-offer'] = async button => {
    if (!await confirmDelete('Удалить предложение магазина?')) return;
    await api(`admin/offers/${button.dataset.id}`, { method: 'DELETE' });
    notify('Предложение удалено.'); await context.refresh();
  };
}
