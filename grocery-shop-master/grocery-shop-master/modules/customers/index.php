<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Customer Management";

$msg = "";

// ADD CUSTOMER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_customer'])) {
    $name  = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $phone = trim(mysqli_real_escape_string($conn, $_POST['phone']));
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $addr  = trim(mysqli_real_escape_string($conn, $_POST['address']));
    if ($name) {
        mysqli_query($conn, "INSERT INTO customers (customer_name, phone, email, address) VALUES ('$name','$phone','$email','$addr')");
        $msg = "<div class='alert alert-success'>✅ Customer added successfully!</div>";
    }
}

// UPDATE CUSTOMER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_customer'])) {
    $id    = (int)$_POST['cid'];
    $name  = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $phone = trim(mysqli_real_escape_string($conn, $_POST['phone']));
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $addr  = trim(mysqli_real_escape_string($conn, $_POST['address']));
    mysqli_query($conn, "UPDATE customers SET customer_name='$name', phone='$phone', email='$email', address='$addr' WHERE customer_id=$id");
    $msg = "<div class='alert alert-success'>✅ Customer updated successfully!</div>";
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM customers WHERE customer_id=$id");
    header("Location: index.php?deleted=1"); exit;
}
if (isset($_GET['deleted'])) $msg = "<div class='alert alert-success'>✅ Customer deleted.</div>";

// Get edit customer
$editCustomer = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $r = mysqli_query($conn, "SELECT * FROM customers WHERE customer_id=$eid");
    $editCustomer = mysqli_fetch_assoc($r);
}

// Stats
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM customers"))['c'];
$withDue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM customers WHERE total_due > 0"))['c'];
$totalDue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_due),0) s FROM customers"))['s'];

// Search
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$where = $search ? "WHERE customer_name LIKE '%$search%' OR phone LIKE '%$search%'" : "";
$customers = mysqli_query($conn, "SELECT * FROM customers $where ORDER BY customer_id DESC");

require_once "../../includes/header.php";
?>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">
        
        <div class="page-header">
          <div><h1>👥 Customer Management</h1><p>Manage your grocery store customers and track dues.</p></div>
          <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Customer</button>
        </div>
        
        <?php echo $msg; ?>
        
        <!-- Stats -->
        <div class="stats-row">
          <div class="stat-card blue"><div class="stat-label">Total Customers</div><div class="stat-value"><?php echo $total; ?></div></div>
          <div class="stat-card red"><div class="stat-label">Customers with Due</div><div class="stat-value"><?php echo $withDue; ?></div></div>
          <div class="stat-card amber"><div class="stat-label">Total Outstanding Due</div><div class="stat-value">৳<?php echo number_format($totalDue, 2); ?></div></div>
        </div>

        <!-- Table -->
        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div class="card-title" style="margin:0; border:none; padding:0;">All Customers</div>
            <form method="GET" class="search-bar" style="margin:0;">
              <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or phone...">
              <button type="submit" class="btn btn-secondary btn-sm">Search</button>
              <?php if ($search): ?><a href="index.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
            </form>
          </div>
          <div class="table-wrapper">
            <table class="data-table">
              <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Due</th><th>Action</th></tr></thead>
              <tbody>
                <?php if (mysqli_num_rows($customers) === 0): ?>
                  <tr class="empty-row"><td colspan="7">No customers found.</td></tr>
                <?php else: while($row = mysqli_fetch_assoc($customers)): ?>
                <tr>
                  <td><?php echo $row['customer_id']; ?></td>
                  <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['address'] ?? '-'); ?></td>
                  <td><?php if($row['total_due'] > 0): ?><span class="badge badge-danger">৳<?php echo number_format($row['total_due'],2); ?></span><?php else: ?><span class="badge badge-success">Clear</span><?php endif; ?></td>
                  <td class="actions">
                    <a href="?edit=<?php echo $row['customer_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="?delete=<?php echo $row['customer_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this customer?')">Delete</a>
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
    <div class="modal-header"><h3>Add New Customer</h3><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Customer Name *</label><input type="text" name="name" required placeholder="Full name"></div>
          <div class="form-group"><label>Phone Number</label><input type="text" name="phone" placeholder="01XXXXXXXXX"></div>
          <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="example@email.com"></div>
          <div class="form-group"><label>Address</label><input type="text" name="address" placeholder="Street, City"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button><button type="submit" name="add_customer" class="btn btn-primary">Add Customer</button></div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<?php if ($editCustomer): ?>
<div class="modal-overlay open" id="editModal">
  <div class="modal">
    <div class="modal-header"><h3>Edit Customer</h3><button class="modal-close" onclick="window.location='index.php'">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="cid" value="<?php echo $editCustomer['customer_id']; ?>">
        <div class="form-grid">
          <div class="form-group"><label>Customer Name *</label><input type="text" name="name" value="<?php echo htmlspecialchars($editCustomer['customer_name']); ?>" required></div>
          <div class="form-group"><label>Phone Number</label><input type="text" name="phone" value="<?php echo htmlspecialchars($editCustomer['phone']??''); ?>"></div>
          <div class="form-group"><label>Email Address</label><input type="email" name="email" value="<?php echo htmlspecialchars($editCustomer['email']??''); ?>"></div>
          <div class="form-group"><label>Address</label><input type="text" name="address" value="<?php echo htmlspecialchars($editCustomer['address']??''); ?>"></div>
        </div>
      </div>
      <div class="modal-footer"><a href="index.php" class="btn btn-secondary">Cancel</a><button type="submit" name="update_customer" class="btn btn-primary">Update Customer</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
</script>
<?php require_once "../../includes/footer.php"; ?>
