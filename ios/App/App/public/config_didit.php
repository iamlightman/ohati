<?php
// config_didit.php - Secure Server-Side Didit API V3 Configuration

if (!defined('DIDIT_API_KEY')) {
    define('DIDIT_API_KEY', getenv('DIDIT_API_KEY') ?: 'st1r7T7TkHIuUVebBtOSAkb0FC3nsqXmZ6g7T7RoXKI');
}

if (!defined('DIDIT_WORKFLOW_ID')) {
    define('DIDIT_WORKFLOW_ID', getenv('DIDIT_WORKFLOW_ID') ?: '7822b366-5c07-4bad-a8bd-5a9da85c3297');
}

if (!defined('DIDIT_WEBHOOK_SECRET')) {
    define('DIDIT_WEBHOOK_SECRET', getenv('DIDIT_WEBHOOK_SECRET') ?: 'akaEyO3wlvcBoAN6GN9MeDQ9drv5ZRGeCyd34v39mvc');
}

if (!defined('DIDIT_BASE_URL')) {
    define('DIDIT_BASE_URL', 'https://verification.didit.me/v3/');
}
