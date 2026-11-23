
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    $_SESSION['delivery_assignment_error'] = 'Invalid delivery order.';
    header('Location: deliveries.php');
    exit;
}

/* ------------------------------------------------------------
   ORDER AND DELIVERY STAFF DETAILS
------------------------------------------------------------ */

$stmt = $pdo->prepare("
    SELECT
        o.*,
        u.full_name AS delivery_staff_name,
        u.phone AS delivery_staff_phone,
        u.email AS delivery_staff_email,
        u.profile_image AS delivery_staff_image,
        u.account_status AS delivery_staff_status,
        p.payment_method,
        p.transaction_code,
        p.amount AS payment_amount,
        p.status AS payment_status,
        p.confirmed_at
    FROM orders o
    LEFT JOIN users u
        ON u.id = o.delivery_user_id
       AND u.role = 'delivery'
    LEFT JOIN payments p
        ON p.order_id = o.id
    WHERE o.id = :id
    LIMIT 1
");

$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['delivery_assignment_error'] = 'Delivery order not found.';
    header('Location: deliveries.php');
    exit;
}

/* ------------------------------------------------------------
   ORDER ITEMS
------------------------------------------------------------ */

$itemStmt = $pdo->prepare("
    SELECT
        product_name,
        quantity,
        unit_price,
        total_price
    FROM order_items
    WHERE order_id = :order_id
    ORDER BY id ASC
");

$itemStmt->execute(['order_id' => $orderId]);
$orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------------------------------------------
   ORDER HISTORY
------------------------------------------------------------ */

$historyStmt = $pdo->prepare("
    SELECT
        h.status,
        h.description,
        h.created_at,
        u.full_name AS updated_by_name
    FROM order_status_history h
    LEFT JOIN users u ON u.id = h.updated_by
    WHERE h.order_id = :order_id
    ORDER BY h.created_at DESC, h.id DESC
");

$historyStmt->execute(['order_id' => $orderId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$success = $_SESSION['delivery_assignment_success'] ?? '';
$error = $_SESSION['delivery_assignment_error'] ?? '';

unset(
    $_SESSION['delivery_assignment_success'],
    $_SESSION['delivery_assignment_error']
);

$pageTitle = 'Delivery Details | Food Mail Admin';

require_once __DIR__ . '/includes/header.php';

$staffImage = $order['delivery_staff_image'] ?? '';

$staffImagePath = __DIR__ . '/../uploads/delivery_staff/' . $staffImage;

$hasStaffImage = (
    $staffImage !== '' &&
    basename($staffImage) === $staffImage &&
    is_file($staffImagePath)
);

$staffImageUrl = $hasStaffImage
    ? '../uploads/delivery_staff/' . rawurlencode($staffImage)
    : '';

$statusLabels = [
    'pending' => ['Pending', 'secondary'],
    'confirmed' => ['Confirmed', 'info'],
    'preparing' => ['Preparing', 'primary'],
    'ready' => ['Ready', 'primary'],
    'out_for_delivery' => ['Out for Delivery', 'warning'],
    'delivered' => ['Delivered', 'success'],
    'cancelled' => ['Cancelled', 'danger']
];

$statusInfo = $statusLabels[$order['status']]
    ?? [ucwords(str_replace('_', ' ', $order['status'])), 'secondary'];
?>

<style>
.delivery-detail-card {
    border: 0;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(20, 40, 30, .06);
}

.delivery-detail-heading {
    font-weight: 800;
    color: #183126;
}

.delivery-detail-label {
    color: #758179;
    font-size: 12px;
    display: block;
    margin-bottom: 4px;
}

.delivery-detail-value {
    font-weight: 650;
    color: #24372d;
    overflow-wrap: anywhere;
}

.delivery-detail-avatar {
    width: 92px;
    height: 92px;
    min-width: 92px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 50%;
    background: #eaf7ef;
    color: #087a3e;
    font-size: 34px;
    border: 4px solid #dceee2;
}

.delivery-detail-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.delivery-history-item {
    border-left: 3px solid #b8dec4;
    padding: 0 0 20px 17px;
    margin-left: 7px;
    position: relative;
}

.delivery-history-item:last-child {
    padding-bottom: 0;
}

.delivery-history-item::before {
    content: "";
    position: absolute;
    left: -7px;
    top: 2px;
    width: 11px;
    height: 11px;
    background: #087a3e;
    border-radius: 50%;
}
</style>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <a href="deliveries.php" class="text-decoration-none small">
                <i class="fas fa-arrow-left me-1"></i>
                Back to Delivery Assignments
            </a>

            <h2 class="fw-bold mt-3 mb-1">Delivery Details</h2>

            <p class="text-muted mb-0">
                Order <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <?php if ($order['status'] === 'ready'): ?>

            <a
                href="delivery_assign.php?id=<?= (int)$order['id'] ?>"
                class="btn btn-success"
            >
                <i class="fas fa-user-plus me-2"></i>

                <?= $order['delivery_user_id'] ? 'Change Delivery Staff' : 'Assign Delivery Staff' ?>
            </a>

        <?php endif; ?>

    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-xl-8">

            <div class="card delivery-detail-card mb-4">

                <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">

                    <h5 class="delivery-detail-heading mb-0">
                        <i class="fas fa-receipt text-success me-2"></i>
                        Order Information
                    </h5>

                    <span class="badge bg-<?= $statusInfo[1] ?>">
                        <?= htmlspecialchars($statusInfo[0], ENT_QUOTES, 'UTF-8') ?>
                    </span>

                </div>

                <div class="card-body p-4">

                    <div class="row g-4 mb-4">

                        <div class="col-md-6">
                            <span class="delivery-detail-label">Order Number</span>
                            <div class="delivery-detail-value text-success">
                                <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <span class="delivery-detail-label">Order Date</span>
                            <div class="delivery-detail-value">
                                <?= htmlspecialchars(date('d M Y, H:i', strtotime($order['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <span class="delivery-detail-label">Customer Name</span>
                            <div class="delivery-detail-value">
                                <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <span class="delivery-detail-label">Customer Phone</span>
                            <div class="delivery-detail-value">
                                <?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <span class="delivery-detail-label">Delivery Address</span>
                            <div class="delivery-detail-value">
                                <?= nl2br(htmlspecialchars($order['delivery_address'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        </div>

                        <?php if (!empty($order['delivery_notes'])): ?>
                            <div class="col-12">
                                <span class="delivery-detail-label">Customer Instructions</span>
                                <div class="delivery-detail-value">
                                    <?= nl2br(htmlspecialchars($order['delivery_notes'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                    <hr>

                    <h6 class="fw-bold mb-3">Ordered Products</h6>

                    <div class="table-responsive">

                        <table class="table align-middle">

                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php foreach ($orderItems as $item): ?>

                                <tr>
                                    <td>
                                        <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td><?= (int)$item['quantity'] ?></td>

                                    <td>
                                        KSh <?= number_format((float)$item['unit_price'], 2) ?>
                                    </td>

                                    <td class="text-end fw-bold">
                                        KSh <?= number_format((float)$item['total_price'], 2) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <hr>

                    <div class="row justify-content-end">

                        <div class="col-md-6">

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span>
                                    KSh <?= number_format((float)$order['subtotal'], 2) ?>
                                </span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Delivery Fee</span>
                                <span>
                                    KSh <?= number_format((float)$order['delivery_fee'], 2) ?>
                                </span>
                            </div>

                            <div class="d-flex justify-content-between border-top pt-3">
                                <strong>Order Total</strong>
                                <strong class="text-success fs-5">
                                    KSh <?= number_format((float)$order['total_amount'], 2) ?>
                                </strong>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="card delivery-detail-card">

                <div class="card-header bg-white py-3">
                    <h5 class="delivery-detail-heading mb-0">
                        <i class="fas fa-history text-success me-2"></i>
                        Order and Assignment History
                    </h5>
                </div>

                <div class="card-body p-4">

                    <?php if (!$history): ?>

                        <p class="text-muted mb-0">No order history is available.</p>

                    <?php else: ?>

                        <?php foreach ($history as $entry): ?>

                            <div class="delivery-history-item">

                                <div class="fw-bold">
                                    <?= htmlspecialchars(
                                        ucwords(str_replace('_', ' ', $entry['status'])),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                                <div class="text-muted small mt-1">
                                    <?= htmlspecialchars($entry['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="text-muted small mt-1">
                                    <?= htmlspecialchars(date('d M Y, H:i', strtotime($entry['created_at'])), ENT_QUOTES, 'UTF-8') ?>

                                    <?php if (!empty($entry['updated_by_name'])): ?>
                                        &middot; <?= htmlspecialchars($entry['updated_by_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <div class="col-xl-4">

            <div class="card delivery-detail-card mb-4">

                <div class="card-header bg-white py-3">
                    <h5 class="delivery-detail-heading mb-0">
                        <i class="fas fa-truck text-success me-2"></i>
                        Assigned Delivery Staff
                    </h5>
                </div>

                <div class="card-body p-4">

                    <?php if ($order['delivery_user_id']): ?>

                        <div class="text-center mb-4">

                            <div class="delivery-detail-avatar mx-auto mb-3">

                                <?php if ($hasStaffImage): ?>

                                    <img
                                        src="<?= htmlspecialchars($staffImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="Delivery staff profile"
                                    >

                                <?php else: ?>

                                    <i class="fas fa-user"></i>

                                <?php endif; ?>

                            </div>

                            <h5 class="fw-bold mb-1">
                                <?= htmlspecialchars($order['delivery_staff_name'] ?? 'Staff account unavailable', ENT_QUOTES, 'UTF-8') ?>
                            </h5>

                            <span class="badge bg-<?= ($order['delivery_staff_status'] ?? '') === 'active' ? 'success' : 'danger' ?>">
                                <?= htmlspecialchars(ucfirst($order['delivery_staff_status'] ?? 'unavailable'), ENT_QUOTES, 'UTF-8') ?>
                            </span>

                        </div>

                        <div class="mb-3">
                            <span class="delivery-detail-label">Phone Number</span>
                            <div class="delivery-detail-value">
                                <?= htmlspecialchars($order['delivery_staff_phone'] ?? 'Not available', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="delivery-detail-label">Email</span>
                            <div class="delivery-detail-value">
                                <?= htmlspecialchars($order['delivery_staff_email'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div>
                            <span class="delivery-detail-label">Assignment Date</span>
                            <div class="delivery-detail-value">
                                <?= $order['delivery_assigned_at']
                                    ? htmlspecialchars(date('d M Y, H:i', strtotime($order['delivery_assigned_at'])), ENT_QUOTES, 'UTF-8')
                                    : 'Not recorded' ?>
                            </div>
                        </div>

                        <?php if ($order['status'] === 'ready'): ?>
                            <a
                                href="delivery_assign.php?id=<?= (int)$order['id'] ?>"
                                class="btn btn-outline-success w-100 mt-4"
                            >
                                <i class="fas fa-exchange-alt me-2"></i>
                                Reassign Order
                            </a>
                        <?php endif; ?>

                    <?php else: ?>

                        <div class="text-center py-4">
                            <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                            <h6 class="fw-bold">No Delivery Staff Assigned</h6>
                            <p class="text-muted small">
                                This order has not yet been assigned to a delivery staff member.
                            </p>

                            <?php if ($order['status'] === 'ready'): ?>
                                <a
                                    href="delivery_assign.php?id=<?= (int)$order['id'] ?>"
                                    class="btn btn-success"
                                >
                                    <i class="fas fa-user-plus me-2"></i>
                                    Assign Staff
                                </a>
                            <?php endif; ?>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card delivery-detail-card">

                <div class="card-header bg-white py-3">
                    <h5 class="delivery-detail-heading mb-0">
                        <i class="fas fa-money-bill-wave text-success me-2"></i>
                        Payment Information
                    </h5>
                </div>

                <div class="card-body p-4">

                    <div class="mb-3">
                        <span class="delivery-detail-label">Payment Method</span>
                        <div class="delivery-detail-value">
                            <?= htmlspecialchars(strtoupper($order['payment_method'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <span class="delivery-detail-label">Transaction Code</span>
                        <div class="delivery-detail-value">
                            <?= htmlspecialchars($order['transaction_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <span class="delivery-detail-label">Payment Status</span>
                        <div>
                            <?php if (($order['payment_status'] ?? '') === 'confirmed'): ?>
                                <span class="badge bg-success">Confirmed</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">
                                    <?= htmlspecialchars(ucfirst($order['payment_status'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <span class="delivery-detail-label">Amount Paid</span>
                        <div class="delivery-detail-value text-success">
                            KSh <?= number_format((float)($order['payment_amount'] ?? 0), 2) ?>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
