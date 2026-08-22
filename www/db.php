<?php
// db.php - Ohati Database - MySQL/SQLite persistent connection & schema
date_default_timezone_set('Africa/Accra');

// 1. Determine environment & database credentials — Prioritize live web config if present
if (file_exists(__DIR__ . '/ohati_config.php')) {
    require_once __DIR__ . '/ohati_config.php';
}

function create_pdo_conn($dbname, $dbuser, $dbpass, $host = 'localhost') {
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $conn->exec("SET time_zone = '+00:00'");
        return $conn;
    } catch (PDOException $e) {
        try {
            $conn = new PDO("mysql:host=$host", $dbuser, $dbpass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->exec("USE `$dbname`");
            return $conn;
        } catch (PDOException $e2) {
            return null;
        }
    }
}

$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_pass = defined('DB_PASS') ? DB_PASS : '';

// 1. Primary Database Connection: ohaticom_1 ($pdo / $pdo_1)
$db_user_1 = defined('DB_USER_1') ? DB_USER_1 : (defined('DB_USER') ? DB_USER : 'root');
$db_name_1 = defined('DB_NAME_1') ? DB_NAME_1 : (defined('DB_NAME') ? DB_NAME : 'ohati');

$pdo = create_pdo_conn($db_name_1, $db_user_1, $db_pass, $host);
$db_type = 'mysql';

if (!$pdo) {
    // Local Development Fallback: SQLite
    try {
        $db_path = __DIR__ . '/ohati.db';
        $pdo = new PDO("sqlite:$db_path", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30
        ]);
        $pdo->exec("PRAGMA journal_mode=WAL");
        $pdo->exec("PRAGMA busy_timeout=30000");
        $pdo->exec("PRAGMA synchronous=NORMAL");
        $db_type = 'sqlite';
    } catch (PDOException $e) {
        die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
    }
}

$pdo_1 = $pdo;

// 2. Database 2: Event Jobs Marketplace ($pdo_jobs / $pdo_2) -> ohaticom_2
$db_user_2 = defined('DB_USER_2') ? DB_USER_2 : $db_user_1;
$db_name_2 = defined('DB_NAME_2') ? DB_NAME_2 : 'ohaticom_2';
$pdo_jobs = ($db_type === 'mysql') ? (create_pdo_conn($db_name_2, $db_user_2, $db_pass, $host) ?: $pdo) : $pdo;
$pdo_2 = $pdo_jobs;

// 3. Database 3: Communication & Messaging ($pdo_comms / $pdo_3) -> ohaticom_3
$db_user_3 = defined('DB_USER_3') ? DB_USER_3 : $db_user_1;
$db_name_3 = defined('DB_NAME_3') ? DB_NAME_3 : 'ohaticom_3';
$pdo_comms = ($db_type === 'mysql') ? (create_pdo_conn($db_name_3, $db_user_3, $db_pass, $host) ?: $pdo) : $pdo;
$pdo_3 = $pdo_comms;

// 4. Database 4: Payments & Escrow ($pdo_payments / $pdo_4) -> ohaticom_4
$db_user_4 = defined('DB_USER_4') ? DB_USER_4 : $db_user_1;
$db_name_4 = defined('DB_NAME_4') ? DB_NAME_4 : 'ohaticom_4';
$pdo_payments = ($db_type === 'mysql') ? (create_pdo_conn($db_name_4, $db_user_4, $db_pass, $host) ?: $pdo) : $pdo;
$pdo_4 = $pdo_payments;

// 5. Database 5: Analytics & Logs ($pdo_logs / $pdo_5) -> ohaticom_5
$db_user_5 = defined('DB_USER_5') ? DB_USER_5 : $db_user_1;
$db_name_5 = defined('DB_NAME_5') ? DB_NAME_5 : 'ohaticom_5';
$pdo_logs = ($db_type === 'mysql') ? (create_pdo_conn($db_name_5, $db_user_5, $db_pass, $host) ?: $pdo) : $pdo;
$pdo_5 = $pdo_logs;

$AI  = ($db_type === 'mysql') ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
$NOW = ($db_type === 'mysql') ? "TIMESTAMP DEFAULT CURRENT_TIMESTAMP" : "TIMESTAMP DEFAULT CURRENT_TIMESTAMP";

// ── USERS ──────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id $AI,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(200),
    phone VARCHAR(50),
    username VARCHAR(100),
    password_hash VARCHAR(255),
    role VARCHAR(20) DEFAULT 'customer',
    avatar VARCHAR(500) DEFAULT '',
    gender VARCHAR(20) DEFAULT '',
    dob VARCHAR(20) DEFAULT '',
    country VARCHAR(100) DEFAULT '',
    state VARCHAR(100) DEFAULT '',
    city VARCHAR(100) DEFAULT '',
    language VARCHAR(50) DEFAULT 'English',
    currency VARCHAR(10) DEFAULT 'GHS',
    kyc_status VARCHAR(30) DEFAULT 'not_started',
    kyc_id_type VARCHAR(50) DEFAULT '',
    kyc_id_front VARCHAR(500) DEFAULT '',
    kyc_id_back VARCHAR(500) DEFAULT '',
    kyc_selfie VARCHAR(500) DEFAULT '',
    kyc_submitted_at VARCHAR(50) DEFAULT '',
    kyc_reviewed_at VARCHAR(50) DEFAULT '',
    kyc_notes TEXT,
    two_fa_enabled INT DEFAULT 0,
    two_fa_secret VARCHAR(100) DEFAULT '',
    is_active INT DEFAULT 1,
    email_verified INT DEFAULT 0,
    phone_verified INT DEFAULT 0,
    last_login VARCHAR(50) DEFAULT '',
    login_count INT DEFAULT 0,
    device_tokens TEXT,
    last_active $NOW,
    created_at $NOW
)");

// Enforce unique constraints at database level to prevent duplicate registrations
try {
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_unique ON users(email) WHERE email IS NOT NULL AND email != ''");
} catch (Exception $e) {}
try {
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_phone_unique ON users(phone) WHERE phone IS NOT NULL AND phone != ''");
} catch (Exception $e) {}

