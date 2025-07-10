<?php
session_start();
require_once '../includes/db-conn.php';

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

// Fetch all antibiotic-dosage combinations with LEFT JOIN to stock
$sql = "SELECT 
            d.stv_number,
            a.id AS antibiotic_id,
            a.name AS antibiotic_name,
            d.id AS dosage_id,
            d.dosage,
            s.quantity,
            s.last_updated
        FROM dosages d
        JOIN antibiotics a ON d.antibiotic_id = a.id
        LEFT JOIN return_stock s ON s.stv_number = d.stv_number
        ORDER BY a.name ASC, d.dosage ASC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Return Antibiotic Stock - Mediq</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Return Antibiotic Stock</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                <li class="breadcrumb-item">Pages</li>
                <li class="breadcrumb-item active">Return Stock</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body pt-4">

                <div class="d-flex mb-3 no-print">
                    <button onclick="printTable()" class="btn btn-outline-secondary">🖨️ Print Report</button>&nbsp;
                    <button onclick="downloadPDF()" class="btn btn-outline-danger">📄 Download PDF</button>&nbsp;
                    <button onclick="downloadExcel()" class="btn btn-outline-success">📥 Download Excel</button>
                </div>

                <div class="mb-3 no-print">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search drugs, dosage, or quantity...">
                </div>

                <script>
                    document.getElementById('searchInput').addEventListener('keyup', function () {
                        const filter = this.value.toLowerCase();
                        const rows = document.querySelectorAll("#print-section table tbody tr");

                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            row.style.display = text.includes(filter) ? "" : "none";
                        });
                    });
                </script>

                <div id="print-section">
                    <table class="table table-bordered">
                        <thead class="text-center">
                            <tr>
                                <th>SR Number</th>
                                <th>Drug Name</th>
                                <th>Dosage</th>
                                <th>Quantity</th>
                                <th>Last Updated</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="text-center">
                                    <td><?= htmlspecialchars($row['stv_number']) ?></td>
                                    <td><?= htmlspecialchars($row['antibiotic_name']) ?></td>
                                    <td><?= htmlspecialchars($row['dosage']) ?></td>
                                    <td><?= is_null($row['quantity']) ? '<span class="text-danger">Not Added</span>' : $row['quantity'] ?></td>
                                    <td><?= is_null($row['last_updated']) ? '-' : $row['last_updated'] ?></td>
                                    <td class="no-print">
                                        <form action="update-return-stock.php" method="POST" class="d-flex justify-content-center gap-1">
                                            <input type="hidden" name="stv_number" value="<?= $row['stv_number'] ?>">
                                            <input type="hidden" name="antibiotic_id" value="<?= $row['antibiotic_id'] ?>">
                                            <input type="hidden" name="dosage_id" value="<?= $row['dosage_id'] ?>">
                                            <input type="number" name="quantity" value="0" class="form-control form-control-sm me-2" min="0" required>
                                            <button type="submit" name="action" value="add" class="btn btn-sm btn-success">Add</button>
                                            <button type="submit" name="action" value="update" class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <script>
                    function printTable() {
                        const printContents = document.getElementById("print-section").outerHTML;
                        const printWindow = window.open('', '', 'height=600,width=1000');

                        printWindow.document.write(`
                            <html>
                            <head>
                                <title>Antibiotic Return Stock Report</title>
                                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                                <style>
                                    body { font-family: 'Segoe UI', sans-serif; padding: 20px; }
                                    table { border-collapse: collapse !important; width: 100%; }
                                    th, td { border: 1px solid #dee2e6 !important; padding: 8px; text-align: center; }
                                    th { background-color: #e9ecef !important; }
                                    h2 { text-align: center; margin-bottom: 10px; }
                                    .text-danger { color: red !important; }
                                </style>
                            </head>
                            <body>
                                <h2>Antibiotic Return Stock Report</h2>
                                <p style="text-align: right; font-style: italic; margin-top: -10px; margin-bottom: 20px;">
                                    Printed on: ${new Date().toLocaleString()}
                                </p>
                                ${printContents}
                            </body>
                            </html>
                        `);

                        printWindow.document.close();
                        printWindow.focus();
                        printWindow.print();
                        printWindow.close();
                    }

                    function downloadPDF() {
                        const element = document.getElementById("print-section");
                        const opt = {
                            margin: 0.5,
                            filename: 'Antibiotic_Return_Stock_Report.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2 },
                            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
                        };
                        html2pdf().from(element).set(opt).save();
                    }

                    function downloadExcel() {
                        const table = document.querySelector("#print-section table");
                        const wb = XLSX.utils.book_new();
                        const ws = XLSX.utils.table_to_sheet(table);
                        XLSX.utils.book_append_sheet(wb, ws, "Antibiotic Return Stock");
                        XLSX.writeFile(wb, "Antibiotic_Return_Stock_Report.xlsx");
                    }
                </script>

            </div>
        </div>
    </section>
</main>

<?php include_once("../includes/footer.php"); ?>
<?php include_once("../includes/js-links-inc.php"); ?>
</body>
</html>
