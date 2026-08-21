<?php
// vendor-dash.php - Ohati Standalone Vendor Dashboard Page
session_start();

// Check Maintenance Mode
require_once __DIR__ . '/db.php';
try {
    $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = 'maintenance_mode'");
    $stmt->execute();
    $maint = $stmt->fetchColumn();
    if ($maint === '1' && (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin')) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {}

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$active_role = $_SESSION['user']['active_role'] ?? $_SESSION['user']['role'];
if ($active_role !== 'vendor') {
    header('Location: index.php');
    exit;
}
if (isset($_SESSION['user']['vendor_onboarding_completed']) && $_SESSION['user']['vendor_onboarding_completed'] === false) {
    header('Location: vendor-register.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - Ohati</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- App Header -->
        <header class="app-header">
            <div class="header-logo">
                <img src="img/logo black transparent small.png" alt="Ohati Logo" class="header-logo-img" id="header-logo-img">
            </div>
            <div class="header-right">
                <button class="header-icon-btn" id="theme-toggle-btn"><i class="fa-solid fa-moon" id="theme-icon"></i></button>
                <div class="header-user">
                    <img class="header-avatar" src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" alt="" id="header-avatar" onclick="const isNative = (typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) || window.location.protocol === 'file:' || window.location.protocol === 'capacitor:'; window.location.href = isNative ? 'index.html' : 'index.php';">
                </div>
            </div>
        </header>

        <!-- App Viewport -->
        <main class="app-viewport" id="app-viewport">
            <div class="dashboard-desktop-container">
                <div class="flex-between mb-24">
                    <h3>Vendor Dashboard</h3>
                    <button class="btn btn-outline btn-sm" onclick="handleLogout()"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                </div>

                <div class="dashboard-grid">
                    <!-- Left Column: KYC & Stats -->
                    <div class="dashboard-col-left">
                        <!-- KYC Review Status Card -->
                        <div class="card mb-16" style="padding: 16px; border-left: 4px solid var(--accent);" id="kyc-card">
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                                <i class="fa-solid fa-shield-halved" style="font-size:1.5rem; color:var(--accent);" id="kyc-icon"></i>
                                <div>
                                    <h4 style="font-size:0.9rem; margin:0;">Identity Verification (KYC)</h4>
                                    <div style="font-size:0.75rem; color:var(--gray-500); text-transform:capitalize;" id="kyc-status-txt">Status: Pending Verification</div>
                                </div>
                            </div>
                            <p style="font-size:0.75rem; color:var(--gray-600); line-height:1.4; margin: 8px 0 8px 0;" id="kyc-desc-txt">
                                Your business profile and Ghana Card validation are currently under review by Ohati administrators.
                            </p>
                            <button class="btn btn-outline btn-xs" style="font-size:0.7rem; padding:4px 10px;" onclick="openKYCDocModal()" id="kyc-btn"><i class="fa-solid fa-eye"></i> View KYC Document Status</button>
                        </div>

                        <!-- KYC Document History Modal -->
                        <div id="kyc-doc-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
                            <div class="card" style="width:90%; max-width:480px; padding:20px; background:#fff; border-radius:12px; max-height:90vh; overflow-y:auto; position:relative;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--gray-200); padding-bottom:10px;">
                                    <h4 style="margin:0; font-size:1rem; font-weight:700;"><i class="fa-solid fa-file-contract text-primary"></i> KYC Document History</h4>
                                    <button class="btn btn-ghost btn-sm" onclick="closeKYCDocModal()" style="font-size:1.2rem; cursor:pointer;">&times;</button>
                                </div>
                                <div id="kyc-modal-body">
                                    <div class="full-spinner-wrap"><div class="spinner"></div></div>
                                </div>
                            </div>
                        </div>

                        <!-- Real-Time Analytics & Pro Date Filter Section -->
                        <div class="card mb-16" style="padding:16px; border-radius:12px; background:var(--card-bg, #fff);">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px; border-bottom:1px solid var(--gray-200); padding-bottom:10px;">
                                <div>
                                    <h4 style="margin:0; font-size:0.95rem; font-weight:800; color:var(--primary); display:flex; align-items:center; gap:6px;">
                                        <i class="fa-solid fa-chart-line" style="color:var(--accent);"></i> Real-Time Business Performance
                                    </h4>
                                    <div style="font-size:0.72rem; color:var(--gray-500);">Live profile views, chat inquiries, and bookings</div>
                                </div>

                                <!-- Pro Date Filter Buttons -->
                                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                    <button class="btn btn-xs btn-outline date-filter-btn" data-period="today" onclick="filterVendorStats('today', this)">Today</button>
                                    <button class="btn btn-xs btn-primary date-filter-btn active" data-period="7days" onclick="filterVendorStats('7days', this)">7 Days</button>
                                    <button class="btn btn-xs btn-outline date-filter-btn" data-period="30days" onclick="filterVendorStats('30days', this)">30 Days</button>
                                    <button class="btn btn-xs btn-outline date-filter-btn" data-period="this_month" onclick="filterVendorStats('this_month', this)">This Month</button>
                                    <button class="btn btn-xs btn-outline date-filter-btn" onclick="toggleCustomDateInputs()">
                                        <i class="fa-solid fa-calendar-days"></i> Custom Range
                                    </button>
                                </div>
                            </div>

                            <!-- Custom Date Range Bar -->
                            <div id="custom-date-bar" style="display:none; background:var(--gray-50); padding:10px; border-radius:8px; border:1px solid var(--gray-200); margin-bottom:14px;">
                                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <label style="font-size:0.72rem; font-weight:700; color:var(--gray-600);">From:</label>
                                        <input type="date" id="stats-start-date" class="form-input" style="font-size:0.75rem; padding:4px 8px;">
                                    </div>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <label style="font-size:0.72rem; font-weight:700; color:var(--gray-600);">To:</label>
                                        <input type="date" id="stats-end-date" class="form-input" style="font-size:0.75rem; padding:4px 8px;">
                                    </div>
                                    <button class="btn btn-xs btn-primary" onclick="applyCustomDateStats()">
                                        <i class="fa-solid fa-filter"></i> Apply Filter
                                    </button>
                                </div>
                            </div>

                            <!-- 3 Real-Time Analytics Stat Cards -->
                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                <div class="vd-stat-card text-center" style="padding:12px; border-radius:10px; background:var(--gray-50); border:1px solid var(--gray-200);">
                                    <div style="font-size:0.7rem; color:var(--gray-600); font-weight:700; text-transform:uppercase;"><i class="fa-solid fa-eye" style="color:var(--accent);"></i> Views</div>
                                    <div class="vd-stat-value" id="vd-stat-views" style="font-size:1.3rem; font-weight:800; color:var(--primary); margin-top:2px;">--</div>
                                    <div style="font-size:0.65rem; color:var(--gray-500);">Profile Impressions</div>
                                </div>

                                <div class="vd-stat-card text-center" style="padding:12px; border-radius:10px; background:var(--gray-50); border:1px solid var(--gray-200);">
                                    <div style="font-size:0.7rem; color:var(--gray-600); font-weight:700; text-transform:uppercase;"><i class="fa-solid fa-comments" style="color:#3B82F6;"></i> Chats</div>
                                    <div class="vd-stat-value" id="vd-stat-chats" style="font-size:1.3rem; font-weight:800; color:var(--primary); margin-top:2px;">--</div>
                                    <div style="font-size:0.65rem; color:var(--gray-500);">Client Inquiries</div>
                                </div>

                                <div class="vd-stat-card text-center" style="padding:12px; border-radius:10px; background:var(--gray-50); border:1px solid var(--gray-200);">
                                    <div style="font-size:0.7rem; color:var(--gray-600); font-weight:700; text-transform:uppercase;"><i class="fa-solid fa-calendar-check" style="color:#10B981;"></i> Bookings</div>
                                    <div class="vd-stat-value" id="vd-stat-bookings" style="font-size:1.3rem; font-weight:800; color:var(--primary); margin-top:2px;">--</div>
                                    <div style="font-size:0.65rem; color:var(--gray-500);">Requests</div>
                                </div>
                            </div>

                            <!-- Followers & Reviews Summary Bar -->
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding-top:10px; border-top:1px solid var(--gray-100); font-size:0.75rem;">
                                <div style="cursor:pointer;" onclick="openFollowersModal()" title="Click to inspect business followers">
                                    <i class="fa-solid fa-users" style="color:var(--accent);"></i> Followers: <strong id="vd-followers-count" style="color:var(--accent);">0</strong>
                                </div>
                                <div>
                                    <i class="fa-solid fa-star" style="color:#FFD700;"></i> Rating: <strong id="vd-rating-val">5.0</strong> <span style="color:var(--gray-500);" id="vd-reviews-count">(0 reviews)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Followers & Following Modal -->
                        <div id="followers-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
                            <div class="card" style="width:90%; max-width:520px; padding:20px; background:var(--card-bg, #fff); border-radius:16px; max-height:85vh; overflow-y:auto; position:relative;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--gray-200); padding-bottom:10px;">
                                    <h4 style="margin:0; font-size:1.1rem; font-weight:700;"><i class="fa-solid fa-users text-primary"></i> Followers & Following</h4>
                                    <button class="btn btn-ghost btn-sm" onclick="closeFollowersModal()" style="font-size:1.2rem; cursor:pointer;">&times;</button>
                                </div>
                                <div style="display:flex; gap:10px; margin-bottom:16px;">
                                    <button class="btn btn-primary btn-sm" id="btn-tab-followers" onclick="switchFollowersTab('followers')" style="flex:1;">Followers (<span id="cnt-followers">0</span>)</button>
                                    <button class="btn btn-outline btn-sm" id="btn-tab-following" onclick="switchFollowersTab('following')" style="flex:1;">Following (<span id="cnt-following">0</span>)</button>
                                </div>
                                <div id="followers-modal-body">
                                    <div class="full-spinner-wrap"><div class="spinner"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Bookings -->
                    <div class="dashboard-col-right">
                        <div class="section-header" style="margin-top:0;">
                            <h3 class="section-title">Customer Bookings</h3>
                        </div>
                        <div id="vendor-bookings-list">
                            <!-- Loaded dynamically -->
                            <div class="skeleton-card mb-12" style="padding:14px;">
                                <div class="skeleton skeleton-title" style="width:50%;"></div>
                                <div class="skeleton skeleton-text" style="width:75%; margin-top:6px;"></div>
                                <div class="skeleton skeleton-text" style="width:35%; margin-top:4px;"></div>
                            </div>
                            <div class="skeleton-card mb-12" style="padding:14px;">
                                <div class="skeleton skeleton-title" style="width:60%;"></div>
                                <div class="skeleton skeleton-text" style="width:70%; margin-top:6px;"></div>
                                <div class="skeleton skeleton-text" style="width:40%; margin-top:4px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="js/utils.js"></script>
    <script src="js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize theme from localStorage
            const savedTheme = localStorage.getItem('theme');
            const body = document.body;
            const themeIcon = document.getElementById('theme-icon');
            const headerLogo = document.getElementById('header-logo-img');
            
            if (savedTheme === 'dark') {
                body.classList.add('dark-theme');
                if (themeIcon) themeIcon.className = 'fa-solid fa-sun';
                if (headerLogo) headerLogo.src = 'img/logo white transparent small.png';
            } else {
                body.classList.remove('dark-theme');
                if (themeIcon) themeIcon.className = 'fa-solid fa-moon';
                if (headerLogo) headerLogo.src = 'img/logo black transparent small.png';
            }

            // Load user profile / KYC status
            API.getSession().then(res => {
                if (res.user) {
                    window._currentUser = res.user;
                    const st = res.user.kyc_status || 'not_started';
                    document.getElementById('kyc-status-txt').textContent = 'Status: ' + st.replace('_', ' ');
                    
                    const descEl = document.getElementById('kyc-desc-txt');
                    const cardEl = document.getElementById('kyc-card');
                    const btnEl = document.getElementById('kyc-btn');
                    if (st === 'verified') {
                        if (cardEl) cardEl.style.borderLeftColor = '#10B981';
                        if (descEl) descEl.innerHTML = '<strong>Verified Vendor!</strong> Your Ghana Card identity and business credentials have been fully verified. Blue Badge seal of trust active.';
                        if (btnEl) btnEl.innerHTML = '<i class="fa-solid fa-eye"></i> View Verified KYC Record';
                    } else if (st === 'pending_verification') {
                        if (cardEl) cardEl.style.borderLeftColor = '#F59E0B';
                        if (descEl) descEl.innerHTML = '<strong>Submission Under Review:</strong> Your Ghana Card identity documents have been submitted and are currently being reviewed by Ohati administrators.';
                        if (btnEl) btnEl.innerHTML = '<i class="fa-solid fa-eye"></i> View Submitted KYC Documents';
                    } else if (st === 'rejected') {
                        if (cardEl) cardEl.style.borderLeftColor = '#EF4444';
                        if (descEl) descEl.innerHTML = '<span class="text-error">Verification Declined:</span> Additional document verification is required. Please inspect document details and resubmit.';
                        if (btnEl) btnEl.innerHTML = '<i class="fa-solid fa-upload"></i> Resubmit KYC ID Documents';
                    } else {
                        if (cardEl) cardEl.style.borderLeftColor = '#3B82F6';
                        if (descEl) descEl.innerHTML = 'Identity Verification (KYC) helps establish client trust on the platform. Verify your Ghana Card ID to unlock your Blue Badge.';
                        if (btnEl) btnEl.innerHTML = '<i class="fa-solid fa-id-card"></i> Submit Identity Verification (KYC)';
                    }

                    const avatar = document.getElementById('header-avatar');
                    if (avatar && res.user.avatar) avatar.src = res.user.avatar;
                }
            });

            // Load Real-Time Vendor Analytics (Default: 7 Days)
            loadVendorRealtimeAnalytics({ period: '7days' });

            // Load bookings
            API.getBookings().then(bookings => {
                const list = document.getElementById('vendor-bookings-list');
                if (!bookings || bookings.length === 0) {
                    list.innerHTML = `<p class="text-sm text-muted text-center" style="padding:20px;">No bookings found yet.</p>`;
                    return;
                }
                list.innerHTML = bookings.map(b => `
                    <div class="booking-card" onclick="openBookingDetailsModal(${b.id})" style="padding:14px; margin-bottom:12px; cursor:pointer; border-radius:12px; transition:transform 0.2s ease;">
                        <div class="booking-card-header" style="margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div class="booking-vendor-name" style="font-weight:700; font-size:0.92rem;">${b.user_name}</div>
                                <div class="booking-vendor-cat" style="font-size:0.75rem; color:var(--gray-500);">${b.event_type || 'Event'} — ${b.package_name || 'Package'}</div>
                            </div>
                            <span class="booking-status ${b.status === 'Inquiry' ? 'status-pending' : 'status-confirmed'}">${b.status}</span>
                        </div>
                        <div style="font-size:0.75rem; color:var(--gray-600); display:flex; flex-direction:column; gap:4px; padding-top:6px; border-top:1px dashed var(--gray-200);">
                            <div style="display:flex; justify-content:space-between;">
                                <span><i class="fa-solid fa-phone" style="color:var(--primary);"></i> Phone: <strong>${b.user_phone}</strong></span>
                                <span><i class="fa-solid fa-tag" style="color:var(--primary);"></i> <strong>GH₵ ${parseFloat(b.negotiated_price || b.price || 0).toLocaleString(undefined,{minimumFractionDigits:2})}</strong></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-top:2px;">
                                <span><i class="fa-solid fa-calendar-day" style="color:var(--primary);"></i> Event: <strong>${formatFriendlyDate(b.event_date)}</strong></span>
                                <span style="font-size:0.68rem; color:var(--gray-500);"><i class="fa-regular fa-clock"></i> ${formatRelativeTime(b.created_at)}</span>
                            </div>
                        </div>
                    </div>
                `).join('');
            });

            // Theme toggle
            const themeBtn = document.getElementById('theme-toggle-btn');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    const body = document.body;
                    const icon = document.getElementById('theme-icon');
                    const logo = document.getElementById('header-logo-img');

                    if (body.classList.contains('dark-theme')) {
                        body.classList.remove('dark-theme');
                        if (icon) icon.className = 'fa-solid fa-moon';
                        if (logo) logo.src = 'img/logo black transparent small.png';
                        localStorage.setItem('theme', 'light');
                    } else {
                        body.classList.add('dark-theme');
                        if (icon) icon.className = 'fa-solid fa-sun';
                        if (logo) logo.src = 'img/logo white transparent small.png';
                        localStorage.setItem('theme', 'dark');
                    }

                    if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.StatusBar) {
                        try {
                            const isDark = body.classList.contains('dark-theme');
                            window.Capacitor.Plugins.StatusBar.setStyle({ style: isDark ? 'DARK' : 'LIGHT' });
                            window.Capacitor.Plugins.StatusBar.setBackgroundColor({ color: isDark ? '#0F1923' : '#FFFFFF' });
                        } catch(e) {}
                    }
                });
            }
        });

        function openKYCDocModal() {
            const modal = document.getElementById('kyc-doc-modal');
            const body = document.getElementById('kyc-modal-body');
            if (!modal || !body) return;

            modal.style.display = 'flex';
            const u = window._currentUser || {};
            const idType = u.kyc_id_type || 'Ghana Card';
            const submittedAt = u.kyc_submitted_at || 'Recently';
            const reviewedAt = u.kyc_reviewed_at || 'Pending';
            const status = u.kyc_status || 'not_started';
            const frontImg = u.kyc_id_front || '';
            const selfieImg = u.kyc_selfie || '';

            let badgeHtml = '<span class="badge" style="background:rgba(245,158,11,0.15); color:#F59E0B;">Pending Verification</span>';
            if (status === 'verified') badgeHtml = '<span class="badge" style="background:rgba(16,185,129,0.15); color:#10B981;">Identity Verified (Blue Badge)</span>';
            else if (status === 'rejected') badgeHtml = '<span class="badge" style="background:rgba(239,68,68,0.15); color:#EF4444;">Verification Declined</span>';

            body.innerHTML = `
                <div style="font-size:0.8rem; color:var(--gray-700); display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong>Current Status:</strong> ${badgeHtml}
                    </div>
                    <div><strong>ID Document Type:</strong> ${idType}</div>
                    <div><strong>Submission Timestamp:</strong> ${submittedAt}</div>
                    <div><strong>Last Reviewed:</strong> ${reviewedAt}</div>
                    
                    <div style="margin-top:12px; font-weight:700; color:var(--primary);">Submitted Identification Documents:</div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:6px;">
                        <div>
                            <div style="font-size:0.7rem; color:var(--gray-500); margin-bottom:4px;">ID Front Photo</div>
                            ${frontImg ? `<img src="${frontImg}" style="width:100%; height:120px; object-fit:cover; border-radius:8px; border:1px solid #ddd;" onclick="window.open(this.src)">` : '<div style="height:100px; background:#f3f4f6; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#888;">No ID Image</div>'}
                        </div>
                        <div>
                            <div style="font-size:0.7rem; color:var(--gray-500); margin-bottom:4px;">Verification Selfie</div>
                            ${selfieImg ? `<img src="${selfieImg}" style="width:100%; height:120px; object-fit:cover; border-radius:8px; border:1px solid #ddd;" onclick="window.open(this.src)">` : '<div style="height:100px; background:#f3f4f6; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#888;">No Selfie</div>'}
                        </div>
                    </div>
                </div>
            `;
        }

        function closeKYCDocModal() {
            const modal = document.getElementById('kyc-doc-modal');
            if (modal) modal.style.display = 'none';
        }

        let currentFollowersData = { followers: [], following: [] };

        function loadFollowersCount() {
            if (typeof API === 'undefined' || !API.getVendorFollowers) return;
            API.getVendorFollowers().then(res => {
                const count = res.count || 0;
                currentFollowersData.followers = res.followers || [];
                const el = document.getElementById('vd-followers-count');
                const cntEl = document.getElementById('cnt-followers');
                if (el) el.textContent = count;
                if (cntEl) cntEl.textContent = count;
            });
            API.getVendorFollowing().then(res => {
                currentFollowersData.following = res.following || [];
                const cntEl = document.getElementById('cnt-following');
                if (cntEl) cntEl.textContent = res.count || 0;
            });
        }

        function openFollowersModal() {
            const modal = document.getElementById('followers-modal');
            if (modal) modal.style.display = 'flex';
            switchFollowersTab('followers');
        }

        function closeFollowersModal() {
            const modal = document.getElementById('followers-modal');
            if (modal) modal.style.display = 'none';
        }

        function switchFollowersTab(tab) {
            const btnF = document.getElementById('btn-tab-followers');
            const btnFg = document.getElementById('btn-tab-following');
            const body = document.getElementById('followers-modal-body');
            if (!body) return;

            if (tab === 'followers') {
                if (btnF) btnF.className = 'btn btn-primary btn-sm';
                if (btnFg) btnFg.className = 'btn btn-outline btn-sm';

                if (currentFollowersData.followers.length === 0) {
                    body.innerHTML = '<div style="text-align:center; padding:30px 10px; color:var(--gray-500);"><i class="fa-solid fa-user-plus" style="font-size:2rem; margin-bottom:8px;"></i><p>No followers yet. Share your vendor profile to gain followers!</p></div>';
                } else {
                    body.innerHTML = currentFollowersData.followers.map(u => `
                        <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--gray-100);">
                            <img src="${u.avatar || (window.DEFAULT_USER_AVATAR || 'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><circle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%23081729\'/><circle cx=\'50\' cy=\'38\' r=\'18\' fill=\'%23FFFFFF\'/><path d=\'M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z\' fill=\'%23FFFFFF\'/></svg>')}" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                            <div style="flex:1;">
                                <div style="font-weight:700; font-size:0.9rem; color:var(--gray-900);">${u.name}</div>
                                <div style="font-size:0.75rem; color:var(--gray-500);">${u.email || u.phone || 'Ohati Member'}</div>
                            </div>
                            <span class="badge badge-success" style="font-size:0.7rem;">Follower</span>
                        </div>
                    `).join('');
                }
            } else {
                if (btnF) btnF.className = 'btn btn-outline btn-sm';
                if (btnFg) btnFg.className = 'btn btn-primary btn-sm';

                if (currentFollowersData.following.length === 0) {
                    body.innerHTML = '<div style="text-align:center; padding:30px 10px; color:var(--gray-500);"><i class="fa-solid fa-store" style="font-size:2rem; margin-bottom:8px;"></i><p>You are not following any vendors yet.</p></div>';
                } else {
                    body.innerHTML = currentFollowersData.following.map(v => `
                        <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--gray-100);">
                            <img src="${v.logo || (window.DEFAULT_USER_AVATAR || 'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><circle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%23081729\'/><circle cx=\'50\' cy=\'38\' r=\'18\' fill=\'%23FFFFFF\'/><path d=\'M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z\' fill=\'%23FFFFFF\'/></svg>')}" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                            <div style="flex:1;">
                                <div style="font-weight:700; font-size:0.9rem; color:var(--gray-900);">${v.name}</div>
                                <div style="font-size:0.75rem; color:var(--gray-500);">${v.category || 'Vendor'}</div>
                            </div>
                            <a href="index.php#vendor-details-${v.id}" class="btn btn-outline btn-xs">View Profile</a>
                        </div>
                    `).join('');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadFollowersCount();
        });

        function handleLogout() {
            API.logout().then(() => {
                const isNative = (typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) || window.location.protocol === 'file:' || window.location.protocol === 'capacitor:';
                window.location.href = isNative ? 'index.html' : 'login.php';
            });
        }

        window.filterVendorStats = function(period, btnEl) {
            document.querySelectorAll('.date-filter-btn').forEach(b => {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-outline');
            });
            if (btnEl) {
                btnEl.classList.remove('btn-outline');
                btnEl.classList.add('btn-primary', 'active');
            }
            const customBar = document.getElementById('custom-date-bar');
            if (customBar) customBar.style.display = 'none';

            loadVendorRealtimeAnalytics({ period: period });
        };

        window.toggleCustomDateInputs = function() {
            const customBar = document.getElementById('custom-date-bar');
            if (customBar) {
                customBar.style.display = customBar.style.display === 'none' ? 'block' : 'none';
            }
        };

        window.applyCustomDateStats = function() {
            const sDate = document.getElementById('stats-start-date')?.value;
            const eDate = document.getElementById('stats-end-date')?.value;

            if (!sDate || !eDate) {
                if (typeof showPushNotification === 'function') {
                    showPushNotification('Invalid Date Range', 'Please select both start and end dates.');
                } else {
                    alert('Please select both start and end dates.');
                }
                return;
            }

            loadVendorRealtimeAnalytics({ period: 'custom', start_date: sDate, end_date: eDate });
        };

        window.loadVendorRealtimeAnalytics = function(params = {}) {
            if (!params.period) params.period = '7days';
            const query = new URLSearchParams(params).toString();
            
            API.get(`get_vendor_analytics?${query}`).then(res => {
                if (res.success && res.stats) {
                    const s = res.stats;
                    const viewsEl = document.getElementById('vd-stat-views');
                    const chatsEl = document.getElementById('vd-stat-chats');
                    const bookingsEl = document.getElementById('vd-stat-bookings');
                    const revenueEl = document.getElementById('vd-stat-revenue');
                    const followersEl = document.getElementById('vd-followers-count');
                    const cntFollowersModal = document.getElementById('cnt-followers');
                    const ratingVal = document.getElementById('vd-rating-val');
                    const reviewsCount = document.getElementById('vd-reviews-count');

                    if (viewsEl) viewsEl.textContent = Number(s.views).toLocaleString();
                    if (chatsEl) chatsEl.textContent = Number(s.chats).toLocaleString();
                    if (bookingsEl) bookingsEl.textContent = Number(s.bookings).toLocaleString();
                    if (revenueEl) revenueEl.textContent = 'GH₵ ' + Number(s.revenue).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                    if (followersEl) followersEl.textContent = Number(s.followers).toLocaleString();
                    if (cntFollowersModal) cntFollowersModal.textContent = Number(s.followers).toLocaleString();
                    if (ratingVal) ratingVal.textContent = s.rating || '5.0';
                    if (reviewsCount) reviewsCount.textContent = `(${s.reviews_count} reviews)`;
                }
            }).catch(err => {});
        };
    </script>
</body>
</html>
