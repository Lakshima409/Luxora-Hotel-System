<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'includes/helpers.php';

function get_redirect_url($role) {
    switch ($role) {
        case 'admin': return 'admin/admin_dashboard.php';
        case 'manager': return 'admin/manager_dashboard.php';
        case 'clerk': return 'clerk_dashboard.php';
        case 'guest': return 'public/my_bookings.php';
        default: return 'index.php';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Support both normal form POST and JSON/AJAX clients (decided by Accept header or ajax flag)
    $wantsJson = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) || isset($_POST['ajax']);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
        // SECURITY: prepared statement with bound parameter => immune to SQL injection.
        // We only fetch ACTIVE accounts (active=1) so disabled users can never log in.
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND active=1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        // Support both a bcrypt hash column (password_hash) and a legacy plain 'password' column.
        $hash = $user['password_hash'] ?? $user['password'] ?? '';
        // BRUTE-FORCE PROTECTION: if the account is currently locked (locked_until in the future), refuse.
        if ($user && !empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $msg = "Account locked. Try again later.";
            if ($wantsJson) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>$msg]); exit(); }
            $error = $msg;
        } elseif ($user && password_verify($password, $hash)) {
            // AUTH SUCCESS — password_verify() checks the bcrypt hash (never compare plaintext).
            // Reset the failed-attempt counter and clear any lockout.
            $pdo->prepare("UPDATE users SET failed_attempts=0, locked_until=NULL WHERE user_id=?")->execute([$user['user_id']]);
            // SESSION FIXATION DEFENSE: generate a brand-new session id (delete old) before
            // storing the login state, so an attacker who pre-set a cookie can't hijack it.
            session_regenerate_id(true);
            // Store the authenticated identity + role in the session.
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_role'] = $user['role']; // legacy alias some pages still read
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();
            log_action_pdo($pdo,$user['user_id'],$user['role'],'login',"Login success: $username");
            if ($wantsJson) { header('Content-Type: application/json'); echo json_encode(['success' => true,'redirect' => get_redirect_url($user['role'])]); exit(); }
            redirect(get_redirect_url($user['role'])); // role-based home page
        } else {
            // AUTH FAILURE — increment failed attempts; lock the account after MAX_LOGIN_ATTEMPTS.
            if ($user) {
                $fails = ((int)$user['failed_attempts'])+1;
                if ($fails >= MAX_LOGIN_ATTEMPTS) {
                    // Lock for LOGIN_LOCKOUT_MIN minutes from now.
                    $pdo->prepare("UPDATE users SET failed_attempts=?, locked_until=DATE_ADD(NOW(), INTERVAL ".LOGIN_LOCKOUT_MIN." MINUTE) WHERE user_id=?")->execute([$fails,$user['user_id']]);
                } else { $pdo->prepare("UPDATE users SET failed_attempts=? WHERE user_id=?")->execute([$fails,$user['user_id']]); }
            }
            // Generic message (do NOT reveal whether the username exists) => avoids user enumeration.
            $msg = "Invalid username or password";
            if ($wantsJson) { header('Content-Type: application/json'); echo json_encode(['success' => false,'error' => $msg]); exit(); }
            $error = $msg;
        }
    } catch (Exception $e) {
        error_log($e->getMessage()); // log real cause server-side; never echo DB errors to the user
        $msg = "Database error. Run database/schema.sql first.";
        if ($wantsJson) { header('Content-Type: application/json'); echo json_encode(['success' => false,'error' => $msg]); exit(); }
        $error = $msg;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Luxora Hotel Suite - Clerk Login</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0c4b33; /* Deep emerald green */
            --primary-light: #1a6b4a;
            --primary-dark: #083023;
            --secondary: #d4af37; /* Gold accent */
            --light: #f8f5f2; /* Warm off-white */
            --dark: #1a1a1a;
            --radius: 6px;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: linear-gradient(rgba(12, 75, 51, 0.2), rgba(12, 75, 51, 0.2)), 
                              url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transform: translateY(-20px);
            animation: fadeInUp 0.5s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background-color: var(--primary-dark);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.2) 0%, transparent 100%);
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .logo span {
            color: var(--secondary);
        }

        .login-subtitle {
            font-size: 14px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .login-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            font-family: inherit;
            transition: var(--transition);
            font-size: 15px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(12, 75, 51, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 38px;
            color: var(--primary);
            font-size: 18px;
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 14px;
            background-color: var(--secondary);
            color: var(--dark);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 15px;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        .btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(12, 75, 51, 0.2);
        }

        .forgot-password {
            text-align: right;
            margin-top: -15px;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: var(--primary);
            font-size: 13px;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-password a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .login-footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 14px;
            color: var(--dark);
        }

        .login-footer a {
            color: var(--primary);
            font-weight: 600;
            transition: var(--transition);
        }

        .login-footer a:hover {
            color: var(--secondary);
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            .login-container {
                margin: 0 15px;
            }
            
            .login-header {
                padding: 25px 20px;
            }
            
            .login-body {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">Luxora<span>Hotel Suite</span></div>
            <div class="login-subtitle">Clerk Login Portal</div>
        </div>
        <div class="login-body">
            <?php if(!empty($error)): ?><div style="background:#fdecea;color:#b71c1c;padding:10px;border-radius:6px;margin-bottom:12px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form id="loginForm" method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
                <div class="forgot-password">
                    <a href="resetpassword.html">Forgot password?</a>
                </div>
                <button type="submit" class="btn">Login</button>
                <p style="margin-top:10px;font-size:12px;opacity:.8">Demo: clerk1/12345 · manager1/manager123 · admin1/admin123</p>
            </form>
        </div>
        <div class="login-footer">
            Having trouble? <a href="#">Contact administrator</a>
        </div>
    </div>

    <script>
        // Real POST login (no bypass). Optional AJAX kept for API clients.
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            // allow normal POST; only block empty
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            if (!username || !password) { e.preventDefault(); alert('Please fill in all fields'); return false; }
            return true;
        });
        
        // Add animation when inputs are focused
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentNode.querySelector('label').style.color = 'var(--secondary)';
                this.parentNode.querySelector('.input-icon').style.color = 'var(--secondary)';
            });
            
            input.addEventListener('blur', function() {
                this.parentNode.querySelector('label').style.color = 'var(--primary)';
                this.parentNode.querySelector('.input-icon').style.color = 'var(--primary)';
            });
        });
    </script>
</body>
</html>