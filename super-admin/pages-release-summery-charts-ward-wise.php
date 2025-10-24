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

// Filters
$startMonth = $_POST['start_month'] ?? date('m');
$startYear = $_POST['start_year'] ?? date('Y');
$endMonth = $_POST['end_month'] ?? date('m');
$endYear = $_POST['end_year'] ?? date('Y');

$startDate = date('Y-m-01', strtotime("$startYear-$startMonth-01"));
$endDate = date('Y-m-t', strtotime("$endYear-$endMonth-01"));

/** Chart 1: Antibiotic-wise by Ward **/
$antibioticData = [];
$wards1 = [];

$stmt = $conn->prepare("
    SELECT ward_name, antibiotic_name, dosage, SUM(item_count) AS usage_count
    FROM releases
    WHERE release_time BETWEEN ? AND ?
    GROUP BY ward_name, antibiotic_name, dosage
    ORDER BY antibiotic_name, ward_name
");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $antibiotic = $row['antibiotic_name'];
    $ward = $row['ward_name'];
    $dosage = strtolower($row['dosage']);
    $count = $row['usage_count'];

    if (!in_array($ward, $wards1)) $wards1[] = $ward;

    $units = 0;
    if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
        $units = ($matches[1] / 1000) * $count;
    } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
        $units = $matches[1] * $count;
    }

    $antibioticData[$antibiotic][$ward] = ($antibioticData[$antibiotic][$ward] ?? 0) + $units;
}
$antibiotics = array_keys($antibioticData);
sort($wards1);
sort($antibiotics);
$stmt->close();

/** Chart 2: Category-wise by Ward **/
$categoryColors = ['Access' => '#28a745', 'Watch' => '#0000ff', 'Reserve' => '#dc3545'];
$categories = [];
$dataMap = [];
$wards2 = [];

$catResult = $conn->query("SELECT DISTINCT category FROM releases WHERE category IS NOT NULL AND category != ''");
while ($catRow = $catResult->fetch_assoc()) {
    $categories[] = $catRow['category'];
}
sort($categories);
$colorList = array_map(fn($c) => $categoryColors[$c] ?? '#888', $categories);

$stmt = $conn->prepare("
    SELECT ward_name, category, dosage, SUM(item_count) AS usage_count
    FROM releases
    WHERE release_time BETWEEN ? AND ?
    GROUP BY ward_name, category, dosage
    ORDER BY ward_name, category
");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $ward = $row['ward_name'];
    $category = $row['category'];
    $dosage = strtolower($row['dosage']);
    $count = $row['usage_count'];

    if (!in_array($ward, $wards2)) $wards2[] = $ward;

    $units = 0;
    if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
        $units = ($matches[1] / 1000) * $count;
    } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
        $units = $matches[1] * $count;
    }

    $dataMap[$ward][$category] = ($dataMap[$ward][$category] ?? 0) + $units;
}
sort($wards2);
$stmt->close();

// Prepare data for Excel export
$excelData = [];

// Chart 1 data for Excel
$excelData['chart1'] = [];
$excelData['chart1'][] = array_merge(['Ward'], $antibiotics); // Header row

foreach ($wards1 as $ward) {
    $row = [$ward];
    foreach ($antibiotics as $a) {
        $row[] = isset($antibioticData[$a][$ward]) ? round($antibioticData[$a][$ward], 2) : 0;
    }
    $excelData['chart1'][] = $row;
}

// Chart 2 data for Excel
$excelData['chart2'] = [];
$excelData['chart2'][] = array_merge(['Ward'], $categories); // Header row

foreach ($wards2 as $ward) {
    $row = [$ward];
    foreach ($categories as $cat) {
        $row[] = isset($dataMap[$ward][$cat]) ? round($dataMap[$ward][$cat], 2) : 0;
    }
    $excelData['chart2'][] = $row;
}

// Store data in session for Excel export
$_SESSION['excel_data'] = $excelData;

