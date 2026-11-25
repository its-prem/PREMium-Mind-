import React, { useEffect, useMemo, useRef, useState } from 'react';
import { formatBytes } from '../lib/cdn';
import { FilePick, FileCard, NameField, SizeCompare, baseName, safeName } from './shared';
import ResultActions from './ResultActions';

/* ======================= Image resize (with crop area) ======================= */

const FORMATS = [
  ['image/jpeg', 'JPG', 'jpg'],
  ['image/png', 'PNG', 'png'],
  ['image/webp', 'WebP', 'webp'],
];

const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));

export default function ImageResizeTool() {
  const [file, setFile] = useState(null);
  const [outName, setOutName] = useState('');
  const [src, setSrc] = useState('');
  const [natural, setNatural] = useState(null);
  const [w, setW] = useState('');
  const [h, setH] = useState('');
  const [lock, setLock] = useState(true);
  const [zoom, setZoom] = useState(100); // size of the selected area, in % of the largest possible
  const [pos, setPos] = useState({ x: 0, y: 0 }); // top-left of the selected area, in source pixels
  const [fmt, setFmt] = useState('image/jpeg');
  const [quality, setQuality] = useState(85);
  const [maxKb, setMaxKb] = useState('');
  const [result, setResult] = useState(null);
  const [msg, setMsg] = useState(null);
  const imgRef = useRef(null);
  const wrapRef = useRef(null);
  const drag = useRef(null);

  useEffect(() => () => src && URL.revokeObjectURL(src), [src]);

  const pick = (list) => {
    const f = list[0];
    if (!/^image\//.test(f.type)) {
      setMsg({ err: true, text: 'Please choose an image (JPG, PNG or WebP).' });
      return;
    }
    const url = URL.createObjectURL(f);
    const img = new Image();
    img.onload = () => {
      setNatural({ w: img.naturalWidth, h: img.naturalHeight });
      setW(String(img.naturalWidth));
      setH(String(img.naturalHeight));
      setZoom(100);
      setPos({ x: 0, y: 0 });
    };
    img.src = url;
    imgRef.current = img;
    setFile(f);
    setOutName(baseName(f.name) + '-resized');
    setSrc(url);
    setResult(null);
    setMsg(null);
  };

  const outW = Math.round(Number(w)) || 0;
  const outH = Math.round(Number(h)) || 0;

  // The selected area keeps the output's shape (w : h) and can be slid anywhere on the photo.
  const box = useMemo(() => {
    if (!natural || !(outW > 0 && outH > 0)) return null;
    const r = outW / outH;
    let mw;
    let mh;
    if (natural.w / natural.h > r) {
      mh = natural.h;
      mw = mh * r;
    } else {
      mw = natural.w;
      mh = mw / r;
    }
    const bw = mw * (zoom / 100);
    const bh = mh * (zoom / 100);
    return {
      w: bw,
      h: bh,
      x: clamp(pos.x, 0, natural.w - bw),
      y: clamp(pos.y, 0, natural.h - bh),
    };
  }, [natural, outW, outH, zoom, pos]);

  useEffect(() => setResult(null), [w, h, zoom, pos, fmt, quality, maxKb]);

  const changeW = (v) => {
    setW(v);
    if (lock && natural && Number(v) > 0) setH(String(Math.round((Number(v) * natural.h) / natural.w)));
  };
  const changeH = (v) => {
    setH(v);
    if (lock && natural && Number(v) > 0) setW(String(Math.round((Number(v) * natural.w) / natural.h)));
  };

  const onDown = (e) => {
    if (!box) return;
    e.preventDefault();
    e.currentTarget.setPointerCapture?.(e.pointerId);
    drag.current = { sx: e.clientX, sy: e.clientY, bx: box.x, by: box.y };
  };
  const onMove = (e) => {
    if (!drag.current || !box || !wrapRef.current) return;
    const rect = wrapRef.current.getBoundingClientRect();
    const k = natural.w / rect.width;
    setPos({
      x: clamp(drag.current.bx + (e.clientX - drag.current.sx) * k, 0, natural.w - box.w),
      y: clamp(drag.current.by + (e.clientY - drag.current.sy) * k, 0, natural.h - box.h),
    });
  };
  const onUp = () => {
    drag.current = null;
  };

  const keptPct = box ? Math.round(((box.w * box.h) / (natural.w * natural.h)) * 100) : 100;
  const cutX = box ? Math.round(natural.w - box.w) : 0;
  const cutY = box ? Math.round(natural.h - box.h) : 0;

  const encode = (canvas, type, q) => new Promise((res) => canvas.toBlob(res, type, q));

  const run = async () => {
    if (!box || outW < 1 || outH < 1 || outW > 8000 || outH > 8000) {
      setMsg({ err: true, text: 'Width and height must be between 1 and 8000.' });
      return;
    }
    setMsg(null);
    const canvas = document.createElement('canvas');
    canvas.width = outW;
    canvas.height = outH;
    const ctx = canvas.getContext('2d');
    if (fmt === 'image/jpeg') {
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, outW, outH);
    }
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(imgRef.current, box.x, box.y, box.w, box.h, 0, 0, outW, outH);

    const target = Number(maxKb) * 1024;
    let blob;
    let reached = true;
    if (fmt !== 'image/png' && target > 0) {
      let lo = 0.05;
      let hi = 0.95;
      blob = await encode(canvas, fmt, lo);
      if (blob && blob.size > target) {
        reached = false; // even the lowest quality is too big
      } else {
        for (let i = 0; i < 7; i += 1) {
          const mid = (lo + hi) / 2;
          const b = await encode(canvas, fmt, mid);
          if (b && b.size <= target) {
            blob = b;
            lo = mid;
          } else {
            hi = mid;
          }
        }
      }
    } else {
      blob = await encode(canvas, fmt, quality / 100);
      if (fmt === 'image/png' && target > 0) reached = blob.size <= target;
    }
    if (!blob) {
      setMsg({ err: true, text: 'This browser cannot save that format. Try JPG.' });
      return;
    }
    setResult({ blob, size: blob.size, reached, target: target > 0 ? target : null });
  };

  const ext = FORMATS.find((x) => x[0] === fmt)[2];
  const outFile = () => ({
    blob: result.blob,
    name: safeName(outName, ext, baseName(file.name) + '-resized'),
  });

  return (
    <div className="tl-panel">
      <h2>Image Resize</h2>
      <p className="tl-help">
        Choose the size you want, then slide the selected area over the photo to pick the part you keep. The
        dark part is what gets cut.
      </p>

      {!file ? (
        <FilePick accept="image/*" onFiles={pick} label="Tap to choose an image" hint="JPG, PNG or WebP" />
      ) : (
        <>
          <FileCard
            icon="image-outline"
            name={file.name}
            meta={`${natural ? `${natural.w} x ${natural.h}px · ` : ''}${formatBytes(file.size)}`}
            accept="image/*"
            onFiles={pick}
            addLabel="Add new"
          />

          {src && (
            <div className="tl-crop-wrap">
              <div className="tl-crop" ref={wrapRef}>
                <img src={src} alt="Selected" draggable="false" />
                {box && (
                  <div
                    className="tl-crop-box"
                    style={{
                      left: `${(box.x / natural.w) * 100}%`,
                      top: `${(box.y / natural.h) * 100}%`,
                      width: `${(box.w / natural.w) * 100}%`,
                      height: `${(box.h / natural.h) * 100}%`,
                    }}
                    onPointerDown={onDown}
                    onPointerMove={onMove}
                    onPointerUp={onUp}
                    onPointerCancel={onUp}
                  >
                    <span className="tl-crop-tag">Keep</span>
                  </div>
                )}
              </div>
              <p className="tl-crop-stat">
                <span className="is-keep">Kept {keptPct}%</span>
                <span className="is-cut">Cut {100 - keptPct}%</span>
                {(cutX > 0 || cutY > 0) && (
                  <small>
                    {cutX > 0 ? `${cutX}px from width` : ''}
                    {cutX > 0 && cutY > 0 ? ', ' : ''}
                    {cutY > 0 ? `${cutY}px from height` : ''}
                  </small>
                )}
              </p>
              <div className="tl-field" style={{ marginBottom: 10 }}>
                <label>Selected area size ({zoom}%)</label>
                <input type="range" min="20" max="100" value={zoom} onChange={(e) => setZoom(Number(e.target.value))} />
              </div>
            </div>
          )}

          <div className="tl-row">
            <div className="tl-field">
              <label>Width (px)</label>
              <input className="tl-input" inputMode="numeric" value={w} onChange={(e) => changeW(e.target.value)} />
            </div>
            <div className="tl-field">
              <label>Height (px)</label>
              <input className="tl-input" inputMode="numeric" value={h} onChange={(e) => changeH(e.target.value)} />
            </div>
          </div>
          <div className="tl-row">
            <label style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: '0.82rem', fontWeight: 700 }}>
              <input type="checkbox" checked={lock} onChange={(e) => setLock(e.target.checked)} /> Keep original proportion (no cut)
            </label>
          </div>
          <div className="tl-row">
            <div className="tl-field">
              <label>Format</label>
              <select className="tl-select" value={fmt} onChange={(e) => setFmt(e.target.value)}>
                {FORMATS.map(([v, l]) => (
                  <option key={v} value={v}>{l}</option>
                ))}
              </select>
            </div>
            {fmt !== 'image/png' && !maxKb && (
              <div className="tl-field">
                <label>Quality {quality}%</label>
                <input type="range" min="30" max="100" value={quality} onChange={(e) => setQuality(Number(e.target.value))} />
              </div>
            )}
          </div>
          {fmt !== 'image/png' && (
            <div className="tl-row">
              <div className="tl-field">
                <label>Max file size in KB (optional)</label>
                <input
                  className="tl-input"
                  inputMode="numeric"
                  placeholder={`e.g. 50 (original ${Math.round(file.size / 1024)} KB)`}
                  value={maxKb}
                  onChange={(e) => setMaxKb(e.target.value.replace(/[^0-9]/g, ''))}
                />
              </div>
            </div>
          )}

          <SizeCompare original={file.size} target={Number(maxKb) * 1024 || null} result={result ? result.size : null} />

          <button type="button" className="tl-btn" disabled={!natural} onClick={run}>
            <ion-icon name="resize-outline"></ion-icon> Resize
          </button>

          {result && (
            <>
              <p className={`tl-msg ${result.reached ? 'is-ok' : 'is-err'}`}>
                {result.reached
                  ? `Done: ${outW} x ${outH}px, ${formatBytes(file.size)} → ${formatBytes(result.size)}.`
                  : `Could not get under ${formatBytes(result.target)}. Smallest possible is ${formatBytes(result.size)}. Try smaller width/height.`}
              </p>
              <NameField value={outName} onChange={setOutName} ext={ext} placeholder="new file name" />
              <ResultActions files={[outFile()]} onMessage={(t) => setMsg({ ok: true, text: t })} />
            </>
          )}
        </>
      )}
      {msg && <p className={`tl-msg ${msg.ok ? 'is-ok' : ''} ${msg.err ? 'is-err' : ''}`}>{msg.text}</p>}
    </div>
  );
}
