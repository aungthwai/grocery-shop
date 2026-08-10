<?php

session_start();

require_once "config/database.php";

$error = "";
$success = "";


/*
 * Check whether a reset token exists
 * in the current session.
 */
if (!isset($_SESSION["reset_token"])) {

    header("Location: forgot_password.php");

    exit;
}


$resetToken = $_SESSION["reset_token"];


/*
 * Hash the token so it can be compared
 * with the database value.
 */
$tokenHash = hash(
    "sha256",
    $resetToken
);


/*
 * Find the reset request.
 */
$sql = "SELECT reset_id, user_id, expires_at, used_at
        FROM password_resets
        WHERE token_hash = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die("Database query could not be prepared.");
}


$stmt->bind_param(
    "s",
    $tokenHash
);


$stmt->execute();


$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    unset($_SESSION["reset_token"]);

    die("This password reset link is invalid.");

}


$reset = $result->fetch_assoc();


$stmt->close();


/*
 * Check whether the token was already used.
 */
if ($reset["used_at"] !== null) {

    unset($_SESSION["reset_token"]);

    die("This password reset request has already been used.");
}


/*
 * Check expiration.
 */
if (strtotime($reset["expires_at"]) < time()) {

    unset($_SESSION["reset_token"]);

    die("This password reset request has expired. Please request a new one.");
}


/*
 * Process the new password.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $newPassword =
        $_POST["new_password"] ?? "";

    $confirmPassword =
        $_POST["confirm_password"] ?? "";


    /*
     * Check empty fields.
     */
    if (
        $newPassword === "" ||
        $confirmPassword === ""
    ) {

        $error =
            "Please enter and confirm your new password.";

    }


    /*
     * Minimum password length.
     */
    elseif (strlen($newPassword) < 8) {

        $error =
            "Password must be at least 8 characters long.";

    }


    /*
     * Check whether passwords match.
     */
    elseif ($newPassword !== $confirmPassword) {

        $error =
            "The passwords do not match.";

    }


    else {

        /*
         * Create secure password hash.
         */
        $passwordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );


        /*
         * Update the user's password.
         */
        $updateSql =
            "UPDATE users
             SET password = ?
             WHERE user_id = ?
             LIMIT 1";

        $updateStmt =
            $conn->prepare($updateSql);


        if ($updateStmt) {

            $updateStmt->bind_param(
                "si",
                $passwordHash,
                $reset["user_id"]
            );


            if ($updateStmt->execute()) {


                /*
                 * Mark reset token as used.
                 */
                $usedAt =
                    date("Y-m-d H:i:s");


                $usedSql =
                    "UPDATE password_resets
                     SET used_at = ?
                     WHERE reset_id = ?
                     LIMIT 1";


                $usedStmt =
                    $conn->prepare($usedSql);


                if ($usedStmt) {

                    $usedStmt->bind_param(
                        "si",
                        $usedAt,
                        $reset["reset_id"]
                    );

                    $usedStmt->execute();

                    $usedStmt->close();
                }


                /*
                 * Remove reset token from session.
                 */
                unset($_SESSION["reset_token"]);


                $success =
                    "Your password has been changed successfully.";


            } else {

                $error =
                    "Could not update your password.";
            }


            $updateStmt->close();


        } else {

            $error =
                "Could not prepare the password update.";
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

    <title>Reset Password - GrocerEase</title>

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


            <?php if ($success === ""): ?>


                <!-- Heading -->

                <div class="forgot-heading">

                    <h2>
                        Reset Password
                    </h2>

                    <p>
                        Enter your new password below.
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


                <!-- Reset Form -->

                <form
                    method="POST"
                    action=""
                    class="forgot-form"
                >


                    <!-- New Password -->

                    <div class="form-group">

                        <label for="new_password">
                            New Password
                        </label>

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            placeholder="Enter new password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <!-- Confirm Password -->

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm new password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="reset-button"
                    >
                        Reset Password
                    </button>


                </form>


            <?php else: ?>


                <!-- Success -->

                <div class="form-success">

                    <?php
                    echo htmlspecialchars($success);
                    ?>

                </div>


                <div class="back-login">

                    <a href="login.php">
                        Return to Login
                    </a>

                </div>


            <?php endif; ?>


        </section>


    </main>


</body>

</html>