
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

$fullName = '';
$phone = '';
$email = '';

if (empty($_SESSION['customer_register_csrf'])) {
    $_SESSION['customer_register_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    try {
        if (!hash_equals($_SESSION['customer_register_csrf'], $csrfToken)) {
            throw new Exception('Your session has expired. Refresh the page and try again.');
        }

        if ($fullName === '' || $phone === '' || $password === '') {
            throw new Exception('Please complete all required fields.');
        }

        if (strlen($fullName) < 3 || strlen($fullName) > 150) {
            throw new Exception('Enter a valid full name.');
        }

        // Accept common Kenyan mobile number formats.
        $phoneDigits = preg_replace('/[\s\-]/', '', $phone);

        if (!preg_match('/^(?:07|01)\d{8}$|^\+254[17]\d{8}$|^254[17]\d{8}$/', $phoneDigits)) {
            throw new Exception('Enter a valid Kenyan phone number, for example 0712345678.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Enter a valid email address.');
        }

        if (strlen($password) < 12) {
            throw new Exception('Your password must contain at least 12 characters.');
        }

        if ($password !== $confirmPassword) {
            throw new Exception('The passwords do not match.');
        }

        // Check phone separately to avoid reusing PDO named parameters.
        $phoneCheck = $pdo->prepare(
            "SELECT id FROM users WHERE phone = :phone LIMIT 1"
        );
        $phoneCheck->execute(['phone' => $phone]);

        if ($phoneCheck->fetch()) {
            throw new Exception('This phone number already has an account. Please log in.');
        }

        // Check email separately. Empty email addresses are stored as NULL.
        if ($email !== '') {
            $emailCheck = $pdo->prepare(
                "SELECT id FROM users WHERE email = :email LIMIT 1"
            );
            $emailCheck->execute(['email' => $email]);

            if ($emailCheck->fetch()) {
                throw new Exception('This email address is already registered.');
            }
        }

        $pdo->beginTransaction();

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insertUser = $pdo->prepare(
            "INSERT INTO users
                (full_name, email, phone, password_hash, role, account_status, created_at, updated_at)
             VALUES
                (:full_name, :email, :phone, :password_hash, 'customer', 'active', NOW(), NOW())"
        );

        $insertUser->execute([
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone,
            'password_hash' => $passwordHash
        ]);

        $customerId = (int) $pdo->lastInsertId();

        // Attach previous guest orders made using this exact phone number.
        $linkOrders = $pdo->prepare(
            "UPDATE orders
             SET user_id = :customer_id
             WHERE customer_phone = :customer_phone
               AND user_id IS NULL"
        );

        $linkOrders->execute([
            'customer_id' => $customerId,
            'customer_phone' => $phone
        ]);

        $pdo->commit();

        // Regenerate session ID after successful registration.
        session_regenerate_id(true);

        $_SESSION['customer_id'] = $customerId;
        $_SESSION['customer_name'] = $fullName;
        $_SESSION['customer_phone'] = $phone;

        unset($_SESSION['customer_register_csrf']);

        header('Location: index.php');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Food Mail customer registration error: ' . $e->getMessage());

        if ($e->getCode() === '23000') {
            $error = 'This phone number or email address is already registered.';
        } else {
            $error = 'Registration could not be completed. Please try again.';
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Food Mail</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f1f5f9;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 35px 15px;
        }

        .register-card {
            width: 100%;
            max-width: 480px;
            background: #fff;
            padding: 34px 32px;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.09);
        }

        .brand {
            text-align: center;
            color: #07833e;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .page-title {
            text-align: center;
            font-size: 23px;
            margin-bottom: 8px;
            color: #111827;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 26px;
            font-size: 15px;
        }

        .form-label {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .form-control {
            min-height: 49px;
            border: 1px solid #d7dee8;
            border-radius: 8px;
            padding: 11px 13px;
        }

        .form-control:focus {
            border-color: #07833e;
            box-shadow: 0 0 0 3px rgba(7, 131, 62, 0.12);
        }

        .form-text {
            color: #64748b;
            font-size: 13px;
        }

        .btn-register {
            width: 100%;
            min-height: 50px;
            border: none;
            border-radius: 8px;
            background: #07833e;
            color: white;
            font-weight: 700;
            font-size: 16px;
            margin-top: 10px;
        }

        .btn-register:hover {
            background: #056b33;
            color: white;
        }

        .login-link {
            text-align: center;
            margin-top: 23px;
            font-size: 14px;
        }

        .login-link a {
            color: #07833e;
            font-weight: 700;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            body {
                padding: 15px 10px;
            }

            .register-card {
                padding: 27px 20px;
            }
        }
    </style>
</head>

<body>

<div class="register-card">

    <div class="brand">FOOD MAIL</div>

    <h1 class="page-title">Create Your Account</h1>

    <p class="subtitle">
        Register to manage and track your orders.
    </p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="on">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['customer_register_csrf'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <div class="mb-3">
            <label for="full_name" class="form-label">Full Name</label>
            <input
                type="text"
                class="form-control"
                id="full_name"
                name="full_name"
                value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
                maxlength="150"
                autocomplete="name"
                required
            >
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input
                type="tel"
                class="form-control"
                id="phone"
                name="phone"
                value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="0712345678"
                autocomplete="tel"
                required
            >
            <div class="form-text">
                Use the same phone number used for your previous orders.
            </div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email Address (optional)</label>
            <input
                type="email"
                class="form-control"
                id="email"
                name="email"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                maxlength="190"
                autocomplete="email"
            >
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input
                type="password"
                class="form-control"
                id="password"
                name="password"
                minlength="12"
                autocomplete="new-password"
                required
            >
            <div class="form-text">Use at least 12 characters.</div>
        </div>

        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input
                type="password"
                class="form-control"
                id="confirm_password"
                name="confirm_password"
                minlength="12"
                autocomplete="new-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-register">
            Create Account
        </button>

    </form>

    <div class="login-link">
        Already have an account?
        <a href="login.php">Login here</a>
    </div>

</div>

</body>
</html>
