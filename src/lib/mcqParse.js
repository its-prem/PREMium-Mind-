/**
 * Turns pasted MCQ text into a test (and back). One test per paste.
 *
 *   Test: Basic Networking        (optional)
 *   Time: 10                      (optional, minutes)
 *
 *   Q1. Which device connects networks?
 *   A) Router
 *   B) Mouse
 *   C) Monitor
 *   D) Keyboard
 *   Answer: A
 *   Explanation: A router connects networks.     (optional)
 *
 * A question starts with "Q1." / "1." / "1)". Options start with A) B) C) D) (A. or (A) also work).
 * The answer is a letter (A-F) or a number (1-6). To change the format, change only this file.
 */

const LETTERS = 'ABCDEF';
const Q_RE = /^\s*(?:q(?:uestion)?\.?\s*)?(\d+)\s*[.):-]\s*(.+)$/i;
const OPT_RE = /^\s*\(?([A-Fa-f])[.):]\s*(.*)$/;
const ANS_RE = /^\s*(?:answer|ans|correct(?:\s*answer)?)\s*[:=-]\s*\(?([A-Fa-f1-6])\)?/i;
const WHY_RE = /^\s*(?:explanation|exp|why|reason)\s*[:=-]\s*(.*)$/i;
const META_RE = /^\s*(test|title|time|minutes|duration)\s*[:=]\s*(.+)$/i;

export function parseMcqText(text) {
  const meta = {};
  const questions = [];
  let cur = null;
  let last = null; // what a wrapped line is added to: 'q' | option index | 'why'

  String(text || '')
    .split(/\r?\n/)
    .forEach((raw) => {
      const line = raw.replace(/\s+/g, ' ').trim();
      if (!line) return;

      if (!cur) {
        const m = line.match(META_RE);
        if (m) {
          const k = m[1].toLowerCase();
          if (k === 'test' || k === 'title') meta.title = m[2].trim();
          else {
            const n = parseInt(m[2], 10);
            if (n >= 1 && n <= 300) meta.minutes = n;
          }
          return;
        }
      }

      const a = cur && line.match(ANS_RE);
      if (a) {
        const v = a[1].toUpperCase();
        cur.answer = /\d/.test(v) ? Number(v) - 1 : LETTERS.indexOf(v);
        last = null;
        return;
      }
      const w = cur && line.match(WHY_RE);
      if (w) {
        cur.why = w[1].trim();
        last = 'why';
        return;
      }
      const o = cur && line.match(OPT_RE);
      if (o && LETTERS.indexOf(o[1].toUpperCase()) === cur.options.length) {
        cur.options.push(o[2].trim());
        last = cur.options.length - 1;
        return;
      }
      const q = line.match(Q_RE);
      if (q) {
        cur = { q: q[2].trim(), options: [], answer: -1, why: '' };
        questions.push(cur);
        last = 'q';
        return;
      }
      // a wrapped line: add it to the part before it
      if (cur) {
        if (last === 'q') cur.q += ' ' + line;
        else if (last === 'why') cur.why += ' ' + line;
        else if (typeof last === 'number') cur.options[last] += ' ' + line;
      }
    });

  questions.forEach((x) => {
    x.error = '';
    if (!x.q) x.error = 'question text missing';
    else if (x.options.length < 2) x.error = 'needs at least 2 options (A) B) ...)';
    else if (x.answer < 0 || x.answer >= x.options.length) x.error = 'answer missing or not one of the options';
  });
  return { meta, questions };
}

/** Saved test -> text, so it can be edited in the same box. */
export function questionsToText(questions) {
  return (questions || [])
    .map((q, i) => {
      const lines = [`Q${i + 1}. ${q.q}`];
      q.options.forEach((o, oi) => lines.push(`${LETTERS[oi]}) ${o}`));
      lines.push(`Answer: ${LETTERS[q.answer]}`);
      if (q.why) lines.push(`Explanation: ${q.why}`);
      return lines.join('\n');
    })
    .join('\n\n');
}
