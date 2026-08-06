<?php
// admin/issues.php - Ohati Admin Issues & Reports Management
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $iid = intval($input['issue_id'] ?? 0);
    $action = $input['action'] ?? '';
    
    if ($iid > 0) {
        if ($action === 'resolve') {
            $pdo->prepare("UPDATE reported_issues SET status = 'resolved' WHERE id = ?")->execute([$iid]);
            echo json_encode(['success' => true, 'new_status' => 'resolved']);
            exit;
        } elseif ($action === 'delete') {
            // Soft delete reported issue: archive first
            $sel = $pdo->prepare("SELECT * FROM reported_issues WHERE id = ?");
            $sel->execute([$iid]);
            $issue = $sel->fetch(PDO::FETCH_ASSOC);
            if ($issue) {
                $record_data = json_encode($issue);
                $stmt = $pdo->prepare("INSERT INTO deleted_records (record_type, record_id, record_data) VALUES ('reported_issue', ?, ?)");
                $stmt->execute([$iid, $record_data]);
                
                $pdo->prepare("DELETE FROM reported_issues WHERE id = ?")->execute([$iid]);
                echo json_encode(['success' => true]);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Issue record not found']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Fetch search and filter params
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql_base = "FROM reported_issues i 
        JOIN users u ON i.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql_base .= " AND (i.title LIKE ? OR i.description LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== '') {
    $sql_base .= " AND i.status = ?";
    $params[] = $status;
}

// Count total
$count_query = "SELECT COUNT(*) " . $sql_base;
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));

$sql = "SELECT i.*, u.name as user_name " . $sql_base . " ORDER BY i.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$issues = $stmt->fetchAll();

