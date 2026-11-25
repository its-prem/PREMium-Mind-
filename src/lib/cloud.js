import { SITE_API } from './api';
import { getPdfSession, getStoredEmail } from './session';

/**
 * Saves small pieces of the student's work (resume, syllabus ticks, mock-test scores) in their account,
 * through progress.php, so it follows them to another phone or laptop.
 * It never blocks anything: with no login or no internet it simply does nothing and the data stays
 * on this device. "stamp" = when this device's copy was last changed, used to see which copy is newer.
 */

const timers = {};

const stampKey = (name) => `pm_cloud_at:${(getStoredEmail() || 'guest').toLowerCase()}:${name}`;

export const stampGet = (name) => {
  try {
    return Number(localStorage.getItem(stampKey(name))) || 0;
  } catch {
    return 0;
  }
};

export const stampSet = (name, value = Date.now()) => {
  try {
    localStorage.setItem(stampKey(name), String(value));
  } catch {
    /* storage blocked */
  }
};

/** The account's copy: { data, ts } or null (no login, nothing saved, or offline). */
export async function cloudGet(kind) {
  const token = getPdfSession();
  if (!token) return null;
  try {
    const res = await fetch(`${SITE_API}/progress.php?kind=${encodeURIComponent(kind)}`, {
      headers: { 'X-PM-Session': token },
      cache: 'no-store',
    });
    const out = await res.json();
    if (out && out.status === 'success' && out.data != null) return { data: out.data, ts: Number(out.ts) || 0 };
  } catch {
    /* offline */
  }
  return null;
}

/** Save to the account a moment after the last change (so typing does not send a request per key). */
export function cloudPut(kind, data, delay = 1500) {
  const token = getPdfSession();
  if (!token) return;
  clearTimeout(timers[kind]);
  timers[kind] = setTimeout(async () => {
    try {
      await fetch(`${SITE_API}/progress.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-PM-Session': token },
        body: JSON.stringify({ kind, data }),
      });
    } catch {
      /* offline: the next change will try again */
    }
  }, delay);
}
