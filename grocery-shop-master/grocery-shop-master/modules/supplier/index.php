<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Supplier Management";
$msg = "";

// ADD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_supplier'])) {
    $name    = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $company = trim(mysqli_real_escape_string($conn, $_POST['company']));
    $phone   = trim(mysqli_real_escape_string($conn, $_POST['phone']));
    $email   = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $address = trim(mysqli_real_escape_string($conn, $_POST['address']));
    if ($name) {
        mysqli_query($conn, "INSERT INTO suppliers (supplier_name, company, phone, email, address) VALUES ('$name','$company','$phone','$email','$address')");
        $msg = "<div class='alert alert-success'>✅ Supplier added successfully!</div>";
    }
}

// UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_supplier'])) {
    $id      = (int)$_POST['sid'];
    $name    = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $company = trim(mysqli_real_escape_string($conn, $_POST['company']));
    $phone   = trim(mysqli_real_escape_string($conn, $_POST['phone']));
    $email   = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $address = trim(mysqli_real_escape_string($conn, $_POST['address']));
    mysqli_query($conn, "UPDATE suppliers SET supplier_name='$name', company='$company', phone='$phone', email='$email', address='$address' WHERE supplier_id=$id");
    $msg = "<div class='alert alert-success'>✅ Supplier updated!</div>";
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM suppliers WHERE supplier_id=$id");
    header("Location: index.php?deleted=1"); exit;
}
if (isset($_GET['deleted'])) $msg = "<div class='alert alert-success'>✅ Supplier deleted.</div>";

// Edit
$editSupplier = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $r = mysqli_query($conn, "SELECT * FROM suppliers WHERE supplier_id=$eid");
    $editSupplier = mysqli_fetch_assoc($r);
}

// Stats
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM suppliers"))['c'];
$totalProducts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM products WHERE supplier_id IS NOT NULL"))['c'];

// Search
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$where = $search ? "WHERE supplier_name LIKE '%$search%' OR company LIKE '%$search%' OR phone LIKE '%$search%'" : "";
$suppliers = mysqli_query($conn, "SELECT s.*, COUNT(p.product_id) as product_count FROM suppliers s LEFT JOIN products p ON s.supplier_id = p.supplier_id $where GROUP BY s.supplier_id ORDER BY s.supplier_id DESC");

require_once "../../includes/header.php";
?>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">

        <div class="page-header">
          <div><h1>🏭 Supplier Management</h1><p>Manage your product suppliers and their details.</p></div>
          <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Supplier</button>
        </div>

        <?php echo $msg; ?>

        <div class="stats-row">
          <div class="stat-card blue"><div class="stat-label">Total Suppliers</div><div class="stat-value"><?php echo $total; ?></div></div>
          <div class="stat-card green"><div class="stat-label">Products Supplied</div><div class="stat-value"><?php echo $totalProducts; ?></div></div>
        </div>

        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div class="card-title" style="margin:0; border:none; padding:0;">All Suppliers</div>
            <form method="GET" class="search-bar" style="margin:0;">
              <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, company or phone...">
              <button type="submit" class="btn btn-secondary btn-sm">Search</button>
              <?php if ($search): ?><a href="index.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
            </form>
          </div>
          <div class="table-wrapper">
            <table class="data-table">
              <thead><tr><th>#</th><th>Name</th><th>Company</th><th>Phone</th><th>Email</th><th>Products</th><th>Action</th></tr></thead>
              <tbody>
                <?php if (mysqli_num_rows($suppliers) === 0): ?>
                  <tr class="empty-row"><td colspan="7">No suppliers found.</td></tr>
                <?php else: while($row = mysqli_fetch_assoc($suppliers)): ?>
                <tr>
                  <td><?php echo $row['supplier_id']; ?></td>
                  <td><strong><?php echo htmlspecialchars($row['supplier_name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($row['company'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                  <td><span class="badge badge-info"><?php echo $row['product_count']; ?> products</span></td>
                  <td class="actions">
                    <a href="?edit=<?php echo $row['supplier_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="?delete=<?php echo $row['supplier_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this supplier?')">Delete</a>
                  </td>
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

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header"><h3>Add New Supplier</h3><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Supplier Name *</label><input type="text" name="name" required placeholder="Full name"></div>
          <div class="form-group"><label>Company Name</label><input type="text" name="company" placeholder="Company Ltd."></div>
          <div class="form-group"><label>Phone Number</label><input type="text" name="phone" placeholder="01XXXXXXXXX"></div>
          <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="email@company.com"></div>
          <div class="form-group form-full"><label>Address</label><input type="text" name="address" placeholder="Street, City, Country"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button><button type="submit" name="add_supplier" class="btn btn-primary">Add Supplier</button></div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<?php if ($editSupplier): ?>
<div class="modal-overlay open" id="editModal">
  <div class="modal">
    <div class="modal-header"><h3>Edit Supplier</h3><button class="modal-close" onclick="window.location='index.php'">✕</button></div>
    <form method="POST">
      <input type="hidden" name="sid" value="<?php echo $editSupplier['supplier_id']; ?>">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Supplier Name *</label><input type="text" name="name" value="<?php echo htmlspecialchars($editSupplier['supplier_name']); ?>" required></div>
          <div class="form-group"><label>Company Name</label><input type="text" name="company" value="<?php echo htmlspecialchars($editSupplier['company']??''); ?>"></div>
          <div class="form-group"><label>Phone Number</label><input type="text" name="phone" value="<?php echo htmlspecialchars($editSupplier['phone']??''); ?>"></div>
          <div class="form-group"><label>Email Address</label><input type="email" name="email" value="<?php echo htmlspecialchars($editSupplier['email']??''); ?>"></div>
          <div class="form-group form-full"><label>Address</label><input type="text" name="address" value="<?php echo htmlspecialchars($editSupplier['address']??''); ?>"></div>
        </div>
      </div>
      <div class="modal-footer"><a href="index.php" class="btn btn-secondary">Cancel</a><button type="submit" name="update_supplier" class="btn btn-primary">Update Supplier</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
</script>
<?php require_once "../../includes/footer.php"; ?>
