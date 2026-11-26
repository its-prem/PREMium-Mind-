/**
 * "Slide to pay" button: drag the round handle to the end to confirm the payment.
 * Also works from the keyboard (focus the handle, press Enter, Space or the right arrow).
 *
 *   <SlideToPay label="Slide to pay ₹49" disabled={false} busy={false} onComplete={startPay} />
 *
 * disabled = the button is greyed (for example the terms are not accepted yet); busy = the payment window is opening.
 * While it waits, the handle nudges to the right, a ring pulses around it and the arrows after the label flow forward,
 * so it is clear that it must be slid. (The look is in App.css, section "slide to pay".)
 */
import React, { useEffect, useRef, useState } from 'react';

const HANDLE = 52; // round handle, px
const PAD = 4; // space around the handle, px

export default function SlideToPay({ label, disabled = false, busy = false, onComplete }) {
  const trackRef = useRef(null);
  const startX = useRef(0);
  const [x, setX] = useState(0);
  const [drag, setDrag] = useState(false);
  // The latest position / drag flag are also kept in refs: a quick swipe can send the pointer-up before React has drawn the
  // last move, and the handlers must not read an old value then.
  const xRef = useRef(0);
  const dragRef = useRef(false);
  const setPos = (v) => {
    xRef.current = v;
    setX(v);
  };
  const setDragging = (v) => {
    dragRef.current = v;
    setDrag(v);
  };

  const maxX = () => Math.max(0, (trackRef.current?.clientWidth || 0) - HANDLE - PAD * 2);

  // the handle goes back to the start when the payment window was closed (busy ends) or the button gets disabled
  useEffect(() => {
    if (!busy) setPos(0);
  }, [busy]);
  useEffect(() => {
    if (disabled) setPos(0);
  }, [disabled]);

  const finish = () => {
    setPos(maxX());
    onComplete?.();
  };

  const down = (e) => {
    if (disabled || busy) return;
    e.currentTarget.setPointerCapture?.(e.pointerId);
    startX.current = e.clientX - xRef.current;
    setDragging(true);
  };
  const move = (e) => {
    if (!dragRef.current) return;
    setPos(Math.min(maxX(), Math.max(0, e.clientX - startX.current)));
  };
  const up = () => {
    if (!dragRef.current) return;
    setDragging(false);
    if (xRef.current >= maxX() * 0.88) finish();
    else setPos(0);
  };

  const key = (e) => {
    if (disabled || busy) return;
    if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowRight') {
      e.preventDefault();
      finish();
    }
  };

  const progress = maxX() ? x / maxX() : 0;
  const idle = !disabled && !busy && !drag && x === 0;

  return (
    <div className={`co-slide${disabled ? ' off' : ''}${busy ? ' busy' : ''}${idle ? ' idle' : ''}${drag ? ' drag' : ''}`} ref={trackRef}>
      <div className="co-slide-fill" style={{ width: x + HANDLE + PAD * 2, transition: drag ? 'none' : 'width 0.3s ease' }} />
      <span className="co-slide-label" style={{ opacity: busy ? 1 : Math.max(0, 1 - progress * 1.5) }}>
        <b>{busy ? 'Opening payment…' : label}</b>
        {!busy && !disabled && (
          <i className="co-slide-chev" aria-hidden="true">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 5l7 7-7 7" /></svg>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 5l7 7-7 7" /></svg>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 5l7 7-7 7" /></svg>
          </i>
        )}
      </span>
      <div
        className="co-slide-handle"
        role="slider"
        tabIndex={disabled ? -1 : 0}
        aria-label={label}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={Math.round(progress * 100)}
        aria-disabled={disabled}
        style={{ transform: `translateX(${x}px)`, transition: drag ? 'none' : 'transform 0.3s cubic-bezier(0.22, 1, 0.36, 1)' }}
        onPointerDown={down}
        onPointerMove={move}
        onPointerUp={up}
        onPointerCancel={up}
        onKeyDown={key}
      >
        {busy ? (
          <span className="co-slide-spin" />
        ) : (
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M5 12h13M13 6l6 6-6 6" />
          </svg>
        )}
      </div>
    </div>
  );
}
