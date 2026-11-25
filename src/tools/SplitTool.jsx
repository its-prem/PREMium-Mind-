import React, { useEffect, useMemo, useState } from 'react';
import { loadPdfLib, formatBytes, downloadBlob } from '../lib/cdn';
import { FilePick, FileCard, baseName, readBytes, safeName } from './shared';
import PdfPreview from './PdfPreview';
import PdfViewer from './PdfViewer';
import HelpTip from './HelpTip';
import { withSuffix } from './naming';

/** "4-7 (5, 6)" style text -> pieces split on commas that are NOT inside brackets. */
function splitTop(text) {
  const parts = [];
  let depth = 0;
  let cur = '';
  for (const ch of text) {
    if (ch === '(') depth += 1;
    if (ch === ')') depth = Math.max(0, depth - 1);
    if (depth === 0 && (ch === ',' || ch === ';' || ch === '\n')) {
      parts.push(cur);
      cur = '';
    } else {
      cur += ch;
    }
  }
  parts.push(cur);
  return parts.map((p) => p.trim()).filter(Boolean);
}

const compress = (nums) => {
  const out = [];
  let i = 0;
  while (i < nums.length) {
    let j = i;
    while (j + 1 < nums.length && nums[j + 1] === nums[j] + 1) j += 1;
    out.push(j > i ? `${nums[i]}-${nums[j]}` : `${nums[i]}`);
    i = j + 1;
  }
  return out.join(', ');
};

/**
 * Turn "1-3, 4-7 (5), 9" into ranges. Every range becomes its own PDF.
 * Pages in brackets are left out: "4-7 (5)" keeps pages 4, 6 and 7.
 */
export function parseRanges(text, total) {
  const ranges = [];
  for (const part of splitTop(text)) {
    const m = part.match(/^(\d+)\s*(?:[-–]\s*(\d+))?\s*(?:\(([^)]*)\))?$/);
    if (!m) {
      return { ranges, error: `"${part}" is not valid. Use numbers like 1-3, 5, 7-9, or 4-7 (5) to skip page 5.` };
    }
    const from = Number(m[1]);
    const to = m[2] ? Number(m[2]) : from;
    if (from < 1 || to < 1 || from > total || to > total) {
      return { ranges, error: `"${part}" is outside the PDF (it has ${total} pages).` };
    }
    if (from > to) return { ranges, error: `"${part}": the first page is bigger than the last page.` };

    const skip = new Set();
    if (m[3] !== undefined) {
      for (const item of m[3].split(/[\s,;]+/).filter(Boolean)) {
        const r = item.match(/^(\d+)(?:[-–](\d+))?$/);
        if (!r) return { ranges, error: `"${item}" inside the brackets is not a page number.` };
        const a = Number(r[1]);
        const b = r[2] ? Number(r[2]) : a;
        if (a > b || a < from || b > to) {
          return { ranges, error: `"${item}" is not inside ${from}-${to}, so it cannot be skipped.` };
        }
        for (let n = a; n <= b; n += 1) skip.add(n);
      }
    }
    const pages = [];
    for (let n = from; n <= to; n += 1) if (!skip.has(n)) pages.push(n);
    if (!pages.length) return { ranges, error: `"${part}" would leave no pages.` };
    const skipped = [...skip].sort((x, y) => x - y);
    const label =
      (pages.length === 1 ? `Page ${pages[0]}` : `Pages ${from}-${to}`) +
      (skipped.length && pages.length > 1 ? ` without ${compress(skipped)}` : '');
    ranges.push({ from, to, pages, label });
  }
  return { ranges, error: '' };
}

/**
 * PDF Split: many page ranges at once, each saved as its own PDF.
 * Names start as "<original> 1", "<original> 2"... and can be edited before splitting.
 */
