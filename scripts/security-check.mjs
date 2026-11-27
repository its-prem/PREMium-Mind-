/**
 * Security check of the LIVE website and server. Run it any time:
 *
 *     node scripts/security-check.mjs                 (checks the live site)
 *     node scripts/security-check.mjs --skip-rate     (does not try the login rate limit)
 *
 * It only sends harmless requests (nothing is changed, no real account is touched) and prints PASS / FAIL for each
 * check. It exits with a non-zero code when something fails, so it can also run in CI.
 */
const API = 'https://premind.diplomawallah.in';
const SITE = 'https://premind.netlify.app';
const EVIL = 'https://evil.example.com';
const skipRate = process.argv.includes('--skip-rate');

let pass = 0;
let fail = 0;
const results = [];
const ok = (name, cond, extra = '') => {
  if (cond) pass += 1;
  else fail += 1;
  results.push(`${cond ? 'PASS' : 'FAIL'}  ${name}${extra ? '  (' + extra + ')' : ''}`);
};

async function req(url, opts = {}) {
  try {
    const res = await fetch(url, { redirect: 'manual', ...opts });
    const text = await res.text();
    let json = null;
    try {
      json = JSON.parse(text.trim());
    } catch {
      /* not JSON */
    }
    return { status: res.status, headers: res.headers, text, json };
  } catch (e) {
    return { status: 0, headers: new Headers(), text: String(e), json: null };
  }
}
const get = (path, headers = {}) => req(API + path, { headers });
const post = (path, body, headers = {}) => req(API + path, { method: 'POST', headers: { 'Content-Type': 'application/json', ...headers }, body: JSON.stringify(body) });

/* ---------- 1. the admin API is closed to everybody who is not an admin ---------- */
for (const f of ['me', 'dashboard', 'courses', 'ranking', 'students', 'orders', 'reports', 'maintenance', 'syllabus']) {
  const r = await get(`/admin/${f}.php`);
  ok(`admin/${f}.php refuses a visitor`, [401, 403, 429].includes(r.status), `HTTP ${r.status}`);
}
const fake = await get('/admin/courses.php', { 'X-PM-Session': 'eyJmYWtlIjoxfQ.AAAA' });
ok('admin/courses.php refuses a forged login token', [401, 403, 429].includes(fake.status), `HTTP ${fake.status}`);
const fakeSave = await post('/admin/syllabus.php', { action: 'save', subject: { title: 'x', sem: 1, units: [] } }, { 'X-PM-Session': 'forged.token' });
ok('admin/syllabus.php cannot save with a forged token', [401, 403, 429].includes(fakeSave.status), `HTTP ${fakeSave.status}`);

/* ---------- 2. money and student data need a login ---------- */
const order = await post('/create_order.php', { course_id: 1 });
ok('create_order.php refuses a visitor', order.status === 401, `HTTP ${order.status}`);
const orderPrice = await post('/create_order.php', { course_id: 1, price: 1, amount: 1 }, { 'X-PM-Session': 'forged.token' });
ok('create_order.php ignores a price sent by the browser (needs a real login)', orderPrice.status === 401, `HTTP ${orderPrice.status}`);
const mine = await get('/my_courses.php');
ok('my_courses.php refuses a visitor', mine.status === 401, `HTTP ${mine.status}`);
const user = await get('/get_user.php?email=someone@example.com');
ok('get_user.php does not give another student\'s profile', user.status === 401 && !/phone/i.test(user.text), `HTTP ${user.status}`);
const verify = await get('/verify_payment.php?order_id=NOT_AN_ORDER_123');
ok('verify_payment.php refuses a visitor', verify.status === 401, `HTTP ${verify.status}`);
const qa = await get('/qa_text.php?course_id=1');
ok('qa_text.php (the questions text) refuses a visitor', qa.status === 401, `HTTP ${qa.status}`);
const progress = await get('/progress.php');
ok('progress.php refuses a visitor', progress.status === 401 || progress.status === 403, `HTTP ${progress.status}`);

/* ---------- 3. the public catalog never shows a paid file ---------- */
const cat = await get('/api.php');
ok('api.php answers', cat.status === 200 && Array.isArray(cat.json), `HTTP ${cat.status}`);
if (Array.isArray(cat.json)) {
  ok('api.php shows no PDF path to a visitor', cat.json.every((c) => !c.pdf_file), '');
  ok('api.php shows no payment link to a visitor', cat.json.every((c) => !c.link), '');
  ok('api.php shows no question text to a visitor', cat.json.every((c) => !('qa_text' in c)), '');
  ok('api.php shows no micro-format terms/secret fields', cat.json.every((c) => !('micro_tnc_text' in c)), '');
}

