'use client';
import Script from 'next/script';
import { t } from '@/lib/i18n';

/**
 * Boots the existing vanilla runtime verbatim: the QAMHAD config object the
 * PHP <head> used to print, then api-service.js and app.js (theme, toast,
 * countdowns, tabs, live-score ticking, PWA service-worker registration and
 * the install/notify prompts — all unchanged).
 *
 * Navigation is full-document (plain <a>), exactly like the original PHP
 * multi-page app, so app.js runs once per page — no double-binding, no
 * duplicate timers.
 */
export default function Scripts() {
  return (
    <>
      <Script id="qamhad-config" strategy="beforeInteractive">{`
        window.QAMHAD = {
          lang: 'ar', prefix: '', build: 'next-1',
          t: { am:'${t('misc.am')}', pm:'${t('misc.pm')}', copied:'${t('misc.copy_done')}',
               live:'${t('status.live')}', ft:'${t('status.finished')}', ht:'${t('status.halftime')}',
               d:'${t('misc.days')}', h:'${t('misc.hours')}', m:'${t('misc.minutes')}', s:'${t('misc.seconds')}',
               update_ready:'${t('misc.update_ready')}', update_now:'${t('misc.update_now')}',
               update_later:'${t('misc.update_later')}', updating:'${t('misc.updating')}' },
          fcm: {}
        };
      `}</Script>
      <Script src="/assets/js/api-service.js" strategy="afterInteractive" />
      <Script src="/assets/js/app.js" strategy="afterInteractive" />
      {/* AdSense Auto Ads — loaded on first interaction, never blocking first paint. */}
      <Script id="ads-lazy" strategy="lazyOnload">{`
        (function () {
          var loaded = false, evs = ['scroll','touchstart','pointerdown','keydown','mousemove'];
          function loadAds() {
            if (loaded) return; loaded = true;
            evs.forEach(function (e) { removeEventListener(e, loadAds, { passive: true }); });
            var s = document.createElement('script');
            s.async = true; s.crossOrigin = 'anonymous';
            s.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6543754410644923';
            document.head.appendChild(s);
          }
          evs.forEach(function (e) { addEventListener(e, loadAds, { passive: true, once: true }); });
          setTimeout(loadAds, 6000);
        })();
      `}</Script>
    </>
  );
}
