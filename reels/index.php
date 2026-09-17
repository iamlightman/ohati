<?php
// index.php — Ohati Event Marketplace Web App
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ── SERVER-SIDE SEO & DYNAMIC METADATA RESOLUTION ─────────────────────
if (file_exists(__DIR__ . '/../db.php')) {
    @include_once __DIR__ . '/../db.php';
}

$seo_title = "Ohati — Find. Compare. Book. Celebrate.";
$seo_desc = "Ohati is Ghana's trusted event vendor marketplace. Discover and secure top photographers, makeup artists, decorators, caterers, and DJs for your wedding, birthday, or corporate event with secure direct payments.";
$seo_image = "https://ohati.com/img/app_icon.png";
$seo_url = "https://ohati.com";
$seo_type = "website";
$seo_json_ld = null;

$requested_vendor_id = intval($_GET['id'] ?? $_GET['vendor_id'] ?? $_GET['vid'] ?? 0);
$requested_blog_id = intval($_GET['blog_id'] ?? $_GET['post_id'] ?? 0);

if ($requested_vendor_id > 0) {
    try {
        $v_stmt = $pdo->prepare("SELECT id, name, category, city, location, description, rating, reviews_count, logo, cover_photo, phone, email, website FROM vendors WHERE id = ?");
        $v_stmt->execute([$requested_vendor_id]);
        $v_data = $v_stmt->fetch(PDO::FETCH_ASSOC);
        if ($v_data) {
            $v_name = htmlspecialchars($v_data['name']);
            $v_cat = htmlspecialchars($v_data['category']);
            $v_city = htmlspecialchars($v_data['city'] ?: ($v_data['location'] ?: 'Ghana'));
            $v_desc = htmlspecialchars(substr(strip_tags($v_data['description'] ?: "Book $v_name for $v_cat in $v_city, Ghana on Ohati."), 0, 160));
            
            $seo_title = "$v_name — $v_cat in $v_city, Ghana | Ohati";
            $seo_desc = $v_desc;
            $seo_image = $v_data['cover_photo'] ?: ($v_data['logo'] ?: "https://ohati.com/img/app_icon.png");
            if (strpos($seo_image, 'http') !== 0) $seo_image = "https://ohati.com/" . ltrim($seo_image, '/');
            $seo_url = "https://ohati.com/detail.php?id=" . $v_data['id'];
            $seo_type = "profile";

            $seo_json_ld = [
                "@context" => "https://schema.org",
                "@type" => "LocalBusiness",
                "name" => $v_data['name'],
                "category" => $v_data['category'],
                "image" => $seo_image,
                "telephone" => $v_data['phone'] ?: '',
                "email" => $v_data['email'] ?: '',
                "url" => $seo_url,
                "address" => [
                    "@type" => "PostalAddress",
                    "addressLocality" => $v_data['city'] ?: 'Accra',
                    "addressCountry" => "GH"
                ],
                "aggregateRating" => [
                    "@type" => "AggregateRating",
                    "ratingValue" => number_format(floatval($v_data['rating'] ?: 5.0), 1),
                    "reviewCount" => intval($v_data['reviews_count'] ?: 1)
                ]
            ];
        }
    } catch (Throwable $eSeoV) {}
} elseif ($requested_blog_id > 0) {
    try {
        $b_stmt = $pdo->prepare("SELECT id, title, excerpt, content, cover_image, category, author_name, created_at FROM blog_posts WHERE id = ?");
        $b_stmt->execute([$requested_blog_id]);
        $b_data = $b_stmt->fetch(PDO::FETCH_ASSOC);
        if ($b_data) {
            $b_title = htmlspecialchars($b_data['title']);
            $b_desc = htmlspecialchars(substr(strip_tags($b_data['excerpt'] ?: $b_data['content']), 0, 160));
            
            $seo_title = "$b_title | Ohati Event Blog";
            $seo_desc = $b_desc;
            $seo_image = $b_data['cover_image'] ?: "https://ohati.com/img/app_icon.png";
            if (strpos($seo_image, 'http') !== 0) $seo_image = "https://ohati.com/" . ltrim($seo_image, '/');
            $seo_url = "https://ohati.com/blog.php?id=" . $b_data['id'];
            $seo_type = "article";

            $seo_json_ld = [
                "@context" => "https://schema.org",
                "@type" => "BlogPosting",
                "headline" => $b_data['title'],
                "description" => $b_desc,
                "image" => $seo_image,
                "author" => [
                    "@type" => "Person",
                    "name" => $b_data['author_name'] ?: 'Ohati Team'
                ],
                "publisher" => [
                    "@type" => "Organization",
                    "name" => "Ohati",
                    "logo" => [
                        "@type" => "ImageObject",
                        "url" => "https://ohati.com/img/app_icon.png"
                    ]
                ],
                "datePublished" => date('c', strtotime($b_data['created_at'] ?: 'now'))
            ];
        }
    } catch (Throwable $eSeoB) {}
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $seo_title ?></title>
    <meta name="csrf-token" content="">
    <meta name="description" content="<?= $seo_desc ?>">
    <meta name="theme-color" content="#1B2B4B">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ohati">
    <link rel="icon" type="image/png" href="img/app_icon.png">
    <link rel="apple-touch-icon" href="img/app_icon.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $seo_url ?>">

    <!-- Android App Deep Indexing & Google Play Link Tags -->
    <link rel="alternate" href="android-app://com.ohati.app/https/ohati.com/" />
    <meta property="al:android:url" content="android-app://com.ohati.app/https/ohati.com/">
    <meta property="al:android:package" content="com.ohati.app">
    <meta property="al:android:app_name" content="Ohati">
    <meta property="al:web:url" content="<?= $seo_url ?>">
    <meta name="twitter:app:name:googleplay" content="Ohati">
    <meta name="twitter:app:id:googleplay" content="com.ohati.app">
    <meta name="twitter:app:url:googleplay" content="https://play.google.com/store/apps/details?id=com.ohati.app">

    <!-- SEO & Link Preview Meta Tags (Open Graph / Twitter) -->
    <meta property="og:title" content="<?= $seo_title ?>">
    <meta property="og:description" content="<?= $seo_desc ?>">
    <meta property="og:image" content="<?= $seo_image ?>">
    <meta property="og:url" content="<?= $seo_url ?>">
    <meta property="og:type" content="<?= $seo_type ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $seo_title ?>">
    <meta name="twitter:description" content="<?= $seo_desc ?>">
    <meta name="twitter:image" content="<?= $seo_image ?>">
    <?php if (!empty($seo_json_ld)): ?>
    <script type="application/ld+json">
    <?= json_encode($seo_json_ld, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <?php endif; ?>

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/app_icon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&display=swap" >

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" >

    <!-- Main Stylesheet & Reels Stylesheet -->
    <link rel="stylesheet" href="../style.css?v=1.1.9">
    <link rel="stylesheet" href="css/reels.css">

    <!-- Suppress Login / Auth Lock Screen for Reels Demo -->
    <script>
        window.showMandatoryAuthLockScreen = function() {};
        if (!localStorage.getItem('ohati_user_session')) {
            try {
                localStorage.setItem('ohati_user_session', JSON.stringify({
                    id: 9999,
                    name: 'Demo Guest User',
                    email: 'demo@ohati.com',
                    account_type: 'customer'
                }));
            } catch(e) {}
        }
    </script>

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
          "url": "https://ohati.com",
          "potentialAction": {
            "@type": "SearchAction",
            "target": "https://ohati.com/search.php?q={search_term_string}",
            "query-input": "required name=search_term_string"
          }
        },
        {
          "@type": "Organization",
          "name": "Ohati",
          "url": "https://ohati.com",
          "logo": "https://ohati.com/img/app_icon.png",
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
                <a href="index.php" class="desktop-nav-item" data-screen="home" onclick="navigateTo('home', {}, { force: true }); event.preventDefault();">
                    <i class="fa-solid fa-house"></i> Home
                </a>
                <a href="search.php" class="desktop-nav-item" data-screen="search" onclick="navigateTo('search', {}, { force: true }); event.preventDefault();">
                    <i class="fa-solid fa-compass"></i> Vendors
                </a>
                <a href="#" class="desktop-nav-item active" data-screen="reels" id="desktop-nav-reels" onclick="navigateTo('reels', {}, { force: true }); event.preventDefault();">
                    <i class="fa-solid fa-clapperboard"></i> Reels
                </a>
                <a href="chat.php" class="desktop-nav-item" data-screen="chat" onclick="state.activeChatVendorId = null; navigateTo('chat', {}, { force: true }); event.preventDefault();" style="position:relative;">
                    <i class="fa-solid fa-comment-dots"></i> Messages
                    <span class="nav-badge" id="chat-nav-badge-desktop" style="display:none; position:absolute; top:-2px; right:-2px; background:var(--danger); color:#fff; border-radius:50%; font-size:0.6rem; min-width:14px; height:14px; align-items:center; justify-content:center; font-weight:700;"></span>
                </a>
                <a href="jobs.php" class="desktop-nav-item" data-screen="user-jobs" id="desktop-nav-post-job" onclick="navigateTo('user-jobs', {}, { force: true }); event.preventDefault();">
                    <i class="fa-solid fa-briefcase"></i> Post Job
                </a>
                <a href="jobs.php" class="desktop-nav-item" data-screen="vendor-jobs" id="desktop-nav-find-jobs" onclick="navigateTo('vendor-jobs', {}, { force: true }); event.preventDefault();">
                    <i class="fa-solid fa-list-check"></i> Find Jobs
                </a>
                <a href="bookings.php" class="desktop-nav-item" data-screen="bookings" onclick="navigateTo('bookings', {}, { force: true }); event.preventDefault();">
                    <i class="fa-solid fa-layer-group"></i> Bookings
                </a>
            </div>
            <div class="header-actions" id="header-actions">
                <button class="header-icon-btn notification-btn" id="header-notif-btn" aria-label="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notif-badge" id="notif-badge" style="display:none;">3</span>
                </button>
                <button class="header-avatar-btn" id="header-avatar-btn" aria-label="Profile">
                    <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='avatarGrad' x1='0%' y1='0%' x2='100%' y2='100%'><stop offset='0%' stop-color='%231B2B4B'/><stop offset='100%' stop-color='%230F172A'/></linearGradient></defs><circle cx='50' cy='50' r='50' fill='url(%23avatarGrad)'/><circle cx='50' cy='38' r='18' fill='%23F2A735'/><path d='M 20 84 C 20 64, 32 58, 50 58 C 68 58, 80 64, 80 84 Z' fill='%23F2A735'/></svg>" alt="User" id="header-avatar" class="header-avatar" onerror="window.handleImageError(this, 'avatar')">
                </button>
                <a href="javascript:void(0)" class="desktop-nav-item sidebar-toggle-btn" id="header-sidebar-toggle-btn" aria-label="Toggle Sidebar" onclick="toggleSidebar(); event.preventDefault();" title="Menu Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </a>
            </div>
        </header>

        <!-- ===== MAIN VIEWPORT ===== -->
        <main class="app-viewport scrollable-y" id="app-viewport">

            <!-- Screen: Reels (Default for Demo) -->
            <section id="screen-reels" class="screen" style="display:block;"></section>

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
            <!-- Screen: Customer Dashboard (for customers/clients) -->
            <section id="screen-user-dash" class="screen" style="display:none;"></section>
            <section id="screen-didit-kyc" class="screen" style="display:none;"></section>

            <!-- Screen: Help Center -->
            <section id="screen-help" class="screen" style="display:none;"></section>

            <!-- New Vendor/Profile Screens -->
            <section id="screen-vendor-ads" class="screen" style="display:none;"></section>
            <section id="screen-vendor-auto-response" class="screen" style="display:none;"></section>
            <section id="screen-profile-edit" class="screen" style="display:none;"></section>
            <section id="screen-report-issue" class="screen" style="display:none;"></section>
            <section id="screen-about" class="screen" style="display:none;"></section>
            <section id="screen-user-jobs" class="screen" style="display:none;"></section>
            <section id="screen-vendor-jobs" class="screen" style="display:none;"></section>
            <section id="screen-blog" class="screen" style="display:none;"></section>
            <section id="screen-blog-detail" class="screen" style="display:none;"></section>
            <section id="screen-privacy" class="screen" style="display:none;"></section>
            <section id="screen-terms" class="screen" style="display:none;"></section>
        </main>

        <!-- ===== BOTTOM NAVIGATION ===== -->
        <nav class="bottom-nav" id="bottom-nav">
            <a href="#" class="nav-item" data-screen="home" id="nav-btn-home" onclick="navigateTo('home'); event.preventDefault();">
                <div class="nav-icon"><i class="fa-solid fa-house"></i></div>
                <span>Home</span>
            </a>
            <a href="#" class="nav-item" data-screen="search" id="nav-btn-search" onclick="navigateTo('search'); event.preventDefault();">
                <div class="nav-icon"><i class="fa-solid fa-compass"></i></div>
                <span>Vendors</span>
            </a>
            <a href="#" class="nav-item nav-center-btn active" data-screen="reels" id="nav-btn-reels" onclick="navigateTo('reels'); event.preventDefault();">
                <div class="nav-center-icon"><i class="fa-solid fa-clapperboard"></i></div>
                <span>Reels</span>
            </a>
            <a href="#" class="nav-item" data-screen="chat" id="nav-btn-chat" onclick="navigateTo('chat'); event.preventDefault();">
                <div class="nav-icon"><i class="fa-solid fa-comment-dots"></i>
                    <span class="nav-badge" id="chat-nav-badge" style="display:none;">1</span>
                </div>
                <span>Messages</span>
            </a>
            <a href="javascript:void(0)" class="nav-item" id="nav-btn-menu" onclick="toggleSidebar(); event.preventDefault();">
                <div class="nav-icon"><i class="fa-solid fa-bars"></i></div>
                <span>Menu</span>
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
                    <a class="sidebar-link" onclick="navigateTo('event'); toggleSidebar(false)">
                        <i class="fa-solid fa-calendar-check" style="color:var(--accent);"></i><span>Planner / Event Plan</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('profile'); toggleSidebar(false)">
                        <i class="fa-solid fa-user-gear"></i><span>My Profile</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('favorites'); toggleSidebar(false)">
                        <i class="fa-solid fa-heart"></i><span>Saved Vendors</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('blog'); toggleSidebar(false)">
                        <i class="fa-solid fa-newspaper"></i><span>Blog & Guides</span>
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
                    <a class="sidebar-link" onclick="openReferAndEarnModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-bullhorn"></i><span>Refer & Earn</span>
                        <span class="sidebar-badge-new" style="background:var(--accent);">PROMO</span>
                    </a>
                    <a class="sidebar-link" onclick="openDiscountsAndOffersModal(); toggleSidebar(false)">
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

        <!-- Welcome Popup Modal Removed for Reels Demo -->

    </div><!-- /app-container -->

    <!-- Global Modal Root Container (Unified Modal Overlays) -->
    <div id="ohati-global-modal-root"></div>

    
    <script src="../js/utils.js?v=3.9.1"></script>
    <script src="../js/helpers.js?v=3.9.1"></script>
    <script src="../js/api.js?v=3.9.1"></script>
    <script src="../js/action_lock.js?v=3.9.1"></script>
    <script src="../js/state.js?v=3.9.1"></script>
    <script src="../js/modals.js?v=3.9.1"></script>
    <script src="../js/auth.js?v=3.9.1"></script>
    <script src="../js/booking.js?v=3.9.1"></script>
    <script src="../js/vendor.js?v=3.9.1"></script>
    <script src="../js/chat.js?v=3.9.1"></script>
    <script src="../js/search.js?v=3.9.1"></script>
    <script src="../js/review.js?v=3.9.1"></script>
    <script src="../js/notification.js?v=3.9.1"></script>
    <script src="../js/payment.js?v=3.9.1"></script>
    <script src="../js/screens.js?v=3.9.1"></script>
    <script src="../js/calling.js?v=3.9.1"></script>
    <script src="../js/jobs.js?v=3.9.1"></script>
    <script src="../js/blog.js?v=3.9.1"></script>
    <script src="../js/app.js?v=3.9.1"></script>

    <!-- Isolated Demonstration Scripts for Reels -->
    <script src="js/reels_data.js"></script>
    <script src="js/reels_app.js"></script>

</body>
</html>

</body>
</html>
