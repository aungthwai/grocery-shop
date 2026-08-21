/*
|--------------------------------------------------------------------------
| GrocerEase Shared Topbar / Mobile Sidebar JavaScript
|--------------------------------------------------------------------------
| IMPORTANT:
| Product Management, Supplier Management and Settings dropdowns are handled
| directly inside includes/sidebar.php.
|
| This file handles only:
| - Mobile sidebar open/close
| - Notification dropdown
| - User menu dropdown
|--------------------------------------------------------------------------
*/

(function () {
    'use strict';


    /*
    |--------------------------------------------------------------------------
    | PREVENT DOUBLE LOADING
    |--------------------------------------------------------------------------
    */

    if (window.GrocerEaseSharedUiInitialized) {
        return;
    }

    window.GrocerEaseSharedUiInitialized = true;


    function initializeSharedUi() {

        /*
        |--------------------------------------------------------------------------
        | MOBILE SIDEBAR
        |--------------------------------------------------------------------------
        */

        const mobileButton =
            document.getElementById('sidebarMobileButton');

        const sidebar =
            document.getElementById('appSidebar');


        if (mobileButton && sidebar) {

            mobileButton.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                    const isOpen =
                        sidebar.classList.toggle('mobile-open');

                    mobileButton.setAttribute(
                        'aria-expanded',
                        isOpen ? 'true' : 'false'
                    );

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION DROPDOWN
        |--------------------------------------------------------------------------
        */

        const notificationButton =
            document.getElementById('notificationButton');

        const notificationDropdown =
            document.getElementById('notificationDropdown');


        /*
        |--------------------------------------------------------------------------
        | USER MENU
        |--------------------------------------------------------------------------
        */

        const userButton =
            document.getElementById('userMenuButton');

        const userDropdown =
            document.getElementById('userMenuDropdown');


        function closeNotificationDropdown() {

            if (notificationDropdown) {
                notificationDropdown.classList.remove('open');
            }

            if (notificationButton) {

                notificationButton.classList.remove('active');

                notificationButton.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }

        }


        function closeUserDropdown() {

            if (userDropdown) {
                userDropdown.classList.remove('open');
            }

            if (userButton) {

                userButton.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }

        }


        if (
            notificationButton &&
            notificationDropdown
        ) {

            notificationButton.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                    closeUserDropdown();

                    const isOpen =
                        notificationDropdown.classList.toggle('open');

                    notificationButton.classList.toggle(
                        'active',
                        isOpen
                    );

                    notificationButton.setAttribute(
                        'aria-expanded',
                        isOpen ? 'true' : 'false'
                    );

                }
            );


            notificationDropdown.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                }
            );

        }


        if (userButton && userDropdown) {

            userButton.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                    closeNotificationDropdown();

                    const isOpen =
                        userDropdown.classList.toggle('open');

                    userButton.setAttribute(
                        'aria-expanded',
                        isOpen ? 'true' : 'false'
                    );

                }
            );


            userDropdown.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | CLICK OUTSIDE TOPBAR MENUS
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function () {

                closeNotificationDropdown();
                closeUserDropdown();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | MOBILE SIDEBAR LINKS
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll('.app-sidebar a')
            .forEach(function (link) {

                link.addEventListener(
                    'click',
                    function () {

                        if (
                            window.innerWidth <= 900 &&
                            sidebar
                        ) {

                            sidebar.classList.remove('mobile-open');

                        }

                        if (mobileButton) {

                            mobileButton.setAttribute(
                                'aria-expanded',
                                'false'
                            );

                        }

                    }
                );

            });


        /*
        |--------------------------------------------------------------------------
        | ESCAPE KEY
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (event) {

                if (event.key !== 'Escape') {
                    return;
                }

                closeNotificationDropdown();
                closeUserDropdown();

                if (sidebar) {
                    sidebar.classList.remove('mobile-open');
                }

                if (mobileButton) {

                    mobileButton.setAttribute(
                        'aria-expanded',
                        'false'
                    );

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | START
    |--------------------------------------------------------------------------
    */

    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            initializeSharedUi,
            { once: true }
        );

    } else {

        initializeSharedUi();

    }

})();