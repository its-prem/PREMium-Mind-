(function () {
  function getWatchReturnUrl() {
    try {
      return sessionStorage.getItem('pmWatchReturn') || 'lecture.html';
    } catch (e) {
      return 'lecture.html';
    }
  }

  function isWatchPage() {
    return /watch\.html/i.test(window.location.pathname || window.location.href);
  }

  function navigateBack(fallbackUrl) {
    var fb = fallbackUrl || (isWatchPage() ? getWatchReturnUrl() : 'index.html');
    if (window.history.length > 1) {
      window.history.back();
      return;
    }
    window.location.href = fb;
  }

  window.pmGoBack = function (fallbackUrl) {
    navigateBack(fallbackUrl);
  };

  window.pmWatchGoBack = function (ev) {
    if (ev) {
      ev.preventDefault();
      ev.stopPropagation();
    }
    navigateBack(getWatchReturnUrl());
  };

  function isNativeApp() {
    if (window.__PM_NATIVE_APP__ === true) return true;
    if (window.AndroidPdfSaver) return true;
    if (window.Android) return true;
    if (window.Capacitor
      && typeof window.Capacitor.isNativePlatform === 'function'
      && window.Capacitor.isNativePlatform()) return true;
    var ua = navigator.userAgent || '';
    return /Android/.test(ua) && (/; wv\)/.test(ua) || /Capacitor/i.test(ua) || /premiummind/i.test(ua));
  }

  /** Status-bar / cutout padding — Capacitor often reports safe-area as 0 */
  function applyNativeSafeInsets() {
    try {
      document.documentElement.classList.add('pm-native');
      // Typical Android status bar ~24–32dp; keep existing env() if larger
      document.documentElement.style.setProperty('--pm-safe-top', '32px');
      document.documentElement.style.setProperty('--pm-safe-bottom', '16px');
    } catch (e) { /* ignore */ }
  }

  /** Open APK / external URL outside WebView (in-app <a download> often fails) */
  window.pmOpenExternalUrl = async function (url) {
    if (!url) return false;
    try {
      var Browser = window.Capacitor
        && window.Capacitor.Plugins
        && window.Capacitor.Plugins.Browser;
      if (Browser && typeof Browser.open === 'function') {
        await Browser.open({ url: url });
        return true;
      }
    } catch (e1) { /* continue */ }

    try {
      if (window.Android && typeof window.Android.openUrl === 'function') {
        window.Android.openUrl(url);
        return true;
      }
      if (window.Android && typeof window.Android.openExternal === 'function') {
        window.Android.openExternal(url);
        return true;
      }
    } catch (e2) { /* continue */ }

    try {
      var w = window.open(url, '_blank');
      if (w) return true;
    } catch (e3) { /* continue */ }

    try {
      var a = document.createElement('a');
      a.href = url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      document.body.appendChild(a);
      a.click();
      a.remove();
      return true;
    } catch (e4) { /* continue */ }

    // Android: hand off to system browser / download manager
    try {
      var ua = navigator.userAgent || '';
      if (/Android/i.test(ua) && /^https?:\/\//i.test(url)) {
        var bare = url.replace(/^https?:\/\//i, '');
        window.location.href = 'intent://' + bare
          + '#Intent;scheme=https;action=android.intent.action.VIEW;category=android.intent.category.BROWSABLE;end';
        return true;
      }
    } catch (e5) { /* continue */ }

    window.location.href = url;
    return true;
  };

  if (!isNativeApp()) return;

  // Index already lays out correctly without extra inset; watch + app-download need it.
  var path = (window.location.pathname || window.location.href || '').toLowerCase();
  var needsInset = /watch\.html|app-download\.html/.test(path);
  if (needsInset) {
    applyNativeSafeInsets();
    setTimeout(applyNativeSafeInsets, 200);
    setTimeout(applyNativeSafeInsets, 800);
  }

  var App = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.App;
  if (App && typeof App.addListener === 'function') {
    App.addListener('backButton', function () {
      navigateBack(isWatchPage() ? getWatchReturnUrl() : null);
    });
  }
})();
