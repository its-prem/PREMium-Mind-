PREMium Mind — Android WebView App (screenshot block + PDF download fix)
========================================================================

Ye folder Android Studio project NAHI hai. Ye sirf 3 ready files hain jinhe
tumhare naye Android Studio project me paste karna hai.

--------------------------------------------------------------------
STEP-BY-STEP
--------------------------------------------------------------------

1) Android Studio kholo -> New Project -> "Empty Views Activity"
   -> Language: Kotlin -> Minimum SDK: API 24 (default) -> Finish.

2) MainActivity.kt
   - Is folder ki MainActivity.kt ka poora content copy karo.
   - Project me: app/src/main/java/.../MainActivity.kt me paste karo.
   - ⚠️ PEHLI LINE badlo: "package com.premiummind.app" ko apne actual
     package name se replace karo (jo left panel me dikh raha hai).

3) activity_main.xml
   - Is folder ki activity_main.xml ka content copy karo.
   - Project me: app/src/main/res/layout/activity_main.xml me paste karo.

4) AndroidManifest.xml
   - AndroidManifest-snippet.xml me di gayi <uses-permission> lines
     apni AndroidManifest.xml me <application> tag se PEHLE paste karo.

5) Run (green ▶️) -> emulator ya phone (USB debugging ON) pe chalao.

--------------------------------------------------------------------
KYA HOGA
--------------------------------------------------------------------
- Site app jaisi khulegi (login, PDF sab wahi jo browser me hai).
- Screenshot (Power+Volume) -> BLACK/BLANK aayega (FLAG_SECURE).
- Recent apps preview bhi mostly blank.
- Save PDF button -> blob PDF ab "Downloads" folder me save hoga
  (blob download bridge lagaya gaya hai).

--------------------------------------------------------------------
KYA BLOCK NAHI HOGA (imaandari se)
--------------------------------------------------------------------
- Dusre phone/camera se screen ki photo -> block nahi hoti.
- Rooted / custom ROM pe FLAG_SECURE bypass ho sakta hai.
- iPhone / desktop browser -> ye app-only protection nahi lagti.

--------------------------------------------------------------------
PLAY STORE
--------------------------------------------------------------------
- Google Play Console account: $25 (one-time).
- Build -> Generate Signed Bundle/APK -> signed .aab banao.
- Listing: screenshots + description + privacy policy link (PDF/courses
  app ke liye zaroori).
- Submit -> review 1-3 din.

Site URL is app me: https://premind.diplomawallah.in
(Netlify wala chahiye to MainActivity.kt me startUrl badal dena:
 https://premind.netlify.app)
