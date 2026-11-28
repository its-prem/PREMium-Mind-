/**
 * Makes src/styles/site-dark.css = the NIGHT MODE of the public website.
 *
 *     node scripts/make-site-dark.mjs
 *
 * It reads the website's CSS files (the list FILES below), finds every colour written directly in them and writes the
 * same rule again with a dark colour, under  html[data-theme="dark"] :
 *   - light backgrounds (white, pale grey, pale pink ...)  -> dark backgrounds of the same hue
 *   - dark text                                             -> light text
 *   - pale borders                                          -> dark borders
 *   - bright colours (the red, gold, green ...)             -> untouched
 * The colours that come from variables (src/styles/theme.css) are changed at the top of the generated file.
 * Run it again after you change any of those CSS files, and commit both. The switch is the moon button in the menu
 * (Sidebar.jsx); it sets  data-theme  on <html> (src/lib/siteTheme.js).
 */
import fs from 'node:fs';
import postcss from 'postcss';

const FILES = [
  'src/index.css', 'src/App.css', 'src/components/Navbar.css', 'src/components/Sidebar.css', 'src/components/Footer.css',
  'src/components/Store.css', 'src/components/ProductCard.css', 'src/pages/Login.css', 'src/pages/Lecture.css',
  'src/pages/Tools.css', 'src/pages/Syllabus.css', 'src/pages/Terms.css', 'src/pages/AppDownload.css', 'src/pages/PaymentReturn.css', 'src/pages/Profile.css',
];
const OUT = 'src/styles/site-dark.css';
const PREFIX = 'html[data-theme="dark"] ';

/* ---------- colours ---------- */
const NAMED = { white: [255, 255, 255], black: [0, 0, 0] };
const parse = (tok) => {
  const t = tok.trim().toLowerCase();
  if (NAMED[t]) return { rgb: NAMED[t], a: 1 };
  let m = t.match(/^#([0-9a-f]{3,8})$/);
  if (m) {
    let h = m[1];
    if (h.length === 3 || h.length === 4) h = [...h].map((x) => x + x).join('');
    if (h.length !== 6 && h.length !== 8) return null;
    return { rgb: [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16)), a: h.length === 8 ? parseInt(h.slice(6, 8), 16) / 255 : 1 };
  }
  m = t.match(/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)(?:[\s,/]+([\d.]+))?\s*\)$/);
  if (m) return { rgb: [+m[1], +m[2], +m[3]], a: m[4] === undefined ? 1 : +m[4] };
  return null;
};
const toHsl = ([r, g, b]) => {
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  const l = (max + min) / 2;
  let h = 0, s = 0;
  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
    h /= 6;
  }
  return [h, s, l];
};
const fromHsl = ([h, s, l]) => {
  const f = (p, q, t) => {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1 / 6) return p + (q - p) * 6 * t;
    if (t < 1 / 2) return q;
    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
    return p;
  };
  if (s === 0) return [l, l, l].map((x) => Math.round(x * 255));
  const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
  const p = 2 * l - q;
  return [f(p, q, h + 1 / 3), f(p, q, h), f(p, q, h - 1 / 3)].map((x) => Math.round(x * 255));
};
const out = (rgb, a) => (a < 1 ? `rgba(${rgb.join(',')},${+a.toFixed(3)})` : '#' + rgb.map((x) => x.toString(16).padStart(2, '0')).join(''));

/** the dark version of one colour, depending on where it is used. null = leave it */
function darken(c, kind) {
  const [h, s, l] = toHsl(c.rgb);
  if (kind === 'bg') {
    if (l < 0.78) return null; // dark or bright colour: keep
    if (c.a < 1 && l > 0.97) return out([22, 18, 20], c.a); // glass white -> dark glass
    const nl = 0.075 + (1 - l) * 0.55; // very light -> very dark, pale grey -> a bit lighter dark
    return out(fromHsl([h, s * 0.55, nl]), c.a);
  }
  if (kind === 'text') {
    if (l > 0.4) return null;
    const nl = 0.97 - l * 0.55;
    return out(fromHsl([h, Math.min(s, 0.25), nl]), c.a);
  }
  if (kind === 'border') {
    if (l < 0.7) return null;
    return out(fromHsl([h, s * 0.4, 0.2 + (1 - l) * 0.5]), c.a);
  }
  return null;
}

