<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$adminLinks = [
    'index.php' => 'Dashboard',
    'users.php' => 'Users',
];
?>
<aside class="admin-sidebar">
    <p class="sidebar-title">Admin</p>
    <?php foreach ($adminLinks as $file => $label): ?>
        <a href="<?= BASE_URL ?>/admin/<?= $file ?>" class="<?= $currentPage === $file ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</aside>
