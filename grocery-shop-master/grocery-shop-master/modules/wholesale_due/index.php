<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Wholesale Due Management";
$msg = "";

// RECEIVE PAYMENT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['receive_payment'])) {
    $cid    = (int)$_POST['customer_id'];
    $amount = (float)$_POST['amount'];
    
    $cRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT total_due FROM customers WHERE customer_id=$cid"));
    if ($cRow && $amount > 0) {
        $newDue = max(0, $cRow['total_due'] - $amount);
        mysqli_query($conn, "UPDATE customers SET total_due=$newDue WHERE customer_id=$cid");
        
        // Get a sale with due from this customer to log payment
        $sRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT sale_id FROM sales WHERE customer_id=$cid AND due_amount > 0 LIMIT 1"));
        if ($sRow) {
            $saleId = $sRow['sale_id'];
            $today = date('Y-m-d');
            $method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            mysqli_query($conn, "INSERT INTO payments (sale_id, customer_id, payment_date, amount, payment_method) VALUES ($saleId, $cid, '$today', $amount, '$method')");
            mysqli_query($conn, "UPDATE sales SET due_amount = GREATEST(0, due_amount - $amount), paid_amount = paid_amount + $amount WHERE sale_id=$saleId");
        }
        $msg = "<div class='alert alert-success'>✅ Payment of ৳" . number_format($amount, 2) . " received successfully!</div>";
    }
}

// Stats
$totalDue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_due),0) s, COUNT(*) c FROM customers WHERE total_due > 0"));

// Customers with due
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$where = "WHERE total_due > 0";
if ($search) $where .= " AND (customer_name LIKE '%$search%' OR phone LIKE '%$search%')";
$customers = mysqli_query($conn, "SELECT * FROM customers $where ORDER BY total_due DESC");

require_once "../../includes/header.php";
?>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">

        <div class="page-header">
          <div><h1>💰 Wholesale Due Management</h1><p>Track and collect outstanding customer payments.</p></div>
        </div>

        <?php echo $msg; ?>

        <div class="stats-row">
          <div class="stat-card red"><div class="stat-label">Customers with Due</div><div class="stat-value"><?php echo $totalDue['c']; ?></div></div>
          <div class="stat-card amber"><div class="stat-label">Total Outstanding Due</div><div class="stat-value">৳<?php echo number_format((float)$totalDue['s'],2); ?></div></div>
        </div>

        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div class="card-title" style="margin:0; border:none; padding:0;">Customers with Outstanding Due</div>
            <form method="GET" class="search-bar" style="margin:0;">
              <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or phone...">
              <button type="submit" class="btn btn-secondary btn-sm">Search</button>
              <?php if ($search): ?><a href="index.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
            </form>
          </div>
          <div class="table-wrapper">
            <table class="data-table">
              <thead><tr><th>#</th><th>Customer Name</th><th>Phone</th><th>Total Due</th><th>Action</th></tr></thead>
              <tbody>
                <?php if (mysqli_num_rows($customers) === 0): ?>
                  <tr class="empty-row"><td colspan="5">🎉 No outstanding dues! All accounts are clear.</td></tr>
                <?php else: while($row=mysqli_fetch_assoc($customers)): ?>
                <tr>
                  <td><?php echo $row['customer_id']; ?></td>
                  <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($row['phone']??'-'); ?></td>
                  <td><span style="font-size:16px; font-weight:700; color:#ef4444;">৳<?php echo number_format($row['total_due'],2); ?></span></td>
                  <td>
                    <button class="btn btn-success btn-sm" onclick="openPayment(<?php echo $row['customer_id']; ?>, '<?php echo htmlspecialchars($row['customer_name']); ?>', <?php echo $row['total_due']; ?>)">
                      Receive Payment
                    </button>
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

<!-- Payment Modal -->
<div class="modal-overlay" id="paymentModal">
  <div class="modal">
    <div class="modal-header"><h3>Receive Payment</h3><button class="modal-close" onclick="closeModal('paymentModal')">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="customer_id" id="payCustomerId">
        <div style="margin-bottom:16px; padding:12px; background:#fef2f2; border-radius:8px;">
          <strong id="payCustomerName"></strong><br>
          <span style="color:#64748b;">Outstanding Due: <strong style="color:#ef4444;" id="payDueAmount"></strong></span>
        </div>
        <div class="form-group">
          <label>Amount Received (৳) *</label>
          <input type="number" name="amount" id="payAmount" step="0.01" min="0.01" required placeholder="Enter amount">
        </div>
        <div class="form-group">
          <label>Payment Method</label>
          <select name="payment_method">
            <option value="Cash">💵 Cash</option>
            <option value="Card">💳 Card</option>
            <option value="Mobile Banking">📱 Mobile Banking</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('paymentModal')">Cancel</button>
        <button type="submit" name="receive_payment" class="btn btn-success">Confirm Payment ✅</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openPayment(id, name, due) {
    document.getElementById('payCustomerId').value = id;
    document.getElementById('payCustomerName').textContent = name;
    document.getElementById('payDueAmount').textContent = '৳' + parseFloat(due).toFixed(2);
    document.getElementById('payAmount').value = due;
    document.getElementById('payAmount').max = due;
    openModal('paymentModal');
}
</script>
<?php require_once "../../includes/footer.php"; ?>
