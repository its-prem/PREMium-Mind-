D2D TEST ENGINE  (d2d.diplomawallah.in)
=======================================

index.php                <- isi naam se site ke root me upload karo
diplomawallah-logo.png   <- usi folder me rakhna (header + footer + watermark logo)

Ye file do kaam karti hai:
  * admin logged in + ?view=... ho to  -> Test Engine admin panel
  * warna /slug ya ?slug=...           -> student ka test page

Tables: web_test_series, web_test_questions, web_test_results
DB connection ../db.php se aata hai.

Student page ko ab correct answer bhejti nahi hai — jawab sirf submit ke baad
server se aate hain, isliye page source se answer nahi dikhte.
