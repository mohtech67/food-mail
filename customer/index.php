
<?php
require_once __DIR__ . '/auth.php';

$customerId = (int)$currentCustomer['id'];
$customerPhone = $currentCustomer['phone'];

$stmt = $pdo->prepare("
    SELECT
        id,
        order_number,
        total_amount,
        status,
        created_at
    FROM orders
    WHERE user_id = :user_id
       OR customer_phone = :phone
    ORDER BY created_at DESC
    LIMIT 100
");

$stmt->execute([
    'user_id' => $customerId,
    'phone' => $customerPhone
]);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders = count($orders);
$delivered = 0;
$pending = 0;
$totalSpent = 0;

foreach ($orders as $order) {
    if ($order['status'] === 'delivered') {
        $delivered++;
    }

    if (in_array($order['status'], ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery'], true)) {
        $pending++;
    }

    if ($order['status'] !== 'cancelled') {
        $totalSpent += (float)$order['total_amount'];
    }
}

function customerDashboardEscape($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Account | Food Mail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: Arial, sans-serif; }
        .topbar {
            background: #166534;
            color: white;
            padding: 18px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }
        .topbar a { color: white; text-decoration: none; }
        .brand { font-size: 23px; font-weight: 800; }
        .dashboard { max-width: 1150px; margin: 30px auto; padding: 0 16px; }
        .profile-card, .stat-card, .orders-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
        }
        .stat-card p { color: #64748b; margin-bottom: 8px; }
        .stat-card h2 { margin: 0; font-size: 25px; }
        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #e2e8f0;
            font-size: 12px;
            font-weight: 700;
        }
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-out_for_delivery { background: #dbeafe; color: #1e40af; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .table-responsive { overflow-x: auto; }
        @media(max-width:600px) {
            .topbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="brand">FOOD MAIL</div>
    <div>
        <?= customerDashboardEscape($currentCustomer['full_name']); ?>
        &nbsp; | &nbsp;
        <a href="logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">
    <div class="mb-4">
        <h2>Welcome, <?= customerDashboardEscape($currentCustomer['full_name']); ?></h2>
        <p class="text-muted">Manage your account and track your Food Mail orders.</p>
    </div>

    <div class="profile-card mb-4">
        <h4>My Profile</h4>
        <p class="mb-1"><strong>Phone:</strong> <?= customerDashboardEscape($currentCustomer['phone']); ?></p>
        <p class="mb-0"><strong>Email:</strong> <?= customerDashboardEscape($currentCustomer['email'] ?: 'Not provided'); ?></p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <p>Total Orders</p>
                <h2><?= $totalOrders; ?></h2>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card">
                <p>In Progress</p>
                <h2><?= $pending; ?></h2>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card">
                <p>Delivered</p>
                <h2><?= $delivered; ?></h2>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card">
                <p>Order Value</p>
                <h2>KSh <?= number_format($totalSpent, 2); ?></h2>
            </div>
        </div>
    </div>

    <div class="orders-card">
        <h4 class="mb-3">My Orders</h4>

        <?php if (!$orders): ?>
            <div class="alert alert-info">
                You have not placed any orders yet.
                <a href="../shop.php">Browse products</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?= customerDashboardEscape($order['order_number']); ?></strong></td>
                            <td>KSh <?= number_format((float)$order['total_amount'], 2); ?></td>
                            <td>
                                <span class="status status-<?= customerDashboardEscape($order['status']); ?>">
                                    <?= customerDashboardEscape(ucwords(str_replace('_', ' ', $order['status']))); ?>
                                </span>
                            </td>
                            <td><?= customerDashboardEscape($order['created_at']); ?></td>
                            <td>
                                <a class="btn btn-success btn-sm"
                                   href="order_details.php?id=<?= (int)$order['id']; ?>">
                                    Track Order
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <a href="../shop.php" class="btn btn-success mt-3">Shop Products</a>
    </div>
</main>

</body>
</html>
