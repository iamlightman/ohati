// components.js - Ohati App Frontend Controller & Components

const DEFAULT_USER_AVATAR = window.DEFAULT_USER_AVATAR || "profile-icon.jpg";

// 1. Application State
const state = {
    currentScreen: 'onboarding',
    previousScreens: [],
    currentUser: null,
    vendors: [],
    categories: [],
    favorites: [],
    bookings: [],
    trackerTasks: [],
    event: null,
    trackerStats: {
        total: 0,
        completed: 0,
        overdue: 0,
        in_progress: 0,
        upcoming: 0,
        percentage: 0,
        recommendation: '',
        budget: {
            estimated: 0.0,
            total_cost: 0.0,
            total_paid: 0.0,
            remaining: 0.0,
            outstanding: 0.0
        }
    },
    activePlanningSubtab: 'checklist', // 'checklist' or 'bookings'
    expandedTaskId: null,
    selectedVendorId: null,
    activeDetailTab: 'overview',
    activeChatVendorId: null,
    filters: {
        category: '',
        location: '',
        rating: '',
        search: ''
    },
    globalReviews: [
        { id: 1, name: "Abena Boateng", rating: 5, comment: "Ohati made finding my event decorator so simple. Royal Gold & Ivory theme was executed to perfection!", views: 476000, likes: 287000, liked: false, avatar: DEFAULT_USER_AVATAR },
        { id: 2, name: "Kwame Mensah", rating: 5, comment: "Exceptional photography choices. We booked Ohati's verified photographers and our wedding album is absolute gold!", views: 421000, likes: 254000, liked: false, avatar: DEFAULT_USER_AVATAR },
        { id: 3, name: "Adjoa Sarfo", rating: 4, comment: "Great customer support and easy booking. Highly recommend the budget planning tools for keeping us on track.", views: 389000, likes: 212000, liked: false, avatar: DEFAULT_USER_AVATAR },
        { id: 4, name: "Yaw Osei", rating: 5, comment: "I got the best catering deal through this platform. The verified badges really gave us peace of mind.", views: 453000, likes: 271000, liked: false, avatar: DEFAULT_USER_AVATAR },
        { id: 5, name: "Kofi Boadu", rating: 5, comment: "Smooth communication with DJs and MCs. Booking traditional marriage services was extremely seamless.", views: 312000, likes: 184000, liked: false, avatar: DEFAULT_USER_AVATAR }
    ]
};

// 2. Initialize App
document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

async function initApp() {
    try {
        setupEventListeners();
        setupTheme();
        initPushNotificationSwipes();
        
        // Load current user session from localStorage (instant, no network)
        state.currentUser = JSON.parse(localStorage.getItem('ohati_user_session') || 'null');
        
        // Keyboard viewport constraint optimizer using visualViewport API
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', () => {
                const viewport = document.getElementById('app-viewport');
                const chatMsgArea = document.getElementById('chat-msg-area');
                const appFrame = document.getElementById('ohati-app-frame');
                
                if (state.currentScreen === 'chat' && viewport) {
                    const vvHeight = window.visualViewport.height;
                    if (window.innerWidth < 480) {
                        if (appFrame) {
                            appFrame.style.height = `${vvHeight}px`;
                        }
                    }
                    if (chatMsgArea) {
                        scrollToBottom('chat-msg-area');
                    }
                }
            });
        }

        // Establish a minimum visible splash screen loading time (0.8 seconds) for a brief branded flash
        const minLoadingPromise = new Promise(resolve => setTimeout(resolve, 800));

        // Fetch ALL data in parallel (much faster than sequential awaits) along with the minimal splash timer
        await Promise.all([
            fetchCategories(),
            fetchVendors(),
            fetchFavorites(),
            fetchBookings(),
            fetchEventDetails(),
            fetchTrackerTasks(),
            fetchTrackerStats(),
            minLoadingPromise
        ]);
        
        // Now navigate — data is ready, screen will render with full content
        navigateTo('home');
        
        // Apply user session UI after screen has rendered
        updateUserSessionUI();

        // Fade out splash loading screen and animate topbar / content transition
        const appFrame = document.getElementById('ohati-app-frame');
        const loadingScreen = document.getElementById('screen-loading');
        if (loadingScreen) {
            loadingScreen.classList.add('fade-out');
            if (appFrame) {
                appFrame.classList.remove('is-loading');
            }
            // Hide loading element fully after opacity transition completes (500ms)
            setTimeout(() => {
                loadingScreen.style.display = 'none';
                // Show login modal automatically if there is no logged-in user session
                if (!state.currentUser) {
                    openLoginModal();
                }
            }, 500);
        }
    } catch (e) {
        console.error("Initialization error:", e);
        const errDiv = document.createElement('div');
        errDiv.style.position = 'fixed';
        errDiv.style.top = '0';
        errDiv.style.left = '0';
        errDiv.style.width = '100%';
        errDiv.style.background = '#e24949';
        errDiv.style.color = 'white';
        errDiv.style.padding = '15px';
        errDiv.style.zIndex = '999999';
        errDiv.style.fontSize = '12px';
        errDiv.style.fontFamily = 'monospace';
        errDiv.style.whiteSpace = 'pre-wrap';
        errDiv.innerText = 'Initialization Error: ' + e.message + '\n\nStack:\n' + e.stack;
        document.body.appendChild(errDiv);
    }
}

async function fetchEventDetails() {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=get_event');
        state.event = await res.json();
    } catch (e) {
        console.error("Error loading event details", e);
    }
}

// Setup Theme Toggle
function setupTheme() {
    const theme = localStorage.getItem('theme') || 'light';
    const icon = document.querySelector('#theme-toggle-btn i');
    
    if (theme === 'dark') {
        document.body.classList.add('dark-theme');
        if (icon) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
    }
    
    document.getElementById('theme-toggle-btn').addEventListener('click', () => {
        document.body.classList.toggle('dark-theme');
        const activeIcon = document.querySelector('#theme-toggle-btn i');
        if (document.body.classList.contains('dark-theme')) {
            localStorage.setItem('theme', 'dark');
            if (activeIcon) {
                activeIcon.classList.remove('fa-moon');
                activeIcon.classList.add('fa-sun');
            }
            showPushNotification("Dark Mode Enabled", "Enjoy a premium, battery-saving dark aesthetic.");
        } else {
            localStorage.setItem('theme', 'light');
            if (activeIcon) {
                activeIcon.classList.remove('fa-sun');
                activeIcon.classList.add('fa-moon');
            }
            showPushNotification("Light Mode Enabled", "Switched back to our classic warm ivory look.");
        }
    });
}

// Sliding Push Notification Helper
let notifTimeout = null;
function showPushNotification(title, desc) {
    const notif = document.getElementById('in-app-push-notif');
    const notifTitle = document.getElementById('notif-title');
    const notifDesc = document.getElementById('notif-desc');
    
    if (!notif || !notifTitle || !notifDesc) return;
    
    notifTitle.innerText = title;
    notifDesc.innerText = desc;
    
    notif.classList.add('active');
    
    if (notifTimeout) clearTimeout(notifTimeout);
    notifTimeout = setTimeout(() => {
        notif.classList.remove('active');
    }, 5000);
}

function dismissPushNotification() {
    const notif = document.getElementById('in-app-push-notif');
    if (notif) {
        notif.classList.remove('active');
    }
    if (notifTimeout) {
        clearTimeout(notifTimeout);
        notifTimeout = null;
    }
}

function initPushNotificationSwipes() {
    const notif = document.getElementById('in-app-push-notif');
    if (!notif) return;
    let startY = 0;
    
    notif.addEventListener('touchstart', (e) => {
        startY = e.touches[0].clientY;
    }, { passive: true });
    
    notif.addEventListener('touchmove', (e) => {
        let currentY = e.touches[0].clientY;
        let diffY = currentY - startY;
        if (diffY < -15) {
            dismissPushNotification();
        }
    }, { passive: true });
}

// 3. Setup Navigation & Actions Listeners
function setupEventListeners() {
    // Bottom Nav clicks
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const targetScreen = item.getAttribute('data-screen');
            
            if (targetScreen === 'menu') {
                toggleSidebar(true);
                return;
            }
            
            // Reset filters when clicking Directory directly
            if (targetScreen === 'search') {
                state.filters = { category: '', location: '', rating: '', search: '' };
                const searchInp = document.getElementById('search-input');
                if (searchInp) searchInp.value = '';
                fetchVendors();
            }
            
            navigateTo(targetScreen);
        });
    });

    // Header Notification Button click
    const notifBtn = document.getElementById('header-notif-btn');
    if (notifBtn) {
        notifBtn.addEventListener('click', () => {
            showPushNotification("System Notifications Feed", "Checking live notification queue...");
            openNotificationsModal();
        });
    }

    // Back Buttons (delegated)
    document.addEventListener('click', (e) => {
        if (e.target.closest('.back-btn')) {
            goBack();
        }
        
        // Favorite toggle on list cards (delegated)
        const favBtn = e.target.closest('.card-favorite-btn');
        if (favBtn) {
            e.stopPropagation();
            const vendorId = favBtn.getAttribute('data-id');
            toggleFavorite(vendorId, favBtn);
        }
    });
}

// 4. API Service Helper Functions
async function fetchCategories() {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=categories');
        state.categories = await res.json();
    } catch (e) {
        console.error("Error loading categories", e);
    }
}

async function fetchVendors() {
    try {
        let url = 'api.php?action=vendors';
        if (state.filters.category) url += `&category=${encodeURIComponent(state.filters.category)}`;
        if (state.filters.location) url += `&location=${encodeURIComponent(state.filters.location)}`;
        if (state.filters.rating) url += `&min_rating=${encodeURIComponent(state.filters.rating)}`;
        if (state.filters.search) url += `&search=${encodeURIComponent(state.filters.search)}`;
        
        const res = await fetch(url);
        state.vendors = await res.json();
    } catch (e) {
        console.error("Error loading vendors", e);
    }
}

async function fetchFavorites() {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=favorites');
        state.favorites = await res.json();
    } catch (e) {
        console.error("Error loading favorites", e);
    }
}

async function fetchBookings() {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=bookings');
        state.bookings = await res.json();
    } catch (e) {
        console.error("Error loading bookings", e);
    }
}

async function fetchTrackerTasks() {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=tracker_tasks');
        state.trackerTasks = await res.json();
    } catch (e) {
        console.error("Error loading tracker tasks", e);
    }
}

async function fetchTrackerStats() {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=tracker_stats');
        state.trackerStats = await res.json();
    } catch (e) {
        console.error("Error loading tracker stats", e);
    }
}

