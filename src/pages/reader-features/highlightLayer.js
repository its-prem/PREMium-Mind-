/**
 * Plain-DOM drawing layer used by the Highlighter feature.
 * Every stroke is stored in page-width units (0-1), so it scales with zoom.
 */

const SVG_NS = 'http://www.w3.org/2000/svg';

/* Bright marker-pen inks */
export const COLORS = {
  yellow: '#ffe600',
  green: '#4cf06f',
  pink: '#ff5fa8',
  blue: '#3fb6ff',
};

const STYLE = {
  hl: { width: 0.034, opacity: 0.88 },
  ul: { width: 0.0045, opacity: 0.95 },
};

/** Create (once) the transparent layer that sits on top of a page. */
export function ensureLayer(pageEl) {
  let layer = pageEl.querySelector(':scope > .rdx-hl-layer');
  if (!layer) {
    layer = document.createElement('div');
    layer.className = 'rdx-hl-layer';
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('preserveAspectRatio', 'none');
    layer.appendChild(svg);
    pageEl.appendChild(layer);
  }
  return layer;
}

export function removeAllLayers(pagesEl) {
  pagesEl?.querySelectorAll('.rdx-hl-layer').forEach((el) => el.remove());
}

/** Underlines are always flat: first point to last x on the first y. */
function pointsFor(stroke) {
  const pts = stroke.points;
  if (stroke.type === 'ul') {
    return [pts[0], { x: pts[pts.length - 1].x, y: pts[0].y }];
  }
  // A single tap still leaves a small mark (chisel tips need some length).
  return pts.length === 1 ? [pts[0], { x: Math.min(1, pts[0].x + 0.03), y: pts[0].y }] : pts;
}

export function renderLayer(layer, strokes) {
  const svg = layer.firstChild;
  const rect = layer.getBoundingClientRect();
  const aspect = rect.width ? rect.height / rect.width : 1.414;
  svg.setAttribute('viewBox', `0 0 1 ${aspect}`);
  svg.replaceChildren();

  strokes.forEach((stroke) => {
    const style = STYLE[stroke.type] || STYLE.hl;
    const line = document.createElementNS(SVG_NS, 'polyline');
    line.setAttribute(
      'points',
      pointsFor(stroke)
        .map((p) => `${p.x.toFixed(4)},${p.y.toFixed(4)}`)
        .join(' ')
    );
    line.setAttribute('fill', 'none');
    line.setAttribute('stroke', stroke.color);
    line.setAttribute('stroke-width', String(style.width));
    line.setAttribute('stroke-opacity', String(style.opacity));
    // Flat (chisel) ends like a real highlighter; underlines stay rounded.
    line.setAttribute('stroke-linecap', stroke.type === 'hl' ? 'butt' : 'round');
    line.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(line);
  });
}

/** Pointer position as page-width units. */
export function toPagePoint(layer, e) {
  const rect = layer.getBoundingClientRect();
  return {
    x: Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width)),
    y: Math.max(0, (e.clientY - rect.top) / rect.width),
  };
}

function distToSegment(p, a, b) {
  const dx = b.x - a.x;
  const dy = b.y - a.y;
  const len2 = dx * dx + dy * dy;
  const t = len2 ? Math.max(0, Math.min(1, ((p.x - a.x) * dx + (p.y - a.y) * dy) / len2)) : 0;
  return Math.hypot(p.x - (a.x + t * dx), p.y - (a.y + t * dy));
}

/** Is the eraser point touching this stroke? */
export function hitsStroke(stroke, p, radius = 0.025) {
  const pts = pointsFor(stroke);
  const reach = radius + (STYLE[stroke.type] || STYLE.hl).width / 2;
  for (let i = 0; i < pts.length - 1; i += 1) {
    if (distToSegment(p, pts[i], pts[i + 1]) <= reach) return true;
  }
  return pts.length === 1 && Math.hypot(p.x - pts[0].x, p.y - pts[0].y) <= reach;
}
