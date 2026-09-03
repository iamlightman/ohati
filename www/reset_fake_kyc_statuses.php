<?php
// reset_fake_kyc_statuses.php - Database Utility to Purge Unevidenced KYC Verification Statuses
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=========================================================================\n";
echo "=== OHATI DIDIT KYC DATABASE CLEANUP & RESET ENGINE ===\n";
echo "=========================================================================\n\n";

try {
    // 1. Fetch authentic approved session IDs from processed webhooks / history log
    $approvedSessions = [];
    try {
        $wStmt = $pdo->query("SELECT session_id FROM processed_didit_webhooks WHERE status = 'Approved'");
        $approvedSessions = $wStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    try {
        $hStmt = $pdo->query("SELECT session_id FROM kyc_verifications_history WHERE decision = 'Approved' OR status = 'Approved'");
        $hSessions = $hStmt->fetchAll(PDO::FETCH_COLUMN);
        $approvedSessions = array_unique(array_merge($approvedSessions, $hSessions));
    } catch (Exception $e) {}

    $approvedSessions = array_filter($approvedSessions);
    echo "[INFO] Found " . count($approvedSessions) . " authentic Didit Approved verification session(s) in history.\n";

    // 2. Reset Users with unevidenced 'approved' or 'under_review' or 'in_progress' state
    $validSessionsMap = array_flip($approvedSessions);
    $allUsers = $pdo->query("SELECT id, kyc_status, didit_session_id FROM users WHERE kyc_status IN ('approved', 'under_review', 'in_progress', 'pending')")->fetchAll(PDO::FETCH_ASSOC);
    $uResetStmt = $pdo->prepare("UPDATE users SET kyc_status = 'not_started', kyc_verified_at = NULL, didit_session_id = '', didit_decision = '' WHERE id = ?");
    $uCount = 0;
    foreach ($allUsers as $u) {
        $sessId = trim($u['didit_session_id'] ?? '');
        if (empty($sessId) || !isset($validSessionsMap[$sessId])) {
            $uResetStmt->execute([$u['id']]);
            $uCount++;
        }
    }

    echo "[SUCCESS] Reset $uCount user account(s) back to 'not_started'.\n";

    // 3. Reset Vendors with unevidenced 'verified' or 'pending' state
    $allVendors = $pdo->query("SELECT id, verification_status, verified, didit_session_id FROM vendors WHERE verification_status IN ('verified', 'pending', 'under_review') OR verified = 1")->fetchAll(PDO::FETCH_ASSOC);
    $vResetStmt = $pdo->prepare("UPDATE vendors SET verification_status = 'not_started', verified = 0, verification_badge = 'grey', didit_session_id = '', didit_decision = '' WHERE id = ?");
    $vCount = 0;
    foreach ($allVendors as $v) {
        $sessId = trim($v['didit_session_id'] ?? '');
        if (empty($sessId) || !isset($validSessionsMap[$sessId])) {
            $vResetStmt->execute([$v['id']]);
            $vCount++;
        }
    }

    echo "[SUCCESS] Reset $vCount vendor profile(s) back to 'not_started' and badge 'grey'.\n";

    echo "\n=========================================================================\n";
    echo "=== DATABASE CLEANUP COMPLETED SUCCESSFULLY ===\n";
    echo "=== ALL UNEVIDENCED VERIFICATIONS SAFELY RESET TO NOT_STARTED ===\n";
    echo "=========================================================================\n";

} catch (Exception $e) {
    echo "[ERROR] Database KYC cleanup failed: " . $e->getMessage() . "\n";
}
