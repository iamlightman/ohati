<?php
// admin/login.php - Standalone Administrator Login Page
require_once __DIR__ . '/../db.php';
session_start();

// If already logged in as admin, redirect to admin index
if (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = htmlspecialchars(trim($_POST['identifier'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both your email/phone and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['role'] === 'admin') {
                    // Authenticate in separate admin session
                    $_SESSION['admin_user'] = [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'phone' => $user['phone'],
                        'role' => $user['role'],
                        'avatar' => $user['avatar']
                    ];
                    
                    // Set CSRF token if not set
                    if (empty($_SESSION['csrf'])) {
                        $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    }
                    
                    // Log admin login history
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $device = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                    $pdo->prepare("INSERT INTO login_history (user_id, ip_address, device, status) VALUES (?, ?, ?, 'success')")
                        ->execute([$user['id'], $ip, $device]);

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Access denied. You do not have administrator permissions.';
                }
            } else {
                $error = 'Incorrect email/phone or password.';
            }
        } catch (Exception $e) {
            $error = 'System error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin Portal — Sign In</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: radial-gradient(circle at center, #1E2E4F 0%, #0F172A 100%);
            color: #FFFFFF;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 40px 30px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }
        .login-logo {
            width: 60px;
            display: block;
            margin: 0 auto 20px auto;
        }
        h1 {
            font-family: 'Fraunces', serif;
            font-size: 1.6rem;
            margin: 0 0 8px 0;
            color: #FFFFFF;
            font-weight: 700;
            text-align: center;
        }
        .subtitle {
            color: #94A3B8;
            font-size: 0.85rem;
            margin: 0 0 30px 0;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #CBD5E1;
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 12px 16px;
            color: #FFFFFF;
            font-size: 0.9rem;
            outline: none;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }
        .form-input:focus {
            border-color: var(--accent, #D4AF37);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }
        .input-group {
            position: relative;
        }
        .input-suffix {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            cursor: pointer;
            transition: color 0.2s;
        }
        .input-suffix:hover {
            color: #FFFFFF;
        }
        .error-msg {
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #FDA4AF;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-submit {
            display: block;
            width: 100%;
            background: var(--accent, #D4AF37);
            color: #0F172A;
            border: none;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 14px 20px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);
            text-align: center;
            box-sizing: border-box;
            margin-top: 10px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(212, 175, 55, 0.4);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #94A3B8;
            text-decoration: none;
            margin-top: 24px;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: var(--accent, #D4AF37);
        }
        .back-container {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <img src="../img/logo white transparent small.png" class="login-logo" alt="Ohati Logo">
        <h1>Admin Control Console</h1>
        <p class="subtitle">Secure login for Ohati system administrators</p>

        <?php if (!empty($error)): ?>
            <div class="error-msg">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label">Email or Phone Number</label>
                <input type="text" name="identifier" class="form-input" placeholder="admin@ohati.com" required value="<?= isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : '' ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="login-pass" class="form-input" placeholder="••••••••" required>
                    <span class="input-suffix" onclick="togglePassword()">
                        <i class="fa-solid fa-eye" id="pass-eye"></i>
                    </span>
                </div>
            </div>

            <button type="submit" class="btn-submit">Sign In to Dashboard</button>
        </form>

        <div class="back-container">
            <a href="../index.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Marketplace
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('login-pass');
            const eye = document.getElementById('pass-eye');
            if (input && eye) {
                if (input.type === 'password') {
                    input.type = 'text';
                    eye.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    eye.classList.replace('fa-eye-slash', 'fa-eye');
                }
            }
        }
    </script>
</body>
</html>