// Dynamic column updates for users & activity tracking
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_active VARCHAR(50) DEFAULT ''");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE vendors ADD COLUMN last_active VARCHAR(50) DEFAULT ''");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN referral_code VARCHAR(50) DEFAULT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN referred_by INT DEFAULT 0");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN referral_balance FLOAT DEFAULT 0");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(30) DEFAULT 'active'");
} catch (Exception $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS auth_tokens (
    id $AI,
    user_id INT NOT NULL,
    token_hash VARCHAR(128) NOT NULL UNIQUE,
    device_name VARCHAR(255) DEFAULT '',
    device_id VARCHAR(255) DEFAULT '',
    ip_address VARCHAR(100) DEFAULT '',
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_device_tokens (
    id $AI,
    user_id INT NOT NULL,
    fcm_token VARCHAR(500) DEFAULT '',
    apns_voip_token VARCHAR(500) DEFAULT '',
    platform VARCHAR(50) DEFAULT 'mobile',
    updated_at $NOW,
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS referrals (
    id $AI,
    referrer_id INT NOT NULL,
    referred_id INT NOT NULL,
    referral_code VARCHAR(50) NOT NULL,
    reward_amount FLOAT DEFAULT 10.0,
    status VARCHAR(30) DEFAULT 'completed',
    payout_status VARCHAR(30) DEFAULT 'pending',
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS discounts (
    id $AI,
    code VARCHAR(50) UNIQUE NOT NULL,
    discount_type VARCHAR(20) DEFAULT 'percentage',
    discount_value FLOAT NOT NULL,
    min_booking_amount FLOAT DEFAULT 0,
    max_discount_amount FLOAT DEFAULT 0,
    event_type VARCHAR(50) DEFAULT 'All',
    usage_limit INT DEFAULT 100,
    used_count INT DEFAULT 0,
    valid_from VARCHAR(50) DEFAULT '',
    valid_until VARCHAR(50) DEFAULT '',
    is_active INT DEFAULT 1,
    created_at $NOW
)");

// ── BLOG TABLES ─────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
    id $AI,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    subheadline VARCHAR(500) DEFAULT '',
    category VARCHAR(100) DEFAULT 'General',
    tags VARCHAR(255) DEFAULT '',
    cover_image VARCHAR(500) DEFAULT '',
    content TEXT,
    video_url VARCHAR(500) DEFAULT '',
    author_name VARCHAR(100) DEFAULT 'Ohati Editorial',
    author_avatar VARCHAR(500) DEFAULT '',
    status VARCHAR(20) DEFAULT 'published',
    scheduled_at VARCHAR(50) DEFAULT '',
    published_at VARCHAR(50) DEFAULT '',
    views_count INT DEFAULT 0,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    reading_time INT DEFAULT 4,
    featured INT DEFAULT 0,
    created_at $NOW,
    updated_at $NOW
)");

try {
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_blog_posts_slug ON blog_posts(slug)");
} catch (Exception $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS blog_comments (
    id $AI,
    post_id INT NOT NULL,
    parent_id INT DEFAULT 0,
    user_id INT DEFAULT 0,
    author_name VARCHAR(150) NOT NULL,
    author_email VARCHAR(150) DEFAULT '',
    author_avatar VARCHAR(500) DEFAULT '',
    comment TEXT NOT NULL,
    likes_count INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'approved',
    created_at $NOW
)");

try {
    $pdo->exec("ALTER TABLE blog_comments ADD COLUMN parent_id INT DEFAULT 0");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE blog_comments ADD COLUMN likes_count INT DEFAULT 0");
} catch (Exception $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS blog_likes (
    id $AI,
    post_id INT NOT NULL,
    user_id INT DEFAULT 0,
    ip_address VARCHAR(100) DEFAULT '',
    session_id VARCHAR(100) DEFAULT '',
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS blog_comment_likes (
    id $AI,
    comment_id INT NOT NULL,
    user_id INT DEFAULT 0,
    ip_address VARCHAR(100) DEFAULT '',
    session_id VARCHAR(100) DEFAULT '',
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS blog_comment_reports (
    id $AI,
    comment_id INT NOT NULL,
    reporter_user_id INT DEFAULT 0,
    reporter_ip VARCHAR(100) DEFAULT '',
    reason VARCHAR(255) DEFAULT 'Inappropriate content',
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS blog_user_blocks (
    id $AI,
    blocker_user_id INT DEFAULT 0,
    blocker_ip VARCHAR(100) DEFAULT '',
    blocked_author_name VARCHAR(150) NOT NULL,
    blocked_user_id INT DEFAULT 0,
    created_at $NOW
)");

// Seed initial 5 sample wedding blog posts if table is empty
try {
    $blog_cnt = $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
    if ($blog_cnt == 0) {
        $now_str = date('Y-m-d H:i:s');
        $seed_posts = [
            [
                'title' => 'The Ultimate Wedding Planning Timeline for Ghanaian Couples: From Engagement to "I Do"',
                'slug' => 'wedding-planning-timeline-ghana',
                'subheadline' => 'Avoid last-minute rush and keep your wedding stress-free with this month-by-month planning roadmap.',
                'category' => 'Planning & Timeline',
                'tags' => 'Wedding Planning, Timeline, Vendor Booking, Ghana Wedding',
                'cover_image' => 'img/chill/event1.jpg',
                'video_url' => 'img/chill/v1_opt.mp4',
                'author_name' => 'Chill & Serve Editorial',
                'author_avatar' => 'img/chill/logo.jpg',
                'status' => 'published',
                'published_at' => $now_str,
                'views_count' => 23450,
                'likes_count' => 3420,
                'comments_count' => 128,
                'shares_count' => 840,
                'reading_time' => 5,
                'featured' => 1,
                'content' => '<p class="lead">Planning a wedding in Ghana is an exhilarating journey, but managing vendors, family expectations, traditional rites, and reception logistics can quickly become overwhelming without a structured roadmap.</p><h2>12 to 9 Months Before: Foundation & Rites</h2><p>Start by establishing your total budget and determining key priorities with your partner. In Ghana, early consultation with family elders regarding the traditional Knocking ceremony (Kokoako) is essential to lock in dates before booking reception venues.</p><blockquote>"Securing your core venue and high-demand vendors 9 to 12 months ahead guarantees peace of mind and prevents last-minute compromises."</blockquote><div class="article-image-box"><img src="img/chill/event2.jpg" alt="Wedding Event Setup"><p class="caption">Early venue selection sets the tone for your wedding theme and guest capacity.</p></div><h2>8 to 6 Months Before: Securing Key Professionals</h2><p>This is the critical window to book your photographer, decorator, caterer, and drinks dispatch team. Ensure vendors are verified and review their previous job portfolios on Ohati.</p><ul><li>Finalize bridal party attire and traditional Kente designs.</li><li>Book your wedding planner or day-of coordinator.</li><li>Confirm beverage logistics with professional chilling services to handle ice and cold storage.</li></ul><div class="article-image-box"><img src="img/chill/1.jpg" alt="Chill and Serve Beverage Logistics"><p class="caption">Professional beverage dispatchers ensuring ice-cold drinks for reception guests.</p></div><h2>3 Months to 1 Month Before: Final Details</h2><p>Send out formal invitations, conduct food and cake tastings, and schedule hair/makeup trials. Confirm sound system setups and timeline flow with your MC and DJ.</p>'
            ],
            [
                'title' => 'How to Choose the Perfect Wedding Venue in Ghana: Indoor Halls vs Outdoor Gardens',
                'slug' => 'choosing-perfect-wedding-venue-ghana',
                'subheadline' => 'Key factors to consider including guest capacity, weather backups, vendor accessibility, and sound restrictions.',
                'category' => 'Venues & Locations',
                'tags' => 'Wedding Venues, Garden Wedding, Event Space, Accra Venues',
                'cover_image' => 'img/chill/event3.jpg',
                'video_url' => 'img/chill/v2_opt.mp4',
                'author_name' => 'Kojo Mensah',
                'author_avatar' => 'img/app_icon.png',
                'status' => 'published',
                'published_at' => $now_str,
                'views_count' => 18920,
                'likes_count' => 2150,
                'comments_count' => 86,
                'shares_count' => 530,
                'reading_time' => 4,
                'featured' => 0,
                'content' => '<p class="lead">Your wedding venue sets the backdrop for your entire celebration. Choosing between an air-conditioned ballroom and a lush outdoor garden requires balancing aesthetics, guest comfort, and climate realities.</p><h2>Indoor Ballrooms: Elegance & Climate Control</h2><p>Indoor venues offer complete protection against Ghana\'s sudden tropical rains and intense afternoon heat. They provide controlled lighting for photographers and built-in sound isolation.</p><div class="article-image-box"><img src="img/chill/event4.jpg" alt="Indoor Wedding Hall Setup"><p class="caption">Indoor halls provide climate comfort and dramatic ceiling drape potential.</p></div><h2>Outdoor Gardens: Natural Splendor & Airiness</h2><p>Outdoor lawns in Airport City, Cantonments, or Aburi offer breathtaking botanical scenery and flexible space for large traditional gatherings. Always ensure your venue contract includes a marquee tent fallback option in case of rain.</p><blockquote>"Always inspect restroom facilities, backup power generator capacity, and parking space before signing your venue contract."</blockquote><div class="article-image-box"><img src="img/chill/3.jpg" alt="Outdoor Lawn Event Setup"><p class="caption">Lush outdoor lawns offer magnificent photo opportunities when paired with marquee tents.</p></div>'
            ],
            [
                'title' => 'Modern Ghanaian Wedding Decor Trends: Blending Traditional Kente Aesthetics with Minimalist Elegance',
                'slug' => 'modern-ghanaian-wedding-decor-trends',
                'subheadline' => 'Transform your reception hall with fairy lighting, lush floral arches, velvet drape accents, and authentic heritage touches.',
                'category' => 'Decoration & Styling',
                'tags' => 'Wedding Decor, Kente Decor, Floral Styling, Reception Trends',
                'cover_image' => 'img/chill/event6.jpg',
                'video_url' => 'img/chill/v3_opt.mp4',
                'author_name' => 'Ama Serwaa',
                'author_avatar' => 'img/new_icon_ohati.png',
                'status' => 'published',
                'published_at' => $now_str,
                'views_count' => 14830,
                'likes_count' => 1840,
                'comments_count' => 64,
                'shares_count' => 410,
                'reading_time' => 4,
                'featured' => 0,
                'content' => '<p class="lead">Today\'s couples are redefining wedding styling by pairing rich heritage textures with sleek contemporary minimalism. Here is how leading decorators are bringing high glamour to Ghanaian receptions.</p><h2>1. Heritage Fusion Color Palettes</h2><p>Combining traditional gold, royal blue, or burgundy Kente accents with neutral ivory drapes creates a sophisticated balance between cultural reverence and modern luxury.</p><div class="article-image-box"><img src="img/chill/event7.jpg" alt="Floral Stage Decor"><p class="caption">Lush floral stages combined with soft ambient lighting create an unforgettable entrance.</p></div><h2>2. Statement Head Tables & Fairy Light Canopy</h2><p>Mirrored bridal tables surrounded by cascading white roses and warm LED fairy light canopies draw immediate focus to the newlyweds, providing stunning background visuals for video highlights.</p><blockquote>"Strategic lighting transforms simple decor into an enchanting cinematic experience."</blockquote><div class="article-image-box"><img src="img/chill/4.jpg" alt="Table Styling and Lighting"><p class="caption">Ambient table centerpieces elevate guest dining experiences.</p></div>'
            ],
            [
                'title' => 'Elevating the Guest Experience: Cold Drinks, Gourmet Catering & Flawless Bar Dispatch',
                'slug' => 'elevating-guest-experience-food-drinks',
                'subheadline' => 'Why pre-chilled beverages, prompt cocktail service, and diverse menu options are the secret to an unforgettable reception.',
                'category' => 'Food & Drinks',
                'tags' => 'Wedding Catering, Chill and Serve, Drinks Dispatch, Guest Comfort',
                'cover_image' => 'img/chill/services.jpg',
                'video_url' => 'img/chill/v4_opt.mp4',
                'author_name' => 'Chill & Serve Hospitality Team',
                'author_avatar' => 'img/chill/logo.jpg',
                'status' => 'published',
                'published_at' => $now_str,
                'views_count' => 21670,
                'likes_count' => 2980,
                'comments_count' => 112,
                'shares_count' => 790,
                'reading_time' => 5,
                'featured' => 1,
                'content' => '<p class="lead">Long after the wedding ceremony concludes, guests remember two main aspects: the music energy and the food & drink service quality. Ensuring your drinks are ice-cold from the start is paramount.</p><h2>The Ice & Chilling Logistics Secret</h2><p>Ghana\'s warm weather demands proactive pre-cooling. Standard venue fridges cannot handle hundreds of beverages simultaneously. Partnering with professional chilling specialists who bring mobile refrigerated containers and block ice guarantees drinks remain sub-zero all night.</p><div class="article-image-box"><img src="img/chill/5.jpg" alt="Chill and Serve Ice Chests"><p class="caption">Refrigerated vans and dedicated ice chests keep soft drinks, wine, and beer thoroughly chilled.</p></div><h2>Buffet Flow & Drink Dispatching</h2><p>Prevent long queues by placing dual service stations and employing uniformed drinks waiters who circulate continuously between guest tables.</p><blockquote>"Guests shouldn\'t have to search for a cold beverage. Proactive table service keeps the celebratory atmosphere vibrant."</blockquote><div class="article-image-box"><img src="img/chill/6.jpg" alt="Professional Drink Servers"><p class="caption">Uniformed servers ensure prompt drink delivery throughout the reception.</p></div>'
            ],
            [
                'title' => 'Capturing Timeless Memories: 7 Essential Moments Your Wedding Photographer Must Capture',
                'slug' => 'capturing-timeless-wedding-memories',
                'subheadline' => 'From the emotional first look to unscripted dance floor joy, ensure your photo album tells your true love story.',
                'category' => 'Photography & Media',
                'tags' => 'Wedding Photography, Photography Guide, Memories, Ghana Bride',
                'cover_image' => 'img/chill/2.jpg',
                'video_url' => 'img/chill/v5_opt.mp4',
                'author_name' => 'Yaw Asante Studio',
                'author_avatar' => 'img/app_icon.png',
                'status' => 'published',
                'published_at' => $now_str,
                'views_count' => 16410,
                'likes_count' => 2340,
                'comments_count' => 78,
                'shares_count' => 490,
                'reading_time' => 4,
                'featured' => 0,
                'content' => '<p class="lead">Your wedding photos and video reels will outlast the flowers and cake. Communicating your key shot list with your photography team ensures no precious emotional moment is missed.</p><h2>1. The Emotional First Look & Preparation</h2><p>Candid images during bridal makeup and groom prep reveal genuine excitement and anticipation. Capture subtle detail shots of the rings, gown, shoes, and traditional accessories.</p><div class="article-image-box"><img src="img/chill/Before the rings… there is this moment ❤️#VEEVALACOLD⸻Bride- @Vtabi_officialGroom- @Kingcold__Ev.jpg" alt="Pre-wedding Moment"><p class="caption">Quiet moments before the ceremony preserve pure emotion.</p></div><h2>2. Family Blessing & Dance Floor High Energy</h2><p>Ghanaian weddings are famous for unscripted dance floor joy and heartfelt parental blessings. Ensure your photographer has clear line-of-sight during the entrance dance and bouquet toss.</p><blockquote>"The best wedding photos aren\'t posed—they are authentic reflections of love, family, and joy."</blockquote><div class="article-image-box"><img src="img/chill/event5.jpg" alt="Dance Floor Celebration"><p class="caption">High-energy celebration images bring your wedding album to life.</p></div>'
            ]
        ];

        $ins_stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, subheadline, category, tags, cover_image, video_url, author_name, author_avatar, status, published_at, views_count, likes_count, comments_count, shares_count, reading_time, featured, content) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($seed_posts as $sp) {
            $ins_stmt->execute([
                $sp['title'], $sp['slug'], $sp['subheadline'], $sp['category'], $sp['tags'],
                $sp['cover_image'], $sp['video_url'], $sp['author_name'], $sp['author_avatar'],
                $sp['status'], $sp['published_at'], $sp['views_count'], $sp['likes_count'],
                $sp['comments_count'], $sp['shares_count'], $sp['reading_time'], $sp['featured'],
                $sp['content']
            ]);
        }

        // Seed sample comments for Post #1 and Post #4
        $ins_com = $pdo->prepare("INSERT INTO blog_comments (post_id, author_name, author_avatar, comment, status, created_at) VALUES (?,?,?,?,?,?)");
        $ins_com->execute([1, 'Efia Dufie', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=150', 'This timeline guide saved our wedding planning! The recommendation to book chilling services 6 months ahead was spot on.', 'approved', $now_str]);
        $ins_com->execute([1, 'Kwadwo Poku', 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=150', 'Great article! Highly recommend locking in venues early in Accra.', 'approved', $now_str]);
        $ins_com->execute([4, 'Akosua Baako', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=150', 'Chill & Serve Ghana handled our drinks at Airport City and everything stayed freezing cold all night!', 'approved', $now_str]);
    }
} catch (Exception $e) {}

try {
    $c = $pdo->query("SELECT COUNT(*) FROM discounts")->fetchColumn();
    if ($c == 0) {
        $pdo->exec("INSERT INTO discounts (code, discount_type, discount_value, min_booking_amount, max_discount_amount, usage_limit, is_active) VALUES ('WELCOME10', 'percentage', 10.0, 50.0, 500.0, 1000, 1)");
        $pdo->exec("INSERT INTO discounts (code, discount_type, discount_value, min_booking_amount, max_discount_amount, usage_limit, is_active) VALUES ('OHATI50', 'fixed', 50.0, 200.0, 50.0, 500, 1)");
    }
} catch (Exception $e) {}

// ── VENDORS ────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS vendors (
    id $AI,
    user_id INT DEFAULT 0,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    logo VARCHAR(500) NOT NULL DEFAULT '',
    cover_photo VARCHAR(500) NOT NULL DEFAULT '',
    description TEXT,
    experience INT DEFAULT 0,
    packages_pricing TEXT,
    location VARCHAR(255) DEFAULT '',
    gps_lat FLOAT DEFAULT 0,
    gps_lng FLOAT DEFAULT 0,
    phone VARCHAR(50) DEFAULT '',
    whatsapp VARCHAR(50) DEFAULT '',
    email VARCHAR(255) DEFAULT '',
    website VARCHAR(500) DEFAULT '',
    social_links TEXT,
    rating FLOAT DEFAULT 0,
    reviews_count INT DEFAULT 0,
    verified INT DEFAULT 0,
    verification_status VARCHAR(30) DEFAULT 'pending',
    verification_badge VARCHAR(30) DEFAULT 'grey',
    premium INT DEFAULT 0,
    has_insurance INT DEFAULT 0,
    service_radius VARCHAR(50) DEFAULT 'Nationwide',
    response_time VARCHAR(100) DEFAULT 'Within 24 hours',
    availability VARCHAR(50) DEFAULT 'Available',
    working_hours TEXT,
    gallery TEXT,
    intro_video VARCHAR(500) DEFAULT '',
    team_members TEXT,
    faqs TEXT,
    languages TEXT,
    certifications TEXT,
    awards TEXT,
    completed_jobs INT DEFAULT 0,
    repeat_customer_pct INT DEFAULT 0,
    instant_booking INT DEFAULT 0,
    is_active INT DEFAULT 1,
    business_reg VARCHAR(200) DEFAULT '',
    tax_number VARCHAR(100) DEFAULT '',
    bank_name VARCHAR(200) DEFAULT '',
    account_name VARCHAR(200) DEFAULT '',
    account_number VARCHAR(100) DEFAULT '',
    momo_number VARCHAR(50) DEFAULT '',
    momo_provider VARCHAR(50) DEFAULT '',
    payout_method VARCHAR(50) DEFAULT '',
    commission_rate FLOAT DEFAULT 10.0,
    featured INT DEFAULT 0,
    feature_expires_at VARCHAR(50) DEFAULT '',
    last_active $NOW,
    created_at $NOW
)");

try {
    $pdo->exec("ALTER TABLE vendors ADD COLUMN views_count INT DEFAULT 0");
} catch (Exception $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS vendor_views_log (
    id $AI,
    vendor_id INT NOT NULL,
    user_id INT DEFAULT 0,
    ip_address VARCHAR(50) DEFAULT '',
    created_at $NOW
)");

// Dynamic column updates

try {
    $pdo->exec("ALTER TABLE vendors ADD COLUMN welcome_message TEXT DEFAULT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE vendors ADD COLUMN auto_response TEXT DEFAULT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE vendors ADD COLUMN premium_expires_at VARCHAR(50) DEFAULT ''");
} catch (Exception $e) {}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN active_role VARCHAR(20) DEFAULT 'customer'");
} catch (Exception $e) {}

// Advertisement manual payment updates
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN payment_method VARCHAR(50) DEFAULT 'paystack'");
} catch (Exception $e) {}

// WebRTC Audio & Video Calls Table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS calls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        caller_id INT NOT NULL,
        receiver_id INT NOT NULL,
        type VARCHAR(20) DEFAULT 'voice',
        status VARCHAR(30) DEFAULT 'dialing',
        sdp_offer TEXT,
        sdp_answer TEXT,
        ice_candidates_caller TEXT,
        ice_candidates_receiver TEXT,
        duration INT DEFAULT 0,
        created_at $NOW,
        updated_at $NOW
    )");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE calls ADD COLUMN duration INT DEFAULT 0");
} catch (Exception $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT 0,
        device_token VARCHAR(500) NOT NULL,
        platform VARCHAR(50) DEFAULT 'android',
        updated_at $NOW,
        UNIQUE KEY uq_token (device_token(255))
    )");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN payment_ref VARCHAR(200) DEFAULT ''");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN receipt_url VARCHAR(500) DEFAULT ''");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN payment_date VARCHAR(50) DEFAULT ''");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN payment_notes TEXT DEFAULT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN admin_notes TEXT DEFAULT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN placement VARCHAR(50) DEFAULT 'home_top_banner'");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN max_views INT DEFAULT 0");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN views_count INT DEFAULT 0");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN max_popups INT DEFAULT 0");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN popup_count INT DEFAULT 0");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE advertisements ADD COLUMN payment_status VARCHAR(30) DEFAULT 'pending'");
} catch (Exception $e) {}


// ── REVIEWS ────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
    id $AI,
    vendor_id INT NOT NULL,
    user_id INT DEFAULT 0,
    user_name VARCHAR(100) NOT NULL,
    user_avatar VARCHAR(500) DEFAULT '',
    rating INT NOT NULL,
    comment TEXT NOT NULL,
    photos TEXT,
    helpful_votes INT DEFAULT 0,
    vendor_response TEXT,
    vendor_response_at VARCHAR(50) DEFAULT '',
    verified_booking INT DEFAULT 0,
    date VARCHAR(50) NOT NULL,
    created_at $NOW
)");

// ── BOOKINGS ──────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
    id $AI,
    vendor_id INT NOT NULL,
    user_id INT DEFAULT 0,
    user_name VARCHAR(100) NOT NULL,
    user_phone VARCHAR(50) NOT NULL,
    event_date VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) DEFAULT '',
    package_name VARCHAR(100) DEFAULT '',
    price FLOAT DEFAULT 0,
    negotiated_price FLOAT DEFAULT 0,
    deposit_paid FLOAT DEFAULT 0,
    balance_paid FLOAT DEFAULT 0,
    total_paid FLOAT DEFAULT 0,
    payment_status VARCHAR(50) DEFAULT 'Unpaid',
    status VARCHAR(50) DEFAULT 'Inquiry',
    notes TEXT,
    inspiration_photos TEXT,
    negotiation_history TEXT,
    timeline TEXT,
    contract_url VARCHAR(500) DEFAULT '',
    contract_signed INT DEFAULT 0,
    escrow_held FLOAT DEFAULT 0,
    coupon_code VARCHAR(50) DEFAULT '',
    discount_amount FLOAT DEFAULT 0,
    created_at $NOW
)");

