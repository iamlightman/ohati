<?php
// index.php - Ohati App - Find. Compare. Book. Celebrate.
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Check Maintenance Mode
require_once __DIR__ . '/db.php';
try {
    $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = 'maintenance_mode'");
    $stmt->execute();
    $maint = $stmt->fetchColumn();
    if ($maint === '1' && (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin')) {
        // Render a beautiful, premium maintenance screen
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Under Maintenance — Ohati</title>
            <link rel="stylesheet" href="style.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
            <style>
                body {
                    background: radial-gradient(circle at center, #1E2E4F 0%, #0F172A 100%);
                    color: #FFFFFF;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    padding: 20px;
                    box-sizing: border-box;
                    text-align: center;
                }
                .maintenance-card {
                    background: rgba(255, 255, 255, 0.03);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(16px);
                    border-radius: 24px;
                    padding: 40px 30px;
                    max-width: 480px;
                    width: 100%;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                }
                .maint-logo {
                    width: 70px;
                    margin-bottom: 24px;
                }
                .maint-icon-pulse {
                    font-size: 3rem;
                    color: var(--accent, #D4AF37);
                    margin-bottom: 20px;
                    animation: pulse 2s infinite ease-in-out;
                }
                @keyframes pulse {
                    0% { transform: scale(1); opacity: 0.8; }
                    50% { transform: scale(1.08); opacity: 1; }
                    100% { transform: scale(1); opacity: 0.8; }
                }
                h1 {
                    font-family: 'Fraunces', serif;
                    font-size: 1.8rem;
                    margin: 0 0 12px 0;
                    color: #FFFFFF;
                    font-weight: 700;
                }
                p {
                    color: #94A3B8;
                    font-size: 0.9rem;
                    line-height: 1.6;
                    margin: 0 0 24px 0;
                }
                .contact-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    background: var(--accent, #D4AF37);
                    color: #0F172A;
                    text-decoration: none;
                    font-weight: 700;
                    font-size: 0.85rem;
                    padding: 12px 24px;
                    border-radius: 30px;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);
                }
                .contact-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 16px rgba(212, 175, 55, 0.4);
                }
                .footer-text {
                    font-size: 0.75rem;
                    color: #64748B;
                    margin-top: 30px;
                }
            </style>
        </head>
        <body>
            <div class="maintenance-card">
                <img src="img/logo white transparent small.png" class="maint-logo" alt="Ohati Logo">
                <div class="maint-icon-pulse">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>
                <h1>System Under Upgrades</h1>
                <p>Ohati is currently undergoing planned maintenance upgrades to deliver an even smoother vendor booking experience. We'll be back online shortly!</p>
                <a href="https://wa.me/233543377470" target="_blank" class="contact-btn">
                    <i class="fa-brands fa-whatsapp"></i> Chat Support
                </a>
                <div class="footer-text">© <?= date('Y') ?> Ohati. All rights reserved.</div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$currentDir = dirname($_SERVER['SCRIPT_NAME']);
$currentDir = str_replace('\\', '/', $currentDir);
if ($currentDir === '/') $currentDir = '';
$base_url = $protocol . $domainName . $currentDir;

$page_title = "Ohati — Find. Compare. Book. Celebrate.";
$meta_desc = "Ohati is Ghana's trusted event vendor marketplace. Discover and secure top photographers, makeup artists, decorators, caterers, and DJs for your wedding, birthday, or corporate event with secure escrow payments.";
$meta_image = $base_url . "/img/logo black transparent small.png";
$meta_url = $base_url;

if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $ref_code = htmlspecialchars(trim($_GET['ref']));
    $_SESSION['pending_ref'] = $ref_code;
    
    // Custom Open Graph Link Preview for Referral Links
    $page_title = "Join Ohati — Earn Rewards on Event Vendor Bookings!";
    $meta_desc = "Sign up using referral code " . $ref_code . " to join Ohati! Discover, compare, and book top event photographers, caterers, DJs & decorators across Ghana.";
    $meta_image = $base_url . "/img/logo black transparent small.png";
    $meta_url = $base_url . "/index.php?ref=" . urlencode($ref_code);
}

if (strpos($_SERVER['REQUEST_URI'], 'detail.php') !== false && isset($_GET['id'])) {
    try {
        require_once __DIR__ . '/db.php';
        $vendor_id = intval($_GET['id']);
        $stmt = $pdo->prepare("SELECT v.*, u.avatar FROM vendors v JOIN users u ON v.user_id = u.id WHERE v.id = ?");
        $stmt->execute([$vendor_id]);
        $vendor = $stmt->fetch();
        if ($vendor) {
            $page_title = htmlspecialchars($vendor['name']) . " — Ohati Event Vendor";
            $meta_desc = "Check out " . ($vendor['verification_badge'] === 'gold' ? 'Gold Verified ' : '') . htmlspecialchars($vendor['name']) . " on Ohati. Rated " . ($vendor['rating'] ?: '5.0') . " ★. Based in " . htmlspecialchars($vendor['location']) . ".";
            $cover = $vendor['logo'] ?: $vendor['cover_photo'];
            if ($cover) {
                if (strpos($cover, 'http') === 0) {
                    $meta_image = $cover;
                } else {
                    $meta_image = $base_url . "/" . $cover;
                }
            } else {
                $meta_image = $base_url . "/img/logo black transparent small.png";
            }
            $meta_url = $base_url . "/detail.php?id=" . $vendor['id'];
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $page_title ?></title>
    <meta name="csrf-token" content="<?= $_SESSION['csrf'] ?>">
    <meta name="description" content="<?= $meta_desc ?>">
    <meta name="theme-color" content="#1B2B4B">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ohati">
    <link rel="icon" type="image/png" href="img/app_icon.png">
    <link rel="apple-touch-icon" href="img/app_icon.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $meta_url ?>">

    <!-- Android App Deep Indexing & Google Play Link Tags -->
    <link rel="alternate" href="android-app://com.ohati.app/https/ohati.com/" />
    <meta property="al:android:url" content="android-app://com.ohati.app/https/ohati.com/">
    <meta property="al:android:package" content="com.ohati.app">
    <meta property="al:android:app_name" content="Ohati">
    <meta property="al:web:url" content="<?= $meta_url ?>">
    <meta name="twitter:app:name:googleplay" content="Ohati">
    <meta name="twitter:app:id:googleplay" content="com.ohati.app">
    <meta name="twitter:app:url:googleplay" content="https://play.google.com/store/apps/details?id=com.ohati.app">

    <!-- SEO & Link Preview Meta Tags (Open Graph / Twitter) -->
    <meta property="og:title" content="<?= $page_title ?>">
    <meta property="og:description" content="<?= $meta_desc ?>">
    <meta property="og:image" content="<?= $meta_image ?>">
    <meta property="og:url" content="<?= $meta_url ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $page_title ?>">
    <meta name="twitter:description" content="<?= $meta_desc ?>">
    <meta name="twitter:image" content="<?= $meta_image ?>">

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/app_icon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&display=swap">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">

    <!-- Google Knowledge Graph & App Bio Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "SoftwareApplication",
          "name": "Ohati",
          "operatingSystem": "Android, iOS, Web",
          "applicationCategory": "BusinessApplication",
          "downloadUrl": "https://play.google.com/store/apps/details?id=com.ohati.app",
          "installUrl": "https://play.google.com/store/apps/details?id=com.ohati.app",
          "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.9",
            "reviewCount": "128"
          },
          "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "GHS"
          },
          "description": "Ghana's trusted event vendor marketplace. Discover and secure top photographers, makeup artists, decorators, caterers, and DJs for your wedding, birthday, or corporate event with secure escrow payments."
        },
        {
          "@type": "WebSite",
          "name": "Ohati",
          "url": "<?= $base_url ?>",
          "potentialAction": {
            "@type": "SearchAction",
            "target": "<?= $base_url ?>/search.php?q={search_term_string}",
            "query-input": "required name=search_term_string"
          }
        },
        {
          "@type": "Organization",
          "name": "Ohati",
          "url": "<?= $base_url ?>",
          "logo": "<?= $base_url ?>/img/app_icon.png",
          "sameAs": [
            "https://facebook.com/ohatighana",
            "https://instagram.com/ohatighana"
          ],
          "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "+233540477911",
            "contactType": "customer service",
            "areaServed": "GH",
            "availableLanguage": "English"
          }
        }
      ]
    }
    </script>
