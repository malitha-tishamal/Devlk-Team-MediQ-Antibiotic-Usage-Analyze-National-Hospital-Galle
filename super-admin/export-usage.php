<?php
session_start();
require_once '../includes/db-conn.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=antibiotic_usage.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, ['Ward Name', 'Antibiotic Name', 'Dosage', 'Usage Count', 'Percentage (%)']);

// Get selected filters from POST request
$selectedMonth = $_POST['month_select'] ?? date('m');
$selectedYear = $_POST['year_select'] ?? date('Y');
$selectedWard = $_POST['ward_select'] ?? '';
$selectedType = $_POST['type_select'] ?? '';

// Query for antibiotic usage filtered by month, year, ward, and type
$query = "
    SELECT ward_name, antibiotic_name, dosage, type, SUM(item_count) AS usage_count
    FROM releases
    WHERE MONTH(release_time) = ? AND YEAR(release_time) = ?
    AND (ward_name = ? OR ? = '')
    AND (type = ? OR ? = '')
    GROUP BY ward_name, antibiotic_name, dosage
    ORDER BY ward_name, usage_count DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iissss", $selectedMonth, $selectedYear, $selectedWard, $selectedWard, $selectedType, $selectedType);
$stmt->execute();
$result = $stmt->get_result();

// Get total usage per ward for percentage calculation
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

// Fetch and write data to CSV
while ($row = $result->fetch_assoc()) {
    $wardName = $row['ward_name'];
    $totalUsageInWard = $wardUsage[$wardName] ?? 1; // Avoid division by zero
    $percentage = round(($row['usage_count'] / $totalUsageInWard) * 100, 2);
    
    fputcsv($output, [$row['ward_name'], $row['antibiotic_name'], $row['dosage'], $row['usage_count'], $percentage . "%"]);
}

// Close database connections
$stmt->close();
$wardUsageStmt->close();
$conn->close();

// Close output stream
fclose($output);
exit();
?>
