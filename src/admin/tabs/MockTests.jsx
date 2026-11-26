/** MCQ mock tests: paste questions as text, check the preview, upload. They appear on the website's Mock Tests page. */
import React, { useMemo, useState } from 'react';
import { adminGet, adminPost } from '../api';
import { Empty, ErrorBox, Loading, useLoad, useUi } from '../ui';
import { loadUploadedMocks } from '../../lib/mockStore';
import { parseMcqText, questionsToText } from '../../lib/mcqParse';

const LETTERS = 'ABCDEF';
const ICONS = ['desktop-outline', 'code-slash-outline', 'flash-outline', 'hardware-chip-outline', 'calculator-outline', 'flask-outline', 'cog-outline', 'construct-outline', 'business-outline', 'radio-outline', 'book-outline', 'school-outline', 'document-text-outline', 'create-outline'];
const TINTS = ['#38bdf8', '#a78bfa', '#fbbf24', '#34d399', '#fb923c', '#f472b6', '#f87171', '#2dd4bf'];

const EXAMPLE = `Test: Basic Networking
Time: 10

Q1. Which device connects two networks?
A) Router
B) Mouse
C) Monitor
D) Keyboard
Answer: A
Explanation: A router connects different networks.

Q2. IP stands for:
A) Internet Protocol
B) Internal Program
C) Input Port
D) Index Page
Answer: A`;

/** The prompt the admin copies into ChatGPT / Claude with the raw questions to get text in the right format. */
const AI_PROMPT = `Convert the MCQ questions I give below into PLAIN TEXT in exactly this format. Output only the text, inside one code block, nothing else.

FORMAT RULES
1. First lines: Test: <test name>, Time: <minutes for the whole test>
2. Then a blank line.
3. Every question starts as: Q1. <question text>  (Q2., Q3., ... in order)
4. Next 4 lines are the options: A) ... B) ... C) ... D) ...  (one option per line)
5. Next line: Answer: <A, B, C or D>  (the correct option letter only)
6. Next line (optional but preferred): Explanation: <one short line why it is correct>
7. One blank line between questions. No bold, no tables, no extra text.
8. Do not change the meaning of the questions. If the answer is not given, work out the correct one.

EXAMPLE OUTPUT
Test: Basic Networking
Time: 10

Q1. Which device connects two networks?
A) Router
B) Mouse
C) Monitor
D) Keyboard
Answer: A
Explanation: A router connects different networks.

MCQ QUESTIONS (paste below this line)
`;

const EMPTY = { id: '', title: '', minutes: 10, icon: 'document-text-outline', tint: TINTS[0], text: '' };

