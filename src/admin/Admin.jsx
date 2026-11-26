/**
 * Admin shell: checks the login, shows the tab bar (from registry.js) and the open tab.
 * Address: /admin/<tab id>   e.g. /admin/courses
 */
import React, { Suspense, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { adminGet } from './api';
import AdminLogin from './AdminLogin';
import { signOutFirebase } from '../lib/firebase';
import { clearStoredSession } from '../lib/session';
import { TABS } from './registry';
import { Loading, UiProvider } from './ui';
import Icon from './Icon';
import './admin.css';

export default function Admin() {
  const { tab } = useParams();
  const navigate = useNavigate();
  const [me, setMe] = useState(null); // { email, options }
  const [error, setError] = useState(null);

  const load = () => {
    setError(null);
    adminGet('me.php').then(setMe).catch(setError);
  };
  useEffect(() => {
    document.title = 'Admin – PREMium Mind';
    load();
  }, []);

  const current = TABS.find((t) => t.id === tab && t.component) || TABS[0];

  useEffect(() => {
    if (me && tab !== current.id) navigate(`/admin/${current.id}`, { replace: true });
  }, [me, tab, current.id, navigate]);

  if (error) {
    if (error.code === 'unauthenticated' || error.code === 'forbidden') {
      return <AdminLogin notAdmin={error.code === 'forbidden'} message={error.message} onDone={load} />;
    }
    return (
      <div className="adm-gate">
        <div className="adm-gate-card">
          <h2>Cannot open admin</h2>
          <p className="adm-muted">{error.message}</p>
          <div className="adm-row center">
            <button className="adm-btn" onClick={load}>
              Try again
            </button>
            <Link className="adm-btn ghost" to="/">
              Home
            </Link>
          </div>
        </div>
      </div>
    );
  }
  if (!me) return <Loading text="Opening admin…" />;

  const Tab = current.component;
  return (
    <UiProvider>
      <div className="adm-app">
        <aside className="adm-side">
          <div className="adm-brand">
            PREMium <b>Admin</b>
          </div>
          <div className="adm-nav-label">Manage</div>
          <nav>
            {TABS.map((t) =>
              t.href ? (
                <a key={t.id} href={t.href} target="_blank" rel="noreferrer" className="adm-nav">
                  <Icon name={t.icon} />
                  {t.label}
                </a>
              ) : (
                <Link key={t.id} to={`/admin/${t.id}`} className={`adm-nav${t.id === current.id ? ' on' : ''}`}>
                  <Icon name={t.icon} />
                  {t.label}
                  {t.badge && me.badges?.[t.badge] > 0 && <em className="adm-badge">{me.badges[t.badge]}</em>}
                </Link>
              )
            )}
          </nav>
          <div className="adm-side-foot">
            <div className="adm-muted small">{me.email}</div>
            <Link to="/" className="adm-nav">
              <Icon name="home" />Open website
            </Link>
            <button
              className="adm-nav adm-logout"
              onClick={async () => {
                try {
                  await signOutFirebase();
                } catch {
                  /* not signed in to Firebase */
                }
                clearStoredSession();
                window.location.assign('/admin');
              }}
            >
              <Icon name="logout" />Log out
            </button>
          </div>
        </aside>
        <main className="adm-main">
          <header className="adm-head">
            <div>
              <h1 className="adm-title">{current.label}</h1>
              <p className="adm-sub">{current.desc}</p>
            </div>
            <div className="adm-me" title={me.email}>
              <span className="adm-avatar">{me.email.charAt(0).toUpperCase()}</span>
              <span className="adm-me-mail">{me.email}</span>
            </div>
          </header>
          <Suspense fallback={<Loading />}>
            <Tab key={current.id} options={me.options} adminEmail={me.email} />
          </Suspense>
        </main>
      </div>
    </UiProvider>
  );
}
