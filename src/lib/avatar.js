/**
 * The profile photo of a student. It is kept on THIS device only (the browser's storage), per email; nobody else sees it.
 * Until a photo is chosen the cat (CatAvatar) is shown.
 *
 *   useAvatar(email)            the saved photo (a small data URL) or ''
 *   photoFromFile(file)         a picture file -> a small square data URL (256 px), ready to keep
 *   saveAvatar(email, dataUrl)  keep it          clearAvatar(email)  back to the cat
 */
import { useEffect, useState } from 'react';

const EVENT = 'pm-avatar-changed';
const keyOf = (email) => `pm_avatar_${String(email || '').trim().toLowerCase()}`;

export const getAvatar = (email) => {
  if (!email) return '';
  try {
    const v = localStorage.getItem(keyOf(email)) || '';
    return v.startsWith('data:image/') ? v : '';
  } catch {
    return '';
  }
};

export function saveAvatar(email, dataUrl) {
  if (!email || !String(dataUrl).startsWith('data:image/')) return false;
  try {
    localStorage.setItem(keyOf(email), dataUrl);
  } catch {
    return false; // storage full or blocked
  }
  window.dispatchEvent(new Event(EVENT));
  return true;
}

export function clearAvatar(email) {
  try {
    localStorage.removeItem(keyOf(email));
  } catch {
    /* storage blocked */
  }
  window.dispatchEvent(new Event(EVENT));
}

/** Reads a picture file, cuts the middle square and shrinks it to 256 x 256 (a JPEG of about 20 KB). */
export function photoFromFile(file, size = 256) {
  return new Promise((resolve, reject) => {
    if (!file || !/^image\//.test(file.type || '')) return reject(new Error('Please choose a photo (jpg, png or webp).'));
    if (file.size > 12 * 1048576) return reject(new Error('That photo is too big (max 12 MB).'));
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      try {
        const side = Math.min(img.naturalWidth, img.naturalHeight);
        const sx = (img.naturalWidth - side) / 2;
        const sy = (img.naturalHeight - side) / 2;
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        canvas.getContext('2d').drawImage(img, sx, sy, side, side, 0, 0, size, size);
        resolve(canvas.toDataURL('image/jpeg', 0.86));
      } catch {
        reject(new Error('Could not read that photo.'));
      } finally {
        URL.revokeObjectURL(url);
      }
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Could not read that photo.'));
    };
    img.src = url;
  });
}

export function useAvatar(email) {
  const [src, setSrc] = useState(() => getAvatar(email));
  useEffect(() => {
    setSrc(getAvatar(email));
    const on = () => setSrc(getAvatar(email));
    window.addEventListener(EVENT, on);
    window.addEventListener('storage', on);
    return () => {
      window.removeEventListener(EVENT, on);
      window.removeEventListener('storage', on);
    };
  }, [email]);
  return src;
}
