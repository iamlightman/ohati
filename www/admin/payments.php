<?php
// admin/payments.php - Ohati Admin Payments & Premium Upgrades Verification Dashboard
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Handle Action Requests
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token verification
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
        die('CSRF token verification failed.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_payment_settings') {
        $settings = [
            'admin_bank_name' => trim($_POST['bank_name'] ?? 'Ecobank Ghana'),
            'admin_account_name' => trim($_POST['account_name'] ?? 'Ohati Global Digital Services'),
            'admin_account_number' => trim($_POST['account_number'] ?? '1441002939201'),
            'admin_momo_provider' => trim($_POST['momo_provider'] ?? 'MTN Mobile Money'),
            'admin_momo_number' => trim($_POST['momo_number'] ?? '0540477911'),
            'admin_momo_name' => trim($_POST['momo_name'] ?? 'Ohati Payments'),
            'admin_payment_instructions' => trim($_POST['payment_instructions'] ?? '')
        ];
        foreach ($settings as $k => $v) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE val_value = VALUES(val_value)");
            $stmt->execute([$k, $v]);
        }
        $message = "Admin payment details updated successfully!";
    } elseif ($action === 'approve_manual_payment') {
        $escrow_id = intval($_POST['escrow_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? 'Approved by Admin');

        $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE id = ?");
        $stmt->execute([$escrow_id]);
        $escrow = $stmt->fetch();

        if ($escrow && $escrow['paystack_status'] !== 'success') {
            $pdo->beginTransaction();
            try {
                $admin_user_id = $_SESSION['admin_user']['id'] ?? $_SESSION['user']['id'] ?? 1;
                $stmt = $pdo->prepare("UPDATE escrow_transactions SET paystack_status = 'success', escrow_status = 'held', released_by = ?, released_at = ? WHERE id = ?");
                $stmt->execute([$admin_user_id, date('Y-m-d H:i:s'), $escrow_id]);

                $provider_ref = !empty($escrow['notes']) ? str_replace('TxID submitted by customer: ', '', $escrow['notes']) : $escrow['paystack_reference'];
                $stmt = $pdo->prepare("INSERT INTO payments (booking_id, user_id, vendor_id, amount, provider_ref, status, type) VALUES (?, ?, ?, ?, ?, 'success', 'escrow_hold')");
                $stmt->execute([$escrow['booking_id'], $escrow['customer_id'], $escrow['vendor_id'], $escrow['amount'], $provider_ref]);

                $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
                $stmt->execute([$escrow['booking_id']]);
                $booking = $stmt->fetch();

                $new_total_paid = $booking['total_paid'] + $escrow['amount'];
                $price_to_match = $booking['negotiated_price'] > 0 ? $booking['negotiated_price'] : $booking['price'];
                $payment_status = ($new_total_paid >= $price_to_match) ? 'Paid' : 'Partially Paid';

                $stmt = $pdo->prepare("UPDATE bookings SET total_paid = ?, payment_status = ?, status = 'Confirmed', escrow_held = escrow_held + ? WHERE id = ?");
                $stmt->execute([$new_total_paid, $payment_status, $escrow['amount'], $escrow['booking_id']]);

                // Ensure vendor wallet & balance
                $v_wallet = $pdo->prepare("SELECT id FROM vendor_wallets WHERE vendor_id = ?");
                $v_wallet->execute([$escrow['vendor_id']]);
                if (!$v_wallet->fetch()) {
                    $v_usr = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
                    $v_usr->execute([$escrow['vendor_id']]);
                    $v_uid = $v_usr->fetchColumn() ?: 0;
                    $pdo->prepare("INSERT INTO vendor_wallets (vendor_id, user_id) VALUES (?, ?)")->execute([$escrow['vendor_id'], $v_uid]);
                }
                $stmt = $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance + ?, pending_balance = pending_balance + ? WHERE vendor_id = ?");
                $stmt->execute([$escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_id']]);

                // Notifications
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Payment Verified & Confirmed! 🎉', ?, 'circle-check')");
                $stmt->execute([$escrow['customer_id'], "Admin verified your payment of GH₵ " . number_format($escrow['amount'], 2) . " for booking #" . $escrow['booking_id'] . "."]);

                $pdo->commit();
                $message = "Manual payment approved! Booking #" . $escrow['booking_id'] . " is now Confirmed.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    } elseif ($action === 'reject_manual_payment') {
        $escrow_id = intval($_POST['escrow_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? 'Rejected by Admin');

        $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE id = ?");
        $stmt->execute([$escrow_id]);
        $escrow = $stmt->fetch();

        if ($escrow) {
            $pdo->prepare("UPDATE escrow_transactions SET paystack_status = 'rejected', escrow_status = 'rejected', notes = ? WHERE id = ?")->execute([$notes, $escrow_id]);
            $pdo->prepare("UPDATE bookings SET payment_status = 'Unpaid' WHERE id = ?")->execute([$escrow['booking_id']]);
            $message = "Manual payment rejected.";
        }
    } elseif ($action === 'approve_premium') {
        $req_id = intval($_POST['request_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $pdo->prepare("SELECT * FROM premium_requests WHERE id = ?");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch();
        
        if ($req && $req['status'] === 'pending') {
            $pdo->beginTransaction();
            try {
                // Update premium request status
                $pdo->prepare("UPDATE premium_requests SET status = 'approved', payment_notes = ? WHERE id = ?")->execute([$notes, $req_id]);
                
                // Set vendor status to premium = 1
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                $pdo->prepare("UPDATE vendors SET premium = 1, premium_expires_at = ? WHERE id = ?")->execute([$expires_at, $req['vendor_id']]);
                
                // Log financial audit trail
                $actor_name = $_SESSION['admin_user']['name'] ?? 'Admin';
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $audit = $pdo->prepare("INSERT INTO financial_audit_log (action, entity_type, entity_id, actor_name, actor_role, amount, ip_address, details) VALUES ('approve_premium', 'premium_requests', ?, ?, 'admin', 150, ?, ?)");
                $audit->execute([$req_id, $actor_name, $ip, "Approved premium membership request. Vendor ID: " . $req['vendor_id'] . ". Notes: " . $notes]);

                // Get vendor user_id to notify
                $v_stmt = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
                $v_stmt->execute([$req['vendor_id']]);
                $uid = $v_stmt->fetchColumn();
                
                if ($uid) {
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Premium Membership Activated', ?, 'crown')");
                    $notif->execute([$uid, "Your premium membership upgrade request has been approved. Welcome to Premium! Expires: " . substr($expires_at, 0, 10)]);
                }
                
                $pdo->commit();
                $message = "Premium membership upgrade request approved successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Database error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Invalid or already approved premium upgrade request.";
            $message_type = 'error';
        }
    } elseif ($action === 'reject_premium') {
        $req_id = intval($_POST['request_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? 'Rejected by Administrator');
        
        $stmt = $pdo->prepare("SELECT * FROM premium_requests WHERE id = ?");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch();
        
        if ($req && $req['status'] === 'pending') {
            $pdo->beginTransaction();
            try {
                // Update premium request status
                $pdo->prepare("UPDATE premium_requests SET status = 'rejected', payment_notes = ? WHERE id = ?")->execute([$notes, $req_id]);
                
                // Log financial audit trail
                $actor_name = $_SESSION['admin_user']['name'] ?? 'Admin';
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $audit = $pdo->prepare("INSERT INTO financial_audit_log (action, entity_type, entity_id, actor_name, actor_role, amount, ip_address, details) VALUES ('reject_premium', 'premium_requests', ?, ?, 'admin', 0, ?, ?)");
                $audit->execute([$req_id, $actor_name, $ip, "Rejected premium membership request. Vendor ID: " . $req['vendor_id'] . ". Reason: " . $notes]);

                // Get vendor user_id to notify
                $v_stmt = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
                $v_stmt->execute([$req['vendor_id']]);
                $uid = $v_stmt->fetchColumn();
                
                if ($uid) {
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Premium Membership Upgrade Rejected', ?, 'xmark')");
                    $notif->execute([$uid, "Your premium upgrade request was rejected. Reason: " . $notes]);
                }
                
                $pdo->commit();
                $message = "Premium membership upgrade request rejected.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Database error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Invalid or already processed premium upgrade request.";
            $message_type = 'error';
        }
    }
}

// Fetch stats
$total_premium = $pdo->query("SELECT COUNT(*) FROM vendors WHERE premium = 1")->fetchColumn() ?: 0;
$pending_payments_count = $pdo->query("SELECT COUNT(*) FROM escrow_transactions WHERE paystack_status = 'pending_verification' OR paystack_status = 'pending_submission'")->fetchColumn() ?: 0;
$pending_premium = $pdo->query("SELECT COUNT(*) FROM premium_requests WHERE status = 'pending'")->fetchColumn() ?: 0;
$total_ads = $pdo->query("SELECT COUNT(*) FROM advertisements")->fetchColumn() ?: 0;
$pending_ads = $pdo->query("SELECT COUNT(*) FROM advertisements WHERE status = 'pending_approval'")->fetchColumn() ?: 0;
$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE (kyc_status = 'pending_verification' OR kyc_status = 'pending') AND (kyc_id_front != '' OR kyc_selfie != '' OR kyc_id_back != '')")->fetchColumn() ?: 0;

// Fetch admin payment settings
$s_rows = $pdo->query("SELECT key_name, val_value FROM system_settings WHERE key_name LIKE 'admin_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$admin_settings = [
    'bank_name' => $s_rows['admin_bank_name'] ?? 'Ecobank Ghana',
    'account_name' => $s_rows['admin_account_name'] ?? 'Ohati Global Digital Services',
    'account_number' => $s_rows['admin_account_number'] ?? '1441002939201',
    'momo_provider' => $s_rows['admin_momo_provider'] ?? 'MTN Mobile Money',
    'momo_number' => $s_rows['admin_momo_number'] ?? '0540477911',
    'momo_name' => $s_rows['admin_momo_name'] ?? 'Ohati Payments',
    'payment_instructions' => $s_rows['admin_payment_instructions'] ?? 'Please transfer the exact payment amount to our Admin Bank Account or Mobile Money. After completing your payment, enter your Transaction ID (TxID) below for Admin verification.'
];

// Fetch queues
$pending_manual_payments = $pdo->query("SELECT e.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone, v.name as vendor_name FROM escrow_transactions e JOIN users u ON e.customer_id = u.id JOIN vendors v ON e.vendor_id = v.id WHERE e.paystack_status = 'pending_verification' OR e.paystack_status = 'pending_submission' ORDER BY e.id DESC")->fetchAll();
$premium_requests = $pdo->query("SELECT p.*, v.name as vendor_name, v.logo as vendor_logo FROM premium_requests p JOIN vendors v ON p.vendor_id = v.id ORDER BY p.id DESC LIMIT 100")->fetchAll();
$audit_trail = $pdo->query("SELECT * FROM financial_audit_log ORDER BY id DESC LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Premium & Payments Console</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .badge {
            padding: 4px 8px;
            font-size: 0.65rem;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-success { background: rgba(16,185,129,0.1); color: #10b981; }
        .badge-warning { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .badge-danger { background: rgba(239,68,68,0.1); color: #ef4444; }
        .badge-info { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .action-btn-group { display: flex; gap: 6px; }
        .modal-action { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-action-box { background: #fff; padding: 24px; border-radius: 8px; max-width: 400px; width: 90%; }
        @media(max-width: 900px) {
            .admin-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; display: flex !important; }
            .admin-sidebar.open { transform: translateX(0); }
            .admin-main { margin-left: 0 !important; }
            .admin-stat-grid { grid-template-columns: repeat(2, 1fr) !important; }
        }
    </style>
</head>
<body class="admin-layout">

    <!-- Admin Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
                <h1 class="admin-page-title">Premium Membership & Payments</h1>
            </div>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-circle-user" style="font-size:1.2rem; color:var(--accent);"></i>
                <span>System Administrator</span>
            </div>
        </header>

        <div class="admin-content">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>" style="margin-bottom:20px;">
                    <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- STATS GRID -->
            <div class="admin-stat-grid" style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px;">
                <div class="admin-stat-card" style="background:#fff; padding:20px; border-radius:8px; border:1px solid var(--gray-200);">
                    <div style="font-size:0.75rem; color:var(--gray-500); text-transform:uppercase; margin-bottom:6px;">Premium Vendors</div>
                    <div style="font-size:1.5rem; font-weight:700; color:var(--primary);"><?= $total_premium ?></div>
                </div>
                <div class="admin-stat-card" style="background:#fff; padding:20px; border-radius:8px; border:1px solid var(--gray-200); <?= $pending_premium > 0 ? 'border-color:var(--warning);' : '' ?>">
                    <div style="font-size:0.75rem; color:var(--gray-500); text-transform:uppercase; margin-bottom:6px;">Pending Premium Requests</div>
                    <div style="font-size:1.5rem; font-weight:700; color:<?= $pending_premium > 0 ? 'var(--warning)' : 'var(--primary)' ?>;"><?= $pending_premium ?></div>
                </div>
                <div class="admin-stat-card" style="background:#fff; padding:20px; border-radius:8px; border:1px solid var(--gray-200);">
                    <div style="font-size:0.75rem; color:var(--gray-500); text-transform:uppercase; margin-bottom:6px;">Total Ad Campaigns</div>
                    <div style="font-size:1.5rem; font-weight:700; color:var(--primary);"><?= $total_ads ?></div>
                </div>
                <div class="admin-stat-card" style="background:#fff; padding:20px; border-radius:8px; border:1px solid var(--gray-200); <?= $pending_ads > 0 ? 'border-color:var(--rose);' : '' ?>">
                    <div style="font-size:0.75rem; color:var(--gray-500); text-transform:uppercase; margin-bottom:6px;">Pending Ad Campaigns</div>
                    <div style="font-size:1.5rem; font-weight:700; color:<?= $pending_ads > 0 ? 'var(--rose)' : 'var(--primary)' ?>;"><?= $pending_ads ?></div>
                </div>
            </div>

            <!-- ADMIN PAYMENT INSTRUCTIONS SETTINGS CARD -->
            <div class="card mb-24" style="background:#fff; border-radius:8px; border:1px solid var(--gray-200); margin-bottom:24px; padding:20px;">
                <h3 style="font-family:'Fraunces',serif; font-size:1.1rem; color:var(--primary); margin:0 0 16px 0;">
                    <i class="fa-solid fa-building-columns" style="color:var(--accent); margin-right:8px;"></i>Platform Payment Instructions & Bank Setup
                </h3>
                <form method="POST" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf'] ?>">
                    <input type="hidden" name="action" value="update_payment_settings">

                    <div>
                        <label style="font-size:0.75rem; font-weight:600; color:var(--gray-700); display:block; margin-bottom:4px;">Bank Name</label>
                        <input type="text" name="bank_name" value="<?= htmlspecialchars($admin_settings['bank_name']) ?>" class="form-control" style="width:100%; padding:8px; font-size:0.85rem;" required>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:600; color:var(--gray-700); display:block; margin-bottom:4px;">Bank Account Name</label>
                        <input type="text" name="account_name" value="<?= htmlspecialchars($admin_settings['account_name']) ?>" class="form-control" style="width:100%; padding:8px; font-size:0.85rem;" required>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:600; color:var(--gray-700); display:block; margin-bottom:4px;">Bank Account Number</label>
                        <input type="text" name="account_number" value="<?= htmlspecialchars($admin_settings['account_number']) ?>" class="form-control" style="width:100%; padding:8px; font-size:0.85rem;" required>
                    </div>

                    <div>
                        <label style="font-size:0.75rem; font-weight:600; color:var(--gray-700); display:block; margin-bottom:4px;">Mobile Money Provider</label>
                        <input type="text" name="momo_provider" value="<?= htmlspecialchars($admin_settings['momo_provider']) ?>" class="form-control" style="width:100%; padding:8px; font-size:0.85rem;" required>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:600; color:var(--gray-700); display:block; margin-bottom:4px;">MoMo Merchant/Phone Number</label>
                        <input type="text" name="momo_number" value="<?= htmlspecialchars($admin_settings['momo_number']) ?>" class="form-control" style="width:100%; padding:8px; font-size:0.85rem;" required>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:600; color:var(--gray-700); display:block; margin-bottom:4px;">MoMo Account Name</label>
                        <input type="text" name="momo_name" value="<?= htmlspecialchars($admin_settings['momo_name']) ?>" class="form-control" style="width:100%; padding:8px; font-size:0.85rem;" required>
                    </div>

                    <div style="grid-column: span 3;">
                        <label style="font-size:0.75rem; font-weight:600; color:var(--gray-700); display:block; margin-bottom:4px;">Customer Instructions</label>
                        <textarea name="payment_instructions" class="form-control" style="width:100%; height:70px; padding:8px; font-size:0.85rem;" required><?= htmlspecialchars($admin_settings['payment_instructions']) ?></textarea>
                    </div>

                    <div style="grid-column: span 3; text-align:right;">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Save Payment Settings</button>
                    </div>
                </form>
            </div>

            <!-- PENDING MANUAL PAYMENTS VERIFICATION QUEUE -->
            <div class="card mb-24" style="background:#fff; border-radius:8px; border:1px solid var(--gray-200); margin-bottom:24px;">
                <div style="padding:18px 20px; border-bottom:1px solid var(--gray-200); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.05rem; color:var(--primary); margin:0;">
                        <i class="fa-solid fa-money-check-dollar" style="color:var(--accent); margin-right:8px;"></i>Customer Booking Manual Payments Queue
                    </h3>
                    <span class="badge badge-warning"><?= count($pending_manual_payments) ?> Pending Verifications</span>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left; background:var(--gray-50);">
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Date</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Customer</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Vendor & Booking</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Submitted TxID / Proof</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Amount</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_manual_payments)): ?>
                                <tr><td colspan="6" style="padding:20px; text-align:center; font-size:0.8rem; color:var(--gray-400);">No manual payments awaiting verification.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pending_manual_payments as $m): ?>
                                    <tr style="border-bottom:1px solid var(--gray-100);">
                                        <td style="padding:14px 20px; font-size:0.75rem;"><?= $m['created_at'] ?></td>
                                        <td style="padding:14px 20px; font-size:0.75rem;">
                                            <strong><?= htmlspecialchars($m['customer_name']) ?></strong><br>
                                            <span style="font-size:0.68rem; color:var(--gray-500);"><?= htmlspecialchars($m['customer_phone'] ?: $m['customer_email']) ?></span>
                                        </td>
                                        <td style="padding:14px 20px; font-size:0.75rem;">
                                            <strong>Booking #<?= $m['booking_id'] ?></strong><br>
                                            <span style="font-size:0.68rem; color:var(--gray-500);"><?= htmlspecialchars($m['vendor_name']) ?></span>
                                        </td>
                                        <td style="padding:14px 20px; font-size:0.75rem;">
                                            <strong style="color:var(--primary);">Ref:</strong> <?= htmlspecialchars($m['paystack_reference']) ?><br>
                                            <span style="font-size:0.72rem; color:var(--emerald); font-weight:600;"><?= htmlspecialchars($m['notes'] ?: 'Awaiting TxID submission') ?></span>
                                        </td>
                                        <td style="padding:14px 20px; font-size:0.75rem; font-weight:700; color:var(--primary);">GH₵ <?= number_format($m['amount'], 2) ?></td>
                                        <td style="padding:14px 20px; font-size:0.75rem;">
                                            <div class="action-btn-group">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf'] ?>">
                                                    <input type="hidden" name="action" value="approve_manual_payment">
                                                    <input type="hidden" name="escrow_id" value="<?= $m['id'] ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm" style="font-size:0.68rem; padding:4px 10px;" onclick="return confirm('Verify and approve this manual payment? Booking will be Confirmed and funds held in Escrow.')"><i class="fa-solid fa-check"></i> Approve</button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf'] ?>">
                                                    <input type="hidden" name="action" value="reject_manual_payment">
                                                    <input type="hidden" name="escrow_id" value="<?= $m['id'] ?>">
                                                    <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.68rem; padding:4px 10px; color:var(--rose); border-color:rgba(244,63,94,0.3);" onclick="return confirm('Reject this payment submission?')"><i class="fa-solid fa-xmark"></i> Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PREMIUM REQUESTS QUEUE -->
            <div class="card mb-24" style="background:#fff; border-radius:8px; border:1px solid var(--gray-200); margin-bottom:24px;">
                <div style="padding:18px 20px; border-bottom:1px solid var(--gray-200);">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.05rem; color:var(--primary); margin:0;">Premium Upgrade Requests</h3>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left; background:var(--gray-50);">
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Date</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Vendor</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Receipt & Reference</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Amount</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Status</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($premium_requests)): ?>
                                <tr><td colspan="6" style="padding:20px; text-align:center; font-size:0.8rem; color:var(--gray-400);">No premium requests in queue.</td></tr>
                            <?php else: ?>
                                <?php foreach ($premium_requests as $p): ?>
                                    <tr style="border-bottom:1px solid var(--gray-100);">
                                        <td style="padding:14px 20px; font-size:0.75rem;"><?= $p['created_at'] ?></td>
                                        <td style="padding:14px 20px; font-size:0.75rem;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <img src="../<?= htmlspecialchars($p['vendor_logo'] ?: 'img/logo black transparent small.png') ?>" style="width:28px; height:28px; border-radius:50%; object-fit:cover; border:1px solid rgba(0,0,0,0.1);">
                                                <strong><?= htmlspecialchars($p['vendor_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td style="padding:14px 20px; font-size:0.75rem;">
                                            <strong>Ref:</strong> <?= htmlspecialchars($p['payment_ref'] ?: 'N/A') ?><br>
                                            <span style="font-size:0.65rem; color:var(--gray-500);">Notes: <?= htmlspecialchars($p['payment_notes'] ?: 'None') ?></span>
                                        </td>
                                        <td style="padding:14px 20px; font-size:0.75rem; font-weight:600;">GHS <?= number_format($p['amount'], 2) ?></td>
                                        <td style="padding:14px 20px; font-size:0.75rem;">
                                            <span class="badge badge-<?= $p['status'] === 'approved' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= $p['status'] ?></span>
                                        </td>
                                        <td style="padding:14px 20px; font-size:0.75rem;">
                                            <div class="action-btn-group">
                                                <?php if (!empty($p['receipt_url'])): ?>
                                                    <button class="btn btn-outline btn-sm" style="font-size:0.65rem; padding:4px 8px; border-color:#D4AF37; color:#D4AF37;" onclick="viewReceipt('<?= htmlspecialchars(addslashes($p['receipt_url'])) ?>', '<?= htmlspecialchars(addslashes($p['payment_ref'])) ?>', '<?= htmlspecialchars(addslashes($p['payment_notes'] ?: '')) ?>')"><i class="fa-solid fa-receipt"></i> Receipt</button>
                                                <?php endif; ?>
                                                <?php if ($p['status'] === 'pending'): ?>
                                                    <button class="btn btn-primary btn-sm" style="font-size:0.65rem; padding:4px 8px;" onclick="openReviewModal(<?= $p['id'] ?>, 'approve')">Approve</button>
                                                    <button class="btn btn-outline btn-sm" style="font-size:0.65rem; padding:4px 8px; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="openReviewModal(<?= $p['id'] ?>, 'reject')">Reject</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FINANCIAL AUDIT TRAIL -->
            <div class="card" style="background:#fff; border-radius:8px; border:1px solid var(--gray-200);">
                <div style="padding:18px 20px; border-bottom:1px solid var(--gray-200);">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.05rem; color:var(--primary); margin:0;">Platform Financial Audit Trail</h3>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left; background:var(--gray-50);">
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Timestamp</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Action</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Entity Type</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Actor</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Amount</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">IP Address</th>
                                <th style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($audit_trail)): ?>
                                <tr><td colspan="7" style="padding:20px; text-align:center; font-size:0.8rem; color:var(--gray-400);">No audit log records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($audit_trail as $log): ?>
                                    <tr style="border-bottom:1px solid var(--gray-100);">
                                        <td style="padding:12px 20px; font-size:0.75rem;"><?= $log['created_at'] ?></td>
                                        <td style="padding:12px 20px; font-size:0.75rem;"><strong><?= htmlspecialchars($log['action']) ?></strong></td>
                                        <td style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);"><?= htmlspecialchars($log['entity_type']) ?> (ID: <?= $log['entity_id'] ?>)</td>
                                        <td style="padding:12px 20px; font-size:0.75rem;"><strong><?= htmlspecialchars($log['actor_name']) ?></strong> <span style="font-size:0.65rem; color:var(--gray-500);">[<?= htmlspecialchars($log['actor_role']) ?>]</span></td>
                                        <td style="padding:12px 20px; font-size:0.75rem; font-weight:600;"><?= $log['amount'] > 0 ? 'GHS ' . number_format($log['amount'], 2) : 'N/A' ?></td>
                                        <td style="padding:12px 20px; font-size:0.75rem; color:var(--gray-500);"><?= htmlspecialchars($log['ip_address']) ?></td>
                                        <td style="padding:12px 20px; font-size:0.75rem; color:var(--gray-600);"><?= htmlspecialchars($log['details']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Review Premium Modal -->
    <div id="review-modal" class="modal-action">
        <div class="modal-action-box">
            <h3 id="review-modal-title" style="margin-bottom:12px;">Approve Premium Request</h3>
            <form method="POST" id="review-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf'] ?>">
                <input type="hidden" name="action" id="review-action" value="approve_premium">
                <input type="hidden" name="request_id" id="review-request-id">
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="font-size:0.75rem; margin-bottom:6px; display:block;">Administrator Notes</label>
                    <textarea name="notes" id="review-notes" required class="form-control" style="width:100%; height:80px; font-size:0.8rem; padding:8px;" placeholder="Provide justification or feedback..."></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeReviewModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="review-submit-btn">Approve & Activate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Receipt Image View Modal -->
    <div id="receipt-modal" class="modal-action">
        <div class="modal-action-box" style="max-width: 480px; padding: 20px;">
            <h3 style="margin-bottom:12px; font-family:'Fraunces',serif; display:flex; justify-content:space-between; align-items:center;">
                <span>Payment Receipt Screenshot</span>
                <button type="button" onclick="closeReceiptModal()" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:var(--gray-400);"><i class="fa-solid fa-xmark"></i></button>
            </h3>
            <div style="text-align:center; margin-bottom:12px;">
                <img id="receipt-img" src="" style="max-width:100%; max-height:300px; border-radius:6px; border:1px solid var(--gray-200); object-fit:contain; background:#f9f9f9;">
            </div>
            <div style="font-size:0.75rem; color:var(--gray-600); line-height:1.5;">
                <strong>Transaction Reference:</strong> <span id="receipt-ref-text"></span><br>
                <strong>Notes:</strong> <span id="receipt-notes-text"></span>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar(open) {
            const sidebar = document.getElementById('adminSidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
        }

        function openReviewModal(requestId, action) {
            document.getElementById('review-request-id').value = requestId;
            document.getElementById('review-action').value = action === 'approve' ? 'approve_premium' : 'reject_premium';
            
            const title = document.getElementById('review-modal-title');
            const submitBtn = document.getElementById('review-submit-btn');
            const notes = document.getElementById('review-notes');

            if (action === 'approve') {
                title.textContent = 'Approve Premium Request';
                submitBtn.textContent = 'Approve & Activate';
                submitBtn.className = 'btn btn-primary btn-sm';
                notes.placeholder = 'e.g. Verified manual payment of GH₵ 150.';
            } else {
                title.textContent = 'Reject Premium Request';
                submitBtn.textContent = 'Reject Request';
                submitBtn.className = 'btn btn-outline btn-sm';
                submitBtn.style.color = 'var(--rose)';
                submitBtn.style.borderColor = 'rgba(244,63,94,0.2)';
                notes.placeholder = 'Provide reason for rejection...';
            }

            document.getElementById('review-modal').style.display = 'flex';
        }

        function closeReviewModal() {
            document.getElementById('review-modal').style.display = 'none';
        }

        function viewReceipt(imgUrl, ref, notes) {
            let src = imgUrl;
            if (src && !src.startsWith('data:') && !src.startsWith('http')) {
                src = '../' + src;
            }
            document.getElementById('receipt-img').src = src;
            document.getElementById('receipt-ref-text').textContent = ref || 'N/A';
            document.getElementById('receipt-notes-text').textContent = notes || 'None';
            document.getElementById('receipt-modal').style.display = 'flex';
        }

        function closeReceiptModal() {
            document.getElementById('receipt-modal').style.display = 'none';
        }
    </script>
</body>
</html>
