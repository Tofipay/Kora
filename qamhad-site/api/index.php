<?php
// Reclaim shim — on some servers an old api/index.php answered
// {"error":"unknown_endpoint"} for every /api/* URL. The real API lives in
// the application router; hand the request over to the front controller
// (which reads the ORIGINAL REQUEST_URI, so /api/push-subscribe etc. route
// normally even when Apache serves this file via DirectoryIndex).
require dirname(__DIR__) . '/public/index.php';