// ── MESSAGES ──────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
    id $AI,
    vendor_id INT NOT NULL,
    user_id INT DEFAULT 0,
    sender VARCHAR(20) NOT NULL,
    type VARCHAR(20) DEFAULT 'text',
    message TEXT NOT NULL,
    media_url VARCHAR(500) DEFAULT '',
    is_read INT DEFAULT 0,
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
    id $AI,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    reason VARCHAR(255) DEFAULT '',
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_reports (
    id $AI,
    reporter_id INT NOT NULL,
    reported_user_id INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    details TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS comment_reports (
    id $AI,
    reporter_id INT NOT NULL,
    comment_id INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    details TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at $NOW
)");

// ── EVENTS ────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS user_event (
    id $AI,
    user_id INT DEFAULT 0,
    event_name VARCHAR(200) DEFAULT 'My Event',
    event_type VARCHAR(100) NOT NULL DEFAULT 'Wedding',
    event_date VARCHAR(50) NOT NULL DEFAULT '',
    start_time VARCHAR(50) DEFAULT '',
    end_time VARCHAR(50) DEFAULT '',
    location VARCHAR(255) DEFAULT '',
    region VARCHAR(100) DEFAULT '',
    city VARCHAR(100) DEFAULT '',
    indoor_outdoor VARCHAR(50) DEFAULT '',
    estimated_budget FLOAT DEFAULT 0,
    guest_count INT DEFAULT 0,
    theme VARCHAR(255) DEFAULT '',
    colors TEXT,
    notes TEXT,
    created_at $NOW
)");

// ── TRACKER TASKS ─────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS tracker_tasks (
    id $AI,
    user_id INT DEFAULT 0,
    task_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    priority VARCHAR(50) DEFAULT 'Medium',
    estimated_date VARCHAR(50) DEFAULT '',
    due_date VARCHAR(50) DEFAULT '',
    completed INT DEFAULT 0,
    notes TEXT,
    is_custom INT DEFAULT 0,
    cost FLOAT DEFAULT 0,
    paid_amount FLOAT DEFAULT 0
)");

// ── CALLS ─────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS calls (
    id $AI,
    caller_id INT NOT NULL,
    receiver_id INT NOT NULL,
    type VARCHAR(50) DEFAULT 'voice',
    status VARCHAR(50) DEFAULT 'ringing',
    sdp_offer TEXT,
    sdp_answer TEXT,
    ice_candidates_caller TEXT,
    ice_candidates_receiver TEXT,
    duration INT DEFAULT 0,
    created_at $NOW,
    updated_at VARCHAR(100) DEFAULT ''
)");

