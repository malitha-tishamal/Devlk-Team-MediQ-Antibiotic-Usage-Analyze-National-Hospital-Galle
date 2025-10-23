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

// Fetch available wards
$wardList = [];
$wardRes = $conn->query("SELECT DISTINCT ward_name FROM releases ORDER BY ward_name ASC");
while ($w = $wardRes->fetch_assoc()) {
    $wardList[] = $w['ward_name'];
}

// Default to the first ward if none selected
$startMonth = $_POST['start_month'] ?? date('m');
$startYear = $_POST['start_year'] ?? date('Y');
$endMonth = $_POST['end_month'] ?? date('m');
$endYear = $_POST['end_year'] ?? date('Y');
$selectedWard = $_POST['ward_name'] ?? ($wardList[0] ?? '');

$startDate = date('Y-m-01', strtotime("$startYear-$startMonth-01"));
$endDate = date('Y-m-t', strtotime("$endYear-$endMonth-01"));

/** Chart 1: Antibiotic-wise by Ward **/
$antibioticData = [];
$wards1 = [];
$hasChart1Data = false;

$query1 = "
    SELECT ward_name, antibiotic_name, dosage, SUM(item_count) AS usage_count
    FROM releases
    WHERE release_time BETWEEN ? AND ?
";
$params1 = [$startDate, $endDate];
$types1 = "ss";
if (!empty($selectedWard)) {
    $query1 .= " AND ward_name = ?";
    $params1[] = $selectedWard;
    $types1 .= "s";
}
$query1 .= " GROUP BY ward_name, antibiotic_name, dosage ORDER BY antibiotic_name, ward_name";

$stmt = $conn->prepare($query1);
$stmt->bind_param($types1, ...$params1);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $hasChart1Data = true;
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
$categoryColors = ['Access' => '#28a745', 'Watch' => '#ffc107', 'Reserve' => '#dc3545'];
$categories = [];
$dataMap = [];
$wards2 = [];
$hasChart2Data = false;

$catResult = $conn->query("SELECT DISTINCT category FROM releases WHERE category IS NOT NULL AND category != ''");
while ($catRow = $catResult->fetch_assoc()) {
    $categories[] = $catRow['category'];
}
sort($categories);
$colorList = array_map(fn($c) => $categoryColors[$c] ?? '#888', $categories);

$query2 = "
    SELECT ward_name, category, dosage, SUM(item_count) AS usage_count
    FROM releases
    WHERE release_time BETWEEN ? AND ?
";
$params2 = [$startDate, $endDate];
$types2 = "ss";
if (!empty($selectedWard)) {
    $query2 .= " AND ward_name = ?";
    $params2[] = $selectedWard;
    $types2 .= "s";
}
$query2 .= " GROUP BY ward_name, category, dosage ORDER BY ward_name, category";

$stmt = $conn->prepare($query2);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $hasChart2Data = true;
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

// Prepare data for tables
$table1Data = [];
$table2Data = [];

// Table 1: Antibiotic-wise data
foreach ($wards1 as $ward) {
    $row = ['Ward' => $ward];
    $total = 0;
    foreach ($antibiotics as $antibiotic) {
        $value = isset($antibioticData[$antibiotic][$ward]) ? round($antibioticData[$antibiotic][$ward], 2) : 0;
        $row[$antibiotic] = $value;
        $total += $value;
    }
    $row['Total'] = round($total, 2);
    $table1Data[] = $row;
}