/* ---------- 4. files that must never be downloadable ---------- */
for (const f of ['/.env', '/pm_secrets.php', '/webhook_debug.txt', '/db_connect.php', '/schema.sql', '/products.sql', '/config.example.php', '/.git/config', '/uploads/pdfs/', '/uploads/', '/d2d_pyq_data/data_2026.json']) {
  const r = await get(f);
  const leaked = r.status === 200 && /(password|secret|DB_|CASHFREE|CREATE TABLE|INSERT INTO|[core]|Index of)/i.test(r.text);
  ok(`${f} is not downloadable`, !leaked, `HTTP ${r.status}`);
}

/* ---------- 5. other websites cannot talk to the protected API ---------- */
const cors = await req(`${API}/my_courses.php`, { headers: { Origin: EVIL } });
ok('my_courses.php refuses another website (CORS)', cors.status === 403 || cors.headers.get('access-control-allow-origin') !== EVIL, `HTTP ${cors.status}, allow-origin: ${cors.headers.get('access-control-allow-origin')}`);
const corsUser = await req(`${API}/get_user.php`, { headers: { Origin: EVIL } });
ok('get_user.php does not answer "*" to another website', corsUser.headers.get('access-control-allow-origin') !== '*', `allow-origin: ${corsUser.headers.get('access-control-allow-origin')}`);
const corsAdmin = await req(`${API}/admin/me.php`, { headers: { Origin: EVIL } });
ok('admin/me.php refuses another website (CORS)', corsAdmin.status === 403 || corsAdmin.headers.get('access-control-allow-origin') !== EVIL, `HTTP ${corsAdmin.status}`);

/* ---------- 6. payments: a forged webhook is refused ---------- */
const wh = await req(`${API}/webhook.php`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'x-webhook-signature': 'AAAA', 'x-webhook-timestamp': String(Date.now()) }, body: JSON.stringify({ type: 'PAYMENT_SUCCESS_WEBHOOK', data: { order: { order_id: 'FAKE', order_amount: 1 }, customer_details: { customer_email: 'x@y.z' } } }) });
ok('webhook.php refuses a wrong signature', wh.status === 401 || wh.status === 500, `HTTP ${wh.status}`);
const whNone = await req(`${API}/webhook.php`, { method: 'POST', body: '{}' });
ok('webhook.php refuses a request without a signature', whNone.status === 401 || whNone.status === 500, `HTTP ${whNone.status}`);

/* ---------- 7. login: guessing and tricks do not work ---------- */
const sqli = await req(`${API}/login_api.php`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ email: "' OR 1=1 -- ", password: "' OR '1'='1" }) });
ok("login_api.php: SQL injection does not log in", !(sqli.json && sqli.json.status === 'success'), `HTTP ${sqli.status}`);
const otp = await post('/verify_otp.php', { email: 'nobody@example.invalid', otp: '' });
ok('verify_otp.php: an empty code does not log in', !(otp.json && otp.json.status === 'success'), `HTTP ${otp.status}`);
if (!skipRate) {
  let limited = false;
  for (let i = 0; i < 12; i += 1) {
    const r = await req(`${API}/login_api.php`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ email: 'ratelimit-test@example.invalid', password: 'wrong-' + i }) });
    if (r.status === 429) {
      limited = true;
      break;
    }
  }
  ok('login_api.php blocks password guessing (rate limit)', limited);
}

/* ---------- 8. PDFs: no token, no file ---------- */
const tok = await post('/get_pdf_token.php', { file: 'uploads/pdfs/anything.pdf' });
ok('get_pdf_token.php refuses a visitor', tok.status >= 400, `HTTP ${tok.status}`);
const pdf = await post('/secure_pdf.php', { file: 'uploads/pdfs/anything.pdf', email: 'x@y.z', token: 'a.b' });
ok('secure_pdf.php refuses a request without a real token', pdf.status >= 400, `HTTP ${pdf.status}`);
const trav = await post('/proxy_pdf.php', { file: 'uploads/pdfs/../../.env', email: 'x@y.z', token: 'a.b' });
ok('proxy_pdf.php refuses a path that leaves the PDF folder', trav.status >= 400 && !/CASHFREE|DB_/i.test(trav.text), `HTTP ${trav.status}`);

/* ---------- 9. the website sends the security headers ---------- */
const home = await req(SITE + '/');
const h = (n) => home.headers.get(n) || '';
ok('website: Strict-Transport-Security (HTTPS only)', /max-age=\d{6,}/.test(h('strict-transport-security')));
ok('website: X-Content-Type-Options nosniff', /nosniff/i.test(h('x-content-type-options')));
ok('website: X-Frame-Options (no clickjacking)', /deny|sameorigin/i.test(h('x-frame-options')));
ok('website: Referrer-Policy', h('referrer-policy') !== '');
ok('website: Permissions-Policy', h('permissions-policy') !== '');
ok('website: Content-Security-Policy in the page', /Content-Security-Policy/i.test(home.text));
const map = await req(SITE + '/assets/index.js.map');
ok('website: no source maps are published', map.status === 404 || !/"sources"/.test(map.text));

console.log(results.join('\n'));
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
