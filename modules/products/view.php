<?php

session_start();

/*
|--------------------------------------------------------------------------
| VIEW PRODUCT PAGE
|--------------------------------------------------------------------------
| Step 5D
| Displays an existing product in read-only mode.
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
$pageTitle = "View Product";

/*
|--------------------------------------------------------------------------
| PRODUCT ID
|--------------------------------------------------------------------------
*/

$productId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($productId === false || $productId === null) {
    header("Location: {$basePath}/modules/products/index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$product = null;

$loadProductStmt = mysqli_prepare(
    $conn,
    "
        SELECT
            p.product_id,
            p.category_id,
            p.supplier_id,
            p.product_name,
            p.barcode,
            p.unit,
            p.purchase_price,
            p.selling_price,
            p.stock,
            p.minimum_stock,
            p.expiry_date,
            p.image,
            p.status,
            p.created_at,
            c.category_name,
            s.supplier_name,
            s.company AS supplier_company
        FROM products p
        INNER JOIN categories c
            ON p.category_id = c.category_id
        LEFT JOIN suppliers s
            ON p.supplier_id = s.supplier_id
        WHERE p.product_id = ?
        LIMIT 1
    "
);

if ($loadProductStmt) {
    mysqli_stmt_bind_param($loadProductStmt, "i", $productId);
    mysqli_stmt_execute($loadProductStmt);

    $result = mysqli_stmt_get_result($loadProductStmt);
    $product = $result ? mysqli_fetch_assoc($result) : null;

    mysqli_stmt_close($loadProductStmt);
}

if (!$product) {
    header("Location: {$basePath}/modules/products/index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$supplierLabel = "No Supplier";

if (!empty($product['supplier_name'])) {
    $supplierLabel = $product['supplier_name'];

    if (!empty($product['supplier_company'])) {
        $supplierLabel .= " — " . $product['supplier_company'];
    }
}

$imagePath = '';

if (!empty($product['image'])) {
    $imagePath = '../../' . ltrim($product['image'], '/');
}

$stock = (int) $product['stock'];
$minimumStock = (int) $product['minimum_stock'];

$stockClass = $stock <= $minimumStock
    ? 'view-product-stock-low'
    : 'view-product-stock-normal';

$isActive = $product['status'] === 'Active';

require_once "../../includes/header.php";

?>

<!-- =====================================================
     SHARED APPLICATION CSS
     ===================================================== -->

<link
    rel="stylesheet"
    href="../../assets/css/layout.css"
>

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

<style>
    .view-product-page {
        padding: 28px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .view-product-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .view-product-heading h1 {
        margin: 0 0 8px;
    }

    .view-product-heading p {
        margin: 0;
    }

    .view-product-back,
    .view-product-edit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        border-radius: 8px;
        padding: 10px 16px;
        font-weight: 600;
    }

    .view-product-back {
        border: 1px solid #d1d5db;
    }

    .view-product-card {
        background: #fff;
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    }

    .view-product-main {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 32px;
    }

    .view-product-image-box {
        min-height: 260px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        overflow: hidden;
        background: #f8fafc;
    }

    .view-product-image {
        width: 100%;
        height: 260px;
        object-fit: contain;
    }

    .view-product-image-placeholder {
        width: 100%;
        height: 260px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 56px;
        font-weight: 700;
        color: #64748b;
        background: #f1f5f9;
    }

    .view-product-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .view-product-field {
        padding: 16px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
    }

    .view-product-field.full-width {
        grid-column: 1 / -1;
    }

    .view-product-label {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
    }

    .view-product-value {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        word-break: break-word;
    }

    .view-product-stock-low {
        color: #b91c1c;
    }

    .view-product-stock-normal {
        color: #15803d;
    }

    .view-product-status {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
    }

    .view-product-status.active {
        color: #15803d;
        background: #dcfce7;
    }

    .view-product-status.inactive {
        color: #b91c1c;
        background: #fee2e2;
    }

    .view-product-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 24px;
    }

    .view-product-edit {
        background: #2563eb;
        color: #fff;
    }

    @media (max-width: 800px) {
        .view-product-main {
            grid-template-columns: 1fr;
        }

        .view-product-details {
            grid-template-columns: 1fr;
        }

        .view-product-header {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="app-layout">

    <aside class="app-sidebar-slot">
        <?php require_once "../../includes/sidebar.php"; ?>
    </aside>

    <div class="app-main-slot">

        <header class="app-topbar-slot">
            <?php require_once "../../includes/topbar.php"; ?>
        </header>

        <main class="dashboard-main-content">

            <div class="view-product-page">

                <div class="view-product-header">

                    <div class="view-product-heading">
                        <h1>View Product</h1>
                        <p>View product information and inventory details.</p>
                    </div>

                    <a
                        href="<?php echo e($basePath); ?>/modules/products/index.php"
                        class="view-product-back"
                    >
                        Back to Product List
                    </a>

                </div>

                <div class="view-product-card">

                    <div class="view-product-main">

                        <div class="view-product-image-box">

                            <?php if ($imagePath !== ''): ?>

                                <img
                                    src="<?php echo e($imagePath); ?>"
                                    alt="<?php echo e($product['product_name']); ?>"
                                    class="view-product-image"
                                    onerror="
                                        this.style.display='none';
                                        this.nextElementSibling.style.display='flex';
                                    "
                                >

                                <div
                                    class="view-product-image-placeholder"
                                    style="display:none;"
                                >
                                    P
                                </div>

                            <?php else: ?>

                                <div class="view-product-image-placeholder">
                                    P
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="view-product-details">

                            <div class="view-product-field full-width">
                                <span class="view-product-label">Product Name</span>
                                <div class="view-product-value">
                                    <?php echo e($product['product_name']); ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Product ID</span>
                                <div class="view-product-value">
                                    <?php echo (int) $product['product_id']; ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Category</span>
                                <div class="view-product-value">
                                    <?php echo e($product['category_name']); ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Supplier</span>
                                <div class="view-product-value">
                                    <?php echo e($supplierLabel); ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Barcode</span>
                                <div class="view-product-value">
                                    <?php
                                    echo !empty($product['barcode'])
                                        ? e($product['barcode'])
                                        : '—';
                                    ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Unit</span>
                                <div class="view-product-value">
                                    <?php echo e($product['unit']); ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Purchase Price</span>
                                <div class="view-product-value">
                                    ৳<?php
                                    echo number_format(
                                        (float) $product['purchase_price'],
                                        2
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Selling Price</span>
                                <div class="view-product-value">
                                    ৳<?php
                                    echo number_format(
                                        (float) $product['selling_price'],
                                        2
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Stock</span>
                                <div class="view-product-value <?php echo $stockClass; ?>">
                                    <?php echo number_format($stock); ?>
                                    <?php echo e($product['unit']); ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Minimum Stock</span>
                                <div class="view-product-value">
                                    <?php echo number_format($minimumStock); ?>
                                    <?php echo e($product['unit']); ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Expiry Date</span>
                                <div class="view-product-value">
                                    <?php
                                    echo !empty($product['expiry_date'])
                                        ? e(date(
                                            'd M Y',
                                            strtotime($product['expiry_date'])
                                        ))
                                        : '—';
                                    ?>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Status</span>
                                <div class="view-product-value">
                                    <span class="view-product-status <?php echo $isActive ? 'active' : 'inactive'; ?>">
                                        <?php echo e($product['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="view-product-field">
                                <span class="view-product-label">Created At</span>
                                <div class="view-product-value">
                                    <?php echo e($product['created_at']); ?>
                                </div>
                            </div>

                        </div>

                    </div>

                    <div class="view-product-actions">

                        <a
                            href="<?php echo e($basePath); ?>/modules/products/index.php"
                            class="view-product-back"
                        >
                            Back to Product List
                        </a>

                        <a
                            href="<?php echo e($basePath); ?>/modules/products/edit.php?id=<?php echo (int) $product['product_id']; ?>"
                            class="view-product-edit"
                        >
                            Edit Product
                        </a>

                    </div>

                </div>

            </div>

        </main>

    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
