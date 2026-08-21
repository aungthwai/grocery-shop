<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Settings - Backup & Restore";
$msg = "";

// BACKUP DATABASE
if (isset($_GET['backup'])) {
    $dbName = 'grocery_shop';
    $tables = mysqli_query($conn, "SHOW TABLES");
    $sql = "-- GrocerEase Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- Database: $dbName\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    
    while ($tRow = mysqli_fetch_row($tables)) {
        $table = $tRow[0];
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $createRow = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE `$table`"));
        $sql .= $createRow[1] . ";\n\n";
        
        $rows = mysqli_query($conn, "SELECT * FROM `$table`");
        while ($dataRow = mysqli_fetch_row($rows)) {
            $values = array_map(function($v) use ($conn) {
                return $v === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
            }, $dataRow);
            $sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    $filename = "grocery_backup_" . date('Y-m-d_H-i-s') . ".sql";
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

// Database stats
$stats = [];
$tables = ['customers','suppliers','products','sales','purchases','sale_items','purchase_items','users'];
foreach ($tables as $t) {
    $cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM `$t`"))['c'];
    $stats[$t] = $cnt;
}

require_once "../../includes/header.php";
?>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">

        <div class="page-header">
          <div><h1>💾 Backup & Restore</h1><p>Export and backup your database data.</p></div>
        </div>

        <?php echo $msg; ?>

        <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px;">
          <!-- Backup -->
          <div class="card">
            <div class="card-title">Database Backup</div>
            <p style="color:#64748b; font-size:14px;">Download a complete SQL backup of your database including all tables, products, sales, customers, and settings.</p>
            <div style="background:#eff6ff; border-radius:8px; padding:16px; margin:16px 0;">
              <strong>What's included in backup:</strong>
              <ul style="margin:8px 0; color:#64748b; font-size:14px;">
                <li>All products, categories, suppliers</li>
                <li>All customers and their data</li>
                <li>All sales and purchase records</li>
                <li>All settings and user accounts</li>
              </ul>
            </div>
            <a href="?backup=1" class="btn btn-primary">⬇️ Download SQL Backup</a>
          </div>

          <!-- Database Stats -->
          <div class="card">
            <div class="card-title">Database Summary</div>
            <table class="data-table">
              <thead><tr><th>Table</th><th>Records</th></tr></thead>
              <tbody>
                <?php foreach ($stats as $table => $count): ?>
                <tr>
                  <td style="text-transform:capitalize;"><?php echo str_replace('_', ' ', $table); ?></td>
                  <td><span class="badge badge-info"><?php echo $count; ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
