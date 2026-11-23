
<?php
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Fresh Food and Drinking Water Delivery in Kitale';

$products = [];
$categories = [];

try {
    $stmt = $pdo->query("
        SELECT
            p.id,
            p.name,
            p.description,
            p.price,
            p.image,
            p.stock_quantity,
            c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.is_available = 1
          AND p.stock_quantity > 0
        ORDER BY p.created_at DESC
        LIMIT 8
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categoryStmt = $pdo->query("
        SELECT id, name, description, image
        FROM categories
        WHERE is_active = 1
        ORDER BY name ASC
        LIMIT 6
    ");

    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Food Mail homepage database error: ' . $e->getMessage());
}

function homeEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function homeImage($image): string
{
    $image = trim((string)$image);

    if ($image === '') {
        return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=700&auto=format&fit=crop&q=85';
    }

    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }

    return 'uploads/products/' . rawurlencode(basename($image));
}

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="assets/css/home.css">

<!-- HERO SECTION -->
<section class="fm-hero">
    <div class="fm-hero-inner">

        <div class="fm-hero-content">

            <div class="fm-location">
                <i class="fas fa-location-dot"></i>
                SERVING KITALE, KENYA
            </div>

            <h1>
                Your favourite food,<br>
                <span>delivered fresh.</span>
            </h1>

            <p class="fm-hero-description">
                Hungry? Need drinking water? Food Mail brings delicious meals
                and quality water right to your doorstep. Order online and
                enjoy convenient delivery within our designated Kitale
                service area.
            </p>

            <div class="fm-hero-buttons">
                <a href="shop.php" class="fm-btn fm-btn-primary">
                    Explore Our Menu
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="#how-it-works" class="fm-btn fm-btn-outline">
                    How It Works
                </a>
            </div>

            <div class="fm-benefits">
                <div>
                    <i class="fas fa-truck-fast"></i>
                    <span>Free Delivery</span>
                </div>

                <div>
                    <i class="fas fa-bowl-food"></i>
                    <span>Fresh Meals</span>
                </div>

                <div>
                    <i class="fas fa-shield-halved"></i>
                    <span>Easy Ordering</span>
                </div>
            </div>

        </div>

        <div class="fm-hero-image">
            <img
                src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=1200&auto=format&fit=crop&q=90"
                alt="Fresh healthy food prepared for delivery"
                fetchpriority="high"
            >

            <div class="fm-image-caption">
                <span class="fm-caption-icon">
                    <i class="fas fa-heart"></i>
                </span>
                <div>
                    <strong>Freshness delivered with care</strong>
                    <small>Quality you can trust</small>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- SERVICE PROMISE -->
<section class="fm-promise">
    <div class="fm-container fm-promise-grid">

        <div class="fm-promise-item">
            <i class="fas fa-truck-fast"></i>
            <div>
                <strong>Free Delivery</strong>
                <span>Within designated Kitale areas</span>
            </div>
        </div>

        <div class="fm-promise-item">
            <i class="fas fa-leaf"></i>
            <div>
                <strong>Fresh Quality</strong>
                <span>Food selected with care</span>
            </div>
        </div>

        <div class="fm-promise-item">
            <i class="fas fa-clock"></i>
            <div>
                <strong>Convenient Ordering</strong>
                <span>Order online whenever you need</span>
            </div>
        </div>

        <div class="fm-promise-item">
            <i class="fas fa-droplet"></i>
            <div>
                <strong>Drinking Water</strong>
                <span>Water delivered to your doorstep</span>
            </div>
        </div>

    </div>
</section>

<!-- CATEGORIES -->
<section class="fm-section">
    <div class="fm-container">

        <div class="fm-section-heading">
            <div>
                <span class="fm-eyebrow">EXPLORE FOOD MAIL</span>
                <h2>What would you like today?</h2>
                <p>Discover our available food and drinking water categories.</p>
            </div>

            <a href="shop.php" class="fm-text-link">
                View all products <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <?php if ($categories): ?>

            <div class="fm-category-grid">

                <?php foreach ($categories as $category): ?>

                    <a
                        href="shop.php?category=<?= (int)$category['id']; ?>"
                        class="fm-category-card"
                    >

                        <?php if (!empty($category['image'])): ?>
                            <img
                                src="<?= homeEscape(homeImage($category['image'])); ?>"
                                alt="<?= homeEscape($category['name']); ?>"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="fm-category-placeholder">
                                <i class="fas fa-utensils"></i>
                            </div>
                        <?php endif; ?>

                        <div class="fm-category-overlay">
                            <h3><?= homeEscape($category['name']); ?></h3>
                            <span>Explore <i class="fas fa-arrow-right"></i></span>
                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="fm-empty-message">
                Our product categories are being prepared. Please check back soon.
            </div>

        <?php endif; ?>

    </div>
</section>

<!-- FEATURED PRODUCTS -->
<section class="fm-section fm-products-section">
    <div class="fm-container">

        <div class="fm-section-heading">
            <div>
                <span class="fm-eyebrow">FRESHLY AVAILABLE</span>
                <h2>Popular products</h2>
                <p>Explore products currently available from Food Mail.</p>
            </div>

            <a href="shop.php" class="fm-text-link">
                Shop all products <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <?php if ($products): ?>

            <div class="fm-product-grid">

                <?php foreach ($products as $product): ?>

                    <article class="fm-product-card">

                        <a href="shop.php" class="fm-product-image">
                            <img
                                src="<?= homeEscape(homeImage($product['image'])); ?>"
                                alt="<?= homeEscape($product['name']); ?>"
                                loading="lazy"
                            >
                        </a>

                        <div class="fm-product-info">

                            <span class="fm-product-category">
                                <?= homeEscape($product['category_name'] ?? 'Food Mail'); ?>
                            </span>

                            <h3><?= homeEscape($product['name']); ?></h3>

                            <p>
                                <?= homeEscape(
                                    mb_strimwidth(
                                        (string)($product['description'] ?? ''),
                                        0,
                                        85,
                                        '...'
                                    )
                                ); ?>
                            </p>

                            <div class="fm-product-bottom">
                                <strong>
                                    KSh <?= number_format((float)$product['price'], 2); ?>
                                </strong>

                                <a href="shop.php" aria-label="View products">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="fm-empty-message">
                Products will appear here once they have been added to the Food Mail inventory.
                <a href="shop.php">Visit our shop.</a>
            </div>

        <?php endif; ?>

        <div class="fm-center-button">
            <a href="shop.php" class="fm-btn fm-btn-primary">
                Browse All Products <i class="fas fa-arrow-right"></i>
            </a>
        </div>

    </div>
</section>

<!-- HOW IT WORKS -->
<section class="fm-section fm-how-section" id="how-it-works">
    <div class="fm-container">

        <div class="fm-section-heading fm-centered-heading">
            <div>
                <span class="fm-eyebrow">SIMPLE AND CONVENIENT</span>
                <h2>How Food Mail works</h2>
                <p>Getting your favourite products delivered is easy.</p>
            </div>
        </div>

        <div class="fm-steps-grid">

            <div class="fm-step-card">
                <div class="fm-step-icon">
                    <i class="fas fa-magnifying-glass"></i>
                </div>
                <span class="fm-step-number">01</span>
                <h3>Explore Products</h3>
                <p>Browse available food and drinking water products.</p>
            </div>

            <div class="fm-step-card">
                <div class="fm-step-icon">
                    <i class="fas fa-cart-shopping"></i>
                </div>
                <span class="fm-step-number">02</span>
                <h3>Place Your Order</h3>
                <p>Add products to your cart and provide your delivery details.</p>
            </div>

            <div class="fm-step-card">
                <div class="fm-step-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <span class="fm-step-number">03</span>
                <h3>Make Payment</h3>
                <p>Follow the payment instructions provided during checkout.</p>
            </div>

            <div class="fm-step-card">
                <div class="fm-step-icon">
                    <i class="fas fa-truck-fast"></i>
                </div>
                <span class="fm-step-number">04</span>
                <h3>Receive Delivery</h3>
                <p>Receive your order at your designated delivery address.</p>
            </div>

        </div>

    </div>
</section>

<!-- CUSTOMER ACCOUNT CALL TO ACTION -->
<section class="fm-account-section">
    <div class="fm-container fm-account-inner">

        <div>
            <span class="fm-eyebrow">YOUR FOOD MAIL ACCOUNT</span>
            <h2>Make every order easier.</h2>
            <p>
                Create an account to view your orders, follow delivery progress
                and share feedback about your experience.
            </p>
        </div>

        <div class="fm-account-buttons">
            <a href="customer/register.php" class="fm-btn fm-btn-light">
                Create Account
            </a>

            <a href="customer/login.php" class="fm-btn fm-btn-account-outline">
                Customer Login
            </a>
        </div>

    </div>
</section>

<!-- CONTACT -->
<section class="fm-section fm-contact-section" id="contact">
    <div class="fm-container fm-contact-inner">

        <div>
            <span class="fm-eyebrow">WE ARE HERE TO HELP</span>
            <h2>Need assistance with your order?</h2>
            <p>
                Contact the Food Mail team for assistance with products,
                delivery and orders.
            </p>
        </div>

        <a href="shop.php" class="fm-btn fm-btn-primary">
            Start Shopping <i class="fas fa-arrow-right"></i>
        </a>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
