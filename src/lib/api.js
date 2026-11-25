/** Backend (Hostinger PHP) helpers used by login and account pages. */

export const SITE_API = 'https://premind.diplomawallah.in';

/**
 * POST to the PHP backend. Some endpoints only read form-data and some only
 * url-encoded bodies, so both encodings are tried (same as the old site).
 */
export async function apiPost(path, fields) {
  const attempts = [];
  if (fields instanceof FormData) {
    attempts.push(fields);
  } else {
    attempts.push(new URLSearchParams(fields));
    const fd = new FormData();
    Object.keys(fields).forEach((k) => fd.append(k, fields[k] ?? ''));
    attempts.push(fd);
  }

  let lastError = null;
  for (const body of attempts) {
    try {
      const res = await fetch(SITE_API + path, {
        method: 'POST',
        body,
        mode: 'cors',
        credentials: 'omit',
      });
      const text = await res.text();
      try {
        return JSON.parse(text.trim());
      } catch {
        lastError = new Error('Server error. Please try again.');
      }
    } catch (err) {
      lastError = err;
    }
  }
  throw lastError || new Error('Network error. Please try again.');
}
