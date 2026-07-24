/**
 * App-only course gate + deep link helpers for website.
 * DB flag: app_only / only_app_access = 1
 */
(function () {
  var APP_PACKAGE = 'com.premiummind.app';
  var APP_SCHEME = 'premiummind';
  var DOWNLOAD_PAGE = 'app-download.html';

  function isNativeApp() {
    if (window.__PM_NATIVE_APP__) return true;
    if (window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform()) return true;
    var ua = navigator.userAgent || '';
    return /Android/.test(ua) && (/; wv\)/.test(ua) || /Capacitor/i.test(ua));
  }

  function isAppOnlyCourse(course) {
    if (!course || typeof course !== 'object') return false;
    var v = course.app_only != null ? course.app_only : course.only_app_access;
    return v == 1 || v === '1' || v === true || String(v).toLowerCase() === 'yes';
  }

  function findCourseById(courseId) {
    var list = window.globalLoadedCourses || [];
    if (!Array.isArray(list)) return null;
    return list.find(function (c) { return String(c.id) === String(courseId); }) || null;
  }

  function buildDeepLink(pathAndQuery) {
    // pathAndQuery example: "buy?id=58" or "open?id=58"
    return APP_SCHEME + '://' + pathAndQuery.replace(/^\//, '');
  }

  function buildIntentUrl(pathAndQuery, fallbackUrl) {
    var hostPath = pathAndQuery.replace(/^\//, '');
    var fallback = fallbackUrl || (location.origin + '/' + DOWNLOAD_PAGE);
    return 'intent://' + hostPath
      + '#Intent;scheme=' + APP_SCHEME
      + ';package=' + APP_PACKAGE
      + ';S.browser_fallback_url=' + encodeURIComponent(fallback)
      + ';end';
  }

  function tryOpenApp(pathAndQuery) {
    var fallback = location.origin.replace(/\/$/, '') + '/' + DOWNLOAD_PAGE
      + '?from=gate&next=' + encodeURIComponent(pathAndQuery);
    var intentUrl = buildIntentUrl(pathAndQuery, fallback);
    var schemeUrl = buildDeepLink(pathAndQuery);

    // Android Chrome prefers intent://
    if (/Android/i.test(navigator.userAgent || '')) {
      window.location.href = intentUrl;
      return;
    }

    // Desktop / iOS: show download page (no reliable install detect)
    window.location.href = fallback;
  }

  function showAppOnlyModal(courseId, action) {
    action = action || 'buy';
    var existing = document.getElementById('pmAppOnlyModal');
    if (existing) existing.remove();

    var path = (action === 'open' ? 'open' : 'buy') + '?id=' + encodeURIComponent(String(courseId));
    var modal = document.createElement('div');
    modal.id = 'pmAppOnlyModal';
    modal.innerHTML = ''
      + '<div class="pm-app-backdrop" data-close="1"></div>'
      + '<div class="pm-app-sheet" role="dialog" aria-modal="true">'
      + '  <div class="pm-app-icon">📱</div>'
      + '  <h3>App Exclusive Course</h3>'
      + '  <p>Yeh course sirf <b>PREMium Mind App</b> me buy / open ho sakta hai.</p>'
      + '  <button type="button" class="pm-app-btn primary" id="pmOpenAppBtn">Open in App</button>'
      + '  <a class="pm-app-btn secondary" href="' + DOWNLOAD_PAGE + '?from=gate&next=' + encodeURIComponent(path) + '">Download / Update App</a>'
      + '  <button type="button" class="pm-app-link" data-close="1">Not now</button>'
      + '</div>';

    if (!document.getElementById('pmAppOnlyStyles')) {
      var style = document.createElement('style');
      style.id = 'pmAppOnlyStyles';
      style.textContent = ''
        + '#pmAppOnlyModal{position:fixed;inset:0;z-index:5000;display:flex;align-items:flex-end;justify-content:center;}'
        + '#pmAppOnlyModal .pm-app-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}'
        + '#pmAppOnlyModal .pm-app-sheet{position:relative;width:100%;max-width:420px;background:#fff;border-radius:20px 20px 0 0;padding:28px 22px calc(22px + env(safe-area-inset-bottom));text-align:center;box-shadow:0 -10px 40px rgba(0,0,0,.18);animation:pmSheetUp .28s ease;}'
        + '@keyframes pmSheetUp{from{transform:translateY(30px);opacity:0}to{transform:none;opacity:1}}'
        + '#pmAppOnlyModal .pm-app-icon{font-size:42px;margin-bottom:8px;}'
        + '#pmAppOnlyModal h3{margin:0 0 8px;font-size:1.25rem;color:#111;}'
        + '#pmAppOnlyModal p{margin:0 0 18px;color:#64748b;font-size:.95rem;line-height:1.5;}'
        + '#pmAppOnlyModal .pm-app-btn{display:block;width:100%;padding:14px 16px;border-radius:12px;font-weight:700;text-decoration:none;border:none;cursor:pointer;margin-bottom:10px;font-size:1rem;}'
        + '#pmAppOnlyModal .pm-app-btn.primary{background:#111;color:#fff;}'
        + '#pmAppOnlyModal .pm-app-btn.secondary{background:#f1f5f9;color:#111;}'
        + '#pmAppOnlyModal .pm-app-link{background:none;border:none;color:#64748b;font-size:.9rem;cursor:pointer;margin-top:4px;}'
        + '@media(min-width:640px){#pmAppOnlyModal{align-items:center}#pmAppOnlyModal .pm-app-sheet{border-radius:20px;margin:20px}}';
      document.head.appendChild(style);
    }

    document.body.appendChild(modal);
    modal.addEventListener('click', function (e) {
      if (e.target && e.target.getAttribute('data-close') === '1') modal.remove();
    });
    document.getElementById('pmOpenAppBtn').addEventListener('click', function () {
      tryOpenApp(path);
      setTimeout(function () {
        // If still on page after 1.8s, user likely has no app
        if (!document.hidden) {
          window.location.href = DOWNLOAD_PAGE + '?from=gate&next=' + encodeURIComponent(path);
        }
      }, 1800);
    });
  }

  /**
   * Returns true if action is blocked on website (caller should return early).
   * In native app, always returns false (allow).
   */
  function gateAppOnly(courseOrId, action) {
    if (isNativeApp()) return false;
    var course = (courseOrId && typeof courseOrId === 'object')
      ? courseOrId
      : findCourseById(courseOrId);
    if (!course) {
      // Unknown course object — if id passed and we later know, still allow server to block
      return false;
    }
    if (!isAppOnlyCourse(course)) return false;
    showAppOnlyModal(course.id, action || 'buy');
    return true;
  }

  window.pmIsNativeApp = isNativeApp;
  window.pmIsAppOnlyCourse = isAppOnlyCourse;
  window.pmGateAppOnly = gateAppOnly;
  window.pmTryOpenApp = tryOpenApp;
  window.pmShowAppOnlyModal = showAppOnlyModal;
})();
