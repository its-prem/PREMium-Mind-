/**
 * Shared login session — localStorage + cookies (works in app WebView).
 */
(function () {
  function setCookie(name, value, days) {
    days = days || 30;
    const expires = new Date(Date.now() + days * 86400000).toUTCString();
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax' + secure;
  }

  function getCookie(name) {
    const match = document.cookie.match(
      new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)')
    );
    return match ? decodeURIComponent(match[1]) : null;
  }

  function deleteCookie(name) {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax' + secure;
  }

  function syncSession(key, val) {
    if (!val) return val;
    try { localStorage.setItem(key, val); } catch (e) { /* WebView may block */ }
    setCookie(key, val);
    return val;
  }

  function isApiSuccess(data) {
    if (!data || typeof data !== 'object') return false;
    const status = String(data.status || data.Status || data.result || '').toLowerCase();
    if (status === 'success' || status === 'ok' || status === 'true' || status === '1') return true;
    if (data.success === true || data.success === 1 || data.success === '1') return true;
    // Some PHP APIs only return user fields on success
    if (data.email && (data.name || data.user_name) && !data.message) return true;
    return false;
  }

  var ADMIN_MENU_EMAILS = ['premku0237@gmail.com'];
  var ADMIN_PANEL_URL = 'https://premind.diplomawallah.in/admin_panel.php';

  function isAdminAccount(email) {
    var e = String(email || '').trim().toLowerCase();
    return ADMIN_MENU_EMAILS.indexOf(e) !== -1;
  }

  async function openAdminPanelSecure() {
    // Prefer Firebase ID token (verified server-side). No shared secret in JS.
    try {
      if (typeof window.pmGetIdToken === 'function') {
        var token = await window.pmGetIdToken();
        if (token) {
          window.location.href = ADMIN_PANEL_URL + '?id_token=' + encodeURIComponent(token);
          return;
        }
      }
    } catch (e) { /* fall through */ }
    // No Firebase session → admin page will ask Google login
    window.location.href = ADMIN_PANEL_URL;
  }
  window.pmOpenAdminPanel = openAdminPanelSecure;

  function resolveLoginEmail(preferred) {
    var candidates = [];
    if (preferred) candidates.push(preferred);
    try { candidates.push(getStoredEmail()); } catch (e1) {}
    try {
      candidates.push(localStorage.getItem('customUserEmail'));
      candidates.push(localStorage.getItem('userEmail'));
    } catch (e2) {}
    try {
      var el = document.getElementById('userEmail');
      if (el && el.textContent) candidates.push(el.textContent);
    } catch (e3) {}
    for (var i = 0; i < candidates.length; i++) {
      var t = String(candidates[i] || '').trim();
      if (t && t.indexOf('@') !== -1) return t;
    }
    return null;
  }

  function ensureAdminMenuItem(menu) {
    var existing = menu.querySelector('.pm-admin-panel-link');
    if (existing) return existing;

    var li = document.createElement('li');
    li.className = 'sidebar-item pm-admin-panel-link';
    li.style.setProperty('--delay', '0.37s');
    li.innerHTML =
      '<a href="' + ADMIN_PANEL_URL + '" class="sidebar-link pm-admin-panel-anchor" data-pm-admin="1">' +
      '<ion-icon name="settings-outline"></ion-icon> Admin Panel</a>';

    // Place right after "App Version" item
    var items = menu.querySelectorAll(':scope > .sidebar-item, :scope > li');
    var insertAfter = null;
    for (var i = 0; i < items.length; i++) {
      var text = (items[i].textContent || '').toLowerCase();
      if (text.indexOf('app version') !== -1) {
        insertAfter = items[i];
        break;
      }
    }
    if (insertAfter && insertAfter.parentNode === menu) {
      if (insertAfter.nextSibling) menu.insertBefore(li, insertAfter.nextSibling);
      else menu.appendChild(li);
    } else {
      menu.appendChild(li);
    }
    return li;
  }

  function updateAdminMenuLink(preferredEmail) {
    var email = resolveLoginEmail(preferredEmail);
    var show = isAdminAccount(email);
    var menus = document.querySelectorAll('.sidebar-menu');
    if (!menus.length) return;

    menus.forEach(function (menu) {
      var existing = ensureAdminMenuItem(menu);
      var anchor = existing.querySelector('a') || existing;
      if (show) {
        if (anchor && anchor.tagName === 'A') {
          anchor.setAttribute('href', ADMIN_PANEL_URL);
          if (anchor.getAttribute('data-pm-sso-hook') !== '1') {
            anchor.setAttribute('data-pm-sso-hook', '1');
            anchor.addEventListener('click', function (ev) {
              var latestEmail = resolveLoginEmail();
              if (!isAdminAccount(latestEmail)) return;
              ev.preventDefault();
              openAdminPanelSecure();
            });
          }
        }
        existing.style.setProperty('display', 'list-item', 'important');
        existing.style.setProperty('opacity', '1', 'important');
        existing.style.setProperty('transform', 'none', 'important');
        existing.removeAttribute('hidden');
      } else {
        existing.style.setProperty('display', 'none', 'important');
      }
    });
  }

  function saveLoginSession(email, name, phone) {
    if (!email) return false;
    const e = String(email).trim();
    const n = name || 'User';
    const p = phone || '0000000000';
    syncSession('customUserEmail', e);
    syncSession('customUserName', n);
    syncSession('customUserPhone', p);
    try {
      sessionStorage.setItem('customUserEmail', e);
      sessionStorage.setItem('customUserName', n);
      sessionStorage.setItem('customUserPhone', p);
    } catch (err) { /* ignore */ }
    try { updateAdminMenuLink(e); } catch (err2) { /* ignore */ }
    return !!(localStorage.getItem('customUserEmail') || getCookie('customUserEmail') || sessionStorage.getItem('customUserEmail'));
  }

  function getStoredEmail() {
    let val = null;
    try { val = localStorage.getItem('customUserEmail'); } catch (e) {}
    if (!val) {
      try { val = sessionStorage.getItem('customUserEmail'); } catch (e) {}
    }
    if (!val) val = getCookie('customUserEmail');
    return val ? syncSession('customUserEmail', val) : null;
  }

  function getStoredName() {
    let val = null;
    try { val = localStorage.getItem('customUserName'); } catch (e) {}
    if (!val) {
      try { val = sessionStorage.getItem('customUserName'); } catch (e) {}
    }
    if (!val) val = getCookie('customUserName');
    return val ? syncSession('customUserName', val) : null;
  }

  function getStoredPhone() {
    let val = null;
    try { val = localStorage.getItem('customUserPhone'); } catch (e) {}
    if (!val) {
      try { val = sessionStorage.getItem('customUserPhone'); } catch (e) {}
    }
    if (!val) val = getCookie('customUserPhone');
    return val ? syncSession('customUserPhone', val) : null;
  }

  function clearStoredSession() {
    try {
      localStorage.removeItem('customUserEmail');
      localStorage.removeItem('customUserName');
      localStorage.removeItem('customUserPhone');
      sessionStorage.removeItem('customUserEmail');
      sessionStorage.removeItem('customUserName');
      sessionStorage.removeItem('customUserPhone');
    } catch (e) {}
    deleteCookie('customUserEmail');
    deleteCookie('customUserName');
    deleteCookie('customUserPhone');
    try { updateAdminMenuLink(); } catch (err) { /* ignore */ }
  }

  window.getStoredEmail = getStoredEmail;
  window.getStoredName = getStoredName;
  window.getStoredPhone = getStoredPhone;
  window.saveLoginSession = saveLoginSession;
  window.clearStoredSession = clearStoredSession;
  window.pmIsApiSuccess = isApiSuccess;
  window.pmGetCookie = getCookie;
  window.pmIsAdminAccount = isAdminAccount;
  window.pmRefreshAdminMenu = updateAdminMenuLink;

  function bootAdminMenu() {
    try { updateAdminMenuLink(); } catch (e) { /* ignore */ }
  }

  function hookMenuOpenRefresh() {
    var btn = document.getElementById('menu-btn');
    if (btn && btn.getAttribute('data-pm-admin-hook') !== '1') {
      btn.setAttribute('data-pm-admin-hook', '1');
      btn.addEventListener('click', function () {
        bootAdminMenu();
        setTimeout(bootAdminMenu, 50);
        setTimeout(bootAdminMenu, 250);
      }, true);
    }

    var sidebar = document.getElementById('sidebar');
    if (sidebar && sidebar.getAttribute('data-pm-admin-obs') !== '1') {
      sidebar.setAttribute('data-pm-admin-obs', '1');
      try {
        var obs = new MutationObserver(function () {
          if (sidebar.classList.contains('active')) bootAdminMenu();
        });
        obs.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
      } catch (e) { /* ignore */ }
    }
  }

  function startAdminMenuWatcher() {
    bootAdminMenu();
    hookMenuOpenRefresh();
    // Late Firebase / WebView session restore
    [300, 800, 1500, 3000].forEach(function (ms) {
      setTimeout(function () {
        bootAdminMenu();
        hookMenuOpenRefresh();
      }, ms);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAdminMenuWatcher);
  } else {
    startAdminMenuWatcher();
  }
})();
