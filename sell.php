<?php
require __DIR__ . '/includes/init.php';
require_login();

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If the upload is bigger than PHP's post_max_size, PHP drops the whole form
    if (empty($_POST)) {
        $errors['images'] = 'The photos are too large. Please choose smaller photos.';
    } else {
        verify_csrf();

        $title       = trim($_POST['title'] ?? '');
        $categoryId  = (int) ($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $price       = trim($_POST['starting_price'] ?? '');
        $increment   = trim($_POST['bid_increment'] ?? '');
        $type        = $_POST['duration_type'] ?? '';
        $length      = (int) ($_POST[$type === 'hourly' ? 'duration_hours' : 'duration_days'] ?? 0);
        $images      = uploaded_files('images');

        // ---- Validation ----
        $errors = listing_errors($title, $categoryId, $description, $price, $increment, $categories);
        if (!in_array($type, ['hourly', 'daily'])
            || !in_array($length, $type === 'hourly' ? HOURLY_DURATIONS : DAILY_DURATIONS)) {
            $errors['duration'] = 'Please choose how long the auction should run.';
        }
        $hours = $type === 'hourly' ? $length : $length * 24;
        $imageError = image_upload_error($images);
        if ($imageError) {
            $errors['images'] = $imageError;
        }

        // ---- Save ----
        if (empty($errors)) {
            $savedFiles = save_images($images);

            // Save the product, its photos and the auction together:
            // if any insert fails, none of them are kept.
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('INSERT INTO products (seller_id, category_id, title, description, ip_address) VALUES (?, ?, ?, ?, ?)')
                    ->execute([current_user()['id'], $categoryId, $title, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
                $productId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO product_images (product_id, file_name, sort_order) VALUES (?, ?, ?)');
                foreach ($savedFiles as $order => $fileName) {
                    $stmt->execute([$productId, $fileName, $order]);
                }

                $startTime = date('Y-m-d H:i:s');
                $endTime   = date('Y-m-d H:i:s', strtotime("+$hours hours"));
                $pdo->prepare('INSERT INTO auctions (product_id, starting_price, bid_increment, start_time, end_time) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$productId, $price, $increment, $startTime, $endTime]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                delete_images($savedFiles);
                throw $e;
            }

            set_flash('success', 'Your auction for "' . $title . '" is now live.');
            redirect('/my-auctions.php');
        }
    }
}

$pageTitle = 'Sell an item';
require __DIR__ . '/includes/header.php';
?>

<div class="form-page">
    <h1>Sell an item</h1>
    <p class="muted">Fill in the details below. Your auction starts as soon as you submit.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">Please fix the errors below.<?= isset($_FILES['images']) ? ' You will need to select your photos again.' : '' ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= old('title') ?>" maxlength="150"
               placeholder="e.g. Samsung Galaxy S21, 128GB, black" required>
        <?php if (isset($errors['title'])): ?><p class="field-error"><?= e($errors['title']) ?></p><?php endif; ?>

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            <option value="">Choose a category</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int) ($_POST['category_id'] ?? 0) === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category_id'])): ?><p class="field-error"><?= e($errors['category_id']) ?></p><?php endif; ?>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="6" maxlength="5000"
                  placeholder="Condition, age, what's included, reason for selling..." required><?= old('description') ?></textarea>
        <?php if (isset($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>

        <div class="form-row half">
            <div>
                <label for="starting_price">Starting price (Rs.)</label>
                <input type="number" id="starting_price" name="starting_price" value="<?= old('starting_price') ?>"
                       min="10" step="1" inputmode="numeric" required>
                <?php if (isset($errors['starting_price'])): ?><p class="field-error"><?= e($errors['starting_price']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="bid_increment">Bid increment (Rs.)</label>
                <input type="number" id="bid_increment" name="bid_increment" value="<?= e($_POST['bid_increment'] ?? '10') ?>"
                       min="1" step="1" inputmode="numeric" aria-describedby="increment-hint" required>
                <p class="hint" id="increment-hint">Each new bid must be at least this much higher.</p>
                <?php if (isset($errors['bid_increment'])): ?><p class="field-error"><?= e($errors['bid_increment']) ?></p><?php endif; ?>
            </div>
        </div>

        <?php $type = ($_POST['duration_type'] ?? 'daily') === 'hourly' ? 'hourly' : 'daily'; ?>
        <div class="form-row half">
            <div>
                <span class="label">Auction type</span>
                <div class="toggle">
                    <label><input type="radio" name="duration_type" value="hourly" <?= $type === 'hourly' ? 'checked' : '' ?>> Hourly</label>
                    <label><input type="radio" name="duration_type" value="daily" <?= $type === 'daily' ? 'checked' : '' ?>> Daily</label>
                </div>
            </div>
            <div>
                <label for="duration_hours" data-type="hourly" <?= $type === 'hourly' ? '' : 'hidden' ?>>Auction length</label>
                <select id="duration_hours" name="duration_hours" data-type="hourly" <?= $type === 'hourly' ? '' : 'hidden' ?>>
                    <?php foreach (HOURLY_DURATIONS as $h): ?>
                        <option value="<?= $h ?>" <?= (int) ($_POST['duration_hours'] ?? 6) === $h ? 'selected' : '' ?>><?= $h ?> <?= $h === 1 ? 'hour' : 'hours' ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="duration_days" data-type="daily" <?= $type === 'daily' ? '' : 'hidden' ?>>Auction length</label>
                <select id="duration_days" name="duration_days" data-type="daily" <?= $type === 'daily' ? '' : 'hidden' ?>>
                    <?php foreach (DAILY_DURATIONS as $d): ?>
                        <option value="<?= $d ?>" <?= (int) ($_POST['duration_days'] ?? 7) === $d ? 'selected' : '' ?>><?= $d ?> <?= $d === 1 ? 'day' : 'days' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="hint" id="ends-preview"></p>
        <?php if (isset($errors['duration'])): ?><p class="field-error"><?= e($errors['duration']) ?></p><?php endif; ?>

        <label for="images">Photos</label>
        <p class="hint">Up to <?= MAX_IMAGES ?> photos, JPG, PNG or WEBP, 2 MB each. The first photo is used as the cover.</p>
        <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
        <div id="preview" class="preview"></div>
        <?php if (isset($errors['images'])): ?><p class="field-error"><?= e($errors['images']) ?></p><?php endif; ?>

        <button type="submit" class="btn">Start auction</button>
    </form>
</div>

<script>
// Show only the length dropdown for the chosen auction type,
// and tell the seller when the auction will end
const typeInputs = document.querySelectorAll('input[name="duration_type"]');
const endsPreview = document.getElementById('ends-preview');

function updateDuration() {
    const type = document.querySelector('input[name="duration_type"]:checked').value;
    document.querySelectorAll('[data-type]').forEach(function (el) {
        el.hidden = el.dataset.type !== type;
    });

    const length = Number(document.getElementById(type === 'hourly' ? 'duration_hours' : 'duration_days').value);
    const end = new Date(Date.now() + length * (type === 'hourly' ? 3600000 : 86400000));
    const time = end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    const date = end.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
    const sameDay = end.toDateString() === new Date().toDateString();

    endsPreview.textContent = 'Starts as soon as you submit, ends '
        + (type === 'hourly' && sameDay ? 'today at ' + time : date + ' at ' + time) + '.';
}

typeInputs.forEach(function (input) { input.addEventListener('change', updateDuration); });
document.getElementById('duration_hours').addEventListener('change', updateDuration);
document.getElementById('duration_days').addEventListener('change', updateDuration);
updateDuration();

// Show small previews of the chosen photos
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
