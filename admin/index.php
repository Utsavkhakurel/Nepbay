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

        <div class="section-head">
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
