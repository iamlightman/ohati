<?php
// scratch_test_desktop_chat.php - Empirical Desktop Chat Backend & API Audit
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function call_api($action, $params = [], $user = null) {
    global $pdo;
    $_GET = array_merge(['action' => $action], $params);
    $_POST = $params;
    $_SERVER['REQUEST_METHOD'] = 'GET';
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
$t = time();

// Create Test Accounts
$email_c = 'chat_cust_' . $t . '@ohati-test.com';
$pdo->prepare("INSERT INTO users (name, email, phone, role, created_at, last_active) VALUES ('Chat Customer', ?, ?, 'customer', ?, ?)")->execute([$email_c, '0245' . substr($t, -6), $now_str, $now_str]);
$id_c = $pdo->lastInsertId();
$user_c = ['id' => $id_c, 'name' => 'Chat Customer', 'email' => $email_c, 'role' => 'customer'];

$email_v = 'chat_vend_' . $t . '@ohati-test.com';
$pdo->prepare("INSERT INTO users (name, email, phone, role, created_at, last_active) VALUES ('Chat Vendor', ?, ?, 'vendor', ?, ?)")->execute([$email_v, '0246' . substr($t, -6), $now_str, $now_str]);
$uid_v = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO vendors (user_id, name, category, created_at, last_active, views_count) VALUES (?, 'Chat Studio', 'Photography', ?, ?, 0)")->execute([$uid_v, $now_str, $now_str]);
$vid_v = $pdo->lastInsertId();
$user_v = ['id' => $uid_v, 'name' => 'Chat Vendor', 'email' => $email_v, 'role' => 'vendor', 'vendor_id' => $vid_v];

// Insert a message between Customer and Vendor
$pdo->prepare("INSERT INTO messages (user_id, vendor_id, sender, message, created_at) VALUES (?, ?, 'customer', 'Hello from desktop test', ?)")->execute([$id_c, $vid_v, $now_str]);

// Test 1: Customer Inbox
$inbox_c = call_api('chat_inbox', [], $user_c);
$pass1 = (is_array($inbox_c) && count($inbox_c) === 1);

// Test 2: Vendor Inbox
$inbox_v = call_api('chat_inbox', [], $user_v);
$pass2 = (is_array($inbox_v) && count($inbox_v) === 1);

// Test 3: Chat History
$hist_c = call_api('chat_history', ['vendor_id' => $vid_v], $user_c);
$pass3 = (is_array($hist_c) && count($hist_c) === 1 && $hist_c[0]['message'] === 'Hello from desktop test');

// Test 4: Container Hash Consistency
$h1 = md5_file(__DIR__ . '/js/screens.js');
$h2 = md5_file(__DIR__ . '/www/js/screens.js');
$h3 = md5_file(__DIR__ . '/ios/App/App/public/js/screens.js');
$pass4 = ($h1 === $h2 && $h2 === $h3);

// Cleanup
$pdo->prepare("DELETE FROM messages WHERE user_id = ? OR vendor_id = ?")->execute([$id_c, $vid_v]);
$pdo->prepare("DELETE FROM vendors WHERE id = ?")->execute([$vid_v]);
$pdo->prepare("DELETE FROM users WHERE id IN (?, ?)")->execute([$id_c, $uid_v]);

$res = [
    'customer_inbox' => $pass1 ? 'PASS' : 'FAIL',
    'vendor_inbox' => $pass2 ? 'PASS' : 'FAIL',
    'chat_history' => $pass3 ? 'PASS' : 'FAIL',
    'screens_js_hash_match' => $pass4 ? 'PASS' : 'FAIL',
    'hash' => $h1
];

echo json_encode($res, JSON_PRETTY_PRINT);
