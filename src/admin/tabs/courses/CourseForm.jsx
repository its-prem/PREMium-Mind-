/** Add / edit popup for one product. Fields and the order of the blocks come from fields.js. */
import React, { useEffect, useState } from 'react';
import { adminPost, openLocalFile, openUploadedPdf } from '../../api';
import { ImageViewer, Modal, Switch, useUi } from '../../ui';
import { CATEGORIES, FIELDS, FLAGS, SECTIONS, emptyCourse, normalizeCategory } from './fields';

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
      next[f.name] = src[f.name] == 1 ? 1 : 0;
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
        {SECTIONS.map((sec) => (
          <section key={sec.title} className="adm-section">
            <div className="adm-section-head">
              <h4>{sec.title}</h4>
              <span className="adm-hint">{sec.hint}</span>
            </div>
            {sec.flags && <div className="adm-flagrows">{sec.flags.map(renderFlag)}</div>}
            {sec.files && files}
            {sec.fields && <div className="adm-form-grid">{sec.fields.map(renderField)}</div>}
          </section>
        ))}
      </form>
    </Modal>
  );
}
