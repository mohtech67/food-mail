
<?php
session_start();

require_once __DIR__ . '/config/database.php';

$pageTitle = 'Shop | Food Mail Kitale';

$search = trim($_GET['search'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 0;
$categorySlug = $_GET['category'] ?? '';

$categories = [];
$products = [];

try {
    $categories = $pdo->query(
        "SELECT id, name FROM categories
         WHERE is_active = 1 ORDER BY name ASC"
    )->fetchAll();

    $sql = "SELECT p.id, p.name, p.description, p.price,
                   p.image, p.stock_quantity, c.name AS category_name,
                   c.id AS category_id
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE p.is_available = 1
              AND c.is_active = 1";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (p.name LIKE :search OR p.description LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }

    if ($categoryId > 0) {
        $sql .= " AND c.id = :category_id";
        $params['category_id'] = $categoryId;
    } elseif ($categorySlug === 'water') {
        $sql .= " AND c.name = 'Drinking Water'";
    } elseif ($categorySlug === 'food') {
        $sql .= " AND c.name = 'Meals'";
    }

    $sql .= " ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Food Mail shop error: ' . $e->getMessage());
    $shopError = true;
}

$cartCount = array_sum($_SESSION['cart'] ?? []);

require_once __DIR__ . '/includes/header.php';
?>

<main class="shop-page">

    <section class="shop-banner">
        <div class="container">
            <span class="section-eyebrow">WELCOME TO FOOD MAIL</span>
            <h1>Discover something delicious.</h1>
            <p>Shop your favourite meals and drinking water. We deliver within our Kitale service area for free.</p>
            <div class="shop-banner-note">
                <i class="fa-solid fa-truck-fast"></i>
                Free delivery in designated Kitale areas
            </div>
        </div>
    </section>

    <section class="section-padding">
        <div class="container">

            <div class="shop-toolbar">
                <div>
                    <span class="section-eyebrow">OUR PRODUCTS</span>
                    <h2>Explore Our Shop</h2>
                    <p>Find something you love today.</p>
                </div>

                <a href="cart.php" class="btn btn-food cart-button">
                    <i class="fa-solid fa-cart-shopping"></i>
                    My Cart
                    <span class="cart-count"><?= (int)$cartCount ?></span>
                </a>
            </div>

            <form action="shop.php" method="GET" class="shop-search">
                <?php if ($categoryId > 0): ?>
                    <input type="hidden" name="category_id"
                           value="<?= (int)$categoryId ?>">
                <?php elseif ($categorySlug !== ''): ?>
                    <input type="hidden" name="category"
                           value="<?= htmlspecialchars($categorySlug, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>

                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" name="search"
                       placeholder="Search food, water and more..."
                       value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Search</button>
            </form>

            <div class="category-filters">
                <a href="shop.php"
                   class="<?= $categoryId === 0 && $categorySlug === '' ? 'selected' : '' ?>">
                    All Products
                </a>

                <?php foreach ($categories as $category): ?>
                    <a href="shop.php?category_id=<?= (int)$category['id'] ?>"
                       class="<?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>">
                        <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($shopError)): ?>

                <div class="alert alert-danger">
                    We could not load products. Please try again later.
                </div>

            <?php elseif (empty($products)): ?>

                <div class="shop-empty">
                    <div class="empty-icon">
                        <i class="fa-solid fa-basket-shopping"></i>
                    </div>
                    <h3>No products found</h3>
                    <p>
                        <?php if ($search !== ''): ?>
                            Try searching with another product name.
                        <?php else: ?>
                            Our team is preparing products for you.
                            Please check back soon.
                        <?php endif; ?>
                    </p>
                    <a href="shop.php" class="btn btn-food">View All Products</a>
                </div>

            <?php else: ?>

                <div class="row g-4">

                    <?php foreach ($products as $product): ?>

                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="product-card shop-product-card">

                                <div class="product-image">
                                    <img
                                        src="<?= htmlspecialchars($product['image'] ?: 'https://placehold.co/600x450?text=Food+Mail', ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        loading="lazy"
                                    >

                                    <span class="product-category">
                                        <?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <div class="product-details">

                                    <h5>
                                        <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </h5>

                                    <p>
                                        <?= htmlspecialchars(
                                            mb_strimwidth($product['description'] ?? '', 0, 75, '...'),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <div class="product-bottom">
                                        <strong>
                                            KSh <?= number_format((float)$product['price'], 2) ?>
                                        </strong>
                                    </div>

                                    <?php if ((int)$product['stock_quantity'] > 0): ?>

                                        <form action="cart_action.php" method="POST" class="add-cart-form">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id"
                                                   value="<?= (int)$product['id'] ?>">

                                            <button type="submit" class="btn btn-food w-100">
                                                <i class="fa-solid fa-cart-plus me-1"></i>
                                                Add to Cart
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <button class="btn btn-secondary w-100" disabled>
                                            Out of Stock
                                        </button>

                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
