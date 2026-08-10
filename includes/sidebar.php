<?php
/*
|--------------------------------------------------------------------------
| GrocerEase Sidebar
|--------------------------------------------------------------------------
| Reusable sidebar navigation.
|
| Final structure:
|
| Dashboard
| Record Sale
| Inventory
| Product Management
|     Product List
|     Add Product
| Purchases Management
| Customer Management
| Wholesale Due Management
| Supplier Management
|     Supplier Details & History List
| Reports
| Settings
|     Security
|     Backup & Restore
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Base Path
|--------------------------------------------------------------------------
*/

if (!isset($basePath) || $basePath === '') {
    $basePath = '/grocery-shop';
}


/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
*/

$currentPath = $_SERVER['PHP_SELF'] ?? '';


/*
|--------------------------------------------------------------------------
| Sidebar Active Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('isSidebarActive')) {

    function isSidebarActive($path)
    {
        global $currentPath;

        return strpos($currentPath, $path) !== false;
    }

}


/*
|--------------------------------------------------------------------------
| Dropdown States
|--------------------------------------------------------------------------
*/

$productOpen =
    isSidebarActive('/products/');

$supplierOpen =
    isSidebarActive('/supplier/');

$settingsOpen =
    isSidebarActive('/settings/');

?>


<aside class="app-sidebar" id="appSidebar">


    <!-- =====================================================
         BRAND
         ===================================================== -->

    <div class="sidebar-brand">

        <a
            href="<?php echo htmlspecialchars($basePath); ?>/modules/dashboard/index.php"
            class="sidebar-brand-link"
        >

            <div class="sidebar-brand-icon">
                🛒
            </div>

            <div class="sidebar-brand-text">

                <span class="sidebar-brand-name">
                    GrocerEase
                </span>

                <span class="sidebar-brand-subtitle">
                    Management System
                </span>

            </div>

        </a>

    </div>


    <!-- =====================================================
         NAVIGATION
         ===================================================== -->

    <nav
        class="sidebar-navigation"
        aria-label="Main navigation"
    >


        <!-- =================================================
             DASHBOARD
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath); ?>/modules/dashboard/index.php"
            class="sidebar-link <?php echo isSidebarActive('/dashboard/') ? 'active' : ''; ?>"
        >

            <span class="sidebar-icon">
                ▦
            </span>

            <span class="sidebar-label">
                Dashboard
            </span>

        </a>


        <!-- =================================================
             RECORD SALE
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath); ?>/modules/sales/index.php"
            class="sidebar-link <?php echo isSidebarActive('/sales/') ? 'active' : ''; ?>"
        >

            <span class="sidebar-icon">
                +
            </span>

            <span class="sidebar-label">
                Record Sale
            </span>

        </a>


        <!-- =================================================
             INVENTORY
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath); ?>/modules/inventory/index.php"
            class="sidebar-link <?php echo isSidebarActive('/inventory/') ? 'active' : ''; ?>"
        >

            <span class="sidebar-icon">
                ▣
            </span>

            <span class="sidebar-label">
                Inventory
            </span>

        </a>


        <!-- =================================================
             PRODUCT MANAGEMENT
             ================================================= -->

        <div
            class="sidebar-group <?php echo $productOpen ? 'open' : ''; ?>"
        >

            <button
                type="button"
                class="sidebar-link sidebar-toggle"
                data-target="productSubmenu"
                aria-expanded="<?php echo $productOpen ? 'true' : 'false'; ?>"
                aria-controls="productSubmenu"
            >

                <span class="sidebar-icon">
                    ▤
                </span>

                <span class="sidebar-label">
                    Product Management
                </span>

                <span class="sidebar-arrow">
                    ▾
                </span>

            </button>


            <div
                id="productSubmenu"
                class="sidebar-submenu"
            >

                <a
                    href="<?php echo htmlspecialchars($basePath); ?>/modules/products/index.php"
                    class="sidebar-sublink <?php echo basename($currentPath) === 'index.php' && isSidebarActive('/products/') ? 'active' : ''; ?>"
                >
                    Product List
                </a>


                <a
                    href="<?php echo htmlspecialchars($basePath); ?>/modules/products/add.php"
                    class="sidebar-sublink <?php echo basename($currentPath) === 'add.php' && isSidebarActive('/products/') ? 'active' : ''; ?>"
                >
                    Add Product
                </a>

            </div>

        </div>


        <!-- =================================================
             PURCHASES MANAGEMENT
             
             IMPORTANT:
             This is a NORMAL LINK.
             NO SUBMENU.
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath); ?>/modules/purchases/index.php"
            class="sidebar-link <?php echo isSidebarActive('/purchases/') ? 'active' : ''; ?>"
        >

            <span class="sidebar-icon">
                🛍
            </span>

            <span class="sidebar-label">
                Purchases Management
            </span>

        </a>


        <!-- =================================================
             CUSTOMER MANAGEMENT
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath); ?>/modules/customers/index.php"
            class="sidebar-link <?php echo isSidebarActive('/customers/') ? 'active' : ''; ?>"
        >

            <span class="sidebar-icon">
                ♙
            </span>

            <span class="sidebar-label">
                Customer Management
            </span>

        </a>


        <!-- =================================================
             WHOLESALE DUE MANAGEMENT
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath); ?>/modules/wholesale_due/index.php"
            class="sidebar-link <?php echo isSidebarActive('/wholesale_due/') ? 'active' : ''; ?>"
        >

            <span class="sidebar-icon">
                ৳
            </span>

            <span class="sidebar-label">
                Wholesale Due Management
            </span>

        </a>


        <!-- =================================================
             SUPPLIER MANAGEMENT
             ================================================= -->

        <div
            class="sidebar-group <?php echo $supplierOpen ? 'open' : ''; ?>"
        >

            <button
                type="button"
                class="sidebar-link sidebar-toggle"
                data-target="supplierSubmenu"
                aria-expanded="<?php echo $supplierOpen ? 'true' : 'false'; ?>"
                aria-controls="supplierSubmenu"
            >

                <span class="sidebar-icon">
                    ♟
                </span>

                <span class="sidebar-label">
                    Supplier Management
                </span>

                <span class="sidebar-arrow">
                    ▾
                </span>

            </button>


            <div
                id="supplierSubmenu"
                class="sidebar-submenu"
            >

                <a
                    href="<?php echo htmlspecialchars($basePath); ?>/modules/supplier/index.php"
                    class="sidebar-sublink <?php echo isSidebarActive('/supplier/') ? 'active' : ''; ?>"
                >
                    Supplier Details &amp; History List
                </a>

            </div>

        </div>


        <!-- =================================================
             REPORTS
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath); ?>/modules/reports/index.php"
            class="sidebar-link <?php echo isSidebarActive('/reports/') ? 'active' : ''; ?>"
        >

            <span class="sidebar-icon">
                ▥
            </span>

            <span class="sidebar-label">
                Reports
            </span>

        </a>


        <!-- =================================================
             SETTINGS
             ================================================= -->

        <div
            class="sidebar-group <?php echo $settingsOpen ? 'open' : ''; ?>"
        >

            <button
                type="button"
                class="sidebar-link sidebar-toggle"
                data-target="settingsSubmenu"
                aria-expanded="<?php echo $settingsOpen ? 'true' : 'false'; ?>"
                aria-controls="settingsSubmenu"
            >

                <span class="sidebar-icon">
                    ⚙
                </span>

                <span class="sidebar-label">
                    Settings
                </span>

                <span class="sidebar-arrow">
                    ▾
                </span>

            </button>


            <div
                id="settingsSubmenu"
                class="sidebar-submenu"
            >

                <a
                    href="<?php echo htmlspecialchars($basePath); ?>/modules/settings/security.php"
                    class="sidebar-sublink <?php echo basename($currentPath) === 'security.php' ? 'active' : ''; ?>"
                >
                    Security
                </a>


                <a
                    href="<?php echo htmlspecialchars($basePath); ?>/modules/settings/backup_restore.php"
                    class="sidebar-sublink <?php echo basename($currentPath) === 'backup_restore.php' ? 'active' : ''; ?>"
                >
                    Backup &amp; Restore
                </a>

            </div>

        </div>


    </nav>


    <!-- =====================================================
         SIDEBAR FOOTER
         ===================================================== -->

    <div class="sidebar-footer">

        <div class="sidebar-footer-text">

            <span>
                GrocerEase
            </span>

            <small>
                © 2026
            </small>

        </div>

    </div>


</aside>