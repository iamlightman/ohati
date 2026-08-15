<?php
// admin/content.php - Ohati Admin Panel Frontend Content Management
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Database-agnostic setting helpers
function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = ?")->execute([$value, $key]);
        } else {
            $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES (?, ?)")->execute([$key, $value]);
        }
    } catch (Exception $e) {}
}

$success_msg = '';
$error_msg = '';

// Load reviews list or fall back to default shortened reviews with <br> tags
$reviews_json = getSetting('platform_reviews', '');
if (empty($reviews_json)) {
    $default_reviews = [
        [
            'id' => 1,
            'name' => 'Abena Boateng',
            'rating' => 5,
            'comment' => 'Ohati made finding my decorator simple.<br>Verified badges gave us real confidence.',
            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=150'
        ],
        [
            'id' => 2,
            'name' => 'Kwame Mensah',
            'rating' => 5,
            'comment' => 'Booked our photographer through Ohati.<br>Process was seamless from start to finish.',
            'avatar' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=150'
        ],
        [
            'id' => 3,
            'name' => 'Adjoa Sarfo',
            'rating' => 5,
            'comment' => 'Great support & easy vendor bookings.<br>Budget planner kept us on track.',
            'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=150'
        ],
        [
            'id' => 4,
            'name' => 'Yaw Osei',
            'rating' => 5,
            'comment' => 'Best catering deals found here.<br>The comparison helper is superb.',
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=150'
        ]
    ];
    setSetting('platform_reviews', json_encode($default_reviews));
    $reviews = $default_reviews;
} else {
    $reviews = json_decode($reviews_json, true) ?: [];
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_hero') {
        setSetting('hero_title', trim($_POST['hero_title'] ?? ''));
        setSetting('hero_subtitle', trim($_POST['hero_subtitle'] ?? ''));
        setSetting('hero_banner_image', trim($_POST['hero_banner_image'] ?? ''));
        $success_msg = 'Homepage banner content saved successfully.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_review') {
        $new_id = time();
        $name = trim($_POST['name'] ?? '');
        $rating = intval($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');

        if ($name !== '' && $comment !== '') {
            $reviews[] = [
                'id' => $new_id,
                'name' => $name,
                'rating' => $rating,
                'comment' => $comment,
                'avatar' => $avatar ?: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>"
            ];
            setSetting('platform_reviews', json_encode($reviews));
            $success_msg = 'Testimonial review added successfully.';
        } else {
            $error_msg = 'Name and review text are required.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_review') {
        $rid = intval($_POST['review_id'] ?? 0);
        $reviews = array_filter($reviews, function($r) use ($rid) {
            return $r['id'] !== $rid;
        });
        $reviews = array_values($reviews); // re-index
        setSetting('platform_reviews', json_encode($reviews));
        $success_msg = 'Testimonial review removed successfully.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_faq') {
        $question = trim($_POST['question'] ?? '');
        $answer = $_POST['answer'] ?? '';
        $category = trim($_POST['category'] ?? 'General');
        $display_order = intval($_POST['display_order'] ?? 0);

        if ($question !== '' && $answer !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO faqs (category, question, answer, display_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$category, $question, $answer, $display_order]);
                $success_msg = 'FAQ added successfully.';
            } catch (Exception $e) {
                $error_msg = 'Failed to add FAQ: ' . $e->getMessage();
            }
        } else {
            $error_msg = 'Question and answer are required.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_faq') {
        $fid = intval($_POST['faq_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer = $_POST['answer'] ?? '';
        $category = trim($_POST['category'] ?? 'General');
        $display_order = intval($_POST['display_order'] ?? 0);

        if ($fid > 0 && $question !== '' && $answer !== '') {
            try {
                $stmt = $pdo->prepare("UPDATE faqs SET category = ?, question = ?, answer = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$category, $question, $answer, $display_order, $fid]);
                $success_msg = 'FAQ updated successfully.';
            } catch (Exception $e) {
                $error_msg = 'Failed to update FAQ: ' . $e->getMessage();
            }
        } else {
            $error_msg = 'FAQ ID, question, and answer are required.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_faq') {
        $fid = intval($_POST['faq_id'] ?? 0);
        if ($fid > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
                $stmt->execute([$fid]);
                $success_msg = 'FAQ deleted successfully.';
            } catch (Exception $e) {
                $error_msg = 'Failed to delete FAQ: ' . $e->getMessage();
            }
        }
    }
}

// Fetch current values
$hero_title = getSetting('hero_title', 'Extraordinary events start with the right people.');
$hero_subtitle = getSetting('hero_subtitle', 'Find. Compare. Book. Celebrate.');
$hero_banner_image = getSetting('hero_banner_image', 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?q=80&w=1200');
$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn();

// Fetch FAQs
$faqs = [];
try {
    $stmt = $pdo->query("SELECT * FROM faqs ORDER BY category ASC, display_order ASC, id ASC");
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Front Content Management</title>
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
        }
        .admin-sidebar-logo img {
            height: 36px;
            width: auto;
            object-fit: contain;
            border-radius: 0;
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media(max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
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
                <h1 class="admin-page-title">Frontend Content Manager</h1>
            </div>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-circle-user" style="font-size:1.2rem; color:var(--accent);"></i>
                <span>System Administrator</span>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="admin-content">

            <!-- Success/Error Banners -->
            <?php if (!empty($success_msg)): ?>
                <div class="card mb-20" style="background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); border-radius:12px; padding:14px 20px; color:var(--success); font-weight:600; font-size:0.85rem;">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="card mb-20" style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:12px; padding:14px 20px; color:var(--rose); font-weight:600; font-size:0.85rem;">
                    <i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <div class="content-grid">
                
                <!-- Left: Testimonials / Reviews Management -->
                <div class="card" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:24px;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin-top:0; margin-bottom:20px; border-bottom:1px solid #F0F2F5; padding-bottom:12px;">Homepage Testimonials (Couples & Planners Say)</h3>
                    
                    <!-- Add Review Form -->
                    <form method="POST" action="content.php" style="background:var(--gray-50); padding:16px; border-radius:12px; margin-bottom:24px;">
                        <input type="hidden" name="action" value="add_review">
                        <h4 style="margin:0 0 12px; font-size:0.85rem; text-transform:uppercase; color:var(--primary);">Add New Testimonial</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                            <div>
                                <label class="form-label" style="font-size:0.75rem;">Client Name</label>
                                <input type="text" name="name" class="form-input" style="padding:8px 12px; margin:0;" placeholder="e.g. Abena Boateng" required>
                            </div>
                            <div>
                                <label class="form-label" style="font-size:0.75rem;">Avatar URL (Optional)</label>
                                <input type="text" name="avatar" class="form-input" style="padding:8px 12px; margin:0;" placeholder="Image URL">
                            </div>
                        </div>
                        <div class="form-group mb-12">
                            <label class="form-label" style="font-size:0.75rem;">Review Text (use &lt;br&gt; to break lines cleanly)</label>
                            <textarea name="comment" class="form-input" style="height:60px; padding:8px 12px; margin:0;" placeholder="Ohati made finding my decorator simple.<br>Verified badges gave us real confidence." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="padding:8px 16px;"><i class="fa-solid fa-plus"></i> Add Testimonial</button>
                    </form>

                    <!-- Reviews List -->
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($reviews as $r): ?>
                            <div style="border:1px solid #E4E7ED; border-radius:12px; padding:12px; display:flex; justify-content:space-between; align-items:start; gap:12px;">
                                <div style="display:flex; gap:12px; align-items:start;">
                                    <img src="<?= htmlspecialchars($r['avatar']) ?>" alt="avatar" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid #E4E7ED;">
                                    <div>
                                        <div style="font-weight:700; color:var(--primary); font-size:0.83rem;"><?= htmlspecialchars($r['name']) ?></div>
                                        <div style="font-size:0.78rem; color:var(--gray-600); margin-top:4px; line-height:1.4;"><?= $r['comment'] ?></div>
                                    </div>
                                </div>
                                <form method="POST" action="content.php" onsubmit="return confirm('Remove this testimonial?');">
                                    <input type="hidden" name="action" value="delete_review">
                                    <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                    <button type="submit" style="background:none; border:none; color:var(--rose); cursor:pointer; padding:4px;"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Homepage Hero Header -->
                <div class="card" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:24px; height:fit-content;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin-top:0; margin-bottom:20px; border-bottom:1px solid #F0F2F5; padding-bottom:12px;">Homepage Banner Texts</h3>
                    
                    <form method="POST" action="content.php">
                        <input type="hidden" name="action" value="save_hero">
                        
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;">Hero Main Title</label>
                            <input type="text" name="hero_title" class="form-input" value="<?= htmlspecialchars($hero_title) ?>" required>
                        </div>

                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700;">Hero Subtitle</label>
                            <textarea name="hero_subtitle" class="form-input" style="height:60px;" required><?= htmlspecialchars($hero_subtitle) ?></textarea>
                        </div>

                        <div class="form-group mb-20">
                            <label class="form-label" style="font-weight:700;">Hero Background Image URL</label>
                            <input type="text" name="hero_banner_image" class="form-input" value="<?= htmlspecialchars($hero_banner_image) ?>" placeholder="https://..." required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-full" style="padding:10px; font-weight:700;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Hero Texts
                        </button>
                    </form>
                </div>

            </div>

            <!-- Frequently Asked Questions (FAQ) Manager -->
            <div class="card mt-24" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:24px; margin-top: 24px;">
                <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin-top:0; margin-bottom:20px; border-bottom:1px solid #F0F2F5; padding-bottom:12px;">Frequently Asked Questions (FAQ) Manager</h3>
                
                <div id="faq-forms-anchor"></div>
                
                <!-- Add FAQ Form -->
                <div id="add-faq-form-container" style="background:var(--gray-50); padding:20px; border-radius:12px; margin-bottom:24px;">
                    <form method="POST" action="content.php">
                        <input type="hidden" name="action" value="add_faq">
                        <h4 style="margin:0 0 16px; font-size:0.9rem; text-transform:uppercase; color:var(--primary); font-weight:700;">Add New FAQ</h4>
                        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:16px;">
                            <div>
                                <label class="form-label" style="font-size:0.75rem; font-weight:700;">Category</label>
                                <input type="text" name="category" class="form-input" placeholder="e.g. General, Payments & Withdrawals" required style="margin:0;">
                            </div>
                            <div>
                                <label class="form-label" style="font-size:0.75rem; font-weight:700;">Display Order</label>
                                <input type="number" name="display_order" class="form-input" value="0" min="0" required style="margin:0;">
                            </div>
                        </div>
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-size:0.75rem; font-weight:700;">Question</label>
                            <input type="text" name="question" class="form-input" placeholder="e.g. How do I reset my password?" required style="margin:0;">
                        </div>
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-size:0.75rem; font-weight:700;">Answer (HTML allowed)</label>
                            <textarea name="answer" class="form-input" style="height:100px; padding:12px; margin:0;" placeholder="Provide a detailed answer..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="padding:10px 20px;"><i class="fa-solid fa-plus"></i> Add FAQ Entry</button>
                    </form>
                </div>

                <!-- Edit FAQ Form (hidden by default) -->
                <div id="edit-faq-form-container" style="background:var(--gray-100); padding:20px; border-radius:12px; margin-bottom:24px; display:none; border: 2px solid var(--primary);">
                    <form method="POST" action="content.php">
                        <input type="hidden" name="action" value="update_faq">
                        <input type="hidden" name="faq_id" id="edit_faq_id">
                        <h4 style="margin:0 0 16px; font-size:0.9rem; text-transform:uppercase; color:var(--primary); font-weight:700;">Edit FAQ Entry</h4>
                        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:16px;">
                            <div>
                                <label class="form-label" style="font-size:0.75rem; font-weight:700;">Category</label>
                                <input type="text" name="category" id="edit_faq_category" class="form-input" required style="margin:0;">
                            </div>
                            <div>
                                <label class="form-label" style="font-size:0.75rem; font-weight:700;">Display Order</label>
                                <input type="number" name="display_order" id="edit_faq_order" class="form-input" required style="margin:0;">
                            </div>
                        </div>
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-size:0.75rem; font-weight:700;">Question</label>
                            <input type="text" name="question" id="edit_faq_question" class="form-input" required style="margin:0;">
                        </div>
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-size:0.75rem; font-weight:700;">Answer (HTML allowed)</label>
                            <textarea name="answer" id="edit_faq_answer" class="form-input" style="height:100px; padding:12px; margin:0;" required></textarea>
                        </div>
                        <div style="display:flex; gap:12px;">
                            <button type="submit" class="btn btn-primary btn-sm" style="padding:10px 20px;"><i class="fa-solid fa-floppy-disk"></i> Update FAQ Entry</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="cancelEditFaq()" style="padding:10px 20px;">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- FAQ Table -->
                <div style="overflow-x:auto;">
                    <table class="admin-table" style="width:100%; border-collapse:collapse; font-size:0.83rem;">
                        <thead>
                            <tr style="background:var(--gray-50); border-bottom:1px solid #E4E7ED; text-align:left;">
                                <th style="padding:12px; width:80px;">Order</th>
                                <th style="padding:12px; width:180px;">Category</th>
                                <th style="padding:12px;">Question & Answer</th>
                                <th style="padding:12px; width:140px; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($faqs)): ?>
                                <tr>
                                    <td colspan="4" style="padding:20px; text-align:center; color:var(--gray-400);">No FAQs loaded in the system database.</td>
                                </tr>
                            <?php else: foreach ($faqs as $f): ?>
                                <tr style="border-bottom:1px solid #F0F2F5;">
                                    <td style="padding:12px; font-weight:700;"><?= htmlspecialchars($f['display_order']) ?></td>
                                    <td style="padding:12px;"><span style="background:rgba(27,43,75,0.1); color:var(--primary); padding:3px 8px; border-radius:4px; font-size:0.72rem; font-weight:700;"><?= htmlspecialchars($f['category']) ?></span></td>
                                    <td style="padding:12px; line-height:1.4;">
                                        <div style="font-weight:700; color:var(--primary); margin-bottom:4px;"><?= htmlspecialchars($f['question']) ?></div>
                                        <div style="font-size:0.78rem; color:var(--gray-600); max-height:60px; overflow:hidden; text-overflow:ellipsis;"><?= strip_tags($f['answer']) ?></div>
                                    </td>
                                    <td style="padding:12px; text-align:right; white-space:nowrap; vertical-align:middle;">
                                        <button type="button" class="btn btn-outline btn-xs" onclick="editFaq(<?= $f['id'] ?>, '<?= addslashes($f['category']) ?>', '<?= addslashes($f['question']) ?>', '<?= addslashes($f['answer']) ?>', <?= $f['display_order'] ?>)" style="padding:4px 8px; margin-right:6px; font-size:0.75rem;"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                                        
                                        <form method="POST" action="content.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                            <input type="hidden" name="action" value="delete_faq">
                                            <input type="hidden" name="faq_id" value="<?= $f['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-xs" style="padding:4px 8px; font-size:0.75rem; background:var(--rose); color:#fff; border:none; border-radius:4px; cursor:pointer;"><i class="fa-solid fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        function editFaq(id, category, question, answer, order) {
            document.getElementById('edit_faq_id').value = id;
            document.getElementById('edit_faq_category').value = category;
            document.getElementById('edit_faq_question').value = question;
            document.getElementById('edit_faq_answer').value = answer;
            document.getElementById('edit_faq_order').value = order;
            
            document.getElementById('add-faq-form-container').style.display = 'none';
            document.getElementById('edit-faq-form-container').style.display = 'block';
            
            // Scroll to form
            document.getElementById('faq-forms-anchor').scrollIntoView({ behavior: 'smooth' });
        }

        function cancelEditFaq() {
            document.getElementById('add-faq-form-container').style.display = 'block';
            document.getElementById('edit-faq-form-container').style.display = 'none';
        }

        function toggleSidebar(open) {
            const sidebar = document.querySelector('.admin-sidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
        }
    </script>
</body>
</html>
