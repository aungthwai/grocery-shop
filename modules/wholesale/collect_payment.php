<?php

session_start();

/*
|--------------------------------------------------------------------------
| WHOLESALE DUE PAYMENT ENDPOINT
|--------------------------------------------------------------------------
| Receives one customer payment and allocates it safely:
|
| 1. Remaining opening/legacy balance
| 2. Oldest unpaid wholesale sale
| 3. Next unpaid sale until the submitted amount is fully allocated
|
| All changes happen in one database transaction.
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=UTF-8');


function wholesalePaymentJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    );

    exit;
}


if (!isset($_SESSION['user_id'])) {
    wholesalePaymentJson(
        [
            'success' => false,
            'message' => 'Your session has expired. Please log in again.'
        ],
        401
    );
}


/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

require_once "../../includes/role_guard.php";
grocerEaseRequireAdmin(true);


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wholesalePaymentJson(
        [
            'success' => false,
            'message' => 'Invalid request method.'
        ],
        405
    );
}


require_once "../../config/database.php";


$transactionStarted = false;

try {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    $sessionToken =
        (string) ($_SESSION['wholesale_payment_csrf'] ?? '');

    $submittedToken =
        (string) ($_POST['csrf_token'] ?? '');

    if (
        $sessionToken === '' ||
        $submittedToken === '' ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        throw new Exception(
            'Your payment form session expired. Refresh the page and try again.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER
    |--------------------------------------------------------------------------
    */

    $customerIdValue =
        $_POST['customer_id'] ?? null;

    if (
        $customerIdValue === null ||
        filter_var(
            $customerIdValue,
            FILTER_VALIDATE_INT
        ) === false ||
        (int) $customerIdValue <= 0
    ) {
        throw new Exception(
            'Please select a valid wholesale customer.'
        );
    }

    $customerId =
        (int) $customerIdValue;


    /*
    |--------------------------------------------------------------------------
    | PAYMENT AMOUNT
    |--------------------------------------------------------------------------
    */

    $amountValue =
        trim((string) ($_POST['amount'] ?? ''));

    if (
        $amountValue === '' ||
        !is_numeric($amountValue)
    ) {
        throw new Exception(
            'Please enter a valid payment amount.'
        );
    }

    $paymentAmount =
        round((float) $amountValue, 2);

    if (
        !is_finite($paymentAmount) ||
        $paymentAmount <= 0
    ) {
        throw new Exception(
            'Payment amount must be greater than zero.'
        );
    }

    if ($paymentAmount > 99999999.99) {
        throw new Exception(
            'Payment amount is too large.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT DATE
    |--------------------------------------------------------------------------
    */

    $paymentDate =
        trim((string) ($_POST['payment_date'] ?? ''));

    $dateObject =
        DateTime::createFromFormat(
            '!Y-m-d',
            $paymentDate
        );

    $dateErrors =
        DateTime::getLastErrors();

    if (
        !$dateObject ||
        (
            is_array($dateErrors) &&
            (
                $dateErrors['warning_count'] > 0 ||
                $dateErrors['error_count'] > 0
            )
        ) ||
        $dateObject->format('Y-m-d') !== $paymentDate
    ) {
        throw new Exception(
            'Please choose a valid payment date.'
        );
    }

    if ($paymentDate > date('Y-m-d')) {
        throw new Exception(
            'Payment date cannot be in the future.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT METHOD
    |--------------------------------------------------------------------------
    */

    $paymentMethod =
        trim(
            (string) (
                $_POST['payment_method'] ??
                'Cash'
            )
        );

    $allowedMethods = [
        'Cash',
        'Card',
        'Mobile Banking'
    ];

    if (
        !in_array(
            $paymentMethod,
            $allowedMethods,
            true
        )
    ) {
        throw new Exception(
            'Please choose a valid payment method.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | NOTES
    |--------------------------------------------------------------------------
    */

    $notes =
        trim((string) ($_POST['notes'] ?? ''));

    $notesLength =
        function_exists('mb_strlen')
            ? mb_strlen($notes)
            : strlen($notes);

    if ($notesLength > 1000) {
        throw new Exception(
            'Payment notes must be 1000 characters or fewer.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    if (!mysqli_begin_transaction($conn)) {
        throw new Exception(
            'Unable to start the payment transaction.'
        );
    }

    $transactionStarted = true;


    /*
    |--------------------------------------------------------------------------
    | LOCK CUSTOMER
    |--------------------------------------------------------------------------
    */

    $customerStmt =
        mysqli_prepare(
            $conn,
            "
                SELECT
                    customer_id,
                    customer_name,
                    phone,
                    customer_type,
                    account_status,
                    opening_due,
                    total_due
                FROM customers
                WHERE customer_id = ?
                LIMIT 1
                FOR UPDATE
            "
        );

    if (!$customerStmt) {
        throw new Exception(
            'Unable to prepare customer verification.'
        );
    }

    mysqli_stmt_bind_param(
        $customerStmt,
        'i',
        $customerId
    );

    if (!mysqli_stmt_execute($customerStmt)) {
        mysqli_stmt_close($customerStmt);

        throw new Exception(
            'Unable to verify the selected customer.'
        );
    }

    $customerResult =
        mysqli_stmt_get_result($customerStmt);

    $customer =
        $customerResult
            ? mysqli_fetch_assoc($customerResult)
            : null;

    mysqli_stmt_close($customerStmt);


    if (!$customer) {
        throw new Exception(
            'The selected customer no longer exists.'
        );
    }

    if (
        $customer['customer_type'] !==
        'Wholesale'
    ) {
        throw new Exception(
            'Payments on this page are only for wholesale customers.'
        );
    }


    $currentTotalDue =
        round(
            (float) $customer['total_due'],
            2
        );

    if ($currentTotalDue <= 0) {
        throw new Exception(
            'This customer no longer has an outstanding due.'
        );
    }

    if (
        $paymentAmount >
        $currentTotalDue + 0.001
    ) {
        throw new Exception(
            'Payment cannot be greater than the current outstanding due of ৳' .
            number_format(
                $currentTotalDue,
                2
            ) .
            '.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOCK OUTSTANDING SALES
    |--------------------------------------------------------------------------
    */

    $salesStmt =
        mysqli_prepare(
            $conn,
            "
                SELECT
                    sale_id,
                    invoice_no,
                    sale_date,
                    total_amount,
                    paid_amount,
                    due_amount
                FROM sales
                WHERE customer_id = ?
                  AND due_amount > 0
                ORDER BY
                    sale_date ASC,
                    sale_id ASC
                FOR UPDATE
            "
        );

    if (!$salesStmt) {
        throw new Exception(
            'Unable to prepare outstanding invoice verification.'
        );
    }

    mysqli_stmt_bind_param(
        $salesStmt,
        'i',
        $customerId
    );

    if (!mysqli_stmt_execute($salesStmt)) {
        mysqli_stmt_close($salesStmt);

        throw new Exception(
            'Unable to load outstanding invoices.'
        );
    }

    $salesResult =
        mysqli_stmt_get_result($salesStmt);

    $dueSales = [];
    $currentSaleDue = 0.00;

    if ($salesResult) {

        while (
            $sale =
            mysqli_fetch_assoc($salesResult)
        ) {

            $sale['due_amount'] =
                round(
                    (float) $sale['due_amount'],
                    2
                );

            $sale['paid_amount'] =
                round(
                    (float) $sale['paid_amount'],
                    2
                );

            $sale['total_amount'] =
                round(
                    (float) $sale['total_amount'],
                    2
                );

            $currentSaleDue =
                round(
                    $currentSaleDue +
                    $sale['due_amount'],
                    2
                );

            $dueSales[] = $sale;
        }
    }

    mysqli_stmt_close($salesStmt);


    /*
    |--------------------------------------------------------------------------
    | DETERMINE NON-INVOICE / OPENING BALANCE
    |--------------------------------------------------------------------------
    |
    | total_due is the authoritative current customer balance.
    | sales.due_amount represents current invoice dues.
    |
    | Any positive difference is treated as remaining opening/legacy due.
    |--------------------------------------------------------------------------
    */

    if (
        $currentSaleDue >
        $currentTotalDue + 0.01
    ) {
        throw new Exception(
            'This customer has inconsistent due data. Please review their invoice balances before collecting a payment.'
        );
    }

    $openingBalanceOutstanding =
        round(
            max(
                0,
                $currentTotalDue -
                $currentSaleDue
            ),
            2
        );


    /*
    |--------------------------------------------------------------------------
    | COLLECTION REFERENCE
    |--------------------------------------------------------------------------
    */

    $collectionRef =
        'COL-' .
        date('Ymd') .
        '-' .
        strtoupper(
            bin2hex(
                random_bytes(3)
            )
        );


    /*
    |--------------------------------------------------------------------------
    | PREPARE PAYMENT INSERTS
    |--------------------------------------------------------------------------
    */

    $openingPaymentStmt =
        mysqli_prepare(
            $conn,
            "
                INSERT INTO payments
                (
                    sale_id,
                    customer_id,
                    payment_date,
                    amount,
                    payment_method,
                    payment_type,
                    collection_ref,
                    notes
                )
                VALUES
                (
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Opening Due',
                    ?,
                    ?
                )
            "
        );

    $salePaymentStmt =
        mysqli_prepare(
            $conn,
            "
                INSERT INTO payments
                (
                    sale_id,
                    customer_id,
                    payment_date,
                    amount,
                    payment_method,
                    payment_type,
                    collection_ref,
                    notes
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Due Collection',
                    ?,
                    ?
                )
            "
        );

    $saleUpdateStmt =
        mysqli_prepare(
            $conn,
            "
                UPDATE sales
                SET
                    paid_amount = ?,
                    due_amount = ?
                WHERE sale_id = ?
                  AND customer_id = ?
            "
        );

    if (
        !$openingPaymentStmt ||
        !$salePaymentStmt ||
        !$saleUpdateStmt
    ) {

        if ($openingPaymentStmt) {
            mysqli_stmt_close(
                $openingPaymentStmt
            );
        }

        if ($salePaymentStmt) {
            mysqli_stmt_close(
                $salePaymentStmt
            );
        }

        if ($saleUpdateStmt) {
            mysqli_stmt_close(
                $saleUpdateStmt
            );
        }

        throw new Exception(
            'Unable to prepare payment allocation.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ALLOCATE PAYMENT
    |--------------------------------------------------------------------------
    */

    $remaining =
        $paymentAmount;

    $allocations = [];


    /*
    |--------------------------------------------------------------------------
    | 1. OPENING / LEGACY DUE FIRST
    |--------------------------------------------------------------------------
    */

    if (
        $remaining > 0 &&
        $openingBalanceOutstanding > 0
    ) {

        $allocation =
            round(
                min(
                    $remaining,
                    $openingBalanceOutstanding
                ),
                2
            );

        mysqli_stmt_bind_param(
            $openingPaymentStmt,
            'isdsss',
            $customerId,
            $paymentDate,
            $allocation,
            $paymentMethod,
            $collectionRef,
            $notes
        );

        if (
            !mysqli_stmt_execute(
                $openingPaymentStmt
            )
        ) {
            throw new Exception(
                'Unable to record the opening-balance payment.'
            );
        }

        $allocations[] = [
            'type' => 'Opening Due',
            'invoice_no' => null,
            'amount' => $allocation
        ];

        $remaining =
            round(
                $remaining -
                $allocation,
                2
            );
    }


    /*
    |--------------------------------------------------------------------------
    | 2. OLDEST OUTSTANDING INVOICES
    |--------------------------------------------------------------------------
    */

    foreach ($dueSales as $sale) {

        if ($remaining <= 0) {
            break;
        }

        $saleDue =
            round(
                (float) $sale['due_amount'],
                2
            );

        if ($saleDue <= 0) {
            continue;
        }

        $allocation =
            round(
                min(
                    $remaining,
                    $saleDue
                ),
                2
            );

        $newDue =
            round(
                max(
                    0,
                    $saleDue -
                    $allocation
                ),
                2
            );

        $newPaid =
            round(
                min(
                    (float) $sale['total_amount'],
                    (float) $sale['paid_amount'] +
                    $allocation
                ),
                2
            );

        $saleId =
            (int) $sale['sale_id'];


        mysqli_stmt_bind_param(
            $saleUpdateStmt,
            'ddii',
            $newPaid,
            $newDue,
            $saleId,
            $customerId
        );

        if (
            !mysqli_stmt_execute(
                $saleUpdateStmt
            ) ||
            mysqli_stmt_affected_rows(
                $saleUpdateStmt
            ) !== 1
        ) {
            throw new Exception(
                'Unable to update invoice ' .
                $sale['invoice_no'] .
                '.'
            );
        }


        mysqli_stmt_bind_param(
            $salePaymentStmt,
            'iisdsss',
            $saleId,
            $customerId,
            $paymentDate,
            $allocation,
            $paymentMethod,
            $collectionRef,
            $notes
        );

        if (
            !mysqli_stmt_execute(
                $salePaymentStmt
            )
        ) {
            throw new Exception(
                'Unable to record payment for invoice ' .
                $sale['invoice_no'] .
                '.'
            );
        }


        $allocations[] = [
            'type' => 'Due Collection',
            'invoice_no' =>
                (string) $sale['invoice_no'],
            'amount' => $allocation
        ];


        $remaining =
            round(
                $remaining -
                $allocation,
                2
            );
    }


    mysqli_stmt_close(
        $openingPaymentStmt
    );

    mysqli_stmt_close(
        $salePaymentStmt
    );

    mysqli_stmt_close(
        $saleUpdateStmt
    );


    if (abs($remaining) > 0.009) {
        throw new Exception(
            'Unable to allocate the complete payment. No changes were saved.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE CUSTOMER CURRENT DUE
    |--------------------------------------------------------------------------
    */

    $newTotalDue =
        round(
            max(
                0,
                $currentTotalDue -
                $paymentAmount
            ),
            2
        );

    $customerUpdateStmt =
        mysqli_prepare(
            $conn,
            "
                UPDATE customers
                SET total_due = ?
                WHERE customer_id = ?
            "
        );

    if (!$customerUpdateStmt) {
        throw new Exception(
            'Unable to prepare customer due update.'
        );
    }

    mysqli_stmt_bind_param(
        $customerUpdateStmt,
        'di',
        $newTotalDue,
        $customerId
    );

    if (
        !mysqli_stmt_execute(
            $customerUpdateStmt
        ) ||
        mysqli_stmt_affected_rows(
            $customerUpdateStmt
        ) !== 1
    ) {
        mysqli_stmt_close(
            $customerUpdateStmt
        );

        throw new Exception(
            'Unable to update the customer outstanding due.'
        );
    }

    mysqli_stmt_close(
        $customerUpdateStmt
    );


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    if (!mysqli_commit($conn)) {
        throw new Exception(
            'Unable to complete the payment transaction.'
        );
    }

    $transactionStarted = false;


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $methodLabel =
        $paymentMethod ===
        'Mobile Banking'
            ? 'bKash / Mobile Banking'
            : $paymentMethod;

    wholesalePaymentJson(
        [
            'success' => true,
            'message' =>
                'Payment of ৳' .
                number_format(
                    $paymentAmount,
                    2
                ) .
                ' recorded successfully for ' .
                $customer['customer_name'] .
                '.',
            'collection_ref' =>
                $collectionRef,
            'customer_id' =>
                $customerId,
            'customer_name' =>
                $customer['customer_name'],
            'payment_date' =>
                $paymentDate,
            'payment_method' =>
                $methodLabel,
            'amount' =>
                number_format(
                    $paymentAmount,
                    2,
                    '.',
                    ''
                ),
            'remaining_due' =>
                number_format(
                    $newTotalDue,
                    2,
                    '.',
                    ''
                ),
            'allocations' =>
                $allocations
        ]
    );


} catch (Throwable $error) {

    if ($transactionStarted) {
        mysqli_rollback($conn);
    }

    wholesalePaymentJson(
        [
            'success' => false,
            'message' =>
                $error->getMessage()
        ],
        400
    );
}
