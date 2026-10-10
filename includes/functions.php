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

// "2026-10-10 14:30:00" -> "10 Oct 2026, 2:30 PM"
function format_datetime(string $datetime): string
{
    return date('j M Y, g:i A', strtotime($datetime));
}

// Nepali number style: 152000 -> "Rs. 1,52,000"
// (the last 3 digits are grouped together, then groups of 2)
function format_price($amount): string
{
    $number = (string) round((float) $amount);
    $last3  = substr($number, -3);
    $rest   = substr($number, 0, -3);

    if ($rest === '') {
        return 'Rs. ' . $last3;
    }
    $rest = preg_replace('/\B(?=(\d{2})+$)/', ',', $rest);
    return 'Rs. ' . $rest . ',' . $last3;
}

// 190000 -> "2d 4h left", 4500 -> "1h 15m left", 300 -> "5m 0s left"
function time_left(int $seconds): string
{
    if ($seconds <= 0) {
        return 'Ended';
    }
    $days    = intdiv($seconds, 86400);
    $hours   = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    if ($days > 0) {
        return "{$days}d {$hours}h left";
    }
    if ($hours > 0) {
        return "{$hours}h {$minutes}m left";
    }
    return "{$minutes}m " . ($seconds % 60) . 's left';
}

// ---- Bidding rules ----

// How much a new bid must be above the current bid (bigger steps for expensive items)
function bid_increment(float $currentBid): int
{
    if ($currentBid < 1000) {
        return 10;
    }
    if ($currentBid < 10000) {
        return 100;
    }
    if ($currentBid < 100000) {
        return 500;
    }
    return 1000;
}

// The lowest bid allowed right now: the starting price if nobody has bid yet,
// otherwise the current bid plus the increment.
function minimum_bid($startingPrice, $currentBid): float
{
    if ($currentBid === null) {
        return (float) $startingPrice;
    }
    return (float) $currentBid + bid_increment((float) $currentBid);
}

// Hide bidder names in public bid history: "Sita Sharma" -> "S***a"
function mask_name(string $name): string
{
    return mb_substr($name, 0, 1) . '***' . mb_substr($name, -1);
}

// Link to another page of a list while keeping the current search and filters
function page_link(int $pageNumber): string
{
    return '?' . http_build_query(array_merge($_GET, ['page' => $pageNumber]));
}

function image_url(?string $fileName): string
{
    return BASE_URL . '/uploads/' . rawurlencode($fileName ?? '');
}

// ---- Photo uploads ----

// $_FILES groups multiple uploads by field (all names, then all sizes...).
// This turns it into a simple list with one array per file.
function uploaded_files(string $field): array
{
    $files = [];
    if (empty($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) {
        return $files;
    }
    foreach ($_FILES[$field]['name'] as $i => $name) {
        if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = [
            'name'     => $name,
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error'    => $_FILES[$field]['error'][$i],
            'size'     => $_FILES[$field]['size'][$i],
        ];
    }
    return $files;
}

// Looks at the file's real content (not its name) and returns
// "jpg", "png" or "webp", or null if it isn't one of those images.
function image_extension(string $path): ?string
{
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    return $allowed[$mime] ?? null;
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
