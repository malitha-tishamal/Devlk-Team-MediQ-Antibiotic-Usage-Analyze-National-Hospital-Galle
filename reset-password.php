<?php
require_once 'includes/db-conn.php';
session_start();
date_default_timezone_set('Asia/Colombo');

if (isset($_GET['token']) && isset($_GET['type'])) {
    $token = $_GET['token'];
    $type = $_GET['type'];

    // Verify token
    $stmt = $conn->prepare("SELECT id FROM $type WHERE reset_token = ? AND token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo "Invalid or expired token.";
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_pass = password_hash($_POST['password'], PASSWORD_BCRYPT);

        $update = $conn->prepare("UPDATE $type SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?");
        $update->bind_param("si", $new_pass, $user['id']);
        $update->execute();

        $_SESSION['success_message'] = "Password successfully reset. Please log in.";
        header("Location: index.php");
        exit();
    }
} else {
    echo "Invalid access.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
<h2>Reset Your Password</h2>
<form method="POST">
    <label>New Password:</label><br>
    <input type="password" name="password" required><br><br>
    <button type="submit">Reset Password</button>
</form>
</body>
</html>
