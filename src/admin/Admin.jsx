/**
 * Admin shell: checks login, renders collapsible sidebar, mobile drawer,
 * Ctrl+K quick-jump palette, and active tab from registry.js.
 * Address: /admin/<tab id>   e.g. /admin/courses
 */
import React, { Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { adminGet } from './api';
import AdminLogin from './AdminLogin';
import { signOutFirebase } from '../lib/firebase';
import { clearStoredSession } from '../lib/session';
import { TABS } from './registry';
import { Loading, UiProvider } from './ui';
import Icon from './Icon';
import './admin.css';
import './admin-dark.css'; // the dark mode (made by scripts/make-admin-dark.mjs)

export default function Admin() {
  const { tab } = useParams();
  const navigate = useNavigate();
  const [me, setMe] = useState(null); // { email, options, badges }
  const [error, setError] = useState(null);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(false);
  const [cmdOpen, setCmdOpen] = useState(false);
  const [cmdQuery, setCmdQuery] = useState('');
  const cmdInputRef = useRef(null);
  // dark mode: remembered on this device, set on <html> so popups and messages follow it too
  const [dark, setDark] = useState(() => {
    try {
      return localStorage.getItem('pm_admin_theme') === 'dark';
    } catch {
      return false;
    }
  });
  useEffect(() => {
    const el = document.documentElement;
    if (dark) el.setAttribute('data-adm-theme', 'dark');
    else el.removeAttribute('data-adm-theme');
    try {
      localStorage.setItem('pm_admin_theme', dark ? 'dark' : 'light');
    } catch {
      /* storage blocked */
    }
    return () => el.removeAttribute('data-adm-theme'); // leaving the admin: the website stays as it is
  }, [dark]);

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
    if (me && tab !== current.id) {
      navigate(`/admin/${current.id}`, { replace: true });
    }
  }, [me, tab, current.id, navigate]);

  // Close mobile drawer on route change
  useEffect(() => {
    setMobileOpen(false);
  }, [current.id]);

  // Global Ctrl+K / Cmd+K shortcut for Quick Jump
  useEffect(() => {
    const onKeyDown = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setCmdOpen((prev) => !prev);
      } else if (e.key === 'Escape') {
        setCmdOpen(false);
        setMobileOpen(false);
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, []);

  useEffect(() => {
    if (cmdOpen) {
      setCmdQuery('');
      setTimeout(() => cmdInputRef.current?.focus(), 40);
    }
  }, [cmdOpen]);

  const filteredTabs = useMemo(() => {
    const q = cmdQuery.trim().toLowerCase();
    if (!q) return TABS;
    return TABS.filter(
      (t) =>
        t.label.toLowerCase().includes(q) ||
        (t.desc && t.desc.toLowerCase().includes(q)) ||
        t.id.toLowerCase().includes(q)
    );
  }, [cmdQuery]);

  const handleLogout = async () => {
    try {
      await signOutFirebase();
    } catch {
      /* not signed in to Firebase */
    }
    clearStoredSession();
    window.location.assign('/admin');
  };

  if (error) {
    if (error.code === 'unauthenticated' || error.code === 'forbidden') {
      return (
        <AdminLogin
          notAdmin={error.code === 'forbidden'}
          message={error.message}
          onDone={load}
        />
      );
    }
    return (
      <div className="adm-gate">
        <div className="adm-gate-card">
          <div className="adm-logo" />
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
      <div className={`adm-app ${collapsed ? 'is-collapsed' : ''}`}>
        {/* Mobile Drawer Backdrop */}
        <div
          className={`adm-side-backdrop ${mobileOpen ? 'is-open' : ''}`}
          onClick={() => setMobileOpen(false)}
          aria-hidden="true"
        />

        {/* Matte Black Executive Sidebar */}
        <aside className={`adm-side ${mobileOpen ? 'is-open' : ''}`}>
          <div className="adm-brand-row">
            <div className="adm-brand">
              <span className="adm-brand-mark">PM</span>
              <span className="adm-brand-text">
                PREMium <b>Admin</b>
              </span>
            </div>

            <button
              type="button"
              className="adm-collapse-btn"
              onClick={() => setCollapsed((c) => !c)}
              title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
              aria-label="Toggle sidebar width"
            >
              {collapsed ? '»' : '«'}
            </button>

            <button
              type="button"
              className="adm-mobile-close"
              onClick={() => setMobileOpen(false)}
              aria-label="Close menu"
            >
              ✕
            </button>
          </div>

          <div className="adm-nav-label">Workspace</div>

          <nav className="adm-nav-list">
            {TABS.map((t) =>
              t.href ? (
                <a
                  key={t.id}
                  href={t.href}
                  target="_blank"
                  rel="noreferrer"
                  className="adm-nav"
                  title={collapsed ? t.label : undefined}
                >
                  <Icon name={t.icon} />
                  <span className="adm-nav-txt">{t.label}</span>
                </a>
              ) : (
                <Link
                  key={t.id}
                  to={`/admin/${t.id}`}
                  className={`adm-nav${t.id === current.id ? ' on' : ''}`}
                  title={collapsed ? t.label : undefined}
                >
                  <Icon name={t.icon} />
                  <span className="adm-nav-txt">{t.label}</span>
                  {t.badge && me.badges?.[t.badge] > 0 && (
                    <em className="adm-badge">{me.badges[t.badge]}</em>
                  )}
                </Link>
              )
            )}
          </nav>

          <div className="adm-side-foot">
            <div className="adm-side-user">
              <span className="adm-avatar small-av">
                {me.email.charAt(0).toUpperCase()}
              </span>
              <div className="adm-side-user-meta">
                <b>Admin</b>
                <span className="adm-ellipsis">{me.email}</span>
              </div>
            </div>

            <Link
              to="/"
              className="adm-nav"
              title={collapsed ? 'Open website' : undefined}
            >
              <Icon name="home" />
              <span className="adm-nav-txt">Open website</span>
            </Link>

            <button
              type="button"
              className="adm-nav adm-logout"
              onClick={handleLogout}
              title={collapsed ? 'Log out' : undefined}
            >
              <Icon name="logout" />
              <span className="adm-nav-txt">Log out</span>
            </button>
          </div>
        </aside>

        {/* Main Workspace */}
        <div className="adm-content-wrap">
          {/* Sticky Glass Topbar */}
          <div className="adm-topbar">
            <div className="adm-topbar-left">
              <button
                type="button"
                className="adm-menu-trigger"
                onClick={() => setMobileOpen(true)}
                aria-label="Open navigation"
              >
                <span />
                <span />
                <span />
              </button>

              <div className="adm-breadcrumb">
                <span className="adm-bc-root">Admin</span>
                <span className="adm-bc-sep">/</span>
                <strong className="adm-bc-curr">{current.label}</strong>
              </div>
            </div>

            <div className="adm-topbar-right">
              <button
                type="button"
                className="adm-cmd-trigger"
                onClick={() => setCmdOpen(true)}
              >
                <span className="adm-cmd-label">Quick jump…</span>
                <kbd>Ctrl K</kbd>
              </button>

              <button type="button" className="adm-theme-btn" aria-label={dark ? 'Light mode' : 'Dark mode'} title={dark ? 'Light mode' : 'Dark mode'} aria-pressed={dark} onClick={() => setDark((d) => !d)}>
                <ion-icon name={dark ? 'sunny-outline' : 'moon-outline'}></ion-icon>
              </button>

              <span className="adm-live-pill" title="Backend connected">
                <i /> Live
              </span>

              <div className="adm-me" title={me.email}>
                <span className="adm-avatar">
                  {me.email.charAt(0).toUpperCase()}
                </span>
                <span className="adm-me-mail">{me.email}</span>
              </div>
            </div>
          </div>

          {/* Active Tab Stage */}
          <main className="adm-main">
            <header className="adm-head">
              <div>
                <h1 className="adm-title">{current.label}</h1>
                {current.desc && <p className="adm-sub">{current.desc}</p>}
              </div>
            </header>

            <div key={current.id} className="adm-tab-enter">
              <Suspense fallback={<Loading />}>
                <Tab
                  key={current.id}
                  options={me.options}
                  adminEmail={me.email}
                />
              </Suspense>
            </div>
          </main>
        </div>

        {/* Ctrl + K Quick Jump Command Palette */}
        {cmdOpen && (
          <div
            className="adm-overlay adm-cmd-overlay"
            onClick={() => setCmdOpen(false)}
          >
            <div
              className="adm-cmd-box"
              onClick={(e) => e.stopPropagation()}
              role="dialog"
              aria-label="Quick Jump"
            >
              <div className="adm-cmd-head">
                <input
                  ref={cmdInputRef}
                  type="text"
                  className="adm-cmd-input"
                  placeholder="Type a section name (e.g. courses, students, orders)…"
                  value={cmdQuery}
                  onChange={(e) => setCmdQuery(e.target.value)}
                />
                <button
                  type="button"
                  className="adm-x"
                  onClick={() => setCmdOpen(false)}
                >
                  ✕
                </button>
              </div>

              <div className="adm-cmd-list">
                {filteredTabs.length === 0 ? (
                  <div className="adm-cmd-empty">No matching sections</div>
                ) : (
                  filteredTabs.map((t) => (
                    <button
                      key={t.id}
                      type="button"
                      className={`adm-cmd-item ${
                        t.id === current.id ? 'is-curr' : ''
                      }`}
                      onClick={() => {
                        setCmdOpen(false);
                        if (t.href) {
                          window.open(t.href, '_blank', 'noreferrer');
                        } else {
                          navigate(`/admin/${t.id}`);
                        }
                      }}
                    >
                      <span className="adm-cmd-ico">
                        <Icon name={t.icon} />
                      </span>
                      <span className="adm-cmd-copy">
                        <b>{t.label}</b>
                        {t.desc && <small>{t.desc}</small>}
                      </span>
                      {t.id === current.id && (
                        <span className="adm-chip">Active</span>
                      )}
                    </button>
                  ))
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </UiProvider>
  );
}
