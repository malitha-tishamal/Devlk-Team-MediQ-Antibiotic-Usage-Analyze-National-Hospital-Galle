<?php
session_start();
require_once("includes/db-conn.php"); // Include your database connection

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the email from the form
    $email = $_POST['email'];

    // Validate the email
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Check if the email exists in the database
        $query = "SELECT id, name FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Email exists, generate token
            $user = $result->fetch_assoc();
            $userId = $user['id'];
            $token = bin2hex(random_bytes(50));  // Generate a random token

            // Insert the token into the password_reset table
            $expireTime = time() + 3600; // Token expires in 1 hour
            $insertQuery = "INSERT INTO password_reset (user_id, token, expire_time) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iss", $userId, $token, $expireTime);
            $stmt->execute();

            // Send password reset email
            $resetLink = "https://yourwebsite.com/reset-password.php?token=$token";
            $subject = "Password Reset Request";
            $message = "Hi " . $user['name'] . ",\n\nPlease click the following link to reset your password:\n$resetLink\n\nIf you did not request this, please ignore this email.";
            $headers = "From: no-reply@yourwebsite.com";

            if (mail($email, $subject, $message, $headers)) {
                // Redirect to confirmation page
                $_SESSION['message'] = "A password reset link has been sent to your email address.";
                header("Location: password_reset.php");
            } else {
                $_SESSION['message'] = "Failed to send the reset email. Please try again later.";
            }
        } else {
            $_SESSION['message'] = "Email address not found in our system.";
        }
    } else {
        $_SESSION['message'] = "Please enter a valid email address.";
    }
}
?>

