
<?php
require_once __DIR__ . '/auth.php';

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        id,
        order_number,
        customer_name,
        customer_phone,
        delivery_address,
        delivery_notes,
        subtotal,
        delivery_fee,
        total_amount,
        status,
        created_at
    FROM orders
    WHERE id = :order_id
      AND delivery_user_id = :delivery_id
    LIMIT 1
");

$stmt->execute([
    'order_id' => $orderId,
    'delivery_id' => $deliveryId
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit('This order is not assigned to your account.');
}

$itemStmt = $pdo->prepare("
    SELECT product_name, quantity, unit_price, total_price
    FROM order_items
    WHERE order_id = :order_id
    ORDER BY id ASC
");

$itemStmt->execute(['order_id' => $orderId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$historyStmt = $pdo->prepare("
    SELECT status, description, created_at
    FROM order_status_history
    WHERE order_id = :order_id
    ORDER BY created_at DESC, id DESC
");

$historyStmt->execute(['order_id' => $orderId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_SESSION['delivery_message'] ?? '';
$messageType = $_SESSION['delivery_message_type'] ?? 'info';

unset(
    $_SESSION['delivery_message'],
    $_SESSION['delivery_message_type']
);

$statusLabels = [
    'ready' => 'Ready for Collection',
    'out_for_delivery' => 'Out for Delivery',
    'delivered' => 'Delivered'
];

$statusLabel = $statusLabels[$order['status']] ?? ucfirst($order['status']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Delivery Details | Food Mail</title>

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
            color: #26382e;
            font-family: Arial, sans-serif;
        }

        .page-container {
            max-width: 1100px;
            margin: auto;
            padding: 30px 18px;
        }

        .brand {
            color: #087a3e;
            font-weight: 900;
            font-size: 22px;
            text-decoration: none;
        }

        .detail-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,.04);
        }

        .label {
            display: block;
            color: #78867e;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .btn-food {
            background: #087a3e;
            color: white;
            font-weight: 700;
        }

        .btn-food:hover {
            background: #056331;
            color: white;
        }

        .otp-input {
            font-size: 25px;
            letter-spacing: 10px;
            text-align: center;
            font-weight: 800;
        }

        .history-item {
            border-left: 3px solid #cde5d4;
            padding: 0 0 18px 17px;
            margin-left: 7px;
        }
    </style>
</head>

<body>

<nav class="navbar bg-white border-bottom px-3 py-3">

    <div class="container-fluid">

        <a href="index.php" class="brand">
            <i class="fas fa-utensils me-2"></i>FOOD MAIL
        </a>

        <a href="index.php" class="btn btn-outline-success btn-sm">
            <i class="fas fa-arrow-left me-1"></i>
            My Deliveries
        </a>

    </div>

</nav>

<main class="page-container">

    <div class="mb-4">

        <h3 class="fw-bold">Delivery Details</h3>

        <p class="text-muted">
            Order <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?>
        </p>

    </div>

    <?php if ($message !== ''): ?>

        <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <div class="row g-4">

        <div class="col-lg-7">

            <div class="card detail-card mb-4">

                <div class="card-body p-4">

                    <div class="d-flex justify-content-between align-items-center mb-4">

                        <h5 class="fw-bold mb-0">Customer Information</h5>

                        <span class="badge bg-success">
                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                    <div class="mb-3">

                        <span class="label">CUSTOMER NAME</span>

                        <strong>
                            <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>

                    </div>

                    <div class="mb-3">

                        <span class="label">CUSTOMER PHONE</span>

                        <strong>
                            <?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>

                        <div class="mt-2">

                            <a
                                href="tel:<?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?>"
                                class="btn btn-sm btn-outline-success"
                            >
                                <i class="fas fa-phone me-1"></i>
                                Call Customer
                            </a>

                        </div>

                    </div>

                    <div class="mb-3">

                        <span class="label">DELIVERY ADDRESS</span>

                        <strong>
                            <?= nl2br(htmlspecialchars($order['delivery_address'], ENT_QUOTES, 'UTF-8')) ?>
                        </strong>

                    </div>

                    <?php if (!empty($order['delivery_notes'])): ?>

                        <div class="alert alert-info mb-0">

                            <strong>Customer Instructions</strong>

                            <div class="mt-2">
                                <?= nl2br(htmlspecialchars($order['delivery_notes'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card detail-card mb-4">

                <div class="card-body p-4">

                    <h5 class="fw-bold mb-3">Order Products</h5>

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

                                <?php foreach ($items as $item): ?>

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

                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span>KSh <?= number_format((float)$order['subtotal'], 2) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Delivery Fee</span>
                        <span>KSh <?= number_format((float)$order['delivery_fee'], 2) ?></span>
                    </div>

                    <div class="d-flex justify-content-between border-top pt-3">
                        <strong>Order Total</strong>
                        <strong class="text-success">
                            KSh <?= number_format((float)$order['total_amount'], 2) ?>
                        </strong>
                    </div>

                </div>

            </div>

            <div class="card detail-card">

                <div class="card-body p-4">

                    <h5 class="fw-bold mb-4">Order History</h5>

                    <?php if (!$history): ?>

                        <p class="text-muted">No order history available.</p>

                    <?php else: ?>

                        <?php foreach ($history as $entry): ?>

                            <div class="history-item">

                                <strong>
                                    <?= htmlspecialchars(
                                        ucwords(str_replace('_', ' ', $entry['status'])),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <div class="small text-muted mt-1">
                                    <?= htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="small text-muted mt-1">
                                    <?= htmlspecialchars(date('d M Y, H:i', strtotime($entry['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <div class="col-lg-5">

            <div class="card detail-card">

                <div class="card-body p-4">

                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-truck text-success me-2"></i>
                        Delivery Progress
                    </h5>

                    <?php if ($order['status'] === 'ready'): ?>

                        <p class="text-muted small">
                            Collect this order and start delivering it to the customer.
                        </p>

                        <form method="POST" action="update_status.php">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION['delivery_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                            >

                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                            <input type="hidden" name="new_status" value="out_for_delivery">

                            <button type="submit" class="btn btn-food w-100">
                                <i class="fas fa-truck me-2"></i>
                                Start Delivery
                            </button>

                        </form>

                    <?php elseif ($order['status'] === 'out_for_delivery'): ?>

                        <div class="alert alert-info">

                            <strong>
                                <i class="fas fa-shield-alt me-2"></i>
                                Customer confirmation required
                            </strong>

                            <p class="small mb-0 mt-2">
                                Ask the customer for their six-digit confirmation code only after handing over the order.
                            </p>

                        </div>

                        <form method="POST" action="update_status.php">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION['delivery_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                            >

                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                            <input type="hidden" name="new_status" value="delivered">

                            <label class="form-label fw-semibold">
                                Customer Delivery Code
                            </label>

                            <input
                                type="text"
                                name="delivery_otp"
                                class="form-control otp-input mb-3"
                                placeholder="000000"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                minlength="6"
                                maxlength="6"
                                autocomplete="off"
                                required
                            >

                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-check-circle me-2"></i>
                                Verify Code and Complete Delivery
                            </button>

                        </form>

                    <?php elseif ($order['status'] === 'delivered'): ?>

                        <div class="alert alert-success mb-0">

                            <i class="fas fa-check-circle me-2"></i>

                            Delivery successfully completed. Customer confirmation was verified.

                        </div>

                    <?php else: ?>

                        <div class="alert alert-secondary mb-0">
                            This order is not currently available for delivery.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</main>

</body>
</html>