// 5. Navigation Router
function navigateTo(screenName, keepHistory = true) {
    const appFrame = document.getElementById('ohati-app-frame');
    if (appFrame && (screenName !== 'chat' || !state.activeChatVendorId)) {
        appFrame.style.height = "";
    }

    if (keepHistory && state.currentScreen !== screenName) {
        state.previousScreens.push(state.currentScreen);
    }
    
    state.currentScreen = screenName;
    
    // Toggle Active Class in Bottom Nav
    document.querySelectorAll('.nav-item').forEach(item => {
        if (item.getAttribute('data-screen') === screenName) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    // Hide all main screen panels
    document.getElementById('screen-onboarding').style.display = 'none';
    document.getElementById('screen-home').style.display = 'none';
    document.getElementById('screen-search').style.display = 'none';
    document.getElementById('screen-detail').style.display = 'none';
    document.getElementById('screen-chat').style.display = 'none';
    document.getElementById('screen-bookings').style.display = 'none';
    document.getElementById('screen-favorites').style.display = 'none';
    
    // Hide header and bottom nav on Onboarding & Chat
    const viewport = document.getElementById('app-viewport');
    if (screenName === 'onboarding') {
        if (viewport) viewport.classList.remove('app-viewport-chat-active');
        document.getElementById('app-header').style.display = 'none';
        document.getElementById('bottom-nav').style.display = 'none';
        document.getElementById('screen-onboarding').style.display = 'flex';
        renderOnboarding();
    } else if (screenName === 'chat') {
        if (state.activeChatVendorId) {
            if (viewport) viewport.classList.add('app-viewport-chat-active');
            document.getElementById('app-header').style.display = 'none';
            document.getElementById('bottom-nav').style.display = 'none';
            document.getElementById('screen-chat').style.display = 'block';
            renderChatScreen();
        } else {
            if (viewport) viewport.classList.remove('app-viewport-chat-active');
            document.getElementById('app-header').style.display = 'flex';
            document.getElementById('bottom-nav').style.display = 'flex';
            document.getElementById('screen-chat').style.display = 'block';
            renderChatScreen();
        }
    } else {
        if (viewport) viewport.classList.remove('app-viewport-chat-active');
        document.getElementById('app-header').style.display = 'flex';
        document.getElementById('bottom-nav').style.display = 'flex';
        
        const el = document.getElementById(`screen-${screenName}`);
        if (el) el.style.display = 'block';
        
        // Render screens respectively
        if (screenName === 'home') {
            Promise.all([fetchTrackerTasks(), fetchTrackerStats()]).then(() => {
                renderHomeScreen();
            });
        }
        else if (screenName === 'search') renderSearchScreen();
        else if (screenName === 'bookings') {
            Promise.all([fetchTrackerTasks(), fetchTrackerStats(), fetchBookings()]).then(() => {
                renderBookingsScreen();
            });
        }
        else if (screenName === 'favorites') renderFavoritesScreen();
    }
}

function goBack() {
    if (state.currentScreen === 'chat' && state.activeChatVendorId) {
        state.activeChatVendorId = null;
        navigateTo('chat', false);
        return;
    }
    if (state.previousScreens.length > 0) {
        const prev = state.previousScreens.pop();
        navigateTo(prev, false);
    } else {
        navigateTo('home');
    }
}

// 6. Component Rendering Functions

// Onboarding Component
function renderOnboarding() {
    const el = document.getElementById('screen-onboarding');
    el.innerHTML = `
        <div class="onboarding-logo anim-fade-in" style="margin-bottom: 25px;">
            <div class="onboarding-logo-icon">
                <i class="fa-solid fa-heart-crack"></i>
            </div>
            <h1 style="color: var(--warm-ivory); font-size: 2.5rem; letter-spacing: 3px;">OHATI</h1>
            <p style="color: var(--sage-green); font-size: 0.85rem; letter-spacing: 2px;">FIND. COMPARE. BOOK.</p>
        </div>
        
        <div class="onboarding-content anim-fade-in" style="animation-delay: 0.2s; margin-bottom: 25px;">
            <div class="onboarding-tagline">Your dream wedding, perfectly planned</div>
            <h2 class="onboarding-title">Plan the Perfect Ghanaian Wedding</h2>
            <p class="onboarding-desc">Connect with verified photographers, make-up artists, decorators, caterers, and 30+ other expert vendor services inside Ghana.</p>
        </div>
        
        <div class="anim-fade-in" style="animation-delay: 0.4s; display: flex; flex-direction: column; gap: 10px; width: 100%; padding: 0 10px; z-index: 10; margin-bottom: 30px;">
            <button class="btn btn-secondary" id="onboard-signup-btn" style="height: 50px; font-weight: 600; width: 100%;">
                Sign Up / Register <i class="fa-solid fa-user-plus" style="margin-left: 6px;"></i>
            </button>
            <button class="btn btn-primary" id="onboard-signin-btn" style="height: 50px; font-weight: 600; width: 100%;">
                Sign In / Login <i class="fa-solid fa-right-to-bracket" style="margin-left: 6px;"></i>
            </button>
            <button class="btn btn-outline" id="onboard-guest-btn" style="height: 50px; font-weight: 600; width: 100%; color: var(--warm-ivory); border-color: rgba(255,255,255,0.4);">
                Explore as Guest <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
            </button>
        </div>
    `;
    
    document.getElementById('onboard-signup-btn').addEventListener('click', () => {
        openSignUpModal();
    });
    
    document.getElementById('onboard-signin-btn').addEventListener('click', () => {
        openLoginModal();
    });
    
    document.getElementById('onboard-guest-btn').addEventListener('click', () => {
        localStorage.setItem('ohati_onboarded', 'true');
        navigateTo('home');
    });
}

// Home Component
function renderHomeScreen() {
    const el = document.getElementById('screen-home');
    const displayName = state.currentUser ? state.currentUser.name.split(' ')[0] : 'Guest';
    
    // Greeting time calculation
    const hours = new Date().getHours();
    let timeGreeting = "Good morning";
    if (hours >= 12 && hours < 17) {
        timeGreeting = "Good afternoon";
    } else if (hours >= 17) {
        timeGreeting = "Good evening";
    }

    // Countdown and date calculation
    let diffDays = 97;
    let eventDateText = '12th Dec 2027';
    if (state.event && state.event.event_date) {
        const eventDate = new Date(state.event.event_date);
        const today = new Date();
        today.setHours(0,0,0,0);
        const diffTime = eventDate - today;
        diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        if (diffDays < 0) diffDays = 0;
        
        eventDateText = formatFriendlyDate(state.event.event_date);
    }

    el.innerHTML = `
        <div class="home-section anim-fade-in">
            <div class="greeting-row">
                <div class="greeting-text">
                    <h2>${timeGreeting}, ${displayName} ✨</h2>
                </div>
            </div>
            
            <!-- Unified Dashboard metrics card -->
            <div class="unified-dashboard-card" style="cursor: pointer;" onclick="navigateTo('bookings')">
                <div class="countdown-section">
                    <div class="countdown-big-number">${diffDays}</div>
                    <div class="countdown-label">days to go</div>
                    <div class="countdown-date">${eventDateText}</div>
                </div>
                <div class="card-vertical-divider"></div>
                <div class="progress-section">
                    <div class="progress-ring-container">
                        <svg width="60" height="60">
                            <circle class="progress-ring-circle" stroke="var(--gray-light)" stroke-width="4" fill="transparent" r="26" cx="30" cy="30"/>
                            <circle class="progress-ring-circle" stroke="var(--forest-green)" stroke-width="4" stroke-dasharray="163.3" stroke-dashoffset="${163.3 - (163.3 * (state.trackerStats.percentage || 0) / 100)}" fill="transparent" r="26" cx="30" cy="30"/>
                        </svg>
                        <div class="progress-ring-text">${state.trackerStats.percentage || 0}%</div>
                    </div>
                    <div class="progress-label">Planning Progress</div>
                </div>
            </div>
            
            <!-- Search bar -->
            <div class="search-container home-search-container">
                <div class="search-input-wrapper">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search photographers, makeup artists..." class="search-input" id="home-search-input">
                    <div class="filter-btn" id="home-filter-btn">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                </div>
            </div>
            
            <!-- Concierge Banner -->
            <div class="concierge-banner" onclick="openConciergeModal()" style="margin: 0 0 22px 0; background: linear-gradient(135deg, #18221c 0%, #223329 100%); border: 1.5px solid var(--champagne-gold); border-radius: 16px; padding: 18px; color: #ffffff !important; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 8px 24px rgba(45,90,60,0.15); transition: all 0.2s;">
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                        <i class="fa-solid fa-crown" style="color: var(--champagne-gold); font-size: 0.95rem;"></i>
                        <span style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--champagne-gold) !important;">Concierge Service</span>
                    </div>
                    <h4 style="margin: 0 0 6px 0; font-size: 1rem; font-weight: 700; font-family: 'Poppins', sans-serif; color: #ffffff !important;">Want us to handle your entire event?</h4>
                    <p style="margin: 0; font-size: 0.72rem; opacity: 0.9; line-height: 1.4; color: #f4efe6 !important;">Let our premium planning team manage everything from vendor booking to design & execution.</p>
                </div>
                <div style="background: rgba(255,255,255,0.08); border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.15);">
                    <i class="fa-solid fa-chevron-right" style="color: var(--champagne-gold); font-size: 0.9rem;"></i>
                </div>
            </div>
            
            <!-- Category Grid (Find Vendors) -->
            <div class="section-header">
                <h3>Find Vendors</h3>
            </div>
            <div class="find-vendors-grid">
                <div class="vendor-grid-item" onclick="selectHomeCategory('Photography')">
                    <div class="grid-icon-box">
                        <i class="fa-solid fa-camera"></i>
                    </div>
                    <span class="grid-name">Photography</span>
                </div>
                <div class="vendor-grid-item" onclick="selectHomeCategory('Decorators')">
                    <div class="grid-icon-box">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <span class="grid-name">Decorators</span>
                </div>
                <div class="vendor-grid-item" onclick="selectHomeCategory('Event Venues')">
                    <div class="grid-icon-box">
                        <i class="fa-solid fa-hotel"></i>
                    </div>
                    <span class="grid-name">Venues</span>
                </div>
                <div class="vendor-grid-item" onclick="selectHomeCategory('Caterers')">
                    <div class="grid-icon-box">
                        <i class="fa-solid fa-utensils"></i>
                    </div>
                    <span class="grid-name">Caterers</span>
                </div>
                <div class="vendor-grid-item" onclick="selectHomeCategory('Makeup Artists')">
                    <div class="grid-icon-box">
                        <i class="fa-solid fa-brush"></i>
                    </div>
                    <span class="grid-name">Makeup Artists</span>
                </div>
                <div class="vendor-grid-item" onclick="navigateTo('search')">
                    <div class="grid-icon-box">
                        <i class="fa-solid fa-ellipsis"></i>
                    </div>
                    <span class="grid-name">View All</span>
                </div>
            </div>
            
            <!-- Recommended list in two columns -->
            <div class="section-header" style="margin-top: 25px;">
                <h3>Recommended for you</h3>
                <a href="#" class="see-all-link" id="home-see-all-recs">See all</a>
            </div>
            <div class="recommended-grid">
                ${state.vendors.slice(0, 4).map(v => {
                    return `
                        <div class="recommended-card anim-fade-in" onclick="viewVendorDetails(${v.id})">
                            <div class="recommended-img-wrapper">
                                <img src="${v.cover_photo}" alt="${v.name}" class="recommended-cover">
                            </div>
                            <div class="recommended-details">
                                <h4 class="recommended-title" style="display: flex; align-items: center; gap: 4px; width: 100%;">
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;">${v.name}</span>
                                    ${v.verified ? '<i class="fa-solid fa-circle-check verified-badge" style="font-size: 0.75rem; flex-shrink: 0;" title="Verified Vendor"></i>' : ''}
                                </h4>
                                <div class="recommended-meta-row">
                                    <span class="recommended-category">${v.category}</span>
                                    <div class="recommended-rating">
                                        <i class="fa-solid fa-star"></i>
                                        <span>${v.rating}</span>
                                    </div>
                                </div>
                                <div class="recommended-location">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <span>${v.location}</span>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
            
            <!-- Platform Reviews Slider -->
            <div class="section-header" style="margin-top: 25px; margin-bottom: 5px;">
                <h3>What Couples Say</h3>
                <a href="#" class="see-all-link" onclick="openAllReviewsModal(); return false;">View All</a>
            </div>
            <div class="reviews-scroller scrollable-x" style="margin: 0 -20px 15px -20px; padding: 5px 20px 15px 20px; display: flex; flex-wrap: nowrap !important; gap: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; scroll-snap-type: x mandatory; scrollbar-width: none; -ms-overflow-style: none;">
                ${state.globalReviews.map(r => `
                    <div class="review-slide-card">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <img src="${r.avatar}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--champagne-gold);">
                                <div style="flex: 1; min-width: 0;">
                                    <h5 style="margin: 0; font-family: 'Poppins', sans-serif; font-size: 0.7rem; font-weight: 600; color: var(--forest-green); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${r.name}</h5>
                                    <div style="color: var(--champagne-gold); font-size: 0.55rem; margin-top: 1px;">
                                        ${Array.from({length: r.rating}, () => '<i class="fa-solid fa-star"></i>').join('')}
                                        ${Array.from({length: 5 - r.rating}, () => '<i class="fa-regular fa-star"></i>').join('')}
                                    </div>
                                </div>
                            </div>
                            <!-- Views & Likes specific to this review -->
                            <div style="display: flex; gap: 8px; font-size: 0.6rem; color: var(--gray-text); margin-bottom: 6px; font-weight: 500;">
                                <span><i class="fa-solid fa-eye" style="color: var(--sage-green); margin-right: 3px;"></i>${formatLikes(r.views)}</span>
                                <span><i class="fa-solid fa-heart" style="color: #e24949; margin-right: 3px;"></i><span class="card-likes-count">${formatLikes(r.likes)}</span></span>
                            </div>
                            <p style="white-space: normal !important; font-size: 0.68rem; line-height: 1.4; color: var(--charcoal); margin: 0 0 8px 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; font-style: italic;">"${r.comment}"</p>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 8px;">
                            <span style="font-size: 0.6rem; color: var(--gray-text); font-weight: 500; display: inline-flex; align-items: center; gap: 3px;">
                                <i class="fa-solid fa-circle-check" style="color: #1DA1F2;"></i> Verified
                            </span>
                            <button class="like-review-btn ${r.liked ? 'liked' : ''}" onclick="toggleLikeReview(${r.id}, event)" style="background: rgba(0,0,0,0.02); border: none; display: flex; align-items: center; gap: 4px; color: ${r.liked ? '#e24949' : 'var(--sage-green)'}; font-size: 0.65rem; cursor: pointer; font-weight: 600; padding: 3px 8px; border-radius: 12px; transition: all 0.2s;">
                                <i class="${r.liked ? 'fa-solid' : 'fa-regular'} fa-heart"></i>
                                <span>Like</span>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
            
            <!-- Give Review Button -->
            <div style="display: flex; justify-content: center; margin-top: 5px; margin-bottom: 25px;">
                <button class="btn btn-primary" onclick="openPlatformReviewModal()" style="padding: 10px 24px; font-size: 0.78rem; border-radius: 20px; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; box-shadow: 0 4px 12px rgba(45,90,60,0.15);">
                    <i class="fa-solid fa-pen-to-square"></i> Share Your Review
                </button>
            </div>
            
            <!-- Trust indicators strip -->
            <div class="section-header" style="margin-top: 25px;">
                <h3>Our Promise</h3>
            </div>
            <div class="trust-strip scrollable-x">
                <div class="trust-item">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <span class="trust-item-title">Trusted & Verified</span>
                        <p style="font-size: 0.65rem; color: var(--gray-text);">Only genuine professionals</p>
                    </div>
                </div>
                <div class="trust-item">
                    <i class="fa-solid fa-star"></i>
                    <div>
                        <span class="trust-item-title">Quality & Excellence</span>
                        <p style="font-size: 0.65rem; color: var(--gray-text);">Top service guarantee</p>
                    </div>
                </div>
                <div class="trust-item">
                    <i class="fa-solid fa-handshake"></i>
                    <div>
                        <span class="trust-item-title">Transparent Pricing</span>
                        <p style="font-size: 0.65rem; color: var(--gray-text);">No hidden fees or charges</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Bind Home search
    const homeSearch = document.getElementById('home-search-input');
    if (homeSearch) {
        homeSearch.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                state.filters.search = homeSearch.value;
                fetchVendors().then(() => navigateTo('search'));
            }
        });
    }
    
    const filterBtn = document.getElementById('home-filter-btn');
    if (filterBtn) {
        filterBtn.addEventListener('click', () => {
            openFilterDrawer();
        });
    }
    
    const seeAllRecs = document.getElementById('home-see-all-recs');
    if (seeAllRecs) {
        seeAllRecs.addEventListener('click', (e) => {
            e.preventDefault();
            navigateTo('search');
        });
    }
}

function selectHomeCategory(categoryName) {
    state.filters.category = categoryName;
    localStorage.setItem('ohati_user_interest_category', categoryName);
    fetchVendors().then(() => navigateTo('search'));
}

// Directory Search Component
function renderSearchScreen() {
    const el = document.getElementById('screen-search');
    el.innerHTML = `
        <div class="home-section anim-fade-in" style="padding-top: 10px;">
            <div class="search-container" style="margin-bottom: 15px;">
                <div class="search-input-wrapper">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search vendors..." class="search-input" id="search-input" value="${state.filters.search}">
                    <div class="filter-btn" id="search-filter-btn">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                </div>
            </div>
            
            <!-- Applied Filters Row -->
            <div id="applied-filters-bar" style="margin-bottom: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
                ${renderActiveFilterChips()}
            </div>
            
            <div class="section-header">
                <h3>Marketplace Directory</h3>
                <span style="font-size: 0.8rem; color: var(--gray-text);">${state.vendors.length} vendors found</span>
            </div>
            
            <div id="search-vendors-list">
                ${state.vendors.length > 0 ? renderVendorList(state.vendors) : `
                    <div style="text-align: center; padding: 40px 20px; color: var(--sage-green);">
                        <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; margin-bottom: 12px;"></i>
                        <p>No vendors found matching your criteria. Try resetting the filters.</p>
                        <button class="btn btn-outline" onclick="resetAllFilters()" style="margin-top: 15px; padding: 8px 16px;">Reset Filters</button>
                    </div>
                `}
            </div>
        </div>
    `;
    
    // Bind search
    const searchInp = document.getElementById('search-input');
    searchInp.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') {
            state.filters.search = searchInp.value;
            fetchVendors().then(() => renderSearchScreen());
        }
    });
    
    document.getElementById('search-filter-btn').addEventListener('click', () => {
        openFilterDrawer();
    });
}

function renderActiveFilterChips() {
    let chips = [];
    if (state.filters.category) {
        chips.push(`<span class="filter-chip active" onclick="clearFilter('category')">${state.filters.category} <i class="fa-solid fa-xmark" style="margin-left:5px;"></i></span>`);
    }
    if (state.filters.location) {
        chips.push(`<span class="filter-chip active" onclick="clearFilter('location')">${state.filters.location} <i class="fa-solid fa-xmark" style="margin-left:5px;"></i></span>`);
    }
    if (state.filters.rating) {
        chips.push(`<span class="filter-chip active" onclick="clearFilter('rating')">${state.filters.rating}+ Stars <i class="fa-solid fa-xmark" style="margin-left:5px;"></i></span>`);
    }
    return chips.join('');
}

function clearFilter(key) {
    state.filters[key] = '';
    fetchVendors().then(() => renderSearchScreen());
}

function resetAllFilters() {
    state.filters = { category: '', location: '', rating: '', search: '' };
    fetchVendors().then(() => renderSearchScreen());
}

// Global Vendor List Renderer
function renderVendorList(vendorsList) {
    return vendorsList.map(v => {
        const pricingText = 'Ask for Price';
            
        return `
            <div class="vendor-card anim-fade-in" onclick="viewVendorDetails(${v.id})">
                <div class="card-img-wrapper">
                    <img src="${v.cover_photo}" alt="${v.name}" class="card-cover">
                    <span class="card-badge">${v.category}</span>
                    <div class="card-logo-wrapper">
                        <img src="${v.logo}" alt="Logo" class="card-logo">
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-meta-row">
                        <span class="card-category">${v.experience} Years Experience</span>
                        <div class="card-rating">
                            <i class="fa-solid fa-star"></i>
                            <span>${v.rating} (${v.reviews_count})</span>
                        </div>
                    </div>
                    <div class="card-title-row">
                        <h4>${v.name}</h4>
                        ${v.verified ? '<i class="fa-solid fa-circle-check verified-badge" title="Verified Vendor"></i>' : ''}
                    </div>
                    <div class="card-location">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>${v.location}</span>
                    </div>
                    <div class="card-footer">
                        <div>
                            <span class="card-price-label">Packages</span>
                            <div class="card-price-value">${pricingText}</div>
                        </div>
                        <span style="font-size: 0.75rem; font-weight:600; color: var(--forest-green);">
                            View Details <i class="fa-solid fa-chevron-right" style="margin-left: 4px;"></i>
                        </span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Vendor Details Component
async function viewVendorDetails(vendorId) {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=vendor_details&id=' + vendorId);
        const vendor = await res.json();
        
        state.selectedVendorId = vendorId;
        state.activeDetailTab = 'overview';
        
        // Track user interest for personalized recommendations
        if (vendor && vendor.category) {
            localStorage.setItem('ohati_user_interest_category', vendor.category);
        }
        
        renderVendorDetailScreen(vendor);
        navigateTo('detail');
    } catch (e) {
        console.error("Error loading vendor details", e);
    }
}

function renderVendorDetailScreen(v) {
    const el = document.getElementById('screen-detail');
    el.innerHTML = `
        <div class="detail-screen anim-fade-in">
            <div class="detail-hero">
                <img src="${v.cover_photo}" alt="${v.name}" class="detail-cover">
                <div class="detail-nav-overlay">
                    <button class="btn-icon back-btn"><i class="fa-solid fa-arrow-left"></i></button>
                </div>
                <div class="detail-logo-wrapper">
                    <img src="${v.logo}" alt="Logo" class="detail-logo">
                </div>
            </div>
            
            <div class="detail-body">
                <div class="detail-category-row">
                    <span style="color: var(--champagne-gold); font-weight: 700; text-transform: uppercase; font-size: 0.8rem;">${v.category}</span>
                    <span style="font-size: 0.75rem; color: var(--gray-text); font-weight: 500;">Resp. time: ${v.response_time}</span>
                </div>
                
                <div class="detail-title-row">
                    <h2 style="display: inline-flex; align-items: center; gap: 8px;">
                        ${v.name}
                        ${v.verified ? '<i class="fa-solid fa-circle-check verified-badge" style="font-size:1.2rem;"></i>' : ''}
                    </h2>
                </div>
                
                <div class="detail-stats-row">
                    <div class="detail-stat-box">
                        <i class="fa-solid fa-star"></i>
                        <span>${v.rating} (${v.reviews_count} Reviews)</span>
                    </div>
                    <div class="detail-stat-box">
                        <i class="fa-solid fa-briefcase"></i>
                        <span>${v.experience} Yrs Experience</span>
                    </div>
                    <div class="detail-stat-box">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>${v.location}</span>
                    </div>
                </div>
                
                <!-- Action Row -->
                <div class="action-buttons-row">
                    <button class="btn btn-primary" onclick="startVendorChat(${v.id})">
                        <i class="fa-solid fa-comment"></i> Chat Now
                    </button>
                    <a href="tel:${v.phone.replace(/\s+/g, '')}" class="btn btn-outline" style="text-align: center;">
                        <i class="fa-solid fa-phone"></i> Call Vendor
                    </a>
                </div>
                
                <!-- Tab Headers -->
                <div class="detail-tabs">
                    <div class="tab-btn active" data-tab="overview" onclick="switchDetailTab('overview')">Overview</div>
                    <div class="tab-btn" data-tab="packages" onclick="switchDetailTab('packages')">Packages</div>
                    <div class="tab-btn" data-tab="portfolio" onclick="switchDetailTab('portfolio')">Portfolio</div>
                    <div class="tab-btn" data-tab="reviews" onclick="switchDetailTab('reviews')">Reviews</div>
                </div>
                
                <!-- Tab Panes -->
                <div class="tab-pane active" id="pane-overview">
                    <h3 style="font-size: 1.1rem; margin-bottom: 10px;">About Business</h3>
                    <p style="font-size: 0.85rem; line-height: 1.6; color: var(--charcoal); margin-bottom: 20px;">${v.description}</p>
                    
                    <h3 style="font-size: 1.1rem; margin-bottom: 10px;">Business Contact</h3>
                    <div style="font-size: 0.85rem; display: flex; flex-direction: column; gap: 10px;">
                        <div><i class="fa-solid fa-phone" style="width: 20px; color: var(--sage-green);"></i> ${v.phone}</div>
                        <div><i class="fa-solid fa-envelope" style="width: 20px; color: var(--sage-green);"></i> ${v.email}</div>
                        <div><i class="fa-solid fa-calendar" style="width: 20px; color: var(--sage-green);"></i> Status: <span style="font-weight: 600; color: var(--forest-green);">${v.availability}</span></div>
                    </div>
                </div>
                
                <div class="tab-pane" id="pane-packages">
                    <h3 style="font-size: 1.1rem; margin-bottom: 15px;">Available Service Packages</h3>
                    ${v.packages_pricing.map(pkg => `
                        <div class="package-card">
                            <div class="package-header">
                                <span class="package-name">${pkg.name}</span>
                                <span class="package-price">Ask for Price</span>
                            </div>
                            <p class="package-desc">${pkg.details}</p>
                            <button class="btn btn-secondary" onclick="openBookingModal(${v.id}, '${pkg.name}', '')" style="width: 100%; padding: 8px; font-size: 0.8rem;">
                                Request Quotation & Book
                            </button>
                        </div>
                    `).join('')}
                </div>
                
                <div class="tab-pane" id="pane-portfolio">
                    <h3 style="font-size: 1.1rem; margin-bottom: 15px;">Real Event Portfolio</h3>
                    <div class="portfolio-grid">
                        ${v.gallery.map(imgUrl => `
                            <div class="portfolio-item" onclick="openLightbox('${imgUrl}')">
                                <img src="${imgUrl}" alt="Portfolio image">
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="tab-pane" id="pane-reviews">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="font-size: 1.1rem;">Customer Feedback</h3>
                        <button class="btn btn-outline" onclick="openReviewModal(${v.id})" style="padding: 6px 12px; font-size: 0.75rem;">Write Review</button>
                    </div>
                    <div id="reviews-list-container">
                        ${v.reviews.map(r => `
                            <div class="review-item">
                                <div class="review-meta">
                                    <span class="review-user">${r.user_name}</span>
                                    <span class="review-date">${r.date}</span>
                                </div>
                                <div class="review-stars">
                                    ${Array.from({length: r.rating}, () => '<i class="fa-solid fa-star"></i>').join('')}
                                    ${Array.from({length: 5 - r.rating}, () => '<i class="fa-regular fa-star"></i>').join('')}
                                </div>
                                <p class="review-comment">"${r.comment}"</p>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function switchDetailTab(tabId) {
    state.activeDetailTab = tabId;
    
    // Switch active class headers
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.getAttribute('data-tab') === tabId) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Switch active panes
    document.querySelectorAll('.tab-pane').forEach(pane => {
        if (pane.id === `pane-${tabId}`) {
            pane.classList.add('active');
        } else {
            pane.classList.remove('active');
        }
    });
}

// Booking component Modal flow
function openBookingModal(vendorId, packageName, packagePrice) {
    const el = document.getElementById('booking-modal');
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in">
            <h3 class="modal-title">Book Service</h3>
            <p style="font-size: 0.75rem; color: var(--champagne-gold); font-weight: 700; margin-bottom: 15px;">
                ${packageName}
            </p>
            <form id="booking-submit-form" onsubmit="submitBooking(event, ${vendorId})">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-input" id="book-name" required placeholder="Ama Mensah">
                </div>
                <div class="form-group">
                    <label class="form-label">Ghanaian Phone Number</label>
                    <input type="tel" class="form-input" id="book-phone" required placeholder="+233 24 000 0000" pattern="^\\+?233\\s?[0-9]{2}\\s?[0-9]{3}\\s?[0-9]{4}$|^0[0-9]{9}$" title="Enter a valid Ghanaian number like 0244000000 or +233 24 000 0000">
                </div>
                <div class="form-group">
                    <label class="form-label">Event Date</label>
                    <input type="date" class="form-input" id="book-date" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeBookingModal()" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:1.5;">Confirm Request</button>
                </div>
            </form>
        </div>
    `;
    el.classList.add('active');
}

function closeBookingModal() {
    document.getElementById('booking-modal').classList.remove('active');
}

async function submitBooking(event, vendorId) {
    event.preventDefault();
    const name = document.getElementById('book-name').value;
    const phone = document.getElementById('book-phone').value;
    const date = document.getElementById('book-date').value;
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=book', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                vendor_id: vendorId,
                user_name: name,
                user_phone: phone,
                event_date: date
            })
        });
        
        const data = await res.json();
        if (data.success) {
            closeBookingModal();
            alert("Booking request submitted successfully! Tracking status updated.");
            await fetchBookings();
            navigateTo('bookings');
        } else {
            alert("Failed to submit booking: " + data.error);
        }
    } catch (e) {
        console.error("Error booking vendor", e);
    }
}

// Review Submission Component
function openReviewModal(vendorId) {
    const el = document.getElementById('review-modal');
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in">
            <h3 class="modal-title">Write Feedback</h3>
            <form id="review-submit-form" onsubmit="submitReview(event, ${vendorId})">
                <div class="form-group">
                    <label class="form-label">Your Name</label>
                    <input type="text" class="form-input" id="rev-name" required placeholder="Kofi Boateng">
                </div>
                <div class="form-group">
                    <label class="form-label">Rating</label>
                    <select class="form-input" id="rev-rating" required>
                        <option value="5">⭐⭐⭐⭐⭐ 5 Stars</option>
                        <option value="4">⭐⭐⭐⭐ 4 Stars</option>
                        <option value="3">⭐⭐⭐ 3 Stars</option>
                        <option value="2">⭐⭐ 2 Stars</option>
                        <option value="1">⭐ 1 Star</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Comments</label>
                    <textarea class="form-input" id="rev-comment" required placeholder="Share your experience with this vendor..." style="height: 80px; padding: 10px; resize: none;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeReviewModal()" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:1.5;">Submit Review</button>
                </div>
            </form>
        </div>
    `;
    el.classList.add('active');
}

function closeReviewModal() {
    document.getElementById('review-modal').classList.remove('active');
}

async function submitReview(event, vendorId) {
    event.preventDefault();
    const name = document.getElementById('rev-name').value;
    const rating = document.getElementById('rev-rating').value;
    const comment = document.getElementById('rev-comment').value;
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=review', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                vendor_id: vendorId,
                user_name: name,
                rating: rating,
                comment: comment
            })
        });
        
        const data = await res.json();
        if (data.success) {
            closeReviewModal();
            alert("Thank you for your review! Updated vendor profile score.");
            
            // Reload vendor details
            await viewVendorDetails(vendorId);
            switchDetailTab('reviews');
        } else {
            alert("Failed to submit review: " + data.error);
        }
    } catch (e) {
        console.error("Error submitting review", e);
    }
}

// Bookings & Smart Planning Tracker Screen Component
// Global Event Wizard State
let wizardState = {
    step: 1,
    event_type: 'Wedding',
    event_date: '',
    start_time: '10:00',
    end_time: '18:00',
    location: '',
    region: 'Greater Accra',
    city: '',
    indoor_outdoor: 'Outdoor',
    estimated_budget: 25000,
    guest_count: 150,
    theme: '',
    notes: '',
    services_needed: []
};

// Bookings & Smart Planning Tracker Screen Component
function renderBookingsScreen() {
    const el = document.getElementById('screen-bookings');
    if (!el) return;
    
    // If no event planned yet, show "Plan My Event" starter view
    if (!state.event || !state.event.id) {
        renderPlannerStarter(el);
        return;
    }
    
    // Otherwise render the full Command Center
    renderPlannerCommandCenter(el);
}

function renderPlannerStarter(el) {
    el.innerHTML = `
        <div class="home-section anim-fade-in" style="padding-top: 10px; padding-bottom: 40px; text-align: center;">
            <div style="margin: 30px auto; width: 80px; height: 80px; border-radius: 50%; background: var(--white); display: flex; align-items: center; justify-content: center; border: 2px solid var(--champagne-gold); color: var(--forest-green); font-size: 2rem; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            
            <h2 style="font-size: 1.8rem; margin-bottom: 10px;">Intelligent Event Planner</h2>
            <p class="greeting-sub" style="margin-bottom: 25px; max-width: 320px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                Welcome to your personal event consultant. Plan your event type, set your budget, select services needed, and receive tailored timelines and smart vendor matching!
            </p>
            
            <div style="display: flex; flex-direction: column; gap: 12px; max-width: 280px; margin: 0 auto;">
                <button class="btn btn-primary" onclick="openPlanEventWizard()" style="height: 52px; font-weight: 700; font-size: 1rem; border-radius: 26px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 8px 20px rgba(45, 90, 60, 0.25);">
                    <i class="fa-solid fa-magic"></i> Plan My Event
                </button>
                <button class="btn btn-outline" onclick="navigateTo('search')" style="height: 52px; font-weight: 600; border-radius: 26px;">
                    Browse Vendor Directory
                </button>
            </div>
            
            <!-- Showcase feature highlights -->
            <div style="margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: left; padding: 0 15px;">
                <div style="background: var(--white); padding: 15px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);">
                    <div style="color: var(--champagne-gold); font-size: 1.2rem; margin-bottom: 6px;"><i class="fa-solid fa-timeline"></i></div>
                    <h4 style="font-size: 0.85rem; font-weight: 700; margin-bottom: 4px;">Smart Timeline</h4>
                    <p style="font-size: 0.7rem; color: var(--gray-text); line-height: 1.3;">Auto-adapts to your selected categories and event date offsets.</p>
                </div>
                <div style="background: var(--white); padding: 15px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);">
                    <div style="color: var(--champagne-gold); font-size: 1.2rem; margin-bottom: 6px;"><i class="fa-solid fa-coins"></i></div>
                    <h4 style="font-size: 0.85rem; font-weight: 700; margin-bottom: 4px;">Budget Tracker</h4>
                    <p style="font-size: 0.7rem; color: var(--gray-text); line-height: 1.3;">Tracks estimated vs actual costs, balance payments, and deposits.</p>
                </div>
            </div>
        </div>
    `;
}

function openPlanEventWizard() {
    wizardState.step = 1;
    // Set default date to 6 months in the future
    const d = new Date();
    d.setMonth(d.getMonth() + 6);
    wizardState.event_date = d.toISOString().split('T')[0];
    
    renderWizardStep();
}

function renderWizardStep() {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    el.classList.add('active');
    
    let content = '';
    
    if (wizardState.step === 1) {
        content = `
            <h3 class="modal-title" style="margin-bottom: 8px;"><span style="color: var(--champagne-gold);">Step 1:</span> Event Details</h3>
            <p style="font-size: 0.7rem; color: var(--gray-text); margin-bottom: 15px;">Configure your event details to generate an accurate planning schedule.</p>
            <form onsubmit="nextWizardStep(event)">
                <div style="max-height: 360px; overflow-y: auto; padding-right: 5px; display: flex; flex-direction: column; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Event Type</label>
                        <select class="form-input" id="wiz-event-type" onchange="wizardState.event_type = this.value">
                            <option value="Wedding" ${wizardState.event_type === 'Wedding' ? 'selected' : ''}>Wedding</option>
                            <option value="Engagement" ${wizardState.event_type === 'Engagement' ? 'selected' : ''}>Traditional Engagement</option>
                            <option value="Birthday" ${wizardState.event_type === 'Birthday' ? 'selected' : ''}>Birthday Party</option>
                            <option value="Funeral" ${wizardState.event_type === 'Funeral' ? 'selected' : ''}>Funeral / Burial Service</option>
                            <option value="Naming Ceremony" ${wizardState.event_type === 'Naming Ceremony' ? 'selected' : ''}>Naming Ceremony</option>
                            <option value="Corporate Event" ${wizardState.event_type === 'Corporate Event' ? 'selected' : ''}>Corporate Event</option>
                            <option value="Anniversary" ${wizardState.event_type === 'Anniversary' ? 'selected' : ''}>Anniversary</option>
                            <option value="Graduation" ${wizardState.event_type === 'Graduation' ? 'selected' : ''}>Graduation Party</option>
                            <option value="Others" ${wizardState.event_type === 'Others' ? 'selected' : ''}>Others / Custom Event</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Event Date</label>
                        <input type="date" class="form-input" id="wiz-event-date" value="${wizardState.event_date}" required onchange="wizardState.event_date = this.value">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-input" id="wiz-start-time" value="${wizardState.start_time}" onchange="wizardState.start_time = this.value">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-input" id="wiz-end-time" value="${wizardState.end_time}" onchange="wizardState.end_time = this.value">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Location / Venue Address</label>
                        <input type="text" class="form-input" id="wiz-location" value="${wizardState.location}" placeholder="e.g. Labadi Beach Hotel, Accra" onchange="wizardState.location = this.value">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label class="form-label">Region in Ghana</label>
                            <select class="form-input" id="wiz-region" onchange="wizardState.region = this.value">
                                <option value="Greater Accra" ${wizardState.region === 'Greater Accra' ? 'selected' : ''}>Greater Accra</option>
                                <option value="Ashanti" ${wizardState.region === 'Ashanti' ? 'selected' : ''}>Ashanti Region</option>
                                <option value="Western" ${wizardState.region === 'Western' ? 'selected' : ''}>Western Region</option>
                                <option value="Eastern" ${wizardState.region === 'Eastern' ? 'selected' : ''}>Eastern Region</option>
                                <option value="Central" ${wizardState.region === 'Central' ? 'selected' : ''}>Central Region</option>
                                <option value="Volta" ${wizardState.region === 'Volta' ? 'selected' : ''}>Volta Region</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" class="form-input" id="wiz-city" value="${wizardState.city}" placeholder="e.g. Accra" onchange="wizardState.city = this.value">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Setting</label>
                        <select class="form-input" id="wiz-setting" onchange="wizardState.indoor_outdoor = this.value">
                            <option value="Indoor" ${wizardState.indoor_outdoor === 'Indoor' ? 'selected' : ''}>Indoor setting</option>
                            <option value="Outdoor" ${wizardState.indoor_outdoor === 'Outdoor' ? 'selected' : ''}>Outdoor setting</option>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label class="form-label">Estimated Budget (GHS)</label>
                            <input type="number" class="form-input" id="wiz-budget" value="${wizardState.estimated_budget}" required onchange="wizardState.estimated_budget = parseFloat(this.value)">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Guests Count</label>
                            <input type="number" class="form-input" id="wiz-guests" value="${wizardState.guest_count}" required onchange="wizardState.guest_count = parseInt(this.value)">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Event Theme (Optional)</label>
                        <input type="text" class="form-input" id="wiz-theme" value="${wizardState.theme}" placeholder="e.g. Royal Ivory & Gold" onchange="wizardState.theme = this.value">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Special Notes</label>
                        <textarea class="form-input" id="wiz-notes" placeholder="Any specific requirements or instructions..." style="height: 60px; padding: 8px; resize: none;" onchange="wizardState.notes = this.value">${wizardState.notes}</textarea>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeBookingModal()" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:1.5;">Next: Services <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </form>
        `;
    }
    
    else if (wizardState.step === 2) {
        const suggestedCats = getSuggestedCategoriesForEvent(wizardState.event_type);
        if (wizardState.services_needed.length === 0) {
            wizardState.services_needed = [...suggestedCats];
        }
        
        content = `
            <h3 class="modal-title" style="margin-bottom: 8px;"><span style="color: var(--champagne-gold);">Step 2:</span> Services Needed</h3>
            <p style="font-size: 0.7rem; color: var(--gray-text); margin-bottom: 15px;">Based on your event type: <strong>${wizardState.event_type}</strong>, we suggest these categories. Hired elements dynamically unlock tracking milestones!</p>
            
            <form onsubmit="nextWizardStep(event)">
                <div style="max-height: 320px; overflow-y: auto; padding-right: 5px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding-bottom: 5px;">
                    ${state.categories.map(cat => {
                        const isChecked = wizardState.services_needed.includes(cat.name);
                        const isSuggested = suggestedCats.includes(cat.name);
                        return `
                            <label style="display: flex; align-items: center; gap: 8px; padding: 10px; border-radius: 8px; border: 1px solid ${isChecked ? 'var(--forest-green)' : 'var(--gray-light)'}; background: ${isChecked ? 'rgba(45, 90, 60, 0.03)' : 'var(--white)'}; cursor: pointer; font-size: 0.75rem;">
                                <input type="checkbox" value="${cat.name}" ${isChecked ? 'checked' : ''} onchange="toggleWizardService('${cat.name}', this.checked)" style="accent-color: var(--forest-green);">
                                <div style="display: flex; flex-direction: column;">
                                    <span>${cat.name}</span>
                                    ${isSuggested ? '<span style="font-size: 0.55rem; color: var(--champagne-gold); font-weight: 700;">Suggested</span>' : ''}
                                </div>
                            </label>
                        `;
                    }).join('')}
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="button" class="btn btn-outline" onclick="prevWizardStep()" style="flex:1;"><i class="fa-solid fa-arrow-left"></i> Back</button>
                    <button type="submit" class="btn btn-primary" style="flex:1.5;">Next: Recommendations <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </form>
        `;
    }
    
    else if (wizardState.step === 3) {
        const recommendedVendors = getSmartRecommendedVendors();
        
        content = `
            <h3 class="modal-title" style="margin-bottom: 8px;"><span style="color: var(--champagne-gold);">Step 3:</span> Smart Matches</h3>
            <p style="font-size: 0.7rem; color: var(--gray-text); margin-bottom: 15px;">Based on your location (<strong>${wizardState.region}</strong>) and budget, we matched these verified partners:</p>
            
            <div style="max-height: 280px; overflow-y: auto; padding-right: 5px; display: flex; flex-direction: column; gap: 10px; padding-bottom: 5px;">
                ${recommendedVendors.length > 0 ? recommendedVendors.map(match => `
                    <div style="padding: 12px; border-radius: 10px; border: 1.5px solid var(--champagne-gold); background: var(--white); display: flex; gap: 10px; align-items: center; position: relative;">
                        <img src="${match.vendor.logo}" style="width: 44px; height: 44px; border-radius: 8px; object-fit: cover;">
                        <div style="flex: 1;">
                            <h4 style="font-size: 0.85rem; margin-bottom: 2px;">${match.vendor.name}</h4>
                            <p style="font-size: 0.65rem; color: var(--champagne-gold); font-weight: 700; text-transform: uppercase;">${match.vendor.category}</p>
                            <p style="font-size: 0.7rem; color: var(--forest-green); font-weight: 600; margin-top: 4px; line-height: 1.2;">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> ${match.reason}
                            </p>
                        </div>
                        <button class="btn btn-primary" onclick="bookVendorDirect(${match.vendor.id})" style="padding: 6px 10px; font-size: 0.65rem; height: auto;">Book</button>
                    </div>
                `).join('') : `
                    <div style="text-align: center; padding: 20px; color: var(--gray-text); font-size: 0.75rem;">
                        No direct matches found. Try selecting more services or modifying details!
                    </div>
                `}
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="button" class="btn btn-outline" onclick="prevWizardStep()" style="flex:1;"><i class="fa-solid fa-arrow-left"></i> Back</button>
                <button type="button" class="btn btn-primary" onclick="completePlanEventWizard()" style="flex:1.5;"><i class="fa-solid fa-circle-check"></i> Complete Setup</button>
            </div>
        `;
    }
    
    el.innerHTML = `<div class="modal-sheet anim-fade-in" style="max-width: 450px; padding: 25px;">${content}</div>`;
}

function nextWizardStep(e) {
    if (e) e.preventDefault();
    wizardState.step++;
    renderWizardStep();
}

function prevWizardStep() {
    wizardState.step--;
    renderWizardStep();
}

function toggleWizardService(cat, checked) {
    if (checked) {
        if (!wizardState.services_needed.includes(cat)) {
            wizardState.services_needed.push(cat);
        }
    } else {
        wizardState.services_needed = wizardState.services_needed.filter(c => c !== cat);
    }
    renderWizardStep();
}

function getSuggestedCategoriesForEvent(evt) {
    switch (evt) {
        case 'Wedding':
        case 'Engagement':
            return ['Photography', 'Videography', 'Decorators', 'Caterers', 'Event Venues', 'DJs', 'Chilling Services', 'Cake Designers'];
        case 'Birthday':
            return ['Photography', 'Caterers', 'DJs', 'Chilling Services', 'Cake Designers'];
        case 'Corporate Event':
            return ['Videography', 'Caterers', 'Event Venues'];
        default:
            return ['Photography', 'Caterers', 'Chilling Services'];
    }
}

function getSmartRecommendedVendors() {
    const list = [];
    
    if (wizardState.services_needed.includes('Chilling Services')) {
        const csv = state.vendors.find(v => v.name.includes('Chill & Serve'));
        if (csv) {
            list.push({
                vendor: csv,
                reason: "Top premium cooling vendor; block ice & server dispatch."
            });
        }
    }
    
    if (wizardState.services_needed.includes('Photography')) {
        const photo = state.vendors.find(v => v.category === 'Photography');
        if (photo) {
            list.push({
                vendor: photo,
                reason: "High customer rating, expert for Accra events."
            });
        }
    }
    
    if (wizardState.services_needed.includes('Makeup Artists')) {
        const mua = state.vendors.find(v => v.category === 'Makeup Artists');
        if (mua) {
            list.push({
                vendor: mua,
                reason: "High endurance makeup specialist."
            });
        }
    }
    
    return list.slice(0, 3);
}

async function bookVendorDirect(vendorId) {
    const v = state.vendors.find(v2 => v2.id == vendorId);
    if (!v) return;
    
    wizardState.services_needed = wizardState.services_needed.filter(c => c !== v.category);
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=book', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                vendor_id: vendorId,
                user_name: 'Ama',
                user_phone: '+233 24 555 1122',
                event_date: wizardState.event_date,
                package_name: v.packages_pricing[0] ? v.packages_pricing[0].name : 'Standard Package',
                price: parseFloat(v.packages_pricing[0] ? v.packages_pricing[0].price.replace(/[^0-9.]/g, '') : 0.0)
            })
        });
        
        const data = await res.json();
        if (data.success) {
            showPushNotification("Matched Hired!", `Reserved ${v.name} for your event.`);
            await fetchBookings();
        }
    } catch(e) {
        console.error(e);
    }
    
    renderWizardStep();
}

async function completePlanEventWizard() {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=save_event', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(wizardState)
        });
        const data = await res.json();
        
        if (data.success) {
            closeBookingModal();
            showPushNotification("Event Setup Successful! 🎉", `Timeline checklist generated for your ${wizardState.event_type}.`);
            
            await fetchEventDetails();
            await fetchTrackerTasks();
            await fetchTrackerStats();
            await fetchBookings();
            
            navigateTo('bookings');
        }
    } catch (e) {
        console.error("Error setting up event", e);
    }
}

function renderPlannerCommandCenter(el) {
    const pct = state.trackerStats.percentage || 0;
    
    // Calculate countdown days
    const eventDate = new Date(state.event.event_date);
    const today = new Date();
    today.setHours(0,0,0,0);
    const diffTime = eventDate - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    const countdownText = diffDays > 0 ? `${diffDays} Days` : (diffDays === 0 ? 'Today! 🎉' : 'Completed');
    
    const formatGHS = (val) => 'GHS ' + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    
    const budget = state.trackerStats.budget || { estimated: 0, total_cost: 0, total_paid: 0, remaining: 0, outstanding: 0 };
    const budgetPercent = budget.estimated > 0 ? Math.min(Math.round((budget.total_paid / budget.estimated) * 100), 100) : 0;
    
    el.innerHTML = `
        <div class="home-section anim-fade-in" style="padding-top: 10px; padding-bottom: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div>
                    <h2 style="font-size: 1.4rem; line-height: 1.2; margin-bottom: 4px;">${state.event.event_type} Planner</h2>
                    <p class="greeting-sub">${state.event.theme || 'Standard Theme'} &bull; ${state.event.city || 'Ghana'}</p>
                </div>
                <button class="btn btn-outline" onclick="resetPlannedEvent()" style="padding: 6px 12px; font-size: 0.75rem; border-color: #e24949; color: #e24949; height: 32px;">Reset</button>
            </div>
            
            <!-- Dashboard Command Grid -->
            <div style="display: grid; grid-template-columns: 1.1fr 1fr; gap: 12px; margin-bottom: 15px;">
                
                <!-- Countdown -->
                <div class="countdown-card" style="padding: 12px; display: flex; flex-direction: column; justify-content: center; min-height: 90px; border-radius: 16px;">
                    <span style="font-size: 0.6rem; color: var(--sage-green); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Countdown</span>
                    <span style="font-size: 1.4rem; font-weight: 800; color: var(--champagne-gold); margin: 2px 0;">${countdownText}</span>
                    <span style="font-size: 0.65rem; color: var(--warm-ivory); opacity: 0.8;"><i class="fa-regular fa-calendar"></i> ${formatFriendlyDate(state.event.event_date)}</span>
                </div>
                
                <!-- Progress Circular ring -->
                <div class="progress-card" onclick="openProgressDetailsModal()" style="padding: 12px; cursor: pointer; display: flex; flex-direction: row; align-items: center; justify-content: space-between; min-height: 90px; border: 1.5px solid var(--champagne-gold); border-radius: 16px;">
                    <div style="flex: 1;">
                        <span style="font-size: 0.6rem; color: var(--gray-text); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Milestones</span>
                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--forest-green); margin-top: 4px;">${state.trackerStats.completed}/${state.trackerStats.total} Done</div>
                        <span style="font-size: 0.55rem; color: var(--champagne-gold); font-weight: 600; display: block; margin-top: 2px;">View Breakdown &rarr;</span>
                    </div>
                    <div class="progress-ring" style="width: 44px; height: 44px; margin-left: 4px;">
                        <svg width="44" height="44" viewBox="0 0 60 60">
                            <circle class="progress-ring-circle" stroke="var(--gray-light)" stroke-width="45" fill="transparent" r="25" cx="30" cy="30" style="stroke-width: 4px;"/>
                            <circle class="progress-ring-circle" stroke="var(--forest-green)" stroke-width="4" stroke-dasharray="157" stroke-dashoffset="${157 - (157 * pct / 100)}" fill="transparent" r="25" cx="30" cy="30" style="stroke-width: 4px;"/>
                        </svg>
                        <div class="progress-text" style="font-size: 0.7rem;">${pct}%</div>
                    </div>
                </div>
                
            </div>
            
            <!-- Budget Tracker Card -->
            <div style="background: var(--white); border-radius: 16px; border: 1.5px solid var(--champagne-gold); padding: 15px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-wallet" style="color: var(--champagne-gold);"></i> Budget Health</h4>
                    <span style="font-size: 0.7rem; color: var(--forest-green); font-weight: 700;">${budgetPercent}% Spent</span>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; text-align: center; margin-bottom: 12px; border-bottom: 1px solid rgba(0,0,0,0.03); padding-bottom: 10px;">
                    <div>
                        <div style="font-size: 0.55rem; color: var(--gray-text); text-transform: uppercase;">Estimated</div>
                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--forest-green);">${formatGHS(budget.estimated)}</div>
                    </div>
                    <div>
                        <div style="font-size: 0.55rem; color: var(--gray-text); text-transform: uppercase;">Hired spent</div>
                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--forest-green);">${formatGHS(budget.total_paid)}</div>
                    </div>
                    <div>
                        <div style="font-size: 0.55rem; color: var(--gray-text); text-transform: uppercase;">Remaining</div>
                        <div style="font-size: 0.8rem; font-weight: 700; color: ${budget.remaining < 0 ? '#e24949' : 'var(--forest-green)'};">${formatGHS(budget.remaining)}</div>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div style="width: 100%; height: 8px; border-radius: 4px; background: var(--gray-light); overflow: hidden; display: flex; margin-bottom: 8px;">
                    <div style="width: ${budgetPercent}%; height: 100%; background: var(--forest-green); border-radius: 4px 0 0 4px;"></div>
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 0.65rem; color: var(--gray-text);">
                    <span>Total Unpaid: <strong>${formatGHS(budget.outstanding)}</strong></span>
                    <span>Hired Estimated: <strong>${formatGHS(budget.total_cost)}</strong></span>
                </div>
            </div>
            
            <!-- Recommendations Banner -->
            <div class="tracker-recommendation-box" style="margin-bottom: 15px; padding: 12px;">
                <div class="tracker-recommendation-icon" style="font-size: 1.2rem;">💡</div>
                <div class="tracker-recommendation-text">
                    <h5 style="font-size: 0.85rem; margin-bottom: 2px;">Recommended Next Step</h5>
                    <p style="font-size: 0.75rem; line-height: 1.3;">${state.trackerStats.recommendation || 'Discover top photography and videography vendors based in Ghana.'}</p>
                </div>
            </div>
            
            <!-- Navigation Sub-tabs -->
            <div class="planning-subtabs" style="margin-bottom: 15px;">
                <div class="planning-subtab-btn ${state.activePlanningSubtab === 'checklist' ? 'active' : ''}" onclick="switchPlanningSubtab('checklist')">
                    <i class="fa-solid fa-list-check" style="margin-right: 4px;"></i> Timeline & Tasks
                </div>
                <div class="planning-subtab-btn ${state.activePlanningSubtab === 'bookings' ? 'active' : ''}" onclick="switchPlanningSubtab('bookings')">
                    <i class="fa-solid fa-receipt" style="margin-right: 4px;"></i> Reservations (${state.bookings.length})
                </div>
            </div>
            
            <!-- Sub-tab Content Area -->
            <div id="planning-subtab-content">
                ${state.activePlanningSubtab === 'checklist' ? renderTimelineChecklistTab() : renderReservationsTab()}
            </div>
        </div>
    `;
}

function switchPlanningSubtab(tab) {
    state.activePlanningSubtab = tab;
    renderBookingsScreen();
}

function formatFriendlyDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

function openProgressDetailsModal() {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    const pct = state.trackerStats.percentage || 0;
    
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 400px; padding: 25px;">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-chart-line" style="color: var(--forest-green);"></i> Progress Breakdown
            </h3>
            
            <div style="text-align: center; margin: 20px 0;">
                <div style="font-size: 2.2rem; font-weight: 800; color: var(--forest-green);">${pct}%</div>
                <div style="font-size: 0.75rem; color: var(--gray-text); margin-top: 2px;">Overall milestone completion</div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; padding: 8px; border-bottom: 1px solid rgba(0,0,0,0.04);">
                    <span>Total Milestones</span>
                    <strong style="color: var(--forest-green);">${state.trackerStats.total}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; padding: 8px; border-bottom: 1px solid rgba(0,0,0,0.04);">
                    <span>Completed Milestones</span>
                    <strong style="color: var(--forest-green);">${state.trackerStats.completed}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; padding: 8px; border-bottom: 1px solid rgba(0,0,0,0.04);">
                    <span>In-Progress Today</span>
                    <strong style="color: #4a90e2;">${state.trackerStats.in_progress}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; padding: 8px; border-bottom: 1px solid rgba(0,0,0,0.04);">
                    <span>Overdue Milestones</span>
                    <strong style="color: #e24949;">${state.trackerStats.overdue}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; padding: 8px; border-bottom: 1px solid rgba(0,0,0,0.04);">
                    <span>Upcoming Checklist Tasks</span>
                    <strong style="color: var(--champagne-gold);">${state.trackerStats.upcoming}</strong>
                </div>
            </div>
            
            <button class="btn btn-primary" onclick="closeBookingModal()" style="width: 100%; height: 44px;">Close Breakdown</button>
        </div>
    `;
    el.classList.add('active');
}

