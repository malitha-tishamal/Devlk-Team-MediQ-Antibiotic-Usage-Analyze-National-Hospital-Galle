<?php
session_start();
require_once 'includes/db-conn.php';
date_default_timezone_set('Asia/Colombo');

// If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $_SESSION['message'] = "Please enter your email address.";
        $_SESSION['status'] = "danger";
        header("Location: fogot-password.php");
        exit();
    }

    // Check if email exists in admins or users
    $user = null;
    $user_type = null;

    // Check in admins
    $stmt = $conn->prepare("SELECT id, email FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $admin_result = $stmt->get_result();

    if ($admin_result->num_rows > 0) {
        $user = $admin_result->fetch_assoc();
        $user_type = "admins";
    } else {
        // Check in users
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user_result = $stmt->get_result();

        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            $user_type = "users";
        }
    }

    if (!$user) {
        $_SESSION['message'] = "No account found with that email.";
        $_SESSION['status'] = "danger";
        header("Location: fogot-password.php");
        exit();
    }

    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expires_at = date("Y-m-d H:i:s", strtotime("+30 minutes"));

    // Save to database
    $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, user_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $token, $expires_at, $user_type);
    $stmt->execute();

    // Send reset link
    $reset_link = "https://yourdomain.com/reset-password.php?token=" . $token;

    $subject = "Password Reset Request";
    $message = "
        Hi,<br><br>
        We received a request to reset your password.<br>
        Click the link below to reset it:<br><br>
        <a href='$reset_link'>$reset_link</a><br><br>
        This link will expire in 30 minutes.<br><br>
        If you didn't request this, please ignore this email.<br><br>
        Regards,<br>
        <b>MediQ Team</b>
    ";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: MediQ <no-reply@yourdomain.com>" . "\r\n";

    if (mail($email, $subject, $message, $headers)) {
        $_SESSION['message'] = "Password reset link sent! Check your email.";
        $_SESSION['status'] = "success";
    } else {
        $_SESSION['message'] = "Failed to send email. Please try again.";
        $_SESSION['status'] = "danger";
    }

    header("Location: fogot-password.php");
    exit();
}
?>
