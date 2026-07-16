package com.tofixtv.app;

import com.tofixtv.app.EngActivity;
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
import android.widget.EditText;
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
import com.blogspot.atifsoftwares.animatoolib.*;
import com.google.android.gms.ads.*;
import com.google.android.gms.ads.MobileAds;
import com.google.android.gms.ads.impl.*;
import com.google.android.gms.base.*;
import com.google.android.gms.common.*;
import com.google.android.gms.tasks.OnCompleteListener;
import com.google.android.gms.tasks.Task;
import com.google.firebase.FirebaseApp;
import com.google.firebase.iid.FirebaseInstanceId;
import com.google.firebase.iid.InstanceIdResult;
import com.google.firebase.messaging.FirebaseMessaging;
import de.hdodenhof.circleimageview.*;
import java.io.*;
import java.io.InputStream;
import java.text.*;
import java.util.*;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.regex.*;
import meorg.jsoup.*;
import org.json.*;
import java.util.Base64;
import java.io.UnsupportedEncodingException;
import java.net.URLDecoder;
import javax.crypto.Cipher;
import javax.crypto.spec.SecretKeySpec;
import android.content.pm.PackageManager;
import android.Manifest;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import java.security.PrivateKey;
import java.security.KeyFactory;
import javax.crypto.Mac;
import java.security.spec.PKCS8EncodedKeySpec;
import javax.crypto.spec.SecretKeySpec;
import java.security.Signature;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;
import java.nio.charset.StandardCharsets;
import java.io.IOException;
import java.util.Base64;
import java.util.Date;
import android.util.Log; 

public class MainActivity extends AppCompatActivity {
	
	private HashMap<String, Object> body = new HashMap<>();
	private String endPoint = "";
	private String JTW_Generated = "";
	private String FCM_ProjectURL = "";
	private String ACCESS_TOKEN = "";
	private HashMap<String, Object> response_AccessToken = new HashMap<>();
	private HashMap<String, Object> Header = new HashMap<>();
	private String PrivateKey = "";
	private String ClientEmail = "";
	private HashMap<String, Object> message = new HashMap<>();
	private HashMap<String, Object> notification_Body = new HashMap<>();
	
	private ArrayList<String> uris = new ArrayList<>();
	
	private LinearLayout linear_background;
	private CircleImageView circleimageview1;
	private ImageView imageview1;
	private TextView textview_topic;
	private EditText edittext_title;
	private EditText edittext_message;
	private EditText edittext_imageurl;
	private Button button_send;
	private TextView textview_notifRes;
	
	private RequestNetwork accessToken;
	private RequestNetwork.RequestListener _accessToken_request_listener;
	private RequestNetwork sendNotification;
	private RequestNetwork.RequestListener _sendNotification_request_listener;
	
	private OnCompleteListener cloudMessaging_onCompleteListener;
	private Intent i = new Intent();
	
	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		setContentView(R.layout.main);
		initialize(_savedInstanceState);
		FirebaseApp.initializeApp(this);
		MobileAds.initialize(this);
		
