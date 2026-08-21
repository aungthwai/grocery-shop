<?php

session_start();

require_once "config/database.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($full_name === "" || $username === "" || $email === "" || $password === "") {
        $error = "Please fill in all fields.";
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT user_id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        
        if ($check_stmt) {
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $error = "Username or Email already exists.";
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = "Admin"; // Defaulting to Admin for this setup, or could be 'Staff'
                $status = "Active";
                
                $insert_sql = "INSERT INTO users (full_name, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                
                if ($insert_stmt) {
                    $insert_stmt->bind_param("ssssss", $full_name, $username, $email, $hashed_password, $role, $status);
                    
                    if ($insert_stmt->execute()) {
                        $success = "Registration successful! You can now <a href='login.php'>Login</a>.";
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                    $insert_stmt->close();
                } else {
                    $error = "Database error: Could not prepare insert statement.";
                }
            }
            $check_stmt->close();
        } else {
            $error = "Database error: Could not prepare check statement.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrocerEase - Register</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

    <main class="login-page">

        <!-- LEFT BLUE PANEL -->
        <section class="brand-panel">
            <div class="brand-content">
                <h1 class="brand-name">GrocerEase</h1>
                <p class="brand-subtitle">Wholesale &amp; Retail Grocery<br>Management System</p>
            </div>
            
            <div class="brand-features">
                <h2>Join Us Today</h2>
                <div class="brand-feature">
                    <div class="feature-icon">🚀</div>
                    <div class="feature-text">
                        <h3>Easy Setup</h3>
                        <p>Get started with managing your store in minutes.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- RIGHT REGISTRATION PANEL -->
        <section class="login-panel">
            <div class="login-content">

                <!-- Welcome -->
                <div class="welcome-section">
                    <h2>Create an Account</h2>
                    <p>Register below to get started with GrocerEase.</p>
                </div>

                <!-- Messages -->
                <?php if ($error !== ""): ?>
                    <div class="login-error" style="color: red; margin-bottom: 15px; font-weight: bold;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success !== ""): ?>
                    <div class="login-success" style="color: green; margin-bottom: 15px; font-weight: bold;">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form action="register.php" method="POST" class="login-form">

                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" required>
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Choose a username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Create a password" required>
                    </div>

                    <button type="submit" class="login-button">Register</button>

                    <div class="forgot-password" style="margin-top: 15px; text-align: center;">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>

                </form>

                <footer class="login-footer">
                    <p>&copy; 2026 GrocerEase. All rights reserved.</p>
                </footer>

            </div>
        </section>

    </main>

</body>
</html>
