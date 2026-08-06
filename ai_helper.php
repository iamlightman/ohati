<?php
// ai_helper.php - Simulates contextual replies from Ghanaian vendors

function generate_vendor_reply($vendor_id, $user_message) {
    global $pdo;
    
    // Fetch vendor details
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $v = $stmt->fetch();
    
    if (!$v) {
        return null;
    }
    
    // Check if custom auto response is set and not yet sent in this chat
    $auto_response = trim($v['auto_response'] ?? '');
    if (!empty($auto_response)) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE vendor_id = ? AND sender = 'vendor' AND message = ?");
        $check->execute([$vendor_id, $auto_response]);
        $has_sent = intval($check->fetchColumn()) > 0;
        if (!$has_sent) {
            return $auto_response;
        }
    }
    
    return null;
}
?>
