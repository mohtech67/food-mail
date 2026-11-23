
<?php
$pageTitle = $pageTitle ?? 'Fresh Food and Water Delivery in Kitale';
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isCustomerLoggedIn = !empty($_SESSION['customer_id']);
$customerName = $_SESSION['customer_name'] ?? 'My Account';

function foodMailNavActive($pages, $currentPage): string
{
    return in_array($currentPage, (array)$pages, true) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | Food Mail</title>

    <meta name="description" content="Order fresh meals and quality drinking water online with Food Mail. Convenient delivery within our designated Kitale service areas.">

    <meta name="theme-color" content="#15803d">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 110px;
        }

        body {
            margin: 0;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            font-family: 'DM Sans', Arial, sans-serif;
        }

        .fm-topbar {
            width: 100%;
            background: #f0fdf4;
            border-bottom: 1px solid #dcfce7;
        }

        .fm-topbar-inner {
            width: min(1200px, calc(100% - 40px));
            min-height: 37px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .fm-topbar-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #166534;
            font-size: 12px;
            font-weight: 600;
        }

        .fm-main-header {
            width: 100%;
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #fff;
            border-bottom: 1px solid #edf0ed;
            box-shadow: 0 3px 12px rgba(0,0,0,.035);
        }

        .fm-navbar {
            width: min(1200px, calc(100% - 40px));
            min-height: 75px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 22px;
        }

        .fm-brand {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .fm-brand-icon {
            color: #15803d;
            font-size: 25px;
        }

        .fm-brand-name {
            color: #172b21;
            font-family: 'Outfit', sans-serif;
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .fm-brand-name span {
            color: #15803d;
        }

        .fm-navigation {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 23px;
            flex: 1;
            min-width: 0;
        }

        .fm-navigation a {
            position: relative;
            display: inline-block;
            padding: 10px 0;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .fm-navigation a:hover,
        .fm-navigation a.active {
            color: #15803d;
        }

        .fm-navigation a.active::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 3px;
            width: 100%;
            height: 2px;
            background: #15803d;
            border-radius: 5px;
        }

        .fm-header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 9px;
            flex-shrink: 0;
        }

        .fm-login-link,
        .fm-register-link,
        .fm-order-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .fm-login-link {
            padding: 10px;
            color: #15803d;
        }

        .fm-register-link {
            padding: 10px 13px;
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 7px;
        }

        .fm-order-link {
            padding: 11px 16px;
            color: #fff;
            background: #15803d;
            border-radius: 7px;
        }

        .fm-register-link:hover {
            background: #dcfce7;
            color: #14532d;
        }

        .fm-order-link:hover {
            background: #14532d;
            color: #fff;
        }

        .fm-account-link {
            color: #166534;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .fm-menu-toggle {
            display: none;
            width: 41px;
            height: 41px;
            border: 1px solid #dcfce7;
            border-radius: 7px;
            color: #166534;
            background: #f0fdf4;
            font-size: 18px;
            cursor: pointer;
        }

        .fm-page-content {
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }

        @media(max-width: 1100px) {
            .fm-navbar {
                gap: 13px;
            }

            .fm-navigation {
                gap: 14px;
            }

            .fm-navigation a {
                font-size: 13px;
            }

            .fm-brand-name {
                font-size: 21px;
            }

            .fm-register-link,
            .fm-order-link {
                padding: 9px 10px;
                font-size: 12px;
            }
        }

        @media(max-width: 850px) {
            .fm-navbar {
                min-height: 68px;
                flex-wrap: wrap;
                gap: 10px;
            }

            .fm-menu-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                order: 3;
                flex-shrink: 0;
            }

            .fm-header-actions {
                margin-left: auto;
            }

            .fm-navigation {
                display: none;
                order: 4;
                flex: 0 0 100%;
                width: 100%;
                padding: 10px 0 15px;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                border-top: 1px solid #edf0ed;
            }

            .fm-navigation.fm-menu-open {
                display: flex;
            }

            .fm-navigation a {
                display: block;
                width: 100%;
                padding: 12px 5px;
                border-bottom: 1px solid #f1f5f9;
            }

            .fm-navigation a.active::after {
                display: none;
            }
        }

        @media(max-width: 520px) {
            .fm-topbar-inner,
            .fm-navbar {
                width: calc(100% - 24px);
            }

            .fm-topbar-item {
                font-size: 10px;
            }

            .fm-topbar-item:last-child {
                display: none;
            }

            .fm-brand-name {
                font-size: 20px;
            }

            .fm-login-link {
                display: none;
            }

            .fm-register-link {
                padding: 8px 9px;
                font-size: 11px;
            }

            .fm-order-link {
                padding: 9px 10px;
                font-size: 11px;
            }

            .fm-menu-toggle {
                width: 36px;
                height: 36px;
            }
        }

        @media(max-width: 370px) {
            .fm-register-link {
                display: none;
            }
        }
    </style>
</head>

<body>

<div class="fm-topbar">
    <div class="fm-topbar-inner">
        <div class="fm-topbar-item">
            <i class="fas fa-truck-fast"></i>
            <span>Free delivery within our Kitale service area</span>
        </div>

        <div class="fm-topbar-item">
            <i class="fas fa-heart"></i>
            <span>Freshness delivered with care</span>
        </div>
    </div>
</div>

<header class="fm-main-header">
    <div class="fm-navbar">

        <a href="index.php" class="fm-brand">
            <i class="fas fa-utensils fm-brand-icon"></i>
            <span class="fm-brand-name">FOOD<span>MAIL</span></span>
        </a>

        <nav class="fm-navigation" id="fmNavigation">
            <a href="index.php"
               class="<?= foodMailNavActive('index.php', $currentPage); ?>">
                Home
            </a>

            <a href="shop.php"
               class="<?= foodMailNavActive('shop.php', $currentPage); ?>">
                Our Menu
            </a>

            <a href="shop.php?category=water">Drinking Water</a>
            <a href="index.php#how-it-works">How It Works</a>
            <a href="index.php#contact">Contact</a>
        </nav>

        <div class="fm-header-actions">
            <?php if ($isCustomerLoggedIn): ?>
                <a href="customer/index.php" class="fm-account-link">
                    <i class="fas fa-user-circle"></i>
                    <?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php else: ?>
                <a href="customer/login.php" class="fm-login-link">
                    <i class="fas fa-user"></i>
                    Login
                </a>

                <a href="customer/register.php" class="fm-register-link">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </a>
            <?php endif; ?>

            <a href="shop.php" class="fm-order-link">
                Order Now
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <button type="button"
                class="fm-menu-toggle"
                id="fmMenuToggle"
                aria-label="Toggle navigation"
                aria-controls="fmNavigation"
                aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>

    </div>
</header>

<div class="fm-page-content">

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuButton = document.getElementById('fmMenuToggle');
    const navigation = document.getElementById('fmNavigation');

    if (!menuButton || !navigation) return;

    function closeMenu() {
        navigation.classList.remove('fm-menu-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.innerHTML = '<i class="fas fa-bars"></i>';
    }

    menuButton.addEventListener('click', function () {
        const isOpen = navigation.classList.toggle('fm-menu-open');

        menuButton.setAttribute('aria-expanded', String(isOpen));
        menuButton.innerHTML = isOpen
            ? '<i class="fas fa-times"></i>'
            : '<i class="fas fa-bars"></i>';
    });

    navigation.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 850) closeMenu();
    });
});
</script>
</div>
</body>
</html>
