<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Record Sale";
$msg = "";

// PROCESS SALE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_sale'])) {
    $customer_id   = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $items = $_POST['items'] ?? [];
    
    $validItems = [];
    $totalAmount = 0;
    $errors = [];
    
    foreach ($items as $item) {
        $pid = (int)$item['product_id'];
        $qty = (int)$item['qty'];
        if ($pid <= 0 || $qty <= 0) continue;
        
        $pRes = mysqli_query($conn, "SELECT * FROM products WHERE product_id=$pid AND status='Active'");
        $pRow = mysqli_fetch_assoc($pRes);
        if (!$pRow) { $errors[] = "Product ID $pid not found."; continue; }
        if ($pRow['stock'] < $qty) { $errors[] = "Not enough stock for {$pRow['product_name']}. Available: {$pRow['stock']}"; continue; }
        
        $subtotal = $pRow['selling_price'] * $qty;
        $totalAmount += $subtotal;
        $validItems[] = ['product' => $pRow, 'qty' => $qty, 'subtotal' => $subtotal];
    }
    
    if (!empty($errors)) {
        $msg = "<div class='alert alert-danger'>❌ " . implode('<br>', $errors) . "</div>";
    } elseif (empty($validItems)) {
        $msg = "<div class='alert alert-warning'>⚠️ Please add at least one item.</div>";
    } else {
        $paidAmount = (float)$_POST['paid_amount'];
        $dueAmount  = $totalAmount - $paidAmount;
        $invoice    = 'INV-' . strtoupper(uniqid());
        $saleDate   = date('Y-m-d');
        $custClause = $customer_id ? $customer_id : 'NULL';
        
        mysqli_query($conn, "INSERT INTO sales (customer_id, invoice_no, sale_date, total_amount, paid_amount, due_amount, payment_method) 
            VALUES ($custClause, '$invoice', '$saleDate', $totalAmount, $paidAmount, $dueAmount, '$payment_method')");
        $saleId = mysqli_insert_id($conn);
        
        foreach ($validItems as $vi) {
            $pid     = $vi['product']['product_id'];
            $price   = $vi['product']['selling_price'];
            $qty     = $vi['qty'];
            $sub     = $vi['subtotal'];
            mysqli_query($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal) VALUES ($saleId, $pid, $qty, $price, $sub)");
            mysqli_query($conn, "UPDATE products SET stock = stock - $qty WHERE product_id=$pid");
        }
        
        // Update customer due if any
        if ($customer_id && $dueAmount > 0) {
            mysqli_query($conn, "UPDATE customers SET total_due = total_due + $dueAmount WHERE customer_id=$customer_id");
        }
        
        $msg = "<div class='alert alert-success'>✅ Sale recorded! Invoice: <strong>$invoice</strong> | Total: ৳" . number_format($totalAmount,2) . "</div>";
    }
}

// Data for dropdowns
$customers = mysqli_query($conn, "SELECT customer_id, customer_name, phone FROM customers ORDER BY customer_name");
$products  = mysqli_query($conn, "SELECT product_id, product_name, selling_price, stock, unit FROM products WHERE status='Active' AND stock > 0 ORDER BY product_name");

// Recent sales
$recentSales = mysqli_query($conn, "
    SELECT s.*, c.customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id=c.customer_id 
    ORDER BY s.sale_id DESC LIMIT 10
");

// Stats
$todaySales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) s, COUNT(*) c FROM sales WHERE sale_date=CURDATE()"));

