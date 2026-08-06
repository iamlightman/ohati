package com.ohati.app;

import android.Manifest;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.pm.PackageManager;
import android.media.AudioAttributes;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.webkit.PermissionRequest;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebView;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import com.getcapacitor.BridgeActivity;
import java.util.ArrayList;
import java.util.List;

public class MainActivity extends BridgeActivity {
    private static final int PERMISSIONS_REQUEST_CODE = 1001;
    public static final String NOTIFICATION_CHANNEL_ID = "ohati_notifications";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        createNotificationChannel();
        requestAllRequiredPermissions();
    }

    @Override
    public void onStart() {
        super.onStart();
        setupWebViewPermissions();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                NOTIFICATION_CHANNEL_ID,
                "Ohati Notifications",
                NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription("Incoming messages, bookings, and alerts");
            channel.enableVibration(true);
            channel.enableLights(true);
            channel.setVibrationPattern(new long[]{0, 250, 250, 250});

            AudioAttributes audioAttributes = new AudioAttributes.Builder()
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                .build();
            channel.setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION), audioAttributes);

            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    private void requestAllRequiredPermissions() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            List<String> perms = new ArrayList<>();
            perms.add(Manifest.permission.RECORD_AUDIO);
            perms.add(Manifest.permission.MODIFY_AUDIO_SETTINGS);
            perms.add(Manifest.permission.CAMERA);

            if (Build.VERSION.SDK_INT >= 33) {
                perms.add(Manifest.permission.POST_NOTIFICATIONS);
                perms.add(Manifest.permission.READ_MEDIA_IMAGES);
                perms.add(Manifest.permission.READ_MEDIA_VIDEO);
                perms.add(Manifest.permission.READ_MEDIA_AUDIO);
            } else {
                perms.add(Manifest.permission.READ_EXTERNAL_STORAGE);
                if (Build.VERSION.SDK_INT <= 28) {
                    perms.add(Manifest.permission.WRITE_EXTERNAL_STORAGE);
                }
            }

            List<String> needed = new ArrayList<>();
            for (String p : perms) {
                if (ContextCompat.checkSelfPermission(this, p) != PackageManager.PERMISSION_GRANTED) {
                    needed.add(p);
                }
            }

            if (!needed.isEmpty()) {
                ActivityCompat.requestPermissions(this, needed.toArray(new String[0]), PERMISSIONS_REQUEST_CODE);
            }
        }
    }

    private void setupWebViewPermissions() {
        try {
            if (this.bridge != null && this.bridge.getWebView() != null) {
                final WebChromeClient defaultClient = this.bridge.getWebView().getWebChromeClient();
                this.bridge.getWebView().setWebChromeClient(new WebChromeClient() {
                    @Override
                    public void onPermissionRequest(final PermissionRequest request) {
                        runOnUiThread(new Runnable() {
                            @Override
                            public void run() {
                                try {
                                    request.grant(request.getResources());
                                } catch (Exception e) {
                                    e.printStackTrace();
                                }
                            }
                        });
                    }

                    @Override
                    public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams fileChooserParams) {
                        if (defaultClient != null) {
                            return defaultClient.onShowFileChooser(webView, filePathCallback, fileChooserParams);
                        }
                        return super.onShowFileChooser(webView, filePathCallback, fileChooserParams);
                    }
                });
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
