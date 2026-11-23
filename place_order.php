
<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/payment.php';

function checkoutRedirect($message)
{
    $_SESSION['checkout_error'] = $message;
    header('Location: checkout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

/*
|--------------------------------------------------------------------------
| CSRF SECURITY
|--------------------------------------------------------------------------
*/

$token = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($token) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $token)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh and try again.');
}

/*
|--------------------------------------------------------------------------
| CUSTOMER INFORMATION
|--------------------------------------------------------------------------
*/

$name = trim($_POST['customer_name'] ?? '');
$phone = trim($_POST['customer_phone'] ?? '');
$estate = trim($_POST['estate'] ?? '');
$address = trim($_POST['delivery_address'] ?? '');
$notes = trim($_POST['delivery_notes'] ?? '');

$paymentMethod = $_POST['payment_method'] ?? '';
$transactionCode = strtoupper(trim($_POST['transaction_code'] ?? ''));

$normalizedPhone = preg_replace('/[\s\-]/', '', $phone);

if (
    $name === '' ||
    strlen($name) > 150 ||
    $phone === '' ||
    strlen($phone) > 20 ||
    !preg_match('/^(?:\+254|254|0)[17][0-9]{8}$/', $normalizedPhone) ||
    $estate === '' ||
    strlen($estate) > 150 ||
    $address === '' ||
    strlen($address) > 1000 ||
    strlen($notes) > 1000
) {
    checkoutRedirect('Please provide valid customer and delivery information.');
}

if ($paymentMethod !== 'mpesa') {
    checkoutRedirect('Please select M-Pesa as your payment method.');
}

if (!preg_match('/^[A-Z0-9]{8,20}$/', $transactionCode)) {
    checkoutRedirect('Please enter a valid M-Pesa transaction code.');
}

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    checkoutRedirect('Your shopping cart is empty.');
}

$deliveryAddress = $estate . ' - ' . $address;

if (strlen($deliveryAddress) > 2000) {
    checkoutRedirect('Your delivery address is too long.');
}

$cart = $_SESSION['cart'];

