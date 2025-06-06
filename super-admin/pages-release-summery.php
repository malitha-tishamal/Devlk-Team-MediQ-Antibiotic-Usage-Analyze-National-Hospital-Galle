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

// Filter logic
$filterType = $_POST['filter_type'] ?? 'month';
$selectedYear = $_POST['year_select'] ?? date('Y');
$startMonth = $_POST['start_month_select'] ?? 1;
$endMonth = $_POST['end_month_select'] ?? 12;
$startDate = $_POST['start_date'] ?? date('Y-m-01');
$endDate = $_POST['end_date'] ?? date('Y-m-t');

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
    <title>Antibiotic Usage - Mediq</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.1/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>


    <style>
        #piechart, #categoryPieChart { width: 95%; height: 400px; margin: auto; }
        .dataTables_filter { text-align: right; }
        .custom-search-box { margin-bottom: 15px; }
        @media print { .no-print { display: none; } }
        @media only screen and (min-width: 768px) {
            .select-bar { display: flex; }
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
        // Re-run pie chart query to fetch both antibiotic and dosage
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

            // Merge same name
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
        title: 'Antibiotic Usage (<?= date('F Y', strtotime("$selectedYear-$startMonth-01")); ?> - <?= date('F Y', strtotime("$selectedYear-$endMonth-01")); ?>)',
        pieHole: 0.4,
        fontSize: 14,
        chartArea: { width: '85%', height: '75%' },
        pieSliceText: 'percentage',
        tooltip: { text: 'percentage' },
        colors: ['#FF5733', '#33FF57', '#5733FF', '#FF33A1', '#33A1FF', '#ffcc00', '#00ccff']
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
            $colorsMap[$category] = match ($category) {
                'Access' => '#28a745',
                'Watch' => '#0000ff',
                'Reserve' => '#dc3545',
                default => '#999999',
            };
        }
        ?>
    ]);

    var options = {
        title: 'Antibiotic Usage by Category (Units)',
        pieHole: 0.4,
        fontSize: 14,
        chartArea: { width: '85%', height: '75%' },
        pieSliceText: 'percentage',
        tooltip: { text: 'percentage' },
        colors: [<?php echo '"' . implode('","', $colorsMap) . '"'; ?>]
    };

    var chart = new google.visualization.PieChart(document.getElementById('categoryPieChart'));
    chart.draw(data, options);
}
</script>

</head>

<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Usage Details</h1>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Filter</h5>
                <form method="POST">
                    <div class="form-group mb-3">
                        <!--div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="filter_type" value="month" <?php if ($filterType === 'month') echo 'checked'; ?>>
                            <label class="form-check-label">Filter by Month</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="filter_type" value="date" <?php if ($filterType === 'date') echo 'checked'; ?>>
                            <label class="form-check-label">Filter by Date</label>
                        </div-->
                    </div>

                    <div id="month_range_filters" class="form-row mb-3 select-bar" style="<?php echo ($filterType === 'month') ? '' : 'display: none;'; ?>">
                        <div class="col-sm-3">
                            <label>Select Year:</label>
                            <select name="year_select" class="form-select">
                                <?php for ($i = 2020; $i <= date('Y'); $i++): ?>
                                    <option value="<?= $i ?>" <?= ($i == $selectedYear) ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label>Start Month:</label>
                            <select name="start_month_select" class="form-select">
                                <?php foreach (range(1, 12) as $m): ?>
                                    <option value="<?= $m ?>" <?= ($m == $startMonth) ? 'selected' : '' ?>><?= date("F", mktime(0, 0, 0, $m, 10)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label>End Month:</label>
                            <select name="end_month_select" class="form-select">
                                <?php foreach (range(1, 12) as $m): ?>
                                    <option value="<?= $m ?>" <?= ($m == $endMonth) ? 'selected' : '' ?>><?= date("F", mktime(0, 0, 0, $m, 10)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="date_range_filters" class="form-row mb-3 select-bar" style="<?php echo ($filterType === 'date') ? '' : 'display: none;'; ?>">
                        <div class="col-sm-3">
                            <label>Start Date:</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                        </div>
                        <div class="col-sm-3">
                            <label>End Date:</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </form>
            </div>
        </div>


        <div class="mb-3 no-print d-flex gap-2">
            <button onclick="exportToExcel()" class="btn btn-success">Download Excel</button>
            <!--button onclick="downloadPDF()" class="btn btn-danger">Download PDF</button-->
            <button onclick="window.print()" class="btn btn-primary">Print</button>
        </div>


        <script>
            function exportToExcel() {
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
                XLSX.writeFile(wb, "antibiotic_usage_full.xlsx");
            }
            </script>

        <script>
            async function downloadPDF() {
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
                const chartWidth = pageWidth - 40;

                // Add chart 1
                doc.text("Antibiotic Usage (by Antibiotic)", 20, 20);
                doc.addImage(imgData1, 'PNG', 20, 30, chartWidth, 200);

                // Add chart 2
                doc.text("Antibiotic Usage (by Category)", 20, 250);
                doc.addImage(imgData2, 'PNG', 20, 260, chartWidth, 200);

                // Move below charts
                const finalY = 470;

                doc.text("Usage Table", 20, finalY);

                doc.autoTable({
                    html: '#antibioticTable',
                    startY: finalY + 10,
                    styles: { fontSize: 8 },
                    theme: 'grid',
                });

                doc.save("antibiotic_usage_report.pdf");
            }

        </script>


        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Antibiotic Usage Data</h5>
                <div class="text-end mb-3">
                </div>

               <div class="d-flex">
                    <div id="piechart"></div>
                    <div id="categoryPieChart"></div>
               </div>

               <div class="mb-3">
                <input type="text" id="customSearchBox" class="form-control w-75" placeholder="Search antibiotic data...">
            </div>

        <script>
            document.getElementById('customSearchBox').addEventListener('keyup', function () {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll('#antibioticTable tbody tr');

                rows.forEach(function (row) {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
            </script>

                    <table id="antibioticTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Antibiotic</th>
                            <th>Dosage</th>
                            <th>Count</th>
                            <th>Converted Usage (g)</th>
                            <th>Units (1g = 1 Unit)</th>
                            <th>% of Total Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 1;
                        $totalUnits = 0;

                        // First pass: calculate total grams/units
                        $tempData = [];
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
                                'units' => $usageInGrams // 1g = 1 unit
                            ];

                            $totalUnits += $usageInGrams;
                        }

                        // Display table with percentage
                        foreach ($tempData as $row) {
                            $percentage = ($totalUnits > 0) ? ($row['units'] / $totalUnits) * 100 : 0;
                        ?>
                        <tr>
                            <td><?= $count++ ?></td>
                            <td><?= htmlspecialchars($row['antibiotic_name']) ?></td>
                            <td><?= htmlspecialchars($row['dosage']) ?></td>
                            <td><?= number_format($row['count']) ?></td>
                            <td><?= number_format($row['grams'], 2) ?> g</td>
                            <td><?= number_format($row['units'], 2) ?></td>
                            <td><?= number_format($percentage, 2) ?>%</td>
                        </tr>
                        <?php } ?>
                        </tbody>

                </table>
            </div>
        </div>
    </section>
</main>
<?php include_once("../includes/footer.php"); ?>
<?php include_once ("../includes/js-links-inc.php") ?>
</body>
</html>
