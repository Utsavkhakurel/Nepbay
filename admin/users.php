<?php
require __DIR__ . '/../includes/init.php';
require_admin();

// Block / unblock a user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Only normal users can be blocked, never admins
    $stmt = db()->prepare("SELECT name FROM users WHERE id = ? AND role = 'user'");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if ($target && in_array($action, ['block', 'unblock'], true)) {
        $newStatus = $action === 'block' ? 'blocked' : 'active';
        db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $userId]);
        refresh_current_bids($userId);
        set_flash('success', $target['name'] . ' has been ' . ($action === 'block' ? 'blocked.' : 'unblocked.'));
    }

    // Go back to the same search / page the admin was on
    redirect('/admin/users.php?' . $_SERVER['QUERY_STRING']);
}

// Filters
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$where  = ["role = 'user'"];
$params = [];

if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status === 'active' || $status === 'blocked') {
    $where[]  = 'status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

// Pagination
$perPage = 20;
$stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE $whereSql");
$stmt->execute($params);
$totalUsers = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page   = min(max(1, (int) ($_GET['page'] ?? 1)), $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("
    SELECT id, name, email, phone, status, created_at
    FROM users
    WHERE $whereSql
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users';
require __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-content">
        <h1>Users <span class="count"><?= $totalUsers ?></span></h1>

        <form method="get" class="filters">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name or email">
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
            </select>
            <button type="submit" class="btn btn-small">Filter</button>
            <?php if ($search !== '' || $status !== ''): ?>
                <a href="<?= BASE_URL ?>/admin/users.php">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($users)): ?>
            <p class="empty">No users found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= e($u['name']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><?= $u['phone'] ? e($u['phone']) : '<span class="muted">-</span>' ?></td>
                                <td><span class="badge badge-<?= e($u['status']) ?>"><?= ucfirst(e($u['status'])) ?></span></td>
                                <td><?= format_date($u['created_at']) ?></td>
                                <td class="actions">
                                    <form method="post" action="?<?= e($_SERVER['QUERY_STRING']) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <?php if ($u['status'] === 'active'): ?>
                                            <button type="submit" name="action" value="block" class="btn-outline danger"
                                                    onclick="return confirm(<?= e(json_encode('Block ' . $u['name'] . '?')) ?>)">Block</button>
                                        <?php else: ?>
                                            <button type="submit" name="action" value="unblock" class="btn-outline">Unblock</button>
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
