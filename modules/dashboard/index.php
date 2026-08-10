<?php
session_start();
/*
|-------------------------------------------------------------------------
-
| Dashboard Page
|-------------------------------------------------------------------------
-
| Dashboard + KPI Cards + Sales Overview + Recent Sales
|-------------------------------------------------------------------------
-
*/
/*
|-------------------------------------------------------------------------
-
| Check Login
|-------------------------------------------------------------------------
-
*/
if (!isset($_SESSION['user_id'])) {
header("Location: ../../login.php");
exit;
}
/*
|-------------------------------------------------------------------------
-
| Database Connection
|-------------------------------------------------------------------------
-
*/
require_once "../../config/database.php";
$basePath = "/grocery-shop";
/*
|-------------------------------------------------------------------------
-
| KPI DATA
|-------------------------------------------------------------------------
-
*/
/*
|-------------------------------------------------------------------------
-
| 1. Total Customers
|-------------------------------------------------------------------------
-
*/
$totalCustomers = 0;
$sql = "
SELECT COUNT(*) AS total
FROM customers
";
$result = mysqli_query($conn, $sql);
if ($result) {
$row = mysqli_fetch_assoc($result);
$totalCustomers = (int) $row['total'];
}
/*
|-------------------------------------------------------------------------
-
| 2. Active Customers
|-------------------------------------------------------------------------
-
*/
$activeCustomers = 0;
$sql = "
SELECT COUNT(DISTINCT customer_id) AS total
FROM sales
WHERE customer_id IS NOT NULL
";
$result = mysqli_query($conn, $sql);
if ($result) {
$row = mysqli_fetch_assoc($result);
$activeCustomers = (int) $row['total'];
}
/*
|-------------------------------------------------------------------------
-
| 3. Outstanding Due
|-------------------------------------------------------------------------
-
*/
$outstandingDue = 0;
$sql = "
SELECT COALESCE(SUM(total_due), 0) AS total
FROM customers
";
$result = mysqli_query($conn, $sql);
if ($result) {
$row = mysqli_fetch_assoc($result);
$outstandingDue = (float) $row['total'];
}
/*
|-------------------------------------------------------------------------
-
| 4. Overdue Customers
|-------------------------------------------------------------------------
-
|
| For now, customers with an outstanding balance
| are counted here.
|
*/
$overdueCustomers = 0;
$sql = "
SELECT COUNT(*) AS total
FROM customers
WHERE total_due > 0
";
$result = mysqli_query($conn, $sql);
if ($result) {
$row = mysqli_fetch_assoc($result);
$overdueCustomers = (int) $row['total'];
}
/*
|-------------------------------------------------------------------------
-
| LATEST SIX MONTHS SALES DATA
|-------------------------------------------------------------------------
-
*/
$monthlySales = [];
$monthLabels = [];
/*
|-------------------------------------------------------------------------
-
| Create Latest Six Month Labels
|-------------------------------------------------------------------------
-
*/
for ($i = 5; $i >= 0; $i--) {
$timestamp = strtotime("-$i months");
$monthKey = date("Y-m", $timestamp);
$monthLabel = date("M", $timestamp);
$monthlySales[$monthKey] = 0;
$monthLabels[] = $monthLabel;
}
/*
|-------------------------------------------------------------------------
-
| Get Actual Sales From Database
|-------------------------------------------------------------------------
-
*/
$sql = "
SELECT
DATE_FORMAT(sale_date, '%Y-%m') AS sale_month,
SUM(total_amount) AS monthly_total
FROM sales
WHERE sale_date >= DATE_FORMAT(
DATE_SUB(CURDATE(), INTERVAL 5 MONTH),
'%Y-%m-01'
)
GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
ORDER BY sale_month ASC
";
$result = mysqli_query($conn, $sql);
/*
|-------------------------------------------------------------------------
-
| Put Database Sales Into Correct Month
|-------------------------------------------------------------------------
-
*/
if ($result) {
while ($row = mysqli_fetch_assoc($result)) {
$monthKey = $row['sale_month'];
if (isset($monthlySales[$monthKey])) {
$monthlySales[$monthKey] =
(float) $row['monthly_total'];
}
}
}
/*
|-------------------------------------------------------------------------
-
| RECENT SALES
|-------------------------------------------------------------------------
-
|
| Get the latest 5 sales.
|
| IMPORTANT:
| The database uses:
| invoice_no
| payment_method
|
| It does NOT have:
| invoice_number
| payment_status
|
*/
$recentSales = [];
$sql = "
SELECT
s.sale_id,
s.invoice_no,
s.sale_date,
s.total_amount,
s.paid_amount,
s.due_amount,
s.payment_method,
c.customer_name
FROM sales s
LEFT JOIN customers c
ON s.customer_id = c.customer_id
ORDER BY s.sale_date DESC, s.sale_id DESC
LIMIT 5
";
$result = mysqli_query($conn, $sql);
if ($result) {
while ($row = mysqli_fetch_assoc($result)) {
$recentSales[] = $row;
}
}
/*
|-------------------------------------------------------------------------
-
| LOW STOCK ALERT
|-------------------------------------------------------------------------
-
| Get products that are at or below their minimum stock level.
|-------------------------------------------------------------------------
-
*/
$lowStockProducts = [];
$sql = "
SELECT
product_id,
product_name,
stock,
unit,
minimum_stock
FROM products
WHERE status = 'Active'
AND stock <= minimum_stock
ORDER BY stock ASC
LIMIT 5
";
$result = mysqli_query($conn, $sql);
if ($result) {
while ($row = mysqli_fetch_assoc($result)) {
$lowStockProducts[] = $row;
}
}
/*
|-------------------------------------------------------------------------
-
| TOP SELLING PRODUCTS
|-------------------------------------------------------------------------
-
| Get the top 4 products by quantity sold during the current month.
|-------------------------------------------------------------------------
-
*/
$topSellingProducts = [];
$sql = "
SELECT
p.product_id,
p.product_name,
p.unit,
SUM(si.quantity) AS total_quantity
FROM sale_items si
INNER JOIN sales s
ON si.sale_id = s.sale_id
INNER JOIN products p
ON si.product_id = p.product_id
WHERE s.sale_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
AND s.sale_date < DATE_ADD(
DATE_FORMAT(CURDATE(), '%Y-%m-01'),
INTERVAL 1 MONTH
)
GROUP BY
p.product_id,
p.product_name,
p.unit
ORDER BY
total_quantity DESC
LIMIT 4
";
$result = mysqli_query($conn, $sql);
if ($result) {
while ($row = mysqli_fetch_assoc($result)) {
$topSellingProducts[] = $row;
}
}
/*
|-------------------------------------------------------------------------
-
| PAGE INFORMATION
|-------------------------------------------------------------------------
-
*/
$page_title = "Dashboard";
/*
|-------------------------------------------------------------------------
-
| SHARED HEADER
|-------------------------------------------------------------------------
-
*/
require_once "../../includes/header.php";
/*
|-------------------------------------------------------------------------
-
| SIDEBAR
|-------------------------------------------------------------------------
-
*/
?>
<!-- =====================================================
DASHBOARD CSS
===================================================== -->
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">

