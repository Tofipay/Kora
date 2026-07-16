package com.tofixtv.app;

import android.animation.*;
import android.app.*;
import android.content.*;
import android.content.res.*;
import android.graphics.*;
import android.graphics.Typeface;
import android.graphics.drawable.*;
import android.media.*;
import android.net.*;
import android.os.*;
import android.text.*;
import android.text.style.*;
import android.util.*;
import android.view.*;
import android.view.View;
import android.view.View.*;
import android.view.animation.*;
import android.webkit.*;
import android.widget.*;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
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
import com.blogspot.atifsoftwares.animatoolib.*;
import com.google.android.gms.ads.*;
import com.google.android.gms.ads.AdError;
import com.google.android.gms.ads.AdRequest;
import com.google.android.gms.ads.AdView;
import com.google.android.gms.ads.FullScreenContentCallback;
import com.google.android.gms.ads.LoadAdError;
import com.google.android.gms.ads.MobileAds;
import com.google.android.gms.ads.impl.*;
import com.google.android.gms.ads.interstitial.InterstitialAd;
import com.google.android.gms.ads.interstitial.InterstitialAdLoadCallback;
import com.google.android.gms.base.*;
import com.google.android.gms.common.*;
import com.google.android.material.tabs.TabLayout;
import com.google.android.material.tabs.TabLayout.OnTabSelectedListener;
import com.google.firebase.FirebaseApp;
import java.io.*;
import java.text.*;
import java.util.*;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.regex.*;
import meorg.jsoup.*;
import org.json.*;
import androidx.viewpager2.widget.ViewPager2;
import com.google.android.material.tabs.TabLayoutMediator;

public class TestActivity extends AppCompatActivity {
	
	private String _ad_unit_id;
	
	private String m3uUrl = "";
	private HashMap<String, Object> map = new HashMap<>();
	private String videoTitle = "";
	
	private ArrayList<HashMap<String, Object>> dataListMap = new ArrayList<>();
	private ArrayList<String> tabList = new ArrayList<>();
	private ArrayList<HashMap<String, Object>> vpMap = new ArrayList<>();
	
	private LinearLayout linear21;
	private LinearLayout linear1;
	private TabLayout tb;
	private ViewPager2 vp;
	private AdView adview1;
	private ImageView imageview3;
	private ImageView imageview2;
	private TextView textview1;
	private ProgressBar progressbar1;
	
