
<?php
require_once __DIR__ . '/auth.php';

$pageTitle = 'My Deliveries | Food Mail';

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_assigned,
        COALESCE(SUM(status = 'ready'), 0) AS ready_orders,
        COALESCE(SUM(status = 'out_for_delivery'), 0) AS active_deliveries,
        COALESCE(SUM(status = 'delivered'), 0) AS completed_deliveries
    FROM orders
    WHERE delivery_user_id = :delivery_id
");

$statsStmt->execute(['delivery_id' => $deliveryId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$orderStmt = $pdo->prepare("
    SELECT
        id,
        order_number,
        customer_name,
        customer_phone,
        delivery_address,
        delivery_notes,
        total_amount,
        status,
        created_at,
        delivery_assigned_at
    FROM orders
    WHERE delivery_user_id = :delivery_id
      AND status IN ('ready', 'out_for_delivery', 'delivered')
    ORDER BY
        CASE
            WHEN status = 'out_for_delivery' THEN 1
            WHEN status = 'ready' THEN 2
            WHEN status = 'delivered' THEN 3
            ELSE 4
        END,
        delivery_assigned_at DESC,
        id DESC
");

$orderStmt->execute(['delivery_id' => $deliveryId]);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

$statusLabels = [
    'ready' => ['Ready for Collection', 'primary'],
    'out_for_delivery' => ['Out for Delivery', 'warning'],
    'delivered' => ['Delivered', 'success']
];
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f3f7f4;
            font-family: Arial, sans-serif;
            color: #23372c;
        }

        .topbar {
            background: white;
            border-bottom: 1px solid #e6eee8;
            padding: 15px 25px;
        }

        .brand {
            color: #087a3e;
            font-size: 23px;
            font-weight: 900;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: auto;
            padding: 30px 22px;
        }

        .profile-photo {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            overflow: hidden;
            background: #eaf7ef;
            color: #087a3e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            border: 3px solid #dceee2;
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .stat-card {
            background: white;
            border: 0;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,.04);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: #eaf7ef;
            color: #087a3e;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delivery-card {
            border: 0;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,.04);
            height: 100%;
        }

        .btn-food {
            background: #087a3e;
            color: white;
        }

        .btn-food:hover {
            background: #056331;
            color: white;
        }

        .order-number {
            color: #087a3e;
            font-weight: 800;
        }

        .small-label {
            font-size: 12px;
            color: #78867e;
        }
    </style>

</head>

<body>

<nav class="topbar">

    <div class="container-fluid d-flex justify-content-between align-items-center">

        <a href="index.php" class="brand text-decoration-none">
            <i class="fas fa-utensils me-2"></i>FOOD MAIL
        </a>

        <form method="POST" action="logout.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['delivery_csrf'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>
                Logout
            </button>

        </form>

    </div>

</nav>

<main class="dashboard-container">

    <!-- PROFILE -->

    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">

        <div class="profile-photo">

            <?php if ($deliveryProfileImage !== ''): ?>

                <img
                    src="<?= htmlspecialchars($deliveryProfileImage, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Profile photograph"
                >

            <?php else: ?>

                <i class="fas fa-user"></i>

            <?php endif; ?>

        </div>

        <div>
            <div class="small-label">WELCOME BACK</div>

            <h3 class="fw-bold mb-1">
                <?= htmlspecialchars($deliveryStaff['full_name'], ENT_QUOTES, 'UTF-8') ?>
            </h3>

            <div class="text-muted small">
                <i class="fas fa-phone me-1"></i>
                <?= htmlspecialchars($deliveryStaff['phone'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

    </div>

    <div class="mb-4">
        <h4 class="fw-bold">Delivery Dashboard</h4>
        <p class="text-muted">
            Manage your assigned Food Mail deliveries.
        </p>
    </div>

    <!-- STATISTICS -->

    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>
                        <div class="small-label">Total Assigned</div>
                        <div class="fs-2 fw-bold">
                            <?= (int)$stats['total_assigned'] ?>
                        </div>
                    </div>

                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>
                        <div class="small-label">Ready for Collection</div>
                        <div class="fs-2 fw-bold text-primary">
                            <?= (int)$stats['ready_orders'] ?>
                        </div>
                    </div>

                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>
                        <div class="small-label">Active Deliveries</div>
                        <div class="fs-2 fw-bold text-warning">
                            <?= (int)$stats['active_deliveries'] ?>
                        </div>
                    </div>

                    <div class="stat-icon">
                        <i class="fas fa-truck"></i>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>
                        <div class="small-label">Completed Deliveries</div>
                        <div class="fs-2 fw-bold text-success">
                            <?= (int)$stats['completed_deliveries'] ?>
                        </div>
                    </div>

                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- ASSIGNED ORDERS -->

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h5 class="fw-bold mb-0">My Assigned Orders</h5>

        <span class="badge bg-success">
            <?= count($orders) ?> orders
        </span>

    </div>

    <div class="row g-3">

        <?php if (!$orders): ?>

            <div class="col-12">

                <div class="card delivery-card">
                    <div class="card-body text-center py-5">

                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>

                        <h5 class="fw-bold">No Assigned Orders Yet</h5>

                        <p class="text-muted mb-0">
                            Your assigned orders will appear here when the administrator assigns them.
                        </p>

                    </div>
                </div>

            </div>

        <?php else: ?>

            <?php foreach ($orders as $order): ?>

                <?php
                $statusInfo = $statusLabels[$order['status']]
                    ?? ['Unknown', 'secondary'];
                ?>

                <div class="col-md-6 col-xl-4">

                    <div class="card delivery-card">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start mb-3">

                                <div>
                                    <div class="small-label">ORDER NUMBER</div>

                                    <div class="order-number">
                                        <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>

                                <span class="badge bg-<?= $statusInfo[1] ?>">
                                    <?= htmlspecialchars($statusInfo[0], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                            </div>

                            <hr>

                            <div class="mb-3">

                                <div class="small-label">CUSTOMER</div>

                                <div class="fw-bold">
                                    <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="small text-muted">
                                    <i class="fas fa-phone me-1"></i>
                                    <?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                            </div>

                            <div class="mb-3">

                                <div class="small-label">DELIVERY ADDRESS</div>

                                <div class="small">
                                    <?= htmlspecialchars($order['delivery_address'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">

                                <span class="small-label">ORDER TOTAL</span>

                                <strong class="text-success">
                                    KSh <?= number_format((float)$order['total_amount'], 2) ?>
                                </strong>

                            </div>

                            <a
                                href="order_details.php?id=<?= (int)$order['id'] ?>"
                                class="btn btn-food w-100"
                            >
                                View Delivery Details
                                <i class="fas fa-arrow-right ms-2"></i>
                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>

</body>
</html>
