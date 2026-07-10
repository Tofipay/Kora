<?php
/**
 * HLS/segment stream proxy (HMAC-signed URLs only).
 *   /stream.php?url=…&h=…&sig=…
 * The browser talks only to this endpoint; the (often geo-blocked) origin host
 * is fetched server-side with the per-channel headers it requires. Signed URLs
 * are produced by the channel resolver, so this can't be used as an open proxy.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/engine/Core/StreamProxy.php';

\Qamhad\Core\StreamProxy::serve();
