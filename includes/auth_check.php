<?php

/*
|--------------------------------------------------------------------------
| GrocerEase Authentication Check
|--------------------------------------------------------------------------
| Safe shared guard for protected pages.
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['user_id']) ||
    filter_var(
        $_SESSION['user_id'],
        FILTER_VALIDATE_INT
    ) === false
) {

    $_SESSION = [];

    header(
        'Location: /grocery-shop/login.php'
    );

    exit;
}