if (!state.trackerActiveFilter) state.trackerActiveFilter = 'all';

function renderTimelineChecklistTab() {
    const today = new Date().toISOString().split('T')[0];
    const sevenDaysOut = new Date();
    sevenDaysOut.setDate(sevenDaysOut.getDate() + 7);
    const sevenDaysOutStr = sevenDaysOut.toISOString().split('T')[0];
    
    const thirtyDaysOut = new Date();
    thirtyDaysOut.setDate(thirtyDaysOut.getDate() + 30);
    const thirtyDaysOutStr = thirtyDaysOut.toISOString().split('T')[0];
    
    // Filter tasks
    let filteredTasks = state.trackerTasks;
    if (state.trackerActiveFilter === 'pending') {
        filteredTasks = state.trackerTasks.filter(t => t.completed == 0);
    } else if (state.trackerActiveFilter === 'completed') {
        filteredTasks = state.trackerTasks.filter(t => t.completed == 1);
    } else if (state.trackerActiveFilter === 'this_week') {
        filteredTasks = state.trackerTasks.filter(t => t.completed == 0 && t.estimated_date >= today && t.estimated_date <= sevenDaysOutStr);
    } else if (state.trackerActiveFilter === 'this_month') {
        filteredTasks = state.trackerTasks.filter(t => t.completed == 0 && t.estimated_date >= today && t.estimated_date <= thirtyDaysOutStr);
    }
    
    // Group tasks by timeline category offsets relative to event date
    const groups = {
        '6 Months Before': [],
        '4 Months Before': [],
        '2 Months Before': [],
        '2 Weeks Before': [],
        '1 Day Before': [],
        'Custom Tasks': []
    };
    
    const eventDate = new Date(state.event.event_date);
    
    filteredTasks.forEach(task => {
        if (task.is_custom == 1) {
            groups['Custom Tasks'].push(task);
            return;
        }
        
        const taskDate = new Date(task.estimated_date);
        const diffDays = Math.round((eventDate - taskDate) / (1000 * 60 * 60 * 24));
        
        if (diffDays >= 150) {
            groups['6 Months Before'].push(task);
        } else if (diffDays >= 90) {
            groups['4 Months Before'].push(task);
        } else if (diffDays >= 45) {
            groups['2 Months Before'].push(task);
        } else if (diffDays >= 7) {
            groups['2 Weeks Before'].push(task);
        } else {
            groups['1 Day Before'].push(task);
        }
    });
    
    return `
        <!-- Filter chips row -->
        <div style="display: flex; gap: 6px; overflow-x: auto; padding-bottom: 8px; margin-bottom: 12px; scrollbar-width: none; -webkit-overflow-scrolling: touch;">
            <span class="chip ${state.trackerActiveFilter === 'all' ? 'active' : ''}" onclick="setTrackerFilter('all')" style="cursor:pointer; font-size:0.7rem; padding: 6px 12px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); white-space: nowrap; background: ${state.trackerActiveFilter === 'all' ? 'var(--forest-green)' : 'var(--white)'}; color: ${state.trackerActiveFilter === 'all' ? 'var(--white)' : 'var(--charcoal)'};">All</span>
            <span class="chip ${state.trackerActiveFilter === 'pending' ? 'active' : ''}" onclick="setTrackerFilter('pending')" style="cursor:pointer; font-size:0.7rem; padding: 6px 12px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); white-space: nowrap; background: ${state.trackerActiveFilter === 'pending' ? 'var(--forest-green)' : 'var(--white)'}; color: ${state.trackerActiveFilter === 'pending' ? 'var(--white)' : 'var(--charcoal)'};">Pending</span>
            <span class="chip ${state.trackerActiveFilter === 'this_week' ? 'active' : ''}" onclick="setTrackerFilter('this_week')" style="cursor:pointer; font-size:0.7rem; padding: 6px 12px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); white-space: nowrap; background: ${state.trackerActiveFilter === 'this_week' ? 'var(--forest-green)' : 'var(--white)'}; color: ${state.trackerActiveFilter === 'this_week' ? 'var(--white)' : 'var(--charcoal)'};">This Week</span>
            <span class="chip ${state.trackerActiveFilter === 'this_month' ? 'active' : ''}" onclick="setTrackerFilter('this_month')" style="cursor:pointer; font-size:0.7rem; padding: 6px 12px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); white-space: nowrap; background: ${state.trackerActiveFilter === 'this_month' ? 'var(--forest-green)' : 'var(--white)'}; color: ${state.trackerActiveFilter === 'this_month' ? 'var(--white)' : 'var(--charcoal)'};">Next Month</span>
            <span class="chip ${state.trackerActiveFilter === 'completed' ? 'active' : ''}" onclick="setTrackerFilter('completed')" style="cursor:pointer; font-size:0.7rem; padding: 6px 12px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); white-space: nowrap; background: ${state.trackerActiveFilter === 'completed' ? 'var(--forest-green)' : 'var(--white)'}; color: ${state.trackerActiveFilter === 'completed' ? 'var(--white)' : 'var(--charcoal)'};">Completed</span>
        </div>
        
        <!-- Action Row -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <button class="btn btn-primary" onclick="openAddTaskModal()" style="padding: 8px 14px; font-size: 0.8rem;">
                <i class="fa-solid fa-plus"></i> Add Custom Task
            </button>
            <button class="btn btn-outline" onclick="triggerCalendarSync()" style="padding: 8px 14px; font-size: 0.8rem; border-color: var(--sage-green); color: var(--forest-green); height: auto;">
                <i class="fa-solid fa-calendar-plus"></i> Sync Calendar
            </button>
        </div>
        
        <!-- Timeline groups -->
        <div class="tracker-task-list">
            ${Object.keys(groups).map(gName => {
                const groupTasks = groups[gName];
                if (groupTasks.length === 0) return '';
                
                return `
                    <div style="margin-top: 15px; margin-bottom: 5px;">
                        <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--forest-green); letter-spacing: 0.5px; border-bottom: 1.5px solid var(--champagne-gold); padding-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">
                            <span>${gName}</span>
                            <span style="font-size: 0.7rem; font-weight: normal; color: var(--gray-text);">${groupTasks.length} tasks</span>
                        </h4>
                    </div>
                    ${groupTasks.map(task => {
                        const isCompleted = task.completed == 1;
                        const isOverdue = !isCompleted && task.estimated_date < today;
                        const isToday = !isCompleted && task.estimated_date === today;
                        
                        let cardClass = 'task-upcoming';
                        if (isCompleted) cardClass = 'task-completed';
                        else if (isOverdue) cardClass = 'task-overdue';
                        else if (isToday) cardClass = 'task-inprogress';
                        
                        const isExpanded = state.expandedTaskId == task.id;
                        
                        return `
                            <div class="tracker-task-card ${cardClass}" id="task-card-${task.id}">
                                <div class="task-main-row">
                                    <div class="task-checkbox-wrapper" onclick="toggleTaskCompletion(${task.id}, ${isCompleted ? 0 : 1})">
                                        <div class="task-checkbox ${isCompleted ? 'checked' : ''}">
                                            ${isCompleted ? '<i class="fa-solid fa-check" style="font-size: 0.65rem;"></i>' : ''}
                                        </div>
                                        <span class="task-name-text">${task.task_name}</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="task-badge task-badge-${task.priority.toLowerCase()}">${task.priority}</span>
                                        <i class="fa-solid fa-chevron-${isExpanded ? 'up' : 'down'}" style="cursor: pointer; color: var(--sage-green); font-size: 0.8rem;" onclick="toggleTaskExpand(${task.id})"></i>
                                    </div>
                                </div>
                                
                                <div class="task-meta-row">
                                    <span><i class="fa-solid fa-folder" style="margin-right: 4px;"></i> ${task.category}</span>
                                    <span><i class="fa-solid fa-calendar-day" style="margin-right: 4px;"></i> Due: ${task.estimated_date}</span>
                                    ${task.cost > 0 ? `<span><i class="fa-solid fa-tag" style="margin-right: 4px;"></i> GHS ${parseFloat(task.cost).toLocaleString()}</span>` : ''}
                                </div>
                                
                                <!-- Expandable Details & Budget -->
                                ${isExpanded ? `
                                    <div class="task-details-pane anim-fade-in">
                                        <div class="form-group" style="margin-bottom: 8px;">
                                            <label class="form-label" style="font-size: 0.65rem;">Checklist Notes / Details</label>
                                            <textarea class="task-notes-area" id="notes-${task.id}" placeholder="Add planning notes, contact info, details...">${task.notes || ''}</textarea>
                                        </div>
                                        
                                        <!-- Cost Tracking Fields -->
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                                            <div>
                                                <label class="form-label" style="font-size: 0.65rem;">Estimated Cost (GHS)</label>
                                                <input type="number" class="form-input" id="cost-${task.id}" value="${task.cost || 0}" style="height: 32px; font-size: 0.75rem; padding: 0 6px;">
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size: 0.65rem;">Amount Paid (GHS)</label>
                                                <input type="number" class="form-input" id="paid-${task.id}" value="${task.paid_amount || 0}" style="height: 32px; font-size: 0.75rem; padding: 0 6px;">
                                            </div>
                                        </div>
                                        
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                                            <div>
                                                <label class="form-label" style="font-size: 0.65rem;">Priority</label>
                                                <select class="form-input" id="priority-${task.id}" style="height: 32px; font-size: 0.75rem; padding: 0 6px;">
                                                    <option value="High" ${task.priority === 'High' ? 'selected' : ''}>High</option>
                                                    <option value="Medium" ${task.priority === 'Medium' ? 'selected' : ''}>Medium</option>
                                                    <option value="Low" ${task.priority === 'Low' ? 'selected' : ''}>Low</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size: 0.65rem;">Target Date</label>
                                                <input type="date" class="form-input" id="date-${task.id}" value="${task.estimated_date}" style="height: 32px; font-size: 0.75rem; padding: 0 6px;">
                                            </div>
                                        </div>
                                        
                                        <div class="task-actions-row">
                                            ${task.is_custom == 1 ? `
                                                <button class="task-btn-small btn-danger" onclick="deleteTask(${task.id})">
                                                    <i class="fa-solid fa-trash"></i> Delete
                                                </button>
                                            ` : ''}
                                            <button class="task-btn-small" onclick="saveTaskDetailsAndBudget(${task.id})">
                                                <i class="fa-solid fa-floppy-disk"></i> Save Details
                                            </button>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    }).join('')}
                `;
            }).join('')}
        </div>
    `;
}

