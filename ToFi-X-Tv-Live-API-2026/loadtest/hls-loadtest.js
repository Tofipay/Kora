/*
 * ═══════════════════════════════════════════════════════════════════════════
 *  hls-loadtest.js — اختبار حمل يحاكي مشغّل HLS حقيقيًا (k6)
 * ───────────────────────────────────────────────────────────────────────────
 *  كل مستخدم افتراضي (VU) يفعل ما يفعله المشغّل تمامًا:
 *      1) يطلب /api/token/{channels} بترويسة User-Agent: MTX Player
 *      2) يتبع الرابط الموقّع
 *      3) يقرأ Master Playlist إن وُجدت ويختار جودة
 *      4) يحدّث Media Playlist باستمرار حسب TARGETDURATION
 *      5) ينزّل المقاطع الجديدة فقط (بلا إعادة تحميل)
 *      6) يستدعي leave_url عند الخروج
 *
 *  التشغيل:
 *      k6 run -e BASE=https://live-api-tofixtv.tofi-xtv.com \
 *             -e CHANNELS=10 -e PROFILE=50 loadtest/hls-loadtest.js
 *
 *      PROFILE = 50 | 200 | 1000 | smoke
 *
 *  لعرض عدد اتصالات المصدر الفعلية أثناء الاختبار فعّل القياسات في
 *  config.php ومرّر:  -e METRICS_KEY=<your-token>
 *
 *  تنبيه: النتيجة تعتمد كليًا على مواصفات السيرفر والشبكة وCDN.
 *  هذا السكربت يقيس، ولا يَعِد بأي رقم.
 * ═══════════════════════════════════════════════════════════════════════════
 */

import http from 'k6/http';
import { check, sleep, fail } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const BASE = __ENV.BASE || 'http://127.0.0.1:8802';
const CHANNELS = __ENV.CHANNELS || '10';
const PROFILE = __ENV.PROFILE || 'smoke';
const METRICS_KEY = __ENV.METRICS_KEY || '';
const WATCH_SECONDS = parseInt(__ENV.WATCH_SECONDS || '60', 10);

const tokenLatency = new Trend('tofi_token_ms', true);
const playlistLatency = new Trend('tofi_playlist_ms', true);
const segmentLatency = new Trend('tofi_segment_ms', true);
const segmentBytes = new Counter('tofi_segment_bytes');
const segmentsDownloaded = new Counter('tofi_segments_downloaded');
const playlistPolls = new Counter('tofi_playlist_polls');
const staleWindows = new Counter('tofi_playlist_unchanged');
const errors = new Counter('tofi_errors');
const playbackHealthy = new Rate('tofi_playback_healthy');

const PROFILES = {
  smoke: [{ duration: '20s', target: 5 }],
  50: [
    { duration: '10s', target: 50 },
    { duration: '60s', target: 50 },
    { duration: '10s', target: 0 },
  ],
  200: [
    { duration: '20s', target: 200 },
    { duration: '90s', target: 200 },
    { duration: '15s', target: 0 },
  ],
  1000: [
    { duration: '60s', target: 1000 },
    { duration: '180s', target: 1000 },
    { duration: '30s', target: 0 },
  ],
};

export const options = {
  scenarios: {
    viewers: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: PROFILES[PROFILE] || PROFILES.smoke,
      gracefulRampDown: '20s',
    },
  },
  thresholds: {
    tofi_playlist_ms: ['p(95)<1500', 'p(99)<3000'],
    tofi_segment_ms: ['p(95)<5000'],
    tofi_playback_healthy: ['rate>0.98'],
    http_req_failed: ['rate<0.02'],
  },
  discardResponseBodies: false,
  noConnectionReuse: false,
};

export function setup() {
  const before = readMetrics();
  return { startedAt: Date.now(), metrics: before };
}

