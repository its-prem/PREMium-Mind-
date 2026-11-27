/**
 * Turns the syllabus text the admin pastes into units -> sections -> topics (and back).
 *
 * Text format (one item per line):
 *   Subject: Engineering Chemistry     (optional lines: Subject / Code / L-T-P / Semester)
 *   Code: 2101
 *   L-T-P: 3-1-0
 *
 *   Unit 1: Atomic Structure           <- starts a unit  ("Unit 1 Atomic Structure", "UNIT-1 - Title", "Chapter 2: ..." also work)
 *   # Fundamental ideas                <- optional heading inside the unit (a line starting with #)
 *   Definition of Atom                 <- every other line is one topic (bullets like "-", "•", "1.1" are removed)
 *   Isotopes and Isobars
 *
 * To change the format, change only this file.
 */

import { BRANCHES } from '../data/syllabus';

const UNIT_RE = /^\s*(?:unit|chapter|module)\s*[-–—.:]?\s*([0-9]+|[ivxIVX]+)\s*[:\-–—.)]*\s*(.*)$/i;
const BULLET_RE = /^\s*(?:[-–—•·▪●*]+\s+|\d+(?:\.\d+)+[.)]?\s+|\d+[.)]\s+)/;
const META_RE = /^\s*(subject|title|code|l-?t-?p|ltp|sem|semester|branch|branches)\s*[:=]\s*(.+)$/i;

const clean = (s) => s.replace(/\s+/g, ' ').trim();

