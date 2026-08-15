<?php
// admin/kyc_history.php — Comprehensive KYC History & Restoration Inspector
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_guard.php';

$page_title = "Complete KYC History & Restoration Inspector";
$success_msg = '';
$error_msg = '';

// Handle Status Updates / Restoration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $uid = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action']; // restore_pending, approve, reject, delete_kyc

    if ($uid > 0) {
        $now = date('Y-m-d H:i:s');
        if ($action === 'restore_pending') {
            $pdo->prepare("UPDATE users SET kyc_status = 'pending_verification' WHERE id = ?")->execute([$uid]);
            $pdo->prepare("UPDATE vendors SET verification_status = 'pending', verification_badge = 'grey', verified = 0 WHERE user_id = ?")->execute([$uid]);
            $success_msg = "Restored User #{$uid} KYC status back to Pending Verification queue.";
        } elseif ($action === 'approve') {
            $pdo->prepare("UPDATE users SET kyc_status = 'verified', kyc_reviewed_at = ? WHERE id = ?")->execute([$now, $uid]);
            $pdo->prepare("UPDATE vendors SET verification_status = 'verified', verification_badge = 'blue', verified = 1 WHERE user_id = ?")->execute([$uid]);
            $success_msg = "Approved User #{$uid} KYC verification (Blue Badge awarded).";
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE users SET kyc_status = 'rejected', kyc_reviewed_at = ? WHERE id = ?")->execute([$now, $uid]);
            $pdo->prepare("UPDATE vendors SET verification_status = 'rejected', verification_badge = 'grey', verified = 0 WHERE user_id = ?")->execute([$uid]);
            $success_msg = "Set User #{$uid} KYC status to Rejected.";
        }
    }
}

// Fetch filter
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];

if ($filter === 'pending') {
    $where[] = "(u.kyc_status = 'pending_verification' OR u.kyc_status = 'pending') AND (u.kyc_id_front != '' OR u.kyc_selfie != '' OR u.kyc_id_back != '')";
} elseif ($filter === 'verified') {
    $where[] = "u.kyc_status = 'verified'";
} elseif ($filter === 'rejected') {
    $where[] = "u.kyc_status = 'rejected'";
} elseif ($filter === 'has_docs') {
    $where[] = "(u.kyc_id_front != '' OR u.kyc_selfie != '' OR u.kyc_id_back != '')";
}

if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where);

