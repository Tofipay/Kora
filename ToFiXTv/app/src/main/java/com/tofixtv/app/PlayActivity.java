package com.tofixtv.app;

import android.animation.*;
import android.app.*;
import android.app.Activity;
import android.content.*;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.res.*;
import android.graphics.*;
import android.graphics.Typeface;
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
import android.view.View;
import android.view.View.*;
import android.view.animation.*;
import android.webkit.*;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.widget.*;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;
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
import androidx.recyclerview.widget.*;
import androidx.recyclerview.widget.RecyclerView;
import androidx.recyclerview.widget.RecyclerView.Adapter;
import androidx.recyclerview.widget.RecyclerView.ViewHolder;
import com.blogspot.atifsoftwares.animatoolib.*;
import com.bumptech.glide.Glide;
import com.google.android.gms.ads.*;
import com.google.android.gms.ads.AdError;
import com.google.android.gms.ads.AdRequest;
import com.google.android.gms.ads.FullScreenContentCallback;
import com.google.android.gms.ads.LoadAdError;
import com.google.android.gms.ads.MobileAds;
import com.google.android.gms.ads.impl.*;
import com.google.android.gms.ads.interstitial.InterstitialAd;
import com.google.android.gms.ads.interstitial.InterstitialAdLoadCallback;
import com.google.android.gms.base.*;
import com.google.android.gms.common.*;
import com.google.firebase.FirebaseApp;
import java.io.*;
import java.io.InputStream;
import java.text.*;
import java.util.*;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.Timer;
import java.util.TimerTask;
import java.util.regex.*;
import meorg.jsoup.*;
import org.json.*;
import android.content.pm.ActivityInfo;
import android.content.res.TypedArray;
import android.media.MediaPlayer;
import android.net.Uri;
import android.util.AttributeSet;
import android.util.Base64;
import androidx.annotation.IntDef;
import androidx.annotation.Nullable;


import com.google.common.collect.ImmutableList;

import java.io.IOException;
import java.lang.annotation.Documented;
import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;
import java.lang.annotation.Target;
import java.lang.reflect.Constructor;
import java.net.CookieHandler;

import java.net.CookiePolicy;
import java.net.NetworkInterface;
import java.net.SocketException;
import java.security.InvalidAlgorithmParameterException;
import java.security.InvalidKeyException;
import java.security.Key;
import java.security.NoSuchAlgorithmException;
import java.security.spec.AlgorithmParameterSpec;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import android.webkit.CookieManager;
import javax.crypto.Cipher;
import javax.crypto.CipherInputStream;
import javax.crypto.NoSuchPaddingException;
import javax.crypto.spec.IvParameterSpec;
import javax.crypto.spec.SecretKeySpec;

import static java.lang.annotation.ElementType.TYPE_USE;
import androidx.media3.exoplayer.trackselection.DefaultTrackSelector;
import androidx.media3.exoplayer.trackselection.TrackSelector;
import androidx.media3.session.MediaSession;
import androidx.media3.session.MediaSession.Builder;
import androidx.media3.ui.AspectRatioFrameLayout;
import androidx.media3.ui.PlayerView;
import androidx.media3.ui.PlayerNotificationManager;

import androidx.media3.ui.TrackSelectionDialogBuilder;
import android.media.AudioFocusRequest;
import android.media.AudioAttributes;
import android.media.AudioManager;
import android.provider.Settings;
import  android.app.PictureInPictureParams; 
import android.content.res.Configuration; 
import android.graphics.Point; 
import android.util.Rational;
import androidx.lifecycle.Lifecycle;

import androidx.media3.common.C;
import androidx.media3.common.ErrorMessageProvider;
import androidx.media3.common.MediaItem;
import androidx.media3.common.PlaybackException;
import androidx.media3.common.Player;
import androidx.media3.common.TrackSelectionParameters;
import androidx.media3.common.Tracks;
import androidx.media3.common.util.UnstableApi;
import androidx.media3.common.util.Util;
import androidx.media3.datasource.DataSource;
import androidx.media3.exoplayer.ExoPlayer;
import androidx.media3.exoplayer.RenderersFactory;
import androidx.media3.exoplayer.drm.DefaultDrmSessionManagerProvider;
import androidx.media3.exoplayer.drm.FrameworkMediaDrm;
import androidx.media3.exoplayer.mediacodec.MediaCodecRenderer.DecoderInitializationException;
import androidx.media3.exoplayer.mediacodec.MediaCodecUtil.DecoderQueryException;
import androidx.media3.exoplayer.drm.LocalMediaDrmCallback;
import androidx.media3.exoplayer.drm.DefaultDrmSessionManager;
import androidx.media3.exoplayer.drm.DrmSessionManager;
import androidx.media3.exoplayer.source.DefaultMediaSourceFactory;
import androidx.media3.exoplayer.source.MediaSource;

import androidx.media3.common.Timeline;
import androidx.media3.common.MediaMetadata;
import androidx.media3.common.TrackSelectionParameters;
import androidx.media3.common.Tracks;
import androidx.media3.common.VideoSize;

import  androidx.media3.common.text.Cue;
import androidx.media3.exoplayer.source.ProgressiveMediaSource;
import java.net.URL;
import java.net.MalformedURLException;
import java.nio.charset.StandardCharsets;
import com.google.android.gms.ads.MobileAds;
import com.google.android.gms.ads.initialization.InitializationStatus;
import com.google.android.gms.ads.initialization.OnInitializationCompleteListener;
import com.google.android.gms.ads.AdError;
import com.google.android.gms.ads.AdListener;
import com.google.android.gms.ads.AdLoader;
import com.google.android.gms.ads.AdRequest;
import com.google.android.gms.ads.AdValue;
import com.google.android.gms.ads.AdView;
import com.google.android.gms.ads.FullScreenContentCallback;
import com.google.android.gms.ads.LoadAdError;
import com.google.android.gms.ads.OnPaidEventListener;
import com.google.android.gms.ads.OnUserEarnedRewardListener;
import com.google.android.gms.ads.interstitial.InterstitialAd;
import com.google.android.gms.ads.interstitial.InterstitialAdLoadCallback;
import com.google.android.gms.ads.nativead.MediaView;
import com.google.android.gms.ads.nativead.NativeAd;
import com.google.android.gms.ads.nativead.NativeAdOptions;
import com.google.android.gms.ads.nativead.NativeAdView;
import com.google.android.gms.ads.rewarded.OnAdMetadataChangedListener;
import com.google.android.gms.ads.rewarded.RewardItem;
import com.google.android.gms.ads.rewarded.RewardedAd;
import com.google.android.gms.ads.rewarded.RewardedAdLoadCallback;
import com.google.android.gms.ads.RequestConfiguration;
import com.google.android.gms.ads.AdSize;
import com.google.android.gms.ads.AdView;
import android.widget.LinearLayout.LayoutParams;
import androidx.annotation.Nullable;
import java.net.URLDecoder;
import java.io.UnsupportedEncodingException;
import java.nio.charset.StandardCharsets;
import okhttp3.*;
import android.graphics.drawable.GradientDrawable;
import android.graphics.drawable.RippleDrawable;
import android.graphics.drawable.Drawable;
import android.content.res.ColorStateList;
import androidx.media3.common.util.Clock;
import androidx.media3.exoplayer.upstream.BandwidthMeter;
import androidx.media3.exoplayer.upstream.DefaultBandwidthMeter;

public class PlayActivity extends AppCompatActivity {
	
	private Timer _timer = new Timer();
	private String _ad_unit_id;
	
	ExoPlayer player;
	private String type = "";
	private String userAgent = "";
	private String referer = "";
	private String videoURL = "";
	private String name_channel = "";
	private double num = 0;
	private String url = "";
	private String uri = "";
	private HashMap<String, Object> server1 = new HashMap<>();
	private HashMap<String, Object> map_channel_server = new HashMap<>();
	private String server2 = "";
	private String refererHeader = "";
	private String userAgentHeader = "";
	private String link = "";
	private String contentUri = "";
	private String url_playing = "";
	private String link3 = "";
	private String key = "";
	private String web_link = "";
	private String ClearKey_Key = "";
	private String ClearKey_Key_ID = "";
	private String drmKey = "";
	private String drmKeyId = "";
	private boolean isDrm = false;
	private boolean isPipMode = false;
	AudioManager mAudioManager;
	private boolean isShowingTrackSelectionDialog = false;
	int ratio = 0;
	private boolean isLandscape = false;
	private double randomId = 0;
	PlayerView spv;
	private double currentPos = 0;
	private boolean isPlayable = false;
	InterstitialAd InterstitialAd;
	private HashMap<String, Object> reqMap = new HashMap<>();
	private String api_decrypted = "";
	private boolean isdefaultUserAgent = false;
	private String cookies = "";
	private String origin = "";
	private String text = "";
	TofiData channel;
	private String title = "";
	private String key_1 = "";
	
