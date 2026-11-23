
<?php
require_once __DIR__ . '/auth.php';

/* ------------------------------------------------------------
   VALIDATE ORDER ID
------------------------------------------------------------ */

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    header('Location: index.php');
    exit;
}

/* ------------------------------------------------------------
   LOAD ORDER, PAYMENT AND ASSIGNED DELIVERY STAFF
------------------------------------------------------------ */

$orderStmt = $pdo->prepare("
    SELECT
        o.*,

        p.payment_method,
        p.transaction_code,
        p.amount AS payment_amount,
        p.status AS payment_status,
        p.confirmed_at,

        d.full_name AS delivery_staff_name,
        d.phone AS delivery_staff_phone,
        d.profile_image AS delivery_staff_image

    FROM orders o

    LEFT JOIN payments p
        ON p.order_id = o.id

    LEFT JOIN users d
        ON d.id = o.delivery_user_id
       AND d.role = 'delivery'

    WHERE o.id = :order_id
      AND (
          o.user_id = :user_id
          OR o.customer_phone = :customer_phone
      )

    LIMIT 1
");

$orderStmt->execute([
    'order_id' => $orderId,
    'user_id' => (int)$currentCustomer['id'],
    'customer_phone' => $currentCustomer['phone']
]);

$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit('Order not found or you are not authorised to view it.');
}

/* ------------------------------------------------------------
   LOAD ORDER ITEMS
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

$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------------------------------------------
   LOAD ORDER STATUS HISTORY
------------------------------------------------------------ */

