<?php

session_start();

/*
|--------------------------------------------------------------------------
| PURCHASE MANAGEMENT PAGE
|--------------------------------------------------------------------------
| Stage 7
|
| Database functionality from Stage 2 is preserved.
|
| Stage 3 implemented:
| - Temporary purchase item management.
| - Add selected products to the purchase items table.
| - Quantity validation.
| - Unit cost validation.
| - Duplicate product prevention.
| - Remove temporary purchase items.
| - Line total calculation.
|
| Stage 4 implemented:
| - Temporary item count.
| - Subtotal calculation.
| - Total calculation.
|
| Stage 5 implemented:
| - Validate supplier.
| - Validate purchase date.
| - Validate invoice number.
| - Validate temporary purchase items.
| - Save purchase inside a database transaction.
| - Save purchase items inside the same transaction.
| - Update product stock inside the same transaction.
| - Roll back the entire purchase if any operation fails.
| - Prevent duplicate purchase records through the existing unique
|   invoice_no database constraint and client-side submission protection.
|
| Important:
| - Stage 3/4 purchase items remain in browser memory until Save Purchase.
| - Stage 5 is the first stage that persists the purchase.
| Stage 7 implemented:
| - Load the latest saved purchases from the database.
| - Display invoice, date, supplier, item count, amount, and payment status.
| - Keep the existing Recent Purchases high-fidelity table layout.
|
| Important:
| - Stage 7 only reads existing purchase data.
| - No purchase is inserted, updated, or deleted by Stage 7.
| - No stock is changed by Stage 7.
|
| Stage 8 implemented:
| - Print the latest saved purchase as a print-friendly invoice.
| - Retrieve saved purchase header and item data from the existing database.
| - Open the browser print dialog without modifying purchase data.
|
| - No edit/delete purchase functionality is implemented yet.
| - Paid/Due purchase status is supported. Supplier settlement tracking is handled separately.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        (
            isset($_POST['save_purchase']) ||
            isset($_POST['print_invoice'])
        )
    ) {
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'success' => false,
            'message' => 'Your session has expired. Please log in again.'
        ]);

        exit;
    }

    header("Location: ../../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

require_once "../../includes/role_guard.php";

$purchaseNeedsJsonAccessResponse =
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (
        isset($_POST['save_purchase']) ||
        isset($_POST['print_invoice'])
    );

grocerEaseRequireAdmin(
    $purchaseNeedsJsonAccessResponse
);


require_once "../../config/database.php";


/*
|--------------------------------------------------------------------------
| BASE PATH / PAGE INFORMATION
|--------------------------------------------------------------------------
*/

$basePath = "/grocery-shop";
$pageTitle = "Purchases Management";


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['purchase_csrf_token']) ||
    !is_string($_SESSION['purchase_csrf_token'])
) {
    $_SESSION['purchase_csrf_token'] =
        bin2hex(random_bytes(32));
}

$purchaseCsrfToken =
    $_SESSION['purchase_csrf_token'];


