import { test, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { api, session, ApiError } from '../client/api.js';

const originalFetch = globalThis.fetch;
const values = new Map();
globalThis.sessionStorage = { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key) };
globalThis.window = new EventTarget();
afterEach(() => { globalThis.fetch = originalFetch; values.clear(); });

test('JSON writes carry Bearer and idempotency headers with cookies omitted', async () => {
  session.save('test-only-token');
  globalThis.fetch = async (url, options) => {
    assert.equal(url, 'http://127.0.0.1:8120/api/v1/orders');
    assert.equal(options.method, 'POST');
    assert.equal(options.headers.Accept, 'application/json');
    assert.equal(options.headers['Content-Type'], 'application/json');
    assert.equal(options.headers.Authorization, 'Bearer test-only-token');
    assert.equal(options.headers['Idempotency-Key'], 'checkout-test');
    assert.equal(options.credentials, 'omit');
    assert.equal(options.body, '{"build_id":1,"expected_total":100}');
    return Response.json({ data: { id: 12 } }, { status: 201 });
  };
  assert.equal((await api('orders', { method: 'POST', body: { build_id: 1, expected_total: 100 }, headers: { 'Idempotency-Key': 'checkout-test' } })).data.id, 12);
});

test('public requests do not transmit a saved token', async () => {
  session.save('private-token');
  globalThis.fetch = async (url, options) => { assert.equal(options.headers.Authorization, undefined); return Response.json({ data: [] }); };
  await api('components', { authenticated: false });
});

test('401 clears the session and signals the application to show login', async () => {
  session.save('expired-token');
  let expired = false;
  window.addEventListener('session-expired', () => { expired = true; }, { once: true });
  globalThis.fetch = async () => Response.json({ message: 'Требуется вход', errors: {} }, { status: 401 });
  await assert.rejects(api('auth/me'), error => error instanceof ApiError && error.status === 401);
  assert.equal(session.token(), null);
  assert.equal(expired, true);
});

test('422 retains field errors and correlation ID without clearing the session', async () => {
  session.save('valid-token');
  globalThis.fetch = async () => Response.json({ message: 'Ошибка валидации', errors: { name: ['Укажите название.'] } }, { status: 422, headers: { 'X-Request-ID': 'validation-request' } });
  await assert.rejects(api('builds', { method: 'POST', body: {} }), error => { assert.deepEqual(error.errors.name, ['Укажите название.']); assert.equal(error.requestId, 'validation-request'); return error.status === 422; });
  assert.equal(session.token(), 'valid-token');
});

test('204 logout is accepted without parsing an empty JSON body', async () => {
  globalThis.fetch = async () => new Response(null, { status: 204 });
  assert.equal(await api('auth/logout', { method: 'POST' }), null);
});

test('network failures and non-JSON server errors become readable errors', async () => {
  globalThis.fetch = async () => { throw new TypeError('Network error'); };
  await assert.rejects(api('components'), error => error.status === 0 && error.message.includes('Нет соединения'));
  globalThis.fetch = async () => new Response('<html>unavailable</html>', { status: 503 });
  await assert.rejects(api('components'), error => error.status === 503 && error.message.includes('503'));
});
