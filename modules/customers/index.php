<?php

session_start();

/*
|--------------------------------------------------------------------------
| CUSTOMER MANAGEMENT PAGE
|--------------------------------------------------------------------------
| Stage 7 — final polish/testing pass
|
| Features:
| - Customer search
| - Retail / Wholesale filter
| - Paid / Pending filter (derived from total_due)
| - Last purchase date derived from sales
| - Customer summary values
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
| BASE PATH / HELPERS
|--------------------------------------------------------------------------
*/

$basePath = "/grocery-shop";

function customerEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| PAGE INFORMATION
|--------------------------------------------------------------------------
*/

$pageTitle = "Customer Management";


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER INPUTS
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$customerTypeFilter = isset($_GET['customer_type'])
    ? trim($_GET['customer_type'])
    : '';

$paymentStatusFilter = isset($_GET['payment_status'])
    ? trim($_GET['payment_status'])
    : '';


/*
|--------------------------------------------------------------------------
| VALIDATE FILTER VALUES
|--------------------------------------------------------------------------
*/

if (!in_array($customerTypeFilter, ['Retail', 'Wholesale'], true)) {
    $customerTypeFilter = '';
}

if (!in_array($paymentStatusFilter, ['Paid', 'Pending'], true)) {
    $paymentStatusFilter = '';
}


/*
|--------------------------------------------------------------------------
| CUSTOMER SUMMARY
|--------------------------------------------------------------------------
| Summary values are for the whole customer database and do not change when
| the user applies table filters.
|--------------------------------------------------------------------------
*/

$summary = [
    'total_customers' => 0,
    'wholesale' => 0,
    'retail' => 0,
    'total_due' => 0.00,
];

$summarySql = "
    SELECT
        COUNT(*) AS total_customers,
        COALESCE(SUM(customer_type = 'Wholesale'), 0) AS wholesale,
        COALESCE(SUM(customer_type = 'Retail'), 0) AS retail,
        COALESCE(SUM(total_due), 0) AS total_due
    FROM customers
";

$summaryResult = mysqli_query($conn, $summarySql);

if ($summaryResult) {
    $row = mysqli_fetch_assoc($summaryResult);

    $summary['total_customers'] = (int) $row['total_customers'];
    $summary['wholesale'] = (int) $row['wholesale'];
    $summary['retail'] = (int) $row['retail'];
    $summary['total_due'] = (float) $row['total_due'];
}


/*
|--------------------------------------------------------------------------
| CUSTOMER LIST QUERY
|--------------------------------------------------------------------------
| last_purchase is calculated from sales and is not stored in customers.
| payment status is also derived:
|   total_due <= 0 => Paid
|   total_due > 0  => Pending
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
        c.customer_type,
        c.account_status,
        c.total_due,
        c.created_at,
        lp.last_purchase
    FROM customers c
    LEFT JOIN (
        SELECT
            customer_id,
            MAX(sale_date) AS last_purchase
        FROM sales
        WHERE customer_id IS NOT NULL
        GROUP BY customer_id
    ) lp
        ON c.customer_id = lp.customer_id
    WHERE 1 = 1
";


/* SEARCH: name, phone or email */
if ($search !== '') {
    $searchEscaped = mysqli_real_escape_string($conn, $search);

    $sql .= "
        AND (
            c.customer_name LIKE '%{$searchEscaped}%'
            OR c.phone LIKE '%{$searchEscaped}%'
            OR c.email LIKE '%{$searchEscaped}%'
        )
    ";
}


/* CUSTOMER TYPE FILTER */
if ($customerTypeFilter !== '') {
    $typeEscaped = mysqli_real_escape_string($conn, $customerTypeFilter);

    $sql .= "
        AND c.customer_type = '{$typeEscaped}'
    ";
}


/* PAYMENT STATUS FILTER (derived from outstanding due) */
if ($paymentStatusFilter === 'Paid') {
    $sql .= "
        AND c.total_due <= 0
    ";
} elseif ($paymentStatusFilter === 'Pending') {
    $sql .= "
        AND c.total_due > 0
    ";
}


$sql .= "
    ORDER BY c.customer_id DESC
";

$customerResult = mysqli_query($conn, $sql);

