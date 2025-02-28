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

// Get selected month, year, day, and week
$selectedMonth = $_POST['month_select'] ?? date('m');
$selectedYear = $_POST['year_select'] ?? date('Y');
$selectedDay = $_POST['day_select'] ?? null;
$selectedWeek = $_POST['week_select'] ?? null;

// Calculate start and end days for each week of the selected month
$startOfMonth = "$selectedYear-$selectedMonth-01";
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$weeks = [];
for ($i = 1; $i <= $daysInMonth; $i++) {
    $date = date('Y-m-d', strtotime("$startOfMonth +$i days"));
    $week = date('W', strtotime($date)); // Get the week number
    if (!in_array($week, $weeks)) {
        $weeks[] = $week;
    }
}

// Query for table data (Day-wise or Week-wise)
if ($selectedDay) {
    $query = "
        SELECT antibiotic_name, dosage, SUM(item_count) AS usage_count, DAY(release_time) AS day
        FROM releases
        WHERE MONTH(release_time) = ? AND YEAR(release_time) = ? AND DAY(release_time) = ?
        GROUP BY antibiotic_name, dosage, day
        ORDER BY usage_count DESC
    ";
} elseif ($selectedWeek) {
    $startDate = date('Y-m-d', strtotime("{$selectedYear}-W{$selectedWeek}-1")); // Start of the selected week
    $endDate = date('Y-m-d', strtotime("{$selectedYear}-W{$selectedWeek}-7")); // End of the selected week
    $query = "
        SELECT antibiotic_name, dosage, SUM(item_count) AS usage_count, WEEK(release_time, 1) AS week
        FROM releases
        WHERE YEAR(release_time) = ? AND WEEK(release_time, 1) = ?
        GROUP BY antibiotic_name, dosage, week
        ORDER BY usage_count DESC
    ";
} else {
    $query = "
        SELECT antibiotic_name, dosage, SUM(item_count) AS usage_count
        FROM releases
        WHERE MONTH(release_time) = ? AND YEAR(release_time) = ?
        GROUP BY antibiotic_name, dosage
        ORDER BY usage_count DESC
    ";
}

$stmt = $conn->prepare($query);
if ($selectedDay) {
    $stmt->bind_param("iii", $selectedMonth, $selectedYear, $selectedDay);
} elseif ($selectedWeek) {
    $stmt->bind_param("ii", $selectedYear, $selectedWeek);
} else {
    $stmt->bind_param("ii", $selectedMonth, $selectedYear);
}
$stmt->execute();
$result = $stmt->get_result();

// Query for pie chart data
$pieChartQuery = "
    SELECT antibiotic_name, SUM(item_count) AS usage_count
    FROM releases
    WHERE YEAR(release_time) = ? AND MONTH(release_time) = ?
    GROUP BY antibiotic_name
    ORDER BY usage_count DESC
