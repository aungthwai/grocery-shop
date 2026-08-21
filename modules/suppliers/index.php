<?php

require_once "../../includes/auth_check.php";
require_once "../../includes/role_guard.php";

grocerEaseRequireAdmin();

require_once "../../config/database.php";

$basePath = "/grocery-shop";
$page_title = "Supplier Management";


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function supplierRedirect(string $status): void
{
    header(
        'Location: index.php?status=' . urlencode($status)
    );

    exit;
}


function verifySupplierCsrf(): void
{
    $sessionToken =
        $_SESSION['csrf_token'] ?? '';

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !is_string($sessionToken) ||
        !is_string($postedToken) ||
        $sessionToken === '' ||
        $postedToken === '' ||
        !hash_equals(
            $sessionToken,
            $postedToken
        )
    ) {
        http_response_code(403);

        exit(
            'Invalid request token.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| HANDLE POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    verifySupplierCsrf();

    $action =
        (string) ($_POST['action'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | ADD SUPPLIER
    |--------------------------------------------------------------------------
    */

    if ($action === 'add') {

        $name =
            trim(
                (string) ($_POST['name'] ?? '')
            );

        $company =
            trim(
                (string) ($_POST['company'] ?? '')
            );

        $phone =
            trim(
                (string) ($_POST['phone'] ?? '')
            );

        $email =
            trim(
                (string) ($_POST['email'] ?? '')
            );

        $address =
            trim(
                (string) ($_POST['address'] ?? '')
            );


        if ($name === '') {
            supplierRedirect(
                'name_required'
            );
        }


        if (
            $email !== '' &&
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            supplierRedirect(
                'invalid_email'
            );
        }


        $stmt = mysqli_prepare(
            $conn,
            "
            INSERT INTO suppliers
            (
                supplier_name,
                company,
                phone,
                email,
                address
            )
            VALUES (?, ?, ?, ?, ?)
            "
        );


        if (!$stmt) {
            supplierRedirect(
                'error'
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            'sssss',
            $name,
            $company,
            $phone,
            $email,
            $address
        );


        $success =
            mysqli_stmt_execute(
                $stmt
            );


        mysqli_stmt_close(
            $stmt
        );


        supplierRedirect(
            $success
                ? 'added'
                : 'error'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE SUPPLIER
    |--------------------------------------------------------------------------
    */

    if ($action === 'update') {

        $supplierId =
            filter_input(
                INPUT_POST,
                'supplier_id',
                FILTER_VALIDATE_INT
            );


        $name =
            trim(
                (string) ($_POST['name'] ?? '')
            );

        $company =
            trim(
                (string) ($_POST['company'] ?? '')
            );

        $phone =
            trim(
                (string) ($_POST['phone'] ?? '')
            );

        $email =
            trim(
                (string) ($_POST['email'] ?? '')
            );

        $address =
            trim(
                (string) ($_POST['address'] ?? '')
            );


        if (
            !$supplierId ||
            $name === ''
        ) {
            supplierRedirect(
                'invalid_supplier'
            );
        }


        if (
            $email !== '' &&
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            supplierRedirect(
                'invalid_email'
            );
        }


        $stmt = mysqli_prepare(
            $conn,
            "
            UPDATE suppliers
            SET
                supplier_name = ?,
                company = ?,
                phone = ?,
                email = ?,
                address = ?
            WHERE supplier_id = ?
            "
        );


        if (!$stmt) {
            supplierRedirect(
                'error'
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            'sssssi',
            $name,
            $company,
            $phone,
            $email,
            $address,
            $supplierId
        );


        $success =
            mysqli_stmt_execute(
                $stmt
            );


        mysqli_stmt_close(
            $stmt
        );


        supplierRedirect(
            $success
                ? 'updated'
                : 'error'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE SUPPLIER
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete') {

        $supplierId =
            filter_input(
                INPUT_POST,
                'supplier_id',
                FILTER_VALIDATE_INT
            );


        if (!$supplierId) {
            supplierRedirect(
                'invalid_supplier'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK WHETHER SUPPLIER HAS RELATED HISTORY
        |--------------------------------------------------------------------------
        */

        $stmt = mysqli_prepare(
            $conn,
            "
            SELECT

                (
                    SELECT COUNT(*)
                    FROM products
                    WHERE supplier_id = ?
                ) AS product_count,

                (
                    SELECT COUNT(*)
                    FROM purchases
                    WHERE supplier_id = ?
                ) AS purchase_count
            "
        );


        if (!$stmt) {
            supplierRedirect(
                'error'
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            'ii',
            $supplierId,
            $supplierId
        );


        mysqli_stmt_execute(
            $stmt
        );


        $result =
            mysqli_stmt_get_result(
                $stmt
            );


        $usage =
            mysqli_fetch_assoc(
                $result
            );


        mysqli_stmt_close(
            $stmt
        );


        $productCount =
            (int) (
                $usage['product_count']
                ?? 0
            );


        $purchaseCount =
            (int) (
                $usage['purchase_count']
                ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | SAFE DELETE RULE
        |--------------------------------------------------------------------------
        |
        | A supplier with product or purchase history is protected.
        |
        */

        if (
            $productCount > 0 ||
            $purchaseCount > 0
        ) {
            supplierRedirect(
                'in_use'
            );
        }


        $stmt = mysqli_prepare(
            $conn,
            "
            DELETE FROM suppliers
            WHERE supplier_id = ?
            "
        );


        if (!$stmt) {
            supplierRedirect(
                'error'
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $supplierId
        );


        $success =
            mysqli_stmt_execute(
                $stmt
            );


        mysqli_stmt_close(
            $stmt
        );


        supplierRedirect(
            $success
                ? 'deleted'
                : 'error'
        );
    }
}


/*
|--------------------------------------------------------------------------
| STATUS MESSAGES
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';

$status =
    trim(
        (string) ($_GET['status'] ?? '')
    );


switch ($status) {

    case 'added':

        $message =
            'Supplier added successfully.';

        $messageType =
            'success';

        break;


    case 'updated':

        $message =
            'Supplier updated successfully.';

        $messageType =
            'success';

        break;


    case 'deleted':

        $message =
            'Supplier deleted successfully.';

        $messageType =
            'success';

        break;


    case 'in_use':

        $message =
            'This supplier cannot be deleted because it is already linked '
            . 'to products or purchase history.';

        $messageType =
            'error';

        break;


    case 'name_required':

        $message =
            'Supplier name is required.';

        $messageType =
            'error';

        break;


    case 'invalid_email':

        $message =
            'Please enter a valid email address.';

        $messageType =
            'error';

        break;


    case 'invalid_supplier':

        $message =
            'The selected supplier is invalid.';

        $messageType =
            'error';

        break;


    case 'error':

        $message =
            'The supplier operation could not be completed.';

        $messageType =
            'error';

        break;
}


/*
|--------------------------------------------------------------------------
| LOAD SUPPLIER FOR EDITING
|--------------------------------------------------------------------------
*/

$editSupplier = null;


$editId =
    filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );


if ($editId) {

    $stmt = mysqli_prepare(
        $conn,
        "
        SELECT
            supplier_id,
            supplier_name,
            company,
            phone,
            email,
            address
        FROM suppliers
        WHERE supplier_id = ?
        LIMIT 1
        "
    );


    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $editId
        );


        mysqli_stmt_execute(
            $stmt
        );


        $result =
            mysqli_stmt_get_result(
                $stmt
            );


        $editSupplier =
            mysqli_fetch_assoc(
                $result
            );


        mysqli_stmt_close(
            $stmt
        );
    }
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalSuppliers = 0;
$totalProducts = 0;
$totalPurchases = 0;


/*
|--------------------------------------------------------------------------
| TOTAL SUPPLIERS
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM suppliers
    "
);


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $totalSuppliers =
        (int) (
            $row['total']
            ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| TOTAL PRODUCTS WITH SUPPLIER
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM products
    WHERE supplier_id IS NOT NULL
    "
);


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $totalProducts =
        (int) (
            $row['total']
            ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| TOTAL PURCHASE RECORDS
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM purchases
    "
);


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $totalPurchases =
        (int) (
            $row['total']
            ?? 0
        );
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
| SUPPLIER LIST
|--------------------------------------------------------------------------
|
| Each statistic is calculated independently to prevent duplicate
| purchase totals when a supplier has multiple products.
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        s.supplier_id,
        s.supplier_name,
        s.company,
        s.phone,
        s.email,
        s.address,
        s.created_at,

        (
            SELECT COUNT(*)
            FROM products p2
            WHERE p2.supplier_id = s.supplier_id
        ) AS product_count,

        (
            SELECT COUNT(*)
            FROM purchases pu2
            WHERE pu2.supplier_id = s.supplier_id
        ) AS purchase_count,

        (
            SELECT COALESCE(
                SUM(pu3.total_amount),
                0
            )
            FROM purchases pu3
            WHERE pu3.supplier_id = s.supplier_id
        ) AS purchase_total

    FROM suppliers s
";


if ($search !== '') {

    $sql .= "
        WHERE
            s.supplier_name LIKE ?
            OR s.company LIKE ?
            OR s.phone LIKE ?
            OR s.email LIKE ?
    ";
}


$sql .= "
    ORDER BY
        s.supplier_id DESC
";


$stmt = mysqli_prepare(
    $conn,
    $sql
);


if (!$stmt) {

    http_response_code(500);

    exit(
        'Unable to load supplier information.'
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


$supplierResult =
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
         MAIN CONTENT
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
                            Supplier Management
                        </h1>

                        <p>
                            Manage supplier details and review
                            related product and purchase activity.
                        </p>

                    </div>


                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="
                            openSupplierModal(
                                'addSupplierModal'
                            )
                        "
                    >
                        + Add Supplier
                    </button>

                </div>


                <!-- =================================================
                     STATUS MESSAGE
                ================================================= -->

                <?php if ($message !== ''): ?>

                    <div
                        class="alert <?php
                            echo
                                $messageType === 'success'
                                    ? 'alert-success'
                                    : 'alert-danger';
                        ?>"
                    >

                        <?php
                        echo htmlspecialchars(
                            $message,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     STATISTICS
                ================================================= -->

                <div class="stats-row">


                    <div class="stat-card blue">

                        <div class="stat-label">
                            Total Suppliers
                        </div>

                        <div class="stat-value">
                            <?php
                            echo $totalSuppliers;
                            ?>
                        </div>

                    </div>


                    <div class="stat-card green">

                        <div class="stat-label">
                            Products Supplied
                        </div>

                        <div class="stat-value">
                            <?php
                            echo $totalProducts;
                            ?>
                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-label">
                            Purchase Records
                        </div>

                        <div class="stat-value">
                            <?php
                            echo $totalPurchases;
                            ?>
                        </div>

                    </div>


                </div>


                <!-- =================================================
                     SUPPLIER TABLE
                ================================================= -->

                <div class="card">


                    <div
                        style="
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            gap: 16px;
                            margin-bottom: 16px;
                            flex-wrap: wrap;
                        "
                    >


                        <div
                            class="card-title"
                            style="
                                margin: 0;
                                border: none;
                                padding: 0;
                            "
                        >
                            Supplier Details &amp; History List
                        </div>


                        <!-- =========================================
                             SEARCH
                        ========================================== -->

                        <form
                            method="GET"
                            class="search-bar"
                            style="
                                margin: 0;
                                display: flex;
                                gap: 8px;
                            "
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
                                placeholder="Search name, company, phone or email..."
                            >


                            <button
                                type="submit"
                                class="btn btn-secondary btn-sm"
                            >
                                Search
                            </button>


                            <?php if ($search !== ''): ?>

                                <a
                                    href="index.php"
                                    class="btn btn-secondary btn-sm"
                                >
                                    Clear
                                </a>

                            <?php endif; ?>


                        </form>


                    </div>


                    <div class="table-wrapper">


                        <table class="data-table">


                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Supplier
                                    </th>

                                    <th>
                                        Contact
                                    </th>

                                    <th>
                                        Products
                                    </th>

                                    <th>
                                        Purchases
                                    </th>

                                    <th>
                                        Purchase Total
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php
                            if (
                                !$supplierResult ||
                                mysqli_num_rows(
                                    $supplierResult
                                ) === 0
                            ):
                            ?>


                                <tr class="empty-row">

                                    <td colspan="7">
                                        No suppliers found.
                                    </td>

                                </tr>


                            <?php else: ?>


                                <?php
                                while (
                                    $supplier =
                                        mysqli_fetch_assoc(
                                            $supplierResult
                                        )
                                ):
                                ?>


                                    <tr>


                                        <!-- ID -->

                                        <td>

                                            <?php
                                            echo
                                                (int) $supplier[
                                                    'supplier_id'
                                                ];
                                            ?>

                                        </td>


                                        <!-- SUPPLIER -->

                                        <td>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $supplier[
                                                        'supplier_name'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </strong>


                                            <?php
                                            if (
                                                !empty(
                                                    $supplier[
                                                        'company'
                                                    ]
                                                )
                                            ):
                                            ?>


                                                <div>

                                                    <small>

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $supplier[
                                                                'company'
                                                            ],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?>

                                                    </small>

                                                </div>


                                            <?php endif; ?>


                                        </td>


                                        <!-- CONTACT -->

                                        <td>

                                            <div>

                                                <?php
                                                echo htmlspecialchars(
                                                    $supplier[
                                                        'phone'
                                                    ] ?: '-',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </div>


                                            <small>

                                                <?php
                                                echo htmlspecialchars(
                                                    $supplier[
                                                        'email'
                                                    ] ?: '-',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </small>

                                        </td>


                                        <!-- PRODUCT COUNT -->

                                        <td>

                                            <span
                                                class="badge badge-info"
                                            >

                                                <?php
                                                echo
                                                    (int) $supplier[
                                                        'product_count'
                                                    ];
                                                ?>

                                            </span>

                                        </td>


                                        <!-- PURCHASE COUNT -->

                                        <td>

                                            <?php
                                            echo
                                                (int) $supplier[
                                                    'purchase_count'
                                                ];
                                            ?>

                                        </td>


                                        <!-- PURCHASE TOTAL -->

                                        <td>

                                            <?php
                                            echo number_format(
                                                (float) $supplier[
                                                    'purchase_total'
                                                ],
                                                2
                                            );
                                            ?>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td class="actions">


                                            <a
                                                href="?edit=<?php
                                                    echo
                                                        (int) $supplier[
                                                            'supplier_id'
                                                        ];
                                                ?>"
                                                class="
                                                    btn
                                                    btn-warning
                                                    btn-sm
                                                "
                                            >
                                                Edit
                                            </a>


                                            <form
                                                method="POST"
                                                style="
                                                    display: inline;
                                                "
                                                onsubmit="
                                                    return confirm(
                                                        'Delete this supplier?'
                                                    );
                                                "
                                            >


                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php
                                                        echo htmlspecialchars(
                                                            $csrfToken,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                    ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="supplier_id"
                                                    value="<?php
                                                        echo
                                                            (int) $supplier[
                                                                'supplier_id'
                                                            ];
                                                    ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="
                                                        btn
                                                        btn-danger
                                                        btn-sm
                                                    "
                                                >
                                                    Delete
                                                </button>


                                            </form>


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



<!-- ================================================================
     ADD SUPPLIER MODAL
================================================================ -->

<div
    class="modal-overlay"
    id="addSupplierModal"
>


    <div class="modal">


        <div class="modal-header">


            <h3>
                Add New Supplier
            </h3>


            <button
                type="button"
                class="modal-close"
                onclick="
                    closeSupplierModal(
                        'addSupplierModal'
                    )
                "
            >
                &times;
            </button>


        </div>


        <form method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                    echo htmlspecialchars(
                        $csrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >


            <input
                type="hidden"
                name="action"
                value="add"
            >


            <div class="modal-body">


                <div class="form-grid">


                    <div class="form-group">


                        <label>
                            Supplier Name *
                        </label>


                        <input
                            type="text"
                            name="name"
                            required
                            maxlength="100"
                            placeholder="Supplier name"
                        >


                    </div>


                    <div class="form-group">


                        <label>
                            Company Name
                        </label>


                        <input
                            type="text"
                            name="company"
                            maxlength="100"
                            placeholder="Company name"
                        >


                    </div>


                    <div class="form-group">


                        <label>
                            Phone Number
                        </label>


                        <input
                            type="text"
                            name="phone"
                            maxlength="20"
                            placeholder="01XXXXXXXXX"
                        >


                    </div>


                    <div class="form-group">


                        <label>
                            Email Address
                        </label>


                        <input
                            type="email"
                            name="email"
                            maxlength="100"
                            placeholder="email@example.com"
                        >


                    </div>


                    <div
                        class="
                            form-group
                            form-full
                        "
                    >


                        <label>
                            Address
                        </label>


                        <input
                            type="text"
                            name="address"
                            placeholder="Supplier address"
                        >


                    </div>


                </div>


            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="
                        closeSupplierModal(
                            'addSupplierModal'
                        )
                    "
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Add Supplier
                </button>


            </div>


        </form>


    </div>


</div>



<!-- ================================================================
     EDIT SUPPLIER MODAL
================================================================ -->

<?php if ($editSupplier): ?>


<div
    class="modal-overlay open"
    id="editSupplierModal"
>


    <div class="modal">


        <div class="modal-header">


            <h3>
                Edit Supplier
            </h3>


            <button
                type="button"
                class="modal-close"
                onclick="
                    window.location.href =
                        'index.php'
                "
            >
                &times;
            </button>


        </div>


        <form method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                    echo htmlspecialchars(
                        $csrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >


            <input
                type="hidden"
                name="action"
                value="update"
            >


            <input
                type="hidden"
                name="supplier_id"
                value="<?php
                    echo
                        (int) $editSupplier[
                            'supplier_id'
                        ];
                ?>"
            >


            <div class="modal-body">


                <div class="form-grid">


                    <div class="form-group">


                        <label>
                            Supplier Name *
                        </label>


                        <input
                            type="text"
                            name="name"
                            required
                            maxlength="100"
                            value="<?php
                                echo htmlspecialchars(
                                    $editSupplier[
                                        'supplier_name'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                        >


                    </div>


                    <div class="form-group">


                        <label>
                            Company Name
                        </label>


                        <input
                            type="text"
                            name="company"
                            maxlength="100"
                            value="<?php
                                echo htmlspecialchars(
                                    $editSupplier[
                                        'company'
                                    ] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                        >


                    </div>


                    <div class="form-group">


                        <label>
                            Phone Number
                        </label>


                        <input
                            type="text"
                            name="phone"
                            maxlength="20"
                            value="<?php
                                echo htmlspecialchars(
                                    $editSupplier[
                                        'phone'
                                    ] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                        >


                    </div>


                    <div class="form-group">


                        <label>
                            Email Address
                        </label>


                        <input
                            type="email"
                            name="email"
                            maxlength="100"
                            value="<?php
                                echo htmlspecialchars(
                                    $editSupplier[
                                        'email'
                                    ] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                        >


                    </div>


                    <div
                        class="
                            form-group
                            form-full
                        "
                    >


                        <label>
                            Address
                        </label>


                        <input
                            type="text"
                            name="address"
                            value="<?php
                                echo htmlspecialchars(
                                    $editSupplier[
                                        'address'
                                    ] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                        >


                    </div>


                </div>


            </div>


            <div class="modal-footer">


                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Supplier
                </button>


            </div>


        </form>


    </div>


</div>


<?php endif; ?>



<!-- ================================================================
     SUPPLIER MODAL SCRIPT
================================================================ -->

<script>

function openSupplierModal(id)
{
    const modal =
        document.getElementById(id);

    if (modal) {
        modal.classList.add('open');
    }
}


function closeSupplierModal(id)
{
    const modal =
        document.getElementById(id);

    if (modal) {
        modal.classList.remove('open');
    }
}

</script>


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