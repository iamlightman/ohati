<?php
// admin/kyc.php - Ohati Admin KYC Verifications
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Handle AJAX actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $uid = intval($input['user_id'] ?? 0);
    $action = $input['action'] ?? ''; // approve, reject
    
    if ($uid > 0 && in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'verified' : 'rejected';
        $badge = ($action === 'approve') ? 'blue' : 'grey';
        
        $verified_val = ($action === 'approve') ? 1 : 0;
        $now_str = date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE users SET kyc_status = ?, kyc_reviewed_at = ? WHERE id = ?")->execute([$status, $now_str, $uid]);
        $pdo->prepare("UPDATE vendors SET verification_status = ?, verification_badge = ?, verified = ? WHERE user_id = ?")->execute([$status, $badge, $verified_val, $uid]);
        
        // Send professional email notification
        try {
            $uStmt = $pdo->prepare("SELECT u.name, u.email, v.name as biz_name FROM users u LEFT JOIN vendors v ON u.id = v.user_id WHERE u.id = ?");
            $uStmt->execute([$uid]);
            $user_info = $uStmt->fetch();
            if ($user_info && !empty($user_info['email'])) {
                require_once __DIR__ . '/../mail_helper.php';
                if ($action === 'approve') {
                    $title = "KYC Verification Approved!";
                    $badge_label = "Identity Verified (Blue Badge)";
                    $badge_type = "blue";
                    $details = "Congratulations <strong>" . htmlspecialchars($user_info['biz_name'] ?: $user_info['name']) . "</strong>! Your identity credentials have been reviewed and approved by Ohati Compliance Team. Your business profile now displays the official Blue Identity Verification Badge.";
                } else {
                    $title = "KYC Verification Update Required";
                    $badge_label = "Verification Action Needed";
                    $badge_type = "rejected";
                    $details = "We reviewed your submitted KYC identity documents. Unfortunately, the submitted details could not be verified. Please log into your vendor dashboard to review document clarity and resubmit your Ghana Card.";
                }
                send_admin_notification_email($user_info['email'], $user_info['name'], $title, $badge_label, $badge_type, $details);
            }
        } catch (Exception $mailEx) {}

        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Invalid action or user ID']);
    exit;
}

