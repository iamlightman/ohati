<?php
// upload_avatar_standalone.php — Standalone Safe Profile Picture Uploader
date_default_timezone_set('Africa/Accra');
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/storage_helper.php';

$message = '';
$error = '';
$current_user = $_SESSION['user'] ?? null;

// Fetch users for selector if not logged in
$all_users = [];
try {
    $stmt = $pdo->query("SELECT id, name, email, role, avatar FROM users ORDER BY id ASC LIMIT 50");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Handle POST Avatar Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    $target_uid = intval($_POST['user_id'] ?? ($current_user['id'] ?? 0));
    
    if ($target_uid <= 0) {
        $error = 'Please select a user account to update.';
    } elseif (empty($_FILES['avatar_file']['tmp_name']) || !is_uploaded_file($_FILES['avatar_file']['tmp_name'])) {
        $error = 'Please select an image file to upload.';
    } else {
        $file = $_FILES['avatar_file'];
        $max_size = 5 * 1024 * 1024; // 5MB limit
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        
        $mime = mime_content_type($file['tmp_name']);
        if ($file['size'] > $max_size) {
            $error = 'File is too large. Maximum size is 5MB.';
        } elseif (!in_array($mime, $allowed_mimes)) {
            $error = 'Invalid image format. Allowed formats: JPG, PNG, WebP.';
        } else {
            // Upload physical file using storage_helper
            $upload_res = upload_media_file($file, 'avatars', 800);
            
            if (!empty($upload_res['success']) && !empty($upload_res['url'])) {
                $new_avatar_path = $upload_res['url'];
                
                try {
                    // 1. Update users database table
                    $up = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $up->execute([$new_avatar_path, $target_uid]);
                    
                    // 2. Sync vendors logo if vendor account
                    $v_chk = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
                    $v_chk->execute([$target_uid]);
                    $vendor_id = $v_chk->fetchColumn();
                    if ($vendor_id) {
                        $pdo->prepare("UPDATE vendors SET logo = ? WHERE id = ?")->execute([$new_avatar_path, $vendor_id]);
                    }
                    
                    // 3. Refetch updated user record
                    $ref = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $ref->execute([$target_uid]);
                    $updated_user = $ref->fetch(PDO::FETCH_ASSOC);
                    
                    if ($updated_user) {
                        $_SESSION['user'] = $updated_user;
                        $_SESSION['user_id'] = $updated_user['id'];
                        $current_user = $updated_user;
                    }
                    
                    $message = "Profile picture updated successfully! New image saved to: " . htmlspecialchars($new_avatar_path);
                } catch (Exception $ex) {
                    $error = "Database update error: " . $ex->getMessage();
                }
            } else {
                $error = "Upload failed: " . ($upload_res['error'] ?? 'Unknown upload error.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati — Safe Profile Picture Uploader</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0F172A;
            --accent: #D4AF37;
            --bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --text: #1E293B;
            --border: #E2E8F0;
            --success: #10B981;
            --danger: #EF4444;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .upload-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            width: 100%;
            max-width: 520px;
            padding: 32px;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header h2 {
            margin: 0 0 8px 0;
            font-size: 1.5rem;
            color: var(--primary);
        }
        .header p {
            margin: 0;
            color: #64748B;
            font-size: 0.875rem;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 20px;
        }
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-danger { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        .preview-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding: 20px;
            background: #F1F5F9;
            border-radius: 12px;
        }
        .avatar-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 8px;
            color: var(--primary);
        }
        select, input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 0.875rem;
            background: #FFF;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: #FFF;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            background: #1E293B;
        }
        .path-badge {
            font-family: monospace;
            background: #E2E8F0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            word-break: break-all;
        }
    </style>
</head>
<body>

<div class="upload-card">
    <div class="header">
        <h2><i class="fa-solid fa-user-gear" style="color:var(--accent);"></i> Profile Image Uploader</h2>
        <p>Safely update and verify profile photo storage in database & physical uploads directory.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Current User Avatar Preview -->
    <div class="preview-box">
        <?php 
            $curr_av = $current_user['avatar'] ?? '';
            $display_av = !empty($curr_av) ? (strpos($curr_av, 'http') === 0 ? $curr_av : $curr_av . '?v=' . time()) : "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%230F172A'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";
        ?>
        <img src="<?= $display_av ?>" alt="Avatar Preview" class="avatar-img">
        <div>
            <strong><?= htmlspecialchars($current_user['name'] ?? 'Guest / Selected User') ?></strong>
            <?php if (!empty($curr_av)): ?>
                <div style="margin-top:4px;"><span class="path-badge"><?= htmlspecialchars($curr_av) ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload Form -->
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_avatar">

        <div class="form-group">
            <label for="user_id"><i class="fa-solid fa-user"></i> Select User Account</label>
            <select name="user_id" id="user_id" required>
                <?php foreach ($all_users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($current_user && $current_user['id'] == $u['id']) ? 'selected' : '' ?>>
                        #<?= $u['id'] ?> — <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email'] ?: 'No Email') ?>) [<?= strtoupper($u['role']) ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="avatar_file"><i class="fa-solid fa-cloud-arrow-up"></i> Choose New Profile Image</label>
            <input type="file" name="avatar_file" id="avatar_file" accept="image/jpeg,image/png,image/webp" required>
            <span style="font-size:0.75rem; color:#64748B; margin-top:4px; display:block;">Supported formats: JPG, PNG, WebP (Max size: 5MB)</span>
        </div>

        <button type="submit" class="btn-submit"><i class="fa-solid fa-upload"></i> Upload & Update Profile</button>
    </form>
</div>

</body>
</html>
