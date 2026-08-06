<?php
// api.php - Ohati Backend API
date_default_timezone_set('Africa/Accra');
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
session_start();
require_once __DIR__ . '/db.php';

// Check Maintenance Mode
try {
    $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = 'maintenance_mode'");
    $stmt->execute();
    $maint = $stmt->fetchColumn();
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    if ($maint === '1' && !$is_admin) {
        http_response_code(503);
        echo json_encode(['error' => 'The platform is currently undergoing upgrades. Please check back shortly.']);
        exit;
    }
} catch (Exception $e) {}

require_once __DIR__ . '/payment_api.php';
require_once __DIR__ . '/sms_helper.php';

$raw_json = json_decode(file_get_contents('php://input'), true);
$raw_input = is_array($raw_json) ? $raw_json : [];
$action = $_GET['action'] ?? $_POST['action'] ?? $raw_input['action'] ?? '';
if (!isset($_SESSION['favorites'])) $_SESSION['favorites'] = [];
if (!isset($_SESSION['compare'])) $_SESSION['compare'] = [];

// Route to payment API dispatcher if action belongs to payment module
$payment_actions = [
    'initiate_paystack_payment', 'verify_paystack_payment', 'get_vendor_wallet',
    'request_withdrawal', 'release_escrow', 'raise_dispute', 'resolve_dispute',
    'admin_get_financials', 'admin_approve_withdrawal', 'admin_reject_withdrawal'
];
if (in_array($action, $payment_actions)) {
    handle_payment_action($action, $pdo);
    exit;
}

// CSRF token helper
function csrf_token() {
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf($token) {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = !empty($token) ? $token : bin2hex(random_bytes(32));
    } elseif (!empty($token)) {
        $_SESSION['csrf'] = $token;
    }
    return true;
}

// Sanitize output helper
function clean($str) { return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8'); }

function add_notification($pdo, $user_id, $title, $message) {
    $now_stamp = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO notifications (user_id, title, body, created_at) VALUES (?, ?, ?, ?)")->execute([$user_id, $title, $message, $now_stamp]);
}

function log_activity($pdo, $action, $entity_type, $entity_id, $actor_id, $actor_role, $actor_name, $amount = 0, $old_status = '', $new_status = '', $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $device = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $stmt = $pdo->prepare("INSERT INTO financial_audit_log (action, entity_type, entity_id, actor_id, actor_role, actor_name, amount, old_status, new_status, details, ip_address, device) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$action, $entity_type, $entity_id, $actor_id, $actor_role, $actor_name, $amount, $old_status, $new_status, $details, $ip, substr($device, 0, 190)]);
    } catch (Exception $e) {}
}

// Rate limit helper (simple session-based)
function rate_limit($key, $max = 5, $window = 60) {
    $now = time();
    if (!isset($_SESSION['rl'][$key])) $_SESSION['rl'][$key] = [];
    $_SESSION['rl'][$key] = array_filter($_SESSION['rl'][$key], fn($t) => $t > $now - $window);
    if (count($_SESSION['rl'][$key]) >= $max) return false;
    $_SESSION['rl'][$key][] = $now;
    return true;
}

// Idempotency / Double-submission lock helper
function check_idempotency_lock($action, $lock_seconds = 3) {
    $now = microtime(true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'anon';
    $lock_key = 'idem_' . $action . '_' . md5($ip . session_id());
    if (isset($_SESSION[$lock_key]) && ($now - $_SESSION[$lock_key]) < $lock_seconds) {
        http_response_code(429);
        echo json_encode(['error' => 'Action already in progress. Please wait a moment.']);
        exit;
    }
    $_SESSION[$lock_key] = $now;
}

// Financial audit log helper
function audit_log($pdo, $action, $entity_type, $entity_id, $amount = 0, $old_status = '', $new_status = '', $details = '') {
    $actor = $_SESSION['admin_user'] ?? $_SESSION['user'] ?? null;
    $actor_id = $actor['id'] ?? 0;
    $actor_role = $actor['role'] ?? 'system';
    $actor_name = $actor['name'] ?? 'System';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $dev = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 300);
    $stmt = $pdo->prepare("INSERT INTO financial_audit_log (action,entity_type,entity_id,actor_id,actor_role,actor_name,amount,old_status,new_status,details,ip_address,device) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$action,$entity_type,$entity_id,$actor_id,$actor_role,$actor_name,$amount,$old_status,$new_status,$details,$ip,$dev]);
}

// Paystack server-side verification
function verify_paystack_transaction($reference) {
    $secret_key = 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; // Replace with real key
    $ch = curl_init("https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $secret_key"],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['status' => false, 'message' => 'Connection error'];
    return json_decode($resp, true);
}

function is_local_env() {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $addr = $_SERVER['REMOTE_ADDR'] ?? '';
    $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    return (
        $host === 'localhost' ||
        strpos($host, 'localhost:') !== false ||
        strpos($host, '127.0.0.1') !== false ||
        strpos($host, '[::1]') !== false ||
        $addr === '127.0.0.1' ||
        $addr === '::1' ||
        stripos($doc_root, 'xampp') !== false
    );
}

function secure_save_base64_image($base64_str, $folder, $prefix = 'file') {
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64_str, $matches)) {
        throw new Exception("Invalid image format or header.");
    }
    $extension = strtolower($matches[1]);
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowed_extensions)) {
        throw new Exception("Extension not allowed: " . $extension);
    }
    $comma_pos = strpos($base64_str, ',');
    $data = base64_decode(substr($base64_str, $comma_pos + 1));
    if ($data === false) {
        throw new Exception("Invalid base64 encoding.");
    }
    $info = getimagesizefromstring($data);
    if ($info === false) {
        throw new Exception("File content is not a valid image.");
    }
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($info['mime'], $allowed_mimes)) {
        throw new Exception("Invalid MIME type: " . $info['mime']);
    }
    $dir = __DIR__ . '/uploads/' . $folder;
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    $filename = $prefix . '_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $dir . '/' . $filename;
    if (file_put_contents($filepath, $data) === false) {
        throw new Exception("Failed to write image file.");
    }
    return 'uploads/' . $folder . '/' . $filename;
}

function compressAndResizeImage($source_path, $target_path, $max_width = 1200, $max_height = 1200, $quality = 75) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    // Get image info
    $info = getimagesize($source_path);
    if (!$info) return false;
    
    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];
    
    // Calculate new dimensions
    $ratio = $width / $height;
    if ($width > $max_width || $height > $max_height) {
        if ($max_width / $max_height > $ratio) {
            $new_width = $max_height * $ratio;
            $new_height = $max_height;
        } else {
            $new_height = $max_width / $ratio;
            $new_width = $max_width;
        }
    } else {
        $new_width = $width;
        $new_height = $height;
    }
    
    // Create image from source
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $image = imagecreatefromwebp($source_path);
            } else {
                return false;
            }
            break;
        default:
            return false;
    }
    
    if (!$image) return false;
    
    // Resize image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Save image to target path
    $success = false;
    switch ($mime) {
        case 'image/jpeg':
            $success = imagejpeg($new_image, $target_path, $quality);
            break;
        case 'image/png':
            $png_quality = max(0, min(9, 9 - round(($quality * 9) / 100)));
            $success = imagepng($new_image, $target_path, $png_quality);
            break;
        case 'image/gif':
            $success = imagegif($new_image, $target_path);
            break;
        case 'image/webp':
            if (function_exists('imagewebp')) {
                $success = imagewebp($new_image, $target_path, $quality);
            } else {
                $success = imagejpeg($new_image, $target_path, $quality);
            }
            break;
    }
    
    imagedestroy($image);
    imagedestroy($new_image);
    
    return $success;
}

// Ensure vendor wallet exists
function ensure_wallet($pdo, $vendor_id, $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM vendor_wallets WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    if (!$stmt->fetchColumn()) {
        $pdo->prepare("INSERT INTO vendor_wallets (vendor_id, user_id) VALUES (?, ?)")->execute([$vendor_id, $user_id]);
    }
}

function unlock_category_milestones($pdo, $category, $user_id) {
    if (!$user_id) return;
    $chk = $pdo->prepare("SELECT COUNT(*) FROM tracker_tasks WHERE category = ? AND user_id = ?");
    $chk->execute([$category, $user_id]);
    if ($chk->fetchColumn() > 0) return;
    $today = date('Y-m-d');
    $milestones = [];
    switch ($category) {
        case 'Photography': $milestones = [['Photography team secured!','High',$today,1,'Photography team secured!'],['Schedule pre-wedding photoshoot','Medium',date('Y-m-d',strtotime('+20 days')),0,'Coordinate location and outfits.'],['Finalize photo shot-list','High',date('Y-m-d',strtotime('+40 days')),0,'Compile family members for portraits.']]; break;
        case 'Videography': $milestones = [['Videographer Confirmed','High',$today,1,'Video team secured!'],['Review music preferences','Low',date('Y-m-d',strtotime('+30 days')),0,'Send preferred tracks.']]; break;
        case 'Makeup Artists': $milestones = [['Makeup Artist Reserved','High',$today,1,'MUA secured!'],['Bridal makeup trial','Medium',date('Y-m-d',strtotime('+25 days')),0,'Run trial skin match.']]; break;
        case 'Decorators': $milestones = [['Decorator Confirmed','High',$today,1,'Decor team secured!'],['Finalize theme color boards','High',date('Y-m-d',strtotime('+15 days')),0,'Confirm fabrics, drapery, flowers.']]; break;
        case 'Caterers': $milestones = [['Caterer Confirmed','High',$today,1,'Catering team secured!'],['Confirm catering numbers','High',date('Y-m-d',strtotime('+45 days')),0,'Provide final head counts.']]; break;
        case 'Event Venues': $milestones = [['Venue Booked','High',$today,1,'Venue reservation locked!'],['Venue layout walkthrough','Medium',date('Y-m-d',strtotime('+60 days')),0,'Visit with decorator for floor designs.']]; break;
        case 'DJs': case 'Live Bands': $milestones = [['Music Playlist Consultation','Medium',date('Y-m-d',strtotime('+40 days')),0,'Submit entry, exit, dance floor songs.']]; break;
        case 'Event Planners': $milestones = [['Planner Confirmed','High',$today,1,'Planning team secured!'],['Lock master budget timeline','High',date('Y-m-d',strtotime('+10 days')),0,'Final review of milestones and expenses.']]; break;
        case 'Chilling Services': $milestones = [['Chilling Service Confirmed','High',$today,1,'Cooling logistics secured!'],['Finalize drinks chilling logs','High',date('Y-m-d',strtotime('+35 days')),0,'Submit beer, soda, wine quantities.']]; break;
        default: $milestones = [['Vendor Confirmed: '.$category,'High',$today,1,'Vendor booked for this category!']]; break;
    }
    $ins = $pdo->prepare("INSERT INTO tracker_tasks (user_id,task_name,category,priority,estimated_date,completed,notes,is_custom) VALUES (?,?,?,?,?,?,?,0)");
    foreach ($milestones as $m) { $ins->execute([$user_id,$m[0],$category,$m[1],$m[2],$m[3],$m[4]]); }
}

function generate_event_checklist($pdo, $event_type, $event_date, $user_id) {
    $pdo->prepare("DELETE FROM tracker_tasks WHERE user_id = ? AND is_custom = 0")->execute([$user_id]);
    $tasks = [
        ['Secure primary Event Venue','General','High',180,'Research locations and secure ceremony/reception spot.'],
        ['Select event theme and colors','General','Medium',180,'Decide on aesthetic direction and primary colors.'],
        ['Hire an event coordinator','General','Medium',180,'Secure a planner to streamline discussions.'],
        ['Choose and taste cake menu','General','Low',120,'Schedule tasting session with cake designers.'],
        ['Finalize guest list and count','General','High',120,'Draft initial count for caterer and decorator.'],
        ['Confirm catering menus','General','Medium',120,'Review buffet vs plated selections.'],
        ['Send invitations to guests','General','High',60,'Distribute printed or digital cards.'],
        ['Schedule outfit fittings','General','High',60,'Ensure all outfits are tailored and ready.'],
        ['Run sound and playlist meetings','General','Medium',60,'List entrance songs and party hits.'],
        ['Reconfirm all booking reservations','General','High',14,'Double-check times with all vendors.'],
        ['Pay remaining vendor balances','General','High',14,'Clear outstanding deposits.'],
        ['Draft reception seating','General','Medium',14,'Create seating chart for reception layout.'],
        ['Final vendor confirmations','General','High',1,'Ensure decorators and services are ready.'],
        ['Prepare cash tips and emergency contacts','General','Medium',1,'Delegate day-of tasks.'],
    ];
    $ins = $pdo->prepare("INSERT INTO tracker_tasks (user_id,task_name,category,priority,estimated_date,completed,notes,is_custom,cost,paid_amount) VALUES (?,?,'General',?,?,0,?,0,0,0)");
    foreach ($tasks as $t) {
        $est = date('Y-m-d', strtotime($event_date . ' - ' . $t[3] . ' days'));
        $ins->execute([$user_id,$t[0],$t[2],$est,$t[4]]);
    }
}

try {
    // ── AUTOMATED EXPIRE AD CAMPAIGNS & SYNC FEATURED VENDORS ──
    $now_date = date('Y-m-d H:i:s');
    $expired_stmt = $pdo->prepare("SELECT DISTINCT vendor_id FROM advertisements WHERE status = 'active' AND end_date < ?");
    $expired_stmt->execute([$now_date]);
    $expired_vendors = $expired_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($expired_vendors)) {
        $pdo->prepare("UPDATE advertisements SET status = 'expired' WHERE status = 'active' AND end_date < ?")->execute([$now_date]);
        foreach ($expired_vendors as $vendor_id) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM advertisements WHERE vendor_id = ? AND status = 'active'");
            $check->execute([$vendor_id]);
            if ($check->fetchColumn() == 0) {
                $pdo->prepare("UPDATE vendors SET featured = 0, feature_expires_at = '' WHERE id = ?")->execute([$vendor_id]);
                $user_stmt = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
                $user_stmt->execute([$vendor_id]);
                $uid = $user_stmt->fetchColumn();
                if ($uid) {
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Campaign Expired', 'Your ad campaign has expired. Renew it to continue enjoying featured status.', 'rectangle-ad')");
                    $notif->execute([$uid]);
                }
            }
        }
    }

// ── CSRF ENFORCEMENT ────────────────────────────────────────────────────
// Enforce CSRF on all state-changing POST actions except pre-auth flows
$csrf_exempt_actions = ['register', 'register_vendor', 'update_vendor', 'update_profile', 'register_device_token', 'login', 'send_otp', 'verify_otp', 'forgot_password', 'reset_password', 'run_diagnostics', 'vendors', 'vendor_detail', 'search', 'categories', 'faq', 'get_tracker_tasks', 'user_bookings', 'chat_inbox', 'chat_history', 'notifications', 'toggle_compare', 'get_compare', 'toggle_favorite', 'get_favorites', 'me', 'get_reviews', 'get_advertisements', 'advertisements', 'get_vendor_packages', 'record_ad_click', 'initiate_call', 'check_incoming_call', 'get_call_details', 'answer_call', 'reject_call', 'end_call', 'send_ice_candidate'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $csrf_exempt_actions)) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $csrf = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $raw_input['csrf_token'] ?? '';
    if (!verify_csrf($csrf)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF verification failed. Please refresh the page and try again.']);
        exit;
    }
}

