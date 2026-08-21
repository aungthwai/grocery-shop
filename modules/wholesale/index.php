<?php

/* GrocerEase Wholesale Fixed Index - Collect Payment Embedded JS */

session_start();

/*
|--------------------------------------------------------------------------
| WHOLESALE DUE MANAGEMENT
|--------------------------------------------------------------------------
| Final Module
|
| Current functionality:
| - Login protection
| - Database connection
| - KPI summary
| - Wholesale customer due list
| - Search
| - Due-status filter
| - Last payment information
| - Recent payment information
|
| History and payment collection are enabled.
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
$pageTitle = "Wholesale Due Management";


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

function wholesaleEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim((string) $_GET['search'])
    : '';

$statusFilter = isset($_GET['status'])
    ? trim((string) $_GET['status'])
    : '';

$validStatuses = [
    '',
    'Pending',
    'Partial',
    'Overdue'
];

if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}


/*
|--------------------------------------------------------------------------
| OVERDUE RULE
|--------------------------------------------------------------------------
| The current database has no due_date column.
|
| For this project:
| A wholesale customer is considered overdue when they have at least one
| sale with remaining due that is older than 30 days.
|--------------------------------------------------------------------------
*/

$overdueDays = 30;


/*
|--------------------------------------------------------------------------
| KPI SUMMARY
|--------------------------------------------------------------------------
*/

$summary = [
    'outstanding_due' => 0.00,
    'customers_with_due' => 0,
    'collected_this_month' => 0.00,
    'overdue_customers' => 0
];


/*
|--------------------------------------------------------------------------
| 1. OUTSTANDING DUE + CUSTOMERS WITH DUE
|--------------------------------------------------------------------------
*/

$summarySql = "
    SELECT
        COALESCE(SUM(total_due), 0) AS outstanding_due,
        COUNT(*) AS customers_with_due
    FROM customers
    WHERE customer_type = 'Wholesale'
      AND total_due > 0
";

$summaryResult = mysqli_query($conn, $summarySql);

if ($summaryResult) {
    $row = mysqli_fetch_assoc($summaryResult);

    $summary['outstanding_due'] =
        (float) $row['outstanding_due'];

    $summary['customers_with_due'] =
        (int) $row['customers_with_due'];
}


/*
|--------------------------------------------------------------------------
| 2. COLLECTED THIS MONTH
|--------------------------------------------------------------------------
| Uses actual payment records belonging to wholesale customers.
|--------------------------------------------------------------------------
*/

$collectedSql = "
    SELECT
        COALESCE(SUM(p.amount), 0) AS collected_total
    FROM payments p
    INNER JOIN customers c
        ON p.customer_id = c.customer_id
    WHERE c.customer_type = 'Wholesale'
      AND p.payment_type IN ('Due Collection', 'Opening Due')
      AND p.payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND p.payment_date < DATE_ADD(
            DATE_FORMAT(CURDATE(), '%Y-%m-01'),
            INTERVAL 1 MONTH
      )
";

$collectedResult = mysqli_query($conn, $collectedSql);

if ($collectedResult) {
    $row = mysqli_fetch_assoc($collectedResult);

    $summary['collected_this_month'] =
        (float) $row['collected_total'];
}


/*
|--------------------------------------------------------------------------
| 3. OVERDUE CUSTOMERS
|--------------------------------------------------------------------------
*/

$overdueSql = "
    SELECT
        COUNT(DISTINCT s.customer_id) AS overdue_total
    FROM sales s
    INNER JOIN customers c
        ON s.customer_id = c.customer_id
    WHERE c.customer_type = 'Wholesale'
      AND c.total_due > 0
      AND s.due_amount > 0
      AND s.sale_date < DATE_SUB(CURDATE(), INTERVAL {$overdueDays} DAY)
";

$overdueResult = mysqli_query($conn, $overdueSql);

if ($overdueResult) {
    $row = mysqli_fetch_assoc($overdueResult);

    $summary['overdue_customers'] =
        (int) $row['overdue_total'];
}


