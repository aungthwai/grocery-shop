<?php

session_start();

require_once "config/database.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if ($email === "") {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        /*
         * Find the active user using the email address.
         */
        $sql = "SELECT user_id, full_name, email, status
                FROM users
                WHERE email = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param("s", $email);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                if ($user["status"] !== "Active") {

                    $error = "This account is inactive.";

                } else {

                    /*
                     * Remove previous unused reset tokens
                     * belonging to this user.
                     */
                    $deleteSql = "DELETE FROM password_resets
                                  WHERE user_id = ?
                                  AND used_at IS NULL";

                    $deleteStmt = $conn->prepare($deleteSql);

                    if ($deleteStmt) {

                        $deleteStmt->bind_param(
                            "i",
                            $user["user_id"]
                        );

                        $deleteStmt->execute();

                        $deleteStmt->close();
                    }


                    /*
                     * Generate a secure random token.
                     */
                    $resetToken = bin2hex(
                        random_bytes(32)
                    );


                    /*
                     * Store only the SHA-256 hash
                     * of the token.
                     */
                    $tokenHash = hash(
                        "sha256",
                        $resetToken
                    );


                    /*
                     * Token expires after 15 minutes.
                     */
                    $expiresAt = date(
                        "Y-m-d H:i:s",
                        time() + (15 * 60)
                    );


                    /*
                     * Insert reset token into database.
                     */
                    $insertSql = "INSERT INTO password_resets
                                  (
                                      user_id,
                                      token_hash,
                                      expires_at
                                  )
                                  VALUES (?, ?, ?)";

                    $insertStmt = $conn->prepare(
                        $insertSql
                    );

                    if ($insertStmt) {

                        $insertStmt->bind_param(
                            "iss",
                            $user["user_id"],
                            $tokenHash,
                            $expiresAt
                        );

                        if ($insertStmt->execute()) {

                            /*
                             * Local XAMPP demonstration:
                             * We store the token in the session
                             * so the user can continue to the
                             * reset page without an email server.
                             */
                            $_SESSION["reset_token"] = $resetToken;

                            header(
                                "Location: reset_password.php"
                            );

                            exit;

                        } else {

                            $error =
                                "Could not create the password reset request.";
                        }

                        $insertStmt->close();

                    } else {

                        $error =
                            "Could not prepare the reset request.";
                    }
                }

            } else {

                /*
                 * We intentionally do not reveal whether
                 * an email exists.
                 */
                $message =
                    "If an account exists with that email address, " .
                    "a password reset request has been created.";
            }

            $stmt->close();

        } else {

            $error =
                "Database query could not be prepared.";
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

    <title>Forgot Password - GrocerEase</title>

    <link
        rel="stylesheet"
        href="assets/css/forgot_password.css"
    >

</head>


<body>


    <main class="forgot-page">


        <section class="forgot-card">


            <!-- Brand -->

            <div class="forgot-brand">

                <h1>
                    GrocerEase
                </h1>

                <p>
                    Wholesale &amp; Retail Grocery
                    Management System
                </p>

            </div>


            <!-- Heading -->

            <div class="forgot-heading">

                <h2>
                    Forgot Password?
                </h2>

                <p>
                    Enter your registered email address
                    to reset your password.
                </p>

            </div>


            <!-- Error -->

            <?php if ($error !== ""): ?>

                <div class="form-error">

                    <?php
                    echo htmlspecialchars($error);
                    ?>

                </div>

            <?php endif; ?>


            <!-- Success -->

            <?php if ($message !== ""): ?>

                <div class="form-success">

                    <?php
                    echo htmlspecialchars($message);
                    ?>

                </div>

            <?php endif; ?>


            <!-- Form -->

            <form
                method="POST"
                action=""
                class="forgot-form"
            >


                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email address"
                        autocomplete="email"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="reset-button"
                >
                    Continue
                </button>


            </form>


            <!-- Back to Login -->

            <div class="back-login">

                <a href="login.php">
                    ← Back to Login
                </a>

            </div>


        </section>


    </main>


</body>

</html>