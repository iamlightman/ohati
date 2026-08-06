<?php
// admin/discounts.php - Ohati Admin Discount Offers & Promo Codes Console
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

$message = '';
$message_type = 'success';

// Handle Action Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = in_array($_POST['discount_type'] ?? '', ['percentage', 'fixed']) ? $_POST['discount_type'] : 'percentage';
        $val = floatval($_POST['discount_value'] ?? 0);
        $min_amt = floatval($_POST['min_booking_amount'] ?? 0);
        $max_amt = floatval($_POST['max_discount_amount'] ?? 0);
        $event_type = trim($_POST['event_type'] ?? 'All');
        $limit = intval($_POST['usage_limit'] ?? 100);
        $until = trim($_POST['valid_until'] ?? '');

        if (!empty($code) && $val > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO discounts (code, discount_type, discount_value, min_booking_amount, max_discount_amount, event_type, usage_limit, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$code, $type, $val, $min_amt, $max_amt, $event_type, $limit, $until]);
                $message = "Discount promo code '$code' created successfully.";
            } catch (Exception $e) {
                $message = "Error creating code: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE discounts SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
            $message = "Discount status updated.";
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM discounts WHERE id = ?")->execute([$id]);
            $message = "Discount promo code deleted.";
        }
    }
}

// Fetch Discounts
$discounts = $pdo->query("SELECT * FROM discounts ORDER BY id DESC")->fetchAll();
$total_codes = count($discounts);
$active_codes = $pdo->query("SELECT COUNT(*) FROM discounts WHERE is_active = 1")->fetchColumn() ?: 0;
$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Discount Offers & Vouchers Console</title>
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
            <h2 style="margin:0; font-size:1.2rem; font-weight:800;">Discount Offers & Voucher Codes</h2>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600);">System Administrator</div>
        </header>

        <div class="admin-content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> mb-20" style="padding:12px 16px; border-radius:10px;">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:20px;">
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Total Promo Codes</div>
                    <div class="admin-stat-value"><?= number_format($total_codes) ?></div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Active Vouchers</div>
                    <div class="admin-stat-value" style="color:var(--success);"><?= number_format($active_codes) ?></div>
                </div>
            </div>

            <!-- Create Promo Code Form Card -->
            <div class="card mb-20" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:20px;">
                <h3 style="margin-top:0; font-size:1.1rem; color:var(--primary);"><i class="fa-solid fa-plus-circle"></i> Create New Promo Voucher Code</h3>
                <form method="POST" action="discounts.php" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; align-items:end;">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label class="form-label" style="font-weight:700;">Promo Code</label>
                        <input type="text" name="code" class="form-input" placeholder="e.g. WELCOME20" required style="text-transform:uppercase; margin:0;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:700;">Discount Type</label>
                        <select name="discount_type" class="form-select" style="margin:0;">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount (GH₵)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:700;">Value (% or GH₵)</label>
                        <input type="number" step="0.5" name="discount_value" class="form-input" placeholder="e.g. 10 or 50" required style="margin:0;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:700;">Min Booking Price (GH₵)</label>
                        <input type="number" step="10" name="min_booking_amount" class="form-input" placeholder="0 = no min" style="margin:0;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:700;">Event Type</label>
                        <select name="event_type" class="form-select" style="margin:0;">
                            <option value="All">All Event Types</option>
                            <option value="Wedding">Wedding</option>
                            <option value="Funeral">Funeral</option>
                            <option value="Birthday">Birthday</option>
                            <option value="Corporate">Corporate</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:700;">Usage Limit</label>
                        <input type="number" name="usage_limit" class="form-input" value="500" style="margin:0;">
                    </div>
                    <div>
                        <label class="form-label" style="font-weight:700;">Valid Until</label>
                        <input type="date" name="valid_until" class="form-input" style="margin:0;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="height:42px; font-weight:700;"><i class="fa-solid fa-plus"></i> Create Code</button>
                </form>
            </div>

            <!-- Discounts Table -->
            <div class="admin-table-wrap" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #E4E7ED;">
                    <h3 style="margin:0; font-size:1.1rem; color:var(--primary);"><i class="fa-solid fa-ticket"></i> Active & Past Voucher Codes</h3>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Type & Value</th>
                            <th>Min Booking</th>
                            <th>Event Filter</th>
                            <th>Usage</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($discounts)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px; color:var(--gray-400);">No discount codes created yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($discounts as $d): ?>
                                <tr>
                                    <td><strong style="font-size:0.9rem; color:var(--primary); border:1px dashed var(--primary); padding:2px 8px; border-radius:6px;"><?= htmlspecialchars($d['code']) ?></strong></td>
                                    <td>
                                        <strong style="color:var(--success);"><?= $d['discount_type'] === 'percentage' ? floatval($d['discount_value']) . '%' : 'GH₵ ' . number_format($d['discount_value'], 2) ?> OFF</strong>
                                    </td>
                                    <td>GH₵ <?= number_format($d['min_booking_amount'], 2) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($d['event_type']) ?></span></td>
                                    <td><?= $d['used_count'] ?> / <?= $d['usage_limit'] ?> used</td>
                                    <td><?= $d['valid_until'] ? htmlspecialchars($d['valid_until']) : 'Never' ?></td>
                                    <td>
                                        <span class="badge <?= $d['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                            <?= $d['is_active'] ? 'ACTIVE' : 'INACTIVE' ?>
                                        </span>
                                    </td>
                                    <td style="display:flex; gap:6px;">
                                        <form method="POST" action="discounts.php" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-xs"><?= $d['is_active'] ? 'Disable' : 'Enable' ?></button>
                                        </form>
                                        <form method="POST" action="discounts.php" style="display:inline;" onsubmit="return confirm('Delete this code?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-xs" style="color:var(--danger);"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
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
