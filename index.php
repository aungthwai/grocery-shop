<?php

/*
|--------------------------------------------------------------------------
| GrocerEase Entry Point
|--------------------------------------------------------------------------
| Logged-out visitors go to Login.
| Logged-in users go directly to Dashboard.
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {

    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' =>
            !empty($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}


if (!empty($_SESSION["user_id"])) {

    header(
        "Location: /grocery-shop/modules/dashboard/index.php"
    );

    exit;
}


header(
    "Location: /grocery-shop/login.php"
);

exit;