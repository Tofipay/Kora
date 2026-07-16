package com.tofixtv.app;


import android.content.Intent;

import android.widget.LinearLayout;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;
import android.content.ClipData;
import android.content.ClipboardManager;
import androidx.recyclerview.widget.RecyclerView;
import android.view.View;
import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.ProgressBar;
import android.widget.TextView;
import androidx.cardview.widget.CardView;
import android.content.DialogInterface;
import android.app.AlertDialog;
import com.tofixtv.app.R;

import java.util.ArrayList;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import java.util.Arrays;
public class ListAdapter extends RecyclerView.Adapter<ListAdapter.ViewHolder>   {
	ArrayList<String> data;
	AppCompatActivity appCompatActivity;
    AlertDialog.Builder d;
	public ListAdapter(ArrayList<String> temp,AppCompatActivity appCompatActivity){
		data = temp; 
		this.appCompatActivity = appCompatActivity;
     }   
	
	@Override
	public ViewHolder onCreateViewHolder(ViewGroup parent, int viewType) {
		View view = null;
		ListAdapter.ViewHolder viewHolder = null;
		RecyclerView.LayoutParams lp = null;
		
			view = LayoutInflater.from(appCompatActivity).inflate(R.layout.urllist,parent,false);
			viewHolder= new ViewHolder(view);
			lp = new	RecyclerView.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
		
		view.setLayoutParams(lp);
		return viewHolder;
	}
	@Override
	public void onBindViewHolder(ViewHolder holder, final int position) {
		holder.name.setText(data.get(position));
        holder.del.setOnClickListener(new View.OnClickListener() {
	@Override
	public void onClick(View _view) {
        d = new AlertDialog.Builder(appCompatActivity);
		d.setTitle("Do you want to delete url?");
d.setPositiveButton("delete ", new DialogInterface.OnClickListener() {
	@Override
	public void onClick(DialogInterface _dialog, int _which) {
		data.remove(position);
		notifyDataSetChanged();
	}
});
d.setNegativeButton("cancel", new DialogInterface.OnClickListener() {
	@Override
	public void onClick(DialogInterface _dialog, int _which) {
		
	}
});

d.create().show();
	}
});
   holder. name.setOnClickListener(new View.OnClickListener() {
	@Override
	public void onClick(View _view) {
		((ClipboardManager)appCompatActivity. getSystemService(Context.CLIPBOARD_SERVICE)).setPrimaryClip(ClipData.newPlainText("clipboard", data.get(position)));
SketchwareUtil.showMessage(appCompatActivity, "copied to clipboard ");
	}
});
	}
	
	public class ViewHolder extends RecyclerView.ViewHolder {
		TextView name;
        ImageView del;
		LinearLayout parent;
		public ViewHolder(View itemView) {
			super(itemView);
            del = itemView.findViewById(R.id.imageview1);
			name = (TextView)itemView.findViewById(R.id.name);
			parent = itemView.findViewById(R.id.parent);
		}
	}
	@Override
	public int getItemCount(){
		return data.size();
	}
	

	}
	
	