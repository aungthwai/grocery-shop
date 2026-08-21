<?php

session_start();

/*
|--------------------------------------------------------------------------
| VIEW CUSTOMER
|--------------------------------------------------------------------------
| Read-only customer details page.
|
| Displays:
| - Customer information
| - Account / payment status
| - Total purchases and last purchase
| - Recent sales
| - Recent customer payments
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

require_once "../../includes/role_guard.php";
grocerEaseRequireAdmin();


require_once "../../config/database.php";

$basePath = "/grocery-shop";
$pageTitle = "Customer Details";

function customerViewEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| CUSTOMER ID
|--------------------------------------------------------------------------
*/

$customerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$customerId || $customerId < 1) {
    header("Location: {$basePath}/modules/customers/index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CUSTOMER + SUMMARY
|--------------------------------------------------------------------------
*/

$customer = null;

$customerSql = "
    SELECT
        c.customer_id,
        c.customer_name,
        c.phone,
        c.email,
        c.address,
        c.customer_type,
        c.account_status,
        c.opening_due,
        c.total_due,
        c.created_at,
        COUNT(s.sale_id) AS total_sales,
        COALESCE(SUM(s.total_amount), 0) AS total_purchases,
        MAX(s.sale_date) AS last_purchase
    FROM customers c
    LEFT JOIN sales s
        ON s.customer_id = c.customer_id
    WHERE c.customer_id = ?
    GROUP BY
        c.customer_id,
        c.customer_name,
        c.phone,
        c.email,
        c.address,
        c.customer_type,
        c.account_status,
        c.opening_due,
        c.total_due,
        c.created_at
    LIMIT 1
";

$customerStmt = mysqli_prepare($conn, $customerSql);

if ($customerStmt) {
    mysqli_stmt_bind_param($customerStmt, 'i', $customerId);
    mysqli_stmt_execute($customerStmt);
    $customerResult = mysqli_stmt_get_result($customerStmt);
    $customer = $customerResult ? mysqli_fetch_assoc($customerResult) : null;
    mysqli_stmt_close($customerStmt);
}

/*
|--------------------------------------------------------------------------
| CUSTOMER NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$customer) {
    require_once "../../includes/header.php";
    ?>

    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
    <link rel="stylesheet" href="../../assets/css/customers.css">

    <div class="app-layout">
        <aside class="app-sidebar-slot">
            <?php require_once "../../includes/sidebar.php"; ?>
        </aside>

        <div class="app-main-slot">
            <header class="app-topbar-slot">
                <?php require_once "../../includes/topbar.php"; ?>
            </header>

            <main class="dashboard-main-content">
                <div class="customers-page customer-view-page">
                    <div class="customer-form-heading-row">
                        <div class="customers-heading customer-form-heading">
                            <h1>Customer Details</h1>
                            <p>Customer record could not be found</p>
                        </div>

                        <a href="<?php echo customerViewEscape($basePath); ?>/modules/customers/index.php" class="customer-back-link">
                            <span aria-hidden="true">←</span>
                            Back to Customers
                        </a>
                    </div>

                    <div class="customer-alert customer-alert-error" role="alert">
                        <span class="customer-alert-icon" aria-hidden="true">!</span>
                        <div>
                            <strong>Customer not found.</strong>
                            <span>The customer may have been removed or the link is invalid.</span>
                        </div>
                    </div>
                </div>
            </main>

            <script src="../../assets/js/sidebar.js"></script>
        </div>
    </div>

    <?php
    require_once "../../includes/footer.php";
    exit;
}

/*
|--------------------------------------------------------------------------
| RECENT SALES
|--------------------------------------------------------------------------
*/

$recentSales = [];

$salesSql = "
    SELECT
        sale_id,
        invoice_no,
        sale_date,
        total_amount,
        paid_amount,
        due_amount,
        payment_method
    FROM sales
    WHERE customer_id = ?
    ORDER BY sale_date DESC, sale_id DESC
    LIMIT 5
";

$salesStmt = mysqli_prepare($conn, $salesSql);

if ($salesStmt) {
    mysqli_stmt_bind_param($salesStmt, 'i', $customerId);
    mysqli_stmt_execute($salesStmt);
    $salesResult = mysqli_stmt_get_result($salesStmt);

    if ($salesResult) {
        while ($row = mysqli_fetch_assoc($salesResult)) {
            $recentSales[] = $row;
        }
    }

    mysqli_stmt_close($salesStmt);
}

/*
|--------------------------------------------------------------------------
| RECENT PAYMENTS
|--------------------------------------------------------------------------
*/

$recentPayments = [];

$paymentsSql = "
    SELECT
        p.payment_id,
        p.payment_date,
        p.amount,
        p.payment_method,
        p.notes,
        s.invoice_no
    FROM payments p
    LEFT JOIN sales s
        ON s.sale_id = p.sale_id
    WHERE p.customer_id = ?
    ORDER BY p.payment_date DESC, p.payment_id DESC
    LIMIT 5
";

$paymentsStmt = mysqli_prepare($conn, $paymentsSql);

if ($paymentsStmt) {
    mysqli_stmt_bind_param($paymentsStmt, 'i', $customerId);
    mysqli_stmt_execute($paymentsStmt);
    $paymentsResult = mysqli_stmt_get_result($paymentsStmt);

    if ($paymentsResult) {
        while ($row = mysqli_fetch_assoc($paymentsResult)) {
            $recentPayments[] = $row;
        }
    }

    mysqli_stmt_close($paymentsStmt);
}

$hasDue = (float) $customer['total_due'] > 0;
$paymentStatus = $hasDue ? 'Pending' : 'Paid';
$lastPurchase = !empty($customer['last_purchase'])
    ? date('d M Y', strtotime($customer['last_purchase']))
    : '—';
$createdDate = !empty($customer['created_at'])
    ? date('d M Y', strtotime($customer['created_at']))
    : '—';

require_once "../../includes/header.php";

?>

<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<link rel="stylesheet" href="../../assets/css/customers.css">

<div class="app-layout">

    <aside class="app-sidebar-slot">
        <?php require_once "../../includes/sidebar.php"; ?>
    </aside>

    <div class="app-main-slot">

        <header class="app-topbar-slot">
            <?php require_once "../../includes/topbar.php"; ?>
        </header>

        <main class="dashboard-main-content">

            <div class="customers-page customer-view-page">

                <div class="customer-form-heading-row">
                    <div class="customers-heading customer-form-heading">
                        <h1>Customer Details</h1>
                        <p>View customer profile and transaction summary</p>
                    </div>

                    <div class="customer-heading-actions">
                        <a
                            href="<?php echo customerViewEscape($basePath); ?>/modules/customers/edit.php?id=<?php echo (int) $customerId; ?>"
                            class="customer-edit-link"
                        >
                            <span aria-hidden="true">✎</span>
                            Edit Customer
                        </a>

                        <a
                            href="<?php echo customerViewEscape($basePath); ?>/modules/customers/delete.php?id=<?php echo (int) $customerId; ?>"
                            class="customer-remove-link"
                        >
                            <span aria-hidden="true">⌫</span>
                            Remove Customer
                        </a>

                        <a
                            href="<?php echo customerViewEscape($basePath); ?>/modules/customers/index.php"
                            class="customer-back-link"
                        >
                            <span aria-hidden="true">←</span>
                            Back to Customers
                        </a>
                    </div>
                </div>

                <section class="customer-profile-card">

                    <div class="customer-profile-header">
                        <div class="customer-profile-identity">
                            <div class="customer-avatar" aria-hidden="true">
                                <?php echo customerViewEscape(mb_strtoupper(mb_substr($customer['customer_name'], 0, 1))); ?>
                            </div>

                            <div>
                                <div class="customer-profile-title-row">
                                    <h2><?php echo customerViewEscape($customer['customer_name']); ?></h2>

                                    <span class="customer-account-badge <?php echo $customer['account_status'] === 'Active' ? 'customer-account-active' : 'customer-account-inactive'; ?>">
                                        <?php echo customerViewEscape($customer['account_status']); ?>
                                    </span>
                                </div>

                                <p>
                                    <?php echo customerViewEscape($customer['customer_type']); ?> Customer
                                    <span class="customer-profile-separator">•</span>
                                    Customer #<?php echo (int) $customer['customer_id']; ?>
                                </p>
                            </div>
                        </div>

                        <div class="customer-profile-payment-state">
                            <span class="payment-badge <?php echo $hasDue ? 'payment-pending' : 'payment-paid'; ?>">
                                <?php echo customerViewEscape($paymentStatus); ?>
                            </span>
                            <small>Payment Status</small>
                        </div>
                    </div>

                    <div class="customer-detail-grid">

                        <div class="customer-detail-item">
                            <span class="customer-detail-label">Phone</span>
                            <strong><?php echo !empty($customer['phone']) ? customerViewEscape($customer['phone']) : '—'; ?></strong>
                        </div>

                        <div class="customer-detail-item">
                            <span class="customer-detail-label">Email</span>
                            <strong><?php echo !empty($customer['email']) ? customerViewEscape($customer['email']) : '—'; ?></strong>
                        </div>

                        <div class="customer-detail-item customer-detail-address">
                            <span class="customer-detail-label">Address</span>
                            <strong><?php echo !empty($customer['address']) ? nl2br(customerViewEscape($customer['address'])) : '—'; ?></strong>
                        </div>

                        <div class="customer-detail-item">
                            <span class="customer-detail-label">Customer Since</span>
                            <strong><?php echo customerViewEscape($createdDate); ?></strong>
                        </div>

                    </div>

                </section>

                <section class="customer-view-summary-grid">

                    <article class="customer-view-stat-card">
                        <span class="customer-view-stat-label">Total Purchases</span>
                        <strong class="customer-view-stat-value summary-blue">
                            ৳<?php echo number_format((float) $customer['total_purchases'], 0); ?>
                        </strong>
                        <small>Lifetime sales value</small>
                    </article>

                    <article class="customer-view-stat-card">
                        <span class="customer-view-stat-label">Total Sales</span>
                        <strong class="customer-view-stat-value summary-blue">
                            <?php echo (int) $customer['total_sales']; ?>
                        </strong>
                        <small>Recorded transactions</small>
                    </article>

                    <article class="customer-view-stat-card">
                        <span class="customer-view-stat-label">Last Purchase</span>
                        <strong class="customer-view-stat-value customer-view-stat-date">
                            <?php echo customerViewEscape($lastPurchase); ?>
                        </strong>
                        <small>Most recent recorded sale</small>
                    </article>

                    <article class="customer-view-stat-card">
                        <span class="customer-view-stat-label">Outstanding Due</span>
                        <strong class="customer-view-stat-value <?php echo $hasDue ? 'summary-red' : 'summary-blue'; ?>">
                            ৳<?php echo number_format((float) $customer['total_due'], 0); ?>
                        </strong>
                        <small>
                            Opening due: ৳<?php echo number_format((float) $customer['opening_due'], 0); ?>
                            · <?php echo $hasDue ? 'Payment currently pending' : 'No outstanding balance'; ?>
                        </small>
                    </article>

                </section>

                <div class="customer-view-sections-grid">

                    <section class="customer-history-card">
                        <div class="customer-history-heading">
                            <div>
                                <h2>Recent Sales</h2>
                                <p>Latest sales recorded for this customer</p>
                            </div>
                        </div>

                        <div class="customer-history-table-wrap">
                            <table class="customer-history-table">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Paid</th>
                                        <th>Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentSales)): ?>
                                        <?php foreach ($recentSales as $sale): ?>
                                            <tr>
                                                <td><?php echo customerViewEscape($sale['invoice_no']); ?></td>
                                                <td><?php echo customerViewEscape(date('d M Y', strtotime($sale['sale_date']))); ?></td>
                                                <td>৳<?php echo number_format((float) $sale['total_amount'], 0); ?></td>
                                                <td>৳<?php echo number_format((float) $sale['paid_amount'], 0); ?></td>
                                                <td class="<?php echo (float) $sale['due_amount'] > 0 ? 'history-due' : ''; ?>">
                                                    ৳<?php echo number_format((float) $sale['due_amount'], 0); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="customer-history-empty">No sales recorded yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="customer-history-card">
                        <div class="customer-history-heading">
                            <div>
                                <h2>Recent Payments</h2>
                                <p>Latest payments linked to this customer</p>
                            </div>
                        </div>

                        <div class="customer-history-table-wrap">
                            <table class="customer-history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Invoice</th>
                                        <th>Method</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentPayments)): ?>
                                        <?php foreach ($recentPayments as $payment): ?>
                                            <tr>
                                                <td><?php echo customerViewEscape(date('d M Y', strtotime($payment['payment_date']))); ?></td>
                                                <td><?php echo !empty($payment['invoice_no']) ? customerViewEscape($payment['invoice_no']) : '—'; ?></td>
                                                <td><?php echo customerViewEscape($payment['payment_method']); ?></td>
                                                <td>৳<?php echo number_format((float) $payment['amount'], 0); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="customer-history-empty">No payments recorded yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                </div>

            </div>

        </main>

        <script src="../../assets/js/sidebar.js"></script>

    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