// ── PAYMENTS ──────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS payments (
    id $AI,
    booking_id INT NOT NULL,
    user_id INT DEFAULT 0,
    vendor_id INT DEFAULT 0,
    amount FLOAT NOT NULL,
    currency VARCHAR(10) DEFAULT 'GHS',
    method VARCHAR(50) DEFAULT '',
    provider VARCHAR(50) DEFAULT '',
    provider_ref VARCHAR(200) DEFAULT '',
    status VARCHAR(50) DEFAULT 'pending',
    type VARCHAR(50) DEFAULT 'deposit',
    receipt_url VARCHAR(500) DEFAULT '',
    notes TEXT,
    created_at $NOW
)");

// ── COUPONS ───────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
    id $AI,
    code VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(20) DEFAULT 'percent',
    value FLOAT NOT NULL,
    min_order FLOAT DEFAULT 0,
    max_uses INT DEFAULT 100,
    uses_count INT DEFAULT 0,
    expires_at VARCHAR(50) DEFAULT '',
    is_active INT DEFAULT 1,
    created_at $NOW
)");

// ── NOTIFICATIONS ─────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id $AI,
    user_id INT DEFAULT 0,
    type VARCHAR(50) DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    icon VARCHAR(50) DEFAULT 'bell',
    link VARCHAR(500) DEFAULT '',
    is_read INT DEFAULT 0,
    created_at $NOW
)");

// ── ASYNCHRONOUS NOTIFICATION QUEUE ────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS notification_queue (
    id $AI,
    recipient_email VARCHAR(200) DEFAULT '',
    recipient_phone VARCHAR(50) DEFAULT '',
    title VARCHAR(200) NOT NULL,
    sms_message TEXT,
    email_subject VARCHAR(255) DEFAULT '',
    email_body TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_error TEXT,
    processed_at VARCHAR(50) DEFAULT '',
    created_at $NOW
)");

// ── OTP ───────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS otp_codes (
    id $AI,
    target VARCHAR(200) NOT NULL,
    code VARCHAR(10) DEFAULT '',
    code_hash VARCHAR(255) DEFAULT '',
    type VARCHAR(30) DEFAULT 'verify',
    email_status VARCHAR(50) DEFAULT 'pending',
    sms_status VARCHAR(50) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    used INT DEFAULT 0,
    expires_at VARCHAR(50) NOT NULL,
    ip_address VARCHAR(50) DEFAULT '',
    device VARCHAR(200) DEFAULT '',
    created_at $NOW
)");
// ── LOGIN HISTORY ─────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS login_history (
    id $AI,
    user_id INT NOT NULL,
    ip_address VARCHAR(50) DEFAULT '',
    device VARCHAR(200) DEFAULT '',
    status VARCHAR(20) DEFAULT 'success',
    created_at $NOW
)");

// ── PROFILE CHANGE REQUESTS ────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS profile_change_requests (
    id $AI,
    user_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    supporting_document VARCHAR(500) DEFAULT '',
    status VARCHAR(30) DEFAULT 'pending',
    admin_notes TEXT,
    created_at $NOW
)");

// ── PROFILE ACTIVITY LOG ───────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS profile_activity_log (
    id $AI,
    user_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    device VARCHAR(200) DEFAULT '',
    ip_address VARCHAR(50) DEFAULT '',
    created_at $NOW
)");

// ── ADVERTISEMENTS ─────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS advertisements (
    id $AI,
    vendor_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    banner_url VARCHAR(500) DEFAULT '',
    video_url VARCHAR(500) DEFAULT '',
    cta_text VARCHAR(100) DEFAULT 'Learn More',
    destination VARCHAR(100) DEFAULT 'profile',
    duration_days INT NOT NULL,
    cost FLOAT NOT NULL,
    start_date VARCHAR(50) NOT NULL,
    end_date VARCHAR(50) NOT NULL,
    status VARCHAR(30) DEFAULT 'pending',
    target_event VARCHAR(100) DEFAULT 'All',
    target_category VARCHAR(100) DEFAULT 'All',
    target_location VARCHAR(100) DEFAULT 'All',
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_at $NOW
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
    key_name VARCHAR(100) PRIMARY KEY,
    val_value TEXT
)");

// Initialize default admin payment details
try {
    $defaults = [
        'admin_bank_name' => 'Ecobank Ghana',
        'admin_account_name' => 'Ohati Global Digital Services',
        'admin_account_number' => '1441002939201',
        'admin_momo_provider' => 'MTN Mobile Money',
        'admin_momo_number' => '0540477911',
        'admin_momo_name' => 'Ohati Payments',
        'admin_payment_instructions' => 'Please transfer the ad campaign fee to MTN MoMo (0540477911) or Ecobank Ghana (1441002939201). Upload your receipt screenshot and enter your transaction ID below.'
    ];
    foreach ($defaults as $k => $v) {
        $chk_def = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = ?");
        $chk_def->execute([$k]);
        if ($chk_def->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES (?, ?)")->execute([$k, $v]);
        }
    }
} catch (Exception $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS premium_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    amount FLOAT NOT NULL,
    receipt_url VARCHAR(500) DEFAULT '',
    payment_ref VARCHAR(100) DEFAULT '',
    payment_date VARCHAR(50) DEFAULT '',
    payment_notes TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ── FAQS ──────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
    id $AI,
    category VARCHAR(100) DEFAULT 'General',
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    display_order INT DEFAULT 0,
    created_at $NOW
)");

// ── REPORTED ISSUES ───────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS reported_issues (
    id $AI,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    screenshot_url TEXT DEFAULT '',
    status VARCHAR(30) DEFAULT 'open',
    created_at $NOW
)");

// ── ESCROW TRANSACTIONS ───────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS escrow_transactions (
    id $AI,
    booking_id INT NOT NULL,
    payment_id INT DEFAULT 0,
    customer_id INT NOT NULL,
    vendor_id INT NOT NULL,
    amount FLOAT NOT NULL,
    platform_fee FLOAT DEFAULT 0,
    vendor_amount FLOAT DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'GHS',
    paystack_reference VARCHAR(200) DEFAULT '' UNIQUE,
    paystack_access_code VARCHAR(200) DEFAULT '',
    paystack_status VARCHAR(50) DEFAULT '',
    escrow_status VARCHAR(30) DEFAULT 'pending',
    release_reason VARCHAR(100) DEFAULT '',
    released_by INT DEFAULT 0,
    released_at VARCHAR(50) DEFAULT '',
    frozen INT DEFAULT 0,
    frozen_reason TEXT,
    notes TEXT,
    ip_address VARCHAR(50) DEFAULT '',
    device VARCHAR(300) DEFAULT '',
    created_at $NOW
)");

// ── VENDOR WALLETS ────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS vendor_wallets (
    id $AI,
    vendor_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    available_balance FLOAT DEFAULT 0,
    escrow_balance FLOAT DEFAULT 0,
    pending_balance FLOAT DEFAULT 0,
    processing_balance FLOAT DEFAULT 0,
    lifetime_earnings FLOAT DEFAULT 0,
    total_withdrawn FLOAT DEFAULT 0,
    total_refunded FLOAT DEFAULT 0,
    is_frozen INT DEFAULT 0,
    frozen_reason TEXT,
    frozen_by INT DEFAULT 0,
    frozen_at VARCHAR(50) DEFAULT '',
    last_withdrawal_at VARCHAR(50) DEFAULT '',
    last_deposit_at VARCHAR(50) DEFAULT '',
    created_at $NOW,
    updated_at $NOW
)");

// ── WITHDRAWALS ───────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS withdrawals (
    id $AI,
    vendor_id INT NOT NULL,
    user_id INT NOT NULL,
    amount FLOAT NOT NULL,
    fee FLOAT DEFAULT 0,
    net_amount FLOAT DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'GHS',
    bank_name VARCHAR(200) DEFAULT '',
    account_name VARCHAR(200) DEFAULT '',
    account_number VARCHAR(100) DEFAULT '',
    bank_code VARCHAR(20) DEFAULT '',
    paystack_transfer_code VARCHAR(200) DEFAULT '',
    paystack_transfer_ref VARCHAR(200) DEFAULT '',
    paystack_recipient_code VARCHAR(200) DEFAULT '',
    status VARCHAR(30) DEFAULT 'pending',
    approved_by INT DEFAULT 0,
    approved_at VARCHAR(50) DEFAULT '',
    completed_at VARCHAR(50) DEFAULT '',
    rejected_reason TEXT,
    ip_address VARCHAR(50) DEFAULT '',
    created_at $NOW
)");

// ── DISPUTES ──────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS disputes (
    id $AI,
    booking_id INT NOT NULL,
    escrow_id INT DEFAULT 0,
    customer_id INT NOT NULL,
    vendor_id INT NOT NULL,
    subject VARCHAR(300) NOT NULL,
    description TEXT NOT NULL,
    evidence_urls TEXT,
    status VARCHAR(30) DEFAULT 'open',
    resolution VARCHAR(30) DEFAULT '',
    resolution_notes TEXT,
    resolved_by INT DEFAULT 0,
    resolved_at VARCHAR(50) DEFAULT '',
    refund_amount FLOAT DEFAULT 0,
    frozen_amount FLOAT DEFAULT 0,
    created_at $NOW
)");

// ── REFUNDS ───────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS refunds (
    id $AI,
    escrow_id INT DEFAULT 0,
    booking_id INT NOT NULL,
    dispute_id INT DEFAULT 0,
    customer_id INT NOT NULL,
    vendor_id INT NOT NULL,
    amount FLOAT NOT NULL,
    type VARCHAR(30) DEFAULT 'full',
    reason TEXT,
    status VARCHAR(30) DEFAULT 'pending',
    approved_by INT DEFAULT 0,
    approved_at VARCHAR(50) DEFAULT '',
    paystack_refund_ref VARCHAR(200) DEFAULT '',
    created_at $NOW
)");

// ── FINANCIAL AUDIT LOG ───────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS financial_audit_log (
    id $AI,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT DEFAULT 0,
    actor_id INT DEFAULT 0,
    actor_role VARCHAR(30) DEFAULT '',
    actor_name VARCHAR(200) DEFAULT '',
    amount FLOAT DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'GHS',
    old_status VARCHAR(50) DEFAULT '',
    new_status VARCHAR(50) DEFAULT '',
    details TEXT,
    ip_address VARCHAR(50) DEFAULT '',
    device VARCHAR(300) DEFAULT '',
    created_at $NOW
)");

