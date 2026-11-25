import { SITE_API } from './api';
import { getPdfSession } from './session';
import { refreshPurchases } from './purchases';
import { loadCatalog } from './catalog';

/**
 * Cashfree payments, the same way the old PREMium Mind website does it:
 *   1. create_order.php  -> the server makes the order (price comes from the database)
 *   2. Cashfree checkout -> a popup on desktop, a full page on phones (so GPay/PhonePe icons show)
 *   3. verify_payment.php -> the server asks Cashfree if it is really PAID, then gives the course
 * The buyer's identity is the signed login token, never an email typed in the browser.
 */

const SDK_URL = 'https://sdk.cashfree.com/js/v3/cashfree.js';

export class PaymentError extends Error {
  constructor(message, code = '') {
    super(message);
    this.code = code;
  }
}

let sdkPromise = null;
function loadCashfree() {
  if (window.Cashfree) return Promise.resolve(window.Cashfree);
  if (!sdkPromise) {
    sdkPromise = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = SDK_URL;
      s.async = true;
      s.onload = () => resolve(window.Cashfree);
      s.onerror = () => {
        sdkPromise = null;
        reject(new PaymentError('Payment service could not load. Check your internet and try again.', 'sdk'));
      };
      document.head.appendChild(s);
    });
  }
  return sdkPromise;
}

const isMobile = () =>
  /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '') || window.matchMedia('(max-width: 900px)').matches;

/** Cashfree does not allow real payments from localhost, so say so clearly instead of failing. */
export const paymentsBlockedHere = () => ['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname);

async function post(path, body, token) {
  const res = await fetch(`${SITE_API}${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-PM-Session': token },
    body: JSON.stringify(body),
  });
  let data = {};
  try {
    data = await res.json();
  } catch {
    /* not JSON */
  }
  return { res, data };
}

/**
 * Ask the server whether an order is paid. Waits a little while Cashfree is still confirming.
 * Returns { status: 'success' | 'failed' | 'pending', message, courseId }.
 */
export async function verifyOrder(orderId, { tries = 6, delayMs = 2000 } = {}) {
  const token = getPdfSession();
  if (!token) return { status: 'failed', message: 'Please log in again to see your purchase.' };

  let last = { status: 'pending' };
  for (let i = 0; i < tries; i += 1) {
    try {
      const { data } = await post('/verify_payment.php', { order_id: orderId }, token);
      last = { status: data.status || 'failed', message: data.message || '', courseId: data.course_id };
      if (last.status === 'success' || last.status === 'failed') break;
    } catch {
      last = { status: 'pending', message: 'Network problem while checking the payment.' };
    }
    if (i < tries - 1) await new Promise((r) => setTimeout(r, delayMs));
  }
  if (last.status === 'success') {
    await Promise.all([refreshPurchases(), loadCatalog(true)]);
  }
  return last;
}

/**
 * Start paying for one product. Resolves with:
 *   { status: 'success', courseId }  paid and unlocked (desktop popup)
 *   { status: 'redirecting' }        the browser is moving to the payment page (phones)
 *   { status: 'cancelled' }          the buyer closed the payment window
 * and throws PaymentError for everything else (not logged in, already owned, gateway problem...).
 */
export async function payForCourse(courseId) {
  if (paymentsBlockedHere()) {
    throw new PaymentError(
      'Payments do not work on localhost. Open the live website (for example premind.netlify.app) to pay.',
      'localhost'
    );
  }
  const token = getPdfSession();
  if (!token) throw new PaymentError('Please log in first, then buy.', 'login_required');

  const { res, data } = await post(
    '/create_order.php',
    { course_id: Number(courseId), return_origin: window.location.origin, from_app: false },
    token
  );
  if (!res.ok || data.status !== 'success' || !data.payment_session_id) {
    throw new PaymentError(data.message || 'Could not start the payment. Please try again.', data.code || 'create');
  }

  const Cashfree = await loadCashfree();
  const cashfree = Cashfree({ mode: data.env === 'sandbox' ? 'sandbox' : 'production' });
  const target = isMobile() ? '_self' : '_modal';

  const result = await cashfree.checkout({ paymentSessionId: data.payment_session_id, redirectTarget: target });

  if (target === '_self') return { status: 'redirecting' };
  if (result && result.error) {
    // The buyer closed the window, or the payment failed.
    if (/close|cancel|abort/i.test(String(result.error.message || ''))) return { status: 'cancelled' };
    throw new PaymentError(result.error.message || 'Payment failed.', 'failed');
  }
  if (result && result.paymentDetails) {
    const verified = await verifyOrder(data.order_id);
    if (verified.status === 'success') return { status: 'success', courseId: verified.courseId || courseId };
    if (verified.status === 'pending') {
      throw new PaymentError(
        `Payment is being confirmed. Your course will unlock in a minute. Order id: ${data.order_id}`,
        'pending'
      );
    }
    throw new PaymentError(verified.message || 'Payment could not be verified.', 'verify');
  }
  return { status: 'cancelled' };
}
