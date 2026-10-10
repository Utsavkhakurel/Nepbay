<?php
require __DIR__ . '/../includes/init.php';
require_admin();

// Report period. Everything below counts only what happened since $since:
// auctions listed, bids placed and users who joined in that period.
$periods = [
    '7'   => 'Last 7 days',
    '30'  => 'Last 30 days',
    '365' => 'Last 12 months',
    'all' => 'All time',
];
$period = $_GET['period'] ?? '30';
if (!isset($periods[$period])) {
    $period = '30';
}
$since = $period === 'all' ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', strtotime("-$period days"));

// ---- Auctions summary ----
$stmt = db()->prepare("
    SELECT COUNT(*)                                             AS listed,
           SUM(status = 'active')                               AS live,
           SUM(status = 'ended' AND winner_id IS NOT NULL)      AS sold,
           SUM(status = 'ended' AND winner_id IS NULL)          AS unsold,
           SUM(status = 'removed')                              AS removed,
           SUM(CASE WHEN status = 'ended' AND winner_id IS NOT NULL THEN current_bid ELSE 0 END) AS sales_value
    FROM auctions
    WHERE created_at >= ?
");
$stmt->execute([$since]);
$summary = $stmt->fetch();

$finished = (int) $summary['sold'] + (int) $summary['unsold'];
$sellRate = $finished > 0 ? round($summary['sold'] / $finished * 100) : 0;

$stmt = db()->prepare('SELECT COUNT(*) AS bids, COUNT(DISTINCT user_id) AS bidders FROM bids WHERE created_at >= ?');
$stmt->execute([$since]);
$bidStats = $stmt->fetch();

$stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= ?");
$stmt->execute([$since]);
$newUsers = (int) $stmt->fetchColumn();

// ---- Sales by category ----
$stmt = db()->prepare("
    SELECT c.name,
           COUNT(a.id) AS listed,
           COALESCE(SUM(a.status = 'ended' AND a.winner_id IS NOT NULL), 0) AS sold,
           COALESCE(SUM(CASE WHEN a.status = 'ended' AND a.winner_id IS NOT NULL THEN a.current_bid END), 0) AS sales_value
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN auctions a ON a.product_id = p.id AND a.created_at >= ?
    GROUP BY c.id, c.name
    ORDER BY sales_value DESC, listed DESC, c.name
");
$stmt->execute([$since]);
$byCategory = $stmt->fetchAll();

// ---- Top sellers by sales value ----
$stmt = db()->prepare("
    SELECT u.name, COUNT(*) AS sold, SUM(a.current_bid) AS sales_value
    FROM auctions a
    JOIN products p ON p.id = a.product_id
    JOIN users u    ON u.id = p.seller_id
    WHERE a.status = 'ended' AND a.winner_id IS NOT NULL AND a.created_at >= ?
    GROUP BY u.id, u.name
    ORDER BY sales_value DESC
    LIMIT 5
");
$stmt->execute([$since]);
$topSellers = $stmt->fetchAll();

// ---- Most active bidders ----
$stmt = db()->prepare("
    SELECT u.name, COUNT(*) AS bids, COUNT(DISTINCT b.auction_id) AS auctions
    FROM bids b
    JOIN users u ON u.id = b.user_id
    WHERE b.created_at >= ?
    GROUP BY u.id, u.name
    ORDER BY bids DESC
    LIMIT 5
");
$stmt->execute([$since]);
$topBidders = $stmt->fetchAll();

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-content report">
        <div class="section-head">
            <h1>Report: <?= $periods[$period] ?></h1>
            <button type="button" class="btn-outline no-print" onclick="window.print()">Print</button>
        </div>
        <p class="muted report-meta">
            <?php if ($period !== 'all'): ?>
                <?= format_date($since) ?> to <?= format_date(date('Y-m-d')) ?> &middot;
            <?php endif; ?>
            Generated <?= format_datetime(date('Y-m-d H:i:s')) ?>
        </p>

        <form method="get" class="filters no-print">
            <select name="period" onchange="this.form.submit()">
                <?php foreach ($periods as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $period === (string) $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-small">Show</button></noscript>
        </form>

        <div class="stats">
            <div class="stat">
                <span class="stat-label">Auctions listed</span>
                <span class="stat-value"><?= (int) $summary['listed'] ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Sold</span>
                <span class="stat-value"><?= (int) $summary['sold'] ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Total sales</span>
                <span class="stat-value"><?= format_price($summary['sales_value'] ?? 0) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Bids placed</span>
                <span class="stat-value"><?= (int) $bidStats['bids'] ?></span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><td>Still live</td><td><?= (int) $summary['live'] ?></td></tr>
                    <tr><td>Ended without bids</td><td><?= (int) $summary['unsold'] ?></td></tr>
                    <tr><td>Removed by admin</td><td><?= (int) $summary['removed'] ?></td></tr>
                    <tr><td>Sell rate (sold out of finished auctions)</td><td><?= $sellRate ?>%</td></tr>
                    <tr><td>Average sale price</td><td><?= $summary['sold'] ? format_price($summary['sales_value'] / $summary['sold']) : '-' ?></td></tr>
                    <tr><td>Users who placed bids</td><td><?= (int) $bidStats['bidders'] ?></td></tr>
                    <tr><td>New users</td><td><?= $newUsers ?></td></tr>
                </tbody>
            </table>
        </div>

        <h2 class="report-heading">Sales by category</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Listed</th>
                        <th>Sold</th>
                        <th>Sales value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byCategory as $c): ?>
                        <tr>
                            <td><?= e($c['name']) ?></td>
                            <td><?= (int) $c['listed'] ?></td>
                            <td><?= (int) $c['sold'] ?></td>
                            <td><?= format_price($c['sales_value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="report-columns">
            <div>
                <h2 class="report-heading">Top sellers</h2>
                <?php if (empty($topSellers)): ?>
                    <p class="empty">No sales in this period.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Seller</th><th>Sold</th><th>Sales value</th></tr></thead>
                            <tbody>
                                <?php foreach ($topSellers as $s): ?>
                                    <tr>
                                        <td><?= e($s['name']) ?></td>
                                        <td><?= (int) $s['sold'] ?></td>
                                        <td><?= format_price($s['sales_value']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <h2 class="report-heading">Most active bidders</h2>
                <?php if (empty($topBidders)): ?>
                    <p class="empty">No bids in this period.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Bidder</th><th>Bids</th><th>Auctions</th></tr></thead>
                            <tbody>
                                <?php foreach ($topBidders as $b): ?>
                                    <tr>
                                        <td><?= e($b['name']) ?></td>
                                        <td><?= (int) $b['bids'] ?></td>
                                        <td><?= (int) $b['auctions'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
