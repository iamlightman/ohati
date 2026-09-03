<?php
// payment_api.php - Ohati Escrow & Wallet API Handlers

// Prevent direct access
if (!defined('DB_NAME') && !isset($pdo)) {
    http_response_code(403);
    die('Direct access forbidden.');
}

// Check CSRF on all POST payment actions
function verify_payment_csrf() {
    global $raw_input;
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_POST['csrf_token'] ?? $raw_input['csrf_token'] ?? '';
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = !empty($token) ? $token : bin2hex(random_bytes(32));
    } elseif (!empty($token)) {
        $_SESSION['csrf'] = $token;
    }
    return true;
}

// Main handler dispatcher
function handle_payment_action($action, $pdo) {
    $is_admin = (isset($_SESSION['admin_user']) && ($_SESSION['admin_user']['role'] ?? '') === 'admin') || (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
    $admin_user_id = $_SESSION['admin_user']['id'] ?? $_SESSION['user']['id'] ?? 0;
    switch ($action) {
        case 'get_payment_instructions':
            $s_rows = $pdo->query("SELECT key_name, val_value FROM system_settings WHERE key_name LIKE 'admin_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
            $bank_details = [
                'bank_name' => $s_rows['admin_bank_name'] ?? 'Ecobank Ghana',
                'account_name' => $s_rows['admin_account_name'] ?? 'Ohati Global Digital Services',
                'account_number' => $s_rows['admin_account_number'] ?? '1441002939201',
                'momo_provider' => $s_rows['admin_momo_provider'] ?? 'MTN Mobile Money',
                'momo_number' => $s_rows['admin_momo_number'] ?? '0540477911',
                'momo_name' => $s_rows['admin_momo_name'] ?? 'Ohati Payments',
                'payment_instructions' => $s_rows['admin_payment_instructions'] ?? 'Please transfer the exact payment amount to our Admin Bank Account or Mobile Money. After completing your payment, enter your Transaction ID (TxID) below for Admin verification.'
            ];
            echo json_encode(['success' => true, 'bank_details' => $bank_details]);
            break;

        case 'initiate_paystack_payment':
        case 'initiate_booking_payment':
        case 'initiate_manual_payment':
            verify_payment_csrf();
            if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $booking_id = intval($input['booking_id'] ?? 0);
            $payment_type = in_array($input['type'] ?? '', ['deposit', 'balance', 'full']) ? $input['type'] : 'deposit';

            $stmt = $pdo->prepare("SELECT b.*, v.user_id as vendor_user_id FROM bookings b JOIN vendors v ON b.vendor_id = v.id WHERE b.id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch();
            if (!$booking) { http_response_code(404); echo json_encode(['error'=>'Booking not found.']); exit; }

            // Determine amount
            $amount = 0;
            if ($payment_type === 'deposit') {
                $amount = $booking['negotiated_price'] > 0 ? ($booking['negotiated_price'] * 0.5) : ($booking['price'] * 0.5);
            } elseif ($payment_type === 'balance') {
                $amount = $booking['negotiated_price'] > 0 ? ($booking['negotiated_price'] * 0.5) : ($booking['price'] * 0.5);
            } else {
                $amount = $booking['negotiated_price'] > 0 ? $booking['negotiated_price'] : $booking['price'];
            }

            if ($amount <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid payment amount.']); exit; }

            // Manual Admin Payment Request
            $reference = 'OH-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            
            $platform_fee = $amount * 0.10; // 10% platform fee
            $vendor_amount = $amount - $platform_fee;

            // Insert escrow transaction in pending_submission state
            $stmt = $pdo->prepare("INSERT INTO escrow_transactions (booking_id, customer_id, vendor_id, amount, platform_fee, vendor_amount, paystack_reference, paystack_status, escrow_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_submission', 'pending')");
            $stmt->execute([$booking_id, $_SESSION['user']['id'], $booking['vendor_id'], $amount, $platform_fee, $vendor_amount, $reference]);
            $escrow_id = $pdo->lastInsertId();

            audit_log($pdo, 'initiate_payment', 'escrow_transactions', $escrow_id, $amount, 'pending', 'pending', "Initiated $payment_type manual payment for booking #$booking_id");

            // Fetch admin bank/momo details from system settings
            $s_rows = $pdo->query("SELECT key_name, val_value FROM system_settings WHERE key_name LIKE 'admin_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
            $bank_details = [
                'bank_name' => $s_rows['admin_bank_name'] ?? 'Ecobank Ghana',
                'account_name' => $s_rows['admin_account_name'] ?? 'Ohati Global Digital Services',
                'account_number' => $s_rows['admin_account_number'] ?? '1441002939201',
                'momo_provider' => $s_rows['admin_momo_provider'] ?? 'MTN Mobile Money',
                'momo_number' => $s_rows['admin_momo_number'] ?? '0540477911',
                'momo_name' => $s_rows['admin_momo_name'] ?? 'Ohati Payments',
                'payment_instructions' => $s_rows['admin_payment_instructions'] ?? 'Please transfer the exact amount to our Admin Bank Account or Mobile Money. Enter your Transaction Reference (TxID) below after completing the payment.'
            ];

            echo json_encode([
                'success' => true,
                'reference' => $reference,
                'amount' => $amount,
                'payment_method' => 'manual_admin',
                'bank_details' => $bank_details
            ]);
            break;

        case 'verify_paystack_payment':
        case 'submit_manual_payment':
            verify_payment_csrf();
            $input = json_decode(file_get_contents('php://input'), true);
            $reference = clean($input['reference'] ?? '');
            $tx_id = clean($input['tx_id'] ?? $input['transaction_id'] ?? '');

            if (empty($reference)) { http_response_code(400); echo json_encode(['error'=>'Reference code is required.']); exit; }
            if (empty($tx_id)) { http_response_code(400); echo json_encode(['error'=>'Please provide your payment Transaction ID (TxID) or Mobile Money reference.']); exit; }

            // Check if transaction exists
            $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE paystack_reference = ?");
            $stmt->execute([$reference]);
            $escrow = $stmt->fetch();
            if (!$escrow) { http_response_code(404); echo json_encode(['error'=>'Transaction reference not found.']); exit; }

            if ($escrow['paystack_status'] === 'success') {
                echo json_encode(['success' => true, 'message' => 'Payment already verified as successful by Admin.', 'escrow' => $escrow]);
                exit;
            }

            $pdo->beginTransaction();
            try {
                // Update transaction status to pending_verification (AWAITING ADMIN REVIEW)
                $stmt = $pdo->prepare("UPDATE escrow_transactions SET paystack_status = 'pending_verification', notes = ? WHERE id = ?");
                $stmt->execute(["TxID submitted by customer: $tx_id", $escrow['id']]);

                // Update booking status to Pending Verification
                $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'Pending Verification' WHERE id = ?");
                $stmt->execute([$escrow['booking_id']]);

                audit_log($pdo, 'payment_submitted', 'escrow_transactions', $escrow['id'], $escrow['amount'], 'pending_submission', 'pending_verification', "Customer submitted TxID: $tx_id for Admin verification.");

                // Notify admin via system notification
                $admin_ids = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
                if (empty($admin_ids)) $admin_ids = [1];
                foreach ($admin_ids as $aid) {
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'New Payment Submission', ?, 'money-bill-wave')");
                    $stmt->execute([$aid, "Payment of GH₵ " . number_format($escrow['amount'], 2) . " submitted for Booking #" . $escrow['booking_id'] . " (TxID: $tx_id). Awaiting Admin approval."]);
                }

                $pdo->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Payment evidence submitted successfully! Your transaction is currently pending Admin verification.',
                    'pending_verification' => true
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'admin_approve_payment':
            verify_payment_csrf();
            if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $escrow_id = intval($input['escrow_id'] ?? 0);
            $reference = clean($input['reference'] ?? '');

            if ($escrow_id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE id = ?");
                $stmt->execute([$escrow_id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE paystack_reference = ?");
                $stmt->execute([$reference]);
            }
            $escrow = $stmt->fetch();
            if (!$escrow) { http_response_code(404); echo json_encode(['error'=>'Escrow transaction record not found.']); exit; }
            if ($escrow['paystack_status'] === 'success') { echo json_encode(['success'=>true, 'message'=>'Payment is already approved.']); exit; }

            $pdo->beginTransaction();
            try {
                // Update escrow transaction status
                $stmt = $pdo->prepare("UPDATE escrow_transactions SET paystack_status = 'success', escrow_status = 'held', released_by = ?, released_at = ? WHERE id = ?");
                $stmt->execute([$admin_user_id, date('Y-m-d H:i:s'), $escrow['id']]);

                // Insert payment record
                $provider_ref = !empty($escrow['notes']) ? str_replace('TxID submitted by customer: ', '', $escrow['notes']) : $escrow['paystack_reference'];
                $stmt = $pdo->prepare("INSERT INTO payments (booking_id, user_id, vendor_id, amount, provider_ref, status, type) VALUES (?, ?, ?, ?, ?, 'success', 'escrow_hold')");
                $stmt->execute([$escrow['booking_id'], $escrow['customer_id'], $escrow['vendor_id'], $escrow['amount'], $provider_ref]);

                // Update booking payment status
                $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
                $stmt->execute([$escrow['booking_id']]);
                $booking = $stmt->fetch();

                $new_total_paid = $booking['total_paid'] + $escrow['amount'];
                $price_to_match = $booking['negotiated_price'] > 0 ? $booking['negotiated_price'] : $booking['price'];
                $payment_status = ($new_total_paid >= $price_to_match) ? 'Paid' : 'Partially Paid';

                $stmt = $pdo->prepare("UPDATE bookings SET total_paid = ?, payment_status = ?, status = 'Confirmed', escrow_held = escrow_held + ? WHERE id = ?");
                $stmt->execute([$new_total_paid, $payment_status, $escrow['amount'], $escrow['booking_id']]);

                // Ensure vendor wallet exists & update escrow balance
                ensure_wallet($pdo, $escrow['vendor_id'], $booking['user_id']);
                $stmt = $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance + ?, pending_balance = pending_balance + ? WHERE vendor_id = ?");
                $stmt->execute([$escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_id']]);

                audit_log($pdo, 'payment_verified_admin', 'escrow_transactions', $escrow['id'], $escrow['amount'], 'pending_verification', 'held', "Admin approved manual payment (TxID: $provider_ref).");

                // Notify customer & vendor
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Payment Verified & Confirmed! 🎉', ?, 'circle-check')");
                $stmt->execute([$escrow['customer_id'], "Admin has verified your payment of GH₵ " . number_format($escrow['amount'], 2) . " for booking #" . $escrow['booking_id'] . ". Funds are held securely in escrow."]);

                $stmt = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
                $stmt->execute([$escrow['vendor_id']]);
                $vendor_user_id = $stmt->fetchColumn();
                if ($vendor_user_id) {
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Booking Payment Confirmed', ?, 'wallet')");
                    $stmt->execute([$vendor_user_id, "Payment of GH₵ " . number_format($escrow['vendor_amount'], 2) . " (after fee) for booking #" . $escrow['booking_id'] . " was verified by Admin and is held in escrow."]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Payment approved and verified successfully. Booking is now Confirmed!']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'admin_reject_payment':
            verify_payment_csrf();
            if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $escrow_id = intval($input['escrow_id'] ?? 0);
            $reason = clean($input['reason'] ?? 'Payment evidence could not be verified by Administration.');

            $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE id = ?");
            $stmt->execute([$escrow_id]);
            $escrow = $stmt->fetch();
            if (!$escrow) { http_response_code(404); echo json_encode(['error'=>'Escrow transaction not found.']); exit; }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE escrow_transactions SET paystack_status = 'rejected', escrow_status = 'rejected', notes = ? WHERE id = ?");
                $stmt->execute(["Rejected by Admin: $reason", $escrow['id']]);

                $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'Unpaid' WHERE id = ?");
                $stmt->execute([$escrow['booking_id']]);

                audit_log($pdo, 'payment_rejected_admin', 'escrow_transactions', $escrow['id'], $escrow['amount'], 'pending_verification', 'rejected', "Admin rejected payment: $reason");

                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Payment Verification Failed', ?, 'circle-xmark')");
                $stmt->execute([$escrow['customer_id'], "Admin could not verify your payment for booking #" . $escrow['booking_id'] . ". Reason: $reason. Please resubmit valid payment evidence."]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Payment rejected successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'update_admin_payment_settings':
            verify_payment_csrf();
            if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            
            $settings = [
                'admin_bank_name' => clean($input['bank_name'] ?? 'Ecobank Ghana'),
                'admin_account_name' => clean($input['account_name'] ?? 'Ohati Global Digital Services'),
                'admin_account_number' => clean($input['account_number'] ?? '1441002939201'),
                'admin_momo_provider' => clean($input['momo_provider'] ?? 'MTN Mobile Money'),
                'admin_momo_number' => clean($input['momo_number'] ?? '0540477911'),
                'admin_momo_name' => clean($input['momo_name'] ?? 'Ohati Payments'),
                'admin_payment_instructions' => clean($input['payment_instructions'] ?? '')
            ];

            foreach ($settings as $k => $v) {
                $chk_p = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = ?");
                $chk_p->execute([$k]);
                if ($chk_p->fetchColumn() > 0) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = ?");
                    $stmt->execute([$v, $k]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES (?, ?)");
                    $stmt->execute([$k, $v]);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Admin payment details updated successfully!']);
            break;

        case 'get_vendor_wallet':
            if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
            
            // Check if vendor
            $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
            $stmt->execute([$_SESSION['user']['id']]);
            $vendor = $stmt->fetch();
            if (!$vendor) { http_response_code(403); echo json_encode(['error'=>'Access denied. Only vendors have wallets.']); exit; }

            ensure_wallet($pdo, $vendor['id'], $_SESSION['user']['id']);

            // Get wallet balances
            $stmt = $pdo->prepare("SELECT * FROM vendor_wallets WHERE vendor_id = ?");
            $stmt->execute([$vendor['id']]);
            $wallet = $stmt->fetch();

            // Fetch transaction history
            $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE vendor_id = ? ORDER BY id DESC LIMIT 50");
            $stmt->execute([$vendor['id']]);
            $transactions = $stmt->fetchAll();

            // Fetch withdrawal history
            $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE vendor_id = ? ORDER BY id DESC LIMIT 50");
            $stmt->execute([$vendor['id']]);
            $withdrawals = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'wallet' => $wallet,
                'transactions' => $transactions,
                'withdrawals' => $withdrawals
            ]);
            break;

        case 'request_withdrawal':
            verify_payment_csrf();
            if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $amount = floatval($input['amount'] ?? 0);

            // Fetch vendor info
            $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
            $stmt->execute([$_SESSION['user']['id']]);
            $vendor = $stmt->fetch();
            if (!$vendor) { http_response_code(403); echo json_encode(['error'=>'Access denied.']); exit; }

            // Security checks
            if ($vendor['verified'] != 1) { http_response_code(400); echo json_encode(['error'=>'Withdrawals blocked. Vendor identity (KYC) not verified.']); exit; }

            // Ensure bank account matches KYC name
            if (empty($vendor['bank_name']) || empty($vendor['account_number']) || empty($vendor['account_name'])) {
                http_response_code(400); echo json_encode(['error'=>'Please complete your payout details (bank/MOMO accounts) first.']); exit;
            }

            // Simple match rule: Check if KYC/User name matches Account Name
            $user_name = strtolower(trim($_SESSION['user']['name']));
            $acc_name = strtolower(trim($vendor['account_name']));
            
            // Check if there is a name match
            $matched = false;
            $user_parts = explode(' ', $user_name);
            foreach ($user_parts as $part) {
                if (strlen($part) > 2 && strpos($acc_name, $part) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                http_response_code(400);
                echo json_encode(['error'=>'Payout account holder name does not reasonably match your Ohati KYC name. Request blocked for security review.']);
                exit;
            }

            ensure_wallet($pdo, $vendor['id'], $_SESSION['user']['id']);

            // Get wallet
            $stmt = $pdo->prepare("SELECT * FROM vendor_wallets WHERE vendor_id = ?");
            $stmt->execute([$vendor['id']]);
            $wallet = $stmt->fetch();

            if ($wallet['is_frozen'] == 1) { http_response_code(400); echo json_encode(['error'=>'Your wallet is frozen by administration: ' . ($wallet['frozen_reason'] ?: 'No details provided.')]); exit; }
            if ($amount < 1000) { http_response_code(400); echo json_encode(['error'=>'Minimum withdrawal amount is GH₵ 1,000.']); exit; }
            if ($amount > $wallet['available_balance']) { http_response_code(400); echo json_encode(['error'=>'Insufficient available balance.']); exit; }

            // Begin withdrawal request transaction
            $pdo->beginTransaction();
            try {
                // Deduct from available balance, add to processing
                $stmt = $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance - ?, processing_balance = processing_balance + ? WHERE id = ?");
                $stmt->execute([$amount, $amount, $wallet['id']]);

                // Create withdrawal record
                $stmt = $pdo->prepare("INSERT INTO withdrawals (vendor_id, user_id, amount, net_amount, bank_name, account_name, account_number, status, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                $stmt->execute([$vendor['id'], $_SESSION['user']['id'], $amount, $amount, $vendor['bank_name'], $vendor['account_name'], $vendor['account_number'], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
                $withdrawal_id = $pdo->lastInsertId();

                audit_log($pdo, 'withdrawal_requested', 'withdrawals', $withdrawal_id, $amount, 'none', 'pending', "Vendor #{$vendor['id']} requested GHS $amount payout");

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Withdrawal request submitted. Payout pending administration approval.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'release_escrow':
            verify_payment_csrf();
            if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $escrow_id = intval($input['escrow_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE id = ?");
            $stmt->execute([$escrow_id]);
            $escrow = $stmt->fetch();
            if (!$escrow) { http_response_code(404); echo json_encode(['error'=>'Escrow transaction not found.']); exit; }

            // Verify authorization
            $is_customer = (isset($_SESSION['user']) && $_SESSION['user']['id'] === $escrow['customer_id']);

            if (!$is_admin && !$is_customer) {
                http_response_code(403); echo json_encode(['error'=>'Unauthorized. Only the booking client or platform admin can release escrow.']); exit;
            }

            if ($escrow['escrow_status'] !== 'held') {
                http_response_code(400); echo json_encode(['error'=>'Funds cannot be released. Current status: ' . $escrow['escrow_status']]); exit;
            }

            $pdo->beginTransaction();
            try {
                // Update escrow status
                $stmt = $pdo->prepare("UPDATE escrow_transactions SET escrow_status = 'released', released_by = ?, released_at = ?, release_reason = ? WHERE id = ?");
                $stmt->execute([$admin_user_id, date('Y-m-d H:i:s'), $is_admin ? 'Admin Override' : 'Client Approved', $escrow['id']]);

                // Deduct escrow, move to available
                ensure_wallet($pdo, $escrow['vendor_id'], 0); // User id not strictly needed if wallet exists
                $stmt = $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, pending_balance = pending_balance - ?, available_balance = available_balance + ?, lifetime_earnings = lifetime_earnings + ? WHERE vendor_id = ?");
                $stmt->execute([$escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_id']]);

                // Log activity
                audit_log($pdo, 'escrow_released', 'escrow_transactions', $escrow['id'], $escrow['amount'], 'held', 'released', "Funds released to vendor wallet. Amount: GHS " . $escrow['vendor_amount']);

                // Notify Vendor
                $stmt = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
                $stmt->execute([$escrow['vendor_id']]);
                $vendor_user_id = $stmt->fetchColumn();
                if ($vendor_user_id) {
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Escrow Released!', ?, 'hand-holding-dollar')");
                    $stmt->execute([$vendor_user_id, "GHS " . number_format($escrow['vendor_amount'], 2) . " has been added to your available balance."]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Escrow funds successfully released to the vendor.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'raise_dispute':
            verify_payment_csrf();
            if (!isset($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $booking_id = intval($input['booking_id'] ?? 0);
            $subject = clean($input['subject'] ?? '');
            $desc = clean($input['description'] ?? '');

            // Fetch escrow details
            $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE booking_id = ? AND escrow_status = 'held'");
            $stmt->execute([$booking_id]);
            $escrow = $stmt->fetch();
            if (!$escrow) { http_response_code(400); echo json_encode(['error'=>'No active escrow held for this booking.']); exit; }

            if ($escrow['customer_id'] !== $_SESSION['user']['id']) {
                http_response_code(403); echo json_encode(['error'=>'Only the booking client can initiate a dispute.']); exit;
            }

            $pdo->beginTransaction();
            try {
                // Freeze escrow transaction
                $stmt = $pdo->prepare("UPDATE escrow_transactions SET escrow_status = 'disputed', frozen = 1, frozen_reason = ? WHERE id = ?");
                $stmt->execute(["Dispute opened: $subject", $escrow['id']]);

                // Log dispute
                $stmt = $pdo->prepare("INSERT INTO disputes (booking_id, escrow_id, customer_id, vendor_id, subject, description, status, frozen_amount) VALUES (?, ?, ?, ?, ?, ?, 'open', ?)");
                $stmt->execute([$booking_id, $escrow['id'], $_SESSION['user']['id'], $escrow['vendor_id'], $subject, $desc, $escrow['amount']]);
                $dispute_id = $pdo->lastInsertId();

                // Lock wallet funds
                $stmt = $pdo->prepare("UPDATE vendor_wallets SET is_frozen = 1, frozen_reason = ? WHERE vendor_id = ?");
                $stmt->execute(["Dispute opened on booking #$booking_id. Wallet actions limited.", $escrow['vendor_id']]);

                audit_log($pdo, 'dispute_opened', 'disputes', $dispute_id, $escrow['amount'], 'held', 'disputed', "Client opened dispute: $subject");

                // Notify vendor
                $stmt = $pdo->prepare("SELECT user_id FROM vendors WHERE id = ?");
                $stmt->execute([$escrow['vendor_id']]);
                $vendor_user_id = $stmt->fetchColumn();
                if ($vendor_user_id) {
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Dispute Opened', ?, 'triangle-exclamation')");
                    $stmt->execute([$vendor_user_id, "A customer has disputed booking #$booking_id. Funds have been frozen pending review."]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Dispute raised. Escrow funds successfully frozen. Admin team will review.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'resolve_dispute':
            verify_payment_csrf();
            if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $dispute_id = intval($input['dispute_id'] ?? 0);
            $resolution = in_array($input['resolution'] ?? '', ['refund_customer', 'release_vendor']) ? $input['resolution'] : '';
            $notes = clean($input['notes'] ?? '');

            if (!$resolution) { http_response_code(400); echo json_encode(['error'=>'Invalid resolution choice.']); exit; }

            $stmt = $pdo->prepare("SELECT * FROM disputes WHERE id = ?");
            $stmt->execute([$dispute_id]);
            $dispute = $stmt->fetch();
            if (!$dispute) { http_response_code(404); echo json_encode(['error'=>'Dispute record not found.']); exit; }
            if ($dispute['status'] !== 'open') { http_response_code(400); echo json_encode(['error'=>'Dispute is already resolved.']); exit; }

            // Fetch escrow transaction
            $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE id = ?");
            $stmt->execute([$dispute['escrow_id']]);
            $escrow = $stmt->fetch();

            $pdo->beginTransaction();
            try {
                if ($resolution === 'release_vendor') {
                    // Update escrow
                    $stmt = $pdo->prepare("UPDATE escrow_transactions SET escrow_status = 'released', frozen = 0, released_by = ?, released_at = ?, release_reason = 'Dispute Resolved' WHERE id = ?");
                    $stmt->execute([$admin_user_id, date('Y-m-d H:i:s'), $escrow['id']]);

                    // Add funds to vendor's available balance
                    $stmt = $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, pending_balance = pending_balance - ?, available_balance = available_balance + ?, lifetime_earnings = lifetime_earnings + ?, is_frozen = 0 WHERE vendor_id = ?");
                    $stmt->execute([$escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_id']]);

                    audit_log($pdo, 'dispute_resolved_vendor', 'disputes', $dispute_id, $escrow['amount'], 'open', 'resolved', "Released GHS {$escrow['vendor_amount']} to vendor.");
                    
                    // Notify parties
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Dispute Resolved in Your Favor', ?, 'gavel')");
                    $stmt->execute([$dispute['customer_id'], "Dispute on booking #{$dispute['booking_id']} resolved. Funds released to vendor."]);
                } else {
                    // Refund customer
                    $stmt = $pdo->prepare("UPDATE escrow_transactions SET escrow_status = 'refunded', frozen = 0 WHERE id = ?");
                    $stmt->execute([$escrow['id']]);

                    // Deduct from vendor's pending/escrow balances, lift freeze
                    $stmt = $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, pending_balance = pending_balance - ?, total_refunded = total_refunded + ?, is_frozen = 0 WHERE vendor_id = ?");
                    $stmt->execute([$escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_amount'], $escrow['vendor_id']]);

                    // Create refund record
                    $stmt = $pdo->prepare("INSERT INTO refunds (escrow_id, booking_id, dispute_id, customer_id, vendor_id, amount, type, status, approved_by, approved_at) VALUES (?, ?, ?, ?, ?, ?, 'full', 'approved', ?, ?)");
                    $stmt->execute([$escrow['id'], $dispute['booking_id'], $dispute_id, $dispute['customer_id'], $dispute['vendor_id'], $escrow['amount'], $admin_user_id, date('Y-m-d H:i:s')]);

                    audit_log($pdo, 'dispute_resolved_refund', 'disputes', $dispute_id, $escrow['amount'], 'open', 'resolved', "Refunded GHS {$escrow['amount']} to customer.");

                    // Notify parties
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Refund Approved', ?, 'circle-dollar-to-slot')");
                    $stmt->execute([$dispute['customer_id'], "Dispute resolved. A refund of GHS " . number_format($escrow['amount'], 2) . " has been issued."]);
                }

                // Update dispute status
                $stmt = $pdo->prepare("UPDATE disputes SET status = 'resolved', resolution = ?, resolution_notes = ?, resolved_by = ?, resolved_at = ? WHERE id = ?");
                $stmt->execute([$resolution, $notes, $admin_user_id, date('Y-m-d H:i:s'), $dispute_id]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Dispute successfully resolved.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'admin_get_financials':
            if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }

            // Summarize all balances
            $escrow_total = $pdo->query("SELECT SUM(amount) FROM escrow_transactions WHERE escrow_status = 'held'")->fetchColumn() ?: 0;
            $withdrawn_total = $pdo->query("SELECT SUM(amount) FROM withdrawals WHERE status = 'completed'")->fetchColumn() ?: 0;
            $pending_withdrawals = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn() ?: 0;
            $open_disputes = $pdo->query("SELECT COUNT(*) FROM disputes WHERE status = 'open'")->fetchColumn() ?: 0;

            // Fetch withdrawals queue
            $withdrawals = $pdo->query("SELECT w.*, v.name as vendor_name, u.name as user_name FROM withdrawals w JOIN vendors v ON w.vendor_id = v.id JOIN users u ON w.user_id = u.id ORDER BY w.id DESC LIMIT 100")->fetchAll();

            // Fetch disputes list
            $disputes = $pdo->query("SELECT d.*, u.name as customer_name, v.name as vendor_name FROM disputes d JOIN users u ON d.customer_id = u.id JOIN vendors v ON d.vendor_id = v.id ORDER BY d.id DESC LIMIT 100")->fetchAll();

            // Fetch audit log
            $audit_logs = $pdo->query("SELECT * FROM financial_audit_log ORDER BY id DESC LIMIT 200")->fetchAll();

            echo json_encode([
                'success' => true,
                'summary' => [
                    'escrow_total' => $escrow_total,
                    'withdrawn_total' => $withdrawn_total,
                    'pending_withdrawals' => $pending_withdrawals,
                    'open_disputes' => $open_disputes
                ],
                'withdrawals' => $withdrawals,
                'disputes' => $disputes,
                'audit_logs' => $audit_logs
            ]);
            break;

        case 'admin_approve_withdrawal':
            verify_payment_csrf();
            if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $withdrawal_id = intval($input['withdrawal_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ?");
            $stmt->execute([$withdrawal_id]);
            $w = $stmt->fetch();
            if (!$w) { http_response_code(404); echo json_encode(['error'=>'Withdrawal request not found.']); exit; }
            if ($w['status'] !== 'pending') { http_response_code(400); echo json_encode(['error'=>'Withdrawal request is already processed. Status: ' . $w['status']]); exit; }

            // Admin manual payout approval
            $pdo->beginTransaction();
            try {
                // Update status to completed
                $stmt = $pdo->prepare("UPDATE withdrawals SET status = 'completed', approved_by = ?, approved_at = ?, completed_at = ? WHERE id = ?");
                $stmt->execute([$admin_user_id, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $withdrawal_id]);

                // Update wallet (deduct from processing balance, add to total withdrawn)
                $stmt = $pdo->prepare("UPDATE vendor_wallets SET processing_balance = processing_balance - ?, total_withdrawn = total_withdrawn + ?, last_withdrawal_at = ? WHERE vendor_id = ?");
                $stmt->execute([$w['amount'], $w['amount'], date('Y-m-d H:i:s'), $w['vendor_id']]);

                audit_log($pdo, 'withdrawal_approved', 'withdrawals', $withdrawal_id, $w['amount'], 'pending', 'completed', "Manual withdrawal request approved and processed.");

                // Notify vendor
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Payout Complete', ?, 'circle-dollar-to-slot')");
                $stmt->execute([$w['user_id'], "Your withdrawal of GHS " . number_format($w['amount'], 2) . " has been successfully transferred to your payout account."]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Withdrawal request approved and successfully completed.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'admin_reject_withdrawal':
            verify_payment_csrf();
            if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Admin access required.']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $withdrawal_id = intval($input['withdrawal_id'] ?? 0);
            $reason = clean($input['reason'] ?? 'Rejected by Administrator');

            $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ?");
            $stmt->execute([$withdrawal_id]);
            $w = $stmt->fetch();
            if (!$w) { http_response_code(404); echo json_encode(['error'=>'Withdrawal request not found.']); exit; }
            if ($w['status'] !== 'pending') { http_response_code(400); echo json_encode(['error'=>'Withdrawal is already processed.']); exit; }

            $pdo->beginTransaction();
            try {
                // Return funds to available balance
                $stmt = $pdo->prepare("UPDATE vendor_wallets SET processing_balance = processing_balance - ?, available_balance = available_balance + ? WHERE vendor_id = ?");
                $stmt->execute([$w['amount'], $w['amount'], $w['vendor_id']]);

                // Update status to rejected
                $stmt = $pdo->prepare("UPDATE withdrawals SET status = 'rejected', approved_by = ?, approved_at = ?, rejected_reason = ? WHERE id = ?");
                $stmt->execute([$admin_user_id, date('Y-m-d H:i:s'), $reason, $withdrawal_id]);

                audit_log($pdo, 'withdrawal_rejected', 'withdrawals', $withdrawal_id, $w['amount'], 'pending', 'rejected', "Rejected reason: $reason");

                // Notify vendor
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, icon) VALUES (?, 'Payout Request Rejected', ?, 'circle-xmark')");
                $stmt->execute([$w['user_id'], "Your withdrawal of GHS " . number_format($w['amount'], 2) . " was rejected. Reason: $reason"]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Withdrawal request rejected. Funds returned to vendor wallet.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown payment action: ' . $action]);
            break;
    }
}
?>
