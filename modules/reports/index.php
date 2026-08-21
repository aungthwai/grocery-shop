<?php

require_once "../../includes/auth_check.php";
require_once "../../includes/role_guard.php";

grocerEaseRequireAdmin();

require_once "../../config/database.php";

$basePath = "/grocery-shop";
$page_title = "Reports & Analytics";


/*
|--------------------------------------------------------------------------
| DATE HELPERS
|--------------------------------------------------------------------------
*/

function isValidReportDate(string $date): bool
{
    $parsed = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    return (
        $parsed !== false &&
        $parsed->format('Y-m-d') === $date
    );
}


/*
|--------------------------------------------------------------------------
| REPORT FILTER
|--------------------------------------------------------------------------
*/

$allowedFilters = [
    'today',
    'week',
    'month',
    'year',
    'custom'
];

$filter =
    trim(
        (string) ($_GET['filter'] ?? 'today')
    );

if (
    !in_array(
        $filter,
        $allowedFilters,
        true
    )
) {
    $filter = 'today';
}


/*
|--------------------------------------------------------------------------
| DATE RANGE
|--------------------------------------------------------------------------
*/

$today = date('Y-m-d');

switch ($filter) {

    case 'week':

        /*
        | Today + previous 6 days = 7 calendar days.
        */

        $from =
            date(
                'Y-m-d',
                strtotime('-6 days')
            );

        $to = $today;

        break;


    case 'month':

        $from =
            date('Y-m-01');

        $to = $today;

        break;


    case 'year':

        $from =
            date('Y-01-01');

        $to = $today;

        break;


    case 'custom':

        $requestedFrom =
            trim(
                (string) (
                    $_GET['from']
                    ?? date('Y-m-01')
                )
            );

        $requestedTo =
            trim(
                (string) (
                    $_GET['to']
                    ?? $today
                )
            );


        $from =
            isValidReportDate(
                $requestedFrom
            )
                ? $requestedFrom
                : date('Y-m-01');


        $to =
            isValidReportDate(
                $requestedTo
            )
                ? $requestedTo
                : $today;


        /*
        | Do not allow the range to be reversed.
        */

        if ($from > $to) {

            $temporary = $from;
            $from = $to;
            $to = $temporary;
        }

        break;


    case 'today':
    default:

        $from = $today;
        $to = $today;

        break;
}


/*
|--------------------------------------------------------------------------
| SALES KPI
|--------------------------------------------------------------------------
*/

$salesRevenue = 0.00;
$paidAmount = 0.00;
$outstandingDue = 0.00;
$salesTransactions = 0;

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        COALESCE(
            SUM(total_amount),
            0
        ) AS sales_revenue,

        COALESCE(
            SUM(paid_amount),
            0
        ) AS paid_amount,

        COALESCE(
            SUM(due_amount),
            0
        ) AS outstanding_due,

        COUNT(*) AS transaction_count

    FROM sales

    WHERE
        sale_date BETWEEN ? AND ?
    "
);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        'ss',
        $from,
        $to
    );

    mysqli_stmt_execute(
        $stmt
    );

    $result =
        mysqli_stmt_get_result(
            $stmt
        );

    $row =
        mysqli_fetch_assoc(
            $result
        );

    if ($row) {

        $salesRevenue =
            (float) (
                $row['sales_revenue']
                ?? 0
            );

        $paidAmount =
            (float) (
                $row['paid_amount']
                ?? 0
            );

        $outstandingDue =
            (float) (
                $row['outstanding_due']
                ?? 0
            );

        $salesTransactions =
            (int) (
                $row['transaction_count']
                ?? 0
            );
    }

    mysqli_stmt_close(
        $stmt
    );
}


/*
|--------------------------------------------------------------------------
| PURCHASE KPI
|--------------------------------------------------------------------------
*/

