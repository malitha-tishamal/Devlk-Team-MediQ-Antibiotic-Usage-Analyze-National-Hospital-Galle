<?php
session_start();
date_default_timezone_set('Asia/Colombo');
require_once '../includes/db-conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = (int)$_SESSION['admin_id'];


try {
    $stmt = $conn->prepare("SELECT name, email, nic, mobile, profile_picture FROM admins WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("User data fetch error: " . $e->getMessage());
    $user = ['name' => 'Admin', 'email' => '', 'nic' => '', 'mobile' => '', 'profile_picture' => ''];
}

// Sanitize and validate input parameters
$startMonth = isset($_POST['start_month']) ? preg_replace('/[^0-9]/', '', $_POST['start_month']) : date('m');
$startYear = isset($_POST['start_year']) ? preg_replace('/[^0-9]/', '', $_POST['start_year']) : date('Y');
$endMonth = isset($_POST['end_month']) ? preg_replace('/[^0-9]/', '', $_POST['end_month']) : date('m');
$endYear = isset($_POST['end_year']) ? preg_replace('/[^0-9]/', '', $_POST['end_year']) : date('Y');
$selectedAntibiotic = isset($_POST['antibiotic_name']) ? trim($conn->real_escape_string($_POST['antibiotic_name'])) : '';

// Validate date range
if (!checkdate($startMonth, 1, $startYear) || !checkdate($endMonth, 1, $endYear)) {
    $startMonth = date('m');
    $startYear = date('Y');
    $endMonth = date('m');
    $endYear = date('Y');
}

$startDate = date('Y-m-01', strtotime("$startYear-$startMonth-01"));
$endDate = date('Y-m-t', strtotime("$endYear-$endMonth-01"));

// Ensure start date is before end date
if ($startDate > $endDate) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
    
    // Also swap the month/year values
    list($startMonth, $startYear, $endMonth, $endYear) = [$endMonth, $endYear, $startMonth, $startYear];
}

// Fetch antibiotic list with error handling
$antibioticList = [];
try {
    $antibioticRes = $conn->query("SELECT DISTINCT antibiotic_name FROM releases WHERE antibiotic_name IS NOT NULL AND antibiotic_name != '' ORDER BY antibiotic_name ASC");
    if ($antibioticRes) {
        while ($a = $antibioticRes->fetch_assoc()) {
            $antibioticList[] = htmlspecialchars($a['antibiotic_name']);
        }
    }
} catch (Exception $e) {
    error_log("Antibiotic list fetch error: " . $e->getMessage());
}

/** Chart 1: Antibiotic-wise by Ward - IMPROVED **/
$antibioticData = [];
$wards1 = [];
$hasChart1Data = false;

// Store detailed data for the table
$detailedTableData = [];

try {
    $query1 = "
        SELECT ward_name, antibiotic_name, dosage, SUM(item_count) AS usage_count
        FROM releases
        WHERE release_time BETWEEN ? AND ?
    ";
    $params1 = [$startDate, $endDate];
    $types1 = "ss";

    if (!empty($selectedAntibiotic)) {
        $query1 .= " AND antibiotic_name = ?";
        $params1[] = $selectedAntibiotic;
        $types1 .= "s";
    }

    $query1 .= " GROUP BY ward_name, antibiotic_name, dosage ORDER BY ward_name, antibiotic_name";

    $stmt = $conn->prepare($query1);
    if ($stmt) {
        $stmt->bind_param($types1, ...$params1);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $hasChart1Data = true;
            $ward = htmlspecialchars($row['ward_name']);
            $antibiotic = htmlspecialchars($row['antibiotic_name']);
            $dosage = strtolower($row['dosage']);
            $count = (int)$row['usage_count'];

            if (!in_array($ward, $wards1)) {
                $wards1[] = $ward;
            }

            // Improved dosage conversion with more patterns
            $units = 0;
            $conversion_note = '';
            if (preg_match('/(\d+\.?\d*)\s*mg/', $dosage, $matches)) {
                $units = ((float)$matches[1] / 1000) * $count;
                $conversion_note = "({$count} × {$matches[1]}mg = " . round($units, 3) . "g)";
            } elseif (preg_match('/(\d+\.?\d*)\s*g/', $dosage, $matches)) {
                $units = (float)$matches[1] * $count;
                $conversion_note = "({$count} × {$matches[1]}g = " . round($units, 3) . "g)";
            } elseif (preg_match('/(\d+\.?\d*)\s*ml/', $dosage, $matches)) {
                // Assuming 1ml ≈ 1g for liquid antibiotics (adjust if needed)
                $units = (float)$matches[1] * $count;
                $conversion_note = "({$count} × {$matches[1]}ml = " . round($units, 3) . "g)";
            } else {
                // Default fallback - count as units if no dosage pattern matched
                $units = $count;
                $conversion_note = "({$count} units)";
            }

            $antibioticData[$ward] = ($antibioticData[$ward] ?? 0) + $units;
            
            // Store detailed data for table
            if (!isset($detailedTableData[$ward])) {
                $detailedTableData[$ward] = [];
            }
            $detailedTableData[$ward][] = [
                'antibiotic' => $antibiotic,
                'dosage' => $dosage,
                'count' => $count,
                'units' => $units,
                'conversion_note' => $conversion_note
            ];
        }
        sort($wards1);
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Chart 1 data fetch error: " . $e->getMessage());
}

