/** Secure PDF backend calls (token + proxy + watermarked pages). */
import { SITE_API } from './api';
import { getPdfSession } from './session';

/** Turn whatever ?file= / ?pdf= contained into "uploads/pdfs/<name>.pdf". */
export function normalizePdfPath(raw) {
  if (!raw) return null;
  let f = String(raw).trim();
  if (!f || f === 'null' || f === 'undefined') return null;
  try {
    f = decodeURIComponent(f);
  } catch {
    /* keep as is */
  }
  f = f.replace(/\\/g, '/');

  if (/proxy_pdf\.php/i.test(f)) {
    try {
      const q = new URL(f, SITE_API).searchParams.get('file');
      if (q) f = q;
    } catch {
      const m = f.match(/[?&]file=([^&]+)/i);
      if (m) {
        try {
          f = decodeURIComponent(m[1]);
        } catch {
          f = m[1];
        }
      }
    }
  }

  if (/^https?:\/\//i.test(f)) {
    try {
      f = (new URL(f).pathname || '').replace(/^\/+/, '');
    } catch {
      /* keep as is */
    }
  }

  f = f.replace(/^\/+/, '');
  if (f && !f.includes('/') && /\.pdf$/i.test(f)) f = `uploads/pdfs/${f}`;
  f = f.replace(/(?:uploads\/pdfs\/)+/i, 'uploads/pdfs/');
  return f || null;
}

/** Errors the server raised on purpose (locked / bad token) vs network trouble. */
export class DeniedError extends Error {
  constructor(message) {
    super(message);
    this.denied = true;
  }
}

async function postJson(path, body, signal) {
  return fetch(`${SITE_API}${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    signal,
  });
}

/**
 * Ask the server for a short-lived token. The signed pdf_session proves who
 * the user is; `getIdToken` (Firebase) is only used as a fallback.
 */
export async function requestToken({ file, email, purpose, getIdToken, signal }) {
  const attempt = async (idToken) => {
    const res = await postJson(
      '/get_pdf_token.php',
      {
        file,
        email,
        purpose,
        pdf_session: getPdfSession() || undefined,
        id_token: idToken || undefined,
      },
      signal
    );
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.status !== 'success' || !data.token) {
      throw new DeniedError(data.message || 'Token request failed');
    }
    return data;
  };

  try {
    return await attempt(null);
  } catch (err) {
    if (!err.denied || !getIdToken) throw err;
    const idToken = await getIdToken();
    if (!idToken) throw err;
    return attempt(idToken);
  }
}

/** Raw PDF bytes (only for courses where downloading is allowed). */
export async function fetchPdfBytes({ file, email, purpose, token, signal }) {
  const res = await postJson('/proxy_pdf.php', { file, email, token }, signal);
  if (!res.ok) throw new DeniedError('Unauthorized Access or Invalid Token');
  return res.arrayBuffer();
}

/** One server-rendered, watermarked page image (locked courses). */
export async function fetchWatermarkedPage({ file, email, token, page, signal }) {
  const res = await postJson(
    '/secure_page_image.php',
    { file, email, token, page },
    signal
  );
  if (!res.ok) {
    const text = await res.text().catch(() => '');
    throw new Error(text || 'Page load failed');
  }
  return createImageBitmap(await res.blob());
}

/** Phone number used in the on-screen watermark. */
export async function fetchPhone(email) {
  try {
    const res = await fetch(
      `${SITE_API}/get_user.php?email=${encodeURIComponent(email)}`
    );
    const data = await res.json();
    if (data.status === 'success' && data.phone && String(data.phone).trim()) {
      return String(data.phone).trim();
    }
  } catch {
    /* watermark falls back to "No Phone" */
  }
  return 'No Phone';
}
