/** Diploma syllabus: paste a syllabus as text, check the preview, upload. It appears on the website's Diploma Syllabus page. */
import React, { useMemo, useState } from 'react';
import { adminGet, adminPost } from '../api';
import { Empty, ErrorBox, Loading, useLoad, useUi } from '../ui';
import { BRANCHES, SEMESTERS, semesterName } from '../../data/syllabus';
import { loadUploadedSyllabus } from '../../lib/syllabusStore';
import SyllabusBulk from './SyllabusBulk';
import { countTopics, parseSyllabusText, unitsToText } from '../../lib/syllabusParse';

const ICONS = ['book-outline', 'flask-outline', 'calculator-outline', 'laptop-outline', 'code-slash-outline', 'cog-outline', 'construct-outline', 'flash-outline', 'hardware-chip-outline', 'radio-outline', 'business-outline', 'library-outline', 'school-outline', 'document-text-outline', 'create-outline', 'flame-outline'];

const EXAMPLE = `Subject: Engineering Chemistry
Code: 2101
L-T-P: 3-1-0

Unit 1: Atomic Structure
Definition of Atom
Isotopes and Isobars
# Valency
Electrovalency and Covalency

Unit 2: Electrochemistry
Definition of Ionisation
Electrolysis`;

/** The prompt the admin copies into ChatGPT / Claude together with the raw syllabus, to get text in the right format. */
const AI_PROMPT = `Convert the syllabus I give below into PLAIN TEXT in exactly this format. Output only the text, inside one code block, nothing else.

FORMAT RULES
1. First lines (one per line): Subject: <full subject name>, Code: <subject code>, L-T-P: <like 3-1-0>, Semester: <1 to 6>
2. Then a blank line.
3. Every unit starts on its own line as: Unit <number>: <unit title>
4. Under a unit, write every topic on its OWN line. One topic = one line. No bullets, no numbering, no bold, no tables.
5. If a unit has sub-headings, write the sub-heading on its own line starting with # (example: # Valency), then its topics under it.
6. Split long paragraphs into separate short topics (split at commas, semicolons or full stops). Keep the original wording; do not add or remove content.
7. Leave one blank line between units.

EXAMPLE OUTPUT
Subject: Engineering Chemistry
Code: 2101
L-T-P: 3-1-0
Semester: 1

Unit 1: Atomic Structure
Definition of Atom
Isotopes and Isobars
# Valency
Electrovalency and Covalency

Unit 2: Electrochemistry
Definition of Ionisation
Electrolysis

SYLLABUS (paste below this line)
`;

const EMPTY = { id: '', title: '', sem: 1, branches: 'all', code: '', ltp: '', icon: 'book-outline', text: '' };

