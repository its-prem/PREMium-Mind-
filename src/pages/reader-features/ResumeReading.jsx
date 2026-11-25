import { useEffect, useRef } from 'react';
import {
  currentPageInfo,
  readJson,
  scrollToPage,
  useFeatureToast,
  writeJson,
} from './shared';

/**
 * Feature: remember the last page read and jump back to it next time.
 * Remove by deleting this file and its line in index.jsx.
 */
export default function ResumeReading({ scrollRef, pagesRef, docKey, ready }) {
  const storageKey = `pm_resume:${docKey}`;
  const [toast, showToast] = useFeatureToast();
  const restored = useRef(false);

  // Jump back to the saved spot once enough pages have loaded.
  useEffect(() => {
    if (!ready || restored.current) return undefined;

    const saved = readJson(storageKey, null);
    if (!saved || (!saved.page && !(saved.ratio > 0.05))) {
      restored.current = true;
      return undefined;
    }

    let tries = 0;
    const attempt = () => {
      const done = scrollToPage(
        scrollRef.current,
        pagesRef.current,
        saved.page,
        saved.ratio,
        false
      );
      if (done) {
        restored.current = true;
        showToast(`Page ${saved.page + 1} se aage padh rahe ho`, {
          label: 'Shuru se',
          run: () => scrollRef.current?.scrollTo({ top: 0, behavior: 'smooth' }),
        });
      }
      return done;
    };

    if (attempt()) return undefined;
    // Pages load one by one; wait for the saved page to appear.
    const timer = setInterval(() => {
      tries += 1;
      if (attempt() || tries > 60) {
        restored.current = true;
        clearInterval(timer);
      }
    }, 250);
    return () => clearInterval(timer);
  }, [ready, storageKey, scrollRef, pagesRef, showToast]);

  // Save the position while reading (and when leaving the page).
  useEffect(() => {
    const scrollEl = scrollRef.current;
    if (!ready || !scrollEl) return undefined;

    // Keep the latest position in memory: when the page unmounts its DOM is
    // already gone, so it can not be measured in the cleanup.
    let latest = null;
    const write = () => {
      if (latest && restored.current) {
        writeJson(storageKey, { ...latest, ts: Date.now() });
      }
    };

    let timer = null;
    const onScroll = () => {
      if (!restored.current) return;
      const { index, ratio } = currentPageInfo(scrollEl, pagesRef.current);
      latest = { page: index, ratio };
      clearTimeout(timer);
      timer = setTimeout(write, 500);
    };

    scrollEl.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('pagehide', write);
    return () => {
      scrollEl.removeEventListener('scroll', onScroll);
      window.removeEventListener('pagehide', write);
      clearTimeout(timer);
      write();
    };
  }, [ready, storageKey, scrollRef, pagesRef]);

  return toast;
}