export function parseSyllabusText(text) {
  const meta = {};
  const units = [];
  let unit = null;
  let section = null;

  const ensureUnit = () => {
    if (!unit) {
      unit = { title: 'Topics', sections: [] };
      units.push(unit);
    }
    return unit;
  };
  const ensureSection = (heading) => {
    const u = ensureUnit();
    section = { heading: heading || '', topics: [] };
    u.sections.push(section);
    return section;
  };

  String(text || '')
    .split(/\r?\n/)
    .forEach((raw) => {
      const line = clean(raw);
      if (!line) return;

      const m = !units.length && line.match(META_RE);
      if (m) {
        const k = m[1].toLowerCase().replace(/-/g, '');
        const v = m[2].trim();
        if (k === 'subject' || k === 'title') meta.title = v;
        else if (k === 'code') meta.code = v;
        else if (k === 'ltp') meta.ltp = v.replace(/\s+/g, '');
        else if (k === 'branch' || k === 'branches') meta.branches = parseBranches(v);
        else if (k === 'sem' || k === 'semester') {
          const n = parseInt(v, 10);
          if (n >= 1 && n <= 6) meta.sem = n;
        }
        return;
      }

      const u = line.match(UNIT_RE);
      if (u) {
        const title = (u[2] || '').trim() || `Unit ${u[1]}`;
        unit = { title, sections: [] };
        units.push(unit);
        section = null;
        return;
      }

      if (line.startsWith('#')) {
        const heading = line.replace(/^#+\s*/, '');
        if (heading) ensureSection(heading);
        return;
      }

      const topic = line.replace(BULLET_RE, '').trim();
      if (!topic) return;
      ensureUnit();
      if (!section) ensureSection('');
      section.topics.push(topic);
    });

  // drop empty units; sections without a heading keep the shape the website expects
  const out = units
    .map((x) => ({
      title: x.title,
      sections: x.sections
        .filter((s) => s.topics.length)
        .map((s) => (s.heading ? { heading: s.heading, topics: s.topics } : { topics: s.topics })),
    }))
    .filter((x) => x.sections.length);
  return { meta, units: out };
}

/** The reverse: units -> text, so a saved subject can be edited in the same text box. */
export function unitsToText(units) {
  const lines = [];
  (units || []).forEach((u, i) => {
    if (i) lines.push('');
    lines.push(`Unit ${i + 1}: ${u.title}`);
    (u.sections || []).forEach((s) => {
      if (s.heading) lines.push(`# ${s.heading}`);
      (s.topics || []).forEach((t) => lines.push(t));
    });
  });
  return lines.join('\n');
}

export const countTopics = (units) => units.reduce((n, u) => n + u.sections.reduce((m, s) => m + s.topics.length, 0), 0);

/** "CSE, Mechanical & Civil" -> ['cse','mechanical','civil'];  "all" (or nothing recognised) -> 'all' */
const ALIASES = { mech: 'mechanical', me: 'mechanical', ce: 'civil', ee: 'electrical', eee: 'electrical', cs: 'cse', computer: 'cse', auto: 'automobile', metal: 'metallurgy', mine: 'mining', electronics: 'electronics' };
export function parseBranches(text) {
  const v = String(text || '').trim().toLowerCase();
  if (!v || /^(all|common|every|sab)/.test(v)) return 'all';
  const found = [];
  v.split(/[,&/+]|\band\b/).forEach((tok) => {
    const t = tok.trim();
    if (!t) return;
    const hit = BRANCHES.find((b) => b.id === t || b.title.toLowerCase().includes(t) || b.id === ALIASES[t]);
    if (hit && !found.includes(hit.id)) found.push(hit.id);
  });
  return found.length ? found : 'all';
}

const SUBJECT_LINE = /^\s*(subject|title)\s*[:=]\s*\S/i;
const SEM_LINE = /^\s*(?:sem(?:ester)?\s*[:=-]?\s*([1-6])|([1-6])(?:st|nd|rd|th)?\s*sem(?:ester)?)\s*$/i;
const SEP_LINE = /^\s*(?:-{3,}|={3,}|\*{3,}|_{3,})\s*$/;

/**
 * Many subjects pasted in one go. A new subject starts at:
 *   - a "Subject: ..." line,
 *   - a line of ---- or ==== ,
 *   - a "Unit 1" that comes after units of the previous subject (the numbers started again).
 * A line like "Semester: 2" or "3rd Semester" on its own sets the semester of the subjects after it.
 * Returns [{ title, code, ltp, sem, branches, units }] (sem is 0 when the text does not say).
 */
export function parseSyllabusBulk(text) {
  const blocks = [];
  let cur = null;
  let carrySem = 0;

  const start = () => {
    cur = { lines: [], units: 0, sem: carrySem };
    blocks.push(cur);
    return cur;
  };

  String(text || '')
    .split(/\r?\n/)
    .forEach((raw) => {
      const line = raw.trim();
      if (!line) {
        if (cur) cur.lines.push('');
        return;
      }
      if (SEP_LINE.test(line)) {
        cur = null;
        return;
      }
      const semOnly = line.match(SEM_LINE);
      if (semOnly) {
        carrySem = Number(semOnly[1] || semOnly[2]);
        if (cur && !cur.units) cur.sem = carrySem;
        else if (cur) cur = null; // the next subject starts with this semester
        return;
      }
      if (SUBJECT_LINE.test(line)) {
        // a "Subject:" line right after the meta lines of the same subject (no units yet) stays in the same block
        if (!cur || cur.units || cur.lines.some((l) => SUBJECT_LINE.test(l))) start();
        cur.lines.push(line);
        return;
      }
      const unit = line.match(UNIT_RE);
      if (unit) {
        const no = /^\d+$/.test(unit[1]) ? Number(unit[1]) : 0;
        if (!cur) start();
        else if (cur.units && no === 1) start(); // numbers began again: the next subject
        cur.units += 1;
        cur.lines.push(line);
        return;
      }
      if (!cur) start();
      cur.lines.push(line);
    });

  return blocks
    .map((b) => {
      const { meta, units } = parseSyllabusText(b.lines.join('\n'));
      return { title: meta.title || '', code: meta.code || '', ltp: meta.ltp || '', sem: meta.sem || b.sem || 0, branches: meta.branches || 'all', units };
    })
    .filter((x) => x.units.length);
}