	private RequestNetwork request;
	private RequestNetwork.RequestListener _request_request_listener;
	private InterstitialAd ad;
	private InterstitialAdLoadCallback _ad_interstitial_ad_load_callback;
	private FullScreenContentCallback _ad_full_screen_content_callback;
	
	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		setContentView(R.layout.test);
		initialize(_savedInstanceState);
		FirebaseApp.initializeApp(this);
		MobileAds.initialize(this);
		_ad_unit_id = "ca-app-pub-6543754410644923/6155884856";
		initializeLogic();
	}
	
	private void initialize(Bundle _savedInstanceState) {
		linear21 = findViewById(R.id.linear21);
		linear1 = findViewById(R.id.linear1);
		tb = findViewById(R.id.tb);
		vp = findViewById(R.id.vp);
		adview1 = findViewById(R.id.adview1);
		imageview3 = findViewById(R.id.imageview3);
		imageview2 = findViewById(R.id.imageview2);
		textview1 = findViewById(R.id.textview1);
		progressbar1 = findViewById(R.id.progressbar1);
		request = new RequestNetwork(this);
		
		imageview3.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (getIntent().hasExtra("home")) {
					finish();
				}
				else {
					if (getIntent().hasExtra("m3u")) {
						startActivity(new Intent(TestActivity.this, ListviweActivity.class)); Animatoo.animateSlideLeft(TestActivity.this);
					}
					else {
						finish();
					}
				}
			}
		});
		
		_request_request_listener = new RequestNetwork.RequestListener() {
			@Override
			public void onResponse(String _param1, String _param2, HashMap<String, Object> _param3) {
				final String _tag = _param1;
				final String _response = _param2;
				final HashMap<String, Object> _responseHeaders = _param3;
				if (_response.equals("")) {
					SketchwareUtil.showMessage(getApplicationContext(), "empty");
					linear1.setVisibility(View.GONE);
					tb.setVisibility(View.GONE);
				}
				else {
					String response = _response;
					ArrayList<String>str = new ArrayList<String>(Arrays.asList(response.split("#EXTINF:")));
					if ((str.size() > 0) && str.get(0).toLowerCase().startsWith("#extm3u")) {
											str.remove(0);
									}
					ArrayList<String> innerStr = null;
					ArrayList<String> tabListMain = new ArrayList<>();
					ArrayList<String> tabListTemp = new ArrayList<>();
					String  word ="";
					String  innerWord ="";
					 String url = "";
						
						 String duration = "";
						 String logo = "";
						 String title = "";
						 String group = "";
						 String referer = "";
						 String useragent = "";
					for(int _repeat18 = 0; _repeat18 < (int)(str.size()); _repeat18++) {
						try{
							innerStr = new ArrayList<String>(Arrays.asList(str.get((int)(_repeat18)).split("\n")));
							word = str.get((int)(_repeat18));
							for(int _repeat27 = 0; _repeat27 < (int)(innerStr.size()); _repeat27++) {
								innerWord = innerStr.get((int)(_repeat27));
								int firstSpaceIndex = word.indexOf(" ");
								        
								if (firstSpaceIndex != -1) {
									            duration = word.substring(0, firstSpaceIndex);
									           }
								Pattern pattern = Pattern.compile("tvg-logo=\"(.*?)\"");
								        Matcher matcher = pattern.matcher(word);
								
								        if (matcher.find()) {
									            logo = matcher.group(1);
								}
								Pattern groupPattern = Pattern.compile("group-title=\"(.*?)\"");
								        Matcher groupMatcher = groupPattern.matcher(word);
								
								        if (groupMatcher.find()) {
									            group = groupMatcher.group(1);
								}
								
								
								int firstCommaIndex = innerWord.indexOf(",");
								        
								if (firstCommaIndex != -1&&innerWord.length()>firstCommaIndex) {
									            title = innerWord.substring(firstCommaIndex+1, innerWord.length());
									           }
								if (innerWord.contains("http")) {
									url = innerStr.get((int)(_repeat27));
								}
								if (innerWord.contains("user-agent")) {
									int uaIndex = innerWord.indexOf("=");
									        
									if (uaIndex != -1) {
										            useragent = innerWord.substring(uaIndex+1, innerWord.length());
										           }
								}
								if (innerWord.contains("referrer")) {
									int refererIndex = innerWord.indexOf("=");
									        
									if (refererIndex != -1) {
										            referer = innerWord.substring(refererIndex+1, innerWord.length());
										           }
								}
								if (!tabListTemp.contains(group)) {
									tabListTemp.add(group);
								}
							}
							if (!url.equals("")) {
								map = new HashMap<>();
								map.put("duration", duration);
								map.put("url", url);
								map.put("group-title", group);
								map.put("txt", videoTitle);
								if (!logo.equals("")) {
									map.put("logo", logo);
								}
								if (!useragent.equals("")) {
									map.put("useragent", useragent);
									map.put("referer", referer);
								}
								if (!title.equals("")) {
									map.put("title", title);
								}
								dataListMap.add(map);
							}
						}catch(Exception e){
							SketchwareUtil.showMessage(getApplicationContext(), e.toString());
						}
					}
					if (dataListMap.size() > 0) {
						for(String s: tabListTemp){
							
							
							
							
							
							
							
							ArrayList<HashMap<String,Object>> tempMap = new ArrayList<>();
							for(HashMap<String, Object>hmap: dataListMap){
								if (hmap.get("group-title").toString().equals(s)) {
									tempMap.add(hmap);
								}
							}
							int count =tempMap.size();
							map = new HashMap<>();
							map.put("title", s);
							map.put("data", tempMap);
							vpMap.add(map);
							if(s.equals("")){
								tabList.add("Undefined ("+count+")");
							}else{
								tabList.add(s+" ("+count+")");
							}
						}
						tabList.add((int)(0), "ALL".concat("(".concat(String.valueOf((long)(str.size())).concat(")"))));
						map = new HashMap<>();
						map.put("title", "ALL");
						map.put("data", dataListMap);
						vpMap.add((int)0, map);
						for(String string: tabList){
							tb.addTab(tb.newTab().setText(string));
						}
						vp.setAdapter( new M3UAdapter(TestActivity.this, vpMap));
						new TabLayoutMediator(tb, vp, true, new TabLayoutMediator.TabConfigurationStrategy() {
							         @Override
							        public void onConfigureTab(TabLayout.Tab tab, int position) { 
								         tab.setText(tabList.get(position));
										 }
							        }).attach();
						linear1.setVisibility(View.GONE);
						tb.setVisibility(View.VISIBLE);
					}
					else {
						linear1.setVisibility(View.GONE);
						tb.setVisibility(View.GONE);
						SketchwareUtil.showMessage(getApplicationContext(), "empty list ");
					}
				}
			}
			
			@Override
			public void onErrorResponse(String _param1, String _param2) {
				final String _tag = _param1;
				final String _message = _param2;
				progressbar1.setVisibility(View.GONE);
				tb.setVisibility(View.GONE);
				SketchwareUtil.showMessage(getApplicationContext(), _message);
			}
		};
		
		_ad_interstitial_ad_load_callback = new InterstitialAdLoadCallback() {
			@Override
			public void onAdLoaded(InterstitialAd _param1) {
				ad = _param1;
				ad.setFullScreenContentCallback(_ad_full_screen_content_callback);
				if (ad != null) {
					ad.show(TestActivity.this);
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
		videoTitle = getIntent().getStringExtra("txt");
		textview1.setText(videoTitle);
		m3uUrl = getIntent().getStringArrayListExtra("uris").get(0);
		request.startRequestNetwork(RequestNetworkController.GET, m3uUrl, "", _request_request_listener);
		vp.setOrientation(ViewPager2.ORIENTATION_HORIZONTAL);
		tb.setVisibility(View.GONE);
		{
			AdRequest adRequest = new AdRequest.Builder().build();
			adview1.loadAd(adRequest);
		}
		if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
			    // تغيير لون شريط التنقل فقط إلى اللون الأسود #000000
			    getWindow().setNavigationBarColor(0xFF192535); // لون أسود
		}
		ProgressBar progressBar = findViewById(R.id.progressbar1);
		
		// تغيير لون الـ Indeterminate Drawable إذا كان الدائري في وضع indeterminate
		progressBar.getIndeterminateDrawable().setColorFilter(
		    Color.parseColor("#FFB300"), 
		    android.graphics.PorterDuff.Mode.SRC_IN);
		textview1.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/neosansarabic.ttf"), 1);
	}
	
	
	@Override
	protected void onPostCreate(Bundle _savedInstanceState) {
		super.onPostCreate(_savedInstanceState);
		
	}
	
	@Override
	public void onBackPressed() {
		if (getIntent().hasExtra("home")) {
			finish();
		}
		else {
			if (getIntent().hasExtra("m3u")) {
				startActivity(new Intent(TestActivity.this, ListviweActivity.class)); Animatoo.animateSlideLeft(TestActivity.this);
			}
			else {
				finish();
			}
		}
	}
	
	@Override
	public void onResume() {
		super.onResume();
		{
			AdRequest adRequest = new AdRequest.Builder().build();
			adview1.loadAd(adRequest);
		}
	}
	
	@Override
	public void onDestroy() {
		super.onDestroy();
		if (adview1 != null) {
			adview1.destroy();
		}
	}
	
	@Override
	public void onPause() {
		super.onPause();
		if (adview1 != null) {
			adview1.pause();
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