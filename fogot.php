<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/includes/db-conn.php";

// ✅ Remove Composer autoload
// require_once __DIR__ . "/vendor/autoload.php";

// Include PHPMailer manually
require_once __DIR__ . "/includes/phpmailer/src/PHPMailer.php";
require_once __DIR__ . "/includes/phpmailer/src/SMTP.php";
require_once __DIR__ . "/includes/phpmailer/src/Exception.php";

require_once __DIR__ . "/includes/mail-config.php"; // $MAILER_CONFIG array

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ... rest of your code remains exactly the same


// Always return the same generic response to avoid email enumeration
function respond_and_exit(string $msg = "If that email exists, a reset link has been sent. Please check your inbox."): void {
    $_SESSION['message'] = $msg;
    $_SESSION['status']  = 'info';
    header("Location: pages-forgotten-password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_and_exit();
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond_and_exit(); // keep generic
}

try {
    // OPTIONAL: Lightweight rate-limit per IP (1 request / 60 seconds)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!isset($_SESSION['last_reset_request'])) $_SESSION['last_reset_request'] = [];
    $last = $_SESSION['last_reset_request'][$ip] ?? 0;
    if (time() - $last < 60) {
        respond_and_exit(); // generic
    }
    $_SESSION['last_reset_request'][$ip] = time();

    // Check existence in admins OR users (keep schema-agnostic by counting)
    $exists = false;

    // admins
    $stmt = $conn->prepare("SELECT 1 FROM admins WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $exists = true;
    $stmt->close();

    // users (only if not found in admins)
    if (!$exists) {
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $exists = true;
        $stmt->close();
    }

    // We still proceed silently even if not found, to prevent enumeration
    // Generate token + expiry (1 hour)
    $token   = bin2hex(random_bytes(32));
    $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

    // Upsert token row
    $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $email, $token, $expires);
    $stmt->execute();
    $stmt->close();

    // Prepare email (we don’t reveal whether the email is valid)
    $resetLink = "https://mediq.42web.io/pages-reset-password.php?token={$token}";
    $subject   = "Password Reset Request";
    $plainText = "We received a request to reset your password.\n\n"
               . "Click the link below to reset your password:\n{$resetLink}\n\n"
               . "This link will expire in 1 hour. If you didn't request this, you can ignore this email.\n";

    $htmlBody  = '<p>We received a request to reset your password.</p>'
               . '<p><a href="'.htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8').'">Reset your password</a></p>'
               . '<p>This link will expire in <strong>1 hour</strong>. If you didn\'t request this, you can ignore this email.</p>';

    // Send via PHPMailer (SMTP)
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $MAILER_CONFIG['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $MAILER_CONFIG['username'];
        $mail->Password   = $MAILER_CONFIG['password'];
        $mail->Port       = (int)$MAILER_CONFIG['port'];
        // TLS/SSL
        if (!empty($MAILER_CONFIG['encryption'])) {
            $mail->SMTPSecure = $MAILER_CONFIG['encryption']; // 'tls' or 'ssl'
        }

        // Sender / Reply-To
        $mail->setFrom($MAILER_CONFIG['from_email'], $MAILER_CONFIG['from_name']);
        if (!empty($MAILER_CONFIG['reply_to'])) {
            $mail->addReplyTo($MAILER_CONFIG['reply_to']);
        }

        // Recipient (we send regardless of account existence)
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainText;

        $mail->send();
        // Always generic response
        respond_and_exit();
    } catch (Exception $e) {
        // You can log $e->getMessage() to a file for debugging (not shown to user)
        respond_and_exit(); // generic
    }

} catch (Throwable $th) {
    // Log $th->getMessage() if needed
    respond_and_exit(); // generic
}
