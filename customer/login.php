
<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['customer_csrf'])) {
    $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['customer_csrf'], $token)) {
        $error = 'Invalid request. Refresh the page and try again.';
    } else {
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("
            SELECT id, full_name, phone, password_hash, account_status
            FROM users
            WHERE phone = :phone
              AND role = 'customer'
            LIMIT 1
        ");

        $stmt->execute(['phone' => $phone]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $customer &&
            $customer['account_status'] === 'active' &&
            password_verify($password, $customer['password_hash'])
        ) {
            session_regenerate_id(true);

            $_SESSION['customer_id'] = (int)$customer['id'];
            $_SESSION['customer_name'] = $customer['full_name'];

            header('Location: index.php');
            exit;
        }

        $error = 'Invalid login details or your account is inactive.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Login | Food Mail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f1f5f9;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .login-card {
            width: min(420px, 100%);
            background: white;
            padding: 32px;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0,0,0,.07);
        }
        .brand { color: #15803d; font-size: 28px; font-weight: 800; }
        .form-control { padding: 12px; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <div class="brand">FOOD MAIL</div>
        <h4 class="mt-2">Customer Login</h4>
        <p class="text-muted">Sign in to access your orders.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['customer_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone" class="form-control"
                   autocomplete="username" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                   autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-success w-100 py-2">
            Sign In
        </button>
    </form>

    <p class="text-center mt-4 mb-0">
        Don't have an account?
        <a href="register.php">Create an account</a>
    </p>
</div>
</body>
</html>
