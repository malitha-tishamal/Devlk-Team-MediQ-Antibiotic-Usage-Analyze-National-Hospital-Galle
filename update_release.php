<?php
session_start(); // Start session to store messages

// Include the database connection
require_once "includes/db-conn.php";

// Check if form data is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data and sanitize inputs
    $antibioticname = isset($_POST['antibiotic_name']) ? trim($_POST['antibiotic_name']) : null;
    $dosage = isset($_POST['dosage']) && trim($_POST['dosage']) !== '' ? trim($_POST['dosage']) : null;
    $itemCount = isset($_POST['item_count']) ? intval($_POST['item_count']) : null;
    $releaseTime = date('Y-m-d H:i:s'); // Current timestamp

    // Validate required inputs
    if (empty($antibioticname) || empty($itemCount)) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Missing required fields!";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    // Insert new antibiotic release (No duplicate check)
    $query = "INSERT INTO releases (antibiotic_name, dosage, item_count, release_time) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssis", $antibioticname, $dosage, $itemCount, $releaseTime);

    if ($stmt->execute()) {
        $_SESSION['status'] = 'success';
        $_SESSION['message'] = "Antibiotic release inserted successfully!";
    } else {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error inserting antibiotic release: " . $stmt->error;
    }

    // Close connections
    $stmt->close();
    $conn->close();

    // Redirect back to the same page
    header("Location: pages-release-antibiotic.php");
    exit();
}
?>
