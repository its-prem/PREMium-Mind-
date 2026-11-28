import React, { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { getStoredEmail, isNativeApp } from '../lib/session';
import './AppLanding.css';

/**
 * The first screen of the Android app: the logo, one line about PREMium Mind and two buttons.
 * Shown ONCE (on the home page, only inside the app, only for someone who is not logged in); after that it never comes
 * back. To look at it in a browser open the site with  ?landing=1  (it also ignores the "seen" mark then).
 * To remove it: delete this file, AppLanding.css and the <AppLanding /> line in main.jsx.
 */
const KEY = 'pm_app_landing_seen';
const FEATURES = [
  ['document-text-outline', 'Notes & PDFs'],
  ['help-circle-outline', 'Important Questions'],
  ['create-outline', 'Resume maker'],
];

const seen = () => {
  try {
    return localStorage.getItem(KEY) === '1';
  } catch {
    return false;
  }
};

export default function AppLanding() {
  const { pathname, search } = useLocation();
  const navigate = useNavigate();
  const preview = new URLSearchParams(search).get('landing') === '1';
  const [open, setOpen] = useState(true);
  const should = pathname === '/' && (preview || (isNativeApp() && !seen() && !getStoredEmail()));
  if (!open || !should) return null;

  const done = (to) => {
    try {
      localStorage.setItem(KEY, '1');
    } catch {
      /* storage blocked */
    }
    setOpen(false);
    navigate(to, { replace: true });
  };

  return (
    <div className="al-page" role="dialog" aria-label="Welcome to PREMium Mind">
      <div className="al-glow" aria-hidden="true" />
      <div className="al-inner">
        <img className="al-logo" src="/app-logo-512.png" alt="PREMium Mind" width="200" height="200" />
        <h1 className="al-title">
          PREM<em>ium</em> Mind
        </h1>
        <p className="al-tag">Wisdom on Demand</p>
        <p className="al-sub">Notes, important questions and tools made for diploma students, all in one app.</p>
        <ul className="al-feats">
          {FEATURES.map(([icon, label]) => (
            <li key={label}>
              <ion-icon name={icon}></ion-icon>
              <span>{label}</span>
            </li>
          ))}
        </ul>
      </div>
      <div className="al-actions">
        <button type="button" className="al-btn al-primary" onClick={() => done('/')}>
          Get started
        </button>
        <button type="button" className="al-btn al-ghost" onClick={() => done('/login')}>
          I already have an account · Log in
        </button>
      </div>
    </div>
  );
}
