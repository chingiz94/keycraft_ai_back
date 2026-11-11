import { API_BASE_URL } from './config.js';

const TOKEN_KEY = 'keycraft.token';

export const session = {
  token: () => sessionStorage.getItem(TOKEN_KEY),
  save: token => sessionStorage.setItem(TOKEN_KEY, token),
  clear: () => sessionStorage.removeItem(TOKEN_KEY),
};

export class ApiError extends Error {
  constructor(message, status = 0, errors = {}, requestId = '') {
    super(message);
    this.status = status;
    this.errors = errors;
    this.requestId = requestId;
  }
}

export async function api(path, { method = 'GET', body, headers = {}, authenticated = true } = {}) {
  const requestHeaders = { Accept: 'application/json', ...headers };
  if (body !== undefined) requestHeaders['Content-Type'] = 'application/json';
  if (authenticated && session.token()) requestHeaders.Authorization = `Bearer ${session.token()}`;
  let response;
  try {
    response = await fetch(`${API_BASE_URL}/${path.replace(/^\//, '')}`, {
      method, headers: requestHeaders, credentials: 'omit',
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: AbortSignal.timeout(45000),
    });
  } catch {
    throw new ApiError('Нет соединения с сервером. Проверьте запуск API и адрес в client/config.js.');
  }
  const data = response.status === 204 ? null : await response.json().catch(() => null);
  if (!response.ok) {
    if (response.status === 401 && authenticated) {
      session.clear();
      window.dispatchEvent(new CustomEvent('session-expired'));
    }
    throw new ApiError(data?.message || `Ошибка сервера (${response.status}). Повторите запрос позже.`, response.status, data?.errors || {}, response.headers.get('X-Request-ID') || '');
  }
  return data;
}

export async function allComponents() {
  const components = [];
  let page = 1;
  let last = 1;
  do {
    const result = await api(`components?per_page=100&page=${page}`, { authenticated: false });
    components.push(...result.data);
    last = result.meta.last_page;
    page += 1;
  } while (page <= last);
  return components;
}
