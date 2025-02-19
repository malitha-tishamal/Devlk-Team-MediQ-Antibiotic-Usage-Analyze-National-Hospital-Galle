<?php
session_start();
require_once 'includes/db-conn.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT name, email, nic, mobile FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle date filter
$filter_date = isset($_POST['filter_date']) ? $_POST['filter_date'] : date('Y-m-d');
$sql = "SELECT id, antibiotic_name, dosage, item_count, ward_name, type, ant_type, release_time FROM releases WHERE DATE(release_time) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $filter_date);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>Antibiotic Release Details - Mediq</title>

    <?php include_once("includes/css-links-inc.php"); ?>
</head>

<body>
    <?php include_once("includes/header.php"); ?>
    <?php include_once("includes/user-sidebar.php"); ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Release Details</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                    <li class="breadcrumb-item">Pages</li>
                    <li class="breadcrumb-item active">Antibiotic Release Details</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Antibiotic Release Details</h5>

                            <!-- Date Filter Form -->
                            <form method="POST" class="mb-3">
                                <div class="d-flex">
                                    <label for="filter_date">Select Date:</label>
                                    <input type="date" name="filter_date" id="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" class="form-control w-25">
                                    &nbsp;&nbsp;
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                </div>
                            </form>

                            <!-- Table with release data -->
                            <table class="table datatable">
                                <thead class="align-middle text-center">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center">Antibiotic Name</th>
                                        <th class="text-center">Dosage</th>
                                        <th class="text-center">Item Count</th>
                                        <th class="text-center">Ward Name</th>
                                        <th class="text-center">Stock Type</th>
                                        <th class="text-center">Antibiotic Type</th>
                                        <th class="text-center">Release Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['id']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['antibiotic_name']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['dosage']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['item_count']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['ward_name']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['type']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['ant_type']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['release_time']) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='8' class='text-center'>No antibiotic releases found for this date.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <!-- End Table with release data -->
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once("includes/footer.php"); ?>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
    <?php include_once("includes/js-links-inc.php"); ?>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>