if ($customerResult) {
    while ($row = mysqli_fetch_assoc($customerResult)) {
        $customers[] = $row;
    }
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

            <div class="customers-page">

                <!-- PAGE HEADING -->
                <div class="customers-heading">
                    <h1>Customer Management</h1>
                    <p>Manage wholesale &amp; retail customers</p>
                </div>


                <?php if (isset($_GET['added']) && $_GET['added'] === '1'): ?>
                    <div class="customer-alert customer-alert-success" role="status">
                        <span class="customer-alert-icon" aria-hidden="true">✓</span>
                        <div>
                            <strong>Customer added successfully.</strong>
                            <span>The new customer is now available in Customer Management.</span>
                        </div>
                    </div>
                <?php endif; ?>


                <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
                    <div class="customer-alert customer-alert-success" role="status">
                        <span class="customer-alert-icon" aria-hidden="true">✓</span>
                        <div>
                            <strong>Customer updated successfully.</strong>
                            <span>The latest customer information is now shown below.</span>
                        </div>
                    </div>
                <?php endif; ?>


                <?php if (isset($_GET['deactivated']) && $_GET['deactivated'] === '1'): ?>
                    <div class="customer-alert customer-alert-neutral" role="status">
                        <span class="customer-alert-icon" aria-hidden="true">i</span>
                        <div>
                            <strong>Customer deactivated safely.</strong>
                            <span>The customer record and all financial history have been preserved.</span>
                        </div>
                    </div>
                <?php endif; ?>


                <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
                    <div class="customer-alert customer-alert-success" role="status">
                        <span class="customer-alert-icon" aria-hidden="true">✓</span>
                        <div>
                            <strong>Customer deleted successfully.</strong>
                            <span>The customer had no sales, payments or outstanding due.</span>
                        </div>
                    </div>
                <?php endif; ?>


                <!-- FILTERS -->
                <form
                    method="GET"
                    action="<?php echo customerEscape($basePath); ?>/modules/customers/index.php"
                    class="customer-filter-form"
                >

                    <div class="customer-search-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.5-3.5"></path>
                        </svg>

                        <input
                            type="search"
                            name="search"
                            value="<?php echo customerEscape($search); ?>"
                            placeholder="Search customer..."
                            aria-label="Search customer"
                            autocomplete="off"
                        >
                    </div>


                    <select
                        name="customer_type"
                        class="customer-filter-select"
                        aria-label="Customer filter"
                        data-auto-submit="true"
                    >
                        <option value="">Customer Filter</option>
                        <option value="Wholesale" <?php echo $customerTypeFilter === 'Wholesale' ? 'selected' : ''; ?>>
                            Wholesale
                        </option>
                        <option value="Retail" <?php echo $customerTypeFilter === 'Retail' ? 'selected' : ''; ?>>
                            Retail
                        </option>
                    </select>


                    <select
                        name="payment_status"
                        class="customer-filter-select"
                        aria-label="Status filter"
                        data-auto-submit="true"
                    >
                        <option value="">Status Filter</option>
                        <option value="Paid" <?php echo $paymentStatusFilter === 'Paid' ? 'selected' : ''; ?>>
                            Paid
                        </option>
                        <option value="Pending" <?php echo $paymentStatusFilter === 'Pending' ? 'selected' : ''; ?>>
                            Pending
                        </option>
                    </select>


                    <button type="submit" class="customer-filter-submit-sr">
                        Apply filters
                    </button>


                    <?php if (
                        $search !== '' ||
                        $customerTypeFilter !== '' ||
                        $paymentStatusFilter !== ''
                    ): ?>
                        <a
                            href="<?php echo customerEscape($basePath); ?>/modules/customers/index.php"
                            class="customer-clear-link"
                            aria-label="Clear all customer filters"
                        >
                            Clear filters
                        </a>
                    <?php endif; ?>


                    <a
                        href="<?php echo customerEscape($basePath); ?>/modules/customers/add.php"
                        class="add-customer-button"
                    >
                        <span aria-hidden="true">+</span>
                        Add Customer
                    </a>

                </form>


                <!-- CUSTOMER TABLE -->
                <section class="customer-table-card">

                    <div class="customer-table-wrapper">

                        <table class="customer-table">

                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th>Type</th>
                                    <th>Last Purchase</th>
                                    <th>Outstanding Due</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (count($customers) > 0): ?>

                                <?php foreach ($customers as $customer): ?>

                                    <?php
                                    $hasDue = (float) $customer['total_due'] > 0;
                                    $paymentStatus = $hasDue ? 'Pending' : 'Paid';

                                    $lastPurchase = !empty($customer['last_purchase'])
                                        ? date('d M Y', strtotime($customer['last_purchase']))
                                        : '—';
                                    ?>

                                    <tr>

                                        <td>
                                            <div class="customer-name-cell">
                                                <span class="customer-name">
                                                    <?php echo customerEscape($customer['customer_name']); ?>
                                                </span>

                                                <?php if ($customer['account_status'] === 'Inactive'): ?>
                                                    <span class="account-inactive-label">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?php echo !empty($customer['phone']) ? customerEscape($customer['phone']) : '—'; ?>
                                        </td>

                                        <td>
                                            <span class="type-text">
                                                <?php echo customerEscape($customer['customer_type']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php echo customerEscape($lastPurchase); ?>
                                        </td>

                                        <td class="due-amount <?php echo $hasDue ? 'has-due' : ''; ?>">
                                            ৳<?php echo number_format((float) $customer['total_due'], 0); ?>
                                        </td>

                                        <td>
                                            <span class="payment-badge <?php echo $hasDue ? 'payment-pending' : 'payment-paid'; ?>">
                                                <?php echo customerEscape($paymentStatus); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="customer-actions">

                                                <a
                                                    href="<?php echo customerEscape($basePath); ?>/modules/customers/view.php?id=<?php echo (int) $customer['customer_id']; ?>"
                                                    class="customer-action-button view-action"
                                                    title="View customer"
                                                    aria-label="View <?php echo customerEscape($customer['customer_name']); ?>"
                                                >
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12Z"></path>
                                                        <circle cx="12" cy="12" r="2.5"></circle>
                                                    </svg>
                                                </a>

                                                <a
                                                    href="<?php echo customerEscape($basePath); ?>/modules/customers/edit.php?id=<?php echo (int) $customer['customer_id']; ?>"
                                                    class="customer-action-button edit-action"
                                                    title="Edit customer"
                                                    aria-label="Edit <?php echo customerEscape($customer['customer_name']); ?>"
                                                >
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-4-4L4 16v4Z"></path>
                                                        <path d="m13.5 6.5 4 4"></path>
                                                    </svg>
                                                </a>

                                                <a
                                                    href="<?php echo customerEscape($basePath); ?>/modules/customers/delete.php?id=<?php echo (int) $customer['customer_id']; ?>"
                                                    class="customer-action-button delete-action"
                                                    title="Delete or deactivate customer"
                                                    aria-label="Delete or deactivate <?php echo customerEscape($customer['customer_name']); ?>"
                                                >
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M4 7h16"></path>
                                                        <path d="M9 7V4h6v3"></path>
                                                        <path d="M7 7l1 13h8l1-13"></path>
                                                        <path d="M10 11v5M14 11v5"></path>
                                                    </svg>
                                                </a>

                                            </div>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="7" class="customer-empty-state">
                                        <div class="empty-state-icon">◎</div>
                                        <strong>No customers found</strong>
                                        <span>
                                            <?php if (
                                                $search !== '' ||
                                                $customerTypeFilter !== '' ||
                                                $paymentStatusFilter !== ''
                                            ): ?>
                                                Try changing or clearing your current filters.
                                            <?php else: ?>
                                                Customer records will appear here after they are added.
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </section>


                <!-- CUSTOMER SUMMARY -->
                <section class="customer-summary-card">

                    <h2>Customer Summary</h2>

                    <div class="customer-summary-grid">

                        <div class="summary-item">
                            <span class="summary-label">Total Customers</span>
                            <strong class="summary-value summary-blue">
                                <?php echo number_format($summary['total_customers']); ?>
                            </strong>
                        </div>

                        <div class="summary-item">
                            <span class="summary-label">Wholesale</span>
                            <strong class="summary-value summary-blue">
                                <?php echo number_format($summary['wholesale']); ?>
                            </strong>
                        </div>

                        <div class="summary-item">
                            <span class="summary-label">Retail</span>
                            <strong class="summary-value summary-blue">
                                <?php echo number_format($summary['retail']); ?>
                            </strong>
                        </div>

                        <div class="summary-item">
                            <span class="summary-label">Total Due</span>
                            <strong class="summary-value summary-red">
                                ৳<?php echo number_format($summary['total_due'], 0); ?>
                            </strong>
                        </div>

                    </div>

                </section>

            </div>

        </main>

        <script>
        (function () {
            const filterForm = document.querySelector('.customer-filter-form');

            if (!filterForm) {
                return;
            }

            filterForm.querySelectorAll('[data-auto-submit="true"]').forEach(function (field) {
                field.addEventListener('change', function () {
                    filterForm.submit();
                });
            });
        }());
        </script>

        <script src="../../assets/js/sidebar.js"></script>

    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
