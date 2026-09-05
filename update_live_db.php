<?php
// update_live_db.php — 1-Click Live Database Migration Tool for Ohati
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ohati Live Database Updater</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background:#0F172A; color:#F8FAFC; padding:40px 20px; text-align:center; }
        .card { max-width:600px; margin:0 auto; background:#1E293B; border-radius:16px; padding:30px; border:1px solid #334155; box-shadow:0 10px 25px rgba(0,0,0,0.3); }
        h2 { color:#F2A735; margin-top:0; }
        .log-box { text-align:left; background:#020617; border:1px solid #1E293B; border-radius:8px; padding:16px; font-family:monospace; font-size:0.85rem; color:#38BDF8; max-height:300px; overflow-y:auto; margin:20px 0; }
        .btn { display:inline-block; background:#F2A735; color:#0F172A; font-weight:800; text-decoration:none; padding:12px 24px; border-radius:8px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Ohati Live Database Migration</h2>
        <p>Updating vendor business logos and user icons in your live MySQL database...</p>
        
        <div class="log-box">
            <?php
            $stmt = $pdo->prepare("UPDATE vendors SET logo = NULL WHERE logo LIKE '%unsplash.com%' AND name NOT LIKE '%Chill & Serve%'");
            $stmt->execute();
            $count = $stmt->rowCount();
            echo "✔ Cleaned $count vendor logo(s) using Unsplash URLs<br>";

            $stmt_cover = $pdo->prepare("UPDATE vendors SET cover_photo = NULL WHERE cover_photo LIKE '%unsplash.com%' AND name NOT LIKE '%Chill & Serve%'");
            $stmt_cover->execute();
            $count_cover = $stmt_cover->rowCount();
            echo "✔ Cleaned $count_cover vendor cover(s) using Unsplash URLs<br>";
            $total_updated = $count + $count_cover;

            // Ensure Chill & Serve Ghana logo is preserved
            $stmt = $pdo->prepare("UPDATE vendors SET logo = 'img/chill/logo.jpg' WHERE name LIKE '%Chill & Serve%'");
            $stmt->execute();
            echo "✔ Preserved Chill & Serve Ghana brand logo<br>";
            
            // Update review avatars in system_settings
            $svg_avatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";
            $rev_stmt = $pdo->prepare("SELECT key_name, val_value FROM system_settings WHERE key_name IN ('platform_reviews', 'pending_platform_reviews')");
            $rev_stmt->execute();
            $rev_rows = $rev_stmt->fetchAll();
            foreach ($rev_rows as $r_row) {
                $r_val = json_decode($r_row['val_value'] ?? '[]', true);
                if (is_array($r_val)) {
                    $r_changed = false;
                    foreach ($r_val as &$rev) {
                        if (empty($rev['avatar']) || strpos($rev['avatar'], 'unsplash.com') !== false || strpos($rev['avatar'], 'photo-') !== false) {
                            $rev['avatar'] = $svg_avatar;
                            $r_changed = true;
                        }
                    }
                    if ($r_changed) {
                        $upd_rev = $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = ?");
                        $upd_rev->execute([json_encode($r_val), $r_row['key_name']]);
                        echo "✔ Updated review avatars in system_settings ('{$r_row['key_name']}')<br>";
                    }
                }
            }
            
            // Deduplicate vendors by name
            try {
                $del_stmt = $pdo->prepare("DELETE FROM vendors WHERE id NOT IN (SELECT min_id FROM (SELECT MIN(id) as min_id FROM vendors GROUP BY name) as t)");
                $del_stmt->execute();
                $del_count = $del_stmt->rowCount();
                echo "✔ Cleaned $del_count duplicate vendor record(s)<br>";
            } catch (Exception $e) {}

            // Clean user table avatars
            try {
                $user_svg = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";
                $u_stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE avatar LIKE '%unsplash.com%' OR avatar LIKE '%photo-%' OR avatar = '' OR avatar IS NULL");
                $u_stmt->execute([$user_svg]);
                $u_count = $u_stmt->rowCount();
                echo "✔ Cleaned $u_count user avatar(s) with SVG user icon<br>";
            } catch (Exception $e) {}

            echo "<br><strong>Done! Total Records Updated: $total_updated</strong>";
            ?>
        </div>

        <p style="color:#10B981; font-weight:700;">Live Database Update Completed Successfully!</p>
        <a href="index.php" class="btn">Return to Ohati Website</a>
    </div>
</body>
</html>