</head>
<body>

    <!-- App Container -->
    <div class="app-container" id="ohati-app" data-theme="light">

        <!-- ===== SPLASH / LOADING SCREEN ===== -->
        <div id="screen-loading" class="splash-screen">
            <div class="splash-inner">
                <img src="img/new_icon_ohati.png" alt="Ohati Logo" class="splash-logo-img" id="splash-logo">
                <div class="splash-loader-container">
                    <div class="splash-loader-bar"></div>
                    <div class="splash-loader-text">LOADING...</div>
                </div>
            </div>
        </div>

        <!-- ===== IN-APP PUSH NOTIFICATION (sliding banner) ===== -->
        <div id="in-app-push-notif" class="push-notif" onclick="dismissPushNotification()">
            <div class="push-notif-icon"><i class="fa-solid fa-bell"></i></div>
            <div class="push-notif-body">
                <div class="push-notif-title" id="notif-title">Notification</div>
                <div class="push-notif-desc" id="notif-desc">You have a new update.</div>
            </div>
            <button class="push-notif-close" onclick="event.stopPropagation(); dismissPushNotification()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- ===== ONBOARDING SCREEN ===== -->
        <div id="screen-onboarding" class="screen" style="display:none;"></div>

        <!-- ===== APP HEADER ===== -->
        <header class="app-header" id="app-header">
            <button class="header-menu-btn" id="header-menu-btn" aria-label="Open Menu">
                <img src="img/logo black transparent small.png" alt="Ohati" class="header-logo-img" id="header-logo-img">
                <span class="header-brand">OHATI</span>
            </button>
            <!-- Desktop Navigation Menu -->
            <div class="desktop-nav" id="desktop-nav">
                <a href="#" class="desktop-nav-item active" data-screen="home" onclick="navigateTo('home'); event.preventDefault();">
                    <i class="fa-solid fa-house"></i> Home
                </a>
                <a href="#" class="desktop-nav-item" data-screen="search" onclick="navigateTo('search'); event.preventDefault();">
                    <i class="fa-solid fa-compass"></i> Vendors
                </a>
                <a href="#" class="desktop-nav-item" data-screen="event" onclick="navigateTo('event'); event.preventDefault();">
                    <i class="fa-solid fa-calendar-check"></i> Planner
                </a>
                <a href="#" class="desktop-nav-item" data-screen="chat" onclick="navigateTo('chat'); event.preventDefault();" style="position:relative;">
                    <i class="fa-solid fa-comment-dots"></i> Messages
                    <span class="nav-badge" id="chat-nav-badge-desktop" style="display:none; position:absolute; top:-2px; right:-2px; background:var(--danger); color:#fff; border-radius:50%; font-size:0.6rem; min-width:14px; height:14px; align-items:center; justify-content:center; font-weight:700;"></span>
                </a>
                <a href="#" class="desktop-nav-item" data-screen="user-jobs" onclick="navigateTo('user-jobs'); event.preventDefault();">
                    <i class="fa-solid fa-briefcase"></i> Post Job
                </a>
                <a href="#" class="desktop-nav-item" data-screen="vendor-jobs" onclick="navigateTo('vendor-jobs'); event.preventDefault();">
                    <i class="fa-solid fa-list-check"></i> Find Jobs
                </a>
                <a href="#" class="desktop-nav-item" data-screen="bookings" onclick="navigateTo('bookings'); event.preventDefault();">
                    <i class="fa-solid fa-layer-group"></i> Bookings
                </a>
            </div>
            <div class="header-actions" id="header-actions">
                <button class="header-icon-btn theme-toggle-btn" id="theme-toggle-btn" aria-label="Toggle Theme">
                    <i class="fa-solid fa-moon" id="theme-icon"></i>
                </button>
                <button class="header-icon-btn notification-btn" id="header-notif-btn" aria-label="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notif-badge" id="notif-badge" style="display:none;">3</span>
                </button>
                <button class="header-avatar-btn" id="header-avatar-btn" aria-label="Profile">
                    <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" alt="User" id="header-avatar" class="header-avatar">
                </button>
            </div>
        </header>

        <!-- ===== MAIN VIEWPORT ===== -->
        <main class="app-viewport scrollable-y" id="app-viewport">

            <!-- Screen: Home -->
            <section id="screen-home" class="screen" style="display:none;"></section>

            <!-- Screen: Vendors / Search -->
            <section id="screen-search" class="screen" style="display:none;"></section>

            <!-- Screen: Vendor Detail -->
            <section id="screen-detail" class="screen screen-detail" style="display:none;"></section>

            <!-- Screen: Chat Inbox / Conversation -->
            <section id="screen-chat" class="screen" style="display:none;"></section>

            <!-- Screen: Event Planner & Bookings -->
            <section id="screen-bookings" class="screen" style="display:none;"></section>

            <!-- Screen: Favorites -->
            <section id="screen-favorites" class="screen" style="display:none;"></section>

            <!-- Screen: Event Dashboard -->
            <section id="screen-event" class="screen" style="display:none;"></section>

            <!-- Screen: Vendor Comparison -->
            <section id="screen-compare" class="screen" style="display:none;"></section>

            <!-- Screen: Notifications -->
            <section id="screen-notifications" class="screen" style="display:none;"></section>

            <!-- Screen: User Profile -->
            <section id="screen-profile" class="screen" style="display:none;"></section>

            <!-- Screen: Vendor Dashboard (for vendors) -->
            <section id="screen-vendor-dash" class="screen" style="display:none;"></section>

            <!-- Screen: Help Center -->
            <section id="screen-help" class="screen" style="display:none;"></section>

            <!-- New Vendor/Profile Screens -->
            <section id="screen-vendor-ads" class="screen" style="display:none;"></section>
            <section id="screen-vendor-auto-response" class="screen" style="display:none;"></section>
            <section id="screen-profile-edit" class="screen" style="display:none;"></section>
            <section id="screen-report-issue" class="screen" style="display:none;"></section>
            <section id="screen-user-jobs" class="screen" style="display:none;"></section>
            <section id="screen-vendor-jobs" class="screen" style="display:none;"></section>
        </main>

        <!-- ===== BOTTOM NAVIGATION ===== -->
        <nav class="bottom-nav" id="bottom-nav">
            <a href="#" class="nav-item active" data-screen="home" id="nav-btn-home">
                <div class="nav-icon"><i class="fa-solid fa-house"></i></div>
                <span>Home</span>
            </a>
            <a href="#" class="nav-item" data-screen="search" id="nav-btn-search">
                <div class="nav-icon"><i class="fa-solid fa-compass"></i></div>
                <span>Vendors</span>
            </a>
            <a href="#" class="nav-item nav-center-btn" data-screen="event" id="nav-btn-event">
                <div class="nav-center-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <span>Plan</span>
            </a>
            <a href="#" class="nav-item" data-screen="chat" id="nav-btn-chat">
                <div class="nav-icon"><i class="fa-solid fa-comment-dots"></i>
                    <span class="nav-badge" id="chat-nav-badge" style="display:none;">1</span>
                </div>
                <span>Messages</span>
            </a>
            <a href="#" class="nav-item" data-screen="bookings" id="nav-btn-bookings">
                <div class="nav-icon"><i class="fa-solid fa-layer-group"></i></div>
                <span>Bookings</span>
            </a>
        </nav>

        <!-- ===== OVERLAYS & DRAWERS ===== -->

        <!-- Sidebar Drawer -->
        <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar(false)">
            <aside class="sidebar-panel" id="sidebar-panel" onclick="event.stopPropagation()">
                <div class="sidebar-header">
                    <div class="sidebar-user-info">
                        <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" alt="Profile" class="sidebar-avatar" id="sidebar-avatar">
                        <div>
                            <div class="sidebar-name" id="sidebar-name">Guest</div>
                            <div class="sidebar-email" id="sidebar-email">Not signed in</div>
                        </div>
                    </div>
                    <button class="sidebar-close-btn" onclick="toggleSidebar(false)"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <nav class="sidebar-nav" id="sidebar-nav-container">
                    <a class="sidebar-link" onclick="navigateTo('profile'); toggleSidebar(false)">
                        <i class="fa-solid fa-user-gear"></i><span>My Profile</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('favorites'); toggleSidebar(false)">
                        <i class="fa-solid fa-heart"></i><span>Saved Vendors</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('bookings'); toggleSidebar(false)">
                        <i class="fa-solid fa-calendar-check"></i><span>My Bookings</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('user-jobs'); toggleSidebar(false)">
                        <i class="fa-solid fa-briefcase"></i><span>My Event Jobs</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('vendor-jobs'); toggleSidebar(false)">
                        <i class="fa-solid fa-list-check"></i><span>Find Event Jobs</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('notifications'); toggleSidebar(false)">
                        <i class="fa-solid fa-bell"></i><span>Notifications</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('compare'); toggleSidebar(false)">
                        <i class="fa-solid fa-scale-balanced"></i><span>Compare Vendors</span>
                    </a>
                    <a class="sidebar-link sidebar-premium" onclick="openPremiumModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-crown"></i><span>Become a Vendor</span>
                        <span class="sidebar-badge-new">NEW</span>
                    </a>
                    <div class="sidebar-divider"></div>
                    <a class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-bullhorn"></i><span>Refer & Earn</span>
                        <span class="sidebar-badge-new" style="background:var(--accent);">PROMO</span>
                    </a>
                    <a class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-tags"></i><span>Discounts & Offers</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('help'); toggleSidebar(false)">
                        <i class="fa-solid fa-circle-question"></i><span>Help Center</span>
                    </a>
                    <a class="sidebar-link" onclick="openSettingsModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-gear"></i><span>Settings</span>
                    </a>
                    <div class="sidebar-divider"></div>
                    <a class="sidebar-link sidebar-signin-link" id="sidebar-auth-link" onclick="openLoginModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-right-to-bracket"></i><span id="sidebar-auth-text">Sign In</span>
                    </a>
                </nav>

                <div class="sidebar-footer">
                    <img src="img/logo black transparent small.png" alt="Ohati" class="sidebar-footer-logo" id="sidebar-footer-logo">
                    <div class="sidebar-footer-text">
                        <span style="font-weight:700;color:var(--primary);font-size:0.65rem;">Ohati v1.0.0</span>
                        <span>Find. Compare. Book. Celebrate.</span>
                        <span style="font-size:0.65rem; color:var(--gray-500); margin-top:3px; display:block;">App Designed by <a href="https://wa.me/2348136731796" target="_blank" style="color:var(--accent, #F2A735); text-decoration:none; font-weight:bold;">C Eye Q Digital</a></span>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Filter Drawer -->
        <div class="filter-drawer-overlay" id="filter-drawer-overlay" onclick="closeFilterDrawer()"></div>
        <div class="filter-drawer" id="filter-drawer"></div>

        <!-- Modal Overlay (generic) -->
        <div class="modal-overlay" id="modal-overlay" onclick="closeModal()">
            <div class="modal-sheet" id="modal-sheet" onclick="event.stopPropagation()">
                <div class="modal-handle"></div>
                <div class="modal-content" id="modal-content"></div>
            </div>
        </div>

        <!-- Lightbox -->
        <div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
            <button class="lightbox-close" onclick="closeLightbox()"><i class="fa-solid fa-xmark"></i></button>
            <button class="lightbox-prev" onclick="event.stopPropagation(); lightboxNav(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <img class="lightbox-img" id="lightbox-img" alt="">
            <button class="lightbox-next" onclick="event.stopPropagation(); lightboxNav(1)"><i class="fa-solid fa-chevron-right"></i></button>
            <div class="lightbox-counter" id="lightbox-counter"></div>
        </div>

        <!-- Welcome Popup Modal -->
        <div class="welcome-popup-overlay" id="welcome-popup-overlay" onclick="closeWelcomePopup(event)">
            <div class="welcome-popup-card" onclick="event.stopPropagation()">
                <button class="welcome-popup-close" onclick="closeWelcomePopup(event)" aria-label="Close popup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="welcome-popup-illustration"></div>
                <div class="welcome-popup-body">
                    <div class="welcome-popup-logo">
                        <img src="img/logo black transparent small.png" alt="Ohati Logo" class="welcome-logo-img">
                    </div>
                    <h2 class="welcome-popup-title">Welcome to Ohati 🎉</h2>
                    <p class="welcome-popup-tagline">Finding trusted event vendors has never been easier.</p>
                    <p class="welcome-popup-desc">
                        Whether you're planning a wedding, birthday, engagement, corporate event, or any special occasion, Ohati helps you discover, compare, chat with, negotiate, and book verified event professionals—all in one place.
                    </p>
                    <div class="welcome-popup-motto">Find. Compare. Book. Celebrate.</div>
                    
                    <div class="welcome-app-badges">
                        <button class="app-badge-btn" onclick="openAppDownloadUrl('android')" style="cursor:pointer;">
                            <i class="fa-brands fa-google-play"></i>
                            <div class="app-badge-text">
                                <span class="app-badge-sub">GET IT ON</span>
                                <span class="app-badge-main">Google Play</span>
                            </div>
                            <span class="badge badge-success" style="font-size:0.65rem; padding:2px 8px; font-weight:700;">LIVE</span>
                        </button>
                        <button class="app-badge-btn" onclick="openAppDownloadUrl('ios')" style="cursor:pointer;">
                            <i class="fa-brands fa-apple"></i>
                            <div class="app-badge-text">
                                <span class="app-badge-sub">Download on the</span>
                                <span class="app-badge-main">App Store</span>
                            </div>
                            <span class="badge badge-success" style="font-size:0.65rem; padding:2px 8px; font-weight:700;">LIVE</span>
                        </button>
                    </div>

                    <button class="btn btn-primary btn-full welcome-continue-btn" onclick="closeWelcomePopup(event)">Continue to Website</button>

                    <label class="welcome-remember-label">
                        <input type="checkbox" id="welcome-dont-show-again">
                        <span>Don't show this again</span>
                    </label>
                </div>
                <div class="welcome-popup-footer" style="display:flex; flex-direction:column; gap:6px; align-items:center; text-align:center; padding:16px 20px; background:var(--gray-100, #F8FAFC); border-top:1px solid var(--gray-200, #E2E8F0); border-radius:0 0 20px 20px;">
                    <div style="font-size:0.85rem; color:var(--gray-700, #334155);">Need help? Chat with <a href="https://wa.me/233209001100" target="_blank" style="color:var(--primary, #1B2B4B); text-decoration:none; font-weight:700;">Ohati Support</a></div>
                </div>
            </div>
        </div>

    </div><!-- /app-container -->

    <?php $v_ts = filemtime(__DIR__ . '/js/screens.js'); ?>
    <script src="js/utils.js?v=<?= $v_ts ?>"></script>
    <script src="js/helpers.js?v=<?= $v_ts ?>"></script>
    <script src="js/api.js?v=<?= $v_ts ?>"></script>
    <script src="js/action_lock.js?v=<?= $v_ts ?>"></script>
    <script src="js/state.js?v=<?= $v_ts ?>"></script>
    <script src="js/modals.js?v=<?= $v_ts ?>"></script>
    <script src="js/auth.js?v=<?= $v_ts ?>"></script>
    <script src="js/booking.js?v=<?= $v_ts ?>"></script>
    <script src="js/vendor.js?v=<?= $v_ts ?>"></script>
    <script src="js/chat.js?v=<?= $v_ts ?>"></script>
    <script src="js/search.js?v=<?= $v_ts ?>"></script>
    <script src="js/review.js?v=<?= $v_ts ?>"></script>
    <script src="js/notification.js?v=<?= $v_ts ?>"></script>
    <script src="js/payment.js?v=<?= $v_ts ?>"></script>
    <script src="js/screens.js?v=<?= $v_ts ?>"></script>
    <script src="js/calling.js?v=<?= $v_ts ?>"></script>
    <script src="js/jobs.js?v=<?= $v_ts ?>"></script>
    <script src="js/app.js?v=<?= $v_ts ?>"></script>

</body>
</html>
