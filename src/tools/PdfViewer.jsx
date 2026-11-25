import React, { useEffect, useRef, useState } from 'react';
import { loadPdfJs } from '../lib/pdfViewer';
import { downloadBlob } from '../lib/cdn';
import { baseName, safeName } from './shared';
import { createPortal } from 'react-dom';

const ZOOMS = [1, 1.5, 2, 3]; // 1 = whole page fits the screen

/** One page, drawn sharply (device pixel ratio) only when it is near the screen. */
function Page({ doc, n, size, cssW, root }) {
  const holder = useRef(null);
  const canvasRef = useRef(null);
  const [near, setNear] = useState(false);

  useEffect(() => {
    const el = holder.current;
    if (!el) return undefined;
    const io = new IntersectionObserver(([e]) => setNear(e.isIntersecting), { root, rootMargin: '900px 0px' });
    io.observe(el);
    return () => io.disconnect();
  }, [root]);

  useEffect(() => {
    if (!near || !doc) return undefined;
    let dead = false;
    let task = null;
    (async () => {
      const page = await doc.getPage(n);
      const dpr = Math.min(window.devicePixelRatio || 1, 3);
      const pixelW = Math.min(4000, Math.round(cssW * dpr));
      const view = page.getViewport({ scale: pixelW / size.w });
      const canvas = canvasRef.current;
      if (!canvas || dead) return;
      canvas.width = Math.floor(view.width);
      canvas.height = Math.floor(view.height);
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      task = page.render({ canvasContext: ctx, viewport: view });
      try {
        await task.promise;
      } catch {
        /* cancelled while resizing */
      }
    })();
    return () => {
      dead = true;
      task?.cancel?.();
    };
  }, [near, doc, n, size, cssW]);

  return (
    <div
      ref={holder}
      className="tl-vw-page"
      data-page={n}
      style={{ width: cssW, height: (cssW * size.h) / size.w }}
    >
      <canvas ref={canvasRef} style={{ width: '100%', height: '100%' }} />
      <span className="tl-vw-num">{n}</span>
    </div>
  );
}

/**
 * Full-screen PDF viewer: one page at a time fits the screen in original quality.
 * Props: file (Blob/File), name (with or without .pdf), onRename(newBase) optional, onClose,
 * startPage (which page to show first, default 1).
 */
