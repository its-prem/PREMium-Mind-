<?php
/**
 * D2D Notes — chapter-wise short-notes editor, backed by MySQL.
 * Upload to Hostinger premind/ as: d2d_notes.php  (with d2d_notes_api.php + pm_admin_auth.php)
 *
 * Same-origin with admin_panel.php on purpose: it reuses that login session,
 * so there is no second login to build or maintain. Sign in on the admin panel
 * once, then open this page.
 */
require_once __DIR__ . '/pm_admin_auth.php';

if (!pm_auth_admin_ok()):
    http_response_code(401);
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notes D2D — Login required</title>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0a0a0a;color:#fff;font-family:Poppins,sans-serif}
  .box{max-width:420px;width:92%;background:#141414;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:32px 28px;text-align:center}
  h1{font-family:Oswald,sans-serif;text-transform:uppercase;letter-spacing:2px;font-size:1.4rem;margin:0 0 10px}
  h1 span{color:#ff5722} p{color:#a0a0a0;font-size:.9rem;line-height:1.6;margin:0 0 22px}
  a{display:inline-block;background:#ff5722;color:#fff;text-decoration:none;font-family:Oswald,sans-serif;text-transform:uppercase;letter-spacing:1px;padding:12px 22px;border-radius:5px;font-weight:600}
</style></head><body>
<div class="box"><h1>Notes <span>D2D</span></h1>
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
<title>Notes D2D — Short Notes Maker</title>
<script>window.PM_D2D = { admin: <?= json_encode($pmAdminEmail) ?>, api: 'd2d_notes_api.php' };</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Poppins:wght@300;400;500;600&family=Tinos:ital,wght@0,400;0,700;1,400;1,700&family=Caveat:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  @page { size: A4 portrait; margin: 0; }

  :root {
    --primary: #ff5722; --bg-dark: #0a0a0a; --bg-light: #141414; --text-muted: #a0a0a0;
    --line: rgba(255,255,255,.08); --line-2: rgba(255,255,255,.16);
    --ease: cubic-bezier(.4,0,.2,1); --toolbar-h: 64px;
    --maroon: #8e1b2a; --maroon-dark: #6f1220;
    --pink: #f7e1e5; --pink-2: #fbecef; --cream: #fff3dd; --cream-2: #fff8ea;
    --ink: #1a1a1a; --paper-pad: 7mm; --col-gap: 6mm; --sheet-scale: 1; --nf: 10.5pt;
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }
  body.booting, body.booting * { transition: none !important; }
  html { -webkit-text-size-adjust: 100%; }
  body { font-family: 'Poppins', sans-serif; background: var(--bg-dark); color: #fff; line-height: 1.6; height: 100dvh; overflow: hidden; }
  h1, h2, h3, .logo { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

  /* ---------------- app chrome ---------------- */
  .toolbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: rgba(10,10,10,.95); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); box-shadow: 0 5px 20px rgba(0,0,0,.5); transition: transform .3s var(--ease); }
  .bar-inner { width: 94%; max-width: 1500px; margin: 0 auto; padding: 12px 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
  .logo a { font-size: 1.5rem; font-weight: 700; letter-spacing: 2px; color: #fff; text-decoration: none; }
  .logo a span { color: var(--primary); }
  .actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: var(--primary); color: #fff; font-family: 'Oswald', sans-serif; font-weight: 500; font-size: .84rem; text-transform: uppercase; letter-spacing: 1px; border: 2px solid var(--primary); border-radius: 4px; cursor: pointer; transition: all .3s ease; white-space: nowrap; }
  .btn:hover { background: transparent; color: var(--primary); }
  .btn:active { transform: scale(.97); }
  .btn.ghost { background: transparent; color: #fff; border-color: var(--line-2); }
  .btn.ghost:hover { border-color: var(--primary); color: var(--primary); }
  .btn.danger { background: transparent; color: var(--text-muted); border-color: var(--line-2); }
  .btn.danger:hover { border-color: #c62828; color: #ef5350; }
  .hint { width: 100%; text-align: center; font-size: .74rem; color: var(--text-muted); padding-bottom: 10px; margin-top: -4px; }
  .hint b { color: var(--primary); }

  .side-tab { position: fixed; left: 0; top: 50%; transform: translateY(-50%); z-index: 900; display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 18px 9px; background: var(--bg-light); border: 1px solid var(--line-2); border-left: none; border-radius: 0 6px 6px 0; color: #fff; cursor: pointer; transition: all .3s ease; }
  .side-tab:hover { background: var(--primary); border-color: var(--primary); }
  .side-tab .tab-label { font-family: 'Oswald', sans-serif; font-size: .72rem; letter-spacing: .16em; text-transform: uppercase; writing-mode: vertical-rl; transform: rotate(180deg); }
  .side-tab .tab-chev { font-size: .8rem; transition: transform .34s var(--ease); }
  body.drawer-open .side-tab .tab-chev { transform: rotate(180deg); }
  .scrim { display: none; }

  .workspace { display: flex; align-items: stretch; height: calc(100dvh - var(--toolbar-h)); margin-top: var(--toolbar-h); }
  .editor-col { flex: 0 0 clamp(360px, 37%, 580px); width: clamp(360px, 37%, 580px); display: flex; align-items: flex-start; justify-content: center; padding: 22px 16px 40px 58px; height: 100%; overflow-y: auto; overscroll-behavior: contain; transition: flex-basis .38s var(--ease), width .38s var(--ease), padding .38s var(--ease), opacity .26s ease, transform .38s var(--ease); }
  body:not(.drawer-open) .editor-col { flex-basis: 0; width: 0; min-width: 0; padding: 0; opacity: 0; transform: translateX(-20px); overflow: hidden; pointer-events: none; }
  .editor-col::-webkit-scrollbar { width: 7px; }
  .editor-col::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 99px; }
  .preview-col { flex: 1 1 auto; min-width: 0; max-width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; padding: 22px 12px 60px; overflow: auto; overscroll-behavior: contain; background: #e9e6e6; position: relative; }
  .preview-col::-webkit-scrollbar { width: 9px; height: 9px; }
  .preview-col::-webkit-scrollbar-thumb { background: #b9b3b3; border-radius: 99px; }

  .editor { width: 100%; }
  .panel { background: var(--bg-light); border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
  .panel-head { padding: 14px 18px; border-bottom: 1px solid var(--line); background: linear-gradient(180deg,#181818,#131313); display: flex; align-items: center; justify-content: space-between; gap: 10px; }
  .panel-head h1 { font-size: 1.1rem; letter-spacing: 1.5px; display: flex; align-items: center; gap: 10px; }
  .panel-head h1 i { color: var(--primary); }
  .panel-close { width: 30px; height: 30px; display: grid; place-items: center; background: transparent; border: 1px solid var(--line-2); border-radius: 4px; color: var(--text-muted); cursor: pointer; }
  .panel-close:hover { border-color: var(--primary); color: var(--primary); }
  .panel-body { padding: 14px; }
  .status { font-size: .74rem; color: var(--text-muted); border-left: 2px solid var(--primary); padding-left: 9px; margin-bottom: 12px; }

  .field { margin-bottom: 10px; }
  .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .row3 { display: grid; grid-template-columns: 72px 1fr; gap: 10px; }
  .field-label { display: block; font-family: 'Oswald', sans-serif; font-size: .7rem; letter-spacing: 1.2px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px; }
  .inp, textarea { width: 100%; background: var(--bg-dark); border: 1px solid var(--line-2); color: #fff; border-radius: 4px; outline: none; transition: border-color .2s; font-family: 'Poppins', sans-serif; font-size: .84rem; padding: 10px 12px; }
  .inp:focus, textarea:focus { border-color: var(--primary); }
  textarea#rawInput { min-height: 340px; resize: vertical; font-family: Consolas, "Courier New", monospace; font-size: .78rem; line-height: 1.6; border-top-left-radius: 0; border-top-right-radius: 0; tab-size: 2; }
  .checks { display: flex; flex-wrap: wrap; gap: 8px 14px; margin: 2px 0 12px; }
  .chk { display: flex; align-items: center; gap: 7px; font-size: .78rem; color: var(--text-muted); cursor: pointer; }
  .chk input { accent-color: var(--primary); width: 15px; height: 15px; }
  .chk input.num { width: 56px; height: auto; padding: 4px 6px; font-size: .78rem; text-align: center; }
  .pal { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
  .pal input[type=color] { width: 34px; height: 28px; padding: 2px; border: 1px solid var(--line-2); border-radius: 4px; background: var(--bg-dark); cursor: pointer; }
  .pal select { flex: 1; min-width: 120px; padding: 6px 8px; font-size: .78rem; }

  /* ---- formatting toolbar ---- */
  .fmt { display: flex; flex-wrap: wrap; gap: 3px; padding: 6px; background: #0f0f0f; border: 1px solid var(--line-2); border-bottom: none; border-radius: 4px 4px 0 0; }
  .fmt button { background: transparent; border: 1px solid transparent; color: #ddd; border-radius: 3px; padding: 5px 8px; font-size: .74rem; font-family: 'Poppins', sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all .15s; min-width: 30px; justify-content: center; }
  .fmt button:hover { border-color: var(--primary); color: var(--primary); }
  .fmt button.on { background: var(--primary); border-color: var(--primary); color: #fff; }
  .fmt button:disabled { opacity: .35; cursor: default; }
  .fmt button b { font-family: Tinos, serif; }
  .fmt .sep { width: 1px; background: var(--line-2); margin: 3px 3px; }
  .fmt .sw { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
  .fmt .grp-label { font-size: .62rem; color: #666; letter-spacing: 1px; text-transform: uppercase; align-self: center; padding: 0 4px; }

  /* ---- floating toolbar over a selection in the preview ---- */
  .floatbar { position: fixed; z-index: 1200; display: none; gap: 3px; padding: 5px; background: rgba(16,16,16,.97); border: 1px solid var(--line-2); border-radius: 6px; box-shadow: 0 10px 30px rgba(0,0,0,.55); flex-wrap: wrap; max-width: 420px; }
  .floatbar.show { display: flex; }
  .floatbar button { background: transparent; border: 1px solid transparent; color: #eee; border-radius: 3px; padding: 5px 8px; font-size: .74rem; font-family: 'Poppins', sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; min-width: 28px; justify-content: center; }
  .floatbar button:hover { border-color: var(--primary); color: var(--primary); }
  .floatbar button.on { background: var(--primary); border-color: var(--primary); color: #fff; }
  .floatbar button b { font-family: Tinos, serif; }
  .floatbar .sep { width: 1px; background: var(--line-2); margin: 2px 2px; }
  .floatbar .sw { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }
  .floatbar::after { content: ""; position: absolute; left: 50%; bottom: -6px; width: 10px; height: 10px; background: rgba(16,16,16,.97); border-right: 1px solid var(--line-2); border-bottom: 1px solid var(--line-2); transform: translateX(-50%) rotate(45deg); }

  /* ---------------- library / sync (DB-backed) ---------------- */
  .sync { display: flex; align-items: center; gap: 8px; font-size: .72rem; color: var(--text-muted); padding: 6px 10px; border: 1px solid var(--line-2); border-radius: 4px; background: #0f0f0f; margin-bottom: 12px; min-height: 32px; }
  .sync .dot { width: 8px; height: 8px; border-radius: 50%; background: #666; flex-shrink: 0; transition: background .25s; }
  .sync.saved .dot { background: #43a047; } .sync.saved { color: #9ccc65; }
  .sync.saving .dot { background: #ffb300; animation: syncPulse 1s ease-in-out infinite; } .sync.saving { color: #ffd54f; }
  .sync.dirty .dot { background: #ff9800; } .sync.dirty { color: #ffb74d; }
  .sync.offline .dot { background: #e53935; } .sync.offline { color: #ef9a9a; }
  .sync.error .dot { background: #e53935; } .sync.error { color: #ef9a9a; }
  .sync .txt { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .sync button { background: transparent; border: 1px solid var(--line-2); color: #ddd; border-radius: 3px; padding: 3px 8px; font-size: .68rem; cursor: pointer; font-family: 'Oswald', sans-serif; letter-spacing: .8px; text-transform: uppercase; white-space: nowrap; }
  .sync button:hover { border-color: var(--primary); color: var(--primary); }
  @keyframes syncPulse { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: .45; transform: scale(.8); } }

  .lib-top { display: grid; grid-template-columns: 1fr auto; gap: 8px; margin-bottom: 8px; }
  .lib-top .inp { margin: 0; }
  .lib-new { background: var(--primary); border: 2px solid var(--primary); color: #fff; border-radius: 4px; padding: 0 12px; font-family: 'Oswald', sans-serif; font-size: .72rem; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
  .lib-new:hover { background: transparent; color: var(--primary); }
  .lib-list { display: flex; flex-direction: column; gap: 6px; max-height: 320px; overflow-y: auto; padding-right: 2px; }
  .lib-list::-webkit-scrollbar { width: 6px; } .lib-list::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 99px; }
  .lib-subj { font-family: 'Oswald', sans-serif; font-size: .68rem; letter-spacing: 1.4px; text-transform: uppercase; color: var(--primary); padding: 8px 4px 2px; display: flex; align-items: center; justify-content: space-between; }
  .lib-subj small { color: #666; letter-spacing: 0; font-family: 'Poppins', sans-serif; text-transform: none; }
  .lib-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; background: #0f0f0f; border: 1px solid var(--line); border-left: 3px solid transparent; border-radius: 4px; cursor: pointer; transition: all .18s; text-align: left; color: #ddd; font-family: 'Poppins', sans-serif; width: 100%; }
  .lib-item:hover { border-color: var(--line-2); border-left-color: var(--primary); background: #151515; }
  .lib-item.cur { border-left-color: var(--primary); background: #1a1410; color: #fff; }
  .lib-item .no { width: 30px; height: 30px; border-radius: 4px; background: #1c1c1c; display: grid; place-items: center; font-family: 'Oswald', sans-serif; font-size: .8rem; color: var(--primary); flex-shrink: 0; }
  .lib-item .ti { flex: 1; min-width: 0; }
  .lib-item .ti b { display: block; font-size: .8rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .lib-item .ti small { display: block; font-size: .66rem; color: #777; margin-top: 1px; }
  .lib-item .del { background: transparent; border: none; color: #555; cursor: pointer; padding: 4px 6px; border-radius: 3px; flex-shrink: 0; font-size: .8rem; }
  .lib-item .del:hover { color: #ef5350; background: rgba(239,83,80,.1); }
  .lib-empty { color: #555; font-size: .76rem; padding: 14px 6px; text-align: center; }
  .lib-tools { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }
  .lib-tools button { background: transparent; border: 1px solid var(--line-2); color: #bbb; border-radius: 3px; padding: 5px 9px; font-size: .68rem; cursor: pointer; font-family: 'Oswald', sans-serif; letter-spacing: .8px; text-transform: uppercase; display: inline-flex; align-items: center; gap: 5px; }
  .lib-tools button:hover { border-color: var(--primary); color: var(--primary); }

  textarea#srcInput { min-height: 200px; resize: vertical; font-family: 'Poppins', sans-serif; font-size: .8rem; line-height: 1.6; }
  .src-tools { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; align-items: center; }
  .src-tools small { color: #666; font-size: .68rem; flex: 1; }

  .modal-scrim { position: fixed; inset: 0; background: rgba(0,0,0,.72); z-index: 3000; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(3px); }
  .modal-scrim.show { display: flex; }
  .modal { background: var(--bg-light); border: 1px solid var(--line-2); border-radius: 8px; max-width: 440px; width: 100%; padding: 22px; box-shadow: 0 20px 60px rgba(0,0,0,.6); }
  .modal h3 { font-size: 1rem; letter-spacing: 1.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
  .modal h3 i { color: #ffb300; }
  .modal p { color: var(--text-muted); font-size: .82rem; line-height: 1.65; margin-bottom: 16px; }
  .modal .row { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
  .modal .btn { padding: 9px 16px; font-size: .78rem; }

  .acc { background: var(--bg-dark); border: 1px solid var(--line); border-left: 3px solid transparent; border-radius: 4px; margin-bottom: 12px; }
  .acc.open { border-left-color: var(--primary); }
  .acc-head { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 11px 14px; background: transparent; border: none; color: #fff; cursor: pointer; font-family: 'Oswald', sans-serif; font-size: .78rem; letter-spacing: 1.2px; text-transform: uppercase; }
  .acc.open .acc-head { color: var(--primary); }
  .acc-head .chev { transition: transform .32s var(--ease); font-size: .78rem; }
  .acc.open .acc-head .chev { transform: rotate(180deg); }
  .acc-body { max-height: 0; overflow: hidden; padding: 0 14px; transition: max-height .36s var(--ease), padding .36s var(--ease); }
  .acc.open .acc-body { max-height: 1400px; padding: 0 14px 14px; }
  .acc-body p { font-size: .76rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 6px; }
  .acc-body table { width: 100%; border-collapse: collapse; font-size: .74rem; color: #ccc; margin-top: 6px; }
  .acc-body td { padding: 5px 6px; border-bottom: 1px solid var(--line); vertical-align: top; }
  .btn-copy { display: inline-flex; align-items: center; gap: 7px; background: var(--primary); color: #fff; border: none; border-radius: 4px; padding: 8px 14px; font-family: 'Oswald', sans-serif; font-size: .78rem; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; margin-bottom: 8px; }
  .btn-copy.done { background: #2e7d32; }
  .prompt-box { width: 100%; height: 160px; background: #0d0d0d; color: #bbb; border: 1px solid var(--line); border-radius: 4px; padding: 8px; font: 11px/1.5 Consolas, monospace; resize: vertical; white-space: pre; overflow: auto; }
  .acc-body td:first-child { color: var(--primary); font-family: Consolas, monospace; white-space: nowrap; }
  .chip { background: var(--primary); color: #fff; font-size: .66rem; font-weight: 600; padding: 1px 8px; border-radius: 99px; letter-spacing: 0; }

  /* images panel */
  .drop { border: 1px dashed var(--line-2); border-radius: 4px; padding: 12px; text-align: center; color: var(--text-muted); font-size: .76rem; cursor: pointer; transition: all .2s; margin-bottom: 8px; }
  .drop:hover, .drop.over { border-color: var(--primary); color: #fff; background: rgba(255,106,0,.06); }
  .drop b { color: var(--primary); }
  .imgs { display: flex; flex-direction: column; gap: 8px; }
  .imgc { display: flex; gap: 10px; background: var(--bg-light); border: 1px solid var(--line); border-radius: 4px; padding: 8px; }
  .imgc .th { width: 64px; height: 64px; flex: none; object-fit: cover; border-radius: 3px; background: #000; border: 1px solid var(--line-2); }
  .imgc .bd { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }
  .imgc .nm { display: flex; align-items: center; gap: 6px; font-size: .74rem; color: #fff; }
  .imgc .nm code { color: var(--primary); font-family: Consolas, monospace; font-size: .74rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
  .imgc .nm small { color: #777; }
  .imgc .del { background: transparent; border: none; color: #888; cursor: pointer; font-size: .8rem; padding: 2px 4px; }
  .imgc .del:hover { color: #e53935; }
  .imgc select.inp, .imgc input.inp { padding: 5px 8px; font-size: .74rem; }
  .imgc .r2 { display: flex; gap: 5px; }
  .imgc .r2 > * { flex: 1; min-width: 0; }
  .imgc .ins { background: var(--primary); color: #fff; border: none; border-radius: 3px; padding: 6px 10px; font-family: 'Oswald', sans-serif; font-size: .74rem; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; }
  .imgc .ins:hover { filter: brightness(1.1); }
  .imgs-empty { color: #555; font-size: .76rem; padding: 6px 4px; text-align: center; }

  .olist { display: flex; flex-direction: column; gap: 4px; max-height: 260px; overflow-y: auto; padding-right: 4px; }
  .oitem { display: flex; gap: 8px; width: 100%; padding: 7px 10px; background: var(--bg-light); border: 1px solid var(--line); border-left: 3px solid transparent; border-radius: 4px; color: var(--text-muted); font-size: .76rem; text-align: left; cursor: pointer; transition: all .2s; }
  .oitem:hover, .oitem.flash { color: #fff; border-left-color: var(--primary); }
  .oitem .n { color: var(--primary); font-family: 'Oswald', sans-serif; font-weight: 700; min-width: 34px; }
  .oitem .t { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .oitem.sub { padding-left: 22px; }

  /* =======================================================
     PAPER
  ======================================================= */
  .stage { width: 100%; display: flex; flex-direction: column; align-items: center; gap: 22px; }
  .stage.no-wm .wm { display: none; }
  .page { width: 210mm; height: 297mm; flex: none; background: #fff; color: var(--ink); font-family: Tinos, "Times New Roman", Times, serif; font-size: var(--nf); line-height: 1.38; padding: var(--paper-pad) var(--paper-pad) 5mm; display: flex; flex-direction: column; position: relative; overflow: hidden; border-radius: 3mm; box-shadow: 0 14px 40px rgba(0,0,0,.28); page-break-after: always; break-after: page; transform-origin: top center; transform: scale(var(--sheet-scale)); margin-bottom: calc((297mm * var(--sheet-scale)) - 297mm); margin-left: calc(((210mm * var(--sheet-scale)) - 210mm) / 2); margin-right: calc(((210mm * var(--sheet-scale)) - 210mm) / 2); }
  .page ::selection { background: rgba(142,27,42,.28); }
  .wm { position: absolute; left: 50%; top: 50%; width: 125mm; height: auto; transform: translate(-50%,-50%); opacity: .06; pointer-events: none; z-index: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .page > .hdr, .page > .body, .page > .foot { position: relative; z-index: 1; }

  /* ---- header ---- */
  .hdr { flex: none; background: linear-gradient(180deg,#9a1f2f 0%,var(--maroon) 55%,#7d1524 100%); color: #fff; border-radius: 3mm; padding: 3.6mm 6mm 3.4mm; margin-bottom: 4mm; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 5mm; box-shadow: 0 1.5mm 4mm rgba(110,18,32,.28); }
  .chap { background: var(--pink-2); color: var(--maroon); border-radius: 2.5mm; padding: 1.6mm 4.2mm; text-align: center; line-height: 1; }
  .chap small { display: block; font-size: 8pt; letter-spacing: 1pt; text-transform: uppercase; font-weight: 700; }
  .chap b { display: block; font-size: 24pt; font-weight: 700; margin-top: 1mm; }
  .hdr-tt { min-width: 0; text-align: center; }
  .hdr-title { font-size: 25pt; font-weight: 700; letter-spacing: .6pt; line-height: 1.05; text-transform: uppercase; overflow: hidden; }
  .hdr-sub { margin-top: 1.4mm; font-size: 10pt; letter-spacing: 2.6pt; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 3mm; white-space: nowrap; overflow: hidden; }
  .hdr-sub::before, .hdr-sub::after { content: ""; height: .3mm; width: 14mm; background: rgba(255,255,255,.75); flex: none; }
  .hdr-r { display: flex; flex-direction: column; align-items: center; gap: 1.6mm; }
  .hdr-r .top { display: flex; align-items: center; gap: 3.5mm; }
  .hdr-icon { width: 12mm; height: 12mm; flex: none; }
  .hand-badge { background: #fff; color: var(--maroon); border-radius: 3mm; padding: 1.8mm 4.5mm; font-family: Caveat, cursive; font-weight: 700; font-size: 14pt; line-height: 1.02; text-align: center; transform: rotate(-3deg); box-shadow: 0 1mm 3mm rgba(0,0,0,.18); white-space: pre-line; }
  .hdr-tag { font-size: 8pt; letter-spacing: 2pt; text-transform: uppercase; opacity: .95; white-space: nowrap; }
  .hdr.slim { padding: 2.2mm 5mm; margin-bottom: 3.5mm; gap: 4mm; }
  .hdr.slim .chap { padding: 1mm 3mm; } .hdr.slim .chap small { font-size: 6pt; } .hdr.slim .chap b { font-size: 13pt; }
  .hdr.slim .hdr-title { font-size: 14pt; } .hdr.slim .hdr-sub { display: none; }
  .hdr.slim .hdr-icon { width: 7.5mm; height: 7.5mm; }
  .hdr.slim .hand-badge { font-size: 10pt; padding: 1mm 3mm; } .hdr.slim .hdr-tag { display: none; }

  /* ---- body = stack of bands; each band is 2 columns, a .wide block spans both ---- */
  .body { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
  .cols { flex: 1 1 auto; min-height: 0; display: flex; gap: var(--col-gap); position: relative; }
  .cols::before { content: ""; position: absolute; top: 0; bottom: 0; left: 50%; width: .35mm; background: #b9b0b2; transform: translateX(-50%); }
  .cols.single::before { display: none; }
  .col { flex: 1 1 0; min-width: 0; overflow: hidden; }
  .cols.single .col:last-child { display: none; }
  .wide { flex: none; margin: 0 0 3mm; }

  /* ---- footer: one clean line, sitting low on the page ---- */
  .foot { flex: none; margin-top: 1.4mm; padding-top: 1.2mm; border-top: .4mm solid var(--maroon); display: flex; align-items: center; justify-content: space-between; gap: 5mm; font-size: 8pt; color: #6f6366; letter-spacing: .3pt; }
  .foot a { color: var(--maroon); font-weight: 700; text-decoration: none; }
  .foot i { color: #1fa855; }
  .foot .brand { display: flex; align-items: center; gap: 2mm; }
  .foot .brand .dw { font-family: Tinos, serif; font-weight: 700; font-size: 9pt; color: var(--maroon); letter-spacing: .6pt; text-transform: uppercase; }
  .foot .title-mini { font-style: italic; color: #8a7d80; }
  .pno { display: inline-flex; align-items: center; gap: 1.6mm; background: var(--maroon); color: #fff; border-radius: 99mm; padding: 1.1mm 3.6mm 1.1mm 3.6mm; font-size: 8pt; letter-spacing: .8pt; text-transform: uppercase; font-weight: 700; box-shadow: 0 .8mm 2mm rgba(110,18,32,.28); }
  .pno .dot { width: 5.6mm; height: 5.6mm; border-radius: 50%; background: #fff; color: var(--maroon); display: flex; align-items: center; justify-content: center; font-size: 8.5pt; }
  .pno .of { opacity: .8; font-weight: 400; }

  /* ---- blocks ---- */
  .blk { margin: 0 0 2.6mm; }
  .h1 { display: flex; align-items: stretch; background: var(--pink); border-radius: 2.2mm; overflow: hidden; margin: 1mm 0 2.6mm; }
  .h1 .num { flex: none; background: var(--maroon); color: #fff; font-weight: 700; font-size: 12.5pt; padding: 1.6mm 3.2mm; display: flex; align-items: center; border-radius: 2.2mm; box-shadow: 0 .8mm 2mm rgba(110,18,32,.3); }
  .h1 .tt { flex: 1; padding: 1.6mm 4mm; color: var(--maroon); font-weight: 700; font-size: 14pt; line-height: 1.15; }
  /* sub-topic: ❖ + text, coloured only as wide as the text, no numbering */
  .h2 { display: inline-flex; align-items: center; gap: 2.2mm; max-width: 100%; background: linear-gradient(90deg, var(--pink) 0%, var(--pink-2) 100%); border-left: 1.4mm solid var(--maroon); color: var(--maroon); font-weight: 700; font-size: 12pt; line-height: 1.15; padding: 1.1mm 4.5mm 1.1mm 3mm; border-radius: 0 2mm 2mm 0; margin: .6mm 0 2mm; box-shadow: 0 .5mm 1.5mm rgba(110,18,32,.12); }
  .h2 .dia { font-size: 9.5pt; line-height: 1; transform: translateY(-.2mm); }
  .h3 { font-weight: 700; font-size: 11pt; color: var(--maroon); margin: .8mm 0 1.4mm; padding-bottom: .5mm; border-bottom: .3mm dashed #d9b7bd; }
  .p { text-align: justify; hyphens: auto; }
  .ul { list-style: none; padding-left: 5.5mm; }
  .ul li { position: relative; margin-bottom: 1.3mm; text-align: justify; hyphens: auto; }
  .ul li::before { content: ""; position: absolute; left: -4.4mm; top: .95em; width: 1.6mm; height: 1.6mm; border-radius: 50%; background: var(--maroon); transform: translateY(-50%); }
  .ul .ul { margin-top: 1mm; padding-left: 5mm; }
  .ul .ul li::before { width: 1.6mm; height: .5mm; border-radius: 0; }
  .ol { list-style: none; counter-reset: c; }
  .ol li { counter-increment: c; position: relative; padding: 1.4mm 3mm 1.4mm 9.5mm; margin-bottom: 1.8mm; min-height: 7mm; text-align: justify; background: var(--cream-2); border-radius: 2mm; }
  .ol li::before { content: counter(c); position: absolute; left: 1.6mm; top: 1.4mm; width: 6mm; height: 6mm; border-radius: 50%; background: var(--maroon); color: #fff; font-weight: 700; font-size: 10pt; display: flex; align-items: center; justify-content: center; }
  .callout { background: var(--cream); border-left: 1.4mm solid var(--maroon); border-radius: 1.8mm; padding: 2mm 3.5mm; text-align: justify; hyphens: auto; white-space: normal; }
  .callout .lbl { font-weight: 700; color: var(--maroon); }
  .callout.def { background: var(--cream-2); border: .45mm solid var(--maroon); padding: 2.2mm 3.8mm; }
  .callout.tip { display: flex; gap: 3mm; align-items: flex-start; border-left: none; }
  .callout.tip .bulb { flex: none; width: 8mm; height: 8mm; border-radius: 50%; background: var(--maroon); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 10pt; margin-top: .3mm; }
  .callout.tip .body-t { flex: 1; min-width: 0; }
  .formula { display: flex; flex-wrap: wrap; align-items: center; gap: 2mm 3mm; }
  .formula .lbl { font-weight: 700; color: var(--maroon); }
  .formula .fx { display: inline-block; background: var(--cream); border-radius: 1.8mm; padding: 1.8mm 4mm; font-weight: 700; font-size: 11.5pt; letter-spacing: .2pt; border: .3mm solid #f0dcc0; }
  .formula.center { justify-content: center; }
  .mi { font-family: Tinos, serif; font-style: italic; background: #fff7ec; padding: 0 .8mm; border-radius: .8mm; white-space: nowrap; }
  .frac { display: inline-flex; flex-direction: column; align-items: center; vertical-align: middle; line-height: 1.05; margin: 0 .6mm; font-size: .92em; }
  .frac > span { padding: 0 .6mm; }
  .frac > span:first-child { border-bottom: .3mm solid currentColor; }
  .rad { border-top: .3mm solid currentColor; padding: 0 .4mm; }
  .fn { font-style: normal; font-weight: 600; }
  .mac { display: inline-block; position: relative; line-height: 1; }
  .mac::before { content: attr(data-a); position: absolute; left: 0; right: 0; top: -.62em; text-align: center; font-size: .72em; line-height: 1; font-weight: 400; pointer-events: none; }
  .mac.arr::before { content: "\2192"; top: -.72em; }
  .mac.warr::before { content: "\27F6"; top: -.72em; letter-spacing: -.05em; }
  .mac.bar { text-decoration: overline; text-decoration-thickness: .08em; }
  .mac.bar::before { content: none; }
  .fx .mac, .frac .mac { margin-top: .35em; }
  /* break-word (not anywhere): wrap at spaces, split a word only if it can't fit
     alone — keeps "10⁻²⁷" and its superscript together */
  /* --tf / --tpy / --tpx are set per table by fitTable() so any table, however
     wide, shrinks (font + padding) until it fits its column without broken words */
  /* auto layout: each column gets at least its longest word, extra space is shared —
     fixed layout would split width equally and break words in narrow columns */
  table.tb { width: 100%; max-width: 100%; table-layout: auto; border-collapse: separate; border-spacing: 0; font-size: var(--tf, calc(var(--nf) - .5pt)); line-height: 1.3; border: .35mm solid #c9b9bc; border-radius: 2mm; overflow: hidden; word-break: normal; overflow-wrap: break-word; }
  table.tb.force { word-break: break-word; overflow-wrap: anywhere; }
  table.tb sup, table.tb sub { white-space: nowrap; }
  table.tb th { background: var(--maroon); color: #fff; font-weight: 700; text-align: left; padding: var(--tpy, 1.4mm) var(--tpx, 2.2mm); border-right: .3mm solid rgba(255,255,255,.18); }
  table.tb th:last-child { border-right: none; }
  table.tb td { padding: var(--tpy, 1.3mm) var(--tpx, 2.2mm); border-top: .3mm solid #e2d6d8; border-right: .3mm solid #e2d6d8; vertical-align: top; }
  table.tb td:last-child { border-right: none; }
  table.tb td:first-child { background: var(--cream); font-weight: 700; }
  table.tb tr:nth-child(even) td:not(:first-child) { background: #fdf7f8; }
  .cap { font-weight: 700; font-size: 11pt; margin: 0 0 1.4mm; }
  /* images: [img: name | 60% | center | caption] */
  .blk.img { display: flex; justify-content: center; }
  .blk.img.al-left { justify-content: flex-start; }
  .blk.img.al-right { justify-content: flex-end; }
  .fig { margin: 0; max-width: 100%; }
  .fig img { display: block; width: 100%; height: auto; max-height: 130mm; object-fit: contain; border: .25mm solid #e6d6c6; border-radius: 1.2mm; background: #fff; }
  .fig figcaption { font-size: .86em; color: #5a4a4a; text-align: center; margin-top: 1.2mm; font-style: italic; line-height: 1.3; }
  .fig.missing { border: .4mm dashed #c9a9a9; border-radius: 1.5mm; padding: 4mm 3mm; text-align: center; color: #9a5a5a; font-size: .85em; background: #fff8f8; }
  .fig.missing b { font-family: Consolas, monospace; color: var(--maroon); }
  /* table heading: a folder-tab that sits on the table's top edge */
  .banner { display: inline-flex; align-items: center; gap: 2mm; max-width: 100%; background: linear-gradient(180deg, #a3233a 0%, var(--maroon) 100%); color: #fff; font-weight: 700; font-size: 10.5pt; letter-spacing: .4pt; padding: 1.5mm 4.5mm 1.5mm 3.2mm; border-radius: 2mm 2mm 0 0; position: relative; top: .35mm; box-shadow: 0 -.4mm 1.5mm rgba(110,18,32,.15); }
  .banner::before { content: "❖"; font-size: 8.5pt; opacity: .9; }
  .banner + .blk > table.tb { border-top-left-radius: 0; border-top: .7mm solid var(--maroon); }

  /* ---- question block (MCQ / numerical / any Q) ---- */
  .qb { background: #fff; border: .35mm solid #e3cfd3; border-radius: 2.2mm; padding: 2mm 2.6mm 2.2mm; }
  .qb .q-head { display: flex; gap: 2.6mm; align-items: flex-start; }
  .qb .q-badge { flex: none; background: var(--maroon); color: #fff; font-weight: 700; font-size: 9.5pt; border-radius: 1.6mm; padding: 1mm 2.2mm; line-height: 1; margin-top: .4mm; box-shadow: 0 .6mm 1.6mm rgba(110,18,32,.3); }
  .qb .q-text { flex: 1; font-weight: 700; text-align: justify; }
  .qb .q-opts { display: grid; grid-template-columns: 1fr 1fr; gap: .8mm 3mm; margin: 1.6mm 0 0 1mm; }
  .qb .q-opts.one { grid-template-columns: 1fr; }
  .qb .q-opts span { display: flex; gap: 1.6mm; }
  .qb .q-opts .l { flex: none; font-weight: 700; color: var(--maroon); min-width: 5mm; }
  .qb .q-ans { display: inline-flex; align-items: center; gap: 1.5mm; margin-top: 1.8mm; background: #e8f7ee; color: #15803d; border: .3mm solid #bfe6cc; border-radius: 99mm; padding: .6mm 3mm; font-weight: 700; font-size: calc(var(--nf) - .5pt); }
  .qb .q-sol { margin-top: 1.8mm; background: var(--cream-2); border-left: 1.2mm solid #d9a441; border-radius: 1.6mm; padding: 1.6mm 3mm; }
  .qb .q-sol .lbl { font-weight: 700; color: #9a6b00; }
  .qb.numerical .q-badge { background: #1d4ed8; box-shadow: 0 .6mm 1.6mm rgba(29,78,216,.3); }

  /* reaction arrow with a condition written above:  ->[heat] */
  .arr { display: inline-flex; flex-direction: column; align-items: center; vertical-align: middle; line-height: 1; margin: 0 1mm; }
  .arr small { font-size: .62em; font-style: italic; color: var(--maroon); margin-bottom: -.2mm; }
  .qt { font-family: Caveat, cursive; font-weight: 700; font-size: 15pt; color: var(--maroon); transform: rotate(-2deg); transform-origin: left; padding: 1mm 2mm; }
  .p .ln + .ln, .callout .ln + .ln { display: block; }
  .ln { display: block; }
  .ln.i1 { padding-left: 4mm; } .ln.i2 { padding-left: 8mm; }

  /* highlight palette — five slots, each a CSS variable so changing a colour
     re-colours every use of that marker at once */
  :root { --hl1: #fff176; --hl2: #b3e5fc; --hl3: #c5e1a5; --hl4: #f8bbd0; --hl5: #ffcc80; }
  mark { color: inherit; font-weight: 700; padding: 0 .5mm; border-radius: .7mm; box-decoration-break: clone; -webkit-box-decoration-break: clone; }
  mark.hl1 { background: var(--hl1); }
  mark.hl2 { background: var(--hl2); }
  mark.hl3 { background: var(--hl3); }
  mark.hl4 { background: var(--hl4); }
  mark.hl5 { background: var(--hl5); }
  .em { color: var(--maroon); font-weight: 700; }
  sup, sub { font-size: .72em; line-height: 0; }
  /* nuclide notation  ^A_Z X  →  mass number over atomic number, both before the symbol */
  .nuc { display: inline-flex; align-items: center; white-space: nowrap; vertical-align: baseline; }
  .nuc .ms { display: inline-flex; flex-direction: column; align-items: flex-end; line-height: 1; font-size: .6em; margin-right: .25mm; }
  .nuc .ms span + span { margin-top: .2em; }
  .nuc .el { font-weight: 700; }

  .empty-paper { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; color: #9a8f91; font-size: 12pt; line-height: 1.8; padding: 10mm; }
  .empty-paper b { color: var(--maroon); }
  .meta { font-size: .76rem; color: #6b6363; text-align: center; padding: 14px 10px 0; }
  .zoom-badge { position: fixed; right: 18px; bottom: 18px; z-index: 950; padding: 7px 14px; border-radius: 99px; background: rgba(20,20,20,.94); border: 1px solid var(--line-2); color: #fff; font-family: 'Oswald', sans-serif; font-size: .8rem; letter-spacing: 1px; pointer-events: none; opacity: 0; transform: translateY(6px); transition: all .2s ease; }
  .zoom-badge.show { opacity: 1; transform: translateY(0); }
  .toast { position: fixed; left: 50%; bottom: 22px; transform: translateX(-50%) translateY(10px); z-index: 1300; background: rgba(16,16,16,.96); border: 1px solid var(--line-2); color: #fff; padding: 9px 16px; border-radius: 8px; font-size: .8rem; opacity: 0; transition: all .25s; pointer-events: none; }
  .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

  /* ---------------- responsive ---------------- */
  @media screen and (max-width: 1240px) {
    .editor-col { position: fixed; left: 0; top: var(--toolbar-h); z-index: 895; flex-basis: auto; width: min(480px, 94vw); height: calc(100dvh - var(--toolbar-h)); max-height: none; padding: 14px; background: var(--bg-dark); border-right: 1px solid var(--line-2); box-shadow: 8px 0 30px rgba(0,0,0,.6); transform: translateX(-100%); opacity: 1; transition: transform .34s var(--ease); }
    body.drawer-open .editor-col { transform: translateX(0); }
    body:not(.drawer-open) .editor-col { flex-basis: auto; width: min(480px, 94vw); padding: 14px; opacity: 1; transform: translateX(-100%); }
    body.drawer-open .side-tab { opacity: 0; visibility: hidden; pointer-events: none; }
    .scrim { display: block; position: fixed; inset: 0; z-index: 890; background: rgba(0,0,0,.7); opacity: 0; visibility: hidden; transition: opacity .3s, visibility .3s; }
    body.drawer-open .scrim { opacity: 1; visibility: visible; }
    .preview-col { flex: 1 1 100%; width: 100%; }
  }
  @media screen and (max-width: 900px) {
    html { overflow-x: hidden; }
    body { height: auto; overflow: visible; }
    .workspace { display: block; height: auto; margin-top: var(--toolbar-h); }
    .editor-col, body:not(.drawer-open) .editor-col, body.drawer-open .editor-col { position: static; width: 100%; flex-basis: auto; min-width: 0; height: auto; max-height: none; padding: 16px 12px 4px; opacity: 1; visibility: visible; overflow: visible; pointer-events: auto; transform: none; background: transparent; border-right: none; box-shadow: none; }
    .preview-col { width: 100%; height: auto; padding: 8px 10px 70px; overflow: visible; }
    .side-tab, .scrim, .panel-close { display: none !important; }
    .stage { overflow-x: hidden; }
    body.bar-hidden .toolbar { transform: translateY(-100%); }
    .row2 { grid-template-columns: 1fr; }
  }
  @media screen and (max-width: 640px) {
    .bar-inner { width: 96%; } .logo a { font-size: 1.25rem; } .actions { width: 100%; }
    .btn { flex: 1 1 auto; justify-content: center; padding: 10px 12px; font-size: .76rem; } .hint { font-size: .68rem; }
  }
  @media (prefers-reduced-motion: reduce) { * { transition-duration: .01ms !important; } }
  @media print {
    html, body { background: #fff; height: auto; overflow: visible; }
    .toolbar, .editor-col, .side-tab, .scrim, .meta, .zoom-badge, .floatbar, .toast { display: none !important; }
    .workspace { display: block; margin: 0; height: auto; }
    .preview-col { padding: 0; overflow: visible; background: #fff; height: auto; }
    .stage { gap: 0; }
    .page { border-radius: 0; box-shadow: none; margin: 0 !important; transform: none !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .page * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>

<header class="toolbar" id="topbar">
  <div class="bar-inner">
    <div class="logo"><a href="javascript:void(0)">NOTES <span>D2D</span></a></div>
    <div class="actions">
      <a class="btn ghost" href="admin_panel.php" title="Back to Admin Panel"><i class="fa fa-arrow-left"></i> Admin</a>
      <button type="button" class="btn ghost" id="btnSample"><i class="fa fa-wand-magic-sparkles"></i> Sample</button>
      <button type="button" class="btn" id="btnPrint"><i class="fa fa-print"></i> Print / PDF</button>
      <button type="button" class="btn danger" id="btnClear"><i class="fa fa-trash"></i> Clear</button>
    </div>
  </div>
  <div class="hint">Har chapter <b>auto-save</b> hota hai DB me (<b>Ctrl+S</b> = save now) · Left me <b>Library</b> se chapter switch karo · <b>Ctrl+Z / Ctrl+Y</b> undo-redo · <b>$…$</b> math <span style="opacity:.45">v3.0 · <?= htmlspecialchars($pmAdminEmail, ENT_QUOTES, 'UTF-8') ?></span></div>
</header>

<button type="button" class="side-tab" id="sideTab" aria-expanded="true"><i class="fa fa-angle-right tab-chev"></i><span class="tab-label">Editor</span></button>
<div class="scrim" id="scrim"></div>

<!-- floating toolbar for selections made on the page itself -->
<div class="floatbar" id="floatbar">
  <button type="button" data-wrap="**" title="Bold"><b>B</b></button>
  <button type="button" data-wrap="*" title="Italic"><i>I</i></button>
  <button type="button" data-wrap="__" title="Underline"><u>U</u></button>
  <button type="button" data-wrap="==" title="Highlight 1"><span class="sw" style="background:var(--hl1)"></span></button>
  <button type="button" data-wrap="%%" title="Highlight 2"><span class="sw" style="background:var(--hl2)"></span></button>
  <button type="button" data-wrap="^^" title="Highlight 3"><span class="sw" style="background:var(--hl3)"></span></button>
  <button type="button" data-wrap="!!" title="Highlight 4"><span class="sw" style="background:var(--hl4)"></span></button>
  <button type="button" data-wrap="::" title="Highlight 5"><span class="sw" style="background:var(--hl5)"></span></button>
  <button type="button" data-unwrap="hl" title="Remove highlight"><span class="sw" style="background:var(--hl1);position:relative"><i class="fa fa-slash" style="position:absolute;inset:0;font-size:11px;color:#c62828;line-height:11px"></i></span></button>
  <button type="button" data-wrap="@@" title="Maroon text"><span class="sw" style="background:#8e1b2a"></span></button>
  <button type="button" data-wrap="$" title="Inline math">ƒ</button>
  <button type="button" data-clear="1" title="Remove ALL formatting"><i class="fa fa-eraser"></i></button>
  <span class="sep"></span>
  <button type="button" data-line="# " title="Section">H1</button>
  <button type="button" data-line="## " title="Sub-topic">H2</button>
  <button type="button" data-line="### " title="Small heading">H3</button>
  <button type="button" data-line="- " title="Bullet"><i class="fa fa-list-ul"></i></button>
  <button type="button" data-line="1. " title="Numbered"><i class="fa fa-list-ol"></i></button>
  <button type="button" data-line="> " title="Box"><i class="fa fa-square-caret-right"></i></button>
  <button type="button" data-line="$ " title="Formula box">ƒx</button>
  <button type="button" data-line="" title="Normal text">¶</button>
</div>

<div class="workspace">
  <aside class="editor-col">
    <div class="editor" id="editor">
      <div class="panel">
        <div class="panel-head">
          <h1><i class="fa fa-book-open"></i> Notes D2D</h1>
          <button type="button" class="panel-close" id="panelClose" aria-label="Close editor"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="panel-body">
          <div class="status" id="status">Ready</div>

          <div class="sync" id="sync"><span class="dot"></span><span class="txt" id="syncTxt">Connecting…</span><button type="button" id="btnSaveNow" title="Save now (Ctrl+S)"><i class="fa fa-cloud-arrow-up"></i> Save</button></div>

          <div class="acc open" id="accLib">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-folder-tree"></i>&nbsp; Library <span class="chip" id="libCount">0</span></span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body">
              <div class="lib-top">
                <input class="inp" id="libSearch" type="search" placeholder="Search subject / chapter / title…" autocomplete="off">
                <button type="button" class="lib-new" id="btnNewNote" title="New chapter"><i class="fa fa-plus"></i> New</button>
              </div>
              <div class="lib-list" id="libList"><div class="lib-empty">Loading…</div></div>
              <div class="lib-tools">
                <button type="button" id="btnLibRefresh" title="Reload list from server"><i class="fa fa-rotate"></i> Refresh</button>
                <button type="button" id="btnDuplicate" title="Copy current chapter as a new one"><i class="fa fa-copy"></i> Duplicate</button>
                <button type="button" id="btnTrash" title="Show deleted chapters"><i class="fa fa-trash-can"></i> Trash</button>
              </div>
            </div>
          </div>

          <div class="acc" id="accHelp">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-circle-info"></i>&nbsp; Formatting guide</span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body">
              <p><b style="color:#fff">Do tarike:</b> (1) page par text select karo → chhota toolbar aayega → style lagao/hatao. (2) Neeche textarea me markup likho ya toolbar use karo. Har button <b style="color:#fff">toggle</b> hai — dobara dabane se style hat jata hai, aur cursor jahan hai wahan ka style button par highlight dikhta hai.</p>
              <table>
                <tr><td># Heading</td><td>Section band (1.1, 1.2 … auto-number)</td></tr>
                <tr><td>## Sub-topic</td><td>Sub-section band (1.2.1 …)</td></tr>
                <tr><td>### Small heading</td><td>Maroon dashed-underline heading</td></tr>
                <tr><td>- item</td><td>Bullet (2 spaces + - = sub-bullet)</td></tr>
                <tr><td>1. item</td><td>Maroon circle numbered item</td></tr>
                <tr><td>&gt; Note: text</td><td>Cream box. Agli lines (blank line tak) usi box me — har Enter = nayi line</td></tr>
                <tr><td>$ E = mc^2</td><td>Formula box. <b>$…$</b> inline math. LaTeX: \frac{a}{b} \sqrt{x} \sqrt[3]{x} \left( \right) \times \cdot \pm \lambda ^{2} _{0} 60^\circ \therefore</td></tr>
                <tr><td>\cos\theta  \sin^2\theta<br>\log_{10} x  \lim_{x \to 0}</td><td>Functions roman me: cos θ, sin²θ, log₁₀ x — hamesha backslash ke saath</td></tr>
                <tr><td>\vec{F}  \hat{n}  \bar{v}<br>\dot{x}  \overline{AB}  \overrightarrow{AB}</td><td>Vector arrow, hat, bar, dot upar aata hai. \mathbf{F} = bold. e.g. <code>W = F \times S \times \cos\theta = \vec{F} \cdot \vec{S}</code></td></tr>
                <tr><td>Q1. text (a) .. (b) ..<br>Ans: b</td><td>MCQ block — options ek line me ya alag lines me. <b>Solution:</b> ke baad steps (numerical). Bina number ke bhi <code>Q.</code> chalega, auto-number hota hai</td></tr>
                <tr><td>H2SO4  Ca^2+  SO4^2-<br>2H2 + O2 ->[heat] 2H2O<br>N2 + 3H2 &lt;=&gt; 2NH3</td><td>Chemistry: H₂SO₄, Ca²⁺, SO₄²⁻, arrow ke upar condition, ⇌ equilibrium — sab auto</td></tr>
                <tr><td>| a | b |</td><td>Table (pehli row header). Tab-separated bhi. Kitni bhi badi ho, font/padding khud ghat ke <b>ek column me fit</b> hoti hai. Lambi table agle column me header ke saath continue. Poori page width chahiye to table ke upar <code>[Wide]</code> likho</td></tr>
                <tr><td>[Banner] text</td><td>Maroon banner (table ke upar)</td></tr>
                <tr><td>~ text</td><td>Handwritten line</td></tr>
                <tr><td>^1_1H  ^235_92U  ^14_6C</td><td>Isotope / nuclide: mass number upar, atomic number neeche, symbol se pehle (¹₁H)</td></tr>
                <tr><td>**b** *i* __u__</td><td>Bold / italic / underline</td></tr>
                <tr><td>==t== %%t%% ^^t^^ !!t!! ::t::</td><td>Highlight 1–5 (colours upar palette se — ek badlo, sab jagah badlega). <code>@@t@@</code> maroon text</td></tr>
                <tr><td>[img: name | 60% | center | caption]</td><td>Image (Images panel se add karo). Width %, left/center/right, caption — sab optional. Upar <code>[Wide]</code> = poori page width</td></tr>
                <tr><td>---col--- / ---page---</td><td>Column / page break</td></tr>
              </table>
              <p style="margin-top:8px">Enter = nayi line (paragraph me bhi). Leading spaces indent bante hain.</p>
            </div>
          </div>

          <div class="acc" id="accPrompt">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-robot"></i>&nbsp; AI prompt (ChatGPT / Claude)</span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body">
              <p>Ye prompt copy karke ChatGPT / Claude me paste karo, neeche apna topic likho — output seedha yahan paste karne par sahi format me render hoga.</p>
              <button type="button" class="btn-copy" id="btnCopyPrompt"><i class="fa fa-copy"></i> Copy prompt</button>
              <textarea id="aiPrompt" class="prompt-box" readonly spellcheck="false">You are a study-notes writer for Indian diploma/engineering students. Convert the material I give you into SHORT NOTES written in Hinglish (Hindi in Roman script mixed with English technical terms, same style as: "Atom kisi element ka smallest particle hota hai jo chemical reaction me part leta hai."). Keep sentences short and exam-oriented.

Your output will be pasted into a renderer that understands ONLY the lightweight markup below. Follow it EXACTLY. Output ONLY the notes text inside one plain code block — no explanations before or after.

════════════════════ MARKUP RULES ════════════════════

HEADER (first lines only, one per line):
Title: &lt;chapter title, e.g. Atomic Structure&gt;
Chapter: &lt;number, e.g. 1&gt;
Subtitle: &lt;optional — omit this line if not needed&gt;

STRUCTURE (each on its own line, blank line between blocks):
# Section title              → main section band (auto-numbered 1.1, 1.2 …)
## Sub-topic title           → sub-topic band with a ❖ mark (not numbered)
### Small heading            → small underlined heading (e.g. "Jahan:", "Types:")
Plain text                   → paragraph. Enter = new line inside the paragraph. Blank line = new block.
- point                      → bullet point
  - sub point                → sub-bullet (2 spaces before the dash)
1. point                     → numbered item (maroon circle)  (use 1. 2. 3. …)

BOXES (label decides the style):
&gt; Note: text                 → cream note box
&gt; Key Idea: text             → note box
&gt; Important: text            → note box
&gt; Definition: text           → bordered definition box
&gt; Statement: text            → bordered box (laws / principles)
&gt; Tip: text                  → lightbulb box
&gt; Example: text              → lightbulb box
&gt; Remember: text             → lightbulb box
&gt; Interesting Fact: text     → lightbulb box
Lines written directly after a "&gt;" line (until a blank line) stay inside the same box, each on its own line.

FORMULAS:
$ E = mc^2                   → formula box (one formula per line, starts with "$ ")
$ Formula: v = u + at        → formula box with a label
Inline math inside text: $2n^2$  $\frac{h}{p}$  $\lambda$
Allowed LaTeX-lite: \frac{a}{b}  \sqrt{x}  \sqrt[3]{x}  \left(  \right)  \times  \cdot  \pm  \ge  \le  \ne  \approx  \propto  \to  \infty  \therefore  \because  \quad
Greek: \lambda \Delta \psi \pi \theta \mu \nu \sigma \omega \alpha \beta \gamma \varepsilon \rho \tau \phi \Omega
Superscript ^2 or ^{-27}, subscript _0 or _{max}.  10^8, H_2O also work.  Degrees: 60^\circ
Trig / log functions: \sin\theta  \cos\theta  \tan\theta  \sin^2\theta  \log_{10} x  \ln x  \lim_{x \to 0}  (always with backslash, e.g. W = F \times S \times \cos\theta)
Vectors / accents: \vec{F}  \vec{v}  \hat{n}  \bar{v}  \dot{x}  \overline{AB}  \overrightarrow{AB}  \mathbf{F}
Example: $ Formula: W = F \times S \times \cos\theta = \vec{F} \cdot \vec{S}
Sum / integral: \sum_{n=1}^{\infty}  \int_0^t  \partial  \nabla

CHEMISTRY (write plainly — the renderer formats it):
H2SO4  CO2  Ca(OH)2          → subscripts are automatic (H₂SO₄)
Na^+  Cl^-  Ca^2+  SO4^2-    → ionic charges as superscript
2H2 + O2 -&gt; 2H2O             → reaction arrow;  -&gt;[heat] or -&gt;[catalyst] writes the condition above the arrow
N2 + 3H2 &lt;=&gt; 2NH3            → equilibrium arrow ⇌
Put every reaction on its own line starting with "$ " so it appears as a formula box.

QUESTIONS (MCQ / numerical / short answer):
Q1. Question text (a) option (b) option (c) option (d) option
Ans: c
Q2. Numerical question text
Solution: step 1 on this line
= step 2 on its own line
= final value with unit
Ans: 7.28 Å
(Options can also be written one per line as "(a) text". Leave a blank line after each question. Numbering is automatic if you write just "Q.")

TABLES:
[Banner] Table title         → maroon tab heading on the table (optional, put right above the table)
| Header 1 | Header 2 | Header 3 |
| cell | cell | cell |
| cell | cell | cell |
First row = header. First column is shown bold. Use tables for comparisons (Atom vs Molecule, Bohr vs Quantum, etc.).
Any table, however wide, is shrunk to fit one column. Write "[Wide]" on the line above a table only if it must span the full page width.
Greek letters: \varepsilon_0 \epsilon \lambda \Delta etc. all work (E_n = -\frac{1}{8} \times \frac{m e^4}{\varepsilon_0^2 h^2 n^2}).

ISOTOPES / NUCLIDES:
^1_1H  ^2_1H  ^235_92U  ^14_6C  → mass number over atomic number, in front of the symbol (¹₁H)

INLINE STYLING (use generously on key terms, like a highlighted textbook):
**bold**   *italic*   __underline__
==yellow highlight==         → use ONLY this yellow highlight for key terms, definitions, values and results
@@maroon text@@              → coloured text (rare — for a warning word or an exception)
Do NOT use %% ^^ !! :: markers.

IMAGES (placeholders only — I will attach the real pictures later):
[img: short-name | 60% | center | caption]   → put this line where a diagram/figure would genuinely help (max 1 per topic). short-name = lowercase-with-hyphens.

OTHER:
~ text                       → handwritten-style line (use once, as a closing quote)
---col---                    → force new column
---page---                   → force new page

════════════════════ HARD RULES ════════════════════
1. Never use markdown headings beyond ### (no ####). Never put the chapter title as "# …" — use "Title:".
2. Never use "|" inside normal sentences (it would be read as a table). Never use "$" for money.
3. Do not write lines in ALL CAPS unless they are headings. Do not start a sentence with a number + dot unless it is a list item.
4. Every formula on its own line with "$ ". Do not write formulas in plain text.
5. Keep bullets to 1–2 lines. Bold the key term in each bullet with **…**.
6. Prefer explicit markup (#, ##, -, &gt;, $) over relying on auto-detection.
7. No emojis, no HTML, no markdown links/images.
8. Language: Hinglish. Technical terms, units, symbols and formulas in English.

════════════════════ CONTENT GUIDELINES ════════════════════
- Order: Introduction → concepts in logical sequence → comparisons (tables) → formulas → key points / quick revision.
- For each concept: 1 short definition (&gt; Definition:), 2–5 bullets, formula if any, 1 table if there is a comparison, 1 tip/trick if useful.
- End with "## Quick Revision" containing 5–10 one-line bullets.
- No length limit: cover the topic completely — every concept, formula, derivation step, comparison and typical question that an exam can ask. Do not shorten to fit a page count.

════════════════════ EXAMPLE OF CORRECT OUTPUT ════════════════════
Title: Atomic Structure
Chapter: 1
Subtitle: Chemistry Short Notes

# Introduction
Atoms hi chemistry ki foundation hain — universe me jitna bhi matter hai, sab atoms se bana hai. **Atom** word Greek word **"atomos"** se aaya hai jiska matlab hai "jo divide nahi kiya ja sakta".

# Atom and Molecules

## Atom
- Atom kisi element ka **smallest particle** hota hai jo chemical reaction me part leta hai.
- Chemical reaction me sirf atoms ka **rearrangement** hota hai.
- Atom free state me exist nahi karta (except ==noble gases==).

## Molecule
&gt; Definition: **Molecule** kisi element ya compound ka **smallest stable particle** hota hai jo independently exist kar sakta hai.

- Molecule me jitne atoms hote hain, use uski **atomicity** kehte hain.

[Banner] Atomicity ke types
| Atomicity | Example |
| One (Monatomic) | Noble gases (He, Ne) |
| Two (Diatomic) | H_2, N_2, O_2, Cl_2 |
| Three (Triatomic) | Ozone (O_3) |

# Atomic Number and Mass Number
- Nucleus me jitne protons, wahi **atomic number (Z)** hai. Isotopes: ^1_1H, ^2_1H, ^3_1H — ==same Z==, ==different A==.
- Protons + neutrons ka total = **mass number (A)**.

$ A = p + n

$ Z = protons ki sankhya = electrons ki sankhya

&gt; Tip: Neutral atom me hamesha ==protons = electrons== hote hain.

$ 2H2 + O2 -&gt;[spark] 2H2O

## Practice Questions
Q1. Nucleus me kaun se particles hote hain? (a) electron, proton (b) proton, neutron (c) electron, neutron (d) sirf proton
Ans: b

Q2. Ek atom me 11 protons aur 12 neutrons hain. Mass number nikalo.
Solution: A = p + n
= 11 + 12
= 23
Ans: 23

## Quick Revision
- Atom = smallest particle, molecule = smallest **stable** particle
- Atomicity = molecule me atoms ki sankhya
- A = p + n, Z = p = e

~ Atoms — The Building Blocks of Everything

════════════════════ NOW WRITE ════════════════════
Topic / material: &lt;&lt;PASTE YOUR TOPIC, SYLLABUS POINTS, OR RAW TEXT HERE&gt;&gt;
</textarea>
            </div>
          </div>

          <div class="field"><label class="field-label">Subject</label><input class="inp" id="subjInput" type="text" value="" placeholder="e.g. Chemistry — library isse group karti hai" autocomplete="off" list="subjList"><datalist id="subjList"></datalist></div>
          <div class="row3">
            <div class="field"><label class="field-label">Chapter</label><input class="inp" id="chapInput" type="text" value="1" autocomplete="off"></div>
            <div class="field"><label class="field-label">Title</label><input class="inp" id="titleInput" type="text" value="Atomic Structure" autocomplete="off"></div>
          </div>
          <div class="row2">
            <div class="field"><label class="field-label">Subtitle (optional)</label><input class="inp" id="subInput" type="text" value="" placeholder="e.g. Chemistry Notes — khali chhodo to nahi dikhega" autocomplete="off"></div>
            <div class="field"><label class="field-label">Corner badge</label><input class="inp" id="badgeInput" type="text" value="Small Notes / Big Results" autocomplete="off"></div>
          </div>
          <div class="field"><label class="field-label">Header tagline</label><input class="inp" id="tagInput" type="text" value="Study | Practice | Score High" autocomplete="off"></div>

          <div class="checks">
            <label class="chk"><input type="checkbox" id="optAuto" checked> Auto headings / boxes / formulas</label>
            <label class="chk"><input type="checkbox" id="optBullets"> Auto-bullet line runs</label>
            <label class="chk"><input type="checkbox" id="optMath" checked> Math symbols</label>
            <label class="chk"><input type="checkbox" id="optChem" checked> Chemical subscripts (H2O → H₂O)</label>
            <label class="chk"><input type="checkbox" id="optTwoCol" checked> 2 columns</label>
            <label class="chk"><input type="checkbox" id="optWM" checked> Watermark</label>
            <label class="chk"><input type="checkbox" id="optBrand" checked> Brand footer</label>
            <label class="chk">Font <input type="number" id="optFont" class="inp num" value="10.5" min="8" max="13" step="0.5"> pt</label>
          </div>

          <div class="field pal-row">
            <label class="field-label">Highlight colours <span style="color:#666;text-transform:none;letter-spacing:0">(== %% ^^ !! ::)</span></label>
            <div class="pal" id="pal">
              <input type="color" data-hl="1" title="Highlight 1  ==text==">
              <input type="color" data-hl="2" title="Highlight 2  %%text%%">
              <input type="color" data-hl="3" title="Highlight 3  ^^text^^">
              <input type="color" data-hl="4" title="Highlight 4  !!text!!">
              <input type="color" data-hl="5" title="Highlight 5  ::text::">
              <select id="palPreset" class="inp" title="Change all five at once">
                <option value="">Preset…</option>
                <option value="textbook">Textbook (yellow/blue/green/pink/orange)</option>
                <option value="pastel">Pastel</option>
                <option value="bright">Bright</option>
                <option value="mono">Only yellow</option>
                <option value="print">Printer-safe (light)</option>
              </select>
            </div>
          </div>

          <div class="acc" id="accSource">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-file-lines"></i>&nbsp; Chapter content (source) <span class="chip" id="srcCount">0</span></span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body">
              <p style="margin-top:8px">Yahan chapter ka <b style="color:#fff">raw material</b> rakho — syllabus points, book ka text, AI prompt ka input. Ye <b style="color:#fff">notes se alag</b> save hota hai, print me nahi aata.</p>
              <textarea id="srcInput" class="inp" spellcheck="false" placeholder="Chapter ka original content / syllabus / reference text yahan paste karo…"></textarea>
              <div class="src-tools">
                <small id="srcMeta">0 words</small>
                <button type="button" class="lib-tools-btn" id="btnSrcToNotes" style="background:transparent;border:1px solid var(--line-2);color:#bbb;border-radius:3px;padding:5px 9px;font-size:.68rem;cursor:pointer;font-family:'Oswald',sans-serif;letter-spacing:.8px;text-transform:uppercase" title="Append source into notes editor at cursor"><i class="fa fa-arrow-turn-down"></i> Send to notes</button>
                <button type="button" id="btnSrcCopy" style="background:transparent;border:1px solid var(--line-2);color:#bbb;border-radius:3px;padding:5px 9px;font-size:.68rem;cursor:pointer;font-family:'Oswald',sans-serif;letter-spacing:.8px;text-transform:uppercase" title="Copy source to clipboard"><i class="fa fa-copy"></i> Copy</button>
              </div>
            </div>
          </div>

          <div class="field">
            <label class="field-label">Notes text</label>
            <div class="fmt" id="fmt">
              <button type="button" id="btnUndo" title="Undo (Ctrl+Z)"><i class="fa fa-rotate-left"></i></button>
              <button type="button" id="btnRedo" title="Redo (Ctrl+Y)"><i class="fa fa-rotate-right"></i></button>
              <span class="sep"></span>
              <button type="button" data-wrap="**" title="Bold (Ctrl+B)"><b>B</b></button>
              <button type="button" data-wrap="*" title="Italic (Ctrl+I)"><i>I</i></button>
              <button type="button" data-wrap="__" title="Underline (Ctrl+U)"><u>U</u></button>
              <button type="button" data-wrap="==" title="Highlight 1"><span class="sw" style="background:var(--hl1)"></span></button>
              <button type="button" data-wrap="%%" title="Highlight 2"><span class="sw" style="background:var(--hl2)"></span></button>
              <button type="button" data-wrap="^^" title="Highlight 3"><span class="sw" style="background:var(--hl3)"></span></button>
              <button type="button" data-wrap="!!" title="Highlight 4"><span class="sw" style="background:var(--hl4)"></span></button>
              <button type="button" data-wrap="::" title="Highlight 5"><span class="sw" style="background:var(--hl5)"></span></button>
              <button type="button" data-unwrap="hl" title="Remove highlight from selection"><span class="sw" style="background:var(--hl1);position:relative"><i class="fa fa-slash" style="position:absolute;inset:0;font-size:12px;color:#c62828;line-height:12px"></i></span></button>
              <button type="button" data-wrap="@@" title="Maroon text"><span class="sw" style="background:#8e1b2a"></span></button>
              <button type="button" data-wrap="$" title="Inline math">ƒ</button>
              <button type="button" data-clear="1" title="Remove ALL formatting"><i class="fa fa-eraser"></i></button>
              <span class="sep"></span>
              <button type="button" data-line="# " title="Section heading">H1</button>
              <button type="button" data-line="## " title="Sub-topic">H2</button>
              <button type="button" data-line="### " title="Small heading">H3</button>
              <button type="button" data-line="- " title="Bullet"><i class="fa fa-list-ul"></i></button>
              <button type="button" data-line="1. " title="Numbered"><i class="fa fa-list-ol"></i></button>
              <button type="button" data-line="> " title="Box"><i class="fa fa-square-caret-right"></i></button>
              <button type="button" data-line="$ " title="Formula box">ƒx</button>
              <button type="button" data-line="~ " title="Handwritten"><i class="fa fa-pen-nib"></i></button>
              <button type="button" data-line="" title="Normal text">¶</button>
              <span class="sep"></span>
              <button type="button" data-insert="| Point | Atom | Molecule |&#10;| Definition | Smallest particle | Smallest stable particle |&#10;| Existence | Not stable alone | Exists independently |" title="Insert table"><i class="fa fa-table"></i></button>
              <button type="button" data-insert="---col---" title="Column break"><i class="fa fa-grip-lines-vertical"></i></button>
              <button type="button" data-insert="---page---" title="Page break"><i class="fa fa-file"></i></button>
              <button type="button" id="btnImgTool" title="Add image"><i class="fa fa-image"></i></button>
            </div>
            <textarea id="rawInput" spellcheck="false" placeholder="# Introduction&#10;Atoms hi chemistry ki foundation hain …&#10;&#10;## Atom&#10;- Atom kisi element ka **smallest particle** hota hai&#10;&#10;> Note: Atom free state me exist nahi karta.&#10;Yeh usi box me dusri line hai.&#10;&#10;$ A = p + n"></textarea>
          </div>

          <div class="acc" id="accImages">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-image"></i>&nbsp; Images <span class="chip" id="imgCount">0</span></span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body">
              <input type="file" id="imgFile" accept="image/*" multiple hidden>
              <div class="drop" id="imgDrop"><b><i class="fa fa-plus"></i> Add image</b> — click karo ya photo yahan drop karo<br><span style="font-size:.7rem">PNG / JPG / screenshot · auto-compress hoti hai</span></div>
              <div class="imgs" id="imgList"></div>
              <p style="margin-top:8px">Har image ke niche <b style="color:#fff">"Insert after"</b> dropdown se position chuno → <b style="color:#fff">Insert</b>. Text me line banti hai: <code style="color:var(--primary)">[img: name | 60% | center | caption]</code> — ise kahin bhi move kar sakte ho. Poori page width ke liye upar <code style="color:var(--primary)">[Wide]</code> likho.</p>
            </div>
          </div>

          <div class="acc open" id="accOutline">
            <button type="button" class="acc-head" data-acc><span><i class="fa fa-list-tree"></i>&nbsp; Outline <span class="chip" id="oCount">0</span></span><i class="fa fa-angle-down chev"></i></button>
            <div class="acc-body"><div class="olist" id="oList"></div></div>
          </div>
        </div>
      </div>
    </div>
  </aside>

  <div class="zoom-badge" id="zoomBadge">100%</div>
  <div class="toast" id="toast"></div>

  <!-- shown when a local draft is newer than the server copy, or the server has a newer version -->
  <div class="modal-scrim" id="modalScrim">
    <div class="modal">
      <h3><i class="fa fa-triangle-exclamation"></i> <span id="modalTitle">Unsaved draft mila</span></h3>
      <p id="modalBody"></p>
      <div class="row" id="modalRow"></div>
    </div>
  </div>

  <main class="preview-col" id="previewCol">
    <div class="stage" id="stage"></div>
    <div class="meta" id="meta"></div>
  </main>
</div>

<script>
(function () {
  "use strict";
  var $ = function (id) { return document.getElementById(id); };
  var ta = $("rawInput"), stage = $("stage"), statusEl = $("status"), metaEl = $("meta");
  function setStatus(t) { statusEl.textContent = t; }
  function esc(s) { return String(s == null ? "" : s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"); }
  var toastT = null;
  function toast(msg) { var t = $("toast"); t.textContent = msg; t.classList.add("show"); if (toastT) clearTimeout(toastT); toastT = setTimeout(function () { t.classList.remove("show"); }, 1800); }

  var SITE_URL = "https://diplomawallah.in/", WA_NUMBER = "9153950552";
  var WA_LINK = "https://wa.me/91" + WA_NUMBER + "?text=" + encodeURIComponent("Hi, I want to join the D2D batch");

  var SAMPLE = [
"de Broglie Hypothesis (1924)","",
"1924 mein Louis de Broglie ne ek paper likha jisme describe kiya ki electron jaise particles bhi wave ki tarah behave kar sakte hain.","",
"Key Idea: Har matter (jo particles se bani hai), jab motion mein hota hai, to woh wave-like properties dikhata hai. Yeh waves **matter waves** ya **de Broglie waves** kehlati hain.","",
"## Context (Till Now We Knew)","",
"- Light wave ki tarah behave karti hai (interference, diffraction)",
"- Light particle ki tarah bhi behave karti hai (photoelectric effect)",
"- de Broglie ka naya idea: Agar particles motion mein hain, to woh bhi wave-like properties dikhate hain","",
"## de Broglie Formula","",
"$ \\lambda = \\frac{h}{p} = \\frac{h}{m \\times v}","",
"Jahan:","",
"- $\\lambda$ = de Broglie wavelength",
"- h = Planck's constant",
"- p = momentum = m × v","",
"> Note: de Broglie wavelength hamesha speed of light (3 × 10^8 m/s) se kam hoti hai.",
"Bade objects: mass zyada → wavelength na ke barabar.",
"Electron: mass kam → wavelength observable.","",
"Heisenberg's Uncertainty Principle (1927)","",
"Statement: Yeh impossible hai ki hum ek electron ki exact position aur exact momentum ek saath simultaneously determine kar sakein.","",
"$ \\Delta x \\cdot \\Delta p \\ge \\frac{h}{4\\pi}","",
"Max electrons in a shell: $2n^2$, in a subshell: $2\\left(2l+1\\right)$","",
"Comparison:","",
"| Bohr Model | Quantum Mechanical Model |",
"| Electron fixed circular orbits mein move karta hai | Electron probability cloud mein hota hai |",
"| Electron ko sirf particle treat kiya | Electron ko particle aur wave dono ki properties |","",
"[Banner] Comparison Table — Proton, Neutron, Electron",
"| Particle | Symbol | Charge | Mass (u) | Mass (kg) | Location |",
"| Proton | p | +1 | 1.0073 | 1.67 × 10^-27 | Nucleus |",
"| Neutron | n | 0 | 1.0087 | 1.67 × 10^-27 | Nucleus |",
"| Electron | e | −1 | 1/1836 | 9.11 × 10^-31 | Around nucleus |","",
"1. 's' subshell — highest shielding effect",
"2. 'f' subshell — poorest shielding effect","",
"Tip: Order yaad rakho — ==s > p > d > f==","",
"## Practice Questions","",
"Q1. Which quantum number describes the orientation of an orbital? (a) Principal (b) Azimuthal (c) Magnetic (d) Spin",
"Ans: c","",
"Q2. Calculate the de Broglie wavelength of an electron (m = 9.1 x 10^-31 kg) moving at 10^6 m/s. (h = 6.63 x 10^-34 Js)",
"Solution: lambda = h / (m v)",
"= 6.63 x 10^-34 / (9.1 x 10^-31 x 10^6)",
"= 7.28 x 10^-10 m = 7.28 Å",
"Ans: 7.28 Å","",
"$ 2H_2 + O_2 ->[spark] 2H_2O",
"$ CaCO3 ->[heat] CaO + CO2",
"$ N2 + 3H2 <=> 2NH3","",
"Ionic compounds like Na^+ Cl^- aur Ca^2+ SO4^2- water me dissolve hote hain; H2SO4 strong acid hai.","",
"## Isotopes of Hydrogen","",
"| Isotope | Notation | Protons | Neutrons |",
"| Protium | ^1_1H | 1 | 0 |",
"| Deuterium | ^2_1H | 1 | 1 |",
"| Tritium | ^3_1H | 1 | 2 |","",
"- ==Isotopes== ka atomic number same, mass number alag — jaise ^235_92U aur ^238_92U, ya ^14_6C (carbon dating).",
"- Highlights: ==yellow== %%blue%% ^^green^^ !!pink!! ::orange:: @@maroon text@@","",
"~ Quantum world — jahan certainty khud uncertain hai"
  ].join("\n");

  /* =====================================================================
     INLINE — markers + math (LaTeX-lite)
  ===================================================================== */
  var GREEK = { lambda:"λ", Lambda:"Λ", delta:"δ", Delta:"Δ", psi:"ψ", Psi:"Ψ", pi:"π", Pi:"Π", theta:"θ", Theta:"Θ", vartheta:"ϑ", mu:"μ", nu:"ν", sigma:"σ", Sigma:"Σ", varsigma:"ς",
                omega:"ω", Omega:"Ω", alpha:"α", beta:"β", gamma:"γ", Gamma:"Γ", epsilon:"ε", varepsilon:"ε", rho:"ρ", varrho:"ϱ", tau:"τ", phi:"φ", varphi:"φ", Phi:"Φ", chi:"χ", eta:"η", kappa:"κ",
                zeta:"ζ", iota:"ι", xi:"ξ", Xi:"Ξ", upsilon:"υ", infty:"∞", hbar:"ħ" };
  var TEX = { times:"×", cdot:"·", pm:"±", mp:"∓", ge:"≥", geq:"≥", le:"≤", leq:"≤", ne:"≠", neq:"≠", approx:"≈", propto:"∝", rightarrow:"→", to:"→", leftarrow:"←", Rightarrow:"⇒", leftrightarrow:"↔", degree:"°", circ:"°", div:"÷", ldots:"…", cdots:"⋯", partial:"∂", nabla:"∇", hbar:"ħ", sum:"Σ", int:"∫", angstrom:"Å", AA:"Å",
              therefore:"∴", because:"∵", implies:"⇒", iff:"⇔", Leftrightarrow:"⇔", longrightarrow:"⟶", mapsto:"↦", uparrow:"↑", downarrow:"↓", equiv:"≡", sim:"∼", simeq:"≃", cong:"≅",
              perp:"⊥", parallel:"∥", angle:"∠", ll:"≪", gg:"≫", in:"∈", notin:"∉", subset:"⊂", cup:"∪", cap:"∩", forall:"∀", exists:"∃", prod:"∏", oint:"∮", ell:"ℓ", prime:"′", dots:"…", vdots:"⋮", ddots:"⋱",
              bullet:"•", ast:"∗", star:"⋆", ohm:"Ω", deg:"°", percent:"%", mid:"|", lvert:"|", rvert:"|", lVert:"‖", rVert:"‖", langle:"⟨", rangle:"⟩", lfloor:"⌊", rfloor:"⌋", lceil:"⌈", rceil:"⌉", emptyset:"∅", neg:"¬", lnot:"¬", surd:"√" };
  var FUNCS = "sin|cos|tan|cot|sec|csc|cosec|sinh|cosh|tanh|coth|arcsin|arccos|arctan|log|ln|lg|exp|lim|max|min|sup|inf|det|dim|deg|mod|gcd|arg|ker|Re|Im";
  var ACCENTS = { vec:"arr", overrightarrow:"warr", hat:"^", bar:"bar", overline:"bar", dot:"˙", ddot:"¨", tilde:"~", breve:"˘", check:"ˇ" };

  /** ^1_1H  _1^1H  ^{235}_{92}U  {}^{14}_{6}C  →  stacked pre-scripts before the symbol */
  function nuclide(s) {
    return s.replace(/(?<=^|[\s(\[,;:>])(?:\{\})?(?:\^\{?([^\s{}^_<>]+)\}?\s*_\{?([^\s{}^_<>]+)\}?|_\{?([^\s{}^_<>]+)\}?\s*\^\{?([^\s{}^_<>]+)\}?)\s*([A-Z][a-z]?)(?![a-z])/g,
      function (m, a1, z1, z2, a2, el) {
        var A = a1 || a2, Z = z1 || z2;
        return '<span class="nuc"><span class="ms"><span>' + A + "</span><span>" + Z + '</span></span><span class="el">' + el + "</span></span>";
      });
  }
  function texify(s) {
    // s is already HTML-escaped
    s = nuclide(s);
    s = s.replace(/\\left\s*|\\right\s*/g, "").replace(/\\,|\\;|\\ /g, " ").replace(/\\%/g, "%");
    s = s.replace(/\\(?:text|mathrm|textrm|operatorname)\{([^{}]*)\}/g, "$1").replace(/\\(?:mathbf|textbf|boldsymbol|bm)\{([^{}]*)\}/g, "<b>$1</b>");
    s = s.replace(/\\(?:dfrac|tfrac)\{/g, "\\frac{").replace(/\\qquad/g, " &emsp;&emsp; ").replace(/\\quad/g, " &emsp; ").replace(/\^\\circ|\^\{\\circ\}|\\degree/g, "°").replace(/\\\{/g, "&#123;").replace(/\\\}/g, "&#125;").replace(/\\\|/g, "‖");
    // accents: \vec{F} \hat{x} \bar{v} \overline{AB} \dot{x} \ddot{x} \tilde{a}  (also \vec F without braces)
    for (var a = 0; a < 3; a++) s = s.replace(/\\(vec|overrightarrow|hat|bar|overline|dot|ddot|tilde|breve|check)\s*(?:\{([^{}]*)\}|([A-Za-z0-9]|\\[A-Za-z]+))/g, function (m, w, g1, g2) {
      var t = ACCENTS[w], body = g1 != null ? g1 : g2;
      if (t === "arr" || t === "warr") return '<span class="mac ' + t + '">' + body + "</span>";
      if (t === "bar") return '<span class="mac bar">' + body + "</span>";
      return '<span class="mac" data-a="' + t + '">' + body + "</span>";
    });
    for (var k = 0; k < 4; k++) s = s.replace(/\\frac\{([^{}]*)\}\{([^{}]*)\}/g, '<span class="frac"><span>$1</span><span>$2</span></span>');
    s = s.replace(/\\sqrt\[([^\]]+)\]\{([^{}]*)\}/g, '<sup>$1</sup>√<span class="rad">$2</span>');
    // function names: \cos\theta -> cos θ,  \sin^2\theta -> sin²θ,  \log_{10} x
    s = s.replace(new RegExp("\\\\(" + FUNCS + ")(?![A-Za-z])((?:\\^|_)(?:\\{[^{}]*\\}|\\S))?\\s*", "g"), function (m, f, sc) { return '<span class="fn">' + f + "</span>" + (sc || "") + "&thinsp;"; });
    s = s.replace(/\\sqrt\{([^{}]*)\}/g, '√<span class="rad">$1</span>').replace(/\\sqrt\s*([A-Za-z0-9]+)/g, '√<span class="rad">$1</span>');
    s = s.replace(/\\([A-Za-z]+)/g, function (m, w) { return TEX.hasOwnProperty(w) ? TEX[w] : (GREEK.hasOwnProperty(w) ? GREEK[w] : m); });
    s = s.replace(/\^\{([^{}]*)\}/g, "<sup>$1</sup>").replace(/_\{([^{}]*)\}/g, "<sub>$1</sub>");
    s = s.replace(/\^\(([^)]+)\)/g, "<sup>$1</sup>").replace(/\^(-?[0-9A-Za-z]+)/g, "<sup>$1</sup>");
    // H_2O -> digits only; v_max -> the word. Never swallow the next element (H_2O ≠ H₂ₒ).
    // The char before "_" may be a Greek letter (ε_0), so anything except whitespace/markup.
    s = s.replace(/([^\s_^{}<>;&])_(\d+|[A-Za-z]+)/g, "$1<sub>$2</sub>");
    s = s.replace(/\{|\}/g, "");
    return s;
  }
  function symbolize(s) {
    return s.replace(/&lt;=&gt;/g, "⇌").replace(/&lt;-&gt;/g, "↔").replace(/=&gt;/g, "⇒").replace(/&gt;=/g, "≥").replace(/&lt;=/g, "≤").replace(/!=/g, "≠")
            .replace(/-&gt;\[([^\]]+)\]/g, '<span class="arr"><small>$1</small>→</span>')        // ->[heat]
            .replace(/-&gt;/g, "→").replace(/&lt;-/g, "←").replace(/\+\/-/g, "±")
            .replace(/\b([A-Za-z]+)\b/g, function (w) { return GREEK.hasOwnProperty(w) ? GREEK[w] : w; });
  }
  /** chemistry: H2SO4 -> H₂SO₄, Ca(OH)2 -> Ca(OH)₂, Na^+ / SO4^2- / Fe^{3+} -> charges as superscript */
  function chemify(s) {
    if (!$("optChem").checked) return s;
    s = s.replace(/\^\{([^{}]*)\}/g, "<sup>$1</sup>").replace(/_\{([^{}]*)\}/g, "<sub>$1</sub>");
    s = s.replace(/\^(\d*[+\-−]{1,2})(?![\w])/g, "<sup>$1</sup>");                                  // charges
    // element symbol (anywhere in a formula: H2SO4, CaCO3, 3H2) or ")" followed by digits -> subscript
    s = s.replace(/([A-Z][a-z]?|\))(\d{1,3})(?![\d.:%])/g, function (m, el, d, off, str) {
      var before = str.slice(Math.max(0, off - 1), off);
      if (before === "^" || before === "_" || before === "$" || before === "<" || before === "/") return m;   // not inside tags / math
      return el + "<sub>" + d + "</sub>";
    });
    return s;
  }
  function mathify(s) {
    if (!$("optMath").checked) return s;
    s = symbolize(texify(s));
    s = chemify(s);
    s = s.replace(/(^|[\s\d\)\w>])\s+x\s+(?=[\s\(\w\d<])/g, "$1 × ").replace(/\s\.\s/g, " · ").replace(/(?<![*])\*(?![*])/g, "×");
    return s;
  }
  function smartText(s) {
    if (!$("optMath").checked) return s;
    if (/\\[A-Za-z]/.test(s)) s = texify(s); else s = nuclide(s);
    s = symbolize(s);
    s = s.replace(/\^\(([^)]+)\)/g, "<sup>$1</sup>").replace(/\^(-?[0-9]+)(?![+\-−])/g, "<sup>$1</sup>").replace(/([^\s_^{}<>;&])_(\d+)/g, "$1<sub>$2</sub>");
    s = chemify(s);
    return s;
  }
  /* content may contain single "=", "_", "!" etc. — only the doubled delimiter ends the run.
     ==  %%  ^^  !!  ::  → highlight slots 1–5 (colours from the palette);  @@ → maroon text */
  function markers(s) {
    return s.replace(/\*\*(\S(?:(?:(?!\*\*)[\s\S])*?\S)?)\*\*/g, "<b>$1</b>")
            .replace(/(^|[^*])\*(\S(?:[^*]*?\S)?)\*(?!\*)/g, "$1<i>$2</i>")
            .replace(/==(\S(?:(?:(?!==)[\s\S])*?\S)?)==/g, '<mark class="hl1">$1</mark>')
            .replace(/%%(\S(?:(?:(?!%%)[\s\S])*?\S)?)%%/g, '<mark class="hl2">$1</mark>')
            .replace(/\^\^(\S(?:(?:(?!\^\^)[\s\S])*?\S)?)\^\^/g, '<mark class="hl3">$1</mark>')
            .replace(/!!(\S(?:(?:(?!!!)[\s\S])*?\S)?)!!/g, '<mark class="hl4">$1</mark>')
            .replace(/::(\S(?:(?:(?!::)[\s\S])*?\S)?)::/g, '<mark class="hl5">$1</mark>')
            .replace(/@@(\S(?:(?:(?!@@)[\s\S])*?\S)?)@@/g, '<span class="em">$1</span>')
            .replace(/__(\S(?:(?:(?!__)[\s\S])*?\S)?)__/g, "<u>$1</u>");
  }
  function inline(t, inFormula) {
    var s = esc(t), maths = [];
    if (!inFormula) s = s.replace(/\$([^$\n]+?)\$/g, function (_, m) { maths.push('<span class="mi">' + mathify(m) + "</span>"); return "\u0001" + (maths.length - 1) + "\u0001"; });
    s = inFormula ? mathify(s) : smartText(s);
    s = markers(s);
    s = s.replace(/\u0001(\d+)\u0001/g, function (_, i) { return maths[+i]; });
    return s;
  }
  /** a run of lines -> one block, every Enter kept, leading spaces -> indent */
  function linesHtml(arr, asMath) {
    return arr.map(function (l) {
      var ind = (l.match(/^\s*/)[0].length / 2) | 0;
      return '<span class="ln' + (ind ? " i" + Math.min(2, ind) : "") + '">' + inline(l.trim(), !!asMath) + "</span>";
    }).join("");
  }

  /* =====================================================================
     PARSER
  ===================================================================== */
  var LABELS = /^(Note|Notes|Key Idea|Key Point|Key Points|Important|Interesting Fact|Fact|Definition|Statement|Why\??|Remember|Tip|Example|Examples|Context[^:]{0,40}|Short form|Formula|Result|Conclusion|Order|Key Relations?|Reason|Meaning|Significance|Application|Applications|Assumptions?|Limitations?|Advantages?|Disadvantages?|Postulates?|Observation|Explanation|Concept|Trick|Shortcut|Caution|Warning|Law)\s*[:：]\s*(.*)$/i;
  var HEADING_WORD = /^(LECTURE|CHAPTER|UNIT|TOPIC|SECTION|PART|MODULE)\b/i;
  var BLOCK_START = /^(#{1,3}\s|>\s?|\$\s|\$\$|[-•*·▪→]\s|\d{1,2}[\.\)]\s|\||~\s|\[(Banner|Caption)\]|\[(?:img|image)\s*:|-{3,}|={3,})/;
  /** [img: name | 60% | center | caption text]  → parts in any order after the name */
  function parseImgLine(t) {
    var m = t.match(/^\[(?:img|image)\s*:\s*([^\]|]+?)\s*(?:\|([^\]]*))?\]$/i);
    if (!m) return null;
    var b = { type: "img", name: m[1].trim(), w: "", align: "center", cap: "" };
    (m[2] || "").split("|").forEach(function (p) {
      p = p.trim(); if (!p) return;
      if (/^\d{1,3}\s*%$/.test(p)) b.w = p.replace(/\s/g, "");
      else if (/^(left|right|center|centre)$/i.test(p)) b.align = p.toLowerCase().replace("centre", "center");
      else if (/^wide$/i.test(p)) b.wide = true;
      else b.cap = b.cap ? b.cap + " | " + p : p;
    });
    return b;
  }

  function isSep(t) { return /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(t); }
  function tableCells(t) {
    if (t.indexOf("\t") >= 0) return t.split("\t").map(function (c) { return c.trim(); });
    if (/\|/.test(t) && t.split("|").length >= 3) { var s = t.trim().replace(/^\|/, "").replace(/\|$/, ""); return s.split("|").map(function (c) { return c.trim(); }); }
    return null;
  }
  /* short line, no sentence punctuation, no math, no mid-line ", " / ": " (those read as prose) */
  function headingLike(t) { if (t.length > 72 || /[.!]$/.test(t) || t.split(/\s+/).length > 11 || /[=$\\]/.test(t) || /,\s|:\s\S/.test(t)) return false; return t.replace(/[^A-Za-z]/g, "").length >= 3; }
  function isCapsLine(t) { var u = t.replace(/[^A-Za-z]/g, ""); return u.length >= 4 && u === u.toUpperCase() && t.length <= 72; }
  function formulaLike(t) { if (t.length > 80 || !/(=|>=|<=|≥|≤|∝|→|\\frac)/.test(t)) return false; var long = t.replace(/\\[a-z]+/g, "").replace(/[^A-Za-z' ]/g, " ").split(/\s+/).filter(function (w) { return w.length > 6; }); return long.length <= 1; }

  function parse(text) {
    var auto = $("optAuto").checked, autoBul = $("optBullets").checked;
    var lines = String(text || "").replace(/\r\n/g, "\n").split("\n");
    var blocks = [], i = 0, n = lines.length, para = null, list = null, wideNext = false;
    function flushPara() { if (para) { blocks.push({ type: "p", html: linesHtml(para.lines), line: para.line, count: para.lines.length }); para = null; } }
    function flushList() { if (list) { blocks.push(list); list = null; } }
    function flushAll() { flushPara(); flushList(); }
    function push(b) { flushAll(); blocks.push(b); }

    while (i < n) {
      var raw = lines[i], t = raw.replace(/```/g, "").trim();
      if (!t) { flushAll(); i++; continue; }
      var m;

      if (/^-{3,}\s*page\s*-{3,}$|^={3,}$/i.test(t)) { push({ type: "break", kind: "page", line: i }); i++; continue; }
      if (/^-{3,}\s*col\s*-{3,}$|^-{3,}$/i.test(t)) { push({ type: "break", kind: "col", line: i }); i++; continue; }

      if ((m = t.match(/^(Title|Chapter|Subtitle|Tagline|Badge)\s*:\s*(.+)$/i)) && blocks.length === 0 && !para && !list) {
        var map = { title: "titleInput", chapter: "chapInput", subtitle: "subInput", tagline: "tagInput", badge: "badgeInput" };
        $(map[m[1].toLowerCase()]).value = m[2].trim(); i++; continue;
      }

      if ((m = t.match(/^(#{1,3})\s+(.+)$/))) { push({ type: "h" + m[1].length, text: m[2].trim(), line: i, count: 1 }); i++; continue; }

      // ---- question: "Q1. text" / "Q. text" / "Question 3: text", then options, Ans:, Solution: (until blank line)
      if ((m = t.match(/^(?:Q|Que|Ques|Question)\.?\s*(\d*)\s*[\.\):\-–]?\s+(.+)$/i))) {
        flushAll();
        var q = { type: "q", num: m[1], text: m[2].trim(), opts: [], ans: "", sol: [], line: i, count: 1 };
        var startQ = i; i++;
        var inSol = false;
        while (i < n) {
          var qt = lines[i].replace(/```/g, ""), qs = qt.trim(), qm;
          if (!qs) break;
          if (/^(?:Q|Que|Ques|Question)\.?\s*\d*\s*[\.\):\-–]?\s+/i.test(qs) || /^(#{1,3}\s|>\s?|\||~\s|\[(Banner|Caption)\]|\[(?:img|image)\s*:|-{3,}|={3,})/.test(qs)) break;
          if ((qm = qs.match(/^(?:Ans|Answer|Key)\s*[:\-–]?\s*(.+)$/i))) { q.ans = qm[1].trim(); i++; continue; }
          if (!inSol && (qm = qs.match(/^(Solution|Soln|Sol|Explanation|Hint)\s*[:\-–]?\s*(.*)$/i))) { inSol = true; q.solLabel = /^hint/i.test(qm[1]) ? "Hint" : (/^expl/i.test(qm[1]) ? "Explanation" : "Solution"); if (qm[2]) q.sol.push(qm[2]); i++; continue; }
          if (!inSol && (qm = qs.match(/^\(?([A-Da-d1-4])[\)\.]\s+(.+)$/))) { q.opts.push({ l: qm[1].toUpperCase(), t: qm[2] }); i++; continue; }
          if (inSol) { q.sol.push(qt); i++; continue; }
          if (!q.opts.length) { q.text += " " + qs; i++; continue; }        // wrapped question text
          q.opts[q.opts.length - 1].t += " " + qs; i++;
        }
        // inline options inside the question line: "… (a) x (b) y (c) z (d) w"
        if (!q.opts.length) {
          var re = /(?:^|\s)\(?([A-Da-d1-4])\)\s*/g, found = [], fm;
          while ((fm = re.exec(q.text))) found.push({ l: fm[1].toUpperCase(), at: fm.index, end: fm.index + fm[0].length });
          var st = -1; for (var f = 0; f < found.length; f++) if (/^(A|1)$/.test(found[f].l)) { st = f; break; }
          if (st >= 0 && found.length - st >= 2) {
            var run = found.slice(st), qtext = q.text.slice(0, run[0].at).trim();
            q.opts = run.map(function (r, k) { return { l: r.l, t: q.text.slice(r.end, k + 1 < run.length ? run[k + 1].at : q.text.length).trim() }; });
            q.text = qtext;
          }
        }
        q.count = i - startQ;
        blocks.push(q);
        continue;
      }
      var ib = parseImgLine(t);
      if (ib) { ib.line = i; ib.count = 1; if (wideNext) { ib.wide = true; wideNext = false; } push(ib); i++; continue; }
      if ((m = t.match(/^\[(Banner|Caption)\]\s*(.+)$/i))) { push({ type: m[1].toLowerCase() === "banner" ? "banner" : "cap", html: inline(m[2]), line: i, count: 1 }); i++; continue; }
      if (/^\[wide\]$/i.test(t)) { flushAll(); wideNext = true; i++; continue; }      // next table spans both columns
      if ((m = t.match(/^~\s+(.+)$/))) { push({ type: "qt", html: inline(m[1]), line: i, count: 1 }); i++; continue; }

      // ---- box: "> text" plus every following line until a blank / new block
      if ((m = t.match(/^>\s?(.*)$/))) {
        flushAll();
        var bl = [m[1]], start = i; i++;
        while (i < n) {
          var nt = lines[i].replace(/```/g, "");
          if (!nt.trim()) break;
          if (/^>\s?/.test(nt.trim())) { bl.push(nt.trim().replace(/^>\s?/, "")); i++; continue; }
          if (BLOCK_START.test(nt.trim()) && !/^[-•*]\s/.test(nt.trim())) break;
          bl.push(nt); i++;
        }
        var first = bl[0], lm = first.match(LABELS), label = lm ? lm[1] : "";
        if (lm) bl[0] = lm[2];
        blocks.push({ type: "callout", label: label, html: linesHtml(bl), line: start, count: i - start });
        continue;
      }
      if (/^\$/.test(t) && (m = t.match(/^\$\$?\s*(.+?)\s*\$?\$?$/))) {
        var fl = m[1].match(/^([^=\\]{1,30}?)\s*:\s*(.+)$/);
        push({ type: "formula", label: fl ? fl[1] : "", html: inline(fl ? fl[2] : m[1], true), line: i, count: 1 }); i++; continue;
      }

      var cells = tableCells(t);
      if (cells && cells.length >= 2) {
        flushAll();
        var rows = [], startLine = i;
        while (i < n) { var rt = lines[i].trim(); if (!rt) break; if (isSep(rt)) { i++; continue; } var rc = tableCells(rt); if (!rc || rc.length < 2) break; rows.push(rc); i++; }
        if (rows.length >= 2) { blocks.push({ type: "table", head: rows[0], rows: rows.slice(1), line: startLine, count: i - startLine, wide: wideNext }); wideNext = false; continue; }
        i = startLine; para = { lines: [t], line: i }; flushPara(); i++; continue;
      }

      var indented = /^\s{2,}/.test(raw);
      if ((m = t.match(/^(?:[-•*·▪]|→)\s+(.+)$/))) {
        flushPara();
        if (!list || list.type !== "ul") { flushList(); list = { type: "ul", items: [], line: i, count: 0 }; }
        if (indented && list.items.length) { var last = list.items[list.items.length - 1]; (last.sub = last.sub || []).push(inline(m[1])); }
        else list.items.push({ html: inline(m[1]), line: i });
        list.count = i - list.line + 1; i++; continue;
      }
      if ((m = t.match(/^(\d{1,2})[\.\)]\s+(.+)$/))) {
        flushPara();
        if (!list || list.type !== "ol") { flushList(); list = { type: "ol", items: [], line: i, count: 0 }; }
        list.items.push({ html: inline(m[2]), line: i }); list.count = i - list.line + 1; i++; continue;
      }

      if (auto && (m = t.match(LABELS))) {
        var lab = m[1].replace(/\s+$/, ""), rest = m[2].trim();
        if (!rest) { push({ type: "h3", text: lab, line: i, count: 1 }); i++; continue; }
        if (/^(Formula|Short form|Result)$/i.test(lab) && formulaLike(rest)) { push({ type: "formula", label: lab, html: inline(rest, true), line: i, count: 1 }); i++; continue; }
        push({ type: "callout", label: lab, html: inline(rest), line: i, count: 1 }); i++; continue;
      }
      if (auto && (m = t.match(/^([A-Z][A-Za-z' ]{1,24}?)\s*:\s+(\S.*)$/)) && m[1].split(/\s+/).length <= 3) {
        if (formulaLike(m[2])) push({ type: "formula", label: m[1], html: inline(m[2], true), line: i, count: 1 });
        else push({ type: "callout", label: m[1], html: inline(m[2]), line: i, count: 1 });
        i++; continue;
      }

      if (auto) {
        var prevBlank = i === 0 || !lines[i - 1].trim(), nextBlank = i + 1 >= n || !lines[i + 1].trim();
        if (/^[^:]{2,40}:$/.test(t) && t.split(/\s+/).length <= 6) { push({ type: "h3", text: t.replace(/:$/, ""), line: i, count: 1 }); i++; continue; }
        if (headingLike(t) && ((prevBlank && nextBlank) || isCapsLine(t) || HEADING_WORD.test(t))) { push({ type: "h1", text: t, line: i, count: 1 }); i++; continue; }
        if (prevBlank && nextBlank && formulaLike(t)) { push({ type: "formula", label: "", html: inline(t, true), line: i, count: 1 }); i++; continue; }
        if (autoBul) {
          var j = i, run = [];
          while (j < n) { var rj = lines[j].replace(/```/g, "").trim(); if (!rj || rj.length > 180 || BLOCK_START.test(rj) || LABELS.test(rj) || tableCells(rj) || isCapsLine(rj)) break; run.push({ t: rj, line: j }); j++; }
          if (run.length >= 2) { push({ type: "ul", items: run.map(function (r) { return { html: inline(r.t), line: r.line }; }), line: i, count: run.length }); i = j; continue; }
        }
      }

      flushList();
      if (para) para.lines.push(raw.replace(/```/g, "")); else para = { lines: [raw.replace(/```/g, "")], line: i };
      i++;
    }
    flushAll();
    return blocks;
  }

  /* =====================================================================
     BLOCKS -> DOM
  ===================================================================== */
  function el(cls, html) { var d = document.createElement("div"); d.className = cls; if (html != null) d.innerHTML = html; return d; }
  /** put a "Label:" inside the first line so the text continues on the same line */
  function withLabel(lblHtml, html) {
    if (!lblHtml) return html;
    return /^<span class="ln[^"]*">/.test(html) ? html.replace(/^(<span class="ln[^"]*">)/, "$1" + lblHtml) : lblHtml + html;
  }
  function buildNodes(blocks) {
    var chap = ($("chapInput").value || "1").trim(), s1 = 0, qn = 0, out = [];
    blocks.forEach(function (b) {
      var node = null;
      switch (b.type) {
        case "h1": s1++; b.num = chap + "." + s1; node = el("blk h1", '<span class="num">' + esc(b.num) + '</span><span class="tt">' + inline(b.text) + "</span>"); break;
        case "h2": b.num = ""; node = el("blk h2", '<span class="dia">❖</span><span>' + inline(b.text) + "</span>"); break;
        case "q":
          qn++; var label = "Q" + (b.num || qn);
          var isNum = !b.opts.length && (b.sol.length || /calculate|find|determine|compute|how much|how many|kitna|nikalo|value of/i.test(b.text));
          var html = '<div class="q-head"><span class="q-badge">' + esc(label) + '</span><div class="q-text">' + inline(b.text) + "</div></div>";
          if (b.opts.length) {
            var longOpt = b.opts.some(function (o) { return o.t.length > 28; });
            html += '<div class="q-opts' + (longOpt ? " one" : "") + '">' + b.opts.map(function (o) { return '<span><span class="l">(' + esc(o.l.toLowerCase()) + ")</span><span>" + inline(o.t) + "</span></span>"; }).join("") + "</div>";
          }
          if (b.sol.length) html += '<div class="q-sol">' + withLabel('<span class="lbl">' + esc(b.solLabel || "Solution") + ":</span> ", linesHtml(b.sol, true)) + "</div>";
          if (b.ans) html += '<div class="q-ans"><i class="fa fa-circle-check"></i> Ans: ' + inline(b.ans) + "</div>";
          node = el("blk qb" + (isNum ? " numerical" : ""), html);
          break;
        case "h3": node = el("blk h3", inline(b.text)); break;
        case "p": node = el("blk p", b.html); break;
        case "ul": node = el("blk"); var ul = document.createElement("ul"); ul.className = "ul";
          b.items.forEach(function (it) { var li = document.createElement("li"); li.innerHTML = it.html; if (it.line != null) li.dataset.line = it.line;
            if (it.sub && it.sub.length) { var s = document.createElement("ul"); s.className = "ul"; it.sub.forEach(function (x) { var l2 = document.createElement("li"); l2.innerHTML = x; s.appendChild(l2); }); li.appendChild(s); }
            ul.appendChild(li); });
          node.appendChild(ul); break;
        case "ol": node = el("blk"); var ol = document.createElement("ol"); ol.className = "ol";
          b.items.forEach(function (it) { var li = document.createElement("li"); li.innerHTML = it.html; if (it.line != null) li.dataset.line = it.line; ol.appendChild(li); }); node.appendChild(ol); break;
        case "callout":
          var lab = b.label || "", lblHtml = lab ? '<span class="lbl">' + esc(lab) + (/[?]$/.test(lab) ? "" : ":") + "</span> " : "";
          var bodyHtml = withLabel(lblHtml, b.html);
          if (/^(Tip|Example|Examples|Remember|Trick|Shortcut|Interesting Fact|Fact)$/i.test(lab)) node = el("blk callout tip", '<span class="bulb"><i class="fa fa-lightbulb"></i></span><div class="body-t">' + bodyHtml + "</div>");
          else if (/^(Definition|Statement|Meaning|Postulates?|Law)$/i.test(lab)) node = el("blk callout def", bodyHtml);
          else node = el("blk callout", bodyHtml);
          break;
        case "formula": node = el("blk formula" + (b.label ? "" : " center"), (b.label ? '<span class="lbl">' + esc(b.label) + ":</span>" : "") + '<span class="fx">' + b.html + "</span>"); break;
        case "table":
          node = el("blk"); var tb = document.createElement("table"); tb.className = "tb";
          if (b.wide) node.dataset.wide = "1";                     // only when the user wrote [Wide] above it
          var cols = b.head.length;
          tb.innerHTML = "<thead><tr>" + b.head.map(function (h) { return "<th>" + inline(h) + "</th>"; }).join("") + "</tr></thead><tbody>" +
            b.rows.map(function (r) { var c = r.slice(0, cols); while (c.length < cols) c.push(""); return "<tr>" + c.map(function (x) { return "<td>" + inline(x) + "</td>"; }).join("") + "</tr>"; }).join("") + "</tbody>";
          node.appendChild(tb); break;
        case "banner": node = el("blk banner", b.html); node.style.marginBottom = "0"; break;
        case "cap": node = el("blk cap", b.html); break;
        case "img":
          var im = findImage(b.name);
          node = el("blk img al-" + b.align);
          if (b.wide) node.dataset.wide = "1";
          if (im) {
            var fg = document.createElement("figure"); fg.className = "fig"; fg.style.width = b.w || (b.wide ? "70%" : "100%");
            var ig = document.createElement("img"); ig.src = im.data; ig.width = im.w; ig.height = im.h; ig.alt = b.name; fg.appendChild(ig);
            if (b.cap) { var fc = document.createElement("figcaption"); fc.innerHTML = inline(b.cap); fg.appendChild(fc); }
            node.appendChild(fg);
          } else node.innerHTML = '<div class="fig missing" style="width:' + (b.w || "100%") + '"><i class="fa fa-image"></i> Image <b>' + esc(b.name) + '</b> nahi mili — Images panel se add karo</div>';
          break;
        case "qt": node = el("blk qt", b.html); break;
        case "break": node = el("brk"); node.dataset.kind = b.kind; break;
      }
      if (node) { node.dataset.line = b.line != null ? b.line : ""; node.dataset.count = b.count || 1; node.dataset.type = b.type; out.push(node); }
    });
    return out;
  }

  /* =====================================================================
     PAGES
  ===================================================================== */
  var ATOM = '<svg class="hdr-icon" viewBox="0 0 64 64" fill="none" stroke="#fff" stroke-width="2.6"><circle cx="32" cy="32" r="4.2" fill="#fff" stroke="none"/><ellipse cx="32" cy="32" rx="26" ry="10"/><ellipse cx="32" cy="32" rx="26" ry="10" transform="rotate(60 32 32)"/><ellipse cx="32" cy="32" rx="26" ry="10" transform="rotate(120 32 32)"/></svg>';

  function headerNode(slim) {
    var badge = ($("badgeInput").value || "").replace(/\s*\/\s*/g, "\n");
    var d = el("hdr" + (slim ? " slim" : ""));
    d.innerHTML = '<div class="chap"><small>Chapter</small><b>' + esc($("chapInput").value || "1") + "</b></div>" +
      '<div class="hdr-tt"><div class="hdr-title">' + esc($("titleInput").value || "Notes") + "</div>" + ($("subInput").value ? '<div class="hdr-sub">' + esc($("subInput").value) + "</div>" : "") + "</div>" +
      '<div class="hdr-r"><div class="top">' + ATOM + (badge ? '<div class="hand-badge">' + esc(badge) + "</div>" : "") + "</div>" +
      ($("tagInput").value ? '<div class="hdr-tag">' + esc($("tagInput").value.replace(/\s*\|\s*/g, "  |  ")) + "</div>" : "") + "</div>";
    return d;
  }
  function footNode(no) {
    var d = el("foot");
    var left = $("optBrand").checked
      ? '<span class="brand"><a class="dw" href="' + SITE_URL + '" target="_blank" rel="noopener">Diploma Wallah</a> · <i class="fab fa-whatsapp"></i> D2D batch: <a href="' + WA_LINK + '" target="_blank" rel="noopener">' + WA_NUMBER + '</a></span>'
      : '<span class="title-mini">' + esc($("titleInput").value || "") + "</span>";
    d.innerHTML = left + '<span class="pno">Page <span class="dot">' + no + '</span><span class="of">/ ' + no + "</span></span>";
    return d;
  }
  /* A page body is a vertical stack of "bands": two-column bands for normal
     flow, and full-width blocks (wide tables) that span both columns. */
  function newBand(page) {
    if (page.band) page.band.style.flex = "0 0 auto";           // freeze the finished band at its content height
    var band = el("cols" + ($("optTwoCol").checked ? "" : " single"));
    var c1 = el("col"), c2 = el("col"); band.appendChild(c1); band.appendChild(c2);
    page.body.appendChild(band);
    page.band = band; page.cols = $("optTwoCol").checked ? [c1, c2] : [c1]; page.ci = 0;
  }
  function newPage(no, slim) {
    var p = document.createElement("section"); p.className = "page";
    var wm = document.createElement("img"); wm.className = "wm"; wm.src = "diplomawallah-logo.png"; wm.alt = ""; p.appendChild(wm);
    p.appendChild(headerNode(slim));
    var body = el("body"); p.appendChild(body); p.appendChild(footNode(no)); stage.appendChild(p); fitTitle(p.querySelector(".hdr"));
    var page = { el: p, body: body, band: null, cols: [], ci: 0 };
    newBand(page);
    return page;
  }
  function fitTitle(hdr) {
    var t = hdr.querySelector(".hdr-title"), slim = hdr.classList.contains("slim"), size = slim ? 14 : 25, min = slim ? 10 : 15;
    t.style.whiteSpace = "nowrap"; t.style.fontSize = size + "pt";
    while (size > min && t.scrollWidth > t.clientWidth + 1) { size -= 0.5; t.style.fontSize = size + "pt"; }
    if (t.scrollWidth > t.clientWidth + 1) t.style.whiteSpace = "normal";
  }
  function fits(col) { return col.scrollHeight <= col.clientHeight + 0.5; }

  function bodyFits(page) { return page.body.scrollHeight <= page.body.clientHeight + 0.5; }

  /* ---- fit a table into one column ----
     Measures the table's min-content width (= no word broken) at decreasing
     font/padding steps and applies the first step that fits. If even the
     smallest step is too wide, words are allowed to break as a last resort. */
  var TSTEPS = [
    [0, 1.4, 2.2], [0.5, 1.3, 2.0], [1.0, 1.2, 1.8], [1.5, 1.1, 1.5], [2.0, 1.0, 1.3],
    [2.5, 0.9, 1.1], [3.0, 0.8, 1.0], [3.5, 0.7, 0.9], [4.0, 0.6, 0.8], [4.5, 0.5, 0.7]
  ];
  function fitTable(tb, host, colW) {
    if (!tb || !colW) return;
    var base = (parseFloat($("optFont").value) || 10.5) - 0.5;
    var probe = tb.cloneNode(true);
    probe.className = "tb";
    probe.style.cssText = "position:absolute;left:-99999px;top:0;visibility:hidden;table-layout:auto;width:min-content;max-width:none;";
    host.appendChild(probe);
    var chosen = TSTEPS[TSTEPS.length - 1], ok = false;
    for (var k = 0; k < TSTEPS.length; k++) {
      var st = TSTEPS[k];
      probe.style.setProperty("--tf", (base - st[0]) + "pt");
      probe.style.setProperty("--tpy", st[1] + "mm"); probe.style.setProperty("--tpx", st[2] + "mm");
      if (probe.offsetWidth <= colW) { chosen = st; ok = true; break; }
    }
    host.removeChild(probe);
    tb.style.setProperty("--tf", (base - chosen[0]) + "pt");
    tb.style.setProperty("--tpy", chosen[1] + "mm"); tb.style.setProperty("--tpx", chosen[2] + "mm");
    tb.classList.toggle("force", !ok);
  }

  /* ---- splitting a block that doesn't fit the rest of a column ----
     tables split by rows (header repeated), lists by items, paragraphs and
     boxes by lines. The remainder becomes a new node placed next. */
  function splitUnits(nd) {
    var t = nd.dataset.type;
    if (t === "table") { var tb = nd.querySelector("tbody"); return tb ? [].slice.call(tb.children) : []; }
    if (t === "ul" || t === "ol") { var l = nd.querySelector(":scope > ul, :scope > ol"); return l ? [].slice.call(l.children) : []; }
    if (t === "p") return [].slice.call(nd.querySelectorAll(":scope > .ln"));
    if (t === "callout") { var host = nd.querySelector(":scope > .body-t") || nd; return [].slice.call(host.querySelectorAll(":scope > .ln")); }
    return [];
  }
  function buildRemainder(nd, moved, keptCount) {
    var t = nd.dataset.type, rest = document.createElement("div");
    rest.className = nd.className; rest.dataset.type = t; rest.dataset.line = nd.dataset.line; rest.dataset.count = nd.dataset.count; rest.dataset.cont = "1";
    if (t === "table") {
      var tb = nd.querySelector("table"), nt = tb.cloneNode(false), th = tb.querySelector("thead");
      if (th) nt.appendChild(th.cloneNode(true));
      var body = document.createElement("tbody"); moved.forEach(function (u) { body.appendChild(u); }); nt.appendChild(body); rest.appendChild(nt);
    } else if (t === "ul" || t === "ol") {
      var l = nd.querySelector(":scope > ul, :scope > ol"), nl = l.cloneNode(false);
      moved.forEach(function (u) { nl.appendChild(u); });
      if (t === "ol") nl.style.counterReset = "c " + keptCount;          // numbering continues
      rest.appendChild(nl);
    } else if (t === "callout" && nd.classList.contains("tip")) {
      rest.innerHTML = '<span class="bulb"><i class="fa fa-lightbulb"></i></span><div class="body-t"></div>';
      var bt = rest.querySelector(".body-t"); moved.forEach(function (u) { bt.appendChild(u); });
    } else {
      moved.forEach(function (u) { rest.appendChild(u); });
    }
    return rest;
  }
  /** try to keep the head of `nd` in `col` (already appended, overflowing). Returns remainder node or null. */
  function trySplit(nd, col) {
    var units = splitUnits(nd), n = units.length;
    if (n < 2) return null;
    var minKeep = nd.dataset.type === "table" ? 2 : (n >= 4 ? 2 : 1), minRest = nd.dataset.type === "table" ? 1 : 1;
    var moved = [];
    while (!fits(col) && n - moved.length > minKeep) { var u = units[n - 1 - moved.length]; u.parentNode.removeChild(u); moved.unshift(u); }
    if (fits(col) && moved.length >= minRest) return buildRemainder(nd, moved, n - moved.length);
    // couldn't make it fit — restore
    var host = nd.dataset.type === "table" ? nd.querySelector("tbody") : nd.dataset.type === "ul" || nd.dataset.type === "ol" ? nd.querySelector(":scope > ul, :scope > ol") : (nd.querySelector(":scope > .body-t") || nd);
    moved.forEach(function (u) { host.appendChild(u); });
    return null;
  }

  /** even out a band whose right column is still empty (called before a full-width block) */
  /* real content height of a column (scrollHeight is never smaller than the box) */
  function contentH(col) {
    var h = 0;
    for (var k = 0; k < col.children.length; k++) { var c = col.children[k]; h += c.getBoundingClientRect().height + parseFloat(getComputedStyle(c).marginBottom || 0); }
    return h;
  }
  function balanceBand(page) {
    if (page.cols.length < 2) return;
    var c1 = page.cols[0], c2 = page.cols[1];
    if (c2.childElementCount || c1.childElementCount < 2) return;
    var guard = 60;
    while (guard-- && c1.childElementCount > 1) {
      var last = c1.lastElementChild, h1 = contentH(c1), h2 = contentH(c2);
      var lh = last.getBoundingClientRect().height + parseFloat(getComputedStyle(last).marginBottom || 0);
      if (Math.max(h1 - lh, h2 + lh) < Math.max(h1, h2) - 1) c2.insertBefore(last, c2.firstChild); else break;
    }
    // never strand a heading at the bottom of the left column
    while (c1.childElementCount > 1 && /^(h1|h2|h3|banner|cap)$/.test(c1.lastElementChild.dataset.type)) c2.insertBefore(c1.lastElementChild, c2.firstChild);
    // …and if the only thing left on the left is a heading, pull one block back so it isn't alone
    if (c1.childElementCount === 1 && /^(h1|h2|h3|banner|cap)$/.test(c1.firstElementChild.dataset.type) && c2.childElementCount > 1) c1.appendChild(c2.firstElementChild);
  }

  function paginate(nodes) {
    stage.innerHTML = "";
    if (!nodes.length) { var p0 = newPage(1, false); p0.body.innerHTML = '<div class="empty-paper">Left panel me notes paste karo.<br><b>Plain text</b> bhi chalega.</div>'; return 1; }
    var pageNo = 1, page = newPage(1, false), carry = [];
    function advance(toPage) { if (!toPage && page.ci < page.cols.length - 1) { page.ci++; return; } pageNo++; page = newPage(pageNo, true); }
    function isHeading(nd) { return /^(h1|h2|h3|banner|cap)$/.test(nd.dataset.type); }
    function isWide(nd) { return nd.dataset.wide === "1"; }
    function bandEmpty() { return page.cols.every(function (c) { return c.childElementCount === 0; }); }

    for (var i = 0; i < nodes.length; i++) {
      var nd = nodes[i];
      if (nd.dataset.type === "break") { carry = []; advance(nd.dataset.kind === "page"); continue; }
      if (isHeading(nd)) { carry.push(nd); continue; }
      var group = carry.concat([nd]); carry = [];

      // every table is shrunk to its column (or the page, when [Wide]) before placing
      if (nd.dataset.type === "table" && !nd.dataset.cont) {
        var target = isWide(nd) ? page.body.clientWidth : page.cols[0].clientWidth;
        fitTable(nd.querySelector("table"), page.body, target);
      }

      if (isWide(nd)) {
        // full-width block: even out the band above it, close it, append across both columns
        for (var w = 0; w < 3; w++) {
          if (bandEmpty()) page.band.remove();                      // don't leave an empty band above it
          else { balanceBand(page); page.band.style.flex = "0 0 auto"; }
          var wrap = el("wide"); group.forEach(function (g) { wrap.appendChild(g); }); page.body.appendChild(wrap);
          var only = page.body.childElementCount === 1;
          if (bodyFits(page) || only) break;
          wrap.remove();
          pageNo++; page = newPage(pageNo, true);
        }
        page.band = null; newBand(page);
        continue;
      }

      var placed = false;
      for (var tries = 0; tries < 6 && !placed; tries++) {
        var col = page.cols[page.ci], wasEmpty = col.childElementCount === 0;
        group.forEach(function (g) { col.appendChild(g); });
        if (fits(col)) { placed = true; break; }
        // doesn't fit: keep as much of the block here as possible, carry the rest over
        var rest = trySplit(nd, col);
        if (rest) { nodes.splice(i + 1, 0, rest); placed = true; break; }
        if (wasEmpty) { placed = true; break; }          // over-tall and unsplittable — let it clip rather than loop
        group.forEach(function (g) { col.removeChild(g); }); advance(false);
      }
    }
    if (carry.length) { var last = page.cols[page.ci]; carry.forEach(function (g) { last.appendChild(g); }); }
    if (bandEmpty() && page.body.childElementCount > 1) page.band.remove();   // trailing empty band after a wide block
    var pages = stage.querySelectorAll(".page");
    pages.forEach(function (pg) { var of = pg.querySelector(".pno .of"); if (of) of.textContent = "/ " + pages.length; });
    return pages.length;
  }

  /* =====================================================================
     RENDER
  ===================================================================== */
  var lastBlocks = [];
  function render() {
    document.documentElement.style.setProperty("--nf", (parseFloat($("optFont").value) || 10) + "pt");
    var blocks = parse(ta.value); lastBlocks = blocks;
    var pages = paginate(buildNodes(blocks));
    renderOutline(blocks); refreshPosSelects();
    var c = { h1: 0, h2: 0, formula: 0, table: 0, callout: 0 }; blocks.forEach(function (b) { if (c.hasOwnProperty(b.type)) c[b.type]++; });
    metaEl.textContent = c.h1 + " sections · " + c.h2 + " sub · " + c.callout + " boxes · " + c.formula + " formulas · " + c.table + " tables → " + pages + " page" + (pages === 1 ? "" : "s");
    setStatus(blocks.length ? "Rendered on " + pages + " A4 page" + (pages === 1 ? "" : "s") + ". Print / PDF ready." : "Paste notes to begin.");
    updateActive(); hideFloat();
  }
  function renderOutline(blocks) {
    var hs = blocks.filter(function (b) { return b.type === "h1" || b.type === "h2"; });
    $("oCount").textContent = hs.length;
    $("oList").innerHTML = hs.length ? hs.map(function (b) { return '<button type="button" class="oitem' + (b.type === "h2" ? " sub" : "") + '" data-line="' + b.line + '"><span class="n">' + (b.type === "h2" ? "❖" : esc(b.num || "")) + '</span><span class="t">' + esc(b.text) + "</span></button>"; }).join("")
      : '<div style="color:#555;font-size:.76rem;padding:10px 4px;text-align:center">Koi heading nahi.</div>';
  }
  function caretTopIn(pos) {
    var cs = getComputedStyle(ta), m = document.createElement("div");
    ["fontFamily","fontSize","fontWeight","lineHeight","letterSpacing","paddingTop","paddingRight","paddingBottom","paddingLeft","tabSize"].forEach(function (p) { m.style[p] = cs[p]; });
    m.style.cssText += ";position:absolute;top:0;left:-99999px;visibility:hidden;box-sizing:content-box;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;";
    m.style.width = (ta.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight)) + "px";
    m.textContent = ta.value.slice(0, pos); var mk = document.createElement("span"); mk.textContent = "\u200b"; m.appendChild(mk);
    document.body.appendChild(m); var y = mk.offsetTop; document.body.removeChild(m); return y;
  }
  function lineBounds(line) { var lines = ta.value.split("\n"), start = 0; for (var i = 0; i < line && i < lines.length; i++) start += lines[i].length + 1; return { start: start, end: start + (lines[line] || "").length }; }
  function jumpToLine(line, selectAll) {
    var b = lineBounds(line); ta.focus();
    ta.scrollTop = Math.max(0, caretTopIn(b.start) - Math.round(ta.clientHeight * 0.3));
    ta.setSelectionRange(b.start, selectAll ? b.end : b.start); updateActive();
  }
  $("oList").addEventListener("click", function (e) { var b = e.target.closest(".oitem"); if (!b) return; jumpToLine(parseInt(b.getAttribute("data-line"), 10), true); $("oList").querySelectorAll(".flash").forEach(function (n) { n.classList.remove("flash"); }); b.classList.add("flash"); });

  /* =====================================================================
     UNDO / REDO  (own stack — programmatic edits keep history)
  ===================================================================== */
  var hist = [], hidx = -1, histT = null;
  function snap(force) {
    var s = { v: ta.value, a: ta.selectionStart, b: ta.selectionEnd };
    if (!force && hidx >= 0 && hist[hidx].v === s.v) { hist[hidx] = s; return; }
    hist = hist.slice(0, hidx + 1); hist.push(s); if (hist.length > 300) hist.shift(); hidx = hist.length - 1; updateUndoButtons();
  }
  function applyHist(s) { ta.value = s.v; ta.focus(); ta.setSelectionRange(s.a, s.b); render(); saveState(); updateUndoButtons(); }
  function undo() { if (hidx > 0) { hidx--; applyHist(hist[hidx]); } }
  function redo() { if (hidx < hist.length - 1) { hidx++; applyHist(hist[hidx]); } }
  function updateUndoButtons() { $("btnUndo").disabled = hidx <= 0; $("btnRedo").disabled = hidx >= hist.length - 1; }
  $("btnUndo").addEventListener("click", undo); $("btnRedo").addEventListener("click", redo);

  /** set the textarea text as one undoable step */
  $("btnCopyPrompt").addEventListener("click", function () {
    var b = this, txt = $("aiPrompt").value;
    function done() { b.classList.add("done"); b.innerHTML = '<i class="fa fa-check"></i> Copied'; setTimeout(function () { b.classList.remove("done"); b.innerHTML = '<i class="fa fa-copy"></i> Copy prompt'; }, 1800); }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(txt).then(done, function () { $("aiPrompt").select(); document.execCommand("copy"); done(); });
    else { $("aiPrompt").select(); document.execCommand("copy"); done(); }
  });
  function setText(v, a, b) { snap(); ta.value = v; ta.setSelectionRange(a, b == null ? a : b); snap(true); render(); saveState(); }

  /* =====================================================================
     STYLE TOGGLES (shared by editor toolbar + page floating toolbar)
  ===================================================================== */
  var WRAPS = ["**", "*", "__", "==", "%%", "^^", "!!", "::", "@@", "$"];
  var LINE_PREFIX = /^(#{1,3} |- |\d{1,2}\. |> |\$ |~ )/;
  function rx(w) { return w.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); }

  /** ranges [start,end) on a line covered by marker w (indices relative to line) */
  function markerRanges(line, w) {
    var out = [], re = new RegExp(rx(w) + "(\\S(?:[\\s\\S]*?\\S)?)" + rx(w), "g"), m;
    if (w === "*") re = /(^|[^*])\*(\S(?:[^*]*?\S)?)\*(?!\*)/g;
    while ((m = re.exec(line))) { var s = m.index + (w === "*" ? m[1].length : 0); out.push({ s: s, e: s + m[0].length - (w === "*" ? m[1].length : 0) }); }
    return out;
  }
  function activeWrapsAt(pos) {
    var v = ta.value, ls = v.lastIndexOf("\n", pos - 1) + 1, le = v.indexOf("\n", pos); if (le < 0) le = v.length;
    var line = v.slice(ls, le), rel = pos - ls, act = {};
    WRAPS.forEach(function (w) { markerRanges(line, w).forEach(function (r) { if (rel >= r.s && rel <= r.e) act[w] = r; }); });
    return { act: act, ls: ls, line: line };
  }
  function wordAt(v, pos) { var s = pos, e = pos; while (s > 0 && /\S/.test(v[s - 1])) s--; while (e < v.length && /\S/.test(v[e])) e++; return { s: s, e: e }; }

  /** all ranges of marker w on the line containing pos, as absolute [s,e) */
  function absRanges(v, pos, w) {
    var ls = v.lastIndexOf("\n", pos - 1) + 1, le = v.indexOf("\n", pos); if (le < 0) le = v.length;
    return markerRanges(v.slice(ls, le), w).map(function (r) { return { s: ls + r.s, e: ls + r.e }; });
  }

  /** Remove marker w from the selection [s,e). Handles: selection inside a
      styled run (splits it), selection covering runs (strips them), or a
      caret inside a run (unwraps it). Returns true if something changed. */
  function removeWrap(w, s, e) {
    var v = ta.value, L = w.length;
    var ranges = absRanges(v, s, w).filter(function (r) { return r.s < e && r.e > s || (s === e && s >= r.s && s <= r.e); });
    if (!ranges.length) return false;
    var out = v, shift = 0;
    ranges.forEach(function (r) {
      var rs = r.s + shift, re = r.e + shift, inner = out.slice(rs + L, re - L);
      var a = Math.max(0, s + shift - (rs + L)), b = Math.min(inner.length, e + shift - (rs + L));
      var rebuilt;
      if (s === e || (a <= 0 && b >= inner.length)) rebuilt = inner;                        // whole run
      else {
        var left = inner.slice(0, a), mid = inner.slice(a, b), right = inner.slice(b);
        var lt = left.replace(/\s+$/, ""), rt = right.replace(/^\s+/, "");
        rebuilt = (lt ? w + lt + w + left.slice(lt.length) : left) + mid + (rt ? right.slice(0, right.length - rt.length) + w + rt + w : right);
      }
      out = out.slice(0, rs) + rebuilt + out.slice(re);
      shift += rebuilt.length - (re - rs);
    });
    var ns = Math.max(0, s - L), ne = Math.min(out.length, e - L);
    setText(out, ns, ne);
    return true;
  }

  function toggleWrap(w) {
    var v = ta.value, s = ta.selectionStart, e = ta.selectionEnd, L = w.length;
    // anything of this style touching the selection / caret → remove it
    if (removeWrap(w, s, e)) return;
    if (s === e) { var wd = wordAt(v, s); if (wd.s === wd.e) { toast("Pehle text select karo"); return; } s = wd.s; e = wd.e; }
    var sel = v.slice(s, e);
    if (sel.length >= 2 * L && sel.slice(0, L) === w && sel.slice(-L) === w) { setText(v.slice(0, s) + sel.slice(L, -L) + v.slice(e), s, e - 2 * L); return; }
    // trim the selection to its non-space core so markers hug the text
    var lead = sel.match(/^\s*/)[0].length, trail = sel.match(/\s*$/)[0].length; s += lead; e -= trail; sel = v.slice(s, e);
    if (!sel) return;
    setText(v.slice(0, s) + w + sel + w + v.slice(e), s + L, e + L);
  }
  function clearFormat() {
    var v = ta.value, s = ta.selectionStart, e = ta.selectionEnd;
    if (s === e) { var info = activeWrapsAt(s), best = null; WRAPS.forEach(function (w) { if (info.act[w]) best = info.act[w]; }); if (!best) { toast("Cursor kisi styled text par rakho"); return; } s = info.ls + best.s; e = info.ls + best.e; }
    var sel = v.slice(s, e), before = v.slice(0, s), after = v.slice(e);
    // markers immediately around the selection
    WRAPS.forEach(function (w) { var L = w.length; if (before.slice(-L) === w && after.slice(0, L) === w) { before = before.slice(0, -L); after = after.slice(L); } });
    var cleaned = sel.replace(/\*\*|==|!!|%%|\^\^|::|@@|__|(?<!\*)\*(?!\*)|\$/g, "");
    setText(before + cleaned + after, before.length, before.length + cleaned.length);
  }
  function toggleLine(prefix) {
    var v = ta.value, s = ta.selectionStart, e = ta.selectionEnd;
    var ls = v.lastIndexOf("\n", s - 1) + 1, le = v.indexOf("\n", e); if (le < 0) le = v.length;
    var chunk = v.slice(ls, le).split("\n");
    var allHave = prefix && chunk.every(function (l) { return prefix === "1. " ? /^\d{1,2}\. /.test(l) : l.indexOf(prefix) === 0; });
    var out = chunk.map(function (l, k) { var bare = l.replace(LINE_PREFIX, ""); if (allHave || !prefix) return bare; return (prefix === "1. " ? (k + 1) + ". " : prefix) + bare; }).join("\n");
    setText(v.slice(0, ls) + out + v.slice(le), ls, ls + out.length);
  }
  function insertSnippet(text) {
    var v = ta.value, s = ta.selectionStart, e = ta.selectionEnd, before = v.slice(0, s);
    var t = (before.length && !/\n$/.test(before) ? "\n" : "") + text + "\n";
    setText(before + t + v.slice(e), s + t.length);
  }
  function runAction(btn) {
    if (btn.dataset.wrap) toggleWrap(btn.dataset.wrap);
    else if (btn.dataset.unwrap) {
      var ws = btn.dataset.unwrap === "hl" ? ["==", "%%", "^^", "!!", "::"] : [btn.dataset.unwrap], did = false;
      ws.forEach(function (w) { if (removeWrap(w, ta.selectionStart, ta.selectionEnd)) did = true; });
      if (!did) toast("Yahan koi highlight nahi hai");
    }
    else if (btn.dataset.clear) clearFormat();
    else if (btn.dataset.line != null) toggleLine(btn.dataset.line);
    else if (btn.dataset.insert) insertSnippet(btn.dataset.insert.replace(/&#10;/g, "\n"));
    ta.focus(); updateActive();
  }
  $("fmt").addEventListener("click", function (e) { var b = e.target.closest("button"); if (!b || b.id) return; runAction(b); });

  /** highlight the buttons whose style is active at the caret / current line */
  function updateActive() {
    var pos = ta.selectionStart, info = activeWrapsAt(pos);
    var lineHas = function (p) { return p === "1. " ? /^\d{1,2}\. /.test(info.line) : (p ? info.line.indexOf(p) === 0 : !LINE_PREFIX.test(info.line)); };
    document.querySelectorAll("#fmt button, #floatbar button").forEach(function (b) {
      var on = false;
      if (b.dataset.wrap) on = !!info.act[b.dataset.wrap];
      else if (b.dataset.line != null) on = lineHas(b.dataset.line);
      b.classList.toggle("on", on);
    });
  }
  ["keyup", "click", "select"].forEach(function (ev) { ta.addEventListener(ev, updateActive); });
  document.addEventListener("selectionchange", function () { if (document.activeElement === ta) updateActive(); });

  /* =====================================================================
     PAGE SELECTION → floating toolbar (Word-like)
  ===================================================================== */
  var fb = $("floatbar"), pendingSel = null;
  function hideFloat() { fb.classList.remove("show"); pendingSel = null; }
  function blockOf(node) { while (node && node.nodeType !== 1) node = node.parentNode; return node ? node.closest(".blk, .ul li, .ol li") : null; }
  function reverseSmart(s) {   // page text -> what the source most likely contains
    var out = s;
    Object.keys(GREEK).forEach(function (k) { out = out.split(GREEK[k]).join(k); });
    return out.replace(/×/g, "x").replace(/·/g, ".").replace(/≥/g, ">=").replace(/≤/g, "<=").replace(/≠/g, "!=").replace(/→/g, "->").replace(/\s+/g, " ").trim();
  }
  function stripMarkers(s) { return s.replace(/\*\*|==|!!|%%|\^\^|::|@@|__|\$|(?<!\*)\*(?!\*)/g, ""); }

  /** find the page-selected text in the source lines of its block; returns {s,e} absolute or null */
  function locateInSource(blk, text) {
    var line = parseInt(blk.dataset.line, 10), count = parseInt(blk.closest(".blk").dataset.count, 10) || 1;
    if (blk.dataset.line && blk.matches("li")) count = 1;
    var lines = ta.value.split("\n"), start = 0;
    for (var i = 0; i < line; i++) start += lines[i].length + 1;
    var region = lines.slice(line, line + count).join("\n");
    var wanted = text.replace(/\s+/g, " ").trim();
    if (!wanted) return null;
    var cands = [wanted, reverseSmart(wanted)];
    for (var c = 0; c < cands.length; c++) {
      var w = cands[c];
      // 1) plain match
      var k = region.indexOf(w); if (k >= 0) return { s: start + k, e: start + k + w.length };
      // 2) match ignoring markers: walk the region skipping marker chars
      var plain = "", map = [];
      for (var p = 0; p < region.length; p++) {
        var two = region.substr(p, 2), one = region[p];
        if (two === "**" || two === "==" || two === "!!" || two === "%%" || two === "^^" || two === "::" || two === "@@" || two === "__") { p++; continue; }
        if (one === "*" || one === "$") continue;
        plain += one; map.push(p);
      }
      k = plain.indexOf(w); if (k >= 0) return { s: start + map[k], e: start + map[k + w.length - 1] + 1 };
    }
    return null;
  }
  function onPageSelect() {
    var sel = window.getSelection();
    if (!sel || sel.isCollapsed || !sel.rangeCount) { hideFloat(); return; }
    var range = sel.getRangeAt(0);
    var blk = blockOf(range.commonAncestorContainer);
    if (!blk || !stage.contains(blk)) { hideFloat(); return; }
    var text = sel.toString();
    if (!text.trim()) { hideFloat(); return; }
    pendingSel = { blk: blk, text: text };
    var r = range.getBoundingClientRect();
    fb.classList.add("show");
    var w = fb.offsetWidth, h = fb.offsetHeight;
    var x = Math.max(8, Math.min(window.innerWidth - w - 8, r.left + r.width / 2 - w / 2));
    var y = r.top - h - 12; if (y < 8) y = r.bottom + 12;
    fb.style.left = x + "px"; fb.style.top = y + "px";
    // reflect the styles present in the source at that spot
    var loc = locateInSource(blk, text);
    fb.querySelectorAll("button").forEach(function (b) {
      var on = false;
      if (loc) {
        var v = ta.value, mid = loc.s;
        if (b.dataset.wrap) { var info = activeWrapsAt(mid); on = !!info.act[b.dataset.wrap]; }
        else if (b.dataset.line != null) { var ls = v.lastIndexOf("\n", mid - 1) + 1, le = v.indexOf("\n", mid); if (le < 0) le = v.length; var ln = v.slice(ls, le); on = b.dataset.line === "1. " ? /^\d{1,2}\. /.test(ln) : (b.dataset.line ? ln.indexOf(b.dataset.line) === 0 : !LINE_PREFIX.test(ln)); }
      }
      b.classList.toggle("on", on);
    });
  }
  $("previewCol").addEventListener("mouseup", function () { setTimeout(onPageSelect, 10); });
  $("previewCol").addEventListener("keyup", function () { setTimeout(onPageSelect, 10); });
  document.addEventListener("mousedown", function (e) { if (!fb.contains(e.target) && !stage.contains(e.target)) hideFloat(); });
  $("previewCol").addEventListener("scroll", hideFloat, { passive: true });

  fb.addEventListener("mousedown", function (e) { e.preventDefault(); });   // keep the page selection alive
  fb.addEventListener("click", function (e) {
    var b = e.target.closest("button"); if (!b || !pendingSel) return;
    var loc = locateInSource(pendingSel.blk, pendingSel.text);
    if (!loc) { toast("Ye text source me nahi mila — editor me select karke lagao"); hideFloat(); return; }
    ta.setSelectionRange(loc.s, loc.e);
    runAction(b);
    window.getSelection().removeAllRanges();
    hideFloat();
    toast(b.title ? b.title + " ✓" : "Done");
  });

  /* =====================================================================
     HIGHLIGHT PALETTE — five slots, CSS variables, presets
  ===================================================================== */
  var PRESETS = {
    textbook: ["#fff176", "#b3e5fc", "#c5e1a5", "#f8bbd0", "#ffcc80"],
    pastel:   ["#fff9c4", "#e1f5fe", "#e8f5e9", "#fce4ec", "#fff3e0"],
    bright:   ["#ffeb3b", "#4fc3f7", "#aed581", "#f48fb1", "#ffb74d"],
    mono:     ["#fff176", "#fff176", "#fff176", "#fff176", "#fff176"],
    print:    ["#fffde7", "#e3f2fd", "#f1f8e9", "#fce4ec", "#fff8e1"]
  };
  var palette = PRESETS.textbook.slice();
  function applyPalette() {
    palette.forEach(function (c, i) { document.documentElement.style.setProperty("--hl" + (i + 1), c); });
    document.querySelectorAll("#pal input[type=color]").forEach(function (inp) { inp.value = palette[parseInt(inp.dataset.hl, 10) - 1]; });
  }
  $("pal").addEventListener("input", function (e) {
    var inp = e.target.closest("input[type=color]"); if (!inp) return;
    palette[parseInt(inp.dataset.hl, 10) - 1] = inp.value;
    applyPalette(); saveState();
  });
  $("palPreset").addEventListener("change", function () {
    var p = PRESETS[this.value]; if (!p) return;
    palette = p.slice(); applyPalette(); saveState(); this.value = "";
    toast("Saare highlights badal gaye");
  });

  /* =====================================================================
     PERSISTENCE — MySQL via d2d_notes_api.php, with a local draft safety net

     Flow on every edit:
       saveState()  →  saveLocal()   (instant, localStorage, per-note key)
                    →  scheduleSave() (1.5s debounce → saveToServer)
     If the network is down, the draft stays in localStorage and a retry
     loop keeps trying; on reload, a draft newer than the server copy is
     offered for restore. Nothing typed is ever only-in-memory.
  ===================================================================== */
  var API = (window.PM_D2D && window.PM_D2D.api) || "d2d_notes_api.php";
  var FIELDS = ["subjInput","chapInput","titleInput","subInput","tagInput","badgeInput"], OPTS = ["optAuto","optBullets","optMath","optChem","optTwoCol","optWM","optBrand"];
  var FIELD_MAP = { subjInput: "subject", chapInput: "chapter_no", titleInput: "title", subInput: "subtitle", tagInput: "tagline", badgeInput: "badge" };
  var srcTa = $("srcInput");

  var cur = { id: 0, version: 0, updated_at: null };   // identity of the note currently in the editor
  var dirty = false, saving = false, pendingSave = false, saveTimer = null, retryTimer = null, retryDelay = 2000;
  var online = navigator.onLine !== false;
  var libNotes = [], libFilter = "", showingTrash = false;
  var suppressSave = false;   // true while programmatically filling the form (loading a note)

  function syncUI(state, text) {
    var el = $("sync"); el.className = "sync " + state; $("syncTxt").textContent = text;
  }
  function fmtAgo(ts) {
    if (!ts) return "";
    var d = new Date(String(ts).replace(" ", "T")); if (isNaN(d)) return "";
    var s = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (s < 5) return "just now"; if (s < 60) return s + "s ago"; var m = Math.floor(s / 60); if (m < 60) return m + "m ago";
    var h = Math.floor(m / 60); if (h < 24) return h + "h ago"; return d.toLocaleDateString("en-IN", { day: "numeric", month: "short" });
  }

  /* ---- snapshot of everything in the editor ---- */
  function collect() {
    var n = { id: cur.id, version: cur.version, notes_text: ta.value, source_content: srcTa.value, settings: { o: {}, font: $("optFont").value, pal: palette.slice() } };
    FIELDS.forEach(function (id) { n[FIELD_MAP[id]] = $(id).value; });
    OPTS.forEach(function (id) { n.settings.o[id] = $(id).checked; });
    return { note: n, images: images };
  }
  function applyNote(note, imgs) {
    suppressSave = true;
    try {
      FIELDS.forEach(function (id) { $(id).value = (note && note[FIELD_MAP[id]] != null) ? String(note[FIELD_MAP[id]]) : ""; });
      ta.value = (note && typeof note.notes_text === "string") ? note.notes_text : "";
      srcTa.value = (note && typeof note.source_content === "string") ? note.source_content : "";
      var st = (note && note.settings) || {};
      OPTS.forEach(function (id) { $(id).checked = (st.o && typeof st.o[id] === "boolean") ? st.o[id] : $(id).defaultChecked; });
      $("optFont").value = st.font || $("optFont").defaultValue;
      palette = (Array.isArray(st.pal) && st.pal.length === 5) ? st.pal.slice() : PRESETS.textbook.slice();
      images = Array.isArray(imgs) ? imgs.filter(function (x) { return x && x.name && x.data; }) : [];
      applyPalette(); stage.classList.toggle("no-wm", !$("optWM").checked);
      renderImages(); updateSrcMeta();
      hist = []; hidx = -1; snap(true); render();
    } finally { suppressSave = false; }
  }

  /* ---- local draft (survives tab close / crash / offline) ---- */
  function draftKey(id) { return "d2d_draft_" + (id || "new"); }
  function draftImgKey(id) { return "d2d_draftimg_" + (id || "new"); }
  function saveLocal() {
    try {
      var c = collect(); c.note.savedAt = Date.now(); c.note.dirty = dirty;
      localStorage.setItem(draftKey(cur.id), JSON.stringify(c.note));
      localStorage.setItem("d2d_last_id", String(cur.id || 0));
    } catch (e) { /* quota — server save still runs */ }
  }
  function saveLocalImages() {
    try { localStorage.setItem(draftImgKey(cur.id), JSON.stringify(images)); return true; }
    catch (e) { toast("Local cache full — images sirf server pe save hongi"); return false; }
  }
  function readDraft(id) {
    try { var d = JSON.parse(localStorage.getItem(draftKey(id)) || "null"); var im = JSON.parse(localStorage.getItem(draftImgKey(id)) || "null"); if (d) d._images = Array.isArray(im) ? im : null; return d; } catch (e) { return null; }
  }
  function clearDraft(id) { try { localStorage.removeItem(draftKey(id)); localStorage.removeItem(draftImgKey(id)); } catch (e) {} }

  /* ---- what every edit calls (kept the old name so all call sites still work) ---- */
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

  /* ---- server I/O ---- */
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
    var snapVersion = cur.version, payload = collect();
    return api("save", payload).then(function (r) {
      saving = false;
      if (r.ok && r.json && r.json.status === "success") {
        var wasNew = !cur.id;
        cur.id = r.json.id; cur.version = r.json.version; cur.updated_at = r.json.updated_at;
        if (cur.version !== snapVersion + 1 && !wasNew) { /* tolerate */ }
        dirty = false; retryDelay = 2000;
        clearDraft(wasNew ? 0 : cur.id); clearDraft(cur.id); saveLocal();  // keep a clean draft for crash-restore
        syncUI("saved", "Saved · " + fmtAgo(cur.updated_at) + (reason === "manual" ? " (manual)" : ""));
        touchLibEntry(); if (wasNew) loadList(false);
        if (pendingSave) { pendingSave = false; dirty = true; return saveToServer("auto"); }
        return true;
      }
      if (r.status === 401) { syncUI("error", "Login expire ho gaya — admin_panel.php me dobara login karo. Draft local me safe hai."); return false; }
      if (r.status === 409 && r.json && r.json.code === "version_conflict") { showConflict(r.json.server_version); return false; }
      syncUI("error", "Save failed: " + ((r.json && r.json.msg) || ("HTTP " + r.status)) + " — retrying");
      armRetry(); return false;
    }).catch(function (e) {
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

  /* ---- modal helpers ---- */
  function showModal(title, body, buttons) {
    $("modalTitle").textContent = title; $("modalBody").innerHTML = body;
    var row = $("modalRow"); row.innerHTML = "";
    buttons.forEach(function (b) { var el = document.createElement("button"); el.type = "button"; el.className = "btn" + (b.ghost ? " ghost" : ""); el.innerHTML = b.html; el.addEventListener("click", function () { hideModal(); b.fn && b.fn(); }); row.appendChild(el); });
    $("modalScrim").classList.add("show");
  }
  function hideModal() { $("modalScrim").classList.remove("show"); }
  function showConflict(serverVersion) {
    syncUI("error", "Conflict — server pe nayi copy hai");
    showModal("Server pe nayi version hai",
      "Is chapter ko kisi doosre tab/device se save kiya gaya (v" + serverVersion + "). Aap kya rakhna chahte ho?<br><br><b style='color:#fff'>Mera rakho</b> — server wali overwrite hogi.<br><b style='color:#fff'>Server wala lo</b> — aapke abhi ke changes local draft me rahenge.",
      [
        { html: '<i class="fa fa-cloud-arrow-down"></i> Server wala lo', ghost: true, fn: function () { var keep = collect(); try { localStorage.setItem("d2d_conflict_backup_" + cur.id + "_" + Date.now(), JSON.stringify(keep)); } catch (e) {} loadNote(cur.id, { ignoreDraft: true }); } },
        { html: '<i class="fa fa-cloud-arrow-up"></i> Mera rakho', fn: function () { cur.version = serverVersion; dirty = true; saveToServer("force"); } }
      ]);
  }

  /* ---- load / new / delete ---- */
  function loadNote(id, opts) {
    opts = opts || {};
    if (dirty && cur.id !== id) saveLocal();
    syncUI("saving", "Loading chapter…");
    return api("get", { id: id }, "POST").then(function (r) {
      if (!r.ok || !r.json || r.json.status !== "success") { syncUI("error", (r.json && r.json.msg) || "Load failed"); return false; }
      var note = r.json.note, imgs = r.json.images || [];
      cur = { id: note.id, version: note.version, updated_at: note.updated_at };
      var draft = opts.ignoreDraft ? null : readDraft(note.id);
      // Version-based, not clock-based: MySQL DATETIME has no timezone and
      // the browser would parse it as local time, so comparing timestamps
      // across server/browser zones is unreliable. A draft is "unsaved work"
      // iff it was written on top of the same server version we just got.
      if (draft && draft.dirty && draft.version === note.version && (draft.notes_text !== note.notes_text || draft.source_content !== note.source_content)) {
        applyNote(note, imgs); dirty = false; syncUI("saved", "Loaded · server copy");
        showModal("Unsaved draft mila",
          "Is chapter ka ek <b style='color:#fff'>local draft</b> hai jo server copy se naya hai (" + fmtAgo(new Date(draft.savedAt).toISOString()) + "). Shayad pichli baar net kat gaya tha ya tab band ho gaya tha.",
          [
            { html: '<i class="fa fa-trash"></i> Draft hatao', ghost: true, fn: function () { clearDraft(note.id); } },
            { html: '<i class="fa fa-rotate-left"></i> Draft restore karo', fn: function () { var d = draft; applyNote(d, d._images || imgs); dirty = true; syncUI("dirty", "Draft restored — saving…"); saveToServer("restore"); } }
          ]);
      } else {
        applyNote(note, imgs); dirty = false; clearDraft(note.id); saveLocal();
        syncUI("saved", "Loaded · last saved " + fmtAgo(note.updated_at));
      }
      highlightLib(); setStatus("Chapter " + (note.chapter_no || "") + " — " + (note.title || "") + " load ho gaya.");
      return true;
    }).catch(function () { syncUI("offline", "Load failed — connection?"); return false; });
  }

  function newNote(prefill) {
    if (dirty) saveLocal();
    cur = { id: 0, version: 0, updated_at: null };
    var base = { subject: (prefill && prefill.subject) || $("subjInput").value || "", chapter_no: (prefill && prefill.chapter_no) || "", title: (prefill && prefill.title) || "", subtitle: "", tagline: $("tagInput").defaultValue, badge: $("badgeInput").defaultValue, notes_text: (prefill && prefill.notes_text) || "", source_content: (prefill && prefill.source_content) || "", settings: (prefill && prefill.settings) || null };
    applyNote(base, (prefill && prefill.images) || []);
    var d = readDraft(0);
    if (!prefill && d && d.dirty && (d.notes_text || d.source_content)) {
      showModal("Naya chapter — unsaved draft mila", "Pichli baar ek naya chapter likhte waqt save nahi hua tha. Restore karein?",
        [{ html: "Nahi, blank", ghost: true, fn: function () { clearDraft(0); } }, { html: '<i class="fa fa-rotate-left"></i> Restore', fn: function () { applyNote(d, d._images || []); dirty = true; syncUI("dirty", "Draft restored"); scheduleSave(); } }]);
    }
    dirty = !!prefill; if (prefill) { syncUI("dirty", "Duplicate ready — saving…"); scheduleSave(); } else syncUI("saved", "New chapter — likhna shuru karo, auto-save ON");
    highlightLib(); $("titleInput").focus(); setStatus("New chapter.");
  }

  function deleteNote(id, title) {
    if (!confirm("\"" + (title || "Chapter") + "\" ko trash me daalein? (Baad me Trash se restore ho sakta hai)")) return;
    api("delete", { id: id }).then(function (r) {
      if (r.ok) { toast("Trash me gaya"); if (cur.id === id) newNote(); loadList(false); } else toast("Delete failed");
    });
  }
  function restoreNote(id) { api("restore", { id: id }).then(function (r) { if (r.ok) { toast("Restore ho gaya"); loadList(false); } }); }

  /* ---- library list ---- */
  function loadList(quiet) {
    if (!quiet) $("libList").innerHTML = '<div class="lib-empty">Loading…</div>';
    return api(showingTrash ? "trash" : "list", undefined, "GET").then(function (r) {
      if (r.status === 401) { syncUI("error", "Login required — admin_panel.php me login karo"); $("libList").innerHTML = '<div class="lib-empty">Login required</div>'; return; }
      if (!r.ok || !r.json) { $("libList").innerHTML = '<div class="lib-empty">List load nahi hui</div>'; return; }
      libNotes = r.json.notes || []; renderLib(); fillSubjectList();
      if (!showingTrash && !quiet) { online = true; if (!dirty) syncUI("saved", cur.id ? "Saved · " + fmtAgo(cur.updated_at) : "Ready · DB connected"); }
    }).catch(function () { $("libList").innerHTML = '<div class="lib-empty">Offline — list unavailable</div>'; online = false; });
  }
  function fillSubjectList() {
    var seen = {}, dl = $("subjList"); dl.innerHTML = "";
    libNotes.forEach(function (n) { var s = (n.subject || "").trim(); if (s && !seen[s]) { seen[s] = 1; var o = document.createElement("option"); o.value = s; dl.appendChild(o); } });
  }
  function renderLib() {
    var host = $("libList"), q = libFilter.toLowerCase();
    var list = libNotes.filter(function (n) { return !q || ((n.subject || "") + " " + (n.chapter_no || "") + " " + (n.title || "") + " " + (n.subtitle || "")).toLowerCase().indexOf(q) >= 0; });
    $("libCount").textContent = libNotes.length;
    if (!list.length) { host.innerHTML = '<div class="lib-empty">' + (showingTrash ? "Trash khali hai." : (libNotes.length ? "Kuch match nahi hua." : "Abhi koi chapter nahi. <b style='color:#fff'>New</b> dabao.")) + "</div>"; return; }
    var groups = {}, order = [];
    list.forEach(function (n) { var s = (n.subject || "General").trim() || "General"; if (!groups[s]) { groups[s] = []; order.push(s); } groups[s].push(n); });
    host.innerHTML = order.map(function (s) {
      return '<div class="lib-subj"><span>' + esc(s) + '</span><small>' + groups[s].length + ' ch</small></div>' + groups[s].map(function (n) {
        var meta = (n.image_count ? n.image_count + " img · " : "") + (n.notes_len ? Math.round(n.notes_len / 5) + " words · " : "") + fmtAgo(n.updated_at);
        return '<button type="button" class="lib-item' + (n.id === cur.id ? " cur" : "") + '" data-id="' + n.id + '">' +
          '<span class="no">' + esc(n.chapter_no || "•") + '</span><span class="ti"><b>' + esc(n.title || "Untitled") + '</b><small>' + esc(meta) + '</small></span>' +
          (showingTrash ? '<span class="del" data-restore="' + n.id + '" title="Restore"><i class="fa fa-rotate-left"></i></span>' : '<span class="del" data-del="' + n.id + '" title="Trash"><i class="fa fa-trash"></i></span>') + '</button>';
      }).join("");
    }).join("");
  }
  function highlightLib() { document.querySelectorAll(".lib-item").forEach(function (el) { el.classList.toggle("cur", parseInt(el.dataset.id, 10) === cur.id); }); }
  function touchLibEntry() {
    var c = collect().note, found = false;
    libNotes.forEach(function (n) { if (n.id === cur.id) { found = true; n.subject = c.subject; n.chapter_no = c.chapter_no; n.title = c.title; n.subtitle = c.subtitle; n.updated_at = cur.updated_at; n.image_count = images.length; n.notes_len = c.notes_text.length; } });
    if (found) { renderLib(); fillSubjectList(); }
  }
  $("libList").addEventListener("click", function (e) {
    var del = e.target.closest("[data-del]"); if (del) { e.stopPropagation(); var n = libNotes.filter(function (x) { return x.id === parseInt(del.dataset.del, 10); })[0]; deleteNote(parseInt(del.dataset.del, 10), n && n.title); return; }
    var rs = e.target.closest("[data-restore]"); if (rs) { e.stopPropagation(); restoreNote(parseInt(rs.dataset.restore, 10)); return; }
    var it = e.target.closest(".lib-item"); if (!it) return;
    var id = parseInt(it.dataset.id, 10); if (id === cur.id && !showingTrash) return;
    if (dirty) { saveLocal(); saveToServer("switch"); }
    loadNote(id);
  });
  $("libSearch").addEventListener("input", function () { libFilter = this.value.trim(); renderLib(); });
  $("btnNewNote").addEventListener("click", function () { newNote(); });
  $("btnLibRefresh").addEventListener("click", function () { loadList(false); toast("List refreshed"); });
  $("btnTrash").addEventListener("click", function () { showingTrash = !showingTrash; this.innerHTML = showingTrash ? '<i class="fa fa-folder"></i> Library' : '<i class="fa fa-trash-can"></i> Trash'; loadList(false); });
  $("btnDuplicate").addEventListener("click", function () {
    var c = collect(); c.note.title = (c.note.title || "Untitled") + " (Copy)"; c.note.chapter_no = ""; c.note.images = images.slice();
    newNote(c.note); toast("Duplicate bana — chapter number set karo");
  });
  $("btnSaveNow").addEventListener("click", function () { dirty = true; saveToServer("manual"); });
  document.addEventListener("keydown", function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s") { e.preventDefault(); dirty = true; saveToServer("manual"); } });

  /* ---- chapter content (source) box ---- */
  function updateSrcMeta() { var w = srcTa.value.trim() ? srcTa.value.trim().split(/\s+/).length : 0; $("srcMeta").textContent = w + " words · " + srcTa.value.length + " chars"; $("srcCount").textContent = w ? Math.round(w / 100) / 10 + "k" : "0"; }
  srcTa.addEventListener("input", function () { updateSrcMeta(); saveState(); });
  $("btnSrcToNotes").addEventListener("click", function () { if (!srcTa.value.trim()) { toast("Source khali hai"); return; } insertLineAt("cursor", srcTa.value.trim()); toast("Notes me add ho gaya"); });
  $("btnSrcCopy").addEventListener("click", function () { var t = srcTa.value; if (navigator.clipboard) navigator.clipboard.writeText(t).then(function () { toast("Copied"); }); else { srcTa.select(); document.execCommand("copy"); toast("Copied"); } });


  /* =====================================================================
     IMAGES  — part of the note (saved to DB with it), placed with [img: name]
  ===================================================================== */
  var images = [];
  function findImage(name) { name = String(name || "").toLowerCase(); for (var i = 0; i < images.length; i++) if (images[i].name.toLowerCase() === name) return images[i]; return null; }
  function loadImages() { /* images arrive with the note via loadNote() */ }
  function saveImages() { saveLocalImages(); saveState(); return true; }
  function slug(fn) { return (fn || "img").replace(/\.[a-z0-9]+$/i, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 24) || "img"; }
  function uniqueName(base) { var n = base, k = 2; while (findImage(n)) n = base + "-" + (k++); return n; }
  /** downscale to max 1400px and re-encode so localStorage stays small */
  function compressFile(file) {
    return new Promise(function (res, rej) {
      var url = URL.createObjectURL(file), im = new Image();
      im.onload = function () {
        var MAX = 1400, w = im.naturalWidth, h = im.naturalHeight, sc = Math.min(1, MAX / Math.max(w, h));
        var cw = Math.round(w * sc), ch = Math.round(h * sc), c = document.createElement("canvas"); c.width = cw; c.height = ch;
        var ctx = c.getContext("2d"); ctx.drawImage(im, 0, 0, cw, ch);
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
    list.reduce(function (p, f) { return p.then(function () { return compressFile(f).then(function (r) { var nm = uniqueName(slug(f.name)); images.push({ name: nm, data: r.data, w: r.w, h: r.h }); added.push(nm); }, function () { toast(f.name + " load nahi hui"); }); }); }, Promise.resolve())
      .then(function () { saveImages(); renderImages(); render(); if (added.length) toast(added.length + " image add ho gayi"); if (then) then(added); });
  }
  function imgLine(nm, w, al, cap) { return "[img: " + nm + (w && w !== "100%" ? " | " + w : "") + (al && al !== "center" ? " | " + al : "") + (cap ? " | " + cap.replace(/[\[\]|]/g, "") : "") + "]"; }
  /** insert a line after source line index `after` (-1 = start, null = at cursor, "end" = end) with blank lines around */
  function insertLineAt(after, text) {
    var v = ta.value, lines = v.split("\n"), at;
    if (after === "cursor" || after == null) { var pos = ta.selectionEnd, cnt = 0; for (at = 0; at < lines.length; at++) { cnt += lines[at].length + 1; if (cnt > pos) break; } at = Math.min(at + 1, lines.length); }
    else if (after === "end") at = lines.length;
    else at = Math.min(parseInt(after, 10) + 1, lines.length);
    var ins = [];
    if (at > 0 && lines[at - 1].trim()) ins.push("");
    ins.push(text);
    if (at < lines.length && lines[at].trim()) ins.push("");
    lines.splice.apply(lines, [at, 0].concat(ins));
    var before = lines.slice(0, at + ins.length - (ins[ins.length - 1] === "" ? 1 : 0)).join("\n");
    setText(lines.join("\n"), before.length - text.length, before.length);
  }
  function posOptions() {
    var src = ta.value.split("\n"), ICON = { h1: "#", h2: "❖", h3: "###", p: "¶", ul: "•", ol: "1.", callout: "▭", formula: "ƒ", table: "▦", q: "Q", banner: "▬", cap: "▬", qt: "~", img: "🖼", break: "—" };
    var o = '<option value="cursor">⌖ Cursor ke baad (editor me jahan cursor hai)</option><option value="end">⤓ Notes ke end me</option><option value="-1">⤒ Sabse upar</option>';
    lastBlocks.forEach(function (b) {
      var last = b.line + (b.count || 1) - 1, txt = (src[b.line] || "").replace(/^(#{1,3}\s+|>\s?|\$\s+|[-•*]\s+|\d{1,2}[\.\)]\s+|\[(?:Banner|Caption)\]\s*|~\s+)/, "").replace(/[*_=%^!:@]{2}|\|/g, " ").trim();
      if (txt.length > 46) txt = txt.slice(0, 44) + "…";
      var ind = (b.type === "h1" || b.type === "h2" || b.type === "h3") ? "" : " ";
      o += '<option value="' + last + '">' + ind + (ICON[b.type] || "¶") + " " + esc(txt || b.type) + "</option>";
    });
    return o;
  }
  function refreshPosSelects() {
    var html = posOptions();
    document.querySelectorAll(".imgPos").forEach(function (sel) { var v = sel.value; sel.innerHTML = html; if ([].some.call(sel.options, function (op) { return op.value === v; })) sel.value = v; });
  }
  function renderImages() {
    var host = $("imgList"); $("imgCount").textContent = images.length;
    if (!images.length) { host.innerHTML = '<div class="imgs-empty">Abhi koi image nahi. Upar se add karo.</div>'; return; }
    host.innerHTML = images.map(function (im, k) {
      return '<div class="imgc" data-k="' + k + '"><img class="th" src="' + im.data + '" alt=""><div class="bd">' +
        '<div class="nm"><code title="' + esc(im.name) + '">' + esc(im.name) + '</code><small>' + im.w + "×" + im.h + '</small><button type="button" class="del" title="Delete"><i class="fa fa-trash"></i></button></div>' +
        '<select class="inp imgPos" title="Insert after"></select>' +
        '<div class="r2"><select class="inp imgW"><option value="100%">Width 100%</option><option value="75%">75%</option><option value="60%" selected>60%</option><option value="50%">50%</option><option value="40%">40%</option><option value="33%">33%</option><option value="wide">Wide (full page)</option></select>' +
        '<select class="inp imgAl"><option value="center">Center</option><option value="left">Left</option><option value="right">Right</option></select></div>' +
        '<input class="inp imgCap" placeholder="Caption (optional)">' +
        '<button type="button" class="ins"><i class="fa fa-arrow-turn-down"></i> Insert</button></div></div>';
    }).join("");
    refreshPosSelects();
  }
  $("imgList").addEventListener("click", function (e) {
    var card = e.target.closest(".imgc"); if (!card) return;
    var im = images[parseInt(card.dataset.k, 10)]; if (!im) return;
    if (e.target.closest(".del")) {
      if (!confirm("Image \"" + im.name + "\" delete karein?")) return;
      images.splice(images.indexOf(im), 1); saveImages(); renderImages(); render(); return;
    }
    if (e.target.closest(".ins")) {
      var w = card.querySelector(".imgW").value, al = card.querySelector(".imgAl").value, cap = card.querySelector(".imgCap").value.trim(), pos = card.querySelector(".imgPos").value;
      var line = imgLine(im.name, w === "wide" ? "" : w, al, cap);
      if (w === "wide") line = "[Wide]\n" + line;
      insertLineAt(pos, line); toast("Image insert ho gayi"); ta.focus();
    }
  });
  $("imgDrop").addEventListener("click", function () { $("imgFile").click(); });
  $("imgFile").addEventListener("change", function () { addImageFiles(this.files); this.value = ""; });
  ["dragenter", "dragover"].forEach(function (ev) { $("imgDrop").addEventListener(ev, function (e) { e.preventDefault(); this.classList.add("over"); }); });
  ["dragleave", "drop"].forEach(function (ev) { $("imgDrop").addEventListener(ev, function (e) { e.preventDefault(); this.classList.remove("over"); }); });
  $("imgDrop").addEventListener("drop", function (e) { addImageFiles(e.dataTransfer.files); });
  // drop a picture straight onto the text: it is added and placed at the cursor
  ta.addEventListener("dragover", function (e) { if (e.dataTransfer && [].some.call(e.dataTransfer.items || [], function (it) { return /^image\//.test(it.type); })) e.preventDefault(); });
  ta.addEventListener("drop", function (e) {
    var fs = [].slice.call(e.dataTransfer.files || []).filter(function (f) { return /^image\//.test(f.type); });
    if (!fs.length) return; e.preventDefault();
    addImageFiles(fs, function (names) { names.forEach(function (nm) { insertLineAt("cursor", imgLine(nm, "60%", "center", "")); }); });
  });
  $("btnImgTool").addEventListener("click", function () { $("accImages").classList.add("open"); $("imgFile").click(); });
  // paste a screenshot (Ctrl+V) into the editor
  ta.addEventListener("paste", function (e) {
    var its = (e.clipboardData && e.clipboardData.items) || [], fs = [];
    for (var i = 0; i < its.length; i++) if (its[i].kind === "file" && /^image\//.test(its[i].type)) fs.push(its[i].getAsFile());
    if (!fs.length) return; e.preventDefault();
    addImageFiles(fs, function (names) { names.forEach(function (nm) { insertLineAt("cursor", imgLine(nm, "60%", "center", "")); }); });
  });

  /* =====================================================================
     CONTROLS / CHROME
  ===================================================================== */
  var timer = null;
  function schedule() { if (timer) clearTimeout(timer); timer = setTimeout(function () { render(); saveState(); }, 320); }
  ta.addEventListener("input", function () { schedule(); if (histT) clearTimeout(histT); histT = setTimeout(function () { snap(); }, 450); });
  FIELDS.forEach(function (id) { $(id).addEventListener("input", schedule); });
  OPTS.concat(["optFont"]).forEach(function (id) { $(id).addEventListener("change", function () { render(); saveState(); }); });
  $("optFont").addEventListener("input", schedule);
  $("optWM").addEventListener("change", function () { stage.classList.toggle("no-wm", !$("optWM").checked); });

  ta.addEventListener("keydown", function (e) {
    var k = e.key.toLowerCase(), mod = e.ctrlKey || e.metaKey;
    if (mod && k === "z" && !e.shiftKey) { e.preventDefault(); undo(); return; }
    if (mod && (k === "y" || (k === "z" && e.shiftKey))) { e.preventDefault(); redo(); return; }
    if (mod && k === "b") { e.preventDefault(); toggleWrap("**"); return; }
    if (mod && k === "i") { e.preventDefault(); toggleWrap("*"); return; }
    if (mod && k === "u") { e.preventDefault(); toggleWrap("__"); return; }
    if (e.key === "Tab") { e.preventDefault(); var s = ta.selectionStart; setText(ta.value.slice(0, s) + "  " + ta.value.slice(ta.selectionEnd), s + 2); }
  });

  $("btnPrint").addEventListener("click", function () { if (timer) clearTimeout(timer); render(); window.print(); });
  $("btnSample").addEventListener("click", function () { setText(SAMPLE, 0); setStatus("Sample loaded."); });
  $("btnClear").addEventListener("click", function () { if (ta.value.trim() && !confirm("Saara text clear kar de?")) return; setText("", 0); ta.focus(); });
  document.querySelectorAll("[data-acc]").forEach(function (h) { h.addEventListener("click", function () { h.closest(".acc").classList.toggle("open"); }); });

  function setDrawer(open) { document.body.classList.toggle("drawer-open", open); $("sideTab").setAttribute("aria-expanded", open ? "true" : "false"); setTimeout(updateScale, 400); }
  $("sideTab").addEventListener("click", function () { setDrawer(!document.body.classList.contains("drawer-open")); });
  $("scrim").addEventListener("click", function () { setDrawer(false); });
  $("panelClose").addEventListener("click", function () { setDrawer(false); });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") { setDrawer(false); hideFloat(); } });

  function updateToolbarOffset() { document.documentElement.style.setProperty("--toolbar-h", $("topbar").offsetHeight + "px"); }
  var zoom = 1;
  function fitScale() { if (window.innerWidth > 900) return 1; var avail = Math.max(stage.clientWidth || window.innerWidth, 1) - 16; return Math.max(0.24, Math.min(1, avail / (210 * 3.7795275591))); }
  function updateScale() { document.documentElement.style.setProperty("--sheet-scale", String(fitScale() * zoom)); }
  var badgeT = null;
  function setZoom(z) { zoom = Math.min(3, Math.max(0.3, z)); updateScale(); var b = $("zoomBadge"); b.textContent = Math.round(zoom * 100) + "%"; b.classList.add("show"); if (badgeT) clearTimeout(badgeT); badgeT = setTimeout(function () { b.classList.remove("show"); }, 1100); }
  document.addEventListener("wheel", function (e) { if (!e.ctrlKey && !e.metaKey) return; e.preventDefault(); setZoom(zoom * (e.deltaY < 0 ? 1.12 : 1 / 1.12)); }, { passive: false });
  document.addEventListener("keydown", function (e) {
    if (!e.ctrlKey && !e.metaKey || document.activeElement === ta) return;
    if (e.key === "+" || e.key === "=") { e.preventDefault(); setZoom(zoom * 1.15); }
    else if (e.key === "-" || e.key === "_") { e.preventDefault(); setZoom(zoom / 1.15); }
    else if (e.key === "0") { e.preventDefault(); setZoom(1); }
    else if (e.key.toLowerCase() === "z" && !e.shiftKey) { e.preventDefault(); undo(); }
    else if (e.key.toLowerCase() === "y" || (e.key.toLowerCase() === "z" && e.shiftKey)) { e.preventDefault(); redo(); }
  });
  (function () { var lastY = 0, lastRun = 0; window.addEventListener("scroll", function () { var now = Date.now(); if (now - lastRun < 60) return; lastRun = now; if (window.innerWidth > 900) { document.body.classList.remove("bar-hidden"); lastY = 0; return; } var y = window.scrollY || 0, dy = y - lastY; if (Math.abs(dy) < 6) return; if (dy > 0 && y > 70) document.body.classList.add("bar-hidden"); else if (dy < 0) document.body.classList.remove("bar-hidden"); lastY = y; }, { passive: true }); })();
  window.addEventListener("resize", function () { updateToolbarOffset(); updateScale(); schedule(); hideFloat(); });

  /* ---- boot ---- */
  document.body.classList.add("booting");
  updateToolbarOffset();
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { updateToolbarOffset(); render(); });
  setDrawer(true);
  applyPalette(); renderImages(); updateSrcMeta();
  stage.classList.toggle("no-wm", !$("optWM").checked);
  snap(true); render();

  // One-time migration: if the old single-document localStorage version of
  // this tool left work behind, bring it in as the first chapter so nothing
  // written before the DB version is lost.
  function migrateLegacy() {
    var s = null, im = null;
    try { s = JSON.parse(localStorage.getItem("notesd2d_v2") || "null"); im = JSON.parse(localStorage.getItem("notesd2d_imgs") || "null"); } catch (e) {}
    if (!s || typeof s.text !== "string" || !s.text.trim()) return false;
    var f = s.f || {};
    var note = { subject: "Imported", chapter_no: f.chapInput || "", title: f.titleInput || "Imported notes", subtitle: f.subInput || "", tagline: f.tagInput || "", badge: f.badgeInput || "", notes_text: s.text, source_content: "", settings: { o: s.o || {}, font: s.font, pal: s.pal } };
    newNote(note); if (Array.isArray(im)) { images = im.filter(function (x) { return x && x.name && x.data; }); renderImages(); render(); }
    try { localStorage.removeItem("notesd2d_v2"); localStorage.removeItem("notesd2d_imgs"); } catch (e) {}
    toast("Purana kaam import ho gaya — save ho raha hai");
    return true;
  }

  syncUI("saving", "Connecting to database…");
  loadList(false).then(function () {
    var last = parseInt(localStorage.getItem("d2d_last_id") || "0", 10);
    var exists = libNotes.some(function (n) { return n.id === last; });
    if (last > 0 && exists) return loadNote(last);
    // Was in the middle of a brand-new (never saved) chapter when the tab
    // closed / net died → newNote() finds that draft and offers restore.
    var nd = readDraft(0);
    if (last === 0 && nd && nd.dirty && (nd.notes_text || nd.source_content)) return newNote();
    if (migrateLegacy()) return;
    if (libNotes.length) return loadNote(libNotes[0].id);
    newNote();
  });

  function endBoot() { document.body.classList.remove("booting"); }
  requestAnimationFrame(function () { requestAnimationFrame(endBoot); }); setTimeout(endBoot, 300);
})();
</script>
</body>
</html>
