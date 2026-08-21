<?php

session_start();

/*
|--------------------------------------------------------------------------
| EDIT CUSTOMER
|--------------------------------------------------------------------------
| Updates customer profile information only.
|
| Financial values remain read-only / calculated elsewhere:
| - total_due is maintained by sales / due workflows
| - last purchase is derived from sales
| - payment status is derived from total_due
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
$pageTitle = "Edit Customer";

function customerEditEscape($value)
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
| LOAD CUSTOMER
|--------------------------------------------------------------------------
*/

$customer = null;

$loadSql = "
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
    LIMIT 1
";

$loadStmt = mysqli_prepare($conn, $loadSql);

if ($loadStmt) {
    mysqli_stmt_bind_param($loadStmt, 'i', $customerId);
    mysqli_stmt_execute($loadStmt);
    $loadResult = mysqli_stmt_get_result($loadStmt);
    $customer = $loadResult ? mysqli_fetch_assoc($loadResult) : null;
    mysqli_stmt_close($loadStmt);
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
                <div class="customers-page customer-form-page">
                    <div class="customer-form-heading-row">
                        <div class="customers-heading customer-form-heading">
                            <h1>Edit Customer</h1>
                            <p>Customer record could not be found</p>
                        </div>

                        <a href="<?php echo customerEditEscape($basePath); ?>/modules/customers/index.php" class="customer-back-link">
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
| CUSTOMER TYPE INTEGRITY
|--------------------------------------------------------------------------
| Customer type may be changed only before any financial history exists.
|
| Financial history means any of:
| - opening_due > 0
| - current total_due > 0
| - at least one sale
| - at least one payment
|
| This prevents a Wholesale customer with due/sales/payment history from
| being changed to Retail and disappearing from Wholesale Due Management
| or Dashboard due calculations.
|--------------------------------------------------------------------------
*/

$hasSales = false;
$hasPayments = false;

$historySql = "
    SELECT
        EXISTS(
            SELECT 1
            FROM sales
            WHERE customer_id = ?
            LIMIT 1
        ) AS has_sales,

        EXISTS(
            SELECT 1
            FROM payments
            WHERE customer_id = ?
            LIMIT 1
        ) AS has_payments
";

$historyStmt = mysqli_prepare($conn, $historySql);

if ($historyStmt) {

    mysqli_stmt_bind_param(
        $historyStmt,
        'ii',
        $customerId,
        $customerId
    );

    mysqli_stmt_execute($historyStmt);

    $historyResult =
        mysqli_stmt_get_result($historyStmt);

    if ($historyResult) {

        $historyRow =
            mysqli_fetch_assoc($historyResult);

        $hasSales =
            !empty($historyRow['has_sales']);

        $hasPayments =
            !empty($historyRow['has_payments']);
    }

    mysqli_stmt_close($historyStmt);
}

$customerTypeLocked =
    (float) $customer['opening_due'] > 0 ||
    (float) $customer['total_due'] > 0 ||
    $hasSales ||
    $hasPayments;


/*
|--------------------------------------------------------------------------
| FORM STATE
|--------------------------------------------------------------------------
*/

$form = [
    'customer_name' => (string) $customer['customer_name'],
    'phone' => (string) ($customer['phone'] ?? ''),
    'email' => (string) ($customer['email'] ?? ''),
    'address' => (string) ($customer['address'] ?? ''),
    'customer_type' => (string) $customer['customer_type'],
    'account_status' => (string) $customer['account_status'],
];

$errors = [];

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

$csrfKey = 'customer_edit_csrf_' . $customerId;

if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION[$csrfKey];

/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form['customer_name'] = trim($_POST['customer_name'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $form['address'] = trim($_POST['address'] ?? '');
    $form['customer_type'] = trim($_POST['customer_type'] ?? '');
    $form['account_status'] = trim($_POST['account_status'] ?? '');

    $submittedToken = $_POST['csrf_token'] ?? '';

    /* CSRF */
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Your form session expired. Please refresh the page and try again.';
    }

    /* CUSTOMER NAME */
    if ($form['customer_name'] === '') {
        $errors[] = 'Customer name is required.';
    } elseif (mb_strlen($form['customer_name']) > 100) {
        $errors[] = 'Customer name cannot be longer than 100 characters.';
    }

    /* PHONE */
    if ($form['phone'] !== '' && mb_strlen($form['phone']) > 20) {
        $errors[] = 'Phone number cannot be longer than 20 characters.';
    }

    /* EMAIL */
    if ($form['email'] !== '') {
        if (mb_strlen($form['email']) > 100) {
            $errors[] = 'Email cannot be longer than 100 characters.';
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
    }

    /* CUSTOMER TYPE */
    if (!in_array($form['customer_type'], ['Retail', 'Wholesale'], true)) {
        $errors[] = 'Please select a valid customer type.';
    } elseif (
        $customerTypeLocked &&
        $form['customer_type'] !== $customer['customer_type']
    ) {
        $errors[] = 'Customer type cannot be changed after financial history exists.';
        $form['customer_type'] = (string) $customer['customer_type'];
    }

    /* ACCOUNT STATUS */
    if (!in_array($form['account_status'], ['Active', 'Inactive'], true)) {
        $errors[] = 'Please select a valid account status.';
    }

    /* UPDATE */
    if (empty($errors)) {

        $updateSql = "
            UPDATE customers
            SET
                customer_name = ?,
                phone = ?,
                email = ?,
                address = ?,
                customer_type = ?,
                account_status = ?
            WHERE customer_id = ?
            LIMIT 1
        ";

        $updateStmt = mysqli_prepare($conn, $updateSql);

        if ($updateStmt) {

            $phone = $form['phone'] !== '' ? $form['phone'] : null;
            $email = $form['email'] !== '' ? $form['email'] : null;
            $address = $form['address'] !== '' ? $form['address'] : null;

            mysqli_stmt_bind_param(
                $updateStmt,
                'ssssssi',
                $form['customer_name'],
                $phone,
                $email,
                $address,
                $form['customer_type'],
                $form['account_status'],
                $customerId
            );

            if (mysqli_stmt_execute($updateStmt)) {
                mysqli_stmt_close($updateStmt);
                unset($_SESSION[$csrfKey]);

                header("Location: {$basePath}/modules/customers/index.php?updated=1");
                exit;
            }

            error_log('Edit customer failed: ' . mysqli_stmt_error($updateStmt));
            mysqli_stmt_close($updateStmt);
            $errors[] = 'The customer could not be updated. Please try again.';

        } else {
            error_log('Edit customer prepare failed: ' . mysqli_error($conn));
            $errors[] = 'The customer could not be updated. Please try again.';
        }
    }
}

$hasDue = (float) $customer['total_due'] > 0;
$paymentStatus = $hasDue ? 'Pending' : 'Paid';

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

            <div class="customers-page customer-form-page">

                <div class="customer-form-heading-row">

                    <div class="customers-heading customer-form-heading">
                        <h1>Edit Customer</h1>
                        <p>Update customer contact, type and account status</p>
                    </div>

                    <a
                        href="<?php echo customerEditEscape($basePath); ?>/modules/customers/index.php"
                        class="customer-back-link"
                    >
                        <span aria-hidden="true">←</span>
                        Back to Customers
                    </a>

                </div>

                <?php if (!empty($errors)): ?>
                    <div class="customer-alert customer-alert-error" role="alert">
                        <span class="customer-alert-icon" aria-hidden="true">!</span>
                        <div>
                            <strong>Please fix the following:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo customerEditEscape($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="customer-form-card" novalidate>

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo customerEditEscape($csrfToken); ?>"
                    >

                    <div class="customer-form-section-heading">
                        <h2>Customer Information</h2>
                        <p>Editing Customer #<?php echo (int) $customerId; ?>.</p>
                    </div>

                    <div class="customer-form-grid">

                        <div class="customer-field customer-field-wide">
                            <label for="customer_name">
                                Customer Name <span class="required-mark">*</span>
                            </label>

                            <input
                                type="text"
                                id="customer_name"
                                name="customer_name"
                                maxlength="100"
                                value="<?php echo customerEditEscape($form['customer_name']); ?>"
                                placeholder="Enter customer name"
                                autocomplete="name"
                                required
                            >
                        </div>

                        <div class="customer-field">
                            <label for="phone">Phone</label>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                maxlength="20"
                                value="<?php echo customerEditEscape($form['phone']); ?>"
                                placeholder="e.g. 01711-111111"
                                autocomplete="tel"
                            >
                        </div>

                        <div class="customer-field">
                            <label for="email">Email</label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="100"
                                value="<?php echo customerEditEscape($form['email']); ?>"
                                placeholder="customer@example.com"
                                autocomplete="email"
                            >
                        </div>

                        <div class="customer-field customer-field-wide">
                            <label for="address">Address</label>

                            <textarea
                                id="address"
                                name="address"
                                rows="4"
                                placeholder="Enter customer address"
                                autocomplete="street-address"
                            ><?php echo customerEditEscape($form['address']); ?></textarea>
                        </div>

                        <div class="customer-field">
                            <label for="customer_type">
                                Customer Type <span class="required-mark">*</span>
                            </label>

                            <?php if ($customerTypeLocked): ?>

                                <select id="customer_type_display" disabled>
                                    <option selected>
                                        <?php echo customerEditEscape($customer['customer_type']); ?>
                                    </option>
                                </select>

                                <input
                                    type="hidden"
                                    name="customer_type"
                                    value="<?php echo customerEditEscape($customer['customer_type']); ?>"
                                >

                                <small class="customer-field-hint">
                                    Customer type is locked because this customer already has financial history.
                                    This protects sales, payment history, Wholesale Due Management and Dashboard totals.
                                </small>

                            <?php else: ?>

                                <select id="customer_type" name="customer_type" required>
                                    <option value="Retail" <?php echo $form['customer_type'] === 'Retail' ? 'selected' : ''; ?>>
                                        Retail
                                    </option>
                                    <option value="Wholesale" <?php echo $form['customer_type'] === 'Wholesale' ? 'selected' : ''; ?>>
                                        Wholesale
                                    </option>
                                </select>

                                <small class="customer-field-hint">
                                    Customer type can be changed because this customer has no opening balance, sales or payments yet.
                                </small>

                            <?php endif; ?>
                        </div>

                        <div class="customer-field">
                            <label for="account_status">
                                Account Status <span class="required-mark">*</span>
                            </label>

                            <select id="account_status" name="account_status" required>
                                <option value="Active" <?php echo $form['account_status'] === 'Active' ? 'selected' : ''; ?>>
                                    Active
                                </option>
                                <option value="Inactive" <?php echo $form['account_status'] === 'Inactive' ? 'selected' : ''; ?>>
                                    Inactive
                                </option>
                            </select>

                            <small class="customer-field-hint">
                                Inactive preserves the customer record and its transaction history.
                            </small>
                        </div>

                    </div>

                    <div class="customer-edit-financial-preview">
                        <div>
                            <span>Opening Due</span>
                            <strong>
                                ৳<?php echo number_format((float) $customer['opening_due'], 0); ?>
                            </strong>
                        </div>

                        <div>
                            <span>Outstanding Due</span>
                            <strong class="<?php echo $hasDue ? 'summary-red' : 'summary-blue'; ?>">
                                ৳<?php echo number_format((float) $customer['total_due'], 0); ?>
                            </strong>
                        </div>

                        <div>
                            <span>Payment Status</span>
                            <strong>
                                <span class="payment-badge <?php echo $hasDue ? 'payment-pending' : 'payment-paid'; ?>">
                                    <?php echo customerEditEscape($paymentStatus); ?>
                                </span>
                            </strong>
                        </div>
                    </div>

                    <div class="customer-form-note">
                        <span class="customer-form-note-icon" aria-hidden="true">i</span>
                        <p>
                            Opening due, outstanding due and payment status are shown for reference only.
                            Opening due is fixed after customer creation.
                            Once financial history exists, Customer Type is also locked to protect connected sales, payments,
                            Wholesale Due Management and Dashboard calculations.
                        </p>
                    </div>

                    <div class="customer-form-actions">

                        <a
                            href="<?php echo customerEditEscape($basePath); ?>/modules/customers/view.php?id=<?php echo (int) $customerId; ?>"
                            class="customer-form-cancel"
                        >
                            Cancel
                        </a>

                        <button type="submit" class="customer-form-submit">
                            <span aria-hidden="true">✓</span>
                            Save Changes
                        </button>

                    </div>

                </form>

            </div>

        </main>

        <script src="../../assets/js/sidebar.js"></script>

    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
