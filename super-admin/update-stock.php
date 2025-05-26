<?php
session_start();
require_once '../includes/db-conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stv_number = $_POST['stv_number'];
    $antibiotic_id = intval($_POST['antibiotic_id']);
    $dosage_id = intval($_POST['dosage_id']);
    $quantity = intval($_POST['quantity']);
    $action = $_POST['action']; // either 'add' or 'update'

    if ($quantity >= 0) {
        // Check if stock exists
        $check = $conn->prepare("SELECT quantity FROM stock WHERE stv_number = ?");
        $check->bind_param("s", $stv_number);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            // Stock exists — fetch current quantity
            $check->bind_result($existing_quantity);
            $check->fetch();
            $check->close();

            if ($action === 'add') {
                $new_quantity = $existing_quantity + $quantity;
            } else {
                $new_quantity = $quantity;
            }

            $stmt = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stv_number = ?");
            $stmt->bind_param("is", $new_quantity, $stv_number);

        } else {
            $check->close();

            // No existing stock — insert new
            $stmt = $conn->prepare("INSERT INTO stock (stv_number, antibiotic_id, dosage_id, quantity, last_updated) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("siii", $stv_number, $antibiotic_id, $dosage_id, $quantity);
        }

        if ($stmt->execute()) {
            $_SESSION['status'] = 'success';
            $_SESSION['message'] = ($action === 'add') ? 'Stock added successfully.' : 'Stock updated successfully.';
        } else {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Failed to save stock.';
        }
        $stmt->close();
    } else {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Quantity must be non-negative.';
    }

    header("Location: pages-manage-stock.php");
    exit();
}
?>
