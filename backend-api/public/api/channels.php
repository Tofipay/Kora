<?php
/** GET /api/channels.php[?lang=ar|en] — the admin-managed TV channel library
 *  (name + playable stream URLs) for the app's Channels screen. */
require __DIR__ . '/_boot.php';

use Qamhad\Core\ChannelLib;
use Qamhad\Core\Lang;

api_serve(
    fn() => array_values(ChannelLib::all()),
    'channels_' . Lang::current(),
    CACHE_TTL_LEAGUES,
    api_fail_text()
);
