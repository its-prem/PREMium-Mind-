/**
 * The micro format (A4 pages) of an Important Questions product  (/microsheet/:id).
 *
 * What the student sees is like the PDF reader: the same header, and only the finished A4 pages (no editor, no text box).
 * The questions text is loaded into the page but never shown as text; selecting, copying and right-click are blocked,
 * the pages carry the student's own details faintly and blur when the window loses focus.
 * Download (PDF / Word) opens at the time the admin set.
 * Only for people who paid for the micro format.
 */
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { SITE_API } from '../lib/api';
import { downloadState, formatWhen } from '../lib/downloadMode';
import { fetchPhone } from '../lib/pdfApi';
import { getPdfSession, getStoredEmail } from '../lib/session';
import MicroSheetFrame from '../microsheet/MicroSheetFrame';
import './Watch.css'; // the header is the PDF reader's header
import './MicroSheetPage.css';

const THEME_KEY = 'pm_reader_dark'; // the same light / dark choice as the PDF reader

export default function MicroSheetPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const frameRef = useRef(null);
  const [state, setState] = useState({ status: 'loading' });
  const [now, setNow] = useState(() => Date.now());
  const [away, setAway] = useState(false); // the window is not in focus: blur the pages
  const [wm, setWm] = useState(() => getStoredEmail() || '');
  const [toast, setToast] = useState('');
  const [dark, setDark] = useState(() => {
    try {
      return localStorage.getItem(THEME_KEY) === '1';
    } catch {
      return false;
    }
  });
  const toggleDark = () =>
    setDark((prev) => {
      const next = !prev;
      try {
        localStorage.setItem(THEME_KEY, next ? '1' : '0');
      } catch {
        /* storage blocked */
      }
      return next;
    });

  const load = useCallback(async () => {
    const token = getPdfSession();
    if (!token) {
      navigate('/login', { replace: true });
      return;
    }
    try {
      const res = await fetch(`${SITE_API}/qa_text.php?course_id=${encodeURIComponent(id)}`, { headers: { 'X-PM-Session': token }, cache: 'no-store' });
      const data = await res.json().catch(() => ({}));
      if (res.status === 401) {
        navigate('/login', { replace: true });
        return;
      }
      if (!res.ok || data.status !== 'success') {
        setState({ status: 'error', message: data.message || 'Could not open this page.' });
        return;
      }
      if (!data.micro?.enabled || !data.micro?.owned) {
        navigate(`/qa/${id}`, { replace: true }); // not bought: back to the questions, where the buy button is
        return;
      }
      setState({ status: 'ready', title: data.title, text: data.text, micro: data.micro });
    } catch {
      setState({ status: 'error', message: 'Could not reach the server. Check your internet and try again.' });
    }
  }, [id, navigate]);

  useEffect(() => {
    document.title = 'Micro format | PREMium Mind';
    load();
  }, [load]);

  // the student's phone number goes into the faint details on the pages
  useEffect(() => {
    const email = getStoredEmail();
    if (!email) return;
    fetchPhone(email).then((phone) => setWm(`${email} · ${phone}`));
  }, []);

  // re-check the unlock time every 15 seconds, so the download opens by itself
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 15000);
    return () => clearInterval(t);
  }, []);

  // blur the pages while the window / tab is not in focus (a screenshot deterrent, like the PDF reader)
  useEffect(() => {
    const check = () => setAway(document.hidden || !document.hasFocus());
    const later = () => setTimeout(check, 80); // focus moving into the frame must not count as "away"
    window.addEventListener('blur', later);
    window.addEventListener('focus', check);
    document.addEventListener('visibilitychange', check);
    return () => {
      window.removeEventListener('blur', later);
      window.removeEventListener('focus', check);
      document.removeEventListener('visibilitychange', check);
    };
  }, []);

  useEffect(() => {
    if (!toast) return undefined;
    const t = setTimeout(() => setToast(''), 3200);
    return () => clearTimeout(t);
  }, [toast]);

  if (state.status === 'loading') return <div className="ms-page ms-center">Opening the micro format…</div>;
  if (state.status === 'error') {
    return (
      <div className="ms-page ms-center">
        <p>{state.message}</p>
        <button type="button" className="ms-btn" onClick={load}>
          Try again
        </button>
      </div>
    );
  }

  const dl = downloadState({ allow_download: state.micro.allowDownload, download_at: state.micro.downloadAt }, now);
  const canDownload = dl.mode === 'instant';
  const lockedMessage = dl.mode === 'timed' ? `Download unlocks ${formatWhen(dl.at)}` : 'Download is not allowed for this micro sheet';

  const download = (format) => {
    if (!canDownload) {
      setToast(lockedMessage);
      return;
    }
    frameRef.current?.send({ type: 'export', format });
  };

  return (
    <div className={`rd-root ms-root${dark ? ' is-dark' : ''}`} onContextMenu={(e) => e.preventDefault()}>
      <header className="rd-header">
        <div className="rd-header-start">
          <button type="button" className="rd-back" aria-label="Back to the questions" onClick={() => navigate(`/qa/${id}`)}>
            <span className="rd-back-icon" aria-hidden="true">
              <ion-icon name="chevron-back-outline"></ion-icon>
            </span>
            <span>Questions</span>
          </button>
        </div>

        <div className="rd-brand">
          PREM<em>ium</em> Mind
          <span>{state.title || 'Micro format'}</span>
        </div>

        <div className="rd-header-end">
          <button type="button" className="rd-theme" aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'} aria-pressed={dark} title={dark ? 'Light mode' : 'Dark mode'} onClick={toggleDark}>
            <ion-icon name={dark ? 'sunny-outline' : 'moon-outline'}></ion-icon>
          </button>
          <button type="button" className="rd-theme" aria-label="Download as a Word file" title={canDownload ? 'Download as a Word file' : lockedMessage} onClick={() => download('docx')}>
            <ion-icon name={canDownload ? 'document-text-outline' : 'lock-closed-outline'}></ion-icon>
          </button>
          <button type="button" className={`rd-download${canDownload ? '' : ' is-locked'}`} aria-label={canDownload ? 'Save as PDF' : 'Download locked'} title={canDownload ? 'Save as PDF' : lockedMessage} onClick={() => download('pdf')}>
            <ion-icon name={canDownload ? 'download-outline' : 'lock-closed-outline'}></ion-icon>
            <span>{canDownload ? 'Save PDF' : 'Locked'}</span>
          </button>
        </div>
      </header>

      {!canDownload && <div className="ms-strip">{lockedMessage}</div>}

      <div className="ms-frame">
        <MicroSheetFrame ref={frameRef} mode="viewer" subject={state.title} text={state.text} canDownload={canDownload} lockedMessage={lockedMessage} dark={dark} watermark={wm} blurred={away} />
      </div>

      {toast && (
        <div className="rd-toast" role="status">
          {toast}
        </div>
      )}
    </div>
  );
}
