<?php
require __DIR__ . '/includes/init.php';

$stmt = db()->prepare("
    SELECT a.id, a.starting_price, a.current_bid, a.start_time, a.end_time, a.status,
           TIMESTAMPDIFF(SECOND, NOW(), a.end_time) AS seconds_left,
           p.id AS product_id, p.title, p.description, p.seller_id,
           c.id AS category_id, c.name AS category,
           u.name AS seller_name, u.created_at AS seller_since
    FROM auctions a
    JOIN products p   ON p.id = a.product_id
    JOIN categories c ON c.id = p.category_id
    JOIN users u      ON u.id = p.seller_id
    WHERE a.id = ?
");
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$auction = $stmt->fetch();

// Removed auctions are only visible to the admin
if (!$auction || ($auction['status'] === 'removed' && !is_admin())) {
    http_response_code(404);
    $pageTitle = 'Auction not found';
    require __DIR__ . '/includes/header.php';
    echo '<p class="empty">This auction doesn\'t exist or has been removed. <a href="' . BASE_URL . '/index.php">Back to live auctions</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = db()->prepare('SELECT file_name FROM product_images WHERE product_id = ? ORDER BY sort_order');
$stmt->execute([$auction['product_id']]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Bid history, highest first
$stmt = db()->prepare('
    SELECT b.user_id, b.amount, b.created_at, u.name
    FROM bids b
    JOIN users u ON u.id = b.user_id
    WHERE b.auction_id = ?
    ORDER BY b.amount DESC
');
$stmt->execute([$auction['id']]);
$bids = $stmt->fetchAll();

$secondsLeft     = (int) $auction['seconds_left'];
$hasEnded        = $auction['status'] !== 'active' || $secondsLeft <= 0;
$userId          = is_logged_in() ? current_user()['id'] : 0;
$isOwner         = $userId === (int) $auction['seller_id'];
$highestBidderId = $bids ? (int) $bids[0]['user_id'] : 0;
$minimumBid      = minimum_bid($auction['starting_price'], $auction['current_bid']);

$pageTitle = $auction['title'];
require __DIR__ . '/includes/header.php';
?>

<p class="breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Live auctions</a> /
    <a href="<?= BASE_URL ?>/index.php?category=<?= $auction['category_id'] ?>"><?= e($auction['category']) ?></a>
</p>

<?php if ($auction['status'] === 'removed'): ?>
    <div class="alert alert-error">This auction was removed by an admin and is hidden from users.</div>
<?php endif; ?>

<div class="auction">
    <div class="gallery">
        <img src="<?= image_url($images[0] ?? null) ?>" alt="<?= e($auction['title']) ?>" id="main-photo" class="main-photo">
        <?php if (count($images) > 1): ?>
            <div class="thumbs">
                <?php foreach ($images as $i => $file): ?>
                    <img src="<?= image_url($file) ?>" alt="" class="<?= $i === 0 ? 'selected' : '' ?>">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="auction-info">
        <h1><?= e($auction['title']) ?></h1>

        <div class="bid-box">
            <span class="price-label"><?= $auction['current_bid'] ? 'Current bid' : 'Starting price' ?></span>
            <span class="price big"><?= format_price($auction['current_bid'] ?? $auction['starting_price']) ?></span>

            <dl class="details">
                <?php if ($auction['current_bid']): ?>
                    <dt>Starting price</dt>
                    <dd><?= format_price($auction['starting_price']) ?></dd>
                <?php endif; ?>
                <dt>Bids</dt>
                <dd><?= count($bids) ? '<a href="#bids">' . count($bids) . '</a>' : '0' ?></dd>
                <dt>Time left</dt>
                <dd>
                    <?php if ($hasEnded): ?>
                        Ended
                    <?php else: ?>
                        <span class="time-left" data-seconds-left="<?= $secondsLeft ?>"><?= time_left($secondsLeft) ?></span>
                    <?php endif; ?>
                </dd>
                <dt><?= $hasEnded ? 'Ended on' : 'Ends on' ?></dt>
                <dd><?= format_datetime($auction['end_time']) ?></dd>
                <dt>Listed on</dt>
                <dd><?= format_datetime($auction['start_time']) ?></dd>
            </dl>

            <?php if ($hasEnded): ?>
                <p class="note">This auction has ended.</p>
            <?php elseif ($isOwner): ?>
                <p class="note">This is your auction.</p>
            <?php elseif (!is_logged_in()): ?>
                <p class="note">
                    <a href="<?= BASE_URL ?>/login.php?redirect=<?= urlencode('/auction.php?id=' . $auction['id']) ?>">Log in</a>
                    to place a bid.
                </p>
            <?php elseif (is_admin()): ?>
                <p class="note">Admins can't place bids.</p>
            <?php elseif ($highestBidderId === $userId): ?>
                <p class="note winning">You are the highest bidder.</p>
            <?php else: ?>
                <form method="post" action="<?= BASE_URL ?>/bid.php" class="bid-form"
                      onsubmit="return confirm('Place a bid of Rs. ' + Number(this.amount.value).toLocaleString('en-IN') + '? Bids can\'t be cancelled.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                    <label for="amount">Your bid (Rs.)</label>
                    <div class="bid-row">
                        <input type="number" id="amount" name="amount" min="<?= (int) $minimumBid ?>" step="1"
                               value="<?= (int) $minimumBid ?>" required>
                        <button type="submit" class="btn">Place bid</button>
                    </div>
                    <p class="hint">Enter <?= format_price($minimumBid) ?> or more.</p>
                </form>
            <?php endif; ?>
        </div>

        <div class="seller">
            <span class="price-label">Seller</span>
            <strong><?= e($auction['seller_name']) ?></strong>
            <span class="muted">Member since <?= date('M Y', strtotime($auction['seller_since'])) ?></span>
        </div>
    </div>
</div>

<section class="description">
    <h2>Description</h2>
    <p><?= nl2br(e($auction['description'])) ?></p>
</section>

<section class="description" id="bids">
    <h2>Bid history</h2>
    <?php if (empty($bids)): ?>
        <p class="muted">No bids yet. Be the first to bid.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Bidder</th>
                        <th>Amount</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bids as $b): ?>
                        <tr>
                            <td>
                                <?php if ((int) $b['user_id'] === $userId): ?>
                                    <strong>You</strong>
                                <?php elseif (is_admin()): ?>
                                    <?= e($b['name']) ?>
                                <?php else: ?>
                                    <?= e(mask_name($b['name'])) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= format_price($b['amount']) ?></td>
                            <td><?= format_datetime($b['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
// Clicking a thumbnail shows it as the main photo
document.querySelectorAll('.thumbs img').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
        document.getElementById('main-photo').src = thumb.src;
        document.querySelector('.thumbs .selected').classList.remove('selected');
        thumb.classList.add('selected');
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