export default function SplitTool() {
  const [file, setFile] = useState(null);
  const [total, setTotal] = useState(0);
  const [text, setText] = useState('');
  const [suffix, setSuffix] = useState('');
  const [names, setNames] = useState({}); // user-edited names by output number
  const [busy, setBusy] = useState(false);
  const [results, setResults] = useState(null); // [{ id, blob }]
  const [viewing, setViewing] = useState(null);
  const [msg, setMsg] = useState(null);

  const pick = (list) => {
    const f = list[0];
    if (!/\.pdf$/i.test(f.name) && f.type !== 'application/pdf') {
      setMsg({ err: true, text: 'Please choose a PDF file.' });
      return;
    }
    setFile(f);
    setTotal(0);
    setText('');
    setNames({});
    setResults(null);
    setMsg(null);
  };

  useEffect(() => setResults(null), [text]);

  const parsed = useMemo(() => (total ? parseRanges(text, total) : { ranges: [], error: '' }), [text, total]);
  const marked = useMemo(() => parsed.ranges.flatMap((r) => r.pages), [parsed]);

  const nameFor = (i) => names[i] ?? `${file ? baseName(file.name) : 'split'} ${i + 1}`;
  const finalName = (i) => safeName(withSuffix(nameFor(i), suffix), 'pdf', `split ${i + 1}`);
  const shownSuffix = (() => {
    const full = withSuffix('', suffix);
    return full;
  })();

  const run = async () => {
    if (!parsed.ranges.length || parsed.error) return;
    setBusy(true);
    setMsg(null);
    setResults(null);
    try {
      const { PDFDocument } = await loadPdfLib();
      const src = await PDFDocument.load(await readBytes(file), { ignoreEncryption: true });
      const out = [];
      for (let i = 0; i < parsed.ranges.length; i += 1) {
        const doc = await PDFDocument.create();
        const pages = await doc.copyPages(src, parsed.ranges[i].pages.map((n) => n - 1));
        pages.forEach((p) => doc.addPage(p));
        const bytes = await doc.save();
        out.push({ id: i, blob: new Blob([bytes], { type: 'application/pdf' }) });
      }
      setResults(out);
    } catch (e) {
      setMsg({ err: true, text: `Could not split: ${e.message || 'the file may be password-protected.'}` });
    } finally {
      setBusy(false);
    }
  };

  const downloadAll = () => {
    results.forEach((r, i) => setTimeout(() => downloadBlob(r.blob, finalName(r.id)), i * 350));
    setMsg({ ok: true, text: `${results.length} PDF${results.length === 1 ? '' : 's'} downloading...` });
  };

  const viewed = viewing !== null && results ? results.find((r) => r.id === viewing) : null;

  return (
    <div className="tl-panel">
      <h2>PDF Split</h2>
      <HelpTip>
        Type many page ranges at once, like <b>1-3, 4-7, 9</b>. Every range becomes its own PDF. To leave a page
        out, put it in brackets: <b>4-7 (5)</b> keeps pages 4, 6 and 7. Tap any page below to open it full screen.
      </HelpTip>

      {!file ? (
        <FilePick accept="application/pdf" onFiles={pick} label="Tap to choose a PDF" hint="One PDF file" />
      ) : (
        <>
          <FileCard
            icon="document-text-outline"
            name={file.name}
            meta={`${total ? `${total} pages · ` : ''}${formatBytes(file.size)}`}
            accept="application/pdf"
            onFiles={pick}
            addLabel="Add new"
          />

          <div className="tl-row">
            <div className="tl-field">
              <label>Page ranges (comma separated)</label>
              <input
                className="tl-input"
                value={text}
                placeholder="e.g. 1-3, 4-7 (5), 9"
                onChange={(e) => setText(e.target.value)}
              />
            </div>
          </div>
          {total > 0 && (
            <div className="tl-row">
              <button type="button" className="tl-btn is-ghost is-small" onClick={() => setText(Array.from({ length: total }, (_, i) => i + 1).join(', '))}>
                Every page separately
              </button>
              <button type="button" className="tl-btn is-ghost is-small" onClick={() => setText('')}>
                Clear
              </button>
            </div>
          )}
          {parsed.error && <p className="tl-msg is-err">{parsed.error}</p>}

          <div className="tl-row">
            <div className="tl-field">
              <label>Add at the end of every name (optional)</label>
              <input
                className="tl-input"
                value={suffix}
                placeholder="e.g. - Sem 3"
                onChange={(e) => setSuffix(e.target.value)}
              />
            </div>
          </div>

          {parsed.ranges.length > 0 && !parsed.error && (
            <div className="tl-outs">
              <p className="tl-outs-head">
                {results ? 'Your PDFs are ready. You can still rename them.' : 'File names (tap a name to change it)'}
              </p>
              {parsed.ranges.map((r, i) => {
                const res = results ? results.find((x) => x.id === i) : null;
                return (
                  <div className={`tl-pdfcard ${res ? 'is-ready' : ''}`} key={i}>
                    <div className="tl-pdfcard-top">
                      <span className="tl-pdfcard-no">{i + 1}</span>
                      <input
                        className="tl-input"
                        value={nameFor(i)}
                        aria-label={`Name of PDF ${i + 1}`}
                        placeholder="File name"
                        onChange={(e) => setNames({ ...names, [i]: e.target.value })}
                      />
                      {res && (
                        <>
                          <button type="button" className="tl-view-btn" aria-label="Open full screen" title="Open full screen" onClick={() => setViewing(i)}>
                            <ion-icon name="scan-outline"></ion-icon>
                          </button>
                          <button type="button" className="tl-dl-btn" aria-label="Download" onClick={() => downloadBlob(res.blob, finalName(i))}>
                            <ion-icon name="download-outline"></ion-icon>
                          </button>
                        </>
                      )}
                    </div>
                    <p className="tl-pdfcard-meta">
                      <span className="tl-chip-soft">{r.label}</span>
                      {res && <span className="tl-chip-soft is-size">{formatBytes(res.blob.size)}</span>}
                      <span className="tl-pdfcard-saved-name" title={finalName(i)}>{finalName(i)}</span>
                    </p>
                  </div>
                );
              })}
            </div>
          )}

          <div className="tl-btn-pair">
            <button type="button" className="tl-btn" disabled={busy || !parsed.ranges.length || !!parsed.error} onClick={run}>
              <ion-icon name="cut-outline"></ion-icon>{' '}
              {busy ? 'Splitting...' : `Split into ${parsed.ranges.length || 0} PDF${parsed.ranges.length === 1 ? '' : 's'}`}
            </button>
            {results && results.length > 0 && (
              <button type="button" className="tl-dl-btn is-all" onClick={downloadAll}>
                <ion-icon name="download-outline"></ion-icon> Download all ({results.length})
              </button>
            )}
          </div>

          <PdfPreview file={file} onCount={setTotal} highlight={marked} />

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
