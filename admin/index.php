
<?php
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Food Mail Admin Dashboard';

$stats = [
    'orders' => 0,
    'pending' => 0,
    'products' => 0,
    'customers' => 0,
    'revenue' => 0,
    'payments_pending' => 0,
    'delivering' => 0,
    'reviews' => 0
];

$recentOrders = [];
$dashboardError = false;

try {

    $stats['orders'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM orders"
    )->fetchColumn();

    $stats['pending'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM orders
         WHERE status = 'pending'"
    )->fetchColumn();

    $stats['products'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM products"
    )->fetchColumn();

    $stats['customers'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM users
         WHERE role = 'customer'"
    )->fetchColumn();

    $stats['revenue'] = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount), 0)
         FROM payments
         WHERE status = 'confirmed'"
    )->fetchColumn();

    $stats['payments_pending'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM payments
         WHERE status = 'pending'"
    )->fetchColumn();

    $stats['delivering'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM orders
         WHERE status = 'out_for_delivery'"
    )->fetchColumn();

    $stats['reviews'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM delivery_ratings"
    )->fetchColumn();

    $recentOrders = $pdo->query(
        "SELECT
            o.id,
            o.order_number,
            o.customer_name,
            o.customer_phone,
            o.total_amount,
            o.status,
            o.created_at,
            p.status AS payment_status
         FROM orders o
         LEFT JOIN payments p ON p.order_id = o.id
         ORDER BY o.created_at DESC
         LIMIT 8"
    )->fetchAll();

} catch (PDOException $e) {

    error_log('Food Mail dashboard error: ' . $e->getMessage());

    $dashboardError = true;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-heading">

    <div>
        <span class="dashboard-eyebrow">BUSINESS OVERVIEW</span>
        <h1>Dashboard</h1>
        <p>Monitor and manage your Food Mail business.</p>
    </div>

    <div class="dashboard-date">
        <i class="fa-regular fa-calendar"></i>
        <?= date('d M Y') ?>
    </div>

</div>

<?php if ($dashboardError): ?>
    <div class="alert alert-danger">
        Some dashboard statistics could not be loaded.
        Check your database connection.
    </div>
<?php endif; ?>

<!-- STATISTICS -->
<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <div>
            <span>Total Orders</span>
            <h3><?= number_format($stats['orders']) ?></h3>
            <small>All recorded orders</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div>
            <span>Pending Orders</span>
            <h3><?= number_format($stats['pending']) ?></h3>
            <small>Awaiting processing</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fa-solid fa-box"></i>
        </div>
        <div>
            <span>Total Products</span>
            <h3><?= number_format($stats['products']) ?></h3>
            <small>Products in inventory</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <span>Customers</span>
            <h3><?= number_format($stats['customers']) ?></h3>
            <small>Registered customers</small>
        </div>
    </div>

</div>

<!-- REVENUE AND OPERATIONS -->
<div class="row g-4 mt-1">

    <div class="col-lg-7">

        <div class="revenue-card">

            <div class="revenue-card-header">
                <div>
                    <span>CONFIRMED PAYMENTS</span>
                    <h2>
                        KSh <?= number_format($stats['revenue'], 2) ?>
                    </h2>
                    <small>Total confirmed M-Pesa and cash payments</small>
                </div>

                <div class="revenue-icon">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>

            <div class="revenue-note">
                <i class="fa-solid fa-circle-info"></i>
                Revenue is calculated from manually confirmed payments.
            </div>

        </div>

    </div>

    <div class="col-lg-5">

        <div class="operations-card">

            <h4>Operations Overview</h4>

            <div class="operation-row">
                <span><i class="fa-solid fa-money-bill-wave"></i> Pending Payments</span>
                <strong><?= number_format($stats['payments_pending']) ?></strong>
            </div>

            <div class="operation-row">
                <span><i class="fa-solid fa-truck-fast"></i> Out for Delivery</span>
                <strong><?= number_format($stats['delivering']) ?></strong>
            </div>

            <div class="operation-row">
                <span><i class="fa-solid fa-star"></i> Customer Reviews</span>
                <strong><?= number_format($stats['reviews']) ?></strong>
            </div>

        </div>

    </div>

</div>

<!-- RECENT ORDERS -->
<div class="dashboard-table-card mt-4">

    <div class="table-card-heading">

        <div>
            <h4>Recent Orders</h4>
            <p>Latest customer orders received by Food Mail.</p>
        </div>

        <a href="orders.php" class="table-view-link">
            View All <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div>

    <div class="table-responsive">

        <table class="table admin-table align-middle">

            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Order Status</th>
                    <th>Payment</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($recentOrders)): ?>

                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="fa-solid fa-inbox d-block mb-2"></i>
                            No orders have been received yet.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($recentOrders as $order): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $order['order_number'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $order['customer_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                                <small class="d-block text-muted">
                                    <?= htmlspecialchars(
                                        $order['customer_phone'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </small>
                            </td>

                            <td>
                                <strong>
                                    KSh <?= number_format(
                                        (float)$order['total_amount'],
                                        2
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(
                                        ucwords(str_replace('_', ' ', $order['status'])),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span class="payment-badge">
                                    <?= htmlspecialchars(
                                        ucfirst($order['payment_status'] ?? 'Not recorded'),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= date(
                                    'd M Y',
                                    strtotime($order['created_at'])
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
