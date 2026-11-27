/**
 * Add MANY syllabus subjects at once (used from the Syllabus tab).
 * Paste one big text with every subject. The subjects are found automatically (see parseSyllabusBulk in
 * lib/syllabusParse.js), shown in a list where each can be checked, renamed or moved to another semester, and
 * all of them are uploaded together. A subject with the same name in the same semester is updated, not copied.
 */
import React, { useMemo, useState } from 'react';
import { adminPost } from '../api';
import { useUi } from '../ui';
import { BRANCHES, SEMESTERS, semesterName } from '../../data/syllabus';
import { loadUploadedSyllabus } from '../../lib/syllabusStore';
import { countTopics, parseSyllabusBulk } from '../../lib/syllabusParse';

const ICON_BY_WORD = [
  [/chem/i, 'flask-outline'],
  [/math|calcul|algebra|statistic/i, 'calculator-outline'],
  [/program|software|computer|data|web|python|java|c\+\+/i, 'code-slash-outline'],
  [/electr|circuit|power/i, 'flash-outline'],
  [/electron|digital|micro|embedded/i, 'hardware-chip-outline'],
  [/mechan|machine|thermo|manufactur/i, 'cog-outline'],
  [/civil|survey|construct|concrete|struct/i, 'business-outline'],
  [/network|communicat|signal/i, 'radio-outline'],
];
const iconFor = (title) => (ICON_BY_WORD.find(([re]) => re.test(title)) || [null, 'book-outline'])[1];

/** The prompt the admin copies into ChatGPT / Claude together with the raw syllabus of many subjects. */
const AI_PROMPT = `Convert the syllabus of the subjects I give below into PLAIN TEXT in exactly this format. Output only the text, inside one code block, nothing else.

FORMAT RULES
1. Write the semester on its own line before its subjects: Semester: 2   (it applies to every subject after it, until the next Semester line)
2. Every subject starts with these lines: Subject: <full subject name>, Code: <subject code>, L-T-P: <like 3-1-0>, Branches: <All, or the branch names separated by commas>
3. Every unit starts on its own line as: Unit <number>: <unit title>   (numbers start again from 1 in every subject)
4. Under a unit, write every topic on its OWN line. One topic = one line. No bullets, no numbering, no bold, no tables.
5. If a unit has sub-headings, write the sub-heading on its own line starting with # (example: # Valency), then its topics under it.
6. Split long paragraphs into separate short topics (split at commas, semicolons or full stops). Keep the original wording; do not add or remove content.
7. Put a line of ---- between two subjects.

EXAMPLE OUTPUT
Semester: 1
Subject: Engineering Chemistry
Code: 2101
L-T-P: 3-1-0
Branches: All

Unit 1: Atomic Structure
Definition of Atom
Isotopes and Isobars
# Valency
Electrovalency and Covalency

Unit 2: Electrochemistry
Definition of Ionisation
----
Semester: 2
Subject: Applied Mathematics
Code: 2201
L-T-P: 3-1-0
Branches: CSE, Mechanical

Unit 1: Matrices
Types of matrices
Determinant

SYLLABUS (paste below this line)
`;

