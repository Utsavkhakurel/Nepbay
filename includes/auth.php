<?php
// Login / logout and page-protection helpers.

// Returns the logged-in user's row, or null for guests.
// The user is re-read from the database so a blocked user is logged out immediately.
function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        $user = null;
        if (isset($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, name, email, phone, address, role, status FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();

            if ($row && $row['status'] === 'active') {
                $user = $row;
            } else {
                unset($_SESSION['user_id']);   // deleted or blocked
            }
        }
    }

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return is_logged_in() && current_user()['role'] === 'admin';
}

function login_user(int $userId): void
{
    session_regenerate_id(true);   // new session ID on login (prevents session fixation)
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];                // forget everything about the user
    session_regenerate_id(true);   // delete the old session and issue a fresh, empty one
}

// Put at the top of pages that need a logged-in user.
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        // Come back to this page after logging in
        $currentPage = substr($_SERVER['REQUEST_URI'], strlen(BASE_URL));
        redirect('/login.php?redirect=' . urlencode($currentPage));
    }
}

// Put at the top of every admin page.
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Access denied.');
    }
}