function setTrackerFilter(filter) {
    state.trackerActiveFilter = filter;
    renderBookingsScreen();
}

async function saveTaskDetailsAndBudget(taskId) {
    const notesVal = document.getElementById(`notes-${taskId}`).value;
    const priorityVal = document.getElementById(`priority-${taskId}`).value;
    const dateVal = document.getElementById(`date-${taskId}`).value;
    const costVal = parseFloat(document.getElementById(`cost-${taskId}`).value) || 0.0;
    const paidVal = parseFloat(document.getElementById(`paid-${taskId}`).value) || 0.0;
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=update_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: taskId,
                notes: notesVal,
                priority: priorityVal,
                estimated_date: dateVal,
                cost: costVal,
                paid_amount: paidVal
            })
        });
        const data = await res.json();
        
        if (data.success) {
            showPushNotification("Details & Budget Saved", "Task specifications updated successfully.");
            state.expandedTaskId = null;
            
            await fetchTrackerTasks();
            await fetchTrackerStats();
            renderBookingsScreen();
        }
    } catch (e) {
        console.error("Error saving task details", e);
    }
}

async function resetPlannedEvent() {
    if (!confirm("Are you sure you want to reset this planned event? This will clear all milestones, budget details, and event settings!")) return;
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=reset_event');
        const data = await res.json();
        if (data.success) {
            showPushNotification("Event Cleared", "Planner has been reset successfully.");
            state.event = null;
            state.trackerTasks = [];
            state.trackerStats.percentage = 0;
            
            await fetchTrackerStats();
            renderBookingsScreen();
        }
    } catch (e) {
        console.error(e);
    }
}

