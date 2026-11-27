/**
 * Night mode of the website. The choice is kept on the device; it is a  data-theme="dark"  attribute on <html>,
 * which the generated src/styles/site-dark.css reacts to (see scripts/make-site-dark.mjs).
 *
 *   initSiteTheme()          once, before the page is drawn (main.jsx), so there is no white flash
 *   useSiteTheme()           [dark, toggle]  for a button
 */
import { useEffect, useState } from 'react';

const KEY = 'pm_site_theme';
const EVENT = 'pm-theme-changed';

export const isDark = () => {
  try {
    return localStorage.getItem(KEY) === 'dark';
  } catch {
    return false;
  }
};

function apply(dark) {
  const el = document.documentElement;
  if (dark) el.setAttribute('data-theme', 'dark');
  else el.removeAttribute('data-theme');
  // the browser's own bars (address bar on a phone) follow it
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.setAttribute('content', dark ? '#0e0a0b' : '#ffffff');
}

export function setSiteTheme(dark) {
  try {
    localStorage.setItem(KEY, dark ? 'dark' : 'light');
  } catch {
    /* storage blocked */
  }
  apply(dark);
  window.dispatchEvent(new Event(EVENT));
}

export function initSiteTheme() {
  apply(isDark());
}

export function useSiteTheme() {
  const [dark, setDark] = useState(isDark);
  useEffect(() => {
    const on = () => setDark(isDark());
    window.addEventListener(EVENT, on);
    return () => window.removeEventListener(EVENT, on);
  }, []);
  return [dark, () => setSiteTheme(!isDark())];
}
