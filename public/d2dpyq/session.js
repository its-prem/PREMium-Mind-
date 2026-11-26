/**
 * Small helper for the D2D PYQ page (the website's own login is read from the same storage the React site writes).
 * Provides what the page calls: getStoredEmail(), pmGetPdfSession(), pmIsAdminAccount().
 */
(function () {
  function read(key) {
    var v = null;
    try { v = localStorage.getItem(key); } catch (e) {}
    if (!v) { try { v = sessionStorage.getItem(key); } catch (e) {} }
    if (!v) {
      var m = document.cookie.match(new RegExp('(?:^|; )' + key + '=([^;]*)'));
      if (m) v = decodeURIComponent(m[1]);
    }
    return v;
  }
  window.getStoredEmail = function () { return read('customUserEmail'); };
  window.pmGetPdfSession = function () { return read('pm_pdf_session'); };
  // No admin emails in public files. Copying stays blocked here for everyone.
  window.pmIsAdminAccount = function () { return false; };
})();
