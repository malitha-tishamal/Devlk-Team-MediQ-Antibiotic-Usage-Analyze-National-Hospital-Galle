<?php
session_start();

// Set PHP timezone to Sri Lanka
date_default_timezone_set('Asia/Colombo');

require_once '../includes/db-conn.php';

// Set MySQL connection timezone to Sri Lanka time zone (+05:30)
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
$result_user = $stmt->get_result();
$user = $result_user->fetch_assoc();
$stmt->close();

// Handle filters
$filter_date = $_POST['filter_date'] ?? '';
$start_month = $_POST['start_month'] ?? '';
$start_year = $_POST['start_year'] ?? '';
$end_month = $_POST['end_month'] ?? '';
$end_year = $_POST['end_year'] ?? '';


if (!empty($filter_date)) {
    $sql = "SELECT * FROM releases WHERE DATE(release_time) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filter_date);
} elseif (!empty($start_month) && !empty($start_year) && !empty($end_month) && !empty($end_year)) {
    // Format start and end dates
    $start_date = sprintf('%04d-%02d-01', $start_year, $start_month);
    $end_date = date("Y-m-t", strtotime(sprintf('%04d-%02d-01', $end_year, $end_month))); // last day of end month

    $sql = "SELECT * FROM releases WHERE release_time BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
} else {
    $sql = "SELECT * FROM releases WHERE DATE(release_time) = CURDATE()";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Antibiotic Release Details - Mediq</title>

    <?php include_once("../includes/css-links-inc.php"); ?>

    <!-- jsPDF & SheetJS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Release Details</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Pages</li>
                <li class="breadcrumb-item active">Antibiotic Release Details</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Antibiotic Release Details</h5>

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


                        <!-- Custom Search -->
                        <div class="mb-3">
                          <input type="text" id="customSearch" class="form-control" placeholder="Search in table...">
                        </div>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Antibiotic Name</th>
                                        <th>Dosage</th>
                                        <th>Item Count</th>
                                        <th>Ward Name</th>
                                        <th>Stock Type</th>
                                        <!--th>Route Type</th-->
                                        <th>Book Number</th>
                                        <th>Page Number</th>
                                        <th>Release Time</th>
                                        <th>User</th>
                                        <th>Action</th> <!-- NEW -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['id']) ?></td>
                                                <td><?= htmlspecialchars($row['antibiotic_name']) ?></td>
                                                <td><?= htmlspecialchars($row['dosage']) ?></td>
                                                <td><?= htmlspecialchars($row['item_count']) ?></td>
                                                <td><?= htmlspecialchars($row['ward_name']) ?></td>
                                                <td><?= htmlspecialchars($row['type']) ?></td>
                                                <!--td><?= htmlspecialchars($row['ant_type']) ?></td-->
                                                <td><?= htmlspecialchars($row['book_number']) ?></td>
                                                <td><?= htmlspecialchars($row['page_number']) ?></td>
                                                <td><?= htmlspecialchars($row['release_time']) ?></td>
                                                <td><?= htmlspecialchars($row['system_name']) ?></td>
                                                <td>
                                                    <form method="POST" action="delete-release.php" onsubmit="return confirm('Are you sure you want to delete this release?');">
                                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="12">No antibiotic releases found for this filter.</td></tr>
                                    <?php endif; ?>
                                </tbody>

                            </table>
                        </div>
                        <!-- End Table -->
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include_once("../includes/footer.php"); ?>
<?php include_once("../includes/js-links-inc.php"); ?>

<script>
    function printTable() {
        const printContents = document.querySelector('.table-responsive').innerHTML;
        const printWindow = window.open('', '', 'height=600,width=1000');
        printWindow.document.write(`
          <html>
            <head>
              <title>Print - Antibiotic Release Details</title>
              <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
              <style>
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
                th { background-color: #f5f5f5; }
              </style>
            </head>
            <body>
              <h3 class="text-center">Antibiotic Release Details</h3>
              ${printContents}
            </body>
          </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }

    async function exportTableToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.text("Antibiotic Release Details", 14, 15);
        doc.autoTable({ html: '.table', startY: 20, theme: 'grid' });
        doc.save("release-details.pdf");
    }

    function exportTableToExcel() {
        const table = document.querySelector('.table');
        const wb = XLSX.utils.table_to_book(table, {sheet: "ReleaseDetails"});
        XLSX.writeFile(wb, "release-details.xlsx");
    }

    // Custom search filter
    document.getElementById('customSearch').addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const table = document.querySelector('.table tbody');
        const rows = table.getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
            const rowText = rows[i].textContent.toLowerCase();
            if (rowText.indexOf(filter) > -1) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    });
</script>

<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
</body>
</html>

<?php $conn->close(); ?>
