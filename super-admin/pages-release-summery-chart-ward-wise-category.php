<?php 
session_start();
date_default_timezone_set('Asia/Colombo');

require_once '../includes/db-conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT name, email, nic, mobile, profile_picture FROM admins WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get filter inputs
$startMonth = $_POST['start_month'] ?? date('m');
$startYear = $_POST['start_year'] ?? date('Y');
$endMonth = $_POST['end_month'] ?? date('m');
$endYear = $_POST['end_year'] ?? date('Y');

$startDate = date('Y-m-01', strtotime("$startYear-$startMonth-01"));
$endDate = date('Y-m-t', strtotime("$endYear-$endMonth-01"));

/** Chart 1: Antibiotic usage by Ward Category **/
$antibioticData = [];
$wardCategories = ['Pediatrics', 'Medicine', 'Medicine Subspecialty', 'Surgery', 'Surgery Subspecialty', 'ICU'];
$antibiotics = [];

$stmt = $conn->prepare("SELECT ward_category, antibiotic_name, dosage, SUM(item_count) AS usage_count FROM releases WHERE release_time BETWEEN ? AND ? GROUP BY ward_category, antibiotic_name, dosage");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $category = $row['ward_category'];
    $antibiotic = $row['antibiotic_name'];
    $dosage = strtolower($row['dosage']);
    $count = $row['usage_count'];

    if (!in_array($antibiotic, $antibiotics)) $antibiotics[] = $antibiotic;

    $units = 0;
    if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
        $units = ($matches[1] / 1000) * $count;
    } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
        $units = $matches[1] * $count;
    }

    $antibioticData[$category][$antibiotic] = ($antibioticData[$category][$antibiotic] ?? 0) + $units;
}
$stmt->close();
sort($antibiotics);

/** Chart 2: Category usage (Access, Watch, Reserve) by Ward Category **/
$categoryColors = [
    'Access' => '#28a745',
    'Watch' => '#ffc107',
    'Reserve' => '#dc3545',
    'Other' => '#6c757d'
];

$categories = ['Access', 'Watch', 'Reserve', 'Other'];
$dataMap = [];

$stmt = $conn->prepare("SELECT ward_category, category, dosage, SUM(item_count) AS usage_count FROM releases WHERE release_time BETWEEN ? AND ? GROUP BY ward_category, category, dosage");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $category = $row['ward_category'];
    $catType = $row['category'];
    $dosage = strtolower($row['dosage']);
    $count = $row['usage_count'];

    $units = 0;
    if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
        $units = ($matches[1] / 1000) * $count;
    } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
        $units = $matches[1] * $count;
    }

    if ($units < 0) $units = 0;
    elseif ($units < 0.01) $units = 0;

    $dataMap[$category][$catType] = ($dataMap[$category][$catType] ?? 0) + $units;
}
$stmt->close();

/** Chart 3: Total usage by Ward Category **/
$usageTotals = [];
$stmt = $conn->prepare("SELECT ward_category, dosage, SUM(item_count) AS total_count FROM releases WHERE release_time BETWEEN ? AND ? GROUP BY ward_category, dosage");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $category = $row['ward_category'];
    $dosage = strtolower($row['dosage']);
    $count = $row['total_count'];

    $units = 0;
    if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
        $units = ($matches[1] / 1000) * $count;
    } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
        $units = $matches[1] * $count;
    }

    $usageTotals[$category] = ($usageTotals[$category] ?? 0) + $units;
}
$stmt->close();

