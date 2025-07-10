<?php
session_start();

// Set PHP timezone to Sri Lanka
date_default_timezone_set('Asia/Colombo');

require_once '../includes/db-conn.php';

// Set MySQL timezone for the current session to Sri Lanka time zone (+05:30)
$conn->query("SET time_zone = '+05:30'");

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['admin_id'];
$sql = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get filters from POST or set defaults
$filter_date = $_POST['filter_date'] ?? '';
// Get filters from POST or set defaults
$filter_date = $_POST['filter_date'] ?? '';
$filter_year = $_POST['filter_year'] ?? '';
$filter_month = $_POST['filter_month'] ?? '';


// Build dynamic SQL with filters
$filter_date = $_POST['filter_date'] ?? '';
$start_year = $_POST['start_year'] ?? '';
$start_month = $_POST['start_month'] ?? '';
$end_year = $_POST['end_year'] ?? '';
$end_month = $_POST['end_month'] ?? '';

$sql = "SELECT * FROM returns WHERE 1=1 ";
$params = [];
$types = "";

// If specific date selected
if (!empty($filter_date)) {
    $sql .= " AND DATE(return_time) = ? ";
    $params[] = $filter_date;
    $types .= "s";
}
// If month range selected
elseif (!empty($start_month) && !empty($start_year) && !empty($end_month) && !empty($end_year)) {
    $start_date = sprintf('%04d-%02d-01', $start_year, $start_month);
    $end_date = date("Y-m-t", strtotime(sprintf('%04d-%02d-01', $end_year, $end_month))); // Last day of end month

    $sql .= " AND return_time BETWEEN ? AND ? ";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}
// Default to current date
else {
    $today = date('Y-m-d');
    $sql .= " AND DATE(return_time) = ? ";
    $params[] = $today;
    $types .= "s";
}

$sql .= " ORDER BY return_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();



?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title>Antibiotic Return Details - Mediq</title>

  <?php include_once("../includes/css-links-inc.php"); ?>

  <!-- jsPDF and autotable for PDF export -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

</head>

