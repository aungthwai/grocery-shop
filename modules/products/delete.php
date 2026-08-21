<?php

session_start();

/*
|--------------------------------------------------------------------------
| DELETE PRODUCT
|--------------------------------------------------------------------------
| Safe deletion:
| - A product with sale history is not physically deleted.
| - A product with purchase history is not physically deleted.
| - Those products should be marked Inactive instead.
| - A product with no transaction history may be deleted.
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

require_once "../../includes/role_guard.php";
grocerEaseRequireAdmin();


require_once '../../config/database.php';

$basePath = '/grocery-shop';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$basePath}/modules/products/index.php");
    exit;
}

$sessionToken =
    isset($_SESSION['product_csrf_token']) &&
    is_string($_SESSION['product_csrf_token'])
        ? $_SESSION['product_csrf_token']
        : '';

$submittedToken =
    (string) ($_POST['csrf_token'] ?? '');

if (
    $sessionToken === '' ||
    $submittedToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    header(
        "Location: {$basePath}/modules/products/index.php?delete_error=1"
    );
    exit;
}

$productId = filter_input(
    INPUT_POST,
    'product_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($productId === false || $productId === null) {
    header(
        "Location: {$basePath}/modules/products/index.php?delete_error=1"
    );
    exit;
}

$imagePath = null;

try {

    mysqli_begin_transaction($conn);

    $productStmt = mysqli_prepare(
        $conn,
        "
            SELECT
                product_id,
                image
            FROM products
            WHERE product_id = ?
            LIMIT 1
            FOR UPDATE
        "
    );

    if (!$productStmt) {
        throw new Exception('Unable to prepare product lookup.');
    }

    mysqli_stmt_bind_param(
        $productStmt,
        'i',
        $productId
    );

    mysqli_stmt_execute($productStmt);

    $productResult =
        mysqli_stmt_get_result($productStmt);

    $product =
        $productResult
            ? mysqli_fetch_assoc($productResult)
            : null;

    mysqli_stmt_close($productStmt);

    if (!$product) {

        mysqli_rollback($conn);

        header(
            "Location: {$basePath}/modules/products/index.php"
        );

        exit;
    }

    $imagePath =
        !empty($product['image'])
            ? (string) $product['image']
            : null;


    /*
    |--------------------------------------------------------------------------
    | TRANSACTION HISTORY CHECK
    |--------------------------------------------------------------------------
    */

    $historyStmt = mysqli_prepare(
        $conn,
        "
            SELECT
                EXISTS(
                    SELECT 1
                    FROM sale_items
                    WHERE product_id = ?
                    LIMIT 1
                ) AS has_sales,

                EXISTS(
                    SELECT 1
                    FROM purchase_items
                    WHERE product_id = ?
                    LIMIT 1
                ) AS has_purchases
        "
    );

    if (!$historyStmt) {
        throw new Exception('Unable to prepare history check.');
    }

    mysqli_stmt_bind_param(
        $historyStmt,
        'ii',
        $productId,
        $productId
    );

    mysqli_stmt_execute($historyStmt);

    $historyResult =
        mysqli_stmt_get_result($historyStmt);

    $history =
        $historyResult
            ? mysqli_fetch_assoc($historyResult)
            : null;

    mysqli_stmt_close($historyStmt);

    if (
        !$history ||
        !empty($history['has_sales']) ||
        !empty($history['has_purchases'])
    ) {

        mysqli_rollback($conn);

        header(
            "Location: {$basePath}/modules/products/index.php?delete_blocked=1&id={$productId}"
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    $deleteStmt = mysqli_prepare(
        $conn,
        "
            DELETE FROM products
            WHERE product_id = ?
            LIMIT 1
        "
    );

    if (!$deleteStmt) {
        throw new Exception('Unable to prepare product deletion.');
    }

    mysqli_stmt_bind_param(
        $deleteStmt,
        'i',
        $productId
    );

    mysqli_stmt_execute($deleteStmt);

    if (mysqli_stmt_affected_rows($deleteStmt) !== 1) {

        mysqli_stmt_close($deleteStmt);

        throw new Exception('Product was not deleted.');
    }

    mysqli_stmt_close($deleteStmt);

    mysqli_commit($conn);


    /*
    |--------------------------------------------------------------------------
    | DELETE IMAGE AFTER DATABASE COMMIT
    |--------------------------------------------------------------------------
    */

    if ($imagePath !== null) {

        $normalizedPath =
            str_replace(
                '\\',
                '/',
                trim($imagePath)
            );

        if (
            strpos(
                $normalizedPath,
                'uploads/products/'
            ) === 0
        ) {

            $filename =
                basename($normalizedPath);

            if (
                $filename !== '' &&
                $filename !== '.' &&
                $filename !== '..'
            ) {

                $absolutePath =
                    dirname(__DIR__, 2) .
                    DIRECTORY_SEPARATOR .
                    'uploads' .
                    DIRECTORY_SEPARATOR .
                    'products' .
                    DIRECTORY_SEPARATOR .
                    $filename;

                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }
    }

    header(
        "Location: {$basePath}/modules/products/index.php?deleted=1"
    );

    exit;

} catch (Throwable $exception) {

    try {
        mysqli_rollback($conn);
    } catch (Throwable $rollbackException) {
        // Nothing else to do.
    }

    header(
        "Location: {$basePath}/modules/products/index.php?delete_error=1"
    );

    exit;
}