export default function PdfViewer({ file, name, onRename, onClose, startPage = 1, suffix = '' }) {
  const scroller = useRef(null);
  const [doc, setDoc] = useState(null);
  const [sizes, setSizes] = useState([]); // [{ w, h }] in PDF points
  const [error, setError] = useState('');
  const [zoom, setZoom] = useState(1);
  const [box, setBox] = useState({ w: window.innerWidth, h: window.innerHeight - 120 });
  const [current, setCurrent] = useState(1);
  const [label, setLabel] = useState(baseName(name));
  const [note, setNote] = useState('');

  // The phone's Back button should close the viewer only, not leave the whole Tools page.
  // We add one history entry while the viewer is open; Back removes it and closes the viewer.
  const closeRef = useRef(onClose);
  closeRef.current = onClose;
  useEffect(() => {
    const marker = `tlViewer${Date.now()}`;
    let pushed = false;
    let closedByBack = false;
    const onPop = () => {
      closedByBack = true;
      closeRef.current();
    };
    // Delayed by a tick so React's double-run in development does not push twice
    const timer = setTimeout(() => {
      window.history.pushState({ ...window.history.state, [marker]: true }, '');
      pushed = true;
      window.addEventListener('popstate', onPop);
    }, 0);
    return () => {
      clearTimeout(timer);
      window.removeEventListener('popstate', onPop);
      // Closed with the X button: remove the entry we added so Back works normally afterwards
      if (pushed && !closedByBack && window.history.state && window.history.state[marker]) {
        window.history.back();
      }
    };
  }, []);

  useEffect(() => {
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKey = (e) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prev;
      window.removeEventListener('keydown', onKey);
    };
  }, [onClose]);

  useEffect(() => {
    let dead = false;
    (async () => {
      try {
        const pdfjs = await loadPdfJs();
        const data = new Uint8Array(await file.arrayBuffer());
        const d = await pdfjs.getDocument({ data }).promise;
        const list = [];
        for (let n = 1; n <= d.numPages; n += 1) {
          const p = await d.getPage(n);
          const v = p.getViewport({ scale: 1 });
          list.push({ w: v.width, h: v.height });
        }
        if (dead) return;
        setSizes(list);
        setDoc(d);
      } catch (e) {
        if (!dead) setError(e.message || 'Could not open this PDF.');
      }
    })();
    return () => {
      dead = true;
    };
  }, [file]);

  // Measure the reading area
  useEffect(() => {
    const el = scroller.current;
    if (!el) return undefined;
    const measure = () => setBox({ w: el.clientWidth, h: el.clientHeight });
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  // Which page is on screen
  useEffect(() => {
    const el = scroller.current;
    if (!el || !sizes.length) return undefined;
    const onScroll = () => {
      const mid = el.scrollTop + el.clientHeight / 2;
      const pages = [...el.querySelectorAll('.tl-vw-page')];
      let best = 1;
      pages.forEach((p) => {
        if (p.offsetTop <= mid) best = Number(p.dataset.page);
      });
      setCurrent(best);
    };
    el.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    return () => el.removeEventListener('scroll', onScroll);
  }, [sizes]);

  // Jump to the page that was tapped once the pages are laid out
  useEffect(() => {
    if (!sizes.length || startPage <= 1) return undefined;
    const t = setTimeout(() => {
      scroller.current?.querySelector(`[data-page="${startPage}"]`)?.scrollIntoView({ block: 'center' });
    }, 60);
    return () => clearTimeout(t);
  }, [sizes, startPage]);

  const fitW = (s) => Math.max(120, Math.min(box.w - 24, (box.h - 24) * (s.w / s.h)));

  const goto = (n) => {
    const el = scroller.current?.querySelector(`[data-page="${n}"]`);
    el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
  };

  const outName = safeName(label + suffix, 'pdf', 'document');
  const total = sizes.length;

  return createPortal(
    <div className="tl-vw" role="dialog" aria-modal="true" aria-label="PDF viewer">
      <div className="tl-vw-top">
        <button type="button" className="tl-back" aria-label="Close viewer" onClick={onClose}>
          <ion-icon name="close-outline"></ion-icon>
        </button>
        <div className="tl-vw-name">
          <input
            value={label}
            disabled={!onRename}
            aria-label="File name"
            onChange={(e) => {
              setLabel(e.target.value);
              onRename?.(e.target.value);
            }}
          />
          <span>{suffix}.pdf</span>
        </div>
        <button
          type="button"
          className="tl-vw-btn"
          onClick={() => {
            downloadBlob(file, outName);
            setNote('Downloaded.');
          }}
        >
          <ion-icon name="download-outline"></ion-icon>
          <span>Download</span>
        </button>
      </div>

      <div className="tl-vw-body" ref={scroller}>
        {error && <p className="tl-vw-msg">{error}</p>}
        {!error && !doc && <p className="tl-vw-msg">Opening PDF...</p>}
        {doc &&
          sizes.map((s, i) => (
            <Page key={i} doc={doc} n={i + 1} size={s} cssW={Math.round(fitW(s) * zoom)} root={scroller.current} />
          ))}
      </div>

      <div className="tl-vw-bar">
        <button type="button" className="tl-vw-btn" disabled={current <= 1} onClick={() => goto(current - 1)} aria-label="Previous page">
          <ion-icon name="chevron-up-outline"></ion-icon>
        </button>
        <span className="tl-vw-count">
          {total ? `${current} / ${total}` : '-'}
        </span>
        <button type="button" className="tl-vw-btn" disabled={current >= total} onClick={() => goto(current + 1)} aria-label="Next page">
          <ion-icon name="chevron-down-outline"></ion-icon>
        </button>
        <span className="tl-vw-sep" />
        <button
          type="button"
          className="tl-vw-btn"
          disabled={zoom <= ZOOMS[0]}
          onClick={() => setZoom(ZOOMS[Math.max(0, ZOOMS.indexOf(zoom) - 1)])}
          aria-label="Zoom out"
        >
          <ion-icon name="remove-outline"></ion-icon>
        </button>
        <span className="tl-vw-count">{zoom === 1 ? 'Fit' : `${Math.round(zoom * 100)}%`}</span>
        <button
          type="button"
          className="tl-vw-btn"
          disabled={zoom >= ZOOMS[ZOOMS.length - 1]}
          onClick={() => setZoom(ZOOMS[Math.min(ZOOMS.length - 1, ZOOMS.indexOf(zoom) + 1)])}
          aria-label="Zoom in"
        >
          <ion-icon name="add-outline"></ion-icon>
        </button>
        {note && <span className="tl-vw-note">{note}</span>}
      </div>
    </div>,
    document.body
  );
}
