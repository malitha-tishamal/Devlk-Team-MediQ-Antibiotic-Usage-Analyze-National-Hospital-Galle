<?php
session_start();
date_default_timezone_set('Asia/Colombo');

require_once '../includes/db-conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['admin_id'];
$sql = "SELECT name, email, nic, mobile, profile_picture FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Filter logic
$filterType = $_POST['filter_type'] ?? 'month';
$selectedYear = $_POST['year_select'] ?? date('Y');
$startMonth = $_POST['start_month_select'] ?? 1;
$endMonth = $_POST['end_month_select'] ?? 12;
$startDate = $_POST['start_date'] ?? date('Y-m-01');
$endDate = $_POST['end_date'] ?? date('Y-m-t');

// Handle monthly range filtering
if ($filterType === 'month') {
    $startDate = "$selectedYear-$startMonth-01";
    $endDate = date('Y-m-t', strtotime("$selectedYear-$endMonth-01"));
}

// Query: antibiotic usage by name/dosage
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

// Query: pie chart 1 (by antibiotic)
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

// Query: pie chart 2 (by category)
$categoryPieQuery = "
    SELECT COALESCE(category, 'Unknown') AS category, SUM(item_count) AS usage_count
    FROM releases
    WHERE release_time BETWEEN ? AND ?
    AND YEAR(release_time) = ?
    AND MONTH(release_time) BETWEEN ? AND ?
    GROUP BY category
