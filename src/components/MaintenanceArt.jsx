import React from 'react';
import MaintenanceCat from './MaintenanceCat';

/**
 * Illustration for the maintenance page: a browser window that is being
 * updated, gears, a cone, caution tape and an interactive tabby cat in a
 * hard hat (see MaintenanceCat.jsx). Pure SVG + CSS animation; styles live
 * in Maintenance.css.
 */
const SPARKLE = 'M0 -6 L1.7 -1.7 L6 0 L1.7 1.7 L0 6 L-1.7 1.7 L-6 0 L-1.7 -1.7 Z';

export default function MaintenanceArt() {
  return (
    <svg
      className="mt-art"
      viewBox="0 0 480 300"
      role="img"
      aria-label="A cat in a hard hat is updating the website. Tap the cat, or press and hold it."
    >
      <defs>
        <linearGradient id="mtWin" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#2f2f35" />
          <stop offset="1" stopColor="#1b1b1f" />
        </linearGradient>
        <linearGradient id="mtFill" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0" stopColor="#fde047" />
          <stop offset="1" stopColor="#f59e0b" />
        </linearGradient>
        <pattern
          id="mtTape"
          width="22"
          height="22"
          patternUnits="userSpaceOnUse"
          patternTransform="rotate(45)"
        >
          <rect width="11" height="22" fill="#facc15" />
          <rect x="11" width="11" height="22" fill="#18181b" />
        </pattern>
        <radialGradient id="mtSpot" cx="0.5" cy="0.5" r="0.5">
          <stop offset="0" stopColor="#ffffff" stopOpacity="0.16" />
          <stop offset="1" stopColor="#ffffff" stopOpacity="0" />
        </radialGradient>
      </defs>

      {/* soft spotlight behind the scene */}
      <ellipse cx="240" cy="150" rx="236" ry="136" fill="url(#mtSpot)" />

      {/* ---------- browser window being updated ---------- */}
      <g>
        <rect x="24" y="30" width="262" height="172" rx="14" fill="url(#mtWin)" stroke="rgba(255,255,255,0.25)" />
        <rect x="24" y="30" width="262" height="30" rx="14" fill="#34343b" />
        <rect x="24" y="46" width="262" height="14" fill="#34343b" />
        <circle cx="43" cy="45" r="4.2" fill="#ef4444" />
        <circle cx="58" cy="45" r="4.2" fill="#facc15" />
        <circle cx="73" cy="45" r="4.2" fill="#22c55e" />
        <rect x="92" y="37" width="180" height="15" rx="7.5" fill="#1b1b1f" />
        <text x="104" y="48" fontSize="8.5" fill="#a1a1aa" fontWeight="700">
          PREMium Mind
        </text>

        <rect x="44" y="76" width="118" height="11" rx="5.5" fill="rgba(255,255,255,0.2)" />
        <rect x="44" y="97" width="205" height="7" rx="3.5" fill="rgba(255,255,255,0.1)" />
        <rect x="44" y="111" width="168" height="7" rx="3.5" fill="rgba(255,255,255,0.1)" />
        <rect x="44" y="125" width="190" height="7" rx="3.5" fill="rgba(255,255,255,0.1)" />

        <g className="mt-art-spin" style={{ transformOrigin: '258px 82px' }}>
          <circle cx="258" cy="82" r="9" fill="none" stroke="rgba(255,255,255,0.15)" strokeWidth="3" />
          <path d="M258 73 A9 9 0 0 1 267 82" fill="none" stroke="#facc15" strokeWidth="3" strokeLinecap="round" />
        </g>

        <rect x="44" y="154" width="222" height="13" rx="6.5" fill="rgba(255,255,255,0.12)" />
        <rect
          className="mt-art-progress"
          x="44"
          y="154"
          width="222"
          height="13"
          rx="6.5"
          fill="url(#mtFill)"
        />
        <text x="44" y="187" fontSize="10.5" fill="#e4e4e7" fontWeight="800">
          Updating...
        </text>
        <text x="266" y="187" fontSize="10.5" fill="#a1a1aa" fontWeight="700" textAnchor="end">
          v2.0
        </text>
      </g>

      {/* ---------- gears ---------- */}
      <g className="mt-art-gear" style={{ transformOrigin: '332px 52px' }}>
        <circle cx="332" cy="52" r="24" fill="none" stroke="#e4e4e7" strokeWidth="8" strokeDasharray="7.5 7.6" />
        <circle cx="332" cy="52" r="20" fill="#d4d4d8" />
        <circle cx="332" cy="52" r="8" fill="#27272a" />
      </g>
      <g className="mt-art-gear mt-art-gear-rev" style={{ transformOrigin: '298px 92px' }}>
        <circle cx="298" cy="92" r="16" fill="none" stroke="#fbbf24" strokeWidth="7" strokeDasharray="6.3 5.9" />
        <circle cx="298" cy="92" r="12.8" fill="#fbbf24" />
        <circle cx="298" cy="92" r="4.5" fill="#27272a" />
      </g>

      {/* ---------- traffic cone ---------- */}
      <ellipse cx="62" cy="285" rx="30" ry="4" fill="rgba(0,0,0,0.35)" />
      <g>
        <rect x="42" y="278" width="40" height="7" rx="2" fill="#9a3412" />
        <polygon points="50,279 74,279 66,239 58,239" fill="#f97316" />
        <polygon points="55,260 69,260 67.5,253 56.5,253" fill="#ffffff" />
        <polygon points="52.5,272 71.5,272 70,265.5 54,265.5" fill="#ffffff" />
        <circle cx="62" cy="238" r="3.2" fill="#f97316" />
      </g>

      {/* ---------- the cat ---------- */}
      <MaintenanceCat />

      {/* ---------- sparkles ---------- */}
      <g transform="translate(300 176)">
        <path className="mt-art-spark" d={SPARKLE} fill="#fde68a" />
      </g>
      <g transform="translate(60 200) scale(.8)">
        <path className="mt-art-spark mt-art-spark-b" d={SPARKLE} fill="#ffffff" />
      </g>
      <g transform="translate(460 140) scale(.7)">
        <path className="mt-art-spark mt-art-spark-c" d={SPARKLE} fill="#fde68a" />
      </g>

      {/* ---------- caution tape ---------- */}
      <rect x="0" y="288" width="480" height="12" fill="url(#mtTape)" />
      <rect x="0" y="288" width="480" height="12" fill="none" stroke="rgba(0,0,0,0.4)" />
    </svg>
  );
}
