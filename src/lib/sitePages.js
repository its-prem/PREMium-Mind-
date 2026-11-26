/**
 * The pages of the website that the admin can close one by one (Admin -> Maintenance).
 * To make a new page closable: add a line here. `path` also closes everything under it (/syllabus closes /syllabus/cse).
 * The admin panel and the payment-return page are never closable.
 */
export const SITE_PAGES = [
  { path: '/', label: 'Home / Store', exact: true },
  { path: '/tools', label: 'Free Tools' },
  { path: '/mock-tests', label: 'Mock Tests' },
  { path: '/syllabus', label: 'Syllabus' },
  { path: '/lecture', label: 'My Courses / Lecture' },
  { path: '/watch', label: 'PDF Reader' },
  { path: '/resume-editor', label: 'Resume Editor' },
  { path: '/app-download', label: 'App Download' },
  { path: '/termcondition', label: 'Terms & Conditions' },
  { path: '/login', label: 'Login / Sign up', warn: 'Closing this stops everybody from signing in.' },
];

/** Is this address inside one of the closed pages? */
export function isPageClosed(pathname, closedPaths) {
  if (!Array.isArray(closedPaths) || closedPaths.length === 0) return false;
  const p = String(pathname || '/').replace(/\/+$/, '') || '/';
  if (p.startsWith('/admin') || p.startsWith('/payment-return')) return false;
  return closedPaths.some((c) => {
    const page = SITE_PAGES.find((x) => x.path === c);
    if (!page) return false;
    if (page.exact) return p === page.path;
    return p === page.path || p.startsWith(`${page.path}/`);
  });
}
