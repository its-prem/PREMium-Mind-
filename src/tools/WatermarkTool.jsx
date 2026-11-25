import React, { useEffect, useRef, useState } from 'react';
import { loadPdfJs } from '../lib/pdfViewer';
import { loadPdfLib, formatBytes, downloadBlob } from '../lib/cdn';
import { FilePick, FileCard, baseName, readBytes, safeName } from './shared';
import PdfViewer from './PdfViewer';
import { withSuffix } from './naming';
import { keepFile, loadKeptFile, dropKeptFile, loadKept, saveKept, dropKept } from '../lib/keep';

const COLORS = ['#dc2626', '#09090b', '#2563eb', '#16a34a', '#7c3aed', '#71717a'];
const LATIN = /^[\x20-\x7E\xA0-\xFF]*$/; // the built-in PDF fonts only know these letters

// Fonts the PDF can use without downloading anything: css family + the pdf-lib names (normal, bold, italic, bold italic)
const FONTS = {
  sans: { label: 'Sans (Helvetica)', css: 'Helvetica, Arial, sans-serif', pdf: ['Helvetica', 'HelveticaBold', 'HelveticaOblique', 'HelveticaBoldOblique'] },
  serif: { label: 'Serif (Times)', css: '"Times New Roman", Times, serif', pdf: ['TimesRoman', 'TimesRomanBold', 'TimesRomanItalic', 'TimesRomanBoldItalic'] },
  mono: { label: 'Typewriter (Courier)', css: '"Courier New", Courier, monospace', pdf: ['Courier', 'CourierBold', 'CourierOblique', 'CourierBoldOblique'] },
};
const fontKey = (family, bold, italic) => FONTS[family].pdf[(bold ? 1 : 0) + (italic ? 2 : 0)];

const SAVE_KEY = 'watermark';
const DEFAULTS = {
  mode: 'text', text: 'CONFIDENTIAL', size: 56, color: '#dc2626', family: 'sans', bold: true, italic: false,
  logoSize: 35, opacity: 25, angle: 45, layout: 'center', posX: 50, posY: 50, suffix: '-watermarked', fileCount: 0,
};

const EDGE = 24; // gap from the page edge for the corner layout, in PDF points
let uid = 1;

const hexToRgb = (hex) => [
  parseInt(hex.slice(1, 3), 16) / 255,
  parseInt(hex.slice(3, 5), 16) / 255,
  parseInt(hex.slice(5, 7), 16) / 255,
];

/**
 * Centres of the watermarks for a page of w x h, for an item of itemW x itemH.
 * `up` = true when y grows upward (PDF coordinates), false for canvas coordinates.
 */
function placeSpots(layout, w, h, itemW, itemH, up, angleDeg = 0, pos = { x: 50, y: 50 }) {
  if (layout === 'custom') {
    // The sliders move the whole (rotated) item between the page edges: 0% = touching one edge, 100% = the other
    const rad = (angleDeg * Math.PI) / 180;
    const bw = Math.abs(itemW * Math.cos(rad)) + Math.abs(itemH * Math.sin(rad));
    const bh = Math.abs(itemW * Math.sin(rad)) + Math.abs(itemH * Math.cos(rad));
    const x = w > bw ? bw / 2 + (w - bw) * (pos.x / 100) : w / 2;
    const y = h > bh ? bh / 2 + (h - bh) * (pos.y / 100) : h / 2; // measured from the top
    return [[x, up ? h - y : y]];
  }
  if (layout === 'corner') {
    const x = w - itemW / 2 - EDGE;
    const y = EDGE + itemH / 2; // measured from the bottom edge
    return [[x, up ? y : h - y]];
  }
  if (layout === 'tiled') {
    const stepX = Math.max(60, itemW * 1.5);
    const stepY = Math.max(50, itemH * 3.2);
    const spots = [];
    let row = 0;
    for (let y = stepY / 2; y < h + stepY && spots.length < 400; y += stepY, row += 1) {
      const shift = row % 2 ? stepX / 2 : 0;
      for (let x = -stepX / 2 + shift; x < w + stepX && spots.length < 400; x += stepX) {
        spots.push([x, up ? h - y : y]);
      }
    }
    return spots;
  }
  return [[w / 2, h / 2]];
}

const loadImg = (url) =>
  new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error('That image could not be read.'));
    img.src = url;
  });

const isPdf = (f) => /\.pdf$/i.test(f.name) || f.type === 'application/pdf';

