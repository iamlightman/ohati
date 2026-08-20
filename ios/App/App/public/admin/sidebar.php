<?php
// admin/sidebar.php — Self-Contained Unified Admin Navigation Component
if (!isset($pending_kyc) && isset($pdo)) {
    try {
        $pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE (kyc_status = 'pending_verification' OR kyc_status = 'pending') AND (kyc_id_front != '' OR kyc_selfie != '' OR kyc_id_back != '')")->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $pending_kyc = 0;
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    .admin-layout {
        display: flex !important;
        min-height: 100vh !important;
        background: #F3F4F6 !important;
    }
    .admin-sidebar {
        width: 260px !important;
        background: #111827 !important;
        color: #ffffff !important;
        flex-shrink: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        height: 100vh !important;
        z-index: 9999 !important;
        transition: transform 0.3s ease !important;
        box-shadow: 2px 0 12px rgba(0,0,0,0.15) !important;
    }
    .admin-main {
        margin-left: 260px !important;
        width: calc(100% - 260px) !important;
        flex: 1 !important;
        min-height: 100vh !important;
    }
    .admin-sidebar-logo {
        padding: 20px !important;
        display: flex !important;
        align-items: center !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    .admin-sidebar-brand {
        font-size: 1.1rem !important;
        font-weight: 800 !important;
        color: #E05A47 !important;
        margin-left: 10px !important;
    }
    .admin-sidebar-close {
        display: none;
        background: none !important;
        border: none !important;
        color: #fff !important;
        font-size: 1.2rem !important;
        margin-left: auto !important;
        cursor: pointer !important;
    }
    .admin-nav {
        padding: 16px 0 !important;
        overflow-y: auto !important;
        flex: 1 !important;
    }
    .admin-nav-section {
        padding: 8px 20px !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 1px !important;
        color: #9CA3AF !important;
        font-weight: 700 !important;
        margin-top: 12px !important;
    }
    .admin-nav-item {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        padding: 10px 20px !important;
        color: #D1D5DB !important;
        text-decoration: none !important;
        font-size: 0.85rem !important;
        font-weight: 500 !important;
        transition: all 0.2s !important;
    }
    .admin-nav-item:hover, .admin-nav-item.active {
        background: rgba(224, 90, 71, 0.15) !important;
        color: #E05A47 !important;
        font-weight: 700 !important;
        border-left: 4px solid #E05A47 !important;
    }
    @media (max-width: 900px) {
        .admin-sidebar {
            transform: translateX(-100%) !important;
        }
        .admin-sidebar.open {
            transform: translateX(0) !important;
        }
        .admin-sidebar-close {
            display: block !important;
        }
        .admin-main {
            margin-left: 0 !important;
            width: 100% !important;
        }
    }
</style>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-logo">
        <img src="../img/app_icon.png" alt="Ohati Logo" style="width:32px; height:32px; border-radius:6px; object-fit:cover;">
        <span class="admin-sidebar-brand">OHATI ADMIN</span>
        <button class="admin-sidebar-close" onclick="toggleSidebar(false)"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <nav class="admin-nav">
        <div class="admin-nav-section">Core Console</div>
        <a href="index.php" class="admin-nav-item <?= $current_page === 'index.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-line"></i> Dashboard
        </a>
        <a href="kyc.php" class="admin-nav-item <?= $current_page === 'kyc.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-shield-halved"></i> KYC Queue
            <?php if (!empty($pending_kyc) && $pending_kyc > 0): ?>
                <span style="background:#EF4444; color:#fff; font-size:0.65rem; padding:2px 6px; border-radius:10px; margin-left:auto; font-weight:700;"><?= $pending_kyc ?></span>
            <?php endif; ?>
        </a>
        <a href="kyc_history.php" class="admin-nav-item <?= $current_page === 'kyc_history.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left"></i> KYC History
        </a>

        <div class="admin-nav-section">Management</div>
        <a href="jobs.php" class="admin-nav-item <?= $current_page === 'jobs.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check"></i> Event Jobs
        </a>
        <a href="vendors.php" class="admin-nav-item <?= $current_page === 'vendors.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-briefcase"></i> Vendors
        </a>
        <a href="users.php" class="admin-nav-item <?= $current_page === 'users.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> Users
        </a>
        <a href="deleted_accounts.php" class="admin-nav-item <?= $current_page === 'deleted_accounts.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-user-slash"></i> Deleted Accounts
        </a>
        <a href="bookings.php" class="admin-nav-item <?= $current_page === 'bookings.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-days"></i> Bookings
        </a>
        <a href="payments.php" class="admin-nav-item <?= $current_page === 'payments.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-circle-dollar-to-slot"></i> Payments & Payouts
        </a>
        <a href="promotions.php" class="admin-nav-item <?= $current_page === 'promotions.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-rectangle-ad"></i> Ad Promotions
        </a>
        <a href="discounts.php" class="admin-nav-item <?= $current_page === 'discounts.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-tags"></i> Discounts & Offers
        </a>
        <a href="referrals.php" class="admin-nav-item <?= $current_page === 'referrals.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-gift"></i> Refer & Earn
        </a>
        <a href="issues.php" class="admin-nav-item <?= $current_page === 'issues.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-triangle-exclamation"></i> Issues & Reports
        </a>
        <a href="reviews.php" class="admin-nav-item <?= $current_page === 'reviews.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-star"></i> Reviews
        </a>
        <a href="trash.php" class="admin-nav-item <?= $current_page === 'trash.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-trash-can"></i> Deleted Records
        </a>

        <div class="admin-nav-section">System</div>
        <a href="audit_log.php" class="admin-nav-item <?= $current_page === 'audit_log.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check"></i> Audit Logs
        </a>
        <a href="otp_logs.php" class="admin-nav-item <?= $current_page === 'otp_logs.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-key"></i> OTP Logs
        </a>
        <a href="settings.php" class="admin-nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-gears"></i> System Settings
        </a>
        <a href="content.php" class="admin-nav-item <?= $current_page === 'content.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-signature"></i> Front Content
        </a>
        <a href="../index.php" class="admin-nav-item">
            <i class="fa-solid fa-arrow-left"></i> Back to Marketplace
        </a>
        <a href="logout.php" class="admin-nav-item" style="color:#EF4444 !important;">
            <i class="fa-solid fa-right-from-bracket"></i> Admin Logout
        </a>
    </nav>
</aside>

<script>
    if (typeof window.toggleSidebar !== 'function') {
        window.toggleSidebar = function(open) {
            const sidebar = document.getElementById('adminSidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
        };
    }
</script>
