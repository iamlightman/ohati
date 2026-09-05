<?php
// auth_guard.php - Ohati User Authentication Protection Guard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Allowed public pages that do not require login
$public_pages = [
    'login.php',
    'not_in_use_login.php',
    'register.php',
    'forgot-password.php',
    'reset_password.php',
    'debug_login.php',
    'privacy_policy.php',
    'terms.php'
];

$current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Check if user is authenticated via session
$is_authenticated = isset($_SESSION['user']) && !empty($_SESSION['user']['id']);

// Check for Bearer token in headers (for API / mobile clients)
if (!$is_authenticated) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $auth_header, $matches)) {
        $token = trim($matches[1]);
        if (!empty($token) && file_exists(__DIR__ . '/db.php')) {
            require_once __DIR__ . '/db.php';
            try {
                if (isset($pdo) && $pdo instanceof PDO) {
                    $token_hash = hash('sha256', $token);
                    $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone, u.role, u.active_role, u.avatar, u.kyc_status FROM users u JOIN auth_tokens t ON u.id = t.user_id WHERE t.token_hash = ? AND (t.expires_at IS NULL OR t.expires_at > ?) LIMIT 1");
                    $stmt->execute([$token_hash, date('Y-m-d H:i:s')]);
                    $u_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($u_row) {
                        $_SESSION['user'] = [
                            'id' => intval($u_row['id']),
                            'name' => $u_row['name'],
                            'email' => $u_row['email'],
                            'phone' => $u_row['phone'],
                            'role' => $u_row['role'],
                            'avatar' => $u_row['avatar'],
                            'kyc_status' => $u_row['kyc_status'] ?? 'not_started',
                            'active_role' => !empty($u_row['active_role']) ? $u_row['active_role'] : ($u_row['role'] === 'vendor' ? 'vendor' : 'customer')
                        ];
                        $is_authenticated = true;
                    }
                }
            } catch (Throwable $t) {
                // Ignore errors during bearer token verification fallback
            }
        }
    }
}

// If page is public, allow access
if (in_array($current_script, $public_pages, true)) {
    return;
}

// If unauthenticated, handle redirect or JSON error
if (!$is_authenticated) {
    $is_json = (
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
        (isset($_GET['action']) || isset($_POST['action']))
    );

    if ($is_json && $current_script === 'api.php') {
        // Allow public API actions
        $public_api_actions = ['login', 'register', 'verify_otp', 'request_password_reset', 'reset_password', 'get_maintenance_status', 'check_session'];
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        if (in_array($action, $public_api_actions, true)) {
            return;
        }

        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required. Please log in.',
            'redirect' => 'login.php'
        ]);
        exit;
    }

    // Redirect browser requests to login.php
    header('Location: login.php');
    exit;
}
