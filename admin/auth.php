
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['admin_id']) ||
    ($_SESSION['admin_role'] ?? '') !== 'admin'
) {
    header('Location: login.php');
    exit;
}
