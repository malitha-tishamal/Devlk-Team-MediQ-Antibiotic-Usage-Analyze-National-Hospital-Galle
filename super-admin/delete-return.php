<?php
session_start();
require_once '../includes/db-conn.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && is_numeric($_POST['id'])) {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM returns WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['msg'] = "Return record deleted successfully.";
    } else {
        $_SESSION['msg'] = "Failed to delete record.";
    }

    $stmt->close();
} else {
    $_SESSION['msg'] = "Invalid request.";
}

$conn->close();
header("Location: pages-return-details.php"); // replace with actual page filename if different
exit();
