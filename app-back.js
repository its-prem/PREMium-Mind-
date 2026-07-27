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

  /**
   * Open an HTTPS URL outside the WebView (browser / Custom Tabs).
   * NEVER navigate to googlechrome:// or intent:// — WebView shows "Webpage not available".
   */
  window.pmOpenInChrome = async function (url) {
    if (!url) return false;
    // Only allow http(s) — weird schemes break the in-app WebView
    if (!/^https?:\/\//i.test(url)) return false;

    // 1) Native bridge
    try {
      if (window.Android && typeof window.Android.openUrl === 'function') {
        window.Android.openUrl(url);
        return true;
      }
      if (window.Android && typeof window.Android.openExternal === 'function') {
        window.Android.openExternal(url);
        return true;
      }
    } catch (e0) { /* continue */ }

    // 2) Capacitor Browser → opens system browser / Custom Tabs (HTTPS works)
    try {
      var Browser = window.Capacitor
        && window.Capacitor.Plugins
        && window.Capacitor.Plugins.Browser;
      if (Browser && typeof Browser.open === 'function') {
        await Browser.open({ url: url });
        return true;
      }
    } catch (e1) { /* continue */ }

    // 3) Cordova InAppBrowser _system
    try {
      if (window.cordova && window.cordova.InAppBrowser && typeof window.cordova.InAppBrowser.open === 'function') {
        window.cordova.InAppBrowser.open(url, '_system');
        return true;
      }
    } catch (e2) { /* continue */ }

    // 4) _system / _blank
    try {
      var w = window.open(url, '_system');
      if (w) return true;
    } catch (e3) { /* continue */ }
    try {
      var w2 = window.open(url, '_blank');
      if (w2) return true;
    } catch (e4) { /* continue */ }

    // 5) Visible user gesture link (best chance without plugins)
    try {
      var a = document.createElement('a');
      a.href = url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.style.display = 'none';
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { try { a.remove(); } catch (e) {} }, 500);
      return true;
    } catch (e5) { /* continue */ }

    return false;
  };

  /** Open external HTTPS URL (safe for WebView) */
  window.pmOpenExternalUrl = async function (url) {
    if (!url) return false;
    try {
      if (await window.pmOpenInChrome(url)) return true;
    } catch (e) { /* continue */ }
    // Last resort: stay on HTTPS page only (never chrome:// schemes)
    if (/^https?:\/\//i.test(url)) {
      window.location.href = url;
      return true;
    }
    return false;
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

  function bytesToBase64(bytes) {
    var CHUNK = 0x8000;
    var binary = '';
    for (var i = 0; i < bytes.length; i += CHUNK) {
      binary += String.fromCharCode.apply(null, bytes.subarray(i, Math.min(i + CHUNK, bytes.length)));
    }
    return btoa(binary);
  }

  function ensureNotifyStyles() {
    if (document.getElementById('pm-dl-notify-style')) return;
    var style = document.createElement('style');
    style.id = 'pm-dl-notify-style';
    style.textContent =
      '#pmDlNotify{position:fixed;left:12px;right:12px;top:calc(10px + max(env(safe-area-inset-top,0px),var(--pm-safe-top,0px)));'
      + 'z-index:99999;display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border-radius:14px;'
      + 'background:#111;color:#fff;box-shadow:0 12px 32px rgba(0,0,0,.28);transform:translateY(-120%);opacity:0;'
      + 'transition:transform .28s ease,opacity .28s ease;font-family:Poppins,system-ui,sans-serif;}'
      + '#pmDlNotify.show{transform:translateY(0);opacity:1;}'
      + '#pmDlNotify .pm-dl-ico{width:36px;height:36px;border-radius:10px;background:#22c55e;display:flex;'
      + 'align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}'
      + '#pmDlNotify .pm-dl-title{font-weight:700;font-size:.92rem;line-height:1.25;margin:0 0 2px;}'
      + '#pmDlNotify .pm-dl-body{font-size:.78rem;color:#cbd5e1;line-height:1.35;margin:0;}';
    document.head.appendChild(style);
  }

  /** Show download notification (native if available + in-app banner) */
  window.pmNotifyDownload = async function (title, body, fileName) {
    title = title || 'Download complete';
    body = body || ((fileName || 'File') + ' saved to Downloads');
    fileName = fileName || '';
    var isApk = /\.apk$/i.test(fileName) || /apk/i.test(title + ' ' + body);

    // Native Android bridges — skip AndroidPdfSaver for APK (it toasts "PDF saved")
    try {
      var saver = window.AndroidPdfSaver;
      if (saver && !isApk) {
        if (typeof saver.showNotification === 'function') {
          saver.showNotification(title, body, fileName);
        } else if (typeof saver.notify === 'function') {
          saver.notify(title, body);
        } else if (typeof saver.notifyDownload === 'function') {
          saver.notifyDownload(title, body, fileName);
        } else if (typeof saver.showToast === 'function') {
          saver.showToast(title + ': ' + body);
        }
      }
    } catch (e1) { /* continue */ }

    try {
      if (window.Android && typeof window.Android.showNotification === 'function') {
        window.Android.showNotification(title, body);
      } else if (window.Android && typeof window.Android.notify === 'function') {
        window.Android.notify(title, body);
      }
    } catch (e2) { /* continue */ }

    // Capacitor Local Notifications
    try {
      var LN = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.LocalNotifications;
      if (LN && typeof LN.schedule === 'function') {
        try {
          if (typeof LN.requestPermissions === 'function') await LN.requestPermissions();
        } catch (ePerm) { /* ignore */ }
        await LN.schedule({
          notifications: [{
            id: Math.floor(Date.now() % 100000) + 1,
            title: title,
            body: body,
            schedule: { at: new Date(Date.now() + 300) },
            smallIcon: 'ic_stat_icon_config_sample',
            sound: undefined
          }]
        });
      }
    } catch (e3) { /* continue */ }

    // Always show in-app notification banner too
    try {
      ensureNotifyStyles();
      var el = document.getElementById('pmDlNotify');
      if (!el) {
        el = document.createElement('div');
        el.id = 'pmDlNotify';
        el.innerHTML = '<div class="pm-dl-ico">✓</div><div><p class="pm-dl-title"></p><p class="pm-dl-body"></p></div>';
        document.body.appendChild(el);
      }
      el.querySelector('.pm-dl-title').textContent = title;
      el.querySelector('.pm-dl-body').textContent = body;
      el.classList.add('show');
      clearTimeout(window.__pmDlNotifyTimer);
      window.__pmDlNotifyTimer = setTimeout(function () {
        el.classList.remove('show');
      }, 4200);
    } catch (e4) { /* ignore */ }

    return true;
  };

  async function saveBlobViaAndroidBridge(blob, fileName, mimeType) {
    if (!window.AndroidPdfSaver || typeof window.AndroidPdfSaver.begin !== 'function') return false;
    var saver = window.AndroidPdfSaver;
    var buf = await blob.arrayBuffer();
    var bytes = new Uint8Array(buf);
    mimeType = mimeType || 'application/octet-stream';

    if (saver.showToast) {
      try { saver.showToast('Downloading ' + fileName + '...'); } catch (e) {}
    }

    // Prefer mime-aware begin if native app supports it (keeps .apk extension)
    try {
      if (typeof saver.beginWithMime === 'function') {
        saver.beginWithMime(fileName, mimeType);
      } else if (typeof saver.beginWithType === 'function') {
        saver.beginWithType(fileName, mimeType);
      } else {
        if (typeof saver.setMimeType === 'function') {
          try { saver.setMimeType(mimeType); } catch (eMime) {}
        }
        if (typeof saver.setFileName === 'function') {
          try { saver.setFileName(fileName); } catch (eName) {}
        }
        saver.begin(fileName);
      }
    } catch (eBegin) {
      saver.begin(fileName);
    }

    var chunkSize = 128 * 1024;
    for (var i = 0; i < bytes.length; i += chunkSize) {
      var slice = bytes.subarray(i, Math.min(i + chunkSize, bytes.length));
      saver.appendChunk(bytesToBase64(slice));
      if (i > 0 && i % (chunkSize * 4) === 0) {
        await new Promise(function (r) { setTimeout(r, 0); });
      }
    }

    try {
      if (typeof saver.finishWithMime === 'function') {
        saver.finishWithMime(mimeType);
      } else if (typeof saver.finishWithType === 'function') {
        saver.finishWithType(mimeType);
      } else {
        saver.finish();
      }
    } catch (eFinish) {
      saver.finish();
    }
    return true;
  }

  function ensureApkFileName(name) {
    var n = String(name || 'PREMium-Mind.apk').trim();
    n = n.replace(/[\\\/:*?"<>|]+/g, '_');
    // Never allow PDF saver / wrong extension to leak into APK downloads
    if (/\.pdf$/i.test(n)) n = n.replace(/\.pdf$/i, '.apk');
    if (!/\.apk$/i.test(n)) n = n.replace(/\.[a-z0-9]+$/i, '') + '.apk';
    if (!n || n === '.apk') n = 'PREMium-Mind.apk';
    return n;
  }

  function withApkFileNameQuery(url, fileName) {
    try {
      var u = new URL(url, window.location.href);
      u.searchParams.set('filename', fileName);
      u.searchParams.set('name', fileName);
      return u.toString();
    } catch (e) {
      var join = String(url).indexOf('?') >= 0 ? '&' : '?';
      return url + join + 'filename=' + encodeURIComponent(fileName);
    }
  }

  /**
   * Download APK.
   * App: open HTTPS get-apk / download URL in external browser (no chrome:// schemes).
   * Never use AndroidPdfSaver (saves as PDF).
   */
  window.pmDownloadApk = async function (url, fileName) {
    fileName = ensureApkFileName(fileName || 'PREMium-Mind.apk');
    if (!url) throw new Error('Missing download URL');
    var apkUrl = withApkFileNameQuery(url, fileName);
    var native = isNativeApp();

    // Real HTTPS landing page — auto-starts APK download in browser
    var landingUrl = 'https://premind.netlify.app/get-apk.html?filename='
      + encodeURIComponent(fileName)
      + '&t=' + Date.now();

    // Website (normal browser) — direct download
    if (!native) {
      try {
        var a = document.createElement('a');
        a.href = apkUrl;
        a.setAttribute('download', fileName);
        a.rel = 'noopener';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        a.remove();
        return 'started';
      } catch (eWeb) {
        window.location.href = apkUrl;
        return 'external';
      }
    }

    // Optional native DownloadManager
    try {
      if (window.Android && typeof window.Android.downloadUrl === 'function') {
        window.Android.downloadUrl(apkUrl, fileName);
        await window.pmNotifyDownload('Downloading APK…', fileName + ' — notification shade check karo', fileName);
        return 'started';
      }
    } catch (e0) { /* continue */ }

    // Open HTTPS page in external browser / Custom Tabs (safe — no Webpage not available)
    var opened = false;
    try {
      opened = await window.pmOpenInChrome(landingUrl);
    } catch (e1) { opened = false; }

    if (!opened) {
      try {
        opened = await window.pmOpenInChrome(apkUrl);
      } catch (e2) { opened = false; }
    }

    // If still not opened externally, show in-page fallback (do NOT navigate to chrome://)
    if (!opened) {
      try {
        var box = document.getElementById('apkChromeFallback');
        if (!box) {
          box = document.createElement('div');
          box.id = 'apkChromeFallback';
          box.style.cssText = 'margin:14px 0 0;padding:14px;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;text-align:center;';
          var hint = document.getElementById('fileHint');
          if (hint && hint.parentNode) hint.parentNode.insertBefore(box, hint.nextSibling);
          else document.body.appendChild(box);
        }
        box.innerHTML =
          '<p style="margin:0 0 10px;font-size:.85rem;color:#991b1b;font-weight:600;">Browser me open nahi hua. Neeche link dabao:</p>'
          + '<a href="' + landingUrl + '" target="_blank" rel="noopener" '
          + 'style="display:inline-block;background:#111;color:#fff;padding:12px 16px;border-radius:10px;font-weight:700;text-decoration:none;">'
          + 'Open Download in Browser</a>';
      } catch (e3) { /* ignore */ }
    }

    await window.pmNotifyDownload(
      opened ? 'Browser open ho raha hai' : 'Browser link use karo',
      fileName + ' download browser me start hoga',
      fileName
    );
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