switch ($action) {

// ── AUTH ────────────────────────────────────────────────────────────────
case 'register':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    check_idempotency_lock('register', 3);
    if (!rate_limit('register', 3, 300)) { http_response_code(429); echo json_encode(['error'=>'Too many attempts. Try again later.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $name = clean($input['name'] ?? '');
    $email = isset($input['email']) ? filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL) : '';
    $phone = clean($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    $role = in_array($input['role'] ?? '', ['customer','vendor']) ? $input['role'] : 'customer';
    if (empty($name) || (empty($email) && empty($phone)) || strlen($password) < 8) {
        http_response_code(400); echo json_encode(['error'=>'Name, email or phone, and password (8+ chars) are required.']); exit;
    }
    // Check duplicates
    if ($email) { $dup = $pdo->prepare("SELECT id FROM users WHERE email = ?"); $dup->execute([$email]); if ($dup->fetch()) { http_response_code(409); echo json_encode(['error'=>'Email already registered.']); exit; } }
    if ($phone) { $dup = $pdo->prepare("SELECT id FROM users WHERE phone = ?"); $dup->execute([$phone]); if ($dup->fetch()) { http_response_code(409); echo json_encode(['error'=>'Phone already registered.']); exit; } }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $my_ref_code = 'OHATI-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    
    // Check pending referral link code
    $used_ref = clean($input['ref'] ?? $_GET['ref'] ?? $_SESSION['pending_ref'] ?? '');
    $referrer_id = 0;
    if (!empty($used_ref)) {
        $r_chk = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? OR referral_code = ?");
        $r_chk->execute([$used_ref, strtoupper($used_ref)]);
        $referrer_id = intval($r_chk->fetchColumn() ?: 0);
    }

    $stmt = $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,role,email_verified,referral_code,referred_by) VALUES (?,?,?,?,?,0,?,?)");
    $stmt->execute([$name, $email ?: null, $phone ?: null, $hash, $role, $my_ref_code, $referrer_id]);
    $uid = $pdo->lastInsertId();

    // Reward referrer if valid
    if ($referrer_id > 0 && $referrer_id !== intval($uid)) {
        try {
            $rew_stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = 'referral_reward_amount'");
            $rew_stmt->execute();
            $reward_val = floatval($rew_stmt->fetchColumn() ?: 10.0);

            $pdo->prepare("INSERT INTO referrals (referrer_id, referred_id, referral_code, reward_amount, status, payout_status) VALUES (?, ?, ?, ?, 'completed', 'pending')")->execute([$referrer_id, $uid, $used_ref, $reward_val]);
            $pdo->prepare("UPDATE users SET referral_balance = referral_balance + ? WHERE id = ?")->execute([$reward_val, $referrer_id]);

            // Add in-app notification to referrer
            $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Referral Bonus Earned! 🎉', ?, 'gift')")
                ->execute([$referrer_id, "Great news! $name joined Ohati using your referral link. You earned GH₵ " . number_format($reward_val, 2) . "!"]);

            // Dual Email + SMS Notification to referrer
            try {
                $ref_user_stmt = $pdo->prepare("SELECT email, phone, name FROM users WHERE id = ?");
                $ref_user_stmt->execute([$referrer_id]);
                $ref_user = $ref_user_stmt->fetch();
                if ($ref_user) {
                    send_dual_notification(
                        $ref_user['phone'] ?? '',
                        $ref_user['email'] ?? '',
                        "Referral Bonus Earned! 🎉",
                        "Hello " . ($ref_user['name'] ?? 'User') . ", great news! $name joined Ohati using your referral link. You have earned GH₵ " . number_format($reward_val, 2) . "!"
                    );
                }
            } catch (Exception $eRefNotif) {}
        } catch (Exception $e) {}
    }

    // Also dispatch Admin Email Notification to ohatiwebsite@gmail.com
    try {
        send_admin_activity_notification(
            "New " . ucfirst($role) . " Registration (" . htmlspecialchars($name) . ")",
            "<p>A new <strong>" . htmlspecialchars($role) . "</strong> account has registered on Ohati Ghana.</p><p><strong>Name:</strong> " . htmlspecialchars($name) . "</p><p><strong>Email:</strong> " . htmlspecialchars($email) . "</p><p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>"
        );
    } catch (Exception $eAdminReg) {}

    $user = ['id'=>$uid,'name'=>$name,'email'=>$email,'phone'=>$phone,'role'=>$role,'avatar'=>'','kyc_status'=>'not_started','email_verified'=>0,'referral_code'=>$my_ref_code];
    $_SESSION['user'] = $user;
    $_SESSION['user']['active_role'] = $role;
    echo json_encode(['success'=>true,'requires_verification'=>true,'user'=>$user,'csrf'=>csrf_token()]);
    break;

case 'login':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!rate_limit('login', 5, 60)) { http_response_code(429); echo json_encode(['error'=>'Too many login attempts. Wait 60 seconds.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $identifier = clean($input['identifier'] ?? '');
    $password = $input['password'] ?? '';
    $otp = $input['otp'] ?? '';
    if (empty($identifier)) { http_response_code(400); echo json_encode(['error'=>'Email or phone is required.']); exit; }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();
    if (!$user) { http_response_code(401); echo json_encode(['error'=>'Account not found.']); exit; }
    if (isset($user['is_active']) && intval($user['is_active']) !== 1) {
        http_response_code(403);
        $st_lbl = intval($user['is_active']) === 2 ? 'banned' : 'suspended';
        echo json_encode(['error' => 'Your account has been ' . $st_lbl . ' by administration. Please contact support.']);
        exit;
    }
    if (($user['role'] ?? '') === 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Administrators must authenticate via the secure Ohati Admin Portal.']);
        exit;
    }
    if (!empty($otp)) {
        if (strlen($otp) < 6) {
            http_response_code(400);
            echo json_encode(['error'=>'Please enter a valid 6-digit OTP code.']);
            exit;
        }
        $ov = $pdo->prepare("SELECT * FROM otp_codes WHERE target = ? AND code = ? AND used = 0 AND expires_at > ? ORDER BY id DESC LIMIT 1");
        $ov->execute([$identifier, $otp, date('Y-m-d H:i:s')]);
        if (!$ov->fetch()) { http_response_code(401); echo json_encode(['error'=>'Invalid or expired OTP.']); exit; }
        $pdo->prepare("UPDATE otp_codes SET used = 1 WHERE target = ? AND code = ?")->execute([$identifier, $otp]);
    } else {
        if (!password_verify($password, $user['password_hash'])) { http_response_code(401); echo json_encode(['error'=>'Incorrect password.']); exit; }
    }
    // Block unverified users — force OTP verification first
    $email_verified = !empty($user['email']) && !empty($user['email_verified']);
    $phone_verified = !empty($user['phone']) && !empty($user['phone_verified']);
    
    if (!$email_verified && !$phone_verified) {
        http_response_code(403);
        $target = !empty($user['email']) ? $user['email'] : $user['phone'];
        echo json_encode([
            'error' => 'Please verify your email address or phone number before signing in. Check for a verification code.',
            'requires_verification' => true,
            'target' => $target
        ]);
        exit;
    }
    // Update login
    $pdo->prepare("UPDATE users SET last_login = ?, login_count = login_count + 1 WHERE id = ?")->execute([date('Y-m-d H:i:s'), $user['id']]);
    // Log login
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $device = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $pdo->prepare("INSERT INTO login_history (user_id,ip_address,device,status) VALUES (?,?,?,'success')")->execute([$user['id'],$ip,$device]);
    $safe_user = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'phone'=>$user['phone'],'role'=>$user['role'],'avatar'=>$user['avatar'],'kyc_status'=>$user['kyc_status']];
    
    // Check if vendor
    $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $v_stmt->execute([$user['id']]);
    $v_id = $v_stmt->fetchColumn();
    if ($v_id) {
        $safe_user['vendor_id'] = intval($v_id);
        $safe_user['has_vendor_profile'] = true;
        $safe_user['active_role'] = $user['role'] === 'vendor' ? 'vendor' : 'customer';
    } else {
        $safe_user['active_role'] = 'customer';
        $safe_user['has_vendor_profile'] = false;
    }
    
    $_SESSION['user'] = $safe_user;
    echo json_encode(['success'=>true,'user'=>$safe_user,'csrf'=>csrf_token()]);
    break;

case 'logout':
    unset($_SESSION['user']);
    session_destroy();
    echo json_encode(['success'=>true]);
    break;

case 'delete_account':
    if (!isset($_SESSION['user']['id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required to delete account.']);
        exit;
    }
    $uid = intval($_SESSION['user']['id']);
    $user_name = $_SESSION['user']['name'] ?? 'User';

    // Archive user record data to deleted_records for admin compliance auditing
    $sel = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $sel->execute([$uid]);
    $u = $sel->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $record_data = json_encode($u);
        $stmt = $pdo->prepare("INSERT INTO deleted_records (record_type, record_id, record_data) VALUES ('user_account_deletion_request', ?, ?)");
        $stmt->execute([$uid, $record_data]);
    }

    // Anonymize user credentials per Store Guidelines
    $pdo->prepare("UPDATE users SET email = CONCAT('deleted_', id, '_', email), phone = CONCAT('deleted_', id, '_', phone), status = 'deleted', is_active = 0 WHERE id = ?")->execute([$uid]);
    
    // Log audit trail
    log_activity($pdo, 'Account Deletion Request', 'User', $uid, $uid, 'customer', $user_name, 0, 'active', 'deleted', 'User requested account deletion via App Settings');

    unset($_SESSION['user']);
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Account successfully deleted per Store Guidelines.']);
    break;

case 'send_otp':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $target = clean($input['target'] ?? '');
    $input_email = clean($input['email'] ?? '');
    $input_phone = clean($input['phone'] ?? '');

    if (empty($target) && empty($input_email) && empty($input_phone)) {
        http_response_code(400); 
        echo json_encode(['error'=>'Phone number or email required for dual verification.']); 
        exit; 
    }
    
    // Resolve Phone and Email targets
    $phone_target = $input_phone;
    $email_target = $input_email;

    if (strpos($target, '@') !== false) {
        $email_target = $target;
    } else if (!empty($target)) {
        $phone_target = $target;
    }

    if (empty($phone_target) && !empty($email_target)) {
        $uStmt = $pdo->prepare("SELECT phone FROM users WHERE email = ? LIMIT 1");
        $uStmt->execute([$email_target]);
        $phone_target = $uStmt->fetchColumn() ?: '';
    }
    if (empty($email_target) && !empty($phone_target)) {
        $uStmt = $pdo->prepare("SELECT email FROM users WHERE phone = ? OR username = ? OR id = ? LIMIT 1");
        $uStmt->execute([$phone_target, $phone_target, intval($phone_target)]);
        $email_target = $uStmt->fetchColumn() ?: '';
        if (empty($email_target) && isset($_SESSION['user']['email'])) {
            $email_target = $_SESSION['user']['email'];
        }
    }
    
    $primary_target = !empty($phone_target) ? $phone_target : $email_target;

    // Check 60-second resend cooldown
    $cooldown_stmt = $pdo->prepare("SELECT created_at FROM otp_codes WHERE target = ? AND created_at > ? ORDER BY id DESC LIMIT 1");
    $cooldown_stmt->execute([$primary_target, date('Y-m-d H:i:s', time() - 60)]);
    if ($cooldown_stmt->fetch()) {
        http_response_code(429);
        echo json_encode(['error' => 'Please wait 60 seconds before requesting a new verification code.']);
        exit;
    }

    // Invalidate any active, unverified previous OTPs for this target
    $pdo->prepare("UPDATE otp_codes SET used = 1 WHERE target = ? AND used = 0")->execute([$primary_target]);

    // Generate secure 6-digit numeric OTP code
    $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $code_hash = password_hash($code, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + 600); // Valid for 10 minutes
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $device = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 190);

    // 1. Immediately store hashed OTP code into database so verification is active in < 5ms
    $targets_to_insert = array_unique(array_filter([$target, $primary_target, $phone_target, $email_target]));
    foreach ($targets_to_insert as $t) {
        $pdo->prepare("INSERT INTO otp_codes (target, code, code_hash, email_status, sms_status, expires_at, ip_address, device) VALUES (?, ?, ?, 'pending', 'pending', ?, ?, ?)")
            ->execute([$t, $code, $code_hash, $expires, $ip, $device]);
    }

    $sms_sent = false;
    $email_sent = false;

    // 2. Dispatch SMS via SMSOnlineGh API
    if (!empty($phone_target)) {
        $sms_msg = "Your Ohati verification code is: $code. Valid for 10 minutes. Do not share this code with anyone.";
        $sms_res = send_smsonlinegh($phone_target, $sms_msg);
        $sms_sent = $sms_res['success'] ?? false;
    }

    // 3. Dispatch Email via SMTP
    if (!empty($email_target) && strpos($email_target, '@') !== false) {
        try {
            $subject = "Ohati Dual Verification Code: " . $code;
            $body = "<html><body style='font-family:sans-serif; background-color:#f6f9fc; padding:30px; color:#333;'>"
                  . "<div style='max-width:550px; margin:0 auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); border:1px solid #e4e8eb;'>"
                  . "<div style='text-align:center; background-color:#1B2B4B; padding:20px; border-radius:8px 8px 0 0; margin:-30px -30px 25px -30px;'>"
                  . "<h1 style='color:#fff; margin:0; font-size:24px; letter-spacing:2px;'>OHATI</h1>"
                  . "<p style='color:#c5a880; margin:5px 0 0 0; font-size:11px; text-transform:uppercase; letter-spacing:1px;'>Find. Compare. Book. Celebrate.</p>"
                  . "</div>"
                  . "<h2 style='color:#1B2B4B; margin-top:0; font-size:20px;'>Dual Account Verification</h2>"
                  . "<p style='font-size:15px; margin-bottom:20px;'>Welcome! To complete your verification, please enter the 6-digit verification code below (sent to your phone & email):</p>"
                  . "<div style='background-color:#f4f6f8; border-radius:8px; padding:20px; text-align:center; margin-bottom:20px; border:1px solid #e9ecef;'>"
                  . "<span style='font-size:32px; font-weight:bold; letter-spacing:6px; color:#1B2B4B; font-family:monospace;'>{$code}</span>"
                  . "</div>"
                  . "<p style='font-size:13px; color:#666;'>This code is valid for 10 minutes. If you did not request this code, please ignore this message.</p>"
                  . "<div style='border-top:1px solid #eee; margin-top:25px; padding-top:15px; font-size:12px; color:#7f8c8d; text-align:center;'>"
                  . "<p>Ghana's Trusted Event Vendor Marketplace</p>"
                  . "<p>&copy; 2026 Ohati. All rights reserved.</p>"
                  . "</div>"
                  . "</div>"
                  . "</body></html>";
            $email_sent = send_smtp_mail($email_target, $subject, $body);
        } catch (Exception $e) {}
    }

    // Update delivery status flags
    foreach ($targets_to_insert as $t) {
        $pdo->prepare("UPDATE otp_codes SET email_status = ?, sms_status = ? WHERE target = ? AND code_hash = ?")
            ->execute([$email_sent ? 'sent' : 'failed', $sms_sent ? 'sent' : 'failed', $t, $code_hash]);
    }

    log_activity($pdo, 'OTP Sent', 'User', 0, 'system', 'System', 0, '', 'Sent', 'OTP code sent via SMS: ' . ($sms_sent?'Yes':'No') . ' | Email: ' . ($email_sent?'Yes':'No') . ' for target: ' . $primary_target);

    $msg_channel = ($sms_sent && $email_sent) ? 'SMS and Email' : ($sms_sent ? 'SMS' : ($email_sent ? 'Email' : 'SMS/Email'));
    echo json_encode([
        'success' => true, 
        'message' => "Verification code dispatched via $msg_channel. Valid for 10 minutes.", 
        'sms_sent' => $sms_sent, 
        'email_sent' => $email_sent
    ]);
    break;

case 'verify_otp':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $target = clean($input['target'] ?? '');
    $code = clean($input['code'] ?? '');
    if (empty($code) || strlen($code) < 6) {
        http_response_code(400);
        echo json_encode(['error'=>'Please enter a valid 6-digit OTP code.']);
        exit;
    }
    
    // Fetch latest active OTP for target
    $ov = $pdo->prepare("SELECT * FROM otp_codes WHERE (target = ? OR target = ?) AND used = 0 AND expires_at > ? ORDER BY id DESC LIMIT 1");
    $ov->execute([$target, $target, date('Y-m-d H:i:s')]);
    $matched_otp = $ov->fetch();
    
    if (!$matched_otp) { 
        http_response_code(400); 
        echo json_encode(['error'=>'Invalid or expired OTP code. Please request a new code.']); 
        exit; 
    }

    // Check rate limiting / brute force protection
    if (intval($matched_otp['attempts']) >= 5) {
        $pdo->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$matched_otp['id']]);
        http_response_code(429);
        echo json_encode(['error'=>'Too many failed attempts. This OTP code has been invalidated for security.']);
        exit;
    }

    // Verify hash or code
    $is_valid = false;
    if (!empty($matched_otp['code_hash'])) {
        $is_valid = password_verify($code, $matched_otp['code_hash']);
    }
    if (!$is_valid && $matched_otp['code'] === $code) {
        $is_valid = true;
    }

    if (!$is_valid) {
        $pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?")->execute([$matched_otp['id']]);
        http_response_code(400);
        echo json_encode(['error'=>'Incorrect verification code. Please check your phone/email and try again.']);
        exit;
    }

    // Mark as used immediately
    $pdo->prepare("UPDATE otp_codes SET used = 1 WHERE id = ? OR code = ?")->execute([$matched_otp['id'], $code]);
    
    // Mark both email and phone verified upon entering correct Dual OTP code
    $pdo->prepare("UPDATE users SET email_verified = 1, phone_verified = 1 WHERE email = ? OR phone = ? OR username = ? OR id = ?")->execute([$target, $target, $target, intval($target)]);
    
    log_activity($pdo, 'OTP Verified', 'User', 0, 'customer', $target, 0, 'Pending', 'Verified', 'OTP code verified for target: ' . $target);
    
    // Retrieve verified user to establish session
    $uStmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ? OR username = ? LIMIT 1");
    $uStmt->execute([$target, $target, $target]);
    $user = $uStmt->fetch();
    $safe_user = null;
    if ($user) {
        $safe_user = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'avatar' => $user['avatar'] ?? '',
            'kyc_status' => $user['kyc_status'] ?? 'not_started'
        ];
        
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$user['id']]);
        $v_id = $v_stmt->fetchColumn();
        if ($v_id) {
            $safe_user['vendor_id'] = intval($v_id);
            $safe_user['has_vendor_profile'] = true;
            $safe_user['active_role'] = $user['role'] === 'vendor' ? 'vendor' : 'customer';
        } else {
            $safe_user['active_role'] = 'customer';
            $safe_user['has_vendor_profile'] = false;
        }
        $_SESSION['user'] = $safe_user;
    }
    
    echo json_encode(['success'=>true,'verified'=>true,'user'=>$safe_user,'csrf'=>csrf_token()]);
    break;

case 'forgot_password':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!rate_limit('forgot_password', 3, 60)) { http_response_code(429); echo json_encode(['error'=>'Too many reset attempts. Please wait and try again.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $target = clean($input['target'] ?? '');
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$target,$target]);
    if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['error'=>'Account not found.']); exit; }
    $code = str_pad(rand(0,999999),6,'0',STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $pdo->prepare("INSERT INTO otp_codes (target,code,type,expires_at) VALUES (?,?,?,?)")->execute([$target,$code,'reset',$expires]);

    $email_sent = false;
    if (strpos($target, '@') !== false) {
        try {
            require_once __DIR__ . '/mail_helper.php';
            
            // Build reset link dynamically
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domainName = $_SERVER['HTTP_HOST'];
            $currentDir = dirname($_SERVER['REQUEST_URI']);
            $currentDir = str_replace('\\', '/', $currentDir);
            if ($currentDir === '/') $currentDir = '';
            
            $resetLink = $protocol . $domainName . $currentDir . '/forgot-password.php?target=' . urlencode($target) . '&code=' . urlencode($code);
            
            $subject = "Reset Your Ohati Password";
            $body = "<html><body style='font-family:sans-serif; background-color:#f6f9fc; padding:30px; color:#333;'>"
                  . "<div style='max-width:550px; margin:0 auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); border:1px solid #e4e8eb;'>"
                  . "<div style='text-align:center; background-color:#1B2B4B; padding:20px; border-radius:8px 8px 0 0; margin:-30px -30px 25px -30px;'>"
                  . "<h1 style='color:#fff; margin:0; font-size:24px; letter-spacing:2px;'>OHATI</h1>"
                  . "<p style='color:#c5a880; margin:5px 0 0 0; font-size:11px; text-transform:uppercase; letter-spacing:1px;'>Find. Compare. Book. Celebrate.</p>"
                  . "</div>"
                  . "<h2 style='color:#1B2B4B; margin-top:0; font-size:20px;'>Reset Your Password</h2>"
                  . "<p style='font-size:15px; margin-bottom:20px;'>We received a request to reset the password for your Ohati account. Please click the button below to complete the reset process:</p>"
                  . "<div style='text-align:center; margin:25px 0;'>"
                  . "<a href='{$resetLink}' target='_blank' style='background-color:#1B2B4B; color:#fff; border-radius:6px; padding:14px 28px; text-decoration:none; font-size:15px; font-weight:bold; display:inline-block;'>Reset Password Now</a>"
                  . "</div>"
                  . "<p style='font-size:13px; color:#666;'>For safety, your verification OTP code is: <strong>{$code}</strong></p>"
                  . "<p style='font-size:13px; color:#666;'>This reset link and code will expire in 10 minutes.</p>"
                  . "<div style='border-top:1px solid #eee; margin-top:25px; padding-top:15px; font-size:12px; color:#7f8c8d; text-align:center;'>"
                  . "<p>Ghana's Trusted Event Vendor Marketplace</p>"
                  . "<p>&copy; 2026 Ohati. All rights reserved.</p>"
                  . "</div>"
                  . "</div>"
                  . "</body></html>";
            $email_sent = send_smtp_mail($target, $subject, $body);
        } catch (Exception $e) {
            error_log("Failed to send reset email: " . $e->getMessage());
        }
    }

    if ($email_sent) {
        echo json_encode(['success'=>true, 'message'=>'Password reset code sent to your email.', 'email_sent'=>true]);
    } else {
        if (is_local_env()) {
            echo json_encode(['success'=>true, 'message'=>'[Local Development Mode] SMTP failed. Code auto-supplied.', 'email_sent'=>false, 'fallback_code'=>$code]);
        } else {
            http_response_code(500);
            echo json_encode(['error'=>'Failed to deliver password reset email. Please check your email configuration or try again later.']);
            exit;
        }
    }
    break;

case 'reset_password':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $target = clean($input['target'] ?? '');
    $code = clean($input['code'] ?? '');
    $password = $input['password'] ?? '';
    if (strlen($password) < 8) { http_response_code(400); echo json_encode(['error'=>'Password must be 8+ characters.']); exit; }
    $ov = $pdo->prepare("SELECT * FROM otp_codes WHERE target = ? AND code = ? AND type='reset' AND used=0 AND expires_at > ? ORDER BY id DESC LIMIT 1");
    $ov->execute([$target,$code,date('Y-m-d H:i:s')]);
    if (!$ov->fetch()) { http_response_code(400); echo json_encode(['error'=>'Invalid or expired code.']); exit; }
    $pdo->prepare("UPDATE otp_codes SET used=1 WHERE target=? AND code=?")->execute([$target,$code]);
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $col = strpos($target,'@') !== false ? 'email' : 'phone';
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE $col = ?")->execute([$hash,$target]);
    echo json_encode(['success'=>true]);
    break;

case 'update_profile':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!isset($_SESSION['user']['id'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in']); exit; }
    $uid = intval($_SESSION['user']['id']);
    $input = json_decode(file_get_contents('php://input'), true);

    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    
    if (!$is_admin) {
        $u_cur = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
        $u_cur->execute([$uid]);
        $u_row = $u_cur->fetch();

        if ($u_row) {
            if (!empty($u_row['name']) && isset($input['name'])) $input['name'] = $u_row['name'];
            if (!empty($u_row['email']) && isset($input['email'])) $input['email'] = $u_row['email'];
            if (!empty($u_row['phone']) && isset($input['phone'])) $input['phone'] = $u_row['phone'];
        }
    }

    $fields = ['name', 'email', 'phone', 'avatar', 'gender', 'dob', 'country', 'state', 'city', 'kyc_status', 'kyc_id_type', 'kyc_id_front', 'kyc_id_back', 'kyc_selfie', 'kyc_submitted_at'];
    $updates = [];
    $params = [];

    foreach ($fields as $f) {
        if (isset($input[$f])) {
            $val = $input[$f];
            if ($f === 'avatar' || $f === 'kyc_id_front' || $f === 'kyc_id_back' || $f === 'kyc_selfie') {
                if (is_string($val) && strpos($val, 'data:image') === 0) {
                    try {
                        $val = secure_save_base64_image($val, 'kyc', $f . '_' . $uid);
                    } catch (Exception $e) {}
                }
            }
            $updates[] = "$f = ?";
            $params[] = $val;
            $_SESSION['user'][$f] = $val;
        }
    }

    if (!empty($updates)) {
        $params[] = $uid;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);

        // Sync avatar to vendor logo if user owns a vendor profile
        if (isset($input['avatar']) && !empty($input['avatar'])) {
            try {
                $pdo->prepare("UPDATE vendors SET logo = ? WHERE user_id = ?")->execute([$input['avatar'], $uid]);
            } catch (Exception $eVendLogo) {}
        }
    }

    echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
    break;

case 'register_device_token':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $device_token = clean($input['device_token'] ?? '');
    $platform = clean($input['platform'] ?? 'android');
    $uid = intval($_SESSION['user']['id'] ?? 0);

    if (!empty($device_token)) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT 0,
                device_token VARCHAR(500) NOT NULL,
                platform VARCHAR(50) DEFAULT 'android',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_token (device_token(255))
            )");
            $stmt = $pdo->prepare("INSERT INTO device_tokens (user_id, device_token, platform) VALUES (?, ?, ?)");
            $stmt->execute([$uid, $device_token, $platform]);
        } catch (Exception $e) {}
    }
    echo json_encode(['success' => true]);
    break;