/*
|--------------------------------------------------------------------------
| STAGE 8 - PROCESS PRINT INVOICE REQUEST
|--------------------------------------------------------------------------
|
| Printing is read-only. The server retrieves the selected saved purchase
| and its purchase items, then returns JSON to the browser.
| No INSERT, UPDATE, DELETE, or stock modification occurs here.
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['print_invoice'])
) {

    header('Content-Type: application/json; charset=UTF-8');

    try {

        $purchaseIdValue =
            filter_input(
                INPUT_POST,
                'purchase_id',
                FILTER_VALIDATE_INT
            );

        $purchaseId =
            (
                $purchaseIdValue !== false &&
                $purchaseIdValue !== null
            )
                ? (int) $purchaseIdValue
                : 0;

        if ($purchaseId <= 0) {
            throw new Exception(
                'Please select a saved purchase to print.'
            );
        }

        $purchaseStmt = mysqli_prepare(
            $conn,
            "
                SELECT
                    p.purchase_id,
                    p.invoice_no,
                    p.purchase_date,
                    p.total_amount,
                    p.payment_status,
                    p.remarks,
                    p.created_at,
                    s.supplier_name,
                    s.company,
                    s.phone AS supplier_phone,
                    s.email AS supplier_email,
                    s.address AS supplier_address,
                    st.shop_name,
                    st.owner_name,
                    st.phone AS shop_phone,
                    st.email AS shop_email,
                    st.address AS shop_address,
                    st.logo AS shop_logo
                FROM purchases p
                INNER JOIN suppliers s
                    ON p.supplier_id = s.supplier_id
                LEFT JOIN settings st
                    ON st.setting_id = (
                        SELECT MIN(setting_id)
                        FROM settings
                    )
                WHERE p.purchase_id = ?
                LIMIT 1
            "
        );

        if (!$purchaseStmt) {
            throw new Exception(
                'Unable to prepare the invoice information.'
            );
        }

        mysqli_stmt_bind_param(
            $purchaseStmt,
            'i',
            $purchaseId
        );

        if (!mysqli_stmt_execute($purchaseStmt)) {
            mysqli_stmt_close($purchaseStmt);
            throw new Exception(
                'Unable to load the purchase for printing.'
            );
        }

        $purchaseResult = mysqli_stmt_get_result($purchaseStmt);
        $purchase =
            $purchaseResult
                ? mysqli_fetch_assoc($purchaseResult)
                : null;

        mysqli_stmt_close($purchaseStmt);

        if (!$purchase) {
            throw new Exception(
                'The selected purchase could not be found.'
            );
        }

        $itemStmt = mysqli_prepare(
            $conn,
            "
                SELECT
                    pi.item_id,
                    pi.product_id,
                    pi.quantity,
                    pi.purchase_price,
                    pi.subtotal,
                    pr.product_name,
                    pr.barcode,
                    pr.unit
                FROM purchase_items pi
                INNER JOIN products pr
                    ON pi.product_id = pr.product_id
                WHERE pi.purchase_id = ?
                ORDER BY pi.item_id ASC
            "
        );

        if (!$itemStmt) {
            throw new Exception(
                'Unable to prepare the invoice items.'
            );
        }

        mysqli_stmt_bind_param(
            $itemStmt,
            'i',
            $purchaseId
        );

        if (!mysqli_stmt_execute($itemStmt)) {
            mysqli_stmt_close($itemStmt);
            throw new Exception(
                'Unable to load the purchase items for printing.'
            );
        }

        $itemResult = mysqli_stmt_get_result($itemStmt);
        $items = [];

        if ($itemResult) {
            while ($item = mysqli_fetch_assoc($itemResult)) {
                $items[] = $item;
            }
        }

        mysqli_stmt_close($itemStmt);

        if (count($items) === 0) {
            throw new Exception(
                'This purchase has no saved items to print.'
            );
        }

        echo json_encode([
            'success' => true,
            'purchase' => $purchase,
            'items' => $items
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;

    } catch (Throwable $exception) {

        echo json_encode([
            'success' => false,
            'message' => $exception->getMessage()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| STAGE 5 - PROCESS PURCHASE SAVE REQUEST
|--------------------------------------------------------------------------
|
| The normal page uses GET.
|
| Save Purchase uses an AJAX POST request to this same page.
|
| The temporary purchase items are sent from JavaScript as JSON.
|
| No page redesign is required.
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_purchase'])
) {

    header('Content-Type: application/json; charset=UTF-8');

    $transactionStarted = false;

    try {

        /*
        |--------------------------------------------------------------------------
        | RECEIVE PURCHASE DETAILS
        |--------------------------------------------------------------------------
        */

        $supplierIdValue =
            filter_input(
                INPUT_POST,
                'supplier_id',
                FILTER_VALIDATE_INT
            );

        $supplierId =
            (
                $supplierIdValue !== false &&
                $supplierIdValue !== null
            )
                ? (int) $supplierIdValue
                : 0;

        $purchaseDate =
            trim(
                (string) (
                    $_POST['purchase_date'] ?? ''
                )
            );

        $invoiceNo =
            trim(
                (string) (
                    $_POST['invoice_no'] ?? ''
                )
            );

        $purchaseItemsJson =
            (string) (
                $_POST['purchase_items'] ?? ''
            );

        $clientTotalValue =
            trim(
                (string) (
                    $_POST['purchase_total'] ?? ''
                )
            );


        $paymentStatus =
            trim(
                (string) (
                    $_POST['payment_status'] ?? 'Paid'
                )
            );

        $remarks =
            trim(
                (string) (
                    $_POST['remarks'] ?? ''
                )
            );

        $csrfToken =
            (string) (
                $_POST['csrf_token'] ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | VALIDATE CSRF TOKEN
        |--------------------------------------------------------------------------
        */

        if (
            $csrfToken === '' ||
            !hash_equals(
                $purchaseCsrfToken,
                $csrfToken
            )
        ) {
            throw new Exception(
                'Invalid form request. Please refresh the page and try again.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE SUPPLIER
        |--------------------------------------------------------------------------
        */

        if ($supplierId <= 0) {

            throw new Exception(
                'Please select a supplier before saving the purchase.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE PURCHASE DATE
        |--------------------------------------------------------------------------
        */

        $purchaseDateObject =
            DateTime::createFromFormat(
                'Y-m-d',
                $purchaseDate
            );

        $purchaseDateErrors =
            DateTime::getLastErrors();

        $purchaseDateValid =
            $purchaseDateObject !== false &&
            (
                $purchaseDateErrors === false ||
                (
                    $purchaseDateErrors['warning_count'] === 0 &&
                    $purchaseDateErrors['error_count'] === 0
                )
            ) &&
            $purchaseDateObject->format('Y-m-d') === $purchaseDate;

        if (!$purchaseDateValid) {

            throw new Exception(
                'Please enter a valid purchase date.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE INVOICE NUMBER
        |--------------------------------------------------------------------------
        |
        | The existing database structure defines invoice_no as:
        | VARCHAR(50) NOT NULL UNIQUE.
        |--------------------------------------------------------------------------
        */

        if ($invoiceNo === '') {

            throw new Exception(
                'PO / Invoice Number is required.'
            );

        }

        if (mb_strlen($invoiceNo) > 50) {

            throw new Exception(
                'PO / Invoice Number cannot exceed 50 characters.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE PAYMENT STATUS
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $paymentStatus,
                ['Paid', 'Due'],
                true
            )
        ) {
            throw new Exception(
                'Please select a valid payment status.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE REMARKS
        |--------------------------------------------------------------------------
        */

        if (mb_strlen($remarks) > 1000) {
            throw new Exception(
                'Remarks cannot exceed 1000 characters.'
            );
        }

        if ($remarks === '') {
            $remarks = null;
        }


        /*
        |--------------------------------------------------------------------------
        | DECODE TEMPORARY PURCHASE ITEMS
        |--------------------------------------------------------------------------
        */

        if ($purchaseItemsJson === '') {

            throw new Exception(
                'Please add at least one product before saving the purchase.'
            );

        }

        $decodedPurchaseItems =
            json_decode(
                $purchaseItemsJson,
                true
            );

        if (
            !is_array($decodedPurchaseItems) ||
            empty($decodedPurchaseItems)
        ) {

            throw new Exception(
                'Please add at least one valid purchase item before saving.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE EVERY PURCHASE ITEM
        |--------------------------------------------------------------------------
        |
        | The database stores quantity as INT and purchase_price/subtotal
        | as DECIMAL(10,2).
        |
        | Therefore the server validates the values independently instead
        | of trusting the browser.
        |--------------------------------------------------------------------------
        */

        $validatedItems = [];

        $calculatedTotal = 0.00;

        $usedProductIds = [];


        foreach (
            $decodedPurchaseItems
            as $itemIndex => $item
        ) {

            if (!is_array($item)) {

                throw new Exception(
                    'Invalid purchase item data.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | PRODUCT ID
            |--------------------------------------------------------------------------
            */

            $productIdValue =
                $item['product_id'] ?? null;

            if (
                $productIdValue === null ||
                filter_var(
                    $productIdValue,
                    FILTER_VALIDATE_INT
                ) === false
            ) {

                throw new Exception(
                    'Invalid product in purchase item ' .
                    ((int) $itemIndex + 1) .
                    '.'
                );

            }

            $productId =
                (int) $productIdValue;

            if ($productId <= 0) {

                throw new Exception(
                    'Invalid product in purchase item ' .
                    ((int) $itemIndex + 1) .
                    '.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | DUPLICATE PRODUCT CHECK
            |--------------------------------------------------------------------------
            */

            if (
                isset(
                    $usedProductIds[$productId]
                )
            ) {

                throw new Exception(
                    'The same product cannot appear more than once in a purchase.'
                );

            }

            $usedProductIds[$productId] = true;


            /*
            |--------------------------------------------------------------------------
            | QUANTITY
            |--------------------------------------------------------------------------
            |
            | purchase_items.quantity is INT in the database.
            |--------------------------------------------------------------------------
            */

            $quantityValue =
                $item['quantity'] ?? null;

            if (
                $quantityValue === null ||
                $quantityValue === '' ||
                !is_numeric($quantityValue)
            ) {

                throw new Exception(
                    'Quantity must be a valid positive whole number.'
                );

            }

            $quantityNumber =
                (float) $quantityValue;

            if (
                !is_finite($quantityNumber) ||
                $quantityNumber <= 0 ||
                floor($quantityNumber) !== $quantityNumber
            ) {

                throw new Exception(
                    'Quantity must be a valid positive whole number.'
                );

            }

            $quantity =
                (int) $quantityNumber;


            /*
            |--------------------------------------------------------------------------
            | UNIT COST
            |--------------------------------------------------------------------------
            */

            $unitCostValue =
                $item['unit_cost'] ?? null;

            if (
                $unitCostValue === null ||
                $unitCostValue === '' ||
                !is_numeric($unitCostValue)
            ) {

                throw new Exception(
                    'Unit cost must be a valid non-negative number.'
                );

            }

            $unitCost =
                (float) $unitCostValue;

            if (
                !is_finite($unitCost) ||
                $unitCost < 0
            ) {

                throw new Exception(
                    'Unit cost must be a valid non-negative number.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | DECIMAL(10,2) VALIDATION
            |--------------------------------------------------------------------------
            |
            | DECIMAL(10,2) can store at most 8 digits before the decimal
            | point and 2 digits after it.
            |--------------------------------------------------------------------------
            */

            $unitCost =
                round(
                    $unitCost,
                    2
                );

            if ($unitCost > 99999999.99) {

                throw new Exception(
                    'Unit cost is too large.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | LINE TOTAL
            |--------------------------------------------------------------------------
            */

            $lineTotal =
                round(
                    $quantity * $unitCost,
                    2
                );

            if ($lineTotal > 99999999.99) {

                throw new Exception(
                    'Purchase item subtotal is too large.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | PURCHASE TOTAL
            |--------------------------------------------------------------------------
            */

            $calculatedTotal +=
                $lineTotal;

            $calculatedTotal =
                round(
                    $calculatedTotal,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | STORE ONLY TRUSTED VALUES
            |--------------------------------------------------------------------------
            */

            $validatedItems[] = [

                'product_id' =>
                    $productId,

                'quantity' =>
                    $quantity,

                'purchase_price' =>
                    $unitCost,

                'subtotal' =>
                    $lineTotal

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE FINAL TOTAL
        |--------------------------------------------------------------------------
        */

        if ($calculatedTotal > 99999999.99) {

            throw new Exception(
                'Purchase total is too large.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY CLIENT CALCULATION
        |--------------------------------------------------------------------------
        |
        | Stage 4 calculates the total in browser memory.
        |
        | The server independently recalculates the total and verifies that
        | the browser value agrees with it.
        |--------------------------------------------------------------------------
        */

        if ($clientTotalValue !== '') {

            if (!is_numeric($clientTotalValue)) {

                throw new Exception(
                    'Invalid purchase total.'
                );

            }

            $clientTotal =
                (float) $clientTotalValue;

            if (
                !is_finite($clientTotal) ||
                $clientTotal < 0
            ) {

                throw new Exception(
                    'Invalid purchase total.'
                );

            }

            $clientTotal =
                round(
                    $clientTotal,
                    2
                );


            if (
                abs(
                    $clientTotal -
                    $calculatedTotal
                ) > 0.009
            ) {

                throw new Exception(
                    'The purchase total changed unexpectedly. Please review the purchase items and try again.'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | BEGIN DATABASE TRANSACTION
        |--------------------------------------------------------------------------
        */

        if (!mysqli_begin_transaction($conn)) {

            throw new Exception(
                'Unable to start the purchase transaction.'
            );

        }

        $transactionStarted = true;


        /*
        |--------------------------------------------------------------------------
        | VERIFY SUPPLIER INSIDE TRANSACTION
        |--------------------------------------------------------------------------
        */

        $supplierStmt =
            mysqli_prepare(
                $conn,
                "
                    SELECT supplier_id
                    FROM suppliers
                    WHERE supplier_id = ?
                    LIMIT 1
                    FOR UPDATE
                "
            );

        if (!$supplierStmt) {

            throw new Exception(
                'Unable to prepare supplier verification.'
            );

        }

        mysqli_stmt_bind_param(
            $supplierStmt,
            "i",
            $supplierId
        );

        if (
            !mysqli_stmt_execute(
                $supplierStmt
            )
        ) {

            mysqli_stmt_close(
                $supplierStmt
            );

            throw new Exception(
                'Unable to verify the selected supplier.'
            );

        }

        $supplierResult =
            mysqli_stmt_get_result(
                $supplierStmt
            );

        $supplierExists =
            $supplierResult &&
            mysqli_num_rows(
                $supplierResult
            ) > 0;

        mysqli_stmt_close(
            $supplierStmt
        );

        if (!$supplierExists) {

            throw new Exception(
                'The selected supplier does not exist.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY INVOICE NUMBER HAS NOT ALREADY BEEN USED
        |--------------------------------------------------------------------------
        |
        | The database also has a UNIQUE constraint on invoice_no.
        |
        | This check gives the user a clearer message before the INSERT.
        |--------------------------------------------------------------------------
        */

        $invoiceCheckStmt =
            mysqli_prepare(
                $conn,
                "
                    SELECT purchase_id
                    FROM purchases
                    WHERE invoice_no = ?
                    LIMIT 1
                    FOR UPDATE
                "
            );

        if (!$invoiceCheckStmt) {

            throw new Exception(
                'Unable to verify the invoice number.'
            );

        }

        mysqli_stmt_bind_param(
            $invoiceCheckStmt,
            "s",
            $invoiceNo
        );

        if (
            !mysqli_stmt_execute(
                $invoiceCheckStmt
            )
        ) {

            mysqli_stmt_close(
                $invoiceCheckStmt
            );

            throw new Exception(
                'Unable to verify the invoice number.'
            );

        }

        $invoiceCheckResult =
            mysqli_stmt_get_result(
                $invoiceCheckStmt
            );

        $invoiceAlreadyExists =
            $invoiceCheckResult &&
            mysqli_num_rows(
                $invoiceCheckResult
            ) > 0;

        mysqli_stmt_close(
            $invoiceCheckStmt
        );

        if ($invoiceAlreadyExists) {

            throw new Exception(
                'This PO / Invoice Number already exists. Please use a different invoice number.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | PREPARE PURCHASE INSERT
        |--------------------------------------------------------------------------
        | Payment status is stored exactly as Paid or Due.
        | Remarks are optional.
        |--------------------------------------------------------------------------
        */

        $purchaseInsertStmt =
            mysqli_prepare(
                $conn,
                "
                    INSERT INTO purchases
                    (
                        supplier_id,
                        invoice_no,
                        purchase_date,
                        total_amount,
                        payment_status,
                        remarks,
                        created_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        NOW()
                    )
                "
            );

        if (!$purchaseInsertStmt) {

            throw new Exception(
                'Unable to prepare the purchase record.'
            );

        }


        $purchaseTotal =
            $calculatedTotal;


        mysqli_stmt_bind_param(
            $purchaseInsertStmt,
            "issdss",
            $supplierId,
            $invoiceNo,
            $purchaseDate,
            $purchaseTotal,
            $paymentStatus,
            $remarks
        );


        if (
            !mysqli_stmt_execute(
                $purchaseInsertStmt
            )
        ) {

            $databaseError =
                mysqli_stmt_error(
                    $purchaseInsertStmt
                );

            mysqli_stmt_close(
                $purchaseInsertStmt
            );

            /*
            |--------------------------------------------------------------------------
            | FRIENDLY DUPLICATE INVOICE ERROR
            |--------------------------------------------------------------------------
            */

            if (
                strpos(
                    strtolower($databaseError),
                    'duplicate'
                ) !== false
            ) {

                throw new Exception(
                    'This PO / Invoice Number already exists. Please use a different invoice number.'
                );

            }

            throw new Exception(
                'Unable to save the purchase record.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | GET NEW PURCHASE ID
        |--------------------------------------------------------------------------
        */

        $purchaseId =
            mysqli_insert_id(
                $conn
            );

        mysqli_stmt_close(
            $purchaseInsertStmt
        );


        if ($purchaseId <= 0) {

            throw new Exception(
                'Unable to obtain the new purchase ID.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | PREPARE PURCHASE ITEM INSERT
        |--------------------------------------------------------------------------
        */

        $purchaseItemInsertStmt =
            mysqli_prepare(
                $conn,
                "
                    INSERT INTO purchase_items
                    (
                        purchase_id,
                        product_id,
                        quantity,
                        purchase_price,
                        subtotal
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                "
            );

        if (!$purchaseItemInsertStmt) {

            throw new Exception(
                'Unable to prepare the purchase item record.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | PREPARE PRODUCT STOCK CHECK
        |--------------------------------------------------------------------------
        |
        | FOR UPDATE locks the product row during the transaction.
        |
        | This prevents another transaction from changing the same stock
        | between reading it and updating it.
        |--------------------------------------------------------------------------
        */

        $productStockStmt =
            mysqli_prepare(
                $conn,
                "
                    SELECT
                        product_id,
                        stock
                    FROM products
                    WHERE product_id = ?
                    LIMIT 1
                    FOR UPDATE
                "
            );

        if (!$productStockStmt) {

            mysqli_stmt_close(
                $purchaseItemInsertStmt
            );

            throw new Exception(
                'Unable to prepare product stock verification.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | PREPARE PRODUCT STOCK UPDATE
        |--------------------------------------------------------------------------
        */

        $productStockUpdateStmt =
            mysqli_prepare(
                $conn,
                "
                    UPDATE products
                    SET stock = stock + ?
                    WHERE product_id = ?
                "
            );

        if (!$productStockUpdateStmt) {

            mysqli_stmt_close(
                $purchaseItemInsertStmt
            );

            mysqli_stmt_close(
                $productStockStmt
            );

            throw new Exception(
                'Unable to prepare product stock update.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | INSERT PURCHASE ITEMS + UPDATE STOCK
        |--------------------------------------------------------------------------
        */

        foreach (
            $validatedItems
            as $validatedItem
        ) {

            $productId =
                $validatedItem['product_id'];

            $quantity =
                $validatedItem['quantity'];

            $purchasePrice =
                $validatedItem['purchase_price'];

            $subtotal =
                $validatedItem['subtotal'];


            /*
            |--------------------------------------------------------------------------
            | LOCK AND VERIFY PRODUCT
            |--------------------------------------------------------------------------
            */

            mysqli_stmt_bind_param(
                $productStockStmt,
                "i",
                $productId
            );

            if (
                !mysqli_stmt_execute(
                    $productStockStmt
                )
            ) {

                throw new Exception(
                    'Unable to verify one of the selected products.'
                );

            }

            $productStockResult =
                mysqli_stmt_get_result(
                    $productStockStmt
                );

            if (
                !$productStockResult ||
                mysqli_num_rows(
                    $productStockResult
                ) === 0
            ) {

                throw new Exception(
                    'One of the selected products no longer exists.'
                );

            }

            $productStockRow =
                mysqli_fetch_assoc(
                    $productStockResult
                );

            $currentStock =
                (int) $productStockRow['stock'];


            /*
            |--------------------------------------------------------------------------
            | CHECK STOCK INTEGER RANGE
            |--------------------------------------------------------------------------
            |
            | products.stock is INT.
            |--------------------------------------------------------------------------
            */

            $newStock =
                $currentStock +
                $quantity;

            if (
                $newStock > 2147483647
            ) {

                throw new Exception(
                    'Stock value is too large for one of the selected products.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | INSERT PURCHASE ITEM
            |--------------------------------------------------------------------------
            */

            mysqli_stmt_bind_param(
                $purchaseItemInsertStmt,
                "iiidd",
                $purchaseId,
                $productId,
                $quantity,
                $purchasePrice,
                $subtotal
            );


            if (
                !mysqli_stmt_execute(
                    $purchaseItemInsertStmt
                )
            ) {

                throw new Exception(
                    'Unable to save one of the purchase items.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE PRODUCT STOCK
            |--------------------------------------------------------------------------
            */

            mysqli_stmt_bind_param(
                $productStockUpdateStmt,
                "ii",
                $quantity,
                $productId
            );


            if (
                !mysqli_stmt_execute(
                    $productStockUpdateStmt
                )
            ) {

                throw new Exception(
                    'Unable to update stock for one of the purchased products.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | VERIFY STOCK UPDATE
            |--------------------------------------------------------------------------
            */

            if (
                mysqli_stmt_affected_rows(
                    $productStockUpdateStmt
                ) !== 1
            ) {

                throw new Exception(
                    'Stock update failed for one of the purchased products.'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | CLOSE PREPARED STATEMENTS
        |--------------------------------------------------------------------------
        */

        mysqli_stmt_close(
            $purchaseItemInsertStmt
        );

        mysqli_stmt_close(
            $productStockStmt
        );

        mysqli_stmt_close(
            $productStockUpdateStmt
        );


        /*
        |--------------------------------------------------------------------------
        | COMMIT TRANSACTION
        |--------------------------------------------------------------------------
        */

        if (
            !mysqli_commit(
                $conn
            )
        ) {

            throw new Exception(
                'Unable to complete the purchase transaction.'
            );

        }

        $transactionStarted = false;


        /*
        |--------------------------------------------------------------------------
        | SUCCESS RESPONSE
        |--------------------------------------------------------------------------
        */

        echo json_encode([
            'success' => true,
            'message' =>
                'Purchase ' .
                $invoiceNo .
                ' was saved successfully and product stock was updated.',
            'purchase_id' =>
                (int) $purchaseId,
            'total_amount' =>
                number_format(
                    $calculatedTotal,
                    2,
                    '.',
                    ''
                ),
            'payment_status' =>
                $paymentStatus
        ]);

        exit;

    } catch (Throwable $exception) {

        /*
        |--------------------------------------------------------------------------
        | ROLLBACK
        |--------------------------------------------------------------------------
        |
        | If anything failed after BEGIN:
        |
        | - purchases INSERT is rolled back.
        | - purchase_items INSERTs are rolled back.
        | - stock updates are rolled back.
        |
        | Therefore the database cannot be left partially saved.
        |--------------------------------------------------------------------------
        */

        if ($transactionStarted) {

            mysqli_rollback(
                $conn
            );

        }


        /*
        |--------------------------------------------------------------------------
        | SERVER-SIDE ERROR RESPONSE
        |--------------------------------------------------------------------------
        */

        echo json_encode([
            'success' => false,
            'message' =>
                $exception->getMessage()
        ]);

        exit;

    }

}


/*
|--------------------------------------------------------------------------
| LOAD SUPPLIERS
|--------------------------------------------------------------------------
|
| Existing suppliers columns used:
| - supplier_id
| - supplier_name
| - phone
| - email
| - address
| - company
|
*/

$suppliers = [];

$supplierResult = mysqli_query(
    $conn,
    "
        SELECT
            supplier_id,
            supplier_name,
            phone,
            email,
            address,
            company
        FROM suppliers
        ORDER BY supplier_name ASC
    "
);

if ($supplierResult) {

    while ($row = mysqli_fetch_assoc($supplierResult)) {
        $suppliers[] = $row;
    }

}


/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
|
| Existing products columns used:
| - product_id
| - product_name
| - barcode
| - unit
| - purchase_price
| - selling_price
| - stock
| - minimum_stock
| - status
|
| No product is modified during normal page loading.
|
*/

$products = [];

$productResult = mysqli_query(
    $conn,
    "
        SELECT
            product_id,
            product_name,
            barcode,
            unit,
            purchase_price,
            selling_price,
            stock,
            minimum_stock,
            status
        FROM products
        ORDER BY product_name ASC
    "
);

if ($productResult) {

    while ($row = mysqli_fetch_assoc($productResult)) {
        $products[] = $row;
    }

}


/*
|--------------------------------------------------------------------------
| PREPARE PRODUCT DATA FOR THE CURRENT PAGE
|--------------------------------------------------------------------------
*/

$productData = [];

foreach ($products as $product) {

    $productData[] = [
        'product_id'     => (int) $product['product_id'],
        'product_name'   => (string) $product['product_name'],
        'barcode'        => (string) ($product['barcode'] ?? ''),
        'unit'           => (string) $product['unit'],
        'purchase_price' => (string) $product['purchase_price'],
        'selling_price'  => (string) $product['selling_price'],
        'stock'          => (int) $product['stock'],
        'minimum_stock'  => (int) $product['minimum_stock'],
        'status'         => (string) $product['status']
    ];

}


/*
|--------------------------------------------------------------------------
| STAGE 7 - LOAD RECENT PURCHASES
|--------------------------------------------------------------------------
|
| Stage 7 only reads saved purchases from the existing database.
|
| Displayed columns:
| - Invoice
| - Date
| - Supplier
| - Items
| - Amount
| - Status
|
| No purchase, purchase item, or stock data is modified here.
|--------------------------------------------------------------------------
*/

$recentPurchases = [];

$recentPurchasesResult = mysqli_query(
    $conn,
    "
        SELECT
            p.purchase_id,
            p.invoice_no,
            p.purchase_date,
            p.total_amount,
            p.payment_status,
            s.supplier_name,
            COUNT(pi.item_id) AS item_count
        FROM purchases p
        INNER JOIN suppliers s
            ON p.supplier_id = s.supplier_id
        LEFT JOIN purchase_items pi
            ON p.purchase_id = pi.purchase_id
        GROUP BY
            p.purchase_id,
            p.invoice_no,
            p.purchase_date,
            p.total_amount,
            p.payment_status,
            s.supplier_name
        ORDER BY
            p.purchase_date DESC,
            p.purchase_id DESC
        LIMIT 5
    "
);

if ($recentPurchasesResult) {

    while ($row = mysqli_fetch_assoc($recentPurchasesResult)) {
        $recentPurchases[] = $row;
    }

}

$latestPurchaseId =
    count($recentPurchases) > 0
        ? (int) $recentPurchases[0]['purchase_id']
        : 0;


/*
|--------------------------------------------------------------------------
| SHARED HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>

<!-- =========================================================
     SHARED APPLICATION CSS
     ========================================================= -->

<link
    rel="stylesheet"
    href="../../assets/css/layout.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/sidebar.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/topbar.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/dashboard-layout.css"
>


<style>

    /*
    |--------------------------------------------------------------------------
    | PURCHASE PAGE
    |--------------------------------------------------------------------------
    */

    .purchases-page {
        padding: 28px;
        max-width: 1400px;
        margin: 0 auto;
    }


    /*
    |--------------------------------------------------------------------------
    | PAGE HEADER
    |--------------------------------------------------------------------------
    */

    .purchases-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 28px;
    }

    .purchases-page-heading h1 {
        margin: 0 0 8px;
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
    }

    .purchases-page-heading p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .purchases-page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        background: #eff6ff;
        color: #2563eb;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
    }
    .purchases-new-purchase-button {
    border: none;
    cursor: pointer;
    font-family: inherit;
}

.purchases-new-purchase-button:hover {
    background: #dbeafe;
}


    /*
    |--------------------------------------------------------------------------
    | MAIN GRID
    |--------------------------------------------------------------------------
    */

    .purchases-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        gap: 24px;
        align-items: start;
    }


    /*
    |--------------------------------------------------------------------------
    | CARD
    |--------------------------------------------------------------------------
    */

    .purchases-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
    }

    .purchases-card-header {
        padding: 22px 24px;
        border-bottom: 1px solid #e5e7eb;
    }

    .purchases-card-header h2 {
        margin: 0 0 5px;
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }

    .purchases-card-header p {
        margin: 0;
        font-size: 13px;
        color: #64748b;
    }

    .purchases-card-body {
        padding: 24px;
    }


    /*
    |--------------------------------------------------------------------------
    | ORDER DETAILS
    |--------------------------------------------------------------------------
    */

    .purchase-details-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .purchase-field {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .purchase-field.full-width {
        grid-column: 1 / -1;
    }

    .purchase-field label {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
    }

    .purchase-field input,
    .purchase-field select,
    .purchase-field textarea {
        width: 100%;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 9px;
        background: #ffffff;
        color: #1e293b;
        font-size: 14px;
        outline: none;
        box-sizing: border-box;
    }

    .purchase-field input:focus,
    .purchase-field select:focus,
    .purchase-field textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
    }


    .purchase-field textarea {
        min-height: 88px;
        resize: vertical;
        font-family: inherit;
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCTS SECTION
    |--------------------------------------------------------------------------
    */

    .purchase-products-section {
        margin-top: 28px;
    }

    .purchase-section-title {
        margin-bottom: 14px;
    }

    .purchase-section-title h3 {
        margin: 0 0 4px;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }

    .purchase-section-title p {
        margin: 0;
        font-size: 13px;
        color: #64748b;
    }


    /*
    |--------------------------------------------------------------------------
    | ADD PRODUCT BAR
    |--------------------------------------------------------------------------
    */

    .purchase-product-entry {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 110px 130px 90px;
        gap: 10px;
        align-items: start;
        margin-bottom: 18px;
    }

    .purchase-product-entry-field {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .purchase-product-entry-field label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
    }

    .purchase-product-entry-field input,
    .purchase-product-entry-field select {
        width: 100%;
        min-height: 42px;
        padding: 9px 11px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 13px;
        color: #1e293b;
        background: #ffffff;
    }


    /*
    |--------------------------------------------------------------------------
    | ADD BUTTON ALIGNMENT
    |--------------------------------------------------------------------------
    */

    .purchase-product-entry-field:last-child {
        transform: translateY(2px);
    }

    .purchase-product-entry-field input:focus,
    .purchase-product-entry-field select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
    }

    .purchase-product-help {
        margin: 0;
        font-size: 11px;
        line-height: 1.4;
        color: #64748b;
    }


    /*
    |--------------------------------------------------------------------------
    | TEMPORARY MESSAGE
    |--------------------------------------------------------------------------
    */

    .purchase-item-message {
        display: none;
        margin-top: -4px;
        margin-bottom: 18px;
        padding: 10px 13px;
        border-radius: 8px;
        font-size: 12px;
        line-height: 1.5;
    }

    .purchase-item-message.visible {
        display: block;
    }

    .purchase-item-message.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
    }

    .purchase-item-message.success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #15803d;
    }


    /*
    |--------------------------------------------------------------------------
    | SELECTED PRODUCT INFORMATION
    |--------------------------------------------------------------------------
    */

    .purchase-selected-product-info {
        display: none;
        margin-top: -4px;
        margin-bottom: 18px;
        padding: 12px 14px;
        border: 1px solid #dbeafe;
        border-radius: 9px;
        background: #eff6ff;
        color: #1e3a8a;
        font-size: 12px;
        line-height: 1.6;
    }

    .purchase-selected-product-info.visible {
        display: block;
    }

    .purchase-selected-product-info strong {
        color: #1e40af;
    }

    .purchase-selected-product-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 18px;
    }


    /*
    |--------------------------------------------------------------------------
    | BUTTONS
    |--------------------------------------------------------------------------
    */

    .purchase-button {
        min-height: 42px;
        padding: 9px 14px;
        border-radius: 8px;
        border: 1px solid transparent;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
    }

    .purchase-button-primary {
        background: #2563eb;
        color: #ffffff;
    }

    .purchase-button-primary:hover {
        background: #1d4ed8;
    }

    .purchase-button-primary:disabled {
        background: #93c5fd;
        cursor: not-allowed;
    }

    .purchase-button-secondary {
        background: #ffffff;
        color: #334155;
        border-color: #d1d5db;
    }

    .purchase-button-secondary:hover {
        background: #f8fafc;
    }

    .purchase-button-danger {
        background: #fff1f2;
        color: #be123c;
        border-color: #fecdd3;
    }

    .purchase-button-danger:hover {
        background: #ffe4e6;
    }


    /*
    |--------------------------------------------------------------------------
    | PURCHASE ITEMS TABLE
    |--------------------------------------------------------------------------
    */

    .purchase-table-wrapper {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
    }

    .purchase-items-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 720px;
    }

    .purchase-items-table th {
        padding: 12px 14px;
        text-align: left;
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
        border-bottom: 1px solid #e5e7eb;
    }

    .purchase-items-table td {
        padding: 14px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 13px;
    }

    .purchase-items-table tbody tr:last-child td {
        border-bottom: none;
    }

    .purchase-empty-row {
        text-align: center !important;
        padding: 36px 20px !important;
        color: #94a3b8 !important;
    }

    .purchase-item-product-name {
        font-weight: 600;
        color: #0f172a;
    }

    .purchase-item-barcode {
        display: block;
        margin-top: 3px;
        font-size: 11px;
        color: #94a3b8;
    }

    .purchase-item-line-total {
        font-weight: 700;
        color: #0f172a;
    }

    .purchase-item-remove-button {
        min-height: 34px;
        padding: 7px 11px;
        border-radius: 7px;
        border: 1px solid #fecdd3;
        background: #fff1f2;
        color: #be123c;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .purchase-item-remove-button:hover {
        background: #ffe4e6;
    }


    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    .purchase-summary-card {
        position: sticky;
        top: 90px;
    }

    .purchase-summary-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .purchase-summary-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        font-size: 14px;
        color: #475569;
    }

    .purchase-summary-row strong {
        color: #0f172a;
        font-weight: 700;
    }

    .purchase-summary-total {
        margin-top: 4px;
        padding-top: 17px;
        border-top: 1px solid #e5e7eb;
        font-size: 17px;
    }

    .purchase-summary-total strong {
        font-size: 20px;
        color: #2563eb;
    }

    .purchase-summary-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 24px;
    }

    .purchase-summary-actions .purchase-button {
        width: 100%;
    }


    /*
    |--------------------------------------------------------------------------
    | RECENT PURCHASES
    |--------------------------------------------------------------------------
    */

    .recent-purchases-card {
        margin-top: 24px;
    }

    .recent-purchases-table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    .recent-purchases-table {
        width: 100%;
        min-width: 820px;
        border-collapse: collapse;
    }

    .recent-purchases-table th {
        padding: 13px 20px;
        text-align: left;
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        border-bottom: 1px solid #e5e7eb;
    }

    .recent-purchases-table td {
        padding: 15px 20px;
        color: #334155;
        font-size: 13px;
        border-bottom: 1px solid #f1f5f9;
    }

    .recent-purchases-table tbody tr:last-child td {
        border-bottom: none;
    }

    .purchase-invoice-number {
        font-weight: 700;
        color: #2563eb;
    }

    .purchase-status {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }

    .purchase-status-received {
        background: #dcfce7;
        color: #15803d;
    }

    .purchase-status-pending {
        background: #fef3c7;
        color: #b45309;
    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSIVE
    |--------------------------------------------------------------------------
    */

    @media (max-width: 1100px) {

        .purchases-main-grid {
            grid-template-columns: 1fr;
        }

        .purchase-summary-card {
            position: static;
        }

    }

    @media (max-width: 800px) {

        .purchases-page {
            padding: 20px;
        }

        .purchases-page-header {
            flex-direction: column;
        }

        .purchase-details-grid {
            grid-template-columns: 1fr;
        }

        .purchase-product-entry {
            grid-template-columns: 1fr 1fr;
        }

        .purchase-product-entry-field:first-child {
            grid-column: 1 / -1;
        }

    }

    @media (max-width: 520px) {

        .purchases-page {
            padding: 16px;
        }

        .purchases-card-body {
            padding: 18px;
        }

        .purchase-product-entry {
            grid-template-columns: 1fr;
        }

        .purchase-product-entry-field:first-child {
            grid-column: auto;
        }

    }

</style>


<!-- =========================================================
     APPLICATION LAYOUT
     ========================================================= -->

<div class="app-layout">


    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <aside class="app-sidebar-slot">

        <?php require_once "../../includes/sidebar.php"; ?>

    </aside>


    <!-- =====================================================
         MAIN APPLICATION AREA
         ===================================================== -->

    <div class="app-main-slot">


        <!-- =================================================
             TOPBAR
             ================================================= -->

        <header class="app-topbar-slot">

            <?php require_once "../../includes/topbar.php"; ?>

        </header>


        <!-- =================================================
             PAGE CONTENT
             ================================================= -->

        <main class="dashboard-main-content">


            <div class="purchases-page">


                <!-- =========================================
                     PAGE HEADER
                     ========================================= -->

                <div class="purchases-page-header">

                    <div class="purchases-page-heading">

                        <h1>
                            Purchases Management
                        </h1>

                        <p>
                            Create and manage supplier purchase orders.
                        </p>

                    </div>


                    <button
    type="button"
    class="purchases-page-header-badge purchases-new-purchase-button"
    id="newPurchaseButton"
>
    New Purchase
</button>

                </div>


                <!-- =========================================
                     MAIN PURCHASE GRID
                     ========================================= -->

                <div class="purchases-main-grid">


                    <!-- =====================================
                         PURCHASE ORDER CARD
                         ===================================== -->

                    <section class="purchases-card">


                        <div class="purchases-card-header">

                            <h2>
                                Purchase Order
                            </h2>

                            <p>
                                Enter supplier and purchase information.
                            </p>

                        </div>


                        <div class="purchases-card-body">


                            <!-- =================================
                                 ORDER DETAILS
                                 ================================= -->

                            <div class="purchase-details-grid">


                                <!-- =============================
                                     SUPPLIER
                                     ============================= -->

                                <div class="purchase-field">

                                    <label for="supplier_id">
                                        Supplier
                                    </label>

                                    <select
                                        id="supplier_id"
                                        name="supplier_id"
                                    >

                                        <option value="">
                                            Select supplier
                                        </option>


                                        <?php foreach ($suppliers as $supplier): ?>

                                            <?php

                                            $supplierLabel =
                                                $supplier['supplier_name'];

                                            if (!empty($supplier['company'])) {
                                                $supplierLabel .=
                                                    ' — ' .
                                                    $supplier['company'];
                                            }

                                            ?>

                                            <option
                                                value="<?php echo (int) $supplier['supplier_id']; ?>"
                                            >
                                                <?php echo htmlspecialchars(
                                                    $supplierLabel,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </option>

                                        <?php endforeach; ?>


                                    </select>

                                </div>


                                <!-- =============================
                                     PURCHASE DATE
                                     ============================= -->

                                <div class="purchase-field">

                                    <label for="purchase_date">
                                        Purchase Date
                                    </label>

                                    <input
                                        type="date"
                                        id="purchase_date"
                                        name="purchase_date"
                                        value="<?php echo date('Y-m-d'); ?>"
                                    >

                                </div>


                                <!-- =============================
                                     INVOICE NUMBER
                                     ============================= -->

                                <div class="purchase-field">

                                    <label for="invoice_no">
                                        PO / Invoice Number
                                    </label>

                                    <input
                                        type="text"
                                        id="invoice_no"
                                        name="invoice_no"
                                        maxlength="50"
                                        placeholder="Enter invoice number"
                                    >

                                </div>


                                <!-- =============================
                                     PAYMENT STATUS
                                     ============================= -->

                                <div class="purchase-field">

                                    <label for="payment_status">
                                        Payment Status
                                    </label>

                                    <select
                                        id="payment_status"
                                        name="payment_status"
                                    >
                                        <option value="Paid" selected>
                                            Paid
                                        </option>

                                        <option value="Due">
                                            Due
                                        </option>
                                    </select>

                                </div>


                                <!-- =============================
                                     REMARKS
                                     ============================= -->

                                <div class="purchase-field full-width">

                                    <label for="purchase_remarks">
                                        Remarks
                                        <span style="font-weight:400;color:#64748b;">
                                            (Optional)
                                        </span>
                                    </label>

                                    <textarea
                                        id="purchase_remarks"
                                        name="remarks"
                                        maxlength="1000"
                                        placeholder="Add a short note about this purchase..."
                                    ></textarea>

                                </div>


                            </div>


                            <!-- =================================
                                 PRODUCTS PURCHASED
                                 ================================= -->

                            <div class="purchase-products-section">


                                <div class="purchase-section-title">

                                    <h3>
                                        Products Purchased
                                    </h3>

                                    <p>
                                        Add products that are included in this purchase.
                                    </p>

                                </div>


                                <!-- =============================
                                     PRODUCT ENTRY
                                     ============================= -->

                                <div class="purchase-product-entry">


                                    <!-- =========================
                                         PRODUCT SEARCH
                                         ========================= -->

                                    <div class="purchase-product-entry-field">

                                        <label for="product_search">
                                            Product
                                        </label>

                                        <input
                                            type="text"
                                            id="product_search"
                                            name="product_search"
                                            list="purchaseProductList"
                                            autocomplete="off"
                                            placeholder="Search products..."
                                        >

                                        <input
                                            type="hidden"
                                            id="selected_product_id"
                                            name="selected_product_id"
                                            value=""
                                        >

                                        <datalist id="purchaseProductList">

                                            <?php foreach ($products as $product): ?>

                                                <?php

                                                $productName =
                                                    (string) $product['product_name'];

                                                $barcode =
                                                    (string) ($product['barcode'] ?? '');

                                                $productLabel =
                                                    $productName;

                                                if ($barcode !== '') {
                                                    $productLabel .=
                                                        ' — Barcode: ' .
                                                        $barcode;
                                                }

                                                ?>

                                                <option
                                                    value="<?php echo htmlspecialchars(
                                                        $productName,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>"
                                                    label="<?php echo htmlspecialchars(
                                                        $productLabel,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>"
                                                ></option>

                                            <?php endforeach; ?>

                                        </datalist>

                                        <p class="purchase-product-help">
                                            Search by product name or barcode.
                                        </p>

                                    </div>


                                    <!-- =========================
                                         QUANTITY
                                         ========================= -->

                                    <div class="purchase-product-entry-field">

                                        <label for="product_quantity">
                                            Qty
                                        </label>

                                        <input
                                            type="number"
                                            id="product_quantity"
                                            name="product_quantity"
                                            min="1"
                                            step="1"
                                            placeholder="0"
                                        >

                                    </div>


                                    <!-- =========================
                                         UNIT COST
                                         ========================= -->

                                    <div class="purchase-product-entry-field">

                                        <label for="product_cost">
                                            Unit Cost
                                        </label>

                                        <input
                                            type="number"
                                            id="product_cost"
                                            name="product_cost"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                        >

                                    </div>


                                    <!-- =========================
                                         ADD BUTTON
                                         ========================= -->

                                    <div class="purchase-product-entry-field">

                                        <label>&nbsp;</label>

                                        <button
                                            type="button"
                                            class="purchase-button purchase-button-primary"
                                            id="addPurchaseProductButton"
                                        >
                                            + Add
                                        </button>

                                    </div>


                                </div>


                                <!-- =============================
                                     TEMPORARY MESSAGE
                                     ============================= -->

                                <div
                                    id="purchaseItemMessage"
                                    class="purchase-item-message"
                                    role="alert"
                                    aria-live="polite"
                                ></div>


                                <!-- =============================
                                     SELECTED PRODUCT INFORMATION
                                     ============================= -->

                                <div
                                    id="selectedProductInfo"
                                    class="purchase-selected-product-info"
                                    aria-live="polite"
                                >

                                    <div>
                                        <strong id="selectedProductName">
                                            Product
                                        </strong>
                                    </div>

                                    <div class="purchase-selected-product-meta">

                                        <span>
                                            Product ID:
                                            <strong id="selectedProductIdDisplay">
                                                —
                                            </strong>
                                        </span>

                                        <span>
                                            Unit:
                                            <strong id="selectedProductUnit">
                                                —
                                            </strong>
                                        </span>

                                        <span>
                                            Current Stock:
                                            <strong id="selectedProductStock">
                                                —
                                            </strong>
                                        </span>

                                        <span>
                                            Minimum Stock:
                                            <strong id="selectedProductMinimumStock">
                                                —
                                            </strong>
                                        </span>

                                        <span>
                                            Status:
                                            <strong id="selectedProductStatus">
                                                —
                                            </strong>
                                        </span>

                                    </div>

                                </div>


                                <!-- =============================
                                     PURCHASE ITEMS TABLE
                                     ============================= -->

                                <div class="purchase-table-wrapper">

                                    <table class="purchase-items-table">

                                        <thead>

                                            <tr>

                                                <th>
                                                    Product
                                                </th>

                                                <th>
                                                    Qty
                                                </th>

                                                <th>
                                                    Unit
                                                </th>

                                                <th>
                                                    Unit Cost
                                                </th>

                                                <th>
                                                    Total
                                                </th>

                                                <th>
                                                    Action
                                                </th>

                                            </tr>

                                        </thead>


                                        <tbody id="purchaseItemsTableBody">

                                            <tr>

                                                <td
                                                    colspan="6"
                                                    class="purchase-empty-row"
                                                >
                                                    No products added yet.
                                                </td>

                                            </tr>

                                        </tbody>

                                    </table>

                                </div>


                            </div>


                        </div>


                    </section>


                    <!-- =====================================
                         INVOICE SUMMARY
                         ===================================== -->

                    <aside class="purchases-card purchase-summary-card">


                        <div class="purchases-card-header">

                            <h2>
                                Invoice Summary
                            </h2>

                            <p>
                                Purchase cost summary.
                            </p>

                        </div>


                        <div class="purchases-card-body">


                            <div class="purchase-summary-list">


                                <div class="purchase-summary-row">

                                    <span>
                                        Items
                                    </span>

                                    <strong id="purchaseItemCount">
                                        0
                                    </strong>

                                </div>


                                <div class="purchase-summary-row">

                                    <span>
                                        Subtotal
                                    </span>

                                    <strong id="purchaseSubtotal">
                                        ৳0.00
                                    </strong>

                                </div>


                                <div class="purchase-summary-row purchase-summary-total">

                                    <span>
                                        Total
                                    </span>

                                    <strong id="purchaseTotal">
                                        ৳0.00
                                    </strong>

                                </div>


                            </div>


                            <div class="purchase-summary-actions">


                                <button
                                    type="button"
                                    class="purchase-button purchase-button-primary"
                                    id="savePurchaseButton"
                                >
                                    Save Purchase
                                </button>


                                <button
                                    type="button"
                                    class="purchase-button purchase-button-secondary"
                                    id="printInvoiceButton"
                                >
                                    Print Invoice
                                </button>


                            </div>


                        </div>


                    </aside>


                </div>


                <!-- =========================================
                     RECENT PURCHASES
                     ========================================= -->

                <section class="purchases-card recent-purchases-card">


                    <div class="purchases-card-header">

                        <h2>
                            Recent Purchases
                        </h2>

                        <p>
                            Recently created supplier purchases.
                        </p>

                    </div>


                    <div class="recent-purchases-table-wrapper">

                        <table class="recent-purchases-table">


                            <thead>

                                <tr>

                                    <th>
                                        Invoice
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Supplier
                                    </th>

                                    <th>
                                        Items
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php if (count($recentPurchases) > 0): ?>

                                    <?php foreach ($recentPurchases as $recentPurchase): ?>

                                        <?php

                                        $recentPurchaseStatus =
                                            (string) ($recentPurchase['payment_status'] ?? 'Paid');

                                        $recentPurchaseStatusClass =
                                            $recentPurchaseStatus === 'Due'
                                                ? 'purchase-status-pending'
                                                : 'purchase-status-received';

                                        $recentPurchaseStatusLabel =
                                            $recentPurchaseStatus === 'Due'
                                                ? 'Due'
                                                : 'Paid';

                                        ?>

                                        <tr>

                                            <td>

                                                <span class="purchase-invoice-number">
                                                    <?php echo htmlspecialchars(
                                                        (string) $recentPurchase['invoice_no'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                </span>

                                            </td>

                                            <td>
                                                <?php echo date(
                                                    'd M Y',
                                                    strtotime((string) $recentPurchase['purchase_date'])
                                                ); ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars(
                                                    (string) $recentPurchase['supplier_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </td>

                                            <td>
                                                <?php echo (int) $recentPurchase['item_count']; ?>
                                            </td>

                                            <td>
                                                ৳<?php echo number_format(
                                                    (float) $recentPurchase['total_amount'],
                                                    2
                                                ); ?>
                                            </td>

                                            <td>

                                                <span
                                                    class="purchase-status <?php echo $recentPurchaseStatusClass; ?>"
                                                >
                                                    <?php echo $recentPurchaseStatusLabel; ?>
                                                </span>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>

                                        <td
                                            colspan="6"
                                            class="purchase-empty-row"
                                        >
                                            No purchases yet.
                                        </td>

                                    </tr>

                                <?php endif; ?>

                            </tbody>


                        </table>

                    </div>


                </section>


            </div>


        </main>


    </div>


</div>


<script>

    /*
    |--------------------------------------------------------------------------
    | STAGE 2 - PRODUCT DATABASE DATA
    |--------------------------------------------------------------------------
    */

    const purchaseProducts =
        <?php echo json_encode(
            $productData,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ); ?>;


    /*
    |--------------------------------------------------------------------------
    | STAGE 3 - TEMPORARY PURCHASE ITEMS
    |--------------------------------------------------------------------------
    |
    | Purchase items stay in browser memory until Save Purchase is clicked.
    |--------------------------------------------------------------------------
    */

    let purchaseItems = [];


    /*
    |--------------------------------------------------------------------------
    | STAGE 5 - SAVE STATE
    |--------------------------------------------------------------------------
    |
    | Prevents multiple Save Purchase requests from being started by
    | repeated clicks while the first request is still being processed.
    |--------------------------------------------------------------------------
    */

    let savePurchaseInProgress = false;


    /*
    |--------------------------------------------------------------------------
    | STAGE 8 - LAST SAVED PURCHASE
    |--------------------------------------------------------------------------
    |
    | Start with the latest purchase shown by Stage 7. After a successful
    | save, this is replaced with the newly created purchase ID.
    |--------------------------------------------------------------------------
    */

    let lastSavedPurchaseId =
        <?php echo (int) $latestPurchaseId; ?>;


    const purchaseCsrfToken =
        <?php echo json_encode(
            $purchaseCsrfToken,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        ); ?>;


    /*
    |--------------------------------------------------------------------------
    | PRODUCT SEARCH ELEMENTS
    |--------------------------------------------------------------------------
    */

    const productSearch =
        document.getElementById('product_search');

    const selectedProductId =
        document.getElementById('selected_product_id');

    const productQuantity =
        document.getElementById('product_quantity');

    const productCost =
        document.getElementById('product_cost');

    const selectedProductInfo =
        document.getElementById('selectedProductInfo');

    const selectedProductName =
        document.getElementById('selectedProductName');

    const selectedProductIdDisplay =
        document.getElementById('selectedProductIdDisplay');

    const selectedProductUnit =
        document.getElementById('selectedProductUnit');

    const selectedProductStock =
        document.getElementById('selectedProductStock');

    const selectedProductMinimumStock =
        document.getElementById('selectedProductMinimumStock');

    const selectedProductStatus =
        document.getElementById('selectedProductStatus');


    /*
    |--------------------------------------------------------------------------
    | STAGE 3/4 PURCHASE ITEM ELEMENTS
    |--------------------------------------------------------------------------
    */

    const purchaseItemsTableBody =
        document.getElementById('purchaseItemsTableBody');

    const purchaseItemCount =
        document.getElementById('purchaseItemCount');

    const purchaseSubtotal =
        document.getElementById('purchaseSubtotal');

    const purchaseTotal =
        document.getElementById('purchaseTotal');

    const purchaseItemMessage =
        document.getElementById('purchaseItemMessage');

    const addPurchaseProductButton =
        document.getElementById('addPurchaseProductButton');
    const newPurchaseButton =
    document.getElementById('newPurchaseButton');


    /*
    |--------------------------------------------------------------------------
    | STAGE 5 PURCHASE ELEMENTS
    |--------------------------------------------------------------------------
    */

    const supplierId =
        document.getElementById('supplier_id');

    const purchaseDate =
        document.getElementById('purchase_date');

    const invoiceNo =
        document.getElementById('invoice_no');

    const paymentStatus =
        document.getElementById('payment_status');

    const purchaseRemarks =
        document.getElementById('purchase_remarks');

    const savePurchaseButton =
        document.getElementById('savePurchaseButton');

    const printInvoiceButton =
        document.getElementById('printInvoiceButton');


    /*
    |--------------------------------------------------------------------------
    | FIND PRODUCT
    |--------------------------------------------------------------------------
    */

    function findPurchaseProduct(searchValue) {

        const value =
            String(searchValue || '').trim().toLowerCase();

        if (value === '') {
            return null;
        }

        return purchaseProducts.find(function (product) {

            const productName =
                String(product.product_name || '')
                    .trim()
                    .toLowerCase();

            const barcode =
                String(product.barcode || '')
                    .trim()
                    .toLowerCase();

            return (
                productName === value ||
                barcode === value
            );

        }) || null;

    }


    /*
    |--------------------------------------------------------------------------
    | SHOW PURCHASE ITEM MESSAGE
    |--------------------------------------------------------------------------
    */

    function showPurchaseItemMessage(message, type) {

        purchaseItemMessage.textContent =
            message;

        purchaseItemMessage.classList.remove(
            'visible',
            'error',
            'success'
        );

        if (type === 'success') {

            purchaseItemMessage.classList.add(
                'visible',
                'success'
            );

        } else {

            purchaseItemMessage.classList.add(
                'visible',
                'error'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CLEAR PURCHASE ITEM MESSAGE
    |--------------------------------------------------------------------------
    */

    function clearPurchaseItemMessage() {

        purchaseItemMessage.textContent =
            '';

        purchaseItemMessage.classList.remove(
            'visible',
            'error',
            'success'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CLEAR SELECTED PRODUCT
    |--------------------------------------------------------------------------
    */

    function clearSelectedProduct() {

        selectedProductId.value =
            '';

        productCost.value =
            '';

        selectedProductInfo.classList.remove(
            'visible'
        );

        selectedProductName.textContent =
            'Product';

        selectedProductIdDisplay.textContent =
            '—';

        selectedProductUnit.textContent =
            '—';

        selectedProductStock.textContent =
            '—';

        selectedProductMinimumStock.textContent =
            '—';

        selectedProductStatus.textContent =
            '—';

    }


    /*
    |--------------------------------------------------------------------------
    | SHOW SELECTED PRODUCT
    |--------------------------------------------------------------------------
    */

    function showSelectedProduct(product) {

        if (!product) {

            clearSelectedProduct();

            return;

        }

        selectedProductId.value =
            String(product.product_id);

        productCost.value =
            String(product.purchase_price);

        selectedProductName.textContent =
            product.product_name;

        selectedProductIdDisplay.textContent =
            String(product.product_id);

        selectedProductUnit.textContent =
            product.unit;

        selectedProductStock.textContent =
            String(product.stock);

        selectedProductMinimumStock.textContent =
            String(product.minimum_stock);

        selectedProductStatus.textContent =
            product.status;

        selectedProductInfo.classList.add(
            'visible'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | FORMAT CURRENCY
    |--------------------------------------------------------------------------
    */

    function formatPurchaseCurrency(amount) {

        const numericAmount =
            Number(amount);

        if (!Number.isFinite(numericAmount)) {
            return '৳0.00';
        }

        return '৳' +
            numericAmount.toFixed(2);

    }


    /*
    |--------------------------------------------------------------------------
    | FORMAT QUANTITY
    |--------------------------------------------------------------------------
    */

    function formatQuantity(quantity) {

        const numericQuantity =
            Number(quantity);

        if (!Number.isFinite(numericQuantity)) {
            return '0';
        }

        if (
            Number.isInteger(numericQuantity)
        ) {

            return String(
                numericQuantity
            );

        }

        return String(
            numericQuantity
        );

    }


    /*
    |--------------------------------------------------------------------------
    | STAGE 4 - UPDATE PURCHASE SUMMARY
    |--------------------------------------------------------------------------
    |
    | Item Count = number of temporary purchase rows.
    |
    | Subtotal = sum of line totals.
    |
    | Total = Subtotal.
    |--------------------------------------------------------------------------
    */

    function updatePurchaseSummary() {

        let subtotal = 0;


        purchaseItems.forEach(function (item) {

            subtotal +=
                Number(item.line_total);

        });


        subtotal =
            Number(
                subtotal.toFixed(2)
            );


        purchaseItemCount.textContent =
            String(
                purchaseItems.length
            );

        purchaseSubtotal.textContent =
            formatPurchaseCurrency(
                subtotal
            );

        purchaseTotal.textContent =
            formatPurchaseCurrency(
                subtotal
            );

    }


    /*
    |--------------------------------------------------------------------------
    | RENDER PURCHASE ITEMS TABLE
    |--------------------------------------------------------------------------
    */

    function renderPurchaseItems() {

        purchaseItemsTableBody.innerHTML =
            '';


        if (
            purchaseItems.length === 0
        ) {

            const emptyRow =
                document.createElement('tr');

            const emptyCell =
                document.createElement('td');

            emptyCell.colSpan =
                6;

            emptyCell.className =
                'purchase-empty-row';

            emptyCell.textContent =
                'No products added yet.';

            emptyRow.appendChild(
                emptyCell
            );

            purchaseItemsTableBody.appendChild(
                emptyRow
            );

            updatePurchaseSummary();

            return;

        }


        purchaseItems.forEach(
            function (item, index) {

                const row =
                    document.createElement('tr');


                /*
                |--------------------------------------------------------------------------
                | PRODUCT
                |--------------------------------------------------------------------------
                */

                const productCell =
                    document.createElement('td');

                const productNameElement =
                    document.createElement('span');

                productNameElement.className =
                    'purchase-item-product-name';

                productNameElement.textContent =
                    item.product_name;

                productCell.appendChild(
                    productNameElement
                );


                if (
                    item.barcode !== ''
                ) {

                    const barcodeElement =
                        document.createElement('span');

                    barcodeElement.className =
                        'purchase-item-barcode';

                    barcodeElement.textContent =
                        'Barcode: ' +
                        item.barcode;

                    productCell.appendChild(
                        barcodeElement
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | QUANTITY
                |--------------------------------------------------------------------------
                */

                const quantityCell =
                    document.createElement('td');

                quantityCell.textContent =
                    formatQuantity(
                        item.quantity
                    );


                /*
                |--------------------------------------------------------------------------
                | UNIT
                |--------------------------------------------------------------------------
                */

                const unitCell =
                    document.createElement('td');

                unitCell.textContent =
                    item.unit;


                /*
                |--------------------------------------------------------------------------
                | UNIT COST
                |--------------------------------------------------------------------------
                */

                const unitCostCell =
                    document.createElement('td');

                unitCostCell.textContent =
                    formatPurchaseCurrency(
                        item.unit_cost
                    );


                /*
                |--------------------------------------------------------------------------
                | LINE TOTAL
                |--------------------------------------------------------------------------
                */

                const lineTotalCell =
                    document.createElement('td');

                lineTotalCell.className =
                    'purchase-item-line-total';

                lineTotalCell.textContent =
                    formatPurchaseCurrency(
                        item.line_total
                    );


                /*
                |--------------------------------------------------------------------------
                | REMOVE ACTION
                |--------------------------------------------------------------------------
                */

                const actionCell =
                    document.createElement('td');

                const removeButton =
                    document.createElement('button');

                removeButton.type =
                    'button';

                removeButton.className =
                    'purchase-item-remove-button';

                removeButton.textContent =
                    'Remove';

                removeButton.dataset.index =
                    String(index);

                removeButton.addEventListener(
                    'click',
                    function () {

                        removePurchaseItem(
                            index
                        );

                    }
                );


                actionCell.appendChild(
                    removeButton
                );


                /*
                |--------------------------------------------------------------------------
                | ADD CELLS TO ROW
                |--------------------------------------------------------------------------
                */

                row.appendChild(
                    productCell
                );

                row.appendChild(
                    quantityCell
                );

                row.appendChild(
                    unitCell
                );

                row.appendChild(
                    unitCostCell
                );

                row.appendChild(
                    lineTotalCell
                );

                row.appendChild(
                    actionCell
                );


                purchaseItemsTableBody.appendChild(
                    row
                );

            }
        );


        updatePurchaseSummary();

    }


    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE PRODUCT
    |--------------------------------------------------------------------------
    */

    function isProductAlreadyAdded(productId) {

        const numericProductId =
            Number(productId);

        return purchaseItems.some(
            function (item) {

                return Number(
                    item.product_id
                ) === numericProductId;

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | STAGE 3 - ADD PURCHASE ITEM
    |--------------------------------------------------------------------------
    */

    function addPurchaseItem() {

        clearPurchaseItemMessage();


        /*
        |--------------------------------------------------------------------------
        | VALIDATE PRODUCT
        |--------------------------------------------------------------------------
        */

        const product =
            findPurchaseProduct(
                productSearch.value
            );

        if (!product) {

            showPurchaseItemMessage(
                'Please select a valid product from the database.',
                'error'
            );

            productSearch.focus();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE PRODUCT
        |--------------------------------------------------------------------------
        */

        if (
            isProductAlreadyAdded(
                product.product_id
            )
        ) {

            showPurchaseItemMessage(
                'This product has already been added to the purchase list.',
                'error'
            );

            productSearch.focus();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE QUANTITY
        |--------------------------------------------------------------------------
        */

        const quantity =
            Number(
                productQuantity.value
            );

        if (
            productQuantity.value.trim() === '' ||
            !Number.isFinite(quantity) ||
            quantity <= 0
        ) {

            showPurchaseItemMessage(
                'Quantity must be a valid positive number.',
                'error'
            );

            productQuantity.focus();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE UNIT COST
        |--------------------------------------------------------------------------
        */

        const unitCost =
            Number(
                productCost.value
            );

        if (
            productCost.value.trim() === '' ||
            !Number.isFinite(unitCost) ||
            unitCost < 0
        ) {

            showPurchaseItemMessage(
                'Unit cost must be a valid non-negative number.',
                'error'
            );

            productCost.focus();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | CALCULATE LINE TOTAL
        |--------------------------------------------------------------------------
        */

        const lineTotal =
            Number(
                (
                    quantity *
                    unitCost
                ).toFixed(2)
            );


        /*
        |--------------------------------------------------------------------------
        | CREATE TEMPORARY ITEM
        |--------------------------------------------------------------------------
        */

        const purchaseItem = {

            product_id:
                Number(
                    product.product_id
                ),

            product_name:
                String(
                    product.product_name
                ),

            barcode:
                String(
                    product.barcode || ''
                ),

            quantity:
                quantity,

            unit:
                String(
                    product.unit
                ),

            unit_cost:
                unitCost,

            line_total:
                lineTotal

        };


        /*
        |--------------------------------------------------------------------------
        | ADD TO BROWSER MEMORY
        |--------------------------------------------------------------------------
        */

        purchaseItems.push(
            purchaseItem
        );


        /*
        |--------------------------------------------------------------------------
        | REFRESH TABLE AND SUMMARY
        |--------------------------------------------------------------------------
        */

        renderPurchaseItems();


        /*
        |--------------------------------------------------------------------------
        | SUCCESS MESSAGE
        |--------------------------------------------------------------------------
        */

        showPurchaseItemMessage(
            product.product_name +
            ' was added to the temporary purchase list.',
            'success'
        );


        /*
        |--------------------------------------------------------------------------
        | RESET PRODUCT ENTRY
        |--------------------------------------------------------------------------
        */

        productSearch.value =
            '';

        selectedProductId.value =
            '';

        productQuantity.value =
            '';

        productCost.value =
            '';

        selectedProductInfo.classList.remove(
            'visible'
        );


        /*
        |--------------------------------------------------------------------------
        | FOCUS PRODUCT SEARCH
        |--------------------------------------------------------------------------
        */

        productSearch.focus();

    }


    /*
    |--------------------------------------------------------------------------
    | STAGE 3 - REMOVE PURCHASE ITEM
    |--------------------------------------------------------------------------
    */

    function removePurchaseItem(index) {

        if (
            index < 0 ||
            index >= purchaseItems.length
        ) {
            return;
        }


        const removedItem =
            purchaseItems[index];


        purchaseItems.splice(
            index,
            1
        );


        renderPurchaseItems();


        showPurchaseItemMessage(
            removedItem.product_name +
            ' was removed from the temporary purchase list.',
            'success'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | STAGE 5 - CLEAR TEMPORARY PURCHASE AFTER SUCCESS
    |--------------------------------------------------------------------------
    */

    function clearTemporaryPurchaseAfterSave() {

        purchaseItems =
            [];

        renderPurchaseItems();


        productSearch.value =
            '';

        selectedProductId.value =
            '';

        productQuantity.value =
            '';

        productCost.value =
            '';

        selectedProductInfo.classList.remove(
            'visible'
        );

        selectedProductName.textContent =
            'Product';

        selectedProductIdDisplay.textContent =
            '—';

        selectedProductUnit.textContent =
            '—';

        selectedProductStock.textContent =
            '—';

        selectedProductMinimumStock.textContent =
            '—';

        selectedProductStatus.textContent =
            '—';

    }


    /*
    |--------------------------------------------------------------------------
    | STAGE 5 - SAVE PURCHASE
    |--------------------------------------------------------------------------
    |
    | Sends the temporary purchase to this same PHP page.
    |
    | The server:
    | - validates the request again.
    | - begins a transaction.
    | - creates purchases row.
    | - creates purchase_items rows.
    | - updates product stock.
    | - commits only when every operation succeeds.
    | - rolls back everything on failure.
    |--------------------------------------------------------------------------
    */

    async function savePurchase() {

        if (savePurchaseInProgress) {

            return;

        }


        clearPurchaseItemMessage();


        /*
        |--------------------------------------------------------------------------
        | VALIDATE SUPPLIER
        |--------------------------------------------------------------------------
        */

        if (
            supplierId.value === ''
        ) {

            showPurchaseItemMessage(
                'Please select a supplier before saving the purchase.',
                'error'
            );

            supplierId.focus();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE PURCHASE DATE
        |--------------------------------------------------------------------------
        */

        if (
            purchaseDate.value === ''
        ) {

            showPurchaseItemMessage(
                'Please select a purchase date.',
                'error'
            );

            purchaseDate.focus();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE INVOICE NUMBER
        |--------------------------------------------------------------------------
        */

        const currentInvoiceNo =
            invoiceNo.value.trim();

        if (
            currentInvoiceNo === ''
        ) {

            showPurchaseItemMessage(
                'PO / Invoice Number is required.',
                'error'
            );

            invoiceNo.focus();

            return;

        }

        if (
            currentInvoiceNo.length > 50
        ) {

            showPurchaseItemMessage(
                'PO / Invoice Number cannot exceed 50 characters.',
                'error'
            );

            invoiceNo.focus();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE TEMPORARY ITEMS
        |--------------------------------------------------------------------------
        */

        if (
            purchaseItems.length === 0
        ) {

            showPurchaseItemMessage(
                'Please add at least one product before saving the purchase.',
                'error'
            );

            productSearch.focus();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE ITEMS BEFORE REQUEST
        |--------------------------------------------------------------------------
        */

        const invalidItem =
            purchaseItems.find(
                function (item) {

                    const quantity =
                        Number(
                            item.quantity
                        );

                    const unitCost =
                        Number(
                            item.unit_cost
                        );

                    return (
                        !Number.isInteger(
                            quantity
                        ) ||
                        quantity <= 0 ||
                        !Number.isFinite(
                            unitCost
                        ) ||
                        unitCost < 0
                    );

                }
            );


        if (invalidItem) {

            showPurchaseItemMessage(
                'One or more purchase items contain invalid quantity or unit cost values.',
                'error'
            );

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | CALCULATE CURRENT STAGE 4 TOTAL
        |--------------------------------------------------------------------------
        */

        let currentTotal =
            0;

        purchaseItems.forEach(
            function (item) {

                currentTotal +=
                    Number(
                        item.line_total
                    );

            }
        );

        currentTotal =
            Number(
                currentTotal.toFixed(2)
            );


        /*
        |--------------------------------------------------------------------------
        | SET SAVE STATE
        |--------------------------------------------------------------------------
        */

        savePurchaseInProgress =
            true;

        savePurchaseButton.disabled =
            true;

        savePurchaseButton.textContent =
            'Saving Purchase...';

        addPurchaseProductButton.disabled =
            true;


        /*
        |--------------------------------------------------------------------------
        | PREPARE FORM DATA
        |--------------------------------------------------------------------------
        */

        const formData =
            new FormData();

        formData.append(
            'save_purchase',
            '1'
        );

        formData.append(
            'supplier_id',
            supplierId.value
        );

        formData.append(
            'purchase_date',
            purchaseDate.value
        );

        formData.append(
            'invoice_no',
            currentInvoiceNo
        );

        formData.append(
            'purchase_total',
            currentTotal.toFixed(2)
        );

        formData.append(
            'payment_status',
            paymentStatus.value
        );

        formData.append(
            'remarks',
            purchaseRemarks.value.trim()
        );

        formData.append(
            'csrf_token',
            purchaseCsrfToken
        );

        formData.append(
            'purchase_items',
            JSON.stringify(
                purchaseItems
            )
        );


        try {

            /*
            |--------------------------------------------------------------------------
            | SEND SAVE REQUEST
            |--------------------------------------------------------------------------
            */

            const response =
                await fetch(
                    window.location.href,
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    }
                );


            /*
            |--------------------------------------------------------------------------
            | READ JSON RESPONSE
            |--------------------------------------------------------------------------
            */

            let result;

            try {

                result =
                    await response.json();

            } catch (jsonError) {

                throw new Error(
                    'The server returned an invalid response. Please try again.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | HANDLE SERVER ERROR
            |--------------------------------------------------------------------------
            */

            if (
                !response.ok ||
                !result ||
                result.success !== true
            ) {

                throw new Error(
                    (
                        result &&
                        result.message
                    )
                        ? result.message
                        : 'Unable to save the purchase. Please try again.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            clearTemporaryPurchaseAfterSave();


            if (
                result.purchase_id &&
                Number.isInteger(
                    Number(result.purchase_id)
                )
            ) {

                lastSavedPurchaseId =
                    Number(result.purchase_id);

            }


            invoiceNo.value =
                '';

            paymentStatus.value =
                'Paid';

            purchaseRemarks.value =
                '';


            showPurchaseItemMessage(
                result.message ||
                'Purchase saved successfully and stock was updated.',
                'success'
            );


            /*
            |--------------------------------------------------------------------------
            | KEEP PURCHASE DATE
            |--------------------------------------------------------------------------
            |
            | The existing date is intentionally preserved so the next
            | purchase starts with today's/current selected date.
            |--------------------------------------------------------------------------
            */


        } catch (error) {

            showPurchaseItemMessage(
                error.message ||
                'Unable to save the purchase. Please try again.',
                'error'
            );

        } finally {

            /*
            |--------------------------------------------------------------------------
            | RESTORE SAVE STATE
            |--------------------------------------------------------------------------
            */

            savePurchaseInProgress =
                false;

            savePurchaseButton.disabled =
                false;

            savePurchaseButton.textContent =
                'Save Purchase';

            addPurchaseProductButton.disabled =
                false;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT SEARCH INPUT EVENT
    |--------------------------------------------------------------------------
    */

    productSearch.addEventListener(
        'input',
        function () {

            const product =
                findPurchaseProduct(
                    productSearch.value
                );

            if (product) {

                showSelectedProduct(
                    product
                );

                clearPurchaseItemMessage();

            } else {

                clearSelectedProduct();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | PRODUCT SEARCH CHANGE EVENT
    |--------------------------------------------------------------------------
    */

    productSearch.addEventListener(
        'change',
        function () {

            const product =
                findPurchaseProduct(
                    productSearch.value
                );

            if (product) {

                showSelectedProduct(
                    product
                );

                clearPurchaseItemMessage();

            } else {

                clearSelectedProduct();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | QUANTITY INPUT VALIDATION FEEDBACK
    |--------------------------------------------------------------------------
    */

    productQuantity.addEventListener(
        'input',
        function () {

            if (
                productQuantity.value !== ''
            ) {

                const quantity =
                    Number(
                        productQuantity.value
                    );

                if (
                    Number.isFinite(quantity) &&
                    quantity > 0
                ) {

                    productQuantity.setCustomValidity(
                        ''
                    );

                } else {

                    productQuantity.setCustomValidity(
                        'Quantity must be a positive number.'
                    );

                }

            } else {

                productQuantity.setCustomValidity(
                    ''
                );

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | UNIT COST INPUT VALIDATION FEEDBACK
    |--------------------------------------------------------------------------
    */

    productCost.addEventListener(
        'input',
        function () {

            if (
                productCost.value !== ''
            ) {

                const unitCost =
                    Number(
                        productCost.value
                    );

                if (
                    Number.isFinite(unitCost) &&
                    unitCost >= 0
                ) {

                    productCost.setCustomValidity(
                        ''
                    );

                } else {

                    productCost.setCustomValidity(
                        'Unit cost must be zero or greater.'
                    );

                }

            } else {

                productCost.setCustomValidity(
                    ''
                );

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | ADD BUTTON EVENT
    |--------------------------------------------------------------------------
    */

    addPurchaseProductButton.addEventListener(
        'click',
        function () {

            addPurchaseItem();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | SAVE PURCHASE BUTTON EVENT
    |--------------------------------------------------------------------------
    */

    savePurchaseButton.addEventListener(
        'click',
        function () {

            savePurchase();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | ENTER KEY SUPPORT
    |--------------------------------------------------------------------------
    |
    | Pressing Enter in Quantity or Unit Cost adds the item.
    |--------------------------------------------------------------------------
    */

    productQuantity.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Enter'
            ) {

                event.preventDefault();

                addPurchaseItem();

            }

        }
    );


    productCost.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Enter'
            ) {

                event.preventDefault();

                addPurchaseItem();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | STAGE 8 - PRINT INVOICE
    |--------------------------------------------------------------------------
    |
    | Printing is read-only. The saved purchase is retrieved from the
    | database, rendered in a separate print window, and sent to the
    | browser print dialog. No purchase or stock data is changed.
    |--------------------------------------------------------------------------
    */

    async function printSavedPurchaseInvoice() {

        clearPurchaseItemMessage();

        if (!lastSavedPurchaseId || lastSavedPurchaseId <= 0) {

            showPurchaseItemMessage(
                'There is no saved purchase available to print yet.',
                'error'
            );

            return;

        }

        const printWindow =
            window.open(
                'about:blank',
                'purchaseInvoiceWindow'
            );

        if (!printWindow) {

            showPurchaseItemMessage(
                'The print window was blocked by the browser. Please allow pop-ups for this site and try again.',
                'error'
            );

            return;

        }

        printWindow.document.write(
            '<!doctype html><html><head><title>Purchase Invoice</title></head><body style="font-family:Arial,sans-serif;padding:40px">Loading invoice...</body></html>'
        );
        printWindow.document.close();

        try {

            const formData =
                new FormData();

            formData.append(
                'print_invoice',
                '1'
            );

            formData.append(
                'purchase_id',
                String(lastSavedPurchaseId)
            );

            const response =
                await fetch(
                    window.location.href,
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    }
                );

            let result;

            try {

                result =
                    await response.json();

            } catch (jsonError) {

                throw new Error(
                    'The server returned an invalid invoice response.'
                );

            }

            if (
                !response.ok ||
                !result ||
                result.success !== true
            ) {

                throw new Error(
                    (
                        result &&
                        result.message
                    )
                        ? result.message
                        : 'Unable to load the invoice.'
                );

            }

            renderPrintablePurchaseInvoice(
                printWindow,
                result.purchase,
                result.items
            );

        } catch (error) {

            printWindow.close();

            showPurchaseItemMessage(
                error.message ||
                'Unable to print the purchase invoice. Please try again.',
                'error'
            );

        }

    }


    function escapePrintHtml(value) {

        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');

    }


    function formatPrintMoney(value) {

        const amount = Number(value);

        return Number.isFinite(amount)
            ? amount.toFixed(2)
            : '0.00';

    }


    function renderPrintablePurchaseInvoice(
        printWindow,
        purchase,
        items
    ) {

        const shopName =
            purchase.shop_name ||
            'My Grocery Shop';

        const supplierName =
            purchase.supplier_name ||
            '—';

        const supplierCompany =
            purchase.company ||
            '';

        const supplierPhone =
            purchase.supplier_phone ||
            '';

        const supplierAddress =
            purchase.supplier_address ||
            '';

        const shopPhone =
            purchase.shop_phone ||
            '';

        const shopEmail =
            purchase.shop_email ||
            '';

        const shopAddress =
            purchase.shop_address ||
            '';

        const itemRows =
            items.map(function (item, index) {

                return `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <strong>${escapePrintHtml(item.product_name)}</strong>
                            ${item.barcode ? `<small>Barcode: ${escapePrintHtml(item.barcode)}</small>` : ''}
                        </td>
                        <td>${escapePrintHtml(item.unit)}</td>
                        <td>${escapePrintHtml(item.quantity)}</td>
                        <td>৳${formatPrintMoney(item.purchase_price)}</td>
                        <td>৳${formatPrintMoney(item.subtotal)}</td>
                    </tr>
                `;

            }).join('');

        const totalAmount =
            formatPrintMoney(
                purchase.total_amount
            );

        printWindow.document.open();

        printWindow.document.write(`
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Purchase Invoice - ${escapePrintHtml(purchase.invoice_no)}</title>
                <style>
                    * { box-sizing: border-box; }
                    body { margin: 0; padding: 28px; background: #ffffff; color: #111827; font-family: Arial, Helvetica, sans-serif; font-size: 13px; }
                    .invoice { max-width: 900px; margin: 0 auto; }
                    .header { display: flex; justify-content: space-between; gap: 30px; padding-bottom: 22px; border-bottom: 2px solid #111827; }
                    .shop-name { margin: 0 0 7px; font-size: 27px; font-weight: 700; }
                    .muted { color: #6b7280; line-height: 1.6; }
                    .invoice-title { text-align: right; }
                    .invoice-title h1 { margin: 0 0 8px; font-size: 25px; letter-spacing: 1px; }
                    .invoice-meta { line-height: 1.7; }
                    .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin: 24px 0; }
                    .party-box { padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px; }
                    .party-box h3 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; color: #6b7280; letter-spacing: .6px; }
                    .party-name { font-weight: 700; font-size: 15px; margin-bottom: 4px; }
                    table { width: 100%; border-collapse: collapse; }
                    th { padding: 10px 8px; text-align: left; background: #f3f4f6; border-bottom: 1px solid #d1d5db; font-size: 11px; text-transform: uppercase; }
                    td { padding: 11px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
                    td small { display: block; margin-top: 3px; color: #9ca3af; }
                    .number { text-align: right; }
                    .summary { width: 320px; margin-left: auto; margin-top: 20px; }
                    .summary-row { display: flex; justify-content: space-between; padding: 7px 0; }
                    .summary-total { margin-top: 5px; padding-top: 12px; border-top: 2px solid #111827; font-size: 17px; font-weight: 700; }
                    .status { margin-top: 14px; display: inline-block; padding: 5px 10px; border-radius: 999px; background: #dcfce7; color: #15803d; font-weight: 700; font-size: 11px; }
                    .footer { margin-top: 45px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 11px; }
                    @media print { body { padding: 0; } .invoice { max-width: none; } }
                </style>
            </head>
            <body>
                <div class="invoice">
                    <div class="header">
                        <div>
                            <h2 class="shop-name">${escapePrintHtml(shopName)}</h2>
                            ${shopAddress ? `<div class="muted">${escapePrintHtml(shopAddress)}</div>` : ''}
                            ${shopPhone ? `<div class="muted">Phone: ${escapePrintHtml(shopPhone)}</div>` : ''}
                            ${shopEmail ? `<div class="muted">Email: ${escapePrintHtml(shopEmail)}</div>` : ''}
                        </div>
                        <div class="invoice-title">
                            <h1>PURCHASE INVOICE</h1>
                            <div class="invoice-meta"><strong>Invoice:</strong> ${escapePrintHtml(purchase.invoice_no)}</div>
                            <div class="invoice-meta"><strong>Date:</strong> ${escapePrintHtml(purchase.purchase_date)}</div>
                            <div class="invoice-meta"><strong>Status:</strong> ${escapePrintHtml(purchase.payment_status || 'Paid')}</div>
                        </div>
                    </div>

                    <div class="parties">
                        <div class="party-box">
                            <h3>Supplier</h3>
                            <div class="party-name">${escapePrintHtml(supplierName)}</div>
                            ${supplierCompany ? `<div class="muted">${escapePrintHtml(supplierCompany)}</div>` : ''}
                            ${supplierPhone ? `<div class="muted">Phone: ${escapePrintHtml(supplierPhone)}</div>` : ''}
                            ${supplierAddress ? `<div class="muted">${escapePrintHtml(supplierAddress)}</div>` : ''}
                        </div>
                        <div class="party-box">
                            <h3>Purchase Details</h3>
                            <div class="muted"><strong>Purchase ID:</strong> ${escapePrintHtml(purchase.purchase_id)}</div>
                            <div class="muted"><strong>Created:</strong> ${escapePrintHtml(purchase.created_at)}</div>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Unit</th>
                                <th>Qty</th>
                                <th>Unit Cost</th>
                                <th>Line Total</th>
                            </tr>
                        </thead>
                        <tbody>${itemRows}</tbody>
                    </table>

                    <div class="summary">
                        <div class="summary-row"><span>Items</span><strong>${items.length}</strong></div>
                        <div class="summary-row summary-total"><span>Total</span><strong>৳${totalAmount}</strong></div>
                    </div>

                    <div class="status">${escapePrintHtml(purchase.payment_status || 'Paid')}</div>

                    ${purchase.remarks ? `<div style="margin-top:18px"><strong>Remarks:</strong> ${escapePrintHtml(purchase.remarks)}</div>` : ''}

                    <div class="footer">Thank you. This document was generated from the Grocery Shop Purchases Management system.</div>
                </div>
            </body>
            </html>
        `);

        printWindow.document.close();

        printWindow.focus();

        setTimeout(function () {
            printWindow.print();
        }, 250);

    }


    printInvoiceButton.addEventListener(
        'click',
        function () {

            printSavedPurchaseInvoice();

        }
    );
    /*
|--------------------------------------------------------------------------
| NEW PURCHASE BUTTON
|--------------------------------------------------------------------------
|
| Resets only the current purchase form and temporary browser-memory
| purchase items.
|
| No saved purchase is deleted.
| No database operation is performed.
|
*/

newPurchaseButton.addEventListener(
    'click',
    function () {

        /*
        |----------------------------------------------------------------------
        | RESET SUPPLIER
        |----------------------------------------------------------------------
        */

        const supplierSelect =
            document.getElementById('supplier_id');

        supplierSelect.value = '';


        /*
        |----------------------------------------------------------------------
        | RESET PURCHASE DATE
        |----------------------------------------------------------------------
        */

        const purchaseDate =
            document.getElementById('purchase_date');

        const today =
            new Date();

        const year =
            today.getFullYear();

        const month =
            String(
                today.getMonth() + 1
            ).padStart(2, '0');

        const day =
            String(
                today.getDate()
            ).padStart(2, '0');

        purchaseDate.value =
            year + '-' + month + '-' + day;


        /*
        |----------------------------------------------------------------------
        | RESET INVOICE NUMBER
        |----------------------------------------------------------------------
        */

        const invoiceNo =
            document.getElementById('invoice_no');

        invoiceNo.value = '';


        /*
        |----------------------------------------------------------------------
        | RESET PAYMENT STATUS / REMARKS
        |----------------------------------------------------------------------
        */

        if (paymentStatus) {
            paymentStatus.value = 'Paid';
        }

        if (purchaseRemarks) {
            purchaseRemarks.value = '';
        }


        /*
        |----------------------------------------------------------------------
        | CLEAR TEMPORARY PURCHASE ITEMS
        |----------------------------------------------------------------------
        */

        purchaseItems = [];


        /*
        |----------------------------------------------------------------------
        | RESET PRODUCT ENTRY
        |----------------------------------------------------------------------
        */

        productSearch.value = '';

        selectedProductId.value = '';

        productQuantity.value = '';

        productCost.value = '';


        /*
        |----------------------------------------------------------------------
        | RESET SELECTED PRODUCT INFORMATION
        |----------------------------------------------------------------------
        */

        clearSelectedProduct();


        /*
        |----------------------------------------------------------------------
        | CLEAR MESSAGE
        |----------------------------------------------------------------------
        */

        clearPurchaseItemMessage();


        /*
        |----------------------------------------------------------------------
        | REFRESH ITEMS TABLE AND SUMMARY
        |----------------------------------------------------------------------
        */

        renderPurchaseItems();


        /*
        |----------------------------------------------------------------------
        | FOCUS PRODUCT SEARCH
        |----------------------------------------------------------------------
        */

        productSearch.focus();

    }
);


    /*
    |--------------------------------------------------------------------------
    | INITIAL SUMMARY
    |--------------------------------------------------------------------------
    */

    renderPurchaseItems();

</script>


<?php require_once "../../includes/footer.php"; ?>
