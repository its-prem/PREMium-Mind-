import React from 'react';
import { formatBytes } from '../lib/cdn';

/* ======================= PDF helpers ======================= */

export const readBytes = (file) => file.arrayBuffer().then((b) => new Uint8Array(b));

export const baseName = (n) => n.replace(/\.[^.]+$/, '');

/** Make a safe file name: remove bad characters and any extension, then add ours. */
export const safeName = (raw, ext, fallback) => {
  const clean = baseName(String(raw || ''))
    .replace(/[\\/:*?"<>|]+/g, '')
    .trim();
  return `${clean || fallback}.${ext}`;
};

export function NameField({ value, onChange, ext, placeholder }) {
  return (
    <div className="tl-row">
      <div className="tl-field">
        <label>Save as (file name)</label>
        <div className="tl-name-box">
          <input
            className="tl-input"
            value={value}
            placeholder={placeholder}
            onChange={(e) => onChange(e.target.value)}
          />
          <span className="tl-ext">.{ext}</span>
        </div>
      </div>
    </div>
  );
}

export function FilePick({ accept, multiple, onFiles, label, hint }) {
  return (
    <label className="tl-drop">
      <ion-icon name="cloud-upload-outline"></ion-icon>
      <span>{label}</span>
      <small>{hint}</small>
      <input
        type="file"
        accept={accept}
        multiple={multiple}
        onChange={(e) => {
          const files = Array.from(e.target.files || []);
          if (files.length) onFiles(files);
          e.target.value = '';
        }}
      />
    </label>
  );
}

/* ======================= Shared: chosen-file card + size compare ======================= */

export function FileCard({ icon, name, meta, accept, multiple, onFiles, addLabel }) {
  return (
    <div className="tl-filecard">
      <span className="tl-filecard-icon">
        <ion-icon name={icon}></ion-icon>
      </span>
      <div className="tl-filecard-text">
        <b>{name}</b>
        <small>{meta}</small>
      </div>
      <label className="tl-btn is-ghost is-small">
        <ion-icon name="add-circle-outline"></ion-icon> {addLabel}
        <input
          type="file"
          accept={accept}
          multiple={multiple}
          hidden
          onChange={(e) => {
            const files = Array.from(e.target.files || []);
            if (files.length) onFiles(files);
            e.target.value = '';
          }}
        />
      </label>
    </div>
  );
}

export function SizeCompare({ original, result, target }) {
  const saved = result != null ? Math.round((1 - result / original) * 100) : null;
  return (
    <div className="tl-compare">
      <div>
        <small>Original</small>
        <b>{formatBytes(original)}</b>
      </div>
      <div>
        <small>Target</small>
        <b>{target ? formatBytes(target) : '--'}</b>
      </div>
      <div className={result != null ? 'is-new' : ''}>
        <small>{result != null ? 'New size' : 'New size'}</small>
        <b>{result != null ? formatBytes(result) : '--'}</b>
        {saved != null && <em>{saved >= 0 ? `${saved}% smaller` : `${-saved}% bigger`}</em>}
      </div>
    </div>
  );
}

/** Target size input: number + KB/MB, plus quick "% of original" chips. */
export function TargetSize({ original, kb, setKb }) {
  const chips = [75, 50, 25];
  const maxKb = Math.max(1, Math.floor(original / 1024));
  return (
    <div className="tl-target">
      <div className="tl-row">
        <div className="tl-field">
          <label>Set target size (KB)</label>
          <input
            className="tl-input"
            inputMode="numeric"
            value={kb}
            onChange={(e) => setKb(e.target.value.replace(/[^0-9]/g, ''))}
            placeholder="e.g. 200"
          />
        </div>
      </div>
      <input
        type="range"
        min="1"
        max={maxKb}
        value={Math.min(Number(kb) || 1, maxKb)}
        onChange={(e) => setKb(e.target.value)}
        aria-label="Target size slider"
        style={{ width: '100%' }}
      />
      <div className="tl-row" style={{ marginTop: 6 }}>
        {chips.map((p) => (
          <button
            key={p}
            type="button"
            className="tl-btn is-ghost is-small"
            onClick={() => setKb(String(Math.max(1, Math.round((maxKb * p) / 100))))}
          >
            {p}% of original
          </button>
        ))}
      </div>
    </div>
  );
}
