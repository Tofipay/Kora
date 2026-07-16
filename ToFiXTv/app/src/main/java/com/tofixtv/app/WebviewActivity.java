package com.tofixtv.app;

import android.animation.*;
import android.app.*;
import android.content.*;
import android.content.Intent;
import android.content.res.*;
import android.graphics.*;
import android.graphics.drawable.*;
import android.media.*;
import android.net.*;
import android.net.Uri;
import android.os.*;
import android.os.Bundle;
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
import android.widget.LinearLayout;
import android.widget.ProgressBar;
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
import java.io.InputStream;
import java.text.*;
import java.util.*;
import java.util.regex.*;
import meorg.jsoup.*;
import org.json.*;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.content.pm.ActivityInfo;

public class WebviewActivity extends AppCompatActivity {
	
	private String url = "";
	View customView;
	WebChromeClient.CustomViewCallback customViewCallback;
	
	private LinearLayout progressbar1;
	private WebView webview1;
	private FrameLayout fullScreenContainer;
	private WebView webview2;
	private ProgressBar progressbar;
	
	private Intent i = new Intent();
	
	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		setContentView(R.layout.webview);
		initialize(_savedInstanceState);
		FirebaseApp.initializeApp(this);
		MobileAds.initialize(this);
		
		initializeLogic();
	}
	
	private void initialize(Bundle _savedInstanceState) {
		progressbar1 = findViewById(R.id.progressbar1);
		webview1 = findViewById(R.id.webview1);
		webview1.getSettings().setJavaScriptEnabled(true);
		webview1.getSettings().setSupportZoom(true);
		fullScreenContainer = findViewById(R.id.fullScreenContainer);
		webview2 = findViewById(R.id.webview2);
		webview2.getSettings().setJavaScriptEnabled(true);
		webview2.getSettings().setSupportZoom(true);
		progressbar = findViewById(R.id.progressbar);
		
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
		
		//webviewOnProgressChanged
		webview2.setWebChromeClient(new WebChromeClient() {
				@Override public void onProgressChanged(WebView view, int _newProgress) {
					
				}
		});
		
		//OnDownloadStarted
		webview2.setDownloadListener(new DownloadListener() {
			public void onDownloadStart(String url, String userAgent, String contentDisposition, String mimetype, long contentLength) {
				DownloadManager.Request webview2a = new DownloadManager.Request(Uri.parse(url));
				String webview2b = CookieManager.getInstance().getCookie(url);
				webview2a.addRequestHeader("cookie", webview2b);
				webview2a.addRequestHeader("User-Agent", userAgent);
				webview2a.setDescription("Downloading file...");
				webview2a.setTitle(URLUtil.guessFileName(url, contentDisposition, mimetype));
				webview2a.allowScanningByMediaScanner(); webview2a.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED); webview2a.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, URLUtil.guessFileName(url, contentDisposition, mimetype));
				
				DownloadManager webview2c = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
				webview2c.enqueue(webview2a);
				showMessage("Downloading File....");
				BroadcastReceiver onComplete = new BroadcastReceiver() {
					public void onReceive(Context ctxt, Intent intent) {
						showMessage("Download Complete!");
						unregisterReceiver(this);
						
					}};
				registerReceiver(onComplete, new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE));
			}
		});
		
		webview2.setWebViewClient(new WebViewClient() {
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
		setTitle(getIntent().getStringExtra("txt"));
		url = getIntent().getStringExtra("url");
		webview1.setWebViewClient(new WebViewClient() {
						@Override
						public void onPageStarted(WebView _param1, String _param2, Bitmap _param3) {
								final String _url = _param2;
								
								super.onPageStarted(_param1, _param2, _param3);
						}
						
						@Override
						public void onPageFinished(WebView _param1, String _param2) {
								final String _url = _param2;
								progressbar1.setVisibility(View.GONE);
							//	webview1.setVisibility(View.GONE);
								super.onPageFinished(_param1, _param2);
						}
			                    @Override
			        public boolean shouldOverrideUrlLoading(WebView webview, WebResourceRequest request) {
				            if( URLUtil.isNetworkUrl(webview.getUrl()) ) {
					                return false;
					            }
				             try {
					                    Intent inent = new Intent(Intent.ACTION_VIEW, Uri.parse(webview.getUrl()));
					                    startActivity(inent);
					                } catch (Exception e) {                  
					              }
				            return true;
				            }
				});
			
		webview1.setWebChromeClient(new WebChromeClient() {
			
			            @Override
			            public void onShowCustomView(View view, CustomViewCallback callback) {
				                if (customView != null) {
					                    callback.onCustomViewHidden();
					                    return;
					                }
				
				                customView = view;
				                if (getSupportActionBar() != null) {
					        getSupportActionBar().hide();
					    }
				         webview1.setVisibility(View.GONE); fullScreenContainer.addView(customView);
				                fullScreenContainer.setVisibility(View.VISIBLE);
				                customViewCallback = callback;
				            }
			
			            @Override
			            public void onHideCustomView() {
				                webview1.setVisibility(View.VISIBLE);
				                if (getSupportActionBar() != null) {
					        getSupportActionBar().show();
					    }
				                if (customView == null) {
					                    return;
					                }
				                 
				                fullScreenContainer.setVisibility(View.GONE);
				                fullScreenContainer.removeView(customView);
				                customView = null;
				                customViewCallback.onCustomViewHidden();
				            }
			        });
		webview1.loadUrl(url);
		if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
			    WindowManager.LayoutParams layoutParams = getWindow().getAttributes();
			    layoutParams.layoutInDisplayCutoutMode = WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES;
			    getWindow().setAttributes(layoutParams);
		}
		
		View decorView = getWindow().getDecorView();
		decorView.setSystemUiVisibility(
		    View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
		    | View.SYSTEM_UI_FLAG_FULLSCREEN
		    | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
		    | View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
		    | View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
		    | View.SYSTEM_UI_FLAG_LAYOUT_STABLE);
		setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE);
		ProgressBar progressBar = findViewById(R.id.progressbar);
		
		// تغيير لون الـ Indeterminate Drawable إذا كان الدائري في وضع indeterminate
		progressBar.getIndeterminateDrawable().setColorFilter(
		    Color.parseColor("#FFB300"), 
		    android.graphics.PorterDuff.Mode.SRC_IN);
	}
	
	@Override
	public void onBackPressed() {
		if (customView != null) {
			            webview1.getWebChromeClient().onHideCustomView();
			        } else if (webview1.canGoBack()) {
			            webview1.goBack();
			        } else {
			            super.onBackPressed();
			        }
	}
	
	
	@Override
	public void onResume() {
		super.onResume();
		webview2.getSettings().setJavaScriptEnabled(true);
		
		webview2.setWebViewClient(new WebViewClient() {
			
			    public boolean shouldOverrideUrlLoading(WebView view, String url) {
				
				        if (url.startsWith("https://wa.me/") || url.startsWith("https://api.whatsapp.com/")) {
					
					            try {
						
						                Intent intent = new Intent(Intent.ACTION_VIEW);
						
						                intent.setData(Uri.parse(url));
						
						                startActivity(intent);
						
						                return true;
						
						            } catch (ActivityNotFoundException e) {
						
						                e.printStackTrace();
						
						            }
					
					        } else if (url.startsWith("https://www.youtube.com/")) {
					
					            try {
						
						                Intent intent = new Intent(Intent.ACTION_VIEW);
						
						                intent.setData(Uri.parse(url));
						
						                startActivity(intent);
						
						                return true;
						
						            } catch (ActivityNotFoundException e) {
						
						                e.printStackTrace();
						
						            }
					
					        } else if (url.startsWith("https://control2.com")) { // الانتقال إلى AppActivity
					
					            try {
						
						                i.setClass(getApplicationContext(), AppActivity.class);
						                startActivity(i);
						                finish();
						
						                return true;
						
						            } catch (ActivityNotFoundException e) {
						
						                e.printStackTrace();
						
						            }
					
					        } else if (url.startsWith("https://control3.com")) {
					
					            try {
						
						                finishAffinity();
						
						                return true;
						
						            } catch (ActivityNotFoundException e) {
						
						                e.printStackTrace();
						
						            }
					
					        }
				
				        return false;
				
				    }
			
		});
		webview2.loadUrl("https://controller-gfx.blogspot.com/p/tofi-player-v2.html");
	}
	
	@Override
	protected void onPostCreate(Bundle _savedInstanceState) {
		super.onPostCreate(_savedInstanceState);
		
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