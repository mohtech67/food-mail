
<?php

// Protect this page before accessing session data.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Products';

$search = trim($_GET['search'] ?? '');

$categoryFilter = filter_input(
    INPUT_GET,
    'category',
    FILTER_VALIDATE_INT
) ?: 0;

$statusFilter = $_GET['status'] ?? '';

$allowedStatuses = ['', 'available', 'unavailable'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

// Build product filters.
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE :search OR p.description LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($categoryFilter > 0) {
    $where[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryFilter;
}

if ($statusFilter === 'available') {
    $where[] = 'p.is_available = 1';
} elseif ($statusFilter === 'unavailable') {
    $where[] = 'p.is_available = 0';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count matching products.
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products p
    $whereSql
");

$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();

// Retrieve products.
$productsStmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.description,
        p.price,
        p.image,
        p.stock_quantity,
        p.is_available,
        p.category_id,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $whereSql
    ORDER BY p.created_at DESC, p.id DESC
");

$productsStmt->execute($params);
$products = $productsStmt->fetchAll();

// Retrieve categories.
$categories = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
")->fetchAll();

// Notifications.
$success = $_SESSION['product_success'] ?? '';
$error = $_SESSION['product_error'] ?? '';

unset(
    $_SESSION['product_success'],
    $_SESSION['product_error']
);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="welcome-label">INVENTORY MANAGEMENT</div>
        <h1>Products</h1>
        <p>Add, edit and manage Food Mail products and stock.</p>
    </div>

    <a href="product_add.php" class="btn-admin-primary">
        <i class="fa-solid fa-plus"></i>
        Add New Product
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon green">
                <i class="fa-solid fa-box"></i>
            </div>
        </div>

        <h3><?= number_format($totalProducts) ?></h3>
        <p>Products matching your filters</p>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon orange">
                <i class="fa-solid fa-tags"></i>
            </div>
        </div>

        <h3><?= count($categories) ?></h3>
        <p>Product categories</p>
    </div>

</div>

<div class="dashboard-card">

    <form method="GET" class="row g-3 align-items-end mb-4">

        <div class="col-md-5">
            <label class="form-label fw-semibold">
                Search products
            </label>

            <input
                type="search"
                name="search"
                class="form-control"
                placeholder="Search by product name..."
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>

        <div class="col-md-3">
            <label class="form-label fw-semibold">
                Category
            </label>

            <select name="category" class="form-select">
                <option value="">All categories</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category['id'] ?>"
                        <?= $categoryFilter === (int) $category['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-semibold">
                Availability
            </label>

            <select name="status" class="form-select">
                <option value="">All products</option>
                <option value="available" <?= $statusFilter === 'available' ? 'selected' : '' ?>>
                    Available
                </option>
                <option value="unavailable" <?= $statusFilter === 'unavailable' ? 'selected' : '' ?>>
                    Unavailable
                </option>
            </select>
        </div>

        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn-admin-primary">
                <i class="fa-solid fa-search"></i>
                Search
            </button>

            <a href="products.php" class="btn-admin-secondary">
                Reset
            </a>
        </div>

    </form>

    <div class="card-heading">
        <h3>Product Inventory</h3>

        <span class="text-muted small">
            <?= number_format($totalProducts) ?> products
        </span>
    </div>

    <div class="table-responsive">

        <table class="admin-table">

            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Availability</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$products): ?>

                <tr>
                    <td colspan="6">

                        <div class="empty-state">
                            <i class="fa-solid fa-box-open"></i>

                            <h4>No products found</h4>

                            <p>
                                Add a product or adjust your search filters.
                            </p>
                        </div>

                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($products as $product): ?>

                    <?php
                    $image = trim((string) ($product['image'] ?? ''));
                    $imageUrl = '';

                    if ($image !== '') {
                        if (preg_match('~^https?://~i', $image)) {
                            $imageUrl = $image;
                        } else {
                            $imageUrl = '../' . ltrim($image, '/');
                        }
                    }
                    ?>

                    <tr>

                        <td>
                            <div class="d-flex align-items-center gap-3">

                                <?php if ($imageUrl !== ''): ?>

                                    <img
                                        src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        style="width:52px;height:52px;object-fit:cover;border-radius:9px;"
                                    >

                                <?php else: ?>

                                    <div class="stat-icon green">
                                        <i class="fa-solid fa-image"></i>
                                    </div>

                                <?php endif; ?>

                                <div>
                                    <strong>
                                        <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </strong>

                                    <div class="text-muted small">
                                        Product #<?= (int) $product['id'] ?>
                                    </div>
                                </div>

                            </div>
                        </td>

                        <td>
                            <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <strong>
                                KSh <?= number_format((float) $product['price'], 2) ?>
                            </strong>
                        </td>

                        <td>
                            <?= number_format((int) $product['stock_quantity']) ?>
                        </td>

                        <td>

                            <?php if ((int) $product['is_available'] === 1): ?>

                                <span class="status-badge status-confirmed">
                                    Available
                                </span>

                            <?php else: ?>

                                <span class="status-badge status-cancelled">
                                    Unavailable
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="d-flex gap-2 align-items-center">

                                <a
                                    href="product_edit.php?id=<?= (int) $product['id'] ?>"
                                    class="btn btn-sm btn-outline-success"
                                    title="Edit product"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <form
                                    action="product_toggle.php"
                                    method="POST"
                                    onsubmit="return confirm('Change this product availability?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $product['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Change availability"
                                    >
                                        <i class="fa-solid fa-power-off"></i>
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