function renderReservationsTab() {
    const formatGHS = (val) => 'GHS ' + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    
    return `
        <div id="bookings-list-container">
            ${state.bookings.length > 0 ? state.bookings.map(b => `
                <div class="booking-tracker-card anim-fade-in" style="cursor: pointer;" onclick="openBookingNegotiationModal(${b.id})">
                    <img src="${b.vendor_logo}" alt="${b.vendor_name}" class="booking-vendor-logo">
                    <div class="booking-details" style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                            <h4 style="margin: 0; font-size: 0.9rem; line-height: 1.2;">${b.vendor_name}</h4>
                            <span class="status-badge status-${b.status.toLowerCase().replace(/ /g, '')}" style="font-size: 0.6rem; padding: 2px 6px;">${b.status}</span>
                        </div>
                        <p style="font-size: 0.7rem; color: var(--champagne-gold); font-weight: 600; text-transform: uppercase; margin: 2px 0;">${b.vendor_category}</p>
                        <div style="font-size: 0.75rem; color: var(--gray-text); display: flex; gap: 10px; margin-top: 4px;">
                            <span><i class="fa-solid fa-box" style="margin-right: 2px;"></i> ${b.package_name || 'Standard'}</span>
                            <span><i class="fa-solid fa-coins" style="margin-right: 2px;"></i> ${b.price > 0 ? formatGHS(b.price) : 'Quote Pending'}</span>
                        </div>
                        <div class="booking-date" style="margin-top: 4px; font-size: 0.7rem;">
                            <i class="fa-solid fa-calendar-days" style="margin-right: 4px;"></i> Event Date: ${b.event_date}
                        </div>
                    </div>
                </div>
            `).join('') : `
                <div style="text-align: center; padding: 40px 20px; color: var(--sage-green);">
                    <i class="fa-solid fa-calendar-xmark" style="font-size: 2.5rem; margin-bottom: 12px;"></i>
                    <p>You have no active bookings or quotation requests. Browse categories to begin!</p>
                    <button class="btn btn-primary" onclick="navigateTo('home')" style="margin-top: 15px;">Discover Vendors</button>
                </div>
            `}
        </div>
    `;
}

function openBookingNegotiationModal(bookingId) {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    const b = state.bookings.find(b2 => b2.id == bookingId);
    if (!b) return;
    
    const formatGHS = (val) => 'GHS ' + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    
    let timelineEntries = [];
    try {
        timelineEntries = typeof b.timeline === 'string' ? JSON.parse(b.timeline || '[]') : (b.timeline || []);
    } catch(e) {
        timelineEntries = [];
    }
    
    let negHistory = [];
    try {
        negHistory = typeof b.negotiation_history === 'string' ? JSON.parse(b.negotiation_history || '[]') : (b.negotiation_history || []);
    } catch(e) {
        negHistory = [];
    }
    
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 480px; padding: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 10px; margin-bottom: 15px;">
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem; color: var(--forest-green);">${b.vendor_name}</h3>
                    <span style="font-size: 0.7rem; color: var(--champagne-gold); font-weight: 700; text-transform: uppercase;">${b.vendor_category}</span>
                </div>
                <span class="status-badge status-${b.status.toLowerCase().replace(/ /g, '')}" style="font-size: 0.7rem;">${b.status}</span>
            </div>
            
            <div style="max-height: 380px; overflow-y: auto; padding-right: 5px; display: flex; flex-direction: column; gap: 15px;">
                
                <!-- Package Details -->
                <div style="background: rgba(0,0,0,0.02); padding: 12px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.04);">
                    <h4 style="font-size: 0.8rem; font-weight: 800; margin-bottom: 6px; color: var(--forest-green);">Selected Package & Quote</h4>
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 4px;">
                        <span style="color: var(--gray-text);">Package Name:</span>
                        <strong>${b.package_name || 'Custom'}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 4px;">
                        <span style="color: var(--gray-text);">Original Price:</span>
                        <strong>${formatGHS(b.price)}</strong>
                    </div>
                    ${b.negotiated_price ? `
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 4px;">
                            <span style="color: var(--gray-text);">Negotiated Price:</span>
                            <strong style="color: var(--champagne-gold);">${formatGHS(b.negotiated_price)}</strong>
                        </div>
                    ` : ''}
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem;">
                        <span style="color: var(--gray-text);">Payment Status:</span>
                        <span class="status-badge status-${b.payment_status ? b.payment_status.toLowerCase() : 'unpaid'}" style="font-size: 0.65rem; padding: 1px 4px;">${b.payment_status || 'Unpaid'}</span>
                    </div>
                </div>
                
                <!-- Negotiation Actions -->
                ${renderNegotiationSection(b, negHistory)}
                
                <!-- Payment Stage Actions -->
                ${renderPaymentSection(b)}
                
                <!-- Booking Timeline -->
                <div style="margin-top: 10px;">
                    <h4 style="font-size: 0.8rem; font-weight: 800; margin-bottom: 10px; color: var(--forest-green);"><i class="fa-solid fa-route"></i> Progress Timeline</h4>
                    <div style="position: relative; padding-left: 20px; border-left: 2px solid var(--sage-green); margin-left: 8px; display: flex; flex-direction: column; gap: 15px;">
                        ${timelineEntries.map((step, idx) => `
                            <div style="position: relative;">
                                <div style="position: absolute; left: -26px; top: 2px; width: 10px; height: 10px; border-radius: 50%; background: ${idx === timelineEntries.length - 1 ? 'var(--champagne-gold)' : 'var(--forest-green)'}; border: 2px solid var(--white);"></div>
                                <div style="font-size: 0.75rem; font-weight: 700; color: var(--forest-green);">${step.status}</div>
                                <div style="font-size: 0.65rem; color: var(--gray-text); margin-top: 1px;">by ${step.user} &bull; ${step.timestamp}</div>
                                ${step.reason ? `<div style="font-size: 0.65rem; color: #e24949; font-style: italic; margin-top: 2px;">Reason: "${step.reason}"</div>` : ''}
                                ${step.notes ? `<div style="font-size: 0.65rem; color: var(--gray-text); margin-top: 2px;">Notes: "${step.notes}"</div>` : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
                
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 15px;">
                <button type="button" class="btn btn-primary" onclick="closeBookingModal()" style="width: 100%; height: 44px;">Close Booking Panel</button>
            </div>
        </div>
    `;
    el.classList.add('active');
}

function renderNegotiationSection(b, negHistory) {
    const formatGHS = (val) => 'GHS ' + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    
    if (b.status === 'Pending') {
        return `
            <div style="border: 1.5px dashed var(--champagne-gold); padding: 12px; border-radius: 10px; background: rgba(212,175,55,0.03);">
                <h5 style="font-size: 0.75rem; font-weight: 700; margin-bottom: 6px; color: var(--forest-green);"><i class="fa-solid fa-gavel"></i> Simulation Panel: Vendor Action</h5>
                <p style="font-size: 0.65rem; color: var(--gray-text); margin-bottom: 8px;">Simulate the response options from the vendor coordinator.</p>
                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <button class="btn btn-outline" onclick="simulateVendorResponse(${b.id}, 'Vendor Accepted')" style="padding: 6px 10px; font-size: 0.65rem; height: auto;">Accept Booking</button>
                    <button class="btn btn-outline" onclick="simulateVendorResponse(${b.id}, 'Vendor Rejected')" style="padding: 6px 10px; font-size: 0.65rem; height: auto; border-color: #e24949; color: #e24949;">Reject Booking</button>
                    <button class="btn btn-outline" onclick="simulateVendorCounter(${b.id})" style="padding: 6px 10px; font-size: 0.65rem; height: auto; border-color: var(--champagne-gold); color: var(--champagne-gold);">Counter-Offer</button>
                </div>
            </div>
        `;
    }
    
    if (b.status === 'Counter-offer Sent') {
        return `
            <div style="border: 1.5px dashed var(--champagne-gold); padding: 12px; border-radius: 10px; background: rgba(212,175,55,0.03);">
                <h5 style="font-size: 0.75rem; font-weight: 700; margin-bottom: 6px; color: var(--forest-green);"><i class="fa-solid fa-gavel"></i> Review Counter-Offer</h5>
                <p style="font-size: 0.65rem; color: var(--gray-text); margin-bottom: 8px;">Vendor proposed counter-offer quote: <strong>${formatGHS(b.price)}</strong></p>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-primary" onclick="simulateVendorResponse(${b.id}, 'Vendor Accepted')" style="padding: 6px 12px; font-size: 0.65rem; height: auto; flex: 1;">Accept Offer</button>
                    <button class="btn btn-outline" onclick="simulateVendorResponse(${b.id}, 'Vendor Rejected')" style="padding: 6px 12px; font-size: 0.65rem; height: auto; flex: 1; border-color: #e24949; color: #e24949;">Decline</button>
                    <button class="btn btn-outline" onclick="navigateToChatDirect(${b.vendor_id})" style="padding: 6px 12px; font-size: 0.65rem; height: auto; flex: 1.2;"><i class="fa-solid fa-comments"></i> Chat</button>
                </div>
            </div>
        `;
    }
    
    return '';
}


window.startVendorChat = function(vid) {
    console.log("Opening chat with vendor/user ID:", vid);
    if (!state.user) {
        if (typeof showPushNotification === 'function') showPushNotification('Login Required', 'Please log in to chat with vendors.');
        if (typeof openAuthModal === 'function') openAuthModal('login');
        return;
    }
    if (vid) state.activeChatVendorId = parseInt(vid);
    if (typeof navigateTo === 'function') {
        navigateTo('chat');
    }
};
window.navigateToChatDirect = window.startVendorChat;


function renderPaymentSection(b) {
    if (b.status !== 'Vendor Accepted' && b.status !== 'Deposit Paid' && b.status !== 'Fully Paid') return '';
    
    const formatGHS = (val) => 'GHS ' + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    
    const depositAmount = b.price * 0.3; // 30% deposit
    const balanceAmount = b.price - (b.deposit_paid || 0);
    
    if (!b.payment_status || b.payment_status === 'Unpaid') {
        return `
            <div style="border: 1.5px solid var(--forest-green); padding: 12px; border-radius: 10px; background: rgba(45,90,60,0.03);">
                <h5 style="font-size: 0.75rem; font-weight: 700; margin-bottom: 6px; color: var(--forest-green);"><i class="fa-solid fa-credit-card"></i> Make Secure Payment</h5>
                <p style="font-size: 0.65rem; color: var(--gray-text); margin-bottom: 8px;">Lock in your date. Pay 30% deposit or full amount.</p>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-primary" onclick="submitSimulatedPayment(${b.id}, ${depositAmount}, 'Deposit Paid')" style="padding: 8px 12px; font-size: 0.7rem; height: auto; flex: 1;">Pay Deposit (${formatGHS(depositAmount)})</button>
                    <button class="btn btn-outline" onclick="submitSimulatedPayment(${b.id}, ${b.price}, 'Fully Paid')" style="padding: 8px 12px; font-size: 0.7rem; height: auto; flex: 1;">Pay Full (${formatGHS(b.price)})</button>
                </div>
            </div>
        `;
    }
    
    if (b.payment_status === 'Deposit Paid') {
        return `
            <div style="border: 1.5px solid var(--forest-green); padding: 12px; border-radius: 10px; background: rgba(45,90,60,0.03);">
                <h5 style="font-size: 0.75rem; font-weight: 700; margin-bottom: 6px; color: var(--forest-green);"><i class="fa-solid fa-credit-card"></i> Clear Outstanding Balance</h5>
                <p style="font-size: 0.65rem; color: var(--gray-text); margin-bottom: 8px;">Paid GHS ${b.deposit_paid || 0}. Settle remaining outstanding balance before event day.</p>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-primary" onclick="submitSimulatedPayment(${b.id}, ${balanceAmount}, 'Fully Paid')" style="padding: 8px 12px; font-size: 0.7rem; height: auto; width: 100%;">Pay Balance (${formatGHS(balanceAmount)})</button>
                </div>
            </div>
        `;
    }
    
    if (b.payment_status === 'Fully Paid') {
        return `
            <div style="border: 1.5px solid var(--forest-green); padding: 12px; border-radius: 10px; background: rgba(45,90,60,0.02); text-align: center;">
                <span style="font-size: 1.3rem; color: var(--forest-green);"><i class="fa-solid fa-circle-check"></i></span>
                <h5 style="font-size: 0.75rem; font-weight: 700; margin-top: 4px; color: var(--forest-green); margin-bottom: 2px;">Fully Paid & Hired</h5>
                <p style="font-size: 0.65rem; color: var(--gray-text);">All financial transactions complete. Dates are fully secured.</p>
            </div>
        `;
    }
    
    return '';
}

async function simulateVendorResponse(bookingId, status) {
    let reason = '';
    if (status === 'Vendor Rejected') {
        reason = prompt("Enter rejection/cancellation reason:") || "Vendor fully booked on selected date";
    }
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=update_booking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: bookingId, status: status, reason: reason })
        });
        const data = await res.json();
        if (data.success) {
            showPushNotification("Booking Updated", `Status changed to ${status}.`);
            await fetchBookings();
            await fetchTrackerStats();
            await fetchTrackerTasks();
            openBookingNegotiationModal(bookingId);
        }
    } catch(e) {
        console.error(e);
    }
}

