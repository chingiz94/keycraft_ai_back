const fs = require('node:fs');
const path = require('node:path');
const items = [];
const json = value => JSON.stringify(value, null, 2).replace(/"\{\{([a-z_]+_id)\}\}"/g, '{{$1}}');
function add(number, name, method, url, code, { body, role, headers = [], tests = [], save, inputs = '' } = {}) {
  const request = {
    method, url: '{{base_url}}' + url, auth: { type: 'noauth' },
    header: [{ key: 'Accept', value: 'application/json' }, ...(body === undefined ? [] : [{ key: 'Content-Type', value: 'application/json' }]), ...(role ? [{ key: 'Authorization', value: `Bearer {{${role}_token}}` }] : []), ...headers],
    description: `Тест ${number}. ${name}. Ожидается HTTP ${code}. ${inputs}`,
    ...(body === undefined ? {} : { body: { mode: 'raw', raw: json(body), options: { raw: { language: 'json' } } } }),
  };
  const script = [`pm.test('HTTP ${code}', () => pm.response.to.have.status(${code}));`, ...tests];
  if (save) script.push(`if(pm.response.code === ${code}) pm.collectionVariables.set('${save}', String(pm.response.json().data.id));`);
  items.push({ name: `TC${String(number).padStart(2, '0')} ${name}`, request, event: [{ listen: 'test', script: { type: 'text/javascript', exec: script } }] });
}
add(1, 'API и база доступны', 'GET', '/health', 200, { tests: ["pm.test('База доступна', () => pm.expect(pm.response.json().data.database).to.eql('ok'));"] });
add(2, 'Гость не читает сборки', 'GET', '/builds', 401);
add(3, 'Вход администратора', 'POST', '/auth/login', 200, { body: { email: '{{admin_email}}', password: '{{admin_password}}' }, tests: ["if(pm.response.code===200) pm.collectionVariables.set('admin_token', pm.response.json().token);", "pm.test('Роль admin',()=>pm.expect(pm.response.json().data.role).to.eql('admin'));"] });
add(4, 'Роль нельзя назначить регистрацией', 'POST', '/auth/register', 422, { body: { name: 'Проверка КП10', email: '{{test_email}}', password: '{{test_password}}', password_confirmation: '{{test_password}}', role: 'admin' }, tests: ["pm.test('Ошибка role',()=>pm.expect(pm.response.json().errors).to.have.property('role'));"] });
add(5, 'Регистрация покупателя', 'POST', '/auth/register', 201, { body: { name: 'Проверка КП10', email: '{{test_email}}', password: '{{test_password}}', password_confirmation: '{{test_password}}' }, tests: ["if(pm.response.code===201) pm.collectionVariables.set('buyer_token', pm.response.json().token);", "pm.test('Новая роль buyer',()=>pm.expect(pm.response.json().data.role).to.eql('buyer'));", "pm.test('Пароль отсутствует в ответе',()=>pm.expect(pm.response.json().data).not.to.have.property('password'));"] });
add(6, 'Текущий пользователь', 'GET', '/auth/me', 200, { role: 'buyer', tests: ["pm.test('Своя учётная запись',()=>pm.expect(pm.response.json().data.email).to.eql(pm.collectionVariables.get('test_email')));"] });
add(7, 'Покупателю запрещено администрирование', 'GET', '/admin/users', 403, { role: 'buyer' });
add(8, 'Валидация компонента', 'POST', '/admin/components', 422, { role: 'admin', body: { name: '', type: 'unknown', price: -1, stock: -1, specs: {} }, tests: ["pm.test('Ошибки полей',()=>pm.expect(pm.response.json().errors).to.include.all.keys('name','type','price','stock'));"] });
const definitions = {
  case: [10000, '65', { mount: 'gasket' }],
  pcb: [7000, '65', { mount: 'gasket', switch_type: 'mx', layout: 'ansi', key_count: 68, stabilizers: { '2u': 3, '6.25u': 1 } }],
  plate: [4000, '65', { mount: 'gasket', switch_type: 'mx' }],
  stabilizer: [2000, null, { stabilizers: { '2u': 4, '6.25u': 1 } }],
  switch: [100, null, { switch_type: 'mx', stem: 'mx' }],
  keycap: [5000, null, { stem: 'mx', layout: 'ansi', key_count: 104 }],
};
let n = 9;
for (const [type, [price, format, specs]] of Object.entries(definitions)) add(n++, `Создать тестовый ${type}`, 'POST', '/admin/components', 201, { role: 'admin', body: { name: `CP10 {{run_id}} ${type}`, type, price, stock: 1000, format, specs }, save: `${type}_id` });
add(n++, 'Поиск фильтрация и пагинация', 'GET', '/components?search={{run_id}}&type=case&in_stock=1&sort=price&order=asc&per_page=1&page=1', 200, { tests: ["pm.test('Ровно один корпус',()=>{const b=pm.response.json();pm.expect(b.data.length).to.eql(1);pm.expect(b.data[0].type).to.eql('case');pm.expect(b.meta.total).to.eql(1);});"] });
add(n++, 'Карточка и связанные предложения', 'GET', '/components/{{case_id}}', 200, { tests: ["pm.test('Связь offers присутствует',()=>pm.expect(pm.response.json().data.offers).to.be.an('array'));"] });
add(n++, 'Создать предложение', 'POST', '/admin/offers', 201, { role: 'admin', body: { component_id: '{{case_id}}', marketplace: 'Учебный магазин', url: 'https://example.test/keycraft', price: 9000, price_updated_at: '2026-11-01T10:00:00Z' }, save: 'offer_id' });
add(n++, 'Получить предложения компонента', 'GET', '/components/{{case_id}}/offers', 200, { tests: ["pm.test('Связанное предложение',()=>pm.expect(pm.response.json().data[0].id).to.eql(Number(pm.collectionVariables.get('offer_id'))));"] });
add(n++, 'Изменить компонент', 'PATCH', '/admin/components/{{case_id}}', 200, { role: 'admin', body: { name: 'CP10 {{run_id}} case updated' } });
add(n++, 'Создать сборку', 'POST', '/builds', 201, { role: 'buyer', body: { name: 'CP10 {{run_id}} build', budget: 1000000 }, save: 'build_id' });
add(n++, 'Неполная сборка не проходит проверку', 'POST', '/builds/{{build_id}}/validate', 200, { role: 'buyer', tests: ["pm.test('complete=false',()=>pm.expect(pm.response.json().data.complete).to.eql(false))"] });
for (const type of Object.keys(definitions)) add(n++, `Добавить ${type} в сборку`, 'POST', '/builds/{{build_id}}/items', 201, { role: 'buyer', body: { component_id: `{{${type}_id}}`, quantity: type === 'switch' ? 68 : 1 }, ...(type === 'case' ? { save: 'item_id' } : {}) });
add(n++, 'Повтор детали запрещён', 'POST', '/builds/{{build_id}}/items', 409, { role: 'buyer', body: { component_id: '{{case_id}}', quantity: 1 } });
add(n++, 'Совместимая сборка и серверная сумма', 'POST', '/builds/{{build_id}}/validate', 200, { role: 'buyer', tests: ["pm.test('Полнота совместимость и сумма',()=>{const d=pm.response.json().data;pm.expect(d.complete).to.eql(true);pm.expect(d.compatible).to.eql(true);pm.expect(d.total).to.eql(34800);});"] });
add(n++, 'Устаревшая сумма не создаёт заказ', 'POST', '/orders', 409, { role: 'buyer', headers: [{ key: 'Idempotency-Key', value: '{{run_id}}-stale' }], body: { build_id: '{{build_id}}', expected_total: 1 }, tests: ["pm.test('Актуальная сумма в ошибке',()=>pm.expect(pm.response.json().errors.current_total[0]).to.eql(34800));"] });
const checkout = { role: 'buyer', headers: [{ key: 'Idempotency-Key', value: '{{run_id}}-checkout' }], body: { build_id: '{{build_id}}', expected_total: 34800 } };
add(n++, 'Оформить заказ', 'POST', '/orders', 201, { ...checkout, save: 'order_id', tests: ["pm.test('Итог и шесть позиций',()=>{const d=pm.response.json().data;pm.expect(d.total).to.eql(34800);pm.expect(d.items.length).to.eql(6);});"] });
add(n++, 'Повтор не создаёт второй заказ', 'POST', '/orders', 200, { ...checkout, tests: ["pm.test('Тот же заказ',()=>pm.expect(pm.response.json().data.id).to.eql(Number(pm.collectionVariables.get('order_id'))));"] });
add(n++, 'Остаток списан один раз', 'GET', '/components/{{switch_id}}', 200, { tests: ["pm.test('1000 - 68 = 932',()=>pm.expect(pm.response.json().data.stock).to.eql(932));"] });
add(n++, 'Пропуск статуса запрещён', 'PATCH', '/admin/orders/{{order_id}}/status', 409, { role: 'admin', body: { status: 'issued' } });
add(n++, 'Отмена возвращает остатки', 'PATCH', '/admin/orders/{{order_id}}/status', 200, { role: 'admin', body: { status: 'cancelled' }, tests: ["pm.test('Заказ отменён',()=>pm.expect(pm.response.json().data.status).to.eql('cancelled'));"] });
add(n++, 'Повторная отмена запрещена', 'PATCH', '/admin/orders/{{order_id}}/status', 409, { role: 'admin', body: { status: 'cancelled' } });
add(n++, 'Остаток возвращён ровно один раз', 'GET', '/components/{{switch_id}}', 200, { tests: ["pm.test('Снова 1000',()=>pm.expect(pm.response.json().data.stock).to.eql(1000));"] });
add(n++, 'Связанный с заказом компонент не удаляется', 'DELETE', '/admin/components/{{case_id}}', 409, { role: 'admin' });
add(n++, 'Статистика доступна администратору', 'GET', '/admin/statistics', 200, { role: 'admin', tests: ["pm.test('Сводка и выручка',()=>pm.expect(pm.response.json().data).to.include.all.keys('orders_by_status','issued_revenue'));"] });
add(n++, 'Изменить предложение', 'PATCH', '/admin/offers/{{offer_id}}', 200, { role: 'admin', body: { price: 8500 }, tests: ["pm.test('Цена обновлена',()=>pm.expect(pm.response.json().data.price).to.eql(8500))"] });
add(n++, 'Удалить созданное предложение', 'DELETE', '/admin/offers/{{offer_id}}', 204, { role: 'admin' });
add(n++, 'Удалить сборку с сохранением заказа', 'DELETE', '/builds/{{build_id}}', 204, { role: 'buyer' });
add(n++, 'Заказ сохраняется после удаления сборки', 'GET', '/orders/{{order_id}}', 200, { role: 'buyer', tests: ["pm.test('build_id=null',()=>pm.expect(pm.response.json().data.build_id).to.eql(null))"] });
add(n++, 'Неизвестный ресурс', 'GET', '/components/2147483647', 404);
add(n++, 'Выход покупателя', 'POST', '/auth/logout', 204, { role: 'buyer' });
add(n++, 'Отозванный токен больше не действует', 'GET', '/auth/me', 401, { role: 'buyer' });
const collection = {
  info: { name: 'KeyCraft CP10 regression', schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json', description: 'Воспроизводимая проверка на отдельной локальной базе keycraft_demo. Создаёт собственные компоненты, покупателя и отменённый заказ для сохранения доказательств. Не запускать на рабочей БД. Реальный ИИ не вызывается. Для запуска задайте allow_coursework_tests=keycraft_demo, admin_email, admin_password, test_password. Секреты в файл не сохраняются.' },
  variable: Object.entries({ base_url: 'http://127.0.0.1:8120/api/v1', allow_coursework_tests: '', admin_email: 'admin@keycraft.test', admin_password: '', test_password: '', admin_token: '', buyer_token: '' }).map(([key, value]) => ({ key, value, type: 'string' })),
  event: [{ listen: 'prerequest', script: { type: 'text/javascript', exec: [
    "const safe = pm.variables.get('allow_coursework_tests') === 'keycraft_demo' && ['http://127.0.0.1:8120/api/v1','http://localhost:8120/api/v1'].includes(pm.variables.get('base_url'));",
    "if(!safe){pm.test('Разрешён только выделенный локальный стенд',()=>{throw new Error('Нужен allow_coursework_tests=keycraft_demo и локальный API на 8120');});pm.execution.skipRequest();}",
    "if(pm.info.requestName.startsWith('TC01 ')){const id=pm.variables.replaceIn('{{$guid}}');pm.collectionVariables.set('run_id',id);pm.collectionVariables.set('test_email','cp10.'+id+'@example.test');}",
  ] } }], item: items,
};
const { Script } = require('node:vm');
for (const item of [collection, ...items]) for (const event of item.event || []) new Script(event.script.exec.join('\n'));
fs.writeFileSync(path.join(__dirname, 'KeyCraft.coursework-tests.postman_collection.json'), JSON.stringify(collection, null, 2) + '\n');
console.log(JSON.stringify({ requests: items.length }));
