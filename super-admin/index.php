<?php
session_start();
date_default_timezone_set('Asia/Colombo'); // ✅ Sri Lanka timezone
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

// Query for chart data (last 3 months, usage in grams)
$chartQuery = "
    SELECT MONTH(release_time) AS month, YEAR(release_time) AS year, dosage, SUM(item_count) AS count
    FROM releases
    WHERE release_time >= DATE_FORMAT(NOW() - INTERVAL 2 MONTH, '%Y-%m-01')
    GROUP BY YEAR(release_time), MONTH(release_time), dosage
    ORDER BY year ASC, month ASC
";

$chartStmt = $conn->prepare($chartQuery);
$chartStmt->execute();
$chartResult = $chartStmt->get_result();

$monthlyData = [];

// Convert dosage to grams and calculate usage
while ($row = $chartResult->fetch_assoc()) {
    $monthKey = "{$row['year']}-{$row['month']}-01";
    $dosage = strtolower($row['dosage']);
    $count = (int)$row['count'];

    $grams = 0;
    if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
        $grams = ($matches[1] / 1000) * $count;
    } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
        $grams = $matches[1] * $count;
    }

    $monthlyData[$monthKey] = ($monthlyData[$monthKey] ?? 0) + $grams;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Home - MediQ</title>

    <?php include_once("../includes/css-links-inc.php"); ?>

    <!-- Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', { 'packages': ['corechart', 'line', 'bar'] });
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Month');
            data.addColumn('number', 'Total Usage (g)');

            <?php 
            ksort($monthlyData); // sort chronologically
            $jsChartData = [];
            foreach ($monthlyData as $date => $gramsUsed) {
                $monthLabel = date('F Y', strtotime($date)); // e.g., "June 2025"
                $roundedGrams = round($gramsUsed, 2);
                $jsChartData[] = "['$monthLabel', $roundedGrams]";
            }
            echo "data.addRows([" . implode(",", $jsChartData) . "]);";
            ?>

            var lineOptions = {
                title: 'Antibiotic Usage in Grams (Last 3 Months)',
                curveType: 'function',
                legend: { position: 'bottom' },
                hAxis: { title: 'Month' },
                vAxis: { title: 'Usage (g)' }
            };

            var barOptions = {
                title: 'Antibiotic Usage in Grams (Last 3 Months)',
                chartArea: { width: '50%' },
                hAxis: { title: 'Usage (g)', minValue: 0 },
                vAxis: { title: 'Month' }
            };

            var lineChart = new google.visualization.LineChart(document.getElementById('linechart'));
            lineChart.draw(data, lineOptions);

            var barChart = new google.visualization.BarChart(document.getElementById('barchart'));
            barChart.draw(data, barOptions);
        }
    </script>

    <style>
        #linechart, #barchart {
            width: 100%;
            height: 400px;
            margin: auto;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
    <script> function printPage() { window.print(); } </script>
</head>
<body>

<?php include_once("../includes/header.php") ?>
<?php include_once("../includes/sadmin-sidebar.php") ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Home</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="">Home</a></li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div>
                        <h6 class="card-title text-center">
                            Welcome, <?php echo htmlspecialchars($user['name']); ?>.
                            This is an Antibiotic Usage Summary (in grams) for the last 3 months.
                        </h6>
                    </div>
                    <div class="card-body d-flex flex-column flex-md-row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Line Chart</h5>
                                    <div id="linechart"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Bar Chart</h5>
                                    <div id="barchart"></div>
                                </div>
                            </div>
                            <div class="col-sm-5">
                                <button class="btn btn-danger mt-4 ml-2 print-btn no-print" onclick="printPage()">Print Report</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<?php include_once("../includes/footer2.php") ?>
<a href="#" class="back-to-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
</a>
<?php include_once("../includes/js-links-inc.php") ?>

</body>
</html>
