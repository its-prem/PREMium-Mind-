import React, { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { playSquish, playBite } from '../lib/uiSounds';
import { pets, petBus, playingWithButterfly } from '../lib/pets';
import './Ant.css';

/**
 * A tricky ant crawls across the screen. Tap it and it gets squashed (squish +
 * a splat that stays for a moment); then another ant walks in.
 *
 * It gets harder with every ant you squash: faster, more random zig-zags,
 * sudden turns and feints, sprint bursts, it runs from the mouse and even
 * dodges a tap by jumping away. Level 0 (the first ant) is calm.
 *
 * Hidden in the PDF reader and for people who prefer reduced motion.
 * To remove: delete this file + Ant.css and <Ant /> in main.jsx.
 */

const SVG_W = 40; // drawn ant size
const SVG_H = 25;
const W = 56; // clickable box (bigger than the drawing, easier to tap)
const H = 40;
const MAX_LEVEL = 8;
const MAX_SPEED = 460; // px per second, hard cap
const NEXT_AFTER_KILL = [1500, 2800]; // ms
const NEXT_AFTER_EXIT = [5000, 9000];
const CORPSE_MS = 2300; // how long the squashed ant stays visible
const HIDE_ON = ['/watch', '/resume-editor', '/tools', '/mock-tests', '/syllabus', '/payment-return', '/login']; // no ant on these pages

const rand = (min, max) => min + Math.random() * (max - min);
const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
const wrapAngle = (a) => Math.atan2(Math.sin(a), Math.cos(a));

/** How tricky an ant is at a given level (number of ants squashed so far). */
function skill(level) {
  const l = Math.min(level, MAX_LEVEL);
  return {
    speedMin: 45 + l * 26,
    speedMax: 75 + l * 38,
    turnRate: 2.4 + l * 0.9, // how fast it can steer (rad/s)
    noise: 0.5 + l * 0.55, // random turning (rad/s)
    wobble: 0.012 + l * 0.009, // side-to-side zig-zag
    pull: Math.max(0.3, 0.9 - l * 0.08), // how strongly it heads for its goal
    juke: l === 0 ? 0.04 : 0.3 + l * 0.16, // sudden turns per second
    burst: 1.1 + l * 0.06, // sprint multiplier (kept modest so it stays catchable)
    fleeRadius: l === 0 ? 0 : 95 + l * 14, // runs from the mouse within this
    tapDodge: l === 0 ? 0 : Math.min(0.85, 0.12 + l * 0.1), // jumps away from a tap
    legSeconds: Math.max(0.06, 0.22 - l * 0.02),
    waypoints: Math.max(2, 4 - Math.floor(l / 3)),
    lifetime: Math.max(9, 16 - l), // seconds on screen before it heads out
  };
}

/** A random spot inside the screen. */
const insideSpot = () => ({
  x: rand(50, window.innerWidth - 50),
  y: rand(100, window.innerHeight - 50),
});

/** Start just outside a random screen edge, heading for a spot inside. */
function entrance() {
  const w = window.innerWidth;
  const h = window.innerHeight;
  const edge = Math.floor(Math.random() * 4); // 0 left 1 right 2 top 3 bottom
  const m = 40;
  const spot = insideSpot();
  switch (edge) {
    case 0:
      return { x: -m, y: rand(100, h - 60), tx: spot.x, ty: spot.y };
    case 1:
      return { x: w + m, y: rand(100, h - 60), tx: spot.x, ty: spot.y };
    case 2:
      return { x: rand(40, w - 40), y: -m, tx: spot.x, ty: spot.y };
    default:
      return { x: rand(40, w - 40), y: h + m, tx: spot.x, ty: spot.y };
  }
}

const LEGS = [
  // [startX, startY, endX, endY, group]
  [26, 12, 18, 3, 'a'],
  [29, 12, 29, 2, 'b'],
  [32, 12, 40, 3, 'a'],
  [26, 22, 18, 31, 'b'],
  [29, 22, 29, 32, 'a'],
  [32, 22, 40, 31, 'b'],
];

export default function Ant() {
  const { pathname } = useLocation();
  const hidden =
    HIDE_ON.some((p) => pathname.startsWith(p)) ||
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  const [alive, setAlive] = useState(false);
  const [antId, setAntId] = useState(0); // new id = new ant
  const [corpse, setCorpse] = useState(null); // the squashed ant + its splat
  const bodyRef = useRef(null);
  const nextTimer = useRef(null);
  const splatTimer = useRef(null);
  const kills = useRef(0); // squashed so far: the next ants are smarter

  // First ant arrives a few seconds after the page opens.
  useEffect(() => {
    if (hidden) return undefined;
    nextTimer.current = setTimeout(() => {
      setAlive(true);
      setAntId((n) => n + 1);
    }, rand(3000, 6000));
    return () => {
      clearTimeout(nextTimer.current);
      clearTimeout(splatTimer.current);
      setAlive(false);
      setCorpse(null);
    };
  }, [hidden]);

  const scheduleNext = (range) => {
    clearTimeout(nextTimer.current);
    // While someone is playing with the butterfly, new ants come much less often
    const extra = playingWithButterfly() ? rand(12000, 22000) : 0;
    nextTimer.current = setTimeout(() => {
      setAlive(true);
      setAntId((n) => n + 1);
    }, rand(range[0], range[1]) + extra);
  };

  // Crawling
  useEffect(() => {
    const el = bodyRef.current;
    if (hidden || !alive || !el) return undefined;

    const level = kills.current;
    const sk = skill(level);
    el.style.setProperty('--leg-s', `${sk.legSeconds}s`);

    const start = entrance();
    const pos = { x: start.x, y: start.y };
    let heading = Math.atan2(start.ty - start.y, start.tx - start.x);
    let target = { x: start.tx, y: start.ty };
    let baseSpeed = rand(sk.speedMin, sk.speedMax);
    let waypointsLeft = sk.waypoints;
    let exiting = false;
    let entered = false;
    const bornAt = performance.now();

    // random-motion state
    let noiseTurn = 0; // current random turning speed (rad/s)
    let noiseUntil = 0;
    let speedMult = 1; // sprint / slow-down bursts
    let speedMultUntil = 0;
    let freezeUntil = 0; // brief stop (a "feint")
    let dashUntil = 0; // after dodging: run fast for a moment
    let dodgeReadyAt = 0;
    let pauseUntil = 0;
    let phase = rand(0, 6);
    let last = performance.now();
    let raf = null;
    let gone = false;
    let pointer = null;
    let hunt = null; // { until } while this ant is chasing the butterfly
    let nextHuntCheck = performance.now() + rand(6000, 12000);
    let swallow = null; // a tap the ant dodged: its click must not hit what is underneath

    const dashAwayFrom = (px, py, jump) => {
      const away = Math.atan2(pos.y - py, pos.x - px) + rand(-0.7, 0.7);
      heading = away;
      if (jump) {
        swallow = { x: px, y: py, until: performance.now() + 450 };
        pos.x += Math.cos(away) * rand(70, 120);
        pos.y += Math.sin(away) * rand(70, 120);
      }
      const now = performance.now();
      dashUntil = now + rand(500, 900);
      dodgeReadyAt = now + 350;
      freezeUntil = 0;
      pauseUntil = 0;
      if (!exiting) target = insideSpot();
    };

    // A chase is rare: only some of the time, never twice in a row, and much rarer
    // while the user is busy playing with the butterfly.
    const startHunt = (now) => {
      hunt = { until: now + rand(7000, 10000) };
      pets.huntCooldownUntil = performance.now() + rand(90000, 150000);
    };

    // The ant reached the butterfly: a resting one is easy prey, a flying one often escapes.
    const resolveCatch = (now, bf) => {
      hunt = null;
      const chance = bf.resting ? 0.8 : 0.35;
      if (Math.random() < chance) {
        playBite();
        petBus.dispatchEvent(new Event('butterfly-caught'));
        pauseUntil = now + 1800; // the ant stays and "eats" for a moment
      } else {
        petBus.dispatchEvent(new Event('butterfly-escape'));
        pauseUntil = now + 700;
      }
    };

    const onStartHunt = () => {
      const bf = pets.butterfly;
      if (!hunt && bf && !bf.dead) startHunt(performance.now());
    };
    petBus.addEventListener('ant-start-hunt', onStartHunt);

    const frame = (now) => {
      raf = requestAnimationFrame(frame);
      const dt = Math.min(0.05, (now - last) / 1000);
      last = now;
      if (document.hidden || gone) return;
      phase += dt;

      const w = window.innerWidth;
      const h = window.innerHeight;
      if (!entered && pos.x > 10 && pos.x < w - 10 && pos.y > 70 && pos.y < h - 10) {
        entered = true;
      }

      // Now and then, decide to go after the butterfly
      if (!hunt && entered && !exiting && now > nextHuntCheck) {
        nextHuntCheck = now + 2000;
        const bf = pets.butterfly;
        const visible = bf && !bf.dead && bf.x > 0 && bf.x < w && bf.y > 60 && bf.y < h;
        const calm = performance.now() > pets.huntCooldownUntil && performance.now() - pets.startedAt > 20000;
        const chance = playingWithButterfly() ? 0.012 : 0.07;
        if (visible && calm && Math.random() < chance) startHunt(now);
      }

      // Runs away from the mouse (smarter ants notice it from further off)
      if (pointer && sk.fleeRadius && now > dodgeReadyAt) {
        const d = Math.hypot(pointer.x - pos.x, pointer.y - pos.y);
        if (d < sk.fleeRadius) dashAwayFrom(pointer.x, pointer.y, false);
      }

      if (now < pauseUntil || now < freezeUntil) {
        el.classList.add('is-paused');
      } else {
        el.classList.remove('is-paused');

        if (hunt) {
          const bf = pets.butterfly;
          if (!bf || bf.dead || now > hunt.until) {
            hunt = null; // lost it, or it is gone
          } else {
            const want = Math.atan2(bf.y - pos.y, bf.x - pos.x);
            heading += clamp(wrapAngle(want - heading), -7 * dt, 7 * dt);
            const huntSpeed = Math.min(330, 170 + level * 18);
            pos.x += Math.cos(heading) * huntSpeed * dt;
            pos.y += Math.sin(heading) * huntSpeed * dt;
            if (Math.hypot(bf.x - pos.x, bf.y - pos.y) < 26) resolveCatch(now, bf);
          }
        }

        if (!hunt) {
        // calm ants sometimes stop for a moment
        if (level === 0 && Math.random() < 0.004) pauseUntil = now + rand(700, 1500);

        // random turning: change direction of the "drunk walk" every so often
        if (now > noiseUntil) {
          noiseTurn = rand(-sk.noise, sk.noise);
          noiseUntil = now + rand(120, 450);
        }

        // sprint / slow bursts
        if (now > speedMultUntil) {
          const r = Math.random();
          speedMult = r < 0.4 ? rand(1, sk.burst) : r < 0.7 ? rand(0.4, 0.75) : 1;
          speedMultUntil = now + rand(250, 700);
        }

        // sudden juke: a quick sharp turn, sometimes after a tiny feint stop
        if (Math.random() < sk.juke * dt) {
          heading += (Math.random() < 0.5 ? -1 : 1) * rand(1.0, 2.5);
          if (level >= 3 && Math.random() < 0.4) freezeUntil = now + rand(80, 200);
        }

        // too old: time to leave (smarter ants stay a shorter time)
        if (!exiting && now - bornAt > sk.lifetime * 1000) {
          exiting = true;
          waypointsLeft = 0;
          target = {
            x: pos.x < w / 2 ? -80 : w + 80,
            y: clamp(pos.y, 80, h - 40),
          };
        }

        // keep inside the screen until it chooses to leave
        if (entered && !exiting && (pos.x < 6 || pos.x > w - 6 || pos.y < 70 || pos.y > h - 6)) {
          heading = Math.atan2(h / 2 - pos.y, w / 2 - pos.x);
        }

        // head toward the goal, but only as strongly as `pull` allows
        const want = Math.atan2(target.y - pos.y, target.x - pos.x);
        const diff = wrapAngle(want - heading);
        const steer = clamp(diff * sk.pull * 4, -sk.turnRate * dt * 3, sk.turnRate * dt * 3);
        heading += steer;
        heading += noiseTurn * dt;
        heading += Math.sin(phase * (3 + level)) * sk.wobble;

        const dashing = now < dashUntil;
        // never faster than a human eye can follow
        const speed = Math.min(MAX_SPEED, baseSpeed * speedMult * (dashing ? 1.5 : 1));
        pos.x += Math.cos(heading) * speed * dt;
        pos.y += Math.sin(heading) * speed * dt;

        const dist = Math.hypot(target.x - pos.x, target.y - pos.y);
        if (dist < 24) {
          if (waypointsLeft > 0) {
            waypointsLeft -= 1;
            target = insideSpot();
            baseSpeed = rand(sk.speedMin, sk.speedMax);
          } else if (!exiting) {
            exiting = true;
            target = {
              x: pos.x < w / 2 ? -80 : w + 80,
              y: clamp(pos.y, 80, h - 40),
            };
          }
        }
        // while leaving, head straight out without wandering
        if (exiting) heading += wrapAngle(Math.atan2(target.y - pos.y, target.x - pos.x) - heading) * 0.25;
        }
      }

      el.style.transform = `translate3d(${pos.x - W / 2}px, ${pos.y - H / 2}px, 0) rotate(${heading}rad)`;
      el.__pos = { x: pos.x, y: pos.y };
      pets.ant = { x: pos.x, y: pos.y, hunting: !!hunt };
      el.__heading = heading;

      // walked off screen
      const m = 90;
      if (pos.x < -m || pos.y < -m || pos.x > w + m || pos.y > h + m) {
        gone = true;
        setAlive(false);
        scheduleNext(NEXT_AFTER_EXIT);
      }
    };

    const onMove = (e) => {
      if (e.pointerType === 'touch') return;
      pointer = { x: e.clientX, y: e.clientY };
    };

    // A tap or press near the ant: smart ants may jump away before it lands
    const onDown = (e) => {
      if (!sk.tapDodge || performance.now() < dodgeReadyAt) return;
      const d = Math.hypot(e.clientX - pos.x, e.clientY - pos.y);
      if (d < 70 && Math.random() < sk.tapDodge) {
        dashAwayFrom(e.clientX, e.clientY, true);
      }
    };

    // Swallow the click that follows a dodged tap, so it does not press a
    // button that was hidden under the ant.
    const onClickCapture = (e) => {
      if (!swallow || performance.now() > swallow.until) return;
      if (Math.hypot(e.clientX - swallow.x, e.clientY - swallow.y) < 90) {
        e.stopPropagation();
        e.preventDefault();
      }
    };

    window.addEventListener('pointermove', onMove, { passive: true });
    window.addEventListener('pointerdown', onDown, true);
    window.addEventListener('click', onClickCapture, true);
    raf = requestAnimationFrame(frame);
    return () => {
      gone = true;
      pets.ant = null;
      petBus.removeEventListener('ant-start-hunt', onStartHunt);
      cancelAnimationFrame(raf);
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerdown', onDown, true);
      window.removeEventListener('click', onClickCapture, true);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [alive, antId, hidden]);

  const squash = (e) => {
    e.stopPropagation();
    const el = bodyRef.current;
    if (!el) return;
    const p = el.__pos || { x: 0, y: 0 };
    playSquish();
    kills.current += 1;
    // The live ant is replaced by a flattened ant + splat that stay briefly
    setCorpse({ id: Date.now(), x: p.x, y: p.y, heading: el.__heading || 0 });
    clearTimeout(splatTimer.current);
    splatTimer.current = setTimeout(() => setCorpse(null), CORPSE_MS);
    setAlive(false);
    scheduleNext(NEXT_AFTER_KILL);
  };

  if (hidden) return null;

  return (
    <>
      {alive && (
        <div
          key={antId}
          ref={bodyRef}
          className="pm-ant"
          data-no-sound
          role="img"
          aria-label="An ant. Tap to squash it"
          onClick={squash}
          style={{ width: W, height: H, transform: 'translate3d(-200px,-200px,0)' }}
        >
          <svg viewBox="0 0 54 34" width={SVG_W} height={SVG_H} aria-hidden="true">
            {LEGS.map(([x1, y1, x2, y2, group], i) => (
              <line
                key={i}
                className={`pm-ant-leg leg-${group}`}
                x1={x1}
                y1={y1}
                x2={x2}
                y2={y2}
                style={{ transformOrigin: `${x1}px ${y1}px` }}
              />
            ))}
            <ellipse cx="13" cy="17" rx="9.5" ry="6.6" className="pm-ant-body" />
            <ellipse cx="27.5" cy="17" rx="5.6" ry="4.4" className="pm-ant-body" />
            <circle cx="40" cy="17" r="5" className="pm-ant-body" />
            <path className="pm-ant-feeler" d="M43.5 14 C47 10 50 9 52 10 M43.5 20 C47 24 50 25 52 24" />
            <circle cx="42" cy="15" r="0.9" fill="#fff" />
            <circle cx="42" cy="19" r="0.9" fill="#fff" />
          </svg>
        </div>
      )}

      {corpse && (
        <div
          key={corpse.id}
          className="pm-ant-corpse"
          style={{
            left: corpse.x,
            top: corpse.y,
            transform: `translate(-50%, -50%) rotate(${corpse.heading}rad)`,
          }}
          aria-hidden="true"
        >
          {/* goo splatter under the body */}
          <svg className="pm-ant-goo" viewBox="0 0 70 50" width="54" height="39">
            <ellipse cx="35" cy="25" rx="24" ry="13" />
            <circle cx="10" cy="14" r="5" />
            <circle cx="60" cy="36" r="5.5" />
            <circle cx="52" cy="9" r="3" />
            <circle cx="18" cy="41" r="3.4" />
            <circle cx="64" cy="18" r="2.2" />
          </svg>
          {/* the flattened ant: body parts squeezed together, legs splayed */}
          <svg className="pm-ant-flat" viewBox="0 0 54 34" width={SVG_W} height={SVG_H}>
            {LEGS.map(([x1, y1, x2, y2], i) => (
              <line
                key={i}
                className="pm-ant-leg"
                x1={x1}
                y1={y1}
                x2={x2 + (x2 > x1 ? 5 : -5)}
                y2={y1 + (y2 - y1) * 1.5}
              />
            ))}
            <ellipse cx="13" cy="17" rx="10.5" ry="5.2" className="pm-ant-body" />
            <ellipse cx="27.5" cy="17" rx="6.4" ry="3.8" className="pm-ant-body" />
            <ellipse cx="40.5" cy="17" rx="5.6" ry="4.4" className="pm-ant-body" />
          </svg>
        </div>
      )}
    </>
  );
}
