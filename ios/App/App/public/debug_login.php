<?php
// debug_login.php — Ohati Login & Backend Diagnostic Tool
// Access this file in your browser: http://your-domain.com/debug_login.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

$results = [
    'config_loaded' => false,
    'db_connected' => false,
    'db_driver' => 'Unknown',
    'db_error' => null,
    'tables' => [],
    'user_count' => 0,
    'demo_users' => [],
    'test_login_result' => null
];

// 1. Check ohati_config.php
if (file_exists(__DIR__ . '/ohati_config.php')) {
    require_once __DIR__ . '/ohati_config.php';
    $results['config_loaded'] = true;
}

// 2. Check Database Connection via db.php
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        $results['db_connected'] = true;
        try {
            $results['db_driver'] = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Exception $e) {
            $results['db_driver'] = 'PDO Active';
        }
    } else {
        $results['db_error'] = 'PDO instance not initialized in db.php';
    }
} else {
    $results['db_error'] = 'db.php file missing!';
}

// 3. Inspect Required Tables if connected
$required_tables = ['users', 'login_history', 'otp_codes', 'system_settings', 'vendors'];
if ($results['db_connected']) {
    foreach ($required_tables as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            $results['tables'][$table] = true;
        } catch (Exception $e) {
            $results['tables'][$table] = false;
        }
    }
    
    // Get user count & sample users
    if ($results['tables']['users']) {
        try {
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $results['user_count'] = (int)$count_stmt->fetchColumn();
            
            $users_stmt = $pdo->query("SELECT id, name, email, phone, role, is_active, email_verified, phone_verified FROM users LIMIT 10");
            $results['demo_users'] = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Error reading users
        }
    }
}

