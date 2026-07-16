package com.tofixtv.app;

import android.animation.*;
import android.app.*;
import android.content.*;
import android.content.res.*;
import android.graphics.*;
import android.graphics.drawable.*;
import android.media.*;
import android.net.*;
import android.os.*;
import android.text.*;
import android.text.style.*;
import android.util.*;
import android.view.*;
import android.view.View.*;
import android.view.animation.*;
import android.webkit.*;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.widget.*;
import androidx.annotation.*;
import androidx.annotation.experimental.*;
import androidx.appcompat.app.AppCompatActivity;
import androidx.fragment.app.DialogFragment;
import androidx.fragment.app.Fragment;
import androidx.fragment.app.FragmentManager;
import androidx.media.*;
import androidx.media3.common.*;
import androidx.media3.database.*;
import androidx.media3.datasource.*;
import androidx.media3.decoder.*;
import androidx.media3.exoplayer.*;
import androidx.media3.exoplayer.dash.*;
import androidx.media3.exoplayer.hls.*;
import androidx.media3.exoplayer.rtsp.*;
import androidx.media3.exoplayer.smoothstreaming.*;
import androidx.media3.extractor.*;
import androidx.media3.session.*;
import androidx.media3.ui.*;
import androidx.privacysandbox.ads.adservices.*;
import androidx.privacysandbox.ads.adservices.java.*;
import com.blogspot.atifsoftwares.animatoolib.*;
import com.google.android.gms.ads.*;
import com.google.android.gms.ads.MobileAds;
import com.google.android.gms.ads.impl.*;
import com.google.android.gms.base.*;
import com.google.android.gms.common.*;
import com.google.firebase.FirebaseApp;
import java.io.*;
import java.text.*;
import java.util.*;
import java.util.regex.*;
import meorg.jsoup.*;
import org.json.*;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebResourceRequest;
import android.webkit.CookieManager;
import android.webkit.PermissionRequest;
import android.webkit.GeolocationPermissions;
import android.os.Build;
import android.os.Message;
import android.view.View;
import android.widget.FrameLayout;
import android.app.Dialog;
import android.content.Intent;
import android.content.pm.ActivityInfo;
import android.graphics.Bitmap;
import android.net.Uri;

public class TofiAppActivity extends AppCompatActivity {
	
	private WebView webview1;
	
	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		setContentView(R.layout.tofi_app);
		initialize(_savedInstanceState);
		FirebaseApp.initializeApp(this);
		MobileAds.initialize(this);
		
