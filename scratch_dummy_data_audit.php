<?php
// scratch_dummy_data_audit.php - Production Audit for Real Data Engine
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'audit_summary' => [],
    'tests' => []
];

// Helper to run internal sub-request to api.php
function call_api($action, $params = [], $session_user = null) {
    global $pdo;
    $_GET = array_merge(['action' => $action], $params);
    $_POST = $params;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    if ($session_user) {
        $_SESSION['user'] = $session_user;
    }

    ob_start();
    include __DIR__ . '/api.php';
    $out = ob_get_clean();
    return json_decode($out, true);
}

// 1. Audit Brand-New Customer Data
$now_str = date('Y-m-d H:i:s');
$test1_email = 'audit_customer_' . time() . '@ohati-test.com';
$pdo->prepare("INSERT INTO users (name, email, phone, role, created_at, last_active) VALUES ('Audit Customer', ?, '0240001111', 'customer', ?, ?)")->execute([$test1_email, $now_str, $now_str]);
$cust_id = $pdo->lastInsertId();
$cust_user = ['id' => $cust_id, 'name' => 'Audit Customer', 'email' => $test1_email, 'role' => 'customer'];

$cust_stats = call_api('dashboard_stats', [], $cust_user);
$cust_notifs = call_api('notifications', [], $cust_user);

$pass1 = ($cust_stats['bookings_count'] === 0 && $cust_stats['saved_vendors'] === 0 && count($cust_notifs) === 0);
$results['tests'][] = [
    'area' => 'Brand-New Customer Data',
    'dummy_source_found' => 'Inherited/fallback demo stats',
    'file_endpoint' => 'api.php (dashboard_stats, notifications)',
    'action_taken' => 'Enforced zero default metrics for new accounts',
    'real_data_source' => 'SQL COUNT on user bookings & notifications',
    'pass_fail' => $pass1 ? 'PASS' : 'FAIL',
    'details' => $cust_stats
];

// 2. Audit Brand-New Vendor Data
$test2_email = 'audit_vendor_' . time() . '@ohati-test.com';
$pdo->prepare("INSERT INTO users (name, email, phone, role, created_at, last_active) VALUES ('Audit Vendor User', ?, '0240002222', 'vendor', ?, ?)")->execute([$test2_email, $now_str, $now_str]);
$v_uid = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO vendors (user_id, name, category, created_at, last_active, views_count) VALUES (?, 'Audit Vendor Studio', 'Photography', ?, ?, 0)")->execute([$v_uid, $now_str, $now_str]);
$vid = $pdo->lastInsertId();
$vendor_user = ['id' => $v_uid, 'name' => 'Audit Vendor User', 'email' => $test2_email, 'role' => 'vendor', 'vendor_id' => $vid];

$v_stats = call_api('vendor_stats', ['vendor_id' => $vid], $vendor_user);

$pass2 = ($v_stats['views'] === 0 && $v_stats['bookings'] === 0 && floatval($v_stats['revenue']) === 0.0 && $v_stats['chats'] === 0);
$results['tests'][] = [
    'area' => 'Brand-New Vendor Analytics',
    'dummy_source_found' => 'Fake 148 base views & multiplier math in loadVendorRealtimeAnalytics',
    'file_endpoint' => 'js/screens.js & api.php (vendor_stats)',
    'action_taken' => 'Replaced hardcoded 148 base views with SQL aggregation',
    'real_data_source' => 'vendor_views_log & bookings tables',
    'pass_fail' => $pass2 ? 'PASS' : 'FAIL',
    'details' => $v_stats
];

// 3. Audit Real Notifications
$pdo->prepare("INSERT INTO notifications (user_id, title, body, created_at) VALUES (?, 'Booking Confirmed', 'Your booking with Audit Studio is confirmed.', ?)")->execute([$cust_id, $now_str]);
$real_notifs = call_api('notifications', [], $cust_user);

$pass3 = (count($real_notifs) === 1 && $real_notifs[0]['title'] === 'Booking Confirmed');
$results['tests'][] = [
    'area' => 'Real Notification System',
    'dummy_source_found' => 'Missing notifications endpoint returning invalid action error',
    'file_endpoint' => 'api.php (notifications)',
    'action_taken' => 'Implemented notifications case returning authenticated DB rows',
    'real_data_source' => 'notifications table',
    'pass_fail' => $pass3 ? 'PASS' : 'FAIL',
    'details' => $real_notifs
];

// 4. Audit Presence Engine & Online Status
$pdo->prepare("UPDATE users SET last_active = ? WHERE id = ?")->execute([$now_str, $cust_id]);
$st_active = call_api('get_user_status', ['user_id' => $cust_id]);

$pdo->prepare("UPDATE users SET last_active = '2026-09-01 10:00:00' WHERE id = ?")->execute([$cust_id]);
$st_old = call_api('get_user_status', ['user_id' => $cust_id]);

$pass4 = ($st_active['is_online'] === true && $st_old['is_online'] === false && strpos($st_old['online_status'], 'Active') !== false);
$results['tests'][] = [
    'area' => 'Online Status & Presence Engine',
    'dummy_source_found' => 'Undefined function get_online_status_info & fake green dots for availability = Available',
    'file_endpoint' => 'api.php & js/screens.js',
    'action_taken' => 'Added get_online_status_info & strict presence check within 120s',
    'real_data_source' => 'users.last_active timestamp',
    'pass_fail' => $pass4 ? 'PASS' : 'FAIL',
    'details' => ['active' => $st_active, 'inactive' => $st_old]
];

// 5. Audit Profile Views & Self-View Blocking
call_api('record_vendor_view', ['vendor_id' => $vid], $vendor_user); // Owner view -> should NOT count
$v_stats_owner = call_api('vendor_stats', ['vendor_id' => $vid], $vendor_user);

call_api('record_vendor_view', ['vendor_id' => $vid], $cust_user); // Non-owner view -> SHOULD count
$v_stats_non_owner = call_api('vendor_stats', ['vendor_id' => $vid], $cust_user);

$pass5 = ($v_stats_owner['views'] === 0 && $v_stats_non_owner['views'] === 1);
$results['tests'][] = [
    'area' => 'Profile Views Tracking',
    'dummy_source_found' => 'Self-views & auto-refreshes inflating profile view stats',
    'file_endpoint' => 'api.php (record_vendor_view)',
    'action_taken' => 'Blocked owner self-views & added 10m session throttling',
    'real_data_source' => 'vendor_views_log table',
    'pass_fail' => $pass5 ? 'PASS' : 'FAIL',
    'details' => ['owner_view_count' => $v_stats_owner['views'], 'visitor_view_count' => $v_stats_non_owner['views']]
];

// 6. Cleanup Audit Test Rows
$pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$cust_id]);
$pdo->prepare("DELETE FROM vendor_views_log WHERE vendor_id = ?")->execute([$vid]);
$pdo->prepare("DELETE FROM vendors WHERE id = ?")->execute([$vid]);
$pdo->prepare("DELETE FROM users WHERE id IN (?, ?)")->execute([$cust_id, $v_uid]);

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
