import React, { useEffect, useState } from 'react';
import MaintenanceArt from './MaintenanceArt';
import { isSoundOn, setSoundOn } from '../lib/uiSounds';
import './Maintenance.css';

/** "1h 20m" / "12m 05s" until `returnAt`, or null when no time is set. */
function useCountdown(returnAt) {
  const target = returnAt ? new Date(returnAt).getTime() : NaN;
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (Number.isNaN(target)) return undefined;
    const id = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(id);
  }, [target]);

  if (Number.isNaN(target)) return null;
  const left = Math.floor((target - now) / 1000);
  if (left <= 0) return 'done';

  const h = Math.floor(left / 3600);
  const m = Math.floor((left % 3600) / 60);
  const s = left % 60;
  if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m`;
  return `${m}m ${String(s).padStart(2, '0')}s`;
}

/** Full-screen page shown on every route while maintenance mode is on. */
export default function MaintenancePage({ config, onRetry, checking }) {
  const countdown = useCountdown(config.returnAt);
  const [soundOn, setSoundOnState] = useState(() => isSoundOn());

  const toggleSound = () => {
    const next = !soundOn;
    setSoundOn(next);
    setSoundOnState(next);
  };

  useEffect(() => {
    const previous = document.title;
    document.title = 'Maintenance | PREMium Mind';
    return () => {
      document.title = previous;
    };
  }, []);

  const phone = String(config.whatsapp || '').replace(/\D/g, '');

  return (
    <main className="mt-page">
      <div className="mt-card">
        <div className="mt-top">
          <img className="mt-logo" src="/small-logo.png" alt="PREMium Mind" />
          <span className="mt-badge">
            <span className="mt-dot" /> Under maintenance
          </span>
          <button
            type="button"
            className="mt-sound"
            onClick={toggleSound}
            aria-pressed={soundOn}
            aria-label={soundOn ? 'Turn cat sounds off' : 'Turn cat sounds on'}
            title={soundOn ? 'Sounds on' : 'Sounds off'}
          >
            <ion-icon name={soundOn ? 'volume-high-outline' : 'volume-mute-outline'}></ion-icon>
          </button>
        </div>

        <MaintenanceArt />
        <p className="mt-hint">Tap the cat. Press and hold to make it purr.</p>

        <h1>{config.title || "We'll be back soon"}</h1>
        <p className="mt-message">
          {config.message ||
            "We're updating the website to make it better. Your notes and courses are safe. Please check back in a little while."}
        </p>

        {countdown && (
          <p className="mt-eta">
            {countdown === 'done' ? (
              'Almost there...'
            ) : (
              <>
                Back in about <strong>{countdown}</strong>
              </>
            )}
          </p>
        )}

        <div className="mt-actions">
          <button type="button" className="mt-btn" onClick={onRetry} disabled={checking}>
            <ion-icon name="refresh-outline"></ion-icon>
            {checking ? 'Checking...' : 'Check again'}
          </button>
          {phone && (
            <a
              className="mt-btn mt-btn-wa"
              href={`https://wa.me/${phone}`}
              target="_blank"
              rel="noreferrer"
            >
              <ion-icon name="logo-whatsapp"></ion-icon> WhatsApp Support
            </a>
          )}
        </div>

        <p className="mt-foot">&copy; {new Date().getFullYear()} PREMium Mind</p>
      </div>
    </main>
  );
}
