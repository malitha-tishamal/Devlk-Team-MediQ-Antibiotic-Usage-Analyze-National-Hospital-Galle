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
        // Check if return stock exists
        $check = $conn->prepare("SELECT quantity FROM return_stock WHERE stv_number = ?");
        $check->bind_param("s", $stv_number);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            // Return stock exists — fetch current quantity
            $check->bind_result($existing_quantity);
            $check->fetch();
            $check->close();

            if ($action === 'add') {
                $new_quantity = $existing_quantity + $quantity;
            } else {
                $new_quantity = $quantity;
            }

            $stmt = $conn->prepare("UPDATE return_stock SET quantity = ?, last_updated = NOW() WHERE stv_number = ?");
            $stmt->bind_param("is", $new_quantity, $stv_number);

        } else {
            $check->close();

            // No existing return stock — insert new
            $stmt = $conn->prepare("INSERT INTO return_stock (stv_number, antibiotic_id, dosage_id, quantity, last_updated) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("siii", $stv_number, $antibiotic_id, $dosage_id, $quantity);
        }

        if ($stmt->execute()) {
            $_SESSION['status'] = 'success';
            $_SESSION['message'] = ($action === 'add') ? 'Return stock added successfully.' : 'Return stock updated successfully.';
        } else {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Failed to save return stock.';
        }
        $stmt->close();
    } else {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Quantity must be non-negative.';
    }

    header("Location: pages-manage-return-stock.php");
    exit();
}
?>