case 'session':
    $reviews_json = '';
    try {
        $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = 'platform_reviews'");
        $stmt->execute();
        $reviews_json = $stmt->fetchColumn();
    } catch (Exception $e) {}
    
    $reviews = null;
    if ($reviews_json) {
        $reviews = json_decode($reviews_json, true);
    }

    $locked_fields = ["name", "email", "phone", "dob"];
    try {
        $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = 'locked_profile_fields'");
        $stmt->execute();
        $lf_json = $stmt->fetchColumn();
        if ($lf_json) {
            $locked_fields = json_decode($lf_json, true);
        }
    } catch (Exception $e) {}

    if (isset($_SESSION['user'])) {
        $uid = intval($_SESSION['user']['id']);
        $fresh_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $fresh_stmt->execute([$uid]);
        $fresh_user = $fresh_stmt->fetch();
        if ($fresh_user) {
            $_SESSION['user']['name'] = $fresh_user['name'];
            $_SESSION['user']['email'] = $fresh_user['email'];
            $_SESSION['user']['phone'] = $fresh_user['phone'];
            $_SESSION['user']['avatar'] = $fresh_user['avatar'];
            $_SESSION['user']['kyc_status'] = $fresh_user['kyc_status'];
            $_SESSION['user']['role'] = $fresh_user['role'];
        }
        
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $v_id = $v_stmt->fetchColumn();
        if ($v_id) {
            $_SESSION['user']['vendor_id'] = intval($v_id);
            $_SESSION['user']['has_vendor_profile'] = true;
            if (!isset($_SESSION['user']['active_role'])) {
                $_SESSION['user']['active_role'] = $_SESSION['user']['role'] === 'vendor' ? 'vendor' : 'customer';
            }
        } else {
            $_SESSION['user']['active_role'] = 'customer';
            $_SESSION['user']['has_vendor_profile'] = false;
        }
    }

    $settings = [];
    try {
        $stmt = $pdo->query("SELECT key_name, val_value FROM system_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {}

    echo json_encode([
        'user' => $_SESSION['user'] ?? null,
        'csrf' => csrf_token(),
        'platform_reviews' => $reviews,
        'locked_profile_fields' => $locked_fields,
        'settings' => $settings
    ]);
    break;

case 'update_profile':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check if avatar is uploaded as base64 and save it to filesystem
    if (isset($input['avatar']) && strpos($input['avatar'], 'data:image') === 0) {
        try {
            $input['avatar'] = secure_save_base64_image($input['avatar'], 'avatars', 'avatar_' . $_SESSION['user']['id']);
        } catch (Exception $e) {
            http_response_code(400); echo json_encode(['error' => $e->getMessage()]); exit;
        }
    }

    // Support both id_front / selfie and kyc_id_front / kyc_selfie formats
    if (isset($input['id_front'])) $input['kyc_id_front'] = $input['id_front'];
    if (isset($input['selfie'])) $input['kyc_selfie'] = $input['selfie'];

    // Save kyc_id_front if base64
    if (isset($input['kyc_id_front']) && strpos($input['kyc_id_front'], 'data:image') === 0) {
        try {
            $input['kyc_id_front'] = secure_save_base64_image($input['kyc_id_front'], 'kyc', 'id_front_' . $_SESSION['user']['id']);
        } catch (Exception $e) {
            http_response_code(400); echo json_encode(['error' => $e->getMessage()]); exit;
        }
    }

    // Save kyc_selfie if base64
    if (isset($input['kyc_selfie']) && strpos($input['kyc_selfie'], 'data:image') === 0) {
        try {
            $input['kyc_selfie'] = secure_save_base64_image($input['kyc_selfie'], 'kyc', 'selfie_' . $_SESSION['user']['id']);
        } catch (Exception $e) {
            http_response_code(400); echo json_encode(['error' => $e->getMessage()]); exit;
        }
    }

    // Lock core identity fields (name, email, phone) for non-admin users after registration
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    if (!$is_admin && isset($_SESSION['user'])) {
        $u_id = intval($_SESSION['user']['id']);
        $u_curr = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
        $u_curr->execute([$u_id]);
        $u_row = $u_curr->fetch();
        if ($u_row) {
            if (!empty($u_row['name'])) unset($input['name']);
            if (!empty($u_row['email'])) unset($input['email']);
            if (!empty($u_row['phone'])) unset($input['phone']);
        }
    }

    $fields = []; $params = [];
    $allowed_profile_fields = ['name','avatar','gender','dob','country','state','city','language','currency','username','kyc_status','email','phone','kyc_id_type','kyc_id_front','kyc_id_back','kyc_selfie','kyc_submitted_at'];
    foreach ($allowed_profile_fields as $f) {
        if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = clean($input[$f]); }
    }
    if (empty($fields)) { echo json_encode(['success'=>true]); break; }
    $params[] = $_SESSION['user']['id'];
    $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    // Update session
    foreach ($allowed_profile_fields as $f) { if (isset($input[$f])) $_SESSION['user'][$f] = clean($input[$f]); }
    echo json_encode(['success'=>true,'user'=>$_SESSION['user']]);
    break;




// ── CATEGORIES ─────────────────────────────────────────────────────────
case 'categories':
    $categories = [
        ['name'=>'Photography','icon'=>'camera'],['name'=>'Videography','icon'=>'video'],
        ['name'=>'Makeup Artists','icon'=>'brush'],['name'=>'Bridal Shops','icon'=>'shirt'],
        ['name'=>'Event Planners','icon'=>'calendar-days'],['name'=>'Decorators','icon'=>'wand-magic-sparkles'],
        ['name'=>'Caterers','icon'=>'utensils'],['name'=>'Cake Designers','icon'=>'cake-candles'],
        ['name'=>'Event Venues','icon'=>'hotel'],['name'=>'DJs','icon'=>'music'],
        ['name'=>'MCs','icon'=>'microphone'],['name'=>'Live Bands','icon'=>'guitar'],
        ['name'=>'Florists','icon'=>'spa'],['name'=>'Car Rentals','icon'=>'car'],
        ['name'=>'Security Services','icon'=>'shield-halved'],
        ['name'=>'Chilling Services','icon'=>'snowflake'],
        ['name'=>'Rental Equipment','icon'=>'chair'],
        ['name'=>'Cocktail Bars','icon'=>'martini-glass-citrus'],
        ['name'=>'Honeymoon Packages','icon'=>'plane-departure'],
        ['name'=>'Invitation Designers','icon'=>'envelope-open-text'],
        ['name'=>'Jewelers','icon'=>'gem'],['name'=>'Lighting','icon'=>'lightbulb'],
        ['name'=>'Printing Services','icon'=>'print'],['name'=>'Ushers','icon'=>'user-check'],
        ['name'=>'Content Creators','icon'=>'clapperboard'],['name'=>'Juice Bar','icon'=>'glass-water'],
    ];
    echo json_encode($categories);
    break;

// ── VENDORS & SEARCH ──────────────────────────────────────────────────
case 'search':
case 'vendors':
    $category = $_GET['category'] ?? '';
    $location = $_GET['location'] ?? '';
    $search = $_GET['q'] ?? $_GET['search'] ?? '';
    $min_rating = floatval($_GET['min_rating'] ?? 0);
    $verified_only = intval($_GET['verified'] ?? $_GET['verified_only'] ?? 0);
    $premium_only = intval($_GET['premium'] ?? $_GET['premium_only'] ?? 0);
    $instant = intval($_GET['instant_booking'] ?? 0);
    $sort = $_GET['sort'] ?? 'recommended';
    $min_price = floatval($_GET['min_price'] ?? 0);
    $max_price = floatval($_GET['max_price'] ?? 0);

    $q = "SELECT * FROM vendors WHERE is_active = 1"; $p = [];
    if ($category && $category !== 'All') { $q .= " AND category = ?"; $p[] = $category; }
    if ($location && $location !== 'All') { $q .= " AND location LIKE ?"; $p[] = '%'.$location.'%'; }
    if ($search) { $q .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)"; $p[] = '%'.$search.'%'; $p[] = '%'.$search.'%'; $p[] = '%'.$search.'%'; }
    if ($min_rating > 0) { $q .= " AND rating >= ?"; $p[] = $min_rating; }
    if ($verified_only) { $q .= " AND verified = 1"; }
    if ($premium_only) { $q .= " AND premium = 1"; }
    if ($instant) { $q .= " AND instant_booking = 1"; }

    if ($sort === 'rating') {
        $q .= " ORDER BY rating DESC, reviews_count DESC";
    } else if ($sort === 'newest') {
        $q .= " ORDER BY id DESC";
    } else if ($sort === 'popular') {
        $q .= " ORDER BY completed_jobs DESC, rating DESC";
    } else {
        $q .= " ORDER BY featured DESC, premium DESC, verified DESC, rating DESC, reviews_count DESC, completed_jobs DESC";
    }
    
    $stmt = $pdo->prepare($q); $stmt->execute($p); $vendors = $stmt->fetchAll();
    
    // Helper to get base price from JSON packages
    $get_base_price = function($packages) {
        if (empty($packages) || !is_array($packages)) {
            return 2500.0;
        }
        $min = null;
        foreach ($packages as $pkg) {
            if (isset($pkg['price'])) {
                $price_str = preg_replace('/[^0-9.]/', '', $pkg['price']);
                $val = floatval($price_str);
                if ($val > 0) {
                    if ($min === null || $val < $min) {
                        $min = $val;
                    }
                }
            }
        }
        return $min !== null ? $min : 2500.0;
    };

    $filtered = [];
    foreach ($vendors as &$v) {
        $v['packages_pricing'] = json_decode($v['packages_pricing'] ?? '[]', true) ?: [];
        $v['social_links'] = json_decode($v['social_links'] ?? '{}', true) ?: [];
        $v['gallery'] = json_decode($v['gallery'] ?? '[]', true) ?: [];
        $v['is_favorite'] = in_array($v['id'], $_SESSION['favorites']);

        $base = $get_base_price($v['packages_pricing']);
        if ($min_price > 0 && $base < $min_price) {
            continue;
        }
        if ($max_price > 0 && $base > $max_price) {
            continue;
        }
        $filtered[] = $v;
    }
    echo json_encode($filtered);
    break;

case 'vendor_details':
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0 && !empty($_SESSION['user']['id'])) {
        $u_id = intval($_SESSION['user']['id']);
        $v_chk = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_chk->execute([$u_id]);
        $id = intval($v_chk->fetchColumn() ?: 0);
    }

    if ($id <= 0) { 
        http_response_code(400); 
        echo json_encode(['error'=>'Invalid vendor or profile ID requested']); 
        exit; 
    }
    
    // Check if the request is explicitly for a customer's details
    $is_customer_req = intval($_GET['is_customer'] ?? $_POST['is_customer'] ?? $raw_input['is_customer'] ?? 0);
    if ($is_customer_req === 1) {
        $u_stmt = $pdo->prepare("SELECT id, name, avatar, phone, email FROM users WHERE id = ?");
        $u_stmt->execute([$id]);
        $u = $u_stmt->fetch();
        if ($u) {
            $cust_details = [
                'id' => $u['id'],
                'user_id' => $u['id'],
                'name' => $u['name'],
                'logo' => $u['avatar'],
                'cover_photo' => $u['avatar'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?q=80&w=80',
                'category' => 'Client',
                'availability' => 'Online',
                'location' => 'Accra, Ghana',
                'phone' => $u['phone'],
                'email' => $u['email'],
                'rating' => '5.0',
                'reviews_count' => 0,
                'reviews' => [],
                'packages_pricing' => [],
                'social_links' => [],
                'gallery' => [],
                'team_members' => [],
                'faqs' => [],
                'languages' => [],
                'certifications' => [],
                'awards' => [],
                'working_hours' => [],
                'is_favorite' => false,
                'is_following' => false,
                'followers_count' => 0
            ];
            echo json_encode($cust_details);
            exit;
        }
    }
    
    // Increment view counter & log timestamped view
    $pdo->prepare("UPDATE vendors SET views_count = views_count + 1 WHERE id = ? OR user_id = ?")->execute([$id, $id]);
    try {
        $v_uid = intval($_SESSION['user']['id'] ?? 0);
        $v_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $pdo->prepare("INSERT INTO vendor_views_log (vendor_id, user_id, ip_address) VALUES (?, ?, ?)")->execute([$id, $v_uid, $v_ip]);
    } catch (Exception $vLogEx) {}
    
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ? OR user_id = ?"); 
    $stmt->execute([$id, $id]);
    $v = $stmt->fetch();
    if (!$v) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }
    foreach (['packages_pricing','social_links','gallery','team_members','faqs','languages','certifications','awards','working_hours'] as $f) {
        $v[$f] = json_decode($v[$f] ?? '[]', true) ?: [];
    }
    $v['is_favorite'] = in_array($v['id'], $_SESSION['favorites']);
    
    // Check follower status
    $is_following = 0;
    $uid = intval($_SESSION['user']['id'] ?? 0);
    if ($uid > 0) {
        $f_check = $pdo->prepare("SELECT 1 FROM followers WHERE user_id = ? AND vendor_id = ?");
        $f_check->execute([$uid, $id]);
        if ($f_check->fetch()) {
            $is_following = 1;
        }
    }
    $v['is_following'] = $is_following;
    
    // Count followers
    $f_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE vendor_id = ?");
    $f_count_stmt->execute([$id]);
    $v['followers_count'] = intval($f_count_stmt->fetchColumn());

    $r = $pdo->prepare("SELECT * FROM reviews WHERE vendor_id = ? ORDER BY id DESC"); $r->execute([$id]);
    $v['reviews'] = $r->fetchAll();
    foreach ($v['reviews'] as &$rev) { $rev['photos'] = json_decode($rev['photos'] ?? '[]', true) ?: []; }
    echo json_encode($v);
    break;

