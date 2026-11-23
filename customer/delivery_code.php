
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/delivery.php';

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.order_number,
        o.status,
        o.customer_phone,
        d.code_encrypted,
        d.expires_at,
        d.used_at
    FROM orders o
    LEFT JOIN delivery_otps d ON d.order_id = o.id
    WHERE o.id = :order_id
      AND (
          o.user_id = :user_id
          OR o.customer_phone = :customer_phone
      )
    LIMIT 1
");

$stmt->execute([
    'order_id' => $orderId,
    'user_id' => (int)$currentCustomer['id'],
    'customer_phone' => $currentCustomer['phone']
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$otp = null;
$error = '';

if ($order['status'] === 'out_for_delivery' && !empty($order['code_encrypted'])) {

    if (!empty($order['used_at'])) {
        $error = 'This delivery code has already been used.';

    } elseif (strtotime($order['expires_at']) < time()) {
        $error = 'Your delivery code has expired. Contact Food Mail for assistance.';

    } else {

        $decoded = base64_decode($order['code_encrypted'], true);

        if ($decoded !== false && strlen($decoded) >= 29) {

            $nonce = substr($decoded, 0, 12);
            $tag = substr($decoded, 12, 16);
            $ciphertext = substr($decoded, 28);

            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                FOOD_MAIL_OTP_KEY,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                'food-mail-delivery-' . $orderId
            );

            if ($decrypted !== false) {
                $otp = $decrypted;
            } else {
                $error = 'Unable to retrieve your delivery code.';
            }

        } else {
            $error = 'Your delivery code could not be read.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Delivery Confirmation Code | Food Mail</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f2f7f3;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .code-card {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 12px 40px rgba(0,0,0,.06);
            text-align: center;
        }

        .brand {
            color: #087a3e;
            font-size: 24px;
            font-weight: 900;
        }

        .code {
            background: #eaf7ef;
            color: #087a3e;
            border: 2px dashed #8ac9a0;
            border-radius: 14px;
            padding: 22px 10px;
            font-size: 42px;
            font-weight: 900;
            letter-spacing: 10px;
            margin: 25px 0;
        }

        .btn-food {
            background: #087a3e;
            color: white;
            font-weight: 700;
        }

        .btn-food:hover {
            background: #056331;
            color: white;
        }
    </style>
</head>

<body>

<div class="code-card">

    <div class="brand mb-4">FOOD MAIL</div>

    <div class="mb-3 text-success fs-1">
        <i class="fas fa-shield-alt"></i>
    </div>

    <h3 class="fw-bold">Delivery Confirmation</h3>

    <p class="text-muted">
        Order:
        <strong><?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></strong>
    </p>

    <?php if ($otp !== null): ?>

        <p class="text-muted">
            Give this code to your Food Mail delivery person only when your order arrives.
        </p>

        <div class="code">
            <?= htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="alert alert-warning text-start small">
            <strong>Keep this code private.</strong>
            Do not share it before receiving your order. Your delivery person needs it to confirm successful delivery.
        </div>

        <p class="small text-muted">
            Expires:
            <?= htmlspecialchars(date('d M Y, H:i', strtotime($order['expires_at'])), ENT_QUOTES, 'UTF-8') ?>
        </p>

    <?php elseif ($error !== ''): ?>

        <div class="alert alert-warning">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php else: ?>

        <div class="alert alert-info">
            Your delivery confirmation code will appear here once your order is out for delivery.
        </div>

    <?php endif; ?>

    <a href="order_details.php?id=<?= (int)$orderId ?>" class="btn btn-food w-100 mt-3">
        Back to Order Tracking
    </a>

    <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
        My Account
    </a>

</div>

</body>
</html>
