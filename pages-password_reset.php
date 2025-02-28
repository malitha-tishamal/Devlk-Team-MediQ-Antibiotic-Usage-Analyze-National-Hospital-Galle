<?php
session_start();
require_once("includes/db-conn.php");

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if token exists and is valid
    $query = "SELECT user_id, expire_time FROM password_reset WHERE token = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $resetRequest = $result->fetch_assoc();
        $userId = $resetRequest['user_id'];
        $expireTime = $resetRequest['expire_time'];

        if (time() < $expireTime) {
            // Token is valid, allow password reset
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // Get the new password
                $newPassword = password_hash($_POST['password'], PASSWORD_BCRYPT);

                // Update password in the database
                $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("si", $newPassword, $userId);
                $stmt->execute();

                // Delete the token from the database
                $deleteQuery = "DELETE FROM password_reset WHERE token = ?";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bind_param("s", $token);
                $stmt->execute();

                $_SESSION['message'] = "Your password has been reset successfully.";
                header("Location: login.php");
            }
        } else {
            $_SESSION['message'] = "This token has expired.";
        }
    } else {
        $_SESSION['message'] = "Invalid token.";
    }
} else {
    $_SESSION['message'] = "No token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Reset Password - MediQ</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    <?php include_once("includes/css-links-inc.php"); ?>
</head>

<body>
    <main>
        <div class="container">
            <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">
                            <div class="d-flex justify-content-center py-4">
                                <a href="index.php" class="logo d-flex align-items-center w-auto">
                                    <img src="assets/images/logos/mediq-logo.png" alt="" style="max-height:130px;">
                                </a>
                            </div><!-- End Logo -->

                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="pt-4 pb-2">
                                        <h5 class="card-title text-center pb-0 fs-4">Reset Password</h5>
                                    </div>

                                    <form action="" method="POST" class="row g-3 needs-validation" novalidate>
                                        <div class="col-12">
                                            <label for="password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                            <div class="invalid-feedback">Please enter a new password!</div>
                                        </div>

                                        <div class="col-12 mt-3">
                                            <input type="submit" class="btn btn-primary w-100" value="Reset Password">
                                        </div>
                                    </form>

                                </div>
                            </div>

                            <?php include_once("includes/footer3.php") ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
    <?php include_once("includes/js-links-inc.php") ?>
</body>

</html>
