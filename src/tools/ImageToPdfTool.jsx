import React, { useEffect, useState } from 'react';
import { loadPdfLib, formatBytes } from '../lib/cdn';
import { FilePick, NameField, safeName } from './shared';
import ResultActions from './ResultActions';

let uid = 1;
const MAX_SIDE = 3000; // keep huge phone photos from making giant PDFs

const loadImage = (url) =>
  new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error('One of the images could not be read.'));
    img.src = url;
  });

/** Image to PDF: many photos -> one PDF, one photo per page. */
export default function ImageToPdfTool() {
  const [items, setItems] = useState([]); // { id, file, url }
  const [pageSize, setPageSize] = useState('a4'); // a4 | fit
  const [margin, setMargin] = useState('small'); // none | small
  const [outName, setOutName] = useState('images');
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState(null);
  const [msg, setMsg] = useState(null);

  useEffect(() => () => items.forEach((i) => URL.revokeObjectURL(i.url)), []); // eslint-disable-line

  useEffect(() => setResult(null), [items, pageSize, margin]);

  const add = (list) => {
    const imgs = list.filter((f) => /^image\//.test(f.type));
    setItems((cur) => [...cur, ...imgs.map((f) => ({ id: uid++, file: f, url: URL.createObjectURL(f) }))]);
    setMsg(imgs.length < list.length ? { err: true, text: 'Only image files were added.' } : null);
  };

  const move = (i, d) => {
    const j = i + d;
    if (j < 0 || j >= items.length) return;
    const next = items.slice();
    [next[i], next[j]] = [next[j], next[i]];
    setItems(next);
  };

  const remove = (id) => {
    const gone = items.find((x) => x.id === id);
    if (gone) URL.revokeObjectURL(gone.url);
    setItems(items.filter((x) => x.id !== id));
  };

  const build = async () => {
    setBusy(true);
    setMsg(null);
    setResult(null);
    try {
      const { PDFDocument } = await loadPdfLib();
      const out = await PDFDocument.create();
      const pad = margin === 'small' ? 24 : 0;
      for (const item of items) {
        const img = await loadImage(item.url);
        const k = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * k);
        canvas.height = Math.round(img.naturalHeight * k);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height); // also applies the photo's rotation
        const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', 0.92));
        const jpg = await out.embedJpg(new Uint8Array(await blob.arrayBuffer()));

        let pw;
        let ph;
        if (pageSize === 'fit') {
          pw = canvas.width * 0.75 + pad * 2;
          ph = canvas.height * 0.75 + pad * 2;
        } else if (canvas.width > canvas.height) {
          pw = 842;
          ph = 595;
        } else {
          pw = 595;
          ph = 842;
        }
        const page = out.addPage([pw, ph]);
        const scale = Math.min((pw - pad * 2) / canvas.width, (ph - pad * 2) / canvas.height);
        const dw = canvas.width * scale;
        const dh = canvas.height * scale;
        page.drawImage(jpg, { x: (pw - dw) / 2, y: (ph - dh) / 2, width: dw, height: dh });
      }
      const bytes = await out.save();
      setResult({ blob: new Blob([bytes], { type: 'application/pdf' }), pages: items.length });
    } catch (e) {
      setMsg({ err: true, text: `Could not create the PDF: ${e.message || 'unknown error'}` });
    } finally {
      setBusy(false);
    }
  };

  const name = safeName(outName, 'pdf', 'images');

  return (
    <div className="tl-panel">
      <h2>Image to PDF</h2>
      <p className="tl-help">
        Turn photos or scans into one PDF, one image per page. Use the arrows to set the page order. Everything
        happens on your device.
      </p>

      {items.length === 0 ? (
        <FilePick accept="image/*" multiple onFiles={add} label="Tap to choose images" hint="JPG, PNG or WebP, one or many" />
      ) : (
        <>
          <div className="tl-row">
            <label className="tl-btn is-ghost is-small">
              <ion-icon name="add-circle-outline"></ion-icon> Add new
              <input
                type="file"
                accept="image/*"
                multiple
                hidden
                onChange={(e) => {
                  const list = Array.from(e.target.files || []);
                  if (list.length) add(list);
                  e.target.value = '';
                }}
              />
            </label>
            <button type="button" className="tl-btn is-ghost is-small" onClick={() => { items.forEach((i) => URL.revokeObjectURL(i.url)); setItems([]); }}>
              Clear all
            </button>
          </div>

          <ul className="tl-list">
            {items.map((it, i) => (
              <li key={it.id}>
                <img className="tl-thumb" src={it.url} alt="" />
                <span className="tl-name">{i + 1}. {it.file.name}</span>
                <small>{formatBytes(it.file.size)}</small>
                <button type="button" className="tl-icon-btn" aria-label="Move up" onClick={() => move(i, -1)}>
                  <ion-icon name="arrow-up-outline"></ion-icon>
                </button>
                <button type="button" className="tl-icon-btn" aria-label="Move down" onClick={() => move(i, 1)}>
                  <ion-icon name="arrow-down-outline"></ion-icon>
                </button>
                <button type="button" className="tl-icon-btn" aria-label="Remove" onClick={() => remove(it.id)}>
                  <ion-icon name="close-outline"></ion-icon>
                </button>
              </li>
            ))}
          </ul>

          <div className="tl-row">
            <div className="tl-field">
              <label>Page size</label>
              <select className="tl-select" value={pageSize} onChange={(e) => setPageSize(e.target.value)}>
                <option value="a4">A4 (fits the photo inside)</option>
                <option value="fit">Same as photo</option>
              </select>
            </div>
            <div className="tl-field">
              <label>Margin</label>
              <select className="tl-select" value={margin} onChange={(e) => setMargin(e.target.value)}>
                <option value="small">Small</option>
                <option value="none">None (full page)</option>
              </select>
            </div>
          </div>

          <button type="button" className="tl-btn" disabled={busy} onClick={build}>
            <ion-icon name="images-outline"></ion-icon> {busy ? 'Creating...' : `Create PDF (${items.length} page${items.length === 1 ? '' : 's'})`}
          </button>

          {result && (
            <>
              <p className="tl-msg is-ok">
                Done: {result.pages} page{result.pages === 1 ? '' : 's'}, {formatBytes(result.blob.size)}.
              </p>
              <NameField value={outName} onChange={setOutName} ext="pdf" placeholder="images" />
              <ResultActions files={[{ blob: result.blob, name }]} onRename={setOutName} onMessage={(t) => setMsg({ ok: true, text: t })} />
            </>
          )}
        </>
      )}
      {msg && <p className={`tl-msg ${msg.ok ? 'is-ok' : ''} ${msg.err ? 'is-err' : ''}`}>{msg.text}</p>}
    </div>
  );
}
