<?php
require __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$detailErrors   = [];
$passwordErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ---- Update name, phone and address ----
    if ($action === 'details') {
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $detailErrors['name'] = 'Name must be between 2 and 100 characters.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $phone)) {
            $detailErrors['phone'] = 'Please enter a valid phone number.';
        }
        if (mb_strlen($address) > 255) {
            $detailErrors['address'] = 'Address can be at most 255 characters.';
        }

        if (empty($detailErrors)) {
            db()->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?')
                ->execute([$name, $phone ?: null, $address ?: null, $user['id']]);
            set_flash('success', 'Your details have been updated.');
            redirect('/profile.php');
        }
    }

    // ---- Change password ----
    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);

        if (!password_verify($current, $stmt->fetchColumn())) {
            $passwordErrors['current_password'] = 'Your current password is incorrect.';
        }
        if (strlen($new) < 8) {
            $passwordErrors['new_password'] = 'New password must be at least 8 characters.';
        } elseif ($new === $current) {
            $passwordErrors['new_password'] = 'New password must be different from the current one.';
        }
        if ($new !== $confirm) {
            $passwordErrors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($passwordErrors)) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            set_flash('success', 'Your password has been changed.');
            redirect('/profile.php');
        }
    }
}

// Show what was just typed after an error, otherwise the saved values
$isDetailsForm = ($_POST['action'] ?? '') === 'details';
$name    = $isDetailsForm ? $_POST['name']    : $user['name'];
$phone   = $isDetailsForm ? $_POST['phone']   : $user['phone'];
$address = $isDetailsForm ? $_POST['address'] : $user['address'];

$stmt = db()->prepare('SELECT created_at FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$memberSince = $stmt->fetchColumn();

$pageTitle = 'My profile';
require __DIR__ . '/includes/header.php';
?>

<div class="form-page">
    <h1>My profile</h1>
    <p class="muted">
        <?= e($user['email']) ?> &middot; Member since <?= format_date($memberSince) ?>
        <?= $user['role'] === 'admin' ? '&middot; Administrator' : '' ?>
    </p>

    <form method="post" novalidate>
        <h2>Your details</h2>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="details">

        <label for="name">Full name</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>" maxlength="100" autocomplete="name" required>
        <?php if (isset($detailErrors['name'])): ?><p class="field-error"><?= e($detailErrors['name']) ?></p><?php endif; ?>

        <label for="email">Email</label>
        <input type="email" id="email" value="<?= e($user['email']) ?>" disabled>
        <p class="hint">Your email is used to log in and can't be changed.</p>

        <label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>" maxlength="20" autocomplete="tel">
        <?php if (isset($detailErrors['phone'])): ?><p class="field-error"><?= e($detailErrors['phone']) ?></p><?php endif; ?>

        <label for="address">Address</label>
        <input type="text" id="address" name="address" value="<?= e($address) ?>" maxlength="255" placeholder="e.g. Baneshwor, Kathmandu">
        <?php if (isset($detailErrors['address'])): ?><p class="field-error"><?= e($detailErrors['address']) ?></p><?php endif; ?>

        <p class="hint">Your name, email and phone are shared with the other person when you win or sell an auction.</p>

        <button type="submit" class="btn">Save details</button>
    </form>

    <form method="post" novalidate>
        <h2>Change password</h2>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">

        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
        <?php if (isset($passwordErrors['current_password'])): ?><p class="field-error"><?= e($passwordErrors['current_password']) ?></p><?php endif; ?>

        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
        <?php if (isset($passwordErrors['new_password'])): ?><p class="field-error"><?= e($passwordErrors['new_password']) ?></p><?php endif; ?>

        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
        <?php if (isset($passwordErrors['confirm_password'])): ?><p class="field-error"><?= e($passwordErrors['confirm_password']) ?></p><?php endif; ?>

        <button type="submit" class="btn">Change password</button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
