<?php
// admin/deleted_accounts.php — Admin Management for Soft-Deleted & Archived User Accounts
session_start();
require_once '../db.php';
require_once '../storage_helper.php';

// Auth Guard for Admin
if (empty($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$msg = '';
$err = '';
$show_undo = false;

// Handle Admin Actions (Restore / Purge / Undo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);
    $trash_id = intval($_POST['trash_id'] ?? 0);

    // UNDO ACTION
    if ($action === 'undo') {
        $last = $_SESSION['last_deleted_acc_action'] ?? null;
        if ($last) {
            try {
                if ($last['type'] === 'restore') {
                    $uid = intval($last['user_id']);
                    $pdo->prepare("UPDATE users SET status = 'deleted', is_active = 0, deleted_at = ? WHERE id = ?")
                        ->execute([date('Y-m-d H:i:s'), $uid]);
                    $pdo->prepare("UPDATE vendors SET is_active = 0 WHERE user_id = ?")->execute([$uid]);
                    $msg = "Undo successful: Account #{$uid} has been marked as deleted again.";
                } else if ($last['type'] === 'purge') {
                    if (!empty($last['backup_data'])) {
                        $stmt = $pdo->prepare("INSERT INTO deleted_records (record_type, record_id, record_data, deleted_at) VALUES (?, ?, ?, ?)");
                        $stmt->execute([
                            $last['record_type'] ?? 'user_account_deletion',
                            $last['user_id'],
                            $last['backup_data'],
                            $last['deleted_at'] ?? date('Y-m-d H:i:s')
                        ]);
                        $msg = "Undo successful: Purged record #{$last['user_id']} has been restored to deleted archives.";
                    }
                }
                unset($_SESSION['last_deleted_acc_action']);
            } catch (Exception $ex) {
                $err = "Failed to undo action: " . $ex->getMessage();
            }
        } else {
            $err = "No previous action available to undo.";
        }
    }
    // RESTORE ACTION
    else if ($action === 'restore') {
        try {
            $pdo->beginTransaction();
            $restored_uid = 0;

            // Soft-deleted in users table
            if ($user_id > 0) {
                $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $chk->execute([$user_id]);
                if ($chk->fetch()) {
                    $pdo->prepare("UPDATE users SET status = 'active', is_active = 1, email_verified = 1, phone_verified = 1, deleted_at = NULL WHERE id = ?")->execute([$user_id]);
                    $pdo->prepare("UPDATE vendors SET is_active = 1, verification_status = 'approved' WHERE user_id = ?")->execute([$user_id]);
                    $restored_uid = $user_id;
                }
            }

            // Archived in deleted_records table
            if ($trash_id > 0) {
                $stmtT = $pdo->prepare("SELECT * FROM deleted_records WHERE id = ?");
                $stmtT->execute([$trash_id]);
                $tRow = $stmtT->fetch(PDO::FETCH_ASSOC);

                if ($tRow) {
                    $raw_data = json_decode($tRow['record_data'], true);
                    $u_data = $raw_data['user'] ?? $raw_data;

                    if (is_array($u_data)) {
                        $uid_target = intval($u_data['id'] ?? $tRow['record_id']);
                        $chkU = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                        $chkU->execute([$uid_target]);

                        if ($chkU->fetch()) {
                            $pdo->prepare("UPDATE users SET status = 'active', is_active = 1, email_verified = 1, phone_verified = 1, deleted_at = NULL WHERE id = ?")->execute([$uid_target]);
                            $pdo->prepare("UPDATE vendors SET is_active = 1, verification_status = 'approved' WHERE user_id = ?")->execute([$uid_target]);
                        } else {
                            // Clean keys not present in users schema
                            unset($u_data['reason']);
                            $valid_cols = ['id', 'name', 'email', 'phone', 'username', 'password_hash', 'role', 'avatar', 'gender', 'dob', 'country', 'state', 'city', 'language', 'currency', 'kyc_status', 'two_fa_enabled', 'is_active', 'email_verified', 'phone_verified', 'last_login', 'login_count', 'created_at'];
                            $insert_data = [];
                            foreach ($u_data as $k => $v) {
                                if (in_array($k, $valid_cols)) {
                                    $insert_data[$k] = $v;
                                }
                            }
                            if (!empty($insert_data)) {
                                $cols = array_keys($insert_data);
                                $cols_str = implode('`, `', $cols);
                                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                                $sqlIns = "INSERT INTO users (`{$cols_str}`) VALUES ({$placeholders})";
                                $pdo->prepare($sqlIns)->execute(array_values($insert_data));
                                $pdo->prepare("UPDATE users SET status = 'active', is_active = 1, email_verified = 1, phone_verified = 1, deleted_at = NULL WHERE id = ?")->execute([$uid_target]);
                            }
                        }
                        $restored_uid = $uid_target;
                    }
                    $pdo->prepare("DELETE FROM deleted_records WHERE id = ?")->execute([$trash_id]);
                }
            }

            if ($restored_uid > 0) {
                $pdo->prepare("DELETE FROM deleted_records WHERE record_id = ? AND record_type IN ('user', 'user_account_deletion', 'web_account_deletion')")->execute([$restored_uid]);
                $_SESSION['last_deleted_acc_action'] = [
                    'type' => 'restore',
                    'user_id' => $restored_uid
                ];
                $msg = "User account #{$restored_uid} has been restored to active status!";
                $show_undo = true;
            } else {
                $err = "Unable to locate account record to restore.";
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = "Error restoring account: " . $e->getMessage();
        }
    }
    // PURGE ACTION
    else if ($action === 'purge') {
        try {
            $pdo->beginTransaction();
            $backup_record = null;
            $purged_uid = $user_id;

            if ($user_id > 0) {
                $selU = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $selU->execute([$user_id]);
                $uRow = $selU->fetch(PDO::FETCH_ASSOC);
                if ($uRow) {
                    $backup_record = json_encode($uRow);
                }

                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM vendors WHERE user_id = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM deleted_records WHERE record_id = ? AND record_type IN ('user', 'user_account_deletion', 'web_account_deletion')")->execute([$user_id]);
            }

            if ($trash_id > 0) {
                $stmtT = $pdo->prepare("SELECT * FROM deleted_records WHERE id = ?");
                $stmtT->execute([$trash_id]);
                $tRow = $stmtT->fetch(PDO::FETCH_ASSOC);
                if ($tRow) {
                    $backup_record = $tRow['record_data'];
                    $purged_uid = $tRow['record_id'];
                }
                $pdo->prepare("DELETE FROM deleted_records WHERE id = ?")->execute([$trash_id]);
            }

            if ($purged_uid > 0) {
                $_SESSION['last_deleted_acc_action'] = [
                    'type' => 'purge',
                    'user_id' => $purged_uid,
                    'record_type' => 'user_account_deletion',
                    'backup_data' => $backup_record,
                    'deleted_at' => date('Y-m-d H:i:s')
                ];
                $msg = "User account #{$purged_uid} record permanently purged from database.";
                $show_undo = true;
            } else {
                $err = "Unable to locate account record to purge.";
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = "Error purging account record: " . $e->getMessage();
        }
    }
}

// Search and List Retrieval
$search = trim($_GET['search'] ?? '');
$deleted_users = [];
$seen_user_ids = [];

// 1. Soft-deleted users in `users` table
try {
    $sqlU = "SELECT id, name, email, phone, role, avatar, status, is_active, created_at, deleted_at, 'soft' AS source, 0 AS trash_id, '' AS reason FROM users WHERE (status = 'deleted' OR is_active = 0 OR deleted_at IS NOT NULL)";
    if (!empty($search)) {
        $sqlU .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR id = ?)";
        $stmtU = $pdo->prepare($sqlU . " ORDER BY id DESC");
        $term = "%$search%";
        $stmtU->execute([$term, $term, $term, intval($search)]);
    } else {
        $stmtU = $pdo->query($sqlU . " ORDER BY id DESC");
    }
    while ($u = $stmtU->fetch(PDO::FETCH_ASSOC)) {
        $deleted_users[] = $u;
        $seen_user_ids[$u['id']] = true;
    }
} catch (Exception $e) {}

// 2. Archived users in `deleted_records` table
try {
    $sqlT = "SELECT * FROM deleted_records WHERE record_type IN ('user', 'user_account_deletion', 'web_account_deletion') ORDER BY id DESC";
    $stmtT = $pdo->query($sqlT);
    while ($t = $stmtT->fetch(PDO::FETCH_ASSOC)) {
        $rec_id = intval($t['record_id']);
        if (isset($seen_user_ids[$rec_id])) {
            continue;
        }

        $data = json_decode($t['record_data'], true);
        $u_data = $data['user'] ?? $data;

        $name = $u_data['name'] ?? "Archived User #{$rec_id}";
        $email = $u_data['email'] ?? '';
        $phone = $u_data['phone'] ?? '';
        $role = $u_data['role'] ?? 'customer';
        $reason = $data['reason'] ?? '';

        if (!empty($search)) {
            $s_term = strtolower($search);
            if (strpos(strtolower($name), $s_term) === false &&
                strpos(strtolower($email), $s_term) === false &&
                strpos(strtolower($phone), $s_term) === false &&
                strval($rec_id) !== $search) {
                continue;
            }
        }

        $deleted_users[] = [
            'id' => $rec_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'avatar' => $u_data['avatar'] ?? '',
            'status' => 'archived',
            'is_active' => 0,
            'created_at' => $u_data['created_at'] ?? '',
            'deleted_at' => $t['deleted_at'] ?? '',
            'source' => 'trash',
            'trash_id' => $t['id'],
            'reason' => $reason
        ];
        $seen_user_ids[$rec_id] = true;
    }
} catch (Exception $e) {}
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
        th, td { padding: 14px 18px; border-bottom: 1px solid #334155; font-size: 0.85rem; vertical-align: middle; }
        th { background: #0F172A; color: #94A3B8; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; }
        tr:hover { background: rgba(255,255,255,0.02); }
        .badge-del { background: rgba(239,68,68,0.2); color: #EF4444; border: 1px solid rgba(239,68,68,0.4); padding: 3px 8px; border-radius: 12px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; }
        .badge-archived { background: rgba(245,158,11,0.2); color: #F59E0B; border: 1px solid rgba(245,158,11,0.4); padding: 3px 8px; border-radius: 12px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; }
        .avatar-img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid #334155; background: #0F172A; }
        .search-box { background: #1E293B; border: 1px solid #334155; color: #F8FAFC; padding: 10px 16px; border-radius: 12px; font-size: 0.85rem; width: 300px; outline: none; }
        .search-box:focus { border-color: #F2A735; }
        .btn-undo { background: #F2A735; color: #0F172A; border: none; padding: 6px 14px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; }
        .btn-undo:hover { background: #E09520; }
    </style>
</head>
<body>

<div class="admin-layout">
    <?php include 'sidebar.php'; ?>

    <main class="admin-main" style="padding: 28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; flex-wrap:wrap; gap:16px;">
            <div>
                <h1 style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:800; color:#fff; margin:0; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-user-slash" style="color:#EF4444;"></i> Deleted Accounts Console
                </h1>
                <p style="color:#94A3B8; font-size:0.85rem; margin-top:4px;">Manage soft-deleted & archived user accounts with 1-click restore or undo.</p>
            </div>
            
            <div style="display:flex; align-items:center; gap:12px;">
                <form method="GET" style="margin:0;">
                    <input type="text" name="search" class="search-box" placeholder="Search by ID, name, email or phone..." value="<?= htmlspecialchars($search) ?>">
                </form>
                <span class="badge-del" style="font-size:0.85rem; padding:8px 16px;">
                    <i class="fa-solid fa-box-archive"></i> <?= count($deleted_users) ?> Account(s) Archived
                </span>
            </div>
        </div>

        <?php if ($msg): ?>
            <div style="background:rgba(16,185,129,0.15); border:1px solid #10B981; color:#6EE7B7; padding:14px 18px; border-radius:12px; margin-bottom:20px; font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <i class="fa-solid fa-circle-check" style="font-size:1.1rem; margin-right:8px;"></i> <?= htmlspecialchars($msg) ?>
                </div>
                <?php if ($show_undo || !empty($_SESSION['last_deleted_acc_action'])): ?>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="undo">
                        <button type="submit" class="btn-undo">
                            <i class="fa-solid fa-rotate-left"></i> Undo Action
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div style="background:rgba(239,68,68,0.15); border:1px solid #EF4444; color:#FCA5A5; padding:14px 18px; border-radius:12px; margin-bottom:20px; font-size:0.85rem;">
                <i class="fa-solid fa-circle-exclamation" style="font-size:1.1rem; margin-right:8px;"></i> <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>User Profile</th>
                        <th>Role</th>
                        <th>Deletion Date</th>
                        <th>Storage State</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deleted_users)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:48px; color:#64748B;">
                                <i class="fa-solid fa-folder-open" style="font-size:2.5rem; margin-bottom:12px; display:block; color:#475569;"></i>
                                No deleted user accounts found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deleted_users as $du): ?>
                            <?php 
                                $avatar_url = !empty($du['avatar']) ? format_full_image_url($du['avatar']) : '../img/default_avatar.png';
                            ?>
                            <tr>
                                <td><strong>#<?= $du['id'] ?></strong></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <img src="<?= htmlspecialchars($avatar_url) ?>" class="avatar-img" alt="Avatar" onerror="this.src='../img/app_icon.png';">
                                        <div>
                                            <div style="font-weight:700; color:#fff; font-size:0.9rem;"><?= htmlspecialchars($du['name'] ?? 'User') ?></div>
                                            <div style="font-size:0.75rem; color:#94A3B8;">
                                                <?= htmlspecialchars($du['email'] ?? 'No Email') ?> 
                                                <?php if (!empty($du['phone'])): ?> | <?= htmlspecialchars($du['phone']) ?><?php endif; ?>
                                            </div>
                                            <?php if (!empty($du['reason'])): ?>
                                                <div style="font-size:0.7rem; color:#F59E0B; margin-top:2px;">Reason: <?= htmlspecialchars($du['reason']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><span style="text-transform:capitalize; font-weight:600; color:#CBD5E1;"><?= htmlspecialchars($du['role'] ?? 'customer') ?></span></td>
                                <td><span style="color:#94A3B8; font-size:0.8rem;"><?= htmlspecialchars($du['deleted_at'] ?: ($du['created_at'] ?: 'Recently')) ?></span></td>
                                <td>
                                    <?php if (($du['source'] ?? '') === 'trash'): ?>
                                        <span class="badge-archived"><i class="fa-solid fa-box-archive"></i> Archived</span>
                                    <?php else: ?>
                                        <span class="badge-del"><i class="fa-solid fa-user-slash"></i> Soft Deleted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:8px;">
                                        <form method="POST" onsubmit="return confirm('Restore user account #<?= $du['id'] ?> back to active status?');" style="display:inline;">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="user_id" value="<?= $du['id'] ?>">
                                            <input type="hidden" name="trash_id" value="<?= $du['trash_id'] ?? 0 ?>">
                                            <button type="submit" style="background:#10B981; color:#fff; border:none; padding:7px 14px; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.75rem; display:inline-flex; align-items:center; gap:6px;">
                                                <i class="fa-solid fa-rotate-left"></i> Restore Account
                                            </button>
                                        </form>

                                        <form method="POST" onsubmit="return confirm('PERMANENTLY PURGE account #<?= $du['id'] ?> from the database? This cannot be easily reversed.');" style="display:inline;">
                                            <input type="hidden" name="action" value="purge">
                                            <input type="hidden" name="user_id" value="<?= $du['id'] ?>">
                                            <input type="hidden" name="trash_id" value="<?= $du['trash_id'] ?? 0 ?>">
                                            <button type="submit" style="background:rgba(239,68,68,0.2); color:#EF4444; border:1px solid #EF4444; padding:7px 14px; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.75rem; display:inline-flex; align-items:center; gap:6px;">
                                                <i class="fa-solid fa-trash-can"></i> Purge
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