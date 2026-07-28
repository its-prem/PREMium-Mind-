/**
 * Basic Inspect / DevTools deterrents for PREMium Mind website.
 * Note: This is not real security — determined users can still bypass.
 */
(function () {
  'use strict';

  try {
    if (window.__PM_INSPECT_BLOCK__) return;
    window.__PM_INSPECT_BLOCK__ = true;
  } catch (e) {}

  function isNativeApp() {
    try {
      if (window.__PM_NATIVE_APP__ === true) return true;
      if (window.AndroidPdfSaver || window.Android) return true;
      if (window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function'
          && window.Capacitor.isNativePlatform()) return true;
    } catch (e) {}
    return false;
  }

  function isLikelyMobile() {
    try {
      if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return true;
      if (window.innerWidth < 820) return true;
      return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
    } catch (e) {
      return false;
    }
  }

  // Right-click / long-press menu
  document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
    return false;
  }, true);

  // Common inspect / view-source shortcuts
  document.addEventListener('keydown', function (e) {
    var key = e.key || '';
    var code = e.keyCode || e.which || 0;
    var ctrl = e.ctrlKey || e.metaKey;
    var shift = e.shiftKey;
    var alt = e.altKey;

    // F12
    if (code === 123 || key === 'F12') {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }

    // Ctrl+Shift+I / J / C  (DevTools)
    if (ctrl && shift && /^(I|J|C)$/i.test(key)) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }

    // Ctrl+U (view source)
    if (ctrl && !shift && /^(U)$/i.test(key)) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }

    // Ctrl+S (save page)
    if (ctrl && !shift && /^(S)$/i.test(key)) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }

    // Mac: Cmd+Option+I/J/C
    if (e.metaKey && alt && /^(I|J|C)$/i.test(key)) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  }, true);

  // Soft select block on content pages (inputs/textarea still usable)
  document.addEventListener('selectstart', function (e) {
    var t = e.target;
    if (!t) return;
    var tag = (t.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || t.isContentEditable) return;
    if (t.closest && t.closest('input, textarea, [contenteditable="true"]')) return;
    e.preventDefault();
    return false;
  }, true);

  document.addEventListener('dragstart', function (e) {
    var t = e.target;
    if (t && (t.tagName || '').toLowerCase() === 'img') {
      e.preventDefault();
      return false;
    }
  }, true);

  // Desktop only: blur page if DevTools dock likely open
  if (!isNativeApp() && !isLikelyMobile()) {
    var threshold = 160;
    setInterval(function () {
      try {
        var open = (window.outerWidth - window.innerWidth > threshold)
          || (window.outerHeight - window.innerHeight > threshold);
        document.documentElement.style.filter = open ? 'blur(6px)' : '';
      } catch (e) {}
    }, 900);
  }
})();
