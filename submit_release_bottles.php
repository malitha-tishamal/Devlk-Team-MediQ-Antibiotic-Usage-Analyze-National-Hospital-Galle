<?php
require_once "includes/db-conn.php";  // Include the database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $antibiotic_id = $_POST['antibiotic_id'];
    $release_bottles = $_POST['release_bottles'];

    // Validate input
    if (empty($antibiotic_id) || empty($release_bottles)) {
        echo "Please fill in all fields.";
        exit();
    }

    // Insert release bottles for the selected antibiotic
    $sql = "INSERT INTO release_bottles (antibiotic_id, quantity) VALUES (?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $antibiotic_id, $release_bottles);
        if ($stmt->execute()) {
            echo "Release bottle information successfully saved!";
            header("Location: success.php");  // Redirect to a success page
            exit();
        } else {
            echo "Error: Could not insert release bottle.";
        }
        $stmt->close();
    } else {
        echo "Error: " . $conn->error;
    }
    $conn->close();
}
?>
