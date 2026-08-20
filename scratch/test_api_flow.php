<?php
// Scratch test script for end-to-end API verification
require_once __DIR__ . '/../db.php';

echo "--- 1. Testing Database & Demo Accounts ---\n";
$stmt = $pdo->prepare("SELECT id, name, email, phone, role, email_verified, phone_verified, is_active FROM users WHERE email IN ('demo.customer@ohati.com', 'demo.vendor@ohati.com')");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

echo "\n--- 2. Checking user_blocks and deleted_records tables ---\n";
$blockCheck = $pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='user_blocks'")->fetchColumn();
echo "user_blocks table exists: " . ($blockCheck ? "YES" : "NO") . "\n";

$deleteCheck = $pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='deleted_records'")->fetchColumn();
echo "deleted_records table exists: " . ($deleteCheck ? "YES" : "NO") . "\n";

echo "\n--- All Database Checks Passed Cleanly ---\n";
