<?php require_once 'config.php'; require_once 'includes/helpers.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <link href="stylee.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <section class="login-box">
            <div class="form-step active" id="step-2">
                <h1 class="login-title">Verify Code</h1>
                <p class="login-subtitle">Check your email for the reset code</p>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div style="background:#fdecea;color:#b71c1c;padding:10px;border-radius:6px;margin-bottom:12px;"><?= e($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <form class="login-form" id="verify-code-form" action="verifycode.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" id="verify-email" name="email">
                    <div class="form-group">
                        <label for="code">
                            <i class="fas fa-key"></i> Reset Code
                        </label>
                        <input type="text" id="code" name="otp" class="form-input" placeholder="Enter 6-digit code" required>
                    </div>
                    <button type="submit" class="login-button">
                        <span>Verify Code</span>
                        <i class="fas fa-check"></i>
                    </button>
                    <div class="register-link">
                        Didn't receive code? <button type="submit" name="resend" value="1" class="signup-link" style="background:none;border:none;padding:0;cursor:pointer;">Resend</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</body>
</html>
