import React, { Suspense, lazy, useCallback, useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import './Maintenance.css';

// The maintenance page (cat artwork + styles) is only downloaded when maintenance is really on.
const MaintenancePage = lazy(() => import('./MaintenancePage'));

/**
 * Maintenance mode for the WHOLE site.
 *
 * How to switch it on/off: edit public/maintenance.json on the host and set
 * "enabled" to true / false. No rebuild is needed: every visitor's browser
 * re-reads that file once a minute (and on "Check again").
 *
 * Owner preview: open the site with ?bypass=YOUR_SECRET once. The secret is
 * checked against "bypassHash" (SHA-256) in maintenance.json, so you can still
 * browse while everyone else sees the maintenance page. See README note below.
 *
 * Fail-safe: if the file is missing or unreachable the site stays OPEN.
 * NOTE: this is a front-end gate (a notice page), not server security.
 *
 * To remove: delete this file, MaintenancePage.jsx, Maintenance.css,
 * public/maintenance.json and the <MaintenanceGate> wrapper in main.jsx.
 */

const CONFIG_URL = '/maintenance.json';
const POLL_MS = 60 * 1000;
const FIRST_CHECK_TIMEOUT_MS = 2000;
const BYPASS_KEY = 'pm_maintenance_bypass';
const CACHE_KEY = 'pm_maintenance_cache';

// The last answer we got. With it the page can open at once instead of waiting for the network.
const readCache = () => {
  try {
    const v = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null');
    return v && typeof v === 'object' ? v : null;
  } catch {
    return null;
  }
};
const writeCache = (v) => {
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify(v));
  } catch {
    /* storage blocked */
  }
};

async function loadConfig(signal) {
  const res = await fetch(`${CONFIG_URL}?t=${Date.now()}`, {
    cache: 'no-store',
    signal,
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

async function sha256Hex(text) {
  const bytes = new TextEncoder().encode(text);
  const hash = await crypto.subtle.digest('SHA-256', bytes);
  return Array.from(new Uint8Array(hash))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

const readBypass = () => {
  try {
    return sessionStorage.getItem(BYPASS_KEY) === '1';
  } catch {
    return false;
  }
};

export default function MaintenanceGate({ children }) {
  const location = useLocation();
  const [config, setConfig] = useState(readCache); // null only on the very first visit, until the first check ends
  const [checking, setChecking] = useState(true);
  const [bypass, setBypass] = useState(readBypass);
  const bypassTried = useRef(false);

  const check = useCallback(async (timeoutMs) => {
    setChecking(true);
    const ctrl = new AbortController();
    const timer = timeoutMs ? setTimeout(() => ctrl.abort(), timeoutMs) : null;
    try {
      const data = await loadConfig(ctrl.signal);
      const next = data && typeof data === 'object' ? data : { enabled: false };
      writeCache(next);
      setConfig(next);
      return data;
    } catch {
      // Missing file / offline / slow: never lock people out because of it
      setConfig((prev) => prev || { enabled: false });
      return null;
    } finally {
      if (timer) clearTimeout(timer);
      setChecking(false);
    }
  }, []);

  // First check + one check a minute
  useEffect(() => {
    check(FIRST_CHECK_TIMEOUT_MS);
    const id = setInterval(() => check(0), POLL_MS);
    return () => clearInterval(id);
  }, [check]);

  // ?bypass=SECRET lets the owner browse during maintenance
  useEffect(() => {
    if (!config || bypassTried.current) return;
    const params = new URLSearchParams(window.location.search);
    const secret = params.get('bypass');
    if (!secret) return;
    bypassTried.current = true;

    sha256Hex(secret)
      .then((hash) => {
        if (config.bypassHash && hash === String(config.bypassHash).toLowerCase()) {
          try {
            sessionStorage.setItem(BYPASS_KEY, '1');
          } catch {
            /* storage blocked */
          }
          setBypass(true);
        }
      })
      .catch(() => {})
      .finally(() => {
        // never leave the secret sitting in the address bar
        params.delete('bypass');
        const query = params.toString();
        window.history.replaceState(
          {},
          '',
          `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`
        );
      });
  }, [config]);

  // Waiting for the very first answer: a plain dark screen, no site flash
  if (!config) return <div className="mt-splash" />;

  // The admin panel stays reachable during maintenance (it needs an admin login anyway).
  const isAdminPage = location.pathname.startsWith('/admin');
  if (config.enabled === true && !bypass && !isAdminPage) {
    return (
      <Suspense fallback={<div className="mt-splash" />}>
        <MaintenancePage
          config={config}
          checking={checking}
          onRetry={() => check(0)}
        />
      </Suspense>
    );
  }

  return (
    <>
      {children}
    </>
  );
}
