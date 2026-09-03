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
$sig = $_SERVER['HTTP_X_SIGNATURE_V2'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? $headers['X-Signature-V2'] ?? $headers['X-Signature'] ?? $headers['x-signature-v2'] ?? $headers['x-signature'] ?? '';
$ts = $_SERVER['HTTP_X_TIMESTAMP'] ?? $headers['X-Timestamp'] ?? $headers['x-timestamp'] ?? 0;

// 1. Freshness check (300 seconds window)
if (!empty($ts) && abs(time() - intval($ts)) > 300) {
    http_response_code(401);
    echo "stale timestamp";
    exit;
}

// 2. Strict Webhook Signature Verification
if (defined('DIDIT_WEBHOOK_SECRET') && !empty(DIDIT_WEBHOOK_SECRET)) {
    $isValid = DiditHelper::verifyWebhookSignature($rawBody, $sig, $ts);
    if (!$isValid) {
        http_response_code(401);
        echo "bad signature";
        exit;
    }
}

$parsed = json_decode($rawBody, true);
if (!$parsed) {
    http_response_code(400);
    echo "invalid json";
    exit;
}

// 3. Extract Didit Payload Fields
$eventId = $parsed['event_id'] ?? '';
$sessionId = $parsed['session_id'] ?? '';
$status = $parsed['status'] ?? '';
$vendorData = $parsed['vendor_data'] ?? '';
$decision = $parsed['decision'] ?? null;

// 4. Idempotency Check on event_id
if (!empty($eventId)) {
    try {
        $chk = $pdo->prepare("SELECT event_id FROM processed_didit_webhooks WHERE event_id = ?");
        $chk->execute([$eventId]);
        if ($chk->fetch()) {
            http_response_code(200);
            echo "ok (duplicate event)";
            exit;
        }

        $ins = $pdo->prepare("INSERT INTO processed_didit_webhooks (event_id, session_id, status) VALUES (?, ?, ?)");
        $ins->execute([$eventId, $sessionId, $status]);
    } catch (Exception $e) {}
}

// 5. Extract user_id & vendor_id from vendor_data (format: user_12 or user_12_vendor_5)
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
$nowStr = date('Y-m-d H:i:s');

// 6. Case-Insensitive Status & Decision Mapping
$statusLower = strtolower(trim($status));

switch ($statusLower) {
    case 'approved':
    case 'verified':
        // Automatic Approval: Update User
        if ($userId > 0) {
            $updU = $pdo->prepare("UPDATE users SET kyc_status = 'approved', kyc_verified_at = ?, didit_session_id = ?, didit_decision = 'Approved', didit_verification_data = ? WHERE id = ?");
            $updU->execute([$nowStr, $sessionId, $decisionJson, $userId]);
        }
        // Update Vendor
        if ($vendorId > 0 || $userId > 0) {
            $updV = $pdo->prepare("UPDATE vendors SET verification_status = 'verified', verification_badge = CASE WHEN verification_badge = 'gold' THEN 'gold' ELSE 'blue' END, verified = 1, didit_session_id = ?, didit_decision = 'Approved', didit_verification_data = ? WHERE (user_id = ? AND user_id > 0) OR (id = ? AND id > 0)");
            $updV->execute([$sessionId, $decisionJson, $userId, $vendorId]);
        }
        // Log History
        try {
            $pdo->prepare("INSERT INTO kyc_verifications_history (user_id, vendor_id, session_id, event_id, status, decision) VALUES (?, ?, ?, ?, 'Approved', ?)")
                ->execute([$userId, $vendorId, $sessionId, $eventId, $decisionJson]);
        } catch (Exception $eLog) {}
        break;

    case 'declined':
    case 'rejected':
    case 'failed':
        if ($userId > 0) {
            $updU = $pdo->prepare("UPDATE users SET kyc_status = 'rejected', didit_session_id = ?, didit_decision = 'Declined', didit_verification_data = ? WHERE id = ?");
            $updU->execute([$sessionId, $decisionJson, $userId]);
        }
        if ($vendorId > 0 || $userId > 0) {
            $updV = $pdo->prepare("UPDATE vendors SET verification_status = 'rejected', verified = 0, didit_session_id = ?, didit_decision = 'Declined' WHERE (user_id = ? AND user_id > 0) OR (id = ? AND id > 0)");
            $updV->execute([$sessionId, $userId, $vendorId]);
        }
        try {
            $pdo->prepare("INSERT INTO kyc_verifications_history (user_id, vendor_id, session_id, event_id, status, decision) VALUES (?, ?, ?, ?, 'Declined', ?)")
                ->execute([$userId, $vendorId, $sessionId, $eventId, $decisionJson]);
        } catch (Exception $eLog) {}
        break;

    case 'in review':
    case 'in_review':
    case 'processing':
    case 'under_review':
        if ($userId > 0) {
            $updU = $pdo->prepare("UPDATE users SET kyc_status = 'under_review', didit_session_id = ?, didit_decision = 'In Review' WHERE id = ?");
            $updU->execute([$sessionId, $userId]);
        }
        if ($vendorId > 0 || $userId > 0) {
            $updV = $pdo->prepare("UPDATE vendors SET verification_status = 'pending', didit_session_id = ?, didit_decision = 'In Review' WHERE (user_id = ? AND user_id > 0) OR (id = ? AND id > 0)");
            $updV->execute([$sessionId, $userId, $vendorId]);
        }
        break;

    case 'kyc expired':
    case 'expired':
        if ($userId > 0) {
            $pdo->prepare("UPDATE users SET kyc_status = 'expired', didit_decision = 'Expired' WHERE id = ?")->execute([$userId]);
        }
        if ($vendorId > 0 || $userId > 0) {
            $pdo->prepare("UPDATE vendors SET verification_status = 'expired', didit_decision = 'Expired' WHERE (user_id = ? AND user_id > 0) OR (id = ? AND id > 0)")->execute([$userId, $vendorId]);
        }
        break;

    case 'cancelled':
    case 'abandoned':
        if ($userId > 0) {
            $pdo->prepare("UPDATE users SET kyc_status = 'cancelled', didit_decision = 'Cancelled' WHERE id = ?")->execute([$userId]);
        }
        if ($vendorId > 0 || $userId > 0) {
            $pdo->prepare("UPDATE vendors SET verification_status = 'cancelled', didit_decision = 'Cancelled' WHERE (user_id = ? AND user_id > 0) OR (id = ? AND id > 0)")->execute([$userId, $vendorId]);
        }
        break;

    default:
        // Other statuses
        break;
}

// 7. Return HTTP 200 OK
http_response_code(200);
echo "ok";
