import React, { useCallback, useEffect, useRef, useState } from 'react';
import './shared.css';

/** localStorage helpers that never throw (storage can be blocked). */
export const readJson = (key, fallback) => {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch {
    return fallback;
  }
};

export const writeJson = (key, value) => {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    /* storage blocked */
  }
};

/** Local calendar day as YYYY-MM-DD. */
export const dayKey = (date = new Date()) => date.toLocaleDateString('en-CA');

/** All page wrappers currently rendered by the reader. */
export const getPages = (pagesEl) =>
  pagesEl ? Array.from(pagesEl.querySelectorAll(':scope > .rd-page')) : [];

/** Which page is in view, and how far down it (0-1). */
export function currentPageInfo(scrollEl, pagesEl) {
  const pages = getPages(pagesEl);
  if (!scrollEl || !pages.length) return { index: 0, ratio: 0, count: 0 };

  const box = scrollEl.getBoundingClientRect();
  const probe = box.top + box.height * 0.35;
  let best = 0;
  let bestDist = Infinity;

  for (let i = 0; i < pages.length; i += 1) {
    const r = pages[i].getBoundingClientRect();
    if (r.top <= probe && probe < r.bottom) {
      return {
        index: i,
        ratio: Math.min(1, Math.max(0, (probe - r.top) / (r.height || 1))),
        count: pages.length,
      };
    }
    const dist = Math.min(Math.abs(r.top - probe), Math.abs(r.bottom - probe));
    if (dist < bestDist) {
      bestDist = dist;
      best = i;
    }
  }
  return { index: best, ratio: 0, count: pages.length };
}

/** Scroll so page `index` (plus a 0-1 offset inside it) is near the top. */
export function scrollToPage(scrollEl, pagesEl, index, ratio = 0, smooth = true) {
  const page = getPages(pagesEl)[index];
  if (!scrollEl || !page) return false;
  const box = scrollEl.getBoundingClientRect();
  const r = page.getBoundingClientRect();
  const top = r.top - box.top + scrollEl.scrollTop + ratio * r.height - 8;
  scrollEl.scrollTo({ top: Math.max(0, top), behavior: smooth ? 'smooth' : 'auto' });
  return true;
}

/** Close a popover on outside press or Escape. */
export function useDismiss(open, onClose, ref) {
  useEffect(() => {
    if (!open) return undefined;
    const onDown = (e) => {
      if (ref.current && !ref.current.contains(e.target)) onClose();
    };
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('pointerdown', onDown, true);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('pointerdown', onDown, true);
      document.removeEventListener('keydown', onKey);
    };
  }, [open, onClose, ref]);
}

/** Small top toast with an optional action button. */
export function useFeatureToast() {
  const [toast, setToast] = useState(null);
  const timer = useRef(null);

  const show = useCallback((text, action) => {
    clearTimeout(timer.current);
    setToast({ text, action });
    timer.current = setTimeout(() => setToast(null), action ? 7000 : 3500);
  }, []);

  useEffect(() => () => clearTimeout(timer.current), []);

  const node = toast ? (
    <div className="rdx-toast" role="status">
      <span>{toast.text}</span>
      {toast.action && (
        <button
          type="button"
          onClick={() => {
            toast.action.run();
            setToast(null);
          }}
        >
          {toast.action.label}
        </button>
      )}
    </div>
  ) : null;

  return [node, show];
}
