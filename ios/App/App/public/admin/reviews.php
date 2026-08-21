<?php
// admin/reviews.php - Ohati Admin Reviews Management
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE val_value = ?");
        $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {}
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // Check for platform reviews actions first
    if ($action === 'approve_platform' || $action === 'reject_platform' || $action === 'delete_live_platform') {
        $rid = $input['review_id'] ?? '';
        if (empty($rid)) {
            echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
            exit;
        }
        
        if ($action === 'approve_platform') {
            $pending_json = getSetting('pending_platform_reviews', '[]');
            $pending = json_decode($pending_json, true) ?: [];
            $review_to_approve = null;
            $new_pending = [];
            foreach ($pending as $r) {
                if ($r['id'] == $rid) {
                    $review_to_approve = $r;
                } else {
                    $new_pending[] = $r;
                }
            }
            if ($review_to_approve) {
                $live_json = getSetting('platform_reviews', '[]');
                $live = json_decode($live_json, true) ?: [];
                $live[] = $review_to_approve;
                setSetting('pending_platform_reviews', json_encode($new_pending));
                setSetting('platform_reviews', json_encode($live));
                echo json_encode(['success' => true]);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Review not found in pending list.']);
            exit;
        }
        
        if ($action === 'reject_platform') {
            $pending_json = getSetting('pending_platform_reviews', '[]');
            $pending = json_decode($pending_json, true) ?: [];
            $new_pending = [];
            foreach ($pending as $r) {
                if ($r['id'] != $rid) {
                    $new_pending[] = $r;
                }
            }
            setSetting('pending_platform_reviews', json_encode($new_pending));
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($action === 'delete_live_platform') {
            $live_json = getSetting('platform_reviews', '[]');
            $live = json_decode($live_json, true) ?: [];
            $new_live = [];
            foreach ($live as $r) {
                if ($r['id'] != $rid) {
                    $new_live[] = $r;
                }
            }
            setSetting('platform_reviews', json_encode($new_live));
            echo json_encode(['success' => true]);
            exit;
        }
    }

    $rid = intval($input['review_id'] ?? 0);
    if ($rid > 0) {
        if ($action === 'delete') {
            // Soft delete review: archive first
            $sel = $pdo->prepare("SELECT * FROM reviews WHERE id = ?");
            $sel->execute([$rid]);
            $review = $sel->fetch(PDO::FETCH_ASSOC);
            if ($review) {
                $record_data = json_encode($review);
                $stmt = $pdo->prepare("INSERT INTO deleted_records (record_type, record_id, record_data) VALUES ('review', ?, ?)");
                $stmt->execute([$rid, $record_data]);
                
                $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$rid]);
                echo json_encode(['success' => true]);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Review not found']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Fetch search and filter params
$search = trim($_GET['search'] ?? '');
$rating = trim($_GET['rating'] ?? '');

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql_base = "FROM reviews r 
        LEFT JOIN vendors v ON r.vendor_id = v.id 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql_base .= " AND (r.review_text LIKE ? OR v.name LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($rating !== '') {
    $sql_base .= " AND r.rating = ?";
    $params[] = intval($rating);
}

// Count total
$count_query = "SELECT COUNT(*) " . $sql_base;
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));

$sql = "SELECT r.*, v.name as vendor_name, u.name as user_name " . $sql_base . " ORDER BY r.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Stats
$total_reviews = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn() ?: 0;
$avg_rating = $pdo->query("SELECT AVG(rating) FROM reviews")->fetchColumn() ?: 0;
$five_star_count = $pdo->query("SELECT COUNT(*) FROM reviews WHERE rating = 5")->fetchColumn() ?: 0;
$one_star_count = $pdo->query("SELECT COUNT(*) FROM reviews WHERE rating <= 2")->fetchColumn() ?: 0;

