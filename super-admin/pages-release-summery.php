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
$sql = "SELECT name, email, nic, mobile, profile_picture FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get filter type
$filterType = $_POST['filter_type'] ?? 'month';

// Get selected year and month range (for month filter)
$selectedYear = $_POST['year_select'] ?? date('Y');
$startMonth = $_POST['start_month_select'] ?? 1;  // Default to January
$endMonth = $_POST['end_month_select'] ?? 12;    // Default to December

// Get specific date range (for date filter)
$startDate = $_POST['start_date'] ?? date('Y-m-01'); // Default to the first day of the current month
$endDate = $_POST['end_date'] ?? date('Y-m-t');     // Default to the last day of the current month

// Set date range based on filter type
if ($filterType === 'month') {
    $startDate = "$selectedYear-$startMonth-01";
    $endDate = date('Y-m-t', strtotime("$selectedYear-$endMonth-01"));
}

// Now your existing queries can use $startDate and $endDate as before

// Query for table data (between selected months of the year, excluding syrups)
$query = "
    SELECT antibiotic_name, dosage, SUM(item_count) AS usage_count
    FROM releases
    WHERE release_time BETWEEN ? AND ?
    AND YEAR(release_time) = ?
    AND MONTH(release_time) BETWEEN ? AND ?
    GROUP BY antibiotic_name, dosage
    ORDER BY usage_count DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssiii", $startDate, $endDate, $selectedYear, $startMonth, $endMonth);
$stmt->execute();
$result = $stmt->get_result();

// Query for pie chart data (between selected months of the year, excluding syrups)
$pieChartQuery = "
    SELECT antibiotic_name, SUM(item_count) AS usage_count
    FROM releases
    WHERE release_time BETWEEN ? AND ?
    AND YEAR(release_time) = ?
    AND MONTH(release_time) BETWEEN ? AND ?
    GROUP BY antibiotic_name
    ORDER BY usage_count DESC
";
$pieStmt = $conn->prepare($pieChartQuery);
$pieStmt->bind_param("ssiii", $startDate, $endDate, $selectedYear, $startMonth, $endMonth);
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

    <!-- Include XLSX.js -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.1/xlsx.full.min.js"></script>

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
                title: 'Antibiotic Usage Distribution (<?php echo date('F Y', strtotime("$selectedYear-$startMonth-01")); ?> - <?php echo date('F Y', strtotime("$selectedYear-$endMonth-01")); ?>)',
                pieHole: 0.4, // Converts to a donut chart
                colors: ['#FF5733', '#33FF57', '#5733FF', '#FF33A1', '#33A1FF'],
                fontSize: 14,
                legend: { position: 'right', textStyle: { fontSize: 14 } },
                chartArea: { width: '85%', height: '75%' }
            };

            var chart = new google.visualization.PieChart(document.getElementById('piechart'));
            chart.draw(data, options);
        }

        // Download Excel function
        function downloadExcel() {
            var table = document.querySelector(".datatable");
            var wb = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
            XLSX.writeFile(wb, 'antibiotic_usage.xlsx');
        }
    </script>

    <style>
        #piechart { width: 95%; height: 400px; margin: auto; }
        .dataTables_filter { text-align: right; }
        .custom-search-box { margin-bottom: 15px; }
        @media print { .no-print { display: none; } }
        @media only screen and (min-width: 768px) {
            .select-bar {display: flex;}
        }
    </style>
</head>

