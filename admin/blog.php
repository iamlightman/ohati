<?php
// admin/blog.php - Ohati Admin Blog Management Console
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

$message = '';
$message_type = 'success';

// Fetch stats
$total_posts = (int)$pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
$published_count = (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn();
$draft_count = (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'")->fetchColumn();
$scheduled_count = (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'scheduled'")->fetchColumn();
$total_views = (int)$pdo->query("SELECT SUM(views_count) FROM blog_posts")->fetchColumn();
$total_likes = (int)$pdo->query("SELECT SUM(likes_count) FROM blog_posts")->fetchColumn();
$total_comments = (int)$pdo->query("SELECT COUNT(*) FROM blog_comments")->fetchColumn();

// Fetch posts list
// Pagination & Filter parameters
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_category = trim($_GET['category'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(title LIKE ? OR subheadline LIKE ? OR tags LIKE ?)";
    $s_param = "%$search%";
    $params[] = $s_param;
    $params[] = $s_param;
    $params[] = $s_param;
}

if (!empty($filter_status) && $filter_status !== 'all') {
    $where[] = "status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_category) && $filter_category !== 'all') {
    $where[] = "category = ?";
    $params[] = $filter_category;
}

$where_sql = implode(' AND ', $where);

// Count Total Filtered Records for Pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE $where_sql");
$count_stmt->execute($params);
$total_filtered = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_filtered / $limit));

// Fetch Paginated Posts
$stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for dropdown
$categories_list = ['Planning & Timeline', 'Venues & Locations', 'Decoration & Styling', 'Food & Drinks', 'Photography & Media', 'General'];

// Fetch latest comments for moderation
$comments_stmt = $pdo->query("SELECT c.*, p.title as post_title FROM blog_comments c JOIN blog_posts p ON c.post_id = p.id ORDER BY c.id DESC LIMIT 30");
$latest_comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management — Ohati Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@600;700;800&display=swap">
    <style>
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; }
        body { background: #F3F4F6; color: #1F2937; }
        .admin-main { padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 1.5rem; font-weight: 800; color: #111827; display: flex; align-items: center; gap: 10px; }
        .page-subtitle { font-size: 0.85rem; color: #6B7280; margin-top: 4px; }
        .btn-primary { background: #E05A47; color: #fff; border: none; padding: 10px 18px; border-radius: 10px; font-size: 0.85rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
        .btn-primary:hover { background: #c84937; }
        .btn-secondary { background: #E5E7EB; color: #374151; border: none; padding: 8px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-secondary:hover { background: #D1D5DB; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 14px; padding: 18px; border: 1px solid #E5E7EB; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-label { font-size: 0.75rem; color: #6B7280; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-num { font-size: 1.6rem; font-weight: 800; color: #111827; margin-top: 6px; }
        
        /* Filters & Search */
        .filter-bar { background: #fff; padding: 16px; border-radius: 14px; border: 1px solid #E5E7EB; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }
        .filter-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1; }
        .search-input, .select-input { padding: 9px 14px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff; }
        .search-input { width: 260px; }
        
        /* Datatable */
        .card-table { background: #fff; border-radius: 14px; border: 1px solid #E5E7EB; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #F9FAFB; padding: 12px 16px; font-size: 0.75rem; text-transform: uppercase; color: #6B7280; font-weight: 700; border-bottom: 1px solid #E5E7EB; }
        td { padding: 14px 16px; border-bottom: 1px solid #F3F4F6; font-size: 0.85rem; vertical-align: middle; }
        tr:hover { background: #F9FAFB; }
        
        .post-thumb { width: 60px; height: 45px; border-radius: 6px; object-fit: cover; background: #E5E7EB; }
        .post-title { font-weight: 700; color: #111827; line-height: 1.3; }
        .post-sub { font-size: 0.75rem; color: #6B7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px; }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-published { background: #DEF7EC; color: #03543F; }
        .badge-draft { background: #F3F4F6; color: #374151; }
        .badge-scheduled { background: #FEF3C7; color: #92400E; }

        .switch-toggle { position: relative; display: inline-block; width: 36px; height: 20px; }
        .switch-toggle input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #CBD5E1; transition: .3s; border-radius: 20px; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: #10B981; }
        input:checked + .slider:before { transform: translateX(16px); }

        .action-btns { display: flex; gap: 6px; }
        .btn-icon { width: 32px; height: 32px; border-radius: 6px; border: 1px solid #E5E7EB; background: #fff; color: #4B5563; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; text-decoration: none; }
        .btn-icon:hover { background: #F3F4F6; color: #111827; }
        .btn-icon-danger:hover { background: #FEE2E2; color: #EF4444; border-color: #FCA5A5; }

        /* Modals */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 20px; }
        .modal-card { background: #fff; border-radius: 16px; width: 100%; max-width: 860px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); display: flex; flex-direction: column; }
        .modal-header { padding: 18px 24px; border-bottom: 1px solid #E5E7EB; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: #fff; z-index: 10; }
        .modal-title { font-size: 1.1rem; font-weight: 800; color: #111827; }
        .modal-close { background: none; border: none; font-size: 1.2rem; color: #6B7280; cursor: pointer; }
        .modal-body { padding: 24px; display: flex; flex-direction: column; gap: 16px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px; position: sticky; bottom: 0; background: #fff; z-index: 10; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { font-size: 0.8rem; font-weight: 700; color: #374151; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.85rem; outline: none; }
        .form-control:focus { border-color: #E05A47; box-shadow: 0 0 0 3px rgba(224,90,71,0.15); }

        /* Rich Editor Toolbar */
        .editor-toolbar { display: flex; flex-wrap: wrap; gap: 4px; padding: 8px; background: #F8FAFC; border: 1px solid #D1D5DB; border-bottom: none; border-radius: 8px 8px 0 0; }
        .editor-btn { background: #fff; border: 1px solid #CBD5E1; padding: 6px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: 700; cursor: pointer; color: #334155; }
        .editor-btn:hover { background: #E2E8F0; }
        .editor-content { min-height: 220px; border: 1px solid #D1D5DB; border-radius: 0 0 8px 8px; padding: 14px; font-size: 0.9rem; line-height: 1.6; outline: none; background: #fff; overflow-y: auto; }

        /* Tabs */
        .tabs-header { display: flex; border-bottom: 2px solid #E5E7EB; margin-bottom: 20px; gap: 20px; }
        .tab-btn { padding: 10px 4px; font-size: 0.9rem; font-weight: 700; color: #6B7280; border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: #E05A47; border-bottom-color: #E05A47; }
    </style>
</head>
<body>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="fa-solid fa-newspaper" style="color:#E05A47;"></i> Blog Management</h1>
                <p class="page-subtitle">Create, edit, publish articles, manage comments, and track article engagement.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <button class="btn-secondary" onclick="openCommentsDrawer()"><i class="fa-solid fa-comments"></i> Manage Comments (<?= count($latest_comments) ?>)</button>
                <button class="btn-primary" onclick="openCreatePostModal()"><i class="fa-solid fa-plus"></i> Create New Article</button>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Articles</div>
                <div class="stat-num"><?= number_format($total_posts) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Published</div>
                <div class="stat-num" style="color:#10B981;"><?= number_format($published_count) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Drafts</div>
                <div class="stat-num" style="color:#6B7280;"><?= number_format($draft_count) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Views</div>
                <div class="stat-num" style="color:#3B82F6;"><?= number_format($total_views) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Likes</div>
                <div class="stat-num" style="color:#EC4899;"><?= number_format($total_likes) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Comments</div>
                <div class="stat-num" style="color:#8B5CF6;"><?= number_format($total_comments) ?></div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <input type="text" name="search" class="search-input" placeholder="Search headline, tags..." value="<?= htmlspecialchars($search) ?>">
                <select name="category" class="select-input" onchange="this.form.submit()">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories_list as $cat): ?>
                        <option value="<?= $cat ?>" <?= $filter_category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="select-input" onchange="this.form.submit()">
                    <option value="all">All Statuses</option>
                    <option value="published" <?= $filter_status === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="scheduled" <?= $filter_status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                </select>
                <button type="submit" class="btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
                <?php if (!empty($search) || (!empty($filter_category) && $filter_category !== 'all') || (!empty($filter_status) && $filter_status !== 'all')): ?>
                    <a href="blog.php" class="btn-secondary" style="text-decoration:none; color:#EF4444; border-color:#FCA5A5;"><i class="fa-solid fa-rotate-left"></i> Reset Filters</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Posts Datatable -->
        <div class="card-table">
            <table>
                <thead>
                    <tr>
                        <th style="width:80px;">Cover</th>
                        <th>Article Headline</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Engagement</th>
                        <th>Published</th>
                        <th style="width:130px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($posts)): ?>
                        <tr><td colspan="8" style="text-align:center; padding:32px; color:#6B7280;">No blog articles found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($p['cover_image'] ?: '../img/app_icon.png') ?>" alt="Cover" class="post-thumb" onerror="this.src='../img/app_icon.png'">
                                </td>
                                <td>
                                    <div class="post-title"><?= htmlspecialchars($p['title']) ?></div>
                                    <div class="post-sub"><?= htmlspecialchars($p['subheadline']) ?></div>
                                </td>
                                <td><span style="font-weight:700; font-size:0.8rem; color:#4B5563;"><?= htmlspecialchars($p['category']) ?></span></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <img src="<?= htmlspecialchars($p['author_avatar'] ?: '../img/new_icon_ohati.png') ?>" style="width:22px; height:22px; border-radius:50%; object-fit:cover;" onerror="this.src='../img/new_icon_ohati.png'">
                                        <span style="font-size:0.8rem;"><?= htmlspecialchars($p['author_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <label class="switch-toggle" title="Toggle Publish Status">
                                        <input type="checkbox" <?= $p['status'] === 'published' ? 'checked' : '' ?> onchange="togglePostStatus(<?= $p['id'] ?>)">
                                        <span class="slider"></span>
                                    </label>
                                    <span class="badge badge-<?= $p['status'] ?>" style="margin-left:6px;"><?= ucfirst($p['status']) ?></span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:10px; font-size:0.75rem; color:#6B7280; font-weight:700;">
                                        <span title="Views"><i class="fa-solid fa-eye"></i> <?= number_format($p['views_count']) ?></span>
                                        <span title="Likes"><i class="fa-solid fa-heart" style="color:#EC4899;"></i> <?= number_format($p['likes_count']) ?></span>
                                        <span title="Comments"><i class="fa-solid fa-comment" style="color:#8B5CF6;"></i> <?= number_format($p['comments_count']) ?></span>
                                    </div>
                                </td>
                                <td style="font-size:0.75rem; color:#6B7280;"><?= !empty($p['published_at']) ? date('M d, Y', strtotime($p['published_at'])) : 'Draft' ?></td>
                                <td style="text-align:right;">
                                    <div class="action-btns" style="justify-content:flex-end;">
                                        <button class="btn-icon" onclick="previewPost(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" title="Preview Article"><i class="fa-solid fa-eye"></i></button>
                                        <button class="btn-icon" onclick="editPost(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" title="Edit Article"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <button class="btn-icon btn-icon-danger" onclick="deletePost(<?= $p['id'] ?>)" title="Delete Article"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Table Pagination Footer (10 articles per page) -->
            <?php if ($total_filtered > 0): ?>
                <div style="padding:16px 20px; display:flex; justify-content:space-between; align-items:center; background:#fff; border-top:1px solid #E5E7EB; flex-wrap:wrap; gap:12px;">
                    <div style="font-size:0.8rem; color:#6B7280;">
                        Showing <strong><?= $offset + 1 ?></strong> to <strong><?= min($offset + $limit, $total_filtered) ?></strong> of <strong><?= $total_filtered ?></strong> articles
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-secondary" style="padding:6px 12px; font-size:0.8rem; text-decoration:none;"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="btn-secondary" style="padding:6px 12px; font-size:0.8rem; text-decoration:none; <?= $p === $page ? 'background:#E05A47; color:#fff; border-color:#E05A47;' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-secondary" style="padding:6px 12px; font-size:0.8rem; text-decoration:none;">Next <i class="fa-solid fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- ===== ARTICLE CREATOR / EDITOR MODAL ===== -->
<div class="modal-overlay" id="postModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" id="postModalTitle">Create New Article</div>
            <button class="modal-close" onclick="closePostModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="postForm" onsubmit="savePost(event)">
            <input type="hidden" id="edit-post-id" value="0">
            <div class="modal-body">
                <div class="form-group full">
                    <label class="form-label">Article Headline / Title *</label>
                    <input type="text" id="post-title" class="form-control" required placeholder="e.g. The Ultimate Wedding Planning Timeline for Ghanaian Couples">
                </div>
                <div class="form-group full">
                    <label class="form-label">Subheadline / Short Excerpt</label>
                    <input type="text" id="post-subheadline" class="form-control" placeholder="e.g. Avoid last-minute rush with this month-by-month roadmap.">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select id="post-category" class="form-control" required>
                            <?php foreach ($categories_list as $cat): ?>
                                <option value="<?= $cat ?>"><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tags (comma-separated)</label>
                        <input type="text" id="post-tags" class="form-control" placeholder="Planning, Ghana Wedding, Decor">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Cover / Featured Image</label>
                        <input type="file" id="post-cover-file" class="form-control" accept="image/*" onchange="previewCoverUpload(this)">
                        <input type="text" id="post-cover-url" class="form-control" placeholder="Or enter Image URL (img/chill/event1.jpg)" style="margin-top:6px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Video / Content URL (Optional)</label>
                        <input type="text" id="post-video-url" class="form-control" placeholder="e.g. img/chill/v1_opt.mp4 or YouTube link">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Author Name</label>
                        <input type="text" id="post-author-name" class="form-control" value="Chill & Serve Editorial">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Author Avatar URL</label>
                        <input type="text" id="post-author-avatar" class="form-control" value="img/chill/logo.jpg">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Publish Status</label>
                        <select id="post-status" class="form-control">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="scheduled">Scheduled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Featured Post?</label>
                        <select id="post-featured" class="form-control">
                            <option value="0">No (Standard Article)</option>
                            <option value="1">Yes (Show as Hero Banner)</option>
                        </select>
                    </div>
                </div>

                <!-- Rich Article Content Editor -->
                <div class="form-group full">
                    <label class="form-label">Article Body Content *</label>
                    <div class="editor-toolbar">
                        <button type="button" class="editor-btn" onclick="execCmd('formatBlock', '<h2>')">H2 Heading</button>
                        <button type="button" class="editor-btn" onclick="execCmd('formatBlock', '<h3>')">H3 Heading</button>
                        <button type="button" class="editor-btn" onclick="execCmd('bold')"><b>B</b></button>
                        <button type="button" class="editor-btn" onclick="execCmd('italic')"><i>I</i></button>
                        <button type="button" class="editor-btn" onclick="execCmd('insertUnorderedList')"><i class="fa-solid fa-list-ul"></i> Bullet</button>
                        <button type="button" class="editor-btn" onclick="execCmd('insertOrderedList')"><i class="fa-solid fa-list-ol"></i> Numbered</button>
                        <button type="button" class="editor-btn" onclick="execCmd('formatBlock', 'blockquote')"><i class="fa-solid fa-quote-left"></i> Quote</button>
                        <button type="button" class="editor-btn" onclick="insertLink()"><i class="fa-solid fa-link"></i> Link</button>
                        <button type="button" class="editor-btn" onclick="triggerInlineImageUpload()"><i class="fa-solid fa-image"></i> Add Image</button>
                    </div>
                    <div id="editor-body" class="editor-content" contenteditable="true" placeholder="Write properly formatted article content here..."></div>
                    <input type="file" id="inline-img-input" style="display:none;" accept="image/*" onchange="uploadInlineImage(this)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closePostModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save & Publish Article</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== PREVIEW MODAL ===== -->
<div class="modal-overlay" id="previewModal">
    <div class="modal-card" style="max-width:760px;">
        <div class="modal-header">
            <div class="modal-title">Live Article Preview</div>
            <button class="modal-close" onclick="document.getElementById('previewModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="previewContainer" style="padding:32px;"></div>
    </div>
</div>

<!-- ===== COMMENTS MODERATION DRAWER MODAL ===== -->
<div class="modal-overlay" id="commentsModal">
    <div class="modal-card" style="max-width:720px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-comments"></i> Manage Comments</div>
            <button class="modal-close" onclick="document.getElementById('commentsModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php if (empty($latest_comments)): ?>
                    <p style="text-align:center; color:#6B7280; padding:20px;">No comments yet.</p>
                <?php else: ?>
                    <?php foreach ($latest_comments as $c): ?>
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:14px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                            <img src="<?= htmlspecialchars($c['author_avatar'] ?: '../img/new_icon_ohati.png') ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover;" onerror="this.src='../img/new_icon_ohati.png'">
                            <div style="flex:1;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-weight:700; font-size:0.85rem; color:#1E293B;"><?= htmlspecialchars($c['author_name']) ?></span>
                                    <span style="font-size:0.7rem; color:#94A3B8;"><?= date('M d, H:i', strtotime($c['created_at'])) ?></span>
                                </div>
                                <div style="font-size:0.75rem; color:#64748B; margin-top:2px;">Article: <strong><?= htmlspecialchars($c['post_title']) ?></strong></div>
                                <div style="font-size:0.85rem; color:#334155; margin-top:6px; background:#fff; padding:8px 12px; border-radius:8px; border:1px solid #CBD5E1;"><?= nl2br(htmlspecialchars($c['comment'])) ?></div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:6px;">
                                <?php if ($c['status'] !== 'approved'): ?>
                                    <button class="btn-icon" style="color:#10B981;" onclick="manageComment(<?= $c['id'] ?>, 'approve')" title="Approve"><i class="fa-solid fa-check"></i></button>
                                <?php endif; ?>
                                <button class="btn-icon btn-icon-danger" onclick="manageComment(<?= $c['id'] ?>, 'delete')" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function execCmd(command, value = null) {
    document.execCommand(command, false, value);
}

function insertLink() {
    const url = prompt('Enter link URL:');
    if (url) {
        document.execCommand('createLink', false, url);
    }
}

function triggerInlineImageUpload() {
    document.getElementById('inline-img-input').click();
}

function uploadInlineImage(input) {
    if (!input.files || !input.files[0]) return;
    const formData = new FormData();
    formData.append('action', 'admin_upload_blog_image');
    formData.append('image', input.files[0]);

    fetch('../api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.url) {
                document.execCommand('insertHTML', false, `<div class="article-image-box"><img src="${res.url}" alt="Article Image"><p class="caption">Uploaded image</p></div>`);
            } else {
                alert(res.error || 'Failed to upload image.');
            }
        });
}

function openCreatePostModal() {
    document.getElementById('edit-post-id').value = '0';
    document.getElementById('postModalTitle').innerText = 'Create New Article';
    document.getElementById('post-title').value = '';
    document.getElementById('post-subheadline').value = '';
    document.getElementById('post-category').selectedIndex = 0;
    document.getElementById('post-tags').value = '';
    document.getElementById('post-cover-url').value = '';
    document.getElementById('post-video-url').value = '';
    document.getElementById('post-author-name').value = 'Chill & Serve Editorial';
    document.getElementById('post-author-avatar').value = 'img/chill/logo.jpg';
    document.getElementById('post-status').value = 'published';
    document.getElementById('post-featured').value = '0';
    document.getElementById('editor-body').innerHTML = '';
    document.getElementById('postModal').style.display = 'flex';
}

function closePostModal() {
    document.getElementById('postModal').style.display = 'none';
}

function editPost(post) {
    document.getElementById('edit-post-id').value = post.id;
    document.getElementById('postModalTitle').innerText = 'Edit Article #' + post.id;
    document.getElementById('post-title').value = post.title || '';
    document.getElementById('post-subheadline').value = post.subheadline || '';
    document.getElementById('post-category').value = post.category || 'General';
    document.getElementById('post-tags').value = post.tags || '';
    document.getElementById('post-cover-url').value = post.cover_image || '';
    document.getElementById('post-video-url').value = post.video_url || '';
    document.getElementById('post-author-name').value = post.author_name || 'Ohati Editorial';
    document.getElementById('post-author-avatar').value = post.author_avatar || 'img/new_icon_ohati.png';
    document.getElementById('post-status').value = post.status || 'published';
    document.getElementById('post-featured').value = post.featured || '0';
    document.getElementById('editor-body').innerHTML = post.content || '';
    document.getElementById('postModal').style.display = 'flex';
}

function savePost(e) {
    e.preventDefault();
    const formData = new FormData();
    const id = document.getElementById('edit-post-id').value;
    formData.append('action', 'admin_save_blog_post');
    formData.append('id', id);
    formData.append('title', document.getElementById('post-title').value);
    formData.append('subheadline', document.getElementById('post-subheadline').value);
    formData.append('category', document.getElementById('post-category').value);
    formData.append('tags', document.getElementById('post-tags').value);
    formData.append('cover_image', document.getElementById('post-cover-url').value);
    formData.append('video_url', document.getElementById('post-video-url').value);
    formData.append('author_name', document.getElementById('post-author-name').value);
    formData.append('author_avatar', document.getElementById('post-author-avatar').value);
    formData.append('status', document.getElementById('post-status').value);
    formData.append('featured', document.getElementById('post-featured').value);
    formData.append('content', document.getElementById('editor-body').innerHTML);

    const fileInput = document.getElementById('post-cover-file');
    if (fileInput.files && fileInput.files[0]) {
        formData.append('cover_image_file', fileInput.files[0]);
    }

    fetch('../api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert(res.message);
                window.location.reload();
            } else {
                alert(res.error || 'Failed to save article.');
            }
        });
}

function togglePostStatus(id) {
    const formData = new FormData();
    formData.append('action', 'admin_toggle_blog_status');
    formData.append('id', id);

    fetch('../api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert(res.error || 'Failed to toggle status.');
                window.location.reload();
            }
        });
}

function deletePost(id) {
    if (!confirm('Are you sure you want to permanently delete this article and its comments?')) return;
    const formData = new FormData();
    formData.append('action', 'admin_delete_blog_post');
    formData.append('id', id);

    fetch('../api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.error || 'Failed to delete post.');
            }
        });
}

function previewPost(post) {
    const container = document.getElementById('previewContainer');
    container.innerHTML = `
        <div style="font-size:0.75rem; font-weight:800; color:#E05A47; text-transform:uppercase; letter-spacing:1px;">${post.category} • ${post.reading_time || 4} min read</div>
        <h1 style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:800; margin:8px 0; color:#111827;">${post.title}</h1>
        ${post.subheadline ? `<p style="font-size:1.05rem; color:#4B5563; font-style:italic; margin-bottom:16px;">${post.subheadline}</p>` : ''}
        <img src="${post.cover_image || '../img/app_icon.png'}" style="width:100%; max-height:360px; object-fit:cover; border-radius:12px; margin-bottom:20px;">
        <div style="font-size:0.95rem; line-height:1.7; color:#1F2937;">${post.content}</div>
    `;
    document.getElementById('previewModal').style.display = 'flex';
}

function openCommentsDrawer() {
    document.getElementById('commentsModal').style.display = 'flex';
}

function manageComment(id, type) {
    const formData = new FormData();
    formData.append('action', 'admin_manage_comment');
    formData.append('comment_id', id);
    formData.append('type', type);

    fetch('../api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.error || 'Action failed.');
            }
        });
}
</script>
</body>
</html>
