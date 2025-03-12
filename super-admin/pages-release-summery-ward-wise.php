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
$sql = "SELECT name, email, nic, mobile,profile_picture FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Determine which filter method is being used
$filterType = $_POST['filter_type'] ?? 'date'; // Default to date range

// Get date range values
$startDate = $_POST['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$endDate = $_POST['end_date'] ?? date('Y-m-t');     // Default to last day of current month

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

// Query to get total usage count per ward for percentage calculation
$wardUsageQuery = "
    SELECT ward_name, SUM(item_count) AS ward_total
    FROM releases
    WHERE release_time BETWEEN ? AND ?
    AND (ward_name = ? OR ? = '')
    GROUP BY ward_name
";
$wardUsageStmt = $conn->prepare($wardUsageQuery);
$wardUsageStmt->bind_param("ssss", $startDate, $endDate, $selectedWard, $selectedWard);
$wardUsageStmt->execute();
$wardUsageResult = $wardUsageStmt->get_result();

$wardUsage = [];
while ($row = $wardUsageResult->fetch_assoc()) {
    $wardUsage[$row['ward_name']] = $row['ward_total'];
}

$stmt->close();
$wardStmt->close();
$wardUsageStmt->close();
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.1/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    

    <style>
        #piechart { width: 50%; height: 400px; margin: auto; }
        @media print { .no-print { display: none; } }
        @media only screen and (min-width: 768px) {
            .select-bar {display: flex; flex-wrap: wrap;}
        } 
        .form-group {
            margin-right: 15px;
            margin-bottom: 15px;
        }
        #dateRangeFilters, #monthRangeFilters {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
    </style>
    <script> function printPage() { window.print(); } </script>
</head>

<body>
    <?php include_once("../includes/header.php") ?>
    <?php include_once("../includes/sadmin-sidebar.php") ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Usage Details Ward Wise</h1>
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
                                <div class="form-row mb-3">
                                    <div class="form-group col-md-6 ">
                                        <label>Choose Filter Type:</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_type" id="filterDate" value="date" <?php echo ($filterType == 'date') ? 'checked' : ''; ?> onchange="toggleFilterType()">
                                            <label class="form-check-label" for="filterDate">
                                                Date Range
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_type" id="filterMonth" value="month" <?php echo ($filterType == 'month') ? 'checked' : ''; ?> onchange="toggleFilterType()">
                                            <label class="form-check-label" for="filterMonth">
                                                Month Range
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Date Range Filters -->
                                <div id="dateRangeFilters" class="form-row mb-3 select-bar" <?php echo ($filterType == 'date') ? '' : 'style="display: none;"'; ?>>
                                    <div class="form-group col-md-3">
                                        <label for="start_date" class="col-form-label">Start Date:</label>
                                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $startDate; ?>">
                                    </div>
                                    
                                    <div class="form-group col-md-3">
                                        <label for="end_date" class="col-form-label">End Date:</label>
                                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $endDate; ?>">
                                    </div>
                                </div>

                                <!-- Month Range Filters -->
                                <div id="monthRangeFilters" class="form-row mb-3 select-bar" <?php echo ($filterType == 'month') ? '' : 'style="display: none;"'; ?>>
                                    <div class="form-group col-md-3">
                                        <label for="start_month" class="col-form-label">Start Month:</label>
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
                                    
                                    <div class="form-group col-md-3">
                                        <label for="start_year" class="col-form-label">Start Year:</label>
                                        <select name="start_year" id="start_year" class="form-select">
                                            <?php
                                            $currentYear = date('Y');
                                            for ($i = 2020; $i <= $currentYear; $i++) {
                                                echo "<option value='$i'" . ($i == $startYear ? ' selected' : '') . ">$i</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group col-md-3">
                                        <label for="end_month" class="col-form-label">End Month:</label>
                                        <select name="end_month" id="end_month" class="form-select">
                                            <?php
                                            foreach ($months as $monthNum => $monthName) {
                                                echo "<option value='$monthNum'" . ($monthNum == $endMonth ? ' selected' : '') . ">$monthName</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group col-md-3">
                                        <label for="end_year" class="col-form-label">End Year:</label>
                                        <select name="end_year" id="end_year" class="form-select">
                                            <?php
                                            for ($i = 2020; $i <= $currentYear; $i++) {
                                                echo "<option value='$i'" . ($i == $endYear ? ' selected' : '') . ">$i</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row mb-3 select-bar">
                                    <div class="form-group col-md-3">
                                        <label for="ward_select" class="col-form-label">Select Ward:</label>
                                        <select name="ward_select" id="ward_select" class="form-select">
                                            <option value="">All Wards</option>
                                            <?php
                                            $wardResult->data_seek(0); // Reset result pointer
                                            while ($wardRow = $wardResult->fetch_assoc()) {
                                                $wardName = $wardRow['ward_name'];
                                                echo "<option value='$wardName'" . ($wardName == $selectedWard ? ' selected' : '') . ">$wardName</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                  
                                    <!-- Added MSD/LP Filter -->
                                    <div class="form-group col-md-3">
                                        <label for="type_select" class="col-form-label">Select Stock:</label>
                                        <select name="type_select" id="type_select" class="form-select">
                                            <option value="">All Types</option>
                                            <option value="msd" <?php echo ($selectedType == 'msd') ? 'selected' : ''; ?>>MSD</option>
                                            <option value="lp" <?php echo ($selectedType == 'lp') ? 'selected' : ''; ?>>LP</option>
                                        </select>
                                    </div>
                                  
                                    <div class="form-group col-md-3">
                                       <label for="route_select" class="col-form-label">Select Route:</label>
                                       <select name="ant_type_select" id="ant_type_select" class="form-select">
                                        <option value="">All Types</option>
                                        <option value="oral" <?php echo ($selectedAntType == 'oral') ? 'selected' : ''; ?>>Oral</option>
                                        <option value="intravenous" <?php echo ($selectedAntType == 'intravenous') ? 'selected' : ''; ?>>Intravenous</option>
                                        <option value="topical" <?php echo ($selectedAntType == 'topical') ? 'selected' : ''; ?>>Topical</option>
                                        <option value="other" <?php echo ($selectedAntType == 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <button type="submit" class="btn btn-primary mt-2">Filter</button>
                                        <button type="button" class="btn btn-danger mt-2 ml-2 print-btn no-print" onclick="printPage()">Print Report</button>
                                        <button type="button" class="btn btn-success mt-2 ml-2" onclick="exportTableToExcel()">Export to Excel</button>
                                        <button type="button" class="btn btn-warning mt-2 ml-2" onclick="exportTableToPDF()">Export to PDF</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- DataTable -->
                        <div style="padding: 15px;">
                          <table class="table datatable">
                            <thead class="align-middle text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Ward Name</th>
                                    <th>Antibiotic Name</th>
                                    <th>Dosage</th>
                                    <th>Usage Count</th>
                                    <th>Percentage (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php 
                                if ($result->num_rows > 0) {
                                    $rowNumber = 1;
                                    $previousWard = "";
                                    while ($row = $result->fetch_assoc()) {
                                        $wardName = $row['ward_name'];
                                        $totalUsageInWard = $wardUsage[$wardName];
                                        $percentage = round(($row['usage_count'] / $totalUsageInWard) * 100, 2);
                                        
                                        // Group by ward name
                                        if ($wardName != $previousWard) {
                                            // Insert a row for the ward name (row with colspan)
                                            echo "<tr><td colspan='6' class='text-center card-title' style='background-color: #f8f9fa; font-weight: bold;'>$wardName</td></tr>";
                                            $previousWard = $wardName;
                                        }

                                        // Regular data row
                                        echo "<tr>";
                                        echo "<td class='text-center'>{$rowNumber}</td>";
                                        echo "<td class='text-center'>{$row['ward_name']}</td>";
                                        echo "<td class='text-center'>{$row['antibiotic_name']}</td>";
                                        echo "<td class='text-center'>{$row['dosage']}</td>";
                                        echo "<td class='text-center'>{$row['usage_count']}</td>";
                                        echo "<td class='text-center'>{$percentage}%</td>";
                                        echo "</tr>";
                                        $rowNumber++;
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center'>No data available for the selected period, ward, and type</td></tr>";
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
    <?php include_once("../includes/js-links-inc.php") ?>
    <?php include_once("../includes/footer.php") ?>
    <script type="text/javascript">
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
        
        function exportTableToExcel() {
            // Get the table element
            let table = document.querySelector(".datatable");
            
            // Convert the table to a worksheet
            let workbook = XLSX.utils.book_new();
            let worksheet = XLSX.utils.table_to_sheet(table);
            
            // Add the worksheet to the workbook
            XLSX.utils.book_append_sheet(workbook, worksheet, "Antibiotic_Usage");

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
            XLSX.writeFile(workbook, "Antibiotic_Usage_" + dateInfo + ".xlsx");
        }
    </script>
    <script>
        function exportTableToPDF() {
            const { jsPDF } = window.jspdf;
            let doc = new jsPDF();

            // Title
            doc.setFont("helvetica", "bold");
            doc.setFontSize(16);
            doc.text("Antibiotic Usage Report", 105, 10, { align: "center" });

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
            doc.text(dateInfo, 105, 20, { align: "center" });

            // Get table data
            let table = document.querySelector(".datatable");
            let data = [];
            let headers = [];

            // Get headers
            let headerCells = table.querySelectorAll("thead tr th");
            headerCells.forEach(header => headers.push(header.innerText));
            
            // Get rows
            let rows = table.querySelectorAll("tbody tr");
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
                startY: 30,
                theme: "striped",
                styles: { fontSize: 10 },
                headStyles: { fillColor: [44, 62, 80], textColor: 255, fontStyle: "bold" },
                alternateRowStyles: { fillColor: [240, 240, 240] },
            });

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
            doc.save("Antibiotic_Usage_" + filenameDateInfo + ".pdf");
        }
    </script>
</body>
</html>