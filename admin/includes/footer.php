
<?php
$footerYear = date('Y');

$footerCustomerLoggedIn = !empty($_SESSION['customer_id']);
?>

<!-- ==========================================
     FOOD MAIL FOOTER
========================================== -->

</div><!-- End .fm-page-content -->

<footer class="fm-footer">

    <div class="fm-footer-main">

        <div class="fm-footer-container">

            <div class="fm-footer-grid">

                <!-- BRAND INFORMATION -->

                <div class="fm-footer-brand">

                    <a href="index.php" class="fm-footer-logo">
                        <span class="fm-footer-logo-icon">
                            <i class="fas fa-utensils"></i>
                        </span>

                        <span>
                            FOOD<span>MAIL</span>
                        </span>
                    </a>

                    <p>
                        Bringing fresh meals and quality drinking water
                        closer to you. Enjoy convenient online ordering
                        and reliable delivery within our designated
                        Kitale service areas.
                    </p>

                    <div class="fm-footer-socials">

                        <a href="#" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>

                        <a href="#" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>

                        <a href="#" aria-label="TikTok">
                            <i class="fab fa-tiktok"></i>
                        </a>

                        <a href="#" aria-label="WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>

                    </div>

                </div>

                <!-- QUICK LINKS -->

                <div class="fm-footer-column">

                    <h4>Quick Links</h4>

                    <ul>
                        <li>
                            <a href="index.php">
                                <i class="fas fa-angle-right"></i>
                                Home
                            </a>
                        </li>

                        <li>
                            <a href="shop.php">
                                <i class="fas fa-angle-right"></i>
                                Our Shop
                            </a>
                        </li>

                        <li>
                            <a href="shop.php?category=water">
                                <i class="fas fa-angle-right"></i>
                                Drinking Water
                            </a>
                        </li>

                        <li>
                            <a href="index.php#how-it-works">
                                <i class="fas fa-angle-right"></i>
                                How It Works
                            </a>
                        </li>

                        <li>
                            <a href="index.php#contact">
                                <i class="fas fa-angle-right"></i>
                                Contact
                            </a>
                        </li>
                    </ul>

                </div>

                <!-- CUSTOMER SERVICES -->

                <div class="fm-footer-column">

                    <h4>Customer Services</h4>

                    <ul>

                        <?php if ($footerCustomerLoggedIn): ?>

                            <li>
                                <a href="customer/index.php">
                                    <i class="fas fa-angle-right"></i>
                                    My Account
                                </a>
                            </li>

                            <li>
                                <a href="customer/index.php">
                                    <i class="fas fa-angle-right"></i>
                                    My Orders
                                </a>
                            </li>

                            <li>
                                <a href="customer/logout.php">
                                    <i class="fas fa-angle-right"></i>
                                    Logout
                                </a>
                            </li>

                        <?php else: ?>

                            <li>
                                <a href="customer/login.php">
                                    <i class="fas fa-angle-right"></i>
                                    Customer Login
                                </a>
                            </li>

                            <li>
                                <a href="customer/register.php">
                                    <i class="fas fa-angle-right"></i>
                                    Create Account
                                </a>
                            </li>

                        <?php endif; ?>

                        <li>
                            <a href="cart.php">
                                <i class="fas fa-angle-right"></i>
                                My Shopping Cart
                            </a>
                        </li>

                        <li>
                            <a href="shop.php">
                                <i class="fas fa-angle-right"></i>
                                Browse Products
                            </a>
                        </li>

                    </ul>

                </div>

                <!-- CONTACT INFORMATION -->

                <div class="fm-footer-column fm-footer-contact">

                    <h4>Contact Information</h4>

                    <div class="fm-footer-contact-item">
                        <i class="fas fa-location-dot"></i>
                        <span>Kitale, Trans Nzoia County, Kenya</span>
                    </div>

                    <div class="fm-footer-contact-item">
                        <i class="fas fa-truck-fast"></i>
                        <span>Delivery within designated Kitale areas</span>
                    </div>

                    <div class="fm-footer-contact-item">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Convenient online ordering</span>
                    </div>

                    <div class="fm-footer-contact-item">
                        <i class="fas fa-heart"></i>
                        <span>Freshness delivered with care</span>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- BOTTOM FOOTER -->

    <div class="fm-footer-bottom">

        <div class="fm-footer-bottom-inner">

            <p>
                &copy; <?= htmlspecialchars($footerYear, ENT_QUOTES, 'UTF-8'); ?>
                <strong>Food Mail.</strong>
                All rights reserved.
            </p>

            <p>
                Made with <i class="fas fa-heart"></i> for our customers.
            </p>

        </div>

    </div>