/*
|--------------------------------------------------------------------------
| WHOLESALE DUE CUSTOMER LIST
|--------------------------------------------------------------------------
| Important:
| - Only Wholesale customers belong on this page.
| - Customers with zero due are not shown in the due table.
| - Inactive customers are NOT hidden if they still owe money.
|   Their debt still needs to be visible and collectible.
|
| Status:
| - Overdue: remaining sale due older than 30 days
| - Partial: customer has a payment record but still has due
| - Pending: due exists but no payment has been recorded
|--------------------------------------------------------------------------
*/

$customers = [];

$sql = "
    SELECT
        c.customer_id,
        c.customer_name,
        c.phone,
        c.email,
        c.address,
        c.account_status,
        c.opening_due,
        c.total_due,
        c.created_at,

        lp.last_payment,
        lp.due_payment_count,

        ds.oldest_due_sale,
        ds.due_invoice_count,
        ds.current_sale_due

    FROM customers c

    LEFT JOIN (
        SELECT
            customer_id,
            MAX(payment_date) AS last_payment,
            COUNT(*) AS due_payment_count
        FROM payments
        WHERE payment_type IN ('Due Collection', 'Opening Due')
        GROUP BY customer_id
    ) lp
        ON c.customer_id = lp.customer_id

    LEFT JOIN (
        SELECT
            customer_id,
            MIN(CASE WHEN due_amount > 0 THEN sale_date END) AS oldest_due_sale,
            SUM(CASE WHEN due_amount > 0 THEN 1 ELSE 0 END) AS due_invoice_count,
            COALESCE(SUM(CASE WHEN due_amount > 0 THEN due_amount ELSE 0 END), 0) AS current_sale_due
        FROM sales
        WHERE customer_id IS NOT NULL
        GROUP BY customer_id
    ) ds
        ON c.customer_id = ds.customer_id

    WHERE c.customer_type = 'Wholesale'
      AND c.total_due > 0
";


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $searchEscaped =
        mysqli_real_escape_string($conn, $search);

    $sql .= "
        AND (
            c.customer_name LIKE '%{$searchEscaped}%'
            OR c.phone LIKE '%{$searchEscaped}%'
            OR c.email LIKE '%{$searchEscaped}%'
        )
    ";
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($statusFilter === 'Overdue') {

    $sql .= "
        AND ds.oldest_due_sale IS NOT NULL
        AND ds.oldest_due_sale < DATE_SUB(
            CURDATE(),
            INTERVAL {$overdueDays} DAY
        )
    ";

} elseif ($statusFilter === 'Partial') {

    $sql .= "
        AND (
            ds.oldest_due_sale IS NULL
            OR ds.oldest_due_sale >= DATE_SUB(
                CURDATE(),
                INTERVAL {$overdueDays} DAY
            )
        )
        AND COALESCE(lp.due_payment_count, 0) > 0
    ";

} elseif ($statusFilter === 'Pending') {

    $sql .= "
        AND (
            ds.oldest_due_sale IS NULL
            OR ds.oldest_due_sale >= DATE_SUB(
                CURDATE(),
                INTERVAL {$overdueDays} DAY
            )
        )
        AND COALESCE(lp.due_payment_count, 0) = 0
    ";
}


$sql .= "
    ORDER BY
        c.total_due DESC,
        c.customer_name ASC
";

$customerResult = mysqli_query($conn, $sql);

