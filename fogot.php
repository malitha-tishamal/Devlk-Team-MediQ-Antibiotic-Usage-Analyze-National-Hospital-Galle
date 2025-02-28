<?php
session_start();
require_once("includes/db-conn.php"); // Database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']); // Remove extra spaces

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Check if the email exists in 'users' table
        $queryUser = "SELECT id, name FROM users WHERE email = ?";
        $stmt = $conn->prepare($queryUser);

        if (!$stmt) {
            $_SESSION['message'] = "Database error: Unable to prepare statement.";
            $_SESSION['status'] = "error";
            header("Location: pages-password_reset.php");
            exit();
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultUser = $stmt->get_result();

        // Check if email exists in 'admins' table
        $queryAdmin = "SELECT id, name FROM admins WHERE email = ?";
        $stmtAdmin = $conn->prepare($queryAdmin);

        if (!$stmtAdmin) {
            $_SESSION['message'] = "Database error: Unable to prepare statement.";
            $_SESSION['status'] = "error";
            header("Location: pages-password_reset.php");
            exit();
        }

        $stmtAdmin->bind_param("s", $email);
        $stmtAdmin->execute();
        $resultAdmin = $stmtAdmin->get_result();

        // Check if user exists in either table
        if ($resultUser->num_rows > 0) {
            $user = $resultUser->fetch_assoc();
            $userId = $user['id'];
            $role = 'user';
        } elseif ($resultAdmin->num_rows > 0) {
            $user = $resultAdmin->fetch_assoc();
            $userId = $user['id'];
            $role = 'admin';
        } else {
            $_SESSION['message'] = "Email not found in our system.";
            $_SESSION['status'] = "error";
            header("Location: pages-forgotten-password.php");
            exit();
        }

        // **Clear old expired tokens**
        $deleteExpiredTokens = "DELETE FROM password_reset WHERE expire_time < ?";
        $stmtDelete = $conn->prepare($deleteExpiredTokens);
        $currentTime = time();
        $stmtDelete->bind_param("i", $currentTime);
        $stmtDelete->execute();

        // **Check if the user already requested a reset**
        $checkTokenQuery = "SELECT token FROM password_reset WHERE user_id = ? AND role = ?";
        $stmtCheckToken = $conn->prepare($checkTokenQuery);
        $stmtCheckToken->bind_param("is", $userId, $role);
        $stmtCheckToken->execute();
        $resultToken = $stmtCheckToken->get_result();
        $existingToken = $resultToken->fetch_assoc();

        if ($existingToken) {
            $token = $existingToken['token']; // Use existing token
        } else {
            $token = bin2hex(random_bytes(50)); // Generate a new token
            $expireTime = time() + 3600; // 1-hour expiry

            // Insert the token into the database
            $insertQuery = "INSERT INTO password_reset (user_id, token, expire_time, role) VALUES (?, ?, ?, ?)";
            $stmtInsert = $conn->prepare($insertQuery);
            $stmtInsert->bind_param("isis", $userId, $token, $expireTime, $role);
            if (!$stmtInsert->execute()) {
                $_SESSION['message'] = "Error saving reset token.";
                $_SESSION['status'] = "error";
                header("Location: pages-password_reset.php");
                exit();
            }
        }

        // **Send Email using PHP Mail**
        $resetLink = "https://yourwebsite.com/pages-password_reset.php?token=$token";
        $subject = "Password Reset Request";
        $message = "Hi " . htmlspecialchars($user['name']) . ",\n\n"
                 . "Click the link below to reset your password:\n$resetLink\n\n"
                 . "If you didn't request this, please ignore this email.";

        $headers = "From: no-reply@yourwebsite.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        if (function_exists('mail') && mail($email, $subject, $message, $headers)) {
            $_SESSION['message'] = "A password reset link has been sent to your email.";
            $_SESSION['status'] = "success";
        } else {
            $_SESSION['message'] = "Failed to send email. Please try again later.";
            $_SESSION['status'] = "error";
        }

        header("Location: pages-password_reset.php");
        exit();
    } else {
        $_SESSION['message'] = "Please enter a valid email address.";
        $_SESSION['status'] = "error";
        header("Location: pages-forgotten-password.php");
        exit();
    }
}
?>
