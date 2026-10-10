<?php
require __DIR__ . '/../includes/init.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    // Delete a category
    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT name FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $category = $stmt->fetch();

        $stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$id]);
        $productCount = (int) $stmt->fetchColumn();

        if ($category && $productCount > 0) {
            set_flash('error', 'Can\'t delete "' . $category['name'] . '" because ' . $productCount . ' item(s) are listed in it.');
        } elseif ($category) {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            set_flash('success', 'Category "' . $category['name'] . '" deleted.');
        }
        redirect('/admin/categories.php');
    }

    // Add a new category, or update an existing one when an id is sent
    if ($action === 'save') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Name must be between 2 and 100 characters.';
        }
        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Description can be at most 255 characters.';
        }

        // Names must be unique (the check ignores upper/lower case)
        if (empty($errors)) {
            $stmt = db()->prepare('SELECT id FROM categories WHERE name = ? AND id != ?');
            $stmt->execute([$name, $id]);
            if ($stmt->fetch()) {
                $errors['name'] = 'A category with this name already exists.';
            }
        }

        if (empty($errors)) {
            $description = $description === '' ? null : $description;

            if ($id > 0) {
                db()->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?')
                    ->execute([$name, $description, $id]);
                set_flash('success', 'Category updated.');
            } else {
                db()->prepare('INSERT INTO categories (name, description) VALUES (?, ?)')
                    ->execute([$name, $description]);
                set_flash('success', 'Category "' . $name . '" added.');
            }
            redirect('/admin/categories.php');
        }
    }
}

// Editing? Load that category into the form.
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT id, name, description FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Values shown in the form: what was just typed (after an error), else the category being edited
$formId          = (int) ($_POST['id'] ?? $editing['id'] ?? 0);
$formName        = $_POST['name'] ?? $editing['name'] ?? '';
$formDescription = $_POST['description'] ?? $editing['description'] ?? '';

$categories = db()->query('SELECT id, name, description, created_at FROM categories ORDER BY name')->fetchAll();

$pageTitle = 'Categories';
require __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-content">
        <h1>Categories <span class="count"><?= count($categories) ?></span></h1>

        <form method="post" action="<?= BASE_URL ?>/admin/categories.php" class="panel">
            <h2><?= $formId ? 'Edit category' : 'Add a category' ?></h2>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $formId ?>">

            <div class="form-row">
                <div>
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?= e($formName) ?>" maxlength="100" required>
                    <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="description">Description <span class="muted">(optional)</span></label>
                    <input type="text" id="description" name="description" value="<?= e($formDescription) ?>" maxlength="255">
                    <?php if (isset($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-small"><?= $formId ? 'Save changes' : 'Add category' ?></button>
                <?php if ($formId): ?>
                    <a href="<?= BASE_URL ?>/admin/categories.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($categories)): ?>
            <p class="empty">No categories yet. Add the first one above.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Added</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr class="<?= $c['id'] === $formId ? 'editing' : '' ?>">
                                <td><?= e($c['name']) ?></td>
                                <td class="wrap"><?= $c['description'] ? e($c['description']) : '<span class="muted">-</span>' ?></td>
                                <td><?= format_date($c['created_at']) ?></td>
                                <td class="actions">
                                    <a href="?edit=<?= $c['id'] ?>" class="btn-outline">Edit</a>
                                    <form method="post" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn-outline danger"
                                                onclick="return confirm(<?= e(json_encode('Delete the category "' . $c['name'] . '"?')) ?>)">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
