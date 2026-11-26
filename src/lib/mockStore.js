/**
 * MCQ tests the admin uploaded (Admin -> MCQ tests) are loaded from the server and added to the built-in
 * list in src/data/mockTests.js (the array is extended; the built-in tests stay).
 */
import { useEffect, useState } from 'react';
import { SITE_API } from './api';
import { MOCK_TESTS } from '../data/mockTests';

const KEY = 'pm_mocktests_cache';
const builtIn = MOCK_TESTS.length;
let state = 'idle'; // idle | loading | done
let listeners = [];

const notify = () => listeners.forEach((fn) => fn());

function merge(list) {
  MOCK_TESTS.length = builtIn; // drop what was merged before
  (Array.isArray(list) ? list : []).forEach((t) => {
    if (!t || !t.id || !Array.isArray(t.questions) || !t.questions.length) return;
    if (MOCK_TESTS.some((x) => x.id === t.id)) return;
    MOCK_TESTS.push({ id: t.id, title: t.title, icon: t.icon || 'document-text-outline', tint: t.tint || '#38bdf8', minutes: t.minutes || 10, questions: t.questions });
  });
}

try {
  merge(JSON.parse(localStorage.getItem(KEY) || '[]')); // the last known list shows at once
} catch {
  /* no cache */
}

export function loadUploadedMocks(force = false) {
  if (state === 'loading' || (state === 'done' && !force)) return;
  state = 'loading';
  fetch(`${SITE_API}/mocktests.php`, { mode: 'cors', credentials: 'omit', cache: 'no-store' })
    .then((r) => r.json())
    .then((d) => {
      if (d && d.status === 'success') {
        merge(d.tests);
        try {
          localStorage.setItem(KEY, JSON.stringify(d.tests || []));
        } catch {
          /* storage full */
        }
      }
    })
    .catch(() => {})
    .finally(() => {
      state = 'done';
      notify();
    });
}

/** Re-renders the page when the uploaded tests arrive. */
export function useUploadedMocks() {
  const [, tick] = useState(0);
  useEffect(() => {
    const fn = () => tick((n) => n + 1);
    listeners.push(fn);
    loadUploadedMocks();
    return () => {
      listeners = listeners.filter((x) => x !== fn);
    };
  }, []);
}
