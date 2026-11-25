import { useCallback, useEffect, useState } from 'react';
import { SITE_API } from './api';
import { getPdfSession, getStoredEmail } from './session';

/**
 * What the logged-in user owns. The truth lives on the server (the `user_courses` table, filled only
 * after Cashfree confirms the payment); this file just keeps a copy so the page can show
 * "Purchased" quickly. Access to the real PDF is always checked again by the server.
 *
 * The ids here are the store product / course ids (the id in the `courses` table).
 */

const EVENT = 'pm-purchases-changed';
const cacheKey = () => `pm_owned_cache:${(getStoredEmail() || 'guest').toLowerCase()}`;

const readCache = () => {
  try {
    const arr = JSON.parse(localStorage.getItem(cacheKey()) || '[]');
    return Array.isArray(arr) ? arr.map(String) : [];
  } catch {
    return [];
  }
};

let owned = new Set(getStoredEmail() ? readCache() : []);

/** Replace the owned ids (called by the catalog and by refreshPurchases). */
export function setOwned(ids) {
  owned = new Set((ids || []).map(String));
  try {
    localStorage.setItem(cacheKey(), JSON.stringify([...owned]));
  } catch {
    /* storage blocked */
  }
  window.dispatchEvent(new Event(EVENT));
}

/** Forget everything (log out / another account). */
export function clearOwned() {
  owned = new Set();
  window.dispatchEvent(new Event(EVENT));
}

window.addEventListener('pm-session-cleared', () => clearOwned());

export const getPurchases = () => [...owned].map((id) => ({ productId: id }));
export const hasPurchased = (productId) => owned.has(String(productId));

/** Ask the server which courses this user owns. Returns the list of ids, or null if not logged in / failed. */
export async function refreshPurchases() {
  const token = getPdfSession();
  if (!token) {
    clearOwned();
    return null;
  }
  try {
    const res = await fetch(`${SITE_API}/my_courses.php`, {
      headers: { 'X-PM-Session': token },
      cache: 'no-store',
    });
    const data = await res.json();
    if (data && data.status === 'success' && Array.isArray(data.courses)) {
      const ids = data.courses.map((c) => c.course_id);
      setOwned(ids);
      return ids;
    }
  } catch {
    /* offline: keep what we have */
  }
  return null;
}

/** React hook: owned ids that update when something is bought. */
export function usePurchases() {
  const [list, setList] = useState(getPurchases);

  useEffect(() => {
    const refresh = () => setList(getPurchases());
    window.addEventListener(EVENT, refresh);
    return () => window.removeEventListener(EVENT, refresh);
  }, []);

  const owns = useCallback(
    (productId) => list.some((p) => String(p.productId) === String(productId)),
    [list]
  );

  return { purchases: list, owns };
}
