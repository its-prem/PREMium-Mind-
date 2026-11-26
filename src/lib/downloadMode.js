/**
 * Download rule of a product, decided in the admin (Products → edit → Download):
 *   instant  – download works right away            (allow_download = 1)
 *   timed    – download unlocks by itself at a time (allow_download = 0 and download_at set, in India time)
 *   none     – download is not allowed              (allow_download = 0, no time)
 * Pass a database row (or an object with allow_download / download_at).
 */

/** "2026-12-05 18:00:00" or "2026-12-05T18:00" (India time) -> Date, or null. */
export function parseIst(value) {
  const v = String(value || '').trim();
  if (!v || v.startsWith('0000')) return null;
  const iso = v.replace(' ', 'T');
  const d = new Date(`${iso.length === 16 ? `${iso}:00` : iso}+05:30`);
  return Number.isNaN(d.getTime()) ? null : d;
}

export function downloadState(row, now = Date.now()) {
  const a = row?.allow_download;
  if (a === true || a === 1 || a === '1') return { mode: 'instant' };
  const at = parseIst(row?.download_at);
  if (at) return now >= at.getTime() ? { mode: 'instant' } : { mode: 'timed', at };
  return { mode: 'none' };
}

/** "5 Dec, 6:00 PM" */
export function formatWhen(date) {
  return date
    .toLocaleString('en-IN', {
    day: 'numeric',
    month: 'short',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
    timeZone: 'Asia/Kolkata',
  })
    .replace(/(am|pm)/i, (x) => x.toUpperCase());
}
