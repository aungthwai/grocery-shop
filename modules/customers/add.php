<?php

session_start();

/*
|--------------------------------------------------------------------------
| ADD CUSTOMER
|--------------------------------------------------------------------------
| Creates a customer record only.
|
| Customer profile + optional opening balance.
| - opening_due records any balance owed before GrocerEase starts tracking sales
| - total_due starts equal to opening_due for that customer
| - later due changes should come from sales/payments
| - last purchase is derived from sales
| - payment status is derived from total_due
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

require_once "../../config/database.php";


/*
|--------------------------------------------------------------------------
| PAGE SETTINGS / HELPERS
|--------------------------------------------------------------------------
*/

$basePath = "/grocery-shop";
$pageTitle = "Add Customer";

function customerAddEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| FORM STATE
|--------------------------------------------------------------------------
*/

$form = [
    'customer_name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'customer_type' => 'Retail',
    'account_status' => 'Active',
    'opening_due' => '0.00',
];

$errors = [];


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['customer_add_csrf'])) {
    $_SESSION['customer_add_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['customer_add_csrf'];


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
    $form['customer_type'] = trim($_POST['customer_type'] ?? 'Retail');
    $form['account_status'] = trim($_POST['account_status'] ?? 'Active');
    $form['opening_due'] = trim($_POST['opening_due'] ?? '0.00');

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
    }


    /* ACCOUNT STATUS */
    if (!in_array($form['account_status'], ['Active', 'Inactive'], true)) {
        $errors[] = 'Please select a valid account status.';
    }


    /* OPENING OUTSTANDING DUE */
    if ($form['opening_due'] === '') {
        $form['opening_due'] = '0.00';
    }

    if (!is_numeric($form['opening_due'])) {
        $errors[] = 'Opening outstanding due must be a valid amount.';
    } else {
        $openingDueValue = (float) $form['opening_due'];

        if ($openingDueValue < 0) {
            $errors[] = 'Opening outstanding due cannot be negative.';
        } elseif ($openingDueValue > 99999999.99) {
            $errors[] = 'Opening outstanding due is too large.';
        } elseif (
            $form['customer_type'] === 'Retail' &&
            $openingDueValue > 0
        ) {
            $errors[] = 'Retail customers cannot have an opening due. Use Wholesale for customers who already owe money.';
        } else {
            $form['opening_due'] = number_format($openingDueValue, 2, '.', '');
        }
    }


    /* INSERT */
    if (empty($errors)) {

        $sql = "
            INSERT INTO customers
                (customer_name, phone, email, address, customer_type, account_status, opening_due, total_due)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {

            $phone = $form['phone'] !== '' ? $form['phone'] : null;
            $email = $form['email'] !== '' ? $form['email'] : null;
            $address = $form['address'] !== '' ? $form['address'] : null;
            $openingDue = (float) $form['opening_due'];
            $currentDue = $openingDue;

            mysqli_stmt_bind_param(
                $stmt,
                'ssssssdd',
                $form['customer_name'],
                $phone,
                $email,
                $address,
                $form['customer_type'],
                $form['account_status'],
                $openingDue,
                $currentDue
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                /* Rotate token after successful write. */
                unset($_SESSION['customer_add_csrf']);

                header("Location: {$basePath}/modules/customers/index.php?added=1");
                exit;
            }

            error_log('Add customer failed: ' . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            $errors[] = 'The customer could not be saved. Please try again.';

        } else {
            error_log('Add customer prepare failed: ' . mysqli_error($conn));
            $errors[] = 'The customer could not be saved. Please try again.';
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
                        <h1>Add Customer</h1>
                        <p>Create a new retail or wholesale customer</p>
                    </div>

                    <a
                        href="<?php echo customerAddEscape($basePath); ?>/modules/customers/index.php"
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
                                    <li><?php echo customerAddEscape($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>


                <form method="POST" class="customer-form-card" novalidate>

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo customerAddEscape($csrfToken); ?>"
                    >


                    <div class="customer-form-section-heading">
                        <h2>Customer Information</h2>
                        <p>Enter the customer's contact and classification details.</p>
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
                                value="<?php echo customerAddEscape($form['customer_name']); ?>"
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
                                value="<?php echo customerAddEscape($form['phone']); ?>"
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
                                value="<?php echo customerAddEscape($form['email']); ?>"
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
                            ><?php echo customerAddEscape($form['address']); ?></textarea>
                        </div>


                        <div class="customer-field">
                            <label for="customer_type">
                                Customer Type <span class="required-mark">*</span>
                            </label>

                            <select id="customer_type" name="customer_type" required>
                                <option value="Retail" <?php echo $form['customer_type'] === 'Retail' ? 'selected' : ''; ?>>
                                    Retail
                                </option>
                                <option value="Wholesale" <?php echo $form['customer_type'] === 'Wholesale' ? 'selected' : ''; ?>>
                                    Wholesale
                                </option>
                            </select>

                            <small class="customer-field-hint">
                                Wholesale customers can use opening due and Wholesale Due Management.
                                Retail customers must start with a 0.00 opening balance.
                            </small>
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
                                Use Inactive when the customer should remain recorded but not treated as active.
                            </small>
                        </div>


                        <div class="customer-field customer-field-wide">
                            <label for="opening_due">Opening Outstanding Due (৳)</label>

                            <input
                                type="number"
                                id="opening_due"
                                name="opening_due"
                                min="0"
                                max="99999999.99"
                                step="0.01"
                                inputmode="decimal"
                                value="<?php echo customerAddEscape($form['opening_due']); ?>"
                                placeholder="0.00"
                            >

                            <small class="customer-field-hint" id="openingDueHint">
                                Available only for Wholesale customers. Enter money already owed before sales began being recorded in GrocerEase.
                            </small>
                        </div>

                    </div>


                    <div class="customer-form-note">
                        <span class="customer-form-note-icon" aria-hidden="true">i</span>
                        <p>
                            Opening Outstanding Due is available only for Wholesale customers.
                            Retail customers must start with no outstanding balance.
                            After creation, future balance changes come from sales, payments and Wholesale Due Management.
                            Last purchase and Paid/Pending status remain calculated automatically.
                        </p>
                    </div>


                    <div class="customer-form-actions">

                        <a
                            href="<?php echo customerAddEscape($basePath); ?>/modules/customers/index.php"
                            class="customer-form-cancel"
                        >
                            Cancel
                        </a>

                        <button type="submit" class="customer-form-submit">
                            <span aria-hidden="true">+</span>
                            Add Customer
                        </button>

                    </div>

                </form>

            </div>

        </main>

        <script>
        document.addEventListener('DOMContentLoaded', function () {

            const typeSelect = document.getElementById('customer_type');
            const openingDue = document.getElementById('opening_due');
            const openingDueHint = document.getElementById('openingDueHint');

            if (!typeSelect || !openingDue) {
                return;
            }

            function syncOpeningDueField() {

                const isRetail = typeSelect.value === 'Retail';

                openingDue.disabled = isRetail;

                if (isRetail) {
                    openingDue.value = '0.00';

                    if (openingDueHint) {
                        openingDueHint.textContent =
                            'Retail customers cannot have an opening due. Select Wholesale to enter an existing balance.';
                    }
                } else {
                    if (openingDueHint) {
                        openingDueHint.textContent =
                            'Optional. Enter money this wholesale customer already owed before sales began being recorded.';
                    }
                }
            }

            typeSelect.addEventListener('change', syncOpeningDueField);
            syncOpeningDueField();

        });
        </script>

        <script src="../../assets/js/sidebar.js"></script>

    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