export default function SyllabusTab() {
  const { toast, confirm } = useUi();
  const { data, error, loading, reload } = useLoad(() => adminGet('syllabus.php'));
  const [form, setForm] = useState(null); // null = list, object = editing
  const [saving, setSaving] = useState(false);
  const [bulk, setBulk] = useState(false); // the "add many subjects" screen
  const copyPrompt = async () => {
    try {
      await navigator.clipboard.writeText(AI_PROMPT);
      toast('Prompt copied. Paste it in ChatGPT / Claude with your syllabus.');
    } catch {
      toast('Could not copy. Select the prompt text and copy it.', 'error');
    }
  };

  const parsed = useMemo(() => (form ? parseSyllabusText(form.text) : null), [form]);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  // pasted text may carry Subject / Code / L-T-P / Semester lines: fill the empty boxes from them
  const onText = (text) => {
    const { meta } = parseSyllabusText(text);
    setForm((f) => ({
      ...f,
      text,
      title: f.title || meta.title || '',
      code: f.code || meta.code || '',
      ltp: f.ltp || meta.ltp || '',
      sem: meta.sem && !f.id && Number(f.sem) === 1 ? meta.sem : f.sem,
    }));
  };

  const toggleBranch = (id) => {
    const cur = form.branches === 'all' ? [] : form.branches;
    set('branches', cur.includes(id) ? cur.filter((b) => b !== id) : [...cur, id]);
  };

  const save = async () => {
    if (!form.title.trim()) return toast('Write the subject name.', 'error');
    if (!parsed.units.length) return toast('No units found. Start each unit with "Unit 1: Title".', 'error');
    const branches = form.branches === 'all' || !form.branches.length ? 'all' : form.branches;
    setSaving(true);
    try {
      await adminPost('syllabus.php', {
        action: 'save',
        subject: { id: form.id, title: form.title, sem: Number(form.sem), branches, code: form.code, ltp: form.ltp, icon: form.icon, units: parsed.units },
      });
      toast('Uploaded. It is live on the website.');
      loadUploadedSyllabus(true);
      setForm(null);
      reload();
    } catch (e) {
      toast(e.message, 'error');
    } finally {
      setSaving(false);
    }
  };

  const remove = async (s) => {
    if (!(await confirm({ title: `Delete "${s.title}"?`, text: 'It disappears from the website syllabus.', confirmText: 'Delete', danger: true }))) return;
    try {
      await adminPost('syllabus.php', { action: 'delete', id: s.id });
      toast('Deleted');
      loadUploadedSyllabus(true);
      reload();
    } catch (e) {
      toast(e.message, 'error');
    }
  };

  const edit = (s) =>
    setForm({ id: s.id, title: s.title, sem: s.sem, branches: s.branches === 'all' ? 'all' : s.branches, code: s.code || '', ltp: s.ltp || '', icon: s.icon || 'book-outline', text: unitsToText(s.units) });

  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;

  /* ---------------- add many subjects at once ---------------- */
  if (bulk) {
    return (
      <SyllabusBulk
        existing={data.subjects || []}
        onClose={() => setBulk(false)}
        onDone={(stay) => {
          if (!stay) setBulk(false);
          reload();
        }}
      />
    );
  }

  /* ---------------- the add / edit screen ---------------- */
  if (form) {
    return (
      <>
        <div className="adm-row between" style={{ marginBottom: 12 }}>
          <b style={{ fontSize: '1.05rem' }}>{form.id ? 'Edit subject' : 'Add a subject'}</b>
          <button className="adm-btn ghost" onClick={() => setForm(null)}>Back</button>
        </div>

        <div className="adm-card" style={{ marginBottom: 16 }}>
          <div className="adm-section-head">
            <h4>Prompt for ChatGPT / Claude</h4>
            <button type="button" className="adm-btn small" onClick={copyPrompt}>Copy prompt</button>
          </div>
          <span className="adm-hint">Copy this prompt, paste it in an AI chat with your raw syllabus under it, then paste the answer in the box below.</span>
          <pre style={{ margin: '8px 0 0', maxHeight: 180, overflow: 'auto', whiteSpace: 'pre-wrap', fontSize: '0.78rem', background: '#f6f6f8', padding: 10, borderRadius: 10 }}>{AI_PROMPT}</pre>
        </div>

        <div className="adm-card">
          <div className="adm-form-grid">
            <div className="adm-field full">
              <label htmlFor="sy_text">Syllabus text</label>
              <textarea id="sy_text" className="adm-textarea" rows={14} value={form.text} onChange={(e) => onText(e.target.value)} placeholder={EXAMPLE} spellCheck={false} style={{ fontFamily: 'ui-monospace, Consolas, monospace', fontSize: '0.82rem' }} />
              <span className="adm-hint">
                Start every unit with <b>Unit 1: Title</b>. Each next line is one topic. A line starting with <b>#</b> is a heading inside the unit. Lines like <b>Subject:</b>, <b>Code:</b>, <b>L-T-P:</b> fill the boxes below.
              </span>
            </div>
            <div className="adm-field">
              <label htmlFor="sy_title">Subject name</label>
              <input id="sy_title" className="adm-input" value={form.title} onChange={(e) => set('title', e.target.value)} maxLength={120} />
            </div>
            <div className="adm-field">
              <label htmlFor="sy_sem">Semester</label>
              <select id="sy_sem" className="adm-select" value={form.sem} onChange={(e) => set('sem', e.target.value)}>
                {SEMESTERS.map((n) => (
                  <option key={n} value={n}>{semesterName(n)}</option>
                ))}
              </select>
            </div>
            <div className="adm-field">
              <label htmlFor="sy_code">Subject code (optional)</label>
              <input id="sy_code" className="adm-input" value={form.code} onChange={(e) => set('code', e.target.value)} maxLength={30} />
            </div>
            <div className="adm-field">
              <label htmlFor="sy_ltp">L-T-P (optional)</label>
              <input id="sy_ltp" className="adm-input" value={form.ltp} onChange={(e) => set('ltp', e.target.value)} placeholder="3-1-0" maxLength={20} />
            </div>
            <div className="adm-field full">
              <label>Which branches</label>
              <div className="adm-row" style={{ gap: 6, flexWrap: 'wrap' }}>
                <button type="button" className={`adm-btn small ${form.branches === 'all' ? '' : 'ghost'}`} onClick={() => set('branches', 'all')}>
                  All branches (common)
                </button>
                {BRANCHES.map((b) => (
                  <button key={b.id} type="button" className={`adm-btn small ${form.branches !== 'all' && form.branches.includes(b.id) ? '' : 'ghost'}`} onClick={() => toggleBranch(b.id)}>
                    {b.title}
                  </button>
                ))}
              </div>
              <span className="adm-hint">Pick one or more branches, or "All branches" for a common subject.</span>
            </div>
            <div className="adm-field full">
              <label>Icon</label>
              <div className="adm-row" style={{ gap: 6, flexWrap: 'wrap' }}>
                {ICONS.map((n) => (
                  <button key={n} type="button" aria-label={n} aria-pressed={form.icon === n} className={`adm-btn small ${form.icon === n ? '' : 'ghost'}`} onClick={() => set('icon', n)}>
                    <ion-icon name={n} style={{ fontSize: 18 }}></ion-icon>
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="adm-card" style={{ marginTop: 16 }}>
          <div className="adm-section-head">
            <h4>Preview</h4>
            <span className="adm-hint">{parsed.units.length ? `${parsed.units.length} units · ${countTopics(parsed.units)} topics` : 'Paste the syllabus above to see it here.'}</span>
          </div>
          {parsed.units.map((u, i) => (
            <div key={i} style={{ marginBottom: 12 }}>
              <b>Unit {i + 1}: {u.title}</b>
              {u.sections.map((s, j) => (
                <div key={j} style={{ marginLeft: 12 }}>
                  {s.heading && <div className="adm-muted" style={{ fontWeight: 700, marginTop: 4 }}>{s.heading}</div>}
                  <ul style={{ margin: '4px 0 0', paddingLeft: 18 }}>
                    {s.topics.map((t, k) => (
                      <li key={k} style={{ fontSize: '0.86rem' }}>{t}</li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          ))}
        </div>

        <div className="adm-row end" style={{ marginTop: 16 }}>
          <button className="adm-btn good" disabled={saving || !parsed.units.length} onClick={save}>
            {saving ? 'Uploading…' : form.id ? 'Save changes' : 'Upload to website'}
          </button>
        </div>
      </>
    );
  }

  /* ---------------- the list ---------------- */
  const list = data.subjects || [];
  const branchLabel = (b) => (b === 'all' ? 'All branches' : b.map((id) => BRANCHES.find((x) => x.id === id)?.title || id).join(', '));
  return (
    <>
      <div className="sya-top">
        <div>
          <p>
            {list.length
              ? `${list.length} subject${list.length === 1 ? '' : 's'} live on the website, grouped by semester.`
              : "Subjects you add here show on the website's Diploma Syllabus page."}
          </p>
        </div>
        <div className="sya-actions">
          <button className="adm-btn" onClick={() => setBulk(true)}>+ Add many</button>
          <button className="adm-btn good" onClick={() => setForm({ ...EMPTY })}>+ Add subject</button>
        </div>
      </div>

      {!list.length ? (
        <Empty text='No subject uploaded yet. Click "Add subject" or "Add many" and paste a syllabus.' />
      ) : (
        SEMESTERS.map((n) => {
          const subs = list.filter((s) => s.sem === n);
          if (!subs.length) return null;
          return (
            <section key={n} className="sya-sem">
              <header className="sya-sem-head">
                <span className="sya-sem-no">{n}</span>
                <b>{semesterName(n)}</b>
                <span className="sya-sem-count">{subs.length} subject{subs.length === 1 ? '' : 's'}</span>
              </header>
              <div className="sya-cards">
                {subs.map((s) => (
                  <article key={s.id} className="sya-card">
                    <span className="sya-ico">
                      <ion-icon name={s.icon || 'book-outline'}></ion-icon>
                    </span>
                    <div className="sya-info">
                      <b>{s.title}</b>
                      <div className="sya-chips">
                        <span>{s.units.length} unit{s.units.length === 1 ? '' : 's'}</span>
                        <span>{countTopics(s.units)} topic{countTopics(s.units) === 1 ? '' : 's'}</span>
                        <span>{branchLabel(s.branches)}</span>
                        {s.code ? <span>Code {s.code}</span> : null}
                      </div>
                    </div>
                    <div className="sya-btns">
                      <button className="adm-btn small" onClick={() => edit(s)}>Edit</button>
                      <button className="adm-btn danger small" onClick={() => remove(s)}>Delete</button>
                    </div>
                  </article>
                ))}
              </div>
            </section>
          );
        })
      )}
    </>
  );
}
