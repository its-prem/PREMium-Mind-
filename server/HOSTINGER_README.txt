PREMium Mind — Hostinger PHP (PURANA SETUP)

UPLOAD / REPLACE:
  server/proxy_pdf.php  →  public_html/premind/proxy_pdf.php

DELETE ON HOSTINGER (agar upload kiye the):
  premind/get_pdf_token.php
  premind/secure_pdf.php
  premind/.pdf_tokens/   (folder)

MAT CHHEDO:
  api.php, get_user.php, login_api.php, register_api.php,
  admin_api.php, create_order.php, verify_payment.php, etc.

PDF FILES:
  public_html/premind/uploads/pdfs/yourfile.pdf

DATABASE (phpMyAdmin):
  pdf_file = uploads/pdfs/yourfile.pdf
  allow_download = 0 or 1

SECRET (watch.html aur proxy_pdf.php dono mein same):
  PREM_MIND_SECURE_2026
