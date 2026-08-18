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