</footer>

<!-- ==========================================
     FOOTER STYLES
========================================== -->

<style>
    .fm-footer {
        width: 100%;
        background: #14291f;
        color: #d1ded5;
        margin-top: 0;
    }

    .fm-footer-main {
        padding: 65px 0 50px;
    }

    .fm-footer-container {
        width: min(1200px, calc(100% - 40px));
        margin: auto;
    }

    .fm-footer-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1.2fr 1.3fr;
        gap: 40px;
    }

    /* BRAND */

    .fm-footer-logo {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: #fff;
        font-family: 'Outfit', sans-serif;
        font-size: 23px;
        font-weight: 800;
        text-decoration: none;
        margin-bottom: 18px;
    }

    .fm-footer-logo > span:last-child > span {
        color: #4ade80;
    }

    .fm-footer-logo-icon {
        width: 43px;
        height: 43px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: #15803d;
        color: #fff;
        font-size: 19px;
    }

    .fm-footer-brand p {
        color: #a8b9ad;
        font-size: 13px;
        line-height: 1.9;
        max-width: 310px;
        margin: 0 0 20px;
    }

    /* SOCIAL LINKS */

    .fm-footer-socials {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .fm-footer-socials a {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: rgba(255,255,255,.08);
        color: #fff;
        text-decoration: none;
        transition: .2s;
    }

    .fm-footer-socials a:hover {
        background: #15803d;
        color: #fff;
        transform: translateY(-2px);
    }

    /* FOOTER COLUMNS */

    .fm-footer-column h4 {
        color: #fff;
        font-family: 'Outfit', sans-serif;
        font-size: 16px;
        font-weight: 700;
        margin: 5px 0 22px;
    }

    .fm-footer-column ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .fm-footer-column li {
        margin-bottom: 13px;
    }

    .fm-footer-column li a {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        color: #a8b9ad;
        text-decoration: none;
        font-size: 13px;
        transition: .2s;
    }

    .fm-footer-column li a i {
        color: #4ade80;
        font-size: 11px;
    }

    .fm-footer-column li a:hover {
        color: #fff;
        padding-left: 4px;
    }

    /* CONTACT */

    .fm-footer-contact-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 17px;
        color: #a8b9ad;
        font-size: 13px;
        line-height: 1.6;
    }

    .fm-footer-contact-item i {
        color: #4ade80;
        width: 17px;
        margin-top: 3px;
        flex-shrink: 0;
    }

    /* BOTTOM */

    .fm-footer-bottom {
        background: #0f2118;
        border-top: 1px solid rgba(255,255,255,.08);
    }

    .fm-footer-bottom-inner {
        width: min(1200px, calc(100% - 40px));
        min-height: 65px;
        margin: auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
    }

    .fm-footer-bottom p {
        color: #94a89a;
        font-size: 12px;
        margin: 0;
    }

    .fm-footer-bottom strong {
        color: #fff;
    }

    .fm-footer-bottom .fa-heart {
        color: #4ade80;
        margin: 0 3px;
    }

    /* TABLET */

    @media(max-width: 950px) {
        .fm-footer-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 35px;
        }
    }

    /* MOBILE */

    @media(max-width: 550px) {
        .fm-footer-main {
            padding: 45px 0 30px;
        }

        .fm-footer-container {
            width: calc(100% - 32px);
        }

        .fm-footer-grid {
            grid-template-columns: 1fr 1fr;
            gap: 30px 20px;
        }

        .fm-footer-brand {
            grid-column: 1 / -1;
        }

        .fm-footer-brand p {
            max-width: 100%;
        }

        .fm-footer-column h4 {
            font-size: 14px;
            margin-bottom: 16px;
        }

        .fm-footer-column li a,
        .fm-footer-contact-item {
            font-size: 12px;
        }

        .fm-footer-bottom-inner {
            width: calc(100% - 32px);
            flex-direction: column;
            justify-content: center;
            text-align: center;
            padding: 15px 0;
            gap: 8px;
        }
    }

    @media(max-width: 350px) {
        .fm-footer-grid {
            grid-template-columns: 1fr;
        }

        .fm-footer-brand {
            grid-column: auto;
        }
    }
</style>

<!-- BOOTSTRAP JAVASCRIPT -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
