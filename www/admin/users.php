<?php
// admin/users.php - Ohati Admin User Management
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Handle AJAX actions (toggle active status, delete user)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $uid = intval($input['user_id'] ?? 0);
    $action = $input['action'] ?? '';
    
    if ($uid > 0) {
        if ($action === 'update_user_status' || $action === 'toggle_active') {
            $stmt = $pdo->prepare("SELECT is_active, name, email, phone, role FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $uRow = $stmt->fetch();

            $current = intval($uRow['is_active'] ?? 1);
            $target_status = isset($input['status']) ? intval($input['status']) : (($current == 1) ? 0 : 1);
            $reason = trim($input['reason'] ?? '');
            
            // Update user active status
            $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$target_status, $uid]);

            // Also sync associated vendor profile visibility
            $v_active = ($target_status === 1) ? 1 : 0;
            $pdo->prepare("UPDATE vendors SET is_active = ? WHERE user_id = ?")->execute([$v_active, $uid]);

            $status_label = ($target_status === 1) ? 'Active' : (($target_status === 2) ? 'Banned' : 'Suspended');

            // Log activity audit
            try {
                $admin_id = $_SESSION['admin_user']['id'] ?? 1;
                $admin_name = $_SESSION['admin_user']['username'] ?? 'Admin';
                log_activity($pdo, 'Account Status Changed', 'User', $uid, $admin_id, 'admin', $admin_name, 0, ($current == 1 ? 'Active' : 'Suspended'), $status_label, $reason ?: 'Status updated via Admin Console');
            } catch (Exception $eAudit) {}

            try {
                if ($uRow && (!empty($uRow['email']) || !empty($uRow['phone']))) {
                    require_once __DIR__ . '/../mail_helper.php';
                    $subject = "Ohati Account Status Update: " . $status_label;
                    $details = "Hello " . htmlspecialchars($uRow['name']) . ",\n\nYour Ohati user account status has been updated to: " . strtoupper($status_label) . ".";
                    if ($reason) {
                        $details .= "\n\nAdmin Reason / Note: " . $reason;
                    }
                    send_dual_notification($uRow['phone'] ?? '', $uRow['email'] ?? '', $subject, $details);
                }
            } catch (Exception $mailEx) {}

            echo json_encode(['success' => true, 'new_status' => $target_status, 'status_label' => $status_label]);
            exit;
        } elseif ($action === 'send_reset_password') {
            $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $uRow = $stmt->fetch();
            
            if (!$uRow || (empty($uRow['email']) && empty($uRow['phone']))) {
                echo json_encode(['success' => false, 'message' => 'User does not have a valid email or phone number registered.']);
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
                    send_admin_notification_email($uRow['email'], $uRow['name'] ?? 'User', "Password Reset Requested", "SECURITY RESET", "warning", $reset_details, $reset_link, "Reset Password Now");
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
        } elseif ($action === 'delete') {
            // Soft delete user account: archive first
            $sel = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $sel->execute([$uid]);
            $u = $sel->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $record_data = json_encode($u);
                $stmt = $pdo->prepare("INSERT INTO deleted_records (record_type, record_id, record_data) VALUES ('user', ?, ?)");
                $stmt->execute([$uid, $record_data]);
                
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
                echo json_encode(['success' => true]);
                exit;
            }
        } elseif ($action === 'broadcast_notification') {
            $target = $input['target'] ?? 'all';
            $title = trim($input['title'] ?? '');
            $message = trim($input['message'] ?? '');
            if (!$title || !$message) {
                echo json_encode(['success' => false, 'message' => 'Title and message are required']);
                exit;
            }
            if ($target === 'vendors') {
                $stmt = $pdo->query("SELECT id FROM users WHERE role = 'vendor'");
            } elseif ($target === 'users') {
                $stmt = $pdo->query("SELECT id FROM users WHERE role = 'user' OR role = 'customer' OR role IS NULL OR role = ''");
            } else {
                $stmt = $pdo->query("SELECT id FROM users");
            }
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $ins = $pdo->prepare("INSERT INTO notifications (user_id, title, body) VALUES (?, ?, ?)");
            $count = 0;
            foreach ($users as $uid) {
                $ins->execute([$uid, $title, $message]);
                $count++;
            }
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Fetch users with search and role filters
$search = trim($_GET['search'] ?? '');
$role = trim($_GET['role'] ?? '');
$status = trim($_GET['status'] ?? '');

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql_base = "FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql_base .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role !== '') {
    $sql_base .= " AND role = ?";
    $params[] = $role;
}

