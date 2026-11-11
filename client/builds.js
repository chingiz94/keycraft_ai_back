import { api, allComponents } from './api.js';
import { escapeHtml as esc, money, types, options, field, select, errorSlot, title, empty, pages, notify, confirmDelete } from './ui.js';

export function registerBuildViews(context) {
  const { views, forms, actions } = context;
  let analysis = null;
  let currentBuild = null;
  let recommended = null;
  let applyingRecommendation = false;
  const checkoutKeyName = () => `keycraft.checkout.${context.user.id}.${currentBuild.id}`;
  const clearCheckout = () => { if (currentBuild) sessionStorage.removeItem(checkoutKeyName()); analysis = null; };
  const requireBuyer = () => {
    if (context.user.role !== 'buyer') throw new Error('Сборки и ИИ-подбор доступны покупателю.');
  };

  views.builds = async params => {
    requireBuyer();
    const result = await api(`builds?per_page=6&page=${Number(params.get('page')) || 1}`);
    return `${title('Мои сборки', 'Сохраните идею, добавьте компоненты и проверьте их совместимость.')}<div class="split"><section class="panel"><h2>Сохранённые сборки</h2>${result.data.length ? result.data.map(build => `<article class="list-row"><div class="row-heading"><div><h3><a href="#build/${build.id}">${esc(build.name)}</a></h3><p class="muted">Бюджет: ${money(build.budget)} · Деталей: ${build.items?.length || 0}</p></div><a class="button secondary" href="#build/${build.id}">Открыть</a></div></article>`).join('') : empty('Сборок пока нет. Создайте первую.')}${pages(result.meta, page => `#builds?page=${page}`)}</section><section class="panel"><h2>Новая сборка</h2><form data-form="create-build">${field('name', 'Название', '', 'required maxlength="150" placeholder="Например, тихая клавиатура 65%"')}${field('budget', 'Бюджет, ₸', 100000, 'required min="0.01" max="1000000" step="0.01"', 'number')}${errorSlot}<button type="submit">Создать сборку</button></form></section></div>`;
  };
  forms['create-build'] = async data => {
    const result = await api('builds', { method: 'POST', body: { name: data.name, budget: Math.round(Number(data.budget) * 100) } });
    notify('Сборка создана. Добавьте компоненты.');
    context.navigate(`#build/${result.data.id}`);
  };

  views.build = async (params, [id]) => {
    requireBuyer();
    const [response, catalog] = await Promise.all([api(`builds/${Number(id)}`), allComponents()]);
    currentBuild = response.data;
    analysis = null;
    const build = currentBuild;
    const available = catalog.filter(component => !build.items.some(item => item.component_id === component.id));
    return `${title(build.name, 'Цены рассчитает сервер. Заказ сохранит стоимость и зарезервирует остатки.', '<a href="#builds">← Все сборки</a>')}<div class="split"><div><section class="panel"><h2>Состав сборки</h2>${build.items.length ? build.items.map(item => `<div class="item-row"><div><h3>${esc(item.component.name)}</h3><small>${types[item.component.type]} · ${money(item.component.price)} / шт.</small></div><form data-form="item-quantity" data-build="${build.id}" data-id="${item.id}"><label><span class="muted">Количество</span><input type="number" name="quantity" aria-label="Количество ${esc(item.component.name)}" value="${item.quantity}" min="1" max="1000" required></label><button class="secondary small" type="submit">Сохранить</button><button class="secondary small" type="button" data-action="delete-item" data-build="${build.id}" data-id="${item.id}" aria-label="Убрать ${esc(item.component.name)}">×</button>${errorSlot}</form></div>`).join('') : empty('В сборке нет деталей. Добавьте их из каталога.')}</section><section class="panel"><h2>Добавить компонент</h2><form data-form="add-item" data-build="${build.id}"><label>Компонент<select name="component_id" required><option value="">Выберите деталь из каталога</option>${available.map(component => `<option value="${component.id}">${esc(types[component.type])}: ${esc(component.name)} · ${money(component.price)} · остаток ${component.stock}</option>`).join('')}</select></label>${field('quantity', 'Количество', 1, 'required min="1" max="1000"', 'number')}${errorSlot}<button type="submit" ${available.length ? '' : 'disabled'}>Добавить</button></form></section></div><div><section class="panel"><h2>Параметры сборки</h2><form data-form="edit-build" data-id="${build.id}">${field('name', 'Название', build.name, 'required maxlength="150"')}${field('budget', 'Бюджет, ₸', build.budget / 100, 'required min="0.01" max="1000000" step="0.01"', 'number')}${errorSlot}<div class="actions"><button class="secondary" type="submit">Сохранить</button><button class="secondary" type="button" data-action="delete-build" data-id="${build.id}">Удалить сборку</button></div></form></section><section class="panel"><h2>Проверка и заказ</h2><p class="muted">Нужны шесть типов деталей. Количество свитчей должно покрывать все клавиши платы.</p><button class="secondary" data-action="validate-build" data-id="${build.id}">Проверить совместимость</button><div id="compatibility-result" aria-live="polite"></div><button data-action="checkout" data-id="${build.id}" disabled>Оформить заказ</button><p><small>Списание происходит один раз. Повтор после сбоя соединения использует тот же ключ запроса.</small></p></section></div></div>`;
  };
  forms['edit-build'] = async (data, form) => {
    await api(`builds/${form.dataset.id}`, { method: 'PATCH', body: { name: data.name, budget: Math.round(Number(data.budget) * 100) } });
    clearCheckout(); notify('Параметры сохранены.'); await context.refresh();
  };
  forms['add-item'] = async (data, form) => {
    await api(`builds/${form.dataset.build}/items`, { method: 'POST', body: { component_id: Number(data.component_id), quantity: Number(data.quantity) } });
    clearCheckout(); notify('Компонент добавлен.'); await context.refresh();
  };
  forms['item-quantity'] = async (data, form) => {
    await api(`builds/${form.dataset.build}/items/${form.dataset.id}`, { method: 'PATCH', body: { quantity: Number(data.quantity) } });
    clearCheckout(); notify('Количество обновлено.'); await context.refresh();
  };
  actions['delete-item'] = async button => {
    if (!await confirmDelete('Убрать этот компонент из сборки?')) return;
    await api(`builds/${button.dataset.build}/items/${button.dataset.id}`, { method: 'DELETE' });
    clearCheckout(); notify('Компонент убран.'); await context.refresh();
  };
  actions['delete-build'] = async button => {
    if (!await confirmDelete('Удалить сборку и её состав? Ранее оформленные заказы сохранятся.')) return;
    await api(`builds/${button.dataset.id}`, { method: 'DELETE' });
    clearCheckout(); notify('Сборка удалена.'); context.navigate('#builds');
  };
  actions['validate-build'] = async button => {
    analysis = (await api(`builds/${button.dataset.id}/validate`, { method: 'POST' })).data;
    const valid = analysis.compatible && analysis.complete;
    document.querySelector('#compatibility-result').innerHTML = `<div class="result ${valid ? '' : 'invalid'}"><strong>${valid ? 'Компоненты совместимы' : 'Сборку нужно дополнить или изменить'}</strong>${analysis.issues.length || analysis.unverified.length ? `<ul>${[...analysis.issues, ...analysis.unverified].map(issue => `<li>${esc(issue)}</li>`).join('')}</ul>` : '<p>Все шесть типов деталей проверены.</p>'}<div class="price">${money(analysis.total)}</div>${analysis.total > currentBuild.budget ? '<p>Сумма выше указанного бюджета.</p>' : ''}</div>`;
    document.querySelector('[data-action="checkout"]').disabled = !valid;
    document.querySelector('[data-action="checkout"]').dataset.locked = String(!valid);
  };
  actions.checkout = async () => {
    if (!analysis?.compatible || !analysis.complete) return;
    const body = { build_id: currentBuild.id, expected_total: analysis.total };
    let saved;
    try { saved = JSON.parse(sessionStorage.getItem(checkoutKeyName())); } catch { saved = null; }
    if (!saved || saved.body.expected_total !== body.expected_total) {
      saved = { key: crypto.randomUUID(), body }; sessionStorage.setItem(checkoutKeyName(), JSON.stringify(saved));
    }
    try {
      const result = await api('orders', { method: 'POST', body: saved.body, headers: { 'Idempotency-Key': saved.key } });
      sessionStorage.removeItem(checkoutKeyName());
      notify(`Заказ №${result.data.id} оформлен. Сумма: ${money(result.data.total)}.`);
      context.navigate(`#order/${result.data.id}`);
    } catch (error) {
      if (error.status && error.status < 500) { sessionStorage.removeItem(checkoutKeyName()); analysis = null; document.querySelector('[data-action="checkout"]').dataset.locked = 'true'; }
      throw error;
    }
  };

  views.ai = async () => {
    requireBuyer();
    recommended = null;
    return `${title('Подбор с помощью ИИ', 'Опишите желаемый звук и ощущения. Сервер проверит ответ модели по каталогу и правилам совместимости.')}<div class="split"><section class="panel"><h2>Что вы хотите собрать?</h2><form data-form="recommend">${field('budget', 'Бюджет, ₸', 100000, 'required min="0.01" max="1000000" step="0.01"', 'number')}${select('format', 'Формат клавиатуры', {65: '65%', 75: '75%'}, '65')}<label>Предпочтения<textarea name="preferences" maxlength="1000" placeholder="Тихая клавиатура для программирования"></textarea></label>${errorSlot}<button type="submit">Подобрать компоненты</button><p><small>Запрос может занять до 30 секунд. Рекомендация не создаёт заказ.</small></p></form></section><section id="ai-result" class="panel" aria-live="polite"><h2>Рекомендация</h2>${empty('Здесь появится подбор из доступного каталога.')}</section></div>`;
  };
  forms.recommend = async data => {
    const result = await api('ai/recommendations', { method: 'POST', body: { budget: Math.round(Number(data.budget) * 100), format: data.format, preferences: data.preferences } });
    recommended = { ...result.data, budget: Math.round(Number(data.budget) * 100) };
    const catalog = await allComponents();
    document.querySelector('#ai-result').innerHTML = `<h2>Подбор готов</h2><p>${esc(recommended.explanation)}</p>${recommended.items.map(item => { const component = catalog.find(part => part.id === item.component_id); return `<p>${esc(component?.name || `Компонент #${item.component_id}`)} · ${item.quantity} шт.</p>`; }).join('')}<p class="price">${money(recommended.total)}</p><p class="muted">Ответ прошёл серверную проверку совместимости.</p><button data-action="save-recommendation">Сохранить как сборку</button>`;
  };
  actions['save-recommendation'] = async () => {
    if (!recommended || applyingRecommendation) return;
    applyingRecommendation = true;
    let build;
    try {
      build = (await api('builds', { method: 'POST', body: { name: 'Сборка по рекомендации ИИ', budget: recommended.budget } })).data;
      for (const item of recommended.items) await api(`builds/${build.id}/items`, { method: 'POST', body: item });
      recommended = null; notify('Рекомендация сохранена. Проверьте сборку перед оформлением.'); context.navigate(`#build/${build.id}`);
    } catch (error) {
      if (build) { notify(`Сборка №${build.id} сохранена частично: ${error.message}. Откройте её и дополните состав.`, true); context.navigate(`#build/${build.id}`); }
      else throw error;
    } finally { applyingRecommendation = false; }
  };
}

