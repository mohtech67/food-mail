
<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/payment.php';

$pageTitle = 'Checkout | Food Mail';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['cart'])) {
    header('Location: shop.php');
    exit;
}

$cartItems = [];
$subtotal = 0.00;
$checkoutError = false;

try {
    $ids = [];

    foreach (array_keys($_SESSION['cart']) as $id) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id && $id > 0) {
            $ids[] = $id;
        }
    }

    $ids = array_values(array_unique($ids));

    if (empty($ids)) {
        $_SESSION['cart'] = [];
        header('Location: shop.php');
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT id, name, price, image, stock_quantity, is_available
         FROM products
         WHERE id IN ($placeholders)"
    );

    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        $id = (int)$product['id'];
        $quantity = (int)($_SESSION['cart'][$id] ?? 0);

        if (
            $quantity < 1 ||
            !$product['is_available'] ||
            $quantity > (int)$product['stock_quantity']
        ) {
            $checkoutError = true;
            continue;
        }

        $product['quantity'] = $quantity;
        $product['line_total'] = (float)$product['price'] * $quantity;

        $cartItems[] = $product;
        $subtotal += $product['line_total'];
    }

    if (count($cartItems) !== count($ids)) {
        $checkoutError = true;
    }

} catch (PDOException $e) {
    error_log('Food Mail checkout error: ' . $e->getMessage());
    $checkoutError = true;
}

$deliveryFee = (float)FOOD_MAIL_DELIVERY_FEE;
$total = $subtotal + $deliveryFee;

require_once __DIR__ . '/includes/header.php';
?>

<main class="checkout-page">

    <section class="shop-banner">
        <div class="container">
            <span class="section-eyebrow">ALMOST THERE</span>
            <h1>Complete Your Order</h1>
            <p>Tell us where to deliver your food and water.</p>
        </div>
    </section>

    <section class="section-padding">
        <div class="container">
            <?php if (!empty($_SESSION['checkout_error'])): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars(
            $_SESSION['checkout_error'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
    <?php unset($_SESSION['checkout_error']); ?>
<?php endif; ?>

            <?php if ($checkoutError): ?>

                <div class="alert alert-danger">
                    Some products are unavailable or their stock has changed.
                    Please return to your cart and update your order.
                    <a href="cart.php">Return to Cart</a>
                </div>

            <?php elseif (empty($cartItems)): ?>

                <div class="alert alert-warning">
                    Your cart is empty.
                    <a href="shop.php">Continue shopping</a>
                </div>

            <?php else: ?>

                <form action="place_order.php" method="POST">

                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row g-4">

                        <!-- CUSTOMER DETAILS -->
                        <div class="col-lg-7">

                            <div class="checkout-card">

                                <h3>
                                    <i class="fa-solid fa-user me-2"></i>
                                    Delivery Information
                                </h3>

                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text"
                                           name="customer_name"
                                           class="form-control"
                                           maxlength="150"
                                           placeholder="Enter your full name"
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="tel"
                                           name="customer_phone"
                                           class="form-control"
                                           maxlength="20"
                                           placeholder="e.g. 0712345678"
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Estate / Area *</label>
                                    <input type="text"
                                           name="estate"
                                           class="form-control"
                                           maxlength="150"
                                           placeholder="e.g. Milimani, Kitale"
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Specific Delivery Location *</label>
                                    <textarea name="delivery_address"
                                              class="form-control"
                                              rows="3"
                                              maxlength="1000"
                                              placeholder="House number, street, building or nearby landmark"
                                              required></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Additional Instructions</label>
                                    <textarea name="delivery_notes"
                                              class="form-control"
                                              rows="2"
                                              maxlength="1000"
                                              placeholder="Any additional information for the delivery person?"></textarea>
                                </div>

                                <h3 class="mt-4">
                                    <i class="fa-solid fa-wallet me-2"></i>
                                    Payment Method
                                </h3>

                                <div class="payment-option">
                                    <div class="payment-icon">
                                        <i class="fa-solid fa-mobile-screen-button"></i>
                                    </div>

                                    <div>
                                        <strong>M-Pesa Manual Payment</strong>
                                        <p>Send payment to the Food Mail number.</p>
                                    </div>

                                    <input type="radio"
                                           name="payment_method"
                                           value="mpesa"
                                           checked>
                                </div>

                                <div class="mpesa-instructions">
                                    <h5>How to Pay</h5>

                                    <p>Send your payment to:</p>

                                    <div class="mpesa-number">
                                        <?= htmlspecialchars(
                                            FOOD_MAIL_MPESA_NUMBER,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <p>
                                        After sending the money, enter your
                                        M-Pesa transaction code below.
                                    </p>

                                    <div class="mb-3">
                                        <label class="form-label">
                                            M-Pesa Transaction Code *
                                        </label>

                                        <input type="text"
                                               name="transaction_code"
                                               class="form-control text-uppercase"
                                               maxlength="100"
                                               placeholder="e.g. QAB12CD34E"
                                               autocomplete="off"
                                               required>
                                    </div>

                                    <small>
                                        Payment will remain pending until
                                        Food Mail manually confirms it.
                                    </small>
                                </div>

                            </div>

                        </div>

                        <!-- ORDER SUMMARY -->
                        <div class="col-lg-5">

                            <div class="checkout-summary">

                                <h3>Your Order</h3>

                                <?php foreach ($cartItems as $item): ?>

                                    <div class="checkout-product">

                                        <div>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $item['name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </strong>

                                            <small>
                                                Quantity: <?= (int)$item['quantity'] ?>
                                            </small>
                                        </div>

                                        <strong>
                                            KSh <?= number_format(
                                                $item['line_total'],
                                                2
                                            ) ?>
                                        </strong>

                                    </div>

                                <?php endforeach; ?>

                                <div class="summary-row">
                                    <span>Subtotal</span>
                                    <strong>KSh <?= number_format($subtotal, 2) ?></strong>
                                </div>

                                <div class="summary-row">
                                    <span>Delivery Fee</span>
                                    <strong class="free-delivery">FREE</strong>
                                </div>

                                <div class="summary-total">
                                    <span>Total to Pay</span>
                                    <strong>KSh <?= number_format($total, 2) ?></strong>
                                </div>

                                <button type="submit"
                                        class="btn btn-food w-100 mt-3">

                                    Place My Order
                                    <i class="fa-solid fa-arrow-right ms-2"></i>

                                </button>

                                <p class="checkout-security">
                                    <i class="fa-solid fa-shield-halved"></i>
                                    Your order will be securely recorded.
                                </p>

                                <a href="cart.php" class="continue-shopping">
                                    <i class="fa-solid fa-arrow-left"></i>
                                    Back to Cart
                                </a>

                            </div>

                        </div>

                    </div>

                </form>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
