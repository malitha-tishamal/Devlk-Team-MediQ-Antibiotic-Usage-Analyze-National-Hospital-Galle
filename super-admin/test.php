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
$sql2 = "SELECT name, email, nic, mobile, profile_picture FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql2);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch antibiotic usage by ward using your provided query
$sql = "
    SELECT '1 (Pediatrics)' AS ward_group, antibiotic_name, dosage, SUM(item_count) AS total_items, type 
    FROM releases 
    WHERE ward_name = '1 & 2 - Pediatrics - Combined'
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '3+5 (Surgical Prof)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('3 - Surgical Prof - Female', '5 - Surgical Prof - Male')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '4+7 (Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('4 - Surgery - Male', '7 - Surgical Prof - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '6 (Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('6 - Surgery - Combined')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '8+37 (Neuro-Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('8 - Neuro-Surgery - Female', '37 - Neuro-Surgery - Male')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '9 (Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('9 - Surgery - Combined')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '10 (Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('10 - Surgery')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '38 (Neuro-Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name = '38 (Neuro-Surgery)'
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '11+12 (Medicine Prof)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('11 - Medicine Prof - Female', '12 - Medicine Prof - Male')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '14+15 (Medicine)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('14 - Medicine - Male', '15 - Medicine - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '16+17 (Medicine)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('16 - Medicine - Male', '17 - Medicine - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '18+23 (Psychiatry)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('18 - Psychiatry - Male', '23 - Psychiatry - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '19+21 (Medicine)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('19 - Medicine - Male', '21 - Medicine - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '20+22 (Orthopedic)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('20 - Orthopedic - Female', '22 - Orthopedic - Male')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '30+31 (ENT)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('30 - ENT - Male', '31 - ENT - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '24 (Neurology)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('24 - Neurology - Combined')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '26 (Oro-Maxillary Facial)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('26 - Oro-Maxillary Facial - Combined')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '36 (Pediatrics)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name = '36 (Pediatrics) - Combined'
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '25+27 (Dermatology)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('25 - Dermatology - Female', '27 - Dermatology - Male')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '28+29 (Oncology)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('28 - Oncology - Male', '29 - Oncology - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '30+31 (ENT)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('30 - ENT - Male', '31 - ENT - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '32+33 (Opthalmology)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('32 - Ophthalmology - Female', '33 - Ophthalmology - Male')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '34+35 (Medicine)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('34 - Medicine - Male', '35 - Medicine - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '36 (Pediatrics)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('36 - Pediatrics - Combined')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '39+40 (Cardiology)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('39 & 40 - Cardiology')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '41+42+43 (Maliban Rehabilitation)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('41, 42 & 43 - Maliban Rehabilitation')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '44+45 (Cardio-Thoracic)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('44 - Cardio-Thoracic - Female', '45 - Cardio-Thoracic - Male')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '46+47 (GU Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('46 & 47 - GU Surgery - Male')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '48+49 (Onco- Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('48 - Onco-Surgery - Female', 'Ward 59', '49 - Onco-Surgery - Male')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '50 (Pediatric Oncology)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name = '50 - Pediatric Oncology - Combined'
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '51+52 (Pediatric Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('51 & 52 - Pediatric Surgery')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '53+54 (Opthalmology)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('53 - Ophthalmology - Male', '54 - Ophthalmology - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '55 (Rheumatology)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('55 - Rheumatology - Combined')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '58+59 (Emergency/ETC)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('58 - Emergency/ETC - Male', '59 - Emergency/ETC - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '60 (ETC Pead)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('60 - ETC Pead - Combined')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '61+62 (Bhikku)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('61 & 62 - Bhikku')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '65 (Palliative)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('65 - Palliative')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '67 (Stroke)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('67 - Stroke')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '68+69 (Respiratory)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('68 & 69 - Respiratory')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '70+71+73+74 (Nephrology)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name IN ('70 - Nephrology', '71 - Nephrology - Male', '73 - Nephrology - Female')
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT '72 (Vascular Surgery)', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name = '72 - Vascular Surgery - Combined'
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT 'All ICU', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name LIKE '%ICU%'
    GROUP BY antibiotic_name, dosage, type
    UNION ALL
    SELECT 'All Theater', antibiotic_name, dosage, SUM(item_count) AS total_items, type
    FROM releases 
    WHERE ward_name LIKE '%Theater%'
    GROUP BY antibiotic_name, dosage, type
    -- Add more wards as necessary
";
$result2 = $conn->query($sql);

// Start HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antibiotic Usage Monitor</title>
    <!-- DataTable CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.css">
    <?php include_once("../includes/css-links-inc.php"); ?>
    <!-- jQuery -->
    <script type="text/javascript" charset="utf-8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTable JS -->
    <script type="text/javascript" charset="utf-8" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
</head>
<body>

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
                            <h5 class="card-title">Antibiotic Usage Details</h5>
                            <?php include_once("../includes/header.php") ?>
                            <?php include_once("../includes/sadmin-sidebar.php") ?>

                            

                            <table id="antibioticUsageTable" class="display table">
                                <thead class="align-middle text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Antibiotic Name</th>
                                        <th>Dosage</th>
                                        <th>Count</th>
                                        <th>Units</th>
                                        <th>Percentage (%)</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                <?php 
                                if ($result2->num_rows > 0) {
                                    $rowNumber = 1;
                                    $previousWard = "";
                                    $totalUnits = 0;
                                    $wardUsage = []; // Store per-ward total units

                                    // First pass: Calculate total units
                                    while ($row = $result2->fetch_assoc()) {
                                        $wardName = $row['ward_group'];
                                        $dosage = strtolower($row['dosage']);
                                        $itemCount = $row['total_items'];
                                        $usageInGrams = 0;

                                        if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
                                            $mgValue = (int)$matches[1];
                                            $usageInGrams = ($mgValue / 1000) * $itemCount;
                                        } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
                                            $gValue = (float)$matches[1];
                                            $usageInGrams = $gValue * $itemCount;
                                        }

                                        $usageInUnits = $usageInGrams; // 1g = 1 unit
                                        $totalUnits += $usageInUnits;

                                        // Store per-ward usage
                                        if (!isset($wardUsage[$wardName])) {
                                            $wardUsage[$wardName] = 0;
                                        }
                                        $wardUsage[$wardName] += $usageInUnits;
                                    }

                                    $result2->data_seek(0); // Reset result pointer for display loop

                                    // Second pass: Display data
                                    while ($row = $result2->fetch_assoc()) {
                                        $wardName = $row['ward_group'];
                                        $antibioticName = $row['antibiotic_name'];
                                        $dosage = strtolower($row['dosage']);
                                        $itemCount = $row['total_items'];
                                        $usageInGrams = 0;

                                        if (preg_match('/(\d+)\s*mg/', $dosage, $matches)) {
                                            $mgValue = (int)$matches[1];
                                            $usageInGrams = ($mgValue / 1000) * $itemCount;
                                        } elseif (preg_match('/(\d+)\s*g/', $dosage, $matches)) {
                                            $gValue = (float)$matches[1];
                                            $usageInGrams = $gValue * $itemCount;
                                        }

                                        $usageInUnits = $usageInGrams;
                                        $percentageUsage = ($totalUnits > 0) ? ($usageInUnits / $totalUnits) * 100 : 0;

                                        // Group by ward name
                                        if ($wardName != $previousWard) {
                                            // Ward heading row
                                            echo "<tr><td colspan='6' class='text-center card-title' style='background-color: #f8f9fa; font-weight: bold;'>$wardName</td></tr>";
                                            $previousWard = $wardName;
                                        }
                                ?>
                                        <tr>
                                            <td class='text-center'><?php echo $rowNumber; ?></td>
                                            <td class='text-center'><?php echo $antibioticName; ?></td>
                                            <td class='text-center'><?php echo $dosage; ?></td>
                                            <td class='text-center'><?php echo number_format($itemCount); ?></td>
                                            <td class='text-center'><?php echo number_format($usageInUnits, 2); ?>g</td>
                                            <td class='text-center'><?php echo number_format($percentageUsage, 2); ?>%</td>
                                        </tr>
                                <?php 
                                        $rowNumber++;
                                    }

                                    // Display total usage per ward and the overall total at the end
                                    //echo "<tr><td colspan='6' class='text-center' style='font-weight: bold;'>Total Units: " . number_format($totalUnits, 2) . "g</td></tr>";

                                    // Display each ward's total usage
                                    //foreach ($wardUsage as $ward => $wardTotal) {
                                      //  echo "<tr><td colspan='6' class='text-center' style='background-color: #e9ecef;'>$ward - Total Usage: " . number_format($wardTotal, 2) . "g</td></tr>";
                                   // }
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
</body>
</html>
