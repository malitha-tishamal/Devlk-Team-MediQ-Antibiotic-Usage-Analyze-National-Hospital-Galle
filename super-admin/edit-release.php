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

$user_id = $_SESSION['admin_id'];
$sql = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_user = $stmt->get_result();
$user = $result_user->fetch_assoc();
$stmt->close();

$id = intval($_GET['id']);

// Fetch existing release
$stmt = $conn->prepare("SELECT * FROM releases WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$release = $result->fetch_assoc();

if (!$release) {
    echo "Release not found!";
    exit();
}

// Fetch dropdown lists
$antibioticList = $conn->query("SELECT DISTINCT antibiotic_name FROM releases");
$wardList = $conn->query("SELECT DISTINCT ward_name FROM releases");

// Fetch dosages for the currently selected antibiotic for initial load
$stmt = $conn->prepare("SELECT DISTINCT dosage FROM releases WHERE antibiotic_name = ?");
$stmt->bind_param("s", $release['antibiotic_name']);
$stmt->execute();
$resultDosages = $stmt->get_result();
$currentDosages = [];
while ($row = $resultDosages->fetch_assoc()) {
    $currentDosages[] = $row['dosage'];
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $antibiotic_name = $_POST['antibiotic_name'];
    $dosage = $_POST['dosage'];
    $item_count = $_POST['item_count'];
    $ward_name = $_POST['ward_name'];
    $type = $_POST['type'];
    $book_number = $_POST['book_number'];
    $page_number = $_POST['page_number'];

    $update = $conn->prepare("UPDATE releases SET antibiotic_name=?, dosage=?, item_count=?, ward_name=?, type=?, book_number=?, page_number=? WHERE id=?");
    $update->bind_param("ssisssii", $antibiotic_name, $dosage, $item_count, $ward_name, $type, $book_number, $page_number, $id);
    $update->execute();

    if ($update->affected_rows > 0) {
        header("Location: pages-release-details.php?msg=updated");
        exit();
    } else {
        echo "<p class='text-danger mt-3'>No changes made or error occurred.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Release</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="container mt-4">
        <h2>Edit Antibiotic Release</h2>
        <form method="POST" id="editReleaseForm">
            <div class="mb-3">
                <label for="antibiotic_name">Antibiotic Name:</label>
                <select id="antibiotic_name" name="antibiotic_name" class="form-control" required>
                    <?php while($row = $antibioticList->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($row['antibiotic_name']) ?>"
                            <?= $row['antibiotic_name'] == $release['antibiotic_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['antibiotic_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="dosage">Dosage:</label>
                <select id="dosage" name="dosage" class="form-control" required>
                    <?php foreach ($currentDosages as $dosage): ?>
                        <option value="<?= htmlspecialchars($dosage) ?>"
                            <?= $dosage == $release['dosage'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dosage) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="item_count">Item Count:</label>
                <input type="number" id="item_count" name="item_count" class="form-control" value="<?= htmlspecialchars($release['item_count']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="ward_name">Ward Name:</label>
                <select id="ward_name" name="ward_name" class="form-control" required>
                    <?php while($row = $wardList->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($row['ward_name']) ?>"
                            <?= $row['ward_name'] == $release['ward_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['ward_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="type">Stock Type:</label>
                <select id="type" name="type" class="form-control" required>
                    <option value="msd" <?= $release['type'] === 'msd' ? 'selected' : '' ?>>msd</option>
                    <option value="lp" <?= $release['type'] === 'lp' ? 'selected' : '' ?>>lp</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="book_number">Book Number:</label>
                <input type="text" id="book_number" name="book_number" class="form-control" value="<?= htmlspecialchars($release['book_number']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="page_number">Page Number:</label>
                <input type="text" id="page_number" name="page_number" class="form-control" value="<?= htmlspecialchars($release['page_number']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="pages-release-details.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</main>

<?php include_once("../includes/footer.php"); ?>
<?php include_once("../includes/js-links-inc.php"); ?>

<script>
document.getElementById('antibiotic_name').addEventListener('change', function() {
    var antibiotic = this.value;
    var dosageSelect = document.getElementById('dosage');

    dosageSelect.innerHTML = '<option>Loading...</option>';

    fetch('fetch_dosages.php?antibiotic_name=' + encodeURIComponent(antibiotic))
        .then(response => response.json())
        .then(data => {
            dosageSelect.innerHTML = '';
            if (data.length === 0) {
                dosageSelect.innerHTML = '<option value="">No dosages found</option>';
            } else {
                data.forEach(function(dosage) {
                    var option = document.createElement('option');
                    option.value = dosage;
                    option.textContent = dosage;
                    dosageSelect.appendChild(option);
                });
            }
        })
        .catch(() => {
            dosageSelect.innerHTML = '<option value="">Error loading dosages</option>';
        });
});
</script>

</body>
</html>
