<?php
session_start();
require_once 'includes/db-conn.php'; // Ensure database connection

if (isset($_POST['submit'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // First, check if the user is an admin
    $sql_admin = "SELECT * FROM admins WHERE email = ?";
    if ($stmt = $conn->prepare($sql_admin)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            // Check if the account is activated for admin
            if ($admin['status'] == 'approved') {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['success_message'] = "Welcome Admin!";
                header("Location: super-admin/index.php");
                exit(); // Stop further execution after redirect
            } else {
                // Admin account is not approved
                $_SESSION['error_message'] = "Your account has not been approved yet.";
                header("Location: index.php"); // Redirect back to login page with error
                exit();
            }
        }
    }

    // If not an admin, check in the users table
    $sql_user = "SELECT * FROM users WHERE email = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Check if the user account is activated
            if ($user['status'] == 'approved') {
                $_SESSION['user_id'] = $user['id']; // Store user session
                $_SESSION['success_message'] = "Welcome back! You're logged in.";
                header("Location: pages-release-antibiotic.php"); // Redirect to user profile
                exit(); // Stop further execution after redirect
            } else {
                // User account is not approved
                $_SESSION['error_message'] = "Your account has not been approved yet.";
                header("Location: index.php"); // Redirect back to login page with error
                exit();
            }
        } else {
            // Incorrect login details
            $_SESSION['error_message'] = "Invalid email or password.";
            header("Location: index.php"); // Redirect back to login page with error
            exit();
        }
    }
}
?>
