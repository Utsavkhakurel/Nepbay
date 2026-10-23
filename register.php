<?php
require __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // ---- Validation ----
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Name must be between 2 and 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Is the email already registered?
    if (empty($errors['email'])) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    // ---- Save the new user ----
    if (empty($errors)) {
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $email,
            $phone === '' ? null : $phone,
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        login_user((int) db()->lastInsertId());
        set_flash('success', 'Welcome to Nepbay, ' . $name . '! Your account has been created.');
        redirect('/index.php');
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1>Create an account</h1>
    <p class="muted">Join Nepbay to buy and sell through online auctions.</p>

    <form method="post" novalidate>
        <?= csrf_field() ?>

        <label for="name">Full name</label>
        <input type="text" id="name" name="name" value="<?= old('name') ?>" autocomplete="name" required maxlength="100">
        <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="email" required maxlength="150">
        <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>

        <label for="phone">Phone <span class="muted">(optional)</span></label>
        <input type="tel" id="phone" name="phone" value="<?= old('phone') ?>" maxlength="20" autocomplete="tel">
        <?php if (isset($errors['phone'])): ?><p class="field-error"><?= e($errors['phone']) ?></p><?php endif; ?>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>

        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        <?php if (isset($errors['confirm_password'])): ?><p class="field-error"><?= e($errors['confirm_password']) ?></p><?php endif; ?>

        <button type="submit" class="btn btn-full">Register</button>
    </form>

    <p class="auth-switch">Already have an account? <a href="<?= BASE_URL ?>/login.php">Log in</a></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
