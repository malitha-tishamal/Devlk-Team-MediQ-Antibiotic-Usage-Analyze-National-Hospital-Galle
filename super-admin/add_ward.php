<?php
session_start();
require_once "../includes/db-conn.php"; // Database connection

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $ward_name = trim($_POST['ward_name']);
    $description = trim($_POST['description']);

    // Check if fields are empty
    if (empty($ward_name) ) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'All fields are required!';
    } else {
        try {
            // Use prepared statement to insert data securely
            $sql = "INSERT INTO ward (ward_name, description) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $ward_name, $description);

            if ($stmt->execute()) {
                $_SESSION['status'] = 'success';
                $_SESSION['message'] = 'Ward added successfully!';
            } else {
                throw new Exception('Database error: ' . $stmt->error);
            }
        } catch (Exception $e) {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = $e->getMessage();
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
            $conn->close();
        }
    }

    // Redirect back to the form page
    header("Location: pages-add-new-ward.php");
    exit();
}
?>
