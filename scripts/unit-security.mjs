/**
 * Local security tests (no network). Run:  node scripts/unit-security.mjs
 * Checks that a bad link typed in the admin (javascript:, data:, //other-site) can never become a link the website opens.
 */
import { normalizeOpenLink } from '../src/lib/openLink.js';

let fail = 0;
const t = (name, got, want) => {
  const ok = got === want;
  if (!ok) fail += 1;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}  ->  ${JSON.stringify(got)}`);
};

t('javascript: link is refused', normalizeOpenLink('javascript:alert(document.cookie)'), '');
t('JaVaScRiPt: link is refused', normalizeOpenLink('JaVaScRiPt:alert(1)'), '');
t('data: link is refused', normalizeOpenLink('data:text/html,<script>alert(1)</script>'), '');
t('vbscript: link is refused', normalizeOpenLink('vbscript:msgbox(1)'), '');
t('protocol-relative //evil.com is refused', normalizeOpenLink('//evil.com/steal'), '');
t('backslash trick is refused', normalizeOpenLink('/\evil.com'), '');
t('a path with spaces/quotes is refused', normalizeOpenLink('/x"onmouseover="alert(1)'), '');
t('plain word (NA) is refused', normalizeOpenLink('NA'), '');
t('empty is refused', normalizeOpenLink(''), '');
t('https link is kept', normalizeOpenLink('https://example.com/page'), 'https://example.com/page');
t('page of this website works', normalizeOpenLink('tools/mcq'), '/tools/mcq');
t('D2D page (old spelling) goes to the new page', normalizeOpenLink('https://premind.netlify.app/D2D%20PYQ/D2DPYQV2.HTML'), '/d2dpyq/d2dpyqv2');

console.log(fail ? `\n${fail} FAILED` : '\nall passed');
process.exit(fail ? 1 : 0);
