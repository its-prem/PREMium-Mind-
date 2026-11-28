import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { goBack } from '../lib/goBack';
import { SITE_API, apiPost } from '../lib/api';
import {
  finishRedirectSignIn,
  getAuthorizedDomains,
  googleSignIn,
  signOutFirebase,
} from '../lib/firebase';
import {
  getPdfSession,
  isApiSuccess,
  saveLoginSession,
  setPdfSession,
} from '../lib/session';
import '../styles/theme.css';
import './Login.css';

const isValidPhone = (p) => {
  const digits = String(p || '').replace(/\D/g, '').slice(-10);
  return digits.length === 10 && digits !== '0000000000' && digits !== '9999999999';
};

const last10 = (p) => String(p || '').replace(/\D/g, '').slice(-10);

/** Smoothly grows or shrinks to fit its content (tab switch, messages). */
function AutoHeight({ children }) {
  const innerRef = useRef(null);
  const [height, setHeight] = useState('auto');

  useEffect(() => {
    const el = innerRef.current;
    if (!el) return undefined;
    const measure = () => setHeight(el.offsetHeight);
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <div className="lg-expand" style={{ height }}>
      <div ref={innerRef}>{children}</div>
    </div>
  );
}

function Message({ msg }) {
  if (!msg?.text) return null;
  return (
    <div className={`lg-message ${msg.error ? 'is-error' : 'is-success'}`} role="status">
      {msg.text}
    </div>
  );
}

/** Turn a Firebase error into a clear message that says what to do. */
async function friendlyAuthError(err) {
  const host = window.location.host;
  switch (err?.code) {
    case 'auth/unauthorized-domain': {
      const hostname = window.location.hostname;
      const info = await getAuthorizedDomains();
      let detail;
      if (info.domains) {
        detail = info.domains.includes(hostname)
          ? `Firebase's list DOES contain "${hostname}", so something else is blocking it: check the API key's allowed websites (Google Cloud > APIs & Services > Credentials), and make sure third-party cookies are not blocked in your browser.`
          : `"${hostname}" is not in Firebase's Authorized domains (${info.domains.join(', ')}). Add it in Firebase Console > Authentication > Settings > Authorized domains.`;
      } else {
        detail = `Could not read Firebase's domain list (${info.error}). This usually means the API key blocks this website: check Google Cloud > APIs & Services > Credentials.`;
      }
      return `Google login is not allowed on this address (${host}). ${detail} [auth/unauthorized-domain]`;
    }
    case 'auth/network-request-failed':
      return 'Network problem while contacting Google. Check your internet and try again.';
    case 'auth/web-storage-unsupported':
      return 'Your browser is blocking storage/cookies, so Google login cannot work. Allow cookies for this site or use another browser.';
    case 'auth/operation-not-allowed':
      return 'Google sign-in is switched off in Firebase (Console > Authentication > Sign-in method > Google).';
    case 'auth/invalid-api-key':
    case 'auth/api-key-not-valid.-please-pass-a-valid-api-key.':
      return 'The Firebase API key is not valid. Please contact support.';
    case 'auth/too-many-requests':
      return 'Too many attempts. Please wait a few minutes and try again.';
    case 'auth/internal-error':
      return `Google login hit an internal error. Try again; if it keeps happening, open the site from localhost or an HTTPS address (now: ${host}).`;
    default:
      return `${err?.message || 'Google login failed.'}${err?.code ? ` [${err.code}]` : ''}`;
  }
}

export default function Login() {
  const navigate = useNavigate();
  const [tab, setTab] = useState('login'); // login | register
  const [screen, setScreen] = useState('main'); // main | otp

  const [loginMsg, setLoginMsg] = useState(null);
  const [regMsg, setRegMsg] = useState(null);
  const [otpMsg, setOtpMsg] = useState(null);

  const [googleBusy, setGoogleBusy] = useState(false);
  const [regBusy, setRegBusy] = useState(false);
  const [otpBusy, setOtpBusy] = useState(false);

  const [form, setForm] = useState({ name: '', email: '', phone: '', password: '' });
  const [pendingEmail, setPendingEmail] = useState('');
  const [otp, setOtp] = useState('');
  const redirectChecked = useRef(false);

  const updateField = (key) => (e) =>
    setForm((prev) => ({ ...prev, [key]: e.target.value }));

  const switchTab = (next) => {
    setTab(next);
    setLoginMsg(null);
    setRegMsg(null);
  };

  /** After Google sign-in: confirm the account exists, then save the session. */
  const handleGoogleUser = async (user) => {
    // 1) Signed PDF session token
    let sessionFailure = '';
    try {
      const idToken = await user.getIdToken(true);
      const res = await fetch(`${SITE_API}/get_pdf_session.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_token: idToken }),
      });
      const data = await res.json().catch(() => ({}));
      if (data?.pdf_session) setPdfSession(data.pdf_session);
      else sessionFailure = data?.message || `server answered ${res.status}`;
    } catch (err) {
      console.warn('PDF token generation failed', err);
      sessionFailure = 'could not reach the server';
    }
    // Without the signed session the account details cannot be read, and the page used to think "no account" and send an
    // existing student to Sign Up ("already registered"). Say what went wrong instead.
    if (sessionFailure) {
      try {
        await signOutFirebase();
      } catch {
        /* ignore */
      }
      throw new Error(`Login could not be completed (${sessionFailure}). Please try again in a minute.`);
    }

    // 2) Does this email already have an account?
    const fd = new FormData();
    fd.append('email', user.email);
    let checkData = { exists: false };
    try {
      checkData = await apiPost('/check_user.php', fd);
    } catch {
      throw new Error('Could not verify account. Please try again.');
    }

    // 3) Profile (phone etc.)
    let profile = null;
    try {
      const sessionToken = getPdfSession() || '';
      const res = await fetch(`${SITE_API}/get_user.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-PM-Session': sessionToken,
        },
        body:
          'email=' +
          encodeURIComponent(user.email) +
          '&pdf_session=' +
          encodeURIComponent(sessionToken),
        mode: 'cors',
        credentials: 'omit',
        cache: 'no-store',
      });
      profile = await res.json();
    } catch (err) {
      console.warn('Get User API failed', err);
    }

    const dbName = String(
      checkData?.name ||
        checkData?.user_name ||
        checkData?.full_name ||
        profile?.name ||
        profile?.user_name ||
        profile?.full_name ||
        ''
    ).trim();
    const phone = last10(
      checkData?.phone || checkData?.mobile || profile?.phone || profile?.mobile
    );

    const profileOk = !!(
      profile &&
      (String(profile.status || '').toLowerCase() === 'success' ||
        profile.email ||
        profile.id ||
        profile.user_id ||
        isValidPhone(profile.phone || profile.mobile))
    );
    const accountExists =
      !!(
        checkData &&
        (checkData.exists === true ||
          checkData.exists === 1 ||
          checkData.exists === '1' ||
          checkData.exists === 'true' ||
          String(checkData.status || '').toLowerCase() === 'found' ||
          String(checkData.message || '').toLowerCase().includes('exists'))
      ) ||
      profileOk; // the server found this very email in its users list (even without a phone: e.g. added by the admin)

    // The account IS there (check_user says so) but its details could not be read: that is a server problem, not a new user.
    const checkSaysExists =
      !!checkData && (checkData.exists === true || checkData.exists === 1 || checkData.exists === '1' || checkData.exists === 'true');
    if (checkSaysExists && !profileOk) {
      try {
        await signOutFirebase();
      } catch {
        /* ignore */
      }
      throw new Error(`Your account was found, but its details could not be loaded (${profile?.message || 'server error'}). Please try again in a minute.`);
    }

    // Only a person who is not in the users list at all must Sign Up first. A student the admin added (bulk enrol) has no
    // phone number yet: he can log in; the phone can be added by the admin (Students -> Manage).
    if (!accountExists) {
      setTab('register');
      setForm((prev) => ({
        ...prev,
        email: user.email || '',
        name: dbName || user.displayName || '',
        phone: isValidPhone(phone) ? phone : prev.phone,
      }));
      setRegMsg({
        error: true,
        text: 'Please complete Sign Up first with your name, email and phone number. After OTP verification, use Continue with Google with the same email.',
      });
      try {
        await signOutFirebase();
      } catch {
        /* ignore */
      }
      return;
    }

    const name = dbName || user.displayName || 'User';
    if (!saveLoginSession(user.email, name, phone)) {
      throw new Error('Could not save session. Please allow cookies/storage.');
    }
    setLoginMsg({ error: false, text: 'Login successful. Redirecting...' });
    navigate('/', { replace: true });
  };

  const runGoogleFlow = async (getUser) => {
    setGoogleBusy(true);
    setLoginMsg(null);
    try {
      const user = await getUser();
      if (!user) return; // redirecting away to Google
      await handleGoogleUser(user);
    } catch (err) {
      console.error('Google login error:', err);
      if (err?.code !== 'auth/popup-closed-by-user') {
        setLoginMsg({ error: true, text: await friendlyAuthError(err) });
      }
    } finally {
      setGoogleBusy(false);
    }
  };

  // Finish a signInWithRedirect() round trip (popup-blocked browsers).
  useEffect(() => {
    if (redirectChecked.current) return;
    redirectChecked.current = true;
    finishRedirectSignIn()
      .then((user) => {
        if (user) runGoogleFlow(async () => user);
      })
      .catch(async (err) => {
        console.error('Google redirect sign-in error:', err);
        setLoginMsg({ error: true, text: await friendlyAuthError(err) });
      });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleRegister = async (e) => {
    e.preventDefault();
    setRegBusy(true);
    setRegMsg(null);
    const payload = {
      name: form.name.trim(),
      email: form.email.trim(),
      phone: form.phone.trim(),
      password: form.password,
    };
    try {
      const data = await apiPost('/register_api.php', payload);
      if (isApiSuccess(data)) {
        setPendingEmail(payload.email);
        setOtp('');
        setScreen('otp');
        setOtpMsg({ error: false, text: data.message || 'OTP sent successfully.' });
      } else {
        setRegMsg({
          error: true,
          text: data?.message || data?.error || 'Registration failed.',
        });
      }
    } catch (err) {
      setRegMsg({ error: true, text: err?.message || 'Network error.' });
    } finally {
      setRegBusy(false);
    }
  };

  const handleVerifyOtp = async () => {
    setOtpBusy(true);
    setOtpMsg(null);
    try {
      const data = await apiPost('/verify_otp.php', {
        email: pendingEmail,
        otp: otp.trim(),
      });
      if (isApiSuccess(data)) {
        setScreen('main');
        setTab('login');
        setLoginMsg({
          error: false,
          text: 'Account created successfully. Please sign in with Google using the same email.',
        });
      } else {
        setOtpMsg({ error: true, text: data?.message || 'Invalid OTP.' });
      }
    } catch {
      setOtpMsg({ error: true, text: 'Network error.' });
    } finally {
      setOtpBusy(false);
    }
  };

  return (
    <div className="lg-page">
      <div className="lg-wrapper">
        {/* Left banner (desktop) */}
        <aside className="lg-banner">
          <img src="/small-logo.png" alt="PREMium Mind" />
          <h1>PREMium Mind</h1>
          <p>
            Unlock premium study materials, notes, and expert courses designed
            for your success.
          </p>
        </aside>

        {/* Form panel */}
        <section className="lg-panel">
          <div className="lg-mobile-logo">
            <img src="/small-logo.png" alt="PREMium Mind" />
            <h2>PREMium Mind</h2>
          </div>

          {screen === 'main' && (
            <div className="lg-screen">
              <div className="lg-tabs" data-active={tab} role="tablist">
                <div className="lg-tab-slider" />
                <button
                  type="button"
                  role="tab"
                  aria-selected={tab === 'login'}
                  className={`lg-tab ${tab === 'login' ? 'is-active' : ''}`}
                  onClick={() => switchTab('login')}
                >
                  Login
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-selected={tab === 'register'}
                  className={`lg-tab ${tab === 'register' ? 'is-active' : ''}`}
                  onClick={() => switchTab('register')}
                >
                  Sign Up
                </button>
              </div>

              <AutoHeight>
              {tab === 'login' && (
                <div className="lg-screen">
                  <p className="lg-note lg-note-top">
                    First complete Sign Up with your name, email, phone and
                    password. Then use Continue with Google with the same email.
                  </p>
                  <button
                    type="button"
                    className="lg-google"
                    disabled={googleBusy}
                    onClick={() => runGoogleFlow(googleSignIn)}
                  >
                    {googleBusy ? (
                      'Verifying...'
                    ) : (
                      <>
                        <img
                          src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg"
                          alt=""
                        />
                        Continue with Google
                      </>
                    )}
                  </button>
                  <Message msg={loginMsg} />
                  <p className="lg-note">
                    New user? Open Sign Up, fill every field, verify OTP, then
                    come back to Login.
                  </p>
                </div>
              )}

              {tab === 'register' && (
                <form className="lg-screen" onSubmit={handleRegister}>
                  <div className="lg-field">
                    <label htmlFor="regName">Full Name</label>
                    <input
                      id="regName"
                      type="text"
                      required
                      placeholder="John Doe"
                      autoComplete="name"
                      value={form.name}
                      onChange={updateField('name')}
                    />
                  </div>
                  <div className="lg-field">
                    <label htmlFor="regEmail">Email Address</label>
                    <input
                      id="regEmail"
                      type="email"
                      required
                      placeholder="john@example.com"
                      autoComplete="email"
                      value={form.email}
                      onChange={updateField('email')}
                    />
                  </div>
                  <div className="lg-field">
                    <label htmlFor="regPhone">Phone Number</label>
                    <input
                      id="regPhone"
                      type="tel"
                      required
                      placeholder="10-digit mobile number"
                      pattern="[0-9]{10}"
                      inputMode="numeric"
                      autoComplete="tel"
                      value={form.phone}
                      onChange={updateField('phone')}
                    />
                  </div>
                  <div className="lg-field">
                    <label htmlFor="regPassword">Create Password</label>
                    <input
                      id="regPassword"
                      type="password"
                      required
                      minLength={6}
                      placeholder="Minimum 6 characters"
                      autoComplete="new-password"
                      value={form.password}
                      onChange={updateField('password')}
                    />
                  </div>
                  <button type="submit" className="lg-btn" disabled={regBusy}>
                    {regBusy ? 'Sending OTP...' : 'Create Account'}
                  </button>
                  <Message msg={regMsg} />
                </form>
              )}
              </AutoHeight>
            </div>
          )}

          {screen === 'otp' && (
            <div className="lg-screen">
              <div className="lg-otp-head">
                <span className="lg-otp-icon">
                  <ion-icon name="mail-open-outline"></ion-icon>
                </span>
                <h3>Verify Email</h3>
                <p>
                  We sent a 6-digit OTP to
                  <br />
                  <b>{pendingEmail}</b>
                </p>
              </div>
              <div className="lg-field">
                <input
                  type="text"
                  className="lg-otp-input"
                  inputMode="numeric"
                  maxLength={6}
                  placeholder="123456"
                  aria-label="6-digit OTP"
                  value={otp}
                  onChange={(e) => setOtp(e.target.value.replace(/\D/g, ''))}
                />
              </div>
              <button
                type="button"
                className="lg-btn"
                disabled={otpBusy || otp.length < 4}
                onClick={handleVerifyOtp}
              >
                {otpBusy ? 'Verifying...' : 'Verify & Continue'}
              </button>
              <Message msg={otpMsg} />
              <button
                type="button"
                className="lg-back"
                onClick={() => {
                  setScreen('main');
                  setTab('register');
                }}
              >
                ← Change Email
              </button>
            </div>
          )}

          <button type="button" className="lg-back lg-back-home" onClick={() => goBack(navigate, "/")}>
            <ion-icon name="arrow-back-outline"></ion-icon>
            <span>Back to Home</span>
          </button>
        </section>
      </div>
    </div>
  );
}