$purchaseCost = 0.00;
$purchaseTransactions = 0;

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        COALESCE(
            SUM(total_amount),
            0
        ) AS purchase_cost,

        COUNT(*) AS transaction_count

    FROM purchases

    WHERE
        purchase_date BETWEEN ? AND ?
    "
);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        'ss',
        $from,
        $to
    );

    mysqli_stmt_execute(
        $stmt
    );

    $result =
        mysqli_stmt_get_result(
            $stmt
        );

    $row =
        mysqli_fetch_assoc(
            $result
        );

    if ($row) {

        $purchaseCost =
            (float) (
                $row['purchase_cost']
                ?? 0
            );

        $purchaseTransactions =
            (int) (
                $row['transaction_count']
                ?? 0
            );
    }

    mysqli_stmt_close(
        $stmt
    );
}


/*
|--------------------------------------------------------------------------
| TOP SELLING PRODUCTS
|--------------------------------------------------------------------------
*/

$topProducts = [];

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        p.product_name,

        COALESCE(
            SUM(si.quantity),
            0
        ) AS units_sold,

        COALESCE(
            SUM(si.subtotal),
            0
        ) AS sales_amount

    FROM sale_items si

    INNER JOIN sales s
        ON s.sale_id = si.sale_id

    INNER JOIN products p
        ON p.product_id = si.product_id

    WHERE
        s.sale_date BETWEEN ? AND ?

    GROUP BY
        p.product_id,
        p.product_name

    ORDER BY
        units_sold DESC,
        sales_amount DESC

    LIMIT 5
    "
);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        'ss',
        $from,
        $to
    );

    mysqli_stmt_execute(
        $stmt
    );

    $result =
        mysqli_stmt_get_result(
            $stmt
        );

    while (
        $row =
            mysqli_fetch_assoc(
                $result
            )
    ) {
        $topProducts[] = $row;
    }

    mysqli_stmt_close(
        $stmt
    );
}


/*
|--------------------------------------------------------------------------
| RECENT SALES IN SELECTED RANGE
|--------------------------------------------------------------------------
*/

$recentSales = [];

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        s.invoice_no,
        s.sale_date,
        s.total_amount,
        s.paid_amount,
        s.due_amount,
        s.payment_method,
        c.customer_name

    FROM sales s

    LEFT JOIN customers c
        ON c.customer_id = s.customer_id

    WHERE
        s.sale_date BETWEEN ? AND ?

    ORDER BY
        s.sale_id DESC

    LIMIT 10
    "
);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        'ss',
        $from,
        $to
    );

    mysqli_stmt_execute(
        $stmt
    );

    $result =
        mysqli_stmt_get_result(
            $stmt
        );

    while (
        $row =
            mysqli_fetch_assoc(
                $result
            )
    ) {
        $recentSales[] = $row;
    }

    mysqli_stmt_close(
        $stmt
    );
}


/*
|--------------------------------------------------------------------------
| LAST SIX MONTHS SALES TREND
|--------------------------------------------------------------------------
*/

$chartData = [];

for ($i = 5; $i >= 0; $i--) {

    $monthReference =
        strtotime(
            '-' . $i . ' months'
        );

    $monthLabel =
        date(
            'M Y',
            $monthReference
        );

    $monthFrom =
        date(
            'Y-m-01',
            $monthReference
        );

    $monthTo =
        date(
            'Y-m-t',
            $monthReference
        );

    $monthSales = 0.00;


    $stmt = mysqli_prepare(
        $conn,
        "
        SELECT
            COALESCE(
                SUM(total_amount),
                0
            ) AS month_sales

        FROM sales

        WHERE
            sale_date BETWEEN ? AND ?
        "
    );


    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            'ss',
            $monthFrom,
            $monthTo
        );

        mysqli_stmt_execute(
            $stmt
        );

        $result =
            mysqli_stmt_get_result(
                $stmt
            );

        $row =
            mysqli_fetch_assoc(
                $result
            );


        $monthSales =
            (float) (
                $row['month_sales']
                ?? 0
            );


        mysqli_stmt_close(
            $stmt
        );
    }


    $chartData[] = [
        'label' => $monthLabel,
        'sales' => $monthSales
    ];
}