// Table 2: Category-wise data
foreach ($wards2 as $ward) {
    $row = ['Ward' => $ward];
    $total = 0;
    foreach ($categories as $category) {
        $value = isset($dataMap[$ward][$category]) ? round($dataMap[$ward][$category], 2) : 0;
        $row[$category] = $value;
        $total += $value;
    }
    $row['Total'] = round($total, 2);
    $table2Data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Antibiotic Usage Dashboard</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
    // Global variables to store chart instances
    let chart1Instance = null;
    let chart2Instance = null;

    google.charts.load("current", {packages: ['corechart']});
    google.charts.setOnLoadCallback(drawCharts);

    function drawCharts() {
        drawChart1();
        drawChart2();
    }

    function drawChart1() {
        var data = new google.visualization.DataTable();

        data.addColumn('string', 'Ward');

        <?php
        // Add data + annotation columns
        foreach ($antibiotics as $a) {
            echo "data.addColumn('number', '".addslashes($a)."');\n";
            echo "data.addColumn({type: 'string', role: 'annotation'});\n";
        }
        ?>

        data.addRows([
            <?php foreach ($wards1 as $ward): ?>
            [
                '<?= addslashes($ward) ?>',
                <?php foreach ($antibiotics as $a):
                    $value = isset($antibioticData[$a][$ward]) ? round($antibioticData[$a][$ward], 2) : 0;
                    $annotation = ($value > 0) ? $value : ''; // annotation = count value
                ?>
                    <?= $value ?>, '<?= $annotation ?>',
                <?php endforeach; ?>
            ],
            <?php endforeach; ?>
        ]);

        var options = {
            title: 'Antibiotic Usage by Ward - <?= "$startYear-$startMonth to $endYear-$endMonth" ?>',
            titleTextStyle: {
                fontSize: 18,
                bold: true,
                color: '#2c3e50'
            },
            hAxis: {
                title: 'Ward',
                titleTextStyle: { color: '#2c3e50', bold: true },
                textStyle: { 
                    fontSize: 11,
                    color: '#5d6d7e'
                },
                slantedText: true,
                slantedTextAngle: 45,
                gridlines: { color: '#ecf0f1' }
            },
            vAxis: { 
                title: 'Units (grams)',
                titleTextStyle: { color: '#2c3e50', bold: true },
                textStyle: { color: '#5d6d7e' },
                minValue: 0,
                gridlines: { color: '#ecf0f1' },
                format: '###,###'
            },
            isStacked: false,
            legend: { 
                position: 'top', 
                textStyle: { 
                    color: '#2c3e50',
                    fontSize: 12
                },
                maxLines: 3
            },
            height: 500,
            chartArea: { 
                left: 80, 
                right: 50, 
                top: 80, 
                bottom: 150,
                backgroundColor: {
                    stroke: '#bdc3c7',
                    strokeWidth: 1
                }
            },
            bar: { groupWidth: '65%' },
            annotations: {
                alwaysOutside: true,
                textStyle: {
                    fontSize: 10,
                    color: '#2c3e50',
                    auraColor: 'none'
                }
            },
            colors: [
                '#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c',
                '#d35400', '#c0392b', '#16a085', '#8e44ad', '#2c3e50', '#f1c40f',
                '#e67e22', '#e74c3c', '#ecf0f1', '#34495e', '#7f8c8d', '#d35400'
            ],
            backgroundColor: '#f8f9fa',
            tooltip: {
                textStyle: { color: '#2c3e50' },
                showColorCode: true
            }
        };

        chart1Instance = new google.visualization.ColumnChart(document.getElementById('chart1'));
        
        // Add resize handler
        google.visualization.events.addListener(chart1Instance, 'ready', function() {
            window.addEventListener('resize', function() {
                drawChart1();
            });
        });
        
        chart1Instance.draw(data, options);
    }

    function drawChart2() {
        var data = google.visualization.arrayToDataTable([
            ['Ward', <?php foreach ($categories as $cat) echo "'".addslashes($cat)."',"; ?> { role: 'annotation' }],
            <?php foreach ($wards2 as $ward): 
                $total = 0;
                foreach ($categories as $cat) {
                    $total += isset($dataMap[$ward][$cat]) ? $dataMap[$ward][$cat] : 0;
                }
            ?>
                ['<?= addslashes($ward) ?>',
                    <?php foreach ($categories as $cat): ?>
                        <?= isset($dataMap[$ward][$cat]) ? round($dataMap[$ward][$cat], 2) : 0 ?>,
                    <?php endforeach; ?>
                    '<?= round($total, 2) ?>'
                ],
            <?php endforeach; ?>
        ]);

        var options = {
            title: 'Category-wise Antibiotic Usage - <?= "$startYear-$startMonth to $endYear-$endMonth" ?>',
            titleTextStyle: {
                fontSize: 18,
                bold: true,
                color: '#2c3e50'
            },
            hAxis: { 
                title: 'Ward',
                titleTextStyle: { color: '#2c3e50', bold: true },
                textStyle: { 
                    fontSize: 12,
                    color: '#5d6d7e'
                },
                slantedText: true,
                slantedTextAngle: 45,
                gridlines: { color: '#ecf0f1' }
            },
            vAxis: { 
                title: 'Units (grams)',
                titleTextStyle: { color: '#2c3e50', bold: true },
                textStyle: { color: '#5d6d7e' },
                minValue: 0,
                gridlines: { color: '#ecf0f1' },
                format: '###,###'
            },
            isStacked: true,
            legend: { 
                position: 'top', 
                textStyle: { 
                    color: '#2c3e50',
                    fontSize: 12
                }
            },
            height: 500,
            chartArea: { 
                left: 80, 
                right: 50, 
                top: 80, 
                bottom: 150,
                backgroundColor: {
                    stroke: '#bdc3c7',
                    strokeWidth: 1
                }
            },
            bar: { groupWidth: '5%' },
            colors: <?= json_encode($colorList) ?>,
            backgroundColor: '#f8f9fa',
            tooltip: {
                textStyle: { color: '#2c3e50' },
                isHtml: true
            },
            annotations: {
                alwaysOutside: true,
                textStyle: {
                    fontSize: 11,
                    color: '#2c3e50',
                    bold: true
                }
            }
        };

        chart2Instance = new google.visualization.ColumnChart(document.getElementById('chart2'));
        
        // Add resize handler
        google.visualization.events.addListener(chart2Instance, 'ready', function() {
            window.addEventListener('resize', function() {
                drawChart2();
            });
        });
        
        chart2Instance.draw(data, options);
    }

    // Download Chart as Image
    function downloadChart(chartInstance, filename) {
        if (!chartInstance) {
            showNotification('Chart is not available for download', 'warning');
            return;
        }

        try {
            const chartElement = chartInstance.getContainer();
            html2canvas(chartElement, {
                scale: 2, // Higher quality
                useCORS: true,
                logging: false
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = filename + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                showNotification('Chart downloaded successfully!', 'success');
            });
        } catch (error) {
            console.error('Error downloading chart:', error);
            showNotification('Error downloading chart', 'error');
        }
    }

    // Download Chart 1 as Image
    function downloadChart1() {
        downloadChart(chart1Instance, 'Antibiotic_Usage_Chart_<?= $startYear . $startMonth . '_' . $endYear . $endMonth ?>');
    }

    // Download Chart 2 as Image
    function downloadChart2() {
        downloadChart(chart2Instance, 'Category_Usage_Chart_<?= $startYear . $startMonth . '_' . $endYear . $endMonth ?>');
    }

    // Export Table 2 to Excel
    function exportTable2ToExcel() {
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Table 2 data
        csvContent += "Category-wise Antibiotic Usage by Ward (grams)\r\n";
        csvContent += "Period: <?= "$startYear-$startMonth to $endYear-$endMonth" ?>\r\n";
        csvContent += "Generated on: <?= date('Y-m-d H:i:s') ?>\r\n\r\n";
        
        // Table 2 headers
        let headers2 = ["Ward"];
        <?php foreach ($categories as $category): ?>
            headers2.push("<?= addslashes($category) ?>");
        <?php endforeach; ?>
        headers2.push("Total");
        csvContent += headers2.join(",") + "\r\n";
        
        // Table 2 rows
        <?php foreach ($table2Data as $row): ?>
            let row2 = ["<?= addslashes($row['Ward']) ?>"];
            <?php foreach ($categories as $category): ?>
                row2.push("<?= $row[$category] ?>");
            <?php endforeach; ?>
            row2.push("<?= $row['Total'] ?>");
            csvContent += row2.join(",") + "\r\n";
        <?php endforeach; ?>

        // Add totals row
        csvContent += "Grand Total,";
        <?php 
        $columnTotals2 = [];
        foreach ($categories as $category) {
            $total = 0;
            foreach ($table2Data as $row) {
                $total += $row[$category];
            }
            $columnTotals2[$category] = round($total, 2);
        }
        ?>
        <?php foreach ($categories as $category): ?>
            csvContent += "<?= $columnTotals2[$category] ?>,";
        <?php endforeach; ?>
        <?php
        $grandTotal2 = 0;
        foreach ($table2Data as $row) {
            $grandTotal2 += $row['Total'];
        }
        ?>
        csvContent += "<?= round($grandTotal2, 2) ?>\r\n";
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "category_usage_data_<?= $startYear . $startMonth . '_' . $endYear . $endMonth ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('Table 2 data exported successfully!', 'success');
    }

    // Initialize DataTables
    $(document).ready(function() {
        $('#table1').DataTable({
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "order": [[0, "asc"]],
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"t>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "language": {
                "search": "<i class='fa fa-search'></i> Search:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoEmpty": "Showing 0 to 0 of 0 entries",
                "infoFiltered": "(filtered from _MAX_ total entries)",
                "paginate": {
                    "previous": "<i class='fa fa-chevron-left'></i>",
                    "next": "<i class='fa fa-chevron-right'></i>"
                }
            },
            "responsive": true,
            "autoWidth": false
        });
        
        $('#table2').DataTable({
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "order": [[0, "asc"]],
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"t>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "language": {
                "search": "<i class='fa fa-search'></i> Search:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoEmpty": "Showing 0 to 0 of 0 entries",
                "infoFiltered": "(filtered from _MAX_ total entries)",
                "paginate": {
                    "previous": "<i class='fa fa-chevron-left'></i>",
                    "next": "<i class='fa fa-chevron-right'></i>"
                }
            },
            "responsive": true,
            "autoWidth": false
        });

        // Summary cards animation
        $('.summary-card').hover(
            function() {
                $(this).addClass('shadow-lg').css('transform', 'translateY(-5px)');
            },
            function() {
                $(this).removeClass('shadow-lg').css('transform', 'translateY(0)');
            }
        );
    });

    // Export functionality for both tables
    function exportToExcel() {
        // Create CSV content
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Table 1 data
        csvContent += "Antibiotic Usage by Ward (grams)\r\n";
        csvContent += "Period: <?= "$startYear-$startMonth to $endYear-$endMonth" ?>\r\n";
        csvContent += "Generated on: <?= date('Y-m-d H:i:s') ?>\r\n\r\n";
        
        // Table 1 headers
        let headers1 = ["Ward"];
        <?php foreach ($antibiotics as $antibiotic): ?>
            headers1.push("<?= addslashes($antibiotic) ?>");
        <?php endforeach; ?>
        headers1.push("Total");
        csvContent += headers1.join(",") + "\r\n";
        
        // Table 1 rows
        <?php foreach ($table1Data as $row): ?>
            let row1 = ["<?= addslashes($row['Ward']) ?>"];
            <?php foreach ($antibiotics as $antibiotic): ?>
                row1.push("<?= $row[$antibiotic] ?>");
            <?php endforeach; ?>
            row1.push("<?= $row['Total'] ?>");
            csvContent += row1.join(",") + "\r\n";
        <?php endforeach; ?>
        
        csvContent += "\r\n\r\n";
        
        // Table 2 data
        csvContent += "Category Usage by Ward (grams)\r\n";
        csvContent += "Period: <?= "$startYear-$startMonth to $endYear-$endMonth" ?>\r\n\r\n";
        
        // Table 2 headers
        let headers2 = ["Ward"];
        <?php foreach ($categories as $category): ?>
            headers2.push("<?= addslashes($category) ?>");
        <?php endforeach; ?>
        headers2.push("Total");
        csvContent += headers2.join(",") + "\r\n";
        
        // Table 2 rows
        <?php foreach ($table2Data as $row): ?>
            let row2 = ["<?= addslashes($row['Ward']) ?>"];
            <?php foreach ($categories as $category): ?>
                row2.push("<?= $row[$category] ?>");
            <?php endforeach; ?>
            row2.push("<?= $row['Total'] ?>");
            csvContent += row2.join(",") + "\r\n";
        <?php endforeach; ?>
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "antibiotic_usage_<?= $startYear . $startMonth . '_' . $endYear . $endMonth ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Show success message
        showNotification('Export completed successfully!', 'success');
    }

    function showNotification(message, type) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.alert.position-fixed');
        existingNotifications.forEach(notification => notification.remove());

        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    </script>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .chart-container {
            margin: 20px auto;
            overflow-x: auto;
            border: 1px solid #e3e6f0;
            border-radius: 12px;
            padding: 20px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
        }

        .chart-container:hover {
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }

        #chart1, #chart2 {
            width: 100%;
            min-width: 800px;
        }

        .data-card {
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: none;
            transition: all 0.3s ease;
            background: white;
        }

        .data-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .card-header {
            background: skyblue;
            color: white;
            border-bottom: none;
            padding: 20px 25px;
            border-radius: 12px 12px 0 0 !important;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .card-header-content {
            flex: 1;
        }

        .card-header h5 {
            font-weight: 600;
            margin: 0;
            font-size: 1.25rem;
        }

        .chart-actions {
            display: flex;
            gap: 8px;
            margin-left: 15px;
        }

        .chart-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .chart-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .data-table {
            margin-top: 25px;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
            font-style: italic;
            background: var(--light-bg);
            border-radius: 8px;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #bdc3c7;
        }

        .filter-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 25px;
            margin-bottom: 25px;
            border: none;
        }

        .export-buttons {
            margin-top: 15px;
        }

        .table-responsive {
            border-radius: 0 0 12px 12px;
            border: 1px solid #e3e6f0;
        }

        .table th {
            background: linear-gradient(135deg, var(--light-bg) 0%, #e9ecef 100%);
            color: var(--secondary-color);
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
            padding: 15px 12px;
        }

        .table td {
            padding: 12px;
            vertical-align: middle;
        }

        .chart-title {
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--secondary-color);
            font-size: 1.1rem;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: white;
        }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .table-action-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .table-action-btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }

        .summary-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .summary-card .label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #2980b9 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 8px;
        }

        .form-select, .form-control {
            border-radius: 8px;
            border: 1px solid #e3e6f0;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 15px;
        }

        .pagetitle {
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .chart-container {
                padding: 15px;
                margin: 10px auto;
            }
            
            .filter-card {
                padding: 20px 15px;
            }
            
            .card-header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .chart-actions {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                justify-content: flex-end;
            }
            
            .summary-card {
                padding: 15px;
            }
            
            .summary-card .number {
                font-size: 1.5rem;
            }

            .chart-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .table-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                <li class="breadcrumb-item active">Antibiotic Usage Dashboard</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="summary-card">
                    <i class="fas fa-hospital"></i>
                    <div class="number"><?= count($wards1) ?></div>
                    <div class="label">Active Wards</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="summary-card">
                    <i class="fas fa-pills"></i>
                    <div class="number"><?= count($antibiotics) ?></div>
                    <div class="label">Antibiotics Tracked</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="summary-card">
                    <i class="fas fa-list-alt"></i>
                    <div class="number"><?= count($categories) ?></div>
                    <div class="label">Usage Categories</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="summary-card">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="number"><?= "$startMonth/$startYear - $endMonth/$endYear" ?></div>
                    <div class="label">Reporting Period</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <h5 class="card-title mb-3"><i class="fas fa-filter me-2"></i>Filter Options</h5>
            <form method="POST" class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <label for="ward_name" class="form-label">Ward Selection</label>
                    <select name="ward_name" id="ward_name" class="form-select" required>
                        <option value="" disabled <?= empty($selectedWard) ? 'selected' : '' ?>>-- Select Ward --</option>
                        <?php foreach ($wardList as $ward):
                            $sel = ($ward == $selectedWard) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($ward) ?>" <?= $sel ?>><?= htmlspecialchars($ward) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="start_year" class="form-label">Start Year</label>
                    <select name="start_year" id="start_year" class="form-select">
                        <?php for ($y = 2020; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $startYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="start_month" class="form-label">Start Month</label>
                    <select name="start_month" id="start_month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): $val = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?= $val ?>" <?= $startMonth == $val ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="end_year" class="form-label">End Year</label>
                    <select name="end_year" id="end_year" class="form-select">
                        <?php for ($y = 2020; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $endYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="end_month" class="form-label">End Month</label>
                    <select name="end_month" id="end_month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): $val = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?= $val ?>" <?= $endMonth == $val ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-lg-1 col-md-12 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Apply
                    </button>
                </div>

                <div class="col-12 mt-3 pt-3 border-top">
                    <div class="export-buttons d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-2"></i>Export All to Excel
                        </button>
                        <button type="button" class="btn btn-info" onclick="drawCharts()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh Charts
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Chart 1: Antibiotic-wise by Ward -->
        <div class="card data-card">
            <div class="card-header">
                <div class="card-header-content">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Antibiotic Usage by Ward</h5>
                    <p class="text-light mb-0 opacity-75">Period: <?= "$startYear-$startMonth to $endYear-$endMonth" ?></p>
                </div>
                <div class="chart-actions">
                    <button type="button" class="chart-action-btn" onclick="downloadChart1()" title="Download Chart as Image">
                        <i class="fas fa-download"></i>
                        <span>Download Chart</span>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container" id="chart1-container">
                    <?php if ($hasChart1Data): ?>
                        <div id="chart1"></div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-chart-bar"></i>
                            <h5>No Data Available</h5>
                            <p>Please adjust your filter criteria to view the chart</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($hasChart1Data): ?>
                <div class="data-table">
                    <h5 class="chart-title"><i class="fas fa-table me-2"></i>Data Table: Antibiotic Usage by Ward (grams)</h5>
                    <div class="table-responsive">
                        <table id="table1" class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Ward</th>
                                    <?php foreach ($antibiotics as $antibiotic): ?>
                                        <th><?= htmlspecialchars($antibiotic) ?></th>
                                    <?php endforeach; ?>
                                    <th class="bg-primary text-white">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($table1Data as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['Ward']) ?></strong></td>
                                        <?php foreach ($antibiotics as $antibiotic): ?>
                                            <td><?= $row[$antibiotic] ?></td>
                                        <?php endforeach; ?>
                                        <td><strong class="text-primary"><?= $row['Total'] ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <th>Grand Total</th>
                                    <?php 
                                    $columnTotals = [];
                                    foreach ($antibiotics as $antibiotic) {
                                        $total = 0;
                                        foreach ($table1Data as $row) {
                                            $total += $row[$antibiotic];
                                        }
                                        $columnTotals[$antibiotic] = round($total, 2);
                                    }
                                    ?>
                                    <?php foreach ($antibiotics as $antibiotic): ?>
                                        <th><?= $columnTotals[$antibiotic] ?></th>
                                    <?php endforeach; ?>
                                    <?php
                                    $grandTotal = 0;
                                    foreach ($table1Data as $row) {
                                        $grandTotal += $row['Total'];
                                    }
                                    ?>
                                    <th><?= round($grandTotal, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart 2: Category-wise by Ward -->
        <div class="card data-card">
            <div class="card-header">
                <div class="card-header-content">
                    <h5 class="card-title mb-0"><i class="fas fa-layer-group me-2"></i>Category-wise Usage by Ward</h5>
                    <p class="text-light mb-0 opacity-75">Period: <?= "$startYear-$startMonth to $endYear-$endMonth" ?><?= (!empty($selectedWard) && $selectedWard !== 'All') ? " | Ward: $selectedWard" : " | All Wards" ?></p>
                </div>
                <div class="chart-actions">
                    <button type="button" class="chart-action-btn" onclick="downloadChart2()" title="Download Chart as Image">
                        <i class="fas fa-download"></i>
                        <span>Download Chart</span>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container" id="chart2-container">
                    <?php if ($hasChart2Data): ?>
                        <div id="chart2"></div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-chart-pie"></i>
                            <h5>No Data Available</h5>
                            <p>Please adjust your filter criteria to view the chart</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($hasChart2Data): ?>
                <div class="data-table">
                    <h5 class="chart-title">
                        <i class="fas fa-table me-2"></i>Data Table: Category Usage by Ward (grams)
                        <div class="table-actions">
                            <button type="button" class="table-action-btn" onclick="exportTable2ToExcel()">
                                <i class="fas fa-file-excel"></i>
                                Export Table
                            </button>
                        </div>
                    </h5>
                    <div class="table-responsive">
                        <table id="table2" class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Ward</th>
                                    <?php foreach ($categories as $category): ?>
                                        <th><?= htmlspecialchars($category) ?></th>
                                    <?php endforeach; ?>
                                    <th class="bg-primary text-white">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($table2Data as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['Ward']) ?></strong></td>
                                        <?php foreach ($categories as $category): ?>
                                            <td><?= $row[$category] ?></td>
                                        <?php endforeach; ?>
                                        <td><strong class="text-primary"><?= $row['Total'] ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <th>Grand Total</th>
                                    <?php 
                                    $columnTotals2 = [];
                                    foreach ($categories as $category) {
                                        $total = 0;
                                        foreach ($table2Data as $row) {
                                            $total += $row[$category];
                                        }
                                        $columnTotals2[$category] = round($total, 2);
                                    }
                                    ?>
                                    <?php foreach ($categories as $category): ?>
                                        <th><?= $columnTotals2[$category] ?></th>
                                    <?php endforeach; ?>
                                    <?php
                                    $grandTotal2 = 0;
                                    foreach ($table2Data as $row) {
                                        $grandTotal2 += $row['Total'];
                                    }
                                    ?>
                                    <th><?= round($grandTotal2, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include_once("../includes/js-links-inc.php"); ?>
<?php include_once("../includes/footer.php"); ?>
</body>
</html>