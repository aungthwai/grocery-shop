<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Purchase Management";
$msg = "";

// RECORD PURCHASE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_purchase'])) {
    $supplier_id    = (int)$_POST['supplier_id'];
    $purchase_date  = mysqli_real_escape_string($conn, $_POST['purchase_date']);
    $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
    $remarks        = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
    $items          = $_POST['items'] ?? [];
    
    $validItems = [];
    $totalAmount = 0;
    $errors = [];

    foreach ($items as $item) {
        $pid   = (int)$item['product_id'];
        $qty   = (int)$item['qty'];
        $price = (float)$item['price'];
        if ($pid <= 0 || $qty <= 0 || $price <= 0) continue;
        $sub = $qty * $price;
        $totalAmount += $sub;
        $validItems[] = ['pid' => $pid, 'qty' => $qty, 'price' => $price, 'sub' => $sub];
    }

    if (empty($validItems)) {
        $msg = "<div class='alert alert-warning'>⚠️ Please add at least one item.</div>";
    } else {
        $invoice = 'PUR-' . strtoupper(uniqid());
        mysqli_query($conn, "INSERT INTO purchases (supplier_id, invoice_no, purchase_date, total_amount, payment_status, remarks) 
            VALUES ($supplier_id, '$invoice', '$purchase_date', $totalAmount, '$payment_status', '$remarks')");
        $purchaseId = mysqli_insert_id($conn);

        foreach ($validItems as $vi) {
            mysqli_query($conn, "INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) 
                VALUES ($purchaseId, {$vi['pid']}, {$vi['qty']}, {$vi['price']}, {$vi['sub']})");
            mysqli_query($conn, "UPDATE products SET stock = stock + {$vi['qty']} WHERE product_id={$vi['pid']}");
        }
        $msg = "<div class='alert alert-success'>✅ Purchase recorded! Invoice: <strong>$invoice</strong> | Stock updated.</div>";
    }
}

// Dropdowns
$suppliers = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY supplier_name");
$products  = mysqli_query($conn, "SELECT product_id, product_name, purchase_price, unit FROM products WHERE status='Active' ORDER BY product_name");

// Recent purchases
$recentPurchases = mysqli_query($conn, "
    SELECT p.*, s.supplier_name 
    FROM purchases p 
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id 
    ORDER BY p.purchase_id DESC LIMIT 10
");

// Stats
$totalPurchases = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) s, COUNT(*) c FROM purchases"));;

require_once "../../includes/header.php";
?>
<style>
.purchase-item-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: center; margin-bottom: 10px; padding: 10px; background: #f8fafc; border-radius: 8px; }
</style>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">

        <div class="page-header">
          <div><h1>🛍️ Purchase Management</h1><p>Record new stock purchases and track inventory updates.</p></div>
        </div>

        <?php echo $msg; ?>

        <div class="stats-row">
          <div class="stat-card blue"><div class="stat-label">Total Purchases</div><div class="stat-value"><?php echo $totalPurchases['c']; ?></div></div>
          <div class="stat-card amber"><div class="stat-label">Total Purchase Cost</div><div class="stat-value">৳<?php echo number_format((float)$totalPurchases['s'],0); ?></div></div>
        </div>

        <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:20px;">
          <!-- Purchase Form -->
          <div class="card">
            <div class="card-title">Record New Purchase</div>
            <form method="POST" id="purchaseForm">
              <div class="form-grid">
                <div class="form-group">
                  <label>Supplier *</label>
                  <select name="supplier_id" required>
                    <option value="">-- Select Supplier --</option>
                    <?php while($r=mysqli_fetch_assoc($suppliers)): ?>
                    <option value="<?php echo $r['supplier_id']; ?>"><?php echo htmlspecialchars($r['supplier_name']); ?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Purchase Date *</label>
                  <input type="date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                  <label>Payment Status</label>
                  <select name="payment_status">
                    <option value="Paid">Paid</option>
                    <option value="Due">Due</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Remarks (Optional)</label>
                  <input type="text" name="remarks" placeholder="Any notes...">
                </div>
              </div>

              <div style="margin:16px 0 8px; font-weight:600; color:#475569;">Items:</div>
              <div id="itemsContainer">
                <div class="purchase-item-row item-row">
                  <select name="items[0][product_id]" class="product-select" required>
                    <option value="">-- Select Product --</option>
                    <?php
                    mysqli_data_seek($products, 0);
                    while($r=mysqli_fetch_assoc($products)):
                    ?>
                    <option value="<?php echo $r['product_id']; ?>" data-price="<?php echo $r['purchase_price']; ?>">
                      <?php echo htmlspecialchars($r['product_name']); ?> (<?php echo $r['unit']; ?>)
                    </option>
                    <?php endwhile; ?>
                  </select>
                  <input type="number" name="items[0][qty]" min="1" value="1" class="qty-input" placeholder="Qty">
                  <input type="number" name="items[0][price]" step="0.01" min="0" class="price-input" placeholder="Unit Price">
                  <div class="subtotal-display" style="font-weight:700; color:#3b82f6;">৳0.00</div>
                  <button type="button" class="btn btn-danger btn-sm remove-row" style="display:none;">✕</button>
                </div>
              </div>
              <button type="button" id="addItem" class="btn btn-secondary btn-sm" style="margin-top:8px;">+ Add Item</button>

              <div style="margin-top:16px; padding:16px; background:#eff6ff; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:600; color:#1e40af;">Grand Total:</span>
                <span style="font-size:26px; font-weight:700; color:#3b82f6;" id="grandTotal">৳0.00</span>
              </div>

              <button type="submit" name="record_purchase" class="btn btn-primary" style="margin-top:16px; width:100%; justify-content:center; font-size:15px; padding:12px;">Save Purchase & Update Stock ✅</button>
            </form>
          </div>

          <!-- Recent Purchases -->
          <div class="card">
            <div class="card-title">Recent Purchases</div>
            <div class="table-wrapper">
              <table class="data-table">
                <thead><tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                  <?php while($r=mysqli_fetch_assoc($recentPurchases)): ?>
                  <tr>
                    <td style="font-size:11px;"><?php echo $r['invoice_no']; ?></td>
                    <td><?php echo htmlspecialchars($r['supplier_name']??'-'); ?></td>
                    <td><?php echo $r['purchase_date']; ?></td>
                    <td>৳<?php echo number_format($r['total_amount'],2); ?></td>
                    <td><?php echo $r['payment_status']=='Paid'?"<span class='badge badge-success'>Paid</span>":"<span class='badge badge-danger'>Due</span>"; ?></td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
let idx = 1;
const productOptions = document.querySelector('.product-select').innerHTML;

function recalc() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty   = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const sub   = qty * price;
        row.querySelector('.subtotal-display').textContent = '৳' + sub.toFixed(2);
        total += sub;
    });
    document.getElementById('grandTotal').textContent = '৳' + total.toFixed(2);
}

