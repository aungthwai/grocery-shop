<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Inventory";

// Stats
$totalProducts  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM products WHERE status='Active'"))['c'];
$lowStock       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM products WHERE stock <= minimum_stock AND status='Active'"))['c'];
$outOfStock     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM products WHERE stock = 0 AND status='Active'"))['c'];
$inventoryValue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(stock * purchase_price), 0) v FROM products WHERE status='Active'"))['v'];

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = "WHERE p.status='Active'";
if ($filter === 'low')  $where .= " AND p.stock <= p.minimum_stock AND p.stock > 0";
if ($filter === 'out')  $where .= " AND p.stock = 0";
if ($search) $where .= " AND p.product_name LIKE '%$search%'";

$products = mysqli_query($conn, "
    SELECT p.*, c.category_name, s.supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    $where
    ORDER BY p.stock ASC
");

require_once "../../includes/header.php";
?>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">

        <div class="page-header">
          <div><h1>📦 Inventory Monitor</h1><p>Track all product stock levels in real-time.</p></div>
          <a href="../products/index.php" class="btn btn-primary">Manage Products</a>
        </div>

        <!-- Stats -->
        <div class="stats-row">
          <div class="stat-card blue"><div class="stat-label">Total Active Products</div><div class="stat-value"><?php echo $totalProducts; ?></div></div>
          <div class="stat-card amber"><div class="stat-label">Low Stock Alerts</div><div class="stat-value"><?php echo $lowStock; ?></div></div>
          <div class="stat-card red"><div class="stat-label">Out of Stock</div><div class="stat-value"><?php echo $outOfStock; ?></div></div>
          <div class="stat-card green"><div class="stat-label">Inventory Value</div><div class="stat-value">৳<?php echo number_format((float)$inventoryValue, 0); ?></div></div>
        </div>

        <!-- Filter + Search -->
        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
            <div style="display:flex; gap:8px;">
              <a href="?filter=all" class="btn btn-sm <?php echo $filter=='all'?'btn-primary':'btn-secondary'; ?>">All</a>
              <a href="?filter=low" class="btn btn-sm <?php echo $filter=='low'?'btn-warning':'btn-secondary'; ?>">Low Stock</a>
              <a href="?filter=out" class="btn btn-sm <?php echo $filter=='out'?'btn-danger':'btn-secondary'; ?>">Out of Stock</a>
            </div>
            <form method="GET" class="search-bar" style="margin:0;">
              <input type="hidden" name="filter" value="<?php echo $filter; ?>">
              <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search product...">
              <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            </form>
          </div>
          <div class="table-wrapper">
            <table class="data-table">
              <thead><tr><th>#</th><th>Product Name</th><th>Category</th><th>Supplier</th><th>Purchase Price</th><th>Selling Price</th><th>Stock</th><th>Min. Stock</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (mysqli_num_rows($products) === 0): ?>
                  <tr class="empty-row"><td colspan="9">No products found.</td></tr>
                <?php else: while($row = mysqli_fetch_assoc($products)): 
                    $stockOk  = $row['stock'] > $row['minimum_stock'];
                    $stockLow = $row['stock'] > 0 && $row['stock'] <= $row['minimum_stock'];
                    $stockOut = $row['stock'] == 0;
                    if ($stockOut) $badge = "<span class='badge badge-danger'>Out of Stock</span>";
                    elseif ($stockLow) $badge = "<span class='badge badge-warning'>Low Stock</span>";
                    else $badge = "<span class='badge badge-success'>In Stock</span>";
                ?>
                <tr>
                  <td><?php echo $row['product_id']; ?></td>
                  <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($row['category_name'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['supplier_name'] ?? '-'); ?></td>
                  <td>৳<?php echo number_format($row['purchase_price'], 2); ?></td>
                  <td>৳<?php echo number_format($row['selling_price'], 2); ?></td>
                  <td style="font-weight:700; <?php echo $stockOut ? 'color:#ef4444' : ($stockLow ? 'color:#f59e0b' : 'color:#22c55e'); ?>"><?php echo $row['stock']; ?> <?php echo $row['unit']; ?></td>
                  <td><?php echo $row['minimum_stock']; ?></td>
                  <td><?php echo $badge; ?></td>
                </tr>
                <?php endwhile; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