		initializeLogic();
	}
	
	private void initialize(Bundle _savedInstanceState) {
		webview1 = findViewById(R.id.webview1);
		webview1.getSettings().setJavaScriptEnabled(true);
		webview1.getSettings().setSupportZoom(true);
		
		//webviewOnProgressChanged
		webview1.setWebChromeClient(new WebChromeClient() {
				@Override public void onProgressChanged(WebView view, int _newProgress) {
					
				}
		});
		
		//OnDownloadStarted
		webview1.setDownloadListener(new DownloadListener() {
			public void onDownloadStart(String url, String userAgent, String contentDisposition, String mimetype, long contentLength) {
				DownloadManager.Request webview1a = new DownloadManager.Request(Uri.parse(url));
				String webview1b = CookieManager.getInstance().getCookie(url);
				webview1a.addRequestHeader("cookie", webview1b);
				webview1a.addRequestHeader("User-Agent", userAgent);
				webview1a.setDescription("Downloading file...");
				webview1a.setTitle(URLUtil.guessFileName(url, contentDisposition, mimetype));
				webview1a.allowScanningByMediaScanner(); webview1a.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED); webview1a.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, URLUtil.guessFileName(url, contentDisposition, mimetype));
				
				DownloadManager webview1c = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
				webview1c.enqueue(webview1a);
				showMessage("Downloading File....");
				BroadcastReceiver onComplete = new BroadcastReceiver() {
					public void onReceive(Context ctxt, Intent intent) {
						showMessage("Download Complete!");
						unregisterReceiver(this);
						
					}};
				registerReceiver(onComplete, new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE));
			}
		});
		
		webview1.setWebViewClient(new WebViewClient() {
			@Override
			public void onPageStarted(WebView _param1, String _param2, Bitmap _param3) {
				final String _url = _param2;
				
				super.onPageStarted(_param1, _param2, _param3);
			}
			
			@Override
			public void onPageFinished(WebView _param1, String _param2) {
				final String _url = _param2;
				
				super.onPageFinished(_param1, _param2);
			}
		});
	}
	
	private void initializeLogic() {
		final View[] fsView = new View[1];
		final WebChromeClient.CustomViewCallback[] fsCallback = new WebChromeClient.CustomViewCallback[1];
		final int[] fsOldUi = new int[1];
		final int[] fsOldOrient = new int[1];
		
		final String notifShim = "(function(){" +
		"if(window.__notifShimDone)return;window.__notifShimDone=true;" +
		"var g=function(){try{return AndroidNotify.isGranted()?'granted':'default';}catch(e){return 'default';}};" +
		"function N(t,o){o=o||{};try{AndroidNotify.showNotification(String(t),String(o.body||''));}catch(e){}}" +
		"N.permission=g();" +
		"N.requestPermission=function(cb){try{AndroidNotify.askPermission();}catch(e){}return new Promise(function(res){var n=0;var iv=setInterval(function(){n++;var p=g();if(p==='granted'||n>=20){clearInterval(iv);N.permission=p;if(cb){try{cb(p);}catch(e){}}res(p);}},500);});};" +
		"window.Notification=N;" +
		"function rb(n,f){var a=new Uint8Array(n);try{crypto.getRandomValues(a);}catch(e){for(var i=0;i<n;i++)a[i]=Math.floor(Math.random()*256);}if(f)a[0]=f;return a;}" +
		"function b64u(a){var s='';for(var i=0;i<a.length;i++)s+=String.fromCharCode(a[i]);return btoa(s).replace(/\\+/g,'-').replace(/\\//g,'_').replace(/=+$/,'');}" +
		"function ub64(s){s=s.replace(/-/g,'+').replace(/_/g,'/');while(s.length%4)s+='=';var b=atob(s);var a=new Uint8Array(b.length);for(var i=0;i<b.length;i++)a[i]=b.charCodeAt(i);return a.buffer;}" +
		"function makeSub(){var d=null;try{d=JSON.parse(localStorage.getItem('__wvPushSub'));}catch(e){}" +
		"if(!d){d={endpoint:'https://webview.push.local/'+b64u(rb(16)),p256dh:b64u(rb(65,4)),auth:b64u(rb(16))};try{localStorage.setItem('__wvPushSub',JSON.stringify(d));}catch(e){}}" +
		"return {endpoint:d.endpoint,expirationTime:null,options:{userVisibleOnly:true,applicationServerKey:null}," +
		"getKey:function(k){if(k==='p256dh')return ub64(d.p256dh);if(k==='auth')return ub64(d.auth);return null;}," +
		"toJSON:function(){return {endpoint:d.endpoint,expirationTime:null,keys:{p256dh:d.p256dh,auth:d.auth}};}," +
		"unsubscribe:function(){try{localStorage.removeItem('__wvPushSub');}catch(e){}return Promise.resolve(true);}};}" +
		"var PM={" +
		"getSubscription:function(){try{return Promise.resolve(localStorage.getItem('__wvPushSub')?makeSub():null);}catch(e){return Promise.resolve(null);}}," +
		"subscribe:function(o){try{AndroidNotify.askPermission();}catch(e){}return Promise.resolve(makeSub());}," +
		"permissionState:function(){return Promise.resolve(g()==='granted'?'granted':'prompt');}};" +
		"if(!window.PushManager){window.PushManager=function(){};window.PushManager.supportedContentEncodings=['aes128gcm'];}" +
		"if(window.ServiceWorkerRegistration){try{var pr=ServiceWorkerRegistration.prototype;" +
		"if(!('pushManager' in pr)){Object.defineProperty(pr,'pushManager',{get:function(){return PM;},configurable:true});}}catch(e){}}" +
		"if(navigator.serviceWorker){" +
		"var patch=function(r){try{if(r&&!r.pushManager){Object.defineProperty(r,'pushManager',{value:PM,configurable:true});}}catch(e){}return r;};" +
		"try{var oreg=navigator.serviceWorker.register.bind(navigator.serviceWorker);navigator.serviceWorker.register=function(){return oreg.apply(null,arguments).then(patch);};}catch(e){}" +
		"try{var ogr=navigator.serviceWorker.getRegistration.bind(navigator.serviceWorker);navigator.serviceWorker.getRegistration=function(){return ogr.apply(null,arguments).then(patch);};}catch(e){}" +
		"try{var ogrs=navigator.serviceWorker.getRegistrations.bind(navigator.serviceWorker);navigator.serviceWorker.getRegistrations=function(){return ogrs.apply(null,arguments).then(function(l){return l.map(patch);});};}catch(e){}" +
		"try{navigator.serviceWorker.ready.then(patch);}catch(e){}}" +
		"})();";
		
		WebSettings ws = webview1.getSettings();
		
		ws.setUserAgentString("com.tofixtv.app");
		
		ws.setJavaScriptEnabled(true);
		ws.setDomStorageEnabled(true);
		ws.setDatabaseEnabled(true);
		ws.setAllowFileAccess(true);
		ws.setAllowContentAccess(true);
		ws.setJavaScriptCanOpenWindowsAutomatically(false);
		ws.setSupportMultipleWindows(false);
		ws.setLoadsImagesAutomatically(true);
		ws.setMediaPlaybackRequiresUserGesture(false);
		ws.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
		ws.setUseWideViewPort(true);
		ws.setLoadWithOverviewMode(true);
		ws.setSupportZoom(true);
		ws.setBuiltInZoomControls(true);
		ws.setDisplayZoomControls(false);
		ws.setGeolocationEnabled(true);
		ws.setCacheMode(WebSettings.LOAD_DEFAULT);
			webview1.setLongClickable(false);
			webview1.setHapticFeedbackEnabled(false);
		
		
		CookieManager cookieManager = CookieManager.getInstance();
		cookieManager.setAcceptCookie(true);
		cookieManager.setAcceptThirdPartyCookies(webview1, true);
		
		webview1.addJavascriptInterface(new Object() {
				
				    @android.webkit.JavascriptInterface
				    public void askPermission() {
						        runOnUiThread(new Runnable() {
								            @Override
								            public void run() {
										                if (Build.VERSION.SDK_INT >= 33) {
												                    if (checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS)
												                            != android.content.pm.PackageManager.PERMISSION_GRANTED) {
														                        requestPermissions(new String[]{ android.Manifest.permission.POST_NOTIFICATIONS }, 5000);
														                    }
												                } else {
												                    if (!androidx.core.app.NotificationManagerCompat.from(getApplicationContext()).areNotificationsEnabled()) {
														                        try {
																                            android.content.Intent it = new android.content.Intent(android.provider.Settings.ACTION_APP_NOTIFICATION_SETTINGS);
																                            it.putExtra(android.provider.Settings.EXTRA_APP_PACKAGE, getPackageName());
																                            startActivity(it);
																                        } catch (Exception e) {}
														                    }
												                }
										            }
								        });
						    }
				
				    @android.webkit.JavascriptInterface
				    public boolean isGranted() {
						        return androidx.core.app.NotificationManagerCompat.from(getApplicationContext()).areNotificationsEnabled();
						    }
				
				    @android.webkit.JavascriptInterface
				    public void showNotification(final String title, final String body) {
						        try {
								            android.app.NotificationManager nm = (android.app.NotificationManager) getSystemService(NOTIFICATION_SERVICE);
								            if (Build.VERSION.SDK_INT >= 26) {
										                android.app.NotificationChannel ch = new android.app.NotificationChannel("web_notif", "Site Notifications", android.app.NotificationManager.IMPORTANCE_HIGH);
										                nm.createNotificationChannel(ch);
										            }
								            androidx.core.app.NotificationCompat.Builder b = new androidx.core.app.NotificationCompat.Builder(getApplicationContext(), "web_notif")
								                    .setSmallIcon(android.R.drawable.ic_dialog_info)
								                    .setContentTitle(title)
								                    .setContentText(body)
								                    .setAutoCancel(true);
								            nm.notify((int)(System.currentTimeMillis() % 100000), b.build());
								        } catch (Exception e) {}
						    }
		}, "AndroidNotify");
		
		webview1.setWebChromeClient(new WebChromeClient() {
				
				    @Override
				    public void onShowCustomView(View view, CustomViewCallback callback) {
						        if (fsView[0] != null) {
								            callback.onCustomViewHidden();
								            return;
								        }
						        fsView[0] = view;
						        fsCallback[0] = callback;
						        fsOldUi[0] = getWindow().getDecorView().getSystemUiVisibility();
						        fsOldOrient[0] = getRequestedOrientation();
						
						        android.widget.FrameLayout decor = (android.widget.FrameLayout) getWindow().getDecorView();
						        decor.addView(view, new android.widget.FrameLayout.LayoutParams(-1, -1));
						
						        getWindow().getDecorView().setSystemUiVisibility(
						                View.SYSTEM_UI_FLAG_FULLSCREEN
						                | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
						                | View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
						                | View.SYSTEM_UI_FLAG_LAYOUT_STABLE
						                | View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
						                | View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION);
						
						        setRequestedOrientation(android.content.pm.ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE);
						    }
				
				    @Override
				    public void onHideCustomView() {
						        if (fsView[0] == null) return;
						        android.widget.FrameLayout decor = (android.widget.FrameLayout) getWindow().getDecorView();
						        decor.removeView(fsView[0]);
						        fsView[0] = null;
						        getWindow().getDecorView().setSystemUiVisibility(fsOldUi[0]);
						        setRequestedOrientation(fsOldOrient[0]);
						        if (fsCallback[0] != null) {
								            try { fsCallback[0].onCustomViewHidden(); } catch (Exception e) {}
								            fsCallback[0] = null;
								        }
						    }
				
				    @Override
				    public boolean onCreateWindow(WebView view, boolean isDialog, boolean isUserGesture, android.os.Message resultMsg) {
						        // تجاهل كل النوافذ الجديدة تماماً
						        return false;
						    }
				
				    @Override
				    public void onPermissionRequest(final android.webkit.PermissionRequest request) {
						        request.grant(request.getResources());
						    }
				
				    @Override
				    public void onGeolocationPermissionsShowPrompt(String origin, android.webkit.GeolocationPermissions.Callback callback) {
						        callback.invoke(origin, true, false);
						    }
		});
		
		// تم دمج هذا الجزء مع WebViewClient النهائي في الأسفل لضمان عدم وجود تكرار وإيقاف الإعلانات بفعالية
		
		if (Build.VERSION.SDK_INT >= 33) {
				    if (checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS)
				            != android.content.pm.PackageManager.PERMISSION_GRANTED) {
						        requestPermissions(
						                new String[]{ android.Manifest.permission.POST_NOTIFICATIONS },
						                5000
						        );
						    }
		}
		
		webview1.loadUrl("https://test.tofi-xtv.com");
		webview1.getSettings().setJavaScriptEnabled(true);
		webview1.getSettings().setUserAgentString("com.tofixtv.app");
		// الجسر بين JavaScript والتطبيق
		webview1.addJavascriptInterface(new Object() {
				
				    @android.webkit.JavascriptInterface
				    public void openPlayer(final String token) {
						
						        runOnUiThread(new Runnable() {
								            @Override
								            public void run() {
										
										                Intent i = new Intent();
										                i.setComponent(new ComponentName(
										                        "com.tofixtv.app",
										                        "com.tofixtv.app.MainActivity"
										                ));
										
										                i.setAction("open_app");
										                i.putExtra("data", token);
										                i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
										
										                try {
												                    startActivity(i);
												                } catch (Exception e) {
												                    Toast.makeText(
												                            getApplicationContext(),
												                            "المشغّل غير مثبت",
												                            Toast.LENGTH_SHORT
												                    ).show();
												                }
										
										            }
								        });
						
						    }
				
		}, "TofiXTv");
		
		webview1.setWebViewClient(new WebViewClient() {
			    private boolean isAllowed(String url) {
				        return url.startsWith("https://test.tofi-xtv.com") ||
				               url.startsWith("http://test.tofi-xtv.com") ||
				               url.startsWith("https://www.youtube.com") ||
				               url.startsWith("https://m.youtube.com") ||
				               url.startsWith("https://youtu.be/") ||
				               url.startsWith("https://www.fifa.com") ||
				               url.startsWith("intent://") || 
				               url.startsWith("xmtv://");
				    }
			
			    @Override
			    public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
				        return handleUrl(view, request.getUrl().toString());
				    }
			
			    @Override
			    public boolean shouldOverrideUrlLoading(WebView view, String url) {
				        return handleUrl(view, url);
				    }
			
			    private boolean handleUrl(WebView view, String url) {
				        if (!isAllowed(url)) {
					            // منع أي رابط خارجي غير مصرح به (الإعلانات)
					            return true; 
					        }
				
				        if (url.startsWith("intent://") || url.startsWith("xmtv://")) {
					            try {
						                Intent i;
						                if (url.startsWith("intent://")) {
							                    i = Intent.parseUri(url, Intent.URI_INTENT_SCHEME);
							                } else {
							                    i = new Intent();
							                    i.setAction("open_app");
							                    i.setData(Uri.parse(url));
							                }
						                i.setComponent(new ComponentName("com.tofixtv.app", "com.tofixtv.app.MainActivity"));
						                i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
						                startActivity(i);
						            } catch (Exception e) {
						                Toast.makeText(getApplicationContext(), "المشغّل غير مثبت", Toast.LENGTH_SHORT).show();
						            }
					            return true;
					        }
				        
				        // السماح بالروابط داخل الموقع أو يوتيوب/فيفا
				        return false;
				    }
			
			    @Override
			    public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
				        super.onPageStarted(view, url, favicon);
				        view.evaluateJavascript(notifShim, null);
				    }
			
			    @Override
			    public void onPageFinished(WebView view, String url) {
				        super.onPageFinished(view, url);
				        view.evaluateJavascript(notifShim, null);
				        CookieManager.getInstance().flush();
				    }
		});
	}
	
	@Override
	public void onBackPressed() {
		if (webview1.canGoBack()) {
			    webview1.goBack();
		} else {
			    finish();
		}
	}
	
	
	@Deprecated
	public void showMessage(String _s) {
		Toast.makeText(getApplicationContext(), _s, Toast.LENGTH_SHORT).show();
	}
	
	@Deprecated
	public int getLocationX(View _v) {
		int _location[] = new int[2];
		_v.getLocationInWindow(_location);
		return _location[0];
	}
	
	@Deprecated
	public int getLocationY(View _v) {
		int _location[] = new int[2];
		_v.getLocationInWindow(_location);
		return _location[1];
	}
	
	@Deprecated
	public int getRandom(int _min, int _max) {
		Random random = new Random();
		return random.nextInt(_max - _min + 1) + _min;
	}
	
	@Deprecated
	public ArrayList<Double> getCheckedItemPositionsToArray(ListView _list) {
		ArrayList<Double> _result = new ArrayList<Double>();
		SparseBooleanArray _arr = _list.getCheckedItemPositions();
		for (int _iIdx = 0; _iIdx < _arr.size(); _iIdx++) {
			if (_arr.valueAt(_iIdx))
			_result.add((double)_arr.keyAt(_iIdx));
		}
		return _result;
	}
	
	@Deprecated
	public float getDip(int _input) {
		return TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, _input, getResources().getDisplayMetrics());
	}
	
	@Deprecated
	public int getDisplayWidthPixels() {
		return getResources().getDisplayMetrics().widthPixels;
	}
	
	@Deprecated
	public int getDisplayHeightPixels() {
		return getResources().getDisplayMetrics().heightPixels;
	}
}