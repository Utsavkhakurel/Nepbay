<?php
require __DIR__ . '/includes/init.php';
require_login();

$stmt = db()->prepare("
    SELECT a.id, a.starting_price, a.current_bid, a.end_time, a.status, a.winner_id,
           p.title,
           (SELECT file_name FROM product_images
            WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
    FROM auctions a
    JOIN products p   ON p.id = a.product_id
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
</div>

<?php if (empty($auctions)): ?>
    <div class="empty">
        <p>You haven't listed anything yet.</p>
        <a href="<?= BASE_URL ?>/sell.php" class="btn btn-small">Sell an item</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Status</th>
                    <th>Starting price</th>
                    <th>Highest bid</th>
                    <th>Ends</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auctions as $a): ?>
                    <?php
                    if ($a['status'] === 'removed') {
                        $status = 'removed';
                    } elseif ($a['status'] === 'ended') {
                        $status = $a['winner_id'] ? 'sold' : 'unsold';
                    } else {
                        $status = 'live';
                    }
                    $noBids = $a['current_bid'] === null;
                    ?>
                    <tr>
                        <td>
                            <div class="item-cell">
                                <img src="<?= image_url($a['image']) ?>" alt="" class="thumb">
                                <a href="<?= BASE_URL ?>/auction.php?id=<?= $a['id'] ?>"><?= e($a['title']) ?></a>
                            </div>
                        </td>
                        <td><span class="badge badge-<?= $status ?>"><?= ucfirst($status) ?></span></td>
                        <td><?= format_price($a['starting_price']) ?></td>
                        <td><?= $a['current_bid'] ? format_price($a['current_bid']) : '<span class="muted">No bids</span>' ?></td>
                        <td><?= format_datetime($a['end_time']) ?></td>
                        <td class="actions">
                            <?php if ($status === 'live' && $noBids): ?>
                                <a href="<?= BASE_URL ?>/edit-auction.php?id=<?= $a['id'] ?>" class="btn-outline">Edit</a>
                            <?php endif; ?>
                            <?php if ($status !== 'removed' && $noBids): ?>
                                <form method="post" action="<?= BASE_URL ?>/delete-auction.php" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="auction_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn-outline danger"
                                            onclick="return confirm(<?= e(json_encode('Delete "' . $a['title'] . '"? This can\'t be undone.')) ?>)">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
