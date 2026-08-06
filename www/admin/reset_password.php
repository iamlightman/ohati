<?php
// reset_password.php — Secure Password Reset Landing Page for Ohati
require_once __DIR__ . '/db.php';
session_start();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$message = '';
$message_type = '';
$is_valid_token = false;
$user_data = null;

if (!empty($token)) {
    try {
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE reset_token = ? AND reset_expires > ?");
        $stmt->execute([$token, $now]);
        $user_data = $stmt->fetch();

        if ($user_data) {
            $is_valid_token = true;
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ?");
            $checkStmt->execute([$token]);
            if ($checkStmt->fetch()) {
                $message = "This password reset link has expired. Please ask the administrator to resend a new reset link.";
            } else {
                $message = "Invalid password reset link or token.";
            }
            $message_type = "error";
        }
    } catch (Exception $e) {
        $message = "Database connection error. Please try again later.";
        $message_type = "error";
    }
} else {
    $message = "No security reset token provided in URL.";
    $message_type = "error";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid_token && isset($_POST['password'])) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match. Please re-enter.";
        $message_type = "error";
    } else {
        $new_hash = password_hash($password, PASSWORD_BCRYPT);
        $uid = intval($user_data['id']);

        $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = '', reset_expires = NULL WHERE id = ?");
        $update_stmt->execute([$new_hash, $uid]);

        $message = "Your password has been successfully reset! You can now log into your Ohati account with your new password.";
        $message_type = "success";
        $is_valid_token = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Account Password — Ohati</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #111827;
            --accent: #E05A47;
            --bg: #F3F4F6;
            --card: #FFFFFF;
            --text: #1F2937;
            --gray: #6B7280;
            --border: #E5E7EB;
            --success: #10B981;
            --error: #EF4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .reset-box {
            background: var(--card);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            border: 1px solid var(--border);
            max-width: 440px;
            width: 100%;
            padding: 36px 28px;
            text-align: center;
        }
        .brand-header { margin-bottom: 24px; }
        .brand-logo { width: 54px; height: 54px; border-radius: 12px; object-fit: cover; margin-bottom: 10px; }
        .brand-title { font-size: 1.4rem; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; }
        .brand-subtitle { font-size: 0.85rem; color: var(--gray); margin-top: 4px; }

        .alert-box {
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 20px;
            text-align: left;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-error { background: #FEE2E2; border: 1px solid #FCA5A5; color: #991B1B; }
        .alert-success { background: #D1FAE5; border: 1px solid #6EE7B7; color: #065F46; }

        .form-group { margin-bottom: 18px; text-align: left; position: relative; }
        .form-label { font-size: 0.8rem; font-weight: 700; color: var(--text); margin-bottom: 6px; display: block; }
        .input-wrapper { position: relative; }
        .input-field {
            width: 100%;
            padding: 12px 42px 12px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .input-field:focus { border-color: var(--accent); }
        .toggle-pw {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            margin-top: 6px;
        }
        .btn-submit:hover { background: #d04936; }

        .footer-note { font-size: 0.78rem; color: var(--gray); margin-top: 24px; border-top: 1px solid var(--border); padding-top: 16px; }
    </style>
</head>
<body>

    <div class="reset-box">
        <div class="brand-header">
            <img src="img/logo black transparent small.png" alt="Ohati Logo" class="brand-logo" onerror="this.src='../img/logo black transparent small.png'">
            <h1 class="brand-title">Reset Account Password</h1>
            <p class="brand-subtitle">Set a new secure password for your Ohati account</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert-box alert-<?= $message_type ?>">
                <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" style="font-size:1.1rem; margin-top:2px;"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($is_valid_token && $user_data): ?>
            <div style="background:#F9FAFB; border:1px solid #E5E7EB; padding:12px; border-radius:10px; font-size:0.82rem; margin-bottom:20px; text-align:left;">
                Account: <strong><?= htmlspecialchars($user_data['name']) ?></strong> (<?= htmlspecialchars($user_data['email'] ?: $user_data['phone']) ?>)
            </div>

            <form method="POST" action="">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="pw" name="password" required minlength="6" placeholder="At least 6 characters" class="input-field">
                        <i class="fa-solid fa-eye toggle-pw" onclick="toggleVisibility('pw', this)"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="pw_confirm" name="confirm_password" required minlength="6" placeholder="Re-enter new password" class="input-field">
                        <i class="fa-solid fa-eye toggle-pw" onclick="toggleVisibility('pw_confirm', this)"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-shield-check"></i> Update Password Now
                </button>
            </form>
        <?php elseif ($message_type === 'success'): ?>
            <a href="login.php" class="btn-submit" style="background:var(--primary);">
                <i class="fa-solid fa-right-to-bracket"></i> Proceed to Login
            </a>
        <?php else: ?>
            <a href="index.php" class="btn-submit" style="background:var(--primary);">
                <i class="fa-solid fa-arrow-left"></i> Return to Homepage
            </a>
        <?php endif; ?>

        <div class="footer-note">
            Protected by Ohati Security Controls &bull; Need help? Contact <a href="mailto:ohatiwebsite@gmail.com" style="color:var(--accent); text-decoration:none; font-weight:700;">Support</a>
        </div>
    </div>

    <script>
        function toggleVisibility(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