<!-- =====================================================
     CHART.JS
     ===================================================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
$pageTitle = "Dashboard";
?>

<!-- =====================================================
     APPLICATION LAYOUT
     Sidebar -> Main Area -> Topbar -> Dashboard
     ===================================================== -->
<div class="app-layout">

    <aside class="app-sidebar-slot">
        <?php require_once "../../includes/sidebar.php"; ?>
    </aside>

    <div class="app-main-slot">

        <header class="app-topbar-slot">
            <?php require_once "../../includes/topbar.php"; ?>
        </header>

        <main class="dashboard-main-content">

            <!-- =====================================================
                 DASHBOARD PAGE
                 ===================================================== -->
            <div class="dashboard-page">
<!-- =================================================
DASHBOARD HEADING
================================================= -->
<div class="dashboard-heading">
<h1>
Dashboard
</h1>
<p>
Welcome back! Here’s today’s business overview
</p>
</div>
<!-- =================================================
KPI CARDS
================================================= -->
<div class="dashboard-kpi-grid">
<!-- =================================================
CARD 1 — TOTAL CUSTOMERS
================================================= -->
<div class="kpi-card kpi-blue">
<div class="kpi-icon">
<svg
width="22"
height="22"
viewBox="0 0 24 24"
fill="none"
xmlns="http://www.w3.org/2000/svg"
aria-hidden="true"
>
<path
d="M16 21V19C16 16.7909 14.2091 15 12 15H6C3.79086
15 2 16.7909 2 19V21"
stroke="currentColor"
stroke-width="2"
stroke-linecap="round"
stroke-linejoin="round"
/>
<circle
cx="9"
cy="7"
r="4"
stroke="currentColor"
stroke-width="2"
/>
<path
d="M22 21V19C21.9986 17.1771 20.765 15.5847 19
15.13"
stroke="currentColor"
stroke-width="2"
stroke-linecap="round"
/>
<path
d="M16 3.13C17.7699 3.58316 19.0075 5.17822
19.0075 7C19.0075 8.82178 17.7699 10.4168 16 10.87"
stroke="currentColor"
stroke-width="2"
stroke-linecap="round"
/>
</svg>
</div>
<div class="kpi-content">
<div class="kpi-title">
Total Customers
</div>
<div class="kpi-value">
<?php
echo number_format($totalCustomers);
?>
</div>
</div>
</div>
<!-- =================================================
CARD 2 — ACTIVE CUSTOMERS
================================================= -->
<div class="kpi-card kpi-green">
<div class="kpi-icon">
<svg
width="22"
height="22"
viewBox="0 0 24 24"
fill="none"
xmlns="http://www.w3.org/2000/svg"
aria-hidden="true"
>
<circle
cx="12"
cy="8"
r="4"
stroke="currentColor"
stroke-width="2"
/>
<path
d="M4 21C4 16.5817 7.58172 13 12 13C16.4183 13 20
16.5817 20 21"
stroke="currentColor"
stroke-width="2"
stroke-linecap="round"
/>
</svg>
</div>
<div class="kpi-content">
<div class="kpi-title">
Active Customers
</div>
<div class="kpi-value">
<?php
echo number_format($activeCustomers);
?>
</div>
</div>
</div>
<!-- =================================================
CARD 3 — OUTSTANDING DUE
================================================= -->
<div class="kpi-card kpi-orange">
<div class="kpi-icon">
<svg
width="22"
height="22"
viewBox="0 0 24 24"
fill="none"
xmlns="http://www.w3.org/2000/svg"
aria-hidden="true"
>
<circle
cx="12"
cy="12"
r="9"
stroke="currentColor"
stroke-width="2"
/>
<path
d="M12 7V17"
stroke="currentColor"
stroke-width="2"
stroke-linecap="round"
/>
<path
d="M15 9C15 7.89543 13.6569 7 12 7C10.3431 7 9
7.89543 9 8.5C9 10 15 10 15 12.5C15 14 13.6569 15 12 15C10.3431 15 9
14.1046 9 13"
stroke="currentColor"
stroke-width="2"
stroke-linecap="round"
/>
</svg>
</div>
<div class="kpi-content">
<div class="kpi-title">
Outstanding Due
</div>
<div class="kpi-value">
৳<?php
echo number_format($outstandingDue, 2);
?>
</div>
</div>
</div>
<!-- =================================================
CARD 4 — OVERDUE CUSTOMERS
================================================= -->
<div class="kpi-card kpi-red">
<div class="kpi-icon">
<svg
width="22"
height="22"
viewBox="0 0 24 24"
fill="none"
xmlns="http://www.w3.org/2000/svg"
aria-hidden="true"
>
<circle
cx="12"
cy="12"
r="9"
stroke="currentColor"
stroke-width="2"
/>
<path
d="M12 7V12L15 15"
stroke="currentColor"
stroke-width="2"
stroke-linecap="round"
stroke-linejoin="round"
/>
</svg>
</div>
<div class="kpi-content">
<div class="kpi-title">
Overdue Customers
</div>
<div class="kpi-value">
<?php
echo number_format($overdueCustomers);
?>
</div>
</div>
</div>
</div>
<!-- =====================================================
SALES OVERVIEW
===================================================== -->
<section class="dashboard-section sales-overview-section">
<div class="section-header">
<div>
<h2>
Sales Overview
</h2>
<p>
Monthly sales performance
</p>
</div>
</div>
<div class="sales-chart-container">
<canvas
id="salesOverviewChart"
></canvas>
</div>
</section>
<!-- =====================================================
RECENT SALES
===================================================== -->
<section class="dashboard-section recent-sales-section">
<!-- =================================================
RECENT SALES HEADER
================================================= -->
<div class="section-header">
<div>
<h2>
Recent Sales
</h2>
<p>
Latest sales transactions
</p>
</div>
<a
href="../../modules/sales/index.php"
class="view-all-link"
>
View All
</a>
</div>
<!-- =================================================
RECENT SALES TABLE
================================================= -->
<div class="recent-sales-table-wrapper">
<table class="recent-sales-table">
<thead>
<tr>
<th>
Invoice
</th>
<th>
Customer
</th>
<th>
Date
</th>
<th>
Amount
</th>
<th>
Status
</th>
</tr>
</thead>
<tbody>
<?php if (count($recentSales) > 0): ?>
<?php foreach ($recentSales as $sale): ?>
<tr>
<!-- Invoice -->
<td>
<span class="invoice-number">
<?php
echo htmlspecialchars(
$sale['invoice_no']
);
?>
</span>
</td>
<!-- Customer -->
<td>
<?php
if (
!empty(
$sale['customer_name']
)
) {
echo htmlspecialchars(
$sale['customer_name']
);
} else {
echo "Walk-in Customer";
}
?>
</td>
<!-- Date -->
<td>
<?php
echo date(
"d M Y",
strtotime(
$sale['sale_date']
)
);
?>
</td>
<!-- Amount -->
<td>
৳<?php
echo number_format(
(float) $sale['total_amount'],
2
);
?>
</td>
<!-- Status -->
<td>
<?php
/*
| Determine payment status
| from the actual database values.
*/
$totalAmount =
(float) $sale['total_amount'];
$paidAmount =
(float) $sale['paid_amount'];
$dueAmount =
(float) $sale['due_amount'];
if ($dueAmount <= 0) {
$status = "Paid";
} elseif ($paidAmount > 0) {
$status = "Pending";
} else {
$status = "Pending";
}
?>
<?php if ($status === "Paid"): ?>
<span
class="status-badge
status-paid"
>
Paid
</span>
<?php else: ?>
<span
class="status-badge
status-pending"
>
Pending
</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td
colspan="5"
class="no-sales-message"
>
No sales recorded yet.
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>

