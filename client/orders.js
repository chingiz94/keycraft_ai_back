import { api } from './api.js';
import { escapeHtml as esc, money, statuses, select, errorSlot, title, empty, pages, notify } from './ui.js';

export function registerOrderViews(context) {
  const { views, forms } = context;
  views.orders = async params => {
    const result = await api(`orders?per_page=8&page=${Number(params.get('page')) || 1}`);
    return `${title(context.user.role === 'admin' ? 'Заказы покупателей' : 'Мои заказы', 'Стоимость и состав фиксируются в момент оформления.')}<section class="panel">${result.data.length ? result.data.map(order => `<article class="list-row"><div class="row-heading"><div><h3><a href="#order/${order.id}">Заказ №${order.id}</a></h3><p class="muted">${esc(new Date(order.created_at).toLocaleString('ru-RU'))} · ${order.items?.length || 0} позиций${context.user.role === 'admin' ? ` · Покупатель #${order.user_id}` : ''}</p></div><div><span class="order-status ${order.status}">${statuses[order.status]}</span><p class="price">${money(order.total)}</p></div></div></article>`).join('') : empty('Заказов пока нет. Создайте и проверьте сборку.')}${pages(result.meta, page => `#orders?page=${page}`)}</section>`;
  };
  views.order = async (params, [id]) => {
    const order = (await api(`orders/${Number(id)}`)).data;
    const next = ({ new: ['confirmed', 'cancelled'], confirmed: ['assembled', 'cancelled'], assembled: ['issued'] })[order.status] || [];
    return `${title(`Заказ №${order.id}`, `Создан ${new Date(order.created_at).toLocaleString('ru-RU')}`, '<a href="#orders">← Все заказы</a>')}<div class="split"><section class="panel"><h2>Состав заказа</h2><div class="table-wrap"><table><thead><tr><th>Компонент</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr></thead><tbody>${order.items.map(item => `<tr><td>${esc(item.component.name)}</td><td>${item.quantity}</td><td>${money(item.unit_price)}</td><td>${money(item.unit_price * item.quantity)}</td></tr>`).join('')}</tbody></table></div></section><section class="panel"><h2>Данные заказа</h2><p><span class="order-status ${order.status}">${statuses[order.status]}</span></p><p class="price">${money(order.total)}</p><p class="muted">${order.status === 'cancelled' ? 'Остатки возвращены на склад.' : 'Остатки зарезервированы при оформлении.'}</p>${context.user.role === 'admin' && next.length ? `<form data-form="order-status" data-id="${order.id}">${select('status', 'Следующий статус', Object.fromEntries(next.map(status => [status, statuses[status]])))}${errorSlot}<button type="submit">Изменить статус</button></form>` : ''}<p><small>Порядок обработки: новый, подтверждён, собран, выдан. Отмена доступна до сборки.</small></p></section></div>`;
  };
  forms['order-status'] = async (data, form) => {
    await api(`admin/orders/${form.dataset.id}/status`, { method: 'PATCH', body: { status: data.status } });
    notify(data.status === 'cancelled' ? 'Заказ отменён. Остатки возвращены.' : 'Статус заказа обновлён.');
    await context.refresh();
  };
}
