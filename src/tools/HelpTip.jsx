import React, { useEffect, useState } from 'react';

const AUTO_CLOSE_MS = 10000;

/**
 * A "How to use" button. Tap it to read the help; it closes by itself after 10 seconds
 * or when tapped again.
 */
export default function HelpTip({ title = 'How to use', children }) {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!open) return undefined;
    const t = setTimeout(() => setOpen(false), AUTO_CLOSE_MS);
    return () => clearTimeout(t);
  }, [open]);

  return (
    <div className={`tl-helptip ${open ? 'is-open' : ''}`}>
      <button type="button" className="tl-helptip-btn" aria-expanded={open} onClick={() => setOpen(!open)}>
        <ion-icon name="information-circle-outline"></ion-icon>
        <span>{title}</span>
        <ion-icon name={open ? 'chevron-up-outline' : 'chevron-down-outline'}></ion-icon>
      </button>
      {open && (
        <div className="tl-helptip-body">
          <div className="tl-helptip-text">{children}</div>
        </div>
      )}
    </div>
  );
}
