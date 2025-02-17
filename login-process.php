<?php
session_start();
require_once 'includes/db-conn.php'; // Ensure database connection

if (isset($_POST['submit'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Function to check user credentials
    function checkUser($conn, $table, $email) {
        $sql = "SELECT * FROM $table WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        }
        return null;
    }

    // Check in admins table
    $admin = checkUser($conn, 'admins', $email);
    if ($admin && password_verify($password, $admin['password'])) {
        if ($admin['status'] == 'approved') {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['success_message'] = "Welcome Admin!";
            header("Location: admin/index.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Your admin account has not been approved yet.";
            header("Location: index.php");
            exit();
        }
    }

    // Check in teachers table
    $teacher = checkUser($conn, 'teachers', $email);
    if ($teacher && password_verify($password, $teacher['password'])) {
        if ($teacher['status'] == 'approved') {
            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['success_message'] = "Welcome Teacher!";
            header("Location: teachers/index.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Your teacher account has not been approved yet.";
            header("Location: index.php");
            exit();
        }
    }

    // Check in users table
    $user = checkUser($conn, 'users', $email);
    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] == 'approved') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['success_message'] = "Welcome back! You're logged in.";
            header("Location: pages-home.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Your user account has not been approved yet.";
            header("Location: index.php");
            exit();
        }
    }

    // If no match found
    $_SESSION['error_message'] = "Invalid email or password.";
    header("Location: index.php");
    exit();
}
?>
