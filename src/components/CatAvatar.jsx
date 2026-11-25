import React, { useCallback, useEffect, useRef, useState } from 'react';
import { playAngryMeow, playMeow, playYawn } from '../lib/catSounds';
import './CatAvatar.css';

const SLEEP_AFTER_MS = 15000; // no activity -> the cat falls asleep
const ANGRY_CLICKS = 5; // this many taps ...
const ANGRY_WINDOW_MS = 3000; // ... within this time -> angry
const ANGRY_FOR_MS = 4200;

/**
 * The sidebar kitty. It has moods:
 *  - normal: meows when tapped, its eyes follow the pointer
 *  - angry : tapped 5 times quickly -> frowns, shakes and growls
 *  - sleepy: nothing happens for a while -> eyes close, "Zzz"; a tap wakes it
 * Mood styles are in CatAvatar.css; the base cat look is in Sidebar.css.
 */
export default function CatAvatar({ active = true }) {
  const [mood, setMood] = useState('idle'); // idle | angry | sleepy
  const [hop, setHop] = useState(false);

  const catRef = useRef(null);
  const moodRef = useRef('idle');
  const taps = useRef([]);
  const sleepTimer = useRef(null);
  const angryTimer = useRef(null);
  const hopTimer = useRef(null);
  moodRef.current = mood;

  const startSleepTimer = useCallback(() => {
    clearTimeout(sleepTimer.current);
    sleepTimer.current = setTimeout(() => {
      if (moodRef.current === 'idle') setMood('sleepy');
    }, SLEEP_AFTER_MS);
  }, []);

  // Sleep timer + "activity" wake-up of the timer (not of the cat itself).
  useEffect(() => {
    if (!active) {
      clearTimeout(sleepTimer.current);
      clearTimeout(angryTimer.current);
      setMood('idle');
      return undefined;
    }

    startSleepTimer();
    const poke = () => {
      if (moodRef.current === 'idle') startSleepTimer();
    };
    window.addEventListener('pointermove', poke, { passive: true });
    window.addEventListener('pointerdown', poke, { passive: true });
    window.addEventListener('keydown', poke);
    return () => {
      clearTimeout(sleepTimer.current);
      window.removeEventListener('pointermove', poke);
      window.removeEventListener('pointerdown', poke);
      window.removeEventListener('keydown', poke);
    };
  }, [active, startSleepTimer]);

  // Eyes follow the pointer (only while the cat is calm and awake).
  useEffect(() => {
    if (!active) return undefined;
    let raf = null;
    let last = null;

    const apply = () => {
      raf = null;
      const cat = catRef.current;
      if (!cat || !last) return;
      if (moodRef.current !== 'idle') {
        cat.style.setProperty('--lx', '0px');
        cat.style.setProperty('--ly', '0px');
        return;
      }
      const r = cat.getBoundingClientRect();
      const dx = last.x - (r.left + r.width / 2);
      const dy = last.y - (r.top + r.height / 2);
      const clamp = (v) => Math.max(-1, Math.min(1, v));
      cat.style.setProperty('--lx', `${(clamp(dx / 90) * 2.6).toFixed(2)}px`);
      cat.style.setProperty('--ly', `${(clamp(dy / 90) * 2.2).toFixed(2)}px`);
    };

    const onMove = (e) => {
      if (e.pointerType === 'touch') return;
      last = { x: e.clientX, y: e.clientY };
      if (!raf) raf = requestAnimationFrame(apply);
    };

    window.addEventListener('pointermove', onMove, { passive: true });
    return () => {
      window.removeEventListener('pointermove', onMove);
      if (raf) cancelAnimationFrame(raf);
    };
  }, [active]);

  useEffect(
    () => () => {
      clearTimeout(sleepTimer.current);
      clearTimeout(angryTimer.current);
      clearTimeout(hopTimer.current);
    },
    []
  );

  const doHop = () => {
    setHop(true);
    clearTimeout(hopTimer.current);
    hopTimer.current = setTimeout(() => setHop(false), 700);
  };

  const handleClick = (e) => {
    e.stopPropagation(); // the avatar box itself opens the photo picker

    // A sleeping cat wakes up with a yawn.
    if (moodRef.current === 'sleepy') {
      setMood('idle');
      taps.current = [];
      playYawn();
      doHop();
      startSleepTimer();
      return;
    }

    const now = Date.now();
    taps.current = [...taps.current.filter((t) => now - t < ANGRY_WINDOW_MS), now];

    if (taps.current.length >= ANGRY_CLICKS) {
      taps.current = [];
      setMood('angry');
      playAngryMeow();
      clearTimeout(angryTimer.current);
      angryTimer.current = setTimeout(() => {
        setMood('idle');
        startSleepTimer();
      }, ANGRY_FOR_MS);
      return;
    }

    if (moodRef.current === 'angry') {
      // poking an angry cat again keeps it grumpy a bit longer
      playAngryMeow();
      clearTimeout(angryTimer.current);
      angryTimer.current = setTimeout(() => setMood('idle'), ANGRY_FOR_MS);
      return;
    }

    playMeow();
    doHop();
    startSleepTimer();
  };

  const title =
    mood === 'sleepy' ? 'Zzz... tap to wake me' : mood === 'angry' ? 'Grrr!' : 'Tap me! Meow';

  return (
    <div
      ref={catRef}
      className={`pm-sb-cat ${hop ? 'is-meowing' : ''} ${
        mood === 'angry' ? 'is-angry' : ''
      } ${mood === 'sleepy' ? 'is-sleepy' : ''}`}
      role="button"
      data-no-sound
      title={title}
      onClick={handleClick}
    >
      <span className="pm-sb-cat-ear left" />
      <span className="pm-sb-cat-ear right" />
      <div className="pm-sb-cat-face">
        <span className="pm-sb-cat-eye left" />
        <span className="pm-sb-cat-eye right" />
        <span className="pm-sb-cat-brow left" />
        <span className="pm-sb-cat-brow right" />
        <span className="pm-sb-cat-blush left" />
        <span className="pm-sb-cat-blush right" />
        <span className="pm-sb-cat-nose" />
        <span className="pm-sb-cat-mouth" />
      </div>
      <span className="pm-sb-cat-anger" aria-hidden="true">
        💢
      </span>
      <span className="pm-sb-cat-zzz" aria-hidden="true">
        <i>z</i>
        <i>z</i>
        <i>Z</i>
      </span>
    </div>
  );
}
