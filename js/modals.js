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
        } catch (e) { }
    }

    if (notifTimeout) clearTimeout(notifTimeout);
    // 5 Seconds auto-dismiss
    notifTimeout = setTimeout(() => dismissPushNotification(), 5000);
}

window.requestDeviceNotificationPermission = function () {
    if (typeof Notification !== 'undefined' && Notification.permission !== 'granted' && Notification.permission !== 'denied') {
        try {
            Notification.requestPermission().then(permission => {
                console.log('Notification permission:', permission);
            });
        } catch (e) { }
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
    try {
        const overlays = [document.getElementById('sidebar-overlay'), document.getElementById('app-sidebar-overlay')].filter(Boolean);
        if (overlays.length === 0) return;

        const mainOverlay = overlays[0];
        const shouldOpen = (open === undefined) ? (!mainOverlay.classList.contains('open') && !mainOverlay.classList.contains('active')) : !!open;

        overlays.forEach(overlay => {
            if (shouldOpen) {
                overlay.classList.add('open', 'active');
                overlay.style.visibility = 'visible';
                overlay.style.pointerEvents = 'auto';
            } else {
                overlay.classList.remove('open', 'active');
                overlay.style.visibility = 'hidden';
                overlay.style.pointerEvents = 'none';
            }
        });

        if (shouldOpen) {
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
            } catch (uiErr) {
                console.warn('Sidebar UI update warning:', uiErr);
            }
        }
    } catch (err) {
        console.error('Error toggling sidebar:', err);
    }
}
window.toggleSidebar = toggleSidebar;

