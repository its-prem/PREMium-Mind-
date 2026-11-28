import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Navbar from '../components/Navbar';
import Sidebar from '../components/Sidebar';
import { SITE_API } from '../lib/api';
import { clearStoredSession, getPdfSession, getStoredEmail, getStoredName, saveLoginSession } from '../lib/session';
import '../styles/theme.css';
import './AppDownload.css'; // the same page header and card as the App Version page
import './Profile.css';

const digits10 = (p) => String(p || '').replace(/\D/g, '').slice(-10);
const phoneOk = (p) => /^[6-9][0-9]{9}$/.test(digits10(p));

/** "My Profile": the student changes his own name and phone number (nothing else). */
export default function Profile() {
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [email] = useState(() => getStoredEmail());
  const [name, setName] = useState(() => getStoredName() || '');
  const [phone, setPhone] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState(null); // { error, text }

  const user = useMemo(() => (email ? { name: name || 'User', email } : null), [email, name]);

  useEffect(() => {
    document.title = 'My Profile | PREMium Mind';
    const token = getPdfSession();
    if (!email || !token) {
      navigate('/login', { replace: true });
      return undefined;
    }
    const ctrl = new AbortController();
    (async () => {
      try {
        const res = await fetch(`${SITE_API}/get_user.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-PM-Session': token },
          body: `pdf_session=${encodeURIComponent(token)}`,
          cache: 'no-store',
          signal: ctrl.signal,
        });
        const data = await res.json().catch(() => ({}));
        if (res.status === 401) {
          navigate('/login', { replace: true });
          return;
        }
        if (data?.status === 'success') {
          setName(String(data.name || '').trim());
          const p = digits10(data.phone);
          setPhone(p === '0000000000' ? '' : p);
        }
      } catch (err) {
        if (err?.name !== 'AbortError') setMsg({ error: true, text: 'Could not load your details. You can still type them and save.' });
      } finally {
        setLoading(false);
      }
    })();
    return () => ctrl.abort();
  }, [email, navigate]);

  const handleLogout = () => {
    clearStoredSession();
    setSidebarOpen(false);
    navigate('/');
  };

  const save = async (e) => {
    e.preventDefault();
    setMsg(null);
    const cleanName = name.replace(/\s+/g, ' ').trim();
    if (cleanName.length < 2) return setMsg({ error: true, text: 'Please enter your name.' });
    if (!phoneOk(phone)) return setMsg({ error: true, text: 'Please enter a valid 10 digit mobile number.' });
    setSaving(true);
    try {
      const res = await fetch(`${SITE_API}/update_profile.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-PM-Session': getPdfSession() || '' },
        body: JSON.stringify({ name: cleanName, phone: digits10(phone) }),
        cache: 'no-store',
      });
      const data = await res.json().catch(() => ({}));
      if (res.status === 401) {
        navigate('/login', { replace: true });
        return;
      }
      if (!res.ok || data.status !== 'success') {
        setMsg({ error: true, text: data.message || 'Could not save. Please try again.' });
        return;
      }
      saveLoginSession(email, data.name, data.phone); // the name / phone shown around the site follow at once
      setName(data.name);
      setPhone(data.phone);
      setMsg({ error: false, text: 'Saved. Your profile is updated.' });
    } catch {
      setMsg({ error: true, text: 'Could not reach the server. Check your internet and try again.' });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="ad-page">
      <Navbar activeNav="profile" onOpenSidebar={() => setSidebarOpen(true)} onNavigate={navigate} />
      <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} user={user} onLogout={handleLogout} onNavigate={navigate} onSelectCategory={() => navigate('/')} />

      <header className="ad-hero">
        <div className="ad-hero-inner">
          <span className="ad-badge">
            <ion-icon name="person-circle-outline"></ion-icon> Account
          </span>
          <h1>My Profile</h1>
          <p>Keep your name and phone number up to date.</p>
        </div>
      </header>

      <main className="ad-wrap">
        <form className="ad-card pf-card" onSubmit={save} noValidate>
          <div className="pf-avatar" aria-hidden="true">
            {(name || email || '?').trim().charAt(0).toUpperCase()}
          </div>

          <label className="pf-field">
            <span>Email</span>
            <input type="email" value={email || ''} readOnly disabled />
            <small>Your email is your login and cannot be changed here.</small>
          </label>

          <label className="pf-field">
            <span>Name</span>
            <input type="text" value={name} onChange={(e) => setName(e.target.value)} maxLength={60} autoComplete="name" disabled={loading} placeholder="Your full name" />
          </label>

          <label className="pf-field">
            <span>Phone number</span>
            <input type="tel" inputMode="numeric" value={phone} onChange={(e) => setPhone(e.target.value.replace(/[^\d+ ]/g, ''))} maxLength={14} autoComplete="tel" disabled={loading} placeholder="10 digit mobile number" />
            <small>Also shown faintly on the pages you read, so it must be your own number.</small>
          </label>

          {msg && (
            <p className={`pf-msg ${msg.error ? 'bad' : 'ok'}`} role={msg.error ? 'alert' : 'status'}>
              {msg.text}
            </p>
          )}

          <button type="submit" className="pf-save" disabled={saving || loading}>
            {saving ? 'Saving…' : 'Save changes'}
          </button>
        </form>
      </main>
    </div>
  );
}