		initializeLogic();
	}
	
	private void initialize(Bundle _savedInstanceState) {
		linear_background = findViewById(R.id.linear_background);
		circleimageview1 = findViewById(R.id.circleimageview1);
		imageview1 = findViewById(R.id.imageview1);
		textview_topic = findViewById(R.id.textview_topic);
		edittext_title = findViewById(R.id.edittext_title);
		edittext_message = findViewById(R.id.edittext_message);
		edittext_imageurl = findViewById(R.id.edittext_imageurl);
		button_send = findViewById(R.id.button_send);
		textview_notifRes = findViewById(R.id.textview_notifRes);
		accessToken = new RequestNetwork(this);
		sendNotification = new RequestNetwork(this);
		
		button_send.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				_clickAnimation(button_send);
				if (edittext_title.getText().toString().equals("") || edittext_message.getText().toString().equals("")) {
					SketchwareUtil.showMessage(getApplicationContext(), "Title Or Message Cannot Be Empty..!!");
				}
				else {
					Header = new HashMap<>();
					Header.put("Authorization", "Bearer ".concat(ACCESS_TOKEN));
					Header.put("Content-Type", "application/json; UTF-8");
					FCM_ProjectURL = "https://fcm.googleapis.com/v1/projects/tofi-player-36/messages:send";
					        HashMap<String, Object> notification = new HashMap<>();
					        notification.put("title", edittext_title.getText().toString());
					        notification.put("body", edittext_message.getText().toString());
					        notification.put("image", edittext_imageurl.getText().toString());
					
					        HashMap<String, Object> message = new HashMap<>();
					        message.put("topic", "news");
					        message.put("data", notification);
					
					        HashMap<String, Object> root = new HashMap<>();
					        root.put("message", message);
					
					        Gson gson = new Gson();
					        String jsonString = gson.toJson(root);
					
					        notification_Body = new Gson().fromJson(jsonString, new TypeToken<HashMap<String, Object>>(){}.getType());
					sendNotification.setHeaders(Header);
					sendNotification.setParams(notification_Body, RequestNetworkController.REQUEST_BODY);
					sendNotification.startRequestNetwork(RequestNetworkController.POST, FCM_ProjectURL, "", _sendNotification_request_listener);
				}
			}
		});
		
		_accessToken_request_listener = new RequestNetwork.RequestListener() {
			@Override
			public void onResponse(String _param1, String _param2, HashMap<String, Object> _param3) {
				final String _tag = _param1;
				final String _response = _param2;
				final HashMap<String, Object> _responseHeaders = _param3;
				response_AccessToken = new Gson().fromJson(_response, new TypeToken<HashMap<String, Object>>(){}.getType());
				if (response_AccessToken.containsKey("access_token")) {
						ACCESS_TOKEN = response_AccessToken.get("access_token").toString();
						button_send.setEnabled(true);
				}
				else {
						button_send.setEnabled(false);
					try{
						String privateKeyPem = PrivateKey;
						String 	clientEmail = ClientEmail;
						String header = "{\"alg\":\"RS256\",\"typ\":\"JWT\"}";
							long now = System.currentTimeMillis();
							long exp = now + 3600 * 1000; // 1 hour expiration
							String payload = String.format(
							"{\"iss\":\"%s\",\"aud\":\"https://oauth2.googleapis.com/token\",\"exp\":%d,\"iat\":%d,\"scope\":\"https://www.googleapis.com/auth/cloud-platform\"}",
							clientEmail, exp / 1000, now / 1000
							);
						
						String encodedHeader = Base64.getUrlEncoder().withoutPadding().encodeToString(header.getBytes(StandardCharsets.UTF_8));
						String encodedPayload = Base64.getUrlEncoder().withoutPadding().encodeToString(payload.getBytes(StandardCharsets.UTF_8));
						String unsignedJwt = encodedHeader + "." + encodedPayload;
						String signature = signWithPrivateKey(unsignedJwt, privateKeyPem);
						JTW_Generated = unsignedJwt + "." + signature;
						String endPoint = "https://oauth2.googleapis.com/token";
						body = new HashMap<>();
						body.put("grant_type", "urn:ietf:params:oauth:grant-type:jwt-bearer");
						body.put("assertion", JTW_Generated);
						accessToken.setParams(body, RequestNetworkController.REQUEST_BODY);
						accessToken.startRequestNetwork(RequestNetworkController.POST, endPoint, "", _accessToken_request_listener);
					}catch(Exception e){
						e.printStackTrace();
						Toast.makeText(MainActivity.this, "JTW: " + e.getMessage(), Toast.LENGTH_LONG).show();
						
					}
				}
			}
			
			@Override
			public void onErrorResponse(String _param1, String _param2) {
				final String _tag = _param1;
				final String _message = _param2;
				
			}
		};
		
		_sendNotification_request_listener = new RequestNetwork.RequestListener() {
			@Override
			public void onResponse(String _param1, String _param2, HashMap<String, Object> _param3) {
				final String _tag = _param1;
				final String _response = _param2;
				final HashMap<String, Object> _responseHeaders = _param3;
				textview_notifRes.setText(_response);
			}
			
			@Override
			public void onErrorResponse(String _param1, String _param2) {
				final String _tag = _param1;
				final String _message = _param2;
				textview_notifRes.setText(_message);
			}
		};
		
		cloudMessaging_onCompleteListener = new OnCompleteListener<InstanceIdResult>() {
			@Override
			public void onComplete(Task<InstanceIdResult> task) {
				final boolean _success = task.isSuccessful();
				final String _token = task.getResult().getToken();
				final String _errorMessage = task.getException() != null ? task.getException().getMessage() : "";
				
			}
		};
	}
	
	private void initializeLogic() {
Intent intent = getIntent();
    String action = intent.getAction();
    Uri data = intent.getData();

   
if (Intent.ACTION_VIEW.equals(action) && data != null) {
String url = data.toString();
if (    url.equals("ToFi.App")) {
i.setClass(getApplicationContext(), AppActivity.class);
startActivity(i);
}
else {
if (url.startsWith("intent")) {
 url = data.getSchemeSpecificPart().split("#")[0];
try {
    // استخدم Base64.getDecoder().decode بدلاً من Base64.decode مع DEFAULT
    byte[] decodedBytes = Base64.getDecoder().decode(url);

    String secretKey = "6.o4XM7s~I|qZF+p9yZ0eOYi";
    SecretKeySpec keySpec = new SecretKeySpec(secretKey.getBytes("UTF-8"), "AES");

    Cipher cipher = Cipher.getInstance("AES/ECB/PKCS5Padding");
    cipher.init(Cipher.DECRYPT_MODE, keySpec);

    try {
        byte[] decryptedBytes = cipher.doFinal(decodedBytes);
        url = new String(decryptedBytes, "UTF-8");
    } catch (Exception aesException) {
        url = new String(decodedBytes, "UTF-8");
    }
} catch (IllegalArgumentException e) {
    e.printStackTrace();
} catch (UnsupportedEncodingException e) {
    e.printStackTrace();
} catch (Exception e) {
    e.printStackTrace();
}
}
if (url.startsWith("//http")) {
		url =     url.replaceFirst("//http", "http");
	}
	if (url.startsWith("xmtv://")) {
		url =     url.replaceFirst("xmtv://", "");
		try {
			    // في حال كان النص يحتوي على فراغات مدمجة
			    String encodedUrl = url.replace(" ", "+"); // استبدال الفراغات بـ "+" لأن بعض النصوص المشفرة تستخدم "+" للفراغات
			
			    // فك التشفير باستخدام Base64
			    byte[] decodedBytes = Base64.getDecoder().decode(encodedUrl);
			
			    String secretKey = "6.o4XM7s~I|qZF+p9yZ0eOYi";
			    SecretKeySpec keySpec = new SecretKeySpec(secretKey.getBytes("UTF-8"), "AES");
			
			    Cipher cipher = Cipher.getInstance("AES/ECB/PKCS5Padding");
			    cipher.init(Cipher.DECRYPT_MODE, keySpec);
			
			    try {
				        byte[] decryptedBytes = cipher.doFinal(decodedBytes);
				        url = new String(decryptedBytes, "UTF-8");
				    } catch (Exception aesException) {
				        // إذا حدث خطأ في فك تشفير AES، نعيد النص بعد فك ترميز Base64
				        url = new String(decodedBytes, "UTF-8");
				    }
		} catch (IllegalArgumentException e) {
			    e.printStackTrace();
		} catch (UnsupportedEncodingException e) {
			    e.printStackTrace();
		} catch (Exception e) {
			    e.printStackTrace();
		}
	}
	if (    url.contains(",")) {
		uris = new ArrayList<String>(Arrays.asList(    url.split(",")));
	}
	else {
		uris.add(    url);
	}
	if (    url.contains("?m3u=m3u")) {
		uris.add(    url);
		i.setClass(getApplicationContext(), TestActivity.class);
		i.putExtra("txt", url.substring(url.indexOf("&txt=") + 5));
		i.putExtra("uris", uris);
		startActivity(i);
	}
	else {
		i.setClass(getApplicationContext(), PlayActivity.class);
		i.putExtra("txt", "   ");
		i.putExtra("referer", "");
		i.putExtra("userAgent", "MTX Player");
		i.putExtra("name", "player");
		i.putExtra("isDrm", "false");
		i.putExtra("uris", uris);
		startActivity(i);
	}
}
}
if (Intent.ACTION_SEND.equals(action)) {
		String type = intent.getType();
	         if ("text/plain".equals(type)) {
		            String sharedText = intent.getStringExtra(Intent.EXTRA_TEXT);
		          if (sharedText != null) {
			if (    sharedText.contains(",")) {
				uris = new ArrayList<String>(Arrays.asList(    sharedText.split(",")));
			}
			else {
				uris.add(    sharedText);
			}
			i.setClass(getApplicationContext(), PlayActivity.class);
			i.putExtra("txt", "   ");
			i.putExtra("referer", "");
			i.putExtra("userAgent", "MTX Player");
			i.putExtra("name", "player");
			i.putExtra("isDrm", "false");
			i.putExtra("uris", uris);
			startActivity(i);
		}}
}
if (intent.hasExtra("link")) {
	if (getIntent().getStringExtra("link").contains("?web=web")) {
		i.setClass(getApplicationContext(), WebviewActivity.class);
		i.putExtra("url", getIntent().getStringExtra("link"));
		startActivity(i);
	}
	else {
		if (getIntent().getStringExtra("link").contains("?m3u=m3u")) {
			uris.add(getIntent().getStringExtra("link"));
			i.setClass(getApplicationContext(), TestActivity.class);
			i.putExtra("txt", getIntent().getStringExtra("txt"));
			i.putExtra("home", "home");
			i.putExtra("uris", uris);
			startActivity(i);
		}
		else {
			if (getIntent().getStringExtra("link").contains(",")) {
				uris = new ArrayList<String>(Arrays.asList(getIntent().getStringExtra("link").split(",")));
			}
			else {
				uris.add(getIntent().getStringExtra("link"));
			}
			i.setClass(getApplicationContext(), PlayActivity.class);
			i.putExtra("txt", getIntent().getStringExtra("txt"));
			i.putExtra("referer", getIntent().getStringExtra("refere"));
			i.putExtra("userAgent", getIntent().getStringExtra("user_agent"));
			i.putExtra("Origin", getIntent().getStringExtra("Origin"));
			i.putExtra("name", "player");
			if (getIntent().getStringExtra("user_agent").equals("")) {
				i.putExtra("isDrm", "false");
			}
			else {
				i.putExtra("isDrm", "true");
				i.putExtra("ClearKey_Key_ID", getIntent().getStringExtra("user_agent"));
				i.putExtra("ClearKey_Key", getIntent().getStringExtra("refere"));
			}
			if (getIntent().hasExtra("cookies")) {
				i.putExtra("cookies", getIntent().getStringExtra("cookies"));
			}
			if (getIntent().hasExtra("logotofi")) {
				i.putExtra("logotofi", getIntent().getStringExtra("logotofi"));
			}
			if (getIntent().hasExtra("cast")) {
				i.putExtra("cast", getIntent().getStringExtra("cast"));
			}
			if (getIntent().hasExtra("title") || getIntent().hasExtra("key_1")) {
				i.putExtra("title", getIntent().getStringExtra("title"));
				i.putExtra("key_1", getIntent().getStringExtra("key_1"));
			}
			i.putExtra("uris", uris);
			startActivity(i);
		}
	}
}
finish();
}


