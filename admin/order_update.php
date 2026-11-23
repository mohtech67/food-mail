
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

function redirectOrderError($message, $orderId = null)
{
    $_SESSION['order_error'] = $message;

    if ($orderId) {
        header('Location: order_details.php?id=' . (int)$orderId);
    } else {
        header('Location: orders.php');
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}

// Validate CSRF token.
$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['admin_csrf']) ||
    !is_string($csrf) ||
    !hash_equals($_SESSION['admin_csrf'], $csrf)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh the page and try again.');
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$newStatus = $_POST['new_status'] ?? '';
$description = trim($_POST['description'] ?? '');

if (!$orderId || $orderId < 1) {
    redirectOrderError('Invalid order selected.');
}

if (strlen($description) > 500) {
    redirectOrderError('The update note must not exceed 500 characters.', $orderId);
}

// Define permitted transitions.
$transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['preparing', 'cancelled'],
    'preparing' => ['ready', 'cancelled'],
    'ready' => ['out_for_delivery', 'cancelled'],
    'out_for_delivery' => ['delivered'],
    'delivered' => [],
    'cancelled' => []
];

$statusLabels = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'preparing' => 'Preparing',
    'ready' => 'Ready for dispatch',
    'out_for_delivery' => 'Out for delivery',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
];

if (!array_key_exists($newStatus, $statusLabels)) {
    redirectOrderError('Invalid order status selected.', $orderId);
}

try {

    $pdo->beginTransaction();

    // Lock the order so simultaneous updates cannot process it twice.
    $orderStmt = $pdo->prepare("
        SELECT id, order_number, status
        FROM orders
        WHERE id = ?
        FOR UPDATE
    ");

    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        redirectOrderError('The selected order does not exist.');
    }

    $oldStatus = $order['status'];

    // Validate the transition against the current database status.
    if (!isset($transitions[$oldStatus]) ||
        !in_array($newStatus, $transitions[$oldStatus], true)
    ) {
        $pdo->rollBack();

        redirectOrderError(
            'This order cannot be moved from ' .
            ($statusLabels[$oldStatus] ?? $oldStatus) .
            ' to ' .
            ($statusLabels[$newStatus] ?? $newStatus) .
            '.',
            $orderId
        );
    }

    // Require verified payment before preparation begins.
    if ($newStatus === 'preparing') {

        $paymentStmt = $pdo->prepare("
            SELECT status
            FROM payments
            WHERE order_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $paymentStmt->execute([$orderId]);
        $paymentStatus = $paymentStmt->fetchColumn();

        if ($paymentStatus !== 'confirmed') {
            $pdo->rollBack();

            redirectOrderError(
                'Payment must be verified before the order can enter preparation.',
                $orderId
            );
        }
    }

    // Restore stock when cancelling an order that has not been dispatched.
    if ($newStatus === 'cancelled') {

        $itemsStmt = $pdo->prepare("
            SELECT product_id, quantity
            FROM order_items
            WHERE order_id = ?
            FOR UPDATE
        ");

        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll();

        $stockStmt = $pdo->prepare("
            UPDATE products
            SET
                stock_quantity = stock_quantity + :quantity,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :product_id
        ");

        foreach ($items as $item) {

            if ($item['product_id'] === null) {
                continue;
            }

            $quantity = (int)$item['quantity'];
            $productId = (int)$item['product_id'];

            if ($quantity < 1 || $productId < 1) {
                throw new RuntimeException(
                    'Invalid order item encountered while restoring stock.'
                );
            }

            $stockStmt->execute([
                'quantity' => $quantity,
                'product_id' => $productId
            ]);

            if ($stockStmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'A product could not be found while restoring stock.'
                );
            }
        }
    }

    // Update order.
    $updateStmt = $pdo->prepare("
        UPDATE orders
        SET
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        'status' => $newStatus,
        'id' => $orderId
    ]);

    // Prepare status history description.
    if ($description === '') {
        $description = 'Order status changed from ' .
            ($statusLabels[$oldStatus] ?? $oldStatus) .
            ' to ' .
            ($statusLabels[$newStatus] ?? $newStatus) .
            '.';
    }

    // Record status history.
    $historyStmt = $pdo->prepare("
        INSERT INTO order_status_history (
            order_id,
            status,
            description,
            updated_by
        )
        VALUES (?, ?, ?, ?)
    ");

    $historyStmt->execute([
        $orderId,
        $newStatus,
        $description,
        (int)$_SESSION['admin_id']
    ]);

    $pdo->commit();

    if ($newStatus === 'cancelled') {
        $_SESSION['order_success'] =
            'Order cancelled successfully. Product stock has been restored.';
    } elseif ($newStatus === 'delivered') {
        $_SESSION['order_success'] =
            'Order marked as successfully delivered.';
    } else {
        $_SESSION['order_success'] =
            'Order status updated to ' .
            ($statusLabels[$newStatus] ?? $newStatus) .
            ' successfully.';
    }

    header('Location: order_details.php?id=' . $orderId);
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Food Mail order update error: ' . $e->getMessage());

    redirectOrderError(
        'Unable to update this order. Please check the server error log.',
        $orderId
    );
}
