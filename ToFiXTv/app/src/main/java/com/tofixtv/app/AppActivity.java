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
import android.view.View;
import android.view.View.*;
import android.view.animation.*;
import android.webkit.*;
import android.widget.*;
import android.widget.Button;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
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
import okhttp3.*;

public class AppActivity extends AppCompatActivity {
	
	private Timer _timer = new Timer();
	private String _ad_unit_id;
	
	private double count = 0;
	private String txt1 = "";
	private String link = "";
	TofiData app;
	
	private ArrayList<HashMap<String, Object>> ma = new ArrayList<>();
	
	private LinearLayout linear1;
	private AdView adview1;
	private ScrollView vscroll1;
	private LinearLayout linear2;
	private ImageView imageview1;
	private TextView textview1;
	private LinearLayout linear3;
	private TextView textview3;
	private TextView Update;
	private Button button2;
	private Button button1;
	private TextView textview2;
	private TextView txt5;
	private TextView Version;
	
	private Intent i = new Intent();
	private TimerTask t;
	private TimerTask teimer;
	private Intent y = new Intent();
	private InterstitialAd ad;
	private InterstitialAdLoadCallback _ad_interstitial_ad_load_callback;
	private FullScreenContentCallback _ad_full_screen_content_callback;
	
	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		setContentView(R.layout.app);
		initialize(_savedInstanceState);
		FirebaseApp.initializeApp(this);
		MobileAds.initialize(this);
		_ad_unit_id = "ca-app-pub-6543754410644923/6155884856";
		initializeLogic();
	}
	
	private void initialize(Bundle _savedInstanceState) {
		linear1 = findViewById(R.id.linear1);
		adview1 = findViewById(R.id.adview1);
		vscroll1 = findViewById(R.id.vscroll1);
		linear2 = findViewById(R.id.linear2);
		imageview1 = findViewById(R.id.imageview1);
		textview1 = findViewById(R.id.textview1);
		linear3 = findViewById(R.id.linear3);
		textview3 = findViewById(R.id.textview3);
		Update = findViewById(R.id.Update);
		button2 = findViewById(R.id.button2);
		button1 = findViewById(R.id.button1);
		textview2 = findViewById(R.id.textview2);
		txt5 = findViewById(R.id.txt5);
		Version = findViewById(R.id.Version);
		
		button2.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				i.setAction(Intent.ACTION_VIEW);
				i.setData(Uri.parse(link));
				startActivity(i);
			}
		});
		
		button1.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (txt5.getText().toString().equals(txt1) || txt1.equals("")) {
					startActivity(new Intent(AppActivity.this, ListviweActivity.class)); Animatoo.animateSlideLeft(AppActivity.this);
				}
				else {
					finishAffinity();
				}
			}
		});
		
		_ad_interstitial_ad_load_callback = new InterstitialAdLoadCallback() {
			@Override
			public void onAdLoaded(InterstitialAd _param1) {
				ad = _param1;
				ad.setFullScreenContentCallback(_ad_full_screen_content_callback);
				if (ad != null) {
					ad.show(AppActivity.this);
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
		if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
			    // تغيير لون شريط التنقل فقط إلى اللون الأسود #000000
			    getWindow().setNavigationBarColor(0xFF192535); // لون أسود
		}
		button2.setBackground(new GradientDrawable() { public GradientDrawable getIns(int a, int b) { this.setCornerRadius(a); this.setColor(b); return this; } }.getIns((int)8, 0xFF01579B));
		{
			AdRequest adRequest = new AdRequest.Builder().build();
			adview1.loadAd(adRequest);
		}
		Version.setText(txt1);
		app = new TofiData(AppActivity.this ,"app" );
		app.addSingleEventValueListener(new TofiData.ValueEventListener(){
			 @Override
			public void onSuccess (String _childKey,HashMap<String,Object > _childValue){
				if (_childValue.containsKey("txt1")) {
					txt1 = _childValue.get("txt1").toString();
				}
				if (_childValue.containsKey("link")) {
					link = _childValue.get("link").toString();
				}
				if (txt5.getText().toString().equals(txt1) || txt1.equals("")) {
					button2.setVisibility(View.GONE);
					textview3.setVisibility(View.VISIBLE);
					txt5.setVisibility(View.VISIBLE);
					Update.setVisibility(View.GONE);
				}
				else {
					button2.setVisibility(View.VISIBLE);
					textview3.setVisibility(View.GONE);
					txt5.setVisibility(View.GONE);
					Update.setVisibility(View.VISIBLE);
					Version.setText(txt1);
				}
			}
			@Override
			public void onPreSuccess (String rawResponse,HashMap<String,Object > mapHeaders){
				 
			}
			@Override
			public void onError (String error){
				 
			}
			 
		});
		button2.setVisibility(View.GONE);
		textview3.setVisibility(View.VISIBLE);
		txt5.setVisibility(View.VISIBLE);
		Update.setVisibility(View.GONE);
		if (Build.VERSION.SDK_INT > Build.VERSION_CODES.KITKAT) {
			Window w =AppActivity.this.getWindow();
			w.clearFlags(WindowManager.LayoutParams.FLAG_TRANSLUCENT_STATUS);
			w.addFlags(WindowManager.LayoutParams.FLAG_DRAWS_SYSTEM_BAR_BACKGROUNDS); w.setStatusBarColor(0xFF192535);
		}
	}
	
	@Override
	public void onBackPressed() {
		if (txt5.getText().toString().equals(txt1) || txt1.equals("")) {
			startActivity(new Intent(AppActivity.this, ListviweActivity.class)); Animatoo.animateSlideLeft(AppActivity.this);
		}
		else {
			if (count == 0) {
				SketchwareUtil.showMessage(getApplicationContext(), "Press back again to exit!");
				count++;
				teimer = new TimerTask() {
					@Override
					public void run() {
						runOnUiThread(new Runnable() {
							@Override
							public void run() {
								count = 0;
							}
						});
					}
				};
				_timer.schedule(teimer, (int)(2000));
			}
			else {
				y.setFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP);
				finishAffinity();
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