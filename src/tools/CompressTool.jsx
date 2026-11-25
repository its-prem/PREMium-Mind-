import React, { useEffect, useRef, useState } from 'react';
import { loadPdfJs } from '../lib/pdfViewer';
import { loadPdfLib, formatBytes } from '../lib/cdn';
import { FilePick, FileCard, NameField, SizeCompare, TargetSize, readBytes, baseName, safeName } from './shared';
import ResultActions from './ResultActions';

/* ======================= PDF Compress ======================= */

const canvasToBytes = async (canvas, q) => {
  const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', q));
  return new Uint8Array(await blob.arrayBuffer());
};

function scaledCopy(src, f) {
  if (f >= 0.999) return src;
  const c = document.createElement('canvas');
  c.width = Math.max(1, Math.round(src.width * f));
  c.height = Math.max(1, Math.round(src.height * f));
  const ctx = c.getContext('2d');
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(src, 0, 0, c.width, c.height);
  return c;
}

export default function CompressTool() {
  const [file, setFile] = useState(null);
  const [outName, setOutName] = useState('');
  const [kb, setKb] = useState('');
  const [busy, setBusy] = useState(false);
  const [progress, setProgress] = useState('');
  const [result, setResult] = useState(null); // { bytes, size, reached }
  const [msg, setMsg] = useState(null);
  const pagesRef = useRef(null);

  const pick = (list) => {
    const f = list[0];
    if (!/\.pdf$/i.test(f.name) && f.type !== 'application/pdf') {
      setMsg({ err: true, text: 'Please choose a PDF file.' });
      return;
    }
    pagesRef.current = null;
    setFile(f);
    setOutName(baseName(f.name) + '-compressed');
    setKb(String(Math.max(1, Math.round(f.size / 1024 / 2))));
    setResult(null);
    setMsg(null);
  };

  useEffect(() => setResult(null), [kb]);

  const run = async () => {
    const targetBytes = Number(kb) * 1024;
    if (!(targetBytes > 0)) {
      setMsg({ err: true, text: 'Enter a target size in KB.' });
      return;
    }
    setBusy(true);
    setMsg(null);
    setResult(null);
    try {
      const [pdfjs, lib] = await Promise.all([loadPdfJs(), loadPdfLib()]);
      if (!pagesRef.current) {
        const doc = await pdfjs.getDocument({ data: (await readBytes(file)).slice() }).promise;
        const pages = [];
        for (let n = 1; n <= doc.numPages; n += 1) {
          setProgress(`Reading page ${n} / ${doc.numPages}`);
          const page = await doc.getPage(n);
          const base = page.getViewport({ scale: 1 });
          const scale = Math.min(2, 1500 / base.width);
          const view = page.getViewport({ scale });
          const canvas = document.createElement('canvas');
          canvas.width = Math.floor(view.width);
          canvas.height = Math.floor(view.height);
          const ctx = canvas.getContext('2d');
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          await page.render({ canvasContext: ctx, viewport: view }).promise;
          pages.push({ canvas, w: base.width, h: base.height });
        }
        pagesRef.current = pages;
      }
      const pages = pagesRef.current;

      const build = async (f, q) => {
        const out = await lib.PDFDocument.create();
        for (const p of pages) {
          const img = await out.embedJpg(await canvasToBytes(scaledCopy(p.canvas, f), q));
          const pg = out.addPage([p.w, p.h]);
          pg.drawImage(img, { x: 0, y: 0, width: p.w, height: p.h });
        }
        return out.save();
      };

      // Try bigger pictures first; shrink them only if the quality floor still misses the target.
      let best = null;
      for (const f of [1, 0.75, 0.55, 0.4, 0.3, 0.2]) {
        setProgress(`Trying size ${Math.round(f * 100)}%...`);
        const lowest = await build(f, 0.35);
        if (lowest.length > targetBytes) {
          best = lowest; // smallest we have so far
          continue;
        }
        let lo = 0.35;
        let hi = 0.92;
        let good = lowest;
        for (let i = 0; i < 4; i += 1) {
          const mid = (lo + hi) / 2;
          const bytes = await build(f, mid);
          if (bytes.length <= targetBytes) {
            good = bytes;
            lo = mid;
          } else {
            hi = mid;
          }
        }
        best = good;
        break;
      }
      setResult({ bytes: best, size: best.length, reached: best.length <= targetBytes });
    } catch (e) {
      setMsg({ err: true, text: `Could not compress: ${e.message || 'the file may be password-protected.'}` });
    } finally {
      setBusy(false);
      setProgress('');
    }
  };

  const outFile = () => ({
    blob: new Blob([result.bytes], { type: 'application/pdf' }),
    name: safeName(outName, 'pdf', baseName(file.name) + '-compressed'),
  });

  return (
    <div className="tl-panel">
      <h2>PDF Compress</h2>
      <p className="tl-help">
        Set the size you want and we shrink the PDF to fit. Pages become images after compression, so text
        cannot be selected or copied.
      </p>

      {!file ? (
        <FilePick accept="application/pdf" onFiles={pick} label="Tap to choose a PDF" hint="One PDF file" />
      ) : (
        <>
          <FileCard
            icon="document-text-outline"
            name={file.name}
            meta={formatBytes(file.size)}
            accept="application/pdf"
            onFiles={pick}
            addLabel="Add new"
          />
          <SizeCompare original={file.size} target={Number(kb) * 1024 || null} result={result ? result.size : null} />
          <TargetSize original={file.size} kb={kb} setKb={setKb} />
          <button type="button" className="tl-btn" disabled={busy} onClick={run}>
            <ion-icon name="contract-outline"></ion-icon> {busy ? progress || 'Working...' : 'Compress'}
          </button>

          {result && (
            <>
              <p className={`tl-msg ${result.reached ? 'is-ok' : 'is-err'}`}>
                {result.reached
                  ? `Done: ${formatBytes(file.size)} → ${formatBytes(result.size)}.`
                  : `Could not reach ${formatBytes(Number(kb) * 1024)}. Smallest possible is ${formatBytes(result.size)}. You can still download it, or set a larger target.`}
              </p>
              <NameField value={outName} onChange={setOutName} ext="pdf" placeholder="new file name" />
              {result.size < file.size ? (
                <ResultActions files={[outFile()]} onRename={setOutName} onMessage={(t) => setMsg({ ok: true, text: t })} />
              ) : (
                <p className="tl-msg is-err">The result is not smaller than the original, so download is off. Set a smaller target.</p>
              )}
            </>
          )}
        </>
      )}
      {msg && <p className={`tl-msg ${msg.ok ? 'is-ok' : ''} ${msg.err ? 'is-err' : ''}`}>{msg.text}</p>}
    </div>
  );
}
