<?php
require __DIR__ . '/includes/init.php';

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$search     = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$sort       = $_GET['sort'] ?? 'ending';

$sortOptions = [
    'ending'     => ['Ending soonest',      'a.end_time ASC'],
    'newest'     => ['Newly listed',        'a.created_at DESC'],
    'price_low'  => ['Price: low to high', 'price ASC'],
    'price_high' => ['Price: high to low', 'price DESC'],
];
if (!isset($sortOptions[$sort])) {
    $sort = 'ending';
}

// Only live auctions from sellers who aren't blocked
$where  = ["a.status = 'active'", 'a.end_time > NOW()', "u.status = 'active'"];
$params = [];

if ($search !== '') {
    $where[]  = '(p.title LIKE ? OR p.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
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
$perPage = 12;
$stmt = db()->prepare("SELECT COUNT(*) $fromSql");
$stmt->execute($params);
$totalAuctions = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalAuctions / $perPage));
$page   = min(max(1, (int) ($_GET['page'] ?? 1)), $totalPages);
$offset = ($page - 1) * $perPage;

$orderBy = $sortOptions[$sort][1];
$stmt = db()->prepare("
    SELECT a.id, a.current_bid,
           COALESCE(a.current_bid, a.starting_price)   AS price,
           TIMESTAMPDIFF(SECOND, NOW(), a.end_time)    AS seconds_left,
           p.title, c.name AS category,
           (SELECT file_name FROM product_images
            WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
    $fromSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$auctions = $stmt->fetchAll();

$pageTitle = 'Live auctions';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Live auctions <span class="count"><?= $totalAuctions ?></span></h1>
</div>

<form method="get" class="filters">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search items">
    <select name="category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $categoryId === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="sort">
        <?php foreach ($sortOptions as $key => [$label]): ?>
            <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-small">Search</button>
    <?php if ($search !== '' || $categoryId > 0): ?>
        <a href="<?= BASE_URL ?>/index.php">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($auctions)): ?>
    <p class="empty">
        <?php if ($search !== '' || $categoryId > 0): ?>
            No auctions match your search.
        <?php else: ?>
            There are no live auctions right now.
            <?php if (is_logged_in() && !is_admin()): ?><a href="<?= BASE_URL ?>/sell.php">Sell an item</a><?php endif; ?>
        <?php endif; ?>
    </p>
<?php else: ?>
    <div class="grid">
        <?php foreach ($auctions as $a): ?>
            <a href="<?= BASE_URL ?>/auction.php?id=<?= $a['id'] ?>" class="card">
                <img src="<?= image_url($a['image']) ?>" alt="" class="card-img" loading="lazy">
                <div class="card-body">
                    <h3 class="card-title"><?= e($a['title']) ?></h3>
                    <p class="card-category"><?= e($a['category']) ?></p>
                    <div class="card-foot">
                        <div>
                            <span class="price-label"><?= $a['current_bid'] ? 'Current bid' : 'Starting price' ?></span>
                            <span class="price"><?= format_price($a['price']) ?></span>
                        </div>
                        <span class="time-left" data-seconds-left="<?= (int) $a['seconds_left'] ?>"><?= time_left((int) $a['seconds_left']) ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
