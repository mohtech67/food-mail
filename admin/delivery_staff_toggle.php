
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

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

$staffId = filter_var(
    $_POST['staff_id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$staffId || $staffId < 1) {
    $_SESSION['delivery_staff_error'] = 'Invalid delivery staff account.';
    header('Location: delivery_staff.php');
    exit;
}

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, account_status
        FROM users
        WHERE id = :id
          AND role = 'delivery'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute(['id' => $staffId]);

    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        $pdo->rollBack();

        $_SESSION['delivery_staff_error'] =
            'Delivery staff account not found.';

        header('Location: delivery_staff.php');
        exit;
    }

    $newStatus = $staff['account_status'] === 'active'
        ? 'suspended'
        : 'active';

    $updateStmt = $pdo->prepare("
        UPDATE users
        SET
            account_status = :account_status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND role = 'delivery'
    ");

    $updateStmt->execute([
        'account_status' => $newStatus,
        'id' => $staffId
    ]);

    $pdo->commit();

    $_SESSION['delivery_staff_success'] =
        $newStatus === 'active'
            ? 'Delivery staff account activated successfully.'
            : 'Delivery staff account suspended successfully.';

    header('Location: delivery_staff.php');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Food Mail delivery staff status error: ' . $e->getMessage());

    $_SESSION['delivery_staff_error'] =
        'Unable to update the delivery staff account. Please try again.';

    header('Location: delivery_staff.php');
    exit;
}
