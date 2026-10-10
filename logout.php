<?php
require __DIR__ . '/includes/init.php';

// Logout only through a POST form (with CSRF token), so another site
// can't log users out just by linking to this page.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}
verify_csrf();

logout_user();

set_flash('success', 'You have been logged out.');
redirect('/login.php');
