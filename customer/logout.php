
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['customer_id'], $_SESSION['customer_name']);

session_regenerate_id(true);

header('Location: login.php');
exit;
