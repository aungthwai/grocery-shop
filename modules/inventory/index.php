<?php

require_once "../../includes/auth_check.php";
require_once "../../includes/role_guard.php";

grocerEaseRequireAdmin();

require_once "../../config/database.php";

$basePath = "/grocery-shop";
$page_title = "Inventory";


/*
|--------------------------------------------------------------------------
| INVENTORY STATISTICS
|--------------------------------------------------------------------------
*/

$totalProducts = 0;
$lowStock = 0;
$outOfStock = 0;
$inventoryValue = 0.00;


/*
|--------------------------------------------------------------------------
| TOTAL ACTIVE PRODUCTS
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'Active'
    "
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalProducts =
        (int) ($row['total'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| LOW STOCK PRODUCTS
|--------------------------------------------------------------------------
|
| Low stock excludes products already at zero stock.
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM products
    WHERE
        status = 'Active'
        AND stock > 0
        AND stock <= minimum_stock
    "
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $lowStock =
        (int) ($row['total'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| OUT OF STOCK PRODUCTS
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM products
    WHERE
        status = 'Active'
        AND stock = 0
    "
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $outOfStock =
        (int) ($row['total'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| CURRENT INVENTORY COST VALUE
|--------------------------------------------------------------------------
|
| Current stock quantity multiplied by purchase price.
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT
        COALESCE(
            SUM(stock * purchase_price),
            0
        ) AS total_value
    FROM products
    WHERE status = 'Active'
    "
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $inventoryValue =
        (float) ($row['total_value'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$allowedFilters = [
    'all',
    'low',
    'out'
];

$filter =
    trim(
        (string) ($_GET['filter'] ?? 'all')
    );

if (
    !in_array(
        $filter,
        $allowedFilters,
        true
    )
) {
    $filter = 'all';
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string) ($_GET['q'] ?? '')
    );


/*
|--------------------------------------------------------------------------
| PRODUCT LIST QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.product_id,
        p.product_name,
        p.barcode,
        p.unit,
        p.purchase_price,
        p.selling_price,
        p.stock,
        p.minimum_stock,
        p.status,

        c.category_name,
        s.supplier_name

    FROM products p

    LEFT JOIN categories c
        ON c.category_id = p.category_id

    LEFT JOIN suppliers s
        ON s.supplier_id = p.supplier_id

    WHERE
        p.status = 'Active'
";


/*
|--------------------------------------------------------------------------
| STOCK FILTER
|--------------------------------------------------------------------------
*/

if ($filter === 'low') {

    $sql .= "
        AND p.stock > 0
        AND p.stock <= p.minimum_stock
    ";
}

if ($filter === 'out') {

    $sql .= "
        AND p.stock = 0
    ";
}


