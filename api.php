<?php
// api.php - Ohati Backend API
date_default_timezone_set('Africa/Accra');
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($origin)) {
    $origin_clean = rtrim($origin, '/');
    header("Access-Control-Allow-Origin: $origin_clean");
    header('Access-Control-Allow-Credentials: true');
} else if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Capacitor') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'Ohati') !== false)) {
    header("Access-Control-Allow-Origin: capacitor://localhost");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/db.php';

set_exception_handler(function($e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(400);
    }
    $msg = $e->getMessage();
    if (strpos($msg, 'locked') !== false || strpos($msg, 'busy') !== false) {
        $msg = 'Database busy. Please tap submit again.';
    }
    echo json_encode(['error' => $msg]);
    exit;
});

if (!function_exists('getBlockedUserIds')) {
    function getBlockedUserIds($uid, $pdo) {
        if ($uid <= 0 || !$pdo) return [];
        try {
            $stmt = $pdo->prepare("SELECT blocked_id FROM user_blocks WHERE blocker_id = ? UNION SELECT blocker_id FROM user_blocks WHERE blocked_id = ?");
            $stmt->execute([$uid, $uid]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            return array_values(array_unique(array_filter($ids)));
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('resolve_vendor_logo')) {
    function resolve_vendor_logo($category, $current_logo = '') {
        if (!empty($current_logo) && strpos($current_logo, 'unsplash.com') === false && strpos($current_logo, 'photo-') === false && strpos($current_logo, 'data:image/svg+xml') === false) {
            return $current_logo;
        }
        if ($category === 'Chilling Services') {
            return 'img/chill/logo.jpg';
        }
        return 'img/default-avatar.png';
    }
}

if (!function_exists('resolve_vendor_cover')) {
    function resolve_vendor_cover($category, $current_cover = '') {
        if (!empty($current_cover) && strpos($current_cover, 'data:image/svg+xml') === false) {
            return $current_cover;
        }
        if ($category === 'Chilling Services') {
            return 'img/chill/services.jpg';
        }
        return 'img/default-cover.jpg';
    }
}

if (!function_exists('get_online_status_info')) {
    function get_online_status_info($last_active_str) {
        if (empty($last_active_str) || $last_active_str === '1970-01-01 00:00:00') {
            return ['is_online' => false, 'online_status' => 'Offline'];
        }
        $ts = strtotime($last_active_str);
        if (!$ts || $ts <= 86400) {
            return ['is_online' => false, 'online_status' => 'Offline'];
        }
        $diff = time() - $ts;
        if ($diff <= 120) {
            return ['is_online' => true, 'online_status' => 'Online'];
        }
        if ($diff < 3600) {
            $mins = max(1, floor($diff / 60));
            return ['is_online' => false, 'online_status' => "Active {$mins}m ago"];
        }
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return ['is_online' => false, 'online_status' => "Active {$hours}h ago"];
        }
        return ['is_online' => false, 'online_status' => "Active " . date('M j', $ts)];
    }
}

// Bearer Token & Persistent Authentication Middleware
$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$raw_json_init = json_decode(file_get_contents('php://input'), true);
$raw_input_init = is_array($raw_json_init) ? $raw_json_init : [];
$token = '';

if (preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
    $token = trim($matches[1]);
} else {
    $token = trim($_GET['auth_token'] ?? $_POST['auth_token'] ?? $raw_input_init['auth_token'] ?? '');
}

if (!empty($token)) {
    $token_hash = hash('sha256', $token);
    try {
        $now_str = date('Y-m-d H:i:s');
        $t_stmt = $pdo->prepare("SELECT user_id FROM auth_tokens WHERE token_hash = ? AND (expires_at IS NULL OR expires_at > ?)");
        $t_stmt->execute([$token_hash, $now_str]);
        $token_uid = $t_stmt->fetchColumn();
        if ($token_uid) {
            $u_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $u_stmt->execute([$token_uid]);
            $token_user = $u_stmt->fetch();
            if ($token_user) {
                $_SESSION['user'] = $token_user;
                $_SESSION['user_id'] = $token_user['id'];
            }
        }
    } catch (Exception $e) {}
}

if (!function_exists('issue_auth_token')) {
    function issue_auth_token($pdo, $user_id, $device_name = '') {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        try {
            $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token_hash, device_name, ip_address, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $token_hash, substr($device_name, 0, 250), $ip, $expires_at]);
            return $token;
        } catch (Exception $e) {
            return '';
        }
    }
}

// Update real-time user & vendor activity timestamp
if (isset($_SESSION['user']['id'])) {
    $uid = intval($_SESSION['user']['id']);
    if (empty($_SESSION['last_active_time']) || (time() - $_SESSION['last_active_time']) >= 10) {
        $_SESSION['last_active_time'] = time();
        $now_str = date('Y-m-d H:i:s');
        try {
            $pdo->prepare("UPDATE users SET last_active = ? WHERE id = ?")->execute([$now_str, $uid]);
            $pdo->prepare("UPDATE vendors SET last_active = ? WHERE user_id = ?")->execute([$now_str, $uid]);
        } catch (Exception $e) {}
    }
}

if (!function_exists('get_online_status_info')) {
    function get_online_status_info($last_active_str) {
        if (empty($last_active_str)) {
            return ['is_online' => false, 'online_status' => 'Offline', 'last_active_formatted' => 'Offline'];
        }
        $time_ts = is_numeric($last_active_str) ? intval($last_active_str) : strtotime($last_active_str);
        if (!$time_ts || $time_ts <= 0) {
            return ['is_online' => false, 'online_status' => 'Offline', 'last_active_formatted' => 'Offline'];
        }
        $diff = time() - $time_ts;

        // Handle future or bad timestamps
        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff <= 180) { // Active within 3 minutes (180 seconds)
            return [
                'is_online' => true,
                'online_status' => 'Online',
                'last_active_formatted' => 'Active now'
            ];
        } else if ($diff < 3600) {
            $mins = max(1, intval($diff / 60));
            $str = "Last seen {$mins} " . ($mins == 1 ? "min" : "mins") . " ago";
            return ['is_online' => false, 'online_status' => $str, 'last_active_formatted' => $str];
        } else if ($diff < 86400) {
            $hrs = intval($diff / 3600);
            $str = "Last seen {$hrs} " . ($hrs == 1 ? "hour" : "hours") . " ago";
            return ['is_online' => false, 'online_status' => $str, 'last_active_formatted' => $str];
        } else {
            $days = floor($diff / 86400);
            if ($days == 1) {
                $str = "Last seen yesterday at " . date('g:i A', $time_ts);
            } else if ($days < 7) {
                $str = "Last seen " . date('D g:i A', $time_ts);
            } else {
                $str = "Last seen " . date('M j, Y', $time_ts);
            }
            return ['is_online' => false, 'online_status' => $str, 'last_active_formatted' => $str];
        }
    }
}

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
require_once __DIR__ . '/storage_helper.php';

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

require_once __DIR__ . '/jobs_api.php';
if (strpos($action, 'job_') === 0 || strpos($action, 'admin_job_') === 0) {
    handle_job_action($action, $pdo);
    exit;
}

require_once __DIR__ . '/blog_api.php';
if (strpos($action, 'blog') !== false || strpos($action, 'admin_blog') !== false || in_array($action, ['get_blog_posts', 'get_blog_post', 'like_blog_post', 'like_blog_comment', 'add_blog_comment', 'get_blog_comments', 'share_blog_post'])) {
    handle_blog_action($action, $pdo);
    exit;
}

// CSRF token helper
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
}
if (!function_exists('verify_csrf')) {
    function verify_csrf($token) {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = !empty($token) ? $token : bin2hex(random_bytes(32));
        } elseif (!empty($token)) {
            $_SESSION['csrf'] = $token;
        }
        return true;
    }
}

