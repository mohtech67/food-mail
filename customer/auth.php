
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, full_name, phone, email, account_status
    FROM users
    WHERE id = :id
      AND role = 'customer'
    LIMIT 1
");

$stmt->execute(['id' => (int)$_SESSION['customer_id']]);
$currentCustomer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentCustomer || $currentCustomer['account_status'] !== 'active') {
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
    session_regenerate_id(true);

    header('Location: login.php');
    exit;
}
