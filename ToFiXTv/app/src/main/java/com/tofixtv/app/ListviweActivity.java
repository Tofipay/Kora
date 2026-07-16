package com.tofixtv.app;

import android.animation.*;
import android.app.*;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.*;
import android.content.DialogInterface;
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
import android.text.Editable;
import android.text.TextWatcher;
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
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.CompoundButton;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.Spinner;
import android.widget.TextView;
import androidx.annotation.*;
import androidx.annotation.experimental.*;
import androidx.appcompat.app.ActionBarDrawerToggle;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;
import androidx.coordinatorlayout.widget.CoordinatorLayout;
import androidx.core.view.GravityCompat;
import androidx.drawerlayout.widget.DrawerLayout;
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
import com.google.android.material.appbar.AppBarLayout;
import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.google.android.material.textfield.*;
import com.google.firebase.FirebaseApp;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;
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
import androidx.core.widget.NestedScrollView;
import okhttp3.*;

public class ListviweActivity extends AppCompatActivity {
	
	private Timer _timer = new Timer();
	private String _ad_unit_id;
	
	private Toolbar _toolbar;
	private AppBarLayout _app_bar;
	private CoordinatorLayout _coordinator;
	private FloatingActionButton _fab;
	private DrawerLayout _drawer;
	private String fontName = "";
	private String typeace = "";
	private HashMap<String, Object> map = new HashMap<>();
	private String data_ = "";
	private double n = 0;
	private double ch1 = 0;
	private double ch2 = 0;
	private String playername = "";
	private double count = 0;
	private String m3u = "";
	private String type_linl = "";
	private String pos = "";
	private String pos2 = "";
	TofiData passed;
	TofiData app;
	private String txt1 = "";
	
	private ArrayList<HashMap<String, Object>> map2 = new ArrayList<>();
	private ArrayList<String> uris = new ArrayList<>();
	private ArrayList<String> drmtypes = new ArrayList<>();
	
	private LinearLayout linear1;
	private LinearLayout linear2;
	private AdView adview1;
	private ImageView imageview3;
	private ImageView imageview2;
	private TextView textview1;
	private WebView webview1;
	private RecyclerView links;
	private TextView textview3;
	private NestedScrollView vscroll1;
	private LinearLayout linear;
	private TextView Update;
	private TextView Version;
	private TextView textview2;
	private TextView textview4;
	private TextInputLayout textinputlayout1;
	private LinearLayout linear4;
	private EditText urlname;
	private RecyclerView rv;
	private TextInputLayout textinputlayout3;
	private TextInputLayout textinputlayout4;
	private TextInputLayout textinputlayout6;
	private TextInputLayout textinputlayout5;
	private LinearLayout drmLin;
	private LinearLayout linear5;
	private LinearLayout linear3;
	private Button button1;
	private EditText edittext1;
	private TextInputLayout textinputlayout2;
	private TextView textview7;
	private EditText edittext2;
	private EditText edittext3;
	private EditText edittext4;
	private EditText origin;
	private EditText cookieseditText;
	private TextView textview8;
	private Spinner spinner1;
	private CheckBox checkbox4;
	private CheckBox checkbox3;
	private CheckBox checkbox1;
	private CheckBox checkbox2;
	private LinearLayout _drawer_linear4;
	private ScrollView _drawer_vscroll1;
	private LinearLayout _drawer_linear2;
	private ImageView _drawer_Privacy_Policy;
	private ImageView _drawer_Location;
	private ImageView _drawer_Telegram;
	private ImageView _drawer_Rating;
	private ImageView _drawer_Share;
	private ImageView _drawer_App;
	
