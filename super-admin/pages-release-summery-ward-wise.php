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

// Determine which filter method is being used
$filterType = $_POST['filter_type'] ?? 'month';

// Get date range values
$startDate = $_POST['start_date'] ?? date('Y-m-01');
$endDate = $_POST['end_date'] ?? date('Y-m-t');

// Get month range values
$startMonth = $_POST['start_month'] ?? date('m');
$startYear = $_POST['start_year'] ?? date('Y');
$endMonth = $_POST['end_month'] ?? date('m');
$endYear = $_POST['end_year'] ?? date('Y');

// Calculate date ranges for month selection if that filter is used
if ($filterType == 'month') {
    $startDate = date('Y-m-01', strtotime("$startYear-$startMonth-01"));
    $endDate = date('Y-m-t', strtotime("$endYear-$endMonth-01"));
}

// Get other filters
$selectedWard = $_POST['ward_select'] ?? '';
$selectedType = $_POST['type_select'] ?? '';
$selectedAntType = $_POST['ant_type_select'] ?? '';

// Fetch all wards from the database
$wardQuery = "SELECT DISTINCT ward_name FROM releases ORDER BY ward_name";
$wardStmt = $conn->prepare($wardQuery);
$wardStmt->execute();
$wardResult = $wardStmt->get_result();

// Query for antibiotic usage filtered by date range, ward, type, and ant_type
$query = "
    SELECT ward_name, antibiotic_name, dosage, type, ant_type, SUM(item_count) AS usage_count
    FROM releases
    WHERE release_time BETWEEN ? AND ?
    AND (ward_name = ? OR ? = '')
    AND (type = ? OR ? = '')
    AND (ant_type = ? OR ? = '')
    GROUP BY ward_name, antibiotic_name, dosage, type, ant_type
    ORDER BY ward_name, antibiotic_name ASC, usage_count DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssssssss", $startDate, $endDate, $selectedWard, $selectedWard, $selectedType, $selectedType, $selectedAntType, $selectedAntType);
$stmt->execute();
$result = $stmt->get_result();

// Calculate totals for statistics
$totalRecords = 0;
$totalUsageCount = 0;
$totalUnits = 0;
$wardsData = [];

while ($row = $result->fetch_assoc()) {
    $totalRecords++;
    $totalUsageCount += $row['usage_count'];
    
    $wardName = $row['ward_name'];
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

    $totalUnits += $usageInGrams;
    
    if (!isset($wardsData[$wardName])) {
        $wardsData[$wardName] = 0;
    }
    $wardsData[$wardName] += $usageInGrams;
}

// Reset result pointer for display
$result->data_seek(0);

