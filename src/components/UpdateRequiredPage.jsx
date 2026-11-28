import React from 'react';
import { SITE_API } from '../lib/api';
import './Blocked.css';

/**
 * Shown inside an OLD version of the Android app when the admin has switched on "Force app update"
 * (Admin -> Site look). The new app has window.PMAppSigner; the old one does not.
 */
export default function UpdateRequiredPage() {
  const apk = `${SITE_API}/download_apk.php`;
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(apk);
      alert('Link copied. Open it in Chrome to download the new app.');
    } catch {
      window.prompt('Copy this link and open it in Chrome:', apk);
    }
  };
  return (
    <div className="bk-page" role="alert">
      <div className="bk-card">
        <div className="bk-icon" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 3v12M7 10l5 5 5-5" />
            <path d="M5 20h14" />
          </svg>
        </div>
        <h1>Please update the app</h1>
        <p>This is an old version of PREMium Mind. Download the new app, install it over this one, and open it again. Your account and courses stay as they are.</p>
        <a className="bk-btn" href={apk}>
          Download the new app
        </a>
        <button type="button" className="bk-btn" style={{ marginTop: 10, background: 'transparent', color: 'inherit', border: '1px solid currentColor' }} onClick={copy}>
          Copy download link
        </button>
      </div>
    </div>
  );
}
