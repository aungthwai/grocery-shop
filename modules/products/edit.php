<?php

session_start();

/*
|--------------------------------------------------------------------------
| EDIT PRODUCT PAGE
|--------------------------------------------------------------------------
| Step 5C
| Loads an existing product, validates submitted values, and updates it.
| Stage 2 adds product-image replacement and removal.
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
| BASE PATH / PAGE INFORMATION
|--------------------------------------------------------------------------
*/

$basePath = "/grocery-shop";
$pageTitle = "Edit Product";

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
            product_id,
            category_id,
            supplier_id,
            product_name,
            barcode,
            unit,
            purchase_price,
            selling_price,
            stock,
            minimum_stock,
            image,
            status
        FROM products
        WHERE product_id = ?
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
| INITIAL FORM VALUES
|--------------------------------------------------------------------------
*/

$productName = (string) $product['product_name'];
$categoryId = (int) $product['category_id'];

$supplierId = $product['supplier_id'] !== null
    ? (int) $product['supplier_id']
    : 0;

$barcode = (string) ($product['barcode'] ?? '');
$unit = (string) $product['unit'];
$purchasePrice = (string) $product['purchase_price'];
$sellingPrice = (string) $product['selling_price'];
$stock = (string) $product['stock'];
$minimumStock = (string) $product['minimum_stock'];
$status = (string) $product['status'];
$imageValue = $product['image'] ?? null;

$errors = [];
$success = '';

/*
|--------------------------------------------------------------------------
| IMAGE UPLOAD SETTINGS
|--------------------------------------------------------------------------
*/

$uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads'
    . DIRECTORY_SEPARATOR . 'products';

$maxImageSize = 2 * 1024 * 1024;

$allowedImageTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/*
|--------------------------------------------------------------------------
| IMAGE PATH HELPERS
|--------------------------------------------------------------------------
*/

$deleteProductImage = static function ($relativePath) use ($uploadDirectory) {

    if (
        !is_string($relativePath) ||
        $relativePath === ''
    ) {
        return;
    }

    $normalizedPath = str_replace('\\', '/', trim($relativePath));

    if (
        strpos($normalizedPath, 'uploads/products/') !== 0
    ) {
        return;
    }

    $filename = basename($normalizedPath);

    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $absolutePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
};

/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

$categoryResult = mysqli_query(
    $conn,
    "
        SELECT category_id, category_name
        FROM categories
        ORDER BY category_name ASC
    "
);

