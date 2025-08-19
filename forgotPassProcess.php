<?php
session_start();
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions/connectdb.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper: generate a secure token
function generateToken(int $length = 48): string {
    return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
}

$email = trim($_POST['email'] ?? '');
if ($email === '') {
    header('Location: forgotpass.php?msg=missingEmail');
    exit;
}

// Lookup user by email
$emailSafe = mysqli_real_escape_string($con, $email);
$res = mysqli_query($con, "SELECT id, email FROM users WHERE email = '$emailSafe' LIMIT 1");
$user = $res ? mysqli_fetch_assoc($res) : null;

// Always behave the same even if email not found (avoid enumeration)
$resetRequested = false;
if ($user && isset($user['id'])) {
    $token = generateToken(32);
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60); // 1 hour

    // Ensure table exists (lightweight safety); in production, manage schema separately
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        INDEX (user_id),
        INDEX (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Invalidate previous tokens for this user
    mysqli_query($con, "UPDATE password_resets SET used = 1 WHERE user_id = " . (int)$user['id']);

    // Insert new reset record
    $tokenSafe = mysqli_real_escape_string($con, $token);
    $expSafe = mysqli_real_escape_string($con, $expiresAt);
    mysqli_query($con, "INSERT INTO password_resets (user_id, token, expires_at, used) VALUES (" . (int)$user['id'] . ", '$tokenSafe', '$expSafe', 0)");

    // Build reset URL
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\');
    $resetUrl = $scheme . '://' . $host . $path . '/reset_password.php?token=' . urlencode($token);

    // Send email
    $cfg = require __DIR__ . '/functions/mail_config.php';
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['username'];
        $mail->Password = $cfg['password'];
        $mail->Port = $cfg['port'];
        $mail->SMTPSecure = ($cfg['secure'] === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($user['email']);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Fleetara Password Reset';
        $mail->Body = '<p>Hello,</p><p>We received a request to reset your Fleetara password.</p>'
            . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Click here to reset your password</a></p>'
            . '<p>This link will expire in 1 hour. If you did not request this, you can ignore this email.</p>'
            . '<p>Thanks,<br>Fleetara</p>';
        $mail->AltBody = "Open this link to reset your password: $resetUrl\nThis link expires in 1 hour.";

        $mail->send();
        $resetRequested = true;
    } catch (Exception $e) {
        error_log('Password reset email failed: ' . $mail->ErrorInfo);
        // fall through to generic message
        $resetRequested = true; // still do not leak status
    }
}

// Always show the same result to prevent email enumeration
header('Location: forgotpass.php?msg=sent');
exit;
