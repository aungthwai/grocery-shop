<?php
/*
|--------------------------------------------------------------------------
| GrocerEase Top Navigation
|--------------------------------------------------------------------------
*/

if (!isset($basePath) || $basePath === '') {
    $basePath = '/grocery-shop';
}

if (!isset($pageTitle) || $pageTitle === '') {
    $pageTitle = 'Dashboard';
}


/*
|--------------------------------------------------------------------------
| User Information
|--------------------------------------------------------------------------
*/

$userName = $_SESSION['full_name'] ?? 'User';

$userRole = $_SESSION['role'] ?? 'User';

$userInitial =
    strtoupper(
        substr($userName, 0, 1)
    );

?>

<header class="app-topbar">


    <!-- =====================================================
         MOBILE SIDEBAR BUTTON
         ===================================================== -->

    <button
        type="button"
        class="sidebar-mobile-button"
        id="sidebarMobileButton"
        aria-label="Open navigation"
        aria-controls="appSidebar"
        aria-expanded="false"
    >
        ☰
    </button>


    <!-- =====================================================
         PAGE TITLE
         ===================================================== -->

    <div class="topbar-page-info">

        <span class="topbar-page-title">

            <?php
            echo htmlspecialchars($pageTitle);
            ?>

        </span>

    </div>


    <!-- =====================================================
         RIGHT SIDE
         ===================================================== -->

    <div class="topbar-right">


        <!-- =================================================
             NOTIFICATIONS
             ================================================= -->

        <div class="topbar-notification">

            <button
                type="button"
                class="topbar-icon-button"
                id="notificationButton"
                title="Notifications"
                aria-label="Notifications"
                aria-expanded="false"
                aria-controls="notificationDropdown"
            >
                🔔
            </button>


            <div
                class="topbar-dropdown"
                id="notificationDropdown"
            >

                <div class="topbar-dropdown-header">
                    Notifications
                </div>

                <div class="topbar-dropdown-empty">
                    No new notifications.
                </div>

            </div>

        </div>


        <!-- =================================================
             USER MENU
             ================================================= -->

        <div class="topbar-user-menu">

            <button
                type="button"
                class="topbar-user-button"
                id="userMenuButton"
                aria-expanded="false"
                aria-controls="userMenuDropdown"
            >

                <div class="topbar-user-avatar">

                    <?php
                    echo htmlspecialchars($userInitial);
                    ?>

                </div>


                <div class="topbar-user-info">

                    <span class="topbar-user-name">

                        <?php
                        echo htmlspecialchars($userName);
                        ?>

                    </span>


                    <span class="topbar-user-role">

                        <?php
                        echo htmlspecialchars($userRole);
                        ?>

                    </span>

                </div>


                <span class="topbar-user-arrow">
                    ▾
                </span>

            </button>


            <div
                class="topbar-dropdown topbar-user-dropdown"
                id="userMenuDropdown"
            >

                <div class="topbar-user-dropdown-info">

                    <span class="topbar-user-dropdown-name">

                        <?php
                        echo htmlspecialchars($userName);
                        ?>

                    </span>


                    <span class="topbar-user-dropdown-role">

                        <?php
                        echo htmlspecialchars($userRole);
                        ?>

                    </span>

                </div>


                <a
                    href="<?php echo htmlspecialchars($basePath); ?>/logout.php"
                    class="topbar-dropdown-action"
                >
                    Logout
                </a>

            </div>

        </div>


        <!-- =================================================
             LOGOUT
             ================================================= -->

        <a
            href="<?php echo htmlspecialchars($basePath); ?>/logout.php"
            class="topbar-logout"
        >
            Logout
        </a>


    </div>


</header>