<body>
    <?php include_once("../includes/header.php") ?>
    <?php include_once("../includes/sadmin-sidebar.php") ?>

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
                                <!-- Add a toggle for selecting filter type -->
                                <div class="form-group mb-3">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="filter_type" id="filter_month" value="month" checked>
                                        <label class="form-check-label" for="filter_month">Filter by Month Range</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="filter_type" id="filter_date" value="date">
                                        <label class="form-check-label" for="filter_date">Filter by Date Range</label>
                                    </div>
                                </div>

                                <!-- Month range selection (shown by default) -->
                                <div id="month_range_filters" class="form-row mb-3 select-bar">
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
                                        <label for="start_month_select" class="col-form-label">Select Start Month:</label>
                                        <select name="start_month_select" id="start_month_select" class="form-select">
                                            <?php
                                            $months = [
                                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
                                                7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                            ];
                                            foreach ($months as $monthNum => $monthName) {
                                                echo "<option value='$monthNum'" . ($monthNum == $startMonth ? ' selected' : '') . ">$monthName</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="col-sm-3">
                                        <label for="end_month_select" class="col-form-label">Select End Month:</label>
                                        <select name="end_month_select" id="end_month_select" class="form-select">
                                            <?php
                                            foreach ($months as $monthNum => $monthName) {
                                                echo "<option value='$monthNum'" . ($monthNum == $endMonth ? ' selected' : '') . ">$monthName</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Date range selection (hidden by default) -->
                                <div id="date_range_filters" class="form-row mb-3 select-bar" style="display: none;">
                                    <div class="col-sm-3">
                                        <label for="start_date" class="col-form-label">Select Start Date:</label>
                                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $_POST['start_date'] ?? ''; ?>">
                                    </div>

                                    <div class="col-sm-3">
                                        <label for="end_date" class="col-form-label">Select End Date:</label>
                                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $_POST['end_date'] ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="col-sm-5">
                                    <button type="submit" class="btn btn-primary mt-4">Filter</button>
                                    <button type="button" class="btn btn-danger mt-4 ml-2 print-btn no-print" onclick="window.print()">Print Report</button>
                                    <button type="button" class="btn btn-success mt-4 ml-2 no-print" onclick="downloadExcel()">Download Excel</button>
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
                                        <th>Total Usage</th>
                                        <th>Units</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center">
                                    <?php 
                                    $count = 1;
                                    $totalUsage = 0;
                                    $totalGrams = 0;
                                    $totalUnits = 0;
                                    
                                    while ($row = $result->fetch_assoc()) {
                                        $dosage = strtolower($row['dosage']);
                                        $itemCount = $row['usage_count'];
                                        $usageInGrams = 0;

                                        if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
                                            $mgValue = (int)$matches[1];
                                            $usageInGrams = ($mgValue / 1000) * $itemCount; 
                                        } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
                                            $gValue = (float)$matches[1];
                                            $usageInGrams = $gValue * $itemCount;
                                        }

                                        $usageInUnits = $usageInGrams; // 1g = 1 unit
                                        $totalUnits += $usageInUnits; // Update total units
                                    }

                                    $result->data_seek(0); // Reset the result pointer to start

                                    while ($row = $result->fetch_assoc()) {
                                        $antibioticName = $row['antibiotic_name'];
                                        $dosage = strtolower($row['dosage']);
                                        $itemCount = $row['usage_count'];
                                        $usageInGrams = 0;

                                        if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
                                            $mgValue = (int)$matches[1];
                                            $usageInGrams = ($mgValue / 1000) * $itemCount;
                                        } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
                                            $gValue = (float)$matches[1];
                                            $usageInGrams = $gValue * $itemCount;
                                        }

                                        $usageInUnits = $usageInGrams;
                                        $percentageUsage = ($totalUnits > 0) ? ($usageInUnits / $totalUnits) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $count; ?></td>
                                        <td><?php echo $antibioticName; ?></td>
                                        <td><?php echo $dosage; ?></td>
                                        <td><?php echo number_format($itemCount); ?></td>
                                        <td><?php echo number_format($usageInUnits, 2); ?>g</td>
                                        <td><?php echo number_format($percentageUsage, 2); ?>%</td>
                                    </tr>
                                    <?php
                                        $count++;
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
    <?php include_once("../includes/js-links-inc.php") ?>

    <script>
        $(document).ready(function () {
            $('.datatable').DataTable();
        });
    </script>
    <script>
    $(document).ready(function() {
        // Toggle between filter types
        $('input[name="filter_type"]').change(function() {
            if ($(this).val() === 'month') {
                $('#month_range_filters').show();
                $('#date_range_filters').hide();
            } else {
                $('#month_range_filters').hide();
                $('#date_range_filters').show();
            }
        });
    });
</script>
</body>
</html>
