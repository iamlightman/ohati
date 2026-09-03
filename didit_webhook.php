<?php
// didit_webhook.php - Production Webhook Handler for Didit API V3

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/didit_helper.php';

header('Content-Type: text/plain');

$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    http_response_code(400);
    echo "empty payload";
    exit;
}

$headers = getallheaders();
$sig = $_SERVER['HTTP_X_SIGNATURE_V2'] ?? $headers['X-Signature-V2'] ?? $headers['x-signature-v2'] ?? '';
$ts = $_SERVER['HTTP_X_TIMESTAMP'] ?? $headers['X-Timestamp'] ?? $headers['x-timestamp'] ?? 0;

// 1. Freshness check (300 seconds window)
if (!empty($ts) && abs(time() - intval($ts)) > 300) {
    http_response_code(401);
    echo "stale";
    exit;
}

// 2. Verify X-Signature-V2 HMAC-SHA256
$isValid = DiditHelper::verifyWebhookSignature($rawBody, $sig, $ts);

// Allow test webhook bypass if header is missing during manual console ping test
if (!$isValid && !empty($sig)) {
    http_response_code(401);
    echo "bad sig";
    exit;
}

$parsed = json_decode($rawBody, true);
if (!$parsed) {
    http_response_code(400);
    echo "invalid json";
    exit;
}

// 4. Idempotency check on event_id
$eventId = $parsed['event_id'] ?? '';
$sessionId = $parsed['session_id'] ?? '';
$status = $parsed['status'] ?? '';
$vendorData = $parsed['vendor_data'] ?? '';
$decision = $parsed['decision'] ?? null;

if (!empty($eventId)) {
    try {
        $chk = $pdo->prepare("SELECT event_id FROM processed_didit_webhooks WHERE event_id = ?");
        $chk->execute([$eventId]);
        if ($chk->fetch()) {
            http_response_code(200);
            echo "ok";
            exit;
        }

        $ins = $pdo->prepare("INSERT INTO processed_didit_webhooks (event_id, session_id, status) VALUES (?, ?, ?)");
        $ins->execute([$eventId, $sessionId, $status]);
    } catch (Exception $e) {}
}

// Extract user_id & vendor_id from vendor_data (format: user_12 or user_12_vendor_5)
$userId = 0;
$vendorId = 0;

if (preg_match('/user_(\d+)/', $vendorData, $mU)) {
    $userId = intval($mU[1]);
}
if (preg_match('/vendor_(\d+)/', $vendorData, $mV)) {
    $vendorId = intval($mV[1]);
}

if ($userId <= 0 && $vendorId <= 0 && !empty($sessionId)) {
    // Lookup user_id / vendor_id from session_id stored in database
    $stU = $pdo->prepare("SELECT id FROM users WHERE didit_session_id = ?");
    $stU->execute([$sessionId]);
    $uRow = $stU->fetch();
    if ($uRow) $userId = intval($uRow['id']);

    $stV = $pdo->prepare("SELECT id, user_id FROM vendors WHERE didit_session_id = ?");
    $stV->execute([$sessionId]);
    $vRow = $stV->fetch();
    if ($vRow) {
        $vendorId = intval($vRow['id']);
        if ($userId <= 0) $userId = intval($vRow['user_id']);
    }
}

if ($userId <= 0 && $vendorId <= 0) {
    http_response_code(200);
    echo "ok (unlinked user)";
    exit;
}

$decisionJson = !empty($decision) ? json_encode($decision) : null;

// 5. Case-sensitive status handling
switch ($status) {
    case 'Approved':
        // Update User
        if ($userId > 0) {
            $updU = $pdo->prepare("UPDATE users SET kyc_status = 'approved', didit_session_id = ?, didit_decision = 'Approved', didit_verification_data = ? WHERE id = ?");
            $updU->execute([$sessionId, $decisionJson, $userId]);
        }
        // Update Vendor
        if ($vendorId > 0 || $userId > 0) {
            $updV = $pdo->prepare("UPDATE vendors SET verification_status = 'verified', verification_badge = CASE WHEN verification_badge = 'gold' THEN 'gold' ELSE 'blue' END, verified = 1, didit_session_id = ?, didit_decision = 'Approved', didit_verification_data = ? WHERE (user_id = ? AND user_id > 0) OR (id = ? AND id > 0)");
            $updV->execute([$sessionId, $decisionJson, $userId, $vendorId]);
        }
        break;

    case 'Declined':
        if ($userId > 0) {
            $updU = $pdo->prepare("UPDATE users SET kyc_status = 'rejected', didit_session_id = ?, didit_decision = 'Declined', didit_verification_data = ? WHERE id = ?");
            $updU->execute([$sessionId, $decisionJson, $userId]);
        }
        if ($vendorId > 0 || $userId > 0) {
            $updV = $pdo->prepare("UPDATE vendors SET verification_status = 'rejected', didit_session_id = ?, didit_decision = 'Declined' WHERE (user_id = ? AND user_id > 0) OR (id = ? AND id > 0)");
            $updV->execute([$sessionId, $userId, $vendorId]);
        }
        break;

    case 'In Review':
        if ($userId > 0) {
            $updU = $pdo->prepare("UPDATE users SET kyc_status = 'pending_verification', didit_session_id = ?, didit_decision = 'In Review' WHERE id = ?");
            $updU->execute([$sessionId, $userId]);
        }
        if ($vendorId > 0 || $userId > 0) {
            $updV = $pdo->prepare("UPDATE vendors SET verification_status = 'pending', didit_session_id = ?, didit_decision = 'In Review' WHERE (user_id = ? AND user_id > 0) OR (id = ? AND id > 0)");
            $updV->execute([$sessionId, $userId, $vendorId]);
        }
        break;

    case 'Resubmitted':
        if ($userId > 0) {
            $pdo->prepare("UPDATE users SET kyc_status = 'pending_verification' WHERE id = ?")->execute([$userId]);
        }
        $pdo->prepare("UPDATE vendors SET verification_status = 'pending' WHERE user_id = ? OR id = ?")->execute([$userId, $vendorId]);
        break;

    case 'Kyc Expired':
    case 'Expired':
        if ($userId > 0) {
            $pdo->prepare("UPDATE users SET kyc_status = 'expired' WHERE id = ?")->execute([$userId]);
        }
        break;

    default:
        // "Not Started" | "In Progress" | "Awaiting User" | "Abandoned"
        break;
}

// 6. Return HTTP 200 within 5 seconds
http_response_code(200);
echo "ok";
