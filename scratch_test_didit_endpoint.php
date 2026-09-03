<?php
require_once __DIR__ . '/db.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'init_didit_kyc';
$_SESSION['user'] = ['id' => 1];
ob_start();
include __DIR__ . '/api.php';
$out = ob_get_clean();
echo $out;
