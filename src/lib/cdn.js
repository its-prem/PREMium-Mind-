/** Load a script from a CDN once and return the global it defines. */
const cache = {};

export function loadScript(url, globalName) {
  if (window[globalName]) return Promise.resolve(window[globalName]);
  if (!cache[url]) {
    cache[url] = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = url;
      s.async = true;
      s.onload = () => resolve(window[globalName]);
      s.onerror = () => {
        delete cache[url];
        reject(new Error('Could not load a helper library. Check your internet.'));
      };
      document.head.appendChild(s);
    });
  }
  return cache[url];
}

export const loadPdfLib = () =>
  loadScript('https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js', 'PDFLib');

export function formatBytes(n) {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / 1024 / 1024).toFixed(2)} MB`;
}

export function downloadBlob(blob, name) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}
