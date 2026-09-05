<?php
// admin/settings.php - Ohati Admin Panel Settings
require_once __DIR__ . '/../db.php';
session_start();

require_once __DIR__ . '/auth_guard.php';

// Database-agnostic setting helpers
function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = ?")->execute([$value, $key]);
        } else {
            $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES (?, ?)")->execute([$key, $value]);
        }
    } catch (Exception $e) {}
}

$success_msg = '';
$error_msg = '';

// Handle Settings Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_general') {
        // Save General System Settings
        setSetting('site_name', trim($_POST['site_name'] ?? 'Ohati'));
        setSetting('site_email', trim($_POST['site_email'] ?? ''));
        setSetting('site_phone', trim($_POST['site_phone'] ?? ''));
        setSetting('chat_support_number', trim($_POST['chat_support_number'] ?? '+233209001100'));
        setSetting('site_address', trim($_POST['site_address'] ?? ''));
        setSetting('android_download_url', trim($_POST['android_download_url'] ?? 'https://play.google.com/store/apps/details?id=com.ohati.app'));
        setSetting('ios_download_url', trim($_POST['ios_download_url'] ?? 'https://apps.apple.com/app/ohati/id123456789'));
        setSetting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');

        setSetting('bank_1_name', trim($_POST['bank_1_name'] ?? ''));
        setSetting('bank_1_acc_num', trim($_POST['bank_1_acc_num'] ?? ''));
        setSetting('bank_1_acc_name', trim($_POST['bank_1_acc_name'] ?? ''));

        setSetting('bank_2_name', trim($_POST['bank_2_name'] ?? ''));
        setSetting('bank_2_acc_num', trim($_POST['bank_2_acc_num'] ?? ''));
        setSetting('bank_2_acc_name', trim($_POST['bank_2_acc_name'] ?? ''));

        setSetting('bank_3_name', trim($_POST['bank_3_name'] ?? ''));
        setSetting('bank_3_acc_num', trim($_POST['bank_3_acc_num'] ?? ''));
        setSetting('bank_3_acc_name', trim($_POST['bank_3_acc_name'] ?? ''));

        $success_msg = 'System configurations updated successfully.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save_smtp') {
        setSetting('smtp_host', trim($_POST['smtp_host'] ?? 'stardust.globaldnsnetwork.com'));
        setSetting('smtp_port', trim($_POST['smtp_port'] ?? '587'));
        setSetting('smtp_user', trim($_POST['smtp_user'] ?? 'contact@ohati.com'));
        setSetting('smtp_pass', trim($_POST['smtp_pass'] ?? ''));
        $success_msg = 'SMTP Mailer server configurations saved successfully.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        // Change Admin Password
        $current = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new_pass) < 8) {
            $error_msg = 'New password must be at least 8 characters long.';
        } elseif ($new_pass !== $confirm) {
            $error_msg = 'New password and confirmation do not match.';
        } else {
            // Find current logged-in admin user
            $admin_id = $_SESSION['admin_user']['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
            $stmt->execute([$admin_id]);
            $admin_user = $stmt->fetch();
            
            if (!$admin_user || !password_verify($current, $admin_user['password_hash'])) {
                $error_msg = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $admin_id]);
                $success_msg = 'Administrative password changed successfully.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'review_profile_change') {
        $req_id = intval($_POST['request_id'] ?? 0);
        $status = $_POST['status'] ?? 'rejected';
        $notes = trim($_POST['admin_notes'] ?? '');
        
        $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE id = ?");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch();
        if ($req) {
            $pdo->prepare("UPDATE profile_change_requests SET status = ?, admin_notes = ? WHERE id = ?")->execute([$status, $notes, $req_id]);
            if ($status === 'approved') {
                $field = $req['field_name'];
                $user_fields = ['name', 'email', 'phone', 'dob'];
                $vendor_fields = ['name', 'category', 'description', 'location', 'phone', 'email', 'experience', 'whatsapp', 'website', 'bank_name', 'account_name', 'account_number', 'momo_number', 'momo_provider', 'payout_method'];

                if (in_array($field, $user_fields)) {
                    $pdo->prepare("UPDATE users SET $field = ? WHERE id = ?")->execute([$req['new_value'], $req['user_id']]);
                }
                if (in_array($field, $vendor_fields)) {
                    $pdo->prepare("UPDATE vendors SET $field = ? WHERE user_id = ?")->execute([$req['new_value'], $req['user_id']]);
                }
                // Log activity
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $dev = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $pdo->prepare("INSERT INTO profile_activity_log (user_id, field_name, old_value, new_value, device, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$req['user_id'], $field, $req['old_value'], $req['new_value'], $dev, $ip]);
                
                // Notify
                $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Profile Update Approved', ?, 'user-check')")
                    ->execute([$req['user_id'], "Your request to change '$field' has been approved."]);
            } else {
                $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Profile Update Rejected', ?, 'user-xmark')")
                    ->execute([$req['user_id'], "Your request to change '{$req['field_name']}' was rejected. Reason: $notes"]);
            }
            $success_msg = 'Profile change request reviewed successfully.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save_ads_pricing') {
        setSetting('ad_plan_starter_price', floatval($_POST['ad_plan_starter_price'] ?? 50.0));
        setSetting('ad_plan_starter_reach', trim($_POST['ad_plan_starter_reach'] ?? '1,000+ planners'));

        setSetting('ad_plan_standard_price', floatval($_POST['ad_plan_standard_price'] ?? 300.0));
        setSetting('ad_plan_standard_reach', trim($_POST['ad_plan_standard_reach'] ?? '10,000+ planners'));

        setSetting('ad_plan_premium_price', floatval($_POST['ad_plan_premium_price'] ?? 1100.0));
        setSetting('ad_plan_premium_reach', trim($_POST['ad_plan_premium_reach'] ?? '50,000+ planners'));

        setSetting('ad_plan_platinum_price', floatval($_POST['ad_plan_platinum_price'] ?? 3000.0));
        setSetting('ad_plan_platinum_reach', trim($_POST['ad_plan_platinum_reach'] ?? '200,000+ planners'));

        setSetting('locked_profile_fields', json_encode($_POST['locked_fields'] ?? []));
        $success_msg = 'Advertising package rates, reaches, and profile lock policies updated.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'review_ad') {
        $ad_id = intval($_POST['ad_id'] ?? 0);
        $status = $_POST['status'] ?? ''; // active, paused, expired
        $pdo->prepare("UPDATE advertisements SET status = ? WHERE id = ?")->execute([$status, $ad_id]);
        $success_msg = "Advertisement campaign status updated to '$status'.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save_referral_settings') {
        setSetting('referral_reward_amount', floatval($_POST['referral_reward_amount'] ?? 10.0));
        setSetting('referral_program_active', isset($_POST['referral_program_active']) ? '1' : '0');
        $success_msg = 'Refer & Earn reward rate and program status updated successfully.';
    }
}

// Read current settings values
$site_name = getSetting('site_name', 'Ohati');
$site_email = getSetting('site_email', 'hello@ohati.com');
$site_phone = getSetting('site_phone', '+233 20 900 1100');
$chat_support_number = getSetting('chat_support_number', '+233 20 900 1100');
$site_address = getSetting('site_address', 'Accra, Ghana');
$android_download_url = getSetting('android_download_url', 'https://play.google.com/store/apps/details?id=com.ohati.app');
$ios_download_url = getSetting('ios_download_url', 'https://apps.apple.com/app/ohati/id123456789');
$commission_rate = getSetting('commission_rate', '10.0');
$maintenance_mode = getSetting('maintenance_mode', '0');

$referral_reward_amount = floatval(getSetting('referral_reward_amount', '10.0'));
$referral_program_active = getSetting('referral_program_active', '1');

$bank_1_name = getSetting('bank_1_name', 'Ohati Partner Bank');
$bank_1_acc_num = getSetting('bank_1_acc_num', '1202239401923');
$bank_1_acc_name = getSetting('bank_1_acc_name', 'Ohati Services Ltd.');

$bank_2_name = getSetting('bank_2_name', '');
$bank_2_acc_num = getSetting('bank_2_acc_num', '');
$bank_2_acc_name = getSetting('bank_2_acc_name', '');

$bank_3_name = getSetting('bank_3_name', '');
$bank_3_acc_num = getSetting('bank_3_acc_num', '');
$bank_3_acc_name = getSetting('bank_3_acc_name', '');

$ad_plan_starter_price = floatval(getSetting('ad_plan_starter_price', '50.0'));
$ad_plan_starter_reach = getSetting('ad_plan_starter_reach', '1,000+ planners');

$ad_plan_standard_price = floatval(getSetting('ad_plan_standard_price', '300.0'));
$ad_plan_standard_reach = getSetting('ad_plan_standard_reach', '10,000+ planners');

$ad_plan_premium_price = floatval(getSetting('ad_plan_premium_price', '1100.0'));
$ad_plan_premium_reach = getSetting('ad_plan_premium_reach', '50,000+ planners');

$ad_plan_platinum_price = floatval(getSetting('ad_plan_platinum_price', '3000.0'));
$ad_plan_platinum_reach = getSetting('ad_plan_platinum_reach', '200,000+ planners');
$locked_fields_json = getSetting('locked_profile_fields', '["name","email","phone","dob"]');
$locked_fields = json_decode($locked_fields_json ?: '[]', true) ?: [];

$smtp_host = getSetting('smtp_host', 'stardust.globaldnsnetwork.com');
$smtp_port = getSetting('smtp_port', '587');
$smtp_user = getSetting('smtp_user', 'contact@ohati.com');
$smtp_pass = getSetting('smtp_pass', '');

$pending_change_requests = $pdo->query("SELECT r.*, u.name as user_name FROM profile_change_requests r JOIN users u ON r.user_id = u.id WHERE r.status = 'pending' ORDER BY r.id ASC")->fetchAll();
$all_ads = $pdo->query("SELECT a.*, v.name as vendor_name FROM advertisements a JOIN vendors v ON a.vendor_id = v.id ORDER BY a.id DESC")->fetchAll();

$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - System Settings</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Mobile responsive overrides for admin panel */
        @media(max-width: 900px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                display: flex !important;
                box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .admin-main {
                margin-left: 0 !important;
            }
            .admin-stat-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media(max-width: 600px) {
            .admin-stat-grid {
                grid-template-columns: 1fr !important;
            }
            .admin-topbar {
                padding: 12px 16px !important;
            }
            .admin-content {
                padding: 16px !important;
            }
        }
        .admin-sidebar-logo img {
            height: 36px;
            width: auto;
            object-fit: contain;
            border-radius: 0;
        }
        .admin-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--primary);
            cursor: pointer;
            padding: 8px;
        }
        @media(max-width: 900px) {
            .admin-menu-toggle {
                display: block;
            }
        }
        .admin-sidebar-close {
            display: none;
            background: none;
            border: none;
            color: rgba(255,255,255,0.6);
            font-size: 1.25rem;
            cursor: pointer;
            margin-left: auto;
            padding: 4px;
        }
        @media(max-width: 900px) {
            .admin-sidebar-close {
                display: block;
            }
        }

        .settings-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        @media(max-width: 992px) {
            .settings-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="admin-layout">

    <!-- Admin Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Admin Main Body -->
    <main class="admin-main">
        <!-- Top Bar -->
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
                <h1 class="admin-page-title">System Settings</h1>
            </div>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600); display:flex; align-items:center; gap:16px;">
                <a href="../email_diagnostic.php" class="btn btn-outline btn-xs" style="padding:6px 12px; font-weight:700; color:var(--primary); border-color:var(--primary); text-decoration:none;"><i class="fa-solid fa-stethoscope"></i> Email & OTP Diagnostics</a>
                <a href="../test_otp_delivery.php" class="btn btn-outline btn-xs" style="padding:6px 12px; font-weight:700; color:var(--rose); border-color:var(--rose); text-decoration:none;"><i class="fa-solid fa-bug"></i> Live OTP Socket Trace Debugger</a>
                <div style="display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-circle-user" style="font-size:1.2rem; color:var(--accent);"></i>
                    <span>System Administrator</span>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="admin-content">

            <!-- Success/Error Banners -->
            <?php if (!empty($success_msg)): ?>
                <div class="card mb-20" style="background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); border-radius:12px; padding:14px 20px; color:var(--success); font-weight:600; font-size:0.85rem;">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="card mb-20" style="background:rgba(244,63,94,0.1); border:1px solid rgba(244,63,94,0.3); border-radius:12px; padding:14px 20px; color:var(--rose); font-weight:600; font-size:0.85rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>
            <!-- Refer & Earn Program Settings Card -->
            <div class="card mb-24" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:24px; margin-bottom:24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #F0F2F5; padding-bottom:12px;">
                    <div>
                        <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin:0; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-gift" style="color:var(--accent);"></i> Refer & Earn Program Settings
                        </h3>
                        <p style="margin:4px 0 0 0; font-size:0.8rem; color:var(--gray-500);">Configure reward amounts in GHS credited to referrers when new users or vendors sign up.</p>
                    </div>
                    <span class="badge <?= $referral_program_active === '1' ? 'badge-success' : 'badge-danger' ?>" style="padding:6px 12px; font-size:0.75rem;">
                        <?= $referral_program_active === '1' ? '● Program Active' : '○ Program Disabled' ?>
                    </span>
                </div>

                <form method="POST" action="settings.php">
                    <input type="hidden" name="action" value="save_referral_settings">
                    
                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:20px; margin-bottom:16px;">
                        <div>
                            <label class="form-label" style="font-weight:700;">Reward Rate Per Successful Referral (GH₵)</label>
                            <div style="position:relative;">
                                <span style="position:absolute; left:14px; top:50%; transform:translateY(-50%); font-weight:800; color:var(--accent);">GH₵</span>
                                <input type="number" step="0.5" min="0" class="form-input" name="referral_reward_amount" value="<?= htmlspecialchars($referral_reward_amount) ?>" style="padding-left:55px; font-weight:800; font-size:1.1rem;" required>
                            </div>
                            <div style="font-size:0.75rem; color:var(--gray-500); margin-top:6px;">This reward is instantly credited to the referrer's balance upon new registration.</div>
                        </div>

                        <div>
                            <label class="form-label" style="font-weight:700;">Program Master Control</label>
                            <div style="margin-top:12px; background:var(--gray-50, #F9FAFB); padding:12px 16px; border-radius:10px; border:1px solid #E5E7EB;">
                                <label style="display:inline-flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; font-size:0.9rem; color:var(--primary);">
                                    <input type="checkbox" name="referral_program_active" value="1" <?= $referral_program_active === '1' ? 'checked' : '' ?> style="width:20px; height:20px; accent-color:var(--primary);">
                                    Enable Referral & Earn System Platform-wide
                                </label>
                            </div>
                            <div style="font-size:0.75rem; color:var(--gray-500); margin-top:6px;">Uncheck to temporarily freeze referral link crediting without affecting past earnings.</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding:10px 24px; font-weight:700;">
                        <i class="fa-solid fa-floppy-disk"></i> Update Referral Reward Settings
                    </button>
                </form>
            </div>

            <div class="settings-container">
                
                <!-- General System Settings Column -->
                <div class="card" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:24px;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin-top:0; margin-bottom:20px; border-bottom:1px solid #F0F2F5; padding-bottom:12px;">General Marketplace Configurations</h3>
                    
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="action" value="save_general">
                        
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;">Platform Title / Site Name</label>
                            <input type="text" name="site_name" class="form-input" value="<?= htmlspecialchars($site_name) ?>" required>
                        </div>

                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;">Support Email Address</label>
                            <input type="email" name="site_email" class="form-input" value="<?= htmlspecialchars($site_email) ?>">
                        </div>

                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;">Support Contact Phone Number</label>
                            <input type="text" name="site_phone" class="form-input" value="<?= htmlspecialchars($site_phone) ?>">
                        </div>

                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700; color:var(--primary);"><i class="fa-brands fa-whatsapp" style="color:#25D366;"></i> 24/7 Chat Support Number (WhatsApp Desk)</label>
                            <input type="text" name="chat_support_number" class="form-input" placeholder="e.g. +233209001100" value="<?= htmlspecialchars($chat_support_number) ?>">
                            <div style="font-size:0.75rem; color:var(--gray-500); margin-top:4px;">This number powers the "Still need assistance? Our support desk is online 24/7." 1-click Chat Support button across the app.</div>
                        </div>

                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;">Office/Physical Location Address</label>
                            <input type="text" name="site_address" class="form-input" value="<?= htmlspecialchars($site_address) ?>">
                        </div>

                        <h4 style="font-family:'Fraunces',serif; font-size:1rem; color:var(--primary); margin-top:20px; margin-bottom:12px;">
                            <i class="fa-solid fa-mobile-screen-button" style="color:var(--accent);"></i> Mobile App Live Download Links
                        </h4>
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;"><i class="fa-brands fa-google-play" style="color:#34A853;"></i> Android / Google Play Store URL</label>
                            <input type="url" name="android_download_url" class="form-input" placeholder="https://play.google.com/store/apps/details?id=..." value="<?= htmlspecialchars($android_download_url) ?>">
                        </div>
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;"><i class="fa-brands fa-apple" style="color:#000;"></i> iOS / Apple App Store URL</label>
                            <input type="url" name="ios_download_url" class="form-input" placeholder="https://apps.apple.com/app/ohati/id..." value="<?= htmlspecialchars($ios_download_url) ?>">
                        </div>



                        <h4 style="font-family:'Fraunces',serif; font-size:1rem; color:var(--primary); margin-top:20px; margin-bottom:12px;">Bank Transfer Accounts (Up to 3)</h4>
                        
                        <div style="background:var(--gray-50); padding:12px; border-radius:8px; margin-bottom:16px;">
                            <div style="font-size:0.8rem; font-weight:700; margin-bottom:8px; color:var(--primary);">Bank Account 1 (Primary)</div>
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                                <input type="text" name="bank_1_name" class="form-input" placeholder="Bank Name" value="<?= htmlspecialchars($bank_1_name) ?>">
                                <input type="text" name="bank_1_acc_num" class="form-input" placeholder="Account Number" value="<?= htmlspecialchars($bank_1_acc_num) ?>">
                                <input type="text" name="bank_1_acc_name" class="form-input" placeholder="Account Name" value="<?= htmlspecialchars($bank_1_acc_name) ?>">
                            </div>
                        </div>

                        <div style="background:var(--gray-50); padding:12px; border-radius:8px; margin-bottom:16px;">
                            <div style="font-size:0.8rem; font-weight:700; margin-bottom:8px; color:var(--primary);">Bank Account 2 (Optional)</div>
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                                <input type="text" name="bank_2_name" class="form-input" placeholder="Bank Name" value="<?= htmlspecialchars($bank_2_name) ?>">
                                <input type="text" name="bank_2_acc_num" class="form-input" placeholder="Account Number" value="<?= htmlspecialchars($bank_2_acc_num) ?>">
                                <input type="text" name="bank_2_acc_name" class="form-input" placeholder="Account Name" value="<?= htmlspecialchars($bank_2_acc_name) ?>">
                            </div>
                        </div>

                        <div style="background:var(--gray-50); padding:12px; border-radius:8px; margin-bottom:16px;">
                            <div style="font-size:0.8rem; font-weight:700; margin-bottom:8px; color:var(--primary);">Bank Account 3 (Optional)</div>
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                                <input type="text" name="bank_3_name" class="form-input" placeholder="Bank Name" value="<?= htmlspecialchars($bank_3_name) ?>">
                                <input type="text" name="bank_3_acc_num" class="form-input" placeholder="Account Number" value="<?= htmlspecialchars($bank_3_acc_num) ?>">
                                <input type="text" name="bank_3_acc_name" class="form-input" placeholder="Account Name" value="<?= htmlspecialchars($bank_3_acc_name) ?>">
                            </div>
                        </div>

                        <div class="form-group mb-24" style="display:flex; align-items:center; gap:10px; background:var(--gray-50); padding:12px; border-radius:8px;">
                            <input type="checkbox" name="maintenance_mode" id="maint" style="width:16px; height:16px;" <?= ($maintenance_mode === '1') ? 'checked' : '' ?>>
                            <label for="maint" style="font-size:0.83rem; font-weight:700; color:var(--primary); cursor:pointer; margin:0;">
                                Enable System Maintenance Mode (locks public portal access)
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary" style="padding:10px 24px; font-weight:700;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Configurations
                        </button>
                    </form>

                    <!-- Live SMTP Mailer Configuration -->
                    <div style="margin-top:28px; border-top:1px solid #E4E7ED; padding-top:20px;">
                        <h3 style="font-family:'Fraunces',serif; font-size:1.1rem; color:var(--primary); margin-top:0; margin-bottom:16px;">Live SMTP Mailer Configurations</h3>
                        <form method="POST" action="settings.php">
                            <input type="hidden" name="action" value="save_smtp">
                            <div class="form-group mb-12">
                                <label class="form-label" style="font-size:0.78rem; font-weight:700;">SMTP Server Host</label>
                                <input type="text" name="smtp_host" class="form-input" value="<?= htmlspecialchars($smtp_host) ?>" required placeholder="e.g. mail.ohati.com or smtp.gmail.com">
                            </div>
                            <div class="form-group mb-12">
                                <label class="form-label" style="font-size:0.78rem; font-weight:700;">SMTP Port (587 TLS / 465 SSL)</label>
                                <input type="number" name="smtp_port" class="form-input" value="<?= htmlspecialchars($smtp_port) ?>" required placeholder="587">
                            </div>
                            <div class="form-group mb-12">
                                <label class="form-label" style="font-size:0.78rem; font-weight:700;">SMTP Username / Email</label>
                                <input type="text" name="smtp_user" class="form-input" value="<?= htmlspecialchars($smtp_user) ?>" required placeholder="contact@ohati.com">
                            </div>
                            <div class="form-group mb-16">
                                <label class="form-label" style="font-size:0.78rem; font-weight:700;">SMTP Password</label>
                                <input type="password" name="smtp_pass" class="form-input" value="<?= htmlspecialchars($smtp_pass) ?>" placeholder="Enter cPanel mail password">
                            </div>
                            <button type="submit" class="btn btn-primary" style="padding:8px 18px; font-weight:700; font-size:0.82rem;">
                                <i class="fa-solid fa-paper-plane"></i> Save SMTP Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Admin Credentials/Security Column -->
                <div class="card" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:24px; height:fit-content;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin-top:0; margin-bottom:20px; border-bottom:1px solid #F0F2F5; padding-bottom:12px;">Admin Security</h3>
                    
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;">Current Password</label>
                            <input type="password" name="current_password" class="form-input" placeholder="Your current password" required>
                        </div>

                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;">New Password</label>
                            <input type="password" name="new_password" class="form-input" placeholder="Min 8 characters" required>
                        </div>

                        <div class="form-group mb-20">
                            <label class="form-label" style="font-weight:700;">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Repeat password" required>
                        </div>

                        <button type="submit" class="btn btn-outline btn-full" style="padding:10px; font-weight:700; border-color:var(--primary); color:var(--primary);">
                            <i class="fa-solid fa-key"></i> Update Password
                        </button>
                    </form>
                </div>

            </div>

            <!-- Ad Pricing & Profile Lock Config Row -->
            <div class="settings-container mt-24" style="margin-top: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="card" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:24px;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin-top:0; margin-bottom:20px; border-bottom:1px solid #F0F2F5; padding-bottom:12px;">Sponsored Listings & Ads Pricing</h3>
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="action" value="save_ads_pricing">
                        
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label class="form-label" style="font-weight:700; font-size: 0.75rem;">Starter Price (GHS)</label>
                                    <input type="number" step="0.01" name="ad_plan_starter_price" class="form-input" value="<?= htmlspecialchars($ad_plan_starter_price) ?>" required style="padding: 6px 10px; font-size: 0.8rem;">
                                </div>
                                <div>
                                    <label class="form-label" style="font-weight:700; font-size: 0.75rem;">Starter Reach Estimate</label>
                                    <input type="text" name="ad_plan_starter_reach" class="form-input" value="<?= htmlspecialchars($ad_plan_starter_reach) ?>" required style="padding: 6px 10px; font-size: 0.8rem;">
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label class="form-label" style="font-weight:700; font-size: 0.75rem;">Standard Price (GHS)</label>
                                    <input type="number" step="0.01" name="ad_plan_standard_price" class="form-input" value="<?= htmlspecialchars($ad_plan_standard_price) ?>" required style="padding: 6px 10px; font-size: 0.8rem;">
                                </div>
                                <div>
                                    <label class="form-label" style="font-weight:700; font-size: 0.75rem;">Standard Reach Estimate</label>
                                    <input type="text" name="ad_plan_standard_reach" class="form-input" value="<?= htmlspecialchars($ad_plan_standard_reach) ?>" required style="padding: 6px 10px; font-size: 0.8rem;">
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label class="form-label" style="font-weight:700; font-size: 0.75rem;">Premium Price (GHS)</label>
                                    <input type="number" step="0.01" name="ad_plan_premium_price" class="form-input" value="<?= htmlspecialchars($ad_plan_premium_price) ?>" required style="padding: 6px 10px; font-size: 0.8rem;">
                                </div>
                                <div>
                                    <label class="form-label" style="font-weight:700; font-size: 0.75rem;">Premium Reach Estimate</label>
                                    <input type="text" name="ad_plan_premium_reach" class="form-input" value="<?= htmlspecialchars($ad_plan_premium_reach) ?>" required style="padding: 6px 10px; font-size: 0.8rem;">
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                <div>
                                    <label class="form-label" style="font-weight:700; font-size: 0.75rem;">Platinum Price (GHS)</label>
                                    <input type="number" step="0.01" name="ad_plan_platinum_price" class="form-input" value="<?= htmlspecialchars($ad_plan_platinum_price) ?>" required style="padding: 6px 10px; font-size: 0.8rem;">
                                </div>
                                <div>
                                    <label class="form-label" style="font-weight:700; font-size: 0.75rem;">Platinum Reach Estimate</label>
                                    <input type="text" name="ad_plan_platinum_reach" class="form-input" value="<?= htmlspecialchars($ad_plan_platinum_reach) ?>" required style="padding: 6px 10px; font-size: 0.8rem;">
                                </div>
                            </div>
                        
                        <h4 style="font-size:0.95rem; margin-bottom:12px; color:var(--primary);">Locked Profile Fields Policy</h4>
                        <div class="mb-20" style="display:flex; flex-direction:column; gap:8px;">
                            <?php foreach (['name'=>'Full Name', 'email'=>'Email Address', 'phone'=>'Phone Number', 'dob'=>'Date of Birth'] as $fk => $fl): ?>
                                <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; cursor:pointer;">
                                    <input type="checkbox" name="locked_fields[]" value="<?= $fk ?>" <?= in_array($fk, $locked_fields) ? 'checked' : '' ?>>
                                    <span><?= $fl ?> (Requires Approval after Verification)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary" style="padding:10px 24px; font-weight:700;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Rates & Policies
                        </button>
                    </form>
                </div>

                <div class="card" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:24px;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin-top:0; margin-bottom:20px; border-bottom:1px solid #F0F2F5; padding-bottom:12px;">Active & Scheduled Ads</h3>
                    <div style="max-height: 380px; overflow-y: auto;">
                        <?php if (empty($all_ads)): ?>
                            <p style="color:var(--gray-500); font-size:0.85rem; text-align:center; padding:20px;">No advertisement campaigns found.</p>
                        <?php else: ?>
                            <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
                                <thead>
                                    <tr style="border-bottom:1px solid #F0F2F5; text-align:left;">
                                        <th style="padding:8px 4px;">Vendor / Campaign</th>
                                        <th style="padding:8px 4px;">Duration</th>
                                        <th style="padding:8px 4px;">Clicks/Imps</th>
                                        <th style="padding:8px 4px;">Status</th>
                                        <th style="padding:8px 4px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_ads as $ad): ?>
                                        <tr style="border-bottom:1px solid #F8F9FA;">
                                            <td style="padding:8px 4px;">
                                                <strong><?= htmlspecialchars($ad['vendor_name']) ?></strong><br>
                                                <span style="color:var(--gray-600);"><?= htmlspecialchars($ad['title']) ?></span>
                                            </td>
                                            <td style="padding:8px 4px;">
                                                <?= htmlspecialchars(date('d M', strtotime($ad['start_date']))) ?> - <?= htmlspecialchars(date('d M Y', strtotime($ad['end_date']))) ?>
                                            </td>
                                            <td style="padding:8px 4px;">
                                                <?= $ad['clicks'] ?> / <?= $ad['impressions'] ?>
                                            </td>
                                            <td style="padding:8px 4px;">
                                                <span style="padding:2px 6px; border-radius:4px; font-weight:700; font-size:0.7rem; background:<?= $ad['status'] === 'active' ? 'rgba(34,197,94,0.1)' : 'rgba(244,63,94,0.1)' ?>; color:<?= $ad['status'] === 'active' ? 'var(--success)' : 'var(--rose)' ?>;">
                                                    <?= strtoupper($ad['status']) ?>
                                                </span>
                                            </td>
                                            <td style="padding:8px 4px;">
                                                <form method="POST" action="settings.php" style="display:inline;">
                                                    <input type="hidden" name="action" value="review_ad">
                                                    <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                                    <?php if ($ad['status'] === 'active'): ?>
                                                        <button type="submit" name="status" value="paused" class="btn" style="padding:2px 6px; font-size:0.7rem; background:var(--rose); color:#fff; border:none; border-radius:4px; cursor:pointer;">Pause</button>
                                                    <?php else: ?>
                                                        <button type="submit" name="status" value="active" class="btn" style="padding:2px 6px; font-size:0.7rem; background:var(--success); color:#fff; border:none; border-radius:4px; cursor:pointer;">Resume</button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Change Requests Card -->
            <div class="card mt-24" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:24px; margin-top:24px;">
                <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin-top:0; margin-bottom:20px; border-bottom:1px solid #F0F2F5; padding-bottom:12px;">Locked Profile Change Requests</h3>
                <?php if (empty($pending_change_requests)): ?>
                    <p style="color:var(--gray-500); font-size:0.85rem; text-align:center; padding:20px;">No pending change requests.</p>
                <?php else: ?>
                    <table style="width:100%; border-collapse:collapse; font-size:0.85rem; text-align:left;">
                        <thead>
                            <tr style="border-bottom:1px solid #F0F2F5;">
                                <th style="padding:12px 8px;">User / Member</th>
                                <th style="padding:12px 8px;">Field</th>
                                <th style="padding:12px 8px;">Old Value</th>
                                <th style="padding:12px 8px;">New Value</th>
                                <th style="padding:12px 8px;">Proof Doc</th>
                                <th style="padding:12px 8px;">Admin Notes & Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_change_requests as $req): ?>
                                <tr style="border-bottom:1px solid #F8F9FA;">
                                    <td style="padding:12px 8px;">
                                        <strong><?= htmlspecialchars($req['user_name']) ?></strong><br>
                                        <span style="color:var(--gray-500); font-size:0.75rem;">User ID: <?= $req['user_id'] ?></span>
                                    </td>
                                    <td style="padding:12px 8px; font-weight:700; color:var(--accent);">
                                        <?= htmlspecialchars(strtoupper($req['field_name'])) ?>
                                    </td>
                                    <td style="padding:12px 8px; color:var(--rose); text-decoration:line-through;">
                                        <?= htmlspecialchars($req['old_value'] ?: '(empty)') ?>
                                    </td>
                                    <td style="padding:12px 8px; color:var(--success); font-weight:700;">
                                        <?= htmlspecialchars($req['new_value']) ?>
                                    </td>
                                    <td style="padding:12px 8px;">
                                        <?php if (!empty($req['supporting_document'])): ?>
                                            <a href="<?= htmlspecialchars($req['supporting_document']) ?>" target="_blank" style="color:var(--accent); font-weight:700;"><i class="fa-solid fa-file-arrow-down"></i> View proof</a>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px 8px;">
                                        <form method="POST" action="settings.php" style="display:flex; flex-direction:column; gap:8px;">
                                            <input type="hidden" name="action" value="review_profile_change">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <input type="text" name="admin_notes" class="form-input" placeholder="Feedback/reason" style="padding:4px 8px; font-size:0.8rem;">
                                            <div style="display:flex; gap:8px;">
                                                <button type="submit" name="status" value="approved" class="btn btn-primary" style="padding:4px 10px; font-size:0.75rem; background:var(--success); border-color:var(--success);"><i class="fa-solid fa-check"></i> Approve</button>
                                                <button type="submit" name="status" value="rejected" class="btn btn-outline" style="padding:4px 10px; font-size:0.75rem; color:var(--rose); border-color:var(--rose);"><i class="fa-solid fa-xmark"></i> Reject</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        function toggleSidebar(open) {
            const sidebar = document.querySelector('.admin-sidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
        }
    </script>
</body>
</html>