/** PDF Watermark: stamp text OR a photo/logo on every page of one or many PDFs, with a live preview. */
export default function WatermarkTool() {
  const [saved] = useState(() => loadKept(SAVE_KEY, DEFAULTS));
  const [files, setFiles] = useState([]); // [{ id, file }]
  const [activeId, setActiveId] = useState(null); // the PDF shown in the preview
  const [names, setNames] = useState({}); // user-edited names by file id
  const [mode, setMode] = useState(saved.mode); // text | image
  const [text, setText] = useState(saved.text);
  const [size, setSize] = useState(saved.size);
  const [color, setColor] = useState(saved.color);
  const [family, setFamily] = useState(saved.family);
  const [bold, setBold] = useState(saved.bold);
  const [italic, setItalic] = useState(saved.italic);
  const [logo, setLogo] = useState(null); // { name, url, img }
  const [logoSize, setLogoSize] = useState(saved.logoSize); // % of the page width
  const [opacity, setOpacity] = useState(saved.opacity);
  const [angle, setAngle] = useState(saved.angle);
  const [layout, setLayout] = useState(saved.layout); // center | tiled | corner | custom
  const [posX, setPosX] = useState(saved.posX); // left-right, 0 to 100
  const [posY, setPosY] = useState(saved.posY); // top-bottom, 0 to 100
  const [suffix, setSuffix] = useState(saved.suffix);
  const [restored, setRestored] = useState(false);
  const [busy, setBusy] = useState('');
  const [results, setResults] = useState(null); // [{ id, blob, pages }]
  const [viewing, setViewing] = useState(null);
  const [msg, setMsg] = useState(null);
  const base = useRef(null); // { id, canvas, wPt, hPt }
  const view = useRef(null);
  const failed = useRef(null); // id of a PDF that could not be opened
  const keptCount = useRef(saved.fileCount || 0);

  const active = files.find((f) => f.id === activeId) || files[0] || null;

  /* ---------- keep the uploaded PDFs on this device ---------- */
  const persist = (list) => {
    list.forEach((f, i) => keepFile(`watermark-pdf-${i}`, f.file));
    for (let i = list.length; i < keptCount.current; i += 1) dropKeptFile(`watermark-pdf-${i}`);
    keptCount.current = list.length;
  };

  const addFiles = (picked) => {
    const pdfs = picked.filter(isPdf);
    if (!pdfs.length) {
      setMsg({ err: true, text: 'Please choose PDF files.' });
      return;
    }
    const added = pdfs.map((f) => ({ id: uid++, file: f }));
    const next = [...files, ...added];
    setFiles(next);
    if (!activeId) setActiveId(added[0].id);
    setResults(null);
    setRestored(false);
    setMsg(pdfs.length < picked.length ? { err: true, text: 'Only PDF files were added.' } : null);
    persist(next);
  };

  const removeFile = (id) => {
    const next = files.filter((f) => f.id !== id);
    setFiles(next);
    if (activeId === id) setActiveId(next[0] ? next[0].id : null);
    setResults(null);
    persist(next);
  };

  const pickLogo = async (list) => {
    const f = list[0];
    if (!/^image\//.test(f.type)) {
      setMsg({ err: true, text: 'Please choose an image (PNG with a transparent background looks best).' });
      return;
    }
    try {
      const url = URL.createObjectURL(f);
      const img = await loadImg(url);
      if (logo) URL.revokeObjectURL(logo.url);
      setLogo({ name: f.name, url, img });
      setMsg(null);
      keepFile('watermark-logo', f);
    } catch (e) {
      setMsg({ err: true, text: e.message });
    }
  };

  useEffect(() => () => logo && URL.revokeObjectURL(logo.url), [logo]);

  // Bring back the last PDFs and logo after a refresh or when coming back later
  useEffect(() => {
    let dead = false;
    (async () => {
      const list = [];
      const count = saved.fileCount || 0;
      for (let i = 0; i < count; i += 1) {
        const f = await loadKeptFile(`watermark-pdf-${i}`);
        if (f) list.push({ id: uid++, file: f });
      }
      if (!count) {
        const old = await loadKeptFile('watermark-pdf'); // older single-file save
        if (old) list.push({ id: uid++, file: old });
      }
      const img = await loadKeptFile('watermark-logo');
      if (dead) return;
      if (list.length) {
        setFiles(list);
        setActiveId(list[0].id);
        keptCount.current = list.length;
        setRestored(true);
      }
      if (img) {
        try {
          const url = URL.createObjectURL(img);
          const el = await loadImg(url);
          if (!dead) setLogo({ name: img.name, url, img: el });
        } catch {
          /* ignore a broken saved image */
        }
      }
    })();
    return () => {
      dead = true;
    };
  }, []); // eslint-disable-line

  // Remember every setting as it changes
  useEffect(() => {
    saveKept(SAVE_KEY, { mode, text, size, color, family, bold, italic, logoSize, opacity, angle, layout, posX, posY, suffix, fileCount: files.length });
  }, [mode, text, size, color, family, bold, italic, logoSize, opacity, angle, layout, posX, posY, suffix, files.length]);

  const forget = () => {
    for (let i = 0; i < Math.max(keptCount.current, files.length); i += 1) dropKeptFile(`watermark-pdf-${i}`);
    dropKeptFile('watermark-pdf');
    dropKeptFile('watermark-logo');
    dropKept(SAVE_KEY);
    keptCount.current = 0;
    base.current = null;
    setFiles([]);
    setActiveId(null);
    setLogo(null);
    setResults(null);
    setRestored(false);
    setMsg({ ok: true, text: 'Saved files and settings removed from this device.' });
  };

  useEffect(() => setResults(null), [mode, text, size, color, family, bold, italic, logo, logoSize, opacity, angle, layout, posX, posY]);

  /* ---------- live preview of page 1 of the selected PDF ---------- */
  const draw = () => {
    const c = view.current;
    const b = base.current;
    if (!c || !b) return;
    c.width = b.canvas.width;
    c.height = b.canvas.height;
    const ctx = c.getContext('2d');
    ctx.drawImage(b.canvas, 0, 0);
    const k = c.width / b.wPt;
    ctx.save();
    ctx.globalAlpha = opacity / 100;

    if (mode === 'image') {
      if (!logo) {
        ctx.restore();
        return;
      }
      const iw = c.width * (logoSize / 100);
      const ih = iw * (logo.img.naturalHeight / logo.img.naturalWidth);
      placeSpots(layout, c.width, c.height, iw, ih, false, angle, { x: posX, y: posY }).forEach(([x, y]) => {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((-angle * Math.PI) / 180);
        ctx.drawImage(logo.img, -iw / 2, -ih / 2, iw, ih);
        ctx.restore();
      });
    } else {
      ctx.fillStyle = color;
      ctx.font = `${italic ? 'italic ' : ''}${bold ? 'bold ' : ''}${size * k}px ${FONTS[family].css}`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      const tw = ctx.measureText(text || ' ').width;
      placeSpots(layout, c.width, c.height, tw, size * k, false, angle, { x: posX, y: posY }).forEach(([x, y]) => {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((-angle * Math.PI) / 180);
        ctx.fillText(text, 0, 0);
        ctx.restore();
      });
    }
    ctx.restore();
  };

  useEffect(() => {
    if (!active) return undefined;
    let dead = false;
    (async () => {
      if ((!base.current || base.current.id !== active.id) && failed.current !== active.id) {
        try {
          const pdfjs = await loadPdfJs();
          const doc = await pdfjs.getDocument({ data: (await readBytes(active.file)).slice() }).promise;
          const page = await doc.getPage(1);
          const v1 = page.getViewport({ scale: 1 });
          const vw = page.getViewport({ scale: 320 / v1.width });
          const canvas = document.createElement('canvas');
          canvas.width = Math.floor(vw.width);
          canvas.height = Math.floor(vw.height);
          const ctx = canvas.getContext('2d');
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          await page.render({ canvasContext: ctx, viewport: vw }).promise;
          base.current = { id: active.id, canvas, wPt: v1.width, hPt: v1.height };
        } catch (e) {
          failed.current = active.id;
          if (!dead) setMsg({ err: true, text: `Could not preview ${active.file.name} (it may be password-protected).` });
          return;
        }
      }
      if (!dead && base.current && base.current.id === active.id) draw();
    })();
    return () => {
      dead = true;
    };
  }); // redraws whenever any setting changes

  /* ---------- names ---------- */
  const nameFor = (id) => names[id] ?? baseName(files.find((f) => f.id === id)?.file.name || 'file');
  const finalName = (id) => safeName(withSuffix(nameFor(id), suffix), 'pdf', 'watermarked');
  const shownSuffix = withSuffix('', suffix);

  /* ---------- stamp every PDF ---------- */
  const stampOne = async (lib, file, pngBytes) => {
    const doc = await lib.PDFDocument.load(await readBytes(file), { ignoreEncryption: true });
    const rad = (angle * Math.PI) / 180;
    const alpha = opacity / 100;

    // pdf-lib rotates around the item's bottom-left point, so shift it so the item's centre lands on (cx, cy)
    const anchor = (cx, cy, iw, ih) => ({
      x: cx - (iw / 2) * Math.cos(rad) + (ih / 2) * Math.sin(rad),
      y: cy - (iw / 2) * Math.sin(rad) - (ih / 2) * Math.cos(rad),
    });

    if (mode === 'image') {
      const png = await doc.embedPng(pngBytes.bytes);
      doc.getPages().forEach((page) => {
        const { width, height } = page.getSize();
        const iw = width * (logoSize / 100);
        const ih = iw * (pngBytes.h / pngBytes.w);
        placeSpots(layout, width, height, iw, ih, true, angle, { x: posX, y: posY }).forEach(([cx, cy]) => {
          const { x, y } = anchor(cx, cy, iw, ih);
          page.drawImage(png, { x, y, width: iw, height: ih, opacity: alpha, rotate: lib.degrees(angle) });
        });
      });
    } else {
      const font = await doc.embedFont(lib.StandardFonts[fontKey(family, bold, italic)]);
      const [r, g, bl] = hexToRgb(color);
      const tw = font.widthOfTextAtSize(text, size);
      const th = size * 0.7;
      doc.getPages().forEach((page) => {
        const { width, height } = page.getSize();
        placeSpots(layout, width, height, tw, size, true, angle, { x: posX, y: posY }).forEach(([cx, cy]) => {
          const { x, y } = anchor(cx, cy, tw, th);
          page.drawText(text, { x, y, size, font, color: lib.rgb(r, g, bl), opacity: alpha, rotate: lib.degrees(angle) });
        });
      });
    }
    const bytes = await doc.save();
    return { blob: new Blob([bytes], { type: 'application/pdf' }), pages: doc.getPageCount() };
  };

  const apply = async () => {
    if (!files.length) return;
    if (mode === 'text') {
      if (!text.trim()) {
        setMsg({ err: true, text: 'Type the watermark text first.' });
        return;
      }
      if (!LATIN.test(text)) {
        setMsg({ err: true, text: 'Only English letters, numbers and common symbols are supported in the watermark text.' });
        return;
      }
    } else if (!logo) {
      setMsg({ err: true, text: 'Choose the photo or logo to use as the watermark.' });
      return;
    }
    setBusy('Starting...');
    setMsg(null);
    setResults(null);
    try {
      const lib = await loadPdfLib();
      let pngBytes = null;
      if (mode === 'image') {
        // Re-draw through a canvas as PNG: keeps transparency and fixes phone-photo rotation
        const src = logo.img;
        const k = Math.min(1, 1600 / Math.max(src.naturalWidth, src.naturalHeight));
        const c = document.createElement('canvas');
        c.width = Math.round(src.naturalWidth * k);
        c.height = Math.round(src.naturalHeight * k);
        c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
        const blob = await new Promise((res) => c.toBlob(res, 'image/png'));
        pngBytes = { bytes: new Uint8Array(await blob.arrayBuffer()), w: c.width, h: c.height };
      }
      const out = [];
      const failedNames = [];
      for (let i = 0; i < files.length; i += 1) {
        setBusy(`Adding watermark ${i + 1} / ${files.length}...`);
        try {
          out.push({ id: files[i].id, ...(await stampOne(lib, files[i].file, pngBytes)) });
        } catch {
          failedNames.push(files[i].file.name);
        }
      }
      setResults(out);
      if (failedNames.length) {
        setMsg({ err: true, text: `Could not open: ${failedNames.join(', ')} (maybe password-protected). The rest are done.` });
      }
    } catch (e) {
      setMsg({ err: true, text: `Could not add the watermark: ${e.message || 'unknown error'}` });
    } finally {
      setBusy('');
    }
  };

  const downloadAll = () => {
    results.forEach((r, i) => setTimeout(() => downloadBlob(r.blob, finalName(r.id)), i * 350));
    setMsg({ ok: true, text: `${results.length} PDF${results.length === 1 ? '' : 's'} downloading...` });
  };

  const viewed = viewing !== null && results ? results.find((r) => r.id === viewing) : null;

  return (
    <div className="tl-panel">
      <h2>PDF Watermark</h2>
      <p className="tl-help">
        Stamp text or your own photo/logo on every page of one or many PDFs at once. The preview shows page 1 of
        the PDF you tap in the list.
      </p>

      {files.length === 0 ? (
        <FilePick accept="application/pdf" multiple onFiles={addFiles} label="Tap to choose PDFs" hint="One or many PDF files" />
      ) : (
        <>
          <div className="tl-row">
            <label className="tl-btn is-ghost is-small">
              <ion-icon name="add-circle-outline"></ion-icon> Add new
              <input
                type="file"
                accept="application/pdf"
                multiple
                hidden
                onChange={(e) => {
                  const list = Array.from(e.target.files || []);
                  if (list.length) addFiles(list);
                  e.target.value = '';
                }}
              />
            </label>
            <button type="button" className="tl-btn is-ghost is-small" onClick={forget}>
              Remove all
            </button>
          </div>

          <ul className="tl-list">
            {files.map((f, i) => (
              <li
                key={f.id}
                className={f.id === (active && active.id) ? 'is-picked' : ''}
                onClick={() => setActiveId(f.id)}
                role="button"
                tabIndex={0}
                onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && setActiveId(f.id)}
              >
                <span className="tl-name">{i + 1}. {f.file.name}</span>
                <small>{formatBytes(f.file.size)}</small>
                <button
                  type="button"
                  className="tl-icon-btn"
                  aria-label="Remove"
                  onClick={(e) => {
                    e.stopPropagation();
                    removeFile(f.id);
                  }}
                >
                  <ion-icon name="close-outline"></ion-icon>
                </button>
              </li>
            ))}
          </ul>

          <p className="tl-keepnote">
            <ion-icon name="save-outline"></ion-icon>
            <span>{restored ? 'Your last files were restored. ' : ''}Saved on this device, so a refresh will not lose them.</span>
          </p>

          <div className="tl-wm-view">
            <canvas ref={view} />
          </div>

          <div className="tl-tabs" role="tablist">
            <button type="button" className={mode === 'text' ? 'is-on' : ''} onClick={() => setMode('text')}>
              Text
            </button>
            <button type="button" className={mode === 'image' ? 'is-on' : ''} onClick={() => setMode('image')}>
              Photo / Logo
            </button>
          </div>

          {mode === 'text' ? (
            <>
              <div className="tl-row">
                <div className="tl-field">
                  <label>Watermark text</label>
                  <input className="tl-input" value={text} onChange={(e) => setText(e.target.value)} placeholder="e.g. CONFIDENTIAL" />
                </div>
              </div>
              <div className="tl-row">
                <div className="tl-field">
                  <label>Font</label>
                  <select className="tl-select" value={family} onChange={(e) => setFamily(e.target.value)}>
                    {Object.entries(FONTS).map(([k, v]) => (
                      <option key={k} value={k}>{v.label}</option>
                    ))}
                  </select>
                </div>
                <label className="tl-check">
                  <input type="checkbox" checked={bold} onChange={(e) => setBold(e.target.checked)} /> Bold
                </label>
                <label className="tl-check">
                  <input type="checkbox" checked={italic} onChange={(e) => setItalic(e.target.checked)} /> Italic
                </label>
              </div>
              <div className="tl-row">
                <div className="tl-field">
                  <label>Text size ({size})</label>
                  <input type="range" min="16" max="140" value={size} onChange={(e) => setSize(Number(e.target.value))} />
                </div>
              </div>
              <div className="tl-row">
                <span className="tl-swatch-label">Colour</span>
                {COLORS.map((c) => (
                  <button
                    key={c}
                    type="button"
                    className={`tl-swatch ${c === color ? 'is-on' : ''}`}
                    style={{ background: c }}
                    aria-label={`Colour ${c}`}
                    onClick={() => setColor(c)}
                  />
                ))}
              </div>
            </>
          ) : (
            <>
              {!logo ? (
                <FilePick accept="image/*" onFiles={pickLogo} label="Tap to choose a photo or logo" hint="PNG with a transparent background looks best" />
              ) : (
                <FileCard
                  icon="image-outline"
                  name={logo.name}
                  meta={`${logo.img.naturalWidth} x ${logo.img.naturalHeight}px`}
                  accept="image/*"
                  onFiles={pickLogo}
                  addLabel="Change"
                />
              )}
              <div className="tl-row">
                <div className="tl-field">
                  <label>Photo size ({logoSize}% of page width)</label>
                  <input type="range" min="8" max="90" value={logoSize} onChange={(e) => setLogoSize(Number(e.target.value))} />
                </div>
              </div>
            </>
          )}

          <div className="tl-row">
            <div className="tl-field">
              <label>Visibility ({opacity}%)</label>
              <input type="range" min="5" max="100" value={opacity} onChange={(e) => setOpacity(Number(e.target.value))} />
            </div>
          </div>
          <div className="tl-row">
            <div className="tl-field">
              <label>Layout</label>
              <select className="tl-select" value={layout} onChange={(e) => setLayout(e.target.value)}>
                <option value="center">One in the centre</option>
                <option value="tiled">Repeated all over the page</option>
                <option value="corner">Bottom-right corner</option>
                <option value="custom">Set position with sliders</option>
              </select>
            </div>
            <div className="tl-field">
              <label>Angle</label>
              <select className="tl-select" value={angle} onChange={(e) => setAngle(Number(e.target.value))}>
                <option value={45}>Diagonal</option>
                <option value={0}>Straight</option>
                <option value={90}>Vertical</option>
              </select>
            </div>
          </div>

          {layout === 'custom' && (
            <div className="tl-possliders">
              <div className="tl-field">
                <label>Left - Right ({posX}%)</label>
                <input type="range" min="0" max="100" value={posX} onChange={(e) => setPosX(Number(e.target.value))} />
              </div>
              <div className="tl-field">
                <label>Top - Bottom ({posY}%)</label>
                <input type="range" min="0" max="100" value={posY} onChange={(e) => setPosY(Number(e.target.value))} />
              </div>
              <button
                type="button"
                className="tl-btn is-ghost is-small"
                onClick={() => {
                  setPosX(50);
                  setPosY(50);
                }}
              >
                Back to centre
              </button>
            </div>
          )}

          <div className="tl-row">
            <div className="tl-field">
              <label>Add at the end of every name (optional)</label>
              <input className="tl-input" value={suffix} placeholder="e.g. -watermarked" onChange={(e) => setSuffix(e.target.value)} />
            </div>
          </div>

          <div className="tl-outs">
            <p className="tl-outs-head">
              {results ? 'Your PDFs are ready. You can still rename them.' : 'File names (tap a name to change it)'}
            </p>
            {files.map((f, i) => {
              const res = results ? results.find((x) => x.id === f.id) : null;
              return (
                <div className={`tl-pdfcard ${res ? 'is-ready' : ''}`} key={f.id}>
                  <div className="tl-pdfcard-top">
                    <span className="tl-pdfcard-no">{i + 1}</span>
                    <input
                      className="tl-input"
                      value={nameFor(f.id)}
                      aria-label={`Name of PDF ${i + 1}`}
                      onChange={(e) => setNames({ ...names, [f.id]: e.target.value })}
                    />
                    {res && (
                      <>
                        <button type="button" className="tl-view-btn" aria-label="Open full screen" title="Open full screen" onClick={() => setViewing(f.id)}>
                          <ion-icon name="scan-outline"></ion-icon>
                        </button>
                        <button type="button" className="tl-dl-btn" aria-label="Download" onClick={() => downloadBlob(res.blob, finalName(f.id))}>
                          <ion-icon name="download-outline"></ion-icon>
                        </button>
                      </>
                    )}
                  </div>
                  <p className="tl-pdfcard-meta">
                    {res && <span className="tl-chip-soft">{res.pages} page{res.pages === 1 ? '' : 's'}</span>}
                    {res && <span className="tl-chip-soft is-size">{formatBytes(res.blob.size)}</span>}
                    <span className="tl-pdfcard-saved-name" title={finalName(f.id)}>{finalName(f.id)}</span>
                  </p>
                </div>
              );
            })}
          </div>

          <div className="tl-btn-pair">
            <button type="button" className="tl-btn" disabled={!!busy} onClick={apply}>
              <ion-icon name="water-outline"></ion-icon> {busy || `Add watermark${files.length > 1 ? ` (${files.length} PDFs)` : ''}`}
            </button>
            {results && results.length > 1 && (
              <button type="button" className="tl-dl-btn is-all" onClick={downloadAll}>
                <ion-icon name="download-outline"></ion-icon> Download all ({results.length})
              </button>
            )}
          </div>

          {viewed && (
            <PdfViewer
              file={viewed.blob}
              name={nameFor(viewed.id)}
              suffix={shownSuffix}
              onRename={(v) => setNames({ ...names, [viewed.id]: v })}
              onClose={() => setViewing(null)}
            />
          )}
        </>
      )}
      {msg && <p className={`tl-msg ${msg.ok ? 'is-ok' : ''} ${msg.err ? 'is-err' : ''}`}>{msg.text}</p>}
    </div>
  );
}