async function simulateVendorCounter(bookingId) {
    const counterVal = parseFloat(prompt("Enter counter offer price (GHS):"));
    if (isNaN(counterVal) || counterVal <= 0) return;
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=update_booking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: bookingId, status: 'Counter-offer Sent', price: counterVal })
        });
        const data = await res.json();
        if (data.success) {
            showPushNotification("Counter Offer Proposal", `Sent counter offer of GHS ${counterVal}.`);
            await fetchBookings();
            openBookingNegotiationModal(bookingId);
        }
    } catch(e) {
        console.error(e);
    }
}

async function submitSimulatedPayment(bookingId, amount, paymentStatus) {
    const isDeposit = paymentStatus === 'Deposit Paid';
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=update_booking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: bookingId,
                deposit_paid: isDeposit ? amount : null,
                balance_paid: !isDeposit ? amount : null,
                payment_status: paymentStatus,
                status: paymentStatus
            })
        });
        const data = await res.json();
        if (data.success) {
            showPushNotification("Payment Successful! 💰", `${paymentStatus} details processed securely.`);
            await fetchBookings();
            await fetchTrackerTasks();
            await fetchTrackerStats();
            openBookingNegotiationModal(bookingId);
        }
    } catch(e) {
        console.error(e);
    }
}

// Expand/Collapse task card details
function toggleTaskExpand(taskId) {
    if (state.expandedTaskId == taskId) {
        state.expandedTaskId = null;
    } else {
        state.expandedTaskId = taskId;
    }
    renderBookingsScreen();
}

// Sync Checklist Calendar integration simulation
function triggerCalendarSync() {
    showPushNotification("Calendar Sync Successful", "All wedding checklist milestones synced to Google/Apple Calendar.");
}

// Toggle completion status via API
async function toggleTaskCompletion(taskId, isCompleted) {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=update_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, completed: isCompleted })
        });
        const data = await res.json();
        
        if (data.success) {
            const task = state.trackerTasks.find(t => t.id == taskId);
            const title = isCompleted ? "Milestone Completed! 🎉" : "Milestone Reopened ⏳";
            const desc = isCompleted 
                ? `Checked off: "${task.task_name}"` 
                : `Reopened: "${task.task_name}"`;
                
            showPushNotification(title, desc);
            
            await fetchTrackerTasks();
            await fetchTrackerStats();
            renderBookingsScreen();
        }
    } catch (e) {
        console.error("Error toggling task completion", e);
    }
}

// Save detailed notes, priority, date via API
async function saveTaskDetails(taskId) {
    const notesVal = document.getElementById(`notes-${taskId}`).value;
    const priorityVal = document.getElementById(`priority-${taskId}`).value;
    const dateVal = document.getElementById(`date-${taskId}`).value;
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=update_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: taskId,
                notes: notesVal,
                priority: priorityVal,
                estimated_date: dateVal
            })
        });
        const data = await res.json();
        
        if (data.success) {
            showPushNotification("Details Saved", "Task details updated successfully.");
            state.expandedTaskId = null; // collapse on save
            
            await fetchTrackerTasks();
            await fetchTrackerStats();
            renderBookingsScreen();
        }
    } catch (e) {
        console.error("Error saving task details", e);
    }
}