if ($status !== '') {
    if ($status === 'active') {
        $sql_base .= " AND is_active = 1";
    } elseif ($status === 'suspended') {
        $sql_base .= " AND is_active = 0";
    }
}

// Count total
$count_query = "SELECT COUNT(*) " . $sql_base;
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));

$sql = "SELECT * " . $sql_base . " ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE (kyc_status = 'pending_verification' OR kyc_status = 'pending') AND (kyc_id_front != '' OR kyc_selfie != '' OR kyc_id_back != '')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - User Management</title>
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
                <h1 class="admin-page-title">User Management</h1>
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
                <form method="GET" action="users.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <div style="flex:2; min-width:200px;">
                        <input type="text" name="search" class="form-input" placeholder="Search users by name, email, phone..." value="<?= htmlspecialchars($search) ?>" style="margin:0; padding:10px 14px;">
                    </div>
                    <div style="flex:1; min-width:150px;">
                        <select name="role" class="form-select" style="margin:0; padding:10px 14px; width:100%;">
                            <option value="">All Roles</option>
                            <option value="customer" <?= ($role === 'customer') ? 'selected' : '' ?>>Customer</option>
                            <option value="vendor" <?= ($role === 'vendor') ? 'selected' : '' ?>>Vendor</option>
                            <option value="admin" <?= ($role === 'admin') ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div style="flex:1; min-width:150px;">
                        <select name="status" class="form-select" style="margin:0; padding:10px 14px; width:100%;">
                            <option value="">All Statuses</option>
                            <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= ($status === 'suspended') ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:10px 20px; font-weight:700;"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                    <a href="users.php" class="btn btn-outline" style="padding:10px 20px; font-weight:700;">Reset</a>
                    <button type="button" onclick="openBroadcastModal()" class="btn" style="padding:10px 20px; font-weight:700; background:var(--accent); color:#0F1923; border:none; margin-left:auto; cursor:pointer;"><i class="fa-solid fa-bullhorn"></i> Send Broadcast</button>
                </form>
            </div>

            <!-- Users Table -->
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Avatar</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Phone Number</th>
                            <th>Role</th>
                            <th>Active Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px; color:var(--gray-400);">No users found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr id="row-<?= $u['id'] ?>">
                                    <td>
                                        <img src="<?= htmlspecialchars($u['avatar'] ?: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>") ?>" alt="avatar" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid #E4E7ED;">
                                    </td>
                                    <td>
                                        <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($u['name']) ?></div>
                                        <div style="font-size:0.7rem; color:var(--gray-500);">KYC: <?= htmlspecialchars(str_replace('_', ' ', $u['kyc_status'])) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($u['email'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($u['phone'] ?: 'N/A') ?></td>
                                    <td>
                                        <?php 
                                        $roleClass = 'status-pending';
                                        if ($u['role'] === 'vendor') $roleClass = 'status-confirmed';
                                        elseif ($u['role'] === 'admin') $roleClass = 'status-cancelled'; // Reddish/Gold theme
                                        ?>
                                        <span class="booking-status <?= $roleClass ?>" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; text-transform:capitalize;">
                                            <?= htmlspecialchars($u['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span id="active-badge-<?= $u['id'] ?>" class="booking-status <?= ($u['is_active'] == 1) ? 'status-confirmed' : 'status-cancelled' ?>" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; cursor:pointer;" onclick="openStatusActionModal(<?= $u['id'] ?>)">
                                            <?= ($u['is_active'] == 1) ? 'Active' : 'Suspended' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime($u['created_at']))) ?></td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <script id="user-data-<?= $u['id'] ?>" type="application/json"><?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
                                            <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; font-weight:700;" onclick="viewUserDetails(<?= $u['id'] ?>)" title="View Full Details"><i class="fa-solid fa-eye"></i> Details</button>
                                            <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; font-weight:700; color:var(--primary);" onclick="sendPasswordReset(<?= $u['id'] ?>, '<?= htmlspecialchars($u['email'] ?: $u['phone'], ENT_QUOTES) ?>')" title="Send Password Reset Link"><i class="fa-solid fa-key"></i> Reset Link</button>
                                            <button class="btn btn-outline btn-sm" style="padding:6px; font-size:0.75rem;" onclick="openStatusActionModal(<?= $u['id'] ?>)" title="Manage Status"><i class="fa-solid fa-user-gear"></i> Action</button>
                                            <button class="btn btn-outline btn-sm" style="padding:6px; font-size:0.75rem; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="deleteUser(<?= $u['id'] ?>)" title="Delete Account"><i class="fa-solid fa-trash"></i></button>
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
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_items) ?> of <?= $total_items ?> users
                    </div>
                    <div class="pagination-buttons">
                        <!-- Prev button -->
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>" class="pagination-btn <?= $page == 1 ? 'disabled' : '' ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                        
                        <!-- Page numbers -->
                        <?php 
                        $start_range = max(1, $page - 2);
                        $end_range = min($total_pages, $page + 2);
                        if ($start_range > 1) {
                            echo '<a href="?page=1&search='.urlencode($search).'&role='.urlencode($role).'&status='.urlencode($status).'" class="pagination-btn">1</a>';
                            if ($start_range > 2) echo '<span style="padding:6px;">...</span>';
                        }
                        for ($i = $start_range; $i <= $end_range; $i++) {
                            $active_cls = $i == $page ? 'active' : '';
                            echo '<a href="?page='.$i.'&search='.urlencode($search).'&role='.urlencode($role).'&status='.urlencode($status).'" class="pagination-btn '.$active_cls.'">'.$i.'</a>';
                        }
                        if ($end_range < $total_pages) {
                            if ($end_range < $total_pages - 1) echo '<span style="padding:6px;">...</span>';
                            echo '<a href="?page='.$total_pages.'&search='.urlencode($search).'&role='.urlencode($role).'&status='.urlencode($status).'" class="pagination-btn">'.$total_pages.'</a>';
                        }
                        ?>

                        <!-- Next button -->
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>" class="pagination-btn <?= $page == $total_pages ? 'disabled' : '' ?>">
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

        function toggleActive(userId) {
            fetch('users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, action: 'toggle_active' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('active-badge-' + userId);
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

        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user account? This cannot be undone.')) return;
            
            fetch('users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, action: 'delete' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('row-' + userId);
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                }
            });
        }

        function openBroadcastModal() {
            document.getElementById('broadcastModal').style.display = 'flex';
        }
        function closeBroadcastModal() {
            document.getElementById('broadcastModal').style.display = 'none';
        }
        function submitBroadcast() {
            const target = document.getElementById('broadcast_target').value;
            const title = document.getElementById('broadcast_title').value.trim();
            const message = document.getElementById('broadcast_message').value.trim();
            if (!title || !message) {
                alert('Please enter both a title and message.');
                return;
            }
            fetch('users.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'broadcast_notification', target, title, message})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Broadcast dispatched successfully to ' + data.count + ' account(s)!');
                    closeBroadcastModal();
                    document.getElementById('broadcast_title').value = '';
                    document.getElementById('broadcast_message').value = '';
                } else {
                    alert('Error sending broadcast: ' + (data.message || 'Failed'));
                }
            })
            .catch(err => alert('Network error sending broadcast.'));
        }
    </script>

    <!-- Broadcast Notification Modal -->
    <div id="broadcastModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:500px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2); position:relative;">
            <h3 style="margin:0 0 16px 0; font-family:'Fraunces',serif; display:flex; align-items:center; gap:8px; color:var(--primary);">
                <i class="fa-solid fa-bullhorn" style="color:var(--accent);"></i> Send Broadcast Notification
            </h3>
            <div style="display:flex; flex-direction:column; gap:14px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:700; color:var(--gray-600); margin-bottom:4px; display:block;">Target Audience</label>
                    <select id="broadcast_target" class="form-select" style="width:100%; padding:10px; margin:0;">
                        <option value="all">📢 All Users & Vendors</option>
                        <option value="users">👤 Event Hosts / Customers Only</option>
                        <option value="vendors">💼 Vendors / Professionals Only</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:700; color:var(--gray-600); margin-bottom:4px; display:block;">Notification Title</label>
                    <input type="text" id="broadcast_title" class="form-input" placeholder="e.g. Platform Special Announcement" style="width:100%; padding:10px; margin:0;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:700; color:var(--gray-600); margin-bottom:4px; display:block;">Message Body</label>
                    <textarea id="broadcast_message" class="form-textarea" rows="4" placeholder="Enter message text..." style="width:100%; padding:10px; margin:0;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
                    <button type="button" class="btn btn-outline" onclick="closeBroadcastModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitBroadcast()"><i class="fa-solid fa-paper-plane"></i> Dispatch Broadcast</button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:550px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E5E7EB; padding-bottom:12px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">
                    <i class="fa-solid fa-id-card" style="color:var(--accent);"></i> User Profile & Account Details
                </h3>
                <button onclick="closeUserDetailsModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--gray-500);">&times;</button>
            </div>
            <div id="userDetailsContent"></div>
        </div>
    </div>

    <script>
        function sendPasswordReset(userId, contactInfo) {
            if (!confirm('Send password reset link via Email & SMS to ' + contactInfo + '?')) return;

            fetch('users.php', {
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
                    alert('Error: ' + (data.message || 'Failed to dispatch reset email.'));
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

        function viewUserDetails(userInput) {
            let u = userInput;
            if (typeof userInput === 'number' || typeof userInput === 'string') {
                const scriptTag = document.getElementById('user-data-' + userInput);
                if (scriptTag) {
                    try { u = JSON.parse(scriptTag.textContent); } catch(e) {}
                }
            }
            if (!u || typeof u !== 'object') {
                alert('User details record not found.'); return;
            }

            const modal = document.getElementById('userDetailsModal');
            const content = document.getElementById('userDetailsContent');
            if (!content || !modal) {
                alert('User details modal element not found.'); return;
            }

            const safeName = escapeHtml(u.name || 'User Profile');
            const safeEmail = escapeHtml(u.email || 'N/A');
            const safePhone = escapeHtml(u.phone || 'N/A');
            const safeAvatar = u.avatar || "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";

            content.innerHTML = `
                <div style="text-align:center; margin-bottom:16px;">
                    <img src="${safeAvatar}" style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:2px solid var(--primary); margin-bottom:8px;">
                    <h4 style="margin:0; font-size:1.1rem; font-weight:800; color:var(--primary);">${safeName}</h4>
                    <span class="booking-status ${u.role === 'vendor' ? 'status-confirmed' : 'status-pending'}" style="text-transform:uppercase; font-size:0.65rem; padding:4px 10px; border-radius:20px; font-weight:700; margin-top:4px; display:inline-block;">${escapeHtml(u.role || 'customer')}</span>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.8rem; background:var(--gray-50); padding:14px; border-radius:12px; border:1px solid #E5E7EB; margin-bottom:16px;">
                    <div><strong>User ID:</strong> #${u.id}</div>
                    <div><strong>Active Status:</strong> <span style="font-weight:700;" class="${u.is_active == 1 ? 'text-success' : 'text-danger'}">${u.is_active == 1 ? 'Active' : 'Suspended'}</span></div>
                    <div><strong>Email:</strong> ${safeEmail}</div>
                    <div><strong>Phone:</strong> ${safePhone}</div>
                    <div><strong>Date of Birth:</strong> ${escapeHtml(u.dob || 'Not provided')}</div>
                    <div><strong>Gender:</strong> ${escapeHtml(u.gender || 'Not specified')}</div>
                    <div><strong>Location:</strong> ${escapeHtml((u.city || '') + ' ' + (u.state || '') + ' ' + (u.country || 'Ghana'))}</div>
                    <div><strong>KYC Identity:</strong> ${escapeHtml(u.kyc_status || 'not_started')}</div>
                    <div><strong>Referral Code:</strong> <code>${escapeHtml(u.referral_code || 'N/A')}</code></div>
                    <div><strong>Referral Earnings:</strong> GH₵ ${parseFloat(u.referral_balance || 0).toFixed(2)}</div>
                    <div style="grid-column: span 2;"><strong>Joined Date:</strong> ${escapeHtml(u.created_at || 'N/A')}</div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button class="btn btn-primary" onclick="sendPasswordReset(${u.id}, '${safeEmail !== 'N/A' ? safeEmail : safePhone}')" style="flex:1; font-weight:700;">
                        <i class="fa-solid fa-paper-plane"></i> Send Reset Link
                    </button>
                    <button class="btn btn-outline" onclick="closeUserDetailsModal()" style="font-weight:700;">
                        Close
                    </button>
                </div>
            `;

            modal.style.display = 'flex';
        }

        function closeUserDetailsModal() {
            const modal = document.getElementById('userDetailsModal');
            if (modal) modal.style.display = 'none';
        }

        function openStatusActionModal(userId, currentStatus) {
            let u = null;
            const scriptTag = document.getElementById('user-data-' + userId);
            if (scriptTag) {
                try { u = JSON.parse(scriptTag.textContent); } catch(e) {}
            }
            if (u && currentStatus === undefined) {
                currentStatus = u.is_active;
            }
            document.getElementById('sa_user_id').value = userId;
            document.getElementById('sa_status_select').value = (currentStatus !== undefined) ? currentStatus : 1;
            document.getElementById('sa_reason_text').value = '';
            const modal = document.getElementById('statusActionModal');
            if (modal) modal.style.display = 'flex';
        }
        function closeStatusActionModal() {
            const modal = document.getElementById('statusActionModal');
            if (modal) modal.style.display = 'none';
        }
        function submitStatusAction() {
            const userId = document.getElementById('sa_user_id').value;
            const status = document.getElementById('sa_status_select').value;
            const reason = document.getElementById('sa_reason_text').value.trim();

            fetch('users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, action: 'update_user_status', status: parseInt(status), reason: reason })
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
                    alert('Account status updated to ' + data.status_label + '. Notification sent!');
                    closeStatusActionModal();
                    location.reload();
                } else {
                    alert(data.message || 'Error updating user account status');
                }
            }).catch(err => alert('Status Update Notice: ' + err.message));
        }
    </script>

    <!-- Status Action Modal -->
    <div id="statusActionModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:480px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
            <h3 style="margin:0 0 14px 0; font-family:'Fraunces',serif; display:flex; align-items:center; gap:8px; color:var(--primary);">
                <i class="fa-solid fa-user-gear" style="color:var(--accent);"></i> Manage Account Status & Permissions
            </h3>
            <input type="hidden" id="sa_user_id">
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:700; color:var(--gray-600); margin-bottom:4px; display:block;">Select Status Option</label>
                    <select id="sa_status_select" class="form-select" style="width:100%; padding:10px; margin:0;">
                        <option value="1">🟢 Active (Full Platform Access)</option>
                        <option value="0">🟡 Suspended (Temporary Account Lock)</option>
                        <option value="2">🔴 Banned (Permanent Violation Ban)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:700; color:var(--gray-600); margin-bottom:4px; display:block;">Administrative Note (Dispatched via Email & SMS)</label>
                    <textarea id="sa_reason_text" class="form-textarea" rows="3" placeholder="Provide reason or restoration guidance for the user..." style="width:100%; padding:10px; margin:0;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
                    <button type="button" class="btn btn-outline" onclick="closeStatusActionModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitStatusAction()"><i class="fa-solid fa-paper-plane"></i> Apply & Send Notification</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
