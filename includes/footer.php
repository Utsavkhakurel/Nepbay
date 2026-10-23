</main>

<footer class="footer">
    <div class="container footer-top">
        <div class="footer-about">
            <a href="<?= BASE_URL ?>/index.php" class="logo"><img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Nepbay"></a>
            <p>Online auctions for second-hand items in Nepal.</p>
            <p class="copyright">&copy; <?= date('Y') ?> Nepbay &middot; Kathmandu, Nepal</p>
        </div>

        <div class="footer-col">
            <p class="footer-heading">Buy</p>
            <a href="<?= BASE_URL ?>/index.php">Live auctions</a>
            <a href="<?= BASE_URL ?>/index.php?sort=newest">Newly listed</a>
            <a href="<?= BASE_URL ?>/my-bids.php">My bids</a>
        </div>

        <div class="footer-col">
            <p class="footer-heading">Sell</p>
            <a href="<?= BASE_URL ?>/sell.php">Sell an item</a>
            <a href="<?= BASE_URL ?>/my-auctions.php">My auctions</a>
            <a href="<?= BASE_URL ?>/profile.php">Profile</a>
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
