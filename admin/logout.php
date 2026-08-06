<?php
// admin/logout.php - Administrator Sign Out Handler
session_start();
unset($_SESSION['admin_user']);
header('Location: login.php');
exit;
?>
