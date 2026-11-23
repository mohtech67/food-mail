
<?php
session_start();

require_once __DIR__ . '/config/database.php';

$pageTitle = 'My Shopping Cart | Food Mail';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize cart
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cartItems = [];
$subtotal = 0.00;
$cartError = false;

try {

    if (!empty($_SESSION['cart'])) {

        $ids = [];

        foreach (array_keys($_SESSION['cart']) as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT);

            if ($id && $id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        if (!empty($ids)) {

            $placeholders = implode(
                ',',
                array_fill(0, count($ids), '?')
            );

            $stmt = $pdo->prepare(
                "SELECT id, name, price, image,
                        stock_quantity, is_available
                 FROM products
                 WHERE id IN ($placeholders)"
            );

            $stmt->execute($ids);

            $products = $stmt->fetchAll();

            foreach ($products as $product) {

                $id = (int)$product['id'];

                $quantity = (int)(
                    $_SESSION['cart'][$id] ?? 0
                );

                if ($quantity < 1) {
                    unset($_SESSION['cart'][$id]);
                    continue;
                }

                $product['quantity'] = $quantity;

                $product['line_total'] =
                    (float)$product['price'] * $quantity;

                $product['stock_problem'] =
                    !$product['is_available'] ||
                    $quantity > (int)$product['stock_quantity'];

                $cartItems[] = $product;

                $subtotal += $product['line_total'];
            }
        }
    }

} catch (PDOException $e) {

    error_log('Food Mail cart page error: ' . $e->getMessage());

    $cartError = true;
}

$cartCount = array_sum($_SESSION['cart']);

$csrfToken = htmlspecialchars(
    $_SESSION['csrf_token'],
    ENT_QUOTES,
    'UTF-8'
);

require_once __DIR__ . '/includes/header.php';
?>

<main class="cart-page">

    <!-- PAGE BANNER -->
    <section class="shop-banner">
        <div class="container">

            <span class="section-eyebrow">
                YOUR FOOD MAIL ORDER
            </span>

            <h1>My Shopping Cart</h1>

            <p>
                Review your selected products before proceeding
                to checkout.
            </p>

        </div>
    </section>

    <section class="section-padding">

        <div class="container">

            <!-- NOTIFICATIONS -->
            <?php if (!empty($_SESSION['cart_message'])): ?>

                <div class="alert alert-info alert-dismissible fade show">

                    <?= htmlspecialchars(
                        $_SESSION['cart_message'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="alert">
                    </button>

                </div>

                <?php unset($_SESSION['cart_message']); ?>

            <?php endif; ?>

            <?php if ($cartError): ?>

                <div class="alert alert-danger">
                    Unable to load your cart. Please try again later.
                </div>

            <?php elseif (empty($cartItems)): ?>

                <!-- EMPTY CART -->
                <div class="shop-empty">

                    <div class="empty-icon">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>

                    <h3>Your cart is empty</h3>

                    <p>
                        You haven't added any products yet.
                        Explore our menu and find something delicious.
                    </p>

                    <a href="shop.php" class="btn btn-food">
                        Start Shopping
                    </a>

                </div>

            <?php else: ?>

                <div class="row g-4">

                    <!-- CART PRODUCTS -->
                    <div class="col-lg-8">

                        <div class="cart-table-wrapper">

                            <div class="cart-table-header">

                                <h4>
                                    Your Products
                                    (<?= (int)$cartCount ?>)
                                </h4>

                            </div>

                            <!-- QUANTITY UPDATE FORM -->
                            <form id="updateCart"
                                  action="cart_action.php"
                                  method="POST">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= $csrfToken ?>">

                                <input type="hidden"
                                       name="action"
                                       value="update">

                            </form>

                            <?php foreach ($cartItems as $item): ?>

                                <div class="cart-item">

                                    <!-- PRODUCT IMAGE -->
                                    <img
                                        src="<?= htmlspecialchars(
                                            $item['image'] ?: 'https://placehold.co/200x160?text=Food+Mail',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        alt="<?= htmlspecialchars(
                                            $item['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <!-- PRODUCT DETAILS -->
                                    <div class="cart-item-details">

                                        <h5>
                                            <?= htmlspecialchars(
                                                $item['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </h5>

                                        <p>
                                            KSh
                                            <?= number_format(
                                                (float)$item['price'],
                                                2
                                            ) ?>
                                            each
                                        </p>

                                        <?php if ($item['stock_problem']): ?>

                                            <small class="text-danger">
                                                Stock availability has changed.
                                                Please adjust your quantity.
                                            </small>

                                        <?php endif; ?>

                                        <!-- QUANTITY CONTROL -->
                                        <div class="quantity-control">

                                            <label for="qty-<?= (int)$item['id'] ?>">
                                                Quantity
                                            </label>

                                            <input
                                                type="number"
                                                id="qty-<?= (int)$item['id'] ?>"
                                                name="quantities[<?= (int)$item['id'] ?>]"
                                                value="<?= (int)$item['quantity'] ?>"
                                                min="0"
                                                max="<?= max(1, (int)$item['stock_quantity']) ?>"
                                                form="updateCart"
                                                required
                                            >

                                        </div>

                                    </div>

                                    <!-- PRICE AND REMOVE -->
                                    <div class="cart-item-price">

                                        <strong>
                                            KSh
                                            <?= number_format(
                                                $item['line_total'],
                                                2
                                            ) ?>
                                        </strong>

                                        <!-- INDEPENDENT REMOVE FORM -->
                                        <form action="cart_action.php"
                                              method="POST">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= $csrfToken ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="remove">

                                            <input type="hidden"
                                                   name="product_id"
                                                   value="<?= (int)$item['id'] ?>">

                                            <button type="submit"
                                                    class="remove-item">

                                                <i class="fa-solid fa-trash"></i>
                                                Remove

                                            </button>

                                        </form>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                            <!-- UPDATE CART BUTTON -->
                            <button type="submit"
                                    form="updateCart"
                                    class="btn btn-outline-food mt-4">

                                <i class="fa-solid fa-rotate me-1"></i>
                                Update Cart

                            </button>

                        </div>

                    </div>

                    <!-- ORDER SUMMARY -->
                    <div class="col-lg-4">

                        <div class="cart-summary">

                            <h4>Order Summary</h4>

                            <div class="summary-row">

                                <span>Subtotal</span>

                                <strong>
                                    KSh
                                    <?= number_format($subtotal, 2) ?>
                                </strong>

                            </div>

                            <div class="summary-row">

                                <span>Delivery</span>

                                <strong class="free-delivery">
                                    FREE
                                </strong>

                            </div>

                            <div class="summary-total">

                                <span>Total</span>

                                <strong>
                                    KSh
                                    <?= number_format($subtotal, 2) ?>
                                </strong>

                            </div>

                            <?php
                            $hasStockProblem = false;

                            foreach ($cartItems as $item) {
                                if ($item['stock_problem']) {
                                    $hasStockProblem = true;
                                    break;
                                }
                            }
                            ?>

                            <?php if ($hasStockProblem): ?>

                                <button class="btn btn-secondary w-100"
                                        disabled>
                                    Resolve Stock Issues First
                                </button>

                            <?php else: ?>

                                <a href="checkout.php"
                                   class="btn btn-food w-100">

                                    Proceed to Checkout

                                    <i class="fa-solid fa-arrow-right ms-2"></i>

                                </a>

                            <?php endif; ?>

                            <a href="shop.php"
                               class="continue-shopping">

                                <i class="fa-solid fa-arrow-left me-1"></i>
                                Continue Shopping

                            </a>

                            <div class="cart-delivery-note">

                                <i class="fa-solid fa-truck-fast"></i>

                                Enjoy free delivery within our designated
                                Kitale service area.

                            </div>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
