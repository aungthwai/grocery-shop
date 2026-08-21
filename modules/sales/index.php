<?php

session_start();

/*
|--------------------------------------------------------------------------
| RECORD SALE
|--------------------------------------------------------------------------
| Functional sale-entry workflow based on the existing GrocerEase schema.
|
| - Walk-in retail sales use customer_id = NULL.
| - Wholesale sales require an active Wholesale customer.
| - Sale items stay in browser memory until the sale is confirmed.
| - The server re-validates product status, price, and stock.
| - Sale, sale items, stock updates, customer due, and payment history are
|   committed together inside one database transaction.
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Your session has expired. Please log in again.'
        ]);
        exit;
    }

    header('Location: ../../login.php');
    exit;
}

require_once '../../config/database.php';

$basePath = '/grocery-shop';
$pageTitle = 'Record Sale';

function saleJsonResponse(array $payload): void
{
    header('Content-Type: application/json; charset=UTF-8');
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

function generateSaleInvoiceNo(mysqli $conn): string
{
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $invoiceNo = 'SAL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $stmt = mysqli_prepare(
            $conn,
            'SELECT sale_id FROM sales WHERE invoice_no = ? LIMIT 1'
        );

        if (!$stmt) {
            throw new Exception('Unable to prepare invoice number verification.');
        }

        mysqli_stmt_bind_param($stmt, 's', $invoiceNo);

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Unable to verify the generated invoice number.');
        }

        $result = mysqli_stmt_get_result($stmt);
        $exists = $result && mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);

        if (!$exists) {
            return $invoiceNo;
        }
    }

    throw new Exception('Unable to generate a unique sale invoice number. Please try again.');
}

if (empty($_SESSION['sale_csrf'])) {
    $_SESSION['sale_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['sale_csrf'];

/*
|--------------------------------------------------------------------------
| SAVE SALE (AJAX)
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_sale'])
) {
    $transactionStarted = false;

    try {
        $submittedToken = (string) ($_POST['csrf_token'] ?? '');

        if ($submittedToken === '' || !hash_equals($csrfToken, $submittedToken)) {
            throw new Exception('Your form session expired. Refresh the page and try again.');
        }

        $customerMode = trim((string) ($_POST['customer_mode'] ?? 'retail'));
        $customerIdValue = $_POST['customer_id'] ?? null;
        $paymentChoice = trim((string) ($_POST['payment_method'] ?? 'Cash'));
        $saveMode = trim((string) ($_POST['save_mode'] ?? 'paid'));
        $saleItemsJson = (string) ($_POST['sale_items'] ?? '');

        if (!in_array($customerMode, ['retail', 'wholesale'], true)) {
            throw new Exception('Please choose a valid customer type.');
        }

        if (!in_array($saveMode, ['paid', 'due'], true)) {
            throw new Exception('Please choose a valid sale action.');
        }

        $paymentMap = [
            'Cash' => 'Cash',
            'Bkash' => 'Mobile Banking',
            'Card' => 'Card',
            'Mobile Banking' => 'Mobile Banking',
            'Due' => 'Cash'
        ];

        if (!array_key_exists($paymentChoice, $paymentMap)) {
            throw new Exception('Please choose a valid payment method.');
        }

        if ($paymentChoice === 'Due') {
            $saveMode = 'due';
        }

        $customerId = null;

        if ($customerMode === 'wholesale') {
            if (
                $customerIdValue === null ||
                filter_var($customerIdValue, FILTER_VALIDATE_INT) === false ||
                (int) $customerIdValue <= 0
            ) {
                throw new Exception('Please select a wholesale customer.');
            }

            $customerId = (int) $customerIdValue;
        } elseif ($saveMode === 'due') {
            throw new Exception('A walk-in retail sale cannot be saved as due. Select a wholesale customer first.');
        }

        if ($saleItemsJson === '') {
            throw new Exception('Please add at least one product to the sale.');
        }

        $decodedItems = json_decode($saleItemsJson, true);

        if (!is_array($decodedItems) || empty($decodedItems)) {
            throw new Exception('Please add at least one valid sale item.');
        }

        $requestedItems = [];
        $usedProductIds = [];

        foreach ($decodedItems as $index => $item) {
            if (!is_array($item)) {
                throw new Exception('Invalid sale item data.');
            }

            $productIdValue = $item['product_id'] ?? null;
            $quantityValue = $item['quantity'] ?? null;

            if (
                $productIdValue === null ||
                filter_var($productIdValue, FILTER_VALIDATE_INT) === false ||
                (int) $productIdValue <= 0
            ) {
                throw new Exception('Invalid product in sale item ' . ((int) $index + 1) . '.');
            }

            $productId = (int) $productIdValue;

            if (isset($usedProductIds[$productId])) {
                throw new Exception('The same product cannot appear more than once in a sale.');
            }

            if (
                $quantityValue === null ||
                $quantityValue === '' ||
                !is_numeric($quantityValue)
            ) {
                throw new Exception('Quantity must be a positive whole number.');
            }

            $quantityNumber = (float) $quantityValue;

            if (
                !is_finite($quantityNumber) ||
                $quantityNumber <= 0 ||
                floor($quantityNumber) !== $quantityNumber ||
                $quantityNumber > 2147483647
            ) {
                throw new Exception('Quantity must be a valid positive whole number.');
            }

            $usedProductIds[$productId] = true;
            $requestedItems[] = [
                'product_id' => $productId,
                'quantity' => (int) $quantityNumber
            ];
        }

        if (!mysqli_begin_transaction($conn)) {
            throw new Exception('Unable to start the sale transaction.');
        }

        $transactionStarted = true;

        $customer = null;

        if ($customerMode === 'wholesale') {
            $customerStmt = mysqli_prepare(
                $conn,
                "
                    SELECT
                        customer_id,
                        customer_name,
                        phone,
                        email,
                        address,
                        customer_type,
                        account_status,
                        total_due
                    FROM customers
                    WHERE customer_id = ?
                    LIMIT 1
                    FOR UPDATE
                "
            );

            if (!$customerStmt) {
                throw new Exception('Unable to prepare customer verification.');
            }

            mysqli_stmt_bind_param($customerStmt, 'i', $customerId);

            if (!mysqli_stmt_execute($customerStmt)) {
                mysqli_stmt_close($customerStmt);
                throw new Exception('Unable to verify the selected customer.');
            }

            $customerResult = mysqli_stmt_get_result($customerStmt);
            $customer = $customerResult ? mysqli_fetch_assoc($customerResult) : null;
            mysqli_stmt_close($customerStmt);

            if (!$customer) {
                throw new Exception('The selected wholesale customer no longer exists.');
            }

            if ($customer['customer_type'] !== 'Wholesale') {
                throw new Exception('The selected customer is not a wholesale customer.');
            }

            if ($customer['account_status'] !== 'Active') {
                throw new Exception('The selected wholesale customer is inactive.');
            }
        }

        $productStmt = mysqli_prepare(
            $conn,
            "
                SELECT
                    product_id,
                    product_name,
                    barcode,
                    unit,
                    selling_price,
                    stock,
                    status
                FROM products
                WHERE product_id = ?
                LIMIT 1
                FOR UPDATE
            "
        );

        if (!$productStmt) {
            throw new Exception('Unable to prepare product stock verification.');
        }

        $validatedItems = [];
        $calculatedTotal = 0.00;

        foreach ($requestedItems as $requestedItem) {
            $productId = $requestedItem['product_id'];
            $quantity = $requestedItem['quantity'];

            mysqli_stmt_bind_param($productStmt, 'i', $productId);

            if (!mysqli_stmt_execute($productStmt)) {
                mysqli_stmt_close($productStmt);
                throw new Exception('Unable to verify one of the selected products.');
            }

            $productResult = mysqli_stmt_get_result($productStmt);
            $product = $productResult ? mysqli_fetch_assoc($productResult) : null;

            if (!$product) {
                mysqli_stmt_close($productStmt);
                throw new Exception('One of the selected products no longer exists.');
            }

            if ($product['status'] !== 'Active') {
                mysqli_stmt_close($productStmt);
                throw new Exception($product['product_name'] . ' is inactive and cannot be sold.');
            }

            $stock = (int) $product['stock'];

            if ($stock < $quantity) {
                mysqli_stmt_close($productStmt);
                throw new Exception(
                    $product['product_name'] . ' has only ' . $stock . ' ' . $product['unit'] . ' available.'
                );
            }

            $sellingPrice = round((float) $product['selling_price'], 2);

            if ($sellingPrice < 0 || $sellingPrice > 99999999.99) {
                mysqli_stmt_close($productStmt);
                throw new Exception('Invalid selling price for ' . $product['product_name'] . '.');
            }

            $subtotal = round($sellingPrice * $quantity, 2);

            if ($subtotal > 99999999.99) {
                mysqli_stmt_close($productStmt);
                throw new Exception('The line total for ' . $product['product_name'] . ' is too large.');
            }

            $calculatedTotal = round($calculatedTotal + $subtotal, 2);

            if ($calculatedTotal > 99999999.99) {
                mysqli_stmt_close($productStmt);
                throw new Exception('The sale total is too large.');
            }

            $validatedItems[] = [
                'product_id' => $productId,
                'product_name' => (string) $product['product_name'],
                'barcode' => (string) ($product['barcode'] ?? ''),
                'unit' => (string) $product['unit'],
                'quantity' => $quantity,
                'selling_price' => $sellingPrice,
                'subtotal' => $subtotal,
                'stock_before' => $stock
            ];
        }

        mysqli_stmt_close($productStmt);

        if ($calculatedTotal <= 0) {
            throw new Exception('The sale total must be greater than zero.');
        }

        $invoiceNo = generateSaleInvoiceNo($conn);
        $saleDate = date('Y-m-d');
        $databasePaymentMethod = $paymentMap[$paymentChoice];

        if ($saveMode === 'due') {
            $paidAmount = 0.00;
            $dueAmount = $calculatedTotal;
        } else {
            $paidAmount = $calculatedTotal;
            $dueAmount = 0.00;
        }

        $saleInsertStmt = mysqli_prepare(
            $conn,
            "
                INSERT INTO sales
                (
                    customer_id,
                    invoice_no,
                    sale_date,
                    total_amount,
                    paid_amount,
                    due_amount,
                    payment_method
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            "
        );

        if (!$saleInsertStmt) {
            throw new Exception('Unable to prepare the sale record.');
        }

        mysqli_stmt_bind_param(
            $saleInsertStmt,
            'issddds',
            $customerId,
            $invoiceNo,
            $saleDate,
            $calculatedTotal,
            $paidAmount,
            $dueAmount,
            $databasePaymentMethod
        );

        if (!mysqli_stmt_execute($saleInsertStmt)) {
            $dbError = mysqli_stmt_error($saleInsertStmt);
            mysqli_stmt_close($saleInsertStmt);
            throw new Exception('Unable to save the sale. ' . $dbError);
        }

        $saleId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($saleInsertStmt);

        if ($saleId <= 0) {
            throw new Exception('Unable to determine the new sale ID.');
        }

        $saleItemStmt = mysqli_prepare(
            $conn,
            "
                INSERT INTO sale_items
                (sale_id, product_id, quantity, selling_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            "
        );

        $stockUpdateStmt = mysqli_prepare(
            $conn,
            "
                UPDATE products
                SET stock = stock - ?
                WHERE product_id = ? AND stock >= ?
            "
        );

        if (!$saleItemStmt || !$stockUpdateStmt) {
            if ($saleItemStmt) {
                mysqli_stmt_close($saleItemStmt);
            }
            if ($stockUpdateStmt) {
                mysqli_stmt_close($stockUpdateStmt);
            }
            throw new Exception('Unable to prepare sale item stock updates.');
        }

        foreach ($validatedItems as $validatedItem) {
            $productId = $validatedItem['product_id'];
            $quantity = $validatedItem['quantity'];
            $sellingPrice = $validatedItem['selling_price'];
            $subtotal = $validatedItem['subtotal'];

            mysqli_stmt_bind_param(
                $saleItemStmt,
                'iiidd',
                $saleId,
                $productId,
                $quantity,
                $sellingPrice,
                $subtotal
            );

            if (!mysqli_stmt_execute($saleItemStmt)) {
                mysqli_stmt_close($saleItemStmt);
                mysqli_stmt_close($stockUpdateStmt);
                throw new Exception('Unable to save one of the sale items.');
            }

            mysqli_stmt_bind_param(
                $stockUpdateStmt,
                'iii',
                $quantity,
                $productId,
                $quantity
            );

            if (!mysqli_stmt_execute($stockUpdateStmt)) {
                mysqli_stmt_close($saleItemStmt);
                mysqli_stmt_close($stockUpdateStmt);
                throw new Exception('Unable to update stock for one of the sold products.');
            }

            if (mysqli_stmt_affected_rows($stockUpdateStmt) !== 1) {
                mysqli_stmt_close($saleItemStmt);
                mysqli_stmt_close($stockUpdateStmt);
                throw new Exception('Stock changed while saving. Please review the sale and try again.');
            }
        }

        mysqli_stmt_close($saleItemStmt);
        mysqli_stmt_close($stockUpdateStmt);

        if ($customerMode === 'wholesale' && $dueAmount > 0) {
            $customerDueStmt = mysqli_prepare(
                $conn,
                'UPDATE customers SET total_due = total_due + ? WHERE customer_id = ?'
            );

            if (!$customerDueStmt) {
                throw new Exception('Unable to prepare the customer due update.');
            }

            mysqli_stmt_bind_param($customerDueStmt, 'di', $dueAmount, $customerId);

            if (!mysqli_stmt_execute($customerDueStmt) || mysqli_stmt_affected_rows($customerDueStmt) !== 1) {
                mysqli_stmt_close($customerDueStmt);
                throw new Exception('Unable to update the customer outstanding due.');
            }

            mysqli_stmt_close($customerDueStmt);
        }

        if ($customerMode === 'wholesale' && $paidAmount > 0) {
            $paymentStmt = mysqli_prepare(
                $conn,
                "
                    INSERT INTO payments
                    (sale_id, customer_id, payment_date, amount, payment_method, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                "
            );

            if (!$paymentStmt) {
                throw new Exception('Unable to prepare the sale payment record.');
            }

            $paymentNotes = 'Payment received when sale was created.';

            mysqli_stmt_bind_param(
                $paymentStmt,
                'iisdss',
                $saleId,
                $customerId,
                $saleDate,
                $paidAmount,
                $databasePaymentMethod,
                $paymentNotes
            );

            if (!mysqli_stmt_execute($paymentStmt)) {
                mysqli_stmt_close($paymentStmt);
                throw new Exception('Unable to save the sale payment record.');
            }

            mysqli_stmt_close($paymentStmt);
        }

        if (!mysqli_commit($conn)) {
            throw new Exception('Unable to complete the sale transaction.');
        }

        $transactionStarted = false;

        $settings = [
            'shop_name' => 'GrocerEase',
            'owner_name' => '',
            'phone' => '',
            'email' => '',
            'address' => ''
        ];

        $settingsResult = mysqli_query(
            $conn,
            "
                SELECT shop_name, owner_name, phone, email, address
                FROM settings
                ORDER BY setting_id ASC
                LIMIT 1
            "
        );

        if ($settingsResult && ($settingsRow = mysqli_fetch_assoc($settingsResult))) {
            $settings = array_merge($settings, $settingsRow);
        }

        $customerLabel = $customerMode === 'wholesale' && $customer
            ? (string) $customer['customer_name']
            : 'Walk-in Customer';

        $invoiceItems = array_map(
            static function (array $item): array {
                return [
                    'product_name' => $item['product_name'],
                    'barcode' => $item['barcode'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'selling_price' => number_format($item['selling_price'], 2, '.', ''),
                    'subtotal' => number_format($item['subtotal'], 2, '.', '')
                ];
            },
            $validatedItems
        );

        saleJsonResponse([
            'success' => true,
            'message' => $saveMode === 'due'
                ? 'Sale saved as due successfully. Stock and customer due were updated.'
                : 'Sale confirmed successfully and product stock was updated.',
            'sale_id' => $saleId,
            'invoice_no' => $invoiceNo,
            'sale_date' => $saleDate,
            'total_amount' => number_format($calculatedTotal, 2, '.', ''),
            'paid_amount' => number_format($paidAmount, 2, '.', ''),
            'due_amount' => number_format($dueAmount, 2, '.', ''),
            'payment_label' => $saveMode === 'due' ? 'Due' : $paymentChoice,
            'customer_name' => $customerLabel,
            'customer_phone' => $customer['phone'] ?? '',
            'shop' => $settings,
            'items' => $invoiceItems
        ]);
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            mysqli_rollback($conn);
        }

        saleJsonResponse([
            'success' => false,
            'message' => $exception->getMessage()
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| PAGE DATA
|--------------------------------------------------------------------------
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
            selling_price,
            stock,
            minimum_stock,
            status
        FROM products
        WHERE status = 'Active'
        ORDER BY product_name ASC
    "
);

if ($productResult) {
    while ($row = mysqli_fetch_assoc($productResult)) {
        $products[] = $row;
    }
}

$wholesaleCustomers = [];
$customerResult = mysqli_query(
    $conn,
    "
        SELECT
            customer_id,
            customer_name,
            phone,
            email,
            address,
            total_due
        FROM customers
        WHERE customer_type = 'Wholesale'
          AND account_status = 'Active'
        ORDER BY customer_name ASC
    "
);

if ($customerResult) {
    while ($row = mysqli_fetch_assoc($customerResult)) {
        $wholesaleCustomers[] = $row;
    }
}

$productData = [];
foreach ($products as $product) {
    $productData[] = [
        'product_id' => (int) $product['product_id'],
        'product_name' => (string) $product['product_name'],
        'barcode' => (string) ($product['barcode'] ?? ''),
        'unit' => (string) $product['unit'],
        'selling_price' => (float) $product['selling_price'],
        'stock' => (int) $product['stock'],
        'minimum_stock' => (int) $product['minimum_stock'],
        'status' => (string) $product['status']
    ];
}

$customerData = [];
foreach ($wholesaleCustomers as $customerRow) {
    $customerData[] = [
        'customer_id' => (int) $customerRow['customer_id'],
        'customer_name' => (string) $customerRow['customer_name'],
        'phone' => (string) ($customerRow['phone'] ?? ''),
        'email' => (string) ($customerRow['email'] ?? ''),
        'address' => (string) ($customerRow['address'] ?? ''),
        'total_due' => (float) $customerRow['total_due']
    ];
}

require_once '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<link rel="stylesheet" href="../../assets/css/sales.css">

<div class="app-layout">
    <aside class="app-sidebar-slot">
        <?php require_once '../../includes/sidebar.php'; ?>
    </aside>

    <div class="app-main-slot">
        <header class="app-topbar-slot">
            <?php require_once '../../includes/topbar.php'; ?>
        </header>

        <main class="dashboard-main-content">
            <div class="sale-page">
                <div class="sale-heading">
                    <div>
                        <h1>Record New Sale</h1>
                        <p>Create a new retail or wholesale sale.</p>
                    </div>
                </div>

                <div id="saleMessage" class="sale-message" role="status" aria-live="polite"></div>

                <section class="sale-customer-section" aria-labelledby="customerTypeHeading">
                    <span class="sale-section-label" id="customerTypeHeading">Customer Type</span>

                    <div class="sale-customer-type-buttons" role="group" aria-label="Customer type">
                        <button type="button" class="sale-type-button active" data-mode="retail" id="retailModeButton">
                            Walk-in (Retail)
                        </button>
                        <button type="button" class="sale-type-button" data-mode="wholesale" id="wholesaleModeButton">
                            Wholesale Customer
                        </button>
                    </div>

                    <div class="sale-wholesale-picker" id="wholesalePicker" hidden>
                        <label for="customer_id">Select Wholesale Customer</label>
                        <select id="customer_id" name="customer_id">
                            <option value="">Choose customer...</option>
                            <?php foreach ($wholesaleCustomers as $customerRow): ?>
                                <option value="<?php echo (int) $customerRow['customer_id']; ?>">
                                    <?php
                                    $customerLabel = (string) $customerRow['customer_name'];
                                    if (!empty($customerRow['phone'])) {
                                        $customerLabel .= ' - ' . $customerRow['phone'];
                                    }
                                    echo htmlspecialchars($customerLabel, ENT_QUOTES, 'UTF-8');
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="sale-customer-meta" id="customerMeta"></div>
                    </div>
                </section>

                <section class="sale-entry-card" aria-labelledby="saleItemsHeading">
                    <div class="sale-product-controls">
                        <div class="sale-product-search-wrap">
                            <label class="sr-only" for="product_search">Search product</label>
                            <input
                                type="text"
                                id="product_search"
                                list="saleProductOptions"
                                placeholder="Search product..."
                                autocomplete="off"
                            >
                            <datalist id="saleProductOptions">
                                <?php foreach ($products as $product): ?>
                                    <?php
                                    $label = (string) $product['product_name'];
                                    if (!empty($product['barcode'])) {
                                        $label .= ' - ' . $product['barcode'];
                                    }
                                    ?>
                                    <option value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" id="selected_product_id" value="">
                            <div class="sale-product-hint" id="selectedProductHint">
                                Search by product name or barcode.
                            </div>
                        </div>

                        <div class="sale-quantity-wrap">
                            <label class="sr-only" for="product_quantity">Quantity</label>
                            <input type="number" id="product_quantity" min="1" step="1" value="1" aria-label="Quantity">
                        </div>

                        <button type="button" class="sale-add-button" id="addSaleProductButton">+ Add</button>
                    </div>

                    <div class="sale-table-wrap">
                        <table class="sale-items-table" aria-labelledby="saleItemsHeading">
                            <thead>
                                <tr>
                                    <th id="saleItemsHeading">Product</th>
                                    <th>Qty</th>
                                    <th>Unit</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                    <th class="sale-delete-heading">Delete</th>
                                </tr>
                            </thead>
                            <tbody id="saleItemsTableBody">
                                <tr class="sale-empty-row">
                                    <td colspan="6">
                                        <span>No products added yet.</span>
                                        Search for a product above to start the sale.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="sale-payment-card" aria-labelledby="paymentHeading">
                    <div class="sale-payment-left">
                        <h2 id="paymentHeading">Payment Method</h2>
                        <div class="sale-payment-methods" role="group" aria-label="Payment method">
                            <button type="button" class="sale-payment-button cash active" data-payment="Cash">Cash</button>
                            <button type="button" class="sale-payment-button bkash" data-payment="Bkash">Bkash</button>
                            <button type="button" class="sale-payment-button card" data-payment="Card">Card</button>
                            <button type="button" class="sale-payment-button due" data-payment="Due">Due</button>
                        </div>
                        <p class="sale-payment-note" id="paymentNote">Walk-in retail sales must be paid at checkout.</p>
                    </div>

                    <div class="sale-total-area">
                        <span>Total Amount</span>
                        <strong id="saleTotal">৳0.00</strong>

                        <div class="sale-actions">
                            <button type="button" class="sale-due-button" id="saveDueButton" disabled>Save as due</button>
                            <button type="button" class="sale-confirm-button" id="confirmSaleButton">✓ Confirm &amp; Generate Invoice</button>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<script>
window.GrocerEaseSaleData = <?php echo json_encode([
    'products' => $productData,
    'customers' => $customerData,
    'csrfToken' => $csrfToken,
    'endpoint' => $basePath . '/modules/sales/index.php'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="../../assets/js/sales.js"></script>
<script src="../../assets/js/sidebar.js"></script>
