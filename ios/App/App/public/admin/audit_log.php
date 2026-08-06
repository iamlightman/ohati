<?php
// admin/audit_log.php — System Activity & Financial Audit Trail
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

$search = trim($_GET['q'] ?? '');
$action_filter = trim($_GET['action'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(actor_name LIKE ? OR details LIKE ? OR entity_type LIKE ? OR action LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($action_filter !== '') {
    $where[] = "action = ?";
    $params[] = $action_filter;
}

if ($role_filter !== '') {
    $where[] = "actor_role = ?";
    $params[] = $role_filter;
}

if ($start_date !== '') {
    $where[] = "created_at >= ?";
    $params[] = $start_date . " 00:00:00";
}

if ($end_date !== '') {
    $where[] = "created_at <= ?";
    $params[] = $end_date . " 23:59:59";
}

$where_sql = implode(' AND ', $where);

// Count total
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM financial_audit_log WHERE $where_sql");
$count_stmt->execute($params);
$total_records = intval($count_stmt->fetchColumn());
$total_pages = max(1, ceil($total_records / $limit));

// Fetch logs
$stmt = $pdo->prepare("SELECT * FROM financial_audit_log WHERE $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get distinct action types and roles for filter dropdowns
$actions_list = $pdo->query("SELECT DISTINCT action FROM financial_audit_log WHERE action != '' ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
$roles_list = $pdo->query("SELECT DISTINCT actor_role FROM financial_audit_log WHERE actor_role != '' ORDER BY actor_role ASC")->fetchAll(PDO::FETCH_COLUMN);

$page_title = "Activity & Financial Audit Logs";
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
            --primary: #111827; 
            --accent: #E05A47; 
            --bg: #F3F4F6; 
            --card: #FFFFFF; 
            --text: #1F2937; 
            --gray: #6B7280; 
            --border: #E5E7EB; 
            --success: #10B981; 
            --error: #EF4444;
            --sidebar-width: 260px;
        }
        * { box-sizing:border-box; margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        body { background:var(--bg); color:var(--text); min-height:100vh; }
        
        .admin-layout { display:flex; min-height:100vh; }
        .admin-sidebar { width:var(--sidebar-width); background:#111827; color:#fff; flex-shrink:0; display:flex; flex-direction:column; position:fixed; top:0; bottom:0; left:0; z-index:100; transition:transform 0.3s ease; }
        .admin-sidebar-logo { padding:20px; display:flex; align-items:center; border-bottom:1px solid rgba(255,255,255,0.1); }
        .admin-sidebar-brand { font-size:1.1rem; font-weight:800; color:#E05A47; margin-left:10px; }
        .admin-sidebar-close { display:none; background:none; border:none; color:#fff; font-size:1.2rem; margin-left:auto; cursor:pointer; }
        .admin-nav { padding:16px 0; overflow-y:auto; flex:1; }
        .admin-nav-section { padding:8px 20px; font-size:0.65rem; text-transform:uppercase; letter-spacing:1px; color:#9CA3AF; font-weight:700; margin-top:12px; }
        .admin-nav-item { display:flex; align-items:center; gap:12px; padding:10px 20px; color:#D1D5DB; text-decoration:none; font-size:0.85rem; font-weight:500; transition:all 0.2s; }
        .admin-nav-item:hover, .admin-nav-item.active { background:rgba(224,90,71,0.15); color:#E05A47; font-weight:700; border-left:4px solid #E05A47; }
        
        .admin-main { flex:1; margin-left:var(--sidebar-width); padding:24px; width:calc(100% - var(--sidebar-width)); }
        .admin-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid var(--border); }
        .admin-menu-toggle { display:none; background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text); }
        .admin-page-title { font-size:1.4rem; font-weight:800; color:var(--primary); }
        
        .card { background:var(--card); border-radius:12px; padding:20px; border:1px solid var(--border); box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:20px; }
        .filter-bar { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .input-field, .select-field { padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:0.85rem; outline:none; }
        .input-field:focus, .select-field:focus { border-color:var(--accent); }
        .btn { padding:8px 16px; background:var(--primary); color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
        .btn-outline { background:transparent; color:var(--primary); border:1px solid var(--border); }
        .btn-outline:hover { background:var(--bg); }
        
        .table-responsive { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:0.82rem; text-align:left; }
        th { background:var(--bg); padding:10px 12px; border-bottom:2px solid var(--border); color:var(--gray); font-weight:700; text-transform:uppercase; font-size:0.7rem; }
        td { padding:12px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:hover { background:rgba(0,0,0,0.02); }
        .badge { padding:4px 8px; border-radius:6px; font-size:0.7rem; font-weight:700; text-transform:uppercase; display:inline-block; }
        .badge-action { background:#E0F2FE; color:#0369A1; }
        .badge-actor { background:#F1F5F9; color:#475569; }
        .pagination { display:flex; justify-content:space-between; align-items:center; margin-top:16px; font-size:0.82rem; color:var(--gray); }
        .page-links { display:flex; gap:6px; }
        .page-link { padding:6px 12px; border:1px solid var(--border); border-radius:6px; color:var(--text); text-decoration:none; }
        .page-link.active { background:var(--accent); color:#fff; border-color:var(--accent); }
        
        /* Details Modal */
        .modal-backdrop { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-box { background:#fff; border-radius:16px; max-width:600px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.2); border:1px solid var(--border); }
        .modal-header { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:#F9FAFB; }
        .modal-body { padding:24px; }
        .modal-footer { padding:16px 24px; border-top:1px solid var(--border); text-align:right; background:#F9FAFB; }
        
        .detail-row { display:flex; padding:8px 0; border-bottom:1px solid #F3F4F6; font-size:0.85rem; }
        .detail-label { width:150px; font-weight:700; color:var(--gray); flex-shrink:0; }
        .detail-val { flex:1; color:var(--text); word-break:break-word; }

        @media (max-width:768px) {
            .admin-sidebar { transform:translateX(-100%); }
            .admin-sidebar.open { transform:translateX(0); }
            .admin-sidebar-close { display:block; }
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
        <!-- Topbar -->
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1 class="admin-page-title"><i class="fa-solid fa-list-check" style="color:var(--accent);"></i> Activity & Financial Audit Logs</h1>
                    <p style="font-size:0.8rem; color:var(--gray);">Immutable trail of platform actions, payments, status updates, and security logs</p>
                </div>
            </div>
        </header>

        <div class="card">
            <form method="GET" class="filter-bar" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:10px; align-items:end;">
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--gray); margin-bottom:4px; display:block;">Keyword Search</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search actor, details, IP..." class="input-field" style="width:100%;">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--gray); margin-bottom:4px; display:block;">Action Category</label>
                    <select name="action" class="select-field" style="width:100%;">
                        <option value="">All Action Types</option>
                        <?php foreach ($actions_list as $act): ?>
                            <option value="<?= htmlspecialchars($act) ?>" <?= $action_filter === $act ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--gray); margin-bottom:4px; display:block;">Actor Role</label>
                    <select name="role" class="select-field" style="width:100%;">
                        <option value="">All Roles</option>
                        <?php foreach ($roles_list as $rl): ?>
                            <option value="<?= htmlspecialchars($rl) ?>" <?= $role_filter === $rl ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($rl)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--gray); margin-bottom:4px; display:block;">From Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="input-field" style="width:100%;">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--gray); margin-bottom:4px; display:block;">To Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="input-field" style="width:100%;">
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn" style="flex:1;"><i class="fa-solid fa-filter"></i> Filter</button>
                    <?php if ($search !== '' || $action_filter !== '' || $role_filter !== '' || $start_date !== '' || $end_date !== ''): ?>
                        <a href="audit_log.php" class="btn btn-outline" style="background:#F1F5F9;" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive" style="margin-top:16px;">
                <table>
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Actor</th>
                            <th>Amount</th>
                            <th>Old → New Status</th>
                            <th>IP Address</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--gray);">No audit log entries found matching criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td><strong>#<?= $l['id'] ?></strong></td>
                                    <td style="white-space:nowrap; color:var(--gray); font-size:0.78rem;"><?= htmlspecialchars($l['created_at']) ?></td>
                                    <td><span class="badge badge-action"><?= htmlspecialchars($l['action']) ?></span></td>
                                    <td><?= htmlspecialchars($l['entity_type']) ?> #<?= $l['entity_id'] ?></td>
                                    <td>
                                        <span class="badge badge-actor"><?= htmlspecialchars($l['actor_role'] ?: 'system') ?></span>
                                        <div><strong><?= htmlspecialchars($l['actor_name'] ?: 'User #'.$l['actor_id']) ?></strong></div>
                                    </td>
                                    <td><?= floatval($l['amount']) > 0 ? '<strong style="color:var(--success);">GH₵ '.number_format($l['amount'], 2).'</strong>' : '—' ?></td>
                                    <td>
                                        <?php if ($l['old_status'] || $l['new_status']): ?>
                                            <span style="color:var(--gray);"><?= htmlspecialchars($l['old_status'] ?: 'None') ?></span> → <strong style="color:var(--accent);"><?= htmlspecialchars($l['new_status']) ?></strong>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-family:monospace; font-size:0.75rem; color:var(--gray);"><?= htmlspecialchars($l['ip_address']) ?></td>
                                    <td>
                                        <script id="audit-data-<?= $l['id'] ?>" type="application/json"><?= json_encode($l, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
                                        <button class="btn btn-outline" style="padding:4px 10px; font-size:0.75rem; font-weight:700;" onclick="openAuditDetails(<?= $l['id'] ?>)">
                                            <i class="fa-solid fa-eye"></i> Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pro Pagination Controls -->
            <div class="pagination" style="margin-top:20px; padding-top:12px; border-top:1px solid var(--border);">
                <div>Showing <?= min($offset + 1, $total_records) ?> to <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> log entries</div>
                <div class="page-links">
                    <a href="?page=<?= max(1, $page - 1) ?>&q=<?= urlencode($search) ?>&action=<?= urlencode($action_filter) ?>&role=<?= urlencode($role_filter) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>" style="pointer-events:<?= $page <= 1 ? 'none' : 'auto' ?>; opacity:<?= $page <= 1 ? '0.5' : '1' ?>;"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                    
                    <?php 
                    $start_p = max(1, $page - 2);
                    $end_p = min($total_pages, $page + 2);
                    for ($i = $start_p; $i <= $end_p; $i++): 
                    ?>
                        <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&action=<?= urlencode($action_filter) ?>&role=<?= urlencode($role_filter) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <a href="?page=<?= min($total_pages, $page + 1) ?>&q=<?= urlencode($search) ?>&action=<?= urlencode($action_filter) ?>&role=<?= urlencode($role_filter) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>" style="pointer-events:<?= $page >= $total_pages ? 'none' : 'auto' ?>; opacity:<?= $page >= $total_pages ? '0.5' : '1' ?>;">Next <i class="fa-solid fa-chevron-right"></i></a>
                </div>
            </div>
        </div>
    </main>

    <!-- Details View Modal -->
    <div class="modal-backdrop" id="auditDetailsModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:var(--primary);"><i class="fa-solid fa-circle-info" style="color:var(--accent);"></i> Audit Log Record Details</h3>
                <button onclick="closeAuditDetails()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--gray);">&times;</button>
            </div>
            <div class="modal-body" id="auditDetailsBody">
                <!-- Dynamically populated -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeAuditDetails()">Close Window</button>
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

        function openAuditDetails(logInput) {
            let log = logInput;
            if (typeof logInput === 'number' || typeof logInput === 'string') {
                const scriptTag = document.getElementById('audit-data-' + logInput);
                if (scriptTag) {
                    try {
                        log = JSON.parse(scriptTag.textContent);
                    } catch(e) {
                        alert('Could not parse audit log data.'); return;
                    }
                }
            }
            if (!log || typeof log !== 'object') {
                alert('Audit log data not found.'); return;
            }

            const body = document.getElementById('auditDetailsBody');
            if (!body) return;

            body.innerHTML = `
                <div class="detail-row"><div class="detail-label">Log ID:</div><div class="detail-val"><strong>#${log.id}</strong></div></div>
                <div class="detail-row"><div class="detail-label">Created Timestamp:</div><div class="detail-val">${log.created_at || 'N/A'}</div></div>
                <div class="detail-row"><div class="detail-label">Action Name:</div><div class="detail-val"><span class="badge badge-action">${log.action || 'General'}</span></div></div>
                <div class="detail-row"><div class="detail-label">Entity Type & ID:</div><div class="detail-val">${log.entity_type} #${log.entity_id}</div></div>
                <div class="detail-row"><div class="detail-label">Actor Name:</div><div class="detail-val"><strong>${log.actor_name || 'System'}</strong> (ID: ${log.actor_id || 0})</div></div>
                <div class="detail-row"><div class="detail-label">Actor Role:</div><div class="detail-val"><span class="badge badge-actor">${log.actor_role || 'system'}</span></div></div>
                <div class="detail-row"><div class="detail-label">Monetary Amount:</div><div class="detail-val">${parseFloat(log.amount) > 0 ? '<strong style="color:#10B981; font-size:1rem;">GH₵ ' + parseFloat(log.amount).toFixed(2) + '</strong>' : 'N/A'}</div></div>
                <div class="detail-row"><div class="detail-label">Status Transition:</div><div class="detail-val">${log.old_status || 'None'} &rarr; <strong style="color:var(--accent);">${log.new_status || 'Active'}</strong></div></div>
                <div class="detail-row"><div class="detail-label">IP Address:</div><div class="detail-val"><code>${log.ip_address || '127.0.0.1'}</code></div></div>
                <div class="detail-row"><div class="detail-label">Device Fingerprint:</div><div class="detail-val" style="font-size:0.75rem; color:var(--gray);">${log.device || 'Unknown'}</div></div>
                <div style="margin-top:16px;">
                    <div style="font-weight:700; font-size:0.8rem; color:var(--gray); margin-bottom:6px;">EVENT LOG DETAILS & DESCRIPTION:</div>
                    <div style="background:#F9FAFB; border:1px solid #E5E7EB; padding:12px; border-radius:8px; font-size:0.85rem; line-height:1.5; color:#374151;">
                        ${log.details || 'No additional text details recorded.'}
                    </div>
                </div>
            `;

            document.getElementById('auditDetailsModal').classList.add('open');
        }

        function closeAuditDetails() {
            document.getElementById('auditDetailsModal').classList.remove('open');
        }
    </script>
</body>
</html>
