
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

function customerDetailsEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function customerDetailsMoney($amount): string
{
    return 'KSh ' . number_format((float)$amount, 2);
}

$phone = trim($_GET['phone'] ?? '');

if ($phone === '' || strlen($phone) > 30) {
    http_response_code(400);
    exit('Invalid customer phone number.');
}

/*
|--------------------------------------------------------------------------
| Registered customer information
|--------------------------------------------------------------------------
*/
$userStmt = $pdo->prepare("
    SELECT id, full_name, email, phone, account_status, created_at
    FROM users
    WHERE phone = :phone
      AND role = 'customer'
    LIMIT 1
");

$userStmt->execute(['phone' => $phone]);
$customerAccount = $userStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Customer orders
|--------------------------------------------------------------------------
*/
$orderStmt = $pdo->prepare("
    SELECT
        id,
        order_number,
        customer_name,
        customer_phone,
        delivery_address,
        subtotal,
        delivery_fee,
        total_amount,
        status,
        created_at
    FROM orders
    WHERE customer_phone = :phone
    ORDER BY created_at DESC
");

$orderStmt->execute(['phone' => $phone]);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Customer summary
|--------------------------------------------------------------------------
*/
$totalOrders = count($orders);
$totalSpent = 0;
$deliveredOrders = 0;
$cancelledOrders = 0;

foreach ($orders as $order) {
    if ($order['status'] !== 'cancelled') {
        $totalSpent += (float)$order['total_amount'];
    }

    if ($order['status'] === 'delivered') {
        $deliveredOrders++;
    }

    if ($order['status'] === 'cancelled') {
        $cancelledOrders++;
    }
}

$customerName = $customerAccount['full_name']
    ?? ($orders[0]['customer_name'] ?? 'Unknown Customer');

$pageTitle = 'Customer Details';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content">

    <div class="page-header">
        <div>
            <h1>Customer Details</h1>
            <p>Customer profile and order history.</p>
        </div>

        <a href="customers.php" class="btn btn-secondary">
            Back to Customers
        </a>
    </div>

    <!-- CUSTOMER PROFILE -->
    <div class="card">
        <div class="card-header">
            <h2>Customer Profile</h2>
        </div>

        <div class="card-body">

            <table class="details-table">

                <tr>
                    <th>Full Name</th>
                    <td><?= customerDetailsEscape($customerName); ?></td>
                </tr>

                <tr>
                    <th>Phone Number</th>
                    <td><?= customerDetailsEscape($phone); ?></td>
                </tr>

                <tr>
                    <th>Email Address</th>
                    <td>
                        <?= customerDetailsEscape($customerAccount['email'] ?? 'Not registered'); ?>
                    </td>
                </tr>

                <tr>
                    <th>Customer Type</th>
                    <td>
                        <?php if ($customerAccount): ?>
                            <span class="status-badge status-confirmed">
                                Registered Customer
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-pending">
                                Guest Customer
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th>Account Status</th>
                    <td>
                        <?= customerDetailsEscape($customerAccount['account_status'] ?? 'No account'); ?>
                    </td>
                </tr>

                <tr>
                    <th>Registration Date</th>
                    <td>
                        <?= customerDetailsEscape($customerAccount['created_at'] ?? 'Not applicable'); ?>
                    </td>
                </tr>

            </table>

        </div>
    </div>

    <!-- CUSTOMER STATISTICS -->
    <div class="customer-stats">

        <div class="stat-card">
            <p>Total Orders</p>
            <h2><?= number_format($totalOrders); ?></h2>
        </div>

        <div class="stat-card">
            <p>Total Order Value Excluding Cancelled Orders</p>
            <h2><?= customerDetailsMoney($totalSpent); ?></h2>
        </div>

        <div class="stat-card">
            <p>Delivered Orders</p>
            <h2><?= number_format($deliveredOrders); ?></h2>
        </div>

        <div class="stat-card">
            <p>Cancelled Orders</p>
            <h2><?= number_format($cancelledOrders); ?></h2>
        </div>

    </div>

    <!-- ORDER HISTORY -->
    <div class="card">
        <div class="card-header">
            <h2>Customer Order History</h2>
        </div>

        <div class="card-body">

            <?php if (empty($orders)): ?>

                <div class="alert alert-info">
                    This customer has no recorded orders.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Delivery Address</th>
                                <th>Order Amount</th>
                                <th>Status</th>
                                <th>Order Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($orders as $order): ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= customerDetailsEscape($order['order_number']); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= customerDetailsEscape($order['delivery_address']); ?>
                                </td>

                                <td>
                                    <?= customerDetailsMoney($order['total_amount']); ?>
                                </td>

                                <td>
                                    <span class="status-badge status-<?= customerDetailsEscape($order['status']); ?>">
                                        <?= customerDetailsEscape(ucwords(str_replace('_', ' ', $order['status']))); ?>
                                    </span>
                                </td>

                                <td>
                                    <?= customerDetailsEscape($order['created_at']); ?>
                                </td>

                                <td>
                                    <a
                                        href="order_details.php?id=<?= (int)$order['id']; ?>"
                                        class="btn btn-primary btn-sm"
                                    >
                                        View Order
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            <?php endif; ?>

        </div>
    </div>

</div>

<style>
.customer-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
}

.stat-card p {
    color: #64748b;
    margin-bottom: 8px;
}

.stat-card h2 {
    font-size: 22px;
    margin: 0;
}

.details-table {
    width: 100%;
    border-collapse: collapse;
}

.details-table th,
.details-table td {
    text-align: left;
    padding: 13px 10px;
    border-bottom: 1px solid #e5e7eb;
}

.details-table th {
    width: 35%;
    color: #64748b;
}

.table-responsive {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 13px 10px;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-confirmed,
.status-delivered {
    background: #dcfce7;
    color: #166534;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-cancelled,
.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-preparing,
.status-ready,
.status-out_for_delivery {
    background: #dbeafe;
    color: #1e40af;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