if ($categoryResult) {
    while ($row = mysqli_fetch_assoc($categoryResult)) {
        $categories[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| LOAD SUPPLIERS
|--------------------------------------------------------------------------
*/

$suppliers = [];

$supplierResult = mysqli_query(
    $conn,
    "
        SELECT supplier_id, supplier_name, company
        FROM suppliers
        ORDER BY supplier_name ASC
    "
);

if ($supplierResult) {
    while ($row = mysqli_fetch_assoc($supplierResult)) {
        $suppliers[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| HANDLE UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productName = trim($_POST['product_name'] ?? '');

    $categoryIdValue = filter_input(
        INPUT_POST,
        'category_id',
        FILTER_VALIDATE_INT
    );

    $supplierIdValue = filter_input(
        INPUT_POST,
        'supplier_id',
        FILTER_VALIDATE_INT
    );

    $barcode = trim($_POST['barcode'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $purchasePrice = trim($_POST['purchase_price'] ?? '');
    $sellingPrice = trim($_POST['selling_price'] ?? '');

    $stockValue = filter_input(
        INPUT_POST,
        'stock',
        FILTER_VALIDATE_INT
    );

    $minimumStockValue = filter_input(
        INPUT_POST,
        'minimum_stock',
        FILTER_VALIDATE_INT
    );

    $status = trim($_POST['status'] ?? '');

    $removeImage = isset($_POST['remove_image'])
        && $_POST['remove_image'] === '1';

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE OPTIONAL VALUES
    |--------------------------------------------------------------------------
    */

    $categoryId = $categoryIdValue !== false && $categoryIdValue !== null
        ? (int) $categoryIdValue
        : 0;

    $supplierId = $supplierIdValue !== false && $supplierIdValue !== null
        ? (int) $supplierIdValue
        : 0;

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($productName === '') {
        $errors[] = 'Product name is required.';
    } elseif (mb_strlen($productName) > 150) {
        $errors[] = 'Product name cannot exceed 150 characters.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Please select a category.';
    }

    if ($barcode !== '' && mb_strlen($barcode) > 50) {
        $errors[] = 'Barcode cannot exceed 50 characters.';
    }

    if ($unit === '') {
        $errors[] = 'Unit is required.';
    } elseif (mb_strlen($unit) > 20) {
        $errors[] = 'Unit cannot exceed 20 characters.';
    }

    if ($purchasePrice === '' || !is_numeric($purchasePrice)) {
        $errors[] = 'Purchase price must be a valid number.';
    } elseif ((float) $purchasePrice < 0) {
        $errors[] = 'Purchase price cannot be negative.';
    }

    if ($sellingPrice === '' || !is_numeric($sellingPrice)) {
        $errors[] = 'Selling price must be a valid number.';
    } elseif ((float) $sellingPrice < 0) {
        $errors[] = 'Selling price cannot be negative.';
    }

    if (
        $stockValue === false ||
        $stockValue === null ||
        $stockValue < 0
    ) {
        $errors[] = 'Stock must be a whole number greater than or equal to 0.';
    }

    if (
        $minimumStockValue === false ||
        $minimumStockValue === null ||
        $minimumStockValue < 0
    ) {
        $errors[] = 'Minimum stock must be a whole number greater than or equal to 0.';
    }

    if ($status !== 'Active' && $status !== 'Inactive') {
        $errors[] = 'Please select a valid product status.';
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY CATEGORY
    |--------------------------------------------------------------------------
    */

    if ($categoryId > 0) {

        $categoryStmt = mysqli_prepare(
            $conn,
            "
                SELECT category_id
                FROM categories
                WHERE category_id = ?
                LIMIT 1
            "
        );

        if ($categoryStmt) {

            mysqli_stmt_bind_param(
                $categoryStmt,
                "i",
                $categoryId
            );

            mysqli_stmt_execute($categoryStmt);

            $categoryCheck = mysqli_stmt_get_result($categoryStmt);

            if (
                !$categoryCheck ||
                mysqli_num_rows($categoryCheck) === 0
            ) {
                $errors[] = 'The selected category does not exist.';
            }

            mysqli_stmt_close($categoryStmt);

        } else {
            $errors[] = 'Unable to verify the selected category.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY SUPPLIER
    |--------------------------------------------------------------------------
    */

    if ($supplierId > 0) {

        $supplierStmt = mysqli_prepare(
            $conn,
            "
                SELECT supplier_id
                FROM suppliers
                WHERE supplier_id = ?
                LIMIT 1
            "
        );

        if ($supplierStmt) {

            mysqli_stmt_bind_param(
                $supplierStmt,
                "i",
                $supplierId
            );

            mysqli_stmt_execute($supplierStmt);

            $supplierCheck = mysqli_stmt_get_result($supplierStmt);

            if (
                !$supplierCheck ||
                mysqli_num_rows($supplierCheck) === 0
            ) {
                $errors[] = 'The selected supplier does not exist.';
            }

            mysqli_stmt_close($supplierStmt);

        } else {
            $errors[] = 'Unable to verify the selected supplier.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE BARCODE
    |--------------------------------------------------------------------------
    | The current product is excluded so it can keep its own barcode.
    |--------------------------------------------------------------------------
    */

    if ($barcode !== '') {

        $barcodeStmt = mysqli_prepare(
            $conn,
            "
                SELECT product_id
                FROM products
                WHERE barcode = ?
                  AND product_id != ?
                LIMIT 1
            "
        );

        if ($barcodeStmt) {

            mysqli_stmt_bind_param(
                $barcodeStmt,
                "si",
                $barcode,
                $productId
            );

            mysqli_stmt_execute($barcodeStmt);

            $barcodeCheck = mysqli_stmt_get_result($barcodeStmt);

            if (
                $barcodeCheck &&
                mysqli_num_rows($barcodeCheck) > 0
            ) {
                $errors[] =
                    'This barcode is already used by another product.';
            }

            mysqli_stmt_close($barcodeStmt);

        } else {
            $errors[] = 'Unable to verify the barcode.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE NEW IMAGE, IF PROVIDED
    |--------------------------------------------------------------------------
    */

    $newImagePath = null;
    $newImageAbsolutePath = null;

    $hasNewImage = isset($_FILES['product_image'])
        && is_array($_FILES['product_image'])
        && (
            (int) ($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_NO_FILE
        );

    if ($hasNewImage) {

        $uploadError = (int) (
            $_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE
        );

        if ($uploadError !== UPLOAD_ERR_OK) {

            if ($uploadError === UPLOAD_ERR_INI_SIZE) {
                $errors[] =
                    'The uploaded image is too large. Maximum size is 2 MB.';
            } elseif ($uploadError === UPLOAD_ERR_FORM_SIZE) {
                $errors[] =
                    'The uploaded image is too large. Maximum size is 2 MB.';
            } else {
                $errors[] =
                    'The product image could not be uploaded.';
            }

        } else {

            $temporaryPath = $_FILES['product_image']['tmp_name'] ?? '';
            $uploadedSize = (int) (
                $_FILES['product_image']['size'] ?? 0
            );

            if ($uploadedSize <= 0) {
                $errors[] = 'The uploaded image is empty.';
            } elseif ($uploadedSize > $maxImageSize) {
                $errors[] =
                    'Product image must be 2 MB or smaller.';
            } elseif (
                !is_uploaded_file($temporaryPath)
            ) {
                $errors[] =
                    'The uploaded image could not be verified.';
            } else {

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $actualMimeType = $finfo->file($temporaryPath);

                if (
                    $actualMimeType === false ||
                    !isset($allowedImageTypes[$actualMimeType])
                ) {
                    $errors[] =
                        'Only JPG/JPEG, PNG, and WebP images are allowed.';
                } else {

                    $imageInfo = @getimagesize($temporaryPath);

                    if ($imageInfo === false) {
                        $errors[] =
                            'The uploaded file is not a valid image.';
                    } else {

                        if (!is_dir($uploadDirectory)) {

                            if (
                                !mkdir(
                                    $uploadDirectory,
                                    0755,
                                    true
                                ) &&
                                !is_dir($uploadDirectory)
                            ) {
                                $errors[] =
                                    'Unable to create the product image directory.';
                            }
                        }

                        if (empty($errors)) {

                            try {
                                $randomName = bin2hex(random_bytes(16));
                            } catch (Throwable $exception) {
                                $randomName = '';
                            }

                            if ($randomName === '') {
                                $errors[] =
                                    'Unable to generate a safe image filename.';
                            } else {

                                $extension =
                                    $allowedImageTypes[$actualMimeType];

                                $filename =
                                    'product_' .
                                    $randomName .
                                    '.' .
                                    $extension;

                                $newImageAbsolutePath =
                                    $uploadDirectory .
                                    DIRECTORY_SEPARATOR .
                                    $filename;

                                $newImagePath =
                                    'uploads/products/' . $filename;

                                if (
                                    !move_uploaded_file(
                                        $temporaryPath,
                                        $newImageAbsolutePath
                                    )
                                ) {
                                    $errors[] =
                                        'Unable to save the uploaded product image.';

                                    $newImagePath = null;
                                    $newImageAbsolutePath = null;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PRODUCT
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        /*
         * A new image always replaces the old image.
         *
         * If no new image is uploaded:
         * - checked "Remove image" clears the database value
         * - otherwise the existing image is preserved
         */

        if ($newImagePath !== null) {
            $imageValue = $newImagePath;
        } elseif ($removeImage) {
            $imageValue = null;
        }

        $supplierDbValue = $supplierId > 0
            ? $supplierId
            : null;

        $updateStmt = mysqli_prepare(
            $conn,
            "
                UPDATE products
                SET
                    category_id = ?,
                    supplier_id = ?,
                    product_name = ?,
                    barcode = NULLIF(?, ''),
                    unit = ?,
                    purchase_price = ?,
                    selling_price = ?,
                    stock = ?,
                    minimum_stock = ?,
                    image = ?,
                    status = ?
                WHERE product_id = ?
            "
        );

        if ($updateStmt) {

            $purchasePriceValue = (float) $purchasePrice;
            $sellingPriceValue = (float) $sellingPrice;
            $stockInt = (int) $stockValue;
            $minimumStockInt = (int) $minimumStockValue;

            mysqli_stmt_bind_param(
                $updateStmt,
                "iisssddiissi",
                $categoryId,
                $supplierDbValue,
                $productName,
                $barcode,
                $unit,
                $purchasePriceValue,
                $sellingPriceValue,
                $stockInt,
                $minimumStockInt,
                $imageValue,
                $status,
                $productId
            );

            if (mysqli_stmt_execute($updateStmt)) {

                mysqli_stmt_close($updateStmt);

                /*
                 * Only delete the old file after the database update succeeds.
                 * Do not delete it if the same path is somehow retained.
                 */
                if (
                    $product['image'] !== null &&
                    $product['image'] !== '' &&
                    $product['image'] !== $imageValue
                ) {
                    $deleteProductImage($product['image']);
                }

                header(
                    "Location: {$basePath}/modules/products/index.php?updated=1"
                );
                exit;
            }

            $errors[] =
                'Unable to update the product. Please try again.';

            mysqli_stmt_close($updateStmt);

            /*
             * The new file is not referenced by the database because the
             * UPDATE failed, so remove it to prevent an orphan file.
             */
            if ($newImagePath !== null) {
                $deleteProductImage($newImagePath);
            }

            $newImagePath = null;
            $newImageAbsolutePath = null;

            /*
             * Restore the currently stored image value for the form state.
             */
            $imageValue = $product['image'] ?? null;

        } else {

            $errors[] =
                'Unable to prepare the product update.';

            if ($newImagePath !== null) {
                $deleteProductImage($newImagePath);
            }

            $newImagePath = null;
            $newImageAbsolutePath = null;
            $imageValue = $product['image'] ?? null;
        }
    } else {

        /*
         * Validation failed after a new file was already moved into storage.
         * Remove that file because the database will not reference it.
         */
        if ($newImagePath !== null) {
            $deleteProductImage($newImagePath);
        }

        $newImagePath = null;
        $newImageAbsolutePath = null;
        $imageValue = $product['image'] ?? null;
    }
}

/*
|--------------------------------------------------------------------------
| SHARED HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>

<link rel="stylesheet" href="../../assets/css/layout.css">
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">

<style>
    .edit-product-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 30px;
    }

    .edit-product-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 24px;
    }

    .edit-product-heading h1 {
        margin: 0 0 8px;
    }

    .edit-product-heading p {
        margin: 0;
    }

    .edit-product-back {
        text-decoration: none;
    }

    .edit-product-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    }

    .edit-product-form {
        display: grid;
        gap: 22px;
    }

    .edit-product-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .edit-product-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .edit-product-field.full-width {
        grid-column: 1 / -1;
    }

    .edit-product-field label {
        font-weight: 600;
    }

    .edit-product-field input,
    .edit-product-field select {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 14px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background: #ffffff;
        font: inherit;
    }

    .edit-product-field input:focus,
    .edit-product-field select:focus {
        outline: none;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    }

    .edit-product-help {
        margin: 0;
        color: #64748b;
        font-size: 13px;
    }

    .edit-product-current-image {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #f8fafc;
    }

    .edit-product-current-image img {
        width: 90px;
        height: 90px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
    }

    .edit-product-current-image-info {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .edit-product-current-image-title {
        margin: 0;
        font-weight: 600;
        color: #1f2937;
    }

    .edit-product-current-image-path {
        margin: 0;
        color: #64748b;
        font-size: 13px;
        word-break: break-all;
    }

    .edit-product-remove-image {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
        color: #b91c1c;
        font-size: 13px;
    }

    .edit-product-remove-image input {
        width: auto;
        margin: 0;
    }

    .edit-product-errors {
        margin-bottom: 22px;
        padding: 14px 18px;
        border-radius: 10px;
        background: #fee2e2;
        color: #991b1b;
    }

    .edit-product-errors ul {
        margin: 0;
        padding-left: 20px;
    }

    .edit-product-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 8px;
    }

    .edit-product-button,
    .edit-product-cancel {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 11px 18px;
        border-radius: 10px;
        text-decoration: none;
        border: 0;
        cursor: pointer;
        font: inherit;
    }

    .edit-product-button {
        background: #10b981;
        color: #ffffff;
    }

    .edit-product-cancel {
        background: #e5e7eb;
        color: #1f2937;
    }

    @media (max-width: 760px) {
        .edit-product-grid {
            grid-template-columns: 1fr;
        }

        .edit-product-field.full-width {
            grid-column: auto;
        }

        .edit-product-header {
            flex-direction: column;
        }

        .edit-product-page {
            padding: 20px;
        }

        .edit-product-card {
            padding: 20px;
        }

        .edit-product-current-image {
            align-items: flex-start;
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

            <div class="edit-product-page">

                <div class="edit-product-header">

                    <div class="edit-product-heading">
                        <h1>Edit Product</h1>
                        <p>
                            Update the product information and inventory details.
                        </p>
                    </div>

                    <a
                        href="<?php echo e($basePath); ?>/modules/products/index.php"
                        class="edit-product-cancel"
                    >
                        Back to Product List
                    </a>

                </div>

                <?php if (!empty($errors)): ?>

                    <div class="edit-product-errors" role="alert">

                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>

                    </div>

                <?php endif; ?>

                <div class="edit-product-card">

                    <form
                        method="post"
                        action="<?php echo e($basePath); ?>/modules/products/edit.php?id=<?php echo (int) $productId; ?>"
                        class="edit-product-form"
                        enctype="multipart/form-data"
                        novalidate
                    >

                        <div class="edit-product-grid">

                            <div class="edit-product-field full-width">

                                <label for="product_name">
                                    Product Name
                                </label>

                                <input
                                    type="text"
                                    id="product_name"
                                    name="product_name"
                                    maxlength="150"
                                    required
                                    value="<?php echo e($productName); ?>"
                                >

                            </div>

                            <div class="edit-product-field">

                                <label for="category_id">
                                    Category
                                </label>

                                <select
                                    id="category_id"
                                    name="category_id"
                                    required
                                >

                                    <option value="0">
                                        Select Category
                                    </option>

                                    <?php foreach ($categories as $category): ?>

                                        <option
                                            value="<?php echo (int) $category['category_id']; ?>"
                                            <?php echo $categoryId === (int) $category['category_id'] ? 'selected' : ''; ?>
                                        >
                                            <?php echo e($category['category_name']); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="edit-product-field">

                                <label for="supplier_id">
                                    Supplier
                                </label>

                                <select
                                    id="supplier_id"
                                    name="supplier_id"
                                >

                                    <option value="0">
                                        No Supplier
                                    </option>

                                    <?php foreach ($suppliers as $supplier): ?>

                                        <?php
                                        $supplierLabel =
                                            $supplier['supplier_name'];

                                        if (!empty($supplier['company'])) {
                                            $supplierLabel .=
                                                ' — ' .
                                                $supplier['company'];
                                        }
                                        ?>

                                        <option
                                            value="<?php echo (int) $supplier['supplier_id']; ?>"
                                            <?php echo $supplierId === (int) $supplier['supplier_id'] ? 'selected' : ''; ?>
                                        >
                                            <?php echo e($supplierLabel); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="edit-product-field">

                                <label for="barcode">
                                    Barcode
                                </label>

                                <input
                                    type="text"
                                    id="barcode"
                                    name="barcode"
                                    maxlength="50"
                                    value="<?php echo e($barcode); ?>"
                                >

                                <p class="edit-product-help">
                                    Leave blank if this product does not have a barcode.
                                </p>

                            </div>

                            <div class="edit-product-field">

                                <label for="unit">
                                    Unit
                                </label>

                                <input
                                    type="text"
                                    id="unit"
                                    name="unit"
                                    maxlength="20"
                                    required
                                    value="<?php echo e($unit); ?>"
                                >

                            </div>

                            <div class="edit-product-field">

                                <label for="purchase_price">
                                    Purchase Price
                                </label>

                                <input
                                    type="number"
                                    id="purchase_price"
                                    name="purchase_price"
                                    min="0"
                                    step="0.01"
                                    required
                                    value="<?php echo e($purchasePrice); ?>"
                                >

                            </div>

                            <div class="edit-product-field">

                                <label for="selling_price">
                                    Selling Price
                                </label>

                                <input
                                    type="number"
                                    id="selling_price"
                                    name="selling_price"
                                    min="0"
                                    step="0.01"
                                    required
                                    value="<?php echo e($sellingPrice); ?>"
                                >

                            </div>

                            <div class="edit-product-field">

                                <label for="stock">
                                    Stock
                                </label>

                                <input
                                    type="number"
                                    id="stock"
                                    name="stock"
                                    min="0"
                                    step="1"
                                    required
                                    value="<?php echo e($stock); ?>"
                                >

                            </div>

                            <div class="edit-product-field">

                                <label for="minimum_stock">
                                    Minimum Stock
                                </label>

                                <input
                                    type="number"
                                    id="minimum_stock"
                                    name="minimum_stock"
                                    min="0"
                                    step="1"
                                    required
                                    value="<?php echo e($minimumStock); ?>"
                                >

                            </div>

                            <div class="edit-product-field">

                                <label for="status">
                                    Status
                                </label>

                                <select
                                    id="status"
                                    name="status"
                                    required
                                >

                                    <option
                                        value="Active"
                                        <?php echo $status === 'Active' ? 'selected' : ''; ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="Inactive"
                                        <?php echo $status === 'Inactive' ? 'selected' : ''; ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>

                            <div class="edit-product-field full-width">

                                <label for="product-image">
                                    Product Image
                                </label>

                                <?php if (!empty($product['image'])): ?>

                                    <?php
                                    $currentImagePath =
                                        '../../' .
                                        ltrim(
                                            (string) $product['image'],
                                            '/'
                                        );
                                    ?>

                                    <div class="edit-product-current-image">

                                        <img
                                            src="<?php echo e($currentImagePath); ?>"
                                            alt="<?php echo e($productName); ?>"
                                            onerror="this.style.display='none';"
                                        >

                                        <div class="edit-product-current-image-info">

                                            <p class="edit-product-current-image-title">
                                                Current product image
                                            </p>

                                            <p class="edit-product-current-image-path">
                                                <?php echo e($product['image']); ?>
                                            </p>

                                            <label class="edit-product-remove-image">
                                                <input
                                                    type="checkbox"
                                                    name="remove_image"
                                                    value="1"
                                                >
                                                Remove current image
                                            </label>

                                        </div>

                                    </div>

                                <?php endif; ?>

                                <input
                                    type="file"
                                    id="product-image"
                                    name="product_image"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                >

                                <p class="edit-product-help">
                                    Optional. JPG/JPEG, PNG, or WebP only. Maximum size: 2 MB.
                                    Uploading a new image replaces the current image.
                                </p>

                            </div>

                        </div>

                        <div class="edit-product-actions">

                            <a
                                href="<?php echo e($basePath); ?>/modules/products/index.php"
                                class="edit-product-cancel"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="edit-product-button"
                            >
                                Save Changes
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </main>

    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
