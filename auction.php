<?php
require __DIR__ . '/includes/init.php';

$stmt = db()->prepare("
    SELECT a.id, a.starting_price, a.bid_increment, a.current_bid, a.start_time, a.end_time, a.status, a.winner_id,
           TIMESTAMPDIFF(SECOND, NOW(), a.end_time) AS seconds_left,
           p.id AS product_id, p.title, p.description, p.seller_id,
           c.id AS category_id, c.name AS category,
           u.name AS seller_name, u.status AS seller_status, u.email AS seller_email, u.phone AS seller_phone, u.created_at AS seller_since,
           w.name AS winner_name, w.email AS winner_email, w.phone AS winner_phone
    FROM auctions a
    JOIN products p   ON p.id = a.product_id
    JOIN categories c ON c.id = p.category_id
    JOIN users u      ON u.id = p.seller_id
    LEFT JOIN users w ON w.id = a.winner_id
    WHERE a.id = ?
");
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$auction = $stmt->fetch();

// Removed auctions are only visible to the admin
if (!$auction || ($auction['status'] === 'removed' && !is_admin())) {
    http_response_code(404);
    $pageTitle = 'Auction not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty"><p>This auction doesn\'t exist or has been removed.</p><a href="' . BASE_URL . '/index.php">Back to live auctions</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = db()->prepare('SELECT file_name FROM product_images WHERE product_id = ? ORDER BY sort_order');
$stmt->execute([$auction['product_id']]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Bid history, highest first (bids from blocked users don't count)
$stmt = db()->prepare("
    SELECT b.user_id, b.amount, b.created_at, u.name
    FROM bids b
    JOIN users u ON u.id = b.user_id
    WHERE b.auction_id = ? AND u.status = 'active'
    ORDER BY b.amount DESC
");
$stmt->execute([$auction['id']]);
$bids = $stmt->fetchAll();

$secondsLeft     = (int) $auction['seconds_left'];
$hasEnded        = $auction['status'] !== 'active' || $secondsLeft <= 0;
$userId          = is_logged_in() ? current_user()['id'] : 0;
$isOwner         = $userId === (int) $auction['seller_id'];
$highestBidderId = $bids ? (int) $bids[0]['user_id'] : 0;
$winnerId        = (int) $auction['winner_id'];
$sellerBlocked   = $auction['seller_status'] === 'blocked';
$minimumBid      = minimum_bid($auction['starting_price'], $auction['current_bid'], $auction['bid_increment']);

// This user's highest bid, if they have bid here (bids are sorted highest first)
$myBid = null;
foreach ($bids as $b) {
    if ((int) $b['user_id'] === $userId) {
        $myBid = $b['amount'];
        break;
    }
}

$pageTitle = $auction['title'];
require __DIR__ . '/includes/header.php';
?>

<a href="<?= BASE_URL ?>/index.php" class="back-link">&larr; Back to auctions</a>

<?php if ($auction['status'] === 'removed'): ?>
    <div class="alert alert-error">This auction was removed by an admin and is hidden from users.</div>
<?php endif; ?>

<div class="auction">
    <div class="gallery">
        <img src="<?= image_url($images[0] ?? null) ?>" alt="<?= e($auction['title']) ?>" id="main-photo" class="main-photo">
        <?php if (count($images) > 1): ?>
            <div class="thumbs">
                <?php foreach ($images as $i => $file): ?>
                    <button type="button" class="<?= $i === 0 ? 'selected' : '' ?>" aria-label="Show photo <?= $i + 1 ?>">
                        <img src="<?= image_url($file) ?>" alt="">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="auction-info">
        <a href="<?= BASE_URL ?>/index.php?category=<?= $auction['category_id'] ?>" class="category-link"><?= e($auction['category']) ?></a>
        <h1><?= e($auction['title']) ?></h1>

        <div class="bid-box">
            <div class="auction-status">
                <?php if ($auction['status'] === 'removed'): ?>
                    <span class="badge badge-removed">Removed</span>
                <?php elseif ($hasEnded): ?>
                    <span class="badge badge-ended">Ended</span>
                    <span class="muted"><?= format_datetime($auction['end_time']) ?></span>
                <?php else: ?>
                    <span class="badge badge-live">Live</span>
                    <span class="ends">
                        <span class="time-left" data-seconds-left="<?= $secondsLeft ?>"><?= time_left($secondsLeft) ?></span>
                        <span class="muted"><?= ends_label($auction['start_time'], $auction['end_time']) ?></span>
                    </span>
                <?php endif; ?>
            </div>

            <span class="price-label">
                <?php if ($hasEnded && $auction['current_bid']): ?>
                    Winning bid
                <?php elseif ($auction['current_bid']): ?>
                    Current bid
                <?php else: ?>
                    Starting price
                <?php endif; ?>
            </span>
            <span class="price big"><?= format_price($auction['current_bid'] ?? $auction['starting_price']) ?></span>

            <?php if ($auction['status'] === 'removed'): ?>
                <?php // the red alert above already explains this ?>
            <?php elseif ($hasEnded && !$winnerId): ?>
                <p class="note">This auction ended with no bids.</p>
            <?php elseif ($hasEnded && $winnerId === $userId): ?>
                <div class="result">
                    <strong>You won this auction.</strong>
                    <p>Contact the seller to arrange payment and pickup:</p>
                    <p class="contact">
                        <?= e($auction['seller_name']) ?><br>
                        <a href="mailto:<?= e($auction['seller_email']) ?>"><?= e($auction['seller_email']) ?></a>
                        <?php if ($auction['seller_phone']): ?><br><?= e($auction['seller_phone']) ?><?php endif; ?>
                    </p>
                </div>
            <?php elseif ($hasEnded && $isOwner): ?>
                <div class="result">
                    <strong>Your item sold for <?= format_price($auction['current_bid']) ?>.</strong>
                    <p>Contact the buyer to arrange payment and pickup:</p>
                    <p class="contact">
                        <?= e($auction['winner_name']) ?><br>
                        <a href="mailto:<?= e($auction['winner_email']) ?>"><?= e($auction['winner_email']) ?></a>
                        <?php if ($auction['winner_phone']): ?><br><?= e($auction['winner_phone']) ?><?php endif; ?>
                    </p>
                </div>
            <?php elseif ($hasEnded && is_admin()): ?>
                <p class="note">Won by <?= e($auction['winner_name']) ?> (<?= e($auction['winner_email']) ?>).</p>
            <?php elseif ($hasEnded): ?>
                <p class="note">This auction has ended.</p>
            <?php elseif ($sellerBlocked): ?>
                <p class="note">Bidding is closed because the seller's account has been suspended.</p>
            <?php elseif ($isOwner): ?>
                <p class="note">
                    This is your auction.
                    <?php if (!$bids): ?>
                        <a href="<?= BASE_URL ?>/edit-auction.php?id=<?= $auction['id'] ?>">Edit listing</a>
                    <?php endif; ?>
                </p>
            <?php elseif (!is_logged_in()): ?>
                <a href="<?= BASE_URL ?>/login.php?redirect=<?= urlencode('/auction.php?id=' . $auction['id']) ?>" class="btn btn-full">Log in to bid</a>
            <?php elseif (is_admin()): ?>
                <p class="note">Admins can't place bids.</p>
            <?php elseif ($highestBidderId === $userId): ?>
                <p class="note winning">You are the highest bidder.</p>
            <?php else: ?>
                <form method="post" action="<?= BASE_URL ?>/bid.php" class="bid-form"
                      onsubmit="return confirm('Place a bid of Rs. ' + Number(this.amount.value).toLocaleString('en-IN') + '? Bids can\'t be cancelled.')">
                    <?php if ($myBid !== null): ?>
                        <p class="note outbid">You've been outbid. Your highest bid was <?= format_price($myBid) ?>.</p>
                    <?php endif; ?>
                    <?= csrf_field() ?>
                    <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                    <label for="amount">Your bid (Rs.)</label>
                    <div class="bid-row">
                        <input type="number" id="amount" name="amount" min="<?= (int) $minimumBid ?>" step="1"
                               value="<?= (int) $minimumBid ?>" inputmode="numeric" aria-describedby="bid-hint" required>
                        <button type="submit" class="btn">Place bid</button>
                    </div>
                    <p class="hint" id="bid-hint">Enter <?= format_price($minimumBid) ?> or more.</p>
                </form>
            <?php endif; ?>

            <dl class="details">
                <?php if ($auction['current_bid']): ?>
                    <dt>Starting price</dt>
                    <dd><?= format_price($auction['starting_price']) ?></dd>
                <?php endif; ?>
                <dt>Bid increment</dt>
                <dd><?= format_price($auction['bid_increment']) ?></dd>
                <dt>Bids</dt>
                <dd><?= count($bids) ? '<a href="#bids">' . count($bids) . '</a>' : '0' ?></dd>
                <dt>Auction type</dt>
                <dd><?= is_hourly($auction['start_time'], $auction['end_time']) ? 'Hourly' : 'Daily' ?>, <?= duration_label($auction['start_time'], $auction['end_time']) ?></dd>
                <dt><?= $hasEnded ? 'Ended on' : 'Ends on' ?></dt>
                <dd><?= format_datetime($auction['end_time']) ?></dd>
                <dt>Listed on</dt>
                <dd><?= format_datetime($auction['start_time']) ?></dd>
            </dl>
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
        <p class="muted"><?= $hasEnded ? 'No bids were placed.' : 'No bids yet.' ?></p>
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
document.querySelectorAll('.thumbs button').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
        document.getElementById('main-photo').src = thumb.querySelector('img').src;
        document.querySelector('.thumbs .selected').classList.remove('selected');
        thumb.classList.add('selected');
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
