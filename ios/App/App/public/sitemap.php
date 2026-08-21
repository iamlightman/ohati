<?php
// sitemap.php — Dynamic Google XML Sitemap Generator for Ohati
header("Content-Type: application/xml; charset=utf-8");

require_once __DIR__ . '/db.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'ohati.com';
$base_url = $protocol . $domainName;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Homepage
echo '  <url>' . "\n";
echo '    <loc>' . htmlspecialchars($base_url . '/') . '</loc>' . "\n";
echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
echo '    <changefreq>daily</changefreq>' . "\n";
echo '    <priority>1.0</priority>' . "\n";
echo '  </url>' . "\n";

// 2. Search Page
echo '  <url>' . "\n";
echo '    <loc>' . htmlspecialchars($base_url . '/search.php') . '</loc>' . "\n";
echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
echo '    <changefreq>daily</changefreq>' . "\n";
echo '    <priority>0.9</priority>' . "\n";
echo '  </url>' . "\n";

// 3. Static Public Pages & Categories
$static_pages = [
    '/blog.php' => ['freq' => 'daily', 'prio' => '0.9'],
    '/vendor-register.php' => ['freq' => 'weekly', 'prio' => '0.8'],
    '/privacy_policy.php' => ['freq' => 'monthly', 'prio' => '0.5'],
    '/terms.php' => ['freq' => 'monthly', 'prio' => '0.5'],
    '/help.php' => ['freq' => 'monthly', 'prio' => '0.6'],
];

$categories = [
    'Photography', 'Videography', 'Makeup Artists', 'Event Planners',
    'Decorators', 'Caterers', 'Cake Designers', 'Event Venues', 'DJs',
    'MCs', 'Live Bands', 'Florists', 'Car Rentals', 'Chilling Services'
];

foreach ($categories as $cat) {
    $static_pages['/search.php?category=' . urlencode($cat)] = ['freq' => 'daily', 'prio' => '0.85'];
}

foreach ($static_pages as $page => $meta) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($base_url . $page) . '</loc>' . "\n";
    echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
    echo '    <changefreq>' . $meta['freq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $meta['prio'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

// 4. Dynamic Blog Article Pages
try {
    $b_stmt = $pdo->query("SELECT id, updated_at, created_at FROM blog_posts ORDER BY id DESC");
    while ($b = $b_stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($b['updated_at']) ? date('Y-m-d', strtotime($b['updated_at'])) : date('Y-m-d', strtotime($b['created_at'] ?: 'now'));
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($base_url . '/blog.php?id=' . $b['id']) . '</loc>' . "\n";
        echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.8</priority>' . "\n";
        echo '  </url>' . "\n";
    }
} catch (Exception $e) {}

// 5. Dynamic Vendor Directory Pages
try {
    $stmt = $pdo->query("SELECT id, updated_at FROM vendors ORDER BY id DESC");
    while ($v = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($v['updated_at']) ? date('Y-m-d', strtotime($v['updated_at'])) : date('Y-m-d');
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($base_url . '/detail.php?id=' . $v['id']) . '</loc>' . "\n";
        echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.85</priority>' . "\n";
        echo '  </url>' . "\n";
    }
} catch (Exception $e) {}

echo '</urlset>';
