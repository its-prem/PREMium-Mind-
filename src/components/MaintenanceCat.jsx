import React, { useCallback, useEffect, useRef, useState } from 'react';
import { playAngryMeow, playMeow, playYawn, startPurr } from '../lib/catSounds';

/**
 * The maintenance-page cat: a detailed tabby wearing a hard hat and fixing the
 * site with a wrench. Drawn as SVG (coordinates share the scene's viewBox).
 *
 * Interactions
 *  - the eyes follow the pointer
 *  - tap: meow + speech bubble        - hold: purr + floating hearts
 *  - 5 quick taps: angry (growl)      - idle for a while: falls asleep, a tap wakes it
 * Styles are in Maintenance.css (classes start with "mc-").
 */

const SLEEP_AFTER_MS = 15000;
const ANGRY_TAPS = 5;
const ANGRY_WINDOW_MS = 3000;
const ANGRY_FOR_MS = 4200;
const HOLD_TO_PURR_MS = 450;

const TAP_LINES = ['Meow!', 'Fixing it!', 'Almost done!', 'Mrrow?', 'Back soon!'];
const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
const pick = (list) => list[Math.floor(Math.random() * list.length)];

export default function MaintenanceCat() {
  const rootRef = useRef(null);
  const [mood, setMood] = useState('idle'); // idle | angry | sleepy
  const [hop, setHop] = useState(false);
  const [purring, setPurring] = useState(false);
  const [look, setLook] = useState({ x: 0, y: 0 });
  const [bubble, setBubble] = useState(null);
  const [hearts, setHearts] = useState([]);

  const moodRef = useRef('idle');
  moodRef.current = mood;
  const taps = useRef([]);
  const timers = useRef({});
  const stopPurr = useRef(null);
  const heldRef = useRef(false);

  const later = (key, fn, ms) => {
    clearTimeout(timers.current[key]);
    timers.current[key] = setTimeout(fn, ms);
  };

  const say = (text) => setBubble({ id: Date.now(), text });

  const startSleepTimer = useCallback(() => {
    clearTimeout(timers.current.sleep);
    timers.current.sleep = setTimeout(() => {
      if (moodRef.current === 'idle') {
        setMood('sleepy');
        setBubble({ id: Date.now(), text: 'Zzz...' });
      }
    }, SLEEP_AFTER_MS);
  }, []);

  // Eyes follow the pointer; any activity postpones sleeping
  useEffect(() => {
    startSleepTimer();
    let raf = null;
    let last = null;

    const apply = () => {
      raf = null;
      const head = rootRef.current?.querySelector('.mc-head');
      if (!head || !last || moodRef.current !== 'idle') {
        setLook((p) => (p.x === 0 && p.y === 0 ? p : { x: 0, y: 0 }));
        return;
      }
      const r = head.getBoundingClientRect();
      const dx = last.x - (r.left + r.width / 2);
      const dy = last.y - (r.top + r.height / 2);
      setLook({ x: clamp(dx / 110, -1, 1) * 3.4, y: clamp(dy / 110, -1, 1) * 2.4 });
    };

    const onMove = (e) => {
      if (moodRef.current === 'idle') startSleepTimer();
      if (e.pointerType === 'touch') return;
      last = { x: e.clientX, y: e.clientY };
      if (!raf) raf = requestAnimationFrame(apply);
    };
    const poke = () => {
      if (moodRef.current === 'idle') startSleepTimer();
    };

    window.addEventListener('pointermove', onMove, { passive: true });
    window.addEventListener('pointerdown', poke, { passive: true });
    window.addEventListener('keydown', poke);
    const t = timers.current;
    return () => {
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerdown', poke);
      window.removeEventListener('keydown', poke);
      if (raf) cancelAnimationFrame(raf);
      Object.values(t).forEach(clearTimeout);
      if (stopPurr.current) stopPurr.current();
    };
  }, [startSleepTimer]);

  // Press and hold -> purr + hearts
  const beginPress = () => {
    heldRef.current = false;
    clearTimeout(timers.current.hold);
    timers.current.hold = setTimeout(() => {
      if (moodRef.current !== 'idle') return;
      heldRef.current = true;
      setPurring(true);
      say('Purrr~');
      stopPurr.current = startPurr();
      const spawn = () => {
        const id = `${Date.now()}-${Math.random()}`;
        setHearts((h) => [...h.slice(-5), { id, x: 352 + Math.random() * 44, dx: (Math.random() - 0.5) * 26 }]);
        timers.current.heartLoop = setTimeout(spawn, 420);
      };
      spawn();
    }, HOLD_TO_PURR_MS);
  };

  const endPress = () => {
    clearTimeout(timers.current.hold);
    clearTimeout(timers.current.heartLoop);
    if (stopPurr.current) {
      stopPurr.current();
      stopPurr.current = null;
    }
    setPurring(false);
    setTimeout(() => setHearts([]), 1700);
  };

  const hopOnce = () => {
    setHop(true);
    later('hop', () => setHop(false), 650);
  };

  const onTap = (e) => {
    e.stopPropagation();
    if (heldRef.current) {
      heldRef.current = false; // this click ended a purr, not a tap
      return;
    }

    if (moodRef.current === 'sleepy') {
      setMood('idle');
      taps.current = [];
      playYawn();
      say('*yawn*');
      hopOnce();
      startSleepTimer();
      return;
    }

    const now = Date.now();
    taps.current = [...taps.current.filter((t) => now - t < ANGRY_WINDOW_MS), now];

    if (taps.current.length >= ANGRY_TAPS || moodRef.current === 'angry') {
      if (moodRef.current !== 'angry') taps.current = [];
      setMood('angry');
      playAngryMeow();
      say('GRRR!!');
      later(
        'angry',
        () => {
          setMood('idle');
          startSleepTimer();
        },
        ANGRY_FOR_MS
      );
      return;
    }

    playMeow();
    say(pick(TAP_LINES));
    hopOnce();
    startSleepTimer();
  };

  useEffect(() => {
    if (!bubble) return undefined;
    const t = setTimeout(() => setBubble(null), mood === 'sleepy' ? 4000 : 1900);
    return () => clearTimeout(t);
  }, [bubble, mood]);

  const classes = ['mc', mood === 'angry' && 'is-angry', mood === 'sleepy' && 'is-sleepy', hop && 'is-hop', purring && 'is-purr']
    .filter(Boolean)
    .join(' ');

  return (
    <g ref={rootRef} className={classes}>
      <defs>
        <radialGradient id="mcFur" cx="0.5" cy="0.35" r="0.75">
          <stop offset="0" stopColor="#f6c288" />
          <stop offset="0.55" stopColor="#e39a4c" />
          <stop offset="1" stopColor="#b9692a" />
        </radialGradient>
        <radialGradient id="mcFurDark" cx="0.5" cy="0.3" r="0.8">
          <stop offset="0" stopColor="#e9a35a" />
          <stop offset="1" stopColor="#a85d25" />
        </radialGradient>
        <radialGradient id="mcWhite" cx="0.5" cy="0.4" r="0.7">
          <stop offset="0" stopColor="#ffffff" />
          <stop offset="1" stopColor="#f1e3d2" />
        </radialGradient>
        <radialGradient id="mcIris" cx="0.45" cy="0.4" r="0.65">
          <stop offset="0" stopColor="#fff3b0" />
          <stop offset="0.45" stopColor="#b5d93c" />
          <stop offset="1" stopColor="#3f7a1a" />
        </radialGradient>
        <linearGradient id="mcHat" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#fde047" />
          <stop offset="1" stopColor="#eab308" />
        </linearGradient>
        <clipPath id="mcEyeL">
          <path d="M338 164 Q352 152 366 164 Q352 176 338 164 Z" />
        </clipPath>
        <clipPath id="mcEyeR">
          <path d="M378 164 Q392 152 406 164 Q392 176 378 164 Z" />
        </clipPath>
      </defs>

      {/* shadow */}
      <ellipse cx="372" cy="285" rx="62" ry="6" fill="rgba(0,0,0,0.4)" />

      {/* tail */}
      <g className="mt-art-tail" style={{ transformOrigin: '420px 268px' }}>
        <path d="M418 270 C452 268 464 228 442 206" fill="none" stroke="#a85d25" strokeWidth="15" strokeLinecap="round" />
        <path d="M418 270 C452 268 464 228 442 206" fill="none" stroke="url(#mcFurDark)" strokeWidth="11" strokeLinecap="round" />
        <path d="M424 270 C450 262 458 238 447 218" fill="none" stroke="#7a3f14" strokeWidth="11" strokeLinecap="butt" strokeDasharray="3 9" opacity="0.65" />
        <circle cx="442" cy="206" r="5.5" fill="#7a3f14" />
      </g>

      <g className="mc-body" style={{ transformOrigin: '372px 284px' }}>
        {/* haunches + body */}
        <ellipse cx="328" cy="262" rx="21" ry="24" fill="url(#mcFurDark)" />
        <ellipse cx="416" cy="262" rx="21" ry="24" fill="url(#mcFurDark)" />
        <ellipse cx="372" cy="244" rx="52" ry="46" fill="url(#mcFur)" />
        <ellipse cx="372" cy="238" rx="16" ry="22" fill="url(#mcWhite)" />
        {/* tabby stripes on the sides */}
        <g stroke="#7a3f14" strokeWidth="3.2" strokeLinecap="round" fill="none" opacity="0.55">
          <path d="M326 222 q11 7 9 22" />
          <path d="M322 242 q12 6 11 20" />
          <path d="M322 262 q10 4 10 14" />
          <path d="M418 222 q-11 7 -9 22" />
          <path d="M422 242 q-12 6 -11 20" />
          <path d="M422 262 q-10 4 -10 14" />
        </g>
        {/* chest fur tufts */}
        <g stroke="#e6d6c2" strokeWidth="1.4" strokeLinecap="round" fill="none" opacity="0.9">
          <path d="M366 232 q2 6 0 12" />
          <path d="M372 230 q2 7 0 14" />
          <path d="M378 232 q2 6 0 12" />
        </g>

        {/* front legs + white paws */}
        <path d="M349 238 h23 v40 q0 6 -11.5 6 q-11.5 0 -11.5 -6 Z" fill="url(#mcFur)" />
        <path d="M372 238 h23 v40 q0 6 -11.5 6 q-11.5 0 -11.5 -6 Z" fill="url(#mcFur)" />
        <ellipse cx="360.5" cy="283" rx="13" ry="7.5" fill="url(#mcWhite)" />
        <ellipse cx="383.5" cy="283" rx="13" ry="7.5" fill="url(#mcWhite)" />
        <path d="M372 244 v36" stroke="#9a5a22" strokeWidth="1.6" strokeLinecap="round" opacity="0.55" />
        <g stroke="#c9b49a" strokeWidth="1.3" strokeLinecap="round">
          <path d="M355 280 v5" />
          <path d="M360 280 v5" />
          <path d="M364 280 v5" />
          <path d="M381 280 v5" />
          <path d="M385 280 v5" />
          <path d="M389 280 v5" />
        </g>

        {/* head */}
        <g className="mc-head">
          {/* ears (they twitch) */}
          <g className="mc-ear mc-ear-l" style={{ transformOrigin: '344px 148px' }}>
            <path d="M338 152 L324 112 Q346 118 356 142 Z" fill="url(#mcFur)" />
            <path d="M340 146 L333 123 Q346 128 351 142 Z" fill="#d9847c" />
            <path d="M324 112 Q330 112 334 116 L328 122 Z" fill="#7a3f14" opacity="0.8" />
          </g>
          <g className="mc-ear mc-ear-r" style={{ transformOrigin: '400px 148px' }}>
            <path d="M406 152 L420 112 Q398 118 388 142 Z" fill="url(#mcFur)" />
            <path d="M404 146 L411 123 Q398 128 393 142 Z" fill="#d9847c" />
            <path d="M420 112 Q414 112 410 116 L416 122 Z" fill="#7a3f14" opacity="0.8" />
          </g>

          <ellipse cx="372" cy="170" rx="46" ry="39" fill="url(#mcFur)" />
          {/* cheek fluff */}
          <path d="M327 174 l-10 7 l12 3 z" fill="#e39a4c" />
          <path d="M327 184 l-9 9 l13 -1 z" fill="#d98e3f" />
          <path d="M417 174 l10 7 l-12 3 z" fill="#e39a4c" />
          <path d="M417 184 l9 9 l-13 -1 z" fill="#d98e3f" />

          {/* forehead "M" and cheek stripes */}
          <g stroke="#7a3f14" strokeWidth="3" strokeLinecap="round" fill="none" opacity="0.65">
            <path d="M372 134 v15" />
            <path d="M362 136 q3 8 0 15" />
            <path d="M382 136 q-3 8 0 15" />
            <path d="M331 172 q9 1 16 -3" />
            <path d="M331 182 q9 -1 15 -5" />
            <path d="M413 172 q-9 1 -16 -3" />
            <path d="M413 182 q-9 -1 -15 -5" />
          </g>

          {/* muzzle, chin, nose, mouth */}
          <ellipse cx="372" cy="188" rx="19" ry="13" fill="url(#mcWhite)" />
          <ellipse cx="372" cy="201" rx="10" ry="6.5" fill="url(#mcWhite)" />
          <path d="M365 180 h14 l-7 8 z" fill="#d98880" stroke="#a85a52" strokeWidth="0.8" strokeLinejoin="round" />
          <path className="mc-mouth" d="M372 188 v4 M372 192 q-6 6 -12 2 M372 192 q6 6 12 2" fill="none" stroke="#5b3a2a" strokeWidth="1.6" strokeLinecap="round" />
          <g stroke="#c9b49a" strokeWidth="1" opacity="0.7">
            <circle cx="363" cy="186" r="0.8" fill="#8a6a50" />
            <circle cx="367" cy="190" r="0.8" fill="#8a6a50" />
            <circle cx="381" cy="186" r="0.8" fill="#8a6a50" />
            <circle cx="377" cy="190" r="0.8" fill="#8a6a50" />
          </g>

          {/* whiskers */}
          <g className="mc-whiskers" stroke="rgba(255,255,255,0.92)" strokeWidth="1.2" strokeLinecap="round" fill="none">
            <path d="M356 190 Q338 184 318 182" />
            <path d="M356 194 Q338 194 318 197" />
            <path d="M358 198 Q340 204 322 212" />
            <path d="M388 190 Q406 184 426 182" />
            <path d="M388 194 Q406 194 426 197" />
            <path d="M386 198 Q404 204 422 212" />
          </g>

          {/* open eyes (blink + follow the pointer) */}
          <g className="mc-eyes" style={{ transformOrigin: '372px 164px' }}>
            {[
              { cx: 352, clip: 'mcEyeL', d: 'M338 164 Q352 152 366 164 Q352 176 338 164 Z' },
              { cx: 392, clip: 'mcEyeR', d: 'M378 164 Q392 152 406 164 Q392 176 378 164 Z' },
            ].map((eye) => (
              <g key={eye.cx}>
                <path d={eye.d} fill="#1b1b1f" />
                <g clipPath={`url(#${eye.clip})`}>
                  <g style={{ transform: `translate(${look.x}px, ${look.y}px)`, transition: 'transform 0.12s ease-out' }}>
                    <circle cx={eye.cx} cy="164" r="8.4" fill="url(#mcIris)" />
                    <ellipse className="mc-pupil" cx={eye.cx} cy="164" rx="2.4" ry="7.4" fill="#050505" />
                    <circle cx={eye.cx + 2.6} cy="160.8" r="1.9" fill="#ffffff" />
                    <circle cx={eye.cx - 2.4} cy="167.4" r="0.9" fill="#ffffff" opacity="0.7" />
                  </g>
                </g>
                <path d={eye.d} fill="none" stroke="#3b2412" strokeWidth="1.6" strokeLinejoin="round" />
              </g>
            ))}
          </g>

          {/* closed eyes (sleeping) */}
          <g className="mc-closed" fill="none" stroke="#3b2412" strokeWidth="2.2" strokeLinecap="round">
            <path d="M339 164 Q352 172 365 164" />
            <path d="M379 164 Q392 172 405 164" />
          </g>

          {/* angry brows */}
          <g className="mc-brow" stroke="#5b2a0e" strokeWidth="3.6" strokeLinecap="round">
            <path d="M338 152 L364 160" />
            <path d="M406 152 L380 160" />
          </g>

          {/* hard hat */}
          <path d="M344 140 A28 25 0 0 1 400 140 Z" fill="url(#mcHat)" stroke="#ca8a04" strokeWidth="2" strokeLinejoin="round" />
          <rect x="368" y="114" width="8" height="26" rx="3" fill="#fde68a" />
          <path d="M351 132 Q358 118 372 115" fill="none" stroke="#ffffff" strokeWidth="3" strokeLinecap="round" opacity="0.4" />
          <rect x="336" y="137" width="72" height="9" rx="4.5" fill="#eab308" stroke="#ca8a04" strokeWidth="1.5" />
          <circle cx="372" cy="127" r="4" fill="#fff7ae" stroke="#ca8a04" strokeWidth="1" />
        </g>

        {/* raised arm gripping the wrench (it taps the site) */}
        <g className="mt-art-wrench" style={{ transformOrigin: '356px 246px' }}>
          {/* upper arm (thicker) and forearm (slimmer) with a soft shadow side */}
          <line x1="356" y1="247" x2="339" y2="236" stroke="#b9692a" strokeWidth="18" strokeLinecap="round" />
          <line x1="356" y1="246" x2="339" y2="235" stroke="#e39a4c" strokeWidth="14.5" strokeLinecap="round" />
          <line x1="339" y1="236" x2="326" y2="228" stroke="#b9692a" strokeWidth="13" strokeLinecap="round" />
          <line x1="339" y1="235" x2="326" y2="227" stroke="#eaa55e" strokeWidth="10" strokeLinecap="round" />
          {/* tabby stripes on the arm */}
          <g stroke="#7a3f14" strokeWidth="2.3" strokeLinecap="round" opacity="0.55">
            <path d="M349 238 l3 6" />
            <path d="M343 233 l3 6" />
            <path d="M335 229 l2.5 5" />
          </g>

          {/* wrench: grip, steel shaft, open-end head */}
          <line x1="323" y1="226" x2="301" y2="208" stroke="#6b7280" strokeWidth="8" strokeLinecap="round" />
          <line x1="323" y1="226" x2="301" y2="208" stroke="#d4d4d8" strokeWidth="5" strokeLinecap="round" />
          <line x1="322" y1="225" x2="311" y2="216" stroke="#dc2626" strokeWidth="8.5" strokeLinecap="round" />
          <line x1="321" y1="224" x2="312" y2="217" stroke="#ef4444" strokeWidth="4.5" strokeLinecap="round" />
          <circle cx="297" cy="204" r="9.5" fill="#6b7280" />
          <circle cx="297" cy="204" r="8" fill="#e4e4e7" />
          <rect x="289" y="196" width="8.5" height="8.5" fill="#1b1b1f" transform="rotate(45 293 200)" />

          {/* paw wrapped round the grip: pad + three curled toes */}
          <ellipse cx="326" cy="228" rx="9.5" ry="8" fill="url(#mcWhite)" stroke="#d9c7b0" strokeWidth="0.9" transform="rotate(35 326 228)" />
          <g fill="url(#mcWhite)" stroke="#d9c7b0" strokeWidth="0.9">
            <ellipse cx="318" cy="222" rx="3.6" ry="3" transform="rotate(35 318 222)" />
            <ellipse cx="316" cy="228" rx="3.6" ry="3" transform="rotate(35 316 228)" />
            <ellipse cx="320" cy="233" rx="3.6" ry="3" transform="rotate(35 320 233)" />
          </g>
          <g stroke="#cdb99f" strokeWidth="0.9" strokeLinecap="round">
            <path d="M318.5 225 v0.1" />
            <path d="M317.5 230 v0.1" />
          </g>
        </g>
      </g>

      {/* sleeping Zs */}
      <g className="mc-zzz" fill="#ffffff" fontWeight="800" fontSize="15">
        <text x="404" y="112">z</text>
        <text x="414" y="98" fontSize="19">z</text>
        <text x="426" y="80" fontSize="23">Z</text>
      </g>

      {/* hearts while purring */}
      {hearts.map((h) => (
        <text
          key={h.id}
          className="mc-heart"
          x={h.x}
          y="124"
          style={{ '--dx': `${h.dx}px` }}
        >
          ♥
        </text>
      ))}

      {/* speech bubble */}
      {bubble && (
        <g key={bubble.id} className="mc-bubble" style={{ transformOrigin: '404px 100px' }}>
          <rect x="370" y="62" width="100" height="28" rx="14" fill="#ffffff" />
          <path d="M398 89 l6 9 l6 -9 z" fill="#ffffff" />
          <text x="420" y="81" textAnchor="middle" fontSize="12.5" fontWeight="800" fill="#09090b">
            {bubble.text}
          </text>
        </g>
      )}

      {/* invisible hit area: tap, hold to purr */}
      <rect
        className="mc-hit"
        x="312"
        y="104"
        width="128"
        height="184"
        fill="transparent"
        data-no-sound
        onClick={onTap}
        onPointerDown={beginPress}
        onPointerUp={endPress}
        onPointerLeave={endPress}
        onPointerCancel={endPress}
      />
    </g>
  );
}