function updateSidebarUI() {
    if (typeof window.updateHeaderNavRoleVisibility === 'function') {
        window.updateHeaderNavRoleVisibility();
    }
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
        if (avatarEl) avatarEl.src = window.resolveImageUrl(state.user.avatar);
        if (authText) authText.textContent = 'Sign Out';
        if (authLink) {
            authLink.onclick = () => { handleLogout(); toggleSidebar(false); };
        }

        const activeRole = state.user.active_role || state.user.role || ((state.user.vendor_id && parseInt(state.user.vendor_id) > 0) ? 'vendor' : 'customer');

        if (navContainer) {
            if (activeRole === 'vendor') {
                navContainer.innerHTML = `
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('vendor-dash', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-chart-pie"></i><span>Vendor Dashboard</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('vendor-jobs', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-briefcase"></i><span>Find Event Jobs</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('blog', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-newspaper"></i><span>Blog & Guides</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="JobsModule.openCreateJobModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-plus-circle"></i><span>Post Event Job</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('bookings', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-calendar-check"></i><span>My Bookings</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('vendor-ads', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-rectangle-ad"></i><span>Promotions Hub</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('vendor-auto-response', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-robot"></i><span>Auto-Response</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('profile-edit', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-user-pen"></i><span>Edit Profile</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="switchAccountType('customer'); toggleSidebar(false)" style="background:var(--gray-100); border-radius:8px; margin-top:10px;">
                        <i class="fa-solid fa-repeat"></i><span>Switch to Customer Mode</span>
                    </a>
                    <div class="sidebar-divider"></div>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-bullhorn"></i><span>Refer & Earn</span>
                        <span class="sidebar-badge-new" style="background:var(--accent);">PROMO</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-tags"></i><span>Discounts & Offers</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="openPlatformReviewModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-star"></i><span>Give a Review</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('report-issue', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-bug"></i><span>Report an Issue</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('help', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-circle-question"></i><span>Help Center</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="openSettingsModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-gear"></i><span>Settings</span>
                    </a>
                    <div class="sidebar-divider"></div>
                    <a href="javascript:void(0)" role="button" class="sidebar-link sidebar-signin-link" id="sidebar-auth-link" onclick="handleLogout(); toggleSidebar(false)">
                        <i class="fa-solid fa-right-from-bracket"></i><span>Sign Out</span>
                    </a>
                `;
            } else {
                navContainer.innerHTML = `
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo((state.user && (state.user.vendor_id || state.user.role === 'vendor' || state.user.vendor)) ? 'vendor-dash' : 'user-jobs', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('bookings', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-calendar-check"></i><span>My Bookings</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('profile', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-user-gear"></i><span>My Profile</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="JobsModule.openCreateJobModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-plus-circle"></i><span>Post Event Job</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('blog', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-newspaper"></i><span>Blog & Guides</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('user-jobs', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-list-check"></i><span>My Posted Jobs</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('vendor-jobs', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-briefcase"></i><span>Browse Event Jobs</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('favorites', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-heart"></i><span>Saved Vendors</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('notifications', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-bell"></i><span>Notifications</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('compare', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-scale-balanced"></i><span>Compare Vendors</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('vendor-ads', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-rectangle-ad"></i><span>Promotions Hub</span>
                    </a>
                    ${state.user.has_vendor_profile ? `
                        <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="switchAccountType('vendor'); toggleSidebar(false)" style="background:var(--gray-100); border-radius:8px; margin-top:10px;">
                            <i class="fa-solid fa-repeat"></i><span>Switch to Vendor Mode</span>
                        </a>
                    ` : `
                        <a href="javascript:void(0)" role="button" class="sidebar-link sidebar-premium" onclick="openPremiumModal(); toggleSidebar(false)">
                            <i class="fa-solid fa-crown"></i><span>Become a Vendor</span>
                            <span class="sidebar-badge-new">NEW</span>
                        </a>
                    `}
                    <div class="sidebar-divider"></div>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-bullhorn"></i><span>Refer & Earn</span>
                        <span class="sidebar-badge-new" style="background:var(--accent);">PROMO</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                        <i class="fa-solid fa-tags"></i><span>Discounts & Offers</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="openPlatformReviewModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-star"></i><span>Give a Review</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('report-issue', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-bug"></i><span>Report an Issue</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('help', {}, { force: true }); toggleSidebar(false)">
                        <i class="fa-solid fa-circle-question"></i><span>Help Center</span>
                    </a>
                    <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="openSettingsModal(); toggleSidebar(false)">
                        <i class="fa-solid fa-gear"></i><span>Settings</span>
                    </a>
                    <div class="sidebar-divider"></div>
                    <a href="javascript:void(0)" role="button" class="sidebar-link sidebar-signin-link" id="sidebar-auth-link" onclick="handleLogout(); toggleSidebar(false)">
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
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('home', {}, { force: true }); toggleSidebar(false)">
                    <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
                </a>
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('bookings', {}, { force: true }); toggleSidebar(false)">
                    <i class="fa-solid fa-calendar-check"></i><span>My Bookings</span>
                </a>
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('search', {}, { force: true }); toggleSidebar(false)">
                    <i class="fa-solid fa-magnifying-glass"></i><span>Find Vendors</span>
                </a>
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('blog', {}, { force: true }); toggleSidebar(false)">
                    <i class="fa-solid fa-newspaper"></i><span>Blog & Guides</span>
                </a>
                <div class="sidebar-divider"></div>
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                    <i class="fa-solid fa-bullhorn"></i><span>Refer & Earn</span>
                    <span class="sidebar-badge-new" style="background:var(--accent);">PROMO</span>
                </a>
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="showComingSoonReferral(); toggleSidebar(false)">
                    <i class="fa-solid fa-tags"></i><span>Discounts & Offers</span>
                </a>
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="openPlatformReviewModal(); toggleSidebar(false)">
                    <i class="fa-solid fa-star"></i><span>Give a Review</span>
                </a>
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('report-issue', {}, { force: true }); toggleSidebar(false)">
                    <i class="fa-solid fa-bug"></i><span>Report an Issue</span>
                </a>
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="navigateTo('help', {}, { force: true }); toggleSidebar(false)">
                    <i class="fa-solid fa-circle-question"></i><span>Help Center</span>
                </a>
                <a href="javascript:void(0)" role="button" class="sidebar-link" onclick="openSettingsModal(); toggleSidebar(false)">
                    <i class="fa-solid fa-gear"></i><span>Settings</span>
                </a>
                <div class="sidebar-divider"></div>
                <a href="javascript:void(0)" role="button" class="sidebar-link sidebar-signin-link" id="sidebar-auth-link" onclick="openLoginModal(); toggleSidebar(false)">
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
                navigateTo('vendor-dash', {}, { force: true });
            } else {
                navigateTo('home', {}, { force: true });
            }
        }
    }).catch(err => {
        if (err.need_upgrade || (err.message && err.message.includes('not activated'))) {
            openBecomeVendorModal();
        } else {
            showPushNotification('Error', err.message || err);
        }
    });
}

function openBecomeVendorModal() {
    let currentUser = (window.state && window.state.user && window.state.user.id) ? window.state.user : null;
    if (!currentUser) {
        try {
            const cached = localStorage.getItem('ohati_user_session');
            if (cached) {
                const parsed = JSON.parse(cached);
                if (parsed && parsed.id) {
                    if (!window.state) window.state = {};
                    window.state.user = parsed;
                    currentUser = parsed;
                }
            }
        } catch(e) {}
    }

    if (!currentUser || !currentUser.id) {
        if (typeof openLoginModal === 'function') {
            openLoginModal();
        }
        return;
    }

    if (currentUser.has_vendor_profile || currentUser.vendor_id || (window.state && window.state.vendor)) {
        if (typeof switchAccountType === 'function') {
            switchAccountType('vendor');
        }
        return;
    }

    const html = `
        <div class="auth-modal-content p-24" style="max-width:520px; width:100%; border-radius:20px; background:var(--white);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div>
                    <h3 style="margin:0; font-weight:700;">Become an Ohati Vendor</h3>
                    <p style="margin:4px 0 0 0; font-size:0.78rem; color:var(--gray-500);">Activate your vendor business profile on your account.</p>
                </div>
                <button onclick="closeModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--gray-400);"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form onsubmit="handleBecomeVendorSubmit(event)">
                <div class="form-group mb-12">
                    <label class="form-label">Business / Brand Name</label>
                    <input type="text" class="form-input" id="bv-bizname" placeholder="e.g. Royal Crown Photography" required>
                </div>
                
                <div class="form-group mb-12">
                    <label class="form-label">Primary Vendor Category</label>
                    <select class="form-select" id="bv-category" required>
                        ${(() => {
                            const canonicalCats = (state.categories && Array.isArray(state.categories) && state.categories.length > 0) ? state.categories : [];
                            const sanitize = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s).replace(/[&<>"']/g, ''));
                            if (canonicalCats.length > 0) {
                                return canonicalCats.map(c => {
                                    const val = typeof c === 'string' ? c : (c.name || c.title || '');
                                    return `<option value="${sanitize(val)}">${sanitize(val)}</option>`;
                                }).join('');
                            }
                            return '<option value="" disabled selected>Loading categories...</option>';
                        })()}
                    </select>
                </div>
                
                <div class="form-group mb-12">
                    <label class="form-label">Years of Experience</label>
                    <input type="number" class="form-input" id="bv-experience" value="3" min="0" required>
                </div>

                <div class="form-group mb-12">
                    <label class="form-label">Business Location (City / Region)</label>
                    <input type="text" class="form-input" id="bv-location" placeholder="e.g. East Legon, Accra" required>
                </div>
                
                <div class="form-group mb-12">
                    <label class="form-label">Business Phone Number</label>
                    <input type="text" class="form-input" id="bv-phone" placeholder="e.g. +233 24 123 4567" value="${state.user?.phone || ''}" required>
                </div>

                <div class="form-group mb-12">
                    <label class="form-label">About Your Services</label>
                    <textarea class="form-textarea" id="bv-desc" style="min-height:70px;" placeholder="Tell clients about your event services..." required></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                    <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bv-submit-btn">Activate Vendor Profile</button>
                </div>
            </form>
        </div>
    `;
    openModal(html);
    
    const canonicalCats = (state.categories && Array.isArray(state.categories) && state.categories.length > 0) ? state.categories : [];
    const sanitize = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s).replace(/[&<>"']/g, ''));

    const populateBvSelect = (cats) => {
        const select = document.getElementById('bv-category');
        const submitBtn = document.getElementById('bv-submit-btn');
        if (select && Array.isArray(cats) && cats.length > 0) {
            select.innerHTML = cats.map(c => {
                const val = typeof c === 'string' ? c : (c.name || c.title || '');
                return `<option value="${sanitize(val)}">${sanitize(val)}</option>`;
            }).join('');
            if (submitBtn) submitBtn.disabled = false;
        }
    };

    if (canonicalCats.length === 0) {
        const submitBtn = document.getElementById('bv-submit-btn');
        if (submitBtn) submitBtn.disabled = true;
        API.getCategories().then(res => {
            const cats = Array.isArray(res) ? res : (res && Array.isArray(res.categories) ? res.categories : (res && Array.isArray(res.data) ? res.data : []));
            if (cats && cats.length > 0) {
                state.categories = cats;
                populateBvSelect(cats);
            } else {
                throw new Error('No categories available');
            }
        }).catch(() => {
            const select = document.getElementById('bv-category');
            if (select) {
                select.innerHTML = '<option value="" disabled selected>Unable to load categories. Please try again.</option>';
            }
            const submitBtn = document.getElementById('bv-submit-btn');
            if (submitBtn) submitBtn.disabled = true;
        });
    }
}

function handleBecomeVendorSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('bv-submit-btn');
    const categoryVal = document.getElementById('bv-category')?.value;

    if (!categoryVal) {
        showPushNotification('Category Required', 'Please select an active category from the list.');
        return;
    }

    if (btn) { btn.disabled = true; btn.textContent = 'Activating...'; }
    
    const payload = {
        business_name: document.getElementById('bv-bizname').value.trim(),
        category: categoryVal,
        experience: parseInt(document.getElementById('bv-experience').value) || 0,
        location: document.getElementById('bv-location').value.trim(),
        phone: document.getElementById('bv-phone').value.trim(),
        email: (window.state && window.state.user) ? window.state.user.email || '' : '',
        description: document.getElementById('bv-desc').value.trim()
    };
    
    API.registerVendor(payload)
        .then(res => {
            return API.getSession();
        })
        .then(res => {
            if (res && res.user) {
                state.user = res.user;
                if (res.vendor) state.vendor = res.vendor;
                localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
            }
            closeModal();
            showPushNotification('Vendor Profile Activated', 'Welcome to Ohati Vendor Mode!');
            updateSidebarUI();
            navigateTo('vendor-dash', {}, { force: true });
        })
        .catch(err => {
            if (btn) { btn.disabled = false; btn.textContent = 'Activate Vendor Profile'; }
            showPushNotification('Activation Error', err.message || 'Could not activate vendor profile.');
        });
}

// ── Unified Global Modal System ───────────────────────────────────────────
window._activeConfirmCallback = null;
window._activeCancelCallback = null;

function getGlobalModalRoot() {
    let root = document.getElementById('ohati-global-modal-root');
    if (!root) {
        root = document.createElement('div');
        root.id = 'ohati-global-modal-root';
        document.body.appendChild(root);
    }
    return root;
}

function showConfirmModal(options = {}) {
    const {
        title = 'Confirmation Required',
        message = 'Are you sure you want to proceed with this action?',
        icon = 'fa-triangle-exclamation',
        confirmText = 'Confirm',
        cancelText = 'Cancel',
        type = 'danger', // 'danger', 'warning', 'primary'
        onConfirm = null,
        onCancel = null
    } = options;

    // Close any currently active modal to prevent stacking
    closeConfirmModal();

    window._activeConfirmCallback = onConfirm;
    window._activeCancelCallback = onCancel;

    const root = getGlobalModalRoot();
    root.innerHTML = `
        <div class="ohati-confirm-modal-overlay" onclick="closeConfirmModal(false)">
            <div class="ohati-confirm-modal-card" onclick="event.stopPropagation()">
                <button class="blog-modal-close-btn" onclick="closeConfirmModal(false)"><i class="fa-solid fa-xmark"></i></button>

                <div class="ohati-confirm-icon-box ${type}">
                    <i class="fa-solid ${icon}"></i>
                </div>

                <h3 class="ohati-confirm-title">${escapeHtml(title)}</h3>
                <div class="ohati-confirm-message">${typeof message === 'string' ? message : message}</div>

                <div class="ohati-confirm-actions">
                    <button type="button" class="btn-confirm-cancel" onclick="closeConfirmModal(false)">${escapeHtml(cancelText)}</button>
                    <button type="button" class="btn-confirm-submit-${type}" onclick="handleConfirmModalAction()">${escapeHtml(confirmText)}</button>
                </div>
            </div>
        </div>
    `;

    root.classList.add('open');
    document.body.classList.add('modal-open');
    return new Promise((resolve) => {
        window._activeConfirmPromiseResolve = resolve;
    });
}

function handleConfirmModalAction() {
    const cb = window._activeConfirmCallback;
    const resolve = window._activeConfirmPromiseResolve;
    closeConfirmModal(true);
    if (typeof cb === 'function') cb();
    if (typeof resolve === 'function') resolve(true);
}

function closeConfirmModal(confirmed = false) {
    const root = document.getElementById('ohati-global-modal-root');
    if (root) {
        root.classList.remove('open');
        root.innerHTML = '';
    }
    document.body.classList.remove('modal-open');

    if (!confirmed) {
        const cancelCb = window._activeCancelCallback;
        const resolve = window._activeConfirmPromiseResolve;
        if (typeof cancelCb === 'function') cancelCb();
        if (typeof resolve === 'function') resolve(false);
    }

    window._activeConfirmCallback = null;
    window._activeCancelCallback = null;
    window._activeConfirmPromiseResolve = null;
}

window.showConfirmModal = showConfirmModal;
window.closeConfirmModal = closeConfirmModal;

// Global Escape Key Listener for Modals
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const root = document.getElementById('ohati-global-modal-root');
        if (root && root.classList.contains('open')) {
            closeConfirmModal(false);
        } else if (typeof closeModal === 'function') {
            closeModal();
        }
    }
});

function openModal(contentHTML) {
    const overlay = document.getElementById('modal-overlay');
    const content = document.getElementById('modal-content');
    if (!overlay || !content) return;
    content.innerHTML = contentHTML;
    overlay.style.visibility = 'visible';
    overlay.style.pointerEvents = 'auto';
    overlay.style.display = 'flex';
    overlay.classList.add('open');
    document.body.classList.add('modal-open');
}

function closeModal() {
    const overlay = document.getElementById('modal-overlay');
    if (overlay) {
        overlay.classList.remove('open');
        overlay.style.visibility = 'hidden';
        overlay.style.pointerEvents = 'none';
    }

    // Check if any other modal is open before unlocking body scroll
    const hasOtherModals = document.querySelector('.blog-modal-backdrop:not([style*="none"]), .ohati-confirm-modal-overlay, .welcome-popup-overlay.open');
    if (!hasOtherModals) {
        document.body.classList.remove('modal-open');
    }
}

window.clearAllAuthOverlays = function () {
    try {
        if (typeof closeModal === 'function') closeModal();
        if (typeof toggleSidebar === 'function') toggleSidebar(false);
        if (typeof unlockMandatoryAuthScreen === 'function') unlockMandatoryAuthScreen();

        ['sidebar-overlay', 'app-sidebar-overlay', 'mandatory-auth-lock-overlay', 'modal-overlay', 'filter-drawer-overlay'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.classList.remove('open', 'active');
                if (id === 'mandatory-auth-lock-overlay') {
                    el.style.display = 'none';
                    try { el.remove(); } catch (e) { }
                } else {
                    el.style.visibility = 'hidden';
                    el.style.pointerEvents = 'none';
                }
            }
        });

        document.body.classList.remove('modal-open', 'sidebar-open', 'no-scroll');
        document.body.style.overflow = '';
        document.body.style.position = '';
    } catch (err) {
        console.warn('Error clearing auth overlays:', err);
    }
};

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

    const renderCategoryOptions = (cats) => `
        <option value="">All Categories</option>
        ${(cats || []).map(c => `<option value="${c.name}" ${state.filters.category === c.name ? 'selected' : ''}>${c.name}</option>`).join('')}
    `;

    const categories = state.categories || [];

    drawer.innerHTML = `
        <div class="flex-between mb-16">
            <h3 style="font-size:1.1rem;">Filter Vendors</h3>
            <button class="btn btn-ghost btn-sm" onclick="closeFilterDrawer()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-select" id="filter-category">
                ${renderCategoryOptions(categories)}
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

    if (!categories.length) {
        API.getCategories().then(cats => {
            if (cats && cats.length) {
                state.categories = cats;
                const select = document.getElementById('filter-category');
                if (select) select.innerHTML = renderCategoryOptions(cats);
            }
        }).catch(() => {});
    }
}

function applyFilters() {
    state.filters.category = document.getElementById('filter-category')?.value || '';
    state.filters.location = document.getElementById('filter-location')?.value || '';
    state.filters.rating = document.getElementById('filter-rating')?.value || '';
    state.filters.min_price = document.getElementById('filter-min-price')?.value || '';
    state.filters.max_price = document.getElementById('filter-max-price')?.value || '';
    state.filters.verified_only = document.getElementById('filter-verified')?.checked ? 1 : 0;
    state.filters.premium_only = document.getElementById('filter-premium')?.checked ? 1 : 0;
    state.filters.is_refined = true;
    closeFilterDrawer();
    initSearchScreen();
}

function resetAllFilters() {
    state.filters = { category: '', location: '', search: '', rating: '', verified_only: 0, premium_only: 0, instant_booking: 0, min_price: '', max_price: '', is_refined: false };
    closeFilterDrawer();
    initSearchScreen();
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
    let currentUser = (window.state && window.state.user && window.state.user.id) ? window.state.user : null;
    if (!currentUser) {
        try {
            const cached = localStorage.getItem('ohati_user_session');
            if (cached) {
                const parsed = JSON.parse(cached);
                if (parsed && parsed.id) currentUser = parsed;
            }
        } catch(e) {}
    }

    if (currentUser && currentUser.id) {
        if (currentUser.has_vendor_profile || currentUser.vendor_id || (window.state && window.state.vendor)) {
            switchAccountType('vendor');
        } else {
            openBecomeVendorModal();
        }
    } else {
        if (typeof openLoginModal === 'function') openLoginModal();
    }
}
window.openPremiumModal = openPremiumModal;

function openSettingsModal() {
    openModal(`
        <div class="auth-modal-header"><h2 class="auth-modal-title">Settings</h2></div>
        <div class="profile-menu-section" style="padding:0;">
            <div class="profile-menu-item" onclick="if(typeof window.toggleAppTheme==='function') window.toggleAppTheme(); closeModal();">
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
window.openSettingsModal = openSettingsModal;

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

window.openAppDownloadUrl = function (platform) {
    const isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
    if (isNative) {
        return; // Hidden on native Android/iOS Capacitor webview
    }

    const ua = (navigator.userAgent || navigator.vendor || window.opera || '').toLowerCase();
    const isIOS = /iphone|ipad|ipod/i.test(ua);
    const isAndroid = /android/i.test(ua);

    const playStoreUrl = 'https://play.google.com/store/apps/details?id=com.ohati.app';
    const appStoreUrl = 'https://apps.apple.com/app/ohati/id6740000000';

    if (platform === 'android') {
        window.open(playStoreUrl, '_blank');
        return;
    }
    if (platform === 'ios') {
        window.open(appStoreUrl, '_blank');
        return;
    }

    // Auto-detect browser device type
    if (isAndroid) {
        window.open(playStoreUrl, '_blank');
    } else if (isIOS) {
        window.open(appStoreUrl, '_blank');
    } else {
        showAppDownloadModal();
    }
};
window.showBadgeMessage = window.openAppDownloadUrl;

window.initWebDownloadBanner = function() {
    try {
        const isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) || window.location.protocol === 'capacitor:';
        if (isNative) return;

        if (document.getElementById('web-app-download-banner')) return;

        const ua = (navigator.userAgent || navigator.vendor || window.opera || '').toLowerCase();
        const isIOS = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isAndroid = /android/i.test(ua);

        let storeUrl = 'https://ohati.com/download';
        let btnText = 'Get App';
        let iconClass = 'fa-solid fa-mobile-screen-button';

        if (isIOS) {
            storeUrl = 'https://apps.apple.com/ng/app/ohati/id6801835847';
            btnText = 'App Store';
            iconClass = 'fa-brands fa-apple';
        } else if (isAndroid) {
            storeUrl = 'https://play.google.com/store/apps/details?id=com.ohati.app';
            btnText = 'Google Play';
            iconClass = 'fa-brands fa-google-play';
        }

        const banner = document.createElement('div');
        banner.id = 'web-app-download-banner';
        banner.className = 'web-download-banner-strip';
        banner.innerHTML = `
            <div class="banner-content">
                <span class="banner-badge"><i class="fa-solid fa-sparkles"></i> App Available</span>
                <span class="banner-text">Get the official Ohati app — Fast booking, real-time chat & instant alerts!</span>
            </div>
            <div class="banner-actions">
                <a href="${storeUrl}" target="_blank" rel="noopener" class="banner-btn">
                    <i class="${iconClass}"></i> ${btnText}
                </a>
                <button onclick="dismissWebDownloadBanner()" class="banner-close-btn" title="Dismiss">&times;</button>
            </div>
        `;

        const appHeader = document.getElementById('app-header');
        if (appHeader && appHeader.parentNode) {
            appHeader.parentNode.insertBefore(banner, appHeader);
        } else {
            document.body.prepend(banner);
        }
    } catch(e) {}
};

window.dismissWebDownloadBanner = function() {
    const banner = document.getElementById('web-app-download-banner');
    if (banner) banner.remove();
};

function showAppDownloadModal() {
    const playStoreUrl = 'https://play.google.com/store/apps/details?id=com.ohati.app';
    const appStoreUrl = 'https://apps.apple.com/app/ohati/id6740000000';
    openModal(`
        <div style="text-align:center; padding:24px 16px;">
            <div style="width:68px; height:68px; background:linear-gradient(135deg, #1B2B4B, #0F172A); color:#F2A735; border-radius:20px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:1.8rem; box-shadow:0 10px 25px rgba(27,43,75,0.25);">
                <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <h3 style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:800; color:var(--gray-900, #0F172A); margin:0 0 6px 0;">Get Ohati Mobile App</h3>
            <p style="font-size:0.88rem; color:var(--gray-600, #475569); line-height:1.5; margin:0 0 20px 0;">
                Experience faster booking, real-time chat notifications, and vendor updates on your phone.
            </p>
            <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px;">
                <a href="${playStoreUrl}" target="_blank" class="btn btn-primary btn-full" style="height:48px; background:#34A853; border-color:#34A853; font-weight:700; border-radius:12px; display:flex; align-items:center; justify-content:center; gap:10px; text-decoration:none; color:#fff;">
                    <i class="fa-brands fa-google-play" style="font-size:1.2rem;"></i> Get it on Google Play
                </a>
                <a href="${appStoreUrl}" target="_blank" class="btn btn-primary btn-full" style="height:48px; background:#000000; border-color:#000000; font-weight:700; border-radius:12px; display:flex; align-items:center; justify-content:center; gap:10px; text-decoration:none; color:#fff;">
                    <i class="fa-brands fa-apple" style="font-size:1.25rem;"></i> Download on App Store
                </a>
            </div>
            <button class="btn btn-outline btn-full" onclick="closeModal()" style="border-radius:12px; font-weight:700;">Close</button>
        </div>
    `);
}
window.showAppDownloadModal = showAppDownloadModal;

function openAllCategoriesModal() {
    const renderCategoryCards = (cats) => (cats || []).map(c => `
        <div class="category-item-modal" onclick="selectCategoryFilter('${c.name}'); closeModal();" data-name="${c.name.toLowerCase()}" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; border:1px solid var(--gray-200); background:var(--gray-50); cursor:pointer; transition:all var(--anim-fast) ease;">
            <div style="font-size:1.1rem; color:var(--accent); display:flex; align-items:center; justify-content:center; width:28px; height:28px;"><i class="fa-solid fa-${c.icon}"></i></div>
            <span style="font-size:0.8rem; font-weight:600; color:var(--gray-800);">${c.name}</span>
        </div>
    `).join('');

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
            ${renderCategoryCards(categories)}
        </div>
    `;

    openModal(modalHTML);

    if (!categories.length) {
        API.getCategories().then(cats => {
            if (cats && cats.length) {
                state.categories = cats;
                const grid = document.getElementById('modal-category-grid');
                if (grid) grid.innerHTML = renderCategoryCards(cats);
            }
        }).catch(() => {});
    }
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

window.dismissVendorPromoPopupToday = function () {
    localStorage.setItem('ohati_vendor_promo_dismissed', Date.now().toString());
    closeModal();
};

window.dismissSponsoredPopupToday = function () {
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

        let bannerImg = window.resolveImageUrl(ad.banner_url || ad.vendor_cover || ad.cover_photo, 'cover');

        const html = `
            <div class="auth-modal-header" style="position:relative; text-align:center;">
                <span style="background:#0F172A; color:#FBBF24; border:1px solid #F59E0B; font-size:0.68rem; font-weight:800; padding:4px 12px; border-radius:12px; display:inline-flex; align-items:center; gap:4px; margin-bottom:8px; text-transform:uppercase;">
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

function showComingSoonReferral(title = 'Refer & Earn', icon = 'fa-gift') {
    openModal(`
        <div style="text-align:center; padding:32px 20px 24px;">
            <div style="width:72px; height:72px; background:rgba(242, 167, 53, 0.15); color:var(--accent, #F2A735); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:2rem; border:2px solid rgba(242,167,53,0.3); box-shadow:0 8px 24px rgba(242,167,53,0.2);">
                <i class="fa-solid ${icon}"></i>
            </div>
            <span class="badge badge-warning" style="font-size:0.7rem; padding:4px 12px; font-weight:800; background:#F59E0B; color:#fff; border-radius:12px; display:inline-block; margin-bottom:10px; letter-spacing:0.5px;">COMING SOON</span>
            <h3 style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:800; color:var(--gray-900, #0F172A); margin:0 0 6px 0;">${title}</h3>
            <p style="font-size:0.92rem; color:var(--gray-600, #475569); line-height:1.5; margin:0 0 24px 0; max-width:320px; margin-left:auto; margin-right:auto;">
                We're building something special! This feature will be available shortly in the upcoming app update.
            </p>
            <button class="btn btn-primary btn-full" onclick="closeModal()" style="height:46px; font-weight:800; border-radius:14px; font-size:0.95rem;">Got It</button>
        </div>
    `);
}
window.showComingSoonReferral = showComingSoonReferral;
window.openReferAndEarnModal = function() { showComingSoonReferral('Refer & Earn', 'fa-gift'); };
window.openDiscountsAndOffersModal = function() { showComingSoonReferral('Discounts & Offers', 'fa-tags'); };

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
window.openAllPlatformReviewsModal = openAllPlatformReviewsModal;

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
window.openPlatformReviewModal = openPlatformReviewModal;

window.setSelectRating = function (rating) {
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

window.submitPlatformReviewForm = function (event) {
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

// ── BLOCK & REPORT USER MODALS ──────────────────────────────────────────
window.showBlockUserModal = function (targetUserId, targetName = 'User') {
    const cleanName = escapeHTML(targetName);
    const html = `
        <div class="auth-modal-header" style="text-align:center;">
            <div style="width:56px; height:56px; border-radius:50%; background:rgba(239,68,68,0.1); color:#EF4444; display:inline-flex; align-items:center; justify-content:center; font-size:1.6rem; margin-bottom:12px;">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <h2 class="auth-modal-title" style="font-family:'Fraunces', serif; color:var(--gray-900);">Block ${cleanName}?</h2>
            <p class="auth-modal-subtitle" style="color:var(--gray-500); font-size:0.8rem;">They will no longer be able to send you messages or contact you on Ohati.</p>
        </div>
        <div style="padding:10px 0;">
            <div class="form-group mb-16">
                <label class="form-label" style="font-weight:700; font-size:0.75rem; color:var(--gray-700); margin-bottom:6px; display:block;">Reason for blocking (Optional)</label>
                <select id="block-reason-select" class="form-input" style="width:100%; border-radius:10px; padding:10px; border:1px solid var(--gray-200); background:#fff; font-size:0.8rem;">
                    <option value="Unwanted Messages / Spam">Unwanted Messages / Spam</option>
                    <option value="Harassment or Offensive Behavior">Harassment or Offensive Behavior</option>
                    <option value="Fraudulent or Suspicious Activity">Fraudulent or Suspicious Activity</option>
                    <option value="Other Reason">Other Reason</option>
                </select>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline btn-full" onclick="closeModal()" style="border-radius:10px;">Cancel</button>
                <button type="button" class="btn btn-primary btn-full" onclick="submitBlockUserAction(${targetUserId})" style="background:linear-gradient(135deg, #EF4444, #DC2626); border:none; color:#fff; border-radius:10px; font-weight:700;">Block User</button>
            </div>
        </div>
    `;
    openModal(html);
};

window.submitBlockUserAction = function (targetUserId) {
    const reasonSelect = document.getElementById('block-reason-select');
    const reason = reasonSelect ? reasonSelect.value : 'User Blocked';
    API.blockUser(targetUserId, reason).then(res => {
        closeModal();
        showPushNotification('User Blocked', res.message || 'You have blocked this user.');
    }).catch(err => {
        closeModal();
        showPushNotification('User Blocked', 'User blocked from messaging.');
    });
};

window.showReportUserModal = function (targetUserId, targetName = 'User') {
    const cleanName = escapeHTML(targetName);
    const html = `
        <div class="auth-modal-header" style="text-align:center;">
            <div style="width:56px; height:56px; border-radius:50%; background:rgba(245,158,11,0.1); color:#F59E0B; display:inline-flex; align-items:center; justify-content:center; font-size:1.6rem; margin-bottom:12px;">
                <i class="fa-solid fa-shield-cat"></i>
            </div>
            <h2 class="auth-modal-title" style="font-family:'Fraunces', serif; color:var(--gray-900);">Report ${cleanName}</h2>
            <p class="auth-modal-subtitle" style="color:var(--gray-500); font-size:0.8rem;">Help us keep Ohati safe. Select the reason for your report.</p>
        </div>
        <form id="report-user-form" onsubmit="submitReportUserAction(event, ${targetUserId})">
            <div class="form-group mb-12">
                <label class="form-label" style="font-weight:700; font-size:0.75rem; color:var(--gray-700); margin-bottom:6px; display:block;">Violation Type</label>
                <div class="report-chips-wrap" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                    <label class="report-chip-item" style="cursor:pointer;">
                        <input type="radio" name="report_reason" value="Harassment" checked style="display:none;" onchange="updateReportChipUI(this)">
                        <span class="chip-badge active" style="padding:6px 12px; border-radius:20px; border:1px solid var(--primary); background:var(--primary); color:#fff; font-size:0.75rem; font-weight:600; display:inline-block;">Harassment</span>
                    </label>
                    <label class="report-chip-item" style="cursor:pointer;">
                        <input type="radio" name="report_reason" value="Spam / Scams" style="display:none;" onchange="updateReportChipUI(this)">
                        <span class="chip-badge" style="padding:6px 12px; border-radius:20px; border:1px solid var(--gray-200); background:var(--gray-100); color:var(--gray-700); font-size:0.75rem; font-weight:600; display:inline-block;">Spam / Scams</span>
                    </label>
                    <label class="report-chip-item" style="cursor:pointer;">
                        <input type="radio" name="report_reason" value="Inappropriate Content" style="display:none;" onchange="updateReportChipUI(this)">
                        <span class="chip-badge" style="padding:6px 12px; border-radius:20px; border:1px solid var(--gray-200); background:var(--gray-100); color:var(--gray-700); font-size:0.75rem; font-weight:600; display:inline-block;">Inappropriate Content</span>
                    </label>
                    <label class="report-chip-item" style="cursor:pointer;">
                        <input type="radio" name="report_reason" value="Fake Profile" style="display:none;" onchange="updateReportChipUI(this)">
                        <span class="chip-badge" style="padding:6px 12px; border-radius:20px; border:1px solid var(--gray-200); background:var(--gray-100); color:var(--gray-700); font-size:0.75rem; font-weight:600; display:inline-block;">Fake Profile</span>
                    </label>
                </div>
            </div>
            <div class="form-group mb-16">
                <label class="form-label" style="font-weight:700; font-size:0.75rem; color:var(--gray-700); margin-bottom:6px; display:block;">Additional Details (Optional)</label>
                <textarea id="report-user-details" class="form-input" rows="3" placeholder="Provide extra context to help our moderation team..." style="width:100%; border-radius:10px; padding:10px; border:1px solid var(--gray-200); font-size:0.8rem; resize:none; font-family:inherit; box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline btn-full" onclick="closeModal()" style="border-radius:10px;">Cancel</button>
                <button type="submit" class="btn btn-primary btn-full" style="background:var(--primary); color:#fff; border-radius:10px; font-weight:700;">Submit Report</button>
            </div>
        </form>
    `;
    openModal(html);
};

window.updateReportChipUI = function (radioEl) {
    const parentForm = radioEl.closest('form');
    if (!parentForm) return;
    const chips = parentForm.querySelectorAll('.chip-badge');
    chips.forEach(chip => {
        chip.style.background = 'var(--gray-100)';
        chip.style.borderColor = 'var(--gray-200)';
        chip.style.color = 'var(--gray-700)';
    });
    const selectedBadge = radioEl.nextElementSibling;
    if (selectedBadge) {
        selectedBadge.style.background = 'var(--primary)';
        selectedBadge.style.borderColor = 'var(--primary)';
        selectedBadge.style.color = '#fff';
    }
};

window.submitReportUserAction = function (event, targetUserId) {
    event.preventDefault();
    const selectedRadio = document.querySelector('input[name="report_reason"]:checked');
    const reason = selectedRadio ? selectedRadio.value : 'Inappropriate Behavior';
    const details = document.getElementById('report-user-details') ? document.getElementById('report-user-details').value.trim() : '';

    API.reportUser(targetUserId, reason, details).then(res => {
        closeModal();
        showPushNotification('Report Received', res.message || 'Report submitted to moderation team.');
    }).catch(err => {
        closeModal();
        showPushNotification('Report Received', 'Thank you. Your report has been dispatched to Ohati moderators.');
    });
};

window.showReportCommentModal = function (commentId, authorName = 'Comment') {
    const cleanAuthor = escapeHTML(authorName);
    const html = `
        <div class="auth-modal-header" style="text-align:center;">
            <div style="width:56px; height:56px; border-radius:50%; background:rgba(239,68,68,0.1); color:#EF4444; display:inline-flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:12px;">
                <i class="fa-solid fa-flag"></i>
            </div>
            <h2 class="auth-modal-title" style="font-family:'Fraunces', serif; color:var(--gray-900);">Report Comment</h2>
            <p class="auth-modal-subtitle" style="color:var(--gray-500); font-size:0.8rem;">Flag inappropriate comment by <strong>${cleanAuthor}</strong></p>
        </div>
        <form id="report-comment-form" onsubmit="submitReportCommentAction(event, ${commentId})">
            <div class="form-group mb-12">
                <label class="form-label" style="font-weight:700; font-size:0.75rem; color:var(--gray-700); margin-bottom:6px; display:block;">Reason for Reporting</label>
                <div class="report-chips-wrap" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                    <label class="report-chip-item" style="cursor:pointer;">
                        <input type="radio" name="comment_report_reason" value="Offensive Language" checked style="display:none;" onchange="updateReportChipUI(this)">
                        <span class="chip-badge active" style="padding:6px 12px; border-radius:20px; border:1px solid var(--primary); background:var(--primary); color:#fff; font-size:0.75rem; font-weight:600; display:inline-block;">Offensive Language</span>
                    </label>
                    <label class="report-chip-item" style="cursor:pointer;">
                        <input type="radio" name="comment_report_reason" value="Spam / Self Promotion" style="display:none;" onchange="updateReportChipUI(this)">
                        <span class="chip-badge" style="padding:6px 12px; border-radius:20px; border:1px solid var(--gray-200); background:var(--gray-100); color:var(--gray-700); font-size:0.75rem; font-weight:600; display:inline-block;">Spam / Promotion</span>
                    </label>
                    <label class="report-chip-item" style="cursor:pointer;">
                        <input type="radio" name="comment_report_reason" value="Harassment" style="display:none;" onchange="updateReportChipUI(this)">
                        <span class="chip-badge" style="padding:6px 12px; border-radius:20px; border:1px solid var(--gray-200); background:var(--gray-100); color:var(--gray-700); font-size:0.75rem; font-weight:600; display:inline-block;">Harassment</span>
                    </label>
                </div>
            </div>
            <div class="form-group mb-16">
                <label class="form-label" style="font-weight:700; font-size:0.75rem; color:var(--gray-700); margin-bottom:6px; display:block;">Notes (Optional)</label>
                <textarea id="report-comment-details" class="form-input" rows="2" placeholder="Briefly describe why this comment should be removed..." style="width:100%; border-radius:10px; padding:10px; border:1px solid var(--gray-200); font-size:0.8rem; resize:none; font-family:inherit; box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline btn-full" onclick="closeModal()" style="border-radius:10px;">Cancel</button>
                <button type="submit" class="btn btn-primary btn-full" style="background:var(--primary); color:#fff; border-radius:10px; font-weight:700;">Submit Flag</button>
            </div>
        </form>
    `;
    openModal(html);
};

window.submitReportCommentAction = function (event, commentId) {
    event.preventDefault();
    const selectedRadio = document.querySelector('input[name="comment_report_reason"]:checked');
    const reason = selectedRadio ? selectedRadio.value : 'Inappropriate Content';
    const details = document.getElementById('report-comment-details') ? document.getElementById('report-comment-details').value.trim() : '';

    API.reportComment(commentId, reason, details).then(res => {
        closeModal();
        showPushNotification('Comment Flagged', res.message || 'Comment report submitted to blog moderators.');
    }).catch(err => {
        closeModal();
        showPushNotification('Comment Flagged', 'Thank you. Comment reported for moderation.');
    });
};

window.openDesktopPopupModal = function(screenId, params = {}) {
    if (screenId === 'post-job') {
        if (window.JobsModule && typeof window.JobsModule.openCreateJobModal === 'function') {
            window.JobsModule.openCreateJobModal();
            return;
        }
    }

    const titles = {
        'notifications': 'Notifications',
        'post-job': 'Post an Event Job',
        'user-jobs': 'My Posted Jobs',
        'vendor-jobs': 'Browse Event Jobs',
        'compare': 'Compare Vendors',
        'favorites': 'Saved Vendors',
        'blog': 'Blog & Guides',
        'blog-detail': 'Blog Article',
        'report-issue': 'Report an Issue',
        'help': 'Help Center',
        'about': 'About Ohati'
    };

    const title = titles[screenId] || 'Ohati';
    const containerId = 'desktop-modal-content-' + Date.now();

    const modalHtml = `
        <div class="desktop-popup-wrapper" style="width:94vw; max-width:1260px; height:88vh; max-height:90vh; display:flex; flex-direction:column; background:var(--card-bg, #ffffff); border-radius:20px; overflow:hidden; box-shadow:0 25px 60px rgba(0,0,0,0.38); border:1px solid var(--gray-200, rgba(255,255,255,0.12)); position:relative; margin:auto;">
            <div class="desktop-popup-header" style="display:flex; align-items:center; justify-content:space-between; padding:18px 28px; background:var(--card-header-bg, #f8fafc); border-bottom:1px solid var(--gray-200, #e2e8f0);">
                <h3 style="margin:0; font-size:1.25rem; font-weight:700; color:var(--gray-900, #0f172a); display:flex; align-items:center; gap:12px;">
                    <i class="fa-solid fa-layer-group" style="color:var(--accent, #F2A735);"></i> ${escapeHtml(title)}
                </h3>
                <button onclick="closeModal()" style="background:rgba(0,0,0,0.06); border:none; color:var(--gray-600, #64748b); width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.2rem; transition:all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.15)'; this.style.color='#EF4444';" onmouseout="this.style.background='rgba(0,0,0,0.06)'; this.style.color='var(--gray-600, #64748b)';">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="${containerId}" class="desktop-popup-body scrollable-y" style="padding:24px; overflow-y:auto; flex:1; height:calc(100% - 65px);">
                <div style="text-align:center; padding:60px;"><i class="fa-solid fa-spinner fa-spin" style="font-size:2.2rem; color:var(--accent, #F2A735);"></i></div>
            </div>
        </div>
    `;

    openModal(modalHtml);

    setTimeout(() => {
        const popupBody = document.getElementById(containerId);
        if (!popupBody) return;

        try {
            if (screenId === 'notifications') {
                if (typeof renderNotificationsScreen === 'function') popupBody.innerHTML = renderNotificationsScreen(params);
                else if (typeof initNotificationsScreen === 'function') initNotificationsScreen(params, popupBody);
            } else if (screenId === 'user-jobs') {
                if (typeof initUserJobsScreen === 'function') initUserJobsScreen(params, popupBody);
            } else if (screenId === 'vendor-jobs') {
                if (typeof initVendorJobsScreen === 'function') initVendorJobsScreen(params, popupBody);
            } else if (screenId === 'compare') {
                if (typeof initCompareScreen === 'function') initCompareScreen(params, popupBody);
            } else if (screenId === 'favorites') {
                if (typeof initFavoritesScreen === 'function') initFavoritesScreen(params, popupBody);
            } else if (screenId === 'blog' || screenId === 'blog-detail') {
                if (typeof initBlogScreen === 'function') initBlogScreen(params, popupBody);
            } else if (screenId === 'report-issue') {
                if (typeof initReportIssueScreen === 'function') initReportIssueScreen(params, popupBody);
            } else if (screenId === 'help') {
                if (typeof initHelpScreen === 'function') initHelpScreen(params, popupBody);
            } else if (screenId === 'about') {
                if (typeof initAboutScreen === 'function') initAboutScreen(params, popupBody);
            }
        } catch (err) {
            console.error("Desktop popup render error:", err);
        }
    }, 50);
};

// ══════════════════════════════════════════════════════════════════════════════
// SAFE MANDATORY APP UPDATE SUBSYSTEM (ISOLATED & FAIL-OPEN)
// ══════════════════════════════════════════════════════════════════════════════

window.isStrictSemVer = function(version) {
    if (typeof version !== 'string') return false;
    return /^\d+\.\d+\.\d+$/.test(version.trim());
};

window.compareSemVer = function(v1, v2) {
    if (!window.isStrictSemVer(v1) || !window.isStrictSemVer(v2)) return 0;
    const p1 = v1.trim().split('.').map(n => parseInt(n, 10));
    const p2 = v2.trim().split('.').map(n => parseInt(n, 10));
    for (let i = 0; i < 3; i++) {
        if (p1[i] > p2[i]) return 1;
        if (p1[i] < p2[i]) return -1;
    }
    return 0;
};

window.getSafePlatformStoreUrl = function(rawUrl, platform) {
    const OFFICIAL_STORES = {
        android: 'https://play.google.com/store/apps/details?id=com.ohati.app',
        ios: 'https://apps.apple.com/ng/app/ohati/id6801835847'
    };
    const fallback = OFFICIAL_STORES[platform] || OFFICIAL_STORES.android;
    if (!rawUrl || typeof rawUrl !== 'string') return fallback;

    try {
        const parsed = new URL(rawUrl.trim());
        const hostname = parsed.hostname.toLowerCase();
        if (platform === 'ios') {
            if (hostname === 'apps.apple.com' || hostname === 'itunes.apple.com') {
                return parsed.href;
            }
        } else if (platform === 'android') {
            if (hostname === 'play.google.com') {
                return parsed.href;
            }
        }
    } catch (e) {}

    return fallback;
};

window.openOfficialPlatformStore = async function(rawUrl, platform) {
    const safeUrl = window.getSafePlatformStoreUrl(rawUrl, platform);
    try {
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Browser && typeof window.Capacitor.Plugins.Browser.open === 'function') {
            await window.Capacitor.Plugins.Browser.open({ url: safeUrl });
            return;
        }
    } catch (pluginErr) {
        console.warn("[AppUpdate] Capacitor Browser plugin failed, falling back to window.open", pluginErr);
    }

    try {
        window.open(safeUrl, '_system') || window.open(safeUrl, '_blank');
    } catch (openErr) {
        console.error("[AppUpdate] Window open fallback error", openErr);
        try { window.location.href = safeUrl; } catch (locErr) {}
    }
};

window.showMandatoryUpdateLock = function(policy) {
    if (!policy || typeof policy !== 'object') return;

    // Safety: Dismiss splash / loading screen first so user is never stuck
    const loadingScreen = document.getElementById('screen-loading');
    if (loadingScreen) {
        loadingScreen.style.opacity = '0';
        loadingScreen.style.display = 'none';
        try { loadingScreen.remove(); } catch (e) {}
    }

    // Single instance: if already visible, do not recreate
    let overlay = document.getElementById('mandatory-update-overlay');
    if (overlay) return;

    const isIos = (policy.platform === 'ios');
    const storeName = isIos ? 'Apple App Store' : 'Google Play Store';
    const storeBtnText = isIos ? 'Update on App Store' : 'Update on Google Play';
    const storeBtnBg = isIos ? '#000000' : '#34A853';
    const storeBtnIcon = isIos ? 'fa-brands fa-apple' : 'fa-brands fa-google-play';

    overlay = document.createElement('div');
    overlay.id = 'mandatory-update-overlay';
    overlay.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15, 25, 35, 0.85); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:9999999; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box; overflow-y:auto; font-family:"Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;';

    const card = document.createElement('div');
    card.style.cssText = 'background:#FFFFFF; border:1px solid #E2E8F0; border-radius:24px; padding:36px 24px 28px; max-width:420px; width:100%; text-align:center; box-shadow:0 25px 60px rgba(0,0,0,0.3); color:#1E293B; box-sizing:border-box; animation:modalFadeIn 0.25s ease;';

    // Ohati Branded Icon: Primary Navy (#1B2B4B) + Accent Gold (#F2A735)
    const iconWrap = document.createElement('div');
    iconWrap.style.cssText = 'width:70px; height:70px; background:linear-gradient(135deg, #1B2B4B, #0F1923); border-radius:22px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; box-shadow:0 10px 25px rgba(27,43,75,0.25); font-size:1.85rem; color:#F2A735;';
    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-mobile-screen-button';
    iconWrap.appendChild(icon);
    card.appendChild(iconWrap);

    // Title
    const title = document.createElement('h2');
    title.style.cssText = 'font-family:"Fraunces",serif; font-size:1.45rem; font-weight:800; color:#1B2B4B; margin:0 0 10px 0; line-height:1.25;';
    title.textContent = 'App Update Required';
    card.appendChild(title);

    // Universal update message
    const message = document.createElement('p');
    message.style.cssText = 'font-size:0.92rem; color:#475569; line-height:1.55; margin:0 0 22px 0;';
    message.textContent = policy.release_notes || policy.message || 'A new version of Ohati is available. Please update your application to continue enjoying the latest features and improvements.';
    card.appendChild(message);

    // Version Pill
    const versionPill = document.createElement('div');
    versionPill.style.cssText = 'background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:12px 16px; margin-bottom:24px; display:flex; justify-content:space-around; align-items:center; font-size:0.8rem;';
    
    const curVerBox = document.createElement('div');
    curVerBox.innerHTML = '<span style="color:#64748B; display:block; margin-bottom:3px; font-weight:600; font-size:0.75rem;">Your Version</span>';
    const curVerVal = document.createElement('strong');
    curVerVal.style.color = '#E05A47';
    curVerVal.style.fontSize = '0.9rem';
    curVerVal.textContent = policy.installed_version || '1.0.37';
    curVerBox.appendChild(curVerVal);
    versionPill.appendChild(curVerBox);

    const sep = document.createElement('div');
    sep.style.cssText = 'width:1px; height:28px; background:#CBD5E1;';
    versionPill.appendChild(sep);

    const minVerBox = document.createElement('div');
    minVerBox.innerHTML = '<span style="color:#64748B; display:block; margin-bottom:3px; font-weight:600; font-size:0.75rem;">Latest Version</span>';
    const minVerVal = document.createElement('strong');
    minVerVal.style.color = '#1B2B4B';
    minVerVal.style.fontSize = '0.9rem';
    minVerVal.textContent = policy.latest_version || policy.minimum_version || '1.0.40';
    minVerBox.appendChild(minVerVal);
    versionPill.appendChild(minVerBox);

    card.appendChild(versionPill);

    // Primary Action Button
    const updateBtn = document.createElement('button');
    updateBtn.style.cssText = `width:100%; height:50px; background:${storeBtnBg}; border:none; border-radius:12px; color:#FFFFFF; font-weight:700; font-size:0.95rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; box-shadow:0 6px 18px rgba(0,0,0,0.18); transition:transform 0.15s ease;`;
    
    const btnIcon = document.createElement('i');
    btnIcon.className = storeBtnIcon;
    btnIcon.style.fontSize = '1.2rem';
    updateBtn.appendChild(btnIcon);

    const btnText = document.createElement('span');
    btnText.textContent = storeBtnText;
    updateBtn.appendChild(btnText);

    updateBtn.onclick = function() {
        window.openOfficialPlatformStore(policy.store_url, policy.platform);
    };
    card.appendChild(updateBtn);

    // Dynamic Store Footer
    const footerNotice = document.createElement('div');
    footerNotice.style.cssText = 'font-size:0.75rem; color:#64748B; margin-top:16px; display:flex; align-items:center; justify-content:center; gap:6px; font-weight:500;';
    footerNotice.innerHTML = `<i class="fa-solid fa-lock" style="font-size:0.7rem; color:#94A3B8;"></i> Handled securely by ${storeName}`;
    card.appendChild(footerNotice);

    overlay.appendChild(card);
    document.body.appendChild(overlay);

    window._ohatiMandatoryUpdateVisible = true;
};

window.dismissMandatoryUpdateLock = function() {
    const overlay = document.getElementById('mandatory-update-overlay');
    if (overlay) {
        try { overlay.remove(); } catch (e) {}
    }
    window._ohatiMandatoryUpdateVisible = false;
};
