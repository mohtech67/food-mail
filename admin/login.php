
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (
    !empty($_SESSION['admin_id']) &&
    ($_SESSION['admin_role'] ?? '') === 'admin'
) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['admin_login_csrf'], $token)
    ) {
        $error = 'Invalid security token. Refresh and try again.';
    } elseif (
        ($_SESSION['login_locked_until'] ?? 0) > time()
    ) {
        $error = 'Too many attempts. Please wait before trying again.';
    } else {

        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !is_string($password)
        ) {
            $error = 'Invalid email address or password.';
        } else {

            try {

                $stmt = $pdo->prepare(
                    "SELECT id, full_name, email, password_hash,
                            role, account_status
                     FROM users
                     WHERE email = :email
                     LIMIT 1"
                );

                $stmt->execute(['email' => $email]);
                $admin = $stmt->fetch();

                if (
                    $admin &&
                    $admin['role'] === 'admin' &&
                    $admin['account_status'] === 'active' &&
                    password_verify($password, $admin['password_hash'])
                ) {

                    if (password_needs_rehash(
                        $admin['password_hash'],
                        PASSWORD_DEFAULT
                    )) {
                        $rehash = $pdo->prepare(
                            "UPDATE users SET password_hash = :hash
                             WHERE id = :id"
                        );

                        $rehash->execute([
                            'hash' => password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            ),
                            'id' => $admin['id']
                        ]);
                    }

                    session_regenerate_id(true);

                    $_SESSION['admin_id'] = (int)$admin['id'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_role'] = 'admin';

                    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));

                    unset(
                        $_SESSION['admin_login_csrf'],
                        $_SESSION['login_attempts'],
                        $_SESSION['login_locked_until']
                    );

                    header('Location: index.php');
                    exit;

                } else {

                    $attempts = (int)($_SESSION['login_attempts'] ?? 0) + 1;
                    $_SESSION['login_attempts'] = $attempts;

                    if ($attempts >= 5) {
                        $_SESSION['login_locked_until'] = time() + 300;
                        $_SESSION['login_attempts'] = 0;
                    }

                    $error = 'Incorrect email or password, or account inactive.';
                }

            } catch (PDOException $e) {

                error_log('Food Mail admin login: ' . $e->getMessage());

                $error = 'Unable to log in at the moment. Please try again.';
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
    <title>Admin Login | Food Mail</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="admin-login-body">

<div class="login-card">

    <div class="login-brand">
        <i class="fa-solid fa-utensils"></i>
        FOOD MAIL
    </div>

    <div class="login-heading">
        <h2>Welcome Back!</h2>
        <p>Sign in to manage your Food Mail business.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">

        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['admin_login_csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="mb-3">
            <label class="form-label">Administrator Email</label>
            <div class="login-input">
                <i class="fa-solid fa-envelope"></i>
                <input type="email"
                       name="email"
                       class="form-control"
                       placeholder="Enter your email"
                       autocomplete="username"
                       required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="login-input">
                <i class="fa-solid fa-lock"></i>
                <input type="password"
                       name="password"
                       class="form-control"
                       placeholder="Enter your password"
                       autocomplete="current-password"
                       required>
            </div>
        </div>

        <button type="submit" class="btn btn-admin w-100">
            Sign In <i class="fa-solid fa-arrow-right ms-2"></i>
        </button>

    </form>

    <div class="login-footer">
        <a href="../index.php">
            <i class="fa-solid fa-arrow-left"></i>
            Return to Food Mail Website
        </a>
        <p>Authorized administrators only.</p>
    </div>

</div>

</body>
</html>