// Fetch all users with KYC history
$stmt = $pdo->prepare("
    SELECT u.*, v.name as biz_name, v.category, v.id as vendor_id
    FROM users u
    LEFT JOIN vendors v ON u.id = v.user_id
    WHERE $where_sql
    ORDER BY u.id DESC
");
$stmt->execute($params);
$kyc_records = $stmt->fetchAll();

// Statistics
$stat_total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stat_has_docs = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_id_front != '' OR kyc_selfie != '' OR kyc_id_back != ''")->fetchColumn();
$stat_pending = $pdo->query("SELECT COUNT(*) FROM users WHERE (kyc_status = 'pending_verification' OR kyc_status = 'pending') AND (kyc_id_front != '' OR kyc_selfie != '' OR kyc_id_back != '')")->fetchColumn();
$stat_verified = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'verified'")->fetchColumn();
$stat_rejected = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'rejected'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Ohati Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#1B2B4B; --accent:#F2A735; --bg:#F8FAFC; --card:#FFFFFF; --text:#1E293B; --gray:#64748B; --border:#E2E8F0; --success:#10B981; --error:#EF4444; --warning:#F59E0B; }
        * { box-sizing:border-box; margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        body { background:var(--bg); color:var(--text); padding:24px; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .title { font-size:1.5rem; font-weight:800; color:var(--primary); }
        .subtitle { font-size:0.85rem; color:var(--gray); }
        .card { background:var(--card); border-radius:16px; padding:24px; border:1px solid var(--border); box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:20px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:14px; margin-bottom:24px; }
        .stat-box { background:var(--bg); border:1px solid var(--border); padding:16px; border-radius:12px; text-align:center; }
        .stat-val { font-size:1.5rem; font-weight:800; color:var(--primary); }
        .stat-lbl { font-size:0.72rem; color:var(--gray); text-transform:uppercase; font-weight:700; margin-top:2px; }
        .filter-bar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; align-items:center; }
        .input-field { padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:0.85rem; outline:none; }
        .btn { padding:8px 16px; background:var(--primary); color:#fff; border:none; border-radius:8px; font-weight:700; font-size:0.82rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
        :root { 
            --primary: #111827; 
            --accent: #E05A47; 
            --bg: #F3F4F6; 
            --card: #FFFFFF; 
            --text: #1F2937; 
            --gray: #6B7280; 
            --border: #E5E7EB; 
            --success: #10B981; 
            --warning: #F59E0B;
            --error: #EF4444;
            --sidebar-width: 260px;
        }
        * { box-sizing:border-box; margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        body { background:var(--bg); color:var(--text); min-height:100vh; }
        
        .admin-layout { display:flex; min-height:100vh; }
        .admin-main { flex:1; margin-left:var(--sidebar-width); padding:24px; width:calc(100% - var(--sidebar-width)); }
        .admin-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid var(--border); }
        .admin-menu-toggle { display:none; background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text); }
        .admin-page-title { font-size:1.4rem; font-weight:800; color:var(--primary); }

        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .title { font-size:1.5rem; font-weight:800; color:var(--primary); }
        .subtitle { font-size:0.85rem; color:var(--gray); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-bottom:24px; }
        .stat-box { background:var(--card); padding:16px; border-radius:12px; border:1px solid var(--border); box-shadow:0 1px 3px rgba(0,0,0,0.05); }
        .stat-val { font-size:1.5rem; font-weight:800; color:var(--primary); }
        .stat-lbl { font-size:0.75rem; color:var(--gray); text-transform:uppercase; font-weight:700; margin-top:4px; }
        .card { background:var(--card); border-radius:12px; padding:20px; border:1px solid var(--border); box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:20px; }
        .filter-bar { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .input-field, .select-field { padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:0.85rem; outline:none; }
        .input-field:focus, .select-field:focus { border-color:var(--accent); }
        .btn { padding:8px 16px; background:var(--primary); color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
        .btn-sm { padding:5px 10px; font-size:0.75rem; border-radius:6px; }
        .btn-accent { background:var(--accent); color:#fff; }
        .btn-success { background:var(--success); color:#fff; }
        .btn-warning { background:var(--warning); color:#fff; }
        .btn-error { background:var(--error); color:#fff; }
        .table-responsive { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:0.82rem; text-align:left; }
        th { background:var(--bg); padding:10px 12px; border-bottom:2px solid var(--border); color:var(--gray); font-weight:700; text-transform:uppercase; font-size:0.7rem; }
        td { padding:12px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:hover { background:rgba(0,0,0,0.02); }
        .badge { padding:4px 8px; border-radius:6px; font-size:0.7rem; font-weight:700; text-transform:uppercase; display:inline-block; }
        .badge-verified { background:#D1FAE5; color:#065F46; }
        .badge-pending { background:#FEF3C7; color:#92400E; }
        .badge-rejected { background:#FEE2E2; color:#991B1B; }
        .badge-none { background:#F3F4F6; color:#6B7280; }
        .thumb-img { width:48px; height:36px; object-fit:cover; border-radius:6px; border:1px solid var(--border); cursor:pointer; }
        .msg-box { background:#D1FAE5; border:1px solid #A7F3D0; color:#065F46; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:0.85rem; font-weight:600; }

        @media (max-width:768px) {
            .admin-main { margin-left:0; width:100%; padding:16px; }
            .admin-menu-toggle { display:block; }
        }
    </style>
</head>
<body class="admin-layout">

    <!-- Admin Sidebar Component -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Admin Main Body -->
    <main class="admin-main">
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1 class="admin-page-title"><i class="fa-solid fa-id-card" style="color:var(--accent);"></i> Complete KYC History & Restoration Inspector</h1>
                    <p style="font-size:0.8rem; color:var(--gray);">Audit trail of all KYC verification records, submitted ID documents, and restoration controls</p>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="kyc.php" class="btn"><i class="fa-solid fa-clock-rotate-left"></i> Approval Queue</a>
            </div>
        </header>

    <?php if (!empty($success_msg)): ?>
        <div class="msg-box"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="stat-box">
            <div class="stat-val"><?= $stat_total ?></div>
            <div class="stat-lbl">Total Users</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" style="color:var(--accent);"><?= $stat_has_docs ?></div>
            <div class="stat-lbl">KYC History Entries</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" style="color:var(--warning);"><?= $stat_pending ?></div>
            <div class="stat-lbl">Pending Queue</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" style="color:var(--success);"><?= $stat_verified ?></div>
            <div class="stat-lbl">Verified (Blue Badge)</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" style="color:var(--error);"><?= $stat_rejected ?></div>
            <div class="stat-lbl">Rejected</div>
        </div>
    </div>

    <div class="card">
        <form method="GET" class="filter-bar">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email or phone..." class="input-field" style="flex:1; min-width:200px;">
            <select name="filter" class="input-field" onchange="this.form.submit()">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Users (<?= $stat_total ?>)</option>
                <option value="has_docs" <?= $filter === 'has_docs' ? 'selected' : '' ?>>Has KYC History (<?= $stat_has_docs ?>)</option>
                <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending Verification (<?= $stat_pending ?>)</option>
                <option value="verified" <?= $filter === 'verified' ? 'selected' : '' ?>>Verified (<?= $stat_verified ?>)</option>
                <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>Rejected (<?= $stat_rejected ?>)</option>
            </select>
            <button type="submit" class="btn"><i class="fa-solid fa-filter"></i> Search</button>
            <?php if ($search !== '' || $filter !== 'all'): ?>
                <a href="kyc_history.php" class="btn" style="background:var(--gray);"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>User Name & Contact</th>
                        <th>Account Role</th>
                        <th>ID Document Type</th>
                        <th>ID Front Image</th>
                        <th>Selfie Image</th>
                        <th>Submission Time</th>
                        <th>Current Status</th>
                        <th>Restoration & Admin Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kyc_records)): ?>
                        <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--gray);">No KYC history records match your search or filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($kyc_records as $r): ?>
                            <?php
                                $status = $r['kyc_status'] ?: 'not_started';
                                $badge_class = 'badge-none';
                                if ($status === 'verified') $badge_class = 'badge-verified';
                                elseif ($status === 'pending_verification') $badge_class = 'badge-pending';
                                elseif ($status === 'rejected') $badge_class = 'badge-rejected';
                            ?>
                            <tr>
                                <td><strong>#<?= $r['id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['name']) ?></strong>
                                    <?php if (!empty($r['biz_name'])): ?>
                                        <div style="font-size:0.72rem; color:var(--accent); font-weight:700;"><?= htmlspecialchars($r['biz_name']) ?></div>
                                    <?php endif; ?>
                                    <div style="font-size:0.72rem; color:var(--gray);"><?= htmlspecialchars($r['email'] ?: $r['phone']) ?></div>
                                </td>
                                <td><span class="badge" style="background:#E2E8F0; color:#1E293B;"><?= htmlspecialchars(strtoupper($r['role'])) ?></span></td>
                                <td><strong><?= htmlspecialchars($r['kyc_id_type'] ?: 'Ghana Card') ?></strong></td>
                                <td>
                                    <?php if (!empty($r['kyc_id_front'])): ?>
                                        <img src="../<?= htmlspecialchars($r['kyc_id_front']) ?>" class="thumb-img" title="Click to View ID Front" onclick="window.open(this.src)">
                                    <?php else: ?>
                                        <span style="color:var(--gray); font-size:0.72rem;">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($r['kyc_selfie'])): ?>
                                        <img src="../<?= htmlspecialchars($r['kyc_selfie']) ?>" class="thumb-img" title="Click to View Verification Selfie" onclick="window.open(this.src)">
                                    <?php else: ?>
                                        <span style="color:var(--gray); font-size:0.72rem;">No Selfie</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap; color:var(--gray); font-size:0.75rem;"><?= htmlspecialchars($r['kyc_submitted_at'] ?: $r['created_at']) ?></td>
                                <td>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                        <form method="POST" style="display:inline;" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='<i class=\'fa-solid fa-spinner fa-spin\'></i> Restoring...';">
                                            <input type="hidden" name="user_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="restore_pending">
                                            <button type="submit" class="btn btn-sm btn-warning" title="Restore back to Pending Approval Queue">
                                                <i class="fa-solid fa-rotate-left"></i> Restore Queue
                                            </button>
                                        </form>

                                        <?php if ($status !== 'verified'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='<i class=\'fa-solid fa-spinner fa-spin\'></i> Approving...';">
                                                <input type="hidden" name="user_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success" title="Approve KYC (Award Blue Badge)">
                                                    <i class="fa-solid fa-check"></i> Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($status !== 'rejected'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='<i class=\'fa-solid fa-spinner fa-spin\'></i> Rejecting...';">
                                                <input type="hidden" name="user_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-sm btn-error" title="Reject KYC">
                                                    <i class="fa-solid fa-xmark"></i> Reject
                                                </button>
                                            </form>
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
</body>
</html>
