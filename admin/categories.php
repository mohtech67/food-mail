
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Categories';

// Search
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        c.id,
        c.name,
        c.description,
        c.is_active,
        c.created_at,
        COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
";

$params = [];

if ($search !== '') {
    $sql .= " WHERE c.name LIKE :search OR c.description LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$sql .= "
    GROUP BY
        c.id,
        c.name,
        c.description,
        c.is_active,
        c.created_at
    ORDER BY c.created_at DESC, c.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

$success = $_SESSION['category_success'] ?? '';
$error = $_SESSION['category_error'] ?? '';

unset($_SESSION['category_success'], $_SESSION['category_error']);

$totalCategories = count($categories);
$activeCategories = 0;
$totalCategoryProducts = 0;

foreach ($categories as $category) {
    if ((int)$category['is_active'] === 1) {
        $activeCategories++;
    }

    $totalCategoryProducts += (int)$category['product_count'];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="welcome-label">INVENTORY ORGANIZATION</div>
        <h1>Categories</h1>
        <p>Organize your Food Mail products into manageable groups.</p>
    </div>

    <a href="category_form.php" class="btn-admin-primary">
        <i class="fa-solid fa-plus"></i>
        Add Category
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
                <i class="fa-solid fa-layer-group"></i>
            </div>
        </div>
        <h3><?= number_format($totalCategories) ?></h3>
        <p>Total categories</p>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon blue">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
        <h3><?= number_format($activeCategories) ?></h3>
        <p>Active categories</p>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon orange">
                <i class="fa-solid fa-box"></i>
            </div>
        </div>
        <h3><?= number_format($totalCategoryProducts) ?></h3>
        <p>Products across categories</p>
    </div>

</div>

<div class="dashboard-card">

    <div class="card-heading">
        <h3>Category Directory</h3>
        <span class="text-muted small">
            <?= number_format($totalCategories) ?> categories
        </span>
    </div>

    <form method="GET" class="row g-3 mb-4">

        <div class="col-md-8">
            <input
                type="search"
                name="search"
                class="form-control"
                placeholder="Search category name or description..."
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>

        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn-admin-primary">
                <i class="fa-solid fa-search"></i>
                Search
            </button>

            <a href="categories.php" class="btn-admin-secondary">
                Reset
            </a>
        </div>

    </form>

    <div class="table-responsive">

        <table class="admin-table">

            <thead>
                <tr>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$categories): ?>

                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fa-solid fa-layer-group"></i>
                            <h4>No categories found</h4>
                            <p>Create a category to organize your products.</p>
                        </div>
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($categories as $category): ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                            <div class="text-muted small">
                                Category #<?= (int)$category['id'] ?>
                            </div>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $category['description'] ?: 'No description',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <span class="badge text-bg-light">
                                <?= number_format((int)$category['product_count']) ?>
                            </span>
                        </td>

                        <td>
                            <?php if ((int)$category['is_active'] === 1): ?>
                                <span class="status-badge status-confirmed">Active</span>
                            <?php else: ?>
                                <span class="status-badge status-cancelled">Inactive</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                date('d M Y', strtotime($category['created_at'])),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <div class="d-flex gap-2 align-items-center">

                                <a
                                    href="category_form.php?id=<?= (int)$category['id'] ?>"
                                    class="btn btn-sm btn-outline-success"
                                    title="Edit category"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <form
                                    action="category_toggle.php"
                                    method="POST"
                                    onsubmit="return confirm('Change this category status?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$category['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Activate or deactivate"
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
