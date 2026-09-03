<?php
// didit_helper.php - Didit API V3 Server-Side Helper Engine

require_once __DIR__ . '/config_didit.php';

class DiditHelper {

    /**
     * Creates a new verification session with Didit API V3
     * 
     * @param int $userId
     * @param int|null $vendorId
     * @param string|null $callbackUrl
     * @return array
     * @throws Exception
     */
    public static function createSession($userId, $vendorId = null, $callbackUrl = null) {
        $apiKey = DIDIT_API_KEY;
        $workflowId = DIDIT_WORKFLOW_ID;
        $url = DIDIT_BASE_URL . 'session/';

        if (empty($callbackUrl)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $dir = ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') ? '' : rtrim(str_replace('\\', '/', $scriptDir), '/');
            $callbackUrl = "$scheme://$host$dir/index.php?action=didit_callback";
        }

        $vendorData = "user_" . intval($userId);
        if (!empty($vendorId)) {
            $vendorData .= "_vendor_" . intval($vendorId);
        }

        $payload = [
            'workflow_id' => $workflowId,
            'vendor_data' => $vendorData,
            'callback' => $callbackUrl,
            'callback_method' => 'completer'
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("Didit API Network Error: " . $curlErr);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400 || empty($data['session_id'])) {
            $msg = $data['detail'] ?? $data['error'] ?? "Failed to create Didit session (HTTP $httpCode)";
            throw new Exception($msg);
        }

        return [
            'session_id' => $data['session_id'] ?? '',
            'session_token' => $data['session_token'] ?? '',
            'url' => $data['url'] ?? '',
            'status' => $data['status'] ?? 'Not Started',
            'vendor_data' => $vendorData
        ];
    }

    /**
     * Retrieves session decision details directly from Didit V3 API
     * 
     * @param string $sessionId
     * @return array
     */
    public static function fetchSessionDecision($sessionId) {
        $apiKey = DIDIT_API_KEY;
        $url = DIDIT_BASE_URL . "session/" . urlencode($sessionId) . "/decision/";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            return json_decode($response, true);
        }
        return null;
    }

    /**
     * Verifies the incoming X-Signature-V2 HMAC signature from Didit Webhooks
     * 
     * @param string $rawBody
     * @param string $signature
     * @param int|string $timestamp
     * @return bool
     */
    public static function verifyWebhookSignature($rawBody, $signature, $timestamp) {
        if (empty($signature) || empty($timestamp)) {
            return false;
        }

        // 1. Freshness check: Reject if > 300 seconds old/new
        $ts = intval($timestamp);
        if (abs(time() - $ts) > 300) {
            return false;
        }

        $secret = DIDIT_WEBHOOK_SECRET;

        // Method A: X-Signature-V2 (Canonical JSON HMAC)
        try {
            $parsed = json_decode($rawBody, true);
            if ($parsed) {
                $canonicalData = self::sortKeys(self::shortenFloats($parsed));
                $canonicalJson = json_encode($canonicalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $expectedV2 = hash_hmac('sha256', $canonicalJson, $secret);
                if (hash_equals(strtolower($expectedV2), strtolower($signature))) {
                    return true;
                }
            }
        } catch (Exception $e) {}

        // Method B: X-Signature (Raw verbatim bytes HMAC)
        $expectedRaw = hash_hmac('sha256', $rawBody, $secret);
        if (hash_equals(strtolower($expectedRaw), strtolower($signature))) {
            return true;
        }

        return false;
    }

    /**
     * Converts whole float numbers (1.0) to integers (1) recursively
     */
    public static function shortenFloats($v) {
        if (is_array($v)) {
            $res = [];
            foreach ($v as $k => $val) {
                $res[$k] = self::shortenFloats($val);
            }
            return $res;
        }
        if (is_float($v) && floor($v) == $v) {
            return intval($v);
        }
        return $v;
    }

    /**
     * Sorts array keys recursively lexicographically (array order preserved)
     */
    public static function sortKeys($v) {
        if (is_array($v)) {
            // Check if associative array
            $isAssoc = array_keys($v) !== range(0, count($v) - 1);
            if ($isAssoc) {
                ksort($v, SORT_STRING);
            }
            foreach ($v as $k => $val) {
                $v[$k] = self::sortKeys($val);
            }
        }
        return $v;
    }
}