// Sort antibiotic data by usage (descending) for better chart display
arsort($antibioticData);
$sortedWards = array_keys($antibioticData);

/** Chart 2: Category-wise by Ward - IMPROVED **/
$categoryColors = [
    'Access' => '#28a745', 
    'Watch' => '#ffc107', 
    'Reserve' => '#dc3545',
    'Other' => '#6c757d'
];

$categories = [];
$dataMap = [];
$wards2 = [];
$hasChart2Data = false;

try {
    // Get unique categories
    $catResult = $conn->query("SELECT DISTINCT category FROM releases WHERE category IS NOT NULL AND category != ''");
    if ($catResult) {
        while ($catRow = $catResult->fetch_assoc()) {
            $categories[] = htmlspecialchars($catRow['category']);
        }
    }
    
    if (empty($categories)) {
        $categories = ['Other'];
    }
    
    sort($categories);
    $colorList = array_map(fn($c) => $categoryColors[$c] ?? $categoryColors['Other'], $categories);

    // Get category data
    $query2 = "
        SELECT ward_name, category, dosage, SUM(item_count) AS usage_count
        FROM releases
        WHERE release_time BETWEEN ? AND ?
        GROUP BY ward_name, category, dosage 
        ORDER BY ward_name, category
    ";

    $stmt = $conn->prepare($query2);
    if ($stmt) {
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $hasChart2Data = true;
            $ward = htmlspecialchars($row['ward_name']);
            $category = htmlspecialchars($row['category']) ?: 'Other';
            $dosage = strtolower($row['dosage']);
            $count = (int)$row['usage_count'];

            if (!in_array($ward, $wards2)) {
                $wards2[] = $ward;
            }

            // Same improved dosage conversion as chart 1
            $units = 0;
            if (preg_match('/(\d+\.?\d*)\s*mg/', $dosage, $matches)) {
                $units = ((float)$matches[1] / 1000) * $count;
            } elseif (preg_match('/(\d+\.?\d*)\s*g/', $dosage, $matches)) {
                $units = (float)$matches[1] * $count;
            } elseif (preg_match('/(\d+\.?\d*)\s*ml/', $dosage, $matches)) {
                $units = (float)$matches[1] * $count;
            } else {
                $units = $count;
            }

            $dataMap[$ward][$category] = ($dataMap[$ward][$category] ?? 0) + $units;
        }
        sort($wards2);
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Chart 2 data fetch error: " . $e->getMessage());
}

// Calculate dynamic chart widths with reasonable limits
$chart1Width = min(2000, max(800, count($wards1) * 80));
$chart2Width = min(2000, max(800, count($wards2) * 100));