export default function SyllabusBulk({ existing, onClose, onDone }) {
  const { toast } = useUi();
  const [text, setText] = useState('');
  const [rows, setRows] = useState(null); // detected subjects, editable
  const [saving, setSaving] = useState(false);
  const [progress, setProgress] = useState('');

  const copyPrompt = async () => {
    try {
      await navigator.clipboard.writeText(AI_PROMPT);
      toast('Prompt copied. Paste it in ChatGPT / Claude with your syllabus.');
    } catch {
      toast('Could not copy. Select the prompt text and copy it.', 'error');
    }
  };

  const find = () => {
    const found = parseSyllabusBulk(text).map((b, i) => {
      const same = existing.find((e) => b.title && e.title.trim().toLowerCase() === b.title.trim().toLowerCase() && Number(e.sem) === Number(b.sem || 1));
      return { ...b, key: i, on: true, sem: b.sem || 1, id: same ? same.id : '', icon: same?.icon || iconFor(b.title), updates: !!same };
    });
    setRows(found);
    if (!found.length) toast('No subjects found. Check that units start with "Unit 1: Title".', 'error');
  };

  const patch = (key, p) => setRows((rs) => rs.map((r) => (r.key === key ? { ...r, ...p } : r)));

  const chosen = (rows || []).filter((r) => r.on);
  const unnamed = chosen.filter((r) => !r.title.trim()).length;

  const uploadAll = async () => {
    if (!chosen.length) return toast('Select at least one subject.', 'error');
    if (unnamed) return toast(`${unnamed} subject(s) have no name. Write the name in the red box.`, 'error');
    setSaving(true);
    let ok = 0;
    try {
      for (const r of chosen) {
        setProgress(`Uploading ${ok + 1} of ${chosen.length}: ${r.title}`);
        await adminPost('syllabus.php', {
          action: 'save',
          subject: { id: r.id, title: r.title, sem: Number(r.sem), branches: r.branches, code: r.code, ltp: r.ltp, icon: r.icon, units: r.units },
        });
        ok += 1;
      }
      toast(`${ok} subject${ok === 1 ? '' : 's'} uploaded. Live on the website.`);
      loadUploadedSyllabus(true);
      onDone();
    } catch (e) {
      toast(`${ok} uploaded, then an error: ${e.message}`, 'error');
      if (ok) {
        loadUploadedSyllabus(true);
        onDone(true); // refresh the list but stay here
      }
    } finally {
      setSaving(false);
      setProgress('');
    }
  };

  const branchText = (b) => (b === 'all' ? 'All branches' : b.map((id) => BRANCHES.find((x) => x.id === id)?.title || id).join(', '));

  const total = useMemo(() => (rows || []).filter((r) => r.on).reduce((n, r) => n + countTopics(r.units), 0), [rows]);

  return (
    <>
      <div className="adm-row between" style={{ marginBottom: 12 }}>
        <b style={{ fontSize: '1.05rem' }}>Add many subjects</b>
        <button className="adm-btn ghost" onClick={onClose}>Back</button>
      </div>

      <div className="adm-card" style={{ marginBottom: 16 }}>
        <div className="adm-section-head">
          <h4>Prompt for ChatGPT / Claude</h4>
          <button type="button" className="adm-btn small" onClick={copyPrompt}>Copy prompt</button>
        </div>
        <span className="adm-hint">Copy this prompt, paste it in an AI chat with the syllabus of all your subjects under it, then paste the answer below.</span>
        <pre style={{ margin: '8px 0 0', maxHeight: 160, overflow: 'auto', whiteSpace: 'pre-wrap', fontSize: '0.78rem', background: '#f6f6f8', padding: 10, borderRadius: 10 }}>{AI_PROMPT}</pre>
      </div>

      <div className="adm-card">
        <div className="adm-field full">
          <label htmlFor="sb_text">Syllabus text of all subjects</label>
          <textarea
            id="sb_text"
            className="adm-textarea"
            rows={14}
            value={text}
            onChange={(e) => {
              setText(e.target.value);
              setRows(null);
            }}
            placeholder={'Semester: 1\nSubject: Engineering Chemistry\nUnit 1: Atomic Structure\nDefinition of Atom\n----\nSubject: Applied Maths\nUnit 1: Matrices\nDeterminant'}
            spellCheck={false}
            style={{ fontFamily: 'ui-monospace, Consolas, monospace', fontSize: '0.82rem' }}
          />
          <span className="adm-hint">
            A new subject is found at every <b>Subject:</b> line, a line of <b>----</b>, or when <b>Unit 1</b> comes again. <b>Semester: 2</b> on its own line sets the semester of the subjects after it. <b>Branches: CSE, Mechanical</b> sets the branches.
          </span>
        </div>
        <div className="adm-row end">
          <button className="adm-btn" disabled={!text.trim()} onClick={find}>Find subjects</button>
        </div>
      </div>

      {rows && rows.length > 0 && (
        <div className="adm-card" style={{ marginTop: 16 }}>
          <div className="adm-section-head">
            <h4>{rows.length} subjects found</h4>
            <span className="adm-hint">
              {chosen.length} selected · {total} topics. Check the name and semester of each one.
            </span>
          </div>
          {rows.map((r) => (
            <div key={r.key} style={{ display: 'grid', gridTemplateColumns: 'auto 1fr', gap: 10, padding: '10px 0', borderTop: '1px solid #ececef', opacity: r.on ? 1 : 0.5 }}>
              <input type="checkbox" checked={r.on} onChange={(e) => patch(r.key, { on: e.target.checked })} aria-label="Upload this subject" style={{ marginTop: 10 }} />
              <div>
                <div className="adm-row" style={{ gap: 8, flexWrap: 'wrap' }}>
                  <input
                    className="adm-input"
                    style={{ flex: '1 1 200px', borderColor: r.on && !r.title.trim() ? '#ef4444' : undefined }}
                    value={r.title}
                    placeholder="Write the subject name"
                    onChange={(e) => patch(r.key, { title: e.target.value })}
                    maxLength={120}
                  />
                  <select className="adm-select" style={{ width: 150 }} value={r.sem} onChange={(e) => patch(r.key, { sem: e.target.value })}>
                    {SEMESTERS.map((n) => (
                      <option key={n} value={n}>{semesterName(n)}</option>
                    ))}
                  </select>
                </div>
                <div className="adm-hint" style={{ marginTop: 4 }}>
                  {r.units.length} units · {countTopics(r.units)} topics · {branchText(r.branches)}
                  {r.code ? ` · Code ${r.code}` : ''}
                  {r.ltp ? ` · L-T-P ${r.ltp}` : ''}
                  {r.updates && <span className="adm-chip" style={{ marginLeft: 6 }}>already uploaded: will be updated</span>}
                </div>
              </div>
            </div>
          ))}
          <div className="adm-row end" style={{ marginTop: 12 }}>
            {progress && <span className="adm-muted">{progress}</span>}
            <button className="adm-btn good" disabled={saving || !chosen.length} onClick={uploadAll}>
              {saving ? 'Uploading…' : `Upload ${chosen.length} subject${chosen.length === 1 ? '' : 's'}`}
            </button>
          </div>
        </div>
      )}
    </>
  );
}
