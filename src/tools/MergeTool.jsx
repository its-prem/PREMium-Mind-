import React, { useEffect, useState } from 'react';
import { loadPdfLib, formatBytes } from '../lib/cdn';
import { FilePick, NameField, readBytes, safeName } from './shared';
import ResultActions from './ResultActions';

let uid = 1;

/** PDF Merge: join many PDFs into one. */
export default function MergeTool() {
  const [files, setFiles] = useState([]);
  const [outName, setOutName] = useState('merged');
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState(null);
  const [msg, setMsg] = useState(null);

  useEffect(() => setResult(null), [files]);

  const add = (list) => {
    const pdfs = list.filter((f) => /pdf$/i.test(f.type) || /\.pdf$/i.test(f.name));
    setFiles((cur) => [...cur, ...pdfs.map((f) => ({ id: uid++, file: f }))]);
    setMsg(pdfs.length < list.length ? { err: true, text: 'Only PDF files were added.' } : null);
  };

  const move = (i, d) => {
    const j = i + d;
    if (j < 0 || j >= files.length) return;
    const next = files.slice();
    [next[i], next[j]] = [next[j], next[i]];
    setFiles(next);
  };

  const merge = async () => {
    setBusy(true);
    setMsg(null);
    setResult(null);
    try {
      const { PDFDocument } = await loadPdfLib();
      const out = await PDFDocument.create();
      for (const item of files) {
        const src = await PDFDocument.load(await readBytes(item.file), { ignoreEncryption: true });
        const pages = await out.copyPages(src, src.getPageIndices());
        pages.forEach((p) => out.addPage(p));
      }
      const bytes = await out.save();
      setResult({ blob: new Blob([bytes], { type: 'application/pdf' }), pages: out.getPageCount() });
    } catch (e) {
      setMsg({ err: true, text: `Could not merge: ${e.message || 'a file may be corrupt or password-protected.'}` });
    } finally {
      setBusy(false);
    }
  };

  const name = safeName(outName, 'pdf', 'merged');

  return (
    <div className="tl-panel">
      <h2>PDF Merge</h2>
      <p className="tl-help">
        Join many PDFs into one. Use the arrows to set the order. Files are processed on your device and are
        never uploaded.
      </p>
      {files.length === 0 ? (
        <FilePick accept="application/pdf" multiple onFiles={add} label="Tap to choose PDFs" hint="Select 2 or more PDFs" />
      ) : (
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
                if (list.length) add(list);
                e.target.value = '';
              }}
            />
          </label>
          <button type="button" className="tl-btn is-ghost is-small" onClick={() => setFiles([])}>
            Clear all
          </button>
        </div>
      )}
      {files.length > 0 && (
        <ul className="tl-list">
          {files.map((f, i) => (
            <li key={f.id}>
              <span className="tl-name">{i + 1}. {f.file.name}</span>
              <small>{formatBytes(f.file.size)}</small>
              <button type="button" className="tl-icon-btn" aria-label="Move up" onClick={() => move(i, -1)}>
                <ion-icon name="arrow-up-outline"></ion-icon>
              </button>
              <button type="button" className="tl-icon-btn" aria-label="Move down" onClick={() => move(i, 1)}>
                <ion-icon name="arrow-down-outline"></ion-icon>
              </button>
              <button type="button" className="tl-icon-btn" aria-label="Remove" onClick={() => setFiles(files.filter((x) => x.id !== f.id))}>
                <ion-icon name="close-outline"></ion-icon>
              </button>
            </li>
          ))}
        </ul>
      )}

      {files.length > 0 && (
        <button type="button" className="tl-btn" disabled={files.length < 2 || busy} onClick={merge}>
          <ion-icon name="git-merge-outline"></ion-icon> {busy ? 'Merging...' : 'Merge PDFs'}
        </button>
      )}

      {result && (
        <>
          <p className="tl-msg is-ok">Done: {result.pages} pages, {formatBytes(result.blob.size)}.</p>
          <NameField value={outName} onChange={setOutName} ext="pdf" placeholder="merged" />
          <ResultActions files={[{ blob: result.blob, name }]} onRename={setOutName} onMessage={(t) => setMsg({ ok: true, text: t })} />
        </>
      )}
      {msg && <p className={`tl-msg ${msg.ok ? 'is-ok' : ''} ${msg.err ? 'is-err' : ''}`}>{msg.text}</p>}
    </div>
  );
}