	private ArrayList<String> strs = new ArrayList<>();
	private ArrayList<HashMap<String, Object>> rvList = new ArrayList<>();
	private ArrayList<HashMap<String, Object>> map = new ArrayList<>();
	
	private FrameLayout linear2;
	private PlayerView linear1;
	private LinearLayout toolbar;
	private WebView webview1;
	private TextView web_cast_txt;
	private ImageView imageview1;
	private LinearLayout linear3;
	private LinearLayout linear4;
	private TextView textview1;
	private RecyclerView recyclerview2;
	private ImageView ic_arrow_back_white;
	private TextView textview1en;
	private RecyclerView recyclerview1;
	private TextView textview2ar;
	
	private TimerTask t;
	private Intent i = new Intent();
	private InterstitialAd ad;
	private InterstitialAdLoadCallback _ad_interstitial_ad_load_callback;
	private FullScreenContentCallback _ad_full_screen_content_callback;
	private SharedPreferences O;
	private Intent web = new Intent();
	private TimerTask ti;
	private RequestNetwork req;
	private RequestNetwork.RequestListener _req_request_listener;
	private TimerTask logia;
	private RequestNetwork ytReq;
	private RequestNetwork.RequestListener _ytReq_request_listener;
	
	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		setContentView(R.layout.play);
		initialize(_savedInstanceState);
		FirebaseApp.initializeApp(this);
		MobileAds.initialize(this);
		_ad_unit_id = "ca-app-pub-6543754410644923/6155884856";
		initializeLogic();
	}
	
	private void initialize(Bundle _savedInstanceState) {
		linear2 = findViewById(R.id.linear2);
		linear1 = findViewById(R.id.linear1);
		toolbar = findViewById(R.id.toolbar);
		webview1 = findViewById(R.id.webview1);
		webview1.getSettings().setJavaScriptEnabled(true);
		webview1.getSettings().setSupportZoom(true);
		web_cast_txt = findViewById(R.id.web_cast_txt);
		imageview1 = findViewById(R.id.imageview1);
		linear3 = findViewById(R.id.linear3);
		linear4 = findViewById(R.id.linear4);
		textview1 = findViewById(R.id.textview1);
		recyclerview2 = findViewById(R.id.recyclerview2);
		ic_arrow_back_white = findViewById(R.id.ic_arrow_back_white);
		textview1en = findViewById(R.id.textview1en);
		recyclerview1 = findViewById(R.id.recyclerview1);
		textview2ar = findViewById(R.id.textview2ar);
		O = getSharedPreferences("O", Activity.MODE_PRIVATE);
		req = new RequestNetwork(this);
		ytReq = new RequestNetwork(this);
		
		linear1.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
					if (toolbar.getVisibility() == View.GONE && !isPipMode) {
								
									toolbar.setVisibility(View.VISIBLE);
							} else {
									toolbar.setVisibility(View.GONE);
					recyclerview2.setVisibility(View.GONE);
					
								
							}
			}
		});
		
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
		
		ic_arrow_back_white.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				finish();
			}
		});
		
		_req_request_listener = new RequestNetwork.RequestListener() {
			@Override
			public void onResponse(String _param1, String _param2, HashMap<String, Object> _param3) {
				final String _tag = _param1;
				final String _response = _param2;
				final HashMap<String, Object> _responseHeaders = _param3;
				String timestamp = String.valueOf(System.currentTimeMillis() / 1000);
				try{
					if (_responseHeaders.containsKey("t")) {
						timestamp = _responseHeaders.get("t").toString();
					}
					api_decrypted = decrypt(_response,"c!xZj+N9&G@Ev@vw"+timestamp);
					JSONObject jsonObject = new JSONObject(api_decrypted);
					            JSONArray dataArray = jsonObject.getJSONArray("data");
					            JSONObject firstObject = dataArray.getJSONObject(0);
					
					if (firstObject.has("user_agent")) {
						userAgent = firstObject.getString("user_agent");
						isdefaultUserAgent = false;
					}
					else {
						isdefaultUserAgent = true;
					}
					if (firstObject.has("referer") && !firstObject.getString("referer").equals("")) {
						referer = firstObject.getString("referer");
					}
					if (firstObject.has("headers")) {
						JSONObject secondObject = firstObject.getJSONObject("headers");
						
						if (secondObject.has("User-Agent")) {
							userAgentHeader = secondObject.getString("User-Agent");
						}
						if (secondObject.has("Referer")) {
							refererHeader = secondObject.getString("Referer");
						}
					}
					if (firstObject.has("drm")) {
						Object drmValue = firstObject.get("drm");
						if (!drmValue.equals(null)) {
							JSONObject drmObject = firstObject.getJSONObject("drm");
							if (drmObject.has("license_url")) {
								link3 = drmObject.getString("license_url");
							}
							if (drmObject.has("key")) {
								ClearKey_Key = drmObject.getString("key");
							}
							if (drmObject.has("key_id")) {
								ClearKey_Key_ID = drmObject.getString("key_id");
							}
						}
					}
					String newUrl = "";
					if (rvList.get((int)currentPos).get("key").toString().contains("tofiUrlname=")) {
						Uri uri = Uri.parse(rvList.get((int)currentPos).get("key").toString());
						
						        
						        String urlname = uri.getQueryParameter("tofiUrlname");
						
						
						
						 newUrl =firstObject.getString("url");
						rvList.get((int)currentPos).put("key", newUrl);
						rvList.get((int)currentPos).put("name", urlname);
					}
					else {
						newUrl =firstObject.getString("url");
						rvList.get((int)currentPos).put("key", newUrl);
					}
					rvList.get((int)currentPos).put("key", newUrl);
					if (recyclerview1.getAdapter()!= null) {
								
									recyclerview1.getAdapter().notifyDataSetChanged();
								
						}
					_loadServer(newUrl);
				}catch(Exception e){
					SketchwareUtil.showMessage(getApplicationContext(), e.toString());
					_play_videos();
				}
			}
			
			@Override
			public void onErrorResponse(String _param1, String _param2) {
				final String _tag = _param1;
				final String _message = _param2;
				
			}
		};
		
		_ytReq_request_listener = new RequestNetwork.RequestListener() {
			@Override
			public void onResponse(String _param1, String _param2, HashMap<String, Object> _param3) {
				final String _tag = _param1;
				final String _response = _param2;
				final HashMap<String, Object> _responseHeaders = _param3;
				// استخراج الجزء الذي يحتوي على "hlsManifestUrl"
				int startIndex = _response.indexOf("hlsManifestUrl\":\"") + "hlsManifestUrl\":\"".length();
				int endIndex = _response.indexOf("index.m3u8", startIndex) + "index.m3u8".length();
				
				// التحقق من وجود الرابط الصحيح بعد "hlsManifestUrl"
				if (startIndex != -1 && endIndex != -1 && startIndex < endIndex) {
					    // استخراج الرابط الذي يأتي بعد "hlsManifestUrl" وتنظيفه
					    final String url_direct = _response.substring(startIndex, endIndex).replace("\\u0026", "&");
					
					    
					
					String newUrl = "";
					if (rvList.get((int)currentPos).get("key").toString().contains("tofiUrlname=")) {
						Uri uri = Uri.parse(rvList.get((int)currentPos).get("key").toString());
						
						        
						        String urlname = uri.getQueryParameter("tofiUrlname");
						
						
						
						 newUrl =url_direct;
						rvList.get((int)currentPos).put("key", newUrl);
						rvList.get((int)currentPos).put("name", urlname);
					}
					else {
						 newUrl =url_direct;
						rvList.get((int)currentPos).put("key", newUrl);
					}
					if (recyclerview1.getAdapter()!= null) {
								
									recyclerview1.getAdapter().notifyDataSetChanged();
								
						}
					_loadServer(newUrl);
				} else {
					    // إذا لم يتم العثور على الرابط الصحيح
					    SketchwareUtil.showMessage(getApplicationContext(), "الرابط غير موجود في الاستجابة.");
				}
			}
			
			@Override
			public void onErrorResponse(String _param1, String _param2) {
				final String _tag = _param1;
				final String _message = _param2;
				
			}
		};
		
		_ad_interstitial_ad_load_callback = new InterstitialAdLoadCallback() {
			@Override
			public void onAdLoaded(InterstitialAd _param1) {
				ad = _param1;
				ad.setFullScreenContentCallback(_ad_full_screen_content_callback);
				if (ad != null) {
					ad.show(PlayActivity.this);
				} else {
					SketchwareUtil.showMessage(getApplicationContext(), "Error: InterstitialAd ad hasn't been loaded yet!");
				}
			}
			
			@Override
			public void onAdFailedToLoad(LoadAdError _param1) {
				final int _errorCode = _param1.getCode();
				final String _errorMessage = _param1.getMessage();
				
			}
		};
		
		_ad_full_screen_content_callback = new FullScreenContentCallback() {
			@Override
			public void onAdDismissedFullScreenContent() {
				
			}
			
			@Override
			public void onAdFailedToShowFullScreenContent(AdError _adError) {
				final int _errorCode = _adError.getCode();
				final String _errorMessage = _adError.getMessage();
				
			}
			
			@Override
			public void onAdShowedFullScreenContent() {
				
			}
		};
	}
	
	private void initializeLogic() {
		// إعداد LayoutParams لـ ImageView
		FrameLayout.LayoutParams params = new FrameLayout.LayoutParams(
		    350, // العرض 100 بكسل
		    250   // الطول 30 بكسل
		);
		
		// إضافة المسافات من الحافة اليمنى وجعل الصورة تتجاوز الحافة العلوية بمقدار -20
		params.rightMargin = 80; // إضافة 20 بكسل كمسافة من اليمين
		params.topMargin = -30;  // استخدام قيمة سالبة لتحريك الصورة للأعلى بمقدار -20 بكسل
		
		// وضع الصورة في الجهة اليمنى العليا
		params.gravity = Gravity.TOP | Gravity.END; // تعيين الموقع العلوي اليميني
		
		// تعيين الـ LayoutParams لـ ImageView
		imageview1.setLayoutParams(params);
		if (getIntent().hasExtra("logotofi")) {
			Glide.with(getApplicationContext()).load(Uri.parse(getIntent().getStringExtra("logotofi"))).into(imageview1);
		}
		imageview1.setVisibility(View.GONE);
		if (getIntent().hasExtra("txt")) {
			String text = getIntent().getStringExtra("txt");
			
			// التحقق مما إذا كان النص يحتوي على أحرف عربية
			if (text.matches(".*[\\u0600-\\u06FF].*")) {
				    textview2ar.setText(text);
			} else {
				    textview1en.setText(text);
			}
			textview2ar.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/neosansarabic.ttf"), 1);
			textview1en.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/neosansarabic.ttf"), 1);
		}
		spv = linear1;
			
		isPipMode = isInPictureInPictureMode();
		toolbar.setVisibility(View.VISIBLE);
		if (getIntent().getStringExtra("userAgent").equals("")) {
			userAgent = webview1.getSettings().getUserAgentString();
			isdefaultUserAgent = true;
		}
		else {
			userAgent = getIntent().getStringExtra("userAgent");
			isdefaultUserAgent = false;
		}
		if (getIntent().getStringExtra("referer").equals("")) {
			referer = "";
		}
		else {
			referer = getIntent().getStringExtra("referer");
		}
		if (getIntent().hasExtra("cookies")) {
			cookies = getIntent().getStringExtra("cookies");
		}
		if (getIntent().hasExtra("Origin")) {
			origin = getIntent().getStringExtra("Origin");
		}
		if (getIntent().hasExtra("uris")) {
			strs = getIntent().getStringArrayListExtra("uris");
			if (strs != null) {
				url_playing = strs.get((int)(0));
				link = strs.get((int)(0));
				contentUri = strs.get((int)(0));
				videoURL = link;
				for (String s : strs){
					{
						HashMap<String, Object> _item = new HashMap<>();
						_item.put("key", s);
						rvList.add(_item);
					}
					
				}
				recyclerview1.setLayoutManager(new LinearLayoutManager(this,LinearLayoutManager.HORIZONTAL, false));
				recyclerview1.setHasFixedSize(true);
				if (rvList.size() > 1) {
					recyclerview1.setAdapter(new Recyclerview1Adapter(rvList));
				}
			}
		}
		else {
			url_playing = getIntent().getStringExtra("url");
			link = getIntent().getStringExtra("url");
			contentUri = getIntent().getStringExtra("url");
			videoURL = link;
		}
		isDrm = false;
		if (getIntent().hasExtra("isDrm")) {
			if (getIntent().getStringExtra("isDrm").equals("true")) {
				isDrm = true;
			}
		}
		if (isDrm) {
			link3 = "https://drm.cloud.insysvt.com/acquire-license/widevine";
			ClearKey_Key = getIntent().getStringExtra("ClearKey_Key");
			ClearKey_Key_ID = getIntent().getStringExtra("ClearKey_Key_ID");
		}
		_loadServer(link);
		num = 1;
		ImageView exo_track = linear1.findViewById(R.id.trackselection);
		exo_track.setBackgroundColor(Color.TRANSPARENT);
		exo_track.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (player!=null) {
					TrackSelectionDialog trackSelectionDialog =
					          TrackSelectionDialog.createForPlayer(
					              player,
					              /* onDismissListener= */ dismissedDialog -> isShowingTrackSelectionDialog = false);
					      trackSelectionDialog.show(getSupportFragmentManager(), /* tag= */ null);
					    
				}
			}
		});
		ImageView pip = linear1.findViewById(R.id.pip);
		pip.setBackgroundColor(Color.TRANSPARENT);
		pip.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (player!=null) {
					if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
						//ignore above blocks
						
						Display d = getWindowManager() 
						                                .getDefaultDisplay(); 
						                Point p = new Point(); 
						                d.getSize(p); 
						                int width = p.x; 
						                int height = p.y; 
						  
						                Rational ratio 
						                    = new Rational(width, height); 
						                PictureInPictureParams.Builder 
						                    pip_Builder 
						                    = new PictureInPictureParams 
						                          .Builder(); 
						                pip_Builder.setAspectRatio(ratio).build(); 
						                enterPictureInPictureMode(pip_Builder.build()); 
						            
					}
				}
			}
		});
		ImageView zoom = linear1.findViewById(R.id.zoom);
		zoom.setBackgroundColor(Color.TRANSPARENT);
		zoom.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (player!=null) {
					if (ratio >= 4) {
										ratio = 0;
								} else {
										ratio++;
								}
								linear1.setResizeMode(ratio);
				}
			}
		});
		ImageView orient = linear1.findViewById(R.id.orient);
		orient.setBackgroundColor(Color.TRANSPARENT);
		orient.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (player!=null) {
					if (isLandscape) {
						setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_PORTRAIT);
						isLandscape = false;
						orient.setImageResource(R.drawable.fullscreeno);
					}
					else {
						setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_LANDSCAPE);
						isLandscape = true;
						orient.setImageResource(R.drawable.landscape);
						setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE);
					}
				}
			}
		});
		ImageView cast = linear1.findViewById(R.id.cast);
		cast.setBackgroundColor(Color.TRANSPARENT);
		cast.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (player!=null) {
					Intent intent = new Intent(android.provider.Settings.ACTION_CAST_SETTINGS);
					startActivityForResult(intent, 0);
					
					
				}
			}
		});
		ImageView web_cast = linear1.findViewById(R.id.web_cast);
		web_cast.setBackgroundColor(Color.TRANSPARENT);
		web_cast.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (_isAppInstalled("com.instantbits.cast.webvideo")) {
					web.setPackage("com.instantbits.cast.webvideo");
					web.setData(Uri.parse(link));
					startActivity(web);
				}
				else {
					web.setAction(Intent.ACTION_VIEW);
					web.setData(Uri.parse("https://play.google.com/store/apps/details?id=com.instantbits.cast.webvideo"));
					startActivity(web);
				}
			}
		});
		ImageView enser = linear1.findViewById(R.id.enser);
		enser.setBackgroundColor(Color.TRANSPARENT);
		enser.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				recyclerview2.setVisibility(View.VISIBLE);
			}
		});
		_fullscreen();
		linear1.setResizeMode(AspectRatioFrameLayout.RESIZE_MODE_FILL);
		linear1.setControllerVisibilityListener(new PlayerView.ControllerVisibilityListener() {
			    @Override
			    public void onVisibilityChanged(int visibility) {
				        if (visibility == View.GONE) {
					    toolbar.setVisibility(View.GONE);
					        } else {
					            toolbar.setVisibility(View.VISIBLE);
					        }
				    }
			    });
		orient.setClickable(true); // التأكد من أن العنصر قابل للضغط
		orient.setFocusable(true); // التأكد من أن العنصر قابل للتركيز
		
		_Click_Effect(orient, "#6681D4FA", "#00000000", "#4081D4FA", 0, 0, 50);
		pip.setClickable(true); // التأكد من أن العنصر قابل للضغط
		pip.setFocusable(true); // التأكد من أن العنصر قابل للتركيز
		
		_Click_Effect(pip, "#6681D4FA", "#00000000", "#4081D4FA", 0, 0, 50);
		exo_track.setClickable(true); // التأكد من أن العنصر قابل للضغط
		exo_track.setFocusable(true); // التأكد من أن العنصر قابل للتركيز
		
		_Click_Effect(exo_track, "#6681D4FA", "#00000000", "#4081D4FA", 0, 0, 50);
		web_cast.setClickable(true); // التأكد من أن العنصر قابل للضغط
		web_cast.setFocusable(true); // التأكد من أن العنصر قابل للتركيز
		
		_Click_Effect(web_cast, "#6681D4FA", "#00000000", "#4081D4FA", 0, 0, 50);
		cast.setClickable(true); // التأكد من أن العنصر قابل للضغط
		cast.setFocusable(true); // التأكد من أن العنصر قابل للتركيز
		
		_Click_Effect(cast, "#6681D4FA", "#00000000", "#4081D4FA", 0, 0, 50);
		zoom.setClickable(true); // التأكد من أن العنصر قابل للضغط
		zoom.setFocusable(true); // التأكد من أن العنصر قابل للتركيز
		
		_Click_Effect(zoom, "#6681D4FA", "#00000000", "#4081D4FA", 0, 0, 50);
		enser.setClickable(true); // التأكد من أن العنصر قابل للضغط
		enser.setFocusable(true); // التأكد من أن العنصر قابل للتركيز
		
		_Click_Effect(enser, "#6681D4FA", "#00000000", "#4081D4FA", 0, 0, 50);
		toolbar.setBackground(new GradientDrawable() { public GradientDrawable getIns(int a, int b) { this.setCornerRadius(a); this.setColor(b); return this; } }.getIns((int)2, 0x1A000000));
		
		// الحصول على مرجع إلى PlayerView
		PlayerView playerView = findViewById(R.id.linear1);
		
		// إعداد Handler للتكرار
		Handler handler = new Handler(Looper.getMainLooper());
		Runnable runnable = new Runnable() {
			    @Override
			    public void run() {
				        if (playerView.getPlayer() != null) {
					            // التحقق من حالة مشغل الفيديو
					            int playbackState = playerView.getPlayer().getPlaybackState();
					            if (playbackState == Player.STATE_IDLE) {
						                // استدعاء الدالة _play_videos إذا كان الفيديو متوقفًا
						                _play_videos();
						            } else if (playbackState == Player.STATE_ENDED) {
						                // إعادة تعيين MediaItem وتشغيل الفيديو من البداية
						                playerView.getPlayer().seekToDefaultPosition();
						                playerView.getPlayer().prepare();
						                playerView.getPlayer().play();
						            }
					        }
				        // إعادة تشغيل runnable بعد 100 مللي ثانية
				        handler.postDelayed(this, 10);
				    }
		};
		
		// بدء التكرار
		handler.post(runnable);
		{
			AdRequest adRequest = new AdRequest.Builder().build();
			InterstitialAd.load(PlayActivity.this, _ad_unit_id, adRequest, _ad_interstitial_ad_load_callback);
		}
		ti = new TimerTask() {
			@Override
			public void run() {
				runOnUiThread(new Runnable() {
					@Override
					public void run() {
						if (web_cast_txt.getText().toString().contains("false")) {
							ImageView web_cast = linear1.findViewById(R.id.web_cast);
							web_cast.setVisibility(View.GONE);
						}
						if (web_cast_txt.getText().toString().contains("true")) {
							ImageView web_cast = linear1.findViewById(R.id.web_cast);
							web_cast.setVisibility(View.VISIBLE);
						}
					}
				});
			}
		};
		_timer.schedule(ti, (int)(200));
		if (getIntent().hasExtra("cast")) {
			web_cast_txt.setText(getIntent().getStringExtra("cast"));
		}
		ProgressBar bufferingSpinner = linear1.findViewById(R.id.exo_buffering);
		if (bufferingSpinner != null) {
			    bufferingSpinner.getIndeterminateDrawable().setColorFilter(Color.parseColor("#B71C1C"), PorterDuff.Mode.SRC_IN);
		}
		if (getIntent().hasExtra("key_1")) {
			channel = new TofiData(PlayActivity.this ,"channel" );
			channel.addSingleEventValueListener(new TofiData.ValueEventListener(){
				 @Override
				public void onSuccess (String _childKey,HashMap<String,Object > _childValue){
					if (getIntent().getStringExtra("key_1").equals(_childValue.get("txt_channel").toString())) {
						map.add(_childValue);
						recyclerview2.setAdapter(new Recyclerview2Adapter(map));
						enser.setVisibility(View.VISIBLE);
					}
				}
				@Override
				public void onPreSuccess (String rawResponse,HashMap<String,Object > mapHeaders){
					map.clear();
				}
				@Override
				public void onError (String error){
					 
				}
				 
			});
		}
		recyclerview2.setVisibility(View.GONE);
		enser.setVisibility(View.GONE);
		textview1.setText(getIntent().getStringExtra("title"));
		int columnWidthInDp = 140;
		
		// Convert dp to pixels
		DisplayMetrics displayMetrics = getResources().getDisplayMetrics();
		int columnWidthInPx = Math.round(columnWidthInDp * (displayMetrics.densityDpi / DisplayMetrics.DENSITY_DEFAULT));
		
		// Get the available width (e.g., screen width or RecyclerView width)
		int availableWidth = displayMetrics.widthPixels;  // or recyclerView.getWidth() if you have the layout set
		
		// Set the spanCount to ensure all items appear in one row
		int spanCount = recyclerview2.getAdapter() != null ? recyclerview2.getAdapter().getItemCount() : 1;
		GridLayoutManager gridLayoutManager = new GridLayoutManager(this, spanCount);
		gridLayoutManager.setSpanSizeLookup(new GridLayoutManager.SpanSizeLookup() {
			    @Override
			    public int getSpanSize(int position) {
				        return 1; // Each item takes one span
				    }
		});
		
		// Set the GridLayoutManager to the RecyclerView
		recyclerview2.setLayoutManager(gridLayoutManager);
		
		// تأكد من أن تخطيط العناصر (Item Layout) لا يحتوي على أي margin أو padding لعدم وجود تباعد بين العناصر.
		title = getIntent().getStringExtra("title");
		key_1 = getIntent().getStringExtra("key_1");
		if (getIntent().hasExtra("key_1")) {
			enser.setVisibility(View.VISIBLE);
		}
	}
	
	
	@Override
	public void onDestroy() {
		super.onDestroy();
		if (player != null) {
			
				spv.setPlayer(null);
				player.release();
				player = null;
		}
	}
	
	@Override
	public void onBackPressed() {
		if ((Build.VERSION.SDK_INT > 21) || (Build.VERSION.SDK_INT == 21)) {
			finishAndRemoveTask();
		}
		else {
			finish();
		}
	}
	
	@Override
	protected void onPostCreate(Bundle _savedInstanceState) {
		super.onPostCreate(_savedInstanceState);
			mAudioManager = (AudioManager) getSystemService(AUDIO_SERVICE);
		AudioManager.OnAudioFocusChangeListener focusChangeListener = new AudioManager.OnAudioFocusChangeListener() {
					public void onAudioFocusChange(int focusChange) {
				
							switch (focusChange) {
								case (AudioManager.AUDIOFOCUS_LOSS_TRANSIENT_CAN_DUCK):
									// Lower the volume while ducking.
									if (player != null)
									player.setVolume(0.2f);
									break;
								case (AudioManager.AUDIOFOCUS_LOSS_TRANSIENT):
									if (player != null)
									player.setPlayWhenReady(false);
									break;
								case (AudioManager.AUDIOFOCUS_LOSS):
									if (player != null)
										player.setPlayWhenReady(false);
									break;
								case (AudioManager.AUDIOFOCUS_GAIN):
									if (player != null && player.isCommandAvailable(Player.COMMAND_SET_VOLUME))
										player.setVolume(1f);
									break;
								default:
									break;
							}
					}
			};
			if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
						mAudioManager.requestAudioFocus(new AudioFocusRequest.Builder(AudioManager.AUDIOFOCUS_GAIN)
								.setAudioAttributes(new AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_MEDIA)
										.setContentType(AudioAttributes.CONTENT_TYPE_MOVIE).build())
								.setAcceptsDelayedFocusGain(true).setOnAudioFocusChangeListener(focusChangeListener).build());
				} else {
						mAudioManager.requestAudioFocus(focusChangeListener, AudioManager.STREAM_MUSIC,
								AudioManager.AUDIOFOCUS_GAIN);
				}
	}
	
	@Override
	public void onResume() {
		super.onResume();
		if(player!=null){
			player.setPlayWhenReady(true);
		}
		webview1.getSettings().setJavaScriptEnabled(true);
		
		webview1.setWebViewClient(new WebViewClient() {
			
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
		webview1.loadUrl("https://controller-gfx.blogspot.com/p/tofi-player-v3.html");
		linear1.setKeepScreenOn(true);
		setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE);
	}
	
	@Override
	public void onPause() {
		super.onPause();
		if(player!=null){
			player.setPlayWhenReady(false);
		}
	}
	public void _MarqueTextView(final TextView _view, final String _text) {
		_view.setText(_text);
		_view.setSingleLine(true);
		_view.setEllipsize(TextUtils.TruncateAt.MARQUEE);
		_view.setSelected(true);
	}
	
	
	public void _MPD_1() {
		byte[] drmKeyBytes = hexStringToByteArray(ClearKey_Key);
		        String encodedDrmKey = Base64.encodeToString(drmKeyBytes, Base64.URL_SAFE | Base64.NO_PADDING | Base64.NO_WRAP);
		
		        byte[] drmKeyIdBytes = hexStringToByteArray(ClearKey_Key_ID);
		        String encodedDrmKeyId = Base64.encodeToString(drmKeyIdBytes, Base64.URL_SAFE | Base64.NO_PADDING | Base64.NO_WRAP);
		
		       // String drmBody = "{\"keys\":[{\"kty\":\"oct\",\"k\":\"" + encodedDrmKey + "\",\"kid\":\"" + encodedDrmKeyId + "\"}],\"type\":\"temporary\"}";
		        
		        String drmBody = "{\"keys\":[{\"kty\":\"oct\",\"k\":\"" +encodedDrmKey+
		                "    \",\"kid\":\"" +encodedDrmKeyId+
		                "    \"}],'type':\"temporary\"}";
		        MediaItem dashMediaItem = new MediaItem.Builder()
		                .setUri(link)
		                .setMimeType(MimeTypes.APPLICATION_MPD)
		                .build();
		                String tempUserAgent = webview1.getSettings().getUserAgentString();
		HttpDataSource.Factory httpDataSourceFactory = new DefaultHttpDataSource.Factory()
		        .setUserAgent("Mozila")
		        .setAllowCrossProtocolRedirects(true);
			
			Map<String, String> headers = new HashMap<>();
		    
		    if(!referer.equals(""))
			headers.put("Referer", referer);
		     headers.put("Cookie", cookies);
			headers.put("Icy-MetaData", "1");
		    headers.put("Origin", origin);
			
			httpDataSourceFactory.setDefaultRequestProperties(headers);
		
		// Pass the factory when creating your media source
		//ataSource.Factory dataSourceFactory = new DefaultDataSource.Factory(this, httpDataSourceFactory);
		
		
		        DefaultTrackSelector trackSelector = new DefaultTrackSelector(this);
		        DefaultLoadControl loadControl = new DefaultLoadControl();
		
		        LocalMediaDrmCallback drmCallback = new LocalMediaDrmCallback(drmBody.getBytes());
		        DefaultDrmSessionManager drmSessionManager = new DefaultDrmSessionManager.Builder()
		                .setPlayClearSamplesWithoutKeys(true)
		                .setMultiSession(false)
		                .setKeyRequestParameters(new HashMap<>())
		                .setUuidAndExoMediaDrmProvider(C.CLEARKEY_UUID, FrameworkMediaDrm.DEFAULT_PROVIDER)
		                .build(drmCallback);
		
		        DrmSessionManager customDrmSessionManager = drmSessionManager;
		        DefaultMediaSourceFactory mediaSourceFactory = new DefaultMediaSourceFactory(httpDataSourceFactory)
		                .setDrmSessionManagerProvider(drmSessionManagerProvider -> customDrmSessionManager);
		BandwidthMeter bandwidthMeter = new DefaultBandwidthMeter.Builder(this)
		        .setClock(Clock.DEFAULT)
		        .build();
		
		 
		        player = new ExoPlayer.Builder(this)
		                .setTrackSelector(trackSelector)
		                .setBandwidthMeter(bandwidthMeter)
		               .setSeekForwardIncrementMs(10000L)
		               .setSeekBackIncrementMs(10000L)
		                .build();
		
		
		       
		
		        player.setMediaSource(mediaSourceFactory.createMediaSource(dashMediaItem), true);
		
		
		
	}
	
	
	public void _init() {
	}
	private android.app.Dialog dialogX_vpn;
	private Context context = PlayActivity.this;
	
	
	
	    private static final Boolean DEFAULT_PLAYER = false;
	    private static final Boolean DEFAULT_BUFFERVALUE = true;
	    private static final Boolean DEFAULT_MEDIASOURCE = false;
	    private static final String DOWNLOAD_CONTENT_DIRECTORY = "downloads";
	    public static final String DRM_SCHEME_EXTRA = "drm_scheme";
	    public static final String DRM_LICENSE_URL_EXTRA = "drm_license_url";
	    public static final String DRM_KEY_REQUEST_PROPERTIES_EXTRA = "drm_key_request_properties";
	    public static final String DRM_MULTI_SESSION_EXTRA = "drm_multi_session";
	    public static final String PREFER_EXTENSION_DECODERS_EXTRA = "prefer_extension_decoders";
	    private static final String DRM_SCHEME_UUID_EXTRA = "drm_scheme_uuid";
	
	    public static final String ABR_ALGORITHM_EXTRA = "abr_algorithm";
	    public static final String ABR_ALGORITHM_DEFAULT = "default";
	    public static final String ABR_ALGORITHM_RANDOM = "random";
	
	   
	    private boolean starting = false;
	    private boolean stopBuffering = false;
	    
	    
	    
	    private String urlStreaming = "";
	    
	   
	    //private static PlayerManager instance;
	
	  
	
	
	
	
	
	
	private byte[] hexStringToByteArray(String s) {
		        int len = s.length();
		        byte[] data = new byte[len / 2];
		        for (int i = 0; i < len; i += 2) {
			            data[i / 2] = (byte) ((Character.digit(s.charAt(i), 16) << 4) + Character.digit(s.charAt(i + 1), 16));
			        }
		        return data;
	}
	
	
	{
		
		
		
	}
	
	
	public void _play_videos() {
		try {
				HttpDataSource.Factory httpDataSourceFactory = new DefaultHttpDataSource.Factory()
				.setAllowCrossProtocolRedirects(true);
				
				Map<String, String> headers = new HashMap<>();
			    
				headers.put("User-Agent", userAgent);
			    
				headers.put("Referer", referer);
			    headers.put("Cookie", cookies);
			    headers.put("Origin", origin);
				headers.put("Icy-MetaData", "1");
			    headers.put("token", cookies);
				httpDataSourceFactory.setDefaultRequestProperties(headers);
				
				DefaultDataSource.Factory dataSourceFactory;
				DefaultRenderersFactory renderersFactory = new DefaultRenderersFactory(this)
				.setEnableDecoderFallback(true);
				
				if (link.startsWith("https://prod")) {
						dataSourceFactory = new DefaultDataSource.Factory(this, new DefaultHttpDataSource.Factory());
				} else {
						dataSourceFactory = new DefaultDataSource.Factory(this, httpDataSourceFactory);
				}
				
				player = new ExoPlayer.Builder(this, renderersFactory)
				.setMediaSourceFactory(new DefaultMediaSourceFactory(dataSourceFactory))
				.build();
				
				
				MediaSource mediaSource = null;
			    
				
				if (link.contains(".mpd")) {
						if (!ClearKey_Key.equals("")) {
								_MPD_1();
					            
						} else {
					            player = new ExoPlayer.Builder(this)
								                .setTrackSelector(new DefaultTrackSelector(this))
								                .build();
								MediaItem mediaItem = new MediaItem.Builder()
								.setUri(link)
								.setDrmUuid(C.WIDEVINE_UUID)
								.setDrmLicenseUri(link3)
								.build();
								
								mediaSource = new DashMediaSource.Factory(dataSourceFactory)
								.createMediaSource(mediaItem);
					            
						}
				} else if (link.contains(".m3u8") || link.contains("=m3u8") || type.equals("hls") || link.contains(".php") || link.contains(".zip")) {
				        
				    MediaItem mediaItem = new MediaItem.Builder()
				        .setUri(link)
				        .setMimeType(MimeTypes.APPLICATION_M3U8)
				    //  .setDrmUuid(C.WIDEVINE_UUID)
				    //  .setDrmLicenseUri(link3)
				        .build();
				    player.setMediaItem(mediaItem);
				        
				} else if (link.contains(".mp4")) { // التحقق مما إذا كان الرابط يحتوي على ".mp4"
						try {
								URL url = new URL(link);
								String refererFromUrl = url.getProtocol() + "://" + url.getHost();
								headers.put("Referer", refererFromUrl);
					            if(cookies.equals(""))
					                   headers .put("Cookie", cookies);
						} catch (MalformedURLException e) {
								headers.put("Referer", "");
					            headers.put("Cookie", cookies);
					            headers.put("Origin", origin);
						}
						
						httpDataSourceFactory.setDefaultRequestProperties(headers);
						
						MediaItem mediaItem = MediaItem.fromUri(link);
						mediaSource = new ProgressiveMediaSource.Factory(httpDataSourceFactory)
						.createMediaSource(mediaItem);
				} else {
						// للتعامل مع أي نوع آخر من الروابط
						MediaItem mediaItem = MediaItem.fromUri(link);
						mediaSource = new ProgressiveMediaSource.Factory(httpDataSourceFactory)
						.createMediaSource(mediaItem);
				}
				
				if (mediaSource != null) {
						player.setMediaSource(mediaSource);
				}
				
			    
			
				linear1.setPlayer(player);
			player.prepare();
			player.seekTo(0);
			player.play(); // قم بإزالة شرط فحص الـ VPN هنا
			
			ProgressBar pb = linear1.findViewById(R.id.pb);
			player.addListener(new Player.Listener() {
				
				    @Override
				    public void onPlaylistMetadataChanged(MediaMetadata mediaMetadata) {}
				
				    @Override
				    public void onSeekBackIncrementChanged(long seekBackIncrementMs) {}
				    
				    @Override
				    public void onSeekForwardIncrementChanged(long seekForwardIncrementMs) {}
				    
				    @Override
				    public void onMaxSeekToPreviousPositionChanged(long maxSeekToPreviousPositionMs) {}
				
				    @Override
				    public void onAudioSessionIdChanged(int audioSessionId) {}
				
				    @Override
				    public void onVolumeChanged(float volume) {}
				
				    @Override
				    public void onSkipSilenceEnabledChanged(boolean skipSilenceEnabled) {}
				
				    @Override
				    public void onDeviceInfoChanged(DeviceInfo deviceInfo) {}
				
				    @Override
				    public void onCues(List<Cue> list) {
					        // تعامل مع التسميات التوضيحية (subtitles)
					    }
				
				    @Override
				    public void onCues(androidx.media3.common.text.CueGroup group) {
					        // تعامل مع مجموعة التسميات التوضيحية
					    }
				
				    @Override
				    public void onPlaybackStateChanged(int playbackState) {
					        if (playbackState == Player.STATE_ENDED) {
						            // تعامل مع انتهاء الفيديو بدون شرط فحص VPN
						        }
					            if (playbackState == Player.STATE_BUFFERING) {
						                // إظهار ProgressBar عند التحميل (مثلاً عند التقديم)
						                pb.setVisibility(View.VISIBLE);
						            }
					        }
				
				        @Override
				public void onPlayerStateChanged(boolean b, int state) {
					    if (state == Player.STATE_ENDED) {
						        _loadServer(link);
						    }
				}
				
				        @Override
				        public void onPositionDiscontinuity(Player.PositionInfo pi, Player.PositionInfo pi2, int I) {
					            // Handle position discontinuity
					        }
				
				        @Override
				        public void onTimelineChanged(Timeline timeline, int reason) {
					            // Handle timeline changes
					        }
				
				        @Override
				        public void onMediaItemTransition(MediaItem mediaItem, int reason) {
					            // Handle media item transition
					        }
				
				        @Override
				        public void onTracksChanged(Tracks tracks) {
					            // Handle track changes
					        }
				
				        @Override
				        public void onIsLoadingChanged(boolean isLoading) {
					            // إظهار ProgressBar عند التحميل
					            if (isLoading) {
						                pb.setVisibility(View.VISIBLE);
						                imageview1.setVisibility(View.GONE);
						            } else {
						                pb.setVisibility(View.GONE);
						            }
					        }
				
				        @Override
				        public void onLoadingChanged(boolean b) {
					            // Handle loading state changes
					        }
				
				        @Override
				        public void onAvailableCommandsChanged(Player.Commands command) {
					            // Handle available commands changes
					        }
				
				        @Override
				        public void onPlayWhenReadyChanged(boolean playWhenReady, int reason) {
					            // Handle playWhenReady changes
					        }
				
				        @Override
				        public void onPlaybackSuppressionReasonChanged(int playbackSuppressionReason) {
					            // Handle playback suppression reason changes
					        }
				
				        @Override
				        public void onIsPlayingChanged(boolean isPlaying) {
					            if (isPlaying) {
						                isPlayable = true;
						                // إخفاء ProgressBar عند بدء التشغيل
						                pb.setVisibility(View.GONE);
						                imageview1.setVisibility(View.VISIBLE);
						            }
					        }
				
				        @Override
				        public void onRepeatModeChanged(int repeatMode) {
					            // Handle repeat mode changes
					        }
				
				        @Override
				        public void onShuffleModeEnabledChanged(boolean shuffleModeEnabled) {
					            // Handle shuffle mode changes
					        }
				
				        @Override
				        public void onPlayerError(PlaybackException error) {
					          showMessage (error.toString());
					            if (!isPlayable && rvList.size() > 1) {
						                if (currentPos < (rvList.size() - 1)) {
							                    currentPos++;
							                } else {
							                    currentPos = 0;
							                }
						                _loadServer(rvList.get((int) currentPos).get("key").toString());
						            }
					        }
				
				        @Override
				        public void onPlayerErrorChanged(PlaybackException error) {
					            // Handle player error changes
					        }
				
				        @Override
				        public void onPositionDiscontinuity(int reason) {
					            // Handle position discontinuity
					        }
				
				        @Override
				        public void onPlaybackParametersChanged(PlaybackParameters playbackParameters) {
					            // Handle playback parameter changes
					        }
				
				        // onSeekProcessed لم تعد ضمن Player.Listener في Media3؛ أُبقيت الدالة كما هي بدون @Override
				        public void onSeekProcessed() {
					            // إظهار ProgressBar عند بدء التقديم
					            pb.setVisibility(View.VISIBLE);
					        }
				
				        @Override
				        public void onVideoSizeChanged(VideoSize videoSize) {
					            // Handle video size changes
					        }
				
				        @Override
				        public void onRenderedFirstFrame() {
					            // Handle first frame rendering
					        }
				
				        @Override
				        public void onMetadata(Metadata metadata) {
					            // Handle metadata
					        }
				
				        @Override
				        public void onTrackSelectionParametersChanged(TrackSelectionParameters parameters) {
					            // Handle track selection parameters changes
					        }
				
				        @Override
				        public void onDeviceVolumeChanged(int volume, boolean muted) {
					            // Handle device volume changes
					        }
				        
				        @Override
				        public void onMediaMetadataChanged(MediaMetadata mediaMetadata) {
					           // التعامل مع التغيرات في الميتاداتا الخاصة بالوسائط
					        }
				
				        @Override
				        public void onEvents(Player player, Player.Events events) {
					            if (events.contains(Player.EVENT_PLAYBACK_STATE_CHANGED)) {
						                if (player.getPlaybackState() == Player.STATE_ENDED) {
							                    // عند انتهاء تشغيل الفيديو
							                    // player.seekTo(0);  // إعادة تشغيل الفيديو من البداية إذا لزم الأمر
							                    // player.play();
							                }
						                if (player.getPlaybackState() == Player.STATE_BUFFERING) {
							                    // إظهار ProgressBar عند بدء التحميل أو التقديم
							                    pb.setVisibility(View.VISIBLE);
							                } else if (player.getPlaybackState() == Player.STATE_READY) {
							                    // إخفاء ProgressBar عند انتهاء التحميل وجاهزية الفيديو للتشغيل
							                    pb.setVisibility(View.GONE);
							                }
						            }
					        }
				        
				        
				
				        @Override
				        public void onSurfaceSizeChanged(int width, int height) {
					            // التعامل مع تغيير حجم السطح (Surface)
					        }
				    });
				getWindow().setFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN, WindowManager.LayoutParams.FLAG_FULLSCREEN);
				
		} catch (Exception e) {
				// Handle exception
				e.printStackTrace(); // يمكنك إضافة طباعة للخطأ للمساعدة في التحقق من أي مشكلة
		}
		
	}
	
	
	public void _loadServer(final String _url) {
		if (player != null) {
				spv.setPlayer(null);
				player.release();
				player = null;
		}
		if (_url.contains("?tofi-api") || _url.contains("&tofi-api")) {
			reqMap = new HashMap<>();
			reqMap.put("Cache-Control", "no-cache");
			reqMap.put("Accept", "application/json");
			reqMap.put("User-Agent", "okhttp/3.14.9");
			req.setHeaders(reqMap);
			req.startRequestNetwork(RequestNetworkController.GET, _url, "", _req_request_listener);
			_getQueriesFromUrl(_url);
			_play_videos();
		}
		else {
			if (_url.contains("youtube.com/live")) {
				ytReq.startRequestNetwork(RequestNetworkController.GET, _url, "", _ytReq_request_listener);
				_getQueriesFromUrl(_url);
				_play_videos();
			}
			else {
				_getQueriesFromUrl(_url);
				if (containsSpecialChar(userAgent)) {
					isDrm = false;
					if (isdefaultUserAgent) {
						isDrm = true;
					}
				}
				else {
					isDrm = true;
				}
				if (isDrm) {
					String[] keyData = extractKeyAndKeyId(_url);
					
					        if (keyData != null) {
						           
						ClearKey_Key = keyData[1];
						ClearKey_Key_ID = keyData[0];
						link3 = "https://drm.cloud.insysvt.com/acquire-license/widevine";
					}
				}
				url_playing = _url;
				link = _url;
				contentUri = _url;
				videoURL = link;
				isPlayable = false;
				_play_videos();
				num = 1;
				if (recyclerview1.getAdapter()!= null) {
					try{
						recyclerview1.getAdapter().notifyDataSetChanged();
					}catch(Exception e){
						 
					}
				}
			}
		}
	}
	
	
	public void _pipListener() {
	}
	
		@Override
		public void onPictureInPictureModeChanged(boolean isInPictureInPictureMode, @NonNull Configuration newConfig) {
				if (newConfig != null)
					isPipMode = isInPictureInPictureMode;
				if (getLifecycle().getCurrentState() == Lifecycle.State.CREATED) {
						finish();
				}
				if (isInPictureInPictureMode && newConfig != null) {
			 if(player!=null&&linear1!=null){
							player.setPlayWhenReady(true);
							linear1.hideController();
							toolbar.setVisibility(View.GONE);
				         }
			        }    
				super.onPictureInPictureModeChanged(isInPictureInPictureMode, newConfig);
			
	}
	
	
	public void _fullscreen() {
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
	}
	
	
	public void _Click_Effect(final View _view, final String _StrokeColor, final String _color, final String _colorclicked, final double _CornerRadius, final double _stroke, final double _strokecliced) {
		_view.setOnTouchListener(new View.OnTouchListener(){
			    @Override
			    public boolean onTouch(View v, MotionEvent event){
				        int ev = event.getAction();
				        switch (ev) {
					            case MotionEvent.ACTION_DOWN:
					                // تغيير الخلفية عند النقر
					                _view.setBackground(new GradientDrawable() {
						                    public GradientDrawable getIns(int a, int b, int c, int d) {
							                        this.setCornerRadius(a);
							                        this.setStroke(b, c);
							                        this.setColor(d);
							                        return this;
							                    }
						                }.getIns((int)_CornerRadius, (int)_strokecliced, Color.parseColor(_StrokeColor), Color.parseColor(_color)));
					                break;
					            case MotionEvent.ACTION_UP:
					            case MotionEvent.ACTION_CANCEL:
					                // إعادة الخلفية للوضع الطبيعي عند إزالة النقر أو إزالة الماوس
					                _view.setBackground(new GradientDrawable() {
						                    public GradientDrawable getIns(int a, int b, int c, int d) {
							                        this.setCornerRadius(a);
							                        this.setStroke(b, c);
							                        this.setColor(d);
							                        return this;
							                    }
						                }.getIns((int)_CornerRadius, (int)_stroke, Color.parseColor(_color), Color.parseColor(_color)));
					                break;
					        }
				        return false;
				    }
		});
	}
	
	
	public void _extra() {
	}
	String decrypt(String enc, String key) {
		        byte[] decodedBytes = java.util.Base64.getDecoder().decode(enc);
		        String decodedString = new String(decodedBytes, StandardCharsets.UTF_8);
		        StringBuilder result = new StringBuilder();
		
		        for (int i = 0; i < decodedString.length(); i++) {
			            result.append((char) (decodedString.charAt(i) ^ key.charAt(i % key.length())));
			        }
		        return result.toString();
		    }
	private Map<String, String> getCustomHeaders() {
		        Map<String, String> headers = new HashMap<>();
		        headers.put("User-Agent", userAgentHeader);
		        
		        headers.put("Cookie", cookies);
		        // Add other headers if needed (e.g., Referer)
		        return headers;
		    }
	    String toHumanReadableAscii(String s) {
		    for (int i = 0, length = s.length(), c; i < length; i += Character.charCount(c)) {
			      c = s.codePointAt(i);
			      if (c > '\u001f' && c < '\u007f') continue;
			
			      okio.Buffer buffer = new okio.Buffer();
			      buffer.writeUtf8(s, 0, i);
			      for (int j = i; j < length; j += Character.charCount(c)) {
				        c = s.codePointAt(j);
				        buffer.writeUtf8CodePoint(c > '\u001f' && c < '\u007f' ? c : '?');
				      }
			      return buffer.readUtf8();
			    }
		    return s;
		  }
	  private HttpDataSource.Factory getHttpDataSourceFactory() {
		        // Adding the referer to the request
		        DefaultHttpDataSource.Factory httpDataSourceFactory = new DefaultHttpDataSource.Factory();
		        httpDataSourceFactory.setUserAgent(userAgent);
		        httpDataSourceFactory.setDefaultRequestProperties(
		            new HashMap<String, String>() {{
				                put("Referer", referer);
				                
				        put("Cookie", cookies);
				            }}
		        );
		        return httpDataSourceFactory;
		    
	}
	String[] extractKeyAndKeyId(String url) {
		        // Check if the URL contains the drmLicense parameter
		        if (url.contains("drmLicense=")) {
			              Uri uri = Uri.parse(url);
			
			        
			        String urlname = uri.getQueryParameter("drmLicense");
			            // Split the drmLicense value by the colon (:)
			            String[] keyParts = urlname.split(":");
			
			            // Ensure we have both keyId and key
			            if (keyParts.length == 2) {
				                String keyId = keyParts[0];
				                String key = keyParts[1];
				                return new String[] { keyId, key };
				            }
			        }
		        // Return null if no valid drmLicense is found
		        return null;
		    }
	    public  boolean containsSpecialChar(String str) {
		        // Regular expression to match special characters
		        String regex = "[^a-zA-Z0-9 ]";
		        Pattern p = Pattern.compile("[^a-z0-9 ]", Pattern.CASE_INSENSITIVE);
		Matcher m = p.matcher(str);
		boolean b = m.find();
		
		return b ;
		        // str.matches(".*" + regex + ".*");
		    
	}
	
	
	public void _getQueriesFromUrl(final String _url) {
		if (_url.contains("?")) {
			int index = _url.indexOf("?");
			if (index !=-1) {
				String query = "";
				query = _url.substring(index);
				Map<String, String> queryPairs = new HashMap<>();
				
				String[] pairs = query.split("&");
				for (String pair : pairs) {
					    int idx = pair.indexOf("=");
					    
					    // تحقق من أن `pair` يحتوي على علامة `=`
					    if (idx != -1) {
						        String key = pair.substring(0, idx).replaceAll("[^a-zA-Z0-9-]", "");
						        String value = pair.substring(idx + 1);
						        queryPairs.put(key, value);
						    } else {
						        // خيار إضافي للتعامل مع `pair` إذا لم يكن يحتوي على `=`
						        // يمكن تسجيل رسالة أو إضافة قيمة افتراضية للمفتاح إذا لزم الأمر
						        System.out.println("Warning: Pair without '=' found - " + pair);
						    }
				}
				if (queryPairs.containsKey("referer")) {
					referer = queryPairs.get("referer").toString();
				}
				if (queryPairs.containsKey("user-agent")) {
					userAgent = queryPairs.get("user-agent").toString();
				}
				if (queryPairs.containsKey("cookies")) {
					cookies = queryPairs.get("cookies").toString();
				}
				if (queryPairs.containsKey("origin")) {
					origin = queryPairs.get("origin").toString();
				}
				if (queryPairs.containsKey("img")) {
					Glide.with(getApplicationContext()).load(Uri.parse(queryPairs.get("img").toString())).into(imageview1);
				}
				if (queryPairs.containsKey("cast")) {
					web_cast_txt.setText(queryPairs.get("cast").toString());
				}
				if (queryPairs.containsKey("txt")) {
					    try {
						        // فك ترميز النص قبل عرضه
						        String decodedText = URLDecoder.decode(queryPairs.get("txt").toString(), StandardCharsets.UTF_8.name());
						        
						        // التحقق من ما إذا كان النص يحتوي على أحرف عربية
						        if (decodedText.matches(".*[\\u0600-\\u06FF\\u0750-\\u077F\\u08A0-\\u08FF].*")) {
							            textview2ar.setText(decodedText); // عرض النص في textview العربي
							            textview1en.setText(" "); // وضع فراغ في textview الإنجليزي
							        } else {
							            textview1en.setText(decodedText); // عرض النص في textview الإنجليزي
							            textview2ar.setText(" "); // وضع فراغ في textview العربي
							        }
						    } catch (Exception e) {
						        e.printStackTrace();
						        // عرض النص كما هو إذا حدثت مشكلة في فك الترميز
						        String originalText = queryPairs.get("txt").toString();
						        if (originalText.matches(".*[\\u0600-\\u06FF\\u0750-\\u077F\\u08A0-\\u08FF].*")) {
							            textview2ar.setText(originalText); // عرض النص في textview العربي
							            textview1en.setText(" "); // وضع فراغ في textview الإنجليزي
							        } else {
							            textview1en.setText(originalText); // عرض النص في textview الإنجليزي
							            textview2ar.setText(" "); // وضع فراغ في textview العربي
							        }
						    }
				}
			}
		}
	}
	
	
	public boolean _isAppInstalled(final String _pkg) {
		android.content.pm.PackageManager packageManager = getPackageManager();
		    try {
			        android.content.pm.ApplicationInfo appInfo = packageManager.getApplicationInfo(_pkg, 0);
			        return appInfo.enabled;
			    } catch (android.content.pm.PackageManager.NameNotFoundException e) {
			        return false;
			    }
	}
	
	public class Recyclerview2Adapter extends RecyclerView.Adapter<Recyclerview2Adapter.ViewHolder> {
		
		ArrayList<HashMap<String, Object>> _data;
		
		public Recyclerview2Adapter(ArrayList<HashMap<String, Object>> _arr) {
			_data = _arr;
		}
		
		@Override
		public ViewHolder onCreateViewHolder(ViewGroup parent, int viewType) {
			LayoutInflater _inflater = getLayoutInflater();
			View _v = _inflater.inflate(R.layout.ccc, null);
			RecyclerView.LayoutParams _lp = new RecyclerView.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
			_v.setLayoutParams(_lp);
			return new ViewHolder(_v);
		}
		
		@Override
		public void onBindViewHolder(ViewHolder _holder, final int _position) {
			View _view = _holder.itemView;
			
			final LinearLayout linear1 = _view.findViewById(R.id.linear1);
			final TextView txt1 = _view.findViewById(R.id.txt1);
			final TextView txt = _view.findViewById(R.id.txt);
			final ImageView img1 = _view.findViewById(R.id.img1);
			final LinearLayout linear2 = _view.findViewById(R.id.linear2);
			final TextView textview1 = _view.findViewById(R.id.textview1);
			final androidx.cardview.widget.CardView cardview1 = _view.findViewById(R.id.cardview1);
			final ImageView imageview1 = _view.findViewById(R.id.imageview1);
			
			textview1.setText(map.get((int)_position).get("txt").toString());
			_MarqueTextView(textview1, map.get((int)_position).get("txt").toString());
			Glide.with(getApplicationContext()).load(Uri.parse(map.get((int)_position).get("img").toString())).into(imageview1);
			imageview1.setImageResource(R.drawable.default_image);
			linear1.setOnClickListener(new View.OnClickListener() {
				@Override
				public void onClick(View _view) {
					i.setClass(getApplicationContext(), MainActivity.class);
					if (_data.get((int)_position).containsKey("link")) {
						i.putExtra("link", map.get((int)_position).get("link").toString());
					}
					else {
						i.putExtra("link", map.get((int)_position).get("url").toString());
					}
					if (_data.get((int)_position).containsKey("user_agent")) {
						i.putExtra("user_agent", map.get((int)_position).get("user_agent").toString());
					}
					else {
						
					}
					if (_data.get((int)_position).containsKey("referer")) {
						i.putExtra("refere", map.get((int)_position).get("referer").toString());
					}
					else {
						
					}
					if (_data.get((int)_position).containsKey("txt")) {
						i.putExtra("txt", map.get((int)_position).get("txt").toString());
						i.putExtra("cast", "false");
					}
					else {
						i.putExtra("txt", "ToFi X Tv");
					}
					if (_data.get((int)_position).containsKey("Origin")) {
						i.putExtra("Origin", map.get((int)_position).get("Origin").toString());
					}
					else {
						
					}
					if (_data.get((int)_position).containsKey("cookies")) {
						i.putExtra("cookies", map.get((int)_position).get("cookies").toString());
					}
					else {
						
					}
					if (_data.get((int)_position).containsKey("logotofi")) {
						i.putExtra("logotofi", map.get((int)_position).get("logotofi").toString());
					}
					else {
						
					}
					i.putExtra("key_1", key_1);
					i.putExtra("title", title);
					startActivity(i);
					finish();
				}
			});
			// إنشاء شكل بخلفية من نوع GradientDrawable
			GradientDrawable gradientDrawable = new GradientDrawable();
			gradientDrawable.setCornerRadius(20); // ضبط الحواف الدائرية
			gradientDrawable.setColor(Color.TRANSPARENT); // تعيين اللون الشفاف للخلفية
			
			// تعيين الخلفية للكارد فيو
			cardview1.setBackground(gradientDrawable);
			
			// إضافة ارتفاع (ظل) للكارد فيو
			cardview1.setElevation(10); // هذا يعادل app:cardElevation="10dp"
			Glide.with(getApplicationContext())
			    .load(Uri.parse(map.get((int)_position).get("img").toString())) // URL الصورة الأساسية
			    .placeholder(R.drawable.logo) // صورة اللوجو الافتراضية
			    .into(imageview1); // ImageView التي ستظهر فيها الصورة
		}
		
		@Override
		public int getItemCount() {
			return _data.size();
		}
		
		public class ViewHolder extends RecyclerView.ViewHolder {
			public ViewHolder(View v) {
				super(v);
			}
		}
	}
	
	public class Recyclerview1Adapter extends RecyclerView.Adapter<Recyclerview1Adapter.ViewHolder> {
		
		ArrayList<HashMap<String, Object>> _data;
		
		public Recyclerview1Adapter(ArrayList<HashMap<String, Object>> _arr) {
			_data = _arr;
		}
		
		@Override
		public ViewHolder onCreateViewHolder(ViewGroup parent, int viewType) {
			LayoutInflater _inflater = getLayoutInflater();
			View _v = _inflater.inflate(R.layout.cus, null);
			RecyclerView.LayoutParams _lp = new RecyclerView.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
			_v.setLayoutParams(_lp);
			return new ViewHolder(_v);
		}
		
		@Override
		public void onBindViewHolder(ViewHolder _holder, final int _position) {
			View _view = _holder.itemView;
			
			final LinearLayout linear1 = _view.findViewById(R.id.linear1);
			final TextView textview1 = _view.findViewById(R.id.textview1);
			
			
						RecyclerView.LayoutParams lp = new RecyclerView.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT);
						_view.setLayoutParams(lp);
			if (_data.get((int)_position).containsKey("name")) {
				textview1.setText(rvList.get((int)_position).get("name").toString());
			}
			else {
				if (_data.get((int)_position).get("key").toString().contains("tofiUrlname=")) {
					Uri uri = Uri.parse(_data.get((int)_position).get("key").toString());
					
					        
					        String urlname = uri.getQueryParameter("tofiUrlname");
					textview1.setText(urlname);
				}
				else {
					textview1.setText("S".concat(String.valueOf((long)(_position + 1))));
				}
			}
			linear1.setOnClickListener(new View.OnClickListener() {
				@Override
				public void onClick(View _view) {
					if (_data.get((int)_position).get("key").toString().contains("&web=web")) {
						web.setClass(getApplicationContext(), WebviewActivity.class);
						web.putExtra("url", _data.get((int)_position).get("key").toString());
						startActivity(web);
						Animatoo.animateSlideDown(PlayActivity.this);
					}
					else {
						currentPos = _position;
						_loadServer(_data.get((int)_position).get("key").toString());
					}
				}
			});
			textview1.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/droid.ttf"), 1);
			
			if (_data.get((int) _position).get("key").toString().equals(url_playing)) {
				    // إعداد الخلفية مع تأثير التموج للزر المحدد
				    linear1.setBackground(new RippleDrawable(
				        ColorStateList.valueOf(0xFF00BCD4), // لون التموج
				        new GradientDrawable() {
					            public GradientDrawable getIns(int a, int b, int c, int d) {
						                this.setCornerRadius(a);
						                this.setStroke(b, c);
						                this.setColor(d);
						                return this;
						            }
					        }.getIns((int) 100, (int) 5, 0xFFFFFFFF, 0xFFFFEB3B), // خلفية محددة باللون الأصفر
				        null // قناع التموج (اختياري)
				    ));
				    linear1.setClickable(true); // التأكد من أن العنصر قابل للضغط
				    linear1.setFocusable(true); // التأكد من أن العنصر قابل للتركيز
				    textview1.setTextColor(0xFF000000);
			} else {
				    // إعداد الخلفية مع تأثير التموج للزر غير المحدد
				    linear1.setBackground(new RippleDrawable(
				        ColorStateList.valueOf(0xFF00BCD4), // لون التموج
				        new GradientDrawable() {
					            public GradientDrawable getIns(int a, int b, int c, int d) {
						                this.setCornerRadius(a);
						                this.setStroke(b, c);
						                this.setColor(d);
						                return this;
						            }
					        }.getIns((int) 100, (int) 1, 0xFFFFFFFF, 0xFFFFFFFF), // خلفية غير محددة باللون الأبيض
				        null // قناع التموج (اختياري)
				    ));
				    linear1.setClickable(true); // التأكد من أن العنصر قابل للضغط
				    linear1.setFocusable(true); // التأكد من أن العنصر قابل للتركيز
				    textview1.setTextColor(0xFF000000);
			}
		}
		
		@Override
		public int getItemCount() {
			return _data.size();
		}
		
		public class ViewHolder extends RecyclerView.ViewHolder {
			public ViewHolder(View v) {
				super(v);
			}
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