$stmt->close();
$wardStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Antibiotic Usage Analytics - Mediq</title>
    <?php include_once("../includes/css-links-inc.php"); ?>

    <!-- Include DataTables CSS and JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.1/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
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
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
        }
        
        .stat-number {
            font-size: 2.2rem;
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
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .dataTables_wrapper {
            margin-top: 20px;
        }
        
        .dataTables_filter input {
            border-radius: 6px;
            border: 1px solid #ddd;
            padding: 8px 12px;
            margin-left: 10px;
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
            text-align: center;
        }
        
        .dataTable tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .ward-header {
            background: linear-gradient(135deg, #3498db, #2980b9) !important;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
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
        
        .filter-toggle {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-toggle i {
            transition: transform 0.3s ease;
        }
        
        .filter-toggle.collapsed i {
            transform: rotate(180deg);
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .export-buttons {
                justify-content: center;
            }
            
            .btn {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
        }
        
        .form-check-input:checked {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .form-select, .form-control {
            border-radius: 6px;
            border: 1px solid #ddd;
            padding: 10px 12px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }
    </style>
</head>

<body>
    <?php include_once("../includes/header.php") ?>
    <?php include_once("../includes/sadmin-sidebar.php") ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Ward-Wise Antibiotic Usage Analytics</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item">Analytics</li>
                    <li class="breadcrumb-item active">Ward-Wise Usage</li>
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

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stats-card">
                    <div class="stat-label">Total Records</div>
                    <div class="stat-number"><?= number_format($totalRecords) ?></div>
                    <small>Antibiotic entries</small>
                </div>
                <div class="stats-card">
                    <div class="stat-label">Total Usage Count</div>
                    <div class="stat-number"><?= number_format($totalUsageCount) ?></div>
                    <small>Items dispensed</small>
                </div>
                <div class="stats-card">
                    <div class="stat-label">Total Units</div>
                    <div class="stat-number"><?= number_format($totalUnits, 2) ?></div>
                    <small>Grams equivalent</small>
                </div>
                <div class="stats-card">
                    <div class="stat-label">Wards Covered</div>
                    <div class="stat-number"><?= count($wardsData) ?></div>
                    <small>In report</small>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card">
                <div class="card-body">
                    <button type="button" class="filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="bi bi-funnel"></i> Filter Options
                    </button>
                    
                    <div class="collapse show" id="filterCollapse">
                        <h5 class="card-title">Filter Data</h5>
                        
                        <form method="POST">
                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <label class="form-label">Choose Filter Type:</label>
                                    <div class="d-flex gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_type" id="filterDate" value="date" <?= ($filterType == 'date') ? 'checked' : '' ?> onchange="toggleFilterType()">
                                            <label class="form-check-label" for="filterDate">
                                                Date Range
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_type" id="filterMonth" value="month" <?= ($filterType == 'month') ? 'checked' : '' ?> onchange="toggleFilterType()">
                                            <label class="form-check-label" for="filterMonth">
                                                Month Range
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Date Range Filters -->
                            <div id="dateRangeFilters" class="row g-3 mb-3" <?= ($filterType == 'date') ? '' : 'style="display: none;"' ?>>
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Start Date:</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $startDate ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label">End Date:</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $endDate ?>">
                                </div>
                            </div>

                            <!-- Month Range Filters -->
                            <div id="monthRangeFilters" class="row g-3 mb-3" <?= ($filterType == 'month') ? '' : 'style="display: none;"' ?>>
                                <div class="col-md-3">
                                    <label for="start_month" class="form-label">Start Month:</label>
                                    <select name="start_month" id="start_month" class="form-select">
                                        <?php
                                        $months = [
                                            '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                                            '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                                            '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                                        ];
                                        foreach ($months as $monthNum => $monthName) {
                                            echo "<option value='$monthNum'" . ($monthNum == $startMonth ? ' selected' : '') . ">$monthName</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="start_year" class="form-label">Start Year:</label>
                                    <select name="start_year" id="start_year" class="form-select">
                                        <?php
                                        $currentYear = date('Y');
                                        for ($i = 2020; $i <= $currentYear; $i++) {
                                            echo "<option value='$i'" . ($i == $startYear ? ' selected' : '') . ">$i</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="end_month" class="form-label">End Month:</label>
                                    <select name="end_month" id="end_month" class="form-select">
                                        <?php
                                        foreach ($months as $monthNum => $monthName) {
                                            echo "<option value='$monthNum'" . ($monthNum == $endMonth ? ' selected' : '') . ">$monthName</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="end_year" class="form-label">End Year:</label>
                                    <select name="end_year" id="end_year" class="form-select">
                                        <?php
                                        for ($i = 2020; $i <= $currentYear; $i++) {
                                            echo "<option value='$i'" . ($i == $endYear ? ' selected' : '') . ">$i</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label for="ward_select" class="form-label">Select Ward:</label>
                                    <select name="ward_select" id="ward_select" class="form-select">
                                        <option value="">All Wards</option>
                                        <?php
                                        $wardResult->data_seek(0);
                                        while ($wardRow = $wardResult->fetch_assoc()) {
                                            $wardName = $wardRow['ward_name'];
                                            echo "<option value='$wardName'" . ($wardName == $selectedWard ? ' selected' : '') . ">$wardName</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                              
                                <!-- MSD/LP Filter -->
                                <div class="col-md-3">
                                    <label for="type_select" class="form-label">Select Stock Type:</label>
                                    <select name="type_select" id="type_select" class="form-select">
                                        <option value="">All Types</option>
                                        <option value="msd" <?= ($selectedType == 'msd') ? 'selected' : '' ?>>MSD</option>
                                        <option value="lp" <?= ($selectedType == 'lp') ? 'selected' : '' ?>>LP</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-funnel"></i> Apply Filters
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                                        <i class="bi bi-arrow-clockwise"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <button onclick="exportToExcel()" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Export to Excel
                </button>
                <button onclick="exportToPDF()" class="btn btn-danger">
                    <i class="bi bi-file-pdf"></i> Export to PDF
                </button>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Print Report
                </button>
            </div>

            <!-- Data Table Section -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Detailed Ward-Wise Usage Data</h5>
                    
                    <div class="table-responsive">
                        <table id="antibioticTable" class="table table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Antibiotic Name</th>
                                    <th>Dosage</th>
                                    <th>Count</th>
                                    <th>Units (g)</th>
                                    <th>Percentage (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($result->num_rows > 0) {
                                    $rowNumber = 1;
                                    $previousWard = "";
                                    
                                    while ($row = $result->fetch_assoc()) {
                                        $wardName = $row['ward_name'];
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

                                        $percentageUsage = ($totalUnits > 0) ? ($usageInGrams / $totalUnits) * 100 : 0;

                                        // Group by ward name
                                        if ($wardName != $previousWard) {
                                            echo "<tr class='ward-header'><td colspan='6' class='text-center'>$wardName - Total Usage: " . number_format($wardsData[$wardName], 2) . "g</td></tr>";
                                            $previousWard = $wardName;
                                        }
                                ?>
                                        <tr>
                                            <td class='text-center'><?= $rowNumber ?></td>
                                            <td><?= htmlspecialchars($antibioticName) ?></td>
                                            <td class='text-center'><?= htmlspecialchars($dosage) ?></td>
                                            <td class='text-center'><?= number_format($itemCount) ?></td>
                                            <td class='text-center'><?= number_format($usageInGrams, 2) ?>g</td>
                                            <td class='text-center'>
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <span class="percentage-badge me-2"><?= number_format($percentageUsage, 4) ?>%</span>
                                                    <div class="progress" style="width: 100px;">
                                                        <div class="progress-bar" style="width: <?= $percentageUsage ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                <?php 
                                        $rowNumber++;
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center'>No data available for the selected period, ward, and type</td></tr>";
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                    <td class="text-center"><strong><?= number_format($totalUsageCount) ?></strong></td>
                                    <td class="text-center"><strong><?= number_format($totalUnits, 2) ?>g</strong></td>
                                    <td class="text-center"><strong>100%</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once("../includes/js-links-inc.php") ?>
    <?php include_once("../includes/footer.php") ?>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#antibioticTable').DataTable({
                "pageLength": 25,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "order": [], // No initial sorting to maintain ward grouping
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                "language": {
                    "search": "Search records:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)"
                },
                "drawCallback": function(settings) {
                    // Highlight ward headers after table redraw
                    $('.ward-header').css('background', 'linear-gradient(135deg, #3498db, #2980b9)');
                }
            });
        });

        // Toggle between date range and month range filters
        function toggleFilterType() {
            const dateFilterSelected = document.getElementById('filterDate').checked;
            document.getElementById('dateRangeFilters').style.display = dateFilterSelected ? 'flex' : 'none';
            document.getElementById('monthRangeFilters').style.display = !dateFilterSelected ? 'flex' : 'none';
        }

        // Set default dates if not already set
        document.addEventListener("DOMContentLoaded", function() {
            if (document.getElementById("start_date").value === "") {
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                document.getElementById("start_date").value = firstDay.toISOString().split('T')[0];
            }
            
            if (document.getElementById("end_date").value === "") {
                const today = new Date();
                const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                document.getElementById("end_date").value = lastDay.toISOString().split('T')[0];
            }
        });
        
        function showLoading(message = 'Processing...') {
            document.getElementById('loadingMessage').textContent = message;
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function resetFilters() {
            document.getElementById('ward_select').value = '';
            document.getElementById('type_select').value = '';
            document.getElementById('ant_type_select').value = '';
            
            // Reset to current month
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
            document.getElementById('end_date').value = lastDay.toISOString().split('T')[0];
            
            document.getElementById('start_month').value = String(today.getMonth() + 1).padStart(2, '0');
            document.getElementById('start_year').value = today.getFullYear();
            document.getElementById('end_month').value = String(today.getMonth() + 1).padStart(2, '0');
            document.getElementById('end_year').value = today.getFullYear();
            
            document.getElementById('filterMonth').checked = true;
            toggleFilterType();
        }
        
        function exportToExcel() {
            showLoading('Generating Excel report...');
            
            // Get the table element
            let table = document.getElementById("antibioticTable");
            
            // Convert the table to a worksheet
            let workbook = XLSX.utils.book_new();
            let worksheet = XLSX.utils.table_to_sheet(table);
            
            // Add the worksheet to the workbook
            XLSX.utils.book_append_sheet(workbook, worksheet, "Ward_Wise_Usage");

            // Generate filename with date info
            let dateInfo = "";
            if (document.getElementById('filterDate').checked) {
                dateInfo = document.getElementById('start_date').value + "_to_" + document.getElementById('end_date').value;
            } else {
                const startMonthSelect = document.getElementById('start_month');
                const startMonthText = startMonthSelect.options[startMonthSelect.selectedIndex].text;
                const endMonthSelect = document.getElementById('end_month');
                const endMonthText = endMonthSelect.options[endMonthSelect.selectedIndex].text;
                dateInfo = startMonthText + document.getElementById('start_year').value + "_to_" + 
                          endMonthText + document.getElementById('end_year').value;
            }
            
            // Save the file
            XLSX.writeFile(workbook, "Ward_Wise_Antibiotic_Usage_" + dateInfo + ".xlsx");
            
            setTimeout(hideLoading, 1000);
        }
        
        async function exportToPDF() {
            showLoading('Generating PDF report...');
            
            const { jsPDF } = window.jspdf;
            let doc = new jsPDF('p', 'pt', 'a4');

            // Title
            doc.setFont("helvetica", "bold");
            doc.setFontSize(18);
            doc.setTextColor(44, 62, 80);
            doc.text("Ward-Wise Antibiotic Usage Report", 105, 30, { align: "center" });

            // Date range info
            let dateInfo = "";
            if (document.getElementById('filterDate').checked) {
                dateInfo = "Period: " + document.getElementById('start_date').value + " to " + document.getElementById('end_date').value;
            } else {
                const startMonthSelect = document.getElementById('start_month');
                const startMonthText = startMonthSelect.options[startMonthSelect.selectedIndex].text;
                const endMonthSelect = document.getElementById('end_month');
                const endMonthText = endMonthSelect.options[endMonthSelect.selectedIndex].text;
                dateInfo = "Period: " + startMonthText + " " + document.getElementById('start_year').value + " to " + 
                          endMonthText + " " + document.getElementById('end_year').value;
            }
            
            doc.setFontSize(12);
            doc.setTextColor(100, 100, 100);
            doc.text(dateInfo, 105, 50, { align: "center" });

            // Filter info
            let filterInfo = "";
            const wardSelect = document.getElementById('ward_select');
            if (wardSelect.value) {
                filterInfo += "Ward: " + wardSelect.options[wardSelect.selectedIndex].text;
            }
            
            const typeSelect = document.getElementById('type_select');
            if (typeSelect.value) {
                if (filterInfo) filterInfo += " | ";
                filterInfo += "Stock: " + typeSelect.options[typeSelect.selectedIndex].text;
            }
            
            if (filterInfo) {
                doc.text(filterInfo, 105, 70, { align: "center" });
            }

            // Get table data
            let table = document.getElementById("antibioticTable");
            let data = [];
            let headers = [];

            // Get headers
            let headerCells = table.querySelectorAll("thead tr th");
            headerCells.forEach(header => headers.push(header.innerText));
            
            // Get rows (skip ward header rows for the table)
            let rows = table.querySelectorAll("tbody tr:not(.ward-header)");
            rows.forEach(row => {
                let rowData = [];
                let cells = row.querySelectorAll("td");
                cells.forEach(cell => rowData.push(cell.innerText));
                data.push(rowData);
            });

            // Add table to PDF
            doc.autoTable({
                head: [headers],
                body: data,
                startY: 90,
                theme: "grid",
                styles: { 
                    fontSize: 9,
                    cellPadding: 4,
                    lineColor: [200, 200, 200],
                    lineWidth: 0.25
                },
                headStyles: { 
                    fillColor: [44, 62, 80],
                    textColor: 255,
                    fontStyle: 'bold',
                    lineWidth: 0.25
                },
                alternateRowStyles: {
                    fillColor: [248, 249, 250]
                },
                margin: { top: 90 }
            });

            // Add footer with page numbers
            const pageCount = doc.internal.getNumberOfPages();
            for(let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(10);
                doc.setTextColor(150, 150, 150);
                doc.text(`Page ${i} of ${pageCount}`, doc.internal.pageSize.getWidth() - 40, doc.internal.pageSize.getHeight() - 20, { align: 'right' });
                doc.text("Generated on: <?= date('Y-m-d H:i:s') ?>", 40, doc.internal.pageSize.getHeight() - 20);
            }

            // Generate filename with date info
            let filenameDateInfo = "";
            if (document.getElementById('filterDate').checked) {
                filenameDateInfo = document.getElementById('start_date').value + "_to_" + document.getElementById('end_date').value;
            } else {
                const startMonthSelect = document.getElementById('start_month');
                const startMonthText = startMonthSelect.options[startMonthSelect.selectedIndex].text;
                const endMonthSelect = document.getElementById('end_month');
                const endMonthText = endMonthSelect.options[endMonthSelect.selectedIndex].text;
                filenameDateInfo = startMonthText + document.getElementById('start_year').value + "_to_" + 
                          endMonthText + document.getElementById('end_year').value;
            }

            // Save PDF
            doc.save("Ward_Wise_Antibiotic_Usage_" + filenameDateInfo + ".pdf");
            
            hideLoading();
        }
    </script>
</body>
</html>