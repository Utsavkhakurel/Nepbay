<?php
require __DIR__ . '/includes/init.php';

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1>Bid. Win. Sell.</h1>
    <p>Nepbay is Nepal's online auction platform. Auctions are coming soon.</p>
    <?php if (!is_logged_in()): ?>
        <a href="<?= BASE_URL ?>/register.php" class="btn">Get started</a>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
