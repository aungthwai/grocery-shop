<?php

session_start();

/*
|--------------------------------------------------------------------------
| SAFE CUSTOMER REMOVAL
|--------------------------------------------------------------------------
| A customer is permanently deleted only when all of these are true:
| - no recorded sales
| - no recorded payments
| - no outstanding due
|
| Otherwise the customer is safely deactivated so financial history is
| preserved for sales, payments and wholesale due workflows.
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
$pageTitle = "Remove Customer";

function customerDeleteEscape($value)
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
| CUSTOMER + SAFETY INFORMATION
|--------------------------------------------------------------------------
*/

function loadCustomerRemovalInfo($conn, $customerId)
{
    $sql = "
        SELECT
            c.customer_id,
            c.customer_name,
            c.phone,
            c.customer_type,
            c.account_status,
            c.total_due,
            (SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.customer_id) AS sales_count,
            (SELECT COUNT(*) FROM payments p WHERE p.customer_id = c.customer_id) AS payments_count
        FROM customers c
        WHERE c.customer_id = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customer = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $customer;
}

$customer = loadCustomerRemovalInfo($conn, $customerId);

/*
|--------------------------------------------------------------------------
| CUSTOMER NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$customer) {
    header("Location: {$basePath}/modules/customers/index.php?not_found=1");
    exit;
}

$salesCount = (int) $customer['sales_count'];
$paymentsCount = (int) $customer['payments_count'];
$totalDue = (float) $customer['total_due'];
$canDeletePermanently = $salesCount === 0 && $paymentsCount === 0 && $totalDue <= 0;
$isAlreadyInactive = $customer['account_status'] === 'Inactive';

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

$csrfKey = 'customer_delete_csrf_' . $customerId;

if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION[$csrfKey];
$errors = [];

/*
|--------------------------------------------------------------------------
| PROCESS CONFIRMATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Your form session expired. Please refresh the page and try again.';
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);

        try {
            /* Lock the customer row while the safety rules are checked again. */
            $lockSql = "
                SELECT customer_id, account_status, total_due
                FROM customers
                WHERE customer_id = ?
                LIMIT 1
                FOR UPDATE
            ";

            $lockStmt = mysqli_prepare($conn, $lockSql);

            if (!$lockStmt) {
                throw new Exception('Unable to verify the customer record.');
            }

            mysqli_stmt_bind_param($lockStmt, 'i', $customerId);
            mysqli_stmt_execute($lockStmt);
            $lockResult = mysqli_stmt_get_result($lockStmt);
            $lockedCustomer = $lockResult ? mysqli_fetch_assoc($lockResult) : null;
            mysqli_stmt_close($lockStmt);

            if (!$lockedCustomer) {
                throw new Exception('Customer record no longer exists.');
            }

            $salesStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM sales WHERE customer_id = ?");
            $paymentsStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM payments WHERE customer_id = ?");

            if (!$salesStmt || !$paymentsStmt) {
                if ($salesStmt) {
                    mysqli_stmt_close($salesStmt);
                }
                if ($paymentsStmt) {
                    mysqli_stmt_close($paymentsStmt);
                }
                throw new Exception('Unable to verify customer history.');
            }

            mysqli_stmt_bind_param($salesStmt, 'i', $customerId);
            mysqli_stmt_execute($salesStmt);
            $salesResult = mysqli_stmt_get_result($salesStmt);
            $currentSalesCount = (int) mysqli_fetch_assoc($salesResult)['total'];
            mysqli_stmt_close($salesStmt);

            mysqli_stmt_bind_param($paymentsStmt, 'i', $customerId);
            mysqli_stmt_execute($paymentsStmt);
            $paymentsResult = mysqli_stmt_get_result($paymentsStmt);
            $currentPaymentsCount = (int) mysqli_fetch_assoc($paymentsResult)['total'];
            mysqli_stmt_close($paymentsStmt);

            $currentDue = (float) $lockedCustomer['total_due'];
            $safeForPermanentDelete = $currentSalesCount === 0
                && $currentPaymentsCount === 0
                && $currentDue <= 0;

            if ($safeForPermanentDelete) {
                $deleteStmt = mysqli_prepare($conn, "DELETE FROM customers WHERE customer_id = ? LIMIT 1");

                if (!$deleteStmt) {
                    throw new Exception('Unable to prepare customer deletion.');
                }

                mysqli_stmt_bind_param($deleteStmt, 'i', $customerId);

                if (!mysqli_stmt_execute($deleteStmt)) {
                    $message = mysqli_stmt_error($deleteStmt);
                    mysqli_stmt_close($deleteStmt);
                    throw new Exception($message ?: 'Customer could not be deleted.');
                }

                mysqli_stmt_close($deleteStmt);
                mysqli_commit($conn);
                unset($_SESSION[$csrfKey]);

                header("Location: {$basePath}/modules/customers/index.php?deleted=1");
                exit;
            }

            /* Financial/history data exists: preserve the record and deactivate it. */
            $deactivateStmt = mysqli_prepare(
                $conn,
                "UPDATE customers SET account_status = 'Inactive' WHERE customer_id = ? LIMIT 1"
            );

            if (!$deactivateStmt) {
                throw new Exception('Unable to prepare customer deactivation.');
            }

            mysqli_stmt_bind_param($deactivateStmt, 'i', $customerId);

            if (!mysqli_stmt_execute($deactivateStmt)) {
                $message = mysqli_stmt_error($deactivateStmt);
                mysqli_stmt_close($deactivateStmt);
                throw new Exception($message ?: 'Customer could not be deactivated.');
            }

            mysqli_stmt_close($deactivateStmt);
            mysqli_commit($conn);
            unset($_SESSION[$csrfKey]);

            header("Location: {$basePath}/modules/customers/index.php?deactivated=1");
            exit;

        } catch (Throwable $exception) {
            mysqli_rollback($conn);
            error_log('Customer removal failed: ' . $exception->getMessage());
            $errors[] = 'The customer could not be updated safely. Please try again.';
        }
    }

    /* Refresh the displayed safety information if the operation did not finish. */
    $customer = loadCustomerRemovalInfo($conn, $customerId);

    if ($customer) {
        $salesCount = (int) $customer['sales_count'];
        $paymentsCount = (int) $customer['payments_count'];
        $totalDue = (float) $customer['total_due'];
        $canDeletePermanently = $salesCount === 0 && $paymentsCount === 0 && $totalDue <= 0;
        $isAlreadyInactive = $customer['account_status'] === 'Inactive';
    }
}

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

            <div class="customers-page customer-form-page customer-remove-page">

                <div class="customer-form-heading-row">
                    <div class="customers-heading customer-form-heading">
                        <h1><?php echo $canDeletePermanently ? 'Delete Customer' : 'Deactivate Customer'; ?></h1>
                        <p>Protect customer and financial history before removing access</p>
                    </div>

                    <a
                        href="<?php echo customerDeleteEscape($basePath); ?>/modules/customers/index.php"
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
                            <strong>We could not complete that action.</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo customerDeleteEscape($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <section class="customer-remove-card">

                    <div class="customer-remove-customer">
                        <div class="customer-remove-avatar" aria-hidden="true">
                            <?php echo customerDeleteEscape(mb_strtoupper(mb_substr($customer['customer_name'], 0, 1))); ?>
                        </div>

                        <div>
                            <span class="customer-remove-eyebrow">Customer #<?php echo (int) $customer['customer_id']; ?></span>
                            <h2><?php echo customerDeleteEscape($customer['customer_name']); ?></h2>
                            <p>
                                <?php echo customerDeleteEscape($customer['customer_type']); ?> Customer
                                <?php if (!empty($customer['phone'])): ?>
                                    <span>•</span>
                                    <?php echo customerDeleteEscape($customer['phone']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="customer-remove-rule <?php echo $canDeletePermanently ? 'customer-remove-rule-delete' : 'customer-remove-rule-deactivate'; ?>">
                        <div class="customer-remove-rule-icon" aria-hidden="true">
                            <?php echo $canDeletePermanently ? '!' : 'i'; ?>
                        </div>

                        <div>
                            <?php if ($canDeletePermanently): ?>
                                <h3>This customer can be permanently deleted.</h3>
                                <p>
                                    No sales, payments or outstanding due are connected to this customer.
                                    Permanent deletion will remove the customer profile from the database.
                                </p>
                            <?php else: ?>
                                <h3>This customer will be deactivated instead of deleted.</h3>
                                <p>
                                    Financial or transaction information is connected to this customer.
                                    GrocerEase will keep the record for history and mark the account as Inactive.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="customer-remove-checks">
                        <div class="customer-remove-check-item">
                            <span>Recorded Sales</span>
                            <strong><?php echo $salesCount; ?></strong>
                        </div>

                        <div class="customer-remove-check-item">
                            <span>Recorded Payments</span>
                            <strong><?php echo $paymentsCount; ?></strong>
                        </div>

                        <div class="customer-remove-check-item">
                            <span>Outstanding Due</span>
                            <strong class="<?php echo $totalDue > 0 ? 'customer-remove-due' : ''; ?>">
                                ৳<?php echo number_format($totalDue, 0); ?>
                            </strong>
                        </div>

                        <div class="customer-remove-check-item">
                            <span>Account Status</span>
                            <strong><?php echo customerDeleteEscape($customer['account_status']); ?></strong>
                        </div>
                    </div>

                    <?php if (!$canDeletePermanently): ?>
                        <div class="customer-remove-history-note">
                            <strong>Why is permanent deletion blocked?</strong>
                            <ul>
                                <?php if ($salesCount > 0): ?>
                                    <li>This customer has <?php echo $salesCount; ?> recorded sale<?php echo $salesCount === 1 ? '' : 's'; ?>.</li>
                                <?php endif; ?>
                                <?php if ($paymentsCount > 0): ?>
                                    <li>This customer has <?php echo $paymentsCount; ?> recorded payment<?php echo $paymentsCount === 1 ? '' : 's'; ?>.</li>
                                <?php endif; ?>
                                <?php if ($totalDue > 0): ?>
                                    <li>This customer has an outstanding due of ৳<?php echo number_format($totalDue, 0); ?>.</li>
                                <?php endif; ?>
                            </ul>
                            <p>The sales/payment records stay untouched when the customer is deactivated.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($isAlreadyInactive && !$canDeletePermanently): ?>
                        <div class="customer-alert customer-alert-neutral" role="status">
                            <span class="customer-alert-icon" aria-hidden="true">i</span>
                            <div>
                                <strong>This customer is already inactive.</strong>
                                <span>You can reactivate the customer later from Edit Customer if needed.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="customer-remove-actions">
                        <input type="hidden" name="csrf_token" value="<?php echo customerDeleteEscape($csrfToken); ?>">

                        <a
                            href="<?php echo customerDeleteEscape($basePath); ?>/modules/customers/view.php?id=<?php echo (int) $customerId; ?>"
                            class="customer-form-cancel"
                        >
                            Cancel
                        </a>

                        <?php if (!($isAlreadyInactive && !$canDeletePermanently)): ?>
                            <button
                                type="submit"
                                class="customer-remove-confirm <?php echo $canDeletePermanently ? 'customer-remove-confirm-delete' : 'customer-remove-confirm-deactivate'; ?>"
                            >
                                <span aria-hidden="true"><?php echo $canDeletePermanently ? '⌫' : '○'; ?></span>
                                <?php echo $canDeletePermanently ? 'Delete Customer Permanently' : 'Deactivate Customer'; ?>
                            </button>
                        <?php endif; ?>
                    </form>

                </section>

            </div>

        </main>

        <script src="../../assets/js/sidebar.js"></script>

    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
