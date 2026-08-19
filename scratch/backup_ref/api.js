window.getOhatiApiBaseUrl = function() {
    const customUrl = localStorage.getItem('ohati_custom_server_url');
    if (customUrl) return customUrl.endsWith('/') ? customUrl + 'api.php' : customUrl + '/api.php';
    if (window.OHATI_API_BASE_URL) return window.OHATI_API_BASE_URL;

    const isNativeApp = (typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) ||
                        window.location.protocol === 'capacitor:' ||
                        window.location.protocol === 'file:' ||
                        (navigator.userAgent && navigator.userAgent.includes('OhatiApp'));

    if (isNativeApp) {
        return 'https://ohati.com/api.php';
    }
    return 'api.php';
};

const API = {
    get base() {
        return window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php';
    },
    _pendingRequests: new Map(),

    getAuthHeaders(extra = {}) {
        const headers = { ...extra };
        const token = localStorage.getItem('ohati_auth_token');
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        return headers;
    },

    async get(action, params = {}) {
        let url = `${this.base}?action=${action}`;
        for (const [k, v] of Object.entries(params)) {
            if (v !== '' && v !== null && v !== undefined) url += `&${k}=${encodeURIComponent(v)}`;
        }
        const res = await fetch(url, { 
            credentials: 'include',
            headers: this.getAuthHeaders()
        });
        let json;
        try {
            json = await res.json();
        } catch (e) {
            if (!res.ok) {
                throw new Error(`Request failed with status ${res.status}`);
            }
            throw new Error('Invalid JSON response from server');
        }
        if (!res.ok) {
            if (res.status === 401 && !['login', 'register', 'send_otp', 'verify_otp', 'session'].includes(action)) {
                if (typeof window.handleLogout === 'function' && window.state && window.state.user) {
                    window.handleLogout();
                }
            }
            throw new Error(json.error || 'Request failed');
        }
        return json;
    },

    async post(action, data = {}) {
        const reqKey = action + ':' + JSON.stringify(data);
        if (this._pendingRequests.has(reqKey)) {
            return this._pendingRequests.get(reqKey);
        }

        const promise = (async () => {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = (window.state && window.state.csrfToken) ? window.state.csrfToken : (csrfMeta ? csrfMeta.getAttribute('content') : '');
            const res = await fetch(`${this.base}?action=${action}`, {
                method: 'POST',
                credentials: 'include',
                headers: this.getAuthHeaders({ 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                }),
                body: JSON.stringify(data)
            });
            let json;
            try {
                json = await res.json();
            } catch (e) {
                if (!res.ok) {
                    throw new Error(`Request failed with status ${res.status}`);
                }
                throw new Error('Invalid JSON response from server');
            }
            if (json && json.csrf && window.state) {
                window.state.csrfToken = json.csrf;
            }
            if (!res.ok) {
                if (res.status === 401 && !['login', 'register', 'send_otp', 'verify_otp', 'session'].includes(action)) {
                    if (typeof window.handleLogout === 'function' && window.state && window.state.user) {
                        window.handleLogout();
                    }
                }
                const errObj = new Error((json && json.error) ? json.error : 'Request failed');
                if (json && typeof json === 'object') {
                    Object.assign(errObj, json);
                }
                throw errObj;
            }
            return json;
        })();

        this._pendingRequests.set(reqKey, promise);
        try {
            return await promise;
        } finally {
            this._pendingRequests.delete(reqKey);
        }
    },

    // ── Auth ──
    register(data) { return this.post('register', data); },
    login(data, password) {
        if (typeof data === 'string') {
            data = { identifier: data, password: password };
        }
        return this.post('login', data);
    },
    logout() { return this.get('logout'); },
    deleteAccount() { return this.get('delete_account'); },
    getVendorFollowers(vendorId = 0) { return this.get(`get_vendor_followers${vendorId ? '?vendor_id=' + vendorId : ''}`); },
    getVendorFollowing() { return this.get('get_vendor_following'); },
    getBankDetails() { return this.get('get_bank_details'); },
    sendOTP(target, type = 'verify', email = '', phone = '') { return this.post('send_otp', { target, type, email, phone }); },
    verifyOTP(target, code) { return this.post('verify_otp', { target, code }); },
    forgotPassword(target) { return this.post('forgot_password', { target }); },
    resetPassword(target, code, password) { return this.post('reset_password', { target, code, password }); },
    getSession() { return this.get('session'); },
    updateProfile(data) { return this.post('update_profile', data); },

    // ── Vendors ──
    getCategories() { return this.get('categories'); },
    getVendors(filters = {}) { return this.get('vendors', filters); },
    getVendorDetails(id, isCustomer = false) { return this.get('vendor_details', { id, is_customer: isCustomer ? 1 : 0 }); },
    registerVendor(data) { return this.post('register_vendor', data); },
    updateVendor(data) { return this.post('update_vendor', data); },

    // ── Bookings ──
    createBooking(data) { return this.post('book', data); },
    getBookings() { return this.get('bookings'); },
    updateBooking(data) { return this.post('update_booking', data); },

    // ── Reviews ──
    submitReview(data) { return this.post('review', data); },

    // ── Favorites ──
    toggleFavorite(vendorId) { return this.post('toggle_favorite', { vendor_id: vendorId }); },
    getFavorites() { return this.get('favorites'); },

    // ── Compare ──
    toggleCompare(vendorId) { return this.post('toggle_compare', { vendor_id: vendorId }); },
    getCompareList() { return this.get('compare_list'); },

    // ── Chat & Presence ──
    sendHeartbeat() { return this.get('heartbeat'); },
    getUserStatus(params = {}) { return this.get('get_user_status', params); },
    getChatInbox() { return this.get('chat_inbox'); },
    getUnreadChats() { return this.get('get_unread_chats'); },
    getChatHistory(vendorId) { return this.get('chat_history', { vendor_id: vendorId }); },
    sendMessage(vendorId, message, type = 'text') { return this.post('chat', { vendor_id: vendorId, message, type }); },

    // ── Events ──
    saveEvent(data) { return this.post('save_event', data); },
    getEvent() { return this.get('get_event'); },
    resetEvent() { return this.get('reset_event'); },

    // ── Tracker ──
    getTrackerTasks() { return this.get('tracker_tasks'); },
    addTask(data) { return this.post('add_task', data); },
    updateTask(data) { return this.post('update_task', data); },
    deleteTask(id) { return this.post('delete_task', { id }); },
    getTrackerStats() { return this.get('tracker_stats'); },

    // ── Direct Bookings & Invoicing ──
    recordPayment(data) { return Promise.resolve({ success: true, message: 'Direct booking model active.' }); },
    getPaymentHistory(bookingId) { return Promise.resolve([]); },
    initiatePaystackPayment(bookingId, type) { return Promise.resolve({ success: true, direct_booking: true }); },
    verifyPaystackPayment(reference, txId) { return Promise.resolve({ success: true }); },
    submitManualPayment(reference, txId) { return Promise.resolve({ success: true }); },
    getVendorWallet() { return Promise.resolve({ success: true, wallet: { escrow_balance: 0, available_balance: 0, pending_balance: 0, total_withdrawn: 0 }, transactions: [], withdrawals: [] }); },
    requestWithdrawal(amount) { return Promise.resolve({ success: true, message: 'Withdrawals not applicable.' }); },
    releaseEscrow(escrowId) { return Promise.resolve({ success: true }); },
    raiseDispute(bookingId, subject, description) { return this.post('raise_dispute', { booking_id: bookingId, subject, description }); },

    // ── Notifications ──
    getNotifications() { return this.get('notifications'); },
    markNotificationRead(id) { return this.post('mark_notification_read', { id: id || 0 }); },

    // ── Promotions & Advertising ──
    getAdCampaigns(vendorId) { return this.get('get_advertisements', { vendor_id: vendorId || '' }); },
    createAdCampaign(data) { return this.post('create_advertisement', data); },
    renewAdCampaign(data) { return this.post('renew_ad_campaign', data); },
    upgradeAdCampaign(data) { return this.post('upgrade_ad_campaign', data); },
    getAdAnalytics(vendorId) { return this.get('get_ad_analytics', { vendor_id: vendorId }); },
    getAdminCampaigns() { return this.get('get_admin_campaigns'); },
    recordAdClick(id) { return this.post('record_ad_click', { id }); },
    adminReviewCampaign(data) { return this.post('admin_review_campaign', data); },
    adminUpdateVendorPremium(data) { return this.post('admin_update_vendor_premium', data); },

    // ── Recommendations & Toggles ──
    getRecommendedVendors(categoryId, excludeId) { return this.get('get_recommended_vendors', { category: categoryId || '', exclude_id: excludeId || 0 }); },
    getTrustedVendors() { return this.get('get_trusted_vendors'); },
    getPopularVendors() { return this.get('get_popular_vendors'); },

    // ── Followers ──
    followVendor(vendorId) { return this.post('follow_vendor', { vendor_id: vendorId }); },
    getFollowingVendors() { return this.get('get_following_vendors'); },
};
