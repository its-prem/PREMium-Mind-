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

  function getStoredEmail() {
    const val = localStorage.getItem('customUserEmail') || getCookie('customUserEmail');
    return val ? syncSession('customUserEmail', val) : null;
  }

  function getStoredName() {
    const val = localStorage.getItem('customUserName') || getCookie('customUserName');
    return val ? syncSession('customUserName', val) : null;
  }

  function getStoredPhone() {
    const val = localStorage.getItem('customUserPhone') || getCookie('customUserPhone');
    return val ? syncSession('customUserPhone', val) : null;
  }

  function saveLoginSession(email, name, phone) {
    if (!email) return false;
    syncSession('customUserEmail', String(email).trim());
    syncSession('customUserName', name || 'User');
    syncSession('customUserPhone', phone || '0000000000');
    return !!getStoredEmail();
  }

  function clearStoredSession() {
    localStorage.removeItem('customUserEmail');
    localStorage.removeItem('customUserName');
    localStorage.removeItem('customUserPhone');
    deleteCookie('customUserEmail');
    deleteCookie('customUserName');
    deleteCookie('customUserPhone');
  }

  function isApiSuccess(data) {
    if (!data || typeof data !== 'object') return false;
    const status = String(data.status || data.Status || '').toLowerCase();
    return status === 'success' || data.success === true;
  }

  window.getStoredEmail = getStoredEmail;
  window.getStoredName = getStoredName;
  window.getStoredPhone = getStoredPhone;
  window.saveLoginSession = saveLoginSession;
  window.clearStoredSession = clearStoredSession;
  window.pmIsApiSuccess = isApiSuccess;
  window.pmGetCookie = getCookie;
})();
