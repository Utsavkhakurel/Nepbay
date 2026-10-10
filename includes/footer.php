</main>

<footer class="footer">
    <div class="container footer-inner">
        <div class="footer-left">
            <a href="<?= BASE_URL ?>/index.php" class="logo"><span class="logo-mark">N</span>Nepbay</a>
            <span>&copy; <?= date('Y') ?> &middot; Kathmandu, Nepal</span>
        </div>
        <div class="footer-links">
            <a href="<?= BASE_URL ?>/index.php">Home</a>
            <?php if (!is_logged_in()): ?>
                <a href="<?= BASE_URL ?>/login.php">Log in</a>
                <a href="<?= BASE_URL ?>/register.php">Register</a>
            <?php elseif (is_admin()): ?>
                <a href="<?= BASE_URL ?>/admin/index.php">Admin panel</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/sell.php">Sell an item</a>
                <a href="<?= BASE_URL ?>/my-bids.php">My bids</a>
            <?php endif; ?>
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
