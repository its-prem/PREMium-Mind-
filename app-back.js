(function () {
  if (window.pmGoBack) return;

  window.pmGoBack = function (fallbackUrl) {
    if (window.history.length > 1) {
      window.history.back();
      return;
    }
    if (fallbackUrl) window.location.href = fallbackUrl;
  };

  function isNativeApp() {
    return !!(window.Capacitor
      && typeof window.Capacitor.isNativePlatform === 'function'
      && window.Capacitor.isNativePlatform());
  }

  if (!isNativeApp()) return;

  var App = window.Capacitor.Plugins && window.Capacitor.Plugins.App;
  if (!App || typeof App.addListener !== 'function') return;

  App.addListener('backButton', function () {
    if (window.history.length > 1) {
      window.history.back();
    } else if (typeof App.exitApp === 'function') {
      App.exitApp();
    }
  });
})();
