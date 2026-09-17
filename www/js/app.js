// js/app.js — Ohati Main Application Bootstrapper

// ══════════════════════════════════════════════════════════════════════════════
// ISOLATED SAFE MANDATORY APP UPDATE CHECKER (FAIL-OPEN)
// ══════════════════════════════════════════════════════════════════════════════
window.initSafeAppUpdateCheck = function() {
    try {
        // 1. Platform Detection: ONLY run for native mobile applications
        const isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) ||
                         window.location.protocol === 'capacitor:' ||
                         window.location.protocol === 'file:' ||
                         (navigator.userAgent && navigator.userAgent.includes('OhatiApp'));

        if (!isNative) {
            // Normal web/desktop browser — immediately exit (fail-open)
            return;
        }

        const rawPlatform = (window.Capacitor && typeof window.Capacitor.getPlatform === 'function') 
            ? window.Capacitor.getPlatform() 
            : '';
        let platform = 'android';
        if (rawPlatform === 'ios' || /iphone|ipad|ipod/i.test(navigator.userAgent || '')) {
            platform = 'ios';
        } else if (rawPlatform === 'android' || /android/i.test(navigator.userAgent || '')) {
            platform = 'android';
        } else {
            // Unidentifiable platform — fail open
            return;
        }

        const CACHE_KEY = 'ohati_app_version_policy_v1';
        const CACHE_TTL_MS = 6 * 60 * 60 * 1000; // 6 hours

        async function evaluatePolicy(policy, source) {
            if (!policy || typeof policy !== 'object' || !policy.platforms || !policy.platforms[platform]) {
                return false;
            }

            const platformData = policy.platforms[platform];
            const enforcementEnabled = (policy.enforcement_enabled === true || policy.enforcement_enabled === '1' || policy.enforcement_enabled === 1);

            if (!enforcementEnabled) {
                // Kill switch OFF — dismiss any existing lock and allow entry
                if (typeof window.dismissMandatoryUpdateLock === 'function') {
                    window.dismissMandatoryUpdateLock();
                }
                return false;
            }

            const minVersion = platformData.minimum_version;
            const latestVersion = platformData.latest_version;
            const currentVersion = window.OHATI_APP_VERSION || (window.state && window.state.appVersion) || '1.0.40';

            // Strict SemVer validation on both versions
            if (typeof window.isStrictSemVer !== 'function' || !window.isStrictSemVer(minVersion) || !window.isStrictSemVer(currentVersion)) {
                // Malformed SemVer — fail open
                return false;
            }

            // Segmented integer comparison
            const cmp = window.compareSemVer(currentVersion, minVersion);

            if (cmp < 0) {
                // STATE 3: Installed version is below minimum supported version AND enforcement is ON
                if (typeof window.showMandatoryUpdateLock === 'function') {
                    window.showMandatoryUpdateLock({
                        platform: platform,
                        installed_version: currentVersion,
                        minimum_version: minVersion,
                        latest_version: latestVersion,
                        store_url: platformData.store_url,
                        release_notes: platformData.release_notes
                    });
                }
                return true;
            } else {
                // STATE 1 & STATE 2: Current version is compliant
                if (typeof window.dismissMandatoryUpdateLock === 'function') {
                    window.dismissMandatoryUpdateLock();
                }
                return false;
            }
        }

        async function executeCheck() {
            if (window._ohatiVersionCheckInFlight) return;
            window._ohatiVersionCheckInFlight = true;

            const currentVersion = window.OHATI_APP_VERSION || (window.state && window.state.appVersion) || '1.0.40';
            let onlinePolicyFetched = false;

            try {
                const apiBase = (typeof window.getOhatiApiBaseUrl === 'function') ? window.getOhatiApiBaseUrl() : 'api.php';
                const endpoint = (apiBase.indexOf('?') === -1) 
                    ? `${apiBase}?action=app_version_policy` 
                    : `${apiBase}&action=app_version_policy`;

                // Strict 2500ms timeout using AbortController
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 2500);

                const res = await fetch(endpoint, {
                    method: 'GET',
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json' }
                });
                clearTimeout(timeoutId);

                if (res.ok) {
                    const data = await res.json();
                    if (data && data.status === 'success' && data.platforms && data.platforms[platform]) {
                        const minV = data.platforms[platform].minimum_version;
                        if (window.isStrictSemVer && window.isStrictSemVer(minV)) {
                            // Cache valid policy with timestamp & version
                            try {
                                const cachePayload = {
                                    policy: data,
                                    fetched_at: Date.now(),
                                    platform: platform,
                                    client_version: currentVersion
                                };
                                localStorage.setItem(CACHE_KEY, JSON.stringify(cachePayload));
                            } catch (cacheErr) {}

                            onlinePolicyFetched = true;
                            await evaluatePolicy(data, 'network');
                        }
                    }
                }
            } catch (netErr) {
                // Timeout, network error, HTTP 500, or fetch abort — fail open to cache/normal app
            } finally {
                window._ohatiVersionCheckInFlight = false;
            }

            // If online fetch did not succeed (offline / timeout / error), check cache
            if (!onlinePolicyFetched) {
                try {
                    const cachedRaw = localStorage.getItem(CACHE_KEY);
                    if (cachedRaw) {
                        const cached = JSON.parse(cachedRaw);
                        const now = Date.now();
                        const age = now - (cached.fetched_at || 0);
                        if (age >= 0 && age < CACHE_TTL_MS && cached.policy) {
                            await evaluatePolicy(cached.policy, 'cache');
                        }
                    }
                } catch (cacheEvalErr) {
                    // Corrupted cache — fail open
                }
            }
        }

        // Fire non-blocking check immediately
        executeCheck().catch(() => {});

        // Re-evaluate when app resumes from app store
        if (!window._ohatiResumeListenerAttached) {
            window._ohatiResumeListenerAttached = true;
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    executeCheck().catch(() => {});
                }
            });
            window.addEventListener('focus', () => {
                executeCheck().catch(() => {});
            });
        }
    } catch (globalErr) {
        console.warn("[AppUpdate] Unexpected error in update checker (failing open):", globalErr);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.initSafeAppUpdateCheck === 'function') window.initSafeAppUpdateCheck();
    if (typeof window.normalizeUserSession === 'function') window.normalizeUserSession();
    if (typeof window.initWebDownloadBanner === 'function') {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => window.initWebDownloadBanner(), { timeout: 1000 });
        } else {
            setTimeout(() => window.initWebDownloadBanner(), 100);
        }
    }

    // 0. Initialize theme from localStorage
    const savedTheme = localStorage.getItem('theme');
    const body = document.body;
    const themeIcon = document.getElementById('theme-icon');
    const headerLogo = document.getElementById('header-logo-img');
    
    const initialPath = decodeURIComponent(window.location.pathname.split('/').pop() || '');
    const isBlogRoute = initialPath.includes('blog.php');

    if (savedTheme === 'dark' && !isBlogRoute) {
        body.classList.add('dark-theme');
        if (themeIcon) themeIcon.className = 'fa-solid fa-sun';
        if (headerLogo) headerLogo.src = 'img/logo white transparent small.png';
    } else {
        body.classList.remove('dark-theme');
        if (themeIcon) themeIcon.className = 'fa-solid fa-moon';
        if (headerLogo) headerLogo.src = 'img/logo black transparent small.png';
    }

    // Smooth loading splash dismissal helper
    const dismissLoading = () => {
        const loadingScreen = document.getElementById('screen-loading');
        if (loadingScreen) {
            loadingScreen.classList.add('hide');
            loadingScreen.style.opacity = '0';
            setTimeout(() => {
                loadingScreen.style.display = 'none';
                try { loadingScreen.remove(); } catch(e) {}
            }, 300);
        }
    };

    // Safety fallback: Dismiss loading screen after 1.5s maximum
    const splashTimer = setTimeout(() => {
        dismissLoading();
    }, 1500);

    // Instant check: If no stored auth session token exists, check if accessing public routes (blog.php, about.php, help.php)
    const currentPath = decodeURIComponent(window.location.pathname.split('/').pop() || '');
    const isPublicGuestPage = (currentPath.includes('blog.php') || currentPath.includes('about.php') || currentPath.includes('help.php') || currentPath.includes('privacy.php') || currentPath.includes('privacy_policy.php') || currentPath.includes('terms.php'));
    const hasLocalAuth = localStorage.getItem('ohati_auth_token') || localStorage.getItem('ohati_user_session');

    if (!hasLocalAuth && !isPublicGuestPage) {
        state.user = null;
        dismissLoading();
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        if ((action === 'register' || action === 'signup') && typeof showMandatoryAuthLockScreen === 'function') {
            showMandatoryAuthLockScreen('register');
        } else if (typeof showMandatoryAuthLockScreen === 'function') {
            showMandatoryAuthLockScreen('login');
        }
        return;
    }

    const getStartRoute = () => {
        const path = decodeURIComponent(window.location.pathname.split('/').pop() || '');
        const urlParams = new URLSearchParams(window.location.search);
        let startScreen = 'home';
        let startParams = {};

        if (path === 'planner.php') startScreen = 'event';
        else if (path === 'search.php') startScreen = 'search';
        else if (path === 'detail.php') {
            startScreen = 'detail';
            const idVal = parseInt(urlParams.get('id') || urlParams.get('vendor_id'));
            if (idVal) {
                state.selectedVendorId = idVal;
                startParams = { id: idVal };
            }
        }
        else if (path === 'chat.php') {
            startScreen = 'chat';
            const vid = parseInt(urlParams.get('vendor_id') || urlParams.get('id'));
            if (vid) {
                state.activeChatVendorId = vid;
                startParams = { vendor_id: vid };
            } else {
                state.activeChatVendorId = null;
                startParams = {};
            }
        }
        else if (path === 'bookings.php') {
            startScreen = 'bookings';
            const bId = parseInt(urlParams.get('id'));
            if (bId) {
                state.selectedBookingId = bId;
                startParams = { id: bId };
            }
        }
        else if (path === 'favorites.php') startScreen = 'favorites';
        else if (path === 'compare.php') startScreen = 'compare';
        else if (path === 'notifications.php') startScreen = 'notifications';
        else if (path === 'profile.php') startScreen = 'profile';
        else if (path === 'profile-edit.php') startScreen = 'profile-edit';
        else if (path === 'vendor-dash.php') startScreen = 'vendor-dash';
        else if (path === 'vendor-ads.php' || path === 'promotions.php') startScreen = (state.user && (state.user.active_role || state.user.role) === 'vendor') ? 'vendor-ads' : 'home';
        else if (path === 'report-issue.php') startScreen = 'report-issue';
        else if (path === 'help.php') startScreen = 'help';
        else if (path === 'about.php') startScreen = 'about';
        else if (path === 'privacy.php' || path === 'privacy_policy.php') startScreen = 'privacy';
        else if (path === 'terms.php') startScreen = 'terms';
        else if (path === 'user-jobs.php') startScreen = 'user-jobs';
        else if (path === 'vendor-jobs.php') startScreen = 'vendor-jobs';
        else if (path === 'blog-detail.php') startScreen = 'blog-detail';
        else if (path === 'blog.php') {
            const bId = parseInt(urlParams.get('id'));
            const bSlug = urlParams.get('slug');
            if (bId || bSlug) {
                startScreen = 'blog-detail';
                if (bId) state.selectedBlogId = bId;
                startParams = { id: bId, slug: bSlug };
            } else {
                startScreen = 'blog';
            }
        }
        else if (path === 'vendor-auto-response.php') startScreen = 'vendor-auto-response';
        else if (path === 'jobs.php') {
            startScreen = (state.user && (state.user.active_role || state.user.role) === 'vendor') ? 'vendor-jobs' : 'user-jobs';
            const jId = parseInt(urlParams.get('id'));
            if (jId) {
                state.selectedJobId = jId;
                startParams = { id: jId };
            }
        }
        else if (path === 'reviews.php') startScreen = 'home';

        return { startScreen, startParams, path };
    };

    // 1. Authenticate Session First Before Exposing App Content
    API.getSession()
        .then(res => {
            clearTimeout(splashTimer);
            if (!res || !res.user) {
                // UNAUTHENTICATED USER: Clear local state
                state.user = null;
                localStorage.removeItem('ohati_auth_token');
                localStorage.removeItem('ohati_user_session');
                localStorage.removeItem('ohati_user');
                localStorage.removeItem('ohati_user_session');
                dismissLoading();

                if (isPublicGuestPage) {
                    // Boot public guest page cleanly
                    const { startScreen, startParams } = getStartRoute();
                    if (typeof navigateTo === 'function') {
                        navigateTo(startScreen, startParams, { replace: true, force: true });
                    }
                    return null;
                }

                if (typeof showMandatoryAuthLockScreen === 'function') {
                    showMandatoryAuthLockScreen('login');
                }
                return null;
            }

            // AUTHENTICATED USER: Initialize session & load application state
            state.user = res.user;
            localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
            if (typeof window.clearAllAuthOverlays === 'function') {
                window.clearAllAuthOverlays();
            } else if (typeof unlockMandatoryAuthScreen === 'function') {
                unlockMandatoryAuthScreen();
            }

            state.lockedFields = res.locked_profile_fields || ["name", "email", "phone", "dob"];
            if (res.platform_reviews) state.platformReviews = res.platform_reviews;
            state.settings = res.settings || {};
            updateAppHeader();
            updateNotifBadgeCount();

            if (typeof window.showDiditKycModalPopup === 'function') {
                setTimeout(() => {
                    window.showDiditKycModalPopup(res.user);
                }, 1200);
            }

            // Render skeleton loading screen IMMEDIATELY so user never sees a blank screen
            const { startScreen, startParams } = getStartRoute();
            if (typeof navigateTo === 'function') {
                navigateTo(startScreen, startParams, { replace: true, force: true });
            }
            dismissLoading();

            // Start periodic background activity heartbeat for real-time online status
            if (!window._onlineHeartbeatStarted) {
                window._onlineHeartbeatStarted = true;
                setInterval(() => {
                    if (state.user && state.user.id) {
                        API.sendHeartbeat().catch(() => {});
                    }
                }, 15000);
            }
            if (typeof checkReferralWebLanding === 'function') checkReferralWebLanding();
            
            return Promise.allSettled([
                API.getCategories(),
                API.getVendors(),
                API.getVendors({ premium_only: 1 }),
                API.get('get_advertisements'),
                API.getPopularVendors(),
                API.getBookings(),
                API.getFavorites(),
                API.getEvent(),
                API.get('get_faqs')
            ]);
        })
        .then((results) => {
            if (!state.user || !results) return; // Halt data processing if unauthenticated

            state.categories = results[0].status === 'fulfilled' ? results[0].value : [];
            state.vendors = results[1].status === 'fulfilled' ? results[1].value : [];
            
            const premiumVendors = results[2].status === 'fulfilled' ? results[2].value : [];
            const categories = state.categories;
            const activeAds = results[3].status === 'fulfilled' ? results[3].value : [];
            const popularVendors = results[4].status === 'fulfilled' ? results[4].value : [];
            
            state.homeCache = { premiumVendors, categories, activeAds, popularVendors };
            state.bookings = results[5].status === 'fulfilled' ? results[5].value : [];
            state.favorites = results[6].status === 'fulfilled' ? results[6].value : [];
            state.event = results[7].status === 'fulfilled' ? results[7].value : null;
            state.faqs = results[8].status === 'fulfilled' ? results[8].value : [];

            const { startScreen, startParams, path } = getStartRoute();
            window.history.replaceState({ screenId: startScreen, params: startParams }, '', window.location.href);
            navigateTo(startScreen, startParams, { force: true });
            if (window.OhatiNavManager) window.OhatiNavManager.init();
            pollUnreadChats();
            if (window.requestDeviceNotificationPermission) window.requestDeviceNotificationPermission();
            if (startScreen === 'home') {
                openWelcomePopup();
                if (path === 'reviews.php') {
                    setTimeout(() => openPlatformReviewModal(), 300);
                }
            }
        })
        .catch(err => {
            console.error("Initial load failed:", err);
            dismissLoading();
            if (!state.user) {
                localStorage.removeItem('ohati_auth_token');
                localStorage.removeItem('ohati_user_session');
                localStorage.removeItem('ohati_user');
                localStorage.removeItem('ohati_user_session');
                if (typeof showMandatoryAuthLockScreen === 'function') {
                    showMandatoryAuthLockScreen('login');
                }
            } else {
                navigateTo('home');
            }
        });

    // 3. Attach Bottom Navigation Handlers
    document.querySelectorAll('.bottom-nav .nav-item').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (btn.id === 'nav-btn-menu') {
                return;
            }
            e.preventDefault();
            const screen = btn.getAttribute('data-screen');
            if (screen) {
                // Clear active subchats
                if (screen === 'chat') state.activeChatVendorId = null;
                navigateTo(screen);
            }
        });
    });

    // 4. Attach Header Brand Logo Handler (Navigates to Home)
    const menuBtn = document.getElementById('header-menu-btn');
    if (menuBtn) {
        menuBtn.addEventListener('click', (e) => {
            e.preventDefault();
            navigateTo('home');
        });
    }

    // Attach Header Profile Avatar Handler (Navigates to User Profile Page)
    const avatarBtn = document.getElementById('header-avatar-btn');
    if (avatarBtn) {
        avatarBtn.addEventListener('click', (e) => {
            e.preventDefault();
            navigateTo('profile');
        });
    }

    // Attach Header Notifications button handler
    const notifBtn = document.getElementById('header-notif-btn');
    if (notifBtn) {
        notifBtn.addEventListener('click', () => {
            navigateTo('notifications');
        });
    }

    // 5. Theme Toggle Button
    const themeBtn = document.getElementById('theme-toggle-btn');
    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            window.toggleAppTheme();
        });
    }

    // 6. Keyboard & Viewport height alignment for Chat
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', () => {
            if (state.currentScreen === 'chat' && state.activeChatVendorId) {
                const appContainer = document.getElementById('ohati-app');
                if (appContainer) {
                    const vvHeight = window.visualViewport.height;
                    // Only restrict height on mobile views
                    if (window.innerWidth < 768) {
                        appContainer.style.height = `${vvHeight}px`;
                    } else {
                        appContainer.style.height = '';
                    }
                }
                scrollToBottom('chat-messages-container');
            }
        });
    }

    // 7. Global Link Interception & Native App UX Behavior (Prevent Browser Tabs)
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href) return;

        // Prevent default on pure '#' or javascript links
        if (href === '#' || href.startsWith('javascript:')) {
            e.preventDefault();
            return;
        }

        // Allow tel: and mailto: to trigger native phone/email handlers
        if (href.startsWith('tel:') || href.startsWith('mailto:')) {
            return;
        }

        // Check if link already handles navigation via inline onclick attribute to avoid double invocation
        const inlineOnclick = link.getAttribute('onclick') || '';
        if (inlineOnclick.includes('navigateTo(')) {
            return;
        }

        // Handle internal app routes (e.g. detail.php?id=123, planner.php, blog.php, chat.php, etc.)
        try {
            const urlObj = new URL(href, window.location.href);
            if (urlObj.origin === window.location.origin) {
                const pathName = urlObj.pathname.split('/').pop();

                if (pathName === 'detail.php') {
                    e.preventDefault();
                    const vendorId = urlObj.searchParams.get('id') || urlObj.searchParams.get('vendor_id');
                    if (vendorId) {
                        state.selectedVendorId = parseInt(vendorId);
                        navigateTo('detail', { id: parseInt(vendorId) });
                    }
                    return;
                } else if (pathName === 'chat.php') {
                    e.preventDefault();
                    const vendorId = urlObj.searchParams.get('vendor_id') || urlObj.searchParams.get('id') || urlObj.searchParams.get('user_id');
                    const numVid = vendorId ? parseInt(vendorId) : null;
                    state.activeChatVendorId = numVid;
                    navigateTo('chat', numVid ? { vendor_id: numVid } : null);
                    return;
                } else if (pathName === 'blog.php') {
                    e.preventDefault();
                    const bId = urlObj.searchParams.get('id');
                    const bSlug = urlObj.searchParams.get('slug');
                    if (bId || bSlug) {
                        if (bId) state.selectedBlogId = parseInt(bId);
                        navigateTo('blog-detail', { id: bId ? parseInt(bId) : null, slug: bSlug });
                    } else {
                        navigateTo('blog');
                    }
                    return;
                } else if (pathName === 'planner.php') {
                    e.preventDefault();
                    navigateTo('event');
                    return;
                } else if (pathName === 'search.php') {
                    e.preventDefault();
                    navigateTo('search');
                    return;
                } else if (pathName === 'bookings.php') {
                    e.preventDefault();
                    const bId = urlObj.searchParams.get('id');
                    navigateTo('bookings', bId ? { id: parseInt(bId) } : null);
                    return;
                } else if (pathName === 'favorites.php') {
                    e.preventDefault();
                    navigateTo('favorites');
                    return;
                } else if (pathName === 'notifications.php') {
                    e.preventDefault();
                    navigateTo('notifications');
                    return;
                } else if (pathName === 'profile.php') {
                    e.preventDefault();
                    navigateTo('profile');
                    return;
                } else if (pathName === 'vendor-dash.php') {
                    e.preventDefault();
                    navigateTo('vendor-dash');
                    return;
                } else if (pathName === 'promotions.php') {
                    e.preventDefault();
                    navigateTo((state.user && (state.user.active_role || state.user.role) === 'vendor') ? 'vendor-ads' : 'home');
                    return;
                } else if (pathName === 'help.php') {
                    e.preventDefault();
                    navigateTo('help');
                    return;
                } else if (pathName === 'about.php') {
                    e.preventDefault();
                    navigateTo('about');
                    return;
                } else if (pathName === 'report-issue.php') {
                    e.preventDefault();
                    navigateTo('report-issue');
                    return;
                } else if (pathName === 'index.php') {
                    e.preventDefault();
                    navigateTo('home');
                    return;
                }
            }
        } catch (err) {}

        // If running in Capacitor / Mobile native app, open external links via system browser cleanly
        const isNative = window.Capacitor && (typeof window.Capacitor.isNativePlatform === 'function' ? window.Capacitor.isNativePlatform() : window.Capacitor.isNative);
        if (isNative && (href.startsWith('http://') || href.startsWith('https://'))) {
            e.preventDefault();
            if (window.Capacitor.Plugins && window.Capacitor.Plugins.Browser) {
                window.Capacitor.Plugins.Browser.open({ url: href }).catch(() => {
                    window.open(href, '_system');
                });
            } else {
                window.open(href, '_system');
            }
        }
    });

    // 8. Prevent long-press context menus everywhere except text input fields
    document.addEventListener('contextmenu', (e) => {
        if (!e.target.closest('input, textarea, [contenteditable="true"], .selectable-text')) {
            e.preventDefault();
        }
    }, { passive: false });

    // 9. Prevent ghost dragging of images/links
    document.addEventListener('dragstart', (e) => {
        e.preventDefault();
    });
});

