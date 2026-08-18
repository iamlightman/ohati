<?php
// admin/deleted_accounts.php — Admin Management for Soft-Deleted User Accounts
session_start();
require_once '../db.php';

// Auth Guard for Admin
if (empty($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$msg = '';
$err = '';

// Handle Admin Actions (Restore / Permanent Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id > 0) {
        if ($action === 'restore') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active', is_active = 1, deleted_at = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            $msg = "User account #{$user_id} has been restored to active status!";
        } else if ($action === 'purge') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $msg = "User account #{$user_id} record permanently purged from database.";
        }
    }
}

// Fetch all soft deleted accounts
$deleted_users = [];
try {
    $stmt = $pdo->query("SELECT * FROM users WHERE status = 'deleted' OR deleted_at IS NOT NULL ORDER BY id DESC");
    $deleted_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Accounts — Ohati Admin</title>
    <link rel="icon" type="image/png" href="../img/app_icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background: #0F172A; color: #F8FAFC; font-family: 'Plus Jakarta Sans', sans-serif; }
        .table-responsive { width: 100%; overflow-x: auto; background: #1E293B; border-radius: 16px; border: 1px solid #334155; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 14px 18px; border-bottom: 1px solid #334155; font-size: 0.85rem; }
        th { background: #0F172A; color: #94A3B8; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; }
        tr:hover { background: rgba(255,255,255,0.02); }
        .badge-del { background: rgba(239,68,68,0.2); color: #EF4444; border: 1px solid rgba(239,68,68,0.4); padding: 3px 8px; border-radius: 12px; font-weight: 700; font-size: 0.75rem; }
    </style>
</head>
<body>

<div class="admin-layout">
    <?php include 'sidebar.php'; ?>

    <main class="admin-main" style="padding: 28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
            <div>
                <h1 style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:800; color:#fff; margin:0;">
                    <i class="fa-solid fa-user-slash" style="color:#EF4444;"></i> Deleted Accounts
                </h1>
                <p style="color:#94A3B8; font-size:0.85rem; margin-top:4px;">Archived user accounts retained in database for administrative compliance.</p>
            </div>
            <span class="badge-del" style="font-size:0.85rem; padding:6px 14px;"><?= count($deleted_users) ?> Account(s) Archived</span>
        </div>

        <?php if ($msg): ?>
            <div style="background:rgba(16,185,129,0.15); border:1px solid #10B981; color:#6EE7B7; padding:12px 16px; border-radius:12px; margin-bottom:20px; font-size:0.85rem;">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>User Info</th>
                        <th>Role</th>
                        <th>Deletion Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deleted_users)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:32px; color:#64748B;">
                                <i class="fa-solid fa-folder-open" style="font-size:2rem; margin-bottom:8px; display:block;"></i>
                                No deleted user accounts found in database.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deleted_users as $du): ?>
                            <tr>
                                <td><strong>#<?= $du['id'] ?></strong></td>
                                <td>
                                    <div style="font-weight:700; color:#fff;"><?= htmlspecialchars($du['name'] ?? 'User') ?></div>
                                    <div style="font-size:0.75rem; color:#94A3B8;"><?= htmlspecialchars($du['email'] ?? '') ?> | <?= htmlspecialchars($du['phone'] ?? '') ?></div>
                                </td>
                                <td><span style="text-transform:capitalize; font-weight:600; color:#CBD5E1;"><?= htmlspecialchars($du['role'] ?? 'customer') ?></span></td>
                                <td><span style="color:#94A3B8; font-size:0.8rem;"><?= htmlspecialchars($du['deleted_at'] ?? 'Recently') ?></span></td>
                                <td><span class="badge-del">Deleted</span></td>
                                <td>
                                    <div style="display:flex; gap:8px;">
                                        <form method="POST" onsubmit="return confirm('Restore this account to active status?');" style="display:inline;">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="user_id" value="<?= $du['id'] ?>">
                                            <button type="submit" style="background:#10B981; color:#fff; border:none; padding:6px 12px; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.75rem;">
                                                <i class="fa-solid fa-rotate-left"></i> Restore
                                            </button>
                                        </form>

                                        <form method="POST" onsubmit="return confirm('Permanently purge this record from DB? Cannot be undone.');" style="display:inline;">
                                            <input type="hidden" name="action" value="purge">
                                            <input type="hidden" name="user_id" value="<?= $du['id'] ?>">
                                            <button type="submit" style="background:rgba(239,68,68,0.2); color:#EF4444; border:1px solid #EF4444; padding:6px 12px; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.75rem;">
                                                <i class="fa-solid fa-trash"></i> Purge
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>