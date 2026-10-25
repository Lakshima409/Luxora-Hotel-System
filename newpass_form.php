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
            <div class="form-step active" id="step-3">
                <h1 class="login-title">New Password</h1>
                <p class="login-subtitle">Create a new password for your account</p>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div style="background:#fdecea;color:#b71c1c;padding:10px;border-radius:6px;margin-bottom:12px;"><?= e($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <form class="login-form" id="new-password-form" action="newpass.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" id="reset-email" name="email">
                    <input type="hidden" id="reset-code" name="code">
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter new password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm new password" required>
                    </div>
                    <button type="submit" class="login-button">
                        <span>Update Password</span>
                        <i class="fas fa-save"></i>
                    </button>
                </form>
            </div>
        </section>
    </div>
</body>
</html>