// Helper to update app header state
function updateAppHeader() {
    const avatar = document.getElementById('header-avatar');
    if (avatar) {
        if (state.user && state.user.avatar) {
            avatar.src = window.resolveImageUrl(state.user.avatar);
        } else {
            avatar.src = window.resolveImageUrl(null);
        }
    }
    if (typeof window.updateHeaderNavRoleVisibility === 'function') {
        window.updateHeaderNavRoleVisibility();
    }
}

// Update notification count badge globally
window.updateNotifBadgeCount = function() {
    if (!state.user) {
        const badge = document.getElementById('notif-badge');
        if (badge) badge.style.display = 'none';
        return;
    }
    API.getNotifications().then(list => {
        const notifList = Array.isArray(list) ? list : [];
        const unreadCount = notifList.filter(n => !n.is_read).length;
        const badge = document.getElementById('notif-badge');
        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }).catch(e => console.error("Error updating notification badge:", e));
};

// Background Polling for Unread Messages
state.notifiedMessages = state.notifiedMessages || new Set();
state.unreadPollTimeout = null;

function pollUnreadChats() {
    if (state.unreadPollTimeout) {
        clearTimeout(state.unreadPollTimeout);
        state.unreadPollTimeout = null;
    }

    if (!state.user) {
        updateChatBadges(0);
        state.unreadPollTimeout = setTimeout(pollUnreadChats, 4000);
        return;
    }

    API.getUnreadChats().then(unreadList => {
        const count = unreadList.length;
        updateChatBadges(count);

        unreadList.forEach(msg => {
            const msgId = msg.id;
            // Defensive UI verification: ensure message is intended for the logged in user
            const curUid = state.user ? parseInt(state.user.id) : 0;
            if (msg.recipient_type === 'user' && parseInt(msg.recipient_id) !== curUid) {
                return; // Defense in depth: ignore foreign recipient messages
            }

            if (!state.notifiedMessages.has(msgId)) {
                state.notifiedMessages.add(msgId);
                
                const activeChatId = parseInt(state.activeChatVendorId);
                const isCurrentPartner = (activeChatId && (
                    (msg.sender === 'user' && msg.user_id === activeChatId) ||
                    (msg.sender === 'vendor' && msg.vendor_id === activeChatId)
                ));

                if (state.currentScreen !== 'chat' || !isCurrentPartner) {
                    const senderName = msg.sender_name || 'New Message';
                    showPushNotification(senderName, msg.message);
                }
            }
        });

        state.unreadPollTimeout = setTimeout(pollUnreadChats, 4000);
    }).catch(err => {
        console.error("Error polling unread chats:", err);
        state.unreadPollTimeout = setTimeout(pollUnreadChats, 4000);
    });
}

