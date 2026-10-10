<?php
// Every page starts with:  require __DIR__ . '/includes/init.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

session_set_cookie_params([
    'path'     => BASE_URL . '/',
    'httponly' => true,    // JavaScript can't read the session cookie
    'samesite' => 'Lax',
]);
session_start();

close_ended_auctions();
