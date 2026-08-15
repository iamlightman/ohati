<?php
// admin/jobs.php - Multi-Database Admin Management Console for Event Jobs Marketplace
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Multi-Database Handle Mapping
$db_main = $pdo;                       // ohaticom_1 (Users, Vendors, Bookings, Settings)
$db_jobs = $pdo_jobs ?: $pdo;          // ohaticom_2 (Jobs, Categories, Applications, Shortlists)
$db_comms = $pdo_comms ?: $pdo;        // ohaticom_3 (Notifications, Queues)
$db_payments = $pdo_payments ?: $pdo;  // ohaticom_4 (Payments, Financials)
$db_logs = $pdo_logs ?: $pdo;          // ohaticom_5 (Audit Logs, Job Reports, Analytics, Job Views)

// Helper: Audit Logging (Database 5)
function log_job_admin_action($db_logs, $action, $details) {
    $admin_id = $_SESSION['admin_user']['id'] ?? $_SESSION['user']['id'] ?? 0;
    $admin_name = $_SESSION['admin_user']['name'] ?? $_SESSION['user']['name'] ?? 'System Admin';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
    try {
        $stmt = $db_logs->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$admin_id, $admin_name, $action, $details, $ip, $ua]);
    } catch (Exception $e) {}
}

$msg = ''; $msg_type = 'success';
$tab = $_GET['tab'] ?? 'jobs';

// ── HANDLE CSV EXPORT REQUEST ───────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_type = $_GET['type'] ?? 'jobs';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ohati_event_' . $export_type . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');

    if ($export_type === 'jobs') {
        fputcsv($output, ['ID', 'Title', 'Category', 'Client Name', 'Budget', 'Status', 'Is Urgent', 'Is Featured', 'Applications', 'Created At']);
        $rows = $db_jobs->query("SELECT id, title, category, user_name, budget, status, is_urgent, is_featured, applications_count, created_at FROM jobs ORDER BY id DESC")->fetchAll();
        foreach ($rows as $r) fputcsv($output, $r);
    } elseif ($export_type === 'applications') {
        fputcsv($output, ['ID', 'Job ID', 'Vendor ID', 'Vendor Name', 'Quote Price', 'Timeline', 'Status', 'Applied At']);
        $rows = $db_jobs->query("SELECT id, job_id, vendor_id, vendor_name, price_quote, delivery_timeline, status, created_at FROM job_applications ORDER BY id DESC")->fetchAll();
        foreach ($rows as $r) fputcsv($output, $r);
    } elseif ($export_type === 'hires') {
        fputcsv($output, ['ID', 'Job ID', 'User ID', 'Vendor ID', 'Agreed Price', 'Status', 'Hired At']);
        $rows = $db_jobs->query("SELECT id, job_id, user_id, vendor_id, agreed_price, status, hired_at FROM job_hires ORDER BY id DESC")->fetchAll();
        foreach ($rows as $r) fputcsv($output, $r);
    }
    log_job_admin_action($db_logs, 'Export CSV', "Exported $export_type CSV dataset.");
    exit;
}