export default function MockTestsTab() {
  const { toast, confirm } = useUi();
  const { data, error, loading, reload } = useLoad(() => adminGet('mocktests.php'));
  const [form, setForm] = useState(null); // null = list, object = editing
  const [saving, setSaving] = useState(false);

  const parsed = useMemo(() => (form ? parseMcqText(form.text) : null), [form]);
  const bad = parsed ? parsed.questions.filter((q) => q.error).length : 0;
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const copyPrompt = async () => {
    try {
      await navigator.clipboard.writeText(AI_PROMPT);
      toast('Prompt copied. Paste it in ChatGPT / Claude with your questions.');
    } catch {
      toast('Could not copy. Select the prompt text and copy it.', 'error');
    }
  };

  // pasted text may carry Test: / Time: lines: fill the empty boxes from them
  const onText = (text) => {
    const { meta } = parseMcqText(text);
    setForm((f) => ({ ...f, text, title: f.title || meta.title || '', minutes: meta.minutes && !f.id && Number(f.minutes) === 10 ? meta.minutes : f.minutes }));
  };

  const save = async () => {
    if (!form.title.trim()) return toast('Write the test name.', 'error');
    if (!parsed.questions.length) return toast('No questions found. Start each with "Q1."', 'error');
    if (bad) return toast(`${bad} question(s) have a problem. Fix the red ones in the preview.`, 'error');
    setSaving(true);
    try {
      await adminPost('mocktests.php', {
        action: 'save',
        test: {
          id: form.id,
          title: form.title,
          minutes: Number(form.minutes),
          icon: form.icon,
          tint: form.tint,
          questions: parsed.questions.map((q) => ({ q: q.q, options: q.options, answer: q.answer, why: q.why })),
        },
      });
      toast('Uploaded. It is live on the website.');
      loadUploadedMocks(true);
      setForm(null);
      reload();
    } catch (e) {
      toast(e.message, 'error');
    } finally {
      setSaving(false);
    }
  };

  const remove = async (t) => {
    if (!(await confirm({ title: `Delete "${t.title}"?`, text: 'It disappears from the website Mock Tests page.', confirmText: 'Delete', danger: true }))) return;
    try {
      await adminPost('mocktests.php', { action: 'delete', id: t.id });
      toast('Deleted');
      loadUploadedMocks(true);
      reload();
    } catch (e) {
      toast(e.message, 'error');
    }
  };

  const edit = (t) => setForm({ id: t.id, title: t.title, minutes: t.minutes, icon: t.icon || EMPTY.icon, tint: t.tint || TINTS[0], text: questionsToText(t.questions) });

  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;

  /* ---------------- the add / edit screen ---------------- */
  if (form) {
    return (
      <>
        <div className="adm-row between" style={{ marginBottom: 12 }}>
          <b style={{ fontSize: '1.05rem' }}>{form.id ? 'Edit test' : 'Add an MCQ test'}</b>
          <button className="adm-btn ghost" onClick={() => setForm(null)}>Back</button>
        </div>

        <div className="adm-card" style={{ marginBottom: 16 }}>
          <div className="adm-section-head">
            <h4>Prompt for ChatGPT / Claude</h4>
            <button type="button" className="adm-btn small" onClick={copyPrompt}>Copy prompt</button>
          </div>
          <span className="adm-hint">Copy this prompt, paste it in an AI chat with your raw questions under it, then paste the answer in the box below.</span>
          <pre style={{ margin: '8px 0 0', maxHeight: 180, overflow: 'auto', whiteSpace: 'pre-wrap', fontSize: '0.78rem', background: '#f6f6f8', padding: 10, borderRadius: 10 }}>{AI_PROMPT}</pre>
        </div>

        <div className="adm-card">
          <div className="adm-form-grid">
            <div className="adm-field full">
              <label htmlFor="mq_text">Questions text</label>
              <textarea id="mq_text" className="adm-textarea" rows={14} value={form.text} onChange={(e) => onText(e.target.value)} placeholder={EXAMPLE} spellCheck={false} style={{ fontFamily: 'ui-monospace, Consolas, monospace', fontSize: '0.82rem' }} />
              <span className="adm-hint">
                Each question: <b>Q1. text</b>, options <b>A) B) C) D)</b> one per line, then <b>Answer: B</b> and optional <b>Explanation: ...</b>. Lines <b>Test:</b> and <b>Time:</b> at the top fill the boxes below.
              </span>
            </div>
            <div className="adm-field">
              <label htmlFor="mq_title">Test name</label>
              <input id="mq_title" className="adm-input" value={form.title} onChange={(e) => set('title', e.target.value)} maxLength={120} />
            </div>
            <div className="adm-field">
              <label htmlFor="mq_min">Time (minutes)</label>
              <input id="mq_min" className="adm-input" type="number" min={1} max={300} value={form.minutes} onChange={(e) => set('minutes', e.target.value)} />
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
            <div className="adm-field full">
              <label>Icon colour</label>
              <div className="adm-row" style={{ gap: 8, flexWrap: 'wrap' }}>
                {TINTS.map((c) => (
                  <button key={c} type="button" aria-label={c} aria-pressed={form.tint === c} onClick={() => set('tint', c)} style={{ width: 30, height: 30, borderRadius: '50%', background: c, border: form.tint === c ? '3px solid #09090b' : '2px solid #e4e4e7', cursor: 'pointer' }} />
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="adm-card" style={{ marginTop: 16 }}>
          <div className="adm-section-head">
            <h4>Preview</h4>
            <span className="adm-hint">
              {parsed.questions.length ? `${parsed.questions.length} questions${bad ? ` · ${bad} with a problem` : ' · all good'}` : 'Paste the questions above to see them here.'}
            </span>
          </div>
          {parsed.questions.map((q, i) => (
            <div key={i} style={{ marginBottom: 12, padding: 10, borderRadius: 10, border: `1px solid ${q.error ? '#fca5a5' : '#e4e4e7'}`, background: q.error ? '#fef2f2' : '#fff' }}>
              <b>{i + 1}. {q.q}</b>
              <div style={{ marginTop: 4 }}>
                {q.options.map((o, oi) => (
                  <div key={oi} style={{ fontSize: '0.86rem', color: oi === q.answer ? '#166534' : '#3f3f46', fontWeight: oi === q.answer ? 800 : 500 }}>
                    {LETTERS[oi]}) {o} {oi === q.answer ? '✓' : ''}
                  </div>
                ))}
              </div>
              {q.why && <div className="adm-muted" style={{ marginTop: 4 }}>Why: {q.why}</div>}
              {q.error && <div style={{ marginTop: 4, color: '#b91c1c', fontWeight: 700, fontSize: '0.8rem' }}>Problem: {q.error}</div>}
            </div>
          ))}
        </div>

        <div className="adm-row end" style={{ marginTop: 16 }}>
          <button className="adm-btn good" disabled={saving || !parsed.questions.length || bad > 0} onClick={save}>
            {saving ? 'Uploading…' : form.id ? 'Save changes' : 'Upload to website'}
          </button>
        </div>
      </>
    );
  }

  /* ---------------- the list ---------------- */
  const list = data.tests || [];
  return (
    <>
      <div className="adm-row between" style={{ marginBottom: 12 }}>
        <span className="adm-muted">Tests you upload here show on the website's Mock Tests page, after the built-in ones.</span>
        <button className="adm-btn good" onClick={() => setForm({ ...EMPTY })}>+ Add test</button>
      </div>
      {!list.length ? (
        <Empty text='No test uploaded yet. Click "Add test" and paste your MCQs.' />
      ) : (
        <div className="adm-card">
          {list.map((t) => (
            <div key={t.id} className="adm-flagrow">
              <div>
                <b>{t.title}</b>
                <span className="adm-hint">
                  {t.questions.length} questions · {t.minutes} min
                </span>
              </div>
              <div className="adm-row" style={{ flexWrap: 'nowrap' }}>
                <button className="adm-btn small" onClick={() => edit(t)}>Edit</button>
                <button className="adm-btn danger small" onClick={() => remove(t)}>Delete</button>
              </div>
            </div>
          ))}
        </div>
      )}
    </>
  );
}
