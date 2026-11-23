
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

if (empty($_SESSION['delivery_id'])) {
    header('Location: login.php');
    exit;
}

$deliveryId = filter_var(
    $_SESSION['delivery_id'],
    FILTER_VALIDATE_INT
);

if (!$deliveryId || $deliveryId < 1) {
    $_SESSION = [];
    session_regenerate_id(true);

    header('Location: login.php');
    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            phone,
            email,
            profile_image,
            account_status
        FROM users
        WHERE id = :id
          AND role = 'delivery'
        LIMIT 1
    ");

    $stmt->execute(['id' => $deliveryId]);

    $deliveryStaff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$deliveryStaff ||
        $deliveryStaff['account_status'] !== 'active'
    ) {
        $_SESSION = [];
        session_regenerate_id(true);

        header('Location: login.php');
        exit;
    }

    $_SESSION['delivery_name'] = $deliveryStaff['full_name'];

} catch (Throwable $e) {

    error_log('Food Mail delivery authentication: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to verify your delivery account. Please try again later.');
}

$deliveryId = (int)$deliveryStaff['id'];

$deliveryProfileImage = '';

if (!empty($deliveryStaff['profile_image'])) {

    $filename = $deliveryStaff['profile_image'];

    if (basename($filename) === $filename) {

        $path = __DIR__ . '/../../uploads/delivery_staff/' . $filename;

        if (is_file($path)) {
            $deliveryProfileImage =
                '../../uploads/delivery_staff/' . rawurlencode($filename);
        }
    }
}

if (empty($_SESSION['delivery_csrf'])) {
    $_SESSION['delivery_csrf'] = bin2hex(random_bytes(32));
}
