<?php
session_start();
date_default_timezone_set('Asia/Colombo'); // ✅ Set Sri Lanka Time Zone

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

// Fetch active book numbers for dropdown
$bookOptions = [];
$bookQuery = $conn->query("SELECT id, book_number FROM book_transactions WHERE status = 'active' ORDER BY book_number ASC");
while ($row = $bookQuery->fetch_assoc()) {
    $bookOptions[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Dispensing Antibiotics - Mediq</title>

    <?php include_once("includes/css-links-inc.php"); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        /* Suggestion dropdown */
        .autocomplete-list {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ccc;
            border-top: none;
            list-style-type: none;
            padding: 0;
            margin: 0;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }
        .autocomplete-list li {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .autocomplete-list li:hover {
            background-color: #f8f9fa;
        }

        /* Popup message */
        .popup-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 25px;
            background-color: #28a745;
            color: white;
            font-weight: bold;
            border-radius: 30px;
            display: none;
            z-index: 9999;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.2);
        }
        .error-popup {
            background-color: #dc3545;
        }
    </style>
</head>

<body>
    <!-- ✅ Session popup -->
    <?php if (isset($_SESSION['status'])): ?>
        <div class="popup-message <?php echo ($_SESSION['status'] == 'success') ? '' : 'error-popup'; ?>" id="popup-alert">
            <?php echo $_SESSION['message']; ?>
        </div>
        <script>
            document.getElementById('popup-alert').style.display = 'block';
            setTimeout(() => document.getElementById('popup-alert').style.display = 'none', 1500);
        </script>
        <?php unset($_SESSION['status'], $_SESSION['message']); ?>
    <?php endif; ?>

    <?php include_once("includes/header.php"); ?>
    <?php include_once("includes/user-sidebar.php"); ?>

    <main id="main" class="main">
        <div class="pagetitle mb-3">
            <h1><i class="bi bi-capsule me-2 text-primary"></i>Dispensing Antibiotics</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item">Pages</li>
                    <li class="breadcrumb-item active">Dispensing Antibiotics</li>
                </ol>
            </nav>
        </div>

        <!-- ✅ Modernized Form -->
        <section class="section">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-lg border-0 rounded-4">
                        <div class="card-header bg-primary text-white rounded-top-4">
                            <h4 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i> Antibiotic Release Form</h4>
                        </div>
                        <div class="card-body p-4">
                            <form action="update_release.php" method="POST" id="releaseForm" class="row g-3">
                                <!-- Hidden IDs -->
                                <input type="hidden" id="antibiotic_id" name="antibiotic_id">
                                <input type="hidden" id="ward_id" name="ward_id">

                                <!-- Antibiotic -->
                                <div class="col-md-6 position-relative">
                                    <label class="form-label fw-bold"><i class="bi bi-search"></i> Antibiotic</label>
                                    <input type="text" id="antibiotic" name="antibiotic_name"
                                        class="form-control rounded-pill"
                                        placeholder="Type to search..." autocomplete="off" required>
                                    <ul id="autocomplete-antibiotic" class="autocomplete-list"></ul>
                                </div>

                                <!-- Dosage -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><i class="bi bi-droplet-half"></i> Dosage</label>
                                    <input type="text" id="dosage" name="dosage"
                                        class="form-control rounded-pill" readonly>
                                </div>

                                <!-- Ward -->
                                <div class="col-md-6 position-relative">
                                    <label class="form-label fw-bold"><i class="bi bi-hospital"></i> Release Ward</label>
                                    <input type="text" id="ward" name="ward"
                                        class="form-control rounded-pill"
                                        placeholder="Type to search ward..." autocomplete="off">
                                    <ul id="autocomplete-ward" class="autocomplete-list"></ul>
                                </div>

                                <!-- Date & Time -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><i class="bi bi-clock"></i> Date & Time</label>
                                    <div class="form-check">
                                        <input type="radio" class="form-check-input" name="datetime_option" id="useCurrent" value="current" checked>
                                        <label class="form-check-label" for="useCurrent">Use current system time</label>
                                    </div>
                                    <div class="form-check mt-1">
                                        <input type="radio" class="form-check-input" name="datetime_option" id="useManual" value="manual">
                                        <label class="form-check-label" for="useManual">Enter manually</label>
                                    </div>
                                    <input type="hidden" name="current_datetime" id="currentDateTime">
                                    <input type="datetime-local" name="manual_datetime" id="manualDateTime" class="form-control rounded-pill mt-2" style="display: none;">
                                </div>

                                <!-- Book Number -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><i class="bi bi-book"></i> Book Number</label>
                                    <select name="book_number_select" id="book_number_select" class="form-select rounded-pill">
                                        <option value="">-- Select Active Book --</option>
                                        <?php foreach ($bookOptions as $book): ?>
                                            <option value="<?= $book['book_number'] ?>"><?= htmlspecialchars($book['book_number']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Page Number -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><i class="bi bi-file-text"></i> Page Number</label>
                                    <input type="text" name="page_number_manual" id="page_number_manual"
                                        class="form-control rounded-pill" placeholder="Enter page number">
                                </div>

                                <!-- Item Count -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><i class="bi bi-123"></i> Item Count</label>
                                    <input type="number" id="itemCount" name="item_count"
                                        class="form-control rounded-pill" placeholder="Enter item count" required>
                                </div>

                                <!-- Stock Type -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><i class="bi bi-box-seam"></i> Stock Source</label>
                                    <div class="d-flex gap-4 mt-2">
                                        <div class="form-check">
                                            <input type="radio" id="msd" name="type" class="form-check-input" value="msd">
                                            <label for="msd" class="form-check-label">MSD</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="radio" id="lp" name="type" class="form-check-input" value="lp">
                                            <label for="lp" class="form-check-label">LP</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Buttons -->
                                <div class="col-12 text-end mt-4">
                                    <button type="submit" class="btn btn-success px-4 rounded-pill">
                                        <i class="bi bi-save me-1"></i> Update Database
                                    </button>
                                    <button type="reset" class="btn btn-outline-danger px-4 rounded-pill">
                                        <i class="bi bi-x-circle me-1"></i> Clear
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once("includes/footer.php"); ?>
    <?php include_once("includes/js-links-inc.php"); ?>

    <script>
        // Current datetime auto-fill
        function setCurrentDateTime() {
            const now = new Date();
            const formatted = now.toISOString().slice(0, 16);
            document.getElementById("currentDateTime").value = formatted;
        }
        document.getElementById("useCurrent").addEventListener("change", () => {
            document.getElementById("manualDateTime").style.display = "none";
        });
        document.getElementById("useManual").addEventListener("change", () => {
            document.getElementById("manualDateTime").style.display = "block";
        });
        window.addEventListener("DOMContentLoaded", setCurrentDateTime);

        // Autocomplete setup
        function setupAutocomplete(inputId, hiddenId, url, dosageId = null) {
            const input = document.getElementById(inputId);
            const hidden = document.getElementById(hiddenId);
            const list = document.getElementById(`autocomplete-${inputId}`);

            input.addEventListener("keyup", function () {
                const term = this.value.trim();
                if (term.length < 1) { list.innerHTML = ""; return; }

                fetch(`${url}?term=${encodeURIComponent(term)}`)
                    .then(r => r.json())
                    .then(data => {
                        list.innerHTML = "";
                        if (!data.length) {
                            list.innerHTML = `<li class="text-muted">No results</li>`;
                            return;
                        }
                        data.forEach(item => {
                            const li = document.createElement("li");
                            li.innerHTML = `<strong>${item.name}</strong> ${dosageId ? `(${item.dosage})` : ''}`;
                            li.addEventListener("click", () => {
                                input.value = item.name;
                                hidden.value = item.id;
                                if (dosageId) document.getElementById(dosageId).value = item.dosage;
                                list.innerHTML = "";
                            });
                            list.appendChild(li);
                        });
                    });
            });

            document.addEventListener("click", e => {
                if (!input.contains(e.target) && !list.contains(e.target)) list.innerHTML = "";
            });
        }
        setupAutocomplete("antibiotic", "antibiotic_id", "get_antibiotics.php", "dosage");
        setupAutocomplete("ward", "ward_id", "get_wards.php");

        // Validate selection before submit
        document.getElementById("releaseForm").addEventListener("submit", function (e) {
            if (!document.getElementById("antibiotic_id").value || !document.getElementById("ward_id").value) {
                alert("⚠️ Please select valid antibiotic and ward from the list.");
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
