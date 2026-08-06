<?php
// admin/generator_tool.php — Hidden Account & Custom KYC Generator Utility
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_guard.php';

$page_title = "Account & Custom KYC Generator";
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_account') {
    $role = $_POST['role'] ?? 'vendor'; // vendor, customer
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass = $_POST['password'] ?? 'OhatiUser2026!';
    $category = trim($_POST['category'] ?? 'Photography');
    $location = trim($_POST['location'] ?? 'Accra, Ghana');
    $id_type = trim($_POST['id_type'] ?? 'Ghana Card');
    $kyc_status = $_POST['kyc_status'] ?? 'pending_verification';
    $created_at = trim($_POST['created_at'] ?? date('Y-m-d H:i:s'));
    $kyc_submitted_at = trim($_POST['kyc_submitted_at'] ?? date('Y-m-d H:i:s'));

    if (empty($name) || (empty($email) && empty($phone))) {
        $error_msg = "Please provide Name and either Email or Phone Number.";
    } else {
        // Prevent duplicate user creation
        $check_dup = $pdo->prepare("SELECT id FROM users WHERE (email = ? AND email != '') OR (phone = ? AND phone != '')");
        $check_dup->execute([$email ?: '___NOEMAIL___', $phone ?: '___NOPHONE___']);
        $dup_user_id = $check_dup->fetchColumn();

        if ($dup_user_id) {
            $error_msg = "An account with this email or phone number already exists (User #{$dup_user_id}). Duplicate submission blocked.";
        } else {
            try {
                $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
            $uploads_dir = __DIR__ . '/../uploads/kyc';
            if (!file_exists($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
            }

            $id_front_path = '';
            $id_back_path = '';
            $selfie_path = '';

            // Process ID Front Upload
            if (isset($_FILES['id_front']) && $_FILES['id_front']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['id_front']['name'], PATHINFO_EXTENSION);
                $fname = 'id_front_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['id_front']['tmp_name'], $uploads_dir . '/' . $fname)) {
                    $id_front_path = 'uploads/kyc/' . $fname;
                }
            }

            // Process ID Back Upload
            if (isset($_FILES['id_back']) && $_FILES['id_back']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['id_back']['name'], PATHINFO_EXTENSION);
                $fname = 'id_back_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['id_back']['tmp_name'], $uploads_dir . '/' . $fname)) {
                    $id_back_path = 'uploads/kyc/' . $fname;
                }
            }

            // Process Selfie Upload
            if (isset($_FILES['selfie']) && $_FILES['selfie']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['selfie']['name'], PATHINFO_EXTENSION);
                $fname = 'selfie_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['selfie']['tmp_name'], $uploads_dir . '/' . $fname)) {
                    $selfie_path = 'uploads/kyc/' . $fname;
                }
            }

            // 1. Insert User Record with Custom Timestamps
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, email_verified, phone_verified, kyc_status, kyc_id_type, kyc_id_front, kyc_id_back, kyc_selfie, kyc_submitted_at, created_at) VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name, $email, $phone, $pass_hash, $role,
                $kyc_status, $id_type, $id_front_path, $id_back_path, $selfie_path,
                $kyc_submitted_at, $created_at
            ]);
            $user_id = $pdo->lastInsertId();

            // 2. If Vendor, Insert Vendor Profile with Custom Timestamps
            if ($role === 'vendor') {
                $v_status = ($kyc_status === 'verified') ? 'verified' : 'pending';
                $v_badge = ($kyc_status === 'verified') ? 'blue' : 'grey';
                $is_ver = ($kyc_status === 'verified') ? 1 : 0;

                $stmt_v = $pdo->prepare("INSERT INTO vendors (user_id, name, category, location, phone, email, verified, verification_status, verification_badge, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_v->execute([
                    $user_id, $name, $category, $location, $phone, $email,
                    $is_ver, $v_status, $v_badge, $created_at
                ]);
                $vendor_id = $pdo->lastInsertId();
                $success_msg = "Successfully generated Vendor #{$vendor_id} (User #{$user_id}) with custom timestamp ({$created_at}) and KYC submission ({$kyc_submitted_at}).";
            } else {
                $success_msg = "Successfully generated Customer User #{$user_id} with custom timestamp ({$created_at}) and KYC submission ({$kyc_submitted_at}).";
            }

        } catch (Exception $e) {
            $error_msg = "Error generating account: " . $e->getMessage();
        }
    }
    }
}