// 4. Handle Live Interactive API Test POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['test_login'])) {
    $test_identifier = trim($_POST['identifier'] ?? '');
    $test_password = $_POST['password'] ?? '';
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $api_url = $protocol . $host . $dir . '/api.php?action=login';

    $payload = json_encode([
        'identifier' => $test_identifier,
        'password' => $test_password
    ]);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $json_decoded = json_decode($response_body, true);
    $is_valid_json = (json_last_error() === JSON_ERROR_NONE);

    $results['test_login_result'] = [
        'api_url' => $api_url,
        'http_code' => $http_code,
        'curl_error' => $curl_error,
        'is_valid_json' => $is_valid_json,
        'json_error' => json_last_error_msg(),
        'raw_response' => $response_body,
        'decoded' => $json_decoded
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ohati API & Login Diagnostic Tool</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0F172A; color: #F8FAFC; margin: 0; padding: 40px 20px; line-height: 1.5; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #1E293B; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        h1 { color: #F2A735; font-size: 1.8rem; margin-top: 0; }
        h2 { color: #38BDF8; font-size: 1.2rem; margin-top: 0; border-bottom: 1px solid #334155; padding-bottom: 8px; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 0.85rem; }
        .badge-success { background: #059669; color: #ECFDF5; }
        .badge-danger { background: #DC2626; color: #FEF2F2; }
        .badge-warning { background: #D97706; color: #FFFBEB; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9rem; }
        th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #334155; }
        th { color: #94A3B8; font-weight: 600; }
        .code-box { background: #020617; border: 1px solid #334155; border-radius: 8px; padding: 14px; font-family: monospace; font-size: 0.85rem; color: #F8FAFC; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
        input[type="text"], input[type="password"] { width: 100%; max-width: 300px; padding: 10px; border-radius: 6px; border: 1px solid #475569; background: #0F172A; color: #FFF; margin-bottom: 12px; box-sizing: border-box; }
        button { background: #F2A735; color: #0F172A; border: none; font-weight: bold; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        button:hover { background: #E09420; }
    </style>
</head>
<body>
<div class="container">
    <h1>🛠️ Ohati Login & Server Diagnostic Tool</h1>

    <!-- 1. System & Database Health -->
    <div class="card">
        <h2>1. Database Connection Status</h2>
        <p>
            <strong>Config File (`ohati_config.php`):</strong> 
            <?php if ($results['config_loaded']): ?>
                <span class="status-badge badge-success">Found & Loaded</span>
            <?php else: ?>
                <span class="status-badge badge-warning">Not Found (Using Fallbacks)</span>
            <?php endif; ?>
        </p>
        <p>
            <strong>Database Connection (`$pdo`):</strong> 
            <?php if ($results['db_connected']): ?>
                <span class="status-badge badge-success">Connected (Driver: <?= htmlspecialchars($results['db_driver']) ?>)</span>
            <?php else: ?>
                <span class="status-badge badge-danger">FAILED CONNECTION</span>
            <?php endif; ?>
        </p>
        <?php if ($results['db_error']): ?>
            <div class="code-box" style="color: #FCA5A5;"><?= htmlspecialchars($results['db_error']) ?></div>
        <?php endif; ?>
    </div>

    <!-- 2. Database Tables Check -->
    <?php if ($results['db_connected']): ?>
    <div class="card">
        <h2>2. Database Tables Status</h2>
        <table>
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['tables'] as $tbl => $exists): ?>
                <tr>
                    <td><code><?= htmlspecialchars($tbl) ?></code></td>
                    <td>
                        <?php if ($exists): ?>
                            <span class="status-badge badge-success">Exists</span>
                        <?php else: ?>
                            <span class="status-badge badge-danger">MISSING TABLE!</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3 style="color:#F2A735; margin-top:20px;">Users in Database: <?= $results['user_count'] ?></h3>
        <?php if (!empty($results['demo_users'])): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email / Phone</th>
                        <th>Role</th>
                        <th>Active</th>
                        <th>Email Ver.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['demo_users'] as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email'] ?: $u['phone']) ?></td>
                        <td><?= htmlspecialchars($u['role']) ?></td>
                        <td><?= $u['is_active'] == 1 ? 'Yes' : 'No (' . $u['is_active'] . ')' ?></td>
                        <td><?= $u['email_verified'] ? 'Yes' : 'No' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #FCA5A5;">No users found in database! You can run <code>import_demo_accounts.php</code> in your browser to insert default accounts.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 3. Interactive API Login Test -->
    <div class="card">
        <h2>3. Test Live Login API Call</h2>
        <form method="POST">
            <div>
                <label>Email or Phone Number:</label><br>
                <input type="text" name="identifier" placeholder="e.g. demo@ohati.com or 0200000000" required value="<?= htmlspecialchars($_POST['identifier'] ?? 'demo.customer@ohati.com') ?>">
            </div>
            <div>
                <label>Password:</label><br>
                <input type="password" name="password" placeholder="Password" required value="OhatiDemo2026@Customer">
            </div>
            <button type="submit" name="test_login">Run Login API Test</button>
        </form>

        <?php if (!empty($results['test_login_result'])): ?>
            <?php $res = $results['test_login_result']; ?>
            <h3 style="margin-top:20px; color:#F2A735;">API Test Result:</h3>
            <p><strong>Tested Endpoint:</strong> <code><?= htmlspecialchars($res['api_url']) ?></code></p>
            <p><strong>HTTP Response Code:</strong> <code><?= $res['http_code'] ?></code></p>
            <p>
                <strong>JSON Parsing Status:</strong>
                <?php if ($res['is_valid_json']): ?>
                    <span class="status-badge badge-success">VALID JSON</span>
                <?php else: ?>
                    <span class="status-badge badge-danger">INVALID JSON! (Reason: <?= htmlspecialchars($res['json_error']) ?>)</span>
                <?php endif; ?>
            </p>

            <h4>Raw Output from `api.php`:</h4>
            <div class="code-box"><?= htmlspecialchars($res['raw_response']) ?></div>

            <?php if (!$res['is_valid_json']): ?>
                <div style="background:#7F1D1D; border:1px solid #EF4444; border-radius:8px; padding:12px; margin-top:12px;">
                    <strong>🚨 ROOT CAUSE DETECTED:</strong><br>
                    The server returned raw non-JSON output (shown above). Look closely at the raw response box above to read the PHP error or HTML string that broke `api.php`.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
