/**
 * Turns the questions & answers text of an Important Questions product into cards.
 * A new question starts with Q.1, Q.2 … (also "Question 3"); every line below it is the answer.
 * Same rule as the Microsheet tool (src/microsheet/microsheet.html), so both show the same questions.
 */

const Q_HEAD = /^(Q\.?\s*\d+|Question\s*\d+)\s*[-—–:.]*\s*(.*)$/i;

export function isQuestionLine(line) {
  const t = String(line || '').trim();
  return /^Q\.\s*\d+\b/i.test(t) || /^Q\s*\d+\s*[-—–:.]/i.test(t) || /^Question\s*\d+\b/i.test(t);
}

/** "Q 7" / "Question 7" -> "Q.7" */
function niceLabel(raw) {
  const n = String(raw || '').replace(/\s+/g, '');
  if (/^Question/i.test(n)) return `Q.${n.replace(/^Question/i, '')}`;
  if (/^Q\d/i.test(n)) return `Q.${n.slice(1)}`;
  return n;
}

const clean = (s) =>
  String(s || '')
    .replace(/\r\n/g, '\n')
    .replace(/ /g, ' ')
    .replace(/[​-‍﻿]/g, '')
    .replace(/`{1,3}[\w+-]*/g, '');

/** -> [{ label: 'Q.7', title: 'Explain …', lines: ['1. Initiating: …', …] }] */
export function parseQuestions(text) {
  const out = [];
  let cur = null;
  clean(text)
    .split('\n')
    .map((l) => l.trim())
    .filter(Boolean)
    .forEach((line) => {
      if (isQuestionLine(line)) {
        const m = line.match(Q_HEAD);
        cur = { label: niceLabel(m ? m[1] : ''), title: (m ? m[2] : line).trim(), lines: [] };
        out.push(cur);
      } else if (cur) {
        cur.lines.push(line.replace(/^(Ans(?:wer)?\s*[:：]\s*)/i, ''));
      } else {
        cur = { label: '', title: line, lines: [] };
        out.push(cur);
      }
    });
  return out;
}

/** "1. Initiating: In this step…" -> { num: '1.', head: 'Initiating:', body: 'In this step…' } */
export function splitLine(line) {
  let rest = String(line || '').trim();
  let num = '';
  let head = '';
  const n = rest.match(/^(\d+\s*[.)\-:]|[ivxlcdm]+[.)]|\([ivxlcdm]+\)|\([a-z]\))\s+(.*)$/i);
  if (n) {
    num = n[1];
    rest = n[2];
  }
  const h = rest.match(/^([^:\n]{2,55}:)\s*(.*)$/);
  if (h) {
    head = h[1];
    rest = h[2];
  }
  return { num, head, body: rest };
}
