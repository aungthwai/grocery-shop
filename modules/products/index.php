<?php

session_start();

/*
|--------------------------------------------------------------------------
| PRODUCT LIST PAGE
|--------------------------------------------------------------------------
| Step 5A
| Product listing, routing and filtering.
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
| BASE PATH
|--------------------------------------------------------------------------
| IMPORTANT:
| The actual project folder is:
| /grocery-shop
|--------------------------------------------------------------------------
*/

$basePath = "/grocery-shop";


/*
|--------------------------------------------------------------------------
| PAGE INFORMATION
|--------------------------------------------------------------------------
*/

$pageTitle = "Product List";


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER VALUES
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$categoryFilter = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;

$statusFilter = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';


/*
|--------------------------------------------------------------------------
| VALIDATE STATUS
|--------------------------------------------------------------------------
| Only the statuses that actually exist in the products table
| are accepted.
|--------------------------------------------------------------------------
*/

if (
    $statusFilter !== 'Active' &&
    $statusFilter !== 'Inactive'
) {

    $statusFilter = '';

}


/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

$categorySql = "
    SELECT
        category_id,
        category_name
    FROM categories
    ORDER BY category_name ASC
";

$categoryResult = mysqli_query(
    $conn,
    $categorySql
);

if ($categoryResult) {

    while (
        $row = mysqli_fetch_assoc($categoryResult)
    ) {

        $categories[] = $row;

    }

}


/*
|--------------------------------------------------------------------------
| BUILD PRODUCT QUERY
|--------------------------------------------------------------------------
| Existing database tables:
|
| products
| categories
| suppliers
|
| Existing product columns are preserved.
|--------------------------------------------------------------------------
*/

$products = [];


/*
|--------------------------------------------------------------------------
| BASE PRODUCT QUERY
|--------------------------------------------------------------------------
*/

$sql = "
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
        p.image,
        p.status,
        p.created_at,

        c.category_name,

        s.supplier_name

    FROM products p

    INNER JOIN categories c
        ON p.category_id = c.category_id

    LEFT JOIN suppliers s
        ON p.supplier_id = s.supplier_id

    WHERE 1 = 1
";


/*
|--------------------------------------------------------------------------
| SEARCH FILTER
|--------------------------------------------------------------------------
| Search supports:
|
| 1. Product ID
| 2. Product Name
| 3. Category Name
| 4. Barcode
|
| Barcode is retained because the existing UI already describes
| the search field as "product name or barcode".
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $searchEscaped = mysqli_real_escape_string(
        $conn,
        $search
    );


    /*
    |--------------------------------------------------------------
    | Product ID search
    |--------------------------------------------------------------
    | Product ID is numeric, so only add the direct ID condition
    | when the search value is numeric.
    |--------------------------------------------------------------
    */

    if (ctype_digit($search)) {

        $productId = (int) $search;

        $sql .= "
            AND (
                p.product_id = {$productId}
                OR p.product_name LIKE '%{$searchEscaped}%'
                OR c.category_name LIKE '%{$searchEscaped}%'
                OR p.barcode LIKE '%{$searchEscaped}%'
            )
        ";

    } else {

        $sql .= "
            AND (
                p.product_name LIKE '%{$searchEscaped}%'
                OR c.category_name LIKE '%{$searchEscaped}%'
                OR p.barcode LIKE '%{$searchEscaped}%'
            )
        ";

    }

}


/*
|--------------------------------------------------------------------------
| CATEGORY FILTER
|--------------------------------------------------------------------------
*/

if ($categoryFilter > 0) {

    $sql .= "
        AND p.category_id = {$categoryFilter}
    ";

}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($statusFilter !== '') {

    $statusEscaped = mysqli_real_escape_string(
        $conn,
        $statusFilter
    );

    $sql .= "
        AND p.status = '{$statusEscaped}'
    ";

}


/*
|--------------------------------------------------------------------------
| PRODUCT ORDER
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY p.product_id DESC
";


/*
|--------------------------------------------------------------------------
| EXECUTE PRODUCT QUERY
|--------------------------------------------------------------------------
*/

