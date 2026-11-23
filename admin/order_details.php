
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Order Details';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $_SESSION['order_error'] = 'Invalid order selected.';
    header('Location: orders.php');
    exit;
}

// Retrieve order and payment.
$orderStmt = $pdo->prepare("
    SELECT
        o.*,
        p.payment_method,
        p.transaction_code,
        p.amount AS payment_amount,
        p.status AS payment_status,
        p.confirmed_at
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE o.id = ?
    LIMIT 1
");

$orderStmt->execute([$id]);
$order = $orderStmt->fetch();

if (!$order) {
    $_SESSION['order_error'] = 'The selected order could not be found.';
    header('Location: orders.php');
    exit;
}

// Retrieve order items.
$itemsStmt = $pdo->prepare("
    SELECT
        id,
        product_id,
        product_name,
        quantity,
        unit_price,
        total_price
    FROM order_items
    WHERE order_id = ?
    ORDER BY id ASC
");

$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

// Retrieve order status history.
$historyStmt = $pdo->prepare("
    SELECT
        h.status,
        h.description,
        h.created_at,
        u.full_name AS updated_by_name
    FROM order_status_history h
    LEFT JOIN users u ON u.id = h.updated_by
    WHERE h.order_id = ?
    ORDER BY h.created_at DESC, h.id DESC
");

$historyStmt->execute([$id]);
$history = $historyStmt->fetchAll();

$success = $_SESSION['order_success'] ?? '';
$error = $_SESSION['order_error'] ?? '';

unset($_SESSION['order_success'], $_SESSION['order_error']);

$statusLabels = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'preparing' => 'Preparing',
    'ready' => 'Ready for dispatch',
    'out_for_delivery' => 'Out for delivery',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
];

$statusClasses = [
    'pending' => 'status-pending',
    'confirmed' => 'status-confirmed',
    'preparing' => 'status-preparing',
    'ready' => 'status-ready',
    'out_for_delivery' => 'status-out_for_delivery',
    'delivered' => 'status-delivered',
    'cancelled' => 'status-cancelled'
];

$transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['preparing', 'cancelled'],
    'preparing' => ['ready', 'cancelled'],
    'ready' => ['out_for_delivery', 'cancelled'],
    'out_for_delivery' => ['delivered'],
    'delivered' => [],
    'cancelled' => []
];

$currentStatus = $order['status'];
$nextStatuses = $transitions[$currentStatus] ?? [];

$paymentStatus = $order['payment_status'] ?? 'pending';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-heading">

    <div>
        <div class="welcome-label">ORDER MANAGEMENT</div>

        <h1>Order Details</h1>

        <p>
            Order:
            <strong><?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    </div>

    <a href="orders.php" class="btn-admin-secondary">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Orders
    </a>

</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="row g-4">

    <div class="col-lg-8">

        <div class="dashboard-card mb-4">

            <div class="card-heading">
                <h3>Order Information</h3>

                <span class="status-badge <?= $statusClasses[$currentStatus] ?? 'status-pending' ?>">
                    <?= htmlspecialchars($statusLabels[$currentStatus] ?? $currentStatus, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <div class="row g-3">

                <div class="col-md-6">
                    <small class="text-muted">Order Number</small>
                    <div class="fw-bold">
                        <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Date Placed</small>
                    <div class="fw-semibold">
                        <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Customer Name</small>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Customer Phone</small>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>

                <div class="col-12">
                    <small class="text-muted">Delivery Address</small>
                    <div class="fw-semibold">
                        <?= nl2br(htmlspecialchars($order['delivery_address'], ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                </div>

                <?php if (!empty($order['delivery_notes'])): ?>
                    <div class="col-12">
                        <small class="text-muted">Delivery Instructions</small>
                        <div>
                            <?= nl2br(htmlspecialchars($order['delivery_notes'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>

        <div class="dashboard-card mb-4">

            <div class="card-heading">
                <h3>Ordered Products</h3>

                <span class="text-muted small">
                    <?= count($items) ?> line items
                </span>
            </div>

            <div class="table-responsive">

                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($items as $item): ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </td>

                            <td>
                                <?= number_format((int)$item['quantity']) ?>
                            </td>

                            <td>
                                KSh <?= number_format((float)$item['unit_price'], 2) ?>
                            </td>

                            <td>
                                <strong>
                                    KSh <?= number_format((float)$item['total_price'], 2) ?>
                                </strong>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <div class="row justify-content-end mt-4">

                <div class="col-md-6">

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <strong>KSh <?= number_format((float)$order['subtotal'], 2) ?></strong>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Delivery Fee</span>
                        <strong>KSh <?= number_format((float)$order['delivery_fee'], 2) ?></strong>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <strong>Order Total</strong>
                        <strong class="text-success fs-5">
                            KSh <?= number_format((float)$order['total_amount'], 2) ?>
                        </strong>
                    </div>

                </div>

            </div>

        </div>

        <div class="dashboard-card">

            <div class="card-heading">
                <h3>Order Status History</h3>
            </div>

            <?php if (!$history): ?>

                <div class="empty-state">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <h4>No status history</h4>
                    <p>Status changes will appear here.</p>
                </div>

            <?php else: ?>

                <div class="order-history-list">

                    <?php foreach ($history as $record): ?>

                        <div class="d-flex gap-3 pb-4">

                            <div class="stat-icon green">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>

                            <div>
                                <strong>
                                    <?= htmlspecialchars(
                                        $statusLabels[$record['status']] ?? $record['status'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <p class="text-muted small mb-1">
                                    <?= htmlspecialchars($record['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <small class="text-muted">
                                    <?= date('d M Y, H:i', strtotime($record['created_at'])) ?>

                                    <?php if ($record['updated_by_name']): ?>
                                        &middot;
                                        <?= htmlspecialchars($record['updated_by_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </small>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

    <div class="col-lg-4">

        <div class="dashboard-card mb-4">

            <div class="card-heading">
                <h3>Payment Information</h3>
            </div>

            <div class="mb-3">
                <small class="text-muted">Payment Method</small>
                <div class="fw-semibold">
                    <?= htmlspecialchars(strtoupper($order['payment_method'] ?? 'MPESA'), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <div class="mb-3">
                <small class="text-muted">Transaction Code</small>
                <div class="fw-semibold">
                    <?= htmlspecialchars($order['transaction_code'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <div class="mb-3">
                <small class="text-muted">Amount Submitted</small>
                <div class="fw-semibold">
                    KSh <?= number_format((float)($order['payment_amount'] ?? 0), 2) ?>
                </div>
            </div>

            <div class="mb-3">
                <small class="text-muted">Payment Status</small>
                <div>
                    <?php if ($paymentStatus === 'confirmed'): ?>
                        <span class="status-badge status-confirmed">Confirmed</span>
                    <?php elseif ($paymentStatus === 'rejected'): ?>
                        <span class="status-badge status-rejected">Rejected</span>
                    <?php else: ?>
                        <span class="status-badge status-pending">Pending Verification</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($order['confirmed_at'])): ?>
                <div>
                    <small class="text-muted">Payment Confirmed At</small>
                    <div class="fw-semibold">
                        <?= date('d M Y, H:i', strtotime($order['confirmed_at'])) ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <div class="dashboard-card">

            <div class="card-heading">
                <h3>Update Order Status</h3>
            </div>

            <?php if (!$nextStatuses): ?>

                <div class="alert alert-info mb-0">
                    This order has reached a final status and cannot be changed through this workflow.
                </div>

            <?php else: ?>

                <?php if (
                    $currentStatus === 'confirmed' &&
                    $paymentStatus !== 'confirmed'
                ): ?>

                    <div class="alert alert-warning small">
                        Payment is not yet confirmed. Verify the M-Pesa transaction before moving this order to preparation.
                    </div>

                <?php endif; ?>

                <form action="order_update.php" method="POST">

                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">

                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                    <label for="new_status" class="form-label fw-semibold">
                        Select Next Status
                    </label>

                    <select name="new_status" id="new_status" class="form-select mb-3" required>

                        <option value="">Choose status</option>

                        <?php foreach ($nextStatuses as $nextStatus): ?>

                            <?php
                            if (
                                $nextStatus === 'preparing' &&
                                $paymentStatus !== 'confirmed'
                            ) {
                                continue;
                            }
                            ?>

                            <option value="<?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($statusLabels[$nextStatus], ENT_QUOTES, 'UTF-8') ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <label for="description" class="form-label fw-semibold">
                        Update Note
                    </label>

                    <textarea
                        name="description"
                        id="description"
                        class="form-control mb-3"
                        rows="3"
                        maxlength="500"
                        placeholder="Optional note about this update..."
                    ></textarea>

                    <button type="submit" class="btn-admin-primary w-100">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        Update Order
                    </button>

                </form>

                <p class="text-muted small mt-3 mb-0">
                    Order status changes are recorded in the history.
                </p>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
