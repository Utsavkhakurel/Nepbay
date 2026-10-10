<?php
require __DIR__ . '/includes/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/my-auctions.php');
}
verify_csrf();

$auctionId = (int) ($_POST['auction_id'] ?? 0);

$pdo = db();
$pdo->beginTransaction();

// Lock the auction so a bid can't arrive while we delete it
$stmt = $pdo->prepare("
    SELECT a.status, a.current_bid, p.id AS product_id, p.title
    FROM auctions a
    JOIN products p ON p.id = a.product_id
    WHERE a.id = ? AND p.seller_id = ?
    FOR UPDATE
");
$stmt->execute([$auctionId, current_user()['id']]);
$auction = $stmt->fetch();

if (!$auction || $auction['status'] === 'removed') {
    $pdo->rollBack();
    set_flash('error', 'Auction not found.');
    redirect('/my-auctions.php');
}
if ($auction['current_bid'] !== null) {
    $pdo->rollBack();
    set_flash('error', 'This listing can\'t be deleted because it has bids.');
    redirect('/my-auctions.php');
}

$stmt = $pdo->prepare('SELECT file_name FROM product_images WHERE product_id = ?');
$stmt->execute([$auction['product_id']]);
$files = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Deleting the product also deletes its auction and photo rows (ON DELETE CASCADE)
$pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$auction['product_id']]);
$pdo->commit();

delete_images($files);

set_flash('success', '"' . $auction['title'] . '" has been deleted.');
redirect('/my-auctions.php');