if ($customerResult) {

    while ($row = mysqli_fetch_assoc($customerResult)) {

        $isOverdue = false;

        if (!empty($row['oldest_due_sale'])) {

            $oldestDueTimestamp =
                strtotime($row['oldest_due_sale']);

            $overdueBoundary =
                strtotime("-{$overdueDays} days");

            $isOverdue =
                $oldestDueTimestamp !== false &&
                $oldestDueTimestamp < $overdueBoundary;
        }


        if ($isOverdue) {

            $row['due_status'] = 'Overdue';

        } elseif ((int) ($row['due_payment_count'] ?? 0) > 0) {

            $row['due_status'] = 'Partial';

        } else {

            $row['due_status'] = 'Pending';

        }


        $customers[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| CUSTOMERS AVAILABLE FOR PAYMENT COLLECTION
|--------------------------------------------------------------------------
| This list is intentionally independent from the search/filter above so
| the top "+ Collect Payment" button can always select any customer with due.
|--------------------------------------------------------------------------
*/

$collectCustomers = [];

$collectCustomerSql = "
    SELECT
        customer_id,
        customer_name,
        phone,
        total_due
    FROM customers
    WHERE customer_type = 'Wholesale'
      AND total_due > 0
    ORDER BY customer_name ASC
";

$collectCustomerResult =
    mysqli_query($conn, $collectCustomerSql);

if ($collectCustomerResult) {
    while ($row = mysqli_fetch_assoc($collectCustomerResult)) {
        $collectCustomers[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| RECENT PAYMENT
|--------------------------------------------------------------------------
*/

$recentPayment = null;

$recentPaymentSql = "
    SELECT
        p.collection_ref,
        MAX(p.payment_id) AS latest_payment_id,
        MAX(p.payment_date) AS payment_date,
        SUM(p.amount) AS amount,
        MAX(p.payment_method) AS payment_method,
        MAX(p.notes) AS notes,
        c.customer_name
    FROM payments p
    INNER JOIN customers c
        ON p.customer_id = c.customer_id
    WHERE c.customer_type = 'Wholesale'
      AND p.payment_type IN ('Due Collection', 'Opening Due')
      AND p.collection_ref IS NOT NULL
    GROUP BY
        p.collection_ref,
        p.customer_id,
        c.customer_name
    ORDER BY
        latest_payment_id DESC
    LIMIT 1
";

$recentPaymentResult =
    mysqli_query($conn, $recentPaymentSql);

if (
    $recentPaymentResult &&
    mysqli_num_rows($recentPaymentResult) > 0
) {
    $recentPayment =
        mysqli_fetch_assoc($recentPaymentResult);
}


/*
|--------------------------------------------------------------------------
| TEMPORARY SYSTEM CHECK
|--------------------------------------------------------------------------
| Open:
|   /modules/wholesale/index.php?system_check=1
|
| This is read-only. It does not modify any database data.
|--------------------------------------------------------------------------
*/

$showSystemCheck =
    isset($_GET['system_check']) &&
    $_GET['system_check'] === '1';

$systemChecks = [];
$systemCustomerRows = [];

if ($showSystemCheck) {

    /*
    |--------------------------------------------------------------------------
    | CHECK REQUIRED PAYMENT COLUMNS
    |--------------------------------------------------------------------------
    */

    $schemaSql = "
        SELECT
            COLUMN_NAME,
            IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'payments'
          AND COLUMN_NAME IN (
              'sale_id',
              'payment_type',
              'collection_ref'
          )
    ";

    $schemaResult =
        mysqli_query($conn, $schemaSql);

    $schemaColumns = [];

    if ($schemaResult) {

        while ($row = mysqli_fetch_assoc($schemaResult)) {
            $schemaColumns[$row['COLUMN_NAME']] = $row;
        }
    }

    $schemaPass =
        isset($schemaColumns['sale_id']) &&
        isset($schemaColumns['payment_type']) &&
        isset($schemaColumns['collection_ref']) &&
        $schemaColumns['sale_id']['IS_NULLABLE'] === 'YES';

    $systemChecks[] = [
        'name' => 'Part 4 payment schema',
        'pass' => $schemaPass,
        'detail' => $schemaPass
            ? 'sale_id is nullable and payment_type / collection_ref exist.'
            : 'Required Part 4 payment columns are missing or sale_id is not nullable.'
    ];


    /*
    |--------------------------------------------------------------------------
    | CHECK NEGATIVE CUSTOMER DUES
    |--------------------------------------------------------------------------
    */

    $negativeCustomerDue = 0;

    $negativeCustomerSql = "
        SELECT COUNT(*) AS total
        FROM customers
        WHERE customer_type = 'Wholesale'
          AND total_due < 0
    ";

    $negativeCustomerResult =
        mysqli_query($conn, $negativeCustomerSql);

    if ($negativeCustomerResult) {
        $row = mysqli_fetch_assoc($negativeCustomerResult);
        $negativeCustomerDue = (int) $row['total'];
    }

    $systemChecks[] = [
        'name' => 'No negative customer balances',
        'pass' => $negativeCustomerDue === 0,
        'detail' => $negativeCustomerDue === 0
            ? 'All wholesale customer balances are zero or positive.'
            : $negativeCustomerDue . ' wholesale customer balance(s) are negative.'
    ];


    /*
    |--------------------------------------------------------------------------
    | CHECK SALE MATH
    |--------------------------------------------------------------------------
    */

    $invalidSaleMath = 0;

    $saleMathSql = "
        SELECT COUNT(*) AS total
        FROM sales
        WHERE paid_amount < 0
           OR due_amount < 0
           OR ABS(
                total_amount -
                (paid_amount + due_amount)
              ) > 0.01
    ";

    $saleMathResult =
        mysqli_query($conn, $saleMathSql);

    if ($saleMathResult) {
        $row = mysqli_fetch_assoc($saleMathResult);
        $invalidSaleMath = (int) $row['total'];
    }

    $systemChecks[] = [
        'name' => 'Invoice totals are consistent',
        'pass' => $invalidSaleMath === 0,
        'detail' => $invalidSaleMath === 0
            ? 'Every sale satisfies total = paid + due.'
            : $invalidSaleMath . ' sale(s) have inconsistent total/paid/due amounts.'
    ];


    /*
    |--------------------------------------------------------------------------
    | CHECK CUSTOMER DUE AGAINST INVOICES + OPENING BALANCE
    |--------------------------------------------------------------------------
    */

    $consistencySql = "
        SELECT
            c.customer_id,
            c.customer_name,
            c.opening_due,
            c.total_due,

            COALESCE(inv.invoice_due, 0) AS invoice_due,

            COALESCE(op.opening_collected, 0) AS opening_collected,

            GREATEST(
                c.opening_due -
                COALESCE(op.opening_collected, 0),
                0
            ) AS remaining_opening_due,

            (
                COALESCE(inv.invoice_due, 0) +
                GREATEST(
                    c.opening_due -
                    COALESCE(op.opening_collected, 0),
                    0
                )
            ) AS expected_due

        FROM customers c

        LEFT JOIN (
            SELECT
                customer_id,
                SUM(due_amount) AS invoice_due
            FROM sales
            WHERE customer_id IS NOT NULL
            GROUP BY customer_id
        ) inv
            ON c.customer_id = inv.customer_id

        LEFT JOIN (
            SELECT
                customer_id,
                SUM(amount) AS opening_collected
            FROM payments
            WHERE payment_type = 'Opening Due'
            GROUP BY customer_id
        ) op
            ON c.customer_id = op.customer_id

        WHERE c.customer_type = 'Wholesale'

        ORDER BY
            c.customer_name ASC
    ";

    $consistencyResult =
        mysqli_query($conn, $consistencySql);

    $mismatchCount = 0;

    if ($consistencyResult) {

        while ($row = mysqli_fetch_assoc($consistencyResult)) {

            $difference =
                round(
                    (float) $row['total_due'] -
                    (float) $row['expected_due'],
                    2
                );

            $row['difference'] = $difference;

            if (abs($difference) > 0.01) {
                $mismatchCount++;
            }

            $systemCustomerRows[] = $row;
        }
    }

    $systemChecks[] = [
        'name' => 'Customer due matches invoice/opening due',
        'pass' => $mismatchCount === 0,
        'detail' => $mismatchCount === 0
            ? 'All wholesale customer balances reconcile correctly.'
            : $mismatchCount . ' customer balance(s) need attention.'
    ];


    /*
    |--------------------------------------------------------------------------
    | CHECK KPI VALUES
    |--------------------------------------------------------------------------
    */

    $systemChecks[] = [
        'name' => 'Wholesale KPI calculation',
        'pass' => true,
        'detail' =>
            'Outstanding Due: ৳' .
            number_format(
                $summary['outstanding_due'],
                2
            ) .
            ' | Overdue Customers: ' .
            number_format(
                $summary['overdue_customers']
            ) .
            ' | 30+ day rule.'
    ];


    /*
    |--------------------------------------------------------------------------
    | OVERALL RESULT
    |--------------------------------------------------------------------------
    */

    $systemCheckPassed = true;

    foreach ($systemChecks as $check) {

        if (!$check['pass']) {
            $systemCheckPassed = false;
            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| SHARED HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>

<!-- =====================================================
     SHARED CSS
     ===================================================== -->

<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<link rel="stylesheet" href="../../assets/css/wholesale.css">


<!-- =====================================================
     TEMPORARY SYSTEM CHECK STYLES
     ===================================================== -->

<style>

.wholesale-system-check-link {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: #ffffff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.wholesale-system-check-link:hover {
    border-color: #2563eb;
    color: #2563eb;
}

.wholesale-system-check-panel {
    margin-top: 22px;
    border: 1px solid #cbd5e1;
    border-radius: 14px;
    background: #ffffff;
    overflow: hidden;
}

.wholesale-system-check-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 18px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}

.wholesale-system-check-header h2 {
    margin: 0;
    font-size: 16px;
    color: #0f172a;
}

.wholesale-system-check-header p {
    margin: 4px 0 0;
    font-size: 11px;
    color: #64748b;
}

.wholesale-system-overall {
    display: inline-flex;
    align-items: center;
    min-height: 29px;
    padding: 0 11px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
}

.wholesale-system-overall.pass {
    background: #dcfce7;
    color: #15803d;
}

.wholesale-system-overall.fail {
    background: #fee2e2;
    color: #b91c1c;
}

.wholesale-system-check-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 18px 20px;
}

.wholesale-system-check-item {
    display: flex;
    gap: 11px;
    padding: 13px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.wholesale-system-check-icon {
    width: 25px;
    height: 25px;
    flex: 0 0 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 12px;
    font-weight: 900;
}

.wholesale-system-check-icon.pass {
    background: #dcfce7;
    color: #15803d;
}

.wholesale-system-check-icon.fail {
    background: #fee2e2;
    color: #dc2626;
}

.wholesale-system-check-item strong {
    display: block;
    font-size: 12px;
    color: #0f172a;
}

.wholesale-system-check-item p {
    margin: 4px 0 0;
    font-size: 11px;
    line-height: 1.45;
    color: #64748b;
}

.wholesale-system-check-table-wrap {
    padding: 0 20px 20px;
    overflow-x: auto;
}

.wholesale-system-check-table-wrap h3 {
    margin: 0 0 10px;
    font-size: 13px;
    color: #0f172a;
}

.wholesale-system-check-table {
    width: 100%;
    min-width: 760px;
    border-collapse: collapse;
}

.wholesale-system-check-table th,
.wholesale-system-check-table td {
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    font-size: 11px;
    text-align: left;
}

.wholesale-system-check-table th {
    background: #f8fafc;
    color: #64748b;
}

.wholesale-system-difference {
    display: inline-flex;
    min-width: 42px;
    justify-content: center;
    padding: 4px 7px;
    border-radius: 6px;
    font-weight: 800;
}

.wholesale-system-difference.pass {
    background: #dcfce7;
    color: #15803d;
}

.wholesale-system-difference.fail {
    background: #fee2e2;
    color: #dc2626;
}

.wholesale-system-test-guide {
    margin: 0 20px 20px;
    padding: 12px 14px;
    border: 1px solid #bfdbfe;
    border-radius: 9px;
    background: #eff6ff;
    color: #475569;
    font-size: 11px;
    line-height: 1.55;
}

.wholesale-system-test-guide strong {
    color: #1d4ed8;
}

@media (max-width: 800px) {
    .wholesale-system-check-list {
        grid-template-columns: 1fr;
    }

    .wholesale-system-check-header {
        align-items: flex-start;
        flex-direction: column;
    }
}

</style>


<!-- =====================================================
     APPLICATION LAYOUT
     ===================================================== -->

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


        <!-- PAGE CONTENT -->
        <main class="dashboard-main-content">

            <div class="wholesale-page">


                <!-- =================================================
                     PAGE HEADING
                     ================================================= -->

                <div class="wholesale-heading">

                    <div>

                        <h1>
                            Wholesale Due Management
                        </h1>

                        <p>
                            Track outstanding wholesale payments and collect dues.
                        </p>

                    </div>

                    <a
                        href="<?php echo $showSystemCheck ? 'index.php' : 'index.php?system_check=1'; ?>"
                        class="wholesale-system-check-link"
                    >
                        <?php echo $showSystemCheck ? 'Hide System Check' : 'Run System Check'; ?>
                    </a>

                </div>


                <!-- =================================================
                     KPI CARDS
                     ================================================= -->

                <div class="wholesale-kpi-grid">


                    <!-- OUTSTANDING DUE -->

                    <div class="wholesale-kpi-card due-card">

                        <span>
                            Outstanding Due
                        </span>

                        <strong>
                            ৳<?php echo number_format(
                                $summary['outstanding_due'],
                                2
                            ); ?>
                        </strong>

                    </div>


                    <!-- CUSTOMERS WITH DUE -->

                    <div class="wholesale-kpi-card customers-card">

                        <span>
                            Customers With Due
                        </span>

                        <strong>
                            <?php echo number_format(
                                $summary['customers_with_due']
                            ); ?>
                        </strong>

                    </div>


                    <!-- COLLECTED THIS MONTH -->

                    <div class="wholesale-kpi-card collected-card">

                        <span>
                            Collected This Month
                        </span>

                        <strong>
                            ৳<?php echo number_format(
                                $summary['collected_this_month'],
                                2
                            ); ?>
                        </strong>

                    </div>


                    <!-- OVERDUE -->

                    <div class="wholesale-kpi-card overdue-card">

                        <span>
                            Overdue (<?php echo $overdueDays; ?>+ Days)
                        </span>

                        <strong>
                            <?php echo number_format(
                                $summary['overdue_customers']
                            ); ?>
                        </strong>

                    </div>


                </div>


                <!-- =================================================
                     SEARCH / FILTER
                     ================================================= -->

                <form
                    method="GET"
                    action=""
                    class="wholesale-toolbar"
                >

                    <div>

                        <label
                            for="wholesaleSearch"
                            class="sr-only"
                        >
                            Search customer
                        </label>

                        <div class="wholesale-search-field">
                            <span class="wholesale-search-icon" aria-hidden="true">
                                ⌕
                            </span>

                            <input
                                type="search"
                                id="wholesaleSearch"
                                name="search"
                                placeholder="Search customer..."
                                value="<?php echo wholesaleEscape($search); ?>"
                            >
                        </div>

                    </div>


                    <div>

                        <label
                            for="wholesaleStatus"
                            class="sr-only"
                        >
                            Due status
                        </label>

                        <select
                            id="wholesaleStatus"
                            name="status"
                            onchange="this.form.submit()"
                        >

                            <option
                                value=""
                                <?php echo $statusFilter === '' ? 'selected' : ''; ?>
                            >
                                All Status
                            </option>

                            <option
                                value="Pending"
                                <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>
                            >
                                Pending
                            </option>

                            <option
                                value="Partial"
                                <?php echo $statusFilter === 'Partial' ? 'selected' : ''; ?>
                            >
                                Partial
                            </option>

                            <option
                                value="Overdue"
                                <?php echo $statusFilter === 'Overdue' ? 'selected' : ''; ?>
                            >
                                Overdue
                            </option>

                        </select>

                    </div>


                    <button
                        type="submit"
                        class="wholesale-search-submit"
                    >
                        Search
                    </button>


                    <?php if ($search !== '' || $statusFilter !== ''): ?>

                        <a
                            href="index.php"
                            class="wholesale-clear-filter"
                        >
                            Clear
                        </a>

                    <?php endif; ?>


                    <button
                        type="button"
                        class="wholesale-collect-main js-open-payment-modal"
                        <?php echo count($collectCustomers) === 0 ? 'disabled' : ''; ?>
                    >
                        + Collect Payment
                    </button>

                </form>


                <!-- =================================================
                     DUE TABLE
                     ================================================= -->

                <section class="wholesale-table-section">

                    <div class="wholesale-table-wrapper">

                        <table class="wholesale-table">

                            <thead>

                                <tr>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Phone
                                    </th>

                                    <th>
                                        Total Due
                                    </th>

                                    <th>
                                        Last Payment
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php if (count($customers) > 0): ?>

                                    <?php foreach ($customers as $customer): ?>

                                        <tr>


                                            <!-- CUSTOMER -->

                                            <td>

                                                <strong>
                                                    <?php echo wholesaleEscape(
                                                        $customer['customer_name']
                                                    ); ?>
                                                </strong>

                                                <?php if (
                                                    $customer['account_status'] === 'Inactive'
                                                ): ?>

                                                    <small>
                                                        Inactive Account
                                                    </small>

                                                <?php endif; ?>

                                            </td>


                                            <!-- PHONE -->

                                            <td>

                                                <?php if (!empty($customer['phone'])): ?>

                                                    <?php echo wholesaleEscape(
                                                        $customer['phone']
                                                    ); ?>

                                                <?php else: ?>

                                                    —

                                                <?php endif; ?>

                                            </td>


                                            <!-- TOTAL DUE -->

                                            <td>

                                                <strong>
                                                    ৳<?php echo number_format(
                                                        (float) $customer['total_due'],
                                                        2
                                                    ); ?>
                                                </strong>

                                            </td>


                                            <!-- LAST PAYMENT -->

                                            <td>

                                                <?php if (!empty($customer['last_payment'])): ?>

                                                    <?php echo date(
                                                        'd M Y',
                                                        strtotime(
                                                            $customer['last_payment']
                                                        )
                                                    ); ?>

                                                <?php else: ?>

                                                    Never

                                                <?php endif; ?>

                                            </td>


                                            <!-- STATUS -->

                                            <td>

                                                <span
                                                    class="wholesale-status-badge status-<?php echo strtolower(
                                                        wholesaleEscape($customer['due_status'])
                                                    ); ?>"
                                                >
                                                    <?php echo wholesaleEscape(
                                                        $customer['due_status']
                                                    ); ?>
                                                </span>

                                            </td>


                                            <!-- ACTION -->

                                            <td>

                                                <!--
                                                    Part 3:
                                                    View customer due history.
                                                -->
                                                <a
                                                    href="history.php?customer_id=<?php echo (int) $customer['customer_id']; ?>"
                                                    class="wholesale-action-button history"
                                                >
                                                    History
                                                </a>


                                                <button
                                                    type="button"
                                                    class="wholesale-action-button collect js-open-payment-modal"
                                                    data-customer-id="<?php echo (int) $customer['customer_id']; ?>"
                                                >
                                                    Collect
                                                </button>

                                            </td>


                                        </tr>

                                    <?php endforeach; ?>


                                <?php else: ?>


                                    <tr>

                                        <td colspan="6">

                                            <?php if (
                                                $search !== '' ||
                                                $statusFilter !== ''
                                            ): ?>

                                                No wholesale due customers match your search/filter.

                                            <?php else: ?>

                                                No wholesale customers currently have outstanding dues.

                                            <?php endif; ?>

                                        </td>

                                    </tr>


                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </section>


                <!-- =================================================
                     RECENT PAYMENT
                     ================================================= -->

                <section class="wholesale-recent-payment">

                    <h2>
                        Recent Payment
                    </h2>


                    <?php if ($recentPayment): ?>

                        <p>

                            <strong>
                                <?php echo wholesaleEscape(
                                    $recentPayment['customer_name']
                                ); ?>
                            </strong>

                            paid

                            <strong>
                                ৳<?php echo number_format(
                                    (float) $recentPayment['amount'],
                                    2
                                ); ?>
                            </strong>

                            on

                            <?php echo date(
                                'd F Y',
                                strtotime(
                                    $recentPayment['payment_date']
                                )
                            ); ?>

                            via

                            <?php
                            $method =
                                $recentPayment['payment_method'];

                            if ($method === 'Mobile Banking') {
                                $method = 'bKash / Mobile Banking';
                            }

                            echo wholesaleEscape($method);
                            ?>.

                        </p>


                    <?php else: ?>


                        <p>
                            No wholesale payments have been recorded yet.
                        </p>


                    <?php endif; ?>

                </section>


                <?php if ($showSystemCheck): ?>

                    <!-- ================================================
                         TEMPORARY SYSTEM CHECK PANEL
                         ================================================ -->

                    <section class="wholesale-system-check-panel">

                        <div class="wholesale-system-check-header">

                            <div>

                                <h2>
                                    Wholesale System Check
                                </h2>

                                <p>
                                    Read-only verification. No database values are changed.
                                </p>

                            </div>

                            <span
                                class="wholesale-system-overall <?php echo $systemCheckPassed ? 'pass' : 'fail'; ?>"
                            >
                                <?php echo $systemCheckPassed ? 'ALL CHECKS PASSED' : 'CHECK REQUIRED'; ?>
                            </span>

                        </div>


                        <div class="wholesale-system-check-list">

                            <?php foreach ($systemChecks as $check): ?>

                                <div class="wholesale-system-check-item">

                                    <span
                                        class="wholesale-system-check-icon <?php echo $check['pass'] ? 'pass' : 'fail'; ?>"
                                    >
                                        <?php echo $check['pass'] ? '✓' : '✕'; ?>
                                    </span>

                                    <div>

                                        <strong>
                                            <?php echo wholesaleEscape(
                                                $check['name']
                                            ); ?>
                                        </strong>

                                        <p>
                                            <?php echo wholesaleEscape(
                                                $check['detail']
                                            ); ?>
                                        </p>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>


                        <div class="wholesale-system-check-table-wrap">

                            <h3>
                                Customer Balance Reconciliation
                            </h3>

                            <table class="wholesale-system-check-table">

                                <thead>

                                    <tr>
                                        <th>Customer</th>
                                        <th>Current Due</th>
                                        <th>Invoice Due</th>
                                        <th>Opening Due Left</th>
                                        <th>Expected Due</th>
                                        <th>Difference</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php if (count($systemCustomerRows) > 0): ?>

                                        <?php foreach ($systemCustomerRows as $checkCustomer): ?>

                                            <?php
                                            $rowPass =
                                                abs(
                                                    (float) $checkCustomer['difference']
                                                ) <= 0.01;
                                            ?>

                                            <tr>

                                                <td>
                                                    <strong>
                                                        <?php echo wholesaleEscape(
                                                            $checkCustomer['customer_name']
                                                        ); ?>
                                                    </strong>
                                                </td>

                                                <td>
                                                    ৳<?php echo number_format(
                                                        (float) $checkCustomer['total_due'],
                                                        2
                                                    ); ?>
                                                </td>

                                                <td>
                                                    ৳<?php echo number_format(
                                                        (float) $checkCustomer['invoice_due'],
                                                        2
                                                    ); ?>
                                                </td>

                                                <td>
                                                    ৳<?php echo number_format(
                                                        (float) $checkCustomer['remaining_opening_due'],
                                                        2
                                                    ); ?>
                                                </td>

                                                <td>
                                                    ৳<?php echo number_format(
                                                        (float) $checkCustomer['expected_due'],
                                                        2
                                                    ); ?>
                                                </td>

                                                <td>

                                                    <span
                                                        class="wholesale-system-difference <?php echo $rowPass ? 'pass' : 'fail'; ?>"
                                                    >
                                                        <?php
                                                        echo $rowPass
                                                            ? 'OK'
                                                            : (
                                                                (
                                                                    (float) $checkCustomer['difference'] > 0
                                                                        ? '+'
                                                                        : ''
                                                                ) .
                                                                '৳' .
                                                                number_format(
                                                                    (float) $checkCustomer['difference'],
                                                                    2
                                                                )
                                                            );
                                                        ?>
                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php else: ?>

                                        <tr>
                                            <td colspan="6">
                                                No wholesale customers found.
                                            </td>
                                        </tr>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>


                        <div class="wholesale-system-test-guide">

                            <strong>
                                Live payment test:
                            </strong>

                            Open Collect Payment and try an amount larger than the
                            customer's due first. It must be rejected. Then record one
                            small valid payment and run this System Check again. If the
                            affected customer still shows <b>OK</b>, the customer,
                            invoice and opening-due balances remained synchronized.

                        </div>

                    </section>

                <?php endif; ?>


                <?php
                /*
                |--------------------------------------------------------------------------
                | PAYMENT MODAL
                |--------------------------------------------------------------------------
                */
                require __DIR__ . "/payment_modal.php";
                ?>


            </div>

        </main>


        <script>
        window.GrocerEaseWholesaleData = <?php
        echo json_encode(
            [
                'endpoint' => '/grocery-shop/modules/wholesale/collect_payment.php',
                'csrfToken' => $paymentCsrfToken,
                'customers' => array_map(
                    static function ($customer) {
                        return [
                            'customer_id' => (int) $customer['customer_id'],
                            'customer_name' => (string) $customer['customer_name'],
                            'phone' => (string) ($customer['phone'] ?? ''),
                            'total_due' => number_format(
                                (float) $customer['total_due'],
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

/*
|--------------------------------------------------------------------------
| SHARED FOOTER
|--------------------------------------------------------------------------
*/

require_once "../../includes/footer.php";

?>
