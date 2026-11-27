import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

/**
 * Content-Security-Policy of the website: the browser refuses to run a script, open a frame or send data to any
 * address that is not listed here. That stops an injected script from loading code from another site or sending
 * the student's data away (the "e-skimming" attack on a checkout page).
 *
 * It is added to the built index.html only (not to `npm run dev`). Every address below is used by the site:
 *   premind.diplomawallah.in  our PHP server          gstatic / googleapis / apis.google / firebaseapp / accounts.google  Google login
 *   sdk.cashfree.com + *.cashfree.com  payments       fonts.googleapis / fonts.gstatic  fonts      cdnjs  PDF tools and the Font Awesome icons of the micro format
 * To allow one more address, add it to the right line. 'unsafe-inline' is needed for the tiny inline handlers of the
 * page and for the micro-format tool, which runs inside the page.
 */
const CSP = [
  "default-src 'self'",
  "script-src 'self' 'unsafe-inline' https://www.gstatic.com https://apis.google.com https://sdk.cashfree.com https://cdnjs.cloudflare.com",
  "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
  "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
  "img-src 'self' data: blob: https:",
  "media-src 'self' blob: https:",
  "connect-src 'self' blob: https://premind.diplomawallah.in https://*.googleapis.com https://www.gstatic.com https://*.cashfree.com https://cdnjs.cloudflare.com",
  "frame-src https://*.firebaseapp.com https://accounts.google.com https://*.cashfree.com",
  "worker-src 'self' blob:",
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self' https://*.cashfree.com",
].join('; ')

const securityMeta = () => ({
  name: 'pm-security-meta',
  apply: 'build',
  transformIndexHtml() {
    return [
      { tag: 'meta', attrs: { 'http-equiv': 'Content-Security-Policy', content: CSP }, injectTo: 'head-prepend' },
      { tag: 'meta', attrs: { name: 'referrer', content: 'strict-origin-when-cross-origin' }, injectTo: 'head-prepend' },
    ]
  },
})

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), securityMeta()],
  build: { sourcemap: false }, // never publish the readable source of the website
})
