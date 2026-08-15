<?php
// admin/vendors.php - Ohati Admin Vendor Management
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Handle AJAX actions (toggle active status, toggle verification, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['vendor_id'] ?? 0);
    $action = $input['action'] ?? '';
    
    if ($vid > 0) {
        if ($action === 'update_vendor_tier' || $action === 'toggle_active' || $action === 'toggle_premium') {
            $stmt = $pdo->prepare("SELECT is_active, premium, verification_badge, name, email, phone FROM vendors WHERE id = ?");
            $stmt->execute([$vid]);
            $vRow = $stmt->fetch();

            $is_active = isset($input['is_active']) ? intval($input['is_active']) : ($action === 'toggle_active' ? (($vRow['is_active'] ?? 0) ? 0 : 1) : intval($vRow['is_active'] ?? 1));
            $premium = isset($input['premium']) ? intval($input['premium']) : ($action === 'toggle_premium' ? (($vRow['premium'] ?? 0) ? 0 : 1) : intval($vRow['premium'] ?? 0));
            $badge = trim($input['badge'] ?? ($premium ? 'gold' : 'blue'));
            $reason = trim($input['reason'] ?? '');

            $pdo->prepare("UPDATE vendors SET is_active = ?, premium = ?, verification_badge = ? WHERE id = ?")
                ->execute([$is_active, $premium, $badge, $vid]);

            try {
                if ($vRow && (!empty($vRow['email']) || !empty($vRow['phone']))) {
                    require_once __DIR__ . '/../mail_helper.php';
                    $subject = "Vendor Listing Update: " . $vRow['name'];
                    $details = "Hello " . htmlspecialchars($vRow['name']) . ",\n\nYour vendor profile settings have been updated:\n• Listing Status: " . ($is_active ? 'Active' : 'Suspended') . "\n• Vendor Tier: " . ($premium ? '👑 Premium Vendor (100 Portfolio Photos)' : 'Standard Listing') . "\n• Trust Badge: " . strtoupper($badge);
                    if ($reason) {
                        $details .= "\n\nAdmin Note: " . $reason;
                    }
                    send_dual_notification($vRow['phone'] ?? '', $vRow['email'] ?? '', $subject, $details);
                }
            } catch (Exception $mailEx) {}

            echo json_encode(['success' => true, 'is_active' => $is_active, 'premium' => $premium, 'badge' => $badge]);
            exit;
        } elseif ($action === 'send_reset_password') {
            $uid = intval($input['user_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $uRow = $stmt->fetch();
            
            if (!$uRow || (empty($uRow['email']) && empty($uRow['phone']))) {
                echo json_encode(['success' => false, 'message' => 'Vendor user account does not have a valid email or phone number registered.']);
                exit;
            }

            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));

            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(100) DEFAULT ''");
            } catch (Exception $e1) {}
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN reset_expires DATETIME DEFAULT NULL");
            } catch (Exception $e2) {}

            try {
                $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")->execute([$token, $expires, $uid]);
            } catch (Exception $tokenEx) {}

            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? 80) == 443
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            $protocol = $is_https ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $parent_dir = dirname(dirname($script));
            $parent_dir = str_replace('\\', '/', $parent_dir);
            if ($parent_dir === '/' || $parent_dir === '.' || $parent_dir === '\\') {
                $parent_dir = '';
            }

            $reset_link = $protocol . $host . $parent_dir . "/reset_password.php?token=" . $token;

            require_once __DIR__ . '/../mail_helper.php';
            require_once __DIR__ . '/../sms_helper.php';

            $reset_details = "
            <div style='font-family:sans-serif; color:#1F2937;'>
                <p>Hello <strong>" . htmlspecialchars($uRow['name']) . "</strong>,</p>
                <p>An administrator requested a password reset for your Ohati account. Click the button below to reset your password:</p>
                <p><a href='{$reset_link}' style='color:#E05A47; font-weight:700;'>{$reset_link}</a></p>
                <p style='font-size:12px; color:#6B7280;'>This reset link is valid for 2 hours.</p>
            </div>";

            try {
                if (!empty($uRow['email'])) {
                    send_admin_notification_email($uRow['email'], $uRow['name'] ?? 'Vendor', "Password Reset Requested", "SECURITY RESET", "warning", $reset_details, $reset_link, "Reset Password Now");
                }
                if (!empty($uRow['phone'])) {
                    send_dual_notification($uRow['phone'], '', "Password Reset", "Your Ohati password reset link: " . $reset_link);
                }
            } catch (Exception $mailEx) {}

            echo json_encode([
                'success' => true,
                'message' => "Password reset email & SMS dispatched successfully to " . ($uRow['email'] ?: $uRow['phone']) . ".",
                'reset_link' => $reset_link
            ]);
            exit;
        } elseif ($action === 'toggle_premium') {
            $stmt = $pdo->prepare("SELECT premium, name, email FROM vendors WHERE id = ?");
            $stmt->execute([$vid]);
            $vRow = $stmt->fetch();
            $current = $vRow['premium'] ?? 0;
            $new_status = ($current == 1) ? 0 : 1;
            
            $pdo->prepare("UPDATE vendors SET premium = ?, verification_badge = ? WHERE id = ?")->execute([$new_status, $new_status ? 'gold' : 'blue', $vid]);

            try {
                if ($vRow && !empty($vRow['email'])) {
                    require_once __DIR__ . '/../mail_helper.php';
                    $title = $new_status ? "Premium Trust Vendor Badge Granted!" : "Premium Vendor Status Updated";
                    $badge_label = $new_status ? "Gold (Premium Trust)" : "Standard Verified";
                    $badge_type = $new_status ? "gold" : "info";
                    $details = $new_status 
                        ? "Congratulations <strong>" . htmlspecialchars($vRow['name']) . "</strong>! Ohati Admin has granted your business account <strong>Gold (Premium Trust)</strong> badge status. Your profile will now enjoy priority placement in customer searches." 
                        : "Your account premium badge status for <strong>" . htmlspecialchars($vRow['name']) . "</strong> has been updated to Standard Verified.";
                    send_admin_notification_email($vRow['email'], $vRow['name'], $title, $badge_label, $badge_type, $details);
                }
            } catch (Exception $mailEx) {}

            echo json_encode(['success' => true, 'new_status' => $new_status]);
            exit;
        } elseif ($action === 'toggle_verify') {
            $stmt = $pdo->prepare("SELECT verified, name, email FROM vendors WHERE id = ?");
            $stmt->execute([$vid]);
            $vRow = $stmt->fetch();
            $current = $vRow['verified'] ?? 0;
            $new_status = ($current == 1) ? 0 : 1;
            $status_str = ($new_status == 1) ? 'verified' : 'rejected';
            $badge_str = ($new_status == 1) ? 'blue' : 'grey';
            
            $pdo->prepare("UPDATE vendors SET verified = ?, verification_status = ?, verification_badge = ? WHERE id = ?")->execute([$new_status, $status_str, $badge_str, $vid]);

            try {
                if ($vRow && !empty($vRow['email'])) {
                    require_once __DIR__ . '/../mail_helper.php';
                    $title = $new_status ? "Identity Verification Approved" : "Verification Status Revised";
                    $badge_label = $new_status ? "Verified Vendor (Blue Badge)" : "Unverified Status";
                    $badge_type = $new_status ? "blue" : "warning";
                    $details = $new_status 
                        ? "Your business <strong>" . htmlspecialchars($vRow['name']) . "</strong> has been granted official Blue Identity Verification status on Ohati." 
                        : "Verification status for <strong>" . htmlspecialchars($vRow['name']) . "</strong> has been reset. Please review your credentials in dashboard settings.";
                    send_admin_notification_email($vRow['email'], $vRow['name'], $title, $badge_label, $badge_type, $details);
                }
            } catch (Exception $mailEx) {}

            echo json_encode(['success' => true, 'new_status' => $new_status]);
            exit;
        } elseif ($action === 'delete') {
            // Soft delete vendor: archive first
            $sel = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
            $sel->execute([$vid]);
            $v = $sel->fetch(PDO::FETCH_ASSOC);
            if ($v) {
                $record_data = json_encode($v);
                $stmt = $pdo->prepare("INSERT INTO deleted_records (record_type, record_id, record_data) VALUES ('vendor', ?, ?)");
                $stmt->execute([$vid, $record_data]);

                $pdo->prepare("DELETE FROM vendors WHERE id = ?")->execute([$vid]);
                echo json_encode(['success' => true]);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Vendor not found']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT DISTINCT category FROM vendors ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Fetch vendors with search and filters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? '');

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql_base = "FROM vendors v LEFT JOIN users u ON v.user_id = u.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql_base .= " AND (v.name LIKE ? OR v.location LIKE ? OR v.phone LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category !== '') {
    $sql_base .= " AND v.category = ?";
    $params[] = $category;
}

if ($status !== '') {
    if ($status === 'verified') {
        $sql_base .= " AND v.verified = 1";
    } elseif ($status === 'pending') {
        $sql_base .= " AND v.verified = 0 AND v.verification_status = 'pending'";
    } elseif ($status === 'active') {
        $sql_base .= " AND v.is_active = 1";
    } elseif ($status === 'inactive') {
        $sql_base .= " AND v.is_active = 0";
    }
}

// Count total
$count_query = "SELECT COUNT(*) " . $sql_base;
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));

