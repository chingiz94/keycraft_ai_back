const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[character]);
try {
  const response = await fetch('/openapi.json', { headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Контракт недоступен.');
  const spec = await response.json();
  const resolve = schema => schema?.$ref ? schema.$ref.split('/').slice(1).reduce((node, key) => node?.[key], spec) : schema;
  const schemaName = schema => schema?.$ref?.split('/').at(-1) || schema?.type || 'JSON';
  const schemaTable = schema => {
    const value = resolve(schema);
    if (!value?.properties) return `<pre>${esc(JSON.stringify(value, null, 2))}</pre>`;
    return `<div class="table-wrap"><table><thead><tr><th>Поле</th><th>Тип</th><th>Обязательность</th><th>Условия</th></tr></thead><tbody>${Object.entries(value.properties).map(([name, field]) => `<tr><td><code>${esc(name)}</code></td><td>${esc(schemaName(field))}</td><td>${value.required?.includes(name) ? 'Да' : 'Нет'}</td><td>${esc(field.description || '')}${field.enum ? ` Допустимо: ${esc(field.enum.join(', '))}.` : ''}${field.minimum !== undefined ? ` Мин.: ${field.minimum}.` : ''}${field.maximum !== undefined ? ` Макс.: ${field.maximum}.` : ''}${field.maxLength ? ` Длина до ${field.maxLength}.` : ''}</td></tr>`).join('')}</tbody></table></div>`;
  };
  const operations = [];
  for (const [url, methods] of Object.entries(spec.paths)) for (const [method, operation] of Object.entries(methods)) operations.push({ url, method, operation });
  document.querySelector('#operations').innerHTML = operations.map(({ url, method, operation }) => `<details data-search="${esc(`${url} ${operation.summary} ${operation.tags.join(' ')}`.toLowerCase())}"><summary><span class="method">${method.toUpperCase()}</span>${esc(url)}<br><small>${esc(operation.summary)}</small></summary><article><p>${esc(operation.description || '')}</p><p><strong>Авторизация:</strong> ${operation.security?.length ? 'Bearer Sanctum; роль указана в описании операции.' : 'Не требуется.'}</p>${operation.parameters?.length ? `<h3>Параметры</h3><div class="table-wrap"><table><thead><tr><th>Параметр</th><th>Где</th><th>Обязателен</th><th>Описание</th></tr></thead><tbody>${operation.parameters.map(parameter => `<tr><td><code>${esc(parameter.name)}</code></td><td>${esc(parameter.in)}</td><td>${parameter.required ? 'Да' : 'Нет'}</td><td>${esc(parameter.description || '')}${parameter.schema.enum ? ` ${esc(parameter.schema.enum.join(', '))}` : ''}</td></tr>`).join('')}</tbody></table></div>` : ''}${operation.requestBody ? `<h3>Тело запроса</h3>${schemaTable(operation.requestBody.content['application/json'].schema)}${operation.requestBody.content['application/json'].example ? `<pre>${esc(JSON.stringify(operation.requestBody.content['application/json'].example, null, 2))}</pre>` : ''}` : ''}<h3>Ответы и ошибки</h3>${Object.entries(operation.responses).map(([status, response]) => `<details><summary>${status} ${esc(response.description)}</summary><article>${response.content?.['application/json'] ? `<p>Схема: ${esc(schemaName(response.content['application/json'].schema))}</p><pre>${esc(JSON.stringify(response.content['application/json'].example || resolve(response.content['application/json'].schema), null, 2))}</pre>` : '<p>Ответ без тела.</p>'}</article></details>`).join('')}</article></details>`).join('');
  const filter = () => {
    const query = document.querySelector('#search').value.toLowerCase();
    let shown = 0;
    document.querySelectorAll('details[data-search]').forEach(element => { element.hidden = !element.dataset.search.includes(query); if (!element.hidden) shown++; });
    document.querySelector('#status').textContent = `Операций: ${shown} из ${operations.length}. Версия контракта ${spec.info.version}.`;
  };
  document.querySelector('#search').addEventListener('input', filter); filter();
} catch (error) { document.querySelector('#status').textContent = error.message; }
