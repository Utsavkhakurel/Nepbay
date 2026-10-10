<?php
require __DIR__ . '/includes/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}
verify_csrf();

$user      = current_user();
$auctionId = (int) ($_POST['auction_id'] ?? 0);
$amount    = trim($_POST['amount'] ?? '');

$pdo = db();
$pdo->beginTransaction();

// FOR UPDATE locks this auction row until we commit. If two people bid at the
// same moment, the second one waits here and then sees the first one's bid.
$stmt = $pdo->prepare("
    SELECT a.id, a.starting_price, a.current_bid, a.end_time, a.status, p.seller_id
    FROM auctions a
    JOIN products p ON p.id = a.product_id
    WHERE a.id = ?
    FOR UPDATE
");
$stmt->execute([$auctionId]);
$auction = $stmt->fetch();

// Who is winning right now?
$stmt = $pdo->prepare('SELECT user_id FROM bids WHERE auction_id = ? ORDER BY amount DESC LIMIT 1');
$stmt->execute([$auctionId]);
$highestBidderId = $stmt->fetchColumn();

$error = null;

if (!$auction || $auction['status'] === 'removed') {
    $error = 'This auction does not exist.';
} elseif ($auction['status'] !== 'active' || strtotime($auction['end_time']) <= time()) {
    $error = 'This auction has already ended.';
} elseif ($user['role'] === 'admin') {
    $error = 'Admins cannot place bids.';
} elseif ((int) $auction['seller_id'] === $user['id']) {
    $error = 'You cannot bid on your own auction.';
} elseif ((int) $highestBidderId === $user['id']) {
    $error = 'You are already the highest bidder.';
} elseif (!ctype_digit($amount) || $amount > 1000000000) {
    $error = 'Please enter your bid as a whole number of rupees.';
} else {
    $minimum = minimum_bid($auction['starting_price'], $auction['current_bid']);
    if ($amount < $minimum) {
        $error = 'Your bid must be at least ' . format_price($minimum) . '.';
    }
}

if ($error) {
    $pdo->rollBack();
    set_flash('error', $error);
    redirect('/auction.php?id=' . $auctionId);
}

$pdo->prepare('INSERT INTO bids (auction_id, user_id, amount) VALUES (?, ?, ?)')
    ->execute([$auctionId, $user['id'], $amount]);
$pdo->prepare('UPDATE auctions SET current_bid = ? WHERE id = ?')
    ->execute([$amount, $auctionId]);
$pdo->commit();

set_flash('success', 'Your bid of ' . format_price($amount) . ' was placed. You are now the highest bidder.');
redirect('/auction.php?id=' . $auctionId);
