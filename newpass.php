<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/includes/helpers.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: newpass_form.php');
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = ' Invalid request. Please try again.';
    header('Location: newpass_form.php');
    exit;
}

// Require successful, unexpired OTP verification bound to the reset flow.
$verified = !empty($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true;
$flowOk = isset($_SESSION['reset_flow']) && $_SESSION['reset_flow'] === 'password_reset';
$userId = isset($_SESSION['reset_user_id']) ? (int)$_SESSION['reset_user_id'] : 0;
$verifiedEmail = isset($_SESSION['otp_verified_email']) ? (string)$_SESSION['otp_verified_email'] : '';
$verifiedAt = isset($_SESSION['otp_verified_at']) ? (int)$_SESSION['otp_verified_at'] : 0;

if (!$verified || !$flowOk || $userId <= 0 || $verifiedEmail === '' || $verifiedAt <= 0 || (time() - $verifiedAt) > 600) {
    $_SESSION['error'] = ' Password reset authorization expired. Start again.';
    unset($_SESSION['otp'], $_SESSION['otp_email'], $_SESSION['otp_issued_at'], $_SESSION['otp_attempts'], $_SESSION['reset_user_id'], $_SESSION['reset_flow'], $_SESSION['otp_verified'], $_SESSION['otp_verified_at'], $_SESSION['otp_verified_email']);
    header('Location: resetpassword.php');
    exit;
}

$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if ($password === '' || $password !== $confirm || strlen($password) < 8) {
    $_SESSION['error'] = ' Passwords must match and be at least 8 characters.';
    header('Location: newpass_form.php');
    exit;
}

// Fetch intended active user.
$stmt = $pdo->prepare("SELECT user_id, username, email, role, active FROM users WHERE user_id = ? AND email = ? AND active = 1 LIMIT 1");
$stmt->execute([$userId, $verifiedEmail]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = ' Password reset authorization expired. Start again.';
    unset($_SESSION['otp'], $_SESSION['otp_email'], $_SESSION['otp_issued_at'], $_SESSION['otp_attempts'], $_SESSION['reset_user_id'], $_SESSION['reset_flow'], $_SESSION['otp_verified'], $_SESSION['otp_verified_at'], $_SESSION['otp_verified_email']);
    header('Location: resetpassword.php');
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password_hash = ?, password = NULL WHERE user_id = ? AND active = 1")
    ->execute([$hash, $userId]);

// Invalidate reset authorization and clean up.
unset($_SESSION['otp'], $_SESSION['otp_email'], $_SESSION['otp_issued_at'], $_SESSION['otp_attempts'], $_SESSION['reset_user_id'], $_SESSION['reset_flow'], $_SESSION['otp_verified'], $_SESSION['otp_verified_at'], $_SESSION['otp_verified_email']);
// Invalidate the CSRF token used for this reset so it cannot be replayed.
unset($_SESSION['csrf_token']);
session_regenerate_id(true);

log_action_pdo($pdo, $userId, $user['role'] ?? 'guest', 'password_reset', "Password reset completed for user_id=$userId");

$_SESSION['success'] = ' Password updated. Please log in.';
header('Location: login.php');
exit;