// ── MIGRATIONS (safe alter) ───────────────────────────────────────────────
$migrations = [
    "ALTER TABLE tracker_tasks ADD COLUMN cost REAL DEFAULT 0.0",
    "ALTER TABLE tracker_tasks ADD COLUMN paid_amount REAL DEFAULT 0.0",
    "ALTER TABLE tracker_tasks ADD COLUMN due_date VARCHAR(50)",
    "ALTER TABLE bookings ADD COLUMN event_type VARCHAR(100) DEFAULT ''",
    "ALTER TABLE bookings ADD COLUMN coupon_code VARCHAR(50) DEFAULT ''",
    "ALTER TABLE bookings ADD COLUMN discount_amount FLOAT DEFAULT 0",
    "ALTER TABLE bookings ADD COLUMN contract_signed INT DEFAULT 0",
    "ALTER TABLE vendors ADD COLUMN featured INT DEFAULT 0",
    "ALTER TABLE vendors ADD COLUMN instant_booking INT DEFAULT 0",
    "ALTER TABLE messages ADD COLUMN type VARCHAR(20) DEFAULT 'text'",
    "ALTER TABLE messages ADD COLUMN media_url VARCHAR(500) DEFAULT ''",
    "ALTER TABLE messages ADD COLUMN is_read INT DEFAULT 0",
    "ALTER TABLE vendors ADD COLUMN auto_response TEXT DEFAULT ''",
    "ALTER TABLE vendors ADD COLUMN views_count INT DEFAULT 0",
    "ALTER TABLE vendors ADD COLUMN followers_count INT DEFAULT 0",
];
foreach ($migrations as $m) {
    try { $pdo->exec($m); } catch (Exception $e) {}
}

// Create followers table
$pdo->exec("CREATE TABLE IF NOT EXISTS followers (
    id $AI,
    user_id INT NOT NULL,
    vendor_id INT NOT NULL,
    created_at $NOW,
    UNIQUE(user_id, vendor_id)
)");

// Create vendor_views_log table
$pdo->exec("CREATE TABLE IF NOT EXISTS vendor_views_log (
    id $AI,
    vendor_id INT NOT NULL,
    user_id INT DEFAULT 0,
    ip_address VARCHAR(45) DEFAULT '',
    created_at $NOW
)");

// Create discount_requests table
$pdo->exec("CREATE TABLE IF NOT EXISTS discount_requests (
    id $AI,
    user_id INT NOT NULL,
    vendor_id INT NOT NULL,
    event_type VARCHAR(100) DEFAULT '',
    event_date VARCHAR(50) DEFAULT '',
    target_price FLOAT DEFAULT 0,
    requested_discount_pct FLOAT DEFAULT 0,
    notes TEXT,
    vendor_response TEXT,
    counter_price FLOAT DEFAULT 0,
    status VARCHAR(30) DEFAULT 'pending',
    coupon_code VARCHAR(50) DEFAULT '',
    created_at $NOW
)");

// Create deleted_records table
$pdo->exec("CREATE TABLE IF NOT EXISTS deleted_records (
    id $AI,
    record_type VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    record_data LONGTEXT NOT NULL,
    deleted_by INT DEFAULT 0,
    deleted_at $NOW
)");


// ── ENSURE ADMIN ACCOUNT & TABLES EXIST SAFELY (NO DATA LOSS) ──────────
try {
    $admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($admin_count == 0) {
        $admin_email = 'admin@ohati.com';
        $admin_name = 'Chill & Serve Ghana';
        $admin_hash = password_hash('OhatiAdmin2026@Pass', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role, email_verified, is_active) VALUES (?, ?, ?, 'admin', 1, 1)")
            ->execute([$admin_name, $admin_email, $admin_hash]);
    }
    $demo_cust_count = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'demo.customer@ohati.com'")->fetchColumn();
    if ($demo_cust_count == 0) {
        $cust_hash = password_hash('OhatiDemo2026@Customer', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, email_verified, phone_verified, is_active) VALUES ('App Review Customer', 'demo.customer@ohati.com', '+233200000001', ?, 'customer', 1, 1, 1)")
            ->execute([$cust_hash]);
    }
    $demo_vnd_count = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'demo.vendor@ohati.com'")->fetchColumn();
    if ($demo_vnd_count == 0) {
        $vnd_hash = password_hash('OhatiDemo2026@Vendor', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, email_verified, phone_verified, is_active) VALUES ('App Review Vendor', 'demo.vendor@ohati.com', '+233200000002', ?, 'vendor', 1, 1, 1)")
            ->execute([$vnd_hash]);
        $v_uid = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO vendors (user_id, name, category, location, rating, verified, verification_badge, is_active) VALUES (?, 'App Review Event Services', 'Photography', 'Accra, Ghana', 5.0, 1, 'gold', 1)")
            ->execute([$v_uid]);
    }

    $msg_count = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    if ($msg_count < 2) {
        $now_stamp = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO messages (vendor_id, user_id, sender, message, type, created_at) VALUES (1, 3, 'user', 'Hello Chill & Serve, I would like to inquire about your event chilling packages.', 'text', ?)")
            ->execute([$now_stamp]);
        $pdo->prepare("INSERT INTO messages (vendor_id, user_id, sender, message, type, created_at) VALUES (1, 3, 'vendor', 'Hello! Thank you for reaching out. We offer premium beverage cooling and bar service packages for all events.', 'text', ?)")
            ->execute([$now_stamp]);
        $pdo->prepare("INSERT INTO messages (vendor_id, user_id, sender, message, type, created_at) VALUES (2, 3, 'user', 'Hi Jojo, are you available for wedding coverage next month?', 'text', ?)")
            ->execute([$now_stamp]);
    }
} catch (Exception $e) {}

// Create tables if not existing without dropping any data
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendors (id $AI, user_id INT DEFAULT 0, name VARCHAR(255) NOT NULL, category VARCHAR(100) NOT NULL, logo VARCHAR(500) NOT NULL DEFAULT '', cover_photo VARCHAR(500) NOT NULL DEFAULT '', description TEXT, experience INT DEFAULT 0, packages_pricing TEXT, location VARCHAR(255) DEFAULT '', gps_lat FLOAT DEFAULT 0, gps_lng FLOAT DEFAULT 0, phone VARCHAR(50) DEFAULT '', whatsapp VARCHAR(50) DEFAULT '', email VARCHAR(255) DEFAULT '', website VARCHAR(500) DEFAULT '', social_links TEXT, rating FLOAT DEFAULT 0, reviews_count INT DEFAULT 0, verified INT DEFAULT 0, verification_status VARCHAR(30) DEFAULT 'pending', verification_badge VARCHAR(30) DEFAULT 'grey', premium INT DEFAULT 0, has_insurance INT DEFAULT 0, service_radius VARCHAR(50) DEFAULT 'Nationwide', response_time VARCHAR(100) DEFAULT 'Within 24 hours', availability VARCHAR(50) DEFAULT 'Available', working_hours TEXT, gallery TEXT, intro_video VARCHAR(500) DEFAULT '', team_members TEXT, faqs TEXT, languages TEXT, certifications TEXT, awards TEXT, completed_jobs INT DEFAULT 0, repeat_customer_pct INT DEFAULT 0, instant_booking INT DEFAULT 0, is_active INT DEFAULT 1, business_reg VARCHAR(200) DEFAULT '', tax_number VARCHAR(100) DEFAULT '', bank_name VARCHAR(200) DEFAULT '', account_name VARCHAR(200) DEFAULT '', account_number VARCHAR(100) DEFAULT '', momo_number VARCHAR(50) DEFAULT '', momo_provider VARCHAR(50) DEFAULT '', payout_method VARCHAR(50) DEFAULT '', commission_rate FLOAT DEFAULT 10.0, featured INT DEFAULT 0, feature_expires_at VARCHAR(50) DEFAULT '', last_active $NOW, created_at $NOW)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (id $AI, vendor_id INT NOT NULL, user_id INT DEFAULT 0, user_name VARCHAR(100) NOT NULL, user_avatar VARCHAR(500) DEFAULT '', rating INT NOT NULL, comment TEXT NOT NULL, photos TEXT, helpful_votes INT DEFAULT 0, vendor_response TEXT, vendor_response_at VARCHAR(50) DEFAULT '', verified_booking INT DEFAULT 0, date VARCHAR(50) NOT NULL, created_at $NOW)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tracker_tasks (id $AI, user_id INT DEFAULT 0, task_name VARCHAR(255) NOT NULL, category VARCHAR(100) DEFAULT 'General', priority VARCHAR(50) DEFAULT 'Medium', estimated_date VARCHAR(50) DEFAULT '', due_date VARCHAR(50) DEFAULT '', completed INT DEFAULT 0, notes TEXT, is_custom INT DEFAULT 0, cost FLOAT DEFAULT 0, paid_amount FLOAT DEFAULT 0)");
    
    // Performance indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bk_vendor ON bookings(vendor_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bk_user ON bookings(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_msg_vid_uid ON messages(vendor_id, user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_vnd_cat ON vendors(category)");
} catch (Exception $e) {}

// Safe column migrations for pre-existing tables
try { $pdo->exec("ALTER TABLE otp_codes ADD COLUMN code_hash VARCHAR(255) DEFAULT ''"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE otp_codes ADD COLUMN email_status VARCHAR(50) DEFAULT 'pending'"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE otp_codes ADD COLUMN sms_status VARCHAR(50) DEFAULT 'pending'"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE otp_codes ADD COLUMN attempts INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE otp_codes ADD COLUMN ip_address VARCHAR(50) DEFAULT ''"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE otp_codes ADD COLUMN device VARCHAR(200) DEFAULT ''"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN pref_inapp INT DEFAULT 1"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN pref_push INT DEFAULT 1"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN pref_sms INT DEFAULT 1"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN pref_email INT DEFAULT 1"); } catch (Exception $e) {}

$pdo_logs->exec("CREATE TABLE IF NOT EXISTS audit_logs (
    id $AI,
    admin_id INT DEFAULT 0,
    admin_name VARCHAR(200) DEFAULT 'Admin',
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(50) DEFAULT '',
    user_agent VARCHAR(300) DEFAULT '',
    created_at $NOW
)");

// Performance Indexes for Multi-Database Tables
try {
    $pdo_jobs->exec("CREATE INDEX IF NOT EXISTS idx_jobs_user ON jobs(user_id)");
    $pdo_jobs->exec("CREATE INDEX IF NOT EXISTS idx_jobs_cat ON jobs(category)");
    $pdo_jobs->exec("CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status)");
    $pdo_jobs->exec("CREATE INDEX IF NOT EXISTS idx_app_job ON job_applications(job_id)");
    $pdo_jobs->exec("CREATE INDEX IF NOT EXISTS idx_app_vendor ON job_applications(vendor_id)");
    $pdo_comms->exec("CREATE INDEX IF NOT EXISTS idx_jnotif_user ON job_notifications(user_id)");
    $pdo_logs->exec("CREATE INDEX IF NOT EXISTS idx_audit_admin ON audit_logs(admin_id)");
} catch (Exception $e) {}

