
<?php

/*
|--------------------------------------------------------------------------
| Food Mail delivery OTP encryption key
|--------------------------------------------------------------------------
| Keep this key private. Changing it will make existing OTPs unreadable.
*/

define(
    'FOOD_MAIL_OTP_KEY',
    hex2bin('a8f14c9d7e2b6035f19a4d8c2e7b5061c3d9f8427a5e1b6c0d4f9382a7615e0b')
);
