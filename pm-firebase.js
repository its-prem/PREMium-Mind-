// Shared Firebase auth helper so any page (e.g. watch.html) can get the
// logged-in user's *verified* ID token. The token proves who the user is
// to the server — unlike the email stored in localStorage, it cannot be
// faked. PDF endpoints use it to grant access to the real buyer only.
import { initializeApp, getApps } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getAuth, onAuthStateChanged } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';

const firebaseConfig = {
  apiKey: "AIzaSyBMF_RAmopbnPC7OpNcJCo2CUGS6CDiMEY",
  authDomain: "premium-mind-fcb16.firebaseapp.com",
  projectId: "premium-mind-fcb16",
  storageBucket: "premium-mind-fcb16.firebasestorage.app",
  messagingSenderId: "864874199521",
  appId: "1:864874199521:web:5deb96a956dc404a077aa0"
};

const app = getApps().length ? getApps()[0] : initializeApp(firebaseConfig);
const auth = getAuth(app);
window.__pmAuth = auth;

// Resolves once Firebase has restored (or confirmed the absence of) a session.
window.pmAuthReady = new Promise((resolve) => {
  const unsub = onAuthStateChanged(auth, () => { resolve(auth.currentUser || null); try { unsub(); } catch (e) {} });
});

// Returns a fresh ID token for the signed-in user, or null if not signed in.
window.pmIdToken = async function () {
  try {
    await window.pmAuthReady;
    const u = auth.currentUser;
    return u ? await u.getIdToken() : null;
  } catch (e) { return null; }
};