// ── HANDLE POST ACTIONS ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Single Job Operations (Database 2)
    if (isset($_POST['job_action'])) {
        $action = $_POST['job_action'];
        $job_id = intval($_POST['job_id'] ?? 0);

        if ($job_id > 0) {
            if ($action === 'feature') {
                $db_jobs->exec("UPDATE jobs SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END WHERE id = $job_id");
                $msg = "Job #$job_id featured status toggled.";
                log_job_admin_action($db_logs, 'Feature Toggle', "Toggled featured status for Job #$job_id.");
            } elseif ($action === 'urgent') {
                $db_jobs->exec("UPDATE jobs SET is_urgent = CASE WHEN is_urgent = 1 THEN 0 ELSE 1 END WHERE id = $job_id");
                $msg = "Job #$job_id urgent badge toggled.";
                log_job_admin_action($db_logs, 'Urgent Toggle', "Toggled urgent badge for Job #$job_id.");
            } elseif ($action === 'pin') {
                $db_jobs->exec("UPDATE jobs SET is_pinned = CASE WHEN is_pinned = 1 THEN 0 ELSE 1 END WHERE id = $job_id");
                $msg = "Job #$job_id pinned status toggled.";
                log_job_admin_action($db_logs, 'Pin Toggle', "Toggled pinned status for Job #$job_id.");
            } elseif ($action === 'lock') {
                $db_jobs->exec("UPDATE jobs SET is_locked = CASE WHEN is_locked = 1 THEN 0 ELSE 1 END WHERE id = $job_id");
                $msg = "Job #$job_id edit lock toggled.";
                log_job_admin_action($db_logs, 'Lock Toggle', "Toggled edit lock for Job #$job_id.");
            } elseif (in_array($action, ['open', 'in_review', 'hiring', 'filled', 'closed', 'cancelled', 'completed', 'suspended', 'archived', 'draft'])) {
                $stmt = $db_jobs->prepare("UPDATE jobs SET status = ? WHERE id = ?");
                $stmt->execute([$action, $job_id]);
                $msg = "Job #$job_id status set to '$action'.";
                log_job_admin_action($db_logs, 'Status Update', "Updated Job #$job_id status to $action.");
            } elseif ($action === 'delete') {
                $db_jobs->exec("DELETE FROM jobs WHERE id = $job_id");
                $msg = "Job #$job_id permanently deleted.";
                log_job_admin_action($db_logs, 'Delete Job', "Permanently deleted Job #$job_id.");
            } elseif ($action === 'save_edit') {
                $title = trim($_POST['title'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $budget = floatval($_POST['budget'] ?? 0);
                $location = trim($_POST['location'] ?? '');
                $status = trim($_POST['status'] ?? 'open');
                $notes = trim($_POST['admin_notes'] ?? '');

                $stmt = $db_jobs->prepare("UPDATE jobs SET title = ?, category = ?, budget = ?, location = ?, status = ?, admin_notes = ? WHERE id = ?");
                $stmt->execute([$title, $category, $budget, $location, $status, $notes, $job_id]);
                $msg = "Job #$job_id details updated successfully.";
                log_job_admin_action($db_logs, 'Edit Job', "Edited details for Job #$job_id.");
            }
        }
    }

    // 2. Bulk Actions (Database 2)
    if (isset($_POST['bulk_action']) && !empty($_POST['selected_jobs'])) {
        $bulk = $_POST['bulk_action'];
        $selected_ids = array_map('intval', $_POST['selected_jobs']);
        $in_clause = implode(',', $selected_ids);

        if ($bulk === 'approve' || $bulk === 'open') {
            $db_jobs->exec("UPDATE jobs SET status = 'open' WHERE id IN ($in_clause)");
            $msg = "Bulk Action: Approved & opened " . count($selected_ids) . " jobs.";
        } elseif ($bulk === 'close') {
            $db_jobs->exec("UPDATE jobs SET status = 'closed' WHERE id IN ($in_clause)");
            $msg = "Bulk Action: Closed " . count($selected_ids) . " jobs.";
        } elseif ($bulk === 'feature') {
            $db_jobs->exec("UPDATE jobs SET is_featured = 1 WHERE id IN ($in_clause)");
            $msg = "Bulk Action: Featured " . count($selected_ids) . " jobs.";
        } elseif ($bulk === 'unfeature') {
            $db_jobs->exec("UPDATE jobs SET is_featured = 0 WHERE id IN ($in_clause)");
            $msg = "Bulk Action: Removed feature status for " . count($selected_ids) . " jobs.";
        } elseif ($bulk === 'urgent') {
            $db_jobs->exec("UPDATE jobs SET is_urgent = 1 WHERE id IN ($in_clause)");
            $msg = "Bulk Action: Marked " . count($selected_ids) . " jobs as urgent.";
        } elseif ($bulk === 'suspend') {
            $db_jobs->exec("UPDATE jobs SET status = 'suspended' WHERE id IN ($in_clause)");
            $msg = "Bulk Action: Suspended " . count($selected_ids) . " jobs.";
        } elseif ($bulk === 'delete') {
            $db_jobs->exec("DELETE FROM jobs WHERE id IN ($in_clause)");
            $msg = "Bulk Action: Deleted " . count($selected_ids) . " jobs.";
        }
        log_job_admin_action($db_logs, 'Bulk Action', "Executed '$bulk' on " . count($selected_ids) . " jobs.");
    }

    // 3. Category Management (Database 2)
    if (isset($_POST['category_action'])) {
        $action = $_POST['category_action'];
        $cat_id = intval($_POST['cat_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-solid fa-briefcase');
        $color = trim($_POST['color_code'] ?? '#1B2B4B');

        if ($action === 'add' && !empty($name)) {
            $stmt = $db_jobs->prepare("INSERT INTO job_categories (name, icon, color_code) VALUES (?, ?, ?)");
            $stmt->execute([$name, $icon, $color]);
            $msg = "Category '$name' added.";
            log_job_admin_action($db_logs, 'Add Category', "Created category '$name'.");
        } elseif ($action === 'toggle' && $cat_id > 0) {
            $db_jobs->exec("UPDATE job_categories SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = $cat_id");
            $msg = "Category status updated.";
        } elseif ($action === 'delete' && $cat_id > 0) {
            $db_jobs->exec("DELETE FROM job_categories WHERE id = $cat_id");
            $msg = "Category deleted.";
            log_job_admin_action($db_logs, 'Delete Category', "Deleted category #$cat_id.");
        }
    }

    // 4. Custom Broadcast Notification (Database 3 & Database 1)
    if (isset($_POST['broadcast_action'])) {
        $recipient_type = $_POST['recipient_type'] ?? 'all';
        $title = trim($_POST['notif_title'] ?? '');
        $body = trim($_POST['notif_body'] ?? '');

        if (!empty($title) && !empty($body)) {
            $target_users = [];
            if ($recipient_type === 'all') {
                $target_users = $db_main->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($recipient_type === 'vendors') {
                $target_users = $db_main->query("SELECT user_id FROM vendors WHERE user_id > 0")->fetchAll(PDO::FETCH_COLUMN);
            }

            $count_sent = 0;
            $n_stmt = $db_comms->prepare("INSERT INTO job_notifications (user_id, type, title, message) VALUES (?, 'admin_announcement', ?, ?)");
            foreach ($target_users as $uid) {
                if ($uid) {
                    $n_stmt->execute([$uid, $title, $body]);
                    $count_sent++;
                }
            }
            $msg = "Broadcast Announcement sent to $count_sent users!";
            log_job_admin_action($db_logs, 'Broadcast Notification', "Sent announcement '$title' to $count_sent users.");
        }
    }

    // 5. Safety Reports (Database 5)
    if (isset($_POST['report_action'])) {
        $rep_id = intval($_POST['report_id'] ?? 0);
        $rep_status = $_POST['report_status'] ?? 'resolved';
        if ($rep_id > 0) {
            $db_logs->prepare("UPDATE job_reports SET status = ? WHERE id = ?")->execute([$rep_status, $rep_id]);
            $msg = "Report #$rep_id status updated to '$rep_status'.";
            log_job_admin_action($db_logs, 'Report Resolved', "Updated report #$rep_id status to $rep_status.");
        }
    }
}

// ── FETCH STATISTICAL METRICS Across Databases ────────────────────────────
$total_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs")->fetchColumn() ?: 0;
$active_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE status = 'open'")->fetchColumn() ?: 0;
$draft_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE status = 'draft'")->fetchColumn() ?: 0;
$closed_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE status = 'closed'")->fetchColumn() ?: 0;
$completed_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE status = 'completed'")->fetchColumn() ?: 0;
$suspended_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE status = 'suspended'")->fetchColumn() ?: 0;
$featured_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE is_featured = 1")->fetchColumn() ?: 0;
$urgent_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE is_urgent = 1")->fetchColumn() ?: 0;

$total_proposals = $db_jobs->query("SELECT COUNT(*) FROM job_applications")->fetchColumn() ?: 0;
$total_hires = $db_jobs->query("SELECT COUNT(*) FROM job_hires")->fetchColumn() ?: 0;
$total_views = $db_jobs->query("SELECT SUM(views_count) FROM jobs")->fetchColumn() ?: 0;

$today_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
$week_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn() ?: 0;
$month_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;

// Filter & Search Params
$q_search = trim($_GET['search'] ?? '');
$q_category = trim($_GET['category'] ?? '');
$q_status = trim($_GET['status'] ?? '');

$where = ["1=1"]; $params = [];
if ($q_search !== '') {
    $where[] = "(title LIKE ? OR description LIKE ? OR user_name LIKE ? OR location LIKE ?)";
    $wild = "%$q_search%";
    $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild;
}
if ($q_category !== '') {
    $where[] = "category = ?";
    $params[] = $q_category;
}
if ($q_status !== '') {
    $where[] = "status = ?";
    $params[] = $q_status;
}
$where_sql = implode(' AND ', $where);

$jobs_list = $db_jobs->prepare("SELECT * FROM jobs WHERE $where_sql ORDER BY is_pinned DESC, id DESC");
$jobs_list->execute($params);
$jobs = $jobs_list->fetchAll();

// Enrich client emails from Database 1 (ohaticom_1)
foreach ($jobs as &$j) {
    $u_row = $db_main->query("SELECT email FROM users WHERE id = {$j['user_id']}")->fetch();
    $j['client_email'] = $u_row['email'] ?? '';
}

$categories = $db_jobs->query("SELECT * FROM job_categories ORDER BY name ASC")->fetchAll();
$applications = $db_jobs->query("SELECT ja.*, j.title as job_title FROM job_applications ja LEFT JOIN jobs j ON ja.job_id = j.id ORDER BY ja.id DESC LIMIT 50")->fetchAll();
$hires = $db_jobs->query("SELECT jh.*, j.title as job_title FROM job_hires jh LEFT JOIN jobs j ON jh.job_id = j.id ORDER BY jh.id DESC LIMIT 50")->fetchAll();

// Enrich Hires with User & Vendor names from Database 1 (ohaticom_1)
foreach ($hires as &$h) {
    $u_name = $db_main->query("SELECT name FROM users WHERE id = {$h['user_id']}")->fetchColumn();
    $v_name = $db_main->query("SELECT name FROM vendors WHERE id = {$h['vendor_id']}")->fetchColumn();
    $h['user_name'] = $u_name ?: 'Host';
    $h['vendor_name'] = $v_name ?: 'Vendor';
}

$reports = $db_logs->query("SELECT r.* FROM job_reports r ORDER BY r.id DESC")->fetchAll();
foreach ($reports as &$r) {
    $r_name = $db_main->query("SELECT name FROM users WHERE id = {$r['reporter_user_id']}")->fetchColumn();
    $r['reporter_name'] = $r_name ?: 'User';
}

$audit_logs = $db_logs->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Multi-DB Event Jobs Console</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-layout">

    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
                <h1 class="admin-page-title">Event Jobs Multi-Database Administration</h1>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="?export=csv&type=jobs" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export Jobs CSV</a>
                <a href="?export=csv&type=applications" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export Proposals CSV</a>
            </div>
        </header>

        <div class="admin-content" style="padding:20px;">
            <?php if ($msg): ?>
                <div style="background:#DCFCE7; color:#166534; padding:14px; border-radius:8px; margin-bottom:20px; font-weight:700;">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <!-- Multi-Database Allocation Info Banner -->
            <div style="background:#EFF6FF; border:1px solid #BFDBFE; color:#1E40AF; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:0.85rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div>
                    <i class="fa-solid fa-database" style="margin-right:6px;"></i>
                    <strong>Multi-Database Architecture Active:</strong>
                    Primary (<code>ohaticom_1</code>) • Jobs (<code>ohaticom_2</code>) • Comms (<code>ohaticom_3</code>) • Payments (<code>ohaticom_4</code>) • Logs (<code>ohaticom_5</code>)
                </div>
                <span class="badge" style="background:#DBEAFE; color:#1E40AF; font-weight:700; padding:4px 8px; border-radius:6px;">Live & Isolated</span>
            </div>

            <!-- Real-Time Statistical Metrics Grid -->
            <div class="admin-stat-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:14px; margin-bottom:24px;">
                <div class="admin-stat-card" style="background:#fff; padding:14px; border-radius:12px; border:1px solid #E5E7EB; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:var(--primary);"><?= $total_jobs ?></div>
                    <div style="font-size:0.75rem; color:#6B7280; font-weight:700; margin-top:2px;">Total Jobs (DB2)</div>
                </div>
                <div class="admin-stat-card" style="background:#fff; padding:14px; border-radius:12px; border:1px solid #E5E7EB; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#2563EB;"><?= $active_jobs ?></div>
                    <div style="font-size:0.75rem; color:#6B7280; font-weight:700; margin-top:2px;">Active Jobs (DB2)</div>
                </div>
                <div class="admin-stat-card" style="background:#fff; padding:14px; border-radius:12px; border:1px solid #E5E7EB; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#D97706;"><?= $total_proposals ?></div>
                    <div style="font-size:0.75rem; color:#6B7280; font-weight:700; margin-top:2px;">Proposals (DB2)</div>
                </div>
                <div class="admin-stat-card" style="background:#fff; padding:14px; border-radius:12px; border:1px solid #E5E7EB; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#16A34A;"><?= $total_hires ?></div>
                    <div style="font-size:0.75rem; color:#6B7280; font-weight:700; margin-top:2px;">Total Hires (DB2)</div>
                </div>
                <div class="admin-stat-card" style="background:#fff; padding:14px; border-radius:12px; border:1px solid #E5E7EB; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#9333EA;"><?= $featured_jobs ?></div>
                    <div style="font-size:0.75rem; color:#6B7280; font-weight:700; margin-top:2px;">Featured Jobs</div>
                </div>
                <div class="admin-stat-card" style="background:#fff; padding:14px; border-radius:12px; border:1px solid #E5E7EB; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#DC2626;"><?= $urgent_jobs ?></div>
                    <div style="font-size:0.75rem; color:#6B7280; font-weight:700; margin-top:2px;">Urgent Jobs</div>
                </div>
                <div class="admin-stat-card" style="background:#fff; padding:14px; border-radius:12px; border:1px solid #E5E7EB; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#059669;"><?= $today_jobs ?></div>
                    <div style="font-size:0.75rem; color:#6B7280; font-weight:700; margin-top:2px;">Posted Today</div>
                </div>
            </div>

            <!-- Tab Navigation Header -->
            <div style="display:flex; gap:8px; border-bottom:2px solid #E5E7EB; margin-bottom:20px; overflow-x:auto;">
                <a href="?tab=jobs" style="padding:10px 16px; font-weight:700; text-decoration:none; color:<?= $tab === 'jobs' ? 'var(--primary)' : '#6B7280' ?>; border-bottom:<?= $tab === 'jobs' ? '3px solid var(--accent)' : 'none' ?>;"><i class="fa-solid fa-list-check"></i> Jobs List & Bulk Actions</a>
                <a href="?tab=categories" style="padding:10px 16px; font-weight:700; text-decoration:none; color:<?= $tab === 'categories' ? 'var(--primary)' : '#6B7280' ?>; border-bottom:<?= $tab === 'categories' ? '3px solid var(--accent)' : 'none' ?>;"><i class="fa-solid fa-tags"></i> Categories Manager (<?= count($categories) ?>)</a>
                <a href="?tab=applications" style="padding:10px 16px; font-weight:700; text-decoration:none; color:<?= $tab === 'applications' ? 'var(--primary)' : '#6B7280' ?>; border-bottom:<?= $tab === 'applications' ? '3px solid var(--accent)' : 'none' ?>;"><i class="fa-solid fa-paper-plane"></i> Proposals Inbox (<?= count($applications) ?>)</a>
                <a href="?tab=hires" style="padding:10px 16px; font-weight:700; text-decoration:none; color:<?= $tab === 'hires' ? 'var(--primary)' : '#6B7280' ?>; border-bottom:<?= $tab === 'hires' ? '3px solid var(--accent)' : 'none' ?>;"><i class="fa-solid fa-handshake"></i> Hired Contracts (<?= count($hires) ?>)</a>
                <a href="?tab=reports" style="padding:10px 16px; font-weight:700; text-decoration:none; color:<?= $tab === 'reports' ? 'var(--primary)' : '#6B7280' ?>; border-bottom:<?= $tab === 'reports' ? '3px solid var(--accent)' : 'none' ?>;"><i class="fa-solid fa-shield-halved"></i> Safety Reports (<?= count($reports) ?>)</a>
                <a href="?tab=notifications" style="padding:10px 16px; font-weight:700; text-decoration:none; color:<?= $tab === 'notifications' ? 'var(--primary)' : '#6B7280' ?>; border-bottom:<?= $tab === 'notifications' ? '3px solid var(--accent)' : 'none' ?>;"><i class="fa-solid fa-bullhorn"></i> Broadcast Notifications</a>
                <a href="?tab=audit" style="padding:10px 16px; font-weight:700; text-decoration:none; color:<?= $tab === 'audit' ? 'var(--primary)' : '#6B7280' ?>; border-bottom:<?= $tab === 'audit' ? '3px solid var(--accent)' : 'none' ?>;"><i class="fa-solid fa-history"></i> Audit Logs (DB5)</a>
            </div>

            <!-- TAB 1: JOBS LIST & BULK ACTIONS -->
            <?php if ($tab === 'jobs'): ?>
                <form method="GET" style="background:#fff; padding:14px; border-radius:12px; border:1px solid #E5E7EB; margin-bottom:20px; display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:10px;">
                    <input type="hidden" name="tab" value="jobs">
                    <input type="text" name="search" placeholder="Search by Job ID, title, client name, location..." value="<?= htmlspecialchars($q_search) ?>" style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                    <select name="category" style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $q_category === $cat['name'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                        <option value="">All Statuses</option>
                        <option value="open" <?= $q_status === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="draft" <?= $q_status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="closed" <?= $q_status === 'closed' ? 'selected' : '' ?>>Closed</option>
                        <option value="filled" <?= $q_status === 'filled' ? 'selected' : '' ?>>Filled</option>
                        <option value="suspended" <?= $q_status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
                </form>

                <form method="POST" id="bulk-jobs-form">
                    <div style="background:#fff; border-radius:12px; border:1px solid #E5E7EB; overflow:hidden;">
                        <div style="padding:14px 20px; border-bottom:1px solid #E5E7EB; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <div style="font-weight:700; color:var(--primary);">Job Postings (<?= count($jobs) ?>)</div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <select name="bulk_action" style="padding:6px 12px; border:1px solid #CBD5E1; border-radius:6px; font-size:0.82rem;" required>
                                    <option value="">Select Bulk Action...</option>
                                    <option value="approve">Approve & Open</option>
                                    <option value="close">Close Jobs</option>
                                    <option value="feature">Mark Featured</option>
                                    <option value="unfeature">Remove Featured</option>
                                    <option value="urgent">Mark Urgent</option>
                                    <option value="suspend">Suspend Jobs</option>
                                    <option value="delete">Delete Jobs</option>
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Apply bulk action to selected jobs?')">Apply Bulk Action</button>
                            </div>
                        </div>

                        <div class="admin-table-wrap">
                            <table class="admin-table" style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr style="background:#F9FAFB; text-align:left; font-size:0.8rem; color:#6B7280;">
                                        <th style="padding:12px 16px; width:30px;"><input type="checkbox" onclick="document.querySelectorAll('.job-chk').forEach(c=>c.checked=this.checked)"></th>
                                        <th style="padding:12px 16px;">ID</th>
                                        <th style="padding:12px 16px;">Title & Category</th>
                                        <th style="padding:12px 16px;">Client Name</th>
                                        <th style="padding:12px 16px;">Budget</th>
                                        <th style="padding:12px 16px;">Status</th>
                                        <th style="padding:12px 16px;">Proposals</th>
                                        <th style="padding:12px 16px; text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($jobs)): ?>
                                        <tr><td colspan="8" style="padding:30px; text-align:center; color:#9CA3AF;">No jobs found matching criteria.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($jobs as $j): ?>
                                            <tr style="border-bottom:1px solid #F3F4F6; font-size:0.85rem; <?= $j['is_pinned'] ? 'background:#FFFBEB;' : '' ?>">
                                                <td style="padding:12px 16px;"><input type="checkbox" name="selected_jobs[]" value="<?= $j['id'] ?>" class="job-chk"></td>
                                                <td style="padding:12px 16px; font-weight:700;">#<?= $j['id'] ?></td>
                                                <td style="padding:12px 16px;">
                                                    <div style="font-weight:700; color:var(--primary);">
                                                        <?= $j['is_pinned'] ? '<i class="fa-solid fa-thumbtack" style="color:#D97706;" title="Pinned"></i> ' : '' ?>
                                                        <?= htmlspecialchars($j['title']) ?>
                                                    </div>
                                                    <span style="font-size:0.75rem; color:#6B7280;"><?= htmlspecialchars($j['category']) ?> <?= $j['is_urgent'] ? '• <strong style="color:#DC2626;"><i class="fa-solid fa-bolt"></i> URGENT</strong>' : '' ?> <?= $j['is_featured'] ? '• <strong style="color:#9333EA;"><i class="fa-solid fa-star"></i> FEATURED</strong>' : '' ?></span>
                                                </td>
                                                <td style="padding:12px 16px;"><?= htmlspecialchars($j['user_name'] ?: 'Host') ?></td>
                                                <td style="padding:12px 16px; font-weight:700;">GHS <?= number_format($j['budget'], 2) ?></td>
                                                <td style="padding:12px 16px;">
                                                    <span class="badge" style="padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; background:#E5E7EB; text-transform:capitalize;"><?= htmlspecialchars($j['status']) ?></span>
                                                </td>
                                                <td style="padding:12px 16px; font-weight:600;"><?= $j['applications_count'] ?> proposals</td>
                                                <td style="padding:12px 16px; text-align:right;">
                                                    <button type="button" class="btn btn-outline btn-sm" onclick='openAdminJobModal(<?= json_encode($j) ?>)'><i class="fa-solid fa-sliders"></i> Manage</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- TAB 2: CATEGORIES & SUBCATEGORIES MANAGER -->
            <?php if ($tab === 'categories'): ?>
                <div style="background:#fff; border-radius:12px; border:1px solid #E5E7EB; padding:20px; margin-bottom:20px;">
                    <h3 style="margin-top:0; font-size:1.1rem; color:var(--primary);"><i class="fa-solid fa-plus-circle" style="color:var(--accent);"></i> Add New Job Category</h3>
                    <form method="POST" style="display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:12px; max-width:700px;">
                        <input type="hidden" name="category_action" value="add">
                        <input type="text" name="category_name" placeholder="Category Name (e.g. Drone Operators)" required style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                        <input type="text" name="icon" value="fa-solid fa-briefcase" placeholder="FontAwesome Icon" style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                        <input type="color" name="color_code" value="#1B2B4B" style="padding:4px; height:42px; border:1px solid #CBD5E1; border-radius:8px; width:100%;">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Category</button>
                    </form>
                </div>

                <div class="admin-table-wrap" style="background:#fff; border-radius:12px; border:1px solid #E5E7EB; overflow:hidden;">
                    <table class="admin-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#F9FAFB; text-align:left; font-size:0.8rem; color:#6B7280;">
                                <th style="padding:12px 16px;">ID</th>
                                <th style="padding:12px 16px;">Icon & Category Name</th>
                                <th style="padding:12px 16px;">Status</th>
                                <th style="padding:12px 16px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr style="border-bottom:1px solid #F3F4F6; font-size:0.85rem;">
                                    <td style="padding:12px 16px; font-weight:700;">#<?= $cat['id'] ?></td>
                                    <td style="padding:12px 16px; font-weight:700; color:var(--primary);">
                                        <i class="<?= htmlspecialchars($cat['icon'] ?: 'fa-solid fa-briefcase') ?>" style="margin-right:8px; color:<?= htmlspecialchars($cat['color_code'] ?: '#1B2B4B') ?>;"></i>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <span class="badge" style="padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; background:<?= $cat['is_active'] ? '#DCFCE7' : '#F3F4F6' ?>; color:<?= $cat['is_active'] ? '#166534' : '#4B5563' ?>;"><?= $cat['is_active'] ? 'Active' : 'Disabled' ?></span>
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <form method="POST" style="display:inline-flex; gap:6px;">
                                            <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" name="category_action" value="toggle" class="btn btn-outline btn-sm"><?= $cat['is_active'] ? 'Disable' : 'Enable' ?></button>
                                            <button type="submit" name="category_action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Delete category?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- TAB 3: PROPOSALS INBOX -->
            <?php if ($tab === 'applications'): ?>
                <div class="admin-table-wrap" style="background:#fff; border-radius:12px; border:1px solid #E5E7EB; overflow:hidden;">
                    <table class="admin-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#F9FAFB; text-align:left; font-size:0.8rem; color:#6B7280;">
                                <th style="padding:12px 16px;">ID</th>
                                <th style="padding:12px 16px;">Job Title</th>
                                <th style="padding:12px 16px;">Vendor Name</th>
                                <th style="padding:12px 16px;">Quote Price</th>
                                <th style="padding:12px 16px;">Timeline</th>
                                <th style="padding:12px 16px;">Status</th>
                                <th style="padding:12px 16px;">Applied Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applications)): ?>
                                <tr><td colspan="7" style="padding:30px; text-align:center; color:#9CA3AF;">No proposals submitted yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($applications as $app): ?>
                                    <tr style="border-bottom:1px solid #F3F4F6; font-size:0.85rem;">
                                        <td style="padding:12px 16px; font-weight:700;">#<?= $app['id'] ?></td>
                                        <td style="padding:12px 16px; font-weight:700; color:var(--primary);"><?= htmlspecialchars($app['job_title'] ?: 'Job #' . $app['job_id']) ?></td>
                                        <td style="padding:12px 16px;"><?= htmlspecialchars($app['vendor_name']) ?></td>
                                        <td style="padding:12px 16px; font-weight:700;">GHS <?= number_format($app['price_quote'], 2) ?></td>
                                        <td style="padding:12px 16px;"><?= htmlspecialchars($app['delivery_timeline']) ?></td>
                                        <td style="padding:12px 16px;">
                                            <span class="badge" style="padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; background:#E5E7EB; text-transform:capitalize;"><?= htmlspecialchars($app['status']) ?></span>
                                        </td>
                                        <td style="padding:12px 16px; font-size:0.78rem; color:#6B7280;"><?= htmlspecialchars($app['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- TAB 4: HIRED CONTRACTS -->
            <?php if ($tab === 'hires'): ?>
                <div class="admin-table-wrap" style="background:#fff; border-radius:12px; border:1px solid #E5E7EB; overflow:hidden;">
                    <table class="admin-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#F9FAFB; text-align:left; font-size:0.8rem; color:#6B7280;">
                                <th style="padding:12px 16px;">Hire ID</th>
                                <th style="padding:12px 16px;">Job Title</th>
                                <th style="padding:12px 16px;">Client</th>
                                <th style="padding:12px 16px;">Hired Vendor</th>
                                <th style="padding:12px 16px;">Agreed Price</th>
                                <th style="padding:12px 16px;">Status</th>
                                <th style="padding:12px 16px;">Hired Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hires)): ?>
                                <tr><td colspan="7" style="padding:30px; text-align:center; color:#9CA3AF;">No hire contracts established yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($hires as $h): ?>
                                    <tr style="border-bottom:1px solid #F3F4F6; font-size:0.85rem;">
                                        <td style="padding:12px 16px; font-weight:700;">#<?= $h['id'] ?></td>
                                        <td style="padding:12px 16px; font-weight:700; color:var(--primary);"><?= htmlspecialchars($h['job_title'] ?: 'Job #' . $h['job_id']) ?></td>
                                        <td style="padding:12px 16px;"><?= htmlspecialchars($h['user_name'] ?: 'Host') ?></td>
                                        <td style="padding:12px 16px; font-weight:600; color:#16A34A;"><?= htmlspecialchars($h['vendor_name'] ?: 'Vendor') ?></td>
                                        <td style="padding:12px 16px; font-weight:800;">GHS <?= number_format($h['agreed_price'], 2) ?></td>
                                        <td style="padding:12px 16px;">
                                            <span class="badge" style="padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; background:#DCFCE7; color:#166534; text-transform:capitalize;"><?= htmlspecialchars($h['status']) ?></span>
                                        </td>
                                        <td style="padding:12px 16px; font-size:0.78rem; color:#6B7280;"><?= htmlspecialchars($h['hired_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- TAB 5: BROADCAST NOTIFICATIONS -->
            <?php if ($tab === 'notifications'): ?>
                <div style="background:#fff; border-radius:12px; border:1px solid #E5E7EB; padding:20px; max-width:650px;">
                    <h3 style="margin-top:0; font-size:1.1rem; color:var(--primary);"><i class="fa-solid fa-bullhorn" style="color:var(--accent);"></i> Send Multi-Channel Broadcast Notification</h3>
                    <form method="POST">
                        <input type="hidden" name="broadcast_action" value="1">
                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Target Recipients</label>
                            <select name="recipient_type" class="form-control" style="width:100%; padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                                <option value="all">All Platform Users & Hosts</option>
                                <option value="vendors">All Registered Vendors</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Notification Title</label>
                            <input type="text" name="notif_title" placeholder="e.g. New High-Budget Jobs Available!" required style="width:100%; padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                        </div>
                        <div class="form-group" style="margin-bottom:16px;">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Message Content</label>
                            <textarea name="notif_body" rows="4" placeholder="Write your broadcast notification announcement..." required style="width:100%; padding:10px; border:1px solid #CBD5E1; border-radius:8px;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Send broadcast notification to all selected recipients?')"><i class="fa-solid fa-paper-plane"></i> Send Multi-Channel Broadcast</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- TAB 6: AUDIT LOGS -->
            <?php if ($tab === 'audit'): ?>
                <div class="admin-table-wrap" style="background:#fff; border-radius:12px; border:1px solid #E5E7EB; overflow:hidden;">
                    <table class="admin-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#F9FAFB; text-align:left; font-size:0.8rem; color:#6B7280;">
                                <th style="padding:12px 16px;">ID</th>
                                <th style="padding:12px 16px;">Admin Name</th>
                                <th style="padding:12px 16px;">Action</th>
                                <th style="padding:12px 16px;">Details</th>
                                <th style="padding:12px 16px;">IP Address</th>
                                <th style="padding:12px 16px;">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($audit_logs)): ?>
                                <tr><td colspan="6" style="padding:30px; text-align:center; color:#9CA3AF;">No admin audit log records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($audit_logs as $log): ?>
                                    <tr style="border-bottom:1px solid #F3F4F6; font-size:0.85rem;">
                                        <td style="padding:12px 16px; font-weight:700;">#<?= $log['id'] ?></td>
                                        <td style="padding:12px 16px; font-weight:700; color:var(--primary);"><?= htmlspecialchars($log['admin_name']) ?></td>
                                        <td style="padding:12px 16px;"><span class="badge" style="padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; background:#E2E8F0;"><?= htmlspecialchars($log['action']) ?></span></td>
                                        <td style="padding:12px 16px; color:#475569;"><?= htmlspecialchars($log['details']) ?></td>
                                        <td style="padding:12px 16px; font-size:0.78rem; font-family:monospace;"><?= htmlspecialchars($log['ip_address']) ?></td>
                                        <td style="padding:12px 16px; font-size:0.78rem; color:#6B7280;"><?= htmlspecialchars($log['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- JOB EDIT / DETAILS ADMIN MODAL -->
    <div id="admin-job-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99999; justify-content:center; align-items:center;">
        <div style="background:#fff; width:90%; max-width:600px; border-radius:14px; padding:24px; max-height:85vh; overflow-y:auto; position:relative;">
            <button onclick="document.getElementById('admin-job-modal').style.display='none'" style="position:absolute; right:16px; top:16px; background:none; border:none; font-size:1.2rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            <h3 style="margin-top:0; color:var(--primary);" id="ajm-title">Manage Job</h3>

            <form method="POST">
                <input type="hidden" name="job_action" value="save_edit">
                <input type="hidden" name="job_id" id="ajm-id">

                <div class="form-group" style="margin-bottom:12px;">
                    <label style="font-weight:600; font-size:0.85rem;">Job Title</label>
                    <input type="text" name="title" id="ajm-input-title" required style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.85rem;">Category</label>
                        <input type="text" name="category" id="ajm-input-category" required style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
                    </div>
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.85rem;">Budget (GHS)</label>
                        <input type="number" name="budget" id="ajm-input-budget" required style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.85rem;">Location</label>
                        <input type="text" name="location" id="ajm-input-location" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
                    </div>
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.85rem;">Status</label>
                        <select name="status" id="ajm-input-status" style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px;">
                            <option value="open">Open</option>
                            <option value="draft">Draft</option>
                            <option value="closed">Closed</option>
                            <option value="filled">Filled</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label style="font-weight:600; font-size:0.85rem;">Internal Admin Notes</label>
                    <textarea name="admin_notes" id="ajm-input-notes" rows="3" placeholder="Notes visible only to administrators..." style="width:100%; padding:8px; border:1px solid #CBD5E1; border-radius:6px;"></textarea>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #E5E7EB; padding-top:14px;">
                    <div style="display:flex; gap:6px;">
                        <button type="submit" name="job_action" value="pin" class="btn btn-outline btn-sm">Pin / Unpin</button>
                        <button type="submit" name="job_action" value="urgent" class="btn btn-outline btn-sm">Toggle Urgent</button>
                        <button type="submit" name="job_action" value="feature" class="btn btn-outline btn-sm">Toggle Feature</button>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAdminJobModal(job) {
            document.getElementById('ajm-id').value = job.id;
            document.getElementById('ajm-title').innerText = 'Manage Job #' + job.id + ': ' + job.title;
            document.getElementById('ajm-input-title').value = job.title;
            document.getElementById('ajm-input-category').value = job.category;
            document.getElementById('ajm-input-budget').value = job.budget;
            document.getElementById('ajm-input-location').value = job.location || '';
            document.getElementById('ajm-input-status').value = job.status || 'open';
            document.getElementById('ajm-input-notes').value = job.admin_notes || '';

            const modal = document.getElementById('admin-job-modal');
            modal.style.display = 'flex';
        }
    </script>
</body>
</html>
