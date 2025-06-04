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

    $releaseTime = (isset($_POST['datetime_option']) && $_POST['datetime_option'] === 'manual')
        ? trim($_POST['manual_datetime'] ?? '')
        : date('Y-m-d H:i:s');

    // Step 3: Validate fields
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

    // Step 4: Get stv_number from dosage
    $stmt = $conn->prepare("SELECT d.stv_number FROM dosages d 
                            JOIN antibiotics a ON d.antibiotic_id = a.id 
                            WHERE a.name = ? AND d.dosage = ?");
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

    // Step 4.1: Get ward_category from wards table
    $stmt = $conn->prepare("SELECT category FROM ward WHERE ward_name = ?");
    $stmt->bind_param("s", $ward);
    $stmt->execute();
    $stmt->bind_result($ward_category);
    $stmt->fetch();
    $stmt->close();

    if (!$ward_category) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Ward category not found.";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    // Step 4.2: Get category from antibiotics table
    $stmt = $conn->prepare("SELECT category FROM antibiotics WHERE name = ?");
    $stmt->bind_param("s", $antibioticname);
    $stmt->execute();
    $stmt->bind_result($antibiotic_category);
    $stmt->fetch();
    $stmt->close();

    if (!$antibiotic_category) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Antibiotic category not found.";
        header("Location: pages-release-antibiotic.php");
        exit();
    }

    // Step 5: Check and update stock
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

    $new_quantity = $current_quantity - $itemCount;

    $stmt = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stv_number = ?");
    $stmt->bind_param("is", $new_quantity, $stv_number);
    $stmt->execute();
    $stmt->close();

    // Step 6: Insert into releases (with antibiotic category)
    $query = "INSERT INTO releases 
        (antibiotic_name, dosage, item_count, release_time, ward_name, type, ant_type, ward_category, system_name, book_number, page_number, category) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "ssisssssssss", 
        $antibioticname, $dosage, $itemCount, $releaseTime, 
        $ward, $type, $ant_type, $ward_category, 
        $systemName, $book_number, $page_number, $antibiotic_category
    );

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