/*
|--------------------------------------------------------------------------
| CHART SCALE
|--------------------------------------------------------------------------
*/

$maximumChartValue = 0.00;

foreach ($chartData as $chartItem) {

    if (
        $chartItem['sales'] >
        $maximumChartValue
    ) {
        $maximumChartValue =
            $chartItem['sales'];
    }
}


/*
|--------------------------------------------------------------------------
| SHARED HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>


<!-- ================================================================
     PAGE STYLES
================================================================ -->

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

<link
    rel="stylesheet"
    href="../../assets/css/module.css"
>


<style>

.report-filter-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 14px;
}


.report-period {
    margin-bottom: 18px;
    font-size: 13px;
    color: #64748b;
}


.report-custom-form {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}


.report-date-input {
    padding: 7px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    background: #ffffff;
}


.report-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(300px, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}


.report-chart {
    min-height: 250px;
}


.report-bars {
    height: 200px;
    display: flex;
    align-items: flex-end;
    gap: 12px;
    padding-top: 20px;
}


.report-bar-item {
    flex: 1;
    min-width: 0;
    text-align: center;
}


.report-bar-track {
    height: 145px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}


.report-bar {
    width: min(46px, 70%);
    min-height: 4px;
    border-radius: 7px 7px 3px 3px;
    background: #3b82f6;
}


.report-bar-value {
    font-size: 11px;
    color: #475569;
    margin-bottom: 6px;
    white-space: nowrap;
}


.report-bar-label {
    margin-top: 8px;
    font-size: 11px;
    color: #64748b;
}


@media (max-width: 900px) {

    .report-grid {
        grid-template-columns: 1fr;
    }
}

</style>


<div class="app-layout">


    <!-- ============================================================
         SIDEBAR
    ============================================================ -->

    <aside class="app-sidebar-slot">

        <?php
        require_once "../../includes/sidebar.php";
        ?>

    </aside>


    <div class="app-main-slot">


        <!-- ========================================================
             TOPBAR
        ======================================================== -->

        <header class="app-topbar-slot">

            <?php
            require_once "../../includes/topbar.php";
            ?>

        </header>


        <main class="dashboard-main-content">

            <div
                class="dashboard-page"
                style="padding: 24px;"
            >


                <!-- =================================================
                     PAGE HEADER
                ================================================= -->

                <div class="page-header">

                    <div>

                        <h1>
                            Reports &amp; Analytics
                        </h1>

                        <p>
                            Review sales, purchases, outstanding dues,
                            product performance, and transaction activity.
                        </p>

                    </div>

                </div>


                <!-- =================================================
                     REPORT FILTERS
                ================================================= -->

                <div class="report-filter-row">


                    <?php

                    $filterOptions = [
                        'today' => 'Today',
                        'week' => 'Last 7 Days',
                        'month' => 'This Month',
                        'year' => 'This Year',
                        'custom' => 'Custom'
                    ];

                    ?>


                    <?php
                    foreach (
                        $filterOptions
                        as $filterKey => $filterLabel
                    ):
                    ?>

                        <a
                            href="?filter=<?php
                                echo urlencode(
                                    $filterKey
                                );
                            ?>"
                            class="
                                btn
                                btn-sm
                                <?php
                                echo
                                    $filter === $filterKey
                                        ? 'btn-primary'
                                        : 'btn-secondary';
                                ?>
                            "
                        >
                            <?php
                            echo htmlspecialchars(
                                $filterLabel,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </a>

                    <?php endforeach; ?>


                    <?php if ($filter === 'custom'): ?>

                        <form
                            method="GET"
                            class="report-custom-form"
                        >

                            <input
                                type="hidden"
                                name="filter"
                                value="custom"
                            >


                            <input
                                type="date"
                                name="from"
                                class="report-date-input"
                                value="<?php
                                    echo htmlspecialchars(
                                        $from,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                                required
                            >


                            <span>
                                to
                            </span>


                            <input
                                type="date"
                                name="to"
                                class="report-date-input"
                                value="<?php
                                    echo htmlspecialchars(
                                        $to,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                                required
                            >


                            <button
                                type="submit"
                                class="btn btn-primary btn-sm"
                            >
                                Apply
                            </button>

                        </form>

                    <?php endif; ?>


                </div>


                <div class="report-period">

                    Showing data from

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $from,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </strong>

                    to

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $to,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </strong>

                </div>


                <!-- =================================================
                     KPI CARDS
                ================================================= -->

                <div class="stats-row">


                    <div class="stat-card green">

                        <div class="stat-label">
                            Sales Revenue
                        </div>

                        <div class="stat-value">

                            &#2547;<?php
                            echo number_format(
                                $salesRevenue,
                                2
                            );
                            ?>

                        </div>

                    </div>


                    <div class="stat-card blue">

                        <div class="stat-label">
                            Sales Transactions
                        </div>

                        <div class="stat-value">

                            <?php
                            echo $salesTransactions;
                            ?>

                        </div>

                    </div>


                    <div class="stat-card green">

                        <div class="stat-label">
                            Paid Amount
                        </div>

                        <div class="stat-value">

                            &#2547;<?php
                            echo number_format(
                                $paidAmount,
                                2
                            );
                            ?>

                        </div>

                    </div>


                    <div class="stat-card red">

                        <div class="stat-label">
                            Outstanding Due
                        </div>

                        <div class="stat-value">

                            &#2547;<?php
                            echo number_format(
                                $outstandingDue,
                                2
                            );
                            ?>

                        </div>

                    </div>


                    <div class="stat-card amber">

                        <div class="stat-label">
                            Purchase Cost
                        </div>

                        <div class="stat-value">

                            &#2547;<?php
                            echo number_format(
                                $purchaseCost,
                                2
                            );
                            ?>

                        </div>

                    </div>


                </div>


                <!-- =================================================
                     CHART + TOP PRODUCTS
                ================================================= -->

                <div class="report-grid">


                    <!-- SALES TREND -->

                    <div class="card report-chart">

                        <div class="card-title">
                            Sales Trend - Last 6 Months
                        </div>


                        <div class="report-bars">


                            <?php
                            foreach (
                                $chartData
                                as $chartItem
                            ):
                            ?>


                                <?php

                                $heightPercentage = 0;

                                if (
                                    $maximumChartValue > 0
                                ) {
                                    $heightPercentage =
                                        (
                                            $chartItem['sales']
                                            /
                                            $maximumChartValue
                                        ) * 100;
                                }

                                ?>


                                <div class="report-bar-item">


                                    <div class="report-bar-value">

                                        &#2547;<?php
                                        echo number_format(
                                            (float) $chartItem[
                                                'sales'
                                            ],
                                            0
                                        );
                                        ?>

                                    </div>


                                    <div class="report-bar-track">

                                        <div
                                            class="report-bar"
                                            style="
                                                height:
                                                <?php
                                                echo max(
                                                    3,
                                                    round(
                                                        $heightPercentage,
                                                        2
                                                    )
                                                );
                                                ?>%;
                                            "
                                        ></div>

                                    </div>


                                    <div class="report-bar-label">

                                        <?php
                                        echo htmlspecialchars(
                                            $chartItem[
                                                'label'
                                            ],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>

                    </div>


                    <!-- TOP PRODUCTS -->

                    <div class="card">

                        <div class="card-title">
                            Top Selling Products
                        </div>


                        <div class="table-wrapper">

                            <table class="data-table">


                                <thead>

                                    <tr>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            Units
                                        </th>

                                        <th>
                                            Sales
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                <?php
                                if (
                                    count(
                                        $topProducts
                                    ) === 0
                                ):
                                ?>


                                    <tr class="empty-row">

                                        <td colspan="3">
                                            No sales found for this period.
                                        </td>

                                    </tr>


                                <?php else: ?>


                                    <?php

                                    $position = 1;

                                    foreach (
                                        $topProducts
                                        as $product
                                    ):

                                    ?>


                                        <tr>

                                            <td>

                                                <?php
                                                echo $position++;
                                                ?>.

                                                <?php
                                                echo htmlspecialchars(
                                                    $product[
                                                        'product_name'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </td>


                                            <td>

                                                <span
                                                    class="badge badge-info"
                                                >

                                                    <?php
                                                    echo
                                                        (int) $product[
                                                            'units_sold'
                                                        ];
                                                    ?>

                                                </span>

                                            </td>


                                            <td>

                                                &#2547;<?php
                                                echo number_format(
                                                    (float) $product[
                                                        'sales_amount'
                                                    ],
                                                    2
                                                );
                                                ?>

                                            </td>

                                        </tr>


                                    <?php endforeach; ?>


                                <?php endif; ?>


                                </tbody>


                            </table>

                        </div>

                    </div>


                </div>


                <!-- =================================================
                     TRANSACTION SUMMARY
                ================================================= -->

                <div class="card">

                    <div class="card-title">
                        Period Summary
                    </div>


                    <div
                        style="
                            display: flex;
                            gap: 30px;
                            flex-wrap: wrap;
                            padding: 4px 0 8px;
                            color: #475569;
                        "
                    >

                        <div>

                            <strong>
                                <?php
                                echo $salesTransactions;
                                ?>
                            </strong>

                            sales transaction(s)

                        </div>


                        <div>

                            <strong>
                                <?php
                                echo $purchaseTransactions;
                                ?>
                            </strong>

                            purchase transaction(s)

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     SALES TRANSACTIONS
                ================================================= -->

                <div class="card">


                    <div class="card-title">
                        Recent Sales in Selected Period
                    </div>


                    <div class="table-wrapper">


                        <table class="data-table">


                            <thead>

                                <tr>

                                    <th>
                                        Invoice
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Paid
                                    </th>

                                    <th>
                                        Due
                                    </th>

                                    <th>
                                        Payment Method
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php
                            if (
                                count(
                                    $recentSales
                                ) === 0
                            ):
                            ?>


                                <tr class="empty-row">

                                    <td colspan="7">
                                        No sales transactions found for this period.
                                    </td>

                                </tr>


                            <?php else: ?>


                                <?php
                                foreach (
                                    $recentSales
                                    as $sale
                                ):
                                ?>


                                    <tr>


                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $sale[
                                                    'invoice_no'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $sale[
                                                    'sale_date'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $sale[
                                                    'customer_name'
                                                ]
                                                ?? 'Walk-in',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <strong>

                                                &#2547;<?php
                                                echo number_format(
                                                    (float) $sale[
                                                        'total_amount'
                                                    ],
                                                    2
                                                );
                                                ?>

                                            </strong>

                                        </td>


                                        <td>

                                            &#2547;<?php
                                            echo number_format(
                                                (float) $sale[
                                                    'paid_amount'
                                                ],
                                                2
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            &#2547;<?php
                                            echo number_format(
                                                (float) $sale[
                                                    'due_amount'
                                                ],
                                                2
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <span
                                                class="badge badge-info"
                                            >

                                                <?php
                                                echo htmlspecialchars(
                                                    $sale[
                                                        'payment_method'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </span>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            <?php endif; ?>


                            </tbody>


                        </table>


                    </div>


                </div>


            </div>


        </main>


    </div>


</div>


<?php

require_once "../../includes/footer.php";

?>