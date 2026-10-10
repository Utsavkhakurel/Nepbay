<?php
// Site-wide settings. Change these if your XAMPP setup is different.

define('DB_HOST', 'localhost');
define('DB_NAME', 'nepbay');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_NAME', 'Nepbay');
define('BASE_URL', '/Nepbay');   // URL path of the project: http://localhost/Nepbay

date_default_timezone_set('Asia/Kathmandu');

// Product photos
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_IMAGES', 5);
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024);   // 2 MB per photo

// How long an auction can run, in days
define('AUCTION_DURATIONS', [1, 3, 5, 7, 14]);
