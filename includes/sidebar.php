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
    isSidebarActive('/modules/suppliers/');

$settingsOpen =
    isSidebarActive('/modules/settings/');


/*
|--------------------------------------------------------------------------
| ROLE-BASED NAVIGATION
|--------------------------------------------------------------------------
| Admin = full management navigation
| Staff = cashier navigation (Dashboard + Record Sale)
|--------------------------------------------------------------------------
*/

$isAdmin =
    (string) ($_SESSION['role'] ?? '') === 'Admin';

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


        <?php if ($isAdmin): ?>

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
                onclick="return GrocerEaseSidebarDropdown.toggle(event, this, 'productSubmenu')"
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
            href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/wholesale/index.php"
            class="sidebar-link <?php echo isSidebarActive('/modules/wholesale/') ? 'active' : ''; ?>"
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
                onclick="return GrocerEaseSidebarDropdown.toggle(event, this, 'supplierSubmenu')"
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
                    href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/modules/suppliers/index.php"
                    class="sidebar-sublink <?php echo isSidebarActive('/modules/suppliers/') ? 'active' : ''; ?>"
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
                onclick="return GrocerEaseSidebarDropdown.toggle(event, this, 'settingsSubmenu')"
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


        <?php endif; ?>

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
     SELF-CONTAINED SIDEBAR DROPDOWN CONTROLLER
     =========================================================
     This controller intentionally lives in sidebar.php.
     It prevents page-specific or cached sidebar.js files from
     changing Product / Supplier / Settings dropdown state.
     ========================================================= -->

<script>
window.GrocerEaseSidebarDropdown = (function () {
    'use strict';

    function getSidebar() {
        return document.getElementById('appSidebar');
    }

    function closeGroup(group) {

        if (!group) {
            return;
        }

        const submenu =
            group.querySelector('.sidebar-submenu');

        const toggle =
            group.querySelector('.sidebar-toggle');

        group.classList.remove('open');

        if (submenu) {
            submenu.style.display = 'none';
        }

        if (toggle) {
            toggle.setAttribute(
                'aria-expanded',
                'false'
            );
        }
    }

    function openGroup(group) {

        if (!group) {
            return;
        }

        const submenu =
            group.querySelector('.sidebar-submenu');

        const toggle =
            group.querySelector('.sidebar-toggle');

        group.classList.add('open');

        if (submenu) {
            submenu.style.display = 'block';
        }

        if (toggle) {
            toggle.setAttribute(
                'aria-expanded',
                'true'
            );
        }
    }

    function toggle(event, button, submenuId) {

        /*
        |--------------------------------------------------------------------------
        | STOP OLD / DUPLICATE SIDEBAR HANDLERS
        |--------------------------------------------------------------------------
        | Some existing pages still load an older sidebar.js. This stops that
        | file from running another click handler after this one.
        |--------------------------------------------------------------------------
        */

        if (event) {

            event.preventDefault();
            event.stopPropagation();

            if (
                typeof event.stopImmediatePropagation ===
                'function'
            ) {
                event.stopImmediatePropagation();
            }
        }

        const sidebar =
            getSidebar();

        if (!sidebar || !button) {
            return false;
        }

        const group =
            button.closest('.sidebar-group');

        const submenu =
            document.getElementById(submenuId);

        if (!group || !submenu) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | DETERMINE CURRENT STATE
        |--------------------------------------------------------------------------
        | We read the actual computed visibility instead of relying only on a
        | CSS class. This makes it work even when another page stylesheet has
        | changed the class state.
        |--------------------------------------------------------------------------
        */

        const currentlyVisible =
            window.getComputedStyle(submenu).display !==
            'none';

        /*
        |--------------------------------------------------------------------------
        | CLOSE ALL SIDEBAR DROPDOWNS
        |--------------------------------------------------------------------------
        */

        sidebar
            .querySelectorAll('.sidebar-group')
            .forEach(function (otherGroup) {

                closeGroup(otherGroup);

            });

        /*
        |--------------------------------------------------------------------------
        | OPEN SELECTED DROPDOWN WHEN IT WAS CLOSED
        |--------------------------------------------------------------------------
        */

        if (!currentlyVisible) {
            openGroup(group);
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE INITIAL STATE
    |--------------------------------------------------------------------------
    | PHP marks the active Product / Supplier / Settings group as .open.
    | We explicitly synchronize its inline display state after rendering.
    |--------------------------------------------------------------------------
    */

    function normalizeInitialState() {

        const sidebar =
            getSidebar();

        if (!sidebar) {
            return;
        }

        sidebar
            .querySelectorAll('.sidebar-group')
            .forEach(function (group) {

                if (
                    group.classList.contains('open')
                ) {
                    openGroup(group);
                } else {
                    closeGroup(group);
                }

            });
    }

    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            normalizeInitialState,
            { once: true }
        );

    } else {

        normalizeInitialState();
    }

    return {
        toggle: toggle
    };

})();
</script>

<!-- SIDEBAR-TYPOGRAPHY-HARD-LOCK-START -->

<style>
/*
|--------------------------------------------------------------------------
| GROCER EASE SIDEBAR TYPOGRAPHY HARD LOCK
|--------------------------------------------------------------------------
| This is intentionally kept inside sidebar.php so every page gets the
| exact same sidebar typography regardless of its own CSS files.
|--------------------------------------------------------------------------
*/

/* Main menu items */
.app-sidebar .sidebar-link,
.app-sidebar a.sidebar-link,
.app-sidebar button.sidebar-link,
.app-sidebar .sidebar-toggle {
    font-family: "Segoe UI", Arial, sans-serif !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    line-height: 18px !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
    font-style: normal !important;
    font-variant: normal !important;

    -webkit-text-size-adjust: 100% !important;
    text-size-adjust: 100% !important;
}

