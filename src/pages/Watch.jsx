import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { getIdToken } from '../lib/firebase';
import {
  fetchPdfBytes,
  fetchPhone,
  fetchWatermarkedPage,
  normalizePdfPath,
  requestToken,
} from '../lib/pdfApi';
import {
  createZoomController,
  renderPageImages,
  renderPdfDocument,
} from '../lib/pdfViewer';
import { getStoredEmail } from '../lib/session';
import ReaderFeatures from './reader-features';
import './Watch.css';

const THEME_KEY = 'pm_reader_dark';

const DEFAULT_DL_MSG =
  'Download abhi available nahi hai. Exam se 1 din pehle unlock hoga.';

const isNativeApp = () =>
  !!window.AndroidPdfSaver ||
  !!window.Capacitor?.isNativePlatform?.() ||
  (/Android/.test(navigator.userAgent || '') &&
    (/; wv\)/.test(navigator.userAgent) || /Capacitor/i.test(navigator.userAgent)));

function bytesToBase64(bytes) {
  const CHUNK = 0x8000;
  let binary = '';
  for (let i = 0; i < bytes.length; i += CHUNK) {
    binary += String.fromCharCode.apply(
      null,
      bytes.subarray(i, Math.min(i + CHUNK, bytes.length))
    );
  }
  return btoa(binary);
}

/** Save through the Android app's native bridge (WebView can't download). */
async function saveViaAndroid(buf, fileName) {
  const saver = window.AndroidPdfSaver;
  if (!saver) throw new Error('App update required to save PDFs.');
  saver.showToast?.('Saving to Downloads...');
  saver.begin(fileName);
  const bytes = new Uint8Array(buf);
  const chunk = 128 * 1024;
  for (let i = 0; i < bytes.length; i += chunk) {
    saver.appendChunk(bytesToBase64(bytes.subarray(i, Math.min(i + chunk, bytes.length))));
    if (i > 0 && i % (chunk * 4) === 0) {
      // eslint-disable-next-line no-await-in-loop
      await new Promise((r) => setTimeout(r, 0));
    }
  }
  saver.finish();
}

function backTarget(params) {
  let target = params.get('from');
  if (!target) {
    try {
      target = sessionStorage.getItem('pmWatchReturn');
    } catch {
      target = null;
    }
  }
  if (target && /lecture/i.test(target) && !target.startsWith('/')) return '/lecture';
  return target && target.startsWith('/') ? target : '/lecture';
}