try { $pdo_jobs->exec("ALTER TABLE jobs ADD COLUMN admin_notes TEXT"); } catch (Exception $e) {}
try { $pdo_jobs->exec("ALTER TABLE jobs ADD COLUMN is_pinned INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo_jobs->exec("ALTER TABLE jobs ADD COLUMN is_locked INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo_jobs->exec("ALTER TABLE job_categories ADD COLUMN color_code VARCHAR(20) DEFAULT '#1B2B4B'"); } catch (Exception $e) {}


// ── EVENT JOBS MARKETPLACE (Database 2: ohaticom_2) ─────────────────────────
$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS job_categories (
    id $AI,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(100) DEFAULT 'fa-solid fa-briefcase',
    description TEXT,
    is_active INT DEFAULT 1,
    created_at $NOW
)");

try {
    $cat_count = $pdo_jobs->query("SELECT COUNT(*) FROM job_categories")->fetchColumn();
    if ($cat_count == 0) {
        $default_cats = [
            'Photography', 'Videography', 'MC', 'DJ', 'Catering', 'Decoration',
            'Hall', 'Live Band', 'Traditional Group', 'Security', 'Cleaning',
            'Makeup', 'Fashion Designer', 'Tailor', 'Florist', 'Cake',
            'Transportation', 'Rental Equipment', 'Event Planner', 'Ushering',
            'Sound Engineer', 'Lighting'
        ];
        $cat_stmt = $pdo_jobs->prepare("INSERT INTO job_categories (name) VALUES (?)");
        foreach ($default_cats as $c) { $cat_stmt->execute([$c]); }
    }
} catch (Exception $e) {}

$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS jobs (
    id $AI,
    user_id INT NOT NULL,
    user_name VARCHAR(200) DEFAULT '',
    user_avatar VARCHAR(500) DEFAULT '',
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100) DEFAULT '',
    description TEXT NOT NULL,
    required_skills TEXT,
    budget DECIMAL(12,2) DEFAULT 0.00,
    negotiable INT DEFAULT 1,
    location VARCHAR(200) DEFAULT '',
    event_type VARCHAR(20) DEFAULT 'physical',
    event_date VARCHAR(50) DEFAULT '',
    deadline VARCHAR(50) DEFAULT '',
    num_vendors INT DEFAULT 1,
    is_urgent INT DEFAULT 0,
    visibility VARCHAR(20) DEFAULT 'public',
    status VARCHAR(30) DEFAULT 'open',
    views_count INT DEFAULT 0,
    applications_count INT DEFAULT 0,
    shortlisted_count INT DEFAULT 0,
    hired_count INT DEFAULT 0,
    saved_count INT DEFAULT 0,
    is_featured INT DEFAULT 0,
    created_at $NOW,
    updated_at $NOW
)");

$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS job_attachments (
    id $AI,
    job_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) DEFAULT '',
    file_type VARCHAR(50) DEFAULT 'image',
    created_at $NOW
)");

$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS job_applications (
    id $AI,
    job_id INT NOT NULL,
    vendor_id INT NOT NULL,
    vendor_user_id INT DEFAULT 0,
    vendor_name VARCHAR(200) DEFAULT '',
    vendor_avatar VARCHAR(500) DEFAULT '',
    cover_letter TEXT NOT NULL,
    price_quote DECIMAL(12,2) NOT NULL,
    delivery_timeline VARCHAR(100) DEFAULT '',
    answers TEXT,
    portfolio_links TEXT,
    availability VARCHAR(100) DEFAULT '',
    status VARCHAR(30) DEFAULT 'submitted',
    is_featured INT DEFAULT 0,
    created_at $NOW,
    updated_at $NOW
)");

$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS job_application_attachments (
    id $AI,
    application_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) DEFAULT '',
    file_type VARCHAR(50) DEFAULT 'image',
    created_at $NOW
)");

$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS job_shortlists (
    id $AI,
    job_id INT NOT NULL,
    application_id INT NOT NULL,
    vendor_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at $NOW
)");

$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS job_hires (
    id $AI,
    job_id INT NOT NULL,
    application_id INT NOT NULL,
    user_id INT NOT NULL,
    vendor_id INT NOT NULL,
    agreed_price DECIMAL(12,2) NOT NULL,
    status VARCHAR(30) DEFAULT 'active',
    hired_at $NOW
)");

$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS job_saved (
    id $AI,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at $NOW
)");

$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS job_saved_vendors (
    id $AI,
    user_id INT NOT NULL,
    vendor_id INT NOT NULL,
    created_at $NOW
)");

// ── COMMUNICATION & MESSAGING (Database 3: ohaticom_3) ──────────────────────
$pdo_comms->exec("CREATE TABLE IF NOT EXISTS job_notifications (
    id $AI,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    job_id INT DEFAULT 0,
    application_id INT DEFAULT 0,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read INT DEFAULT 0,
    created_at $NOW
)");

// ── ANALYTICS & LOGS (Database 5: ohaticom_5) ──────────────────────────────
$pdo_logs->exec("CREATE TABLE IF NOT EXISTS job_reports (
    id $AI,
    reporter_user_id INT NOT NULL,
    target_type VARCHAR(20) NOT NULL,
    target_id INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    details TEXT,
    status VARCHAR(30) DEFAULT 'pending',
    created_at $NOW
)");

$pdo_logs->exec("CREATE TABLE IF NOT EXISTS job_analytics (
    id $AI,
    user_id INT DEFAULT 0,
    vendor_id INT DEFAULT 0,
    metric_type VARCHAR(50) NOT NULL,
    metric_value INT DEFAULT 1,
    created_at $NOW
)");

$pdo_logs->exec("CREATE TABLE IF NOT EXISTS job_views (
    id $AI,
    job_id INT NOT NULL,
    viewer_user_id INT DEFAULT 0,
    ip_address VARCHAR(50) DEFAULT '',
    created_at $NOW
)");

$pdo_jobs->exec("CREATE TABLE IF NOT EXISTS job_invitations (
    id $AI,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    vendor_id INT NOT NULL,
    status VARCHAR(30) DEFAULT 'pending',
    created_at $NOW
)");

