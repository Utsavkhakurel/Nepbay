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

// Auction length for the sell form: 6 -> "6 hours", 72 -> "3 days"
function duration_label(int $hours): string
{
    if ($hours < 24) {
        return $hours . ($hours === 1 ? ' hour' : ' hours');
    }
    $days = intdiv($hours, 24);
    return $days . ($days === 1 ? ' day' : ' days');
}

// ---- Bidding rules ----

// The lowest bid allowed right now: the starting price if nobody has bid yet,
// otherwise the current bid plus the increment the seller chose.
function minimum_bid($startingPrice, $currentBid, $increment): float
{
    if ($currentBid === null) {
        return (float) $startingPrice;
    }
    return (float) $currentBid + (float) $increment;
}

// Closes every auction whose time is up and saves the highest bidder as the winner
// (winner stays NULL if nobody bid). Bids from blocked users don't count.
// PHP can't run tasks in the background, so this is called at the start of
// every page load instead. It is one quick query.
function close_ended_auctions(): void
{
    db()->exec("
        UPDATE auctions
        SET status = 'ended',
            winner_id = (SELECT b.user_id FROM bids b
                         JOIN users u ON u.id = b.user_id
                         WHERE b.auction_id = auctions.id AND u.status = 'active'
                         ORDER BY b.amount DESC LIMIT 1)
        WHERE status = 'active' AND end_time <= NOW()
    ");
}

// Recalculates current_bid on the live auctions a user has bid on, counting only
// bids from active users. Called when that user is blocked or unblocked, so a
// blocked user's bids stop counting (and count again if they are unblocked).
function refresh_current_bids(int $userId): void
{
    db()->prepare("
        UPDATE auctions
        SET current_bid = (SELECT MAX(b.amount) FROM bids b
                           JOIN users u ON u.id = b.user_id
                           WHERE b.auction_id = auctions.id AND u.status = 'active')
        WHERE status = 'active'
          AND id IN (SELECT auction_id FROM bids WHERE user_id = ?)
    ")->execute([$userId]);
}

// One word describing an auction for the admin pages: live, sold, unsold or removed
function auction_status(array $auction): string
{
    if ($auction['status'] === 'removed') {
        return 'removed';
    }
    if ($auction['status'] === 'ended') {
        return $auction['winner_id'] ? 'sold' : 'unsold';
    }
    return 'live';
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

// Checks the listing fields shared by sell.php and edit-auction.php.
// Returns an array of error messages keyed by field name (empty = all fine).
function listing_errors(string $title, int $categoryId, string $description, string $price, string $increment, array $categories): array
{
    $errors = [];
    if (mb_strlen($title) < 5 || mb_strlen($title) > 150) {
        $errors['title'] = 'Title must be between 5 and 150 characters.';
    }
    if (!in_array($categoryId, array_column($categories, 'id'))) {
        $errors['category_id'] = 'Please choose a category.';
    }
    if (mb_strlen($description) < 20 || mb_strlen($description) > 5000) {
        $errors['description'] = 'Description must be between 20 and 5000 characters.';
    }
    if (!ctype_digit($price) || $price < 10 || $price > 100000000) {
        $errors['starting_price'] = 'Starting price must be a whole number between Rs. 10 and Rs. 10,00,00,000.';
    }
    if (!ctype_digit($increment) || $increment < 1 || $increment > 10000000) {
        $errors['bid_increment'] = 'Bid increment must be a whole number between Rs. 1 and Rs. 1,00,00,000.';
    }
    return $errors;
}

// Checks new photos. $keptCount is how many photos the listing already has.
// Returns an error message, or null if the photos are fine.
function image_upload_error(array $images, int $keptCount = 0): ?string
{
    $total = $keptCount + count($images);
    if ($total === 0) {
        return 'Please add at least one photo.';
    }
    if ($total > MAX_IMAGES) {
        return 'A listing can have up to ' . MAX_IMAGES . ' photos.';
    }
    foreach ($images as $image) {
        if ($image['error'] !== UPLOAD_ERR_OK || $image['size'] > MAX_IMAGE_SIZE) {
            return '"' . $image['name'] . '" is too large. Each photo must be under 2 MB.';
        }
        if (image_extension($image['tmp_name']) === null) {
            return '"' . $image['name'] . '" is not a JPG, PNG or WEBP image.';
        }
    }
    return null;
}

// Moves checked photos into /uploads with random names and returns the new file names
function save_images(array $images): array
{
    $saved = [];
    foreach ($images as $image) {
        $fileName = bin2hex(random_bytes(16)) . '.' . image_extension($image['tmp_name']);
        if (!move_uploaded_file($image['tmp_name'], UPLOAD_DIR . $fileName)) {
            delete_images($saved);
            throw new RuntimeException('Could not save photo. Check that the uploads folder is writable.');
        }
        $saved[] = $fileName;
    }
    return $saved;
}

function delete_images(array $fileNames): void
{
    foreach ($fileNames as $fileName) {
        if (is_file(UPLOAD_DIR . $fileName)) {
            unlink(UPLOAD_DIR . $fileName);
        }
    }
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
