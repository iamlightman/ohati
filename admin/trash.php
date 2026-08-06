<?php
// admin/trash.php - Ohati Deleted Records History & Recovery Console
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

$message = '';
$message_type = '';

// Handle Restore or Purge Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $trash_id = intval($input['trash_id'] ?? 0);

    if ($trash_id > 0) {
        // Fetch trash record
        $stmt = $pdo->prepare("SELECT * FROM deleted_records WHERE id = ?");
        $stmt->execute([$trash_id]);
        $trash = $stmt->fetch();

        if ($trash) {
            $data = json_decode($trash['record_data'], true);
            $type = $trash['record_type']; // 'user' or 'vendor'
            $original_id = $trash['record_id'];

            if ($action === 'restore') {
                try {
                    $pdo->beginTransaction();

                    if ($type === 'user') {
                        // Check if the ID is already taken
                        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                        $chk->execute([$original_id]);
                        if ($chk->fetch()) {
                            // If ID is taken, we insert with a new ID or throw error.
                            // But usually it's not taken because it was deleted.
                            throw new Exception("A user with the original ID {$original_id} already exists.");
                        }

                        // Reconstruct query
                        $columns = array_keys($data);
                        $placeholders = array_fill(0, count($columns), '?');
                        $sql = "INSERT INTO users (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
                        $pdo->prepare($sql)->execute(array_values($data));
                    } elseif ($type === 'vendor') {
                        // Check if vendor ID is taken
                        $chk = $pdo->prepare("SELECT id FROM vendors WHERE id = ?");
                        $chk->execute([$original_id]);
                        if ($chk->fetch()) {
                            throw new Exception("A vendor with the original ID {$original_id} already exists.");
                        }

                        // Reconstruct query
                        $columns = array_keys($data);
                        $placeholders = array_fill(0, count($columns), '?');
                        $sql = "INSERT INTO vendors (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
                        $pdo->prepare($sql)->execute(array_values($data));
                    }

                    // Remove from trash
                    $pdo->prepare("DELETE FROM deleted_records WHERE id = ?")->execute([$trash_id]);

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Record restored successfully.']);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            } elseif ($action === 'purge') {
                // Permanently delete from trash
                $pdo->prepare("DELETE FROM deleted_records WHERE id = ?")->execute([$trash_id]);
                echo json_encode(['success' => true, 'message' => 'Record permanently purged.']);
                exit;
            }
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid record or action.']);
    exit;
}

// Fetch deleted records
$search = trim($_GET['search'] ?? '');
$type_filter = trim($_GET['type'] ?? '');

$sql = "SELECT * FROM deleted_records WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (record_data LIKE ?)";
    $params[] = "%$search%";
}

if ($type_filter !== '') {
    $sql .= " AND record_type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deleted_records = $stmt->fetchAll();

$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Deleted Records</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @media(max-width: 900px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                display: flex !important;
                box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            }
            .admin-sidebar.open { transform: translateX(0); }
            .admin-main { margin-left: 0 !important; }
        }
        .admin-sidebar-logo img {
            height: 36px;
            width: auto;
            object-fit: contain;
        }
        .admin-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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
        @media(max-width: 900px) { .admin-menu-toggle { display: block; } }
    </style>
</head>
<body class="admin-layout">

    <!-- Admin Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Admin Main Body -->
    <main class="admin-main">
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
                <h1 class="admin-page-title">Deleted Records History</h1>
            </div>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-circle-user" style="font-size:1.2rem; color:var(--accent);"></i>
                <span>System Administrator</span>
            </div>
        </header>

        <div class="admin-content">
            <!-- Filter Controls -->
            <div class="card mb-20" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:16px;">
                <form method="GET" action="trash.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <div style="flex:2; min-width:200px;">
                        <input type="text" name="search" class="form-input" placeholder="Search deleted records..." value="<?= htmlspecialchars($search) ?>" style="margin:0; padding:10px 14px;">
                    </div>
                    <div style="flex:1; min-width:150px;">
                        <select name="type" class="form-select" style="margin:0; padding:10px 14px; width:100%;">
                            <option value="">All Types</option>
                            <option value="user" <?= ($type_filter === 'user') ? 'selected' : '' ?>>User Accounts</option>
                            <option value="vendor" <?= ($type_filter === 'vendor') ? 'selected' : '' ?>>Vendor Profiles</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:10px 20px; font-weight:700;"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                    <a href="trash.php" class="btn btn-outline" style="padding:10px 20px; font-weight:700;">Reset</a>
                </form>
            </div>

            <!-- Deleted Records Table -->
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Record Type</th>
                            <th>Original ID</th>
                            <th>Details</th>
                            <th>Deleted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deleted_records)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:40px; color:var(--gray-400);">No deleted records in history.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($deleted_records as $r): 
                                $rdata = json_decode($r['record_data'], true);
                                $details = '';
                                if ($r['record_type'] === 'user') {
                                    $details = 'Name: <strong>' . htmlspecialchars($rdata['name'] ?? 'N/A') . '</strong><br>Email: ' . htmlspecialchars($rdata['email'] ?? 'N/A') . ' | Phone: ' . htmlspecialchars($rdata['phone'] ?? 'N/A');
                                } else {
                                    $details = 'Business Name: <strong>' . htmlspecialchars($rdata['name'] ?? 'N/A') . '</strong><br>Category: ' . htmlspecialchars($rdata['category'] ?? 'N/A') . ' | Location: ' . htmlspecialchars($rdata['location'] ?? 'N/A');
                                }
                            ?>
                                <tr id="row-<?= $r['id'] ?>">
                                    <td><?= $r['id'] ?></td>
                                    <td>
                                        <span class="booking-status <?= ($r['record_type'] === 'user') ? 'status-pending' : 'status-confirmed' ?>" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; text-transform:uppercase;">
                                            <?= htmlspecialchars($r['record_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $r['record_id'] ?></td>
                                    <td><?= $details ?></td>
                                    <td><?= htmlspecialchars($r['deleted_at']) ?></td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; font-weight:700;" onclick='viewTrashDetails(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Full Raw Record Payload"><i class="fa-solid fa-eye"></i> Details</button>
                                            <button class="btn btn-primary btn-sm" style="padding:6px 10px; font-size:0.75rem;" onclick="restoreRecord(<?= $r['id'] ?>)"><i class="fa-solid fa-trash-arrow-up"></i> Recover</button>
                                            <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="purgeRecord(<?= $r['id'] ?>)"><i class="fa-solid fa-circle-xmark"></i> Purge</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar(open) {
            const sidebar = document.querySelector('.admin-sidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
        }

        function restoreRecord(trashId) {
            if (!confirm('Are you sure you want to restore this record? It will return to the active management console.')) return;
            
            fetch('trash.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ trash_id: trashId, action: 'restore' })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    const row = document.getElementById('row-' + trashId);
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                }
            });
        }

        function purgeRecord(trashId) {
            if (!confirm('WARNING: Are you sure you want to permanently purge this record? This action is irreversible and all backup data will be deleted.')) return;
            
            fetch('trash.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ trash_id: trashId, action: 'purge' })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    const row = document.getElementById('row-' + trashId);
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                }
            });
        }

        function viewTrashDetails(r) {
            const content = document.getElementById('trashDetailsContent');
            if (!content) return;

            let parsed = {};
            try {
                parsed = JSON.parse(r.record_data);
            } catch(e) { parsed = { raw: r.record_data }; }

            content.innerHTML = `
                <div style="text-align:center; margin-bottom:16px;">
                    <div style="font-size:1.6rem; color:var(--rose);"><i class="fa-solid fa-trash-can"></i></div>
                    <h4 style="margin:4px 0 0 0; font-size:1.1rem; font-weight:800; color:var(--primary);">Archived Record #${r.id}</h4>
                    <div style="font-size:0.75rem; color:var(--gray-600); margin-top:2px;">Entity Type: <strong>${r.record_type.toUpperCase()}</strong> | Deleted: ${r.deleted_at}</div>
                </div>

                <div style="font-size:0.8rem; background:var(--gray-50); padding:14px; border-radius:12px; border:1px solid #E5E7EB; margin-bottom:16px; max-height:300px; overflow-y:auto;">
                    <pre style="margin:0; font-family:monospace; font-size:0.75rem; white-space:pre-wrap; word-break:break-all;">${JSON.stringify(parsed, null, 2)}</pre>
                </div>

                <div style="display:flex; gap:10px;">
                    <button class="btn btn-primary" onclick="restoreRecord(${r.id}); closeTrashDetailsModal();" style="flex:1; font-weight:700;">
                        <i class="fa-solid fa-trash-arrow-up"></i> Recover Record
                    </button>
                    <button class="btn btn-outline" onclick="closeTrashDetailsModal()" style="font-weight:700;">Close</button>
                </div>
            `;

            document.getElementById('trashDetailsModal').style.display = 'flex';
        }

        function closeTrashDetailsModal() {
            document.getElementById('trashDetailsModal').style.display = 'none';
        }
    </script>

    <!-- Trash Details Modal -->
    <div id="trashDetailsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:540px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E5E7EB; padding-bottom:12px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">
                    <i class="fa-solid fa-trash-can" style="color:var(--rose);"></i> Archived Deleted Record Payload
                </h3>
                <button onclick="closeTrashDetailsModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--gray-500);">&times;</button>
            </div>
            <div id="trashDetailsContent"></div>
        </div>
    </div>
</body>
</html>
