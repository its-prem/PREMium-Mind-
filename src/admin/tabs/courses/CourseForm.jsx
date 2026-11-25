/** Add / edit popup for one product. The fields come from fields.js. */
import React, { useState } from 'react';
import { adminPost } from '../../api';
import { Modal, Switch, useUi } from '../../ui';
import { FIELDS, FLAGS, emptyCourse } from './fields';

export default function CourseForm({ course, options, onClose, onSaved }) {
  const { toast } = useUi();
  const editing = !!course?.id;
  const [v, setV] = useState({ ...emptyCourse, ...(course || {}) });
  const [imageFile, setImageFile] = useState(null);
  const [pdfFile, setPdfFile] = useState(null);
  const [saving, setSaving] = useState(false);

  const set = (name, value) => setV((p) => ({ ...p, [name]: value }));
  const isResume = v.category === 'resume';

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
    if (f.type === 'textarea') return <textarea className="adm-textarea" {...common} />;
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

  return (
    <Modal title={editing ? `Edit: ${course.title}` : 'Add a product'} onClose={onClose} wide>
      <form onSubmit={submit}>
        <div className="adm-form-grid">
          {FIELDS.map((f) => (
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
              {f.hint && <span className="adm-hint">{f.hint}</span>}
            </div>
          ))}

          <div className="adm-field">
            <label>Picture</label>
            {v.image && !imageFile && <img className="adm-thumb" src={v.image} alt="" />}
            <input type="file" accept="image/*" onChange={(e) => setImageFile(e.target.files[0] || null)} />
            <span className="adm-hint">jpg, png, webp or gif – max 5 MB</span>
            {v.image && !imageFile && (
              <button type="button" className="adm-btn ghost small" onClick={() => set('image', '')}>
                Remove picture
              </button>
            )}
          </div>
          <div className="adm-field">
            <label>PDF file</label>
            {v.pdf_file && !pdfFile && <span className="adm-chip ok">PDF uploaded: {String(v.pdf_file).split('/').pop()}</span>}
            <input type="file" accept="application/pdf" onChange={(e) => setPdfFile(e.target.files[0] || null)} />
            <span className="adm-hint">max 60 MB</span>
            {v.pdf_file && !pdfFile && (
              <button type="button" className="adm-btn ghost small" onClick={() => set('pdf_file', '')}>
                Remove PDF
              </button>
            )}
          </div>

          <div className="adm-field full">
            <label>Switches</label>
            <div className="adm-form-grid">
              {FLAGS.map((f) => (
                <div key={f.name} className="adm-tog">
                  <span>{f.label}</span>
                  <Switch checked={v[f.name] == 1 || v[f.name] === true} onChange={(on) => set(f.name, on ? 1 : 0)} />
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="adm-row end">
          <button type="button" className="adm-btn ghost" onClick={onClose}>
            Cancel
          </button>
          <button className="adm-btn" disabled={saving}>
            {saving ? 'Saving…' : editing ? 'Save changes' : 'Add product'}
          </button>
        </div>
      </form>
    </Modal>
  );
}
