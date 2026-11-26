/**
 * Subjects the admin uploaded (Admin -> Syllabus) are loaded from the server and added to the built-in
 * ones in src/data/syllabus.js. The built-in SUBJECTS stay as they are; uploaded subjects are merged in.
 */
import { useEffect, useState } from 'react';
import { SITE_API } from './api';
import { SUBJECTS } from '../data/syllabus';

const KEY = 'pm_syllabus_cache';
let state = 'idle'; // idle | loading | done
let listeners = [];
let added = []; // ids merged from the server

const notify = () => listeners.forEach((fn) => fn());

function merge(list) {
  added.forEach((id) => delete SUBJECTS[id]);
  added = [];
  (Array.isArray(list) ? list : []).forEach((s) => {
    if (!s || !s.id || !Array.isArray(s.units) || SUBJECTS[s.id]) return;
    SUBJECTS[s.id] = {
      id: s.id,
      title: s.title,
      semester: `${['1st', '2nd', '3rd', '4th', '5th', '6th'][(s.sem || 1) - 1]} Semester`,
      sem: s.sem,
      branches: s.branches === 'all' || !Array.isArray(s.branches) ? 'all' : s.branches,
      code: s.code || '',
      ltp: s.ltp || '-',
      icon: s.icon || 'book-outline',
      units: s.units,
    };
    added.push(s.id);
  });
}

try {
  merge(JSON.parse(localStorage.getItem(KEY) || '[]')); // show the last known list at once
} catch {
  /* no cache */
}

export function loadUploadedSyllabus(force = false) {
  if (state === 'loading' || (state === 'done' && !force)) return;
  state = 'loading';
  fetch(`${SITE_API}/syllabus.php`, { mode: 'cors', credentials: 'omit', cache: 'no-store' })
    .then((r) => r.json())
    .then((d) => {
      if (d && d.status === 'success') {
        merge(d.subjects);
        try {
          localStorage.setItem(KEY, JSON.stringify(d.subjects || []));
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

/** Re-renders the page when the uploaded subjects arrive. Returns true while they are still loading. */
export function useUploadedSyllabus() {
  const [, tick] = useState(0);
  useEffect(() => {
    const fn = () => tick((n) => n + 1);
    listeners.push(fn);
    loadUploadedSyllabus();
    return () => {
      listeners = listeners.filter((x) => x !== fn);
    };
  }, []);
  return state !== 'done';
}
