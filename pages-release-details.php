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
$sql = "SELECT name, email, nic,mobile FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch users from the database
$sql = "SELECT id, antibiotic_name, dosage, item_count,ward_name, release_time FROM releases";
$result = $conn->query($sql);
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

    <?php include_once("includes/header.php") ?>

    <?php include_once("includes/user-sidebar.php") ?>

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

                            <!-- Table with user data -->
                            <table class="table datatable">
                                <thead class="align-middle text-center">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center ">antibiotic_name</th>
                                        <th class="text-center">dosage</th>
                                        <th class="text-center">iteam_count</th>
                                        <th class="text-center">Ward Name</th>
                                        <th class="text-center">release_time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td >" . $row['id'] . "</td>";
                                            echo "<td>" . $row['antibiotic_name'] . "</td>";
                                            echo "<td>" . $row['dosage'] . "</td>";
                                            echo "<td>" . $row['item_count'] . "</td>";
                                            echo "<td>" . $row['ward_name'] . "</td>";
                                            echo "<td>" . $row['release_time'] . "</td>";

                                        }
                                    } else {
                                        echo "<tr><td colspan='9' class='text-center'>No users found.</td></tr>";
                                    }
                                    ?>
                                </tbody>

                            </table>
                            <!-- End Table with user data -->

                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once("includes/footer.php") ?>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <?php include_once("includes/js-links-inc.php") ?>

</body>

</html>

<?php
// Close database connection
$conn->close();
?>
