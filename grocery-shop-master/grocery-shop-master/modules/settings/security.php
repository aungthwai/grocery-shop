<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
require_once "../../config/database.php";
$basePath = "/grocery-shop";
$page_title = "Settings - Security";
$msg = "";

// Change Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $currentPwd = $_POST['current_password'];
    $newPwd     = $_POST['new_password'];
    $confirmPwd = $_POST['confirm_password'];
    
    $uid = $_SESSION['user_id'];
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE user_id=$uid"));
    
    if (!password_verify($currentPwd, $user['password'])) {
        $msg = "<div class='alert alert-danger'>❌ Current password is incorrect.</div>";
    } elseif ($newPwd !== $confirmPwd) {
        $msg = "<div class='alert alert-danger'>❌ New passwords do not match.</div>";
    } elseif (strlen($newPwd) < 6) {
        $msg = "<div class='alert alert-warning'>⚠️ Password must be at least 6 characters.</div>";
    } else {
        $hash = password_hash($newPwd, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password='$hash' WHERE user_id=$uid");
        $msg = "<div class='alert alert-success'>✅ Password changed successfully!</div>";
    }
}

// Update Profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $uid       = $_SESSION['user_id'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $phone     = mysqli_real_escape_string($conn, $_POST['phone']);
    mysqli_query($conn, "UPDATE users SET full_name='$full_name', email='$email', phone='$phone' WHERE user_id=$uid");
    $_SESSION['full_name'] = $full_name;
    $msg = "<div class='alert alert-success'>✅ Profile updated successfully!</div>";
}

$uid = $_SESSION['user_id'];
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE user_id=$uid"));

require_once "../../includes/header.php";
?>
<div class="app-layout">
  <aside class="app-sidebar-slot"><?php require_once "../../includes/sidebar.php"; ?></aside>
  <div class="app-main-slot">
    <header class="app-topbar-slot"><?php require_once "../../includes/topbar.php"; ?></header>
    <main class="dashboard-main-content">
      <div class="dashboard-page" style="padding:24px;">

        <div class="page-header">
          <div><h1>⚙️ Settings - Security</h1><p>Manage your profile and account security.</p></div>
        </div>

        <?php echo $msg; ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
          <!-- Profile Card -->
          <div class="card">
            <div class="card-title">Update Profile</div>
            <form method="POST">
              <div class="form-group" style="margin-bottom:12px;">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
              </div>
              <div class="form-group" style="margin-bottom:12px;">
                <label>Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']??''); ?>">
              </div>
              <div class="form-group" style="margin-bottom:16px;">
                <label>Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']??''); ?>">
              </div>
              <div class="form-group" style="margin-bottom:12px;">
                <label>Role</label>
                <input type="text" value="<?php echo $user['role']; ?>" disabled style="background:#f8fafc; color:#94a3b8;">
              </div>
              <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
          </div>

          <!-- Change Password Card -->
          <div class="card">
            <div class="card-title">Change Password</div>
            <form method="POST">
              <div class="form-group" style="margin-bottom:12px;">
                <label>Current Password</label>
                <input type="password" name="current_password" required placeholder="Enter current password">
              </div>
              <div class="form-group" style="margin-bottom:12px;">
                <label>New Password</label>
                <input type="password" name="new_password" required placeholder="Min. 6 characters">
              </div>
              <div class="form-group" style="margin-bottom:16px;">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required placeholder="Repeat new password">
              </div>
              <button type="submit" name="change_password" class="btn btn-danger">Change Password</button>
            </form>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>
<?php require_once "../../includes/footer.php"; ?>
