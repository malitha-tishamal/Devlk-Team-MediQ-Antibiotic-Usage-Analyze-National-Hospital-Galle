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

// Fetch all antibiotic-dosage combinations with LEFT JOIN to return_stock
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

// Calculate summary statistics
$totalItems = 0;
$noReturns = 0;
$hasReturns = 0;
$totalReturnQuantity = 0;

// Store data for later use
$returnStockData = [];
while ($row = $result->fetch_assoc()) {
    $returnStockData[] = $row;
    $totalItems++;
    
    if (is_null($row['quantity']) || $row['quantity'] == 0) {
        $noReturns++;
    } else {
        $hasReturns++;
        $totalReturnQuantity += $row['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Return Antibiotic Stock - Mediq</title>
    <?php include_once("../includes/css-links-inc.php"); ?>
    
    <!-- Include libraries for export functionality -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <style>
        @media print {
            .no-print, .no-print * {
                display: none !important;
            }
            .print-section {
                display: block !important;
            }
        }
        
        .dashboard-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .summary-card:hover {
            transform: scale(1.02);
        }
        .summary-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .summary-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .summary-card.info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .stat-label {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 500;
        }
        .stat-subtext {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 0.5rem;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            color: white;
        }
        .btn-print {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            color: white;
        }
        .btn-pdf {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            color: white;
        }
        
        .table th {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px;
        }
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        .table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .table tr:hover {
            background-color: #e3f2fd;
        }
        
        .return-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .return-none {
            background-color: #ffe6e6;
            color: #d63031;
        }
        .return-available {
            background-color: #e6f7ee;
            color: #00b894;
        }
        
        .action-form {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .quantity-input {
            width: 100px;
            text-align: center;
        }
        
        .filter-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 1px solid #e3e6f0;
        }
        
        .page-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.5rem;
            border-left: 5px solid #3498db;
            padding-left: 1rem;
            font-size: 1.8rem;
        }
        
        .return-highlight {
            background-color: #fff9e6 !important;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
<?php include_once("../includes/header.php"); ?>
<?php include_once("../includes/sadmin-sidebar.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1 class="page-title">🔄 Return Antibiotic Stock Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                <li class="breadcrumb-item active">Return Stock Management</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="summary-card">
                    <div class="stat-number"><?= $totalItems ?></div>
                    <div class="stat-label">Total Items</div>
                    <div class="stat-subtext">All antibiotic types</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="summary-card success">
                    <div class="stat-number"><?= $hasReturns ?></div>
                    <div class="stat-label">With Returns</div>
                    <div class="stat-subtext">Items with returned stock</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="summary-card warning">
                    <div class="stat-number"><?= $noReturns ?></div>
                    <div class="stat-label">No Returns</div>
                    <div class="stat-subtext">Items without returns</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="summary-card info">
                    <div class="stat-number"><?= $totalReturnQuantity ?></div>
                    <div class="stat-label">Total Return Qty</div>
                    <div class="stat-subtext">All returned items</div>
                </div>
            </div>
        </div>

        <!-- Filter and Export Section -->
        <div class="filter-section">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="searchInput" class="form-label fw-bold">🔍 Search Return Stock</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by drug name, dosage, SR number...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                        <button onclick="printTable()" class="btn-print btn">🖨️ Print Report</button>
                        <button onclick="downloadPDF()" class="btn-pdf btn">📄 Download PDF</button>
                        <button onclick="downloadExcel()" class="btn-export btn">📊 Download Excel</button>
                        <button onclick="downloadCSV()" class="btn btn-info text-white">📥 Download CSV</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Return Stock Table -->
        <div class="dashboard-card">
            <div class="card-body pt-4">
                <div id="print-section">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="text-center">
                                <tr>
                                    <th>SR Number</th>
                                    <th>Drug Name</th>
                                    <th>Dosage</th>
                                    <th>Return Quantity</th>
                                    <th>Return Status</th>
                                    <th>Last Updated</th>
                                    <th class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($returnStockData as $row): 
                                    $quantity = $row['quantity'];
                                    $statusClass = '';
                                    $statusText = '';
                                    
                                    if (is_null($quantity) || $quantity == 0) {
                                        $statusClass = 'return-none';
                                        $statusText = 'No Returns';
                                    } else {
                                        $statusClass = 'return-available';
                                        $statusText = 'Returns Available';
                                    }
                                    
                                    $rowClass = (!is_null($quantity) && $quantity > 0) ? 'return-highlight' : '';
                                ?>
                                    <tr class="text-center <?= $rowClass ?>">
                                        <td><strong><?= htmlspecialchars($row['stv_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['antibiotic_name']) ?></td>
                                        <td><?= htmlspecialchars($row['dosage']) ?></td>
                                        <td>
                                            <?php if (is_null($quantity)): ?>
                                                <span class="text-danger fw-bold">0</span>
                                            <?php else: ?>
                                                <span class="fw-bold"><?= $quantity ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="return-status <?= $statusClass ?>"><?= $statusText ?></span>
                                        </td>
                                        <td>
                                            <?php if (is_null($row['last_updated'])): ?>
                                                <span class="text-muted">Never</span>
                                            <?php else: ?>
                                                <?= date('M j, Y g:i A', strtotime($row['last_updated'])) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="no-print">
                                            <form action="update-return-stock.php" method="POST" class="action-form">
                                                <input type="hidden" name="stv_number" value="<?= $row['stv_number'] ?>">
                                                <input type="hidden" name="antibiotic_id" value="<?= $row['antibiotic_id'] ?>">
                                                <input type="hidden" name="dosage_id" value="<?= $row['dosage_id'] ?>">
                                                <input type="number" name="quantity" value="<?= is_null($quantity) ? 0 : $quantity ?>" 
                                                       class="form-control quantity-input" min="0" required>
                                                <button type="submit" name="action" value="add" class="btn btn-sm btn-success">
                                                    ➕ Add
                                                </button>
                                                <button type="submit" name="action" value="update" class="btn btn-sm btn-primary">
                                                    ✏️ Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Return Stock Summary for Print/Export -->
                <div id="summary-section" class="mt-4 p-3 bg-light rounded d-none">
                    <h5>Return Stock Summary</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Total Items:</strong> <?= $totalItems ?>
                        </div>
                        <div class="col-md-3">
                            <strong>With Returns:</strong> <?= $hasReturns ?>
                        </div>
                        <div class="col-md-3">
                            <strong>No Returns:</strong> <?= $noReturns ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Total Return Qty:</strong> <?= $totalReturnQuantity ?>
                        </div>
                    </div>
                    <div class="mt-2">
                        <strong>Report Generated:</strong> <?= date('F j, Y \a\t g:i A') ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll("#print-section table tbody tr");
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        // Update search result count
        const searchInfo = document.getElementById('search-info') || (function() {
            const info = document.createElement('div');
            info.id = 'search-info';
            info.className = 'mt-2 text-muted';
            document.querySelector('.filter-section').appendChild(info);
            return info;
        })();
        
        searchInfo.textContent = visibleCount + ' items match your search';
    });

    // Print function
    function printTable() {
        const printContents = document.getElementById("print-section").innerHTML;
        const summarySection = document.getElementById("summary-section");
        summarySection.classList.remove('d-none');
        const summaryHTML = summarySection.outerHTML;
        summarySection.classList.add('d-none');

        const printWindow = window.open('', '', 'height=800,width=1200');
        const currentDate = new Date().toLocaleString();

        printWindow.document.write(`
            <html>
            <head>
                <title>Antibiotic Return Stock Report - Mediq</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; color: #333; }
                    .table { border-collapse: collapse; width: 100%; margin-bottom: 1rem; font-size: 0.9rem; }
                    .table th, .table td { border: 1px solid #dee2e6; padding: 8px; text-align: center; }
                    .table th { background-color: #3498db !important; color: white; font-weight: 600; }
                    .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(0,0,0,.05); }
                    h2 { color: #2c3e50; margin-bottom: 15px; text-align: center; }
                    .return-status { padding: 3px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
                    .return-none { background-color: #ffe6e6; color: #d63031; }
                    .return-available { background-color: #e6f7ee; color: #00b894; }
                    .return-highlight { background-color: #fff9e6 !important; border-left: 4px solid #ffc107; }
                    .summary-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3498db; }
                </style>
            </head>
            <body>
                <div class="container-fluid">
                    <h2>Antibiotic Return Stock Report - Mediq</h2>
                    <div class="summary-box">
                        ${summaryHTML}
                    </div>
                    ${printContents}
                    <div class="mt-4 text-end">
                        <small>Printed on: ${currentDate}</small>
                    </div>
                </div>
            </body>
            </html>
        `);

        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    }

    // PDF Download function
    function downloadPDF() {
        const element = document.getElementById("print-section");
        const summarySection = document.getElementById("summary-section");
        summarySection.classList.remove('d-none');
        
        // Create a temporary container with both table and summary
        const tempContainer = document.createElement('div');
        tempContainer.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <h2 style="color: #2c3e50;">Antibiotic Return Stock Report - Mediq</h2>
            </div>
            ${summarySection.outerHTML}
            ${element.innerHTML}
            <div style="text-align: right; margin-top: 20px; font-size: 0.8rem;">
                Generated on: ${new Date().toLocaleString()}
            </div>
        `;
        
        const opt = {
            margin: 0.5,
            filename: `Antibiotic_Return_Stock_Report_${new Date().toISOString().split('T')[0]}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2,
                useCORS: true,
                logging: false
            },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
        };

        html2pdf().set(opt).from(tempContainer).save().then(() => {
            summarySection.classList.add('d-none');
        });
    }

    // Excel Download function
    function downloadExcel() {
        const table = document.querySelector("#print-section table");
        const summarySection = document.getElementById("summary-section");
        summarySection.classList.remove('d-none');
        
        // Create a new workbook
        const wb = XLSX.utils.book_new();
        
        // Convert table to worksheet
        const ws = XLSX.utils.table_to_sheet(table);
        
        // Add summary data
        const summaryData = [
            ["Antibiotic Return Stock Report - Mediq"],
            ["Generated on: " + new Date().toLocaleString()],
            [""],
            ["Total Items", "With Returns", "No Returns", "Total Return Quantity"],
            [<?= $totalItems ?>, <?= $hasReturns ?>, <?= $noReturns ?>, <?= $totalReturnQuantity ?>],
            [""],
            [""]
        ];
        
        const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
        
        // Add worksheets to workbook
        XLSX.utils.book_append_sheet(wb, summaryWs, "Summary");
        XLSX.utils.book_append_sheet(wb, ws, "Return Stock Details");
        
        // Save the file
        XLSX.writeFile(wb, `Antibiotic_Return_Stock_Report_${new Date().toISOString().split('T')[0]}.xlsx`);
        
        summarySection.classList.add('d-none');
    }

    // CSV Download function
    function downloadCSV() {
        const rows = document.querySelectorAll("#print-section table tr");
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Add header
        csvContent += "Antibiotic Return Stock Report - Mediq\n";
        csvContent += "Generated on: " + new Date().toLocaleString() + "\n\n";
        
        // Add summary
        csvContent += "Summary\n";
        csvContent += "Total Items,With Returns,No Returns,Total Return Quantity\n";
        csvContent += `${<?= $totalItems ?>},${<?= $hasReturns ?>},${<?= $noReturns ?>},${<?= $totalReturnQuantity ?>}\n\n`;
        
        // Add table headers
        const headers = [];
        document.querySelectorAll("#print-section table thead th").forEach(header => {
            if (!header.classList.contains('no-print')) {
                headers.push(header.innerText);
            }
        });
        csvContent += headers.join(",") + "\n";
        
        // Add table data
        rows.forEach((row, index) => {
            if (index > 0) { // Skip header row
                const rowData = [];
                row.querySelectorAll("td").forEach((cell, cellIndex) => {
                    if (cellIndex < 6) { // Only include first 6 columns (exclude actions)
                        rowData.push(cell.innerText.replace(/,/g, ';'));
                    }
                });
                csvContent += rowData.join(",") + "\n";
            }
        });
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `Antibiotic_Return_Stock_${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php include_once("../includes/footer.php"); ?>
<?php include_once("../includes/js-links-inc.php"); ?>
</body>
</html>