@Override
protected void onPostCreate(Bundle _savedInstanceState) {
	super.onPostCreate(_savedInstanceState);
}
public void _signWithPrivateKey() {
}
private String signWithPrivateKey(String data, String privateKeyPem) throws Exception {
		// Clean up the private key string
		privateKeyPem = privateKeyPem.replace("-----BEGIN PRIVATE KEY-----", "")
		.replace("-----END PRIVATE KEY-----", "")
		.replaceAll("\\s", "");
		
		// Decode the private key
		byte[] keyBytes = Base64.getDecoder().decode(privateKeyPem);
		PKCS8EncodedKeySpec spec = new PKCS8EncodedKeySpec(keyBytes);
		KeyFactory keyFactory = KeyFactory.getInstance("RSA");
		PrivateKey privateKey = keyFactory.generatePrivate(spec);
		
		// Create the signature
		Signature signature = Signature.getInstance("SHA256withRSA");
		signature.initSign(privateKey);
		signature.update(data.getBytes(StandardCharsets.UTF_8));
		byte[] signatureBytes = signature.sign();
		
		// Encode the signature in Base64 URL format
		return Base64.getUrlEncoder().withoutPadding().encodeToString(signatureBytes);
}

{
}


public void _clickAnimation(final View _view) {
	ScaleAnimation fade_in = new ScaleAnimation(0.9f, 1f, 0.9f, 1f, Animation.RELATIVE_TO_SELF, 0.5f, Animation.RELATIVE_TO_SELF, 0.7f);
	fade_in.setDuration(300);
	fade_in.setFillAfter(true);
	_view.startAnimation(fade_in);
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