// Fetch categories for dropdown safely
$categories = [];
try {
    $categories = $pdo->query("SELECT name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Exception $e) {}

if (empty($categories)) {
    $categories = [
        'Photography', 'Videography', 'Makeup Artists', 'Bridal Shops', 'Event Planners', 'Decorators', 'Caterers', 'Cake Designers', 'Event Venues', 'DJs', 'MCs', 'Live Bands', 'Florists', 'Car Rentals', 'Security Services', 'Chilling Services', 'Rental Equipment', 'Cocktail Bars', 'Honeymoon Packages', 'Invitation Designers', 'Jewelers', 'Lighting', 'Printing Services', 'Ushers', 'Content Creators', 'Juice Bar'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Ohati Hidden Utility</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#1B2B4B; --accent:#F2A735; --bg:#F8FAFC; --card:#FFFFFF; --text:#1E293B; --gray:#64748B; --border:#E2E8F0; --success:#10B981; --error:#EF4444; }
        * { box-sizing:border-box; margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        body { background:var(--bg); color:var(--text); padding:24px; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
        .title { font-size:1.4rem; font-weight:800; color:var(--primary); }
        .subtitle { font-size:0.83rem; color:var(--gray); }
        .card { background:var(--card); border-radius:16px; padding:24px; border:1px solid var(--border); box-shadow:0 1px 3px rgba(0,0,0,0.05); max-width:800px; margin:0 auto; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-group { margin-bottom:16px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size:0.8rem; font-weight:700; color:var(--primary); display:block; margin-bottom:6px; }
        input, select, textarea { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-size:0.85rem; outline:none; }
        input:focus, select:focus { border-color:var(--primary); }
        .btn { padding:12px 24px; background:var(--primary); color:#fff; border:none; border-radius:10px; font-weight:700; font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .btn-accent { background:var(--accent); color:var(--primary); }
        .msg-box { background:#D1FAE5; border:1px solid #A7F3D0; color:#065F46; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:0.85rem; font-weight:600; }
        .err-box { background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:0.85rem; font-weight:600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div>
                <h1 class="title"><i class="fa-solid fa-user-plus"></i> Account & Custom KYC Generator</h1>
                <p class="subtitle">Generate realistic vendor or user profiles with custom past/real dates & uploaded KYC documents</p>
            </div>
            <a href="index.php" class="btn" style="padding:8px 14px; font-size:0.8rem;"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="msg-box"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="err-box"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="generator_tool.php" enctype="multipart/form-data" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='<i class=\'fa-solid fa-spinner fa-spin\'></i> Generating Profile...';">
            <input type="hidden" name="action" value="generate_account">

            <div class="form-grid">
                <div class="form-group">
                    <label>Account Role</label>
                    <select name="role" id="role-select" onchange="toggleVendorFields()">
                        <option value="vendor" selected>Vendor Account</option>
                        <option value="customer">Customer Account</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>KYC Status</label>
                    <select name="kyc_status">
                        <option value="pending_verification" selected>Pending Verification (Queue)</option>
                        <option value="verified">Verified (Blue Badge)</option>
                        <option value="not_started">Not Started</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Full Name / Business Name</label>
                    <input type="text" name="name" required placeholder="e.g. Kwame Studios or Ama Serwaa">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="user@domain.com">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" placeholder="024XXXXXXX">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" value="OhatiUser2026!">
                </div>

                <div class="form-group vendor-field">
                    <label>Category (Vendors Only)</label>
                    <select name="category">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group vendor-field">
                    <label>Location (Vendors Only)</label>
                    <input type="text" name="location" value="Accra, Ghana">
                </div>

                <div class="form-group">
                    <label>KYC ID Document Type</label>
                    <select name="id_type">
                        <option value="Ghana Card" selected>Ghana Card</option>
                        <option value="Passport">Passport</option>
                        <option value="Drivers License">Driver's License</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>KYC ID Front Photo Upload</label>
                    <input type="file" name="id_front" accept="image/*">
                </div>

                <div class="form-group">
                    <label>KYC ID Back Photo Upload</label>
                    <input type="file" name="id_back" accept="image/*">
                </div>

                <div class="form-group">
                    <label>KYC Verification Selfie Upload</label>
                    <input type="file" name="selfie" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Account Creation Timestamp (created_at)</label>
                    <input type="text" name="created_at" value="<?= date('Y-m-d H:i:s') ?>" placeholder="YYYY-MM-DD HH:MM:SS">
                </div>

                <div class="form-group">
                    <label>KYC Submission Timestamp (kyc_submitted_at)</label>
                    <input type="text" name="kyc_submitted_at" value="<?= date('Y-m-d H:i:s') ?>" placeholder="YYYY-MM-DD HH:MM:SS">
                </div>
            </div>

            <div style="margin-top:20px; text-align:right;">
                <button type="submit" class="btn btn-accent"><i class="fa-solid fa-user-check"></i> Generate Profile & KYC Record</button>
            </div>
        </form>
    </div>

    <script>
        function toggleVendorFields() {
            const role = document.getElementById('role-select').value;
            document.querySelectorAll('.vendor-field').forEach(el => {
                el.style.display = (role === 'vendor') ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>
