<?php
// manage_reviews_and_vendors.php - Management & Deletion Tool for Ohati Vendors and Reviews
require_once __DIR__ . '/db.php';
session_start();

$message = '';
$messageType = 'success';

// Ensure tables exist
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
} catch (Exception $e) {}

// Handle Deletion Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_vendors') {
        $vendor_ids = $_POST['vendor_ids'] ?? [];
        if (!empty($vendor_ids) && is_array($vendor_ids)) {
            $ids = array_map('intval', $vendor_ids);
            $in_clause = implode(',', $ids);
            try {
                $count = $pdo->exec("DELETE FROM vendors WHERE id IN ($in_clause)");
                $message = "Successfully deleted $count vendor(s).";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error deleting vendors: " . $e->getMessage();
                $messageType = "danger";
            }
        } else {
            $message = "No vendors selected for deletion.";
            $messageType = "warning";
        }
    } elseif ($action === 'delete_all_vendors') {
        try {
            $count = $pdo->exec("DELETE FROM vendors");
            $message = "Successfully deleted ALL $count vendor records from the database.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error deleting all vendors: " . $e->getMessage();
            $messageType = "danger";
        }
    } elseif ($action === 'delete_reviews') {
        $review_ids = $_POST['review_ids'] ?? [];
        if (!empty($review_ids) && is_array($review_ids)) {
            $ids = array_map('intval', $review_ids);
            $in_clause = implode(',', $ids);
            try {
                $count = $pdo->exec("DELETE FROM vendor_reviews WHERE id IN ($in_clause)");
                $message = "Successfully deleted $count review(s).";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error deleting reviews: " . $e->getMessage();
                $messageType = "danger";
            }
        } else {
            $message = "No reviews selected for deletion.";
            $messageType = "warning";
        }
    } elseif ($action === 'delete_all_reviews') {
        try {
            $count = $pdo->exec("DELETE FROM vendor_reviews");
            // Also reset platform reviews in system_settings if present
            try {
                $pdo->exec("UPDATE system_settings SET val_value = '[]' WHERE key_name = 'platform_reviews' OR key_name = 'pending_platform_reviews'");
            } catch (Exception $ex) {}
            $message = "Successfully deleted ALL $count review records from the database.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error deleting all reviews: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch all vendors
$vendors = [];
try {
    $v_stmt = $pdo->query("SELECT id, name, category, location, phone, email, rating, logo, created_at FROM vendors ORDER BY id DESC");
    $vendors = $v_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch all reviews
$reviews = [];
try {
    $r_stmt = $pdo->query("SELECT r.*, v.name as vendor_name FROM vendor_reviews r LEFT JOIN vendors v ON r.vendor_id = v.id ORDER BY r.id DESC");
    $reviews = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati — Manage & Delete Vendors & Reviews</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1B2B4B;
            --accent: #F2A735;
            --danger: #EF4444;
            --success: #10B981;
            --warning: #F59E0B;
            --bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --text: #0F172A;
            --border: #E2E8F0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--primary);
            color: #fff;
            padding: 20px 28px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px rgba(27,43,75,0.15);
        }
        .header h1 {
            margin: 0;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-danger { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }
        .alert-warning { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
        
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 12px 24px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .tab-content {
            display: none;
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .tab-content.active {
            display: block;
        }
        .actions-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }
        th, td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: var(--bg);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748B;
        }
        tr:hover {
            background: #F1F5F9;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #DC2626; }
        .btn-warning { background: var(--warning); color: #fff; }
        .btn-secondary { background: #64748B; color: #fff; }
        .avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-trash-can" style="color:var(--accent);"></i> Ohati Management Tool: Vendors & Reviews</h1>
            <span><i class="fa-solid fa-database"></i> Database: <strong>ohati.db</strong></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('vendors')">
                <i class="fa-solid fa-store"></i> Vendors (<?= count($vendors) ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('reviews')">
                <i class="fa-solid fa-star"></i> Reviews (<?= count($reviews) ?>)
            </button>
        </div>

        <!-- VENDORS TAB -->
        <div id="tab-vendors" class="tab-content active">
            <form method="POST" id="form-vendors" onsubmit="return confirm('Are you sure you want to delete the selected vendors?');">
                <input type="hidden" name="action" value="delete_vendors">
                
                <div class="actions-bar">
                    <div>
                        <button type="button" class="btn btn-secondary" onclick="toggleSelectAll('form-vendors', true)">Select All</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleSelectAll('form-vendors', false)">Deselect All</button>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fa-solid fa-trash"></i> Delete Selected Vendors
                        </button>
                        <button type="button" class="btn btn-warning" onclick="deleteAllVendors()">
                            <i class="fa-solid fa-triangle-exclamation"></i> Delete ALL Vendors
                        </button>
                    </div>
                </div>

                <?php if (empty($vendors)): ?>
                    <p style="text-align:center; padding:40px; color:#64748B;">No vendors found in database.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px;"><input type="checkbox" onclick="toggleSelectAll('form-vendors', this.checked)"></th>
                                <th>ID</th>
                                <th>Logo</th>
                                <th>Business Name</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Phone / Email</th>
                                <th>Rating</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendors as $v): ?>
                                <tr>
                                    <td><input type="checkbox" name="vendor_ids[]" value="<?= $v['id'] ?>"></td>
                                    <td>#<?= $v['id'] ?></td>
                                    <td>
                                        <img src="<?= htmlspecialchars($v['logo'] ?: 'img/default-avatar.png') ?>" class="avatar-sm" onerror="this.src='img/default-avatar.png'">
                                    </td>
                                    <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
                                    <td><span style="background:#FEF3C7; color:#D97706; padding:2px 8px; border-radius:10px; font-size:0.75rem; font-weight:700;"><?= htmlspecialchars($v['category']) ?></span></td>
                                    <td><?= htmlspecialchars($v['location'] ?: 'Ghana') ?></td>
                                    <td><?= htmlspecialchars($v['phone'] ?: $v['email']) ?></td>
                                    <td><i class="fa-solid fa-star" style="color:var(--accent);"></i> <?= number_format($v['rating'] ?: 5.0, 1) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-danger" style="padding:4px 10px; font-size:0.75rem;" onclick="deleteSingleVendor(<?= $v['id'] ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </form>

            <form method="POST" id="form-delete-all-vendors" style="display:none;">
                <input type="hidden" name="action" value="delete_all_vendors">
            </form>
        </div>

        <!-- REVIEWS TAB -->
        <div id="tab-reviews" class="tab-content">
            <form method="POST" id="form-reviews" onsubmit="return confirm('Are you sure you want to delete the selected reviews?');">
                <input type="hidden" name="action" value="delete_reviews">
                
                <div class="actions-bar">
                    <div>
                        <button type="button" class="btn btn-secondary" onclick="toggleSelectAll('form-reviews', true)">Select All</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleSelectAll('form-reviews', false)">Deselect All</button>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fa-solid fa-trash"></i> Delete Selected Reviews
                        </button>
                        <button type="button" class="btn btn-warning" onclick="deleteAllReviews()">
                            <i class="fa-solid fa-triangle-exclamation"></i> Delete ALL Reviews
                        </button>
                    </div>
                </div>

                <?php if (empty($reviews)): ?>
                    <p style="text-align:center; padding:40px; color:#64748B;">No reviews found in database.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px;"><input type="checkbox" onclick="toggleSelectAll('form-reviews', this.checked)"></th>
                                <th>ID</th>
                                <th>Reviewer</th>
                                <th>Vendor</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $r): ?>
                                <tr>
                                    <td><input type="checkbox" name="review_ids[]" value="<?= $r['id'] ?>"></td>
                                    <td>#<?= $r['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($r['user_name'] ?: 'User #' . $r['user_id']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['vendor_name'] ?: 'Vendor #' . $r['vendor_id']) ?></td>
                                    <td><i class="fa-solid fa-star" style="color:var(--accent);"></i> <?= number_format($r['rating'], 1) ?></td>
                                    <td style="max-width:300px;"><?= htmlspecialchars($r['comment']) ?></td>
                                    <td><?= htmlspecialchars($r['created_at']) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-danger" style="padding:4px 10px; font-size:0.75rem;" onclick="deleteSingleReview(<?= $r['id'] ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </form>

            <form method="POST" id="form-delete-all-reviews" style="display:none;">
                <input type="hidden" name="action" value="delete_all_reviews">
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            if (tabName === 'vendors') {
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
                document.getElementById('tab-vendors').classList.add('active');
            } else {
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
                document.getElementById('tab-reviews').classList.add('active');
            }
        }

        function toggleSelectAll(formId, checked) {
            const form = document.getElementById(formId);
            if (!form) return;
            form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = checked);
        }

        function deleteSingleVendor(id) {
            if (confirm('Delete vendor #' + id + '?')) {
                const form = document.getElementById('form-vendors');
                form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
                const target = form.querySelector('input[value="' + id + '"]');
                if (target) target.checked = true;
                form.submit();
            }
        }

        function deleteSingleReview(id) {
            if (confirm('Delete review #' + id + '?')) {
                const form = document.getElementById('form-reviews');
                form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
                const target = form.querySelector('input[value="' + id + '"]');
                if (target) target.checked = true;
                form.submit();
            }
        }

        function deleteAllVendors() {
            if (confirm('⚠️ WARNING: Are you completely sure you want to delete ALL vendor records from the database? This cannot be undone!')) {
                document.getElementById('form-delete-all-vendors').submit();
            }
        }

        function deleteAllReviews() {
            if (confirm('⚠️ WARNING: Are you completely sure you want to delete ALL review records from the database? This cannot be undone!')) {
                document.getElementById('form-delete-all-reviews').submit();
            }
        }
    </script>
</body>
</html>
