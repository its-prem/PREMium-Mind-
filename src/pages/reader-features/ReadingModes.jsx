import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { readJson, useDismiss, writeJson } from './shared';
import './ReadingModes.css';

const KEY = 'pm_reader_modes';

/**
 * Feature: wide pages, sepia and distraction-free (focus) reading.
 * It only sets data-* attributes on the reader root; the styles live in
 * ReadingModes.css. Remove by deleting this file and its line in index.jsx.
 */
export default function ReadingModes({ scrollRef, dock }) {
  const [modes, setModes] = useState(() => ({
    wide: false,
    sepia: false,
    ...readJson(KEY, {}),
  }));
  const [focus, setFocus] = useState(false);
  const [open, setOpen] = useState(false);
  const itemRef = useRef(null);
  const closePop = useCallback(() => setOpen(false), []);
  useDismiss(open, closePop, itemRef);

  useEffect(() => {
    const root = scrollRef.current?.closest('.rd-root');
    if (!root) return undefined;
    root.dataset.rdWide = modes.wide ? '1' : '0';
    root.dataset.rdSepia = modes.sepia ? '1' : '0';
    root.dataset.rdFocus = focus ? '1' : '0';
    return () => {
      delete root.dataset.rdWide;
      delete root.dataset.rdSepia;
      delete root.dataset.rdFocus;
    };
  }, [modes, focus, scrollRef]);

  const toggle = (name) => {
    setModes((prev) => {
      const next = { ...prev, [name]: !prev[name] };
      writeJson(KEY, next);
      return next;
    });
  };

  const button = dock
    ? createPortal(
        <div className="rdx-dock-item" ref={itemRef}>
          {open && (
            <div className="rdx-pop">
              <h4>Reading mode</h4>
              <div className="rdx-stack">
                <button
                  type="button"
                  className={`rdx-chip ${modes.wide ? 'is-on' : ''}`}
                  onClick={() => toggle('wide')}
                >
                  <ion-icon name="resize-outline"></ion-icon> Wide pages
                </button>
                <button
                  type="button"
                  className={`rdx-chip ${modes.sepia ? 'is-on' : ''}`}
                  onClick={() => toggle('sepia')}
                >
                  <ion-icon name="cafe-outline"></ion-icon> Sepia
                </button>
                <button
                  type="button"
                  className="rdx-chip"
                  onClick={() => {
                    setFocus(true);
                    setOpen(false);
                  }}
                >
                  <ion-icon name="expand-outline"></ion-icon> Focus mode
                </button>
              </div>
            </div>
          )}
          <button
            type="button"
            className={`rdx-fab ${open ? 'is-on' : ''}`}
            aria-label="Reading modes"
            aria-expanded={open}
            title="Reading modes"
            onClick={() => setOpen((o) => !o)}
          >
            <ion-icon name="color-wand-outline"></ion-icon>
          </button>
        </div>,
        dock
      )
    : null;

  return (
    <>
      {button}
      {focus && (
        <button
          type="button"
          className="rdx-exit-focus"
          onClick={() => setFocus(false)}
        >
          <ion-icon name="contract-outline"></ion-icon> Exit focus
        </button>
      )}
    </>
  );
}
