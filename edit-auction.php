<?php
require __DIR__ . '/includes/init.php';
require_login();

$auctionId = (int) ($_GET['id'] ?? 0);

// Only the seller can open this page
$stmt = db()->prepare("
    SELECT a.id, a.product_id, a.starting_price, a.current_bid, a.status, a.end_time,
           p.title, p.description, p.category_id
    FROM auctions a
    JOIN products p ON p.id = a.product_id
    WHERE a.id = ? AND p.seller_id = ?
");
$stmt->execute([$auctionId, current_user()['id']]);
$auction = $stmt->fetch();

if (!$auction) {
    set_flash('error', 'Auction not found.');
    redirect('/my-auctions.php');
}

// A listing can only be changed while it is live and nobody has bid on it
function can_edit(array $auction): bool
{
    return $auction['status'] === 'active'
        && $auction['current_bid'] === null
        && strtotime($auction['end_time']) > time();
}

if (!can_edit($auction)) {
    set_flash('error', 'This listing can no longer be edited because it has bids or has ended.');
    redirect('/my-auctions.php');
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$stmt = db()->prepare('SELECT id, file_name, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order');
$stmt->execute([$auction['product_id']]);
$existingImages = $stmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST)) {
        $errors['images'] = 'The photos are too large. Please choose smaller photos.';
    } else {
        verify_csrf();

        $title       = trim($_POST['title'] ?? '');
        $categoryId  = (int) ($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $price       = trim($_POST['starting_price'] ?? '');
        $newImages   = uploaded_files('images');
        $removeIds   = array_map('intval', $_POST['remove_images'] ?? []);

        // Split the current photos into the ones being kept and the ones being removed
        $keptImages = $removedImages = [];
        foreach ($existingImages as $image) {
            if (in_array($image['id'], $removeIds)) {
                $removedImages[] = $image;
            } else {
                $keptImages[] = $image;
            }
        }

        $errors = listing_errors($title, $categoryId, $description, $price, $categories);
        $imageError = image_upload_error($newImages, count($keptImages));
        if ($imageError) {
            $errors['images'] = $imageError;
        }

        if (empty($errors)) {
            $savedFiles = save_images($newImages);

            $pdo = db();
            $pdo->beginTransaction();

            // Lock the auction and check again that nobody bid while the seller was editing
            $stmt = $pdo->prepare('SELECT status, current_bid, end_time FROM auctions WHERE id = ? FOR UPDATE');
            $stmt->execute([$auctionId]);
            if (!can_edit($stmt->fetch())) {
                $pdo->rollBack();
                delete_images($savedFiles);
                set_flash('error', 'Someone placed a bid while you were editing, so your changes were not saved.');
                redirect('/auction.php?id=' . $auctionId);
            }

            $pdo->prepare('UPDATE products SET title = ?, category_id = ?, description = ? WHERE id = ?')
                ->execute([$title, $categoryId, $description, $auction['product_id']]);
            $pdo->prepare('UPDATE auctions SET starting_price = ? WHERE id = ?')
                ->execute([$price, $auctionId]);

            $deleteStmt = $pdo->prepare('DELETE FROM product_images WHERE id = ?');
            foreach ($removedImages as $image) {
                $deleteStmt->execute([$image['id']]);
            }

            // New photos go after the existing ones
            $nextOrder = $existingImages ? max(array_column($existingImages, 'sort_order')) + 1 : 0;
            $insertStmt = $pdo->prepare('INSERT INTO product_images (product_id, file_name, sort_order) VALUES (?, ?, ?)');
            foreach ($savedFiles as $fileName) {
                $insertStmt->execute([$auction['product_id'], $fileName, $nextOrder++]);
            }

            $pdo->commit();
            delete_images(array_column($removedImages, 'file_name'));

            set_flash('success', 'Your listing has been updated.');
            redirect('/auction.php?id=' . $auctionId);
        }
    }
}

// Form values: what was just typed (after an error), otherwise the saved listing
$isPost      = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST);
$title       = $isPost ? ($_POST['title'] ?? '')                : $auction['title'];
$categoryId  = $isPost ? (int) ($_POST['category_id'] ?? 0)     : $auction['category_id'];
$description = $isPost ? ($_POST['description'] ?? '')          : $auction['description'];
$price       = $isPost ? ($_POST['starting_price'] ?? '')       : (int) $auction['starting_price'];
$removeIds   = $isPost ? array_map('intval', $_POST['remove_images'] ?? []) : [];

$pageTitle = 'Edit listing';
require __DIR__ . '/includes/header.php';
?>

<div class="form-page">
    <h1>Edit listing</h1>
    <p class="muted">You can change this listing until someone places a bid. The end time stays the same.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">Please fix the errors below.</div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($title) ?>" maxlength="150" required>
        <?php if (isset($errors['title'])): ?><p class="field-error"><?= e($errors['title']) ?></p><?php endif; ?>

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $categoryId === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category_id'])): ?><p class="field-error"><?= e($errors['category_id']) ?></p><?php endif; ?>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="6" maxlength="5000" required><?= e($description) ?></textarea>
        <?php if (isset($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>

        <div class="form-row half">
            <div>
                <label for="starting_price">Starting price (Rs.)</label>
                <input type="number" id="starting_price" name="starting_price" value="<?= e((string) $price) ?>" min="10" step="1" required>
                <?php if (isset($errors['starting_price'])): ?><p class="field-error"><?= e($errors['starting_price']) ?></p><?php endif; ?>
            </div>
            <div>
                <label>Ends on</label>
                <input type="text" value="<?= format_datetime($auction['end_time']) ?>" disabled>
            </div>
        </div>

        <label>Current photos</label>
        <p class="hint">Tick the photos you want to remove.</p>
        <div class="current-photos">
            <?php foreach ($existingImages as $image): ?>
                <label class="photo-option">
                    <img src="<?= image_url($image['file_name']) ?>" alt="">
                    <span>
                        <input type="checkbox" name="remove_images[]" value="<?= $image['id'] ?>"
                            <?= in_array($image['id'], $removeIds) ? 'checked' : '' ?>>
                        Remove
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <label for="images">Add photos</label>
        <p class="hint">Up to <?= MAX_IMAGES ?> photos in total, JPG, PNG or WEBP, 2 MB each.</p>
        <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
        <div id="preview" class="preview"></div>
        <?php if (isset($errors['images'])): ?><p class="field-error"><?= e($errors['images']) ?></p><?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn">Save changes</button>
            <a href="<?= BASE_URL ?>/my-auctions.php">Cancel</a>
        </div>
    </form>
</div>

<script>
document.getElementById('images').addEventListener('change', function () {
    const preview = document.getElementById('preview');
    preview.innerHTML = '';
    for (const file of this.files) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        preview.appendChild(img);
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
