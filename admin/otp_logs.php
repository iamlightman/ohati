<?php
// admin/otp_logs.php — Production OTP Activity Monitoring Console
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(target LIKE ? OR ip_address LIKE ? OR code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where);

// Count total records
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM otp_codes WHERE $where_sql");
$count_stmt->execute($params);
$total_records = intval($count_stmt->fetchColumn());
$total_pages = max(1, ceil($total_records / $limit));

// Fetch OTP logs
$stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$otps = $stmt->fetchAll();

$page_title = "OTP Activity & Delivery Logs";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Ohati Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary:#111827; 
            --accent:#E05A47; 
            --bg:#F3F4F6; 
            --card:#FFFFFF; 
            --text:#1F2937; 
            --gray:#6B7280; 
            --border:#E5E7EB; 
            --success:#10B981; 
            --error:#EF4444;
            --sidebar-width:260px;
        }
        * { box-sizing:border-box; margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        body { background:var(--bg); color:var(--text); min-height:100vh; }
        .admin-layout { display:flex; min-height:100vh; }
        .admin-main { flex:1; margin-left:var(--sidebar-width); padding:24px; width:calc(100% - var(--sidebar-width)); }
        .admin-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid var(--border); }
        .admin-menu-toggle { display:none; background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text); }
        .admin-page-title { font-size:1.4rem; font-weight:800; color:var(--primary); }
        
        .card { background:var(--card); border-radius:12px; padding:20px; border:1px solid var(--border); box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:20px; }
        .filter-bar { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .input-field { padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:0.85rem; outline:none; }
        .input-field:focus { border-color:var(--accent); }
        .btn { padding:8px 16px; background:var(--primary); color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
        .btn-outline { background:transparent; color:var(--primary); border:1px solid var(--border); }
        .table-responsive { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:0.82rem; text-align:left; }
        th { background:var(--bg); padding:10px 12px; border-bottom:2px solid var(--border); color:var(--gray); font-weight:700; text-transform:uppercase; font-size:0.7rem; }
        td { padding:12px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:hover { background:rgba(0,0,0,0.02); }
        .badge { padding:4px 8px; border-radius:6px; font-size:0.7rem; font-weight:700; text-transform:uppercase; display:inline-block; }
        .badge-success { background:#D1FAE5; color:#065F46; }
        .badge-pending { background:#FEF3C7; color:#92400E; }
        .badge-error { background:#FEE2E2; color:#991B1B; }

        .pagination { display:flex; justify-content:space-between; align-items:center; margin-top:16px; font-size:0.82rem; color:var(--gray); }
        .page-links { display:flex; gap:6px; }
        .page-link { padding:6px 12px; border:1px solid var(--border); border-radius:6px; color:var(--text); text-decoration:none; }
        .page-link.active { background:var(--accent); color:#fff; border-color:var(--accent); }

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
                    <h1 class="admin-page-title"><i class="fa-solid fa-key" style="color:var(--accent);"></i> OTP Activity & Delivery Logs</h1>
                    <p style="font-size:0.8rem; color:var(--gray);">Audit trail of all generated 6-digit OTP verification codes and dispatch status</p>
                </div>
            </div>
        </header>

        <div class="card">
            <form method="GET" class="filter-bar">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search phone, email, OTP code, IP address..." class="input-field" style="flex:1; min-width:220px;">
                <button type="submit" class="btn"><i class="fa-solid fa-filter"></i> Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="otp_logs.php" class="btn btn-outline" style="background:#F1F5F9;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Target Contact</th>
                            <th>OTP Code</th>
                            <th>Status</th>
                            <th>Email Status</th>
                            <th>SMS Status</th>
                            <th>Expires At</th>
                            <th>Created At</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($otps)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--gray);">No OTP activity logs found matching criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($otps as $o): ?>
                                <tr>
                                    <td><strong>#<?= $o['id'] ?></strong></td>
                                    <td><strong><?= htmlspecialchars($o['target']) ?></strong></td>
                                    <td><code style="background:#F1F5F9; padding:4px 8px; border-radius:6px; font-weight:700; color:var(--primary); font-size:0.9rem;"><?= htmlspecialchars($o['code']) ?></code></td>
                                    <td>
                                        <?php if ($o['used'] == 1): ?>
                                            <span class="badge badge-success"><i class="fa-solid fa-check"></i> Verified</span>
                                        <?php elseif (strtotime($o['expires_at']) < time()): ?>
                                            <span class="badge badge-error"><i class="fa-solid fa-clock"></i> Expired</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending"><i class="fa-solid fa-hourglass-half"></i> Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= ($o['email_status'] === 'sent') ? 'badge-success' : 'badge-pending' ?>"><?= htmlspecialchars($o['email_status'] ?: 'sent') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= ($o['sms_status'] === 'sent') ? 'badge-success' : 'badge-pending' ?>"><?= htmlspecialchars($o['sms_status'] ?: 'pending') ?></span>
                                    </td>
                                    <td style="color:var(--gray); font-size:0.78rem;"><?= htmlspecialchars($o['expires_at']) ?></td>
                                    <td style="color:var(--gray); font-size:0.78rem;"><?= htmlspecialchars($o['created_at']) ?></td>
                                    <td style="font-family:monospace; font-size:0.75rem; color:var(--gray);"><?= htmlspecialchars($o['ip_address'] ?: '127.0.0.1') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pro Pagination Controls -->
            <div class="pagination" style="margin-top:20px; padding-top:12px; border-top:1px solid var(--border);">
                <div>Showing <?= min($offset + 1, $total_records) ?> to <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> OTP log records</div>
                <div class="page-links">
                    <a href="?page=<?= max(1, $page - 1) ?>&q=<?= urlencode($search) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>" style="pointer-events:<?= $page <= 1 ? 'none' : 'auto' ?>; opacity:<?= $page <= 1 ? '0.5' : '1' ?>;"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                    
                    <?php 
                    $start_p = max(1, $page - 2);
                    $end_p = min($total_pages, $page + 2);
                    for ($i = $start_p; $i <= $end_p; $i++): 
                    ?>
                        <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <a href="?page=<?= min($total_pages, $page + 1) ?>&q=<?= urlencode($search) ?>" class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>" style="pointer-events:<?= $page >= $total_pages ? 'none' : 'auto' ?>; opacity:<?= $page >= $total_pages ? '0.5' : '1' ?>;">Next <i class="fa-solid fa-chevron-right"></i></a>
                </div>
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
    </script>
</body>
</html>
