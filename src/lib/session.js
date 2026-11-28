/**
 * Login session helpers (React port of pm-session.js).
 * Uses the same storage keys as the old site so an existing login keeps working.
 */

function getCookie(name) {
  const match = document.cookie.match(
    new RegExp(
      '(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'
    )
  );
  return match ? decodeURIComponent(match[1]) : null;
}

function deleteCookie(name) {
  const secure = location.protocol === 'https:' ? '; Secure' : '';
  document.cookie =
    name +
    '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax' +
    secure;
}

function readStored(key) {
  let val = null;
  try {
    val = localStorage.getItem(key);
  } catch {
    /* storage blocked */
  }
  if (!val) {
    try {
      val = sessionStorage.getItem(key);
    } catch {
      /* storage blocked */
    }
  }
  if (!val) val = getCookie(key);
  return val || null;
}

export const getStoredEmail = () => readStored('customUserEmail');
export const getStoredName = () => readStored('customUserName');
export const getPdfSession = () => readStored('pm_pdf_session');

function setCookie(name, value, days = 30) {
  const expires = new Date(Date.now() + days * 86400000).toUTCString();
  const secure = location.protocol === 'https:' ? '; Secure' : '';
  document.cookie =
    name +
    '=' +
    encodeURIComponent(value) +
    '; expires=' +
    expires +
    '; path=/; SameSite=Lax' +
    secure;
}

function writeStored(key, value) {
  if (!value) return;
  try {
    localStorage.setItem(key, value);
  } catch {
    /* storage blocked (some WebViews) */
  }
  setCookie(key, value);
}

/** Save the login so every page (and the old site) sees the same user. */
export function saveLoginSession(email, name, phone) {
  if (!email) return false;
  const e = String(email).trim();
  const n = name || 'User';
  const p = phone || '0000000000';
  writeStored('customUserEmail', e);
  writeStored('customUserName', n);
  writeStored('customUserPhone', p);
  try {
    sessionStorage.setItem('customUserEmail', e);
    sessionStorage.setItem('customUserName', n);
    sessionStorage.setItem('customUserPhone', p);
  } catch {
    /* storage blocked */
  }
  return !!getStoredEmail();
}

/** Signed PDF session token minted by the server at login. */
export function setPdfSession(token) {
  if (!token) return;
  const t = String(token);
  writeStored('pm_pdf_session', t);
  try {
    sessionStorage.setItem('pm_pdf_session', t);
  } catch {
    /* storage blocked */
  }
}

/** True when a PHP API response means success. */
export function isApiSuccess(data) {
  if (!data || typeof data !== 'object') return false;
  const status = String(
    data.status || data.Status || data.result || ''
  ).toLowerCase();
  if (['success', 'ok', 'true', '1'].includes(status)) return true;
  if (data.success === true || data.success === 1 || data.success === '1') {
    return true;
  }
  return !!(data.email && (data.name || data.user_name) && !data.message);
}

export function clearStoredSession() {
  const keys = [
    'customUserEmail',
    'customUserName',
    'customUserPhone',
    'pm_pdf_session',
  ];
  keys.forEach((key) => {
    try {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    } catch {
      /* storage blocked */
    }
    deleteCookie(key);
  });
  // lets other parts (purchases) forget this user's data
  window.dispatchEvent(new Event('pm-session-cleared'));
}

/** True when a course is flagged as app-only in the database. */
export function isAppOnlyCourse(course) {
  if (!course || typeof course !== 'object') return false;
  const v = course.app_only != null ? course.app_only : course.only_app_access;
  return v == 1 || v === '1' || v === true || String(v).toLowerCase() === 'yes';
}

/** True when running inside the PREMium Mind Android app WebView. */
export function isNativeApp() {
  try {
    if (
      sessionStorage.getItem('pm_native_app') === '1' ||
      localStorage.getItem('pm_native_app') === '1'
    ) {
      return true;
    }
  } catch {
    /* storage blocked */
  }
  if (window.__PM_NATIVE_APP__ || window.PMAppSigner) return true; // PMAppSigner = the app's own native signer (AppSigner.java)
  try {
    if (window.Capacitor?.isNativePlatform?.()) return true;
  } catch {
    /* ignore */
  }
  if (window.Android || window.AndroidPdfSaver) return true;
  const ua = navigator.userAgent || '';
  return (
    /Android/i.test(ua) &&
    (/; wv\)/.test(ua) || /Capacitor/i.test(ua) || /premiummind/i.test(ua))
  );
}
