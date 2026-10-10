<?php
// A few categories for the footer links
$footerCategories = db()->query('SELECT id, name FROM categories ORDER BY name LIMIT 6')->fetchAll();
?>
</main>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-about">
                <a href="<?= BASE_URL ?>/index.php" class="logo">Nep<span>bay</span></a>
                <p>Buy and sell second-hand and new items through online auctions. Made for buyers and sellers across Nepal.</p>
            </div>

            <div>
                <h4>Browse</h4>
                <a href="<?= BASE_URL ?>/index.php">Live auctions</a>
                <a href="<?= BASE_URL ?>/index.php?sort=ending">Ending soon</a>
                <a href="<?= BASE_URL ?>/index.php?sort=newest">Newly listed</a>
                <a href="<?= BASE_URL ?>/index.php?sort=price_low">Lowest price</a>
            </div>

            <div>
                <h4>Categories</h4>
                <?php foreach ($footerCategories as $c): ?>
                    <a href="<?= BASE_URL ?>/index.php?category=<?= $c['id'] ?>"><?= e($c['name']) ?></a>
                <?php endforeach; ?>
            </div>

            <div>
                <h4>Your account</h4>
                <?php if (!is_logged_in()): ?>
                    <a href="<?= BASE_URL ?>/login.php">Log in</a>
                    <a href="<?= BASE_URL ?>/register.php">Create an account</a>
                <?php elseif (is_admin()): ?>
                    <a href="<?= BASE_URL ?>/admin/index.php">Admin panel</a>
                    <a href="<?= BASE_URL ?>/admin/reports.php">Reports</a>
                    <a href="<?= BASE_URL ?>/profile.php">Profile &amp; password</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/sell.php">Sell an item</a>
                    <a href="<?= BASE_URL ?>/my-auctions.php">My auctions</a>
                    <a href="<?= BASE_URL ?>/my-bids.php">My bids</a>
                    <a href="<?= BASE_URL ?>/profile.php">Profile &amp; password</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</span>
            <span>Kathmandu, Nepal</span>
        </div>
    </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/countdown.js"></script>
<script>
// Close the account menu when clicking anywhere else on the page
document.addEventListener('click', function (event) {
    const menu = document.querySelector('.account-menu[open]');
    if (menu && !menu.contains(event.target)) {
        menu.removeAttribute('open');
    }
});
</script>
</body>
</html>
