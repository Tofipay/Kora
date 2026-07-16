package com.tofixtv.app;

import android.animation.*;
import android.animation.ObjectAnimator;
import android.app.*;
import android.app.Activity;
import android.content.*;
import android.content.Intent;
import android.content.SharedPreferences;
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
import android.view.animation.AccelerateDecelerateInterpolator;
import android.view.animation.AccelerateInterpolator;
import android.view.animation.BounceInterpolator;
import android.view.animation.DecelerateInterpolator;
import android.view.animation.LinearInterpolator;
import android.webkit.*;
import android.widget.*;
import android.widget.ImageView;
import android.widget.LinearLayout;
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
import java.util.Timer;
import java.util.TimerTask;
import java.util.regex.*;
import meorg.jsoup.*;
import org.json.*;

public class EngActivity extends AppCompatActivity {
	
	private Timer _timer = new Timer();
	
	private boolean URL = false;
	
	private LinearLayout linear3;
	private ImageView imageview1;
	
	private Intent i = new Intent();
	private ObjectAnimator a = new ObjectAnimator();
	private ObjectAnimator b = new ObjectAnimator();
	private ObjectAnimator c = new ObjectAnimator();
	private ObjectAnimator d = new ObjectAnimator();
	private TimerTask t;
	private TimerTask ti;
	private SharedPreferences lodg;
	private Intent lo = new Intent();
	private ObjectAnimator a1 = new ObjectAnimator();
	private ObjectAnimator a2 = new ObjectAnimator();
	
	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		setContentView(R.layout.eng);
		initialize(_savedInstanceState);
		FirebaseApp.initializeApp(this);
		MobileAds.initialize(this);
		
		initializeLogic();
	}
	
	private void initialize(Bundle _savedInstanceState) {
		linear3 = findViewById(R.id.linear3);
		imageview1 = findViewById(R.id.imageview1);
		lodg = getSharedPreferences("lodg", Activity.MODE_PRIVATE);
		
		imageview1.setOnLongClickListener(new View.OnLongClickListener() {
			@Override
			public boolean onLongClick(View _view) {
				
				return true;
			}
		});
	}
	
	private void initializeLogic() {
		if (getIntent().getStringExtra("url") == null) {
			_start();
		}
		else {
			i.setClass(getApplicationContext(), MainActivity.class);
			i.putExtra("txt", getIntent().getStringExtra("txt"));
			i.putExtra("link", getIntent().getStringExtra("url"));
			i.putExtra("refererHeader", getIntent().getStringExtra("referer"));
			i.putExtra("userAgentHeader", getIntent().getStringExtra("user_agent"));
			i.putExtra("name", "user");
			startActivity(i);
		}
		if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
			    // تغيير لون شريط التنقل فقط إلى اللون الأسود #000000
			    getWindow().setNavigationBarColor(0xFF0A1324); // لون أسود
		}
	}
	
	@Override
	protected void onPostCreate(Bundle _savedInstanceState) {
		super.onPostCreate(_savedInstanceState);
		
	}
	public void _imageScaleXY() {
		
	}
	
	
	public void _LinearScaleY() {
		
	}
	
	
	public void _start() {
		// تكبير ثم تصغير في الاتجاه Y
		a1.setTarget(imageview1);
		a1.setPropertyName("scaleY");
		a1.setFloatValues((float)(1), (float)(0)); // تبدأ بالحجم الكامل وتنتهي بالحجم المصغر
		a1.setDuration((int)(1000));
		a1.setInterpolator(new AccelerateInterpolator());
		a1.start();
		
		// تكبير ثم تصغير في الاتجاه X
		a2.setTarget(imageview1);
		a2.setPropertyName("scaleX");
		a2.setFloatValues((float)(1), (float)(0)); // تبدأ بالحجم الكامل وتنتهي بالحجم المصغر
		a2.setDuration((int)(1000));
		a2.setInterpolator(new AccelerateInterpolator());
		a2.start();
		
		t = new TimerTask() {
				@Override
				public void run() {
						runOnUiThread(new Runnable() {
								@Override
								public void run() {
										i.setClass(getApplicationContext(), ListviweActivity.class);
										startActivity(i);
										Animatoo.animateSlideUp(EngActivity.this);
										finish();
								}
						});
				}
		};
		_timer.schedule(t, (int)(1000));
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