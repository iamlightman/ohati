<?php
// jobs_api.php - Event Jobs Marketplace Backend API Handler & Multi-Channel Multi-DB Engine
date_default_timezone_set('Africa/Accra');

if (file_exists(__DIR__ . '/sms_helper.php')) { require_once __DIR__ . '/sms_helper.php'; }
if (file_exists(__DIR__ . '/mail_helper.php')) { require_once __DIR__ . '/mail_helper.php'; }
if (file_exists(__DIR__ . '/storage_helper.php')) { require_once __DIR__ . '/storage_helper.php'; }

/**
 * Dispatch Multi-Channel Notifications (In-App Bell + Non-Blocking Asynchronous Queue)
 */
function send_job_multichannel_notification($pdo_main, $recipient_user_id, $type, $job_id, $app_id, $title, $message) {
    global $pdo_comms;
    $db_comms = $pdo_comms ?: $pdo_main;

    if (!$recipient_user_id) return;

    // 1. In-App Notification (Database 3: ohaticom_3 & Database 1: ohaticom_1 for bell UI)
    try {
        $n_stmt = $db_comms->prepare("INSERT INTO job_notifications (user_id, type, job_id, application_id, title, message) VALUES (?, ?, ?, ?, ?, ?)");
        $n_stmt->execute([$recipient_user_id, $type, $job_id, $app_id, $title, $message]);
    } catch (Exception $e) {}

    try {
        $n_main = $pdo_main->prepare("INSERT INTO notifications (user_id, type, title, body, icon) VALUES (?, ?, ?, ?, 'bell')");
        $n_main->execute([$recipient_user_id, $type, $title, $message]);
    } catch (Exception $e) {}

    // 2. Queue Email & SMS Notifications Asynchronously into notification_queue (~1ms DB insert, 0 network latency)
    try {
        $u_stmt = $pdo_main->prepare("SELECT email, phone, name, COALESCE(pref_sms, 1) as pref_sms, COALESCE(pref_email, 1) as pref_email FROM users WHERE id = ?");
        $u_stmt->execute([$recipient_user_id]);
        $user = $u_stmt->fetch();

        if ($user) {
            $phone = (($user['pref_sms'] ?? 1) == 1) ? ($user['phone'] ?? '') : '';
            $email = (($user['pref_email'] ?? 1) == 1) ? ($user['email'] ?? '') : '';
            
            $email_html = "
                <div style='font-family: Arial, sans-serif; padding: 24px; color: #1B2B4B; background: #F8FAFC; border-radius: 12px;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <h2 style='color: #F2A735; margin: 0;'>Ohati Event Jobs</h2>
                        <span style='font-size: 0.85rem; color: #64748B;'>Ghana's Premier Event Marketplace</span>
                    </div>
                    <div style='background: #ffffff; padding: 20px; border-radius: 10px; border: 1px solid #E2E8F0;'>
                        <h3 style='color: #1B2B4B; margin-top: 0;'>" . htmlspecialchars($title) . "</h3>
                        <p>Hi " . htmlspecialchars($user['name'] ?: 'there') . ",</p>
                        <p style='font-size: 0.95rem; line-height: 1.5; color: #334155;'>" . htmlspecialchars($message) . "</p>
                    </div>
                    <div style='text-align: center; margin-top: 20px; font-size: 0.8rem; color: #94A3B8;'>
                        Log in to your Ohati dashboard to manage your jobs and proposals.
                    </div>
                </div>
            ";

            if (function_exists('queue_dual_notification')) {
                queue_dual_notification($phone, $email, $title, $message, "Ohati Event Jobs: $title", $email_html);
            }
        }
    } catch (Exception $e) {}
}

