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
$sql = "SELECT name, email, nic, mobile FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get selected month, year, ward, type, and ant_type
$selectedMonth = $_POST['month_select'] ?? date('m');
$selectedYear = $_POST['year_select'] ?? date('Y');
$selectedWard = $_POST['ward_select'] ?? '';
$selectedType = $_POST['type_select'] ?? ''; // Added for MSD/LP
$selectedAntType = $_POST['ant_type_select'] ?? ''; // Added for Antibiotic Type (ant_type)

// Fetch all wards from the database
$wardQuery = "SELECT DISTINCT ward_name FROM releases ORDER BY ward_name";
$wardStmt = $conn->prepare($wardQuery);
$wardStmt->execute();
$wardResult = $wardStmt->get_result();

// Query for antibiotic usage filtered by selected month, year, ward, type, and ant_type
$query = "
    SELECT ward_name, antibiotic_name, dosage, type, ant_type, SUM(item_count) AS usage_count
    FROM releases
    WHERE MONTH(release_time) = ? AND YEAR(release_time) = ?
    AND (ward_name = ? OR ? = '')
    AND (type = ? OR ? = '')
    AND (ant_type = ? OR ? = '')
    GROUP BY ward_name, antibiotic_name, dosage, type, ant_type
    ORDER BY ward_name, antibiotic_name ASC, usage_count DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iissssss", $selectedMonth, $selectedYear, $selectedWard, $selectedWard, $selectedType, $selectedType, $selectedAntType, $selectedAntType);
$stmt->execute();
$result = $stmt->get_result();

// Query to get total usage count per ward for percentage calculation
$wardUsageQuery = "
    SELECT ward_name, SUM(item_count) AS ward_total
    FROM releases
    WHERE MONTH(release_time) = ? AND YEAR(release_time) = ?
    AND (ward_name = ? OR ? = '')
    GROUP BY ward_name
";
$wardUsageStmt = $conn->prepare($wardUsageQuery);
$wardUsageStmt->bind_param("iiss", $selectedMonth, $selectedYear, $selectedWard, $selectedWard);
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
            .select-bar {display: flex;}
        } 
    </style>
    <script> function printPage() { window.print(); } </script>
    
</head>

