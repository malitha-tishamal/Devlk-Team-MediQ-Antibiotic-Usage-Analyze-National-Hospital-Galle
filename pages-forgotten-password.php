<?php
require_once 'includes/db-conn.php';
session_start();
date_default_timezone_set('Asia/Colombo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Search in both tables
    $tables = ['admins', 'users'];
    $found = false;

    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            $found = true;
            $token = bin2hex(random_bytes(16));
            $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

            $update = $conn->prepare("UPDATE $table SET reset_token = ?, token_expiry = ? WHERE email = ?");
            $update->bind_param("sss", $token, $expiry, $email);
            $update->execute();

            // Normally you would send an email — here we just simulate it
            $resetLink = "https://mediq.42web.io/karapitiya?token=$token&type=$table";
            $_SESSION['success_message'] = "Password reset link: $resetLink (valid for 15 minutes).";
            header("Location: pages-forgotten-password.php");
            exit();
        }
    }

    if (!$found) {
        $_SESSION['error_message'] = "No account found with that email.";
        header("Location: pages-forgotten-password.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
</head>
<body>
<h2>Forgot Password</h2>
<?php
if (isset($_SESSION['error_message'])) {
    echo "<p style='color:red'>{$_SESSION['error_message']}</p>";
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    echo "<p style='color:green'>{$_SESSION['success_message']}</p>";
    unset($_SESSION['success_message']);
}
?>
<form method="POST">
    <label>Enter your email:</label><br>
    <input type="email" name="email" required><br><br>
    <button type="submit">Send Reset Link</button>
</form>
</body>
</html>
