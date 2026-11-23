
<?php
session_start();

$pageTitle = 'Order Received | Food Mail';

$orderNumber = $_SESSION['last_order_number'] ?? null;
$orderPhone = $_SESSION['last_order_phone'] ?? null;
$orderTotal = $_SESSION['last_order_total'] ?? null;

if (!$orderNumber || !$orderPhone || $orderTotal === null) {
    header('Location: index.php');
    exit;
}

$orderNumber = (string) $orderNumber;
$orderPhone = (string) $orderPhone;
$orderTotal = (float) $orderTotal;

unset(
    $_SESSION['last_order_number'],
    $_SESSION['last_order_phone'],
    $_SESSION['last_order_total']
);

require_once __DIR__ . '/includes/header.php';
?>

<main class="order-success-page">

    <section class="container">

        <div class="success-card">

            <div class="success-icon">
                <i class="fa-solid fa-check"></i>
            </div>

            <span class="section-eyebrow">
                Order successfully received
            </span>

            <h1>Thank You for Ordering!</h1>

            <p class="success-description">
                Your order has been successfully submitted to Food Mail.
                Our team will review your payment and contact you
                to coordinate your delivery.
            </p>

            <div class="success-order-number">

                <span>YOUR ORDER REFERENCE</span>

                <strong>
                    <?= htmlspecialchars(
                        $orderNumber,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            </div>

            <div class="success-details">

                <div>
                    <span>Contact Phone</span>

                    <strong>
                        <?= htmlspecialchars(
                            $orderPhone,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                </div>

                <div>
                    <span>Order Total</span>

                    <strong>
                        KSh <?= number_format($orderTotal, 2) ?>
                    </strong>
                </div>

                <div>
                    <span>Payment Status</span>

                    <strong class="pending-payment">
                        <i class="fa-solid fa-clock"></i>
                        Awaiting Confirmation
                    </strong>
                </div>

            </div>

            <div class="success-notice">

                <i class="fa-solid fa-circle-info"></i>

                <div>
                    <strong>Payment verification in progress</strong>

                    <p>
                        Your M-Pesa transaction code has been submitted.
                        Food Mail will manually verify your payment before
                        confirming your order. Please keep your M-Pesa
                        confirmation message until verification is complete.
                    </p>
                </div>

            </div>

            <div class="success-actions">

                <a href="index.php" class="btn btn-food">
                    <i class="fa-solid fa-house"></i>
                    Back to Home
                </a>

                <a href="shop.php" class="btn btn-outline-food">
                    <i class="fa-solid fa-basket-shopping"></i>
                    Continue Shopping
                </a>

            </div>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
