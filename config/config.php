<?php
// Site-wide settings. Local XAMPP values are used on localhost,
// InfinityFree values everywhere else.

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1']);

if ($isLocal) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'nepbay');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', '/Nepbay');   // http://localhost/Nepbay
} else {
    define('DB_HOST', 'sql301.infinityfree.com');
    define('DB_NAME', 'if0_43252939_nepbay');
    define('DB_USER', 'if0_43252939');
    define('DB_PASS', 'PUT_YOUR_VPANEL_PASSWORD_HERE');
    define('BASE_URL', '');          // https://nepbay.ifree.page
}

define('SITE_NAME', 'Nepbay');

date_default_timezone_set('Asia/Kathmandu');

// Product photos
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_IMAGES', 5);
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024);   // 2 MB per photo

// How long an auction can run, in hours (24 = 1 day, 168 = 7 days)
define('AUCTION_DURATIONS', [1, 3, 6, 12, 24, 72, 120, 168, 336]);
