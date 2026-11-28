/**
 * Proof that a request comes from the real Android app (so "app only" products open only there).
 *
 * The Android app has a native object  window.PMAppSigner  (premium-mind-app: AppSigner.java). It holds a secret that is
 * NOT in this website. For every request to our own server it makes a signature:
 *     X-PM-App-Ts   = unix seconds
 *     X-PM-App-Sign = HMAC-SHA256("<ts>|<METHOD>|<path>", secret)
 * and the server (server/pm_pdf_access.php, pm_app_signature_valid) checks it. In a normal browser PMAppSigner does not
 * exist, so nothing is added and "app only" products stay closed there.
 *
 * installAppSigning() wraps window.fetch once (main.jsx). Nothing else in the website has to know about it.
 */
import { SITE_API } from './api';

// only the server files that decide 'app only' get the signature (the others do not allow the extra headers)
const SIGNED_PATHS = new Set(['/create_order.php', '/qa_text.php', '/get_pdf_token.php', '/proxy_pdf.php', '/secure_page_image.php', '/secure_pdf.php']);

export function installAppSigning() {
  if (typeof window === 'undefined' || window.__pmFetchSigned) return;
  window.__pmFetchSigned = true;
  let apiOrigin = '';
  try {
    apiOrigin = new URL(SITE_API).origin;
  } catch {
    return;
  }
  const nativeFetch = window.fetch.bind(window);

  window.fetch = (input, init) => {
    // looked up on every request: the app may attach its native object a moment after the page started
    const signer = window.PMAppSigner;
    if (!signer || typeof signer.sign !== 'function') return nativeFetch(input, init);
    try {
      const url = new URL(typeof input === 'string' ? input : input instanceof Request ? input.url : String(input), window.location.href);
      if (url.origin === apiOrigin && SIGNED_PATHS.has(url.pathname)) {
        const method = String((init && init.method) || (input instanceof Request ? input.method : 'GET')).toUpperCase();
        // the native side answers "<ts>:<hex>"
        const out = String(signer.sign(method, url.pathname));
        const i = out.indexOf(':');
        if (i > 0) {
          const headers = new Headers((init && init.headers) || (input instanceof Request ? input.headers : undefined));
          headers.set('X-PM-App-Ts', out.slice(0, i));
          headers.set('X-PM-App-Sign', out.slice(i + 1));
          return nativeFetch(input, { ...(init || {}), headers });
        }
      }
    } catch {
      /* signing must never break a request */
    }
    return nativeFetch(input, init);
  };
}