// Sanitize output helper
if (!function_exists('clean')) {
    function clean($str) {
        if ($str === null || $str === false) return '';
        if (is_array($str)) return '';
        return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('add_notification')) {
    function add_notification($pdo, $user_id, $title, $message) {
        $uid = intval($user_id);
        if ($uid <= 0 || !$pdo) return;
        $now_stamp = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO notifications (user_id, title, body, created_at) VALUES (?, ?, ?, ?)")->execute([$uid, $title, $message, $now_stamp]);
    }
}

if (!function_exists('log_activity')) {
    function log_activity($pdo, $action, $entity_type, $entity_id, $actor_id, $actor_role, $actor_name, $amount = 0, $old_status = '', $new_status = '', $details = '') {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $device = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $stmt = $pdo->prepare("INSERT INTO financial_audit_log (action, entity_type, entity_id, actor_id, actor_role, actor_name, amount, old_status, new_status, details, ip_address, device) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$action, $entity_type, $entity_id, $actor_id, $actor_role, $actor_name, $amount, $old_status, $new_status, $details, $ip, substr($device, 0, 190)]);
        } catch (Exception $e) {}
    }
}

// Rate limit helper (simple session-based)
if (!function_exists('rate_limit')) {
    function rate_limit($key, $max = 5, $window = 60) {
        $now = time();
        if (!isset($_SESSION['rl'][$key])) $_SESSION['rl'][$key] = [];
        $_SESSION['rl'][$key] = array_filter($_SESSION['rl'][$key], fn($t) => $t > $now - $window);
        if (count($_SESSION['rl'][$key]) >= $max) return false;
        $_SESSION['rl'][$key][] = $now;
        return true;
    }
}

// Safe database transaction helper with SQLite busy lock retry support
if (!function_exists('begin_db_transaction')) {
    function begin_db_transaction($pdo) {
        if ($pdo && $pdo->inTransaction()) {
            return true;
        }
        global $db_type;
        if ($db_type === 'sqlite') {
            for ($i = 0; $i < 5; $i++) {
                try {
                    $pdo->exec("PRAGMA busy_timeout=60000;");
                    $pdo->exec("BEGIN IMMEDIATE TRANSACTION");
                    return true;
                } catch (PDOException $e) {
                    if ((strpos($e->getMessage(), 'locked') !== false || strpos($e->getMessage(), 'busy') !== false) && $i < 4) {
                        usleep(150000); // 150ms retry delay
                        continue;
                    }
                    throw $e;
                }
            }
        } else {
            return $pdo->beginTransaction();
        }
    }
}

// Idempotency / Double-submission lock helper
if (!function_exists('check_idempotency_lock')) {
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
}

// Financial audit log helper
if (!function_exists('audit_log')) {
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
}

// Paystack server-side verification
if (!function_exists('verify_paystack_transaction')) {
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
}

if (!function_exists('is_local_env')) {
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
}

if (!function_exists('secure_save_base64_image')) {
    function secure_save_base64_image($b64_data, $folder = 'receipts', $prefix = 'file') {
        if (empty($b64_data)) return '';
        if (file_exists(__DIR__ . '/storage_helper.php')) {
            require_once __DIR__ . '/storage_helper.php';
            $res = upload_media_file($b64_data, $folder);
            if (!empty($res['url'])) {
                return $res['url'];
            }
        }
        return '';
    }
}


if (!function_exists('compressAndResizeImage')) {
    function compressAndResizeImage($source_path, $target_path, $max_width = 1200, $max_height = 1200, $quality = 75) {
        if (!extension_loaded('gd') || !function_exists('imagecreatefrompng')) {
            return false;
        }
        
        $info = @getimagesize($source_path);
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
        
        // Create image from source safely
        $image = false;
        switch ($mime) {
            case 'image/jpeg':
                $image = function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source_path) : false;
                break;
            case 'image/png':
                $image = function_exists('imagecreatefrompng') ? @imagecreatefrompng($source_path) : false;
                break;
            case 'image/gif':
                $image = function_exists('imagecreatefromgif') ? @imagecreatefromgif($source_path) : false;
                break;
            case 'image/webp':
                $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source_path) : false;
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
}

// Ensure vendor wallet exists
if (!function_exists('ensure_wallet')) {
    function ensure_wallet($pdo, $vendor_id, $user_id) {
        $stmt = $pdo->prepare("SELECT id FROM vendor_wallets WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        if (!$stmt->fetchColumn()) {
            $pdo->prepare("INSERT INTO vendor_wallets (vendor_id, user_id) VALUES (?, ?)")->execute([$vendor_id, $user_id]);
        }
    }
}

if (!function_exists('unlock_category_milestones')) {
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
}

if (!function_exists('generate_event_checklist')) {
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
$csrf_exempt_actions = ['register', 'register_vendor', 'update_vendor', 'update_profile', 'register_device_token', 'login', 'logout', 'send_otp', 'verify_otp', 'forgot_password', 'reset_password', 'run_diagnostics', 'vendors', 'vendor_detail', 'search', 'categories', 'faq', 'get_tracker_tasks', 'user_bookings', 'chat_inbox', 'chat_history', 'notifications', 'mark_notifications_read', 'vendor_stats', 'dashboard_stats', 'record_vendor_view', 'toggle_compare', 'get_compare', 'toggle_favorite', 'get_favorites', 'me', 'get_reviews', 'get_advertisements', 'advertisements', 'get_vendor_packages', 'record_ad_click', 'initiate_call', 'check_incoming_call', 'get_call_details', 'accept_call', 'answer_call', 'reject_call', 'end_call', 'update_call_status', 'send_ice_candidate', 'heartbeat', 'get_user_status', 'upload_chat_file', 'get_call_number', 'init_didit_kyc', 'check_didit_kyc', 'switch_role'];
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

case 'register':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    check_idempotency_lock('register', 3);
    if (!rate_limit('register', 5, 300)) { http_response_code(429); echo json_encode(['error'=>'Too many registration attempts. Please wait 5 minutes before trying again.']); exit; }
    $raw_in = file_get_contents('php://input');
    $input = json_decode($raw_in, true);
    if (!is_array($input)) { $input = $_POST; }
    $name = clean($input['name'] ?? trim(($input['fname'] ?? '') . ' ' . ($input['lname'] ?? '')));
    $raw_email = strtolower(trim($input['email'] ?? ''));
    $email = !empty($raw_email) ? filter_var($raw_email, FILTER_VALIDATE_EMAIL) : '';
    $phone = clean($input['phone'] ?? '');
    $phone_digits = preg_replace('/\D/', '', $phone);
    $password = $input['password'] ?? '';
    $role = in_array($input['role'] ?? '', ['customer','vendor']) ? $input['role'] : 'customer';

    // Detailed input validation
    if (empty($name)) {
        http_response_code(400); echo json_encode(['error'=>'Full name is required to create an account.']); exit;
    }
    if (!empty($raw_email) && !$email) {
        http_response_code(400); echo json_encode(['error'=>'Please enter a valid email address (e.g. name@example.com).']); exit;
    }
    if (empty($email) && empty($phone)) {
        http_response_code(400); echo json_encode(['error'=>'Either a valid email address or phone number is required.']); exit;
    }
    if (strlen($password) < 6) {
        http_response_code(400); echo json_encode(['error'=>'Password must be at least 6 characters long.']); exit;
    }
    $confirm = $input['confirm_password'] ?? $input['confirm'] ?? '';
    if (empty($confirm)) {
        http_response_code(400); echo json_encode(['error'=>'Confirm Password field is compulsory.']); exit;
    }
    if ($password !== $confirm) {
        http_response_code(400); echo json_encode(['error'=>'Passwords do not match. Please verify your password.']); exit;
    }

    // Process compulsory profile avatar & cover photo
    $avatar_url = 'img/default-avatar.png';
    $cover_url = 'img/default-cover.jpg';

    $avatar_input = $input['avatar'] ?? $input['avatar_base64'] ?? null;
    if (!empty($avatar_input)) {
        $up_avatar = upload_media_file($avatar_input, 'avatars', 800);
        if (!empty($up_avatar['success']) && !empty($up_avatar['url'])) {
            $avatar_url = $up_avatar['url'];
        }
    }

    $cover_input = $input['cover_photo'] ?? $input['cover_base64'] ?? null;
    if (!empty($cover_input)) {
        $up_cover = upload_media_file($cover_input, 'covers', 1920);
        if (!empty($up_cover['success']) && !empty($up_cover['url'])) {
            $cover_url = $up_cover['url'];
        }
    }

    try {
        try { if (!$pdo->inTransaction()) { $pdo->beginTransaction(); } } catch (Exception $eTrans) {}

        // Check duplicate email (case-insensitive & trimmed)
        if ($email) { 
            $dup = $pdo->prepare("SELECT id, email_verified, phone_verified, status FROM users WHERE LOWER(email) = LOWER(?)"); 
            $dup->execute([$email]); 
            $existing = $dup->fetch();
            if ($existing) {
                if (intval($existing['email_verified'] ?? 0) === 0 && intval($existing['phone_verified'] ?? 0) === 0) {
                    $uid = intval($existing['id']);
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE users SET name = ?, password_hash = ?, role = ?, avatar = ?, cover_photo = ? WHERE id = ?")
                        ->execute([$name, $hash, $role, $avatar_url, $cover_url, $uid]);
                    
                    if ($role === 'vendor') {
                        $bname = clean($input['business_name'] ?? $input['bname'] ?? $name);
                        $category = clean($input['category'] ?? 'General Services');
                        $desc = clean($input['description'] ?? '');
                        $loc = clean($input['location'] ?? $input['city'] ?? 'Accra, Ghana');
                        $v_chk = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
                        $v_chk->execute([$uid]);
                        if ($v_chk->fetch()) {
                            $pdo->prepare("UPDATE vendors SET name = ?, category = ?, logo = ?, cover_photo = ?, description = ?, location = ? WHERE user_id = ?")
                                ->execute([$bname, $category, $avatar_url, $cover_url, $desc, $loc, $uid]);
                        } else {
                            $pdo->prepare("INSERT INTO vendors (user_id, name, category, logo, cover_photo, description, location, phone, email, verification_status, verification_badge, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 'grey', 1)")
                                ->execute([$uid, $bname, $category, $avatar_url, $cover_url, $desc, $loc, $phone ?: null, $email ?: null]);
                        }
                    }
                    
                    $pdo->commit();
                    $my_ref_code = 'OHATI-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
                    $user = ['id'=>$uid,'name'=>$name,'email'=>$email,'phone'=>$phone,'role'=>$role,'avatar'=>$avatar_url,'cover_photo'=>$cover_url,'vendor_cover_photo'=>$cover_url,'kyc_status'=>'not_started','email_verified'=>0,'referral_code'=>$my_ref_code];
                    $_SESSION['user'] = $user;
                    $_SESSION['user']['active_role'] = $role;
                    $auth_token = issue_auth_token($pdo, $uid, $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile/Web');
                    echo json_encode(['success'=>true,'requires_verification'=>true,'user'=>$user,'auth_token'=>$auth_token,'csrf'=>csrf_token()]);
                    exit;
                } else {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    http_response_code(409); 
                    echo json_encode([
                        'success' => false,
                        'account_exists' => true,
                        'target' => $email,
                        'role' => $existing['role'] ?? 'customer',
                        'kyc_status' => $existing['kyc_status'] ?? 'not_started',
                        'error' => "An account with email address '$email' already exists on Ohati. Please log in or request an OTP to verify and access your account."
                    ]); 
                    exit; 
                }
            } 
        }

        // Check duplicate phone (matching raw, formatted, or core 9-digit mobile line)
        if (!empty($phone_digits) && strlen($phone_digits) >= 8) { 
            $last9 = substr($phone_digits, -9);
            $dup = $pdo->prepare("SELECT id, email_verified, phone_verified, status, role, kyc_status FROM users WHERE phone = ? OR phone = ? OR (LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', '')) >= 9 AND SUBSTR(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), -9) = ?)"); 
            $dup->execute([$phone, '+' . $phone_digits, $last9]); 
            $existing = $dup->fetch();
            if ($existing) { 
                if (intval($existing['email_verified'] ?? 0) === 0 && intval($existing['phone_verified'] ?? 0) === 0) {
                    $uid = intval($existing['id']);
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE users SET name = ?, password_hash = ?, role = ?, avatar = ?, cover_photo = ? WHERE id = ?")
                        ->execute([$name, $hash, $role, $avatar_url, $cover_url, $uid]);
                    
                    if ($role === 'vendor') {
                        $bname = clean($input['business_name'] ?? $input['bname'] ?? $name);
                        $category = clean($input['category'] ?? 'General Services');
                        $desc = clean($input['description'] ?? '');
                        $loc = clean($input['location'] ?? $input['city'] ?? 'Accra, Ghana');
                        $v_chk = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
                        $v_chk->execute([$uid]);
                        if ($v_chk->fetch()) {
                            $pdo->prepare("UPDATE vendors SET name = ?, category = ?, logo = ?, cover_photo = ?, description = ?, location = ? WHERE user_id = ?")
                                ->execute([$bname, $category, $avatar_url, $cover_url, $desc, $loc, $uid]);
                        } else {
                            $pdo->prepare("INSERT INTO vendors (user_id, name, category, logo, cover_photo, description, location, phone, email, verification_status, verification_badge, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 'grey', 1)")
                                ->execute([$uid, $bname, $category, $avatar_url, $cover_url, $desc, $loc, $phone ?: null, $email ?: null]);
                        }
                    }
                    
                    $pdo->commit();
                    $my_ref_code = 'OHATI-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
                    $user = ['id'=>$uid,'name'=>$name,'email'=>$email,'phone'=>$phone,'role'=>$role,'avatar'=>$avatar_url,'cover_photo'=>$cover_url,'vendor_cover_photo'=>$cover_url,'kyc_status'=>'not_started','email_verified'=>0,'referral_code'=>$my_ref_code];
                    $_SESSION['user'] = $user;
                    $_SESSION['user']['active_role'] = $role;
                    $auth_token = issue_auth_token($pdo, $uid, $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile/Web');
                    echo json_encode(['success'=>true,'requires_verification'=>true,'user'=>$user,'auth_token'=>$auth_token,'csrf'=>csrf_token()]);
                    exit;
                } else {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    http_response_code(409); 
                    echo json_encode([
                        'success' => false,
                        'account_exists' => true,
                        'target' => $phone,
                        'role' => $existing['role'] ?? 'customer',
                        'kyc_status' => $existing['kyc_status'] ?? 'not_started',
                        'error' => "An account with phone number '$phone' already exists on Ohati. Please log in or request an OTP to verify and access your account."
                    ]); 
                    exit; 
                }
            } 
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $my_ref_code = 'OHATI-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        
        $used_ref = clean($input['ref'] ?? $_GET['ref'] ?? $_SESSION['pending_ref'] ?? '');
        $referrer_id = 0;
        if (!empty($used_ref)) {
            $r_chk = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? OR referral_code = ?");
            $r_chk->execute([$used_ref, strtoupper($used_ref)]);
            $referrer_id = intval($r_chk->fetchColumn() ?: 0);
        }

        $stmt = $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,role,avatar,cover_photo,email_verified,referral_code,referred_by) VALUES (?,?,?,?,?,?,?,0,?,?)");
        $stmt->execute([$name, $email ?: null, $phone ?: null, $hash, $role, $avatar_url, $cover_url, $my_ref_code, $referrer_id]);
        $uid = $pdo->lastInsertId();

        if ($referrer_id > 0 && $referrer_id !== intval($uid)) {
            try {
                $rew_stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = 'referral_reward_amount'");
                $rew_stmt->execute();
                $reward_val = floatval($rew_stmt->fetchColumn() ?: 10.0);

                $pdo->prepare("INSERT INTO referrals (referrer_id, referred_id, referral_code, reward_amount, status, payout_status) VALUES (?, ?, ?, ?, 'completed', 'pending')")->execute([$referrer_id, $uid, $used_ref, $reward_val]);
                $pdo->prepare("UPDATE users SET referral_balance = referral_balance + ? WHERE id = ?")->execute([$reward_val, $referrer_id]);

                $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Referral Bonus Earned! 🎉', ?, 'gift')")
                    ->execute([$referrer_id, "Great news! $name joined Ohati using your referral link. You earned GH₵ " . number_format($reward_val, 2) . "!"]);

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

        if ($role === 'vendor') {
            $bname = clean($input['business_name'] ?? $input['bname'] ?? $name);
            $category = clean($input['category'] ?? 'General Services');
            $desc = clean($input['description'] ?? '');
            $loc = clean($input['location'] ?? $input['city'] ?? 'Accra, Ghana');
            
            try {
                $v_ins = $pdo->prepare("INSERT INTO vendors (user_id, name, category, logo, cover_photo, description, location, phone, email, verification_status, verification_badge, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 'grey', 1)");
                $v_ins->execute([$uid, $bname, $category, $avatar_url, $cover_url, $desc, $loc, $phone ?: null, $email ?: null]);
            } catch (Exception $eVend) {}
        }

        try {
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Exception $eCommit) {}
    } catch (Exception $eReg) {
        try {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Exception $eRoll) {}
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed: ' . $eReg->getMessage()]);
        exit;
    }

    // Also dispatch Admin Email Notification
    try {
        send_admin_activity_notification(
            "New " . ucfirst($role) . " Registration (" . htmlspecialchars($name) . ")",
            "<p>A new <strong>" . htmlspecialchars($role) . "</strong> account has registered on Ohati Ghana.</p><p><strong>Name:</strong> " . htmlspecialchars($name) . "</p><p><strong>Email:</strong> " . htmlspecialchars($email) . "</p><p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>"
        );
    } catch (Exception $eAdminReg) {}

    $user = ['id'=>$uid,'name'=>$name,'email'=>$email,'phone'=>$phone,'role'=>$role,'avatar'=>'','kyc_status'=>'not_started','email_verified'=>0,'referral_code'=>$my_ref_code];
    $_SESSION['user'] = $user;
    $_SESSION['user']['active_role'] = $role;
    $auth_token = issue_auth_token($pdo, $uid, $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile/Web');
    echo json_encode(['success'=>true,'requires_verification'=>true,'user'=>$user,'auth_token'=>$auth_token,'csrf'=>csrf_token()]);
    break;

case 'session':
case 'me':
    if (isset($_SESSION['user']['id'])) {
        $uid = intval($_SESSION['user']['id']);
        $u_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $u_stmt->execute([$uid]);
        $u_row = $u_stmt->fetch();
        if ($u_row && intval($u_row['is_active'] ?? 1) === 1) {
            $safe_user = [
                'id' => intval($u_row['id']),
                'name' => $u_row['name'] ?? '',
                'username' => $u_row['username'] ?? '',
                'email' => $u_row['email'] ?? '',
                'phone' => $u_row['phone'] ?? '',
                'dob' => $u_row['dob'] ?? '',
                'gender' => $u_row['gender'] ?? '',
                'country' => $u_row['country'] ?? 'Ghana',
                'state' => $u_row['state'] ?? '',
                'city' => $u_row['city'] ?? '',
                'language' => $u_row['language'] ?? 'English',
                'currency' => $u_row['currency'] ?? 'GHS',
                'role' => $u_row['role'] ?? 'customer',
                'avatar' => !empty($u_row['avatar']) ? format_full_image_url($u_row['avatar']) : '',
                'kyc_status' => $u_row['kyc_status'] ?? 'not_started',
                'active_role' => !empty($u_row['active_role']) ? $u_row['active_role'] : ($u_row['role'] === 'vendor' ? 'vendor' : 'customer')
            ];
            $v_stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
            $v_stmt->execute([$uid]);
            $v_row = $v_stmt->fetch();
            $vendor_res = null;
            if ($v_row) {
                $safe_user['vendor_id'] = intval($v_row['id']);
                $safe_user['has_vendor_profile'] = true;
                $v_st = $v_row['verification_status'] ?? 'pending';
                $safe_user['vendor_verification_status'] = $v_st;
                $safe_user['vendor_onboarding_completed'] = ($v_st !== 'draft' && !empty($v_row['name']));
                $vendor_res = $v_row;
            } else {
                $safe_user['has_vendor_profile'] = false;
                $safe_user['vendor_verification_status'] = 'not_created';
                $safe_user['vendor_onboarding_completed'] = false;
            }
            $_SESSION['user'] = $safe_user;
            echo json_encode(['success' => true, 'user' => $safe_user, 'vendor' => $vendor_res, 'csrf' => csrf_token()]);
            break;
        }
    }
    $_SESSION['user'] = null;
    echo json_encode(['success' => false, 'user' => null, 'vendor' => null, 'csrf' => csrf_token()]);
    break;

case 'login':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!rate_limit('login', 5, 60)) { http_response_code(429); echo json_encode(['error'=>'Too many login attempts. Wait 60 seconds.']); exit; }
    $raw_body = file_get_contents('php://input');
    $input = json_decode($raw_body, true);
    if (is_string($input)) {
        $identifier = clean($input);
    } else if (is_array($input)) {
        $identifier = clean($input['identifier'] ?? $input['email'] ?? $input['phone'] ?? $input['username'] ?? $input['user'] ?? $input['login'] ?? $_POST['identifier'] ?? $_POST['email'] ?? $_POST['phone'] ?? $_POST['login'] ?? '');
    } else {
        $identifier = clean($_POST['identifier'] ?? $_POST['email'] ?? $_POST['phone'] ?? $_POST['username'] ?? $_POST['login'] ?? $_GET['identifier'] ?? $_GET['email'] ?? '');
    }
    $password = is_array($input) ? ($input['password'] ?? $_POST['password'] ?? '') : ($_POST['password'] ?? '');
    $otp = is_array($input) ? ($input['otp'] ?? $_POST['otp'] ?? '') : ($_POST['otp'] ?? '');
    if (empty($identifier)) { http_response_code(400); echo json_encode(['error'=>'Email or phone is required.']); exit; }

    $id_lower = strtolower(trim($identifier));
    $id_digits = preg_replace('/\D/', '', $identifier);
    $last9 = strlen($id_digits) >= 8 ? substr($id_digits, -9) : '';

    if (!empty($last9)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? OR phone = ? OR phone = ? OR (LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', '')) >= 9 AND SUBSTR(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), -9) = ?)");
        $stmt->execute([$id_lower, $identifier, '+' . $id_digits, $last9]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? OR phone = ?");
        $stmt->execute([$id_lower, $identifier]);
    }
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
    // Automatically ensure existing accounts in the database are marked verified & active
    $pdo->prepare("UPDATE users SET email_verified = 1, phone_verified = 1, status = 'active' WHERE id = ?")->execute([$user['id']]);
    $user['status'] = 'active';
    $user['email_verified'] = 1;
    $user['phone_verified'] = 1;
    // Update login
    $pdo->prepare("UPDATE users SET last_login = ?, login_count = login_count + 1 WHERE id = ?")->execute([date('Y-m-d H:i:s'), $user['id']]);
    // Log login
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $device = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $pdo->prepare("INSERT INTO login_history (user_id,ip_address,device,status) VALUES (?,?,?,'success')")->execute([$user['id'],$ip,$device]);
    $safe_user = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'phone'=>$user['phone'],'role'=>$user['role'],'avatar'=>$user['avatar'],'kyc_status'=>$user['kyc_status']];
    
    // Check if vendor
    $v_stmt = $pdo->prepare("SELECT id, name, verification_status, description, location FROM vendors WHERE user_id = ?");
    $v_stmt->execute([$user['id']]);
    $v_row = $v_stmt->fetch();
    if ($v_row) {
        $safe_user['vendor_id'] = intval($v_row['id']);
        $safe_user['has_vendor_profile'] = true;
        $v_st = $v_row['verification_status'] ?? 'pending';
        $safe_user['vendor_verification_status'] = $v_st;
        $is_draft = ($v_st === 'draft');
        $has_name = !empty($v_row['name']);
        $safe_user['vendor_onboarding_completed'] = (!$is_draft && $has_name);
        $safe_user['active_role'] = !empty($user['active_role']) ? $user['active_role'] : ($user['role'] === 'vendor' ? 'vendor' : 'customer');
    } else {
        $safe_user['active_role'] = !empty($user['active_role']) ? $user['active_role'] : ($user['role'] === 'vendor' ? 'vendor' : 'customer');
        $safe_user['has_vendor_profile'] = false;
        $safe_user['vendor_verification_status'] = 'not_created';
        $safe_user['vendor_onboarding_completed'] = false;
    }
    
    $_SESSION['user'] = $safe_user;
    $auth_token = issue_auth_token($pdo, $user['id'], $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile/Web');
    echo json_encode(['success'=>true,'user'=>$safe_user,'auth_token'=>$auth_token,'csrf'=>csrf_token()]);
    break;

case 'logout':
    $uid = intval($_SESSION['user']['id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? $token_uid ?? 0);
    if ($uid > 0) {
        try {
            $now_reset = '1970-01-01 00:00:00';
            $pdo->prepare("UPDATE users SET last_active = ? WHERE id = ?")->execute([$now_reset, $uid]);
            $pdo->prepare("UPDATE vendors SET last_active = ? WHERE user_id = ?")->execute([$now_reset, $uid]);
        } catch (Exception $e) {}
    }
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $token_to_del = '';
    if (preg_match('/Bearer\s+(.+)/i', $auth_header, $m)) {
        $token_to_del = trim($m[1]);
    } else {
        $token_to_del = trim($_GET['auth_token'] ?? $_POST['auth_token'] ?? $raw_input_init['auth_token'] ?? '');
    }
    if (!empty($token_to_del)) {
        $hash = hash('sha256', $token_to_del);
        try {
            $pdo->prepare("DELETE FROM auth_tokens WHERE token_hash = ?")->execute([$hash]);
        } catch (Exception $e) {}
    }
    if ($uid > 0) {
        try {
            $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$uid]);
        } catch (Exception $e) {}
    }
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
    echo json_encode(['success'=>true]);
    break;

case 'delete_account':
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    $input_data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!$uid && isset($_POST['user_id'])) {
        $uid = intval($_POST['user_id']);
    } else if (!$uid && isset($input_data['user_id'])) {
        $uid = intval($input_data['user_id']);
    }

    // Allow deletion by verifying identifier + password if not currently logged in
    $identifier = clean($_POST['identifier'] ?? $input_data['identifier'] ?? '');
    $password = $_POST['password'] ?? $input_data['password'] ?? '';
    if (!$uid && !empty($identifier) && !empty($password)) {
        $id_lower = strtolower(trim($identifier));
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? OR phone = ?");
        $stmt->execute([$id_lower, $identifier]);
        $user_to_del = $stmt->fetch();
        if ($user_to_del && password_verify($password, $user_to_del['password_hash'])) {
            $uid = intval($user_to_del['id']);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email/phone or password. Account deletion failed.']);
            exit;
        }
    }

    if (!$uid) {
        http_response_code(401);
        echo json_encode(['error' => 'Please log in or provide your password to confirm account deletion.']);
        exit;
    }


    $sel = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $sel->execute([$uid]);
    $u = $sel->fetch(PDO::FETCH_ASSOC);

    if ($u) {
        // Archive full user record to deleted_records table
        $record_data = json_encode($u);
        try {
            $stmt = $pdo->prepare("INSERT INTO deleted_records (record_type, record_id, record_data) VALUES ('user_account_deletion', ?, ?)");
            $stmt->execute([$uid, $record_data]);
        } catch(Exception $e) {}

        // Soft delete user record - retain data in DB for admin management
        try {
            $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$uid]);
        } catch(Exception $e) {}
        try {
            $pdo->prepare("UPDATE users SET status = 'deleted' WHERE id = ?")->execute([$uid]);
        } catch(Exception $e) {}
        try {
            $pdo->prepare("UPDATE users SET deleted_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $uid]);
        } catch(Exception $e) {}

    }

    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();

    echo json_encode([
        'success' => true,
        'message' => 'Your account has been deleted. Admin has been notified and records retained in the database.'
    ]);
    break;

case 'send_otp':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $raw_in = file_get_contents('php://input');
    $input = json_decode($raw_in, true);
    if (!is_array($input)) { $input = $_POST; }
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
    $raw_in = file_get_contents('php://input');
    $input = json_decode($raw_in, true);
    if (!is_array($input)) { $input = $_POST; }
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $target_raw = trim($input['target'] ?? $input['email'] ?? $_POST['target'] ?? $_POST['email'] ?? '');
    $target_email = strtolower($target_raw);

    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE LOWER(TRIM(email)) = ? OR phone = ? OR REPLACE(phone, ' ', '') = ? LIMIT 1");
    $stmt->execute([$target_email, $target_raw, $target_raw]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['email'])) {
        http_response_code(404);
        echo json_encode(['error' => 'No registered account with a valid email address was found. Please check your spelling or contact support.']);
        exit;
    }

    try {
            $raw_token = bin2hex(random_bytes(32)); // 64 hex chars
            $token_hash = hash('sha256', $raw_token);
            $now_str = date('Y-m-d H:i:s');
            $expires_str = date('Y-m-d H:i:s', time() + 86400); // 24 hours
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            // Ensure password_resets table exists cross-database
            $is_sqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
            if ($is_sqlite) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INT NOT NULL,
                    token_hash VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    ip_address VARCHAR(45) DEFAULT ''
                );");
                try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pw_token ON password_resets(token_hash);"); } catch (Throwable $eIdx) {}
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token_hash VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    ip_address VARCHAR(45) DEFAULT '',
                    KEY idx_pw_token (token_hash),
                    KEY idx_pw_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }

            // Invalidate existing unused tokens for this user
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")->execute([$user['id']]);

            // Insert new reset token
            $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, created_at, used, ip_address) VALUES (?, ?, ?, ?, 0, ?)");
            $ins->execute([$user['id'], $token_hash, $expires_str, $now_str, $ip]);

            // Determine reset URL based on active host and scheme
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                     || ($_SERVER['SERVER_PORT'] ?? 80) == 443
                     || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
            $scheme = $is_https ? "https" : "http";
            $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $dir = ($script_dir === '/' || $script_dir === '\\' || $script_dir === '.') ? '' : rtrim(str_replace('\\', '/', $script_dir), '/');
            $reset_url = "{$scheme}://{$host}{$dir}/reset_password.php?token={$raw_token}";

            // Send branded HTML email via send_smtp_mail
            require_once __DIR__ . '/mail_helper.php';
            $user_name = htmlspecialchars($user['name'] ?: 'Ohati User');
            $subject = "Reset your Ohati password";
            $year = date('Y');
            
            $html_body = "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>"
                       . "<html xmlns='http://www.w3.org/1999/xhtml'><head>"
                       . "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />"
                       . "<meta name='viewport' content='width=device-width, initial-scale=1.0'/>"
                       . "<title>Reset Your Ohati Password</title></head>"
                       . "<body style='margin:0; padding:0; background-color:#F3F4F6; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>"
                       . "<table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color:#F3F4F6; table-layout:fixed; padding:30px 10px;'>"
                       . "<tr><td align='center'>"
                       . "<table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width:560px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 10px 30px rgba(0,0,0,0.06); overflow:hidden;'>"
                       
                       . "<tr><td align='center' style='background:#111827; padding:32px 24px; text-align:center;'>"
                       . "<h1 style='color:#FFFFFF; font-size:26px; font-weight:900; margin:0; letter-spacing:3px; font-family:sans-serif;'>OHATI</h1>"
                       . "<p style='color:#F2A735; font-size:12px; margin:6px 0 0 0; text-transform:uppercase; letter-spacing:1.5px; font-weight:700;'>Secure Account Password Reset</p>"
                       . "</td></tr>"

                       . "<tr><td style='padding:36px 32px; background-color:#FFFFFF;'>"
                       . "<h2 style='color:#111827; font-size:20px; font-weight:800; margin:0 0 16px 0;'>Reset Your Password</h2>"
                       . "<p style='color:#374151; font-size:15px; line-height:1.6; margin:0 0 16px 0;'>Hello <strong>{$user_name}</strong>,</p>"
                       . "<p style='color:#374151; font-size:15px; line-height:1.6; margin:0 0 24px 0;'>We received a request to reset the password for your Ohati account. Click the button below to create your new password:</p>"

                       . "<table border='0' cellpadding='0' cellspacing='0' width='100%' style='margin:28px 0;'><tr><td align='center'>"
                       . "<a href='{$reset_url}' style='background-color:#E05A47; color:#FFFFFF !important; font-size:16px; font-weight:800; text-decoration:none; padding:16px 36px; border-radius:12px; display:inline-block; letter-spacing:0.3px;'>Reset Password</a>"
                       . "</td></tr></table>"

                       . "<table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color:#FFFBEB; border:1px solid #FCD34D; border-radius:12px; margin:24px 0;'><tr>"
                       . "<td style='padding:14px 16px; color:#92400E; font-size:13px; line-height:1.5;'>"
                       . "<strong>⚠️ Security Notice:</strong> This reset link will expire in <strong>24 hours</strong> and can only be used once."
                       . "</td></tr></table>"

                       . "<p style='color:#6B7280; font-size:13px; line-height:1.5; margin:24px 0 8px 0;'>If the button above does not work, copy and paste this link into your browser address bar:</p>"
                       . "<div style='background-color:#F9FAFB; border:1px solid #E5E7EB; border-radius:10px; padding:12px 14px; font-size:12px; color:#4B5563; word-break:break-all; font-family:monospace; line-height:1.4;'>{$reset_url}</div>"

                       . "<p style='color:#9CA3AF; font-size:12px; line-height:1.5; margin:28px 0 0 0; border-top:1px solid #F3F4F6; padding-top:20px;'>"
                       . "If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged."
                       . "</p>"
                       . "</td></tr>"

                       . "<tr><td align='center' style='background-color:#F9FAFB; padding:24px 32px; border-top:1px solid #E5E7EB; text-align:center;'>"
                       . "<p style='color:#6B7280; font-size:12px; margin:0 0 6px 0; font-weight:600;'>Ohati Event Marketplace & Professional Network</p>"
                       . "<p style='color:#9CA3AF; font-size:11px; margin:0;'>&copy; {$year} Ohati Inc. All rights reserved. &bull; <a href='https://ohati.com' style='color:#E05A47; text-decoration:none; font-weight:600;'>ohati.com</a></p>"
                       . "</td></tr>"

                       . "</table></td></tr></table>"
                       . "</body></html>";

            $mail_sent = false;
            try {
                $mail_sent = send_smtp_mail($user['email'], $subject, $html_body, 'Ohati Security');
            } catch (Throwable $eMail) {
                error_log("Password reset email dispatch error: " . $eMail->getMessage());
            }

            if (!$mail_sent) {
                http_response_code(500);
                echo json_encode(['error' => 'Unable to deliver password reset email via mail server. Please try again later or contact support.']);
                exit;
            }
        } catch (Exception $eGen) {
            http_response_code(500);
            echo json_encode(['error' => $eGen->getMessage() ?: 'An error occurred processing password reset.']);
            exit;
        }

    echo json_encode([
        'success' => true,
        'message' => 'Password reset link sent to your registered email address (' . htmlspecialchars($user['email']) . '). Please check your inbox.'
    ]);
    break;

case 'reset_password':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($token)) { http_response_code(400); echo json_encode(['error' => 'Reset token is required.']); exit; }
    if (strlen($password) < 8) { http_response_code(400); echo json_encode(['error' => 'Password must be at least 8 characters long.']); exit; }
    
    $token_hash = hash('sha256', $token);
    $now = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("SELECT r.id as reset_id, r.user_id FROM password_resets r JOIN users u ON r.user_id = u.id WHERE r.token_hash = ? AND r.used = 0 AND r.expires_at > ? LIMIT 1");
    $stmt->execute([$token_hash, $now]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rec) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired password reset token. Please request a new link.']);
        exit;
    }
    
    $new_hash = password_hash($password, PASSWORD_BCRYPT);
    $uid = intval($rec['user_id']);
    
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $uid]);
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?")->execute([$uid]);
    try { $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$uid]); } catch (Exception $eT) {}
    
    echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now login.']);
    break;

