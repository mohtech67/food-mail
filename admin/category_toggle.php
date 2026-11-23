
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: categories.php');
    exit;
}

// CSRF validation.
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
    $_SESSION['category_error'] = 'Invalid category selected.';
    header('Location: categories.php');
    exit;
}

try {

    // Lock the category row while checking and updating its status.
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, is_active
        FROM categories
        WHERE id = ?
        FOR UPDATE
    ");

    $stmt->execute([$id]);

    $category = $stmt->fetch();

    if (!$category) {
        $pdo->rollBack();

        $_SESSION['category_error'] = 'Category not found.';
        header('Location: categories.php');
        exit;
    }

    $newStatus = (int)$category['is_active'] === 1 ? 0 : 1;

    $update = $pdo->prepare("
        UPDATE categories
        SET is_active = ?
        WHERE id = ?
    ");

    $update->execute([$newStatus, $id]);

    $pdo->commit();

    $_SESSION['category_success'] = $newStatus === 1
        ? 'Category activated successfully.'
        : 'Category deactivated successfully.';

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Food Mail category status error: ' . $e->getMessage());

    $_SESSION['category_error'] = 'Unable to update category status.';
}

header('Location: categories.php');
exit;
