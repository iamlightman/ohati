// js/modals.js — Ohati Modal, Sidebar, Filter, Lightbox, and Push Notification Management

// ── Push Notifications ─────────────────────────────────────────────────
let notifTimeout = null;
let notifTouchStartY = 0;
let notifTouchStartX = 0;

function showPushNotification(title, desc, type = 'info') {
    const el = document.getElementById('in-app-push-notif');
    const t = document.getElementById('notif-title');
    const d = document.getElementById('notif-desc');
    const iconEl = document.getElementById('notif-icon');
    if (!el || !t || !d) return;

    t.textContent = title || 'Notice';
    d.textContent = desc || '';

    // Apply type styles and icons
    el.classList.remove('notif-error', 'notif-success', 'notif-warning', 'notif-info');
    el.classList.add('notif-' + type);

    if (iconEl) {
        let iconClass = 'fa-solid fa-bell';
        if (type === 'error') iconClass = 'fa-solid fa-circle-exclamation';
        else if (type === 'success') iconClass = 'fa-solid fa-circle-check';
        else if (type === 'warning') iconClass = 'fa-solid fa-triangle-exclamation';
        iconEl.className = iconClass;
    }

    el.style.transform = '';
    el.style.opacity = '1';
    el.classList.add('active');

    // Attach swipe handlers if not already added
    if (!el.dataset.swipeInitialized) {
        el.dataset.swipeInitialized = 'true';
        el.addEventListener('touchstart', (e) => {
            if (e.touches && e.touches[0]) {
                notifTouchStartY = e.touches[0].clientY;
                notifTouchStartX = e.touches[0].clientX;
            }
        }, { passive: true });

        el.addEventListener('touchmove', (e) => {
            if (!e.touches || !e.touches[0]) return;
            const diffY = e.touches[0].clientY - notifTouchStartY;
            const diffX = Math.abs(e.touches[0].clientX - notifTouchStartX);
            if (diffY < 0 || diffX > 30) {
                el.style.transform = `translateY(${Math.min(0, diffY)}px)`;
                el.style.opacity = `${Math.max(0, 1 - Math.abs(diffY) / 100)}`;
            }
        }, { passive: true });

        el.addEventListener('touchend', (e) => {
            if (el.style.transform) {
                dismissPushNotification();
            }
        });
    }

    // Trigger device haptic vibration for notifications
    if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
        try {
            if (type === 'error') navigator.vibrate([300, 100, 300]);
            else navigator.vibrate([150, 80, 150]);
        } catch (e) {}
    }

    if (notifTimeout) clearTimeout(notifTimeout);
    // 5 Seconds auto-dismiss
    notifTimeout = setTimeout(() => dismissPushNotification(), 5000);
}

window.requestDeviceNotificationPermission = function() {
    if (typeof Notification !== 'undefined' && Notification.permission !== 'granted' && Notification.permission !== 'denied') {
        try {
            Notification.requestPermission().then(permission => {
                console.log('Notification permission:', permission);
            });
        } catch (e) {}
    }
};

function dismissPushNotification() {
    const el = document.getElementById('in-app-push-notif');
    if (el) {
        el.classList.remove('active');
        setTimeout(() => {
            el.style.transform = '';
            el.style.opacity = '';
        }, 300);
    }
    if (notifTimeout) { clearTimeout(notifTimeout); notifTimeout = null; }
}

