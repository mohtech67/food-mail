
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEdit = $id !== false && $id !== null && $id > 0;

$pageTitle = $isEdit ? 'Edit Category' : 'Add Category';

$category = [
    'name' => '',
    'description' => '',
    'is_active' => 1
];

if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT id, name, description, is_active
        FROM categories
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        $_SESSION['category_error'] = 'The selected category was not found.';
        header('Location: categories.php');
        exit;
    }

    $category = $record;
}

$error = $_SESSION['category_error'] ?? '';
unset($_SESSION['category_error']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-heading">

    <div>
        <div class="welcome-label">INVENTORY ORGANIZATION</div>

        <h1>
            <?= $isEdit ? 'Edit Category' : 'Add New Category' ?>
        </h1>

        <p>
            <?= $isEdit
                ? 'Update the category information.'
                : 'Create a new category for your products.' ?>
        </p>
    </div>

    <a href="categories.php" class="btn-admin-secondary">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Categories
    </a>

</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="admin-form-card">

    <form action="category_save.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <input
            type="hidden"
            name="action"
            value="<?= $isEdit ? 'update' : 'create' ?>"
        >

        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php endif; ?>

        <div class="row">

            <div class="col-md-8">

                <label for="name">Category Name *</label>

                <input
                    type="text"
                    name="name"
                    id="name"
                    class="form-control mb-4"
                    maxlength="100"
                    placeholder="Example: Meals"
                    value="<?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>"
                    required
                >

                <label for="description">Category Description</label>

                <textarea
                    name="description"
                    id="description"
                    class="form-control mb-4"
                    rows="5"
                    maxlength="1000"
                    placeholder="Describe the products in this category..."
                ><?= htmlspecialchars($category['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

                <label class="d-flex align-items-center gap-2 mb-4">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= (int)$category['is_active'] === 1 ? 'checked' : '' ?>
                    >
                    Make this category active
                </label>

                <div class="d-flex flex-wrap gap-2">

                    <button type="submit" class="btn-admin-primary">
                        <i class="fa-solid fa-save"></i>

                        <?= $isEdit ? 'Update Category' : 'Save Category' ?>
                    </button>

                    <a href="categories.php" class="btn-admin-secondary">
                        Cancel
                    </a>

                </div>

            </div>

            <div class="col-md-4">

                <div class="operations-card">
                    <div class="stat-icon green mb-3">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>

                    <h5>Category Information</h5>

                    <p class="text-muted small">
                        Categories help customers find products more easily.
                        Use clear and recognizable names.
                    </p>

                    <hr>

                    <p class="small mb-1">
                        <strong>Examples</strong>
                    </p>

                    <ul class="small text-muted">
                        <li>Meals</li>
                        <li>Drinking Water</li>
                        <li>Snacks</li>
                        <li>Beverages</li>
                    </ul>
                </div>

            </div>

        </div>

    </form>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