// Add Custom Task Modal sheet flow
function openAddTaskModal() {
    const el = document.getElementById('booking-modal'); // Re-use modal container
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in">
            <h3 class="modal-title">Create Custom Milestone</h3>
            <form id="add-task-submit-form" onsubmit="submitAddTask(event)">
                <div class="form-group">
                    <label class="form-label">Task Name</label>
                    <input type="text" class="form-input" id="new-task-name" required placeholder="e.g. Schedule final dress fitting">
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select class="form-input" id="new-task-priority" required>
                        <option value="High">High Priority</option>
                        <option value="Medium" selected>Medium Priority</option>
                        <option value="Low">Low Priority</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Target Date</label>
                    <input type="date" class="form-input" id="new-task-date" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea class="form-input" id="new-task-notes" placeholder="Any details, locations, contact info..." style="height: 60px; padding: 8px; resize: none;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeBookingModal()" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:1.5;">Create Milestone</button>
                </div>
            </form>
        </div>
    `;
    el.classList.add('active');
    
    // Set default date to 30 days out
    const dateInput = document.getElementById('new-task-date');
    const futureDate = new Date();
    futureDate.setDate(futureDate.getDate() + 30);
    dateInput.value = futureDate.toISOString().split('T')[0];
}

async function submitAddTask(event) {
    event.preventDefault();
    const taskNameVal = document.getElementById('new-task-name').value;
    const priorityVal = document.getElementById('new-task-priority').value;
    const dateVal = document.getElementById('new-task-date').value;
    const notesVal = document.getElementById('new-task-notes').value;
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=add_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_name: taskNameVal,
                priority: priorityVal,
                estimated_date: dateVal,
                notes: notesVal
            })
        });
        const data = await res.json();
        
        if (data.success) {
            closeBookingModal();
            showPushNotification("Custom Task Created", `"${taskNameVal}" has been added to checklist.`);
            
            await fetchTrackerTasks();
            await fetchTrackerStats();
            renderBookingsScreen();
        }
    } catch (e) {
        console.error("Error creating custom task", e);
    }
}

// Delete custom task via API
async function deleteTask(taskId) {
    if (!confirm("Are you sure you want to delete this custom milestone?")) return;
    
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=delete_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId })
        });
        const data = await res.json();
        
        if (data.success) {
            showPushNotification("Milestone Removed", "Custom task deleted from checklist.");
            state.expandedTaskId = null;
            
            await fetchTrackerTasks();
            await fetchTrackerStats();
            renderBookingsScreen();
        }
    } catch (e) {
        console.error("Error deleting task", e);
    }
}

// Favorites Screen Component
function renderFavoritesScreen() {
    const el = document.getElementById('screen-favorites');
    el.innerHTML = `
        <div class="home-section anim-fade-in" style="padding-top: 10px;">
            <h2>Saved Favorites</h2>
            <p class="greeting-sub" style="margin-bottom: 20px;">Quick access to your preferred wedding vendors</p>
            
            <div id="favorites-list-container">
                ${state.favorites.length > 0 ? renderVendorList(state.favorites) : `
                    <div style="text-align: center; padding: 60px 20px; color: var(--sage-green);">
                        <i class="fa-solid fa-heart-crack" style="font-size: 2.5rem; margin-bottom: 12px;"></i>
                        <p>Your favorites folder is empty. Click the heart icon on any vendor to save them here.</p>
                        <button class="btn btn-primary" onclick="navigateTo('home')" style="margin-top: 15px;">Discover Vendors</button>
                    </div>
                `}
            </div>
        </div>
    `;
}

// 7. Favorite Toggle API Action
async function toggleFavorite(vendorId, buttonEl) {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=toggle_favorite', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ vendor_id: vendorId })
        });
        const data = await res.json();
        if (data.success) {
            // Update local button UI instantly
            if (data.is_favorite) {
                buttonEl.classList.add('active');
            } else {
                buttonEl.classList.remove('active');
            }
            
            // Reload all list states
            await fetchFavorites();
            await fetchVendors();
            
            // Refresh screen if currently viewing favorites
            if (state.currentScreen === 'favorites') {
                renderFavoritesScreen();
            }
        }
    } catch (e) {
        console.error("Error toggling favorite", e);
    }
}

// 8. Simulated Messaging Flow
// 8. Simulated Messaging Flow

window.startVendorChat = function(vid) {
    console.log("Opening chat with vendor/user ID:", vid);
    if (!state.user) {
        if (typeof showPushNotification === 'function') showPushNotification('Login Required', 'Please log in to chat with vendors.');
        if (typeof openAuthModal === 'function') openAuthModal('login');
        return;
    }
    if (vid) state.activeChatVendorId = parseInt(vid);
    if (typeof navigateTo === 'function') {
        navigateTo('chat');
    }
};
window.navigateToChatDirect = window.startVendorChat;


async function renderChatScreen() {
    const el = document.getElementById('screen-chat');
    const vendorId = state.activeChatVendorId;
    
    if (!vendorId) {
        // Render Inbox View
        try {
            const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=chat_inbox');
            const inbox = await res.json();
            
            el.innerHTML = `
                <div class="inbox-container anim-fade-in" style="padding: 15px; height: 100%; display: flex; flex-direction: column;">
                    <h2 style="font-size: 1.4rem; color: var(--forest-green); margin-bottom: 5px;">Messages</h2>
                    <p style="font-size: 0.8rem; color: var(--gray-text); margin-bottom: 20px;">Connect with your booked and favorited vendor coordinators</p>
                    
                    <div class="inbox-list" style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px;">
                        ${inbox.length > 0 ? inbox.map(item => `
                            <div class="inbox-item" onclick="startVendorChat(${item.id})" style="display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; background: var(--white); border: 1px solid var(--gray-light); cursor: pointer; transition: transform 0.2s, background 0.2s;">
                                <img src="${item.logo}" alt="${item.name}" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 1px solid var(--sage-green);">
                                <div style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <h4 style="margin: 0; font-size: 0.9rem; color: var(--forest-green); font-weight: 700;">${item.name}</h4>
                                    </div>
                                    <p style="margin: 3px 0 0 0; font-size: 0.75rem; color: var(--champagne-gold); font-weight: 600; text-transform: uppercase;">${item.category}</p>
                                </div>
                                <i class="fa-solid fa-chevron-right" style="color: var(--sage-green); font-size: 0.8rem;"></i>
                            </div>
                        `).join('') : `
                            <div style="text-align: center; padding: 60px 20px; color: var(--sage-green);">
                                <i class="fa-solid fa-comments" style="font-size: 2.5rem; margin-bottom: 12px;"></i>
                                <p>You have no active chats yet. Visit a vendor page and click "Chat" to start a conversation.</p>
                                <button class="btn btn-primary" onclick="navigateTo('home')" style="margin-top: 15px;">Find Vendors</button>
                            </div>
                        `}
                    </div>
                </div>
            `;
        } catch (e) {
            console.error("Error loading chat inbox", e);
            el.innerHTML = `<div style="padding: 20px; text-align: center; color: red;">Failed to load messages.</div>`;
        }
        return;
    }
    
    // Fetch vendor details
    const vRes = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=vendor_details&id=' + vendorId);
    const v = await vRes.json();
    
    el.innerHTML = `
        <div class="chat-container anim-fade-in" style="height: 100%; display: flex; flex-direction: column;">
            <div class="chat-header" style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 12px 16px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button class="btn-icon back-btn" style="width: 32px; height: 32px; box-shadow: none; border: 1px solid var(--gray-light);"><i class="fa-solid fa-arrow-left"></i></button>
                    <img src="${v.logo}" alt="${v.name}" class="chat-avatar" style="width: 36px; height: 36px; border-radius: 50%;">
                    <div class="chat-vendor-info">
                        <h4 style="font-size: 0.85rem; margin: 0; line-height: 1.2;">${v.name}</h4>
                        <span style="font-size: 0.65rem; color: var(--sage-green);">${v.availability} • Active</span>
                    </div>
                </div>
                <!-- Call Signaling Buttons -->
                <div style="display: flex; gap: 8px; margin-right: 5px;">
                    <button class="btn-icon" onclick="triggerChatCall('Audio', '${v.name}')" style="width: 34px; height: 34px; background: rgba(45,90,60,0.06); border-radius: 50%; box-shadow: none; border: 1.5px solid var(--sage-green); color: var(--forest-green); display: flex; align-items: center; justify-content: center;" title="Voice Call">
                        <i class="fa-solid fa-phone" style="font-size: 0.8rem;"></i>
                    </button>
                </div>
            </div>
            
            <div class="chat-messages" id="chat-msg-area" style="flex: 1; padding: 15px; overflow-y: auto;">
                <!-- Messages load here -->
            </div>
            
            <div class="chat-input-row" style="padding: 10px 15px; display: flex; align-items: center; gap: 8px; border-top: 1px solid var(--gray-light);">
                <input type="text" placeholder="Type a message..." class="chat-input" id="chat-text-input" style="flex: 1; height: 38px; border-radius: 19px;">
                <button class="chat-send-btn" id="chat-submit-btn" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-paper-plane" style="font-size: 0.85rem;"></i>
                </button>
            </div>
        </div>
    `;
    
    // Load history
    await loadChatHistory(vendorId);
    
    // Bind send handlers
    const input = document.getElementById('chat-text-input');
    const sendBtn = document.getElementById('chat-submit-btn');
    
    if (input) {
        input.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') sendChatMessage(vendorId);
        });
    }
    
    if (sendBtn) {
        sendBtn.addEventListener('click', () => {
            sendChatMessage(vendorId);
        });
    }
    
    // Set up real-time simulation interval (every 18 seconds, send vendor tip/reply)
    if (state.chatInterval) clearInterval(state.chatInterval);
    
    const simulatedTips = [
        "Just wanted to check if you have finalized your ceremony venue location details yet? 🏰",
        "Let me know if you would like me to draft a custom contract options file for you! 📝",
        "Hello! I am checking my calendar slot. Are we looking at a morning or afternoon start time? ☀️",
        "Don't forget to add our booking costs into your Smart Budget planner so we keep you on track! 💰",
        "Ohati tells me you completed another planning milestone! Keep up the good momentum! 🎉"
    ];
    
    state.chatInterval = setInterval(async () => {
        if (state.currentScreen !== 'chat' || state.activeChatVendorId !== vendorId) {
            clearInterval(state.chatInterval);
            return;
        }
        
        const randomTip = simulatedTips[Math.floor(Math.random() * simulatedTips.length)];
        
        // Show typing indicator
        const area = document.getElementById('chat-msg-area');
        if (!area) return;
        
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'typing-indicator';
        typingIndicator.id = 'chat-typing-indicator';
        typingIndicator.innerHTML = `
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        `;
        area.appendChild(typingIndicator);
        scrollToBottom('chat-msg-area');
        
        setTimeout(async () => {
            const indicator = document.getElementById('chat-typing-indicator');
            if (indicator) indicator.remove();
            
            // Post message via API so it persists in database
            await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vendor_id: vendorId, message: randomTip, simulate_vendor: true })
            });
            
            const vendorMsgEl = document.createElement('div');
            vendorMsgEl.className = 'msg-bubble msg-vendor';
            vendorMsgEl.innerHTML = randomTip;
            area.appendChild(vendorMsgEl);
            scrollToBottom('chat-msg-area');
        }, 1500);
        
    }, 18000);
}

async function loadChatHistory(vendorId) {
    try {
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=chat_history&vendor_id=' + vendorId);
        const history = await res.json();
        
        const area = document.getElementById('chat-msg-area');
        if (!area) return;
        
        area.innerHTML = history.map(msg => `
            <div class="msg-bubble msg-${msg.sender === 'user' ? 'user' : 'vendor'}">
                ${msg.message.replace(/\n/g, '<br>')}
            </div>
        `).join('');
        
        scrollToBottom('chat-msg-area');
    } catch (e) {
        console.error("Error loading chat history", e);
    }
}

async function sendChatMessage(vendorId) {
    const input = document.getElementById('chat-text-input');
    const msg = input.value.trim();
    if (!msg) return;
    
    input.value = '';
    
    // Render user message instantly in view
    const area = document.getElementById('chat-msg-area');
    const userMsgEl = document.createElement('div');
    userMsgEl.className = 'msg-bubble msg-user';
    userMsgEl.innerHTML = msg;
    area.appendChild(userMsgEl);
    scrollToBottom('chat-msg-area');
    
    // Display typing indicator
    const typingIndicator = document.createElement('div');
    typingIndicator.className = 'typing-indicator';
    typingIndicator.id = 'chat-typing-indicator';
    typingIndicator.innerHTML = `
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
    `;
    area.appendChild(typingIndicator);
    scrollToBottom('chat-msg-area');
    
    try {
        // Send request to API
        const res = await fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ vendor_id: vendorId, message: msg })
        });
        
        const data = await res.json();
        
        // Remove typing indicator after delay to simulate natural responsiveness
        setTimeout(() => {
            const indicator = document.getElementById('chat-typing-indicator');
            if (indicator) indicator.remove();
            
            // Render vendor reply
            const vendorMsgEl = document.createElement('div');
            vendorMsgEl.className = 'msg-bubble msg-vendor';
            vendorMsgEl.innerHTML = data.vendor_reply.message.replace(/\n/g, '<br>');
            area.appendChild(vendorMsgEl);
            scrollToBottom('chat-msg-area');
        }, 1200);
        
    } catch (e) {
        console.error("Error sending message", e);
        const indicator = document.getElementById('chat-typing-indicator');
        if (indicator) indicator.remove();
    }
}

function scrollToBottom(elementId) {
    const el = document.getElementById(elementId);
    if (el) {
        el.scrollTop = el.scrollHeight;
    }
}

// 9. Lightbox component helpers
function openLightbox(imageUrl) {
    const el = document.getElementById('app-lightbox');
    el.innerHTML = `
        <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
        <img class="lightbox-img" src="${imageUrl}">
    `;
    el.classList.add('active');
}

function closeLightbox() {
    document.getElementById('app-lightbox').classList.remove('active');
}

// 10. Filter Drawer Handlers
function openFilterDrawer() {
    const drawer = document.getElementById('filter-drawer');
    drawer.innerHTML = `
        <div class="filter-content">
            <div class="filter-header">
                <h3>Refine Marketplace</h3>
                <span onclick="closeFilterDrawer()" style="cursor:pointer; font-size:1.4rem; color:var(--sage-green);">&times;</span>
            </div>
            
            <form id="filter-submit-form" onsubmit="applyFilters(event)">
                <div class="filter-row">
                    <label class="filter-label">Category</label>
                    <select class="filter-select" id="filt-category">
                        <option value="">All Categories</option>
                        ${state.categories.map(c => `
                            <option value="${c.name}" ${state.filters.category === c.name ? 'selected' : ''}>${c.name}</option>
                        `).join('')}
                    </select>
                </div>
                
                <div class="filter-row">
                    <label class="filter-label">Location (Ghana Cities)</label>
                    <select class="filter-select" id="filt-location">
                        <option value="">All Locations</option>
                        <option value="Accra" ${state.filters.location === 'Accra' ? 'selected' : ''}>Accra</option>
                        <option value="Kumasi" ${state.filters.location === 'Kumasi' ? 'selected' : ''}>Kumasi</option>
                        <option value="Tema" ${state.filters.location === 'Tema' ? 'selected' : ''}>Tema</option>
                        <option value="Takoradi" ${state.filters.location === 'Takoradi' ? 'selected' : ''}>Takoradi</option>
                        <option value="Akosombo" ${state.filters.location === 'Akosombo' ? 'selected' : ''}>Akosombo</option>
                        <option value="Axim" ${state.filters.location === 'Axim' ? 'selected' : ''}>Axim</option>
                    </select>
                </div>
                
                <div class="filter-row">
                    <label class="filter-label">Minimum Rating</label>
                    <select class="filter-select" id="filt-rating">
                        <option value="">Any Rating</option>
                        <option value="4.9" ${state.filters.rating === '4.9' ? 'selected' : ''}>⭐⭐⭐⭐⭐ 4.9+ Stars</option>
                        <option value="4.8" ${state.filters.rating === '4.8' ? 'selected' : ''}>⭐⭐⭐⭐ 4.8+ Stars</option>
                        <option value="4.7" ${state.filters.rating === '4.7' ? 'selected' : ''}>⭐⭐⭐ 4.7+ Stars</option>
                        <option value="4.5" ${state.filters.rating === '4.5' ? 'selected' : ''}>⭐⭐ 4.5+ Stars</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="button" class="btn btn-outline" onclick="clearFiltersForm()" style="flex:1;">Clear</button>
                    <button type="submit" class="btn btn-primary" style="flex:1.5;">Apply Filters</button>
                </div>
            </form>
        </div>
    `;
    drawer.classList.add('active');
}

function closeFilterDrawer() {
    document.getElementById('filter-drawer').classList.remove('active');
}

function clearFiltersForm() {
    document.getElementById('filt-category').value = '';
    document.getElementById('filt-location').value = '';
    document.getElementById('filt-rating').value = '';
}

function applyFilters(event) {
    event.preventDefault();
    state.filters.category = document.getElementById('filt-category').value;
    state.filters.location = document.getElementById('filt-location').value;
    state.filters.rating = document.getElementById('filt-rating').value;
    
    closeFilterDrawer();
    fetchVendors().then(() => {
        navigateTo('search');
        renderSearchScreen();
    });
}

function triggerChatCall(type, name) {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    el.classList.add('active');
    
    // Set up ring tone sound effect using Web Audio API
    let audioCtx = null;
    let oscillator = null;
    let ringInterval = null;
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (AudioContext) {
            audioCtx = new AudioContext();
            
            // Ringing beep generator
            const playRingSound = () => {
                if (!audioCtx) return;
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(440, audioCtx.currentTime); // A4 note
                osc.frequency.exponentialRampToValueAtTime(480, audioCtx.currentTime + 1.2);
                gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 1.4);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 1.5);
            };
            
            playRingSound();
            ringInterval = setInterval(playRingSound, 3000);
        }
    } catch(err) {
        console.log("Web Audio not allowed or failed", err);
    }
    
    const stopAudio = () => {
        if (ringInterval) {
            clearInterval(ringInterval);
            ringInterval = null;
        }
        if (audioCtx) {
            audioCtx.close();
            audioCtx = null;
        }
    };
    
    // Draw calling screen
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 400px; padding: 0; overflow: hidden; border-radius: 28px; background: #131a17; border: 1.5px solid var(--champagne-gold); color: #fff; height: 500px; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
            <!-- Video Call Camera Feed simulation placeholder -->
            ${type === 'Video' ? `
                <div id="call-video-bg" style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0.1), rgba(0,0,0,0.8)), url('img/vendor_1.jpg'); background-size: cover; background-position: center; transition: all 1s ease;">
                    <div id="self-camera-view" style="position: absolute; top: 20px; right: 20px; width: 80px; height: 120px; border-radius: 12px; border: 2px solid #fff; background: #222; box-shadow: 0 8px 20px rgba(0,0,0,0.3); overflow: hidden;">
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; color: #aaa; text-align: center; padding: 4px;">Self Camera</div>
                    </div>
                </div>
            ` : `
                <div style="position: absolute; inset: 0; background: radial-gradient(circle at center, #1b2622 0%, #0d1210 100%);"></div>
            `}
            
            <!-- Call Header -->
            <div style="z-index: 2; padding: 30px 20px; text-align: center; width: 100%;">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; color: var(--champagne-gold); font-weight: 700; margin-bottom: 10px;">
                    Simulated ${type} Call
                </div>
                <h3 style="font-size: 1.6rem; font-weight: 700; margin: 0; color: #fff;">${name}</h3>
                <div id="call-status-label" style="font-size: 0.85rem; color: #a3b2ab; margin-top: 8px; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <i class="fa-solid fa-circle-notch fa-spin"></i> Ringing...
                </div>
            </div>
            
            <!-- Call Body Avatar -->
            <div style="z-index: 2; display: flex; justify-content: center; align-items: center; flex: 1;">
                ${type === 'Audio' ? `
                    <div style="position: relative; width: 120px; height: 120px;">
                        <div style="width: 120px; height: 120px; border-radius: 50%; border: 3px solid var(--champagne-gold); overflow: hidden; background: #fff; position: relative; z-index: 2;">
                            <img src="img/vendor_1.jpg" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    </div>
                ` : ''}
            </div>
            
            <!-- Call Actions Footer -->
            <div style="z-index: 2; padding: 40px 20px; text-align: center; width: 100%; display: flex; flex-direction: column; gap: 20px;">
                <div style="display: flex; justify-content: center; gap: 25px;">
                    <!-- Mute Button -->
                    <button class="btn-icon" onclick="this.classList.toggle('active')" style="width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.08); border: none; color: #fff; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-microphone-slash"></i>
                    </button>
                    <!-- Hang Up -->
                    <button class="btn-icon" id="hang-up-btn" style="width: 60px; height: 60px; border-radius: 50%; background: #e24949; border: none; color: #fff; font-size: 1.4rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 20px rgba(226, 73, 73, 0.3);">
                        <i class="fa-solid fa-phone-slash"></i>
                    </button>
                    <!-- Speaker Button -->
                    <button class="btn-icon" onclick="this.classList.toggle('active')" style="width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.08); border: none; color: #fff; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-volume-high"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Bind Hang Up
    document.getElementById('hang-up-btn').addEventListener('click', () => {
        stopAudio();
        closeBookingModal();
        showPushNotification("Call Ended", `Simulated conversation with ${name} complete.`);
    });
    
    // Simulate Connect after 3 seconds
    setTimeout(() => {
        const label = document.getElementById('call-status-label');
        if (!label) {
            stopAudio();
            return;
        }
        stopAudio();
        
        label.innerHTML = `<span style="color: var(--champagne-gold);"><i class="fa-solid fa-circle" style="font-size: 0.55rem; animation: pulse 1s infinite;"></i> Connected (Simulated)</span>`;
        
        // Start duration counter
        let seconds = 0;
        const durInterval = setInterval(() => {
            const label2 = document.getElementById('call-status-label');
            if (!label2) {
                clearInterval(durInterval);
                return;
            }
            seconds++;
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            label2.innerHTML = `<span style="color: var(--champagne-gold);"><i class="fa-solid fa-circle" style="font-size: 0.55rem; animation: pulse 1s infinite; margin-right: 4px;"></i> ${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}</span>`;
        }, 1000);
        
        // Automatically hang up after 15 seconds
        setTimeout(() => {
            const btn = document.getElementById('hang-up-btn');
            if (btn) btn.click();
        }, 15000);
        
    }, 3000);
}

function openNotificationsModal() {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    
    const displayName = state.currentUser ? state.currentUser.name : 'Guest';
    
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 420px; padding: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 12px; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 1.1rem; color: var(--forest-green); display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-bell" style="color: var(--champagne-gold);"></i> Notifications Feed
                </h3>
                <span onclick="closeBookingModal()" style="cursor: pointer; font-size: 1.5rem; color: var(--sage-green); font-weight: 300;">&times;</span>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 12px; max-height: 300px; overflow-y: auto; padding-right: 5px;">
                <div style="padding: 10px; border-radius: 8px; background: rgba(45,90,60,0.04); border-left: 4px solid var(--forest-green); font-size: 0.75rem;">
                    <div style="font-weight: 700; color: var(--forest-green); display: flex; justify-content: space-between;">
                        <span>System Session Active</span>
                        <span style="font-size: 0.65rem; color: var(--gray-text);">Just Now</span>
                    </div>
                    <div style="margin-top: 4px; color: var(--gray-text);">You are logged in as <strong>${displayName}</strong>. Local storage and event state initialized.</div>
                </div>
                
                <div style="padding: 10px; border-radius: 8px; background: rgba(212,175,55,0.04); border-left: 4px solid var(--champagne-gold); font-size: 0.75rem;">
                    <div style="font-weight: 700; color: var(--forest-green); display: flex; justify-content: space-between;">
                        <span>New Vendor Recommendation</span>
                        <span style="font-size: 0.65rem; color: var(--gray-text);">${formatRelativeTime(new Date(Date.now() - 600000))}</span>
                    </div>
                    <div style="margin-top: 4px; color: var(--gray-text);">Chill & Serve Ghana has been added to your recommended vendors list based on your theme.</div>
                </div>
                
                <div style="padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.02); border-left: 4px solid var(--sage-green); font-size: 0.75rem;">
                    <div style="font-weight: 700; color: var(--forest-green); display: flex; justify-content: space-between;">
                        <span>Welcome to Ohati!</span>
                        <span style="font-size: 0.65rem; color: var(--gray-text);">${formatRelativeTime(new Date(Date.now() - 7200000))}</span>
                    </div>
                    <div style="margin-top: 4px; color: var(--gray-text);">Your smart event consultant setup guide is ready. Tap "Plan My Event" to get started!</div>
                </div>
            </div>
            
            <button class="btn btn-primary" onclick="closeBookingModal()" style="width: 100%; margin-top: 20px; height: 40px;">Clear All & Close</button>
        </div>
    `;
    el.classList.add('active');
}

function toggleSidebar(open) {
    try {
        const overlays = [document.getElementById('sidebar-overlay'), document.getElementById('app-sidebar-overlay')].filter(Boolean);
        if (overlays.length === 0) return;

        const mainOverlay = overlays[0];
        const shouldOpen = (open === undefined || open === null) ? (!mainOverlay.classList.contains('open') && !mainOverlay.classList.contains('active')) : !!open;

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
            } catch (uiErr) {}
        }
    } catch (err) {}
}
window.toggleSidebar = toggleSidebar;

function updateUserSessionUI() {
    const nameEl = document.getElementById('sidebar-user-name');
    const emailEl = document.querySelector('.sidebar-email');
    const avatarEl = document.getElementById('sidebar-user-avatar');
    
    const homeGreeting = document.querySelector('.greeting-text h2');
    const homeAvatar = document.querySelector('.greeting-row img');
    
    if (state.currentUser) {
        if (nameEl) nameEl.innerText = state.currentUser.name;
        if (emailEl) emailEl.innerText = state.currentUser.email || '';
        if (avatarEl) avatarEl.src = state.currentUser.avatar || DEFAULT_USER_AVATAR;
        
        if (homeGreeting) homeGreeting.innerHTML = `Maba, ${state.currentUser.name.split(' ')[0]} ✨`;
        if (homeAvatar) homeAvatar.src = state.currentUser.avatar || DEFAULT_USER_AVATAR;
        
        const signInItem = document.getElementById('sidebar-signin-item');
        if (signInItem) {
            signInItem.innerHTML = `<i class="fa-solid fa-right-from-bracket"></i> <span>Sign Out</span>`;
            signInItem.onclick = () => { logoutUser(); toggleSidebar(false); };
        }
    } else {
        if (nameEl) nameEl.innerText = "Guest User";
        if (emailEl) emailEl.innerText = "Not signed in";
        if (avatarEl) avatarEl.src = DEFAULT_USER_AVATAR;
        
        if (homeGreeting) homeGreeting.innerText = "Maba, Guest ✨";
        if (homeAvatar) homeAvatar.src = DEFAULT_USER_AVATAR;
        
        const signInItem = document.getElementById('sidebar-signin-item');
        if (signInItem) {
            signInItem.innerHTML = `<i class="fa-solid fa-right-to-bracket"></i> <span>Sign In / Log In</span>`;
            signInItem.onclick = () => { openLoginModal(); toggleSidebar(false); };
        }
    }
}

function logoutUser() { if (typeof handleLogout === 'function') handleLogout(); }

