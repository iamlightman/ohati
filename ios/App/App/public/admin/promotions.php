<?php
// admin/promotions.php - Ohati Admin Promotions & Ad Campaigns Management
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $aid = intval($input['ad_id'] ?? 0);
    $action = $input['action'] ?? '';
    
    if ($aid > 0) {
        if ($action === 'toggle_status') {
            $stmt = $pdo->prepare("SELECT a.status, a.title, v.name as vendor_name, v.email FROM advertisements a JOIN vendors v ON a.vendor_id = v.id WHERE a.id = ?");
            $stmt->execute([$aid]);
            $adRow = $stmt->fetch();
            $current = $adRow['status'] ?? 'paused';
            
            $new_status = ($current === 'active') ? 'paused' : 'active';
            $pdo->prepare("UPDATE advertisements SET status = ? WHERE id = ?")->execute([$new_status, $aid]);

            try {
                if ($adRow && !empty($adRow['email'])) {
                    require_once __DIR__ . '/../mail_helper.php';
                    $title = ($new_status === 'active') ? "Ad Campaign Activated" : "Ad Campaign Paused";
                    $badge_label = ($new_status === 'active') ? "Campaign Active" : "Campaign Paused";
                    $badge_type = ($new_status === 'active') ? "success" : "warning";
                    $details = "Your advertisement campaign <strong>'" . htmlspecialchars($adRow['title']) . "'</strong> for <strong>" . htmlspecialchars($adRow['vendor_name']) . "</strong> is now <strong>" . strtoupper($new_status) . "</strong>.";
                    send_admin_notification_email($adRow['email'], $adRow['vendor_name'], $title, $badge_label, $badge_type, $details);
                }
            } catch (Exception $mailEx) {}

            echo json_encode(['success' => true, 'new_status' => $new_status]);
            exit;
        } elseif ($action === 'delete') {
            // Soft delete advertisement: archive first
            $sel = $pdo->prepare("SELECT * FROM advertisements WHERE id = ?");
            $sel->execute([$aid]);
            $ad = $sel->fetch(PDO::FETCH_ASSOC);
            if ($ad) {
                $record_data = json_encode($ad);
                $stmt = $pdo->prepare("INSERT INTO deleted_records (record_type, record_id, record_data) VALUES ('advertisement', ?, ?)");
                $stmt->execute([$aid, $record_data]);
                
                $pdo->prepare("DELETE FROM advertisements WHERE id = ?")->execute([$aid]);
                echo json_encode(['success' => true]);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Campaign not found']);
            exit;
        } elseif ($action === 'approve') {
            $notes = trim($input['admin_notes'] ?? '');
            $end_date = trim($input['end_date'] ?? '');
            $max_views = intval($input['max_views'] ?? 0);
            $max_popups = intval($input['max_popups'] ?? 0);
            $placement = trim($input['placement'] ?? '');
            
            $ad_stmt = $pdo->prepare("SELECT a.*, v.name as vendor_name, v.email, v.phone, v.user_id FROM advertisements a JOIN vendors v ON a.vendor_id = v.id WHERE a.id = ?");
            $ad_stmt->execute([$aid]);
            $ad = $ad_stmt->fetch();
            if ($ad) {
                $final_end_date = !empty($end_date) ? $end_date : ($ad['end_date'] ?: date('Y-m-d H:i:s', strtotime('+30 days')));
                $final_placement = !empty($placement) ? $placement : ($ad['placement'] ?: 'home_top_banner');

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE advertisements SET status = 'active', payment_status = 'paid', end_date = ?, placement = ?, max_views = ?, max_popups = ?, admin_notes = ? WHERE id = ?")
                        ->execute([$final_end_date, $final_placement, $max_views, $max_popups, $notes, $aid]);
                    
                    // Update vendor featured status, Gold badge seal, and verification
                    $pdo->prepare("UPDATE vendors SET featured = 1, premium = 1, verification_badge = 'gold', verified = 1, feature_expires_at = ? WHERE id = ?")->execute([$final_end_date, $ad['vendor_id']]);
                    $pdo->prepare("UPDATE users SET kyc_status = 'verified' WHERE id = ?")->execute([$ad['user_id']]);
                    
                    // Send approved notification
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, '🎉 Ad Campaign Approved & Activated!', ?, 'crown')");
                    $notif->execute([$ad['user_id'], "Congratulations! Your ad campaign '" . $ad['title'] . "' (" . strtoupper(str_replace('_', ' ', $final_placement)) . ") has been approved by admin and is now active until " . $final_end_date . "."]);
                    
                    $pdo->commit();

                    // Send Dual Email + SMS Notification to Vendor
                    try {
                        require_once __DIR__ . '/../sms_helper.php';
                        send_dual_notification(
                            $ad['phone'] ?? '',
                            $ad['email'] ?? '',
                            "🎉 Premium Gold Upgrade Approved!",
                            "Congratulations " . $ad['vendor_name'] . "! Your Premium Gold Upgrade payment receipt has been APPROVED by Ohati Admin. Your Gold Verified Badge is now active on your business profile."
                        );
                    } catch (Exception $mailEx) {}

                    echo json_encode(['success' => true]);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            echo json_encode(['success' => false, 'message' => 'Campaign not found']);
            exit;
        } elseif ($action === 'reject') {
            $notes = trim($input['admin_notes'] ?? 'Receipt image or payment reference could not be verified.');
            
            $ad_stmt = $pdo->prepare("SELECT a.*, v.name as vendor_name, v.email, v.phone, v.user_id FROM advertisements a JOIN vendors v ON a.vendor_id = v.id WHERE a.id = ?");
            $ad_stmt->execute([$aid]);
            $ad = $ad_stmt->fetch();
            if ($ad) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE advertisements SET status = 'rejected', admin_notes = ? WHERE id = ?")->execute([$notes, $aid]);
                    
                    // Send rejected notification
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Premium Upgrade Receipt Notice', ?, 'circle-exclamation')");
                    $notif->execute([$ad['user_id'], "Notice: Your Premium Upgrade payment receipt was declined. Reason: $notes"]);
                    
                    $pdo->commit();

                    // Send Dual Email + SMS Notification to Vendor
                    try {
                        require_once __DIR__ . '/../sms_helper.php';
                        send_dual_notification(
                            $ad['phone'] ?? '',
                            $ad['email'] ?? '',
                            "Premium Upgrade Receipt Notice",
                            "Notice for " . $ad['vendor_name'] . ": Your Premium Upgrade payment receipt was declined by Ohati Admin. Reason: " . $notes . ". Please log into your dashboard to re-upload."
                        );
                    } catch (Exception $mailEx) {}

                    echo json_encode(['success' => true]);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            echo json_encode(['success' => false, 'message' => 'Campaign not found']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Fetch promotions with search and filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql_base = "FROM advertisements a 
        JOIN vendors v ON a.vendor_id = v.id 
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql_base .= " AND (a.title LIKE ? OR v.name LIKE ? OR a.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== '') {
    $sql_base .= " AND a.status = ?";
    $params[] = $status;
}

// Count total
$count_query = "SELECT COUNT(*) " . $sql_base;
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));

$sql = "SELECT a.*, v.name as vendor_name, v.logo as vendor_logo " . $sql_base . " ORDER BY a.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ads = $stmt->fetchAll();

// Calculations for Stats Section
$total_campaigns = $pdo->query("SELECT COUNT(*) FROM advertisements")->fetchColumn() ?: 0;
$active_campaigns = $pdo->query("SELECT COUNT(*) FROM advertisements WHERE status = 'active'")->fetchColumn() ?: 0;
$total_revenue = $pdo->query("SELECT SUM(cost) FROM advertisements")->fetchColumn() ?: 0.0;
$total_impressions = $pdo->query("SELECT SUM(impressions) FROM advertisements")->fetchColumn() ?: 0;
$total_clicks = $pdo->query("SELECT SUM(clicks) FROM advertisements")->fetchColumn() ?: 0;
$ctr = $total_impressions > 0 ? ($total_clicks / $total_impressions) * 100 : 0.0;

$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Promotions & Campaigns</title>
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
        .admin-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        .pagination-buttons {
            display: flex;
            gap: 6px;
        }
        .pagination-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #E4E7ED;
            background: #fff;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pagination-btn:hover:not(.disabled) {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .pagination-btn.active {
            background: var(--accent);
            color: var(--primary-dark);
            border-color: var(--accent);
            cursor: default;
        }
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            color: var(--gray-400);
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
        .clickable-badge {
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .clickable-badge:hover {
            opacity: 0.8;
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
            <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
            <div class="admin-topbar-title">Promotions & Advertising</div>
            <div class="admin-user-pill">
                <i class="fa-solid fa-user-shield"></i>
                <span>Admin</span>
            </div>
        </header>

        <!-- Admin Content -->
        <div class="admin-content">
            <!-- Stats Blocks -->
            <div class="admin-stat-grid" style="display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:30px;">
                <div class="card" style="padding:20px;">
                    <div style="font-size:0.8rem; color:var(--gray-500); text-transform:uppercase; font-weight:700;">Active / Total</div>
                    <div style="font-size:1.8rem; font-weight:800; color:var(--primary); margin:8px 0;"><?= $active_campaigns ?> <span style="font-size:1.1rem; color:var(--gray-400);">/ <?= $total_campaigns ?></span></div>
                    <div style="font-size:0.75rem; color:var(--gray-500);">Live active campaigns</div>
                </div>
                <div class="card" style="padding:20px;">
                    <div style="font-size:0.8rem; color:var(--gray-500); text-transform:uppercase; font-weight:700;">Total Revenue</div>
                    <div style="font-size:1.8rem; font-weight:800; color:var(--teal); margin:8px 0;">GH₵ <?= number_format($total_revenue, 2) ?></div>
                    <div style="font-size:0.75rem; color:var(--gray-500);">Ad fees collected</div>
                </div>
                <div class="card" style="padding:20px;">
                    <div style="font-size:0.8rem; color:var(--gray-500); text-transform:uppercase; font-weight:700;">Performance</div>
                    <div style="font-size:1.8rem; font-weight:800; color:var(--primary); margin:8px 0;"><?= number_format($total_impressions) ?> <span style="font-size:1.1rem; color:var(--gray-400);">Views</span></div>
                    <div style="font-size:0.75rem; color:var(--gray-500);"><?= number_format($total_clicks) ?> clicks recorded</div>
                </div>
                <div class="card" style="padding:20px;">
                    <div style="font-size:0.8rem; color:var(--gray-500); text-transform:uppercase; font-weight:700;">Avg CTR</div>
                    <div style="font-size:1.8rem; font-weight:800; color:var(--rose); margin:8px 0;"><?= number_format($ctr, 2) ?>%</div>
                    <div style="font-size:0.75rem; color:var(--gray-500);">Click-Through Rate</div>
                </div>
            </div>

            <!-- Filters & Actions -->
            <div class="card" style="padding:20px; margin-bottom:30px;">
                <form method="GET" action="promotions.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <input type="text" name="search" class="form-input" placeholder="Search title or vendor..." value="<?= htmlspecialchars($search) ?>" style="margin:0; padding:10px 14px; flex:1; min-width:200px;">
                    
                    <select name="status" class="form-input" style="margin:0; padding:10px 14px; width:auto; min-width:150px;">
                        <option value="">All Statuses</option>
                        <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="paused" <?= ($status === 'paused') ? 'selected' : '' ?>>Paused</option>
                        <option value="pending" <?= ($status === 'pending') ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="expired" <?= ($status === 'expired') ? 'selected' : '' ?>>Expired</option>
                    </select>

                    <button type="submit" class="btn" style="padding:10px 20px;">Filter</button>
                    <?php if ($search !== '' || $status !== ''): ?>
                        <a href="promotions.php" class="btn btn-outline" style="padding:10px 20px; text-decoration:none; text-align:center;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table Card -->
            <div class="card" style="padding:0; overflow:hidden;">
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Ad Details</th>
                                <th>Vendor</th>
                                <th>Duration & Cost</th>
                                <th>Target Criteria</th>
                                <th>Analytics</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ads)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:40px; color:var(--gray-500);">No promotions match the filter criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ads as $a): ?>
                                    <tr id="row-<?= $a['id'] ?>">
                                        <td>
                                            <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($a['title']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--gray-500); max-width:260px;"><?= htmlspecialchars($a['description']) ?></div>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <img src="<?= htmlspecialchars($a['vendor_logo'] ?: '../img/logo black transparent small.png') ?>" style="width:28px; height:28px; border-radius:50%; object-fit:cover; border:1px solid rgba(0,0,0,0.1);">
                                                <span style="font-size:0.8rem; font-weight:600;"><?= htmlspecialchars($a['vendor_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size:0.8rem; font-weight:600;"><?= $a['duration_days'] ?> Days</div>
                                            <div style="font-size:0.85rem; color:var(--teal); font-weight:700;">GH₵ <?= number_format($a['cost'], 2) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-size:0.75rem;"><span style="color:var(--gray-400);">Loc:</span> <?= htmlspecialchars($a['target_location']) ?></div>
                                            <div style="font-size:0.75rem;"><span style="color:var(--gray-400);">Cat:</span> <?= htmlspecialchars($a['target_category']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-size:0.75rem; font-weight:600;"><i class="fa-solid fa-eye" style="margin-right:4px;"></i> <?= number_format($a['impressions']) ?></div>
                                            <div style="font-size:0.75rem; font-weight:600; color:var(--gray-500);"><i class="fa-solid fa-arrow-pointer" style="margin-right:4px;"></i> <?= number_format($a['clicks']) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $cls = 'status-pending';
                                            $act = htmlspecialchars($a['status']);
                                            if ($act === 'active') $cls = 'status-confirmed';
                                            elseif ($act === 'paused') $cls = 'status-pending';
                                            elseif ($act === 'pending_approval') $cls = 'status-pending';
                                            elseif ($act === 'rejected') $cls = 'status-cancelled';
                                            elseif ($act === 'expired') $cls = 'status-cancelled';
                                            ?>
                                            <span id="status-badge-<?= $a['id'] ?>" class="booking-status <?= $cls ?> clickable-badge" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; text-transform:capitalize;" onclick="toggleStatus(<?= $a['id'] ?>)">
                                                <?= $act ?>
                                            </span>
                                            <div style="font-size:0.65rem; color:var(--gray-400); margin-top:4px;">Ends: <?= substr($a['end_date'], 0, 10) ?></div>
                                        </td>
                                        <td style="text-align:right;">
                                            <div style="display:flex; justify-content:flex-end; gap:6px;">
                                                <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; font-weight:700;" onclick='viewPromotionDetails(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Full Promotion Specification"><i class="fa-solid fa-eye"></i> Details</button>
                                                <?php if (($a['payment_method'] ?? 'paystack') === 'manual' && !empty($a['receipt_url'])): ?>
                                                    <button class="btn btn-primary btn-sm" style="padding:6px 10px; font-size:0.75rem; background:#D4AF37; border-color:#D4AF37;" onclick="viewReceipt(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['title'])) ?>', '<?= htmlspecialchars(addslashes($a['receipt_url'])) ?>', '<?= htmlspecialchars(addslashes($a['payment_ref'])) ?>', '<?= htmlspecialchars(addslashes($a['payment_date'])) ?>', '<?= htmlspecialchars(addslashes($a['payment_notes'] ?? '')) ?>')" title="Review Receipt">
                                                        <i class="fa-solid fa-receipt"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem;" onclick="toggleStatus(<?= $a['id'] ?>)" title="Toggle Campaign Status">
                                                    <i class="fa-solid fa-power-off"></i>
                                                </button>
                                                <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="deleteAd(<?= $a['id'] ?>)" title="Archive & Delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination UI -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container" style="padding:15px 20px;">
                        <div>
                            Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_items) ?> of <?= $total_items ?> promotions
                        </div>
                        <div class="pagination-buttons">
                            <!-- Prev button -->
                            <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="pagination-btn <?= $page == 1 ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                            
                            <!-- Page numbers -->
                            <?php 
                            $start_range = max(1, $page - 2);
                            $end_range = min($total_pages, $page + 2);
                            if ($start_range > 1) {
                                echo '<a href="?page=1&search='.urlencode($search).'&status='.urlencode($status).'" class="pagination-btn">1</a>';
                                if ($start_range > 2) echo '<span style="padding:6px;">...</span>';
                            }
                            for ($i = $start_range; $i <= $end_range; $i++) {
                                $active_cls = $i == $page ? 'active' : '';
                                echo '<a href="?page='.$i.'&search='.urlencode($search).'&status='.urlencode($status).'" class="pagination-btn '.$active_cls.'">'.$i.'</a>';
                            }
                            if ($end_range < $total_pages) {
                                if ($end_range < $total_pages - 1) echo '<span style="padding:6px;">...</span>';
                                echo '<a href="?page='.$total_pages.'&search='.urlencode($search).'&status='.urlencode($status).'" class="pagination-btn">'.$total_pages.'</a>';
                            }
                            ?>

                            <!-- Next button -->
                            <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="pagination-btn <?= $page == $total_pages ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar(open) {
            const sidebar = document.getElementById('adminSidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
        }

        function toggleStatus(adId) {
            fetch('promotions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ad_id: adId, action: 'toggle_status' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('status-badge-' + adId);
                    if (badge) {
                        badge.textContent = data.new_status;
                        if (data.new_status === 'active') {
                            badge.className = 'booking-status status-confirmed clickable-badge';
                        } else if (data.new_status === 'paused') {
                            badge.className = 'booking-status status-pending clickable-badge';
                        } else {
                            badge.className = 'booking-status status-cancelled clickable-badge';
                        }
                    }
                }
            });
        }

        function deleteAd(adId) {
            if (!confirm('Are you sure you want to archive and delete this advertisement?')) return;
            fetch('promotions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ad_id: adId, action: 'delete' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('row-' + adId);
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                } else {
                    alert(data.message || 'Failed to delete campaign');
                }
            });
        }
    </script>

    <!-- Receipt Review Modal -->
    <div id="receiptModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div class="card" style="width:100%; max-width:480px; padding:24px; margin:16px; position:relative; max-height:90vh; overflow-y:auto; border-radius:12px;">
            <button onclick="closeReceiptModal()" style="position:absolute; right:16px; top:16px; background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--gray-400);"><i class="fa-solid fa-xmark"></i></button>
            <h3 style="margin-bottom:16px; font-family:'Fraunces', serif; color:var(--primary);" id="modalAdTitle">Review Payment Receipt</h3>
            
            <div style="margin-bottom:16px; text-align:center;">
                <img id="modalReceiptImg" src="" style="max-width:100%; max-height:280px; object-fit:contain; border-radius:8px; border:1px solid var(--gray-200); background:#fcfcfc;" alt="No receipt image uploaded">
            </div>

            <div style="font-size:0.85rem; line-height:1.6; margin-bottom:20px; color:var(--gray-700);">
                <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--gray-100); padding:6px 0;">
                    <strong style="color:var(--gray-500);">Transaction Reference:</strong>
                    <span id="modalReceiptRef" style="font-weight:600;"></span>
                </div>
                <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--gray-100); padding:6px 0;">
                    <strong style="color:var(--gray-500);">Payment Date:</strong>
                    <span id="modalReceiptDate"></span>
                </div>
                <div style="padding:10px 0;">
                    <strong style="color:var(--gray-500); display:block; margin-bottom:4px;">Payment Notes:</strong>
                    <p id="modalReceiptNotes" style="margin:0; background:var(--gray-50); padding:10px; border-radius:8px; font-size:0.8rem; font-style:italic; border-left:3px solid var(--accent);"></p>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
                    <div>
                        <label style="color:var(--gray-500); font-weight:700; display:block; margin-bottom:4px; font-size:0.75rem;">Ad Placement Location</label>
                        <select id="modalPlacement" class="form-input" style="width:100%; margin:0; font-size:0.8rem;">
                            <option value="home_top_banner">Home Top Banner</option>
                            <option value="home_popup">Home Modal Pop-up</option>
                            <option value="search_top_banner">Search Top Banner</option>
                            <option value="category_sponsored_badge">Category Sponsored Badge</option>
                            <option value="vendor_detail_banner">Vendor Detail Banner</option>
                        </select>
                    </div>
                    <div>
                        <label style="color:var(--gray-500); font-weight:700; display:block; margin-bottom:4px; font-size:0.75rem;">Expiration Date & Time</label>
                        <input type="datetime-local" id="modalEndDate" class="form-input" style="width:100%; margin:0; font-size:0.8rem;">
                    </div>
                    <div>
                        <label style="color:var(--gray-500); font-weight:700; display:block; margin-bottom:4px; font-size:0.75rem;">Max Views Limit (0=unlimited)</label>
                        <input type="number" id="modalMaxViews" class="form-input" style="width:100%; margin:0; font-size:0.8rem;" value="0" min="0">
                    </div>
                    <div>
                        <label style="color:var(--gray-500); font-weight:700; display:block; margin-bottom:4px; font-size:0.75rem;">Max Pop-ups Limit (0=unlimited)</label>
                        <input type="number" id="modalMaxPopups" class="form-input" style="width:100%; margin:0; font-size:0.8rem;" value="0" min="0">
                    </div>
                </div>

                <div style="padding-top:10px;">
                    <label style="color:var(--gray-500); font-weight:700; display:block; margin-bottom:4px; font-size:0.75rem;">Administrator Review Notes</label>
                    <textarea id="adminReviewNotes" class="form-input" style="width:100%; min-height:60px; margin:0;" placeholder="Add approval/rejection details or comments..."></textarea>
                </div>
            </div>

            <input type="hidden" id="modalAdId">

            <div style="display:flex; gap:12px;">
                <button class="btn btn-outline btn-full" style="color:var(--rose); border-color:rgba(244,63,94,0.3);" onclick="submitReceiptReview('rejected')"><i class="fa-solid fa-circle-xmark"></i> Reject Payment</button>
                <button class="btn btn-primary btn-full" onclick="submitReceiptReview('active')"><i class="fa-solid fa-circle-check"></i> Approve & Activate</button>
            </div>
        </div>
    </div>

    <script>
        function viewReceipt(adId, title, receiptUrl, ref, date, notes, placement, endDate, maxViews, maxPopups) {
            document.getElementById('modalAdId').value = adId;
            document.getElementById('modalAdTitle').textContent = 'Review: ' + title;
            let src = receiptUrl;
            if (src && !src.startsWith('data:') && !src.startsWith('http')) {
                src = '../' + src;
            }
            document.getElementById('modalReceiptImg').src = src || '../img/ads/default.jpg';
            document.getElementById('modalReceiptRef').textContent = ref || 'N/A';
            document.getElementById('modalReceiptDate').textContent = date || 'N/A';
            document.getElementById('modalReceiptNotes').textContent = notes || 'No notes provided.';
            document.getElementById('adminReviewNotes').value = '';
            
            if (placement) document.getElementById('modalPlacement').value = placement;
            if (maxViews !== undefined) document.getElementById('modalMaxViews').value = maxViews || 0;
            if (maxPopups !== undefined) document.getElementById('modalMaxPopups').value = maxPopups || 0;
            
            document.getElementById('receiptModal').style.display = 'flex';
        }

        function closeReceiptModal() {
            document.getElementById('receiptModal').style.display = 'none';
        }

        function submitReceiptReview(status) {
            const adId = document.getElementById('modalAdId').value;
            const notes = document.getElementById('adminReviewNotes').value.trim();
            const placement = document.getElementById('modalPlacement').value;
            const endDate = document.getElementById('modalEndDate').value;
            const maxViews = document.getElementById('modalMaxViews').value;
            const maxPopups = document.getElementById('modalMaxPopups').value;
            const action = status === 'active' ? 'approve' : 'reject';

            fetch('promotions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    ad_id: adId, 
                    action: action, 
                    admin_notes: notes,
                    placement: placement,
                    end_date: endDate,
                    max_views: maxViews,
                    max_popups: maxPopups
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeReceiptModal();
                    location.reload();
                } else {
                    alert(data.message || 'Error updating status');
                }
            });
        }

        function viewPromotionDetails(a) {
            const content = document.getElementById('promotionDetailsContent');
            if (!content) return;

            content.innerHTML = `
                <div style="text-align:center; margin-bottom:16px;">
                    ${a.image_url ? `<img src="../${a.image_url}" style="width:100%; max-height:140px; object-fit:cover; border-radius:10px; border:1px solid #E5E7EB; margin-bottom:10px;">` : ''}
                    <h4 style="margin:0; font-size:1.1rem; font-weight:800; color:var(--primary);">${a.title}</h4>
                    <div style="font-size:0.78rem; color:var(--gray-600); margin-top:2px;">${a.description || ''}</div>
                    <span class="booking-status ${a.status === 'active' ? 'status-confirmed' : 'status-pending'}" style="font-size:0.65rem; padding:4px 10px; border-radius:20px; text-transform:uppercase; font-weight:700; display:inline-block; margin-top:6px;">${a.status}</span>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.8rem; background:var(--gray-50); padding:14px; border-radius:12px; border:1px solid #E5E7EB; margin-bottom:16px;">
                    <div><strong>Campaign ID:</strong> #${a.id}</div>
                    <div><strong>Vendor Name:</strong> ${a.vendor_name || 'N/A'}</div>
                    <div><strong>Campaign Cost:</strong> <span style="font-weight:800; color:var(--primary);">GH₵ ${parseFloat(a.cost || 0).toFixed(2)}</span></div>
                    <div><strong>Duration:</strong> ${a.duration_days} Days</div>
                    <div><strong>Target Location:</strong> ${a.target_location || 'All'}</div>
                    <div><strong>Target Category:</strong> ${a.target_category || 'All'}</div>
                    <div><strong>Total Impressions:</strong> 👁️ ${numberWithCommas(a.impressions || 0)}</div>
                    <div><strong>Total Clicks:</strong> 🎯 ${numberWithCommas(a.clicks || 0)}</div>
                    <div><strong>CTA Button Text:</strong> ${a.cta_text || 'Learn More'}</div>
                    <div><strong>Destination Link:</strong> ${a.destination_url || 'Vendor Profile'}</div>
                    <div><strong>Start Date:</strong> ${a.start_date || 'N/A'}</div>
                    <div><strong>End Date:</strong> ${a.end_date || 'N/A'}</div>
                    <div style="grid-column: span 2;"><strong>Payment Method & Ref:</strong> ${(a.payment_method || 'paystack').toUpperCase()} (${a.payment_ref || 'N/A'})</div>
                </div>

                <button class="btn btn-outline btn-full" onclick="closePromotionDetailsModal()" style="font-weight:700;">Close</button>
            `;

            document.getElementById('promotionDetailsModal').style.display = 'flex';
        }

        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function closePromotionDetailsModal() {
            document.getElementById('promotionDetailsModal').style.display = 'none';
        }
    </script>

    <!-- Promotion Details Modal -->
    <div id="promotionDetailsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:560px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E5E7EB; padding-bottom:12px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">
                    <i class="fa-solid fa-rectangle-ad" style="color:var(--accent);"></i> Ad Campaign Specification
                </h3>
                <button onclick="closePromotionDetailsModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--gray-500);">&times;</button>
            </div>
            <div id="promotionDetailsContent"></div>
        </div>
    </div>
</body>
</html>
