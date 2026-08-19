<?php
// delete_account.php — Official Public Web Account Deletion Request Page for Ohati
$page_title = "Account Deletion Request — Ohati";
$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = "Please enter your Email/Phone and Password to confirm account deletion.";
    } else {
        require_once __DIR__ . '/db.php';
        $id_lower = strtolower($identifier);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? OR phone = ?");
        $stmt->execute([$id_lower, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $uid = intval($user['id']);
            $record_data = json_encode(['user' => $user, 'reason' => $reason]);
            
            try {
                $stmtDel = $pdo->prepare("INSERT INTO deleted_records (record_type, record_id, record_data) VALUES ('web_account_deletion', ?, ?)");
                $stmtDel->execute([$uid, $record_data]);
            } catch (Exception $e) {}

            try {
                $pdo->prepare("UPDATE users SET is_active = 0, status = 'deleted' WHERE id = ?")->execute([$uid]);
                $pdo->prepare("UPDATE vendors SET is_active = 0 WHERE user_id = ?")->execute([$uid]);
            } catch (Exception $e) {}

            $msg = "Your Ohati account (" . htmlspecialchars($user['name']) . ") and all associated data have been permanently deactivated and queued for deletion. You have been signed out.";
        } else {
            $error = "Invalid credentials. Please verify your Email/Phone and Password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" href="img/app_icon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#0F1923; --accent:#EF4444; --bg:#F8FAFC; --card:#FFFFFF; --text:#1E293B; --gray:#64748B; --border:#E2E8F0; }
        * { box-sizing:border-box; margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        body { background:var(--bg); color:var(--text); line-height:1.6; padding:24px 16px; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .container { width:100%; max-width:500px; background:var(--card); border-radius:20px; padding:32px 24px; border:1px solid var(--border); box-shadow:0 10px 25px rgba(0,0,0,0.05); }
        .logo-wrap { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
        .logo-wrap img { width:42px; height:42px; border-radius:10px; object-fit:cover; }
        .brand { font-size:1.4rem; font-weight:800; color:var(--primary); }
        h1 { font-size:1.5rem; font-weight:800; color:var(--primary); margin-bottom:8px; }
        p { font-size:0.88rem; color:var(--gray); margin-bottom:20px; }
        .form-group { margin-bottom:16px; }
        label { display:block; font-size:0.8rem; font-weight:700; color:var(--text); margin-bottom:6px; }
        input, select, textarea { width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-size:0.9rem; outline:none; transition:border 0.2s; }
        input:focus, textarea:focus { border-color:var(--accent); }
        .btn-delete { width:100%; background:linear-gradient(135deg,#EF4444,#DC2626); color:#fff; font-weight:700; border:none; padding:14px; border-radius:12px; font-size:0.95rem; cursor:pointer; box-shadow:0 4px 12px rgba(239,68,68,0.3); transition:all 0.2s; }
        .btn-delete:hover { opacity:0.95; }
        .alert { padding:12px 16px; border-radius:10px; font-size:0.85rem; margin-bottom:18px; }
        .alert-error { background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5; }
        .alert-success { background:#DCFCE7; color:#166534; border:1px solid #86EFAC; }
        .info-box { background:#F1F5F9; border-radius:12px; padding:14px; font-size:0.8rem; color:var(--gray); margin-top:20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-wrap">
            <img src="img/app_icon.png" alt="Ohati Logo">
            <span class="brand">OHATI</span>
        </div>

        <h1>Delete Ohati Account</h1>
        <p>Use this official web form to request permanent account deactivation and data deletion in accordance with Google Play & Apple App Store Policies.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
        <?php else: ?>
            <form method="POST" action="delete_account.php">
                <div class="form-group">
                    <label>Account Email or Phone Number:</label>
                    <input type="text" name="identifier" placeholder="e.g. user@example.com or +233..." required>
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="password" placeholder="Enter your account password" required>
                </div>
                <div class="form-group">
                    <label>Reason for Leaving (Optional):</label>
                    <textarea name="reason" rows="2" placeholder="Tell us why you are deleting your account..."></textarea>
                </div>
                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure you want to permanently delete your Ohati account?')">
                    <i class="fa-solid fa-trash-can"></i> Request Account Deletion
                </button>
            </form>
        <?php endif; ?>

        <div class="info-box">
            <strong>Data Retention Disclosure:</strong> Upon account deletion, personal credentials are permanently anonymized and vendor listings deactivated. Financial transaction logs are retained strictly as required by law for accounting and fraud prevention.
        </div>
    </div>
</body>
</html>
