import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  dayKey,
  readJson,
  useDismiss,
  useFeatureToast,
  writeJson,
} from './shared';
import './StudyTimer.css';

const LOG_KEY = 'pm_study_log';
const COUNTS_AS_STUDIED = 60; // seconds needed for a day to count
const FOCUS_SECONDS = 25 * 60;
const BREAK_SECONDS = 5 * 60;

const pad = (n) => String(n).padStart(2, '0');
const clock = (sec) => `${pad(Math.floor(sec / 60))}:${pad(sec % 60)}`;

function previousDay(key) {
  const d = new Date(`${key}T12:00:00`);
  d.setDate(d.getDate() - 1);
  return dayKey(d);
}

/** Consecutive studied days ending today (or yesterday if today is not done yet). */
function computeStreak(days) {
  let key = dayKey();
  if ((days[key] || 0) < COUNTS_AS_STUDIED) key = previousDay(key);
  let streak = 0;
  while ((days[key] || 0) >= COUNTS_AS_STUDIED) {
    streak += 1;
    key = previousDay(key);
  }
  return streak;
}

/**
 * Feature: study timer (only counts while the page is visible), daily streak
 * and an optional 25/5 Pomodoro. Everything is saved on this device.
 * Remove by deleting this file (and StudyTimer.css) and its line in index.jsx.
 */
export default function StudyTimer({ ready }) {
  const [toast, showToast] = useFeatureToast();
  const [tick, setTick] = useState(0); // forces a re-render each second
  const [open, setOpen] = useState(false);
  const [paused, setPaused] = useState(false);
  const [pomodoro, setPomodoro] = useState(false);
  const wrapRef = useRef(null);
  const closePop = useCallback(() => setOpen(false), []);
  useDismiss(open, closePop, wrapRef);

  const log = useRef(readJson(LOG_KEY, { days: {}, best: 0 }));
  const session = useRef(0);
  const phase = useRef('focus'); // focus | break
  const phaseSec = useRef(0);
  const sinceSave = useRef(0);
  const pausedRef = useRef(false);
  const pomodoroRef = useRef(false);
  pausedRef.current = paused;
  pomodoroRef.current = pomodoro;

  const save = useCallback(() => writeJson(LOG_KEY, log.current), []);

  useEffect(() => {
    if (!ready) return undefined;

    const id = setInterval(() => {
      if (pausedRef.current || document.hidden) return;

      if (phase.current === 'break') {
        phaseSec.current += 1;
        if (phaseSec.current >= BREAK_SECONDS) {
          phase.current = 'focus';
          phaseSec.current = 0;
          showToast('Break over. Back to studying!');
        }
        setTick((t) => t + 1);
        return;
      }

      const today = dayKey();
      const before = log.current.days[today] || 0;
      const after = before + 1;
      log.current.days[today] = after;
      session.current += 1;
      sinceSave.current += 1;

      if (before < COUNTS_AS_STUDIED && after >= COUNTS_AS_STUDIED) {
        const streak = computeStreak(log.current.days);
        log.current.best = Math.max(log.current.best || 0, streak);
      }

      if (pomodoroRef.current) {
        phaseSec.current += 1;
        if (phaseSec.current >= FOCUS_SECONDS) {
          phase.current = 'break';
          phaseSec.current = 0;
          showToast('25 min ho gaye. 5 min ka break lo.');
        }
      }

      if (sinceSave.current >= 10) {
        sinceSave.current = 0;
        save();
      }
      setTick((t) => t + 1);
    }, 1000);

    const flush = () => save();
    document.addEventListener('visibilitychange', flush);
    window.addEventListener('pagehide', flush);
    return () => {
      clearInterval(id);
      document.removeEventListener('visibilitychange', flush);
      window.removeEventListener('pagehide', flush);
      save();
    };
  }, [ready, save, showToast]);

  const togglePomodoro = () => {
    setPomodoro((p) => !p);
    phase.current = 'focus';
    phaseSec.current = 0;
  };

  const stats = useMemo(() => {
    const days = log.current.days;
    const streak = computeStreak(days);
    return {
      today: days[dayKey()] || 0,
      streak,
      best: Math.max(log.current.best || 0, streak),
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tick]);

  if (!ready) return toast;

  const onBreak = phase.current === 'break';
  const shown = onBreak ? BREAK_SECONDS - phaseSec.current : session.current;

  return (
    <>
      {toast}
      <div className="rdx-timer-wrap" ref={wrapRef}>
        {open && (
          <div className="rdx-pop rdx-timer-pop">
            <h4>Study tracker</h4>
            <dl>
              <div>
                <dt>Aaj</dt>
                <dd>{Math.floor(stats.today / 60)} min</dd>
              </div>
              <div>
                <dt>Is session</dt>
                <dd>{clock(session.current)}</dd>
              </div>
              <div>
                <dt>Streak</dt>
                <dd>{stats.streak} din</dd>
              </div>
              <div>
                <dt>Best</dt>
                <dd>{stats.best} din</dd>
              </div>
            </dl>
            <div className="rdx-stack">
              <button
                type="button"
                className={`rdx-chip ${pomodoro ? 'is-on' : ''}`}
                onClick={togglePomodoro}
              >
                <ion-icon name="alarm-outline"></ion-icon> Pomodoro 25 / 5
              </button>
              <button type="button" className="rdx-chip" onClick={() => setPaused((p) => !p)}>
                <ion-icon name={paused ? 'play-outline' : 'pause-outline'}></ion-icon>
                {paused ? 'Resume timer' : 'Pause timer'}
              </button>
            </div>
            <p className="rdx-note">Counted only while this page is open and visible.</p>
          </div>
        )}
        <button
          type="button"
          className={`rdx-timer ${onBreak ? 'is-break' : ''} ${paused ? 'is-paused' : ''}`}
          aria-expanded={open}
          aria-label="Study timer"
          onClick={() => setOpen((o) => !o)}
        >
          <ion-icon name={onBreak ? 'cafe-outline' : 'timer-outline'}></ion-icon>
          <span>{onBreak ? `Break ${clock(shown)}` : clock(shown)}</span>
        </button>
      </div>
    </>
  );
}
