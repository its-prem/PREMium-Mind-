/**
 * Microsheet dashboard: look at the A4-boxes tool with the text of any Important Questions product,
 * change the text and save it back to the product. The tool itself lives in src/microsheet/microsheet.html.
 */
import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import MicroSheetFrame from '../../microsheet/MicroSheetFrame';
import { adminGet, adminPost } from '../api';
import { downloadState, formatWhen } from '../../lib/downloadMode';
import { Empty, ErrorBox, Loading, money, useLoad, useUi } from '../ui';

export default function Microsheet() {
  const { toast } = useUi();
  const frameRef = useRef(null);
  const { data, error, loading, reload } = useLoad(() => adminGet('courses.php'));
  const [pid, setPid] = useState('');
  const [doc, setDoc] = useState(null); // { id, title, text, micro }
  const [busy, setBusy] = useState(false);
  const [loadingText, setLoadingText] = useState(false);

  const products = (data?.courses || []).filter((c) => c.category === 'important' && c.is_deleted != 1);

  // pick the first product by default
  useEffect(() => {
    if (!pid && products.length) setPid(String(products[0].id));
  }, [products, pid]);

  const loadText = async (id) => {
    setLoadingText(true);
    try {
      const r = await adminPost('courses.php', { action: 'qa_get', id });
      setDoc({ id, title: r.title, text: r.text || '', micro: r.micro });
    } catch (e) {
      toast(e.message, 'error');
      setDoc(null);
    }
    setLoadingText(false);
  };

  useEffect(() => {
    if (pid) loadText(pid);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pid]);

  const save = async () => {
    if (!doc) return;
    setBusy(true);
    try {
      const got = await frameRef.current?.requestText();
      if (!got) throw new Error('The tool did not answer. Please try again.');
      await adminPost('courses.php', { action: 'qa_save', id: doc.id, text: got.text });
      toast('Text saved to the product');
      reload();
    } catch (e) {
      toast(e.message, 'error');
    }
    setBusy(false);
  };

  if (loading && !data) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  if (products.length === 0) {
    return (
      <Empty text="No Important Questions product yet. Add a product, choose the category “Important Questions” and paste its questions text." />
    );
  }

  const m = doc?.micro;
  const dl = m ? downloadState({ allow_download: m.allowDownload, download_at: m.downloadAt }) : null;

  return (
    <>
      <div className="adm-card ms-ctrl">
        <div className="adm-row between">
          <div className="adm-field" style={{ minWidth: 260, flex: 1 }}>
            <label htmlFor="ms_prod">Product</label>
            <select id="ms_prod" className="adm-select" value={pid} onChange={(e) => setPid(e.target.value)}>
              {products.map((p) => (
                <option key={p.id} value={p.id}>
                  #{p.id} — {p.title}
                  {Number(p.has_qa) ? '' : ' (no text yet)'}
                </option>
              ))}
            </select>
          </div>
          <div className="adm-row">
            <button className="adm-btn ghost" disabled={busy || loadingText} onClick={() => loadText(pid)}>
              Reload from product
            </button>
            <button className="adm-btn good" disabled={busy || loadingText || !doc} onClick={save}>
              {busy ? 'Saving…' : 'Save text to product'}
            </button>
          </div>
        </div>
        {m && (
          <div className="adm-row" style={{ marginTop: 10 }}>
            <span className={`adm-chip ${m.enabled ? 'ok' : 'grey'}`}>{m.enabled ? 'Micro format on sale' : 'Micro format OFF'}</span>
            {m.enabled && <span className="adm-chip">Price {money(m.price || 0)}</span>}
            {m.enabled && (
              <span className={`adm-chip ${dl.mode === 'instant' ? 'ok' : dl.mode === 'timed' ? 'warn' : 'bad'}`}>
                {dl.mode === 'instant' ? 'Download instant' : dl.mode === 'timed' ? `Download ${formatWhen(dl.at)}` : 'Download not allowed'}
              </span>
            )}
            <Link to="/admin/courses" className="adm-muted small" style={{ marginBottom: 0 }}>
              Change price / download time: Products → Edit
            </Link>
          </div>
        )}
        <p className="adm-hint" style={{ marginTop: 8 }}>
          This is the same tool buyers get. Here the download is always open for you. To change the tool itself, edit <code>src/microsheet/microsheet.html</code>.
        </p>
      </div>

      <div className="adm-ms-frame">
        {loadingText && <div className="adm-ms-loading">Loading the text…</div>}
        {doc && <MicroSheetFrame ref={frameRef} subject={doc.title} text={doc.text} canDownload lockedMessage="" />}
      </div>
    </>
  );
}