function updateChatBadges(count) {
    const badgeMobile = document.getElementById('chat-nav-badge');
    const badgeDesktop = document.getElementById('chat-nav-badge-desktop');

    if (badgeMobile) {
        if (count > 0) {
            badgeMobile.textContent = count;
            badgeMobile.style.display = 'flex';
        } else {
            badgeMobile.style.display = 'none';
        }
    }
    if (badgeDesktop) {
        if (count > 0) {
            badgeDesktop.textContent = count;
            badgeDesktop.style.display = 'flex';
        } else {
            badgeDesktop.style.display = 'none';
        }
    }
}

window.checkReferralWebLanding = function() {
    const urlParams = new URLSearchParams(window.location.search);
    const refCode = urlParams.get('ref') || sessionStorage.getItem('ohati_pending_ref');
    
    // Check if on mobile native app vs Web
    const isNativeApp = window.Capacitor && window.Capacitor.isNativePlatform();

    if (refCode) {
        sessionStorage.setItem('ohati_pending_ref', refCode);
        
        if (!isNativeApp) {
            setTimeout(() => {
                if (typeof openModal === 'function') {
                    const html = `
                        <div style="text-align:center; padding:10px 4px;">
                            <div style="width:64px; height:64px; background:linear-gradient(135deg, var(--primary), var(--accent)); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-size:2rem; box-shadow:0 8px 20px rgba(0,0,0,0.15);">
                                <i class="fa-solid fa-gift"></i>
                            </div>
                            <h2 style="font-size:1.3rem; font-weight:800; color:var(--gray-900); margin-bottom:6px;">🎉 You've Been Invited to Ohati!</h2>
                            <p style="font-size:0.82rem; color:var(--gray-600); line-height:1.5; margin-bottom:16px;">
                                Your friend invited you to join <strong>Ohati</strong> — Ghana's premier event planning & vendor booking app. For the best experience, download the mobile app for iOS or Android!
                            </p>

                            <div class="card" style="padding:10px 12px; background:rgba(var(--accent-rgb),0.1); border:1px solid rgba(var(--accent-rgb),0.3); border-radius:10px; font-size:0.75rem; margin-bottom:16px;">
                                <strong>Referral Code:</strong> <span class="badge badge-primary" style="font-size:0.8rem; letter-spacing:0.5px;">${refCode}</span>
                            </div>

                            <!-- App Download Buttons -->
                            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px;">
                                <a href="android-release.apk" target="_blank" class="btn btn-primary btn-full" style="height:44px; font-weight:700; font-size:0.85rem; display:flex; align-items:center; justify-content:center; gap:8px; border-radius:10px; text-decoration:none;">
                                    <i class="fa-brands fa-android" style="font-size:1.2rem; color:#A4C639;"></i> Download Android App (.APK)
                                </a>
                                <button class="btn btn-outline btn-full" onclick="showPushNotification('App Store', 'iOS App Store link will open upon store publication. You can register on web below!')" style="height:40px; font-size:0.8rem; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px;">
                                    <i class="fa-brands fa-apple" style="font-size:1.1rem;"></i> Download for iOS (App Store)
                                </button>
                            </div>

                            <div style="border-top:1px solid var(--gray-11200); padding-top:12px; margin-top:12px;">
                                <button class="btn btn-ghost btn-full" onclick="closeModal(); state.authMode='register'; state.authStep=1; renderAuthModal();" style="font-size:0.78rem; font-weight:700; color:var(--primary);">
                                    Or Continue on Web Browser →
                                </button>
                            </div>
                        </div>
                    `;
                    openModal(html);
                }
            }, 800);
        }
    }
};

