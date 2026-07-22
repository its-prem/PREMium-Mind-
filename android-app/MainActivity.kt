// ⚠️ Is line ko apne project ke actual package name se replace karo
// (Android Studio ke left panel me MainActivity.kt ke upar dikhega, e.g. com.yourname.premiummind)
package com.premiummind.app

import android.annotation.SuppressLint
import android.app.DownloadManager
import android.content.Context
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.util.Base64
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.WebChromeClient
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import java.io.File
import java.io.FileOutputStream

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView

    // Apni site ka URL. Chaho to netlify wala use kar sakte ho.
    private val startUrl = "https://premind.diplomawallah.in"

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // 🔒 Screenshot + screen recording block (normal screenshot -> black/blank)
        window.setFlags(
            android.view.WindowManager.LayoutParams.FLAG_SECURE,
            android.view.WindowManager.LayoutParams.FLAG_SECURE
        )

        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)

        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true              // localStorage/login ke liye
            databaseEnabled = true
            cacheMode = WebSettings.LOAD_DEFAULT
            loadWithOverviewMode = true
            useWideViewPort = true
            allowFileAccess = true
            mediaPlaybackRequiresUserGesture = false
            javaScriptCanOpenWindowsAutomatically = true
        }

        // Cookies (login sessions) enable
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        // Same site ke andar hi navigate ho, bahar ke links bhi WebView me hi khulein
        webView.webViewClient = WebViewClient()
        webView.webChromeClient = WebChromeClient()

        // 📥 PDF "Save" download bridge — blob: aur normal dono handle karta hai
        webView.addJavascriptInterface(DownloadBridge(), "AndroidDownloader")

        webView.setDownloadListener { url, _, _, mimeType, _ ->
            if (url.startsWith("blob:")) {
                // blob URL ko JS se base64 me convert karke bridge ko bhejo
                webView.evaluateJavascript(buildBlobReaderJs(url, mimeType), null)
            } else {
                // Normal http(s) download -> Android DownloadManager
                try {
                    val request = DownloadManager.Request(android.net.Uri.parse(url))
                    request.setNotificationVisibility(
                        DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED
                    )
                    request.setDestinationInExternalPublicDir(
                        Environment.DIRECTORY_DOWNLOADS,
                        "premium-mind-${System.currentTimeMillis()}.pdf"
                    )
                    val dm = getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
                    dm.enqueue(request)
                    Toast.makeText(this, "Download started…", Toast.LENGTH_SHORT).show()
                } catch (e: Exception) {
                    Toast.makeText(this, "Download failed", Toast.LENGTH_SHORT).show()
                }
            }
        }

        // Back button -> WebView history me peeche jao, warna app band
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) webView.goBack() else finish()
            }
        })

        webView.loadUrl(startUrl)
    }

    // JS: blob URL fetch -> FileReader -> base64 -> AndroidDownloader.saveBase64Pdf(...)
    private fun buildBlobReaderJs(blobUrl: String, mimeType: String): String {
        return """
            (function() {
              var xhr = new XMLHttpRequest();
              xhr.open('GET', '$blobUrl', true);
              xhr.responseType = 'blob';
              xhr.onload = function() {
                if (xhr.status === 200) {
                  var reader = new FileReader();
                  reader.onloadend = function() {
                    var base64 = reader.result.split(',')[1];
                    AndroidDownloader.saveBase64Pdf(base64, '$mimeType');
                  };
                  reader.readAsDataURL(xhr.response);
                }
              };
              xhr.send();
            })();
        """.trimIndent()
    }

    inner class DownloadBridge {
        @JavascriptInterface
        fun saveBase64Pdf(base64: String, mimeType: String) {
            try {
                val bytes = Base64.decode(base64, Base64.DEFAULT)
                val fileName = "premium-mind-${System.currentTimeMillis()}.pdf"

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    // Android 10+ : MediaStore (koi storage permission nahi chahiye)
                    val values = android.content.ContentValues().apply {
                        put(android.provider.MediaStore.Downloads.DISPLAY_NAME, fileName)
                        put(android.provider.MediaStore.Downloads.MIME_TYPE, "application/pdf")
                        put(android.provider.MediaStore.Downloads.IS_PENDING, 1)
                    }
                    val resolver = contentResolver
                    val uri = resolver.insert(
                        android.provider.MediaStore.Downloads.EXTERNAL_CONTENT_URI, values
                    )
                    if (uri != null) {
                        resolver.openOutputStream(uri)?.use { it.write(bytes) }
                        values.clear()
                        values.put(android.provider.MediaStore.Downloads.IS_PENDING, 0)
                        resolver.update(uri, values, null, null)
                    }
                } else {
                    // Purane Android : direct Downloads folder
                    val dir = Environment.getExternalStoragePublicDirectory(
                        Environment.DIRECTORY_DOWNLOADS
                    )
                    val file = File(dir, fileName)
                    FileOutputStream(file).use { it.write(bytes) }
                }

                runOnUiThread {
                    Toast.makeText(
                        this@MainActivity,
                        "Saved to Downloads: $fileName",
                        Toast.LENGTH_LONG
                    ).show()
                }
            } catch (e: Exception) {
                runOnUiThread {
                    Toast.makeText(this@MainActivity, "Save failed", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }
}
