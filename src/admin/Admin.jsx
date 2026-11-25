/**
 * Admin shell: checks the login, shows the tab bar (from registry.js) and the open tab.
 * Address: /admin/<tab id>   e.g. /admin/courses
 */
import React, { Suspense, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { adminGet } from './api';
import { TABS } from './registry';
import { Loading, UiProvider } from './ui';
import Icon from './Icon';
import './admin.css';

export default function Admin() {
  const { tab } = useParams();
  const navigate = useNavigate();
  const [me, setMe] = useState(null); // { email, options }
  const [error, setError] = useState(null);

  useEffect(() => {
    document.title = 'Admin – PREMium Mind';
    adminGet('me.php').then(setMe).catch(setError);
  }, []);

  const current = TABS.find((t) => t.id === tab) || TABS[0];

  useEffect(() => {
    if (me && tab !== current.id) navigate(`/admin/${current.id}`, { replace: true });
  }, [me, tab, current.id, navigate]);

  if (error) {
    const needLogin = error.code === 'unauthenticated';
    return (
      <div className="adm-gate">
        <div className="adm-gate-card">
          <h2>{needLogin ? 'Please log in' : error.code === 'forbidden' ? 'Not allowed' : 'Cannot open admin'}</h2>
          <p className="adm-muted">
            {needLogin
              ? 'Log in with your admin Google account, then open this page again.'
              : error.message}
          </p>
          <div className="adm-row center">
            {needLogin && (
              <Link className="adm-btn" to="/login">
                Go to login
              </Link>
            )}
            <button className="adm-btn ghost" onClick={() => window.location.reload()}>
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
          <nav>
            {TABS.map((t) => (
              <Link key={t.id} to={`/admin/${t.id}`} className={`adm-nav${t.id === current.id ? ' on' : ''}`}>
                <Icon name={t.icon} />
                {t.label}
              </Link>
            ))}
          </nav>
          <div className="adm-side-foot">
            <div className="adm-muted small">{me.email}</div>
            <Link to="/" className="adm-nav">
              <Icon name="home" />Open website
            </Link>
          </div>
        </aside>
        <main className="adm-main">
          <h1 className="adm-title">
            {current.label}
          </h1>
          <Suspense fallback={<Loading />}>
            <Tab key={current.id} options={me.options} adminEmail={me.email} />
          </Suspense>
        </main>
      </div>
    </UiProvider>
  );
}