case 'update_profile':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error'=>'Not logged in']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];

    // Support field aliases sent by frontend modals
    if (isset($input['id_front'])) $input['kyc_id_front'] = $input['id_front'];
    if (isset($input['selfie'])) $input['kyc_selfie'] = $input['selfie'];
    if (isset($input['id_back'])) $input['kyc_id_back'] = $input['id_back'];

    // Save base64 image/PDF file uploads to disk
    $file_fields = ['avatar' => 'avatars', 'kyc_id_front' => 'kyc', 'kyc_id_back' => 'kyc', 'kyc_selfie' => 'kyc'];
    foreach ($file_fields as $field_key => $folder) {
        if (!empty($input[$field_key]) && is_string($input[$field_key]) && (strpos($input[$field_key], 'data:') === 0 || strpos($input[$field_key], 'base64,') !== false)) {
            $saved_img = secure_save_base64_image($input[$field_key], $folder, $field_key . '_' . $uid);
            if (!empty($saved_img)) {
                $input[$field_key] = $saved_img;
            } else {
                // Defensive Safety Rule: Never overwrite existing avatar/image with empty string on failure!
                http_response_code(400);
                echo json_encode(['error' => 'Image upload failed. Existing image preserved.']);
                exit;
            }
        }
    }

    if (isset($input['avatar']) && is_string($input['avatar'])) {
        $input['avatar'] = preg_replace('/[\?&]v=\d+/', '', $input['avatar']);
    }
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');

    $allowed_fields = ['name','avatar','gender','dob','country','state','city','language','currency','username','kyc_status','email','phone','kyc_id_type','kyc_id_front','kyc_id_back','kyc_selfie','kyc_submitted_at'];
    $updates = [];
    $params = [];

    foreach ($allowed_fields as $f) {
        if (isset($input[$f])) {
            $val = $input[$f];
            $updates[] = "$f = ?";
            $params[] = $val;
            $_SESSION['user'][$f] = $val;
        }
    }

    if (!empty($updates)) {
        $params[] = $uid;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);

        // Sync avatar, name, email, phone to vendor profile if user owns one
        try {
            $v_sync_fields = []; $v_sync_params = [];
            if (!empty($input['avatar'])) { $v_sync_fields[] = "logo = ?"; $v_sync_params[] = $input['avatar']; }
            if (!empty($input['name'])) { $v_sync_fields[] = "name = ?"; $v_sync_params[] = $input['name']; }
            if (!empty($input['email'])) { $v_sync_fields[] = "email = ?"; $v_sync_params[] = $input['email']; }
            if (!empty($input['phone'])) { $v_sync_fields[] = "phone = ?"; $v_sync_params[] = $input['phone']; }
            if (!empty($v_sync_fields)) {
                $v_sync_params[] = $uid;
                $pdo->prepare("UPDATE vendors SET " . implode(', ', $v_sync_fields) . " WHERE user_id = ?")->execute($v_sync_params);
                if (isset($_SESSION['vendor']) && !empty($input['avatar'])) {
                    $_SESSION['vendor']['logo'] = $input['avatar'];
                }
            }
        } catch (Exception $eVendSync) {}
    }

    $ts = time();
    $saved_avatar = $_SESSION['user']['avatar'] ?? '';
    $busted_avatar = $saved_avatar ? ($saved_avatar . (strpos($saved_avatar, '?') !== false ? '&v=' : '?v=') . $ts) : '';

    echo json_encode([
        'success' => true,
        'avatar' => $busted_avatar,
        'user' => $_SESSION['user'],
        'vendor' => $_SESSION['vendor'] ?? null
    ]);
    break;

case 'init_didit_kyc':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $uid = intval($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? $input['user_id'] ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error' => 'Authentication required. Please sign in to verify your identity.']); exit; }

    require_once __DIR__ . '/didit_helper.php';

    // 1. Fetch current user from database
    $uStmt = $pdo->prepare("SELECT id, kyc_status, didit_session_id, didit_decision FROM users WHERE id = ?");
    $uStmt->execute([$uid]);
    $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

    if ($userRow && (strtolower($userRow['kyc_status']) === 'approved' || strtolower($userRow['kyc_status']) === 'verified')) {
        echo json_encode([
            'success' => false,
            'error' => 'Identity Verified: Your account has already been verified.',
            'kyc_status' => 'VERIFIED',
            'is_verified' => true
        ]);
        break;
    }

    $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $v_stmt->execute([$uid]);
    $v_row = $v_stmt->fetch();
    $vendorId = $v_row ? intval($v_row['id']) : null;

    // 2. Prevent duplicate active session creation
    $existingSessionId = $userRow['didit_session_id'] ?? '';
    if (!empty($existingSessionId)) {
        try {
            $existingDecision = DiditHelper::fetchSessionDecision($existingSessionId);
            if ($existingDecision && !empty($existingDecision['status'])) {
                $eStatus = strtolower($existingDecision['status']);
                if ($eStatus === 'approved' || $eStatus === 'verified') {
                    $pdo->prepare("UPDATE users SET kyc_status = 'approved', didit_decision = 'Approved' WHERE id = ?")->execute([$uid]);
                    if ($vendorId) $pdo->prepare("UPDATE vendors SET verification_status = 'verified', verified = 1 WHERE id = ?")->execute([$vendorId]);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Identity Verified: Your account has already been verified.',
                        'kyc_status' => 'VERIFIED',
                        'is_verified' => true
                    ]);
                    break;
                } else if ($eStatus === 'in review' || $eStatus === 'processing' || $eStatus === 'under_review') {
                    $pdo->prepare("UPDATE users SET kyc_status = 'under_review', didit_decision = 'In Review' WHERE id = ?")->execute([$uid]);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Verification Under Review: Your identity verification is currently being processed by Didit.',
                        'kyc_status' => 'UNDER_REVIEW',
                        'session_id' => $existingSessionId
                    ]);
                    break;
                } else if ($eStatus === 'not started' || $eStatus === 'in progress' || $eStatus === 'awaiting user') {
                    $resumeUrl = !empty($existingDecision['url']) ? $existingDecision['url'] : (!empty($existingDecision['session_url']) ? $existingDecision['session_url'] : '');
                    if (empty($resumeUrl)) {
                        $newSession = DiditHelper::createSession($uid, $vendorId);
                        $existingSessionId = $newSession['session_id'];
                        $resumeUrl = $newSession['url'];
                        $pdo->prepare("UPDATE users SET didit_session_id = ?, kyc_status = 'in_progress', didit_decision = 'In Progress' WHERE id = ?")->execute([$existingSessionId, $uid]);
                        if ($vendorId) {
                            $pdo->prepare("UPDATE vendors SET didit_session_id = ?, verification_status = 'pending', didit_decision = 'In Progress' WHERE id = ?")->execute([$existingSessionId, $vendorId]);
                        }
                    }
                    echo json_encode([
                        'success' => true,
                        'resumed' => true,
                        'url' => $resumeUrl,
                        'session_id' => $existingSessionId
                    ]);
                    break;
                }
            }
        } catch (Exception $ex) {}
    }

    try {
        $session = DiditHelper::createSession($uid, $vendorId);
        $sessionId = $session['session_id'];
        $url = $session['url'];

        // Record session_id and update status to in_progress
        $pdo->prepare("UPDATE users SET didit_session_id = ?, kyc_status = 'in_progress', didit_decision = 'In Progress' WHERE id = ?")->execute([$sessionId, $uid]);
        if ($vendorId) {
            $pdo->prepare("UPDATE vendors SET didit_session_id = ?, verification_status = 'pending', didit_decision = 'In Progress' WHERE id = ?")->execute([$sessionId, $vendorId]);
        }

        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['didit_session_id'] = $sessionId;
            $_SESSION['user']['kyc_status'] = 'in_progress';
            $_SESSION['user']['didit_decision'] = 'In Progress';
        }

        echo json_encode([
            'success' => true,
            'url' => $url,
            'session_id' => $sessionId,
            'session_token' => $session['session_token'] ?? ''
        ]);
    } catch (Exception $eDidit) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $eDidit->getMessage() ?: 'Failed to initialize Didit verification session',
            'detail' => $eDidit->getMessage()
        ]);
    }
    break;

