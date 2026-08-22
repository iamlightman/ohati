<?php
// cron_notification_worker.php - Ohati Asynchronous Background Notification Queue Worker
date_default_timezone_set('Africa/Accra');

if (file_exists(__DIR__ . '/db.php')) { require_once __DIR__ . '/db.php'; }
if (file_exists(__DIR__ . '/mail_helper.php')) { require_once __DIR__ . '/mail_helper.php'; }
if (file_exists(__DIR__ . '/sms_helper.php')) { require_once __DIR__ . '/sms_helper.php'; }

function run_notification_queue_worker($limit = 20) {
    global $pdo;
    if (!$pdo) {
        return ['success' => false, 'error' => 'Database connection unavailable'];
    }

    try {
        // 1. Fetch pending notification jobs
        $stmt = $pdo->prepare("SELECT * FROM notification_queue WHERE status = 'pending' AND attempts < max_attempts ORDER BY id ASC LIMIT ?");
        $stmt->bindValue(1, intval($limit), PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($jobs)) {
            return ['success' => true, 'processed' => 0, 'message' => 'No pending notification jobs in queue.'];
        }

        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $processed++;
            $job_id = $job['id'];
            $phone = trim($job['recipient_phone'] ?? '');
            $email = trim($job['recipient_email'] ?? '');
            $title = trim($job['title'] ?? 'Ohati Update');
            $sms_msg = trim($job['sms_message'] ?? '');
            $email_subj = trim($job['email_subject'] ?? "Ohati Update: $title");
            $email_body = trim($job['email_body'] ?? '');
            $attempts = intval($job['attempts'] ?? 0) + 1;

            $sms_status = false;
            $email_status = false;
            $errors = [];

            // A. Dispatch SMS via SMSOnlineGh
            if (!empty($phone) && function_exists('send_smsonlinegh')) {
                $sms_res = send_smsonlinegh($phone, "$title: $sms_msg");
                if (isset($sms_res['success']) && $sms_res['success'] === true) {
                    $sms_status = true;
                } else {
                    $errors[] = "SMS: " . ($sms_res['error'] ?? 'Delivery failed');
                }
            } else {
                $sms_status = true; // Non-applicable or skipped
            }

            // B. Dispatch Email via SMTP
            if (!empty($email) && function_exists('send_smtp_mail')) {
                $body = !empty($email_body) ? $email_body : "<div style='font-family:sans-serif; padding:20px; color:#1B2B4B;'><h2>" . htmlspecialchars($title) . "</h2><p>" . htmlspecialchars($sms_msg) . "</p></div>";
                $email_res = send_smtp_mail($email, $email_subj, $body);
                if ($email_res === true) {
                    $email_status = true;
                } else {
                    $errors[] = "Email: SMTP delivery failed";
                }
            } else {
                $email_status = true; // Non-applicable or skipped
            }

            // C. Record Status & Retry Metadata
            $now_str = date('Y-m-d H:i:s');
            if ($sms_status && $email_status) {
                $succeeded++;
                $up = $pdo->prepare("UPDATE notification_queue SET status = 'sent', attempts = ?, processed_at = ?, last_error = '' WHERE id = ?");
                $up->execute([$attempts, $now_str, $job_id]);
            } else {
                $failed++;
                $err_text = implode(' | ', $errors);
                $new_status = ($attempts >= intval($job['max_attempts'] ?? 3)) ? 'failed' : 'pending';
                $up = $pdo->prepare("UPDATE notification_queue SET status = ?, attempts = ?, last_error = ? WHERE id = ?");
                $up->execute([$new_status, $attempts, $err_text, $job_id]);
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Enable direct HTTP/Cron execution or CLI execution
if (php_sapi_name() === 'cli' || isset($_GET['run']) || isset($_GET['cron'])) {
    header('Content-Type: application/json');
    $res = run_notification_queue_worker(intval($_GET['limit'] ?? 20));
    echo json_encode($res, JSON_PRETTY_PRINT);
    exit;
}
