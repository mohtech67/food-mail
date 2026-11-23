
<?php
$pageTitle = $pageTitle ?? 'Food Mail | Food & Water Delivery';
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Order delicious food and drinking water online with free delivery in Kitale.">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>

<div class="top-strip">
    <div class="container d-flex justify-content-between flex-wrap gap-2">
        <span><i class="fa-solid fa-truck-fast"></i> Free delivery within our Kitale service area</span>
        <span><i class="fa-solid fa-heart"></i> Freshness delivered with care</span>
    </div>
</div>

<nav class="navbar navbar-expand-lg main-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand food-logo" href="index.php">
            <span class="logo-icon"><i class="fa-solid fa-utensils"></i></span>
            FOOD<span>MAIL</span>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#foodNavbar"
                aria-controls="foodNavbar" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="foodNavbar">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">Home</a>
                </li>
                <li class="nav-item"><a class="nav-link" href="shop.php">Our Menu</a></li>
                <li class="nav-item"><a class="nav-link" href="shop.php?category=water">Drinking Water</a></li>
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>

            <div class="nav-actions">
                <a href="customer/login.php" class="login-link">Login</a>
                <a href="shop.php" class="btn btn-food">
                    Order Now <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</nav>
