import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import ResumeDocument, {
  ACCENTS,
  BLANK_RESUME,
  ResumeScaled,
  SAMPLE_RESUME,
} from '../components/ResumeDocument';
import { PRODUCTS, getProduct } from '../data/storeData';
import { usePurchases } from '../lib/purchases';
import { useCatalog } from '../lib/catalog';
import { cloudGet, cloudPut, stampGet, stampSet } from '../lib/cloud';
import { getStoredEmail } from '../lib/session';
import './ResumeEditor.css';

const newId = () => Math.random().toString(36).slice(2, 8);

const BLANKS = {
  experience: () => ({ id: newId(), role: '', company: '', period: '', points: '' }),
  education: () => ({ id: newId(), degree: '', school: '', period: '', score: '' }),
  projects: () => ({ id: newId(), name: '', desc: '' }),
};

const TEMPLATE_LABEL = { modern: 'Modern', classic: 'Classic', compact: 'Compact' };

function Field({ label, value, onChange, placeholder, textarea = false, rows = 3 }) {
  const Tag = textarea ? 'textarea' : 'input';
  return (
    <label className="re-field">
      <span>{label}</span>
      <Tag
        value={value}
        placeholder={placeholder}
        rows={textarea ? rows : undefined}
        onChange={(e) => onChange(e.target.value)}
      />
    </label>
  );
}

function Block({ title, icon, open, onToggle, children }) {
  return (
    <section className={`re-block ${open ? 'is-open' : ''}`}>
      <button type="button" className="re-block-head" onClick={onToggle} aria-expanded={open}>
        <span>
          <ion-icon name={icon}></ion-icon> {title}
        </span>
        <ion-icon name={open ? 'chevron-up' : 'chevron-down'}></ion-icon>
      </button>
      {open && <div className="re-block-body">{children}</div>}
    </section>
  );
}

