<?php
session_start();
require_once '../includes/db-conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "Invalid request!";
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

$return_id = intval($_GET['id']);

// Fetch the return record
$stmt = $conn->prepare("SELECT * FROM returns WHERE id = ?");
$stmt->bind_param("i", $return_id);
$stmt->execute();
$result = $stmt->get_result();
$return = $result->fetch_assoc();
$stmt->close();

if (!$return) {
    echo "Return record not found!";
    exit();
}

// Fetch dropdown data
$antibiotics = $conn->query("SELECT DISTINCT antibiotic_name FROM releases");
$wards = $conn->query("SELECT DISTINCT ward_name FROM releases");

// Fetch dosages based on selected antibiotic
$selected_antibiotic = $return['antibiotic_name'];
$dosages_stmt = $conn->prepare("SELECT DISTINCT dosage FROM releases WHERE antibiotic_name = ?");
$dosages_stmt->bind_param("s", $selected_antibiotic);
$dosages_stmt->execute();
$dosages_result = $dosages_stmt->get_result();
$dosages = [];
while ($row = $dosages_result->fetch_assoc()) {
    $dosages[] = $row['dosage'];
}
$dosages_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $antibiotic_name = $_POST['antibiotic_name'];
    $dosage = $_POST['dosage'];
    $item_count = $_POST['item_count'];
    $ward_name = $_POST['ward_name'];

    $update = $conn->prepare("UPDATE returns SET antibiotic_name=?, dosage=?, item_count=?, ward_name=? WHERE id=?");
    $update->bind_param("ssisi", $antibiotic_name, $dosage, $item_count, $ward_name, $return_id);
    $update->execute();

    if ($update->affected_rows > 0) {
        header("Location: pages-return-details.php?msg=updated");
        exit();
    } else {
        $msg = "<p class='text-danger mt-3'>No changes made or error occurred.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Return</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="container mt-4">
        <h2>Edit Return Record</h2>
        <?php if (isset($msg)) echo $msg; ?>
        <form method="POST">
            <div class="mb-3">
                <label>Antibiotic Name:</label>
                <select name="antibiotic_name" class="form-control" required>
                    <?php while ($row = $antibiotics->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($row['antibiotic_name']) ?>"
                            <?= $row['antibiotic_name'] === $return['antibiotic_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['antibiotic_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label>Dosage:</label>
                <select name="dosage" class="form-control" required>
                    <?php foreach ($dosages as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $d === $return['dosage'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label>Item Count:</label>
                <input type="number" name="item_count" class="form-control" value="<?= htmlspecialchars($return['item_count']) ?>" required>
            </div>

            <div class="mb-3">
                <label>Ward Name:</label>
                <select name="ward_name" class="form-control" required>
                    <?php while ($row = $wards->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($row['ward_name']) ?>"
                            <?= $row['ward_name'] === $return['ward_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['ward_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="pages-return-details.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</main>

<?php include_once("../includes/footer.php"); ?>
<?php include_once("../includes/js-links-inc.php"); ?>
</body>
</html>
