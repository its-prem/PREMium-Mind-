/**
 * The "Secure checkout" popup. Used for a product (store) and for the micro format of an Important Questions product.
 *
 * item = {
 *   courseId, kind: 'course' | 'micro',
 *   title, price, mrp,
 *   showTnc, tncText,                         terms box (only when showTnc is true)
 *   download: { allow_download, download_at } the download chip; leave out for the plain "Instant access" chip
 *   openLabel, doneText                       texts of the success screen
 *   slideToPay                                true = a "slide to pay" handle instead of the Pay button
 * }
 * onOpen(item) runs when the buyer presses the button on the success screen. onClose() closes the popup.
 */
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getPdfSession, getStoredEmail, getStoredName } from '../lib/session';
import { payForCourse, paymentsBlockedHere } from '../lib/payments';
import { DEFAULT_TNC, parseTerms } from '../lib/defaultTerms';
import { downloadState, formatWhen } from '../lib/downloadMode';
import SlideToPay from './SlideToPay';

export default function CheckoutModal({ item, onClose, onOpen }) {
  const navigate = useNavigate();
  const [termsOpen, setTermsOpen] = useState(false); // false = Order summary tab, true = Terms tab
  const [agree, setAgree] = useState(false);
  const [payState, setPayState] = useState('idle'); // idle | paying | done
  const [payMsg, setPayMsg] = useState('');

  if (!item) return null;

  const loggedIn = !!(getStoredEmail() && getPdfSession());
  const price = Number(item.price) || 0;
  const mrp = Number(item.mrp) || 0;
  const saved = mrp > price ? mrp - price : 0;
  const percent = saved ? Math.round((saved / mrp) * 100) : 0;
  const needsTerms = !!item.showTnc;
  const dl = item.download ? downloadState(item.download) : null;

  const close = () => {
    if (payState === 'paying') return; // do not close while the payment window is open
    onClose();
  };

  // Cashfree payment (price and buyer are decided on the server)
  const startPay = async () => {
    setPayState('paying');
    setPayMsg('');
    try {
      const result = await payForCourse(item.courseId, { kind: item.kind || 'course' });
      if (result.status === 'success') setPayState('done');
      else if (result.status === 'redirecting') return; // the page is moving to the payment screen
      else setPayState('idle'); // the buyer closed the payment window
    } catch (err) {
      setPayState('idle');
      setPayMsg(err.message || 'Payment could not start. Please try again.');
    }
  };

  return (
    <div className="pm-modal-overlay" onClick={close}>
      <div className="pm-modal-card checkout-card" role="dialog" aria-modal="true" aria-label="Secure checkout" onClick={(e) => e.stopPropagation()}>
        <div className="co-head">
          <div className="co-head-top">
            <span className="co-secure">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
              Secure checkout
            </span>
            <button type="button" className="co-close" onClick={close} aria-label="Close">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
          </div>
          <h4>{item.title}</h4>
        </div>

        {payState !== 'done' ? (
          <>
            {needsTerms && (
              <div className="co-tabs" role="tablist">
                <button type="button" role="tab" aria-selected={!termsOpen} className={`co-tab${!termsOpen ? ' on' : ''}`} onClick={() => setTermsOpen(false)}>
                  Order summary
                </button>
                <button type="button" role="tab" aria-selected={termsOpen} className={`co-tab${termsOpen ? ' on' : ''}`} onClick={() => setTermsOpen(true)}>
                  Terms
                </button>
              </div>
            )}

            <div className="co-main">
              {!termsOpen || !needsTerms ? (
                <>
                  <div className="co-price">
                    <div className="co-price-main">
                      <span className="co-label">Total payable</span>
                      <div className="co-amount">
                        <strong>₹{price}</strong>
                        {mrp > price && <s>₹{mrp}</s>}
                      </div>
                    </div>
                    {saved > 0 && (
                      <div className="co-off">
                        <b>{percent}% OFF</b>
                        <small>You save ₹{saved}</small>
                      </div>
                    )}
                  </div>

                  <ul className="co-trust">
                    {/* Download rule, decided in the admin: instant / unlocks at a time / not allowed */}
                    {dl ? (
                      <li className={`co-dl ${dl.mode}`}>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                          {dl.mode === 'none' ? <><circle cx="12" cy="12" r="9" /><path d="M5.6 5.6l12.8 12.8" /></> : dl.mode === 'timed' ? <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></> : <><path d="M12 4v11M7 11l5 5 5-5M5 20h14" /></>}
                        </svg>
                        {dl.mode === 'instant' ? 'Instant download' : dl.mode === 'timed' ? `Download ${formatWhen(dl.at)}` : 'Download not allowed'}
                      </li>
                    ) : (
                      <li>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" /></svg>
                        Instant access
                      </li>
                    )}
                    <li>
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3" /><path d="M3 10h18" /></svg>
                      UPI &amp; Cards
                    </li>
                    <li>
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.4 8.2-8 9-4.6-.8-8-4-8-9V6l8-3z" /><path d="M9 12l2 2 4-4" /></svg>
                      Secure
                    </li>
                  </ul>

                  {paymentsBlockedHere() && <p className="co-note">Payments work only on the live website (not on localhost).</p>}
                </>
              ) : (
                <ol className="co-tlist">
                  {parseTerms(item.tncText || DEFAULT_TNC).map((t, i) => (
                    <li key={i}>
                      <span className="co-tno">{i + 1}</span>
                      <p>
                        {t.title && <strong>{t.title}</strong>}
                        {t.text}
                      </p>
                    </li>
                  ))}
                </ol>
              )}
            </div>

            {/* Always visible at the bottom: the terms tick (only when the admin turned the terms ON) and the pay button */}
            <div className="co-bar">
              {!loggedIn && <p className="co-alert">Please log in or sign up to buy this.</p>}
              {payMsg && <p className="co-alert">{payMsg}</p>}

              {needsTerms && (
                <label className={`co-agree-row${agree ? ' on' : ''}`}>
                  <input type="checkbox" checked={agree} onChange={(e) => setAgree(e.target.checked)} />
                  <span className="co-check" aria-hidden="true">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12l5 5 9-10" /></svg>
                  </span>
                  <span>
                    I agree to the{' '}
                    <button
                      type="button"
                      className="co-linkbtn"
                      onClick={(e) => {
                        e.preventDefault();
                        setTermsOpen(true);
                      }}
                    >
                      Terms &amp; Conditions
                    </button>
                  </span>
                </label>
              )}

              {loggedIn && item.slideToPay ? (
                <SlideToPay
                  label={needsTerms && !agree ? 'Accept terms to pay' : `Slide to pay ₹${price}`}
                  disabled={needsTerms && !agree ? true : paymentsBlockedHere()}
                  busy={payState === 'paying'}
                  onComplete={startPay}
                />
              ) : loggedIn ? (
                <button type="button" className="co-pay" disabled={payState === 'paying' || (needsTerms && !agree) || paymentsBlockedHere()} onClick={startPay}>
                  {payState === 'paying' ? 'Opening payment…' : needsTerms && !agree ? 'Accept terms to pay' : `Pay ₹${price} securely`}
                </button>
              ) : (
                <button type="button" className="co-pay" onClick={() => navigate('/login')}>
                  Login / Sign Up
                </button>
              )}
              <p className="co-foot">🔒 Secured by Cashfree · We never see your card or UPI details</p>
            </div>
          </>
        ) : (
          <div className="co-body co-done">
            <div className="co-tick" aria-hidden="true">
              <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12l5 5 9-10" /></svg>
            </div>
            <h4>Payment successful!</h4>
            <p className="co-note">Thank you, {getStoredName() || 'Student'}. {item.doneText || 'Your access is unlocked.'}</p>
            <button type="button" className="co-pay" onClick={() => onOpen?.(item)}>
              {item.openLabel || 'Open & read now'}
            </button>
            <p className="co-note">You can also find it in <b>My Courses</b>.</p>
          </div>
        )}
      </div>
    </div>
  );
}
