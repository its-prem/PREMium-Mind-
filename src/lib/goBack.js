import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

/**
 * Back button rules of the whole website:
 *   1. a back button goes ONE page back (the page the user came from). Only when there is nothing to go back to,
 *      it opens the fallback page (home by default).
 *   2. Home is the start: when the user reaches Home, the pages visited before are forgotten (useHomeRoot below),
 *      so the phone's back button on Home closes the app instead of walking back through old pages.
 */
export function goBack(navigate, fallback = '/') {
  if (window.history.state && window.history.state.idx > 0) navigate(-1);
  else navigate(fallback, { replace: true });
}

/** Kept for the pages that already use it: one page back, or home. */
export function goBackOrHome(navigate) {
  goBack(navigate, '/');
}

let resetting = false;

/**
 * Call this once on the Home page. If the user arrived at Home through other pages, the history is rewound to
 * its first entry, which is then replaced by Home. Nothing is left behind Home, so "back" on Home leaves the app.
 */
export function useHomeRoot() {
  const navigate = useNavigate();
  useEffect(() => {
    const idx = window.history.state && window.history.state.idx;
    if (!idx || idx < 1 || resetting) return undefined;
    resetting = true;
    const onPop = () => {
      window.removeEventListener('popstate', onPop);
      resetting = false;
      navigate('/', { replace: true });
    };
    window.addEventListener('popstate', onPop);
    window.history.go(-idx);
    return undefined;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
}
