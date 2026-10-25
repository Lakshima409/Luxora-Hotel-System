<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailLocal = __DIR__ . "/mail.local.php";
if (is_file($mailLocal)) require_once $mailLocal;

$phpMailerBase = __DIR__ . "/PHPMailer/src";
if (is_file($phpMailerBase . "/Exception.php")) {
    require $phpMailerBase . "/Exception.php";
    require $phpMailerBase . "/PHPMailer.php";
    require $phpMailerBase . "/SMTP.php";
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/includes/helpers.php";

$smtpHost     = getenv("SMTP_HOST")     ?: (isset($smtp_host)     ? $smtp_host     : "smtp.gmail.com");
$smtpPort     = (int)(getenv("SMTP_PORT") ?: (isset($smtp_port)     ? $smtp_port     : 587));
$smtpUser     = getenv("SMTP_USER")     ?: (isset($smtp_user)     ? $smtp_user     : "");
$smtpPass     = getenv("SMTP_PASS")     ?: (isset($smtp_pass)     ? $smtp_pass     : "");
$smtpFrom     = getenv("SMTP_FROM")     ?: (isset($smtp_from)     ? $smtp_from     : $smtpUser);
$smtpFromName = getenv("SMTP_FROM_NAME")?: (isset($smtp_from_name) ? $smtp_from_name : "Hotel Booking");
$smtpSecure   = getenv("SMTP_SECURE")   ?: (isset($smtp_secure)   ? $smtp_secure   : "tls");

if (!defined("OTP_TTL")) define("OTP_TTL", 300);
if (!defined("OTP_MAX_TRIES")) define("OTP_MAX_TRIES", 5);
if (!defined("OTP_RESEND_COOLDOWN")) define("OTP_RESEND_COOLDOWN", 60);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: resetpassword.php');
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = ' Invalid request. Please try again.';
    header('Location: resetpassword.php');
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$genericMessage = ' If an account exists for that email, a reset code has been sent.';

// Basic validation; keep response generic.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = $genericMessage;
    header('Location: verifycode_form.php');
    exit;
}

// Cooldown based on any existing reset OTP.
if (isset($_SESSION['otp_issued_at']) && (time() - (int)$_SESSION['otp_issued_at'] < OTP_RESEND_COOLDOWN)) {
    $_SESSION['error'] = $genericMessage;
    header('Location: verifycode_form.php');
    exit;
}

// Look up active user by email.
$stmt = $pdo->prepare("SELECT user_id, username, email FROM users WHERE email = ? AND active = 1 LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Only issue when the account exists; response stays generic either way.
if ($user) {
    if (!class_exists("PHPMailer\\PHPMailer\\PHPMailer")) {
        error_log('Request_Reset: PHPMailer is not installed');
        $_SESSION['error'] = $genericMessage;
        header('Location: verifycode_form.php');
        exit;
    }
    if ($smtpUser === "" || $smtpPass === "") {
        error_log('Request_Reset: SMTP credentials are not configured');
        $_SESSION['error'] = $genericMessage;
        header('Location: verifycode_form.php');
        exit;
    }

    $otp = random_int(100000, 999999);
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp'] = (string)$otp;
    $_SESSION['otp_issued_at'] = time();
    $_SESSION['otp_attempts'] = 0;
    $_SESSION['reset_user_id'] = (int)$user['user_id'];
    $_SESSION['reset_flow'] = 'password_reset';
    unset($_SESSION['otp_verified'], $_SESSION['otp_verified_at'], $_SESSION['otp_verified_email']);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        if ($smtpSecure === "ssl") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = $smtpPort;
        $mail->setFrom($smtpFrom ?: $smtpUser, $smtpFromName);
        $mail->addAddress($email);
        $mail->Subject = "Password Reset Code";
        $mail->Body = "Hello,\n\nYour password reset code is: $otp\nIt is valid for 5 minutes.\n\n- Hotel Booking System";
        $mail->send();
    } catch (Exception $e) {
        unset($_SESSION['otp'], $_SESSION['otp_email'], $_SESSION['otp_issued_at'], $_SESSION['otp_attempts'], $_SESSION['reset_user_id'], $_SESSION['reset_flow'], $_SESSION['otp_verified'], $_SESSION['otp_verified_at'], $_SESSION['otp_verified_email']);
        $_SESSION['error'] = ' Unable to send reset code. Please try again later.';
        header('Location: resetpassword.php');
        exit;
    }
}

$_SESSION['error'] = $genericMessage;
header('Location: verifycode_form.php');
exit;