try {

    $pdo->beginTransaction();

    $orderProducts = [];
    $subtotal = 0.00;

    /*
    |--------------------------------------------------------------------------
    | 1. VALIDATE PRODUCTS AND LOCK STOCK
    |--------------------------------------------------------------------------
    */

    foreach ($cart as $productId => $quantity) {

        $productId = filter_var($productId, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if (
            !$productId ||
            $productId < 1 ||
            !$quantity ||
            $quantity < 1
        ) {
            throw new RuntimeException('Invalid cart information.');
        }

        $productQuery = $pdo->prepare(
            "SELECT id, name, price, stock_quantity, is_available
             FROM products
             WHERE id = :product_id
             FOR UPDATE"
        );

        $productQuery->execute([
            'product_id' => $productId
        ]);

        $product = $productQuery->fetch(PDO::FETCH_ASSOC);

        if (!$product || !(int)$product['is_available']) {
            throw new RuntimeException(
                'One of your products is no longer available.'
            );
        }

        if ($quantity > (int)$product['stock_quantity']) {
            throw new RuntimeException(
                'Insufficient stock for ' . $product['name'] . '.'
            );
        }

        $lineTotal = round((float)$product['price'] * $quantity, 2);

        $subtotal += $lineTotal;

        $orderProducts[] = [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'price' => (float)$product['price'],
            'quantity' => $quantity,
            'total' => $lineTotal
        ];
    }

    if (empty($orderProducts)) {
        throw new RuntimeException('Your shopping cart is empty.');
    }

    $subtotal = round($subtotal, 2);
    $deliveryFee = (float)FOOD_MAIL_DELIVERY_FEE;
    $total = round($subtotal + $deliveryFee, 2);

    if ($total <= 0) {
        throw new RuntimeException('Invalid order total.');
    }

    /*
    |--------------------------------------------------------------------------
    | 2. IDENTIFY REGISTERED CUSTOMER
    |--------------------------------------------------------------------------
    */

    $userId = null;

    $candidateId = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? null;

    if ($candidateId !== null) {

        $candidateId = filter_var($candidateId, FILTER_VALIDATE_INT);

        if ($candidateId && $candidateId > 0) {

            $userQuery = $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE id = :user_id
                   AND role = 'customer'
                   AND account_status = 'active'
                 LIMIT 1"
            );

            $userQuery->execute([
                'user_id' => $candidateId
            ]);

            $validUser = $userQuery->fetch(PDO::FETCH_ASSOC);

            if ($validUser) {
                $userId = (int)$validUser['id'];
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. GENERATE ORDER NUMBER
    |--------------------------------------------------------------------------
    */

    $orderNumber = 'FM-' . date('Ymd') . '-' .
        strtoupper(bin2hex(random_bytes(4)));

    /*
    |--------------------------------------------------------------------------
    | 4. CREATE CUSTOMER ORDER
    |--------------------------------------------------------------------------
    */

    $orderQuery = $pdo->prepare(
        "INSERT INTO orders (
            order_number,
            user_id,
            customer_name,
            customer_phone,
            delivery_address,
            delivery_notes,
            subtotal,
            delivery_fee,
            total_amount,
            status
        ) VALUES (
            :order_number,
            :user_id,
            :customer_name,
            :customer_phone,
            :delivery_address,
            :delivery_notes,
            :subtotal,
            :delivery_fee,
            :total_amount,
            'pending'
        )"
    );

    $orderQuery->execute([
        'order_number' => $orderNumber,
        'user_id' => $userId,
        'customer_name' => $name,
        'customer_phone' => $phone,
        'delivery_address' => $deliveryAddress,
        'delivery_notes' => $notes !== '' ? $notes : null,
        'subtotal' => $subtotal,
        'delivery_fee' => $deliveryFee,
        'total_amount' => $total
    ]);

    $orderId = (int)$pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | 5. SAVE ORDER ITEMS
    |--------------------------------------------------------------------------
    */

    $itemQuery = $pdo->prepare(
        "INSERT INTO order_items (
            order_id,
            product_id,
            product_name,
            quantity,
            unit_price,
            total_price
        ) VALUES (
            :order_id,
            :product_id,
            :product_name,
            :quantity,
            :unit_price,
            :total_price
        )"
    );

    /*
    |--------------------------------------------------------------------------
    | 6. DEDUCT PRODUCT STOCK
    |--------------------------------------------------------------------------
    |
    | Every named parameter is unique.
    | This avoids PDO HY093 Invalid parameter number errors.
    |
    */

    $stockQuery = $pdo->prepare(
        "UPDATE products
         SET stock_quantity = stock_quantity - :deduct_quantity
         WHERE id = :product_id
           AND stock_quantity >= :minimum_quantity
           AND is_available = 1"
    );

    foreach ($orderProducts as $item) {

        $itemQuery->execute([
            'order_id' => $orderId,
            'product_id' => $item['id'],
            'product_name' => $item['name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'total_price' => $item['total']
        ]);

        $stockQuery->execute([
            'deduct_quantity' => $item['quantity'],
            'product_id' => $item['id'],
            'minimum_quantity' => $item['quantity']
        ]);

        if ($stockQuery->rowCount() !== 1) {
            throw new RuntimeException(
                'Product stock changed. Please try again.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 7. RECORD M-PESA PAYMENT
    |--------------------------------------------------------------------------
    */

    $paymentQuery = $pdo->prepare(
        "INSERT INTO payments (
            order_id,
            payment_method,
            transaction_code,
            amount,
            status
        ) VALUES (
            :order_id,
            'mpesa',
            :transaction_code,
            :amount,
            'pending'
        )"
    );

    $paymentQuery->execute([
        'order_id' => $orderId,
        'transaction_code' => $transactionCode,
        'amount' => $total
    ]);

    /*
    |--------------------------------------------------------------------------
    | 8. RECORD ORDER STATUS HISTORY
    |--------------------------------------------------------------------------
    */

    $historyQuery = $pdo->prepare(
        "INSERT INTO order_status_history (
            order_id,
            status,
            description
        ) VALUES (
            :order_id,
            'pending',
            :description
        )"
    );

    $historyQuery->execute([
        'order_id' => $orderId,
        'description' => 'Order successfully received. Awaiting confirmation.'
    ]);

    /*
    |--------------------------------------------------------------------------
    | 9. COMMIT DATABASE TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    $_SESSION['cart'] = [];

    $_SESSION['last_order_number'] = $orderNumber;
    $_SESSION['last_order_phone'] = $phone;
    $_SESSION['last_order_total'] = $total;

    unset($_SESSION['checkout_error']);

    header('Location: order_success.php');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Food Mail order processing error: ' . $e->getMessage()
    );

    if ($e instanceof PDOException && $e->getCode() === '23000') {
        checkoutRedirect(
            'This M-Pesa transaction code has already been submitted, or the order contains conflicting information.'
        );
    }

    if ($e instanceof RuntimeException) {
        checkoutRedirect($e->getMessage());
    }

    checkoutRedirect(
        'We could not complete your order. Please try again.'
    );
}
