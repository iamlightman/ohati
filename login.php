<?php
// login.php - Seamless Redirect to Single Unified In-App Login Screen
session_start();
header('Location: index.php?action=login');
exit;
?>
