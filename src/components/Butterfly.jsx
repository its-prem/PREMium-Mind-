import React, { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { playTune, playFlutter } from '../lib/uiSounds';
import { pets, petBus, touchButterfly } from '../lib/pets';
import './Butterfly.css';

/**
 * A small butterfly that flutters around the page, sometimes lands and rests,
 * flies away when the mouse comes close, and sparkles (with a chime and a flutter) when tapped.
 * Once in a while the ant chases it: it runs from the ant, and now and then gets caught.
 * Hidden in the PDF reader (so it never distracts reading) and for people who
 * prefer reduced motion.
 * To remove: delete this file + Butterfly.css and <Butterfly /> in main.jsx.
 */

const SIZE = 46;
const CRUISE_SPEED = 70; // px per second
const FLEE_SPEED = 260;
const FLEE_RADIUS = 80; // mouse closer than this scares it
const HIDE_ON = ['/watch', '/resume-editor', '/tools', '/mock-tests', '/syllabus', '/payment-return'];

const rand = (min, max) => min + Math.random() * (max - min);

export default function Butterfly() {
  const { pathname } = useLocation();
  const hidden =
    HIDE_ON.some((p) => pathname.startsWith(p)) ||
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  const bodyRef = useRef(null);
  const [sparks, setSparks] = useState([]);
  const [resting, setResting] = useState(false);
  const [eaten, setEaten] = useState(false);

  useEffect(() => {
    const el = bodyRef.current;
    if (hidden || !el) return undefined;

    const W = () => window.innerWidth;
    const H = () => window.innerHeight;
    const pick = () => ({ x: rand(30, W() - 30), y: rand(80, H() - 40) });

    const pos = { x: -SIZE, y: rand(120, H() - 120) }; // enters from the left
    let target = pick();
    let angle = 0;
    let speed = CRUISE_SPEED;
    let mode = 'fly'; // fly | rest | flee
    let modeUntil = performance.now() + rand(6000, 11000);
    let phase = rand(0, 6);
    let last = performance.now();
    let raf = null;
    let pointer = null;
    let dead = false; // caught by the ant: gone for a while
    let deadUntil = 0;
    let evadeAt = 0; // when to pick a new escape direction from the ant

    const setRest = (on) => setResting(on);

    const frame = (now) => {
      raf = requestAnimationFrame(frame);
      const dt = Math.min(0.05, (now - last) / 1000);
      last = now;
      if (document.hidden) return;
      phase += dt;

      // Eaten: stay away for a while, then a new butterfly flies in from the left
      if (dead) {
        pets.butterfly = { x: pos.x, y: pos.y, resting: false, mode, dead: true };
        if (now > deadUntil) {
          dead = false;
          setEaten(false);
          pos.x = -SIZE;
          pos.y = rand(120, H() - 120);
          mode = 'fly';
          target = pick();
          speed = CRUISE_SPEED;
          modeUntil = now + rand(6000, 11000);
        }
        return;
      }

      // The ant is hunting it: wake up and run away from the ant
      const ant = pets.ant;
      if (ant && ant.hunting) {
        const d = Math.hypot(ant.x - pos.x, ant.y - pos.y);
        // a resting butterfly only notices the ant when it is already close
        if (d < 280 && !(mode === 'rest' && d > 120)) {
          if (mode === 'rest') {
            setRest(false);
            playFlutter();
          }
          if (now > evadeAt) {
            evadeAt = now + 260;
            const away = Math.atan2(pos.y - ant.y, pos.x - ant.x) + rand(-0.9, 0.9);
            target = {
              x: Math.max(30, Math.min(W() - 30, pos.x + Math.cos(away) * 220)),
              y: Math.max(80, Math.min(H() - 40, pos.y + Math.sin(away) * 220)),
            };
          }
          mode = 'flee';
          speed = 215;
          modeUntil = now + 500;
        }
      }

      // Mouse nearby -> flee
      if (pointer && mode !== 'flee') {
        const d = Math.hypot(pointer.x - pos.x, pointer.y - pos.y);
        if (d < FLEE_RADIUS) {
          touchButterfly();
          mode = 'flee';
          setRest(false);
          const away = Math.atan2(pos.y - pointer.y, pos.x - pointer.x);
          target = {
            x: Math.max(30, Math.min(W() - 30, pos.x + Math.cos(away) * 260)),
            y: Math.max(80, Math.min(H() - 40, pos.y + Math.sin(away) * 260)),
          };
          speed = FLEE_SPEED;
          modeUntil = now + 1200;
        }
      }

      if (mode === 'rest') {
        if (now > modeUntil) {
          mode = 'fly';
          setRest(false);
          target = pick();
          speed = CRUISE_SPEED;
          modeUntil = now + rand(6000, 11000);
        }
      } else {
        if (mode === 'flee' && now > modeUntil) {
          mode = 'fly';
          speed = CRUISE_SPEED;
          target = pick();
          modeUntil = now + rand(6000, 11000);
        }

        // Steer toward the target with a gentle wobble
        const dx = target.x - pos.x;
        const dy = target.y - pos.y;
        const dist = Math.hypot(dx, dy);
        if (dist < 14) {
          if (mode === 'fly' && now > modeUntil) {
            mode = 'rest';
            setRest(true);
            modeUntil = now + rand(3000, 5500);
          } else {
            target = pick();
          }
        } else {
          const wobble = Math.sin(phase * 2.2) * 0.9;
          const heading = Math.atan2(dy, dx) + wobble * 0.35;
          pos.x += Math.cos(heading) * speed * dt;
          pos.y += Math.sin(heading) * speed * dt + Math.sin(phase * 5) * 18 * dt;
          angle = Math.max(-28, Math.min(28, Math.cos(heading) * 22));
        }
      }

      el.style.transform = `translate3d(${pos.x - SIZE / 2}px, ${pos.y - SIZE / 2}px, 0) rotate(${angle}deg)`;
      el.dataset.mode = mode;
      el.__pos = pos;
      pets.butterfly = { x: pos.x, y: pos.y, resting: mode === 'rest', mode, dead: false };
    };

    // Tapping the butterfly: sparkle, a chime, and it flies off
    const burst = (x, y) => {
      const items = Array.from({ length: 7 }, (_, i) => ({
        id: `${Date.now()}-${i}`,
        x,
        y,
        dx: Math.cos((i / 7) * Math.PI * 2) * rand(26, 48),
        dy: Math.sin((i / 7) * Math.PI * 2) * rand(26, 48),
      }));
      setSparks(items);
      setTimeout(() => setSparks([]), 900);
    };

    const onTap = (e) => {
      e.stopPropagation();
      touchButterfly();
      playTune([7, 9, 11], 90, 0.08);
      playFlutter();
      burst(pos.x, pos.y);
      mode = 'flee';
      setRest(false);
      target = { x: rand(40, W() - 40), y: rand(90, H() - 60) };
      speed = FLEE_SPEED;
      modeUntil = performance.now() + 1500;
    };

    const onMove = (e) => {
      if (e.pointerType === 'touch') return;
      pointer = { x: e.clientX, y: e.clientY };
    };

    // The ant bit it: it falls, and a new butterfly comes back after a long while
    const onCaught = () => {
      if (dead) return;
      dead = true;
      deadUntil = performance.now() + rand(35000, 60000);
      setRest(false);
      setEaten(true);
      burst(pos.x, pos.y);
    };

    // The ant missed: a quick getaway
    const onEscape = () => {
      if (dead) return;
      mode = 'flee';
      setRest(false);
      target = pick();
      speed = 320;
      modeUntil = performance.now() + 1600;
      playFlutter();
    };

    petBus.addEventListener('butterfly-caught', onCaught);
    petBus.addEventListener('butterfly-escape', onEscape);
    el.addEventListener('click', onTap);
    window.addEventListener('pointermove', onMove, { passive: true });
    raf = requestAnimationFrame(frame);

    return () => {
      cancelAnimationFrame(raf);
      pets.butterfly = null;
      petBus.removeEventListener('butterfly-caught', onCaught);
      petBus.removeEventListener('butterfly-escape', onEscape);
      el.removeEventListener('click', onTap);
      window.removeEventListener('pointermove', onMove);
    };
  }, [hidden]);

  if (hidden) return null;

  return (
    <>
      <div
        ref={bodyRef}
        className={`pm-butterfly ${resting ? 'is-resting' : ''} ${eaten ? 'is-eaten' : ''}`}
        data-no-sound
        role="img"
        aria-label="A butterfly"
        style={{ width: SIZE, height: SIZE, transform: `translate3d(${-SIZE}px, 120px, 0)` }}
      >
        <svg viewBox="0 0 64 56" width={SIZE} height={SIZE} aria-hidden="true">
          <defs>
            <linearGradient id="pmBfWingA" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stopColor="#fb923c" />
              <stop offset="1" stopColor="#f43f5e" />
            </linearGradient>
            <linearGradient id="pmBfWingB" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stopColor="#fbbf24" />
              <stop offset="1" stopColor="#fb923c" />
            </linearGradient>
          </defs>
          <g className="pm-bf-wing pm-bf-left">
            <path d="M32 26 C14 2 -2 8 4 26 C8 36 22 34 32 28 Z" fill="url(#pmBfWingA)" />
            <path d="M32 29 C16 30 6 40 12 50 C20 56 30 44 32 31 Z" fill="url(#pmBfWingB)" />
            <circle cx="14" cy="18" r="3.4" fill="#fff" opacity="0.85" />
          </g>
          <g className="pm-bf-wing pm-bf-right">
            <path d="M32 26 C50 2 66 8 60 26 C56 36 42 34 32 28 Z" fill="url(#pmBfWingA)" />
            <path d="M32 29 C48 30 58 40 52 50 C44 56 34 44 32 31 Z" fill="url(#pmBfWingB)" />
            <circle cx="50" cy="18" r="3.4" fill="#fff" opacity="0.85" />
          </g>
          <rect x="30.2" y="16" width="3.6" height="28" rx="1.8" fill="#27272a" />
          <path d="M31 17 C28 9 24 6 21 6 M33 17 C36 9 40 6 43 6" stroke="#27272a" strokeWidth="1.4" fill="none" strokeLinecap="round" />
        </svg>
      </div>

      {sparks.map((s) => (
        <span
          key={s.id}
          className="pm-bf-spark"
          style={{ left: s.x, top: s.y, '--dx': `${s.dx}px`, '--dy': `${s.dy}px` }}
          aria-hidden="true"
        >
          ✦
        </span>
      ))}
    </>
  );
}
