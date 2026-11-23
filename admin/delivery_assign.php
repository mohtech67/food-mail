
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    $_SESSION['delivery_assignment_error'] = 'Invalid order selected.';
    header('Location: orders.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.delivery_address,
        o.delivery_notes,
        o.total_amount,
        o.status,
        o.delivery_user_id,
        o.delivery_assigned_at,
        p.status AS payment_status
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE o.id = :id
    LIMIT 1
");

$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['delivery_assignment_error'] = 'Order not found.';
    header('Location: orders.php');
    exit;
}

if ($order['status'] !== 'ready') {
    $_SESSION['delivery_assignment_error'] =
        'Only orders marked Ready can be assigned to delivery staff.';

    header('Location: order_details.php?id=' . $orderId);
    exit;
}

if ($order['payment_status'] !== 'confirmed') {
    $_SESSION['delivery_assignment_error'] =
        'Payment must be confirmed before assigning this order.';

    header('Location: order_details.php?id=' . $orderId);
    exit;
}

$staffStmt = $pdo->query("
    SELECT id, full_name, phone, profile_image
    FROM users
    WHERE role = 'delivery'
      AND account_status = 'active'
    ORDER BY full_name ASC
");

$staffMembers = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = $_SESSION['csrf_token'] ?? '';

if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$error = $_SESSION['delivery_assignment_error'] ?? '';
unset($_SESSION['delivery_assignment_error']);

$pageTitle = 'Assign Delivery | Food Mail Admin';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.assignment-panel {
    border: 0;
    border-radius: 15px;
    box-shadow: 0 5px 22px rgba(20, 40, 30, .06);
}

.assignment-staff-option {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px;
    border: 1px solid #e5ece7;
    border-radius: 12px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: .2s ease;
}

.assignment-staff-option:hover {
    background: #f3faf5;
    border-color: #83c59b;
}

.assignment-staff-option input {
    width: 18px;
    height: 18px;
}

.assignment-staff-photo {
    width: 52px;
    height: 52px;
    min-width: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    overflow: hidden;
    background: #eaf7ef;
    color: #087a3e;
    font-size: 19px;
}

.assignment-staff-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.assignment-staff-option:has(input:checked) {
    background: #effaf2;
    border-color: #087a3e;
}
</style>

<div class="container-fluid py-4">

    <div class="mb-4">
        <a href="orders.php" class="text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i> Back to Orders
        </a>

        <h2 class="fw-bold mt-3 mb-1">Assign Delivery Staff</h2>

        <p class="text-muted mb-0">
            Select an active delivery staff member for this prepared order.
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-lg-5">

            <div class="card assignment-panel">

                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-receipt text-success me-2"></i>
                        Order Information
                    </h5>
                </div>

                <div class="card-body p-4">

                    <div class="mb-3">
                        <small class="text-muted">Order Number</small>
                        <h5 class="fw-bold text-success">
                            <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
                        </h5>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <small class="text-muted">Customer</small>
                        <div class="fw-bold">
                            <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div>
                            <?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">Delivery Address</small>
                        <div>
                            <?= nl2br(htmlspecialchars($order['delivery_address'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    </div>

                    <?php if (!empty($order['delivery_notes'])): ?>
                        <div class="mb-3">
                            <small class="text-muted">Customer Instructions</small>
                            <div>
                                <?= nl2br(htmlspecialchars($order['delivery_notes'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Order Status</span>
                        <span class="badge bg-primary">Ready</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Payment Status</span>
                        <span class="badge bg-success">Confirmed</span>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">Order Total</span>
                        <strong class="text-success">
                            KSh <?= number_format((float)$order['total_amount'], 2) ?>
                        </strong>
                    </div>

                </div>

            </div>

        </div>

        <div class="col-lg-7">

            <div class="card assignment-panel">

                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-truck text-success me-2"></i>
                        Choose Delivery Staff
                    </h5>
                </div>

                <div class="card-body p-4">

                    <?php if (!$staffMembers): ?>

                        <div class="alert alert-warning">
                            No active delivery staff accounts are available.
                            Register and activate a delivery staff member first.
                        </div>

                        <a href="delivery_staff_form.php" class="btn btn-success">
                            <i class="fas fa-user-plus me-2"></i>
                            Register Delivery Staff
                        </a>

                    <?php else: ?>

                        <form method="POST" action="delivery_assignment_save.php">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                            >

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= (int)$order['id'] ?>"
                            >

                            <p class="text-muted small mb-3">
                                Select one delivery staff member.
                            </p>

                            <?php foreach ($staffMembers as $staff): ?>

                                <?php
                                $staffImage = $staff['profile_image'] ?? '';
                                $staffImagePath = __DIR__ . '/../uploads/delivery_staff/' . $staffImage;

                                $hasImage = (
                                    $staffImage !== '' &&
                                    basename($staffImage) === $staffImage &&
                                    is_file($staffImagePath)
                                );

                                $staffImageUrl = $hasImage
                                    ? '../uploads/delivery_staff/' . rawurlencode($staffImage)
                                    : '';
                                ?>

                                <label class="assignment-staff-option">

                                    <input
                                        type="radio"
                                        name="delivery_user_id"
                                        value="<?= (int)$staff['id'] ?>"
                                        <?= (int)$order['delivery_user_id'] === (int)$staff['id'] ? 'checked' : '' ?>
                                        required
                                    >

                                    <div class="assignment-staff-photo">

                                        <?php if ($hasImage): ?>
                                            <img
                                                src="<?= htmlspecialchars($staffImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                alt="Staff profile"
                                            >
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>

                                    </div>

                                    <div class="flex-grow-1">
                                        <div class="fw-bold">
                                            <?= htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>

                                        <small class="text-muted">
                                            <?= htmlspecialchars($staff['phone'], ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                    </div>

                                    <span class="badge bg-success">Active</span>

                                </label>

                            <?php endforeach; ?>

                            <div class="alert alert-info small mt-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Assigning this order will make it visible in the selected delivery staff member's portal.
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-4">

                                <button type="submit" class="btn btn-success px-4">
                                    <i class="fas fa-truck me-2"></i>
                                    Save Delivery Assignment
                                </button>

                                <a href="orders.php" class="btn btn-outline-secondary">
                                    Cancel
                                </a>

                            </div>

                        </form>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
