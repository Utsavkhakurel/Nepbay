<?php
// Shared page header. Set $pageTitle before including this file.
$user  = current_user();
$flash = get_flash();

// Used to highlight the current page in the menu and to keep the search text on the home page
$currentScript = substr($_SERVER['SCRIPT_NAME'], strlen(BASE_URL));
$searchText    = $currentScript === '/index.php' ? ($_GET['q'] ?? '') : '';

function nav_link(string $path, string $label, string $currentScript): string
{
    // Every admin page counts as "Admin"
    $isCurrent = $currentScript === $path
        || ($path === '/admin/index.php' && str_starts_with($currentScript, '/admin/'));
    $class = $isCurrent ? ' class="current"' : '';
    return '<a href="' . BASE_URL . $path . '"' . $class . '>' . $label . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(isset($pageTitle) ? "$pageTitle | " . SITE_NAME : SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <!-- ?v= changes whenever the file is edited, so browsers don't keep an old copy -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="navbar">
    <div class="container nav-inner">
        <a href="<?= BASE_URL ?>/index.php" class="logo"><span class="logo-mark">N</span>Nepbay</a>

        <form action="<?= BASE_URL ?>/index.php" method="get" class="nav-search" role="search">
            <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="<?= e($searchText) ?>" placeholder="Search auctions" aria-label="Search auctions">
        </form>

        <div class="nav-actions">
            <?php if ($user): ?>
                <?php if ($user['role'] !== 'admin'): ?>
                    <a href="<?= BASE_URL ?>/sell.php" class="btn btn-small">+ Sell an item</a>
                <?php endif; ?>

                <!-- Account menu: <details> opens and closes on click without any JavaScript -->
                <details class="account-menu">
                    <summary>
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                        <span class="account-name"><?= e(explode(' ', $user['name'])[0]) ?></span>
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
                <a href="<?= BASE_URL ?>/login.php" class="btn-ghost">Log in</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-small">Register</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Second row: page links -->
    <div class="nav-bottom">
        <nav class="container nav-links">
            <?= nav_link('/index.php', 'Home', $currentScript) ?>
            <?php if ($user && $user['role'] === 'admin'): ?>
                <?= nav_link('/admin/index.php', 'Admin panel', $currentScript) ?>
            <?php elseif ($user): ?>
                <?= nav_link('/my-bids.php', 'My bids', $currentScript) ?>
                <?= nav_link('/my-auctions.php', 'My auctions', $currentScript) ?>
                <?= nav_link('/profile.php', 'Profile', $currentScript) ?>
            <?php else: ?>
                <?= nav_link('/sell.php', 'Sell an item', $currentScript) ?>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
