<?php 
 
session_start(); 

/* 
|--------------------------------------------------------------------------
| ADD PRODUCT PAGE 
|--------------------------------------------------------------------------
| Creates a new product using the existing products table and the existing 
| categories/suppliers relationships. 
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
$pageTitle = "Add Product"; 

/* 
|--------------------------------------------------------------------------
| SHARED HEADER 
|--------------------------------------------------------------------------
| header.php provides the shared e() output-escaping helper. 
|--------------------------------------------------------------------------
*/ 

require_once "../../includes/header.php"; 

/* 
|--------------------------------------------------------------------------
| FORM STATE 
|--------------------------------------------------------------------------
*/ 

$errors = []; 
$success = ''; 

$productName = ''; 
$categoryId = 0; 
$supplierId = 0; 
$barcode = ''; 
$unit = 'pcs'; 
$purchasePrice = ''; 
$sellingPrice = ''; 
$stock = '0'; 
$minimumStock = '0'; 
$status = 'Active'; 

/* 
|--------------------------------------------------------------------------
| LOAD CATEGORIES 
|--------------------------------------------------------------------------
*/ 

$categories = []; 

$categorySql = " 
    SELECT category_id, category_name 
    FROM categories 
    ORDER BY category_name ASC 
"; 

$categoryResult = mysqli_query($conn, $categorySql); 

if ($categoryResult) { 
    while ($row = mysqli_fetch_assoc($categoryResult)) { 
        $categories[] = $row; 
    } 
} else { 
    $errors[] = "Unable to load product categories."; 
} 

/* 
|--------------------------------------------------------------------------
| LOAD SUPPLIERS 
|--------------------------------------------------------------------------
*/ 

$suppliers = []; 

$supplierSql = " 
    SELECT supplier_id, supplier_name, company 
    FROM suppliers 
    ORDER BY supplier_name ASC 
"; 

$supplierResult = mysqli_query($conn, $supplierSql); 

if ($supplierResult) { 
    while ($row = mysqli_fetch_assoc($supplierResult)) { 
        $suppliers[] = $row; 
    } 
} else { 
    $errors[] = "Unable to load suppliers."; 
} 