// ── SEED VENDORS ──────────────────────────────────────────────────────────
$count = $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
if ($count == 0) {
    $vendors_data = [
        ['name'=>'Chill & Serve Ghana','category'=>'Chilling Services','logo'=>'img/chill/logo.jpg','cover_photo'=>'img/chill/services.jpg','description'=>'Ghana\'s premier chilling and drinks service. Operating from Airport City, Accra, we supply block ice, cubed ice, refrigerated mobile containers, and professional drinks dispatchers for events of all sizes.','experience'=>8,'packages_pricing'=>json_encode([['name'=>'Royal Ice & Serve Package','price'=>'GHS 6,500','details'=>'Full event service: refrigerated van, unlimited ice, 8 servers, serving trays, ice chests.'],['name'=>'Classic Event Cooling','price'=>'GHS 3,500','details'=>'5 large chilling boxes, 25 blocks of ice, 3 servers, pre-cooling 3 hours before.']]),'location'=>'Airport City, Accra','phone'=>'+233 20 900 1100','whatsapp'=>'233209001100','email'=>'bookings@chillservegh.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/chillservegh']),'rating'=>5.0,'reviews_count'=>142,'verified'=>1,'verification_badge'=>'gold','premium'=>1,'response_time'=>'Within 15 minutes','availability'=>'Available','completed_jobs'=>340,'gallery'=>json_encode(['img/chill/1.jpg','img/chill/2.jpg','img/chill/3.jpg','img/chill/4.jpg','img/chill/5.jpg','img/chill/6.jpg']),'featured'=>1],
        ['name'=>'Jojo Temeng Photography','category'=>'Photography','logo'=>'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=800','description'=>'Elite Accra-based wedding photography studio specializing in luxury editorial portraits and candid emotional reporting of traditional and white weddings.','experience'=>8,'packages_pricing'=>json_encode([['name'=>'Traditional Wedding','price'=>'GHS 6,500','details'=>'Full day, 1 lead + assistant, 300 edited photos, leather photobook.'],['name'=>'Signature Wedding','price'=>'GHS 12,000','details'=>'Traditional & White wedding, 2 photographers, pre-wedding session, 600 edited photos, 2 photobooks.']]),'location'=>'Osu, Accra','phone'=>'+233 24 412 3456','whatsapp'=>'233244123456','email'=>'info@jojotemeng.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/jojotemeng']),'rating'=>4.9,'reviews_count'=>84,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 30 minutes','availability'=>'Available','completed_jobs'=>196,'gallery'=>json_encode(['https://images.unsplash.com/photo-1606800052052-a08af7148866?q=80&w=600','https://images.unsplash.com/photo-1511285560929-80b456fea0bc?q=80&w=600','https://images.unsplash.com/photo-1583939003579-730e3918a45a?q=80&w=600']),'featured'=>0],
        ['name'=>'Cyeq Films','category'=>'Videography','logo'=>'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?q=80&w=800','description'=>'Cinematic wedding films from East Legon. Drone operations, slow-motion, 4K cultural soundscapes.','experience'=>6,'packages_pricing'=>json_encode([['name'=>'Cinematic Highlight','price'=>'GHS 8,000','details'=>'5-min highlight, 1 videographer, drone, 4K, digital delivery.'],['name'=>'Royal Feature','price'=>'GHS 15,000','details'=>'Full documentary + trailer, 3 cameras, drone, crane, audio recording.']]),'location'=>'East Legon, Accra','phone'=>'+233 20 811 9876','whatsapp'=>'233208119876','email'=>'hello@cyeqfilms.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/cyeqfilms']),'rating'=>4.8,'reviews_count'=>62,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 1 hour','availability'=>'Available','completed_jobs'=>145,'gallery'=>json_encode(['https://images.unsplash.com/photo-1485846234645-a62644f84728?q=80&w=600','https://images.unsplash.com/photo-1478737270239-2f02b77fc618?q=80&w=600']),'featured'=>0],
        ['name'=>'Debbie Makeup Artistry','category'=>'Makeup Artists','logo'=>'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?q=80&w=800','description'=>'Premium bridal beauty services in Accra. Long-lasting makeup designed for the tropical climate using top-tier global and local brands.','experience'=>5,'packages_pricing'=>json_encode([['name'=>'Classic Bridal','price'=>'GHS 3,500','details'=>'White wedding makeup, trial session, lashes, touch-up kit.'],['name'=>'Royal Dual Bridal','price'=>'GHS 6,000','details'=>'Traditional + White wedding, bridesmaid touch-ups (up to 3), skincare prep.']]),'location'=>'Airport Residential, Accra','phone'=>'+233 24 555 1212','whatsapp'=>'233245551212','email'=>'debbie@debbiemua.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/debbiemua']),'rating'=>4.7,'reviews_count'=>45,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 15 minutes','availability'=>'Available','completed_jobs'=>98,'gallery'=>json_encode(['https://images.unsplash.com/photo-1512496015851-a90fb38ba796?q=80&w=600','https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?q=80&w=600']),'featured'=>0],
        ['name'=>'PlanIt Ghana','category'=>'Event Planners','logo'=>'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1469371670807-013ccf25f16a?q=80&w=800','description'=>'Multi-award-winning events and wedding planning agency. Full-service coordination from vendor booking to event execution.','experience'=>15,'packages_pricing'=>json_encode([['name'=>'Day Coordination','price'=>'GHS 10,000','details'=>'Timeline oversight, vendor management, setup supervision.'],['name'=>'Full Luxury Planning','price'=>'GHS 25,000','details'=>'Budget advisory, design proposal, vendor procurement, rehearsal, unlimited consultations.']]),'location'=>'Cantonments, Accra','phone'=>'+233 30 278 1234','whatsapp'=>'233302781234','email'=>'info@planitghana.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/planitghana']),'rating'=>5.0,'reviews_count'=>95,'verified'=>1,'verification_badge'=>'gold','premium'=>1,'response_time'=>'Within 1 hour','availability'=>'Available','completed_jobs'=>280,'gallery'=>json_encode(['https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=600','https://images.unsplash.com/photo-1472653431158-6364773b2a56?q=80&w=600']),'featured'=>1],
        ['name'=>'Decor Talk Ghana','category'=>'Decorators','logo'=>'https://images.unsplash.com/photo-1519225495810-7512c696505a?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1519225495810-7512c696505a?q=80&w=800','description'=>'Breathtaking event setups — floral arches, fairy-light ceilings, gold stages, turning any space into a magical dreamscape.','experience'=>7,'packages_pricing'=>json_encode([['name'=>'Chic Intimate Setup','price'=>'GHS 15,000','details'=>'Up to 100 guests: stage, flower walls, centerpieces, fairy lights.'],['name'=>'Grand Ballroom','price'=>'GHS 45,000','details'=>'300+ guests: custom walkways, hanging florals, gold chairs, mood lighting, fireworks.']]),'location'=>'Spintex, Accra','phone'=>'+233 24 812 5566','whatsapp'=>'233248125566','email'=>'hello@decortalk.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/decortalkgh']),'rating'=>4.8,'reviews_count'=>54,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 3 hours','availability'=>'Available','completed_jobs'=>132,'gallery'=>json_encode(['https://images.unsplash.com/photo-1469371670807-013ccf25f16a?q=80&w=600','https://images.unsplash.com/photo-1507504038482-76210062ece1?q=80&w=600']),'featured'=>0],
        ['name'=>'Mensdo Catering Services','category'=>'Caterers','logo'=>'https://images.unsplash.com/photo-1555244162-803834f70033?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1555244162-803834f70033?q=80&w=800','description'=>'Authentic Ghanaian and international menus. Famous for Ghana Jollof, grilled tilapia, palmnut soup, and continental platters.','experience'=>11,'packages_pricing'=>json_encode([['name'=>'Standard Buffet','price'=>'GHS 95 per head','details'=>'Jollof, fried rice, chicken, fish, salad, kelewele, soft drinks. Min 150 guests.'],['name'=>'Executive Multi-Course','price'=>'GHS 180 per head','details'=>'Appetizers, 4 mains, traditional corner, live grill, continental dessert bar. Min 100 guests.']]),'location'=>'Adabraka, Accra','phone'=>'+233 20 223 3445','whatsapp'=>'233202233445','email'=>'mensdocatering@gmail.com','social_links'=>json_encode(['facebook'=>'https://facebook.com/mensdocatering']),'rating'=>4.7,'reviews_count'=>39,'verified'=>0,'verification_badge'=>'grey','premium'=>0,'response_time'=>'Within 5 hours','availability'=>'Available','completed_jobs'=>210,'gallery'=>json_encode(['https://images.unsplash.com/photo-1504674900247-0877df9cc836?q=80&w=600','https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?q=80&w=600']),'featured'=>0],
        ['name'=>'Sweet Spoons Ghana','category'=>'Cake Designers','logo'=>'https://images.unsplash.com/photo-1535141192574-5d4897c13636?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1535141192574-5d4897c13636?q=80&w=800','description'=>'Breathtaking wedding cakes — fondant layers, sugar flowers, Red Velvet, Coconut Cream, and traditional Ghana fruitcake.','experience'=>9,'packages_pricing'=>json_encode([['name'=>'3-Tier Floral Fondant','price'=>'GHS 4,500','details'=>'Handcrafted sugar flowers, 2 flavors, delivery & setup within Accra.'],['name'=>'5-Tier Majestic Gold','price'=>'GHS 9,500','details'=>'Gold leaf accents, premium flavors, custom tasting boxes, cake swing setup.']]),'location'=>'Roman Ridge, Accra','phone'=>'+233 24 111 2233','whatsapp'=>'233241112233','email'=>'ekua@sweetspoons.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/sweetspoonsgh']),'rating'=>4.9,'reviews_count'=>51,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 2 hours','availability'=>'Available','completed_jobs'=>178,'gallery'=>json_encode(['https://images.unsplash.com/photo-1525257831700-183b9b47e6ed?q=80&w=600','https://images.unsplash.com/photo-1558961359-fa8f0c11a28a?q=80&w=600']),'featured'=>0],
        ['name'=>'The Royal Senchi Hotel','category'=>'Event Venues','logo'=>'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=800','description'=>'Ghana\'s premier 4-star luxury resort on the Volta River, Akosombo. Fairytale destination weddings with lush gardens and river views.','experience'=>14,'packages_pricing'=>json_encode([['name'=>'Riverfront Lawn Rental','price'=>'GHS 25,000','details'=>'Full-day garden rental, power hookups, security, backup generator.'],['name'=>'Royal Wedding Package','price'=>'GHS 75,000','details'=>'Lawn + buffet (150 guests), executive suite, boat photoshoot, spa session.']]),'location'=>'Akosombo, Eastern Region','phone'=>'+233 30 340 9100','whatsapp'=>'233303409100','email'=>'reservations@theroyalsenchi.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/theroyalsenchi']),'rating'=>4.9,'reviews_count'=>120,'verified'=>1,'verification_badge'=>'gold','premium'=>1,'response_time'=>'Within 1 hour','availability'=>'Available','completed_jobs'=>320,'gallery'=>json_encode(['https://images.unsplash.com/photo-1571896349842-33c89424de2d?q=80&w=600','https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?q=80&w=600']),'featured'=>1],
        ['name'=>'DJ Vyrusky','category'=>'DJs','logo'=>'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?q=80&w=800','description'=>'Ghana\'s multi-time DJ of the Year. Mixing highlife to afrobeats, amapiano, and pop. Keeps dancefloors absolutely packed.','experience'=>11,'packages_pricing'=>json_encode([['name'=>'Classic Wedding Party','price'=>'GHS 8,000','details'=>'5 hours, professional setup, MC coordination, playlist consultation.'],['name'=>'Premium Experience','price'=>'GHS 15,000','details'=>'Full-day (trad + reception), dual backup laptops, sound engineer, wireless mics, custom mix drop.']]),'location'=>'Airport Hills, Accra','phone'=>'+233 24 998 8776','whatsapp'=>'233249988776','email'=>'bookings@djvyrusky.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/djvyrusky']),'rating'=>5.0,'reviews_count'=>105,'verified'=>1,'verification_badge'=>'gold','premium'=>1,'response_time'=>'Within 45 minutes','availability'=>'Available','completed_jobs'=>290,'gallery'=>json_encode(['https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?q=80&w=600','https://images.unsplash.com/photo-1482440308425-276ad0f28b19?q=80&w=600']),'featured'=>1],
        ['name'=>'Sika Bridal','category'=>'Bridal Shops','logo'=>'https://images.unsplash.com/photo-1594552072238-b8a33785b261?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1594552072238-b8a33785b261?q=80&w=800','description'=>'Premier boutique in Osu offering high-end custom wedding gowns and ready-to-wear international dresses with expert tailoring.','experience'=>10,'packages_pricing'=>json_encode([['name'=>'Custom Ball Gown','price'=>'GHS 12,000','details'=>'Bespoke lace/silk, headpiece, veil, custom fitting.'],['name'=>'Mermaid Rental','price'=>'GHS 4,500','details'=>'Designer gown rental, alterations, professional laundry, veil.']]),'location'=>'Osu, Accra','phone'=>'+233 27 789 4561','whatsapp'=>'233277894561','email'=>'sales@sikabridal.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/sikabridal']),'rating'=>4.9,'reviews_count'=>78,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 2 hours','availability'=>'Available','completed_jobs'=>165,'gallery'=>json_encode(['https://images.unsplash.com/photo-1549417229-aa67d3263c09?q=80&w=600','https://images.unsplash.com/photo-1566174053879-31528523f8ae?q=80&w=600']),'featured'=>0],
        ['name'=>'MC Kabutey','category'=>'MCs','logo'=>'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1516280440614-37939bbacd6a?q=80&w=800','description'=>'The "Elegant MC" — Ghana\'s gold standard for wedding hosting. Coordinates events with humor, cultural respect, and a refined tone.','experience'=>10,'packages_pricing'=>json_encode([['name'=>'Reception Hosting','price'=>'GHS 5,000','details'=>'~4 hours reception, DJ coordination, preliminary meeting.'],['name'=>'Full Wedding (Trad + Reception)','price'=>'GHS 8,500','details'=>'Traditional + white wedding, family interviews, timeline consultation, vendor coordination.']]),'location'=>'East Legon, Accra','phone'=>'+233 20 444 8888','whatsapp'=>'233204448888','email'=>'kabutey@mcghana.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/mckabutey']),'rating'=>4.9,'reviews_count'=>74,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 1 hour','availability'=>'Available','completed_jobs'=>215,'gallery'=>json_encode(['https://images.unsplash.com/photo-1475721027785-f74eccf877e2?q=80&w=600']),'featured'=>0],
        ['name'=>'Flora & Fauna Ghana','category'=>'Florists','logo'=>'https://images.unsplash.com/photo-1561181286-d3fee7d55364?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?q=80&w=800','description'=>'Spectacular luxury bouquets, boutonnieres, and tablescapes. Imported roses, orchids, and tropical Ghanaian flora.','experience'=>6,'packages_pricing'=>json_encode([['name'=>'Bridal Bouquet Package','price'=>'GHS 2,500','details'=>'Premium rose/peony bouquet, 4 bridesmaids posies, 6 boutonnieres.'],['name'=>'Luxury Floral Styling','price'=>'GHS 18,000','details'=>'Full chapel arrangements, aisle runners, head table installation, centerpieces, throw bouquet.']]),'location'=>'Labone, Accra','phone'=>'+233 27 122 3344','whatsapp'=>'233271223344','email'=>'flowers@florafaunagh.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/florafauna.gh']),'rating'=>4.8,'reviews_count'=>42,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 1 hour','availability'=>'Available','completed_jobs'=>88,'gallery'=>json_encode(['https://images.unsplash.com/photo-1561181286-d3fee7d55364?q=80&w=600']),'featured'=>0],
        ['name'=>'Luxo Car Rental','category'=>'Car Rentals','logo'=>'https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?q=80&w=800','description'=>'Ghana\'s finest luxury vehicles — white Mercedes G-Wagons, Range Rovers, vintage Rolls Royce replicas, and black convertibles.','experience'=>7,'packages_pricing'=>json_encode([['name'=>'Mercedes E-Class','price'=>'GHS 3,500/day','details'=>'Chauffeur-driven, 8 hours fuel within Accra, ribbon decoration.'],['name'=>'G-Wagon Elite Entrance','price'=>'GHS 7,500/day','details'=>'White G-Wagon, VIP chauffeur, security escort, 10 hours service.']]),'location'=>'East Legon, Accra','phone'=>'+233 20 112 2233','whatsapp'=>'233201122233','email'=>'bookings@luxorentals.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/luxo.rentals']),'rating'=>4.8,'reviews_count'=>37,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 1 hour','availability'=>'Available','completed_jobs'=>94,'gallery'=>json_encode(['https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=600']),'featured'=>0],
        ['name'=>'Kente & Beads Hub','category'=>'Traditional Marriage Services','logo'=>'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?q=80&w=400','cover_photo'=>'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?q=80&w=800','description'=>'Kumasi\'s premier traditional wedding outfit designer. Handloom Kente cloths and premium Krobo glass bead jewelry.','experience'=>14,'packages_pricing'=>json_encode([['name'=>'His & Hers Kente Cloths','price'=>'GHS 8,500','details'=>'2 custom hand-woven Kente wraps, custom color selection.'],['name'=>'Full Royal Engagement Set','price'=>'GHS 15,000','details'=>'His & hers Kente, traditional wear, brass-beaded jewelry, fans, umbrella.']]),'location'=>'Adum, Kumasi','phone'=>'+233 24 456 7890','whatsapp'=>'233244567890','email'=>'order@kentehub.com','social_links'=>json_encode(['instagram'=>'https://instagram.com/kentebeadshub']),'rating'=>4.9,'reviews_count'=>64,'verified'=>1,'verification_badge'=>'blue','premium'=>0,'response_time'=>'Within 1 hour','availability'=>'Available','completed_jobs'=>148,'gallery'=>json_encode(['https://images.unsplash.com/photo-1596755094514-f87e34085b2c?q=80&w=600']),'featured'=>0],
    ];

    $ins = $pdo->prepare("INSERT INTO vendors (name,category,logo,cover_photo,description,experience,packages_pricing,location,phone,whatsapp,email,social_links,rating,reviews_count,verified,verification_badge,premium,response_time,availability,completed_jobs,gallery,featured,verification_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($vendors_data as $v) {
        $v_status = ($v['verified'] == 1) ? 'verified' : 'pending';
        $ins->execute([$v['name'],$v['category'],$v['logo'],$v['cover_photo'],$v['description'],$v['experience'],$v['packages_pricing'],$v['location'],$v['phone'],$v['whatsapp'],$v['email'],$v['social_links'],$v['rating'],$v['reviews_count'],$v['verified'],$v['verification_badge'],$v['premium'],$v['response_time'],$v['availability'],$v['completed_jobs'],$v['gallery'],$v['featured'],$v_status]);
    }
}

