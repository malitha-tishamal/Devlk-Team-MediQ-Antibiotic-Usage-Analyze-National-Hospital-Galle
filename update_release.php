<?php
session_start(); // Start session to store messages

// Include the database connection
require_once "includes/db-conn.php";

// Check if form data is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate form inputs
    $antibioticname = isset($_POST['antibiotic_name']) ? trim($_POST['antibiotic_name']) : null;
    $dosage = isset($_POST['dosage']) && trim($_POST['dosage']) !== '' ? trim($_POST['dosage']) : null;
    $itemCount = isset($_POST['item_count']) ? intval($_POST['item_count']) : null;
    $ward = isset($_POST['ward']) ? trim($_POST['ward']) : null; // New ward input
    $releaseTime = date('Y-m-d H:i:s'); // Current timestamp

    // Validate required fields
    if (empty($antibioticname) || empty($itemCount) || empty($ward)) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Missing required fields!";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    // Ensure that item count is a valid number
    if ($itemCount <= 0) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Invalid item count!";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    // Insert antibiotic release into the database along with the ward
    $query = "INSERT INTO releases (antibiotic_name, dosage, item_count, release_time, ward_name) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);

    // Check if the prepared statement was created successfully
    if ($stmt === false) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Failed to prepare SQL query!";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    // Bind parameters and execute the query
    $stmt->bind_param("ssiss", $antibioticname, $dosage, $itemCount, $releaseTime, $ward);

    if ($stmt->execute()) {
        $_SESSION['status'] = 'success';
        $_SESSION['message'] = "Antibiotic release inserted successfully!";
    } else {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error inserting antibiotic release: " . $stmt->error;
    }

    // Close the statement and database connection
    $stmt->close();
    $conn->close();

    // Redirect back to the same page
    header("Location: pages-release-antibiotic.php");
    exit();
}
?>