/* Normal / hover / focus / active must ALL stay identical */
.app-sidebar .sidebar-link:hover,
.app-sidebar .sidebar-link:focus,
.app-sidebar .sidebar-link:active,
.app-sidebar .sidebar-link.active,
.app-sidebar .sidebar-toggle:hover,
.app-sidebar .sidebar-toggle:focus,
.app-sidebar .sidebar-toggle:active {
    font-family: "Segoe UI", Arial, sans-serif !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    line-height: 18px !important;
    letter-spacing: 0 !important;
}

/* Text labels */
.app-sidebar .sidebar-label,
.app-sidebar .sidebar-link .sidebar-label,
.app-sidebar .sidebar-link:hover .sidebar-label,
.app-sidebar .sidebar-link.active .sidebar-label,
.app-sidebar .sidebar-toggle .sidebar-label,
.app-sidebar .sidebar-toggle:hover .sidebar-label {
    font-family: "Segoe UI", Arial, sans-serif !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    line-height: 18px !important;
    letter-spacing: 0 !important;
}

/* Product / Supplier / Settings submenu */
.app-sidebar .sidebar-sublink,
.app-sidebar a.sidebar-sublink,
.app-sidebar .sidebar-sublink:hover,
.app-sidebar .sidebar-sublink:focus,
.app-sidebar .sidebar-sublink:active,
.app-sidebar .sidebar-sublink.active {
    font-family: "Segoe UI", Arial, sans-serif !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    line-height: 16px !important;
    letter-spacing: 0 !important;

    -webkit-text-size-adjust: 100% !important;
    text-size-adjust: 100% !important;
}

/* Icons */
.app-sidebar .sidebar-icon,
.app-sidebar .sidebar-link .sidebar-icon,
.app-sidebar .sidebar-link:hover .sidebar-icon,
.app-sidebar .sidebar-link.active .sidebar-icon {
    font-size: 15px !important;
    line-height: 15px !important;
}

/* Dropdown arrows */
.app-sidebar .sidebar-arrow {
    font-size: 11px !important;
    line-height: 11px !important;
}

/* Brand stays identical everywhere */
.app-sidebar .sidebar-brand-name {
    font-family: "Segoe UI", Arial, sans-serif !important;
    font-size: 17px !important;
    font-weight: 700 !important;
    line-height: 21px !important;
}

.app-sidebar .sidebar-brand-subtitle {
    font-family: "Segoe UI", Arial, sans-serif !important;
    font-size: 11px !important;
    font-weight: 400 !important;
    line-height: 14px !important;
}

/* Footer */
.app-sidebar .sidebar-footer-text span {
    font-family: "Segoe UI", Arial, sans-serif !important;
    font-size: 11px !important;
    font-weight: 600 !important;
}

.app-sidebar .sidebar-footer-text small {
    font-family: "Segoe UI", Arial, sans-serif !important;
    font-size: 10px !important;
    font-weight: 400 !important;
}

/*
|--------------------------------------------------------------------------
| Prevent generic page button/link CSS from changing navigation typography
|--------------------------------------------------------------------------
*/

.app-sidebar a,
.app-sidebar button,
.app-sidebar label {
    -webkit-text-size-adjust: 100% !important;
    text-size-adjust: 100% !important;
}
</style>

<script>
(function () {
    "use strict";

    /*
    |--------------------------------------------------------------------------
    | SIDEBAR FONT STABILIZER
    |--------------------------------------------------------------------------
    | CSS should already be sufficient, but this also puts the important
    | typography values directly on the sidebar elements. This prevents
    | individual module stylesheets from resizing them.
    |--------------------------------------------------------------------------
    */

    function stabilizeGrocerEaseSidebar() {

        var mainItems = document.querySelectorAll(
            ".app-sidebar .sidebar-link, " +
            ".app-sidebar .sidebar-toggle, " +
            ".app-sidebar .sidebar-label"
        );

        mainItems.forEach(function (element) {
            element.style.setProperty(
                "font-family",
                '"Segoe UI", Arial, sans-serif',
                "important"
            );

            element.style.setProperty(
                "font-size",
                "14px",
                "important"
            );

            element.style.setProperty(
                "font-weight",
                "500",
                "important"
            );

            element.style.setProperty(
                "line-height",
                "18px",
                "important"
            );

            element.style.setProperty(
                "letter-spacing",
                "0px",
                "important"
            );
        });


        var subItems = document.querySelectorAll(
            ".app-sidebar .sidebar-sublink"
        );

        subItems.forEach(function (element) {
            element.style.setProperty(
                "font-family",
                '"Segoe UI", Arial, sans-serif',
                "important"
            );

            element.style.setProperty(
                "font-size",
                "12px",
                "important"
            );

            element.style.setProperty(
                "font-weight",
                "500",
                "important"
            );

            element.style.setProperty(
                "line-height",
                "16px",
                "important"
            );

            element.style.setProperty(
                "letter-spacing",
                "0px",
                "important"
            );
        });
    }


    /* Run immediately */
    stabilizeGrocerEaseSidebar();


    /* Run again after the page finishes loading */
    window.addEventListener(
        "load",
        stabilizeGrocerEaseSidebar
    );


    /* Also handle browser Back / Forward cache */
    window.addEventListener(
        "pageshow",
        stabilizeGrocerEaseSidebar
    );


    /*
     * A web font finishing loading can change the apparent size of text.
     * Run the lock again after fonts finish loading.
     */
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            stabilizeGrocerEaseSidebar();
        });
    }
})();
</script>

<!-- SIDEBAR-TYPOGRAPHY-HARD-LOCK-END -->