<!-- =================================================
TOP SELLING PRODUCTS
================================================= -->
<section class="dashboard-section top-selling-section">
<!-- =================================================
SECTION HEADER
================================================= -->
<div class="section-header">
<div>
<h2>
Top Selling Products
</h2>
<p>
This Month
</p>
</div>
</div>
<!-- =================================================
TOP SELLING PRODUCTS LIST
================================================= -->
<div class="top-selling-list">
<?php if (count($topSellingProducts) > 0): ?>
<?php
/*
* Calculate total quantity sold
* so we can display the percentage.
*/
$totalTopSellingQuantity = 0;
foreach ($topSellingProducts as $product) {
$totalTopSellingQuantity +=
(int) $product['total_quantity'];
}
?>
<?php foreach (
$topSellingProducts as $index => $product
): ?>
<?php
$rank = $index + 1;
$quantity =
(int) $product['total_quantity'];
$percentage = 0;
if ($totalTopSellingQuantity > 0) {
$percentage = round(
($quantity / $totalTopSellingQuantity) * 100
);
}
?>
<div class="top-selling-item">
<!-- Rank -->
<div class="top-selling-rank">
<?php if ($rank === 1): ?>
🥇
<?php elseif ($rank === 2): ?>
🥈
<?php elseif ($rank === 3): ?>
🥉
<?php else: ?>
<span class="rank-number">
4
</span>
<?php endif; ?>
</div>
<!-- Product Information -->
<div class="top-selling-product">
<div class="top-selling-product-name">
<?php
echo htmlspecialchars(
$product['product_name']
);
?>
</div>
<div class="top-selling-quantity">
<?php
echo number_format($quantity);
?>
<?php
echo " " .
htmlspecialchars(
$product['unit']
);
?>
</div>
</div>
<!-- Percentage -->
<div class="top-selling-percentage">
<div class="top-selling-percentage-value">
<?php
echo $percentage;
?>%
</div>
<div class="top-selling-progress">
<div
class="top-selling-progress-fill"
style="width: <?php
echo $percentage;
?>%;"
></div>
</div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<!-- =================================================
EMPTY STATE
================================================= -->
<div class="top-selling-empty">
<div class="top-selling-empty-icon">
📊
</div>
<p>
No sales recorded this month.
</p>
</div>
<?php endif; ?>
</div>
</section>
<!-- =================================================
LOW STOCK ALERT
================================================= -->
<section class="dashboard-section low-stock-section">
<!-- =================================================
SECTION HEADER
================================================= -->
<div class="section-header">
<div>
<h2>
Low Stock Alert
</h2>
<p>
Products requiring attention
</p>
</div>
</div>
<!-- =================================================
LOW STOCK TABLE
================================================= -->
<div class="low-stock-table-wrapper">
<table class="low-stock-table">
<thead>
<tr>
<th>
Product
</th>
<th>
Stock
</th>
<th>
Status
</th>
</tr>
</thead>
<tbody>
<?php if (count($lowStockProducts) > 0): ?>
<?php foreach ($lowStockProducts as $product): ?>
<?php
$stock =
(int) $product['stock'];
$minimumStock =
(int) $product['minimum_stock'];
/*
* Critical:
* Stock is zero or less than
* half of the minimum level.
*/
if (
$stock <= 0 ||
(
$minimumStock > 0 &&
$stock <= ($minimumStock / 2)
)
) {
$stockStatus = "Critical";
} else {
$stockStatus = "Low";
}
?>
<tr>
<!-- Product -->
<td>
<span
class="low-stock-product-name"
>
<?php
echo htmlspecialchars(
$product['product_name']
);
?>
</span>
</td>
<!-- Stock -->
<td>
<span
class="low-stock-quantity"
>
<?php
echo number_format($stock);
?>
<?php
echo " " .
htmlspecialchars(
$product['unit']
);
?>
</span>
</td>
<!-- Status -->
<td>
<?php if ($stockStatus ===
"Critical"): ?>
<span
class="low-stock-status
critical"
>
Critical
</span>
<?php else: ?>
<span
class="low-stock-status low"
>
Low
</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td
colspan="3"
class="low-stock-empty"
>
✓ All products have sufficient stock.
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>

            </div>

        </main>

        <script src="../../assets/js/sidebar.js"></script>

