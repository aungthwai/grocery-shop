<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Add Product";
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name      = mysqli_real_escape_string($conn, trim($_POST['product_name']));
    $category  = (int)($_POST['category_id'] ?? 0);
    $supplier  = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 'NULL';
    $barcode   = mysqli_real_escape_string($conn, trim($_POST['barcode'] ?? ''));
    $unit      = mysqli_real_escape_string($conn, $_POST['unit'] ?? 'pcs');
    $buyPrice  = (float)($_POST['purchase_price'] ?? 0);
    $sellPrice = (float)($_POST['selling_price'] ?? 0);
    $stock     = (int)($_POST['stock'] ?? 0);
    $minStock  = (int)($_POST['minimum_stock'] ?? 5);
    
    // If category is not selected, select first category from DB
    if ($category <= 0) {
        $firstCat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT category_id FROM categories ORDER BY category_id ASC LIMIT 1"));
        if ($firstCat) {
            $category = (int)$firstCat['category_id'];
        }
    }

    // Check duplicate barcode before query
    if ($barcode !== '') {
        $checkBarcode = mysqli_query($conn, "SELECT product_id, product_name FROM products WHERE barcode='$barcode'");
        if (mysqli_num_rows($checkBarcode) > 0) {
            $existing = mysqli_fetch_assoc($checkBarcode);
            $msg = "<div class='alert alert-danger'>⚠️ Barcode <strong>'" . htmlspecialchars($barcode) . "'</strong> is already assigned to product <strong>'" . htmlspecialchars($existing['product_name']) . "'</strong>. Please use a unique barcode or leave it blank.</div>";
        }
    }

    if (empty($msg)) {
        if (!empty($name) && $category > 0) {
            $barcodeSQL  = $barcode !== '' ? "'$barcode'" : 'NULL';
            $supplierSQL = is_int($supplier) && $supplier > 0 ? $supplier : 'NULL';
            
            try {
                $sql = "INSERT INTO products (product_name, category_id, supplier_id, barcode, unit, purchase_price, selling_price, stock, minimum_stock, status)
                        VALUES ('$name', $category, $supplierSQL, $barcodeSQL, '$unit', $buyPrice, $sellPrice, $stock, $minStock, 'Active')";
                
                if (mysqli_query($conn, $sql)) {
                    header("Location: index.php?msg=added");
                    exit;
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $msg = "<div class='alert alert-danger'>⚠️ Barcode <strong>'" . htmlspecialchars($barcode) . "'</strong> already exists in the database. Please enter a different barcode.</div>";
                } else {
                    $msg = "<div class='alert alert-danger'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
        } else {
            $msg = "<div class='alert alert-danger'>❌ Please enter product name and select a category.</div>";
        }
    }
}

$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");
$suppliers  = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY supplier_name");
require_once "../../includes/header.php";
?>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">
        <div class="page-header">
          <div><h1>➕ Add New Product</h1><p>Add a new product to the system.</p></div>
          <a href="index.php" class="btn btn-secondary">← Back to Products</a>
        </div>
        <?php echo $msg; ?>
        <div class="card">
          <form method="POST">
            <div class="form-grid">
              <div class="form-group form-full"><label>Product Name *</label><input type="text" name="product_name" value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>" required placeholder="e.g. Basmati Rice 1kg"></div>
              <div class="form-group"><label>Category *</label>
                <select name="category_id" required>
                  <option value="">-- Select Category --</option>
                  <?php while($r=mysqli_fetch_assoc($categories)): ?>
                  <option value="<?php echo $r['category_id']; ?>" <?php echo (($_POST['category_id']??'') == $r['category_id'])?'selected':''; ?>><?php echo htmlspecialchars($r['category_name']); ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group"><label>Supplier</label>
                <select name="supplier_id">
                  <option value="">-- None --</option>
                  <?php while($r=mysqli_fetch_assoc($suppliers)): ?>
                  <option value="<?php echo $r['supplier_id']; ?>" <?php echo (($_POST['supplier_id']??'') == $r['supplier_id'])?'selected':''; ?>><?php echo htmlspecialchars($r['supplier_name']); ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group"><label>Unit</label>
                <select name="unit">
                  <option value="pcs">Pieces (pcs)</option>
                  <option value="kg">Kilogram (kg)</option>
                  <option value="g">Gram (g)</option>
                  <option value="L">Liter (L)</option>
                  <option value="ml">Milliliter (ml)</option>
                  <option value="pack">Pack</option>
                  <option value="dozen">Dozen</option>
                  <option value="box">Box</option>
                </select>
              </div>
              <div class="form-group"><label>Barcode (Optional)</label><input type="text" name="barcode" value="<?php echo htmlspecialchars($_POST['barcode'] ?? ''); ?>" placeholder="Scan or type barcode (Must be unique)"></div>
              <div class="form-group"><label>Purchase Price (৳) *</label><input type="number" name="purchase_price" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['purchase_price'] ?? ''); ?>" required placeholder="0.00"></div>
              <div class="form-group"><label>Selling Price (৳) *</label><input type="number" name="selling_price" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['selling_price'] ?? ''); ?>" required placeholder="0.00"></div>
              <div class="form-group"><label>Initial Stock</label><input type="number" name="stock" min="0" value="<?php echo htmlspecialchars($_POST['stock'] ?? '0'); ?>"></div>
              <div class="form-group"><label>Minimum Stock (Alert Threshold)</label><input type="number" name="minimum_stock" min="0" value="<?php echo htmlspecialchars($_POST['minimum_stock'] ?? '5'); ?>"></div>
            </div>
            <div class="form-actions" style="margin-top:16px;">
              <button type="submit" class="btn btn-primary">Add Product ✅</button>
              <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
