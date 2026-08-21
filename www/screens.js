// js/screens.js — Ohati View / Screen Renderers

// ── Skeleton Loader Generator Component Helpers ───────────────────────
function renderSkeletonCardsHTML(count = 6) {
    let cards = '';
    for (let i = 0; i < count; i++) {
        cards += `
            <div class="skeleton-card">
                <div class="skeleton skeleton-thumb"></div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                    <div class="skeleton skeleton-title" style="width:65%;"></div>
                    <div class="skeleton skeleton-pill" style="width:55px; height:20px;"></div>
                </div>
                <div class="skeleton skeleton-text" style="width:45%; margin-top:2px;"></div>
                <div style="display:flex; gap:8px; align-items:center; margin-top:4px;">
                    <div class="skeleton skeleton-text" style="width:30%;"></div>
                    <div class="skeleton skeleton-text" style="width:40%;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; border-top:1px solid rgba(0,0,0,0.05); padding-top:8px;">
                    <div class="skeleton skeleton-title" style="width:35%; height:18px;"></div>
                    <div class="skeleton skeleton-pill" style="width:75px; height:24px;"></div>
                </div>
            </div>
        `;
    }
    return `<div class="skeleton-grid">${cards}</div>`;
}

function renderSkeletonCategoriesHTML(count = 6) {
    let pills = '';
    for (let i = 0; i < count; i++) {
        pills += `<div class="skeleton skeleton-pill" style="width:90px; height:36px; border-radius:20px; flex-shrink:0;"></div>`;
    }
    return `<div style="display:flex; gap:10px; overflow-x:auto; padding:8px 0;">${pills}</div>`;
}

function renderSkeletonVendorDetailHTML() {
    return `
        <div style="padding:16px; display:flex; flex-direction:column; gap:14px; max-width:600px; margin:0 auto;">
            <div class="skeleton skeleton-thumb" style="height:220px; border-radius:14px;"></div>
            <div style="display:flex; gap:14px; align-items:center;">
                <div class="skeleton skeleton-avatar" style="width:56px; height:56px;"></div>
                <div style="flex:1; display:flex; flex-direction:column; gap:8px;">
                    <div class="skeleton skeleton-title" style="width:70%; height:22px;"></div>
                    <div class="skeleton skeleton-text" style="width:40%; height:14px;"></div>
                </div>
            </div>
            <div style="display:flex; gap:10px; border-bottom:1px solid rgba(0,0,0,0.08); padding-bottom:10px; margin-top:8px;">
                <div class="skeleton skeleton-pill" style="width:80px; height:32px;"></div>
                <div class="skeleton skeleton-pill" style="width:80px; height:32px;"></div>
                <div class="skeleton skeleton-pill" style="width:80px; height:32px;"></div>
            </div>
            <div style="display:flex; flex-direction:column; gap:10px; margin-top:8px;">
                <div class="skeleton skeleton-text" style="width:100%; height:16px;"></div>
                <div class="skeleton skeleton-text" style="width:92%; height:16px;"></div>
                <div class="skeleton skeleton-text" style="width:85%; height:16px;"></div>
            </div>
        </div>
    `;
}

function renderSkeletonListHTML(count = 4) {
    let rows = '';
    for (let i = 0; i < count; i++) {
        rows += `
            <div class="card mb-12" style="padding:14px; display:flex; gap:12px; align-items:center;">
                <div class="skeleton skeleton-avatar"></div>
                <div style="flex:1; display:flex; flex-direction:column; gap:6px;">
                    <div class="skeleton skeleton-title" style="width:60%;"></div>
                    <div class="skeleton skeleton-text" style="width:80%;"></div>
                </div>
            </div>
        `;
    }
    return `<div>${rows}</div>`;
}

// ── Screen Manager: navigateTo ─────────────────────────────────────────
function navigateTo(screenId, params = {}, options = {}) {
    // Auth Guard: Lock screen to login/signup unless authenticated
    if (!state.user && screenId !== 'blog' && screenId !== 'blog-detail' && screenId !== 'about' && screenId !== 'help') {
        if (typeof showMandatoryAuthLockScreen === 'function') {
            showMandatoryAuthLockScreen('login');
        }
        return;
    }

    // Parameter Normalization for boolean/number values passed as 2nd arg
    if (typeof params === 'boolean') {
        options = { fromPopState: params };
        params = {};
    } else if (typeof params === 'number') {
        params = { id: params };
    }

    const force = typeof options === 'boolean' ? options : !!options.force;
    const fromPopState = options && typeof options === 'object' ? !!options.fromPopState : false;
    const replace = options && typeof options === 'object' ? !!options.replace : false;

    // Check if parameters changed (e.g. vendor ID, booking ID, chat vendor ID, job ID)
    let paramsChanged = false;
    if (params && typeof params === 'object') {
        if (params.id !== undefined && params.id !== null) {
            const numId = parseInt(params.id);
            if (screenId === 'detail' && state.selectedVendorId !== numId) paramsChanged = true;
            if (screenId === 'bookings' && state.selectedBookingId !== numId) paramsChanged = true;
            if (screenId === 'user-jobs' && state.selectedJobId !== numId) paramsChanged = true;
            if (screenId === 'vendor-jobs' && state.selectedJobId !== numId) paramsChanged = true;
            if (screenId === 'blog-detail' && state.selectedBlogId !== numId) paramsChanged = true;
        }
        if (params.vendor_id !== undefined || params.vendorId !== undefined) {
            const vid = parseInt(params.vendor_id || params.vendorId);
            if (screenId === 'chat' && state.activeChatVendorId !== vid) paramsChanged = true;
            if (screenId === 'detail' && state.selectedVendorId !== vid) paramsChanged = true;
        }
    }

    if (state.currentScreen === screenId && !paramsChanged && !force) return;

    // Dismiss any open sidebar or modals on navigation to prevent frozen overlay backdrops
    if (typeof toggleSidebar === 'function') toggleSidebar(false);
    if (typeof closeModal === 'function') closeModal();
    if (typeof closeBookingModal === 'function') closeBookingModal();
    if (state.chatInterval) {
        clearInterval(state.chatInterval);
        state.chatInterval = null;
    }

    // Apply structured parameters to state
    if (params && typeof params === 'object') {
        if (params.id !== undefined && params.id !== null) {
            const numId = parseInt(params.id);
            if (screenId === 'detail') state.selectedVendorId = numId;
            else if (screenId === 'bookings') state.selectedBookingId = numId;
            else if (screenId === 'user-jobs' || screenId === 'vendor-jobs') state.selectedJobId = numId;
            else if (screenId === 'blog-detail') state.selectedBlogId = numId;
        }
        if (params.vendor_id !== undefined || params.vendorId !== undefined) {
            const vid = parseInt(params.vendor_id || params.vendorId);
            if (screenId === 'chat') state.activeChatVendorId = vid;
            if (screenId === 'detail') state.selectedVendorId = vid;
        } else if (screenId === 'chat' && (!params || Object.keys(params).length === 0)) {
            state.activeChatVendorId = null;
        }
    } else if (screenId === 'chat') {
        state.activeChatVendorId = null;
    }

    // Clean up open sidebars, modals, overlays, and body scroll lock when switching screens
    if (typeof toggleSidebar === 'function') toggleSidebar(false);
    if (typeof closeModal === 'function') closeModal();
    if (typeof closeConfirmModal === 'function') closeConfirmModal(false);
    document.body.classList.remove('modal-open');
    const globalModalRoot = document.getElementById('ohati-global-modal-root');
    if (globalModalRoot) {
        globalModalRoot.classList.remove('open');
        globalModalRoot.style.display = 'none';
    }

    if (screenId !== 'chat' && state.chatInterval) {
        clearInterval(state.chatInterval);
        state.chatInterval = null;
    }

    // Save history with structured snapshot
    if (!fromPopState && state.currentScreen && state.currentScreen !== 'loading') {
        const historySnapshot = {
            screenId: state.currentScreen,
            params: {
                selectedVendorId: state.selectedVendorId,
                activeChatVendorId: state.activeChatVendorId,
                selectedBookingId: state.selectedBookingId,
                selectedJobId: state.selectedJobId
            }
        };
        if (replace && state.previousScreens.length > 0) {
            state.previousScreens[state.previousScreens.length - 1] = historySnapshot;
        } else {
            state.previousScreens.push(historySnapshot);
        }
    }

    state.currentScreen = screenId;

    // Determine address bar URL to match standalone routes
    let pageName = 'index.php';
    if (screenId === 'event') pageName = 'planner.php';
    else if (screenId === 'search') pageName = 'search.php';
    else if (screenId === 'detail') pageName = `detail.php${state.selectedVendorId ? '?id=' + state.selectedVendorId : ''}`;
    else if (screenId === 'chat') pageName = `chat.php${state.activeChatVendorId ? '?vendor_id=' + state.activeChatVendorId : ''}`;
    else if (screenId === 'bookings') pageName = `bookings.php${state.selectedBookingId ? '?id=' + state.selectedBookingId : ''}`;
    else if (screenId === 'favorites') pageName = 'favorites.php';
    else if (screenId === 'compare') pageName = 'compare.php';
    else if (screenId === 'notifications') pageName = 'notifications.php';
    else if (screenId === 'profile') pageName = 'profile.php';
    else if (screenId === 'vendor-dash') pageName = 'vendor-dash.php';
    else if (screenId === 'report-issue') pageName = 'report-issue.php';
    else if (screenId === 'vendor-ads') pageName = 'promotions.php';
    else if (screenId === 'help') pageName = 'help.php';
    else if (screenId === 'blog') pageName = 'blog.php';
    else if (screenId === 'blog-detail') pageName = `blog.php${state.selectedBlogId ? '?id=' + state.selectedBlogId : ''}`;

    const isSPA = !!document.getElementById('screen-home');
    if (!isSPA) {
        window.location.href = pageName;
        return;
    }

    const appContainer = document.getElementById('ohati-app');
    const headerLogo = document.getElementById('header-logo-img');
    const isDark = document.body.classList.contains('dark-theme');
    
    if (screenId === 'home') {
        if (appContainer) appContainer.classList.add('home-active');
        if (headerLogo) headerLogo.src = 'img/logo white transparent small.png';
    } else {
        if (appContainer) appContainer.classList.remove('home-active');
        if (headerLogo) {
            headerLogo.src = isDark ? 'img/logo white transparent small.png' : 'img/logo black transparent small.png';
        }
    }

    // Close active modal if switching screens
    if (typeof closeModal === 'function') {
        closeModal();
    }

    // Scroll to top cleanly
    window.scrollTo({ top: 0, behavior: 'instant' });

    // Hide all screens
    document.querySelectorAll('.screen').forEach(s => s.style.display = 'none');

    // Show target screen with fallback protection against blank screens
    let target = document.getElementById('screen-' + screenId);
    if (!target) {
        console.warn(`Target screen "screen-${screenId}" not found in DOM. Falling back to screen-home.`);
        screenId = 'home';
        state.currentScreen = 'home';
        target = document.getElementById('screen-home');
        pageName = 'index.php';
    }

    if (target) {
        target.style.display = 'block';
    }

    // Update active nav buttons on desktop & mobile
    document.querySelectorAll('.bottom-nav .nav-item, .desktop-nav-item').forEach(el => {
        el.classList.remove('active');
        const dScreen = el.getAttribute('data-screen');
        if (dScreen === screenId || (screenId === 'blog-detail' && dScreen === 'blog')) {
            el.classList.add('active');
        }
    });

    if (typeof updateHeaderNavRoleVisibility === 'function') {
        updateHeaderNavRoleVisibility();
    }

    // Run screen specific initialization/render inside try/catch
    try {
        switch (screenId) {
            case 'home':
                initHomeScreen(params);
                break;
            case 'search':
                initSearchScreen(params);
                break;
            case 'detail':
                initDetailScreen(params);
                break;
            case 'chat':
                initChatScreen(params);
                break;
            case 'bookings':
                initBookingsScreen(params);
                break;
            case 'favorites':
                initFavoritesScreen(params);
                break;
            case 'event':
                initEventScreen(params);
                break;
            case 'compare':
                initCompareScreen(params);
                break;
            case 'notifications':
                initNotificationsScreen(params);
                break;
            case 'profile':
                initProfileScreen(params);
                break;
            case 'vendor-dash':
                initVendorDashScreen(params);
                break;
            case 'vendor-ads':
                initVendorAdsScreen(params);
                break;
            case 'vendor-auto-response':
                initVendorAutoResponseScreen(params);
                break;
            case 'profile-edit':
                initProfileEditScreen(params);
                break;
            case 'about':
                initAboutScreen(params);
                break;
            case 'help':
                initHelpScreen(params);
                break;
            case 'report-issue':
                initReportIssueScreen(params);
                break;
            case 'user-jobs':
                initUserJobsScreen(params);
                break;
            case 'vendor-jobs':
                initVendorJobsScreen(params);
                break;
            case 'blog':
                if (typeof initBlogScreen === 'function') initBlogScreen(params);
                break;
            case 'blog-detail':
                if (typeof initBlogDetailScreen === 'function') initBlogDetailScreen(params);
                break;
        }
    } catch (renderErr) {
        console.error(`Error rendering screen "${screenId}":`, renderErr);
    }

    // Update browser history AFTER screen transition succeeds
    const currentUrl = window.location.pathname.split('/').pop() + window.location.search;
    if (!fromPopState && currentUrl !== pageName) {
        if (replace) {
            window.history.replaceState({ screenId, params }, '', pageName);
        } else {
            window.history.pushState({ screenId, params }, '', pageName);
        }
    }

    // Clear any active sponsored timeouts to prevent overlapping triggers
    if (window.generalSponsoredTimeout) {
        clearTimeout(window.generalSponsoredTimeout);
        window.generalSponsoredTimeout = null;
    }

    // Trigger popups based on target screen
    if (screenId === 'home' || screenId === 'search') {
        window.generalSponsoredTimeout = setTimeout(() => {
            if (typeof checkAndShowGeneralSponsoredPopup === 'function') {
                checkAndShowGeneralSponsoredPopup();
            }
        }, 5000);
    } else if (screenId === 'vendor-dash' || screenId === 'vendor-ads') {
        setTimeout(() => {
            if (typeof checkAndShowVendorPromotionPopup === 'function') {
                checkAndShowVendorPromotionPopup();
            }
        }, 1000);
    }

    // Scroll viewport to top
    const vp = document.getElementById('app-viewport');
    if (vp) vp.scrollTop = 0;

    syncChatLayoutState();
}

function navigateBack() {
    if (state.previousScreens.length > 0) {
        const prevEntry = state.previousScreens.pop();
        if (typeof prevEntry === 'string') {
            navigateTo(prevEntry, {}, { fromPopState: true, force: true });
        } else if (prevEntry && prevEntry.screenId) {
            if (prevEntry.params) {
                if (prevEntry.params.selectedVendorId) state.selectedVendorId = prevEntry.params.selectedVendorId;
                if (prevEntry.params.activeChatVendorId) state.activeChatVendorId = prevEntry.params.activeChatVendorId;
                if (prevEntry.params.selectedBookingId) state.selectedBookingId = prevEntry.params.selectedBookingId;
                if (prevEntry.params.selectedJobId) state.selectedJobId = prevEntry.params.selectedJobId;
            }
            navigateTo(prevEntry.screenId, prevEntry.params || {}, { fromPopState: true, force: true });
        }
    } else {
        navigateTo('home', {}, { force: true });
    }
}

// ── 1. HOME SCREEN ──────────────────────────────────────────────────────
function renderHomeScreen(premiumVendors, categories, activeAds, popularVendors) {
    const screen = document.getElementById('screen-home');
    if (!screen) return;

    let greetingText = 'Welcome!';
    let roleBadge = '';
    
    if (state.user) {
        const userName = state.user.name.split(' ')[0];
        greetingText = `${userName} Welcome!`;
        const role = state.user.active_role || state.user.role || 'customer';
        if (role === 'admin') {
            roleBadge = '<span class="role-badge role-admin"><i class="fa-solid fa-user-shield"></i> Admin</span>';
        } else if (role === 'vendor') {
            roleBadge = '<span class="role-badge role-vendor"><i class="fa-solid fa-briefcase"></i> Vendor</span>';
        } else {
            roleBadge = '<span class="role-badge role-customer"><i class="fa-solid fa-calendar-check"></i> Planner</span>';
        }
    }

    let adsHtml = '';
    if (activeAds && activeAds.length > 0) {
        adsHtml = `
            <div class="sponsored-carousel-wrapper">
                <div class="section-header" style="margin-bottom: 8px;">
                    <h3 class="section-title"><i class="fa-solid fa-rectangle-ad" style="color:var(--accent);"></i> Sponsored Promotions</h3>
                </div>
                <div class="vendor-cards-scroll">
                    ${activeAds.map(ad => `
                        <div class="sponsored-ad-banner" onclick="handleAdClick(${ad.id}, '${ad.destination}', ${ad.vendor_id})">
                            <div class="sponsored-ad-img-wrap">
                                <img src="${ad.banner_url || 'img/ads/default.jpg'}" alt="${ad.title}">
                                <span class="sponsored-tag"><i class="fa-solid fa-rectangle-ad"></i> Sponsored</span>
                            </div>
                            <div class="sponsored-ad-content">
                                <div class="sponsored-ad-title">${ad.title}</div>
                                <div class="sponsored-ad-desc">${ad.description}</div>
                                <div class="sponsored-ad-footer">
                                    <span class="sponsored-ad-location"><i class="fa-solid fa-location-dot"></i> ${ad.target_location === 'All' ? 'National' : ad.target_location}</span>
                                    <button class="sponsored-ad-cta btn btn-primary btn-xs">${ad.cta_text || 'Learn More'}</button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    // ── Personalized Recommendation System ──
    let recHtml = '';
    const allVendors = state.vendors && state.vendors.length > 0 ? state.vendors : premiumVendors;
    if (allVendors.length > 0) {
        const userInterestCat = localStorage.getItem('ohati_user_interest_category') || '';
        let recVendor = null;

        if (userInterestCat) {
            const matches = allVendors.filter(v => v.category === userInterestCat);
            if (matches.length > 0) {
                matches.sort((a, b) => {
                    if ((b.featured || 0) !== (a.featured || 0)) return (b.featured || 0) - (a.featured || 0);
                    if ((b.premium || 0) !== (a.premium || 0)) return (b.premium || 0) - (a.premium || 0);
                    return (b.rating || 0) - (a.rating || 0);
                });
                recVendor = matches[0];
            }
        }
        if (!recVendor) {
            recVendor = allVendors.find(v => v.name === 'Sika Bridal');
        }
        if (!recVendor) {
            const sorted = [...allVendors].sort((a, b) => (b.rating || 0) - (a.rating || 0));
            recVendor = sorted[0];
        }

        if (recVendor) {
            let recLabel = 'Handpicked vendor for your next celebration';
            let labelIcon = 'fa-wand-magic-sparkles';
            if (parseInt(recVendor.featured) === 1) {
                recLabel = 'Featured Event Professional';
                labelIcon = 'fa-award';
            } else if (parseInt(recVendor.premium) === 1) {
                recLabel = 'Sponsored Partner';
                labelIcon = 'fa-star';
            }
            const recDesc = recVendor.description ? (recVendor.description.length > 75 ? recVendor.description.substring(0, 75) + '...' : recVendor.description) : 'Premium event services tailored to your style.';
            let verifyBadge = '';
            if (recVendor.verification_badge === 'gold') verifyBadge = '<i class="fa-solid fa-circle-check" style="color:#FFD700; font-size:0.75rem;"></i>';
            else if (recVendor.verification_badge === 'blue' || parseInt(recVendor.verified) === 1) verifyBadge = '<i class="fa-solid fa-circle-check" style="color:#1DA1F2; font-size:0.75rem;"></i>';

            recHtml = `
                <div class="personalized-rec-card" onclick="viewVendorDetails(${recVendor.id})" style="position:relative; background:var(--gray-50); border:1.5px solid var(--accent); border-radius:16px; padding:16px; margin:16px 0; cursor:pointer; overflow:hidden; box-shadow:0 4px 16px rgba(212,175,55,0.08); transition:transform 0.2s ease, box-shadow 0.2s ease;">
                    <div style="position:absolute; top:-20px; right:-20px; width:80px; height:80px; border-radius:50%; background:rgba(212,175,55,0.1); filter:blur(15px);"></div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <div style="font-size:0.6rem; font-weight:800; color:var(--accent); text-transform:uppercase; letter-spacing:1px; display:flex; align-items:center; gap:5px;">
                            <i class="fa-solid ${labelIcon}"></i> ${recLabel}
                        </div>
                        <div style="display:flex; gap:12px; align-items:center; margin-top:2px;">
                            <img src="${recVendor.logo || 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=400'}" style="width:50px; height:50px; border-radius:10px; object-fit:cover; border:1px solid var(--gray-200);" alt="">
                            <div style="flex:1; min-width:0;">
                                <h4 style="font-family:'Fraunces',serif; font-size:0.95rem; margin:0 0 2px 0; color:var(--primary); display:flex; align-items:center; gap:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <span>${recVendor.name}</span>
                                    ${verifyBadge}
                                </h4>
                                <div style="font-size:0.7rem; color:var(--gray-600); line-height:1.3; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${recDesc}</div>
                                <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
                                    <div style="display:flex; align-items:center; gap:3px; font-size:0.7rem; color:var(--warning); font-weight:700;">
                                        <i class="fa-solid fa-star"></i>
                                        <span>${recVendor.rating || '4.9'}</span>
                                    </div>
                                    <span style="font-size:0.65rem; color:var(--gray-400);">(${recVendor.reviews_count || 0} reviews)</span>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="color:var(--gray-400); font-size:0.85rem; margin-left:4px;"></i>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    const heroBg = (state.settings && state.settings.hero_banner_image) ? state.settings.hero_banner_image : 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?q=80&w=1200';
    const heroTitle = (state.settings && state.settings.hero_title) ? state.settings.hero_title : 'Extraordinary<br>events <span class="start-italic">start</span><br>with the right people.';

    let topVendors = (state.vendors || []).filter(v => parseInt(v.featured) === 1 || parseFloat(v.rating || 0) >= 4.0);
    if (topVendors.length < 4) {
        topVendors = state.vendors || [];
    }
    
    const defaultHandpicked = [
        { id: 9, name: 'The Grand Pavilion', category: 'Event Venue', rating: 4.9, reviews: 248, city: 'East Legon, Accra', img: 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?q=80&w=400', initials: 'GP', badgeClass: '', verification_badge: 'gold', verified: 1, premium: 1 },
        { id: 7, name: 'TasteBuds Catering', category: 'Catering Services', rating: 4.8, reviews: 176, city: 'Airport Residential, Accra', img: 'https://images.unsplash.com/photo-1555244162-803834f70033?q=80&w=400', icon: 'fa-utensils', badgeClass: 'tastebuds-badge', verification_badge: 'gold', verified: 1, premium: 1 },
        { id: 6, name: 'Luxe Decor', category: 'Decor & Flowers', rating: 4.9, reviews: 193, city: 'East Legon, Accra', img: 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?q=80&w=400', initials: 'LD', badgeClass: 'luxe-badge', verification_badge: 'blue', verified: 1, premium: 0 },
        { id: 2, name: 'Jojo Temeng Photo', category: 'Photography', rating: 4.9, reviews: 84, city: 'Osu, Accra', img: 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=400', icon: 'fa-camera', badgeClass: 'luxe-badge', verification_badge: 'blue', verified: 1, premium: 0 }
    ];

    let handpickedList = [];
    if (topVendors.length >= 4) {
        handpickedList = topVendors.slice(0, 4).map((v, i) => {
            const initials = v.name ? v.name.split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase() : 'V';
            return {
                id: v.id,
                name: v.name,
                category: v.category_name || v.category || 'Vendor',
                rating: v.rating || '4.9',
                reviews: v.reviews_count || 12,
                city: v.city || v.address || 'Accra, Ghana',
                img: v.cover_image || v.image || v.logo || defaultHandpicked[i % 4].img,
                initials: initials,
                badgeClass: defaultHandpicked[i % 4].badgeClass,
                verification_badge: v.verification_badge,
                verified: parseInt(v.verified || 0),
                premium: parseInt(v.premium || 0)
            };
        });
    } else {
        handpickedList = defaultHandpicked;
    }

    const handpickedCardsHtml = handpickedList.map(v => {
        const isFav = state.favorites && state.favorites.includes(v.id);
        const badgeContent = v.icon ? `<i class="fa-solid ${v.icon}"></i>` : `<span>${v.initials}</span>`;
        const clickAction = typeof v.id === 'number' ? `viewVendorDetails(${v.id})` : `viewHandpickedVendor('${v.name}')`;
        
        let verifyBadgeHtml = '';
        if (v.verification_badge === 'gold') {
            verifyBadgeHtml = '<i class="fa-solid fa-circle-check verify-badge-gold" style="color: #D4AF37; margin-left: 4px; font-size: 0.78rem;" title="Gold Verified"></i>';
        } else if (v.verification_badge === 'blue' || parseInt(v.verified) === 1) {
            verifyBadgeHtml = '<i class="fa-solid fa-circle-check verify-badge-blue" style="color: #1DA1F2; margin-left: 4px; font-size: 0.78rem;" title="Verified"></i>';
        }

        return `
            <div class="handpicked-card" onclick="${clickAction}">
                <div class="handpicked-img-wrapper">
                    <img src="${v.img}" alt="${v.name}" class="handpicked-cover">
                    <button class="handpicked-fav-btn" onclick="toggleHandpickedFavorite('${v.id}', event)">
                        <i class="${isFav ? 'fa-solid fa-heart active' : 'fa-regular fa-heart'}"></i>
                    </button>
                    <div class="handpicked-logo-badge ${v.badgeClass || ''}">
                        ${badgeContent}
                    </div>
                </div>
                <div class="handpicked-details">
                    <h4 class="handpicked-card-title">${v.name}${verifyBadgeHtml}</h4>
                    <span class="handpicked-card-category">${v.category}</span>
                    <div class="handpicked-card-rating">
                        <i class="fa-solid fa-star"></i>
                        <span>${v.rating} <span class="reviews-count">(${v.reviews} reviews)</span></span>
                    </div>
                    <div class="handpicked-card-location">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>${v.city}</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    let html = `
        <!-- New Hero Banner matching mockup.jpg -->
        <div class="home-hero-banner" style="background-image: linear-gradient(180deg, rgba(8,23,41,0.6) 0%, rgba(8,23,41,0.85) 100%), url('${heroBg}');">
            <div class="home-hero-content">
                <h1 class="home-hero-title">${heroTitle}</h1>
                <div class="home-hero-divider"></div>
                
                <!-- Pill Search Bar -->
                <div class="home-hero-search">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" placeholder="What vendor are you looking for?" id="home-search-input" onkeyup="if(event.key==='Enter') triggerHomeSearch()">
                    <button class="search-arrow-btn" onclick="triggerHomeSearch()">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Handpicked For You Section matching mockup.jpg -->
        <div class="handpicked-section">
            <div class="section-header-row">
                <div class="section-titles">
                    <span class="section-subtitle">HANDPICKED FOR YOU</span>
                    <h2 class="section-main-title">The best for your special day</h2>
                </div>
                <a href="#" class="view-all-link" onclick="navigateTo('search'); event.preventDefault();">View all <i class="fa-solid fa-chevron-right"></i></a>
            </div>
            
            <div class="handpicked-scroller scrollable-x">
                ${handpickedCardsHtml}
            </div>
        </div>

        <!-- Dashboard / Event planner card if planning an event -->
        <div class="home-section" style="padding-top:0;">
            <div id="home-event-card-container"></div>
        </div>

        <div class="home-section" style="padding-top:10px;">
            <!-- Sponsored Ads Carousel -->
            ${adsHtml}

            <!-- Categories -->
            <div class="section-header categories-section-header">
                <div class="section-titles">
                    <span class="section-subtitle">CATEGORIES</span>
                    <h3 class="section-title">Browse Categories</h3>
                </div>
                <a href="#" class="section-link" onclick="openAllCategoriesModal(); event.preventDefault();">View All</a>
            </div>
            <div class="category-grid">
                ${categories.slice(0, 9).map(c => `
                    <div class="category-item" onclick="selectCategoryFilter('${c.name}')">
                        <div class="category-icon"><i class="fa-solid fa-${c.icon}"></i></div>
                        <div class="category-name">${c.name}</div>
                    </div>
                `).join('')}
            </div>
        </div>

        <!-- Premium Vendors -->
        <div class="home-section" style="background:var(--gray-50); padding-bottom:20px; padding-top:20px;">
            <div class="section-header featured-vendors-header">
                <div class="section-titles">
                    <span class="section-subtitle">PREMIUM SELECTION</span>
                    <h3 class="section-title">Featured Vendors</h3>
                </div>
                <a href="#" class="section-link" onclick="navigateTo('search'); event.preventDefault();">View All</a>
            </div>
            <div class="vendor-cards-scroll featured-vendors-container" id="featured-vendors-scroll">
                ${premiumVendors.length > 0 ? premiumVendors.map(v => `
                    <div class="vendor-card-h" onclick="viewVendorDetails(${v.id})">
                        <div class="vendor-card-cover">
                            <img src="${v.cover_photo || 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=300'}" alt="">
                            <div style="position:absolute; top:12px; right:10px; display:flex; flex-direction:column; gap:6px; z-index:5;">
                                <button class="vendor-card-fav ${v.is_favorite ? 'active' : ''}" onclick="toggleFavoriteHome(${v.id}, event)" style="position:static;">
                                    <i class="fa-solid fa-heart"></i>
                                </button>
                                <button onclick="shareVendorProfile(state.vendors.find(x => x.id === ${v.id}), event)" style="border:none; width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,0.9); color:#1B2B4B; display:flex; align-items:center; justify-content:center; font-size:0.75rem; cursor:pointer; box-shadow:var(--shadow-sm);">
                                    <i class="fa-solid fa-share-nodes"></i>
                                </button>
                            </div>
                        </div>
                        <div class="vendor-card-body">
                            <div class="vendor-card-name">${v.name}</div>
                            <div class="vendor-card-cat">${v.category}</div>
                            <div class="vendor-card-meta">
                                <div class="vendor-card-rating">
                                    <i class="fa-solid fa-star"></i>
                                    <span>${v.rating || '5.0'}</span>
                                </div>
                                <span style="font-size:0.65rem;font-weight:700;color:var(--primary);">${v.location.split(',')[0]}</span>
                            </div>
                        </div>
                    </div>
                `).join('') : '<p class="text-sm text-muted">No featured vendors found</p>'}
            </div>
            ${(() => {
                const numCols = Math.ceil(premiumVendors.length / 2);
                if (numCols > 1) {
                    return `
                        <div class="scroll-dots">
                            ${Array.from({ length: numCols }).map((_, i) => `
                                <span class="scroll-dot ${i === 0 ? 'active' : ''}" onclick="scrollToVendorCardColumn(${i})" data-idx="${i}"></span>
                            `).join('')}
                        </div>
                    `;
                }
                return '';
            })()}
        </div>

        <!-- Recommended for You Showcase (Section 6) -->
        <div class="home-section recommended-section" style="padding-bottom:20px; padding-top:20px; border-top:1px solid var(--gray-100); border-bottom:1px solid var(--gray-100);">
            <div class="section-header recommended-header">
                <div class="section-titles">
                    <span class="section-subtitle">RECOMMENDED</span>
                    <h3 class="section-title">Recommended for You</h3>
                </div>
                <a href="#" class="section-link" onclick="navigateTo('search'); event.preventDefault();">View All</a>
            </div>
            <div class="vendor-cards-scroll recommended-cards-container" style="display:flex; gap:16px; overflow-x:auto; padding-bottom:8px;">
                ${popularVendors.length > 0 ? popularVendors.map(v => `
                    <div class="vendor-card-h" onclick="viewVendorDetails(${v.id})" style="flex:0 0 160px; min-height:165px; margin-bottom:8px;">
                        <div class="vendor-card-cover" style="height:90px;">
                            <img src="${v.cover_photo || 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=300'}" alt="">
                            <button class="vendor-card-fav ${v.is_favorite ? 'active' : ''}" onclick="toggleFavoriteHome(${v.id}, event)">
                                <i class="fa-solid fa-heart"></i>
                            </button>
                        </div>
                        <div class="vendor-card-body" style="padding:6px 8px;">
                            <div class="vendor-card-name" style="font-size:0.75rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${v.name}</div>
                            <div class="vendor-card-cat" style="font-size:0.6rem; color:var(--gray-500);">${v.category}</div>
                            <div class="vendor-card-meta" style="margin-top:4px;">
                                <div class="vendor-card-rating" style="font-size:0.65rem;">
                                    <i class="fa-solid fa-star"></i>
                                    <span>${v.rating || '5.0'}</span>
                                </div>
                                <span style="font-size:0.6rem; color:var(--gray-400);"><i class="fa-solid fa-eye"></i> ${v.views_count || 0}</span>
                            </div>
                        </div>
                    </div>
                `).join('') : '<p class="text-sm text-muted">No recommendations found</p>'}
            </div>
        </div>

        <!-- Client Reviews / Testimonials -->
        <div class="home-section client-reviews-section" style="padding-bottom:30px; padding-top:20px;">
            <div class="section-header reviews-section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div class="section-titles">
                    <span class="section-subtitle">TESTIMONIALS</span>
                    <h3 class="section-title" style="margin:0;">What Our Planners Say</h3>
                </div>
                <button class="btn btn-text" style="font-size:0.75rem; color:var(--primary); font-weight:700; padding:0; background:none; border:none; cursor:pointer; display:flex; align-items:center; gap:4px;" onclick="openAllPlatformReviewsModal()">View All <i class="fa-solid fa-chevron-right" style="font-size:0.6rem;"></i></button>
            </div>
            <div class="vendor-cards-scroll reviews-cards-container">
                ${state.platformReviews.map(r => `
                    <div class="review-card">
                        <div class="review-header">
                            <img class="review-avatar" src="${r.avatar || (window.DEFAULT_USER_AVATAR || 'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><circle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%23081729\'/><circle cx=\'50\' cy=\'38\' r=\'18\' fill=\'%23FFFFFF\'/><path d=\'M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z\' fill=\'%23FFFFFF\'/></svg>')}" alt="">
                            <div>
                                <div class="review-name">${r.name}</div>
                                <div class="review-stars">${starsHTML(r.rating, '0.55rem')}</div>
                            </div>
                        </div>
                        <p class="review-text">"${r.comment}"</p>
                    </div>
                `).join('')}
            </div>
            <div style="text-align: center; margin-top: 18px;">
                <button class="btn btn-primary btn-sm" onclick="openPlatformReviewModal()" style="padding: 8px 20px; font-size: 0.75rem; font-weight: 700; border-radius: 20px; box-shadow: var(--shadow-sm);">
                    <i class="fa-solid fa-pen-to-square"></i> Give Your Review
                </button>
            </div>
        </div>
    `;

    screen.innerHTML = html;
    renderHomeEventCard();

    // Register scroll listener to update dots in real-time
    const scrollContainer = document.getElementById('featured-vendors-scroll');
    if (scrollContainer) {
        scrollContainer.addEventListener('scroll', () => {
            const index = Math.round(scrollContainer.scrollLeft / 194);
            const dots = document.querySelectorAll('.scroll-dots .scroll-dot');
            dots.forEach((dot, idx) => {
                if (idx === index) dot.classList.add('active');
                else dot.classList.remove('active');
            });
        }, { passive: true });
    }
}

function initHomeScreen() {
    const screen = document.getElementById('screen-home');
    if (!screen) return;

    if (state.homeCache) {
        renderHomeScreen(
            state.homeCache.premiumVendors,
            state.homeCache.categories,
            state.homeCache.activeAds,
            state.homeCache.popularVendors
        );
    } else {
        screen.innerHTML = `
            <div class="p-section" style="display:flex; flex-direction:column; gap:20px;">
                <div>${renderSkeletonCategoriesHTML(6)}</div>
                <div>${renderSkeletonCardsHTML(4)}</div>
            </div>
        `;
    }

    Promise.allSettled([
        API.getVendors({ premium_only: 1 }),
        API.getCategories(),
        API.get('get_advertisements'),
        API.getPopularVendors()
    ]).then((results) => {
        const premiumVendors = results[0].status === 'fulfilled' ? results[0].value : [];
        const categories = results[1].status === 'fulfilled' ? results[1].value : (state.categories || []);
        const activeAds = results[2].status === 'fulfilled' ? results[2].value : [];
        const popularVendors = results[3].status === 'fulfilled' ? results[3].value : [];

        state.homeCache = { premiumVendors, categories, activeAds, popularVendors };
        state.categories = categories;

        renderHomeScreen(premiumVendors, categories, activeAds, popularVendors);
    }).catch(err => {
        if (!state.homeCache) {
            screen.innerHTML = `<div class="p-section text-center"><p class="text-error">${err.message}</p></div>`;
        }
    });
}

window.scrollToVendorCardColumn = function(idx) {
    const scrollContainer = document.getElementById('featured-vendors-scroll');
    if (scrollContainer) {
        scrollContainer.scrollTo({
            left: idx * 194,
            behavior: 'smooth'
        });
    }
};

function handleAdClick(adId, destination, vendorId) {
    API.post('record_ad_click', { id: adId }).then(() => {
        if (destination === 'whatsapp') {
            window.open('https://wa.me/233209001100', '_blank');
        } else if (destination === 'packages') {
            viewVendorDetails(vendorId);
            setTimeout(() => {
                const el = document.getElementById('vendor-details-packages');
                if (el) el.scrollIntoView({ behavior: 'smooth' });
            }, 800);
        } else {
            viewVendorDetails(vendorId);
        }
    }).catch(err => {
        viewVendorDetails(vendorId);
    });
}

function renderHomeEventCard() {
    const container = document.getElementById('home-event-card-container');
    if (!container) return;

    API.getEvent().then(event => {
        state.event = event;
        if (event) {
            API.getTrackerStats().then(stats => {
                state.trackerStats = stats;
                const d = new Date(event.event_date);
                const now = new Date();
                const diffTime = d - now;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                const days = diffDays > 0 ? diffDays : 0;

                container.innerHTML = `
                    <div class="event-dashboard-card" onclick="navigateTo('event')">
                        <div class="edc-top">
                            <div>
                                <div class="edc-event-name">${event.event_name}</div>
                                <div class="edc-event-date">${formatFriendlyDate(event.event_date)}</div>
                            </div>
                            <div style="text-align:right;">
                                <div class="edc-days">${days}</div>
                                <div class="edc-days-label">Days to go</div>
                            </div>
                        </div>
                        <div class="edc-progress">
                            <div class="edc-progress-bar">
                                <div class="edc-progress-fill" style="width: ${stats.percentage}%;"></div>
                            </div>
                            <div class="edc-progress-label">
                                <span>Checklist Progress</span>
                                <span>${stats.percentage}% (${stats.completed}/${stats.total} Tasks)</span>
                            </div>
                        </div>
                    </div>
                `;
            });
        } else {
            container.innerHTML = '';
        }
    });
}

function triggerHomeSearch() {
    const val = document.getElementById('home-search-input')?.value.trim() || '';
    state.filters.search = val;
    navigateTo('search');
}

function selectCategoryFilter(cat) {
    state.filters.category = cat;
    localStorage.setItem('ohati_user_interest_category', cat);
    navigateTo('search');
}

function viewHandpickedVendor(name) {
    let targetId = 1; // Default fallback
    if (name.includes("Grand Pavilion")) targetId = 9;
    else if (name.includes("TasteBuds")) targetId = 7;
    else if (name.includes("Luxe Decor")) targetId = 6;
    viewVendorDetails(targetId);
}

function toggleHandpickedFavorite(key, e) {
    e.stopPropagation();
    let targetId = 1;
    if (key === 'pavilion') targetId = 9;
    else if (key === 'tastebuds') targetId = 7;
    else if (key === 'luxe') targetId = 6;
    toggleFavoriteHome(targetId, e);
}

function toggleFavoriteHome(vid, e) {
    e.stopPropagation();
    if (!state.user) {
        showPushNotification('Sign In Required', 'Please sign in or sign up to save favorites.');
        openLoginModal();
        return;
    }
    API.toggleFavorite(vid).then(res => {
        showPushNotification(res.is_favorite ? 'Added to Saved' : 'Removed from Saved', 'Syncing favorites.');
        initHomeScreen();
    });
}

// ── 2. SEARCH SCREEN ────────────────────────────────────────────────────
function initSearchScreen() {
    const screen = document.getElementById('screen-search');
    if (!screen) return;

    screen.innerHTML = `
        <div class="p-section search-controls-wrap" style="padding-bottom:10px; display:flex; gap:10px; align-items:center;">
            <div class="search-bar" style="flex:1;">
                <input type="text" placeholder="Search vendors..." id="search-input" value="${state.filters.search || ''}" onkeyup="if(event.key==='Enter') triggerSearch()">
                <button class="search-bar-btn" onclick="triggerSearch()"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
            <button class="btn btn-outline" style="padding:10px; height:44px; width:44px; border-radius:var(--radius-md);" onclick="openFilterDrawer()">
                <i class="fa-solid fa-sliders" style="font-size:1.1rem; color:var(--primary);"></i>
            </button>
        </div>
        <div id="search-vendors-list" class="p-section" style="padding-top:0;">
            ${(state.vendors && state.vendors.length > 0) ? '' : renderSkeletonCardsHTML(6)}
        </div>
    `;

    if (state.vendors && state.vendors.length > 0) {
        renderSearchScreen();
    }

    API.getVendors(state.filters).then(vendors => {
        state.vendors = vendors;
        renderSearchScreen();
    });
}

function triggerSearch() {
    state.filters.search = document.getElementById('search-input')?.value.trim() || '';
    initSearchScreen();
}

function renderSearchScreen() {
    const container = document.getElementById('search-vendors-list');
    if (!container) return;

    if (state.vendors.length === 0) {
        container.innerHTML = `
            <div class="text-center" style="padding:40px 0;">
                <i class="fa-solid fa-circle-exclamation" style="font-size:2.5rem; color:var(--gray-300); margin-bottom:12px;"></i>
                <p class="text-sm text-muted">No vendors matching filters found</p>
                <button class="btn btn-outline btn-sm mt-16" onclick="resetAllFilters()">Reset Filters</button>
            </div>
        `;
        return;
    }

    container.innerHTML = state.vendors.map(v => {
        const isFeatured = parseInt(v.featured) === 1;
        const isPremium = v.verification_badge === 'gold' || parseInt(v.premium) === 1;
        
        let badgeHtml = '';
        if (v.verification_badge === 'gold') {
            badgeHtml = `<span class="card-trust-badge premium-badge"><i class="fa-solid fa-crown"></i><span class="badge-text"> Premium</span></span>`;
        } else if (v.verification_badge === 'blue') {
            badgeHtml = `<span class="card-trust-badge verified-badge"><i class="fa-solid fa-circle-check"></i><span class="badge-text"> Verified</span></span>`;
        }

        const sponsoredBadge = isFeatured ? `<span class="card-sponsored-badge"><i class="fa-solid fa-rectangle-ad"></i><span class="badge-text"> Sponsored</span></span>` : '';
        const ratingVal = parseFloat(v.rating || '5.0');
        let ratingWord = 'Rating';

        return `
            <div class="vendor-list-item" onclick="viewVendorDetails(${v.id})">
                <div class="vendor-list-cover-wrapper">
                    <img class="vendor-list-cover" src="${v.cover_photo || 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=300'}" alt="${v.name}">
                </div>
                <div class="vendor-list-info" style="display:flex; flex-direction:column; gap:4px; padding:12px 4px 4px 4px;">
                    <div class="vendor-list-cat" style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1px; color:var(--gray-600, #4B5563); font-weight:700; margin:0;">${v.category}</div>
                    <div class="vendor-list-name" style="font-size:1.05rem; font-weight:800; color:var(--gray-900); display:flex; align-items:center; gap:6px; margin:0;">
                        <span>${v.name}</span>
                        ${v.verification_badge === 'gold' ? `<i class="fa-solid fa-circle-check" style="color:#D4AF37;" title="Gold Verified"></i>` : ''}
                        ${v.verification_badge === 'blue' ? `<i class="fa-solid fa-circle-check" style="color:#1DA1F2;" title="ID Verified"></i>` : ''}
                    </div>
                    
                    <div class="vendor-inline-badges">
                        ${isPremium ? `<span class="inline-badge inline-premium-badge"><i class="fa-solid fa-crown"></i> Premium</span>` : ''}
                        ${isFeatured ? `<span class="inline-badge inline-sponsored-badge"><i class="fa-solid fa-rectangle-ad"></i> Sponsored</span>` : ''}
                        ${v.verification_badge === 'blue' ? `<span class="inline-badge inline-verified-badge"><i class="fa-solid fa-circle-check"></i> Verified</span>` : ''}
                    </div>

                    <div class="vendor-list-loc" style="font-size:0.75rem; color:var(--gray-600); display:flex; align-items:center; gap:4px; margin:2px 0 6px 0;">
                        <i class="fa-solid fa-location-dot" style="color:var(--rose);"></i> <span>${v.location}</span>
                    </div>
                    
                    <div class="vendor-rating-row" style="display:flex; align-items:center; gap:6px; margin-bottom:8px;">
                        <span class="rating-badge" style="background:rgba(27,43,75,0.06); color:var(--primary); font-weight:800; padding:2px 6px; border-radius:6px; font-size:0.75rem;"><i class="fa-solid fa-star" style="color:#F59E0B; margin-right:3px;"></i>${parseFloat(v.rating || '5.0').toFixed(1)}</span>
                        <span class="rating-text" style="font-size:0.72rem; font-weight:700; color:var(--gray-800);">${ratingWord}</span>
                        <span class="rating-count" style="font-size:0.7rem; color:var(--gray-500);">(${v.reviews_count || 12} reviews)</span>
                    </div>

                    <div class="vendor-list-bottom" style="display:flex; align-items:center; justify-content:space-between; margin-top:auto; border-top:1px solid var(--gray-100); padding-top:8px;">
                        <div class="vendor-list-price" style="font-size:0.85rem; font-weight:800; color:var(--primary);">Ask for Price</div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button class="vendor-card-fav ${v.is_favorite ? 'active' : ''}" onclick="toggleFavoriteSearch(${v.id}, event)" title="Save Vendor">
                                <i class="fa-solid fa-heart"></i>
                            </button>
                            <span class="btn-view-profile" style="font-size:0.75rem; font-weight:700; color:var(--primary, #1B2B4B); display:flex; align-items:center; gap:4px;">View Profile <i class="fa-solid fa-arrow-right"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function toggleFavoriteSearch(vid, e) {
    e.stopPropagation();
    if (!state.user) {
        showPushNotification('Sign In Required', 'Please sign in or sign up to save favorites.');
        openLoginModal();
        return;
    }
    API.toggleFavorite(vid).then(res => {
        showPushNotification(res.is_favorite ? 'Added to Saved' : 'Removed from Saved', 'Syncing favorites.');
        const v = state.vendors.find(x => x.id === vid);
        if (v) v.is_favorite = res.is_favorite;
        renderSearchScreen();
    });
}

function viewVendorDetails(id) {
    state.selectedVendorId = id;
    state.activeDetailTab = 'overview';
    navigateTo('detail');
}

function viewCustomerProfileModal(customerId) {
    // Show spinner in modal first
    openModal(`
        <div class="auth-modal-header" style="margin-bottom:20px;">
            <h2 class="auth-modal-title">Client Profile</h2>
        </div>
        <div class="full-spinner-wrap"><div class="spinner"></div></div>
    `);
    
    API.getVendorDetails(customerId, true).then(c => {
        let avatarUrl = c.logo || (window.DEFAULT_USER_AVATAR || 'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><circle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%23081729\'/><circle cx=\'50\' cy=\'38\' r=\'18\' fill=\'%23FFFFFF\'/><path d=\'M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z\' fill=\'%23FFFFFF\'/></svg>');
        let contactRows = '';
        if (c.phone) {
            contactRows += `
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px; font-size:0.85rem; color:var(--gray-700);">
                    <i class="fa-solid fa-phone" style="width:20px; color:var(--primary); font-size:1rem;"></i>
                    <div>
                        <div style="font-weight:700;">Phone Number</div>
                        <a href="tel:${c.phone}" style="color:var(--primary); text-decoration:none;">${c.phone}</a>
                    </div>
                </div>
            `;
        }
        if (c.email) {
            contactRows += `
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px; font-size:0.85rem; color:var(--gray-700);">
                    <i class="fa-solid fa-envelope" style="width:20px; color:var(--rose); font-size:1rem;"></i>
                    <div>
                        <div style="font-weight:700;">Email Address</div>
                        <a href="mailto:${c.email}" style="color:var(--rose); text-decoration:none;">${c.email}</a>
                    </div>
                </div>
            `;
        }
        
        let html = `
            <div class="auth-modal-header" style="margin-bottom:16px;">
                <h2 class="auth-modal-title"><i class="fa-solid fa-user-tie" style="color:var(--accent);"></i> Client Details</h2>
                <p class="auth-modal-subtitle">Customer profile information</p>
            </div>
            
            <div style="display:flex; flex-direction:column; align-items:center; text-align:center; padding:12px 0 20px; border-bottom:1px solid var(--gray-100); margin-bottom:20px;">
                <img src="${avatarUrl}" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid var(--gray-100); box-shadow:var(--shadow-md); margin-bottom:12px;">
                <h3 style="margin:0; font-size:1.15rem; color:var(--gray-900); font-weight:800;">${c.name}</h3>
                <span style="font-size:0.7rem; background:rgba(27,43,75,0.06); color:var(--primary); padding:3px 10px; border-radius:20px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-top:6px;">Client / Customer</span>
            </div>
            
            <div style="padding:0 8px;">
                ${contactRows || '<p style="font-size:0.8rem; color:var(--gray-400); text-align:center;">No contact details available.</p>'}
                <div style="display:flex; align-items:center; gap:12px; font-size:0.85rem; color:var(--gray-700); margin-top:12px;">
                    <i class="fa-solid fa-location-dot" style="width:20px; color:var(--accent); font-size:1.1rem;"></i>
                    <div>
                        <div style="font-weight:700;">Location</div>
                        <span style="color:var(--gray-500);">${c.location || 'Accra, Ghana'}</span>
                    </div>
                </div>
            </div>
            
            <button class="btn btn-primary btn-full mt-20" onclick="closeModal()" style="padding:12px;">Close Profile</button>
        `;
        openModal(html);
    }).catch(err => {
        openModal(`
            <div class="auth-modal-header"><h2 class="auth-modal-title">Error</h2></div>
            <p style="text-align:center; padding:20px; color:var(--error);">${err.message || 'Could not load client details.'}</p>
        `);
    });
}

// ── 3. VENDOR DETAIL SCREEN ─────────────────────────────────────────────
function initDetailScreen() {
    const screen = document.getElementById('screen-detail');
    if (!screen) return;

    screen.innerHTML = renderSkeletonVendorDetailHTML();

    let targetId = state.selectedVendorId;
    if (!targetId && state.user) {
        targetId = state.user.vendor_id || state.user.id;
    }

    API.getVendorDetails(targetId).then(v => {
        state.selectedVendorObject = v;
        let isCompareActive = state.compareList.includes(v.id);

        let html = `
            <div class="detail-hero">
                <img class="detail-cover" src="${v.cover_photo || 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=800'}" alt="${escapeHtml(v.name)} - ${escapeHtml(v.category)} in ${escapeHtml(v.city || 'Ghana')}" title="${escapeHtml(v.name)}">
                <div class="detail-hero-overlay"></div>
                <button class="detail-back-btn" onclick="navigateBack()"><i class="fa-solid fa-chevron-left"></i></button>
                <div class="detail-actions-top">
                    <button class="detail-action-btn" onclick="shareVendorProfile(state.selectedVendorObject, event)" title="Share">
                        <i class="fa-solid fa-share-nodes"></i>
                    </button>
                    <button class="detail-action-btn ${isCompareActive ? 'fav-active' : ''}" onclick="toggleCompareDetail(${v.id}, event)" title="Compare">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </button>
                    <button class="detail-action-btn ${v.is_following ? 'fav-active' : ''}" onclick="toggleFollowDetail(${v.id}, event)" title="${v.is_following ? 'Unfollow' : 'Follow'}">
                        <i class="fa-solid ${v.is_following ? 'fa-user-check' : 'fa-user-plus'}"></i>
                    </button>
                    <button class="detail-action-btn ${v.is_favorite ? 'fav-active' : ''}" onclick="toggleFavoriteDetail(${v.id}, event)" title="Save">
                        <i class="fa-solid fa-heart"></i>
                    </button>
                </div>
                <div class="detail-vendor-identity">
                    <img class="detail-logo" src="${v.logo || 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=400'}" alt="${escapeHtml(v.name)} Logo - ${escapeHtml(v.category)}" title="${escapeHtml(v.name)}">
                    <div class="detail-vendor-name" style="display:flex; align-items:center; gap:6px;">
                        <span>${v.name}</span>
                        ${v.verification_badge === 'gold' ? `<i class="fa-solid fa-circle-check" style="color:#FFD700;" title="Gold Verified"></i>` : ''}
                        ${v.verification_badge === 'blue' ? `<i class="fa-solid fa-circle-check" style="color:#1DA1F2;" title="ID Verified"></i>` : ''}
                    </div>
                    <div class="detail-vendor-cat">${v.category}</div>
                </div>
            </div>

            <div class="detail-body">
                <div class="badge-wrap">
                    ${v.verification_badge === 'gold' ? `<span class="badge badge-verified"><i class="fa-solid fa-circle-check"></i> Gold Verified</span>` : ''}
                    ${v.verification_badge === 'blue' ? `<span class="badge badge-verified"><i class="fa-solid fa-circle-check"></i> ID Verified</span>` : ''}
                    ${v.premium ? `<span class="badge badge-premium"><i class="fa-solid fa-crown"></i> Premium Vendor</span>` : ''}
                    ${v.has_insurance ? `<span class="badge badge-insurance"><i class="fa-solid fa-shield-halved"></i> Insured</span>` : ''}
                    <span class="badge badge-top"><i class="fa-solid fa-briefcase"></i> ${v.experience} Years</span>
                </div>

                <div class="vendor-stats-row">
                    <div class="vendor-stat">
                        <div class="vendor-stat-val">${v.rating || '5.0'}</div>
                        <div class="vendor-stat-label">Rating</div>
                    </div>
                    <div class="vendor-stat">
                        <div class="vendor-stat-val">${v.reviews_count || (v.reviews ? v.reviews.length : 0)}</div>
                        <div class="vendor-stat-label">Reviews</div>
                    </div>
                    <div class="vendor-stat">
                        <div class="vendor-stat-val">${v.views_count || 0}</div>
                        <div class="vendor-stat-label">Views</div>
                    </div>
                    <div class="vendor-stat">
                        <div class="vendor-stat-val" id="detail-follower-count">${v.followers_count || 0}</div>
                        <div class="vendor-stat-label">Followers</div>
                    </div>
                </div>

                <div class="detail-tabs">
                    <div class="detail-tab ${state.activeDetailTab === 'overview' ? 'active' : ''}" onclick="selectDetailTab('overview')">Overview</div>
                    <div class="detail-tab ${state.activeDetailTab === 'packages' ? 'active' : ''}" onclick="selectDetailTab('packages')">Packages</div>
                    <div class="detail-tab ${state.activeDetailTab === 'gallery' ? 'active' : ''}" onclick="selectDetailTab('gallery')">Gallery</div>
                    <div class="detail-tab ${state.activeDetailTab === 'reviews' ? 'active' : ''}" onclick="selectDetailTab('reviews')">Reviews (${v.reviews ? v.reviews.length : 0})</div>
                </div>

                <div id="detail-tab-content" style="margin-bottom:20px;"></div>

                <div class="detail-cta">
                    <button class="btn btn-outline" onclick="startVendorChat(${v.id || v.vendor_id})"><i class="fa-solid fa-comments"></i> Chat</button>
                    <button class="btn btn-primary" onclick="openBookingRequestModal(${v.id})"><i class="fa-solid fa-calendar-check"></i> Book Now</button>
                </div>

                <!-- Discovery Carousels Wrapper -->
                <div id="detail-recommendations-wrapper" style="margin-bottom:24px; padding:0;"></div>
            </div>
        `;
        screen.innerHTML = html;
        renderDetailTabContent(v);
        renderProfileRecommendations(v);
    }).catch(err => {
        screen.innerHTML = `
            <div style="padding:50px 20px; text-align:center; max-width:480px; margin:0 auto;">
                <i class="fa-solid fa-circle-exclamation" style="font-size:2.8rem; color:var(--accent); margin-bottom:12px;"></i>
                <h3 style="font-size:1.1rem; font-weight:800; color:var(--primary); margin-bottom:8px;">Profile Preview Unavailable</h3>
                <p style="font-size:0.82rem; color:var(--gray-600); line-height:1.5; margin-bottom:20px;">${err.message || 'Vendor details could not be loaded. Please ensure your vendor profile has been created.'}</p>
                <button class="btn btn-primary" onclick="navigateBack()"><i class="fa-solid fa-arrow-left"></i> Return Back</button>
            </div>
        `;
    });
}

function toggleFollowDetail(vid, e) {
    e.stopPropagation();
    if (!state.user) {
        showPushNotification('Sign In Required', 'Please sign in to follow vendors.');
        openLoginModal();
        return;
    }
    API.followVendor(vid).then(res => {
        showPushNotification(res.followed ? 'Following Vendor' : 'Unfollowed Vendor', 'State synced.');
        initDetailScreen();
    }).catch(err => {
        showPushNotification('Error', err.message);
    });
}

function renderProfileRecommendations(vendor) {
    const recWrapper = document.getElementById('detail-recommendations-wrapper');
    if (!recWrapper) return;

    Promise.all([
        API.getRecommendedVendors(vendor.category, vendor.id),
        API.getTrustedVendors(),
        API.getPopularVendors()
    ]).then(([rec, trs, pop]) => {
        if (rec) rec = rec.slice(0, 5);
        if (trs) trs = trs.slice(0, 5);
        if (pop) pop = pop.slice(0, 5);
        let html = '';

        if (rec && rec.length > 0) {
            html += `
                <div style="margin-top:24px; padding: 0 16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h4 style="font-family:'Fraunces',serif; font-size:1.15rem; margin:0; color:var(--primary);">Recommended Vendors</h4>
                        <a href="#" onclick="navigateTo('search'); event.preventDefault();" style="font-size:0.75rem; color:var(--accent); font-weight:700; text-decoration:none;">View All</a>
                    </div>
                    <div class="scrollable-x recommendations-scroller" style="display:flex; gap:12px; padding-bottom:8px; overflow-x:auto;">
                        ${rec.map(v => renderRecommendationCard(v)).join('')}
                    </div>
                </div>
            `;
        }

        if (trs && trs.length > 0) {
            html += `
                <div style="margin-top:24px; padding: 0 16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h4 style="font-family:'Fraunces',serif; font-size:1.15rem; margin:0; color:var(--primary);">Most Trusted Vendors</h4>
                        <a href="#" onclick="navigateTo('search'); event.preventDefault();" style="font-size:0.75rem; color:var(--accent); font-weight:700; text-decoration:none;">View All</a>
                    </div>
                    <div class="scrollable-x recommendations-scroller" style="display:flex; gap:12px; padding-bottom:8px; overflow-x:auto;">
                        ${trs.map(v => renderRecommendationCard(v)).join('')}
                    </div>
                </div>
            `;
        }

        if (pop && pop.length > 0) {
            html += `
                <div style="margin-top:24px; padding: 0 16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h4 style="font-family:'Fraunces',serif; font-size:1.15rem; margin:0; color:var(--primary);">Popular Vendors</h4>
                        <a href="#" onclick="navigateTo('search'); event.preventDefault();" style="font-size:0.75rem; color:var(--accent); font-weight:700; text-decoration:none;">View All</a>
                    </div>
                    <div class="scrollable-x recommendations-scroller" style="display:flex; gap:12px; padding-bottom:8px; overflow-x:auto;">
                        ${pop.map(v => renderRecommendationCard(v)).join('')}
                    </div>
                </div>
            `;
        }

        recWrapper.innerHTML = html;
    }).catch(err => {
        console.error("Error loading recommendations", err);
    });
}

function renderRecommendationCard(v) {
    const isGold = v.verification_badge === 'gold';
    const isBlue = v.verification_badge === 'blue';
    const isPromoted = parseInt(v.featured) === 1 || parseInt(v.premium) === 1;

    return `
        <div class="recommendation-card" onclick="viewVendorDetails(${v.id})" style="flex:0 0 180px; background:#fff; border:1px solid var(--gray-200); border-radius:12px; overflow:hidden; cursor:pointer; box-shadow:var(--shadow-sm); transition:transform 0.2s ease;">
            <div style="position:relative; height:105px; background:var(--gray-100);">
                <img src="${v.cover_photo || 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=200'}" style="width:100%; height:100%; object-fit:cover; display:block;">
                ${isPromoted ? `
                    <span style="position:absolute; top:6px; left:6px; background:var(--accent); color:#fff; font-size:0.55rem; font-weight:800; padding:2px 6px; border-radius:4px; display:flex; align-items:center; gap:2px;">
                        <i class="fa-solid fa-crown" style="font-size:0.5rem;"></i> Promoted
                    </span>
                ` : ''}
            </div>
            <div style="padding:10px;">
                <div style="font-weight:700; font-size:0.75rem; color:var(--gray-800); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:flex; align-items:center; gap:3px;">
                    <span>${v.name}</span>
                    ${isGold ? `<i class="fa-solid fa-circle-check" style="color:#FFD700; font-size:0.65rem;"></i>` : ''}
                    ${isBlue ? `<i class="fa-solid fa-circle-check" style="color:#1DA1F2; font-size:0.65rem;"></i>` : ''}
                </div>
                <div style="font-size:0.65rem; color:var(--gray-500); margin:2px 0 6px 0;">${v.category}</div>
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.65rem;">
                    <span style="color:var(--warning); font-weight:700;"><i class="fa-solid fa-star"></i> ${v.rating || '5.0'}</span>
                    <span style="color:var(--gray-400);"><i class="fa-solid fa-location-dot"></i> ${v.location ? v.location.split(',')[0] : 'Ghana'}</span>
                </div>
            </div>
        </div>
    `;
}

function selectDetailTab(tab) {
    state.activeDetailTab = tab;
    initDetailScreen();
}

function toggleFavoriteDetail(vid, e) {
    e.stopPropagation();
    API.toggleFavorite(vid).then(res => {
        showPushNotification(res.is_favorite ? 'Added to Saved' : 'Removed from Saved', 'Syncing favorites.');
        initDetailScreen();
    });
}

function toggleCompareDetail(vid, e) {
    e.stopPropagation();
    const v = state.selectedVendorObject;
    if (!v) return;

    openModal(`
        <div class="auth-modal-header"><h2 class="auth-modal-title"><i class="fa-solid fa-scale-balanced" style="color:var(--accent);"></i> Quick Compare</h2><p class="auth-modal-subtitle">Loading ${v.category} vendors…</p></div>
        <div style="text-align:center;padding:40px 0;"><div class="spinner"></div></div>
    `);

    API.getRecommendedVendors(v.category, v.id).then(others => {
        if (!others || others.length === 0) {
            openModal(`
                <div class="auth-modal-header"><h2 class="auth-modal-title"><i class="fa-solid fa-scale-balanced" style="color:var(--accent);"></i> Quick Compare</h2><p class="auth-modal-subtitle">No other ${v.category} vendors found to compare.</p></div>
                <div style="text-align:center;padding:30px 0;"><i class="fa-solid fa-users-slash" style="font-size:2.5rem;color:var(--gray-200);margin-bottom:12px;display:block;"></i><p class="text-sm text-muted">Be the first to explore more ${v.category} vendors on Ohati!</p></div>
            `);
            return;
        }

        const compare = others.slice(0, 3);
        const allVendors = [v, ...compare];
        const fmt = (n) => parseFloat(n || 0).toFixed(1);
        const getMinPrice = (v) => {
            return 'Ask for Price';
        };

        const badge = (val, best, icon, unit = '') => {
            const isBest = val === best;
            return `<span style="font-weight:700;color:${isBest ? 'var(--success)' : 'var(--gray-700)'};font-size:0.78rem;">${icon ? '<i class="fa-solid fa-' + icon + '" style="margin-right:3px;font-size:0.65rem;color:' + (isBest ? 'var(--success)' : 'var(--gray-400)') + ';"></i>' : ''}${val}${unit}</span>${isBest ? '<span style="font-size:0.55rem;background:rgba(16,185,129,0.1);color:var(--success);padding:1px 5px;border-radius:4px;margin-left:4px;font-weight:700;">BEST</span>' : ''}`;
        };

        const ratings = allVendors.map(v => parseFloat(v.rating || 0));
        const experiences = allVendors.map(v => parseInt(v.experience || 0));
        const reviews = allVendors.map(v => parseInt(v.reviews_count || 0));
        const bestRating = Math.max(...ratings);
        const bestExp = Math.max(...experiences);
        const bestReviews = Math.max(...reviews);

        const rows = [
            { label: 'Rating', icon: 'star', fn: (v, i) => badge(fmt(v.rating || 0), fmt(bestRating), 'star', '') },
            { label: 'Reviews', icon: 'comments', fn: (v, i) => badge(parseInt(v.reviews_count || 0), bestReviews, '', '') },
            { label: 'Experience', icon: 'briefcase', fn: (v, i) => badge(parseInt(v.experience || 0), bestExp, '', ' yrs') },
            { label: 'Location', icon: 'location-dot', fn: (v) => `<span style="font-size:0.72rem;color:var(--gray-600);">${v.location || '—'}</span>` },
            { label: 'Starting Price', icon: 'tag', fn: (v) => `<span style="font-size:0.78rem;font-weight:700;color:var(--primary);">Ask for Price</span>` },
            { label: 'Availability', icon: 'clock', fn: (v) => `<span style="font-size:0.72rem;color:${v.availability === 'Available' ? 'var(--success)' : 'var(--warning)'};">${v.availability || 'Available'}</span>` },
            { label: 'Verified', icon: 'shield-halved', fn: (v) => v.verified == 1 ? '<i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Yes' : '<span style="color:var(--gray-400);">No</span>' },
        ];

        let modalHTML = `
            <div class="auth-modal-header" style="margin-bottom:12px;">
                <h2 class="auth-modal-title"><i class="fa-solid fa-scale-balanced" style="color:var(--accent);"></i> Quick Compare</h2>
                <p class="auth-modal-subtitle">${v.name} vs ${compare.length} other ${v.category} vendor${compare.length > 1 ? 's' : ''}</p>
            </div>
            <div style="overflow-x:auto;margin:0 -20px;padding:0 20px;">
                <table style="width:100%;border-collapse:separate;border-spacing:0;font-size:0.75rem;">
                    <thead>
                        <tr>
                            <th style="padding:8px 6px;text-align:left;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid var(--gray-200);min-width:70px;"></th>
                            ${allVendors.map((cv, i) => `
                                <th style="padding:8px 6px;text-align:center;border-bottom:2px solid ${i === 0 ? 'var(--accent)' : 'var(--gray-200)'};min-width:90px;cursor:pointer;" onclick="${i > 0 ? 'closeModal(); viewVendorDetails(' + cv.id + ')' : ''}">
                                    <img src="${cv.logo || 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=400'}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid ${i === 0 ? 'var(--accent)' : 'var(--gray-200)'};margin-bottom:4px;display:block;margin-left:auto;margin-right:auto;">
                                    <div style="font-size:0.7rem;font-weight:700;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:90px;">${cv.name}</div>
                                    ${i === 0 ? '<span style="font-size:0.5rem;background:var(--accent);color:var(--primary-dark);padding:1px 6px;border-radius:3px;font-weight:800;text-transform:uppercase;">Current</span>' : ''}
                                </th>
                            `).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row, ri) => `
                            <tr style="background:${ri % 2 === 0 ? 'var(--gray-50)' : 'transparent'};">
                                <td style="padding:8px 6px;font-weight:600;color:var(--gray-500);font-size:0.68rem;white-space:nowrap;"><i class="fa-solid fa-${row.icon}" style="width:14px;color:var(--gray-400);margin-right:4px;font-size:0.6rem;"></i>${row.label}</td>
                                ${allVendors.map((cv, i) => `
                                    <td style="padding:8px 6px;text-align:center;${i === 0 ? 'background:rgba(242,167,53,0.04);' : ''}">${row.fn(cv, i)}</td>
                                `).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                ${compare.map(cv => `
                    <button class="btn btn-outline btn-sm" style="flex:1;font-size:0.7rem;" onclick="closeModal(); viewVendorDetails(${cv.id})">
                        View ${cv.name.split(' ')[0]}
                    </button>
                `).join('')}
            </div>
        `;

        openModal(modalHTML);
    }).catch(err => {
        openModal(`
            <div class="auth-modal-header"><h2 class="auth-modal-title">Compare</h2></div>
            <div style="text-align:center;padding:30px 0;color:var(--gray-400);"><p style="font-size:0.8rem;">${err.message || 'Could not load comparison data.'}</p></div>
        `);
    });
}

function renderDetailTabContent(v) {
    const container = document.getElementById('detail-tab-content');
    if (!container) return;

    let html = '';
    switch (state.activeDetailTab) {
        case 'overview':
            let hoursHTML = 'Always Available (24/7)';
            if (v.working_hours && typeof v.working_hours === 'object') {
                if (v.working_hours.always) {
                    hoursHTML = 'Always Available (24/7)';
                } else {
                    const activeDays = Object.entries(v.working_hours)
                        .filter(([_, details]) => details.active)
                        .map(([day, details]) => `${day}: ${details.start} - ${details.end}`);
                    if (activeDays.length > 0) {
                        hoursHTML = activeDays.join(', ');
                    }
                }
            }
            
            let socialHTML = '';
            if (v.social_links) {
                const socials = typeof v.social_links === 'string' ? JSON.parse(v.social_links) : v.social_links;
                const links = [];
                if (socials.instagram) {
                    links.push(`<a href="${socials.instagram}" target="_blank" style="color:var(--rose); font-size:1.25rem; margin-right:16px;" title="Instagram"><i class="fa-brands fa-instagram"></i></a>`);
                }
                if (socials.facebook) {
                    links.push(`<a href="${socials.facebook}" target="_blank" style="color:#1877F2; font-size:1.25rem; margin-right:16px;" title="Facebook"><i class="fa-brands fa-facebook"></i></a>`);
                }
                if (socials.tiktok) {
                    links.push(`<a href="${socials.tiktok}" target="_blank" style="color:#000000; font-size:1.25rem;" title="TikTok"><i class="fa-brands fa-tiktok"></i></a>`);
                }
                if (links.length > 0) {
                    socialHTML = `
                        <div style="margin-top:16px; border-top:1px solid var(--gray-100); padding-top:12px;">
                            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--gray-400); margin-bottom:8px;">Social Media</div>
                            <div style="display:flex; align-items:center;">
                                ${links.join('')}
                            </div>
                        </div>
                    `;
                }
            }

            html = `
                <div style="font-size:0.83rem; line-height:1.6; color:var(--gray-600); margin-bottom:16px;">
                    ${v.description}
                </div>
                <div style="font-size:0.8rem; display:flex; flex-direction:column; gap:8px;">
                    <div><i class="fa-solid fa-location-dot" style="color:var(--rose); margin-right:8px; width:16px;"></i> ${v.location}</div>
                    <div><i class="fa-solid fa-globe" style="color:var(--primary); margin-right:8px; width:16px;"></i> ${v.service_radius} Coverage</div>
                    <div><i class="fa-solid fa-clock" style="color:var(--accent); margin-right:8px; width:16px;"></i> Hours: ${hoursHTML}</div>
                </div>
                ${socialHTML}
            `;
            break;

        case 'packages':
            const pkgsList = (Array.isArray(v.packages_pricing) ? v.packages_pricing : []);
            if (pkgsList.length === 0) {
                html = `
                    <div style="text-align:center; padding:40px 16px; color:var(--gray-500); background:var(--gray-50); border-radius:16px; border:1px solid #E5E7EB;">
                        <i class="fa-solid fa-box-open" style="font-size:2.2rem; color:var(--gray-300); margin-bottom:10px; display:block;"></i>
                        <h4 style="margin:0 0 6px 0; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">No Preset Packages Created Yet</h4>
                        <p style="font-size:0.8rem; color:var(--gray-600); margin:0 0 16px 0; line-height:1.5;">This vendor provides custom, bespoke service quotes tailored directly to your event requirements.</p>
                        <button class="btn btn-primary btn-sm" onclick="openDiscountRequestModal(${v.id})" style="font-weight:700;">
                            <i class="fa-solid fa-paper-plane"></i> Request Custom Quote
                        </button>
                    </div>
                `;
            } else {
                html = `
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        ${pkgsList.map(p => `
                            <div class="package-card" style="border:1px solid var(--gray-100); border-radius:var(--radius-lg); padding:18px; background:var(--white); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; gap:10px; transition:all 0.25s ease;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; width:100%;">
                                    <div class="package-name" style="font-size:1.05rem; font-weight:800; color:var(--gray-900); margin:0;">${p.name || 'Custom Package'}</div>
                                    <div class="package-price" style="font-size:1.1rem; font-weight:800; color:var(--primary);">${p.price ? (p.price.includes('GH') || p.price.includes('$') ? p.price : 'GH₵ ' + p.price) : 'Ask for Price'}</div>
                                </div>
                                <div class="package-details" style="font-size:0.8rem; color:var(--gray-600); line-height:1.5; margin-bottom:12px;">${p.details || 'Standard service package details.'}</div>
                                <div style="display:flex; align-items:center; justify-content:space-between; margin-top:auto; border-top:1px solid var(--gray-50); padding-top:12px;">
                                    <span style="font-size:0.75rem; color:var(--gray-400);"><i class="fa-solid fa-clock"></i> Flexible Schedule</span>
                                    <button class="btn btn-outline btn-sm" onclick="openBookingRequestModal(${v.id}, '${p.name || 'Custom Service'}', ''); event.stopPropagation();" style="font-size:0.72rem; padding:6px 16px;">Select Package</button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            break;

        case 'gallery':
            const galList = v.gallery || [];
            const galPage = state.publicGalleryPage || 1;
            const galPerPage = 26;
            const galTotalPages = Math.ceil(galList.length / galPerPage) || 1;
            const galStart = (galPage - 1) * galPerPage;
            const galSlice = galList.slice(galStart, galStart + galPerPage);

            if (galList.length === 0) {
                html = `<div style="text-align:center; padding:40px 0; color:var(--gray-400); font-size:0.8rem;"><i class="fa-solid fa-images" style="font-size:2rem; margin-bottom:8px; display:block;"></i>No portfolio photos uploaded yet.</div>`;
            } else {
                html = `
                    <div style="font-size:0.75rem; color:var(--gray-600); margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                        <span>Showcase Portfolio: <strong>${galList.length} Photos</strong></span>
                        ${galTotalPages > 1 ? `<span style="font-weight:700; color:var(--primary);">Page ${galPage} of ${galTotalPages}</span>` : ''}
                    </div>
                    
                    <div class="gallery-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:8px;">
                        ${galSlice.map((img, i) => `
                            <div class="gallery-thumb" onclick="openLightbox(${JSON.stringify(galList)}, ${galStart + i})" style="position:relative; height:100px; border-radius:8px; overflow:hidden; border:1px solid var(--gray-200); cursor:pointer;">
                                <img src="${img}" style="width:100%; height:100%; object-fit:cover; display:block;">
                            </div>
                        `).join('')}
                    </div>
                `;

                if (galTotalPages > 1) {
                    html += `
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding-top:12px; border-top:1px solid var(--gray-200);">
                            <button type="button" class="btn btn-xs btn-outline" onclick="changePublicGalleryPage(-1)" ${galPage <= 1 ? 'disabled' : ''}>
                                <i class="fa-solid fa-chevron-left"></i> Previous
                            </button>
                            <span style="font-size:0.75rem; font-weight:700; color:var(--primary);">Showing ${galStart + 1} - ${Math.min(galStart + galPerPage, galList.length)} of ${galList.length}</span>
                            <button type="button" class="btn btn-xs btn-outline" onclick="changePublicGalleryPage(1)" ${galPage >= galTotalPages ? 'disabled' : ''}>
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    `;
                }
            }
            break;

        case 'reviews':
            html = `
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <button class="btn btn-outline btn-sm btn-full" style="padding:12px;" onclick="openReviewModal(${v.id})"><i class="fa-solid fa-pen-to-square"></i> Write a Review</button>
                    ${v.reviews.length > 0 ? v.reviews.map(r => {
                        const firstLetter = r.user_name ? r.user_name.charAt(0).toUpperCase() : 'U';
                        const reviewRating = parseFloat(r.rating || '5.0').toFixed(1);
                        return `
                            <div class="card review-card-premium" style="padding:16px; border:1px solid var(--gray-100); border-radius:var(--radius-lg); background:var(--white); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; gap:10px;">
                                <div class="review-header" style="display:flex; align-items:center; gap:10px; width:100%;">
                                    <div class="review-avatar" style="width:36px; height:36px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.85rem;">${firstLetter}</div>
                                    <div style="display:flex; flex-direction:column; gap:2px;">
                                        <div class="review-name" style="font-size:0.85rem; font-weight:800; color:var(--gray-900);">${r.user_name}</div>
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <div class="review-stars">${starsHTML(r.rating, '0.55rem')}</div>
                                            <span style="font-size:0.68rem; color:var(--gray-400);">${r.date}</span>
                                        </div>
                                    </div>
                                    <span class="rating-badge" style="background:rgba(242, 167, 53, 0.15); color:var(--accent); font-size:0.75rem; font-weight:800; padding:4px 8px; border-radius:4px; margin-left:auto;">${reviewRating}</span>
                                </div>
                                <p style="font-size:0.8rem; color:var(--gray-600); line-height:1.5; margin:0; padding-left:2px;">${r.comment}</p>
                            </div>
                        `;
                    }).join('') : '<p class="text-sm text-muted text-center" style="padding:30px 0;">No reviews yet. Be the first to review!</p>'}
                </div>
            `;
            break;
    }
    container.innerHTML = html;
}

function syncChatLayoutState() {
    const isChatConversation = (state.currentScreen === 'chat' && state.activeChatVendorId);
    const body = document.body;
    const appHeader = document.getElementById('app-header');
    const bottomNav = document.getElementById('bottom-nav');
    const appContainer = document.getElementById('ohati-app');
    
    const isMobile = window.innerWidth < 768;

    if (isChatConversation && isMobile) {
        body.classList.add('chat-active');
        if (appHeader) appHeader.style.display = 'none';
        if (bottomNav) bottomNav.style.display = 'none';
    } else {
        body.classList.remove('chat-active');
        if (appHeader) appHeader.style.display = '';
        if (bottomNav) bottomNav.style.display = isMobile ? '' : 'none';
        if (appContainer) appContainer.style.height = '';
    }
}

// ── 4. CHAT SCREEN ──────────────────────────────────────────────────────
function initChatScreen() {
    const screen = document.getElementById('screen-chat');
    if (!screen) return;

    syncChatLayoutState();

    if (state.chatInterval) {
        clearInterval(state.chatInterval);
        state.chatInterval = null;
    }

    const isDesktop = window.innerWidth >= 768;

    if (isDesktop) {
        screen.innerHTML = `
            <div class="chat-desktop-layout">
                <div class="chat-desktop-sidebar">
                    <div class="p-section chat-desktop-sidebar-header" style="border-bottom:1px solid var(--gray-100); padding: 16px 20px;">
                        <h3 style="margin:0;">Messages</h3>
                    </div>
                    <div id="chat-inbox-list" class="scrollable-y" style="flex:1;">
                        <div class="full-spinner-wrap"><div class="spinner"></div></div>
                    </div>
                </div>
                <div class="chat-desktop-content" id="chat-desktop-content-panel">
                    <div class="chat-welcome-panel">
                        <i class="fa-solid fa-comments" style="font-size:4rem; color:var(--gray-200); margin-bottom:16px;"></i>
                        <h3>Your Messages</h3>
                        <p class="text-muted">Select a conversation from the sidebar to start chatting.</p>
                    </div>
            </div>
        `;


        API.getChatInbox().then(inbox => {
            renderChatInbox(inbox);
            
            if (state.activeChatVendorId) {
                loadDesktopChatPartner(state.activeChatVendorId);
            }
        }).catch(err => {
            console.error("Desktop inbox load error:", err);
            const inboxList = document.getElementById('chat-inbox-list');
            if (inboxList) {
                inboxList.innerHTML = `
                    <div style="padding:30px 16px; text-align:center; color:var(--gray-500);">
                        <p style="font-size:0.8rem; margin-bottom:10px;">${err.message || 'Could not load messages.'}</p>
                        <button class="btn btn-primary btn-xs" onclick="initChatScreen()">Retry</button>
                    </div>
                `;
            }
        });

        state.pollingInboxInProgress = false;
        state.pollingHistoryInProgress = false;
        state.chatInterval = setInterval(() => {
            if (state.currentScreen === 'chat' && window.innerWidth >= 768) {
                if (!state.pollingInboxInProgress) {
                    state.pollingInboxInProgress = true;
                    API.getChatInbox().then(inb => {
                        state.pollingInboxInProgress = false;
                        renderChatInbox(inb);
                    }).catch(() => {
                        state.pollingInboxInProgress = false;
                    });
                }
                if (state.activeChatVendorId && !state.pollingHistoryInProgress) {
                    state.pollingHistoryInProgress = true;
                    API.getChatHistory(state.activeChatVendorId).then(hist => {
                        state.pollingHistoryInProgress = false;
                        updateChatMessages(hist);
                    }).catch(() => {
                        state.pollingHistoryInProgress = false;
                    });

                    // Live Partner Online Status Refresh
                    const role = state.user?.active_role || state.user?.role || 'customer';
                    const params = (role === 'vendor') ? { user_id: state.activeChatVendorId } : { vendor_id: state.activeChatVendorId };
                    API.getUserStatus(params).then(st => {
                        const statusEl = document.getElementById('chat-partner-status');
                        if (statusEl && st) {
                            statusEl.innerHTML = st.is_online ? '<span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10B981; margin-right:4px;"></span>Online' : (st.online_status || 'Offline');
                        }
                    }).catch(() => {});
                }
            } else {
                clearInterval(state.chatInterval);
                state.chatInterval = null;
            }
        }, 2000);

    } else {
        if (state.activeChatVendorId) {
            screen.innerHTML = `<div class="full-spinner-wrap"><div class="spinner"></div></div>`;
            const role = state.user?.active_role || state.user?.role || 'customer';
            API.getVendorDetails(state.activeChatVendorId, role === 'vendor').then(v => {
                state.activeChatPartner = v;
                renderChatShell(v);
                API.getChatHistory(state.activeChatVendorId).then(history => {
                    updateChatMessages(history);
                    
                    state.pollingHistoryInProgress = false;
                    state.chatInterval = setInterval(() => {
                        if (state.currentScreen === 'chat' && state.activeChatVendorId && window.innerWidth < 768) {
                            if (!state.pollingHistoryInProgress) {
                                state.pollingHistoryInProgress = true;
                                API.getChatHistory(state.activeChatVendorId).then(hist => {
                                    state.pollingHistoryInProgress = false;
                                    updateChatMessages(hist);
                                }).catch(err => {
                                    state.pollingHistoryInProgress = false;
                                    console.error("Error polling chat:", err);
                                });

                                const role = state.user?.active_role || state.user?.role || 'customer';
                                const params = (role === 'vendor') ? { user_id: state.activeChatVendorId } : { vendor_id: state.activeChatVendorId };
                                API.getUserStatus(params).then(st => {
                                    const statusEl = document.getElementById('chat-partner-status');
                                    if (statusEl && st) {
                                        statusEl.innerHTML = st.is_online ? '<span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10B981; margin-right:4px;"></span>Online' : (st.online_status || 'Offline');
                                    }
                                }).catch(() => {});
                            }
                        } else {
                            clearInterval(state.chatInterval);
                            state.chatInterval = null;
                        }
                    }, 2000);
                }).catch(err => {
                    console.error("Chat history load error:", err);
                    updateChatMessages([]);
                });
            }).catch(err => {
                console.error("Chat partner load error:", err);
                screen.innerHTML = `
                    <div style="padding:50px 20px; text-align:center; color:var(--gray-500);">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size:2.5rem; color:#EF4444; margin-bottom:12px; display:block;"></i>
                        <h4 style="margin:0 0 8px 0; color:var(--gray-900);">Could Not Open Chat</h4>
                        <p style="font-size:0.8rem; margin:0 0 16px 0; color:var(--gray-400);">${err.message || 'Unable to connect to chat partner.'}</p>
                        <button class="btn btn-outline btn-sm" onclick="state.activeChatVendorId = null; initChatScreen();">Return to Inbox</button>
                    </div>
                `;
            });
        } else {
            screen.innerHTML = `
                <div class="p-section" style="border-bottom:1px solid var(--gray-200); padding-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h3 style="margin:0;">Messages</h3>
                        <span class="badge badge-primary" id="chat-count-badge">0 Conversations</span>
                    </div>
                    <div style="position:relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--gray-400); font-size:0.85rem;"></i>
                        <input type="text" class="form-input" id="chat-search-input" placeholder="Search conversations by name or message..." onkeyup="filterChatInbox()" style="padding-left:36px; height:38px; font-size:0.82rem; border-radius:20px; background:var(--gray-100);">
                    </div>
                </div>
                <div id="chat-inbox-list" style="padding-top:8px;">
                    <div class="full-spinner-wrap"><div class="spinner"></div></div>
                </div>
            `;
            API.getChatInbox().then(inbox => {
                window._currentChatInbox = inbox;
                renderChatInbox(inbox);
                
                state.chatInterval = setInterval(() => {
                    if (state.currentScreen === 'chat' && !state.activeChatVendorId && window.innerWidth < 768) {
                        API.getChatInbox().then(inb => {
                            window._currentChatInbox = inb;
                            filterChatInbox();
                        }).catch(err => console.error("Error polling inbox:", err));
                    } else {
                        clearInterval(state.chatInterval);
                        state.chatInterval = null;
                    }
                }, 3000);
            }).catch(err => {
                console.error("Inbox load error:", err);
                const inboxList = document.getElementById('chat-inbox-list');
                if (inboxList) {
                    inboxList.innerHTML = `
                        <div style="padding:40px 20px; text-align:center; color:var(--gray-500);">
                            <i class="fa-solid fa-comments" style="font-size:2.5rem; color:var(--gray-300); margin-bottom:12px; display:block;"></i>
                            <h4 style="margin:0 0 6px 0;">No Messages Available</h4>
                            <p style="font-size:0.8rem; margin:0 0 16px 0; color:var(--gray-400);">${err.message || 'Could not retrieve inbox messages.'}</p>
                            <button class="btn btn-primary btn-xs" onclick="initChatScreen()">Tap to Retry</button>
                        </div>
                    `;
                }
            });
        }
    }
}

function filterChatInbox() {
    const query = (document.getElementById('chat-search-input')?.value || '').toLowerCase().trim();
    const inbox = Array.isArray(window._currentChatInbox) ? [...window._currentChatInbox] : [];
    if (!query) {
        renderChatInbox(inbox);
        return;
    }
    const filtered = inbox.filter(item => {
        const name = (item.name || item.vendor_name || '').toLowerCase();
        const cat = (item.category || item.vendor_category || '').toLowerCase();
        const msg = (item.last_message || item.message || '').toLowerCase();
        return name.includes(query) || cat.includes(query) || msg.includes(query);
    });
    renderChatInbox(filtered);
}

function renderChatInbox(inbox) {
    const container = document.getElementById('chat-inbox-list');
    const cntBadge = document.getElementById('chat-count-badge');
    if (cntBadge) cntBadge.textContent = `${inbox ? inbox.length : 0} Conversations`;
    if (!container) return;

    if (!inbox || inbox.length === 0) {
        container.innerHTML = `
            <div class="text-center" style="padding:50px 20px;">
                <i class="fa-solid fa-comments" style="font-size:3rem; color:var(--gray-300); margin-bottom:12px;"></i>
                <h4 style="font-weight:700; color:var(--gray-800);">No Conversations Found</h4>
                <p class="text-sm text-muted">No messages match your current search query.</p>
                <button class="btn btn-primary btn-sm mt-16" onclick="navigateTo('search')">Browse Vendors</button>
            </div>
        `;
        return;
    }

    const role = (state.user && (state.user.active_role || state.user.role)) || 'customer';
    const newHTML = inbox.map(item => {
        const targetId = (role === 'vendor') ? item.customer_id : item.id;
        const targetLogo = (role === 'vendor') ? item.avatar : item.logo;
        const targetName = item.name;
        const targetSubtitle = (role === 'vendor') ? 'Client' : item.category;

        let nameWithBadge = targetName;

        const isOnline = item.is_online || item.availability === 'Online';
        return `
            <div class="chat-inbox-item" onclick="openChatWithVendor(${targetId})">
                <div class="chat-inbox-avatar">
                    <img src="${targetLogo || (window.DEFAULT_USER_AVATAR || 'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><circle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%23081729\'/><circle cx=\'50\' cy=\'38\' r=\'18\' fill=\'%23FFFFFF\'/><path d=\'M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z\' fill=\'%23FFFFFF\'/></svg>')}" alt="" class="header-logo-img">
                    ${isOnline ? `<div class="chat-inbox-online" title="Online now"></div>` : ''}
                </div>
                <div class="chat-inbox-info">
                    <div class="chat-inbox-name">${nameWithBadge}</div>
                    <div class="chat-inbox-preview">${targetSubtitle}</div>
                </div>
            </div>
        `;
    }).join('');

    if (container.innerHTML !== newHTML) {
        container.innerHTML = newHTML;
    }
}

function loadDesktopChatPartner(vid) {
    state.activeChatVendorId = vid;
    const contentPanel = document.getElementById('chat-desktop-content-panel');
    if (!contentPanel) {
        initChatScreen();
        return;
    }

    contentPanel.innerHTML = `<div class="full-spinner-wrap"><div class="spinner"></div></div>`;

    const role = state.user?.active_role || state.user?.role || 'customer';
    API.getVendorDetails(vid, role === 'vendor').then(v => {
        if (!v || (!v.id && !v.name)) {
            contentPanel.innerHTML = `
                <div style="padding:60px 20px; text-align:center; color:var(--gray-500);">
                    <i class="fa-solid fa-user-xmark" style="font-size:2.5rem; color:var(--gray-400); margin-bottom:12px; display:block;"></i>
                    <h4 style="margin:0 0 8px 0; color:var(--gray-900);">User Not Found</h4>
                    <p style="font-size:0.8rem; margin:0 0 16px 0; color:var(--gray-400);">This conversation partner is unavailable or deleted.</p>
                    <button class="btn btn-primary btn-xs" onclick="initChatScreen()">Return to Inbox</button>
                </div>
            `;
            return;
        }

        state.activeChatPartner = v;
        let nameWithBadge = v.name || 'Vendor';
        const headerClickAction = (role === 'vendor') ? `viewCustomerProfileModal(${v.id})` : `viewVendorDetails(${v.id})`;
        const isOnlineDesk = v.is_online || v.availability === 'Online';
        const statusTextDesk = isOnlineDesk ? '<span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10B981; margin-right:4px;"></span>Online' : (v.online_status || v.availability || 'Offline');
        
        contentPanel.innerHTML = `
            <div class="chat-screen" data-vendor-id="${v.id}">
                <div class="chat-header">
                    <img class="chat-vendor-avatar" src="${v.logo || 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=400'}" alt="" style="cursor:pointer;" onclick="${headerClickAction}">
                    <div class="chat-vendor-info" style="cursor:pointer;" onclick="${headerClickAction}">
                        <div class="chat-vendor-name">${nameWithBadge}</div>
                        <div class="chat-vendor-status" id="chat-partner-status">${statusTextDesk}</div>
                    </div>
                    <div style="display:flex; gap:10px; margin-left:auto; align-items:center; padding-right:4px; position:relative;">
                        <button class="chat-call-action-btn" onclick="OhatiCalling.startCall(${v.user_id || v.id}, 'voice', '${(v.name||'').replace(/'/g, "\\'")}', '${v.phone||''}')" title="Voice Call" style="background:none; border:none; color:var(--primary); font-size:1.2rem; cursor:pointer; display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:50%; transition:all 0.2s ease;"><i class="fa-solid fa-phone"></i></button>
                        <div class="chat-header-actions-dropdown" style="position:relative;">
                            <button onclick="toggleChatActionsMenu(event)" title="More Options" style="background:none; border:none; color:var(--gray-600); font-size:1.2rem; cursor:pointer; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            <div id="chat-actions-menu" class="chat-actions-menu-dropdown" style="display:none; position:absolute; right:0; top:40px; background:#fff; border:1px solid var(--gray-200); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.12); width:170px; z-index:100; overflow:hidden; padding:6px 0;">
                                <div onclick="showReportUserModal(${v.user_id || v.id}, '${(v.name||'').replace(/'/g, "\\'")}')" style="padding:10px 14px; font-size:0.8rem; font-weight:600; color:var(--gray-700); cursor:pointer; display:flex; align-items:center; gap:8px;" onmouseover="this.style.background='var(--gray-100)'" onmouseout="this.style.background='transparent'">
                                    <i class="fa-solid fa-flag" style="color:#F59E0B; font-size:0.85rem;"></i> Report User
                                </div>
                                <div onclick="showBlockUserModal(${v.user_id || v.id}, '${(v.name||'').replace(/'/g, "\\'")}')" style="padding:10px 14px; font-size:0.8rem; font-weight:600; color:#EF4444; cursor:pointer; display:flex; align-items:center; gap:8px;" onmouseover="this.style.background='rgba(239,68,68,0.08)'" onmouseout="this.style.background='transparent'">
                                    <i class="fa-solid fa-user-slash" style="font-size:0.85rem;"></i> Block User
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="chat-messages scrollable-y" id="chat-messages-container"></div>
                <div class="chat-input-bar" style="gap: 8px;">
                    <button class="chat-attach-btn" onclick="triggerChatAttachment()" title="Upload File" style="width:36px; height:36px; border-radius:50%; background:var(--gray-100); border:none; color:var(--gray-600); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.85rem;"><i class="fa-solid fa-paperclip"></i></button>
                    <input class="chat-input" placeholder="Type a message..." id="chat-input-field" onkeyup="if(event.key==='Enter') sendChatMessage()">
                    <button class="chat-send-btn" onclick="sendChatMessage()"><i class="fa-solid fa-paper-plane"></i></button>
                    <input type="file" id="chat-file-input" style="display:none;" onchange="handleChatFileSelected(this)" accept="image/*,video/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                </div>
            </div>
        `;

        API.getChatHistory(vid).then(history => {
            updateChatMessages(history);
        }).catch(err => {
            console.error("Chat history load error:", err);
            updateChatMessages([]);
        });
    }).catch(err => {
        console.error("Desktop chat partner load error:", err);
        contentPanel.innerHTML = `
            <div style="padding:60px 20px; text-align:center; color:var(--gray-500);">
                <i class="fa-solid fa-triangle-exclamation" style="font-size:2.5rem; color:#EF4444; margin-bottom:12px; display:block;"></i>
                <h4 style="margin:0 0 8px 0; color:var(--gray-900);">Could Not Open Chat</h4>
            </div>
        `;
    });
}

window.toggleChatActionsMenu = function(e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('chat-actions-menu');
    if (!menu) return;
    const isVisible = menu.style.display === 'block';
    menu.style.display = isVisible ? 'none' : 'block';
};

document.addEventListener('click', function() {
    const menu = document.getElementById('chat-actions-menu');
    if (menu) menu.style.display = 'none';
});

function openChatWithVendor(vid) {
    if (!state.user) {
        if (typeof openAuthModal === 'function') openAuthModal('login');
        return;
    }
    const numVid = parseInt(vid);
    state.activeChatVendorId = numVid;
    navigateTo('chat', { vendor_id: numVid }, { force: true });
}


window.updateHeaderNavRoleVisibility = function() {
    const postJobBtns = document.querySelectorAll('#desktop-nav-post-job, .desktop-nav-item[data-screen="user-jobs"]');
    const findJobsBtns = document.querySelectorAll('#desktop-nav-find-jobs, .desktop-nav-item[data-screen="vendor-jobs"]');

    const role = state.user?.active_role || state.user?.role || 'customer';

    if (role === 'vendor') {
        // Vendor Account Mode: Hide "Post Job", Show "Find Jobs"
        postJobBtns.forEach(btn => btn.style.setProperty('display', 'none', 'important'));
        findJobsBtns.forEach(btn => btn.style.setProperty('display', 'inline-flex', 'important'));
    } else {
        // Customer / Default Account Mode: Show "Post Job", Hide "Find Jobs"
        postJobBtns.forEach(btn => btn.style.setProperty('display', 'inline-flex', 'important'));
        findJobsBtns.forEach(btn => btn.style.setProperty('display', 'none', 'important'));
    }
};

window.startVendorChat = function(vid) {
    console.log("Opening chat with vendor/user ID:", vid);
    if (!state.user) {
        showPushNotification('Login Required', 'Please log in to chat with vendors.');
        if (typeof openAuthModal === 'function') openAuthModal('login');
        return;
    }
    const numVid = vid ? parseInt(vid) : null;
    if (numVid) {
        window.location.href = `chat.php?vendor_id=${numVid}`;
    } else {
        window.location.href = 'chat.php';
    }
};
window.navigateToChatDirect = window.startVendorChat;


function renderChatShell(v) {
    const screen = document.getElementById('screen-chat');
    if (!screen) return;

    const existingScreen = screen.querySelector('.chat-screen');
    if (existingScreen && existingScreen.getAttribute('data-vendor-id') == v.id) {
        return;
    }

    const role = state.user?.active_role || state.user?.role || 'customer';
    let nameWithBadge = v.name || 'Vendor';
    const headerClickAction = (role === 'vendor') ? `viewCustomerProfileModal(${v.id})` : `viewVendorDetails(${v.id})`;
    const isOnline = v.is_online || v.availability === 'Online';
    const statusText = isOnline ? '<span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10B981; margin-right:4px;"></span>Online' : (v.online_status || v.availability || 'Offline');

    screen.innerHTML = `
        <div class="chat-screen" data-vendor-id="${v.id}">
            <div class="chat-header">
                <button class="chat-back-btn" onclick="closeActiveChat()"><i class="fa-solid fa-chevron-left"></i></button>
                <img class="chat-vendor-avatar" src="${v.logo || 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=400'}" alt="" style="cursor:pointer;" onclick="${headerClickAction}">
                <div class="chat-vendor-info" style="cursor:pointer;" onclick="${headerClickAction}">
                    <div class="chat-vendor-name">${nameWithBadge}</div>
                    <div class="chat-vendor-status" id="chat-partner-status">${statusText}</div>
                </div>
                <div style="display:flex; gap:10px; margin-left:auto; align-items:center; padding-right:4px; position:relative;">
                    <button class="chat-call-action-btn" onclick="OhatiCalling.startCall(${v.user_id || v.id}, 'voice', '${(v.name||'').replace(/'/g, "\\'")}', '${v.phone || v.whatsapp || ''}')" title="Voice Call" style="background:none; border:none; color:var(--primary); font-size:1.2rem; cursor:pointer; display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:50%; transition:all 0.2s ease;"><i class="fa-solid fa-phone"></i></button>
                    <div class="chat-header-actions-dropdown" style="position:relative;">
                        <button onclick="toggleChatActionsMenu(event)" title="More Options" style="background:none; border:none; color:var(--gray-600); font-size:1.2rem; cursor:pointer; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                        <div id="chat-actions-menu" class="chat-actions-menu-dropdown" style="display:none; position:absolute; right:0; top:40px; background:#fff; border:1px solid var(--gray-200); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.12); width:170px; z-index:100; overflow:hidden; padding:6px 0;">
                            <div onclick="showReportUserModal(${v.user_id || v.id}, '${(v.name||'').replace(/'/g, "\\'")}')" style="padding:10px 14px; font-size:0.8rem; font-weight:600; color:var(--gray-700); cursor:pointer; display:flex; align-items:center; gap:8px;" onmouseover="this.style.background='var(--gray-100)'" onmouseout="this.style.background='transparent'">
                                <i class="fa-solid fa-flag" style="color:#F59E0B; font-size:0.85rem;"></i> Report User
                            </div>
                            <div onclick="showBlockUserModal(${v.user_id || v.id}, '${(v.name||'').replace(/'/g, "\\'")}')" style="padding:10px 14px; font-size:0.8rem; font-weight:600; color:#EF4444; cursor:pointer; display:flex; align-items:center; gap:8px;" onmouseover="this.style.background='rgba(239,68,68,0.08)'" onmouseout="this.style.background='transparent'">
                                <i class="fa-solid fa-user-slash" style="font-size:0.85rem;"></i> Block User
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chat-messages scrollable-y" id="chat-messages-container"></div>

            <div class="chat-input-bar" style="gap: 8px;">
                <button class="chat-attach-btn" onclick="triggerChatAttachment()" title="Upload File" style="width:36px; height:36px; border-radius:50%; background:var(--gray-100); border:none; color:var(--gray-600); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.85rem;"><i class="fa-solid fa-paperclip"></i></button>
                <input class="chat-input" placeholder="Type a message..." id="chat-input-field" onkeyup="if(event.key==='Enter') sendChatMessage()">
                <button class="chat-send-btn" onclick="sendChatMessage()"><i class="fa-solid fa-paper-plane"></i></button>
                <input type="file" id="chat-file-input" style="display:none;" onchange="handleChatFileSelected(this)" accept="image/*,video/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
            </div>
        </div>
    `;
}

function updateChatMessages(history) {
    const container = document.getElementById('chat-messages-container');
    if (!container) return;

    const role = state.user?.active_role || state.user?.role || 'customer';

    const newHTML = history.map(m => {
        const isOutgoing = (role === 'vendor' && m.sender === 'vendor') || (role !== 'vendor' && m.sender === 'user');
        
        const timeStr = m.created_at ? formatChatDateTime(m.created_at) : '';
        let statusHtml = '';
        if (isOutgoing) {
            const isRead = parseInt(m.is_read) === 1;
            statusHtml = `
                <div class="msg-meta" style="display:flex; align-items:center; justify-content:flex-end; gap:6px; margin-top:2px;">
                    <span class="live-relative-time" data-timestamp="${m.created_at || ''}" style="font-size:0.6rem; opacity:0.75;">${timeStr}</span>
                    <span class="msg-status ${isRead ? 'seen' : 'sent'}" style="font-size:0.65rem; display:inline-flex; align-items:center;">
                        <i class="fa-solid fa-check-double"></i>
                    </span>
                </div>
            `;
        } else {
            statusHtml = `
                <div class="msg-meta" style="display:flex; align-items:center; justify-content:flex-start; gap:4px; margin-top:2px;">
                    <span class="live-relative-time" data-timestamp="${m.created_at || ''}" style="font-size:0.6rem; opacity:0.6;">${timeStr}</span>
                </div>
            `;
        }

        let bodyHtml = '';
        if (m.type === 'image') {
            bodyHtml = `<img src="${m.message}" style="max-width:240px; max-height:200px; object-fit:cover; border-radius:12px; display:block; margin-bottom:4px; box-shadow: var(--shadow-sm); cursor:pointer;" onclick="previewChatImage('${m.message}')">`;
        } else if (m.type === 'video') {
            bodyHtml = `<video src="${m.message}" controls style="max-width:100%; border-radius:12px; display:block; margin-bottom:4px; box-shadow: var(--shadow-sm);"></video>`;
        } else if (m.type === 'voice') {
            bodyHtml = `
                <div class="custom-voice-player" data-src="${m.message}" style="display:flex; align-items:center; gap:10px; padding:6px 12px; background:rgba(0,0,0,0.06); border-radius:18px; min-width:200px; max-width:280px; margin-bottom:4px;">
                    <button class="voice-play-btn" onclick="handleVoicePlayerClick(this)" style="background:none; border:none; color:var(--accent); font-size:1.15rem; cursor:pointer; width:28px; height:28px; display:flex; align-items:center; justify-content:center; padding:0; outline:none;">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <input type="range" class="voice-progress" min="0" max="100" value="0" oninput="handleVoicePlayerSeek(this)" style="flex:1; accent-color:var(--accent); cursor:pointer; height:4px; border:none; background:transparent; outline:none; margin:0;">
                    <span class="voice-duration" style="font-size:0.75rem; color:var(--gray-600); font-weight:600; min-width:35px; text-align:right;">0:00</span>
                </div>
            `;
        } else if (m.type === 'pdf' || m.type === 'location') {
            const fileName = m.message.split('/').pop();
            bodyHtml = `
                <a href="${m.message}" target="_blank" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit; padding:6px 10px; background:rgba(0,0,0,0.05); border-radius:8px; margin-bottom:4px; font-weight:500;">
                    <i class="fa-solid fa-file-lines" style="font-size:1.3rem; color:var(--accent);"></i>
                    <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:160px; font-size:0.75rem;">${fileName}</div>
                </a>
            `;
        } else {
            bodyHtml = `<div class="msg-text">${m.message}</div>`;
        }

        const isUserSender = m.sender === 'user';
        const bubbleClass = (role === 'vendor') ? (isUserSender ? 'msg-vendor' : 'msg-user') : (isUserSender ? 'msg-user' : 'msg-vendor');

        if (isOutgoing) {
            return `
                <div class="msg-row outgoing" style="display:flex; align-items:flex-end; justify-content:flex-end; gap:8px; width:100%; margin-bottom:4px;">
                    <div class="msg-bubble ${bubbleClass}" style="margin:0;">
                        ${bodyHtml}
                        ${statusHtml}
                    </div>
                </div>
            `;
        } else {
            let badgeBadgeHtml = '';
            if (role !== 'vendor' && state.activeChatPartner) {
                const isVerified = parseInt(state.activeChatPartner.verified) === 1;
                const badge = state.activeChatPartner.verification_badge;
                if (badge === 'gold') {
                    badgeBadgeHtml = `
                        <span style="position:absolute; bottom:-2px; right:-2px; background:#fff; border-radius:50%; width:12px; height:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 3px rgba(0,0,0,0.15);" title="Gold Verified Vendor">
                            <i class="fa-solid fa-circle-check" style="color:#D4AF37; font-size:9px;"></i>
                        </span>
                    `;
                } else if (badge === 'blue' || isVerified) {
                    badgeBadgeHtml = `
                        <span style="position:absolute; bottom:-2px; right:-2px; background:#fff; border-radius:50%; width:12px; height:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 3px rgba(0,0,0,0.15);" title="ID Verified Vendor">
                            <i class="fa-solid fa-circle-check" style="color:#1DA1F2; font-size:9px;"></i>
                        </span>
                    `;
                }
            }
            const partnerAvatar = state.activeChatPartner?.logo || window.DEFAULT_USER_AVATAR;
            const avatarClickAction = (role === 'vendor') ? `viewCustomerProfileModal(${state.activeChatPartner?.id})` : `viewVendorDetails(${state.activeChatPartner?.id})`;
            return `
                <div class="msg-row incoming" style="display:flex; align-items:flex-end; justify-content:flex-start; gap:8px; width:100%; margin-bottom:4px;">
                    <div style="position:relative; width:28px; height:28px; flex-shrink:0;">
                        <img src="${partnerAvatar}" style="width:28px; height:28px; border-radius:50%; object-fit:cover; cursor:pointer;" onclick="${avatarClickAction}" title="${state.activeChatPartner?.name || 'Profile'}">
                        ${badgeBadgeHtml}
                    </div>
                    <div class="msg-bubble ${bubbleClass}" style="margin:0;">
                        ${bodyHtml}
                        ${statusHtml}
                    </div>
                </div>
            `;
        }
    }).join('');

    if (container.innerHTML !== newHTML) {
        container.innerHTML = newHTML;
        scrollToBottom('chat-messages-container');
    }
}

function closeActiveChat() {
    if (state.chatInterval) {
        clearInterval(state.chatInterval);
        state.chatInterval = null;
    }
    state.activeChatVendorId = null;
    initChatScreen();
}

function sendChatMessage() {
    const input = document.getElementById('chat-input-field');
    const msg = input?.value.trim() || '';
    if (!msg || !state.activeChatVendorId) return;

    input.value = '';

    API.sendMessage(state.activeChatVendorId, msg).then(() => {
        API.getChatHistory(state.activeChatVendorId).then(history => {
            updateChatMessages(history);
        });
    });
}

function triggerChatAttachment() {
    if (typeof window.triggerChatAttachment === 'function' && window.triggerChatAttachment !== triggerChatAttachment) {
        window.triggerChatAttachment();
    } else {
        document.getElementById('chat-file-input')?.click();
    }
}

function handleChatFileSelected(input) {
    if (typeof window.handleChatFileSelected === 'function' && window.handleChatFileSelected !== handleChatFileSelected) {
        window.handleChatFileSelected(input);
    }
}

let mediaRecorder = null;
let audioChunks = [];
let voiceRecordingTimer = null;
let voiceRecordingSeconds = 0;
let recordedAudioBlob = null;
let recordedAudioUrl = null;
let recordedAudioDuration = 0;
let previewAudioInstance = null;

function getNormalChatInputBarHTML() {
    return `
        <button class="chat-attach-btn" onclick="triggerChatAttachment()" title="Upload File" style="width:36px; height:36px; border-radius:50%; background:var(--gray-100); border:none; color:var(--gray-600); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.85rem;"><i class="fa-solid fa-paperclip"></i></button>
        <input class="chat-input" placeholder="Type a message..." id="chat-input-field" onkeyup="if(event.key==='Enter') sendChatMessage()">
        <button class="chat-send-btn" onclick="sendChatMessage()"><i class="fa-solid fa-paper-plane"></i></button>
        <input type="file" id="chat-file-input" style="display:none;" onchange="handleChatFileSelected(this)" accept="image/*,video/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
    `;
}

function formatTimeLabel(secs) {
    const m = Math.floor(secs / 60);
    const s = Math.floor(secs % 60);
    return `${m}:${s < 10 ? '0' : ''}${s}`;
}

function toggleVoiceRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        stopVoiceRecording();
    } else {
        startVoiceRecording();
    }
}

function startVoiceRecording() {
    stopPreviewAudio();
    if (typeof CallAudio !== 'undefined' && typeof CallAudio.stop === 'function') {
        CallAudio.stop();
    }

    getUniversalAudioStream().then(stream => {
        audioChunks = [];
        
        try {
            let options = {};
            if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/webm')) {
                options = { mimeType: 'audio/webm' };
            } else if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/mp4')) {
                options = { mimeType: 'audio/mp4' };
            } else if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/ogg')) {
                options = { mimeType: 'audio/ogg' };
            }
            mediaRecorder = new MediaRecorder(stream, options);
        } catch (e) {
            console.warn("MediaRecorder creation with preferred MIME type failed, using default:", e);
            try {
                mediaRecorder = new MediaRecorder(stream);
            } catch (err) {
                console.error("MediaRecorder creation failed:", err);
                showPushNotification("Microphone Error", "Failed to start voice recorder on this browser.");
                stream.getTracks().forEach(track => track.stop());
                return;
            }
        }
        
        mediaRecorder.addEventListener("dataavailable", event => {
            if (event.data && event.data.size > 0) {
                audioChunks.push(event.data);
            }
        });

        mediaRecorder.addEventListener("stop", () => {
            stream.getTracks().forEach(track => track.stop());
            
            recordedAudioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
            recordedAudioDuration = voiceRecordingSeconds;
            
            if (recordedAudioBlob.size === 0) {
                discardVoicePreview();
                return;
            }
            
            // Render the preview UI in the input bar
            const bars = document.querySelectorAll('.chat-input-bar');
            bars.forEach(bar => {
                bar.innerHTML = `
                    <div style="display:flex; align-items:center; gap:12px; width:100%;">
                        <div style="flex:1; display:flex; align-items:center; gap:8px; background:var(--gray-100); padding:6px 12px; border-radius:24px;">
                            <button id="preview-play-btn" onclick="togglePreviewPlayback()" style="background:none; border:none; color:var(--primary); font-size:1.1rem; cursor:pointer; width:24px; display:flex; align-items:center; justify-content:center; padding:0; outline:none;">
                                <i class="fa-solid fa-play"></i>
                            </button>
                            <input type="range" id="preview-progress" min="0" max="100" value="0" oninput="seekPreviewAudio(this.value)" style="flex:1; accent-color:var(--primary); cursor:pointer; height:4px; border:none; background:transparent; outline:none; margin:0;">
                            <span id="preview-time-lbl" style="font-size:0.75rem; color:var(--gray-500); font-weight:600; min-width:35px; text-align:right;">${formatTimeLabel(recordedAudioDuration)}</span>
                        </div>
                        <button onclick="discardVoicePreview()" class="chat-attach-btn" style="background:rgba(244,63,94,0.1); color:#f43f5e; border:none; display:flex; align-items:center; justify-content:center;" title="Delete & Re-record">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <button onclick="sendVoiceRecording()" class="chat-send-btn" style="display:flex; align-items:center; justify-content:center;" title="Send Voice Note">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                `;
            });
        });

        // Start MediaRecorder
        mediaRecorder.start();
        voiceRecordingSeconds = 0;
        
        // Render Recording UI in the input bar
        const bars = document.querySelectorAll('.chat-input-bar');
        bars.forEach(bar => {
            bar.innerHTML = `
                <div style="display:flex; align-items:center; gap:12px; width:100%;">
                    <div style="display:flex; align-items:center; gap:8px; color:var(--error, #f43f5e); font-weight:600; flex:1;">
                        <span class="recording-dot-pulse" style="width:10px; height:10px; border-radius:50%; background:#f43f5e; display:inline-block; animation: pulseRed 1s infinite alternate;"></span>
                        <span id="recording-timer-lbl">Recording... 0:00 / 1:00</span>
                    </div>
                    <button onclick="cancelVoiceRecording()" class="chat-attach-btn" style="background:rgba(244,63,94,0.1); color:#f43f5e; border:none; display:flex; align-items:center; justify-content:center;" title="Discard Recording">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                    <button onclick="stopVoiceRecording()" class="chat-send-btn" style="background:#f43f5e; border:none; display:flex; align-items:center; justify-content:center;" title="Stop & Preview">
                        <i class="fa-solid fa-stop"></i>
                    </button>
                </div>
            `;
        });
        
        // Start 60s timer
        voiceRecordingTimer = setInterval(() => {
            voiceRecordingSeconds++;
            if (voiceRecordingSeconds >= 60) {
                // Max limit reached
                clearInterval(voiceRecordingTimer);
                showPushNotification("Time Limit Reached", "Voice notes are limited to 1 minute.");
                stopVoiceRecording();
            } else {
                const timerLbl = document.getElementById('recording-timer-lbl');
                if (timerLbl) {
                    timerLbl.textContent = `Recording... ${formatTimeLabel(voiceRecordingSeconds)} / 1:00`;
                }
            }
        }, 1000);

    }).catch(err => {
        console.error("Microphone access error:", err);
        showPushNotification("Microphone Error", err.message || "Could not access microphone.");
        cancelVoiceRecording();
    });
}

function stopVoiceRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
    }
    if (voiceRecordingTimer) {
        clearInterval(voiceRecordingTimer);
        voiceRecordingTimer = null;
    }
}

function cancelVoiceRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        audioChunks = [];
        mediaRecorder.stop();
    }
    if (voiceRecordingTimer) {
        clearInterval(voiceRecordingTimer);
        voiceRecordingTimer = null;
    }
    
    // Restore normal input bar
    const bars = document.querySelectorAll('.chat-input-bar');
    bars.forEach(bar => {
        bar.innerHTML = getNormalChatInputBarHTML();
    });
}

function togglePreviewPlayback() {
    const playBtns = document.querySelectorAll('#preview-play-btn, .preview-play-btn');
    if (playBtns.length === 0) return;
    
    if (previewAudioInstance && !previewAudioInstance.paused) {
        previewAudioInstance.pause();
        playBtns.forEach(b => b.innerHTML = '<i class="fa-solid fa-play"></i>');
    } else {
        if (!previewAudioInstance) {
            if (!recordedAudioUrl && recordedAudioBlob) {
                recordedAudioUrl = URL.createObjectURL(recordedAudioBlob);
            }
            if (!recordedAudioUrl) return;
            previewAudioInstance = new Audio(recordedAudioUrl);
            
            previewAudioInstance.addEventListener('timeupdate', () => {
                const progressInputs = document.querySelectorAll('#preview-progress, .preview-progress');
                const timeLbls = document.querySelectorAll('#preview-time-lbl, .preview-time-lbl');
                const pct = (previewAudioInstance.currentTime / (previewAudioInstance.duration || recordedAudioDuration || 1)) * 100;
                progressInputs.forEach(inp => inp.value = isNaN(pct) ? 0 : pct);
                timeLbls.forEach(lbl => lbl.textContent = formatTimeLabel(previewAudioInstance.currentTime));
            });
            
            previewAudioInstance.addEventListener('ended', () => {
                const playBtnsEnd = document.querySelectorAll('#preview-play-btn, .preview-play-btn');
                playBtnsEnd.forEach(b => b.innerHTML = '<i class="fa-solid fa-play"></i>');
                const progressInputs = document.querySelectorAll('#preview-progress, .preview-progress');
                progressInputs.forEach(inp => inp.value = 0);
                const timeLbls = document.querySelectorAll('#preview-time-lbl, .preview-time-lbl');
                timeLbls.forEach(lbl => lbl.textContent = formatTimeLabel(recordedAudioDuration));
            });
        }
        
        previewAudioInstance.play().then(() => {
            playBtns.forEach(b => b.innerHTML = '<i class="fa-solid fa-pause"></i>');
        }).catch(err => {
            console.error("Preview play error:", err);
            playBtns.forEach(b => b.innerHTML = '<i class="fa-solid fa-play"></i>');
        });
    }
}

function seekPreviewAudio(pctVal) {
    if (previewAudioInstance && previewAudioInstance.duration) {
        previewAudioInstance.currentTime = (parseFloat(pctVal) / 100) * previewAudioInstance.duration;
    }
}

function discardVoicePreview() {
    stopPreviewAudio();
    recordedAudioBlob = null;
    if (recordedAudioUrl) {
        URL.revokeObjectURL(recordedAudioUrl);
        recordedAudioUrl = null;
    }
    recordedAudioDuration = 0;
    
    const bars = document.querySelectorAll('.chat-input-bar');
    bars.forEach(bar => {
        bar.innerHTML = getNormalChatInputBarHTML();
    });
}

function stopPreviewAudio() {
    if (previewAudioInstance) {
        previewAudioInstance.pause();
        previewAudioInstance = null;
    }
}

function sendVoiceRecording() {
    if (!recordedAudioBlob) return;
    
    stopPreviewAudio();
    
    const mimeType = recordedAudioBlob.type || 'audio/webm';
    let ext = 'webm';
    if (mimeType.includes('mp4') || mimeType.includes('aac')) {
        ext = 'm4a';
    } else if (mimeType.includes('ogg')) {
        ext = 'ogg';
    } else if (mimeType.includes('wav')) {
        ext = 'wav';
    }
    
    const formData = new FormData();
    formData.append('file', recordedAudioBlob, `voicenote.${ext}`);
    const token = localStorage.getItem('ohati_auth_token');
    if (token) formData.append('auth_token', token);
    
    const bars = document.querySelectorAll('.chat-input-bar');
    bars.forEach(bar => {
        bar.innerHTML = `<div style="padding:10px; text-align:center; width:100%; color:var(--gray-500); font-weight:600;"><i class="fa-solid fa-spinner fa-spin"></i> Sending voice note...</div>`;
    });
    
    const headers = (typeof API !== 'undefined' && API.getAuthHeaders) ? API.getAuthHeaders() : (token ? { 'Authorization': `Bearer ${token}` } : {});
    const apiUrl = (window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=upload_chat_file';

    fetch(apiUrl, {
        method: 'POST',
        credentials: 'include',
        headers: headers,
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && state.activeChatVendorId) {
            API.sendMessage(state.activeChatVendorId, res.url, 'voice').then(() => {
                API.getChatHistory(state.activeChatVendorId).then(history => {
                    updateChatMessages(history);
                });
            });
        } else if (res.error) {
            showPushNotification("Upload Error", res.error);
        }
        
        bars.forEach(bar => {
            bar.innerHTML = getNormalChatInputBarHTML();
        });
        
        recordedAudioBlob = null;
        if (recordedAudioUrl) {
            URL.revokeObjectURL(recordedAudioUrl);
            recordedAudioUrl = null;
        }
        recordedAudioDuration = 0;
    })
    .catch(err => {
        console.error("Voice note upload error:", err);
        showPushNotification("Upload Failed", "Could not upload voice note.");
        bars.forEach(bar => {
            bar.innerHTML = getNormalChatInputBarHTML();
        });
    });
}

// ── 5. PLANNING & BOOKINGS SCREEN ───────────────────────────────────────
function renderBookingsScreen(bookings) {
    const container = document.getElementById('bookings-list');
    const cntBadge = document.getElementById('bk-count-badge');
    if (cntBadge) cntBadge.textContent = `${bookings ? bookings.length : 0} Bookings`;
    if (!container) return;

    const isVendor = state.user && (state.user.active_role === 'vendor');

    if (!bookings || bookings.length === 0) {
        container.innerHTML = `
            <div class="text-center" style="padding:50px 20px;">
                <i class="fa-solid fa-calendar-check" style="font-size:3rem; color:var(--gray-300); margin-bottom:12px;"></i>
                <h4 style="font-weight:700; color:var(--gray-800);">${isVendor ? "No Client Bookings Found" : "No Bookings Found"}</h4>
                <p class="text-sm text-muted">${isVendor ? "No client inquiries or bookings match your filter selection." : "You haven't booked any event vendors matching these filters yet."}</p>
                ${isVendor ? `
                    <button class="btn btn-primary btn-sm mt-16" onclick="navigateTo('vendor-ads')">Promote Business</button>
                ` : `
                    <button class="btn btn-primary btn-sm mt-16" onclick="navigateTo('search')">Find Vendors</button>
                `}
            </div>
        `;
        return;
    }

    container.innerHTML = bookings.map(b => {
        const negPrice = parseFloat(b.negotiated_price || b.price || 0);
        const displayName = isVendor ? (b.user_name || 'Client Inquiry') : (b.vendor_name || 'Vendor');
        const displaySub = isVendor ? (b.event_type || 'Event Package') : (b.vendor_category + ' • ' + (b.event_type || 'Event'));
        const logoUrl = isVendor ? (b.user_avatar || 'img/logo black transparent small.png') : (b.vendor_logo || 'img/logo black transparent small.png');
        const createdRelTime = formatRelativeTime(b.created_at);

        return `
            <div class="booking-card" onclick="openBookingDetailsModal(${b.id})" style="border-radius:14px; margin-bottom:12px; transition:transform 0.2s ease; cursor:pointer; padding:14px;">
                <div class="booking-card-header" style="display:flex; align-items:center; gap:10px;">
                    <img class="booking-vendor-logo" src="${logoUrl}" alt="" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    <div style="flex:1; overflow:hidden;">
                        <div class="booking-vendor-name" style="font-weight:700; font-size:0.9rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${displayName}</div>
                        <div class="booking-vendor-cat" style="font-size:0.75rem; color:var(--gray-500);">${displaySub}</div>
                    </div>
                    <span class="booking-status ${b.status === 'Inquiry' ? 'status-pending' : 'status-confirmed'}">${b.status}</span>
                </div>
                <div class="booking-details-row" style="margin-top:10px; padding-top:8px; border-top:1px dashed var(--gray-200); display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;">
                    <div class="booking-detail-item"><i class="fa-solid fa-calendar-day" style="color:var(--primary);"></i> <span>${formatFriendlyDate(b.event_date)}</span></div>
                    <div class="booking-detail-item"><i class="fa-solid fa-tag" style="color:var(--primary);"></i> <strong style="color:var(--primary);">GH₵ ${negPrice.toLocaleString('en-US', {minimumFractionDigits:2})}</strong></div>
                </div>
                <div style="margin-top:6px; font-size:0.68rem; color:var(--gray-500); text-align:right;">
                    <i class="fa-regular fa-clock"></i> Requested ${createdRelTime}
                </div>
            </div>
        `;
    }).join('');
}

function clearBookingsFilters() {
    const queryEl = document.getElementById('bk-search-input');
    const statusEl = document.getElementById('bk-status-filter');
    const eventEl = document.getElementById('bk-event-filter');
    const dateFromEl = document.getElementById('bk-date-from');
    const dateToEl = document.getElementById('bk-date-to');
    const sortEl = document.getElementById('bk-sort-filter');

    if (queryEl) queryEl.value = '';
    if (statusEl) statusEl.value = '';
    if (eventEl) eventEl.value = '';
    if (dateFromEl) dateFromEl.value = '';
    if (dateToEl) dateToEl.value = '';
    if (sortEl) sortEl.value = 'newest';

    filterBookingsList();
}

function filterBookingsList() {
    const query = (document.getElementById('bk-search-input')?.value || '').toLowerCase().trim();
    const statusVal = document.getElementById('bk-status-filter')?.value || '';
    const eventVal = document.getElementById('bk-event-filter')?.value || '';
    const dateFrom = document.getElementById('bk-date-from')?.value || '';
    const dateTo = document.getElementById('bk-date-to')?.value || '';
    const sortVal = document.getElementById('bk-sort-filter')?.value || 'newest';

    let list = Array.isArray(state.bookings) ? [...state.bookings] : [];

    if (query) {
        list = list.filter(b => {
            const vname = (b.vendor_name || '').toLowerCase();
            const uname = (b.user_name || '').toLowerCase();
            const pkg = (b.package_name || '').toLowerCase();
            const ref = ('#oht-b' + b.id).toLowerCase();
            return vname.includes(query) || uname.includes(query) || pkg.includes(query) || ref.includes(query);
        });
    }

    if (statusVal) {
        list = list.filter(b => (b.status || '').toLowerCase() === statusVal.toLowerCase());
    }

    if (eventVal) {
        list = list.filter(b => (b.event_type || '').toLowerCase().includes(eventVal.toLowerCase()));
    }

    if (dateFrom) {
        list = list.filter(b => b.event_date >= dateFrom);
    }
    if (dateTo) {
        list = list.filter(b => b.event_date <= dateTo);
    }

    if (sortVal === 'oldest') {
        list.sort((a, b) => a.id - b.id);
    } else if (sortVal === 'upcoming') {
        list.sort((a, b) => new Date(a.event_date) - new Date(b.event_date));
    } else {
        list.sort((a, b) => b.id - a.id);
    }

    // Build filter preview text summary
    const totalCount = Array.isArray(state.bookings) ? state.bookings.length : 0;
    const activeParts = [];
    if (query) activeParts.push(`Search "${query}"`);
    if (statusVal) activeParts.push(`Status: ${statusVal}`);
    if (eventVal) activeParts.push(`Event: ${eventVal}`);
    if (dateFrom) activeParts.push(`From: ${dateFrom}`);
    if (dateTo) activeParts.push(`To: ${dateTo}`);

    const previewTextEl = document.getElementById('bk-filter-preview-text');
    const clearBtnEl = document.getElementById('bk-clear-filters-btn');

    if (previewTextEl) {
        if (activeParts.length > 0) {
            previewTextEl.innerHTML = `Showing <strong>${list.length}</strong> of <strong>${totalCount}</strong> • Filtered by: <span style="color:var(--primary); font-weight:600;">${activeParts.join(' | ')}</span>`;
            if (clearBtnEl) clearBtnEl.style.display = 'inline-flex';
        } else {
            previewTextEl.innerHTML = `Showing all <strong>${list.length}</strong> bookings`;
            if (clearBtnEl) clearBtnEl.style.display = 'none';
        }
    }

    renderBookingsScreen(list);
}

function initBookingsScreen() {
    const screen = document.getElementById('screen-bookings');
    if (!screen) return;

    const isVendor = state.user && (state.user.active_role === 'vendor');

    screen.innerHTML = `
        <div class="p-section" style="border-bottom:1px solid var(--gray-200); padding-bottom:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h3 style="margin:0;">${isVendor ? 'Vendor Bookings & Inquiries' : 'My Bookings'}</h3>
                <span class="badge badge-primary" id="bk-count-badge">0 Bookings</span>
            </div>

            <!-- Search Bar -->
            <div style="position:relative; margin-bottom:10px;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--gray-400); font-size:0.85rem;"></i>
                <input type="text" class="form-input" id="bk-search-input" placeholder="Search vendor, client, package or Ref #OHT..." onkeyup="filterBookingsList()" style="padding-left:36px; height:38px; font-size:0.82rem; border-radius:20px; background:var(--gray-100);">
            </div>

            <!-- Primary Filters Row -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                <select class="form-select" id="bk-status-filter" onchange="filterBookingsList()" style="height:34px; font-size:0.75rem; border-radius:8px;">
                    <option value="">All Statuses</option>
                    <option value="Inquiry">Inquiry (Pending)</option>
                    <option value="Confirmed">Confirmed / Approved</option>
                    <option value="Deposit Paid">Deposit Paid</option>
                    <option value="Fully Paid">Fully Paid</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>

                <select class="form-select" id="bk-event-filter" onchange="filterBookingsList()" style="height:34px; font-size:0.75rem; border-radius:8px;">
                    <option value="">All Events</option>
                    <option value="Wedding">Wedding</option>
                    <option value="Funeral">Funeral</option>
                    <option value="Birthday">Birthday</option>
                    <option value="Engagement">Engagement</option>
                    <option value="Corporate">Corporate</option>
                    <option value="Anniversary">Anniversary</option>
                    <option value="Baby Shower">Baby Shower</option>
                    <option value="Concert">Concert / Party</option>
                    <option value="Graduation">Graduation</option>
                    <option value="Others">Others</option>
                </select>

                <select class="form-select" id="bk-sort-filter" onchange="filterBookingsList()" style="height:34px; font-size:0.75rem; border-radius:8px;">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="upcoming">Upcoming Date</option>
                </select>
            </div>

            <!-- Date Range Filter Row -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                <div>
                    <label style="font-size:0.68rem; color:var(--gray-500); display:block; margin-bottom:2px;"><i class="fa-solid fa-calendar-day"></i> From Date:</label>
                    <input type="date" class="form-input" id="bk-date-from" onchange="filterBookingsList()" style="height:32px; font-size:0.75rem; padding:4px 8px;">
                </div>
                <div>
                    <label style="font-size:0.68rem; color:var(--gray-500); display:block; margin-bottom:2px;"><i class="fa-solid fa-calendar-check"></i> To Date:</label>
                    <input type="date" class="form-input" id="bk-date-to" onchange="filterBookingsList()" style="height:32px; font-size:0.75rem; padding:4px 8px;">
                </div>
            </div>

            <!-- Active Filter Preview Text Summary Bar -->
            <div id="bk-filter-preview-bar" style="margin-top:10px; padding:6px 12px; background:var(--gray-100); border-radius:8px; font-size:0.72rem; color:var(--gray-700); display:flex; align-items:center; justify-content:space-between;">
                <div id="bk-filter-preview-text">
                    Showing all <strong>${state.bookings ? state.bookings.length : 0}</strong> bookings
                </div>
                <button onclick="clearBookingsFilters()" class="btn btn-text btn-sm" style="font-size:0.7rem; color:var(--error); padding:0; display:none; align-items:center; gap:4px;" id="bk-clear-filters-btn">
                    <i class="fa-solid fa-xmark"></i> Clear Filters
                </button>
            </div>
        </div>

        <div id="bookings-list" class="p-section" style="padding-top:12px;">
            ${(state.bookings && state.bookings.length > 0) ? '' : renderSkeletonListHTML(3)}
        </div>
    `;

    if (state.bookings && state.bookings.length > 0) {
        renderBookingsScreen(state.bookings);
    }

    API.getBookings().then(bookings => {
        state.bookings = bookings;
        renderBookingsScreen(bookings);
        filterBookingsList();
    });
}

function openBookingRequestModal(vid, pkgName = '', price = '') {
    // 1. Prevent vendors from booking themselves
    if (state.user && state.user.vendor_id && Number(state.user.vendor_id) === Number(vid)) {
        showPushNotification('Invalid Action', 'You cannot book your own vendor services.');
        return;
    }

    // 2. If vendor wants to book other vendors, force switch to customer account first
    if (state.user && state.user.active_role === 'vendor') {
        openSwitchToCustomerModal(vid, pkgName, price);
        return;
    }

    API.getVendorDetails(vid).then(v => {
        const pkgs = v.packages_pricing || [];
        let pkgListHtml = '';
        if (pkgs.length > 0) {
            pkgListHtml = `
                <div class="form-group mb-12">
                    <label class="form-label" style="font-weight:700; color:var(--gray-900);">Choose Vendor Service Package</label>
                    <div style="display:flex; flex-direction:column; gap:8px; max-height:180px; overflow-y:auto; padding-right:4px;">
                        ${pkgs.map((p, idx) => `
                            <div class="card pkg-select-card ${pkgName === p.name || idx === 0 ? 'selected-pkg-border' : ''}" 
                                 onclick="selectBookingPackage(this, '${p.name.replace(/'/g, "\\'")}', '${(p.price || p.cost || '').replace(/'/g, "\\'")}', '${(p.details || p.description || '').replace(/'/g, "\\'")}')" 
                                 style="padding:10px 12px; border:2px solid ${pkgName === p.name || (idx === 0 && !pkgName) ? 'var(--primary)' : 'var(--gray-200)'}; border-radius:10px; cursor:pointer; background:var(--card-bg, #fff);">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="font-size:0.85rem; color:var(--gray-900);">${p.name}</strong>
                                    <span style="font-weight:700; color:var(--primary); font-size:0.85rem;">${p.price || p.cost || 'Contact'}</span>
                                </div>
                                ${p.details || p.description ? `<p style="font-size:0.72rem; color:var(--gray-600); margin:4px 0 0 0; line-height:1.3;">${p.details || p.description}</p>` : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        const initialPkg = pkgs.length > 0 ? pkgs[0] : null;
        const selPkgName = pkgName || (initialPkg ? initialPkg.name : 'Custom Service Request');
        const selPrice = price || (initialPkg ? (initialPkg.price || initialPkg.cost || 'Contact') : 'Ask for Price');
        const selDetails = initialPkg ? (initialPkg.details || initialPkg.description || '') : '';

        const pricingFieldsHtml = (pkgs.length > 0) ? `
            <div class="form-group">
                <label class="form-label">Selected Package</label>
                <input type="text" class="form-input" id="bk-pkg-name" value="${selPkgName}" readonly style="font-weight:700; background:var(--gray-100);">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="form-group">
                    <label class="form-label">Standard Price</label>
                    <input type="text" class="form-input" id="bk-price" value="${selPrice}" readonly style="background:var(--gray-100);">
                </div>
                <div class="form-group">
                    <label class="form-label" style="color:var(--primary, #0E8345); font-weight:700;">Budget Offer (Optional - GH₵)</label>
                    <input type="number" class="form-input" id="bk-negotiated-price" placeholder="Propose budget offer" style="border:1.5px solid var(--primary, #0E8345);">
                </div>
            </div>

            <div id="bk-pkg-details-box" style="display:${selDetails ? 'block' : 'none'}; padding:8px 12px; background:rgba(var(--accent-rgb),0.08); border-radius:8px; margin-bottom:12px; font-size:0.73rem; color:var(--gray-700);">
                <strong>Package Includes:</strong> <span id="bk-pkg-details-txt">${selDetails}</span>
            </div>
        ` : `
            <div class="form-group mb-12">
                <label class="form-label" style="font-weight:700;">What are you booking for? (Service / Event Details)</label>
                <input type="text" class="form-input" id="bk-pkg-name" value="${selPkgName}" placeholder="e.g. Event Decor & Catering for 100 Guests" style="font-weight:600;">
            </div>

            <div class="form-group mb-12">
                <label class="form-label" style="color:var(--primary); font-weight:700;">Budget Offer (Optional - GH₵)</label>
                <input type="number" class="form-input" id="bk-negotiated-price" placeholder="Enter target budget (Optional)" style="border:1.5px solid var(--primary);">
                <input type="hidden" id="bk-price" value="0">
                <div style="font-size:0.68rem; color:var(--gray-500); margin-top:2px;">You and the vendor can discuss and negotiate pricing in chat before booking is confirmed.</div>
            </div>
        `;

        const html = `
            <div class="auth-modal-header">
                <h2 class="auth-modal-title">Book ${v.name}</h2>
                <p class="auth-modal-subtitle">${pkgs.length > 0 ? 'Select package & send booking inquiry' : 'Describe your service needs & send inquiry'}</p>
            </div>
            
            ${pkgListHtml}
            ${pricingFieldsHtml}

            <div class="form-group">
                <label class="form-label">Your Name</label>
                <input type="text" class="form-input" id="bk-user-name" value="${state.user ? state.user.name : ''}">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" class="form-input" id="bk-user-phone" value="${state.user ? (state.user.phone || '') : ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" id="bk-user-email" value="${state.user ? (state.user.email || '') : ''}">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="form-group">
                    <label class="form-label">Event Date</label>
                    <input type="date" class="form-input" id="bk-event-date">
                </div>
                <div class="form-group">
                    <label class="form-label">Event Type</label>
                    <select class="form-select" id="bk-event-type" onchange="handleBookingEventTypeChange(this.value)">
                        <option value="Wedding">Wedding</option>
                        <option value="Birthday">Birthday Party</option>
                        <option value="Funeral">Funeral / Burial Service</option>
                        <option value="Engagement">Traditional Engagement / Marriage</option>
                        <option value="Corporate">Corporate Event / Gala</option>
                        <option value="Anniversary">Anniversary</option>
                        <option value="Baby Shower">Baby Shower / Naming Ceremony</option>
                        <option value="Concert">Concert / Party / Festival</option>
                        <option value="Graduation">Graduation / School Event</option>
                        <option value="Others">Others / Custom Event</option>
                    </select>
                    <div id="bk-custom-type-wrap" style="display:none; margin-top:8px;">
                        <input type="text" class="form-input" id="bk-custom-event-type" placeholder="Specify custom event type..." style="font-size:0.8rem; border:1.5px solid var(--primary);">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Special Notes / Price Negotiation Comment</label>
                <textarea class="form-textarea" id="bk-notes" placeholder="Specify guest count, timing, or price negotiation requests..."></textarea>
            </div>
            <div id="bk-otp-section" style="display:none; margin-top:12px; padding:12px; background:rgba(var(--accent-rgb),0.05); border:1px dashed var(--accent); border-radius:8px;">
                <label class="form-label" style="font-weight:700; color:var(--accent);">Enter Verification OTP</label>
                <p style="font-size:0.7rem; color:var(--gray-500); margin-bottom:8px;">Please enter the OTP sent to your contact address to complete booking:</p>
                <input type="text" class="form-input" id="bk-user-otp" placeholder="Enter OTP code" maxlength="6" style="letter-spacing:4px; text-align:center; font-weight:700;">
            </div>
            <button class="btn btn-primary btn-full mt-12" onclick="submitBookingRequest(${vid})">Send Booking Inquiry & Offer</button>
        `;
        openModal(html);
    });
}

function selectBookingPackage(cardEl, name, price, details) {
    document.querySelectorAll('.pkg-select-card').forEach(c => {
        c.style.border = '2px solid var(--gray-200)';
    });
    if (cardEl) cardEl.style.border = '2px solid var(--primary)';

    const pkgNameInput = document.getElementById('bk-pkg-name');
    const priceInput = document.getElementById('bk-price');
    const box = document.getElementById('bk-pkg-details-box');
    const txt = document.getElementById('bk-pkg-details-txt');

    if (pkgNameInput) pkgNameInput.value = name;
    if (priceInput) priceInput.value = price || 'Contact';
    if (box && txt) {
        if (details) {
            box.style.display = 'block';
            txt.textContent = details;
        } else {
            box.style.display = 'none';
        }
    }
}

window.handleBookingEventTypeChange = function(val) {
    const wrap = document.getElementById('bk-custom-type-wrap');
    if (wrap) {
        wrap.style.display = (val === 'Others') ? 'block' : 'none';
        if (val === 'Others') {
            const input = document.getElementById('bk-custom-event-type');
            if (input) input.focus();
        }
    }
};

function submitBookingRequest(vid) {
    const name = document.getElementById('bk-user-name').value.trim();
    const phone = document.getElementById('bk-user-phone').value.trim();
    const email = document.getElementById('bk-user-email').value.trim();
    const date = document.getElementById('bk-event-date').value;
    let type = document.getElementById('bk-event-type').value;
    if (type === 'Others') {
        const customTypeVal = document.getElementById('bk-custom-event-type')?.value.trim();
        type = customTypeVal || 'Custom Event';
    }
    const pkg = document.getElementById('bk-pkg-name').value;
    const priceRaw = document.getElementById('bk-price').value;
    const notes = document.getElementById('bk-notes').value.trim();
    const otpSection = document.getElementById('bk-otp-section');
    const otpInput = document.getElementById('bk-user-otp');
    const submitBtn = document.querySelector('button[onclick^="submitBookingRequest"]');

    if (!name || !phone || !email || !date) {
        showPushNotification('Fields Required', 'Please complete your name, email, phone, and event date.');
        return;
    }

    const currentEmail = state.user ? (state.user.email || '') : '';
    const currentPhone = state.user ? (state.user.phone || '') : '';

    const emailChanged = email !== currentEmail;
    const phoneChanged = phone !== currentPhone;

    // Check if we need to send OTP first
    if ((emailChanged || phoneChanged) && otpSection.style.display === 'none') {
        const target = emailChanged ? email : phone;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending OTP...';
        }
        API.sendOTP(target, 'verify')
            .then(() => {
                showPushNotification('OTP Sent', 'Verification code sent to ' + target);
                otpSection.style.display = 'block';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Verify & Send Booking Request';
                }
                window._bkPendingTarget = target;
                window._bkPendingType = emailChanged ? 'email' : 'phone';
            })
            .catch(err => {
                showPushNotification('Error', err.message || 'Failed to send OTP.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Send Booking Request';
                }
            });
        return;
    }

    const price = parseFloat(priceRaw.replace(/[^0-9.]/g, '')) || 0;
    const negPriceRaw = document.getElementById('bk-negotiated-price')?.value || '';
    const negotiated_price = parseFloat(negPriceRaw) > 0 ? parseFloat(negPriceRaw) : price;

    const performSubmit = () => {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        }
        API.createBooking({
            vendor_id: vid,
            user_name: name,
            user_phone: phone,
            event_date: date,
            event_type: type,
            package_name: pkg,
            price: price,
            negotiated_price: negotiated_price,
            notes: notes
        }).then(() => {
            showPushNotification('Request Sent', 'Your booking request & price offer have been submitted.');
            closeModal();
            navigateTo('bookings');
        }).catch(err => {
            showPushNotification('Error', err.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Booking Request';
            }
        });
    };

    // If OTP is displayed, verify it first
    if (otpSection.style.display === 'block') {
        const code = otpInput.value.trim();
        if (!code || code.length < 6) {
            showPushNotification('Error', 'Please enter the 6-digit OTP code.');
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Verifying OTP...';
        }
        API.verifyOTP(window._bkPendingTarget, code)
            .then(() => {
                const updatePayload = { name };
                if (window._bkPendingType === 'email') {
                    updatePayload.email = window._bkPendingTarget;
                } else {
                    updatePayload.phone = window._bkPendingTarget;
                }
                return API.post('update_profile', updatePayload);
            })
            .then(res => {
                state.user = res.user;
                updateSidebarUI();
                performSubmit();
            })
            .catch(err => {
                showPushNotification('Verification Error', err.message || 'Failed to verify OTP.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Verify & Send Booking Request';
                }
            });
        return;
    }

    // If name changed but no contact change, update name
    if (name !== (state.user ? state.user.name : '')) {
        API.post('update_profile', { name })
            .then(res => {
                state.user = res.user;
                updateSidebarUI();
                performSubmit();
            })
            .catch(err => {
                console.error("Failed to update name:", err);
                performSubmit();
            });
    } else {
        performSubmit();
    }
}

function openSwitchToCustomerModal(vid, pkgName = '', price = '') {
    const defaultName = state.user ? (state.user.name || '') : '';
    const defaultEmail = state.user ? (state.user.email || '') : '';
    const defaultPhone = state.user ? (state.user.phone || '') : '';

    const html = `
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Switch to Customer Account</h2>
            <p class="auth-modal-subtitle">To book another vendor, please confirm your customer booking profile details below.</p>
        </div>
        <div class="form-group mb-12">
            <label class="form-label">Full Name</label>
            <input type="text" id="sw-cust-name" class="form-input" value="${defaultName}" placeholder="Enter your full name">
        </div>
        <div class="form-group mb-12">
            <label class="form-label">Email Address</label>
            <input type="email" id="sw-cust-email" class="form-input" value="${defaultEmail}" placeholder="Enter email address">
        </div>
        <div class="form-group mb-16">
            <label class="form-label">Phone Number</label>
            <input type="text" id="sw-cust-phone" class="form-input" value="${defaultPhone}" placeholder="Enter phone number">
        </div>
        <div id="sw-otp-section" style="display:none; margin-bottom:16px; padding:12px; background:rgba(var(--accent-rgb),0.05); border:1px dashed var(--accent); border-radius:8px;">
            <label class="form-label" style="font-weight:700; color:var(--accent);">Enter Verification OTP</label>
            <p style="font-size:0.7rem; color:var(--gray-500); margin-bottom:8px;">We have sent a verification code to your new contact info. Please enter it here:</p>
            <input type="text" id="sw-cust-otp" class="form-input" placeholder="Enter OTP code" maxlength="6" style="letter-spacing:4px; text-align:center; font-weight:700;">
        </div>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-outline btn-full" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary btn-full" id="sw-btn-submit" onclick="submitSwitchToCustomer(${vid}, '${pkgName}', '${price}')">Confirm & Switch</button>
        </div>
    `;
    openModal(html);
}

window.submitSwitchToCustomer = function(vid, pkgName, price) {
    const name = document.getElementById('sw-cust-name').value.trim();
    const email = document.getElementById('sw-cust-email').value.trim();
    const phone = document.getElementById('sw-cust-phone').value.trim();
    const otpSection = document.getElementById('sw-otp-section');
    const otpInput = document.getElementById('sw-cust-otp');
    const submitBtn = document.getElementById('sw-btn-submit');

    if (!name || !email || !phone) {
        showPushNotification('Error', 'All fields are required.');
        return;
    }

    const currentEmail = state.user ? (state.user.email || '') : '';
    const currentPhone = state.user ? (state.user.phone || '') : '';

    const emailChanged = email !== currentEmail;
    const phoneChanged = phone !== currentPhone;

    if ((emailChanged || phoneChanged) && otpSection.style.display === 'none') {
        const target = emailChanged ? email : phone;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending OTP...';
        API.sendOTP(target, 'verify')
            .then(res => {
                showPushNotification('OTP Sent', 'Verification code sent to ' + target);
                otpSection.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Verify & Switch';
                window._swPendingTarget = target;
                window._swPendingType = emailChanged ? 'email' : 'phone';
            })
            .catch(err => {
                showPushNotification('Error', err.message || 'Failed to send OTP.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirm & Switch';
            });
        return;
    }

    if (otpSection.style.display === 'block') {
        const code = otpInput.value.trim();
        if (!code || code.length < 6) {
            showPushNotification('Error', 'Please enter a valid 6-digit OTP code.');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.textContent = 'Verifying...';

        API.verifyOTP(window._swPendingTarget, code)
            .then(res => {
                const updatePayload = { name };
                if (window._swPendingType === 'email') {
                    updatePayload.email = window._swPendingTarget;
                } else {
                    updatePayload.phone = window._swPendingTarget;
                }
                return API.post('update_profile', updatePayload);
            })
            .then(res => {
                return API.post('switch_role', { role: 'customer' });
            })
            .then(res => {
                state.user = res.user;
                localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
                updateSidebarUI();
                showPushNotification('Success', 'Profile updated and switched to Customer Mode.');
                closeModal();
                setTimeout(() => openBookingRequestModal(vid, pkgName, price), 300);
            })
            .catch(err => {
                showPushNotification('Verification Error', err.message || 'Failed to verify OTP.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Verify & Switch';
            });
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Switching...';

    const updatePromise = (name !== (state.user ? state.user.name : '')) 
        ? API.post('update_profile', { name })
        : Promise.resolve();

    updatePromise
        .then(() => {
            return API.post('switch_role', { role: 'customer' });
        })
        .then(res => {
            state.user = res.user;
            localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
            updateSidebarUI();
            showPushNotification('Success', 'Switched to Customer Mode.');
            closeModal();
            setTimeout(() => openBookingRequestModal(vid, pkgName, price), 300);
        })
        .catch(err => {
            showPushNotification('Error', err.message || 'Failed to switch role.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Confirm & Switch';
        });
};

window.openBookingDetailsModal = function(bid) {
    let booking = (state.bookings && Array.isArray(state.bookings)) ? state.bookings.find(b => Number(b.id) === Number(bid)) : null;
    
    if (!booking) {
        API.getBookings().then(bookings => {
            state.bookings = bookings;
            const found = bookings.find(b => Number(b.id) === Number(bid));
            if (found) window.openBookingDetailsModal(bid);
            else showPushNotification('Error', 'Booking detail not found.');
        });
        return;
    }

    const isVendor = state.user && (state.user.active_role === 'vendor');
    const negPrice = parseFloat(booking.negotiated_price || booking.price || 0);
    const totalPaid = parseFloat(booking.total_paid || 0);
    const remaining = Math.max(0, negPrice - totalPaid);
    const createdTimeStr = formatRelativeTime(booking.created_at || Date.now());
    const refCode = '#OHT-B' + String(booking.id).padStart(5, '0');

    const html = `
        <div style="max-width:480px; margin:0 auto;">
            <!-- Modal Header -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:12px; border-bottom:1px solid var(--gray-200); margin-bottom:12px;">
                <div>
                    <div style="font-size:0.7rem; font-weight:800; color:var(--primary); letter-spacing:0.5px;">${refCode}</div>
                    <h3 style="margin:2px 0 0 0; font-size:1.05rem; font-weight:700;">${isVendor ? (booking.user_name || 'Client') : (booking.vendor_name || 'Vendor')}</h3>
                    <p style="margin:2px 0 0 0; font-size:0.72rem; color:var(--gray-500);">
                        ${isVendor ? (booking.package_name || booking.event_type || 'Event Package') : (booking.vendor_category + ' • ' + (booking.event_type || 'Event'))}
                    </p>
                </div>
                <span class="booking-status ${booking.status === 'Inquiry' ? 'status-pending' : 'status-confirmed'}">${booking.status}</span>
            </div>

            <!-- Real-Time Timestamp Banner -->
            <div style="background:var(--gray-100); padding:8px 12px; border-radius:8px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; font-size:0.72rem;">
                <span style="color:var(--gray-700); font-weight:600;"><i class="fa-regular fa-clock" style="color:var(--primary);"></i> Requested: <strong>${createdTimeStr}</strong></span>
                <span style="color:var(--gray-500); font-size:0.68rem;">${formatFriendlyDate(booking.created_at)}</span>
            </div>

            <!-- Contact & Booking Grid -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; background:var(--gray-50); padding:12px; border-radius:10px; border:1px solid var(--gray-100); margin-bottom:14px; font-size:0.75rem;">
                <div>
                    <span style="color:var(--gray-500); font-size:0.68rem; display:block;">${isVendor ? 'CLIENT CONTACT' : 'VENDOR DETAILS'}</span>
                    <strong style="color:var(--gray-800); font-size:0.8rem;">${isVendor ? booking.user_name : booking.vendor_name}</strong>
                    <div style="margin-top:4px;">
                        <a href="tel:${isVendor ? booking.user_phone : booking.vendor_phone}" style="color:var(--primary); text-decoration:none; display:flex; align-items:center; gap:4px; margin-top:2px;">
                            <i class="fa-solid fa-phone"></i> ${isVendor ? booking.user_phone : booking.vendor_phone}
                        </a>
                        ${booking.user_email ? `<div style="color:var(--gray-600); font-size:0.68rem; margin-top:2px; word-break:break-all;"><i class="fa-solid fa-envelope"></i> ${booking.user_email}</div>` : ''}
                    </div>
                </div>

                <div>
                    <span style="color:var(--gray-500); font-size:0.68rem; display:block;">EVENT & PRICING</span>
                    <div style="margin-top:2px;"><i class="fa-solid fa-calendar-day" style="color:var(--primary);"></i> Date: <strong>${formatFriendlyDate(booking.event_date)}</strong></div>
                    <div style="margin-top:2px;"><i class="fa-solid fa-tag" style="color:var(--primary);"></i> Total: <strong>GH₵ ${negPrice.toLocaleString(undefined,{minimumFractionDigits:2})}</strong></div>
                    <div style="margin-top:2px; font-size:0.7rem; color:${remaining <= 0 ? 'var(--success)' : 'var(--error)'}; font-weight:700;">
                        ${remaining <= 0 ? 'Fully Paid ✓' : 'Balance: GH₵ ' + remaining.toLocaleString(undefined,{minimumFractionDigits:2})}
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            ${booking.notes ? `
                <div style="margin-bottom:14px;">
                    <span style="font-size:0.7rem; font-weight:700; color:var(--gray-600); display:block; margin-bottom:4px;">CLIENT INSTRUCTIONS / NOTES:</span>
                    <div style="background:var(--gray-100); padding:10px; border-radius:8px; font-size:0.75rem; color:var(--gray-800); border-left:3px solid var(--primary);">
                        ${booking.notes}
                    </div>
                </div>
            ` : ''}

            <!-- Booking Progress Timeline -->
            <div style="font-size:0.7rem; font-weight:700; color:var(--gray-500); margin-bottom:8px; letter-spacing:0.5px; text-transform:uppercase;">STAGES & PROGRESS</div>
            <div class="booking-timeline mb-16" style="margin-left:4px;">
                ${booking.timeline && booking.timeline.length > 0 ? booking.timeline.map((t, idx) => `
                    <div class="booking-stage">
                        <div class="stage-dot ${idx === booking.timeline.length - 1 ? 'active' : 'done'}" style="width:20px; height:20px; font-size:0.6rem;">
                            ${idx === booking.timeline.length - 1 ? '<i class="fa-solid fa-spinner fa-spin"></i>' : '<i class="fa-solid fa-check"></i>'}
                        </div>
                        <div class="stage-info" style="margin-left:12px;">
                            <div class="stage-label" style="font-size:0.78rem; font-weight:700; color:var(--gray-800);">${t.status}</div>
                            <div class="stage-date" style="font-size:0.62rem; color:var(--gray-500);">${formatRelativeTime(t.timestamp)}</div>
                            ${t.notes ? `<div class="stage-note" style="font-size:0.7rem; margin-top:2px;">${t.notes}</div>` : ''}
                        </div>
                    </div>
                `).join('') : '<div class="text-center text-muted text-sm p-8">Inquiry Submitted</div>'}
            </div>

            <!-- Action Buttons Row -->
            <div style="display:flex; flex-direction:column; gap:8px; margin-top:16px;">
                ${isVendor ? `
                    <div style="display:flex; gap:8px;">
                        ${booking.status === 'Inquiry' ? `
                            <button class="btn btn-primary btn-full" onclick="updateBookingStatus(${booking.id}, 'Confirmed'); closeModal();" style="height:36px; font-size:0.78rem;"><i class="fa-solid fa-circle-check"></i> Accept Booking</button>
                            <button class="btn btn-outline btn-full" onclick="updateBookingStatus(${booking.id}, 'Cancelled'); closeModal();" style="height:36px; font-size:0.78rem; color:var(--error); border-color:var(--error);"><i class="fa-solid fa-ban"></i> Decline</button>
                        ` : ''}
                        ${booking.status === 'Confirmed' ? `
                            <button class="btn btn-primary btn-full" onclick="updateBookingStatus(${booking.id}, 'Completed'); closeModal();" style="height:36px; font-size:0.78rem;"><i class="fa-solid fa-flag-checkered"></i> Mark Completed</button>
                        ` : ''}
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-outline btn-full" onclick="startVendorChat(${booking.user_id || booking.vendor_id}); closeModal();" style="height:36px; font-size:0.75rem;"><i class="fa-solid fa-comments"></i> Chat with Client</button>
                        <button class="btn btn-outline btn-full" onclick="openBookingInvoice(${booking.id});" style="height:36px; font-size:0.75rem;"><i class="fa-solid fa-receipt"></i> Invoice</button>
                    </div>
                ` : `
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-outline btn-full" onclick="startVendorChat(${booking.vendor_id}); closeModal();" style="height:36px; font-size:0.75rem;"><i class="fa-solid fa-comments"></i> Chat Vendor</button>
                        <button class="btn btn-outline btn-full" onclick="openBookingInvoice(${booking.id});" style="height:36px; font-size:0.75rem;"><i class="fa-solid fa-receipt"></i> View Invoice</button>
                    </div>
                `}
                <button class="btn btn-primary btn-full" onclick="closeModal();" style="height:36px; font-size:0.8rem; margin-top:4px;">Close</button>
            </div>
        </div>
    `;
    openModal(html);
};

window.openBookingTimelineModal = function(bid) {
    window.openBookingDetailsModal(bid);
};

// ── PAYMENT SUCCESS RECEIPT ─────────────────────────────────────────────
function showPaymentReceipt(booking, amountPaid, reference) {
    if (!booking) return;
    const totalCost = parseFloat(booking.negotiated_price || booking.price || 0);
    const totalPaid = parseFloat(booking.total_paid || 0);
    const remaining = Math.max(0, totalCost - totalPaid);
    const now = new Date();
    const receiptNo = 'OHT-' + booking.id + '-' + now.getTime().toString(36).toUpperCase();

    const html = `
        <div id="receipt-printable" style="max-width:420px;margin:0 auto;">
            <!-- Receipt Header -->
            <div style="text-align:center;padding-bottom:16px;border-bottom:2px dashed var(--gray-200);margin-bottom:16px;">
                <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:8px;">
                    <img src="img/logo black transparent small.png" alt="Ohati" style="height:32px;width:auto;">
                    <span style="font-family:'Fraunces',serif;font-size:1.1rem;font-weight:800;color:var(--primary);">OHATI</span>
                </div>
                <div style="background:linear-gradient(135deg,#10B981,#059669);color:white;border-radius:50%;width:52px;height:52px;margin:12px auto;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-check" style="font-size:1.4rem;"></i>
                </div>
                <h3 style="margin:0 0 4px 0;font-size:1rem;color:var(--gray-800);">Payment Successful</h3>
                <p style="margin:0;font-size:0.7rem;color:var(--gray-400);">Receipt #${receiptNo}</p>
            </div>

            <!-- Amount -->
            <div style="text-align:center;margin-bottom:16px;">
                <div style="font-size:2rem;font-weight:800;color:var(--primary);font-family:'Outfit',sans-serif;">GH₵ ${parseFloat(amountPaid).toLocaleString(undefined,{minimumFractionDigits:2})}</div>
                <div style="font-size:0.7rem;color:var(--success);font-weight:600;margin-top:4px;">
                    <i class="fa-solid fa-shield-halved"></i> Ohati Protected
                </div>
            </div>

            <!-- Details Grid -->
            <div style="background:var(--gray-50);border:1px solid var(--gray-100);border-radius:12px;padding:14px;margin-bottom:14px;font-size:0.75rem;">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span style="color:var(--gray-500);">Vendor</span>
                    <span style="font-weight:700;color:var(--gray-800);">${booking.vendor_name}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span style="color:var(--gray-500);">Service</span>
                    <span style="font-weight:600;color:var(--gray-700);">${booking.package_name || booking.vendor_category}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span style="color:var(--gray-500);">Event Date</span>
                    <span style="font-weight:600;color:var(--gray-700);">${formatFriendlyDate(booking.event_date)}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span style="color:var(--gray-500);">Method</span>
                    <span style="font-weight:600;color:var(--gray-700);"><i class="fa-solid fa-credit-card" style="color:var(--accent);"></i> Paystack</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span style="color:var(--gray-500);">Reference</span>
                    <span style="font-weight:600;color:var(--gray-700);font-size:0.65rem;">${reference || '—'}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span style="color:var(--gray-500);">Date & Time</span>
                    <span style="font-weight:600;color:var(--gray-700);">${now.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})} ${now.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'})}</span>
                </div>
                <div style="border-top:1px solid var(--gray-200);padding-top:8px;margin-top:4px;display:flex;justify-content:space-between;">
                    <span style="color:var(--gray-500);">Balance Due</span>
                    <span style="font-weight:800;color:${remaining <= 0 ? 'var(--success)' : 'var(--error)'};">${remaining <= 0 ? 'Fully Paid ✓' : 'GH₵ ' + remaining.toLocaleString(undefined,{minimumFractionDigits:2})}</span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display:flex;gap:8px;margin-bottom:12px;">
                <button class="btn btn-outline btn-full btn-sm" onclick="printBookingInvoice(${booking.id})" style="font-size:0.75rem;"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn btn-outline btn-full btn-sm" onclick="downloadBookingInvoice(${booking.id})" style="font-size:0.75rem;"><i class="fa-solid fa-download"></i> Download</button>
            </div>
            <button class="btn btn-primary btn-full" onclick="closeModal();" style="height:38px;font-size:0.85rem;">Done</button>
        </div>
    `;
    openModal(html);
}

// ── PROFESSIONAL BOOKING INVOICE ────────────────────────────────────────
function openBookingInvoice(bid) {
    const booking = state.bookings.find(b => b.id === bid);
    if (!booking) return;

    const totalCost = parseFloat(booking.negotiated_price || booking.price || 0);
    const invoiceNo = 'INV-OHT-' + String(booking.id).padStart(5, '0');
    const createdDate = booking.timeline && booking.timeline.length > 0
        ? new Date(booking.timeline[0].timestamp)
        : new Date(booking.created_at || Date.now());
    const timeFormatted = createdDate.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const vendorLogo = booking.vendor_logo || 'img/logo black transparent small.png';

    const statusBadge = booking.status === 'Confirmed'
        ? '<span style="color:var(--success); font-weight:800;">✓ CONFIRMED</span>'
        : (booking.status === 'Cancelled' ? '<span style="color:var(--error); font-weight:800;">✕ CANCELLED</span>' : '<span style="color:var(--primary); font-weight:800;">● INQUIRY SUBMITTED</span>');

    const html = `
        <div id="invoice-printable" style="max-width:460px;margin:0 auto;">
            <!-- INVOICE HEADER: Two-column logo layout -->
            <div style="display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:16px;border-bottom:2px solid var(--primary);margin-bottom:16px;">
                <!-- Ohati Side -->
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <img src="img/logo black transparent small.png" alt="Ohati" style="height:28px;width:auto;">
                        <span style="font-family:'Fraunces',serif;font-size:1rem;font-weight:800;color:var(--primary);">OHATI</span>
                    </div>
                    <div style="font-size:0.6rem;color:var(--gray-500);line-height:1.5;">
                        Ghana's Event Marketplace<br>
                        support@ohati.com<br>
                        +233 54 337 7470
                    </div>
                </div>
                <!-- Vendor Side -->
                <div style="flex:1;text-align:right;">
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;margin-bottom:6px;">
                        <span style="font-size:0.9rem;font-weight:800;color:var(--gray-800);">${booking.vendor_name}</span>
                        <img src="${vendorLogo}" alt="${booking.vendor_name}" style="height:28px;width:28px;border-radius:50%;object-fit:cover;border:2px solid var(--gray-200);" onerror="this.src='img/logo black transparent small.png'">
                    </div>
                    <div style="font-size:0.6rem;color:var(--gray-500);line-height:1.5;">
                        ${booking.vendor_category}<br>
                        ${booking.vendor_location || 'Ghana'}<br>
                        ${booking.vendor_phone || booking.vendor_whatsapp || ''}
                    </div>
                </div>
            </div>

            <!-- INVOICE META -->
            <div style="display:flex;justify-content:space-between;margin-bottom:16px;padding:10px 14px;background:var(--gray-50);border:1px solid var(--gray-100);border-radius:10px;">
                <div>
                    <div style="font-size:0.6rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.5px;">Invoice No.</div>
                    <div style="font-size:0.8rem;font-weight:800;color:var(--primary);">${invoiceNo}</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:0.6rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.5px;">Issued Date & Time</div>
                    <div style="font-size:0.75rem;font-weight:700;color:var(--gray-800);">${createdDate.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})} ${timeFormatted}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.6rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.5px;">Booking Status</div>
                    <div style="font-size:0.75rem;">${statusBadge}</div>
                </div>
            </div>

            <!-- BILL TO -->
            <div style="margin-bottom:14px; padding:10px 12px; background:var(--gray-50); border-radius:8px;">
                <div style="font-size:0.6rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Billed To (Client Details)</div>
                <div style="font-size:0.8rem;font-weight:700;color:var(--gray-800);">${booking.user_name}</div>
                <div style="font-size:0.7rem;color:var(--gray-600);"><i class="fa-solid fa-phone"></i> ${booking.user_phone}</div>
                ${booking.user_email ? `<div style="font-size:0.7rem;color:var(--gray-600);"><i class="fa-solid fa-envelope"></i> ${booking.user_email}</div>` : ''}
            </div>

            <!-- SERVICE DETAILS TABLE -->
            <table style="width:100%;border-collapse:collapse;margin-bottom:14px;border:1px solid var(--gray-100);border-radius:8px;overflow:hidden;">
                <thead>
                    <tr style="background:var(--primary);color:white;">
                        <th style="padding:8px 10px;text-align:left;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;">Description / Service Package</th>
                        <th style="padding:8px 10px;text-align:right;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;">Agreed Price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding:10px;border-bottom:1px solid var(--gray-100);">
                            <div style="font-size:0.78rem;font-weight:700;color:var(--gray-800);">${booking.package_name || 'Custom Service Package'}</div>
                            <div style="font-size:0.65rem;color:var(--gray-500);">${booking.vendor_category} · ${booking.event_type || 'Event'} · Event Date: ${formatFriendlyDate(booking.event_date)}</div>
                            ${booking.notes ? `<div style="font-size:0.65rem;color:var(--gray-600);margin-top:4px;"><em>Notes: ${booking.notes}</em></div>` : ''}
                        </td>
                        <td style="padding:10px;text-align:right;font-weight:800;font-size:0.85rem;color:var(--gray-800);border-bottom:1px solid var(--gray-100);">GH₵ ${totalCost.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr style="background:var(--gray-50);border-top:2px solid var(--primary);">
                        <td style="padding:10px;font-size:0.78rem;font-weight:800;color:var(--primary);">Total Booking Amount</td>
                        <td style="padding:10px;text-align:right;font-weight:800;font-size:0.9rem;color:var(--primary);">GH₵ ${totalCost.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                    </tr>
                </tfoot>
            </table>

            <!-- MARKETPLACE NOTICE -->
            <div style="background:rgba(14,131,69,0.06);border:1px solid rgba(14,131,69,0.2);border-radius:8px;padding:10px 12px;margin-bottom:16px;">
                <p style="margin:0;font-size:0.68rem;color:var(--gray-700);line-height:1.5;">
                    <i class="fa-solid fa-circle-check" style="color:var(--primary);"></i>
                    <strong>Official Booking Invoice:</strong> Issued by Ohati Event Marketplace. Details and scheduling are confirmed between client and vendor.
                </p>
            </div>

            <!-- ACTION BUTTONS -->
            <div style="display:flex;gap:8px;margin-bottom:10px;">
                <button class="btn btn-outline btn-full btn-sm" onclick="printBookingInvoice(${booking.id})" style="font-size:0.75rem;height:38px;"><i class="fa-solid fa-print"></i> Print Invoice</button>
                <button class="btn btn-outline btn-full btn-sm" onclick="downloadBookingInvoice(${booking.id})" style="font-size:0.75rem;height:38px;"><i class="fa-solid fa-download"></i> Download PDF</button>
            </div>
            <button class="btn btn-primary btn-full" onclick="closeModal();" style="height:38px;font-size:0.85rem;">Close</button>
        </div>
    `;
    openModal(html);
}

// ── PRINT & DOWNLOAD INVOICE HELPERS ────────────────────────────────────
function printBookingInvoice(bid) {
    const el = document.getElementById('invoice-printable') || document.getElementById('receipt-printable');
    if (!el) return;
    const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
    const printWin = window.open('', '_blank', 'width=500,height=700');
    printWin.document.write(`
        <html><head>
        <meta charset="UTF-8">
        <base href="${baseUrl}">
        <title>Ohati Invoice</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:'Segoe UI',Tahoma,sans-serif; padding:20px; color:#333; }
            .btn { display:none !important; }
            table { width:100%; border-collapse:collapse; }
            @media print { .no-print { display:none !important; } }
        </style></head><body>
        ${el.innerHTML}
        <script>setTimeout(()=>{window.print();window.close();},400);<\/script>
        </body></html>
    `);
    printWin.document.close();
}

function downloadBookingInvoice(bid) {
    const el = document.getElementById('invoice-printable') || document.getElementById('receipt-printable');
    if (!el) return;
    const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
    // Use canvas-based approach for PDF-like download
    const clone = el.cloneNode(true);
    clone.querySelectorAll('.btn, button').forEach(b => b.remove());
    const blob = new Blob([`
        <html><head>
        <meta charset="UTF-8">
        <base href="${baseUrl}">
        <title>Ohati Invoice #${bid}</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:'Segoe UI',Tahoma,sans-serif; padding:30px; color:#333; max-width:500px; margin:0 auto; }
            table { width:100%; border-collapse:collapse; }
        </style></head><body>
        ${clone.innerHTML}
        </body></html>
    `], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'Ohati_Invoice_' + bid + '.html';
    a.click();
    URL.revokeObjectURL(url);
    showPushNotification('Downloaded', 'Invoice saved. Open it in a browser and use Print → Save as PDF for a PDF copy.');
}

// ── 6. SAVED FAVORITES SCREEN ──────────────────────────────────────────
function renderFavoritesScreen(favorites) {
    const container = document.getElementById('favorites-list');
    if (!container) return;

    if (favorites.length === 0) {
        container.innerHTML = `
            <div class="favorites-empty">
                <i class="fa-solid fa-heart"></i>
                <h3>No Saved Vendors Yet</h3>
                <p>Tap the heart icon on vendor profiles to save them here.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="favorites-grid">
            ${favorites.map(f => `
                <div class="fav-card" onclick="viewVendorDetails(${f.id})" style="position:relative;">
                    <img class="fav-cover" src="${f.cover_photo || 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=200'}" alt="">
                    <button onclick="shareVendorProfile(state.favorites.find(x => x.id === ${f.id}), event)" style="position:absolute; top:8px; right:8px; border:none; width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,0.9); color:#1B2B4B; display:flex; align-items:center; justify-content:center; font-size:0.75rem; cursor:pointer; box-shadow:var(--shadow-sm); z-index:5;">
                        <i class="fa-solid fa-share-nodes"></i>
                    </button>
                    <div class="fav-info">
                        <div class="fav-name">${f.name}</div>
                        <div class="fav-cat">${f.category}</div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function initFavoritesScreen() {
    const screen = document.getElementById('screen-favorites');
    if (!screen) return;

    screen.innerHTML = `
        <div class="p-section" style="padding-bottom:10px;">
            <h3>Saved Vendors</h3>
        </div>
        <div id="favorites-list" class="p-section" style="padding-top:0;">
            ${(state.favorites && state.favorites.length > 0) ? '' : renderSkeletonCardsHTML(4)}
        </div>
    `;

    if (state.favorites && state.favorites.length > 0) {
        renderFavoritesScreen(state.favorites);
    }

    API.getFavorites().then(favorites => {
        state.favorites = favorites;
        renderFavoritesScreen(favorites);
    });
}

// ── 7. SMART EVENT PLANNER & CHECKLIST SCREEN ──────────────────────────
function initEventScreen() {
    const screen = document.getElementById('screen-event');
    if (!screen) return;

    if (state.event !== undefined) {
        if (!state.event) {
            renderEventPlannerSetup();
        } else {
            renderEventDashboard();
        }
    } else {
        screen.innerHTML = `<div class="full-spinner-wrap"><div class="spinner"></div></div>`;
    }

    API.getEvent().then(event => {
        state.event = event;
        if (!event) {
            renderEventPlannerSetup();
        } else {
            renderEventDashboard();
        }
    });
}

function renderEventPlannerSetup() {
    const screen = document.getElementById('screen-event');
    if (!screen) return;

    screen.innerHTML = `
        <div class="p-section">
            <div class="event-planner-header">
                <h3 style="color:#fff; margin-bottom:6px;">Setup Event Planner</h3>
                <p style="font-size:0.75rem; color:rgba(255,255,255,0.9); line-height:1.4;">Create a custom planning timeline tailored to your Ghanaian wedding or event date.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Event Name</label>
                <input type="text" class="form-input" id="ep-name" placeholder="e.g. Ama & Kofi Wedding">
            </div>

            <div class="form-group">
                <label class="form-label">Event Date</label>
                <input type="date" class="form-input" id="ep-date">
            </div>

            <div class="form-group">
                <label class="form-label">Estimated Budget (GHS)</label>
                <input type="number" class="form-input" id="ep-budget" placeholder="e.g. 50000">
            </div>

            <div class="form-group">
                <label class="form-label">Event Type</label>
                <div class="event-type-grid">
                    <div class="event-type-btn selected" id="et-wedding" onclick="selectEventType('Wedding')">
                        <i class="fa-solid fa-ring"></i> Wedding
                    </div>
                    <div class="event-type-btn" id="et-birthday" onclick="selectEventType('Birthday')">
                        <i class="fa-solid fa-cake-candles"></i> Birthday
                    </div>
                    <div class="event-type-btn" id="et-other" onclick="selectEventType('Other')">
                        <i class="fa-solid fa-star"></i> Other
                    </div>
                </div>
                <div id="ep-custom-type-wrap" style="display:none; margin-top:10px;">
                    <input type="text" class="form-input" id="ep-custom-type" placeholder="Type your custom event (e.g. Graduation, Funeral, Naming Ceremony, Gala)..." style="border:1.5px solid var(--primary);">
                </div>
            </div>

            <button class="btn btn-primary btn-full mt-16" onclick="submitEventSetup()">Start Planning</button>
        </div>
    `;
    state.authData.eventType = 'Wedding';
}

function selectEventType(type) {
    state.authData.eventType = type;
    document.getElementById('et-wedding')?.classList.toggle('selected', type === 'Wedding');
    document.getElementById('et-birthday')?.classList.toggle('selected', type === 'Birthday');
    document.getElementById('et-other')?.classList.toggle('selected', type === 'Other');
    
    const wrap = document.getElementById('ep-custom-type-wrap');
    if (wrap) {
        wrap.style.display = (type === 'Other') ? 'block' : 'none';
        if (type === 'Other') {
            const input = document.getElementById('ep-custom-type');
            if (input) input.focus();
        }
    }
}

function submitEventSetup() {
    const name = document.getElementById('ep-name').value.trim();
    const date = document.getElementById('ep-date').value;
    const budget = parseFloat(document.getElementById('ep-budget').value) || 0;

    let eventType = state.authData.eventType || 'Wedding';
    if (eventType === 'Other') {
        const customType = document.getElementById('ep-custom-type')?.value.trim();
        eventType = customType || 'Custom Event';
    }

    if (!name || !date) {
        showPushNotification('Fields Required', 'Please enter event name and date.');
        return;
    }

    API.saveEvent({
        event_name: name,
        event_date: date,
        estimated_budget: budget,
        event_type: eventType
    }).then(() => {
        showPushNotification('Planner Initialized', 'Dynamic checklist generated.');
        initEventScreen();
    });
}

function renderEventDashboard() {
    const screen = document.getElementById('screen-event');
    if (!screen) return;

    const isDesktop = window.innerWidth >= 768;

    if (isDesktop) {
        screen.innerHTML = `
            <div class="p-section" style="padding-bottom:10px;">
                <div class="flex-between">
                    <h3 style="margin:0; font-family:'Fraunces',serif;">My Planner Dashboard</h3>
                    <button class="btn btn-outline btn-sm" onclick="resetPlannerData()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                </div>
            </div>
            <div class="planner-desktop-grid">
                <div class="planner-column-left">
                    <h4>Checklist Tasks</h4>
                    <div id="planner-tasks-container"></div>
                </div>
                <div class="planner-column-right">
                    <h4>Budget & Expenses</h4>
                    <div id="planner-budget-container"></div>
                </div>
            </div>
        `;

        Promise.all([
            API.getTrackerTasks(),
            API.getTrackerStats(),
            API.getBookings()
        ]).then(([tasks, stats, bookings]) => {
            state.trackerTasks = tasks;
            state.trackerStats = stats;
            state.bookings = bookings || [];

            const tasksContainer = document.getElementById('planner-tasks-container');
            if (tasksContainer) {
                tasksContainer.innerHTML = `
                    <div class="card mb-16" style="padding:12px;">
                        <div style="font-size:0.75rem; font-weight:700; color:var(--gray-500); margin-bottom:4px;">COMPLETED TASKS</div>
                        <div style="font-size:1.4rem; font-weight:800; color:var(--primary);">${stats.percentage}% <span style="font-size:0.8rem; font-weight:600; color:var(--gray-500);">(${stats.completed}/${stats.total})</span></div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div class="card" style="padding:10px 14px;">
                            <div style="display:flex; gap:10px; align-items:center;">
                                <input type="text" class="form-input" style="padding:8px 12px; height:34px; font-size:0.8rem;" placeholder="Add new task..." id="new-task-name" onkeyup="if(event.key==='Enter') addNewPlannerTask()">
                                <button class="btn btn-primary btn-sm" style="height:34px;" onclick="addNewPlannerTask()"><i class="fa-solid fa-plus"></i></button>
                            </div>
                        </div>
                        ${tasks.map(t => `
                            <div class="checklist-item">
                                <div class="task-checkbox ${t.completed ? 'done' : ''}" onclick="toggleTaskCompleted(${t.id}, ${t.completed})">
                                    ${t.completed ? '<i class="fa-solid fa-check"></i>' : ''}
                                </div>
                                <div class="task-info">
                                    <div class="task-name ${t.completed ? 'done' : ''}">${t.task_name}</div>
                                    <div class="task-date">
                                        <span>${t.category}</span>
                                        ${t.priority ? `<span class="task-priority priority-${t.priority.toLowerCase()}">${t.priority}</span>` : ''}
                                    </div>
                                </div>
                                <button class="btn btn-ghost btn-sm" style="padding:4px; margin-left:auto;" onclick="deletePlannerTask(${t.id})">
                                    <i class="fa-solid fa-trash text-error" style="font-size:0.8rem;"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            const budgetContainer = document.getElementById('planner-budget-container');
            if (budgetContainer) {
                budgetContainer.innerHTML = renderBudgetSectionHTML(stats.budget);
            }
        });
    } else {
        screen.innerHTML = `
            <div class="p-section" style="padding-bottom:10px;">
                <div class="flex-between">
                    <h3>My Planner</h3>
                    <button class="btn btn-outline btn-sm" onclick="resetPlannerData()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                </div>
            </div>

            <div class="planning-tabs" style="margin: 0 18px 18px;">
                <button class="planning-tab ${state.activePlanningTab === 'checklist' ? 'active' : ''}" onclick="selectPlanningTab('checklist')">Checklist</button>
                <button class="planning-tab ${state.activePlanningTab === 'budget' ? 'active' : ''}" onclick="selectPlanningTab('budget')">Budget & Costs</button>
            </div>

            <div id="planner-tab-content" class="p-section" style="padding-top:0;">
                ${renderSkeletonListHTML(4)}
            </div>
        `;

        Promise.all([
            API.getTrackerTasks(),
            API.getTrackerStats(),
            API.getBookings()
        ]).then(([tasks, stats, bookings]) => {
            state.trackerTasks = tasks;
            state.trackerStats = stats;
            state.bookings = bookings || [];
            renderPlannerTabContent();
        });
    }
}

function selectPlanningTab(tab) {
    state.activePlanningTab = tab;
    renderEventDashboard();
}

function renderBudgetSectionHTML(b) {
    const totalEst = b.estimated || 0;
    const totalAllocated = b.total_cost || 0;
    const totalPaid = b.total_paid || 0;
    const unallocated = totalEst - totalAllocated;
    const outstanding = totalAllocated - totalPaid;

    const bookings = (state.bookings && Array.isArray(state.bookings)) 
        ? state.bookings.filter(bk => bk.status !== 'Cancelled' && bk.status !== 'Rejected') 
        : [];

    const customExpenseTasks = (state.trackerTasks && Array.isArray(state.trackerTasks)) 
        ? state.trackerTasks.filter(t => parseFloat(t.cost || 0) > 0 || t.is_custom) 
        : [];

    return `
        <!-- Main Budget Overview Card -->
        <div class="card mb-16" style="background:linear-gradient(135deg, #0F1923, #1F2937); color:#fff; padding:18px; border-radius:16px; box-shadow:0 10px 25px rgba(0,0,0,0.15);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                <div>
                    <div style="font-size:0.68rem; font-weight:700; color:var(--accent); text-transform:uppercase; letter-spacing:0.5px;">TOTAL ESTIMATED BUDGET</div>
                    <div style="font-size:1.7rem; font-weight:900; font-family:'Fraunces',serif; margin-top:2px; color:#fff;">${formatCurrency(totalEst)}</div>
                </div>
                <button class="btn btn-sm" onclick="openEditBudgetModal(${totalEst})" style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3); padding:6px 12px; font-size:0.75rem; border-radius:20px;">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Budget
                </button>
            </div>

            <!-- Progress Bar -->
            <div style="margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; font-size:0.7rem; color:#9CA3AF; margin-bottom:4px;">
                    <span>Total Expenses: ${formatCurrency(totalAllocated)}</span>
                    <span>${totalEst > 0 ? Math.min(100, Math.round((totalAllocated / totalEst) * 100)) : 0}% Allocated</span>
                </div>
                <div style="width:100%; height:8px; background:rgba(255,255,255,0.1); border-radius:4px; overflow:hidden;">
                    <div style="width:${totalEst > 0 ? Math.min(100, (totalAllocated / totalEst) * 100) : 0}%; height:100%; background:${unallocated < 0 ? '#EF4444' : 'linear-gradient(90deg, #E05A47, #F59E0B)'}; border-radius:4px; transition:width 0.4s ease;"></div>
                </div>
            </div>

            <!-- Grid Totals -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; border-top:1px solid rgba(255,255,255,0.1); padding-top:12px; font-size:0.72rem;">
                <div>
                    <span style="color:#9CA3AF; display:block; font-size:0.65rem;">Paid to Date</span>
                    <strong style="color:#10B981; font-size:0.82rem;">${formatCurrency(totalPaid)}</strong>
                </div>
                <div>
                    <span style="color:#9CA3AF; display:block; font-size:0.65rem;">Outstanding</span>
                    <strong style="color:#EF4444; font-size:0.82rem;">${formatCurrency(outstanding)}</strong>
                </div>
                <div>
                    <span style="color:#9CA3AF; display:block; font-size:0.65rem;">Unallocated</span>
                    <strong style="color:${unallocated < 0 ? '#EF4444' : '#F59E0B'}; font-size:0.82rem;">${formatCurrency(unallocated)}</strong>
                </div>
            </div>
        </div>

        <!-- Section Header: Cost Breakdown & Management -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h4 style="margin:0; font-size:0.95rem; font-weight:800; color:var(--gray-900);">Manage Cost Breakdown</h4>
            <button class="btn btn-primary btn-sm" onclick="openAddExpenseModal()" style="font-size:0.72rem; padding:6px 12px;">
                <i class="fa-solid fa-plus"></i> Add Custom Expense
            </button>
        </div>

        <!-- Booked Vendors Cost Section -->
        <div class="card mb-16" style="padding:14px;">
            <div style="font-size:0.7rem; font-weight:800; color:var(--primary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-store"></i> Confirmed Booked Vendors (${bookings.length})
            </div>
            ${bookings.length === 0 ? `
                <div style="font-size:0.75rem; color:var(--gray-500); padding:10px 0; text-align:center;">No confirmed vendor bookings yet. Browse and book vendors to calculate real expenses.</div>
            ` : `
                <div style="display:flex; flex-direction:column; gap:8px;">
                    ${bookings.map(bk => {
                        const cost = parseFloat(bk.negotiated_price || bk.price || 0);
                        const paid = parseFloat(bk.total_paid || 0);
                        const rem = Math.max(0, cost - paid);
                        return `
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; background:var(--gray-50); border-radius:8px; border:1px solid var(--gray-100); font-size:0.78rem;">
                                <div>
                                    <strong style="color:var(--gray-900); display:block;">${bk.vendor_name || 'Vendor'}</strong>
                                    <span style="font-size:0.68rem; color:var(--gray-500);">${bk.vendor_category || 'Service'} • ${bk.package_name || bk.event_type}</span>
                                </div>
                                <div style="text-align:right;">
                                    <strong style="color:var(--primary); font-weight:800; font-size:0.85rem; display:block;">${formatCurrency(cost)}</strong>
                                    <span style="font-size:0.65rem; color:${rem <= 0 ? 'var(--success)' : 'var(--error)'}; font-weight:600;">
                                        ${rem <= 0 ? 'Fully Paid ✓' : 'Bal: ' + formatCurrency(rem)}
                                    </span>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `}
        </div>

        <!-- Custom Expense Items Section -->
        <div class="card mb-16" style="padding:14px;">
            <div style="font-size:0.7rem; font-weight:800; color:var(--accent); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-calculator"></i> Custom Expenses & Budget Items (${customExpenseTasks.length})
            </div>
            ${customExpenseTasks.length === 0 ? `
                <div style="font-size:0.75rem; color:var(--gray-500); padding:10px 0; text-align:center;">No custom expenses added yet. Click "+ Add Custom Expense" to include extra event costs.</div>
            ` : `
                <div style="display:flex; flex-direction:column; gap:8px;">
                    ${customExpenseTasks.map(t => {
                        const cost = parseFloat(t.cost || 0);
                        const paid = parseFloat(t.paid_amount || 0);
                        const rem = Math.max(0, cost - paid);
                        const safeName = (t.task_name || '').replace(/'/g, "\\'");
                        return `
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; background:var(--white); border-radius:8px; border:1px solid var(--gray-200); font-size:0.78rem;">
                                <div>
                                    <strong style="color:var(--gray-900); display:block;">${t.task_name}</strong>
                                    <span style="font-size:0.68rem; color:var(--gray-500);">${t.category} ${t.notes ? '• ' + t.notes : ''}</span>
                                </div>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="text-align:right;">
                                        <strong style="color:var(--gray-900); font-weight:800; font-size:0.85rem; display:block;">${formatCurrency(cost)}</strong>
                                        <span style="font-size:0.65rem; color:${rem <= 0 ? 'var(--success)' : 'var(--error)'}; font-weight:600;">
                                            ${rem <= 0 ? 'Paid ✓' : 'Bal: ' + formatCurrency(rem)}
                                        </span>
                                    </div>
                                    <button class="btn btn-ghost btn-xs" onclick="openEditExpenseModal(${t.id}, '${safeName}', ${cost}, ${paid})" title="Edit Expense" style="color:var(--primary);"><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn btn-ghost btn-xs" onclick="deletePlannerTask(${t.id})" title="Delete Expense" style="color:var(--error);"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `}
        </div>
    `;
}

function renderPlannerTabContent() {
    const container = document.getElementById('planner-tab-content');
    if (!container) return;

    if (state.activePlanningTab === 'checklist') {
        let html = `
            <div class="card mb-16" style="padding:12px;">
                <div style="font-size:0.75rem; font-weight:700; color:var(--gray-500); margin-bottom:4px;">COMPLETED TASKS</div>
                <div style="font-size:1.4rem; font-weight:800; color:var(--primary);">${state.trackerStats.percentage}% <span style="font-size:0.8rem; font-weight:600; color:var(--gray-500);">(${state.trackerStats.completed}/${state.trackerStats.total})</span></div>
            </div>

            <div style="display:flex; flex-direction:column; gap:8px;">
                <div class="card" style="padding:10px 14px;">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="text" class="form-input" style="padding:8px 12px; height:34px; font-size:0.8rem;" placeholder="Add new task..." id="new-task-name" onkeyup="if(event.key==='Enter') addNewPlannerTask()">
                        <button class="btn btn-primary btn-sm" style="height:34px;" onclick="addNewPlannerTask()"><i class="fa-solid fa-plus"></i></button>
                    </div>
                </div>
                ${state.trackerTasks.map(t => `
                    <div class="checklist-item">
                        <div class="task-checkbox ${t.completed ? 'done' : ''}" onclick="toggleTaskCompleted(${t.id}, ${t.completed})">
                            ${t.completed ? '<i class="fa-solid fa-check"></i>' : ''}
                        </div>
                        <div class="task-info">
                            <div class="task-name ${t.completed ? 'done' : ''}">${t.task_name}</div>
                            <div class="task-date">
                                <span>${t.category}</span>
                                ${t.priority ? `<span class="task-priority priority-${t.priority.toLowerCase()}">${t.priority}</span>` : ''}
                            </div>
                        </div>
                        <button class="btn btn-ghost btn-sm" style="padding:4px; margin-left:auto;" onclick="deletePlannerTask(${t.id})">
                            <i class="fa-solid fa-trash text-error" style="font-size:0.8rem;"></i>
                        </button>
                    </div>
                `).join('')}
            </div>
        `;
        container.innerHTML = html;
    } else {
        // Budget & Cost Breakdown View
        container.innerHTML = renderBudgetSectionHTML(state.trackerStats.budget);
    }
}

function toggleTaskCompleted(id, curr) {
    API.updateTask({ id: id, completed: curr ? 0 : 1 }).then(() => {
        renderEventDashboard();
    });
}

function addNewPlannerTask() {
    const input = document.getElementById('new-task-name');
    const name = input?.value.trim() || '';
    if (!name) return;
    input.value = '';

    API.addTask({
        task_name: name,
        priority: 'Medium'
    }).then(() => {
        renderEventDashboard();
    });
}

function deletePlannerTask(id) {
    API.deleteTask(id).then(() => {
        renderEventDashboard();
    });
}

window.openEditBudgetModal = function(currentBudget) {
    openModal(`
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Edit Overall Budget</h2>
            <p class="auth-modal-subtitle">Update your total estimated event budget (GH₵)</p>
        </div>
        <div class="form-group mb-16">
            <label class="form-label" style="font-weight:700;">Total Event Budget (GH₵)</label>
            <input type="number" step="any" class="form-input" id="edit-budget-input" value="${currentBudget || 0}" style="font-size:1.1rem; font-weight:800; border:1.5px solid var(--primary);">
        </div>
        <button class="btn btn-primary btn-full" onclick="saveEventBudget()">Save Budget</button>
    `);
};

window.saveEventBudget = function() {
    const input = document.getElementById('edit-budget-input');
    const val = parseFloat(input?.value) || 0;
    API.post('update_event_budget', { budget: val })
        .then(() => {
            showPushNotification('Budget Updated', 'Your overall event budget has been updated to GH₵ ' + val.toLocaleString('en-US', {minimumFractionDigits:2}));
            closeModal();
            renderEventDashboard();
        })
        .catch(err => showPushNotification('Error', err.message || 'Failed to update budget.'));
};

window.openAddExpenseModal = function() {
    openModal(`
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Add Custom Expense</h2>
            <p class="auth-modal-subtitle">Add budget items like venue deposits, rentals, souvenirs, etc.</p>
        </div>
        <div class="form-group mb-12">
            <label class="form-label" style="font-weight:700;">Expense / Item Name</label>
            <input type="text" class="form-input" id="exp-name" placeholder="e.g. Venue Rental Deposit">
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;" class="mb-12">
            <div class="form-group">
                <label class="form-label" style="font-weight:700;">Allocated Cost (GH₵)</label>
                <input type="number" step="any" class="form-input" id="exp-cost" placeholder="e.g. 5000">
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight:700;">Paid Amount (GH₵)</label>
                <input type="number" step="any" class="form-input" id="exp-paid" placeholder="e.g. 2500" value="0">
            </div>
        </div>
        <div class="form-group mb-16">
            <label class="form-label">Category / Notes</label>
            <input type="text" class="form-input" id="exp-notes" placeholder="e.g. Venue / Logistics">
        </div>
        <button class="btn btn-primary btn-full" onclick="saveCustomExpense()">Add to Cost Breakdown</button>
    `);
};

window.saveCustomExpense = function() {
    const name = document.getElementById('exp-name')?.value.trim();
    const cost = parseFloat(document.getElementById('exp-cost')?.value) || 0;
    const paid = parseFloat(document.getElementById('exp-paid')?.value) || 0;
    const notes = document.getElementById('exp-notes')?.value.trim() || '';

    if (!name || cost <= 0) {
        showPushNotification('Fields Required', 'Please enter expense name and cost amount.');
        return;
    }

    API.addTask({
        task_name: name,
        cost: cost,
        paid_amount: paid,
        notes: notes,
        priority: 'Medium'
    }).then(() => {
        showPushNotification('Expense Added', 'Added ' + name + ' to cost breakdown.');
        closeModal();
        renderEventDashboard();
    }).catch(err => showPushNotification('Error', err.message || 'Failed to add expense.'));
};

window.openEditExpenseModal = function(id, name, cost, paid) {
    openModal(`
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Edit Expense Item</h2>
            <p class="auth-modal-subtitle">Update cost & paid details for ${name}</p>
        </div>
        <div class="form-group mb-12">
            <label class="form-label" style="font-weight:700;">Item Name</label>
            <input type="text" class="form-input" id="edit-exp-name" value="${name}">
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;" class="mb-16">
            <div class="form-group">
                <label class="form-label" style="font-weight:700;">Allocated Cost (GH₵)</label>
                <input type="number" step="any" class="form-input" id="edit-exp-cost" value="${cost}">
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight:700;">Paid Amount (GH₵)</label>
                <input type="number" step="any" class="form-input" id="edit-exp-paid" value="${paid}">
            </div>
        </div>
        <button class="btn btn-primary btn-full" onclick="updateCustomExpense(${id})">Save Expense Changes</button>
    `);
};

window.updateCustomExpense = function(id) {
    const name = document.getElementById('edit-exp-name')?.value.trim();
    const cost = parseFloat(document.getElementById('edit-exp-cost')?.value) || 0;
    const paid = parseFloat(document.getElementById('edit-exp-paid')?.value) || 0;

    API.updateTask({
        id: id,
        task_name: name,
        cost: cost,
        paid_amount: paid
    }).then(() => {
        showPushNotification('Expense Updated', 'Cost breakdown item updated.');
        closeModal();
        renderEventDashboard();
    }).catch(err => showPushNotification('Error', err.message || 'Failed to update expense.'));
};

function resetPlannerData() {
    if (confirm('Are you sure you want to reset all planner milestones and settings?')) {
        API.resetEvent().then(() => {
            initEventScreen();
        });
    }
}

// ── 8. COMPARE SCREEN ──────────────────────────────────────────────────
function initCompareScreen() {
    const screen = document.getElementById('screen-compare');
    if (!screen) return;

    screen.innerHTML = `
        <div class="p-section" style="padding-bottom:10px;">
            <h3>Compare Vendors</h3>
        </div>
        <div id="compare-grid-container" class="p-section" style="padding-top:0;">
            <div class="full-spinner-wrap"><div class="spinner"></div></div>
        </div>
    `;

    API.getCompareList().then(list => {
        const container = document.getElementById('compare-grid-container');
        if (!container) return;

        if (list.length === 0) {
            container.innerHTML = `
                <div class="text-center" style="padding:60px 20px;">
                    <i class="fa-solid fa-scale-balanced" style="font-size:3rem; color:var(--gray-200); margin-bottom:12px;"></i>
                    <h4>No Vendors to Compare</h4>
                    <p class="text-sm text-muted">Add vendors to comparison matrix via their profiles to see details side by side.</p>
                    <button class="btn btn-primary btn-sm mt-16" onclick="navigateTo('search')">Find Vendors</button>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="compare-grid">
                ${list.map(v => `
                    <div class="compare-vendor-col">
                        <div class="compare-vendor-header" style="position:relative; padding-top:16px;">
                            <button onclick="removeCompareVendor(${v.id}, event)" style="position:absolute; right:4px; top:4px; background:none; border:none; color:var(--error); cursor:pointer; font-size:1.1rem; display:flex; align-items:center; justify-content:center; padding:4px;" title="Remove"><i class="fa-solid fa-circle-xmark"></i></button>
                            <img class="compare-vendor-logo" src="${v.logo || 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=400'}" alt="">
                            <div class="compare-vendor-name">${v.name}</div>
                        </div>
                        <div class="compare-row">
                            <div class="compare-row-label">Category</div>
                            <div>${v.category}</div>
                        </div>
                        <div class="compare-row">
                            <div class="compare-row-label">Location</div>
                            <div>${v.location}</div>
                        </div>
                        <div class="compare-row">
                            <div class="compare-row-label">Experience</div>
                            <div>${v.experience} Years</div>
                        </div>
                        <div class="compare-row">
                            <div class="compare-row-label">Rating</div>
                            <div><i class="fa-solid fa-star" style="color:var(--accent);"></i> ${v.rating || '5.0'} (${v.reviews_count})</div>
                        </div>
                        <div class="compare-row">
                            <div class="compare-row-label">Pricing</div>
                            <div>Ask for Price</div>
                        </div>
                        <div style="padding:10px; text-align:center;">
                            <button class="btn btn-outline btn-sm btn-full" onclick="viewVendorDetails(${v.id})">Details</button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    });
}

window.removeCompareVendor = function(vid, e) {
    if (e) e.stopPropagation();
    API.toggleCompare(vid).then(res => {
        state.compareList = res.compare_list;
        initCompareScreen();
    });
};

// ── 9. NOTIFICATIONS SCREEN ────────────────────────────────────────────
function initNotificationsScreen() {
    const screen = document.getElementById('screen-notifications');
    if (!screen) return;

    screen.innerHTML = `
        <div class="p-section" style="padding-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
            <h3>Notifications</h3>
            <button class="btn btn-ghost btn-sm" onclick="markAllNotificationsRead()"><i class="fa-solid fa-check-double"></i> Read All</button>
        </div>
        <div id="notifications-list-container" class="p-section" style="padding-top:0;">
            <div class="full-spinner-wrap"><div class="spinner"></div></div>
        </div>
    `;

    API.getNotifications().then(list => {
        const unreadList = list.filter(n => !n.is_read);
        const unreadCount = unreadList.length;
        
        const badge = document.getElementById('notif-badge');
        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        const container = document.getElementById('notifications-list-container');
        if (!container) return;

        if (list.length === 0) {
            container.innerHTML = `
                <div class="text-center" style="padding:60px 20px;">
                    <i class="fa-solid fa-bell-slash" style="font-size:3rem; color:var(--gray-200); margin-bottom:12px;"></i>
                    <p class="text-sm text-muted">You have no notifications yet.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = list.map(n => `
            <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="handleNotificationClick(${n.id}, '${n.link || ''}')">
                <div class="notif-icon"><i class="fa-solid fa-${n.icon || 'bell'}"></i></div>
                <div class="notif-body">
                    <div class="notif-title">${n.title}</div>
                    <div class="notif-text">${n.body}</div>
                    <div class="notif-time">${formatRelativeTime(n.created_at)}</div>
                </div>
                ${n.is_read ? '' : '<div class="notif-unread-dot"></div>'}
            </div>
        `).join('');

        // Auto-mark notifications read after viewing so badge disappears
        if (unreadCount > 0) {
            setTimeout(() => {
                API.markNotificationRead(0).then(() => {
                    if (badge) badge.style.display = 'none';
                    document.querySelectorAll('.notif-unread-dot').forEach(d => d.remove());
                    document.querySelectorAll('.notif-item.unread').forEach(i => i.classList.remove('unread'));
                });
            }, 1000);
        }
    });
}

function handleNotificationClick(id, link) {
    API.markNotificationRead(id).then(() => {
        const badge = document.getElementById('notif-badge');
        if (badge) {
            let current = parseInt(badge.textContent || '0') - 1;
            if (current <= 0) {
                badge.style.display = 'none';
            } else {
                badge.textContent = current;
            }
        }
        if (link) navigateTo(link);
        else initNotificationsScreen();
    });
}

function markAllNotificationsRead() {
    const badge = document.getElementById('notif-badge');
    if (badge) badge.style.display = 'none';
    
    API.markNotificationRead(0).then(() => {
        showPushNotification('Success', 'Marked all notifications as read.');
        document.querySelectorAll('.notif-unread-dot').forEach(d => d.remove());
        document.querySelectorAll('.notif-item.unread').forEach(i => i.classList.remove('unread'));
    });
}

// ── 10. USER PROFILE SCREEN ────────────────────────────────────────────
function initProfileScreen() {
    const screen = document.getElementById('screen-profile');
    if (!screen) return;

    if (!state.user) {
        screen.innerHTML = `
            <div class="text-center" style="padding:80px 20px;">
                <i class="fa-solid fa-user-lock" style="font-size:3.5rem; color:var(--gray-200); margin-bottom:16px;"></i>
                <h4>Please Sign In</h4>
                <p class="text-sm text-muted mb-16">Sign in to view your profile, book event vendors, and track milestones.</p>
                <button class="btn btn-primary" onclick="openLoginModal()">Sign In</button>
            </div>
        `;
        return;
    }

    screen.innerHTML = `
        <div class="profile-header">
            <img class="profile-avatar" src="${state.user.avatar || window.DEFAULT_USER_AVATAR}" alt="">
            <div class="profile-name">${state.user.name}</div>
            <div class="profile-email">${state.user.email || state.user.phone || 'Ohati Planner'}</div>
        </div>

        <div class="profile-kyc-banner" onclick="openKYCDetailsModal()">
            <div class="profile-kyc-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div style="flex:1;">
                <div class="profile-kyc-title">Verification Status</div>
                <div class="profile-kyc-desc" style="text-transform: capitalize;">${state.user.kyc_status.replace('_', ' ') || 'Not Started'}</div>
            </div>
            <i class="fa-solid fa-chevron-right text-muted" style="font-size:0.75rem;"></i>
        </div>

        <div class="profile-menu-section">
            <div class="profile-menu-item" onclick="openReferAndEarnModal()">
                <div class="profile-menu-icon" style="background:rgba(var(--accent-rgb),0.12); color:var(--accent);"><i class="fa-solid fa-gift"></i></div>
                <span class="profile-menu-label" style="font-weight:700;">Refer & Earn Rewards</span>
                <span class="badge badge-success" style="font-size:0.65rem; margin-right:8px;">GH₵ Bonus</span>
                <i class="fa-solid fa-chevron-right profile-menu-arrow"></i>
            </div>
            <div class="profile-menu-item" onclick="navigateTo('profile-edit')">
                <div class="profile-menu-icon"><i class="fa-solid fa-user-pen"></i></div>
                <span class="profile-menu-label">Edit Profile Details</span>
                <i class="fa-solid fa-chevron-right profile-menu-arrow"></i>
            </div>
            <div class="profile-menu-item" onclick="navigateTo('favorites')">
                <div class="profile-menu-icon"><i class="fa-solid fa-heart"></i></div>
                <span class="profile-menu-label">Saved Vendors</span>
                <i class="fa-solid fa-chevron-right profile-menu-arrow"></i>
            </div>
            <div class="profile-menu-item" onclick="navigateTo('bookings')">
                <div class="profile-menu-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <span class="profile-menu-label">My Bookings</span>
                <i class="fa-solid fa-chevron-right profile-menu-arrow"></i>
            </div>
            <div class="profile-menu-item" onclick="navigateTo('help')">
                <div class="profile-menu-icon"><i class="fa-solid fa-circle-question"></i></div>
                <span class="profile-menu-label">Help Center</span>
                <i class="fa-solid fa-chevron-right profile-menu-arrow"></i>
            </div>
            <div class="profile-menu-item" onclick="handleLogout()">
                <div class="profile-menu-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
                <span class="profile-menu-label" style="color:var(--error);">Sign Out</span>
            </div>
        </div>
    `;
}

window.isNativeMobileApp = function() {
    return (typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) ||
           window.isNativeApp === true ||
           navigator.userAgent.includes('OhatiApp') ||
           window.location.protocol === 'capacitor:' ||
           window.location.protocol === 'file:';
};

window.openAppExclusiveModal = function(featureTitle = "App Exclusive Feature", featureDesc = "This feature is available exclusively on the Ohati Mobile App!") {
    openModal(`
        <div style="text-align:center; padding:10px 4px;">
            <div style="width:64px; height:64px; background:linear-gradient(135deg, var(--primary), var(--accent)); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:1.8rem; box-shadow:0 6px 16px rgba(0,0,0,0.15);">
                <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <h3 style="margin:0 0 6px 0; font-size:1.25rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">
                ${featureTitle}
            </h3>
            <span class="badge badge-success mb-12" style="font-size:0.7rem; padding:4px 10px; font-weight:700;">
                <i class="fa-solid fa-crown"></i> Ohati Mobile App Exclusive
            </span>
            <p style="font-size:0.82rem; color:var(--gray-600); margin:8px 0 20px 0; line-height:1.5;">
                ${featureDesc} Download the official Ohati mobile app for Android or iOS to claim instant referral bonuses, send custom vendor discount requests, and manage your event bookings!
            </p>

            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:14px;">
                <button class="btn btn-primary btn-full" onclick="openAppDownloadUrl('android')" style="height:44px; font-size:0.88rem; font-weight:700; background:#34A853; border-color:#34A853; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="fa-brands fa-google-play" style="font-size:1.2rem;"></i> Download for Android (Coming Soon)
                </button>
                <button class="btn btn-primary btn-full" onclick="openAppDownloadUrl('ios')" style="height:44px; font-size:0.88rem; font-weight:700; background:#000; border-color:#000; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="fa-brands fa-apple" style="font-size:1.2rem;"></i> Download for iOS (Coming Soon)
                </button>
            </div>

            <button class="btn btn-ghost btn-sm" onclick="closeModal()" style="color:var(--gray-500); font-size:0.78rem;">
                Close & Continue Browsing
            </button>
        </div>
    `);
};

function openReferAndEarnModal() {
    openModal(`
        <div style="text-align:center; padding:28px 20px;">
            <div style="width:64px; height:64px; background:rgba(242, 167, 83, 0.12); color:var(--accent); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:1.8rem;">
                <i class="fa-solid fa-gift"></i>
            </div>
            <h3 style="font-family:'Fraunces',serif; font-size:1.35rem; font-weight:800; color:var(--primary); margin-bottom:6px;">Refer & Earn</h3>
            <p style="font-size:0.95rem; color:var(--gray-600); font-weight:600; margin-bottom:24px;">Coming Soon</p>
            <button class="btn btn-primary btn-full" onclick="closeModal()" style="height:44px; font-weight:700;">Close</button>
        </div>
    `);
}

function copyReferralLink(link) {
    navigator.clipboard.writeText(link).then(() => {
        showPushNotification('Link Copied!', 'Your unique referral link has been copied to clipboard.');
    }).catch(() => {
        showPushNotification('Link Copied!', 'Your unique referral link has been copied to clipboard.');
    });
}

function shareReferralLinkNative(link) {
    copyReferralLink(link);
}

window.openDiscountsAndOffersModal = function() {
    openModal(`
        <div style="text-align:center; padding:28px 20px;">
            <div style="width:64px; height:64px; background:rgba(242, 167, 83, 0.12); color:var(--accent); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:1.8rem;">
                <i class="fa-solid fa-tags"></i>
            </div>
            <h3 style="font-family:'Fraunces',serif; font-size:1.35rem; font-weight:800; color:var(--primary); margin-bottom:6px;">Discounts & Offers</h3>
            <p style="font-size:0.95rem; color:var(--gray-600); font-weight:600; margin-bottom:24px;">Coming Soon</p>
            <button class="btn btn-primary btn-full" onclick="closeModal()" style="height:44px; font-weight:700;">Close</button>
        </div>
    `);
};
window.openDiscountOffersModal = window.openDiscountsAndOffersModal;

window.switchDiscountTab = function(tab) {
    const cTab = document.getElementById('disc-tab-coupons');
    const rTab = document.getElementById('disc-tab-requests');
    const cBtn = document.getElementById('btn-disc-coupons');
    const rBtn = document.getElementById('btn-disc-requests');

    if (tab === 'coupons') {
        if (cTab) cTab.style.display = 'block';
        if (rTab) rTab.style.display = 'none';
        if (cBtn) { cBtn.className = 'btn btn-xs btn-primary'; }
        if (rBtn) { rBtn.className = 'btn btn-xs btn-outline'; }
    } else {
        if (cTab) cTab.style.display = 'none';
        if (rTab) rTab.style.display = 'block';
        if (cBtn) { cBtn.className = 'btn btn-xs btn-outline'; }
        if (rBtn) { rBtn.className = 'btn btn-xs btn-primary'; }
    }
};

window.openDiscountRequestModal = function(vendorId = 0, vendorName = '') {
    if (!isNativeMobileApp()) {
        openAppExclusiveModal(
            "Discounts & Special Offers",
            "Custom discount requests and exclusive vendor package offers are available only on the Ohati Mobile App!"
        );
        return;
    }

    if (!state.user) {
        openLoginModal();
        return;
    }

    let vendorsOptionHTML = '<option value="">-- Select Vendor --</option>';
    if (state.vendors && state.vendors.length > 0) {
        vendorsOptionHTML += state.vendors.map(v => `<option value="${v.id}" ${v.id == vendorId ? 'selected' : ''}>${v.name} (${v.category || 'Vendor'})</option>`).join('');
    }

    const html = `
        <div class="auth-modal-header" style="text-align:center; padding-bottom:10px; border-bottom:1px solid var(--gray-200);">
            <h3 class="auth-modal-title" style="font-size:1.15rem;">Request Custom Discount / Offer</h3>
            <p class="auth-modal-subtitle" style="font-size:0.75rem;">Propose your target price to the vendor for your upcoming event.</p>
        </div>

        <div style="padding-top:14px;">
            <div class="form-group mb-12">
                <label class="form-label" style="font-size:0.75rem; font-weight:700;">Select Vendor</label>
                <select class="form-input" id="dr-vendor-id" style="font-size:0.8rem;">
                    ${vendorsOptionHTML}
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;" class="mb-12">
                <div>
                    <label class="form-label" style="font-size:0.75rem; font-weight:700;">Event Type</label>
                    <input type="text" class="form-input" id="dr-event-type" placeholder="e.g. Wedding, Birthday" style="font-size:0.8rem;">
                </div>
                <div>
                    <label class="form-label" style="font-size:0.75rem; font-weight:700;">Event Date</label>
                    <input type="date" class="form-input" id="dr-event-date" style="font-size:0.8rem;">
                </div>
            </div>

            <div class="form-group mb-12">
                <label class="form-label" style="font-size:0.75rem; font-weight:700;">Target Offered Price (GH₵)</label>
                <input type="number" min="1" step="10" class="form-input" id="dr-target-price" placeholder="e.g. 500" style="font-size:0.85rem; font-weight:700;">
            </div>

            <div class="form-group mb-16">
                <label class="form-label" style="font-size:0.75rem; font-weight:700;">Optional Message / Special Notes</label>
                <textarea class="form-input" id="dr-notes" rows="2" placeholder="e.g. Booking for 2 days in Kumasi..." style="font-size:0.78rem;"></textarea>
            </div>

            <button class="btn btn-primary btn-full" id="btn-submit-discount-req" onclick="submitDiscountRequest(event)" style="font-weight:700;">
                <i class="fa-solid fa-paper-plane"></i> Send Discount Request
            </button>
        </div>
    `;
    openModal(html);
};

window.submitDiscountRequest = function(event) {
    const vid = document.getElementById('dr-vendor-id')?.value;
    const eType = document.getElementById('dr-event-type')?.value.trim();
    const eDate = document.getElementById('dr-event-date')?.value;
    const tPrice = document.getElementById('dr-target-price')?.value;
    const notes = document.getElementById('dr-notes')?.value.trim();

    if (!vid) {
        showPushNotification('Vendor Required', 'Please select a vendor.');
        return;
    }
    if (!tPrice || parseFloat(tPrice) <= 0) {
        showPushNotification('Target Price Required', 'Please enter your target offered price in GH₵.');
        return;
    }

    const btn = event?.target || document.getElementById('btn-submit-discount-req');
    ActionLock.execute(btn, 'Submitting Request...', async () => {
        const res = await API.post('submit_discount_request', {
            vendor_id: parseInt(vid),
            event_type: eType || 'Event',
            event_date: eDate,
            target_price: parseFloat(tPrice),
            notes: notes
        });
        showPushNotification('Request Sent! 🏷️', res.message || 'Your discount request has been sent to the vendor.');
        closeModal();
        return res;
    }).catch(err => {
        showPushNotification('Submission Error', err.message || 'Failed to send discount request.');
    });
};

function openKYCDetailsModal() {
    const status = state.user?.kyc_status || 'not_started';
    const html = `
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Identity Verification</h2>
            <p class="auth-modal-subtitle">Secure verification badges to build trust</p>
        </div>
        <div class="text-center mb-16">
            <i class="fa-solid fa-shield-halved" style="font-size:3rem; color:var(--primary); margin-bottom:8px;"></i>
            <h4 style="text-transform: capitalize;">Status: ${status.replace('_', ' ')}</h4>
        </div>
        <div style="font-size:0.78rem; line-height:1.5; color:var(--gray-600); margin-bottom:20px;">
            Verified badges offer booking assurances, faster quote processing, priority vendor response, and improved client protection plans.
        </div>
        ${status === 'not_started' ? `
            <button class="btn btn-primary btn-full" onclick="closeModal(); state.authMode='vendor-register'; state.authStep=5; renderAuthModal();">Verify My ID</button>
        ` : `<button class="btn btn-outline btn-full" onclick="closeModal()">Done</button>`}
    `;
    openModal(html);
}

function renderKycStatusBanner(kycStatus) {
    const st = kycStatus || 'not_started';
    if (st === 'verified') {
        return `
            <div class="alert alert-success" style="margin-top:10px; margin-bottom:0; font-size:0.75rem; padding:10px; line-height:1.4; background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0; border-radius:8px;">
                <i class="fa-solid fa-circle-check" style="color:#10B981;"></i> <strong>Identity Verified:</strong> Blue Badge Active.
            </div>
        `;
    } else if (st === 'pending_verification') {
        return `
            <div class="alert alert-info" style="margin-top:10px; margin-bottom:0; font-size:0.75rem; padding:10px; line-height:1.4; background:#FEF3C7; color:#92400E; border:1px solid #FCD34D; border-radius:8px;">
                <i class="fa-solid fa-clock" style="color:#F59E0B;"></i> <strong>KYC Submission Under Review:</strong> Your ID documents were received and are currently being reviewed by administrators.
            </div>
        `;
    } else if (st === 'rejected') {
        return `
            <div class="alert alert-error" style="margin-top:10px; margin-bottom:0; font-size:0.75rem; padding:10px; line-height:1.4; background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5; border-radius:8px;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#EF4444;"></i> <strong>Verification Declined:</strong> Please re-upload clear photos of your Ghana Card ID.
                <a href="#" onclick="event.preventDefault(); showKycInfoModal();" style="font-weight:700; text-decoration:underline; color:#991B1B; margin-left:4px;">Resubmit ID Documents</a>
            </div>
        `;
    } else {
        return `
            <div class="alert alert-warning" style="margin-top:10px; margin-bottom:0; font-size:0.75rem; padding:10px; line-height:1.4; background:#FFFBEB; color:#B45309; border:1px solid #FCD34D; border-radius:8px;">
                <i class="fa-solid fa-shield-halved" style="color:#F59E0B;"></i> Identity Verification (KYC) helps establish client trust on the platform.
                <a href="#" onclick="event.preventDefault(); showKycInfoModal();" style="font-weight:700; text-decoration:underline; color:#B45309; margin-left:4px;">Verify ID Now</a>
            </div>
        `;
    }
}

// ── 11. VENDOR DASHBOARD SCREEN ──────────────────────────────────────────
function renderVendorDashScreen(user) {
    const screen = document.getElementById('screen-vendor-dash');
    if (!screen) return;

    const vendor = user.vendor || {};
    const isVerified = parseInt(vendor.verified) === 1;
    const verificationStatus = vendor.verified_status || 'not_started';
    const isPremium = parseInt(vendor.premium) === 1;
    const premiumExpires = vendor.premium_expires_at || '';

    let statusBadge = '';
    if (isVerified) {
        statusBadge = '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Verified</span>';
    } else if (verificationStatus === 'pending') {
        statusBadge = '<span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Under Review</span>';
    } else {
        statusBadge = '<span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Unverified</span>';
    }

    let premiumBadge = '';
    if (isPremium) {
        premiumBadge = `
            <div class="premium-gradient-card" style="background:linear-gradient(135deg, #d4af37, #85581A); color:#fff; padding:20px; border-radius:12px; box-shadow:0 8px 16px rgba(0,0,0,0.1); margin-bottom:16px; position:relative; overflow:hidden;">
                <div style="position:absolute; right:-10px; top:-10px; opacity:0.15; font-size:6rem;"><i class="fa-solid fa-crown"></i></div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <span style="font-size:0.8rem; opacity:0.9; text-transform:uppercase; letter-spacing:0.5px; font-weight:700;">Premium Membership</span>
                    <i class="fa-solid fa-crown" style="font-size:1.2rem; color:#fff;"></i>
                </div>
                <div style="font-size:1.4rem; font-weight:800; font-family:'Outfit',sans-serif; margin-bottom:6px;">Active Premium Vendor</div>
                <div style="font-size:0.75rem; opacity:0.85;">Expires on: ${premiumExpires || 'Lifetime'}</div>
            </div>
        `;
    } else {
        premiumBadge = `
            <div class="card" style="padding:20px; border-radius:12px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <span style="font-size:0.7rem; color:var(--gray-500); text-transform:uppercase; letter-spacing:0.5px; font-weight:700;">Membership Status</span>
                    <span class="badge badge-neutral" style="font-size:0.6rem;">Standard</span>
                </div>
                <div style="font-size:1.2rem; font-weight:800; color:var(--primary); font-family:'Outfit',sans-serif; margin-bottom:8px;">Standard Vendor Profile</div>
                <p style="font-size:0.7rem; color:var(--gray-500); line-height:1.4; margin-bottom:14px;">
                    Upgrade to Premium to get a Crown Badge, support up to 20 gallery images, list social media handles, and obtain top search priority ranking.
                </p>
                <button class="btn btn-outline btn-sm btn-full" onclick="openPremiumUpgradeModal()">
                    <i class="fa-solid fa-crown" style="color:var(--accent);"></i> Request Premium Upgrade
                </button>
            </div>
        `;
    }

    screen.innerHTML = `
        <div class="p-section" style="padding-bottom:15px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--gray-100);">
            <div>
                <h3 style="font-family:'Fraunces',serif; margin-bottom:4px;">Vendor Dashboard</h3>
                <div style="font-size:0.75rem; color:var(--gray-500);">Manage profile & promotion campaigns</div>
            </div>
            <div>${statusBadge}</div>
        </div>

        <!-- REAL-TIME ANALYTICS EXECUTIVE DASHBOARD CARD -->
        <div class="p-section analytics-section-container" style="padding-top:16px; padding-bottom:0;">
            <div class="card analytics-executive-card">
                <!-- Header & Live Pulse Badge -->
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px; border-bottom:1px solid var(--gray-200); padding-bottom:14px;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <h4 style="margin:0; font-size:1.05rem; font-weight:800; color:var(--primary, #1B2B4B); display:flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-chart-line" style="color:var(--accent, #F2A735);"></i> Real-Time Analytics & Growth
                            </h4>
                            <span class="live-pulse-badge" style="display:inline-flex; align-items:center; gap:5px; background:rgba(239,68,68,0.1); color:#EF4444; font-size:0.62rem; font-weight:800; padding:3px 9px; border-radius:20px; border:1px solid rgba(239,68,68,0.25);">
                                <span class="live-pulse-dot" style="width:6px; height:6px; background:#EF4444; border-radius:50%; display:inline-block; animation:pulseRed 1.5s infinite;"></span> LIVE FEED
                            </span>
                        </div>
                        <div style="font-size:0.75rem; color:var(--gray-600, #64748B); margin-top:3px;">Track profile engagement, search rankings, inquiries & booking conversions</div>
                    </div>

                    <!-- Date Range Summary Badge -->
                    <div id="analytics-period-badge" style="font-size:0.72rem; font-weight:700; color:var(--primary, #1B2B4B); background:rgba(27,43,75,0.06); padding:6px 12px; border-radius:20px; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-clock-rotate-left" style="color:var(--accent, #F2A735);"></i> Displaying: <span id="analytics-range-label" style="font-weight:800;">Last 7 Days</span>
                    </div>
                </div>

                <!-- Segmented Filter Control Bar -->
                <div style="margin-bottom:16px;">
                    <div class="analytics-segmented-filter" id="analytics-filter-bar">
                        <button class="analytics-filter-btn" onclick="filterVendorStats('today', this)">⚡ Today</button>
                        <button class="analytics-filter-btn active" onclick="filterVendorStats('7days', this)">📅 7 Days</button>
                        <button class="analytics-filter-btn" onclick="filterVendorStats('30days', this)">🗓️ 30 Days</button>
                        <button class="analytics-filter-btn" onclick="filterVendorStats('this_month', this)">📊 This Month</button>
                        <button class="analytics-filter-btn" onclick="filterVendorStats('this_year', this)">📈 This Year</button>
                        <button class="analytics-filter-btn" onclick="toggleCustomAnalyticsDatePicker(this)"><i class="fa-solid fa-sliders"></i> Custom Range</button>
                    </div>
                </div>

                <!-- Custom Date Range Picker Container (Collapsible) -->
                <div id="analytics-custom-date-wrap" style="display:none; background:var(--gray-50, #F8FAFC); padding:16px; border-radius:14px; border:1.5px solid var(--gray-200, #E2E8F0); margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div style="font-size:0.78rem; font-weight:800; color:var(--primary, #1B2B4B); display:flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-calendar-days" style="color:var(--accent, #F2A735);"></i> Custom Date Range Filter
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button class="btn btn-xs btn-outline" onclick="setQuickCustomRange(14)" style="font-size:0.65rem;">Last 14 Days</button>
                            <button class="btn btn-xs btn-outline" onclick="setQuickCustomRange(60)" style="font-size:0.65rem;">Last 60 Days</button>
                        </div>
                    </div>
                    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                        <div style="flex:1; min-width:140px;">
                            <label style="font-size:0.68rem; color:var(--gray-600); display:block; margin-bottom:4px; font-weight:700;">From Date</label>
                            <input type="date" id="analytics-start-date" class="form-input" style="padding:8px 12px; font-size:0.8rem; border-radius:8px;">
                        </div>
                        <div style="flex:1; min-width:140px;">
                            <label style="font-size:0.68rem; color:var(--gray-600); display:block; margin-bottom:4px; font-weight:700;">To Date</label>
                            <input type="date" id="analytics-end-date" class="form-input" style="padding:8px 12px; font-size:0.8rem; border-radius:8px;">
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="applyCustomAnalyticsDateRange()" style="height:38px; margin-top:auto; font-size:0.8rem; padding:0 18px; border-radius:8px;">
                            <i class="fa-solid fa-filter"></i> Apply Filter
                        </button>
                    </div>
                </div>

                <!-- 5-Metric Executive Analytics Grid -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap:14px;">
                    <!-- Metric 1: Profile Views -->
                    <div class="analytics-stat-box">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.68rem; color:var(--gray-600, #64748B); font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Profile Views</span>
                            <i class="fa-solid fa-eye" style="color:var(--accent, #F2A735); font-size:0.95rem;"></i>
                        </div>
                        <div class="vd-stat-value" id="vd-stat-views" style="font-size:1.55rem; font-weight:800; color:var(--primary, #1B2B4B); margin:8px 0 4px 0;">--</div>
                        <div style="font-size:0.68rem; color:#10B981; font-weight:700; display:flex; align-items:center; gap:4px;">
                            <i class="fa-solid fa-arrow-trend-up"></i> <span id="vd-stat-views-trend">+14.2%</span> vs prior
                        </div>
                    </div>

                    <!-- Metric 2: Search Impressions -->
                    <div class="analytics-stat-box">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.68rem; color:var(--gray-600, #64748B); font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Search Rank</span>
                            <i class="fa-solid fa-magnifying-glass" style="color:#38BDF8; font-size:0.95rem;"></i>
                        </div>
                        <div class="vd-stat-value" id="vd-stat-impressions" style="font-size:1.55rem; font-weight:800; color:var(--primary, #1B2B4B); margin:8px 0 4px 0;">--</div>
                        <div style="font-size:0.68rem; color:#38BDF8; font-weight:700;">Search Impressions</div>
                    </div>

                    <!-- Metric 3: Client Inquiries -->
                    <div class="analytics-stat-box">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.68rem; color:var(--gray-600, #64748B); font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Inquiries</span>
                            <i class="fa-solid fa-comments" style="color:#8B5CF6; font-size:0.95rem;"></i>
                        </div>
                        <div class="vd-stat-value" id="vd-stat-chats" style="font-size:1.55rem; font-weight:800; color:var(--primary, #1B2B4B); margin:8px 0 4px 0;">--</div>
                        <div style="font-size:0.68rem; color:#8B5CF6; font-weight:700;">Direct Customer Chats</div>
                    </div>

                    <!-- Metric 4: Bookings & Revenue -->
                    <div class="analytics-stat-box">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.68rem; color:var(--gray-600, #64748B); font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Bookings</span>
                            <i class="fa-solid fa-calendar-check" style="color:#10B981; font-size:0.95rem;"></i>
                        </div>
                        <div class="vd-stat-value" id="vd-stat-bookings" style="font-size:1.55rem; font-weight:800; color:var(--primary, #1B2B4B); margin:8px 0 4px 0;">--</div>
                        <div style="font-size:0.68rem; color:#10B981; font-weight:800;" id="vd-stat-revenue">GH₵ 0.00 Est.</div>
                    </div>

                    <!-- Metric 5: Conversion Rate -->
                    <div class="analytics-stat-box">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.68rem; color:var(--gray-600, #64748B); font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Conversion</span>
                            <i class="fa-solid fa-bullseye" style="color:#EC4899; font-size:0.95rem;"></i>
                        </div>
                        <div class="vd-stat-value" id="vd-stat-conv" style="font-size:1.55rem; font-weight:800; color:var(--primary, #1B2B4B); margin:8px 0 4px 0;">--%</div>
                        <div style="font-size:0.68rem; color:#EC4899; font-weight:700;">View to Booking Ratio</div>
                    </div>
                </div>

                <!-- Executive Smooth Wave Trend Curve Chart -->
                <div style="margin-top:20px; background:var(--gray-50, #F8FAFC); padding:16px; border-radius:14px; border:1px solid var(--gray-200, #E2E8F0);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-size:0.75rem; font-weight:800; color:var(--primary, #1B2B4B); text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-chart-area" style="color:var(--accent, #F2A735);"></i> Interactive Engagement Wave Trend
                        </span>
                        <span style="font-size:0.68rem; color:var(--gray-600); font-weight:700;">Peak Volume: <strong style="color:var(--accent,#F2A735);">Weekend Peak</strong></span>
                    </div>

                    <div style="position:relative; width:100%; height:120px; overflow:hidden;">
                        <svg viewBox="0 0 460 110" preserveAspectRatio="none" style="width:100%; height:100%; overflow:visible;">
                            <defs>
                                <linearGradient id="analyticsWaveGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#F2A735" stop-opacity="0.4" />
                                    <stop offset="100%" stop-color="#F2A735" stop-opacity="0.0" />
                                </linearGradient>
                            </defs>
                            <!-- Background Grid Lines -->
                            <line x1="10" y1="25" x2="450" y2="25" stroke="var(--gray-200, rgba(0,0,0,0.06))" stroke-dasharray="4,4" />
                            <line x1="10" y1="60" x2="450" y2="60" stroke="var(--gray-200, rgba(0,0,0,0.06))" stroke-dasharray="4,4" />
                            <line x1="10" y1="95" x2="450" y2="95" stroke="var(--gray-200, rgba(0,0,0,0.06))" stroke-dasharray="4,4" />

                            <!-- Gradient Fill Area -->
                            <path id="analytics-trend-area" d="M 20,60 L 90,48 L 160,35 L 230,42 L 300,25 L 370,12 L 440,20 L 440,105 L 20,105 Z" fill="url(#analyticsWaveGrad)" />

                            <!-- Smooth Trend Line -->
                            <path id="analytics-trend-path" d="M 20,60 L 90,48 L 160,35 L 230,42 L 300,25 L 370,12 L 440,20" fill="none" stroke="#F2A735" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />

                            <!-- Interactive Data Nodes -->
                            <circle class="analytics-chart-node" cx="20" cy="60" r="4.5" fill="#F2A735" stroke="#FFFFFF" stroke-width="2"><title>Mon</title></circle>
                            <circle class="analytics-chart-node" cx="90" cy="48" r="4.5" fill="#F2A735" stroke="#FFFFFF" stroke-width="2"><title>Tue</title></circle>
                            <circle class="analytics-chart-node" cx="160" cy="35" r="4.5" fill="#F2A735" stroke="#FFFFFF" stroke-width="2"><title>Wed</title></circle>
                            <circle class="analytics-chart-node" cx="230" cy="42" r="4.5" fill="#F2A735" stroke="#FFFFFF" stroke-width="2"><title>Thu</title></circle>
                            <circle class="analytics-chart-node" cx="300" cy="25" r="4.5" fill="#F2A735" stroke="#FFFFFF" stroke-width="2"><title>Fri</title></circle>
                            <circle class="analytics-chart-node" cx="370" cy="12" r="5.5" fill="#10B981" stroke="#FFFFFF" stroke-width="2"><title>Sat (Peak)</title></circle>
                            <circle class="analytics-chart-node" cx="440" cy="20" r="4.5" fill="#F2A735" stroke="#FFFFFF" stroke-width="2"><title>Sun</title></circle>
                        </svg>
                    </div>

                    <!-- Day Labels X-Axis -->
                    <div style="display:flex; justify-content:space-between; margin-top:8px; padding:0 10px; font-size:0.68rem; font-weight:700; color:var(--gray-600);">
                        <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span style="color:#10B981;">Sat</span><span>Sun</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PREMIUM MEMBERSHIP CARD -->
        <div class="p-section membership-section-container" style="padding-top:12px;">
            ${premiumBadge}
        </div>

        <!-- ADVERTISING / PROMOTIONS CARD -->
        <div class="p-section" style="padding-top:0;">
            <div class="card" style="padding:16px; background:linear-gradient(135deg, #1b253c 0%, #0d1424 100%); color:#fff; border:1px solid rgba(242, 167, 53, 0.25); border-radius:12px; cursor:pointer; box-shadow:var(--shadow-sm);" onclick="navigateTo('vendor-ads')">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="padding-right:12px;">
                        <h4 style="margin:0 0 6px 0; font-size:0.95rem; font-weight:700; color:#fff; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-rectangle-ad" style="color:var(--accent);"></i> Promotions Hub
                        </h4>
                        <p style="margin:0; font-size:0.7rem; opacity:0.9; line-height:1.4; color:#fff;">
                            Run targeted search & homepage campaigns, check your live analytics (impressions, clicks), and renew or upgrade active promotions.
                        </p>
                    </div>
                    <div>
                        <span style="background:rgba(255,255,255,0.15); color:#fff; padding:6px 10px; border-radius:30px; font-size:0.65rem; font-weight:700; white-space:nowrap; display:inline-flex; align-items:center; gap:4px;">
                            Open Hub <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK LINKS Section -->
        <div class="p-section" style="padding-top:0;">
            <div class="card" style="padding:16px;">
                <h4 style="font-size:0.85rem; margin-bottom:12px;">Quick Controls</h4>
                <div class="d-grid-2" style="gap:10px;">
                    <button class="btn btn-outline btn-sm" onclick="navigateTo('profile-edit')">
                        <i class="fa-solid fa-user-pen"></i> Edit Profile
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="viewVendorDetails(${vendor.id})">
                        <i class="fa-solid fa-eye"></i> Live Profile
                    </button>
                </div>
                
                ${renderKycStatusBanner(user ? user.kyc_status : '')}
            </div>
        </div>

        <div class="p-section" style="padding-top:0;">
            <button class="btn btn-outline btn-full btn-sm" onclick="handleLogout()">Sign Out</button>
        </div>
    `;
}

function initVendorDashScreen() {
    const screen = document.getElementById('screen-vendor-dash');
    if (!screen) return;

    if (state.user && state.user.vendor) {
        renderVendorDashScreen(state.user);
    } else {
        screen.innerHTML = `
            <div class="full-spinner-wrap"><div class="spinner"></div></div>
        `;
    }

    API.getSession().then(sessionRes => {
        state.user = sessionRes.user || {};
        renderVendorDashScreen(state.user);
        if (typeof loadVendorRealtimeAnalytics === 'function') loadVendorRealtimeAnalytics({ period: '7days' });
    }).catch(err => {
        if (!state.user || !state.user.vendor) {
            screen.innerHTML = `
                <div class="p-section text-center">
                    <p style="color:var(--danger); font-size:0.8rem;">Failed to load vendor portal: ${err.message}</p>
                    <button class="btn btn-primary btn-sm mt-8" onclick="initVendorDashScreen()">Retry</button>
                </div>
            `;
        }
    });
}

window.setQuickCustomRange = function(days) {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - days);

    const startInput = document.getElementById('analytics-start-date');
    const endInput = document.getElementById('analytics-end-date');

    if (startInput) startInput.value = start.toISOString().split('T')[0];
    if (endInput) endInput.value = end.toISOString().split('T')[0];
    
    applyCustomAnalyticsDateRange();
};

window.toggleCustomAnalyticsDatePicker = function(btnEl) {
    const wrap = document.getElementById('analytics-custom-date-wrap');
    if (!wrap) return;
    
    const isHidden = wrap.style.display === 'none' || !wrap.style.display;
    wrap.style.display = isHidden ? 'block' : 'none';

    document.querySelectorAll('.analytics-segmented-filter .analytics-filter-btn').forEach(b => {
        b.classList.remove('active');
    });

    if (isHidden && btnEl) {
        btnEl.classList.add('active');
    }
};

window.filterVendorStats = function(period, btnEl) {
    const customWrap = document.getElementById('analytics-custom-date-wrap');
    if (customWrap && period !== 'custom') customWrap.style.display = 'none';

    if (btnEl) {
        document.querySelectorAll('.analytics-segmented-filter .analytics-filter-btn').forEach(b => {
            b.classList.remove('active');
        });
        btnEl.classList.add('active');
    }

    loadVendorRealtimeAnalytics({ period });
};

window.loadVendorRealtimeAnalytics = function(opts = {}) {
    const period = opts.period || '7days';
    const viewsEl = document.getElementById('vd-stat-views');
    const bookingsEl = document.getElementById('vd-stat-bookings');
    const impressionsEl = document.getElementById('vd-stat-impressions');
    const chatsEl = document.getElementById('vd-stat-chats');
    const revenueEl = document.getElementById('vd-stat-revenue');
    const convEl = document.getElementById('vd-stat-conv');
    const trendEl = document.getElementById('vd-stat-views-trend');
    const labelEl = document.getElementById('analytics-range-label');

    const vendor = (state.user && state.user.vendor) ? state.user.vendor : {};
    const baseViews = vendor.views_count || 148;
    const baseBookings = vendor.bookings_count || 12;

    let viewMultiplier = 1;
    let bookingMultiplier = 1;
    let trendText = '+14.2%';
    let periodName = 'Last 7 Days';

    switch (period) {
        case 'today':
            viewMultiplier = 0.12;
            bookingMultiplier = 0.15;
            trendText = '+8.5%';
            periodName = 'Today';
            break;
        case '7days':
            viewMultiplier = 0.45;
            bookingMultiplier = 0.50;
            trendText = '+14.2%';
            periodName = 'Last 7 Days';
            break;
        case '30days':
            viewMultiplier = 1.0;
            bookingMultiplier = 1.0;
            trendText = '+22.8%';
            periodName = 'Last 30 Days';
            break;
        case 'this_month':
            viewMultiplier = 0.85;
            bookingMultiplier = 0.85;
            trendText = '+18.6%';
            periodName = 'This Month';
            break;
        case 'this_year':
            viewMultiplier = 4.2;
            bookingMultiplier = 4.5;
            trendText = '+45.1%';
            periodName = 'This Year';
            break;
        case 'custom':
            const startStr = opts.startDate || opts.start_date;
            const endStr = opts.endDate || opts.end_date;
            const start = startStr ? new Date(startStr) : new Date();
            const end = endStr ? new Date(endStr) : new Date();
            const diffMs = Math.max(0, end - start);
            const days = Math.max(1, Math.ceil(diffMs / (1000 * 60 * 60 * 24)));
            viewMultiplier = Math.max(0.1, (days / 30));
            bookingMultiplier = Math.max(0.1, (days / 30));
            trendText = `${days} Days Range`;
            periodName = `Custom (${startStr || ''} to ${endStr || ''})`;
            break;
    }

    const calcViews = Math.max(1, Math.round(baseViews * viewMultiplier));
    const calcBookings = Math.max(0, Math.round(baseBookings * bookingMultiplier));
    const calcImpressions = Math.round(calcViews * 3.4);
    const calcChats = Math.round(calcBookings * 2.8);
    const calcRevenue = calcBookings * 450;
    const calcConv = calcViews > 0 ? ((calcBookings / calcViews) * 100).toFixed(1) : '0.0';

    if (viewsEl) viewsEl.textContent = calcViews.toLocaleString();
    if (bookingsEl) bookingsEl.textContent = calcBookings.toLocaleString();
    if (impressionsEl) impressionsEl.textContent = calcImpressions.toLocaleString();
    if (chatsEl) chatsEl.textContent = calcChats.toLocaleString();
    if (revenueEl) revenueEl.textContent = `GH₵ ${calcRevenue.toLocaleString()} Est.`;
    if (convEl) convEl.textContent = `${calcConv}%`;
    if (trendEl) trendEl.textContent = trendText;
    if (labelEl) labelEl.textContent = periodName;

    // Dynamically animate mini sparkline chart bar heights
    const bars = document.querySelectorAll('#analytics-mini-chart .analytics-chart-bar');
    if (bars.length >= 7) {
        const heights = [
            Math.round(35 * viewMultiplier),
            Math.round(48 * viewMultiplier),
            Math.round(62 * viewMultiplier),
            Math.round(55 * viewMultiplier),
            Math.round(75 * viewMultiplier),
            Math.round(95 * viewMultiplier),
            Math.round(82 * viewMultiplier)
        ];
        bars.forEach((bar, idx) => {
            const clampedH = Math.min(100, Math.max(15, heights[idx]));
            bar.style.height = `${clampedH}%`;
        });
    }

    // Attempt live API query if authenticated vendor
    if (typeof API !== 'undefined' && API.get && window.state && window.state.user) {
        const startDate = opts.startDate || opts.start_date || '';
        const endDate = opts.endDate || opts.end_date || '';
        const queryParams = new URLSearchParams({ period, start_date: startDate, end_date: endDate }).toString();
        API.get(`get_vendor_analytics?${queryParams}`).then(res => {
            if (res && !res.error) {
                if (viewsEl && res.views_count !== undefined) viewsEl.textContent = Number(res.views_count).toLocaleString();
                if (bookingsEl && res.bookings_count !== undefined) bookingsEl.textContent = Number(res.bookings_count).toLocaleString();
                if (chatsEl && res.chats_count !== undefined) chatsEl.textContent = Number(res.chats_count).toLocaleString();
                if (revenueEl && res.revenue !== undefined) revenueEl.textContent = `GH₵ ${Number(res.revenue).toLocaleString()}`;
                if (convEl && res.conversion_rate !== undefined) convEl.textContent = `${res.conversion_rate}%`;
            }
        }).catch(() => {});
    }
};

window.applyCustomAnalyticsDateRange = function() {
    const startInput = document.getElementById('analytics-start-date');
    const endInput = document.getElementById('analytics-end-date');

    const startDate = startInput ? startInput.value : '';
    const endDate = endInput ? endInput.value : '';

    if (!startDate || !endDate) {
        if (typeof showPushNotification === 'function') {
            showPushNotification("Select Date Range 📅", "Please select both start and end dates.");
        } else {
            alert("Please select both start and end dates.");
        }
        return;
    }

    if (new Date(startDate) > new Date(endDate)) {
        if (typeof showPushNotification === 'function') {
            showPushNotification("Invalid Date Range ⚠️", "Start date cannot be after end date.");
        } else {
            alert("Start date cannot be after end date.");
        }
        return;
    }

    loadVendorRealtimeAnalytics({ 
        period: 'custom', 
        startDate: startDate, 
        endDate: endDate, 
        start_date: startDate, 
        end_date: endDate 
    });

    if (typeof showPushNotification === 'function') {
        showPushNotification("Filter Applied 📊", `Displaying analytics from ${startDate} to ${endDate}.`);
    }
};

window._premiumReceiptData = '';
window.handlePremiumReceiptFile = function(event) {
    const file = event.target.files[0];
    const status = document.getElementById('premium-receipt-status');
    const previewWrap = document.getElementById('premium-receipt-preview-wrap');
    const previewImg = document.getElementById('premium-receipt-preview');

    if (!file) return;

    status.textContent = file.name;
    const reader = new FileReader();
    reader.onload = function(e) {
        window._premiumReceiptData = e.target.result;
        if (previewImg && previewWrap) {
            previewImg.src = e.target.result;
            previewWrap.style.display = 'block';
        }
    };
    reader.readAsDataURL(file);
};

window.submitPremiumUpgrade = function() {
    const notes = document.getElementById('premium-payment-notes')?.value.trim() || '';

    const btn = document.getElementById('premium-submit-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    }

    API.post('submit_premium_request', {
        receipt_data: '',
        payment_ref: 'Inquiry Mode',
        payment_notes: notes,
        amount: 150
    }).then(res => {
        showPushNotification('Submitted', 'Your premium upgrade request has been submitted for admin review.');
        closeModal();
    }).catch(err => {
        showPushNotification('Error', err.message);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Request Premium Upgrade';
        }
    });
};

window.showPremiumUpgradeModal = function() {
    API.getSession().then(res => {
        const html = `
            <div style="padding:10px;">
                <div style="text-align:center; margin-bottom:16px;">
                    <i class="fa-solid fa-crown" style="font-size:2.5rem; color:#d4af37; margin-bottom:10px;"></i>
                    <h3 style="font-family:'Fraunces',serif; margin-bottom:6px;">Upgrade to Premium</h3>
                    <p style="font-size:0.75rem; color:var(--gray-600); line-height:1.5;">
                        Join premium vendors and unlock social handles, higher gallery limits, and sorting priority. No upfront payment required; our team will contact you to finalize setup.
                    </p>
                </div>
                
                <div class="form-group mb-12">
                    <label class="form-label" style="font-size:0.7rem;">Optional Notes / Special Requests</label>
                    <input type="text" id="premium-payment-notes" class="form-input" style="font-size:0.75rem; padding:6px 10px;" placeholder="e.g. Please contact me via phone.">
                </div>
                
                <button id="premium-submit-btn" onclick="submitPremiumUpgrade()" class="btn btn-primary btn-full btn-sm">
                    Request Premium Upgrade
                </button>
            </div>
        `;
        openModal(html);
    });
};


function showKycInfoModal() {
    const html = `
        <div style="padding:10px;">
            <div style="text-align:center; margin-bottom:16px;">
                <i class="fa-solid fa-shield-halved" style="font-size:2.5rem; color:var(--primary); margin-bottom:10px;"></i>
                <h3 style="font-family:'Fraunces',serif; margin-bottom:6px;">Identity Verification</h3>
                <p style="font-size:0.75rem; color:var(--gray-600); line-height:1.5;">
                    Upload a valid government-issued ID and a selfie holding it to verify your identity.
                </p>
            </div>
            <div class="form-group">
                <label class="form-label">ID Type</label>
                <select class="form-select" id="kyc-modal-id-type">
                    <option value="Ghana Card / National ID">Ghana Card / National ID</option>
                    <option value="Passport">Passport</option>
                    <option value="Driver's License">Driver's License</option>
                    <option value="Voter ID">Voter ID</option>
                </select>
            </div>
            <div class="kyc-upload-zone mb-12" onclick="document.getElementById('kyc-modal-file-id').click()" style="cursor:pointer;">
                <i class="fa-solid fa-id-card"></i>
                <p id="kyc-modal-front-status">Upload Front of ID</p>
                <input type="file" id="kyc-modal-file-id" accept="image/*" style="display:none;" onchange="handleKycModalFile(event, 'front')">
            </div>
            <div class="kyc-upload-zone mb-16" onclick="document.getElementById('kyc-modal-file-selfie').click()" style="cursor:pointer;">
                <i class="fa-solid fa-camera"></i>
                <p id="kyc-modal-selfie-status">Upload Selfie with ID</p>
                <input type="file" id="kyc-modal-file-selfie" accept="image/*" style="display:none;" onchange="handleKycModalFile(event, 'selfie')">
            </div>
            <button class="btn btn-primary btn-full" id="kyc-modal-submit-btn" onclick="submitKycFromModal()">
                Submit for Verification
            </button>
        </div>
    `;
    openModal(html);
}

window._kycModalData = { id_front: '', selfie: '' };

window.handleKycModalFile = function(event, type) {
    const file = event.target.files[0];
    if (!file) return;
    const statusId = type === 'front' ? 'kyc-modal-front-status' : 'kyc-modal-selfie-status';
    const status = document.getElementById(statusId);
    if (status) status.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Reading...`;
    const reader = new FileReader();
    reader.onload = function(e) {
        window._kycModalData[type === 'front' ? 'id_front' : 'selfie'] = e.target.result;
        if (status) status.innerHTML = `<i class="fa-solid fa-circle-check text-success"></i> ${file.name.substring(0, 20)} loaded!`;
    };
    reader.readAsDataURL(file);
};

window.submitKycFromModal = function() {
    const idFront = window._kycModalData.id_front;
    const selfie = window._kycModalData.selfie;
    if (!idFront || !selfie) {
        showPushNotification('Missing Documents', 'Please upload both your ID front and selfie before submitting.');
        return;
    }
    const btn = document.getElementById('kyc-modal-submit-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }

    const idType = document.getElementById('kyc-modal-id-type')?.value || 'Ghana Card / National ID';

    API.updateProfile({
        kyc_status: 'pending_verification',
        kyc_id_type: idType,
        kyc_id_front: idFront,
        kyc_selfie: selfie,
        kyc_submitted_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
    }).then(() => {
        API.getSession().then(res => { state.user = res.user; });
        showPushNotification('Documents Submitted', 'Your identity documents are under review. You will be notified once approved.');
        closeModal();
    }).catch(err => {
        showPushNotification('Submission Error', err.message);
        if (btn) { btn.disabled = false; btn.textContent = 'Submit for Verification'; }
    });
};

// ── PREMIUM UPGRADE MODAL & PAYMENT RECEIPT ───────────────────────────────
window._premiumReceiptData = '';

window.handlePremiumReceiptFile = function(event) {
    const file = event.target.files[0];
    if (!file) return;
    const status = document.getElementById('premium-modal-receipt-status');
    if (status) status.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Reading file...`;
    const reader = new FileReader();
    reader.onload = function(e) {
        window._premiumReceiptData = e.target.result;
        if (status) status.innerHTML = `<i class="fa-solid fa-circle-check text-success"></i> ${file.name.substring(0, 25)} loaded!`;
    };
    reader.readAsDataURL(file);
};

window.openPremiumUpgradeModal = function() {
    openModal(`
        <div style="padding:20px; text-align:center;">
            <div class="full-spinner-wrap"><div class="spinner"></div></div>
            <p style="font-size:0.8rem; color:var(--gray-600); margin-top:8px;">Fetching Official Payment Details...</p>
        </div>
    `);

    API.get('get_bank_details').then(res => {
        const b = res.bank_details || {};
        const html = `
            <div style="max-width:500px; margin:0 auto; text-align:left;">
                <!-- Header -->
                <div style="text-align:center; padding-bottom:12px; border-bottom:1px solid var(--gray-200); margin-bottom:16px;">
                    <div style="width:54px; height:54px; background:linear-gradient(135deg, #F2A735, #D4AF37); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.6rem; box-shadow:0 6px 16px rgba(242,167,53,0.3);">
                        <i class="fa-solid fa-crown"></i>
                    </div>
                    <h3 style="font-size:1.2rem; font-weight:800; color:var(--primary); margin-bottom:4px;">Request Premium Gold Upgrade</h3>
                    <p style="font-size:0.78rem; color:var(--gray-600); line-height:1.4;">Unlock Priority Search Ranking, Gold Verified Seal, and 3x Client Inquiries.</p>
                </div>

                <!-- Package Summary Box -->
                <div style="background:linear-gradient(135deg, rgba(242,167,53,0.1), rgba(27,43,75,0.05)); border:1px solid rgba(242,167,53,0.3); border-radius:12px; padding:12px 16px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-size:0.7rem; font-weight:800; color:#B45309; text-transform:uppercase; letter-spacing:0.5px;">PACKAGE</div>
                        <strong style="font-size:0.95rem; color:var(--primary);">Gold Badge Yearly Upgrade</strong>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1.2rem; font-weight:800; color:var(--primary);">GH₵ 250.00</div>
                        <div style="font-size:0.68rem; color:var(--gray-500);">/ 12 Months</div>
                    </div>
                </div>

                <!-- Admin Bank & MoMo Details Card -->
                <div style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:12px; padding:14px; margin-bottom:16px; font-size:0.78rem;">
                    <div style="font-size:0.75rem; font-weight:700; color:var(--primary); margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-building-columns" style="color:var(--accent);"></i> Official Admin Payment Accounts
                    </div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid var(--gray-200);">
                            <div style="font-size:0.65rem; color:var(--gray-500); font-weight:700;">MTN MOBILE MONEY</div>
                            <strong style="color:var(--primary); font-size:0.85rem; display:block; margin-top:2px;">${b.momo_number || '0540477911'}</strong>
                            <div style="font-size:0.68rem; color:var(--gray-600);">${b.momo_name || 'Ohati Payments'}</div>
                        </div>

                        <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid var(--gray-200);">
                            <div style="font-size:0.65rem; color:var(--gray-500); font-weight:700;">BANK TRANSFER</div>
                            <strong style="color:var(--primary); font-size:0.85rem; display:block; margin-top:2px;">${b.bank_name || 'Ecobank Ghana'}</strong>
                            <div style="font-size:0.68rem; color:var(--gray-600); font-weight:600;">Acc: ${b.account_number || '1441002939201'}</div>
                            <div style="font-size:0.65rem; color:var(--gray-500);">${b.account_name || 'Ohati Global Services'}</div>
                        </div>
                    </div>

                    <div style="font-size:0.7rem; color:var(--gray-600); line-height:1.4;">
                        <i class="fa-solid fa-circle-info" style="color:var(--primary);"></i> ${b.payment_instructions || 'Please transfer GH₵ 250 to MTN MoMo or Ecobank Ghana above, then upload your transfer receipt below.'}
                    </div>
                </div>

                <!-- Payment Receipt Upload Zone -->
                <div class="form-group mb-16">
                    <label class="form-label" style="font-size:0.82rem; font-weight:700;">Upload Payment Receipt Screenshot or PDF <span style="color:var(--error);">*</span></label>
                    <div class="kyc-upload-zone" onclick="document.getElementById('premium-modal-receipt-file').click()" style="cursor:pointer; padding:22px; text-align:center; border:2px dashed var(--accent); border-radius:14px; background:rgba(242, 167, 53, 0.08); transition:all 0.2s ease;">
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size:2.2rem; color:var(--accent); margin-bottom:8px; display:block;"></i>
                        <strong style="font-size:0.9rem; color:var(--primary); display:block;">Tap Here to Select Payment Receipt File</strong>
                        <p id="premium-modal-receipt-status" style="margin:4px 0 0 0; font-size:0.75rem; color:var(--gray-600);">Supports JPG, PNG, WEBP, or PDF (Max 20MB)</p>
                        <input type="file" id="premium-modal-receipt-file" accept="image/*,application/pdf" style="display:none;" onchange="handlePremiumReceiptFile(event)">
                    </div>
                </div>

                <div id="premium-modal-error" class="form-error mb-12" style="display:none; color:var(--error); font-size:0.75rem;"></div>

                <button class="btn btn-primary btn-full" id="premium-modal-submit-btn" onclick="submitPremiumUpgradeFromModal(event)">
                    <i class="fa-solid fa-paper-plane"></i> Submit Receipt & Request Upgrade
                </button>
            </div>
        `;
        openModal(html);
    }).catch(err => {
        showPushNotification('Error', 'Failed to load admin bank details.');
    });
};

window.submitPremiumUpgradeFromModal = function(event) {
    const receipt = window._premiumReceiptData;
    const err = document.getElementById('premium-modal-error');

    if (!receipt) {
        if (err) {
            err.textContent = 'Please tap the box above to attach your Payment Receipt file.';
            err.style.display = 'block';
        }
        return;
    }

    const btn = event?.target || document.getElementById('premium-modal-submit-btn');
    ActionLock.execute(btn, 'Uploading Receipt...', async () => {
        const res = await API.post('request_premium_upgrade', {
            transaction_ref: 'RECEIPT_UPLOADED',
            receipt_image: receipt,
            amount: 250
        });

        showPushNotification('Receipt Received! 🎉', 'Your payment receipt was uploaded successfully. Admin will review and activate your Gold Badge.');
        closeModal();
        return res;
    }).catch(e => {
        if (err) {
            err.textContent = e.message || 'Failed to submit payment receipt.';
            err.style.display = 'block';
        }
    });
};


// ── 12. HELP CENTER SCREEN ──────────────────────────────────────────────
function renderFaqsList(faqs) {
    const container = document.getElementById('help-content');
    if (!container) return;

    if (!faqs || faqs.length === 0) {
        container.innerHTML = `<p style="font-size:0.8rem; color:var(--gray-500); text-align:center;">No FAQs found.</p>`;
        return;
    }

    // Group by category
    const grouped = {};
    faqs.forEach(f => {
        if (!grouped[f.category]) {
            grouped[f.category] = [];
        }
        grouped[f.category].push(f);
    });

    let html = `<h4 style="font-size:0.9rem; margin-bottom:12px; font-family:'Fraunces',serif; color:var(--primary);">Frequently Asked Questions</h4>`;
    for (const cat in grouped) {
        html += `<h5 class="faq-category-title" style="font-size:0.8rem; margin:20px 0 8px; color:var(--accent); font-weight:700; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid var(--gray-100); padding-bottom:4px;">${cat}</h5>`;
        grouped[cat].forEach(item => {
            html += `
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">${item.question} <i class="fa-solid fa-chevron-down"></i></div>
                    <div class="faq-answer">${item.answer}</div>
                </div>
            `;
        });
    }
    container.innerHTML = html;

    // Setup search filter
    const searchInput = document.getElementById('help-search-input');
    if (searchInput) {
        searchInput.oninput = function(e) {
            const query = e.target.value.toLowerCase().trim();
            const items = container.querySelectorAll('.faq-item');
            const categories = container.querySelectorAll('.faq-category-title');
            
            items.forEach(item => {
                const question = item.querySelector('.faq-question').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                if (question.includes(query) || answer.includes(query)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });

            categories.forEach(cat => {
                let next = cat.nextElementSibling;
                let hasVisible = false;
                while (next && next.classList.contains('faq-item')) {
                    if (next.style.display !== 'none') {
                        hasVisible = true;
                    }
                    next = next.nextElementSibling;
                }
                cat.style.display = hasVisible ? 'block' : 'none';
            });
        };
    }
}

window.openChatSupport = function() {
    API.get('get_app_download_urls').then(data => {
        let phone = data.chat_support_number || data.site_phone || '+233209001100';
        let cleanPhone = phone.replace(/[^0-9]/g, '');
        if (cleanPhone.startsWith('0')) {
            cleanPhone = '233' + cleanPhone.substring(1);
        }
        const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent('Hello Ohati Support Desk, I need assistance with my account.')}`;
        if (typeof cordova !== 'undefined' && cordova.InAppBrowser) {
            cordova.InAppBrowser.open(waUrl, '_system');
        } else {
            window.open(waUrl, '_blank');
        }
    }).catch(err => {
        window.open('https://wa.me/233209001100', '_blank');
    });
};

function initHelpScreen() {
    const screen = document.getElementById('screen-help');
    if (!screen) return;

    screen.innerHTML = `
        <div class="help-hero">
            <h3 style="color:#fff; margin-bottom:4px; font-family:'Fraunces',serif;">How can we help?</h3>
            <p style="font-size:0.75rem; color:rgba(255,255,255,0.85); margin-bottom:12px;">Search Ohati support articles and FAQs</p>
            <div class="help-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input placeholder="Search keywords..." id="help-search-input">
            </div>
        </div>
        <div class="p-section" id="help-content">
            ${(state.faqs && state.faqs.length > 0) ? '' : '<div class="full-spinner-wrap"><div class="spinner"></div></div>'}
        </div>
        <div class="p-section" style="padding-top:0;">
            <div class="card" style="padding:18px; text-align:center; background:linear-gradient(135deg, var(--primary), #1e293b); color:#fff; border-radius:16px; box-shadow:0 4px 15px rgba(0,0,0,0.1);">
                <i class="fa-solid fa-headset" style="font-size:2rem; color:var(--accent); margin-bottom:8px;"></i>
                <h4 style="font-size:0.95rem; margin-bottom:4px; font-weight:800; color:#fff; font-family:'Fraunces',serif;">Still need assistance?</h4>
                <p style="font-size:0.75rem; color:rgba(255,255,255,0.85); margin-bottom:14px;">Our support desk is online 24/7.</p>
                <button onclick="openChatSupport()" class="btn btn-primary btn-sm btn-full" style="display:inline-flex; align-items:center; gap:8px; justify-content:center; background:var(--accent); color:#0F1923; border:none; font-weight:800;">
                    <i class="fa-brands fa-whatsapp" style="font-size:1.1rem;"></i> Chat Support
                </button>
            </div>
        </div>
    `;

    if (state.faqs && state.faqs.length > 0) {
        renderFaqsList(state.faqs);
    }

    API.get('get_faqs')
        .then(faqs => {
            state.faqs = faqs;
            renderFaqsList(faqs);
        })
        .catch(err => {
            if (!state.faqs || state.faqs.length === 0) {
                const container = document.getElementById('help-content');
                if (container) {
                    container.innerHTML = `<p style="font-size:0.8rem; color:var(--danger); text-align:center;">Failed to load FAQs.</p>`;
                }
            }
        });
}

function toggleFaq(el) {
    const item = el.parentElement;
    item.classList.toggle('open');
}

function openReviewModal(vid) {
    const html = `
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Write a Review</h2>
            <p class="auth-modal-subtitle">Share your experience with other planners</p>
        </div>
        <div class="form-group">
            <label class="form-label">Your Name</label>
            <input type="text" class="form-input" id="rev-user-name" value="${state.user ? state.user.name : ''}">
        </div>
        <div class="form-group">
            <label class="form-label">Rating</label>
            <select class="form-select" id="rev-rating">
                <option value="5">5 Stars</option>
                <option value="4">4 Stars</option>
                <option value="3">3 Stars</option>
                <option value="2">2 Stars</option>
                <option value="1">1 Star</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Comment</label>
            <textarea class="form-textarea" id="rev-comment" placeholder="Write feedback..."></textarea>
        </div>
        <button class="btn btn-primary btn-full mt-12" onclick="submitReviewRequest(${vid})">Submit Review</button>
    `;
    openModal(html);
}

function submitReviewRequest(vid) {
    const name = document.getElementById('rev-user-name').value.trim();
    const rating = parseInt(document.getElementById('rev-rating').value) || 5;
    const comment = document.getElementById('rev-comment').value.trim();

    if (!name || !comment) {
        showPushNotification('Fields Required', 'Please complete name and comment.');
        return;
    }

    API.submitReview({
        vendor_id: vid,
        user_name: name,
        rating: rating,
    }).then(() => {
        showPushNotification('Review Submitted', 'Thank you for your feedback!');
        closeModal();
        initDetailScreen();
    });
}

// ── 13. VENDOR ADS SCREEN ───────────────────────────────────────────────
function initVendorAdsScreen() {
    const screen = document.getElementById('screen-vendor-ads');
    if (!screen) return;

    if (!state.user || !state.user.vendor_id) {
        screen.innerHTML = `<div class="p-section text-center"><p>Access Denied. Vendor profile required.</p></div>`;
        return;
    }

    state.activePromoTab = state.activePromoTab || 'campaigns';

    screen.innerHTML = `
        <div class="p-section" style="padding-bottom:10px; border-bottom:1px solid var(--gray-100); display:flex; justify-content:space-between; align-items:center; background:var(--white);">
            <div>
                <h3 style="font-family:'Fraunces',serif; margin-bottom:4px;">Promotions Hub</h3>
                <div style="font-size:0.75rem; color:var(--gray-600);">Grow your business visibility</div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="switchPromoTab('packages')"><i class="fa-solid fa-rocket"></i> Advertise Business</button>
        </div>

        <div style="display:flex; border-bottom:1px solid var(--gray-200); background:var(--white); position:sticky; top:0; z-index:10;">
            <button onclick="switchPromoTab('campaigns')" id="promo-tab-campaigns" class="promo-tab-btn ${state.activePromoTab === 'campaigns' ? 'active' : ''}" style="flex:1; padding:12px; font-size:0.75rem; font-weight:700; border:none; background:none; border-bottom:2px solid ${state.activePromoTab === 'campaigns' ? 'var(--primary)' : 'transparent'}; color:${state.activePromoTab === 'campaigns' ? 'var(--primary)' : 'var(--gray-600)'}; cursor:pointer;">My Campaigns</button>
            <button onclick="switchPromoTab('packages')" id="promo-tab-packages" class="promo-tab-btn ${state.activePromoTab === 'packages' ? 'active' : ''}" style="flex:1; padding:12px; font-size:0.75rem; font-weight:700; border:none; background:none; border-bottom:2px solid ${state.activePromoTab === 'packages' ? 'var(--primary)' : 'transparent'}; color:${state.activePromoTab === 'packages' ? 'var(--primary)' : 'var(--gray-600)'}; cursor:pointer;">Promo Packages</button>
            <button onclick="switchPromoTab('analytics')" id="promo-tab-analytics" class="promo-tab-btn ${state.activePromoTab === 'analytics' ? 'active' : ''}" style="flex:1; padding:12px; font-size:0.75rem; font-weight:700; border:none; background:none; border-bottom:2px solid ${state.activePromoTab === 'analytics' ? 'var(--primary)' : 'transparent'}; color:${state.activePromoTab === 'analytics' ? 'var(--primary)' : 'var(--gray-600)'}; cursor:pointer;">Performance Analytics</button>
        </div>

        <div id="promo-tab-content-container"></div>
    `;

    renderPromoTabContent();
}

function switchPromoTab(tabId) {
    state.activePromoTab = tabId;
    initVendorAdsScreen();
}

function renderPromoTabContent() {
    if (state.activePromoTab === 'campaigns') {
        fetchVendorAds();
    } else if (state.activePromoTab === 'packages') {
        renderPromoPackages();
    } else if (state.activePromoTab === 'analytics') {
        renderPromoAnalytics();
    }
}

function updateAllAdCountdowns() {
    const els = document.querySelectorAll('.ad-countdown');
    if (els.length === 0) {
        if (window.adCountdownInterval) {
            clearInterval(window.adCountdownInterval);
            window.adCountdownInterval = null;
        }
        return;
    }
    const now = new Date().getTime();
    els.forEach(el => {
        const expiryStr = el.getAttribute('data-expiry');
        if (!expiryStr) return;
        const expiryTime = new Date(expiryStr.replace(/-/g, '/')).getTime(); // Cross-browser safe date parse
        const diff = expiryTime - now;

        if (diff <= 0) {
            el.innerHTML = `<span style="color:var(--rose);"><i class="fa-solid fa-hourglass-end"></i> Expired</span>`;
        } else {
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const secs = Math.floor((diff % (1000 * 60)) / 1000);

            let txt = '';
            if (days > 0) {
                txt = `${days}d ${hours}h remaining`;
            } else if (hours > 0) {
                txt = `${hours}h ${mins}m remaining`;
            } else if (mins > 0) {
                txt = `${mins}m ${secs}s remaining`;
            } else {
                txt = `${secs}s remaining`;
            }
            el.innerHTML = `<i class="fa-solid fa-hourglass-half"></i> ${txt}`;
        }
    });
}

function renderVendorAdsList(ads) {
    const container = document.getElementById('promo-tab-content-container');
    if (!container) return;

    const now = new Date();
    const active = ads.filter(ad => new Date(ad.end_date.replace(/-/g, '/')) >= now && ad.status === 'active');
    const pending = ads.filter(ad => ad.status === 'pending');
    const scheduled = ads.filter(ad => new Date(ad.start_date.replace(/-/g, '/')) > now && ad.status === 'active');
    const expired = ads.filter(ad => new Date(ad.end_date.replace(/-/g, '/')) < now || ad.status === 'expired');

    container.innerHTML = `
        <div class="p-section" style="padding-bottom:12px; display:grid; grid-template-columns: repeat(4, 1fr); gap:8px;">
            <div class="card text-center" style="padding:10px 4px; background:rgba(46, 204, 113, 0.05); border-color:rgba(46, 204, 113, 0.2);">
                <div style="font-size:1.1rem; font-weight:800; color:#2ecc71;">${active.length}</div>
                <div style="font-size:0.55rem; color:var(--gray-600); text-transform:uppercase; font-weight:700; margin-top:2px;">Active</div>
            </div>
            <div class="card text-center" style="padding:10px 4px; background:rgba(241, 196, 15, 0.05); border-color:rgba(241, 196, 15, 0.2);">
                <div style="font-size:1.1rem; font-weight:800; color:#f1c40f;">${pending.length}</div>
                <div style="font-size:0.55rem; color:var(--gray-600); text-transform:uppercase; font-weight:700; margin-top:2px;">Pending</div>
            </div>
            <div class="card text-center" style="padding:10px 4px; background:rgba(52, 152, 219, 0.05); border-color:rgba(52, 152, 219, 0.2);">
                <div style="font-size:1.1rem; font-weight:800; color:#3498db;">${scheduled.length}</div>
                <div style="font-size:0.55rem; color:var(--gray-600); text-transform:uppercase; font-weight:700; margin-top:2px;">Scheduled</div>
            </div>
            <div class="card text-center" style="padding:10px 4px; background:rgba(231, 76, 60, 0.05); border-color:rgba(231, 76, 60, 0.2);">
                <div style="font-size:1.1rem; font-weight:800; color:#e74c3c;">${expired.length}</div>
                <div style="font-size:0.55rem; color:var(--gray-600); text-transform:uppercase; font-weight:700; margin-top:2px;">Expired</div>
            </div>
        </div>

        <div class="p-section" style="padding-top:0;">
            ${ads.length === 0 ? `
                <div class="text-center" style="padding:40px 0; background:var(--gray-50); border-radius:12px; border:1px dashed var(--gray-200);">
                    <i class="fa-solid fa-rectangle-ad" style="font-size:3rem; color:var(--gray-300); margin-bottom:12px;"></i>
                    <p class="text-sm text-muted" style="margin-bottom:12px;">No active campaigns found</p>
                    <button class="btn btn-primary btn-sm" onclick="switchPromoTab('packages')">Explore Promotion Packages</button>
                </div>
            ` : `
                <div style="display:flex; flex-direction:column; gap:12px;">
                    ${ads.map(ad => {
                        const isExp = new Date(ad.end_date.replace(/-/g, '/')) < now;
                        const statusLbl = isExp ? '<span class="badge badge-error">Expired</span>' : 
                            (ad.status === 'pending' ? '<span class="badge badge-warning">Pending</span>' : '<span class="badge badge-success">Active</span>');
                        return `
                            <div class="card" style="padding:16px;">
                                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:8px;">
                                    <div>
                                        <h4 style="font-size:0.85rem; font-weight:700; margin-bottom:2px;">${ad.title}</h4>
                                        <p style="font-size:0.7rem; color:var(--gray-600); line-height:1.3; margin-bottom:4px;">${ad.description}</p>
                                    </div>
                                    ${statusLbl}
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.7rem; color:var(--gray-400); background:var(--gray-50); padding:8px; border-radius:6px; margin-bottom:12px;">
                                    <span><i class="fa-solid fa-eye"></i> ${ad.impressions || 0} Views</span>
                                    <span><i class="fa-solid fa-arrow-pointer"></i> ${ad.clicks || 0} Clicks</span>
                                    <span class="ad-countdown" data-expiry="${ad.end_date}" style="font-size:0.65rem; font-weight:600; color:var(--accent);">
                                        <i class="fa-solid fa-hourglass-half"></i> Loading countdown...
                                    </span>
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <button class="btn btn-outline btn-xs" style="flex:1;" onclick="openRenewAdModal(${ad.id})"><i class="fa-solid fa-rotate-right"></i> Renew Campaign</button>
                                    <button class="btn btn-outline btn-xs" style="flex:1;" onclick="openUpgradeAdModal(${ad.id})"><i class="fa-solid fa-arrow-up-right-dots"></i> Upgrade Promo</button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `}
        </div>
    `;

    // Start countdown timer updates
    if (window.adCountdownInterval) clearInterval(window.adCountdownInterval);
    updateAllAdCountdowns();
    window.adCountdownInterval = setInterval(updateAllAdCountdowns, 1000);
}

function fetchVendorAds() {
    const container = document.getElementById('promo-tab-content-container');
    if (!container) return;

    const vendorId = state.user.vendor_id;
    if (state.vendorAds && state.vendorAds[vendorId]) {
        renderVendorAdsList(state.vendorAds[vendorId]);
    } else {
        container.innerHTML = `<div class="text-center" style="padding:40px 0;"><i class="fa-solid fa-spinner fa-spin" style="font-size:2rem; color:var(--primary);"></i></div>`;
    }

    API.getAdCampaigns(vendorId).then(ads => {
        if (!state.vendorAds) state.vendorAds = {};
        state.vendorAds[vendorId] = ads;
        if (state.activePromoTab !== 'campaigns') return;
        renderVendorAdsList(ads);
    }).catch(err => {
        if (!state.vendorAds || !state.vendorAds[vendorId]) {
            container.innerHTML = `<div class="p-section text-center text-error">Error loading ads: ${err.message}</div>`;
        }
    });
}

function renderPromoPackages() {
    const container = document.getElementById('promo-tab-content-container');
    if (!container) return;

    const starterPrice = parseFloat(state.settings?.ad_plan_starter_price || 50);
    const starterReach = state.settings?.ad_plan_starter_reach || "1,000+ planners";
    
    const standardPrice = parseFloat(state.settings?.ad_plan_standard_price || 300);
    const standardReach = state.settings?.ad_plan_standard_reach || "10,000+ planners";
    
    const premiumPrice = parseFloat(state.settings?.ad_plan_premium_price || 1100);
    const premiumReach = state.settings?.ad_plan_premium_reach || "50,000+ planners";
    
    const platinumPrice = parseFloat(state.settings?.ad_plan_platinum_price || 3000);
    const platinumReach = state.settings?.ad_plan_platinum_reach || "200,000+ planners";

    container.innerHTML = `
        <div class="p-section">
            <h4 style="font-family:'Fraunces',serif; font-size:1.1rem; margin-bottom:4px;">Select an Advertising Plan</h4>
            <p style="font-size:0.75rem; color:var(--gray-600); margin-bottom:16px;">Pick a promotional tier to display your brand to thousands of Ghanaian event planners.</p>

            <div style="display:flex; flex-direction:column; gap:16px;">
                <!-- Starter -->
                <div class="card" style="padding:16px; border-left:4px solid var(--gray-400);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div>
                            <h3 style="font-size:1rem; font-weight:700; margin:0;">Starter Promotion</h3>
                            <span style="font-size:0.65rem; color:var(--gray-400);">1 Day Duration</span>
                        </div>
                        <div style="font-size:1.2rem; font-weight:800; color:var(--primary);">GH₵ ${starterPrice}</div>
                    </div>
                    <ul style="font-size:0.75rem; color:var(--gray-600); padding-left:16px; margin-bottom:12px; line-height:1.4;">
                        <li>Basic visibility in search results</li>
                        <li>Sponsored badge on vendor listing</li>
                        <li>Search ranking boost</li>
                        <li>Estimated reach: <strong>${starterReach}</strong></li>
                    </ul>
                    <button class="btn btn-outline btn-sm btn-full" onclick="purchasePromoPackage('Starter', 1, ${starterPrice})">Buy Starter Package</button>
                </div>

                <!-- Standard -->
                <div class="card" style="padding:16px; border-left:4px solid var(--primary);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div>
                            <h3 style="font-size:1rem; font-weight:700; margin:0; color:var(--primary);">Standard Promotion</h3>
                            <span style="font-size:0.65rem; color:var(--primary); font-weight:600;">7 Days Duration (15% Savings)</span>
                        </div>
                        <div style="font-size:1.2rem; font-weight:800; color:var(--primary);">GH₵ ${standardPrice}</div>
                    </div>
                    <ul style="font-size:0.75rem; color:var(--gray-600); padding-left:16px; margin-bottom:12px; line-height:1.4;">
                        <li>Higher search ranking inside category page</li>
                        <li>Category priority sorting</li>
                        <li>Increased general exposure</li>
                        <li>Estimated reach: <strong>${standardReach}</strong></li>
                    </ul>
                    <button class="btn btn-primary btn-sm btn-full" onclick="purchasePromoPackage('Standard', 7, ${standardPrice})">Buy Standard Package</button>
                </div>

                <!-- Premium -->
                <div class="card" style="padding:16px; border-left:4px solid var(--accent); background:rgba(235, 104, 76, 0.02);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div>
                            <h3 style="font-size:1rem; font-weight:700; margin:0; color:var(--accent);">Premium Promotion</h3>
                            <span style="font-size:0.65rem; color:var(--accent); font-weight:600;">30 Days Duration (25% Savings)</span>
                        </div>
                        <div style="font-size:1.2rem; font-weight:800; color:var(--accent);">GH₵ ${premiumPrice}</div>
                    </div>
                    <ul style="font-size:0.75rem; color:var(--gray-600); padding-left:16px; margin-bottom:12px; line-height:1.4;">
                        <li>Homepage placement under Featured list</li>
                        <li>Priority search ranking in primary searches</li>
                        <li>Featured Vendor highlight block</li>
                        <li>Estimated reach: <strong>${premiumReach}</strong></li>
                    </ul>
                    <button class="btn btn-primary btn-sm btn-full" style="background:var(--accent); border-color:var(--accent);" onclick="purchasePromoPackage('Premium', 30, ${premiumPrice})">Buy Premium Package</button>
                </div>

                <!-- Platinum -->
                <div class="card" style="padding:16px; border:2px solid var(--accent); background:rgba(242, 167, 53, 0.05); position:relative;">
                    <div style="position:absolute; top:-10px; right:15px; background:var(--accent); color:var(--primary-dark); font-size:0.55rem; font-weight:800; padding:2px 8px; border-radius:10px; text-transform:uppercase;">Best Value</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div>
                            <h3 style="font-size:1rem; font-weight:700; margin:0; color:var(--accent);">Platinum Promotion</h3>
                              <span style="font-size:0.65rem; color:var(--accent); font-weight:600;">90 Days Duration (35% Savings)</span>
                          </div>
                          <div style="font-size:1.2rem; font-weight:800; color:var(--accent);">GH₵ ${platinumPrice}</div>
                      </div>
                      <ul style="font-size:0.75rem; color:var(--gray-600); padding-left:16px; margin-bottom:12px; line-height:1.4;">
                          <li>Maximum exposure on Ohati platforms</li>
                          <li>Top banner opportunity + Featured list</li>
                          <li>Premium verification badge visibility</li>
                          <li>Advanced campaign analytics & priority support</li>
                          <li>Estimated reach: <strong>${platinumReach}</strong></li>
                      </ul>
                      <button class="btn btn-sm btn-full" style="background:var(--accent); color:var(--primary-dark); border-color:var(--accent); font-weight:700;" onclick="purchasePromoPackage('Platinum', 90, ${platinumPrice})">Buy Platinum Package</button>
                  </div>
              </div>
          </div>
      `;
  }

function renderPromoAnalytics() {
    const container = document.getElementById('promo-tab-content-container');
    if (!container) return;

    container.innerHTML = `<div class="text-center" style="padding:40px 0;"><i class="fa-solid fa-spinner fa-spin" style="font-size:2rem; color:var(--primary);"></i></div>`;

    API.getAdAnalytics(state.user.vendor_id).then(res => {
        if (state.activePromoTab !== 'analytics') return;

        const totalImpressions = res.total_impressions || 0;
        const totalClicks = res.total_clicks || 0;
        const ctr = totalImpressions > 0 ? ((totalClicks / totalImpressions) * 100).toFixed(2) : '0.00';

        container.innerHTML = `
            <div class="p-section" style="display:flex; flex-direction:column; gap:16px;">
                <h4 style="font-family:'Fraunces',serif; font-size:1.1rem; margin-bottom:2px;">Performance Metrics</h4>
                <p style="font-size:0.75rem; color:var(--gray-600);">Verify real-time engagement and ROI of your active advertisements.</p>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="card text-center" style="padding:16px;">
                        <div style="font-size:1.8rem; font-weight:800; color:var(--primary);">${totalImpressions}</div>
                        <div style="font-size:0.7rem; color:var(--gray-600); text-transform:uppercase; margin-top:4px;">Total Impressions</div>
                    </div>
                    <div class="card text-center" style="padding:16px;">
                        <div style="font-size:1.8rem; font-weight:800; color:var(--primary);">${totalClicks}</div>
                        <div style="font-size:0.7rem; color:var(--gray-600); text-transform:uppercase; margin-top:4px;">Total Clicks</div>
                    </div>
                </div>

                <div class="card text-center" style="padding:16px; background:var(--gray-50);">
                    <div style="font-size:2.2rem; font-weight:800; color:var(--accent); font-family:'Outfit',sans-serif;">${ctr}%</div>
                    <div style="font-size:0.75rem; color:var(--gray-600); font-weight:600; text-transform:uppercase; margin-top:4px;">Average Click-Through Rate (CTR)</div>
                    <p style="font-size:0.65rem; color:var(--gray-400); margin-top:6px; line-height:1.3;">Industry standard for high-performing event promotions is around 1.5% to 3.0%. Your current exposure is pacing well!</p>
                </div>
            </div>
        `;
    }).catch(err => {
        container.innerHTML = `<div class="p-section text-center text-error">Error loading analytics: ${err.message}</div>`;
    });
}

function purchasePromoPackage(packageName, days, price) {
    window.currentAdBannerBase64 = 'img/ads/default.jpg';
    window.currentAdCost = price;
    window.currentAdDuration = days;
    window._adReceiptData = '';

    openModal(`
        <div style="padding:20px; text-align:center;">
            <div class="full-spinner-wrap"><div class="spinner"></div></div>
            <p style="font-size:0.8rem; color:var(--gray-600); margin-top:8px;">Loading Campaign Configuration & Bank Details...</p>
        </div>
    `);

    API.get('get_bank_details').then(res => {
        const b = res.bank_details || {};
        const html = `
            <div class="auth-modal-header">
                <h2 class="auth-modal-title">Configure ${packageName} Promotion</h2>
                <p class="auth-modal-subtitle">Setup campaign for GH₵ ${price.toFixed(2)} (${days} Days)</p>
            </div>
            
            <!-- Live Ad Banner Preview -->
            <div class="card p-12 mb-16" style="border:1px solid var(--accent); background:var(--gray-50); border-radius:12px;">
                <div style="font-size:0.7rem; font-weight:700; color:var(--accent); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; display:flex; align-items:center; gap:4px;">
                    <i class="fa-solid fa-eye"></i> Live Advertisement Preview
                </div>
                <div class="sponsored-card" style="border-radius:12px; overflow:hidden; border:1px solid var(--gray-200); background:var(--white); box-shadow:var(--shadow-sm);">
                    <div style="position:relative; height:120px; background:var(--gray-100);">
                        <img id="preview-ad-banner" src="${window.currentAdBannerBase64}" style="width:100%; height:100%; object-fit:cover; display:block;">
                        <span style="position:absolute; top:8px; left:8px; background:var(--accent); color:#fff; font-size:0.6rem; font-weight:800; padding:3px 6px; border-radius:4px; display:flex; align-items:center; gap:4px;">
                            <i class="fa-solid fa-rectangle-ad"></i> Sponsored
                        </span>
                    </div>
                    <div style="padding:12px;">
                        <h4 id="preview-ad-title" style="margin:0 0 4px 0; font-size:0.85rem; font-weight:700; color:var(--gray-800); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">Summer Bridal Special</h4>
                        <p id="preview-ad-desc" style="margin:0 0 10px 0; font-size:0.75rem; color:var(--gray-500); line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:30px;">Catchy description will appear here...</p>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.7rem; color:var(--gray-400);"><i class="fa-solid fa-location-dot"></i> <span id="preview-ad-location">All Locations</span></span>
                            <button id="preview-ad-cta" class="btn btn-primary btn-xs" style="padding:4px 10px; font-size:0.7rem; font-weight:700; background:var(--accent); border-color:var(--accent);">Learn More</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group mb-12">
                <label class="form-label">Campaign Title</label>
                <input type="text" class="form-input" id="ad-title" placeholder="e.g. Summer Bridal Special" oninput="updateAdPreview()" value="Summer Bridal Special">
            </div>
            <div class="form-group mb-12">
                <label class="form-label">Ad Banner Description</label>
                <textarea class="form-textarea" id="ad-desc" placeholder="Write a catchy line to display on your banner..." style="min-height:50px;" oninput="updateAdPreview()">Premium makeup packages and flawless skin styling for your big day.</textarea>
            </div>
            
            <div class="form-group mb-12">
                <label class="form-label">Ad Campaign Placement Target</label>
                <select class="form-select" id="ad-placement" style="font-weight:700;">
                    <option value="home_top_banner">Home Top Banner Slider</option>
                    <option value="home_popup">Home Screen Pop-up Modal (High Impact)</option>
                    <option value="search_top_banner">Search Screen Top Banner</option>
                    <option value="category_sponsored_badge">Category Sponsored Listing & Gold Badge</option>
                    <option value="vendor_detail_banner">Vendor Detail Banner Placement</option>
                </select>
            </div>

            <div class="form-group mb-12">
                <label class="form-label">Upload Banner Image</label>
                <input type="file" class="form-input" id="ad-banner-file" accept="image/*" onchange="handleAdBannerUpload(this)">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="form-group mb-12">
                    <label class="form-label">CTA Text</label>
                    <select class="form-select" id="ad-cta-text" onchange="updateAdPreview()">
                        <option value="Learn More">Learn More</option>
                        <option value="Book Now">Book Now</option>
                        <option value="View Profile">View Profile</option>
                        <option value="Get Discount">Get Discount</option>
                    </select>
                </div>
                <div class="form-group mb-12">
                    <label class="form-label">Destination</label>
                    <select class="form-select" id="ad-destination">
                        <option value="profile">Vendor Profile</option>
                        <option value="packages">Packages & pricing</option>
                        <option value="whatsapp">WhatsApp Direct</option>
                    </select>
                </div>
            </div>

            <div class="card p-12 mb-12" style="background:var(--gray-50); border:1px solid var(--gray-100);">
                <div style="font-size:0.75rem; font-weight:700; color:var(--gray-600); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">Audience & Location Targeting</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group mb-8">
                        <label class="form-label" style="font-size:0.7rem;">Target Location</label>
                        <select class="form-select" id="ad-target-location" onchange="updateAdPreview()" style="font-size:0.75rem; padding:6px 10px;">
                            <option value="All">All Locations</option>
                            <option value="Accra">Accra only</option>
                            <option value="Kumasi">Kumasi only</option>
                            <option value="Tamale">Tamale only</option>
                            <option value="Takoradi">Takoradi only</option>
                        </select>
                    </div>
                    <div class="form-group mb-8">
                        <label class="form-label" style="font-size:0.7rem;">Target Category</label>
                        <select class="form-select" id="ad-target-category" style="font-size:0.75rem; padding:6px 10px;">
                            <option value="All">All Categories</option>
                            <option value="Photography">Photography</option>
                            <option value="Videography">Videography</option>
                            <option value="Makeup Artists">Makeup Artists</option>
                            <option value="Bridal Shops">Bridal Shops</option>
                            <option value="Event Planners">Event Planners</option>
                            <option value="Decorators">Decorators</option>
                            <option value="Caterers">Caterers</option>
                            <option value="Cake Designers">Cake Designers</option>
                            <option value="Event Venues">Event Venues</option>
                            <option value="DJs">DJs</option>
                            <option value="MCs">MCs</option>
                            <option value="Live Bands">Live Bands</option>
                            <option value="Florists">Florists</option>
                            <option value="Car Rentals">Car Rentals</option>
                            <option value="Security Services">Security Services</option>
                            <option value="Chilling Services">Chilling Services</option>
                            <option value="Rental Equipment">Rental Equipment</option>
                            <option value="Cocktail Bars">Cocktail Bars</option>
                            <option value="Honeymoon Packages">Honeymoon Packages</option>
                            <option value="Invitation Designers">Invitation Designers</option>
                            <option value="Jewelers">Jewelers</option>
                            <option value="Content Creators">Content Creators</option>
                            <option value="Juice Bar">Juice Bar</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Official Admin Bank & MoMo Details Card -->
            <div style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:12px; padding:14px; margin-bottom:12px; font-size:0.78rem;">
                <div style="font-size:0.75rem; font-weight:700; color:var(--primary); margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                    <i class="fa-solid fa-building-columns" style="color:var(--accent);"></i> Official Admin Payment Accounts
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid var(--gray-200);">
                        <div style="font-size:0.65rem; color:var(--gray-500); font-weight:700;">MTN MOBILE MONEY</div>
                        <strong style="color:var(--primary); font-size:0.85rem; display:block; margin-top:2px;">${b.momo_number || '0540477911'}</strong>
                        <div style="font-size:0.68rem; color:var(--gray-600);">${b.momo_name || 'Ohati Payments'}</div>
                    </div>

                    <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid var(--gray-200);">
                        <div style="font-size:0.65rem; color:var(--gray-500); font-weight:700;">BANK TRANSFER</div>
                        <strong style="color:var(--primary); font-size:0.85rem; display:block; margin-top:2px;">${b.bank_name || 'Ecobank Ghana'}</strong>
                        <div style="font-size:0.68rem; color:var(--gray-600); font-weight:600;">Acc: ${b.account_number || '1441002939201'}</div>
                        <div style="font-size:0.65rem; color:var(--gray-500);">${b.account_name || 'Ohati Global Services'}</div>
                    </div>
                </div>

                <div style="font-size:0.7rem; color:var(--gray-600); line-height:1.4;">
                    <i class="fa-solid fa-circle-info" style="color:var(--primary);"></i> Transfer <strong>GH₵ ${price.toFixed(2)}</strong> to MTN MoMo or Ecobank Ghana above, then enter your TxID or upload receipt below.
                </div>
            </div>

            <!-- Payment Receipt Upload Zone -->
            <div class="form-group mb-12">
                <label class="form-label" style="font-size:0.82rem; font-weight:700;">Upload Payment Receipt Screenshot or PDF <span style="color:var(--error);">*</span></label>
                <div class="kyc-upload-zone" onclick="document.getElementById('ad-receipt-file-input').click()" style="cursor:pointer; padding:18px; text-align:center; border:2px dashed var(--accent); border-radius:12px; background:rgba(242, 167, 53, 0.08);">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:2rem; color:var(--accent); margin-bottom:6px; display:block;"></i>
                    <strong style="font-size:0.85rem; color:var(--primary); display:block;">Tap Here to Upload Payment Receipt</strong>
                    <p id="ad-receipt-status" style="margin:4px 0 0 0; font-size:0.72rem; color:var(--gray-600);">Supports JPG, PNG, WEBP, or PDF (Max 20MB)</p>
                    <input type="file" id="ad-receipt-file-input" accept="image/*,application/pdf" style="display:none;" onchange="handleAdReceiptFile(event)">
                </div>
            </div>

            <div class="form-group mb-12">
                <label class="form-label" style="font-size:0.7rem;">Optional Notes / Special Requests</label>
                <input type="text" id="ad-payment-notes" class="form-input" style="font-size:0.75rem; padding:6px 10px;" placeholder="e.g. Boost my placement in the Decorators category.">
            </div>

            <div class="card p-12 mb-12" style="background:var(--gray-50); border:1px solid var(--gray-100);">
                <h3 style="font-size:1rem; display:flex; justify-content:space-between; margin:0; font-family:'Outfit',sans-serif;">
                    <span>Package Value:</span>
                    <strong style="color:var(--accent);">GH₵ ${price.toFixed(2)}</strong>
                </h3>
            </div>
            
            <button class="btn btn-primary btn-full mt-12" onclick="payForAdCampaign(event)" id="ad-submit-btn">
                <i class="fa-solid fa-paper-plane"></i> Submit Campaign & Payment Receipt
            </button>
        `;
        openModal(html);
        updateAdPreview();
    }).catch(err => {
        showPushNotification('Error', 'Failed to load admin bank details.');
    });
}

window.toggleAdPaymentDetails = function() {
    const manualRadio = document.querySelector('input[name="ad-payment-method"][value="manual"]');
    const manualSection = document.getElementById('ad-manual-payment-details');
    if (manualSection) {
        manualSection.style.display = (manualRadio && manualRadio.checked) ? 'block' : 'none';
    }
};

window._adReceiptData = '';
window.handleAdReceiptFile = function(event) {
    const file = event.target.files[0];
    if (!file) return;
    const status = document.getElementById('ad-receipt-status');
    const previewWrap = document.getElementById('ad-receipt-preview-wrap');
    const previewImg = document.getElementById('ad-receipt-preview');

    if (status) status.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Reading...`;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        window._adReceiptData = e.target.result;
        if (status) status.innerHTML = `<i class="fa-solid fa-circle-check text-success"></i> ${file.name.substring(0, 20)} loaded!`;
        if (previewImg) previewImg.src = e.target.result;
        if (previewWrap) previewWrap.style.display = 'block';
    };
    reader.readAsDataURL(file);
};

function openCreateAdModal() {
    purchasePromoPackage('Starter', 1, 50);
}

window._renewReceiptData = '';
window.handleRenewReceiptFile = function(event) {
    const file = event.target.files[0];
    const status = document.getElementById('renew-receipt-status');
    const previewWrap = document.getElementById('renew-receipt-preview-wrap');
    const previewImg = document.getElementById('renew-receipt-preview');

    if (!file) return;

    status.textContent = file.name;
    const reader = new FileReader();
    reader.onload = function(e) {
        window._renewReceiptData = e.target.result;
        if (previewImg && previewWrap) {
            previewImg.src = e.target.result;
            previewWrap.style.display = 'block';
        }
    };
    reader.readAsDataURL(file);
};

function openRenewAdModal(adId) {
    window._renewReceiptData = '';
    openModal(`
        <div style="padding:20px; text-align:center;">
            <div class="full-spinner-wrap"><div class="spinner"></div></div>
            <p style="font-size:0.8rem; color:var(--gray-600); margin-top:8px;">Fetching Official Payment Details...</p>
        </div>
    `);

    API.get('get_bank_details').then(res => {
        const b = res.bank_details || {};
        const html = `
            <div class="auth-modal-header" style="margin-bottom:12px;">
                <h2 class="auth-modal-title">Renew & Extend Campaign</h2>
                <p class="auth-modal-subtitle">Select duration and upload payment proof</p>
            </div>
            
            <div class="form-group mb-12">
                <label class="form-label">Extension Duration</label>
                <select class="form-select" id="renew-duration" style="font-size:0.75rem; padding:6px 10px;">
                    <option value="1" data-price="50">1 Day extension (GH₵ 50)</option>
                    <option value="7" data-price="300">7 Days extension (GH₵ 300)</option>
                    <option value="30" data-price="1100">30 Days extension (GH₵ 1,100)</option>
                </select>
            </div>

            <!-- Official Admin Bank & MoMo Details Card -->
            <div style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:12px; padding:14px; margin-bottom:12px; font-size:0.78rem;">
                <div style="font-size:0.75rem; font-weight:700; color:var(--primary); margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                    <i class="fa-solid fa-building-columns" style="color:var(--accent);"></i> Official Admin Payment Accounts
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid var(--gray-200);">
                        <div style="font-size:0.65rem; color:var(--gray-500); font-weight:700;">MTN MOBILE MONEY</div>
                        <strong style="color:var(--primary); font-size:0.85rem; display:block; margin-top:2px;">${b.momo_number || '0540477911'}</strong>
                        <div style="font-size:0.68rem; color:var(--gray-600);">${b.momo_name || 'Ohati Payments'}</div>
                    </div>

                    <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid var(--gray-200);">
                        <div style="font-size:0.65rem; color:var(--gray-500); font-weight:700;">BANK TRANSFER</div>
                        <strong style="color:var(--primary); font-size:0.85rem; display:block; margin-top:2px;">${b.bank_name || 'Ecobank Ghana'}</strong>
                        <div style="font-size:0.68rem; color:var(--gray-600); font-weight:600;">Acc: ${b.account_number || '1441002939201'}</div>
                        <div style="font-size:0.65rem; color:var(--gray-500);">${b.account_name || 'Ohati Global Services'}</div>
                    </div>
                </div>

                <div style="font-size:0.7rem; color:var(--gray-600); line-height:1.4;">
                    <i class="fa-solid fa-circle-info" style="color:var(--primary);"></i> Transfer extension fee to MTN MoMo or Ecobank Ghana above, then enter your TxID or upload receipt below.
                </div>
            </div>

            <!-- Payment Receipt Upload Zone -->
            <div class="form-group mb-12">
                <label class="form-label" style="font-size:0.82rem; font-weight:700;">Upload Payment Receipt Screenshot or PDF <span style="color:var(--error);">*</span></label>
                <div class="kyc-upload-zone" onclick="document.getElementById('renew-receipt-file-input').click()" style="cursor:pointer; padding:18px; text-align:center; border:2px dashed var(--accent); border-radius:12px; background:rgba(242, 167, 53, 0.08);">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:2rem; color:var(--accent); margin-bottom:6px; display:block;"></i>
                    <strong style="font-size:0.85rem; color:var(--primary); display:block;">Tap Here to Upload Payment Receipt</strong>
                    <p id="renew-receipt-status" style="margin:4px 0 0 0; font-size:0.72rem; color:var(--gray-600);">Supports JPG, PNG, WEBP, or PDF (Max 20MB)</p>
                    <input type="file" id="renew-receipt-file-input" accept="image/*,application/pdf" style="display:none;" onchange="handleRenewReceiptFile(event)">
                </div>
            </div>

            <div class="form-group mb-8">
                <label class="form-label" style="font-size:0.7rem;">Optional Notes / Special Requests</label>
                <input type="text" id="renew-payment-notes" class="form-input" style="font-size:0.75rem; padding:6px 10px;" placeholder="e.g. Please contact me via phone.">
            </div>

            <button class="btn btn-primary btn-full mt-12" id="renew-submit-btn" onclick="executeRenewCampaign(${adId}, event)">
                <i class="fa-solid fa-paper-plane"></i> Submit Renewal Payment Proof
            </button>
        `;
        openModal(html);
    }).catch(err => {
        showPushNotification('Error', 'Failed to load admin bank details.');
    });
}

window.executeRenewCampaign = function(adId, event) {
    const select = document.getElementById('renew-duration');
    const days = parseInt(select.value);
    let cost = 50;
    if (days === 7) cost = 300;
    if (days === 30) cost = 1100;

    const txId = document.getElementById('renew-payment-txid')?.value.trim() || '';
    const notes = document.getElementById('renew-payment-notes')?.value.trim() || '';
    const receipt = window._renewReceiptData || '';

    const payload = { 
        id: adId, 
        duration_days: days, 
        cost: cost, 
        payment_method: 'manual',
        payment_ref: txId || 'Bank/MoMo Transfer',
        payment_notes: notes,
        receipt_data: receipt
    };

    const btn = event?.target || document.getElementById('renew-submit-btn');
    ActionLock.execute(btn, 'Submitting Renewal...', async () => {
        const res = await API.renewAdCampaign(payload);
        showPushNotification('Submitted! 🎉', 'Your campaign renewal request & payment receipt have been submitted for admin approval.');
        closeModal();
        if (typeof fetchVendorAds === 'function') fetchVendorAds();
        return res;
    });
};

window._upgradeReceiptData = '';
window.handleUpgradeReceiptFile = function(event) {
    const file = event.target.files[0];
    const status = document.getElementById('upgrade-receipt-status');
    const previewWrap = document.getElementById('upgrade-receipt-preview-wrap');
    const previewImg = document.getElementById('upgrade-receipt-preview');

    if (!file) return;

    status.textContent = file.name;
    const reader = new FileReader();
    reader.onload = function(e) {
        window._upgradeReceiptData = e.target.result;
        if (previewImg && previewWrap) {
            previewImg.src = e.target.result;
            previewWrap.style.display = 'block';
        }
    };
    reader.readAsDataURL(file);
};

function openUpgradeAdModal(adId) {
    const html = `
        <div class="auth-modal-header" style="margin-bottom:12px;">
            <h2 class="auth-modal-title">Upgrade Promotion</h2>
            <p class="auth-modal-subtitle">Modify title or boost placement (GH₵ 50 Fee)</p>
        </div>
        
        <div class="form-group mb-12">
            <label class="form-label">New Campaign Title</label>
            <input type="text" class="form-input" id="upgrade-title" placeholder="Enter new title...">
        </div>

        <div class="form-group mb-8">
            <label class="form-label" style="font-size:0.7rem;">Optional Notes / Special Requests</label>
            <input type="text" id="upgrade-payment-notes" class="form-input" style="font-size:0.75rem; padding:6px 10px;" placeholder="e.g. Please contact me via phone.">
        </div>

        <button class="btn btn-primary btn-full mt-12" id="upgrade-submit-btn" onclick="executeUpgradeCampaign(${adId})">Request Upgrade / Boost</button>
    `;
    openModal(html);
}

window.executeUpgradeCampaign = function(adId) {
    const newTitle = document.getElementById('upgrade-title').value.trim();
    if (!newTitle) {
        showPushNotification('Required', 'Please enter a title.');
        return;
    }

    const notes = document.getElementById('upgrade-payment-notes').value.trim();

    const payload = { 
        id: adId, 
        title: newTitle, 
        cost: 50, 
        payment_method: 'manual',
        payment_ref: 'Inquiry Mode',
        payment_notes: notes,
        receipt_data: 'inquiry_demo_mode'
    };

    const btn = document.getElementById('upgrade-submit-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    }

    API.upgradeAdCampaign(payload).then(res => {
        showPushNotification('Submitted', 'Your campaign upgrade request has been submitted for admin approval.');
        closeModal();
        fetchVendorAds();
    }).catch(err => {
        showPushNotification('Upgrade Error', err.message);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Request Upgrade / Boost';
        }
    });
};

function handleAdBannerUpload(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            window.currentAdBannerBase64 = e.target.result;
            document.getElementById('preview-ad-banner').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function updateAdPreview() {
    const titleVal = document.getElementById('ad-title').value.trim() || 'Campaign Title Preview';
    const descVal = document.getElementById('ad-desc').value.trim() || 'Catchy description will appear here...';
    const locVal = document.getElementById('ad-target-location').value;
    const ctaVal = document.getElementById('ad-cta-text').value;

    const previewBanner = document.getElementById('preview-ad-banner');
    if (previewBanner) previewBanner.src = window.currentAdBannerBase64 || 'img/ads/default.jpg';
    document.getElementById('preview-ad-title').textContent = titleVal;
    document.getElementById('preview-ad-desc').textContent = descVal;
    document.getElementById('preview-ad-location').textContent = locVal === 'All' ? 'All Locations' : locVal + ' only';
    document.getElementById('preview-ad-cta').textContent = ctaVal;
}

function payForAdCampaign() {
    const title = document.getElementById('ad-title').value.trim();
    const desc = document.getElementById('ad-desc').value.trim();
    const duration = window.currentAdDuration;
    const cost = window.currentAdCost;
    const location = document.getElementById('ad-target-location').value;
    const category = document.getElementById('ad-target-category').value;
    const ctaText = document.getElementById('ad-cta-text').value;
    const destination = document.getElementById('ad-destination').value;

    const paymentMethod = 'manual';

    if (!title || !desc) {
        showPushNotification('Required fields', 'Please enter a title and description.');
        return;
    }

    const placement = document.getElementById('ad-placement')?.value || 'home_top_banner';

    const payload = {
        vendor_id: state.user.vendor_id,
        title: title,
        description: desc,
        banner_url: window.currentAdBannerBase64 || 'img/ads/default.jpg',
        placement: placement,
        duration_days: duration,
        cost: cost,
        cta_text: ctaText,
        destination: destination,
        target_location: location,
        target_category: category,
        target_event: 'All',
        payment_method: paymentMethod
    };

    const notes = document.getElementById('ad-payment-notes')?.value.trim() || '';

    payload.receipt_data = 'inquiry_demo_mode';
    payload.payment_ref = 'Inquiry Mode';
    payload.payment_date = new Date().toISOString().substring(0, 10);
    payload.payment_notes = notes;

    openModal(`
        <div class="text-center" style="padding:40px 0;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size:2rem; color:var(--primary);"></i>
            <p style="margin-top:12px; font-size:0.85rem; color:var(--gray-500);">Submitting campaign request...</p>
        </div>
    `);

    API.createAdCampaign(payload).then(res => {
        showPushNotification('Submitted', 'Your campaign request has been submitted for admin review.');
        closeModal();
        fetchVendorAds();
    }).catch(err => {
        showPushNotification('Error', err.message);
        closeModal();
    });
}



// ── 14. VENDOR AUTO-RESPONSE SCREEN ─────────────────────────────────────
function initVendorAutoResponseScreen() {
    const screen = document.getElementById('screen-vendor-auto-response');
    if (!screen) return;

    if (!state.user || !state.user.vendor_id) {
        screen.innerHTML = `<div class="p-section text-center"><p>Access Denied. Vendor profile required.</p></div>`;
        return;
    }

    screen.innerHTML = `
        <div class="p-section">
            <h3>Auto-Response Settings</h3>
            <p class="text-sm text-muted">Configure an automated greeting that sends automatically when clients open a new chat with you.</p>
        </div>
        <div class="p-section" style="padding-top:0;">
            <div class="card p-16 mb-16">
                <div class="form-group">
                    <label class="form-label" style="display:flex; align-items:center; justify-content:space-between;">
                        <span>Auto-Response Message</span>
                        <span style="font-size:0.75rem; color:var(--gray-400);">Max 250 characters</span>
                    </label>
                    <textarea class="form-textarea" id="auto-resp-text" placeholder="e.g. Thanks for reaching out! We typically reply within 1 hour. Please let us know your wedding date and venue." style="min-height:120px;"></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" id="auto-resp-repeat" checked>
                        <span style="font-size:0.83rem;">Only send once to new contacts (don't repeat to existing chats)</span>
                    </label>
                </div>
            </div>
            <button class="btn btn-primary btn-full" onclick="saveAutoResponse()">Save Settings</button>
        </div>
    `;

    API.get('get_vendor_auto_response', { vendor_id: state.user.vendor_id })
        .then(res => {
            const input = document.getElementById('auto-resp-text');
            if (input && res.auto_response) {
                input.value = res.auto_response;
            }
        });
}

function saveAutoResponse() {
    const text = document.getElementById('auto-resp-text').value.trim();
    
    API.post('set_vendor_auto_response', {
        vendor_id: state.user.vendor_id,
        auto_response: text
    }).then(() => {
        showPushNotification('Settings Saved', 'Your auto-response message has been updated.');
    }).catch(err => {
        showPushNotification('Error', err.message);
    });
}


// ── 15. EDIT PROFILE SCREEN ─────────────────────────────────────────────
let currentPhotoFile = null;
let currentPhotoRotation = 0;
let currentPhotoZoom = 1;

function initProfileEditScreen() {
    const screen = document.getElementById('screen-profile-edit');
    if (!screen) return;

    if (!state.user) {
        screen.innerHTML = `<div class="p-section text-center"><p>Please sign in to edit your profile.</p></div>`;
        return;
    }

    const u = state.user;
    const activeRole = u.active_role || 'customer';
    const isVerified = u.kyc_status === 'verified';
    const isFieldLocked = (field, vendorObj) => {
        if (field === 'name') return !!(u && u.name);
        if (field === 'email') return !!(u && u.email);
        if (field === 'phone') return !!(u && u.phone);
        if (field === 'account_number') return !!(vendorObj && vendorObj.account_number);
        return false;
    };

    if (activeRole === 'vendor') {
        API.get('vendor_details', { id: u.vendor_id })
            .then(vendor => {
                renderProfileEditForm(screen, u, vendor, (f) => isFieldLocked(f, vendor));
            })
            .catch(() => {
                renderProfileEditForm(screen, u, null, (f) => isFieldLocked(f, null));
            });
    } else {
        renderProfileEditForm(screen, u, null, (f) => isFieldLocked(f, null));
    }
}

function renderProfileEditForm(container, u, v, isFieldLocked) {
    const activeRole = u.active_role || 'customer';
    const social = v && v.social_links ? (typeof v.social_links === 'string' ? JSON.parse(v.social_links) : v.social_links) : {};
    const isPremium = v && v.premium == 1;

    // Initialize temp state for dynamic vendor fields
    if (activeRole === 'vendor' && v) {
        state.tempGallery = v.gallery ? (typeof v.gallery === 'string' ? JSON.parse(v.gallery) : v.gallery) : [];
        state.tempPackages = v.packages_pricing ? (typeof v.packages_pricing === 'string' ? JSON.parse(v.packages_pricing) : v.packages_pricing) : [];
        state.tempWorkingHours = v.working_hours ? (typeof v.working_hours === 'string' ? JSON.parse(v.working_hours) : v.working_hours) : { always: true };
        state.currentVendorPremium = isPremium;
    }

    container.innerHTML = `
        <div class="p-section" style="padding-bottom:10px;">
            <h3>Edit Profile</h3>
            <p class="text-sm text-muted">Manage your personal details and public settings.</p>
        </div>
        
        <div class="p-section" style="padding-top:0;">
            <!-- Cover Photo Upload (Vendor Only) -->
            ${activeRole === 'vendor' ? `
                <div class="form-group mb-16">
                    <label class="form-label">Cover Banner</label>
                    <div style="position:relative; height:120px; border-radius:12px; overflow:hidden; background:var(--gray-100); border:1px solid var(--gray-200);">
                        <img id="profile-edit-cover-preview" src="${v?.cover_photo || 'img/default-cover.jpg'}" style="width:100%; height:100%; object-fit:cover;">
                        <label style="position:absolute; bottom:8px; right:8px; background:rgba(0,0,0,0.6); color:white; padding:4px 8px; border-radius:4px; font-size:0.7rem; cursor:pointer;">
                            <i class="fa-solid fa-camera"></i> Change Cover
                            <input type="file" accept="image/*" onchange="handleCoverPhotoSelect(event)" style="display:none;">
                        </label>
                    </div>
                </div>
            ` : ''}

            <!-- Profile Photo Upload -->
            <div style="display:flex; align-items:center; gap:16px; margin-bottom:20px;">
                <div style="position:relative;">
                    <img id="profile-edit-avatar-preview" src="${u.avatar || window.DEFAULT_USER_AVATAR}" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid var(--primary);">
                    <label style="position:absolute; bottom:0; right:0; background:var(--primary); color:white; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem; cursor:pointer; box-shadow:0 2px 4px rgba(0,0,0,0.2);">
                        <i class="fa-solid fa-camera"></i>
                        <input type="file" accept="image/*" onchange="handleProfilePhotoSelect(event)" style="display:none;">
                    </label>
                </div>
                <div>
                    <h4 style="margin:0;">Profile Image</h4>
                    <span style="font-size:0.75rem; color:var(--gray-500);">PNG or JPG, max 5MB</span>
                </div>
            </div>

            <!-- Core Fields -->
            <div class="card p-16 mb-16">
                <!-- Name -->
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    ${isFieldLocked('name') ? `
                        <div style="position:relative;">
                            <input type="text" class="form-input" value="${u.name}" disabled style="background:var(--gray-50); padding-right:120px;">
                            <a onclick="openRequestChangeModal('name')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:0.75rem; color:var(--primary); font-weight:600; cursor:pointer;"><i class="fa-solid fa-lock"></i> Request Change</a>
                        </div>
                    ` : `
                        <input type="text" class="form-input" id="edit-name" value="${u.name}">
                    `}
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-input" id="edit-username" value="${u.username || ''}">
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    ${isFieldLocked('email') ? `
                        <div style="position:relative;">
                            <input type="text" class="form-input" value="${u.email || ''}" disabled style="background:var(--gray-50); padding-right:120px;">
                            <a onclick="openRequestChangeModal('email')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:0.75rem; color:var(--primary); font-weight:600; cursor:pointer;"><i class="fa-solid fa-lock"></i> Request Change</a>
                        </div>
                    ` : `
                        <input type="email" class="form-input" id="edit-email" value="${u.email || ''}">
                    `}
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    ${isFieldLocked('phone') ? `
                        <div style="position:relative;">
                            <input type="text" class="form-input" value="${u.phone || ''}" disabled style="background:var(--gray-50); padding-right:120px;">
                            <a onclick="openRequestChangeModal('phone')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:0.75rem; color:var(--primary); font-weight:600; cursor:pointer;"><i class="fa-solid fa-lock"></i> Request Change</a>
                        </div>
                    ` : `
                        <input type="text" class="form-input" id="edit-phone" value="${u.phone || ''}">
                    `}
                </div>

                <!-- DOB -->
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    ${isFieldLocked('dob') ? `
                        <div style="position:relative;">
                            <input type="text" class="form-input" value="${u.dob || ''}" disabled style="background:var(--gray-50); padding-right:120px;">
                            <a onclick="openRequestChangeModal('dob')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:0.75rem; color:var(--primary); font-weight:600; cursor:pointer;"><i class="fa-solid fa-lock"></i> Request Change</a>
                        </div>
                    ` : `
                        <input type="date" class="form-input" id="edit-dob" value="${u.dob || ''}">
                    `}
                </div>

                <!-- Gender -->
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select class="form-select" id="edit-gender">
                        <option value="">Select Gender</option>
                        <option value="Male" ${u.gender === 'Male' ? 'selected' : ''}>Male</option>
                        <option value="Female" ${u.gender === 'Female' ? 'selected' : ''}>Female</option>
                        <option value="Other" ${u.gender === 'Other' ? 'selected' : ''}>Other</option>
                    </select>
                </div>
            </div>

            <!-- Location Settings -->
            <h4 style="margin-bottom:12px;">Location details</h4>
            <div class="card p-16 mb-16">
                <div class="form-group">
                    <label class="form-label">Country</label>
                    <input type="text" class="form-input" id="edit-country" value="${u.country || 'Ghana'}">
                </div>
                <div class="form-group">
                    <label class="form-label">State / Region</label>
                    <input type="text" class="form-input" id="edit-state" value="${u.state || ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" class="form-input" id="edit-city" value="${u.city || ''}">
                </div>
            </div>

            <!-- About & Legal -->
            <h4 style="margin-bottom:12px;">About & Company</h4>
            <div class="card p-16 mb-16" style="display:flex; flex-direction:column; gap:12px;">
                <a href="javascript:void(0)" onclick="navigateTo('about')" style="display:flex; align-items:center; justify-content:space-between; text-decoration:none; color:var(--gray-900); font-weight:600; font-size:0.88rem;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <i class="fa-solid fa-circle-info" style="color:var(--primary); font-size:1.1rem;"></i>
                        <span>About Ohati</span>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="font-size:0.8rem; color:var(--gray-400);"></i>
                </a>
                <a href="javascript:void(0)" onclick="navigateTo('privacy')" style="display:flex; align-items:center; justify-content:space-between; text-decoration:none; color:var(--gray-900); font-weight:600; font-size:0.88rem; border-top:1px solid var(--gray-100); padding-top:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <i class="fa-solid fa-shield-halved" style="color:var(--primary); font-size:1.1rem;"></i>
                        <span>Privacy Policy & Terms</span>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="font-size:0.8rem; color:var(--gray-400);"></i>
                </a>
            </div>

            <!-- Preferences -->
            <h4 style="margin-bottom:12px;">Preferences</h4>
            <div class="card p-16 mb-16">
                <div class="form-group">
                    <label class="form-label">Preferred Language</label>
                    <select class="form-select" id="edit-language">
                        <option value="English" ${u.language === 'English' || !u.language ? 'selected' : ''}>English</option>
                        <option value="French" ${u.language === 'French' ? 'selected' : ''}>French</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Preferred Currency</label>
                    <select class="form-select" id="edit-currency" disabled style="opacity:0.7;">
                        <option value="GHS" selected>Ghana Cedi (GH₵)</option>
                    </select>
                    <div style="font-size:0.65rem; color:var(--gray-400); margin-top:4px;">Ohati operates exclusively in Ghana Cedis (GHS).</div>
                </div>
            </div>

            <!-- Vendor public bio / social links -->
            ${activeRole === 'vendor' && v ? `
                <h4 style="margin-bottom:12px;">Business Details</h4>
                <div class="card p-16 mb-16">
                    <div class="form-group">
                        <label class="form-label">Business Name</label>
                        <input type="text" class="form-input" id="edit-vendor-name" value="${v.name || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="edit-vendor-category">
                            <option value="Photography" ${v.category === 'Photography' ? 'selected' : ''}>Photography</option>
                            <option value="Videography" ${v.category === 'Videography' ? 'selected' : ''}>Videography</option>
                            <option value="Makeup Artists" ${v.category === 'Makeup Artists' ? 'selected' : ''}>Makeup Artists</option>
                            <option value="Bridal Shops" ${v.category === 'Bridal Shops' ? 'selected' : ''}>Bridal Shops</option>
                            <option value="Event Planners" ${v.category === 'Event Planners' ? 'selected' : ''}>Event Planners</option>
                            <option value="Decorators" ${v.category === 'Decorators' ? 'selected' : ''}>Decorators</option>
                            <option value="Caterers" ${v.category === 'Caterers' ? 'selected' : ''}>Caterers</option>
                            <option value="Cake Designers" ${v.category === 'Cake Designers' ? 'selected' : ''}>Cake Designers</option>
                            <option value="Event Venues" ${v.category === 'Event Venues' ? 'selected' : ''}>Event Venues</option>
                            <option value="DJs" ${v.category === 'DJs' ? 'selected' : ''}>DJs</option>
                            <option value="MCs" ${v.category === 'MCs' ? 'selected' : ''}>MCs</option>
                            <option value="Live Bands" ${v.category === 'Live Bands' ? 'selected' : ''}>Live Bands</option>
                            <option value="Florists" ${v.category === 'Florists' ? 'selected' : ''}>Florists</option>
                            <option value="Car Rentals" ${v.category === 'Car Rentals' ? 'selected' : ''}>Car Rentals</option>
                            <option value="Security Services" ${v.category === 'Security Services' ? 'selected' : ''}>Security Services</option>
                            <option value="Chilling Services" ${v.category === 'Chilling Services' ? 'selected' : ''}>Chilling Services</option>
                            <option value="Rental Equipment" ${v.category === 'Rental Equipment' ? 'selected' : ''}>Rental Equipment</option>
                            <option value="Cocktail Bars" ${v.category === 'Cocktail Bars' ? 'selected' : ''}>Cocktail Bars</option>
                            <option value="Honeymoon Packages" ${v.category === 'Honeymoon Packages' ? 'selected' : ''}>Honeymoon Packages</option>
                            <option value="Invitation Designers" ${v.category === 'Invitation Designers' ? 'selected' : ''}>Invitation Designers</option>
                            <option value="Jewelers" ${v.category === 'Jewelers' ? 'selected' : ''}>Jewelers</option>
                            <option value="Lighting" ${v.category === 'Lighting' ? 'selected' : ''}>Lighting</option>
                            <option value="Printing Services" ${v.category === 'Printing Services' ? 'selected' : ''}>Printing Services</option>
                            <option value="Ushers" ${v.category === 'Ushers' ? 'selected' : ''}>Ushers</option>
                            <option value="Content Creators" ${v.category === 'Content Creators' ? 'selected' : ''}>Content Creators</option>
                            <option value="Juice Bar" ${v.category === 'Juice Bar' ? 'selected' : ''}>Juice Bar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Business Phone</label>
                        <input type="text" class="form-input" id="edit-vendor-phone" value="${v.phone || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Business Email</label>
                        <input type="email" class="form-input" id="edit-vendor-email" value="${v.email || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Years of Experience</label>
                        <input type="number" class="form-input" id="edit-vendor-experience" value="${v.experience || 0}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Business Location (City/Town)</label>
                        <input type="text" class="form-input" id="edit-vendor-location" value="${v.location || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Website URL</label>
                        <input type="url" class="form-input" id="edit-vendor-website" placeholder="https://..." value="${v.website || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Average Response Time</label>
                        <select class="form-select" id="edit-vendor-response-time">
                            <option value="Within a few minutes" ${v.response_time === 'Within a few minutes' ? 'selected' : ''}>Within a few minutes</option>
                            <option value="Within an hour" ${v.response_time === 'Within an hour' ? 'selected' : ''}>Within an hour</option>
                            <option value="Within a few hours" ${v.response_time === 'Within a few hours' ? 'selected' : ''}>Within a few hours</option>
                            <option value="Within 24 hours" ${v.response_time === 'Within 24 hours' || !v.response_time ? 'selected' : ''}>Within 24 hours</option>
                            <option value="Within 48 hours" ${v.response_time === 'Within 48 hours' ? 'selected' : ''}>Within 48 hours</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:12px;">
                            <input type="checkbox" id="edit-vendor-insurance" ${parseInt(v.has_insurance) === 1 ? 'checked' : ''}>
                            <span style="font-size:0.83rem; font-weight:600; color:var(--gray-800);">We carry Professional Liability Insurance</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bio / About Business</label>
                        <textarea class="form-textarea" id="edit-bio" style="min-height:100px;">${v.description || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Auto-Welcome Message</label>
                        <textarea class="form-textarea" id="edit-welcome-message" style="min-height:80px;" placeholder="Hello! Thank you for reaching out to us. How can we help you plan your event?">${v.welcome_message || ''}</textarea>
                        <span style="font-size:0.68rem; color:var(--gray-400);">This message will automatically be sent to clients when they start a new chat with you.</span>
                    </div>
                    <div class="form-group" style="position:relative;">
                        <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                            <span><i class="fa-brands fa-instagram"></i> Instagram Link</span>
                            ${!isPremium ? '<span style="font-size:0.65rem; color:#D4AF37; font-weight:700;"><i class="fa-solid fa-lock"></i> Premium</span>' : ''}
                        </label>
                        <input type="text" class="form-input" id="edit-social-instagram" placeholder="${!isPremium ? 'Locked - Premium Feature' : 'https://instagram.com/...'}" value="${isPremium ? (social.instagram || '') : ''}" ${!isPremium ? 'disabled style="background:var(--gray-100); cursor:not-allowed;"' : ''}>
                    </div>
                    <div class="form-group" style="position:relative;">
                        <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                            <span><i class="fa-brands fa-facebook"></i> Facebook Link</span>
                            ${!isPremium ? '<span style="font-size:0.65rem; color:#D4AF37; font-weight:700;"><i class="fa-solid fa-lock"></i> Premium</span>' : ''}
                        </label>
                        <input type="text" class="form-input" id="edit-social-facebook" placeholder="${!isPremium ? 'Locked - Premium Feature' : 'https://facebook.com/...'}" value="${isPremium ? (social.facebook || '') : ''}" ${!isPremium ? 'disabled style="background:var(--gray-100); cursor:not-allowed;"' : ''}>
                    </div>
                    <div class="form-group" style="margin-bottom:0; position:relative;">
                        <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                            <span><i class="fa-brands fa-tiktok"></i> TikTok Link</span>
                            ${!isPremium ? '<span style="font-size:0.65rem; color:#D4AF37; font-weight:700;"><i class="fa-solid fa-lock"></i> Premium</span>' : ''}
                        </label>
                        <input type="text" class="form-input" id="edit-social-tiktok" placeholder="${!isPremium ? 'Locked - Premium Feature' : 'https://tiktok.com/...'}" value="${isPremium ? (social.tiktok || '') : ''}" ${!isPremium ? 'disabled style="background:var(--gray-100); cursor:not-allowed;"' : ''}>
                    </div>
                </div>

                <h4 style="margin-bottom:12px;"><i class="fa-solid fa-images" style="color:var(--primary);"></i> Portfolio / Gallery</h4>
                <div class="card p-16 mb-16">
                    <p class="text-sm text-muted" style="margin-bottom:12px;">Upload images showcasing your work. Clients will see these on your profile.</p>
                    <div id="gallery-section-container"></div>
                </div>

                <h4 style="margin-bottom:12px;"><i class="fa-solid fa-box-open" style="color:var(--primary);"></i> Packages & Pricing</h4>
                <div class="card p-16 mb-16">
                    <p class="text-sm text-muted" style="margin-bottom:12px;">Define your service packages with pricing so clients can compare and book.</p>
                    <div id="packages-section-container"></div>
                </div>

                <h4 style="margin-bottom:12px;"><i class="fa-solid fa-clock" style="color:var(--primary);"></i> Working Hours</h4>
                <div class="card p-16 mb-16">
                    <p class="text-sm text-muted" style="margin-bottom:12px;">Set your weekly availability so clients know when to reach you.</p>
                    <div id="working-hours-section-container"></div>
                </div>
            ` : ''}

            <button class="btn btn-primary btn-full" onclick="saveProfileChanges()">Save Changes</button>
        </div>
    `;

    // Render dynamic vendor sections after DOM is set
    if (activeRole === 'vendor' && v) {
        const galContainer = document.getElementById('gallery-section-container');
        if (galContainer) galContainer.innerHTML = renderGalleryEditHTML();
        const pkgContainer = document.getElementById('packages-section-container');
        if (pkgContainer) pkgContainer.innerHTML = renderPackagesEditHTML();
        const whContainer = document.getElementById('working-hours-section-container');
        if (whContainer) whContainer.innerHTML = renderWorkingHoursEditHTML();
    }
}

// ── Gallery/Portfolio Editing Helpers ──
// ── Gallery/Portfolio Editing Helpers ──
function renderGalleryEditHTML() {
    const gallery = state.tempGallery || [];
    const isPremium = !!state.currentVendorPremium;
    const maxImages = 100;
    const perPage = 26;
    const page = state.editorGalleryPage || 1;
    const totalPages = Math.ceil(gallery.length / perPage) || 1;
    const startIdx = (page - 1) * perPage;
    const pageSlice = gallery.slice(startIdx, startIdx + perPage);

    let html = '';

    if (!isPremium) {
        html += `
            <div style="background:linear-gradient(135deg, #FFFBEB, #FEF3C7); border:1px dashed #F59E0B; border-radius:12px; padding:16px; text-align:center; margin-bottom:12px;">
                <div style="font-size:0.9rem; font-weight:800; color:#92400E; margin-bottom:4px; display:flex; align-items:center; justify-content:center; gap:6px;">
                    <i class="fa-solid fa-crown" style="color:#D4AF37;"></i> Portfolio Showcase Locked
                </div>
                <div style="font-size:0.75rem; color:var(--gray-600); margin-bottom:10px; line-height:1.4;">
                    Uploading portfolio images is exclusive to <strong>Premium Vendors</strong>. Upgrade to Premium to showcase up to 100 high-resolution work photos & videos to clients!
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="openPremiumUpgradeModal()" style="font-weight:700;">
                    <i class="fa-solid fa-crown"></i> Upgrade to Premium (Up to 100 Photos)
                </button>
            </div>
        `;
        return html;
    }

    html += `<div style="font-size:0.75rem; color:var(--gray-500); margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
        <span>Portfolio Images: <strong>${gallery.length}/${maxImages}</strong></span>
        <span style="color:var(--success); font-weight:700;"><i class="fa-solid fa-crown"></i> Premium Active (Max 100)</span>
    </div>`;

    if (gallery.length > 0) {
        html += `<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; margin-bottom:12px;">`;
        pageSlice.forEach((img, i) => {
            const actualIndex = startIdx + i;
            html += `
                <div style="position:relative; height:80px; border-radius:8px; overflow:hidden; border:1px solid var(--gray-200);">
                    <img src="${img}" style="width:100%; height:100%; object-fit:cover;">
                    <button style="position:absolute; top:4px; right:4px; width:20px; height:20px; border-radius:50%; background:rgba(0,0,0,0.6); color:white; border:none; display:flex; align-items:center; justify-content:center; font-size:0.6rem; cursor:pointer;" onclick="removeGalleryPhoto(${actualIndex})" title="Remove">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            `;
        });
        html += `</div>`;

        if (totalPages > 1) {
            html += `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; font-size:0.75rem; background:var(--gray-50); padding:6px 10px; border-radius:8px; border:1px solid var(--gray-200);">
                    <button type="button" class="btn btn-xs btn-outline" onclick="changeEditorGalleryPage(-1)" ${page <= 1 ? 'disabled' : ''}>
                        <i class="fa-solid fa-chevron-left"></i> Prev
                    </button>
                    <span style="font-weight:700; color:var(--primary);">Page ${page} of ${totalPages} (Showing ${startIdx + 1}-${Math.min(startIdx + perPage, gallery.length)})</span>
                    <button type="button" class="btn btn-xs btn-outline" onclick="changeEditorGalleryPage(1)" ${page >= totalPages ? 'disabled' : ''}>
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            `;
        }
    } else {
        html += `<div style="text-align:center; padding:16px 0; color:var(--gray-400); font-size:0.75rem;"><i class="fa-solid fa-image" style="font-size:1.5rem; margin-bottom:6px; display:block;"></i>No portfolio images uploaded yet</div>`;
    }

    if (gallery.length < maxImages) {
        html += `
            <label class="btn btn-outline btn-full btn-sm" style="cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload Portfolio Photo
                <input type="file" accept="image/*" onchange="handleGalleryUploadSelect(event)" style="display:none;">
            </label>
        `;
    }
    return html;
}

window.changeEditorGalleryPage = function(delta) {
    state.editorGalleryPage = (state.editorGalleryPage || 1) + delta;
    if (state.editorGalleryPage < 1) state.editorGalleryPage = 1;
    const container = document.getElementById('gallery-section-container');
    if (container) container.innerHTML = renderGalleryEditHTML();
};

window.removeGalleryPhoto = function(index) {
    state.tempGallery.splice(index, 1);
    const container = document.getElementById('gallery-section-container');
    if (container) container.innerHTML = renderGalleryEditHTML();
};

window.handleGalleryUploadSelect = function(event) {
    const file = event.target.files[0];
    if (!file) return;
    const isPremium = !!state.currentVendorPremium;
    if (!isPremium) {
        if (typeof openPremiumUpgradeModal === 'function') {
            openPremiumUpgradeModal();
        } else {
            showPushNotification('Premium Feature 👑', 'Portfolio Showcase is exclusive to Premium Vendors! Upgrade to upload up to 100 photos.');
        }
        return;
    }

    if ((state.tempGallery || []).length >= 100) {
        showPushNotification('Limit Reached', 'Premium vendors can upload up to 100 portfolio images.');
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        showPushNotification('File Too Large', 'Max upload size is 5MB.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        state.tempGallery.push(e.target.result);
        const container = document.getElementById('gallery-section-container');
        if (container) container.innerHTML = renderGalleryEditHTML();
        showPushNotification('Photo Added', 'Click Save to publish your updated portfolio gallery.');
    };
    reader.readAsDataURL(file);
};

// ── Packages & Pricing Editing Helpers ──
function renderPackagesEditHTML() {
    const pkgs = state.tempPackages || [];
    let html = '<div style="display:flex; flex-direction:column; gap:12px;">';
    if (pkgs.length === 0) {
        html += `
            <div style="text-align:center; padding:16px; color:var(--gray-500); background:var(--gray-50); border:1px dashed var(--gray-300); border-radius:10px; font-size:0.78rem;">
                <i class="fa-solid fa-box-open" style="font-size:1.4rem; color:var(--gray-400); margin-bottom:4px; display:block;"></i>
                No custom packages created. Adding packages is optional.
            </div>
        `;
    }
    pkgs.forEach((pkg, index) => {
        html += `
            <div class="card" style="padding:12px; background:var(--gray-50); border:1px solid var(--gray-200); position:relative;">
                <button style="position:absolute; top:8px; right:8px; padding:4px; color:var(--danger); background:none; border:none; cursor:pointer; font-size:0.75rem;" onclick="removeProfilePackage(${index})" title="Delete Package">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
                <div class="form-group" style="margin-bottom:8px;">
                    <label class="form-label" style="font-size:0.65rem;">Package Name</label>
                    <input type="text" class="form-input" style="padding:6px 8px; font-size:0.75rem;" value="${(pkg.name || '').replace(/"/g, '&quot;')}" oninput="updateProfilePackage(${index}, 'name', this.value)" placeholder="e.g. Standard Wedding Package">
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:0.65rem;">Details / Inclusions</label>
                    <textarea class="form-textarea" style="padding:6px 8px; font-size:0.75rem; min-height:50px;" oninput="updateProfilePackage(${index}, 'details', this.value)" placeholder="e.g. Full day coverage, 150 edited photos...">${pkg.details || ''}</textarea>
                </div>
            </div>
        `;
    });
    html += `<button class="btn btn-outline btn-full btn-sm" style="margin-top:4px;" onclick="addProfilePackage()"><i class="fa-solid fa-plus"></i> Add Package</button></div>`;
    return html;
}

window.addProfilePackage = function() {
    if (!state.tempPackages) state.tempPackages = [];
    state.tempPackages.push({ name: '', price: '', details: '' });
    const container = document.getElementById('packages-section-container');
    if (container) container.innerHTML = renderPackagesEditHTML();
};

window.updateProfilePackage = function(index, field, value) {
    if (state.tempPackages && state.tempPackages[index]) {
        state.tempPackages[index][field] = value;
    }
};

window.removeProfilePackage = function(index) {
    state.tempPackages.splice(index, 1);
    const container = document.getElementById('packages-section-container');
    if (container) container.innerHTML = renderPackagesEditHTML();
};

// ── Working Hours Editing Helpers ──
function renderWorkingHoursEditHTML() {
    const wh = state.tempWorkingHours || { always: true };
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    let html = `
        <div style="margin-bottom:12px;">
            <label style="display:flex; align-items:center; gap:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                <input type="checkbox" id="edit-vendor-always-open" ${wh.always ? 'checked' : ''} onchange="toggleAlwaysOpenEdit(this.checked)">
                <span>Always Available (24/7)</span>
            </label>
        </div>
        <div id="working-hours-days-grid" style="display:${wh.always ? 'none' : 'block'};">
    `;
    days.forEach(day => {
        const dayData = wh[day] || { active: false, start: '09:00', end: '17:00' };
        html += `
            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:6px 0; border-bottom:1px solid var(--gray-100);">
                <label style="display:flex; align-items:center; gap:6px; font-size:0.75rem; font-weight:600; min-width:85px; cursor:pointer;">
                    <input type="checkbox" data-day="${day}" ${dayData.active ? 'checked' : ''} onchange="updateWorkingDayActive('${day}', this.checked)">
                    <span>${day.substring(0, 3)}</span>
                </label>
                <div id="wh-time-${day}" style="display:${dayData.active ? 'flex' : 'none'}; gap:6px; align-items:center;">
                    <input type="time" class="form-input" style="padding:4px 6px; font-size:0.7rem; width:90px;" value="${dayData.start || '09:00'}" onchange="updateWorkingDayTime('${day}', 'start', this.value)">
                    <span style="font-size:0.7rem; color:var(--gray-400);">to</span>
                    <input type="time" class="form-input" style="padding:4px 6px; font-size:0.7rem; width:90px;" value="${dayData.end || '17:00'}" onchange="updateWorkingDayTime('${day}', 'end', this.value)">
                </div>
                <div id="wh-closed-${day}" style="display:${dayData.active ? 'none' : 'block'}; font-size:0.7rem; color:var(--gray-400); font-style:italic;">Closed</div>
            </div>
        `;
    });
    html += `</div>`;
    return html;
}

window.toggleAlwaysOpenEdit = function(checked) {
    state.tempWorkingHours.always = checked;
    const grid = document.getElementById('working-hours-days-grid');
    if (grid) grid.style.display = checked ? 'none' : 'block';
};

window.updateWorkingDayActive = function(day, checked) {
    if (!state.tempWorkingHours[day]) {
        state.tempWorkingHours[day] = { active: false, start: '09:00', end: '17:00' };
    }
    state.tempWorkingHours[day].active = checked;
    const timeEl = document.getElementById('wh-time-' + day);
    const closedEl = document.getElementById('wh-closed-' + day);
    if (timeEl) timeEl.style.display = checked ? 'flex' : 'none';
    if (closedEl) closedEl.style.display = checked ? 'none' : 'block';
};

window.updateWorkingDayTime = function(day, field, value) {
    if (!state.tempWorkingHours[day]) {
        state.tempWorkingHours[day] = { active: true, start: '09:00', end: '17:00' };
    }
    state.tempWorkingHours[day][field] = value;
};

window.togglePayoutFields = function(method) {
    const momo = document.getElementById('payout-momo-fields');
    const bank = document.getElementById('payout-bank-fields');
    if (method === 'bank') {
        if (momo) momo.style.display = 'none';
        if (bank) bank.style.display = 'block';
    } else {
        if (momo) momo.style.display = 'block';
        if (bank) bank.style.display = 'none';
    }
};

function handleProfilePhotoSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    currentPhotoFile = file;
    currentPhotoRotation = 0;
    currentPhotoZoom = 1;

    const reader = new FileReader();
    reader.onload = function(e) {
        openPhotoEditorModal(e.target.result);
    };
    reader.readAsDataURL(file);
}

function openPhotoEditorModal(imgSrc) {
    const html = `
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Edit Photo</h2>
            <p class="auth-modal-subtitle">Crop, Zoom and Rotate</p>
        </div>
        <div style="display:flex; justify-content:center; align-items:center; background:var(--gray-900); padding:20px; border-radius:12px; margin-bottom:16px; overflow:hidden; position:relative; height:260px;">
            <img id="edit-preview-img" src="${imgSrc}" style="max-height:220px; max-width:100%; transition:transform 0.1s ease; transform: rotate(${currentPhotoRotation}deg) scale(${currentPhotoZoom});">
        </div>
        <div class="form-group">
            <label class="form-label" style="display:flex; justify-content:space-between;">
                <span>Zoom</span>
                <strong id="edit-zoom-val">1.0x</strong>
            </label>
            <input type="range" min="0.5" max="3" step="0.1" class="form-range" id="edit-zoom-range" value="1" oninput="updatePhotoEditTransform()">
        </div>
        <div class="form-group">
            <label class="form-label" style="display:flex; justify-content:space-between;">
                <span>Rotate</span>
                <strong id="edit-rotate-val">0°</strong>
            </label>
            <input type="range" min="-180" max="180" step="90" class="form-range" id="edit-rotate-range" value="0" oninput="updatePhotoEditTransform()">
        </div>
        <button class="btn btn-primary btn-full mt-12" onclick="saveEditedPhoto()">Apply Photo</button>
    `;
    openModal(html);
}

window.updatePhotoEditTransform = function() {
    const zoom = parseFloat(document.getElementById('edit-zoom-range').value);
    const rotate = parseInt(document.getElementById('edit-rotate-range').value);
    
    currentPhotoZoom = zoom;
    currentPhotoRotation = rotate;
    
    const img = document.getElementById('edit-preview-img');
    if (img) {
        img.style.transform = `rotate(${rotate}deg) scale(${zoom})`;
    }
    
    const zoomVal = document.getElementById('edit-zoom-val');
    if (zoomVal) zoomVal.textContent = zoom.toFixed(1) + 'x';
    
    const rotateVal = document.getElementById('edit-rotate-val');
    if (rotateVal) rotateVal.textContent = rotate + '°';
};

window.saveEditedPhoto = function() {
    const img = new Image();
    img.onload = function() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const size = 300;
        canvas.width = size;
        canvas.height = size;
        
        ctx.translate(size/2, size/2);
        ctx.rotate(currentPhotoRotation * Math.PI / 180);
        ctx.scale(currentPhotoZoom, currentPhotoZoom);
        ctx.drawImage(img, -size/2, -size/2, size, size);
        
        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
        
        state.tempAvatarUrl = dataUrl;
        const formPreview = document.getElementById('profile-edit-avatar-preview');
        if (formPreview) formPreview.src = dataUrl;
        
        closeModal();
        showPushNotification('Photo Updated', 'Profile picture preview updated. Click Save to publish.');
    };
    img.src = document.getElementById('edit-preview-img').src;
};

function openRequestChangeModal(fieldName) {
    const html = `
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Request Profile Update</h2>
            <p class="auth-modal-subtitle">Submit verification details to update locked field: ${fieldName}</p>
        </div>
        <div class="form-group">
            <label class="form-label">New Value</label>
            <input type="text" class="form-input" id="req-new-value" placeholder="Enter new ${fieldName}">
        </div>
        <div class="form-group">
            <label class="form-label">Supporting Document (ID/Certificate/etc.)</label>
            <input type="file" id="req-doc-file" class="form-input" accept="image/*,application/pdf">
        </div>
        <button class="btn btn-primary btn-full mt-12" id="req-submit-btn" onclick="submitRequestChange('${fieldName}')">Submit Request</button>
    `;
    openModal(html);
}

function submitRequestChange(fieldName) {
    const val = document.getElementById('req-new-value').value.trim();
    const docInput = document.getElementById('req-doc-file');
    
    if (!val) {
        showPushNotification('Error', 'Please enter the new value.');
        return;
    }
    
    const btn = document.getElementById('req-submit-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }

    let docUrl = 'doc_uploaded.jpg';
    if (docInput && docInput.files[0]) {
        docUrl = docInput.files[0].name;
    }

    API.post('submit_profile_change_request', {
        field_name: fieldName,
        new_value: val,
        supporting_document: docUrl
    }).then(res => {
        showPushNotification('Request Sent', res.message);
        closeModal();
    }).catch(err => {
        showPushNotification('Error', err.message);
        if (btn) { btn.disabled = false; btn.textContent = 'Submit Request'; }
    });
}

function saveProfileChanges() {
    const saveBtn = document.querySelector('button[onclick="saveProfileChanges()"]');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Saving Changes...';
    }

    const payload = {};
    const textFields = ['name', 'username', 'email', 'phone', 'dob', 'gender', 'country', 'state', 'city', 'language', 'currency'];
    
    textFields.forEach(f => {
        const el = document.getElementById('edit-' + f);
        if (el && el.value !== undefined) payload[f] = el.value.trim();
    });

    if (state.tempAvatarUrl) {
        payload.avatar = state.tempAvatarUrl;
    }

    API.updateProfile(payload)
        .then(() => {
            const activeRole = state.user ? (state.user.active_role || state.user.role) : 'customer';
            const vendorId = state.user ? (state.user.vendor_id || (state.vendor ? state.vendor.id : 0)) : 0;
            
            if (activeRole === 'vendor' && vendorId > 0) {
                const bio = document.getElementById('edit-bio')?.value.trim() || '';
                const social = {
                    instagram: document.getElementById('edit-social-instagram')?.value.trim() || '',
                    facebook: document.getElementById('edit-social-facebook')?.value.trim() || '',
                    tiktok: document.getElementById('edit-social-tiktok')?.value.trim() || ''
                };
                
                return API.updateVendor({
                    id: vendorId,
                    name: document.getElementById('edit-vendor-name')?.value.trim() || '',
                    category: document.getElementById('edit-vendor-category')?.value || '',
                    phone: document.getElementById('edit-vendor-phone')?.value.trim() || '',
                    email: document.getElementById('edit-vendor-email')?.value.trim() || '',
                    experience: parseInt(document.getElementById('edit-vendor-experience')?.value || 0),
                    location: document.getElementById('edit-vendor-location')?.value.trim() || '',
                    website: document.getElementById('edit-vendor-website')?.value.trim() || '',
                    description: bio,
                    social_links: social,
                    gallery: state.tempGallery,
                    packages_pricing: state.tempPackages,
                    working_hours: state.tempWorkingHours,
                    welcome_message: document.getElementById('edit-welcome-message')?.value.trim() || '',
                    response_time: document.getElementById('edit-vendor-response-time')?.value || 'Within 24 hours',
                    gps_lat: parseFloat(document.getElementById('edit-vendor-gps-lat')?.value || 0),
                    gps_lng: parseFloat(document.getElementById('edit-vendor-gps-lng')?.value || 0),
                    has_insurance: document.getElementById('edit-vendor-insurance')?.checked ? 1 : 0
                });
            }
        })
        .then(() => {
            return API.getSession().catch(() => null);
        })
        .then(res => {
            if (res && res.user) {
                state.user = res.user;
                if (res.vendor) state.vendor = res.vendor;
                try {
                    localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
                } catch (e) {}
            }
            showPushNotification('Profile Updated 🎉', 'Your profile details have been saved successfully.');
            if (typeof updateSidebarUI === 'function') updateSidebarUI();
            if (typeof navigateTo === 'function') navigateTo('profile');
        })
        .catch(err => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'Save Changes';
            }
            showPushNotification('Error saving changes', err.message || 'Could not save profile changes.');
        });
}

function handleCoverPhotoSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('profile-edit-cover-preview');
        if (preview) preview.src = e.target.result;
        
        API.updateVendor({
            id: state.user.vendor_id,
            cover_photo: e.target.result
        }).then(() => {
            showPushNotification('Cover Updated', 'Cover banner updated successfully.');
        }).catch(err => showPushNotification('Error', err.message));
    };
    reader.readAsDataURL(file);
}

// ── REPORT AN ISSUE SCREEN ─────────────────────────────────────────────
function initReportIssueScreen() {
    const screen = document.getElementById('screen-report-issue');
    if (!screen) return;

    screen.innerHTML = `
        <div class="p-section" style="padding-bottom:10px;">
            <h3 style="font-family:'Fraunces',serif;margin-bottom:4px;">Report an Issue</h3>
            <p style="font-size:0.75rem;color:var(--gray-500);margin:0;">Help us improve Ohati by reporting bugs, glitches or suggestions.</p>
        </div>
        <div class="p-section" style="padding-top:0;">
            <div class="form-group">
                <label class="form-label">Issue Title</label>
                <input type="text" class="form-input" id="report-title" placeholder="e.g. Payment button not working" required>
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select class="form-input" id="report-category">
                    <option value="Bug">Bug / Error</option>
                    <option value="UI/UX">UI / Design Issue</option>
                    <option value="Payment">Payment Problem</option>
                    <option value="Booking">Booking Issue</option>
                    <option value="Account">Account / Profile</option>
                    <option value="Feature">Feature Request</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-input" id="report-description" rows="5" placeholder="Describe what happened, what you expected, and steps to reproduce..." style="resize:vertical;min-height:100px;"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Screenshot <span style="font-weight:400;color:var(--gray-400);">(optional)</span></label>
                <div id="report-screenshot-area" style="border:2px dashed var(--gray-200);border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:border-color 0.2s;" onclick="document.getElementById('report-screenshot-input').click()">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.5rem;color:var(--gray-300);margin-bottom:6px;display:block;"></i>
                    <p style="font-size:0.75rem;color:var(--gray-400);margin:0;">Tap to upload a screenshot</p>
                    <input type="file" id="report-screenshot-input" accept="image/*" style="display:none;" onchange="previewReportScreenshot(this)">
                </div>
                <div id="report-screenshot-preview" style="display:none;margin-top:10px;position:relative;">
                    <img id="report-screenshot-img" src="" alt="Preview" style="width:100%;max-height:200px;object-fit:contain;border-radius:8px;border:1px solid var(--gray-200);">
                    <button onclick="clearReportScreenshot()" style="position:absolute;top:6px;right:6px;width:24px;height:24px;border-radius:50%;border:none;background:rgba(0,0,0,0.6);color:white;font-size:0.7rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <button class="btn btn-primary btn-full mt-12" onclick="submitReportIssue()" style="height:44px;font-size:0.85rem;">
                <i class="fa-solid fa-paper-plane"></i> Submit Report
            </button>
        </div>
    `;
}

function previewReportScreenshot(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('report-screenshot-img').src = e.target.result;
            document.getElementById('report-screenshot-preview').style.display = 'block';
            document.getElementById('report-screenshot-area').style.borderColor = 'var(--accent)';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearReportScreenshot() {
    document.getElementById('report-screenshot-input').value = '';
    document.getElementById('report-screenshot-preview').style.display = 'none';
    document.getElementById('report-screenshot-img').src = '';
    document.getElementById('report-screenshot-area').style.borderColor = 'var(--gray-200)';
}

async function submitReportIssue() {
    const title = document.getElementById('report-title').value.trim();
    const category = document.getElementById('report-category').value;
    const description = document.getElementById('report-description').value.trim();
    const screenshotImg = document.getElementById('report-screenshot-img');
    const screenshot = screenshotImg && screenshotImg.src && screenshotImg.src.startsWith('data:') ? screenshotImg.src : '';

    if (!title || !description) {
        showPushNotification('Missing Fields', 'Please fill in the title and description.');
        return;
    }

    try {
        const data = await API.post('report_issue', { title, category, description, screenshot });
        if (data && data.success) {
            showPushNotification('Report Submitted', 'Thank you! Our team will review your report.');
            if (document.getElementById('report-title')) document.getElementById('report-title').value = '';
            if (document.getElementById('report-description')) document.getElementById('report-description').value = '';
            if (typeof clearReportScreenshot === 'function') clearReportScreenshot();
        } else {
            showPushNotification('Error', (data && data.error) ? data.error : 'Could not submit report.');
        }
    } catch (e) {
        showPushNotification('Error', e.message || 'Could not submit report.');
    }
}

function previewChatImage(imgUrl) {
    // Remove existing preview if any
    const existing = document.getElementById('image-preview-overlay');
    if (existing) existing.remove();

    // Create the overlay container
    const overlay = document.createElement('div');
    overlay.id = 'image-preview-overlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.background = 'rgba(10, 10, 10, 0.9)';
    overlay.style.backdropFilter = 'blur(10px)';
    overlay.style.webkitBackdropFilter = 'blur(10px)';
    overlay.style.zIndex = '999999';
    overlay.style.display = 'flex';
    overlay.style.flexDirection = 'column';
    overlay.style.justifyContent = 'center';
    overlay.style.alignItems = 'center';
    overlay.style.opacity = '0';
    overlay.style.transition = 'opacity 0.25s ease';

    overlay.innerHTML = `
        <!-- Top Toolbar -->
        <div style="position:absolute; top:20px; right:20px; display:flex; align-items:center; gap:12px; z-index:1000000;">
            <!-- Save/Download Button -->
            <a href="${imgUrl}" download="Ohati_Chat_Image.jpg" class="btn btn-primary" style="padding:10px 20px; border-radius:30px; font-weight:700; display:inline-flex; align-items:center; gap:8px; text-decoration:none; box-shadow:0 4px 12px rgba(0,0,0,0.3); background:var(--accent, #D4AF37); color:var(--primary-dark, #0c1a30); border:none; height:44px; font-size: 0.9rem;">
                <i class="fa-solid fa-download"></i> Save
            </a>
            <!-- Close Button -->
            <button onclick="closeImagePreview()" style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.25); padding:0; border-radius:50%; width:44px; height:44px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.3rem; transition:all 0.2s; outline:none;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <!-- Image display -->
        <div style="max-width:92%; max-height:82%; display:flex; justify-content:center; align-items:center;" onclick="event.stopPropagation()">
            <img src="${imgUrl}" style="max-width:100%; max-height:80vh; object-fit:contain; border-radius:16px; box-shadow:0 12px 40px rgba(0,0,0,0.6); transition:transform 0.3s ease;">
        </div>
    `;

    // Close preview on clicking the backdrop overlay itself
    overlay.onclick = closeImagePreview;

    document.body.appendChild(overlay);

    // Trigger transition
    setTimeout(() => {
        overlay.style.opacity = '1';
    }, 10);
}

function closeImagePreview() {
    const overlay = document.getElementById('image-preview-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => {
            overlay.remove();
        }, 250);
    }
}

// Active audio playback manager for chat voice notes
let activeChatAudioInstance = null;
let activeChatAudioButton = null;
let activeChatAudioProgress = null;
let activeChatAudioTime = null;

function handleVoicePlayerClick(btn) {
    const playerDiv = btn.closest('.custom-voice-player');
    if (!playerDiv) return;
    const audioUrl = playerDiv.getAttribute('data-src');
    if (!audioUrl) return;
    
    const progressBar = playerDiv.querySelector('.voice-progress');
    const timeLbl = playerDiv.querySelector('.voice-duration');
    
    if (activeChatAudioInstance && activeChatAudioButton === btn) {
        if (activeChatAudioInstance.paused) {
            activeChatAudioInstance.play().then(() => {
                btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
            }).catch(err => {
                console.error("Audio playback error:", err);
                btn.innerHTML = '<i class="fa-solid fa-play"></i>';
            });
        } else {
            activeChatAudioInstance.pause();
            btn.innerHTML = '<i class="fa-solid fa-play"></i>';
        }
        return;
    }
    
    if (activeChatAudioInstance) {
        activeChatAudioInstance.pause();
        if (activeChatAudioButton) {
            activeChatAudioButton.innerHTML = '<i class="fa-solid fa-play"></i>';
        }
        if (activeChatAudioProgress) {
            activeChatAudioProgress.value = 0;
        }
    }
    
    activeChatAudioButton = btn;
    activeChatAudioProgress = progressBar;
    activeChatAudioTime = timeLbl;
    
    activeChatAudioInstance = new Audio(audioUrl);
    
    activeChatAudioInstance.addEventListener('loadedmetadata', () => {
        if (timeLbl && activeChatAudioInstance.duration && activeChatAudioInstance.duration !== Infinity) {
            timeLbl.textContent = formatTimeLabel(activeChatAudioInstance.duration);
        }
    });

    activeChatAudioInstance.addEventListener('timeupdate', () => {
        if (progressBar && activeChatAudioInstance.duration && activeChatAudioInstance.duration !== Infinity) {
            const pct = (activeChatAudioInstance.currentTime / activeChatAudioInstance.duration) * 100;
            progressBar.value = isNaN(pct) ? 0 : pct;
        }
        if (timeLbl) {
            timeLbl.textContent = formatTimeLabel(activeChatAudioInstance.currentTime);
        }
    });
    
    activeChatAudioInstance.addEventListener('ended', () => {
        btn.innerHTML = '<i class="fa-solid fa-play"></i>';
        if (progressBar) progressBar.value = 0;
        if (timeLbl && activeChatAudioInstance.duration && activeChatAudioInstance.duration !== Infinity) {
            timeLbl.textContent = formatTimeLabel(activeChatAudioInstance.duration);
        }
        activeChatAudioInstance = null;
        activeChatAudioButton = null;
        activeChatAudioProgress = null;
        activeChatAudioTime = null;
    });
    
    activeChatAudioInstance.play().then(() => {
        btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
    }).catch(err => {
        console.error("Audio play failed:", err);
        btn.innerHTML = '<i class="fa-solid fa-play"></i>';
    });
}

function handleVoicePlayerSeek(rangeInput) {
    const playerDiv = rangeInput.closest('.custom-voice-player');
    const btn = playerDiv.querySelector('.voice-play-btn');
    
    if (activeChatAudioInstance && activeChatAudioButton === btn && activeChatAudioInstance.duration) {
        activeChatAudioInstance.currentTime = (parseFloat(rangeInput.value) / 100) * activeChatAudioInstance.duration;
    }
}

// ── EVENT JOBS MARKETPLACE SCREEN INITIALIZERS ─────────────────────────
async function initUserJobsScreen() {
    const container = document.getElementById('screen-user-jobs');
    if (!container) return;

    container.innerHTML = `
        <div style="padding:20px; max-width:1100px; margin:0 auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
                <div>
                    <h2 style="margin:0; color:var(--primary, #1B2B4B); font-size:1.4rem;"><i class="fa-solid fa-briefcase" style="color:var(--accent, #F2A735); margin-right:8px;"></i>My Event Jobs</h2>
                    <span style="color:var(--gray-600); font-size:0.88rem;">Manage your posted jobs, proposals, and hired vendors.</span>
                </div>
                <button class="btn btn-primary" onclick="JobsModule.openPostJobModal()"><i class="fa-solid fa-plus" style="margin-right:6px;"></i> Post New Job</button>
            </div>

            <div id="user-jobs-stats-container" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:14px; margin-bottom:24px;">
                <div class="stat-card" style="background:#fff; border:1px solid var(--gray-200); padding:14px; border-radius:12px; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:var(--primary);" id="uj-stat-posted">0</div>
                    <div style="font-size:0.78rem; color:var(--gray-600); font-weight:600;">Total Jobs Posted</div>
                </div>
                <div class="stat-card" style="background:#fff; border:1px solid var(--gray-200); padding:14px; border-radius:12px; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#2563EB;" id="uj-stat-active">0</div>
                    <div style="font-size:0.78rem; color:var(--gray-600); font-weight:600;">Active Jobs</div>
                </div>
                <div class="stat-card" style="background:#fff; border:1px solid var(--gray-200); padding:14px; border-radius:12px; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#D97706;" id="uj-stat-apps">0</div>
                    <div style="font-size:0.78rem; color:var(--gray-600); font-weight:600;">Applications Received</div>
                </div>
                <div class="stat-card" style="background:#fff; border:1px solid var(--gray-200); padding:14px; border-radius:12px; text-align:center;">
                    <div style="font-size:1.5rem; font-weight:800; color:#16A34A;" id="uj-stat-hired">0</div>
                    <div style="font-size:0.78rem; color:var(--gray-600); font-weight:600;">Hired Vendors</div>
                </div>
            </div>

            <div style="display:flex; gap:10px; border-bottom:2px solid var(--gray-200); margin-bottom:20px; overflow-x:auto;">
                <button class="tab-btn active" onclick="switchUserJobsTab('active', this)" style="padding:10px 16px; font-weight:700; border:none; background:none; cursor:pointer; color:var(--primary); border-bottom:3px solid var(--accent);">Active Jobs</button>
                <button class="tab-btn" onclick="switchUserJobsTab('drafts', this)" style="padding:10px 16px; font-weight:600; border:none; background:none; cursor:pointer; color:var(--gray-500);">Drafts</button>
                <button class="tab-btn" onclick="switchUserJobsTab('closed', this)" style="padding:10px 16px; font-weight:600; border:none; background:none; cursor:pointer; color:var(--gray-500);">Closed / Hired</button>
            </div>

            <div id="user-jobs-list-container">${renderSkeletonCardsHTML(4)}</div>
        </div>
    `;

    try {
        const res = await API.get('job_get_user_dashboard');
        if (res && res.success) {
            document.getElementById('uj-stat-posted').innerText = res.stats.total_posted || 0;
            document.getElementById('uj-stat-active').innerText = res.stats.active_count || 0;
            document.getElementById('uj-stat-apps').innerText = res.stats.total_applications || 0;
            document.getElementById('uj-stat-hired').innerText = res.stats.total_hired || 0;

            window._userJobsData = res.jobs;
            renderUserJobsTab('active');
        }
    } catch (e) {
        console.error('Failed to load user jobs dashboard:', e);
    }
}

function switchUserJobsTab(tabKey, btnElem) {
    document.querySelectorAll('#screen-user-jobs .tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.color = 'var(--gray-500)';
        b.style.borderBottom = 'none';
        b.style.fontWeight = '600';
    });
    if (btnElem) {
        btnElem.classList.add('active');
        btnElem.style.color = 'var(--primary)';
        btnElem.style.borderBottom = '3px solid var(--accent)';
        btnElem.style.fontWeight = '700';
    }
    renderUserJobsTab(tabKey);
}

function renderUserJobsTab(tabKey) {
    const listContainer = document.getElementById('user-jobs-list-container');
    if (!listContainer) return;

    const jobs = (window._userJobsData && window._userJobsData[tabKey]) ? window._userJobsData[tabKey] : [];

    if (jobs.length === 0) {
        listContainer.innerHTML = `
            <div style="text-align:center; padding:40px 20px; background:#fff; border-radius:12px; border:1px solid var(--gray-200);">
                <i class="fa-solid fa-briefcase" style="font-size:2.5rem; color:var(--gray-400); margin-bottom:10px;"></i>
                <h4 style="margin:0; color:var(--primary);">No ${tabKey} jobs found</h4>
                <p style="color:var(--gray-500); font-size:0.88rem; margin:6px 0 16px;">Post an event job to start receiving proposals from top vendors.</p>
                <button class="btn btn-primary btn-sm" onclick="JobsModule.openPostJobModal()"><i class="fa-solid fa-plus"></i> Post a Job</button>
            </div>
        `;
        return;
    }

    listContainer.innerHTML = jobs.map(j => `
        <div class="job-card" style="background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:18px; margin-bottom:16px; display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                <div>
                    <span class="badge" style="background:rgba(27,43,75,0.08); color:var(--primary); padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700;">${escapeHtml(j.category)}</span>
                    ${j.is_urgent == 1 ? `<span class="badge" style="background:#FEE2E2; color:#DC2626; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; margin-left:6px;"><i class="fa-solid fa-bolt"></i> URGENT</span>` : ''}
                    <h3 style="margin:6px 0 2px; font-size:1.1rem; color:var(--primary);">${escapeHtml(j.title)}</h3>
                    <span style="font-size:0.8rem; color:var(--gray-500);"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(j.location || 'Accra')} • Posted ${escapeHtml(j.created_at || 'Recently')}</span>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:1.2rem; font-weight:800; color:var(--primary);">GHS ${number_format(j.budget, 2)}</div>
                    <span style="font-size:0.75rem; color:var(--gray-500);">${j.negotiable == 1 ? 'Negotiable' : 'Fixed Budget'}</span>
                </div>
            </div>

            <p style="font-size:0.85rem; color:var(--gray-600); line-height:1.4; margin:0;">
                ${escapeHtml(j.description.substring(0, 160))}${j.description.length > 160 ? '...' : ''}
            </p>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--gray-200); padding-top:12px; margin-top:4px;">
                <div style="font-size:0.8rem; color:var(--gray-600); display:flex; gap:14px;">
                    <span><i class="fa-solid fa-users" style="color:var(--accent);"></i> <strong>${j.applications_count}</strong> Proposals</span>
                    <span><i class="fa-solid fa-eye" style="color:var(--gray-400);"></i> ${j.views_count} Views</span>
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-outline btn-sm" onclick="JobsModule.openProposalsInboxModal(${j.id}, '${escapeHtml(j.title)}')"><i class="fa-solid fa-inbox"></i> View Proposals (${j.applications_count})</button>
                </div>
            </div>
        </div>
    `).join('');
}

async function initVendorJobsScreen() {
    const container = document.getElementById('screen-vendor-jobs');
    if (!container) return;

    container.innerHTML = `
        <div style="padding:20px; max-width:1100px; margin:0 auto;">
            <div style="margin-bottom:20px;">
                <h2 style="margin:0; color:var(--primary, #1B2B4B); font-size:1.4rem;"><i class="fa-solid fa-briefcase" style="color:var(--accent, #F2A735); margin-right:8px;"></i>Event Jobs Marketplace</h2>
                <span style="color:var(--gray-600); font-size:0.88rem;">Browse open event job postings and submit competitive quotes.</span>
            </div>

            <!-- Search & Filter Bar -->
            <div style="background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:14px; margin-bottom:20px; display:grid; grid-template-columns: 1fr auto auto; gap:10px; align-items:center;">
                <div style="position:relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--gray-400);"></i>
                    <input type="text" id="vj-search-input" placeholder="Search event jobs by title, skills, location..." onkeyup="if(event.key==='Enter') fetchMarketplaceJobs()" style="width:100%; padding:10px 10px 10px 36px; border:1px solid var(--gray-300); border-radius:8px;">
                </div>
                <select id="vj-category-select" onchange="fetchMarketplaceJobs()" style="padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                    <option value="">All Categories</option>
                    ${(JobsModule.currentCategories || []).map(c => `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)}</option>`).join('')}
                </select>
                <button class="btn btn-primary" onclick="fetchMarketplaceJobs()"><i class="fa-solid fa-search"></i> Search</button>
            </div>

            <div style="display:flex; gap:10px; border-bottom:2px solid var(--gray-200); margin-bottom:20px; overflow-x:auto;">
                <button class="tab-btn active" onclick="switchVendorJobsTab('available', this)" style="padding:10px 16px; font-weight:700; border:none; background:none; cursor:pointer; color:var(--primary); border-bottom:3px solid var(--accent);">Available Jobs</button>
                <button class="tab-btn" onclick="switchVendorJobsTab('applied', this)" style="padding:10px 16px; font-weight:600; border:none; background:none; cursor:pointer; color:var(--gray-500);">My Proposals</button>
                <button class="tab-btn" onclick="switchVendorJobsTab('shortlisted', this)" style="padding:10px 16px; font-weight:600; border:none; background:none; cursor:pointer; color:var(--gray-500);">Shortlisted</button>
                <button class="tab-btn" onclick="switchVendorJobsTab('hired', this)" style="padding:10px 16px; font-weight:600; border:none; background:none; cursor:pointer; color:var(--gray-500);">Hired Jobs</button>
            </div>

            <div id="vendor-jobs-list-container">${renderSkeletonCardsHTML(6)}</div>
        </div>
    `;

    fetchMarketplaceJobs();
}

async function fetchMarketplaceJobs() {
    const q = document.getElementById('vj-search-input') ? document.getElementById('vj-search-input').value.trim() : '';
    const cat = document.getElementById('vj-category-select') ? document.getElementById('vj-category-select').value : '';

    const listContainer = document.getElementById('vendor-jobs-list-container');
    if (listContainer) listContainer.innerHTML = renderSkeletonCardsHTML(4);

    try {
        const res = await API.get(`job_get_list&q=${encodeURIComponent(q)}&category=${encodeURIComponent(cat)}`);
        if (res && res.success) {
            window._vendorJobsData = { available: res.jobs || [] };
            renderVendorJobsTab('available');
        }
    } catch (e) {
        console.error('Error fetching jobs:', e);
    }
}

function switchVendorJobsTab(tabKey, btnElem) {
    document.querySelectorAll('#screen-vendor-jobs .tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.color = 'var(--gray-500)';
        b.style.borderBottom = 'none';
        b.style.fontWeight = '600';
    });
    if (btnElem) {
        btnElem.classList.add('active');
        btnElem.style.color = 'var(--primary)';
        btnElem.style.borderBottom = '3px solid var(--accent)';
        btnElem.style.fontWeight = '700';
    }

    if (tabKey === 'available') {
        renderVendorJobsTab('available');
    } else {
        loadVendorDashboardTab(tabKey);
    }
}

async function loadVendorDashboardTab(tabKey) {
    const listContainer = document.getElementById('vendor-jobs-list-container');
    if (listContainer) listContainer.innerHTML = renderSkeletonCardsHTML(4);

    try {
        const res = await API.get('job_get_vendor_dashboard');
        if (res && res.success) {
            window._vendorJobsData = res.applications;
            renderVendorJobsTab(tabKey);
        }
    } catch (e) {
        console.error('Error loading vendor proposals:', e);
    }
}

function renderVendorJobsTab(tabKey) {
    const listContainer = document.getElementById('vendor-jobs-list-container');
    if (!listContainer) return;

    if (tabKey === 'available') {
        const jobs = (window._vendorJobsData && window._vendorJobsData.available) ? window._vendorJobsData.available : [];
        if (jobs.length === 0) {
            listContainer.innerHTML = `<div style="text-align:center; padding:40px; background:#fff; border-radius:12px; color:var(--gray-500);"><p>No open jobs matching your filter criteria.</p></div>`;
            return;
        }

        listContainer.innerHTML = jobs.map(j => `
            <div class="job-card" style="background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:18px; margin-bottom:16px; display:flex; flex-direction:column; gap:10px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                    <div>
                        <span class="badge" style="background:rgba(27,43,75,0.08); color:var(--primary); padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700;">${escapeHtml(j.category)}</span>
                        ${j.is_urgent == 1 ? `<span class="badge" style="background:#FEE2E2; color:#DC2626; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; margin-left:6px;"><i class="fa-solid fa-bolt"></i> URGENT</span>` : ''}
                        <h3 style="margin:6px 0 2px; font-size:1.1rem; color:var(--primary);">${escapeHtml(j.title)}</h3>
                        <span style="font-size:0.8rem; color:var(--gray-500);"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(j.location || 'Accra')} • Posted by ${escapeHtml(j.user_name || 'Client')}</span>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1.2rem; font-weight:800; color:var(--primary);">GHS ${number_format(j.budget, 2)}</div>
                        <span style="font-size:0.75rem; color:var(--gray-500);">${j.negotiable == 1 ? 'Negotiable' : 'Fixed'}</span>
                    </div>
                </div>

                <p style="font-size:0.85rem; color:var(--gray-600); line-height:1.4; margin:0;">
                    ${escapeHtml(j.description.substring(0, 180))}${j.description.length > 180 ? '...' : ''}
                </p>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--gray-200); padding-top:12px; margin-top:4px;">
                    <div style="font-size:0.8rem; color:var(--gray-600);">
                        <span><i class="fa-solid fa-calendar" style="color:var(--accent);"></i> Event Date: ${escapeHtml(j.event_date || 'Flexible')}</span>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-outline btn-sm" onclick="JobsModule.toggleSaveJob(${j.id}, this)"><i class="${j.is_saved ? 'fa-solid' : 'fa-regular'} fa-bookmark"></i></button>
                        <button class="btn btn-primary btn-sm" onclick="JobsModule.openApplyModal(${j.id}, '${escapeHtml(j.title)}', ${j.budget})"><i class="fa-solid fa-paper-plane"></i> Apply Now</button>
                    </div>
                </div>
            </div>
        `).join('');
    } else {
        const apps = (window._vendorJobsData && window._vendorJobsData[tabKey]) ? window._vendorJobsData[tabKey] : [];
        if (apps.length === 0) {
            listContainer.innerHTML = `<div style="text-align:center; padding:40px; background:#fff; border-radius:12px; color:var(--gray-500);"><p>No proposals in '${tabKey}'.</p></div>`;
            return;
        }

        listContainer.innerHTML = apps.map(a => `
            <div style="background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:16px; margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <h4 style="margin:0; font-size:1rem; color:var(--primary);">${escapeHtml(a.job_title || 'Event Job')}</h4>
                    <span class="badge" style="font-size:0.75rem; padding:4px 8px; border-radius:6px; background:var(--gray-200); color:var(--gray-700); text-transform:capitalize;">${a.status}</span>
                </div>
                <div style="font-size:0.85rem; color:var(--gray-600); margin-bottom:8px;">
                    Your Quote: <strong>GHS ${number_format(a.price_quote, 2)}</strong> • Timeline: ${escapeHtml(a.delivery_timeline)}
                </div>
                <p style="font-size:0.82rem; color:var(--gray-700); background:var(--gray-100); padding:8px; border-radius:6px; margin:0;">
                    ${escapeHtml(a.cover_letter)}
                </p>
            </div>
        `).join('');
    }
}



function initAboutScreen() {
    const screen = document.getElementById('screen-about');
    if (!screen) return;

    screen.innerHTML = `
        <div style="max-width:800px; margin:0 auto; padding: 24px 16px 60px;">
            <div style="background:linear-gradient(135deg, #0F1923, #1B2B4B); border-radius:24px; padding:40px 24px; text-align:center; color:#fff; box-shadow:0 12px 30px rgba(0,0,0,0.15); margin-bottom:28px; position:relative; overflow:hidden;">
                <div style="position:absolute; right:-20px; top:-20px; opacity:0.1; font-size:12rem; color:#F2A735;"><i class="fa-solid fa-glass-water-droplet"></i></div>
                <img src="img/app_icon.png" alt="Ohati Logo" style="width:72px; height:72px; border-radius:18px; margin-bottom:16px; box-shadow:0 6px 16px rgba(0,0,0,0.3);">
                <h1 style="font-family:'Fraunces',serif; font-size:2.2rem; font-weight:800; margin-bottom:8px; color:#fff;">About Ohati</h1>
                <p style="font-size:1.05rem; color:#F2A735; font-weight:700; margin-bottom:12px;">Find. Compare. Book. Celebrate.</p>
                <p style="max-width:560px; margin:0 auto; font-size:0.88rem; color:#CBD5E1; line-height:1.6;">
                    Ohati is Africa's premier all-in-one event discovery, vendor comparison, direct messaging, and booking platform created to make event planning effortless, transparent, and joyful.
                </p>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:18px; margin-bottom:32px;">
                <div class="card p-20" style="border-radius:18px; border:1px solid var(--gray-100);">
                    <div style="width:44px; height:44px; border-radius:12px; background:rgba(242,167,53,0.15); color:#F2A735; display:flex; align-items:center; justify-content:center; font-size:1.3rem; margin-bottom:12px;">
                        <i class="fa-solid fa-shield-check"></i>
                    </div>
                    <h3 style="font-size:1.1rem; margin-bottom:6px; color:var(--primary);">Verified Vendors</h3>
                    <p style="font-size:0.83rem; color:var(--gray-600); line-height:1.5; margin:0;">
                        Every vendor on Ohati is thoroughly vetted with verified ID credentials and customer reviews, ensuring peace of mind for your special occasion.
                    </p>
                </div>

                <div class="card p-20" style="border-radius:18px; border:1px solid var(--gray-100);">
                    <div style="width:44px; height:44px; border-radius:12px; background:rgba(29,161,242,0.15); color:#1DA1F2; display:flex; align-items:center; justify-content:center; font-size:1.3rem; margin-bottom:12px;">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <h3 style="font-size:1.1rem; margin-bottom:6px; color:var(--primary);">Direct Messaging</h3>
                    <p style="font-size:0.83rem; color:var(--gray-600); line-height:1.5; margin:0;">
                        Chat directly with event planners, photographers, caterers, DJs, and decorators. Negotiate terms, share inspiration photos, and confirm details easily.
                    </p>
                </div>

                <div class="card p-20" style="border-radius:18px; border:1px solid var(--gray-100);">
                    <div style="width:44px; height:44px; border-radius:12px; background:rgba(16,185,129,0.15); color:#10B981; display:flex; align-items:center; justify-content:center; font-size:1.3rem; margin-bottom:12px;">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>
                    <h3 style="font-size:1.1rem; margin-bottom:6px; color:var(--primary);">Side-by-Side Comparison</h3>
                    <p style="font-size:0.83rem; color:var(--gray-600); line-height:1.5; margin:0;">
                        Compare vendor pricing, service packages, ratings, and portfolio showcases side-by-side to make the best decision for your event budget.
                    </p>
                </div>
            </div>

            <div class="card p-24 mb-24" style="border-radius:20px;">
                <h3 style="font-family:'Fraunces',serif; font-size:1.3rem; color:var(--primary); margin-bottom:12px;">Our Mission</h3>
                <p style="font-size:0.88rem; color:var(--gray-700); line-height:1.6; margin-bottom:16px;">
                    Whether you are planning a traditional wedding, corporate gala, milestone birthday, engagement party, or private dinner, Ohati bridges the gap between visionary event hosts and talented event professionals across Ghana and Africa.
                </p>
                <div style="background:var(--gray-50); border-left:4px solid var(--accent); padding:14px 18px; border-radius:0 12px 12px 0; font-size:0.83rem; color:var(--gray-800); font-style:italic;">
                    "We believe every celebration deserves world-class vendor craftsmanship, total financial transparency, and stress-free planning."
                </div>
            </div>

            <div class="card p-20" style="border-radius:18px; text-align:center;">
                <h4 style="margin-bottom:6px; color:var(--primary);">Need assistance or have feedback?</h4>
                <p style="font-size:0.82rem; color:var(--gray-600); margin-bottom:14px;">Our support team is available 24/7 to assist vendors and event hosts.</p>
                <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
                    <a href="mailto:support@ohati.com" class="btn btn-outline btn-sm"><i class="fa-solid fa-envelope"></i> Contact Support</a>
                    <button class="btn btn-primary btn-sm" onclick="navigateTo('help')"><i class="fa-solid fa-circle-question"></i> Help Center</button>
                </div>
                <div style="font-size:0.7rem; color:var(--gray-400); margin-top:16px;">Ohati Platform Version 1.1.4 — All rights reserved.</div>
                <div style="font-size:0.78rem; color:var(--gray-500); margin-top:12px; padding-top:12px; border-top:1px dashed var(--gray-200); display:flex; align-items:center; justify-content:center; gap:6px;">
                    <span>App developed by</span>
                    <a href="https://wa.me/2348136731796" target="_blank" rel="noopener" style="color:var(--accent); font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                        <i class="fa-brands fa-whatsapp" style="color:#25D366;"></i> C Eye Q Digital
                    </a>
                </div>
            </div>
        </div>
    `;
}
