/**
 * Keeps a tool's uploaded files and settings on THIS device, so a refresh, a back button
 * or a closed tab does not lose the user's work. Nothing is sent anywhere.
 *  - files   -> IndexedDB (big files are fine)
 *  - settings -> localStorage (small JSON)
 * Every call is safe: if the browser blocks storage (private mode), it just does nothing.
 */

const DB_NAME = 'pm_tools';
const STORE = 'files';

function openDb() {
  return new Promise((resolve, reject) => {
    if (!window.indexedDB) {
      reject(new Error('no indexedDB'));
      return;
    }
    const req = indexedDB.open(DB_NAME, 1);
    req.onupgradeneeded = () => req.result.createObjectStore(STORE);
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

const run = async (mode, fn) => {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, mode);
    const out = fn(tx.objectStore(STORE));
    tx.oncomplete = () => {
      db.close();
      resolve(out && 'result' in out ? out.result : undefined);
    };
    tx.onerror = () => {
      db.close();
      reject(tx.error);
    };
  });
};

/** Save one uploaded file under a name, replacing the older one. */
export async function keepFile(key, file) {
  try {
    await run('readwrite', (s) => s.put({ blob: file, name: file.name, type: file.type, at: Date.now() }, key));
  } catch {
    /* storage blocked: ignore */
  }
}

/** Get the saved file back as a File, or null. */
export async function loadKeptFile(key) {
  try {
    const rec = await run('readonly', (s) => s.get(key));
    if (!rec || !rec.blob) return null;
    return new File([rec.blob], rec.name, { type: rec.type || rec.blob.type });
  } catch {
    return null;
  }
}

export async function dropKeptFile(key) {
  try {
    await run('readwrite', (s) => s.delete(key));
  } catch {
    /* ignore */
  }
}

/** Small settings (text, colours, sliders...) */
export function loadKept(key, fallback) {
  try {
    const raw = localStorage.getItem(`pm_tool_${key}`);
    return raw ? { ...fallback, ...JSON.parse(raw) } : fallback;
  } catch {
    return fallback;
  }
}

export function saveKept(key, value) {
  try {
    localStorage.setItem(`pm_tool_${key}`, JSON.stringify(value));
  } catch {
    /* ignore */
  }
}

export function dropKept(key) {
  try {
    localStorage.removeItem(`pm_tool_${key}`);
  } catch {
    /* ignore */
  }
}
