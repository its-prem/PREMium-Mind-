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

  function resolveLoginEmail(preferred) {
    var fromArg = preferred ? String(preferred).trim() : '';
    if (fromArg) return fromArg;
    var stored = getStoredEmail();
    if (stored) return stored;
    try {
      var el = document.getElementById('userEmail');
      if (el && el.textContent) {
        var t = String(el.textContent).trim();
        if (t.indexOf('@') !== -1) return t;
      }
    } catch (e) { /* ignore */ }
    return null;
  }

  function updateAdminMenuLink(preferredEmail) {
    var email = resolveLoginEmail(preferredEmail);
    var show = isAdminAccount(email);
    var menus = document.querySelectorAll('.sidebar-menu, ul.nav-links');
    if (!menus.length) return;

    menus.forEach(function (menu) {
      var existing = menu.querySelector('.pm-admin-panel-link');
      if (show) {
        if (!existing) {
          var isSidebar = menu.classList.contains('sidebar-menu');
          var li = document.createElement('li');
          li.className = isSidebar ? 'sidebar-item pm-admin-panel-link' : 'pm-admin-panel-link';
          if (isSidebar) {
            li.style.setProperty('--delay', '0.55s');
            li.innerHTML =
              '<a href="' + ADMIN_PANEL_URL + '" class="sidebar-link">' +
              '<ion-icon name="settings-outline"></ion-icon> Admin Panel</a>';
          } else {
            li.innerHTML =
              '<a href="' + ADMIN_PANEL_URL + '" class="nav-link">' +
              '<ion-icon name="settings-outline"></ion-icon> Admin Panel</a>';
          }
          menu.appendChild(li);
        } else {
          existing.style.display = '';
        }
      } else if (existing) {
        existing.style.display = 'none';
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
    if (!btn || btn.getAttribute('data-pm-admin-hook') === '1') return;
    btn.setAttribute('data-pm-admin-hook', '1');
    btn.addEventListener('click', function () {
      setTimeout(bootAdminMenu, 0);
      setTimeout(bootAdminMenu, 200);
    }, true);
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
