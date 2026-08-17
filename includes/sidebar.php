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
| BASE PATH
|--------------------------------------------------------------------------
| Use one absolute application path so navigation works from every module.
|--------------------------------------------------------------------------
*/

if (!isset($basePath) || $basePath === '') {
    $basePath = '/grocery-shop';
}


/*
|--------------------------------------------------------------------------
| CURRENT PAGE
|--------------------------------------------------------------------------
*/

$currentPath = $_SERVER['PHP_SELF'] ?? '';

/*
|--------------------------------------------------------------------------
| NORMALIZE BASE PATH
|--------------------------------------------------------------------------
*/

$basePath = rtrim($basePath, '/');


/*
|--------------------------------------------------------------------------
| SIDEBAR ACTIVE HELPER
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
| DROPDOWN STATES
|--------------------------------------------------------------------------
*/

$productOpen =
    isSidebarActive('/modules/products/');

$supplierOpen =
    isSidebarActive('/modules/supplier/');

$settingsOpen =
    isSidebarActive('/modules/settings/');

?>


<aside class="app-sidebar" id="appSidebar">


    <!-- =====================================================
         BRAND
         ===================================================== -->

    <div class="sidebar-brand">

        <a
            href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/dashboard/index.php"
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
            href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/dashboard/index.php"
            class="sidebar-link <?php echo isSidebarActive('/modules/dashboard/') ? 'active' : ''; ?>"
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
            href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/sales/index.php"
            class="sidebar-link <?php echo isSidebarActive('/modules/sales/') ? 'active' : ''; ?>"
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
            href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/inventory/index.php"
            class="sidebar-link <?php echo isSidebarActive('/modules/inventory/') ? 'active' : ''; ?>"
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
            id="productManagementGroup"
        >

            <button
                type="button"
                class="sidebar-link sidebar-toggle"
                data-target="productSubmenu"
                aria-expanded="<?php echo $productOpen ? 'true' : 'false'; ?>"
                aria-controls="productSubmenu"
                onclick="toggleSidebarSubmenu(this, 'productSubmenu')"
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
                <?php echo $productOpen ? 'style="display: block;"' : 'style="display: none;"'; ?>
            >

                <!-- Product List -->

                <a
                    href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/products/index.php"
                    class="sidebar-sublink <?php echo (basename($currentPath) === 'index.php' && isSidebarActive('/modules/products/')) ? 'active' : ''; ?>"
                >
                    Product List
                </a>


                <!-- Add Product -->

                <a
                    href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/products/add.php"
                    class="sidebar-sublink <?php echo (basename($currentPath) === 'add.php' && isSidebarActive('/modules/products/')) ? 'active' : ''; ?>"
                >
                    Add Product
                </a>

            </div>

        </div>


        <!-- =================================================
             PURCHASES MANAGEMENT
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/purchases/index.php"
            class="sidebar-link <?php echo isSidebarActive('/modules/purchases/') ? 'active' : ''; ?>"
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
            href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/customers/index.php"
            class="sidebar-link <?php echo isSidebarActive('/modules/customers/') ? 'active' : ''; ?>"
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
            href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/wholesale_due/index.php"
            class="sidebar-link <?php echo isSidebarActive('/modules/wholesale_due/') ? 'active' : ''; ?>"
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
            id="supplierManagementGroup"
        >

            <button
                type="button"
                class="sidebar-link sidebar-toggle"
                data-target="supplierSubmenu"
                aria-expanded="<?php echo $supplierOpen ? 'true' : 'false'; ?>"
                aria-controls="supplierSubmenu"
                onclick="toggleSidebarSubmenu(this, 'supplierSubmenu')"
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
                <?php echo $supplierOpen ? 'style="display: block;"' : 'style="display: none;"'; ?>
            >

                <a
                    href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/supplier/index.php"
                    class="sidebar-sublink <?php echo isSidebarActive('/modules/supplier/') ? 'active' : ''; ?>"
                >
                    Supplier Details &amp; History List
                </a>

            </div>

        </div>


        <!-- =================================================
             REPORTS
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/reports/index.php"
            class="sidebar-link <?php echo isSidebarActive('/modules/reports/') ? 'active' : ''; ?>"
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
            id="settingsManagementGroup"
        >

            <button
                type="button"
                class="sidebar-link sidebar-toggle"
                data-target="settingsSubmenu"
                aria-expanded="<?php echo $settingsOpen ? 'true' : 'false'; ?>"
                aria-controls="settingsSubmenu"
                onclick="toggleSidebarSubmenu(this, 'settingsSubmenu')"
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
                <?php echo $settingsOpen ? 'style="display: block;"' : 'style="display: none;"'; ?>
            >

                <a
                    href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/settings/security.php"
                    class="sidebar-sublink <?php echo basename($currentPath) === 'security.php' ? 'active' : ''; ?>"
                >
                    Security
                </a>


                <a
                    href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/settings/backup_restore.php"
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


<!-- =========================================================
     SIDEBAR DROPDOWN FALLBACK
     ========================================================= -->

<script>

function toggleSidebarSubmenu(button, submenuId) {

    const submenu = document.getElementById(submenuId);

    if (!submenu) {
        return;
    }

    const isOpen = submenu.style.display === 'block';

    /*
    |--------------------------------------------------------------------------
    | CLOSE ALL SIDEBAR SUBMENUS
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.sidebar-submenu').forEach(function(menu) {

        menu.style.display = 'none';

    });


    /*
    |--------------------------------------------------------------------------
    | REMOVE OPEN STATE FROM ALL GROUPS
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.sidebar-group').forEach(function(group) {

        group.classList.remove('open');

    });


    /*
    |--------------------------------------------------------------------------
    | RESET ALL ARIA STATES
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.sidebar-toggle').forEach(function(toggle) {

        toggle.setAttribute('aria-expanded', 'false');

    });


    /*
    |--------------------------------------------------------------------------
    | OPEN SELECTED SUBMENU
    |--------------------------------------------------------------------------
    */

    if (!isOpen) {

        submenu.style.display = 'block';

        const parentGroup = button.closest('.sidebar-group');

        if (parentGroup) {

            parentGroup.classList.add('open');

        }

        button.setAttribute('aria-expanded', 'true');

    }

}

</script>
```