$sql = "SELECT v.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.kyc_status, u.kyc_id_front AS kyc_document, u.created_at AS user_created_at " . $sql_base . " ORDER BY v.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendors = $stmt->fetchAll();

$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE (kyc_status = 'pending_verification' OR kyc_status = 'pending') AND (kyc_id_front != '' OR kyc_selfie != '' OR kyc_id_back != '')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Vendor Management</title>
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
                <h1 class="admin-page-title">Vendor Management</h1>
            </div>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-circle-user" style="font-size:1.2rem; color:var(--accent);"></i>
                <span>System Administrator</span>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="admin-content">

            <!-- Filter Controls -->
            <div class="card mb-20" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:16px;">
                <form method="GET" action="vendors.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <div style="flex:2; min-width:200px;">
                        <input type="text" name="search" class="form-input" placeholder="Search by name, location, phone..." value="<?= htmlspecialchars($search) ?>" style="margin:0; padding:10px 14px;">
                    </div>
                    <div style="flex:1; min-width:150px;">
                        <select name="category" class="form-select" style="margin:0; padding:10px 14px; width:100%;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1; min-width:150px;">
                        <select name="status" class="form-select" style="margin:0; padding:10px 14px; width:100%;">
                            <option value="">All Statuses</option>
                            <option value="verified" <?= ($status === 'verified') ? 'selected' : '' ?>>Verified Only</option>
                            <option value="pending" <?= ($status === 'pending') ? 'selected' : '' ?>>Verification Pending</option>
                            <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>Active Only</option>
                            <option value="inactive" <?= ($status === 'inactive') ? 'selected' : '' ?>>Inactive Only</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:10px 20px; font-weight:700;"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                    <a href="vendors.php" class="btn btn-outline" style="padding:10px 20px; font-weight:700;">Reset</a>
                </form>
            </div>

            <!-- Vendors Table -->
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Business Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Phone</th>
                            <th>KYC Verification</th>
                            <th>Account Type</th>
                            <th>Active Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vendors)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px; color:var(--gray-400);">No vendors matched the search criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vendors as $v): ?>
                                <tr id="row-<?= $v['id'] ?>">
                                    <td>
                                        <img src="../<?= htmlspecialchars($v['logo'] ?: 'img/logo black transparent small.png') ?>" alt="logo" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid #E4E7ED;">
                                    </td>
                                    <td>
                                        <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($v['name']) ?></div>
                                        <div style="font-size:0.7rem; color:var(--gray-500);">Exp: <?= htmlspecialchars($v['experience']) ?> years • Rating: ★ <?= number_format($v['rating'], 1) ?></div>
                                    </td>
                                    <td><span style="font-size:0.78rem; font-weight:600; background:var(--gray-100); color:var(--gray-700); padding:4px 8px; border-radius:4px;"><?= htmlspecialchars($v['category']) ?></span></td>
                                    <td><i class="fa-solid fa-location-dot" style="color:var(--gray-400); font-size:0.75rem;"></i> <?= htmlspecialchars($v['location']) ?></td>
                                    <td><?= htmlspecialchars($v['phone'] ?: 'N/A') ?></td>
                                    <td>
                                        <span id="verify-badge-<?= $v['id'] ?>" class="booking-status <?= ($v['verified'] == 1) ? 'status-confirmed' : 'status-pending' ?>" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; cursor:pointer;" onclick="toggleVerify(<?= $v['id'] ?>)">
                                            <?= ($v['verified'] == 1) ? 'Verified' : 'Unverified' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span id="premium-badge-<?= $v['id'] ?>" class="booking-status <?= ($v['premium'] == 1) ? 'status-confirmed' : 'status-pending' ?>" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; cursor:pointer; <?= ($v['premium'] == 1) ? 'background:#D4AF37; color:#fff;' : '' ?>" onclick="openVendorActionModal(<?= $v['id'] ?>)">
                                            <?= ($v['premium'] == 1) ? 'Premium' : 'Standard' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span id="active-badge-<?= $v['id'] ?>" class="booking-status <?= ($v['is_active'] == 1) ? 'status-confirmed' : 'status-cancelled' ?>" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; cursor:pointer;" onclick="openVendorActionModal(<?= $v['id'] ?>)">
                                            <?= ($v['is_active'] == 1) ? 'Active' : 'Suspended' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <script id="vendor-data-<?= $v['id'] ?>" type="application/json"><?= json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
                                            <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; font-weight:700;" onclick="viewVendorDetails(<?= $v['id'] ?>)" title="View Full Details"><i class="fa-solid fa-eye"></i> Details</button>
                                            <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; font-weight:700; color:var(--primary);" onclick="sendPasswordReset(<?= $v['user_id'] ?: 0 ?>, '<?= htmlspecialchars($v['email'] ?: $v['phone'] ?: $v['user_email'] ?: $v['user_phone'] ?: '', ENT_QUOTES) ?>')" title="Send Password Reset Link"><i class="fa-solid fa-key"></i> Reset Link</button>
                                            <button class="btn btn-outline btn-sm" style="padding:6px; font-size:0.75rem;" onclick="openVendorActionModal(<?= $v['id'] ?>)" title="Manage Vendor Tier & Status"><i class="fa-solid fa-gears"></i> Action</button>
                                            <button class="btn btn-outline btn-sm" style="padding:6px; font-size:0.75rem; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="deleteVendor(<?= $v['id'] ?>)" title="Delete Vendor"><i class="fa-solid fa-trash"></i></button>
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
                <div class="pagination-container">
                    <div>
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_items) ?> of <?= $total_items ?> vendors
                    </div>
                    <div class="pagination-buttons">
                        <!-- Prev button -->
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>" class="pagination-btn <?= $page == 1 ? 'disabled' : '' ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                        
                        <!-- Page numbers -->
                        <?php 
                        $start_range = max(1, $page - 2);
                        $end_range = min($total_pages, $page + 2);
                        if ($start_range > 1) {
                            echo '<a href="?page=1&search='.urlencode($search).'&category='.urlencode($category).'&status='.urlencode($status).'" class="pagination-btn">1</a>';
                            if ($start_range > 2) echo '<span style="padding:6px;">...</span>';
                        }
                        for ($i = $start_range; $i <= $end_range; $i++) {
                            $active_cls = $i == $page ? 'active' : '';
                            echo '<a href="?page='.$i.'&search='.urlencode($search).'&category='.urlencode($category).'&status='.urlencode($status).'" class="pagination-btn '.$active_cls.'">'.$i.'</a>';
                        }
                        if ($end_range < $total_pages) {
                            if ($end_range < $total_pages - 1) echo '<span style="padding:6px;">...</span>';
                            echo '<a href="?page='.$total_pages.'&search='.urlencode($search).'&category='.urlencode($category).'&status='.urlencode($status).'" class="pagination-btn">'.$total_pages.'</a>';
                        }
                        ?>

                        <!-- Next button -->
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>" class="pagination-btn <?= $page == $total_pages ? 'disabled' : '' ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Scripts -->
    <script>
        function toggleSidebar(open) {
            const sidebar = document.querySelector('.admin-sidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
        }

        function toggleActive(vendorId) {
            fetch('vendors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vendor_id: vendorId, action: 'toggle_active' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('active-badge-' + vendorId);
                    if (badge) {
                        if (data.new_status == 1) {
                            badge.className = 'booking-status status-confirmed';
                            badge.textContent = 'Active';
                        } else {
                            badge.className = 'booking-status status-cancelled';
                            badge.textContent = 'Suspended';
                        }
                    }
                }
            });
        }

        function toggleVerify(vendorId) {
            fetch('vendors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vendor_id: vendorId, action: 'toggle_verify' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('verify-badge-' + vendorId);
                    if (badge) {
                        if (data.new_status == 1) {
                            badge.className = 'booking-status status-confirmed';
                            badge.textContent = 'Verified';
                        } else {
                            badge.className = 'booking-status status-pending';
                            badge.textContent = 'Unverified';
                        }
                    }
                }
            });
        }

        function togglePremium(vendorId) {
            fetch('vendors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vendor_id: vendorId, action: 'toggle_premium' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('premium-badge-' + vendorId);
                    if (badge) {
                        if (data.new_status == 1) {
                            badge.className = 'booking-status status-confirmed';
                            badge.style.background = '#D4AF37';
                            badge.style.color = '#fff';
                            badge.textContent = 'Premium';
                        } else {
                            badge.className = 'booking-status status-pending';
                            badge.style.background = '';
                            badge.style.color = '';
                            badge.textContent = 'Standard';
                        }
                    }
                }
            });
        }

        function deleteVendor(vendorId) {
            if (!confirm('Are you sure you want to permanently delete this vendor? This will remove all their catalog details.')) return;
            
            fetch('vendors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vendor_id: vendorId, action: 'delete' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('row-' + vendorId);
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                }
            });
        }

        function sendPasswordReset(userId, contactInfo) {
            if (!userId || userId <= 0) {
                alert('This vendor profile is not linked to a user login account.');
                return;
            }
            if (!confirm('Send password reset link via Email & SMS to ' + contactInfo + '?')) return;

            fetch('vendors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, action: 'send_reset_password' })
            })
            .then(res => res.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch(e) {
                    throw new Error(text || 'Server returned invalid response');
                }
            }))
            .then(data => {
                if (data.success) {
                    alert('Success! ' + data.message + '\n\nGenerated Link:\n' + data.reset_link);
                } else {
                    alert('Error: ' + (data.message || 'Failed to dispatch reset link.'));
                }
            }).catch(err => alert('Reset Link Dispatch Notice: ' + err.message));
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function viewVendorDetails(vendorInput) {
            let v = vendorInput;
            if (typeof vendorInput === 'number' || typeof vendorInput === 'string') {
                const scriptTag = document.getElementById('vendor-data-' + vendorInput);
                if (scriptTag) {
                    try {
                        v = JSON.parse(scriptTag.textContent);
                    } catch(e) {
                        alert('Could not parse vendor data.'); return;
                    }
                }
            }
            if (!v || typeof v !== 'object') {
                alert('Vendor details not found.'); return;
            }

            const modal = document.getElementById('vendorDetailsModal');
            const content = document.getElementById('vendorDetailsContent');
            if (!content || !modal) {
                alert('Vendor details modal element not found on page.');
                return;
            }

            const email = v.email || v.user_email || 'Not Provided';
            const phone = v.phone || v.user_phone || 'Not Provided';
            const kycStatus = v.kyc_status || (v.verified == 1 ? 'verified' : 'not_started');
            const kycBadge = v.verification_badge || 'blue';
            const startingPrice = parseFloat(v.starting_price || 0) > 0 ? ('GH₵ ' + parseFloat(v.starting_price).toFixed(2)) : 'Custom Quotes';
            const logoUrl = v.logo || 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=400';

            let kycBadgeHtml = `<span class="booking-status status-pending" style="font-size:0.7rem; padding:3px 8px; border-radius:12px;">KYC: ${escapeHtml(kycStatus)}</span>`;
            if (kycStatus === 'verified' || v.verified == 1) {
                kycBadgeHtml = `<span class="booking-status status-confirmed" style="font-size:0.7rem; padding:3px 8px; border-radius:12px; background:#D4AF37; color:#fff;">🏅 ${escapeHtml(kycBadge.toUpperCase())} Verified</span>`;
            }

            const safeName = escapeHtml(v.name || 'Vendor Profile');
            const safeCat = escapeHtml(v.category || 'General Service');
            const safeLoc = escapeHtml(v.location || 'Accra, Ghana');
            const safeDesc = escapeHtml(v.description || 'No detailed business description provided yet.');
            const safeBank = escapeHtml(v.bank_name || 'Not Configured');
            const safeAcc = escapeHtml(v.account_number || 'Not Set');

            content.innerHTML = `
                <div style="text-align:center; margin-bottom:16px;">
                    <img src="${logoUrl}" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid var(--primary); margin-bottom:8px; box-shadow:0 4px 10px rgba(0,0,0,0.15);">
                    <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">${safeName}</h3>
                    <div style="font-size:0.8rem; color:var(--gray-600); margin-top:2px;"><strong>${safeCat}</strong> &bull; ${safeLoc}</div>
                    <div style="margin-top:8px; display:flex; gap:6px; justify-content:center; flex-wrap:wrap;">
                        <span class="booking-status ${v.premium == 1 ? 'status-confirmed' : 'status-pending'}" style="font-size:0.7rem; padding:3px 8px; border-radius:12px;">${v.premium == 1 ? '👑 Premium Tier' : 'Standard Tier'}</span>
                        ${kycBadgeHtml}
                        <span class="booking-status ${v.is_active == 1 ? 'status-confirmed' : 'status-cancelled'}" style="font-size:0.7rem; padding:3px 8px; border-radius:12px;">${v.is_active == 1 ? '🟢 Listing Active' : '🔴 Suspended'}</span>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:0.82rem; background:var(--gray-50); padding:16px; border-radius:12px; border:1px solid #E5E7EB; margin-bottom:16px;">
                    <div><strong>Vendor Record ID:</strong> #${v.id}</div>
                    <div><strong>Linked User Account:</strong> #${v.user_id || 'N/A'}</div>
                    <div><strong>Email Address:</strong> <a href="mailto:${escapeHtml(email)}" style="color:var(--accent); text-decoration:none;">${escapeHtml(email)}</a></div>
                    <div><strong>Contact Phone:</strong> <a href="tel:${escapeHtml(phone)}" style="color:var(--accent); text-decoration:none;">${escapeHtml(phone)}</a></div>
                    <div><strong>Starting Price:</strong> <span style="font-weight:800; color:#16a34a;">${startingPrice}</span></div>
                    <div><strong>Years Experience:</strong> ${v.experience || 0} Years</div>
                    <div><strong>Rating Score:</strong> ⭐ ${v.rating || '5.0'} (${v.reviews_count || 0} reviews)</div>
                    <div><strong>Profile Views:</strong> 👁️ ${v.views_count || 0} views</div>
                    <div><strong>Followers Count:</strong> 👥 ${v.followers_count || 0} followers</div>
                    <div><strong>Insurance Status:</strong> ${v.has_insurance == 1 ? '🛡️ Insured' : 'None'}</div>
                    <div><strong>Payout Bank/MoMo:</strong> ${safeBank}</div>
                    <div><strong>Account Number:</strong> <code>${safeAcc}</code></div>
                    <div style="grid-column: span 2; border-top:1px solid #E2E8F0; padding-top:8px; margin-top:4px;">
                        <strong>Business Description / Bio:</strong>
                        <p style="margin:4px 0 0 0; color:var(--gray-700); line-height:1.5;">${safeDesc}</p>
                    </div>
                    ${v.kyc_document ? `<div style="grid-column: span 2; background:#FEF3C7; padding:8px 12px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fa-solid fa-file-contract" style="color:#B45309;"></i> <strong>KYC Ghana Card Document Attached</strong></span>
                        <a href="../${v.kyc_document}" target="_blank" class="btn btn-sm btn-outline" style="font-size:0.75rem; background:#fff;">View Document</a>
                    </div>` : ''}
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button class="btn btn-primary" onclick="sendPasswordReset(${v.user_id || 0}, '${escapeHtml(email !== 'Not Provided' ? email : phone)}')" style="flex:1; font-weight:700; font-size:0.8rem;">
                        <i class="fa-solid fa-paper-plane"></i> Reset Password Link
                    </button>
                    <button class="btn btn-outline" onclick="openVendorActionModal(${v.id})" style="font-weight:700; font-size:0.8rem;">
                        <i class="fa-solid fa-gears"></i> Manage Status
                    </button>
                    <button class="btn btn-outline" onclick="closeVendorDetailsModal()" style="font-weight:700; font-size:0.8rem;">
                        Close
                    </button>
                </div>
            `;

            modal.style.display = 'flex';
        }

        function closeVendorDetailsModal() {
            const modal = document.getElementById('vendorDetailsModal');
            if (modal) modal.style.display = 'none';
        }

        function openVendorActionModal(vendorId, isPremium, isActive, badge) {
            let v = null;
            const scriptTag = document.getElementById('vendor-data-' + vendorId);
            if (scriptTag) {
                try { v = JSON.parse(scriptTag.textContent); } catch(e) {}
            }
            if (v) {
                isPremium = v.premium;
                isActive = v.is_active;
                badge = v.verification_badge || 'blue';
            }
            document.getElementById('va_vendor_id').value = vendorId;
            document.getElementById('va_premium_select').value = (isPremium == 1 || isPremium === true) ? 1 : 0;
            document.getElementById('va_active_select').value = (isActive == 1 || isActive === true) ? 1 : 0;
            document.getElementById('va_badge_select').value = badge || 'blue';
            document.getElementById('va_reason_text').value = '';
            const modal = document.getElementById('vendorActionModal');
            if (modal) modal.style.display = 'flex';
        }
        function closeVendorActionModal() {
            document.getElementById('vendorActionModal').style.display = 'none';
        }
        function submitVendorAction() {
            const vendorId = document.getElementById('va_vendor_id').value;
            const isPremium = document.getElementById('va_premium_select').value;
            const isActive = document.getElementById('va_active_select').value;
            const badge = document.getElementById('va_badge_select').value;
            const reason = document.getElementById('va_reason_text').value.trim();

            fetch('vendors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    vendor_id: vendorId,
                    action: 'update_vendor_tier',
                    premium: parseInt(isPremium),
                    is_active: parseInt(isActive),
                    badge: badge,
                    reason: reason
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Vendor tier & status updated successfully. Notification sent via Email & SMS!');
                    closeVendorActionModal();
                    location.reload();
                } else {
                    alert(data.message || 'Error updating vendor status');
                }
            });
        }
    </script>

    <!-- Vendor Details Modal -->
    <div id="vendorDetailsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:560px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E5E7EB; padding-bottom:12px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">
                    <i class="fa-solid fa-briefcase" style="color:var(--accent);"></i> Vendor Business Profile Details
                </h3>
                <button onclick="closeVendorDetailsModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--gray-500);">&times;</button>
            </div>
            <div id="vendorDetailsContent"></div>
        </div>
    </div>

    <!-- Vendor Action Modal -->
    <div id="vendorActionModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:500px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
            <h3 style="margin:0 0 14px 0; font-family:'Fraunces',serif; display:flex; align-items:center; gap:8px; color:var(--primary);">
                <i class="fa-solid fa-briefcase" style="color:var(--accent);"></i> Manage Vendor Tier & Status
            </h3>
            <input type="hidden" id="va_vendor_id">
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:700; color:var(--gray-600); margin-bottom:4px; display:block;">Listing Active Status</label>
                    <select id="va_active_select" class="form-select" style="width:100%; padding:10px; margin:0;">
                        <option value="1">🟢 Active (Visible in Searches)</option>
                        <option value="0">🔴 Suspended (Hidden from Public)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:700; color:var(--gray-600); margin-bottom:4px; display:block;">Vendor Subscription Tier</label>
                    <select id="va_premium_select" class="form-select" style="width:100%; padding:10px; margin:0;">
                        <option value="1">👑 Premium Vendor (Max Placement + Up to 100 Photos)</option>
                        <option value="0">⚪ Standard Vendor (Basic Listing)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:700; color:var(--gray-600); margin-bottom:4px; display:block;">Trust Badge Tier</label>
                    <select id="va_badge_select" class="form-select" style="width:100%; padding:10px; margin:0;">
                        <option value="gold">🥇 Gold Badge (Verified & Premium Trust)</option>
                        <option value="blue">🔵 Blue Badge (ID Verified)</option>
                        <option value="none">⚪ No Badge</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:700; color:var(--gray-600); margin-bottom:4px; display:block;">Admin Justification Note (Sent via Email & SMS)</label>
                    <textarea id="va_reason_text" class="form-textarea" rows="3" placeholder="Enter reason or notification notes for the vendor..." style="width:100%; padding:10px; margin:0;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
                    <button type="button" class="btn btn-outline" onclick="closeVendorActionModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitVendorAction()"><i class="fa-solid fa-paper-plane"></i> Update & Notify Vendor</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
