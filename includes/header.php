<?php
// Shared page header. Set $pageTitle before including this file.
$user = current_user();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(isset($pageTitle) ? "$pageTitle | " . SITE_NAME : SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="navbar">
    <div class="container nav-inner">
        <a href="<?= BASE_URL ?>/index.php" class="logo">Nep<span>bay</span></a>
        <nav>
            <?php if ($user): ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= BASE_URL ?>/admin/index.php">Admin Panel</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/sell.php">Sell</a>
                    <a href="<?= BASE_URL ?>/my-auctions.php">My auctions</a>
                <?php endif; ?>
                <span class="nav-user">Hi, <?= e($user['name']) ?></span>
                <form action="<?= BASE_URL ?>/logout.php" method="post" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Logout</button>
                </form>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php">Login</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-small">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
