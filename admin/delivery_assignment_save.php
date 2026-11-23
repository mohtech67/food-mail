
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function assignmentRedirect(
    string $message,
    string $location = 'deliveries.php',
    bool $success = false
): void {
    if ($success) {
        $_SESSION['delivery_assignment_success'] = $message;
    } else {
        $_SESSION['delivery_assignment_error'] = $message;
    }

    header('Location: ' . $location);
    exit;
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

$orderId = filter_var(
    $_POST['order_id'] ?? '',
    FILTER_VALIDATE_INT
);

$staffId = filter_var(
    $_POST['delivery_user_id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$orderId || $orderId < 1 || !$staffId || $staffId < 1) {
    assignmentRedirect('Please select a valid order and delivery staff member.');
}

try {

    $pdo->beginTransaction();

    // Lock the order while assigning it.
    $orderStmt = $pdo->prepare("
        SELECT id, order_number, status, delivery_user_id
        FROM orders
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ");

    $orderStmt->execute(['id' => $orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        assignmentRedirect('The selected order could not be found.');
    }

    if ($order['status'] !== 'ready') {
        $pdo->rollBack();

        assignmentRedirect(
            'Only orders marked Ready can be assigned.',
            'order_details.php?id=' . $orderId
        );
    }

    // Verify that the payment is confirmed.
    $paymentStmt = $pdo->prepare("
        SELECT status
        FROM payments
        WHERE order_id = :order_id
        LIMIT 1
        FOR UPDATE
    ");

    $paymentStmt->execute(['order_id' => $orderId]);
    $paymentStatus = $paymentStmt->fetchColumn();

    if ($paymentStatus !== 'confirmed') {
        $pdo->rollBack();

        assignmentRedirect(
            'Payment must be confirmed before assigning this order.',
            'order_details.php?id=' . $orderId
        );
    }

    // Verify active delivery staff.
    $staffStmt = $pdo->prepare("
        SELECT id, full_name
        FROM users
        WHERE id = :id
          AND role = 'delivery'
          AND account_status = 'active'
        LIMIT 1
    ");

    $staffStmt->execute(['id' => $staffId]);
    $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        $pdo->rollBack();
        assignmentRedirect(
            'The selected delivery staff account is unavailable or suspended.',
            'delivery_assign.php?id=' . $orderId
        );
    }

    // Assign or reassign the order.
    $updateStmt = $pdo->prepare("
        UPDATE orders
        SET
            delivery_user_id = :delivery_user_id,
            delivery_assigned_at = CURRENT_TIMESTAMP
        WHERE id = :order_id
          AND status = 'ready'
    ");

    $updateStmt->execute([
        'delivery_user_id' => $staffId,
        'order_id' => $orderId
    ]);

    if ($updateStmt->rowCount() !== 1) {
        $pdo->rollBack();

        assignmentRedirect(
            'The order assignment could not be saved. Refresh and try again.',
            'delivery_assign.php?id=' . $orderId
        );
    }

    // Record the assignment in order history.
    $adminId = filter_var(
        $_SESSION['admin_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $adminId = ($adminId && $adminId > 0) ? $adminId : null;

    $historyStmt = $pdo->prepare("
        INSERT INTO order_status_history (
            order_id,
            status,
            description,
            updated_by
        ) VALUES (
            :order_id,
            'ready',
            :description,
            :updated_by
        )
    ");

    $historyStmt->execute([
        'order_id' => $orderId,
        'description' =>
            'Delivery assigned to ' . $staff['full_name'] . '.',
        'updated_by' => $adminId
    ]);

    $pdo->commit();

    assignmentRedirect(
        'Order ' . $order['order_number'] .
        ' was successfully assigned to ' . $staff['full_name'] . '.',
        'delivery_details.php?id=' . $orderId,
        true
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Food Mail delivery assignment error: ' . $e->getMessage()
    );

    assignmentRedirect(
        'Unable to save the delivery assignment. Please try again.'
    );
}
