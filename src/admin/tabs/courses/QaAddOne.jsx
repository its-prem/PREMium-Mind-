/**
 * "Add one question" box: write one question and its answer, press the button, and it is added at the end of the
 * questions text with the next number (Q.4, Q.5 ...). Used in the product form and in the Microsheet tab.
 *
 *   <QaAddOne getText={() => text | Promise<text>} onChange={(newText) => ...} toast={toast} />
 */
import React, { useState } from 'react';

const nextNumber = (text) => {
  let max = 0;
  String(text || '').replace(/^\s*(?:Q\.?\s*|Question\s*)(\d+)/gim, (_, n) => {
    max = Math.max(max, Number(n));
    return '';
  });
  return max + 1;
};

export default function QaAddOne({ getText, onChange, toast }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const [marks, setMarks] = useState('');
  const [a, setA] = useState('');

  const add = async () => {
    const question = q.trim().replace(/\s+/g, ' ');
    const answer = a
      .split('\n')
      .map((l) => l.trim())
      .filter(Boolean);
    if (!question) return toast('Write the question.', 'error');
    if (!answer.length) return toast('Write the answer (one point on each line).', 'error');
    const base = String((await getText()) || '').trimEnd();
    const no = nextNumber(base);
    const head = `Q.${no} ${question}${marks.trim() ? ` (${marks.trim().replace(/^\(|\)$/g, '')})` : ''}`;
    onChange((base ? base + '\n\n' : '') + head + '\n' + answer.join('\n'));
    toast(`Question Q.${no} added at the end.`);
    setQ('');
    setMarks('');
    setA('');
  };

  return (
    <div className="adm-qahelp" style={{ marginTop: 8 }}>
      <button type="button" className="adm-qahelp-head" aria-expanded={open} onClick={() => setOpen((o) => !o)}>
        <b>+ Add one question</b>
        <span>{open ? 'Hide' : 'Show'}</span>
      </button>
      {open && (
        <div className="adm-qahelp-body">
          <div className="adm-field">
            <label htmlFor="qa1_q">Question</label>
            <input id="qa1_q" className="adm-input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Explain the Software Development Life Cycle." />
          </div>
          <div className="adm-field">
            <label htmlFor="qa1_m">Marks (optional)</label>
            <input id="qa1_m" className="adm-input" value={marks} onChange={(e) => setMarks(e.target.value)} placeholder="5 marks" style={{ maxWidth: 160 }} />
          </div>
          <div className="adm-field">
            <label htmlFor="qa1_a">Answer (one point on each line)</label>
            <textarea
              id="qa1_a"
              className="adm-textarea"
              rows={5}
              value={a}
              onChange={(e) => setA(e.target.value)}
              placeholder={'1. Requirement: The needs of the user are collected.\n2. Design: The structure is planned.\n3. Coding: The code is written.'}
            />
          </div>
          <div>
            <button type="button" className="adm-btn good small" onClick={add}>
              Add this question
            </button>
          </div>
          <span className="adm-hint">It is added at the end with the next number (Q.4, Q.5 …). You can still edit the whole text in the big box.</span>
        </div>
      )}
    </div>
  );
}
