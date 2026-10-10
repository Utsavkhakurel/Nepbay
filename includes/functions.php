<?php
// Small helper functions used on many pages.

// Escape text before printing it into HTML (prevents XSS).
function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// "2026-10-10 14:30:00" -> "10 Oct 2026"
function format_date(string $datetime): string
{
    return date('j M Y', strtotime($datetime));
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

// ---- Flash messages: shown once on the next page load ----

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ---- CSRF protection: every POST form must include csrf_field() ----

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Invalid form submission. Please go back, refresh the page and try again.');
    }
}

// Remember what the user typed so a form can be refilled after an error.
function old(string $field): string
{
    return e($_POST[$field] ?? '');
}
