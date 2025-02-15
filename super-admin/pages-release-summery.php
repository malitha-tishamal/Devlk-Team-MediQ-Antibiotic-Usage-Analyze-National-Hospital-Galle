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

// Get selected month and year
$selectedMonth = $_POST['month_select'] ?? date('m');
$selectedYear = $_POST['year_select'] ?? date('Y');

// Query for table data
$query = "
    SELECT antibiotic_name, dosage, SUM(item_count) AS usage_count
    FROM releases
    WHERE MONTH(release_time) = ? AND YEAR(release_time) = ?
    GROUP BY antibiotic_name, dosage
    ORDER BY usage_count DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $selectedMonth, $selectedYear);
$stmt->execute();
$result = $stmt->get_result();

// Query for pie chart data
$pieChartQuery = "
    SELECT antibiotic_name, SUM(item_count) AS usage_count
    FROM releases
    WHERE MONTH(release_time) = ? AND YEAR(release_time) = ?
    GROUP BY antibiotic_name
    ORDER BY usage_count DESC
";
$pieStmt = $conn->prepare($pieChartQuery);
$pieStmt->bind_param("ii", $selectedMonth, $selectedYear);
$pieStmt->execute();
$pieChartResult = $pieStmt->get_result();
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

    <!-- Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', { 'packages': ['corechart'] });
        google.charts.setOnLoadCallback(drawChart);

        function drawChart() {
            var data = google.visualization.arrayToDataTable([
                ['Antibiotic', 'Usage Count'],
                <?php 
                while ($row = $pieChartResult->fetch_assoc()) {
                    echo "['" . addslashes($row['antibiotic_name']) . "', " . $row['usage_count'] . "],";
                }
                ?>
            ]);

            var options = {
                title: 'Antibiotic Usage Distribution (<?php echo date('F Y', strtotime("$selectedYear-$selectedMonth-01")); ?>)',
                pieHole: 0.4, // Converts to a donut chart
                colors: [
                    '#FF5733', '#33FF57', '#5733FF', '#FF33A1', '#33A1FF', '#A1FF33', '#FFC300', '#DAF7A6', 
                    '#C70039', '#900C3F', '#581845', '#1ABC9C', '#2ECC71', '#3498DB', '#9B59B6', '#E74C3C', 
                    '#F39C12', '#D35400', '#27AE60', '#16A085', '#2980B9', '#8E44AD', '#2C3E50', '#F1C40F', 
                    '#E67E22', '#ECF0F1', '#95A5A6', '#7F8C8D', '#DFFF00', '#FFBF00', '#FF7F50', '#DE3163', 
                    '#9FE2BF', '#40E0D0', '#6495ED', '#CCCCFF', '#800000', '#FF4500', '#2E8B57', '#8B4513', 
                    '#808000', '#00CED1', '#20B2AA', '#5F9EA0', '#4B0082', '#4682B4', '#D2691E', '#8A2BE2', 
                    '#6B8E23', '#FF1493', '#00BFFF', '#DC143C'
                ],
                fontSize: 14,
                legend: { position: 'right', textStyle: { fontSize: 14 } },
                chartArea: { width: '85%', height: '75%' }
            };

            var chart = new google.visualization.PieChart(document.getElementById('piechart'));
            chart.draw(data, options);
        }

        $(document).ready(function() {
            // Initialize DataTables with the Show All Entries option
            $('.datatable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "pageLength": 10, // Default page length (can be customized)
                "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ] // Show 'All' option
            });
        });
    </script>

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
            <h1>Usage Details</h1>
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
                                </div>
                                <div class="col-sm-3">
                                    <button type="submit" class="btn btn-primary mt-4">Filter</button>
                                    <button class="btn btn-danger mt-4 ml-2 print-btn no-print" onclick="printPage()">Print Report</button>
                                </div>
                            </form>

                            <div id="piechart" style="float: left;"></div>
                        </div>
                        
                        <!-- DataTable -->
                        <div style="padding: 15px;">
                            <table class="table datatable">
                            <thead class="align-middle text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Antibiotic Name</th>
                                    <th>Dosage</th>
                                    <th>Usage Count</th>
                                    <th>Percentage (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php 
                                // Get total usage count to calculate percentages
                                $totalQuery = "SELECT SUM(item_count) AS total_usage FROM releases WHERE MONTH(release_time) = '$selectedMonth' AND YEAR(release_time) = '$selectedYear'";
                                $totalResult = $conn->query($totalQuery);
                                $totalRow = $totalResult->fetch_assoc();
                                $totalUsage = $totalRow['total_usage'] ?? 1;

                                if ($result->num_rows > 0) {
                                    $rowNumber = 1;
                                    while ($row = $result->fetch_assoc()) {
                                        $percentage = round(($row['usage_count'] / $totalUsage) * 100, 2);
                                        echo "<tr>";
                                        echo "<td class='text-center'>{$rowNumber}</td>";
                                        echo "<td class='text-center'>{$row['antibiotic_name']}</td>";
                                        echo "<td class='text-center'>{$row['dosage']}</td>";
                                        echo "<td class='text-center'>{$row['usage_count']}</td>";
                                        echo "<td class='text-center'>{$percentage}%</td>";
                                        echo "</tr>";
                                        $rowNumber++;
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center'>No data available for the selected month and year</td></tr>";
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
