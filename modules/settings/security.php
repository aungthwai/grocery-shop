<?php

require_once "../../includes/auth_check.php";
require_once "../../includes/role_guard.php";

grocerEaseRequireAdmin();

require_once "../../config/database.php";

$basePath = "/grocery-shop";
$page_title = "Settings - Security";


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function settingsRedirect(string $status): void
{
    header(
        'Location: security.php?status='
        . urlencode($status)
    );

    exit;
}


function verifySettingsCsrf(): void
{
    $sessionToken =
        $_SESSION['csrf_token']
        ?? '';

    $postedToken =
        $_POST['csrf_token']
        ?? '';

    if (
        !is_string($sessionToken) ||
        !is_string($postedToken) ||
        $sessionToken === '' ||
        $postedToken === '' ||
        !hash_equals(
            $sessionToken,
            $postedToken
        )
    ) {
        http_response_code(403);

        exit(
            'Invalid request token.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId =
    (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    verifySettingsCsrf();

    $action =
        (string) (
            $_POST['action']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | UPDATE PROFILE
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_profile') {

        $fullName =
            trim(
                (string) (
                    $_POST['full_name']
                    ?? ''
                )
            );

        $email =
            trim(
                (string) (
                    $_POST['email']
                    ?? ''
                )
            );

        $phone =
            trim(
                (string) (
                    $_POST['phone']
                    ?? ''
                )
            );


        if ($fullName === '') {
            settingsRedirect(
                'name_required'
            );
        }


        if (
            $email !== '' &&
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            settingsRedirect(
                'invalid_email'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE EMAIL
        |--------------------------------------------------------------------------
        */

        if ($email !== '') {

            $stmt = mysqli_prepare(
                $conn,
                "
                SELECT user_id
                FROM users
                WHERE
                    email = ?
                    AND user_id <> ?
                LIMIT 1
                "
            );


            if (!$stmt) {
                settingsRedirect(
                    'error'
                );
            }


            mysqli_stmt_bind_param(
                $stmt,
                'si',
                $email,
                $userId
            );


            mysqli_stmt_execute(
                $stmt
            );


            $result =
                mysqli_stmt_get_result(
                    $stmt
                );


            $duplicate =
                mysqli_fetch_assoc(
                    $result
                );


            mysqli_stmt_close(
                $stmt
            );


            if ($duplicate) {
                settingsRedirect(
                    'email_exists'
                );
            }
        }


        $stmt = mysqli_prepare(
            $conn,
            "
            UPDATE users
            SET
                full_name = ?,
                email = ?,
                phone = ?
            WHERE user_id = ?
            "
        );


        if (!$stmt) {
            settingsRedirect(
                'error'
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            'sssi',
            $fullName,
            $email,
            $phone,
            $userId
        );


        $success =
            mysqli_stmt_execute(
                $stmt
            );


        mysqli_stmt_close(
            $stmt
        );


        if (!$success) {
            settingsRedirect(
                'error'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | KEEP SESSION DISPLAY NAME SYNCHRONIZED
        |--------------------------------------------------------------------------
        */

        $_SESSION['full_name'] =
            $fullName;


        settingsRedirect(
            'profile_updated'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHANGE PASSWORD
    |--------------------------------------------------------------------------
    */

    if ($action === 'change_password') {

        $currentPassword =
            (string) (
                $_POST[
                    'current_password'
                ]
                ?? ''
            );

        $newPassword =
            (string) (
                $_POST[
                    'new_password'
                ]
                ?? ''
            );

        $confirmPassword =
            (string) (
                $_POST[
                    'confirm_password'
                ]
                ?? ''
            );


        if (
            $currentPassword === '' ||
            $newPassword === '' ||
            $confirmPassword === ''
        ) {
            settingsRedirect(
                'password_fields_required'
            );
        }


        if (
            $newPassword !==
            $confirmPassword
        ) {
            settingsRedirect(
                'password_mismatch'
            );
        }


        if (
            strlen($newPassword) < 6
        ) {
            settingsRedirect(
                'password_short'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOAD CURRENT PASSWORD HASH
        |--------------------------------------------------------------------------
        */

        $stmt = mysqli_prepare(
            $conn,
            "
            SELECT password
            FROM users
            WHERE user_id = ?
            LIMIT 1
            "
        );


        if (!$stmt) {
            settingsRedirect(
                'error'
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $userId
        );


        mysqli_stmt_execute(
            $stmt
        );


        $result =
            mysqli_stmt_get_result(
                $stmt
            );


        $passwordRow =
            mysqli_fetch_assoc(
                $result
            );


        mysqli_stmt_close(
            $stmt
        );


        if (
            !$passwordRow ||
            empty(
                $passwordRow[
                    'password'
                ]
            )
        ) {
            settingsRedirect(
                'error'
            );
        }


        if (
            !password_verify(
                $currentPassword,
                $passwordRow[
                    'password'
                ]
            )
        ) {
            settingsRedirect(
                'current_password_wrong'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PREVENT REUSING CURRENT PASSWORD
        |--------------------------------------------------------------------------
        */

        if (
            password_verify(
                $newPassword,
                $passwordRow[
                    'password'
                ]
            )
        ) {
            settingsRedirect(
                'same_password'
            );
        }


        $newHash =
            password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );


        if ($newHash === false) {
            settingsRedirect(
                'error'
            );
        }


        $stmt = mysqli_prepare(
            $conn,
            "
            UPDATE users
            SET password = ?
            WHERE user_id = ?
            "
        );


        if (!$stmt) {
            settingsRedirect(
                'error'
            );
        }


        mysqli_stmt_bind_param(
            $stmt,
            'si',
            $newHash,
            $userId
        );


        $success =
            mysqli_stmt_execute(
                $stmt
            );


        mysqli_stmt_close(
            $stmt
        );


        if (!$success) {
            settingsRedirect(
                'error'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | REGENERATE SESSION AFTER SENSITIVE ACCOUNT CHANGE
        |--------------------------------------------------------------------------
        */

        session_regenerate_id(
            true
        );


        settingsRedirect(
            'password_updated'
        );
    }
}


/*
|--------------------------------------------------------------------------
| STATUS MESSAGE
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';

$status =
    trim(
        (string) (
            $_GET['status']
            ?? ''
        )
    );


switch ($status) {

    case 'profile_updated':

        $message =
            'Profile updated successfully.';

        $messageType =
            'success';

        break;


    case 'password_updated':

        $message =
            'Password changed successfully.';

        $messageType =
            'success';

        break;


    case 'name_required':

        $message =
            'Full name is required.';

        $messageType =
            'error';

        break;


    case 'invalid_email':

        $message =
            'Please enter a valid email address.';

        $messageType =
            'error';

        break;


    case 'email_exists':

        $message =
            'That email address is already used by another account.';

        $messageType =
            'error';

        break;


    case 'password_fields_required':

        $message =
            'Please complete all password fields.';

        $messageType =
            'error';

        break;


    case 'current_password_wrong':

        $message =
            'Current password is incorrect.';

        $messageType =
            'error';

        break;


    case 'password_mismatch':

        $message =
            'The new passwords do not match.';

        $messageType =
            'error';

        break;


    case 'password_short':

        $message =
            'The new password must contain at least 6 characters.';

        $messageType =
            'error';

        break;


    case 'same_password':

        $message =
            'The new password must be different from the current password.';

        $messageType =
            'error';

        break;


    case 'error':

        $message =
            'The requested account change could not be completed.';

        $messageType =
            'error';

        break;
}


/*
|--------------------------------------------------------------------------
| LOAD CURRENT USER PROFILE
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        user_id,
        full_name,
        username,
        email,
        phone,
        role,
        status
    FROM users
    WHERE user_id = ?
    LIMIT 1
    "
);


if (!$stmt) {

    http_response_code(500);

    exit(
        'Unable to load account information.'
    );
}


mysqli_stmt_bind_param(
    $stmt,
    'i',
    $userId
);


mysqli_stmt_execute(
    $stmt
);


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$user =
    mysqli_fetch_assoc(
        $result
    );


mysqli_stmt_close(
    $stmt
);


if (!$user) {

    $_SESSION = [];

    session_destroy();

    header(
        'Location: /grocery-shop/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| SHARED HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>


<link
    rel="stylesheet"
    href="../../assets/css/sidebar.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/topbar.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/dashboard-layout.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/module.css"
>


<style>

.settings-grid {
    display: grid;
    grid-template-columns:
        minmax(0, 1fr)
        minmax(0, 1fr);
    gap: 20px;
}


.settings-account-info {
    margin-bottom: 18px;
    padding: 12px 14px;
    border-radius: 8px;
    background: #f8fafc;
    color: #64748b;
    font-size: 13px;
}


.settings-account-info strong {
    color: #334155;
}


@media (max-width: 850px) {

    .settings-grid {
        grid-template-columns: 1fr;
    }
}

</style>


<div class="app-layout">


    <aside class="app-sidebar-slot">

        <?php
        require_once "../../includes/sidebar.php";
        ?>

    </aside>


    <div class="app-main-slot">


        <header class="app-topbar-slot">

            <?php
            require_once "../../includes/topbar.php";
            ?>

        </header>


        <main class="dashboard-main-content">


            <div
                class="dashboard-page"
                style="padding: 24px;"
            >


                <div class="page-header">

                    <div>

                        <h1>
                            Security Settings
                        </h1>

                        <p>
                            Manage your administrator profile
                            and account password.
                        </p>

                    </div>

                </div>


                <?php if ($message !== ''): ?>

                    <div
                        class="alert <?php
                            echo
                                $messageType ===
                                'success'
                                    ? 'alert-success'
                                    : 'alert-danger';
                        ?>"
                    >

                        <?php
                        echo htmlspecialchars(
                            $message,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <div class="settings-grid">


                    <!-- =============================================
                         PROFILE
                    ============================================== -->

                    <div class="card">


                        <div class="card-title">
                            Update Profile
                        </div>


                        <div class="settings-account-info">

                            Username:
                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $user[
                                        'username'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </strong>

                            <br>

                            Role:
                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $user[
                                        'role'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </strong>

                            <br>

                            Status:
                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $user[
                                        'status'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </strong>

                        </div>


                        <form method="POST">


                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php
                                    echo htmlspecialchars(
                                        $csrfToken,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="update_profile"
                            >


                            <div
                                class="form-group"
                                style="margin-bottom: 14px;"
                            >

                                <label>
                                    Full Name
                                </label>

                                <input
                                    type="text"
                                    name="full_name"
                                    maxlength="100"
                                    required
                                    value="<?php
                                        echo htmlspecialchars(
                                            $user[
                                                'full_name'
                                            ],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

                            </div>


                            <div
                                class="form-group"
                                style="margin-bottom: 14px;"
                            >

                                <label>
                                    Email Address
                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    maxlength="100"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $user[
                                                'email'
                                            ] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

                            </div>


                            <div
                                class="form-group"
                                style="margin-bottom: 18px;"
                            >

                                <label>
                                    Phone Number
                                </label>

                                <input
                                    type="text"
                                    name="phone"
                                    maxlength="20"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $user[
                                                'phone'
                                            ] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

                            </div>


                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Update Profile
                            </button>


                        </form>


                    </div>


                    <!-- =============================================
                         PASSWORD
                    ============================================== -->

                    <div class="card">


                        <div class="card-title">
                            Change Password
                        </div>


                        <form method="POST">


                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php
                                    echo htmlspecialchars(
                                        $csrfToken,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="change_password"
                            >


                            <div
                                class="form-group"
                                style="margin-bottom: 14px;"
                            >

                                <label>
                                    Current Password
                                </label>

                                <input
                                    type="password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    required
                                >

                            </div>


                            <div
                                class="form-group"
                                style="margin-bottom: 14px;"
                            >

                                <label>
                                    New Password
                                </label>

                                <input
                                    type="password"
                                    name="new_password"
                                    minlength="6"
                                    autocomplete="new-password"
                                    required
                                >

                            </div>


                            <div
                                class="form-group"
                                style="margin-bottom: 18px;"
                            >

                                <label>
                                    Confirm New Password
                                </label>

                                <input
                                    type="password"
                                    name="confirm_password"
                                    minlength="6"
                                    autocomplete="new-password"
                                    required
                                >

                            </div>


                            <button
                                type="submit"
                                class="btn btn-danger"
                            >
                                Change Password
                            </button>


                        </form>


                    </div>


                </div>


            </div>


        </main>


    </div>


</div>


<?php

require_once "../../includes/footer.php";

?>