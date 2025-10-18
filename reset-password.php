<?php
session_start();
require_once 'includes/db-conn.php';
date_default_timezone_set('Asia/Colombo');

if (!isset($_GET['token'])) {
    die("Invalid request.");
}

$token = $_GET['token'];
$stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$reset = $result->fetch_assoc();

if (!$reset) {
    die("Invalid or expired link.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
    $email = $reset['email'];
    $user_type = $reset['user_type'];

    $update = $conn->prepare("UPDATE $user_type SET password = ? WHERE email = ?");
    $update->bind_param("ss", $new_password, $email);
    $update->execute();

    // Delete token
    $conn->query("DELETE FROM password_resets WHERE email = '$email'");

    $_SESSION['message'] = "Password updated successfully!";
    $_SESSION['status'] = "success";
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Reset Password</title>
  <?php include_once("includes/css-links-inc.php"); ?>
</head>
<body>
  <div class="container py-5">
    <div class="card mx-auto" style="max-width:400px;">
      <div class="card-body">
        <h4 class="text-center mb-3">Reset Password</h4>
        <form method="POST">
          <div class="mb-3">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
      </div>
    </div>
  </div>
  <?php include_once("includes/js-links-inc.php"); ?>
</body>
</html>
