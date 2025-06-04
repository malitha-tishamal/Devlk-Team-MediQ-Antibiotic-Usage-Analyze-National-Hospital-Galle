<?php
session_start();
require_once 'includes/db-conn.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get filters from POST or set defaults
$filter_date = isset($_POST['filter_date']) ? $_POST['filter_date'] : '';
$filter_year = isset($_POST['filter_year']) ? $_POST['filter_year'] : '';
$filter_month = isset($_POST['filter_month']) ? $_POST['filter_month'] : '';

// Build dynamic SQL with filters
$sql = "SELECT * FROM returns WHERE 1=1 ";
$params = [];
$types = "";

if (!empty($filter_date)) {
    $sql .= " AND DATE(return_time) = ? ";
    $params[] = $filter_date;
    $types .= "s";
} else {
    if (!empty($filter_year)) {
        $sql .= " AND YEAR(return_time) = ? ";
        $params[] = $filter_year;
        $types .= "i";
    }
    if (!empty($filter_month)) {
        $sql .= " AND MONTH(return_time) = ? ";
        $params[] = $filter_month;
        $types .= "i";
    }
}

$sql .= " ORDER BY return_time DESC ";

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

  <?php include_once("includes/css-links-inc.php"); ?>

  <!-- jsPDF and autotable for PDF export -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

</head>

<body>
  <?php include_once("includes/header.php"); ?>
  <?php include_once("includes/user-sidebar.php"); ?>

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

              <!-- Filters Form -->
              <form method="POST" class="mb-3 row align-items-end">
                <!-- Year filter -->
                <div class="col-auto">
                  <label for="filter_year" class="form-label">Year:</label>
                  <select name="filter_year" id="filter_year" class="form-select">
                    <option value="">-- All Years --</option>
                    <?php
                    $currentYear = date('Y');
                    for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                        $selected = ($filter_year == $y) ? "selected" : "";
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                  </select>
                </div>

                <!-- Month filter -->
                <div class="col-auto">
                  <label for="filter_month" class="form-label">Month:</label>
                  <select name="filter_month" id="filter_month" class="form-select">
                    <option value="">-- All Months --</option>
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $monthName = date('F', mktime(0, 0, 0, $m, 10));
                        $selected = ($filter_month == $m) ? "selected" : "";
                        echo "<option value='$m' $selected>$monthName</option>";
                    }
                    ?>
                  </select>
                </div>

                <!-- Date filter -->
                <div class="col-auto">
                  <label for="filter_date" class="form-label">Exact Date:</label>
                  <input type="date" name="filter_date" id="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" class="form-control">
                </div>

                <!-- Buttons -->
                <div class="col-md-5 d-flex flex-wrap gap-2">
                  <button type="submit" class="btn btn-primary mt-2">Filter</button>
                  <button type="button" class="btn btn-danger mt-2" onclick="exportTableToPDF()">Download PDF</button>
                  <button type="button" class="btn btn-success mt-2" onclick="exportTableToExcel()">Download Excel</button>
                  <button type="button" class="btn btn-secondary mt-2" onclick="printTable()">🖨️ Print</button>
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
                    <th class="text-center">Route Type</th>
                    <th class="text-center">Book Number</th>
                    <th class="text-center">Page Number</th>
                    <th class="text-center">Return Time</th>
                    <th class="text-center">User</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  if ($result->num_rows > 0) {
                      while ($row = $result->fetch_assoc()) {
                          echo "<tr>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['id']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['antibiotic_name']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['dosage']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['item_count']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['ward_name']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['type']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['ant_type']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['book_number']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['page_number']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['return_time']) . "</td>";
                          echo "<td class='text-center'>" . htmlspecialchars($row['system_name']) . "</td>";
                          echo "</tr>";
                      }
                  } else {
                      echo "<tr><td colspan='11' class='text-center'>No antibiotic returns found for this filter.</td></tr>";
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

  <?php include_once("includes/footer.php"); ?>
  <?php include_once("includes/js-links-inc.php"); ?>

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
