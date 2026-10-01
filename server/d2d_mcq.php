<?php
/**
 * D2D MCQ — PYQ practice-sheet maker, backed by MySQL.
 * Upload to Hostinger premind/ as: d2d_mcq.php  (with d2d_mcq_api.php + pm_admin_auth.php)
 */
require_once __DIR__ . '/pm_admin_auth.php';

if (!pm_auth_admin_ok()):
    http_response_code(401);
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MCQ D2D — Login required</title>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
  body { margin:0; min-height:100vh; display:grid; place-items:center; background: #f1f5f9; color: #1e293b; font-family: Poppins, sans-serif; }
  .box { max-width:420px; width:92%; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); border-radius: 12px; padding: 36px 32px; text-align:center; }
  h1 { font-family: Oswald, sans-serif; text-transform: uppercase; letter-spacing: 2px; font-size: 1.6rem; margin: 0 0 10px; color: #0f172a; }
  h1 span { color: #8e1b2a; }
  p { color: #475569; font-size: .95rem; line-height: 1.6; margin: 0 0 26px; }
  a { display:inline-block; background: #8e1b2a; color: #fff; text-decoration:none; font-family: Oswald, sans-serif; text-transform:uppercase; letter-spacing:1px; padding:12px 26px; border-radius: 6px; font-weight:600; box-shadow: 0 4px 6px rgba(142, 27, 42, 0.2); transition: all 0.2s ease; }
  a:hover { background: #6f1220; transform: translateY(-1px); }
</style></head><body>
<div class="box"><h1>MCQ <span>D2D</span></h1>
<p>Ye tool admin ke liye hai. Pehle Admin Panel me Google se login karo, phir is page ko dobara kholo.</p>
<a href="admin_panel.php">Admin Panel → Login</a></div>
</body></html>
<?php
    exit;
endif;

$pmAdminEmail = pm_auth_admin_email();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>MCQ D2D — Workspace</title>
<script>window.PM_MCQ = { admin: <?= json_encode($pmAdminEmail) ?>, api: 'd2d_mcq_api.php' };</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Poppins:wght@300;400;500;600&family=Tinos:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  @page { size: A4 portrait; margin: 0; }

  :root {
    --primary: #8e1b2a;
    --primary-hover: #6f1220;
    --primary-light: #fbecef;
    --bg-dark: #f1f5f9;
    --bg-light: #ffffff;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --line: #e2e8f0;
    --line-2: #cbd5e1;
    --ease: cubic-bezier(.4,0,.2,1); --toolbar-h: 64px;

    --maroon: #8e1b2a; --maroon-dark: #6f1220;
    --pink: #f7e1e5; --pink-2: #fbecef; --cream: #fff3dd; --cream-2: #fff8ea;
    --ink: #1a1a1a; --paper-pad: 8mm; --col-gap: 8mm; --sheet-scale: 1;
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }
  body.booting, body.booting * { transition: none !important; }
  html { -webkit-text-size-adjust: 100%; }

  body {
    font-family: 'Poppins', sans-serif;
    background: var(--bg-dark);
    color: var(--text-main);
    line-height: 1.6;
    height: 100dvh;
    overflow: hidden;
    display: flex; flex-direction: column;
  }
  h1, h2, h3, .logo { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

  ::-webkit-scrollbar { width: 8px; height: 8px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
  ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

  /* ---------------- app chrome ---------------- */
  .toolbar { position: relative; flex: none; z-index: 1000; background: #ffffff; border-bottom: 1px solid var(--line); box-shadow: 0 2px 5px rgba(0,0,0,0.03); transition: transform .3s var(--ease); }
  body.nav-hidden .toolbar { display: none; }
  .nav-show { position: fixed; top: 10px; left: 12px; z-index: 1001; display: none; align-items: center; gap: 6px; padding: 6px 14px; background: #ffffff; border: 1px solid var(--line); border-radius: 99px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); color: var(--text-main); font-family: 'Oswald', sans-serif; font-size: .74rem; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; opacity: .55; transition: opacity .2s; }
  .nav-show:hover { opacity: 1; }
  body.nav-hidden .nav-show { display: inline-flex; }
  body.nav-hidden .preview-col { padding-top: 54px; }
  .bar-inner { width: 96%; max-width: 1600px; margin: 0 auto; padding: 10px 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
  .logo a { font-size: 1.4rem; font-weight: 700; letter-spacing: 2px; color: var(--text-main); text-decoration: none; }
  .logo a span { color: var(--primary); }
  .actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; background: var(--primary); color: #fff; font-family: 'Oswald', sans-serif; font-weight: 500; font-size: .84rem; text-transform: uppercase; letter-spacing: 1px; border: 1px solid var(--primary); border-radius: 6px; cursor: pointer; transition: all .2s ease; white-space: nowrap; box-shadow: 0 2px 4px rgba(142,27,42,0.15); text-decoration: none; }
  .btn:hover { background: var(--primary-hover); border-color: var(--primary-hover); transform: translateY(-1px); }
  .btn:active { transform: scale(.97); }
  .btn.ghost { background: #f8fafc; color: #334155; box-shadow: none; border: 1px solid var(--line); }
  .btn.ghost:hover { background: var(--bg-dark); color: var(--text-main); border-color: var(--line-2); }
  .btn.danger { background: #fef2f2; color: #ef4444; box-shadow: none; border: 1px solid #fecaca; }
  .btn.danger:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }
  #btnRefreshPreview.stale { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  #btnRefreshPreview.stale i { animation: syncPulse 1.2s ease-in-out infinite; }
  body:not(.live-off) #btnRefreshPreview { display: none; }

  .side-tab { position: fixed; right: 0; left: auto; top: 50%; transform: translateY(-50%); z-index: 900; display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 18px 9px; background: #ffffff; border: 1px solid var(--line); border-right: none; border-radius: 8px 0 0 8px; color: var(--text-main); cursor: pointer; transition: all .3s ease; box-shadow: -2px 4px 12px rgba(0,0,0,0.06); }
  .side-tab:hover { background: var(--primary-light); color: var(--primary); border-color: var(--primary-light); }
  .side-tab .tab-label { font-family: 'Oswald', sans-serif; font-size: .72rem; letter-spacing: .16em; text-transform: uppercase; writing-mode: vertical-rl; transform: rotate(180deg); }
  body.drawer-open .side-tab { opacity: 0; visibility: hidden; pointer-events: none; }
  .scrim { display: none; }

  /* ---------------- workspace: paper left, editor right ---------------- */
  .workspace { display: flex; align-items: stretch; flex: 1 1 auto; min-height: 0; flex-direction: row; position: relative; }

  .editor-col { flex: 0 0 clamp(360px, 37%, 580px); width: clamp(360px, 37%, 580px); display: flex; align-items: flex-start; justify-content: center; padding: 20px 16px 40px 16px; height: 100%; overflow-y: auto; overscroll-behavior: contain; transition: flex-basis .38s var(--ease), width .38s var(--ease), padding .38s var(--ease), opacity .26s ease, transform .38s var(--ease); background: #ffffff; border-left: 1px solid var(--line); box-shadow: -4px 0 15px rgba(0,0,0,0.03); z-index: 10; contain: layout style; }
  body:not(.drawer-open) .editor-col { flex-basis: 0; width: 0; min-width: 0; padding: 0; opacity: 0; border-left-width: 0; overflow: hidden; pointer-events: none; }
  .preview-col { flex: 1 1 auto; min-width: 0; max-width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; padding: 22px 12px 60px; overflow: auto; overscroll-behavior: contain; background: transparent; position: relative; z-index: 5; }

  .editor { width: 100%; }
  .panel { background: var(--bg-light); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05); }
  .panel-head { padding: 14px 18px; border-bottom: 1px solid var(--line); background: #f8fafc; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
  .panel-head h1 { font-size: 1.1rem; letter-spacing: 1.2px; display: flex; align-items: center; gap: 10px; color: var(--text-main); margin: 0; }
  .panel-head h1 i { color: var(--primary); }
  .panel-close { width: 30px; height: 30px; display: grid; place-items: center; background: #fff; border: 1px solid var(--line-2); border-radius: 6px; color: var(--text-muted); cursor: pointer; transition: all 0.2s; }
  .panel-close:hover { background: #f1f5f9; border-color: var(--text-main); color: var(--text-main); }
  .panel-body { padding: 16px; }
  .status { font-size: .78rem; font-weight: 500; color: var(--primary); border-left: 3px solid var(--primary); padding: 4px 0 4px 9px; margin-bottom: 12px; background: var(--primary-light); border-radius: 0 4px 4px 0; }

  .field { margin-bottom: 12px; }
  .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .row3 { display: grid; grid-template-columns: 72px 1fr; gap: 12px; }
  .field-label { display: block; font-family: 'Oswald', sans-serif; font-size: .75rem; letter-spacing: 1.2px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; font-weight: 500; }

  .inp, textarea { width: 100%; background: #ffffff; border: 1px solid var(--line-2); color: var(--text-main); border-radius: 6px; outline: none; transition: all .2s ease; font-family: 'Poppins', sans-serif; font-size: .88rem; padding: 10px 12px; box-shadow: inset 0 1px 2px 0 rgba(0,0,0,0.02); }
  .inp:hover, textarea:hover { border-color: #94a3b8; }
  .inp:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(142, 27, 42, 0.15); }
  textarea#rawInput { min-height: 340px; resize: vertical; font-family: Consolas, "Courier New", monospace; font-size: .84rem; line-height: 1.6; tab-size: 2; }

  .checks { display: flex; flex-wrap: wrap; gap: 8px 16px; margin: 4px 0 16px; }
  .chk { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: var(--text-main); cursor: pointer; font-weight: 500; }
  .chk input { accent-color: var(--primary); width: 16px; height: 16px; cursor: pointer; }
  .chk input.num { width: 60px; height: auto; padding: 4px 6px; font-size: .82rem; text-align: center; }

  /* ---------------- sync / library ---------------- */
  .sync { display: flex; align-items: center; gap: 10px; font-size: .78rem; font-weight: 500; color: var(--text-main); padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; background: #f8fafc; margin-bottom: 14px; min-height: 36px; }
  .sync .dot { width: 10px; height: 10px; border-radius: 50%; background: #94a3b8; flex-shrink: 0; transition: background .25s; }
  .sync.saved .dot { background: #10b981; } .sync.saved { color: #047857; background: #ecfdf5; border-color: #a7f3d0; }
  .sync.saving .dot { background: #f59e0b; animation: syncPulse 1s ease-in-out infinite; } .sync.saving { color: #b45309; background: #fffbeb; border-color: #fde68a; }
  .sync.dirty .dot { background: #f97316; } .sync.dirty { color: #c2410c; background: #fff7ed; border-color: #fed7aa; }
  .sync.offline .dot, .sync.error .dot { background: #ef4444; } .sync.offline, .sync.error { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
  .sync .txt { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .sync button { background: #fff; border: 1px solid var(--line-2); color: var(--text-main); border-radius: 4px; padding: 4px 10px; font-size: .72rem; cursor: pointer; font-family: 'Oswald', sans-serif; letter-spacing: .8px; text-transform: uppercase; white-space: nowrap; transition: all 0.2s; }
  .sync button:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  @keyframes syncPulse { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: .45; transform: scale(.8); } }

  .lib-top { display: grid; grid-template-columns: 1fr auto; gap: 8px; margin-bottom: 10px; }
  .lib-new { background: var(--primary); border: none; color: #fff; border-radius: 6px; padding: 0 14px; font-family: 'Oswald', sans-serif; font-size: .76rem; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(142,27,42,0.2); transition: all 0.2s; }
  .lib-new:hover { background: var(--primary-hover); transform: translateY(-1px); }
  .lib-list { display: flex; flex-direction: column; gap: 6px; max-height: 320px; overflow-y: auto; padding-right: 4px; }
  .lib-subj { font-family: 'Oswald', sans-serif; font-size: .72rem; letter-spacing: 1.4px; text-transform: uppercase; color: var(--primary); padding: 10px 4px 2px; display: flex; align-items: center; justify-content: space-between; }
  .lib-subj small { color: var(--text-muted); letter-spacing: 0; font-family: 'Poppins', sans-serif; text-transform: none; font-weight: 500; }
  .lib-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: #ffffff; border: 1px solid var(--line); border-left: 4px solid transparent; border-radius: 8px; cursor: pointer; transition: all .2s ease; text-align: left; color: var(--text-main); font-family: 'Poppins', sans-serif; width: 100%; }
  .lib-item:hover { background: #f8fafc; border-color: var(--line-2); border-left-color: var(--primary-light); }
  .lib-item.cur { border-left-color: var(--primary); background: #f8fafc; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
  .lib-item .no { width: 34px; height: 34px; border-radius: 6px; background: var(--primary-light); display: grid; place-items: center; font-family: 'Oswald', sans-serif; font-size: .85rem; color: var(--primary); flex-shrink: 0; }
  .lib-item .ti { flex: 1; min-width: 0; }
  .lib-item .ti b { display: block; font-size: .85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .lib-item .ti small { display: block; font-size: .7rem; color: var(--text-muted); margin-top: 2px; }
  .lib-item .del { background: transparent; border: none; color: #94a3b8; cursor: pointer; padding: 6px 8px; border-radius: 6px; flex-shrink: 0; font-size: .85rem; transition: all 0.2s; }
  .lib-item .del:hover { color: #ef4444; background: #fef2f2; }
  .lib-empty { color: var(--text-muted); font-size: .82rem; padding: 16px 6px; text-align: center; }
  .lib-tools { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
  .lib-tools button { background: #ffffff; border: 1px solid var(--line-2); color: var(--text-main); border-radius: 6px; padding: 6px 12px; font-size: .72rem; cursor: pointer; font-family: 'Oswald', sans-serif; letter-spacing: .8px; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
  .lib-tools button:hover { background: #f8fafc; border-color: var(--primary); color: var(--primary); }

  /* ---------------- accordions ---------------- */
  .acc { background: #ffffff; border: 1px solid var(--line); border-left: 4px solid transparent; border-radius: 8px; margin-bottom: 12px; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
  .acc:hover { border-color: var(--line-2); }
  .acc.open { border-left-color: var(--primary); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
  .acc-head { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 16px; background: transparent; border: none; color: var(--text-main); cursor: pointer; font-family: 'Oswald', sans-serif; font-size: .82rem; letter-spacing: 1.2px; text-transform: uppercase; }
  .acc.open .acc-head { color: var(--primary); }
  .acc-head .chev { transition: transform .32s var(--ease); font-size: .85rem; }
  .acc.open .acc-head .chev { transform: rotate(180deg); }
  .acc-body { max-height: 0; overflow: hidden; padding: 0 16px; transition: max-height .4s var(--ease), padding .4s var(--ease); }
  .acc.open .acc-body { max-height: 1400px; padding: 0 16px 16px; }
  .acc-body p { font-size: .8rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 8px; }
  .acc-body pre { background: #f8fafc; border: 1px solid var(--line); border-radius: 6px; padding: 10px 12px; margin-top: 8px; white-space: pre-wrap; color: var(--text-main); font-family: Consolas, monospace; font-size: .74rem; line-height: 1.6; }
  .acc-body code { background: var(--primary-light); color: var(--primary); padding: 1px 6px; border-radius: 3px; font-size: .74rem; }
  .chip { background: var(--primary-light); color: var(--primary); font-size: .7rem; font-weight: 600; padding: 2px 10px; border-radius: 99px; letter-spacing: 0; font-family: 'Poppins', sans-serif; text-transform: none; }

  /* ---------------- question jump list ---------------- */
  .qlist { display: flex; flex-direction: column; gap: 6px; max-height: 300px; overflow-y: auto; padding-right: 6px; }
  .qitem { display: flex; gap: 10px; width: 100%; padding: 8px 12px; background: #f8fafc; border: 1px solid var(--line); border-left: 4px solid transparent; border-radius: 6px; color: var(--text-main); font-size: .82rem; text-align: left; cursor: pointer; transition: all .2s; }
  .qitem:hover, .qitem.flash { background: #ffffff; border-left-color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
  .qitem .n { color: var(--primary); font-family: 'Oswald', sans-serif; font-weight: 700; min-width: 32px; }
  .qitem .t { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
  .qitem .y { color: var(--text-muted); font-size: .72rem; }
  .qitem .im { color: var(--primary); font-size: .72rem; }
  .qsep { width: 100%; text-align: left; margin-top: 4px; padding: 7px 10px; background: transparent; border: none; border-bottom: 1px solid var(--line-2); color: var(--primary); font-family: 'Oswald', sans-serif; font-size: .72rem; letter-spacing: 1.2px; text-transform: uppercase; cursor: pointer; }
  .qsep:hover, .qsep.flash { color: var(--text-main); border-bottom-color: var(--primary); }

  /* ---------------- images panel ---------------- */
  .drop { background: #f8fafc; border: 2px dashed var(--line-2); border-radius: 8px; padding: 16px; text-align: center; color: var(--text-muted); font-size: .8rem; cursor: pointer; transition: all .2s; margin-bottom: 12px; }
  .drop:hover, .drop.over { border-color: var(--primary); color: var(--primary-hover); background: var(--primary-light); }
  .drop b { color: var(--primary); font-weight: 600; }
  .imgs { display: flex; flex-direction: column; gap: 10px; }
  .imgc { display: flex; gap: 12px; background: #ffffff; border: 1px solid var(--line); border-radius: 8px; padding: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
  .imgc.used { border-left: 4px solid var(--primary); }
  .imgc .th { width: 68px; height: 68px; flex: none; object-fit: cover; border-radius: 6px; background: #f1f5f9; border: 1px solid var(--line); }
  .imgc .bd { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
  .imgc .nm { display: flex; align-items: center; gap: 8px; font-size: .78rem; color: var(--text-main); }
  .imgc .nm code { color: var(--primary-hover); font-family: Consolas, monospace; font-size: .78rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
  .imgc .nm small { color: var(--text-muted); font-weight: 500; }
  .imgc .del { background: transparent; border: none; color: #94a3b8; cursor: pointer; font-size: .85rem; padding: 4px 6px; border-radius: 4px; transition: all 0.2s; }
  .imgc .del:hover { color: #ef4444; background: #fef2f2; }
  .imgc select.inp, .imgc input.inp { padding: 6px 10px; font-size: .78rem; background: #fff; }
  .imgc .r2 { display: flex; gap: 8px; }
  .imgc .r2 > * { flex: 1; min-width: 0; }
  .imgc .ins { background: var(--primary); color: #fff; border: none; border-radius: 6px; padding: 8px 12px; font-family: 'Oswald', sans-serif; font-size: .78rem; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; transition: all 0.2s; }
  .imgc .ins:hover { background: var(--primary-hover); }
  .imgc .ins.again { background: #fff; color: var(--text-main); border: 1px solid var(--line-2); }
  .imgc .ins.again:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  .imgc .used-note { font-size: .72rem; color: var(--primary-hover); background: var(--primary-light); border-radius: 6px; padding: 5px 8px; line-height: 1.4; }
  .imgs-empty { color: var(--text-muted); font-size: .82rem; padding: 10px 6px; text-align: center; }

  /* ---------------- modal ---------------- */
  .modal-scrim { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 3000; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(2px); }
  .modal-scrim.show { display: flex; }
  .modal { background: #ffffff; border: 1px solid var(--line); border-radius: 12px; max-width: 440px; width: 100%; padding: 26px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); color: var(--text-main); }
  .modal h3 { font-size: 1.1rem; letter-spacing: 1px; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; font-family: 'Oswald', sans-serif; text-transform: uppercase; }
  .modal h3 i { color: #f59e0b; }
  .modal p { color: var(--text-muted); font-size: .88rem; line-height: 1.65; margin-bottom: 20px; }
  .modal .row { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
  .modal .btn { padding: 10px 18px; font-size: .8rem; }

  /* =======================================================
     PAPER
  ======================================================= */
  .stage { width: 100%; display: flex; flex-direction: column; align-items: center; gap: 22px; }
  .measure { position: fixed; left: -99999px; top: 0; width: 210mm; visibility: hidden; pointer-events: none; contain: layout style; }
  .measure .page { transform: none; margin: 0; }
  .stage .page { content-visibility: auto; contain-intrinsic-size: 210mm 297mm; }
  .stage.no-wm .wm, body.no-wm .measure .wm { display: none; }

  .page {
    width: 210mm; height: 297mm; flex: none;
    background: #fff; color: var(--ink);
    font-family: Tinos, "Times New Roman", Times, serif;
    padding: var(--paper-pad);
    display: flex; flex-direction: column;
    position: relative; overflow: hidden;
    border-radius: 3mm;
    box-shadow: 0 8px 30px rgba(0,0,0,.08);
    page-break-after: always; break-after: page;
    transform-origin: top center;
    transform: scale(var(--sheet-scale));
    margin-bottom: calc((297mm * var(--sheet-scale)) - 297mm);
    margin-left: calc(((210mm * var(--sheet-scale)) - 210mm) / 2);
    margin-right: calc(((210mm * var(--sheet-scale)) - 210mm) / 2);
  }
  .page ::selection { background: rgba(142,27,42,.28); }

  .wm { position: absolute; left: 50%; top: 50%; width: 125mm; height: auto; transform: translate(-50%,-50%); opacity: .075; pointer-events: none; user-select: none; z-index: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .page > .hdr, .page > .body, .page > .foot { position: relative; z-index: 1; }

  /* ---- header ---- */
  .hdr { flex: none; background: linear-gradient(180deg, #9a1f2f 0%, var(--maroon) 55%, #7d1524 100%); color: #fff; border-radius: 3mm; padding: 5mm 7mm; margin-bottom: 4mm; display: flex; align-items: center; justify-content: space-between; gap: 6mm; box-shadow: 0 1.5mm 4mm rgba(110,18,32,.28); }
  .hdr-l { display: flex; align-items: center; gap: 5mm; min-width: 0; flex: 1 1 auto; }
  .hdr-icon { width: 15mm; height: 15mm; flex: none; }
  .hdr-title { flex: 1 1 auto; min-width: 0; overflow: hidden; font-size: 22pt; font-weight: 700; letter-spacing: .6pt; line-height: 1.05; text-transform: uppercase; overflow-wrap: anywhere; }
  .hdr-r { text-align: center; flex: none; }
  .hdr-badge { display: inline-block; background: rgba(0,0,0,.28); border: 1px solid rgba(255,255,255,.18); border-radius: 2mm; padding: 2mm 5mm; font-size: 11pt; font-weight: 700; letter-spacing: .3pt; text-transform: uppercase; white-space: nowrap; }
  .hdr-tag { margin-top: 2mm; font-size: 8pt; letter-spacing: 1.5pt; text-transform: uppercase; opacity: .95; white-space: nowrap; }
  .hdr.slim { padding: 3mm 6mm; margin-bottom: 3.5mm; }
  .hdr.slim .hdr-icon { width: 9mm; height: 9mm; }
  .hdr.slim .hdr-title { font-size: 15pt; }
  .hdr.slim .hdr-badge { font-size: 9pt; padding: 1.4mm 4mm; }
  .hdr.slim .hdr-tag { display: none; }

  /* ---- body: 2-column row grid ---- */
  .body { flex: 1 1 auto; min-height: 0; overflow: hidden; display: flex; flex-direction: column; position: relative; }
  .body::before { content: ""; position: absolute; top: 0; bottom: 0; left: 50%; width: .35mm; background: #b9b0b2; transform: translateX(-50%); }
  .rows { flex: none; display: grid; grid-template-columns: 1fr 1fr; column-gap: var(--col-gap); row-gap: 3mm; align-items: start; }
  .body.one-col .rows { grid-template-columns: 1fr; }
  .body.one-col::before { display: none; }
  .cell { min-width: 0; }
  .cell .q { margin-bottom: 0; }
  .cell .yr { margin-bottom: 2.6mm; }
  .body > .key { margin: 4mm 0 0; flex: none; }

  /* ---- footer ---- */
  .foot { flex: none; display: flex; justify-content: space-between; align-items: flex-end; gap: 6mm; padding-top: 2.2mm; margin-top: 2mm; border-top: .3mm solid #d8cfd1; font-size: 8pt; color: #7a6f71; letter-spacing: .3pt; line-height: 1.45; }
  .foot-l { min-width: 0; }
  .foot a { color: var(--maroon); text-decoration: none; font-weight: 700; }
  .foot .brand { font-size: 8.5pt; letter-spacing: .5pt; text-transform: uppercase; }
  .foot .wa { display: flex; align-items: center; gap: 1.6mm; margin-top: .6mm; }
  .foot .wa i { color: #1fa855; font-size: 9pt; }
  .foot .wa a { color: var(--ink); font-weight: 700; letter-spacing: .6pt; }
  .foot .pno { flex: none; white-space: nowrap; }

  /* ---- blocks ---- */
  .yr { background: var(--pink); color: var(--maroon); font-weight: 700; font-size: 15pt; letter-spacing: .2pt; border-radius: 2.2mm; padding: 1.9mm 5mm; margin: 0 0 3mm; }
  .topic { background: var(--pink); color: var(--maroon); border-left: 1.3mm solid var(--maroon); border-radius: 1.6mm; font-weight: 700; font-size: 11.5pt; line-height: 1.25; letter-spacing: .4pt; text-transform: uppercase; padding: 1.9mm 4mm; margin: 0 0 2.6mm; overflow-wrap: anywhere; }

  .q { margin: 0 0 3.2mm; break-inside: avoid; }
  .q-head { position: relative; padding-left: 4mm; min-height: 9.5mm; display: flex; align-items: center; }
  .q-badge { position: absolute; left: 0; top: 50%; transform: translateY(-50%); background: var(--maroon); color: #fff; font-weight: 700; font-size: 12pt; border-radius: 2mm; padding: 2.2mm 2.6mm; min-width: 14mm; text-align: center; line-height: 1; box-shadow: 0 1mm 2.5mm rgba(110,18,32,.35); z-index: 2; white-space: nowrap; }
  .q-text { flex: 1; background: var(--pink-2); border-radius: 2.2mm; padding: 1.8mm 4mm 1.8mm 13mm; font-weight: 700; font-size: var(--qf, 10.5pt); line-height: 1.3; color: var(--ink); text-align: justify; text-justify: inter-word; hyphens: auto; }
  .q-head.w2 .q-text { padding-left: 16mm; }
  .opts { list-style: none; margin: 2mm 0 0 4mm; padding: 0; }
  .opts li { display: flex; gap: 3mm; font-size: var(--qf, 10.5pt); line-height: 1.3; margin: 0 0 1.2mm; }
  .opts .l { flex: none; min-width: 6.5mm; }
  .opts .t { flex: 1; min-width: 0; overflow-wrap: anywhere; text-align: justify; text-justify: inter-word; hyphens: auto; }
  .opts li.ans .t { text-decoration: underline; text-decoration-color: var(--maroon); text-underline-offset: 1.2pt; }

  /* ---- question figures ---- */
  .q-figs { margin: 2mm 0 0 4mm; display: flex; flex-direction: column; gap: 1.6mm; }
  .q-figs.row { flex-direction: row; flex-wrap: wrap; align-items: flex-start; }
  .fig { margin: 0; }
  .fig.al-center { margin-left: auto; margin-right: auto; }
  .fig.al-right { margin-left: auto; }
  .fig img { display: block; width: 100%; height: auto; max-height: 90mm; object-fit: contain; border: .25mm solid #e6d6c6; border-radius: 1.2mm; background: #fff; }
  .fig figcaption { font-size: .82em; color: #5a4a4a; text-align: center; margin-top: 1mm; font-style: italic; line-height: 1.3; }
  .fig.missing { border: .4mm dashed #c9a9a9; border-radius: 1.5mm; padding: 3mm 2mm; text-align: center; color: #9a5a5a; font-size: 9pt; background: #fff8f8; }
  .fig.missing b { font-family: Consolas, monospace; color: var(--maroon); }

  /* ---- answer key ---- */
  .key { border: .4mm solid var(--maroon); border-radius: 2.2mm; padding: 3mm 4mm; margin: 0 0 4mm; break-inside: avoid; }
  .key h3 { font-family: Tinos, "Times New Roman", serif; text-transform: uppercase; text-align: center; color: var(--maroon); font-size: 12pt; letter-spacing: 1pt; margin-bottom: 2.4mm; }
  .body.has-key::before { bottom: calc(var(--key-h, 0px) + 4mm); }
  .body.key-only::before { display: none; }
  .body.key-only .rows { display: none; }
  .body.key-only > .key { margin-top: 0; }
  .body.key-only { overflow: visible; }
  .key-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.4mm 2mm; font-size: 10pt; }
  .key-grid span b { color: var(--maroon); }

  .empty-paper { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; color: var(--text-muted); font-size: 12pt; line-height: 1.8; padding: 10mm; }
  .empty-paper b { color: var(--primary); }
  .meta { font-size: .8rem; color: var(--text-muted); font-weight: 500; text-align: center; padding: 16px 10px 0; }

  .zoom-badge { position: fixed; right: 24px; bottom: 24px; z-index: 950; padding: 8px 16px; border-radius: 99px; background: #ffffff; border: 1px solid var(--line); box-shadow: 0 4px 10px rgba(0,0,0,0.08); color: var(--text-main); font-family: 'Oswald', sans-serif; font-size: .85rem; letter-spacing: 1px; pointer-events: none; opacity: 0; transform: translateY(10px); transition: all .3s ease; }
  .zoom-badge.show { opacity: 1; transform: translateY(0); }
  .toast { position: fixed; left: 50%; bottom: 26px; transform: translateX(-50%) translateY(15px); z-index: 1300; background: #0f172a; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 12px 20px; border-radius: 8px; font-size: .85rem; font-weight: 500; opacity: 0; transition: all .3s cubic-bezier(0.175, 0.885, 0.32, 1.275); pointer-events: none; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
  .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

  /* ---------------- responsive ---------------- */
  @media screen and (max-width: 1240px) {
    .editor-col { position: fixed; right: 0; top: 0; bottom: 0; z-index: 1100; flex-basis: auto; width: min(480px, 94vw); height: auto; max-height: none; padding: 16px; background: #ffffff; border-left: 1px solid var(--line); box-shadow: -8px 0 30px rgba(0,0,0,0.05); transform: translateX(100%); opacity: 1; transition: transform .34s var(--ease); }
    body.drawer-open .editor-col { transform: translateX(0); }
    body:not(.drawer-open) .editor-col { flex-basis: auto; width: min(480px, 94vw); padding: 16px; opacity: 1; transform: translateX(100%); }
    .scrim { display: block; position: fixed; inset: 0; z-index: 1090; background: rgba(15, 23, 42, 0.4); opacity: 0; visibility: hidden; transition: opacity .3s, visibility .3s; }
    body.drawer-open .scrim { opacity: 1; visibility: visible; }
    .preview-col { flex: 1 1 100%; width: 100%; }
  }
  @media screen and (max-width: 900px) {
    html { overflow-x: hidden; }
    body { height: auto; overflow: visible; display: block; }
    .toolbar { position: sticky; top: 0; }
    .workspace { height: auto; }
    .preview-col { width: 100%; height: auto; padding: 12px 10px 70px; overflow: visible; }
    .stage { overflow-x: hidden; }
    body.bar-hidden .toolbar { transform: translateY(-100%); }
    .row2 { grid-template-columns: 1fr; }
  }
  @media screen and (max-width: 640px) {
    .bar-inner { width: 96%; } .logo a { font-size: 1.25rem; } .actions { width: 100%; justify-content: space-between; }
    .btn { flex: 1 1 auto; justify-content: center; padding: 8px 10px; font-size: .76rem; }
  }
  @media (prefers-reduced-motion: reduce) { * { transition-duration: .01ms !important; } }
  @media print {
    html, body { background: #fff; height: auto; overflow: visible; display: block; }
    .toolbar, .nav-show, .editor-col, .side-tab, .scrim, .meta, .zoom-badge, .toast, .measure { display: none !important; }
    .workspace { display: block; margin: 0; height: auto; }
    .preview-col { padding: 0; overflow: visible; background: #fff; height: auto; }
    .stage { gap: 0; }
    .page { border-radius: 0; box-shadow: none; margin: 0 !important; transform: none !important; content-visibility: visible; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .page * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>

<header class="toolbar" id="topbar">
  <div class="bar-inner">
    <div class="logo"><a href="javascript:void(0)">MCQ <span>D2D</span></a></div>
    <div class="actions">
      <button type="button" class="btn ghost" id="btnHeaderLib"><i class="fa fa-folder-open"></i> Library</button>
      <button type="button" class="btn" id="btnHeaderNew" title="New sheet (Ctrl+Alt+N)"><i class="fa fa-plus"></i> New sheet</button>
      <a class="btn ghost" href="d2d_notes.php" title="Notes editor"><i class="fa fa-book-open"></i> Notes</a>
      <a class="btn ghost" href="admin_panel.php" title="Back to Admin Panel"><i class="fa fa-arrow-left"></i> Admin</a>
      <button type="button" class="btn ghost" id="btnRefreshPreview" title="Re-render preview (Ctrl+Enter)"><i class="fa fa-rotate"></i> Refresh</button>
      <button type="button" class="btn ghost" id="btnSample"><i class="fa fa-wand-magic-sparkles"></i> Sample</button>
      <button type="button" class="btn" id="btnPrint"><i class="fa fa-print"></i> Print / PDF</button>
      <button type="button" class="btn ghost" id="btnNavHide" title="Hide top bar (more space)"><i class="fa fa-angle-up"></i> Hide bar</button>
      <button type="button" class="btn danger" id="btnClear"><i class="fa fa-trash"></i> Clear</button>
    </div>
  </div>
</header>

<button type="button" class="nav-show" id="btnNavShow" title="Show top bar"><i class="fa fa-angle-down"></i> Menu</button>
<button type="button" class="side-tab" id="sideTab" aria-expanded="true" title="Open Editor"><i class="fa fa-angle-right tab-chev"></i><span class="tab-label">Editor</span></button>
<div class="scrim" id="scrim"></div>

<div class="workspace">
  <main class="preview-col" id="previewCol">
    <div class="measure" id="measure" aria-hidden="true"></div>
    <div class="stage" id="stage"></div>
    <div class="meta" id="meta"></div>
  </main>

  <aside class="editor-col">
    <div class="editor" id="editor">
      <div class="panel">
        <div class="panel-head">
          <h1><i class="fa fa-list-check"></i> MCQ D2D</h1>
          <button type="button" class="panel-close" id="panelClose" aria-label="Close editor" title="Close Panel"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="panel-body">
          <div class="status" id="status">Ready</div>

          <div class="sync" id="sync"><span class="dot"></span><span class="txt" id="syncTxt">Connecting…</span><button type="button" id="btnSaveNow" title="Save now (Ctrl+S)"><i class="fa fa-cloud-arrow-up"></i> Save</button></div>

          <div class="acc open" id="accLib">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-folder-tree"></i>&nbsp; Library <span class="chip" id="libCount">0</span></span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body">
              <div class="lib-top">
                <input class="inp" id="libSearch" type="search" placeholder="Search subject / chapter / title…" autocomplete="off">
                <button type="button" class="lib-new" id="btnNewSheet" title="New sheet"><i class="fa fa-plus"></i> New</button>
              </div>
              <div class="lib-list" id="libList"><div class="lib-empty">Loading…</div></div>
              <div class="lib-tools">
                <button type="button" id="btnLibRefresh" title="Reload list from server"><i class="fa fa-rotate"></i> Refresh</button>
                <button type="button" id="btnDuplicate" title="Copy current sheet as a new one"><i class="fa fa-copy"></i> Duplicate</button>
                <button type="button" id="btnTrash" title="Show deleted sheets"><i class="fa fa-trash-can"></i> Trash</button>
              </div>
            </div>
          </div>

          <div class="acc" id="accHelp">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-circle-info"></i>&nbsp; How to write MCQs</span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body">
              <p><code>Year: 2026</code> se naya year band. <code>Q1.</code> / <code>Q.1</code> / <code>1.</code> se naya question.
                 Options do tarah se — alag lines par <code>A)</code>–<code>D)</code>, <b>ya</b> ek hi line me
                 <code>(1) … (2) … (3) … (4) …</code>. Optional <code>Ans: C</code> / <code>Ans: 3</code> se answer key.
                 Beech me koi <b>CAPITAL</b> line ya <code>Topic: …</code> topic heading ban jati hai.
                 Image ke liye question ke andar line: <code>[img: name | 60%]</code> — Images panel se add karo.</p>
<pre>Year: 2026
Q1. The charge/mass ratio of electron
A) Depends on the nature of the electrodes
B) Depends upon nature of the gas
C) Remains constant
D) Depends on both
Ans: C

Q2. Diagram dekh ke bataye
[img: circuit | 70%]
(1) 2 A (2) 3 A (3) 4 A (4) 5 A
Ans: 2</pre>
            </div>
          </div>

          <div class="field"><label class="field-label" for="subjInput">Subject</label><input class="inp" id="subjInput" type="text" value="" placeholder="e.g. Chemistry — library isse group karti hai" autocomplete="off" list="subjList"><datalist id="subjList"></datalist></div>
          <div class="row3">
            <div class="field"><label class="field-label" for="chapInput">Chapter</label><input class="inp" id="chapInput" type="text" value="1" autocomplete="off"></div>
            <div class="field"><label class="field-label" for="titleInput">Sheet title</label><input class="inp" id="titleInput" type="text" value="Atomic Structure" autocomplete="off"></div>
          </div>
          <div class="row2">
            <div class="field"><label class="field-label" for="badgeInput">Badge</label><input class="inp" id="badgeInput" type="text" value="Chemistry PYQ Practice" autocomplete="off"></div>
            <div class="field"><label class="field-label" for="tagInput">Tagline</label><input class="inp" id="tagInput" type="text" value="Practice | Revise | Score High" autocomplete="off"></div>
          </div>

          <div class="checks">
            <label class="chk"><input type="checkbox" id="optAutoNum" checked> Auto-number Q1, Q2 …</label>
            <label class="chk"><input type="checkbox" id="optKey" checked> Answer key at end</label>
            <label class="chk"><input type="checkbox" id="optMark"> Underline correct option</label>
            <label class="chk"><input type="checkbox" id="optABCD"> Options as A) B) C) D)</label>
            <label class="chk"><input type="checkbox" id="optTwoCol" checked> 2 columns</label>
            <label class="chk"><input type="checkbox" id="optWM" checked> Watermark</label>
            <label class="chk" title="Off karo to bade sheet pe typing fast rahegi; preview Refresh se banega"><input type="checkbox" id="optLive" checked> Live preview</label>
            <label class="chk">Questions / page <input type="number" id="optPerPage" class="inp num" value="10" min="2" max="20" step="1"></label>
          </div>

          <div class="field">
            <label class="field-label" for="rawInput">MCQs</label>
            <textarea id="rawInput" spellcheck="false" placeholder="Year: 2026&#10;Q1. Question text…&#10;A) option&#10;B) option&#10;C) option&#10;D) option&#10;Ans: B"></textarea>
          </div>

          <div class="acc open" id="accList">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-list-ol"></i>&nbsp; Questions <span class="chip" id="qCount">0</span></span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body"><div class="qlist" id="qList"></div></div>
          </div>

          <div class="acc" id="accImages">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-image"></i>&nbsp; Images <span class="chip" id="imgCount">0</span></span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body">
              <input type="file" id="imgFile" accept="image/*" multiple hidden>
              <div class="drop" id="imgDrop"><b><i class="fa fa-plus"></i> Add image</b> — click karo ya photo yahan drop karo<br><span style="font-size:.7rem">PNG / JPG / screenshot · auto-compress hoti hai</span></div>
              <div class="imgs" id="imgList"></div>
              <p style="margin-top:8px">Image ko kisi question me daalne ke liye niche se <b>question chuno</b> → <b>Insert</b>. Text me line banti hai: <code>[img: name | 60%]</code> — use question ke andar kahin bhi move kar sakte ho.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </aside>

  <div class="zoom-badge" id="zoomBadge">100%</div>
  <div class="toast" id="toast"></div>
  <div class="modal-scrim" id="modalScrim"><div class="modal"><h3><i class="fa fa-triangle-exclamation"></i> <span id="modalTitle">Unsaved draft mila</span></h3><p id="modalBody"></p><div class="row" id="modalRow"></div></div></div>
</div>

<script>
(function () {
  "use strict";

  var $ = function (id) { return document.getElementById(id); };
  var ta = $("rawInput"), stage = $("stage"), statusEl = $("status"), metaEl = $("meta");

  function setStatus(t) { statusEl.textContent = t; }
  function esc(s) { return String(s == null ? "" : s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
  var toastT = null;
  function toast(msg) { var t = $("toast"); t.textContent = msg; t.classList.add("show"); if (toastT) clearTimeout(toastT); toastT = setTimeout(function () { t.classList.remove("show"); }, 1800); }

  var SITE_URL = "https://diplomawallah.in/", WA_NUMBER = "9153950552";
  var WA_LINK = "https://wa.me/91" + WA_NUMBER + "?text=" + encodeURIComponent("Hi, I want to join the D2D batch");

  var SAMPLE = [
    "Year: 2026",
    "Q1. The charge/mass ratio of electron",
    "A) Depends on the nature of the electrodes",
    "B) Depends upon nature of the gas",
    "C) Remains constant",
    "D) Depends on both nature of the gas and nature of the electrode",
    "Ans: C",
    "Q2. An element has atomic number 11 and mass number 24. What does the nucleus contain?",
    "A) 11 protons, 13 neutrons",
    "B) 11 protons, 24 neutrons",
    "C) 13 protons, 11 neutrons",
    "D) 13 protons, 11 electrons",
    "Ans: A",
    "Q3. The introduction of a neutron in the nucleus of an atom would lead to the change in",
    "A) Atomic weight",
    "B) Atomic number",
    "C) Number of electrons around a nucleus",
    "D) None of these",
    "Ans: A",
    "Q4. The spectral lines of hydrogen are explained by which of the following models?",
    "A) Rutherford's model",
    "B) Thomson's model",
    "C) Bohr's model",
    "D) Dalton's model",
    "Ans: C",
    "Q5. Which of the following quantum numbers describes the orientation of an orbital?",
    "A) Principal Quantum Number",
    "B) Azimuthal Quantum Number",
    "C) Magnetic Quantum Number",
    "D) Spin Quantum Number",
    "Ans: C",
    "Q6. The nuclear stability of an atom mainly depends upon which of the following factors?",
    "A) Number of electrons present in the outermost shell only",
    "B) Ratio of neutrons to protons in the nucleus",
    "C) Number of valence electrons involved in bonding",
    "D) Physical state of the element at room temperature",
    "Ans: B",
    "Year: 2025",
    "Q7. As we move away from the nucleus, the energy of the orbits",
    "A) Decreases",
    "B) Remains constant",
    "C) Goes on increasing",
    "D) Fluctuates randomly",
    "Ans: C",
    "Q8. Which series of hydrogen spectrum appears in visible region?",
    "A) Balmer series",
    "B) Lyman series",
    "C) Paschen series",
    "D) Brackett series",
    "Ans: A",
    "Q9. Heisenberg won the Nobel Prize in 1932 for the",
    "A) Theory of relativity",
    "B) Quantum mechanics",
    "C) Discovery of the electron",
    "D) Uncertainty principle",
    "Ans: B"
  ].join("\n");

  /* =====================================================================
     PARSER
  ===================================================================== */
  var RE_YEAR  = /^\s*year\s*[:\-–]?\s*(\d{4})\b/i;
  var RE_Q     = /^\s*(?:Q\.?\s*)?(\d{1,3})\s*[\.\)\:\-–]\s*(.*)$/i;
  var RE_OPT_A = /^\s*\(?([A-Da-d])[\)\.\:\-–]\s*(.*)$/;
  var RE_OPT_N = /^\s*\(([1-4])\)\s*(.*)$|^\s*([1-4])\)\s*(.*)$/;
  var RE_ANS   = /^\s*(?:ans(?:wer)?|key|correct)\s*[:\-–]?\s*\(?([1-4A-Da-d])\)?\s*\.?\s*$/i;
  var RE_TOPIC = /^\s*(?:topic|chapter|section|unit)\s*[:\-–]\s*(.+)$/i;
  var RE_IMG   = /^\s*\[(?:img|image)\s*:\s*([^\]|]+?)\s*(?:\|([^\]]*))?\]\s*$/i;

  function looksLikeHeading(t) {
    if (t.length > 90 || /[?]$/.test(t)) return false;
    var letters = t.replace(/[^A-Za-z]/g, "");
    if (letters.length < 3) return false;
    var upper = letters.replace(/[^A-Z]/g, "").length;
    return upper / letters.length >= 0.7;
  }
  function optIndex(l) {
    l = String(l || "").toUpperCase();
    if (/^[1-4]$/.test(l)) return parseInt(l, 10) - 1;
    if (/^[A-D]$/.test(l)) return l.charCodeAt(0) - 65;
    return -1;
  }
  function parseImgLine(t) {
    var m = t.match(RE_IMG);
    if (!m) return null;
    var b = { name: m[1].trim(), w: "", align: "center", cap: "" };
    (m[2] || "").split("|").forEach(function (p) {
      p = p.trim(); if (!p) return;
      if (/^\d{1,3}\s*%$/.test(p)) b.w = p.replace(/\s/g, "");
      else if (/^(left|right|center|centre)$/i.test(p)) b.align = p.toLowerCase().replace("centre", "center");
      else if (/^(row|side|inline)$/i.test(p)) b.row = true;
      else b.cap = b.cap ? b.cap + " | " + p : p;
    });
    return b;
  }

  var RE_INLINE = /(?:^|\s)\(?([1-4A-Da-d])\)\s*/g;
  function splitInline(text) {
    var found = [], m;
    RE_INLINE.lastIndex = 0;
    while ((m = RE_INLINE.exec(text))) found.push({ idx: optIndex(m[1]), l: m[1], at: m.index, end: m.index + m[0].length });
    var start = -1;
    for (var i = 0; i < found.length; i++) if (found[i].idx === 0) { start = i; break; }
    if (start < 0) return null;
    var run = [found[start]];
    for (var j = start + 1; j < found.length; j++) {
      if (found[j].idx === run[run.length - 1].idx + 1) run.push(found[j]); else break;
    }
    if (run.length < 2) return null;
    var q = text.slice(0, run[0].at).trim();
    var opts = run.map(function (r, k) {
      var stop = k + 1 < run.length ? run[k + 1].at : text.length;
      return { l: r.l.toUpperCase(), t: text.slice(r.end, stop).trim() };
    });
    return { text: q, opts: opts };
  }

  function isQuestionLine(t, cur) {
    if (!RE_Q.test(t) || RE_OPT_A.test(t) || RE_ANS.test(t)) return false;
    var n = t.match(RE_OPT_N);
    if (n && cur) {
      var num = parseInt(n[1] || n[3], 10);
      if (num === cur.opts.length + 1) return false;
    }
    return true;
  }

  function parse(text) {
    var lines = String(text || "").replace(/\r\n/g, "\n").split("\n");
    var out = [], cur = null;

    function push() {
      if (!cur) return;
      cur.text = cur.text.trim();
      if (!cur.opts.length) {
        var s = splitInline(cur.text);
        if (s) { cur.text = s.text; cur.opts = s.opts; }
      }
      if (cur.text || cur.opts.length || cur.imgs.length) out.push(cur);
      cur = null;
    }

    for (var i = 0; i < lines.length; i++) {
      var t = lines[i].replace(/```/g, "").trim();
      if (!t) continue;
      var m;

      if ((m = t.match(RE_YEAR))) { push(); out.push({ type: "year", year: m[1], srcLine: i }); continue; }
      if ((m = t.match(RE_TOPIC))) { push(); out.push({ type: "topic", text: m[1].trim(), srcLine: i }); continue; }

      var ib = parseImgLine(t);
      if (ib) {
        ib.srcLine = i;
        if (cur) cur.imgs.push(ib);
        else { cur = { type: "q", num: 0, text: "", opts: [], imgs: [ib], ans: "", srcLine: i }; }
        continue;
      }

      if ((m = t.match(RE_ANS)) && cur) { cur.ans = m[1].toUpperCase(); continue; }

      if (looksLikeHeading(t) && !RE_Q.test(t) && !RE_OPT_A.test(t) && (!cur || cur.ans || cur.opts.length >= 2)) {
        push(); out.push({ type: "topic", text: t, srcLine: i }); continue;
      }
      if (isQuestionLine(t, cur)) {
        push();
        m = t.match(RE_Q);
        cur = { type: "q", num: parseInt(m[1], 10), text: m[2] || "", opts: [], imgs: [], ans: "", srcLine: i };
        continue;
      }
      if (cur && (RE_OPT_A.test(t) || RE_OPT_N.test(t))) {
        var inl = !cur.opts.length ? splitInline(t) : null;
        if (inl && !inl.text && inl.opts.length >= 2) { inl.opts.forEach(function (o) { cur.opts.push(o); }); continue; }
        if ((m = t.match(RE_OPT_A))) { cur.opts.push({ l: m[1].toUpperCase(), t: m[2] }); continue; }
        m = t.match(RE_OPT_N); cur.opts.push({ l: (m[1] || m[3]), t: (m[2] || m[4] || "") }); continue;
      }

      if (!cur) { cur = { type: "q", num: 0, text: t, opts: [], imgs: [], ans: "", srcLine: i }; }
      else if (cur.opts.length) { cur.opts[cur.opts.length - 1].t += " " + t; }
      else { cur.text += (cur.text ? " " : "") + t; }
    }
    push();
    return out;
  }

  /* =====================================================================
     BLOCK BUILDERS
  ===================================================================== */
  var ATOM = '<svg class="hdr-icon" viewBox="0 0 64 64" fill="none" stroke="#fff" stroke-width="2.6" aria-hidden="true">' +
    '<circle cx="32" cy="32" r="4.2" fill="#fff" stroke="none"/>' +
    '<ellipse cx="32" cy="32" rx="26" ry="10"/>' +
    '<ellipse cx="32" cy="32" rx="26" ry="10" transform="rotate(60 32 32)"/>' +
    '<ellipse cx="32" cy="32" rx="26" ry="10" transform="rotate(120 32 32)"/></svg>';

  function el(cls, html) { var d = document.createElement("div"); d.className = cls; if (html != null) d.innerHTML = html; return d; }

  function headerNode(slim) {
    var d = el("hdr" + (slim ? " slim" : ""));
    d.innerHTML =
      '<div class="hdr-l">' + ATOM + '<div class="hdr-title">' + esc($("titleInput").value || "MCQ Practice") + "</div></div>" +
      '<div class="hdr-r"><div class="hdr-badge">' + esc($("badgeInput").value || "PYQ Practice") + "</div>" +
      '<div class="hdr-tag">' + esc($("tagInput").value || "") + "</div></div>";
    return d;
  }
  function yearNode(y) { var d = el("yr"); d.textContent = "Year: " + y; return d; }
  function topicNode(text) { var d = el("topic"); d.textContent = text; return d; }

  function optLabel(l, normalise) {
    var i = optIndex(l);
    if (normalise && i >= 0) return String.fromCharCode(65 + i) + ")";
    return /^[1-4]$/.test(l) ? "(" + l + ")" : l + ")";
  }

  function figHtml(b) {
    var im = findImage(b.name);
    if (!im) return '<figure class="fig missing"><i class="fa fa-image"></i> Image <b>' + esc(b.name) + "</b> nahi mili</figure>";
    var w = b.w || "100%";
    return '<figure class="fig al-' + esc(b.align) + '" style="width:' + esc(w) + '">' +
      '<img src="' + im.data + '" width="' + im.w + '" height="' + im.h + '" alt="' + esc(b.name) + '">' +
      (b.cap ? "<figcaption>" + esc(b.cap) + "</figcaption>" : "") + "</figure>";
  }

  function qNode(q, label, mark) {
    var d = el("q");
    var wide = String(label).length > 3;
    var norm = $("optABCD").checked;
    var ansIdx = optIndex(q.ans);
    var opts = q.opts.map(function (o) {
      var isAns = mark && ansIdx >= 0 && optIndex(o.l) === ansIdx;
      return '<li' + (isAns ? ' class="ans"' : "") + '><span class="l">' + esc(optLabel(o.l, norm)) + '</span><span class="t">' + esc(o.t) + "</span></li>";
    }).join("");
    var imgs = (q.imgs && q.imgs.length)
      ? '<div class="q-figs' + (q.imgs.length > 1 && q.imgs.some(function (b) { return b.row; }) ? " row" : "") + '">' + q.imgs.map(figHtml).join("") + "</div>"
      : "";
    d.innerHTML =
      '<div class="q-head' + (wide ? " w2" : "") + '"><span class="q-badge">' + esc(label) + '</span>' +
      '<div class="q-text">' + esc(q.text) + "</div></div>" + imgs +
      (opts ? '<ul class="opts">' + opts + "</ul>" : "");
    return d;
  }

  function keyNode(entries) {
    var d = el("key");
    d.innerHTML = "<h3>Answer Key</h3><div class=\"key-grid\">" +
      entries.map(function (e) { return "<span><b>" + esc(e.label) + "</b> – " + esc(e.ans) + "</span>"; }).join("") + "</div>";
    return d;
  }

  /* =====================================================================
     PAGINATION
  ===================================================================== */
  function newPage(no, slim) {
    var p = document.createElement("section");
    p.className = "page";

    var wm = document.createElement("img");
    wm.className = "wm"; wm.src = "diplomawallah-logo.png"; wm.alt = ""; wm.setAttribute("aria-hidden", "true");
    p.appendChild(wm);

    p.appendChild(headerNode(slim));
    var body = el("body"); if (!$("optTwoCol").checked) body.classList.add("one-col");
    var rows = el("rows");
    body.appendChild(rows); p.appendChild(body);

    var f = el("foot");
    f.innerHTML =
      '<div class="foot-l">' +
        '<div class="brand"><a href="' + SITE_URL + '" target="_blank" rel="noopener">Diploma Wallah</a></div>' +
        '<div class="wa"><i class="fab fa-whatsapp" aria-hidden="true"></i>' +
          'Contact on WhatsApp for D2D batch: <a href="' + WA_LINK + '" target="_blank" rel="noopener">' + WA_NUMBER + "</a></div>" +
      "</div>" +
      '<span class="pno">Page ' + no + "</span>";
    p.appendChild(f);

    $("measure").appendChild(p);
    return { el: p, body: body, rows: rows };
  }

  function overflows(e) { return e.scrollHeight > e.clientHeight + 0.5; }

  /** Step the page's question font down (to a floor) until its content fits. */
  function fitPage(page) {
    var size = 10.5;
    page.el.style.setProperty("--qf", size + "pt");
    if (!overflows(page.body)) return;
    while (size > 8.25 && overflows(page.body)) { size -= 0.25; page.el.style.setProperty("--qf", size + "pt"); }
  }

  var titleFitCache = {};
  function fitTitle(hdr) {
    if (!hdr) return;
    var t = hdr.querySelector(".hdr-title"), slim = hdr.classList.contains("slim");
    var key = (slim ? "s:" : "b:") + t.textContent;
    var c = titleFitCache[key];
    if (c) { t.style.whiteSpace = c.ws; t.style.fontSize = c.fs; return; }
    var max = slim ? 15 : 22, min = slim ? 11 : 15, size = max;
    t.style.whiteSpace = "nowrap"; t.style.fontSize = size + "pt";
    while (size > min && t.scrollWidth > t.clientWidth + 1) { size -= 0.5; t.style.fontSize = size + "pt"; }
    if (t.scrollWidth > t.clientWidth + 1) t.style.whiteSpace = "normal";
    titleFitCache[key] = { ws: t.style.whiteSpace, fs: t.style.fontSize };
  }

  /** lay a slice of questions into the page's grid (left column first, then right) */
  function layout(page, list, twoCol) {
    page.rows.innerHTML = "";
    var rowsPer = twoCol ? Math.ceil(list.length / 2) : list.length;
    list.forEach(function (e, i) {
      var cell = el("cell");
      if (e.year) cell.appendChild(e.year);
      if (e.topic) cell.appendChild(e.topic);
      cell.appendChild(e.q);
      cell.style.gridColumn = (!twoCol || i < rowsPer) ? "1" : "2";
      cell.style.gridRow = String((i % rowsPer) + 1);
      page.rows.appendChild(cell);
    });
  }

  /** how many of `list` fit on this page — the font shrinks first, questions
      move to the next page only when even the smallest size overflows */
  function fillPage(page, list, twoCol) {
    layout(page, list, twoCol);
    fitPage(page);
    if (!overflows(page.body)) return list.length;
    var lo = 1, hi = list.length - 1, best = 1;
    while (lo <= hi) {
      var mid = (lo + hi) >> 1;
      layout(page, list.slice(0, mid), twoCol);
      fitPage(page);
      if (!overflows(page.body)) { best = mid; lo = mid + 1; } else hi = mid - 1;
    }
    layout(page, list.slice(0, best), twoCol);
    fitPage(page);
    return best;
  }

  /** largest prefix of the answer key that still fits on this page */
  function fitKey(page, entries) {
    var k = keyNode(entries);
    page.body.appendChild(k);
    if (!overflows(page.body)) return { node: k, used: entries.length };
    var lo = 1, hi = entries.length - 1, best = 0;
    while (lo <= hi) {
      var mid = (lo + hi) >> 1;
      k.remove(); k = keyNode(entries.slice(0, mid)); page.body.appendChild(k);
      if (!overflows(page.body)) { best = mid; lo = mid + 1; } else hi = mid - 1;
    }
    k.remove();
    if (!best) return { node: null, used: 0 };
    k = keyNode(entries.slice(0, best)); page.body.appendChild(k);
    return { node: k, used: best };
  }

  function paginate(entries, keyEntries, perPage) {
    stage.innerHTML = ""; $("measure").innerHTML = "";
    titleFitCache = {};
    var done = document.createDocumentFragment();
    function finish(pg) { if (pg && pg.el.parentNode !== done) done.appendChild(pg.el); }

    if (!entries.length && !(keyEntries && keyEntries.length)) {
      var p0 = newPage(1, false);
      p0.body.innerHTML = '<div class="empty-paper">Editor me MCQs paste karo.<br><b>Year: 2026</b> → <b>Q1.</b> → <b>A) B) C) D)</b></div>';
      fitTitle(p0.el.querySelector(".hdr"));
      finish(p0); stage.appendChild(done);
      return 1;
    }

    var twoCol = $("optTwoCol").checked;
    var pageNo = 0, page = null, i = 0;

    while (i < entries.length) {
      if (page) finish(page);
      pageNo++;
      page = newPage(pageNo, pageNo > 1);
      fitTitle(page.el.querySelector(".hdr"));
      var slice = entries.slice(i, i + perPage);
      i += fillPage(page, slice, twoCol);
    }

    // answer key: under the last grid if a chunk fits, then its own pages
    var rest = (keyEntries || []).slice();
    if (rest.length && page) {
      var r = fitKey(page, rest);
      if (r.used) {
        rest = rest.slice(r.used);
        page.body.classList.add(page.rows.childElementCount ? "has-key" : "key-only");
        if (page.rows.childElementCount) page.body.style.setProperty("--key-h", r.node.offsetHeight + "px");
      }
    }
    while (rest.length) {
      if (page) finish(page);
      pageNo++;
      page = newPage(pageNo, true);
      fitTitle(page.el.querySelector(".hdr"));
      var r2 = fitKey(page, rest);
      page.body.classList.add("key-only");
      if (!r2.used) break;                       // nothing fits even alone — bail instead of looping
      rest = rest.slice(r2.used);
    }

    finish(page);
    stage.appendChild(done);

    var pages = stage.querySelectorAll(".page");
    pages.forEach(function (pg, idx) {
      var pn = pg.querySelector(".pno"); if (pn) pn.textContent = "Page " + (idx + 1) + " / " + pages.length;
    });
    return pages.length;
  }

  /* =====================================================================
     RENDER
  ===================================================================== */
  var lastItems = [];
  var renderBusy = false, renderAgain = false;
  function render() {
    if (renderBusy) { renderAgain = true; return; }
    renderBusy = true;
    try { renderNow(); } finally { renderBusy = false; }
    if (renderAgain) { renderAgain = false; schedule(); }
  }
  function renderNow() {
    var items = parse(ta.value);
    lastItems = items;

    var autoNum = $("optAutoNum").checked, showKey = $("optKey").checked, mark = $("optMark").checked;
    var perPage = Math.max(2, Math.min(20, parseInt($("optPerPage").value, 10) || 10));
    var entries = [], keyEntries = [], n = 0, qCount = 0, years = 0, topics = 0, imgsUsed = 0;
    var pendingYear = null, pendingTopic = null;

    items.forEach(function (it) {
      if (it.type === "year") { years++; pendingYear = yearNode(it.year); return; }
      if (it.type === "topic") { topics++; pendingTopic = topicNode(it.text); return; }
      n++; qCount++;
      imgsUsed += (it.imgs ? it.imgs.length : 0);
      var label = "Q" + (autoNum ? n : (it.num || n));
      it.label = label;
      entries.push({ year: pendingYear, topic: pendingTopic, q: qNode(it, label, mark) });
      pendingYear = null; pendingTopic = null;
      if (it.ans) {
        var ai = optIndex(it.ans);
        var shown = ($("optABCD").checked && ai >= 0) ? String.fromCharCode(65 + ai) : it.ans;
        keyEntries.push({ label: label.replace(/^Q/, ""), ans: shown });
      }
    });

    var pages = paginate(entries, showKey ? keyEntries : [], perPage);
    renderList(items);
    syncImgCards();

    metaEl.textContent = qCount + " question" + (qCount === 1 ? "" : "s") +
      (topics ? " · " + topics + " topic" + (topics === 1 ? "" : "s") : "") +
      (years ? " · " + years + " year band" + (years === 1 ? "" : "s") : "") +
      (imgsUsed ? " · " + imgsUsed + " image" + (imgsUsed === 1 ? "" : "s") : "") +
      " → " + pages + " page" + (pages === 1 ? "" : "s");
    setStatus(qCount ? qCount + " MCQs on " + pages + " A4 page" + (pages === 1 ? "" : "s") + ". Print / PDF ready." : "Paste MCQs to begin.");
  }

  function renderList(items) {
    var qs = items.filter(function (i) { return i.type === "q"; });
    $("qCount").textContent = qs.length;
    if (!qs.length) { $("qList").innerHTML = '<div class="lib-empty">Koi question nahi mila.</div>'; return; }
    var year = "", html = "";
    items.forEach(function (it) {
      if (it.type === "year") { year = it.year; return; }
      if (it.type === "topic") { html += '<button type="button" class="qsep" data-line="' + it.srcLine + '">' + esc(it.text) + "</button>"; return; }
      html += '<button type="button" class="qitem" data-line="' + it.srcLine + '">' +
        '<span class="n">' + esc(it.label || "") + '</span><span class="t">' + esc(it.text) + "</span>" +
        (it.imgs && it.imgs.length ? '<span class="im"><i class="fa fa-image"></i> ' + it.imgs.length + "</span>" : "") +
        (year ? '<span class="y">' + esc(year) + "</span>" : "") + "</button>";
    });
    $("qList").innerHTML = html;
  }

  /* caret position inside the textarea, measured with a wrapping mirror */
  function caretTopIn(pos) {
    var cs = getComputedStyle(ta), m = document.createElement("div");
    ["fontFamily","fontSize","fontWeight","fontStyle","lineHeight","letterSpacing","wordSpacing",
     "paddingTop","paddingRight","paddingBottom","paddingLeft","textIndent","tabSize"]
      .forEach(function (p) { m.style[p] = cs[p]; });
    m.style.cssText += ";position:absolute;top:0;left:-99999px;visibility:hidden;box-sizing:content-box;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;";
    m.style.width = (ta.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight)) + "px";
    m.textContent = ta.value.slice(0, pos);
    var mark = document.createElement("span"); mark.textContent = "​"; m.appendChild(mark);
    document.body.appendChild(m); var y = mark.offsetTop; document.body.removeChild(m); return y;
  }
  function lineBounds(line) {
    var lines = ta.value.split("\n"), start = 0;
    for (var i = 0; i < line && i < lines.length; i++) start += lines[i].length + 1;
    return { start: start, end: start + (lines[line] || "").length };
  }
  function jumpToLine(line, selectAll) {
    var b = lineBounds(line);
    ta.focus();
    ta.scrollTop = Math.max(0, caretTopIn(b.start) - Math.round(ta.clientHeight * 0.3));
    ta.setSelectionRange(b.start, selectAll ? b.end : b.start);
  }
  $("qList").addEventListener("click", function (e) {
    var b = e.target.closest(".qitem, .qsep"); if (!b) return;
    var lineNo = parseInt(b.getAttribute("data-line"), 10);
    jumpToLine(lineNo, true);
    $("qList").querySelectorAll(".flash").forEach(function (n) { n.classList.remove("flash"); });
    b.classList.add("flash");
  });

  /* =====================================================================
     PERSISTENCE
  ===================================================================== */
  var API = (window.PM_MCQ && window.PM_MCQ.api) || "d2d_mcq_api.php";
  var FIELDS = ["subjInput","chapInput","titleInput","badgeInput","tagInput"];
  var FIELD_MAP = { subjInput: "subject", chapInput: "chapter_no", titleInput: "title", badgeInput: "badge", tagInput: "tagline" };
  var OPTS = ["optAutoNum","optKey","optMark","optABCD","optTwoCol","optWM"];

  var cur = { id: 0, version: 0, updated_at: null };
  var dirty = false, saving = false, pendingSave = false, saveTimer = null, retryTimer = null, retryDelay = 2000;
  var online = navigator.onLine !== false;
  var libSheets = [], libFilter = "", showingTrash = false;
  var suppressSave = false, imagesDirty = false;

  function syncUI(state, text) { $("sync").className = "sync " + state; $("syncTxt").textContent = text; }
  function fmtAgo(ts) {
    if (!ts) return "";
    var d = new Date(String(ts).replace(" ", "T")); if (isNaN(d)) return "";
    var s = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (s < 5) return "just now"; if (s < 60) return s + "s ago";
    var m = Math.floor(s / 60); if (m < 60) return m + "m ago";
    var h = Math.floor(m / 60); if (h < 24) return h + "h ago";
    return d.toLocaleDateString("en-IN", { day: "numeric", month: "short" });
  }

  function collect(forServer) {
    var n = { id: cur.id, version: cur.version, mcq_text: ta.value,
              settings: { o: {}, perPage: $("optPerPage").value, live: $("optLive").checked } };
    FIELDS.forEach(function (id) { n[FIELD_MAP[id]] = $(id).value; });
    OPTS.forEach(function (id) { n.settings.o[id] = $(id).checked; });
    var out = { sheet: n };
    if (!forServer || imagesDirty || !cur.id) out.images = images;
    return out;
  }
  function applySheet(sheet, imgs) {
    suppressSave = true;
    try {
      FIELDS.forEach(function (id) { $(id).value = (sheet && sheet[FIELD_MAP[id]] != null) ? String(sheet[FIELD_MAP[id]]) : ""; });
      ta.value = (sheet && typeof sheet.mcq_text === "string") ? sheet.mcq_text : "";
      var st = (sheet && sheet.settings) || {};
      OPTS.forEach(function (id) { $(id).checked = (st.o && typeof st.o[id] === "boolean") ? st.o[id] : $(id).defaultChecked; });
      $("optPerPage").value = st.perPage || $("optPerPage").defaultValue;
      $("optLive").checked = st.live !== false; document.body.classList.toggle("live-off", st.live === false);
      images = Array.isArray(imgs) ? imgs.filter(function (x) { return x && x.name && x.data; }) : [];
      imagesDirty = false;
      applyWatermark(); renderImages(); render();
    } finally { suppressSave = false; }
  }

  function draftKey(id) { return "mcq_draft_" + (id || "new"); }
  function draftImgKey(id) { return "mcq_draftimg_" + (id || "new"); }
  function saveLocal() {
    try {
      var c = collect(); c.sheet.savedAt = Date.now(); c.sheet.dirty = dirty;
      localStorage.setItem(draftKey(cur.id), JSON.stringify(c.sheet));
      localStorage.setItem("mcq_last_id", String(cur.id || 0));
    } catch (e) {}
  }
  function saveLocalImages() {
    try { localStorage.setItem(draftImgKey(cur.id), JSON.stringify(images)); return true; }
    catch (e) { toast("Local cache full — images sirf server pe save hongi"); return false; }
  }
  function readDraft(id) {
    try {
      var d = JSON.parse(localStorage.getItem(draftKey(id)) || "null");
      var im = JSON.parse(localStorage.getItem(draftImgKey(id)) || "null");
      if (d) d._images = Array.isArray(im) ? im : null;
      return d;
    } catch (e) { return null; }
  }
  function clearDraft(id) { try { localStorage.removeItem(draftKey(id)); localStorage.removeItem(draftImgKey(id)); } catch (e) {} }

  function saveState() {
    if (suppressSave) return;
    dirty = true; saveLocal();
    syncUI("dirty", "Unsaved changes… (auto-save 1.5s)");
    scheduleSave();
  }
  function scheduleSave() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(function () { saveToServer("auto"); }, 1500);
  }

  function api(action, body, method) {
    var opt = { method: method || "POST", credentials: "same-origin", headers: {} };
    if (body !== undefined) { opt.headers["Content-Type"] = "application/json"; opt.body = JSON.stringify(body); }
    return fetch(API + "?action=" + encodeURIComponent(action), opt).then(function (r) {
      return r.text().then(function (t) { var j = null; try { j = JSON.parse(t); } catch (e) {} return { ok: r.ok, status: r.status, json: j, text: t }; });
    });
  }

  function saveToServer(reason) {
    if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
    if (!dirty) return Promise.resolve(true);
    if (saving) { pendingSave = true; return Promise.resolve(false); }
    if (!online) { syncUI("offline", "Offline — draft local me safe hai, net aate hi save hoga"); armRetry(); return Promise.resolve(false); }

    saving = true; syncUI("saving", "Saving…");
    var payload = collect(true), sentImages = "images" in payload;
    return api("save", payload).then(function (r) {
      saving = false;
      if (r.ok && r.json && r.json.status === "success") {
        var wasNew = !cur.id;
        if (sentImages) imagesDirty = false;
        cur.id = r.json.id; cur.version = r.json.version; cur.updated_at = r.json.updated_at;
        dirty = false; retryDelay = 2000;
        clearDraft(wasNew ? 0 : cur.id); clearDraft(cur.id); saveLocal();
        syncUI("saved", "Saved · " + fmtAgo(cur.updated_at) + (reason === "manual" ? " (manual)" : ""));
        touchLibEntry(); if (wasNew) loadList(true);
        if (pendingSave) { pendingSave = false; dirty = true; return saveToServer("auto"); }
        return true;
      }
      if (r.status === 401) { syncUI("error", "Login expire ho gaya — admin_panel.php me dobara login karo. Draft local me safe hai."); return false; }
      if (r.status === 409 && r.json && r.json.code === "version_conflict") { showConflict(r.json.server_version); return false; }
      syncUI("error", "Save failed: " + ((r.json && r.json.msg) || ("HTTP " + r.status)) + " — retrying");
      armRetry(); return false;
    }).catch(function () {
      saving = false; online = false;
      syncUI("offline", "Connection lost — draft safe, retrying…"); armRetry(); return false;
    });
  }
  function armRetry() {
    if (retryTimer) clearTimeout(retryTimer);
    retryTimer = setTimeout(function () {
      retryTimer = null; retryDelay = Math.min(retryDelay * 1.8, 30000);
      api("ping").then(function (r) { if (r.ok) { online = true; if (dirty) saveToServer("retry"); } else armRetry(); }, armRetry);
    }, retryDelay);
  }
  window.addEventListener("online", function () { online = true; toast("Wapas online"); if (dirty) saveToServer("reconnect"); });
  window.addEventListener("offline", function () { online = false; syncUI("offline", "Offline — draft local me safe hai"); });
  window.addEventListener("beforeunload", function (e) { if (dirty) { saveLocal(); e.preventDefault(); e.returnValue = ""; } });
  document.addEventListener("visibilitychange", function () { if (document.hidden && dirty) { saveLocal(); saveToServer("blur"); } });

  function showModal(title, body, buttons) {
    $("modalTitle").textContent = title; $("modalBody").innerHTML = body;
    var row = $("modalRow"); row.innerHTML = "";
    buttons.forEach(function (b) {
      var e = document.createElement("button"); e.type = "button"; e.className = "btn" + (b.ghost ? " ghost" : "");
      e.innerHTML = b.html; e.addEventListener("click", function () { hideModal(); b.fn && b.fn(); }); row.appendChild(e);
    });
    $("modalScrim").classList.add("show");
  }
  function hideModal() { $("modalScrim").classList.remove("show"); }
  function showConflict(serverVersion) {
    syncUI("error", "Conflict — server pe nayi copy hai");
    showModal("Server pe nayi version hai",
      "Is sheet ko kisi doosre tab/device se save kiya gaya (v" + serverVersion + "). Aap kya rakhna chahte ho?<br><br><b>Mera rakho</b> — server wali overwrite hogi.<br><b>Server wala lo</b> — aapke abhi ke changes local draft me rahenge.",
      [
        { html: '<i class="fa fa-cloud-arrow-down"></i> Server wala lo', ghost: true, fn: function () { try { localStorage.setItem("mcq_conflict_backup_" + cur.id + "_" + Date.now(), JSON.stringify(collect())); } catch (e) {} loadSheet(cur.id, { ignoreDraft: true }); } },
        { html: '<i class="fa fa-cloud-arrow-up"></i> Mera rakho', fn: function () { cur.version = serverVersion; dirty = true; imagesDirty = true; saveToServer("force"); } }
      ]);
  }

  function loadSheet(id, opts) {
    opts = opts || {};
    if (dirty && cur.id !== id) saveLocal();
    syncUI("saving", "Loading sheet…");
    return api("get", { id: id }, "POST").then(function (r) {
      if (!r.ok || !r.json || r.json.status !== "success") { syncUI("error", (r.json && r.json.msg) || "Load failed"); return false; }
      var sheet = r.json.sheet, imgs = r.json.images || [];
      cur = { id: sheet.id, version: sheet.version, updated_at: sheet.updated_at };
      var draft = opts.ignoreDraft ? null : readDraft(sheet.id);
      if (draft && draft.dirty && draft.version === sheet.version && draft.mcq_text !== sheet.mcq_text) {
        applySheet(sheet, imgs); dirty = false; syncUI("saved", "Loaded · server copy");
        showModal("Unsaved draft mila",
          "Is sheet ka ek <b>local draft</b> hai jo server copy se naya hai (" + fmtAgo(new Date(draft.savedAt).toISOString()) + "). Shayad pichli baar net kat gaya tha ya tab band ho gaya tha.",
          [
            { html: '<i class="fa fa-trash"></i> Draft hatao', ghost: true, fn: function () { clearDraft(sheet.id); } },
            { html: '<i class="fa fa-rotate-left"></i> Draft restore karo', fn: function () { applySheet(draft, draft._images || imgs); imagesDirty = !!draft._images; dirty = true; syncUI("dirty", "Draft restored — saving…"); saveToServer("restore"); } }
          ]);
      } else {
        applySheet(sheet, imgs); dirty = false; clearDraft(sheet.id); saveLocal();
        syncUI("saved", "Loaded · last saved " + fmtAgo(sheet.updated_at));
      }
      highlightLib();
      setStatus("Chapter " + (sheet.chapter_no || "") + " — " + (sheet.title || "") + " load ho gaya.");
      return true;
    }).catch(function () { syncUI("offline", "Load failed — connection?"); return false; });
  }

  function newSheet(prefill) {
    if (dirty) saveLocal();
    cur = { id: 0, version: 0, updated_at: null };
    var base = {
      subject: (prefill && prefill.subject) || $("subjInput").value || "",
      chapter_no: (prefill && prefill.chapter_no) || "",
      title: (prefill && prefill.title) || "",
      badge: (prefill && prefill.badge) || $("badgeInput").defaultValue,
      tagline: (prefill && prefill.tagline) || $("tagInput").defaultValue,
      mcq_text: (prefill && prefill.mcq_text) || "",
      settings: (prefill && prefill.settings) || null
    };
    applySheet(base, (prefill && prefill.images) || []);
    var d = readDraft(0);
    if (!prefill && d && d.dirty && d.mcq_text) {
      showModal("Naya sheet — unsaved draft mila", "Pichli baar ek naya sheet likhte waqt save nahi hua tha. Restore karein?",
        [{ html: "Nahi, blank", ghost: true, fn: function () { clearDraft(0); } },
         { html: '<i class="fa fa-rotate-left"></i> Restore', fn: function () { applySheet(d, d._images || []); imagesDirty = true; dirty = true; syncUI("dirty", "Draft restored"); scheduleSave(); } }]);
    }
    dirty = !!prefill;
    if (prefill) { imagesDirty = true; syncUI("dirty", "Duplicate ready — saving…"); scheduleSave(); }
    else syncUI("saved", "New sheet — MCQs paste karo, auto-save ON");
    highlightLib(); $("titleInput").focus(); setStatus("New sheet.");
  }
  function startNewSheet() { if (dirty) { saveLocal(); saveToServer("switch"); } newSheet(); setDrawer(true); $("titleInput").focus(); toast("Naya sheet — title likho, MCQs paste karo"); }

  function deleteSheet(id, title) {
    if (!confirm("\"" + (title || "Sheet") + "\" ko trash me daalein? (Baad me Trash se restore ho sakta hai)")) return;
    api("delete", { id: id }).then(function (r) {
      if (r.ok) { toast("Trash me gaya"); if (cur.id === id) newSheet(); loadList(false); } else toast("Delete failed");
    });
  }
  function restoreSheet(id) { api("restore", { id: id }).then(function (r) { if (r.ok) { toast("Restore ho gaya"); loadList(false); } }); }

  function loadList(quiet) {
    if (!quiet) $("libList").innerHTML = '<div class="lib-empty">Loading…</div>';
    return api(showingTrash ? "trash" : "list", undefined, "GET").then(function (r) {
      if (r.status === 401) { syncUI("error", "Login required — admin_panel.php me login karo"); $("libList").innerHTML = '<div class="lib-empty">Login required</div>'; return; }
      if (!r.ok || !r.json) { $("libList").innerHTML = '<div class="lib-empty">List load nahi hui</div>'; return; }
      libSheets = r.json.sheets || []; renderLib(); fillSubjectList();
      if (!showingTrash && !quiet) { online = true; if (!dirty) syncUI("saved", cur.id ? "Saved · " + fmtAgo(cur.updated_at) : "Ready · DB connected"); }
    }).catch(function () { $("libList").innerHTML = '<div class="lib-empty">Offline — list unavailable</div>'; online = false; });
  }
  function fillSubjectList() {
    var seen = {}, dl = $("subjList"); dl.innerHTML = "";
    libSheets.forEach(function (n) { var s = (n.subject || "").trim(); if (s && !seen[s]) { seen[s] = 1; var o = document.createElement("option"); o.value = s; dl.appendChild(o); } });
  }
  function renderLib() {
    var host = $("libList"), q = libFilter.toLowerCase();
    var list = libSheets.filter(function (n) { return !q || ((n.subject || "") + " " + (n.chapter_no || "") + " " + (n.title || "")).toLowerCase().indexOf(q) >= 0; });
    $("libCount").textContent = libSheets.length;
    if (!list.length) { host.innerHTML = '<div class="lib-empty">' + (showingTrash ? "Trash khali hai." : (libSheets.length ? "Kuch match nahi hua." : "Abhi koi sheet nahi. <b>New</b> dabao.")) + "</div>"; return; }
    var groups = {}, order = [];
    list.forEach(function (n) { var s = (n.subject || "General").trim() || "General"; if (!groups[s]) { groups[s] = []; order.push(s); } groups[s].push(n); });
    host.innerHTML = order.map(function (s) {
      return '<div class="lib-subj"><span>' + esc(s) + '</span><small>' + groups[s].length + ' sheet</small></div>' + groups[s].map(function (n) {
        var meta = (n.image_count ? n.image_count + " img · " : "") + (n.text_len ? Math.round(n.text_len / 120) + " Q approx · " : "") + fmtAgo(n.updated_at);
        return '<button type="button" class="lib-item' + (n.id === cur.id ? " cur" : "") + '" data-id="' + n.id + '">' +
          '<span class="no">' + esc(n.chapter_no || "•") + '</span><span class="ti"><b>' + esc(n.title || "Untitled") + '</b><small>' + esc(meta) + '</small></span>' +
          (showingTrash ? '<span class="del" data-restore="' + n.id + '" title="Restore"><i class="fa fa-rotate-left"></i></span>'
                        : '<span class="del" data-del="' + n.id + '" title="Trash"><i class="fa fa-trash"></i></span>') + '</button>';
      }).join("");
    }).join("");
  }
  function highlightLib() { document.querySelectorAll(".lib-item").forEach(function (e) { e.classList.toggle("cur", parseInt(e.dataset.id, 10) === cur.id); }); }
  function touchLibEntry() {
    var c = collect().sheet, found = false;
    libSheets.forEach(function (n) {
      if (n.id === cur.id) { found = true; n.subject = c.subject; n.chapter_no = c.chapter_no; n.title = c.title; n.updated_at = cur.updated_at; n.image_count = images.length; n.text_len = c.mcq_text.length; }
    });
    if (found) { renderLib(); fillSubjectList(); }
  }
  $("libList").addEventListener("click", function (e) {
    var del = e.target.closest("[data-del]");
    if (del) { e.stopPropagation(); var id = parseInt(del.dataset.del, 10); var n = libSheets.filter(function (x) { return x.id === id; })[0]; deleteSheet(id, n && n.title); return; }
    var rs = e.target.closest("[data-restore]");
    if (rs) { e.stopPropagation(); restoreSheet(parseInt(rs.dataset.restore, 10)); return; }
    var it = e.target.closest(".lib-item"); if (!it) return;
    var sid = parseInt(it.dataset.id, 10); if (sid === cur.id && !showingTrash) return;
    if (dirty) { saveLocal(); saveToServer("switch"); }
    loadSheet(sid);
  });
  $("libSearch").addEventListener("input", function () { libFilter = this.value.trim(); renderLib(); });
  $("btnNewSheet").addEventListener("click", startNewSheet);
  $("btnHeaderNew").addEventListener("click", startNewSheet);
  $("btnLibRefresh").addEventListener("click", function () { loadList(false); toast("List refreshed"); });
  $("btnTrash").addEventListener("click", function () { showingTrash = !showingTrash; this.innerHTML = showingTrash ? '<i class="fa fa-folder"></i> Library' : '<i class="fa fa-trash-can"></i> Trash'; loadList(false); });
  $("btnDuplicate").addEventListener("click", function () {
    var c = collect().sheet; c.title = (c.title || "Untitled") + " (Copy)"; c.chapter_no = ""; c.images = images.slice();
    newSheet(c); toast("Duplicate bana — chapter number set karo");
  });
  $("btnSaveNow").addEventListener("click", function () { dirty = true; saveToServer("manual"); });
  $("btnHeaderLib").addEventListener("click", function () {
    setDrawer(true); $("accLib").classList.add("open");
    setTimeout(function () { $("accLib").scrollIntoView({ behavior: "smooth", block: "start" }); }, 300);
  });

  /* =====================================================================
     IMAGES
  ===================================================================== */
  var images = [];
  function findImage(name) { name = String(name || "").toLowerCase(); for (var i = 0; i < images.length; i++) if (images[i].name.toLowerCase() === name) return images[i]; return null; }
  function saveImages() { imagesDirty = true; saveLocalImages(); saveState(); return true; }
  function slug(fn) { return (fn || "img").replace(/\.[a-z0-9]+$/i, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 24) || "img"; }
  function uniqueName(base) { var n = base, k = 2; while (findImage(n)) n = base + "-" + (k++); return n; }

  function compressFile(file) {
    return new Promise(function (res, rej) {
      var url = URL.createObjectURL(file), im = new Image();
      im.onload = function () {
        var MAX = 1400, w = im.naturalWidth, h = im.naturalHeight, sc = Math.min(1, MAX / Math.max(w, h));
        var cw = Math.round(w * sc), ch = Math.round(h * sc), c = document.createElement("canvas"); c.width = cw; c.height = ch;
        c.getContext("2d").drawImage(im, 0, 0, cw, ch);
        var png = /png|gif|webp/i.test(file.type) && file.size < 350000, data = png ? c.toDataURL("image/png") : c.toDataURL("image/jpeg", 0.86);
        if (png && data.length > 600000) data = c.toDataURL("image/jpeg", 0.86);
        URL.revokeObjectURL(url); res({ data: data, w: cw, h: ch });
      };
      im.onerror = function () { URL.revokeObjectURL(url); rej(new Error("bad image")); };
      im.src = url;
    });
  }
  function addImageFiles(files, then) {
    var list = [].slice.call(files || []).filter(function (f) { return /^image\//.test(f.type); });
    if (!list.length) { toast("Image file choose karo"); return; }
    var added = [];
    list.reduce(function (p, f) {
      return p.then(function () {
        return compressFile(f).then(function (r) { var nm = uniqueName(slug(f.name)); images.push({ name: nm, data: r.data, w: r.w, h: r.h }); added.push(nm); },
                                    function () { toast(f.name + " load nahi hui"); });
      });
    }, Promise.resolve()).then(function () {
      saveImages(); renderImages(); render();
      if (added.length) toast(added.length + " image add ho gayi");
      if (then) then(added);
    });
  }
  function imgLine(nm, w, al, cap) {
    return "[img: " + nm + (w && w !== "100%" ? " | " + w : "") + (al && al !== "center" ? " | " + al : "") + (cap ? " | " + cap.replace(/[\[\]|]/g, "") : "") + "]";
  }

  var IMG_TAG_RE = /^\s*\[(?:img|image)\s*:\s*([^\]|]+?)\s*(?:\|[^\]]*)?\]\s*$/i;
  var linesCache = { v: null, lines: null };
  function taLines() { var v = ta.value; if (linesCache.v !== v) { linesCache.v = v; linesCache.lines = v.split("\n"); } return linesCache.lines; }
  function findImgTag(name) {
    var lines = taLines(), nm = String(name || "").toLowerCase();
    for (var i = 0; i < lines.length; i++) {
      var m = lines[i].match(IMG_TAG_RE);
      if (m && m[1].trim().toLowerCase() === nm) return { line: i, tag: parseImgLine(lines[i].trim()) };
    }
    return null;
  }
  function setText(v, a, b) { ta.value = v; ta.focus(); ta.setSelectionRange(a, b == null ? a : b); render(); saveState(); }
  function updateImgTag(name, newLine) {
    var hit = findImgTag(name); if (!hit) return false;
    var lines = ta.value.split("\n");
    lines[hit.line] = newLine;
    var before = lines.slice(0, hit.line).join("\n"), start = before.length + (hit.line ? 1 : 0);
    setText(lines.join("\n"), start, start + newLine.length);
    return true;
  }
  /** insert the tag on its own line right under the question's first line */
  function insertImgTag(afterLine, text) {
    var lines = ta.value.split("\n");
    var at = (afterLine === "end" || afterLine == null) ? lines.length : Math.min(parseInt(afterLine, 10) + 1, lines.length);
    lines.splice(at, 0, text);
    var before = lines.slice(0, at).join("\n"), start = before.length + (at ? 1 : 0);
    setText(lines.join("\n"), start, start + text.length);
  }

  function qOptions() {
    var o = '<option value="end">⤓ Sheet ke end me</option>';
    lastItems.forEach(function (b) {
      if (b.type !== "q") return;
      var txt = String(b.text || "").trim();
      if (txt.length > 44) txt = txt.slice(0, 42) + "…";
      o += '<option value="' + b.srcLine + '">' + esc((b.label || "Q") + " — " + (txt || "untitled")) + "</option>";
    });
    return o;
  }
  var posHtmlStale = true;
  function refreshPosSelects() { posHtmlStale = true; }
  function fillPosSelect(sel) {
    if (!posHtmlStale && sel.options.length > 1) return;
    var v = sel.value; sel.innerHTML = qOptions();
    if ([].some.call(sel.options, function (op) { return op.value === v; })) sel.value = v;
  }
  $("imgList").addEventListener("mousedown", function (e) { var sel = e.target.closest(".imgPos"); if (sel) fillPosSelect(sel); }, true);
  $("imgList").addEventListener("focusin", function (e) { var sel = e.target.closest(".imgPos"); if (sel) fillPosSelect(sel); });

  function syncImgCards() {
    refreshPosSelects();
    var cards = document.querySelectorAll("#imgList .imgc"), stale = false;
    cards.forEach(function (c) { var im = images[parseInt(c.dataset.k, 10)]; if (im && (!!findImgTag(im.name)) !== c.classList.contains("used")) stale = true; });
    if (stale) renderImages();
  }
  function renderImages() {
    var host = $("imgList"); $("imgCount").textContent = images.length;
    if (!images.length) { host.innerHTML = '<div class="imgs-empty">Abhi koi image nahi. Upar se add karo.</div>'; return; }
    host.innerHTML = images.map(function (im, k) {
      var hit = findImgTag(im.name), tg = hit && hit.tag;
      var curW = tg ? (tg.w || "100%") : "60%", curAl = tg ? tg.align : "center", curCap = tg ? tg.cap : "";
      var W = ["100%","75%","60%","50%","40%","33%"], AL = [["center","Center"],["left","Left"],["right","Right"]];
      if (W.indexOf(curW) < 0) W.splice(1, 0, curW);
      return '<div class="imgc' + (hit ? " used" : "") + '" data-k="' + k + '"><img class="th" src="' + im.data + '" alt=""><div class="bd">' +
        '<div class="nm"><code title="' + esc(im.name) + '">' + esc(im.name) + '</code><small>' + im.w + "×" + im.h + '</small><button type="button" class="del" title="Delete"><i class="fa fa-trash"></i></button></div>' +
        (hit ? '<div class="used-note"><i class="fa fa-link"></i> Line ' + (hit.line + 1) + ' pe lagi hai — niche change karke <b>Update</b> dabao</div>'
             : '<select class="inp imgPos" title="Kis question me daalni hai"><option value="end">⤓ Sheet ke end me</option></select>') +
        '<div class="r2"><select class="inp imgW">' + W.map(function (x) { return '<option value="' + x + '"' + (x === curW ? " selected" : "") + '>' + (x === "100%" ? "Width 100%" : x) + "</option>"; }).join("") + '</select>' +
        '<select class="inp imgAl">' + AL.map(function (x) { return '<option value="' + x[0] + '"' + (x[0] === curAl ? " selected" : "") + '>' + x[1] + "</option>"; }).join("") + '</select></div>' +
        '<input class="inp imgCap" placeholder="Caption (optional)" value="' + esc(curCap) + '">' +
        (hit ? '<div class="r2"><button type="button" class="ins upd"><i class="fa fa-pen"></i> Update</button><button type="button" class="ins again" title="Same image ek aur jagah"><i class="fa fa-plus"></i> Insert again</button></div>'
             : '<button type="button" class="ins"><i class="fa fa-arrow-turn-down"></i> Insert</button>') +
        '</div></div>';
    }).join("");
  }
  $("imgList").addEventListener("click", function (e) {
    var card = e.target.closest(".imgc"); if (!card) return;
    var im = images[parseInt(card.dataset.k, 10)]; if (!im) return;
    if (e.target.closest(".del")) {
      if (!confirm("Image \"" + im.name + "\" delete karein?")) return;
      images.splice(images.indexOf(im), 1); saveImages(); renderImages(); render(); return;
    }
    var btn = e.target.closest(".ins");
    if (btn) {
      var w = card.querySelector(".imgW").value, al = card.querySelector(".imgAl").value, cap = card.querySelector(".imgCap").value.trim();
      var line = imgLine(im.name, w, al, cap);
      if (btn.classList.contains("upd")) {
        if (updateImgTag(im.name, line)) { toast("Image update ho gayi"); renderImages(); }
        else toast("Tag nahi mila — Insert karo");
        return;
      }
      var sel = card.querySelector(".imgPos");
      insertImgTag(sel ? sel.value : "end", line);
      toast("Image insert ho gayi"); renderImages();
    }
  });
  $("imgDrop").addEventListener("click", function () { $("imgFile").click(); });
  $("imgFile").addEventListener("change", function () { addImageFiles(this.files); this.value = ""; });
  ["dragenter", "dragover"].forEach(function (ev) { $("imgDrop").addEventListener(ev, function (e) { e.preventDefault(); this.classList.add("over"); }); });
  ["dragleave", "drop"].forEach(function (ev) { $("imgDrop").addEventListener(ev, function (e) { e.preventDefault(); this.classList.remove("over"); }); });
  $("imgDrop").addEventListener("drop", function (e) { addImageFiles(e.dataTransfer.files); });
  ta.addEventListener("dragover", function (e) { if (e.dataTransfer && [].some.call(e.dataTransfer.items || [], function (it) { return /^image\//.test(it.type); })) e.preventDefault(); });
  ta.addEventListener("drop", function (e) {
    var fs = [].slice.call(e.dataTransfer.files || []).filter(function (f) { return /^image\//.test(f.type); });
    if (!fs.length) return; e.preventDefault();
    addImageFiles(fs, function (names) { names.forEach(function (nm) { insertImgTag(curLineNo(), imgLine(nm, "60%", "center", "")); }); });
  });
  ta.addEventListener("paste", function (e) {
    var its = (e.clipboardData && e.clipboardData.items) || [], fs = [];
    for (var i = 0; i < its.length; i++) if (its[i].kind === "file" && /^image\//.test(its[i].type)) fs.push(its[i].getAsFile());
    if (!fs.length) {
      var txt = e.clipboardData ? e.clipboardData.getData("text/plain") : "";
      if (txt && txt.length > 8000) { bigPaste = true; setStatus("Bada text paste hua (" + Math.round(txt.length / 1000) + "k) — render ho raha hai…"); }
      if (txt && !$("titleInput").value.trim()) setTimeout(function () { autoTitleFrom(txt); }, 0);
      return;
    }
    e.preventDefault();
    addImageFiles(fs, function (names) { names.forEach(function (nm) { insertImgTag(curLineNo(), imgLine(nm, "60%", "center", "")); }); });
  });
  function curLineNo() {
    var pos = ta.selectionEnd, lines = ta.value.split("\n"), cnt = 0, i;
    for (i = 0; i < lines.length; i++) { cnt += lines[i].length + 1; if (cnt > pos) break; }
    return Math.min(i, lines.length - 1);
  }
  function autoTitleFrom(txt) {
    var lines = txt.replace(/\r/g, "").split("\n"), pick = "";
    for (var i = 0; i < lines.length && i < 40; i++) {
      var t = lines[i].trim(); if (!t) continue;
      var m = t.match(/^(Title|Chapter|Topic)\s*:\s*(.+)$/i);
      if (m) { if (/^chapter$/i.test(m[1]) && !$("chapInput").value.trim() && /^\d+$/.test(m[2].trim())) { $("chapInput").value = m[2].trim(); continue; } pick = m[2]; break; }
      m = t.match(/^(?:CHAPTER|UNIT|LECTURE)\s*(\d+)\s*[:\-–.]?\s*(.+)$/i);
      if (m) { if (!$("chapInput").value.trim()) $("chapInput").value = m[1]; pick = m[2]; break; }
      if (/^\s*(?:year\s*[:\-–]|Q\.?\s*\d|\d{1,3}[\.\)])/i.test(t)) continue;
      if (t.length <= 70 && !/[.!?]$/.test(t)) { pick = t; break; }
    }
    pick = pick.replace(/[*_#]/g, "").trim();
    if (pick) { $("titleInput").value = pick.slice(0, 80); toast("Title set: " + pick.slice(0, 40)); }
  }

  /* =====================================================================
     CONTROLS / CHROME
  ===================================================================== */
  var timer = null, previewStale = false, bigPaste = false;
  function renderDelay() { var L = ta.value.length; return bigPaste ? 900 : (L < 40000 ? 300 : L < 150000 ? 650 : 1100); }
  function schedule() {
    if (timer) clearTimeout(timer);
    if (!$("optLive").checked) {
      previewStale = true; setStatus("Preview paused — Refresh dabao (Ctrl+Enter)");
      $("btnRefreshPreview").classList.add("stale");
      timer = setTimeout(function () { saveState(); }, 600);
      return;
    }
    if (ta.value.length > 40000) setStatus("Rendering " + Math.round(ta.value.length / 1000) + "k chars…");
    timer = setTimeout(function () { bigPaste = false; render(); saveState(); }, renderDelay());
  }
  function refreshPreview() { if (timer) clearTimeout(timer); previewStale = false; $("btnRefreshPreview").classList.remove("stale"); render(); saveState(); }
  $("btnRefreshPreview").addEventListener("click", refreshPreview);
  $("optLive").addEventListener("change", function () { document.body.classList.toggle("live-off", !this.checked); if (this.checked && previewStale) refreshPreview(); saveState(); });

  ta.addEventListener("input", schedule);
  FIELDS.forEach(function (id) { $(id).addEventListener("input", schedule); });
  OPTS.concat(["optPerPage"]).forEach(function (id) { $(id).addEventListener("change", function () { render(); saveState(); }); });
  $("optPerPage").addEventListener("input", schedule);
  function applyWatermark() { stage.classList.toggle("no-wm", !$("optWM").checked); document.body.classList.toggle("no-wm", !$("optWM").checked); }
  $("optWM").addEventListener("change", applyWatermark);

  $("btnPrint").addEventListener("click", function () { if (timer) clearTimeout(timer); render(); window.print(); });
  $("btnSample").addEventListener("click", function () { ta.value = SAMPLE; render(); saveState(); setStatus("Sample loaded — edit freely."); });
  $("btnClear").addEventListener("click", function () {
    if (ta.value.trim() && !confirm("Is sheet ka saara text clear kar de?")) return;
    ta.value = ""; render(); saveState(); ta.focus();
  });
  document.querySelectorAll("[data-acc]").forEach(function (h) { h.addEventListener("click", function () { h.closest(".acc").classList.toggle("open"); }); });

  document.addEventListener("keydown", function (e) {
    var mod = e.ctrlKey || e.metaKey;
    if (mod && !e.altKey && e.key.toLowerCase() === "s") { e.preventDefault(); dirty = true; saveToServer("manual"); }
    if (mod && e.altKey && e.key.toLowerCase() === "n") { e.preventDefault(); startNewSheet(); }
    if (mod && e.key === "Enter") { e.preventDefault(); refreshPreview(); }
    if (e.key === "Escape") { setDrawer(false); }
  });

  function setDrawer(open) {
    document.body.classList.toggle("drawer-open", open);
    $("sideTab").setAttribute("aria-expanded", open ? "true" : "false");
    setTimeout(updateScale, 400);
  }
  $("sideTab").addEventListener("click", function () { setDrawer(!document.body.classList.contains("drawer-open")); });
  $("scrim").addEventListener("click", function () { setDrawer(false); });
  $("panelClose").addEventListener("click", function () { setDrawer(false); });

  function updateToolbarOffset() { document.documentElement.style.setProperty("--toolbar-h", $("topbar").offsetHeight + "px"); }
  function setNav(hidden) {
    document.body.classList.toggle("nav-hidden", hidden);
    try { localStorage.setItem("mcq_nav_hidden", hidden ? "1" : "0"); } catch (e) {}
    setTimeout(function () { updateScale(); }, 50);
  }
  $("btnNavHide").addEventListener("click", function () { setNav(true); toast("Top bar hidden — upar \"Menu\" se wapas lao"); });
  $("btnNavShow").addEventListener("click", function () { setNav(false); });
  try { if (localStorage.getItem("mcq_nav_hidden") === "1") document.body.classList.add("nav-hidden"); } catch (e) {}

  var zoom = 1;
  function fitScale() {
    if (window.innerWidth > 900) return 1;
    var avail = Math.max(stage.clientWidth || window.innerWidth, 1) - 16;
    return Math.max(0.24, Math.min(1, avail / (210 * 3.7795275591)));
  }
  function updateScale() { document.documentElement.style.setProperty("--sheet-scale", String(fitScale() * zoom)); }
  var badgeT = null;
  function setZoom(z) {
    zoom = Math.min(3, Math.max(0.3, z)); updateScale();
    var b = $("zoomBadge"); b.textContent = Math.round(zoom * 100) + "%"; b.classList.add("show");
    if (badgeT) clearTimeout(badgeT); badgeT = setTimeout(function () { b.classList.remove("show"); }, 1100);
  }
  document.addEventListener("wheel", function (e) { if (!e.ctrlKey && !e.metaKey) return; e.preventDefault(); setZoom(zoom * (e.deltaY < 0 ? 1.12 : 1 / 1.12)); }, { passive: false });
  document.addEventListener("keydown", function (e) {
    if ((!e.ctrlKey && !e.metaKey) || document.activeElement === ta) return;
    if (e.key === "+" || e.key === "=") { e.preventDefault(); setZoom(zoom * 1.15); }
    else if (e.key === "-" || e.key === "_") { e.preventDefault(); setZoom(zoom / 1.15); }
    else if (e.key === "0") { e.preventDefault(); setZoom(1); }
  });
  (function () {
    var lastY = 0, lastRun = 0;
    window.addEventListener("scroll", function () {
      var now = Date.now(); if (now - lastRun < 60) return; lastRun = now;
      if (window.innerWidth > 900) { document.body.classList.remove("bar-hidden"); lastY = 0; return; }
      var y = window.scrollY || 0, dy = y - lastY;
      if (Math.abs(dy) < 6) return;
      if (dy > 0 && y > 70) document.body.classList.add("bar-hidden"); else if (dy < 0) document.body.classList.remove("bar-hidden");
      lastY = y;
    }, { passive: true });
  })();
  window.addEventListener("resize", function () { updateToolbarOffset(); updateScale(); schedule(); });

  /* ---- boot ---- */
  document.body.classList.add("booting");
  updateToolbarOffset();
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { updateToolbarOffset(); render(); });
  setDrawer(true);
  applyWatermark(); renderImages();
  render();

  syncUI("saving", "Connecting to database…");
  loadList(false).then(function () {
    var last = parseInt(localStorage.getItem("mcq_last_id") || "0", 10);
    var exists = libSheets.some(function (n) { return n.id === last; });
    if (last > 0 && exists) return loadSheet(last);
    var nd = readDraft(0);
    if (last === 0 && nd && nd.dirty && nd.mcq_text) return newSheet();
    if (libSheets.length) return loadSheet(libSheets[0].id);
    newSheet();
  });

  function endBoot() { document.body.classList.remove("booting"); }
  requestAnimationFrame(function () { requestAnimationFrame(endBoot); });
  setTimeout(endBoot, 300);
})();
</script>
</body>
</html>