function handle_job_action($action, $pdo) {
    global $pdo_jobs, $pdo_comms, $pdo_logs, $pdo_payments;
    $db_main = $pdo;                     // ohaticom_1 (Users, Vendors, Bookings, Settings)
    $db_jobs = $pdo_jobs ?: $pdo;        // ohaticom_2 (Jobs, Categories, Applications, Shortlists)
    $db_comms = $pdo_comms ?: $pdo;      // ohaticom_3 (Notifications, Queues)
    $db_logs = $pdo_logs ?: $pdo;        // ohaticom_5 (Reports, Analytics, Job Views)

    $raw_json = json_decode(file_get_contents('php://input'), true);
    $data = is_array($raw_json) ? $raw_json : $_POST;

    // Helper: Current logged-in user
    $user_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? ($GLOBALS['token_uid'] ?? 0) ?: ($data['user_id'] ?? 0);
    $vendor_id = $_SESSION['vendor_id'] ?? $_SESSION['vendor']['id'] ?? $data['vendor_id'] ?? 0;

    switch ($action) {

        // ── CATEGORIES (Database 2) ─────────────────────────────────────
        case 'job_get_categories':
            $stmt = $db_jobs->query("SELECT * FROM job_categories WHERE is_active = 1 ORDER BY name ASC");
            echo json_encode(['success' => true, 'categories' => $stmt->fetchAll()]);
            break;

        // ── CREATE / SAVE DRAFT JOB (Database 2) ────────────────────────
        case 'job_post_create':
            if (!$user_id) {
                echo json_encode(['error' => 'Authentication required. Please sign in to post a job.']);
                return;
            }

            $title = trim($data['title'] ?? '');
            $category = trim($data['category'] ?? '');
            $subcategory = trim($data['subcategory'] ?? '');
            $description = trim($data['description'] ?? '');
            $skills = trim($data['required_skills'] ?? '');
            $budget = floatval($data['budget'] ?? 0);
            $negotiable = intval($data['negotiable'] ?? 1);
            $location = trim($data['location'] ?? '');
            $event_type = trim($data['event_type'] ?? 'physical');
            $event_date = trim($data['event_date'] ?? '');
            $deadline = trim($data['deadline'] ?? '');
            $num_vendors = intval($data['num_vendors'] ?? 1);
            $is_urgent = intval($data['is_urgent'] ?? 0);
            $visibility = trim($data['visibility'] ?? 'public');
            $status = trim($data['status'] ?? 'open');

            if (empty($title) || empty($category) || empty($description)) {
                echo json_encode(['error' => 'Title, Category, and Description are required.']);
                return;
            }

            try {
                // Fetch user info from Database 1 (ohaticom_1)
                $u_stmt = $db_main->prepare("SELECT name, avatar FROM users WHERE id = ?");
                $u_stmt->execute([$user_id]);
                $u_info = $u_stmt->fetch() ?: ['name' => 'Host', 'avatar' => ''];

                // Insert into Database 2 (ohaticom_2)
                $stmt = $db_jobs->prepare("INSERT INTO jobs (user_id, user_name, user_avatar, title, category, subcategory, description, required_skills, budget, negotiable, location, event_type, event_date, deadline, num_vendors, is_urgent, visibility, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id, $u_info['name'], $u_info['avatar'], $title, $category, $subcategory,
                    $description, $skills, $budget, $negotiable, $location, $event_type,
                    $event_date, $deadline, $num_vendors, $is_urgent, $visibility, $status
                ]);
                $job_id = $db_jobs->lastInsertId();

                // Handle Attachments (Database 2)
                if (!empty($data['attachments']) && is_array($data['attachments'])) {
                    $upload_dir = __DIR__ . '/uploads/job_attachments/';
                    if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
                    
                    $att_stmt = $db_jobs->prepare("INSERT INTO job_attachments (job_id, file_path, file_name, file_type) VALUES (?, ?, ?, ?)");
                    foreach ($data['attachments'] as $att) {
                        if (is_array($att) && !empty($att['data'])) {
                            $file_data = base64_decode(preg_replace('#^data:\w+/\w+;base64,#i', '', $att['data']));
                            $file_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $att['name'] ?? 'file');
                            $save_name = time() . '_' . uniqid() . '_' . $file_name;
                            file_put_contents($upload_dir . $save_name, $file_data);
                            $rel_path = 'uploads/job_attachments/' . $save_name;
                            $type = $att['type'] ?? 'image';
                            $att_stmt->execute([$job_id, $rel_path, $file_name, $type]);
                        }
                    }
                }

                // Multi-channel notification to Host & Matching Vendors
                if ($status === 'open') {
                    send_job_multichannel_notification(
                        $db_main, $user_id, 'job_posted', $job_id, 0,
                        'Job Posted Successfully! 🎉',
                        "Your job listing '$title' is now live on the Ohati Marketplace."
                    );

                    try {
                        $v_stm = $db_main->prepare("SELECT user_id FROM vendors WHERE category = ? AND user_id > 0");
                        $v_stm->execute([$category]);
                        $v_users = $v_stm->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($v_users as $v_uid) {
                            send_job_multichannel_notification(
                                $db_main, $v_uid, 'new_job_category', $job_id, 0,
                                'New Job in Your Category! 💼',
                                "A new job '$title' ($category) matching your profile was just posted!"
                            );
                        }
                    } catch (Exception $e) {}
                }

                echo json_encode(['success' => true, 'job_id' => $job_id, 'message' => ($status === 'draft' ? 'Job saved as draft.' : 'Job posted successfully!')]);
            } catch (Throwable $e) {
                echo json_encode(['error' => 'Job creation failed: ' . $e->getMessage()]);
            }
            break;

        // ── LIST / SEARCH JOBS (Database 2) ─────────────────────────────
        case 'job_get_list':
            $q = trim($data['q'] ?? '');
            $category = trim($data['category'] ?? '');
            $location = trim($data['location'] ?? '');
            $event_type = trim($data['event_type'] ?? '');
            $urgent = isset($data['urgent']) ? intval($data['urgent']) : -1;
            $min_budget = floatval($data['min_budget'] ?? 0);
            $max_budget = floatval($data['max_budget'] ?? 0);
            $sort = trim($data['sort'] ?? 'newest');
            $page = max(1, intval($data['page'] ?? 1));
            $limit = max(1, min(50, intval($data['limit'] ?? 12)));
            $offset = ($page - 1) * $limit;

            $where = ["status = 'open'", "visibility = 'public'"];
            $params = [];

            if ($q !== '') {
                $where[] = "(title LIKE ? OR description LIKE ? OR required_skills LIKE ? OR category LIKE ?)";
                $wild = "%$q%";
                $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild;
            }
            if ($category !== '' && $category !== 'All') {
                $where[] = "category = ?";
                $params[] = $category;
            }
            if ($location !== '') {
                $where[] = "location LIKE ?";
                $params[] = "%$location%";
            }
            if ($event_type !== '' && $event_type !== 'all') {
                $where[] = "event_type = ?";
                $params[] = $event_type;
            }
            if ($urgent !== -1) {
                $where[] = "is_urgent = ?";
                $params[] = $urgent;
            }
            if ($min_budget > 0) {
                $where[] = "budget >= ?";
                $params[] = $min_budget;
            }
            if ($max_budget > 0) {
                $where[] = "budget <= ?";
                $params[] = $max_budget;
            }

            $where_sql = implode(' AND ', $where);

            $orderBy = 'created_at DESC';
            if ($sort === 'oldest') $orderBy = 'created_at ASC';
            elseif ($sort === 'budget_high') $orderBy = 'budget DESC';
            elseif ($sort === 'budget_low') $orderBy = 'budget ASC';
            elseif ($sort === 'urgent') $orderBy = 'is_urgent DESC, created_at DESC';

            // Count
            $count_stmt = $db_jobs->prepare("SELECT COUNT(*) FROM jobs WHERE $where_sql");
            $count_stmt->execute($params);
            $total = $count_stmt->fetchColumn();

            // Results
            $stmt = $db_jobs->prepare("SELECT * FROM jobs WHERE $where_sql ORDER BY $orderBy LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $jobs = $stmt->fetchAll();

            // Check if saved by current vendor/user
            if ($user_id > 0 && !empty($jobs)) {
                $job_ids = array_column($jobs, 'id');
                $in_clause = implode(',', array_map('intval', $job_ids));
                $saved_stmt = $db_jobs->query("SELECT job_id FROM job_saved WHERE user_id = $user_id AND job_id IN ($in_clause)");
                $saved_ids = $saved_stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($jobs as &$j) {
                    $j['is_saved'] = in_array($j['id'], $saved_ids);
                }
            }

            echo json_encode([
                'success' => true,
                'jobs' => $jobs,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ]);
            break;

        // ── JOB DETAILS (Database 2) ────────────────────────────────────
        case 'job_get_details':
            $job_id = intval($data['job_id'] ?? 0);
            if (!$job_id) {
                echo json_encode(['error' => 'Job ID required']);
                return;
            }

            // Increment views in Database 2
            $db_jobs->exec("UPDATE jobs SET views_count = views_count + 1 WHERE id = $job_id");

            // Track view log in Database 5 (ohaticom_5)
            try {
                $db_logs->prepare("INSERT INTO job_views (job_id, viewer_user_id, ip_address) VALUES (?, ?, ?)")
                        ->execute([$job_id, $user_id, $_SERVER['REMOTE_ADDR'] ?? '']);
            } catch (Exception $e) {}

            $stmt = $db_jobs->prepare("SELECT * FROM jobs WHERE id = ?");
            $stmt->execute([$job_id]);
            $job = $stmt->fetch();

            if (!$job) {
                echo json_encode(['error' => 'Job not found']);
                return;
            }

            // Client Info from Database 1 (ohaticom_1)
            $u_stmt = $db_main->prepare("SELECT id, name, avatar, country, city, created_at FROM users WHERE id = ?");
            $u_stmt->execute([$job['user_id']]);
            $client = $u_stmt->fetch() ?: [];

            // Client Stats from Database 2 (ohaticom_2)
            $c_jobs = $db_jobs->query("SELECT COUNT(*) FROM jobs WHERE user_id = {$job['user_id']}")->fetchColumn();
            $client['total_jobs_posted'] = $c_jobs;

            // Attachments
            $att_stmt = $db_jobs->prepare("SELECT * FROM job_attachments WHERE job_id = ?");
            $att_stmt->execute([$job_id]);
            $attachments = $att_stmt->fetchAll();

            // Check if vendor already applied
            $has_applied = false;
            $my_application = null;
            if ($vendor_id > 0) {
                $app_stmt = $db_jobs->prepare("SELECT * FROM job_applications WHERE job_id = ? AND vendor_id = ?");
                $app_stmt->execute([$job_id, $vendor_id]);
                $my_application = $app_stmt->fetch();
                if ($my_application) $has_applied = true;
            }

            echo json_encode([
                'success' => true,
                'job' => $job,
                'client' => $client,
                'attachments' => $attachments,
                'has_applied' => $has_applied,
                'my_application' => $my_application
            ]);
            break;

        // ── SUBMIT PROPOSAL (Database 2) ────────────────────────────────
        case 'job_submit_proposal':
            if (!$user_id && !$vendor_id) {
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $job_id = intval($data['job_id'] ?? 0);
            $cover_letter = trim($data['cover_letter'] ?? '');
            $price_quote = floatval($data['price_quote'] ?? 0);
            $delivery_timeline = trim($data['delivery_timeline'] ?? '');
            $answers = trim($data['answers'] ?? '');
            $portfolio_links = trim($data['portfolio_links'] ?? '');
            $availability = trim($data['availability'] ?? '');

            if (!$job_id || empty($cover_letter) || $price_quote <= 0) {
                echo json_encode(['error' => 'Job ID, Cover Letter, and Price Quote are required.']);
                return;
            }

            // Vendor profile lookup from Database 1 (ohaticom_1)
            $v_stmt = $db_main->prepare("SELECT id, name, logo, user_id FROM vendors WHERE user_id = ? OR id = ?");
            $v_stmt->execute([$user_id, $vendor_id]);
            $vendor = $v_stmt->fetch();

            if (!$vendor) {
                echo json_encode(['error' => 'You must have a registered vendor profile to submit proposals.']);
                return;
            }

            $v_id = $vendor['id'];
            $v_user_id = $vendor['user_id'] ?: $user_id;

            // Prevent self-booking / self-applying on own posted job regardless of active role/account switch
            $j_owner_stmt = $db_jobs->prepare("SELECT user_id FROM jobs WHERE id = ?");
            $j_owner_stmt->execute([$job_id]);
            $job_owner_id = intval($j_owner_stmt->fetchColumn() ?: 0);

            $effective_user_id = intval($user_id ?: ($v_user_id ?? 0));
            if ($job_owner_id > 0 && ($job_owner_id === $effective_user_id || $job_owner_id === intval($user_id) || $job_owner_id === intval($v_user_id))) {
                echo json_encode(['error' => 'You cannot apply or submit proposals to your own posted job.']);
                return;
            }

            // Check if already applied (Database 2)
            $chk = $db_jobs->prepare("SELECT id FROM job_applications WHERE job_id = ? AND vendor_id = ?");
            $chk->execute([$job_id, $v_id]);
            if ($chk->fetch()) {
                echo json_encode(['error' => 'You have already submitted a proposal for this job.']);
                return;
            }

            $stmt = $db_jobs->prepare("INSERT INTO job_applications (job_id, vendor_id, vendor_user_id, vendor_name, vendor_avatar, cover_letter, price_quote, delivery_timeline, answers, portfolio_links, availability) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $job_id, $v_id, $v_user_id, $vendor['name'], $vendor['logo'],
                $cover_letter, $price_quote, $delivery_timeline, $answers,
                $portfolio_links, $availability
            ]);
            $app_id = $db_jobs->lastInsertId();

            // Update applications count
            $db_jobs->exec("UPDATE jobs SET applications_count = applications_count + 1 WHERE id = $job_id");

            // Attachments
            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                $upload_dir = __DIR__ . '/uploads/job_attachments/';
                if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $att_stmt = $db_jobs->prepare("INSERT INTO job_application_attachments (application_id, file_path, file_name, file_type) VALUES (?, ?, ?, ?)");
                foreach ($data['attachments'] as $att) {
                    if (is_array($att) && !empty($att['data'])) {
                        $file_data = base64_decode(preg_replace('#^data:\w+/\w+;base64,#i', '', $att['data']));
                        $file_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $att['name'] ?? 'file');
                        $save_name = time() . '_app_' . uniqid() . '_' . $file_name;
                        file_put_contents($upload_dir . $save_name, $file_data);
                        $att_stmt->execute([$app_id, 'uploads/job_attachments/' . $save_name, $file_name, $att['type'] ?? 'image']);
                    }
                }
            }

            // Multi-channel notifications
            $job_owner = $db_jobs->query("SELECT user_id, title FROM jobs WHERE id = $job_id")->fetch();
            if ($job_owner) {
                send_job_multichannel_notification(
                    $db_main, $job_owner['user_id'], 'proposal_received', $job_id, $app_id,
                    'New Proposal Received! 📩',
                    "{$vendor['name']} submitted a proposal quote of GHS " . number_format($price_quote, 2) . " for '{$job_owner['title']}'."
                );

                send_job_multichannel_notification(
                    $db_main, $v_user_id, 'proposal_submitted', $job_id, $app_id,
                    'Proposal Submitted! 🚀',
                    "Your proposal of GHS " . number_format($price_quote, 2) . " for '{$job_owner['title']}' was sent successfully."
                );
            }

            echo json_encode(['success' => true, 'application_id' => $app_id, 'message' => 'Proposal submitted successfully!']);
            break;

        // ── PROPOSALS INBOX (Host View) ────────────────────────────────
        case 'job_get_proposals':
            $job_id = intval($data['job_id'] ?? 0);
            if (!$job_id) {
                echo json_encode(['error' => 'Job ID required']);
                return;
            }

            $stmt = $db_jobs->prepare("SELECT ja.* FROM job_applications ja WHERE ja.job_id = ? ORDER BY ja.is_featured DESC, ja.created_at DESC");
            $stmt->execute([$job_id]);
            $proposals = $stmt->fetchAll();

            // Enrich with vendor details from Database 1 (ohaticom_1)
            foreach ($proposals as &$p) {
                $v_info = $db_main->query("SELECT rating, reviews_count, verified, verification_badge, location as vendor_location FROM vendors WHERE id = {$p['vendor_id']}")->fetch() ?: [];
                $p = array_merge($p, $v_info);
            }

            echo json_encode(['success' => true, 'proposals' => $proposals]);
            break;

        // ── SHORTLIST PROPOSAL ──────────────────────────────────────────
        case 'job_shortlist_proposal':
            $app_id = intval($data['application_id'] ?? 0);
            $app = $db_jobs->query("SELECT * FROM job_applications WHERE id = $app_id")->fetch();
            if (!$app) {
                echo json_encode(['error' => 'Proposal not found']);
                return;
            }

            $db_jobs->exec("UPDATE job_applications SET status = 'shortlisted' WHERE id = $app_id");
            $db_jobs->exec("UPDATE jobs SET shortlisted_count = shortlisted_count + 1 WHERE id = {$app['job_id']}");

            send_job_multichannel_notification(
                $db_main, $app['vendor_user_id'], 'shortlisted', $app['job_id'], $app_id,
                'You Have Been Shortlisted! ⭐',
                "Your proposal for job #{$app['job_id']} was shortlisted by the client!"
            );

            echo json_encode(['success' => true, 'message' => 'Vendor proposal shortlisted!']);
            break;

        // ── REJECT PROPOSAL ─────────────────────────────────────────────
        case 'job_reject_proposal':
            $app_id = intval($data['application_id'] ?? 0);
            $app = $db_jobs->query("SELECT * FROM job_applications WHERE id = $app_id")->fetch();
            if (!$app) {
                echo json_encode(['error' => 'Proposal not found']);
                return;
            }

            $db_jobs->exec("UPDATE job_applications SET status = 'rejected' WHERE id = $app_id");
            
            send_job_multichannel_notification(
                $db_main, $app['vendor_user_id'], 'proposal_rejected', $app['job_id'], $app_id,
                'Proposal Status Update',
                "Your proposal for job #{$app['job_id']} was not selected by the client."
            );

            echo json_encode(['success' => true, 'message' => 'Proposal rejected']);
            break;

        // ── HIRE VENDOR ─────────────────────────────────────────────────
        case 'job_hire_vendor':
            $app_id = intval($data['application_id'] ?? 0);
            $app = $db_jobs->query("SELECT * FROM job_applications WHERE id = $app_id")->fetch();
            if (!$app) {
                echo json_encode(['error' => 'Proposal not found']);
                return;
            }

            $job = $db_jobs->query("SELECT * FROM jobs WHERE id = {$app['job_id']}")->fetch();
            if (!$job) {
                echo json_encode(['error' => 'Job not found']);
                return;
            }

            // Update proposal status (Database 2)
            $db_jobs->exec("UPDATE job_applications SET status = 'hired' WHERE id = $app_id");

            // Create Hire Contract record (Database 2)
            $h_stmt = $db_jobs->prepare("INSERT INTO job_hires (job_id, application_id, user_id, vendor_id, agreed_price) VALUES (?, ?, ?, ?, ?)");
            $h_stmt->execute([$job['id'], $app_id, $job['user_id'], $app['vendor_id'], $app['price_quote']]);

            // Update Job status (Database 2)
            $new_hired_count = $job['hired_count'] + 1;
            $new_status = ($new_hired_count >= $job['num_vendors']) ? 'filled' : 'partially_filled';
            $db_jobs->exec("UPDATE jobs SET hired_count = $new_hired_count, status = '$new_status' WHERE id = {$job['id']}");

            // Multi-channel Notifications
            send_job_multichannel_notification(
                $db_main, $app['vendor_user_id'], 'hired', $job['id'], $app_id,
                'You Have Been Hired! 🎉',
                "Congratulations! You were hired for '{$job['title']}' at GHS " . number_format($app['price_quote'], 2) . "."
            );

            send_job_multichannel_notification(
                $db_main, $job['user_id'], 'hire_confirmed', $job['id'], $app_id,
                'Vendor Hired Successfully! 🤝',
                "You have hired {$app['vendor_name']} for '{$job['title']}'."
            );

            // Automatically ensure a chat session exists in Database 1 / Database 3
            try {
                $c_chk = $db_main->prepare("SELECT id FROM chat_rooms WHERE (user_id = ? AND vendor_id = ?) OR (user_id = ? AND vendor_id = ?)");
                $c_chk->execute([$job['user_id'], $app['vendor_id'], $app['vendor_user_id'], $app['vendor_id']]);
                $room = $c_chk->fetch();
                if (!$room) {
                    $c_ins = $db_main->prepare("INSERT INTO chat_rooms (user_id, vendor_id, last_message, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                    $c_ins->execute([$job['user_id'], $app['vendor_id'], "Job Hire: {$job['title']}"]);
                    $room_id = $db_main->lastInsertId();
                } else {
                    $room_id = $room['id'];
                }
                
                $m_ins = $db_main->prepare("INSERT INTO chat_messages (room_id, sender_type, sender_id, message, created_at) VALUES (?, 'system', 0, ?, CURRENT_TIMESTAMP)");
                $m_ins->execute([$room_id, "🎉 Vendor hired for job: {$job['title']} (Agreed Quote: GHS " . number_format($app['price_quote'], 2) . ")"]);
            } catch (Exception $e) {}

            echo json_encode([
                'success' => true,
                'job_status' => $new_status,
                'message' => 'Vendor successfully hired!'
            ]);
            break;

        // ── USER DASHBOARD ──────────────────────────────────────────────
        case 'job_get_user_dashboard':
            if (!$user_id) {
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $stmt = $db_jobs->prepare("SELECT * FROM jobs WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $all_jobs = $stmt->fetchAll();

            $posted = []; $active = []; $drafts = []; $closed = []; $hired = [];
            $total_views = 0; $total_apps = 0; $total_hired = 0;

            foreach ($all_jobs as $j) {
                $total_views += $j['views_count'];
                $total_apps += $j['applications_count'];
                $total_hired += $j['hired_count'];

                if ($j['status'] === 'draft') $drafts[] = $j;
                elseif ($j['status'] === 'open' || $j['status'] === 'in_review') $active[] = $j;
                elseif ($j['status'] === 'filled' || $j['status'] === 'completed') { $closed[] = $j; $hired[] = $j; }
                else $posted[] = $j;
            }

            $hire_rate = count($all_jobs) > 0 ? round(($total_hired / count($all_jobs)) * 100, 1) : 0;

            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_posted' => count($all_jobs),
                    'active_count' => count($active),
                    'total_views' => $total_views,
                    'total_applications' => $total_apps,
                    'total_hired' => $total_hired,
                    'hire_rate' => $hire_rate
                ],
                'jobs' => [
                    'all' => $all_jobs,
                    'active' => $active,
                    'drafts' => $drafts,
                    'closed' => $closed,
                    'hired' => $hired
                ]
            ]);
            break;

        // ── VENDOR DASHBOARD ────────────────────────────────────────────
        case 'job_get_vendor_dashboard':
            if (!$vendor_id && !$user_id) {
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $v_row = $db_main->query("SELECT id FROM vendors WHERE user_id = $user_id OR id = $vendor_id")->fetch();
            $v_id = $v_row['id'] ?? $vendor_id;

            $stmt = $db_jobs->prepare("SELECT ja.*, j.title as job_title, j.category, j.budget, j.location, j.status as job_status, j.event_date FROM job_applications ja LEFT JOIN jobs j ON ja.job_id = j.id WHERE ja.vendor_id = ? ORDER BY ja.created_at DESC");
            $stmt->execute([$v_id]);
            $applications = $stmt->fetchAll();

            $applied = []; $shortlisted = []; $hired = []; $rejected = [];
            foreach ($applications as $a) {
                if ($a['status'] === 'submitted' || $a['status'] === 'viewed') $applied[] = $a;
                elseif ($a['status'] === 'shortlisted') $shortlisted[] = $a;
                elseif ($a['status'] === 'hired') $hired[] = $a;
                elseif ($a['status'] === 'rejected') $rejected[] = $a;
            }

            $total_sent = count($applications);
            $total_accepted = count($hired) + count($shortlisted);
            $acceptance_rate = $total_sent > 0 ? round(($total_accepted / $total_sent) * 100, 1) : 0;

            echo json_encode([
                'success' => true,
                'stats' => [
                    'applications_sent' => $total_sent,
                    'shortlisted_count' => count($shortlisted),
                    'hired_count' => count($hired),
                    'rejected_count' => count($rejected),
                    'acceptance_rate' => $acceptance_rate
                ],
                'applications' => [
                    'all' => $applications,
                    'applied' => $applied,
                    'shortlisted' => $shortlisted,
                    'hired' => $hired,
                    'rejected' => $rejected
                ]
            ]);
            break;

        // ── SAVE / BOOKMARK JOB ────────────────────────────────────────
        case 'job_toggle_save':
            if (!$user_id) {
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $job_id = intval($data['job_id'] ?? 0);
            if (!$job_id) {
                echo json_encode(['error' => 'Job ID required']);
                return;
            }

            $chk = $db_jobs->prepare("SELECT id FROM job_saved WHERE user_id = ? AND job_id = ?");
            $chk->execute([$user_id, $job_id]);
            if ($chk->fetch()) {
                $db_jobs->exec("DELETE FROM job_saved WHERE user_id = $user_id AND job_id = $job_id");
                $db_jobs->exec("UPDATE jobs SET saved_count = GREATEST(0, saved_count - 1) WHERE id = $job_id");
                echo json_encode(['success' => true, 'is_saved' => false, 'message' => 'Job removed from saved list.']);
            } else {
                $db_jobs->exec("INSERT INTO job_saved (user_id, job_id) VALUES ($user_id, $job_id)");
                $db_jobs->exec("UPDATE jobs SET saved_count = saved_count + 1 WHERE id = $job_id");
                echo json_encode(['success' => true, 'is_saved' => true, 'message' => 'Job saved!']);
            }
            break;

        // ── REPORT JOB OR PROPOSAL (Database 5) ─────────────────────────
        case 'job_report':
            if (!$user_id) {
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $target_type = trim($data['target_type'] ?? 'job');
            $target_id = intval($data['target_id'] ?? 0);
            $reason = trim($data['reason'] ?? '');
            $details = trim($data['details'] ?? '');

            if (!$target_id || empty($reason)) {
                echo json_encode(['error' => 'Target ID and Reason are required.']);
                return;
            }

            $stmt = $db_logs->prepare("INSERT INTO job_reports (reporter_user_id, target_type, target_id, reason, details) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $target_type, $target_id, $reason, $details]);

            echo json_encode(['success' => true, 'message' => 'Report submitted to administrators for review.']);
            break;

        // ── NOTIFICATIONS & PREFERENCES (Database 3) ────────────────────
        case 'job_get_notifications':
            if (!$user_id) {
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $stmt = $db_comms->prepare("SELECT * FROM job_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
            $stmt->execute([$user_id]);
            $notifs = $stmt->fetchAll();

            $unread_count = 0;
            foreach ($notifs as $n) { if ($n['is_read'] == 0) $unread_count++; }

            echo json_encode(['success' => true, 'notifications' => $notifs, 'unread_count' => $unread_count]);
            break;

        case 'job_mark_notifications_read':
            if (!$user_id) {
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $notif_id = intval($data['notification_id'] ?? 0);
            if ($notif_id > 0) {
                $db_comms->exec("UPDATE job_notifications SET is_read = 1 WHERE id = $notif_id AND user_id = $user_id");
            } else {
                $db_comms->exec("UPDATE job_notifications SET is_read = 1 WHERE user_id = $user_id");
            }

            echo json_encode(['success' => true, 'message' => 'Notifications marked as read.']);
            break;

        case 'job_get_notification_preferences':
            if (!$user_id) {
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $stmt = $db_main->prepare("SELECT COALESCE(pref_inapp,1) as pref_inapp, COALESCE(pref_push,1) as pref_push, COALESCE(pref_sms,1) as pref_sms, COALESCE(pref_email,1) as pref_email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $prefs = $stmt->fetch() ?: ['pref_inapp' => 1, 'pref_push' => 1, 'pref_sms' => 1, 'pref_email' => 1];

            echo json_encode(['success' => true, 'preferences' => $prefs]);
            break;

        case 'job_update_notification_preferences':
            if (!$user_id) {
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $inapp = intval($data['pref_inapp'] ?? 1);
            $push = intval($data['pref_push'] ?? 1);
            $sms = intval($data['pref_sms'] ?? 1);
            $email = intval($data['pref_email'] ?? 1);

            $stmt = $db_main->prepare("UPDATE users SET pref_inapp = ?, pref_push = ?, pref_sms = ?, pref_email = ? WHERE id = ?");
            $stmt->execute([$inapp, $push, $sms, $email, $user_id]);

            echo json_encode(['success' => true, 'message' => 'Notification preferences updated!']);
            break;

        // ── ADMIN MODERATION ───────────────────────────────────────────
        case 'admin_job_list':
            $stmt = $db_jobs->query("SELECT * FROM jobs ORDER BY created_at DESC");
            $jobs_res = $stmt->fetchAll();
            foreach ($jobs_res as &$j) {
                $u_row = $db_main->query("SELECT email FROM users WHERE id = {$j['user_id']}")->fetch();
                $j['user_email'] = $u_row['email'] ?? '';
            }
            echo json_encode(['success' => true, 'jobs' => $jobs_res]);
            break;

        case 'admin_job_update_status':
            $job_id = intval($data['job_id'] ?? 0);
            $status = trim($data['status'] ?? 'open');
            $is_featured = isset($data['is_featured']) ? intval($data['is_featured']) : 0;

            if (!$job_id) {
                echo json_encode(['error' => 'Job ID required']);
                return;
            }

            $stmt = $db_jobs->prepare("UPDATE jobs SET status = ?, is_featured = ? WHERE id = ?");
            $stmt->execute([$status, $is_featured, $job_id]);

            echo json_encode(['success' => true, 'message' => 'Job updated by administrator.']);
            break;

        default:
            echo json_encode(['error' => "Action '$action' not recognized in jobs_api."]);
            break;
    }
}
?>
