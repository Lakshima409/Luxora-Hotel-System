<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Optional local mail settings (git-ignored; see mail.local.php.example).
$mailLocal = __DIR__ . "/mail.local.php";
if (is_file($mailLocal)) {
    require_once $mailLocal;
}

$phpMailerBase = __DIR__ . "/PHPMailer/src";
if (is_file($phpMailerBase . "/Exception.php")) {
    require $phpMailerBase . "/Exception.php";
    require $phpMailerBase . "/PHPMailer.php";
    require $phpMailerBase . "/SMTP.php";
}

session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/includes/helpers.php";


// Resolve SMTP settings from environment first, then local config variables, then safe defaults.
$smtpHost     = getenv("SMTP_HOST")     ?: (isset($smtp_host)     ? $smtp_host     : "smtp.gmail.com");
$smtpPort     = (int)(getenv("SMTP_PORT") ?: (isset($smtp_port)     ? $smtp_port     : 587));
$smtpUser     = getenv("SMTP_USER")     ?: (isset($smtp_user)     ? $smtp_user     : "");
$smtpPass     = getenv("SMTP_PASS")     ?: (isset($smtp_pass)     ? $smtp_pass     : "");
$smtpFrom     = getenv("SMTP_FROM")     ?: (isset($smtp_from)     ? $smtp_from     : $smtpUser);
$smtpFromName = getenv("SMTP_FROM_NAME")?: (isset($smtp_from_name) ? $smtp_from_name : "Hotel Booking");
$smtpSecure   = getenv("SMTP_SECURE")   ?: (isset($smtp_secure)   ? $smtp_secure   : "tls");

