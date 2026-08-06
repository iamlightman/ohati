<?php
// admin/setup.php — Ohati Database Import & Admin Password Reset Utility
// This single file initializes the database and allows resetting the admin password.
// DELETE THIS FILE FROM PRODUCTION AFTER USE.

$message = '';
$message_type = '';

// Step 1: Import the database
if (isset($_POST['import_db'])) {
    try {
        require_once __DIR__ . '/../db.php';
        $message = '✅ Database imported & initialized successfully. All tables are ready.';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = '❌ Database import failed: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Step 2: Reset admin password
if (isset($_POST['reset_admin'])) {
    $new_password = trim($_POST['new_password'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_name = trim($_POST['admin_name'] ?? 'Ohati Admin');

    if (strlen($new_password) < 8) {
        $message = '❌ Password must be at least 8 characters.';
        $message_type = 'error';
    } elseif (empty($admin_email) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $message = '❌ Please provide a valid admin email.';
        $message_type = 'error';
    } else {
        try {
            require_once __DIR__ . '/../db.php';
            $hash = password_hash($new_password, PASSWORD_BCRYPT);

            // Check if admin exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
            $stmt->execute([$admin_email]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing admin password
                $pdo->prepare("UPDATE users SET password_hash = ?, email_verified = 1, is_active = 1 WHERE id = ?")->execute([$hash, $existing['id']]);
                $message = "✅ Admin password reset successfully for: {$admin_email}";
            } else {
                // Create new admin user
                $pdo->prepare("INSERT INTO users (name, email, password_hash, role, email_verified, is_active) VALUES (?, ?, ?, 'admin', 1, 1)")->execute([$admin_name, $admin_email, $hash]);
                $message = "✅ New admin account created: {$admin_email}";
            }
            $message_type = 'success';
        } catch (Exception $e) {
            $message = '❌ Failed: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Step 3: Create deleted_records table if not exists
if (isset($_POST['import_db']) || isset($_POST['reset_admin'])) {
    try {
        if (!isset($pdo)) require_once __DIR__ . '/../db.php';
        $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_records (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            record_type VARCHAR(50) NOT NULL,
            record_id INT NOT NULL,
            record_data TEXT NOT NULL,
            deleted_by INT DEFAULT 0,
            deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {
        // Silently handle — table might already exist
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati — Database Setup & Admin Reset</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,700;0,9..144,900;1,9..144,400&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0F1923 0%, #1B2B4B 50%, #0F1923 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-container {
            width: 100%;
            max-width: 520px;
        }
        .setup-card {
            background: rgba(27, 43, 75, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 36px 32px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            margin-bottom: 20px;
        }
        .brand {
            font-family: 'Fraunces', serif;
            font-size: 2rem;
            color: #F2A735;
            text-align: center;
            font-weight: 900;
            letter-spacing: 3px;
            margin-bottom: 4px;
        }
        .subtitle {
            text-align: center;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.5);
            margin-bottom: 28px;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: #F2A735;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i { font-size: 1.1rem; }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: rgba(255,255,255,0.75);
            margin-bottom: 6px;
        }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1.5px solid rgba(255,255,255,0.12);
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus {
            border-color: #F2A735;
            box-shadow: 0 0 0 3px rgba(242, 167, 53, 0.12);
        }
        .btn {
            width: 100%;
            padding: 13px;
            font-size: 0.9rem;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s;
        }
        .btn-gold {
            background: linear-gradient(135deg, #F2A735, #e8963a);
            color: #0F1923;
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(242, 167, 53, 0.3);
        }
        .btn-teal {
            background: linear-gradient(135deg, #00B4D8, #0077B6);
            color: #fff;
        }
        .btn-teal:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 180, 216, 0.3);
        }
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        .divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.08);
            margin: 24px 0;
        }
        .warning-text {
            text-align: center;
            font-size: 0.72rem;
            color: rgba(239, 68, 68, 0.7);
            margin-top: 16px;
            line-height: 1.5;
        }
        .back-link {
            display: block;
            text-align: center;
            font-size: 0.85rem;
            color: #F2A735;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="brand">OHATI</div>
            <div class="subtitle">Database Setup & Admin Password Reset</div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Section 1: Import Database -->
            <div class="section-title"><i>🗄️</i> Import / Initialize Database</div>
            <p style="font-size:0.78rem; color:rgba(255,255,255,0.5); margin-bottom:14px; line-height:1.5;">
                This will create all required tables (users, vendors, bookings, payments, etc.) if they don't already exist. Safe to run multiple times.
            </p>
            <form method="POST">
                <button type="submit" name="import_db" class="btn btn-teal">Initialize Database</button>
            </form>

            <hr class="divider">

            <!-- Section 2: Reset Admin Password -->
            <div class="section-title"><i>🔑</i> Reset / Create Admin Account</div>
            <p style="font-size:0.78rem; color:rgba(255,255,255,0.5); margin-bottom:14px; line-height:1.5;">
                Enter admin credentials below. If the email exists as an admin, the password will be reset. Otherwise, a new admin account will be created.
            </p>
            <form method="POST">
                <div class="form-group">
                    <label>Admin Full Name</label>
                    <input type="text" name="admin_name" placeholder="e.g. Ohati Admin" value="Ohati Admin">
                </div>
                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" name="admin_email" placeholder="admin@ohati.com" required>
                </div>
                <div class="form-group">
                    <label>New Password (min 8 chars)</label>
                    <input type="password" name="new_password" placeholder="Choose a strong password" required minlength="8">
                </div>
                <button type="submit" name="reset_admin" class="btn btn-gold">Reset / Create Admin</button>
            </form>

            <div class="warning-text">
                ⚠️ DELETE THIS FILE FROM YOUR SERVER AFTER USE.<br>
                Leaving it accessible is a critical security risk.
            </div>
        </div>

        <a href="index.php" class="back-link">← Go to Admin Dashboard</a>
    </div>
</body>
</html>
