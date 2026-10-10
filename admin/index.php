<?php
require __DIR__ . '/../includes/init.php';
require_admin();

$stats = db()->query("
    SELECT COUNT(*)                                  AS total,
           SUM(status = 'active')                    AS active,
           SUM(status = 'blocked')                   AS blocked,
           SUM(created_at >= NOW() - INTERVAL 7 DAY) AS new_this_week
    FROM users
    WHERE role = 'user'
")->fetch();

$auctionStats = db()->query("
    SELECT SUM(status = 'active')                                                          AS live,
           SUM(status = 'ended' AND winner_id IS NOT NULL)                                 AS sold,
           SUM(CASE WHEN status = 'ended' AND winner_id IS NOT NULL THEN current_bid END) AS sales_value
    FROM auctions
")->fetch();

$bidsToday = (int) db()->query('SELECT COUNT(*) FROM bids WHERE created_at >= CURDATE()')->fetchColumn();

$latestAuctions = db()->query("
    SELECT a.id, a.starting_price, a.current_bid, a.status, a.winner_id, p.title, u.name AS seller_name
    FROM auctions a
    JOIN products p ON p.id = a.product_id
    JOIN users u    ON u.id = p.seller_id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll();

$recentUsers = db()->query("
    SELECT id, name, email, status, created_at
    FROM users
    WHERE role = 'user'
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-content">
        <h1>Dashboard</h1>

        <div class="stats">
            <div class="stat">
                <span class="stat-label">Total users</span>
                <span class="stat-value"><?= (int) $stats['total'] ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Active</span>
                <span class="stat-value"><?= (int) $stats['active'] ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Blocked</span>
                <span class="stat-value"><?= (int) $stats['blocked'] ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Joined this week</span>
                <span class="stat-value"><?= (int) $stats['new_this_week'] ?></span>
            </div>
        </div>

        <div class="stats">
            <div class="stat">
                <span class="stat-label">Live auctions</span>
                <span class="stat-value"><?= (int) $auctionStats['live'] ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Items sold</span>
                <span class="stat-value"><?= (int) $auctionStats['sold'] ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Total sales</span>
                <span class="stat-value"><?= format_price($auctionStats['sales_value'] ?? 0) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Bids today</span>
                <span class="stat-value"><?= $bidsToday ?></span>
            </div>
        </div>

        <div class="section-head">
            <h2>Latest auctions</h2>
            <a href="<?= BASE_URL ?>/admin/auctions.php">View all</a>
        </div>

        <?php if (empty($latestAuctions)): ?>
            <p class="empty">No auctions have been listed yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Seller</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestAuctions as $a): ?>
                            <?php $s = auction_status($a); ?>
                            <tr>
                                <td class="wrap"><a href="<?= BASE_URL ?>/auction.php?id=<?= $a['id'] ?>"><?= e($a['title']) ?></a></td>
                                <td><?= e($a['seller_name']) ?></td>
                                <td><?= format_price($a['current_bid'] ?? $a['starting_price']) ?></td>
                                <td><span class="badge badge-<?= $s ?>"><?= ucfirst($s) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="section-head spaced">
            <h2>Recent registrations</h2>
            <a href="<?= BASE_URL ?>/admin/users.php">View all</a>
        </div>

        <?php if (empty($recentUsers)): ?>
            <p class="empty">No users have registered yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td><?= e($u['name']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><span class="badge badge-<?= e($u['status']) ?>"><?= ucfirst(e($u['status'])) ?></span></td>
                                <td><?= format_date($u['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