export default function Watch() {
  const navigate = useNavigate();
  const [params] = useSearchParams();

  const email = useMemo(() => getStoredEmail(), []);
  const file = useMemo(
    () => normalizePdfPath(params.get('file') || params.get('pdf')),
    [params]
  );
  const title = params.get('title') || '';
  // Purchased item shipped with the site: read straight from /path.pdf (same origin only)
  const rawSrc = params.get('src') || '';
  const localSrc = /^\/(?!\/)[^?#]*\.pdf$/i.test(rawSrc) ? rawSrc : '';
  const dlMsg = params.get('dlmsg') || params.get('download_msg') || DEFAULT_DL_MSG;

  // The URL flag is only an initial guess; the server answer replaces it.
  const [allowDownload, setAllowDownload] = useState(
    ['true', '1', 'yes'].includes(params.get('download')) ||
      params.get('allow_download') === '1'
  );
  const [status, setStatus] = useState('loading'); // loading | ready | error
  const [loadingText, setLoadingText] = useState('Loading notes...');
  const [errorText, setErrorText] = useState('');
  const [phone, setPhone] = useState('');
  const [dlState, setDlState] = useState('idle'); // idle | busy | done | failed
  const [toast, setToast] = useState('');
  const [secure, setSecure] = useState(false);
  const [dark, setDark] = useState(() => {
    try {
      return localStorage.getItem(THEME_KEY) === '1';
    } catch {
      return false;
    }
  });

  const scrollRef = useRef(null);
  const pagesRef = useRef(null);
  const dlBusy = useRef(false);

  const showToast = useCallback((msg) => {
    setToast(msg);
    setTimeout(() => setToast((t) => (t === msg ? '' : t)), 3500);
  }, []);

  // ---------- Load + render ----------
  useEffect(() => {
    if (!email && !localSrc) {
      navigate('/login', { replace: true });
      return undefined;
    }
    if (!file && !localSrc) {
      setStatus('error');
      setErrorText('No PDF file provided.');
      return undefined;
    }

    const pages = pagesRef.current;
    const scrollArea = scrollRef.current;
    const ctrl = new AbortController();
    let cancelled = false;
    let viewer = null;
    let zoom = null;
    pages.replaceChildren();

    const handlers = {
      isCancelled: () => cancelled,
      onProgress: (n, total) => setLoadingText(`Loading ${n}/${total}...`),
      onFirstPage: () => setStatus('ready'),
    };

    (async () => {
      try {
        if (localSrc) {
          zoom = createZoomController({
            scrollArea,
            zoomTarget: pages,
            onZoomEnd: () => viewer?.reRenderZoom?.(),
          });
          const res = await fetch(localSrc, { signal: ctrl.signal });
          if (!res.ok) throw new Error('Could not open this file.');
          const data = await res.arrayBuffer();
          if (cancelled) return;
          viewer = await renderPdfDocument({
            data,
            container: pages,
            scrollArea,
            zoom,
            ...handlers,
          });
          return;
        }

        const session = await requestToken({
          file,
          email,
          purpose: 'page_view',
          getIdToken,
          signal: ctrl.signal,
        });
        if (cancelled) return;
        if (typeof session.allow_download === 'boolean') {
          setAllowDownload(session.allow_download);
        }

        zoom = createZoomController({
          scrollArea,
          zoomTarget: pages,
          onZoomEnd: () => viewer?.reRenderZoom?.(),
        });

        if (!session.allow_download) {
          // Locked course: raw PDF never reaches the browser.
          const totalPages = Math.max(1, parseInt(session.pages, 10) || 0);
          if (!session.pages) {
            throw new Error(
              'Could not prepare secure viewer for this document. Please try again.'
            );
          }
          await renderPageImages({
            totalPages,
            container: pages,
            fetchPage: (page) =>
              fetchWatermarkedPage({
                file,
                email,
                token: session.token,
                page,
                signal: ctrl.signal,
              }),
            ...handlers,
          });
          return;
        }

        const viewToken = await requestToken({
          file,
          email,
          purpose: 'view',
          getIdToken,
          signal: ctrl.signal,
        });
        const data = await fetchPdfBytes({
          file,
          email,
          token: viewToken.token,
          signal: ctrl.signal,
        });
        if (cancelled) return;
        viewer = await renderPdfDocument({
          data,
          container: pages,
          scrollArea,
          zoom,
          ...handlers,
        });
      } catch (err) {
        if (cancelled || err?.name === 'AbortError') return;
        console.error(err);
        setStatus('error');
        setErrorText(
          `Access Denied. ${String(err?.message || 'Secure Connection Failed')}`
        );
      }
    })();

    return () => {
      cancelled = true;
      ctrl.abort();
      zoom?.destroy();
      viewer?.destroy?.();
      pages.replaceChildren();
    };
  }, [email, file, localSrc, navigate]);

  // Phone number for the on-screen watermark.
  useEffect(() => {
    if (!email) return;
    fetchPhone(email).then(setPhone);
  }, [email]);

  // ---------- Content protection (deterrents, not hard security) ----------
  useEffect(() => {
    const isTouch = 'ontouchstart' in window;
    const blurOn = () => {
      if (!isTouch) setSecure(true);
    };
    const blurOff = () => setSecure(false);
    const onVisibility = () => setSecure(document.hidden);
    const onKey = (e) => {
      const mod = e.ctrlKey || e.metaKey;
      if (mod && ['p', 's', 'c', 'u'].includes(String(e.key).toLowerCase())) {
        e.preventDefault();
      }
      if (e.key === 'PrintScreen') {
        navigator.clipboard?.writeText('Screenshots not allowed').catch(() => {});
        setSecure(true);
        setTimeout(() => setSecure(false), 2000);
      }
    };
    const onInteract = () => {
      if (!document.hidden) setSecure(false);
    };

    window.addEventListener('blur', blurOn);
    window.addEventListener('focus', blurOff);
    document.addEventListener('visibilitychange', onVisibility);
    document.addEventListener('keydown', onKey);
    ['touchstart', 'pointerdown', 'click'].forEach((evt) =>
      document.addEventListener(evt, onInteract, { passive: true, capture: true })
    );
    return () => {
      window.removeEventListener('blur', blurOn);
      window.removeEventListener('focus', blurOff);
      document.removeEventListener('visibilitychange', onVisibility);
      document.removeEventListener('keydown', onKey);
      ['touchstart', 'pointerdown', 'click'].forEach((evt) =>
        document.removeEventListener(evt, onInteract, { capture: true })
      );
    };
  }, []);

  const toggleDark = () => {
    setDark((prev) => {
      const next = !prev;
      try {
        localStorage.setItem(THEME_KEY, next ? '1' : '0');
      } catch {
        /* storage blocked */
      }
      return next;
    });
  };

  // ---------- Download ----------
  const handleDownload = async () => {
    if (dlBusy.current) return;
    if (!file) {
      showToast('No PDF file found.');
      return;
    }
    if (!allowDownload) {
      showToast(dlMsg);
      return;
    }

    dlBusy.current = true;
    setDlState('busy');
    try {
      const tokenData = await requestToken({
        file,
        email,
        purpose: 'download',
        getIdToken,
      });
      if (typeof tokenData.allow_download === 'boolean' && !tokenData.allow_download) {
        setAllowDownload(false);
        throw new Error(dlMsg);
      }
      const buf = await fetchPdfBytes({ file, email, token: tokenData.token });

      let baseName = 'Premium_Notes';
      if (title) {
        baseName = title.replace(/[^a-zA-Z0-9 ]/g, '_');
      } else {
        const name = file.substring(file.lastIndexOf('/') + 1).replace(/\.pdf$/i, '');
        if (name) baseName = name;
      }
      const fileName = `${baseName}_PREMium_Mind.pdf`;

      if (isNativeApp()) {
        await saveViaAndroid(buf, fileName);
      } else {
        const url = URL.createObjectURL(new Blob([buf], { type: 'application/pdf' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
      }
      setDlState('done');
      showToast(`Saved: ${fileName}`);
    } catch (err) {
      console.error(err);
      setDlState('failed');
      showToast(`Download failed: ${err?.message || 'secure layer'}`);
    }
    setTimeout(() => {
      setDlState('idle');
      dlBusy.current = false;
    }, 2500);
  };

  const wmText = email
    ? `${email}\n+91 ${phone || '...'}`
    : 'PREMium Mind\nPurchased copy';
  const dlLabel =
    dlState === 'busy'
      ? 'Downloading…'
      : dlState === 'done'
        ? 'Done'
        : dlState === 'failed'
          ? 'Failed'
          : allowDownload
            ? 'Save PDF'
            : 'Locked';

  return (
    <div
      className={`rd-root ${secure ? 'is-secure' : ''} ${dark ? 'is-dark' : ''}`}
      onContextMenu={(e) => e.preventDefault()}
    >
      <header className="rd-header">
        <div className="rd-header-start">
          <button
            type="button"
            className="rd-back"
            aria-label="Back to My Courses"
            onClick={() => navigate(backTarget(params))}
          >
            <span className="rd-back-icon" aria-hidden="true">
              <ion-icon name="chevron-back-outline"></ion-icon>
            </span>
            <span>My Courses</span>
          </button>
        </div>

        <div className="rd-brand">
          PREM<em>ium</em> Mind
          <span>{title || 'Secure Reader'}</span>
        </div>

        <div className="rd-header-end">
          <button
            type="button"
            className="rd-theme"
            aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'}
            aria-pressed={dark}
            title={dark ? 'Light mode' : 'Dark mode'}
            onClick={toggleDark}
          >
            <ion-icon name={dark ? 'sunny-outline' : 'moon-outline'}></ion-icon>
          </button>
          <button
            type="button"
            className={`rd-download ${allowDownload ? '' : 'is-locked'}`}
            aria-label={allowDownload ? 'Download PDF' : 'Download locked'}
            disabled={dlState === 'busy'}
            onClick={handleDownload}
          >
            <ion-icon
              name={allowDownload ? 'download-outline' : 'lock-closed-outline'}
            ></ion-icon>
            <span>{dlLabel}</span>
          </button>
        </div>
      </header>

      {/* Watermark with the viewer's own details */}
      <div className="rd-watermark" aria-hidden="true">
        {Array.from({ length: 12 }, (_, i) => (
          <div key={i} className="rd-wm">
            {wmText}
          </div>
        ))}
      </div>

      {status !== 'ready' && (
        <div className={`rd-loader ${status === 'error' ? 'is-error' : ''}`}>
          {status === 'loading' && <div className="rd-spinner" />}
          <p>{status === 'error' ? errorText : loadingText}</p>
          {status === 'error' && (
            <button type="button" className="rd-retry" onClick={() => navigate(backTarget(params))}>
              Back to My Courses
            </button>
          )}
        </div>
      )}

      <div className="rd-scroll" ref={scrollRef}>
        <div className="rd-pages" ref={pagesRef} />
      </div>

      {/* Optional extras (resume, page jump, modes, highlighter, timer).
          Remove this line to run the plain reader. */}
      <ReaderFeatures
        scrollRef={scrollRef}
        pagesRef={pagesRef}
        docKey={`${email || 'guest'}|${file || localSrc}`}
        ready={status === 'ready'}
      />

      {toast && (
        <div className="rd-toast" role="status">
          {toast}
        </div>
      )}
    </div>
  );
}