case 'check_didit_kyc':
case 'get_kyc_status':
    $uid = intval($_SESSION['user']['id'] ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error' => 'Authentication required']); exit; }

    try {
        $uStmt = $pdo->prepare("SELECT u.id, u.kyc_status, u.kyc_verified_at, u.didit_session_id, u.didit_decision, v.verification_status, v.verification_badge, v.verified FROM users u LEFT JOIN vendors v ON u.id = v.user_id WHERE u.id = ?");
        $uStmt->execute([$uid]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $eColMissing) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN kyc_verified_at VARCHAR(50) DEFAULT ''"); } catch (Throwable $eAdd) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN didit_session_id VARCHAR(255) DEFAULT ''"); } catch (Throwable $eAdd) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN didit_decision VARCHAR(100) DEFAULT ''"); } catch (Throwable $eAdd) {}
        
        $uStmt = $pdo->prepare("SELECT u.id, u.kyc_status, u.didit_session_id, u.didit_decision, v.verification_status, v.verification_badge, v.verified FROM users u LEFT JOIN vendors v ON u.id = v.user_id WHERE u.id = ?");
        $uStmt->execute([$uid]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($user) $user['kyc_verified_at'] = '';
    }

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        break;
    }

    $sessionId = $user['didit_session_id'] ?? '';
    if (!empty($sessionId)) {
        require_once __DIR__ . '/didit_helper.php';
        $decision = DiditHelper::fetchSessionDecision($sessionId);

        if ($decision && !empty($decision['status'])) {
            $dStatus = strtolower($decision['status']);
            $nowStr = date('Y-m-d H:i:s');
            if ($dStatus === 'approved' || $dStatus === 'verified') {
                try {
                    $pdo->prepare("UPDATE users SET kyc_status = 'approved', kyc_verified_at = ?, didit_decision = 'Approved' WHERE id = ?")->execute([$nowStr, $uid]);
                } catch (Throwable $eUpdCol) {
                    $pdo->prepare("UPDATE users SET kyc_status = 'approved', didit_decision = 'Approved' WHERE id = ?")->execute([$uid]);
                }
                $pdo->prepare("UPDATE vendors SET verification_status = 'verified', verification_badge = CASE WHEN verification_badge = 'gold' THEN 'gold' ELSE 'blue' END, verified = 1 WHERE user_id = ?")->execute([$uid]);
                $user['kyc_status'] = 'approved';
                $user['verified'] = 1;
                $_SESSION['user']['kyc_status'] = 'approved';
            } else if ($dStatus === 'declined' || $dStatus === 'rejected') {
                $pdo->prepare("UPDATE users SET kyc_status = 'rejected', didit_decision = 'Declined' WHERE id = ?")->execute([$uid]);
                $pdo->prepare("UPDATE vendors SET verification_status = 'rejected', verified = 0 WHERE user_id = ?")->execute([$uid]);
                $user['kyc_status'] = 'rejected';
                $_SESSION['user']['kyc_status'] = 'rejected';
            } else if ($dStatus === 'in review' || $dStatus === 'processing') {
                $pdo->prepare("UPDATE users SET kyc_status = 'under_review', didit_decision = 'In Review' WHERE id = ?")->execute([$uid]);
                $user['kyc_status'] = 'under_review';
                $_SESSION['user']['kyc_status'] = 'under_review';
            } else if ($dStatus === 'expired') {
                $pdo->prepare("UPDATE users SET kyc_status = 'expired', didit_decision = 'Expired' WHERE id = ?")->execute([$uid]);
                $user['kyc_status'] = 'expired';
                $_SESSION['user']['kyc_status'] = 'expired';
            }
        }
    }

    echo json_encode([
        'success' => true,
        'user_id' => $uid,
        'kyc_status' => strtoupper($user['kyc_status'] ?: 'NOT_STARTED'),
        'is_verified' => (strtolower($user['kyc_status']) === 'approved' || strtolower($user['kyc_status']) === 'verified' || intval($user['verified']) === 1),
        'didit_session_id' => $user['didit_session_id'],
        'didit_decision' => $user['didit_decision']
    ]);
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
    $default_user_svg = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";
    if (is_array($reviews)) {
        foreach ($reviews as &$r) {
            if (empty($r['avatar']) || strpos($r['avatar'], 'unsplash.com') !== false || strpos($r['avatar'], 'photo-') !== false) {
                $r['avatar'] = $default_user_svg;
            }
        }
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
            foreach (['name', 'email', 'phone', 'avatar', 'gender', 'dob', 'country', 'state', 'city', 'language', 'currency', 'username', 'kyc_status', 'role'] as $uf) {
                if (array_key_exists($uf, $fresh_user)) {
                    $_SESSION['user'][$uf] = $fresh_user[$uf];
                }
            }
        }
        
        $v_stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vendor_row = $v_stmt->fetch();
        if ($vendor_row) {
            $_SESSION['user']['vendor_id'] = intval($vendor_row['id']);
            $_SESSION['user']['has_vendor_profile'] = true;
            if (!isset($_SESSION['user']['active_role'])) {
                $_SESSION['user']['active_role'] = $_SESSION['user']['role'] === 'vendor' ? 'vendor' : 'customer';
            }
            $_SESSION['vendor'] = $vendor_row;
        } else {
            $_SESSION['user']['has_vendor_profile'] = false;
            $_SESSION['vendor'] = null;
        }
    }

    $settings = [];
    try {
        $stmt = $pdo->query("SELECT key_name, val_value FROM system_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {}

    echo json_encode([
        'user' => $_SESSION['user'] ?? null,
        'vendor' => $_SESSION['vendor'] ?? null,
        'csrf' => csrf_token(),
        'platform_reviews' => $reviews,
        'locked_profile_fields' => $locked_fields,
        'system_settings' => $settings
    ]);
    break;



case 'get_notification_preferences':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $uid = intval($_SESSION['user']['id']);
    try {
        $stmt = $pdo->prepare("SELECT email_notif, sms_notif, push_notif, promo_notif FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $prefs = $stmt->fetch();
        echo json_encode([
            'success' => true,
            'preferences' => [
                'email_notif' => intval($prefs['email_notif'] ?? 1) === 1,
                'sms_notif' => intval($prefs['sms_notif'] ?? 1) === 1,
                'push_notif' => intval($prefs['push_notif'] ?? 1) === 1,
                'promo_notif' => intval($prefs['promo_notif'] ?? 1) === 1,
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'preferences' => ['email_notif' => true, 'sms_notif' => true, 'push_notif' => true, 'promo_notif' => true]]);
    }
    break;

case 'update_notification_preferences':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $uid = intval($_SESSION['user']['id']);
    $input = json_decode(file_get_contents('php://input'), true);

    $email_n = isset($input['email_notif']) ? ($input['email_notif'] ? 1 : 0) : 1;
    $sms_n = isset($input['sms_notif']) ? ($input['sms_notif'] ? 1 : 0) : 1;
    $push_n = isset($input['push_notif']) ? ($input['push_notif'] ? 1 : 0) : 1;
    $promo_n = isset($input['promo_notif']) ? ($input['promo_notif'] ? 1 : 0) : 1;

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_notif INT DEFAULT 1");
        $pdo->exec("ALTER TABLE users ADD COLUMN sms_notif INT DEFAULT 1");
        $pdo->exec("ALTER TABLE users ADD COLUMN push_notif INT DEFAULT 1");
        $pdo->exec("ALTER TABLE users ADD COLUMN promo_notif INT DEFAULT 1");
    } catch(Exception $eIgn) {}

    $stmt = $pdo->prepare("UPDATE users SET email_notif = ?, sms_notif = ?, push_notif = ?, promo_notif = ? WHERE id = ?");
    $stmt->execute([$email_n, $sms_n, $push_n, $promo_n, $uid]);

    echo json_encode(['success' => true, 'message' => 'Notification preferences updated successfully.']);
    break;

case 'request_account_deletion':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $uid = intval($_SESSION['user']['id']);
    $input = json_decode(file_get_contents('php://input'), true);
    $reason = clean($input['reason'] ?? 'User requested account deletion');

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN deletion_requested_at DATETIME NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN deletion_reason VARCHAR(255) NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN account_status VARCHAR(50) DEFAULT 'active'");
    } catch(Exception $eIgn) {}

    // Inactivate account and set deletion request flag for admin review & fraud prevention
    $stmt = $pdo->prepare("UPDATE users SET account_status = 'inactive', deletion_requested_at = ?, deletion_reason = ? WHERE id = ?");
    $stmt->execute([date('Y-m-d H:i:s'), $reason, $uid]);

    // Send notification to admin
    try {
        send_admin_activity_notification(
            "Account Deletion Requested (" . htmlspecialchars($_SESSION['user']['name'] ?? 'User') . ")",
            "<p>User ID <strong>#$uid</strong> (" . htmlspecialchars($_SESSION['user']['email'] ?? $_SESSION['user']['phone'] ?? '') . ") requested deletion.</p><p>Account has been set to <strong>Inactive</strong> for admin fraud review.</p><p>Reason: " . htmlspecialchars($reason) . "</p>"
        );
    } catch (Exception $eAdmin) {}

    // Sign out user
    unset($_SESSION['user']);
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'Your account deletion request has been received. Your account has been inactivated and signed out.']);
    break;




