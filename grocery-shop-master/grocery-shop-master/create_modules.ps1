$modulesDir = "c:\Users\DFIT\Downloads\grocery-shop-master\grocery-shop-master\modules"

$pages = @(
    @{ Path = "sales\index.php"; Title = "Record Sale / Generate Bill"; Desc = "Process sales transactions and generate customer invoices." },
    @{ Path = "inventory\index.php"; Title = "Inventory Management"; Desc = "Monitor and manage product stock levels." },
    @{ Path = "purchases\index.php"; Title = "Purchases Management"; Desc = "Manage purchase records and incoming stock." },
    @{ Path = "customers\index.php"; Title = "Customer Management"; Desc = "Manage customer information and track history." },
    @{ Path = "wholesale_due\index.php"; Title = "Wholesale Due Management"; Desc = "Track and manage outstanding due payments from wholesale customers." },
    @{ Path = "supplier\index.php"; Title = "Supplier Management"; Desc = "Manage supplier details and purchase history." },
    @{ Path = "reports\index.php"; Title = "Reports & Analytics"; Desc = "Generate sales, inventory, and purchase reports." },
    @{ Path = "settings\security.php"; Title = "Security Settings"; Desc = "Manage system security and passwords." },
    @{ Path = "settings\backup_restore.php"; Title = "Backup & Restore"; Desc = "Backup database and restore previous backups." }
)

foreach ($page in $pages) {
    $fullPath = Join-Path $modulesDir $page.Path
    $dirPath = Split-Path $fullPath -Parent
    
    if (-not (Test-Path $dirPath)) {
        New-Item -ItemType Directory -Force -Path $dirPath | Out-Null
    }
    
    $title = $page.Title
    $desc = $page.Desc

    $content = @"
<?php
session_start();
if (!isset(`$_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}
require_once "../../config/database.php";
`$basePath = "/grocery-shop";
`$page_title = "$title";
require_once "../../includes/header.php";
?>
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/topbar.css">
<link rel="stylesheet" href="../../assets/css/dashboard-layout.css">

<style>
.table-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-top: 20px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { background: #f8f9fa; font-weight: 600; color: #333; }
.btn-primary { background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; text-decoration: none; }
</style>

<div class="app-layout">
    <aside class="app-sidebar-slot">
        <?php require_once "../../includes/sidebar.php"; ?>
    </aside>
    <div class="app-main-slot">
        <header class="app-topbar-slot">
            <?php require_once "../../includes/topbar.php"; ?>
        </header>
        <main class="dashboard-main-content">
            <div class="dashboard-page" style="padding: 20px;">
                
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 style="margin:0; font-size: 24px; color: #333;">$title</h1>
                        <p style="color: #666; margin-top: 5px;">$desc</p>
                    </div>
                    <button class="btn-primary">+ Add New</button>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name / Details</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 30px; color: #888;">
                                    No data available yet. This module is setup based on SRS requirements and ready for backend integration.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
"@

    Set-Content -Path $fullPath -Value $content -Encoding UTF8
}
