
<?php

/*
|--------------------------------------------------------------------------
| Food Mail Payment Configuration
|--------------------------------------------------------------------------
| Configure the M-Pesa receiving number and delivery fee here.
| Payment verification is performed manually by the administrator.
*/

if (!defined('FOOD_MAIL_MPESA_NUMBER')) {
    define('FOOD_MAIL_MPESA_NUMBER', '0780618312');
}

if (!defined('FOOD_MAIL_PAYMENT_NAME')) {
    define('FOOD_MAIL_PAYMENT_NAME', 'Food Mail');
}

if (!defined('FOOD_MAIL_DELIVERY_FEE')) {
    define('FOOD_MAIL_DELIVERY_FEE', 0.00);
}