$productResult = mysqli_query(
    $conn,
    $sql
);


/*
|--------------------------------------------------------------------------
| STORE PRODUCTS
|--------------------------------------------------------------------------
*/

if ($productResult) {

    while (
        $row = mysqli_fetch_assoc($productResult)
    ) {

        $products[] = $row;

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


<!-- =====================================================
     PRODUCT PAGE CSS
     ===================================================== -->

<style>

    /* =====================================================
       PRODUCT PAGE
       ===================================================== */

    .products-page {
        width: 100%;
        box-sizing: border-box;
    }


    /* =====================================================
       PAGE HEADER
       ===================================================== */

    .products-page .products-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .products-page .products-heading h1 {
        margin: 0;
        font-size: 28px;
        line-height: 1.25;
        font-weight: 700;
        color: #0f172a;
    }

    .products-page .products-heading p {
        margin: 7px 0 0;
        font-size: 14px;
        line-height: 1.5;
        color: #64748b;
    }


    /* =====================================================
       ADD PRODUCT BUTTON
       ===================================================== */

    .products-page .add-product-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
        padding: 0 17px;
        border: 0;
        border-radius: 9px;
        background: #2563eb;
        color: #ffffff;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;

        transition:
            background-color 0.2s ease,
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }

    .products-page .add-product-button:hover {
        background: #1d4ed8;

        box-shadow:
            0 6px 14px rgba(37, 99, 235, 0.20);

        transform: translateY(-1px);
    }


    /* =====================================================
       FILTER CARD
       ===================================================== */

    .products-page .product-filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 20px;

        box-shadow:
            0 4px 14px rgba(15, 23, 42, 0.05);
    }


    .products-page .product-filter-form {
        display: grid;

        grid-template-columns:
            minmax(220px, 1.6fr)
            minmax(170px, 1fr)
            minmax(150px, 0.8fr)
            auto;

        gap: 12px;

        align-items: end;
    }


    .products-page .filter-field {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }


    .products-page .filter-field label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
    }


    .products-page .filter-field input,
    .products-page .filter-field select {

        width: 100%;
        height: 42px;

        box-sizing: border-box;

        padding: 0 12px;

        border: 1px solid #cbd5e1;
        border-radius: 8px;

        background: #ffffff;
        color: #0f172a;

        font-size: 13px;

        outline: none;

        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease;
    }


    .products-page .filter-field input:focus,
    .products-page .filter-field select:focus {

        border-color: #2563eb;

        box-shadow:
            0 0 0 3px rgba(37, 99, 235, 0.10);
    }


    /* =====================================================
       FILTER BUTTONS
       ===================================================== */

    .products-page .filter-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }


    .products-page .filter-button {

        height: 42px;

        padding: 0 16px;

        border: 0;
        border-radius: 8px;

        background: #2563eb;
        color: #ffffff;

        font-size: 13px;
        font-weight: 600;

        cursor: pointer;
    }


    .products-page .filter-button:hover {
        background: #1d4ed8;
    }


    .products-page .clear-button {

        display: inline-flex;
        align-items: center;
        justify-content: center;

        height: 42px;

        padding: 0 14px;

        border: 1px solid #cbd5e1;
        border-radius: 8px;

        background: #ffffff;
        color: #475569;

        font-size: 13px;
        font-weight: 600;

        text-decoration: none;
    }


    .products-page .clear-button:hover {
        background: #f8fafc;
    }


    /* =====================================================
       PRODUCT TABLE CARD
       ===================================================== */

    .products-page .product-table-card {

        background: #ffffff;

        border: 1px solid #e2e8f0;

        border-radius: 14px;

        overflow: hidden;

        box-shadow:
            0 4px 14px rgba(15, 23, 42, 0.05);
    }


    /* =====================================================
       TABLE HEADER
       ===================================================== */

    .products-page .product-table-header {

        display: flex;
        align-items: center;
        justify-content: space-between;

        padding: 20px 22px;

        border-bottom: 1px solid #e2e8f0;
    }


    .products-page .product-table-title h2 {

        margin: 0;

        font-size: 17px;
        font-weight: 700;

        color: #0f172a;
    }


    .products-page .product-table-title p {

        margin: 5px 0 0;

        font-size: 12px;

        color: #64748b;
    }


    .products-page .product-count {

        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-width: 30px;
        height: 28px;

        padding: 0 9px;

        border-radius: 20px;

        background: #eff6ff;
        color: #2563eb;

        font-size: 12px;
        font-weight: 700;
    }


    /* =====================================================
       TABLE WRAPPER
       ===================================================== */

    .products-page .product-table-wrapper {

        width: 100%;

        overflow-x: auto;
    }


    .products-page .product-table {

        width: 100%;

        min-width: 1050px;

        border-collapse: collapse;
    }


    .products-page .product-table thead {

        background: #f8fafc;
    }


    .products-page .product-table th {

        padding: 13px 16px;

        border-bottom: 1px solid #e2e8f0;

        text-align: left;

        color: #64748b;

        font-size: 11px;
        font-weight: 700;

        text-transform: uppercase;
        letter-spacing: 0.04em;

        white-space: nowrap;
    }


    .products-page .product-table td {

        padding: 15px 16px;

        border-bottom: 1px solid #f1f5f9;

        color: #334155;

        font-size: 13px;

        vertical-align: middle;

        white-space: nowrap;
    }


    .products-page .product-table tbody tr {

        transition:
            background-color 0.15s ease;
    }


    .products-page .product-table tbody tr:hover {

        background: #f8fafc;
    }


    .products-page .product-table tbody tr:last-child td {

        border-bottom: 0;
    }


    /* =====================================================
       PRODUCT NAME
       ===================================================== */

    .products-page .product-name {

        display: flex;
        align-items: center;

        gap: 11px;

        min-width: 190px;
    }


    .products-page .product-image {

        width: 38px;
        height: 38px;

        flex-shrink: 0;

        border-radius: 8px;

        object-fit: cover;

        border: 1px solid #e2e8f0;

        background: #f8fafc;
    }


    .products-page .product-image-placeholder {

        display: flex;
        align-items: center;
        justify-content: center;

        width: 38px;
        height: 38px;

        flex-shrink: 0;

        border-radius: 8px;

        border: 1px solid #dbeafe;

        background: #eff6ff;

        color: #2563eb;

        font-size: 15px;
        font-weight: 700;
    }


    .products-page .product-name-text {

        display: flex;
        flex-direction: column;

        gap: 3px;
    }


    .products-page .product-name-main {

        color: #0f172a;

        font-weight: 600;
    }


    .products-page .product-id {

        color: #94a3b8;

        font-size: 11px;
    }


    /* =====================================================
       PRICE
       ===================================================== */

    .products-page .price {

        color: #0f172a;

        font-weight: 600;
    }


    /* =====================================================
       STOCK
       ===================================================== */

    .products-page .stock-normal {

        color: #15803d;

        font-weight: 600;
    }


    .products-page .stock-low {

        color: #dc2626;

        font-weight: 700;
    }


    /* =====================================================
       STATUS BADGES
       ===================================================== */

    .products-page .status-badge {

        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-width: 66px;

        padding: 5px 10px;

        border-radius: 20px;

        font-size: 11px;
        font-weight: 700;
    }


    .products-page .status-active {

        background: #dcfce7;
        color: #15803d;
    }


    .products-page .status-inactive {

        background: #fee2e2;
        color: #b91c1c;
    }


    /* =====================================================
       ACTION BUTTONS
       ===================================================== */

    .products-page .product-actions {

        display: flex;
        align-items: center;

        gap: 6px;
    }


    .products-page .action-button {

        display: inline-flex;
        align-items: center;
        justify-content: center;

        height: 32px;

        padding: 0 10px;

        border-radius: 7px;

        font-size: 11px;
        font-weight: 600;

        text-decoration: none;

        border: 1px solid #dbe3ed;

        background: #ffffff;
        color: #475569;
    }


    .products-page .action-button:hover {

        background: #f8fafc;

        border-color: #cbd5e1;
    }


    .products-page .edit-action {

        color: #2563eb;

        border-color: #bfdbfe;

        background: #eff6ff;
    }


    /* =====================================================
       EMPTY STATE
       ===================================================== */

    .products-page .empty-state {

        padding: 65px 25px;

        text-align: center;
    }


    .products-page .empty-icon {

        width: 56px;
        height: 56px;

        margin: 0 auto 15px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 14px;

        background: #eff6ff;
        color: #2563eb;

        font-size: 24px;
    }


    .products-page .empty-state h3 {

        margin: 0;

        font-size: 16px;

        color: #0f172a;
    }


    .products-page .empty-state p {

        max-width: 430px;

        margin: 8px auto 20px;

        font-size: 13px;
        line-height: 1.6;

        color: #64748b;
    }


    .products-page .empty-add-button {

        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-height: 40px;

        padding: 0 15px;

        border-radius: 8px;

        background: #2563eb;
        color: #ffffff;

        text-decoration: none;

        font-size: 13px;
        font-weight: 600;
    }


    /* =====================================================
       RESPONSIVE
       ===================================================== */

    @media (max-width: 1050px) {

        .products-page .product-filter-form {

            grid-template-columns:
                1fr
                1fr;
        }

        .products-page .filter-actions {

            justify-content: flex-start;
        }

    }


    @media (max-width: 700px) {

        .products-page .products-header {

            flex-direction: column;

            align-items: stretch;
        }

        .products-page .add-product-button {

            width: 100%;
        }

        .products-page .product-filter-form {

            grid-template-columns: 1fr;
        }

        .products-page .filter-actions {

            width: 100%;
        }

        .products-page .filter-button,
        .products-page .clear-button {

            flex: 1;
        }

    }

