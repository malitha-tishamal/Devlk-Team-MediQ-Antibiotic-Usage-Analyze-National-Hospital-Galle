<?php
session_start();
date_default_timezone_set('Asia/Colombo');
require_once "includes/db-conn.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;

    // Get system_name
    $systemName = null;
    if ($user_id) {
        $stmt = $conn->prepare("SELECT system_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($systemName);
        $stmt->fetch();
        $stmt->close();
    }

    // Collect form data
    $antibioticName = trim($_POST['antibiotic_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $itemCount = intval($_POST['item_count'] ?? 0);
    $ward = trim($_POST['ward'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $antType = trim($_POST['ant_type'] ?? '');
    $bookNumber = trim($_POST['book_number_select'] ?? '');
    $pageNumber = trim($_POST['page_number_manual'] ?? '');

    $returnTime = (isset($_POST['datetime_option']) && $_POST['datetime_option'] === 'manual')
        ? trim($_POST['manual_datetime'] ?? '')
        : date('Y-m-d H:i:s');

    // Validate required fields
    if (empty($antibioticName) || empty($dosage) || empty($itemCount) || empty($ward) || empty($type) || empty($antType) || empty($returnTime)) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Missing required fields!";
        header("Location: pages-return-antibiotic.php");
        exit();
    }

    if ($itemCount <= 0) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Invalid item count!";
        header("Location: pages-return-antibiotic.php");
        exit();
    }

    // Get STV number
    $stmt = $conn->prepare("SELECT stv_number FROM dosages WHERE antibiotic_id = (SELECT id FROM antibiotics WHERE name = ?) AND dosage = ?");
    $stmt->bind_param("ss", $antibioticName, $dosage);
    $stmt->execute();
    $stmt->bind_result($stvNumber);
    $stmt->fetch();
    $stmt->close();

    if (!$stvNumber) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Error: Matching dosage not found!";
        header("Location: pages-return-antibiotic.php");
        exit();
    }

    // Update stock (add back returned quantity)
    $stmt = $conn->prepare("SELECT quantity FROM stock WHERE stv_number = ?");
    $stmt->bind_param("s", $stvNumber);
    $stmt->execute();
    $stmt->bind_result($currentQty);
    $stmt->fetch();
    $stmt->close();

    $newQty = $currentQty + $itemCount;
    $stmt = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stv_number = ?");
    $stmt->bind_param("is", $newQty, $stvNumber);
    $stmt->execute();
    $stmt->close();

    // Insert into returns table
    $stmt = $conn->prepare("INSERT INTO returns 
        (antibiotic_name, dosage, item_count, return_time, ward_name, type, ant_type, system_name, book_number, page_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisssssss", 
        $antibioticName, $dosage, $itemCount, $returnTime, $ward, $type, $antType, $systemName, $bookNumber, $pageNumber);

    if ($stmt->execute()) {
        $_SESSION['status'] = 'success';
        $_SESSION['message'] = "Antibiotic returned and stock updated!";
    } else {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "DB Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    header("Location: pages-return-antibiotic.php");
    exit();
}
?>
