<?php
// admin/auth_guard.php — Ohati Admin Session Protection
date_default_timezone_set('Africa/Accra');
// Include this file at the top of every admin page AFTER session_start() and db.php

if (!isset($_SESSION['admin_user']) || ($_SESSION['admin_user']['role'] ?? '') !== 'admin') {
    // If this is an AJAX/JSON request, return 403
    if (
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
        $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Administrator access required. Please log in as admin.']);
        exit;
    }
    // For normal page loads, redirect to the admin login page
    header('Location: login.php');
    exit;
}
?>
