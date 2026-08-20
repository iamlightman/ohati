<?php
// blog_api.php — Ohati Blog API Module
date_default_timezone_set('Africa/Accra');

if (!function_exists('generate_blog_slug')) {
    function generate_blog_slug($title, $pdo, $current_id = 0) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        if (empty($slug)) $slug = 'post-' . time();
        $original_slug = $slug;
        $counter = 1;

        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $current_id]);
            if (!$stmt->fetchColumn()) break;
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        return $slug;
    }
}

function handle_blog_action($action, $pdo) {
    $raw_json = json_decode(file_get_contents('php://input'), true);
    $raw_input = is_array($raw_json) ? $raw_json : [];
    
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') ||
                 (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');

    switch ($action) {
        // ── PUBLIC: LIST POSTS ─────────────────────────────────────────────
        case 'get_blog_posts':
            $category = trim($_GET['category'] ?? $_POST['category'] ?? $raw_input['category'] ?? '');
            $search   = trim($_GET['search'] ?? $_POST['search'] ?? $raw_input['search'] ?? '');
            $featured = isset($_GET['featured']) ? intval($_GET['featured']) : (isset($raw_input['featured']) ? intval($raw_input['featured']) : null);
            $limit    = max(1, min(50, intval($_GET['limit'] ?? $_POST['limit'] ?? $raw_input['limit'] ?? 20)));
            $page     = max(1, intval($_GET['page'] ?? $_POST['page'] ?? $raw_input['page'] ?? 1));
            $offset   = ($page - 1) * $limit;

            $where = ["status = 'published'"];
            $params = [];

            if (!empty($category) && strtolower($category) !== 'all') {
                $where[] = "category = ?";
                $params[] = $category;
            }

            if (!empty($search)) {
                $where[] = "(title LIKE ? OR subheadline LIKE ? OR content LIKE ? OR tags LIKE ?)";
                $s_param = "%$search%";
                $params[] = $s_param;
                $params[] = $s_param;
                $params[] = $s_param;
                $params[] = $s_param;
            }

            if ($featured !== null) {
                $where[] = "featured = ?";
                $params[] = $featured;
            }

            $where_sql = implode(' AND ', $where);

            // Fetch Total Count
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE $where_sql");
            $count_stmt->execute($params);
            $total_count = (int)$count_stmt->fetchColumn();

            // Fetch Posts
            $query = "SELECT id, title, slug, subheadline, category, tags, cover_image, video_url, author_name, author_avatar, status, published_at, views_count, likes_count, comments_count, shares_count, reading_time, featured, created_at FROM blog_posts WHERE $where_sql ORDER BY featured DESC, id DESC LIMIT $limit OFFSET $offset";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch Available Categories
            $cat_stmt = $pdo->query("SELECT DISTINCT category FROM blog_posts WHERE status = 'published' AND category != '' ORDER BY category ASC");
            $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

            // Fetch Top Featured Hero Post
            $hero_stmt = $pdo->query("SELECT id, title, slug, subheadline, category, tags, cover_image, video_url, author_name, author_avatar, published_at, views_count, likes_count, comments_count, reading_time FROM blog_posts WHERE status = 'published' AND featured = 1 ORDER BY id DESC LIMIT 1");
            $hero_post = $hero_stmt->fetch(PDO::FETCH_ASSOC) ?: ($posts[0] ?? null);

            echo json_encode([
                'success' => true,
                'posts' => $posts,
                'total' => $total_count,
                'page' => $page,
                'limit' => $limit,
                'categories' => $categories,
                'hero_post' => $hero_post
            ]);
            break;

        // ── PUBLIC: GET SINGLE POST DETAILS ────────────────────────────────
        case 'get_blog_post':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? $raw_input['id'] ?? 0);
            $slug = trim($_GET['slug'] ?? $_POST['slug'] ?? $raw_input['slug'] ?? '');

            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
            } elseif (!empty($slug)) {
                $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ?");
                $stmt->execute([$slug]);
            } else {
                echo json_encode(['error' => 'Post ID or Slug is required.']);
                exit;
            }

            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                http_response_code(404);
                echo json_encode(['error' => 'Blog article not found.']);
                exit;
            }

            // Check view access (if unpublished & non-admin, deny)
            if ($post['status'] !== 'published' && !$is_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'This article is currently unpublished or pending scheduled release.']);
                exit;
            }

            // Increment View Counter (rate-limited per session)
            $view_key = 'viewed_blog_' . $post['id'];
            if (!isset($_SESSION[$view_key])) {
                $_SESSION[$view_key] = true;
                $pdo->prepare("UPDATE blog_posts SET views_count = views_count + 1 WHERE id = ?")->execute([$post['id']]);
                $post['views_count']++;
            }

            // Check if current user/IP liked this post
            $user_id = $_SESSION['user']['id'] ?? 0;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $sess_id = session_id();

            if ($user_id > 0) {
                $like_chk = $pdo->prepare("SELECT id FROM blog_likes WHERE post_id = ? AND user_id = ?");
                $like_chk->execute([$post['id'], $user_id]);
            } else {
                $like_chk = $pdo->prepare("SELECT id FROM blog_likes WHERE post_id = ? AND (ip_address = ? OR session_id = ?)");
                $like_chk->execute([$post['id'], $ip, $sess_id]);
            }
            $post['has_liked'] = (bool)$like_chk->fetchColumn();

            // Fetch Approved Comments with Threaded Replies & Likes Check
            $com_stmt = $pdo->prepare("SELECT id, parent_id, user_id, author_name, author_avatar, comment, likes_count, created_at FROM blog_comments WHERE post_id = ? AND status = 'approved' ORDER BY id ASC");
            $com_stmt->execute([$post['id']]);
            $all_comments = $com_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Filter out comments by users blocked by current reader
            $blk_stmt = $pdo->prepare("SELECT blocked_author_name FROM blog_user_blocks WHERE (blocker_user_id > 0 AND blocker_user_id = ?) OR (blocker_ip != '' AND blocker_ip = ?)");
            $blk_stmt->execute([$user_id, $ip]);
            $blocked_names = $blk_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            if (!empty($blocked_names)) {
                $all_comments = array_values(array_filter($all_comments, function($c) use ($blocked_names) {
                    return !in_array($c['author_name'], $blocked_names);
                }));
            }

            // Enhance comments with has_liked state for current user/IP
            foreach ($all_comments as &$c) {
                if ($user_id > 0) {
                    $c_chk = $pdo->prepare("SELECT id FROM blog_comment_likes WHERE comment_id = ? AND user_id = ?");
                    $c_chk->execute([$c['id'], $user_id]);
                } else {
                    $c_chk = $pdo->prepare("SELECT id FROM blog_comment_likes WHERE comment_id = ? AND (ip_address = ? OR session_id = ?)");
                    $c_chk->execute([$c['id'], $ip, $sess_id]);
                }
                $c['has_liked'] = (bool)$c_chk->fetchColumn();
                $c['replies'] = [];
            }
            unset($c);

            // Group into threaded parent -> replies structure
            $top_comments = [];
            $replies_map = [];

            foreach ($all_comments as $c) {
                if ($c['parent_id'] > 0) {
                    $replies_map[$c['parent_id']][] = $c;
                } else {
                    $top_comments[] = $c;
                }
            }

            foreach ($top_comments as &$top_c) {
                if (isset($replies_map[$top_c['id']])) {
                    $top_c['replies'] = $replies_map[$top_c['id']];
                }
            }
            unset($top_c);

            // Fetch Related Articles in same category
            $rel_stmt = $pdo->prepare("SELECT id, title, slug, category, cover_image, reading_time, published_at FROM blog_posts WHERE category = ? AND id != ? AND status = 'published' ORDER BY id DESC LIMIT 3");
            $rel_stmt->execute([$post['category'], $post['id']]);
            $related = $rel_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'post' => $post,
                'comments' => array_reverse($top_comments),
                'related' => $related
            ]);
            break;

        // ── PUBLIC / AUTH: LIKE BLOG POST ──────────────────────────────────
        case 'like_blog_post':
            $post_id = intval($_POST['post_id'] ?? $raw_input['post_id'] ?? $_GET['post_id'] ?? 0);
            if ($post_id <= 0) {
                echo json_encode(['error' => 'Valid post ID required.']);
                exit;
            }

            $user_id = $_SESSION['user']['id'] ?? 0;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $sess_id = session_id();

            // Check if already liked
            if ($user_id > 0) {
                $stmt = $pdo->prepare("SELECT id FROM blog_likes WHERE post_id = ? AND user_id = ?");
                $stmt->execute([$post_id, $user_id]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM blog_likes WHERE post_id = ? AND (ip_address = ? OR session_id = ?)");
                $stmt->execute([$post_id, $ip, $sess_id]);
            }
            $existing_like_id = $stmt->fetchColumn();

            if ($existing_like_id) {
                // UNLIKE
                $pdo->prepare("DELETE FROM blog_likes WHERE id = ?")->execute([$existing_like_id]);
                $pdo->prepare("UPDATE blog_posts SET likes_count = MAX(0, likes_count - 1) WHERE id = ?")->execute([$post_id]);
                $liked = false;
            } else {
                // LIKE
                $ins = $pdo->prepare("INSERT INTO blog_likes (post_id, user_id, ip_address, session_id) VALUES (?, ?, ?, ?)");
                $ins->execute([$post_id, $user_id, $ip, $sess_id]);
                $pdo->prepare("UPDATE blog_posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$post_id]);
                $liked = true;
            }

            // Get updated count
            $cnt_stmt = $pdo->prepare("SELECT likes_count FROM blog_posts WHERE id = ?");
            $cnt_stmt->execute([$post_id]);
            $likes_count = (int)$cnt_stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'liked' => $liked,
                'likes_count' => $likes_count
            ]);
            break;

        // ── PUBLIC / AUTH: LIKE COMMENT ────────────────────────────────────
        case 'like_blog_comment':
            $comment_id = intval($_POST['comment_id'] ?? $raw_input['comment_id'] ?? $_GET['comment_id'] ?? 0);
            if ($comment_id <= 0) {
                echo json_encode(['error' => 'Valid comment ID required.']);
                exit;
            }

            $user_id = $_SESSION['user']['id'] ?? 0;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $sess_id = session_id();

            if ($user_id > 0) {
                $stmt = $pdo->prepare("SELECT id FROM blog_comment_likes WHERE comment_id = ? AND user_id = ?");
                $stmt->execute([$comment_id, $user_id]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM blog_comment_likes WHERE comment_id = ? AND (ip_address = ? OR session_id = ?)");
                $stmt->execute([$comment_id, $ip, $sess_id]);
            }
            $existing_like_id = $stmt->fetchColumn();

            if ($existing_like_id) {
                // UNLIKE COMMENT
                $pdo->prepare("DELETE FROM blog_comment_likes WHERE id = ?")->execute([$existing_like_id]);
                $pdo->prepare("UPDATE blog_comments SET likes_count = MAX(0, likes_count - 1) WHERE id = ?")->execute([$comment_id]);
                $liked = false;
            } else {
                // LIKE COMMENT
                $ins = $pdo->prepare("INSERT INTO blog_comment_likes (comment_id, user_id, ip_address, session_id) VALUES (?, ?, ?, ?)");
                $ins->execute([$comment_id, $user_id, $ip, $sess_id]);
                $pdo->prepare("UPDATE blog_comments SET likes_count = likes_count + 1 WHERE id = ?")->execute([$comment_id]);
                $liked = true;
            }

            $cnt_stmt = $pdo->prepare("SELECT likes_count FROM blog_comments WHERE id = ?");
            $cnt_stmt->execute([$comment_id]);
            $likes_count = (int)$cnt_stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'liked' => $liked,
                'likes_count' => $likes_count
            ]);
            break;

        // ── PUBLIC / AUTH: ADD COMMENT OR REPLY ────────────────────────────
        case 'add_blog_comment':
            $post_id = intval($_POST['post_id'] ?? $raw_input['post_id'] ?? 0);
            $parent_id = intval($_POST['parent_id'] ?? $raw_input['parent_id'] ?? 0);
            $comment_text = trim($_POST['comment'] ?? $raw_input['comment'] ?? '');
            
            if ($post_id <= 0 || empty($comment_text)) {
                echo json_encode(['error' => 'Post ID and comment content are required.']);
                exit;
            }

            if (mb_strlen($comment_text) > 1000) {
                echo json_encode(['error' => 'Comment text is too long (maximum 1,000 characters).']);
                exit;
            }

            $user = $_SESSION['user'] ?? null;
            $user_id = $user['id'] ?? 0;
            
            $author_name = trim($_POST['author_name'] ?? $raw_input['author_name'] ?? ($user['name'] ?? ''));
            $author_email = trim($_POST['author_email'] ?? $raw_input['author_email'] ?? ($user['email'] ?? ''));
            $author_avatar = $user['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=150';

            if (empty($author_name)) {
                $author_name = 'Guest Reader';
            }

            $now_str = date('Y-m-d H:i:s');
            $status = 'approved';

            $ins = $pdo->prepare("INSERT INTO blog_comments (post_id, parent_id, user_id, author_name, author_email, author_avatar, comment, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$post_id, $parent_id, $user_id, htmlspecialchars($author_name), htmlspecialchars($author_email), $author_avatar, htmlspecialchars($comment_text), $status, $now_str]);

            // Update comments_count on post
            $pdo->prepare("UPDATE blog_posts SET comments_count = comments_count + 1 WHERE id = ?")->execute([$post_id]);

            echo json_encode([
                'success' => true,
                'message' => $parent_id > 0 ? 'Reply posted successfully!' : 'Comment posted successfully!',
                'comment' => [
                    'id' => $pdo->lastInsertId(),
                    'parent_id' => $parent_id,
                    'author_name' => htmlspecialchars($author_name),
                    'author_avatar' => $author_avatar,
                    'comment' => htmlspecialchars($comment_text),
                    'likes_count' => 0,
                    'has_liked' => false,
                    'created_at' => $now_str
                ]
            ]);
            break;

        // ── PUBLIC: SHARE INCREMENT ────────────────────────────────────────
        case 'share_blog_post':
            $post_id = intval($_POST['post_id'] ?? $raw_input['post_id'] ?? 0);
            if ($post_id > 0) {
                $pdo->prepare("UPDATE blog_posts SET shares_count = shares_count + 1 WHERE id = ?")->execute([$post_id]);
            }
            echo json_encode(['success' => true]);
            break;

        // ── PUBLIC / AUTH: REPORT BLOG COMMENT ─────────────────────────────
        case 'report_blog_comment':
            $comment_id = intval($_POST['comment_id'] ?? $raw_input['comment_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? $raw_input['reason'] ?? 'Inappropriate content');
            $user_id = $_SESSION['user']['id'] ?? 0;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            if ($comment_id > 0) {
                $ins = $pdo->prepare("INSERT INTO blog_comment_reports (comment_id, reporter_user_id, reporter_ip, reason) VALUES (?, ?, ?, ?)");
                $ins->execute([$comment_id, $user_id, $ip, $reason]);
            }
            echo json_encode(['success' => true, 'message' => 'Comment reported for review.']);
            break;

        // ── PUBLIC / AUTH: BLOCK USER IN BLOG ──────────────────────────────
        case 'block_blog_user':
            $blocked_author = trim($_POST['blocked_author'] ?? $raw_input['blocked_author'] ?? '');
            $blocked_uid = intval($_POST['blocked_user_id'] ?? $raw_input['blocked_user_id'] ?? 0);
            $user_id = $_SESSION['user']['id'] ?? 0;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            if (!empty($blocked_author)) {
                $ins = $pdo->prepare("INSERT INTO blog_user_blocks (blocker_user_id, blocker_ip, blocked_author_name, blocked_user_id) VALUES (?, ?, ?, ?)");
                $ins->execute([$user_id, $ip, $blocked_author, $blocked_uid]);
            }
            echo json_encode(['success' => true, 'message' => 'User blocked from your blog comment feed.']);
            break;

        // ── ADMIN: LIST ALL POSTS & STATS ──────────────────────────────────
        case 'admin_get_blog_posts':
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin authorization required.']);
                exit;
            }

            $search = trim($_GET['search'] ?? $_POST['search'] ?? '');
            $status = trim($_GET['status'] ?? $_POST['status'] ?? '');
            $category = trim($_GET['category'] ?? $_POST['category'] ?? '');

            $where = ["1=1"];
            $params = [];

            if (!empty($search)) {
                $where[] = "(title LIKE ? OR subheadline LIKE ? OR category LIKE ?)";
                $s_param = "%$search%";
                $params[] = $s_param;
                $params[] = $s_param;
                $params[] = $s_param;
            }

            if (!empty($status) && $status !== 'all') {
                $where[] = "status = ?";
                $params[] = $status;
            }

            if (!empty($category) && $category !== 'all') {
                $where[] = "category = ?";
                $params[] = $category;
            }

            $where_sql = implode(' AND ', $where);

            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE $where_sql ORDER BY id DESC");
            $stmt->execute($params);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch Overview Metrics
            $stats = [
                'total_posts' => (int)$pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn(),
                'published' => (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn(),
                'drafts' => (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'")->fetchColumn(),
                'scheduled' => (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'scheduled'")->fetchColumn(),
                'total_views' => (int)$pdo->query("SELECT SUM(views_count) FROM blog_posts")->fetchColumn(),
                'total_likes' => (int)$pdo->query("SELECT SUM(likes_count) FROM blog_posts")->fetchColumn(),
                'total_comments' => (int)$pdo->query("SELECT COUNT(*) FROM blog_comments")->fetchColumn()
            ];

            echo json_encode([
                'success' => true,
                'posts' => $posts,
                'stats' => $stats
            ]);
            break;

        // ── ADMIN: SAVE / UPDATE POST ──────────────────────────────────────
        case 'admin_save_blog_post':
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin authorization required.']);
                exit;
            }

            $id = intval($_POST['id'] ?? $raw_input['id'] ?? 0);
            $title = trim($_POST['title'] ?? $raw_input['title'] ?? '');
            $subheadline = trim($_POST['subheadline'] ?? $raw_input['subheadline'] ?? '');
            $category = trim($_POST['category'] ?? $raw_input['category'] ?? 'General');
            $tags = trim($_POST['tags'] ?? $raw_input['tags'] ?? '');
            $content = trim($_POST['content'] ?? $raw_input['content'] ?? '');
            $video_url = trim($_POST['video_url'] ?? $raw_input['video_url'] ?? '');
            $author_name = trim($_POST['author_name'] ?? $raw_input['author_name'] ?? 'Ohati Editorial');
            $author_avatar = trim($_POST['author_avatar'] ?? $raw_input['author_avatar'] ?? 'img/new_icon_ohati.png');
            $status = trim($_POST['status'] ?? $raw_input['status'] ?? 'published');
            $scheduled_at = trim($_POST['scheduled_at'] ?? $raw_input['scheduled_at'] ?? '');
            $featured = isset($_POST['featured']) ? intval($_POST['featured']) : (isset($raw_input['featured']) ? intval($raw_input['featured']) : 0);

            if (empty($title)) {
                echo json_encode(['error' => 'Article title is required.']);
                exit;
            }

            if (empty($content)) {
                echo json_encode(['error' => 'Article content cannot be empty.']);
                exit;
            }

            // Handle Cover Image Upload if provided
            $cover_image = trim($_POST['cover_image'] ?? $raw_input['cover_image'] ?? '');
            if (isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] === UPLOAD_ERR_OK) {
                require_once __DIR__ . '/storage_helper.php';
                $up_res = upload_media_file($_FILES['cover_image_file'], 'blog', 1920);
                if (!empty($up_res['url'])) {
                    $cover_image = $up_res['url'];
                }
            }

            // Calculate reading time estimate (approx 200 words per min)
            $word_count = str_word_count(strip_tags($content));
            $reading_time = max(1, (int)ceil($word_count / 200));

            // Generate Slug
            $slug = generate_blog_slug($title, $pdo, $id);
            $now_str = date('Y-m-d H:i:s');
            $published_at = ($status === 'published') ? $now_str : '';

            if ($id > 0) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, subheadline = ?, category = ?, tags = ?, cover_image = ?, content = ?, video_url = ?, author_name = ?, author_avatar = ?, status = ?, scheduled_at = ?, reading_time = ?, featured = ?, updated_at = ? WHERE id = ?");
                $stmt->execute([
                    $title, $slug, $subheadline, $category, $tags,
                    $cover_image, $content, $video_url, $author_name, $author_avatar,
                    $status, $scheduled_at, $reading_time, $featured, $now_str, $id
                ]);
                $post_id = $id;
                $msg = 'Blog article updated successfully!';
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, subheadline, category, tags, cover_image, content, video_url, author_name, author_avatar, status, scheduled_at, published_at, reading_time, featured, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $title, $slug, $subheadline, $category, $tags,
                    $cover_image, $content, $video_url, $author_name, $author_avatar,
                    $status, $scheduled_at, $published_at, $reading_time, $featured, $now_str, $now_str
                ]);
                $post_id = $pdo->lastInsertId();
                $msg = 'Blog article created successfully!';
            }

            echo json_encode([
                'success' => true,
                'message' => $msg,
                'post_id' => $post_id,
                'slug' => $slug
            ]);
            break;

        // ── ADMIN: TOGGLE PUBLISH / UNPUBLISH ──────────────────────────────
        case 'admin_toggle_blog_status':
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin authorization required.']);
                exit;
            }

            $id = intval($_POST['id'] ?? $raw_input['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['error' => 'Valid post ID required.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT status FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $curr_status = $stmt->fetchColumn();

            $new_status = ($curr_status === 'published') ? 'draft' : 'published';
            $now_str = date('Y-m-d H:i:s');

            $up = $pdo->prepare("UPDATE blog_posts SET status = ?, published_at = ?, updated_at = ? WHERE id = ?");
            $up->execute([$new_status, ($new_status === 'published' ? $now_str : ''), $now_str, $id]);

            echo json_encode([
                'success' => true,
                'status' => $new_status,
                'message' => 'Article status updated to ' . ucfirst($new_status)
            ]);
            break;

        // ── ADMIN: DELETE BLOG POST ────────────────────────────────────────
        case 'admin_delete_blog_post':
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin authorization required.']);
                exit;
            }

            $id = intval($_POST['id'] ?? $raw_input['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['error' => 'Valid post ID required.']);
                exit;
            }

            $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM blog_comments WHERE post_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM blog_likes WHERE post_id = ?")->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Blog post and associated data permanently deleted.'
            ]);
            break;

        // ── ADMIN: UPLOAD INLINE IMAGE FOR EDITOR ──────────────────────────
        case 'admin_upload_blog_image':
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin authorization required.']);
                exit;
            }

            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'Image upload file missing or invalid.']);
                exit;
            }

            require_once __DIR__ . '/storage_helper.php';
            $up_res = upload_media_file($_FILES['image'], 'blog', 1600);

            if (!empty($up_res['url'])) {
                echo json_encode([
                    'success' => true,
                    'url' => $up_res['url']
                ]);
            } else {
                echo json_encode(['error' => $up_res['error'] ?? 'Image upload failed.']);
            }
            break;

        // ── ADMIN: MANAGE COMMENTS ─────────────────────────────────────────
        case 'admin_manage_comment':
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin authorization required.']);
                exit;
            }

            $comment_id = intval($_POST['comment_id'] ?? $raw_input['comment_id'] ?? 0);
            $action_type = trim($_POST['type'] ?? $raw_input['type'] ?? ''); // approve, hide, delete

            if ($comment_id <= 0) {
                echo json_encode(['error' => 'Comment ID required.']);
                exit;
            }

            if ($action_type === 'delete') {
                // Get post_id first to decrement count
                $p_stmt = $pdo->prepare("SELECT post_id FROM blog_comments WHERE id = ?");
                $p_stmt->execute([$comment_id]);
                $post_id = $p_stmt->fetchColumn();

                $pdo->prepare("DELETE FROM blog_comments WHERE id = ?")->execute([$comment_id]);
                if ($post_id) {
                    $pdo->prepare("UPDATE blog_posts SET comments_count = MAX(0, comments_count - 1) WHERE id = ?")->execute([$post_id]);
                }
                echo json_encode(['success' => true, 'message' => 'Comment deleted.']);
            } elseif ($action_type === 'approve') {
                $pdo->prepare("UPDATE blog_comments SET status = 'approved' WHERE id = ?")->execute([$comment_id]);
                echo json_encode(['success' => true, 'message' => 'Comment approved.']);
            } elseif ($action_type === 'hide') {
                $pdo->prepare("UPDATE blog_comments SET status = 'spam' WHERE id = ?")->execute([$comment_id]);
                echo json_encode(['success' => true, 'message' => 'Comment marked as spam/hidden.']);
            } else {
                echo json_encode(['error' => 'Invalid action type.']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown blog API action: '$action'"]);
            break;
    }
}
