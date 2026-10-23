<?php
require __DIR__ . '/../includes/init.php';
require_admin();

$search     = trim($_GET['q'] ?? '');
$suspicious = isset($_GET['suspicious']);

// A bid looks like shill bidding (the seller bidding on their own item from a
// second account) when it came from the same IP the item was listed from, or the
// bidder has the same phone number as the seller. Shared Wi-Fi can also cause an
// IP match, so this only flags bids for the admin to look at.
$phoneDigits = fn($column) => "RIGHT(REPLACE(REPLACE(REPLACE($column, ' ', ''), '-', ''), '+', ''), 10)";
$suspiciousSql = "(b.ip_address = p.ip_address
                   OR " . $phoneDigits('u.phone') . " = " . $phoneDigits('s.phone') . ")";

$where  = ['1 = 1'];
$params = [];
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR p.title LIKE ?)';
    $params  = ["%$search%", "%$search%", "%$search%"];
}
if ($suspicious) {
    $where[] = $suspiciousSql;
}
$whereSql = implode(' AND ', $where);

$fromSql = "
    FROM bids b
    JOIN users u    ON u.id = b.user_id
    JOIN auctions a ON a.id = b.auction_id
    JOIN products p ON p.id = a.product_id
    JOIN users s    ON s.id = p.seller_id
    WHERE $whereSql
";

// Pagination
$perPage = 25;
$stmt = db()->prepare("SELECT COUNT(*) $fromSql");
$stmt->execute($params);
$totalBids  = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalBids / $perPage));
$page   = min(max(1, (int) ($_GET['page'] ?? 1)), $totalPages);
$offset = ($page - 1) * $perPage;

// Newest bids first. "is_highest" tells us if this bid is the auction's current top bid.
$stmt = db()->prepare("
    SELECT b.amount, b.created_at,
           u.name AS bidder_name, u.email AS bidder_email,
           a.id AS auction_id, p.title, s.name AS seller_name,
           (b.amount = a.current_bid) AS is_highest,
           (b.ip_address = p.ip_address) AS same_ip,
           COALESCE($suspiciousSql, 0) AS is_suspicious
    $fromSql
    ORDER BY b.created_at DESC, b.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$bids = $stmt->fetchAll();

$pageTitle = 'Bids';
require __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-content">
        <h1>Bids <span class="count"><?= $totalBids ?></span></h1>

        <form method="get" class="filters">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search bidder or item">
            <label>
                <input type="checkbox" name="suspicious" value="1" <?= $suspicious ? 'checked' : '' ?>>
                Suspicious only
            </label>
            <button type="submit" class="btn btn-small">Search</button>
            <?php if ($search !== '' || $suspicious): ?>
                <a href="<?= BASE_URL ?>/admin/bids.php">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($bids)): ?>
            <p class="empty">No bids found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Bidder</th>
                            <th>Item</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bids as $b): ?>
                            <tr>
                                <td><?= format_datetime($b['created_at']) ?></td>
                                <td>
                                    <?= e($b['bidder_name']) ?>
                                    <div class="muted small"><?= e($b['bidder_email']) ?></div>
                                </td>
                                <td class="wrap">
                                    <a href="<?= BASE_URL ?>/auction.php?id=<?= $b['auction_id'] ?>"><?= e($b['title']) ?></a>
                                    <div class="muted small">Seller: <?= e($b['seller_name']) ?></div>
                                    <?php if ($b['is_suspicious']): ?>
                                        <span class="badge badge-outbid" title="Possible shill bidding">
                                            <?= $b['same_ip'] ? 'Same IP as seller' : 'Same phone as seller' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= format_price($b['amount']) ?>
                                    <?php if ($b['is_highest']): ?>
                                        <span class="badge badge-live">Highest</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= e(page_link($page - 1)) ?>">&larr; Previous</a>
                    <?php endif; ?>
                    <span>Page <?= $page ?> of <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= e(page_link($page + 1)) ?>">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
