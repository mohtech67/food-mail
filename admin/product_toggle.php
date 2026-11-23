
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['admin_csrf']) ||
    !is_string($csrf) ||
    !hash_equals($_SESSION['admin_csrf'], $csrf)
) {
    http_response_code(403);
    exit('Invalid security token.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $_SESSION['product_error'] = 'Invalid product selected.';
    header('Location: products.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE products
        SET is_available = CASE
            WHEN is_available = 1 THEN 0
            ELSE 1
        END,
        updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['product_success'] = 'Product availability updated successfully.';
    } else {
        $_SESSION['product_error'] = 'Product was not found or its availability was unchanged.';
    }

} catch (PDOException $e) {
    error_log('Food Mail product status error: ' . $e->getMessage());
    $_SESSION['product_error'] = 'Unable to update product availability.';
}

header('Location: products.php');
exit;