function autoFillPrice(sel) {
    const opt = sel.options[sel.selectedIndex];
    const price = opt ? parseFloat(opt.dataset.price) || 0 : 0;
    const row = sel.closest('.item-row');
    if (row) row.querySelector('.price-input').value = price;
    recalc();
}

document.querySelector('.product-select').addEventListener('change', function() { autoFillPrice(this); });
document.querySelectorAll('.qty-input, .price-input').forEach(el => el.addEventListener('input', recalc));

document.getElementById('addItem').addEventListener('click', function() {
    const container = document.getElementById('itemsContainer');
    const div = document.createElement('div');
    div.className = 'purchase-item-row item-row';
    div.innerHTML = `<select name="items[${idx}][product_id]" class="product-select">${productOptions}</select>
                     <input type="number" name="items[${idx}][qty]" min="1" value="1" class="qty-input" placeholder="Qty">
                     <input type="number" name="items[${idx}][price]" step="0.01" min="0" class="price-input" placeholder="Unit Price">
                     <div class="subtotal-display" style="font-weight:700;color:#3b82f6;">৳0.00</div>
                     <button type="button" class="btn btn-danger btn-sm remove-row">✕</button>`;
    container.appendChild(div);
    div.querySelector('.product-select').addEventListener('change', function() { autoFillPrice(this); });
    div.querySelector('.qty-input').addEventListener('input', recalc);
    div.querySelector('.price-input').addEventListener('input', recalc);
    div.querySelector('.remove-row').addEventListener('click', function() { div.remove(); recalc(); });
    document.querySelectorAll('.remove-row').forEach(b => b.style.display = 'block');
    idx++;
});
</script>
<?php require_once "../../includes/footer.php"; ?>
