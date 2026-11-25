import { useEffect } from 'react';
import { playPageFlip } from '../../lib/uiSounds';
import { currentPageInfo } from './shared';

/**
 * Feature: a soft paper "swish" whenever you scroll onto a new page.
 * Remove by deleting this file and its line in index.jsx.
 */
export default function PageFlipSound({ scrollRef, pagesRef, ready }) {
  useEffect(() => {
    const scrollEl = scrollRef.current;
    const pagesEl = pagesRef.current;
    if (!ready || !scrollEl || !pagesEl) return undefined;

    const startedAt = performance.now();
    let last = currentPageInfo(scrollEl, pagesEl).index;
    let raf = null;

    const check = () => {
      raf = null;
      const { index } = currentPageInfo(scrollEl, pagesEl);
      if (index === last) return;
      last = index;
      // Ignore the jump from restoring the last-read position right after load.
      if (performance.now() - startedAt > 2500) playPageFlip();
    };
    const onScroll = () => {
      if (!raf) raf = requestAnimationFrame(check);
    };

    scrollEl.addEventListener('scroll', onScroll, { passive: true });
    return () => {
      scrollEl.removeEventListener('scroll', onScroll);
      if (raf) cancelAnimationFrame(raf);
    };
  }, [ready, scrollRef, pagesRef]);

  return null;
}
