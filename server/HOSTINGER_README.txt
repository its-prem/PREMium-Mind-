PREMium Mind — Hostinger PHP (secure PDF setup)

UPLOAD / REPLACE to public_html/premind/:
  proxy_pdf.php
  secure_pdf.php
  secure_page_image.php   ← watermarked page images for allow_download=0 courses
  get_pdf_token.php
  pm_pdf_access.php       ← shared enrollment / allow_download / app_only checks
  pm_load_secrets.php
  pm_load_env.php         ← tiny .env parser, no composer needed
  .env                    ← copy from .env.example, set your real secrets
  .htaccess               ← blocks direct HTTP access to .env / pm_secrets.php
  pm_secrets.php          ← legacy fallback; copy from pm_secrets.example.php if not using .env
  admin_panel.php
  uploads/pdfs/.htaccess  ← blocks direct access to PDF files, must stay in that folder

Requires the Imagick PHP extension (already used for preview_pdf.php).

D2D NOTES (chapter-wise short-notes editor, DB-backed):
  d2d_notes.php           ← the editor. Login on admin_panel.php first, then open this.
  d2d_notes_api.php       ← its storage API (creates d2d_notes / d2d_note_images tables on first call)
  pm_admin_auth.php       ← shared admin-session check used by the two above
  Open at: https://premind.diplomawallah.in/d2d_notes.php
  Replaces the old standalone "D2D PYQ/d2dmasterpage.html" (that one saved only in the browser).

D2D MCQ (PYQ practice-sheet maker, DB-backed):
  d2d_mcq.php             <- the sheet maker. Login on admin_panel.php first, then open this.
  d2d_mcq_api.php         <- its storage API (creates d2d_mcq_sheets / d2d_mcq_images on first call)
  Open at: https://premind.diplomawallah.in/d2d_mcq.php
  Same login, same Library/auto-save/images as d2d_notes.php.

D2D MCQ TEST (student-facing online test, no login, no DB):
  d2d_mcq_test.php        <- khud chalne wala test page (20 question andar hi hain)
  Open at: https://premind.diplomawallah.in/d2d_mcq_test.php
  Naya test banane ke liye file ke upar wala $TEST array edit karo:
    title / total_minutes / per_question_sec / mark / negative / home_url / questions
  per_question_sec hamesha lagta hai (0 karoge to sirf overall timer chalega).
  Time khatam hone par question band nahi hota — sirf notice aata hai, student
  tab bhi answer kar sakta hai aur khud Next dabata hai.
  3-line menu me "View all questions" = saare MCQ ek hi scroll me, MCQ sheet
  jaisi print/PDF layout me (Print / PDF button se PDF bana sakte ho).
  Answer key us view me sirf test submit hone ke baad dikhti hai.
  home_url = result page ke "Back to Home" button ka link.

LOGO (sab pages isi file ko use karte hain):
  diplomawallah-logo.png  <- Diploma Wallah ka seal, transparent background
  Notes/MCQ sheet ka watermark aur MCQ test page ka header logo yahi file hai.
  premind/ folder me rakhna zaroori hai, warna watermark khali rahega.

D2D SHARED FOLDERS:
  d2d_folders.php         <- one folder list for BOTH editors (creates d2d_folders table)
  Notes aur MCQ dono isi file ko use karte hain, isliye ise bhi upload karna zaroori hai.
  Folder = subject. Rename karoge to dono taraf ke chapters/sheets ka subject badal jayega.

SECRETS: prefer .env now (server/.env.example has the two keys needed).
pm_secrets.php still works as a fallback if .env isn't uploaded — you
don't need both, .env takes priority when present.

NEVER commit .env or pm_secrets.php to GitHub (both are gitignored).
NEVER put secrets in Netlify HTML/JS.
After uploading .env, confirm .htaccess is ALSO uploaded in the same
folder — without it, a direct request to /.env would download it as
plain text (.php files always execute instead; .env would not without
this rule).

MAT CHHEDO without care:
  api.php, get_user.php, login_api.php, register_api.php,
  create_order.php, verify_payment.php, db_connect.php

PDF FILES:
  public_html/premind/uploads/pdfs/yourfile.pdf

DATABASE (phpMyAdmin):
  pdf_file = uploads/pdfs/yourfile.pdf
  allow_download = 0 or 1

Admin:
  Use Google login admin_panel.php only.
  Old admin.html password panel is retired (security).
