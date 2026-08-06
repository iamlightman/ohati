<?php
// admin/restore_database.php — One-Click Database Sync & History Restoration Tool
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_guard.php';

$page_title = "Data History Restoration Tool";
$status_messages = [];
$error_messages = [];
$restored_users = 0;
$restored_bookings = 0;
$restored_kyc = 0;
$restored_reviews = 0;
$restored_messages = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle File Upload if user uploads ohati.db or SQLite file
    if (isset($_FILES['db_file']) && $_FILES['db_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['db_file']['tmp_name'];
        $target_db = __DIR__ . '/../ohati.db';
        if (move_uploaded_file($tmp_name, $target_db)) {
            $status_messages[] = "Uploaded database file successfully. Scanning for history to restore...";
        } else {
            $error_messages[] = "Failed to save uploaded database file.";
        }
    }

    if ($action === 'run_restore' || isset($_FILES['db_file'])) {
        $sqlite_path = __DIR__ . '/../ohati.db';

        $is_valid_sqlite = false;
        if (file_exists($sqlite_path) && filesize($sqlite_path) > 512) {
            $header = @file_get_contents($sqlite_path, false, null, 0, 16);
            if ($header && strpos($header, 'SQLite format 3') === 0) {
                $is_valid_sqlite = true;
            }
        }

        if ($is_valid_sqlite) {
            try {
                $sqlite_pdo = new PDO("sqlite:$sqlite_path", null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);

                // Restore Users & KYC
                try {
                    $sq_users = $sqlite_pdo->query("SELECT * FROM users")->fetchAll();
                    foreach ($sq_users as $u) {
                        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR (phone = ? AND phone != '')");
                        $check->execute([$u['email'] ?? '', $u['phone'] ?? '']);
                        if (!$check->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, email_verified, phone_verified, kyc_status, avatar, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $u['name'] ?? 'Restored User',
                                $u['email'] ?? '',
                                $u['phone'] ?? '',
                                $u['password_hash'] ?? password_hash('Pass123!', PASSWORD_DEFAULT),
                                $u['role'] ?? 'customer',
                                $u['email_verified'] ?? 1,
                                $u['phone_verified'] ?? 1,
                                $u['kyc_status'] ?? 'none',
                                $u['avatar'] ?? '',
                                $u['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $restored_users++;
                            if (($u['kyc_status'] ?? 'none') !== 'none') $restored_kyc++;
                        }
                    }
                    $status_messages[] = "Restored $restored_users missing user account(s) and $restored_kyc KYC record(s).";
                } catch (Exception $e) {
                    $error_messages[] = "Users Restoration Notice: " . $e->getMessage();
                }

                // Restore Bookings
                try {
                    $sq_bookings = $sqlite_pdo->query("SELECT * FROM bookings")->fetchAll();
                    foreach ($sq_bookings as $b) {
                        $check = $pdo->prepare("SELECT id FROM bookings WHERE vendor_id = ? AND user_name = ? AND event_date = ?");
                        $check->execute([$b['vendor_id'] ?? 0, $b['user_name'] ?? '', $b['event_date'] ?? '']);
                        if (!$check->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO bookings (vendor_id, user_id, user_name, user_phone, event_date, event_type, package_name, price, status, payment_status, timeline, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $b['vendor_id'] ?? 1,
                                $b['user_id'] ?? 1,
                                $b['user_name'] ?? 'Client',
                                $b['user_phone'] ?? '',
                                $b['event_date'] ?? date('Y-m-d'),
                                $b['event_type'] ?? 'Event',
                                $b['package_name'] ?? 'Standard Package',
                                $b['price'] ?? 0,
                                $b['status'] ?? 'Inquiry',
                                $b['payment_status'] ?? 'Unpaid',
                                $b['timeline'] ?? json_encode([]),
                                $b['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $restored_bookings++;
                        }
                    }
                    $status_messages[] = "Restored $restored_bookings missing booking timeline record(s).";
                } catch (Exception $e) {}

                // Restore Reviews
                try {
                    $sq_reviews = $sqlite_pdo->query("SELECT * FROM reviews")->fetchAll();
                    foreach ($sq_reviews as $r) {
                        $check = $pdo->prepare("SELECT id FROM reviews WHERE vendor_id = ? AND user_name = ? AND comment = ?");
                        $check->execute([$r['vendor_id'] ?? 0, $r['user_name'] ?? '', $r['comment'] ?? '']);
                        if (!$check->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO reviews (vendor_id, user_id, user_name, user_avatar, rating, comment, photos, verified_booking, date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $r['vendor_id'] ?? 1,
                                $r['user_id'] ?? 1,
                                $r['user_name'] ?? 'Reviewer',
                                $r['user_avatar'] ?? '',
                                $r['rating'] ?? 5,
                                $r['comment'] ?? '',
                                $r['photos'] ?? '',
                                $r['verified_booking'] ?? 1,
                                $r['date'] ?? date('F d, Y'),
                                $r['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $restored_reviews++;
                        }
                    }
                    $status_messages[] = "Restored $restored_reviews customer review(s).";
                } catch (Exception $e) {}

                // Restore Messages
                try {
                    $sq_msgs = $sqlite_pdo->query("SELECT * FROM messages")->fetchAll();
                    foreach ($sq_msgs as $m) {
                        $check = $pdo->prepare("SELECT id FROM messages WHERE vendor_id = ? AND user_id = ? AND message = ? AND created_at = ?");
                        $check->execute([$m['vendor_id'] ?? 0, $m['user_id'] ?? 0, $m['message'] ?? '', $m['created_at'] ?? '']);
                        if (!$check->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO messages (vendor_id, user_id, sender, message, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $m['vendor_id'] ?? 1,
                                $m['user_id'] ?? 1,
                                $m['sender'] ?? 'user',
                                $m['message'] ?? '',
                                $m['type'] ?? 'text',
                                $m['is_read'] ?? 0,
                                $m['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $restored_messages++;
                        }
                    }
                    $status_messages[] = "Restored $restored_messages chat message(s).";
                } catch (Exception $e) {}

            } catch (Exception $e) {
                $error_messages[] = "Database Source Notice: " . $e->getMessage();
            }
        } else {
            $error_messages[] = "No local ohati.db file found on server yet. Use the file uploader below to upload your ohati.db file if you have it saved on your PC.";
        }

        // Recalculate Vendor Ratings
        try {
            $vendors = $pdo->query("SELECT id FROM vendors")->fetchAll();
            foreach ($vendors as $v) {
                $vid = $v['id'];
                $avg = $pdo->query("SELECT AVG(rating) as ar, COUNT(*) as rc FROM reviews WHERE vendor_id = $vid")->fetch();
                if ($avg && $avg['rc'] > 0) {
                    $pdo->prepare("UPDATE vendors SET rating = ?, reviews_count = ? WHERE id = ?")->execute([round($avg['ar'], 1), $avg['rc'], $vid]);
                }
            }
        } catch (Exception $e) {}
    }
}

// Current live database counts
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status != 'none'")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_reviews = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$total_messages = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Ohati Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#1B2B4B; --accent:#F2A735; --bg:#F8FAFC; --card:#FFFFFF; --text:#1E293B; --gray:#64748B; --border:#E2E8F0; --success:#10B981; --error:#EF4444; }
        * { box-sizing:border-box; margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        body { background:var(--bg); color:var(--text); padding:24px; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .title { font-size:1.5rem; font-weight:800; color:var(--primary); }
        .subtitle { font-size:0.85rem; color:var(--gray); }
        .card { background:var(--card); border-radius:16px; padding:24px; border:1px solid var(--border); box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:20px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-bottom:24px; }
        .stat-box { background:var(--bg); border:1px solid var(--border); padding:16px; border-radius:12px; text-align:center; }
        .stat-val { font-size:1.6rem; font-weight:800; color:var(--primary); }
        .stat-lbl { font-size:0.75rem; color:var(--gray); text-transform:uppercase; font-weight:700; }
        .btn { padding:12px 24px; background:var(--primary); color:#fff; border:none; border-radius:10px; font-weight:700; font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .btn-accent { background:var(--accent); color:var(--primary); }
        .msg-box { background:#D1FAE5; border:1px solid #A7F3D0; color:#065F46; padding:12px 16px; border-radius:10px; margin-bottom:12px; font-size:0.85rem; font-weight:600; }
        .err-box { background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:12px 16px; border-radius:10px; margin-bottom:12px; font-size:0.85rem; font-weight:600; }
        .input-file { padding:10px; border:1px dashed var(--border); border-radius:10px; background:var(--bg); width:100%; font-size:0.85rem; margin-bottom:16px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1 class="title"><i class="fa-solid fa-rotate-left"></i> Data History Restoration Tool</h1>
            <p class="subtitle">One-click utility to restore and sync users, KYC records, bookings, and reviews into Admin Console</p>
        </div>
        <a href="index.php" class="btn"><i class="fa-solid fa-arrow-left"></i> Admin Dashboard</a>
    </div>

    <div class="grid">
        <div class="stat-box">
            <div class="stat-val"><?= $total_users ?></div>
            <div class="stat-lbl">Registered Users</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= $total_kyc ?></div>
            <div class="stat-lbl">KYC Records</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= $total_bookings ?></div>
            <div class="stat-lbl">Total Bookings</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= $total_reviews ?></div>
            <div class="stat-lbl">Total Reviews</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= $total_messages ?></div>
            <div class="stat-lbl">Chat Messages</div>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:1.1rem; color:var(--primary); margin-bottom:12px;"><i class="fa-solid fa-database"></i> History Restoration & Sync</h2>
        <p style="font-size:0.85rem; color:var(--gray); margin-bottom:20px; line-height:1.5;">
            Upload your local <strong>ohati.db</strong> file below OR click <strong>Run Sync</strong> to merge missing registered users, KYC verifications, booking timelines, reviews, and chat history into your live MySQL database.
        </p>

        <?php foreach ($status_messages as $msg): ?>
            <div class="msg-box"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>

        <?php foreach ($error_messages as $err): ?>
            <div class="err-box"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="restore_database.php" enctype="multipart/form-data" style="margin-top:16px;" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='<i class=\'fa-solid fa-spinner fa-spin\'></i> Restoring & Syncing History...';">
            <input type="hidden" name="action" value="run_restore">
            
            <div style="margin-bottom:16px;">
                <label style="font-size:0.82rem; font-weight:700; color:var(--primary); display:block; margin-bottom:6px;">
                    Upload ohati.db File from PC (Optional):
                </label>
                <input type="file" name="db_file" accept=".db,.sqlite,.sqlite3" class="input-file">
            </div>

            <button type="submit" class="btn btn-accent"><i class="fa-solid fa-bolt"></i> Run History Restoration & Sync Now</button>
        </form>
    </div>
</body>
</html>
