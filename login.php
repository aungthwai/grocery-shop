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
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

require_once "config/database.php";

$error = "";


/*
|--------------------------------------------------------------------------
| ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION["user_id"])) {

    header("Location: /grocery-shop/modules/dashboard/index.php");
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
    $_SESSION["login_csrf_token"] = bin2hex(random_bytes(32));
}

$loginCsrfToken = $_SESSION["login_csrf_token"];

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

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter your username and password.";

    } else {

        $sql = "SELECT user_id, full_name, username, email, password, role, status
        FROM users
        WHERE username = ? OR email = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param("ss", $username, $username);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                if ($user["status"] !== "Active") {

                    $error = "Your account is inactive.";

                } elseif (password_verify($password, $user["password"])) {

                    /*
                    |--------------------------------------------------------------------------
                    | PREVENT SESSION FIXATION
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);

                    $_SESSION["user_id"] = (int) $user["user_id"];
                    $_SESSION["full_name"] = $user["full_name"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["role"] = $user["role"];

                    unset($_SESSION["login_csrf_token"]);

                    header("Location: /grocery-shop/modules/dashboard/index.php");
                    exit;

                } else {

                    $error = "Invalid username or password.";

                }

            } else {

                $error = "Invalid username or password.";

            }

            $stmt->close();

        } else {

            $error = "Database query could not be prepared.";
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

</head>


<body>


    <!-- =====================================================
         LOGIN PAGE
         ===================================================== -->

    <main class="login-page">


        <!-- =================================================
             LEFT BLUE PANEL
             ================================================= -->

        <section class="brand-panel">


            <!-- Brand -->
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


            <!-- Features -->
            <div class="brand-features">

                <h2>
                    Everything you need
                </h2>


                <!-- Inventory -->
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


                <!-- Customer and Supplier -->
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


                <!-- Reports -->
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


        <!-- =================================================
             RIGHT LOGIN PANEL
             ================================================= -->

        <section class="login-panel">


            <div class="login-content">


                <!-- Welcome -->
                <div class="welcome-section">

                    <h2>
                        Welcome Back!
                    </h2>

                    <p>
                        Sign in to continue managing your grocery store.
                    </p>

                </div>


                <!-- Error Message -->
                <?php if ($error !== ""): ?>

                    <div class="login-error">

                        <?php echo htmlspecialchars($error); ?>

                    </div>

                <?php endif; ?>


                <!-- Login Form -->
                <form
                    action=""
                    method="POST"
                    class="login-form"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars(
                            $loginCsrfToken,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >


                    <!-- Email Address -->
                    <div class="form-group">

                        <label for="username">
                            Email Address
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


                    <!-- Password -->
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


                    <!-- Remember Me -->
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


                    <!-- Login Button -->
                    <button
                        type="submit"
                        class="login-button"
                    >
                        Login
                    </button>


                    <!-- Forgot Password -->
                    <div class="forgot-password">

    <a href="forgot_password.php">
        Forgot Password?
    </a>

</div>


                </form>


                <!-- Footer -->
                <footer class="login-footer">

                    <p>
                        &copy; 2026 GrocerEase. All rights reserved.
                    </p>

                </footer>


            </div>


        </section>


    </main>


</body>

</html>