/*
|--------------------------------------------------------------------------
| SEARCH FILTER
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND
        (
            p.product_name LIKE ?
            OR p.barcode LIKE ?
            OR c.category_name LIKE ?
            OR s.supplier_name LIKE ?
        )
    ";
}


/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
|
| Out-of-stock and low-stock products naturally appear first.
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        p.stock ASC,
        p.product_name ASC
";


$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {

    http_response_code(500);

    exit(
        'Unable to load inventory information.'
    );
}


if ($search !== '') {

    $searchValue =
        '%' . $search . '%';

    mysqli_stmt_bind_param(
        $stmt,
        'ssss',
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );
}


mysqli_stmt_execute(
    $stmt
);


$productResult =
    mysqli_stmt_get_result(
        $stmt
    );


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


<div class="app-layout">


    <!-- ============================================================
         SIDEBAR
    ============================================================ -->

    <aside class="app-sidebar-slot">

        <?php
        require_once "../../includes/sidebar.php";
        ?>

    </aside>


    <!-- ============================================================
         MAIN AREA
    ============================================================ -->

    <div class="app-main-slot">


        <!-- ========================================================
             TOPBAR
        ======================================================== -->

        <header class="app-topbar-slot">

            <?php
            require_once "../../includes/topbar.php";
            ?>

        </header>


        <!-- ========================================================
             CONTENT
        ======================================================== -->

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
                            Inventory Monitor
                        </h1>

                        <p>
                            Monitor current product stock levels,
                            low-stock products, and inventory value.
                        </p>

                    </div>


                    <a
                        href="../products/index.php"
                        class="btn btn-primary"
                    >
                        Manage Products
                    </a>


                </div>


                <!-- =================================================
                     INVENTORY STATISTICS
                ================================================= -->

                <div class="stats-row">


                    <!-- TOTAL ACTIVE PRODUCTS -->

                    <div class="stat-card blue">

                        <div class="stat-label">
                            Total Active Products
                        </div>

                        <div class="stat-value">

                            <?php
                            echo $totalProducts;
                            ?>

                        </div>

                    </div>


                    <!-- LOW STOCK -->

                    <div class="stat-card amber">

                        <div class="stat-label">
                            Low Stock Alerts
                        </div>

                        <div class="stat-value">

                            <?php
                            echo $lowStock;
                            ?>

                        </div>

                    </div>


                    <!-- OUT OF STOCK -->

                    <div class="stat-card red">

                        <div class="stat-label">
                            Out of Stock
                        </div>

                        <div class="stat-value">

                            <?php
                            echo $outOfStock;
                            ?>

                        </div>

                    </div>


                    <!-- INVENTORY VALUE -->

                    <div class="stat-card green">

                        <div class="stat-label">
                            Inventory Cost Value
                        </div>

                        <div class="stat-value">

                            &#2547;<?php
                            echo number_format(
                                $inventoryValue,
                                2
                            );
                            ?>

                        </div>

                    </div>


                </div>


                <!-- =================================================
                     INVENTORY TABLE CARD
                ================================================= -->

                <div class="card">


                    <!-- =================================================
                         FILTER + SEARCH
                    ================================================= -->

                    <div
                        style="
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            flex-wrap: wrap;
                            gap: 12px;
                            margin-bottom: 16px;
                        "
                    >


                        <!-- STOCK FILTERS -->

                        <div
                            style="
                                display: flex;
                                gap: 8px;
                                flex-wrap: wrap;
                            "
                        >


                            <a
                                href="?filter=all"
                                class="
                                    btn
                                    btn-sm
                                    <?php
                                    echo
                                        $filter === 'all'
                                            ? 'btn-primary'
                                            : 'btn-secondary';
                                    ?>
                                "
                            >
                                All
                            </a>


                            <a
                                href="?filter=low"
                                class="
                                    btn
                                    btn-sm
                                    <?php
                                    echo
                                        $filter === 'low'
                                            ? 'btn-warning'
                                            : 'btn-secondary';
                                    ?>
                                "
                            >
                                Low Stock
                            </a>


                            <a
                                href="?filter=out"
                                class="
                                    btn
                                    btn-sm
                                    <?php
                                    echo
                                        $filter === 'out'
                                            ? 'btn-danger'
                                            : 'btn-secondary';
                                    ?>
                                "
                            >
                                Out of Stock
                            </a>


                        </div>


                        <!-- SEARCH -->

                        <form
                            method="GET"
                            class="search-bar"
                            style="
                                margin: 0;
                                display: flex;
                                gap: 8px;
                                align-items: center;
                            "
                        >


                            <input
                                type="hidden"
                                name="filter"
                                value="<?php
                                    echo htmlspecialchars(
                                        $filter,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                            >


                            <input
                                type="text"
                                name="q"
                                value="<?php
                                    echo htmlspecialchars(
                                        $search,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                                placeholder="Search product, barcode, category or supplier..."
                            >


                            <button
                                type="submit"
                                class="btn btn-secondary btn-sm"
                            >
                                Search
                            </button>


                            <?php if ($search !== ''): ?>


                                <a
                                    href="?filter=<?php
                                        echo urlencode(
                                            $filter
                                        );
                                    ?>"
                                    class="btn btn-secondary btn-sm"
                                >
                                    Clear
                                </a>


                            <?php endif; ?>


                        </form>


                    </div>


                    <!-- =================================================
                         PRODUCT TABLE
                    ================================================= -->

                    <div class="table-wrapper">


                        <table class="data-table">


                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Product
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
                                        Current Stock
                                    </th>

                                    <th>
                                        Minimum Stock
                                    </th>

                                    <th>
                                        Stock Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php
                            if (
                                !$productResult ||
                                mysqli_num_rows(
                                    $productResult
                                ) === 0
                            ):
                            ?>


                                <tr class="empty-row">

                                    <td colspan="9">
                                        No products found.
                                    </td>

                                </tr>


                            <?php else: ?>


                                <?php
                                while (
                                    $product =
                                        mysqli_fetch_assoc(
                                            $productResult
                                        )
                                ):
                                ?>


                                    <?php

                                    $stock =
                                        (int) (
                                            $product['stock']
                                            ?? 0
                                        );


                                    $minimumStock =
                                        (int) (
                                            $product[
                                                'minimum_stock'
                                            ]
                                            ?? 0
                                        );


                                    $stockOut =
                                        $stock === 0;


                                    $stockLow =
                                        $stock > 0 &&
                                        $stock <=
                                            $minimumStock;


                                    if ($stockOut) {

                                        $stockBadgeClass =
                                            'badge-danger';

                                        $stockLabel =
                                            'Out of Stock';

                                        $stockColor =
                                            '#ef4444';

                                    } elseif ($stockLow) {

                                        $stockBadgeClass =
                                            'badge-warning';

                                        $stockLabel =
                                            'Low Stock';

                                        $stockColor =
                                            '#f59e0b';

                                    } else {

                                        $stockBadgeClass =
                                            'badge-success';

                                        $stockLabel =
                                            'In Stock';

                                        $stockColor =
                                            '#22c55e';
                                    }

                                    ?>


                                    <tr>


                                        <!-- ID -->

                                        <td>

                                            <?php
                                            echo
                                                (int) $product[
                                                    'product_id'
                                                ];
                                            ?>

                                        </td>


                                        <!-- PRODUCT -->

                                        <td>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $product[
                                                        'product_name'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </strong>


                                            <?php
                                            if (
                                                !empty(
                                                    $product[
                                                        'barcode'
                                                    ]
                                                )
                                            ):
                                            ?>

                                                <div>

                                                    <small>

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $product[
                                                                'barcode'
                                                            ],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?>

                                                    </small>

                                                </div>

                                            <?php endif; ?>


                                        </td>


                                        <!-- CATEGORY -->

                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $product[
                                                    'category_name'
                                                ] ?? '-',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <!-- SUPPLIER -->

                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $product[
                                                    'supplier_name'
                                                ] ?? '-',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <!-- PURCHASE PRICE -->

                                        <td>

                                            &#2547;<?php
                                            echo number_format(
                                                (float) $product[
                                                    'purchase_price'
                                                ],
                                                2
                                            );
                                            ?>

                                        </td>


                                        <!-- SELLING PRICE -->

                                        <td>

                                            &#2547;<?php
                                            echo number_format(
                                                (float) $product[
                                                    'selling_price'
                                                ],
                                                2
                                            );
                                            ?>

                                        </td>


                                        <!-- CURRENT STOCK -->

                                        <td
                                            style="
                                                font-weight: 700;
                                                color: <?php
                                                    echo $stockColor;
                                                ?>;
                                            "
                                        >

                                            <?php
                                            echo $stock;
                                            ?>

                                            <?php
                                            echo htmlspecialchars(
                                                $product['unit']
                                                    ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <!-- MIN STOCK -->

                                        <td>

                                            <?php
                                            echo $minimumStock;
                                            ?>

                                        </td>


                                        <!-- STOCK STATUS -->

                                        <td>

                                            <span
                                                class="
                                                    badge
                                                    <?php
                                                    echo
                                                        $stockBadgeClass;
                                                    ?>
                                                "
                                            >

                                                <?php
                                                echo
                                                    $stockLabel;
                                                ?>

                                            </span>

                                        </td>


                                    </tr>


                                <?php endwhile; ?>


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

/*
|--------------------------------------------------------------------------
| CLEAN UP
|--------------------------------------------------------------------------
*/

if (
    isset($stmt) &&
    $stmt instanceof mysqli_stmt
) {
    mysqli_stmt_close(
        $stmt
    );
}


require_once "../../includes/footer.php";

?>