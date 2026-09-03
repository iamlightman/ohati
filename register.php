<?php
// register.php - Seamless Redirect to Single Unified In-App Registration Screen
session_start();
$target = $_GET['target'] ?? '';
$url = 'index.php?action=register' . (!empty($target) ? '&target=' . urlencode($target) : '');
header('Location: ' . $url);
exit;
?>
