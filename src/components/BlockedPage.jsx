import React from 'react';
import { WHATSAPP_NUMBER } from '../lib/resumeService';
import './Blocked.css';

/**
 * Full-screen page for a visitor who is blocked by the admin (Admin -> Students / IP block):
 *   why = 'user'  the student's account is blocked
 *   why = 'ip'    the network address is blocked
 * The server decides (maintenance_status.php answers "blocked"); the server files refuse a blocked visitor as well.
 */
export default function BlockedPage({ why, whatsapp }) {
  const phone = String(whatsapp || WHATSAPP_NUMBER || '').replace(/\D/g, '');
  const title = why === 'ip' ? 'Access from this network is blocked' : 'Your account is blocked';
  const text =
    why === 'ip'
      ? 'This website cannot be used from this internet connection right now. If you think this is a mistake, contact support.'
      : 'Your account has been blocked, so you cannot use PREMium Mind. If you think this is a mistake, contact support.';
  return (
    <div className="bk-page" role="alert">
      <div className="bk-card">
        <div className="bk-icon" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="9" />
            <path d="M5.6 5.6l12.8 12.8" />
          </svg>
        </div>
        <h1>{title}</h1>
        <p>{text}</p>
        {phone && (
          <a className="bk-btn" href={`https://wa.me/${phone}?text=${encodeURIComponent('Hello, my access to PREMium Mind is blocked. Please check.')}`} target="_blank" rel="noopener noreferrer">
            Contact support on WhatsApp
          </a>
        )}
      </div>
    </div>
  );
}
