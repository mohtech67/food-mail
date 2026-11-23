
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Order Management | Food Mail Admin';

/* ------------------------------------------------------------
   FILTERS
------------------------------------------------------------ */

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$paymentFilter = trim($_GET['payment_status'] ?? '');

$allowedStatuses = [
    'pending',
    'confirmed',
    'preparing',
    'ready',
    'out_for_delivery',
    'delivered',
    'cancelled'
];

$allowedPaymentStatuses = [
    'pending',
    'confirmed',
    'rejected'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if (!in_array($paymentFilter, $allowedPaymentStatuses, true)) {
    $paymentFilter = '';
}

/* ------------------------------------------------------------
   ORDER STATISTICS
------------------------------------------------------------ */

$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(status = 'pending'), 0) AS pending_orders,
        COALESCE(SUM(status = 'confirmed'), 0) AS confirmed_orders,
        COALESCE(SUM(status = 'preparing'), 0) AS preparing_orders,
        COALESCE(SUM(status = 'ready'), 0) AS ready_orders,
        COALESCE(SUM(status = 'out_for_delivery'), 0) AS out_for_delivery_orders,
        COALESCE(SUM(status = 'delivered'), 0) AS delivered_orders,
        COALESCE(SUM(status = 'cancelled'), 0) AS cancelled_orders
    FROM orders
");

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

/* ------------------------------------------------------------
   PAYMENT STATISTICS
------------------------------------------------------------ */

$paymentStatsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_payments,
        COALESCE(SUM(status = 'pending'), 0) AS pending_payments,
        COALESCE(SUM(status = 'confirmed'), 0) AS confirmed_payments,
        COALESCE(SUM(status = 'rejected'), 0) AS rejected_payments
    FROM payments
");

$paymentStats = $paymentStatsStmt->fetch(PDO::FETCH_ASSOC);

/* ------------------------------------------------------------
   BUILD ORDER QUERY
------------------------------------------------------------ */

$sql = "
    SELECT
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.delivery_address,
        o.subtotal,
        o.delivery_fee,
        o.total_amount,
        o.status,
        o.created_at,
        o.delivery_user_id,
        o.delivery_assigned_at,

        p.status AS payment_status,
        p.transaction_code,
        p.amount AS payment_amount,

        d.full_name AS delivery_staff_name,
        d.phone AS delivery_staff_phone,
        d.profile_image AS delivery_staff_image

    FROM orders o

    LEFT JOIN payments p
        ON p.order_id = o.id

    LEFT JOIN users d
        ON d.id = o.delivery_user_id
       AND d.role = 'delivery'

    WHERE 1 = 1
";

$params = [];

/* Search */
if ($search !== '') {

    $sql .= "
        AND (
            o.order_number LIKE :search_order
            OR o.customer_name LIKE :search_name
            OR o.customer_phone LIKE :search_phone
        )
    ";

    $term = '%' . $search . '%';

    $params['search_order'] = $term;
    $params['search_name'] = $term;
    $params['search_phone'] = $term;
}

/* Order status */
if ($statusFilter !== '') {
    $sql .= " AND o.status = :order_status";
    $params['order_status'] = $statusFilter;
}

/* Payment status */
if ($paymentFilter !== '') {
    $sql .= " AND p.status = :payment_status";
    $params['payment_status'] = $paymentFilter;
}

$sql .= "
    ORDER BY o.created_at DESC, o.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------------------------------------------
   FLASH MESSAGES
------------------------------------------------------------ */

$success = $_SESSION['order_success'] ?? '';
$error = $_SESSION['order_error'] ?? '';

$assignmentSuccess = $_SESSION['delivery_assignment_success'] ?? '';
$assignmentError = $_SESSION['delivery_assignment_error'] ?? '';

unset(
    $_SESSION['order_success'],
    $_SESSION['order_error'],
    $_SESSION['delivery_assignment_success'],
    $_SESSION['delivery_assignment_error']
);

/* ------------------------------------------------------------
   STATUS DISPLAY HELPERS
------------------------------------------------------------ */

