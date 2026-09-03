<?php
// scratch_presence_time_audit.php - Time/Presence Architecture & Multi-User Isolation Audit
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'audit_summary' => [],
    'tests' => []
];

// Helper to simulate API requests for specific users
function call_api_user($action, $params = [], $user = null) {
    global $pdo;
    $_GET = array_merge(['action' => $action], $params);
    $_POST = $params;
    $_SERVER['REQUEST_METHOD'] = isset($params['_method']) ? $params['_method'] : 'GET';
    if ($user) {
        $_SESSION['user'] = $user;
    } else {
        unset($_SESSION['user']);
    }
    ob_start();
    include __DIR__ . '/api.php';
    $out = ob_get_clean();
    return json_decode($out, true);
}

$now_str = date('Y-m-d H:i:s');

// 1. Create Test Accounts: Customer A, Customer B, Vendor A, Vendor B
$t = time();
$email_ca = 'cust_a_' . $t . '@ohati-test.com';
$email_cb = 'cust_b_' . $t . '@ohati-test.com';
$email_va = 'vend_a_' . $t . '@ohati-test.com';
$email_vb = 'vend_b_' . $t . '@ohati-test.com';

// Customer A
$pdo->prepare("INSERT INTO users (name, email, phone, role, created_at, last_active) VALUES ('Customer A', ?, ?, 'customer', ?, ?)")->execute([$email_ca, '0241' . substr($t, -6), $now_str, $now_str]);
$id_ca = $pdo->lastInsertId();
$user_ca = ['id' => $id_ca, 'name' => 'Customer A', 'email' => $email_ca, 'role' => 'customer'];

// Customer B
$pdo->prepare("INSERT INTO users (name, email, phone, role, created_at, last_active) VALUES ('Customer B', ?, ?, 'customer', ?, ?)")->execute([$email_cb, '0242' . substr($t, -6), $now_str, $now_str]);
$id_cb = $pdo->lastInsertId();
$user_cb = ['id' => $id_cb, 'name' => 'Customer B', 'email' => $email_cb, 'role' => 'customer'];

// Vendor A
$pdo->prepare("INSERT INTO users (name, email, phone, role, created_at, last_active) VALUES ('Vendor A Owner', ?, ?, 'vendor', ?, ?)")->execute([$email_va, '0243' . substr($t, -6), $now_str, $now_str]);
$uid_va = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO vendors (user_id, name, category, created_at, last_active, views_count) VALUES (?, 'Vendor A Studio', 'Photography', ?, ?, 0)")->execute([$uid_va, $now_str, $now_str]);
$vid_va = $pdo->lastInsertId();
$user_va = ['id' => $uid_va, 'name' => 'Vendor A Owner', 'email' => $email_va, 'role' => 'vendor', 'vendor_id' => $vid_va];

// Vendor B
$pdo->prepare("INSERT INTO users (name, email, phone, role, created_at, last_active) VALUES ('Vendor B Owner', ?, ?, 'vendor', ?, ?)")->execute([$email_vb, '0244' . substr($t, -6), $now_str, $now_str]);
$uid_vb = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO vendors (user_id, name, category, created_at, last_active, views_count) VALUES (?, 'Vendor B Studio', 'Catering', ?, ?, 0)")->execute([$uid_vb, $now_str, $now_str]);
$vid_vb = $pdo->lastInsertId();
$user_vb = ['id' => $uid_vb, 'name' => 'Vendor B Owner', 'email' => $email_vb, 'role' => 'vendor', 'vendor_id' => $vid_vb];


// ── TEST 1: Authoritative Server Timestamps ──────────────────────────────
$pdo->prepare("INSERT INTO notifications (user_id, title, body, created_at) VALUES (?, 'Test Notification', 'Hello Customer A', ?)")->execute([$id_ca, $now_str]);
$notifs_ca = call_api_user('notifications', [], $user_ca);

$pass1 = (count($notifs_ca) === 1 && isset($notifs_ca[0]['created_at']) && $notifs_ca[0]['created_at'] === $now_str);
$results['tests'][] = [
    'area' => 'Authoritative Server Timestamps',
    'source_of_time' => 'Database SQL TIMESTAMP (created_at)',
    'timezone_handling' => 'Server standard YYYY-MM-DD HH:MM:SS parsed by device browser',
    'pass_fail' => $pass1 ? 'PASS' : 'FAIL',
    'details' => ['db_timestamp' => $now_str, 'api_response_time' => $notifs_ca[0]['created_at'] ?? 'NONE']
];


// ── TEST 2: Real Online Status Engine & Heartbeat Throttle ───────────────
call_api_user('heartbeat', [], $user_ca); // Active heartbeat
$st_active = call_api_user('get_user_status', ['user_id' => $id_ca]);

// Set last_active to 5 minutes ago (offline threshold)
$past_time = date('Y-m-d H:i:s', time() - 300);
$pdo->prepare("UPDATE users SET last_active = ? WHERE id = ?")->execute([$past_time, $id_ca]);
$st_expired = call_api_user('get_user_status', ['user_id' => $id_ca]);

$pass2 = ($st_active['is_online'] === true && $st_expired['is_online'] === false);
$results['tests'][] = [
    'area' => 'Presence Engine & Heartbeat Evaluation',
    'source_of_time' => 'users.last_active timestamp compared to server time() (120s cutoff)',
    'timezone_handling' => 'Server UNIX epoch difference calculation',
    'pass_fail' => $pass2 ? 'PASS' : 'FAIL',
    'details' => ['active_status' => $st_active, 'expired_status' => $st_expired]
];