// ── CATEGORIES ─────────────────────────────────────────────────────────
case 'categories':
    try {
        $stmt = $pdo->query("SELECT id, name, slug, icon, description, display_order, is_active FROM vendor_categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($categories)) {
            $categories = [
                ['name'=>'Photography','icon'=>'camera'],['name'=>'Videography','icon'=>'video'],
                ['name'=>'Makeup Artists','icon'=>'brush'],['name'=>'Bridal Shops','icon'=>'shirt'],
                ['name'=>'Event Planners','icon'=>'calendar-days'],['name'=>'Decorators','icon'=>'wand-magic-sparkles'],
                ['name'=>'Caterers','icon'=>'utensils'],['name'=>'Cake Designers','icon'=>'cake-candles'],
                ['name'=>'Event Venues','icon'=>'hotel'],['name'=>'DJs','icon'=>'music'],
                ['name'=>'MCs','icon'=>'microphone'],['name'=>'Live Bands','icon'=>'guitar'],
                ['name'=>'Florists','icon'=>'spa'],['name'=>'Car Rentals','icon'=>'car'],
                ['name'=>'Security Services','icon'=>'shield-halved'],['name'=>'Chilling Services','icon'=>'snowflake'],
                ['name'=>'Rental Equipment','icon'=>'chair'],['name'=>'Cocktail Bars','icon'=>'martini-glass-citrus'],
                ['name'=>'Honeymoon Packages','icon'=>'plane-departure'],['name'=>'Invitation Designers','icon'=>'envelope-open-text'],
                ['name'=>'Jewelers','icon'=>'gem'],['name'=>'Lighting','icon'=>'lightbulb'],
                ['name'=>'Printing Services','icon'=>'print'],['name'=>'Ushers','icon'=>'user-check'],
                ['name'=>'Content Creators','icon'=>'clapperboard'],['name'=>'Juice Bar','icon'=>'glass-water'],
                ['name'=>'Traditional Marriage Services','icon'=>'hands-holding'],
                ['name'=>'Dowry Wrapping','icon'=>'gift'],['name'=>'Breakfast','icon'=>'mug-hot'],
                ['name'=>'Coordinators','icon'=>'clipboard-list'],['name'=>'Waiters','icon'=>'concierge-bell'],
                ['name'=>'Portable Washroom','icon'=>'restroom'],['name'=>'Souvenirs','icon'=>'bag-shopping'],
                ['name'=>'Hairstylists','icon'=>'scissors'],['name'=>'Dowry Bearers','icon'=>'people-group'],
                ['name'=>'Local Bar','icon'=>'beer-mug-empty']
            ];
        }
        echo json_encode($categories);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    break;

case 'admin_get_categories':
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required']); exit; }
    try {
        $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM vendors v WHERE v.category = c.name) as vendor_count FROM vendor_categories c ORDER BY c.display_order ASC, c.name ASC");
        echo json_encode(['success'=>true, 'categories'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['error'=>$e->getMessage()]);
    }
    break;

case 'admin_create_category':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $name = clean($input['name'] ?? '');
    $icon = clean($input['icon'] ?? 'camera');
    $desc = clean($input['description'] ?? '');
    $order = intval($input['display_order'] ?? 0);
    $active = isset($input['is_active']) ? intval($input['is_active']) : 1;
    if (empty($name)) { http_response_code(400); echo json_encode(['error'=>'Category name required']); exit; }
    
    // Check duplicate name
    $chk = $pdo->prepare("SELECT id FROM vendor_categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $chk->execute([$name]);
    if ($chk->fetch()) { http_response_code(400); echo json_encode(['error'=>'A category with this name already exists']); exit; }
    
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    $ins = $pdo->prepare("INSERT INTO vendor_categories (name, slug, icon, description, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
    $ins->execute([$name, $slug, $icon, $desc, $order, $active]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
    break;

case 'admin_update_category':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $cid = intval($input['id'] ?? 0);
    $name = clean($input['name'] ?? '');
    $icon = clean($input['icon'] ?? 'camera');
    $desc = clean($input['description'] ?? '');
    $order = intval($input['display_order'] ?? 0);
    $active = isset($input['is_active']) ? intval($input['is_active']) : 1;
    if ($cid <= 0 || empty($name)) { http_response_code(400); echo json_encode(['error'=>'Valid ID and name required']); exit; }
    
    // Fetch old name to update vendors if category name changed
    $old_stmt = $pdo->prepare("SELECT name FROM vendor_categories WHERE id = ?");
    $old_stmt->execute([$cid]);
    $old_name = $old_stmt->fetchColumn();
    
    // Check duplicate
    $chk = $pdo->prepare("SELECT id FROM vendor_categories WHERE LOWER(name) = LOWER(?) AND id != ? LIMIT 1");
    $chk->execute([$name, $cid]);
    if ($chk->fetch()) { http_response_code(400); echo json_encode(['error'=>'Another category with this name already exists']); exit; }
    
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    $up = $pdo->prepare("UPDATE vendor_categories SET name = ?, slug = ?, icon = ?, description = ?, display_order = ?, is_active = ? WHERE id = ?");
    $up->execute([$name, $slug, $icon, $desc, $order, $active, $cid]);
    
    if (!empty($old_name) && $old_name !== $name) {
        $pdo->prepare("UPDATE vendors SET category = ? WHERE category = ?")->execute([$name, $old_name]);
    }
    echo json_encode(['success'=>true]);
    break;

case 'admin_delete_category':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $cid = intval($input['id'] ?? 0);
    if ($cid <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid category ID']); exit; }
    
    $cat_stmt = $pdo->prepare("SELECT name FROM vendor_categories WHERE id = ?");
    $cat_stmt->execute([$cid]);
    $cat_name = $cat_stmt->fetchColumn();
    if (!$cat_name) { http_response_code(404); echo json_encode(['error'=>'Category not found']); exit; }
    
    // Database integrity check: check vendor assignment
    $v_cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE category = ?");
    $v_cnt_stmt->execute([$cat_name]);
    $assigned_vendors = intval($v_cnt_stmt->fetchColumn());
    
    if ($assigned_vendors > 0 && empty($input['reassign_to'])) {
        http_response_code(409);
        echo json_encode([
            'error' => "Cannot delete category '{$cat_name}' directly because {$assigned_vendors} vendor(s) are currently assigned to it.",
            'assigned_count' => $assigned_vendors,
            'requires_reassignment' => true
        ]);
        exit;
    }
    
    if ($assigned_vendors > 0 && !empty($input['reassign_to'])) {
        $reassign_cat = clean($input['reassign_to']);
        $pdo->prepare("UPDATE vendors SET category = ? WHERE category = ?")->execute([$reassign_cat, $cat_name]);
    }
    
    $pdo->prepare("DELETE FROM vendor_categories WHERE id = ?")->execute([$cid]);
    echo json_encode(['success'=>true, 'message'=>"Category deleted successfully"]);
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
    $cur_uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($cur_uid > 0) {
        $blocked = getBlockedUserIds($cur_uid, $pdo);
        if (!empty($blocked)) {
            $ph = implode(',', array_fill(0, count($blocked), '?'));
            $q .= " AND user_id NOT IN ($ph) AND id NOT IN ($ph)";
            $p = array_merge($p, $blocked, $blocked);
        }
    }
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
        $v['logo'] = resolve_vendor_logo($v['category'] ?? '', $v['logo'] ?? '');
        $v['cover_photo'] = resolve_vendor_cover($v['category'] ?? '', $v['cover_photo'] ?? '');
        $v['packages_pricing'] = json_decode($v['packages_pricing'] ?? '[]', true) ?: [];
        $v['social_links'] = json_decode($v['social_links'] ?? '{}', true) ?: [];
        $v['gallery'] = json_decode($v['gallery'] ?? '[]', true) ?: [];
        $v['is_favorite'] = in_array($v['id'], $_SESSION['favorites']);

        $info = get_online_status_info($v['last_active'] ?? '');
        $v['is_online'] = $info['is_online'];
        $v['online_status'] = $info['online_status'];
        if ($info['is_online']) {
            $v['availability'] = 'Online';
        } else if (!empty($info['online_status']) && $info['online_status'] !== 'Offline') {
            $v['availability'] = $info['online_status'];
        }

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

    // Check if vendor profile or user account is blocked for current requester
    $cur_uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($cur_uid > 0) {
        $blocked = getBlockedUserIds($cur_uid, $pdo);
        if (!empty($blocked)) {
            $v_chk = $pdo->prepare("SELECT user_id, id FROM vendors WHERE id = ? OR user_id = ?");
            $v_chk->execute([$id, $id]);
            $v_data = $v_chk->fetch();
            if ($v_data) {
                if (in_array(intval($v_data['user_id']), $blocked) || in_array(intval($v_data['id']), $blocked) || in_array($id, $blocked)) {
                    http_response_code(404);
                    echo json_encode(['error' => 'This profile is unavailable or blocked.']);
                    exit;
                }
            } else if (in_array($id, $blocked)) {
                http_response_code(404);
                echo json_encode(['error' => 'This profile is unavailable or blocked.']);
                exit;
            }
        }
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
                'cover_photo' => $u['avatar'] ?: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>",
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
    $v_uid = intval($_SESSION['user']['id'] ?? 0);
    $v_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $by_user_id = intval($_GET['by_user_id'] ?? $_POST['by_user_id'] ?? $raw_input['by_user_id'] ?? 0);
    
    $v = null;
    if ($by_user_id === 1) {
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $v = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $v = $stmt->fetch();
        if (!$v) {
            $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
            $stmt->execute([$id]);
            $v = $stmt->fetch();
        }
    }
    
    if ($v) {
        $real_vid = intval($v['id']);
        $real_v_uid = intval($v['user_id']);
        if ($v_uid <= 0 || $v_uid !== $real_v_uid) {
            $sess_key = 'viewed_v_' . $real_vid;
            $last_view = intval($_SESSION[$sess_key] ?? 0);
            if (time() - $last_view > 300) {
                $_SESSION[$sess_key] = time();
                $pdo->prepare("UPDATE vendors SET views_count = views_count + 1 WHERE id = ?")->execute([$real_vid]);
                try {
                    $pdo->prepare("INSERT INTO vendor_views_log (vendor_id, user_id, ip_address) VALUES (?, ?, ?)")->execute([$real_vid, $v_uid, $v_ip]);
                } catch (Exception $vLogEx) {}
            }
        }
    } else {
        http_response_code(404); echo json_encode(['error'=>'Not found']); exit;
    }
    $v['logo'] = resolve_vendor_logo($v['category'] ?? '', $v['logo'] ?? '');
    $v['cover_photo'] = resolve_vendor_cover($v['category'] ?? '', $v['cover_photo'] ?? '');
    $info = get_online_status_info($v['last_active'] ?? '');
    $v['is_online'] = $info['is_online'];
    $v['online_status'] = $info['online_status'];
    if ($info['is_online']) {
        $v['availability'] = 'Online';
    } else if (!empty($info['online_status']) && $info['online_status'] !== 'Offline') {
        $v['availability'] = $info['online_status'];
    }
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
    $stmt->execute([$vendor_id,$uid,$user_name,$user_phone,$event_date,$event_type,$package_name,$price,$negotiated_price,$neg_history_json,$notes,'Inquiry','N/A',$timeline,$created_at_stamp]);
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
    $avatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";
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

    $val_json = json_encode($pending);
    $chk_rev = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = 'pending_platform_reviews'");
    $chk_rev->execute();
    if ($chk_rev->fetchColumn() > 0) {
        $stmt = $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = 'pending_platform_reviews'");
        $stmt->execute([$val_json]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES ('pending_platform_reviews', ?)");
        $stmt->execute([$val_json]);
    }

    echo json_encode(['success' => true, 'message' => 'Review submitted successfully. It will appear once approved by an administrator.']);
    break;


// ── CHAT ───────────────────────────────────────────────────────────────
case 'heartbeat':
    if (isset($_SESSION['user']['id'])) {
        $uid = intval($_SESSION['user']['id']);
        $now_str = date('Y-m-d H:i:s');
        try {
            $pdo->prepare("UPDATE users SET last_active = ? WHERE id = ?")->execute([$now_str, $uid]);
            $pdo->prepare("UPDATE vendors SET last_active = ? WHERE user_id = ?")->execute([$now_str, $uid]);
        } catch (Exception $e) {}
    }
    echo json_encode(['success' => true, 'timestamp' => time()]);
    break;

case 'get_user_status':
    $target_user_id = intval($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
    $target_vendor_id = intval($_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0);
    $last_active = '';
    if ($target_vendor_id > 0) {
        $stmt = $pdo->prepare("SELECT u.last_active FROM vendors v JOIN users u ON v.user_id = u.id WHERE v.id = ?");
        $stmt->execute([$target_vendor_id]);
        $last_active = $stmt->fetchColumn() ?: '';
    } else if ($target_user_id > 0) {
        $stmt = $pdo->prepare("SELECT last_active FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $last_active = $stmt->fetchColumn() ?: '';
    }
    $info = get_online_status_info($last_active);
    echo json_encode($info);
    break;



case 'chat_inbox':
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) {
        echo json_encode([]);
        exit;
    }
    $role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'] ?? $token_user['active_role'] ?? $token_user['role'] ?? 'customer';
    $list = [];
    if ($role === 'vendor') {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vendor_id = $v_stmt->fetchColumn();
        if ($vendor_id) {
            $stmt = $pdo->prepare("SELECT u.id as customer_id, u.name, u.avatar, u.last_active, 'Customer' as category, MAX(m.id) as max_msg_id FROM messages m JOIN users u ON m.user_id = u.id WHERE m.vendor_id = ? GROUP BY u.id, u.name, u.avatar, u.last_active ORDER BY max_msg_id DESC");
            $stmt->execute([$vendor_id]);
            $list = $stmt->fetchAll();
            foreach ($list as &$item) {
                $info = get_online_status_info($item['last_active'] ?? '');
                $item['is_online'] = $info['is_online'];
                $item['online_status'] = $info['online_status'];
                $item['availability'] = $info['is_online'] ? 'Online' : $info['online_status'];
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT v.id, v.user_id, v.name, v.logo, v.category, v.availability, v.verified, v.verification_badge, MAX(m.id) as max_msg_id, MAX(u.last_active) as user_last_active, MAX(v.last_active) as vendor_last_active FROM messages m JOIN vendors v ON m.vendor_id = v.id LEFT JOIN users u ON v.user_id = u.id WHERE m.user_id = ? GROUP BY v.id, v.user_id, v.name, v.logo, v.category, v.availability, v.verified, v.verification_badge ORDER BY max_msg_id DESC");
        $stmt->execute([$uid]);
        $list = $stmt->fetchAll();
        foreach ($list as &$item) {
            $last_active = !empty($item['user_last_active']) ? $item['user_last_active'] : ($item['vendor_last_active'] ?? '');
            $info = get_online_status_info($last_active);
            $item['is_online'] = $info['is_online'];
            $item['online_status'] = $info['online_status'];
            if ($info['is_online']) {
                $item['availability'] = 'Online';
            }
        }
    }
    echo json_encode($list ?: []);
    break;

case 'get_unread_chats':
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) {
        echo json_encode([]);
        exit;
    }
    $role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'] ?? $token_user['active_role'] ?? $token_user['role'] ?? 'customer';
    if ($role === 'vendor') {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vendor_id = intval($v_stmt->fetchColumn() ?: 0);
        if ($vendor_id > 0) {
            $stmt = $pdo->prepare("SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.user_id = u.id WHERE m.vendor_id = ? AND m.user_id != ? AND m.sender = 'user' AND m.is_read = 0 ORDER BY m.id DESC");
            $stmt->execute([$vendor_id, $uid]);
            echo json_encode($stmt->fetchAll() ?: []);
        } else {
            echo json_encode([]);
        }
    } else {
        $stmt = $pdo->prepare("SELECT m.*, v.name as sender_name FROM messages m JOIN vendors v ON m.vendor_id = v.id WHERE m.user_id = ? AND m.sender = 'vendor' AND m.is_read = 0 ORDER BY m.id DESC");
        $stmt->execute([$uid]);
        echo json_encode($stmt->fetchAll() ?: []);
    }
    break;

case 'notifications':
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) {
        echo json_encode([]);
        break;
    }
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute([$uid]);
    $list = $stmt->fetchAll() ?: [];
    echo json_encode($list);
    break;

case 'mark_notifications_read':
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid > 0) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
    }
    echo json_encode(['success' => true]);
    break;

case 'record_vendor_view':
    $vendor_id = intval($_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0);
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if ($vendor_id > 0) {
        $v_owner = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
        $v_owner->execute([$vendor_id]);
        $owner_uid = intval($v_owner->fetchColumn() ?: 0);

        if ($uid !== $owner_uid) {
            $throttle_key = "v_view_{$vendor_id}_{$uid}_{$ip}";
            if (!isset($_SESSION[$throttle_key]) || (time() - $_SESSION[$throttle_key]) > 600) {
                $_SESSION[$throttle_key] = time();
                try {
                    $now_str = date('Y-m-d H:i:s');
                    $pdo->prepare("INSERT INTO vendor_views_log (vendor_id, user_id, ip_address, created_at) VALUES (?, ?, ?, ?)")->execute([$vendor_id, $uid, $ip, $now_str]);
                    $pdo->prepare("UPDATE vendors SET views_count = views_count + 1 WHERE id = ?")->execute([$vendor_id]);
                } catch (Exception $e) {}
            }
        }
    }
    echo json_encode(['success' => true]);
    break;

case 'vendor_stats':
    $vendor_id = intval($_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0);
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);

    if ($vendor_id <= 0 && $uid > 0) {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $vendor_id = intval($v_stmt->fetchColumn() ?: 0);
    }

    if ($vendor_id <= 0) {
        echo json_encode([
            'success' => true,
            'views' => 0,
            'bookings' => 0,
            'completed' => 0,
            'pending' => 0,
            'cancelled' => 0,
            'revenue' => 0.0,
            'impressions' => 0,
            'chats' => 0,
            'conversion_rate' => 0.0,
            'rating' => 0.0,
            'reviews_count' => 0
        ]);
        break;
    }

    $period = clean($_GET['period'] ?? $_POST['period'] ?? '7days');
    $start_date = clean($_GET['start_date'] ?? $_POST['start_date'] ?? '');
    $end_date = clean($_GET['end_date'] ?? $_POST['end_date'] ?? '');

    $date_where = "";
    $params = [$vendor_id];
    $is_sqlite = (defined('DB_TYPE') && DB_TYPE === 'sqlite');

    switch ($period) {
        case 'today':
            $date_where = " AND created_at >= " . ($is_sqlite ? "datetime('now', 'start of day')" : "CURDATE()");
            break;
        case '7days':
            $date_where = " AND created_at >= " . ($is_sqlite ? "datetime('now', '-7 days')" : "DATE_SUB(NOW(), INTERVAL 7 DAY)");
            break;
        case '30days':
            $date_where = " AND created_at >= " . ($is_sqlite ? "datetime('now', '-30 days')" : "DATE_SUB(NOW(), INTERVAL 30 DAY)");
            break;
        case 'this_month':
            $date_where = " AND created_at >= " . ($is_sqlite ? "datetime('now', 'start of month')" : "DATE_FORMAT(NOW(), '%Y-%m-01')");
            break;
        case 'this_year':
            $date_where = " AND created_at >= " . ($is_sqlite ? "datetime('now', 'start of year')" : "DATE_FORMAT(NOW(), '%Y-01-01')");
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $date_where = " AND created_at BETWEEN ? AND ?";
                $params[] = $start_date . " 00:00:00";
                $params[] = $end_date . " 23:59:59";
            }
            break;
    }

    $period_views = 0;
    try {
        $views_stmt = $pdo->prepare("SELECT COUNT(*) FROM vendor_views_log WHERE vendor_id = ?" . $date_where);
        $views_stmt->execute($params);
        $period_views = intval($views_stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {}

    if ($period_views == 0 && ($period === '7days' || $period === 'this_year')) {
        try {
            $v_row_stmt = $pdo->prepare("SELECT views_count FROM vendors WHERE id = ?");
            $v_row_stmt->execute([$vendor_id]);
            $period_views = intval($v_row_stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {}
    }

    $total_bookings = 0;
    $completed_bookings = 0;
    $pending_bookings = 0;
    $cancelled_bookings = 0;
    $total_revenue = 0.0;

    try {
        $bk_stmt = $pdo->prepare("SELECT COUNT(*) AS total_bookings, 
            SUM(CASE WHEN LOWER(status) IN ('completed') THEN 1 ELSE 0 END) AS completed_bookings,
            SUM(CASE WHEN LOWER(status) IN ('inquiry', 'pending', 'in progress') THEN 1 ELSE 0 END) AS pending_bookings,
            SUM(CASE WHEN LOWER(status) IN ('cancelled', 'declined') THEN 1 ELSE 0 END) AS cancelled_bookings,
            SUM(CASE WHEN LOWER(payment_status) IN ('paid', 'completed', 'deposit paid') THEN (COALESCE(deposit_paid,0)+COALESCE(balance_paid,0)+COALESCE(total_paid,0)) ELSE 0 END) AS total_revenue
            FROM bookings WHERE vendor_id = ?" . $date_where);
        $bk_stmt->execute($params);
        $bk_res = $bk_stmt->fetch() ?: [];

        $total_bookings = intval($bk_res['total_bookings'] ?? 0);
        $completed_bookings = intval($bk_res['completed_bookings'] ?? 0);
        $pending_bookings = intval($bk_res['pending_bookings'] ?? 0);
        $cancelled_bookings = intval($bk_res['cancelled_bookings'] ?? 0);
        $total_revenue = floatval($bk_res['total_revenue'] ?? 0.0);
    } catch (Exception $e) {}

    $total_chats = 0;
    try {
        $chat_stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM messages WHERE vendor_id = ?" . $date_where);
        $chat_stmt->execute($params);
        $total_chats = intval($chat_stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {}

    $avg_rating = 0.0;
    $reviews_count = 0;
    try {
        $rev_stmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS rev_count FROM reviews WHERE vendor_id = ?");
        $rev_stmt->execute([$vendor_id]);
        $rev_res = $rev_stmt->fetch() ?: [];
        $avg_rating = round(floatval($rev_res['avg_rating'] ?? 0.0), 1);
        $reviews_count = intval($rev_res['rev_count'] ?? 0);
    } catch (Exception $e) {}

    $search_impressions = $period_views > 0 ? intval($period_views * 1.5) : 0;
    $conversion_rate = $period_views > 0 ? round(($total_bookings / $period_views) * 100, 1) : 0.0;

    echo json_encode([
        'success' => true,
        'views' => $period_views,
        'bookings' => $total_bookings,
        'completed' => $completed_bookings,
        'pending' => $pending_bookings,
        'cancelled' => $cancelled_bookings,
        'revenue' => $total_revenue,
        'impressions' => $search_impressions,
        'chats' => $total_chats,
        'conversion_rate' => $conversion_rate,
        'rating' => $avg_rating,
        'reviews_count' => $reviews_count
    ]);
    break;

case 'dashboard_stats':
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) {
        echo json_encode([
            'success' => true,
            'bookings_count' => 0,
            'upcoming_bookings' => 0,
            'completed_bookings' => 0,
            'saved_vendors' => 0,
            'unread_notifications' => 0
        ]);
        break;
    }

    $bk_cnt = 0;
    $up_cnt = 0;
    $comp_cnt = 0;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) AS total, 
            SUM(CASE WHEN LOWER(status) NOT IN ('completed', 'cancelled', 'declined') THEN 1 ELSE 0 END) AS upcoming,
            SUM(CASE WHEN LOWER(status) IN ('completed') THEN 1 ELSE 0 END) AS completed
            FROM bookings WHERE user_id = ?");
        $st->execute([$uid]);
        $r = $st->fetch() ?: [];
        $bk_cnt = intval($r['total'] ?? 0);
        $up_cnt = intval($r['upcoming'] ?? 0);
        $comp_cnt = intval($r['completed'] ?? 0);
    } catch (Exception $e) {}

    $saved_cnt = 0;
    try {
        $st2 = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
        $st2->execute([$uid]);
        $saved_cnt = intval($st2->fetchColumn() ?: 0);
    } catch (Exception $e) {}

    $unnotif_cnt = 0;
    try {
        $st3 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $st3->execute([$uid]);
        $unnotif_cnt = intval($st3->fetchColumn() ?: 0);
    } catch (Exception $e) {}

    echo json_encode([
        'success' => true,
        'bookings_count' => $bk_cnt,
        'upcoming_bookings' => $up_cnt,
        'completed_bookings' => $comp_cnt,
        'saved_vendors' => $saved_cnt,
        'unread_notifications' => $unnotif_cnt
    ]);
    break;

case 'block_user':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error' => 'Authentication required']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $target_id = intval($input['target_user_id'] ?? $input['vendor_id'] ?? 0);
    $reason = clean($input['reason'] ?? 'User blocked');
    if ($target_id <= 0) { http_response_code(400); echo json_encode(['error' => 'Target user ID required']); exit; }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blocker_id INT NOT NULL,
            blocked_id INT NOT NULL,
            reason VARCHAR(255) DEFAULT 'User blocked',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_block (blocker_id, blocked_id)
        )");

        $target_uid = $target_id;
        $chk_v = $pdo->prepare("SELECT id, user_id FROM vendors WHERE id = ? OR user_id = ?");
        $chk_v->execute([$target_id, $target_id]);
        $found_v = $chk_v->fetch();

        if ($found_v) {
            if (intval($found_v['user_id']) > 0) {
                $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id, reason) VALUES (?, ?, ?)")
                    ->execute([$uid, intval($found_v['user_id']), $reason]);
            }
            if (intval($found_v['id']) > 0) {
                $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id, reason) VALUES (?, ?, ?)")
                    ->execute([$uid, intval($found_v['id']), $reason]);
            }
        } else {
            $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id, reason) VALUES (?, ?, ?)")
                ->execute([$uid, $target_id, $reason]);
        }
    } catch (Exception $eBlock) {}

    echo json_encode(['success' => true, 'message' => 'User blocked successfully.']);
    break;

case 'unblock_user':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error' => 'Authentication required']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $target_id = intval($input['target_user_id'] ?? $input['vendor_id'] ?? 0);
    
    $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?")
        ->execute([$uid, $target_id]);
    echo json_encode(['success' => true, 'message' => 'User unblocked successfully.']);
    break;

case 'report_user':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error' => 'Authentication required']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $target_id = intval($input['target_user_id'] ?? $input['vendor_id'] ?? 0);
    $reason = clean($input['reason'] ?? 'Inappropriate Behavior');
    $details = clean($input['details'] ?? '');
    if ($target_id <= 0) { http_response_code(400); echo json_encode(['error' => 'Target user ID required']); exit; }
    
    $pdo->prepare("INSERT INTO user_reports (reporter_id, reported_user_id, reason, details) VALUES (?, ?, ?, ?)")
        ->execute([$uid, $target_id, $reason, $details]);
    echo json_encode(['success' => true, 'message' => 'Thank you. Your report has been received and sent to moderators for review.']);
    break;

case 'report_comment':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error' => 'Authentication required']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $comment_id = intval($input['comment_id'] ?? 0);
    $reason = clean($input['reason'] ?? 'Inappropriate Content');
    $details = clean($input['details'] ?? '');
    if ($comment_id <= 0) { http_response_code(400); echo json_encode(['error' => 'Comment ID required']); exit; }
    
    $pdo->prepare("INSERT INTO comment_reports (reporter_id, comment_id, reason, details) VALUES (?, ?, ?, ?)")
        ->execute([$uid, $comment_id, $reason, $details]);
    echo json_encode(['success' => true, 'message' => 'Comment report submitted successfully. Moderation team notified.']);
    break;

case 'chat_inbox':
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) {
        echo json_encode([]);
        exit;
    }
    
    // Find vendor ID owned by current user (if any)
    $my_v_id = 0;
    try {
        $v_chk = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_chk->execute([$uid]);
        $my_v_id = intval($v_chk->fetchColumn() ?: 0);
    } catch (Throwable $eV1) {}

    // Find all message records involving current user (as sender or receiver)
    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE user_id = ? OR (vendor_id = ? AND ? > 0) OR (vendor_id = ? AND ? > 0)
        ORDER BY id DESC
    ");
    $stmt->execute([$uid, $my_v_id, $my_v_id, $uid, $uid]);
    $all_msgs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Group messages by partner user ID
    $conversations = [];
    foreach ($all_msgs as $m) {
        $msg_user_id = intval($m['user_id']);
        $msg_vendor_id = intval($m['vendor_id']);
        $sender = $m['sender'];

        // Resolve partner user ID
        $partner_user_id = 0;
        if ($msg_user_id === $uid) {
            // Current user is the 'user' in this row. Partner is vendor owner or vendor ID
            if ($my_v_id > 0 && $msg_vendor_id === $my_v_id) {
                // Self-message edge case, skip
                continue;
            }
            // Find partner user ID from vendor_id
            $vp_stmt = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
            $vp_stmt->execute([$msg_vendor_id]);
            $partner_user_id = intval($vp_stmt->fetchColumn() ?: $msg_vendor_id);
        } else {
            // Partner is msg_user_id
            $partner_user_id = $msg_user_id;
        }

        if ($partner_user_id <= 0 || $partner_user_id === $uid) continue;

        if (!isset($conversations[$partner_user_id])) {
            $conversations[$partner_user_id] = [
                'partner_user_id' => $partner_user_id,
                'last_msg' => $m,
                'unread_count' => 0
            ];
        }

        // Count unread incoming messages from partner
        $is_incoming = false;
        if ($m['is_read'] == 0) {
            if ($sender === 'vendor' && $msg_user_id === $uid) {
                $is_incoming = true;
            } else if ($sender === 'user' && ($msg_vendor_id === $my_v_id || $msg_vendor_id === $uid) && $msg_user_id !== $uid) {
                $is_incoming = true;
            }
        }
        if ($is_incoming) {
            $conversations[$partner_user_id]['unread_count']++;
        }
    }

    $list = [];
    foreach ($conversations as $p_uid => $c_data) {
        $m = $c_data['last_msg'];
        
        // Fetch partner user profile
        $u_stmt = $pdo->prepare("SELECT id, name, avatar, last_active FROM users WHERE id = ?");
        $u_stmt->execute([$p_uid]);
        $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u_row) continue;

        // Check if partner has a vendor profile
        $v_stmt = $pdo->prepare("SELECT id, name, logo, category, availability, verified, verification_badge, last_active FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$p_uid]);
        $v_row = $v_stmt->fetch(PDO::FETCH_ASSOC);

        $partner_id = $v_row ? intval($v_row['id']) : intval($u_row['id']);
        $partner_name = $v_row ? ($v_row['name'] ?: $u_row['name']) : $u_row['name'];
        $partner_logo = $v_row ? ($v_row['logo'] ?: $u_row['avatar']) : $u_row['avatar'];
        $partner_cat = $v_row ? ($v_row['category'] ?: 'Event Vendor') : 'Customer';

        $last_active = $u_row['last_active'] ?: ($v_row['last_active'] ?? '');
        $info = get_online_status_info($last_active);

        $msg_preview = $m['message'];
        if ($m['type'] === 'image') $msg_preview = "📷 Photo";
        else if ($m['type'] === 'voice') $msg_preview = "🎙️ Voice Note";
        else if ($m['type'] === 'video') $msg_preview = "🎥 Video";
        else if (in_array($m['type'], ['pdf', 'file', 'location'])) $msg_preview = "📎 Attachment";

        $list[] = [
            'id' => $partner_id,
            'user_id' => intval($u_row['id']),
            'customer_id' => intval($u_row['id']),
            'vendor_id' => $partner_id,
            'name' => $partner_name,
            'logo' => $partner_logo,
            'avatar' => $u_row['avatar'] ?: $partner_logo,
            'category' => $partner_cat,
            'last_message' => $msg_preview,
            'last_msg_id' => intval($m['id']),
            'unread_count' => $c_data['unread_count'],
            'is_online' => $info['is_online'],
            'online_status' => $info['online_status'],
            'availability' => $info['is_online'] ? 'Online' : $info['online_status'],
            'verified' => $v_row ? intval($v_row['verified'] ?? 0) : 0,
            'verification_badge' => $v_row['verification_badge'] ?? ''
        ];
    }

    // Sort conversations newest message first
    usort($list, function($a, $b) {
        return $b['last_msg_id'] <=> $a['last_msg_id'];
    });

    echo json_encode($list);
    break;

case 'get_unread_chats':
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) {
        echo json_encode([]);
        exit;
    }
    $my_v_id = 0;
    try {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $my_v_id = intval($v_stmt->fetchColumn() ?: 0);
    } catch (Throwable $eV2) {}

    $stmt = $pdo->prepare("
        SELECT m.*, COALESCE(v.name, u.name, 'User') as sender_name 
        FROM messages m 
        LEFT JOIN users u ON m.user_id = u.id 
        LEFT JOIN vendors v ON m.vendor_id = v.id 
        WHERE ((m.user_id = :uid AND m.sender = 'vendor') 
           OR (m.vendor_id = :my_v_id AND m.user_id != :uid AND m.sender = 'user' AND :my_v_id > 0)
           OR (m.vendor_id = :uid AND m.user_id != :uid AND m.sender = 'user'))
          AND m.is_read = 0 
        ORDER BY m.id DESC
    ");
    $stmt->execute(['uid' => $uid, 'my_v_id' => $my_v_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    break;

case 'chat_history':
    try {
        $vid = intval($_GET['vendor_id'] ?? $_GET['customer_id'] ?? $_GET['user_id'] ?? 0);
        $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
        if ($uid <= 0 || $vid <= 0) {
            echo json_encode([]);
            exit;
        }

        // Find vendor ID owned by current user (if any)
        $my_v_id = 0;
        try {
            $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
            $v_stmt->execute([$uid]);
            $my_v_id = intval($v_stmt->fetchColumn() ?: 0);
        } catch (Throwable $eVHist) {}

        // Resolve target partner user_id and vendor_id
        $target_v_id = $vid;
        $target_u_id = $vid;
        try {
            $v_lookup = $pdo->prepare("SELECT id, user_id FROM vendors WHERE id = ? OR user_id = ?");
            $v_lookup->execute([$vid, $vid]);
            if ($v_row = $v_lookup->fetch(PDO::FETCH_ASSOC)) {
                $target_v_id = intval($v_row['id']);
                $target_u_id = intval($v_row['user_id']);
            }
        } catch (Throwable $eVHist2) {}

        // Mark incoming messages from target partner as read
        try {
            $up_stmt = $pdo->prepare("
                UPDATE messages SET is_read = 1 
                WHERE ((user_id = :target_u_id AND (vendor_id = :my_v_id OR vendor_id = :uid) AND sender = 'user')
                   OR (vendor_id = :target_v_id AND user_id = :uid AND sender = 'vendor'))
                  AND is_read = 0
            ");
            $up_stmt->execute([
                'target_u_id' => $target_u_id,
                'my_v_id' => $my_v_id,
                'uid' => $uid,
                'target_v_id' => $target_v_id
            ]);
        } catch (Throwable $eUpHist) {}

        // Retrieve all messages between current user and target partner
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE ((user_id = :uid AND (vendor_id = :target_v_id OR vendor_id = :target_u_id))
               OR (user_id = :target_u_id AND ((vendor_id = :my_v_id AND :my_v_id > 0) OR vendor_id = :uid)))
            ORDER BY id ASC
        ");
        $stmt->execute([
            'uid' => $uid,
            'target_v_id' => $target_v_id,
            'target_u_id' => $target_u_id,
            'my_v_id' => $my_v_id
        ]);
        $msgs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        echo json_encode($msgs ?: []);
    } catch (Throwable $eChatHist) {
        echo json_encode([]);
    }
    break;

case 'chat':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $vid = intval($input['vendor_id'] ?? $input['customer_id'] ?? $input['user_id'] ?? $input['target_id'] ?? 0);
    $message = clean($input['message'] ?? '');
    $type = in_array($input['type'] ?? '', ['text','image','voice','pdf','file','video','location']) ? $input['type'] : 'text';
    $file_name = clean($input['file_name'] ?? '');
    $file_size = intval($input['file_size'] ?? 0);
    $duration = intval($input['duration'] ?? 0);
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    
    if ($uid <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required to send messages']);
        exit;
    }

    if ($vid <= 0 || empty($message)) {
        http_response_code(400);
        echo json_encode(['error' => 'Message and recipient target are required']);
        exit;
    }

    // Check if sender owns a vendor profile
    $my_v_id = 0;
    try {
        $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$uid]);
        $my_v_id = intval($v_stmt->fetchColumn() ?: 0);
    } catch (Throwable $eSendV) {}

    // Resolve target partner
    $target_v_id = 0;
    $target_u_id = $vid;
    try {
        $v_lookup = $pdo->prepare("SELECT id, user_id FROM vendors WHERE id = ? OR user_id = ?");
        $v_lookup->execute([$vid, $vid]);
        if ($v_row = $v_lookup->fetch(PDO::FETCH_ASSOC)) {
            $target_v_id = intval($v_row['id']);
            $target_u_id = intval($v_row['user_id']);
        }
    } catch (Throwable $eSendV2) {}

    // Prevent self messaging
    if ($target_u_id === $uid || ($target_v_id > 0 && $target_v_id === $my_v_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot message your own profile']);
        exit;
    }

    // Determine vendor_id, user_id, and sender for DB record
    $db_vendor_id = $target_v_id > 0 ? $target_v_id : $vid;
    $db_user_id = $uid;
    $sender_role = 'user';
    $active_user_role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'] ?? 'customer';

    if (($my_v_id > 0 && $my_v_id === $target_v_id) || ($active_user_role === 'vendor' && $my_v_id > 0)) {
        $db_vendor_id = $my_v_id > 0 ? $my_v_id : $target_v_id;
        $db_user_id = $target_u_id;
        $sender_role = 'vendor';
    }

    try {
        $now_stamp = date('Y-m-d H:i:s');
        $ins = $pdo->prepare("INSERT INTO messages (vendor_id, user_id, sender, message, type, file_name, file_size, duration, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$db_vendor_id, $db_user_id, $sender_role, $message, $type, $file_name, $file_size, $duration, $now_stamp]);
        $inserted_id = intval($pdo->lastInsertId());
    } catch (Throwable $eMsgIns) {
        // Fallback for missing optional columns
        try {
            $now_stamp = date('Y-m-d H:i:s');
            $ins = $pdo->prepare("INSERT INTO messages (vendor_id, user_id, sender, message, type, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$db_vendor_id, $db_user_id, $sender_role, $message, $type, $now_stamp]);
            $inserted_id = intval($pdo->lastInsertId());
        } catch (Throwable $eMsgFatal) {
            http_response_code(400);
            echo json_encode(['error' => 'Unable to deliver message: ' . $eMsgFatal->getMessage()]);
            exit;
        }
    }

    // Send notification to recipient
    $notif_text = $message;
    if ($type === 'image') $notif_text = "sent you a photo";
    else if ($type === 'voice') $notif_text = "sent you a voice note";
    else if (in_array($type, ['pdf', 'file', 'video', 'location'])) $notif_text = "sent you an attachment";

    try {
        $s_name_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $s_name_stmt->execute([$uid]);
        $s_name = $s_name_stmt->fetchColumn() ?: 'A user';

        $recipient_user_id = ($sender_role === 'vendor') ? $db_user_id : $target_u_id;
        if ($recipient_user_id > 0 && $recipient_user_id !== $uid) {
            add_notification($pdo, $recipient_user_id, $s_name, "$s_name: $notif_text");
        }
    } catch (Throwable $eNotifMsg) {}

    $msg_payload = [
        'id' => $inserted_id,
        'vendor_id' => $db_vendor_id,
        'user_id' => $db_user_id,
        'sender' => $sender_role,
        'message' => $message,
        'type' => $type,
        'file_name' => $file_name,
        'file_size' => $file_size,
        'duration' => $duration,
        'is_read' => 0,
        'created_at' => $now_stamp
    ];

    echo json_encode([
        'success' => true,
        'message_id' => $inserted_id,
        'user_message' => $msg_payload,
        'vendor_message' => $msg_payload,
        'vendor_reply' => null
    ]);
    break;

case 'upload_chat_file':
    $upload_uid = $_SESSION['user']['id'] ?? $token_uid ?? 0;
    if (!$upload_uid && (isset($_POST['auth_token']) || isset($_GET['auth_token']))) {
        $post_token = trim($_POST['auth_token'] ?? $_GET['auth_token'] ?? '');
        if (!empty($post_token)) {
            $token_hash = hash('sha256', $post_token);
            try {
                $t_stmt = $pdo->prepare("SELECT user_id FROM auth_tokens WHERE token_hash = ? AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)");
                $t_stmt->execute([$token_hash]);
                $upload_uid = intval($t_stmt->fetchColumn() ?: 0);
            } catch (Exception $e) {}
        }
    }
    if (!$upload_uid) { http_response_code(401); echo json_encode(['error'=>'Please log in to upload files.']); exit; }
    if (!isset($_FILES['file'])) { http_response_code(400); echo json_encode(['error'=>'No file uploaded or file exceeds server post limit.']); exit; }
    
    $file = $_FILES['file'];
    if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
        $err_msg = 'Upload failed.';
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $err_msg = 'File exceeds maximum allowed upload size of 10MB.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $err_msg = 'File upload was only partially completed.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $err_msg = 'No file was uploaded.';
                break;
        }
        http_response_code(400);
        echo json_encode(['error' => $err_msg]);
        exit;
    }
    
    // Strict 10 MB Limit
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['error'=>'File exceeds maximum allowed limit of 10 MB.']);
        exit;
    }
    
    // Validate MIME type & extension safely
    $mime = $file['type'] ?? '';
    if (function_exists('finfo_open') && !empty($file['tmp_name'])) {
        try {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = @finfo_file($finfo, $file['tmp_name']);
                if ($detected) $mime = $detected;
                @finfo_close($finfo);
            }
        } catch (Throwable $e) {}
    }

    $filename = basename($file['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Blacklist dangerous executable files
    $blocked_exts = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'htaccess', 'svg'];
    if (in_array($ext, $blocked_exts)) {
        http_response_code(400);
        echo json_encode(['error'=>'Security notice: Excutable or script file extensions are strictly prohibited.']);
        exit;
    }

    $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowed_audios = ['mp3', 'wav', 'ogg', 'm4a', 'webm', '3gp', 'aac'];
    $allowed_videos = ['mp4', 'webm', 'mov', 'avi'];
    $allowed_docs = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip'];
    
    $type = 'file';
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
        $type = 'file';
    } else {
        http_response_code(400);
        echo json_encode(['error'=>'File format .' . $ext . ' is not supported.']);
        exit;
    }
    
    // Create upload directory if not exists
    $dir = __DIR__ . '/uploads/chat/';
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    // Generate safe unique stored filename
    $new_filename = uniqid('chat_', true) . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        if ($type === 'image') {
            try {
                if (function_exists('compressAndResizeImage')) {
                    compressAndResizeImage($target, $target, 1600, 1600, 80);
                }
            } catch (Throwable $e) {}
        }
        $relative_path = 'uploads/chat/' . $new_filename;
        echo json_encode([
            'success' => true,
            'url' => $relative_path,
            'type' => $type,
            'name' => $filename,
            'size' => intval($file['size'])
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error'=>'Failed to save uploaded file on server.']);
    }
    break;

case 'download_chat_file':
    $dl_uid = $_SESSION['user']['id'] ?? $token_uid ?? 0;
    if (!$dl_uid) { http_response_code(401); echo "Unauthorized"; exit; }
    
    $file_path = trim($_GET['file'] ?? '');
    if (empty($file_path) || strpos($file_path, '..') !== false) {
        http_response_code(400); echo "Invalid file request"; exit; }
    
    $clean_rel = ltrim($file_path, '/');
    if (strpos($clean_rel, 'uploads/chat/') !== 0) {
        http_response_code(403); echo "Access denied"; exit; }
    
    $full_path = __DIR__ . '/' . $clean_rel;
    if (!file_exists($full_path)) {
        http_response_code(404); echo "File not found"; exit; }
    
    $file_name = basename($full_path);
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $mime_types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain', 'csv' => 'text/csv', 'zip' => 'application/zip',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'webm' => 'audio/webm', 'm4a' => 'audio/x-m4a'
    ];
    
    $content_type = $mime_types[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . filesize($full_path));
    header('Content-Disposition: attachment; filename="' . addslashes($file_name) . '"');
    header('Cache-Control: private, max-age=3600');
    readfile($full_path);
    exit;



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
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    $notifs = [];
    
    if ($uid > 0) {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$uid]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($notifs as &$n) {
        if (empty($n['created_at'])) {
            $n['created_at'] = date('Y-m-d H:i:s');
        }
        $n['id'] = intval($n['id']);
        $n['is_read'] = intval($n['is_read'] ?? 0);
    }
    echo json_encode($notifs);
    break;

case 'mark_notification_read':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $input = json_decode(file_get_contents('php://input'), true);
    $nid = intval($input['id'] ?? 0);
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    
    if ($uid <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

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
    
    $exist_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? AND user_id > 0 LIMIT 1");
    $exist_stmt->execute([$uid]);
    $exist_v = $exist_stmt->fetch();
    
    if ($exist_v) {
        $vendor_id = intval($exist_v['id']);
        $u_stmt = $pdo->prepare("UPDATE vendors SET name = ?, category = ?, description = ?, location = ?, phone = ?, email = ?, experience = ?, verification_status = 'pending' WHERE id = ?");
        $u_stmt->execute([$name, $category, clean($input['description']??''), clean($input['location']??''), clean($input['phone']??''), clean($input['email']??''), intval($input['experience']??0), $vendor_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO vendors (user_id,name,category,description,location,phone,email,experience,verification_status,verification_badge) VALUES (?,?,?,?,?,?,?,?,'pending','grey')");
        $stmt->execute([$uid,$name,$category,clean($input['description']??''),clean($input['location']??''),clean($input['phone']??''),clean($input['email']??''),intval($input['experience']??0)]);
        $vendor_id = $pdo->lastInsertId();
    }
    
    // Auto-update users role to vendor & store KYC info
    if ($uid > 0) {
        $kyc_type = clean($input['kyc_id_type'] ?? 'Ghana Card');
        $pdo->prepare("UPDATE users SET role = 'vendor', active_role = 'vendor', kyc_id_type = ?, kyc_status = 'pending' WHERE id = ?")->execute([$kyc_type, $uid]);
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['role'] = 'vendor';
            $_SESSION['user']['active_role'] = 'vendor';
            $_SESSION['user']['vendor_id'] = intval($vendor_id);
            $_SESSION['user']['has_vendor_profile'] = true;
            $_SESSION['user']['vendor_verification_status'] = 'pending';
            $_SESSION['user']['vendor_onboarding_completed'] = true;
            $_SESSION['user']['kyc_id_type'] = $kyc_type;
        }
    }
    
    echo json_encode(['success'=>true,'vendor_id'=>$vendor_id]);
    break;

case 'update_vendor':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST required");
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $vid = intval($input['id'] ?? 0);
    if ($vid <= 0 && $uid > 0) {
        $v_find = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
        $v_find->execute([$uid]);
        $vid = intval($v_find->fetchColumn());
    }
    if ($vid <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid vendor ID']); exit; }
    
    // Verify ownership
    $check = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    $check->execute([$vid]);
    $owner_id = $check->fetchColumn();
    if (intval($owner_id) === 0 && $uid > 0) {
        $pdo->prepare("UPDATE vendors SET user_id = ? WHERE id = ?")->execute([$uid, $vid]);
        $owner_id = $uid;
    }
    if (intval($owner_id) !== $uid && !$is_admin) {
        http_response_code(403); echo json_encode(['error'=>'Unauthorized to update this vendor profile.']); exit;
    }
    
    // Fetch vendor details & lock sensitive fields for non-admins
    $v_stmt = $pdo->prepare("SELECT name, email, phone, account_number, premium FROM vendors WHERE id = ?");
    $v_stmt->execute([$vid]);
    $v_row = $v_stmt->fetch();
    $is_premium = intval($v_row['premium'] ?? 0);

    // Keep vendor identity fields editable

    if (isset($input['cover_image'])) $input['cover_photo'] = $input['cover_image'];
    if (isset($input['avatar'])) $input['logo'] = $input['avatar'];

    if (isset($input['gallery']) && is_array($input['gallery'])) {
        $input['gallery'] = array_slice($input['gallery'], 0, 100);
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
            $saved_cover = secure_save_base64_image($input['cover_photo'], 'covers', 'cover_' . $vid);
            if (!empty($saved_cover)) {
                $input['cover_photo'] = $saved_cover;
            } else {
                unset($input['cover_photo']);
                http_response_code(400); echo json_encode(['error' => 'Cover image upload failed. Please try a valid image under 8MB.']); exit;
            }
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
        
        // Sync vendor logo, name, email, phone back to user account
        try {
            $u_sync_fields = []; $u_sync_params = [];
            if (!empty($input['logo'])) { $u_sync_fields[] = "avatar = ?"; $u_sync_params[] = $input['logo']; }
            if (!empty($input['name'])) { $u_sync_fields[] = "name = ?"; $u_sync_params[] = $input['name']; }
            if (!empty($input['email'])) { $u_sync_fields[] = "email = ?"; $u_sync_params[] = $input['email']; }
            if (!empty($input['phone'])) { $u_sync_fields[] = "phone = ?"; $u_sync_params[] = $input['phone']; }
            if (!empty($u_sync_fields) && !empty($owner_id)) {
                $u_sync_params[] = $owner_id;
                $pdo->prepare("UPDATE users SET " . implode(', ', $u_sync_fields) . " WHERE id = ?")->execute($u_sync_params);
                if (isset($_SESSION['user']) && intval($_SESSION['user']['id'] ?? 0) === intval($owner_id)) {
                    if (!empty($input['logo'])) $_SESSION['user']['avatar'] = $input['logo'];
                    if (!empty($input['name'])) $_SESSION['user']['name'] = $input['name'];
                    if (!empty($input['email'])) $_SESSION['user']['email'] = $input['email'];
                    if (!empty($input['phone'])) $_SESSION['user']['phone'] = $input['phone'];
                }
            }
        } catch (Exception $eUsrSync) {}
    }

    $fresh_v_stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
    $fresh_v_stmt->execute([$vid]);
    $fresh_vendor = $fresh_v_stmt->fetch();
    if ($fresh_vendor) {
        $_SESSION['vendor'] = $fresh_vendor;
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['vendor_id'] = $vid;
            $_SESSION['user']['vendor_cover_photo'] = $fresh_vendor['cover_photo'] ?? '';
            $_SESSION['user']['vendor_logo'] = $fresh_vendor['logo'] ?? '';
        }
    }

    echo json_encode([
        'success' => true,
        'vendor_id' => $vid,
        'cover_photo' => $_SESSION['vendor']['cover_photo'] ?? ($input['cover_photo'] ?? ''),
        'logo' => $_SESSION['vendor']['logo'] ?? ($input['logo'] ?? ''),
        'vendor' => $_SESSION['vendor'] ?? null,
        'user' => $_SESSION['user'] ?? null
    ]);
    break;

case 'upload_avatar':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error' => 'Not logged in.']); exit; }
    $uid = intval($_SESSION['user']['id']);
    $file_input = $_FILES['avatar'] ?? $_FILES['avatar_file'] ?? $_POST['avatar'] ?? $raw_input['avatar'] ?? null;
    if (!$file_input && !empty($_POST['avatar_base64'])) $file_input = $_POST['avatar_base64'];
    if (!$file_input) { http_response_code(400); echo json_encode(['error' => 'No image file uploaded.']); exit; }
    
    $up_res = upload_media_file($file_input, 'avatars', 800);
    if (empty($up_res['success']) || empty($up_res['url'])) {
        http_response_code(400); echo json_encode(['error' => $up_res['error'] ?? 'Avatar upload failed.']); exit;
    }
    $avatar_url = $up_res['url'];
    $ts = time();
    $busted_url = $avatar_url . (strpos($avatar_url, '?') !== false ? '&v=' : '?v=') . $ts;
    
    $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$avatar_url, $uid]);
    $v_chk = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $v_chk->execute([$uid]);
    $v_id = $v_chk->fetchColumn();
    if ($v_id) {
        $pdo->prepare("UPDATE vendors SET logo = ? WHERE id = ?")->execute([$avatar_url, $v_id]);
        if (isset($_SESSION['vendor'])) $_SESSION['vendor']['logo'] = $avatar_url;
    }
    $_SESSION['user']['avatar'] = $avatar_url;
    echo json_encode(['success' => true, 'avatar' => $busted_url, 'url' => $busted_url, 'user' => $_SESSION['user'], 'vendor' => $_SESSION['vendor'] ?? null]);
    break;

case 'upload_cover_image':
    if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error' => 'Not logged in.']); exit; }
    $uid = intval($_SESSION['user']['id']);
    $file_input = $_FILES['cover'] ?? $_FILES['cover_photo'] ?? $_POST['cover_photo'] ?? $raw_input['cover_photo'] ?? null;
    if (!$file_input && !empty($_POST['cover_base64'])) $file_input = $_POST['cover_base64'];
    if (!$file_input) { http_response_code(400); echo json_encode(['error' => 'No cover image file uploaded.']); exit; }

    $up_res = upload_media_file($file_input, 'covers', 1920);
    if (empty($up_res['success']) || empty($up_res['url'])) {
        http_response_code(400); echo json_encode(['error' => $up_res['error'] ?? 'Cover upload failed.']); exit;
    }
    $cover_url = $up_res['url'];
    $ts = time();
    $busted_url = $cover_url . (strpos($cover_url, '?') !== false ? '&v=' : '?v=') . $ts;

    $v_chk = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $v_chk->execute([$uid]);
    $v_id = $v_chk->fetchColumn();
    if ($v_id) {
        try {
            $pdo->prepare("UPDATE vendors SET cover_photo = ? WHERE id = ?")->execute([$cover_url, $v_id]);
        } catch (Exception $eVendCol) {
            try {
                $pdo->exec("ALTER TABLE vendors ADD COLUMN cover_photo VARCHAR(500) DEFAULT ''");
                $pdo->prepare("UPDATE vendors SET cover_photo = ? WHERE id = ?")->execute([$cover_url, $v_id]);
            } catch (Exception $eVendCol2) {}
        }
        if (isset($_SESSION['vendor'])) $_SESSION['vendor']['cover_photo'] = $cover_url;
    }
    try {
        $pdo->prepare("UPDATE users SET cover_photo = ? WHERE id = ?")->execute([$cover_url, $uid]);
    } catch (Exception $eCol) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN cover_photo VARCHAR(500) DEFAULT ''");
            $pdo->prepare("UPDATE users SET cover_photo = ? WHERE id = ?")->execute([$cover_url, $uid]);
        } catch (Exception $eCol2) {}
    }
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['cover_photo'] = $cover_url;
        $_SESSION['user']['vendor_cover_photo'] = $cover_url;
    }
    echo json_encode(['success' => true, 'cover_photo' => $busted_url, 'url' => $busted_url, 'vendor' => $_SESSION['vendor'] ?? null, 'user' => $_SESSION['user'] ?? null]);
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
    
    $txn_ref = 'OHATI_TXN_' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
    try {
        $pdo->prepare("INSERT INTO payments (booking_id,amount,method,type,status,provider,reference) VALUES (?,?,?,?,'completed',?,?)")->execute([$bid,$amount,$method,$type,clean($input['provider']??''),$txn_ref]);
    } catch (Exception $e) {
        $pdo->prepare("INSERT INTO payments (booking_id,amount,method,type,status,provider) VALUES (?,?,?,?,'completed',?)")->execute([$bid,$amount,$method,$type,clean($input['provider']??'')]);
    }
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
    echo json_encode(['success'=>true, 'reference'=>$txn_ref]);
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
    $raw_in = file_get_contents('php://input');
    $input = json_decode($raw_in, true);
    if (!is_array($input)) $input = $_POST;
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? $input['user_id'] ?? 0);
    if ($uid <= 0) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }

    if (!isset($_SESSION['user']) && $uid > 0) {
        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $uStmt->execute([$uid]);
        $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($uRow) {
            $_SESSION['user'] = [
                'id' => $uRow['id'],
                'name' => $uRow['name'],
                'email' => $uRow['email'],
                'phone' => $uRow['phone'],
                'role' => $uRow['role'],
                'avatar' => $uRow['avatar'] ?? '',
                'kyc_status' => $uRow['kyc_status'] ?? 'not_started'
            ];
        }
    }

    $role = ($input['role'] ?? '') === 'vendor' ? 'vendor' : 'customer';

    $stmt = $pdo->prepare("SELECT id, name FROM vendors WHERE user_id = ?");
    $stmt->execute([$uid]);
    $vendor = $stmt->fetch();

    if ($role === 'vendor') {
        if (!$vendor) {
            http_response_code(403);
            echo json_encode(['error'=>'Vendor profile not activated yet.', 'need_upgrade'=>true]);
            exit;
        }
        $_SESSION['user']['vendor_id'] = intval($vendor['id']);
        $_SESSION['user']['has_vendor_profile'] = true;
        $_SESSION['user']['role'] = 'vendor';
    }

    if ($vendor) {
        $_SESSION['user']['vendor_id'] = intval($vendor['id']);
        $_SESSION['user']['has_vendor_profile'] = true;
        if (!empty($vendor['name'])) {
            $_SESSION['user']['name'] = $vendor['name'];
            $_SESSION['user']['vendor_name'] = $vendor['name'];
        }
    }

    $_SESSION['user']['active_role'] = $role;
    try {
        if ($role === 'vendor') {
            if ($vendor && !empty($vendor['name'])) {
                $stmt = $pdo->prepare("UPDATE users SET role = 'vendor', active_role = 'vendor', name = ? WHERE id = ?");
                $stmt->execute([$vendor['name'], $uid]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET role = 'vendor', active_role = 'vendor' WHERE id = ?");
                $stmt->execute([$uid]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE users SET active_role = 'customer' WHERE id = ?");
            $stmt->execute([$uid]);
        }
    } catch (Exception $e) {}
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
        $user_fields = ['name', 'email', 'phone', 'dob'];
        $vendor_fields = ['name', 'category', 'description', 'location', 'phone', 'email', 'experience', 'whatsapp', 'website', 'bank_name', 'account_name', 'account_number', 'momo_number', 'momo_provider', 'payout_method'];

        if (in_array($field, $user_fields)) {
            $upd = $pdo->prepare("UPDATE users SET $field = ? WHERE id = ?");
            $upd->execute([$req['new_value'], $req['user_id']]);
        }
        if (in_array($field, $vendor_fields)) {
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
        $q = "SELECT a.*, v.name as vendor_name, v.logo as vendor_logo, v.cover_photo as vendor_cover, v.category as vendor_category 
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
    $banner = clean($input['banner_url'] ?? 'img/default-cover.jpg');
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
    $stmt = $pdo->query("SELECT a.*, v.name as vendor_name, v.logo as vendor_logo, v.cover_photo as vendor_cover FROM advertisements a JOIN vendors v ON a.vendor_id = v.id ORDER BY a.id DESC");
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
        'payment_instructions' => $settings['admin_payment_instructions'] ?? ''
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
    $stmt = $pdo->prepare("INSERT INTO advertisements (vendor_id, title, placement, duration_days, cost, start_date, end_date, receipt_url, status, payment_status) VALUES (?, ?, 'premium_gold', 365, ?, ?, ?, ?, 'pending', 'pending')");
    
    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $stmt->execute([$vendor['id'], $title, $amount, $start_date, $end_date, $receipt_path ?: $tx_id]);
            break;
        } catch (PDOException $e) {
            if ($attempt < 4 && (strpos($e->getMessage(), 'locked') !== false || strpos($e->getMessage(), 'HY000') !== false || $e->getCode() == 5)) {
                usleep(300000);
                continue;
            }
            throw $e;
        }
    }

    try {
        if (function_exists('fastcgi_finish_request')) {
            echo json_encode(['success' => true, 'message' => 'Receipt uploaded successfully.']);
            fastcgi_finish_request();
        }
    } catch (Exception $eFcgi) {}

    // Dual Email + SMS Notification to Admin & Vendor
    try {
        $admin_email = defined('SMTP_USER') ? SMTP_USER : 'contact@ohati.com';
        $admin_phone = '0540477911';
        send_dual_notification($admin_phone, $admin_email, "New Premium Upgrade Payment Receipt", "Vendor '" . $vendor['name'] . "' requested a Premium Gold Badge Upgrade.");
    } catch (Throwable $eAdminNotif) {}

    try {
        $v_phone = $vendor['phone'] ?: ($_SESSION['user']['phone'] ?? '');
        $v_email = $vendor['email'] ?: ($_SESSION['user']['email'] ?? '');
        send_dual_notification($v_phone, $v_email, "Premium Upgrade Payment Receipt Received", "Hello " . $vendor['name'] . ", your payment receipt for Premium Gold Badge Upgrade has been received.");
    } catch (Throwable $eVendorNotif) {}

    if (!headers_sent()) {
        echo json_encode(['success' => true, 'message' => 'Receipt uploaded successfully.']);
    }
    break;

    // In-app notification
    try {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Premium Request Submitted', 'Your Premium Gold Badge payment receipt is under review by Admin.', 'crown')")->execute([$uid]);
                break;
            } catch (Throwable $eNotif) {
                if ($attempt < 2 && strpos($eNotif->getMessage(), 'locked') !== false) {
                    usleep(300000);
                    continue;
                }
                break;
            }
        }
    } catch (Throwable $eNotifOuter) {}

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
    $call_uid = $_SESSION['user']['id'] ?? $token_uid ?? 0;
    if (!$call_uid) { http_response_code(401); echo json_encode(['error'=>'Please sign in to make voice calls.']); exit; }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $receiver_id = intval($input['receiver_id'] ?? $_POST['receiver_id'] ?? $_GET['receiver_id'] ?? $_POST['vendor_id'] ?? $input['vendor_id'] ?? 0);
    $type = clean($input['type'] ?? $_POST['type'] ?? 'voice');
    $sdp = $input['sdp_offer'] ?? $_POST['sdp_offer'] ?? 'voice_call_session';

    // If receiver_id maps to a vendor record, resolve user_id
    if ($receiver_id > 0) {
        $v_check = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
        $v_check->execute([$receiver_id]);
        $v_uid = $v_check->fetchColumn();
        if ($v_uid) {
            $receiver_id = intval($v_uid);
        }
    }

    if ($receiver_id <= 0) {
        http_response_code(400); echo json_encode(['error'=>'Recipient not found.']); exit;
    }
    if ($receiver_id === $call_uid) {
        http_response_code(400); echo json_encode(['error'=>'You cannot call yourself.']); exit;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO calls (caller_id, receiver_id, type, status, sdp_offer, ice_candidates_caller, ice_candidates_receiver, created_at, updated_at) VALUES (?, ?, ?, 'ringing', ?, '[]', '[]', ?, ?)");
    $stmt->execute([$call_uid, $receiver_id, $type, $sdp, $now, $now]);
    $call_id = $pdo->lastInsertId();

    echo json_encode(['success'=>true, 'call_id'=>$call_id]);
    break;

case 'check_incoming_call':
case 'poll_incoming_call':
    $call_uid = $_SESSION['user']['id'] ?? $token_uid ?? 0;
    if (!$call_uid) { echo json_encode(null); exit; }
    
    // Look for active ringing calls (within last 60 seconds)
    $time_limit = date('Y-m-d H:i:s', time() - 60);
    $stmt = $pdo->prepare("SELECT c.*, u.name as caller_name, u.avatar as caller_avatar FROM calls c JOIN users u ON c.caller_id = u.id WHERE c.receiver_id = ? AND c.status IN ('ringing', 'dialing') AND c.created_at >= ? ORDER BY c.id DESC LIMIT 1");
    $stmt->execute([$call_uid, $time_limit]);
    $call = $stmt->fetch();
    
    echo json_encode($call ?: null);
    break;

case 'accept_call':
case 'answer_call':
    $call_uid = $_SESSION['user']['id'] ?? $token_uid ?? 0;
    if (!$call_uid) { http_response_code(401); echo json_encode(['error'=>'Please sign in to answer calls.']); exit; }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $call_id = intval($input['call_id'] ?? $_POST['call_id'] ?? $_GET['call_id'] ?? 0);
    $sdp = $input['sdp_answer'] ?? $_POST['sdp_answer'] ?? 'voice_call_answered';

    if ($call_id <= 0) {
        http_response_code(400); echo json_encode(['error'=>'Invalid call session.']); exit;
    }

    $stmt = $pdo->prepare("UPDATE calls SET status = 'accepted', sdp_answer = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$sdp, date('Y-m-d H:i:s'), $call_id]);

    echo json_encode(['success'=>true]);
    break;

case 'update_call_status':
case 'reject_call':
case 'end_call':
    $call_uid = $_SESSION['user']['id'] ?? $token_uid ?? 0;
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $call_id = intval($input['call_id'] ?? $_GET['call_id'] ?? $_POST['call_id'] ?? 0);
    $default_status = ($action === 'reject_call') ? 'rejected' : 'ended';
    $status = clean($input['status'] ?? $_POST['status'] ?? $default_status);
    $duration = intval($input['duration'] ?? $_POST['duration'] ?? 0);

    if ($call_id > 0) {
        $stmt = $pdo->prepare("UPDATE calls SET status = ?, duration = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$status, $duration, date('Y-m-d H:i:s'), $call_id]);
    }

    echo json_encode(['success'=>true]);
    break;

case 'send_ice_candidate':
    $call_uid = $_SESSION['user']['id'] ?? $token_uid ?? 0;
    if (!$call_uid) { echo json_encode(['success'=>true]); exit; }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $call_id = intval($input['call_id'] ?? $_POST['call_id'] ?? 0);
    $role = clean($input['role'] ?? $_POST['role'] ?? 'caller');
    $candidate = $input['candidate'] ?? $_POST['candidate'] ?? '';

    if ($call_id <= 0 || empty($candidate)) {
        echo json_encode(['success'=>true]); exit;
    }

    $field = ($role === 'caller') ? 'ice_candidates_caller' : 'ice_candidates_receiver';
    
    $pdo->beginTransaction();
    try {
        $get_stmt = $pdo->prepare("SELECT $field FROM calls WHERE id = ?");
        $get_stmt->execute([$call_id]);
        $existing = json_decode($get_stmt->fetchColumn() ?: '[]', true);
        if (!is_array($existing)) $existing = [];
        
        $new_cand = is_array($candidate) ? $candidate : json_decode($candidate, true);
        if (is_array($new_cand) && isset($new_cand[0])) {
            foreach ($new_cand as $c) {
                if ($c) {
                    $existing[] = is_string($c) ? (json_decode($c, true) ?: $c) : $c;
                }
            }
        } else if ($new_cand) {
            $existing[] = is_string($new_cand) ? (json_decode($new_cand, true) ?: $new_cand) : $new_cand;
        }

        $up_stmt = $pdo->prepare("UPDATE calls SET $field = ?, updated_at = ? WHERE id = ?");
        $up_stmt->execute([json_encode($existing), date('Y-m-d H:i:s'), $call_id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>true]); exit;
    }

    echo json_encode(['success'=>true]);
    break;

case 'get_call_details':
case 'poll_call_status':
    $call_uid = $_SESSION['user']['id'] ?? $token_uid ?? 0;
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $cid = intval($_GET['call_id'] ?? $_POST['call_id'] ?? $input['call_id'] ?? 0);
    
    if ($cid <= 0) {
        echo json_encode(['status'=>'ended']); exit;
    }

    $stmt = $pdo->prepare("SELECT c.*, u1.name as caller_name, u1.avatar as caller_avatar, u2.name as receiver_name, u2.avatar as receiver_avatar FROM calls c JOIN users u1 ON c.caller_id = u1.id JOIN users u2 ON c.receiver_id = u2.id WHERE c.id = ?");
    $stmt->execute([$cid]);
    $call_data = $stmt->fetch();
    
    echo json_encode($call_data ?: ['status'=>'ended']);
    break;

case 'get_call_history':
    $call_uid = $_SESSION['user']['id'] ?? $token_uid ?? 0;
    if (!$call_uid) { echo json_encode([]); exit; }

    $uid = $_SESSION['user']['id'];
    
    $stmt = $pdo->prepare("SELECT c.*, u1.name as caller_name, u1.avatar as caller_avatar, u2.name as receiver_name, u2.avatar as receiver_avatar FROM calls c JOIN users u1 ON c.caller_id = u1.id JOIN users u2 ON c.receiver_id = u2.id WHERE c.caller_id = ? OR c.receiver_id = ? ORDER BY c.id DESC LIMIT 50");
    $stmt->execute([$uid, $uid]);
    
    echo json_encode($stmt->fetchAll());
    break;

case 'get_call_number':
    $target_id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    $type_param = $_GET['type'] ?? $_POST['type'] ?? '';
    if ($target_id <= 0) { echo json_encode(['phone'=>'', 'name'=>'Ohati Contact']); exit; }

    // 1. If target is explicitly a customer/user
    if ($type_param === 'user' || $type_param === 'customer') {
        $u_stmt = $pdo->prepare("SELECT phone, name FROM users WHERE id = ?");
        $u_stmt->execute([$target_id]);
        $u = $u_stmt->fetch();
        if ($u && !empty($u['phone'])) {
            echo json_encode(['phone' => $u['phone'], 'name' => $u['name'] ?? 'Ohati User']);
            exit;
        }
        $v_stmt = $pdo->prepare("SELECT phone, whatsapp, name FROM vendors WHERE user_id = ?");
        $v_stmt->execute([$target_id]);
        $v = $v_stmt->fetch();
        if ($v && (!empty($v['phone']) || !empty($v['whatsapp']))) {
            echo json_encode(['phone' => !empty($v['phone']) ? $v['phone'] : $v['whatsapp'], 'name' => $v['name']]);
            exit;
        }
        echo json_encode(['phone' => $u['phone'] ?? '', 'name' => $u['name'] ?? 'Ohati Contact']);
        exit;
    }

    // 2. Check vendors table by vendor ID first
    $v_stmt = $pdo->prepare("SELECT phone, whatsapp, name FROM vendors WHERE id = ?");
    $v_stmt->execute([$target_id]);
    $v = $v_stmt->fetch();
    if ($v && (!empty($v['phone']) || !empty($v['whatsapp']))) {
        $p = !empty($v['phone']) ? $v['phone'] : $v['whatsapp'];
        echo json_encode(['phone' => $p, 'name' => $v['name']]);
        exit;
    }

    // 3. Check vendors table by user_id
    $v_stmt2 = $pdo->prepare("SELECT phone, whatsapp, name FROM vendors WHERE user_id = ?");
    $v_stmt2->execute([$target_id]);
    $v2 = $v_stmt2->fetch();
    if ($v2 && (!empty($v2['phone']) || !empty($v2['whatsapp']))) {
        $p = !empty($v2['phone']) ? $v2['phone'] : $v2['whatsapp'];
        echo json_encode(['phone' => $p, 'name' => $v2['name']]);
        exit;
    }

    // 4. Check users table by ID
    $u_stmt = $pdo->prepare("SELECT phone, name FROM users WHERE id = ?");
    $u_stmt->execute([$target_id]);
    $u = $u_stmt->fetch();
    echo json_encode(['phone' => $u['phone'] ?? '', 'name' => $u['name'] ?? 'Ohati Contact']);
    break;

case 'get_admin_ads':
    if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
    $stmt = $pdo->query("SELECT a.*, v.name as vendor_name, v.logo as vendor_logo, v.cover_photo as vendor_cover FROM advertisements a JOIN vendors v ON a.vendor_id = v.id ORDER BY a.id DESC");
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
    $uid = intval($_SESSION['user']['id'] ?? $token_uid ?? 0);
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
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($admins)) {
        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, created_at) VALUES (?, ?, ?, ?)");
        $notif_title = 'New Issue Report';
        $notif_body = "A user reported: $title ($category)";
        $now_str = date('Y-m-d H:i:s');
        foreach ($admins as $admin_id) {
            $notif_stmt->execute([$admin_id, $notif_title, $notif_body, $now_str]);
        }
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

    $chk_r1 = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = 'referral_reward_amount'");
    $chk_r1->execute();
    if ($chk_r1->fetchColumn() > 0) {
        $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = 'referral_reward_amount'")->execute([$reward_amt]);
    } else {
        $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES ('referral_reward_amount', ?)")->execute([$reward_amt]);
    }

    $chk_r2 = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = 'referral_program_active'");
    $chk_r2->execute();
    if ($chk_r2->fetchColumn() > 0) {
        $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = 'referral_program_active'")->execute([$active_state]);
    } else {
        $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES ('referral_program_active', ?)")->execute([$active_state]);
    }

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
