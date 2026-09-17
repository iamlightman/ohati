<?php
/**
 * Isolated, One-Time Database Migration Script: Collision-Proof Messaging Isolation
 * 
 * Safety features:
 * - Standalone CLI or secured web execution.
 * - Zero automatic execution in db.php bootstrap.
 * - Idempotent and restart-safe.
 * - Pre- and post-integrity checksum assertions with transaction rollback on error.
 * - Preserves legacy columns (vendor_id, user_id, sender) untouched for rollback safety.
 */

// Only allow execution via CLI or with explicit secret key
$is_cli = (php_sapi_name() === 'cli');
$secret = 'ohati_migrate_msg_iso_2026';
if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $secret)) {
    http_response_code(403);
    die("Access Denied: Migration must be run via CLI or with valid authorization token.\n");
}

require_once __DIR__ . '/db.php';

function out($msg) {
    echo $msg . (php_sapi_name() === 'cli' ? PHP_EOL : "<br>\n");
}

out("=================================================");
out("OHATI MESSAGING ISOLATION MIGRATION");
out("Started at: " . date('Y-m-d H:i:s'));
out("=================================================");

try {
    // 1. Check existing table
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    if (!$stmt->fetch()) {
        throw new Exception("Table 'messages' does not exist.");
    }

    // 2. Pre-migration metrics
    $pre_count = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $pre_metrics = $pdo->query("SELECT SUM(LENGTH(message)), SUM(id), MIN(id), MAX(id) FROM messages")->fetch(PDO::FETCH_NUM);
    out("Pre-Migration Metrics:");
    out("  Row count: {$pre_count}");
    out("  Sum(LENGTH(message)): {$pre_metrics[0]}");
    out("  Sum(id): {$pre_metrics[1]}");

    if ($pre_count === 0) {
        out("Notice: messages table is currently empty. Proceeding with schema update.");
    }

    // 3. Inspect existing columns
    $col_stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'");
    $existing_cols = $col_stmt->fetchAll(PDO::FETCH_COLUMN);

    $needed_cols = [
        'conversation_id' => "VARCHAR(64) NULL AFTER `id`",
        'sender_type'     => "VARCHAR(10) NULL AFTER `sender`",
        'sender_id'       => "INT(11) NULL AFTER `sender_type`",
        'recipient_type'  => "VARCHAR(10) NULL AFTER `sender_id`",
        'recipient_id'    => "INT(11) NULL AFTER `recipient_type`",
        'file_name'       => "VARCHAR(255) NULL DEFAULT ''",
        'file_size'       => "INT(11) NULL DEFAULT 0",
        'duration'        => "INT(11) NULL DEFAULT 0"
    ];

    $cols_to_add = [];
    foreach ($needed_cols as $col => $definition) {
        if (!in_array($col, $existing_cols)) {
            $cols_to_add[] = "ADD COLUMN `{$col}` {$definition}";
        }
    }

    if (!empty($cols_to_add)) {
        out("Adding missing columns to `messages`...");
        $sql = "ALTER TABLE `messages` " . implode(', ', $cols_to_add);
        $pdo->exec($sql);
        out("  Columns added successfully.");
    } else {
        out("Columns already present. Proceeding to backfill verification.");
    }

    // 4. Backfill data within transaction
    $pdo->beginTransaction();

    out("Executing ACID backfill for historical rows...");

    // Rows with sender = 'user'
    $pdo->exec("UPDATE `messages` SET 
        `conversation_id` = CONCAT('v', `vendor_id`, '_u', `user_id`),
        `sender_type` = 'user',
        `sender_id` = `user_id`,
        `recipient_type` = 'vendor',
        `recipient_id` = `vendor_id`
        WHERE `sender` = 'user' AND (`conversation_id` IS NULL OR `conversation_id` = '')
    ");

    // Rows with sender = 'vendor'
    $pdo->exec("UPDATE `messages` SET 
        `conversation_id` = CONCAT('v', `vendor_id`, '_u', `user_id`),
        `sender_type` = 'vendor',
        `sender_id` = `vendor_id`,
        `recipient_type` = 'user',
        `recipient_id` = `user_id`
        WHERE `sender` = 'vendor' AND (`conversation_id` IS NULL OR `conversation_id` = '')
    ");

    // Seed Row #3 fix (if present): Customer User 3 to Jojo Temeng Photography (Canonical Vendor 3)
    $pdo->exec("UPDATE `messages` SET 
        `conversation_id` = 'v3_u3',
        `sender_type` = 'user',
        `sender_id` = 3,
        `recipient_type` = 'vendor',
        `recipient_id` = 3
        WHERE `id` = 3 AND `vendor_id` = 2 AND `user_id` = 3
    ");

    // 5. Verify backfill assertions before committing
    $unmapped = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE conversation_id IS NULL OR sender_id IS NULL OR recipient_id IS NULL OR sender_type IS NULL OR recipient_type IS NULL")->fetchColumn();
    if ($unmapped > 0) {
        $pdo->rollBack();
        throw new Exception("Backfill failed: {$unmapped} rows have NULL canonical fields. Rolled back.");
    }

    $post_count = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $post_metrics = $pdo->query("SELECT SUM(LENGTH(message)), SUM(id), MIN(id), MAX(id) FROM messages")->fetch(PDO::FETCH_NUM);

    if ($post_count !== $pre_count || (int)$post_metrics[0] !== (int)$pre_metrics[0]) {
        $pdo->rollBack();
        throw new Exception("Checksum mismatch post-backfill. Expected {$pre_count} rows, found {$post_count}. Rolled back.");
    }

    $pdo->commit();
    out("ACID Backfill committed and verified.");

    // 6. Enforce NOT NULL constraints
    out("Enforcing NOT NULL constraints on canonical columns...");
    $pdo->exec("ALTER TABLE `messages`
        MODIFY `conversation_id` VARCHAR(64) NOT NULL,
        MODIFY `sender_type` VARCHAR(10) NOT NULL,
        MODIFY `sender_id` INT(11) NOT NULL,
        MODIFY `recipient_type` VARCHAR(10) NOT NULL,
        MODIFY `recipient_id` INT(11) NOT NULL
    ");
    out("  Constraints enforced.");

    // 7. Index verification and creation
    $idx_stmt = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'");
    $existing_indexes = $idx_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('idx_msg_convo_id', $existing_indexes)) {
        out("Creating index `idx_msg_convo_id` on (conversation_id, id)...");
        $pdo->exec("CREATE INDEX `idx_msg_convo_id` ON `messages` (`conversation_id`, `id`)");
        out("  Index `idx_msg_convo_id` created.");
    } else {
        out("Index `idx_msg_convo_id` already exists.");
    }

    if (!in_array('idx_msg_recipient_unread', $existing_indexes)) {
        out("Creating index `idx_msg_recipient_unread` on (recipient_type, recipient_id, is_read)...");
        $pdo->exec("CREATE INDEX `idx_msg_recipient_unread` ON `messages` (`recipient_type`, `recipient_id`, `is_read`)");
        out("  Index `idx_msg_recipient_unread` created.");
    } else {
        out("Index `idx_msg_recipient_unread` already exists.");
    }

    out("=================================================");
    out("MIGRATION COMPLETED SUCCESSFULLY!");
    out("Finished at: " . date('Y-m-d H:i:s'));
    out("=================================================");

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    out("ERROR OCCURRED: " . $e->getMessage());
    exit(1);
}
