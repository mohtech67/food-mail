
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Delivery Assignments | Food Mail Admin';

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatuses = [
    'ready',
    'out_for_delivery',
    'delivered',
    'cancelled'
];

$sql = "
    SELECT
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.delivery_address,
        o.total_amount,
        o.status,
        o.created_at,
        o.delivery_assigned_at,
        o.delivery_user_id,
        u.full_name AS delivery_staff_name,
        u.phone AS delivery_staff_phone,
        u.profile_image AS delivery_staff_image
    FROM orders o
    LEFT JOIN users u
        ON u.id = o.delivery_user_id
       AND u.role = 'delivery'
    WHERE o.delivery_user_id IS NOT NULL
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            o.order_number LIKE :order_number
            OR o.customer_name LIKE :customer_name
            OR o.customer_phone LIKE :customer_phone
            OR u.full_name LIKE :staff_name
        )
    ";

    $term = '%' . $search . '%';

    $params['order_number'] = $term;
    $params['customer_name'] = $term;
    $params['customer_phone'] = $term;
    $params['staff_name'] = $term;
}

if (in_array($status, $allowedStatuses, true)) {
    $sql .= " AND o.status = :status";
    $params['status'] = $status;
}

$sql .= "
    ORDER BY
        CASE
            WHEN o.status = 'out_for_delivery' THEN 1
            WHEN o.status = 'ready' THEN 2
            WHEN o.status = 'delivered' THEN 3
            ELSE 4
        END,
        o.delivery_assigned_at DESC,
        o.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Summary counts */
$summaryStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_assigned,
        COALESCE(SUM(status = 'ready'), 0) AS ready_count,
        COALESCE(SUM(status = 'out_for_delivery'), 0) AS in_transit_count,
        COALESCE(SUM(status = 'delivered'), 0) AS delivered_count
    FROM orders
    WHERE delivery_user_id IS NOT NULL
");

$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

/* Ready orders waiting for assignment */
$unassignedStmt = $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE status = 'ready'
      AND delivery_user_id IS NULL
");

$unassignedCount = (int)$unassignedStmt->fetchColumn();

$success = $_SESSION['delivery_assignment_success'] ?? '';
$error = $_SESSION['delivery_assignment_error'] ?? '';

unset(
    $_SESSION['delivery_assignment_success'],
    $_SESSION['delivery_assignment_error']
);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.delivery-stat-card {
    border: 0;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(20, 40, 30, .06);
}

.delivery-stat-icon {
    width: 46px;
    height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #eaf7ef;
    color: #087a3e;
    font-size: 19px;
}

.delivery-person-avatar {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #eaf7ef;
    color: #087a3e;
    font-size: 17px;
}

.delivery-person-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.delivery-table th {
    font-size: 11px;
    color: #738078;
    text-transform: uppercase;
    white-space: nowrap;
}

.delivery-table td {
    vertical-align: middle;
}

.delivery-order-number {
    color: #087a3e;
    font-weight: 800;
    text-decoration: none;
}

