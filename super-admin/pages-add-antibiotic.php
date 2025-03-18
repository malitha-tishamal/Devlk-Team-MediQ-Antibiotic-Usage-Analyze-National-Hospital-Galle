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
$sql = "SELECT name, email, nic,mobile,profile_picture FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>Add Antibiotic - Mediq</title>

    <?php include_once("../includes/css-links-inc.php"); ?>

    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

    <style>
        /* Styling for the popup */
        .popup-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px;
            background-color: #28a745;
            color: white;
            font-weight: bold;
            border-radius: 5px;
            display: none; /* Hidden by default */
            z-index: 9999;
        }

        .error-popup {
            background-color: #dc3545;
        }
        .gramint {
            margin-right: 8px;
        }
    </style>
    
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
            <h1>Add Antibiotic</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                    <li class="breadcrumb-item">Pages</li>
                    <li class="breadcrumb-item active">Add Antibiotic</li>
                </ol>
            </nav>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                    <div class="p-3">
                        <h5 class="text-danger">Gram to Milligram Converter</h5>
                        <div class="d-flex">
                            <input type="number" class="gramint form-control w-50" id="gramInput" placeholder="Enter grams">
                            <p class="output form-control w-50" id="mgresult">Output: </p>
                        </div>

                        <script>
                            document.getElementById("gramInput").addEventListener("keyup", function() {
                                let grams = parseFloat(this.value);
                                if (!isNaN(grams) && grams >= 0) {
                                    let mg = grams * 1000;
                                    document.getElementById("mgresult").textContent = grams + "g (" + mg + "mg)";
                                } else {
                                    document.getElementById("mgresult").textContent = "Output: ";
                                }
                            });
                        </script>
                    </div>
                </div>
                </div>
            </div>
        </section>
        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Add New Antibiotic</h5>
                            <p>Add Antibiotic here.</p>

                            <div class="container mt-4">
                                 <form action="submit.php" method="POST">
                                    <div class="col-md-4 mb-3">
                                        <label for="antibioticName" class="form-label">Antibiotic Name</label>
                                        <input type="text" class="form-control" id="antibioticName" name="antibiotic_name" placeholder="eg: Amoxicillin" required>
                                    </div>

                                    <div class="col-md-2 mb-2">
                                        <label for="dosage" class="form-label">Dosage</label>
                                        <input type="text" class="form-control" id="dosage" name="dosage[]" placeholder="eg: 10mg">
                                    </div>

                                    <div id="dosageFields"></div>

                                    <!-- Container for buttons with space between them -->
                                    <div class="d-flex mt-3">
                                        <button type="button" class="btn btn-primary me-3" id="addDosageBtn">Add Another Dosage</button>
                                        <button type="submit" class="btn btn-success me-3">Submit</button>
                                        <button type="reset" class="btn btn-danger ">Clear</button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once("../includes/footer2.php") ?>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <?php include_once("../includes/js-links-inc.php") ?>
    
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