// ── Sidebar ────────────────────────────────────────────────────────────
function toggleSidebar(open) {
    const overlay = document.getElementById('sidebar-overlay') || document.getElementById('app-sidebar-overlay');
    if (!overlay) return;
    const shouldOpen = (open === undefined) ? (!overlay.classList.contains('open') && !overlay.classList.contains('active')) : !!open;
    if (shouldOpen) {
        overlay.classList.add('open');
        overlay.classList.add('active');
        try {
            if (typeof updateSidebarUI === 'function') updateSidebarUI();
            if (typeof updateUserSessionUI === 'function') updateUserSessionUI();
            document.querySelectorAll('.sidebar-item').forEach(item => {
                const screen = item.getAttribute('onclick');
                if (screen && window.state && screen.includes(`navigateTo('${window.state.currentScreen}')`)) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        } catch (err) {
            console.warn("Sidebar UI update error:", err);
        }
    } else {
        overlay.classList.remove('open');
        overlay.classList.remove('active');
    }
}
window.toggleSidebar = toggleSidebar;

function updateSidebarUI() {
    const nameEl = document.getElementById('sidebar-name');
    const emailEl = document.getElementById('sidebar-email');
    const avatarEl = document.getElementById('sidebar-avatar');
    const authText = document.getElementById('sidebar-auth-text');
    const authLink = document.getElementById('sidebar-auth-link');
    const footerLogo = document.getElementById('sidebar-footer-logo');
    const navContainer = document.getElementById('sidebar-nav-container');

    if (state.user) {
        if (nameEl) nameEl.textContent = state.user.name;
        if (emailEl) emailEl.textContent = state.user.email || state.user.phone || 'Ohati Member';
        if (avatarEl && state.user.avatar) avatarEl.src = state.user.avatar;
        if (authText) authText.textContent = 'Sign Out';
        if (authLink) {
            authLink.onclick = () => { handleLogout(); toggleSidebar(false); };
        }

        const activeRole = state.user.active_role || 'customer';

        if (navContainer) {
            if (activeRole === 'vendor') {
                navContainer.innerHTML = `
                    <a class="sidebar-link" onclick="navigateTo('vendor-dash'); toggleSidebar(false)">
                        <i class="fa-solid fa-chart-pie"></i><span>Vendor Dashboard</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('vendor-jobs'); toggleSidebar(false)">
                        <i class="fa-solid fa-briefcase"></i><span>Find Event Jobs</span>
                    </a>
                    <a class="sidebar-link" onclick="JobsModule.openCreateJobModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-plus-circle"></i><span>Post Event Job</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('bookings'); toggleSidebar(false)">
                        <i class="fa-solid fa-calendar-check"></i><span>My Bookings</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('vendor-ads'); toggleSidebar(false)">
                        <i class="fa-solid fa-rectangle-ad"></i><span>Promotions Hub</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('vendor-auto-response'); toggleSidebar(false)">
                        <i class="fa-solid fa-robot"></i><span>Auto-Response</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('profile-edit'); toggleSidebar(false)">
                        <i class="fa-solid fa-user-pen"></i><span>Edit Profile</span>
                    </a>
                    <a class="sidebar-link" onclick="switchAccountType('customer'); toggleSidebar(false)" style="background:var(--gray-100); border-radius:8px; margin-top:10px;">
                        <i class="fa-solid fa-repeat"></i><span>Switch to Customer Mode</span>
                    </a>
                    <div class="sidebar-divider"></div>
                    <a class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-bullhorn"></i><span>Refer & Earn</span>
                        <span class="sidebar-badge-new" style="background:var(--accent);">PROMO</span>
                    </a>
                    <a class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-tags"></i><span>Discounts & Offers</span>
                    </a>
                    <a class="sidebar-link" onclick="openPlatformReviewModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-star"></i><span>Give a Review</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('report-issue'); toggleSidebar(false)">
                        <i class="fa-solid fa-bug"></i><span>Report an Issue</span>
                    </a>
                    <a class="sidebar-link" onclick="openSettingsModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-gear"></i><span>Settings</span>
                    </a>
                    <div class="sidebar-divider"></div>
                    <a class="sidebar-link sidebar-signin-link" id="sidebar-auth-link" onclick="handleLogout(); toggleSidebar(false)">
                        <i class="fa-solid fa-right-from-bracket"></i><span>Sign Out</span>
                    </a>
                `;
            } else {
                navContainer.innerHTML = `
                    <a class="sidebar-link" onclick="navigateTo('profile'); toggleSidebar(false)">
                        <i class="fa-solid fa-user-gear"></i><span>My Profile</span>
                    </a>
                    <a class="sidebar-link" onclick="JobsModule.openCreateJobModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-plus-circle"></i><span>Post Event Job</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('user-jobs'); toggleSidebar(false)">
                        <i class="fa-solid fa-list-check"></i><span>My Posted Jobs</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('vendor-jobs'); toggleSidebar(false)">
                        <i class="fa-solid fa-briefcase"></i><span>Browse Event Jobs</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('favorites'); toggleSidebar(false)">
                        <i class="fa-solid fa-heart"></i><span>Saved Vendors</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('bookings'); toggleSidebar(false)">
                        <i class="fa-solid fa-calendar-check"></i><span>My Bookings</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('notifications'); toggleSidebar(false)">
                        <i class="fa-solid fa-bell"></i><span>Notifications</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('compare'); toggleSidebar(false)">
                        <i class="fa-solid fa-scale-balanced"></i><span>Compare Vendors</span>
                    </a>
                    ${state.user.has_vendor_profile ? `
                        <a class="sidebar-link" onclick="switchAccountType('vendor'); toggleSidebar(false)" style="background:var(--gray-100); border-radius:8px; margin-top:10px;">
                            <i class="fa-solid fa-repeat"></i><span>Switch to Vendor Mode</span>
                        </a>
                    ` : `
                        <a class="sidebar-link sidebar-premium" onclick="openPremiumModal(); toggleSidebar(false)">
                            <i class="fa-solid fa-crown"></i><span>Become a Vendor</span>
                            <span class="sidebar-badge-new">NEW</span>
                        </a>
                    `}
                    <div class="sidebar-divider"></div>
                    <a class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-bullhorn"></i><span>Refer & Earn</span>
                        <span class="sidebar-badge-new" style="background:var(--accent);">PROMO</span>
                    </a>
                    <a class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-tags"></i><span>Discounts & Offers</span>
                    </a>
                    <a class="sidebar-link" onclick="openPlatformReviewModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-star"></i><span>Give a Review</span>
                    </a>
                    <a class="sidebar-link" onclick="navigateTo('report-issue'); toggleSidebar(false)">
                        <i class="fa-solid fa-bug"></i><span>Report an Issue</span>
                    </a>
                    <a class="sidebar-link" onclick="openSettingsModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-gear"></i><span>Settings</span>
                    </a>
                    <div class="sidebar-divider"></div>
                    <a class="sidebar-link sidebar-signin-link" id="sidebar-auth-link" onclick="handleLogout(); toggleSidebar(false)">
                        <i class="fa-solid fa-right-from-bracket"></i><span>Sign Out</span>
                    </a>
                `;
            }
        }
    } else {
        if (nameEl) nameEl.textContent = 'Guest';
        if (emailEl) emailEl.textContent = 'Not signed in';
        if (avatarEl) avatarEl.src = window.DEFAULT_USER_AVATAR || "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";
        if (authText) authText.textContent = 'Sign In';
        if (authLink) {
            authLink.onclick = () => { openLoginModal(); toggleSidebar(false); };
        }
        if (navContainer) {
            navContainer.innerHTML = `
                <a class="sidebar-link" onclick="navigateTo('home'); toggleSidebar(false)">
                    <i class="fa-solid fa-house"></i><span>Home</span>
                </a>
                <a class="sidebar-link" onclick="navigateTo('search'); toggleSidebar(false)">
                    <i class="fa-solid fa-magnifying-glass"></i><span>Find Vendors</span>
                </a>
                <div class="sidebar-divider"></div>
                <a class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                    <i class="fa-solid fa-bullhorn"></i><span>Refer & Earn</span>
                    <span class="sidebar-badge-new" style="background:var(--accent);">PROMO</span>
                </a>
                <a class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                    <i class="fa-solid fa-tags"></i><span>Discounts & Offers</span>
                </a>
                <a class="sidebar-link" onclick="openPlatformReviewModal(); toggleSidebar(false)">
                    <i class="fa-solid fa-star"></i><span>Give a Review</span>
                </a>
                <a class="sidebar-link" onclick="navigateTo('report-issue'); toggleSidebar(false)">
                    <i class="fa-solid fa-bug"></i><span>Report an Issue</span>
                </a>
                <div class="sidebar-divider"></div>
                <a class="sidebar-link sidebar-signin-link" id="sidebar-auth-link" onclick="openLoginModal(); toggleSidebar(false)">
                    <i class="fa-solid fa-right-to-bracket"></i><span>Sign In</span>
                </a>
            `;
        }
    }

    // Theme-aware footer logo
    const isDark = document.body.classList.contains('dark-theme');
    if (footerLogo) footerLogo.src = isDark ? 'img/logo white transparent small.png' : 'img/logo black transparent small.png';
}

function switchAccountType(targetRole) {
    API.post('switch_role', { role: targetRole }).then(res => {
        if (res.success) {
            state.user = res.user;
            localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
            showPushNotification('Account Switched', 'Switched to ' + (targetRole === 'vendor' ? 'Vendor Mode' : 'Customer Mode'));
            updateSidebarUI();
            if (targetRole === 'vendor') {
                navigateTo('vendor-dash');
            } else {
                navigateTo('home');
            }
        }
    }).catch(err => {
        if (err.need_upgrade || (err.message && err.message.includes('not activated'))) {
            showPushNotification('Upgrade Required', 'Please complete vendor registration first.');
            openPremiumModal();
        } else {
            showPushNotification('Error', err.message || err);
        }
    });
}

// ── Modal System ───────────────────────────────────────────────────────
function openModal(contentHTML) {
    const overlay = document.getElementById('modal-overlay');
    const content = document.getElementById('modal-content');
    if (!overlay || !content) return;
    content.innerHTML = contentHTML;
    overlay.classList.add('open');
}

function closeModal() {
    const overlay = document.getElementById('modal-overlay');
    if (overlay) overlay.classList.remove('open');
}

// ── Filter Drawer ──────────────────────────────────────────────────────
function openFilterDrawer() {
    const overlay = document.getElementById('filter-drawer-overlay');
    const drawer = document.getElementById('filter-drawer');
    if (overlay) overlay.classList.add('open');
    if (drawer) {
        drawer.classList.add('open');
        renderFilterDrawer();
    }
}

function closeFilterDrawer() {
    const overlay = document.getElementById('filter-drawer-overlay');
    const drawer = document.getElementById('filter-drawer');
    if (overlay) overlay.classList.remove('open');
    if (drawer) drawer.classList.remove('open');
}

function renderFilterDrawer() {
    const drawer = document.getElementById('filter-drawer');
    if (!drawer) return;

    drawer.innerHTML = `
        <div class="flex-between mb-16">
            <h3 style="font-size:1.1rem;">Filter Vendors</h3>
            <button class="btn btn-ghost btn-sm" onclick="closeFilterDrawer()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-select" id="filter-category">
                <option value="">All Categories</option>
                ${state.categories.map(c => `<option value="${c.name}" ${state.filters.category === c.name ? 'selected' : ''}>${c.name}</option>`).join('')}
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Location</label>
            <input class="form-input" type="text" placeholder="e.g. Accra, Kumasi..." id="filter-location" value="${state.filters.location}">
        </div>



        <div class="form-group">
            <label class="form-label">Minimum Rating</label>
            <select class="form-select" id="filter-rating">
                <option value="">Any Rating</option>
                <option value="3" ${state.filters.rating === '3' ? 'selected' : ''}>3+ Stars</option>
                <option value="4" ${state.filters.rating === '4' ? 'selected' : ''}>4+ Stars</option>
                <option value="4.5" ${state.filters.rating === '4.5' ? 'selected' : ''}>4.5+ Stars</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" style="margin-bottom:8px;">Options</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:0.82rem;color:var(--gray-600);margin-bottom:8px;cursor:pointer;">
                <input type="checkbox" id="filter-verified" ${state.filters.verified_only ? 'checked' : ''}>
                <span>Verified vendors only</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:0.82rem;color:var(--gray-600);cursor:pointer;">
                <input type="checkbox" id="filter-premium" ${state.filters.premium_only ? 'checked' : ''}>
                <span>Premium vendors only</span>
            </label>
        </div>

        <div class="divider"></div>

        <div style="display:flex;gap:10px;">
            <button class="btn btn-outline btn-full" onclick="resetAllFilters()">Reset</button>
            <button class="btn btn-primary btn-full" onclick="applyFilters()">Apply Filters</button>
        </div>
    `;
}

function applyFilters() {
    state.filters.category = document.getElementById('filter-category')?.value || '';
    state.filters.location = document.getElementById('filter-location')?.value || '';
    state.filters.rating = document.getElementById('filter-rating')?.value || '';
    state.filters.min_price = document.getElementById('filter-min-price')?.value || '';
    state.filters.max_price = document.getElementById('filter-max-price')?.value || '';
    state.filters.verified_only = document.getElementById('filter-verified')?.checked ? 1 : 0;
    state.filters.premium_only = document.getElementById('filter-premium')?.checked ? 1 : 0;
    closeFilterDrawer();
    API.getVendors(state.filters).then(v => { state.vendors = v; renderSearchScreen(); });
}

function resetAllFilters() {
    state.filters = { category: '', location: '', search: '', rating: '', verified_only: 0, premium_only: 0, instant_booking: 0, min_price: '', max_price: '' };
    closeFilterDrawer();
    API.getVendors().then(v => { state.vendors = v; renderSearchScreen(); });
}

// ── Lightbox ───────────────────────────────────────────────────────────
let lightboxImages = [];
let lightboxIndex = 0;

function openLightbox(images, index = 0) {
    lightboxImages = images;
    lightboxIndex = index;
    const overlay = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-img');
    const counter = document.getElementById('lightbox-counter');
    if (!overlay || !img) return;
    img.src = lightboxImages[lightboxIndex];
    if (counter) counter.textContent = `${lightboxIndex + 1} / ${lightboxImages.length}`;
    overlay.classList.add('open');
}

function closeLightbox() {
    const overlay = document.getElementById('lightbox');
    if (overlay) overlay.classList.remove('open');
}

function lightboxNav(dir) {
    lightboxIndex = (lightboxIndex + dir + lightboxImages.length) % lightboxImages.length;
    const img = document.getElementById('lightbox-img');
    const counter = document.getElementById('lightbox-counter');
    if (img) img.src = lightboxImages[lightboxIndex];
    if (counter) counter.textContent = `${lightboxIndex + 1} / ${lightboxImages.length}`;
}

// ── Convenience Modals ─────────────────────────────────────────────────
function openLoginModal() {
    if (typeof showMandatoryAuthLockScreen === 'function') {
        showMandatoryAuthLockScreen('login');
    }
}

function openSignUpModal() {
    if (typeof showMandatoryAuthLockScreen === 'function') {
        showMandatoryAuthLockScreen('signup');
    }
}



function openPremiumModal() {
    state.authMode = 'vendor-register';
    state.authStep = 1;
    state.authData = {};
    renderAuthModal();
}

function openSettingsModal() {
    openModal(`
        <div class="auth-modal-header"><h2 class="auth-modal-title">Settings</h2></div>
        <div class="profile-menu-section" style="padding:0;">
            <div class="profile-menu-item" onclick="document.getElementById('theme-toggle-btn').click(); closeModal();">
                <div class="profile-menu-icon"><i class="fa-solid fa-moon"></i></div>
                <span class="profile-menu-label">Toggle Dark Mode</span>
                <i class="fa-solid fa-chevron-right profile-menu-arrow"></i>
            </div>
            <div class="profile-menu-item" onclick="closeModal(); navigateTo('help');">
                <div class="profile-menu-icon"><i class="fa-solid fa-circle-question"></i></div>
                <span class="profile-menu-label">Help Center</span>
                <i class="fa-solid fa-chevron-right profile-menu-arrow"></i>
            </div>
            <div class="profile-menu-item" onclick="closeModal(); navigateTo('notifications');">
                <div class="profile-menu-icon"><i class="fa-solid fa-bell"></i></div>
                <span class="profile-menu-label">Notifications</span>
                <i class="fa-solid fa-chevron-right profile-menu-arrow"></i>
            </div>
            <div class="profile-menu-item" onclick="closeModal(); confirmDeleteAccount();" style="color:var(--danger, #EF4444);">
                <div class="profile-menu-icon" style="background:rgba(239,68,68,0.1); color:var(--danger, #EF4444);"><i class="fa-solid fa-trash-can"></i></div>
                <span class="profile-menu-label" style="color:var(--danger, #EF4444); font-weight:700;">Delete My Account</span>
                <i class="fa-solid fa-chevron-right profile-menu-arrow" style="color:var(--danger, #EF4444);"></i>
            </div>
        </div>
    `);
}

function openNotificationsModal() {
    navigateTo('notifications');
}

// ── Welcome Popup Modal ───────────────────────────────────────────────
function openWelcomePopup() {
    return; // Welcome popup completely disabled per user requirement
}

function closeWelcomePopup(event) {
    if (event) {
        event.stopPropagation();
    }
    const checkbox = document.getElementById('welcome-dont-show-again');
    if (checkbox && checkbox.checked) {
        localStorage.setItem('ohati_welcome_dismissed', Date.now().toString());
    } else {
        // If not checked, remove the dismissal key so it shows next time
        localStorage.removeItem('ohati_welcome_dismissed');
    }
    const overlay = document.getElementById('welcome-popup-overlay');
    if (overlay) {
        overlay.classList.remove('open');
    }
}

window.openAppDownloadUrl = function(platform) {
    API.get('get_app_download_urls').then(res => {
        if (res.success) {
            const url = (platform === 'ios' || platform === 'App Store') ? res.ios_download_url : res.android_download_url;
            if (url) {
                window.open(url, '_blank');
                return;
            }
        }
        window.open('https://play.google.com/store/apps/details?id=com.ohati.app', '_blank');
    }).catch(() => {
        window.open('https://play.google.com/store/apps/details?id=com.ohati.app', '_blank');
    });
};
window.showBadgeMessage = window.openAppDownloadUrl;

function openAllCategoriesModal() {
    const categories = state.categories || [];
    
    let modalHTML = `
        <div class="auth-modal-header" style="position:relative; padding-bottom:12px;">
            <h2 class="auth-modal-title" style="font-size:1.15rem; font-family:'Fraunces', serif;">All Categories</h2>
            <p class="auth-modal-subtitle" style="font-size:0.75rem; color:var(--gray-500);">Browse event vendor services by category</p>
        </div>
        
        <div style="margin: 12px 0 16px 0; position:relative;">
            <div style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--gray-400); font-size:0.85rem;">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <input type="text" id="category-modal-search" placeholder="Search categories..." onkeyup="filterCategoriesInModal()" style="width:100%; padding:10px 12px 10px 36px; border-radius:10px; border:1px solid var(--gray-200); background:var(--gray-50); color:var(--gray-800); font-size:0.85rem; box-sizing:border-box; outline:none;">
        </div>
        
        <div id="modal-category-grid" style="display:grid; grid-template-columns:repeat(2, 1fr); gap:12px; max-height:300px; overflow-y:auto; padding-bottom:8px;">
            ${categories.map(c => `
                <div class="category-item-modal" onclick="selectCategoryFilter('${c.name}'); closeModal();" data-name="${c.name.toLowerCase()}" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; border:1px solid var(--gray-200); background:var(--gray-50); cursor:pointer; transition:all var(--anim-fast) ease;">
                    <div style="font-size:1.1rem; color:var(--accent); display:flex; align-items:center; justify-content:center; width:28px; height:28px;"><i class="fa-solid fa-${c.icon}"></i></div>
                    <span style="font-size:0.8rem; font-weight:600; color:var(--gray-800);">${c.name}</span>
                </div>
            `).join('')}
        </div>
    `;
    
    openModal(modalHTML);
}

function filterCategoriesInModal() {
    const q = document.getElementById('category-modal-search')?.value.toLowerCase().trim() || '';
    const items = document.querySelectorAll('#modal-category-grid .category-item-modal');
    items.forEach(item => {
        const name = item.getAttribute('data-name') || '';
        if (name.includes(q)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

window.dismissVendorPromoPopupToday = function() {
    localStorage.setItem('ohati_vendor_promo_dismissed', Date.now().toString());
    closeModal();
};

window.dismissSponsoredPopupToday = function() {
    localStorage.setItem('ohati_sponsored_dismissed', Date.now().toString());
    closeModal();
};

function checkAndShowVendorPromotionPopup() {
    if (!state.user || state.user.role !== 'vendor') return;

    // Check if dismissed within the last 24 hours
    const dismissedTime = localStorage.getItem('ohati_vendor_promo_dismissed');
    if (dismissedTime) {
        const diff = Date.now() - parseInt(dismissedTime, 10);
        if (diff < 24 * 60 * 60 * 1000) {
            return;
        }
    }

    API.getAdCampaigns(state.user.vendor_id).then(ads => {
        // If they have no active/pending ads, show promotion invite
        const activeOrPending = ads.filter(ad => ad.status === 'active' || ad.status === 'pending');
        if (activeOrPending.length === 0) {
            const html = `
                <div class="auth-modal-header" style="text-align:center;">
                    <div style="font-size:3rem; color:var(--accent); margin-bottom:12px;"><i class="fa-solid fa-rocket fa-bounce"></i></div>
                    <h2 class="auth-modal-title" style="font-family:'Fraunces', serif;">Double Your Leads!</h2>
                    <p class="auth-modal-subtitle">Promote your business on Ohati today</p>
                </div>
                <div style="padding:10px 0; text-align:center;">
                    <p style="font-size:0.8rem; color:var(--gray-600); line-height:1.4; margin-bottom:16px;">
                        Put your services in front of thousands of Ghana planners. Plans start from only <strong>GH₵ 50 / day</strong>.
                    </p>
                    <div class="card" style="padding:12px; background:var(--gray-50); border:1px solid var(--gray-100); text-align:left; margin-bottom:16px; border-radius:10px;">
                        <div style="font-weight:700; font-size:0.75rem; color:var(--primary); margin-bottom:4px;">Starter Promotion highlights:</div>
                        <div style="font-size:0.75rem; color:var(--gray-500); margin-bottom:2px;"><i class="fa-solid fa-check" style="color:var(--success);"></i> Est. Reach: 1,000+ views</div>
                        <div style="font-size:0.75rem; color:var(--gray-500); margin-bottom:2px;"><i class="fa-solid fa-check" style="color:var(--success);"></i> Sponsored Listing Badge</div>
                        <div style="font-size:0.75rem; color:var(--gray-500);"><i class="fa-solid fa-check" style="color:var(--success);"></i> Search priority ranking</div>
                    </div>
                    <button class="btn btn-primary btn-full" onclick="closeModal(); navigateTo('vendor-ads');">Promote My Business</button>
                    <button class="btn btn-outline btn-full mt-8" onclick="dismissVendorPromoPopupToday()">Maybe Later</button>
                </div>
            `;
            openModal(html);
        }
    }).catch(err => {
        console.error("Error checking vendor ad campaigns for popup:", err);
    });
}

function checkAndShowGeneralSponsoredPopup() {
    // Only show if not a vendor
    if (state.user && state.user.role === 'vendor') return;

    // Fetch active, admin-approved pop-up ads specifically for home_popup placement
    API.get('get_advertisements', { placement: 'home_popup' }).then(ads => {
        if (!ads || !Array.isArray(ads) || ads.length === 0) return;
        
        // Pick top active approved popup ad
        const ad = ads[0];
        if (!ad || ad.status !== 'active' || ad.payment_status !== 'paid') return;

        // Record popup trigger count in backend
        API.get('record_ad_popup', { ad_id: ad.id });

        let bannerImg = ad.banner_url || 'img/ads/default.jpg';
        if (bannerImg && !bannerImg.startsWith('data:') && !bannerImg.startsWith('http')) {
            bannerImg = bannerImg;
        }

        const html = `
            <div class="auth-modal-header" style="position:relative; text-align:center;">
                <span style="background:var(--accent); color:#fff; font-size:0.65rem; font-weight:800; padding:3px 10px; border-radius:12px; display:inline-flex; align-items:center; gap:4px; margin-bottom:8px; text-transform:uppercase;">
                    <i class="fa-solid fa-rectangle-ad"></i> Sponsored Promotion
                </span>
                <h2 class="auth-modal-title" style="font-family:'Fraunces', serif;">${escapeHTML(ad.title)}</h2>
                <p class="auth-modal-subtitle">${escapeHTML(ad.vendor_name || 'Featured Partner')}</p>
            </div>
            <div style="padding:10px 0;">
                <div class="card" onclick="closeModal(); handleAdClick(${ad.id}, '${ad.destination || 'profile'}', ${ad.vendor_id});" style="cursor:pointer; padding:0; overflow:hidden; border:1px solid var(--gray-200); box-shadow:var(--shadow-sm); margin-bottom:16px; border-radius:12px;">
                    <div style="position:relative; height:160px; background:var(--gray-100);">
                        <img src="${bannerImg}" style="width:100%; height:100%; object-fit:cover; display:block;">
                    </div>
                    <div style="padding:12px;">
                        <h4 style="margin:0 0 4px 0; font-size:0.95rem; font-weight:700; color:var(--gray-800);">${escapeHTML(ad.title)}</h4>
                        <p style="margin:0 0 8px 0; font-size:0.8rem; color:var(--gray-600); line-height:1.4;">${escapeHTML(ad.description || 'Exclusive service offering for your event.')}</p>
                    </div>
                </div>
                <button class="btn btn-primary btn-full" onclick="closeModal(); handleAdClick(${ad.id}, '${ad.destination || 'profile'}', ${ad.vendor_id});">${escapeHTML(ad.cta_text || 'Learn More')}</button>
                <button class="btn btn-outline btn-full mt-8" onclick="closeModal()">Close</button>
            </div>
        `;
        openModal(html);
    }).catch(err => {
        console.error("Error checking active sponsored popup:", err);
    });
}

function showComingSoonReferral() {
    const html = `
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Coming soon on App</h2>
            <p class="auth-modal-subtitle">Exciting rewards are on the way!</p>
        </div>
        <div style="padding:16px 0; text-align:center;">
            <i class="fa-solid fa-gift" style="font-size:3rem; color:var(--accent); margin-bottom:16px;"></i>
            <p style="font-size:0.9rem; color:var(--gray-700); line-height:1.5; margin-bottom:12px;">
                <strong>First 200 vendors</strong> get <span style="color:var(--primary); font-weight:700;">100 Cedis</span> sign up bonus.
            </p>
            <p style="font-size:0.9rem; color:var(--gray-700); line-height:1.5; margin-bottom:12px;">
                <strong>First 200 customers</strong> get <span style="color:var(--primary); font-weight:700;">50 Cedis</span> discount.
            </p>
            <p style="font-size:0.9rem; color:var(--gray-700); line-height:1.5; margin-bottom:16px;">
                A transactional referral gets <span style="color:var(--primary); font-weight:700;">50 Cedis</span> too if they refer someone.
            </p>
            <button class="btn btn-primary btn-full" onclick="closeModal()">Got it!</button>
        </div>
    `;
    openModal(html);
}

function openAllPlatformReviewsModal() {
    const reviewsHtml = state.platformReviews.map(r => `
        <div class="card p-12 mb-12" style="background:var(--gray-50); border:1px solid var(--gray-100); border-radius:12px; display:flex; gap:12px; text-align:left;">
            <img src="${r.avatar}" alt="" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
            <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--gray-800);">${r.name}</span>
                    <span>${starsHTML(r.rating, '0.55rem')}</span>
                </div>
                <p style="font-size:0.75rem; color:var(--gray-600); line-height:1.4; margin:0;">"${r.comment}"</p>
            </div>
        </div>
    `).join('');

    const html = `
        <div class="auth-modal-header" style="margin-bottom:16px;">
            <h2 class="auth-modal-title">Client Reviews</h2>
            <p class="auth-modal-subtitle">What our happy couples and clients say about Ohati.</p>
        </div>
        <div style="max-height:400px; overflow-y:auto; padding-right:4px; margin-bottom:16px;">
            ${reviewsHtml}
        </div>
        <button class="btn btn-primary btn-full" onclick="closeModal()">Close</button>
    `;
    openModal(html);
}

function openPlatformReviewModal() {
    const defaultName = (state.user && state.user.name) ? state.user.name : '';
    const html = `
        <div class="auth-modal-header" style="margin-bottom:16px;">
            <h2 class="auth-modal-title">Share Your Experience</h2>
            <p class="auth-modal-subtitle">We would love to hear your feedback about using Ohati.</p>
        </div>
        <form id="platform-review-form" onsubmit="submitPlatformReviewForm(event)">
            <div class="form-group mb-12">
                <label class="form-label" style="font-weight:600; font-size:0.75rem; color:var(--gray-700);">Your Name</label>
                <input type="text" id="review-user-name" class="form-input" placeholder="e.g. Ama Serwaa" required value="${defaultName}" style="width:100%; border-radius:8px; box-sizing:border-box;">
            </div>
            <div class="form-group mb-12">
                <label class="form-label" style="font-weight:600; font-size:0.75rem; color:var(--gray-700); margin-bottom:6px; display:block;">Rating</label>
                <div class="rating-stars-select" style="display:flex; gap:8px; font-size:1.5rem; color:var(--gray-300); cursor:pointer;">
                    <i class="fa-solid fa-star star-select" data-rating="1" onclick="setSelectRating(1)"></i>
                    <i class="fa-solid fa-star star-select" data-rating="2" onclick="setSelectRating(2)"></i>
                    <i class="fa-solid fa-star star-select" data-rating="3" onclick="setSelectRating(3)"></i>
                    <i class="fa-solid fa-star star-select" data-rating="4" onclick="setSelectRating(4)"></i>
                    <i class="fa-solid fa-star star-select" data-rating="5" onclick="setSelectRating(5)"></i>
                </div>
                <input type="hidden" id="review-rating-val" value="5">
            </div>
            <div class="form-group mb-16">
                <label class="form-label" style="font-weight:600; font-size:0.75rem; color:var(--gray-700);">Your Review</label>
                <textarea id="review-comment" class="form-input" rows="3" placeholder="Tell us what you liked about Ohati..." required style="width:100%; border-radius:8px; box-sizing:border-box; height:80px; resize:none; font-family:inherit;"></textarea>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline btn-full" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-full">Submit Review</button>
            </div>
        </form>
    `;
    openModal(html);
    // Trigger initial star select UI update
    setTimeout(() => setSelectRating(5), 10);
}

window.setSelectRating = function(rating) {
    document.getElementById('review-rating-val').value = rating;
    const stars = document.querySelectorAll('.star-select');
    stars.forEach(star => {
        const starRating = parseInt(star.getAttribute('data-rating'));
        if (starRating <= rating) {
            star.style.color = 'var(--accent)';
        } else {
            star.style.color = 'var(--gray-300)';
        }
    });
};

window.submitPlatformReviewForm = function(event) {
    event.preventDefault();
    const name = document.getElementById('review-user-name').value.trim();
    const rating = parseInt(document.getElementById('review-rating-val').value);
    const comment = document.getElementById('review-comment').value.trim();

    if (!name || !comment) {
        showPushNotification('Error', 'Please fill in all fields.');
        return;
    }

    API.post('submit_platform_review', { name, rating, comment })
        .then(res => {
            closeModal();
            showPushNotification('Thank You!', res.message || 'Review submitted for approval.');
        })
        .catch(err => {
            showPushNotification('Error', err.message || 'Failed to submit review.');
        });
};

function showVideoCallComingSoon() {
    if (typeof initiateVoiceCall === 'function') {
        initiateVoiceCall();
    }
}