/* 
|--------------------------------------------------------------------------
| PROCESS FORM SUBMISSION 
|--------------------------------------------------------------------------
*/ 

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 

    $productName = trim($_POST['product_name'] ?? ''); 
    $categoryId = filter_input( 
        INPUT_POST, 
        'category_id', 
        FILTER_VALIDATE_INT 
    ); 
    $supplierId = filter_input( 
        INPUT_POST, 
        'supplier_id', 
        FILTER_VALIDATE_INT 
    ); 
    $barcode = trim($_POST['barcode'] ?? ''); 
    $unit = trim($_POST['unit'] ?? ''); 
    $purchasePrice = trim($_POST['purchase_price'] ?? ''); 
    $sellingPrice = trim($_POST['selling_price'] ?? ''); 
    $stock = filter_input( 
        INPUT_POST, 
        'stock', 
        FILTER_VALIDATE_INT 
    ); 
    $minimumStock = filter_input( 
        INPUT_POST, 
        'minimum_stock', 
        FILTER_VALIDATE_INT 
    ); 
    $status = trim($_POST['status'] ?? ''); 

    /* 
    |--------------------------------------------------------------------------
    | NORMALIZE OPTIONAL VALUES 
    |--------------------------------------------------------------------------
    */ 

    if ($supplierId === false || $supplierId === null || $supplierId <= 0) { 
        $supplierId = 0; 
    } 

    if ($categoryId === false || $categoryId === null) { 
        $categoryId = 0; 
    } 

    if ($stock === false || $stock === null) { 
        $stock = -1; 
    } 

    if ($minimumStock === false || $minimumStock === null) { 
        $minimumStock = -1; 
    } 

    /* 
    |--------------------------------------------------------------------------
    | VALIDATION 
    |--------------------------------------------------------------------------
    */ 

    if ($productName === '') { 
        $errors[] = "Product name is required."; 
    } elseif (mb_strlen($productName) > 150) { 
        $errors[] = "Product name must not exceed 150 characters."; 
    } 

    if ($categoryId <= 0) { 
        $errors[] = "Please select a category."; 
    } 

    if ($supplierId < 0) { 
        $errors[] = "Please select a valid supplier."; 
    } 

    if ($barcode !== '' && mb_strlen($barcode) > 50) { 
        $errors[] = "Barcode must not exceed 50 characters."; 
    } 

    if ($unit === '') { 
        $errors[] = "Unit is required."; 
    } elseif (mb_strlen($unit) > 20) { 
        $errors[] = "Unit must not exceed 20 characters."; 
    } 

    if ($purchasePrice === '' || !is_numeric($purchasePrice)) { 
        $errors[] = "Purchase price must be a valid number."; 
    } elseif ((float) $purchasePrice < 0) { 
        $errors[] = "Purchase price cannot be negative."; 
    } 

    if ($sellingPrice === '' || !is_numeric($sellingPrice)) { 
        $errors[] = "Selling price must be a valid number."; 
    } elseif ((float) $sellingPrice < 0) { 
        $errors[] = "Selling price cannot be negative."; 
    } 

    if ($stock < 0) { 
        $errors[] = "Stock must be a whole number greater than or equal to 0."; 
    } 

    if ($minimumStock < 0) { 
        $errors[] = "Minimum stock must be a whole number greater than or equal to 0."; 
    } 

    if ($status !== 'Active' && $status !== 'Inactive') { 
        $errors[] = "Please select a valid product status."; 
    } 

    /* 
    |--------------------------------------------------------------------------
    | VERIFY CATEGORY / SUPPLIER RELATIONSHIPS 
    |--------------------------------------------------------------------------
    */ 

    if ($categoryId > 0) { 
        $categoryCheck = mysqli_prepare( 
            $conn, 
            "SELECT category_id FROM categories WHERE category_id = ? LIMIT 1" 
        ); 

        if ($categoryCheck) { 
            mysqli_stmt_bind_param($categoryCheck, 'i', $categoryId); 
            mysqli_stmt_execute($categoryCheck); 
            mysqli_stmt_store_result($categoryCheck); 

            if (mysqli_stmt_num_rows($categoryCheck) !== 1) { 
                $errors[] = "The selected category does not exist."; 
            } 

            mysqli_stmt_close($categoryCheck); 
        } else { 
            $errors[] = "Unable to verify the selected category."; 
        } 
    } 

    if ($supplierId > 0) { 
        $supplierCheck = mysqli_prepare( 
            $conn, 
            "SELECT supplier_id FROM suppliers WHERE supplier_id = ? LIMIT 1" 
        ); 

        if ($supplierCheck) { 
            mysqli_stmt_bind_param($supplierCheck, 'i', $supplierId); 
            mysqli_stmt_execute($supplierCheck); 
            mysqli_stmt_store_result($supplierCheck); 

            if (mysqli_stmt_num_rows($supplierCheck) !== 1) { 
                $errors[] = "The selected supplier does not exist."; 
            } 

            mysqli_stmt_close($supplierCheck); 
        } else { 
            $errors[] = "Unable to verify the selected supplier."; 
        } 
    } 

    /* 
    |--------------------------------------------------------------------------
    | PRODUCT IMAGE UPLOAD 
    |--------------------------------------------------------------------------
    | The image is optional. Only JPG/JPEG, PNG, and WebP images up to 2 MB 
    | are accepted. The actual MIME type and image contents are both checked. 
    | A generated filename is used and only the relative path is stored. 
    |--------------------------------------------------------------------------
    */ 

    $imageValue = null; 
    $uploadedImagePath = null; 
    $uploadedImageAbsolutePath = null; 

    if (
        isset($_FILES['product_image']) && 
        $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) { 

        $imageFile = $_FILES['product_image']; 

        if ($imageFile['error'] !== UPLOAD_ERR_OK) { 
            $errors[] = "Unable to upload the product image. Please try again."; 
        } elseif ($imageFile['size'] > 2 * 1024 * 1024) { 
            $errors[] = "Product image must not exceed 2 MB."; 
        } elseif (!is_uploaded_file($imageFile['tmp_name'])) { 
            $errors[] = "Invalid product image upload."; 
        } else { 

            $finfo = new finfo(FILEINFO_MIME_TYPE); 
            $detectedMime = $finfo->file($imageFile['tmp_name']); 

            $allowedMimeTypes = [ 
                'image/jpeg' => 'jpg', 
                'image/png' => 'png', 
                'image/webp' => 'webp', 
            ]; 

            if (!isset($allowedMimeTypes[$detectedMime])) { 
                $errors[] = "Only JPG, JPEG, PNG, and WebP images are allowed."; 
            } else { 

                $imageInfo = @getimagesize($imageFile['tmp_name']); 

                if ($imageInfo === false) { 
                    $errors[] = "The uploaded file is not a valid image."; 
                } else { 

                    $uploadDirectory = 
                        dirname(__DIR__, 2) . 
                        DIRECTORY_SEPARATOR . 
                        'uploads' . 
                        DIRECTORY_SEPARATOR . 
                        'products'; 

                    if (
                        !is_dir($uploadDirectory) && 
                        !mkdir($uploadDirectory, 0755, true)
                    ) { 
                        $errors[] = 
                            "Unable to create the product image upload directory."; 
                    } elseif (!is_writable($uploadDirectory)) { 
                        $errors[] = 
                            "The product image upload directory is not writable."; 
                    } else { 

                        try { 

                            $randomFilename = 
                                'product_' . 
                                bin2hex(random_bytes(16)) . 
                                '.' . 
                                $allowedMimeTypes[$detectedMime]; 

                            $uploadedImageAbsolutePath = 
                                $uploadDirectory . 
                                DIRECTORY_SEPARATOR . 
                                $randomFilename; 

                            $uploadedImagePath = 
                                'uploads/products/' . 
                                $randomFilename; 

                            if (
                                !move_uploaded_file(
                                    $imageFile['tmp_name'], 
                                    $uploadedImageAbsolutePath
                                )
                            ) { 

                                $errors[] = 
                                    "Unable to save the product image. Please try again."; 

                                $uploadedImageAbsolutePath = null; 
                                $uploadedImagePath = null; 

                            } else { 

                                $imageValue = $uploadedImagePath; 
                            } 

                        } catch (Throwable $uploadException) { 

                            $errors[] = 
                                "Unable to generate a safe product image filename."; 

                            $uploadedImageAbsolutePath = null; 
                            $uploadedImagePath = null; 
                        } 
                    } 
                } 
            } 
        } 
    } 

    /* 
    |--------------------------------------------------------------------------
    | INSERT PRODUCT 
    |--------------------------------------------------------------------------
    */ 

    if (empty($errors)) { 

        $purchasePriceValue = (float) $purchasePrice; 
        $sellingPriceValue = (float) $sellingPrice; 
        $supplierValue = $supplierId > 0 ? $supplierId : null; 

        $insertSql = " 
            INSERT INTO products ( 
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
        "; 

        $insertStatement = mysqli_prepare($conn, $insertSql); 

        if ($insertStatement) { 

            mysqli_stmt_bind_param( 
                $insertStatement, 
                'iisssddiiss', 
                $categoryId, 
                $supplierValue, 
                $productName, 
                $barcode, 
                $unit, 
                $purchasePriceValue, 
                $sellingPriceValue, 
                $stock, 
                $minimumStock, 
                $imageValue, 
                $status 
            ); 

            if (mysqli_stmt_execute($insertStatement)) { 

                $newProductId = mysqli_insert_id($conn); 

                mysqli_stmt_close($insertStatement); 

                header( 
                    "Location: " . 
                    $basePath . 
                    "/modules/products/index.php?added=1&id=" . 
                    (int) $newProductId 
                ); 

                exit; 
            } 

            /*
             * The image was successfully moved but the database insert
             * failed, so remove the uploaded file to prevent an orphan.
             */
            if (
                $uploadedImageAbsolutePath !== null && 
                is_file($uploadedImageAbsolutePath)
            ) { 
                @unlink($uploadedImageAbsolutePath); 
            } 

            $errors[] = 
                "Unable to add the product. Please try again."; 

            mysqli_stmt_close($insertStatement); 

        } else { 

            /*
             * The image was successfully moved but the INSERT statement
             * could not be prepared, so remove the uploaded file.
             */
            if (
                $uploadedImageAbsolutePath !== null && 
                is_file($uploadedImageAbsolutePath)
            ) { 
                @unlink($uploadedImageAbsolutePath); 
            } 

            $errors[] = 
                "Unable to prepare the product insert."; 
        } 

    } elseif (
        $uploadedImageAbsolutePath !== null && 
        is_file($uploadedImageAbsolutePath)
    ) { 

        /*
         * Any validation/upload-related error after a file was moved
         * must also clean up the file.
         */
        @unlink($uploadedImageAbsolutePath); 
    } 
} 