// Generate chart title with proper formatting
$chartTitle1 = "Antibiotic Usage by Ward - " . date('M Y', strtotime($startDate)) . " to " . date('M Y', strtotime($endDate));
if (!empty($selectedAntibiotic)) {
    $chartTitle1 .= " | " . htmlspecialchars($selectedAntibiotic);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antibiotic Usage Dashboard</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
   <script>
google.charts.load("current", {packages: ['corechart']});
google.charts.setOnLoadCallback(drawCharts);

function drawCharts() {
    drawChart1();
    <?php if ($hasChart2Data): ?>
    drawChart2();
    <?php endif; ?>
}

function drawChart1() {
    var rawData = [
        <?php foreach ($sortedWards as $ward): ?>
            ['<?= addslashes($ward) ?>', <?= round($antibioticData[$ward] ?? 0, 2) ?>],
        <?php endforeach; ?>
    ];

    var dataArray = [
        ['Ward', '<?= addslashes($selectedAntibiotic ?: 'Total Usage') ?>', { role: 'style' }]
    ];
    
    // Color bars based on usage levels
    rawData.forEach(function(row) {
        var color = '#4285f4';
        if (row[1] > 1000) color = '#ea4335';
        else if (row[1] > 500) color = '#fbbc05';
        
        dataArray.push([row[0], row[1], color]);
    });

    var data = google.visualization.arrayToDataTable(dataArray);

    var options = {
        title: '<?= $chartTitle1 ?>',
        titleTextStyle: { fontSize: 18, bold: true },
        hAxis: {
            title: 'Ward',
            slantedText: true,
            slantedTextAngle: 75,
            textStyle: { 
                fontSize: 11, 
                fontName: 'Arial'
            }
        },
        vAxis: { 
            title: 'Units (grams)',
            minValue: 0,
            format: '#,##0.00',
            textStyle: { fontSize: 11 }
        },
        legend: { position: 'none' },
        height: 500, // Reduced height since no annotations
        chartArea: {
            left: 80,
            right: 50,
            top: 120,
            bottom: 150, // Reduced bottom margin
            width: '90%',
            height: '75%'
        },
        bar: { groupWidth: '60%' },
        // REMOVED annotations configuration completely
        tooltip: { 
            textStyle: { fontSize: 12 },
            showColorCode: true,
            isHtml: true
        }
    };

    var chart = new google.visualization.ColumnChart(document.getElementById('chart1'));
    chart.draw(data, options);

    // Enhanced tooltip with more information
    google.visualization.events.addListener(chart, 'ready', function() {
        // Add custom hover effects if needed
        var bars = document.querySelectorAll('#chart1 rect');
        bars.forEach(function(bar) {
            bar.style.cursor = 'pointer';
        });
    });

    // Redraw on window resize with debounce
    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            chart.draw(data, options);
        }, 250);
    });
}