.delivery-order-number:hover {
    text-decoration: underline;
}
</style>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h2 class="fw-bold mb-1">Delivery Assignments</h2>
            <p class="text-muted mb-0">
                Assign prepared orders and monitor delivery progress.
            </p>
        </div>

        <a href="orders.php" class="btn btn-outline-success">
            <i class="fas fa-shopping-bag me-2"></i>
            View All Orders
        </a>

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

    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="card delivery-stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Assigned</div>
                        <div class="fs-2 fw-bold">
                            <?= (int)$summary['total_assigned'] ?>
                        </div>
                    </div>
                    <div class="delivery-stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card delivery-stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Ready for Dispatch</div>
                        <div class="fs-2 fw-bold text-primary">
                            <?= (int)$summary['ready_count'] ?>
                        </div>
                    </div>
                    <div class="delivery-stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card delivery-stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Out for Delivery</div>
                        <div class="fs-2 fw-bold text-warning">
                            <?= (int)$summary['in_transit_count'] ?>
                        </div>
                    </div>
                    <div class="delivery-stat-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card delivery-stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Delivered</div>
                        <div class="fs-2 fw-bold text-success">
                            <?= (int)$summary['delivered_count'] ?>
                        </div>
                    </div>
                    <div class="delivery-stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <i class="fas fa-box-open me-2"></i>
            <strong><?= $unassignedCount ?></strong>
            ready order(s) are waiting for a delivery staff assignment.
        </div>

        <a href="orders.php" class="btn btn-sm btn-outline-primary">
            Review Orders
        </a>
    </div>

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0">Assigned Deliveries</h5>
        </div>

        <div class="card-body">

            <form method="GET" class="row g-2 mb-4">

                <div class="col-lg-6">
                    <input
                        type="search"
                        name="search"
                        class="form-control"
                        placeholder="Search order, customer, phone or delivery staff..."
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="col-lg-3">
                    <select name="status" class="form-select">
                        <option value="">All delivery statuses</option>
                        <option value="ready" <?= $status === 'ready' ? 'selected' : '' ?>>Ready</option>
                        <option value="out_for_delivery" <?= $status === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                        <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-success flex-fill">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="deliveries.php" class="btn btn-outline-secondary">Reset</a>
                </div>

            </form>

            <div class="table-responsive">

                <table class="table table-hover delivery-table">

                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Delivery Staff</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!$deliveries): ?>

                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-truck fa-2x text-muted mb-3"></i>
                                <p class="fw-bold mb-1">No assigned deliveries found</p>
                                <small class="text-muted">
                                    Assign a ready order to a delivery staff member to get started.
                                </small>
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($deliveries as $delivery): ?>

                            <?php
                            $staffImage = $delivery['delivery_staff_image'] ?? '';
                            $staffImagePath = __DIR__ . '/../uploads/delivery_staff/' . $staffImage;

                            $hasStaffImage = (
                                $staffImage !== '' &&
                                basename($staffImage) === $staffImage &&
                                is_file($staffImagePath)
                            );

                            $staffImageUrl = $hasStaffImage
                                ? '../uploads/delivery_staff/' . rawurlencode($staffImage)
                                : '';
                            ?>

                            <tr>

                                <td>
                                    <a
                                        class="delivery-order-number"
                                        href="delivery_details.php?id=<?= (int)$delivery['id'] ?>"
                                    >
                                        <?= htmlspecialchars($delivery['order_number'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <div class="small text-muted">
                                        #<?= (int)$delivery['id'] ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($delivery['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($delivery['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-2">

                                        <div class="delivery-person-avatar">

                                            <?php if ($hasStaffImage): ?>
                                                <img
                                                    src="<?= htmlspecialchars($staffImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                    alt="Delivery staff"
                                                >
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>

                                        </div>

                                        <div>
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars($delivery['delivery_staff_name'] ?? 'Staff unavailable', ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($delivery['delivery_staff_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        </div>

                                    </div>
                                </td>

                                <td class="fw-bold">
                                    KSh <?= number_format((float)$delivery['total_amount'], 2) ?>
                                </td>

                                <td>
                                    <?php
                                    $statusLabels = [
                                        'ready' => ['Ready', 'primary'],
                                        'out_for_delivery' => ['Out for Delivery', 'warning'],
                                        'delivered' => ['Delivered', 'success'],
                                        'cancelled' => ['Cancelled', 'danger']
                                    ];

                                    $statusInfo = $statusLabels[$delivery['status']]
                                        ?? [ucwords(str_replace('_', ' ', $delivery['status'])), 'secondary'];
                                    ?>

                                    <span class="badge bg-<?= $statusInfo[1] ?>">
                                        <?= htmlspecialchars($statusInfo[0], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td>
                                    <small>
                                        <?= $delivery['delivery_assigned_at']
                                            ? htmlspecialchars(date('d M Y, H:i', strtotime($delivery['delivery_assigned_at'])), ENT_QUOTES, 'UTF-8')
                                            : 'Not recorded' ?>
                                    </small>
                                </td>

                                <td class="text-end">
                                    <a
                                        href="delivery_details.php?id=<?= (int)$delivery['id'] ?>"
                                        class="btn btn-sm btn-outline-success"
                                    >
                                        <i class="fas fa-eye me-1"></i> Details
                                    </a>
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
