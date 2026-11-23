
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Payments';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$allowedStatuses = ['', 'pending', 'confirmed', 'rejected'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        o.order_number LIKE :order_number
        OR o.customer_name LIKE :customer_name
        OR o.customer_phone LIKE :customer_phone
        OR p.transaction_code LIKE :transaction_code
    )";

    $searchValue = '%' . $search . '%';

    $params['order_number'] = $searchValue;
    $params['customer_name'] = $searchValue;
    $params['customer_phone'] = $searchValue;
    $params['transaction_code'] = $searchValue;
}

if ($statusFilter !== '') {
    $where[] = 'p.status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Payment summary.
$summaryStmt = $pdo->query("
    SELECT
        status,
        COUNT(*) AS total,
        COALESCE(SUM(amount), 0) AS amount_total
    FROM payments
    GROUP BY status
");

$summary = [
    'pending' => ['count' => 0, 'amount' => 0],
    'confirmed' => ['count' => 0, 'amount' => 0],
    'rejected' => ['count' => 0, 'amount' => 0]
];

foreach ($summaryStmt->fetchAll() as $row) {
    if (isset($summary[$row['status']])) {
        $summary[$row['status']] = [
            'count' => (int)$row['total'],
            'amount' => (float)$row['amount_total']
        ];
    }
}

// Retrieve payment records.
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.order_id,
        p.payment_method,
        p.transaction_code,
        p.amount,
        p.status,
        p.confirmed_at,
        p.created_at,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.total_amount,
        o.status AS order_status,
        u.full_name AS verifier_name
    FROM payments p
    INNER JOIN orders o ON o.id = p.order_id
    LEFT JOIN users u ON u.id = p.confirmed_by
    $whereSql
    ORDER BY
        CASE p.status
            WHEN 'pending' THEN 1
            WHEN 'confirmed' THEN 2
            WHEN 'rejected' THEN 3
            ELSE 4
        END,
        p.created_at DESC,
        p.id DESC
");

$stmt->execute($params);
$payments = $stmt->fetchAll();

$success = $_SESSION['payment_success'] ?? '';
$error = $_SESSION['payment_error'] ?? '';

unset($_SESSION['payment_success'], $_SESSION['payment_error']);

require_once __DIR__ . '/includes/header.php';

?>

<div class="page-heading">

    <div>
        <div class="welcome-label">FINANCIAL MANAGEMENT</div>
        <h1>Payments</h1>
        <p>Review and manually verify customer payment submissions.</p>
    </div>

    <div class="page-date">
        <i class="fa-regular fa-calendar"></i>
        <?= date('d M Y') ?>
    </div>

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

<div class="alert alert-info">
    <i class="fa-solid fa-circle-info me-2"></i>
    Confirm a payment only after checking the transaction code and amount against the official M-Pesa statement.
</div>

<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon orange">
                <i class="fa-solid fa-clock"></i>
            </div>
        </div>

        <h3><?= number_format($summary['pending']['count']) ?></h3>
        <p>Pending payments</p>

        <div class="stat-caption">
            KSh <?= number_format($summary['pending']['amount'], 2) ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon green">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <h3><?= number_format($summary['confirmed']['count']) ?></h3>
        <p>Confirmed payments</p>

        <div class="stat-caption">
            KSh <?= number_format($summary['confirmed']['amount'], 2) ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon purple">
                <i class="fa-solid fa-ban"></i>
            </div>
        </div>

        <h3><?= number_format($summary['rejected']['count']) ?></h3>
        <p>Rejected payments</p>

        <div class="stat-caption">
            KSh <?= number_format($summary['rejected']['amount'], 2) ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <div class="stat-icon blue">
                <i class="fa-solid fa-wallet"></i>
            </div>
        </div>

        <h3>
            KSh <?= number_format($summary['confirmed']['amount'], 2) ?>
        </h3>

        <p>Confirmed payment value</p>
        <div class="stat-caption">Based on recorded payment entries</div>
    </div>

</div>

<div class="dashboard-card">

    <div class="card-heading">
        <h3>Payment Register</h3>

        <span class="text-muted small">
            <?= number_format(count($payments)) ?> records
        </span>
    </div>

    <form method="GET" class="row g-3 align-items-end mb-4">

        <div class="col-md-7">

            <label class="form-label fw-semibold">
                Search payments
            </label>

            <input
                type="search"
                name="search"
                class="form-control"
                placeholder="Order number, customer, phone or M-Pesa code..."
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
            >

        </div>

        <div class="col-md-3">

            <label class="form-label fw-semibold">
                Payment status
            </label>

            <select name="status" class="form-select">

                <option value="">All statuses</option>

                <?php foreach (array_slice($allowedStatuses, 1) as $status): ?>

                    <option
                        value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $statusFilter === $status ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="col-md-2 d-flex gap-2">

            <button type="submit" class="btn-admin-primary">
                <i class="fa-solid fa-search"></i>
                Search
            </button>

            <a href="payments.php" class="btn-admin-secondary">
                Reset
            </a>

        </div>

    </form>

    <div class="table-responsive">

        <table class="admin-table">

            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Transaction Code</th>
                    <th>Submitted Amount</th>
                    <th>Order Total</th>
                    <th>Payment Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$payments): ?>

                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fa-solid fa-wallet"></i>
                            <h4>No payments found</h4>
                            <p>Payment submissions will appear here.</p>
                        </div>
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($payments as $payment): ?>

                    <?php
                    $paymentClass = [
                        'pending' => 'status-pending',
                        'confirmed' => 'status-confirmed',
                        'rejected' => 'status-rejected'
                    ][$payment['status']] ?? 'status-pending';

                    $amountMatches = abs(
                        (float)$payment['amount'] -
                        (float)$payment['total_amount']
                    ) < 0.005;
                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars($payment['order_number'], ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                        </td>

                        <td>
                            <strong>
                                <?= htmlspecialchars($payment['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                            </strong>

                            <div class="text-muted small">
                                <?= htmlspecialchars($payment['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>

                        <td>
                            <strong>
                                <?= htmlspecialchars($payment['transaction_code'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                        </td>

                        <td>
                            KSh <?= number_format((float)$payment['amount'], 2) ?>
                        </td>

                        <td>
                            KSh <?= number_format((float)$payment['total_amount'], 2) ?>

                            <?php if (!$amountMatches): ?>
                                <div class="text-danger small">Amount mismatch</div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="status-badge <?= $paymentClass ?>">
                                <?= htmlspecialchars(ucfirst($payment['status']), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>

                        <td>
                            <?= date('d M Y, H:i', strtotime($payment['created_at'])) ?>
                        </td>

                        <td>
                            <a
                                href="payment_details.php?id=<?= (int)$payment['id'] ?>"
                                class="btn btn-sm btn-outline-success"
                            >
                                <i class="fa-solid fa-eye"></i>
                                Review
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
