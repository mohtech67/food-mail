
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CSRF protection
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Validate payment ID
|--------------------------------------------------------------------------
*/
$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$paymentId || $paymentId < 1) {
    http_response_code(400);
    exit('Invalid payment ID.');
}

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/
function paymentEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function paymentMoney($amount): string
{
    return 'KSh ' . number_format((float)$amount, 2);
}

/*
|--------------------------------------------------------------------------
| Fetch payment, order and verification details
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        p.id AS payment_id,
        p.order_id,
        p.payment_method,
        p.transaction_code,
        p.amount AS payment_amount,
        p.status AS payment_status,
        p.confirmed_by,
        p.confirmed_at,
        p.rejection_reason,
        p.created_at AS payment_created_at,

        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.delivery_address,
        o.delivery_notes,
        o.subtotal,
        o.delivery_fee,
        o.total_amount AS order_total,
        o.status AS order_status,
        o.created_at AS order_created_at,

        u.full_name AS verifier_name

    FROM payments p
    INNER JOIN orders o ON o.id = p.order_id
    LEFT JOIN users u ON u.id = p.confirmed_by
    WHERE p.id = :payment_id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['payment_id' => $paymentId]);

$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    http_response_code(404);
    exit('Payment record not found.');
}

/*
|--------------------------------------------------------------------------
| Calculate amount difference safely
|--------------------------------------------------------------------------
*/
$paymentCents = (int) round((float)$payment['payment_amount'] * 100);
$orderCents = (int) round((float)$payment['order_total'] * 100);

$amountMatches = ($paymentCents === $orderCents);
$amountDifference = abs($paymentCents - $orderCents) / 100;

$isPending = $payment['payment_status'] === 'pending';
$isCancelled = $payment['order_status'] === 'cancelled';
$isConfirmed = $payment['payment_status'] === 'confirmed';
$isRejected = $payment['payment_status'] === 'rejected';

$pageTitle = 'Payment Details';

