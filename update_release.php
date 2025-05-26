<?php
session_start();
date_default_timezone_set('Asia/Colombo');
require_once "includes/db-conn.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;

    // Step 1: Fetch system_name from users table
    $systemName = null;
    if ($user_id) {
        $stmt = $conn->prepare("SELECT system_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($systemName);
        $stmt->fetch();
        $stmt->close();
    }

    // Step 2: Collect form data
    $antibioticname = trim($_POST['antibiotic_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $itemCount = intval($_POST['item_count'] ?? 0);
    $ward = trim($_POST['ward'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $ant_type = trim($_POST['ant_type'] ?? '');
    $book_number = trim($_POST['book_number_select'] ?? '');
    $page_number = trim($_POST['page_number_manual'] ?? '');

    if (isset($_POST['datetime_option']) && $_POST['datetime_option'] === 'manual') {
        $releaseTime = trim($_POST['manual_datetime'] ?? '');
    } else {
        $releaseTime = date('Y-m-d H:i:s');
    }

    // Validation
    if (empty($antibioticname) || empty($itemCount) || empty($ward) || empty($type) || empty($ant_type) || empty($releaseTime)) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Missing required fields!";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    if ($itemCount <= 0) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Invalid item count!";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    // Step 3: Get stv_number using antibiotic name and dosage
    $stmt = $conn->prepare("SELECT stv_number FROM dosages WHERE antibiotic_id = (SELECT id FROM antibiotics WHERE name = ?) AND dosage = ?");
    $stmt->bind_param("ss", $antibioticname, $dosage);
    $stmt->execute();
    $stmt->bind_result($stv_number);
    $stmt->fetch();
    $stmt->close();

    if (!$stv_number) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Matching stock not found for this antibiotic & dosage.";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    // Step 4: Check and update stock
    $stmt = $conn->prepare("SELECT quantity FROM stock WHERE stv_number = ?");
    $stmt->bind_param("s", $stv_number);
    $stmt->execute();
    $stmt->bind_result($current_quantity);
    $stmt->fetch();
    $stmt->close();

    if ($current_quantity < $itemCount) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Not enough stock available.";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    // Reduce stock
    $new_quantity = $current_quantity - $itemCount;
    $stmt = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stv_number = ?");
    $stmt->bind_param("is", $new_quantity, $stv_number);
    $stmt->execute();
    $stmt->close();

    // Step 5: Insert release data
    $query = "INSERT INTO releases 
        (antibiotic_name, dosage, item_count, release_time, ward_name, type, ant_type, system_name, book_number, page_number) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssisssssss", $antibioticname, $dosage, $itemCount, $releaseTime, $ward, $type, $ant_type, $systemName, $book_number, $page_number);

    if ($stmt->execute()) {
        $_SESSION['status'] = 'success';
        $_SESSION['message'] = "Antibiotic released and stock updated!";
    } else {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Insert error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    header("Location: pages-release-antibiotic.php");
    exit();
}
?>
