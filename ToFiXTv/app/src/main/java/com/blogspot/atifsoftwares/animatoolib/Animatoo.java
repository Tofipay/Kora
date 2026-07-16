package com.blogspot.atifsoftwares.animatoolib;

import android.app.Activity;
import android.content.Context;

import com.tofixtv.app.R;

/**
 * Local drop-in implementation of the Animatoo transition helper used by the
 * original app, tuned with 2026 Material-motion easing curves.
 */
public final class Animatoo {

    private Animatoo() {
    }

    public static void animateSlideLeft(Context context) {
        if (context instanceof Activity) {
            ((Activity) context).overridePendingTransition(
                    R.anim.animatoo_slide_in_right, R.anim.animatoo_slide_out_left);
        }
    }

    public static void animateSlideRight(Context context) {
        if (context instanceof Activity) {
            ((Activity) context).overridePendingTransition(
                    R.anim.animatoo_slide_in_left, R.anim.animatoo_slide_out_right);
        }
    }

    public static void animateSlideUp(Context context) {
        if (context instanceof Activity) {
            ((Activity) context).overridePendingTransition(
                    R.anim.animatoo_slide_in_up, R.anim.animatoo_slide_out_up);
        }
    }

    public static void animateSlideDown(Context context) {
        if (context instanceof Activity) {
            ((Activity) context).overridePendingTransition(
                    R.anim.animatoo_slide_in_down, R.anim.animatoo_slide_out_down);
        }
    }

    public static void animateFade(Context context) {
        if (context instanceof Activity) {
            ((Activity) context).overridePendingTransition(
                    R.anim.animatoo_fade_in, R.anim.animatoo_fade_out);
        }
    }

    public static void animateZoom(Context context) {
        if (context instanceof Activity) {
            ((Activity) context).overridePendingTransition(
                    R.anim.animatoo_zoom_in, R.anim.animatoo_fade_out);
        }
    }
}