require_once "../../includes/header.php";
?>
<style>
.sale-item-row { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: center; margin-bottom: 10px; padding: 10px; background: #f8fafc; border-radius: 8px; }
.total-display { font-size: 28px; font-weight: 700; color: #3b82f6; }
</style>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">

        <div class="page-header">
          <div><h1>🛒 Record Sale / Billing</h1><p>Create invoices and process customer sales.</p></div>
        </div>

        <?php echo $msg; ?>

        <!-- Today Stats -->
        <div class="stats-row">
          <div class="stat-card green"><div class="stat-label">Today's Revenue</div><div class="stat-value">৳<?php echo number_format((float)$todaySales['s'],2); ?></div></div>
          <div class="stat-card blue"><div class="stat-label">Today's Transactions</div><div class="stat-value"><?php echo $todaySales['c']; ?></div></div>
        </div>

        <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:20px;">
          <!-- Sale Form -->
          <div class="card">
            <div class="card-title">New Sale</div>
            <form method="POST" id="saleForm">
              <div class="form-grid">
                <div class="form-group">
                  <label>Customer (Optional)</label>
                  <select name="customer_id">
                    <option value="">-- Walk-in Customer --</option>
                    <?php while($r=mysqli_fetch_assoc($customers)): ?>
                    <option value="<?php echo $r['customer_id']; ?>"><?php echo htmlspecialchars($r['customer_name']); ?> (<?php echo $r['phone']??'No phone'; ?>)</option>
                    <?php endwhile; ?>
                  </select>
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

              <!-- Items -->
              <div style="margin:16px 0 8px; font-weight:600; color:#475569;">Items:</div>
              <div id="itemsContainer">
                <div class="sale-item-row item-row">
                  <select name="items[0][product_id]" class="product-select" required>
                    <option value="">-- Select Product --</option>
                    <?php
                    mysqli_data_seek($products, 0);
                    while($r=mysqli_fetch_assoc($products)):
                    ?>
                    <option value="<?php echo $r['product_id']; ?>" data-price="<?php echo $r['selling_price']; ?>" data-stock="<?php echo $r['stock']; ?>" data-unit="<?php echo $r['unit']; ?>">
                      <?php echo htmlspecialchars($r['product_name']); ?> (Stock: <?php echo $r['stock']; ?> <?php echo $r['unit']; ?>) - ৳<?php echo $r['selling_price']; ?>
                    </option>
                    <?php endwhile; ?>
                  </select>
                  <input type="number" name="items[0][qty]" min="1" value="1" class="qty-input" placeholder="Qty">
                  <div class="subtotal-display" style="font-weight:700; color:#3b82f6;">৳0.00</div>
                  <button type="button" class="btn btn-danger btn-sm remove-row" style="display:none;">✕</button>
                </div>
              </div>
              <button type="button" id="addItem" class="btn btn-secondary btn-sm" style="margin-top:8px;">+ Add Item</button>

              <div style="margin-top:16px; padding:16px; background:#f0fdf4; border-radius:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                  <span style="font-weight:600; color:#166534;">Total Amount:</span>
                  <span class="total-display" id="grandTotal">৳0.00</span>
                </div>
                <div class="form-group" style="margin-top:12px;">
                  <label>Amount Paid (৳)</label>
                  <input type="number" name="paid_amount" id="paidAmount" step="0.01" min="0" value="0" required>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:8px;">
                  <span style="font-weight:600; color:#991b1b;">Due Amount:</span>
                  <span id="dueDisplay" style="font-size:18px; font-weight:700; color:#ef4444;">৳0.00</span>
                </div>
              </div>

              <div class="form-actions" style="margin-top:16px;">
                <button type="submit" name="record_sale" class="btn btn-success" style="width:100%; justify-content:center; font-size:15px; padding:12px;">Generate Invoice ✅</button>
              </div>
            </form>
          </div>

          <!-- Recent Sales -->
          <div class="card">
            <div class="card-title">Recent Sales</div>
            <div class="table-wrapper">
              <table class="data-table">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Amount</th><th>Due</th></tr></thead>
                <tbody>
                  <?php while($r=mysqli_fetch_assoc($recentSales)): ?>
                  <tr>
                    <td style="font-size:11px;"><?php echo $r['invoice_no']; ?></td>
                    <td><?php echo htmlspecialchars($r['customer_name'] ?? 'Walk-in'); ?></td>
                    <td>৳<?php echo number_format($r['total_amount'],2); ?></td>
                    <td><?php if($r['due_amount']>0): ?><span class="badge badge-danger">৳<?php echo number_format($r['due_amount'],2); ?></span><?php else: ?><span class="badge badge-success">Paid</span><?php endif; ?></td>
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
let itemIndex = 1;
const productOptions = document.querySelector('.product-select').innerHTML;

function recalc() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const sel = row.querySelector('.product-select');
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const opt = sel.options[sel.selectedIndex];
        const price = opt ? parseFloat(opt.dataset.price) || 0 : 0;
        const sub = price * qty;
        row.querySelector('.subtotal-display').textContent = '৳' + sub.toFixed(2);
        total += sub;
    });
    document.getElementById('grandTotal').textContent = '৳' + total.toFixed(2);
    const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    const due = Math.max(0, total - paid);
    document.getElementById('dueDisplay').textContent = '৳' + due.toFixed(2);
    document.getElementById('paidAmount').max = total;
}

document.getElementById('addItem').addEventListener('click', function() {
    const container = document.getElementById('itemsContainer');
    const div = document.createElement('div');
    div.className = 'sale-item-row item-row';
    div.innerHTML = `<select name="items[${itemIndex}][product_id]" class="product-select">${productOptions}</select>
                     <input type="number" name="items[${itemIndex}][qty]" min="1" value="1" class="qty-input">
                     <div class="subtotal-display" style="font-weight:700;color:#3b82f6;">৳0.00</div>
                     <button type="button" class="btn btn-danger btn-sm remove-row">✕</button>`;
    container.appendChild(div);
    div.querySelector('.product-select').addEventListener('change', recalc);
    div.querySelector('.qty-input').addEventListener('input', recalc);
    div.querySelector('.remove-row').addEventListener('click', function() { div.remove(); recalc(); });
    itemIndex++;
    document.querySelectorAll('.remove-row').forEach(b => b.style.display = 'block');
});

document.querySelectorAll('.product-select, .qty-input').forEach(el => el.addEventListener('change', recalc));
document.querySelectorAll('.product-select, .qty-input').forEach(el => el.addEventListener('input', recalc));
document.getElementById('paidAmount').addEventListener('input', recalc);
</script>
<?php require_once "../../includes/footer.php"; ?>
