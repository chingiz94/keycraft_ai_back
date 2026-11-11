export const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
export const money = value => `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(Number(value) / 100)} ₸`;
export const types = { case: 'Корпус', pcb: 'Плата', plate: 'Плейт', stabilizer: 'Стабилизаторы', switch: 'Свитчи', keycap: 'Кейкапы' };
export const statuses = { new: 'Новый', confirmed: 'Подтверждён', assembled: 'Собран', issued: 'Выдан', cancelled: 'Отменён' };
export const options = (values, selected = '') => Object.entries(values).map(([value, label]) => `<option value="${escapeHtml(value)}" ${String(value) === String(selected) ? 'selected' : ''}>${escapeHtml(label)}</option>`).join('');
export const field = (name, label, value = '', attributes = '', type = 'text') => `<label>${escapeHtml(label)}<input name="${escapeHtml(name)}" type="${type}" value="${escapeHtml(value)}" ${attributes}></label>`;
export const select = (name, label, values, selected = '', attributes = '') => `<label>${escapeHtml(label)}<select name="${escapeHtml(name)}" ${attributes}>${options(values, selected)}</select></label>`;
export const errorSlot = '<p class="form-error" role="alert"></p>';
export const title = (heading, description = '', action = '') => `<div class="page-title"><div><p class="eyebrow">KeyCraft AI</p><h1>${escapeHtml(heading)}</h1><p>${escapeHtml(description)}</p></div>${action}</div>`;
export const empty = text => `<div class="empty">${escapeHtml(text)}</div>`;
export const pages = (meta, route) => meta.last_page > 1 ? `<div class="pagination"><a class="button secondary" href="${route(Math.max(1, meta.current_page - 1))}" ${meta.current_page === 1 ? 'aria-disabled="true" tabindex="-1"' : ''}>Назад</a><span>${meta.current_page} / ${meta.last_page}</span><a class="button secondary" href="${route(Math.min(meta.last_page, meta.current_page + 1))}" ${meta.current_page === meta.last_page ? 'aria-disabled="true" tabindex="-1"' : ''}>Далее</a></div>` : '';

export function notify(message, error = false) {
  const notice = document.querySelector('#notice');
  notice.textContent = message;
  notice.className = `notice${error ? ' error' : ''}`;
  notice.hidden = false;
  if (error) notice.scrollIntoView({ block: 'start' });
}

export function showError(error, form = null) {
  const details = Object.values(error.errors || {}).flat().join('\n');
  const message = `${error.message}${details ? `\n${details}` : ''}${error.requestId ? `\nКод запроса: ${error.requestId}` : ''}`;
  if (!form) return notify(message, true);
  form.querySelector('.form-error').textContent = message;
  for (const [name, messages] of Object.entries(error.errors || {})) {
    const input = form.elements.namedItem(name);
    if (!input?.closest) continue;
    input.setAttribute('aria-invalid', 'true');
    const hint = document.createElement('span');
    hint.className = 'field-error';
    hint.textContent = messages.join(' ');
    input.closest('label').append(hint);
  }
}

export async function submitForm(form, callback) {
  if (!form.reportValidity()) return;
  form.querySelectorAll('.field-error').forEach(element => element.remove());
  form.querySelectorAll('[aria-invalid]').forEach(element => element.removeAttribute('aria-invalid'));
  form.querySelector('.form-error').textContent = '';
  const buttons = [...form.querySelectorAll('button')];
  buttons.forEach(button => { button.disabled = true; });
  try { await callback(Object.fromEntries(new FormData(form))); }
  catch (error) { showError(error, form); }
  finally { buttons.forEach(button => { button.disabled = false; }); }
}

export function confirmDelete(message) {
  const dialog = document.querySelector('#confirm-dialog');
  document.querySelector('#confirm-message').textContent = message;
  dialog.returnValue = 'cancel';
  dialog.showModal();
  return new Promise(resolve => dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true }));
}

export function safeLink(url) {
  try { return new URL(url).protocol === 'https:' ? escapeHtml(url) : '#'; }
  catch { return '#'; }
}