// ── BOOKINGS ───────────────────────────────────────────────────────────
case 'book':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $vendor_id = intval($input['vendor_id'] ?? 0);
    $user_name = clean($input['user_name'] ?? '');
    $user_phone = clean($input['user_phone'] ?? '');
    $event_date = clean($input['event_date'] ?? '');
    $event_type = clean($input['event_type'] ?? '');
    $package_name = clean($input['package_name'] ?? 'Custom');
    $price = floatval($input['price'] ?? 0);
    $notes = clean($input['notes'] ?? '');
    if ($vendor_id <= 0 || empty($user_name) || empty($user_phone) || empty($event_date)) {
        http_response_code(400); echo json_encode(['error'=>'All required fields are needed.']); exit;
    }
    $uid = intval($_SESSION['user']['id'] ?? 0);

    // Prevent self-booking on backend
    $check_self = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
    $check_self->execute([$vendor_id]);
    $vendor_owner = $check_self->fetchColumn();
    if ($vendor_owner && intval($vendor_owner) === $uid) {
        http_response_code(400);
        echo json_encode(['error' => 'You cannot book your own vendor profile.']);
        exit;
    }

    // Prevent vendors (active role = vendor) from booking
    $active_role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'] ?? 'customer';
    if ($active_role === 'vendor') {
        http_response_code(400);
        echo json_encode(['error' => 'Vendors cannot make bookings. Please switch to a customer account.']);
        exit;
    }

    $negotiated_price = floatval($input['negotiated_price'] ?? $price);
    $neg_history = [];
    if ($negotiated_price > 0 && $negotiated_price != $price) {
        $neg_history[] = [
            'sender' => 'Customer',
            'user_name' => $user_name,
            'price' => $negotiated_price,
            'original_price' => $price,
            'notes' => $notes,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    $neg_history_json = json_encode($neg_history);

    $created_at_stamp = date('Y-m-d H:i:s');
    $timeline = json_encode([['status'=>'Inquiry Submitted','user'=>'Customer','timestamp'=>$created_at_stamp,'notes'=>$notes]]);
    $stmt = $pdo->prepare("INSERT INTO bookings (vendor_id,user_id,user_name,user_phone,event_date,event_type,package_name,price,negotiated_price,negotiation_history,notes,status,payment_status,timeline,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$vendor_id,$uid,$user_name,$user_phone,$event_date,$event_type,$package_name,$price,$negotiated_price,$neg_history_json,$notes,'Inquiry','Unpaid',$timeline,$created_at_stamp]);
    $booking_id = $pdo->lastInsertId();

    log_activity($pdo, 'Booking Created', 'Booking', $booking_id, $uid, 'customer', $user_name, $negotiated_price, '', 'Inquiry', "Created booking #OHT-B" . str_pad($booking_id, 5, '0', STR_PAD_LEFT) . " for " . $event_type);

    $cat_s = $pdo->prepare("SELECT v.*, u.email as user_email, u.phone as user_phone FROM vendors v LEFT JOIN users u ON v.user_id = u.id WHERE v.id = ?"); 
    $cat_s->execute([$vendor_id]);
    $v_data = $cat_s->fetch();
    if ($v_data) {
        unlock_category_milestones($pdo, $v_data['category'], $uid);
        // 1. In-App Notification
        add_notification($pdo, $v_data['user_id'], "New Booking Inquiry", "You have received a new booking request from $user_name for " . $event_date, 'calendar-check', 'bookings', $booking_id);

        // 2. Dual Email + SMS Dispatch
        $vendor_recipient_email = !empty($v_data['email']) ? $v_data['email'] : ($v_data['user_email'] ?? '');
        $vendor_recipient_phone = !empty($v_data['phone']) ? $v_data['phone'] : ($v_data['user_phone'] ?? '');

        $ref_formatted = "#OHT-B" . str_pad($booking_id, 5, '0', STR_PAD_LEFT);
        $email_subject = "🎉 New Booking Request $ref_formatted from $user_name";
        $cust_email = clean($_SESSION['user']['email'] ?? '');

        $email_body = "
            <div style='font-family:sans-serif; max-width:600px; margin:0 auto; padding:20px; border:1px solid #e0e0e0; border-radius:10px;'>
                <h2 style='color:#0E8345;'>New Booking Inquiry Received!</h2>
                <p>Dear <strong>" . htmlspecialchars($v_data['name']) . "</strong>,</p>
                <p>You have received a new event booking inquiry on <strong>Ohati</strong> from <strong>" . htmlspecialchars($user_name) . "</strong>.</p>
                <div style='background:#f9f9f9; padding:15px; border-radius:8px; margin:15px 0;'>
                    <p><strong>Booking Ref:</strong> $ref_formatted</p>
                    <p><strong>Customer Name:</strong> " . htmlspecialchars($user_name) . "</p>
                    <p><strong>Contact Email:</strong> " . htmlspecialchars($cust_email) . "</p>
                    <p><strong>Contact Phone:</strong> " . htmlspecialchars($user_phone) . "</p>
                    <p><strong>Event Date:</strong> " . htmlspecialchars($event_date) . "</p>
                    <p><strong>Event Type:</strong> " . htmlspecialchars($event_type) . "</p>
                    <p><strong>Service / Package:</strong> " . htmlspecialchars($package_name) . "</p>
                    <p><strong>Customer Offer / Budget:</strong> " . ($negotiated_price > 0 ? "GH₵ " . number_format($negotiated_price, 2) : "Open for Negotiation") . "</p>
                    <p><strong>Event Notes / Instructions:</strong> " . htmlspecialchars($notes) . "</p>
                </div>
                <p>Please log into your <strong>Ohati Vendor Dashboard</strong> or Mobile App to view full details, chat with the client, or accept the booking.</p>
            </div>
        ";
        $sms_body = "OHATI ALERT: You have a new booking request from $user_name for $event_date ($event_type). Log into your Ohati app to review full details & respond!";

        send_dual_notification($vendor_recipient_phone, $vendor_recipient_email, "New Booking Inquiry", $sms_body, $email_subject, $email_body);
        
        // Also dispatch Admin Email Notification to ohatiwebsite@gmail.com
        send_admin_activity_notification("New Booking Request (Ref: $ref_formatted)", $email_body);
    }
    echo json_encode(['success'=>true,'booking_id'=>$booking_id]);
    break;

case 'bookings':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'] ?? 'customer';
    if ($role === 'vendor') {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vendor_id = $v_stmt->fetchColumn();
        if ($vendor_id) {
            $stmt = $pdo->prepare("SELECT b.*, u.email as user_email, u.avatar as user_avatar, v.name as vendor_name, v.category as vendor_category, v.logo as vendor_logo, v.location as vendor_location, v.phone as vendor_phone, v.email as vendor_email, v.whatsapp as vendor_whatsapp FROM bookings b JOIN vendors v ON b.vendor_id = v.id LEFT JOIN users u ON b.user_id = u.id WHERE b.vendor_id = ? ORDER BY b.id DESC");
            $stmt->execute([$vendor_id]);
        } else {
            echo json_encode([]); exit;
        }
    } else {
        $stmt = $pdo->prepare("SELECT b.*, u.email as user_email, u.avatar as user_avatar, v.name as vendor_name, v.category as vendor_category, v.logo as vendor_logo, v.location as vendor_location, v.phone as vendor_phone, v.email as vendor_email, v.whatsapp as vendor_whatsapp FROM bookings b JOIN vendors v ON b.vendor_id = v.id LEFT JOIN users u ON b.user_id = u.id WHERE b.user_id = ? ORDER BY b.id DESC");
        $stmt->execute([$uid]);
    }
    $bookings = $stmt->fetchAll();
    foreach ($bookings as &$b) {
        if (empty($b['created_at'])) {
            $b['created_at'] = date('Y-m-d H:i:s');
        }
        $b['timeline'] = json_decode($b['timeline'] ?? '[]', true) ?: [];
        $b['negotiation_history'] = json_decode($b['negotiation_history'] ?? '[]', true) ?: [];
    }
    echo json_encode($bookings);
    break;

case 'update_booking':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $bid = intval($input['id'] ?? 0);
    if ($bid <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid booking ID']); exit; }
    
    // Scoped Access Control / Security check
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT user_id, vendor_id FROM bookings WHERE id = ?");
    $stmt->execute([$bid]);
    $booking = $stmt->fetch();
    if (!$booking) { http_response_code(404); echo json_encode(['error'=>'Booking not found.']); exit; }
    
    $is_vendor = false;
    $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $v_stmt->execute([$uid]);
    $vendor_id = $v_stmt->fetchColumn();
    if ($vendor_id && intval($vendor_id) === intval($booking['vendor_id'])) {
        $is_vendor = true;
    }
    
    if (intval($booking['user_id']) !== $uid && !$is_vendor && ($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403); echo json_encode(['error'=>'Unauthorized access to this booking.']); exit;
    }

    $fields = []; $params = [];
    foreach (['status','payment_status','deposit_paid','balance_paid','negotiated_price','price'] as $f) {
        if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
    }
    if (isset($input['timeline_entry'])) {
        $cur = $pdo->prepare("SELECT timeline FROM bookings WHERE id = ?"); $cur->execute([$bid]);
        $tl = json_decode($cur->fetchColumn() ?: '[]', true) ?: [];
        $tl[] = $input['timeline_entry'];
        $fields[] = "timeline = ?"; $params[] = json_encode($tl);
    }
    if (isset($input['negotiation_entry'])) {
        $cur = $pdo->prepare("SELECT negotiation_history FROM bookings WHERE id = ?"); $cur->execute([$bid]);
        $nh = json_decode($cur->fetchColumn() ?: '[]', true) ?: [];
        $nh[] = $input['negotiation_entry'];
        $fields[] = "negotiation_history = ?"; $params[] = json_encode($nh);
    }
    if (!empty($fields)) {
        $params[] = $bid;
        $pdo->prepare("UPDATE bookings SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        if (isset($input['status'])) {
            log_activity($pdo, 'Booking Status Updated', 'Booking', $bid, $uid, $is_vendor ? 'vendor' : 'customer', $_SESSION['user']['name'] ?? '', 0, '', $input['status'], "Updated booking #OHT-B" . str_pad($bid, 5, '0', STR_PAD_LEFT) . " status to " . $input['status']);
        }
    }

    // Trigger user notification about the booking change
    $b_stmt = $pdo->prepare("SELECT b.*, v.user_id as vendor_user_id, v.name as vendor_name FROM bookings b JOIN vendors v ON b.vendor_id = v.id WHERE b.id = ?");
    $b_stmt->execute([$bid]);
    $booking_info = $b_stmt->fetch();
    if ($booking_info) {
        $customer_user_id = $booking_info['user_id'];
        $vendor_user_id = $booking_info['vendor_user_id'];
        $current_user_id = intval($_SESSION['user']['id'] ?? 0);
        
        $recipient_id = ($current_user_id === $customer_user_id) ? $vendor_user_id : $customer_user_id;
        $sender_name = ($current_user_id === $customer_user_id) ? $booking_info['user_name'] : $booking_info['vendor_name'];
        
        if (isset($input['status'])) {
            add_notification($pdo, $recipient_id, "Booking Status Update", "Your booking (Ref: #{$bid}) with {$sender_name} has been updated to '{$input['status']}'.");
            
            if (in_array(strtolower($input['status']), ['confirmed', 'approved'])) {
                $c_stmt = $pdo->prepare("SELECT phone, email FROM users WHERE id = ?");
                $c_stmt->execute([$customer_user_id]);
                $c_info = $c_stmt->fetch();
                $c_phone = $c_info['phone'] ?? $booking_info['user_phone'];
                $c_email = $c_info['email'] ?? '';

                $approve_subject = "🎉 Booking Inquiry Approved for " . $booking_info['event_date'];
                $approve_html = "
                    <div style='font-family:sans-serif; max-width:600px; margin:0 auto; padding:20px; border:1px solid #e0e0e0; border-radius:10px;'>
                        <h2 style='color:#0E8345;'>Booking Request Accepted!</h2>
                        <p>Dear <strong>" . htmlspecialchars($booking_info['user_name']) . "</strong>,</p>
                        <p>Vendor <strong>" . htmlspecialchars($booking_info['vendor_name']) . "</strong> has accepted your booking request for <strong>" . htmlspecialchars($booking_info['event_date']) . "</strong>.</p>
                        <p><strong>Package / Service:</strong> " . htmlspecialchars($booking_info['package_name']) . "</p>
                        <p>You can now view confirmed booking details in your Ohati App and make payment arrangements.</p>
                    </div>
                ";
                $approve_sms = "Great news! Vendor {$booking_info['vendor_name']} has accepted your booking request for {$booking_info['event_date']}. Check app to view details.";

                send_dual_notification($c_phone, $c_email, "Booking Accepted!", $approve_sms, $approve_subject, $approve_html);
            }
            
            // Also notify Admin at ohatiwebsite@gmail.com on booking status updates
            send_admin_activity_notification("Booking Status Update (Ref: #OHT-B" . str_pad($bid, 5, '0', STR_PAD_LEFT) . ")", "<p>Booking <strong>#OHT-B" . str_pad($bid, 5, '0', STR_PAD_LEFT) . "</strong> (Client: " . htmlspecialchars($booking_info['user_name']) . ", Vendor: " . htmlspecialchars($booking_info['vendor_name']) . ") status has been updated to <strong>" . htmlspecialchars($input['status']) . "</strong> by {$sender_name}.</p>");
        }
        if (isset($input['payment_status'])) {
            add_notification($pdo, $recipient_id, "Payment Status Update", "Payment status for booking #{$bid} is now '{$input['payment_status']}'.");
        }
    }
    echo json_encode(['success'=>true]);
    break;

// ── FAVORITES ──────────────────────────────────────────────────────────
case 'toggle_favorite':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['vendor_id'] ?? 0);
    if ($vid <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid ID']); exit; }
    $key = array_search($vid, $_SESSION['favorites']);
    if ($key !== false) { unset($_SESSION['favorites'][$key]); $_SESSION['favorites'] = array_values($_SESSION['favorites']); $is_fav = false; }
    else { $_SESSION['favorites'][] = $vid; $is_fav = true;
        $cat_s = $pdo->prepare("SELECT category FROM vendors WHERE id = ?"); $cat_s->execute([$vid]);
        $cat = $cat_s->fetchColumn(); if ($cat) unlock_category_milestones($pdo, $cat, $_SESSION['user']['id'] ?? 0);
    }
    echo json_encode(['success'=>true,'is_favorite'=>$is_fav]);
    break;

case 'favorites':
    if (empty($_SESSION['favorites'])) { echo json_encode([]); exit; }
    $ph = implode(',', array_fill(0, count($_SESSION['favorites']), '?'));
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id IN ($ph)"); $stmt->execute($_SESSION['favorites']);
    $vs = $stmt->fetchAll();
    foreach ($vs as &$v) { $v['packages_pricing'] = json_decode($v['packages_pricing']??'[]',true)?:[]; $v['social_links'] = json_decode($v['social_links']??'{}',true)?:[]; $v['gallery'] = json_decode($v['gallery']??'[]',true)?:[]; $v['is_favorite'] = true; }
    echo json_encode($vs);
    break;

case 'get_vendor_followers':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $vendor_id = intval($_GET['vendor_id'] ?? $_SESSION['user']['vendor_id'] ?? 0);
    if ($vendor_id <= 0) {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$_SESSION['user']['id']]);
        $vendor_id = intval($v_stmt->fetchColumn() ?: 0);
    }
    if ($vendor_id <= 0) {
        echo json_encode(['followers' => [], 'count' => 0]);
        exit;
    }
    $f_stmt = $pdo->prepare("SELECT u.id as user_id, u.name, u.email, u.phone, u.avatar, f.created_at FROM favorites f JOIN users u ON f.user_id = u.id WHERE f.vendor_id = ? ORDER BY f.id DESC");
    $f_stmt->execute([$vendor_id]);
    $followers = $f_stmt->fetchAll();
    echo json_encode(['followers' => $followers, 'count' => count($followers)]);
    break;

case 'get_vendor_following':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $uid = intval($_SESSION['user']['id']);
    $f_stmt = $pdo->prepare("SELECT v.* FROM favorites f JOIN vendors v ON f.vendor_id = v.id WHERE f.user_id = ? ORDER BY f.id DESC");
    $f_stmt->execute([$uid]);
    $following = $f_stmt->fetchAll();
    foreach ($following as &$v) {
        $v['packages_pricing'] = json_decode($v['packages_pricing'] ?? '[]', true) ?: [];
        $v['gallery'] = json_decode($v['gallery'] ?? '[]', true) ?: [];
    }
    echo json_encode(['following' => $following, 'count' => count($following)]);
    break;

// ── COMPARE ────────────────────────────────────────────────────────────
case 'toggle_compare':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['vendor_id'] ?? 0);
    $key = array_search($vid, $_SESSION['compare']);
    if ($key !== false) { unset($_SESSION['compare'][$key]); $_SESSION['compare'] = array_values($_SESSION['compare']); }
    else { if (count($_SESSION['compare']) >= 4) { http_response_code(400); echo json_encode(['error'=>'Max 4 vendors to compare.']); exit; } $_SESSION['compare'][] = $vid; }
    echo json_encode(['success'=>true,'compare_list'=>$_SESSION['compare']]);
    break;

case 'compare_list':
    if (empty($_SESSION['compare'])) { echo json_encode([]); exit; }
    $ph = implode(',', array_fill(0, count($_SESSION['compare']), '?'));
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id IN ($ph)"); $stmt->execute($_SESSION['compare']);
    $vs = $stmt->fetchAll();
    foreach ($vs as &$v) { $v['packages_pricing'] = json_decode($v['packages_pricing']??'[]',true)?:[]; $v['gallery'] = json_decode($v['gallery']??'[]',true)?:[]; }
    echo json_encode($vs);
    break;

// ── REVIEWS ────────────────────────────────────────────────────────────
case 'review':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['vendor_id'] ?? 0);
    $uname = clean($input['user_name'] ?? '');
    $rating = intval($input['rating'] ?? 5);
    $comment = clean($input['comment'] ?? '');
    if ($vid <= 0 || empty($uname) || empty($comment)) { http_response_code(400); echo json_encode(['error'=>'Fields required.']); exit; }
    $now_stamp = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO reviews (vendor_id,user_name,rating,comment,date,created_at) VALUES (?,?,?,?,?,?)")->execute([$vid,$uname,$rating,$comment,date('F d, Y'),$now_stamp]);
    $avg = $pdo->prepare("SELECT AVG(rating) as ar, COUNT(*) as rc FROM reviews WHERE vendor_id = ?"); $avg->execute([$vid]); $res = $avg->fetch();
    $pdo->prepare("UPDATE vendors SET rating = ?, reviews_count = ? WHERE id = ?")->execute([round($res['ar'],1),$res['rc'],$vid]);
    echo json_encode(['success'=>true,'new_rating'=>round($res['ar'],1)]);
    break;

case 'submit_platform_review':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $name = clean($input['name'] ?? '');
    $rating = intval($input['rating'] ?? 5);
    $comment = clean($input['comment'] ?? '');
    if (empty($name) || empty($comment)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and review content are required.']);
        exit;
    }
    $avatar = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?q=80&w=80';
    if (isset($_SESSION['user']) && !empty($_SESSION['user']['avatar'])) {
        $avatar = $_SESSION['user']['avatar'];
    }

    $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = 'pending_platform_reviews'");
    $stmt->execute();
    $pending_json = $stmt->fetchColumn();
    $pending = $pending_json ? json_decode($pending_json, true) : [];

    $pending[] = [
        'id' => time() . '_' . rand(100, 999),
        'name' => $name,
        'rating' => $rating,
        'comment' => $comment,
        'avatar' => $avatar,
        'date' => date('F d, Y')
    ];

    $stmt = $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES ('pending_platform_reviews', ?) ON DUPLICATE KEY UPDATE val_value = ?");
    $stmt->execute([json_encode($pending), json_encode($pending)]);

    echo json_encode(['success' => true, 'message' => 'Review submitted successfully. It will appear once approved by an administrator.']);
    break;


// ── CHAT ───────────────────────────────────────────────────────────────
case 'chat_inbox':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'] ?? 'customer';
    if ($role === 'vendor') {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vendor_id = $v_stmt->fetchColumn();
        if ($vendor_id) {
            $stmt = $pdo->prepare("SELECT DISTINCT u.id as customer_id, u.name, u.avatar, 'Customer' as category, 'Online' as availability FROM messages m JOIN users u ON m.user_id = u.id WHERE m.vendor_id = ? ORDER BY m.id DESC");
            $stmt->execute([$vendor_id]);
        } else {
            echo json_encode([]); exit;
        }
    } else {
        $stmt = $pdo->prepare("SELECT DISTINCT v.id, v.name, v.logo, v.category, v.availability, v.verified, v.verification_badge FROM messages m JOIN vendors v ON m.vendor_id = v.id WHERE m.user_id = ? ORDER BY m.id DESC");
        $stmt->execute([$uid]);
    }
    echo json_encode($stmt->fetchAll());
    break;

case 'get_unread_chats':
    if (!isset($_SESSION['user'])) {
        echo json_encode([]);
        exit;
    }
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'] ?? 'customer';
    if ($role === 'vendor') {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vendor_id = $v_stmt->fetchColumn();
        if ($vendor_id) {
            $stmt = $pdo->prepare("SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.user_id = u.id WHERE m.vendor_id = ? AND m.sender = 'user' AND m.is_read = 0 ORDER BY m.id DESC");
            $stmt->execute([$vendor_id]);
            echo json_encode($stmt->fetchAll());
        } else {
            echo json_encode([]);
        }
    } else {
        $stmt = $pdo->prepare("SELECT m.*, v.name as sender_name FROM messages m JOIN vendors v ON m.vendor_id = v.id WHERE m.user_id = ? AND m.sender = 'vendor' AND m.is_read = 0 ORDER BY m.id DESC");
        $stmt->execute([$uid]);
        echo json_encode($stmt->fetchAll());
    }
    break;

case 'chat_history':
    $vid = intval($_GET['vendor_id'] ?? 0);
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'] ?? 'customer';
    if ($role === 'vendor') {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vendor_id = $v_stmt->fetchColumn();
        $cust_id = intval($_GET['customer_id'] ?? $_GET['vendor_id'] ?? 0);
        if ($vendor_id && $cust_id) {
            // Mark incoming customer messages as read
            $pdo->prepare("UPDATE messages SET is_read = 1 WHERE vendor_id = ? AND user_id = ? AND sender = 'user' AND is_read = 0")->execute([$vendor_id, $cust_id]);
            
            $stmt = $pdo->prepare("SELECT * FROM messages WHERE vendor_id = ? AND user_id = ? ORDER BY id ASC");
            $stmt->execute([$vendor_id, $cust_id]);
        } else {
            echo json_encode([]); exit;
        }
    } else {
        if ($vid <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid ID']); exit; }
        // Mark incoming vendor messages as read
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE vendor_id = ? AND user_id = ? AND sender = 'vendor' AND is_read = 0")->execute([$vid, $uid]);
        
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE vendor_id = ? AND user_id = ? ORDER BY id ASC");
        $stmt->execute([$vid, $uid]);
    }
    $msgs = $stmt->fetchAll();
    if (empty($msgs) && $role !== 'vendor') {
        $vn = $pdo->prepare("SELECT name, welcome_message FROM vendors WHERE id = ?");
        $vn->execute([$vid]);
        $v_row = $vn->fetch();
        $name = $v_row['name'] ?? 'this vendor';
        $welcome = !empty($v_row['welcome_message']) ? $v_row['welcome_message'] : "Hello! Thank you for reaching out to $name. How can we help you plan your event?";
        
        $pdo->prepare("INSERT INTO messages (vendor_id,user_id,sender,message,type) VALUES (?,?,'vendor',?,'text')")->execute([$vid,$uid,$welcome]);
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE vendor_id = ? AND user_id = ? ORDER BY id ASC");
        $stmt->execute([$vid, $uid]);
        $msgs = $stmt->fetchAll();
    }
    echo json_encode($msgs);
    break;

case 'chat':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['vendor_id'] ?? 0);
    $message = clean($input['message'] ?? '');
    $type = in_array($input['type'] ?? '', ['text','image','voice','pdf','location']) ? $input['type'] : 'text';
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'] ?? 'customer';
    
    if ($role === 'vendor') {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vendor_id = $v_stmt->fetchColumn();
        $cust_id = intval($input['customer_id'] ?? $input['vendor_id'] ?? 0);
        $now_stamp = date('Y-m-d H:i:s');
        if ($vendor_id && $cust_id && !empty($message)) {
            $pdo->prepare("INSERT INTO messages (vendor_id,user_id,sender,message,type,created_at) VALUES (?,?,'vendor',?,?,?)")->execute([$vendor_id,$cust_id,$message,$type,$now_stamp]);
            echo json_encode(['success'=>true,'vendor_message'=>['sender'=>'vendor','message'=>$message,'type'=>$type,'created_at'=>$now_stamp]]);
        } else {
            http_response_code(400); echo json_encode(['error'=>'Invalid target customer or vendor profile']);
        }
    } else {
        if ($vid <= 0 || empty($message)) { http_response_code(400); echo json_encode(['error'=>'Message required.']); exit; }
        $now_stamp = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO messages (vendor_id,user_id,sender,message,type,created_at) VALUES (?,?,'user',?,?,?)")->execute([$vid,$uid,$message,$type,$now_stamp]);
        require_once __DIR__ . '/ai_helper.php';
        $reply = generate_vendor_reply($vid, $message);
        if ($reply !== null && $reply !== '') {
            $reply_stamp = date('Y-m-d H:i:s');
            $pdo->prepare("INSERT INTO messages (vendor_id,user_id,sender,message,type,created_at) VALUES (?,?,'vendor',?,'text',?)")->execute([$vid,$uid,$reply,$reply_stamp]);
            echo json_encode([
                'success' => true,
                'user_message' => ['sender'=>'user','message'=>$message,'type'=>$type,'created_at'=>$now_stamp],
                'vendor_reply' => ['sender'=>'vendor','message'=>$reply,'type'=>'text','created_at'=>$reply_stamp]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'user_message' => ['sender'=>'user','message'=>$message,'type'=>$type,'created_at'=>$now_stamp],
                'vendor_reply' => null
            ]);
        }
    }
    break;

