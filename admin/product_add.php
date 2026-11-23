
<?php

// Verify administrator authentication first.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Add Product';

// Load active categories.
$categories = $pdo->query("
    SELECT id, name
    FROM categories
    WHERE is_active = 1
    ORDER BY name ASC
")->fetchAll();

// Display any previous error.
$error = $_SESSION['product_error'] ?? '';
unset($_SESSION['product_error']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-heading">

    <div>
        <div class="welcome-label">INVENTORY MANAGEMENT</div>

        <h1>Add New Product</h1>

        <p>
            Enter the details of the product you want to sell.
        </p>
    </div>

    <a href="products.php" class="btn-admin-secondary">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Products
    </a>

</div>

<?php if ($error): ?>

    <div class="alert alert-danger">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>

<?php endif; ?>

<?php if (!$categories): ?>

    <div class="alert alert-warning">
        No active categories exist. Create or activate a category before adding products.
    </div>

<?php else: ?>

    <div class="admin-form-card">

        <form
            action="product_save.php"
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <input type="hidden" name="action" value="create">

            <div class="row g-4">

                <div class="col-md-8">

                    <label for="name">
                        Product Name *
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control mb-3"
                        maxlength="150"
                        placeholder="Example: Fresh Chicken Pilau"
                        required
                    >

                    <label for="description">
                        Product Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        class="form-control mb-3"
                        rows="5"
                        maxlength="5000"
                        placeholder="Describe the product, ingredients, size or quantity..."
                    ></textarea>

                    <label for="image">
                        Product Photograph
                    </label>

                    <input
                        type="file"
                        id="image"
                        name="image"
                        class="form-control mb-2"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                    <small class="text-muted">
                        JPG, PNG or WebP. Maximum file size: 5 MB.
                    </small>

                    <div class="mt-4">

                        <label class="d-flex align-items-center gap-2">

                            <input
                                type="checkbox"
                                name="is_available"
                                value="1"
                                checked
                            >

                            Make this product available for customers

                        </label>

                    </div>

                </div>

                <div class="col-md-4">

                    <label for="category_id">
                        Product Category *
                    </label>

                    <select
                        id="category_id"
                        name="category_id"
                        class="form-select mb-3"
                        required
                    >

                        <option value="">Select category</option>

                        <?php foreach ($categories as $category): ?>

                            <option value="<?= (int) $category['id'] ?>">
                                <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <label for="price">
                        Selling Price (KSh) *
                    </label>

                    <input
                        type="number"
                        id="price"
                        name="price"
                        class="form-control mb-3"
                        min="0.01"
                        max="99999999.99"
                        step="0.01"
                        placeholder="0.00"
                        required
                    >

                    <label for="stock_quantity">
                        Available Stock *
                    </label>

                    <input
                        type="number"
                        id="stock_quantity"
                        name="stock_quantity"
                        class="form-control mb-3"
                        min="0"
                        max="1000000"
                        step="1"
                        value="0"
                        required
                    >

                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex flex-wrap gap-2">

                <button type="submit" class="btn-admin-primary">
                    <i class="fa-solid fa-save"></i>
                    Save Product
                </button>

                <a href="products.php" class="btn-admin-secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
