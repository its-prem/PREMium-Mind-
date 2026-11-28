import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { getProduct } from '../data/storeData';
import { loadCatalog } from '../lib/catalog';
import { verifyOrder } from '../lib/payments';
import { getPdfSession } from '../lib/session';
import './PaymentReturn.css';

const SUPPORT_WHATSAPP = 'https://wa.me/918709911016';

/**
 * Where Cashfree sends the buyer back (phones use a full-page payment, so this page is the finish line).
 * It asks the server whether the order is really PAID, then shows the result.
 */
export default function PaymentReturn() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const orderId = params.get('oid') || params.get('order_id') || '';
  const courseId = params.get('cid') || '';
  const [result, setResult] = useState({ status: 'checking' });

  useEffect(() => {
    document.title = 'Payment | PREMium Mind';
    if (!orderId) {
      navigate('/', { replace: true });
      return undefined;
    }
    if (!getPdfSession()) {
      setResult({ status: 'login', message: 'Please log in again to see your purchase.' });
      return undefined;
    }
    let dead = false;
    (async () => {
      await loadCatalog(); // so the product (title, type) is known
      const r = await verifyOrder(orderId, { tries: 8, delayMs: 2500 });
      if (!dead) setResult(r);
    })();
    return () => {
      dead = true;
    };
  }, [orderId, navigate]);

  const product = getProduct(result.courseId || courseId);

  const isMicro = (result.kind || params.get('kind')) === 'micro';

  const openNow = () => {
    if (isMicro) {
      navigate(`/microsheet/${result.courseId || courseId}`);
      return;
    }
    if (product && product.hasQa) {
      navigate(`/qa/${product.id}`, { replace: true });
      return;
    }
    if (!product) {
      navigate('/lecture', { replace: true });
      return;
    }
    if (product.category === 'resume') {
      navigate(`/resume-editor/${product.id}`, { replace: true });
      return;
    }
    if (product.pdfFile) {
      const q = new URLSearchParams({ file: product.pdfFile, title: product.title });
      try {
        sessionStorage.setItem('pmWatchReturn', '/lecture');
      } catch {
        /* storage blocked */
      }
      navigate(`/watch?${q.toString()}`, { replace: true });
      return;
    }
    navigate('/lecture', { replace: true });
  };

  return (
    <main className="pr-wrap">
      <div className="pr-card">
        {result.status === 'checking' && (
          <>
            <div className="pr-spin" aria-hidden="true" />
            <h1>Checking your payment...</h1>
            <p>Please do not close this page. It takes a few seconds.</p>
          </>
        )}

        {result.status === 'success' && (
          <>
            <div className="pr-icon is-ok"><ion-icon name="checkmark-circle"></ion-icon></div>
            <h1>Payment Successful!</h1>
            <p>{isMicro ? 'Your micro format is unlocked.' : product ? <><b>{product.title}</b> is now in your account.</> : 'Your course is unlocked.'}</p>
            <button type="button" className="pr-btn is-dark" onClick={openNow}>
              {isMicro ? 'Open Micro Format' : product && product.category === 'resume' ? 'Open Template Editor' : 'Open Now'}
            </button>
            <Link to="/lecture" className="pr-btn">My Courses</Link>
          </>
        )}

        {result.status === 'pending' && (
          <>
            <div className="pr-icon is-wait"><ion-icon name="time-outline"></ion-icon></div>
            <h1>Confirming your payment</h1>
            <p>
              The confirmation from the bank can take a little time. Your course will unlock by itself in 1-2 minutes
              (check My Courses). Order id: <code>{orderId}</code>
            </p>
            <Link to="/lecture" className="pr-btn is-dark">My Courses</Link>
            <a className="pr-btn" href={`${SUPPORT_WHATSAPP}?text=${encodeURIComponent(`Mera payment pending hai. Order id: ${orderId}`)}`} target="_blank" rel="noreferrer">
              WhatsApp Support
            </a>
          </>
        )}

        {result.status === 'login' && (
          <>
            <div className="pr-icon is-wait"><ion-icon name="log-in-outline"></ion-icon></div>
            <h1>Login required</h1>
            <p>{result.message}</p>
            <Link to="/login" className="pr-btn is-dark">Login</Link>
          </>
        )}

        {result.status === 'failed' && (
          <>
            <div className="pr-icon is-bad"><ion-icon name="close-circle"></ion-icon></div>
            <h1>Payment was not completed</h1>
            <p>{result.message || 'The payment was cancelled or failed. If money was deducted, it is refunded within 24 hours.'}</p>
            <Link to="/#our-store" className="pr-btn is-dark">Back to Store</Link>
            <a className="pr-btn" href={`${SUPPORT_WHATSAPP}?text=${encodeURIComponent(`Payment issue. Order id: ${orderId}`)}`} target="_blank" rel="noreferrer">
              WhatsApp Support
            </a>
          </>
        )}
      </div>
    </main>
  );
}
