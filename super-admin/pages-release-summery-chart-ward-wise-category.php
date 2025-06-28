<?php 
session_start();
date_default_timezone_set('Asia/Colombo'); // Set timezone to Sri Lanka

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

// Get filter inputs or default to current month/year
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
    'Watch' => '#0000ff',
    'Reserve' => '#dc3545',
    'Other' => '#6c757d' // gray for "Other"
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

// Optional: output Sri Lanka time (for logging or user display)
$currentSriLankaTime = date('Y-m-d H:i:s');

// Optional Debugging Output
// echo "Current Sri Lanka Time: $currentSriLankaTime";

$chart1Width = max(6000, count($wardCategories) * 100);
$chart2Width = max(1500, count($wardCategories) * 100);
$chart3Width = max(1500, count($wardCategories) * 100);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Antibiotic Usage by Ward Category</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
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
        // data.addColumn({type: 'string', role: 'annotation'});
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
        vAxis: { title: 'Units (g)' },
        isStacked: false,
        legend: { position: 'top' },
        height: 800,
        chartArea: { left: 150, right: 50, top: 60, bottom: 180 },
        bar: { groupWidth: '40%' },
        annotations: {
            alwaysOutside: false,  // annotation labels දැක්කාට ගැටළුවක් නෑ නම් false කරන්න
            textStyle: { fontSize: 12, color: '#000', auraColor: 'none' }
        },
        hAxis: {
            slantedText: true,
            slantedTextAngle: 50,
            textStyle: { fontSize: 12, color: '#000', auraColor: 'none' }
        }
    };

    new google.visualization.ColumnChart(document.getElementById('chart1')).draw(data, options);
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
                title: 'Chart 2: Category Usage by Ward Category (Stacked)',
                isStacked: true,
                height: 500,
                legend: { position: 'top' },
                hAxis: { title: 'Ward Category' },
                vAxis: { title: 'Units (g)' },
               colors: ['#28a745', '#0000ff', '#dc3545', '#6c757d']
            };

            new google.visualization.ColumnChart(document.getElementById('chart2')).draw(data, options);
        }

        function drawChart3() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Ward Category');
    data.addColumn('number', 'Total Usage (g)');
    data.addColumn({type: 'string', role: 'annotation'});  // annotation column

    data.addRows([
        <?php foreach ($wardCategories as $wc): 
            $val = isset($usageTotals[$wc]) ? round($usageTotals[$wc], 2) : 0;
        ?>
        ['<?= $wc ?>', <?= $val ?>, '<?= $val ?>'],
        <?php endforeach; ?>
    ]);

    var options = {
        title: 'Chart 3: Total Usage by Ward Category',
        hAxis: {title: 'Ward Category'},
        vAxis: {title: 'Total Usage (g)'},
        height: 800,
        legend: 'none',
        annotations: {
            alwaysOutside: true,
            textStyle: {
                fontSize: 15,
                color: '#000',
                auraColor: 'none'
            }
        }
    };

    new google.visualization.ColumnChart(document.getElementById('chart3')).draw(data, options);
}

    </script>
    <style>
        #chart1.chart-container { width: <?= $chart1Width ?>px; margin: 10px auto; }
        #chart2.chart-container { width: <?= $chart2Width ?>px; margin: 10px auto; }
        #chart3.chart-container { width: <?= $chart3Width ?>px; margin: 10px auto; }
    </style>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Antibiotic Usage by Ward Category</h1>
    </div>

    <section class="section">
        <form method="POST" class="row g-3 mb-4">
            
            <div class="col-md-3">
                <label for="start_year" class="form-label">Start Year</label>
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
            <div class="col-md-3">
                <label for="start_month" class="form-label">Start Month</label>
                <select name="start_month" id="start_month" class="form-select">
                    <?php 
                    for ($m=1; $m<=12; $m++) {
                        $selected = ($m == intval($startMonth)) ? 'selected' : '';
                        echo "<option value='$m' $selected>".date('F', mktime(0,0,0,$m,1))."</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="end_year" class="form-label">End Year</label>
                <select name="end_year" id="end_year" class="form-select">
                    <?php 
                    for ($y = $currentYear-5; $y <= $currentYear; $y++) {
                        $selected = ($y == intval($endYear)) ? 'selected' : '';
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="end_month" class="form-label">End Month</label>
                <select name="end_month" id="end_month" class="form-select">
                    <?php 
                    for ($m=1; $m<=12; $m++) {
                        $selected = ($m == intval($endMonth)) ? 'selected' : '';
                        echo "<option value='$m' $selected>".date('F', mktime(0,0,0,$m,1))."</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Filter</button>
                 <button onclick="window.print()" class="btn btn-danger">Print</button>
            </div>
        </form>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title text-center">Chart 1: Antibiotic Usage by Ward Category</h5>
                <div id="chart1" class="chart-container"></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title text-center">Chart 2: Category Usage by Ward Category</h5>
                <div id="chart2" class="chart-container"></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title text-center">Chart 3: Total Usage by Ward Category</h5>
                <div id="chart3" class="chart-container"></div>
            </div>
        </div>
    </section>
</main>
<?php include_once("../includes/footer.php"); ?>
<?php include_once("../includes/js-links-inc.php"); ?>
</body>
</html>