const COLOR_RE = /#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|\bwhite\b/g;
function swap(value, kind) {
  let changed = false;
  // never touch the inside of var(--name) or url(...): only real colours
  const parts = value.split(/(var\([^)]*\)|url\([^)]*\))/g);
  const next = parts
    .map((part) => {
      if (/^(var|url)\(/.test(part)) return part;
      return part.replace(COLOR_RE, (tok) => {
        const c = parse(tok);
        if (!c) return tok;
        const d = darken(c, kind);
        if (d === null) return tok;
        changed = true;
        return d;
      });
    })
    .join('');
  return changed ? next : null;
}

const kindOf = (prop) => {
  const p = prop.toLowerCase();
  if (/^background(-color|-image)?$/.test(p)) return 'bg';
  if (p === 'color' || p === 'fill' || p === 'stroke' || p === '-webkit-text-fill-color') return 'text';
  if (/^border(-top|-right|-bottom|-left)?(-color)?$/.test(p) || p === 'outline' || p === 'outline-color') return 'border';
  return null;
};

/* ---------- build ---------- */
const result = postcss.root();
let blocks = 0;
for (const file of FILES) {
  if (!fs.existsSync(file)) continue;
  const root = postcss.parse(fs.readFileSync(file, 'utf8'));
  root.walkRules((rule) => {
    if (rule.parent && rule.parent.type === 'atrule' && /keyframes/i.test(rule.parent.name)) return;
    const decls = [];
    rule.walkDecls((d) => {
      const kind = kindOf(d.prop);
      if (!kind) return;
      const v = swap(d.value, kind);
      if (v !== null && v !== d.value) decls.push([d.prop, v, d.important]);
    });
    if (!decls.length) return;
    const sel = rule.selector
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean)
      .map((s) => (/^(html|:root)\b/.test(s) ? s.replace(/^(html|:root)/, 'html[data-theme="dark"]') : PREFIX + s))
      .join(',\n');
    const clone = postcss.rule({ selector: sel });
    decls.forEach(([prop, value, important]) => clone.append({ prop, value, important }));
    let holder = result;
    const chain = [];
    for (let p = rule.parent; p && p.type !== 'root'; p = p.parent) if (p.type === 'atrule') chain.unshift(p);
    chain.forEach((at) => {
      let found = holder.nodes && holder.nodes.find((n) => n.type === 'atrule' && n.name === at.name && n.params === at.params && n.__gen);
      if (!found) {
        found = postcss.atRule({ name: at.name, params: at.params });
        found.__gen = true;
        holder.append(found);
      }
      holder = found;
    });
    holder.append(clone);
    blocks += 1;
  });
}

const head = `/* GENERATED by scripts/make-site-dark.mjs - do not edit by hand. Night mode of the website. */

html[data-theme="dark"] {
  --bg-page: #0e0a0b;
  --white: #171214;
  --ink-900: #f6f1f2;
  --ink-700: #d9cfd2;
  --ink-500: #a79da0;
  --border-light: #2c2326;
  --card-tint: #1c1618;
  color-scheme: dark;
}
html[data-theme="dark"] body {
  background-color: #0e0a0b;
  color: #f6f1f2;
}
html[data-theme="dark"] input,
html[data-theme="dark"] select,
html[data-theme="dark"] textarea {
  background-color: #1b1517;
  color: #f6f1f2;
  border-color: #3a2f33;
}
html[data-theme="dark"] ::placeholder {
  color: #7d7276;
}
/* the white wave under the menu header (an SVG with its colour written inside) */
html[data-theme="dark"] .pm-sb-bottom-wave path {
  fill: #171214;
}
html[data-theme="dark"] img.pm-dim,
html[data-theme="dark"] .pm-card-thumb img {
  filter: brightness(0.92);
}

`;
fs.writeFileSync(OUT, head + result.toString().replace(/\n{3,}/g, '\n\n') + '\n');
console.log(`written ${OUT}: ${blocks} rules`);