// Fetch pending verifications
$stmt = $pdo->query("
    SELECT u.id as user_id, u.name as user_name, u.email as user_email, u.phone as user_phone, u.kyc_status, 
           u.kyc_id_type as id_type, u.kyc_submitted_at as submitted_at, u.didit_session_id, u.didit_decision,
           v.id as vendor_id, v.name as biz_name, v.category, v.location, v.experience, v.logo, 
           u.kyc_id_front as id_front, u.kyc_selfie as selfie 
    FROM users u 
    JOIN vendors v ON u.id = v.user_id 
    WHERE (u.kyc_status = 'pending_verification' OR u.kyc_status = 'pending' OR (u.didit_session_id IS NOT NULL AND u.kyc_status NOT IN ('approved', 'verified', 'rejected')))
    ORDER BY u.id DESC
");
$pending = $stmt->fetchAll();
$pending_kyc = count($pending);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - KYC Verification Queue</title>
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

        .kyc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .card-pending {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #E4E7ED;
            display: flex;
            flex-direction: column;
            gap: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card-pending:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .biz-logo {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gray-100);
        }
        .biz-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .biz-name {
            font-family: 'Fraunces', serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }
        .biz-meta {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        .info-grid {
            background: var(--gray-50);
            padding: 12px;
            border-radius: 12px;
            font-size: 0.78rem;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .info-label {
            font-weight: 700;
            color: var(--primary);
            width: 85px;
            display: inline-block;
        }
        .doc-preview-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--gray-500);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .doc-images-row {
            display: flex;
            gap: 12px;
        }
        .doc-thumb {
            flex: 1;
            height: 96px;
            border-radius: 8px;
            background: var(--gray-100);
            border: 1px dashed var(--gray-300);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 0.68rem;
            color: var(--gray-500);
            cursor: pointer;
            overflow: hidden;
            position: relative;
            transition: border-color 0.2s;
        }
        .doc-thumb:hover {
            border-color: var(--accent);
        }
        .doc-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 4px;
        }
        
        /* Lightbox modal styling */
        .lightbox-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            inset: 0;
            background: rgba(15,25,35,0.9);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .lightbox-content {
            max-width: 90%;
            max-height: 85vh;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            background: #fff;
            position: relative;
        }
        .lightbox-content img {
            display: block;
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }
        .lightbox-close {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.8);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            color: var(--primary);
            transition: background 0.2s;
        }
        .lightbox-close:hover {
            background: #fff;
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
                <h1 class="admin-page-title">KYC Verification Queue</h1>
            </div>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-circle-user" style="font-size:1.2rem; color:var(--accent);"></i>
                <span>System Administrator</span>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="admin-content">

            <?php if (empty($pending)): ?>
                <div class="card empty-state" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:60px 20px; text-align:center;">
                    <i class="fa-solid fa-clipboard-check empty-icon" style="font-size:3rem; color:var(--teal); margin-bottom:16px;"></i>
                    <h4 style="font-family:'Fraunces',serif; font-size:1.2rem; color:var(--primary); margin-bottom:6px;">All Caught Up!</h4>
                    <p style="font-size:0.83rem; color:var(--gray-500); margin:0;">There are no vendors pending identity verification (Ghana Card or selfie) at this moment.</p>
                </div>
            <?php else: ?>
                <div class="kyc-grid">
                    <?php foreach ($pending as $v): ?>
                        <div class="card-pending" id="row-<?= $v['user_id'] ?>">
                            <div class="biz-title-row">
                                <img class="biz-logo" src="../<?= htmlspecialchars($v['logo'] ?: 'img/logo black transparent small.png') ?>" alt="">
                                <div>
                                    <h3 class="biz-name"><?= htmlspecialchars($v['biz_name']) ?></h3>
                                    <div class="biz-meta"><?= htmlspecialchars($v['category']) ?> • <?= htmlspecialchars($v['location']) ?></div>
                                </div>
                            </div>

                            <div class="info-grid">
                                <div><span class="info-label">Contact Name:</span> <?= htmlspecialchars($v['user_name']) ?></div>
                                <div><span class="info-label">Phone:</span> <?= htmlspecialchars($v['user_phone'] ?: 'N/A') ?></div>
                                <div><span class="info-label">Email:</span> <?= htmlspecialchars($v['user_email'] ?: 'N/A') ?></div>
                                <div><span class="info-label">Experience:</span> <?= htmlspecialchars($v['experience']) ?> Years</div>
                                <div><span class="info-label">ID Type:</span> <?= htmlspecialchars($v['id_type'] ?: 'Not specified') ?></div>
                                <div><span class="info-label">Submitted:</span> <?= htmlspecialchars($v['submitted_at'] ?: 'Not yet') ?></div>
                                <?php if (!empty($v['didit_decision'])): ?>
                                    <div><span class="info-label">Didit Status:</span> <span style="font-weight:700; color:var(--accent);"><?= htmlspecialchars($v['didit_decision']) ?></span></div>
                                <?php endif; ?>
                            </div>
                            </div>

                            <div>
                                <div class="doc-preview-title"><i class="fa-solid fa-id-card"></i> Ghana Card & Verification Selfie</div>
                                <div class="doc-images-row">
                                    <?php if (!empty($v['id_front'])): ?>
                                        <div class="doc-thumb" onclick="viewDoc('../<?= htmlspecialchars($v['id_front']) ?>')" style="cursor:pointer;">
                                            <img src="../<?= htmlspecialchars($v['id_front']) ?>" alt="ID Front">
                                        </div>
                                    <?php else: ?>
                                        <div class="doc-thumb" style="cursor:default; opacity:0.5;">
                                            <i class="fa-solid fa-id-card" style="font-size:1.5rem; margin-bottom:4px; color:var(--gray-400);"></i>
                                            <span style="color:#E11D48; font-weight:700;">Not Uploaded</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($v['selfie'])): ?>
                                        <div class="doc-thumb" onclick="viewDoc('../<?= htmlspecialchars($v['selfie']) ?>')" style="cursor:pointer;">
                                            <img src="../<?= htmlspecialchars($v['selfie']) ?>" alt="Selfie">
                                        </div>
                                    <?php else: ?>
                                        <div class="doc-thumb" style="cursor:default; opacity:0.5;">
                                            <i class="fa-solid fa-camera" style="font-size:1.5rem; margin-bottom:4px; color:var(--gray-400);"></i>
                                            <span style="color:#E11D48; font-weight:700;">Not Uploaded</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php $has_docs = !empty($v['id_front']) && !empty($v['selfie']); ?>
                            <div class="action-buttons" style="display:grid; grid-template-columns: 1fr 1fr 1.2fr; gap:6px;">
                                <button class="btn btn-outline btn-sm" style="padding:10px; font-weight:700;" onclick='viewKYCDetails(<?= json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Full KYC Payload"><i class="fa-solid fa-eye"></i> Details</button>
                                <button class="btn btn-outline btn-sm" style="padding:10px; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="reviewAction(<?= $v['user_id'] ?>, 'reject')">Reject</button>
                                <?php if ($has_docs): ?>
                                    <button class="btn btn-primary btn-sm" style="padding:10px;" onclick="reviewAction(<?= $v['user_id'] ?>, 'approve')">Approve</button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm" style="padding:10px; opacity:0.4; cursor:not-allowed;" disabled title="Cannot approve — documents not uploaded">Approve</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Lightbox Modal -->
    <div id="lightbox" class="lightbox-modal" onclick="closeDocModal()">
        <div class="lightbox-content" onclick="event.stopPropagation()">
            <button class="lightbox-close" onclick="closeDocModal()"><i class="fa-solid fa-xmark"></i></button>
            <img id="lightbox-img" src="" alt="Document Preview">
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function toggleSidebar(open) {
            const sidebar = document.querySelector('.admin-sidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
        }

        function viewDoc(url) {
            const lightbox = document.getElementById('lightbox');
            const img = document.getElementById('lightbox-img');
            if (lightbox && img) {
                img.src = url;
                lightbox.style.display = 'flex';
            }
        }

        function closeDocModal() {
            const lightbox = document.getElementById('lightbox');
            if (lightbox) {
                lightbox.style.display = 'none';
            }
        }

        function reviewAction(userId, action) {
            if (!confirm(`Are you sure you want to ${action} this vendor's KYC request?`)) return;

            fetch('kyc.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, action: action })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('row-' + userId);
                    if (row) {
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            // If no more cards left, reload to show empty state
                            if (document.querySelectorAll('.card-pending').length === 0) {
                                window.location.reload();
                            }
                        }, 300);
                    }
                } else {
                    alert('Action failed: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Error during KYC review:', err);
                alert('An error occurred during communication with the server.');
            });
        }

        function viewKYCDetails(v) {
            const content = document.getElementById('kycDetailsContent');
            if (!content) return;

            content.innerHTML = `
                <div style="text-align:center; margin-bottom:16px;">
                    <img src="../${v.logo || 'img/logo black transparent small.png'}" style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid var(--primary); margin-bottom:8px;">
                    <h4 style="margin:0; font-size:1.1rem; font-weight:800; color:var(--primary);">${v.biz_name}</h4>
                    <div style="font-size:0.75rem; color:var(--gray-600); margin-top:2px;">Category: ${v.category} | Location: ${v.location || 'Ghana'}</div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.8rem; background:var(--gray-50); padding:14px; border-radius:12px; border:1px solid #E5E7EB; margin-bottom:16px;">
                    <div><strong>User / Contact Name:</strong> ${v.user_name || 'N/A'}</div>
                    <div><strong>User ID:</strong> #${v.user_id}</div>
                    <div><strong>Email:</strong> ${v.user_email || 'N/A'}</div>
                    <div><strong>Phone:</strong> ${v.user_phone || 'N/A'}</div>
                    <div><strong>Years Experience:</strong> ${v.experience || 0} Years</div>
                    <div><strong>ID Document Type:</strong> ${v.id_type || 'Ghana Card'}</div>
                    <div style="grid-column: span 2;"><strong>Submission Date:</strong> ${v.submitted_at || 'N/A'}</div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button class="btn btn-primary" onclick="closeKYCDetailsModal()" style="flex:1; font-weight:700;">Close Specification</button>
                </div>
            `;

            document.getElementById('kycDetailsModal').style.display = 'flex';
        }

        function closeKYCDetailsModal() {
            document.getElementById('kycDetailsModal').style.display = 'none';
        }
    </script>

    <!-- KYC Details Modal -->
    <div id="kycDetailsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:540px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E5E7EB; padding-bottom:12px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">
                    <i class="fa-solid fa-shield-halved" style="color:var(--accent);"></i> KYC Identity Review Payload
                </h3>
                <button onclick="closeKYCDetailsModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--gray-500);">&times;</button>
            </div>
            <div id="kycDetailsContent"></div>
        </div>
    </div>
</body>
</html>
