/*
|--------------------------------------------------------------------------
| GrocerEase Sidebar / Topbar JavaScript
|--------------------------------------------------------------------------
*/

document.addEventListener("DOMContentLoaded", function () {


    /* =========================================================
       SIDEBAR DROPDOWNS
       ========================================================= */

    const sidebarToggles =
        document.querySelectorAll(".sidebar-toggle");


    sidebarToggles.forEach(function (toggle) {

        toggle.addEventListener("click", function () {

            const targetId =
                toggle.getAttribute("data-target");

            const target =
                document.getElementById(targetId);

            const group =
                toggle.closest(".sidebar-group");


            if (!target || !group) {
                return;
            }


            /*
             * Close other sidebar dropdowns.
             */

            document
                .querySelectorAll(".sidebar-group.open")
                .forEach(function (otherGroup) {

                    if (otherGroup !== group) {

                        otherGroup.classList.remove("open");

                        const otherToggle =
                            otherGroup.querySelector(
                                ".sidebar-toggle"
                            );

                        if (otherToggle) {

                            otherToggle.setAttribute(
                                "aria-expanded",
                                "false"
                            );

                        }

                    }

                });


            /*
             * Toggle selected group.
             */

            const isOpen =
                group.classList.toggle("open");


            toggle.setAttribute(
                "aria-expanded",
                isOpen ? "true" : "false"
            );

        });

    });


    /* =========================================================
       MOBILE SIDEBAR
       ========================================================= */

    const mobileButton =
        document.getElementById(
            "sidebarMobileButton"
        );


    const sidebar =
        document.getElementById(
            "appSidebar"
        );


    if (mobileButton && sidebar) {

        mobileButton.addEventListener(
            "click",
            function () {

                const isOpen =
                    sidebar.classList.toggle(
                        "mobile-open"
                    );


                mobileButton.setAttribute(
                    "aria-expanded",
                    isOpen ? "true" : "false"
                );

            }
        );

    }


    /* =========================================================
       NOTIFICATION DROPDOWN
       ========================================================= */

    const notificationButton =
        document.getElementById(
            "notificationButton"
        );


    const notificationDropdown =
        document.getElementById(
            "notificationDropdown"
        );


    if (
        notificationButton &&
        notificationDropdown
    ) {

        notificationButton.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();


                const userDropdown =
                    document.getElementById(
                        "userMenuDropdown"
                    );

                const userButton =
                    document.getElementById(
                        "userMenuButton"
                    );


                if (userDropdown) {
                    userDropdown.classList.remove("open");
                }


                if (userButton) {
                    userButton.setAttribute(
                        "aria-expanded",
                        "false"
                    );
                }


                const isOpen =
                    notificationDropdown.classList.toggle(
                        "open"
                    );


                notificationButton.classList.toggle(
                    "active",
                    isOpen
                );


                notificationButton.setAttribute(
                    "aria-expanded",
                    isOpen ? "true" : "false"
                );

            }
        );

    }


    /* =========================================================
       USER / ADMINISTRATOR DROPDOWN
       ========================================================= */

    const userButton =
        document.getElementById(
            "userMenuButton"
        );


    const userDropdown =
        document.getElementById(
            "userMenuDropdown"
        );


    if (userButton && userDropdown) {

        userButton.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();


                const notificationDropdown =
                    document.getElementById(
                        "notificationDropdown"
                    );

                const notificationButton =
                    document.getElementById(
                        "notificationButton"
                    );


                if (notificationDropdown) {

                    notificationDropdown.classList.remove(
                        "open"
                    );

                }


                if (notificationButton) {

                    notificationButton.classList.remove(
                        "active"
                    );

                    notificationButton.setAttribute(
                        "aria-expanded",
                        "false"
                    );

                }


                const isOpen =
                    userDropdown.classList.toggle(
                        "open"
                    );


                userButton.setAttribute(
                    "aria-expanded",
                    isOpen ? "true" : "false"
                );

            }
        );

    }


    /* =========================================================
       CLOSE TOPBAR DROPDOWNS WHEN CLICKING OUTSIDE
       ========================================================= */

    document.addEventListener(
        "click",
        function () {

            if (notificationDropdown) {

                notificationDropdown.classList.remove(
                    "open"
                );

            }


            if (notificationButton) {

                notificationButton.classList.remove(
                    "active"
                );

                notificationButton.setAttribute(
                    "aria-expanded",
                    "false"
                );

            }


            if (userDropdown) {

                userDropdown.classList.remove(
                    "open"
                );

            }


            if (userButton) {

                userButton.setAttribute(
                    "aria-expanded",
                    "false"
                );

            }

        }
    );


    /* =========================================================
       PREVENT DROPDOWN CLICKS FROM CLOSING THEMSELVES
       ========================================================= */

    if (notificationDropdown) {

        notificationDropdown.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

            }
        );

    }


    if (userDropdown) {

        userDropdown.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

            }
        );

    }


    /* =========================================================
       CLOSE MOBILE SIDEBAR AFTER NAVIGATION
       ========================================================= */

    document
        .querySelectorAll(
            ".app-sidebar a"
        )
        .forEach(function (link) {

            link.addEventListener(
                "click",
                function () {

                    if (
                        window.innerWidth <= 900 &&
                        sidebar
                    ) {

                        sidebar.classList.remove(
                            "mobile-open"
                        );

                    }


                    if (mobileButton) {

                        mobileButton.setAttribute(
                            "aria-expanded",
                            "false"
                        );

                    }

                }
            );

        });


});