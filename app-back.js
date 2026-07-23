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
    if (window.AndroidPdfSaver) return true;
    if (window.Capacitor
      && typeof window.Capacitor.isNativePlatform === 'function'
      && window.Capacitor.isNativePlatform()) return true;
    var ua = navigator.userAgent || '';
    return /Android/.test(ua) && (/; wv\)/.test(ua) || /Capacitor/i.test(ua));
  }

  if (!isNativeApp()) return;

  var App = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.App;
  if (App && typeof App.addListener === 'function') {
    App.addListener('backButton', function () {
      navigateBack(isWatchPage() ? getWatchReturnUrl() : null);
    });
  }
})();
