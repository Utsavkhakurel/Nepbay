<?php
require __DIR__ . '/includes/init.php';
require_login();

$stmt = db()->prepare("
    SELECT a.id, a.starting_price, a.current_bid, a.end_time, a.status,
           p.title, c.name AS category,
           (SELECT file_name FROM product_images
            WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
    FROM auctions a
    JOIN products p   ON p.id = a.product_id
    JOIN categories c ON c.id = p.category_id
    WHERE p.seller_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([current_user()['id']]);
$auctions = $stmt->fetchAll();

$pageTitle = 'My auctions';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>My auctions</h1>
    <a href="<?= BASE_URL ?>/sell.php" class="btn btn-small">Sell an item</a>
</div>

<?php if (empty($auctions)): ?>
    <p class="empty">You haven't listed anything yet. <a href="<?= BASE_URL ?>/sell.php">Sell your first item</a></p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Starting price</th>
                    <th>Highest bid</th>
                    <th>Ends</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auctions as $a): ?>
                    <?php
                    if ($a['status'] === 'removed') {
                        $status = 'removed';
                    } elseif ($a['status'] === 'ended' || strtotime($a['end_time']) <= time()) {
                        $status = 'ended';
                    } else {
                        $status = 'active';
                    }
                    ?>
                    <tr>
                        <td>
                            <div class="item-cell">
                                <img src="<?= image_url($a['image']) ?>" alt="" class="thumb">
                                <a href="<?= BASE_URL ?>/auction.php?id=<?= $a['id'] ?>"><?= e($a['title']) ?></a>
                            </div>
                        </td>
                        <td><?= e($a['category']) ?></td>
                        <td><?= format_price($a['starting_price']) ?></td>
                        <td><?= $a['current_bid'] ? format_price($a['current_bid']) : '<span class="muted">No bids</span>' ?></td>
                        <td><?= format_datetime($a['end_time']) ?></td>
                        <td><span class="badge badge-<?= $status ?>"><?= ucfirst($status) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