function openProfileModal() {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    
    const ev = state.event || { event_type: 'Wedding', theme: 'N/A', estimated_budget: 25000, guest_count: 150 };
    const clientName = state.currentUser ? state.currentUser.name : '';
    const clientEmail = state.currentUser ? state.currentUser.email : '';
    
    const defaultAvatar = DEFAULT_USER_AVATAR;
    const currentAvatar = state.currentUser && state.currentUser.avatar ? state.currentUser.avatar : defaultAvatar;
    
    // Globally accessible profile image upload handler
    window.handleProfileImageUpload = function(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        if (!file.type.startsWith('image/')) {
            showPushNotification("Invalid File", "Please select a valid image file.");
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const base64Url = e.target.result;
            const preview = document.getElementById('profile-avatar-preview');
            const hiddenInp = document.getElementById('profile-avatar-input');
            
            if (preview) preview.src = base64Url;
            if (hiddenInp) hiddenInp.value = base64Url;
        };
        reader.readAsDataURL(file);
    };
    
    const themesList = [
        "Traditional Ghanaian & Kente",
        "Royal Gold & Ivory",
        "Modern Minimalist",
        "Forest Green & Gold",
        "Champagne & Blush Pink",
        "Rustic Vintage"
    ];
    const selectedTheme = ev.theme || "Royal Gold & Ivory";
    
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 440px; padding: 25px;">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-user-gear" style="color: var(--champagne-gold);"></i> Profile & Event Settings
            </h3>
            
            <form id="profile-settings-form" onsubmit="saveProfileSettings(event)">
                <div style="max-height: 380px; overflow-y: auto; padding-right: 5px; display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                    
                    <!-- User Photo Upload -->
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.05); margin-bottom: 5px;">
                        <img id="profile-avatar-preview" src="${currentAvatar}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--champagne-gold); box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                        <label class="btn btn-outline" style="padding: 6px 14px; font-size: 0.72rem; border-radius: 20px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; margin: 4px 0 0 0;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Profile Image
                            <input type="file" id="profile-image-file" accept="image/*" onchange="handleProfileImageUpload(event)" style="display: none;">
                        </label>
                        <input type="hidden" id="profile-avatar-input" value="${currentAvatar}">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Client Name</label>
                        <input type="text" class="form-input" id="profile-name" value="${clientName}" required placeholder="Ama & Kofi">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" class="form-input" id="profile-email" value="${clientEmail}" required placeholder="client-session@ohati.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Event Type</label>
                        <input type="text" class="form-input" id="profile-event-type" value="${ev.event_type}" disabled style="background-color: var(--gray-light); opacity: 0.7;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Theme Preference</label>
                        <select class="form-input" id="profile-theme" style="width: 100%; height: 42px; border-radius: 8px; border: 1px solid var(--gray-light); padding: 0 10px; font-family: inherit; font-size: 0.85rem; outline: none; background-color: var(--white); cursor: pointer;">
                            ${themesList.map(t => `<option value="${t}" ${t === selectedTheme ? 'selected' : ''}>${t}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Guest Count Target</label>
                        <input type="number" class="form-input" id="profile-guests" value="${ev.guest_count || 150}">
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; background: rgba(45,90,60,0.03); padding: 10px; border-radius: 8px; border: 1.5px dashed var(--sage-green);">
                        <i class="fa-solid fa-circle-check" style="color: var(--forest-green); font-size: 1.2rem;"></i>
                        <span style="font-size: 0.65rem; color: var(--gray-text);">You're a verified user</span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-outline" onclick="closeBookingModal()" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:1.5;">Save Settings</button>
                </div>
            </form>
        </div>
    `;
    el.classList.add('active');
}

function saveProfileSettings(e) {
    e.preventDefault();
    const name = document.getElementById('profile-name').value;
    const email = document.getElementById('profile-email').value;
    const theme = document.getElementById('profile-theme').value;
    const guests = document.getElementById('profile-guests').value;
    const avatar = document.getElementById('profile-avatar-input').value;
    
    if (!state.currentUser) {
        state.currentUser = {};
    }
    state.currentUser.name = name;
    state.currentUser.email = email;
    state.currentUser.avatar = avatar;
    localStorage.setItem('ohati_user_session', JSON.stringify(state.currentUser));
    
    if (state.event) {
        state.event.theme = theme;
        state.event.guest_count = guests;
    }
    
    closeBookingModal();
    updateUserSessionUI();
    showPushNotification("Profile Updated", "Local storage and theme configurations updated successfully.");
}

function openSignUpModal() {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 400px; padding: 25px;">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-user-plus" style="color: var(--champagne-gold);"></i> Create Ohati Account
            </h3>
            <p style="font-size: 0.7rem; color: var(--gray-text); margin-bottom: 15px;">Sign up to book vendors, track your planning budget, and milestones.</p>
            
            <form id="signup-submit-form" onsubmit="submitSignUp(event)">
                <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label">Your Name / Couple Names</label>
                        <input type="text" class="form-input" id="signup-name" required placeholder="e.g. Ama & Kofi">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="signup-email" required placeholder="name@domain.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-input" id="signup-phone" required placeholder="024 XXX XXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PIN / Password</label>
                        <input type="password" class="form-input" id="signup-password" required placeholder="Create PIN">
                    </div>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <button type="submit" class="btn btn-primary" style="height: 44px;">Sign Up & Register</button>
                    <button type="button" class="btn btn-outline" onclick="closeBookingModal()" style="height: 44px;">Cancel</button>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 15px; font-size: 0.75rem; color: var(--gray-text);">
                Already have an account? <a href="#" onclick="openLoginModal(); return false;" style="color: var(--forest-green); font-weight: 700; text-decoration: none;">Sign In</a>
            </div>
        </div>
    `;
    el.classList.add('active');
}

function submitSignUp(e) {
    e.preventDefault();
    const name = document.getElementById('signup-name').value;
    const email = document.getElementById('signup-email').value;
    const phone = document.getElementById('signup-phone').value;
    
    state.currentUser = { name, email, phone };
    localStorage.setItem('ohati_user_session', JSON.stringify(state.currentUser));
    localStorage.setItem('ohati_onboarded', 'true');
    
    closeBookingModal();
    updateUserSessionUI();
    
    showPushNotification("Account Created! 🎉", `Welcome to Ohati, ${name}!`);
    navigateTo('home');
}

function openLoginModal() {
    if (typeof showMandatoryAuthLockScreen === 'function') {
        showMandatoryAuthLockScreen('login');
    }
}

function submitLogin(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (typeof showMandatoryAuthLockScreen === 'function') {
        showMandatoryAuthLockScreen('login');
    }
}

// openWalletModal is now defined in modals.js with role-aware Vendor/Customer views.
// This legacy definition has been removed to prevent overriding.

function openHistoryModal() {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    
    const completedTasks = state.trackerTasks.filter(t => t.completed == 1);
    
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 440px; padding: 25px;">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-clock-rotate-left" style="color: var(--champagne-gold);"></i> Event Milestone History
            </h3>
            <p style="font-size: 0.7rem; color: var(--gray-text); margin-bottom: 15px;">Detailed chronological log of checked off items and setups.</p>
            
            <div style="max-height: 280px; overflow-y: auto; padding-right: 5px; display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                ${completedTasks.length > 0 ? completedTasks.map(t => `
                    <div style="padding: 10px; border-radius: 8px; background: rgba(45,90,60,0.04); border-left: 4px solid var(--forest-green); font-size: 0.75rem;">
                        <div style="font-weight: 700; color: var(--forest-green);">${t.task_name}</div>
                        <div style="color: var(--gray-text); margin-top: 3px; font-size: 0.65rem;">Completed on ${t.estimated_date} &bull; Category: ${t.category}</div>
                        ${t.cost > 0 ? `<div style="font-weight: 600; color: var(--champagne-gold); margin-top: 2px;">Cost: GHS ${t.cost.toLocaleString()}</div>` : ''}
                    </div>
                `).join('') : `
                    <div style="text-align: center; padding: 30px 10px; color: var(--sage-green);">
                        <i class="fa-solid fa-receipt" style="font-size: 2rem; margin-bottom: 8px;"></i>
                        <p style="font-size: 0.75rem; margin: 0;">No milestones checked off yet. Check off items in the Planning Tab timeline to log history.</p>
                    </div>
                `}
            </div>
            
            <button class="btn btn-primary" onclick="closeBookingModal()" style="width: 100%; height: 44px;">Close History Panel</button>
        </div>
    `;
    el.classList.add('active');
}

// Platform Reviews Helper & Interactive Actions
function formatLikes(val) {
    if (val >= 1000) {
        const k = val / 1000;
        return k % 1 === 0 ? k.toFixed(0) + 'k' : k.toFixed(1) + 'k';
    }
    return val;
}

window.toggleLikeReview = function(id, event) {
    if (event) event.stopPropagation();
    const rev = state.globalReviews.find(r => r.id === id);
    if (!rev) return;
    
    if (rev.liked) {
        rev.likes--;
        rev.liked = false;
        state.globalStats.likes--;
    } else {
        rev.likes++;
        rev.liked = true;
        state.globalStats.likes++;
    }
    
    const btn = event.currentTarget;
    if (btn) {
        btn.classList.toggle('liked');
        btn.style.color = rev.liked ? '#e24949' : 'var(--sage-green)';
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = rev.liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
        }
        const span = btn.querySelector('span');
        if (span) {
            span.innerText = rev.likes;
        }
    }
    
    const globalCountEl = document.getElementById('global-likes-count');
    if (globalCountEl) {
        globalCountEl.innerText = formatLikes(state.globalStats.likes);
    }
};

window.openPlatformReviewModal = function() {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    
    const authorName = state.currentUser ? state.currentUser.name : '';
    
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 400px; padding: 25px;">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-pen-to-square" style="color: var(--champagne-gold);"></i> Platform Experience Review
            </h3>
            <p style="font-size: 0.72rem; color: var(--gray-text); margin-bottom: 15px;">Tell us about your experience planning with Ohati.</p>
            
            <form id="platform-review-form" onsubmit="submitPlatformReview(event)">
                <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label">Your Name</label>
                        <input type="text" class="form-input" id="platform-rev-name" value="${authorName}" required placeholder="e.g. Abena Osei">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rating</label>
                        <select class="form-input" id="platform-rev-rating" style="cursor: pointer; width: 100%; height: 42px; border-radius: 8px; border: 1px solid var(--gray-light); padding: 0 10px; font-family: inherit; font-size: 0.85rem;">
                            <option value="5">⭐⭐⭐⭐⭐ (5 - Excellent)</option>
                            <option value="4">⭐⭐⭐⭐ (4 - Very Good)</option>
                            <option value="3">⭐⭐⭐ (3 - Average)</option>
                            <option value="2">⭐⭐ (2 - Poor)</option>
                            <option value="1">⭐ (1 - Terrible)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Your Review</label>
                        <textarea class="form-input" id="platform-rev-comment" required placeholder="Write your wedding planning experience..." style="height: 90px; resize: none; padding: 10px;"></textarea>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-outline" onclick="closeBookingModal()" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:1.5;">Submit Review</button>
                </div>
            </form>
        </div>
    `;
    el.classList.add('active');
};

window.submitPlatformReview = function(e) {
    e.preventDefault();
    const name = document.getElementById('platform-rev-name').value;
    const rating = parseInt(document.getElementById('platform-rev-rating').value);
    const comment = document.getElementById('platform-rev-comment').value;
    
    const randomViews = Math.floor(Math.random() * 150000) + 250000;
    const randomLikes = Math.floor(Math.random() * 80000) + 120000;
    
    const newRev = {
        id: state.globalReviews.length + 1,
        name: name,
        rating: rating,
        comment: comment,
        views: randomViews,
        likes: randomLikes,
        liked: false,
        avatar: state.currentUser && state.currentUser.avatar ? state.currentUser.avatar : DEFAULT_USER_AVATAR
    };
    
    state.globalReviews.unshift(newRev);
    closeBookingModal();
    renderHomeScreen();
    showPushNotification("Review Submitted! 🎉", "Thank you for sharing your experience on Ohati.");
};

window.openAllReviewsModal = function() {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 440px; padding: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 12px; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 1.1rem; color: var(--forest-green); display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-comments" style="color: var(--champagne-gold);"></i> All Couple Reviews
                </h3>
                <span onclick="closeBookingModal()" style="cursor: pointer; font-size: 1.5rem; color: var(--sage-green); font-weight: 300;">&times;</span>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 12px; max-height: 380px; overflow-y: auto; padding-right: 5px;">
                ${state.globalReviews.map(r => `
                    <div style="padding: 12px; border-radius: 12px; background: rgba(0,0,0,0.01); border: 1px solid var(--gray-light); font-size: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                            <img src="${r.avatar}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--champagne-gold);">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: var(--forest-green); display: flex; justify-content: space-between; align-items: center;">
                                    <span>${r.name}</span>
                                    <span style="font-size: 0.65rem; color: var(--gray-text); display: inline-flex; align-items: center; gap: 2px;"><i class="fa-solid fa-heart" style="color: #e24949;"></i> ${r.likes}</span>
                                </div>
                                <div style="color: var(--champagne-gold); font-size: 0.6rem; margin-top: 1px;">
                                    ${Array.from({length: r.rating}, () => '<i class="fa-solid fa-star"></i>').join('')}
                                    ${Array.from({length: 5 - r.rating}, () => '<i class="fa-regular fa-star"></i>').join('')}
                                </div>
                            </div>
                        </div>
                        <div style="color: var(--charcoal); line-height: 1.5;">"${r.comment}"</div>
                    </div>
                `).join('')}
            </div>
            
            <button class="btn btn-primary" onclick="closeBookingModal()" style="width: 100%; margin-top: 20px; height: 42px;">Close Reviews</button>
        </div>
    `;
    el.classList.add('active');
};

window.openConciergeModal = function() {
    const el = document.getElementById('booking-modal');
    if (!el) return;
    
    const clientName = state.currentUser ? state.currentUser.name : '';
    
    el.innerHTML = `
        <div class="modal-sheet anim-fade-in" style="max-width: 440px; padding: 25px;">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-crown" style="color: var(--champagne-gold);"></i> Full Event Concierge Planning
            </h3>
            <p style="font-size: 0.72rem; color: var(--gray-text); margin-bottom: 18px;">Tell us about your event, and our premium planners will handle everything.</p>
            
            <form id="concierge-request-form" onsubmit="submitConciergeRequest(event)">
                <div style="max-height: 360px; overflow-y: auto; padding-right: 5px; display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                    
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-input" id="concierge-name" value="${clientName}" required placeholder="e.g. Kwame & Abena Mensah">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Ghanaian Phone Number</label>
                        <input type="tel" class="form-input" id="concierge-phone" required placeholder="e.g. 0244123456" pattern="^\\+?233\\s?[0-9]{2}\\s?[0-9]{3}\\s?[0-9]{4}$|^0[0-9]{9}$" title="Enter a valid Ghanaian number like 0244123456 or +233 24 123 4567">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Event Type</label>
                        <select class="form-input" id="concierge-event-type" style="cursor: pointer; width: 100%; height: 42px; border-radius: 8px; border: 1px solid var(--gray-light); padding: 0 10px; font-family: inherit; font-size: 0.85rem;">
                            <option value="Wedding">Wedding</option>
                            <option value="Traditional Marriage / Engagement">Traditional Marriage / Engagement</option>
                            <option value="Corporate Gala">Corporate Gala</option>
                            <option value="Anniversary / Birthday">Anniversary / Birthday</option>
                            <option value="Other Premium Event">Other Premium Event</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Target Location / City</label>
                        <select class="form-input" id="concierge-location" style="cursor: pointer; width: 100%; height: 42px; border-radius: 8px; border: 1px solid var(--gray-light); padding: 0 10px; font-family: inherit; font-size: 0.85rem;">
                            <option value="Accra">Accra</option>
                            <option value="Kumasi">Kumasi</option>
                            <option value="Takoradi">Takoradi</option>
                            <option value="Tema">Tema</option>
                            <option value="Cape Coast">Cape Coast</option>
                            <option value="Other">Other Region / Destination</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Estimated Planning Budget (GHS)</label>
                        <input type="number" class="form-input" id="concierge-budget" required placeholder="e.g. 50000">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Expected Guest Count</label>
                        <input type="number" class="form-input" id="concierge-guests" required placeholder="e.g. 200">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Target Event Date</label>
                        <input type="date" class="form-input" id="concierge-date" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Special Requirements / Theme Description</label>
                        <textarea class="form-input" id="concierge-requirements" placeholder="Tell us about your dream event, preferred styling, specific must-haves..." style="height: 80px; resize: none; padding: 10px;"></textarea>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-outline" onclick="closeBookingModal()" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:1.5;">Submit Concierge Request</button>
                </div>
            </form>
        </div>
    `;
    el.classList.add('active');
};

window.submitConciergeRequest = function(e) {
    e.preventDefault();
    const name = document.getElementById('concierge-name').value;
    const phone = document.getElementById('concierge-phone').value;
    const type = document.getElementById('concierge-event-type').value;
    const location = document.getElementById('concierge-location').value;
    const budget = document.getElementById('concierge-budget').value;
    const guests = document.getElementById('concierge-guests').value;
    const date = document.getElementById('concierge-date').value;
    const reqs = document.getElementById('concierge-requirements').value;
    
    const requests = JSON.parse(localStorage.getItem('ohati_concierge_requests') || '[]');
    requests.push({
        id: Date.now(),
        name,
        phone,
        type,
        location,
        budget,
        guests,
        date,
        reqs,
        timestamp: new Date().toISOString()
    });
    localStorage.setItem('ohati_concierge_requests', JSON.stringify(requests));
    
    closeBookingModal();
    showPushNotification("Request Received! 👑", "Our Lead Concierge Planner will contact you shortly.");
};

window.formatLikes = function(num) {
    if (num === undefined || num === null) return '0';
    if (typeof num === 'string') return num;
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    }
    return num.toString();
};

window.toggleLikeReview = function(id, event) {
    if (event) event.stopPropagation();
    const rev = state.globalReviews.find(r => r.id === id);
    if (!rev) return;
    
    if (rev.liked) {
        rev.likes--;
        rev.liked = false;
    } else {
        rev.likes++;
        rev.liked = true;
    }
    
    const btn = event.currentTarget;
    if (btn) {
        btn.classList.toggle('liked');
        btn.style.color = rev.liked ? '#e24949' : 'var(--sage-green)';
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = rev.liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
        }
    }
    
    const card = btn ? btn.closest('.review-slide-card') : null;
    if (card) {
        const likesSpan = card.querySelector('.card-likes-count');
        if (likesSpan) {
            likesSpan.innerText = formatLikes(rev.likes);
        }
    }
};