// OTP constants
if (!defined("OTP_TTL"))      define("OTP_TTL", 300);   // 5 minutes
if (!defined("OTP_MAX_TRIES")) define("OTP_MAX_TRIES", 5);
if (!defined("OTP_RESEND_COOLDOWN")) define("OTP_RESEND_COOLDOWN", 60); // seconds

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Resend OTP request
    if (isset($_POST["resend"])) {
        if (isset($_SESSION["otp_email"])) {
            $email = $_SESSION["otp_email"];

            // Enforce resend cooldown before generating a new OTP.
            if (isset($_SESSION["otp_issued_at"]) && (time() - (int)$_SESSION["otp_issued_at"] < OTP_RESEND_COOLDOWN)) {
                $remaining = OTP_RESEND_COOLDOWN - (time() - (int)$_SESSION["otp_issued_at"]);
                $_SESSION["error"] = " Please wait " . $remaining . "s before requesting a new code.";
                header("Location: verifycode_form.php");
                exit;
            }

            // Guard: PHPMailer must be installed.
            if (!class_exists("PHPMailer\PHPMailer\PHPMailer")) {
                $_SESSION["error"] = " Mail service is not installed. Please install PHPMailer (see README).";
                header("Location: verifycode_form.php");
                exit;
            }
            // Guard: SMTP credentials must be configured.
            if ($smtpUser === "" || $smtpPass === "") {
                $_SESSION["error"] = " Mail settings are not configured. Please set SMTP_USER/SMTP_PASS (see mail.local.php.example).";
                header("Location: verifycode_form.php");
                exit;
            }

            // Issue a new OTP and reset state.
            $otp = random_int(100000, 999999);
            $_SESSION["otp"]           = (string)$otp;
            $_SESSION["otp_issued_at"] = time();
            $_SESSION["otp_attempts"]  = 0;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpUser;
                $mail->Password   = $smtpPass;
                if ($smtpSecure === "ssl") {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
                $mail->Port       = $smtpPort;

                $mail->setFrom($smtpFrom ?: $smtpUser, $smtpFromName);
                $mail->addAddress($email);
                $mail->Subject = "Resent OTP Code";
                $mail->Body    = "Hello,\n\nYour new OTP code is: $otp\nIt is valid for 5 minutes.\n\n- Hotel Booking System";

                $mail->send();
                $_SESSION["error"] = " OTP resent successfully!";
                header("Location: verifycode_form.php");
                exit;
            } catch (Exception $e) {
                $_SESSION["error"] = " OTP resend failed. Error: {$mail->ErrorInfo}";
                unset($_SESSION["otp"], $_SESSION["otp_issued_at"], $_SESSION["otp_attempts"]);
                header("Location: verifycode_form.php");
                exit;
            }
        } else {
            $_SESSION["error"] = " Session expired. Try again.";
            header("Location: verifycode_form.php");
            exit;
        }
    }

    // OTP verify
    $userOtp = isset($_POST["otp"]) ? trim((string)$_POST["otp"]) : "";
    if (isset($_SESSION["otp"]) && isset($_SESSION["otp_email"])) {
        // Reject OTPs with missing or invalid issuance timestamps.
        $issuedAt = $_SESSION['otp_issued_at'] ?? null;
        if (!is_int($issuedAt) && !ctype_digit((string)$issuedAt)) {
            unset($_SESSION["otp"], $_SESSION["otp_email"], $_SESSION["otp_issued_at"], $_SESSION["otp_attempts"], $_SESSION["reset_user_id"], $_SESSION["otp_verified"], $_SESSION["otp_verified_at"], $_SESSION["otp_verified_email"]);
            $_SESSION["error"] = " OTP expired. Request a new code.";
            header("Location: verifycode_form.php");
            exit;
        }
        $issuedAt = (int)$issuedAt;
        if ($issuedAt <= 0 || $issuedAt > time()) {
            unset($_SESSION["otp"], $_SESSION["otp_email"], $_SESSION["otp_issued_at"], $_SESSION["otp_attempts"], $_SESSION["reset_user_id"], $_SESSION["otp_verified"], $_SESSION["otp_verified_at"], $_SESSION["otp_verified_email"]);
            $_SESSION["error"] = " OTP expired. Request a new code.";
            header("Location: verifycode_form.php");
            exit;
        }
        if (!isset($_SESSION["otp_attempts"])) {
            $_SESSION["otp_attempts"] = 0;
        }

        // Malformed input check.
        if (!preg_match("/^\d{6}$/", $userOtp)) {
            $_SESSION["error"] = " Invalid code format.";
            header("Location: verifycode_form.php");
            exit;
        }

        // Attempt limit.
        if ((int)$_SESSION["otp_attempts"] >= OTP_MAX_TRIES) {
            $_SESSION["error"] = " Too many failed attempts. Request a new code.";
            header("Location: verifycode_form.php");
            exit;
        }

        // Expiry check.
        if (time() - $issuedAt > OTP_TTL) {
            $_SESSION["error"] = " OTP expired. Request a new code.";
            header("Location: verifycode_form.php");
            exit;
        }

        $storedOtp = (string)$_SESSION["otp"];
        if ($userOtp === $storedOtp) {
            // Consume the OTP so it cannot be reused.
            $_SESSION['otp_verified'] = true;
            $_SESSION['otp_verified_at'] = time();
            $_SESSION['otp_verified_email'] = (string)$_SESSION['otp_email'];
            unset($_SESSION["otp"], $_SESSION["otp_issued_at"], $_SESSION["otp_attempts"]);
            // Prevent session fixation while preserving the reset-flow state.
            session_regenerate_id(true);
            header("Location: newpass_form.php");
            exit;
        } else {
            $_SESSION["otp_attempts"] = (int)$_SESSION["otp_attempts"] + 1;
            if ((int)$_SESSION["otp_attempts"] >= OTP_MAX_TRIES) {
                $_SESSION["error"] = " Too many failed attempts. Request a new code.";
            } else {
                $remaining = OTP_MAX_TRIES - (int)$_SESSION["otp_attempts"];
                $_SESSION["error"] = " Invalid OTP (" . $remaining . " attempts remaining).";
            }
            header("Location: verifycode_form.php");
            exit;
        }
    } else {
        $_SESSION["error"] = "Session expired. Try again.";
        header("Location: verifycode_form.php");
        exit;
    }
} else {
    header("Location: verifycode_form.php");
    exit;
}
