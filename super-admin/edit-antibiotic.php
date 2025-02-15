<?php
session_start();
require_once "../includes/db-conn.php";

// Check if the ID is provided
if (!isset($_GET['id'])) {
    header("Location: pages-manage-antibiotic.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['admin_id'];
$sql = "SELECT name, email, nic, mobile FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$antibiotic_id = $_GET['id'];

// Fetch the antibiotic data
$sql = "SELECT * FROM antibiotics WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $antibiotic_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Antibiotic not found!';
    header("Location: pages-manage-antibiotic.php");
    exit();
}

$antibiotic = $result->fetch_assoc();

// Fetch dosages for the antibiotic
$dosages_sql = "SELECT dosage FROM dosages WHERE antibiotic_id = ?";
$dosages_stmt = $conn->prepare($dosages_sql);
$dosages_stmt->bind_param("i", $antibiotic_id);
$dosages_stmt->execute();
$dosages_result = $dosages_stmt->get_result();
$dosages = [];
while ($dosage_row = $dosages_result->fetch_assoc()) {
    $dosages[] = $dosage_row['dosage'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Edit Ward - Mediq</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
</head>
<body>

    <?php include_once("../includes/header.php"); ?>
    <?php include_once("../includes/sadmin-sidebar.php"); ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Edit Antibiotic</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="pages-manage-antibiotic.php">Manage Antibiotic</a></li>
                    <li class="breadcrumb-item active">Edit Antibiotic</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                             <h5 class="card-title mb-4">Edit Antibiotic Details</h5>
                            
                            <!-- Error Message -->
                            <?php if (isset($_SESSION['message'])): ?>
                                <div class="alert <?php echo $_SESSION['status'] == 'success' ? 'alert-success' : 'alert-danger'; ?>">
                                    <?php echo $_SESSION['message']; ?>
                                </div>
                                <?php unset($_SESSION['message']); unset($_SESSION['status']); ?>
                            <?php endif; ?>

                            <form action="update-antibiotic.php" method="POST">
                                <div class="mb-3">
                                     <label for="antibiotic_name">Antibiotic Name:</label>
                                    <input type="text" class="form-control" id="antibiotic_name" name="antibiotic_name" value="<?= htmlspecialchars($antibiotic['name']) ?>" required>
                                </div>

                                <div class="mb-3">
                                     <label for="dosage">Dosages:</label>
                                    <input type="text" class="form-control" id="dosage" name="dosage[]" value="<?= implode(', ', $dosages) ?>" required>
                                    <small class="form-text text-muted">Enter dosages separated by commas.</small>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
                                    <a href="pages-manage-antibiotic.php" class="btn btn-secondary mt-3">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once("../includes/footer.php"); ?>
</body>
</html>