// ── SEED FAQS ─────────────────────────────────────────────────────────────
try {
    $faq_count = $pdo->query("SELECT COUNT(*) FROM faqs")->fetchColumn();
    if ($faq_count == 0) {
        $faqs_data = [
            ['category' => 'General', 'question' => 'What is Ohati?', 'answer' => 'Ohati is an all-in-one premium event vendor marketplace in Ghana. It helps couples, corporate planners, and event hosts find, compare, and book verified service providers including caterers, photographers, videographers, DJs, decorators, bridal shops, and makeup artists.'],
            ['category' => 'Verification & Badges', 'question' => 'What do the gold, blue, and grey verification badges mean?', 'answer' => 'Verification badges reflect the vetting level of a vendor: <strong>Gold (Premium Trust)</strong> indicates a business with verified company registration, tax status, and a proven track record on the platform. <strong>Blue (Identity Verified)</strong> means the vendor\'s personal ID (such as a Ghana Card) has been successfully verified. <strong>Grey (Unverified)</strong> represents newly registered vendors whose credentials are still pending review.'],
            ['category' => 'Verification & Badges', 'question' => 'How do I submit my vendor account for verification?', 'answer' => 'Vendors can submit KYC details during onboarding or from their Dashboard Profile settings. You must upload a high-resolution photo of your Ghana Card (front and back) and a selfie holding the card. The administration team reviews submissions within 24 to 72 hours.'],
            ['category' => 'Bookings & Escrow', 'question' => 'How does the Ohati escrow payment system work?', 'answer' => 'To protect both parties, clients pay event fees directly to Ohati\'s secure direct payment vault. Ohati holds the deposit and final balances, only releasing the funds to the vendor after the client confirms that specified booking milestones are met or the event is successfully completed.'],
            ['category' => 'Payments & Withdrawals', 'question' => 'What payment methods does Ohati support?', 'answer' => 'Ohati integrates with Paystack, allowing clients to pay securely using Mobile Money (MTN MoMo, Telecel Cash, AT Money) and credit/debit cards (Visa, Mastercard).'],
            ['category' => 'Payments & Withdrawals', 'question' => 'How do vendors withdraw their earnings?', 'answer' => 'Once booking milestones are signed off by the client and funds are released from escrow, vendors can request a payout. Payouts are transferred directly to the vendor\'s registered bank account or mobile money number within 24 to 48 hours.'],
            ['category' => 'Bookings & Escrow', 'question' => 'Is my deposit refundable if the vendor cancels?', 'answer' => 'Yes. Since Ohati holds your deposit securely in escrow, if a vendor cancels the booking or fails to fulfill their contract, the escrowed deposit is fully refunded back to the client\'s original payment method.'],
            ['category' => 'Bookings & Escrow', 'question' => 'Can I negotiate package pricing with a vendor?', 'answer' => 'Absolutely! Clients can initiate a secure chat with any vendor to discuss custom requirements. The vendor can then issue a negotiated price directly within the active booking details for the client to approve and pay.'],
            ['category' => 'Smart Event Planner', 'question' => 'How does the Smart Event Planner work?', 'answer' => 'The Smart Event Planner helps you stay organized. It generates a dynamic milestone checklist based on your event date and budget. You can track completed tasks, manage cost allocations, and monitor outstanding vendor balances in real-time.'],
            ['category' => 'General', 'question' => 'How do ratings and reviews work on the platform?', 'answer' => 'After an event is completed, clients can rate the vendor (1 to 5 stars) and write a review. Reviews associated with completed platform bookings receive a "Verified Booking" badge to guarantee authenticity and prevent fake feedback.'],
            ['category' => 'General', 'question' => 'How can I compare multiple event vendors side-by-side?', 'answer' => 'Use the Compare tool! When browsing vendor profiles, click the "Add to Compare" button. Then, visit the Compare tab to view pricing, experience levels, ratings, and locations in a clear, side-by-side matrix.'],
            ['category' => 'General', 'question' => 'Can I message vendors directly on Ohati?', 'answer' => 'Yes. Ohati has an integrated real-time chat system. Click the "Chat" button on any vendor\'s profile or detail card to start a conversation, share details, and ask questions before placing a booking.'],
            ['category' => 'Vendor Accounts', 'question' => 'What is the automated chat response feature for vendors?', 'answer' => 'Vendors can write a custom automated reply in their Auto-Response settings. If a client messages them while they are away or offline, this automated greeting is sent instantly to keep the customer engaged.'],
            ['category' => 'Vendor Accounts', 'question' => 'How do vendors launch advertisement campaigns?', 'answer' => 'Vendors can boost their profile visibility by launching an Ad Campaign from their dashboard. Select a duration (1 to 365 days) and target location, pay securely via Paystack, and the ad banner will automatically appear in featured slots on the homepage.'],
            ['category' => 'Vendor Accounts', 'question' => 'How are vendor locations pinned on the map?', 'answer' => 'During vendor registration, the onboarding wizard uses GPS coordinates to pin the exact business location. Clients can then filter and find vendors within their specific region or radius.'],
            ['category' => 'Vendor Accounts', 'question' => 'What are the platform fees for booking transactions?', 'answer' => 'Ohati charges a flat 10% commission on completed bookings to cover secure payment verification, platform hosting, payment gateways, and dispute resolution. Vendors retain 90% of their booking value.'],
            ['category' => 'General', 'question' => 'What happens if a vendor is unresponsive?', 'answer' => 'Each vendor has a visible "average response time" badge. If a vendor fails to respond to a booking inquiry or chat message within 72 hours, the inquiry expires, and Ohati support can assist in finding an alternative vendor.'],
            ['category' => 'General', 'question' => 'How do I reset my account password?', 'answer' => 'If you cannot log in, click the "Forgot Password" link on the Sign In modal. Enter your email address or phone number to receive a temporary recovery link or OTP code to reset it securely.'],
            ['category' => 'Vendor Accounts', 'question' => 'Can I list team members on my vendor profile?', 'answer' => 'Yes! Verified vendors can go to their Edit Profile page and add team members, including their roles, names, and photos, to highlight their staff and build client trust.'],
            ['category' => 'Bookings & Escrow', 'question' => 'How do we sign the contract on Ohati?', 'answer' => 'Once booking details are agreed, a digital contract is generated on the booking page. Both client and vendor must electronically sign the contract. Payments are only requested and held in escrow once the contract is signed.']
        ];
        $ins = $pdo->prepare("INSERT INTO faqs (category, question, answer) VALUES (?, ?, ?)");
        foreach ($faqs_data as $faq) {
            $ins->execute([$faq['category'], $faq['question'], $faq['answer']]);
        }
    }
} catch (Exception $e) {}

// Auto-update live database vendor logos to authentic business category imagery
try {
    $category_logos_update = [
        'Photography' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?q=80&w=400',
        'Videography' => 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?q=80&w=400',
        'Makeup Artists' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=400',
        'Event Planners' => 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=400',
        'Decorators' => 'https://images.unsplash.com/photo-1519225495810-7512c696505a?q=80&w=400',
        'Caterers' => 'https://images.unsplash.com/photo-1555244162-803834f70033?q=80&w=400',
        'Cake Designers' => 'https://images.unsplash.com/photo-1535141192574-5d4897c13636?q=80&w=400',
        'Event Venues' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=400',
        'DJs' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?q=80&w=400',
        'Bridal Shops' => 'https://images.unsplash.com/photo-1594552072238-b8a33785b261?q=80&w=400',
        'MCs' => 'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?q=80&w=400',
        'Florists' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?q=80&w=400',
        'Car Rentals' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=400',
        'Traditional Marriage Services' => 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?q=80&w=400'
    ];
    foreach ($category_logos_update as $cat => $logo_url) {
        $stmt = $pdo->prepare("UPDATE vendors SET logo = ? WHERE category = ? AND name NOT LIKE '%Chill & Serve%'");
        $stmt->execute([$logo_url, $cat]);
    }
    
    // Deduplicate vendors by name
    $pdo->exec("DELETE FROM vendors WHERE id NOT IN (SELECT min_id FROM (SELECT MIN(id) as min_id FROM vendors GROUP BY name) as t)");

    // Clean user table avatars
    // Clean user table avatars
    $user_svg = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";
    $pdo->prepare("UPDATE users SET avatar = ? WHERE avatar LIKE '%unsplash.com%' OR avatar LIKE '%photo-%' OR avatar = '' OR avatar IS NULL")->execute([$user_svg]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS call_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        caller_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status VARCHAR(30) DEFAULT 'ringing',
        call_type VARCHAR(20) DEFAULT 'voice',
        sdp_offer TEXT NULL,
        sdp_answer TEXT NULL,
        ice_candidates TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}