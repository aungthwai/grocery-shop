<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Reports & Analytics";

$filter = $_GET['filter'] ?? 'today';

// Date ranges
switch($filter) {
    case 'today':   $from = $to = date('Y-m-d'); break;
    case 'week':    $from = date('Y-m-d', strtotime('-7 days')); $to = date('Y-m-d'); break;
    case 'month':   $from = date('Y-m-01'); $to = date('Y-m-d'); break;
    case 'year':    $from = date('Y-01-01'); $to = date('Y-m-d'); break;
    case 'custom':  $from = $_GET['from'] ?? date('Y-m-01'); $to = $_GET['to'] ?? date('Y-m-d'); break;
    default:        $from = date('Y-m-d'); $to = date('Y-m-d');
}

// KPIs
$salesKpi     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) rev, COALESCE(SUM(paid_amount),0) paid, COALESCE(SUM(due_amount),0) due, COUNT(*) cnt FROM sales WHERE sale_date BETWEEN '$from' AND '$to'"));
$purchaseKpi  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) total, COUNT(*) cnt FROM purchases WHERE purchase_date BETWEEN '$from' AND '$to'"));
$profit = (float)$salesKpi['paid'] - (float)$purchaseKpi['total'];

$totalCustomers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM customers"))['c'];
$lowStockCount  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM products WHERE stock <= minimum_stock AND status='Active'"))['c'];

// Top Products
$topProducts = mysqli_query($conn, "
    SELECT p.product_name, SUM(si.quantity) sold, SUM(si.subtotal) revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id=s.sale_id
    JOIN products p ON si.product_id=p.product_id
    WHERE s.sale_date BETWEEN '$from' AND '$to'
    GROUP BY p.product_id ORDER BY sold DESC LIMIT 5
");

// Recent Sales
$recentSales = mysqli_query($conn, "
    SELECT s.invoice_no, s.sale_date, s.total_amount, s.payment_method, c.customer_name
    FROM sales s LEFT JOIN customers c ON s.customer_id=c.customer_id
    WHERE s.sale_date BETWEEN '$from' AND '$to'
    ORDER BY s.sale_id DESC LIMIT 10
");

// Monthly chart data (last 6 months)
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $mLabel = date('M Y', strtotime("-$i months"));
    $mFrom  = date('Y-m-01', strtotime("-$i months"));
    $mTo    = date('Y-m-t', strtotime("-$i months"));
    $mSales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) s FROM sales WHERE sale_date BETWEEN '$mFrom' AND '$mTo'"))['s'];
    $chartData[] = ['label' => $mLabel, 'sales' => (float)$mSales];
}

require_once "../../includes/header.php";
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">

        <div class="page-header">
          <div><h1>📊 Reports & Analytics</h1><p>Business performance overview and insights.</p></div>
        </div>

        <!-- Date Filter -->
        <div style="display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; align-items:center;">
          <?php foreach(['today'=>'Today','week'=>'Last 7 Days','month'=>'This Month','year'=>'This Year','custom'=>'Custom'] as $k=>$v): ?>
          <a href="?filter=<?php echo $k; ?>" class="btn btn-sm <?php echo $filter==$k?'btn-primary':'btn-secondary'; ?>"><?php echo $v; ?></a>
          <?php endforeach; ?>
          <?php if ($filter === 'custom'): ?>
          <form method="GET" style="display:flex; gap:8px; align-items:center;">
            <input type="hidden" name="filter" value="custom">
            <input type="date" name="from" value="<?php echo $from; ?>" style="padding:7px; border:1px solid #e2e8f0; border-radius:7px;">
            <input type="date" name="to"   value="<?php echo $to; ?>"   style="padding:7px; border:1px solid #e2e8f0; border-radius:7px;">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
          </form>
          <?php endif; ?>
        </div>
        <div style="font-size:13px; color:#64748b; margin-bottom:16px;">📅 Showing data from <strong><?php echo $from; ?></strong> to <strong><?php echo $to; ?></strong></div>

        <!-- KPI Cards -->
        <div class="stats-row">
          <div class="stat-card green"><div class="stat-label">Total Sales Revenue</div><div class="stat-value">৳<?php echo number_format((float)$salesKpi['rev'],0); ?></div></div>
          <div class="stat-card blue"><div class="stat-label">Transactions</div><div class="stat-value"><?php echo $salesKpi['cnt']; ?></div></div>
          <div class="stat-card amber"><div class="stat-label">Purchase Cost</div><div class="stat-value">৳<?php echo number_format((float)$purchaseKpi['total'],0); ?></div></div>
          <div class="stat-card <?php echo $profit>=0?'green':'red'; ?>"><div class="stat-label">Net Profit</div><div class="stat-value">৳<?php echo number_format($profit,0); ?></div></div>
          <div class="stat-card red"><div class="stat-label">Total Dues</div><div class="stat-value">৳<?php echo number_format((float)$salesKpi['due'],0); ?></div></div>
        </div>

        <div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:20px;">
          <!-- Chart -->
          <div class="card">
            <div class="card-title">Sales Trend (Last 6 Months)</div>
            <canvas id="salesChart" height="200"></canvas>
          </div>

          <!-- Top Products -->
          <div class="card">
            <div class="card-title">Top Selling Products</div>
            <table class="data-table">
              <thead><tr><th>Product</th><th>Units Sold</th><th>Revenue</th></tr></thead>
              <tbody>
                <?php $i=1; while($r=mysqli_fetch_assoc($topProducts)): ?>
                <tr>
                  <td><?php echo $i++; ?>. <?php echo htmlspecialchars($r['product_name']); ?></td>
                  <td><span class="badge badge-info"><?php echo $r['sold']; ?></span></td>
                  <td>৳<?php echo number_format($r['revenue'],0); ?></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Sales Table -->
        <div class="card">
          <div class="card-title">Sales Transactions</div>
          <div class="table-wrapper">
            <table class="data-table">
              <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Amount</th><th>Payment Method</th></tr></thead>
              <tbody>
                <?php while($r=mysqli_fetch_assoc($recentSales)): ?>
                <tr>
                  <td style="font-size:12px;"><?php echo $r['invoice_no']; ?></td>
                  <td><?php echo $r['sale_date']; ?></td>
                  <td><?php echo htmlspecialchars($r['customer_name']??'Walk-in'); ?></td>
                  <td><strong>৳<?php echo number_format($r['total_amount'],2); ?></strong></td>
                  <td><span class="badge badge-info"><?php echo $r['payment_method']; ?></span></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<script>
const ctx = document.getElementById('salesChart').getContext('2d');
const labels = <?php echo json_encode(array_column($chartData,'label')); ?>;
const data   = <?php echo json_encode(array_column($chartData,'sales')); ?>;
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Sales (৳)',
            data: data,
            backgroundColor: 'rgba(59, 130, 246, 0.7)',
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '৳' + Number(v).toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php require_once "../../includes/footer.php"; ?>
