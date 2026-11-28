import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
  COLORS,
  ensureLayer,
  hitsStroke,
  removeAllLayers,
  renderLayer,
  toPagePoint,
} from './highlightLayer';
import { getPages, readJson, useDismiss, writeJson } from './shared';
import './Highlighter.css';

/**
 * Feature: highlight, underline and erase on the pages.
 * Marks are saved on this device (per user + PDF). The drawing code lives in
 * highlightLayer.js. Remove by deleting this file, highlightLayer.js and
 * Highlighter.css, and the line in index.jsx.
 */
export default function Highlighter({ scrollRef, pagesRef, docKey, ready, dock }) {
  const storageKey = `pm_marks:${docKey}`;
  const [tool, setTool] = useState(null); // null | hl | ul | erase
  const [color, setColor] = useState('yellow');
  const [open, setOpen] = useState(false);
  const [count, setCount] = useState(0);
  const itemRef = useRef(null);
  const closePop = useCallback(() => setOpen(false), []);
  useDismiss(open, closePop, itemRef);

  const marks = useRef({}); // { [pageIndex]: stroke[] }
  const history = useRef([]); // snapshots for undo
  const toolRef = useRef(tool);
  const colorRef = useRef(color);
  toolRef.current = tool;
  colorRef.current = color;

  const refreshCount = () =>
    setCount(Object.values(marks.current).reduce((n, list) => n + list.length, 0));

  const persist = useCallback(() => {
    writeJson(storageKey, marks.current);
    refreshCount();
  }, [storageKey]);

  const snapshot = () => {
    history.current.push(JSON.stringify(marks.current));
    if (history.current.length > 30) history.current.shift();
  };

  const redrawPage = useCallback((pageEl, index) => {
    renderLayer(ensureLayer(pageEl), marks.current[index] || []);
  }, []);

  const redrawAll = useCallback(() => {
    getPages(pagesRef.current).forEach((page, i) => redrawPage(page, i));
  }, [pagesRef, redrawPage]);

  // Load saved marks and keep every page's layer in sync.
  useEffect(() => {
    const pagesEl = pagesRef.current;
    if (!ready || !pagesEl) return undefined;

    marks.current = readJson(storageKey, {});
    history.current = [];
    refreshCount();

    const attach = () => {
      getPages(pagesEl).forEach((page, i) => {
        if (!page.querySelector(':scope > .rdx-hl-layer')) redrawPage(page, i);
      });
    };
    attach();
    const observer = new MutationObserver(attach);
    observer.observe(pagesEl, { childList: true });

    return () => {
      observer.disconnect();
      removeAllLayers(pagesEl);
    };
  }, [ready, pagesRef, storageKey, redrawPage]);

  // Tell the CSS which tool is active (it enables drawing on the layers).
  useEffect(() => {
    const root = scrollRef.current?.closest('.rd-root');
    if (!root) return undefined;
    if (tool) root.dataset.rdxPen = tool;
    else delete root.dataset.rdxPen;
    return () => {
      delete root.dataset.rdxPen;
    };
  }, [tool, scrollRef]);

  // Drawing / erasing with a finger, mouse or pen.
  useEffect(() => {
    const pagesEl = pagesRef.current;
    if (!ready || !pagesEl) return undefined;

    let active = null; // { layer, index, stroke }

    const pageIndexOf = (layer) => getPages(pagesEl).indexOf(layer.parentElement);

    const erase = (layer, index, point) => {
      const list = marks.current[index] || [];
      const kept = list.filter((s) => !hitsStroke(s, point));
      if (kept.length !== list.length) {
        marks.current[index] = kept;
        renderLayer(layer, kept);
        return true;
      }
      return false;
    };

    const onDown = (e) => {
      const layer = e.target.closest?.('.rdx-hl-layer');
      if (!layer || !toolRef.current || !e.isPrimary) return;
      e.preventDefault();
      const index = pageIndexOf(layer);
      if (index < 0) return;

      snapshot();
      layer.setPointerCapture?.(e.pointerId);
      const point = toPagePoint(layer, e);

      if (toolRef.current === 'erase') {
        active = { layer, index, erasing: true, changed: erase(layer, index, point) };
        return;
      }
      const stroke = {
        type: toolRef.current,
        color: COLORS[colorRef.current],
        points: [point],
      };
      marks.current[index] = [...(marks.current[index] || []), stroke];
      active = { layer, index, stroke };
      renderLayer(layer, marks.current[index]);
    };

    const onMove = (e) => {
      if (!active) return;
      const point = toPagePoint(active.layer, e);
      if (active.erasing) {
        if (erase(active.layer, active.index, point)) active.changed = true;
        return;
      }
      active.stroke.points.push(point);
      renderLayer(active.layer, marks.current[active.index]);
    };

    const finish = () => {
      if (!active) return;
      const { erasing, changed } = active;
      active = null;
      if (erasing && !changed) history.current.pop(); // nothing was erased
      else persist();
    };

    // A second finger means the user is pinching: drop the stroke.
    const onSecondFinger = (e) => {
      if (e.isPrimary || !active) return;
      if (!active.erasing) {
        const list = marks.current[active.index];
        list.pop();
        renderLayer(active.layer, list);
        history.current.pop();
      }
      active = null;
    };

    pagesEl.addEventListener('pointerdown', onDown);
    pagesEl.addEventListener('pointerdown', onSecondFinger);
    pagesEl.addEventListener('pointermove', onMove);
    pagesEl.addEventListener('pointerup', finish);
    pagesEl.addEventListener('pointercancel', finish);
    return () => {
      pagesEl.removeEventListener('pointerdown', onDown);
      pagesEl.removeEventListener('pointerdown', onSecondFinger);
      pagesEl.removeEventListener('pointermove', onMove);
      pagesEl.removeEventListener('pointerup', finish);
      pagesEl.removeEventListener('pointercancel', finish);
    };
  }, [ready, pagesRef, persist]);

  const undo = () => {
    const last = history.current.pop();
    if (!last) return;
    marks.current = JSON.parse(last);
    redrawAll();
    persist();
  };

  const clearAll = () => {
    if (!count) return;
    // eslint-disable-next-line no-alert
    if (!window.confirm('Remove all highlights of this PDF?')) return;
    snapshot();
    marks.current = {};
    redrawAll();
    persist();
  };

  const pick = (next) => setTool((cur) => (cur === next ? null : next));

  if (!dock) return null;

  return createPortal(
    <div className="rdx-dock-item" ref={itemRef}>
      {open && (
        <div className="rdx-pop rdx-hl-pop">
          <h4>Highlighter</h4>
          <div className="rdx-stack">
            <div className="rdx-row">
              <button
                type="button"
                className={`rdx-chip ${tool === 'hl' ? 'is-on' : ''}`}
                onClick={() => pick('hl')}
              >
                <ion-icon name="brush-outline"></ion-icon> Highlight
              </button>
              <button
                type="button"
                className={`rdx-chip ${tool === 'ul' ? 'is-on' : ''}`}
                onClick={() => pick('ul')}
              >
                <ion-icon name="remove-outline"></ion-icon> Underline
              </button>
            </div>

            <div className="rdx-swatches" role="radiogroup" aria-label="Color">
              {Object.entries(COLORS).map(([name, hex]) => (
                <button
                  key={name}
                  type="button"
                  role="radio"
                  aria-checked={color === name}
                  aria-label={name}
                  className={`rdx-swatch ${color === name ? 'is-on' : ''}`}
                  style={{ background: hex }}
                  onClick={() => {
                    setColor(name);
                    if (!tool || tool === 'erase') setTool('hl');
                  }}
                />
              ))}
            </div>

            <div className="rdx-row">
              <button
                type="button"
                className={`rdx-chip ${tool === 'erase' ? 'is-on' : ''}`}
                onClick={() => pick('erase')}
              >
                <ion-icon name="trash-bin-outline"></ion-icon> Eraser
              </button>
              <button type="button" className="rdx-chip" onClick={undo}>
                <ion-icon name="arrow-undo-outline"></ion-icon> Undo
              </button>
            </div>

            <button
              type="button"
              className="rdx-chip"
              disabled={!count}
              onClick={clearAll}
            >
              Clear all ({count})
            </button>
            {tool && (
              <button type="button" className="rdx-chip is-on" onClick={() => setTool(null)}>
                Done - scroll karo
              </button>
            )}
          </div>
        </div>
      )}
      <button
        type="button"
        className={`rdx-fab ${tool ? 'is-on' : ''}`}
        aria-label="Highlighter"
        aria-expanded={open}
        title="Highlighter"
        onClick={() => setOpen((o) => !o)}
      >
        <ion-icon name="create-outline"></ion-icon>
      </button>
    </div>,
    dock
  );
}
