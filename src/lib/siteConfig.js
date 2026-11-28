/**
 * Home page photo + ant / butterfly switches, set by the admin (Admin -> Site look).
 * They arrive with the maintenance check (MaintenanceGate) and are kept in its cache, so they are there at once on
 * the next visit. Until the server has answered once, the built-in photo and both animals are used.
 */
import { useEffect, useState } from 'react';

const DEFAULTS = { heroImage: '', ant: true, butterfly: true };

export function readSiteConfig() {
  try {
    const c = JSON.parse(localStorage.getItem('pm_maintenance_cache') || 'null');
    const s = c && c.site;
    if (!s || typeof s !== 'object') return DEFAULTS;
    const img = typeof s.heroImage === 'string' && /^https:\/\//.test(s.heroImage) ? s.heroImage : '';
    return { heroImage: img, ant: s.ant !== false, butterfly: s.butterfly !== false };
  } catch {
    return DEFAULTS;
  }
}

export function useSiteConfig() {
  const [site, setSite] = useState(readSiteConfig);
  useEffect(() => {
    const on = () => setSite(readSiteConfig());
    window.addEventListener('pm-site-config', on);
    return () => window.removeEventListener('pm-site-config', on);
  }, []);
  return site;
}