?> 
 
<link rel="stylesheet" href="../../assets/css/layout.css"> 
<link rel="stylesheet" href="../../assets/css/sidebar.css"> 
<link rel="stylesheet" href="../../assets/css/topbar.css"> 
 
<style> 
    .add-product-page { 
        max-width: 1100px; 
        margin: 0 auto; 
        padding: 32px; 
    } 
 
    .add-product-header { 
        display: flex; 
        align-items: flex-start; 
        justify-content: space-between; 
        gap: 24px; 
        margin-bottom: 24px; 
    } 
 
    .add-product-heading h1 { 
        margin: 0 0 8px; 
        font-size: 30px; 
    } 
 
    .add-product-heading p { 
        margin: 0; 
    } 
 
    .back-product-button { 
        display: inline-flex; 
        align-items: center; 
        gap: 8px; 
        padding: 10px 16px; 
        border: 1px solid #d1d5db; 
        border-radius: 10px; 
        background: #fff; 
        color: #374151; 
        text-decoration: none; 
        white-space: nowrap; 
    } 
 
    .add-product-card { 
        background: #fff; 
        border: 1px solid #e5e7eb; 
        border-radius: 16px; 
        padding: 28px; 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); 
    } 
 
    .add-product-form { 
        display: grid; 
        grid-template-columns: repeat(2, minmax(0, 1fr)); 
        gap: 20px; 
    } 
 
    .add-product-field { 
        display: flex; 
        flex-direction: column; 
        gap: 8px; 
    } 
 
    .add-product-field.full-width { 
        grid-column: 1 / -1; 
    } 
 
    .add-product-field label { 
        font-weight: 600; 
        color: #374151; 
    } 
 
    .add-product-field input, 
    .add-product-field select { 
        width: 100%; 
        box-sizing: border-box; 
        padding: 11px 13px; 
        border: 1px solid #d1d5db; 
        border-radius: 10px; 
        background: #fff; 
        color: #111827; 
        font: inherit; 
    } 
 
    .add-product-field input:focus, 
    .add-product-field select:focus { 
        outline: none; 
        border-color: #10b981; 
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12); 
    } 
 
    .add-product-help { 
        margin: 0; 
        font-size: 13px; 
        color: #6b7280; 
    } 
 
    .add-product-errors { 
        margin-bottom: 20px; 
        padding: 14px 16px; 
        border: 1px solid #fecaca; 
        border-radius: 10px; 
        background: #fef2f2; 
        color: #991b1b; 
    } 
 
    .add-product-errors ul { 
        margin: 0; 
        padding-left: 20px; 
    } 
 
    .add-product-actions { 
        grid-column: 1 / -1; 
        display: flex; 
        justify-content: flex-end; 
        gap: 12px; 
        margin-top: 8px; 
    } 
 
    .add-product-cancel, 
    .add-product-submit { 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        min-width: 120px; 
        padding: 11px 18px; 
        border-radius: 10px; 
        font: inherit; 
        font-weight: 600; 
        text-decoration: none; 
        cursor: pointer; 
    } 
 
    .add-product-cancel { 
        border: 1px solid #d1d5db; 
        background: #fff; 
        color: #374151; 
    } 
 
    .add-product-submit { 
        border: 1px solid #10b981; 
        background: #10b981; 
        color: #fff; 
    } 
 
    .add-product-submit:hover { 
        background: #059669; 
        border-color: #059669; 
    } 
 
    @media (max-width: 760px) { 
        .add-product-page { 
            padding: 20px; 
        } 
 
        .add-product-header { 
            flex-direction: column; 
        } 
 
        .add-product-form { 
            grid-template-columns: 1fr; 
        } 
 
        .add-product-field.full-width, 
        .add-product-actions { 
            grid-column: auto; 
        } 
 
        .add-product-actions { 
            justify-content: stretch; 
        } 
 
        .add-product-cancel, 
        .add-product-submit { 
            flex: 1; 
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
 
            <div class="add-product-page"> 
 
                <div class="add-product-header"> 
                    <div class="add-product-heading"> 
                        <h1>Add Product</h1> 
                        <p>Add a new product to your inventory.</p> 
                    </div> 
 
                    <a 
                        href="<?php echo e($basePath); ?>/modules/products/index.php" 
                        class="back-product-button" 
                    > 
                        â† Product List 
                    </a> 
                </div> 
 
                <section class="add-product-card"> 
 
                    <?php if (!empty($errors)): ?> 
                        <div class="add-product-errors" role="alert"> 
                            <ul> 
                                <?php foreach ($errors as $error): ?> 
                                    <li><?php echo e($error); ?></li> 
                                <?php endforeach; ?> 
                            </ul> 
                        </div> 
                    <?php endif; ?> 
 
                    <form 
                        method="POST" 
                        action="<?php echo e($basePath); ?>/modules/products/add.php" 
                        class="add-product-form" 
                        enctype="multipart/form-data" 
                    > 
 
                        <div class="add-product-field full-width"> 
                            <label for="product-name">Product Name *</label> 
                            <input 
                                type="text" 
                                id="product-name" 
                                name="product_name" 
                                maxlength="150" 
                                value="<?php echo e($productName); ?>" 
                                required 
                            > 
                        </div> 
 
                        <div class="add-product-field"> 
                            <label for="category-id">Category *</label> 
                            <select id="category-id" name="category_id" required> 
                                <option value="">Select Category</option> 
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
 
                        <div class="add-product-field"> 
                            <label for="supplier-id">Supplier</label> 
                            <select id="supplier-id" name="supplier_id"> 
                                <option value="0">No Supplier</option> 
                                <?php foreach ($suppliers as $supplier): ?> 
                                    <?php 
                                    $supplierLabel = $supplier['supplier_name']; 
                                    if (!empty($supplier['company'])) { 
                                        $supplierLabel .= ' â€” ' . $supplier['company']; 
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
 
                        <div class="add-product-field"> 
                            <label for="barcode">Barcode</label> 
                            <input 
                                type="text" 
                                id="barcode" 
                                name="barcode" 
                                maxlength="50" 
                                value="<?php echo e($barcode); ?>" 
                            > 
                        </div> 
 
                        <div class="add-product-field"> 
                            <label for="unit">Unit *</label> 
                            <input 
                                type="text" 
                                id="unit" 
                                name="unit" 
                                maxlength="20" 
                                value="<?php echo e($unit); ?>" 
                                required 
                            > 
                            <p class="add-product-help">Example: pcs, kg, liter, box</p> 
                        </div> 
 
                        <div class="add-product-field"> 
                            <label for="purchase-price">Purchase Price *</label> 
                            <input 
                                type="number" 
                                id="purchase-price" 
                                name="purchase_price" 
                                min="0" 
                                step="0.01" 
                                value="<?php echo e($purchasePrice); ?>" 
                                required 
                            > 
                        </div> 
 
                        <div class="add-product-field"> 
                            <label for="selling-price">Selling Price *</label> 
                            <input 
                                type="number" 
                                id="selling-price" 
                                name="selling_price" 
                                min="0" 
                                step="0.01" 
                                value="<?php echo e($sellingPrice); ?>" 
                                required 
                            > 
                        </div> 
 
                        <div class="add-product-field"> 
                            <label for="stock">Stock *</label> 
                            <input 
                                type="number" 
                                id="stock" 
                                name="stock" 
                                min="0" 
                                step="1" 
                                value="<?php echo (int) $stock >= 0 ? (int) $stock : ''; ?>" 
                                required 
                            > 
                        </div> 
 
                        <div class="add-product-field"> 
                            <label for="minimum-stock">Minimum Stock *</label> 
                            <input 
                                type="number" 
                                id="minimum-stock" 
                                name="minimum_stock" 
                                min="0" 
                                step="1" 
                                value="<?php echo (int) $minimumStock >= 0 ? (int) $minimumStock : ''; ?>" 
                                required 
                            > 
                        </div> 
 
                        <div class="add-product-field"> 
                            <label for="status">Status *</label> 
                            <select id="status" name="status" required> 
                                <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option> 
                                <option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option> 
                            </select> 
                        </div> 

                        <div class="add-product-field full-width"> 
                            <label for="product-image">Product Image</label> 
                            <input 
                                type="file" 
                                id="product-image" 
                                name="product_image" 
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" 
                            > 
                            <p class="add-product-help">
                                Optional. JPG/JPEG, PNG, or WebP. Maximum size: 2 MB.
                            </p> 
                        </div> 
 
                        <div class="add-product-actions"> 
                            <a 
                                href="<?php echo e($basePath); ?>/modules/products/index.php" 
                                class="add-product-cancel" 
                            > 
                                Cancel 
                            </a> 
 
                            <button 
                                type="submit" 
                                class="add-product-submit" 
                            > 
                                Add Product 
                            </button> 
                        </div> 
 
                    </form> 
 
                </section> 
 
            </div> 
 
        </main> 
 
    </div> 
 
</div> 
 
<?php require_once "../../includes/footer.php"; ?>