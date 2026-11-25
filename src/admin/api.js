/**
 * Calls the admin PHP files (server/admin/*.php on Hostinger).
 * The login is the same signed token the site already stores (pm_pdf_session);
 * the server checks that it belongs to an admin email.
 *
 *   adminGet('courses.php')
 *   adminPost('courses.php', { action: 'trash', id: 5 })
 *   adminPost('courses.php', formData)            // for file uploads
 */
import { SITE_API } from '../lib/api';
import { getPdfSession } from '../lib/session';

const BASE = `${SITE_API}/admin/`;

export class AdminError extends Error {
  constructor(message, code) {
    super(message);
    this.code = code;
  }
}

async function call(file, { method = 'GET', params, body } = {}) {
  const token = getPdfSession();
  if (!token) throw new AdminError('Please log in first.', 'unauthenticated');

  let url = BASE + file;
  if (params) {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q.set(k, v);
    });
    const s = q.toString();
    if (s) url += (url.includes('?') ? '&' : '?') + s;
  }

  const headers = { 'X-PM-Session': token };
  let payload;
  if (body instanceof FormData) {
    payload = body;
  } else if (body) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(url, { method, headers, body: payload, mode: 'cors', credentials: 'omit' });
  } catch {
    throw new AdminError('Could not reach the server. Check your internet.', 'network');
  }
  const text = await res.text();
  let data;
  try {
    data = JSON.parse(text.trim());
  } catch {
    throw new AdminError('Server sent an unexpected answer (is the admin folder uploaded?).', 'bad_response');
  }
  if (data.status !== 'success') {
    throw new AdminError(data.message || 'Something went wrong.', data.code || 'error');
  }
  return data;
}

export const adminGet = (file, params) => call(file, { params });
export const adminPost = (file, body) => call(file, { method: 'POST', body });
