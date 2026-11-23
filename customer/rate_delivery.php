
<?php
require_once __DIR__ . '/auth.php';

if (empty($_SESSION['customer_csrf'])) {
    $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
}

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    http_response_code(400);
    exit('Invalid order.');
}

$orderStmt = $pdo->prepare("
    SELECT id, order_number, status
    FROM orders
    WHERE id = :id
      AND (user_id = :user_id OR customer_phone = :phone)
    LIMIT 1
");

$orderStmt->execute([
    'id' => $orderId,
    'user_id' => (int)$currentCustomer['id'],
    'phone' => $currentCustomer['phone']
]);

$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

if ($order['status'] !== 'delivered') {
    http_response_code(403);
    exit('You can only rate a delivered order.');
}

$existingStmt = $pdo->prepare("
    SELECT id
    FROM delivery_ratings
    WHERE order_id = :order_id
    LIMIT 1
");

$existingStmt->execute(['order_id' => $orderId]);

if ($existingStmt->fetch()) {
    header('Location: order_details.php?id=' . $orderId);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['customer_csrf'], $token)) {
        $error = 'Invalid request. Refresh the page and try again.';
    } else {
        $rating = filter_var(
            $_POST['rating'] ?? null,
            FILTER_VALIDATE_INT
        );

        $review = trim($_POST['review'] ?? '');

        if (!$rating || $rating < 1 || $rating > 5) {
            $error = 'Please select a rating from 1 to 5 stars.';
        } elseif (strlen($review) > 1000) {
            $error = 'Your review must not exceed 1,000 characters.';
        } else {
            try {
                $pdo->beginTransaction();

                // Lock the order and verify it is still delivered.
                $lockOrder = $pdo->prepare("
                    SELECT id
                    FROM orders
                    WHERE id = :id
                      AND status = 'delivered'
                      AND (user_id = :user_id OR customer_phone = :phone)
                    FOR UPDATE
                ");

                $lockOrder->execute([
                    'id' => $orderId,
                    'user_id' => (int)$currentCustomer['id'],
                    'phone' => $currentCustomer['phone']
                ]);

                if (!$lockOrder->fetch()) {
                    throw new RuntimeException('This order is not eligible for rating.');
                }

                $insert = $pdo->prepare("
                    INSERT INTO delivery_ratings
                        (order_id, user_id, rating, review, created_at)
                    VALUES
                        (:order_id, :user_id, :rating, :review, NOW())
                ");

                $insert->execute([
                    'order_id' => $orderId,
                    'user_id' => (int)$currentCustomer['id'],
                    'rating' => $rating,
                    'review' => $review !== '' ? $review : null
                ]);

                $pdo->commit();

                header('Location: order_details.php?id=' . $orderId);
                exit;

            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Your rating could not be saved. It may already have been submitted.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rate Delivery | Food Mail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: #f1f5f9;
            display: grid;
            place-items: center;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .rating-card {
            width: min(520px, 100%);
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0,0,0,.07);
        }
        .stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 8px;
            margin: 22px 0;
        }
        .stars input {
            display: none;
        }
        .stars label {
            font-size: 42px;
            color: #cbd5e1;
            cursor: pointer;
        }
        .stars label:hover,
        .stars label:hover ~ label,
        .stars input:checked ~ label {
            color: #f59e0b;
        }
    </style>
</head>
<body>

<div class="rating-card">
    <div class="text-center mb-3">
        <h2 class="text-success">FOOD MAIL</h2>
        <h4>Rate Your Delivery</h4>
        <p class="text-muted">
            Order <?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['customer_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

        <label class="form-label d-block text-center">
            How would you rate your delivery?
        </label>

        <div class="stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <input type="radio" name="rating"
                       id="star<?= $i; ?>"
                       value="<?= $i; ?>"
                       required>
                <label for="star<?= $i; ?>" title="<?= $i; ?> stars">&#9733;</label>
            <?php endfor; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Your Review (Optional)</label>
            <textarea name="review" class="form-control"
                      rows="4" maxlength="1000"
                      placeholder="Tell us about your delivery experience..."></textarea>
        </div>

        <button type="submit" class="btn btn-success w-100 py-2">
            Submit Rating
        </button>

        <a href="order_details.php?id=<?= (int)$orderId; ?>"
           class="btn btn-outline-secondary w-100 mt-2">
            Cancel
        </a>
    </form>
</div>

</body>
</html>
