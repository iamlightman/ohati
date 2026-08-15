<?php
$db_file = __DIR__ . '/../db.php';
if (!file_exists($db_file)) {
    $db_file = __DIR__ . '/db.php';
}
require_once $db_file;

// 1. Check for duplicate vendor names
$stmt = $pdo->query("SELECT name, COUNT(*) as cnt FROM vendors GROUP BY name HAVING cnt > 1");
$dups = $stmt->fetchAll();

echo "Found " . count($dups) . " duplicate vendor names in SQLite database:\n";
foreach ($dups as $d) {
    echo " - " . $d['name'] . " (Count: " . $d['cnt'] . ")\n";
}

// 2. Delete duplicate rows, keeping the smallest ID for each vendor name
$stmt = $pdo->query("DELETE FROM vendors WHERE id NOT IN (SELECT min_id FROM (SELECT MIN(id) as min_id FROM vendors GROUP BY name) as t)");
echo "Deleted " . $stmt->rowCount() . " duplicate vendor rows.\n";

// 3. Inspect remaining vendors in SQLite
$vendors = $pdo->query("SELECT id, name, category, logo FROM vendors ORDER BY id ASC")->fetchAll();
echo "\nRemaining Unique Vendors (" . count($vendors) . "):\n";
foreach ($vendors as $v) {
    echo "ID {$v['id']}: {$v['name']} ({$v['category']}) -> Logo: {$v['logo']}\n";
}