";
$categoryPieStmt = $conn->prepare($categoryPieQuery);
$categoryPieStmt->bind_param("ssiii", $startDate, $endDate, $selectedYear, $startMonth, $endMonth);
$categoryPieStmt->execute();
$categoryPieResult = $categoryPieStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Antibiotic Usage Analytics - Mediq</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.1/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }
        
        .chart-container {
            width: 100%;
            height: 400px;
            margin: 15px auto;
            border-radius: 10px;
            background: white;
            box-shadow: var(--card-shadow);
            padding: 15px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filter-section {
            background: var(--light-bg);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px);
        }
        
        .card-body {
            padding: 25px;
        }
        
        .btn {
            border-radius: 6px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60, #219653);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .dataTables_wrapper {
            margin-top: 20px;
        }
        
        .dataTables_filter input {
            border-radius: 6px;
            border: 1px solid #ddd;
            padding: 8px 12px;
        }
        
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .chart-row {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: 1fr !important;
            }
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .dataTable {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .dataTable thead th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
        }
        
        .dataTable tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .progress {
            height: 8px;
            margin-top: 5px;
        }
        
        .percentage-badge {
            background: var(--secondary-color);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--secondary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <script>
        google.charts.load('current', { 'packages': ['corechart'] });
        google.charts.setOnLoadCallback(function () {
            drawAntibioticChart();
            drawCategoryChart();
        });

        function drawAntibioticChart() {
            var data = google.visualization.arrayToDataTable([
                ['Antibiotic', 'Usage in Units'],
                <?php
                $pieUsageQuery = "
                    SELECT antibiotic_name, dosage, SUM(item_count) AS usage_count
                    FROM releases
                    WHERE release_time BETWEEN ? AND ?
                    AND YEAR(release_time) = ?
                    AND MONTH(release_time) BETWEEN ? AND ?
                    GROUP BY antibiotic_name, dosage
                ";
                $pieUsageStmt = $conn->prepare($pieUsageQuery);
                $pieUsageStmt->bind_param("ssiii", $startDate, $endDate, $selectedYear, $startMonth, $endMonth);
                $pieUsageStmt->execute();
                $pieUsageResult = $pieUsageStmt->get_result();

                $pieData = [];
                $totalPieUnits = 0;

                while ($row = $pieUsageResult->fetch_assoc()) {
                    $name = $row['antibiotic_name'];
                    $dosage = strtolower($row['dosage']);
                    $count = $row['usage_count'];
                    $grams = 0;

                    if (preg_match('/(\d+(?:\.\d+)?)\s*mg/', $dosage, $m)) {
                        $grams = ($m[1] / 1000) * $count;
                    } elseif (preg_match('/(\d+(?:\.\d+)?)\s*g/', $dosage, $m)) {
                        $grams = $m[1] * $count;
                    }

                    if (!isset($pieData[$name])) {
                        $pieData[$name] = 0;
                    }
                    $pieData[$name] += $grams;
                    $totalPieUnits += $grams;
                }

                foreach ($pieData as $name => $units) {
                    echo "['" . addslashes($name) . "', " . round($units, 2) . "],\n";
                }
                ?>
            ]);

            var options = {
                title: 'Antibiotic Usage Distribution',
                titleTextStyle: {
                    fontSize: 18,
                    bold: true,
                    color: '#2c3e50'
                },
                pieHole: 0.4,
                fontSize: 14,
                chartArea: { width: '85%', height: '75%' },
                pieSliceText: 'percentage',
                tooltip: { 
                    text: 'percentage',
                    textStyle: { fontSize: 12 }
                },
                colors: ['#3498db', '#e74c3c', '#f1c40f', '#2ecc71', '#9b59b6', '#1abc9c', '#e67e22'],
                legend: {
                    position: 'labeled',
                    textStyle: {
                        fontSize: 12,
                        color: '#2c3e50'
                    }
                },
                backgroundColor: 'transparent'
            };

            var chart = new google.visualization.PieChart(document.getElementById('piechart'));
            chart.draw(data, options);
        }

        function drawCategoryChart() {
            var data = google.visualization.arrayToDataTable([
                ['Category', 'Usage Count'],
                <?php 
                $colorsMap = [];
                $categoryPieQuery = "
                    SELECT COALESCE(category, 'Unknown') AS category, dosage, SUM(item_count) AS usage_count
                    FROM releases
                    WHERE release_time BETWEEN ? AND ?
                    AND YEAR(release_time) = ?
                    AND MONTH(release_time) BETWEEN ? AND ?
                    GROUP BY category, dosage
                ";
                $catStmt = $conn->prepare($categoryPieQuery);
                $catStmt->bind_param("ssiii", $startDate, $endDate, $selectedYear, $startMonth, $endMonth);
                $catStmt->execute();
                $catResult = $catStmt->get_result();

                $categoryUnits = [];

                while ($row = $catResult->fetch_assoc()) {
                    $category = ucfirst(strtolower($row['category']));
                    $dosage = strtolower($row['dosage']);
                    $count = $row['usage_count'];
                    $grams = 0;

                    if (preg_match('/(\d+(?:\.\d+)?)\s*mg/', $dosage, $m)) {
                        $grams = ($m[1] / 1000) * $count;
                    } elseif (preg_match('/(\d+(?:\.\d+)?)\s*g/', $dosage, $m)) {
                        $grams = $m[1] * $count;
                    }

                    if (!isset($categoryUnits[$category])) {
                        $categoryUnits[$category] = 0;
                    }

                    $categoryUnits[$category] += $grams;
                }

                foreach ($categoryUnits as $category => $grams) {
                    echo "['" . addslashes($category) . "', " . round($grams, 2) . "],";
                    $colorsMap[$category] = match (strtolower($category)) {
                        'access' => '#28a745',
                        'watch' => '#3498db',
                        'reserve' => '#e74c3c',
                        'other' => '#95a5a6',
                        default => '#f39c12',
                    };
                }
                ?>
            ]);

            var options = {
                title: 'Usage by Category',
                titleTextStyle: {
                    fontSize: 18,
                    bold: true,
                    color: '#2c3e50'
                },
                pieHole: 0.4,
                fontSize: 14,
                chartArea: { width: '85%', height: '75%' },
                pieSliceText: 'percentage',
                tooltip: { 
                    text: 'percentage',
                    textStyle: { fontSize: 12 }
                },
                colors: [<?php echo '"' . implode('","', $colorsMap) . '"'; ?>],
                legend: {
                    position: 'labeled',
                    textStyle: {
                        fontSize: 12,
                        color: '#2c3e50'
                    }
                },
                backgroundColor: 'transparent'
            };

            var chart = new google.visualization.PieChart(document.getElementById('categoryPieChart'));
            chart.draw(data, options);
        }
        
        function showLoading(message = 'Processing...') {
            document.getElementById('loadingMessage').textContent = message;
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    </script>
</head>

<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Antibiotic Usage Analytics</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                <li class="breadcrumb-item active">Usage Analytics</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="loading-overlay">
            <div class="text-center">
                <div class="loading-spinner"></div>
                <div id="loadingMessage">Processing...</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Filter Data</h5>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Select Year:</label>
                            <select name="year_select" class="form-select">
                                <?php for ($i = 2020; $i <= date('Y'); $i++): ?>
                                    <option value="<?= $i ?>" <?= ($i == $selectedYear) ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Month:</label>
                            <select name="start_month_select" class="form-select">
                                <?php foreach (range(1, 12) as $m): ?>
                                    <option value="<?= $m ?>" <?= ($m == $startMonth) ? 'selected' : '' ?>><?= date("F", mktime(0, 0, 0, $m, 10)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Month:</label>
                            <select name="end_month_select" class="form-select">
                                <?php foreach (range(1, 12) as $m): ?>
                                    <option value="<?= $m ?>" <?= ($m == $endMonth) ? 'selected' : '' ?>><?= date("F", mktime(0, 0, 0, $m, 10)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel"></i> Apply Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <?php
        // Calculate statistics
        $totalAntibiotics = count($pieData);
        $totalCategories = count($categoryUnits);
        $totalUsage = array_sum($pieData);
        $avgUsage = $totalAntibiotics > 0 ? $totalUsage / $totalAntibiotics : 0;
        ?>
        
        <div class="stats-container">
            <div class="stats-card">
                <div class="stat-label">Total Antibiotics</div>
                <div class="stat-number"><?= $totalAntibiotics ?></div>
                <small>Tracked in system</small>
            </div>
            <div class="stats-card">
                <div class="stat-label">Categories</div>
                <div class="stat-number"><?= $totalCategories ?></div>
                <small>Usage classification</small>
            </div>
            <div class="stats-card">
                <div class="stat-label">Total Usage</div>
                <div class="stat-number"><?= number_format($totalUsage, 2) ?></div>
                <small>Units (grams)</small>
            </div>
            <div class="stats-card">
                <div class="stat-label">Avg per Antibiotic</div>
                <div class="stat-number"><?= number_format($avgUsage, 2) ?></div>
                <small>Units (grams)</small>
            </div>
        </div>

        <!-- Export Buttons -->
        <div class="export-buttons">
            <button onclick="exportToExcel()" class="btn btn-success">
                <i class="bi bi-file-earmark-excel"></i> Export to Excel
            </button>
            <button onclick="downloadPDF()" class="btn btn-danger">
                <i class="bi bi-file-pdf"></i> Export to PDF
            </button>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>

        <!-- Charts Section -->
        <div class="chart-row">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Antibiotic Usage Distribution</h5>
                    <div id="piechart" class="chart-container"></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Usage by Category</h5>
                    <div id="categoryPieChart" class="chart-container"></div>
                </div>
            </div>
        </div>

        <!-- Data Table Section -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Detailed Usage Data</h5>
                
                <div class="table-responsive">
                    <table id="antibioticTable" class="table table-striped" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Antibiotic</th>
                                <th>Dosage</th>
                                <th>Count</th>
                                <th>Usage (g)</th>
                                <th>Units</th>
                                <th>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 1;
                            $totalUnits = 0;

                            // First pass: calculate total grams/units
                            $tempData = [];
                            mysqli_data_seek($result, 0); // Reset result pointer
                            while ($row = $result->fetch_assoc()) {
                                $dosage = strtolower($row['dosage']);
                                $itemCount = $row['usage_count'];
                                $usageInGrams = 0;

                                if (preg_match('/(\d+(?:\.\d+)?)\s*mg/', $dosage, $matches)) {
                                    $mgValue = (float)$matches[1];
                                    $usageInGrams = ($mgValue / 1000) * $itemCount;
                                } elseif (preg_match('/(\d+(?:\.\d+)?)\s*g/', $dosage, $matches)) {
                                    $gValue = (float)$matches[1];
                                    $usageInGrams = $gValue * $itemCount;
                                }

                                $tempData[] = [
                                    'antibiotic_name' => $row['antibiotic_name'],
                                    'dosage' => $dosage,
                                    'count' => $itemCount,
                                    'grams' => $usageInGrams,
                                    'units' => $usageInGrams
                                ];

                                $totalUnits += $usageInGrams;
                            }

                            // Display table with percentage
                            foreach ($tempData as $row) {
                                $percentage = ($totalUnits > 0) ? ($row['units'] / $totalUnits) * 100 : 0;
                            ?>
                            <tr>
                                <td><?= $count++ ?></td>
                                <td><strong><?= htmlspecialchars($row['antibiotic_name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['dosage']) ?></td>
                                <td><?= number_format($row['count']) ?></td>
                                <td><?= number_format($row['grams'], 2) ?> g</td>
                                <td><?= number_format($row['units'], 2) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="percentage-badge me-2"><?= number_format($percentage, 2) ?>%</span>
                                        <div class="progress flex-grow-1" style="width: 100px;">
                                            <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                <td><strong><?= number_format($totalUnits, 2) ?> g</strong></td>
                                <td><strong><?= number_format($totalUnits, 2) ?></strong></td>
                                <td><strong>100%</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include_once("../includes/footer.php"); ?>
<?php include_once ("../includes/js-links-inc.php") ?>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#antibioticTable').DataTable({
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "order": [[5, "desc"]], // Sort by Units descending
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "language": {
                "search": "Search records:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoEmpty": "Showing 0 to 0 of 0 entries",
                "infoFiltered": "(filtered from _MAX_ total entries)"
            }
        });
    });

    function exportToExcel() {
        showLoading('Generating Excel report...');
        
        // Create a new workbook
        var wb = XLSX.utils.book_new();

        // 1. Table Data Sheet
        var table = document.getElementById("antibioticTable");
        var ws1 = XLSX.utils.table_to_sheet(table);
        XLSX.utils.book_append_sheet(wb, ws1, "Antibiotic Usage");

        // 2. Pie Chart 1 Data (Antibiotic Usage by name)
        var pieData = [
            ["Antibiotic", "Usage in Units"]
            <?php
            foreach ($pieData as $name => $units) {
                echo ", [\"". addslashes($name) ."\", ". round($units, 2) ."]";
            }
            ?>
        ];
        var ws2 = XLSX.utils.aoa_to_sheet(pieData);
        XLSX.utils.book_append_sheet(wb, ws2, "Usage by Antibiotic");

        // 3. Pie Chart 2 Data (Category Usage)
        var categoryData = [
            ["Category", "Usage in Units"]
            <?php
            foreach ($categoryUnits as $category => $grams) {
                echo ", [\"". addslashes($category) ."\", ". round($grams, 2) ."]";
            }
            ?>
        ];
        var ws3 = XLSX.utils.aoa_to_sheet(categoryData);
        XLSX.utils.book_append_sheet(wb, ws3, "Usage by Category");

        // Download
        XLSX.writeFile(wb, "antibiotic_usage_report_<?= $selectedYear . '_' . $startMonth . '_' . $endMonth ?>.xlsx");
        
        setTimeout(hideLoading, 1000);
    }

    async function downloadPDF() {
        showLoading('Generating PDF report...');
        
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');

        const chart1 = document.getElementById('piechart');
        const chart2 = document.getElementById('categoryPieChart');

        // Convert charts to canvas and then to image
        const canvas1 = await html2canvas(chart1);
        const canvas2 = await html2canvas(chart2);

        const imgData1 = canvas1.toDataURL('image/png');
        const imgData2 = canvas2.toDataURL('image/png');

        const pageWidth = doc.internal.pageSize.getWidth();
        const chartWidth = (pageWidth - 60) / 2;

        // Add header
        doc.setFontSize(20);
        doc.setTextColor(44, 62, 80);
        doc.text("Antibiotic Usage Report", pageWidth / 2, 40, { align: 'center' });
        
        doc.setFontSize(12);
        doc.setTextColor(100, 100, 100);
        doc.text("Period: <?= date('F Y', strtotime("$selectedYear-$startMonth-01")) ?> - <?= date('F Y', strtotime("$selectedYear-$endMonth-01")) ?>", pageWidth / 2, 60, { align: 'center' });

        // Add chart 1
        doc.text("Antibiotic Usage Distribution", 40, 90);
        doc.addImage(imgData1, 'PNG', 40, 100, chartWidth, 200);

        // Add chart 2
        doc.text("Usage by Category", pageWidth / 2 + 20, 90);
        doc.addImage(imgData2, 'PNG', pageWidth / 2 + 20, 100, chartWidth, 200);

        // Add table on new page
        doc.addPage();
        doc.setFontSize(16);
        doc.setTextColor(44, 62, 80);
        doc.text("Detailed Usage Data", 40, 40);

        doc.autoTable({
            html: '#antibioticTable',
            startY: 60,
            styles: { 
                fontSize: 9,
                cellPadding: 3
            },
            headStyles: {
                fillColor: [44, 62, 80],
                textColor: 255,
                fontStyle: 'bold'
            },
            alternateRowStyles: {
                fillColor: [248, 249, 250]
            },
            theme: 'grid'
        });

        // Add footer
        const pageCount = doc.internal.getNumberOfPages();
        for(let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(10);
            doc.setTextColor(150, 150, 150);
            doc.text(`Page ${i} of ${pageCount}`, pageWidth - 40, doc.internal.pageSize.getHeight() - 20, { align: 'right' });
            doc.text("Generated on: <?= date('Y-m-d H:i:s') ?>", 40, doc.internal.pageSize.getHeight() - 20);
        }

        doc.save("antibiotic_usage_report_<?= $selectedYear . '_' . $startMonth . '_' . $endMonth ?>.pdf");
        
        hideLoading();
    }

    // Handle window resize for charts
    window.addEventListener('resize', function() {
        google.charts.setOnLoadCallback(function () {
            drawAntibioticChart();
            drawCategoryChart();
        });
    });
</script>
</body>
</html>