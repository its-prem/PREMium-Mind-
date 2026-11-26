/**
 * Login screen of the admin panel: "Sign in with Google", then the server checks that the account is an admin.
 * Uses the same Firebase login and the same signed token as the website (nothing extra to set up).
 */
import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { SITE_API } from '../lib/api';
import { finishRedirectSignIn, googleSignIn, signOutFirebase } from '../lib/firebase';
import { clearStoredSession, setPdfSession } from '../lib/session';

function explain(err) {
  const code = err?.code || '';
  if (code === 'auth/popup-closed-by-user' || code === 'auth/cancelled-popup-request') return 'The Google window was closed. Please try again.';
  if (code === 'auth/unauthorized-domain') return 'This website address is not allowed for Google login yet (Firebase → Authentication → Settings → Authorized domains).';
  if (code === 'auth/network-request-failed') return 'No internet connection. Please try again.';
  return err?.message || 'Sign-in failed. Please try again.';
}

/** Turns a Google sign-in into the website's signed login token. */
async function exchangeForToken(user) {
  const idToken = await user.getIdToken(true);
  const res = await fetch(`${SITE_API}/get_pdf_session.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_token: idToken }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || !data.pdf_session) throw new Error(data.message || 'The server could not verify your Google login.');
  setPdfSession(data.pdf_session);
}

export default function AdminLogin({ onDone, notAdmin, message }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const handled = useRef(false);

  const finish = async (user) => {
    await exchangeForToken(user);
    onDone();
  };

  // coming back from a full-page Google redirect
  useEffect(() => {
    if (handled.current) return;
    handled.current = true;
    finishRedirectSignIn()
      .then((user) => (user ? (setBusy(true), finish(user)) : null))
      .catch((e) => {
        setBusy(false);
        setError(explain(e));
      });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const signIn = async () => {
    setBusy(true);
    setError('');
    try {
      const user = await googleSignIn();
      if (user) await finish(user); // null = the page is moving to Google, it will come back
    } catch (e) {
      setError(explain(e));
      setBusy(false);
    }
  };

  const useAnother = async () => {
    try {
      await signOutFirebase();
    } catch {
      /* not signed in to Firebase */
    }
    clearStoredSession();
    window.location.assign('/admin');
  };

  return (
    <div className="adm-gate">
      <div className="adm-gate-card">
        <div className="adm-logo" />
        <h2>{notAdmin ? 'Not an admin account' : 'Admin login'}</h2>
        <p className="adm-muted">
          {notAdmin
            ? message || 'This Google account is not allowed in the admin panel. Sign in with an admin account.'
            : 'Sign in with your admin Google account to manage PREMium Mind.'}
        </p>
        {error && <div className="adm-alert">{error}</div>}
        <button className="adm-google" onClick={signIn} disabled={busy}>
          <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z" />
            <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 8 3l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" />
            <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z" />
            <path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z" />
          </svg>
          {busy ? 'Signing in…' : 'Sign in with Google'}
        </button>
        {notAdmin && (
          <button className="adm-btn ghost" style={{ marginTop: 10, width: '100%' }} onClick={useAnother}>
            Use another account
          </button>
        )}
        <div className="adm-row center" style={{ marginTop: 14 }}>
          <Link className="adm-muted small" to="/">
            ← Back to website
          </Link>
        </div>
      </div>
    </div>
  );
}
