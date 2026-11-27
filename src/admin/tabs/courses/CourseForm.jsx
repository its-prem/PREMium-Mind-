/** Add / edit popup for one product. Fields and the order of the blocks come from fields.js. */
import React, { useEffect, useState } from 'react';
import { adminPost, openLocalFile, openUploadedPdf } from '../../api';
import { ImageViewer, Modal, Switch, useUi } from '../../ui';
import { CATEGORIES, FIELDS, FLAGS, SECTIONS, emptyCourse, normalizeCategory } from './fields';
import QaFormatHelp from './QaFormatHelp';
import QaAddOne from './QaAddOne';

// how many questions the text has (a new one starts with Q.1, Q.2 …)
const countQuestions = (t) => (String(t || '').match(/^\s*(Q\.?\s*\d+|Question\s*\d+)/gim) || []).length;

// "2026-12-05 18:00:00" -> "2026-12-05T18:00" (the value of a date-time box)
const toLocalInput = (d) => {
  const t = String(d || '').trim().replace(' ', 'T');
  return t.length >= 16 ? t.slice(0, 16) : t;
};
// a sensible starting time: tomorrow 6 PM
const nextEvening = () => {
  const d = new Date(Date.now() + 86400000);
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T18:00`;
};

export default function CourseForm({ course, options, allCourses = [], onClose, onSaved }) {
  const { toast } = useUi();
  const editing = !!course?.id;
  const [v, setV] = useState(() => {
    const start = { ...emptyCourse, ...(course || {}) };
    start.category = normalizeCategory(start.category);
    return start;
  });
  const [imageFile, setImageFile] = useState(null);
  const [pdfFile, setPdfFile] = useState(null);
  const [saving, setSaving] = useState(false);
  const [viewer, setViewer] = useState(null); // picture shown big
  const [imgUrl, setImgUrl] = useState(''); // preview of the picture picked now
  // The questions text is loaded on its own (it can be long). It is sent back only if it loaded, so it is never wiped by mistake.
  const [qaLoaded, setQaLoaded] = useState(!editing);
  useEffect(() => {
    if (!editing) return undefined;
    let dead = false;
    adminPost('courses.php', { action: 'qa_get', id: course.id })
      .then((r) => {
        if (dead) return;
        setV((p) => ({ ...p, qa_text: r.text || '' }));
        setQaLoaded(true);
      })
      .catch(() => {});
    return () => {
      dead = true;
    };
  }, [editing, course?.id]);

  // preview address for a picture that is chosen but not uploaded yet
  useEffect(() => {
    if (!imageFile) {
      setImgUrl('');
      return undefined;
    }
    const url = URL.createObjectURL(imageFile);
    setImgUrl(url);
    return () => URL.revokeObjectURL(url);
  }, [imageFile]);

  const viewPdf = async () => {
    try {
      if (pdfFile) openLocalFile(pdfFile);
      else await openUploadedPdf(v.pdf_file);
    } catch (err) {
      toast(err.message, 'error');
    }
  };
  const shownImage = imgUrl || v.image;

  const set = (name, value) => setV((p) => ({ ...p, [name]: value }));
  const isResume = v.category === 'resume';

  // Download rule: instant / on time / not allowed (stored as an allow flag plus a time)
  const modeOf = (allowKey, atKey) => (v[allowKey] == 1 || v[allowKey] === true ? 'instant' : v[atKey] ? 'timed' : 'none');
  const setModeFor = (allowKey, atKey, m) =>
    setV((p) => ({ ...p, [allowKey]: m === 'instant' ? 1 : 0, [atKey]: m === 'timed' ? p[atKey] || nextEvening() : '' }));
  const dlMode = modeOf('allow_download', 'download_at');
  const microDlMode = modeOf('micro_allow_download', 'micro_download_at');
  const isImportant = v.category === 'important';

  // "Copy from existing card": fills the whole form from another product (new product only)
  const copyFrom = (id) => {
    const src = allCourses.find((c) => String(c.id) === String(id));
    if (!src) return;
    const next = { ...emptyCourse };
    next.category = normalizeCategory(src.category);
    Object.keys(emptyCourse).forEach((k) => {
      if (src[k] !== undefined && src[k] !== null) next[k] = src[k];
    });
    FLAGS.forEach((f) => {
      next[f.name] = src[f.name] === undefined ? emptyCourse[f.name] ?? 0 : src[f.name] == 1 ? 1 : 0;
    });
    setV(next);
    setImageFile(null);
    setPdfFile(null);
    toast('Data copied – change what you need, then press Add product');
  };

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const fd = new FormData();
      fd.append('action', 'save');
      if (editing) fd.append('id', course.id);
      FIELDS.forEach((f) => fd.append(f.name, v[f.name] ?? ''));
      fd.append('download_at', dlMode === 'timed' ? toLocalInput(v.download_at) : '');
      fd.append('micro_download_at', microDlMode === 'timed' ? toLocalInput(v.micro_download_at) : '');
      if (isImportant && qaLoaded) fd.append('qa_text', v.qa_text ?? '');
      FLAGS.forEach((f) => fd.append(f.name, v[f.name] == 1 || v[f.name] === true ? '1' : '0'));
      fd.append('existing_image', v.image || '');
      fd.append('existing_pdf', v.pdf_file || '');
      if (imageFile) fd.append('image_file', imageFile);
      if (pdfFile) fd.append('pdf_file', pdfFile);
      await adminPost('courses.php', fd);
      toast(editing ? 'Product updated' : 'Product added');
      onSaved();
    } catch (err) {
      toast(err.message, 'error');
      setSaving(false);
    }
  };

  const input = (f) => {
    const common = { id: `f_${f.name}`, value: v[f.name] ?? '', required: f.required, onChange: (e) => set(f.name, e.target.value) };
    if (f.type === 'choice')
      return (
        <div className="adm-choices" role="radiogroup" aria-label={f.label}>
          {CATEGORIES.map((c) => (
            <button type="button" key={c.id} role="radio" aria-checked={v.category === c.id} className={`adm-choice${v.category === c.id ? ' on' : ''}`} onClick={() => set('category', c.id)}>
              <span>{c.icon}</span>
              {c.label}
            </button>
          ))}
        </div>
      );
    if (f.type === 'textarea') return <textarea className="adm-textarea" rows={f.rows || 3} {...common} />;
    if (f.type === 'select')
      return (
        <select className="adm-select" {...common}>
          {(options?.[f.optionsKey] || []).map((o) => (
            <option key={o.id} value={o.id}>
              {o.label}
            </option>
          ))}
        </select>
      );
    return <input className="adm-input" type={f.type === 'number' ? 'number' : 'text'} step="any" {...common} />;
  };

  const renderField = (name) => {
    const f = FIELDS.find((x) => x.name === name);
    if (!f || (f.showIf && !f.showIf(v))) return null;
    return (
      <div key={f.name} className={`adm-field${f.full ? ' full' : ''}`}>
        <label htmlFor={`f_${f.name}`}>{f.label}</label>
        {input(f)}
        {f.name === 'demo_link' && isResume && (
          <div className="adm-row">
            {(options?.resumeTemplates || []).map((t) => (
              <button type="button" key={t} className="adm-btn ghost small" onClick={() => set('demo_link', `template:${t}`)}>
                {t}
              </button>
            ))}
          </div>
        )}
        {f.fill && (
          <div>
            <button
              type="button"
              className="adm-btn ghost small"
              onClick={() => {
                set(f.name, f.fill.text);
                toast('Sample loaded – edit it and save');
              }}
            >
              {f.fill.label}
            </button>
          </div>
        )}
        {f.hint && <span className="adm-hint">{f.hint}</span>}
        {f.name === 'price' && v.category === 'resume' && (
          <span className={`adm-chip ${Number(v.price) === 0 && String(v.price) !== '' ? 'ok' : ''}`} style={{ alignSelf: 'flex-start' }}>
            {Number(v.price) === 0 && String(v.price) !== ''
              ? 'FREE template: students download it without paying'
              : `Students customize free and pay ₹${Number(v.price) || 0} to download. Write 0 to make it FREE.`}
          </span>
        )}
      </div>
    );
  };

  const renderFlag = (name) => {
    const f = FLAGS.find((x) => x.name === name);
    if (!f) return null;
    const on = v[f.name] == 1 || v[f.name] === true;
    return (
      <div key={f.name} className={`adm-flagrow${on ? ' on' : ''}`}>
        <div>
          <b>{f.label}</b>
          <span className="adm-hint">{f.hint}</span>
        </div>
        <Switch checked={on} onChange={(x) => set(f.name, x ? 1 : 0)} />
      </div>
    );
  };

  const renderDownloadControl = (allowKey, atKey, inputId, hints) => {
    const mode = modeOf(allowKey, atKey);
    return (
      <div className="adm-dl">
        <div className="adm-choices" role="radiogroup" aria-label="Download">
          {[
            ['instant', '⚡', 'Instant'],
            ['timed', '⏰', 'On time'],
            ['none', '🚫', 'Not allowed'],
          ].map(([id, icon, label]) => (
            <button type="button" key={id} role="radio" aria-checked={mode === id} className={`adm-choice${mode === id ? ' on' : ''}`} onClick={() => setModeFor(allowKey, atKey, id)}>
              <span>{icon}</span>
              {label}
            </button>
          ))}
        </div>
        {mode === 'timed' && (
          <div className="adm-field" style={{ marginTop: 12, maxWidth: 280 }}>
            <label htmlFor={inputId}>Download unlocks on (India time)</label>
            <input id={inputId} className="adm-input" type="datetime-local" required value={toLocalInput(v[atKey])} onChange={(e) => set(atKey, e.target.value)} />
            <span className="adm-hint">{hints.timed}</span>
          </div>
        )}
        {mode === 'instant' && <span className="adm-hint">{hints.instant}</span>}
        {mode === 'none' && <span className="adm-hint">{hints.none}</span>}
      </div>
    );
  };

  const downloadControl = renderDownloadControl('allow_download', 'download_at', 'f_download_at', {
    timed: 'The buyer sees this time at checkout. After it, the download button works.',
    instant: 'Shown at checkout as “Instant download”.',
    none: 'Shown at checkout as “Download not allowed”.',
  });

  // ---- Important Questions: the questions text ----
  const qaBlock = (
    <div className="adm-field full">
      <label htmlFor="f_qa_text">Questions &amp; answers</label>
      <QaFormatHelp hasText={!!String(v.qa_text || '').trim()} onUseExample={(t) => set('qa_text', t)} toast={toast} />
      <textarea
        id="f_qa_text"
        className="adm-textarea adm-qa"
        rows={14}
        spellCheck={false}
        disabled={!qaLoaded}
        placeholder={qaLoaded ? 'Q.1 Your first question here\nAnswer lines here…\n\nQ.2 Your second question…' : 'Loading the saved text…'}
        value={v.qa_text ?? ''}
        onChange={(e) => set('qa_text', e.target.value)}
      />
      <QaAddOne getText={() => v.qa_text} onChange={(t) => set('qa_text', t)} toast={toast} />
      <span className="adm-hint">
        Every new question starts with <b>Q.1</b>, <b>Q.2</b> … and the answer follows below it. <b>{countQuestions(v.qa_text)}</b> question(s) found.
        Buyers read this text on the website. Use the <b>Microsheet</b> tab to see how it looks in the A4 boxes.
      </span>
    </div>
  );

  // ---- Important Questions: the paid micro format ----
  const microOn = v.micro_enabled == 1 || v.micro_enabled === true;
  const microBlock = microOn && (
    <>
      <div className="adm-form-grid" style={{ marginTop: 12 }}>
        {renderField('micro_price')}
        <div className="adm-field full">
          <label>Micro sheet download</label>
          {renderDownloadControl('micro_allow_download', 'micro_download_at', 'f_micro_download_at', {
            timed: 'The micro sheet can be downloaded / printed only after this time. The buyer sees the time before paying.',
            instant: 'The buyer can download the micro sheet right after paying.',
            none: 'The buyer can only look at the micro sheet, not download it.',
          })}
        </div>
      </div>
      <div className="adm-flagrows" style={{ marginTop: 6 }}>
        {renderFlag('micro_show_tnc')}
      </div>
      <div className="adm-form-grid">{renderField('micro_tnc_text')}</div>
    </>
  );

  const files = (
    <div className="adm-form-grid">
      <div className="adm-field">
        <label>Card picture</label>
        <div className="adm-drop">
          {shownImage ? (
            <button type="button" className="adm-picbtn" onClick={() => setViewer(shownImage)} title="Click to see it big">
              <img className="adm-thumb big" src={shownImage} alt="Card picture" />
              <span>Click to view big</span>
            </button>
          ) : (
            <span className="adm-muted">No picture yet</span>
          )}
          {imageFile && <span className="adm-chip ok">New: {imageFile.name} (saved when you press the button below)</span>}
          <input type="file" accept="image/*" onChange={(e) => setImageFile(e.target.files[0] || null)} />
        </div>
        <span className="adm-hint">jpg, png, webp or gif, max 5 MB</span>
        {(v.image || imageFile) && (
          <div className="adm-row">
            {imageFile && (
              <button type="button" className="adm-btn ghost small" onClick={() => setImageFile(null)}>
                Undo new picture
              </button>
            )}
            {v.image && !imageFile && (
              <button type="button" className="adm-btn ghost small" onClick={() => set('image', '')}>
                Remove picture
              </button>
            )}
          </div>
        )}
      </div>
      {(
        <div className="adm-field">
          <label>PDF file</label>
          <div className="adm-drop">
            {pdfFile ? (
              <span className="adm-chip ok">New: {pdfFile.name} ({(pdfFile.size / 1048576).toFixed(1)} MB)</span>
            ) : v.pdf_file ? (
              <span className="adm-chip ok">Uploaded: {String(v.pdf_file).split('/').pop()}</span>
            ) : (
              <span className="adm-muted">No PDF yet</span>
            )}
            {(pdfFile || v.pdf_file) && (
              <button type="button" className="adm-btn small" onClick={viewPdf}>
                Open PDF to check
              </button>
            )}
            <input type="file" accept="application/pdf" onChange={(e) => setPdfFile(e.target.files[0] || null)} />
          </div>
          <span className="adm-hint">max 60 MB</span>
          {(v.pdf_file || pdfFile) && (
            <div className="adm-row">
              {pdfFile && (
                <button type="button" className="adm-btn ghost small" onClick={() => setPdfFile(null)}>
                  Undo new PDF
                </button>
              )}
              {v.pdf_file && !pdfFile && (
                <button type="button" className="adm-btn ghost small" onClick={() => set('pdf_file', '')}>
                  Remove PDF
                </button>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );

  return (
    <Modal
      title={editing ? `Edit: ${course.title}` : 'Add a product'}
      onClose={onClose}
      wide
      footer={
        <>
          <button type="button" className="adm-btn ghost" onClick={onClose}>
            Cancel
          </button>
          <button className="adm-btn" form="course-form" disabled={saving}>
            {saving ? 'Saving…' : editing ? 'Save changes' : 'Add product'}
          </button>
        </>
      }
    >
      {viewer && <ImageViewer src={viewer} title="Card picture" onClose={() => setViewer(null)} />}
      <form id="course-form" onSubmit={submit} className="adm-sections">
        {!editing && allCourses.length > 0 && (
          <section className="adm-section adm-copybox">
            <div className="adm-section-head">
              <h4>Copy from existing card</h4>
              <span className="adm-hint">Pick a product: all its data comes into this form. Edit it and press Add product – a new product is made, the old one stays the same.</span>
            </div>
            <select className="adm-select" defaultValue="" onChange={(e) => copyFrom(e.target.value)}>
              <option value="">— Select a card to copy —</option>
              {allCourses.map((c) => (
                <option key={c.id} value={c.id}>
                  #{c.id} — {c.title}
                </option>
              ))}
            </select>
          </section>
        )}
        {SECTIONS.filter((sec) => !sec.showIf || sec.showIf(v)).map((sec) => (
          <section key={sec.title} className="adm-section">
            <div className="adm-section-head">
              <h4>{sec.title}</h4>
              <span className="adm-hint">{sec.files && isImportant ? 'For Important Questions the PDF is optional: you can add a PDF, the questions text (below), or both. The buyer gets whatever you add.' : sec.hint}</span>
            </div>
            {sec.flags && <div className="adm-flagrows">{sec.flags.map(renderFlag)}</div>}
            {sec.download && downloadControl}
            {sec.qa && qaBlock}
            {sec.micro && microBlock}
            {sec.files && files}
            {sec.fields && <div className="adm-form-grid">{sec.fields.map(renderField)}</div>}
          </section>
        ))}
      </form>
    </Modal>
  );
}