// Calculate summary statistics
$totalUsage = array_sum($usageTotals);
$maxUsageWard = array_keys($usageTotals, max($usageTotals))[0] ?? 'N/A';
$maxUsageValue = max($usageTotals);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Antibiotic Usage Analytics Dashboard</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        .dashboard-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .chart-container {
            padding: 25px;
            margin-bottom: 0;
            overflow-x: auto;
        }
        .data-table-container {
            margin-top: 0;
            overflow-x: auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .chart-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.5rem;
            border-left: 5px solid #3498db;
            padding-left: 1rem;
            font-size: 1.4rem;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
        }
        .nav-tabs .nav-link {
            color: #2c3e50;
            border: none;
            margin-right: 5px;
            border-radius: 8px 8px 0 0;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link:hover {
            background: #ecf0f1;
            color: #2c3e50;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .summary-card:hover {
            transform: scale(1.02);
        }
        .summary-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .summary-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .summary-card.info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .stat-number {
            font-size: 2.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .stat-label {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 500;
        }
        .stat-subtext {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 0.5rem;
        }
        .filter-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 1px solid #e3e6f0;
        }
        .btn-export {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }
        .btn-print {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }
        .tab-content {
            background: #fff;
            border-radius: 0 0 15px 15px;
            padding: 0;
        }
        .nav-tabs {
            border-bottom: 2px solid #e3e6f0;
            padding: 0 25px;
            background: #f8f9fa;
            border-radius: 15px 15px 0 0;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .data-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .data-table th {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px;
        }
        .data-table td {
            padding: 12px 15px;
            border-color: #e3e6f0;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .data-table tr:hover {
            background-color: #e3f2fd;
        }
        .period-display {
            background: linear-gradient(135deg, #ffeaa7, #fab1a0);
            color: #2d3436;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>📊 Antibiotic Usage Analytics Dashboard</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                <li class="breadcrumb-item active">Usage Analytics</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="summary-card">
                    <div class="stat-number"><?= $totalUsage > 0 ? number_format($totalUsage, 2) : '0.00' ?>g</div>
                    <div class="stat-label">Total Antibiotic Usage</div>
                    <div class="stat-subtext">All Ward Categories</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="summary-card warning">
                    <div class="stat-number"><?= count($antibiotics) ?></div>
                    <div class="stat-label">Different Antibiotics</div>
                    <div class="stat-subtext">Across all wards</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="summary-card success">
                    <div class="stat-number"><?= count($wardCategories) ?></div>
                    <div class="stat-label">Ward Categories</div>
                    <div class="stat-subtext">Monitoring coverage</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="summary-card info">
                    <div class="stat-number"><?= $maxUsageValue > 0 ? number_format($maxUsageValue, 2) . 'g' : 'N/A' ?></div>
                    <div class="stat-label">Highest Usage</div>
                    <div class="stat-subtext"><?= $maxUsageWard ?></div>
                </div>
            </div>
        </div>

        <!-- Period Display -->
        <div class="period-display">
            📅 Reporting Period: <?= date('F Y', strtotime($startDate)) ?> to <?= date('F Y', strtotime($endDate)) ?>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="POST" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="start_year" class="form-label fw-bold">Start Year</label>
                    <select name="start_year" id="start_year" class="form-select">
                        <?php 
                        $currentYear = date('Y');
                        for ($y = $currentYear-5; $y <= $currentYear; $y++) {
                            $selected = ($y == intval($startYear)) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_month" class="form-label fw-bold">Start Month</label>
                    <select name="start_month" id="start_month" class="form-select">
                        <?php 
                        for ($m=1; $m<=12; $m++) {
                            $selected = ($m == intval($startMonth)) ? 'selected' : '';
                            echo "<option value='$m' $selected>".date('F', mktime(0,0,0,$m,1))."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="end_year" class="form-label fw-bold">End Year</label>
                    <select name="end_year" id="end_year" class="form-select">
                        <?php 
                        for ($y = $currentYear-5; $y <= $currentYear; $y++) {
                            $selected = ($y == intval($endYear)) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="end_month" class="form-label fw-bold">End Month</label>
                    <select name="end_month" id="end_month" class="form-select">
                        <?php 
                        for ($m=1; $m<=12; $m++) {
                            $selected = ($m == intval($endMonth)) ? 'selected' : '';
                            echo "<option value='$m' $selected>".date('F', mktime(0,0,0,$m,1))."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">🔍 Apply Filter</button>
                    <button type="button" onclick="window.print()" class="btn-print btn text-white flex-fill">🖨️ Print</button>
                    <button type="button" onclick="exportToExcel()" class="btn-export btn text-white flex-fill">📥 Export Excel</button>
                </div>
            </form>
        </div>

        <!-- Chart 1: Antibiotic Usage by Ward Category -->
        <div class="dashboard-card">
            <div class="card-body">
                <h5 class="chart-title">Antibiotic Usage by Ward Category (grams)</h5>
                <ul class="nav nav-tabs" id="chart1Tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="chart1-tab" data-bs-toggle="tab" data-bs-target="#chart1-chart" type="button">📊 Chart View</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="table1-tab" data-bs-toggle="tab" data-bs-target="#chart1-table" type="button">📋 Data Table</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="chart1-chart">
                        <div id="chart1" class="chart-container"></div>
                    </div>
                    <div class="tab-pane fade" id="chart1-table">
                        <div class="data-table-container">
                            <div class="table-responsive">
                                <table class="table table-striped data-table">
                                    <thead>
                                        <tr>
                                            <th>Ward Category</th>
                                            <?php foreach ($antibiotics as $antibiotic): ?>
                                                <th class="text-end"><?= htmlspecialchars($antibiotic) ?> (g)</th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($wardCategories as $wc): ?>
                                            <tr>
                                                <td><strong><?= $wc ?></strong></td>
                                                <?php foreach ($antibiotics as $a): 
                                                    $val = isset($antibioticData[$wc][$a]) ? round($antibioticData[$wc][$a], 2) : 0;
                                                ?>
                                                    <td class="text-end"><?= number_format($val, 2) ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 2: Category Usage by Ward Category -->
        <div class="dashboard-card">
            <div class="card-body">
                <h5 class="chart-title">WHO Antibiotic Category Usage by Ward (grams)</h5>
                <ul class="nav nav-tabs" id="chart2Tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="chart2-tab" data-bs-toggle="tab" data-bs-target="#chart2-chart" type="button">📊 Chart View</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="table2-tab" data-bs-toggle="tab" data-bs-target="#chart2-table" type="button">📋 Data Table</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="chart2-chart">
                        <div id="chart2" class="chart-container"></div>
                    </div>
                    <div class="tab-pane fade" id="chart2-table">
                        <div class="data-table-container">
                            <div class="table-responsive">
                                <table class="table table-striped data-table">
                                    <thead>
                                        <tr>
                                            <th>Ward Category</th>
                                            <th class="text-end">Access (g)</th>
                                            <th class="text-end">Watch (g)</th>
                                            <th class="text-end">Reserve (g)</th>
                                            <th class="text-end">Other (g)</th>
                                            <th class="text-end">Total (g)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($wardCategories as $wc): 
                                            $access = isset($dataMap[$wc]['Access']) ? round($dataMap[$wc]['Access'], 2) : 0;
                                            $watch = isset($dataMap[$wc]['Watch']) ? round($dataMap[$wc]['Watch'], 2) : 0;
                                            $reserve = isset($dataMap[$wc]['Reserve']) ? round($dataMap[$wc]['Reserve'], 2) : 0;
                                            $other = isset($dataMap[$wc]['Other']) ? round($dataMap[$wc]['Other'], 2) : 0;
                                            $total = $access + $watch + $reserve + $other;
                                        ?>
                                            <tr>
                                                <td><strong><?= $wc ?></strong></td>
                                                <td class="text-end"><?= number_format($access, 2) ?></td>
                                                <td class="text-end"><?= number_format($watch, 2) ?></td>
                                                <td class="text-end"><?= number_format($reserve, 2) ?></td>
                                                <td class="text-end"><?= number_format($other, 2) ?></td>
                                                <td class="text-end"><strong><?= number_format($total, 2) ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 3: Total Usage by Ward Category -->
        <div class="dashboard-card">
            <div class="card-body">
                <h5 class="chart-title">Total Antibiotic Usage by Ward Category (grams)</h5>
                <ul class="nav nav-tabs" id="chart3Tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="chart3-tab" data-bs-toggle="tab" data-bs-target="#chart3-chart" type="button">📊 Chart View</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="table3-tab" data-bs-toggle="tab" data-bs-target="#chart3-table" type="button">📋 Data Table</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="chart3-chart">
                        <div id="chart3" class="chart-container"></div>
                    </div>
                    <div class="tab-pane fade" id="chart3-table">
                        <div class="data-table-container">
                            <div class="table-responsive">
                                <table class="table table-striped data-table">
                                    <thead>
                                        <tr>
                                            <th>Ward Category</th>
                                            <th class="text-end">Total Usage (g)</th>
                                            <th class="text-end">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $grandTotal = array_sum($usageTotals);
                                        foreach ($wardCategories as $wc): 
                                            $val = isset($usageTotals[$wc]) ? round($usageTotals[$wc], 2) : 0;
                                            $percentage = $grandTotal > 0 ? ($val / $grandTotal) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td><strong><?= $wc ?></strong></td>
                                                <td class="text-end"><?= number_format($val, 2) ?></td>
                                                <td class="text-end"><?= number_format($percentage, 1) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-primary">
                                            <td><strong>TOTAL</strong></td>
                                            <td class="text-end"><strong><?= number_format($grandTotal, 2) ?></strong></td>
                                            <td class="text-end"><strong>100%</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script>
    google.charts.load("current", {packages:['corechart']});
    google.charts.setOnLoadCallback(drawAllCharts);

    function drawAllCharts() {
        drawChart1();
        drawChart2();
        drawChart3();
    }

    function drawChart1() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Ward Category');
        <?php foreach ($antibiotics as $a): ?>
            data.addColumn('number', '<?= addslashes($a) ?>');
        <?php endforeach; ?>

        data.addRows([
            <?php foreach ($wardCategories as $wc): ?>
                [
                    '<?= $wc ?>',
                    <?php foreach ($antibiotics as $a): 
                        $val = isset($antibioticData[$wc][$a]) ? round($antibioticData[$wc][$a], 2) : 0;
                    ?>
                    <?= $val ?>,
                    <?php endforeach; ?>
                ],
            <?php endforeach; ?>
        ]);

        var options = {
            title: 'Antibiotic Usage Distribution by Ward Category',
            vAxis: { title: 'Usage (grams)' },
            hAxis: { title: 'Ward Category' },
            isStacked: false,
            legend: { position: 'top', maxLines: 3 },
            height: 500,
            chartArea: { left: 80, right: 50, top: 80, bottom: 120 },
            bar: { groupWidth: '90%' },
            hAxis: {
                slantedText: true,
                slantedTextAngle: 45,
                textStyle: { fontSize: 12 }
            },
            colors: ['#3366cc', '#dc3912', '#ff9900', '#109618', '#990099', '#0099c6', '#dd4477', '#66aa00', '#b82e2e', '#316395']
        };

        var chart = new google.visualization.ColumnChart(document.getElementById('chart1'));
        chart.draw(data, options);
    }

    function drawChart2() {
        var data = google.visualization.arrayToDataTable([
            ['Ward Category', 'Access', 'Watch', 'Reserve', 'Other'],
            <?php foreach ($wardCategories as $wc): ?>
            ['<?= $wc ?>',
                <?= isset($dataMap[$wc]['Access']) ? round($dataMap[$wc]['Access'], 2) : 0 ?>,
                <?= isset($dataMap[$wc]['Watch']) ? round($dataMap[$wc]['Watch'], 2) : 0 ?>,
                <?= isset($dataMap[$wc]['Reserve']) ? round($dataMap[$wc]['Reserve'], 2) : 0 ?>,
                <?= isset($dataMap[$wc]['Other']) ? round($dataMap[$wc]['Other'], 2) : 0 ?>,
            ],
            <?php endforeach; ?>
        ]);

        var options = {
            title: 'WHO Antibiotic Category Usage Distribution',
            isStacked: true,
            height: 500,
            legend: { position: 'top' },
            hAxis: { title: 'Ward Category' },
            vAxis: { title: 'Usage (grams)' },
            colors: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
            chartArea: { left: 80, right: 50, top: 80, bottom: 80 }
        };

        var chart = new google.visualization.ColumnChart(document.getElementById('chart2'));
        chart.draw(data, options);
    }

    function drawChart3() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Ward Category');
        data.addColumn('number', 'Total Usage (g)');
        data.addColumn({type: 'string', role: 'annotation'});

        data.addRows([
            <?php 
            $maxVal = max($usageTotals);
            foreach ($wardCategories as $wc): 
                $val = isset($usageTotals[$wc]) ? round($usageTotals[$wc], 2) : 0;
            ?>
            ['<?= $wc ?>', <?= $val ?>, '<?= number_format($val, 1) ?>g'],
            <?php endforeach; ?>
        ]);

        var options = {
            title: 'Total Antibiotic Usage by Ward Category',
            hAxis: {title: 'Ward Category'},
            vAxis: {title: 'Total Usage (grams)'},
            height: 500,
            legend: 'none',
            chartArea: { left: 80, right: 50, top: 80, bottom: 80 },
            colors: ['#007bff'],
            annotations: {
                alwaysOutside: true,
                textStyle: {
                    fontSize: 12,
                    color: '#000',
                    bold: true
                }
            }
        };

        var chart = new google.visualization.ColumnChart(document.getElementById('chart3'));
        chart.draw(data, options);
    }

    function exportToExcel() {
        // Create a simple CSV export
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Add headers
        csvContent += "Antibiotic Usage Report\r\n";
        csvContent += "Period: <?= date('F Y', strtotime($startDate)) ?> to <?= date('F Y', strtotime($endDate)) ?>\r\n";
        csvContent += "Generated on: <?= date('Y-m-d H:i:s') ?>\r\n\r\n";
        
        // Chart 1 data
        csvContent += "ANTIBIOTIC USAGE BY WARD CATEGORY (grams)\r\n";
        csvContent += "Ward Category," + <?= json_encode($antibiotics) ?>.join(",") + "\r\n";
        <?php foreach ($wardCategories as $wc): ?>
        csvContent += "<?= $wc ?>";
        <?php foreach ($antibiotics as $a): ?>
        csvContent += ",<?= isset($antibioticData[$wc][$a]) ? round($antibioticData[$wc][$a], 2) : 0 ?>";
        <?php endforeach; ?>
        csvContent += "\r\n";
        <?php endforeach; ?>
        
        csvContent += "\r\nWHO CATEGORY USAGE BY WARD (grams)\r\n";
        csvContent += "Ward Category,Access,Watch,Reserve,Other,Total\r\n";
        <?php foreach ($wardCategories as $wc): 
            $access = isset($dataMap[$wc]['Access']) ? round($dataMap[$wc]['Access'], 2) : 0;
            $watch = isset($dataMap[$wc]['Watch']) ? round($dataMap[$wc]['Watch'], 2) : 0;
            $reserve = isset($dataMap[$wc]['Reserve']) ? round($dataMap[$wc]['Reserve'], 2) : 0;
            $other = isset($dataMap[$wc]['Other']) ? round($dataMap[$wc]['Other'], 2) : 0;
            $total = $access + $watch + $reserve + $other;
        ?>
        csvContent += "<?= $wc ?>,<?= $access ?>,<?= $watch ?>,<?= $reserve ?>,<?= $other ?>,<?= $total ?>\r\n";
        <?php endforeach; ?>
        
        csvContent += "\r\nTOTAL USAGE BY WARD CATEGORY (grams)\r\n";
        csvContent += "Ward Category,Total Usage,Percentage\r\n";
        <?php 
        $grandTotal = array_sum($usageTotals);
        foreach ($wardCategories as $wc): 
            $val = isset($usageTotals[$wc]) ? round($usageTotals[$wc], 2) : 0;
            $percentage = $grandTotal > 0 ? ($val / $grandTotal) * 100 : 0;
        ?>
        csvContent += "<?= $wc ?>,<?= $val ?>,<?= number_format($percentage, 1) ?>%\r\n";
        <?php endforeach; ?>
        csvContent += "TOTAL,<?= $grandTotal ?>,100%\r\n";
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "antibiotic_usage_report_<?= date('Y-m-d') ?>.csv");
        document.body.appendChild(link);
        link.click();
    }

    // Responsive charts on window resize
    window.addEventListener('resize', function() {
        drawAllCharts();
    });
</script>

<?php include_once("../includes/footer.php"); ?>
<?php include_once("../includes/js-links-inc.php"); ?>
</body>
</html>