export default function ResumeEditor() {
  const { productId } = useParams();
  const navigate = useNavigate();
  const { status: catalogStatus } = useCatalog(); // the products come from the server
  const product = getProduct(productId);
  const { owns } = usePurchases();
  const allowed = !!product && product.category === 'resume' && owns(product.id);

  const email = getStoredEmail();
  const storeKey = `pm_resume_data:${(email || 'guest').toLowerCase()}`;

  const [data, setData] = useState(() => {
    try {
      const saved = JSON.parse(localStorage.getItem(storeKey) || 'null');
      return saved ? { ...BLANK_RESUME, ...saved } : SAMPLE_RESUME;
    } catch {
      return SAMPLE_RESUME;
    }
  });
  const [skillsText, setSkillsText] = useState(() => data.skills.join(', '));
  const [template, setTemplate] = useState(product?.template || 'modern');
  const [accent, setAccent] = useState(product?.accent || ACCENTS[0]);
  const [open, setOpen] = useState('basic');
  const [tab, setTab] = useState('edit'); // phone: edit | preview
  const [saved, setSaved] = useState('');
  const first = useRef(true);

  // Templates the buyer owns (they can switch between them)
  const ownedTemplates = useMemo(
    () => PRODUCTS.filter((p) => p.category === 'resume' && owns(p.id)),
    [owns]
  );

  // Autosave (on this device)
  useEffect(() => {
    if (first.current) {
      first.current = false;
      return undefined;
    }
    const t = setTimeout(() => {
      try {
        localStorage.setItem(storeKey, JSON.stringify(data));
        stampSet(storeKey);
        cloudPut('resume', data); // also in the student's account
        setSaved('Saved');
        setTimeout(() => setSaved(''), 1400);
      } catch {
        /* storage blocked */
      }
    }, 450);
    return () => clearTimeout(t);
  }, [data, storeKey]);

  useEffect(() => {
    document.title = 'Resume Editor | PREMium Mind';
  }, []);

  // The template of the product (it may arrive after the first render, when the store loads from the server)
  useEffect(() => {
    if (product?.template) setTemplate(product.template);
    if (product?.accent) setAccent(product.accent);
  }, [product?.id]); // eslint-disable-line

  // Take the resume saved in the account if it is newer than this device's copy
  useEffect(() => {
    let dead = false;
    cloudGet('resume').then((remote) => {
      if (dead || !remote || !remote.data) return;
      if (remote.ts > stampGet(storeKey)) {
        first.current = true; // do not echo it straight back to the server
        stampSet(storeKey, remote.ts);
        const next = { ...BLANK_RESUME, ...remote.data };
        setData(next);
        setSkillsText((next.skills || []).join(', '));
      }
    });
    return () => {
      dead = true;
    };
  }, []); // eslint-disable-line

  const set = (patch) => setData((prev) => ({ ...prev, ...patch }));
  const updateItem = (key, id, patch) =>
    setData((prev) => ({
      ...prev,
      [key]: prev[key].map((it) => (it.id === id ? { ...it, ...patch } : it)),
    }));
  const addItem = (key) =>
    setData((prev) => ({ ...prev, [key]: [...prev[key], BLANKS[key]()] }));
  const removeItem = (key, id) =>
    setData((prev) => ({ ...prev, [key]: prev[key].filter((it) => it.id !== id) }));

  const toggle = (id) => setOpen((cur) => (cur === id ? '' : id));

  const reset = (next) => {
    // eslint-disable-next-line no-alert
    if (!window.confirm(next === SAMPLE_RESUME ? 'Sample data wapas laayein?' : 'Sab kuch saaf karein?')) return;
    setData(next);
    setSkillsText(next.skills.join(', '));
  };

  /* ---------- locked / not found ---------- */
  if (!allowed && !product && catalogStatus === 'loading') {
    return (
      <main className="re-locked">
        <div className="re-locked-card">
          <h1>Loading...</h1>
          <p>Aapka template check ho raha hai.</p>
        </div>
      </main>
    );
  }

  if (!allowed) {
    return (
      <main className="re-locked">
        <div className="re-locked-card">
          <div className="re-locked-icon">
            <ion-icon name={product ? 'lock-closed-outline' : 'alert-circle-outline'}></ion-icon>
          </div>
          <h1>{product ? 'Template locked' : 'Template not found'}</h1>
          <p>
            {product
              ? 'Ye resume template abhi aapke paas nahi hai. Store se kharidne ke baad aap ise edit karke PDF download kar sakte ho.'
              : 'Ye link galat hai. Store se apna template chuno.'}
          </p>
          <div className="re-locked-actions">
            <Link to="/#our-store" className="re-btn re-btn-dark">
              <ion-icon name="bag-handle-outline"></ion-icon> Go to Store
            </Link>
            <Link to="/lecture" className="re-btn">
              My Courses
            </Link>
          </div>
        </div>
      </main>
    );
  }

  return (
    <div className="re-root">
      {/* ================= TOP BAR ================= */}
      <header className="re-bar re-ui">
        <button type="button" className="re-btn re-icon" onClick={() => navigate('/lecture')} aria-label="Back to My Courses">
          <ion-icon name="chevron-back-outline"></ion-icon>
        </button>
        <div className="re-bar-title">
          <strong>Resume Editor</strong>
          <small>{product.title}</small>
        </div>
        <span className="re-saved" aria-live="polite">
          {saved}
        </span>
        <button type="button" className="re-btn re-btn-dark" onClick={() => window.print()}>
          <ion-icon name="download-outline"></ion-icon>
          <span>Download PDF</span>
        </button>
      </header>

      {/* phone: switch between editing and preview */}
      <div className="re-tabs re-ui" role="tablist">
        <button type="button" role="tab" aria-selected={tab === 'edit'} className={tab === 'edit' ? 'is-on' : ''} onClick={() => setTab('edit')}>
          <ion-icon name="create-outline"></ion-icon> Edit
        </button>
        <button type="button" role="tab" aria-selected={tab === 'preview'} className={tab === 'preview' ? 'is-on' : ''} onClick={() => setTab('preview')}>
          <ion-icon name="eye-outline"></ion-icon> Preview
        </button>
      </div>

      <div className="re-layout re-ui">
        {/* ================= EDITOR ================= */}
        <div className={`re-editor ${tab === 'edit' ? 'is-active' : ''}`}>
          <div className="re-style">
            {ownedTemplates.length > 1 && (
              <div className="re-row">
                <span className="re-label">Template</span>
                <div className="re-chips">
                  {ownedTemplates.map((p) => (
                    <button
                      key={p.id}
                      type="button"
                      className={`re-chip ${template === p.template ? 'is-on' : ''}`}
                      onClick={() => {
                        setTemplate(p.template);
                        setAccent(p.accent);
                      }}
                    >
                      {TEMPLATE_LABEL[p.template]}
                    </button>
                  ))}
                </div>
              </div>
            )}
            <div className="re-row">
              <span className="re-label">Color</span>
              <div className="re-swatches">
                {ACCENTS.map((c) => (
                  <button
                    key={c}
                    type="button"
                    aria-label={`Color ${c}`}
                    aria-pressed={accent === c}
                    className={`re-swatch ${accent === c ? 'is-on' : ''}`}
                    style={{ background: c }}
                    onClick={() => setAccent(c)}
                  />
                ))}
              </div>
            </div>
            <div className="re-row re-row-end">
              <button type="button" className="re-link" onClick={() => reset(SAMPLE_RESUME)}>
                Load sample
              </button>
              <button type="button" className="re-link" onClick={() => reset(BLANK_RESUME)}>
                Clear all
              </button>
            </div>
          </div>

          <Block title="Basic details" icon="person-outline" open={open === 'basic'} onToggle={() => toggle('basic')}>
            <Field label="Full name" value={data.name} onChange={(v) => set({ name: v })} placeholder="Your name" />
            <Field label="Job title / Degree" value={data.title} onChange={(v) => set({ title: v })} placeholder="e.g. Diploma in CSE" />
            <div className="re-two">
              <Field label="Email" value={data.email} onChange={(v) => set({ email: v })} placeholder="you@example.com" />
              <Field label="Phone" value={data.phone} onChange={(v) => set({ phone: v })} placeholder="+91 ..." />
            </div>
            <div className="re-two">
              <Field label="Location" value={data.location} onChange={(v) => set({ location: v })} placeholder="City, State" />
              <Field label="LinkedIn / Portfolio" value={data.link} onChange={(v) => set({ link: v })} placeholder="linkedin.com/in/..." />
            </div>
          </Block>

          <Block title="Summary" icon="chatbox-ellipses-outline" open={open === 'summary'} onToggle={() => toggle('summary')}>
            <Field label="2-3 lines about you" textarea rows={5} value={data.summary} onChange={(v) => set({ summary: v })} placeholder="Short professional summary..." />
          </Block>

          <Block title="Skills" icon="flash-outline" open={open === 'skills'} onToggle={() => toggle('skills')}>
            <Field
              label="Separate with commas"
              textarea
              rows={3}
              value={skillsText}
              placeholder="HTML, CSS, JavaScript, Python"
              onChange={(v) => {
                setSkillsText(v);
                set({ skills: v.split(',').map((s) => s.trim()).filter(Boolean) });
              }}
            />
          </Block>

          <Block title="Experience / Internship" icon="briefcase-outline" open={open === 'experience'} onToggle={() => toggle('experience')}>
            {data.experience.map((e) => (
              <div className="re-item" key={e.id}>
                <Field label="Role" value={e.role} onChange={(v) => updateItem('experience', e.id, { role: v })} placeholder="e.g. Web Intern" />
                <div className="re-two">
                  <Field label="Company" value={e.company} onChange={(v) => updateItem('experience', e.id, { company: v })} placeholder="Company, City" />
                  <Field label="Period" value={e.period} onChange={(v) => updateItem('experience', e.id, { period: v })} placeholder="Jun 2025 - Aug 2025" />
                </div>
                <Field label="What you did (one point per line)" textarea rows={4} value={e.points} onChange={(v) => updateItem('experience', e.id, { points: v })} />
                <button type="button" className="re-remove" onClick={() => removeItem('experience', e.id)}>
                  <ion-icon name="trash-outline"></ion-icon> Remove
                </button>
              </div>
            ))}
            <button type="button" className="re-add" onClick={() => addItem('experience')}>
              <ion-icon name="add-circle-outline"></ion-icon> Add experience
            </button>
          </Block>

          <Block title="Education" icon="school-outline" open={open === 'education'} onToggle={() => toggle('education')}>
            {data.education.map((e) => (
              <div className="re-item" key={e.id}>
                <Field label="Degree / Class" value={e.degree} onChange={(v) => updateItem('education', e.id, { degree: v })} />
                <Field label="School / College" value={e.school} onChange={(v) => updateItem('education', e.id, { school: v })} />
                <div className="re-two">
                  <Field label="Period" value={e.period} onChange={(v) => updateItem('education', e.id, { period: v })} placeholder="2022 - 2025" />
                  <Field label="Score" value={e.score} onChange={(v) => updateItem('education', e.id, { score: v })} placeholder="8.4 CGPA" />
                </div>
                <button type="button" className="re-remove" onClick={() => removeItem('education', e.id)}>
                  <ion-icon name="trash-outline"></ion-icon> Remove
                </button>
              </div>
            ))}
            <button type="button" className="re-add" onClick={() => addItem('education')}>
              <ion-icon name="add-circle-outline"></ion-icon> Add education
            </button>
          </Block>

          <Block title="Projects" icon="code-slash-outline" open={open === 'projects'} onToggle={() => toggle('projects')}>
            {data.projects.map((p) => (
              <div className="re-item" key={p.id}>
                <Field label="Project name" value={p.name} onChange={(v) => updateItem('projects', p.id, { name: v })} />
                <Field label="Short description" textarea rows={3} value={p.desc} onChange={(v) => updateItem('projects', p.id, { desc: v })} />
                <button type="button" className="re-remove" onClick={() => removeItem('projects', p.id)}>
                  <ion-icon name="trash-outline"></ion-icon> Remove
                </button>
              </div>
            ))}
            <button type="button" className="re-add" onClick={() => addItem('projects')}>
              <ion-icon name="add-circle-outline"></ion-icon> Add project
            </button>
          </Block>

          <p className="re-tip">
            <ion-icon name="information-circle-outline"></ion-icon>
            <span>
              <b>Download PDF</b> dabane par print window khulegi: wahan <b>&ldquo;Save as PDF&rdquo;</b> chunein.
              Aapka kaam is device par apne aap save hota hai.
            </span>
          </p>
        </div>

        {/* ================= LIVE PREVIEW ================= */}
        <div className={`re-preview ${tab === 'preview' ? 'is-active' : ''}`}>
          <div className="re-sheet">
            <ResumeScaled template={template} data={data} accent={accent} />
          </div>
        </div>
      </div>

      {/* Full-size copy used only when printing / saving as PDF */}
      <div className="re-print" aria-hidden="true">
        <ResumeDocument template={template} data={data} accent={accent} />
      </div>
    </div>
  );
}
