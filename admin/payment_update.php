
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

function paymentRedirectError($message, $paymentId = null)
{
    $_SESSION['payment_error'] = $message;

    if ($paymentId) {
        header('Location: payment_details.php?id=' . (int)$paymentId);
    } else {
        header('Location: payments.php');
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: payments.php');
    exit;
}

// CSRF protection.
$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['admin_csrf']) ||
    !is_string($csrf) ||
    !hash_equals($_SESSION['admin_csrf'], $csrf)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh the page and try again.');
}

$paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
$decision = $_POST['decision'] ?? '';

if (!$paymentId || $paymentId < 1) {
    paymentRedirectError('Invalid payment selected.');
}

if (!in_array($decision, ['confirm', 'reject'], true)) {
    paymentRedirectError('Invalid payment decision.', $paymentId);
}

$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($adminId < 1) {
    http_response_code(403);
    exit('Administrator session is invalid.');
}

try {

    $pdo->beginTransaction();

    // Lock the payment and related order.
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.order_id,
            p.payment_method,
            p.transaction_code,
            p.amount,
            p.status AS payment_status,
            o.total_amount,
            o.status AS order_status
        FROM payments p
        INNER JOIN orders o ON o.id = p.order_id
        WHERE p.id = ?
        FOR UPDATE
    ");

    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();

    if (!$payment) {
        $pdo->rollBack();
        paymentRedirectError('Payment record not found.');
    }

    // Only pending payments can be processed.
    if ($payment['payment_status'] !== 'pending') {
        $pdo->rollBack();
        paymentRedirectError('This payment has already been processed.', $paymentId);
    }

    // Do not confirm payments for cancelled orders.
    if ($payment['order_status'] === 'cancelled') {
        $pdo->rollBack();
        paymentRedirectError('Payment cannot be confirmed because the order is cancelled.', $paymentId);
    }

    if ($decision === 'confirm') {

        if (($_POST['verified'] ?? '') !== '1') {
            $pdo->rollBack();
            paymentRedirectError('You must confirm that the M-Pesa statement was checked.', $paymentId);
        }

        if ($payment['payment_method'] !== 'mpesa') {
            $pdo->rollBack();
            paymentRedirectError('This payment is not an M-Pesa payment.', $paymentId);
        }

        $transactionCode = trim((string)($payment['transaction_code'] ?? ''));

        if (
            $transactionCode === '' ||
            !preg_match('/^[A-Z0-9]{8,20}$/i', $transactionCode)
        ) {
            $pdo->rollBack();
            paymentRedirectError('The M-Pesa transaction code is missing or invalid.', $paymentId);
        }

        // Compare amounts using integer cents to avoid floating-point errors.
        $submittedCents = (int)round((float)$payment['amount'] * 100);
        $expectedCents = (int)round((float)$payment['total_amount'] * 100);

        if ($submittedCents !== $expectedCents) {
            $pdo->rollBack();
            paymentRedirectError('Payment amount does not match the order total.', $paymentId);
        }

        // Confirm only after manual verification.
        $update = $pdo->prepare("
            UPDATE payments
            SET
                status = 'confirmed',
                confirmed_by = ?,
                confirmed_at = CURRENT_TIMESTAMP,
                rejection_reason = NULL
            WHERE id = ? AND status = 'pending'
        ");

        $update->execute([$adminId, $paymentId]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Payment confirmation was not applied.');
        }

        $pdo->commit();

        $_SESSION['payment_success'] =
            'Payment confirmed successfully. The order can now proceed to preparation.';

    } else {

        $reason = trim($_POST['rejection_reason'] ?? '');

        if ($reason === '' || strlen($reason) > 500) {
            $pdo->rollBack();
            paymentRedirectError('Enter a rejection reason of no more than 500 characters.', $paymentId);
        }

        $update = $pdo->prepare("
            UPDATE payments
            SET
                status = 'rejected',
                confirmed_by = ?,
                confirmed_at = NULL,
                rejection_reason = ?
            WHERE id = ? AND status = 'pending'
        ");

        $update->execute([
            $adminId,
            $reason,
            $paymentId
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Payment rejection was not applied.');
        }

        $pdo->commit();

        $_SESSION['payment_success'] = 'Payment rejected. The rejection reason has been saved.';
    }

    header('Location: payment_details.php?id=' . $paymentId);
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Food Mail payment processing error: ' . $e->getMessage());

    paymentRedirectError(
        'Unable to process this payment. Check the server error log and try again.',
        $paymentId
    );
}
