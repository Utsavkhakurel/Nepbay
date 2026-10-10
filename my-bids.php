<?php
require __DIR__ . '/includes/init.php';
require_login();

$userId = current_user()['id'];

// Every auction this user has bid on, with their own highest bid.
// Live auctions come first, then the rest.
$stmt = db()->prepare("
    SELECT a.id, a.current_bid, a.end_time, a.status, a.winner_id,
           TIMESTAMPDIFF(SECOND, NOW(), a.end_time) AS seconds_left,
           p.title,
           (SELECT MAX(amount) FROM bids WHERE auction_id = a.id AND user_id = ?) AS my_bid,
           (SELECT file_name FROM product_images
            WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
    FROM auctions a
    JOIN products p ON p.id = a.product_id
    WHERE a.id IN (SELECT auction_id FROM bids WHERE user_id = ?)
    ORDER BY (a.status = 'active' AND a.end_time > NOW()) DESC, a.end_time ASC
");
$stmt->execute([$userId, $userId]);
$auctions = $stmt->fetchAll();

$pageTitle = 'My bids';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>My bids</h1>
</div>

<?php if (empty($auctions)): ?>
    <p class="empty">You haven't placed any bids yet. <a href="<?= BASE_URL ?>/index.php">Browse live auctions</a></p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Your highest bid</th>
                    <th>Current bid</th>
                    <th>Time left</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auctions as $a): ?>
                    <?php
                    $isHighest = (float) $a['my_bid'] === (float) $a['current_bid'];
                    $hasEnded  = $a['status'] !== 'active' || $a['seconds_left'] <= 0;

                    if ($a['status'] === 'removed') {
                        $status = 'removed';
                    } elseif ($hasEnded) {
                        $status = (int) $a['winner_id'] === $userId ? 'won' : 'lost';
                    } else {
                        $status = $isHighest ? 'winning' : 'outbid';
                    }
                    ?>
                    <tr>
                        <td>
                            <div class="item-cell">
                                <img src="<?= image_url($a['image']) ?>" alt="" class="thumb">
                                <a href="<?= BASE_URL ?>/auction.php?id=<?= $a['id'] ?>"><?= e($a['title']) ?></a>
                            </div>
                        </td>
                        <td><?= format_price($a['my_bid']) ?></td>
                        <td><?= format_price($a['current_bid']) ?></td>
                        <td>
                            <?php if ($hasEnded): ?>
                                <span class="muted">Ended</span>
                            <?php else: ?>
                                <span class="time-left" data-seconds-left="<?= (int) $a['seconds_left'] ?>"><?= time_left((int) $a['seconds_left']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $status ?>"><?= ucfirst($status) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
