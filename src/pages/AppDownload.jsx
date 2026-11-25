import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import Navbar from '../components/Navbar';
import Sidebar from '../components/Sidebar';
import {
  clearStoredSession,
  getStoredEmail,
  getStoredName,
  isNativeApp,
} from '../lib/session';
import '../styles/theme.css';
import './AppDownload.css';

const SITE_API = 'https://premind.diplomawallah.in';
const DEFAULT_APK_URL = `${SITE_API}/download_apk.php`;
const DEFAULT_APK_NAME = 'PREMium-Mind.apk';
const APP_PACKAGE = 'com.premiummind.app';
const APP_SCHEME = 'premiummind';

const INSTALL_STEPS = [
  'Download the APK.',
  'Phone Settings → Allow install from unknown sources / this browser.',
  'Open the downloaded file and tap Install.',
  'Open the app, log in, then buy / open your course.',
];

/** Version info: live server first, bundled copy as fallback. */
async function loadVersionInfo(signal) {
  const urls = [
    `${SITE_API}/get_app_version.php?t=${Date.now()}`,
    `/app-version.json?t=${Date.now()}`,
  ];
  let lastErr = null;
  for (const url of urls) {
    try {
      const res = await fetch(url, { cache: 'no-store', signal });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.json();
    } catch (err) {
      if (err?.name === 'AbortError') throw err;
      lastErr = err;
    }
  }
  throw lastErr || new Error('Failed to load version');
}

/** Only keep a plain path/query so ?next= can't inject another URL scheme. */
function cleanNextPath(value) {
  const v = String(value || 'home').replace(/^\/+/, '');
  return /^[\w\-./?=&%]+$/.test(v) ? v : 'home';
}

function withFilename(url, fileName) {
  const join = url.includes('?') ? '&' : '?';
  return `${url}${join}filename=${encodeURIComponent(fileName)}`;
}

export default function AppDownload() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [email] = useState(() => getStoredEmail());
  const [name] = useState(() => getStoredName());
  const [info, setInfo] = useState(null);
  const [loadFailed, setLoadFailed] = useState(false);
  const [hint, setHint] = useState('');
  const inApp = useMemo(() => isNativeApp(), []);

  const user = useMemo(
    () => (email ? { name: name || 'User', email } : null),
    [email, name]
  );

  useEffect(() => {
    const controller = new AbortController();
    loadVersionInfo(controller.signal)
      .then(setInfo)
      .catch((err) => {
        if (err?.name !== 'AbortError') setLoadFailed(true);
      });
    return () => controller.abort();
  }, []);

  const apkName = info?.apkFileName || DEFAULT_APK_NAME;
  const rawApkUrl = info?.apkUrl;
  // Old server records sometimes point at a raw /downloads/*.apk file.
  const apkUrl =
    !rawApkUrl || /\/downloads\/.+\.apk(\?|$)/i.test(rawApkUrl)
      ? DEFAULT_APK_URL
      : rawApkUrl;
  const downloadUrl = withFilename(apkUrl, apkName);
  const changelog = info?.changelog || [];

  const fileHint = loadFailed
    ? 'Could not load version info. Please try again later.'
    : info
      ? `${apkName}${info.minAndroid ? ` · Android ${info.minAndroid}` : ''}`
      : 'Loading version info…';

  const copyLink = async () => {
    try {
      await navigator.clipboard.writeText(downloadUrl);
      setHint('Link copied. Chrome me paste karke download start karo.');
    } catch {
      window.prompt('Copy this link and open it in Chrome:', downloadUrl);
    }
  };

  const shareLink = async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: 'PREMium Mind APK',
          text: 'PREMium Mind app download',
          url: downloadUrl,
        });
        setHint('Chrome choose karo - download start ho jayega.');
      } catch (err) {
        if (err?.name !== 'AbortError') copyLink();
      }
      return;
    }
    copyLink();
  };

  const openInstalledApp = (e) => {
    e.preventDefault();
    const next = cleanNextPath(params.get('next'));
    const isAndroid = /Android/i.test(navigator.userAgent || '');
    if (!isAndroid) {
      setHint('App open karne ka option sirf Android phone par kaam karta hai.');
      return;
    }
    const fallback = `${window.location.origin}/app-download?from=gate`;
    window.location.href =
      `intent://${next}#Intent;scheme=${APP_SCHEME};package=${APP_PACKAGE}` +
      ';action=android.intent.action.VIEW;category=android.intent.category.BROWSABLE' +
      `;S.browser_fallback_url=${encodeURIComponent(fallback)};end`;
  };

  const handleLogout = () => {
    clearStoredSession();
    setSidebarOpen(false);
    navigate('/');
  };

  return (
    <div className="ad-page">
      <Navbar
        activeNav="app"
        onOpenSidebar={() => setSidebarOpen(true)}
        onNavigate={navigate}
      />

      <Sidebar
        isOpen={sidebarOpen}
        onClose={() => setSidebarOpen(false)}
        user={user}
        onLogout={handleLogout}
        onNavigate={navigate}
        onSelectCategory={() => navigate('/')}
      />

      <header className="ad-hero">
        <div className="ad-hero-inner">
          <span className="ad-badge">
            <ion-icon name="phone-portrait-outline"></ion-icon> Android App
          </span>
          <h1>App Version</h1>
          <p>Download or update the latest PREMium Mind Android app.</p>
        </div>
      </header>

      <main className="ad-wrap">
        <section className="ad-card">
          <img
            className="ad-logo"
            src="/small-logo.png"
            alt="PREMium Mind"
          />
          <h2>PREMium Mind App</h2>
          <p className="ad-sub">
            Download or update the latest Android APK. App-only courses require
            the PREMium Mind app to buy and open.
          </p>

          <div className="ad-meta">
            <div className="ad-chip">
              Version <span>{info?.latestVersion || '—'}</span>
            </div>
            <div className="ad-chip">
              Code <span>{info?.versionCode || '—'}</span>
            </div>
            <div className="ad-chip">
              Updated <span>{info?.releaseDate || '—'}</span>
            </div>
          </div>

          <a className="ad-download" href={downloadUrl} rel="noreferrer">
            <ion-icon name="download-outline"></ion-icon>
            <span>Download Latest APK</span>
          </a>
          <p className="ad-hint">{hint || fileHint}</p>

          {inApp && (
            <div className="ad-inapp">
              <p>In-app browser may block redirects. Use one of these options:</p>
              <button type="button" className="ad-btn-blue" onClick={shareLink}>
                Share link → open in Chrome
              </button>
              <button type="button" className="ad-btn-ghost" onClick={copyLink}>
                Copy Download Link
              </button>
            </div>
          )}

          <a href="#open" className="ad-open" onClick={openInstalledApp}>
            App already installed? Open App →
          </a>
        </section>

        <section className="ad-section">
          <h3>
            <ion-icon name="sparkles-outline"></ion-icon> What&apos;s New
          </h3>
          <ul>
            {info ? (
              changelog.length ? (
                changelog.map((line) => <li key={line}>{line}</li>)
              ) : (
                <li>No changelog</li>
              )
            ) : (
              <li>{loadFailed ? 'Unavailable' : 'Loading…'}</li>
            )}
          </ul>
        </section>

        <section className="ad-section">
          <h3>
            <ion-icon name="construct-outline"></ion-icon> Install steps
            (Android)
          </h3>
          <ol className="ad-steps">
            {INSTALL_STEPS.map((step, i) => (
              <li key={step}>
                <b>{i + 1}</b>
                <span>{step}</span>
              </li>
            ))}
          </ol>
        </section>
      </main>
    </div>
  );
}
