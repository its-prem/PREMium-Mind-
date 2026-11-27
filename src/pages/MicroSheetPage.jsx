/**
 * The micro format (A4 pages) of an Important Questions product  (/microsheet/:id).
 *
 * What the student sees is like the PDF reader: the same header, and only the finished A4 pages (no editor, no text box).
 * The questions text is loaded into the page but never shown as text; selecting, copying and right-click are blocked,
 * the pages carry the student's own details faintly and blur when the window loses focus.
 * Download (one PDF file) opens at the time the admin set.
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
  const [pdfState, setPdfState] = useState('idle'); // idle | busy | done
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

  // "Save PDF": the tool makes the PDF file and the browser downloads it
  const download = () => {
    if (!canDownload) {
      setToast(lockedMessage);
      return;
    }
    if (pdfState === 'busy') return;
    frameRef.current?.send({ type: 'export', format: 'pdf' });
  };
  const onFrameEvent = (d) => {
    if (d.type !== 'export-state') return;
    if (d.state === 'busy') setPdfState('busy');
    else if (d.state === 'done') {
      setPdfState('done');
      setToast('PDF downloaded');
      setTimeout(() => setPdfState('idle'), 2500);
    } else {
      setPdfState('idle');
      setToast(d.message || 'Could not make the PDF. Please try again.');
    }
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
          {state.micro?.adminPreview && <span style={{ color: '#fbbf24' }}>Admin preview · students must pay</span>}
        </div>

        <div className="rd-header-end">
          <button type="button" className="rd-theme" aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'} aria-pressed={dark} title={dark ? 'Light mode' : 'Dark mode'} onClick={toggleDark}>
            <ion-icon name={dark ? 'sunny-outline' : 'moon-outline'}></ion-icon>
          </button>
          <button type="button" className={`rd-download${canDownload ? '' : ' is-locked'}`} disabled={pdfState === 'busy'} aria-label={canDownload ? 'Download the PDF' : 'Download locked'} title={canDownload ? 'Download the PDF' : lockedMessage} onClick={download}>
            {!canDownload ? (
              <ion-icon name="lock-closed-outline"></ion-icon>
            ) : pdfState === 'busy' ? (
              <span className="ms-dl-spin" aria-hidden="true" />
            ) : pdfState === 'done' ? (
              <svg className="ms-dl-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <path d="M5 12.5l4.5 4.5L19 7.5" />
              </svg>
            ) : (
              // a PDF page with an arrow going down into a tray = "download the PDF"
              <svg className="ms-dl-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <path d="M13.5 2.5H7A1.5 1.5 0 0 0 5.5 4v8" />
                <path d="M13.5 2.5L18.5 7.5V12" />
                <path d="M13.5 2.5V6a1.5 1.5 0 0 0 1.5 1.5h3.5" />
                <g className="ms-dl-arrow">
                  <path d="M12 11.5v6.2M9.2 15l2.8 2.8 2.8-2.8" />
                </g>
                <path d="M6 20.6h12" />
              </svg>
            )}
            <span>{!canDownload ? 'Locked' : pdfState === 'busy' ? 'Preparing…' : pdfState === 'done' ? 'Saved' : 'Download'}</span>
          </button>
        </div>
      </header>

      {!canDownload && <div className="ms-strip">{lockedMessage}</div>}

      <div className="ms-frame">
        <MicroSheetFrame ref={frameRef} mode="viewer" subject={state.title} text={state.text} canDownload={canDownload} lockedMessage={lockedMessage} dark={dark} watermark={wm} blurred={away} onEvent={onFrameEvent} />
      </div>

      {toast && (
        <div className="rd-toast" role="status">
          {toast}
        </div>
      )}
    </div>
  );
}
