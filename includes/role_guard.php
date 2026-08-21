<?php

/*
|--------------------------------------------------------------------------
| GrocerEase Role Guard
|--------------------------------------------------------------------------
| Database roles:
| - Admin = Administrator
| - Staff = Cashier
|--------------------------------------------------------------------------
*/

if (!function_exists('grocerEaseRequireAdmin')) {

    function grocerEaseRequireAdmin(bool $jsonResponse = false): void
    {
        $role =
            (string) ($_SESSION['role'] ?? '');

        if ($role === 'Admin') {
            return;
        }

        if ($jsonResponse) {

            http_response_code(403);

            header(
                'Content-Type: application/json; charset=UTF-8'
            );

            echo json_encode(
                [
                    'success' => false,
                    'message' =>
                        'Administrator access is required for this action.'
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            exit;
        }

        header(
            'Location: /grocery-shop/modules/dashboard/index.php?access=denied'
        );

        exit;
    }

}
