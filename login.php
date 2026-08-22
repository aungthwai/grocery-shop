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

require_once "config/database.php";

$error = "";

$success =
    isset($_GET["registered"]) &&
    $_GET["registered"] === "1"
        ? "Account created successfully. You can now log in."
        : "";


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
| LOGIN CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["login_csrf_token"]) ||
    !is_string($_SESSION["login_csrf_token"])
) {
    $_SESSION["login_csrf_token"] =
        bin2hex(random_bytes(32));
}

$loginCsrfToken =
    $_SESSION["login_csrf_token"];


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken =
        (string) ($_POST["csrf_token"] ?? "");

    if (
        $submittedToken === "" ||
        !hash_equals(
            $loginCsrfToken,
            $submittedToken
        )
    ) {

        $error =
            "Your login form expired. Please refresh the page and try again.";

    } else {

        $username =
            trim((string) ($_POST["username"] ?? ""));

        $password =
            (string) ($_POST["password"] ?? "");


        if (
            $username === "" ||
            $password === ""
        ) {

            $error =
                "Please enter your username and password.";

        } else {

            $sql = "
                SELECT
                    user_id,
                    full_name,
                    username,
                    email,
                    password,
                    role,
                    status
                FROM users
                WHERE username = ?
                   OR email = ?
                LIMIT 1
            ";

            $stmt =
                $conn->prepare($sql);

            if ($stmt) {

                $stmt->bind_param(
                    "ss",
                    $username,
                    $username
                );

                $stmt->execute();

                $result =
                    $stmt->get_result();

                if ($result->num_rows === 1) {

                    $user =
                        $result->fetch_assoc();

                    if (
                        $user["status"] !== "Active"
                    ) {

                        $error =
                            "Your account is inactive.";

                    } elseif (
                        password_verify(
                            $password,
                            $user["password"]
                        )
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | PREVENT SESSION FIXATION
                        |--------------------------------------------------------------------------
                        */

                        session_regenerate_id(true);

                        $_SESSION["user_id"] =
                            (int) $user["user_id"];

                        $_SESSION["full_name"] =
                            $user["full_name"];

                        $_SESSION["username"] =
                            $user["username"];

                        $_SESSION["role"] =
                            $user["role"];

                        unset(
                            $_SESSION[
                                "login_csrf_token"
                            ]
                        );

                        header(
                            "Location: /grocery-shop/modules/dashboard/index.php"
                        );

                        exit;

                    } else {

                        $error =
                            "Invalid username or password.";
                    }

                } else {

                    $error =
                        "Invalid username or password.";
                }

                $stmt->close();

            } else {

                $error =
                    "Database query could not be prepared.";
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

    <title>GrocerEase - Login</title>

    <link
        rel="stylesheet"
        href="assets/css/login.css"
    >

    <style>

        .login-success {
            margin-bottom: 18px;
            padding: 13px 15px;

            border: 1px solid #bbf7d0;
            border-radius: 8px;

            background: #f0fdf4;
            color: #166534;

            font-size: 14px;
            line-height: 1.5;
        }

        .create-account-link {
            margin-top: 18px;

            text-align: center;

            color: #64748b;

            font-size: 14px;
        }

        .create-account-link a {
            color: #2563eb;

            font-weight: 600;

            text-decoration: none;
        }

        .create-account-link a:hover {
            text-decoration: underline;
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
                Everything you need
            </h2>


            <div class="brand-feature">

                <div class="feature-icon">
                    📦
                </div>

                <div class="feature-text">

                    <h3>
                        Inventory Management
                    </h3>

                    <p>
                        Track products and stock levels instantly.
                    </p>

                </div>

            </div>


            <div class="brand-feature">

                <div class="feature-icon">
                    👥
                </div>

                <div class="feature-text">

                    <h3>
                        Customer &amp; Supplier
                    </h3>

                    <p>
                        Manage wholesale and retail customers easily.
                    </p>

                </div>

            </div>


            <div class="brand-feature">

                <div class="feature-icon">
                    📊
                </div>

                <div class="feature-text">

                    <h3>
                        Reports &amp; Analytics
                    </h3>

                    <p>
                        Monitor sales, profit and inventory reports.
                    </p>

                </div>

            </div>

        </div>


    </section>


    <section class="login-panel">


        <div class="login-content">


            <div class="welcome-section">

                <h2>
                    Welcome Back!
                </h2>

                <p>
                    Sign in to continue managing your grocery store.
                </p>

            </div>


            <?php if ($success !== ""): ?>

                <div class="login-success">

                    <?php
                    echo htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

            <?php endif; ?>


            <?php if ($error !== ""): ?>

                <div class="login-error">

                    <?php
                    echo htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

            <?php endif; ?>


            <form
                action=""
                method="POST"
                class="login-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php
                    echo htmlspecialchars(
                        $loginCsrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>"
                >


                <div class="form-group">

                    <label for="username">
                        Username or Email
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter username or email"
                        autocomplete="username"
                        required
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
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <div class="login-options">

                    <label class="remember-me">

                        <input
                            type="checkbox"
                            name="remember"
                            id="remember"
                        >

                        <span>
                            Remember Me
                        </span>

                    </label>

                </div>


                <button
                    type="submit"
                    class="login-button"
                >
                    Login
                </button>


                <div class="forgot-password">

                    <a href="forgot_password.php">
                        Forgot Password?
                    </a>

                </div>


                <div class="create-account-link">

                    Don't have an account?

                    <a href="register.php">
                        Create Account
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