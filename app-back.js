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
      var path = (window.location.pathname || window.location.href || '').toLowerCase();
      // watch: keep status clearance but don't make header feel huge
      var topPad = /watch\.html/.test(path) ? '22px' : '32px';
      document.documentElement.style.setProperty('--pm-safe-top', topPad);
      document.documentElement.style.setProperty('--pm-safe-bottom', '12px');
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
      if (window.Android && typeof window.Android.downloadUrl === 'function') {
        window.Android.downloadUrl(url);
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

  function blobToBase64(blob) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onloadend = function () {
        var result = String(reader.result || '');
        var base64 = result.indexOf(',') >= 0 ? result.split(',')[1] : result;
        resolve(base64);
      };
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  }

  /**
   * Download APK inside app WebView with progress-friendly flow.
   * Returns: 'saved' | 'started' | 'external'
   */
  window.pmDownloadApk = async function (url, fileName) {
    fileName = fileName || 'PREMium-Mind.apk';
    if (!url) throw new Error('Missing download URL');

    // Native bridge download (if app exposes it)
    try {
      if (window.Android && typeof window.Android.downloadUrl === 'function') {
        window.Android.downloadUrl(url, fileName);
        return 'started';
      }
      if (window.AndroidPdfSaver && typeof window.AndroidPdfSaver.downloadUrl === 'function') {
        window.AndroidPdfSaver.downloadUrl(url, fileName);
        return 'started';
      }
    } catch (e0) { /* continue */ }

    // Fetch APK (CORS enabled on download_apk.php) then save/trigger
    var res = await fetch(url, { cache: 'no-store', credentials: 'omit' });
    if (!res.ok) throw new Error('Download failed (HTTP ' + res.status + ')');
    var blob = await res.blob();
    if (!blob || blob.size < 1000) throw new Error('Download file empty');

    // Capacitor Filesystem (if installed)
    try {
      var FS = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Filesystem;
      if (FS && typeof FS.writeFile === 'function') {
        var b64 = await blobToBase64(blob);
        var dir = (FS.Directory && FS.Directory.Documents) || 'DOCUMENTS';
        await FS.writeFile({ path: fileName, data: b64, directory: dir });
        try {
          var Share = window.Capacitor.Plugins.Share;
          if (Share && typeof Share.share === 'function') {
            var uri = await FS.getUri({ path: fileName, directory: dir });
            if (uri && uri.uri) {
              await Share.share({ title: fileName, url: uri.uri });
            }
          }
        } catch (eShare) { /* saved anyway */ }
        return 'saved';
      }
    } catch (eFs) { /* continue */ }

    // Blob + <a download> (works in many WebViews / Chrome)
    try {
      var objectUrl = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = objectUrl;
      a.download = fileName;
      a.style.display = 'none';
      document.body.appendChild(a);
      a.click();
      setTimeout(function () {
        try { URL.revokeObjectURL(objectUrl); } catch (e) {}
        try { a.remove(); } catch (e2) {}
      }, 2500);
      return 'started';
    } catch (eBlob) { /* continue */ }

    // Last resort: open externally
    await window.pmOpenExternalUrl(url);
    return 'external';
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
