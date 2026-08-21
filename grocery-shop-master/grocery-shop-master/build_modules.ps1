$modulesDir = "c:\Users\DFIT\Downloads\grocery-shop-master\grocery-shop-master\modules"

$pages = @(
    @{
        Path = "customers\index.php"
        Content = @"
<?php
session_start();
if (!isset(`$_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
`$basePath = "/grocery-shop"; `$page_title = "Customer Management";

if (`$_SERVER['REQUEST_METHOD'] == 'POST' && isset(`$_POST['add_customer'])) {
    `$name = `$_POST['name']; `$phone = `$_POST['phone'];
    `$sql = "INSERT INTO customers (customer_name, phone) VALUES ('`$name', '`$phone')";
    mysqli_query(`$conn, `$sql);
}
if (isset(`$_GET['delete'])) {
    `$id = `$_GET['delete'];
    mysqli_query(`$conn, "DELETE FROM customers WHERE customer_id = `$id");
}

require_once "../../includes/header.php";
?>
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<style>.form-container, .table-container { background: white; padding: 20px; border-radius: 8px; margin-top: 20px; } .data-table { width: 100%; border-collapse: collapse; } .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #eee; text-align:left; } input, button { padding: 8px; margin: 5px 0; }</style>
<div class="app-layout">
    <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
    <div class="app-main-slot">
        <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
        <main class="dashboard-main-content">
            <div class="dashboard-page" style="padding: 20px;">
                <h1>Customer Management</h1>
                <div class="form-container">
                    <h3>Add New Customer</h3>
                    <form method="POST">
                        <input type="text" name="name" placeholder="Customer Name" required>
                        <input type="text" name="phone" placeholder="Phone Number" required>
                        <button type="submit" name="add_customer" style="background:#007bff; color:white; border:none;">Add Customer</button>
                    </form>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <tr><th>ID</th><th>Name</th><th>Phone</th><th>Due Amount</th><th>Action</th></tr>
                        <?php
                        `$res = mysqli_query(`$conn, "SELECT * FROM customers ORDER BY customer_id DESC");
                        while(`$row = mysqli_fetch_assoc(`$res)) {
                            echo "<tr><td>{`$row['customer_id']}</td><td>{`$row['customer_name']}</td><td>{`$row['phone']}</td><td>{`$row['total_due']}</td>
                            <td><a href='?delete={`$row['customer_id']}' style='color:red;'>Delete</a></td></tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
"@
    },
    @{
        Path = "supplier\index.php"
        Content = @"
<?php
session_start();
if (!isset(`$_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
`$basePath = "/grocery-shop"; `$page_title = "Supplier Management";

if (`$_SERVER['REQUEST_METHOD'] == 'POST' && isset(`$_POST['add_supplier'])) {
    `$name = `$_POST['name']; `$company = `$_POST['company']; `$phone = `$_POST['phone'];
    `$sql = "INSERT INTO suppliers (supplier_name, company, phone) VALUES ('`$name', '`$company', '`$phone')";
    mysqli_query(`$conn, `$sql);
}
if (isset(`$_GET['delete'])) {
    `$id = `$_GET['delete'];
    mysqli_query(`$conn, "DELETE FROM suppliers WHERE supplier_id = `$id");
}

require_once "../../includes/header.php";
?>
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<style>.form-container, .table-container { background: white; padding: 20px; border-radius: 8px; margin-top: 20px; } .data-table { width: 100%; border-collapse: collapse; } .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #eee; text-align:left; } input, button { padding: 8px; margin: 5px 0; }</style>
<div class="app-layout">
    <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
    <div class="app-main-slot">
        <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
        <main class="dashboard-main-content">
            <div class="dashboard-page" style="padding: 20px;">
                <h1>Supplier Management</h1>
                <div class="form-container">
                    <h3>Add New Supplier</h3>
                    <form method="POST">
                        <input type="text" name="name" placeholder="Supplier Name" required>
                        <input type="text" name="company" placeholder="Company Name">
                        <input type="text" name="phone" placeholder="Phone Number" required>
                        <button type="submit" name="add_supplier" style="background:#007bff; color:white; border:none;">Add Supplier</button>
                    </form>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <tr><th>ID</th><th>Name</th><th>Company</th><th>Phone</th><th>Action</th></tr>
                        <?php
                        `$res = mysqli_query(`$conn, "SELECT * FROM suppliers ORDER BY supplier_id DESC");
                        while(`$row = mysqli_fetch_assoc(`$res)) {
                            echo "<tr><td>{`$row['supplier_id']}</td><td>{`$row['supplier_name']}</td><td>{`$row['company']}</td><td>{`$row['phone']}</td>
                            <td><a href='?delete={`$row['supplier_id']}' style='color:red;'>Delete</a></td></tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
"@
    },
    @{
        Path = "inventory\index.php"
        Content = @"
<?php
session_start();
if (!isset(`$_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
`$basePath = "/grocery-shop"; `$page_title = "Inventory";
require_once "../../includes/header.php";
?>
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<style>.table-container { background: white; padding: 20px; border-radius: 8px; margin-top: 20px; } .data-table { width: 100%; border-collapse: collapse; } .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #eee; text-align:left; } .low-stock { color: red; font-weight: bold; }</style>
<div class="app-layout">
    <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
    <div class="app-main-slot">
        <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
        <main class="dashboard-main-content">
            <div class="dashboard-page" style="padding: 20px;">
                <h1>Inventory Monitor</h1>
                <div class="table-container">
                    <table class="data-table">
                        <tr><th>Product ID</th><th>Product Name</th><th>Purchase Price</th><th>Selling Price</th><th>Current Stock</th><th>Status</th></tr>
                        <?php
                        `$res = mysqli_query(`$conn, "SELECT * FROM products ORDER BY stock ASC");
                        while(`$row = mysqli_fetch_assoc(`$res)) {
                            `$stockCls = (`$row['stock'] <= `$row['minimum_stock']) ? 'low-stock' : '';
                            `$status = (`$row['stock'] <= `$row['minimum_stock']) ? 'Low Stock' : 'In Stock';
                            echo "<tr><td>{`$row['product_id']}</td><td>{`$row['product_name']}</td><td>৳{`$row['purchase_price']}</td><td>৳{`$row['selling_price']}</td>
                            <td class='`$stockCls'>{`$row['stock']} {`$row['unit']}</td><td class='`$stockCls'>`$status</td></tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
"@
    },
    @{
        Path = "sales\index.php"
        Content = @"
<?php
session_start();
if (!isset(`$_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
`$basePath = "/grocery-shop"; `$page_title = "Record Sale";

if (`$_SERVER['REQUEST_METHOD'] == 'POST' && isset(`$_POST['record_sale'])) {
    `$customer_id = `$_POST['customer_id'];
    `$product_id = `$_POST['product_id'];
    `$qty = `$_POST['qty'];
    
    // Get product price
    `$p_res = mysqli_query(`$conn, "SELECT selling_price, stock FROM products WHERE product_id = `$product_id");
    `$p_row = mysqli_fetch_assoc(`$p_res);
    
    if (`$p_row && `$p_row['stock'] >= `$qty) {
        `$price = `$p_row['selling_price'];
        `$total = `$price * `$qty;
        `$invoice = "INV-" . time();
        
        // Insert Sale
        mysqli_query(`$conn, "INSERT INTO sales (customer_id, invoice_no, sale_date, total_amount, paid_amount) VALUES (`$customer_id, '`$invoice', CURDATE(), `$total, `$total)");
        `$sale_id = mysqli_insert_id(`$conn);
        
        // Insert Sale Item
        mysqli_query(`$conn, "INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal) VALUES (`$sale_id, `$product_id, `$qty, `$price, `$total)");
        
        // Update Stock
        mysqli_query(`$conn, "UPDATE products SET stock = stock - `$qty WHERE product_id = `$product_id");
        
        `$msg = "<div style='color:green; padding:10px;'>Sale recorded successfully! Invoice: `$invoice</div>";
    } else {
        `$msg = "<div style='color:red; padding:10px;'>Error: Not enough stock or invalid product.</div>";
    }
}

require_once "../../includes/header.php";
?>
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<style>.form-container, .table-container { background: white; padding: 20px; border-radius: 8px; margin-top: 20px; } .data-table { width: 100%; border-collapse: collapse; } .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #eee; text-align:left; } select, input, button { padding: 8px; margin: 5px 0; width: 200px; display:block;}</style>
<div class="app-layout">
    <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
    <div class="app-main-slot">
        <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
        <main class="dashboard-main-content">
            <div class="dashboard-page" style="padding: 20px;">
                <h1>Record Sale / Billing</h1>
                <?php if(isset(`$msg)) echo `$msg; ?>
                <div class="form-container">
                    <h3>Create New Invoice</h3>
                    <form method="POST">
                        <label>Select Customer:</label>
                        <select name="customer_id" required>
                            <?php 
                            `$c = mysqli_query(`$conn, "SELECT * FROM customers");
                            while(`$row = mysqli_fetch_assoc(`$c)) { echo "<option value='{`$row['customer_id']}'>{`$row['customer_name']}</option>"; }
                            ?>
                        </select>
                        <label>Select Product:</label>
                        <select name="product_id" required>
                            <?php 
                            `$p = mysqli_query(`$conn, "SELECT * FROM products WHERE stock > 0");
                            while(`$row = mysqli_fetch_assoc(`$p)) { echo "<option value='{`$row['product_id']}'>{`$row['product_name']} (Stock: {`$row['stock']}) - ৳{`$row['selling_price']}</option>"; }
                            ?>
                        </select>
                        <label>Quantity:</label>
                        <input type="number" name="qty" min="1" required>
                        <button type="submit" name="record_sale" style="background:#28a745; color:white; border:none; width:auto; padding:10px 20px; cursor:pointer;">Generate Bill</button>
                    </form>
                </div>
                
                <div class="table-container">
                    <h3>Recent Sales</h3>
                    <table class="data-table">
                        <tr><th>Invoice No</th><th>Date</th><th>Amount</th><th>Status</th></tr>
                        <?php
                        `$res = mysqli_query(`$conn, "SELECT * FROM sales ORDER BY sale_id DESC LIMIT 10");
                        while(`$row = mysqli_fetch_assoc(`$res)) {
                            echo "<tr><td>{`$row['invoice_no']}</td><td>{`$row['sale_date']}</td><td>৳{`$row['total_amount']}</td><td style='color:green;'>Paid</td></tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
"@
    },
    @{
        Path = "purchases\index.php"
        Content = @"
<?php
session_start();
if (!isset(`$_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
`$basePath = "/grocery-shop"; `$page_title = "Purchase Management";

if (`$_SERVER['REQUEST_METHOD'] == 'POST' && isset(`$_POST['record_purchase'])) {
    `$supplier_id = `$_POST['supplier_id'];
    `$product_id = `$_POST['product_id'];
    `$qty = `$_POST['qty'];
    `$price = `$_POST['price'];
    `$total = `$price * `$qty;
    `$invoice = "PUR-" . time();
    
    // Insert Purchase
    mysqli_query(`$conn, "INSERT INTO purchases (supplier_id, invoice_no, purchase_date, total_amount) VALUES (`$supplier_id, '`$invoice', CURDATE(), `$total)");
    `$purchase_id = mysqli_insert_id(`$conn);
    
    // Insert Purchase Item
    mysqli_query(`$conn, "INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) VALUES (`$purchase_id, `$product_id, `$qty, `$price, `$total)");
    
    // Update Stock
    mysqli_query(`$conn, "UPDATE products SET stock = stock + `$qty WHERE product_id = `$product_id");
    
    `$msg = "<div style='color:green; padding:10px;'>Purchase recorded and stock updated! Invoice: `$invoice</div>";
}

require_once "../../includes/header.php";
?>
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<style>.form-container, .table-container { background: white; padding: 20px; border-radius: 8px; margin-top: 20px; } .data-table { width: 100%; border-collapse: collapse; } .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #eee; text-align:left; } select, input, button { padding: 8px; margin: 5px 0; width: 200px; display:block;}</style>
<div class="app-layout">
    <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
    <div class="app-main-slot">
        <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
        <main class="dashboard-main-content">
            <div class="dashboard-page" style="padding: 20px;">
                <h1>Purchase Management</h1>
                <?php if(isset(`$msg)) echo `$msg; ?>
                <div class="form-container">
                    <h3>Record New Stock Purchase</h3>
                    <form method="POST">
                        <label>Select Supplier:</label>
                        <select name="supplier_id" required>
                            <?php 
                            `$c = mysqli_query(`$conn, "SELECT * FROM suppliers");
                            while(`$row = mysqli_fetch_assoc(`$c)) { echo "<option value='{`$row['supplier_id']}'>{`$row['supplier_name']}</option>"; }
                            ?>
                        </select>
                        <label>Select Product:</label>
                        <select name="product_id" required>
                            <?php 
                            `$p = mysqli_query(`$conn, "SELECT * FROM products");
                            while(`$row = mysqli_fetch_assoc(`$p)) { echo "<option value='{`$row['product_id']}'>{`$row['product_name']}</option>"; }
                            ?>
                        </select>
                        <label>Quantity Added:</label>
                        <input type="number" name="qty" min="1" required>
                        <label>Total Price for this batch:</label>
                        <input type="number" step="0.01" name="price" required>
                        <button type="submit" name="record_purchase" style="background:#007bff; color:white; border:none; width:auto; padding:10px 20px; cursor:pointer;">Save Purchase</button>
                    </form>
                </div>
                
                <div class="table-container">
                    <h3>Recent Purchases</h3>
                    <table class="data-table">
                        <tr><th>Invoice No</th><th>Date</th><th>Amount</th></tr>
                        <?php
                        `$res = mysqli_query(`$conn, "SELECT * FROM purchases ORDER BY purchase_id DESC LIMIT 10");
                        while(`$row = mysqli_fetch_assoc(`$res)) {
                            echo "<tr><td>{`$row['invoice_no']}</td><td>{`$row['purchase_date']}</td><td>৳{`$row['total_amount']}</td></tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
"@
    },
    @{
        Path = "reports\index.php"
        Content = @"
<?php
session_start();
if (!isset(`$_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
`$basePath = "/grocery-shop"; `$page_title = "Reports";
require_once "../../includes/header.php";

// Fetch data
`$sales = mysqli_fetch_assoc(mysqli_query(`$conn, "SELECT SUM(total_amount) as s, COUNT(*) as c FROM sales"));
`$purchases = mysqli_fetch_assoc(mysqli_query(`$conn, "SELECT SUM(total_amount) as p, COUNT(*) as c FROM purchases"));
`$inventory = mysqli_fetch_assoc(mysqli_query(`$conn, "SELECT SUM(stock * purchase_price) as v, SUM(stock) as s FROM products"));
?>
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<style>.card-container { display: flex; gap: 20px; margin-top: 20px; } .report-card { background: white; padding: 20px; border-radius: 8px; flex: 1; box-shadow: 0 2px 10px rgba(0,0,0,0.05); } .report-card h3 { margin-top:0; color: #555; } .report-card .val { font-size: 28px; font-weight: bold; color: #333; }</style>
<div class="app-layout">
    <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
    <div class="app-main-slot">
        <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
        <main class="dashboard-main-content">
            <div class="dashboard-page" style="padding: 20px;">
                <h1>System Reports & Analytics</h1>
                <div class="card-container">
                    <div class="report-card">
                        <h3>Total Sales Revenue</h3>
                        <div class="val">৳<?php echo number_format((float)`$sales['s'], 2); ?></div>
                        <p><?php echo `$sales['c']; ?> total transactions</p>
                    </div>
                    <div class="report-card">
                        <h3>Total Purchase Cost</h3>
                        <div class="val">৳<?php echo number_format((float)`$purchases['p'], 2); ?></div>
                        <p><?php echo `$purchases['c']; ?> total purchases</p>
                    </div>
                    <div class="report-card">
                        <h3>Inventory Value</h3>
                        <div class="val">৳<?php echo number_format((float)`$inventory['v'], 2); ?></div>
                        <p><?php echo `$inventory['s']; ?> total items in stock</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
"@
    },
    @{
        Path = "wholesale_due\index.php"
        Content = @"
<?php
session_start();
if (!isset(`$_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
`$basePath = "/grocery-shop"; `$page_title = "Wholesale Due Management";
require_once "../../includes/header.php";
?>
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">
<style>.table-container { background: white; padding: 20px; border-radius: 8px; margin-top: 20px; } .data-table { width: 100%; border-collapse: collapse; } .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #eee; text-align:left; }</style>
<div class="app-layout">
    <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
    <div class="app-main-slot">
        <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
        <main class="dashboard-main-content">
            <div class="dashboard-page" style="padding: 20px;">
                <h1>Wholesale Due Management</h1>
                <div class="table-container">
                    <table class="data-table">
                        <tr><th>Customer Name</th><th>Phone</th><th>Total Due</th><th>Action</th></tr>
                        <?php
                        `$res = mysqli_query(`$conn, "SELECT * FROM customers WHERE total_due > 0");
                        while(`$row = mysqli_fetch_assoc(`$res)) {
                            echo "<tr><td>{`$row['customer_name']}</td><td>{`$row['phone']}</td><td style='color:red; font-weight:bold;'>৳{`$row['total_due']}</td>
                            <td><button style='background:#28a745; color:white; border:none; padding:5px 10px; border-radius:3px; cursor:pointer;'>Receive Payment</button></td></tr>";
                        }
                        if (mysqli_num_rows(`$res) == 0) {
                            echo "<tr><td colspan='4'>No outstanding dues found.</td></tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
"@
    }
)

foreach ($page in $pages) {
    $fullPath = Join-Path $modulesDir $page.Path
    $dirPath = Split-Path $fullPath -Parent
    
    if (-not (Test-Path $dirPath)) {
        New-Item -ItemType Directory -Force -Path $dirPath | Out-Null
    }
    
    Set-Content -Path $fullPath -Value $page.Content -Encoding UTF8
}
