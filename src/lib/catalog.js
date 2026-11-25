import { useEffect, useState } from 'react';
import { PRODUCTS } from '../data/storeData';
import { SITE_API } from './api';
import { getPdfSession } from './session';
import { setOwned } from './purchases';

/**
 * The store's products come from the same `courses` table the admin panel edits (api.php).
 * src/data/storeData.js keeps a built-in list as a fallback, which is only shown when the server
 * cannot be reached or has no products yet.
 *
 * How a `courses` row becomes a product:
 *   category      notes | ebooks | resume | important | projects (report-writing counts as projects)
 *   demo_link     for resume rows:  template:modern | template:classic | template:compact
 *   pdf_file      only filled in by the server when THIS user owns the course
 *   btn_type      coming_soon / disabled  ->  shown, but cannot be bought
 */

const EVENT = 'pm-catalog-changed';
const FRESH_MS = 60 * 1000;
const ACCENTS = { modern: '#2563eb', classic: '#18181b', compact: '#059669' };

const state = { status: 'idle', source: 'local', loadedAt: 0, promise: null };

const toAbsoluteImage = (img) => {
  const s = String(img || '').trim();
  if (!s) return '/small-logo.png';
  if (/^https?:\/\//i.test(s)) return s;
  return `/${s.replace(/^\/+/, '')}`;
};

const toNumber = (v) => {
  const n = parseFloat(String(v == null ? '' : v).replace(/[^0-9.]/g, ''));
  return Number.isFinite(n) ? n : 0;
};

function rowToProduct(row) {
  const raw = String(row.category || '').trim().toLowerCase();
  let category = 'notes';
  if (['notes', 'ebooks', 'resume', 'important'].includes(raw)) category = raw;
  else if (raw === 'projects' || raw === 'report-writing') category = 'projects';

  const tpl = (/template:\s*([a-z0-9_-]+)/i.exec(String(row.demo_link || '')) || [])[1];
  const template = category === 'resume' ? (ACCENTS[tpl] ? tpl : 'modern') : undefined;
  const blocked = ['coming_soon', 'disabled', 'disabled_look'].includes(String(row.btn_type || ''));
  const image = toAbsoluteImage(row.image);

  return {
    id: Number(row.id),
    category,
    template,
    accent: template ? ACCENTS[template] : undefined,
    badge: String(row.badge || ''),
    title: String(row.title || ''),
    desc1: String(row.desc1 || ''),
    image,
    sampleImg: image,
    price: toNumber(row.price),
    oldPrice: toNumber(row.old_price),
    rating: '',
    timer: '',
    btnType: blocked ? 'coming_soon' : 'buy',
    pdfFile: String(row.pdf_file || ''), // empty unless this user owns it
    demoSrc: '',
    fromServer: true,
  };
}

/** Fetch the products (and which ones this user owns) from api.php. */
export function loadCatalog(force = false) {
  if (state.promise) return state.promise;
  if (!force && state.status === 'ready' && Date.now() - state.loadedAt < FRESH_MS) {
    return Promise.resolve(state);
  }

  state.status = 'loading';
  window.dispatchEvent(new Event(EVENT));

  const token = getPdfSession();
  state.promise = (async () => {
    try {
      const res = await fetch(`${SITE_API}/api.php`, {
        headers: token ? { 'X-PM-Session': token } : {},
        cache: 'no-store',
      });
      const rows = await res.json();
      if (!Array.isArray(rows)) throw new Error('bad catalog');

      const live = rows.filter((r) => Number(r.id) > 0 && !Number(r.is_deleted) && !Number(r.app_only));
      if (live.length) {
        PRODUCTS.splice(0, PRODUCTS.length, ...live.map(rowToProduct));
        state.source = 'server';
      }
      if (token) setOwned(live.filter((r) => Number(r.is_enrolled) === 1).map((r) => r.id));
      state.status = 'ready';
    } catch {
      state.status = 'error';
    } finally {
      state.loadedAt = Date.now();
      state.promise = null;
      window.dispatchEvent(new Event(EVENT));
    }
    return state;
  })();
  return state.promise;
}

export const catalogSource = () => state.source;

/** Re-renders the component when the catalog changes, and starts loading it. */
export function useCatalog() {
  const [, bump] = useState(0);
  useEffect(() => {
    const handler = () => bump((n) => n + 1);
    window.addEventListener(EVENT, handler);
    loadCatalog();
    return () => window.removeEventListener(EVENT, handler);
  }, []);
  return { status: state.status, source: state.source, products: PRODUCTS };
}
