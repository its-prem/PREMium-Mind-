/**
 * PDF page rendering + pinch/wheel zoom for the secure reader.
 * pdf.js is loaded on demand from cdnjs (same pinned version as the old site).
 */

const PDFJS_VERSION = '2.16.105';
const PDFJS_BASE = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDFJS_VERSION}`;

let pdfjsPromise = null;

export function loadPdfJs() {
  if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
  if (!pdfjsPromise) {
    pdfjsPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = `${PDFJS_BASE}/pdf.min.js`;
      script.async = true;
      script.onload = () => {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = `${PDFJS_BASE}/pdf.worker.min.js`;
        resolve(window.pdfjsLib);
      };
      script.onerror = () => {
        pdfjsPromise = null;
        reject(new Error('Could not load the PDF engine. Check your internet.'));
      };
      document.head.appendChild(script);
    });
  }
  return pdfjsPromise;
}

export const ZOOM_MIN = 1;
export const ZOOM_MAX = 6;

/**
 * Pinch (touch) and Ctrl+wheel zoom. Zoom is a CSS scale of the page column;
 * `onZoomEnd` is called after it settles so pages can re-render sharper.
 */
export function createZoomController({ scrollArea, zoomTarget, onZoomEnd }) {
  let scale = 1;
  let pinchStartDist = 0;
  let pinchStartScale = 1;
  let raf = null;
  let pending = null;

  const applyZoom = (next, anchorX, anchorY) => {
    const prev = scale;
    scale = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, next));
    if (Math.abs(scale - prev) < 0.001) return;
    // The point under the fingers stays under the fingers: remember where it is as a share of the whole content,
    // resize, then scroll so the same share sits under the same spot (works for centred pages too).
    const fx = (scrollArea.scrollLeft + anchorX) / Math.max(1, scrollArea.scrollWidth);
    const fy = (scrollArea.scrollTop + anchorY) / Math.max(1, scrollArea.scrollHeight);
    zoomTarget.style.setProperty('--pdf-scale', String(scale));
    scrollArea.scrollLeft = fx * scrollArea.scrollWidth - anchorX;
    scrollArea.scrollTop = fy * scrollArea.scrollHeight - anchorY;
  };

  const touchCenter = (e) => {
    const rect = scrollArea.getBoundingClientRect();
    return {
      x: (e.touches[0].clientX + e.touches[1].clientX) / 2 - rect.left,
      y: (e.touches[0].clientY + e.touches[1].clientY) / 2 - rect.top,
    };
  };
  const touchDist = (e) =>
    Math.hypot(
      e.touches[0].clientX - e.touches[1].clientX,
      e.touches[0].clientY - e.touches[1].clientY
    );

  // Double tap (or double click) toggles between normal size and a zoomed-in view at the tapped spot.
  const DOUBLE_TAP_SCALE = 2.5;
  let tapStart = null; // { x, y, t } of a one-finger touch that may become a tap
  let lastTap = null; // { x, y, t } of the previous tap

  const toggleZoomAt = (clientX, clientY) => {
    const rect = scrollArea.getBoundingClientRect();
    applyZoom(scale > 1.05 ? 1 : DOUBLE_TAP_SCALE, clientX - rect.left, clientY - rect.top);
    onZoomEnd?.();
  };
  let lastToggle = 0;

  const onTouchStart = (e) => {
    if (e.touches.length === 2) {
      pinchStartDist = touchDist(e);
      pinchStartScale = scale;
      tapStart = null;
      lastTap = null;
    } else if (e.touches.length === 1) {
      tapStart = { x: e.touches[0].clientX, y: e.touches[0].clientY, t: Date.now() };
    }
  };

  const onTouchMove = (e) => {
    if (tapStart && e.touches.length === 1) {
      // a finger that moves is scrolling, not tapping
      if (Math.hypot(e.touches[0].clientX - tapStart.x, e.touches[0].clientY - tapStart.y) > 10) tapStart = null;
    }
    if (e.touches.length !== 2 || pinchStartDist <= 0) return;
    e.preventDefault();
    const { x, y } = touchCenter(e);
    pending = {
      scale: pinchStartScale * (touchDist(e) / pinchStartDist),
      x,
      y,
    };
    // At most one layout per animation frame keeps pinching smooth.
    if (!raf) {
      raf = requestAnimationFrame(() => {
        raf = null;
        if (pending) applyZoom(pending.scale, pending.x, pending.y);
      });
    }
  };

  const onTouchEnd = (e) => {
    if (tapStart && e && e.touches && e.touches.length === 0) {
      const now = Date.now();
      if (now - tapStart.t < 300) {
        if (lastTap && now - lastTap.t < 350 && Math.hypot(tapStart.x - lastTap.x, tapStart.y - lastTap.y) < 40) {
          lastToggle = now;
          toggleZoomAt(tapStart.x, tapStart.y);
          lastTap = null;
        } else {
          lastTap = { x: tapStart.x, y: tapStart.y, t: now };
        }
      } else {
        lastTap = null;
      }
      tapStart = null;
    }
    if (pinchStartDist > 0) {
      pinchStartDist = 0;
      pending = null;
      if (raf) {
        cancelAnimationFrame(raf);
        raf = null;
      }
      onZoomEnd?.();
    }
  };

  const onWheel = (e) => {
    if (!(e.ctrlKey || e.metaKey)) return;
    e.preventDefault();
    const rect = scrollArea.getBoundingClientRect();
    applyZoom(
      scale + (e.deltaY > 0 ? -0.25 : 0.25),
      e.clientX - rect.left,
      e.clientY - rect.top
    );
    onZoomEnd?.();
  };

  // Stop the browser's own page-pinch so only our zoom runs.
  const blockPagePinch = (e) => {
    if (e.touches && e.touches.length > 1) e.preventDefault();
  };

  scrollArea.addEventListener('touchstart', onTouchStart, { passive: true });
  scrollArea.addEventListener('touchmove', onTouchMove, { passive: false });
  scrollArea.addEventListener('touchend', onTouchEnd, { passive: true });
  scrollArea.addEventListener('wheel', onWheel, { passive: false });
  const onDblClick = (e) => {
    if (Date.now() - lastToggle < 700) return; // a touch double tap already did it (phones also send a dblclick)
    toggleZoomAt(e.clientX, e.clientY); // mouse / trackpad
  };
  scrollArea.addEventListener('dblclick', onDblClick);
  document.addEventListener('touchmove', blockPagePinch, { passive: false });

  return {
    getScale: () => scale,
    destroy() {
      scrollArea.removeEventListener('touchstart', onTouchStart);
      scrollArea.removeEventListener('touchmove', onTouchMove);
      scrollArea.removeEventListener('touchend', onTouchEnd);
      scrollArea.removeEventListener('wheel', onWheel);
      scrollArea.removeEventListener('dblclick', onDblClick);
      document.removeEventListener('touchmove', blockPagePinch);
      if (raf) cancelAnimationFrame(raf);
    },
  };
}

const isNativeUa = () =>
  !!window.AndroidPdfSaver ||
  (/Android/.test(navigator.userAgent || '') &&
    (/; wv\)/.test(navigator.userAgent) || /Capacitor/i.test(navigator.userAgent)));

/**
 * Render every page of a PDF (raw bytes) into `container` as canvases.
 * Returns a cleanup function.
 */
export async function renderPdfDocument({
  data,
  container,
  scrollArea,
  zoom,
  isCancelled,
  onProgress,
  onFirstPage,
}) {
  const pdfjs = await loadPdfJs();
  const pdfDoc = await pdfjs.getDocument({ data }).promise;

  const isMobile =
    window.innerWidth < 700 || /Android|iPhone|iPad/i.test(navigator.userAgent);
  const native = isNativeUa();
  const quality = native
    ? { dpr: Math.min(window.devicePixelRatio || 1.75, 2), maxEdge: 4096 }
    : {
        dpr: Math.min(window.devicePixelRatio || 1, isMobile ? 2 : 1.75),
        maxEdge: isMobile ? 4096 : 5600,
      };

  const firstPage = await pdfDoc.getPage(1);
  const baseViewport = firstPage.getViewport({ scale: 1 });
  const entries = [];
  let fitScale = 1;

  const viewerWidth = () => {
    const pad = window.innerWidth < 700 ? 16 : 36;
    const w =
      scrollArea.clientWidth ||
      window.visualViewport?.width ||
      document.documentElement.clientWidth ||
      window.innerWidth;
    return Math.max(240, w - pad);
  };
  const computeFit = () => Math.min(900, viewerWidth()) / baseViewport.width;

  const pixelRatio = (viewport) => {
    let pr = Math.max(quality.dpr, native ? 1.5 : 1);
    const longEdge = Math.max(viewport.width, viewport.height) * pr;
    if (longEdge > quality.maxEdge) {
      pr = quality.maxEdge / Math.max(viewport.width, viewport.height);
    }
    return Math.max(pr, native ? 1.25 : 1);
  };

  const renderEntry = async (entry, renderScale) => {
    const viewport = entry.page.getViewport({ scale: renderScale });
    const pr = pixelRatio(viewport);
    const canvas = document.createElement('canvas');
    canvas.oncontextmenu = () => false;
    // Transparent canvas: the paper colour comes from the page wrapper, so
    // optional layers (highlights) can sit between the paper and the text.
    const ctx = canvas.getContext('2d');
    canvas.width = Math.floor(viewport.width * pr);
    canvas.height = Math.floor(viewport.height * pr);
    canvas.style.width = '100%';
    canvas.style.height = 'auto';
    await entry.page.render({
      canvasContext: ctx,
      transform: pr !== 1 ? [pr, 0, 0, pr, 0, 0] : null,
      viewport,
      intent: 'display',
      background: 'rgba(0,0,0,0)',
    }).promise;
    const oldCanvas = entry.wrapper.querySelector(':scope > canvas');
    if (oldCanvas) oldCanvas.replaceWith(canvas);
    else entry.wrapper.prepend(canvas);
    entry.renderedScale = renderScale;
  };

  fitScale = computeFit();
  const total = pdfDoc.numPages;
  container.dataset.total = String(total);

  const mountPage = async (num) => {
    onProgress?.(num, total);
    const page = num === 1 ? firstPage : await pdfDoc.getPage(num);
    const wrapper = document.createElement('div');
    wrapper.className = 'rd-page rd-text-canvas';
    // Reserve height so scrolling doesn't jump while later pages load.
    wrapper.style.minHeight = `${Math.round(baseViewport.height * fitScale)}px`;
    container.appendChild(wrapper);
    const entry = { page, wrapper, renderedScale: 0 };
    entries.push(entry);
    await renderEntry(entry, fitScale);
  };

  await mountPage(1);
  onFirstPage?.();
  for (let num = 2; num <= total; num += 1) {
    if (isCancelled?.()) {
      return { reRenderZoom() {}, destroy: () => pdfDoc.destroy?.() };
    }
    // eslint-disable-next-line no-await-in-loop
    await mountPage(num);
  }

  // Re-render visible pages sharper after zooming, soften the rest.
  let zoomTimer = null;
  let busy = false;
  const reRenderZoom = () => {
    clearTimeout(zoomTimer);
    zoomTimer = setTimeout(async () => {
      if (busy) return;
      busy = true;
      try {
        const target = fitScale * zoom.getScale();
        const root = scrollArea.getBoundingClientRect();
        for (const entry of entries) {
          const r = entry.wrapper.getBoundingClientRect();
          const visible = r.bottom >= root.top - 60 && r.top <= root.bottom + 60;
          if (visible) {
            if (Math.abs(entry.renderedScale - target) > 0.05) {
              // eslint-disable-next-line no-await-in-loop
              await renderEntry(entry, target);
            }
          } else if (entry.renderedScale > fitScale + 0.05) {
            // eslint-disable-next-line no-await-in-loop
            await renderEntry(entry, fitScale);
          }
        }
      } finally {
        busy = false;
      }
    }, 220);
  };

  let resizeTimer = null;
  const reflow = () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(async () => {
      if (busy) return;
      busy = true;
      try {
        const nextFit = computeFit();
        if (Math.abs(nextFit - fitScale) < 0.01) return;
        fitScale = nextFit;
        const target = fitScale * zoom.getScale();
        for (const entry of entries) {
          // eslint-disable-next-line no-await-in-loop
          await renderEntry(entry, zoom.getScale() > 1.05 ? target : fitScale);
        }
      } finally {
        busy = false;
      }
    }, 280);
  };

  const onScroll = () => {
    if (zoom.getScale() > 1.05) reRenderZoom();
  };

  window.addEventListener('resize', reflow, { passive: true });
  window.addEventListener('orientationchange', reflow, { passive: true });
  scrollArea.addEventListener('scroll', onScroll, { passive: true });

  return {
    reRenderZoom,
    destroy() {
      clearTimeout(zoomTimer);
      clearTimeout(resizeTimer);
      window.removeEventListener('resize', reflow);
      window.removeEventListener('orientationchange', reflow);
      scrollArea.removeEventListener('scroll', onScroll);
      pdfDoc.destroy?.();
    },
  };
}

/** Locked courses: each page arrives as a pre-watermarked image. */
export async function renderPageImages({
  totalPages,
  fetchPage,
  container,
  isCancelled,
  onProgress,
  onFirstPage,
}) {
  container.dataset.total = String(totalPages);
  const mount = (bitmap) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'rd-page';
    const canvas = document.createElement('canvas');
    canvas.oncontextmenu = () => false;
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    canvas.style.width = '100%';
    canvas.style.height = 'auto';
    canvas.getContext('2d', { alpha: false }).drawImage(bitmap, 0, 0);
    wrapper.appendChild(canvas);
    container.appendChild(wrapper);
  };

  for (let num = 1; num <= totalPages; num += 1) {
    if (isCancelled?.()) return;
    onProgress?.(num, totalPages);
    // eslint-disable-next-line no-await-in-loop
    const bitmap = await fetchPage(num);
    mount(bitmap);
    if (num === 1) onFirstPage?.();
  }
}
