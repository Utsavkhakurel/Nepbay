<?php
require __DIR__ . '/../includes/init.php';
require_admin();

// Remove an inappropriate listing, or restore one removed by mistake
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $auctionId = (int) ($_POST['auction_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    $stmt = db()->prepare('SELECT p.title FROM auctions a JOIN products p ON p.id = a.product_id WHERE a.id = ?');
    $stmt->execute([$auctionId]);
    $title = $stmt->fetchColumn();

    if ($title && $action === 'remove') {
        db()->prepare("UPDATE auctions SET status = 'removed' WHERE id = ?")->execute([$auctionId]);
        set_flash('success', '"' . $title . '" has been removed. Users can no longer see or bid on it.');
    } elseif ($title && $action === 'restore') {
        // Set it back to active. If its time is already up, close_ended_auctions()
        // will end it and pick the winner on the next page load.
        db()->prepare("UPDATE auctions SET status = 'active' WHERE id = ? AND status = 'removed'")->execute([$auctionId]);
        set_flash('success', '"' . $title . '" has been restored.');
    }

    redirect('/admin/auctions.php?' . $_SERVER['QUERY_STRING']);
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

// Filters
$search     = trim($_GET['q'] ?? '');
$status     = $_GET['status'] ?? '';
$categoryId = (int) ($_GET['category'] ?? 0);

$statusFilters = [
    'live'    => "a.status = 'active'",
    'sold'    => "a.status = 'ended' AND a.winner_id IS NOT NULL",
    'unsold'  => "a.status = 'ended' AND a.winner_id IS NULL",
    'removed' => "a.status = 'removed'",
];

$where  = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[]  = '(p.title LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (isset($statusFilters[$status])) {
    $where[] = $statusFilters[$status];
}
if ($categoryId > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $categoryId;
}
$whereSql = implode(' AND ', $where);

$fromSql = "
    FROM auctions a
    JOIN products p   ON p.id = a.product_id
    JOIN categories c ON c.id = p.category_id
    JOIN users u      ON u.id = p.seller_id
    WHERE $whereSql
";

// Pagination
$perPage = 20;
$stmt = db()->prepare("SELECT COUNT(*) $fromSql");
$stmt->execute($params);
$totalAuctions = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalAuctions / $perPage));
$page   = min(max(1, (int) ($_GET['page'] ?? 1)), $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("
    SELECT a.id, a.starting_price, a.current_bid, a.end_time, a.status, a.winner_id,
           p.title, c.name AS category, u.name AS seller_name,
           (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) AS bid_count,
           (SELECT file_name FROM product_images
            WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
    $fromSql
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$auctions = $stmt->fetchAll();

$pageTitle = 'Auctions';
require __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-content">
        <h1>Auctions <span class="count"><?= $totalAuctions ?></span></h1>

        <form method="get" class="filters">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search item or seller">
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (array_keys($statusFilters) as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $categoryId === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-small">Filter</button>
            <?php if ($search !== '' || $status !== '' || $categoryId > 0): ?>
                <a href="<?= BASE_URL ?>/admin/auctions.php">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($auctions)): ?>
            <p class="empty">No auctions found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Seller</th>
                            <th>Price</th>
                            <th>Bids</th>
                            <th>Ends</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auctions as $a): ?>
                            <?php $s = auction_status($a); ?>
                            <tr>
                                <td>
                                    <div class="item-cell">
                                        <img src="<?= image_url($a['image']) ?>" alt="" class="thumb">
                                        <div>
                                            <a href="<?= BASE_URL ?>/auction.php?id=<?= $a['id'] ?>"><?= e($a['title']) ?></a>
                                            <div class="muted small"><?= e($a['category']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($a['seller_name']) ?></td>
                                <td><?= format_price($a['current_bid'] ?? $a['starting_price']) ?></td>
                                <td><?= (int) $a['bid_count'] ?></td>
                                <td><?= format_date($a['end_time']) ?></td>
                                <td><span class="badge badge-<?= $s ?>"><?= ucfirst($s) ?></span></td>
                                <td class="actions">
                                    <form method="post" action="?<?= e($_SERVER['QUERY_STRING']) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="auction_id" value="<?= $a['id'] ?>">
                                        <?php if ($s === 'removed'): ?>
                                            <button type="submit" name="action" value="restore" class="btn-outline">Restore</button>
                                        <?php else: ?>
                                            <button type="submit" name="action" value="remove" class="btn-outline danger"
                                                    onclick="return confirm(<?= e(json_encode('Remove "' . $a['title'] . '"? It will be hidden from all users.')) ?>)">Remove</button>
                                        <?php endif; ?>
                                    </form>
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
