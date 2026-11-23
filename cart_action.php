
<?php
session_start();

require_once __DIR__ . '/config/database.php';

function redirectToCart(): void
{
    header('Location: cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Validate CSRF token
$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh the page and try again.');
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? '';

try {

    // ADD PRODUCT TO CART
    if ($action === 'add') {

        $productId = filter_input(
            INPUT_POST,
            'product_id',
            FILTER_VALIDATE_INT
        );

        if (!$productId || $productId < 1) {
            $_SESSION['cart_message'] = 'Invalid product selected.';
            redirectToCart();
        }

        $stmt = $pdo->prepare(
            "SELECT id, name, stock_quantity, is_available
             FROM products
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();

        if (!$product || !$product['is_available']) {
            $_SESSION['cart_message'] = 'This product is unavailable.';
            redirectToCart();
        }

        $currentQuantity = (int)(
            $_SESSION['cart'][$productId] ?? 0
        );

        if (
            $currentQuantity + 1 >
            (int)$product['stock_quantity']
        ) {
            $_SESSION['cart_message'] =
                'Sorry, insufficient stock available.';
            redirectToCart();
        }

        $_SESSION['cart'][$productId] = $currentQuantity + 1;

        $_SESSION['cart_message'] =
            $product['name'] . ' added to your cart successfully.';

        redirectToCart();
    }

    // UPDATE CART QUANTITIES
    elseif ($action === 'update') {

        $quantities = $_POST['quantities'] ?? [];

        if (!is_array($quantities)) {
            $_SESSION['cart_message'] = 'Invalid cart information.';
            redirectToCart();
        }

        $updatedCart = $_SESSION['cart'];

        foreach ($_SESSION['cart'] as $productId => $oldQuantity) {

            $productId = (int)$productId;

            $newQuantity = filter_var(
                $quantities[$productId] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($newQuantity === false || $newQuantity === null) {
                $_SESSION['cart_message'] = 'Invalid quantity submitted.';
                redirectToCart();
            }

            if ($newQuantity < 0) {
                $_SESSION['cart_message'] =
                    'Quantity cannot be negative.';
                redirectToCart();
            }

            if ($newQuantity === 0) {
                unset($updatedCart[$productId]);
                continue;
            }

            $stmt = $pdo->prepare(
                "SELECT stock_quantity, is_available
                 FROM products
                 WHERE id = :id
                 LIMIT 1"
            );

            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch();

            if (
                !$product ||
                !$product['is_available'] ||
                $newQuantity > (int)$product['stock_quantity']
            ) {
                $_SESSION['cart_message'] =
                    'A product has insufficient stock or is unavailable.';
                redirectToCart();
            }

            $updatedCart[$productId] = $newQuantity;
        }

        $_SESSION['cart'] = $updatedCart;

        $_SESSION['cart_message'] =
            'Your shopping cart has been updated.';

        redirectToCart();
    }

    // REMOVE PRODUCT
    elseif ($action === 'remove') {

        $productId = filter_input(
            INPUT_POST,
            'product_id',
            FILTER_VALIDATE_INT
        );

        if ($productId && $productId > 0) {

            unset($_SESSION['cart'][$productId]);

            $_SESSION['cart_message'] =
                'Product removed from your cart.';
        }

        redirectToCart();
    }

    else {

        http_response_code(400);
        exit('Invalid cart action.');
    }

} catch (PDOException $e) {

    error_log('Food Mail cart error: ' . $e->getMessage());

    $_SESSION['cart_message'] =
        'Something went wrong. Please try again later.';

    redirectToCart();
}
