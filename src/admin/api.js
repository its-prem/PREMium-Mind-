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

/**
 * Opens an uploaded PDF from the server in a new tab. The server folder is private, so the file is
 * fetched with the admin login and shown from memory.
 */
export async function openUploadedPdf(path) {
  const token = getPdfSession();
  if (!token) throw new AdminError('Please log in first.', 'unauthenticated');
  const win = window.open('', '_blank'); // opened first, so the browser does not block it
  try {
    const res = await fetch(`${BASE}file.php?path=${encodeURIComponent(path)}`, { headers: { 'X-PM-Session': token }, mode: 'cors', credentials: 'omit' });
    const type = res.headers.get('Content-Type') || '';
    if (!res.ok || !type.includes('pdf')) {
      let msg = 'Could not open the PDF.';
      try {
        msg = (await res.json()).message || msg;
      } catch {
        /* not JSON */
      }
      throw new AdminError(msg, 'file');
    }
    const url = URL.createObjectURL(await res.blob());
    if (win) win.location.href = url;
    else window.location.href = url;
    setTimeout(() => URL.revokeObjectURL(url), 5 * 60 * 1000);
  } catch (e) {
    win?.close();
    throw e;
  }
}

/** Opens a file the admin just picked (not uploaded yet) in a new tab. */
export function openLocalFile(file) {
  const url = URL.createObjectURL(file);
  window.open(url, '_blank');
  setTimeout(() => URL.revokeObjectURL(url), 5 * 60 * 1000);
}
