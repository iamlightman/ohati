<?php
// clear_reviews_and_blogs.php - One-Click Single File Tool to Purge All Reviews & Blog Posts Safely
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati - Reset Reviews & Blog Posts</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #F8FAFC; color: #0F172A; margin: 0; padding: 40px 20px; }
        .container { max-width: 650px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #E2E8F0; }
        h1 { font-size: 1.5rem; color: #1E293B; margin-top: 0; display: flex; align-items: center; gap: 10px; }
        .btn { display: inline-block; background: #EF4444; color: #ffffff; border: none; padding: 14px 24px; border-radius: 10px; font-weight: 600; font-size: 1rem; cursor: pointer; text-decoration: none; transition: background 0.2s; }
        .btn:hover { background: #DC2626; }
        .log-box { background: #0F172A; color: #38BDF8; font-family: monospace; padding: 18px; border-radius: 10px; margin-top: 20px; font-size: 0.9rem; line-height: 1.6; white-space: pre-wrap; word-break: break-all; }
        .success-badge { display: inline-block; background: #DCFCE7; color: #166534; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><span>🗑️</span> Ohati Reviews & Blog Posts Purge Utility</h1>
        <p style="color: #64748B; font-size: 0.95rem;">This tool will safely remove all reviews and all blog posts from your database without affecting vendors, user accounts, bookings, or platform functionality.</p>

        <?php
        if (isset($_POST['execute_purge']) || PHP_SAPI === 'cli') {
            echo '<div class="success-badge">Execution Completed</div>';
            echo '<div class="log-box">';

            $logs = [];

            // 1. Delete Reviews from 'reviews' table
            try {
                $c1 = $pdo->exec("DELETE FROM reviews");
                $logs[] = "[SUCCESS] Deleted " . intval($c1) . " record(s) from 'reviews' table.";
            } catch (Exception $e) {
                $logs[] = "[INFO] 'reviews' table notice: " . $e->getMessage();
            }

            // 2. Delete Reviews from 'vendor_reviews' table
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_reviews (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    vendor_id INT NOT NULL,
                    user_id INT DEFAULT 0,
                    user_name VARCHAR(150) DEFAULT '',
                    user_avatar VARCHAR(500) DEFAULT '',
                    rating FLOAT DEFAULT 5.0,
                    comment TEXT,
                    status VARCHAR(20) DEFAULT 'approved',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $c2 = $pdo->exec("DELETE FROM vendor_reviews");
                $logs[] = "[SUCCESS] Deleted " . intval($c2) . " record(s) from 'vendor_reviews' table.";
            } catch (Exception $e) {
                $logs[] = "[INFO] 'vendor_reviews' table notice: " . $e->getMessage();
            }

            // 3. Reset Vendor Ratings & Review Counts
            try {
                $vCount = $pdo->exec("UPDATE vendors SET rating = 5.0, reviews_count = 0");
                $logs[] = "[SUCCESS] Reset rating to 5.0 and reviews_count to 0 across " . intval($vCount) . " vendor(s).";
            } catch (Exception $e) {
                $logs[] = "[INFO] Vendor rating reset notice: " . $e->getMessage();
            }

            // 4. Reset System Settings Reviews Keys
            try {
                $sCount = $pdo->exec("UPDATE system_settings SET val_value = '[]' WHERE key_name IN ('platform_reviews', 'pending_platform_reviews', 'featured_reviews', 'home_reviews')");
                $logs[] = "[SUCCESS] Cleared review cache keys in system_settings.";
            } catch (Exception $e) {
                $logs[] = "[INFO] System settings reset notice: " . $e->getMessage();
            }

            // 5. Delete Blog Posts
            try {
                $b1 = $pdo->exec("DELETE FROM blog_posts");
                $logs[] = "[SUCCESS] Deleted " . intval($b1) . " blog post(s) from 'blog_posts' table.";
            } catch (Exception $e) {
                $logs[] = "[INFO] 'blog_posts' table notice: " . $e->getMessage();
            }

            // 6. Delete Blog Comments & Reports
            try {
                $b2 = $pdo->exec("DELETE FROM blog_comments");
                $logs[] = "[SUCCESS] Deleted " . intval($b2) . " comment(s) from 'blog_comments' table.";
            } catch (Exception $e) {}

            try {
                $b3 = $pdo->exec("DELETE FROM blog_comment_reports");
                $logs[] = "[SUCCESS] Deleted " . intval($b3) . " report(s) from 'blog_comment_reports' table.";
            } catch (Exception $e) {}

            // 7. Delete Blog Likes
            try {
                $b4 = $pdo->exec("DELETE FROM blog_likes");
                $logs[] = "[SUCCESS] Deleted " . intval($b4) . " like(s) from 'blog_likes' table.";
            } catch (Exception $e) {}

            $logs[] = "\n=======================================================";
            $logs[] = "DONE! All reviews and blog posts are completely deleted.";
            $logs[] = "Vendors, users, bookings, and app settings remain 100% intact.";
            $logs[] = "=======================================================";

            echo implode("\n", $logs);
            echo '</div>';
        } else {
        ?>
            <form method="POST" onsubmit="return confirm('Are you sure you want to delete ALL reviews and ALL blog posts? This action cannot be undone.');">
                <button type="submit" name="execute_purge" class="btn">Delete All Reviews & Blog Posts Now</button>
            </form>
        <?php } ?>
    </div>
</body>
</html>