window.toggleAppTheme = function() {
    const body = document.body;
    const icon = document.getElementById('theme-icon');
    const logo = document.getElementById('header-logo-img');
    const isCurrentlyDark = body.classList.contains('dark-theme') || localStorage.getItem('theme') === 'dark';
    const isBlogScreen = (window.state && (window.state.currentScreen === 'blog' || window.state.currentScreen === 'blog-detail'));

    if (isCurrentlyDark) {
        body.classList.remove('dark-theme');
        if (icon) icon.className = 'fa-solid fa-moon';
        if (window.state && window.state.currentScreen !== 'home') {
            if (logo) logo.src = 'img/logo black transparent small.png';
        }
        localStorage.setItem('theme', 'light');
    } else {
        localStorage.setItem('theme', 'dark');
        if (!isBlogScreen) {
            body.classList.add('dark-theme');
            if (logo) logo.src = 'img/logo white transparent small.png';
        }
        if (icon) icon.className = 'fa-solid fa-sun';
    }

    if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.StatusBar) {
        try {
            const isDark = body.classList.contains('dark-theme');
            window.Capacitor.Plugins.StatusBar.setStyle({ style: isDark ? 'DARK' : 'LIGHT' });
            window.Capacitor.Plugins.StatusBar.setBackgroundColor({ color: isDark ? '#0F1923' : '#FFFFFF' });
        } catch (e) {}
    }
};