$statusLabels = [
    'pending' => ['Pending', 'secondary'],
    'confirmed' => ['Confirmed', 'info'],
    'preparing' => ['Preparing', 'primary'],
    'ready' => ['Ready for Dispatch', 'primary'],
    'out_for_delivery' => ['Out for Delivery', 'warning'],
    'delivered' => ['Delivered', 'success'],
    'cancelled' => ['Cancelled', 'danger']
];

$paymentLabels = [
    'pending' => ['Awaiting Verification', 'warning'],
    'confirmed' => ['Confirmed', 'success'],
    'rejected' => ['Rejected', 'danger']
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
.order-stat-card {
    border: 0;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(20, 40, 30, .06);
    transition: transform .2s ease;
}

.order-stat-card:hover {
    transform: translateY(-3px);
}

.order-stat-icon {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #eaf7ef;
    color: #087a3e;
    font-size: 18px;
}

.order-table th {
    color: #718078;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    white-space: nowrap;
}

.order-table td {
    vertical-align: middle;
}

.order-number {
    color: #087a3e;
    font-weight: 800;
    text-decoration: none;
}

.order-number:hover {
    text-decoration: underline;
}

.order-customer-name {
    font-weight: 700;
    color: #26382e;
}

.order-staff-avatar {
    width: 35px;
    height: 35px;
    min-width: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #eaf7ef;
    color: #087a3e;
}

.order-staff-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.order-action-group {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 5px;
}

.order-filter-card {
    border: 0;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(20, 40, 30, .05);
}

.order-empty {
    padding: 55px 20px;
    text-align: center;
}

.order-empty i {
    font-size: 42px;
    color: #b5c7ba;
    margin-bottom: 15px;
}
</style>

<div class="container-fluid py-4">

    <!-- PAGE HEADER -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h2 class="fw-bold mb-1">Order Management</h2>

            <p class="text-muted mb-0">
                Manage customer orders, payment verification and deliveries.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">

            <a href="payments.php" class="btn btn-outline-success">
                <i class="fas fa-money-bill-wave me-2"></i>
                Manage Payments
            </a>

            <a href="deliveries.php" class="btn btn-success">
                <i class="fas fa-truck me-2"></i>
                Delivery Assignments
            </a>

        </div>

    </div>

    <!-- FLASH MESSAGES -->

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

    <?php if ($assignmentSuccess !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($assignmentSuccess, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($assignmentError !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($assignmentError, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ORDER STATISTICS -->

    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="card order-stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Total Orders</div>
                        <div class="fs-2 fw-bold">
                            <?= (int)$stats['total_orders'] ?>
                        </div>
                    </div>
                    <div class="order-stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card order-stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Pending Orders</div>
                        <div class="fs-2 fw-bold text-secondary">
                            <?= (int)$stats['pending_orders'] ?>
                        </div>
                    </div>
                    <div class="order-stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card order-stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Ready for Dispatch</div>
                        <div class="fs-2 fw-bold text-primary">
                            <?= (int)$stats['ready_orders'] ?>
                        </div>
                    </div>
                    <div class="order-stat-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card order-stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Delivered</div>
                        <div class="fs-2 fw-bold text-success">
                            <?= (int)$stats['delivered_orders'] ?>
                        </div>
                    </div>
                    <div class="order-stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ADDITIONAL ORDER COUNTS -->

    <div class="row g-3 mb-4">

        <div class="col-md-3">
            <div class="card border-0 bg-light h-100">
                <div class="card-body">
                    <small class="text-muted">Confirmed</small>
                    <div class="fw-bold fs-4">
                        <?= (int)$stats['confirmed_orders'] ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 bg-light h-100">
                <div class="card-body">
                    <small class="text-muted">Preparing</small>
                    <div class="fw-bold fs-4">
                        <?= (int)$stats['preparing_orders'] ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 bg-light h-100">
                <div class="card-body">
                    <small class="text-muted">Out for Delivery</small>
                    <div class="fw-bold fs-4 text-warning">
                        <?= (int)$stats['out_for_delivery_orders'] ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 bg-light h-100">
                <div class="card-body">
                    <small class="text-muted">Cancelled</small>
                    <div class="fw-bold fs-4 text-danger">
                        <?= (int)$stats['cancelled_orders'] ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- PAYMENT SUMMARY -->

    <div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">

        <div>
            <i class="fas fa-wallet text-success me-2"></i>
            <strong>Payment Overview</strong>
            <span class="text-muted ms-2">
                <?= (int)$paymentStats['pending_payments'] ?> pending,
                <?= (int)$paymentStats['confirmed_payments'] ?> confirmed,
                <?= (int)$paymentStats['rejected_payments'] ?> rejected
            </span>
        </div>

        <a href="payments.php" class="btn btn-sm btn-outline-success">
            Review Payments
            <i class="fas fa-arrow-right ms-1"></i>
        </a>

    </div>

    <!-- ORDER FILTERS -->

    <div class="card order-filter-card mb-4">

        <div class="card-body p-3">

            <form method="GET" class="row g-2">

                <div class="col-lg-5">

                    <input
                        type="search"
                        name="search"
                        class="form-control"
                        placeholder="Search order number, customer or phone..."
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    >

                </div>

                <div class="col-lg-3">

                    <select name="status" class="form-select">

                        <option value="">All Order Statuses</option>

                        <?php foreach ($allowedStatuses as $orderStatus): ?>

                            <option
                                value="<?= htmlspecialchars($orderStatus, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $statusFilter === $orderStatus ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    ucwords(str_replace('_', ' ', $orderStatus)),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-lg-2">

                    <select name="payment_status" class="form-select">

                        <option value="">All Payment Statuses</option>

                        <?php foreach ($allowedPaymentStatuses as $paymentStatus): ?>

                            <option
                                value="<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $paymentFilter === $paymentStatus ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    ucfirst($paymentStatus),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-lg-2 d-flex gap-2">

                    <button type="submit" class="btn btn-success flex-fill">
                        <i class="fas fa-search me-1"></i>
                        Filter
                    </button>

                    <a href="orders.php" class="btn btn-outline-secondary">
                        Reset
                    </a>

                </div>

            </form>

        </div>

    </div>

    <!-- ORDERS TABLE -->

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">

            <h5 class="fw-bold mb-0">Customer Orders</h5>

            <span class="badge bg-light text-dark">
                <?= count($orders) ?> order(s) displayed
            </span>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover order-table align-middle">

                    <thead>

                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Order Status</th>
                            <th>Delivery Staff</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!$orders): ?>

                        <tr>
                            <td colspan="8">

                                <div class="order-empty">

                                    <i class="fas fa-shopping-bag d-block"></i>

                                    <h5 class="fw-bold">No Orders Found</h5>

                                    <p class="text-muted mb-0">
                                        There are no orders matching your current search or filters.
                                    </p>

                                </div>

                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($orders as $order): ?>

                            <?php
                            $orderStatusInfo = $statusLabels[$order['status']]
                                ?? ['Unknown', 'secondary'];

                            $paymentStatusInfo = $paymentLabels[$order['payment_status'] ?? '']
                                ?? ['No Payment', 'secondary'];

                            $staffImage = $order['delivery_staff_image'] ?? '';

                            $staffImagePath = __DIR__ .
                                '/../uploads/delivery_staff/' .
                                $staffImage;

                            $hasStaffImage = (
                                $staffImage !== '' &&
                                basename($staffImage) === $staffImage &&
                                is_file($staffImagePath)
                            );

                            $staffImageUrl = $hasStaffImage
                                ? '../uploads/delivery_staff/' . rawurlencode($staffImage)
                                : '';

                            $canAssign = (
                                $order['status'] === 'ready' &&
                                ($order['payment_status'] ?? '') === 'confirmed'
                            );
                            ?>

                            <tr>

                                <!-- ORDER NUMBER -->

                                <td>

                                    <a
                                        href="order_details.php?id=<?= (int)$order['id'] ?>"
                                        class="order-number"
                                    >
                                        <?= htmlspecialchars(
                                            $order['order_number'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </a>

                                    <div class="small text-muted">
                                        ID: <?= (int)$order['id'] ?>
                                    </div>

                                </td>

                                <!-- CUSTOMER -->

                                <td>

                                    <div class="order-customer-name">
                                        <?= htmlspecialchars(
                                            $order['customer_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <small class="text-muted">
                                        <?= htmlspecialchars(
                                            $order['customer_phone'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </small>

                                </td>

                                <!-- AMOUNT -->

                                <td>

                                    <strong>
                                        KSh <?= number_format(
                                            (float)$order['total_amount'],
                                            2
                                        ) ?>
                                    </strong>

                                </td>

                                <!-- PAYMENT STATUS -->

                                <td>

                                    <span class="badge bg-<?= $paymentStatusInfo[1] ?>">
                                        <?= htmlspecialchars(
                                            $paymentStatusInfo[0],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                    <?php if (!empty($order['transaction_code'])): ?>
                                        <div class="small text-muted mt-1">
                                            <?= htmlspecialchars(
                                                $order['transaction_code'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>
                                    <?php endif; ?>

                                </td>

                                <!-- ORDER STATUS -->

                                <td>

                                    <span class="badge bg-<?= $orderStatusInfo[1] ?>">
                                        <?= htmlspecialchars(
                                            $orderStatusInfo[0],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                </td>

                                <!-- DELIVERY STAFF -->

                                <td>

                                    <?php if (!empty($order['delivery_user_id'])): ?>

                                        <div class="d-flex align-items-center gap-2">

                                            <div class="order-staff-avatar">

                                                <?php if ($hasStaffImage): ?>

                                                    <img
                                                        src="<?= htmlspecialchars(
                                                            $staffImageUrl,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        alt="Delivery staff"
                                                    >

                                                <?php else: ?>

                                                    <i class="fas fa-user"></i>

                                                <?php endif; ?>

                                            </div>

                                            <div>
                                                <div class="fw-semibold small">
                                                    <?= htmlspecialchars(
                                                        $order['delivery_staff_name'] ?? 'Unavailable',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </div>

                                                <small class="text-muted">
                                                    Assigned
                                                </small>
                                            </div>

                                        </div>

                                    <?php else: ?>

                                        <span class="text-muted small">
                                            Not assigned
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- DATE -->

                                <td>

                                    <div class="small">
                                        <?= htmlspecialchars(
                                            date('d M Y', strtotime($order['created_at'])),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <small class="text-muted">
                                        <?= htmlspecialchars(
                                            date('H:i', strtotime($order['created_at'])),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </small>

                                </td>

                                <!-- ACTIONS -->

                                <td>

                                    <div class="order-action-group">

                                        <a
                                            href="order_details.php?id=<?= (int)$order['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="View order details"
                                        >
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if ($canAssign): ?>

                                            <a
                                                href="delivery_assign.php?id=<?= (int)$order['id'] ?>"
                                                class="btn btn-sm btn-success"
                                                title="<?= !empty($order['delivery_user_id']) ? 'Change delivery staff' : 'Assign delivery staff' ?>"
                                            >
                                                <i class="fas fa-truck me-1"></i>
                                                <?= !empty($order['delivery_user_id']) ? 'Reassign' : 'Assign' ?>
                                            </a>

                                        <?php elseif (!empty($order['delivery_user_id'])): ?>

                                            <a
                                                href="delivery_details.php?id=<?= (int)$order['id'] ?>"
                                                class="btn btn-sm btn-outline-success"
                                                title="View delivery details"
                                            >
                                                <i class="fas fa-truck"></i>
                                            </a>

                                        <?php elseif ($order['status'] === 'ready'): ?>

                                            <span
                                                class="badge bg-warning text-dark"
                                                title="Payment must be confirmed before assigning"
                                            >
                                                Payment Pending
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