case 'upload_chat_file':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    if (!isset($_FILES['file'])) { http_response_code(400); echo json_encode(['error'=>'No file uploaded.']); exit; }
    
    $file = $_FILES['file'];
    
    // Validate size (20MB limit)
    $max_size = 20 * 1024 * 1024; // 20MB
    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['error'=>'File exceeds maximum size of 20MB.']);
        exit;
    }
    
    // Validate MIME type & extension
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/webm', 'audio/x-m4a', 'audio/aac', 'audio/3gpp',
        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'
    ];

    if (!in_array($mime, $allowed_mimes) && strpos($mime, 'image/') !== 0 && strpos($mime, 'audio/') !== 0 && strpos($mime, 'video/') !== 0) {
        http_response_code(400);
        echo json_encode(['error'=>'Invalid or unsupported file MIME type: ' . $mime]);
        exit;
    }

    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowed_audios = ['mp3', 'wav', 'ogg', 'm4a', 'webm', '3gp', 'aac'];
    $allowed_videos = ['mp4', 'webm', 'mov', 'avi'];
    $allowed_docs = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
    
    $type = 'text';
    if (strpos($filename, 'voicenote') !== false && in_array($ext, ['mp4', 'm4a', 'webm', 'ogg', 'wav', '3gp', 'aac', 'mp3'])) {
        $type = 'voice';
    } else if (in_array($ext, $allowed_images)) {
        $type = 'image';
    } else if (in_array($ext, $allowed_audios)) {
        $type = 'voice';
    } else if (in_array($ext, $allowed_videos)) {
        $type = 'video';
    } else if ($ext === 'pdf') {
        $type = 'pdf';
    } else if (in_array($ext, $allowed_docs)) {
        $type = 'location'; // Custom doc type mapped to text/doc placeholder
    } else {
        http_response_code(400);
        echo json_encode(['error'=>'File type not allowed.']);
        exit;
    }
    
    // Create directory if not exists
    $dir = __DIR__ . '/uploads/chat/';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Generate safe unique filename
    $new_filename = uniqid('chat_', true) . '.' . $ext;
    $target = $dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        if ($type === 'image') {
            compressAndResizeImage($target, $target, 1000, 1000, 75);
        }
        $relative_path = 'uploads/chat/' . $new_filename;
        echo json_encode([
            'success' => true,
            'url' => $relative_path,
            'type' => $type,
            'name' => $filename
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error'=>'Failed to save uploaded file.']);
    }
    break;

// ── WEBRTC REAL-TIME AUDIO & VIDEO CALLING ──────────────────────────────
case 'initiate_call':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $receiver_id = intval($input['receiver_id'] ?? 0);
    $type = in_array($input['type'] ?? '', ['voice', 'video']) ? $input['type'] : 'voice';
    $sdp_offer = $input['sdp_offer'] ?? '';
    $caller_id = intval($_SESSION['user']['id'] ?? 0);

    if ($caller_id <= 0 || $receiver_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid caller or receiver']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO calls (caller_id, receiver_id, type, status, sdp_offer) VALUES (?, ?, ?, 'dialing', ?)");
    $stmt->execute([$caller_id, $receiver_id, $type, $sdp_offer]);
    $call_id = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'call_id' => $call_id]);
    break;

case 'check_incoming_call':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    if ($uid <= 0) { echo json_encode(null); exit; }

    // Check for incoming call in dialing or connected state created within last 60 seconds
    $stmt = $pdo->prepare("SELECT c.*, u.name as caller_name, u.avatar as caller_avatar FROM calls c JOIN users u ON c.caller_id = u.id WHERE c.receiver_id = ? AND c.status IN ('dialing', 'connected') ORDER BY c.id DESC LIMIT 1");
    $stmt->execute([$uid]);
    $call = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($call ?: null);
    break;

case 'get_call_details':
    $call_id = intval($_GET['call_id'] ?? $_POST['call_id'] ?? 0);
    if ($call_id <= 0) { http_response_code(400); echo json_encode(['error' => 'Call ID required']); exit; }

    $stmt = $pdo->prepare("SELECT c.*, u1.name as caller_name, u1.avatar as caller_avatar, u2.name as receiver_name, u2.avatar as receiver_avatar FROM calls c JOIN users u1 ON c.caller_id = u1.id JOIN users u2 ON c.receiver_id = u2.id WHERE c.id = ?");
    $stmt->execute([$call_id]);
    $call = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($call ?: ['error' => 'Call not found']);
    break;

case 'answer_call':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $call_id = intval($input['call_id'] ?? 0);
    $sdp_answer = $input['sdp_answer'] ?? '';

    $stmt = $pdo->prepare("UPDATE calls SET status = 'connected', sdp_answer = ? WHERE id = ?");
    $stmt->execute([$sdp_answer, $call_id]);

    echo json_encode(['success' => true]);
    break;

case 'reject_call':
case 'end_call':
    $call_id = intval($_GET['call_id'] ?? $_POST['call_id'] ?? 0);
    if ($call_id <= 0) {
        $input = json_decode(file_get_contents('php://input'), true);
        $call_id = intval($input['call_id'] ?? 0);
    }
    $status = ($action === 'reject_call') ? 'rejected' : 'ended';
    if ($call_id > 0) {
        $pdo->prepare("UPDATE calls SET status = ? WHERE id = ?")->execute([$status, $call_id]);
    }
    echo json_encode(['success' => true]);
    break;

case 'send_ice_candidate':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $call_id = intval($input['call_id'] ?? 0);
    $candidate = $input['candidate'] ?? '';
    $role = $input['role'] ?? 'caller';

    if ($call_id > 0 && !empty($candidate)) {
        $column = ($role === 'caller') ? 'ice_candidates_caller' : 'ice_candidates_receiver';
        $stmt = $pdo->prepare("SELECT $column FROM calls WHERE id = ?");
        $stmt->execute([$call_id]);
        $existing = $stmt->fetchColumn() ?: '[]';
        $c_arr = json_decode($existing, true) ?: [];
        $c_arr[] = $candidate;

        $upd = $pdo->prepare("UPDATE calls SET $column = ? WHERE id = ?");
        $upd->execute([json_encode($c_arr), $call_id]);
    }
    echo json_encode(['success' => true]);
    break;

// ── TRACKER ────────────────────────────────────────────────────────────
case 'tracker_tasks':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $chosen = [];
    $sb = $pdo->prepare("SELECT DISTINCT v.category FROM bookings b JOIN vendors v ON b.vendor_id = v.id WHERE b.user_id = ?");
    $sb->execute([$uid]);
    $chosen = $sb->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($_SESSION['favorites'])) {
        $ph = implode(',', array_fill(0, count($_SESSION['favorites']), '?'));
        $sf = $pdo->prepare("SELECT DISTINCT category FROM vendors WHERE id IN ($ph)"); $sf->execute($_SESSION['favorites']);
        $chosen = array_unique(array_merge($chosen, $sf->fetchAll(PDO::FETCH_COLUMN)));
    }
    $q = "SELECT * FROM tracker_tasks WHERE user_id = ? AND (category = 'General' OR is_custom = 1";
    $p = [$uid];
    if (!empty($chosen)) {
        $ph = implode(',', array_fill(0, count($chosen), '?'));
        $q .= " OR category IN ($ph)";
        $p = array_merge($p, $chosen);
    }
    $q .= ") ORDER BY completed ASC, CASE WHEN priority='High' THEN 1 WHEN priority='Medium' THEN 2 ELSE 3 END ASC, estimated_date ASC";
    $stmt = $pdo->prepare($q); $stmt->execute($p);
    echo json_encode($stmt->fetchAll());
    break;

case 'add_task':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $name = clean($input['task_name'] ?? '');
    if (empty($name)) { http_response_code(400); echo json_encode(['error'=>'Task name required.']); exit; }
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $pdo->prepare("INSERT INTO tracker_tasks (user_id,task_name,category,priority,estimated_date,completed,notes,is_custom,cost,paid_amount,due_date) VALUES (?,?,'General',?,?,0,?,1,?,?,?)")
        ->execute([$uid, $name, clean($input['priority']??'Medium'), clean($input['estimated_date']??date('Y-m-d',strtotime('+30 days'))), clean($input['notes']??''), floatval($input['cost']??0), floatval($input['paid_amount']??0), clean($input['due_date']??'')]);
    echo json_encode(['success'=>true,'task_id'=>$pdo->lastInsertId()]);
    break;

case 'update_task':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    $uid = intval($_SESSION['user']['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid ID']); exit; }
    $fields = []; $params = [];
    foreach (['completed','notes','priority','estimated_date','cost','paid_amount','due_date'] as $f) {
        if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
    }
    if (!empty($fields)) {
        $params[] = $id;
        $params[] = $uid;
        $pdo->prepare("UPDATE tracker_tasks SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?")->execute($params);
    }
    echo json_encode(['success'=>true]);
    break;

case 'delete_task':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    $uid = intval($_SESSION['user']['id'] ?? 0);
    if ($id > 0) $pdo->prepare("DELETE FROM tracker_tasks WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
    echo json_encode(['success'=>true]);
    break;

case 'tracker_stats':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $chosen = [];
    $sb = $pdo->prepare("SELECT DISTINCT v.category FROM bookings b JOIN vendors v ON b.vendor_id = v.id WHERE b.user_id = ?");
    $sb->execute([$uid]);
    $chosen = $sb->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($_SESSION['favorites'])) {
        $ph = implode(',', array_fill(0, count($_SESSION['favorites']), '?'));
        $sf = $pdo->prepare("SELECT DISTINCT category FROM vendors WHERE id IN ($ph)"); $sf->execute($_SESSION['favorites']);
        $chosen = array_unique(array_merge($chosen, $sf->fetchAll(PDO::FETCH_COLUMN)));
    }
    $q = "SELECT completed, estimated_date, cost, paid_amount FROM tracker_tasks WHERE user_id = ? AND (category = 'General' OR is_custom = 1";
    $p = [$uid];
    if (!empty($chosen)) {
        $ph = implode(',', array_fill(0, count($chosen), '?'));
        $q .= " OR category IN ($ph)";
        $p = array_merge($p, $chosen);
    }
    $q .= ")";
    $stmt = $pdo->prepare($q); $stmt->execute($p); $tasks = $stmt->fetchAll();
    $total = count($tasks); $completed = 0; $overdue = 0; $upcoming = 0; $tc = 0; $tp = 0; $today = date('Y-m-d');
    foreach ($tasks as $t) { $tc += floatval($t['cost']); $tp += floatval($t['paid_amount']); if ($t['completed']) $completed++; else { if ($t['estimated_date'] < $today) $overdue++; else $upcoming++; } }

    // Include real active/confirmed user bookings in total cost and paid calculation
    $b_stmt = $pdo->prepare("SELECT negotiated_price, price, total_paid FROM bookings WHERE user_id = ? AND status != 'Cancelled'");
    $b_stmt->execute([$uid]);
    $user_bookings = $b_stmt->fetchAll();
    foreach ($user_bookings as $ub) {
        $cost_val = floatval($ub['negotiated_price'] > 0 ? $ub['negotiated_price'] : $ub['price']);
        $tc += $cost_val;
        $tp += floatval($ub['total_paid']);
    }

    $pct = $total > 0 ? round(($completed/$total)*100) : 0;
    
    $ev_stmt = $pdo->prepare("SELECT * FROM user_event WHERE user_id = ? LIMIT 1");
    $ev_stmt->execute([$uid]);
    $ev = $ev_stmt->fetch();
    $eb = $ev ? floatval($ev['estimated_budget']) : 0;
    echo json_encode(['total'=>$total,'completed'=>$completed,'overdue'=>$overdue,'upcoming'=>$upcoming,'percentage'=>$pct,'budget'=>['estimated'=>$eb,'total_cost'=>$tc,'total_paid'=>$tp,'remaining'=>$eb-$tc,'outstanding'=>$tc-$tp]]);
    break;

case 'update_event_budget':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $budget = floatval($input['budget'] ?? 0);
    if ($uid > 0) {
        $pdo->prepare("UPDATE user_event SET estimated_budget = ? WHERE user_id = ?")->execute([$budget, $uid]);
    }
    echo json_encode(['success'=>true]);
    break;

// ── EVENTS ─────────────────────────────────────────────────────────────
case 'save_event':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $pdo->prepare("DELETE FROM user_event WHERE user_id = ?")->execute([$uid]);
    $pdo->prepare("INSERT INTO user_event (user_id,event_name,event_type,event_date,start_time,end_time,location,region,city,indoor_outdoor,estimated_budget,guest_count,theme,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$uid,clean($input['event_name']??'My Event'),clean($input['event_type']??'Wedding'),clean($input['event_date']??''),clean($input['start_time']??''),clean($input['end_time']??''),clean($input['location']??''),clean($input['region']??''),clean($input['city']??''),clean($input['indoor_outdoor']??''),floatval($input['estimated_budget']??0),intval($input['guest_count']??0),clean($input['theme']??''),clean($input['notes']??'')]);
    if (!empty($input['event_date'])) generate_event_checklist($pdo, $input['event_type']??'Wedding', $input['event_date'], $uid);
    if (!empty($input['services_needed'])) { foreach ($input['services_needed'] as $cat) { unlock_category_milestones($pdo, $cat, $uid); } }
    echo json_encode(['success'=>true]);
    break;

case 'get_event':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM user_event WHERE user_id = ? LIMIT 1");
    $stmt->execute([$uid]);
    $ev = $stmt->fetch();
    echo json_encode($ev ?: null);
    break;

case 'reset_event':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $pdo->prepare("DELETE FROM user_event WHERE user_id = ?")->execute([$uid]);
    $pdo->prepare("DELETE FROM tracker_tasks WHERE user_id = ?")->execute([$uid]);
    echo json_encode(['success'=>true]);
    break;

// ── NOTIFICATIONS ──────────────────────────────────────────────────────
case 'notifications':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute([$uid]);
    $notifs = $stmt->fetchAll();
    foreach ($notifs as &$n) {
        if (empty($n['created_at'])) {
            $n['created_at'] = date('Y-m-d H:i:s');
        }
    }
    echo json_encode($notifs);
    break;

case 'mark_notification_read':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $nid = intval($input['id'] ?? 0);
    $uid = intval($_SESSION['user']['id'] ?? 0);
    if ($nid > 0) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$nid, $uid]);
    } else {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
    }
    echo json_encode(['success'=>true]);
    break;

// ── VENDOR REGISTRATION ───────────────────────────────────────────────
case 'register_vendor':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    check_idempotency_lock('register_vendor', 3);
    $input = json_decode(file_get_contents('php://input'), true);
    $name = clean($input['business_name'] ?? '');
    $category = clean($input['category'] ?? '');
    if (empty($name) || empty($category)) { http_response_code(400); echo json_encode(['error'=>'Business name and category required.']); exit; }
    $uid = isset($_SESSION['user']) ? intval($_SESSION['user']['id']) : 0;
    if ($uid <= 0 && (!empty($input['email']) || !empty($input['phone']))) {
        $vemail = clean($input['email'] ?? '');
        $vphone = clean($input['phone'] ?? '');
        $uStmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? AND email != '') OR (phone = ? AND phone != '') LIMIT 1");
        $uStmt->execute([$vemail, $vphone]);
        $uRow = $uStmt->fetch();
        if ($uRow) {
            $uid = intval($uRow['id']);
            $_SESSION['user'] = $uRow;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO vendors (user_id,name,category,description,location,phone,email,experience,verification_status,verification_badge) VALUES (?,?,?,?,?,?,?,?,'pending','grey')");
    $stmt->execute([$uid,$name,$category,clean($input['description']??''),clean($input['location']??''),clean($input['phone']??''),clean($input['email']??''),intval($input['experience']??0)]);
    $vendor_id = $pdo->lastInsertId();
    
    // Auto-update users role to vendor
    if ($uid > 0) {
        $pdo->prepare("UPDATE users SET role = 'vendor' WHERE id = ?")->execute([$uid]);
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['role'] = 'vendor';
            $_SESSION['user']['active_role'] = 'vendor';
            $_SESSION['user']['vendor_id'] = intval($vendor_id);
            $_SESSION['user']['has_vendor_profile'] = true;
        }
    }
    
    echo json_encode(['success'=>true,'vendor_id'=>$vendor_id]);
    break;

case 'update_vendor':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['id'] ?? 0);
    if ($vid <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid vendor ID']); exit; }
    
    // Verify ownership
    $check = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    $check->execute([$vid]);
    $owner_id = $check->fetchColumn();
    if (intval($owner_id) === 0 && isset($_SESSION['user']['id']) && intval($_SESSION['user']['id']) > 0) {
        $pdo->prepare("UPDATE vendors SET user_id = ? WHERE id = ?")->execute([intval($_SESSION['user']['id']), $vid]);
        $owner_id = $_SESSION['user']['id'];
    }
    if (intval($owner_id) !== intval($_SESSION['user']['id'] ?? 0) && !$is_admin) {
        http_response_code(403); echo json_encode(['error'=>'Unauthorized to update this vendor profile.']); exit;
    }
    
    // Fetch vendor details & lock sensitive fields for non-admins
    $v_stmt = $pdo->prepare("SELECT name, email, phone, account_number, premium FROM vendors WHERE id = ?");
    $v_stmt->execute([$vid]);
    $v_row = $v_stmt->fetch();
    $is_premium = intval($v_row['premium'] ?? 0);

    if (!$is_admin && $v_row) {
        if (!empty($v_row['name']) && isset($input['name'])) $input['name'] = $v_row['name'];
        if (!empty($v_row['email']) && isset($input['email'])) $input['email'] = $v_row['email'];
        if (!empty($v_row['phone']) && isset($input['phone'])) $input['phone'] = $v_row['phone'];
        if (!empty($v_row['account_number']) && isset($input['account_number'])) $input['account_number'] = $v_row['account_number'];
    }

    if (isset($input['gallery']) && is_array($input['gallery'])) {
        if (!$is_premium && !$is_admin) {
            $input['gallery'] = []; // Portfolio Gallery is locked exclusively to Premium vendors
        } else {
            $input['gallery'] = array_slice($input['gallery'], 0, 100); // Up to 100 high-res images for Premium vendors
        }
    }
    if (isset($input['social_links'])) {
        if (!$is_premium) {
            $input['social_links'] = [];
        }
    }

    
    // Save logo if base64
    if (isset($input['logo']) && strpos($input['logo'], 'data:image') === 0) {
        try {
            $input['logo'] = secure_save_base64_image($input['logo'], 'logos', 'logo_' . $vid);
        } catch (Exception $e) {
            http_response_code(400); echo json_encode(['error' => $e->getMessage()]); exit;
        }
    }

    // Save cover_photo if base64
    if (isset($input['cover_photo']) && strpos($input['cover_photo'], 'data:image') === 0) {
        try {
            $input['cover_photo'] = secure_save_base64_image($input['cover_photo'], 'covers', 'cover_' . $vid);
        } catch (Exception $e) {
            http_response_code(400); echo json_encode(['error' => $e->getMessage()]); exit;
        }
    }

    $fields = []; $params = [];
    foreach (['name','category','description','location','phone','email','whatsapp','website','logo','cover_photo','experience','service_radius','availability','instant_booking','business_reg','tax_number','bank_name','account_name','account_number','momo_number','momo_provider','payout_method','intro_video','welcome_message','auto_response','response_time','gps_lat','gps_lng','has_insurance'] as $f) {
        if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = is_string($input[$f]) ? clean($input[$f]) : $input[$f]; }
    }
    foreach (['packages_pricing','social_links','gallery','working_hours','team_members','faqs','languages','certifications','awards'] as $f) {
        if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = json_encode($input[$f]); }
    }
    if (!empty($fields)) { 
        $params[] = $vid; 
        $pdo->prepare("UPDATE vendors SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params); 
        
        // Sync logo back to user avatar
        if (isset($input['logo']) && !empty($input['logo']) && !empty($owner_id)) {
            try {
                $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$input['logo'], $owner_id]);
                if (isset($_SESSION['user'])) { $_SESSION['user']['avatar'] = $input['logo']; }
            } catch (Exception $eUsrLogo) {}
        }
    }
    echo json_encode(['success'=>true]);
    break;

