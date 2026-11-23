
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/delivery.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$token = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['delivery_csrf'] ?? '';

if (
    !is_string($token) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $token)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh and try again.');
}

$orderId = filter_var($_POST['order_id'] ?? '', FILTER_VALIDATE_INT);
$newStatus = $_POST['new_status'] ?? '';

if (!$orderId || $orderId < 1) {
    http_response_code(400);
    exit('Invalid order.');
}

if (!in_array($newStatus, ['out_for_delivery', 'delivered'], true)) {
    http_response_code(400);
    exit('Invalid delivery status.');
}

$otpInput = trim((string)($_POST['delivery_otp'] ?? ''));

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, order_number, status
        FROM orders
        WHERE id = :order_id
          AND delivery_user_id = :delivery_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        'order_id' => $orderId,
        'delivery_id' => $deliveryId
    ]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException('This order is not assigned to your account.');
    }

    $currentStatus = $order['status'];

    /*
    |--------------------------------------------------------------------------
    | START DELIVERY: GENERATE CUSTOMER OTP
    |--------------------------------------------------------------------------
    */

    if ($currentStatus === 'ready' && $newStatus === 'out_for_delivery') {

        $otp = (string)random_int(100000, 999999);

        $nonce = random_bytes(12);
        $tag = '';

        $encrypted = openssl_encrypt(
            $otp,
            'aes-256-gcm',
            FOOD_MAIL_OTP_KEY,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'food-mail-delivery-' . $orderId
        );

        if ($encrypted === false) {
            throw new RuntimeException('Unable to generate delivery confirmation code.');
        }

        $storedCode = base64_encode($nonce . $tag . $encrypted);

        $otpStmt = $pdo->prepare("
            INSERT INTO delivery_otps (
                order_id,
                code_encrypted,
                expires_at,
                attempts,
                used_at
            ) VALUES (
                :order_id,
                :code_encrypted,
                DATE_ADD(NOW(), INTERVAL 24 HOUR),
                0,
                NULL
            )
            ON DUPLICATE KEY UPDATE
                code_encrypted = VALUES(code_encrypted),
                expires_at = VALUES(expires_at),
                attempts = 0,
                used_at = NULL
        ");

        $otpStmt->execute([
            'order_id' => $orderId,
            'code_encrypted' => $storedCode
        ]);

        $description =
            'Delivery started. A six-digit confirmation code was generated for the customer.';

    /*
    |--------------------------------------------------------------------------
    | COMPLETE DELIVERY: VERIFY CUSTOMER OTP
    |--------------------------------------------------------------------------
    */

    } elseif ($currentStatus === 'out_for_delivery' && $newStatus === 'delivered') {

        if (!preg_match('/^[0-9]{6}$/', $otpInput)) {
            throw new RuntimeException('Enter the customer six-digit delivery code.');
        }

        $otpStmt = $pdo->prepare("
            SELECT
                code_encrypted,
                expires_at,
                attempts,
                used_at
            FROM delivery_otps
            WHERE order_id = :order_id
            LIMIT 1
            FOR UPDATE
        ");

        $otpStmt->execute(['order_id' => $orderId]);

        $otpRecord = $otpStmt->fetch(PDO::FETCH_ASSOC);

        if (!$otpRecord) {
            throw new RuntimeException('No delivery confirmation code exists for this order.');
        }

        if (!empty($otpRecord['used_at'])) {
            throw new RuntimeException('This delivery confirmation code has already been used.');
        }

        if (strtotime($otpRecord['expires_at']) < time()) {
            throw new RuntimeException('The delivery confirmation code has expired. Contact the administrator.');
        }

        if ((int)$otpRecord['attempts'] >= 5) {
            throw new RuntimeException('Too many incorrect code attempts. Contact the administrator.');
        }

        $decoded = base64_decode($otpRecord['code_encrypted'], true);

        if ($decoded === false || strlen($decoded) < 29) {
            throw new RuntimeException('The delivery confirmation code is invalid.');
        }

        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $actualOtp = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            FOOD_MAIL_OTP_KEY,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'food-mail-delivery-' . $orderId
        );

        if ($actualOtp === false) {
            throw new RuntimeException('Unable to verify the delivery confirmation code.');
        }

        if (!hash_equals($actualOtp, $otpInput)) {

            $failedStmt = $pdo->prepare("
                UPDATE delivery_otps
                SET attempts = attempts + 1
                WHERE order_id = :order_id
            ");

            $failedStmt->execute(['order_id' => $orderId]);

            $pdo->commit();

            $_SESSION['delivery_message'] =
                'Incorrect delivery code. Please ask the customer to check their code.';

            $_SESSION['delivery_message_type'] = 'danger';

            header('Location: order_details.php?id=' . $orderId);
            exit;
        }

        $usedStmt = $pdo->prepare("
            UPDATE delivery_otps
            SET used_at = NOW()
            WHERE order_id = :order_id
        ");

        $usedStmt->execute(['order_id' => $orderId]);

        $description =
            'Delivery completed after successful customer OTP verification.';

    } else {

        throw new RuntimeException('This delivery status change is not permitted.');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER STATUS
    |--------------------------------------------------------------------------
    */

    $updateStmt = $pdo->prepare("
        UPDATE orders
        SET
            status = :new_status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :order_id
          AND delivery_user_id = :delivery_id
          AND status = :old_status
    ");

    $updateStmt->execute([
        'new_status' => $newStatus,
        'order_id' => $orderId,
        'delivery_id' => $deliveryId,
        'old_status' => $currentStatus
    ]);

    if ($updateStmt->rowCount() !== 1) {
        throw new RuntimeException('The order status could not be updated.');
    }

    $historyStmt = $pdo->prepare("
        INSERT INTO order_status_history (
            order_id,
            status,
            description,
            updated_by
        ) VALUES (
            :order_id,
            :status,
            :description,
            :updated_by
        )
    ");

    $historyStmt->execute([
        'order_id' => $orderId,
        'status' => $newStatus,
        'description' => $description,
        'updated_by' => $deliveryId
    ]);

    $pdo->commit();

    $_SESSION['delivery_message'] =
        $newStatus === 'delivered'
            ? 'Order successfully delivered and customer code verified.'
            : 'Delivery started. The customer can now view their confirmation code.';

    $_SESSION['delivery_message_type'] = 'success';

    header('Location: order_details.php?id=' . $orderId);
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Food Mail delivery OTP: ' . $e->getMessage());

    $_SESSION['delivery_message'] = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Unable to update delivery status. Please try again.';

    $_SESSION['delivery_message_type'] = 'danger';

    header('Location: order_details.php?id=' . $orderId);
    exit;
}
