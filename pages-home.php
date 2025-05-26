<?php
session_start();
require_once 'includes/db-conn.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user info
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get current year and month for default filter
$current_year = date('Y');
$current_month = date('m');

// Get filter parameters from request
$selected_year = isset($_GET['year']) ? $_GET['year'] : $current_year;
$selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;

// Prepare WHERE clauses for filtering (using correct datetime columns)
$release_where = "WHERE YEAR(release_time) = ? AND MONTH(release_time) = ?";
$return_where = "WHERE YEAR(return_time) = ? AND MONTH(return_time) = ?";

// Fetch release counts grouped by antibiotic_name and dosage with filtering
$release_sql = "SELECT antibiotic_name, dosage, SUM(item_count) as total FROM releases $release_where GROUP BY antibiotic_name, dosage ORDER BY total DESC";
$release_stmt = $conn->prepare($release_sql);
$release_stmt->bind_param("ii", $selected_year, $selected_month);
$release_stmt->execute();
$release_result = $release_stmt->get_result();

// Fetch return counts grouped by antibiotic_name and dosage with filtering
$return_sql = "SELECT antibiotic_name, dosage, SUM(item_count) as total FROM returns $return_where GROUP BY antibiotic_name, dosage ORDER BY total DESC";
$return_stmt = $conn->prepare($return_sql);
$return_stmt->bind_param("ii", $selected_year, $selected_month);
$return_stmt->execute();
$return_result = $return_stmt->get_result();

// Get distinct years for dropdown (from both tables)
$years_sql = "(SELECT DISTINCT YEAR(release_time) as year FROM releases) 
              UNION 
              (SELECT DISTINCT YEAR(return_time) as year FROM returns) 
              ORDER BY year DESC";
$years_result = $conn->query($years_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Dashboard - Antibiotic Charts</title>
    <?php include_once("includes/css-links-inc.php"); ?>

    <!-- Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', { packages: ['corechart'] });
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            // Release Chart
            var releaseData = new google.visualization.DataTable();
            releaseData.addColumn('string', 'Antibiotic (Dosage)');
            releaseData.addColumn('number', 'Releases');
            releaseData.addRows([
                <?php
                $release_result->data_seek(0);
                while ($row = $release_result->fetch_assoc()) {
                    $label = htmlspecialchars($row['antibiotic_name']) . " (" . htmlspecialchars($row['dosage']) . ")";
                    echo "['" . addslashes($label) . "', " . $row['total'] . "],";
                }
                ?>
            ]);

            var releaseOptions = {
                title: 'Antibiotic Releases - <?php echo date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)); ?>',
                height: Math.max(600, releaseData.getNumberOfRows() * 40),
                width: '100%',
                chartArea: {width: '70%', height: '90%'},
                bar: {groupWidth: '90%'},
                legend: {position: 'none'},
                hAxis: {
                    title: 'Total Releases',
                    minValue: 0
                },
                colors: ['#4285F4'],
                animation: {
                    duration: 1000,
                    easing: 'out',
                    startup: true
                }
            };

            var releaseChart = new google.visualization.BarChart(document.getElementById('release_chart_div'));
            releaseChart.draw(releaseData, releaseOptions);

            // Return Chart
            var returnData = new google.visualization.DataTable();
            returnData.addColumn('string', 'Antibiotic (Dosage)');
            returnData.addColumn('number', 'Returns');
            returnData.addRows([
                <?php
                $return_result->data_seek(0);
                while ($row = $return_result->fetch_assoc()) {
                    $label = htmlspecialchars($row['antibiotic_name']) . " (" . htmlspecialchars($row['dosage']) . ")";
                    echo "['" . addslashes($label) . "', " . $row['total'] . "],";
                }
                ?>
            ]);

            var returnOptions = {
                title: 'Antibiotic Returns - <?php echo date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)); ?>',
                height: Math.max(600, returnData.getNumberOfRows() * 40),
                width: '100%',
                chartArea: {width: '70%', height: '90%'},
                bar: {groupWidth: '90%'},
                legend: {position: 'none'},
                hAxis: {
                    title: 'Total Returns',
                    minValue: 0
                },
                colors: ['#DB4437'],
                animation: {
                    duration: 1000,
                    easing: 'out',
                    startup: true
                }
            };

            var returnChart = new google.visualization.BarChart(document.getElementById('return_chart_div'));
            returnChart.draw(returnData, returnOptions);
            
            // Add window resize event listener
            window.addEventListener('resize', function() {
                releaseChart.draw(releaseData, releaseOptions);
                returnChart.draw(returnData, returnOptions);
            });
        }
    </script>

    <style>
        .chart-container {
            width: 100%;
            margin: 30px auto;
            overflow-x: auto;
            max-height: 1000px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 20px;
            background: white;
        }
        
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        select, button {
            padding: 8px 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background: white;
        }
        
        button {
            background: #4285F4;
            color: white;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #3367D6;
        }
        
        label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include_once("includes/header.php"); ?>
    <?php include_once("includes/user-sidebar.php"); ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Antibiotic Usage Analytics</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Analytics</li>
                </ol>
            </nav>
        </div>

        <section class="section dashboard">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card p-4">
                        <h5 class="text-center">Welcome, <?php echo htmlspecialchars($user['name']); ?>. Analyze antibiotic usage patterns.</h5>
                        
                        <!-- Filter Section -->
                        <div class="filter-container">
                            <form method="get" action="">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label for="year">Year:</label>
                                        <select name="year" id="year">
                                            <?php while ($year_row = $years_result->fetch_assoc()): ?>
                                                <option value="<?php echo $year_row['year']; ?>" <?php echo $year_row['year'] == $selected_year ? 'selected' : ''; ?>>
                                                    <?php echo $year_row['year']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="filter-group">
                                        <label for="month">Month:</label>
                                        <select name="month" id="month">
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <button type="submit">Apply Filters</button>
                                    <button type="button" onclick="window.location.href='antibiotic-charts.php'">Reset</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Charts -->
                        <div class="chart-container">
                            <div id="release_chart_div"></div>
                        </div>
                        
                        <div class="chart-container">
                            <div id="return_chart_div"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once("includes/footer.php"); ?>
    <?php include_once("includes/js-links-inc.php"); ?>
</body>
</html>

<?php 
$release_stmt->close();
$return_stmt->close();
$conn->close(); 
?>