case 'get_vendor_auto_response':
    $vendor_id = intval($_GET['vendor_id'] ?? 0);
    if ($vendor_id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid Vendor ID']); exit; }
    $stmt = $pdo->prepare("SELECT auto_response FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $res = $stmt->fetchColumn();
    echo json_encode(['auto_response' => $res ?: '']);
    break;

case 'set_vendor_auto_response':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $vendor_id = intval($input['vendor_id'] ?? 0);
    $auto_response = clean($input['auto_response'] ?? '');
    if ($vendor_id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid Vendor ID']); exit; }
    $stmt = $pdo->prepare("UPDATE vendors SET auto_response = ? WHERE id = ?");
    $stmt->execute([$auto_response, $vendor_id]);
    echo json_encode(['success' => true]);
    break;

// ── PAYMENTS ───────────────────────────────────────────────────────────
case 'record_payment':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $bid = intval($input['booking_id'] ?? 0);
    $amount = floatval($input['amount'] ?? 0);
    $method = clean($input['method'] ?? '');
    $type = in_array($input['type']??'', ['deposit','balance','full','installment']) ? $input['type'] : 'deposit';
    if ($bid <= 0 || $amount <= 0) { http_response_code(400); echo json_encode(['error'=>'Valid booking and amount required.']); exit; }
    $pdo->prepare("INSERT INTO payments (booking_id,amount,method,type,status,provider) VALUES (?,?,?,?,'completed',?)")->execute([$bid,$amount,$method,$type,clean($input['provider']??'')]);
    // Update booking totals
    $field = ($type === 'deposit') ? 'deposit_paid' : 'balance_paid';
    $pdo->prepare("UPDATE bookings SET $field = $field + ?, total_paid = total_paid + ? WHERE id = ?")->execute([$amount,$amount,$bid]);
    // Check if fully paid
    $bk = $pdo->prepare("SELECT price, total_paid FROM bookings WHERE id = ?"); $bk->execute([$bid]); $b = $bk->fetch();
    if ($b && $b['total_paid'] >= $b['price']) {
        $pdo->prepare("UPDATE bookings SET payment_status = 'Paid' WHERE id = ?")->execute([$bid]);
    } else {
        $pdo->prepare("UPDATE bookings SET payment_status = 'Partial' WHERE id = ?")->execute([$bid]);
    }
    echo json_encode(['success'=>true]);
    break;

case 'payment_history':
    $bid = intval($_GET['booking_id'] ?? 0);
    if ($bid > 0) {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC"); $stmt->execute([$bid]);
    } else {
        $stmt = $pdo->query("SELECT * FROM payments ORDER BY id DESC LIMIT 50");
    }
    echo json_encode($stmt->fetchAll());
    break;

case 'switch_role':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $role = ($input['role'] ?? '') === 'vendor' ? 'vendor' : 'customer';
    if ($role === 'vendor') {
        $stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $vendor = $stmt->fetch();
        if (!$vendor) {
            http_response_code(403);
            echo json_encode(['error'=>'Vendor profile not activated yet.', 'need_upgrade'=>true]);
            exit;
        }
        $_SESSION['user']['vendor_id'] = intval($vendor['id']);
        $_SESSION['user']['has_vendor_profile'] = true;
    }
    $_SESSION['user']['active_role'] = $role;
    echo json_encode(['success'=>true, 'active_role'=>$role, 'user'=>$_SESSION['user']]);
    break;

case 'submit_profile_change_request':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $field = clean($input['field_name'] ?? '');
    $new_val = clean($input['new_value'] ?? '');
    $doc = clean($input['supporting_document'] ?? '');
    
    $user_fields = ['name', 'email', 'phone', 'dob'];
    $vendor_fields = ['name', 'category', 'description', 'location', 'phone', 'email', 'experience', 'whatsapp', 'website', 'bank_name', 'account_name', 'account_number', 'momo_number', 'momo_provider', 'payout_method'];

    $old_val = '';
    if (in_array($field, $user_fields)) {
        $stmt = $pdo->prepare("SELECT $field FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $old_val = $stmt->fetchColumn() ?: '';
    } elseif (in_array($field, $vendor_fields)) {
        $stmt = $pdo->prepare("SELECT $field FROM vendors WHERE user_id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $old_val = $stmt->fetchColumn() ?: '';
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid profile field request.']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO profile_change_requests (user_id, field_name, old_value, new_value, supporting_document, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$_SESSION['user']['id'], $field, $old_val, $new_val, $doc]);
    echo json_encode(['success'=>true, 'message'=>'Change request submitted for admin approval.']);
    break;

case 'get_profile_change_requests':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    if ($is_admin) {
        $stmt = $pdo->query("SELECT r.*, u.name as user_name FROM profile_change_requests r JOIN users u ON r.user_id = u.id ORDER BY r.id DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$_SESSION['user']['id']]);
    }
    echo json_encode($stmt->fetchAll());
    break;

case 'admin_review_profile_change_request':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $req_id = intval($input['request_id'] ?? 0);
    $status = in_array($input['status'] ?? '', ['approved', 'rejected']) ? $input['status'] : 'rejected';
    $notes = clean($input['admin_notes'] ?? '');
    
    // Fetch request
    $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE id = ?");
    $stmt->execute([$req_id]);
    $req = $stmt->fetch();
    if (!$req) { http_response_code(404); echo json_encode(['error'=>'Request not found.']); exit; }
    
    $pdo->prepare("UPDATE profile_change_requests SET status = ?, admin_notes = ? WHERE id = ?")->execute([$status, $notes, $req_id]);
    
    if ($status === 'approved') {
        $field = $req['field_name'];
        if (in_array($field, ['name', 'email', 'phone', 'dob'])) {
            $upd = $pdo->prepare("UPDATE users SET $field = ? WHERE id = ?");
            $upd->execute([$req['new_value'], $req['user_id']]);
        } else {
            $upd = $pdo->prepare("UPDATE vendors SET $field = ? WHERE user_id = ?");
            $upd->execute([$req['new_value'], $req['user_id']]);
        }
        
        // Log activity
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $dev = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $log = $pdo->prepare("INSERT INTO profile_activity_log (user_id, field_name, old_value, new_value, device, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $log->execute([$req['user_id'], $field, $req['old_value'], $req['new_value'], $dev, $ip]);
        
        // Push system notification to user
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Profile Update Approved', ?, 'user-check')");
        $notif->execute([$req['user_id'], "Your request to change '$field' has been approved."]);
    } else {
        // Push rejection notification
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Profile Update Rejected', ?, 'user-xmark')");
        $notif->execute([$req['user_id'], "Your request to change '{$req['field_name']}' was rejected. Reason: $notes"]);
    }
    
    echo json_encode(['success'=>true]);
    break;

case 'get_profile_logs':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    if ($is_admin) {
        $stmt = $pdo->query("SELECT l.*, u.name as user_name FROM profile_activity_log l JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 100");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM profile_activity_log WHERE user_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$_SESSION['user']['id']]);
    }
    echo json_encode($stmt->fetchAll());
    break;

case 'get_advertisements':
case 'advertisements':
    // Automatically auto-expire old active campaigns before serving
    $now_date = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE advertisements SET status = 'expired' WHERE status = 'active' AND end_date < ?")->execute([$now_date]);

    $vid = intval($_GET['vendor_id'] ?? 0);
    $placement = clean($_GET['placement'] ?? '');
    
    if ($vid > 0) {
        $stmt = $pdo->prepare("SELECT * FROM advertisements WHERE vendor_id = ? ORDER BY id DESC");
        $stmt->execute([$vid]);
        echo json_encode($stmt->fetchAll());
        break;
    } else {
        // Query active, admin-approved ads that have not exceeded max_views or max_popups
        $q = "SELECT a.*, v.name as vendor_name, v.logo as vendor_logo, v.category as vendor_category 
              FROM advertisements a 
              JOIN vendors v ON a.vendor_id = v.id 
              WHERE a.status = 'active' 
                AND a.payment_status = 'paid'
                AND (a.start_date IS NULL OR a.start_date <= ?) 
                AND (a.end_date IS NULL OR a.end_date >= ?)
                AND (a.max_views = 0 OR a.views_count < a.max_views)
                AND (a.max_popups = 0 OR a.popup_count < a.max_popups)";
        $params = [$now_date, $now_date];

        if (!empty($placement)) {
            $q .= " AND (a.placement = ? OR a.placement = 'all')";
            $params[] = $placement;
        }

        $q .= " ORDER BY a.id DESC";

        $stmt = $pdo->prepare($q);
        $stmt->execute($params);
        $ads = $stmt->fetchAll();

        if (!empty($ads)) {
            $ids = array_map(fn($ad) => intval($ad['id']), $ads);
            $pdo->query("UPDATE advertisements SET impressions = impressions + 1, views_count = views_count + 1 WHERE id IN (" . implode(',', $ids) . ")");
        }
        echo json_encode($ads);
        break;
    }

case 'record_ad_popup':
    $aid = intval($_GET['ad_id'] ?? $_POST['ad_id'] ?? 0);
    if ($aid > 0) {
        $pdo->prepare("UPDATE advertisements SET popup_count = popup_count + 1 WHERE id = ?")->execute([$aid]);
        $stmt = $pdo->prepare("SELECT popup_count, max_popups FROM advertisements WHERE id = ?");
        $stmt->execute([$aid]);
        $row = $stmt->fetch();
        if ($row && intval($row['max_popups']) > 0 && intval($row['popup_count']) >= intval($row['max_popups'])) {
            $pdo->prepare("UPDATE advertisements SET status = 'expired' WHERE id = ?")->execute([$aid]);
        }
    }
    echo json_encode(['success' => true]);
    break;

case 'create_advertisement':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    
    $vid = intval($input['vendor_id'] ?? 0);
    $title = clean($input['title'] ?? '');
    $desc = clean($input['description'] ?? '');
    $banner = clean($input['banner_url'] ?? 'img/ads/default.jpg');
    $placement = clean($input['placement'] ?? 'home_top_banner');
    $days = intval($input['duration_days'] ?? 30);
    $cost = floatval($input['cost'] ?? 0);
    $payment_method = clean($input['payment_method'] ?? 'manual');

    if ($vid <= 0 || empty($title) || $cost <= 0) {
        http_response_code(400); echo json_encode(['error'=>'Invalid ad detail parameters or cost.']); exit;
    }

    if (strpos($input['banner_url'] ?? '', 'data:image') === 0) {
        try {
            $banner = secure_save_base64_image($input['banner_url'], 'ads', 'ad_' . $vid);
        } catch (Exception $e) {
            http_response_code(400); echo json_encode(['error' => $e->getMessage()]); exit;
        }
    }

    $receipt = '';
    if (!empty($input['receipt_data']) && strpos($input['receipt_data'], 'data:') === 0) {
        try {
            $receipt = secure_save_base64_image($input['receipt_data'], 'receipts', 'receipt_' . $vid . '_' . time());
        } catch (Exception $reEx) {}
    }
    $ref = clean($input['payment_ref'] ?? '');
    $pdate = clean($input['payment_date'] ?? date('Y-m-d'));
    $pnotes = clean($input['payment_notes'] ?? '');

    $start = date('Y-m-d H:i:s');
    $end = date('Y-m-d H:i:s', strtotime("+$days days"));

    // ALL submissions enter pending_approval status until admin approves
    $status = 'pending_approval';
    $pstatus = 'pending';

    $stmt = $pdo->prepare("INSERT INTO advertisements (vendor_id, title, description, banner_url, placement, duration_days, cost, start_date, end_date, status, payment_status, payment_method, payment_ref, receipt_url, payment_date, payment_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$vid, $title, $desc, $banner, $placement, $days, $cost, $start, $end, $status, $pstatus, $payment_method, $ref, $receipt, $pdate, $pnotes]);
    $ad_id = $pdo->lastInsertId();

    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Ad Campaign Submitted', ?, 'clock')");
    $notif->execute([$_SESSION['user']['id'], "Your campaign '$title' has been submitted and is waiting for admin approval."]);

    try {
        require_once __DIR__ . '/mail_helper.php';
        send_admin_activity_notification(
            "New Ad Campaign Submission (" . htmlspecialchars($title) . ")",
            "<p>Vendor <strong>Vendor #" . $vid . "</strong> has submitted a new <strong>" . htmlspecialchars($placement) . "</strong> ad campaign for GH₵ " . number_format($cost, 2) . ".</p><p>Please review and approve in Admin Console.</p>"
        );
    } catch (Exception $adminEx) {}

    echo json_encode(['success' => true, 'ad_id' => $ad_id, 'message' => 'Campaign submitted for admin review']);
    break;

case 'update_ad_status':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $aid = intval($input['id'] ?? 0);
    $status = in_array($input['status'] ?? '', ['active', 'paused', 'expired']) ? $input['status'] : 'active';
    $pdo->prepare("UPDATE advertisements SET status = ? WHERE id = ?")->execute([$status, $aid]);
    echo json_encode(['success'=>true]);
    break;

case 'record_ad_click':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $aid = intval($input['id'] ?? 0);
    $pdo->prepare("UPDATE advertisements SET clicks = clicks + 1 WHERE id = ?")->execute([$aid]);
    echo json_encode(['success'=>true]);
    break;

case 'submit_premium_request':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    
    $uid = intval($_SESSION['user']['id']);
    $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $v_stmt->execute([$uid]);
    $vid = intval($v_stmt->fetchColumn() ?? 0);
    
    if ($vid <= 0) { http_response_code(400); echo json_encode(['error'=>'Vendor profile not found.']); exit; }
    
    $notes = clean($input['payment_notes'] ?? '');
    $ref = clean($input['payment_ref'] ?? 'Inquiry Mode');
    $amount = floatval($input['amount'] ?? 150);
    
    $stmt = $pdo->prepare("INSERT INTO premium_requests (vendor_id, amount, receipt_url, payment_ref, payment_date, payment_notes, status) VALUES (?, ?, '', ?, ?, ?, 'pending')");
    $stmt->execute([$vid, $amount, $ref, date('Y-m-d'), $notes]);
    
    // Notify admins
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    foreach ($admins as $admin) {
        add_notification($pdo, $admin['id'], 'Premium Upgrade Request', "Vendor #$vid has requested premium status.");
    }
    
    echo json_encode(['success'=>true, 'message'=>'Premium request submitted successfully.']);
    break;

case 'follow_vendor':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['vendor_id'] ?? 0);
    $uid = intval($_SESSION['user']['id']);
    if ($vid <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid Vendor ID']); exit; }

    $check = $pdo->prepare("SELECT 1 FROM followers WHERE user_id = ? AND vendor_id = ?");
    $check->execute([$uid, $vid]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM followers WHERE user_id = ? AND vendor_id = ?")->execute([$uid, $vid]);
        $pdo->prepare("UPDATE vendors SET followers_count = CASE WHEN followers_count > 0 THEN followers_count - 1 ELSE 0 END WHERE id = ?")->execute([$vid]);
        echo json_encode(['success'=>true, 'followed'=>false]);
    } else {
        $pdo->prepare("INSERT INTO followers (user_id, vendor_id) VALUES (?, ?)")->execute([$uid, $vid]);
        $pdo->prepare("UPDATE vendors SET followers_count = followers_count + 1 WHERE id = ?")->execute([$vid]);
        echo json_encode(['success'=>true, 'followed'=>true]);
    }
    break;

case 'get_following_vendors':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $uid = intval($_SESSION['user']['id']);
    $stmt = $pdo->prepare("SELECT v.* FROM vendors v JOIN followers f ON f.vendor_id = v.id WHERE f.user_id = ?");
    $stmt->execute([$uid]);
    echo json_encode($stmt->fetchAll());
    break;

case 'get_recommended_vendors':
    $category = clean($_GET['category'] ?? '');
    $exclude_id = intval($_GET['exclude_id'] ?? 0);
    
    $q = "SELECT * FROM vendors WHERE is_active = 1 AND (verification_status = 'verified' OR verification_status IS NULL OR verification_status = '') AND id != ?";
    $params = [$exclude_id];
    if ($category) {
        $q .= " AND category = ?";
        $params[] = $category;
    }
    $q .= " ORDER BY featured DESC, premium DESC, verified DESC, rating DESC, completed_jobs DESC, views_count DESC LIMIT 6";
    $stmt = $pdo->prepare($q);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    break;

case 'get_trusted_vendors':
    $stmt = $pdo->query("SELECT * FROM vendors WHERE is_active = 1 AND verified = 1 AND (verification_status = 'verified' OR verification_status IS NULL OR verification_status = '') ORDER BY rating DESC, completed_jobs DESC, id DESC LIMIT 6");
    echo json_encode($stmt->fetchAll());
    break;

case 'get_popular_vendors':
    $stmt = $pdo->query("SELECT * FROM vendors WHERE is_active = 1 AND (verification_status = 'verified' OR verification_status IS NULL OR verification_status = '') ORDER BY views_count DESC, rating DESC, id DESC LIMIT 6");
    echo json_encode($stmt->fetchAll());
    break;

case 'renew_ad_campaign':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $aid = intval($input['id'] ?? 0);
    $days = intval($input['duration_days'] ?? 7);
    $cost = floatval($input['cost'] ?? 0);
    $payment_method = 'manual';
    $payment_ref = clean($input['payment_ref'] ?? '');
    $payment_notes = clean($input['payment_notes'] ?? '');
    $receipt_data = $input['receipt_data'] ?? '';

    if ($aid <= 0 || $cost <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid request parameters']); exit; }

    $ad_stmt = $pdo->prepare("SELECT * FROM advertisements WHERE id = ?");
    $ad_stmt->execute([$aid]);
    $ad = $ad_stmt->fetch();
    if (!$ad) { http_response_code(404); echo json_encode(['error'=>'Ad not found']); exit; }

    // Save receipt image
    $receipt_url = '';
    if (!empty($receipt_data)) {
        if (preg_match('/^data:image\/(\w+);base64,/', $receipt_data, $type)) {
            $data = substr($receipt_data, strpos($receipt_data, ',') + 1);
            $type = strtolower($type[1]);
            if (in_array($type, ['jpg', 'jpeg', 'png'])) {
                $data = base64_decode($data);
                if ($data !== false) {
                    if (!is_dir('uploads/receipts')) {
                        mkdir('uploads/receipts', 0777, true);
                    }
                    $filename = 'receipt_' . uniqid() . '.' . $type;
                    $path = 'uploads/receipts/' . $filename;
                    file_put_contents($path, $data);
                    $receipt_url = $path;
                }
            }
        }
    }

    $pdo->beginTransaction();
    try {
        $curr_end = strtotime($ad['end_date']);
        if ($curr_end < time()) {
            $new_start = date('Y-m-d H:i:s');
            $new_end = date('Y-m-d H:i:s', strtotime("+$days days"));
        } else {
            $new_start = $ad['start_date'];
            $new_end = date('Y-m-d H:i:s', strtotime("+$days days", $curr_end));
        }

        $pdo->prepare("UPDATE advertisements SET end_date = ?, duration_days = duration_days + ?, cost = cost + ?, status = 'pending_approval', payment_method = ?, payment_ref = ?, receipt_url = ?, payment_date = ?, payment_notes = ? WHERE id = ?")
            ->execute([$new_end, $days, $cost, $payment_method, $payment_ref, $receipt_url, date('Y-m-d'), $payment_notes, $aid]);
        
        $pdo->commit();

        // 1. Dual Email + SMS Notification to Admin
        try {
            $admin_email = defined('SMTP_USER') ? SMTP_USER : 'contact@ohati.com';
            $admin_phone = '0540477911';
            send_dual_notification(
                $admin_phone,
                $admin_email,
                "Ad Campaign Renewal Submitted",
                "Vendor '" . ($_SESSION['user']['name'] ?? 'Vendor') . "' submitted an Ad Renewal for campaign '" . $ad['title'] . "' ($days Days, GH₵ $cost). Log into Admin Console to review."
            );
        } catch (Exception $e1) {}

        // 2. Dual Email + SMS Notification to Vendor
        try {
            $v_phone = $_SESSION['user']['phone'] ?? '';
            $v_email = $_SESSION['user']['email'] ?? '';
            send_dual_notification(
                $v_phone,
                $v_email,
                "Ad Campaign Renewal Received",
                "Hello " . ($_SESSION['user']['name'] ?? 'Vendor') . ", your renewal request for campaign '" . $ad['title'] . "' has been received and is under admin review."
            );
        } catch (Exception $e2) {}

        echo json_encode(['success'=>true, 'message'=>'Renewal request & payment receipt submitted. Waiting for admin approval.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;

case 'get_vendor_analytics':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Authentication required']); exit; }
    
    $vid = intval($_GET['vendor_id'] ?? 0);
    $uid = intval($_SESSION['user']['id']);
    
    if ($vid <= 0) {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vid = intval($v_stmt->fetchColumn() ?: 0);
    }
    
    if ($vid <= 0) { http_response_code(400); echo json_encode(['error'=>'Vendor profile required']); exit; }

    $period = clean($_GET['period'] ?? '7days');
    $start_param = clean($_GET['start_date'] ?? '');
    $end_param = clean($_GET['end_date'] ?? '');

    if ($period === 'today') {
        $start_dt = date('Y-m-d 00:00:00');
        $end_dt = date('Y-m-d 23:59:59');
    } elseif ($period === '30days') {
        $start_dt = date('Y-m-d 00:00:00', strtotime('-30 days'));
        $end_dt = date('Y-m-d 23:59:59');
    } elseif ($period === 'this_month') {
        $start_dt = date('Y-m-01 00:00:00');
        $end_dt = date('Y-m-t 23:59:59');
    } elseif ($period === 'custom' && !empty($start_param) && !empty($end_param)) {
        $start_dt = date('Y-m-d 00:00:00', strtotime($start_param));
        $end_dt = date('Y-m-d 23:59:59', strtotime($end_param));
    } else {
        $period = '7days';
        $start_dt = date('Y-m-d 00:00:00', strtotime('-7 days'));
        $end_dt = date('Y-m-d 23:59:59');
    }

    // 1. Profile Views Count
    $v_cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM vendor_views_log WHERE vendor_id = ? AND created_at BETWEEN ? AND ?");
    $v_cnt_stmt->execute([$vid, $start_dt, $end_dt]);
    $views_count = intval($v_cnt_stmt->fetchColumn() ?: 0);

    // Fallback if views log count is 0
    if ($views_count === 0) {
        $tot_v = $pdo->prepare("SELECT views_count FROM vendors WHERE id = ?");
        $tot_v->execute([$vid]);
        $total_db_views = intval($tot_v->fetchColumn() ?: 0);
        if ($total_db_views > 0) {
            $views_count = ($period === 'today') ? max(1, round($total_db_views * 0.15)) : max(1, round($total_db_views * 0.7));
        }
    }

    // 2. Chat Inquiries Count
    $chat_cnt_stmt = $pdo->prepare("SELECT COUNT(DISTINCT sender_id) FROM messages WHERE (receiver_id = ? OR vendor_id = ?) AND created_at BETWEEN ? AND ?");
    $chat_cnt_stmt->execute([$uid, $vid, $start_dt, $end_dt]);
    $chats_count = intval($chat_cnt_stmt->fetchColumn() ?: 0);

    // 3. Bookings Count
    $bk_cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE (vendor_id = ? OR vendor_id = ?) AND created_at BETWEEN ? AND ?");
    $bk_cnt_stmt->execute([$vid, $uid, $start_dt, $end_dt]);
    $bookings_count = intval($bk_cnt_stmt->fetchColumn() ?: 0);

    // 4. Total Revenue
    $rev_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE (vendor_id = ? OR vendor_id = ?) AND status IN ('completed', 'approved', 'confirmed') AND created_at BETWEEN ? AND ?");
    $rev_stmt->execute([$vid, $uid, $start_dt, $end_dt]);
    $revenue = floatval($rev_stmt->fetchColumn() ?: 0);

    // 5. Followers Count
    $fol_stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE vendor_id = ?");
    $fol_stmt->execute([$vid]);
    $followers_count = intval($fol_stmt->fetchColumn() ?: 0);

    // 6. Rating & Reviews
    $rev_info = $pdo->prepare("SELECT COUNT(*) as rc, COALESCE(AVG(rating), 5.0) as ar FROM reviews WHERE vendor_id = ?");
    $rev_info->execute([$vid]);
    $r_row = $rev_info->fetch();

    echo json_encode([
        'success' => true,
        'period' => $period,
        'start_date' => substr($start_dt, 0, 10),
        'end_date' => substr($end_dt, 0, 10),
        'stats' => [
            'views' => $views_count,
            'chats' => $chats_count,
            'bookings' => $bookings_count,
            'revenue' => $revenue,
            'followers' => $followers_count,
            'reviews_count' => intval($r_row['rc'] ?? 0),
            'rating' => round(floatval($r_row['ar'] ?? 5.0), 1)
        ]
    ]);
    break;

case 'upgrade_ad_campaign':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $aid = intval($input['id'] ?? 0);
    $new_title = clean($input['title'] ?? '');
    $cost = floatval($input['cost'] ?? 0);
    $payment_method = 'manual';
    $payment_ref = clean($input['payment_ref'] ?? '');
    $payment_notes = clean($input['payment_notes'] ?? '');
    $receipt_data = $input['receipt_data'] ?? '';

    if ($aid <= 0 || $cost <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid request parameters']); exit; }

    $ad_stmt = $pdo->prepare("SELECT * FROM advertisements WHERE id = ?");
    $ad_stmt->execute([$aid]);
    $ad = $ad_stmt->fetch();
    if (!$ad) { http_response_code(404); echo json_encode(['error'=>'Ad not found']); exit; }

    // Save receipt image
    $receipt_url = '';
    if (!empty($receipt_data)) {
        if (preg_match('/^data:image\/(\w+);base64,/', $receipt_data, $type)) {
            $data = substr($receipt_data, strpos($receipt_data, ',') + 1);
            $type = strtolower($type[1]);
            if (in_array($type, ['jpg', 'jpeg', 'png'])) {
                $data = base64_decode($data);
                if ($data !== false) {
                    if (!is_dir('uploads/receipts')) {
                        mkdir('uploads/receipts', 0777, true);
                    }
                    $filename = 'receipt_' . uniqid() . '.' . $type;
                    $path = 'uploads/receipts/' . $filename;
                    file_put_contents($path, $data);
                    $receipt_url = $path;
                }
            }
        }
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE advertisements SET title = ?, cost = cost + ?, status = 'pending_approval', payment_method = ?, payment_ref = ?, receipt_url = ?, payment_date = ?, payment_notes = ? WHERE id = ?")
            ->execute([$new_title, $cost, $payment_method, $payment_ref, $receipt_url, date('Y-m-d'), $payment_notes, $aid]);
        
        $pdo->commit();
        echo json_encode(['success'=>true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;

case 'get_ad_analytics':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $vid = intval($_GET['vendor_id'] ?? 0);
    if ($vid <= 0) { http_response_code(400); echo json_encode(['error'=>'Vendor ID required']); exit; }

    $stmt = $pdo->prepare("SELECT SUM(impressions) as total_impressions, SUM(clicks) as total_clicks, COUNT(*) as total_campaigns FROM advertisements WHERE vendor_id = ?");
    $stmt->execute([$vid]);
    $summary = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM advertisements WHERE vendor_id = ? ORDER BY id DESC");
    $stmt->execute([$vid]);
    $campaigns = $stmt->fetchAll();

    echo json_encode([
        'total_impressions' => intval($summary['total_impressions'] ?? 0),
        'total_clicks' => intval($summary['total_clicks'] ?? 0),
        'total_campaigns' => intval($summary['total_campaigns'] ?? 0),
        'campaigns' => $campaigns
    ]);
    break;

case 'get_admin_campaigns':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $stmt = $pdo->query("SELECT a.*, v.name as vendor_name, v.logo as vendor_logo FROM advertisements a JOIN vendors v ON a.vendor_id = v.id ORDER BY a.id DESC");
    echo json_encode($stmt->fetchAll());
    break;

case 'get_bank_details':
    $stmt = $pdo->query("SELECT key_name, val_value FROM system_settings WHERE key_name LIKE 'admin_%'");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $details = [
        'bank_name' => $settings['admin_bank_name'] ?? 'Ecobank Ghana',
        'account_name' => $settings['admin_account_name'] ?? 'Ohati Global Digital Services',
        'account_number' => $settings['admin_account_number'] ?? '1441002939201',
        'momo_provider' => $settings['admin_momo_provider'] ?? 'MTN Mobile Money',
        'momo_number' => $settings['admin_momo_number'] ?? '0540477911',
        'momo_name' => $settings['admin_momo_name'] ?? 'Ohati Payments',
        'payment_instructions' => $settings['admin_payment_instructions'] ?? 'Please transfer the ad campaign fee to MTN MoMo (0540477911) or Ecobank Ghana (1441002939201). Upload your receipt screenshot and enter your transaction ID below.'
    ];
    echo json_encode(['success' => true, 'bank_details' => $details]);
    break;

case 'admin_review_campaign':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $ad_id = intval($input['ad_id'] ?? 0);
    $status = in_array($input['status'] ?? '', ['active', 'rejected']) ? $input['status'] : 'rejected';
    $notes = clean($input['admin_notes'] ?? '');

    $stmt = $pdo->prepare("SELECT a.*, v.user_id FROM advertisements a JOIN vendors v ON a.vendor_id = v.id WHERE a.id = ?");
    $stmt->execute([$ad_id]);
    $ad = $stmt->fetch();
    if (!$ad) { http_response_code(404); echo json_encode(['error'=>'Advertisement not found.']); exit; }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE advertisements SET status = ?, admin_notes = ? WHERE id = ?")->execute([$status, $notes, $ad_id]);

        if ($status === 'active') {
            // Update vendor featured status
            $pdo->prepare("UPDATE vendors SET featured = 1, feature_expires_at = ? WHERE id = ?")->execute([$ad['end_date'], $ad['vendor_id']]);
            
            // Get vendor user contact details
            $u_stmt = $pdo->prepare("SELECT phone, email FROM users WHERE id = ?");
            $u_stmt->execute([$ad['user_id']]);
            $u_info = $u_stmt->fetch();

            // Send Dual SMS and Email Notification
            send_dual_notification(
                $u_info['phone'] ?? '',
                $u_info['email'] ?? '',
                "Ad Campaign Approved & Promoted!",
                "Congratulations! Your Ohati Ad Campaign '" . $ad['title'] . "' has been approved by admin and is now live and promoted across the platform for " . $ad['duration_days'] . " days!"
            );

            // Send approved in-app notification
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Ad Campaign Approved', ?, 'rectangle-ad')");
            $notif->execute([$ad['user_id'], "Your ad campaign '" . $ad['title'] . "' has been approved by admin and is now active and promoted."]);
        } else {
            // Send rejected in-app notification
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Ad Campaign Rejected', ?, 'xmark')");
            $notif->execute([$ad['user_id'], "Your ad campaign '" . $ad['title'] . "' was rejected. Reason: $notes"]);
        }

        $pdo->commit();
        echo json_encode(['success'=>true, 'message'=>'Campaign status updated successfully.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400); echo json_encode(['error'=>$e->getMessage()]);
    }
    break;

case 'request_premium_upgrade':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $uid = intval($_SESSION['user']['id'] ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error'=>'Authentication required']); exit; }

    // Get vendor details
    $v_stmt = $pdo->prepare("SELECT id, name, category, phone, email FROM vendors WHERE user_id = ?");
    $v_stmt->execute([$uid]);
    $vendor = $v_stmt->fetch();
    if (!$vendor) { http_response_code(400); echo json_encode(['error'=>'Vendor profile required to request Premium Upgrade.']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    $tx_id = clean($input['transaction_ref'] ?? '');
    $receipt_b64 = $input['receipt_image'] ?? '';
    $amount = floatval($input['amount'] ?? 250.0);

    $receipt_path = '';
    if (!empty($receipt_b64) && strpos($receipt_b64, 'data:') === 0) {
        try {
            $receipt_path = secure_save_base64_image($receipt_b64, 'receipts', 'premium_receipt_' . $vendor['id'] . '_' . time());
        } catch (Exception $e) {}
    }

    $title = "Premium Gold Badge Upgrade - " . $vendor['name'];
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+365 days'));
    
    // Insert into advertisements table as premium upgrade request
    $stmt = $pdo->prepare("INSERT INTO advertisements (vendor_id, title, placement, duration_days, price, start_date, end_date, proof_of_payment, status, payment_status) VALUES (?, ?, 'premium_gold', 365, ?, ?, ?, ?, 'pending', 'pending')");
    $stmt->execute([$vendor['id'], $title, $amount, $start_date, $end_date, $receipt_path ?: $tx_id]);

    // 1. Dual Email + SMS Notification to Admin
    $admin_email = defined('SMTP_USER') ? SMTP_USER : 'contact@ohati.com';
    $admin_phone = '0540477911';
    
    send_dual_notification(
        $admin_phone,
        $admin_email,
        "New Premium Upgrade Payment Receipt",
        "Vendor '" . $vendor['name'] . "' requested a Premium Gold Badge Upgrade and uploaded a payment receipt (TxID: " . ($tx_id ?: 'Attached') . "). Please log into Admin Console to review."
    );

    // 2. Dual Email + SMS Notification to Vendor
    $v_phone = $vendor['phone'] ?: ($_SESSION['user']['phone'] ?? '');
    $v_email = $vendor['email'] ?: ($_SESSION['user']['email'] ?? '');
    
    send_dual_notification(
        $v_phone,
        $v_email,
        "Premium Upgrade Payment Receipt Received",
        "Hello " . $vendor['name'] . ", your payment receipt for Premium Gold Badge Upgrade has been received by Ohati Admin. Your request is being reviewed."
    );

    // In-app notification
    $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Premium Request Submitted', 'Your Premium Gold Badge payment receipt is under review by Admin.', 'crown')")->execute([$uid]);

    echo json_encode(['success' => true, 'message' => 'Payment receipt uploaded successfully! Admin will review and activate your Gold Badge.']);
    break;

case 'admin_update_vendor_premium':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['vendor_id'] ?? 0);
    $premium = intval($input['premium'] ?? 0);
    $expiry = clean($input['premium_expires_at'] ?? '');

    $stmt = $pdo->prepare("SELECT user_id, name FROM vendors WHERE id = ?");
    $stmt->execute([$vid]);
    $vendor = $stmt->fetch();
    if (!$vendor) { http_response_code(404); echo json_encode(['error'=>'Vendor not found.']); exit; }

    $pdo->prepare("UPDATE vendors SET premium = ?, premium_expires_at = ? WHERE id = ?")->execute([$premium, $expiry, $vid]);

    // Notify vendor
    if ($premium === 1) {
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Account Upgraded to Premium', ?, 'crown')");
        $notif->execute([$vendor['user_id'], "Congratulations! Your vendor profile has been upgraded to Premium membership until $expiry."]);
    } else {
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Premium Membership Cancelled', ?, 'user')");
        $notif->execute([$vendor['user_id'], "Your Premium vendor membership has ended."]);
    }

    echo json_encode(['success'=>true]);
    break;


// ── CALLING SYSTEM ENDPOINTS ─────────────────────────────────────────────
case 'initiate_call':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $receiver_id = intval($input['receiver_id'] ?? 0);
    $type = clean($input['type'] ?? 'voice');
    $sdp = $input['sdp_offer'] ?? '';

    if ($receiver_id <= 0 || empty($sdp)) {
        http_response_code(400); echo json_encode(['error'=>'Invalid request parameters.']); exit;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO calls (caller_id, receiver_id, type, status, sdp_offer, ice_candidates_caller, ice_candidates_receiver, created_at, updated_at) VALUES (?, ?, ?, 'ringing', ?, '[]', '[]', ?, ?)");
    $stmt->execute([$_SESSION['user']['id'], $receiver_id, $type, $sdp, $now, $now]);
    $call_id = $pdo->lastInsertId();

    echo json_encode(['success'=>true, 'call_id'=>$call_id]);
    break;

case 'check_incoming_call':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $uid = $_SESSION['user']['id'];
    
    // Look for active ringing calls (within last 30 seconds, database-agnostic)
    $time_limit = date('Y-m-d H:i:s', time() - 30);
    $stmt = $pdo->prepare("SELECT c.*, u.name as caller_name, u.avatar as caller_avatar FROM calls c JOIN users u ON c.caller_id = u.id WHERE c.receiver_id = ? AND c.status = 'ringing' AND c.created_at >= ? ORDER BY c.id DESC LIMIT 1");
    $stmt->execute([$uid, $time_limit]);
    $call = $stmt->fetch();
    
    echo json_encode($call ?: null);
    break;

case 'accept_call':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $call_id = intval($input['call_id'] ?? 0);
    $sdp = $input['sdp_answer'] ?? '';

    if ($call_id <= 0 || empty($sdp)) {
        http_response_code(400); echo json_encode(['error'=>'Invalid request parameters.']); exit;
    }

    $stmt = $pdo->prepare("UPDATE calls SET status = 'accepted', sdp_answer = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$sdp, date('Y-m-d H:i:s'), $call_id]);

    echo json_encode(['success'=>true]);
    break;

case 'update_call_status':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $call_id = intval($input['call_id'] ?? 0);
    $status = clean($input['status'] ?? 'ended');
    $duration = intval($input['duration'] ?? 0);

    $stmt = $pdo->prepare("UPDATE calls SET status = ?, duration = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$status, $duration, date('Y-m-d H:i:s'), $call_id]);

    echo json_encode(['success'=>true]);
    break;

case 'send_ice_candidate':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $call_id = intval($input['call_id'] ?? 0);
    $role = clean($input['role'] ?? 'caller');
    $candidate = $input['candidate'] ?? '';

    if ($call_id <= 0 || empty($candidate)) {
        http_response_code(400); echo json_encode(['error'=>'Invalid parameters.']); exit;
    }

    $field = ($role === 'caller') ? 'ice_candidates_caller' : 'ice_candidates_receiver';
    
    $pdo->beginTransaction();
    try {
        $get_stmt = $pdo->prepare("SELECT $field FROM calls WHERE id = ?");
        $get_stmt->execute([$call_id]);
        $existing = json_decode($get_stmt->fetchColumn() ?: '[]', true);
        
        $new_cand = json_decode($candidate, true);
        if (is_array($new_cand) && isset($new_cand[0])) {
            foreach ($new_cand as $c) {
                if ($c) {
                    $existing[] = $c;
                }
            }
        } else if ($new_cand) {
            $existing[] = $new_cand;
        }

        $up_stmt = $pdo->prepare("UPDATE calls SET $field = ?, updated_at = ? WHERE id = ?");
        $up_stmt->execute([json_encode($existing), date('Y-m-d H:i:s'), $call_id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500); echo json_encode(['error'=>'Database error.']); exit;
    }

    echo json_encode(['success'=>true]);
    break;

case 'get_call_details':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $cid = intval($_GET['call_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT c.*, u1.name as caller_name, u1.avatar as caller_avatar, u2.name as receiver_name, u2.avatar as receiver_avatar FROM calls c JOIN users u1 ON c.caller_id = u1.id JOIN users u2 ON c.receiver_id = u2.id WHERE c.id = ?");
    $stmt->execute([$cid]);
    
    echo json_encode($stmt->fetch() ?: null);
    break;

case 'get_call_history':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $uid = $_SESSION['user']['id'];
    
    $stmt = $pdo->prepare("SELECT c.*, u1.name as caller_name, u1.avatar as caller_avatar, u2.name as receiver_name, u2.avatar as receiver_avatar FROM calls c JOIN users u1 ON c.caller_id = u1.id JOIN users u2 ON c.receiver_id = u2.id WHERE c.caller_id = ? OR c.receiver_id = ? ORDER BY c.id DESC LIMIT 50");
    $stmt->execute([$uid, $uid]);
    
    echo json_encode($stmt->fetchAll());
    break;

case 'get_admin_ads':
    if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
    $stmt = $pdo->query("SELECT a.*, v.name as vendor_name FROM advertisements a JOIN vendors v ON a.vendor_id = v.id ORDER BY a.id DESC");
    echo json_encode($stmt->fetchAll());
    break;

case 'get_faqs':
    $stmt = $pdo->query("SELECT * FROM faqs ORDER BY category ASC, display_order ASC, id ASC");
    echo json_encode($stmt->fetchAll());
    break;

case 'add_faq':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!$is_admin) {
        http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $question = clean($input['question'] ?? '');
    $answer = $input['answer'] ?? ''; // Allow rich HTML content for answers
    $category = clean($input['category'] ?? 'General');
    $order = intval($input['display_order'] ?? 0);
    if (empty($question) || empty($answer)) {
        http_response_code(400); echo json_encode(['error'=>'Question and Answer are required.']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO faqs (category, question, answer, display_order) VALUES (?, ?, ?, ?)");
    $stmt->execute([$category, $question, $answer, $order]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
    break;

case 'update_faq':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!$is_admin) {
        http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    $question = clean($input['question'] ?? '');
    $answer = $input['answer'] ?? ''; // Allow rich HTML content for answers
    $category = clean($input['category'] ?? 'General');
    $order = intval($input['display_order'] ?? 0);
    if ($id <= 0 || empty($question) || empty($answer)) {
        http_response_code(400); echo json_encode(['error'=>'Invalid ID or missing question/answer.']); exit;
    }
    $stmt = $pdo->prepare("UPDATE faqs SET category = ?, question = ?, answer = ?, display_order = ? WHERE id = ?");
    $stmt->execute([$category, $question, $answer, $order, $id]);
    echo json_encode(['success'=>true]);
    break;

case 'delete_faq':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!$is_admin) {
        http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400); echo json_encode(['error'=>'Invalid ID.']); exit;
    }
    $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    break;

case 'get_vendor_auto_response':
    $vid = intval($_GET['vendor_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT auto_response FROM vendors WHERE id = ?");
    $stmt->execute([$vid]);
    echo json_encode(['auto_response' => $stmt->fetchColumn() ?: '']);
    break;

case 'set_vendor_auto_response':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['vendor_id'] ?? 0);
    $txt = clean($input['auto_response'] ?? '');
    $pdo->prepare("UPDATE vendors SET auto_response = ? WHERE id = ?")->execute([$txt, $vid]);
    echo json_encode(['success'=>true]);
    break;

// ── REPORT ISSUE ────────────────────────────────────────────────────────
case 'report_issue':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $uid = intval($_SESSION['user']['id'] ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error'=>'Please sign in to submit a report.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $title = clean($input['title'] ?? '');
    $category = clean($input['category'] ?? 'Other');
    $description = clean($input['description'] ?? '');
    if (empty($title) || empty($description)) {
        http_response_code(400); echo json_encode(['error'=>'Title and description are required.']); exit;
    }
    $screenshot_url = '';
    if (!empty($input['screenshot'])) {
        try {
            $screenshot_url = secure_save_base64_image($input['screenshot'], 'reports', 'report');
        } catch (Exception $e) {
            // Screenshot failed but report can still go through
            $screenshot_url = '';
        }
    }
    $stmt = $pdo->prepare("INSERT INTO reported_issues (user_id, title, category, description, screenshot_url, status) VALUES (?, ?, ?, ?, ?, 'open')");
    $stmt->execute([$uid, $title, $category, $description, $screenshot_url]);
    // Notify admins
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    foreach ($admins as $admin) {
        add_notification($pdo, $admin['id'], 'New Issue Report', "A user reported: $title ($category)");
    }
    echo json_encode(['success'=>true, 'message'=>'Report submitted successfully.']);
    break;

// ── REFER & EARN ────────────────────────────────────────────────────────
case 'get_referral_info':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error'=>'Please sign in to access Refer & Earn.']); exit; }
    
    // Ensure user has referral code
    $u_stmt = $pdo->prepare("SELECT referral_code, referral_balance FROM users WHERE id = ?");
    $u_stmt->execute([$uid]);
    $u_row = $u_stmt->fetch();
    $ref_code = $u_row['referral_code'] ?? '';
    if (empty($ref_code)) {
        $ref_code = 'OHATI-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$ref_code, $uid]);
        if (isset($_SESSION['user'])) $_SESSION['user']['referral_code'] = $ref_code;
    }
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    $currentDir = dirname($_SERVER['SCRIPT_NAME']);
    $currentDir = str_replace('\\', '/', $currentDir);
    if ($currentDir === '/') $currentDir = '';
    $base_url = $protocol . $domainName . $currentDir;
    $referral_link = $base_url . "/index.php?ref=" . urlencode($ref_code);

    // Fetch user referrals list
    $ref_list_stmt = $pdo->prepare("SELECT r.*, u.name as referred_name, u.created_at as joined_at FROM referrals r JOIN users u ON r.referred_id = u.id WHERE r.referrer_id = ? ORDER BY r.id DESC");
    $ref_list_stmt->execute([$uid]);
    $referrals_list = $ref_list_stmt->fetchAll();

    // Fetch settings
    $rew_val = 10.0;
    $prog_active = 1;
    try {
        $s1 = $pdo->query("SELECT val_value FROM system_settings WHERE key_name = 'referral_reward_amount'")->fetchColumn();
        if ($s1 !== false && $s1 !== null) $rew_val = floatval($s1);
        $s2 = $pdo->query("SELECT val_value FROM system_settings WHERE key_name = 'referral_program_active'")->fetchColumn();
        if ($s2 !== false && $s2 !== null) $prog_active = intval($s2);
    } catch (Exception $e) {}

    echo json_encode([
        'success' => true,
        'referral_code' => $ref_code,
        'referral_link' => $referral_link,
        'total_referrals' => count($referrals_list),
        'referral_balance' => floatval($u_row['referral_balance'] ?? 0),
        'reward_per_referral' => $rew_val,
        'program_active' => $prog_active === 1,
        'link_preview' => [
            'title' => "Join Ohati — Earn Rewards on Event Vendor Bookings!",
            'description' => "Sign up using my referral link ($ref_code) to join Ohati! Discover, compare, and book top event photographers, caterers, DJs & decorators across Ghana.",
            'logo' => $base_url . "/img/logo black transparent small.png"
        ],
        'referrals' => $referrals_list
    ]);
    break;

case 'admin_get_referrals':
    if (($_SESSION['admin_user']['role'] ?? '') !== 'admin' && ($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit;
    }
    $refs = $pdo->query("SELECT r.*, u1.name as referrer_name, u1.email as referrer_email, u2.name as referred_name, u2.email as referred_email FROM referrals r JOIN users u1 ON r.referrer_id = u1.id JOIN users u2 ON r.referred_id = u2.id ORDER BY r.id DESC")->fetchAll();
    
    $total_payouts = $pdo->query("SELECT SUM(reward_amount) FROM referrals WHERE status = 'completed'")->fetchColumn() ?: 0;
    $total_referrals_count = count($refs);

    $rew_val = 10.0;
    $prog_active = 1;
    try {
        $s1 = $pdo->query("SELECT val_value FROM system_settings WHERE key_name = 'referral_reward_amount'")->fetchColumn();
        if ($s1 !== false && $s1 !== null) $rew_val = floatval($s1);
        $s2 = $pdo->query("SELECT val_value FROM system_settings WHERE key_name = 'referral_program_active'")->fetchColumn();
        if ($s2 !== false && $s2 !== null) $prog_active = intval($s2);
    } catch (Exception $e) {}

    echo json_encode([
        'success' => true,
        'referrals' => $refs,
        'total_referrals' => $total_referrals_count,
        'total_payouts' => floatval($total_payouts),
        'reward_amount' => $rew_val,
        'program_active' => $prog_active === 1
    ]);
    break;

case 'admin_update_referral_settings':
    if (($_SESSION['admin_user']['role'] ?? '') !== 'admin' && ($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $reward_amt = floatval($input['reward_amount'] ?? 10.0);
    $active_state = intval($input['program_active'] ?? 1);

    $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES ('referral_reward_amount', ?) ON DUPLICATE KEY UPDATE val_value = ?")->execute([$reward_amt, $reward_amt]);
    $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES ('referral_program_active', ?) ON DUPLICATE KEY UPDATE val_value = ?")->execute([$active_state, $active_state]);

    echo json_encode(['success' => true, 'message' => 'Referral settings updated successfully.']);
    break;

case 'get_app_download_urls':
    $android_url = getSetting('android_download_url', 'https://play.google.com/store/apps/details?id=com.ohati.app');
    $ios_url = getSetting('ios_download_url', 'https://apps.apple.com/app/ohati/id123456789');
    $chat_support = getSetting('chat_support_number', '+233209001100');
    $site_phone = getSetting('site_phone', '+233 20 900 1100');
    $site_email = getSetting('site_email', 'hello@ohati.com');

    echo json_encode([
        'success' => true,
        'android_download_url' => $android_url,
        'ios_download_url' => $ios_url,
        'chat_support_number' => $chat_support,
        'site_phone' => $site_phone,
        'site_email' => $site_email
    ]);
    break;

case 'submit_discount_request':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Please log in to send a discount request.']); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    
    $uid = intval($_SESSION['user']['id']);
    $vid = intval($input['vendor_id'] ?? 0);
    $event_type = clean($input['event_type'] ?? 'General Event');
    $event_date = clean($input['event_date'] ?? '');
    $target_price = floatval($input['target_price'] ?? 0);
    $requested_discount_pct = floatval($input['requested_discount_pct'] ?? 10);
    $notes = clean($input['notes'] ?? '');

    if ($vid <= 0) { http_response_code(400); echo json_encode(['error'=>'Please select a vendor.']); exit; }

    $stmt = $pdo->prepare("INSERT INTO discount_requests (user_id, vendor_id, event_type, event_date, target_price, requested_discount_pct, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$uid, $vid, $event_type, $event_date, $target_price, $requested_discount_pct, $notes]);
    $req_id = $pdo->lastInsertId();

    // Fetch vendor & user details for notifications
    $v_stmt = $pdo->prepare("SELECT v.*, u.email as u_email, u.phone as u_phone FROM vendors v LEFT JOIN users u ON v.user_id = u.id WHERE v.id = ?");
    $v_stmt->execute([$vid]);
    $v_row = $v_stmt->fetch();

    $u_name = $_SESSION['user']['name'] ?? 'Customer';

    if ($v_row) {
        // Vendor in-app notification
        try {
            $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'New Discount Request! 🏷️', ?, 'percent')")
                ->execute([$v_row['user_id'], "{$u_name} submitted a discount request for {$event_type}. Offered Price: GH₵ " . number_format($target_price, 2)]);
        } catch (Exception $eNotif) {}

        // Vendor Dual Email + SMS notification
        try {
            $v_email = !empty($v_row['email']) ? $v_row['email'] : ($v_row['u_email'] ?? '');
            $v_phone = !empty($v_row['phone']) ? $v_row['phone'] : ($v_row['u_phone'] ?? '');
            send_dual_notification(
                $v_phone,
                $v_email,
                "New Discount Request Received",
                "Hello " . ($v_row['name'] ?? 'Vendor') . ", customer '{$u_name}' sent a custom discount request for '{$event_type}' (Target Price: GH₵ " . number_format($target_price, 2) . "). Log into your Vendor Portal to accept, counter, or decline."
            );
        } catch (Exception $eDual) {}
    }

    echo json_encode(['success' => true, 'message' => 'Your discount & offer request has been sent to the vendor! You will be notified when they respond.', 'id' => $req_id]);
    break;

case 'get_user_discount_requests':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Please log in']); exit; }
    $uid = intval($_SESSION['user']['id']);
    $stmt = $pdo->prepare("SELECT dr.*, v.name as vendor_name, v.category as vendor_category, v.logo as vendor_logo FROM discount_requests dr JOIN vendors v ON dr.vendor_id = v.id WHERE dr.user_id = ? ORDER BY dr.id DESC");
    $stmt->execute([$uid]);
    echo json_encode(['success' => true, 'requests' => $stmt->fetchAll()]);
    break;

case 'get_vendor_discount_requests':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Please log in']); exit; }
    $uid = intval($_SESSION['user']['id']);
    $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $v_stmt->execute([$uid]);
    $vid = intval($v_stmt->fetchColumn() ?: 0);

    $stmt = $pdo->prepare("SELECT dr.*, u.name as user_name, u.email as user_email, u.phone as user_phone, u.avatar as user_avatar FROM discount_requests dr JOIN users u ON dr.user_id = u.id WHERE dr.vendor_id = ? OR dr.vendor_id IN (SELECT id FROM vendors WHERE user_id = ?) ORDER BY dr.id DESC");
    $stmt->execute([$vid, $uid]);
    echo json_encode(['success' => true, 'requests' => $stmt->fetchAll()]);
    break;

case 'vendor_respond_discount_request':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Authentication required']); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);

    $req_id = intval($input['request_id'] ?? 0);
    $action = clean($input['action'] ?? '');
    $counter_price = floatval($input['counter_price'] ?? 0);
    $response_notes = clean($input['vendor_notes'] ?? '');

    $req_stmt = $pdo->prepare("SELECT dr.*, v.name as vendor_name, u.name as user_name, u.email as user_email, u.phone as user_phone FROM discount_requests dr JOIN vendors v ON dr.vendor_id = v.id JOIN users u ON dr.user_id = u.id WHERE dr.id = ?");
    $req_stmt->execute([$req_id]);
    $req = $req_stmt->fetch();

    if (!$req) { http_response_code(404); echo json_encode(['error'=>'Discount request not found']); exit; }

    $status = 'pending';
    $coupon_code = '';

    if ($action === 'approve') {
        $status = 'approved';
        $coupon_code = 'OFFER-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        try {
            $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, vendor_id, expiry_date, usage_limit) VALUES (?, 'fixed', ?, ?, ?, 1)")
                ->execute([$coupon_code, floatval($req['target_price']), $req['vendor_id'], date('Y-m-d', strtotime('+30 days'))]);
        } catch (Exception $eCoupon) {}
    } elseif ($action === 'counter') {
        $status = 'countered';
    } else {
        $status = 'declined';
    }

    $pdo->prepare("UPDATE discount_requests SET status = ?, vendor_response = ?, counter_price = ?, coupon_code = ? WHERE id = ?")
        ->execute([$status, $response_notes, $counter_price, $coupon_code, $req_id]);

    try {
        $u_email = $req['user_email'];
        $u_phone = $req['user_phone'];
        $u_name = $req['user_name'];
        $v_name = $req['vendor_name'];

        if ($action === 'approve') {
            $msg = "Hello $u_name, great news! Your discount request to '$v_name' was APPROVED! Use Coupon Code: $coupon_code at checkout to claim your discount.";
            send_dual_notification($u_phone, $u_email, "Discount Request Approved! 🎉", $msg);
        } elseif ($action === 'counter') {
            $msg = "Hello $u_name, '$v_name' has sent a counter-offer of GH₵ " . number_format($counter_price, 2) . " for your discount request. Log into Ohati to review.";
            send_dual_notification($u_phone, $u_email, "Counter Offer Received 🏷️", $msg);
        } else {
            $msg = "Hello $u_name, your discount request to '$v_name' was declined by the vendor.";
            send_dual_notification($u_phone, $u_email, "Discount Request Update", $msg);
        }
    } catch (Exception $eNotifUser) {}

    echo json_encode(['success' => true, 'status' => $status, 'coupon_code' => $coupon_code, 'message' => "Discount request status updated to $status."]);
    break;

// ── DISCOUNT OFFERS & COUPONS ──────────────────────────────────────────
case 'get_active_discounts':
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM discounts WHERE is_active = 1 AND (valid_until = '' OR valid_until >= ?) AND used_count < usage_limit ORDER BY id DESC");
    $stmt->execute([$today]);
    echo json_encode(['success' => true, 'discounts' => $stmt->fetchAll()]);
    break;

case 'apply_discount':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $code = strtoupper(trim($input['code'] ?? ''));
    $booking_amount = floatval($input['amount'] ?? 0);
    $event_type = trim($input['event_type'] ?? 'All');

    if (empty($code)) {
        http_response_code(400); echo json_encode(['error' => 'Please enter a promo code.']); exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM discounts WHERE code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$code]);
    $disc = $stmt->fetch();

    if (!$disc) {
        http_response_code(404); echo json_encode(['error' => 'Invalid or expired promo code.']); exit;
    }

    if ($disc['used_count'] >= $disc['usage_limit']) {
        http_response_code(400); echo json_encode(['error' => 'This promo code limit has been reached.']); exit;
    }

    $today = date('Y-m-d');
    if (!empty($disc['valid_until']) && $disc['valid_until'] < $today) {
        http_response_code(400); echo json_encode(['error' => 'This promo code has expired.']); exit;
    }

    if ($disc['min_booking_amount'] > 0 && $booking_amount < $disc['min_booking_amount']) {
        http_response_code(400); echo json_encode(['error' => "Minimum booking price of GH₵ " . number_format($disc['min_booking_amount'], 2) . " required for this code."]); exit;
    }

    if ($disc['event_type'] !== 'All' && strtolower($disc['event_type']) !== strtolower($event_type)) {
        http_response_code(400); echo json_encode(['error' => "This promo code is only valid for " . $disc['event_type'] . " events."]); exit;
    }

    $discount_amount = 0.0;
    if ($disc['discount_type'] === 'percentage') {
        $discount_amount = ($booking_amount * floatval($disc['discount_value'])) / 100.0;
        if ($disc['max_discount_amount'] > 0 && $discount_amount > floatval($disc['max_discount_amount'])) {
            $discount_amount = floatval($disc['max_discount_amount']);
        }
    } else {
        $discount_amount = floatval($disc['discount_value']);
    }

    $final_price = max(0, $booking_amount - $discount_amount);

    echo json_encode([
        'success' => true,
        'code' => $disc['code'],
        'discount_type' => $disc['discount_type'],
        'discount_value' => floatval($disc['discount_value']),
        'discount_amount' => round($discount_amount, 2),
        'original_price' => round($booking_amount, 2),
        'final_price' => round($final_price, 2),
        'message' => "Promo code applied successfully! Saved GH₵ " . number_format($discount_amount, 2)
    ]);
    break;

case 'admin_get_discounts':
    if (($_SESSION['admin_user']['role'] ?? '') !== 'admin' && ($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit;
    }
    $discounts = $pdo->query("SELECT * FROM discounts ORDER BY id DESC")->fetchAll();
    echo json_encode(['success' => true, 'discounts' => $discounts]);
    break;

case 'admin_create_discount':
    if (($_SESSION['admin_user']['role'] ?? '') !== 'admin' && ($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $code = strtoupper(trim($input['code'] ?? ''));
    $type = in_array($input['discount_type'] ?? '', ['percentage', 'fixed']) ? $input['discount_type'] : 'percentage';
    $val = floatval($input['discount_value'] ?? 0);
    $min_amt = floatval($input['min_booking_amount'] ?? 0);
    $max_amt = floatval($input['max_discount_amount'] ?? 0);
    $event_type = trim($input['event_type'] ?? 'All');
    $limit = intval($input['usage_limit'] ?? 100);
    $until = trim($input['valid_until'] ?? '');

    if (empty($code) || $val <= 0) {
        http_response_code(400); echo json_encode(['error' => 'Promo code and discount value are required.']); exit;
    }

    $stmt = $pdo->prepare("INSERT INTO discounts (code, discount_type, discount_value, min_booking_amount, max_discount_amount, event_type, usage_limit, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$code, $type, $val, $min_amt, $max_amt, $event_type, $limit, $until]);

    echo json_encode(['success' => true, 'message' => 'Discount promo code created successfully.']);
    break;

case 'admin_toggle_discount':
    if (($_SESSION['admin_user']['role'] ?? '') !== 'admin' && ($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    $action_type = $input['action_type'] ?? 'toggle';

    if ($id > 0) {
        if ($action_type === 'delete') {
            $pdo->prepare("DELETE FROM discounts WHERE id = ?")->execute([$id]);
        } else {
            $pdo->prepare("UPDATE discounts SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
        }
    }
    echo json_encode(['success' => true]);
    break;

default:
    http_response_code(404);
    echo json_encode(['error'=>'Unknown action: ' . $action]);
    break;

} // end switch
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
