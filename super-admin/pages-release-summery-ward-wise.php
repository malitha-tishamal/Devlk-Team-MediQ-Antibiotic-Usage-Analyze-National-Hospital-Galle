<?php
session_start();
require_once '../includes/db-conn.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
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

// Get selected month, year, ward, and type
$selectedMonth = $_POST['month_select'] ?? date('m');
$selectedYear = $_POST['year_select'] ?? date('Y');
$selectedWard = $_POST['ward_select'] ?? '';
$selectedType = $_POST['type_select'] ?? ''; // Added for MSD/LP

// Fetch all wards from the database
$wardQuery = "SELECT DISTINCT ward_name FROM releases ORDER BY ward_name";
$wardStmt = $conn->prepare($wardQuery);
$wardStmt->execute();
$wardResult = $wardStmt->get_result();

// Query for antibiotic usage filtered by selected month, year, ward, and type
$query = "
    SELECT ward_name, antibiotic_name, dosage, type, SUM(item_count) AS usage_count
    FROM releases
    WHERE MONTH(release_time) = ? AND YEAR(release_time) = ?
    AND (ward_name = ? OR ? = '')
    AND (type = ? OR ? = '')
    GROUP BY ward_name, antibiotic_name, dosage
    ORDER BY ward_name, usage_count DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iissss", $selectedMonth, $selectedYear, $selectedWard, $selectedWard, $selectedType, $selectedType);
$stmt->execute();
$result = $stmt->get_result();

// Query to get total usage count per ward for percentage calculation
$wardUsageQuery = "
    SELECT ward_name, SUM(item_count) AS ward_total
    FROM releases
    WHERE MONTH(release_time) = ? AND YEAR(release_time) = ?
    AND (ward_name = ? OR ? = '')
    GROUP BY ward_name
";
$wardUsageStmt = $conn->prepare($wardUsageQuery);
$wardUsageStmt->bind_param("iiss", $selectedMonth, $selectedYear, $selectedWard, $selectedWard);
$wardUsageStmt->execute();
$wardUsageResult = $wardUsageStmt->get_result();

$wardUsage = [];
while ($row = $wardUsageResult->fetch_assoc()) {
    $wardUsage[$row['ward_name']] = $row['ward_total'];
}
$stmt->close();
$wardStmt->close();
$wardUsageStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Antibiotic Usage - Mediq</title>
    <?php include_once("../includes/css-links-inc.php"); ?>

    <!-- Include DataTables CSS and JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>

    <style>
        #piechart { width: 50%; height: 400px; margin: auto; }
        @media print { .no-print { display: none; } }
    </style>
    <script> function printPage() { window.print(); } </script>
</head>

<body>
    <?php include_once("../includes/header.php") ?>
    <?php include_once("../includes/sadmin-sidebar2.php") ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Usage Details Ward Wise</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                    <li class="breadcrumb-item">Pages</li>
                    <li class="breadcrumb-item active">Antibiotic Usage Details</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Antibiotic Release Details</h5>

                            <form method="POST">
                                <div class="form-row mb-3 d-flex">
                                     <div class="col-sm-3">
                                        <label for="year_select" class="col-form-label">Select Year:</label>
                                        <select name="year_select" id="year_select" class="form-select">
                                            <?php
                                            $currentYear = date('Y');
                                            for ($i = 2020; $i <= $currentYear; $i++) {
                                                echo "<option value='$i'" . ($i == $selectedYear ? ' selected' : '') . ">$i</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-sm-3">
                                        <label for="month_select" class="col-form-label">Select Month:</label>
                                        <select name="month_select" id="month_select" class="form-select">
                                            <?php
                                            $months = [
                                                '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                                                '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                                                '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                                            ];
                                            foreach ($months as $monthNum => $monthName) {
                                                echo "<option value='$monthNum'" . ($monthNum == $selectedMonth ? ' selected' : '') . ">$monthName</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                  
                                    <div class="col-sm-3">
                                        <label for="ward_select" class="col-form-label">Select Ward:</label>
                                        <select name="ward_select" id="ward_select" class="form-select">
                                            <option value="">All Wards</option>
                                            <?php
                                            while ($wardRow = $wardResult->fetch_assoc()) {
                                                $wardName = $wardRow['ward_name'];
                                                echo "<option value='$wardName'" . ($wardName == $selectedWard ? ' selected' : '') . ">$wardName</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Added MSD/LP Filter -->
                                    <div class="col-sm-3">
                                        <label for="type_select" class="col-form-label">Select Stock:</label>
                                        <select name="type_select" id="type_select" class="form-select">
                                            <option value="">All Types</option>
                                            <option value="msd" <?php echo ($selectedType == 'msd') ? 'selected' : ''; ?>>MSD</option>
                                            <option value="lp" <?php echo ($selectedType == 'lp') ? 'selected' : ''; ?>>LP</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <button type="submit" class="btn btn-primary mt-4">Filter</button>
                                    <button class="btn btn-danger mt-4 ml-2 print-btn no-print" onclick="printPage()">Print Report</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- DataTable -->
                        <div style="padding: 15px;">
                            <table class="table datatable">
                            <thead class="align-middle text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Ward Name</th>
                                    <th>Antibiotic Name</th>
                                    <th>Dosage</th>
                                    <th>Usage Count</th>
                                    <th>Percentage (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php 
                                if ($result->num_rows > 0) {
                                    $rowNumber = 1;
                                    $previousWard = "";
                                    while ($row = $result->fetch_assoc()) {
                                        $wardName = $row['ward_name'];
                                        $totalUsageInWard = $wardUsage[$wardName];
                                        $percentage = round(($row['usage_count'] / $totalUsageInWard) * 100, 2);
                                        
                                        if ($wardName != $previousWard) {
                                            echo "<tr><td colspan='6' class='text-center card-title'>$wardName</td></tr>";
                                            $previousWard = $wardName;
                                        }

                                        echo "<tr>";
                                        echo "<td class='text-center'>{$rowNumber}</td>";
                                        echo "<td class='text-center'>{$row['ward_name']}</td>";
                                        echo "<td class='text-center'>{$row['antibiotic_name']}</td>";
                                        echo "<td class='text-center'>{$row['dosage']}</td>";
                                        echo "<td class='text-center'>{$row['usage_count']}</td>";
                                        echo "<td class='text-center'>{$percentage}%</td>";
                                        echo "</tr>";
                                        $rowNumber++;
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center'>No data available for the selected month, year, ward, and type</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once("../includes/footer.php") ?>
</body>
</html>
