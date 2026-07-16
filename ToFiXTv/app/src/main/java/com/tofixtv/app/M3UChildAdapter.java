package com.tofixtv.app;

import android.app.Activity;
import android.content.Context;
import android.net.Uri;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import androidx.recyclerview.widget.LinearLayoutManager;
import java.util.ArrayList;
import java.util.HashMap;
import android.widget.LinearLayout;
import com.bumptech.glide.Glide;
import android.content.Intent;

public class M3UChildAdapter extends RecyclerView.Adapter<M3UChildAdapter.ViewHolder> {
    ArrayList<HashMap<String, Object>> data;
    Activity context;
    
    public M3UChildAdapter(Activity context, ArrayList<HashMap<String, Object>> temp) {
        data = temp;
        this.context = context;
    }
    
    @Override
    public ViewHolder onCreateViewHolder(ViewGroup parent, int viewType) {
        View view = null;
        M3UChildAdapter.ViewHolder viewHolder = null;
        RecyclerView.LayoutParams lp = null;
        
        view = LayoutInflater.from(context).inflate(R.layout.customview, parent, false);
        viewHolder = new ViewHolder(view);
        lp = new RecyclerView.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        
        view.setLayoutParams(lp);
        return viewHolder;
    }

    @Override
    public void onBindViewHolder(ViewHolder holder, final int position) {
        holder.name.setText(data.get(position).get("title").toString());
        
        if (data.get(position).containsKey("logo")) {
            Glide.with(context)
                .load(data.get(position).get("logo").toString())
                .placeholder(R.drawable.play) // تعيين الصورة الافتراضية 'play'
                .into(holder.icon);
        } else {
            holder.icon.setImageResource(R.drawable.tofilogo);
        }
        
        // إزالة تغيير الخلفية عند الضغط
        holder.parent.setOnTouchListener(null); // إلغاء أي Listener على اللمس

        holder.parent.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View _view) {
                ArrayList<String> uris = new ArrayList<>();
                uris.add(data.get(position).get("url").toString());
                Intent i = new Intent();
                i.setClass(context, PlayActivity.class);
                i.putExtra("txt", data.get(position).get("title").toString());
                i.putExtra("name", "player");

                if (!data.get(position).containsKey("useragent")) {
                    i.putExtra("isDrm", "false");
                    i.putExtra("referer", "");
                    i.putExtra("userAgent", "");
                } else {
                    i.putExtra("referer", data.get(position).get("referer").toString());
                    i.putExtra("userAgent", data.get(position).get("useragent").toString());
                    i.putExtra("isDrm", "true");
                    i.putExtra("ClearKey_Key_ID", data.get(position).get("useragent").toString());
                    i.putExtra("ClearKey_Key", data.get(position).get("referer").toString());
                }
                i.putExtra("uris", uris);
                context.startActivity(i);
            }
        });
    }

    public class ViewHolder extends RecyclerView.ViewHolder {
        TextView name;
        ImageView icon;
        LinearLayout parent;

        public ViewHolder(View itemView) {
            super(itemView);
            icon = itemView.findViewById(R.id.imageview2);
            name = itemView.findViewById(R.id.textview2);
            parent = itemView.findViewById(R.id.linear8);
        }
    }

    @Override
    public int getItemCount() {
        return data.size();
    }

    void hideItem(View v) {
        v.setVisibility(View.GONE);  // إخفاء العنصر
        v.setLayoutParams(new RecyclerView.LayoutParams(0, 0));
    }

    void showItem(View v) {
        v.setVisibility(View.VISIBLE);  // ضمان ظهور العناصر الأخرى
        v.setLayoutParams(new RecyclerView.LayoutParams(
                RecyclerView.LayoutParams.WRAP_CONTENT,
                RecyclerView.LayoutParams.WRAP_CONTENT));  // إعادة تعيين معلمات التخطيط
    }
}