// ── TEST 3: Logout Presence Invalidation ─────────────────────────────────
call_api_user('heartbeat', ['user_id' => $id_ca], $user_ca); // Make active again
$res_logout = call_api_user('logout', ['user_id' => $id_ca], $user_ca);   // Logout user
$st_logout = call_api_user('get_user_status', ['user_id' => $id_ca]);

// Fetch raw DB last_active
$stmt_db = $pdo->prepare("SELECT last_active FROM users WHERE id = ?");
$stmt_db->execute([$id_ca]);
$db_last_act = $stmt_db->fetchColumn();

$pass3 = ($st_logout['is_online'] === false && $st_logout['online_status'] === 'Offline');
$results['tests'][] = [
    'area' => 'Logout Presence Invalidation',
    'source_of_time' => 'logout action resets last_active = 1970-01-01 00:00:00',
    'timezone_handling' => 'Server timestamp reset',
    'pass_fail' => $pass3 ? 'PASS' : 'FAIL',
    'details' => ['logout_response' => $res_logout, 'status_after_logout' => $st_logout, 'db_last_active' => $db_last_act]
];


// ── TEST 4: Multi-User Account Switching & State Isolation ──────────────
// Perform activity for Customer A
$pdo->prepare("INSERT INTO notifications (user_id, title, body, created_at) VALUES (?, 'Notif for CA', 'Body CA', ?)")->execute([$id_ca, $now_str]);

// Perform activity for Customer B
$pdo->prepare("INSERT INTO notifications (user_id, title, body, created_at) VALUES (?, 'Notif for CB', 'Body CB', ?)")->execute([$id_cb, $now_str]);

$notifs_a = call_api_user('notifications', [], $user_ca);
$notifs_b = call_api_user('notifications', [], $user_cb);

$pass4 = (count($notifs_a) === 2 && $notifs_a[0]['user_id'] == $id_ca && count($notifs_b) === 1 && $notifs_b[0]['user_id'] == $id_cb);
$results['tests'][] = [
    'area' => 'Multi-User Data Isolation (Customer A vs B)',
    'source_of_time' => 'SQL WHERE user_id = :authenticated_user_id',
    'timezone_handling' => 'Session token binding',
    'pass_fail' => $pass4 ? 'PASS' : 'FAIL',
    'details' => ['customer_a_notifs_count' => count($notifs_a), 'customer_b_notifs_count' => count($notifs_b)]
];


// ── TEST 5: Vendor A vs Vendor B Real Analytics Isolation ────────────────
// Customer A views Vendor A 2 times
call_api_user('record_vendor_view', ['vendor_id' => $vid_va], $user_ca);
// Customer B views Vendor B 1 time
call_api_user('record_vendor_view', ['vendor_id' => $vid_vb], $user_cb);

$stats_va = call_api_user('vendor_stats', ['vendor_id' => $vid_va], $user_va);
$stats_vb = call_api_user('vendor_stats', ['vendor_id' => $vid_vb], $user_vb);

$pass5 = ($stats_va['views'] === 1 && $stats_vb['views'] === 1);
$results['tests'][] = [
    'area' => 'Vendor Analytics Isolation (Vendor A vs B)',
    'source_of_time' => 'vendor_views_log aggregation by vendor_id',
    'timezone_handling' => 'Server timestamp log',
    'pass_fail' => $pass5 ? 'PASS' : 'FAIL',
    'details' => ['vendor_a_views' => $stats_va['views'], 'vendor_b_views' => $stats_vb['views']]
];


// ── TEST 6: Zero Historical Data on Brand-New Accounts ───────────────────
$email_new = 'brand_new_' . time() . '@ohati-test.com';
$pdo->prepare("INSERT INTO users (name, email, phone, role, created_at, last_active) VALUES ('Brand New User', ?, '0249990000', 'customer', ?, ?)")->execute([$email_new, $now_str, $now_str]);
$id_new = $pdo->lastInsertId();
$user_new = ['id' => $id_new, 'name' => 'Brand New User', 'email' => $email_new, 'role' => 'customer'];

$new_stats = call_api_user('dashboard_stats', [], $user_new);
$new_notifs = call_api_user('notifications', [], $user_new);

$pass6 = ($new_stats['bookings_count'] === 0 && $new_stats['saved_vendors'] === 0 && count($new_notifs) === 0);
$results['tests'][] = [
    'area' => 'Zero Historical Data on Brand-New Accounts',
    'source_of_time' => 'SQL empty set verification',
    'timezone_handling' => 'N/A',
    'pass_fail' => $pass6 ? 'PASS' : 'FAIL',
    'details' => ['new_stats' => $new_stats, 'new_notifs_count' => count($new_notifs)]
];


// ── Cleanup Test Data ────────────────────────────────────────────────────
$pdo->prepare("DELETE FROM notifications WHERE user_id IN (?, ?, ?)")->execute([$id_ca, $id_cb, $id_new]);
$pdo->prepare("DELETE FROM vendor_views_log WHERE vendor_id IN (?, ?)")->execute([$vid_va, $vid_vb]);
$pdo->prepare("DELETE FROM vendors WHERE id IN (?, ?)")->execute([$vid_va, $vid_vb]);
$pdo->prepare("DELETE FROM users WHERE id IN (?, ?, ?, ?, ?)")->execute([$id_ca, $id_cb, $uid_va, $uid_vb, $id_new]);


// Final Summary
$all_pass = true;
foreach ($results['tests'] as $t) {
    if ($t['pass_fail'] !== 'PASS') $all_pass = false;
}
$results['audit_summary'] = [
    'total_tests' => count($results['tests']),
    'passed_tests' => count(array_filter($results['tests'], function($t){ return $t['pass_fail']==='PASS'; })),
    'overall_status' => $all_pass ? '100% PASS' : 'FAIL'
];

echo json_encode($results, JSON_PRETTY_PRINT);
