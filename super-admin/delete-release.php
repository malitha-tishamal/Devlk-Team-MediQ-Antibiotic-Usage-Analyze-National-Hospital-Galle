<?php
session_start();
require_once '../includes/db-conn.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Delete logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && is_numeric($_POST['id'])) {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM releases WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['msg'] = "Release deleted successfully.";
    } else {
        $_SESSION['msg'] = "Error deleting release.";
    }

    $stmt->close();
} else {
    $_SESSION['msg'] = "Invalid request.";
}

$conn->close();
header("Location: pages-release-details.php"); // <-- replace with actual file name if different
exit();