$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Reviews Management</title>
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
            .admin-topbar h1, .admin-page-title {
                font-size: 1rem !important;
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
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
                <h1 class="admin-page-title">Reviews Management</h1>
            </div>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-circle-user" style="font-size:1.2rem; color:var(--accent);"></i>
                <span>System Administrator</span>
            </div>
        </header>

        <!-- Admin Content -->
        <div class="admin-content">
            <?php
            $current_tab = $_GET['tab'] ?? 'vendor';
            $pending_platform_json = getSetting('pending_platform_reviews', '[]');
            $pending_platform = json_decode($pending_platform_json, true) ?: [];
            $live_platform_json = getSetting('platform_reviews', '[]');
            $live_platform = json_decode($live_platform_json, true) ?: [];
            ?>

            <!-- Tab Navigation -->
            <div style="display:flex; border-bottom:2px solid #E4E7ED; margin-bottom:24px; gap:8px;">
                <a href="reviews.php?tab=vendor" style="padding:12px 20px; font-weight:700; font-size:0.9rem; text-decoration:none; color:<?= $current_tab === 'vendor' ? 'var(--primary)' : 'var(--gray-500)' ?>; border-bottom: 3px solid <?= $current_tab === 'vendor' ? 'var(--primary)' : 'transparent' ?>; margin-bottom:-2px;">
                    <i class="fa-solid fa-store" style="margin-right:6px;"></i> Vendor Reviews
                </a>
                <a href="reviews.php?tab=platform" style="padding:12px 20px; font-weight:700; font-size:0.9rem; text-decoration:none; color:<?= $current_tab === 'platform' ? 'var(--primary)' : 'var(--gray-500)' ?>; border-bottom: 3px solid <?= $current_tab === 'platform' ? 'var(--primary)' : 'transparent' ?>; margin-bottom:-2px;">
                    <i class="fa-solid fa-house" style="margin-right:6px;"></i> Platform Testimonials (Homepage)
                    <?php if (count($pending_platform) > 0): ?>
                        <span style="background:var(--rose); color:#fff; font-size:0.65rem; padding:2px 6px; border-radius:10px; font-weight:700; margin-left:4px;"><?= count($pending_platform) ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <?php if ($current_tab === 'vendor'): ?>
                <!-- Stats Blocks -->
                <div class="admin-stat-grid" style="display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:30px;">
                    <div class="card" style="padding:20px;">
                        <div style="font-size:0.8rem; color:var(--gray-500); text-transform:uppercase; font-weight:700;">Total Reviews</div>
                        <div style="font-size:1.8rem; font-weight:800; color:var(--primary); margin:8px 0;"><?= number_format($total_reviews) ?></div>
                        <div style="font-size:0.75rem; color:var(--gray-500);">All vendor reviews</div>
                    </div>
                    <div class="card" style="padding:20px;">
                        <div style="font-size:0.8rem; color:var(--gray-500); text-transform:uppercase; font-weight:700;">Average Rating</div>
                        <div style="font-size:1.8rem; font-weight:800; color:var(--accent); margin:8px 0;"><?= number_format($avg_rating, 1) ?> <i class="fa-solid fa-star" style="font-size:1rem; color:var(--warning);"></i></div>
                        <div style="font-size:0.75rem; color:var(--gray-500);">Across all vendors</div>
                    </div>
                    <div class="card" style="padding:20px;">
                        <div style="font-size:0.8rem; color:var(--gray-500); text-transform:uppercase; font-weight:700;">5-Star Reviews</div>
                        <div style="font-size:1.8rem; font-weight:800; color:var(--teal); margin:8px 0;"><?= number_format($five_star_count) ?></div>
                        <div style="font-size:0.75rem; color:var(--gray-500);">Top rated experiences</div>
                    </div>
                    <div class="card" style="padding:20px;">
                        <div style="font-size:0.8rem; color:var(--gray-500); text-transform:uppercase; font-weight:700;">Low Ratings</div>
                        <div style="font-size:1.8rem; font-weight:800; color:var(--rose); margin:8px 0;"><?= number_format($one_star_count) ?></div>
                        <div style="font-size:0.75rem; color:var(--gray-500);">1-2 star reviews</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card" style="padding:20px; margin-bottom:30px;">
                    <form method="GET" action="reviews.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                        <input type="hidden" name="tab" value="vendor">
                        <input type="text" name="search" class="form-input" placeholder="Search review text, vendor, or user..." value="<?= htmlspecialchars($search) ?>" style="margin:0; padding:10px 14px; flex:1; min-width:200px;">
                        
                        <select name="rating" class="form-input" style="margin:0; padding:10px 14px; width:auto; min-width:150px;">
                            <option value="">All Ratings</option>
                            <option value="5" <?= ($rating === '5') ? 'selected' : '' ?>>5 Stars</option>
                            <option value="4" <?= ($rating === '4') ? 'selected' : '' ?>>4 Stars</option>
                            <option value="3" <?= ($rating === '3') ? 'selected' : '' ?>>3 Stars</option>
                            <option value="2" <?= ($rating === '2') ? 'selected' : '' ?>>2 Stars</option>
                            <option value="1" <?= ($rating === '1') ? 'selected' : '' ?>>1 Star</option>
                        </select>

                        <button type="submit" class="btn" style="padding:10px 20px;">Filter</button>
                        <?php if ($search !== '' || $rating !== ''): ?>
                            <a href="reviews.php?tab=vendor" class="btn btn-outline" style="padding:10px 20px; text-decoration:none; text-align:center;">Clear</a>
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
                                    <th>Vendor</th>
                                    <th>Reviewer</th>
                                    <th>Rating</th>
                                    <th>Review Text</th>
                                    <th>Date</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reviews)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center; padding:40px; color:var(--gray-400);">No reviews found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reviews as $r): ?>
                                        <tr id="row-<?= $r['id'] ?>">
                                            <td style="font-weight:700;">#<?= $r['id'] ?></td>
                                            <td>
                                                <div style="font-weight:600; color:var(--primary);"><?= htmlspecialchars($r['vendor_name'] ?? 'Unknown') ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight:600; color:var(--gray-700);"><?= htmlspecialchars($r['user_name'] ?? 'Anonymous') ?></div>
                                            </td>
                                            <td>
                                                <div style="color:var(--warning); font-size:0.8rem;">
                                                    <?php for ($i = 0; $i < ($r['rating'] ?? 0); $i++): ?><i class="fa-solid fa-star"></i><?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size:0.75rem; color:var(--gray-600); max-width:250px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;" title="<?= htmlspecialchars($r['review_text'] ?? '') ?>">
                                                    <?= htmlspecialchars($r['review_text'] ?? '') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size:0.75rem; color:var(--gray-500);"><?= substr($r['created_at'] ?? '', 0, 10) ?></div>
                                            </td>
                                            <td style="text-align:right;">
                                                <div style="display:flex; justify-content:flex-end; gap:6px;">
                                                    <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; font-weight:700;" onclick='viewReviewDetails(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Full Review Details"><i class="fa-solid fa-eye"></i> Details</button>
                                                    <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="deleteReview(<?= $r['id'] ?>)" title="Archive & Delete"><i class="fa-solid fa-trash"></i></button>
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
                                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_items) ?> of <?= $total_items ?> reviews
                            </div>
                            <div class="pagination-buttons">
                                <!-- Prev button -->
                                <a href="?tab=vendor&page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&rating=<?= urlencode($rating) ?>" class="pagination-btn <?= $page == 1 ? 'disabled' : '' ?>">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                                
                                <!-- Page numbers -->
                                <?php 
                                $start_range = max(1, $page - 2);
                                $end_range = min($total_pages, $page + 2);
                                if ($start_range > 1) {
                                    echo '<a href="?tab=vendor&page=1&search='.urlencode($search).'&rating='.urlencode($rating).'" class="pagination-btn">1</a>';
                                    if ($start_range > 2) echo '<span style="padding:6px;">...</span>';
                                }
                                for ($i = $start_range; $i <= $end_range; $i++) {
                                    $active_cls = $i == $page ? 'active' : '';
                                    echo '<a href="?tab=vendor&page='.$i.'&search='.urlencode($search).'&rating='.urlencode($rating).'" class="pagination-btn '.$active_cls.'">'.$i.'</a>';
                                }
                                if ($end_range < $total_pages) {
                                    if ($end_range < $total_pages - 1) echo '<span style="padding:6px;">...</span>';
                                    echo '<a href="?tab=vendor&page='.$total_pages.'&search='.urlencode($search).'&rating='.urlencode($rating).'" class="pagination-btn">'.$total_pages.'</a>';
                                }
                                ?>

                                <!-- Next button -->
                                <a href="?tab=vendor&page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>&rating=<?= urlencode($rating) ?>" class="pagination-btn <?= $page == $total_pages ? 'disabled' : '' ?>">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Pending Platform Reviews Queue -->
                <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin:0 0 16px 0;"><i class="fa-solid fa-clock" style="color:var(--warning);"></i> Pending Approvals</h3>
                <div class="card mb-30" style="padding:0; overflow:hidden;">
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Reviewer</th>
                                    <th>Rating</th>
                                    <th>Review Comment</th>
                                    <th>Date</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_platform)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:40px; color:var(--gray-400);">No pending platform reviews.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pending_platform as $pr): ?>
                                        <tr id="platform-pending-<?= $pr['id'] ?>">
                                            <td>
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <img src="<?= htmlspecialchars($pr['avatar']) ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                                                    <span style="font-weight:600;"><?= htmlspecialchars($pr['name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="color:var(--warning); font-size:0.8rem;">
                                                    <?php for ($i = 0; $i < ($pr['rating'] ?? 5); $i++): ?><i class="fa-solid fa-star"></i><?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size:0.8rem; color:var(--gray-700); max-width:400px;"><?= htmlspecialchars($pr['comment']) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($pr['date'] ?? '') ?></td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <button class="btn btn-sm" style="padding:6px 12px; font-size:0.75rem; background:var(--teal); color:#fff; border:none; margin-right:6px;" onclick="approvePlatformReview('<?= $pr['id'] ?>')"><i class="fa-solid fa-check"></i> Approve</button>
                                                <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="rejectPlatformReview('<?= $pr['id'] ?>')"><i class="fa-solid fa-xmark"></i> Reject</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Live Testimonials List -->
                <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin:0 0 16px 0;"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Live Homepage Testimonials</h3>
                <div class="card" style="padding:0; overflow:hidden;">
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Reviewer</th>
                                    <th>Rating</th>
                                    <th>Review Comment</th>
                                    <th>Date</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($live_platform)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:40px; color:var(--gray-400);">No live testimonials on homepage.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($live_platform as $lpr): ?>
                                        <tr id="platform-live-<?= $lpr['id'] ?>">
                                            <td>
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <img src="<?= htmlspecialchars($lpr['avatar']) ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                                                    <span style="font-weight:600;"><?= htmlspecialchars($lpr['name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="color:var(--warning); font-size:0.8rem;">
                                                    <?php for ($i = 0; $i < ($lpr['rating'] ?? 5); $i++): ?><i class="fa-solid fa-star"></i><?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size:0.8rem; color:var(--gray-700); max-width:400px;"><?= $lpr['comment'] ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($lpr['date'] ?? 'Live') ?></td>
                                            <td style="text-align:right;">
                                                <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="deleteLivePlatformReview('<?= $lpr['id'] ?>')" title="Remove from Homepage"><i class="fa-solid fa-trash"></i> Remove</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
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

        function deleteReview(reviewId) {
            if (!confirm('Are you sure you want to archive and delete this review?')) return;
            fetch('reviews.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ review_id: reviewId, action: 'delete' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('row-' + reviewId);
                    if (row) {
                        row.style.opacity = '0';
                        row.style.transition = 'opacity 0.3s';
                        setTimeout(() => row.remove(), 300);
                    }
                } else {
                    alert(data.message || 'Failed to delete review.');
                }
            });
        }

        function approvePlatformReview(reviewId) {
            if (!confirm('Approve this review for publication on the homepage?')) return;
            fetch('reviews.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ review_id: reviewId, action: 'approve_platform' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Review approved successfully!');
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to approve review.');
                }
            });
        }

        function rejectPlatformReview(reviewId) {
            if (!confirm('Reject and permanently delete this pending review?')) return;
            fetch('reviews.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ review_id: reviewId, action: 'reject_platform' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('platform-pending-' + reviewId);
                    if (row) {
                        row.style.opacity = '0';
                        row.style.transition = 'opacity 0.3s';
                        setTimeout(() => row.remove(), 300);
                    }
                } else {
                    alert(data.message || 'Failed to reject review.');
                }
            });
        }

        function deleteLivePlatformReview(reviewId) {
            if (!confirm('Remove this testimonial from the homepage?')) return;
            fetch('reviews.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ review_id: reviewId, action: 'delete_live_platform' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('platform-live-' + reviewId);
                    if (row) {
                        row.style.opacity = '0';
                        row.style.transition = 'opacity 0.3s';
                        setTimeout(() => row.remove(), 300);
                    }
                } else {
                    alert(data.message || 'Failed to remove review.');
                }
            });
        }
    </script>
</body>
</html>
