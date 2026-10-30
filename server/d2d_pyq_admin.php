<?php
/**
 * Upload to Hostinger premind/ as: d2d_pyq_admin.php
 * Admin-only page to grant / revoke FULL D2D PYQ access by email.
 */
require_once __DIR__ . '/pm_admin_auth.php';

header('Cache-Control: no-store');

if (!pm_auth_admin_ok()) {
    http_response_code(401);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;text-align:center;padding:60px">'
       . '<h2>Login required</h2><p>Pehle Admin Panel me Google se login karo.</p>'
       . '<a href="admin_panel.php" style="color:#8e1b2a">→ Admin Panel</a></body>';
    exit;
}

require_once __DIR__ . '/db_connect.php';
$conn->set_charset('utf8mb4');
$conn->query("CREATE TABLE IF NOT EXISTS d2d_pyq_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    granted_by VARCHAR(190) NOT NULL DEFAULT '',
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$adminEmail = pm_auth_admin_email();

// ---- AJAX actions ----
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string)$_POST['action'];

    if ($action === 'list') {
        $rows = [];
        $res = $conn->query("SELECT email, granted_by, granted_at FROM d2d_pyq_access ORDER BY granted_at DESC");
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode(['status' => 'success', 'rows' => $rows]);
        exit;
    }

    if ($action === 'add') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'msg' => 'Sahi email daalo']); exit;
        }
        $stmt = $conn->prepare("INSERT INTO d2d_pyq_access (email, granted_by) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by)");
        $stmt->bind_param('ss', $email, $adminEmail);
        $ok = $stmt->execute();
        echo json_encode($ok ? ['status' => 'success'] : ['status' => 'error', 'msg' => $conn->error]);
        exit;
    }

    if ($action === 'remove') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $stmt = $conn->prepare("DELETE FROM d2d_pyq_access WHERE email = ?");
        $stmt->bind_param('s', $email);
        $ok = $stmt->execute();
        echo json_encode($ok ? ['status' => 'success'] : ['status' => 'error', 'msg' => $conn->error]);
        exit;
    }

    echo json_encode(['status' => 'error', 'msg' => 'Unknown action']);
    exit;
}
?><!doctype html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>D2D PYQ Access — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:Poppins,sans-serif; background:#f1f5f9; color:#0f172a; padding:24px; }
  .wrap { max-width:640px; margin:0 auto; }
  h1 { font-family:Oswald,sans-serif; text-transform:uppercase; letter-spacing:1.5px; font-size:1.5rem; margin-bottom:4px; }
  h1 span { color:#8e1b2a; }
  .sub { color:#64748b; font-size:.9rem; margin-bottom:20px; }
  .back { display:inline-block; margin-bottom:18px; color:#8e1b2a; text-decoration:none; font-weight:600; font-size:.85rem; }
  .card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.04); margin-bottom:18px; }
  .row { display:flex; gap:10px; }
  input { flex:1; padding:11px 13px; border:1px solid #cbd5e1; border-radius:8px; font-size:.9rem; font-family:inherit; }
  input:focus { outline:none; border-color:#8e1b2a; box-shadow:0 0 0 3px rgba(142,27,42,.12); }
  button.add { background:#8e1b2a; color:#fff; border:none; border-radius:8px; padding:0 20px; font-family:Oswald,sans-serif; letter-spacing:1px; text-transform:uppercase; font-size:.8rem; cursor:pointer; }
  button.add:hover { background:#6f1220; }
  .note { font-size:.78rem; color:#64748b; margin-top:10px; line-height:1.5; }
  .list-head { font-family:Oswald,sans-serif; text-transform:uppercase; letter-spacing:1.2px; font-size:.8rem; color:#8e1b2a; margin-bottom:12px; }
  .item { display:flex; align-items:center; gap:12px; padding:11px 12px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:8px; }
  .item .em { flex:1; min-width:0; font-weight:600; font-size:.88rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .item .meta { font-size:.7rem; color:#94a3b8; }
  .item .del { background:#fef2f2; color:#ef4444; border:1px solid #fecaca; border-radius:6px; padding:6px 12px; cursor:pointer; font-size:.78rem; }
  .item .del:hover { background:#fee2e2; }
  .empty { color:#94a3b8; text-align:center; padding:20px; font-size:.85rem; }
  .chip { background:#fbecef; color:#8e1b2a; border-radius:99px; padding:2px 10px; font-size:.72rem; font-weight:600; }
</style></head>
<body>
<div class="wrap">
  <a class="back" href="admin_panel.php">← Admin Panel</a>
  <h1>D2D PYQ <span>Access</span></h1>
  <p class="sub">Jin emails ko yahan add karoge, unke account me <b>poore saal (2017–2026)</b> khulenge. Baaki sabke sirf <b>2026</b>.</p>

  <div class="card">
    <div class="row">
      <input type="email" id="emailInput" placeholder="student@gmail.com" autocomplete="off">
      <button class="add" id="addBtn">+ Access do</button>
    </div>
    <p class="note">Ye wahi email honi chahiye jisse student site par login karta hai (Google wala email). Galti se add ho gayi to niche se hata sakte ho.</p>
  </div>

  <div class="card">
    <div class="list-head">Full access emails <span class="chip" id="count">0</span></div>
    <div id="list"><div class="empty">Loading…</div></div>
  </div>
</div>

<script>
  const $ = id => document.getElementById(id);
  async function api(fields) {
    const fd = new FormData();
    Object.keys(fields).forEach(k => fd.append(k, fields[k]));
    const r = await fetch(location.href, { method:'POST', body:fd });
    return r.json().catch(() => ({ status:'error' }));
  }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
  function fmt(ts){ if(!ts) return ''; const d=new Date(String(ts).replace(' ','T')); return isNaN(d)?'':d.toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}); }

  async function load() {
    const d = await api({ action:'list' });
    const rows = (d && d.rows) || [];
    $('count').textContent = rows.length;
    if (!rows.length) { $('list').innerHTML = '<div class="empty">Abhi koi email nahi. Upar se add karo.</div>'; return; }
    $('list').innerHTML = rows.map(r =>
      '<div class="item"><div class="em" title="'+esc(r.email)+'">'+esc(r.email)+
      '<div class="meta">'+fmt(r.granted_at)+'</div></div>'+
      '<button class="del" data-email="'+esc(r.email)+'">Hatao</button></div>'
    ).join('');
  }

  async function add() {
    const email = $('emailInput').value.trim();
    if (!email) return;
    $('addBtn').disabled = true;
    const d = await api({ action:'add', email });
    $('addBtn').disabled = false;
    if (d.status === 'success') { $('emailInput').value=''; load(); }
    else alert(d.msg || 'Add fail ho gaya');
  }

  $('addBtn').addEventListener('click', add);
  $('emailInput').addEventListener('keydown', e => { if (e.key === 'Enter') add(); });
  $('list').addEventListener('click', async e => {
    const b = e.target.closest('.del'); if (!b) return;
    const email = b.getAttribute('data-email');
    if (!confirm('"'+email+'" se full access hata dein? ( unke sirf 2026 bachega)')) return;
    const d = await api({ action:'remove', email });
    if (d.status === 'success') load(); else alert(d.msg || 'Remove fail');
  });

  load();
</script>
</body></html>