<body>

    <!-- Displaying the message from the session -->
    <?php if (isset($_SESSION['status'])): ?>
        <div class="popup-message <?php echo ($_SESSION['status'] == 'success') ? '' : 'error-popup'; ?>" id="popup-alert">
            <?php echo $_SESSION['message']; ?>
        </div>

        <script>
            // Display the popup message
            document.getElementById('popup-alert').style.display = 'block';

            // Automatically hide the popup after 10 seconds
            setTimeout(function() {
                const popupAlert = document.getElementById('popup-alert');
                if (popupAlert) {
                    popupAlert.style.display = 'none';
                }
            }, 500);

            // If success message, redirect to index.php after 10 seconds
            <?php if ($_SESSION['status'] == 'success'): ?>
                setTimeout(function() {
                    window.location.href = 'pages-add-antibiotic.php'; // Redirect after 10 seconds
                }, 500); // Delay 10 seconds before redirecting
            <?php endif; ?>
        </script>

        <?php
        // Clear session variables after showing the message
        unset($_SESSION['status']);
        unset($_SESSION['message']);
        ?>
    <?php endif; ?>


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
                                <div class="form-row mb-3 select-bar">
                                     <div class="col-sm-3">
                                        <label for="year_select" class="col-form-label">Select Year:</label>
                                        <select name="year_select" id="year_select" class="form-select">
                                            <?php
                                            $currentYear = date('Y');
                                            for ($i = 2020; $i <= $currentYear; $i++) {
                                                echo "<option value='$i'" . ($i == $selectedYear ? ' selected' : '') . ">$i</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                  
                                    <div class="col-sm-3">
                                        <label for="month_select" class="col-form-label">Select Month:</label>
                                        <select name="month_select" id="month_select" class="form-select">
                                            <?php
                                            $months = [
                                                '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                                                '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                                                '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                                            ];
                                            foreach ($months as $monthNum => $monthName) {
                                                echo "<option value='$monthNum'" . ($monthNum == $selectedMonth ? ' selected' : '') . ">$monthName</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                  
                                    <div class="col-sm-3">
                                        <label for="ward_select" class="col-form-label">Select Ward:</label>
                                        <select name="ward_select" id="ward_select" class="form-select">
                                            <option value="">All Wards</option>
                                            <?php
                                            while ($wardRow = $wardResult->fetch_assoc()) {
                                                $wardName = $wardRow['ward_name'];
                                                echo "<option value='$wardName'" . ($wardName == $selectedWard ? ' selected' : '') . ">$wardName</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                  
                                    <!-- Added MSD/LP Filter -->
                                    <div class="col-sm-3">
                                        <label for="type_select" class="col-form-label">Select Stock:</label>
                                        <select name="type_select" id="type_select" class="form-select">
                                            <option value="">All Types</option>
                                            <option value="msd" <?php echo ($selectedType == 'msd') ? 'selected' : ''; ?>>MSD</option>
                                            <option value="lp" <?php echo ($selectedType == 'lp') ? 'selected' : ''; ?>>LP</option>
                                        </select>
                                  </div>
                                </div>
                                <div class="col-sm-3">
                                   <label for="route_select" class="col-form-label">Select Route:</label>
                                   <select name="ant_type_select" id="ant_type_select" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="oral" <?php echo ($selectedAntType == 'oral') ? 'selected' : ''; ?>>Oral</option>
                                    <option value="intravenous" <?php echo ($selectedAntType == 'intravenous') ? 'selected' : ''; ?>>Intravenous</option>
                                    <option value="topical" <?php echo ($selectedAntType == 'topical') ? 'selected' : ''; ?>>Topical</option>
                                    <option value="other" <?php echo ($selectedAntType == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                </div>

                                <div class="col-sm-7">
                                    <button type="submit" class="btn btn-primary mt-4">Filter</button>
                                    <button class="btn btn-danger mt-4 ml-2 print-btn no-print" onclick="printPage()">Print Report</button>
                                    <button class="btn btn-success mt-4 ml-2" onclick="exportTableToExcel()">Export to Excel</button>
                                    <button class="btn btn-warning mt-4 ml-2" onclick="exportTableToPDF()">Export to PDF</button>
                                </div>
                            </form>
                        </div>

    <?php include_once("../includes/footer2.php") ?>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  
    
    <script>
    document.getElementById('addDosageBtn').addEventListener('click', function() {
        const dosageFields = document.getElementById('dosageFields');
        
        // Create a new row div to contain the input field and remove button
        const newRow = document.createElement('div');
        newRow.classList.add('row', 'mb-6', 'align-items-center'); // Bootstrap row for alignment

        // Create a column div for the dosage input (col-md-2)
        const newCol = document.createElement('div');
        newCol.classList.add('col-md-5');

        // Add input field
        newCol.innerHTML = `
            <label class="form-label">Dosage</label>
            <input type="text" class="form-control" name="dosage[]" required placeholder="eg: 10mg">
        `;

        // Create a remove button with Bootstrap and set fixed width and same height as Add Dosage button
        const removeButton = document.createElement('button');
        removeButton.classList.add('btn', 'btn-danger', 'btn-sm', 'mt-2', 'ms-2');
        removeButton.innerHTML = 'Remove';

        // Set fixed width to 100px and same height as Add Dosage button
        removeButton.style.width = '100px';  // Custom width of 100px
        removeButton.style.height = '38px';  // Match height with Add Dosage button

        // Add event listener to remove the dosage field
        removeButton.addEventListener('click', function () {
            dosageFields.removeChild(newRow); // Remove the entire row (input + button)
        });

        // Create a column for the Remove button and ensure alignment
        const removeCol = document.createElement('div');
        removeCol.classList.add('col-md-2');
        removeCol.appendChild(removeButton);  // Append Remove button to column

        // Create a wrapper column for both input and button to keep them aligned properly
        const wrapperCol = document.createElement('div');
        wrapperCol.classList.add('col-md-4');
        
        // Append dosage input column and remove button column to the wrapper
        wrapperCol.appendChild(newCol);
        wrapperCol.appendChild(removeCol);

        // Append both dosage input and remove button to the new row
        newRow.appendChild(wrapperCol);

        // Append the new row to the dosage fields container
        dosageFields.appendChild(newRow);
    });
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // On form submit
            $("#signup-form").submit(function(event) {
                event.preventDefault(); // Prevent form submission

                $.ajax({
                    url: "submit.php", // Send form data to register.php
                    type: "POST",
                    data: $(this).serialize(), // Serialize the form data
                    dataType: "json", // Expect JSON response
                    success: function(response) {
                        let popupAlert = $("#popup-alert");

                        // Set the message class and text based on the response status
                        if (response.status === "success") {
                            popupAlert.removeClass("alert-error").addClass("alert-success").html(response.message);
                        } else {
                            popupAlert.removeClass("alert-success").addClass("alert-error").html(response.message);
                        }

                        // Show the alert
                        popupAlert.show();

                        // Hide the alert after 10 seconds
                        setTimeout(function() {
                            popupAlert.fadeOut();
                        }, 10000);

                        // If success, redirect after message disappears
                        if (response.status === "success") {
                            setTimeout(function() {
                                window.location.href = "pages-add-antibiotic.php"; // Change this to your target redirect URL
                            }, 10000); // Same 10 seconds delay before redirect
                        }
                    },
                    error: function(xhr, status, error) {
                        alert("AJAX Error: " + xhr.responseText); // Handle AJAX error
                    }
                });
            });
        });
    </script>



</body>

</html>
