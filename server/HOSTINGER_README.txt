PREMium Mind — Hostinger PHP (secure PDF setup)

UPLOAD / REPLACE to public_html/premind/:
  proxy_pdf.php
  secure_pdf.php
  secure_page_image.php   ← watermarked page images for allow_download=0 courses
  get_pdf_token.php
  pm_pdf_access.php       ← shared enrollment / allow_download / app_only checks
  pm_load_secrets.php
  pm_secrets.php          ← copy from pm_secrets.example.php, set your secret
  admin_panel.php
  uploads/pdfs/.htaccess  ← blocks direct access to PDF files, must stay in that folder

Requires the Imagick PHP extension (already used for preview_pdf.php).

NEVER commit pm_secrets.php to GitHub.
NEVER put secrets in Netlify HTML/JS.

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