<body>
  <?php include_once("../includes/header.php"); ?>
  <?php include_once("../includes/sadmin-sidebar.php"); ?>

  <main id="main" class="main">
    <div class="pagetitle">
      <h1>Return Details</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item">Pages</li>
          <li class="breadcrumb-item active">Antibiotic Return Details</li>
        </ol>
      </nav>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Antibiotic Return Details</h5>

              <!-- Filter Form -->
                <form method="POST" class="mb-3">
                <div class="row align-items-end">
                    <div class="col-md-3">
                    <label for="filter_date">Select Specific Date:</label>
                    <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                    </div>

                    <div class="col-md-2">
                    <label>Start Year:</label>
                    <select name="start_year" class="form-control">
                        <option value="">-- Year --</option>
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= (isset($_POST['start_year']) && $_POST['start_year'] == $y ? 'selected' : '') ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    </div>

                    <!-- Start Month-Year -->
                    <div class="col-md-2">
                    <label>Start Month:</label>
                    <select name="start_month" class="form-control">
                        <option value="">-- Month --</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= (isset($_POST['start_month']) && $_POST['start_month'] == $m ? 'selected' : '') ?>><?= date('F', mktime(0, 0, 0, $m)) ?></option>
                        <?php endfor; ?>
                    </select>
                    </div>

                    <div class="col-md-2">
                    <label>End Year:</label>
                    <select name="end_year" class="form-control">
                        <option value="">-- Year --</option>
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= (isset($_POST['end_year']) && $_POST['end_year'] == $y ? 'selected' : '') ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    </div>
                    

                    <!-- End Month-Year -->
                    <div class="col-md-2">
                    <label>End Month:</label>
                    <select name="end_month" class="form-control">
                        <option value="">-- Month --</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= (isset($_POST['end_month']) && $_POST['end_month'] == $m ? 'selected' : '') ?>><?= date('F', mktime(0, 0, 0, $m)) ?></option>
                        <?php endfor; ?>
                    </select>
                    </div>

                    <div class="col-md-12 mt-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <button type="button" class="btn btn-danger" onclick="exportTableToPDF()">Download PDF</button>
                    <button type="button" class="btn btn-success" onclick="exportTableToExcel()">Download Excel</button>
                    <button class="btn btn-secondary" onclick="printTable()">🖨️ Print </button>
                    </div>
                </div>
                </form>

              <!-- Custom Search Bar -->
              <div class="mb-3">
                <input type="text" id="tableSearch" class="form-control" placeholder="Search in table...">
              </div>

              <!-- Table with return data -->
              <table class="table datatable table-bordered">
                <thead class="align-middle text-center">
                    <tr>
                      <th class="text-center">#</th>
                      <th class="text-center">Antibiotic Name</th>
                      <th class="text-center">Dosage</th>
                      <th class="text-center">Item Count</th>
                      <th class="text-center">Ward Name</th>
                      <th class="text-center">Stock Type</th>
                      <!--th class="text-center">Route Type</th-->
                      <th class="text-center">Book Number</th>
                      <th class="text-center">Page Number</th>
                      <th class="text-center">Return Time</th>
                      <th class="text-center">User</th>
                      <th class="text-center">Action</th> <!-- 👈 Add this -->
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['antibiotic_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['dosage']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['item_count']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['ward_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['type']) . "</td>";
                            //echo "<td>" . htmlspecialchars($row['ant_type']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['book_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['page_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['return_time']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['system_name']) . "</td>";
                            echo "<td>";
                            echo '<form method="POST" action="delete-return.php" onsubmit="return confirm(\'Are you sure you want to delete this return record?\');">';
                            echo '<input type="hidden" name="id" value="' . $row['id'] . '">';
                            echo '<button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>';
                            echo '</form>';
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='12' class='text-center'>No antibiotic returns found for this filter.</td></tr>";
                    }
                    ?>
                  </tbody>

              </table>
              <!-- End Table -->

            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include_once("../includes/footer.php"); ?>
  <?php include_once("../includes/js-links-inc.php"); ?>

  <!-- Back to top button -->
  <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
  </a>

  <!-- Custom search filter script -->
  <script>
    document.getElementById('tableSearch').addEventListener('keyup', function() {
      const filter = this.value.toLowerCase();
      const rows = document.querySelectorAll('table.datatable tbody tr');

      rows.forEach(row => {
        const match = Array.from(row.cells).some(cell =>
          cell.textContent.toLowerCase().includes(filter)
        );
        row.style.display = match ? '' : 'none';
      });
    });

    // Export table to Excel (CSV approach)
    function exportTableToExcel() {
      let table = document.querySelector('table.datatable');
      let rows = table.querySelectorAll('tr');
      let csv = [];

      rows.forEach(row => {
        let cols = row.querySelectorAll('th, td');
        let rowData = [];
        cols.forEach(col => {
          let data = col.innerText.replace(/"/g, '""'); // escape quotes
          rowData.push('"' + data + '"');
        });
        csv.push(rowData.join(','));
      });

      let csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
      let downloadLink = document.createElement('a');
      downloadLink.download = 'antibiotic_return_details.csv';
      downloadLink.href = window.URL.createObjectURL(csvFile);
      downloadLink.style.display = 'none';
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    }

    // Export table to PDF using jsPDF autotable
    function exportTableToPDF() {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();

      doc.text("Antibiotic Return Details", 14, 15);

      doc.autoTable({
        html: 'table.datatable',
        startY: 20,
        styles: { fontSize: 8 },
        headStyles: { fillColor: [41, 128, 185] },
        margin: { left: 14, right: 14 }
      });

      doc.save('antibiotic_return_details.pdf');
    }

    // Print the table only
    function printTable() {
      let printContents = document.querySelector('table.datatable').outerHTML;
      let originalContents = document.body.innerHTML;

      document.body.innerHTML = "<html><head><title>Print</title></head><body>" + printContents + "</body>";

      window.print();

      document.body.innerHTML = originalContents;
      location.reload();
    }
  </script>
</body>

</html>
