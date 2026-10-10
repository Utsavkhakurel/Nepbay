<?php
// Shared page header. Set $pageTitle before including this file.
$user  = current_user();
$flash = get_flash();

// Used to highlight the current page in the menu and to keep the search text on the home page
$currentScript = substr($_SERVER['SCRIPT_NAME'], strlen(BASE_URL));
$searchText    = $currentScript === '/index.php' ? ($_GET['q'] ?? '') : '';

function nav_link(string $path, string $label, string $currentScript): string
{
    $class = $currentScript === $path ? ' class="current"' : '';
    return '<a href="' . BASE_URL . $path . '"' . $class . '>' . $label . '</a>';
}
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

        <form action="<?= BASE_URL ?>/index.php" method="get" class="nav-search" role="search">
            <input type="search" name="q" value="<?= e($searchText) ?>" placeholder="Search for phones, bikes, books..." aria-label="Search auctions">
            <button type="submit">Search</button>
        </form>

        <nav>
            <?php if ($user): ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <?= nav_link('/admin/index.php', 'Admin panel', $currentScript) ?>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/sell.php" class="btn btn-small">Sell<span class="hide-sm"> an item</span></a>
                <?php endif; ?>

                <!-- Account menu: <details> opens and closes on click without any JavaScript -->
                <details class="account-menu">
                    <summary>
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                        <span class="hide-sm"><?= e(explode(' ', $user['name'])[0]) ?></span>
                    </summary>
                    <div class="menu">
                        <div class="menu-head">
                            <strong><?= e($user['name']) ?></strong>
                            <span><?= e($user['email']) ?></span>
                        </div>
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="<?= BASE_URL ?>/admin/index.php">Admin panel</a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/my-auctions.php">My auctions</a>
                            <a href="<?= BASE_URL ?>/my-bids.php">My bids</a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/profile.php">Profile &amp; password</a>
                        <form action="<?= BASE_URL ?>/logout.php" method="post">
                            <?= csrf_field() ?>
                            <button type="submit">Log out</button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <?= nav_link('/login.php', 'Log in', $currentScript) ?>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-small">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