// Export functions remain the same...
function exportChartData() {
    const data = {
        startDate: '<?= $startDate ?>',
        endDate: '<?= $endDate ?>',
        antibiotic: '<?= $selectedAntibiotic ?>',
        chart1Data: <?= json_encode($antibioticData) ?>,
        chart2Data: <?= json_encode($dataMap) ?>,
        detailedTableData: <?= json_encode($detailedTableData) ?>
    };
    
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(data, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", "antibiotic_usage_<?= $startDate ?>_to_<?= $endDate ?>.json");
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
}

function exportTableToCSV() {
    const rows = [];
    // Add headers
    rows.push(['Ward', 'Antibiotic', 'Dosage', 'Item Count', 'Total Grams', 'Conversion Details']);
    
    // Add data rows
    <?php foreach ($detailedTableData as $ward => $antibiotics): ?>
        <?php foreach ($antibiotics as $item): ?>
            rows.push([
                '<?= $ward ?>',
                '<?= $item['antibiotic'] ?>',
                '<?= $item['dosage'] ?>',
                '<?= $item['count'] ?>',
                '<?= round($item['units'], 3) ?>',
                '<?= $item['conversion_note'] ?>'
            ]);
        <?php endforeach; ?>
    <?php endforeach; ?>
    
    const csvContent = "data:text/csv;charset=utf-8," + rows.map(e => e.map(field => `"${field}"`).join(",")).join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "antibiotic_usage_details_<?= $startDate ?>_to_<?= $endDate ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Function to show values on hover (optional enhancement)
function enableHoverValues() {
    const chartDiv = document.getElementById('chart1');
    const bars = chartDiv.querySelectorAll('rect');
    
    bars.forEach((bar, index) => {
        bar.addEventListener('mouseover', function(e) {
            // You can implement custom tooltip here if needed
            console.log('Value:', rawData[index][1]);
        });
    });
}
</script>
    <style>
        .chart-container { 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        .stats-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .export-buttons {
            margin-bottom: 20px;
        }
        .filter-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .data-table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        
        .usage-badge {
            font-size: 0.85em;
            padding: 4px 8px;
        }
        @media print {
            .no-print { display: none; }
            .chart-container { box-shadow: none; }
            .data-table-container { box-shadow: none; }
        }
    </style>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Antibiotic Usage Analytics</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Antibiotic Analytics</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Wards</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= count($wards1) ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Time Period</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-calendar"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= date('M Y', strtotime($startDate)) ?> - <?= date('M Y', strtotime($endDate)) ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Usage</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-capsule"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= round(array_sum($antibioticData), 2) ?>g</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label for="antibiotic_name" class="form-label">Antibiotic</label>
                    <select name="antibiotic_name" id="antibiotic_name" class="form-select">
                        <option value="">-- All Antibiotics --</option>
                        <?php foreach ($antibioticList as $a): 
                            $sel = ($a == $selectedAntibiotic) ? 'selected' : '';
                        ?>
                            <option value="<?= $a ?>" <?= $sel ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="start_year" class="form-label">Start Year</label>
                    <select name="start_year" id="start_year" class="form-select">
                        <?php for ($y = 2020; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $startYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_month" class="form-label">Start Month</label>
                    <select name="start_month" id="start_month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): 
                            $val = str_pad($m, 2, '0', STR_PAD_LEFT);
                            $monthName = date('F', mktime(0, 0, 0, $m, 1));
                        ?>
                            <option value="<?= $val ?>" <?= $startMonth == $val ? 'selected' : '' ?>>
                                <?= $monthName ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="end_year" class="form-label">End Year</label>
                    <select name="end_year" id="end_year" class="form-select">
                        <?php for ($y = 2020; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $endYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="end_month" class="form-label">End Month</label>
                    <select name="end_month" id="end_month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): 
                            $val = str_pad($m, 2, '0', STR_PAD_LEFT);
                            $monthName = date('F', mktime(0, 0, 0, $m, 1));
                        ?>
                            <option value="<?= $val ?>" <?= $endMonth == $val ? 'selected' : '' ?>>
                                <?= $monthName ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2 no-print">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="bi bi-printer"></i> Print Report
                    </button>
                    <button type="button" onclick="exportChartData()" class="btn btn-success">
                        <i class="bi bi-download"></i> Export JSON
                    </button>
                    <button type="button" onclick="exportTableToCSV()" class="btn btn-info">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                    </button>
                    <a href="?reset=1" class="btn btn-outline-danger">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Chart 1 -->
        <div class="chart-container">
            <h5 class="card-title text-center mb-4">Antibiotic Usage by Ward</h5>
            <?php if ($hasChart1Data): ?>
                <div id="chart1" style="width: 100%; min-height: 600px;"></div>
            <?php else: ?>
                <div class="no-data">
                    <i class="bi bi-bar-chart" style="font-size: 3rem;"></i>
                    <h4>No Data Available</h4>
                    <p>No antibiotic usage data found for the selected criteria.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Data Table -->
        <?php if ($hasChart1Data): ?>
        <div class="data-table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Detailed Usage Data</h5>
                <span class="badge bg-primary usage-badge">
                    Total: <?= round(array_sum($antibioticData), 2) ?>g
                </span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Ward</th>
                            <th>Antibiotic</th>
                            <th>Dosage</th>
                            <th>Item Count</th>
                            <th>Total Grams (1g = 1 Unit)</th>
                           
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailedTableData as $ward => $antibiotics): ?>
                            <?php 
                            $wardTotal = 0;
                            foreach ($antibiotics as $item) {
                                $wardTotal += $item['units'];
                            }
                            ?>
                            <?php foreach ($antibiotics as $index => $item): ?>
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td rowspan="<?= count($antibiotics) ?>" class="fw-bold">
                                            <?= $ward ?>
                                            <br><small class="text-muted">Total: <?= round($wardTotal, 2) ?>g</small>
                                        </td>
                                    <?php endif; ?>
                                    <td><?= $item['antibiotic'] ?></td>
                                    <td><?= $item['dosage'] ?></td>
                                    <td class="text-end"><?= $item['count'] ?></td>
                                    <td class="text-end fw-bold"><?= round($item['units'], 3) ?>g</td>
                                 
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Grand Total:</td>
                            <td class="text-end fw-bold"><?= round(array_sum($antibioticData), 2) ?>g</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </section>
</main>

<?php include_once("../includes/js-links-inc.php"); ?>
<?php include_once("../includes/footer.php"); ?>
</body>
</html>