<!-- =====================================================
SALES OVERVIEW CHART JAVASCRIPT
===================================================== -->
<script>
document.addEventListener(
"DOMContentLoaded",
function () {
/*
|-------------------------------------------------------------------------
-
| Find Chart Canvas
|-------------------------------------------------------------------------
-
*/
const chartCanvas =
document.getElementById(
"salesOverviewChart"
);
/*
|-------------------------------------------------------------------------
-
| Stop if Canvas Does Not Exist
|-------------------------------------------------------------------------
-
*/
if (!chartCanvas) {
return;
}
/*
|-------------------------------------------------------------------------
-
| PHP → JavaScript
|-------------------------------------------------------------------------
-
*/
const salesLabels = <?php
echo json_encode(
$monthLabels
);
?>;
const salesData = <?php
echo json_encode(
array_values(
$monthlySales
)
);
?>;
/*
|-------------------------------------------------------------------------
-
| Create Chart
|-------------------------------------------------------------------------
-
*/
new Chart(
chartCanvas,
{
type: "line",
data: {
labels: salesLabels,
datasets: [
{
label: "Sales",
data: salesData,
borderWidth: 2,
tension: 0.4,
fill: true,
pointRadius: 4,
pointHoverRadius: 6
}
]
},
options: {
responsive: true,
maintainAspectRatio: false,
plugins: {
legend: {
display: false
}
},
scales: {
x: {
grid: {
display: false
}
},
y: {
beginAtZero: true,
ticks: {
callback: function (value) {
return "৳" +
Number(value)
.toLocaleString();
}
}
}
}
}
}
);
}
);
</script>
    </div><!-- /.app-main-slot -->

</div><!-- /.app-layout -->

<?php
/*
|-------------------------------------------------------------------------
-
| Shared Footer
|-------------------------------------------------------------------------
-
*/
require_once "../../includes/footer.php";
?>
