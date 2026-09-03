<?php
// add_new_vendor_categories.php - Script to insert requested vendor categories into database
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=========================================================================\n";
echo "=== ADDING NEW VENDOR CATEGORIES TO OHATI DATABASE ===\n";
echo "=========================================================================\n\n";

$newCategories = [
    ['name' => 'Dowry Wrapping', 'icon' => 'gift', 'description' => 'Custom traditional dowry packaging, wrapping & presentation services'],
    ['name' => 'Breakfast', 'icon' => 'mug-hot', 'description' => 'Morning event catering, breakfast buffets & brunch services'],
    ['name' => 'Coordinators', 'icon' => 'clipboard-list', 'description' => 'Day-of event coordinators & logistics managers'],
    ['name' => 'Waiters', 'icon' => 'concierge-bell', 'description' => 'Professional event waitstaff, servers & banquet assistants'],
    ['name' => 'Portable Washroom', 'icon' => 'restroom', 'description' => 'Luxury portable restrooms & mobile sanitation units'],
    ['name' => 'Souvenirs', 'icon' => 'bag-shopping', 'description' => 'Customized event souvenirs, party favors & gift packages'],
    ['name' => 'Hairstylists', 'icon' => 'scissors', 'description' => 'Professional bridal & event hair styling services'],
    ['name' => 'Dowry Bearers', 'icon' => 'people-group', 'description' => 'Traditional marriage dowry presentation team & escorts'],
    ['name' => 'Local Bar', 'icon' => 'beer-mug-empty', 'description' => 'Traditional local bar, palm wine & custom local beverage services']
];

try {
    $insertedCount = 0;
    $existCount = 0;

    $chkStmt = $pdo->prepare("SELECT id FROM vendor_categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $insStmt = $pdo->prepare("INSERT INTO vendor_categories (name, slug, icon, description, display_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");

    $maxOrder = $pdo->query("SELECT MAX(display_order) FROM vendor_categories")->fetchColumn();
    $nextOrder = intval($maxOrder) > 0 ? intval($maxOrder) + 1 : 1;

    foreach ($newCategories as $cat) {
        $chkStmt->execute([$cat['name']]);
        if ($chkStmt->fetch()) {
            echo "[EXISTING] Category '{$cat['name']}' already exists in database.\n";
            $existCount++;
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $cat['name']), '-'));
            $insStmt->execute([$cat['name'], $slug, $cat['icon'], $cat['description'], $nextOrder]);
            echo "[ADDED] Successfully added category '{$cat['name']}' (Icon: {$cat['icon']}).\n";
            $insertedCount++;
            $nextOrder++;
        }
    }

    echo "\n=========================================================================\n";
    echo "=== SUMMARY: $insertedCount new categories added, $existCount already existed. ===\n";
    echo "=========================================================================\n";

} catch (Exception $e) {
    echo "[ERROR] Failed to add categories: " . $e->getMessage() . "\n";
}
