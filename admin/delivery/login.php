
<?php
session_start();

require_once __DIR__ . '/../../config/database.php';

if (!empty($_SESSION['delivery_id'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['delivery_login_csrf'])) {
    $_SESSION['delivery_login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['delivery_login_csrf'] ?? '';

    if (
        !is_string($token) ||
        !is_string($sessionToken) ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $token)
    ) {
        $error = 'Invalid security token. Refresh and try again.';

    } else {

        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($phone === '' || $password === '') {
            $error = 'Enter your phone number and password.';
        } else {

            try {
                $stmt = $pdo->prepare("
                    SELECT id, full_name, phone, password_hash, account_status
                    FROM users
                    WHERE phone = :phone
                      AND role = 'delivery'
                    LIMIT 1
                ");

                $stmt->execute(['phone' => $phone]);

                $staff = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    !$staff ||
                    !password_verify($password, $staff['password_hash'])
                ) {
                    $error = 'Incorrect phone number or password.';

                } elseif ($staff['account_status'] !== 'active') {
                    $error = 'Your account is suspended. Contact the administrator.';

                } else {
                    session_regenerate_id(true);

                    $_SESSION['delivery_id'] = (int)$staff['id'];
                    $_SESSION['delivery_name'] = $staff['full_name'];

                    unset($_SESSION['delivery_login_csrf']);

                    header('Location: index.php');
                    exit;
                }

            } catch (Throwable $e) {
                error_log('Food Mail delivery login: ' . $e->getMessage());
                $error = 'Unable to sign in. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Delivery Staff Login | Food Mail</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: #f1f6f2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 38px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 15px 50px rgba(0,0,0,.07);
        }

        .brand {
            color: #087a3e;
            font-size: 28px;
            font-weight: 900;
        }

        .brand-icon {
            color: #087a3e;
            font-size: 43px;
            margin-bottom: 12px;
        }

        .form-control {
            min-height: 49px;
        }

        .btn-food {
            background: #087a3e;
            color: white;
            min-height: 49px;
            font-weight: 700;
        }

        .btn-food:hover {
            background: #056331;
            color: white;
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="text-center mb-4">
        <div class="brand-icon">
            <i class="fas fa-truck-fast"></i>
        </div>

        <div class="brand">FOOD MAIL</div>

        <h4 class="fw-bold mt-3">Delivery Staff Portal</h4>

        <p class="text-muted">
            Sign in to manage your assigned deliveries.
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['delivery_login_csrf'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <div class="mb-3">
            <label class="form-label fw-semibold">Phone Number</label>

            <input
                type="tel"
                name="phone"
                class="form-control"
                placeholder="Enter your registered phone number"
                autocomplete="username"
                required
            >
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">Password</label>

            <input
                type="password"
                name="password"
                class="form-control"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-food w-100">
            <i class="fas fa-sign-in-alt me-2"></i>
            Sign In
        </button>

    </form>

    <div class="text-center mt-4">
        <small class="text-muted">
            Food Mail Delivery Management System
        </small>
    </div>

</div>

</body>
</html>
