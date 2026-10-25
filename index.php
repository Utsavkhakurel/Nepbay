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
    SELECT a.id, a.current_bid, a.start_time, a.end_time,
           COALESCE(a.current_bid, a.starting_price)   AS price,
           TIMESTAMPDIFF(SECOND, NOW(), a.end_time)    AS seconds_left,
           p.title, c.name AS category,
           (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) AS bid_count,
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

<?php
// Name of the selected category, for the heading
$categoryName = '';
foreach ($categories as $c) {
    if ($c['id'] === $categoryId) {
        $categoryName = $c['name'];
    }
}

// Link to a category while keeping the current search and sort
function category_link(int $id): string
{
    $query = array_filter(['q' => $_GET['q'] ?? '', 'sort' => $_GET['sort'] ?? '', 'category' => $id ?: '']);
    return BASE_URL . '/index.php' . ($query ? '?' . http_build_query($query) : '');
}
?>

<nav class="category-bar" aria-label="Categories">
    <a href="<?= category_link(0) ?>" class="<?= $categoryId === 0 ? 'current' : '' ?>"<?= $categoryId === 0 ? ' aria-current="page"' : '' ?>>All</a>
    <?php foreach ($categories as $c): ?>
        <a href="<?= category_link($c['id']) ?>" class="<?= $categoryId === $c['id'] ? 'current' : '' ?>"<?= $categoryId === $c['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
</nav>

<div class="results-head">
    <div>
        <h1>
            <?php if ($search !== ''): ?>
                Results for "<?= e($search) ?>"
            <?php elseif ($categoryName !== ''): ?>
                <?= e($categoryName) ?>
            <?php else: ?>
                Live auctions
            <?php endif; ?>
        </h1>
        <p class="muted">
            <?= $totalAuctions ?> <?= $totalAuctions === 1 ? 'auction' : 'auctions' ?>
            <?= $search !== '' && $categoryName !== '' ? 'in ' . e($categoryName) : '' ?>
            <?php if ($search !== ''): ?>
                &middot; <a href="<?= BASE_URL ?>/index.php<?= $categoryId ? '?category=' . $categoryId : '' ?>">Clear search</a>
            <?php endif; ?>
        </p>
    </div>

    <form method="get" class="sort-form">
        <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
        <?php if ($categoryId): ?><input type="hidden" name="category" value="<?= $categoryId ?>"><?php endif; ?>
        <label for="sort">Sort by</label>
        <select id="sort" name="sort" onchange="this.form.submit()">
            <?php foreach ($sortOptions as $key => [$label]): ?>
                <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn-outline">Go</button></noscript>
    </form>
</div>

<?php if (empty($auctions)): ?>
    <div class="empty">
        <?php if ($search !== '' || $categoryId > 0): ?>
            <p>No live auctions match your search.</p>
            <a href="<?= BASE_URL ?>/index.php">See all live auctions</a>
        <?php else: ?>
            <p>There are no live auctions right now.</p>
            <?php if (!is_admin()): ?>
                <a href="<?= BASE_URL ?>/sell.php" class="btn btn-small">Sell an item</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($auctions as $a): ?>
            <a href="<?= BASE_URL ?>/auction.php?id=<?= $a['id'] ?>" class="card">
                <img src="<?= image_url($a['image']) ?>" alt="" class="card-img" loading="lazy">
                <div class="card-body">
                    <p class="card-category">
                        <?= e($a['category']) ?>
                        <?php if (is_hourly($a['start_time'], $a['end_time'])): ?><span class="badge badge-hourly">Hourly</span><?php endif; ?>
                    </p>
                    <h2 class="card-title"><?= e($a['title']) ?></h2>
                    <p class="card-price">
                        <span class="price"><?= format_price($a['price']) ?></span>
                        <span class="price-label"><?= $a['current_bid'] ? 'current bid' : 'starting price' ?></span>
                    </p>
                    <div class="card-foot">
                        <span><?= $a['bid_count'] ? $a['bid_count'] . ($a['bid_count'] == 1 ? ' bid' : ' bids') : 'No bids' ?></span>
                        <span class="time-left" data-seconds-left="<?= (int) $a['seconds_left'] ?>"><?= time_left((int) $a['seconds_left']) ?></span>
                    </div>
                    <p class="card-ends"><?= ends_label($a['start_time'], $a['end_time']) ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Pages">
            <?php if ($page > 1): ?>
                <a href="<?= e(page_link($page - 1)) ?>">&larr; Previous</a>
            <?php endif; ?>
            <span>Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= e(page_link($page + 1)) ?>">Next &rarr;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
