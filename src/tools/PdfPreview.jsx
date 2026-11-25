import React, { useEffect, useRef, useState } from 'react';
import PdfViewer from './PdfViewer';
import { loadPdfJs } from '../lib/pdfViewer';
import { readBytes } from './shared';

const MAX_THUMBS = 120;

/**
 * Shows every page of a PDF as a small numbered thumbnail, so you can see the
 * page numbers while choosing ranges. Calls onCount(totalPages) once known.
 */
export default function PdfPreview({ file, onCount, highlight = [] }) {
  const [pages, setPages] = useState([]); // [{ n, url }]
  const [total, setTotal] = useState(0);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [startPage, setStartPage] = useState(1);
  const cb = useRef(onCount);
  cb.current = onCount;

  useEffect(() => {
    let dead = false;
    setPages([]);
    setTotal(0);
    setError('');
    (async () => {
      try {
        const pdfjs = await loadPdfJs();
        const doc = await pdfjs.getDocument({ data: (await readBytes(file)).slice() }).promise;
        if (dead) return;
        setTotal(doc.numPages);
        cb.current?.(doc.numPages);
        const count = Math.min(doc.numPages, MAX_THUMBS);
        for (let n = 1; n <= count; n += 1) {
          const page = await doc.getPage(n);
          const base = page.getViewport({ scale: 1 });
          const view = page.getViewport({ scale: 190 / base.width });
          const canvas = document.createElement('canvas');
          canvas.width = Math.floor(view.width);
          canvas.height = Math.floor(view.height);
          const ctx = canvas.getContext('2d');
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          await page.render({ canvasContext: ctx, viewport: view }).promise;
          if (dead) return;
          const url = canvas.toDataURL('image/jpeg', 0.7);
          setPages((cur) => [...cur, { n, url }]);
        }
      } catch (e) {
        if (!dead) setError(e.message || 'Could not open this PDF (it may be password-protected).');
      }
    })();
    return () => {
      dead = true;
    };
  }, [file]);

  const marked = new Set(highlight);

  return (
    <div className="tl-pdfprev">
      <p className="tl-pdfprev-head">
        {error ? error : total ? `${total} page${total === 1 ? '' : 's'}` : 'Opening PDF...'}
        {total > MAX_THUMBS && ` (showing first ${MAX_THUMBS})`}
        {total > 0 && (
          <button type="button" className="tl-btn is-ghost is-small" style={{ marginLeft: 10 }} onClick={() => {
              setStartPage(1);
              setOpen(true);
            }}>
            <ion-icon name="expand-outline"></ion-icon> Open full screen
          </button>
        )}
      </p>
      {open && <PdfViewer file={file} name={file.name} startPage={startPage} onClose={() => setOpen(false)} />}
      <div className="tl-pdfprev-grid">
        {pages.map((p) => (
          <figure
            key={p.n}
            className={marked.has(p.n) ? 'is-marked' : ''}
            role="button"
            tabIndex={0}
            title={`Open page ${p.n} full screen`}
            onClick={() => {
              setStartPage(p.n);
              setOpen(true);
            }}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setStartPage(p.n);
                setOpen(true);
              }
            }}
          >
            <img src={p.url} alt={`Page ${p.n}`} draggable="false" />
            <figcaption>{p.n}</figcaption>
          </figure>
        ))}
      </div>
    </div>
  );
}