$historyStmt = $pdo->prepare("
    SELECT
        status,
        description,
        created_at
    FROM order_status_history
    WHERE order_id = :order_id
    ORDER BY created_at DESC, id DESC
");

$historyStmt->execute(['order_id' => $orderId]);

$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------------------------------------------
   DELIVERY STAFF PROFILE IMAGE
------------------------------------------------------------ */

$staffImageUrl = '';

if (!empty($order['delivery_staff_image'])) {

    $filename = $order['delivery_staff_image'];

    if (basename($filename) === $filename) {

        $imagePath = __DIR__ .
            '/../uploads/delivery_staff/' .
            $filename;

        if (is_file($imagePath)) {
            $staffImageUrl =
                '../uploads/delivery_staff/' .
                rawurlencode($filename);
        }
    }
}

/* ------------------------------------------------------------
   STATUS CONFIGURATION
------------------------------------------------------------ */

$progressSteps = [
    'pending' => [
        'label' => 'Order Received',
        'description' => 'Your order has been received.'
    ],
    'confirmed' => [
        'label' => 'Order Confirmed',
        'description' => 'Your order has been confirmed.'
    ],
    'preparing' => [
        'label' => 'Preparing Order',
        'description' => 'Your food and water are being prepared.'
    ],
    'ready' => [
        'label' => 'Ready for Dispatch',
        'description' => 'Your order is ready for delivery.'
    ],
    'out_for_delivery' => [
        'label' => 'Out for Delivery',
        'description' => 'Your delivery staff member is bringing your order.'
    ],
    'delivered' => [
        'label' => 'Delivered',
        'description' => 'Your order has been delivered.'
    ]
];

$statusOrder = array_keys($progressSteps);

$currentStatus = $order['status'];

$currentPosition = array_search($currentStatus, $statusOrder, true);

$isCancelled = $currentStatus === 'cancelled';

$isFinished = in_array(
    $currentStatus,
    ['delivered', 'cancelled'],
    true
);

$statusLabel = ucwords(
    str_replace('_', ' ', $currentStatus)
);

$paymentStatus = $order['payment_status'] ?? 'pending';

$paymentLabels = [
    'pending' => ['Awaiting Verification', 'warning'],
    'confirmed' => ['Payment Confirmed', 'success'],
    'rejected' => ['Payment Rejected', 'danger']
];

$paymentInfo = $paymentLabels[$paymentStatus]
    ?? ['Unknown', 'secondary'];

/* ------------------------------------------------------------
   PAGE TITLE
------------------------------------------------------------ */

$pageTitle = 'Track Order ' . $order['order_number'];

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Food Mail</title>

    <?php if (!$isFinished): ?>
        <meta http-equiv="refresh" content="30">
    <?php endif; ?>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --food-green: #087a3e;
            --food-dark: #173b29;
            --food-light: #eaf7ef;
            --food-background: #f4f7f5;
        }

        body {
            background: var(--food-background);
            color: #27372e;
            font-family: Arial, sans-serif;
        }

        .tracking-navbar {
            background: white;
            border-bottom: 1px solid #e8eee9;
        }

        .food-brand {
            color: var(--food-green);
            font-size: 23px;
            font-weight: 900;
            text-decoration: none;
        }

        .tracking-container {
            max-width: 1150px;
            margin: auto;
            padding: 35px 18px 65px;
        }

        .tracking-card {
            border: 0;
            border-radius: 16px;
            background: white;
            box-shadow: 0 5px 25px rgba(20, 40, 30, .045);
        }

        .tracking-heading {
            font-weight: 800;
            color: var(--food-dark);
        }

        .order-number {
            color: var(--food-green);
            font-weight: 800;
        }

        .status-badge {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 30px;
            background: var(--food-light);
            color: var(--food-green);
            font-size: 13px;
            font-weight: 700;
        }

        .progress-track {
            position: relative;
            padding-left: 42px;
        }

        .progress-track::before {
            content: "";
            position: absolute;
            left: 13px;
            top: 15px;
            bottom: 22px;
            width: 3px;
            background: #e4ebe6;
        }

        .progress-step {
            position: relative;
            padding-bottom: 28px;
        }

        .progress-step:last-child {
            padding-bottom: 0;
        }

        .progress-icon {
            position: absolute;
            left: -42px;
            top: 0;
            width: 29px;
            height: 29px;
            border-radius: 50%;
            background: #e9efeb;
            color: #8b9990;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            z-index: 2;
        }

        .progress-step.completed .progress-icon,
        .progress-step.current .progress-icon {
            background: var(--food-green);
            color: white;
        }

        .progress-step.current .progress-title {
            color: var(--food-green);
            font-weight: 800;
        }

        .progress-title {
            font-weight: 700;
            color: #293b30;
        }

        .progress-description {
            color: #849087;
            font-size: 13px;
            margin-top: 4px;
        }

        .staff-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .staff-photo {
            width: 76px;
            height: 76px;
            min-width: 76px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--food-light);
            border: 3px solid #dceee2;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--food-green);
            font-size: 27px;
        }

        .staff-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .detail-label {
            color: #87938b;
            display: block;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .history-item {
            border-left: 3px solid #cde5d4;
            padding: 0 0 20px 18px;
            margin-left: 6px;
            position: relative;
        }

        .history-item::before {
            content: "";
            position: absolute;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: var(--food-green);
            left: -7px;
            top: 2px;
        }

        .history-item:last-child {
            padding-bottom: 0;
            border-left-color: transparent;
        }

        .btn-food {
            background: var(--food-green);
            color: white;
            font-weight: 700;
        }

        .btn-food:hover {
            background: #056331;
            color: white;
        }

        .food-footer {
            background: white;
            border-top: 1px solid #e8eee9;
            padding: 25px 15px;
            text-align: center;
            color: #78867e;
        }

        @media(max-width: 576px) {
            .tracking-container {
                padding: 22px 12px 45px;
            }

            .tracking-card .card-body {
                padding: 20px !important;
            }
        }
    </style>

</head>

<body>

<!-- NAVIGATION -->

<nav class="navbar tracking-navbar py-3">

    <div class="container">

        <a href="../index.php" class="food-brand">
            <i class="fas fa-utensils me-2"></i>FOOD MAIL
        </a>

        <div class="d-flex gap-2">

            <a href="index.php" class="btn btn-outline-success btn-sm">
                <i class="fas fa-user me-1"></i>
                My Account
            </a>

        </div>

    </div>

</nav>

<main class="tracking-container">

    <!-- PAGE HEADING -->

    <div class="mb-4">

        <a href="index.php" class="text-decoration-none small text-success">
            <i class="fas fa-arrow-left me-1"></i>
            Back to My Account
        </a>

        <h2 class="tracking-heading mt-3 mb-1">
            Track Your Order
        </h2>

        <p class="text-muted mb-0">
            Follow your Food Mail order from confirmation to delivery.
        </p>

    </div>

    <!-- ORDER SUMMARY -->

    <div class="card tracking-card mb-4">

        <div class="card-body p-4">

            <div class="row align-items-center g-3">

                <div class="col-md-7">

                    <span class="detail-label">ORDER NUMBER</span>

                    <div class="order-number fs-5">
                        <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="text-muted small mt-2">
                        Placed on
                        <?= htmlspecialchars(
                            date('d M Y, H:i', strtotime($order['created_at'])),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                </div>

                <div class="col-md-5 text-md-end">

                    <span class="status-badge">
                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <div class="mt-3">

                        <span class="detail-label">ORDER TOTAL</span>

                        <strong class="fs-4 text-success">
                            KSh <?= number_format((float)$order['total_amount'], 2) ?>
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <?php if ($isCancelled): ?>

        <div class="alert alert-danger mb-4">

            <h5 class="fw-bold">
                <i class="fas fa-circle-xmark me-2"></i>
                Order Cancelled
            </h5>

            This order has been cancelled. Please contact Food Mail customer service if you need assistance.

        </div>

    <?php endif; ?>

    <div class="row g-4">

        <!-- LEFT COLUMN -->

        <div class="col-lg-7">

            <!-- DELIVERY PROGRESS -->

            <div class="card tracking-card mb-4">

                <div class="card-body p-4">

                    <h5 class="tracking-heading mb-4">
                        <i class="fas fa-route text-success me-2"></i>
                        Delivery Progress
                    </h5>

                    <?php if (!$isCancelled): ?>

                        <div class="progress-track">

                            <?php foreach ($progressSteps as $stepStatus => $step): ?>

                                <?php
                                $stepPosition = array_search(
                                    $stepStatus,
                                    $statusOrder,
                                    true
                                );

                                $stepCompleted = (
                                    $currentPosition !== false &&
                                    $stepPosition < $currentPosition
                                );

                                $stepCurrent = $stepStatus === $currentStatus;

                                $stepClass = $stepCompleted
                                    ? 'completed'
                                    : ($stepCurrent ? 'current' : '');

                                $stepIcon = $stepCompleted
                                    ? 'fa-check'
                                    : 'fa-circle';
                                ?>

                                <div class="progress-step <?= $stepClass ?>">

                                    <div class="progress-icon">
                                        <i class="fas <?= $stepIcon ?>"></i>
                                    </div>

                                    <div class="progress-title">
                                        <?= htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8') ?>

                                        <?php if ($stepCurrent): ?>
                                            <span class="badge bg-success ms-2">
                                                Current
                                            </span>
                                        <?php endif; ?>

                                    </div>

                                    <div class="progress-description">
                                        <?= htmlspecialchars($step['description'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <p class="text-muted mb-0">
                            Delivery progress is unavailable because this order was cancelled.
                        </p>

                    <?php endif; ?>

                    <?php if (!$isFinished): ?>

                        <div class="alert alert-light border mt-4 mb-0 small">

                            <i class="fas fa-sync-alt text-success me-2"></i>

                            This page refreshes automatically every 30 seconds to check for delivery updates.

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <!-- DELIVERY STAFF -->

            <div class="card tracking-card mb-4">

                <div class="card-body p-4">

                    <h5 class="tracking-heading mb-4">
                        <i class="fas fa-truck text-success me-2"></i>
                        Your Delivery Staff
                    </h5>

                    <?php if (!empty($order['delivery_user_id']) && !empty($order['delivery_staff_name'])): ?>

                        <div class="staff-profile">

                            <div class="staff-photo">

                                <?php if ($staffImageUrl !== ''): ?>

                                    <img
                                        src="<?= htmlspecialchars($staffImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="Delivery staff photograph"
                                    >

                                <?php else: ?>

                                    <i class="fas fa-user"></i>

                                <?php endif; ?>

                            </div>

                            <div>

                                <div class="detail-label">
                                    YOUR DELIVERY PERSON
                                </div>

                                <h5 class="fw-bold mb-1">
                                    <?= htmlspecialchars($order['delivery_staff_name'], ENT_QUOTES, 'UTF-8') ?>
                                </h5>

                                <span class="badge bg-success">
                                    Assigned to your order
                                </span>

                            </div>

                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                            <div>
                                <span class="detail-label">CONTACT DELIVERY STAFF</span>

                                <div class="detail-value">
                                    <?= htmlspecialchars(
                                        $order['delivery_staff_phone'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>
                            </div>

                            <?php if (!empty($order['delivery_staff_phone'])): ?>

                                <a
                                    href="tel:<?= htmlspecialchars($order['delivery_staff_phone'], ENT_QUOTES, 'UTF-8') ?>"
                                    class="btn btn-outline-success"
                                >
                                    <i class="fas fa-phone me-2"></i>
                                    Call Staff
                                </a>

                            <?php endif; ?>

                        </div>

                        <?php if (!empty($order['delivery_assigned_at'])): ?>

                            <div class="small text-muted mt-3">
                                Assigned on
                                <?= htmlspecialchars(
                                    date('d M Y, H:i', strtotime($order['delivery_assigned_at'])),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>

                        <?php endif; ?>

                    <?php else: ?>

                        <div class="text-center py-3">

                            <i class="fas fa-user-clock fa-3x text-muted mb-3"></i>

                            <h6 class="fw-bold">Delivery Staff Not Yet Assigned</h6>

                            <p class="text-muted small mb-0">
                                Your delivery person will appear here once Food Mail assigns your order.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <!-- ORDER HISTORY -->

            <div class="card tracking-card">

                <div class="card-body p-4">

                    <h5 class="tracking-heading mb-4">
                        <i class="fas fa-history text-success me-2"></i>
                        Order Activity
                    </h5>

                    <?php if (!$history): ?>

                        <p class="text-muted">
                            No order activity is available yet.
                        </p>

                    <?php else: ?>

                        <?php foreach ($history as $entry): ?>

                            <div class="history-item">

                                <div class="fw-bold">

                                    <?= htmlspecialchars(
                                        ucwords(str_replace('_', ' ', $entry['status'])),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                                <div class="small text-muted mt-1">

                                    <?= htmlspecialchars(
                                        $entry['description'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                                <div class="small text-muted mt-1">

                                    <i class="far fa-clock me-1"></i>

                                    <?= htmlspecialchars(
                                        date('d M Y, H:i', strtotime($entry['created_at'])),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <!-- RIGHT COLUMN -->

        <div class="col-lg-5">

            <!-- CUSTOMER DELIVERY ADDRESS -->

            <div class="card tracking-card mb-4">

                <div class="card-body p-4">

                    <h5 class="tracking-heading mb-4">
                        <i class="fas fa-location-dot text-success me-2"></i>
                        Delivery Information
                    </h5>

                    <div class="mb-3">

                        <span class="detail-label">CUSTOMER NAME</span>

                        <div class="detail-value">
                            <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>

                    </div>

                    <div class="mb-3">

                        <span class="detail-label">PHONE NUMBER</span>

                        <div class="detail-value">
                            <?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
                        </div>

                    </div>

                    <div class="mb-3">

                        <span class="detail-label">DELIVERY ADDRESS</span>

                        <div class="detail-value">
                            <?= nl2br(htmlspecialchars($order['delivery_address'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>

                    </div>

                    <?php if (!empty($order['delivery_notes'])): ?>

                        <div class="alert alert-light border mb-0">

                            <strong>Delivery Instructions</strong>

                            <div class="small mt-2">
                                <?= nl2br(htmlspecialchars($order['delivery_notes'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <!-- PAYMENT INFORMATION -->

            <div class="card tracking-card mb-4">

                <div class="card-body p-4">

                    <h5 class="tracking-heading mb-4">
                        <i class="fas fa-wallet text-success me-2"></i>
                        Payment Information
                    </h5>

                    <div class="mb-3">

                        <span class="detail-label">PAYMENT METHOD</span>

                        <div class="detail-value">
                            <?= htmlspecialchars(
                                strtoupper($order['payment_method'] ?? 'N/A'),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                    </div>

                    <div class="mb-3">

                        <span class="detail-label">TRANSACTION CODE</span>

                        <div class="detail-value">
                            <?= htmlspecialchars(
                                $order['transaction_code'] ?? 'Not available',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                    </div>

                    <div class="mb-3">

                        <span class="detail-label">PAYMENT STATUS</span>

                        <span class="badge bg-<?= $paymentInfo[1] ?>">
                            <?= htmlspecialchars($paymentInfo[0], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                    <div class="mb-3">

                        <span class="detail-label">AMOUNT</span>

                        <div class="detail-value text-success fs-5">
                            KSh <?= number_format((float)($order['payment_amount'] ?? 0), 2) ?>
                        </div>

                    </div>

                    <hr>

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
                        <strong>Total</strong>
                        <strong class="text-success">
                            KSh <?= number_format((float)$order['total_amount'], 2) ?>
                        </strong>
                    </div>

                </div>

            </div>

            <!-- ORDERED PRODUCTS -->

            <div class="card tracking-card">

                <div class="card-body p-4">

                    <h5 class="tracking-heading mb-3">
                        <i class="fas fa-shopping-basket text-success me-2"></i>
                        Your Products
                    </h5>

                    <?php foreach ($items as $item): ?>

                        <div class="d-flex justify-content-between gap-3 py-3 border-bottom">

                            <div>

                                <div class="fw-bold">
                                    <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="small text-muted">
                                    Quantity: <?= (int)$item['quantity'] ?>
                                </div>

                            </div>

                            <strong class="text-nowrap">
                                KSh <?= number_format((float)$item['total_price'], 2) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

            <!-- RATE DELIVERY -->

            <?php if ($currentStatus === 'delivered'): ?>

                <div class="card tracking-card mt-4">

                    <div class="card-body p-4 text-center">

                        <i class="fas fa-star text-warning fa-2x mb-3"></i>

                        <h5 class="fw-bold">How Was Your Delivery?</h5>

                        <p class="text-muted small">
                            Share your experience and rate our delivery service.
                        </p>

                        <a
                            href="rate_delivery.php?id=<?= (int)$order['id'] ?>"
                            class="btn btn-food w-100"
                        >
                            Rate Your Delivery
                        </a>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>

</main>

<footer class="food-footer">

    <div class="fw-bold text-success mb-1">
        FOOD MAIL
    </div>

    <small>
        Freshness delivered with care. Serving Kitale, Kenya.
    </small>

</footer>

</body>
</html>