";
$pieStmt = $conn->prepare($pieChartQuery);
$pieStmt->bind_param("ii", $selectedYear, $selectedMonth);
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
                title: 'Antibiotic Usage Distribution (<?php echo date('F Y', strtotime("$selectedYear-$selectedMonth-01")); ?>)',
                pieHole: 0.4, // Converts to a donut chart
                colors: [
                    '#FF5733', '#33FF57', '#5733FF', '#FF33A1', '#33A1FF', '#A1FF33', '#FFC300', '#DAF7A6',
                    '#C70039', '#900C3F', '#581845', '#1ABC9C', '#2ECC71', '#3498DB', '#9B59B6', '#E74C3C',
                    '#F39C12', '#D35400', '#27AE60', '#16A085', '#2980B9', '#8E44AD', '#2C3E50', '#F1C40F',
                    '#E67E22', '#ECF0F1', '#95A5A6', '#7F8C8D', '#DFFF00', '#FFBF00', '#FF7F50', '#DE3163',
                    '#9FE2BF', '#40E0D0', '#6495ED', '#CCCCFF', '#800000', '#FF4500', '#2E8B57', '#8B4513',
                    '#808000', '#00CED1', '#20B2AA', '#5F9EA0', '#4B0082', '#4682B4', '#D2691E', '#8A2BE2',
                    '#6B8E23', '#FF1493', '#00BFFF', '#DC143C', '#FFD700', '#ADFF2F', '#F0E68C', '#90EE90',
                    '#FF6347', '#EE82EE', '#D8BFD8', '#00FFFF', '#FF00FF', '#8B0000', '#B22222', '#228B22',
                    '#32CD32', '#F0F8FF', '#FAFAD2', '#FFFF00', '#FF1493', '#800080', '#FF6347', '#00FF7F',
                    '#C71585', '#FFD700', '#9ACD32', '#32CD32', '#FF4500', '#98FB98', '#D3D3D3', '#808080',
                    '#E0FFFF', '#C0C0C0', '#ADD8E6', '#B0E0E6', '#A52A2A', '#F5F5DC', '#F0F0F0', '#DCDCDC',
                    '#F4A300', '#C9E4CA', '#DFFF00', '#4B0082', '#A52A2A', '#D2691E', '#C71585', '#DDA0DD',
                    '#FF7F50', '#DC143C', '#B0C4DE', '#F08080', '#FF8C00', '#B22222', '#FF4500', '#3CB371',
                    '#9B30FF', '#FF6347', '#98FB98', '#2F4F4F', '#8B008B', '#556B2F', '#2F4F4F', '#4B0082',
                    '#00FFFF', '#7FFFD4', '#8A2BE2', '#BC8F8F', '#F0FFF0', '#DAA520', '#CD5C5C', '#FFFACD',
                    '#D3D3D3', '#B8860B', '#A9A9A9', '#ADFF2F', '#A52A2A', '#D2691E', '#8B0000', '#E9967A',
                    '#CD5C5C', '#00008B', '#008B8B', '#BDB76B', '#8B4513', '#D2B48C', '#9ACD32', '#8B008B'
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

        // Download Excel function
        function downloadExcel() {
            var table = document.querySelector(".datatable");
            var wb = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
            XLSX.writeFile(wb, 'antibiotic_usage.xlsx');
        }
    </script>

    <style>
        #piechart { width: 95%; height: 400px; margin: auto; }
        @media print { .no-print { display: none; } }
        @media only screen and (min-width: 768px) {
            .select-bar {display: flex;}
        }
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
                                <div class="form-row mb-3 select-bar">
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
                                        <label for="day_select" class="col-form-label">Select Day:</label>
                                        <select name="day_select" id="day_select" class="form-select">
                                            <option value="">--Select Day--</option>
                                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                                <option value="<?php echo $i ?>" <?php echo ($i == $selectedDay ? 'selected' : ''); ?>><?php echo $i ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>

                                    <div class="col-sm-3">
                                        <label for="week_select" class="col-form-label">Select Week:</label>
                                        <select name="week_select" id="week_select" class="form-select">
                                            <option value="">--Select Week--</option>
                                            <?php foreach ($weeks as $week) { ?>
                                                <option value="<?php echo $week ?>" <?php echo ($week == $selectedWeek ? 'selected' : ''); ?>>Week <?php echo $week ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-sm-5">
                                    <button type="submit" class="btn btn-primary mt-4">Filter</button>
                                    <button class="btn btn-danger mt-4 ml-2 print-btn no-print" onclick="printPage()">Print Report</button>
                                    <button class="btn btn-success mt-4 ml-2 no-print" onclick="downloadExcel()">Download Excel</button>
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
                                    $totalQuery = "SELECT SUM(item_count) AS total_usage FROM releases WHERE YEAR(release_time) = '$selectedYear' AND MONTH(release_time) = '$selectedMonth'";
                                    if ($selectedDay) {
                                        $totalQuery .= " AND DAY(release_time) = '$selectedDay'";
                                    } elseif ($selectedWeek) {
                                        $totalQuery .= " AND WEEK(release_time, 1) = '$selectedWeek'";
                                    }
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

    <script>
        function exportTableToPDF() {
            const { jsPDF } = window.jspdf;
            let doc = new jsPDF();

            // Title
            doc.setFont("helvetica", "bold");
            doc.setFontSize(16);
            doc.text("Antibiotic Usage Report", 105, 10, { align: "center" });

            // Get table data
            const table = document.querySelector(".datatable");
            doc.autoTable({ html: table, startY: 20 });

            // Save the PDF
            doc.save("antibiotic_usage_report.pdf");
        }


    </script>
</body>
</html>