// Calculate dynamic widths based on data - ensure minimum bar width of 20px
$minBarWidth = 10;
$chart1Width = max(1000, count($wards1) * count($antibiotics) * $minBarWidth * 1);
$chart2Width = max(1000, count($wards2) * 100);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Antibiotic Usage Dashboard</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <style>
        .chart-container { 
            margin: 10px auto; 
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%;
            overflow-x: auto;
        }
        .chart-title {
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
            color: #333;
            font-size: 18px;
        }
        .table-container {
            margin-top: 30px;
            overflow-x: auto;
        }
        .export-buttons {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .chart-wrapper {
            margin-bottom: 40px;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        /* Custom scrollbar for chart containers */
        .chart-container::-webkit-scrollbar {
            height: 8px;
        }
        .chart-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .chart-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        .chart-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Ensure full chart visibility */
        #chart1, #chart2 {
            width: 100% !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .chart-container {
                padding: 10px;
            }
            .filter-section .row {
                flex-direction: column;
            }
            .filter-section .col-md-3 {
                margin-bottom: 10px;
            }
            .export-buttons {
                justify-content: center;
            }
        }
        
        /* Loading indicator for image download */
        .download-loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 20px;
            border-radius: 5px;
            z-index: 1000;
        }
    </style>
    
    <script>
        // Global chart variables
        var chart1, chart2;
        
        google.charts.load("current", {packages: ['corechart', 'table']});
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            drawChart1();
            drawChart2();
            drawChart1Table();
            drawChart2Table();
        }

        function drawChart1() {
            var data = google.visualization.arrayToDataTable([
                ['Ward', <?php foreach ($antibiotics as $a) echo "'".addslashes($a)."',"; ?>],
                <?php foreach ($wards1 as $ward): ?>
                    ['<?= addslashes($ward) ?>',
                        <?php foreach ($antibiotics as $a): ?>
                            <?= isset($antibioticData[$a][$ward]) ? round($antibioticData[$a][$ward], 2) : 0 ?>,
                        <?php endforeach; ?>
                    ],
                <?php endforeach; ?>
            ]);

            // Calculate dynamic dimensions with minimum bar width
            var minBarWidth = 15; // Minimum 20px per bar
            var barsPerWard = <?= count($antibiotics) ?>;
            var containerWidth = Math.max(1200, <?= count($wards1) ?> * barsPerWard * minBarWidth * 1);
            var chartHeight = Math.max(500, <?= count($wards1) ?> * 30);
            
            var options = {
                title: 'Antibiotic Usage by Ward - <?= "$startYear-$startMonth to $endYear-$endMonth" ?>',
                width: containerWidth,
                height: chartHeight,
                hAxis: {
                    title: 'Ward',
                    slantedText: true,
                    slantedTextAngle: 45,
                    textStyle: { 
                        fontSize: 12,
                        bold: true
                    }
                },
                vAxis: { 
                    title: 'Units (g)',
                    minValue: 0,
                    textStyle: { fontSize: 12 }
                },
                isStacked: false,
                legend: { 
                    position: 'top',
                    maxLines: 3,
                    textStyle: { fontSize: 12 }
                },
                chartArea: { 
                    left: 100, 
                    right: 50, 
                    top: 80, 
                    bottom: 200,
                    width: '90%',
                    height: '75%'
                },
                bar: { 
                    groupWidth: '60%'  // Fixed bar width for better visibility
                },
                colors: ['#4285F4', '#DB4437', '#F4B400', '#0F9D58', '#AB47BC', '#00ACC1', 
                        '#FF7043', '#9E9D24', '#5C6BC0', '#26A69A', '#7E57C2', '#42A5F5',
                        '#EF5350', '#66BB6A', '#FFCA28', '#8D6E63', '#26C6DA', '#FFA726'],
                tooltip: { 
                    textStyle: { fontSize: 12 },
                    showColorCode: true
                },
                animation: {
                    startup: true,
                    duration: 1000,
                    easing: 'out'
                }
            };

            chart1 = new google.visualization.ColumnChart(document.getElementById('chart1'));
            chart1.draw(data, options);
            
            // Add event listener for chart click to show data table
            google.visualization.events.addListener(chart1, 'select', function() {
                var selection = chart1.getSelection();
                if (selection.length > 0) {
                    document.getElementById('chart1-table-container').scrollIntoView({behavior: 'smooth'});
                }
            });
        }

        function drawChart2() {
            var data = google.visualization.arrayToDataTable([
                ['Ward', <?php foreach ($categories as $cat) echo "'".addslashes($cat)."',"; ?>],
                <?php foreach ($wards2 as $ward): ?>
                    ['<?= addslashes($ward) ?>',
                        <?php foreach ($categories as $cat): ?>
                            <?= isset($dataMap[$ward][$cat]) ? round($dataMap[$ward][$cat], 2) : 0 ?>,
                        <?php endforeach; ?>
                    ],
                <?php endforeach; ?>
            ]);

            var containerWidth = Math.max(1000, <?= count($wards2) ?> * 100);
            
            var options = {
                title: 'Usage by Ward (Stacked Categories) - <?= "$startYear-$startMonth to $endYear-$endMonth" ?>',
                width: containerWidth,
                height: 500,
                hAxis: { 
                    title: 'Ward',
                    slantedText: true,
                    slantedTextAngle: 45,
                    textStyle: { fontSize: 12 }
                },
                vAxis: { 
                    title: 'Units (g)',
                    minValue: 0
                },
                isStacked: true,
                legend: { 
                    position: 'top',
                    textStyle: { fontSize: 12 }
                },
                chartArea: {
                    left: 100,
                    right: 50,
                    top: 80,
                    bottom: 150,
                    width: '90%'
                },
                bar: { 
                    groupWidth: '70%'
                },
                colors: <?= json_encode($colorList) ?>,
                tooltip: { 
                    textStyle: { fontSize: 12 },
                    isHtml: true
                },
                animation: {
                    startup: true,
                    duration: 1000,
                    easing: 'out'
                }
            };

            chart2 = new google.visualization.ColumnChart(document.getElementById('chart2'));
            chart2.draw(data, options);
            
            // Add event listener for chart click to show data table
            google.visualization.events.addListener(chart2, 'select', function() {
                var selection = chart2.getSelection();
                if (selection.length > 0) {
                    document.getElementById('chart2-table-container').scrollIntoView({behavior: 'smooth'});
                }
            });
        }

        function drawChart1Table() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Ward');
            <?php foreach ($antibiotics as $a): ?>
                data.addColumn('number', '<?= addslashes($a) ?>');
            <?php endforeach; ?>
            data.addColumn('number', 'Total');

            <?php foreach ($wards1 as $ward): 
                $total = 0;
                foreach ($antibiotics as $a) {
                    $total += isset($antibioticData[$a][$ward]) ? $antibioticData[$a][$ward] : 0;
                }
            ?>
                data.addRow([
                    '<?= addslashes($ward) ?>',
                    <?php foreach ($antibiotics as $a): 
                        $val = isset($antibioticData[$a][$ward]) ? round($antibioticData[$a][$ward], 2) : 0;
                    ?>
                        <?= $val ?>,
                    <?php endforeach; ?>
                    <?= round($total, 2) ?>
                ]);
            <?php endforeach; ?>

            // Add a row for totals per antibiotic
            var totalsRow = ['Total'];
            <?php foreach ($antibiotics as $a): 
                $total = 0;
                foreach ($wards1 as $ward) {
                    $total += isset($antibioticData[$a][$ward]) ? $antibioticData[$a][$ward] : 0;
                }
            ?>
                totalsRow.push(<?= round($total, 2) ?>);
            <?php endforeach; ?>
            totalsRow.push(<?= 
                array_sum(array_map(function($a) use ($antibioticData, $wards1) {
                    return array_sum(array_map(function($ward) use ($a, $antibioticData) {
                        return isset($antibioticData[$a][$ward]) ? $antibioticData[$a][$ward] : 0;
                    }, $wards1));
                }, $antibiotics))
            ?>);
            data.addRow(totalsRow);

            var table = new google.visualization.Table(document.getElementById('chart1-table'));
            var formatter = new google.visualization.NumberFormat({fractionDigits: 2});
            <?php foreach ($antibiotics as $index => $a): ?>
                formatter.format(data, <?= $index + 1 ?>);
            <?php endforeach; ?>
            formatter.format(data, <?= count($antibiotics) + 1 ?>); // Format the total column
            
            table.draw(data, {
                showRowNumber: false, 
                width: '100%', 
                height: '100%',
                allowHtml: true,
                cssClassNames: {
                    headerRow: 'table-header',
                    tableRow: 'table-row',
                    oddTableRow: 'odd-table-row',
                    selectedTableRow: 'selected-table-row',
                    hoverTableRow: 'hover-table-row',
                    headerCell: 'table-header-cell',
                    tableCell: 'table-cell',
                    rowNumberCell: 'row-number-cell'
                }
            });
        }

        function drawChart2Table() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Ward');
            <?php foreach ($categories as $cat): ?>
                data.addColumn('number', '<?= addslashes($cat) ?>');
            <?php endforeach; ?>
            data.addColumn('number', 'Total');

            <?php foreach ($wards2 as $ward): 
                $total = 0;
                foreach ($categories as $cat) {
                    $total += isset($dataMap[$ward][$cat]) ? $dataMap[$ward][$cat] : 0;
                }
            ?>
                data.addRow([
                    '<?= addslashes($ward) ?>',
                    <?php foreach ($categories as $cat): 
                        $val = isset($dataMap[$ward][$cat]) ? round($dataMap[$ward][$cat], 2) : 0;
                    ?>
                        <?= $val ?>,
                    <?php endforeach; ?>
                    <?= round($total, 2) ?>
                ]);
            <?php endforeach; ?>

            // Add a row for totals per category
            var totalsRow = ['Total'];
            <?php foreach ($categories as $cat): 
                $total = 0;
                foreach ($wards2 as $ward) {
                    $total += isset($dataMap[$ward][$cat]) ? $dataMap[$ward][$cat] : 0;
                }
            ?>
                totalsRow.push(<?= round($total, 2) ?>);
            <?php endforeach; ?>
            totalsRow.push(<?= 
                array_sum(array_map(function($cat) use ($dataMap, $wards2) {
                    return array_sum(array_map(function($ward) use ($cat, $dataMap) {
                        return isset($dataMap[$ward][$cat]) ? $dataMap[$ward][$cat] : 0;
                    }, $wards2));
                }, $categories))
            ?>);
            data.addRow(totalsRow);

            var table = new google.visualization.Table(document.getElementById('chart2-table'));
            var formatter = new google.visualization.NumberFormat({fractionDigits: 2});
            <?php foreach ($categories as $index => $cat): ?>
                formatter.format(data, <?= $index + 1 ?>);
            <?php endforeach; ?>
            formatter.format(data, <?= count($categories) + 1 ?>); // Format the total column
            
            table.draw(data, {
                showRowNumber: false, 
                width: '100%', 
                height: '100%',
                allowHtml: true,
                cssClassNames: {
                    headerRow: 'table-header',
                    tableRow: 'table-row',
                    oddTableRow: 'odd-table-row',
                    selectedTableRow: 'selected-table-row',
                    hoverTableRow: 'hover-table-row',
                    headerCell: 'table-header-cell',
                    tableCell: 'table-cell',
                    rowNumberCell: 'row-number-cell'
                }
            });
        }
        

        // Function to export data to Excel
        function exportToExcel(type) {
            // Create a new workbook
            var wb = XLSX.utils.book_new();
            
            if (type === 'chart1' || type === 'all') {
                // Prepare data for chart1
                var ws1_data = [
                    ['Antibiotic Usage by Ward - <?= "$startYear-$startMonth to $endYear-$endMonth" ?>'],
                    ['Ward', <?php foreach ($antibiotics as $a) echo "'".addslashes($a)."',"; ?> 'Total']
                ];
                
                <?php foreach ($wards1 as $ward): 
                    $total = 0;
                    foreach ($antibiotics as $a) {
                        $total += isset($antibioticData[$a][$ward]) ? $antibioticData[$a][$ward] : 0;
                    }
                ?>
                    ws1_data.push([
                        '<?= addslashes($ward) ?>',
                        <?php foreach ($antibiotics as $a): 
                            $val = isset($antibioticData[$a][$ward]) ? round($antibioticData[$a][$ward], 2) : 0;
                        ?>
                            <?= $val ?>,
                        <?php endforeach; ?>
                        <?= round($total, 2) ?>
                    ]);
                <?php endforeach; ?>
                
                // Add totals row
                var totalsRow = ['Total'];
                <?php foreach ($antibiotics as $a): 
                    $total = 0;
                    foreach ($wards1 as $ward) {
                        $total += isset($antibioticData[$a][$ward]) ? $antibioticData[$a][$ward] : 0;
                    }
                ?>
                    totalsRow.push(<?= round($total, 2) ?>);
                <?php endforeach; ?>
                totalsRow.push(<?= 
                    array_sum(array_map(function($a) use ($antibioticData, $wards1) {
                        return array_sum(array_map(function($ward) use ($a, $antibioticData) {
                            return isset($antibioticData[$a][$ward]) ? $antibioticData[$a][$ward] : 0;
                        }, $wards1));
                    }, $antibiotics))
                ?>);
                ws1_data.push(totalsRow);
                
                var ws1 = XLSX.utils.aoa_to_sheet(ws1_data);
                XLSX.utils.book_append_sheet(wb, ws1, "Antibiotic Usage");
            }
            
            if (type === 'chart2' || type === 'all') {
                // Prepare data for chart2
                var ws2_data = [
                    ['Category Usage by Ward - <?= "$startYear-$startMonth to $endYear-$endMonth" ?>'],
                    ['Ward', <?php foreach ($categories as $cat) echo "'".addslashes($cat)."',"; ?> 'Total']
                ];
                
                <?php foreach ($wards2 as $ward): 
                    $total = 0;
                    foreach ($categories as $cat) {
                        $total += isset($dataMap[$ward][$cat]) ? $dataMap[$ward][$cat] : 0;
                    }
                ?>
                    ws2_data.push([
                        '<?= addslashes($ward) ?>',
                        <?php foreach ($categories as $cat): 
                            $val = isset($dataMap[$ward][$cat]) ? round($dataMap[$ward][$cat], 2) : 0;
                        ?>
                            <?= $val ?>,
                        <?php endforeach; ?>
                        <?= round($total, 2) ?>
                    ]);
                <?php endforeach; ?>
                
                // Add totals row
                var totalsRow2 = ['Total'];
                <?php foreach ($categories as $cat): 
                    $total = 0;
                    foreach ($wards2 as $ward) {
                        $total += isset($dataMap[$ward][$cat]) ? $dataMap[$ward][$cat] : 0;
                    }
                ?>
                    totalsRow2.push(<?= round($total, 2) ?>);
                <?php endforeach; ?>
                totalsRow2.push(<?= 
                    array_sum(array_map(function($cat) use ($dataMap, $wards2) {
                        return array_sum(array_map(function($ward) use ($cat, $dataMap) {
                            return isset($dataMap[$ward][$cat]) ? $dataMap[$ward][$cat] : 0;
                        }, $wards2));
                    }, $categories))
                ?>);
                ws2_data.push(totalsRow2);
                
                var ws2 = XLSX.utils.aoa_to_sheet(ws2_data);
                XLSX.utils.book_append_sheet(wb, ws2, "Category Usage");
            }
            
            // Generate and download the Excel file
            var fileName = 'Antibiotic_Usage_Report_<?= $startYear . $startMonth . '_' . $endYear . $endMonth ?>.xlsx';
            XLSX.writeFile(wb, fileName);
        }
        
        // Function to download chart as image
        function downloadChartAsImage(chartId, chartName) {
            // Show loading indicator
            var loading = document.getElementById('download-loading');
            loading.style.display = 'block';
            loading.innerHTML = 'Generating ' + chartName + ' image...';
            
            // Get the chart container
            var chartElement = document.getElementById(chartId);
            
            // Use html2canvas to capture the chart as an image
            html2canvas(chartElement, {
                scale: 2, // Higher quality
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(function(canvas) {
                // Convert canvas to image data URL
                var imageData = canvas.toDataURL('image/png');
                
                // Create a temporary link to download the image
                var link = document.createElement('a');
                link.download = chartName + '_<?= $startYear . $startMonth . '_' . $endYear . $endMonth ?>.png';
                link.href = imageData;
                
                // Trigger the download
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Hide loading indicator
                loading.style.display = 'none';
            }).catch(function(error) {
                console.error('Error generating chart image:', error);
                alert('Error generating chart image. Please try again.');
                loading.style.display = 'none';
            });
        }
        
        // Function to download Google Chart directly (alternative method)
        function downloadGoogleChart(chartInstance, chartName) {
            // Show loading indicator
            var loading = document.getElementById('download-loading');
            loading.style.display = 'block';
            loading.innerHTML = 'Generating ' + chartName + ' image...';
            
            try {
                // Get the chart image URI from Google Charts
                var chartImageUri = chartInstance.getImageURI();
                
                // Create a temporary link to download the image
                var link = document.createElement('a');
                link.download = chartName + '_<?= $startYear . $startMonth . '_' . $endYear . $endMonth ?>.png';
                link.href = chartImageUri;
                
                // Trigger the download
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Hide loading indicator
                loading.style.display = 'none';
            } catch (error) {
                console.error('Error generating Google Chart image:', error);
                // Fallback to html2canvas method
                var chartId = chartInstance === chart1 ? 'chart1' : 'chart2';
                downloadChartAsImage(chartId, chartName);
            }
        }
        
        // Function to adjust chart size on window resize
        window.addEventListener('resize', function() {
            drawCharts();
        });
    </script>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Antibiotic Usage Dashboard</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                <li class="breadcrumb-item active">Antibiotic Usage Dashboard</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Filter Form -->
        <div class="filter-section">
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label for="start_year" class="form-label">Start Year</label>
                    <select name="start_year" id="start_year" class="form-select">
                        <?php for ($y = 2020; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $startYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_month" class="form-label">Start Month</label>
                    <select name="start_month" id="start_month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): $val = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?= $val ?>" <?= $startMonth == $val ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="end_year" class="form-label">End Year</label>
                    <select name="end_year" id="end_year" class="form-select">
                        <?php for ($y = 2020; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $endYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="end_month" class="form-label">End Month</label>
                    <select name="end_month" id="end_month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): $val = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?= $val ?>" <?= $endMonth == $val ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-12 d-flex justify-content-between">
                    <div>
                        <button type="submit" class="btn btn-primary px-4">Apply Filter</button>
                        <button type="button" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'" class="btn btn-secondary">Reset</button>
                    </div>
                    <div>
                        <button type="button" onclick="exportToExcel('all')" class="btn btn-success">Export All to Excel</button>
                        <button onclick="window.print()" class="btn btn-danger">Print Report</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Loading indicator for image downloads -->
        <div id="download-loading" class="download-loading">
            Generating image...
        </div>

        <!-- Chart 1: Antibiotic-wise by Ward -->
        <div class="chart-wrapper">
            <div class="card">
                <div class="card-body">
                    <div class="export-buttons">
                        <button onclick="exportToExcel('chart1')" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-file-earmark-excel"></i> Export to Excel
                        </button>
                        <button onclick="downloadGoogleChart(chart1, 'Antibiotic_Usage_Chart')" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-download"></i> Download as Image
                        </button>
                    </div>
                    <h6 class="chart-title">Chart 1: Antibiotic Usage by Ward</h6>
                    <div class="chart-container">
                        <?php if (empty($wards1) || empty($antibiotics)): ?>
                            <div class="no-data">No data available for the selected period</div>
                        <?php else: ?>
                            <div id="chart1"></div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="chart1-table-container" class="table-container">
                        <h5 class="chart-title">Chart 1 Data Table</h5>
                        <div id="chart1-table"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 2: Category-wise by Ward -->
        <div class="chart-wrapper">
            <div class="card">
                <div class="card-body">
                    <div class="export-buttons">
                        <button onclick="exportToExcel('chart2')" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-file-earmark-excel"></i> Export to Excel
                        </button>
                        <button onclick="downloadGoogleChart(chart2, 'Category_Usage_Chart')" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-download"></i> Download as Image
                        </button>
                    </div>
                    <h5 class="chart-title">Chart 2: Usage by Ward (Stacked Categories)</h5>
                    <div class="chart-container">
                        <?php if (empty($wards2) || empty($categories)): ?>
                            <div class="no-data">No data available for the selected period</div>
                        <?php else: ?>
                            <div id="chart2"></div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="chart2-table-container" class="table-container">
                        <h5 class="chart-title">Chart 2 Data Table</h5>
                        <div id="chart2-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include_once("../includes/js-links-inc.php"); ?>
<?php include_once("../includes/footer.php"); ?>
</body>
</html>