<?php
session_start();
require_once '../includes/db-conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['admin_id'];
$sql = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch all antibiotic-dosage combinations with LEFT JOIN to stock
$sql = "SELECT 
            d.stv_number,
            a.id AS antibiotic_id,
            a.name AS antibiotic_name,
            d.id AS dosage_id,
            d.dosage,
            s.quantity,
            s.last_updated
        FROM dosages d
        JOIN antibiotics a ON d.antibiotic_id = a.id
        LEFT JOIN stock s ON s.stv_number = d.stv_number
        ORDER BY a.name ASC, d.dosage ASC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Antibiotic Stock - Mediq</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Antibiotic Stock</h1>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body pt-4">
                <table class="table table-bordered datatable">
                    <thead class="text-center">
                        <tr>
                            <th>SR Number</th>
                            <th>Drug Name</th>
                            <th>Dosage</th>
                            <th>Quantity</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="text-center">
                                <td><?= htmlspecialchars($row['stv_number']) ?></td>
                                <td><?= htmlspecialchars($row['antibiotic_name']) ?></td>
                                <td><?= htmlspecialchars($row['dosage']) ?></td>
                                <td><?= is_null($row['quantity']) ? '<span class="text-danger">Not Added</span>' : $row['quantity'] ?></td>
                                <td><?= is_null($row['last_updated']) ? '-' : $row['last_updated'] ?></td>
                                <td>
                                    <form action="update-stock.php" method="POST" class="d-flex justify-content-center gap-1">
                                        <input type="hidden" name="stv_number" value="<?= $row['stv_number'] ?>">
                                        <input type="hidden" name="antibiotic_id" value="<?= $row['antibiotic_id'] ?>">
                                        <input type="hidden" name="dosage_id" value="<?= $row['dosage_id'] ?>">
                                        <input type="number" name="quantity" value="0" class="form-control form-control-sm me-2" min="0" required>

                                        <button type="submit" name="action" value="add" class="btn btn-sm btn-success">
                                            Add
                                        </button>
                                        <button type="submit" name="action" value="update" class="btn btn-sm btn-primary">
                                            Update
                                        </button>
                                    </form>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<?php include_once("../includes/footer.php"); ?>
<?php include_once("../includes/js-links-inc.php"); ?>
</body>
</html>
