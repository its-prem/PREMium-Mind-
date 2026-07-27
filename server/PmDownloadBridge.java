/**
 * OPTIONAL — add this to your Android WebView / Capacitor MainActivity
 * so in-app APK download works with real DownloadManager (.apk, not .pdf).
 *
 * Expose as: webView.addJavascriptInterface(new PmDownloadBridge(this), "Android");
 *
 * JS already calls: window.Android.downloadUrl(url, "PREMium-Mind.apk")
 */
package in.diplomawallah.premind; // change to your package

import android.app.DownloadManager;
import android.content.Context;
import android.net.Uri;
import android.os.Environment;
import android.webkit.JavascriptInterface;
import android.widget.Toast;

public class PmDownloadBridge {
    private final Context context;

    public PmDownloadBridge(Context context) {
        this.context = context.getApplicationContext();
    }

    @JavascriptInterface
    public void downloadUrl(String url, String fileName) {
        if (url == null || url.trim().isEmpty()) return;
        if (fileName == null || fileName.trim().isEmpty()) fileName = "PREMium-Mind.apk";
        if (!fileName.toLowerCase().endsWith(".apk")) {
            fileName = fileName.replaceAll("\\.[^.]+$", "") + ".apk";
        }
        try {
            DownloadManager.Request req = new DownloadManager.Request(Uri.parse(url));
            req.setTitle(fileName);
            req.setDescription("PREMium Mind update");
            req.setMimeType("application/vnd.android.package-archive");
            req.setNotificationVisibility(
                DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED
            );
            req.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, fileName);
            req.allowScanningByMediaScanner();
            DownloadManager dm = (DownloadManager) context.getSystemService(Context.DOWNLOAD_SERVICE);
            if (dm != null) {
                dm.enqueue(req);
                Toast.makeText(context, "Downloading " + fileName, Toast.LENGTH_SHORT).show();
            }
        } catch (Exception e) {
            Toast.makeText(context, "Download failed: " + e.getMessage(), Toast.LENGTH_LONG).show();
        }
    }

    @JavascriptInterface
    public void downloadApk(String url, String fileName) {
        downloadUrl(url, fileName);
    }

    @JavascriptInterface
    public void openUrl(String url) {
        if (url == null || url.trim().isEmpty()) return;
        try {
            android.content.Intent i = new android.content.Intent(
                android.content.Intent.ACTION_VIEW,
                Uri.parse(url)
            );
            i.addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK);
            context.startActivity(i);
        } catch (Exception ignored) {}
    }
}
