package okhttp3;
import android.app.Activity;
import org.json.JSONObject;
import org.json.JSONException;
import java.util.HashMap;
import java.util.Iterator;
import okhttp3.Cache;
import okhttp3.CacheControl;

import java.io.File;
import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.FormBody;
import okhttp3.Headers;
import okhttp3.HttpUrl;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;
import java.io.IOException;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.os.Build;
import java.nio.charset.StandardCharsets;
import javax.net.ssl.HostnameVerifier;
import javax.net.ssl.SSLContext;
import javax.net.ssl.SSLSession;
import javax.net.ssl.SSLSocketFactory;
import javax.net.ssl.TrustManager;
import javax.net.ssl.X509TrustManager;
import java.util.concurrent.TimeUnit;
import java.security.SecureRandom;
import java.security.cert.CertificateException;
import java.security.cert.X509Certificate;

public class TofiData {
	String url = "https://api.tofi-xtv.net/api/app/TofiData.php";
	String ref = "";
	String PW =  "([a-zA-Z0-9-!#%&\'*+.^_`{|}~]+)";
	String dummySha1 = "61:ed:37:7e:85:d3:86:a8:df:ee:6b:86:4b:d8:5b:0b:fa:a5:af:81";
	ValueEventListener valueListener;
	Activity activity;
	private final OkHttpClient client;
	
	public TofiData(Activity activity,String REF){
		this.activity = activity;
		client = getClient();
		ref = REF;
        
	}
	
	public void addSingleEventValueListener( ValueEventListener vel){
		valueListener = vel;
		if(vel == null)return;
        
		try{
            
			Request request = new Request.Builder()
			.url(url)
			   .get()
			.header("User-Agent", "tofi/"+getVersionCode())
			.addHeader("metadata", encrypt(dummySha1,PW))
			.addHeader("ref",ref)
			.addHeader("Icy-MetaData","1")
            
			.build();
			client.newCall(request).enqueue(new Callback() {
				@Override public void onFailure(Call call,final IOException e) {
					activity.runOnUiThread(new Runnable() {
						@Override
						public void run() {
							valueListener.onError( e.getMessage());
						}
					});
				}
				
				@Override
				public void onResponse(Call call, final Response response) throws IOException {
					final String responseBody = response.body().string().trim();
					activity.runOnUiThread(new Runnable() {
						@Override
						public void run() {
							Headers b = response.headers();
							HashMap<String, Object> map = new HashMap<>();
							for (String s : b.names()) {
								map.put(s, b.get(s) != null ? b.get(s) : "null");
							}
							
							try {
                                 if(map.containsKey("thcdn2")){
							final String time =map.get("thcdn2").toString();
							final String newBody = decrypt(responseBody,time);
                            valueListener.onPreSuccess(newBody,map);	
								// Parse the root JSON object
								JSONObject jsonObject = new JSONObject(newBody);
								
								// Iterator to loop through all child keys
								Iterator<String> keys = jsonObject.keys();
								
								// Loop through each child key
								while (keys.hasNext()) {
									String key = keys.next(); // This is the child key (e.g., "-O8h9EPVDovCMdY7tdwV")
									// Get the inner JSON object for this key
									JSONObject childObject = jsonObject.getJSONObject(key);
									// Convert the child object into a HashMap
									HashMap<String, Object> childMap = new HashMap<>();
									
									// Iterate over the inner object's keys and populate the HashMap
									Iterator<String> innerKeys = childObject.keys();
									while (innerKeys.hasNext()) {
										String innerKey = innerKeys.next();
										String value = childObject.getString(innerKey);
										childMap.put(innerKey, value);
									}
									valueListener.onSuccess(key, childMap) ; 
                                  }
                                    }else{
                                        valueListener.onError(responseBody);
                                    }
								
							} catch (final Exception ex) {
								ex.printStackTrace();
								valueListener.onError( ex.toString());
							}
						}
					});
				}
			});
		}catch(Exception e){
			valueListener.onError( e.toString());
		}  
        
	}
    
	int getVersionCode() {
		try {
			PackageInfo packageInfo = activity.getPackageManager().getPackageInfo(activity.getPackageName(), 0);
			if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
				return (int) packageInfo.getLongVersionCode(); // For API 28 and above
			} else {
				return packageInfo.versionCode; // For older APIs
			}
		} catch (PackageManager.NameNotFoundException e) {
			e.printStackTrace();
			return -1; // Return a default value or handle the exception
		}
	}    
	String encrypt(String input, String key) {
		StringBuilder result = new StringBuilder();
		// Apply XOR operation with the key
		for (int i = 0; i < input.length(); i++) {
			result.append((char) (input.charAt(i) ^ key.charAt(i % key.length())));
		}
		// Convert the result to a Base64 encoded string
		byte[] encodedBytes = result.toString().getBytes(StandardCharsets.UTF_8);
		return java.util.Base64.getEncoder().encodeToString(encodedBytes);
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
	public interface ValueEventListener{
		public  void onSuccess(String childKey,HashMap<String, Object> childValue);
        public  void onPreSuccess(String response,HashMap<String, Object> rawValue);
		public  void onError(String error);
	}
    OkHttpClient getClient() {
		File cacheDirectory = new File(activity.getCacheDir(), "http-cache");
int cacheSize = 10 * 1024 * 1024; // 10 MB cache
Cache cache = new Cache(cacheDirectory, cacheSize);
			OkHttpClient.Builder builder = new OkHttpClient.Builder();
			builder .cache(cache);
			try {
				final TrustManager[] trustAllCerts = new TrustManager[]{
					new X509TrustManager() {
						@Override
						public void checkClientTrusted(X509Certificate[] chain, String authType)
						throws CertificateException {
						}
						
						@Override
						public void checkServerTrusted(X509Certificate[] chain, String authType)
						throws CertificateException {
						}
						
						@Override
						public X509Certificate[] getAcceptedIssuers() {
							return new X509Certificate[]{};
						}
					}
				};
				
				final SSLContext sslContext = SSLContext.getInstance("TLS");
				sslContext.init(null, trustAllCerts, new SecureRandom());
				final SSLSocketFactory sslSocketFactory = sslContext.getSocketFactory();
				builder.sslSocketFactory(sslSocketFactory, (X509TrustManager) trustAllCerts[0]);
				builder.connectTimeout(15000, TimeUnit.MILLISECONDS);
				builder.readTimeout(25000, TimeUnit.MILLISECONDS);
				builder.writeTimeout(25000, TimeUnit.MILLISECONDS);
				builder.hostnameVerifier(new HostnameVerifier() {
					@Override
					public boolean verify(String hostname, SSLSession session) {
						return true;
					}
				});
			} catch (Exception e) {
			}
		
		return builder.build();
	}
}

