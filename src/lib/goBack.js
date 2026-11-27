/**
 * The back button of the website, in one simple rule:
 *   - there is a page behind this one in the website  ->  go back to it (exactly what the phone's back button does);
 *   - nothing behind (the page was opened directly)   ->  go to the page that is "above" this one (Home by default).
 * Choosing a filter or a tab on a page never adds a back step (it replaces the address), so "back" never has to go
 * through the same page again and again.
 */
export function goBack(navigate, parent = '/') {
  if (window.history.state && window.history.state.idx > 0) navigate(-1);
  else navigate(parent, { replace: true });
}

/** Kept for the pages that already use it: one page back, or home. */
export function goBackOrHome(navigate) {
  goBack(navigate, '/');
}
