<?php

/*
|--------------------------------------------------------------------------
| SECURE SESSION SETTINGS
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {

    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' =>
            !empty($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once "config/database.php";


/*
|--------------------------------------------------------------------------
| ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION["user_id"])) {

    header(
        "Location: /grocery-shop/modules/dashboard/index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| REGISTRATION CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["register_csrf_token"]) ||
    !is_string($_SESSION["register_csrf_token"])
) {
    $_SESSION["register_csrf_token"] =
        bin2hex(random_bytes(32));
}

$registerCsrfToken =
    $_SESSION["register_csrf_token"];


/*
|--------------------------------------------------------------------------
| FORM STATE
|--------------------------------------------------------------------------
*/

$errors = [];

$fullName = "";
$username = "";
$email = "";
$phone = "";


/*
|--------------------------------------------------------------------------
| PROCESS REGISTRATION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken =
        (string) ($_POST["csrf_token"] ?? "");

    if (
        $submittedToken === "" ||
        !hash_equals(
            $registerCsrfToken,
            $submittedToken
        )
    ) {

        $errors[] =
            "Your registration form expired. Please refresh the page and try again.";

    } else {

        $fullName =
            trim((string) ($_POST["full_name"] ?? ""));

        $username =
            trim((string) ($_POST["username"] ?? ""));

        $email =
            trim((string) ($_POST["email"] ?? ""));

        $phone =
            trim((string) ($_POST["phone"] ?? ""));

        $password =
            (string) ($_POST["password"] ?? "");

        $confirmPassword =
            (string) ($_POST["confirm_password"] ?? "");


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($fullName === "") {
            $errors[] = "Full name is required.";
        } elseif (strlen($fullName) > 100) {
            $errors[] =
                "Full name cannot exceed 100 characters.";
        }


        if ($username === "") {

            $errors[] = "Username is required.";

        } elseif (
            strlen($username) < 3 ||
            strlen($username) > 50
        ) {

            $errors[] =
                "Username must be between 3 and 50 characters.";

        } elseif (
            !preg_match(
                '/^[A-Za-z0-9._-]+$/',
                $username
            )
        ) {

            $errors[] =
                "Username may contain only letters, numbers, dots, underscores and hyphens.";
        }


        if ($email === "") {

            $errors[] = "Email address is required.";

        } elseif (
            strlen($email) > 100 ||
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $errors[] =
                "Please enter a valid email address.";
        }


        if (
            $phone !== "" &&
            strlen($phone) > 20
        ) {

            $errors[] =
                "Phone number cannot exceed 20 characters.";
        }


        if (strlen($password) < 8) {

            $errors[] =
                "Password must contain at least 8 characters.";
        }


        if ($password !== $confirmPassword) {

            $errors[] =
                "Password and confirmation password do not match.";
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE USERNAME / EMAIL
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $duplicateSql = "
                SELECT
                    user_id,
                    username,
                    email
                FROM users
                WHERE username = ?
                   OR email = ?
                LIMIT 1
            ";

            $duplicateStmt =
                $conn->prepare($duplicateSql);

            if (!$duplicateStmt) {

                $errors[] =
                    "Registration could not be processed.";

            } else {

                $duplicateStmt->bind_param(
                    "ss",
                    $username,
                    $email
                );

                $duplicateStmt->execute();

                $duplicateResult =
                    $duplicateStmt->get_result();

                if ($duplicateResult->num_rows > 0) {

                    $existingUser =
                        $duplicateResult->fetch_assoc();

                    if (
                        strcasecmp(
                            (string) $existingUser["username"],
                            $username
                        ) === 0
                    ) {
                        $errors[] =
                            "That username is already registered.";
                    }

                    if (
                        isset($existingUser["email"]) &&
                        strcasecmp(
                            (string) $existingUser["email"],
                            $email
                        ) === 0
                    ) {
                        $errors[] =
                            "That email address is already registered.";
                    }
                }

                $duplicateStmt->close();
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE USER
        |--------------------------------------------------------------------------
        | Public registration is deliberately restricted to Staff.
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            if ($passwordHash === false) {

                $errors[] =
                    "Password could not be secured.";

            } else {

                $role = "Staff";
                $status = "Active";

                $insertSql = "
                    INSERT INTO users
                    (
                        full_name,
                        username,
                        email,
                        password,
                        role,
                        phone,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";

                $insertStmt =
                    $conn->prepare($insertSql);

                if (!$insertStmt) {

                    $errors[] =
                        "Registration could not be completed.";

                } else {

                    $phoneValue =
                        $phone === "" ? null : $phone;

                    $insertStmt->bind_param(
                        "sssssss",
                        $fullName,
                        $username,
                        $email,
                        $passwordHash,
                        $role,
                        $phoneValue,
                        $status
                    );

                    if ($insertStmt->execute()) {

                        $insertStmt->close();

                        unset(
                            $_SESSION[
                                "register_csrf_token"
                            ]
                        );

                        header(
                            "Location: /grocery-shop/login.php?registered=1"
                        );

                        exit;

                    } else {

                        $errors[] =
                            "Registration could not be completed.";

                        $insertStmt->close();
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>GrocerEase - Create Account</title>

    <link
        rel="stylesheet"
        href="assets/css/login.css"
    >

    <style>

        .register-content {
            width: 100%;
            max-width: 560px;
        }

        .register-form {
            width: 100%;
        }

        .register-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 16px 18px;
        }

        .register-grid .form-group {
            margin: 0;
        }

        .register-full {
            grid-column: 1 / -1;
        }

        .register-errors {
            margin-bottom: 18px;
            padding: 13px 15px;

            border: 1px solid #fecaca;
            border-radius: 8px;

            background: #fef2f2;
            color: #991b1b;

            font-size: 14px;
            line-height: 1.5;
        }

        .register-errors ul {
            margin: 0;
            padding-left: 20px;
        }

        .register-note {
            margin: 15px 0;

            color: #64748b;

            font-size: 12px;
            line-height: 1.5;

            text-align: center;
        }

        .login-account-link {
            margin-top: 18px;

            text-align: center;

            color: #64748b;

            font-size: 14px;
        }

        .login-account-link a {
            color: #2563eb;

            font-weight: 600;

            text-decoration: none;
        }

        .login-account-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 760px) {

            .register-grid {
                grid-template-columns: 1fr;
            }

            .register-full {
                grid-column: auto;
            }
        }

    </style>

</head>


<body>


<main class="login-page">


    <section class="brand-panel">


        <div class="brand-content">

            <h1 class="brand-name">
                GrocerEase
            </h1>

            <p class="brand-subtitle">
                Wholesale &amp; Retail Grocery
                <br>
                Management System
            </p>

        </div>


        <div class="brand-features">

            <h2>
                Join GrocerEase
            </h2>


            <div class="brand-feature">

                <div class="feature-icon">
                    📦
                </div>

                <div class="feature-text">

                    <h3>
                        Inventory Awareness
                    </h3>

                    <p>
                        Stay informed about products and stock.
                    </p>

                </div>

            </div>


            <div class="brand-feature">

                <div class="feature-icon">
                    🛒
                </div>

                <div class="feature-text">

                    <h3>
                        Record Sales
                    </h3>

                    <p>
                        Process grocery sales quickly and securely.
                    </p>

                </div>

            </div>


            <div class="brand-feature">

                <div class="feature-icon">
                    🔐
                </div>

                <div class="feature-text">

                    <h3>
                        Secure Access
                    </h3>

                    <p>
                        New registrations receive Staff access.
                    </p>

                </div>

            </div>

        </div>


    </section>


    <section class="login-panel">


        <div class="login-content register-content">


            <div class="welcome-section">

                <h2>
                    Create Account
                </h2>

                <p>
                    Register for a GrocerEase Staff account.
                </p>

            </div>


            <?php if (!empty($errors)): ?>

                <div class="register-errors">

                    <ul>

                        <?php foreach ($errors as $error): ?>

                            <li>
                                <?php
                                echo htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>


            <form
                action=""
                method="POST"
                class="login-form register-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php
                    echo htmlspecialchars(
                        $registerCsrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>"
                >


                <div class="register-grid">


                    <div class="form-group register-full">

                        <label for="full_name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            maxlength="100"
                            value="<?php
                            echo htmlspecialchars(
                                $fullName,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            placeholder="Enter your full name"
                            autocomplete="name"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            maxlength="50"
                            value="<?php
                            echo htmlspecialchars(
                                $username,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            placeholder="Choose a username"
                            autocomplete="username"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="100"
                            value="<?php
                            echo htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            placeholder="Enter your email"
                            autocomplete="email"
                            required
                        >

                    </div>


                    <div class="form-group register-full">

                        <label for="phone">
                            Phone Number
                        </label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            maxlength="20"
                            value="<?php
                            echo htmlspecialchars(
                                $phone,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            placeholder="Optional"
                            autocomplete="tel"
                        >

                    </div>


                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            placeholder="Minimum 8 characters"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            placeholder="Re-enter password"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                </div>


                <p class="register-note">
                    New accounts are created with
                    Staff/Cashier access.
                </p>


                <button
                    type="submit"
                    class="login-button"
                >
                    Create Account
                </button>


                <div class="login-account-link">

                    Already have an account?

                    <a href="login.php">
                        Login
                    </a>

                </div>


            </form>


            <footer class="login-footer">

                <p>
                    &copy; 2026 GrocerEase.
                    All rights reserved.
                </p>

            </footer>


        </div>


    </section>


</main>


</body>

</html>