	private Intent i = new Intent();
	private SharedPreferences data;
	private TimerTask t;
	private AlertDialog.Builder d0;
	private Intent nnn = new Intent();
	private TimerTask open;
	private Intent Exit = new Intent();
	private TimerTask teimr;
	private InterstitialAd ad;
	private InterstitialAdLoadCallback _ad_interstitial_ad_load_callback;
	private FullScreenContentCallback _ad_full_screen_content_callback;
	private Intent fi = new Intent();
	private Intent dd = new Intent();
	private AlertDialog d;
	private AlertDialog.Builder dg;
	private Intent tele = new Intent();
	private AlertDialog.Builder c;
	private Intent tog = new Intent();
	
	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		setContentView(R.layout.listviwe);
		initialize(_savedInstanceState);
		FirebaseApp.initializeApp(this);
		MobileAds.initialize(this);
		_ad_unit_id = "ca-app-pub-6543754410644923/6155884856";
		initializeLogic();
	}
	
	private void initialize(Bundle _savedInstanceState) {
		_app_bar = findViewById(R.id._app_bar);
		_coordinator = findViewById(R.id._coordinator);
		_toolbar = findViewById(R.id._toolbar);
		setSupportActionBar(_toolbar);
		getSupportActionBar().setDisplayHomeAsUpEnabled(true);
		getSupportActionBar().setHomeButtonEnabled(true);
		_toolbar.setNavigationOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _v) {
				onBackPressed();
			}
		});
		_fab = findViewById(R.id._fab);
		
		_drawer = findViewById(R.id._drawer);
		ActionBarDrawerToggle _toggle = new ActionBarDrawerToggle(ListviweActivity.this, _drawer, _toolbar, R.string.app_name, R.string.app_name);
		_drawer.addDrawerListener(_toggle);
		_toggle.syncState();
		
		LinearLayout _nav_view = findViewById(R.id._nav_view);
		
		linear1 = findViewById(R.id.linear1);
		linear2 = findViewById(R.id.linear2);
		adview1 = findViewById(R.id.adview1);
		imageview3 = findViewById(R.id.imageview3);
		imageview2 = findViewById(R.id.imageview2);
		textview1 = findViewById(R.id.textview1);
		webview1 = findViewById(R.id.webview1);
		webview1.getSettings().setJavaScriptEnabled(true);
		webview1.getSettings().setSupportZoom(true);
		links = findViewById(R.id.links);
		textview3 = findViewById(R.id.textview3);
		vscroll1 = findViewById(R.id.vscroll1);
		linear = findViewById(R.id.linear);
		Update = findViewById(R.id.Update);
		Version = findViewById(R.id.Version);
		textview2 = findViewById(R.id.textview2);
		textview4 = findViewById(R.id.textview4);
		textinputlayout1 = findViewById(R.id.textinputlayout1);
		linear4 = findViewById(R.id.linear4);
		urlname = findViewById(R.id.urlname);
		rv = findViewById(R.id.rv);
		textinputlayout3 = findViewById(R.id.textinputlayout3);
		textinputlayout4 = findViewById(R.id.textinputlayout4);
		textinputlayout6 = findViewById(R.id.textinputlayout6);
		textinputlayout5 = findViewById(R.id.textinputlayout5);
		drmLin = findViewById(R.id.drmLin);
		linear5 = findViewById(R.id.linear5);
		linear3 = findViewById(R.id.linear3);
		button1 = findViewById(R.id.button1);
		edittext1 = findViewById(R.id.edittext1);
		textinputlayout2 = findViewById(R.id.textinputlayout2);
		textview7 = findViewById(R.id.textview7);
		edittext2 = findViewById(R.id.edittext2);
		edittext3 = findViewById(R.id.edittext3);
		edittext4 = findViewById(R.id.edittext4);
		origin = findViewById(R.id.origin);
		cookieseditText = findViewById(R.id.cookieseditText);
		textview8 = findViewById(R.id.textview8);
		spinner1 = findViewById(R.id.spinner1);
		checkbox4 = findViewById(R.id.checkbox4);
		checkbox3 = findViewById(R.id.checkbox3);
		checkbox1 = findViewById(R.id.checkbox1);
		checkbox2 = findViewById(R.id.checkbox2);
		_drawer_linear4 = _nav_view.findViewById(R.id.linear4);
		_drawer_vscroll1 = _nav_view.findViewById(R.id.vscroll1);
		_drawer_linear2 = _nav_view.findViewById(R.id.linear2);
		_drawer_Privacy_Policy = _nav_view.findViewById(R.id.Privacy_Policy);
		_drawer_Location = _nav_view.findViewById(R.id.Location);
		_drawer_Telegram = _nav_view.findViewById(R.id.Telegram);
		_drawer_Rating = _nav_view.findViewById(R.id.Rating);
		_drawer_Share = _nav_view.findViewById(R.id.Share);
		_drawer_App = _nav_view.findViewById(R.id.App);
		data = getSharedPreferences("data", Activity.MODE_PRIVATE);
		d0 = new AlertDialog.Builder(this);
		dg = new AlertDialog.Builder(this);
		c = new AlertDialog.Builder(this);
		
		imageview3.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (_drawer.isDrawerOpen(GravityCompat.START)) {
						_drawer.closeDrawer(GravityCompat.START);
				}
				else {
						_drawer.openDrawer(GravityCompat.START);
				}
			}
		});
		
		imageview2.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				_fab.show();
				imageview2.setVisibility(View.GONE);
				linear.setVisibility(View.GONE);
				if (map2.size() == 0) {
					textview3.setVisibility(View.VISIBLE);
				}
				else {
					links.setVisibility(View.VISIBLE);
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
		
		links.addOnScrollListener(new RecyclerView.OnScrollListener() {
			@Override
			public void onScrollStateChanged(RecyclerView recyclerView, int _scrollState) {
				super.onScrollStateChanged(recyclerView, _scrollState);
				
			}
			
			@Override
			public void onScrolled(RecyclerView recyclerView, int _offsetX, int _offsetY) {
				super.onScrolled(recyclerView, _offsetX, _offsetY);
				
			}
		});
		
		button1.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (edittext2.getText().toString().equals("")) {
					edittext2.setError("Please Enter a Valid URL");
				}
				else {
					textview7.performClick();
				}
				if (edittext1.getText().toString().equals("")) {
					edittext1.setError("Please Enter Title");
				}
				else {
					if (uris.size() == 0) {
						edittext2.setError("Please Enter a Valid URL");
					}
					else {
						if (n == 0.5d) {
							if (edittext3.getText().toString().equals("")) {
								map = new HashMap<>();
								map.put("txt", edittext1.getText().toString());
								map.put("Url", edittext2.getText().toString());
								map.put("uris", uris);
								map.put("referer", edittext4.getText().toString());
								map.put("user_agent", "");
								map.put("player", playername);
								map.put("cookies", cookieseditText.getText().toString());
								if (checkbox3.isChecked()) {
									map.put("isDrm", "true");
								}
								if (checkbox4.isChecked()) {
									map.put("ism3u", "");
								}
								map.put("Origin", origin.getText().toString());
								map2.add(map);
								data.edit().putString("data", new Gson().toJson(map2)).commit();
								linear.setVisibility(View.GONE);
								textview3.setVisibility(View.GONE);
								imageview2.setVisibility(View.GONE);
								_fab.show();
								links.setVisibility(View.VISIBLE);
								links.setAdapter(new LinksAdapter(map2));
								edittext1.setText("");
								edittext2.setText("");
								edittext3.setText("");
								edittext4.setText("");
							}
							else {
								map = new HashMap<>();
								map.put("txt", edittext1.getText().toString());
								map.put("Url", edittext2.getText().toString());
								map.put("referer", edittext4.getText().toString());
								map.put("user_agent", edittext3.getText().toString());
								map.put("player", playername);
								map.put("cookies", cookieseditText.getText().toString());
								map.put("uris", uris);
								if (checkbox3.isChecked()) {
									map.put("isDrm", "true");
								}
								if (checkbox4.isChecked()) {
									map.put("ism3u", "");
								}
								map.put("Origin", origin.getText().toString());
								map2.add(map);
								data.edit().putString("data", new Gson().toJson(map2)).commit();
								linear.setVisibility(View.GONE);
								textview3.setVisibility(View.GONE);
								imageview2.setVisibility(View.GONE);
								_fab.show();
								links.setVisibility(View.VISIBLE);
								links.setAdapter(new LinksAdapter(map2));
								edittext1.setText("");
								edittext2.setText("");
								edittext3.setText("");
								edittext4.setText("");
							}
						}
						else {
							if (edittext3.getText().toString().equals("")) {
								map = new HashMap<>();
								map.put("txt", edittext1.getText().toString());
								map.put("Url", edittext2.getText().toString());
								map.put("referer", edittext4.getText().toString());
								map.put("user_agent", "");
								map.put("player", playername);
								map.put("uris", uris);
								map.put("cookies", cookieseditText.getText().toString());
								if (checkbox3.isChecked()) {
									map.put("isDrm", "true");
								}
								if (checkbox4.isChecked()) {
									map.put("ism3u", "");
								}
								map.put("Origin", origin.getText().toString());
								map2.add(map);
								map2.remove((int)(n));
								data.edit().putString("data", new Gson().toJson(map2)).commit();
								linear.setVisibility(View.GONE);
								textview3.setVisibility(View.GONE);
								imageview2.setVisibility(View.GONE);
								_fab.show();
								links.setVisibility(View.VISIBLE);
								links.setAdapter(new LinksAdapter(map2));
								edittext1.setText("");
								edittext2.setText("");
								edittext3.setText("");
								edittext4.setText("");
								n = 0.5d;
							}
							else {
								map = new HashMap<>();
								map.put("txt", edittext1.getText().toString());
								map.put("Url", edittext2.getText().toString());
								map.put("referer", edittext4.getText().toString());
								map.put("user_agent", edittext3.getText().toString());
								map.put("player", playername);
								map.put("cookies", cookieseditText.getText().toString());
								map.put("uris", uris);
								if (checkbox3.isChecked()) {
									map.put("isDrm", "true");
								}
								if (checkbox4.isChecked()) {
									map.put("ism3u", "");
								}
								map.put("Origin", origin.getText().toString());
								map2.add(map);
								map2.remove((int)(n));
								data.edit().putString("data", new Gson().toJson(map2)).commit();
								linear.setVisibility(View.GONE);
								textview3.setVisibility(View.GONE);
								imageview2.setVisibility(View.GONE);
								_fab.show();
								links.setVisibility(View.VISIBLE);
								links.setAdapter(new LinksAdapter(map2));
								edittext1.setText("");
								edittext2.setText("");
								edittext3.setText("");
								edittext4.setText("");
								n = 0.5d;
							}
						}
					}
				}
			}
		});
		
		edittext1.addTextChangedListener(new TextWatcher() {
			@Override
			public void onTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				final String _charSeq = _param1.toString();
				_setClearIconVisible(edittext1, _charSeq.length() > 0);
			}
			
			@Override
			public void beforeTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				
			}
			
			@Override
			public void afterTextChanged(Editable _param1) {
				
			}
		});
		
		textview7.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				if (uris!=null) {
					if (urlname.getText().toString().equals("")) {
						uris.add(edittext2.getText().toString());
					}
					else {
						Uri uri = Uri.parse(edittext2.getText().toString());
						Uri newUri = uri.buildUpon()
						                .appendQueryParameter("tofiUrlname", urlname.getText().toString())
						.build();
						String newUrl = newUri.toString();
						       
						uris.add(newUrl);
						urlname.setText("");
					}
					rv.setAdapter(new ListAdapter (uris,ListviweActivity.this));
					
					
					edittext2.setText("");
				}
			}
		});
		
		edittext2.addTextChangedListener(new TextWatcher() {
			@Override
			public void onTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				final String _charSeq = _param1.toString();
				if ((_charSeq.trim().length() > 0) && android.webkit.URLUtil.isNetworkUrl(_charSeq)) {
					textview7.setVisibility(View.VISIBLE);
					if (_charSeq.matches(".*\\bm3u\\b.*")) {
						checkbox4.setChecked(true);
					}
					else {
						checkbox4.setChecked(false);
					}
				}
				else {
					textview7.setVisibility(View.GONE);
				}
				_setClearIconVisible(edittext2, _charSeq.length() > 0);
			}
			
			@Override
			public void beforeTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				
			}
			
			@Override
			public void afterTextChanged(Editable _param1) {
				
			}
		});
		
		edittext3.addTextChangedListener(new TextWatcher() {
			@Override
			public void onTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				final String _charSeq = _param1.toString();
				_setClearIconVisible(edittext3, _charSeq.length() > 0);
			}
			
			@Override
			public void beforeTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				
			}
			
			@Override
			public void afterTextChanged(Editable _param1) {
				
			}
		});
		
		edittext4.addTextChangedListener(new TextWatcher() {
			@Override
			public void onTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				final String _charSeq = _param1.toString();
				_setClearIconVisible(edittext4, _charSeq.length() > 0);
			}
			
			@Override
			public void beforeTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				
			}
			
			@Override
			public void afterTextChanged(Editable _param1) {
				
			}
		});
		
		origin.addTextChangedListener(new TextWatcher() {
			@Override
			public void onTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				final String _charSeq = _param1.toString();
				_setClearIconVisible(origin, _charSeq.length() > 0);
			}
			
			@Override
			public void beforeTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				
			}
			
			@Override
			public void afterTextChanged(Editable _param1) {
				
			}
		});
		
		cookieseditText.addTextChangedListener(new TextWatcher() {
			@Override
			public void onTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				final String _charSeq = _param1.toString();
				_setClearIconVisible(cookieseditText, _charSeq.length() > 0);
			}
			
			@Override
			public void beforeTextChanged(CharSequence _param1, int _param2, int _param3, int _param4) {
				
			}
			
			@Override
			public void afterTextChanged(Editable _param1) {
				
			}
		});
		
		checkbox4.setOnCheckedChangeListener(new CompoundButton.OnCheckedChangeListener() {
			@Override
			public void onCheckedChanged(CompoundButton _param1, boolean _param2) {
				final boolean _isChecked = _param2;
				if (_isChecked) {
					checkbox2.setChecked(false);
					checkbox3.setChecked(false);
					playername = "M3u";
				}
			}
		});
		
		checkbox3.setOnCheckedChangeListener(new CompoundButton.OnCheckedChangeListener() {
			@Override
			public void onCheckedChanged(CompoundButton _param1, boolean _param2) {
				final boolean _isChecked = _param2;
				if (_isChecked) {
					checkbox1.setChecked(false);
					checkbox4.setChecked(false);
					checkbox2.setChecked(false);
					playername = "Mpd";
				}
			}
		});
		
		checkbox1.setOnCheckedChangeListener(new CompoundButton.OnCheckedChangeListener() {
			@Override
			public void onCheckedChanged(CompoundButton _param1, boolean _param2) {
				final boolean _isChecked = _param2;
				if (_isChecked) {
					checkbox2.setChecked(false);
					checkbox3.setChecked(false);
					playername = "Exo";
				}
			}
		});
		
		checkbox2.setOnCheckedChangeListener(new CompoundButton.OnCheckedChangeListener() {
			@Override
			public void onCheckedChanged(CompoundButton _param1, boolean _param2) {
				final boolean _isChecked = _param2;
				if (_isChecked) {
					checkbox1.setChecked(false);
					checkbox3.setChecked(false);
					checkbox4.setChecked(false);
					playername = "Web";
				}
			}
		});
		
		_fab.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				links.setVisibility(View.GONE);
				textview3.setVisibility(View.GONE);
				_fab.hide();
				imageview2.setVisibility(View.VISIBLE);
				linear.setVisibility(View.VISIBLE);
				checkbox1.setChecked(true);
				checkbox2.setChecked(false);
				checkbox4.setChecked(false);
				uris = new ArrayList<String>();
				rv.setAdapter(new ListAdapter (uris,ListviweActivity.this));
				
				edittext1.setText("");
				edittext2.setText("");
				edittext3.setText("");
				edittext4.setText("");
				origin.setText("");
				cookieseditText.setText("");
			}
		});
		
		_drawer_Privacy_Policy.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				startActivity(new Intent(ListviweActivity.this, PrivacyActivity.class)); Animatoo.animateSlideLeft(ListviweActivity.this);
			}
		});
		
		_drawer_Location.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				tele.setAction(Intent.ACTION_VIEW);
				tele.setData(Uri.parse("https://tofi.blog/txt/"));
				startActivity(tele);
			}
		});
		
		_drawer_Telegram.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				tele.setAction(Intent.ACTION_VIEW);
				tele.setData(Uri.parse("https://t.me/tofi_tv"));
				startActivity(tele);
			}
		});
		
		_drawer_Rating.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				tele.setAction(Intent.ACTION_VIEW);
				tele.setData(Uri.parse("https://play.google.com/store/apps/details?id=com.tofi.operator"));
				startActivity(tele);
			}
		});
		
		_drawer_Share.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				Intent fi = new Intent(Intent.ACTION_SEND);
				fi.setType("text/plain");
				fi.putExtra(Intent.EXTRA_SUBJECT, "Check out this app!");
				fi.putExtra(Intent.EXTRA_TEXT, "https://play.google.com/store/apps/details?id=com.tofi.operator");
				startActivity(Intent.createChooser(fi, "Share via"));
			}
		});
		
		_drawer_App.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				startActivity(new Intent(ListviweActivity.this, AppActivity.class)); Animatoo.animateSlideLeft(ListviweActivity.this);
			}
		});
		
		_ad_interstitial_ad_load_callback = new InterstitialAdLoadCallback() {
			@Override
			public void onAdLoaded(InterstitialAd _param1) {
				ad = _param1;
				ad.setFullScreenContentCallback(_ad_full_screen_content_callback);
				if (ad != null) {
					ad.show(ListviweActivity.this);
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
		//listview1.setSelector(android.R.color.transparent); // Set listSelector to transparent
		rv.setLayoutManager(new LinearLayoutManager(this));
		links.setLayoutManager(new LinearLayoutManager(this));
		drmtypes.add("WideVine");
		drmtypes.add("Clear Key");
		spinner1.setAdapter(new ArrayAdapter<String>(getBaseContext(), android.R.layout.simple_spinner_dropdown_item, drmtypes));
		n = 0.5d;
		if (!data.getString("data", "").equals("")) {
			map2 = new Gson().fromJson(data.getString("data", ""), new TypeToken<ArrayList<HashMap<String, Object>>>(){}.getType());
			links.setAdapter(new LinksAdapter(map2));
		}
		textview3.setVisibility(View.GONE);
		imageview2.setVisibility(View.GONE);
		linear.setVisibility(View.GONE);
		links.setVisibility(View.GONE);
		_fab.show();
		if (map2.size() == 0) {
			textview3.setVisibility(View.VISIBLE);
			t = new TimerTask() {
				@Override
				public void run() {
					runOnUiThread(new Runnable() {
						@Override
						public void run() {
							textview3.setVisibility(View.GONE);
							_fab.hide();
							imageview2.setVisibility(View.VISIBLE);
							linear.setVisibility(View.VISIBLE);
						}
					});
				}
			};
			_timer.schedule(t, (int)(500));
		}
		else {
			links.setVisibility(View.VISIBLE);
		}
		_changeActivityFont("neosansarabic");
		if (getIntent().hasExtra("player")) {
			if (getIntent().getStringExtra("player").equals("web")) {
				checkbox1.setChecked(false);
				checkbox2.setChecked(true);
				checkbox3.setChecked(false);
			}
			else {
				if (getIntent().hasExtra("isDrm")) {
					checkbox1.setChecked(false);
					checkbox2.setChecked(false);
					checkbox3.setChecked(true);
				}
				else {
					checkbox1.setChecked(true);
					checkbox2.setChecked(false);
					checkbox3.setChecked(false);
				}
			}
		}
		else {
			checkbox1.setChecked(true);
			checkbox2.setChecked(false);
			checkbox3.setChecked(false);
		}
		if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
			    // تغيير لون شريط التنقل فقط إلى اللون الأسود #000000
			    getWindow().setNavigationBarColor(0xFF192535); // لون أسود
		}
		// تحويل اللون المخصص إلى قيمة int
		int startColor = Color.parseColor("#FF0A1324");
		int endColor = Color.parseColor("#FF0A1324"); // يمكن تغييره إلى لون آخر إذا لزم الأمر
		
		// إنشاء ObjectAnimator لتغيير لون الخلفية
		ObjectAnimator animator = ObjectAnimator.ofInt(
		    _fab, 
		    "backgroundTint", 
		    startColor, 
		    endColor
		);
		
		// تعيين مدة التغيير بالمللي ثانية
		animator.setDuration(10000L);
		
		// تعيين ArgbEvaluator لتقييم الألوان
		animator.setEvaluator(new ArgbEvaluator());
		
		// تعيين interpolator لتحديد سرعة التحريك
		animator.setInterpolator(new DecelerateInterpolator(2));
		
		// إضافة listener لتحديث اللون أثناء التحريك
		animator.addUpdateListener(new ValueAnimator.AnimatorUpdateListener() {
			    @Override
			    public void onAnimationUpdate(ValueAnimator animation) {
				        int animatedValue = (int) animation.getAnimatedValue();
				        _fab.setBackgroundTintList(ColorStateList.valueOf(animatedValue));
				    }
		});
		
		// بدء التحريك
		animator.start();
		linear.setBackground(new GradientDrawable() { public GradientDrawable getIns(int a, int b) { this.setCornerRadius(a); this.setColor(b); return this; } }.getIns((int)15, 0xFFECEFF1));
		getSupportActionBar().hide();
		final LinearLayout _nav_view = (LinearLayout) findViewById(R.id._nav_view); _nav_view.setBackgroundDrawable(new android.graphics.drawable.ColorDrawable(Color.TRANSPARENT));
		_drawer_linear2.setBackground(new GradientDrawable() { public GradientDrawable getIns(int a, int b) { this.setCornerRadius(a); this.setColor(b); return this; } }.getIns((int)13, 0xFFFFFFFF));
		{
			AdRequest adRequest = new AdRequest.Builder().build();
			adview1.loadAd(adRequest);
		}
		_setClearIconVisible(origin, false);
		origin.setOnTouchListener(new View.OnTouchListener(){
				@Override
				public boolean onTouch(View _view, MotionEvent _motionEvent){
						
				if (origin.getCompoundDrawables()[2] != null) { // Check if clear icon is shown
					                    // Check if the touch event is inside the clear icon area
					                    if (_motionEvent.getAction() == MotionEvent.ACTION_UP) {
						                        if (_motionEvent.getX() >= (origin.getRight() - origin.getCompoundDrawables()[2].getBounds().width())) {
							                            // Clear the text
							                         
							origin.requestFocus();
							origin.setText("");
							
							return true;
						}}}
				return false;
						}
				});
		_setClearIconVisible(edittext2, false);
		edittext2.setOnTouchListener(new View.OnTouchListener(){
				@Override
				public boolean onTouch(View _view, MotionEvent _motionEvent){
						
				if (edittext2.getCompoundDrawables()[2] != null) { // Check if clear icon is shown
					                    // Check if the touch event is inside the clear icon area
					                    if (_motionEvent.getAction() == MotionEvent.ACTION_UP) {
						                        if (_motionEvent.getX() >= (edittext2.getRight() - edittext2.getCompoundDrawables()[2].getBounds().width())) {
							                            // Clear the text
							                         
							edittext2.requestFocus();
							edittext2.setText("");
							
							return true;
						}}}
				return false;
						}
				});
		_setClearIconVisible(edittext3, false);
		edittext3.setOnTouchListener(new View.OnTouchListener(){
				@Override
				public boolean onTouch(View _view, MotionEvent _motionEvent){
						
				if (edittext3.getCompoundDrawables()[2] != null) { // Check if clear icon is shown
					                    // Check if the touch event is inside the clear icon area
					                    if (_motionEvent.getAction() == MotionEvent.ACTION_UP) {
						                        if (_motionEvent.getX() >= (edittext3.getRight() - edittext3.getCompoundDrawables()[2].getBounds().width())) {
							                            // Clear the text
							                         
							edittext3.requestFocus();
							edittext3.setText("");
							
							return true;
						}}}
				return false;
						}
				});
		_setClearIconVisible(edittext4, false);
		edittext4.setOnTouchListener(new View.OnTouchListener(){
				@Override
				public boolean onTouch(View _view, MotionEvent _motionEvent){
						
				if (edittext4.getCompoundDrawables()[2] != null) { // Check if clear icon is shown
					                    // Check if the touch event is inside the clear icon area
					                    if (_motionEvent.getAction() == MotionEvent.ACTION_UP) {
						                        if (_motionEvent.getX() >= (edittext4.getRight() - edittext4.getCompoundDrawables()[2].getBounds().width())) {
							                            // Clear the text
							                         
							edittext4.requestFocus();
							edittext4.setText("");
							
							return true;
						}}}
				return false;
						}
				});
		_setClearIconVisible(edittext1, false);
		edittext1.setOnTouchListener(new View.OnTouchListener(){
				@Override
				public boolean onTouch(View _view, MotionEvent _motionEvent){
						
				if (edittext1.getCompoundDrawables()[2] != null) { // Check if clear icon is shown
					                    // Check if the touch event is inside the clear icon area
					                    if (_motionEvent.getAction() == MotionEvent.ACTION_UP) {
						                        if (_motionEvent.getX() >= (edittext1.getRight() - edittext1.getCompoundDrawables()[2].getBounds().width())) {
							                            // Clear the text
							                         
							edittext1.requestFocus();
							edittext1.setText("");
							
							return true;
						}}}
				return false;
						}
				});
		_setClearIconVisible(cookieseditText, false);
		cookieseditText.setOnTouchListener(new View.OnTouchListener(){
				@Override
				public boolean onTouch(View _view, MotionEvent _motionEvent){
						
				if (cookieseditText.getCompoundDrawables()[2] != null) { // Check if clear icon is shown
					                    // Check if the touch event is inside the clear icon area
					                    if (_motionEvent.getAction() == MotionEvent.ACTION_UP) {
						                        if (_motionEvent.getX() >= (cookieseditText.getRight() - cookieseditText.getCompoundDrawables()[2].getBounds().width())) {
							                            // Clear the text
							                         
							cookieseditText.requestFocus();
							cookieseditText.setText("");
							
							return true;
						}}}
				return false;
						}
				});
		Update.setText(txt1);
		app = new TofiData(ListviweActivity.this ,"app" );
		app.addSingleEventValueListener(new TofiData.ValueEventListener(){
			 @Override
			public void onSuccess (String _childKey,HashMap<String,Object > _childValue){
				if (_childValue.containsKey("txt1")) {
					txt1 = _childValue.get("txt1").toString();
				}
				if (Version.getText().toString().equals(txt1) || txt1.equals("")) {
					
				}
				else {
					i.setClass(getApplicationContext(), AppActivity.class);
					startActivity(i);
					finish();
				}
			}
			@Override
			public void onPreSuccess (String rawResponse,HashMap<String,Object > mapHeaders){
				 
			}
			@Override
			public void onError (String error){
				 
			}
			 
		});
	}
	
	@Override
	public void onBackPressed() {
		if (imageview2.getVisibility() == View.VISIBLE) {
			imageview2.performClick();
		}
		else {
			if (count == 0) {
				SketchwareUtil.showMessage(getApplicationContext(), "Press back again to exit!");
				count++;
				teimr = new TimerTask() {
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
				_timer.schedule(teimr, (int)(2000));
			}
			else {
				Exit.setFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP);
				finishAffinity();
			}
		}
	}
	
	
	@Override
	protected void onPostCreate(Bundle _savedInstanceState) {
		super.onPostCreate(_savedInstanceState);
		
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
	public void _changeActivityFont(final String _fontname) {
		fontName = "fonts/".concat(_fontname.concat(".ttf"));
		overrideFonts(this,getWindow().getDecorView()); 
	} 
	private void overrideFonts(final android.content.Context context, final View v) {
		
		try {
			Typeface 
			typeace = Typeface.createFromAsset(getAssets(), fontName);;
			if ((v instanceof ViewGroup)) {
				ViewGroup vg = (ViewGroup) v;
				for (int i = 0;
				i < vg.getChildCount();
				i++) {
					View child = vg.getChildAt(i);
					overrideFonts(context, child);
				}
			}
			else {
				if ((v instanceof TextView)) {
					((TextView) v).setTypeface(typeace);
				}
				else {
					if ((v instanceof EditText )) {
						((EditText) v).setTypeface(typeace);
					}
					else {
						if ((v instanceof Button)) {
							((Button) v).setTypeface(typeace);
						}
					}
				}
			}
		}
		catch(Exception e)
		
		{
			SketchwareUtil.showMessage(getApplicationContext(), "Error Loading Font");
		};
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
	
	
	public void _setClearIconVisible(final TextView _editText, final boolean _shoulClear) {
		Drawable clearIcon = getResources().getDrawable(R.drawable.clear);
		        
		
		        _editText.setCompoundDrawablesWithIntrinsicBounds(null, null, _shoulClear ? clearIcon : null, null);
		    
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
	
	public class LinksAdapter extends RecyclerView.Adapter<LinksAdapter.ViewHolder> {
		
		ArrayList<HashMap<String, Object>> _data;
		
		public LinksAdapter(ArrayList<HashMap<String, Object>> _arr) {
			_data = _arr;
		}
		
		@Override
		public ViewHolder onCreateViewHolder(ViewGroup parent, int viewType) {
			LayoutInflater _inflater = getLayoutInflater();
			View _v = _inflater.inflate(R.layout.save, null);
			RecyclerView.LayoutParams _lp = new RecyclerView.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
			_v.setLayoutParams(_lp);
			return new ViewHolder(_v);
		}
		
		@Override
		public void onBindViewHolder(ViewHolder _holder, final int _position) {
			View _view = _holder.itemView;
			
			final LinearLayout linear1 = _view.findViewById(R.id.linear1);
			final LinearLayout linear3 = _view.findViewById(R.id.linear3);
			final LinearLayout linear2 = _view.findViewById(R.id.linear2);
			final TextView textview5 = _view.findViewById(R.id.textview5);
			final LinearLayout linear4 = _view.findViewById(R.id.linear4);
			final ImageView imageview1 = _view.findViewById(R.id.imageview1);
			final TextView textview1 = _view.findViewById(R.id.textview1);
			final TextView textview3 = _view.findViewById(R.id.textview3);
			final TextView textview4 = _view.findViewById(R.id.textview4);
			final TextView textview2 = _view.findViewById(R.id.textview2);
			final LinearLayout linear5 = _view.findViewById(R.id.linear5);
			
			linear2.setBackground(new GradientDrawable() { public GradientDrawable getIns(int a, int b) { this.setCornerRadius(a); this.setColor(b); return this; } }.getIns((int)20, 0xFFFFFFFF));
			textview1.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/neosansarabic.ttf"), 1);
			textview2.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/neosansarabic.ttf"), 1);
			textview1.setText(map2.get((int)_position).get("txt").toString().toUpperCase());
			textview5.setText(map2.get((int)_position).get("player").toString());
			if (_data.get((int)_position).containsKey("uris")) {
				textview2.setText(((ArrayList<String>)map2.get(_position).get("uris")).get(0));
			}
			else {
				textview2.setText(map2.get((int)_position).get("Url").toString());
			}
			textview3.setText(map2.get((int)_position).get("user_agent").toString());
			textview4.setText(map2.get((int)_position).get("referer").toString());
			int[] colors = new int[] {
				    0xFF80DEEA, // Light Cyan
				    0xFF81C784, // Light Green
				    0xFF64B5F6, // Light Blue
				    0xFF5C6BC0, // Indigo
				    0xFFB0BEC5, // Light Green (Repeated for consistency)
				    0xFF4DD0E1, // Lighter Cyan
				    0xFF66BB6A, // Medium Green
				    0xFFFFF59D, // Sky Blue
				    0xFFFFCC80, // Darker Indigo
				    0xFFAED581, // Light Olive
				    0xFF26C6DA, // Brighter Cyan
				    0xFFB39DDB, // Greenish Lime
				    0xFFAED581, // Brighter Blue
				    0xFF283593, // Dark Indigo
				    0xFFDCE775, // Light Green (Repeated)
				    0xFF00ACC1, // Cyan
				    0xFFA1887F, // Dark Green
				    0xFF2196F3, // Regular Blue
				    0xFF558B2F, // Dark Olive
				    0xFF64B5F6  // Light Blue (Repeated)
			};
			
			// اختر اللون بناءً على الموضع.
			int colorIndex = _position % colors.length;
			imageview1.setColorFilter(colors[colorIndex], PorterDuff.Mode.MULTIPLY);
			textview5.setBackground(new GradientDrawable() { public GradientDrawable getIns(int a, int b) { this.setCornerRadius(a); this.setColor(b); return this; } }.getIns((int)8, 0xFF0A1324));
			type_linl = textview2.getText().toString();
			if (type_linl.contains("m3u8")) {
				textview5.setText("M3U8");
			}
			else {
				if (type_linl.contains("ts")) {
					textview5.setText("TS");
				}
				else {
					if (type_linl.contains("mp4")) {
						textview5.setText("MP4");
					}
					else {
						if (type_linl.contains("mkv")) {
							textview5.setText("MKV");
						}
						else {
							if (type_linl.contains("m3u")) {
								textview5.setText("M3U");
							}
							else {
								if (type_linl.contains("avi")) {
									textview5.setText("AVI");
								}
								else {
									if (type_linl.equals("php")) {
										textview5.setText("PHP");
									}
									else {
										if (type_linl.contains("youtu")) {
											textview5.setText("YouTube");
										}
										else {
											if (type_linl.contains("iptv")) {
												textview5.setText("IPTV");
											}
											else {
												if (type_linl.contains("mpd")) {
													textview5.setText("DRM");
												}
												else {
													if (type_linl.contains("html")) {
														textview5.setText("HTML");
													}
													else {
														if (type_linl.contains("api")) {
															textview5.setText("API");
														}
														else {
															if (type_linl.equals("1212")) {
																textview5.setText("ToFi VIP");
															}
															else {
																
															}
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
			linear1.setOnLongClickListener(new View.OnLongClickListener() {
				@Override
				public boolean onLongClick(View _view) {
					d = new AlertDialog.Builder(ListviweActivity.this).create();
					LayoutInflater dLI = getLayoutInflater();
					View dCV = (View) dLI.inflate(R.layout.dialog, null);
					d.setView(dCV);
					final LinearLayout back = (LinearLayout)
					dCV.findViewById(R.id.bg);
					final LinearLayout linear1 = (LinearLayout)
					dCV.findViewById(R.id.lin1);
					final LinearLayout linear2 = (LinearLayout)
					dCV.findViewById(R.id.lin2);
					final LinearLayout linear3 = (LinearLayout)
					dCV.findViewById(R.id.lin3);
					final TextView txt1 = (TextView)
					dCV.findViewById(R.id.textview1);
					final TextView txt2 = (TextView)
					dCV.findViewById(R.id.textview2);
					final TextView txt3 = (TextView)
					dCV.findViewById(R.id.textview3);
					d.setCancelable(true);
					d.show();
					txt1.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/droid.ttf"), 1);
					txt2.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/droid.ttf"), 1);
					txt3.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/droid.ttf"), 1);
					linear1.setOnClickListener(new View.OnClickListener() {
						@Override
						public void onClick(View _view) {
							n = _position;
							edittext1.setText(map2.get((int)_position).get("txt").toString());
							edittext2.setText("");
							if (map2.get((int)_position).containsKey("uris")) {
								uris = (ArrayList<String>)map2.get(_position).get("uris");
								rv.setAdapter(new ListAdapter (uris,ListviweActivity.this));
								
							}
							else {
								uris =  new ArrayList<String>();
								rv.setAdapter(new ListAdapter (uris,ListviweActivity.this));
								
								edittext2.setText(map2.get((int)_position).get("Url").toString());
							}
							if (map2.get((int)_position).get("user_agent").toString().equals("YF Player")) {
								edittext3.setText("");
							}
							else {
								edittext3.setText(map2.get((int)_position).get("user_agent").toString());
							}
							if (map2.get((int)_position).get("referer").toString().equals("Referer")) {
								edittext4.setText("");
							}
							else {
								edittext4.setText(map2.get((int)_position).get("referer").toString());
							}
							links.setVisibility(View.GONE);
							textview3.setVisibility(View.GONE);
							_fab.hide();
							imageview2.setVisibility(View.VISIBLE);
							linear.setVisibility(View.VISIBLE);
							if (map2.get((int)_position).containsKey("player")) {
								if (map2.get((int)_position).get("player").toString().equals("web")) {
									checkbox1.setChecked(false);
									checkbox2.setChecked(true);
									checkbox3.setChecked(false);
								}
								else {
									if (map2.get((int)_position).containsKey("isDrm")) {
										checkbox1.setChecked(false);
										checkbox2.setChecked(false);
										checkbox3.setChecked(true);
									}
									else {
										checkbox1.setChecked(true);
										checkbox2.setChecked(false);
										checkbox3.setChecked(false);
									}
								}
							}
							else {
								checkbox1.setChecked(true);
								checkbox2.setChecked(false);
								checkbox3.setChecked(false);
								checkbox4.setChecked(false);
							}
							if (map2.get((int)_position).containsKey("ism3u")) {
								checkbox4.setChecked(true);
							}
							else {
								checkbox4.setChecked(false);
							}
							if (map2.get((int)_position).containsKey("cookies")) {
								cookieseditText.setText(map2.get((int)_position).get("cookies").toString());
							}
							else {
								cookieseditText.setText("");
							}
							if (map2.get((int)_position).containsKey("Origin")) {
								origin.setText(map2.get((int)_position).get("Origin").toString());
							}
							else {
								origin.setText("");
							}
							d.dismiss();
						}
					});
					linear2.setOnClickListener(new View.OnClickListener() {
						@Override
						public void onClick(View _view) {
							map2.remove((int)(_position));
							data.edit().putString("data", new Gson().toJson(map2)).commit();
							notifyDataSetChanged();
							if (map2.size() == 0) {
								links.setVisibility(View.GONE);
								textview3.setVisibility(View.VISIBLE);
								t = new TimerTask() {
									@Override
									public void run() {
										runOnUiThread(new Runnable() {
											@Override
											public void run() {
												textview3.setVisibility(View.GONE);
												
												imageview2.setVisibility(View.VISIBLE);
												linear.setVisibility(View.VISIBLE);
											}
										});
									}
								};
								_timer.schedule(t, (int)(500));
							}
							else {
								links.setVisibility(View.VISIBLE);
							}
							d.dismiss();
						}
					});
					linear3.setOnClickListener(new View.OnClickListener() {
						@Override
						public void onClick(View _view) {
							d.dismiss();
						}
					});
					return true;
				}
			});
			linear1.setOnClickListener(new View.OnClickListener() {
				@Override
				public void onClick(View _view) {
					try{
						if (((ArrayList<String>)map2.get(_position).get("uris")).get(0).equals("1212")) {
							if (_isAppInstalled("com.tofi.player")) {
								i.setClass(getApplicationContext(), TofiAppActivity.class);
								i.putExtra("link", "1212");
								startActivity(i);
								Animatoo.animateSlideLeft(ListviweActivity.this);
							}
							else {
								c.setMessage("تنويه التطبيق ليس مثبت ");
								c.setPositiveButton("تثبيت ", new DialogInterface.OnClickListener() {
									@Override
									public void onClick(DialogInterface _dialog, int _which) {
										tog.setAction(Intent.ACTION_VIEW);
										tog.setData(Uri.parse("https://tofi-xtv.net/"));
										startActivity(tog);
									}
								});
								c.setNeutralButton("ألغاء", new DialogInterface.OnClickListener() {
									@Override
									public void onClick(DialogInterface _dialog, int _which) {
										
									}
								});
								c.create().show();
							}
						}
						else {
							if (map2.get((int)_position).get("player").toString().equals("Web")) {
								i.setClass(getApplicationContext(), WebviewActivity.class);
								i.putExtra("txt", map2.get((int)_position).get("txt").toString());
								if (map2.get((int)_position).containsKey("uris")) {
									i.putExtra("url", ((ArrayList<String>)map2.get(_position).get("uris")).get(0));
								}
								else {
									i.putExtra("url", map2.get((int)_position).get("Url").toString());
								}
								if (map2.get((int)_position).containsKey("cookies")) {
									i.putExtra("cookies", map2.get((int)_position).get("cookies").toString());
								}
								if (map2.get((int)_position).containsKey("Origin")) {
									i.putExtra("Origin", map2.get((int)_position).get("Origin").toString());
								}
								startActivity(i);
								Animatoo.animateSlideLeft(ListviweActivity.this);
							}
							else {
								if (map2.get((int)_position).containsKey("ism3u")) {
									i.setClass(getApplicationContext(), TestActivity.class);
									i.putExtra("m3u", "m3u");
								}
								else {
									i.setClass(getApplicationContext(), PlayActivity.class);
								}
								if (map2.get((int)_position).containsKey("cookies")) {
									i.putExtra("cookies", map2.get((int)_position).get("cookies").toString());
								}
								if (map2.get((int)_position).containsKey("isDrm")) {
									i.putExtra("txt", map2.get((int)_position).get("txt").toString());
									i.putExtra("play", "play");
									i.putExtra("referer", map2.get((int)_position).get("referer").toString());
									i.putExtra("userAgent", map2.get((int)_position).get("user_agent").toString());
									i.putExtra("ClearKey_Key_ID", map2.get((int)_position).get("referer").toString());
									i.putExtra("ClearKey_Key", map2.get((int)_position).get("user_agent").toString());
									i.putExtra("name", "player");
									i.putExtra("isDrm", "true");
									if (map2.get((int)_position).containsKey("uris")) {
										i.putExtra("uris", (ArrayList<String>)map2.get(_position).get("uris"));
									}
									else {
										i.putExtra("url", map2.get((int)_position).get("Url").toString());
									}
									if (map2.get((int)_position).containsKey("Origin")) {
										i.putExtra("Origin", map2.get((int)_position).get("Origin").toString());
									}
									startActivity(i);
								}
								else {
									i.putExtra("txt", map2.get((int)_position).get("txt").toString());
									i.putExtra("play", "play");
									i.putExtra("referer", map2.get((int)_position).get("referer").toString());
									i.putExtra("userAgent", map2.get((int)_position).get("user_agent").toString());
									i.putExtra("name", "player");
									i.putExtra("isDrm", "false");
									if (map2.get((int)_position).containsKey("uris")) {
										i.putExtra("uris", (ArrayList<String>)map2.get(_position).get("uris"));
									}
									else {
										i.putExtra("url", map2.get((int)_position).get("Url").toString());
									}
									if (map2.get((int)_position).containsKey("Origin")) {
										i.putExtra("Origin", map2.get((int)_position).get("Origin").toString());
									}
									startActivity(i);
									Animatoo.animateSlideLeft(ListviweActivity.this);
								}
							}
						}
					}catch(Exception e){
						 
					}
				}
			});
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