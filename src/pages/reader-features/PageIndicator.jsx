import React, { useCallback, useEffect, useRef, useState } from 'react';
import { currentPageInfo, getPages, scrollToPage, useDismiss } from './shared';
import './PageIndicator.css';

/**
 * Feature: "3 / 12" pill, a thin progress bar and tap-to-jump.
 * Remove by deleting this file (and PageIndicator.css) and its line in index.jsx.
 */
export default function PageIndicator({ scrollRef, pagesRef, ready }) {
  const [current, setCurrent] = useState(1);
  const [total, setTotal] = useState(0);
  const [progress, setProgress] = useState(0);
  const [open, setOpen] = useState(false);
  const [value, setValue] = useState('');
  const [hint, setHint] = useState('');
  const inputRef = useRef(null);
  const wrapRef = useRef(null);
  const closeJump = useCallback(() => setOpen(false), []);
  useDismiss(open, closeJump, wrapRef);

  useEffect(() => {
    const scrollEl = scrollRef.current;
    const pagesEl = pagesRef.current;
    if (!ready || !scrollEl || !pagesEl) return undefined;

    let raf = null;
    const update = () => {
      raf = null;
      const info = currentPageInfo(scrollEl, pagesEl);
      const declared = parseInt(pagesEl.dataset.total, 10);
      setCurrent(info.index + 1);
      setTotal(declared || info.count);
      const max = scrollEl.scrollHeight - scrollEl.clientHeight;
      setProgress(max > 0 ? Math.min(100, (scrollEl.scrollTop / max) * 100) : 0);
    };
    const schedule = () => {
      if (!raf) raf = requestAnimationFrame(update);
    };

    update();
    scrollEl.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    const observer = new MutationObserver(schedule);
    observer.observe(pagesEl, { childList: true });

    return () => {
      scrollEl.removeEventListener('scroll', schedule);
      window.removeEventListener('resize', schedule);
      observer.disconnect();
      if (raf) cancelAnimationFrame(raf);
    };
  }, [ready, scrollRef, pagesRef]);

  useEffect(() => {
    if (open) inputRef.current?.focus();
  }, [open]);

  if (!ready || !total) return null;

  const jump = (e) => {
    e.preventDefault();
    const n = Math.min(total, Math.max(1, parseInt(value, 10) || 1));
    const loaded = getPages(pagesRef.current).length;
    if (n > loaded) {
      // Pages load one after another; go as far as is ready.
      scrollToPage(scrollRef.current, pagesRef.current, loaded - 1);
      setHint(`Page ${n} is still loading...`);
      setTimeout(() => setHint(''), 2200);
      return;
    }
    scrollToPage(scrollRef.current, pagesRef.current, n - 1);
    setOpen(false);
    setValue('');
  };

  return (
    <>
      <div className="rdx-progress" aria-hidden="true">
        <span style={{ width: `${progress}%` }} />
      </div>

      <div className="rdx-pager-wrap" ref={wrapRef}>
        {open && (
          <form className="rdx-jump" onSubmit={jump}>
            <label htmlFor="rdx-jump-input">Go to page</label>
            <div className="rdx-row">
              <input
                id="rdx-jump-input"
                ref={inputRef}
                type="number"
                inputMode="numeric"
                min={1}
                max={total}
                value={value}
                placeholder={`1 - ${total}`}
                onChange={(e) => setValue(e.target.value)}
              />
              <button type="submit">Go</button>
            </div>
            {hint && <p>{hint}</p>}
          </form>
        )}
        <button
          type="button"
          className="rdx-pager"
          aria-label={`Page ${current} of ${total}. Tap to jump to a page`}
          aria-expanded={open}
          onClick={() => setOpen((o) => !o)}
        >
          <b>{current}</b>
          <span>/ {total}</span>
        </button>
      </div>
    </>
  );
}