$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Issues & Reports</title>
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
            <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
            <div class="admin-topbar-title">Issues & Reports</div>
            <div class="admin-user-pill">
                <i class="fa-solid fa-user-shield"></i>
                <span>Admin</span>
            </div>
        </header>

        <!-- Admin Content -->
        <div class="admin-content">
            <!-- Filters -->
            <div class="card" style="padding:20px; margin-bottom:30px;">
                <form method="GET" action="issues.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <input type="text" name="search" class="form-input" placeholder="Search title, description or reporter..." value="<?= htmlspecialchars($search) ?>" style="margin:0; padding:10px 14px; flex:1; min-width:200px;">
                    
                    <select name="status" class="form-input" style="margin:0; padding:10px 14px; width:auto; min-width:150px;">
                        <option value="">All Statuses</option>
                        <option value="open" <?= ($status === 'open') ? 'selected' : '' ?>>Open</option>
                        <option value="resolved" <?= ($status === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                    </select>

                    <button type="submit" class="btn" style="padding:10px 20px;">Filter</button>
                    <?php if ($search !== '' || $status !== ''): ?>
                        <a href="issues.php" class="btn btn-outline" style="padding:10px 20px; text-decoration:none; text-align:center;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table Card -->
            <div class="card" style="padding:0; overflow:hidden;">
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reporter</th>
                                <th>Issue / Category</th>
                                <th>Description</th>
                                <th>Report Date</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($issues)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:40px; color:var(--gray-500);">No reported issues found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($issues as $i): ?>
                                    <tr id="row-<?= $i['id'] ?>">
                                        <td style="font-weight:700;">#<?= $i['id'] ?></td>
                                        <td>
                                            <div style="font-weight:600; color:var(--primary);"><?= htmlspecialchars($i['user_name']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:700; color:var(--gray-800);"><?= htmlspecialchars($i['title']) ?></div>
                                            <span style="font-size:0.75rem; background:var(--gray-100); color:var(--gray-700); padding:2px 6px; border-radius:4px; font-weight:600;"><?= htmlspecialchars($i['category']) ?></span>
                                        </td>
                                        <td>
                                            <div style="font-size:0.75rem; color:var(--gray-500); max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($i['description']) ?>">
                                                <?= htmlspecialchars($i['description']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size:0.75rem; color:var(--gray-600);"><?= substr($i['created_at'], 0, 10) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $cls = ($i['status'] === 'resolved') ? 'status-confirmed' : 'status-pending';
                                            ?>
                                            <span id="status-badge-<?= $i['id'] ?>" class="booking-status <?= $cls ?>" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; text-transform:capitalize;">
                                                <?= htmlspecialchars($i['status']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <div style="display:flex; justify-content:flex-end; gap:6px;">
                                                <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; font-weight:700;" onclick='viewIssueDetails(<?= json_encode($i, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Issue Report Details"><i class="fa-solid fa-eye"></i> Details</button>
                                                <?php if ($i['status'] !== 'resolved'): ?>
                                                    <button id="resolve-btn-<?= $i['id'] ?>" class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; color:var(--teal); border-color:var(--teal);" onclick="resolveIssue(<?= $i['id'] ?>)" title="Mark Resolved">
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="deleteIssue(<?= $i['id'] ?>)" title="Archive & Delete">
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
                            Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_items) ?> of <?= $total_items ?> reported issues
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

        function resolveIssue(issueId) {
            if (!confirm('Mark this issue as resolved?')) return;
            fetch('issues.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ issue_id: issueId, action: 'resolve' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('status-badge-' + issueId);
                    if (badge) {
                        badge.textContent = 'resolved';
                        badge.className = 'booking-status status-confirmed';
                    }
                    const resolveBtn = document.getElementById('resolve-btn-' + issueId);
                    if (resolveBtn) {
                        resolveBtn.remove();
                    }
                }
            });
        }

        function deleteIssue(issueId) {
            if (!confirm('Are you sure you want to archive and delete this issue record?')) return;
            fetch('issues.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ issue_id: issueId, action: 'delete' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('row-' + issueId);
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                } else {
                    alert(data.message || 'Failed to delete issue');
                }
            });
        }

        function viewIssueDetails(i) {
            const content = document.getElementById('issueDetailsContent');
            if (!content) return;

            content.innerHTML = `
                <div style="text-align:center; margin-bottom:16px;">
                    <div style="font-size:1.6rem; color:var(--rose);"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <h4 style="margin:4px 0 0 0; font-size:1.1rem; font-weight:800; color:var(--primary);">Report #${i.id}: ${i.subject || 'Issue Report'}</h4>
                    <div style="font-size:0.75rem; color:var(--gray-600); margin-top:2px;">Category: <strong>${i.category || 'General'}</strong> | Priority: ${i.priority || 'Normal'}</div>
                    <span class="booking-status ${i.status === 'resolved' ? 'status-confirmed' : 'status-cancelled'}" style="font-size:0.65rem; padding:4px 10px; border-radius:20px; text-transform:uppercase; font-weight:700; display:inline-block; margin-top:6px;">${i.status}</span>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.8rem; background:var(--gray-50); padding:14px; border-radius:12px; border:1px solid #E5E7EB; margin-bottom:16px;">
                    <div><strong>Reporter Name:</strong> ${i.reporter_name || 'Anonymous'}</div>
                    <div><strong>Reporter Email:</strong> ${i.reporter_email || 'N/A'}</div>
                    <div><strong>Reporter Phone:</strong> ${i.reporter_phone || 'N/A'}</div>
                    <div><strong>Target Vendor ID:</strong> #${i.vendor_id || 'N/A'}</div>
                    <div><strong>Date Reported:</strong> 📅 ${i.created_at || 'N/A'}</div>
                    <div><strong>Resolution Status:</strong> ${i.status || 'Open'}</div>
                    <div style="grid-column: span 2;"><strong>Detailed Report Message:</strong> <div style="margin-top:4px; padding:10px; background:#fff; border-radius:8px; border:1px solid #E5E7EB; color:var(--gray-700);">${i.message || 'No description attached.'}</div></div>
                </div>

                <button class="btn btn-outline btn-full" onclick="closeIssueDetailsModal()" style="font-weight:700;">Close</button>
            `;

            document.getElementById('issueDetailsModal').style.display = 'flex';
        }

        function closeIssueDetailsModal() {
            document.getElementById('issueDetailsModal').style.display = 'none';
        }
    </script>

    <!-- Issue Details Modal -->
    <div id="issueDetailsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:560px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E5E7EB; padding-bottom:12px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">
                    <i class="fa-solid fa-triangle-exclamation" style="color:var(--rose);"></i> Reported Issue Full Record
                </h3>
                <button onclick="closeIssueDetailsModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--gray-500);">&times;</button>
            </div>
            <div id="issueDetailsContent"></div>
        </div>
    </div>
</body>
</html>