</style>


<!-- ========================================================
     APPLICATION LAYOUT
     ======================================================== -->

<div class="app-layout">


    <!-- ====================================================
         SIDEBAR
         ==================================================== -->

    <aside class="app-sidebar-slot">

        <?php
        require_once "../../includes/sidebar.php";
        ?>

    </aside>


    <!-- ====================================================
         MAIN APPLICATION AREA
         ==================================================== -->

    <div class="app-main-slot">


        <!-- ====================================================
             TOP NAVBAR
             ==================================================== -->

        <header class="app-topbar-slot">

            <?php
            require_once "../../includes/topbar.php";
            ?>

        </header>


        <!-- ====================================================
             MAIN CONTENT
             ==================================================== -->

        <main class="dashboard-main-content">


            <div class="products-page">


                <!-- =================================================
                     PAGE HEADER
                ================================================== -->

                <div class="products-header">


                    <div class="products-heading">

                        <h1>
                            Product List
                        </h1>

                        <p>
                            Manage your products, pricing, stock and availability.
                        </p>

                    </div>


                    <!-- =================================================
                         ADD PRODUCT
                         Prepared for Step 5B
                    ================================================== -->

                    <a
                        href="<?php echo $basePath; ?>/modules/products/add.php"
                        class="add-product-button"
                    >

                        <span aria-hidden="true">
                            +
                        </span>

                        Add Product

                    </a>


                </div>


                <!-- =================================================
                     FILTER CARD
                ================================================== -->

                <section class="product-filter-card">


                    <form
                        method="GET"
                        action="<?php echo $basePath; ?>/modules/products/index.php"
                        class="product-filter-form"
                    >


                        <!-- SEARCH -->

                        <div class="filter-field">

                            <label for="product-search">
                                Search Product
                            </label>

                            <input
                                type="text"
                                id="product-search"
                                name="search"
                                value="<?php echo e($search); ?>"
                                placeholder="Search by product name or barcode..."
                            >

                        </div>


                        <!-- CATEGORY -->

                        <div class="filter-field">

                            <label for="category-filter">
                                Category
                            </label>

                            <select
                                id="category-filter"
                                name="category"
                            >

                                <option value="0">
                                    All Categories
                                </option>


                                <?php foreach ($categories as $category): ?>

                                    <option
                                        value="<?php echo (int) $category['category_id']; ?>"
                                        <?php
                                        echo (
                                            $categoryFilter ===
                                            (int) $category['category_id']
                                        )
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >

                                        <?php
                                        echo e(
                                            $category['category_name']
                                        );
                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- STATUS -->

                        <div class="filter-field">

                            <label for="status-filter">
                                Status
                            </label>

                            <select
                                id="status-filter"
                                name="status"
                            >

                                <option value="">
                                    All Status
                                </option>


                                <option
                                    value="Active"
                                    <?php
                                    echo $statusFilter === 'Active'
                                        ? 'selected'
                                        : '';
                                    ?>
                                >
                                    Active
                                </option>


                                <option
                                    value="Inactive"
                                    <?php
                                    echo $statusFilter === 'Inactive'
                                        ? 'selected'
                                        : '';
                                    ?>
                                >
                                    Inactive
                                </option>

                            </select>

                        </div>


                        <!-- ACTIONS -->

                        <div class="filter-actions">

                            <button
                                type="submit"
                                class="filter-button"
                            >
                                Filter
                            </button>


                            <a
                                href="<?php echo $basePath; ?>/modules/products/index.php"
                                class="clear-button"
                            >
                                Clear
                            </a>

                        </div>


                    </form>


                </section>


                <!-- =================================================
                     PRODUCT TABLE
                ================================================== -->

                <section class="product-table-card">


                    <!-- TABLE HEADER -->

                    <div class="product-table-header">


                        <div class="product-table-title">

                            <h2>
                                Products
                            </h2>

                            <p>
                                All products currently registered in the system.
                            </p>

                        </div>


                        <span class="product-count">

                            <?php
                            echo number_format(
                                count($products)
                            );
                            ?>

                        </span>


                    </div>


                    <?php if (count($products) > 0): ?>


                        <!-- =================================================
                             TABLE
                        ================================================== -->

                        <div class="product-table-wrapper">


                            <table class="product-table">


                                <thead>

                                    <tr>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            Barcode
                                        </th>

                                        <th>
                                            Category
                                        </th>

                                        <th>
                                            Supplier
                                        </th>

                                        <th>
                                            Purchase Price
                                        </th>

                                        <th>
                                            Selling Price
                                        </th>

                                        <th>
                                            Stock
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Actions
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                <?php foreach ($products as $product): ?>


                                    <tr>


                                        <!-- PRODUCT -->

                                        <td>

                                            <div class="product-name">


                                                <?php

                                                $imagePath = '';

                                                if (
                                                    !empty(
                                                        $product['image']
                                                    )
                                                ) {

                                                    $imagePath =
                                                        '../../' .
                                                        ltrim(
                                                            $product['image'],
                                                            '/'
                                                        );

                                                }

                                                ?>


                                                <?php if ($imagePath !== ''): ?>

                                                    <img
                                                        src="<?php echo e($imagePath); ?>"
                                                        alt="<?php echo e($product['product_name']); ?>"
                                                        class="product-image"

                                                        onerror="
                                                            this.style.display='none';
                                                            this.nextElementSibling.style.display='flex';
                                                        "
                                                    >


                                                    <div
                                                        class="product-image-placeholder"
                                                        style="display:none;"
                                                    >
                                                        P
                                                    </div>

                                                <?php else: ?>

                                                    <div
                                                        class="product-image-placeholder"
                                                    >
                                                        P
                                                    </div>

                                                <?php endif; ?>


                                                <div class="product-name-text">

                                                    <span class="product-name-main">

                                                        <?php
                                                        echo e(
                                                            $product['product_name']
                                                        );
                                                        ?>

                                                    </span>


                                                    <span class="product-id">

                                                        ID:
                                                        <?php
                                                        echo (int)
                                                            $product['product_id'];
                                                        ?>

                                                    </span>

                                                </div>


                                            </div>

                                        </td>


                                        <!-- BARCODE -->

                                        <td>

                                            <?php

                                            echo !empty(
                                                $product['barcode']
                                            )
                                                ? e(
                                                    $product['barcode']
                                                )
                                                : '—';

                                            ?>

                                        </td>


                                        <!-- CATEGORY -->

                                        <td>

                                            <?php

                                            echo !empty(
                                                $product['category_name']
                                            )
                                                ? e(
                                                    $product['category_name']
                                                )
                                                : 'Uncategorized';

                                            ?>

                                        </td>


                                        <!-- SUPPLIER -->

                                        <td>

                                            <?php

                                            echo !empty(
                                                $product['supplier_name']
                                            )
                                                ? e(
                                                    $product['supplier_name']
                                                )
                                                : 'No Supplier';

                                            ?>

                                        </td>


                                        <!-- PURCHASE PRICE -->

                                        <td class="price">

                                            ৳<?php

                                            echo number_format(
                                                (float)
                                                $product['purchase_price'],
                                                2
                                            );

                                            ?>

                                        </td>


                                        <!-- SELLING PRICE -->

                                        <td class="price">

                                            ৳<?php

                                            echo number_format(
                                                (float)
                                                $product['selling_price'],
                                                2
                                            );

                                            ?>

                                        </td>


                                        <!-- STOCK -->

                                        <td>

                                            <?php

                                            $stock =
                                                (int)
                                                $product['stock'];

                                            $minimumStock =
                                                (int)
                                                $product['minimum_stock'];

                                            $stockClass =
                                                $stock <= $minimumStock
                                                    ? 'stock-low'
                                                    : 'stock-normal';

                                            ?>


                                            <span
                                                class="<?php echo $stockClass; ?>"
                                            >

                                                <?php
                                                echo number_format(
                                                    $stock
                                                );
                                                ?>

                                                <?php
                                                echo e(
                                                    $product['unit']
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <?php

                                            $isActive =
                                                $product['status'] ===
                                                'Active';

                                            ?>


                                            <span
                                                class="status-badge
                                                <?php
                                                echo $isActive
                                                    ? 'status-active'
                                                    : 'status-inactive';
                                                ?>"
                                            >

                                                <?php
                                                echo e(
                                                    $product['status']
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td>

                                            <div class="product-actions">


                                                <a
                                                    href="<?php echo $basePath; ?>/modules/products/edit.php?id=<?php echo (int) $product['product_id']; ?>"
                                                    class="action-button edit-action"
                                                >
                                                    Edit
                                                </a>


                                                <a
                                                    href="<?php echo $basePath; ?>/modules/products/view.php?id=<?php echo (int) $product['product_id']; ?>"
                                                    class="action-button"
                                                >
                                                    View
                                                </a>


                                            </div>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                                </tbody>


                            </table>


                        </div>


                    <?php else: ?>


                        <!-- =================================================
                             EMPTY STATE
                        ================================================== -->

                        <div class="empty-state">


                            <div class="empty-icon">
                                📦
                            </div>


                            <h3>
                                No Products Found
                            </h3>


                            <p>

                                <?php if (
                                    $search !== '' ||
                                    $categoryFilter > 0 ||
                                    $statusFilter !== ''
                                ): ?>

                                    No products match your current search or
                                    filter criteria.

                                <?php else: ?>

                                    There are currently no products in the
                                    system. Add your first product to start
                                    managing your inventory.

                                <?php endif; ?>

                            </p>


                            <?php if (
                                $search === '' &&
                                $categoryFilter === 0 &&
                                $statusFilter === ''
                            ): ?>


                                <!-- =================================================
                                     ADD FIRST PRODUCT
                                     Prepared for Step 5B
                                ================================================== -->

                                <a
                                    href="<?php echo $basePath; ?>/modules/products/add.php"
                                    class="empty-add-button"
                                >
                                    + Add First Product
                                </a>


                            <?php else: ?>


                                <a
                                    href="<?php echo $basePath; ?>/modules/products/index.php"
                                    class="empty-add-button"
                                >
                                    Clear Filters
                                </a>


                            <?php endif; ?>


                        </div>


                    <?php endif; ?>


                </section>


            </div>


        </main>


        <!-- ====================================================
             SIDEBAR JAVASCRIPT
        ===================================================== -->

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