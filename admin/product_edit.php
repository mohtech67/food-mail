
<?php

// Verify administrator authentication first.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Edit Product';

// Validate product ID.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $_SESSION['product_error'] = 'Invalid product selected.';
    header('Location: products.php');
    exit;
}

// Retrieve product.
$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$product = $stmt->fetch();

if (!$product) {
    $_SESSION['product_error'] = 'The selected product does not exist.';
    header('Location: products.php');
    exit;
}

// Retrieve active categories and the product's existing category.
$categoryStmt = $pdo->prepare("
    SELECT id, name
    FROM categories
    WHERE is_active = 1 OR id = ?
    ORDER BY name ASC
");

$categoryStmt->execute([(int) $product['category_id']]);

$categories = $categoryStmt->fetchAll();

// Retrieve any error message.
$error = $_SESSION['product_error'] ?? '';
unset($_SESSION['product_error']);

// Prepare current image URL.
$image = trim((string) ($product['image'] ?? ''));
$imageUrl = '';

if ($image !== '') {
    if (preg_match('~^https?://~i', $image)) {
        $imageUrl = $image;
    } else {
        $imageUrl = '../' . ltrim($image, '/');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-heading">

    <div>
        <div class="welcome-label">INVENTORY MANAGEMENT</div>

        <h1>Edit Product</h1>

        <p>
            Update product information, pricing and stock.
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

        <input type="hidden" name="action" value="update">

        <input
            type="hidden"
            name="id"
            value="<?= (int) $product['id'] ?>"
        >

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
                    value="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
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
                ><?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

                <label for="image">
                    Replace Product Photograph
                </label>

                <input
                    type="file"
                    id="image"
                    name="image"
                    class="form-control mb-2"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >

                <small class="text-muted">
                    Leave empty to keep the existing photograph.
                    Maximum file size: 5 MB.
                </small>

                <?php if ($imageUrl !== ''): ?>

                    <div class="mt-3">

                        <p class="small fw-semibold">
                            Current Photograph
                        </p>

                        <img
                            src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Current product image"
                            style="width:130px;height:110px;object-fit:cover;border-radius:10px;"
                        >

                    </div>

                <?php endif; ?>

                <div class="mt-4">

                    <label class="d-flex align-items-center gap-2">

                        <input
                            type="checkbox"
                            name="is_available"
                            value="1"
                            <?= (int) $product['is_available'] === 1 ? 'checked' : '' ?>
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

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= (int) $category['id'] ?>"
                            <?= (int) $product['category_id'] === (int) $category['id'] ? 'selected' : '' ?>
                        >
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
                    value="<?= htmlspecialchars((string) $product['price'], ENT_QUOTES, 'UTF-8') ?>"
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
                    value="<?= (int) $product['stock_quantity'] ?>"
                    required
                >

            </div>

        </div>

        <hr class="my-4">

        <div class="d-flex flex-wrap gap-2">

            <button type="submit" class="btn-admin-primary">
                <i class="fa-solid fa-save"></i>
                Update Product
            </button>

            <a href="products.php" class="btn-admin-secondary">
                Cancel
            </a>

        </div>

    </form>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
