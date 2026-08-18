<?php
// sms_helper.php — Ohati SMS & Dual Notification Gateway (SMSOnlineGh Integration)
require_once __DIR__ . '/ohati_config.php';
require_once __DIR__ . '/mail_helper.php';

if (!defined('SMS_API_KEY')) {
    define('SMS_API_KEY', '38e66cec652f99e5bf32fa1ff09df0ce62865cf854f68f0e5deed46a96e466be');
}
if (!defined('SMS_SENDER_ID')) {
    define('SMS_SENDER_ID', 'ohati');
}

/**
 * Format Ghanaian phone numbers into standard E.164 international format (233xxxxxxxxx)
 */
function format_ghana_phone($phone) {
    $clean = preg_replace('/[^\d]/', '', $phone);
    if (empty($clean)) return '';
    
    if (strpos($clean, '0') === 0) {
        return '233' . substr($clean, 1);
    }
    if (strpos($clean, '233') === 0) {
        return $clean;
    }
    if (strlen($clean) === 9) {
        return '233' . $clean;
    }
    return $clean;
}

/**
 * Sanitize text to ensure zero symbol corruption or broken characters in SMS
 */
function clean_sms_text($text) {
    $clean = strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    $clean = str_replace(["\r", "\n"], " ", $clean);
    return trim($clean);
}

/**
 * Send SMS message via SMSOnlineGh REST API v5 (api.smsonlinegh.com / portal.smsonlinegh.com)
 */
function send_smsonlinegh($phone, $message) {
    $formatted_phone = format_ghana_phone($phone);
    if (empty($formatted_phone)) {
        return ['success' => false, 'error' => 'Invalid phone number format'];
    }

    $clean_msg = clean_sms_text($message);
    $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : '38e66cec652f99e5bf32fa1ff09df0ce62865cf854f68f0e5deed46a96e466be';
    $sender_id = defined('SMS_SENDER_ID') ? SMS_SENDER_ID : 'ohati';

    // SMSOnlineGh API v5 Official JSON Payload Structure
    $v5_payload = [
        'text' => $clean_msg,
        'destinations' => [$formatted_phone],
        'type' => 0,
        'sender' => $sender_id
    ];

    // Legacy v4 fallback payload
    $v4_payload = [
        'key' => $api_key,
        'to' => $formatted_phone,
        'msg' => $clean_msg,
        'sender' => $sender_id
    ];

    $endpoints = [
        // Primary: SMSOnlineGh REST API v5
        [
            'url' => 'https://api.smsonlinegh.com/v5/message/sms/send',
            'payload' => $v5_payload,
            'headers' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Host: api.smsonlinegh.com',
                'Authorization: key ' . $api_key
            ]
        ],
        // Secondary: SMSOnlineGh REST API v4
        [
            'url' => 'https://api.smsonlinegh.com/v4/message/sms/send',
            'payload' => $v5_payload,
            'headers' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: key ' . $api_key
            ]
        ],
        // Tertiary fallback: Legacy v4 parameter endpoint
        [
            'url' => 'https://api.smsonlinegh.com/v4/message/send',
            'payload' => $v4_payload,
            'headers' => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]
    ];

    foreach ($endpoints as $ep) {
        $url = $ep['url'];
        $payload = $ep['payload'];
        $headers = $ep['headers'];

        $response = false;
        $http_code = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init();
            $info_const = defined('CURLINFO_HTTP_CODE') ? CURLINFO_HTTP_CODE : (defined('CURLINFO_RESPONSE_CODE') ? CURLINFO_RESPONSE_CODE : 2097154);
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, $info_const);
            curl_close($ch);
        } else {
            // Stream context fallback when PHP cURL extension is missing
            $options = [
                'http' => [
                    'header'  => implode("\r\n", $headers) . "\r\n",
                    'method'  => 'POST',
                    'content' => json_encode($payload),
                    'timeout' => 8,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            $context  = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header[0])) {
                preg_match('{HTTP\/\S+\s+(\d+)}', $http_response_header[0], $m);
                $http_code = intval($m[1] ?? 200);
            }
        }

        if ($response !== false && ($http_code === 200 || $http_code === 201 || $http_code === 0)) {
            $json = json_decode($response, true);
            if ($json && (
                (isset($json['handshake']['id']) && ($json['handshake']['id'] === 0 || $json['handshake']['id'] === 200 || $json['handshake']['label'] === 'HSHK_OK')) ||
                (isset($json['status']) && strtolower($json['status']) === 'success') || 
                (isset($json['handshake']['status']) && strtolower($json['handshake']['status']) === 'success') ||
                (isset($json['code']) && ($json['code'] == '200' || $json['code'] == 200))
            )) {
                return ['success' => true, 'response' => $json];
            }
        }
    }

    return ['success' => false, 'error' => 'SMSOnlineGh Gateway Connection Failed or Key Pending'];
}

/**
 * Universal Dual Notification Helper (SMS + Email Dual Dispatch)
 */
function send_dual_notification($target_phone, $target_email, $title, $sms_message, $email_subject = null, $email_body = null) {
    $sms_res = ['success' => false];
    $email_res = false;

    // 1. Primary SMS Notification via SMSOnlineGh
    if (!empty($target_phone)) {
        $sms_text = "$title: " . clean_sms_text($sms_message) . " - Ohati Ghana";
        $sms_res = send_smsonlinegh($target_phone, $sms_text);
    }

    // 2. Alternative / Dual Email Notification via SMTP
    if (!empty($target_email)) {
        $subject = $email_subject ?: "Ohati Update: $title";
        $body = $email_body ?: "<div style='font-family:sans-serif; padding:20px; color:#1B2B4B;'>"
            . "<h2 style='color:#1B2B4B;'>$title</h2>"
            . "<p style='font-size:15px; line-height:1.6;'>$sms_message</p>"
            . "<hr style='border:none; border-top:1px solid #eee; margin:20px 0;'>"
            . "<p style='font-size:12px; color:#666;'>Ghana's Trusted Event Vendor Marketplace &bull; Ohati</p>"
            . "</div>";
        try {
            $email_res = send_smtp_mail($target_email, $subject, $body);
        } catch (Exception $e) {}
    }

    return [
        'sms_sent' => $sms_res['success'],
        'email_sent' => $email_res
    ];
}
