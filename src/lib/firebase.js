/**
 * Firebase Auth, loaded on demand from Google's CDN (same SDK version the old
 * site used). This keeps the SDK out of the bundle and needs no npm install.
 */

const FIREBASE_CDN = 'https://www.gstatic.com/firebasejs/10.7.1';

// Public web-app config (safe to ship in client code; access is controlled
// by Firebase security rules and authorized domains).
const firebaseConfig = {
  apiKey: 'AIzaSyBMF_RAmopbnPC7OpNcJCo2CUGS6CDiMEY',
  authDomain: 'premium-mind-fcb16.firebaseapp.com',
  projectId: 'premium-mind-fcb16',
  storageBucket: 'premium-mind-fcb16.firebasestorage.app',
  messagingSenderId: '864874199521',
  appId: '1:864874199521:web:5deb96a956dc404a077aa0',
};

let sdkPromise = null;

function loadSdk() {
  if (!sdkPromise) {
    sdkPromise = (async () => {
      const [appMod, authMod] = await Promise.all([
        import(/* @vite-ignore */ `${FIREBASE_CDN}/firebase-app.js`),
        import(/* @vite-ignore */ `${FIREBASE_CDN}/firebase-auth.js`),
      ]);
      const app = appMod.initializeApp(firebaseConfig);
      return {
        authMod,
        auth: authMod.getAuth(app),
        provider: new authMod.GoogleAuthProvider(),
      };
    })().catch((err) => {
      sdkPromise = null; // allow a retry on the next click
      throw new Error(
        'Could not load Google Sign-In. Check your internet connection and try again.'
      );
    });
  }
  return sdkPromise;
}

function isCapacitorNative() {
  if (window.Capacitor?.isNativePlatform?.()) return true;
  const ua = navigator.userAgent || '';
  return /Android/.test(ua) && (/; wv\)/.test(ua) || /Capacitor/i.test(ua));
}

async function nativeGoogleSignIn({ authMod, auth }) {
  const plugin = window.Capacitor?.Plugins?.FirebaseAuthentication;
  if (!plugin || typeof plugin.signInWithGoogle !== 'function') {
    throw new Error(
      'Google Sign-In plugin missing. Please reinstall the latest app.'
    );
  }

  let result;
  try {
    result = await plugin.signInWithGoogle({ useCredentialManager: true });
  } catch (firstErr) {
    console.warn('Credential Manager sign-in failed, retrying legacy flow.', firstErr);
    result = await plugin.signInWithGoogle({ useCredentialManager: false });
  }

  const idToken = result?.credential?.idToken;
  if (!idToken) throw new Error('Google did not return a valid token. Try again.');

  const userCred = await authMod.signInWithCredential(
    auth,
    authMod.GoogleAuthProvider.credential(idToken)
  );
  return userCred.user;
}

// Browsers / in-app WebViews that block window.open() throw one of these
// from signInWithPopup; fall back to a full-page redirect instead.
const POPUP_FALLBACK_CODES = [
  'auth/popup-blocked',
  'auth/operation-not-supported-in-this-environment',
  'auth/cancelled-popup-request',
];

/** Returns the signed-in user, or null when the page is redirecting away. */
export async function googleSignIn() {
  const sdk = await loadSdk();
  if (isCapacitorNative()) return nativeGoogleSignIn(sdk);

  const { authMod, auth, provider } = sdk;
  try {
    const result = await authMod.signInWithPopup(auth, provider);
    return result.user;
  } catch (err) {
    if (POPUP_FALLBACK_CODES.includes(err.code)) {
      await authMod.signInWithRedirect(auth, provider);
      return null;
    }
    throw err;
  }
}

/**
 * The website addresses Firebase allows for login (read from Firebase itself).
 * Used only to explain an "unauthorized domain" error. Returns { domains } or { error }.
 */
export async function getAuthorizedDomains() {
  try {
    const res = await fetch(
      `https://www.googleapis.com/identitytoolkit/v3/relyingparty/getProjectConfig?key=${firebaseConfig.apiKey}`
    );
    const data = await res.json();
    if (!res.ok) return { error: data?.error?.message || `HTTP ${res.status}` };
    return { domains: data.authorizedDomains || [] };
  } catch (e) {
    return { error: e.message };
  }
}

/** Finishes a signInWithRedirect() round-trip; resolves to a user or null. */
export async function finishRedirectSignIn() {
  const { authMod, auth } = await loadSdk();
  const result = await authMod.getRedirectResult(auth);
  return result?.user || null;
}

export async function signOutFirebase() {
  const { auth } = await loadSdk();
  return auth.signOut();
}

/** Firebase ID token for the current user (fallback proof of identity). */
export async function getIdToken() {
  try {
    const { auth } = await loadSdk();
    await auth.authStateReady?.();
    return auth.currentUser ? await auth.currentUser.getIdToken(true) : null;
  } catch {
    return null;
  }
}
