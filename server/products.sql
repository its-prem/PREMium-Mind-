-- The 8 products the new store shows today, as rows for the `courses` table.
-- Run in phpMyAdmin (SQL tab) ONLY if you want these exact products. You can also add them one by one in
-- admin_panel.php (Category / Price / Button style / PDF upload) - same result.
--
-- How the new website reads a row:
--   category   notes | ebooks | resume | important | projects      -> the store card ("important" is new, see SETUP.md)
--   price      what the buyer pays (the server uses THIS price, nothing from the browser)
--   old_price  the cut price shown next to it
--   badge      the ribbon ("POPULAR", "NEW", ...)
--   desc1      the short line under the title
--   image      thumbnail (upload one in admin_panel.php; small-logo.png is a placeholder)
--   pdf_file   uploads/pdfs/xxx.pdf  (hidden from everybody who has not bought it)
--   demo_link  for category=resume only:  template:modern | template:classic | template:compact
--   btn_type   normal = can be bought,  coming_soon = shown but not for sale
--
-- Make sure the PDFs really exist in public_html/premind/uploads/pdfs/ before you sell them.

INSERT INTO courses
 (title, category, image, badge, desc1, desc2, price, old_price, link, demo_link, website_link, pdf_file,
  allow_download, btn_text, btn_type, show_tnc, show_report_btn, app_only, show_preview, tnc_text, download_msg, sort_order)
VALUES
 ('AI & Machine Learning Complete Notes + PYQ', 'notes', 'small-logo.png', 'POPULAR',
  'All 5 units crisp exam notes with solved JUT previous year questions.', '', '30', '199', '', '', '',
  'uploads/pdfs/ai-ml-notes.pdf', 0, 'Buy Now', 'normal', 1, 0, 0, 0, '', '', 1),
 ('Data Structures Handwritten Notes (Unit 1-5)', 'notes', 'small-logo.png', 'NEW',
  'Neat diagrams, algorithms and shortcuts for quick revision.', '', '35', '149', '', '', '',
  'uploads/pdfs/dsa-notes.pdf', 0, 'Buy Now', 'normal', 1, 0, 0, 0, '', '', 2),
 ('Internet of Things (IoT) Exam Booster Guide', 'ebooks', 'small-logo.png', 'BEST SELLER',
  'Complete syllabus diagrams, block architectures & expected questions.', '', '30', '149', '', '', '',
  'uploads/pdfs/iot-guide.pdf', 0, 'Buy Now', 'normal', 1, 0, 0, 0, '', '', 3),
 ('AI & ML Important Questions (JUT Pattern)', 'important', 'small-logo.png', 'EXAM SPECIAL',
  'Most expected 2, 5 and 10 mark questions with short answers.', '', '25', '99', '', '', '',
  'uploads/pdfs/ai-ml-important.pdf', 0, 'Buy Now', 'normal', 1, 0, 0, 0, '', '', 4),
 ('D2D PYQ Research Lab & Full Mock Bank', 'important', 'small-logo.png', 'NEW',
  '6 years solved Jharkhand D2D entrance papers with shortcut tricks.', '', '49', '249', '', '', '',
  'uploads/pdfs/d2d-mock.pdf', 0, 'Coming Soon', 'coming_soon', 1, 0, 0, 0, '', '', 5),
 ('Modern ATS Resume Template (Editable)', 'resume', 'small-logo.png', 'LIMITED OFFER',
  'Two-column layout, clean sections. Edit everything, download PDF.', '', '49', '199', '', 'template:modern', '',
  '', 0, 'Buy Now', 'normal', 1, 0, 0, 0, '', '', 6),
 ('Classic Professional Resume Template', 'resume', 'small-logo.png', 'BEST SELLER',
  'Traditional single-column format that recruiters love.', '', '39', '149', '', 'template:classic', '',
  '', 0, 'Buy Now', 'normal', 1, 0, 0, 0, '', '', 7),
 ('Fresher Compact Resume Template', 'resume', 'small-logo.png', 'FRESHER',
  'One-page, skills-first design made for diploma & B.Tech freshers.', '', '29', '129', '', 'template:compact', '',
  '', 0, 'Buy Now', 'normal', 1, 0, 0, 0, '', '', 8);
