import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { TOOLS } from '../tools';
import { goBackOrHome } from '../lib/goBack';
import './Tools.css';

/**
 * The Free Tools page. It only shows the tool picker and the chosen tool;
 * every tool has its own file in src/tools/ (see src/tools/index.js).
 */
export default function Tools() {
  const navigate = useNavigate();
  const [active, setActive] = useState(() => {
    const id = new URLSearchParams(window.location.search).get('tool');
    // Nothing is open by default; a link like /tools?tool=split can still open one directly.
    return TOOLS.some((t) => t.id === id) ? id : null;
  });

  useEffect(() => {
    document.title = 'Free Tools | PREMium Mind';
    window.scrollTo(0, 0);
  }, []);

  // Tools you have opened stay alive (just hidden), so switching tools never loses what you typed or uploaded.
  const [opened, setOpened] = useState(() => (active ? [active] : []));
  const choose = (id) => {
    if (id === active) {
      setActive(null); // tapping the open tool again closes it
      return;
    }
    setActive(id);
    setOpened((cur) => (cur.includes(id) ? cur : [...cur, id]));
    justPicked.current = true;
  };

  // After a card is tapped, slide down to the tool that just opened
  const justPicked = useRef(false);
  useEffect(() => {
    if (!justPicked.current || !active) return;
    justPicked.current = false;
    const el = document.getElementById(`tool-${active}`);
    if (el) requestAnimationFrame(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }));
  }, [active]);

  return (
    <div className="tl-page">
      <header className="tl-top">
        <button type="button" className="tl-back" aria-label="Back to store" onClick={() => goBackOrHome(navigate)}>
          <ion-icon name="arrow-back-outline"></ion-icon>
        </button>
        <div>
          <h1>Free Tools</h1>
          <p>Handy tools for students, 100% free</p>
        </div>
        <img className="tl-logo" src="/small-logo.png" alt="" />
      </header>

      <main className="tl-wrap">
        <div className="tl-grid">
          {TOOLS.map((t) => (
            <button
              key={t.id}
              type="button"
              className={`tl-pick ${active === t.id ? 'is-on' : ''}`}
              style={{ '--tint': t.tint }}
              onClick={() => choose(t.id)}
            >
              <span className="tl-pick-icon"><ion-icon name={t.icon}></ion-icon></span>
              <b>{t.title}</b>
              <span className="tl-sub">{t.sub}</span>
              <span className="tl-chip">FREE</span>
            </button>
          ))}
        </div>

        {TOOLS.filter((t) => opened.includes(t.id)).map((t) => (
          <div key={t.id} id={`tool-${t.id}`} className="tl-toolwrap" hidden={t.id !== active}>
            <t.Component />
          </div>
        ))}
      </main>
    </div>
  );
}
