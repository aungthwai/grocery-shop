<?php

session_start();

/*
|--------------------------------------------------------------------------
| WHOLESALE CUSTOMER DUE HISTORY
|--------------------------------------------------------------------------
| Final Module
|
| Read-only customer due history:
| - Customer information
| - Opening due
| - Current outstanding due
| - Wholesale sales / invoice history
| - Paid and remaining amounts
| - Payment history
| - 30+ day overdue rule
|
| Payment collection is enabled from this page.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
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
grocerEaseRequireAdmin();


require_once "../../config/database.php";


/*
|--------------------------------------------------------------------------
| BASE PATH / PAGE INFORMATION
|--------------------------------------------------------------------------
*/

$basePath = "/grocery-shop";
$pageTitle = "Wholesale Customer History";


/*
|--------------------------------------------------------------------------
| PAYMENT CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['wholesale_payment_csrf'])) {
    $_SESSION['wholesale_payment_csrf'] = bin2hex(random_bytes(32));
}

$paymentCsrfToken = $_SESSION['wholesale_payment_csrf'];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function wholesaleHistoryEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CUSTOMER ID
|--------------------------------------------------------------------------
*/

$customerId = isset($_GET['customer_id'])
    ? (int) $_GET['customer_id']
    : 0;

if ($customerId <= 0) {
    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| OVERDUE RULE
|--------------------------------------------------------------------------
| The database currently has no due_date column.
| A sale with remaining due older than 30 days is treated as overdue.
|--------------------------------------------------------------------------
*/

$overdueDays = 30;


/*
|--------------------------------------------------------------------------
| LOAD WHOLESALE CUSTOMER
|--------------------------------------------------------------------------
*/

$customer = null;

$customerSql = "
    SELECT
        customer_id,
        customer_name,
        phone,
        email,
        address,
        customer_type,
        account_status,
        opening_due,
        total_due,
        created_at
    FROM customers
    WHERE customer_id = ?
      AND customer_type = 'Wholesale'
    LIMIT 1
";

$customerStmt = mysqli_prepare($conn, $customerSql);

if ($customerStmt) {

    mysqli_stmt_bind_param(
        $customerStmt,
        "i",
        $customerId
    );

    mysqli_stmt_execute($customerStmt);

    $customerResult =
        mysqli_stmt_get_result($customerStmt);

    if ($customerResult) {
        $customer =
            mysqli_fetch_assoc($customerResult);
    }

    mysqli_stmt_close($customerStmt);
}


if (!$customer) {
    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| SALES / INVOICE HISTORY
|--------------------------------------------------------------------------
*/

$sales = [];

$salesSql = "
    SELECT
        sale_id,
        invoice_no,
        sale_date,
        total_amount,
        paid_amount,
        due_amount,
        payment_method,
        created_at
    FROM sales
    WHERE customer_id = ?
    ORDER BY
        sale_date DESC,
        sale_id DESC
";

$salesStmt =
    mysqli_prepare($conn, $salesSql);

if ($salesStmt) {

    mysqli_stmt_bind_param(
        $salesStmt,
        "i",
        $customerId
    );

    mysqli_stmt_execute($salesStmt);

    $salesResult =
        mysqli_stmt_get_result($salesStmt);

    if ($salesResult) {

        while (
            $row =
            mysqli_fetch_assoc($salesResult)
        ) {

            $saleTimestamp =
                strtotime($row['sale_date']);

            $overdueBoundary =
                strtotime("-{$overdueDays} days");

            $isOverdue =
                (float) $row['due_amount'] > 0 &&
                $saleTimestamp !== false &&
                $saleTimestamp < $overdueBoundary;


            if (
                (float) $row['due_amount'] <= 0
            ) {

                $row['display_status'] =
                    'Paid';

            } elseif ($isOverdue) {

                $row['display_status'] =
                    'Overdue';

            } elseif (
                (float) $row['paid_amount'] > 0
            ) {

                $row['display_status'] =
                    'Partial';

            } else {

                $row['display_status'] =
                    'Pending';

            }


            $sales[] = $row;
        }
    }

    mysqli_stmt_close($salesStmt);
}


/*
|--------------------------------------------------------------------------
| PAYMENT HISTORY
|--------------------------------------------------------------------------
*/

$payments = [];

$paymentsSql = "
    SELECT
        p.payment_id,
        p.sale_id,
        p.payment_date,
        p.amount,
        p.payment_method,
        p.payment_type,
        p.collection_ref,
        p.notes,
        p.created_at,
        s.invoice_no
    FROM payments p
    LEFT JOIN sales s
        ON p.sale_id = s.sale_id
    WHERE p.customer_id = ?
    ORDER BY
        p.payment_date DESC,
        p.payment_id DESC
";

$paymentsStmt =
    mysqli_prepare($conn, $paymentsSql);

if ($paymentsStmt) {

    mysqli_stmt_bind_param(
        $paymentsStmt,
        "i",
        $customerId
    );

    mysqli_stmt_execute($paymentsStmt);

    $paymentsResult =
        mysqli_stmt_get_result($paymentsStmt);

    if ($paymentsResult) {

        while (
            $row =
            mysqli_fetch_assoc($paymentsResult)
        ) {
            $payments[] = $row;
        }
    }

    mysqli_stmt_close($paymentsStmt);
}


/*
|--------------------------------------------------------------------------
| CUSTOMER SUMMARY
|--------------------------------------------------------------------------
*/

$totalSalesAmount = 0.00;
$totalInvoicePaid = 0.00;
$totalInvoiceDue = 0.00;
$dueInvoiceCount = 0;
$overdueInvoiceCount = 0;

foreach ($sales as $sale) {

    $totalSalesAmount +=
        (float) $sale['total_amount'];

    $totalInvoicePaid +=
        (float) $sale['paid_amount'];

    $totalInvoiceDue +=
        (float) $sale['due_amount'];

    if ((float) $sale['due_amount'] > 0) {
        $dueInvoiceCount++;
    }

    if (
        $sale['display_status'] ===
        'Overdue'
    ) {
        $overdueInvoiceCount++;
    }
}


$totalPaymentsRecorded = 0.00;
$totalDueCollections = 0.00;

foreach ($payments as $payment) {

    $totalPaymentsRecorded +=
        (float) $payment['amount'];

    if (
        in_array(
            $payment['payment_type'],
            ['Due Collection', 'Opening Due'],
            true
        )
    ) {
        $totalDueCollections +=
            (float) $payment['amount'];
    }
}


/*
|--------------------------------------------------------------------------
| PAYMENT MODAL CUSTOMER LIST
|--------------------------------------------------------------------------
*/

$collectCustomers = [];

if ((float) $customer['total_due'] > 0) {
    $collectCustomers[] = [
        'customer_id' => (int) $customer['customer_id'],
        'customer_name' => (string) $customer['customer_name'],
        'phone' => (string) ($customer['phone'] ?? ''),
        'total_due' => (float) $customer['total_due']
    ];
}


/*
|--------------------------------------------------------------------------
| SHARED HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>

<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<link rel="stylesheet" href="../../assets/css/wholesale.css">


<div class="app-layout">

    <!-- SIDEBAR -->
    <aside class="app-sidebar-slot">
        <?php require_once "../../includes/sidebar.php"; ?>
    </aside>


    <!-- MAIN -->
    <div class="app-main-slot">

        <!-- TOPBAR -->
        <header class="app-topbar-slot">
            <?php require_once "../../includes/topbar.php"; ?>
        </header>


        <!-- CONTENT -->
        <main class="dashboard-main-content">

            <div class="wholesale-page wholesale-history-page">


                <!-- =================================================
                     PAGE HEADING
                     ================================================= -->

                <div class="wholesale-history-heading">

                    <div>

                        <a
                            href="index.php"
                            class="wholesale-back-link"
                        >
                            ← Back to Wholesale Due
                        </a>

                        <h1>
                            Customer Due History
                        </h1>

                        <p>
                            Wholesale account, invoice and payment history.
                        </p>

                    </div>


                    <button
                        type="button"
                        class="wholesale-collect-main js-open-payment-modal"
                        data-customer-id="<?php echo (int) $customer['customer_id']; ?>"
                        <?php echo (float) $customer['total_due'] <= 0 ? 'disabled' : ''; ?>
                    >
                        + Collect Payment
                    </button>

                </div>


                <!-- =================================================
                     CUSTOMER INFORMATION
                     ================================================= -->

                <section class="wholesale-history-customer-card">

                    <div class="wholesale-history-customer-main">

                        <div class="wholesale-history-avatar">
                            <?php
                            echo strtoupper(
                                substr(
                                    $customer['customer_name'],
                                    0,
                                    1
                                )
                            );
                            ?>
                        </div>


                        <div>

                            <div class="wholesale-history-name-row">

                                <h2>
                                    <?php echo wholesaleHistoryEscape(
                                        $customer['customer_name']
                                    ); ?>
                                </h2>

                                <span
                                    class="wholesale-account-badge <?php echo strtolower(
                                        wholesaleHistoryEscape(
                                            $customer['account_status']
                                        )
                                    ); ?>"
                                >
                                    <?php echo wholesaleHistoryEscape(
                                        $customer['account_status']
                                    ); ?>
                                </span>

                            </div>


                            <div class="wholesale-history-contact">

                                <?php if (!empty($customer['phone'])): ?>

                                    <span>
                                        <?php echo wholesaleHistoryEscape(
                                            $customer['phone']
                                        ); ?>
                                    </span>

                                <?php endif; ?>


                                <?php if (!empty($customer['email'])): ?>

                                    <span>
                                        <?php echo wholesaleHistoryEscape(
                                            $customer['email']
                                        ); ?>
                                    </span>

                                <?php endif; ?>


                                <?php if (!empty($customer['address'])): ?>

                                    <span>
                                        <?php echo wholesaleHistoryEscape(
                                            $customer['address']
                                        ); ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>


                    <div class="wholesale-history-customer-meta">

                        <span>
                            Customer since
                        </span>

                        <strong>
                            <?php echo date(
                                'd M Y',
                                strtotime(
                                    $customer['created_at']
                                )
                            ); ?>
                        </strong>

                    </div>

                </section>


                <!-- =================================================
                     SUMMARY CARDS
                     ================================================= -->

                <div class="wholesale-history-summary-grid">


                    <div class="wholesale-history-summary-card current-due">

                        <span>
                            Current Outstanding Due
                        </span>

                        <strong>
                            ৳<?php echo number_format(
                                (float) $customer['total_due'],
                                2
                            ); ?>
                        </strong>

                    </div>


                    <div class="wholesale-history-summary-card opening-due">

                        <span>
                            Opening Due
                        </span>

                        <strong>
                            ৳<?php echo number_format(
                                (float) $customer['opening_due'],
                                2
                            ); ?>
                        </strong>

                    </div>


                    <div class="wholesale-history-summary-card">

                        <span>
                            Total Wholesale Sales
                        </span>

                        <strong>
                            ৳<?php echo number_format(
                                $totalSalesAmount,
                                2
                            ); ?>
                        </strong>

                    </div>


                    <div class="wholesale-history-summary-card">

                        <span>
                            Due Invoices
                        </span>

                        <strong>
                            <?php echo number_format(
                                $dueInvoiceCount
                            ); ?>
                        </strong>

                        <?php if ($overdueInvoiceCount > 0): ?>

                            <small>
                                <?php echo number_format(
                                    $overdueInvoiceCount
                                ); ?>
                                overdue
                            </small>

                        <?php endif; ?>

                    </div>


                </div>


                <!-- =================================================
                     INVOICE HISTORY
                     ================================================= -->

                <section class="wholesale-history-section">

                    <div class="wholesale-history-section-heading">

                        <div>

                            <h2>
                                Sales / Invoice History
                            </h2>

                            <p>
                                All wholesale sales recorded for this customer.
                            </p>

                        </div>

                    </div>


                    <div class="wholesale-history-table-wrapper">

                        <table class="wholesale-history-table">

                            <thead>

                                <tr>
                                    <th>Invoice</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Remaining Due</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>

                            </thead>


                            <tbody>

                                <?php if (count($sales) > 0): ?>

                                    <?php foreach ($sales as $sale): ?>

                                        <?php
                                        $method =
                                            $sale['payment_method'];

                                        if (
                                            $method ===
                                            'Mobile Banking'
                                        ) {
                                            $method =
                                                'bKash / Mobile Banking';
                                        }
                                        ?>

                                        <tr>

                                            <td>
                                                <strong>
                                                    <?php echo wholesaleHistoryEscape(
                                                        $sale['invoice_no']
                                                    ); ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?php echo date(
                                                    'd M Y',
                                                    strtotime(
                                                        $sale['sale_date']
                                                    )
                                                ); ?>
                                            </td>

                                            <td>
                                                ৳<?php echo number_format(
                                                    (float) $sale['total_amount'],
                                                    2
                                                ); ?>
                                            </td>

                                            <td>
                                                ৳<?php echo number_format(
                                                    (float) $sale['paid_amount'],
                                                    2
                                                ); ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    ৳<?php echo number_format(
                                                        (float) $sale['due_amount'],
                                                        2
                                                    ); ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?php echo wholesaleHistoryEscape(
                                                    $method
                                                ); ?>
                                            </td>

                                            <td>

                                                <span
                                                    class="wholesale-status-badge status-<?php echo strtolower(
                                                        wholesaleHistoryEscape(
                                                            $sale['display_status']
                                                        )
                                                    ); ?>"
                                                >
                                                    <?php echo wholesaleHistoryEscape(
                                                        $sale['display_status']
                                                    ); ?>
                                                </span>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>

                                        <td
                                            colspan="7"
                                            class="wholesale-history-empty"
                                        >
                                            No wholesale sales have been recorded for this customer.
                                        </td>

                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </section>


                <!-- =================================================
                     PAYMENT HISTORY
                     ================================================= -->

                <section class="wholesale-history-section">

                    <div class="wholesale-history-section-heading">

                        <div>

                            <h2>
                                Payment History
                            </h2>

                            <p>
                                Payment records currently stored for this customer.
                            </p>

                        </div>


                        <div class="wholesale-history-section-total">

                            <span>
                                Due Collected
                            </span>

                            <strong>
                                ৳<?php echo number_format(
                                    $totalDueCollections,
                                    2
                                ); ?>
                            </strong>

                        </div>

                    </div>


                    <div class="wholesale-history-table-wrapper">

                        <table class="wholesale-history-table payment-table">

                            <thead>

                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Invoice</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Type</th>
                                    <th>Notes</th>
                                </tr>

                            </thead>


                            <tbody>

                                <?php if (count($payments) > 0): ?>

                                    <?php foreach ($payments as $payment): ?>

                                        <?php
                                        $paymentMethod =
                                            $payment['payment_method'];

                                        if (
                                            $paymentMethod ===
                                            'Mobile Banking'
                                        ) {
                                            $paymentMethod =
                                                'bKash / Mobile Banking';
                                        }
                                        ?>

                                        <tr>

                                            <td>
                                                <?php echo date(
                                                    'd M Y',
                                                    strtotime(
                                                        $payment['payment_date']
                                                    )
                                                ); ?>
                                            </td>

                                            <td>

                                                <?php if (!empty($payment['collection_ref'])): ?>

                                                    <?php echo wholesaleHistoryEscape(
                                                        $payment['collection_ref']
                                                    ); ?>

                                                <?php else: ?>

                                                    —

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <?php if (!empty($payment['invoice_no'])): ?>

                                                    <?php echo wholesaleHistoryEscape(
                                                        $payment['invoice_no']
                                                    ); ?>

                                                <?php elseif ($payment['payment_type'] === 'Opening Due'): ?>

                                                    Opening Balance

                                                <?php else: ?>

                                                    —

                                                <?php endif; ?>

                                            </td>

                                            <td>
                                                <strong>
                                                    ৳<?php echo number_format(
                                                        (float) $payment['amount'],
                                                        2
                                                    ); ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?php echo wholesaleHistoryEscape(
                                                    $paymentMethod
                                                ); ?>
                                            </td>

                                            <td>
                                                <?php echo wholesaleHistoryEscape(
                                                    $payment['payment_type']
                                                ); ?>
                                            </td>

                                            <td>

                                                <?php if (!empty($payment['notes'])): ?>

                                                    <?php echo wholesaleHistoryEscape(
                                                        $payment['notes']
                                                    ); ?>

                                                <?php else: ?>

                                                    —

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>

                                        <td
                                            colspan="7"
                                            class="wholesale-history-empty"
                                        >
                                            No payment records have been stored for this customer yet.
                                        </td>

                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </section>


                <?php
                require __DIR__ . "/payment_modal.php";
                ?>


                <!-- =================================================
                     INFORMATION NOTE
                     ================================================= -->

                <div class="wholesale-history-note">

                    <strong>
                        Overdue rule:
                    </strong>

                    An invoice is marked overdue when it still has a balance
                    and its sale date is more than
                    <?php echo $overdueDays; ?> days old.

                </div>


            </div>

        </main>


        <script>
        window.GrocerEaseWholesaleData = <?php
        echo json_encode(
            [
                'endpoint' => '/grocery-shop/modules/wholesale/collect_payment.php',
                'csrfToken' => $paymentCsrfToken,
                'customers' => array_map(
                    static function ($collectCustomer) {
                        return [
                            'customer_id' => (int) $collectCustomer['customer_id'],
                            'customer_name' => (string) $collectCustomer['customer_name'],
                            'phone' => (string) ($collectCustomer['phone'] ?? ''),
                            'total_due' => number_format(
                                (float) $collectCustomer['total_due'],
                                2,
                                '.',
                                ''
                            )
                        ];
                    },
                    $collectCustomers
                )
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );
        ?>;
        </script>

        <script src="../../assets/js/wholesale.js?v=20260820-1"></script>
        <script src="../../assets/js/sidebar.js"></script>

    </div>

</div>


<?php

require_once "../../includes/footer.php";

?>