/*
|--------------------------------------------------------------------------
| Load admin header
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content">

    <div class="page-header">
        <div>
            <h1>Payment Details</h1>
            <p>Review and verify customer payment information.</p>
        </div>

        <a href="payments.php" class="btn btn-secondary">
            Back to Payments
        </a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= paymentEscape($_SESSION['success']); ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= paymentEscape($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- PAYMENT STATUS -->
    <div class="card payment-status-card">
        <div class="card-body">
            <div>
                <h3>Payment Status</h3>

                <?php if ($isConfirmed): ?>
                    <span class="status-badge status-confirmed">Confirmed</span>
                <?php elseif ($isRejected): ?>
                    <span class="status-badge status-rejected">Rejected</span>
                <?php else: ?>
                    <span class="status-badge status-pending">Pending Verification</span>
                <?php endif; ?>
            </div>

            <div>
                <h3>Order Number</h3>
                <strong><?= paymentEscape($payment['order_number']); ?></strong>
            </div>

            <div>
                <h3>Submitted Amount</h3>
                <strong><?= paymentMoney($payment['payment_amount']); ?></strong>
            </div>
        </div>
    </div>

    <div class="details-grid">

        <!-- PAYMENT INFORMATION -->
        <div class="card">
            <div class="card-header">
                <h2>Payment Information</h2>
            </div>

            <div class="card-body">
                <table class="details-table">
                    <tr>
                        <th>Payment ID</th>
                        <td>#<?= (int)$payment['payment_id']; ?></td>
                    </tr>

                    <tr>
                        <th>Payment Method</th>
                        <td><?= paymentEscape(strtoupper($payment['payment_method'])); ?></td>
                    </tr>

                    <tr>
                        <th>M-Pesa Transaction Code</th>
                        <td>
                            <strong>
                                <?= paymentEscape($payment['transaction_code'] ?: 'Not provided'); ?>
                            </strong>
                        </td>
                    </tr>

                    <tr>
                        <th>Submitted Amount</th>
                        <td><?= paymentMoney($payment['payment_amount']); ?></td>
                    </tr>

                    <tr>
                        <th>Order Total</th>
                        <td><?= paymentMoney($payment['order_total']); ?></td>
                    </tr>

                    <tr>
                        <th>Submitted On</th>
                        <td><?= paymentEscape($payment['payment_created_at']); ?></td>
                    </tr>

                    <tr>
                        <th>Payment Status</th>
                        <td>
                            <?= paymentEscape(ucfirst($payment['payment_status'])); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- CUSTOMER INFORMATION -->
        <div class="card">
            <div class="card-header">
                <h2>Customer Information</h2>
            </div>

            <div class="card-body">
                <table class="details-table">
                    <tr>
                        <th>Customer Name</th>
                        <td><?= paymentEscape($payment['customer_name']); ?></td>
                    </tr>

                    <tr>
                        <th>Phone Number</th>
                        <td><?= paymentEscape($payment['customer_phone']); ?></td>
                    </tr>

                    <tr>
                        <th>Delivery Address</th>
                        <td><?= paymentEscape($payment['delivery_address']); ?></td>
                    </tr>

                    <tr>
                        <th>Delivery Notes</th>
                        <td><?= paymentEscape($payment['delivery_notes'] ?: 'No notes'); ?></td>
                    </tr>

                    <tr>
                        <th>Order Date</th>
                        <td><?= paymentEscape($payment['order_created_at']); ?></td>
                    </tr>

                    <tr>
                        <th>Order Status</th>
                        <td><?= paymentEscape(ucwords(str_replace('_', ' ', $payment['order_status']))); ?></td>
                    </tr>
                </table>
            </div>
        </div>

    </div>

    <!-- AMOUNT VERIFICATION -->
    <div class="card">
        <div class="card-header">
            <h2>Amount Verification</h2>
        </div>

        <div class="card-body">

            <table class="details-table">
                <tr>
                    <th>Order Subtotal</th>
                    <td><?= paymentMoney($payment['subtotal']); ?></td>
                </tr>

                <tr>
                    <th>Delivery Fee</th>
                    <td><?= paymentMoney($payment['delivery_fee']); ?></td>
                </tr>

                <tr>
                    <th>Expected Payment</th>
                    <td><strong><?= paymentMoney($payment['order_total']); ?></strong></td>
                </tr>

                <tr>
                    <th>Customer Submitted</th>
                    <td><strong><?= paymentMoney($payment['payment_amount']); ?></strong></td>
                </tr>
            </table>

            <?php if ($amountMatches): ?>
                <div class="alert alert-success">
                    The submitted amount matches the order total.
                    You must still verify the transaction in your M-Pesa statement.
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <strong>Amount mismatch!</strong>
                    The difference is <?= paymentMoney($amountDifference); ?>.
                    Do not confirm this payment until the discrepancy is resolved.
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- REJECTION REASON -->
    <?php if ($isRejected && !empty($payment['rejection_reason'])): ?>
        <div class="card">
            <div class="card-header">
                <h2>Payment Rejection Information</h2>
            </div>

            <div class="card-body">
                <div class="alert alert-danger">
                    <strong>Reason for rejection:</strong><br>
                    <?= nl2br(paymentEscape($payment['rejection_reason'])); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- VERIFICATION HISTORY -->
    <?php if ($isConfirmed || $isRejected): ?>
        <div class="card">
            <div class="card-header">
                <h2>Verification History</h2>
            </div>

            <div class="card-body">
                <table class="details-table">
                    <tr>
                        <th>Reviewed By</th>
                        <td><?= paymentEscape($payment['verifier_name'] ?: 'Administrator'); ?></td>
                    </tr>

                    <tr>
                        <th>Reviewed On</th>
                        <td><?= paymentEscape($payment['confirmed_at'] ?: 'Not available'); ?></td>
                    </tr>

                    <tr>
                        <th>Final Decision</th>
                        <td><?= paymentEscape(ucfirst($payment['payment_status'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- PAYMENT ACTIONS -->
    <?php if ($isPending): ?>

        <div class="card">
            <div class="card-header">
                <h2>Review Payment</h2>
            </div>

            <div class="card-body">

                <?php if ($isCancelled): ?>
                    <div class="alert alert-warning">
                        This order has been cancelled. You cannot confirm its payment.
                        You may reject the pending payment after reviewing it.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Before confirming, independently check your M-Pesa statement.
                        Verify the transaction code, recipient and amount.
                    </div>

                    <!-- CONFIRM PAYMENT -->
                    <form action="payment_update.php" method="POST"
                          onsubmit="return confirm('Have you independently verified this M-Pesa transaction?');">

                        <input type="hidden" name="csrf_token"
                               value="<?= paymentEscape($csrfToken); ?>">

                        <input type="hidden" name="payment_id"
                               value="<?= (int)$payment['payment_id']; ?>">

                        <input type="hidden" name="action" value="confirm">

                        <div class="form-check">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="verified"
                                   name="verified"
                                   value="1"
                                   required>

                            <label class="form-check-label" for="verified">
                                I have independently checked the M-Pesa statement
                                and verified that this transaction code belongs to
                                this payment and the full amount was received.
                            </label>
                        </div>

                        <button type="submit"
                                class="btn btn-success"
                                <?= !$amountMatches ? 'disabled' : ''; ?>>
                            Confirm Payment
                        </button>

                        <?php if (!$amountMatches): ?>
                            <p class="text-danger">
                                Confirmation is disabled because the submitted amount
                                does not match the order total.
                            </p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <hr>

                <!-- REJECT PAYMENT -->
                <h3>Reject Payment</h3>

                <form action="payment_update.php" method="POST"
                      onsubmit="return confirm('Are you sure you want to reject this payment?');">

                    <input type="hidden" name="csrf_token"
                           value="<?= paymentEscape($csrfToken); ?>">

                    <input type="hidden" name="payment_id"
                           value="<?= (int)$payment['payment_id']; ?>">

                    <input type="hidden" name="action" value="reject">

                    <div class="form-group">
                        <label for="rejection_reason">Reason for Rejection</label>

                        <textarea
                            name="rejection_reason"
                            id="rejection_reason"
                            class="form-control"
                            rows="4"
                            maxlength="500"
                            required
                            placeholder="Explain why this payment is being rejected..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        Reject Payment
                    </button>
                </form>

            </div>
        </div>

    <?php else: ?>

        <div class="card">
            <div class="card-body">
                <div class="alert alert-info">
                    This payment has already been processed.
                    No further payment action is available.
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<style>
.payment-status-card .card-body {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 25px;
}

.payment-status-card h3 {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 8px;
}

.payment-status-card strong {
    font-size: 18px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.details-table {
    width: 100%;
    border-collapse: collapse;
}

.details-table th,
.details-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
}

.details-table th {
    width: 42%;
    color: #64748b;
    font-weight: 600;
}

.status-badge {
    display: inline-block;
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}

.status-confirmed {
    background: #dcfce7;
    color: #166534;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.form-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 18px 0;
}

.form-check-input {
    margin-top: 5px;
}

.form-group {
    margin: 18px 0;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
}

.card {
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .details-grid {
        grid-template-columns: 1fr;
    }

    .payment-status-card .card-body {
        flex-direction: column;
    }

    .details-table th,
    .details-table td {
        display: block;
        width: 100%;
    }

    .details-table th {
        padding-bottom: 3px;
        border-bottom: 0;
    }

    .details-table td {
        padding-top: 3px;
    }
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
