<?php
// import_demo_accounts.php — 1-Click Importer for App Store Reviewer Accounts
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

echo '<div style="font-family:system-ui, sans-serif; max-width:600px; margin:40px auto; padding:24px; border:1px solid #E2E8F0; border-radius:16px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">';
echo '<h2 style="color:#0F172A; margin-top:0;">Ohati — Import Reviewer Demo Accounts</h2>';

try {
    // 1. Customer Demo Account
    $cust_email = 'demo.customer@ohati.com';
    $cust_pass = 'OhatiDemo2026@Customer';
    $cust_hash = password_hash($cust_pass, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$cust_email]);
    $cust_id = $stmt->fetchColumn();

    if (!$cust_id) {
        $ins = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, email_verified, phone_verified, is_active) VALUES ('App Review Customer', ?, '+233200000001', ?, 'customer', 1, 1, 1)");
        $ins->execute([$cust_email, $cust_hash]);
        $cust_id = $pdo->lastInsertId();
        echo "<p style='color:#10B981;'><strong>✔ Customer Account Created:</strong> $cust_email</p>";
    } else {
        $upd = $pdo->prepare("UPDATE users SET password_hash = ?, email_verified = 1, phone_verified = 1, is_active = 1 WHERE id = ?");
        $upd->execute([$cust_hash, $cust_id]);
        echo "<p style='color:#3B82F6;'><strong>✔ Customer Account Updated & Verified:</strong> $cust_email</p>";
    }

    // 2. Vendor Demo Account
    $vnd_email = 'demo.vendor@ohati.com';
    $vnd_pass = 'OhatiDemo2026@Vendor';
    $vnd_hash = password_hash($vnd_pass, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$vnd_email]);
    $vnd_id = $stmt->fetchColumn();

    if (!$vnd_id) {
        $ins = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, email_verified, phone_verified, is_active) VALUES ('App Review Vendor', ?, '+233200000002', ?, 'vendor', 1, 1, 1)");
        $ins->execute([$vnd_email, $vnd_hash]);
        $vnd_id = $pdo->lastInsertId();
        echo "<p style='color:#10B981;'><strong>✔ Vendor Account Created:</strong> $vnd_email</p>";
    } else {
        $upd = $pdo->prepare("UPDATE users SET password_hash = ?, email_verified = 1, phone_verified = 1, is_active = 1 WHERE id = ?");
        $upd->execute([$vnd_hash, $vnd_id]);
        echo "<p style='color:#3B82F6;'><strong>✔ Vendor Account Updated & Verified:</strong> $vnd_email</p>";
    }

    // Ensure Vendor Profile Exists
    $v_stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $v_stmt->execute([$vnd_id]);
    $v_profile_id = $v_stmt->fetchColumn();

    if (!$v_profile_id) {
        $ins_v = $pdo->prepare("INSERT INTO vendors (user_id, name, category, location, rating, reviews_count, verified, verification_badge, is_active) VALUES (?, 'App Review Event Services', 'Photography', 'Accra, Ghana', 5.0, 12, 1, 'gold', 1)");
        $ins_v->execute([$vnd_id]);
        echo "<p style='color:#10B981;'><strong>✔ Vendor Profile Linked:</strong> App Review Event Services</p>";
    } else {
        $upd_v = $pdo->prepare("UPDATE vendors SET verified = 1, verification_badge = 'gold', is_active = 1 WHERE id = ?");
        $upd_v->execute([$v_profile_id]);
        echo "<p style='color:#3B82F6;'><strong>✔ Vendor Profile Verified:</strong> App Review Event Services</p>";
    }

    echo '<hr style="border:none; border-top:1px solid #E2E8F0; margin:20px 0;">';
    echo '<h3 style="color:#0F172A; margin-bottom:8px;">App Store Review Credentials</h3>';
    echo '<table style="width:100%; border-collapse:collapse; font-size:0.9rem;">';
    echo '<tr style="background:#F8FAFC;"><th style="text-align:left; padding:8px;">Role</th><th style="text-align:left; padding:8px;">Email</th><th style="text-align:left; padding:8px;">Password</th></tr>';
    echo '<tr><td style="padding:8px;">Customer</td><td style="padding:8px;"><code>demo.customer@ohati.com</code></td><td style="padding:8px;"><code>OhatiDemo2026@Customer</code></td></tr>';
    echo '<tr style="background:#F8FAFC;"><td style="padding:8px;">Vendor</td><td style="padding:8px;"><code>demo.vendor@ohati.com</code></td><td style="padding:8px;"><code>OhatiDemo2026@Vendor</code></td></tr>';
    echo '</table>';

} catch (Exception $e) {
    echo "<p style='color:#EF4444;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo '</div>';
