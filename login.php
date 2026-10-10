<?php
require __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(is_admin() ? '/admin/index.php' : '/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, name, password_hash, role, status FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Same message for "no such email" and "wrong password",
    // so attackers can't find out which emails are registered.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'Incorrect email or password.';
    } elseif ($user['status'] === 'blocked') {
        $error = 'Your account has been blocked. Please contact the administrator.';
    } else {
        login_user((int) $user['id']);
        set_flash('success', 'Welcome back, ' . $user['name'] . '!');
        redirect($user['role'] === 'admin' ? '/admin/index.php' : '/index.php');
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1>Log in</h1>
    <p class="muted">Welcome back to Nepbay.</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn btn-full">Log in</button>
    </form>

    <p class="auth-switch">New to Nepbay? <a href="<?= BASE_URL ?>/register.php">Create an account</a></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
