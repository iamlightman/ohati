<?php
// admin/referrals.php - Ohati Admin Refer & Earn Management Console
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

$message = '';
$message_type = 'success';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $reward_amt = floatval($_POST['reward_amount'] ?? 10.0);
    $program_active = isset($_POST['program_active']) ? '1' : '0';

    $chk_rf1 = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = 'referral_reward_amount'");
    $chk_rf1->execute();
    if ($chk_rf1->fetchColumn() > 0) {
        $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = 'referral_reward_amount'")->execute([$reward_amt]);
    } else {
        $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES ('referral_reward_amount', ?)")->execute([$reward_amt]);
    }

    $chk_rf2 = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = 'referral_program_active'");
    $chk_rf2->execute();
    if ($chk_rf2->fetchColumn() > 0) {
        $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = 'referral_program_active'")->execute([$program_active]);
    } else {
        $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES ('referral_program_active', ?)")->execute([$program_active]);
    }

    $message = "Referral program settings updated successfully.";
}

// Fetch System Settings
$reward_amt = 10.0;
$program_active = 1;
try {
    $s1 = $pdo->query("SELECT val_value FROM system_settings WHERE key_name = 'referral_reward_amount'")->fetchColumn();
    if ($s1 !== false && $s1 !== null) $reward_amt = floatval($s1);
    $s2 = $pdo->query("SELECT val_value FROM system_settings WHERE key_name = 'referral_program_active'")->fetchColumn();
    if ($s2 !== false && $s2 !== null) $program_active = intval($s2);
} catch (Exception $e) {}

// Fetch Referrals History
$referrals = $pdo->query("
    SELECT r.*, u1.name as referrer_name, u1.email as referrer_email, u2.name as referred_name, u2.email as referred_email 
    FROM referrals r 
    JOIN users u1 ON r.referrer_id = u1.id 
    JOIN users u2 ON r.referred_id = u2.id 
    ORDER BY r.id DESC
")->fetchAll();

$total_referrals = count($referrals);
$total_payouts = $pdo->query("SELECT SUM(reward_amount) FROM referrals WHERE status = 'completed'")->fetchColumn() ?: 0;
$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Refer & Earn Console</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .admin-stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #E4E7ED;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        .admin-stat-title { font-size: 0.75rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; }
        .admin-stat-value { font-size: 1.6rem; font-weight: 800; color: var(--primary); margin-top: 4px; }
        @media(max-width: 900px) {
            .admin-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; display: flex !important; }
            .admin-sidebar.open { transform: translateX(0); }
            .admin-main { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="admin-layout">

    <!-- Admin Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <header class="admin-header">
            <h2 style="margin:0; font-size:1.2rem; font-weight:800;">Refer & Earn Management</h2>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600);">System Administrator</div>
        </header>

        <div class="admin-content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> mb-20" style="padding:12px 16px; border-radius:10px;">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:20px;">
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Total Referrals</div>
                    <div class="admin-stat-value"><?= number_format($total_referrals) ?></div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Total Payouts Issued</div>
                    <div class="admin-stat-value" style="color:var(--success);">GH₵ <?= number_format($total_payouts, 2) ?></div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Reward Per Referral</div>
                    <div class="admin-stat-value" style="color:var(--accent);">GH₵ <?= number_format($reward_amt, 2) ?></div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Program Status</div>
                    <div class="admin-stat-value" style="color:<?= $program_active ? 'var(--success)' : 'var(--danger)' ?>; font-size:1.2rem; margin-top:8px;">
                        <?= $program_active ? '<i class="fa-solid fa-circle-check"></i> ACTIVE' : '<i class="fa-solid fa-circle-xmark"></i> DISABLED' ?>
                    </div>
                </div>
            </div>

            <!-- Program Settings Control Card -->
            <div class="card mb-20" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:20px;">
                <h3 style="margin-top:0; font-size:1.1rem; color:var(--primary);"><i class="fa-solid fa-sliders"></i> Referral Program Settings</h3>
                <form method="POST" action="referrals.php" style="display:grid; grid-template-columns:1fr 1fr auto; gap:16px; align-items:end;">
                    <input type="hidden" name="action" value="update_settings">
                    <div>
                        <label class="form-label" style="font-weight:700;">Reward Amount Per Referral (GH₵)</label>
                        <input type="number" step="0.5" name="reward_amount" class="form-input" value="<?= htmlspecialchars($reward_amt) ?>" required style="margin:0;">
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; padding-bottom:10px;">
                        <input type="checkbox" name="program_active" id="program_active" value="1" <?= $program_active ? 'checked' : '' ?> style="width:20px; height:20px;">
                        <label for="program_active" style="font-weight:700; cursor:pointer;">Enable Refer & Earn Program</label>
                    </div>
                    <button type="submit" class="btn btn-primary" style="height:42px; font-weight:700;"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
                </form>
            </div>

            <!-- Referrals History Table -->
            <div class="admin-table-wrap" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #E4E7ED;">
                    <h3 style="margin:0; font-size:1.1rem; color:var(--primary);"><i class="fa-solid fa-list"></i> Referrals Audit History</h3>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Referrer User</th>
                            <th>Referred User</th>
                            <th>Referral Code</th>
                            <th>Reward Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($referrals)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:40px; color:var(--gray-400);">No referrals recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($referrals as $r): ?>
                                <tr>
                                    <td style="font-weight:700;">#<?= $r['id'] ?></td>
                                    <td>
                                        <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($r['referrer_name']) ?></div>
                                        <div style="font-size:0.7rem; color:var(--gray-500);"><?= htmlspecialchars($r['referrer_email']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:700; color:var(--gray-800);"><?= htmlspecialchars($r['referred_name']) ?></div>
                                        <div style="font-size:0.7rem; color:var(--gray-500);"><?= htmlspecialchars($r['referred_email']) ?></div>
                                    </td>
                                    <td><span class="badge badge-info" style="font-size:0.75rem; font-weight:700;"><?= htmlspecialchars($r['referral_code']) ?></span></td>
                                    <td style="font-weight:800; color:var(--success);">GH₵ <?= number_format($r['reward_amount'], 2) ?></td>
                                    <td><span class="badge badge-success"><?= htmlspecialchars($r['status']) ?></span></td>
                                    <td style="font-size:0.78rem; color:var(--gray-600);"><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
