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

const UNIT_RE = /^\s*(?:unit|chapter|module)\s*[-–—.:]?\s*([0-9]+|[ivxIVX]+)\s*[:\-–—.)]*\s*(.*)$/i;
const BULLET_RE = /^\s*(?:[-–—•·▪●*]+\s+|\d+(?:\.\d+)+[.)]?\s+|\d+[.)]\s+)/;
const META_RE = /^\s*(subject|title|code|l-?t-?p|ltp|sem|semester)\s*[:=]\s*(.+)$/i;

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
