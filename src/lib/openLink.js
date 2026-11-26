/**
 * The link a product opens after it is bought (the "Website link" set in the admin).
 *
 *  - https://...                     -> used as it is
 *  - d2dpyq/d2dpyqv2 (any spelling)  -> the D2D PYQ page of this website (/d2dpyq/d2dpyqv2), also when it is written
 *                                       as D2DPYQV2.HTML, "D2D PYQ/D2DPYQV2.HTML" or with the old site's address
 *  - another page of this website    -> a path that has a "/" in it, like  tools/mcq
 *  - anything else ("NA", "none")    -> no link
 *
 * To add another page that lives in public/, add a line to PAGES below.
 */
const PAGES = [{ match: /d2d[\s_-]*pyq/i, path: '/d2dpyq/d2dpyqv2' }];

export function normalizeOpenLink(value) {
  const raw = String(value || '').trim();
  if (!raw || raw.toLowerCase() === 'null') return '';

  let decoded = raw;
  try {
    decoded = decodeURIComponent(raw);
  } catch {
    /* keep raw */
  }

  const external = /^https?:\/\//i.test(raw);
  let host = '';
  if (external) {
    try {
      host = new URL(raw).hostname;
    } catch {
      return '';
    }
  }
  const ours = !external || /(^|\.)netlify\.app$/i.test(host) || /(^|\.)diplomawallah\.in$/i.test(host);

  if (ours) {
    for (const p of PAGES) if (p.match.test(decoded)) return p.path;
  }
  if (external) return raw;
  if (/^\/?[a-z0-9_-]+(\/[a-z0-9._-]+)+(\?\S*)?$/i.test(raw)) return raw.startsWith('/') ? raw : '/' + raw;
  return '';
}
