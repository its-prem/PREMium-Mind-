/**
 * Saves a file the page has made (for example the micro format PDF).
 *   saveFile(arrayBuffer, 'name.pdf', 'application/pdf')
 * - in the Android app: through its native bridge (a WebView cannot download by itself)
 * - in a browser: a normal download from the TOP page (a download started inside an iframe is blocked on some phones);
 *   if the browser still refuses, the file is opened in a new tab so it can be saved from there.
 */
const toBase64 = (bytes) => {
  let binary = '';
  for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode.apply(null, bytes.subarray(i, Math.min(i + 0x8000, bytes.length)));
  return btoa(binary);
};

export async function saveFile(buffer, fileName, mime = 'application/octet-stream') {
  const saver = typeof window !== 'undefined' ? window.AndroidPdfSaver : null;
  if (saver && typeof saver.begin === 'function') {
    saver.showToast?.('Saving to Downloads...');
    saver.begin(fileName);
    const bytes = new Uint8Array(buffer);
    const chunk = 128 * 1024;
    for (let i = 0; i < bytes.length; i += chunk) {
      saver.appendChunk(toBase64(bytes.subarray(i, Math.min(i + chunk, bytes.length))));
      if (i > 0 && i % (chunk * 4) === 0) await new Promise((r) => setTimeout(r, 0)); // eslint-disable-line no-await-in-loop
    }
    saver.finish();
    return;
  }
  const url = URL.createObjectURL(new Blob([buffer], { type: mime }));
  const a = document.createElement('a');
  a.href = url;
  a.download = fileName;
  a.rel = 'noopener';
  document.body.appendChild(a);
  a.click();
  a.remove();
  // some browsers ignore the download attribute: after a moment there is no way to know, so only iOS (which never saves) gets the tab
  if (/iphone|ipad|ipod/i.test(navigator.userAgent)) window.open(url, '_blank');
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}
