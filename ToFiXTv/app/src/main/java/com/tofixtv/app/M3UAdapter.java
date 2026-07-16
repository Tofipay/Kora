package com.tofixtv.app;

import android.content.Context;
import android.content.res.Configuration;
import android.net.Uri;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import androidx.recyclerview.widget.GridLayoutManager;
import android.app.Activity;
import java.util.HashMap;
import java.util.ArrayList;
import android.util.DisplayMetrics;

public class M3UAdapter extends RecyclerView.Adapter<M3UAdapter.MyViewHolder> {

    private Activity context;
    private ArrayList<HashMap<String, Object>> arrayList;

    public M3UAdapter(Activity context, ArrayList<HashMap<String, Object>> temp) {
        this.context = context;
        arrayList = temp;
    }

    @NonNull
    @Override
    public MyViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.m3uadapter, parent, false);
        return new MyViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull MyViewHolder holder, int position) {
        ArrayList<HashMap<String, Object>> subChildData = (ArrayList<HashMap<String, Object>>) arrayList.get(position).get("data");
        
        // إعادة حساب عدد الأعمدة وتحديث التخطيط
        int numberOfColumns = calculateNoOfColumns(context);
        GridLayoutManager gridLayoutManager = new GridLayoutManager(context, numberOfColumns);
        holder.RV.setLayoutManager(gridLayoutManager);
        
        holder.RV.setAdapter(new M3UChildAdapter(context, subChildData));
    }

    @Override
    public int getItemCount() {
        return arrayList.size();
    }

    public class MyViewHolder extends RecyclerView.ViewHolder {
        RecyclerView RV;

        public MyViewHolder(@NonNull View itemView) {
            super(itemView);
            RV = itemView.findViewById(R.id.recyclerview1);
            
            // إنشاء وتحديث التخطيط
            updateLayoutManager();
        }
        
        private void updateLayoutManager() {
            int numberOfColumns = calculateNoOfColumns(context);
            GridLayoutManager gridLayoutManager = new GridLayoutManager(context, numberOfColumns);
            RV.setLayoutManager(gridLayoutManager);
        }
    }

    private int calculateNoOfColumns(Context context) {
        DisplayMetrics displayMetrics = context.getResources().getDisplayMetrics();
        float dpWidth = displayMetrics.widthPixels / displayMetrics.density;
        int columnWidth = 130; // عرض العمود الواحد بالدب (يمكنك تعديله بناءً على حجم العناصر)
        int noOfColumns = (int) (dpWidth / columnWidth);
        return noOfColumns >= 1 ? noOfColumns : 1; // يجب أن يكون على الأقل 1
    }
}