export default function () {
  const session = getToken();

  if (!session) {
    errors.add(1);
    playbackHealthy.add(false);
    sleep(2);
    return;
  }

  let mediaUrl = session.url;
  const first = fetchPlaylist(mediaUrl);

  if (!first) {
    errors.add(1);
    playbackHealthy.add(false);
    return;
  }

  /* Master Playlist → اختيار أول جودة تمامًا كما يفعل المشغّل. */
  if (first.body.indexOf('#EXT-X-STREAM-INF') !== -1) {
    const variant = firstUri(first.body);

    if (!variant) {
      errors.add(1);
      playbackHealthy.add(false);
      return;
    }

    mediaUrl = absolute(variant, mediaUrl);
  }

  const seen = {};
  let targetDuration = 6;
  const deadline = Date.now() + WATCH_SECONDS * 1000;
  let lastSequence = -1;

  while (Date.now() < deadline) {
    const playlist = fetchPlaylist(mediaUrl);

    if (!playlist) {
      errors.add(1);
      playbackHealthy.add(false);
      sleep(1);
      continue;
    }

    playlistPolls.add(1);

    const parsed = parsePlaylist(playlist.body, mediaUrl);
    targetDuration = parsed.target || targetDuration;

    if (parsed.sequence === lastSequence && parsed.segments.length > 0) {
      staleWindows.add(1);
    }

    /* لا يجوز أن يعود التسلسل للخلف أبدًا. */
    const healthy = parsed.segments.length > 0 && parsed.sequence >= lastSequence;
    playbackHealthy.add(healthy);
    lastSequence = Math.max(lastSequence, parsed.sequence);

    /* تنزيل المقاطع الجديدة فقط — بلا إعادة تحميل لما سبق. */
    let downloaded = 0;

    for (const uri of parsed.segments) {
      if (seen[uri]) {
        continue;
      }

      seen[uri] = true;

      if (downloaded >= 3) {
        continue;
      }

      const response = http.get(uri, {
        tags: { kind: 'segment' },
        redirects: 3,
      });

      segmentLatency.add(response.timings.duration);
      segmentBytes.add(response.body ? response.body.length : 0);
      segmentsDownloaded.add(1);
      downloaded++;

      check(response, { 'segment ok': (r) => r.status === 200 }) ||
        errors.add(1);
    }

    /* نفس إيقاع المشغّل الحقيقي: نصف مدة المقطع. */
    sleep(Math.max(0.5, targetDuration / 2));
  }

  if (session.leave_url) {
    http.get(session.leave_url, { tags: { kind: 'leave' } });
  }
}

export function teardown(data) {
  const after = readMetrics();

  if (!after || !data.metrics) {
    return;
  }

  const delta = {};

  for (const key of Object.keys(after)) {
    delta[key] = after[key] - (data.metrics[key] || 0);
  }

  console.log('── اتصالات المصدر الفعلية خلال الاختبار ──');
  console.log(JSON.stringify(delta, null, 2));
}

function readMetrics() {
  if (!METRICS_KEY) {
    return null;
  }

  const response = http.get(
    `${BASE}/api/metrics?key=${encodeURIComponent(METRICS_KEY)}`,
    { tags: { kind: 'metrics' } }
  );

  if (response.status !== 200) {
    return null;
  }

  try {
    return JSON.parse(response.body).metrics;
  } catch (error) {
    return null;
  }
}

function getToken() {
  const response = http.get(`${BASE}/api/token/${CHANNELS}`, {
    headers: { 'User-Agent': 'MTX Player' },
    tags: { kind: 'token' },
  });

  tokenLatency.add(response.timings.duration);

  if (
    !check(response, {
      'token 200': (r) => r.status === 200,
      'token json': (r) => r.body && r.body.indexOf('"success":true') !== -1,
    })
  ) {
    return null;
  }

  try {
    return JSON.parse(response.body);
  } catch (error) {
    return null;
  }
}

function fetchPlaylist(url) {
  const response = http.get(url, { tags: { kind: 'playlist' }, redirects: 3 });

  playlistLatency.add(response.timings.duration);

  if (response.status !== 200 || !response.body) {
    return null;
  }

  if (response.body.indexOf('#EXTM3U') !== 0) {
    return null;
  }

  return { body: response.body };
}

function parsePlaylist(body, baseUrl) {
  const lines = body.split(/\r?\n/);
  const segments = [];
  let sequence = 0;
  let target = 0;

  for (const raw of lines) {
    const line = raw.trim();

    if (line === '') {
      continue;
    }

    if (line.indexOf('#EXT-X-MEDIA-SEQUENCE:') === 0) {
      sequence = parseInt(line.substring(22), 10) || 0;
      continue;
    }

    if (line.indexOf('#EXT-X-TARGETDURATION:') === 0) {
      target = parseFloat(line.substring(22)) || 0;
      continue;
    }

    if (line[0] === '#') {
      continue;
    }

    segments.push(absolute(line, baseUrl));
  }

  return { segments, sequence, target };
}

function firstUri(body) {
  for (const raw of body.split(/\r?\n/)) {
    const line = raw.trim();

    if (line !== '' && line[0] !== '#') {
      return line;
    }
  }

  return null;
}

function absolute(uri, baseUrl) {
  if (/^https?:\/\//i.test(uri)) {
    return uri;
  }

  const base = baseUrl.split('?')[0];

  if (uri[0] === '/') {
    const match = base.match(/^(https?:\/\/[^/]+)/i);
    return (match ? match[1] : '') + uri;
  }

  return base.substring(0, base.lastIndexOf('/') + 1) + uri;
}
