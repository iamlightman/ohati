window.DEFAULT_USER_AVATAR = "img/default-avatar.png";
window.DEFAULT_BUSINESS_COVER = "img/default-cover.jpg";
window.DEFAULT_VENDOR_COVER = "img/default-cover.jpg";
window.LOCAL_FALLBACK_SVG = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";

/**
 * Universal Authoritative Image URL Resolver for Cross-Platform WebViews (iOS, Android, Web)
 * Normalizes relative paths, local prefixes, and missing values into absolute HTTPS domain URLs.
 */
window.resolveImageUrl = function(url, typeOrFallback = 'avatar') {
    let fallback = window.DEFAULT_USER_AVATAR || 'img/default-avatar.png';
    if (typeOrFallback === 'cover' || typeOrFallback === 'vendor_cover') {
        fallback = window.DEFAULT_VENDOR_COVER || 'img/default-cover.jpg';
    } else if (typeof typeOrFallback === 'string' && (typeOrFallback.startsWith('http') || typeOrFallback.startsWith('data:'))) {
        fallback = typeOrFallback;
    }

    if (!url || typeof url !== 'string' || !url.trim() || url.trim() === 'null' || url.trim() === 'undefined') {
        return fallback;
    }
    
    let trimmed = url.trim();

    if (trimmed.startsWith('data:') || trimmed.startsWith('blob:')) return trimmed;

    // Handle full HTTP / HTTPS URLs directly
    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
        if (trimmed.startsWith('http://') && !trimmed.includes('localhost') && !trimmed.includes('127.0.0.1')) {
            trimmed = 'https://' + trimmed.substring(7);
        }
        try {
            return encodeURI(decodeURI(trimmed));
        } catch (e) {
            return encodeURI(trimmed);
        }
    }

    // Handle Capacitor localhost URL
    if (trimmed.startsWith('capacitor://localhost/')) {
        trimmed = trimmed.replace('capacitor://localhost/', '');
    }

    let domainPrefix = '';
    let appDir = '';
    if (window.location && window.location.protocol && window.location.protocol.startsWith('http')) {
        const rawPathName = window.location.pathname || '';
        let pathName = rawPathName;
        try { pathName = decodeURIComponent(rawPathName); } catch (e) {}
        appDir = pathName.substring(0, pathName.lastIndexOf('/')).replace(/\/$/, '');
        domainPrefix = window.location.origin + appDir;
    } else if (typeof window.getOhatiApiBaseUrl === 'function') {
        const apiBase = window.getOhatiApiBaseUrl();
        if (apiBase && apiBase.includes('://')) {
            domainPrefix = apiBase.split('/api.php')[0].replace(/\/$/, '');
        }
    }
    
    if (!domainPrefix || domainPrefix.includes('capacitor://') || domainPrefix.includes('file://')) {
        domainPrefix = 'https://ohati.com';
    }

    let cleanPath = trimmed.startsWith('/') ? trimmed.substring(1) : trimmed;
    try { cleanPath = decodeURIComponent(cleanPath); } catch (e) {}

    // Strip accidental appDir duplication if cleanPath starts with it
    if (appDir && appDir !== '') {
        const cleanAppDir = appDir.replace(/^\//, '');
        if (cleanPath.toLowerCase().startsWith(cleanAppDir.toLowerCase() + '/')) {
            cleanPath = cleanPath.substring(cleanAppDir.length + 1);
        }
    }

    const fullUrl = `${domainPrefix}/${cleanPath}`;
    try {
        return encodeURI(decodeURI(fullUrl));
    } catch (e) {
        return encodeURI(fullUrl);
    }
};

/**
 * Universal Image Error Fallback Handler
 * Prevents broken image icons and infinite loops across Web, iOS, and Android
 */
window.handleImageError = function(imgEl, type = 'avatar') {
    if (!imgEl) return;
    imgEl.onerror = null; // Prevent infinite fallback loops
    let fallback = (type === 'cover' || type === 'vendor_cover') ? window.DEFAULT_VENDOR_COVER : window.DEFAULT_USER_AVATAR;
    imgEl.src = fallback;
    
    // Safety net: Use SVG data URI if default network JPG fails
    imgEl.onerror = function() {
        this.onerror = null;
        this.src = window.LOCAL_FALLBACK_SVG;
    };
};

/**
 * Normalizes persisted localStorage user session avatar URLs for iOS compatibility
 */
window.normalizeUserSession = function() {
    try {
        const raw = localStorage.getItem('ohati_user_session');
        if (!raw) return;
        const u = JSON.parse(raw);
        if (u && u.avatar) {
            const resolved = window.resolveImageUrl(u.avatar);
            if (resolved !== u.avatar) {
                u.avatar = resolved;
                localStorage.setItem('ohati_user_session', JSON.stringify(u));
            }
            if (typeof window.state !== 'undefined' && window.state.user) {
                window.state.user.avatar = resolved;
            }
        }
    } catch (e) {}
};

/** Format number to compact form (1.2K, 3.4M) */
function formatCompact(n) {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
    return n.toString();
}

/** Format currency with GHS */
function formatCurrency(amount) {
    return 'GH₵ ' + parseFloat(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Robustly parse database date string into local JS Date object */
function parseAppDate(dateStr) {
    if (!dateStr) return new Date();
    if (dateStr instanceof Date) return dateStr;
    if (typeof dateStr === 'number') return new Date(dateStr);

    let str = String(dateStr).trim();
    if (!str) return new Date();

    if (str.includes('Z') || str.includes('+') || (str.includes('T') && str.includes('-') && str.length > 19)) {
        const d = new Date(str);
        if (!isNaN(d.getTime())) return d;
    }

    const match = str.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?/);
    if (match) {
        const [, year, month, day, hour, minute, second] = match;
        const iso = `${year}-${month}-${day}T${hour}:${minute}:${second || '00'}`;
        const d = new Date(iso);
        if (!isNaN(d.getTime())) return d;

        return new Date(
            parseInt(year, 10),
            parseInt(month, 10) - 1,
            parseInt(day, 10),
            parseInt(hour, 10),
            parseInt(minute, 10),
            parseInt(second || '0', 10)
        );
    }

    const fallback = new Date(str);
    return isNaN(fallback.getTime()) ? new Date() : fallback;
}

/** Format friendly date: "Dec 12, 2027" */
function formatFriendlyDate(dateStr) {
    if (!dateStr) return 'Not set';
    const d = parseAppDate(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

/** Format friendly date and time: "Dec 12, 2027 at 10:35 PM" */
function formatFriendlyDateTime(dateStr) {
    if (!dateStr) return 'Not set';
    const d = parseAppDate(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) + ' at ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/** Format full date and time for chat timestamps: "Jul 29, 2026, 10:37 PM" or "Today, 10:37 PM" */
function formatChatDateTime(dateStr) {
    if (!dateStr) return '';
    const d = parseAppDate(dateStr);
    if (isNaN(d.getTime())) return '';

    const now = new Date();
    const isToday = d.toDateString() === now.toDateString();
    const timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });

    if (isToday) {
        return `Today, ${timeStr}`;
    }

    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return `Yesterday, ${timeStr}`;
    }

    const dateStrFormatted = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    return `${dateStrFormatted}, ${timeStr}`;
}

/** Centralized online presence evaluator: returns true ONLY if real heartbeat / last_active presence is active within 120s */
window.isUserOnline = function(item) {
    if (!item) return false;
    if (typeof item.is_online === 'boolean') return item.is_online;
    if (item.last_active) {
        const d = parseAppDate(item.last_active);
        if (!isNaN(d.getTime())) {
            const diffSec = Math.floor((Date.now() - d.getTime()) / 1000);
            return diffSec >= 0 && diffSec <= 120;
        }
    }
    return false;
};

/** Format real-time clock timestamp across all screens: "Just now", "10 mins ago", "3 hours ago", "Yesterday", or "Jul 29, 2026" */
function formatRelativeTime(dateStr) {
    if (!dateStr) return '';
    const d = parseAppDate(dateStr);
    if (isNaN(d.getTime())) return '';

    const now = new Date();
    const diffSec = Math.floor((now.getTime() - d.getTime()) / 1000);

    if (diffSec < 60 && diffSec >= -10) {
        return 'Just now';
    }

    if (diffSec < 3600 && diffSec >= 60) {
        const mins = Math.floor(diffSec / 60);
        return `${mins} ${mins === 1 ? 'min' : 'mins'} ago`;
    }

    const isToday = d.toDateString() === now.toDateString();
    if (isToday) {
        const hours = Math.floor(diffSec / 3600);
        if (hours > 0 && hours < 24) {
            return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
        }
        const timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
        return `Today, ${timeStr}`;
    }

    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return 'Yesterday';
    }

    const isSameYear = d.getFullYear() === now.getFullYear();
    if (isSameYear) {
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/** Global 30-second ticker to keep live relative timestamps fresh on screen */
if (typeof window.liveTimeTicker === 'undefined') {
    window.liveTimeTicker = null;
}
function initLiveTimeTicker() {
    if (window.liveTimeTicker) return;
    window.liveTimeTicker = setInterval(() => {
        const els = document.querySelectorAll('.live-relative-time');
        els.forEach(el => {
            const stamp = el.getAttribute('data-timestamp');
            if (stamp) {
                el.textContent = formatRelativeTime(stamp);
            }
        });
    }, 30000);
}
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', initLiveTimeTicker);
}

/** Scroll element to bottom */
function scrollToBottom(elementId) {
    const el = document.getElementById(elementId);
    if (el) setTimeout(() => { el.scrollTop = el.scrollHeight; }, 50);
}

/** Debounce function */
function debounce(fn, delay = 300) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

/** Generate star rating HTML */
function starsHTML(rating, size = '0.7rem') {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<i class="${i <= rating ? 'fa-solid' : 'fa-regular'} fa-star" style="font-size:${size};color:var(--accent);"></i>`;
    }
    return html;
}

/** Password strength checker */
function getPasswordStrength(pw) {
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    const classes = ['', 'weak', 'fair', 'good', 'strong', 'strong'];
    return { score, label: labels[score] || '', className: classes[score] || '' };
}

/** Safe HTML escape */
function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/** Copy to clipboard */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showPushNotification('Copied', 'Text copied to clipboard.');
    } catch (e) {
        console.error('Copy failed:', e);
    }
}

/** Greeting based on time */
function getTimeGreeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
}

/** Share Vendor Profile */
function shareVendorProfile(vendor, event) {
    if (event) event.stopPropagation();
    
    const badgeText = (vendor.verification_badge === 'gold') ? 'Gold Verified' : ((vendor.verification_badge === 'blue') ? 'ID Verified' : 'Verified');
    const ratingText = vendor.rating ? `${vendor.rating} ★` : '5.0 ★';
    const reviewsCount = vendor.reviews_count || 0;
    const locationText = vendor.location || 'Accra, Ghana';
    
    const text = `Check out ${badgeText} ${vendor.name} on Ohati. Rated ${ratingText} with ${reviewsCount} reviews. Based in ${locationText} and available nationwide.`;
    const url = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/detail.php') + '?id=' + vendor.id;
    
    if (navigator.share) {
        navigator.share({
            title: vendor.name,
            text: text,
            url: url
        }).catch(err => {
            openDesktopShareModal(vendor.name, text, url);
        });
    } else {
        openDesktopShareModal(vendor.name, text, url);
    }
}

/** Desktop fallback share modal */
function openDesktopShareModal(vendorName, text, url) {
    const existing = document.getElementById('desktop-share-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'desktop-share-modal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.background = 'rgba(0,0,0,0.5)';
    modal.style.backdropFilter = 'blur(10px)';
    modal.style.zIndex = '20000';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };

    modal.innerHTML = `
        <div style="background:var(--white); padding:24px; border-radius:16px; width:340px; box-shadow:var(--shadow-lg); text-align:center; position:relative; border: 1px solid var(--gray-100);">
            <button onclick="document.getElementById('desktop-share-modal').remove()" style="position:absolute; top:12px; right:12px; border:none; background:none; font-size:1.2rem; color:var(--gray-400); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            <h3 style="margin-top:0; font-size:1.1rem; color:var(--gray-800); margin-bottom:8px; font-family:'Plus Jakarta Sans',sans-serif;">Share Profile</h3>
            <p style="font-size:0.8rem; color:var(--gray-500); margin-bottom:20px; font-family:'Plus Jakarta Sans',sans-serif;">Share <strong>${escapeHTML(vendorName)}</strong> with your contacts.</p>
            
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin-bottom:20px; font-family:'Plus Jakarta Sans',sans-serif;">
                <a href="https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}" target="_blank" style="text-decoration:none; color:#25D366; display:flex; flex-direction:column; align-items:center; gap:4px; font-size:0.7rem; font-weight:700;">
                    <span style="width:44px; height:44px; border-radius:50%; background:#25D366; color:white; display:flex; align-items:center; justify-content:center; font-size:1.3rem;"><i class="fa-brands fa-whatsapp"></i></span>
                    WhatsApp
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}" target="_blank" style="text-decoration:none; color:#1877F2; display:flex; flex-direction:column; align-items:center; gap:4px; font-size:0.7rem; font-weight:700;">
                    <span style="width:44px; height:44px; border-radius:50%; background:#1877F2; color:white; display:flex; align-items:center; justify-content:center; font-size:1.3rem;"><i class="fa-brands fa-facebook"></i></span>
                    Facebook
                </a>
                <a href="https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}" target="_blank" style="text-decoration:none; color:#1DA1F2; display:flex; flex-direction:column; align-items:center; gap:4px; font-size:0.7rem; font-weight:700;">
                    <span style="width:44px; height:44px; border-radius:50%; background:#1DA1F2; color:white; display:flex; align-items:center; justify-content:center; font-size:1.3rem;"><i class="fa-brands fa-x-twitter"></i></span>
                    X / Twitter
                </a>
                <a href="mailto:?subject=${encodeURIComponent(vendorName + ' on Ohati')}&body=${encodeURIComponent(text + '\n' + url)}" style="text-decoration:none; color:var(--gray-600); display:flex; flex-direction:column; align-items:center; gap:4px; font-size:0.7rem; font-weight:700;">
                    <span style="width:44px; height:44px; border-radius:50%; background:var(--gray-200); color:var(--gray-700); display:flex; align-items:center; justify-content:center; font-size:1.3rem;"><i class="fa-solid fa-envelope"></i></span>
                    Email
                </a>
            </div>

            <div style="border-top:1px solid var(--gray-100); padding-top:16px;">
                <button onclick="copyToClipboard('${url}'); showPushNotification('Copied', 'Vendor profile link copied successfully.'); document.getElementById('desktop-share-modal').remove();" class="btn btn-primary btn-full" style="display:flex; align-items:center; justify-content:center; gap:8px; font-weight:700;">
                    <i class="fa-solid fa-link"></i> Copy Profile Link
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

/** Universal Audio Stream Helper with clear permission error diagnostics & multi-tier fail-safes */
async function getUniversalAudioStream() {
    return await getUniversalMediaStream(false);
}

/** Universal Audio/Video Stream Helper supporting Web & Mobile App WebRTC calls and voice recording */
async function getUniversalMediaStream(videoEnabled = false) {
    const isUnsecureHttp = (location.protocol === 'http:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1' && location.hostname !== '::1');

    if (isUnsecureHttp) {
        const msg = "🔒 Web Browser Security Policy: Browsers (Chrome, Edge, Safari) strictly require an HTTPS (https://) connection to use the microphone. On plain HTTP (http://), microphone access is blocked even if site settings are set to Allow.";
        if (typeof showPushNotification === 'function') {
            showPushNotification("HTTPS Required for Microphone", msg);
        }
        throw new Error(msg);
    }

    // 1. Primary modern getUserMedia API
    if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
        const primaryConstraints = {
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            }
        };
        if (videoEnabled) {
            primaryConstraints.video = { facingMode: 'user' };
        }

        try {
            return await navigator.mediaDevices.getUserMedia(primaryConstraints);
        } catch (e1) {
            console.warn("Tier 1 getUserMedia with constraints failed, trying basic audio:", e1);
            const basicConstraints = { audio: true };
            if (videoEnabled) basicConstraints.video = true;

            try {
                return await navigator.mediaDevices.getUserMedia(basicConstraints);
            } catch (e2) {
                console.error("Tier 2 basic getUserMedia failed:", e2);

                const errStr = String(e2.message || e2.name || e2).toLowerCase();
                if (errStr.includes('insecure') || errStr.includes('secure context') || errStr.includes('origin')) {
                    throw new Error("Web browsers require HTTPS for microphone access. Please open the website using https://...");
                }

                if (e2.name === 'NotAllowedError' || e2.name === 'PermissionDeniedError') {
                    // Check if site permission is granted via Permissions API
                    let isSiteGranted = false;
                    try {
                        if (navigator.permissions && navigator.permissions.query) {
                            const pStatus = await navigator.permissions.query({ name: 'microphone' });
                            if (pStatus && pStatus.state === 'granted') {
                                isSiteGranted = true;
                            }
                        }
                    } catch (pErr) {}

                    if (isSiteGranted) {
                        showOsMicrophoneUnblockGuide();
                        throw new Error("Site setting is Allowed, but OS Privacy settings or another running app (Zoom/Teams) is blocking microphone hardware access.");
                    } else {
                        showMicrophoneUnblockGuide();
                        throw new Error("Microphone permission was denied by browser. Click the lock 🔒 icon in address bar to set Microphone to Allow.");
                    }
                }
                if (e2.name === 'NotFoundError' || e2.name === 'DevicesNotFoundError') {
                    throw new Error("No microphone device was detected on your hardware.");
                }
                if (e2.name === 'NotReadableError' || e2.name === 'TrackStartError') {
                    showOsMicrophoneUnblockGuide();
                    throw new Error("Microphone is currently in use by another application (Zoom, Teams, etc.).");
                }
                throw new Error("Microphone access failed: " + (e2.message || e2.name || 'Permission denied.'));
            }
        }
    }

    // 2. Legacy getUserMedia fallback for older mobile WebViews / browsers
    const legacyGetUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
    if (legacyGetUserMedia) {
        return new Promise((resolve, reject) => {
            legacyGetUserMedia.call(
                navigator,
                { audio: true, video: !!videoEnabled },
                stream => resolve(stream),
                err => {
                    if (err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError' || err === 'PERMISSION_DENIED')) {
                        showMicrophoneUnblockGuide();
                        reject(new Error("Microphone permission denied in browser settings."));
                    } else {
                        reject(new Error("Microphone access failed: " + (err ? (err.message || err) : 'Permission denied')));
                    }
                }
            );
        });
    }

    // 3. Security context error if API is missing due to HTTP connection
    if (location.protocol === 'http:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        throw new Error("Web browsers require HTTPS for microphone access. Please open the website using https://...");
    }

    throw new Error("Microphone API is not supported on this browser connection.");
}

/** Interactive On-Screen Microphone Permission Recovery Modal for Browser Settings */
function showMicrophoneUnblockGuide() {
    let modal = document.getElementById('micUnblockModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'micUnblockModal';
        modal.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:99999; display:flex; align-items:center; justify-content:center; padding:20px; font-family:-apple-system,BlinkMacSystemFont,sans-serif;';
        modal.innerHTML = `
            <div style="background:#ffffff; border-radius:20px; max-width:440px; width:100%; padding:28px 24px; text-align:center; box-shadow:0 25px 50px rgba(0,0,0,0.3); border:1px solid #E5E7EB;">
                <div style="width:64px; height:64px; border-radius:50%; background:#FEE2E2; color:#EF4444; display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 16px auto;">
                    <i class="fa-solid fa-microphone-slash"></i>
                </div>
                <h3 style="margin:0 0 8px 0; font-size:1.25rem; font-weight:800; color:#111827;">Microphone Access Blocked</h3>
                <p style="font-size:0.85rem; color:#4B5563; margin-bottom:16px; line-height:1.5;">
                    Your web browser has blocked microphone access for this website. Follow these 3 steps to unblock:
                </p>
                <div style="text-align:left; background:#F9FAFB; border:1px solid #E5E7EB; padding:14px 16px; border-radius:12px; font-size:0.82rem; color:#374151; margin-bottom:20px; line-height:1.6;">
                    <strong>1. Address Bar Lock:</strong> Click the 🔒 <b>Padlock / Settings</b> icon next to the URL at the top of your browser.<br>
                    <strong>2. Allow Microphone:</strong> Switch <b>Microphone</b> to <b>"Allow"</b> or <b>"Reset Permissions"</b>.<br>
                    <strong>3. Refresh Page:</strong> Click the button below to reload the page and begin calling or recording.
                </div>
                <div style="display:flex; gap:10px;">
                    <button onclick="location.reload()" style="flex:1; padding:12px; background:#E05A47; color:#fff; border:none; border-radius:12px; font-weight:700; cursor:pointer; font-size:0.9rem;">
                        <i class="fa-solid fa-rotate-right"></i> Reload Page Now
                    </button>
                    <button onclick="document.getElementById('micUnblockModal').remove()" style="padding:12px 16px; background:#E5E7EB; color:#374151; border:none; border-radius:12px; font-weight:700; cursor:pointer; font-size:0.9rem;">
                        Dismiss
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
}

/** Interactive On-Screen OS System Privacy & Hardware Lock Guide */
function showOsMicrophoneUnblockGuide() {
    let modal = document.getElementById('micOsUnblockModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'micOsUnblockModal';
        modal.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:99999; display:flex; align-items:center; justify-content:center; padding:20px; font-family:-apple-system,BlinkMacSystemFont,sans-serif;';
        modal.innerHTML = `
            <div style="background:#ffffff; border-radius:20px; max-width:460px; width:100%; padding:28px 24px; text-align:center; box-shadow:0 25px 50px rgba(0,0,0,0.3); border:1px solid #E5E7EB;">
                <div style="width:64px; height:64px; border-radius:50%; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 16px auto;">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <h3 style="margin:0 0 8px 0; font-size:1.25rem; font-weight:800; color:#111827;">System Hardware Access Blocked</h3>
                <p style="font-size:0.85rem; color:#4B5563; margin-bottom:16px; line-height:1.5;">
                    Your website permission is set to Allow, but your <strong>Computer / Phone OS Privacy Settings</strong> or another active app (Zoom, Teams) is blocking microphone access.
                </p>
                <div style="text-align:left; background:#F9FAFB; border:1px solid #E5E7EB; padding:14px 16px; border-radius:12px; font-size:0.82rem; color:#374151; margin-bottom:20px; line-height:1.6;">
                    <strong>Windows 10/11 Fix:</strong><br>
                    1. Open <b>Start Menu</b> &rarr; <b>Settings</b> &rarr; <b>Privacy & Security</b> &rarr; <b>Microphone</b>.<br>
                    2. Turn ON <b>"Microphone access"</b> and <b>"Let desktop apps / browsers access microphone"</b>.<br>
                    3. Close any background call apps (Zoom, Teams, Skype) and refresh this page.
                </div>
                <div style="display:flex; gap:10px;">
                    <button onclick="location.reload()" style="flex:1; padding:12px; background:#E05A47; color:#fff; border:none; border-radius:12px; font-weight:700; cursor:pointer; font-size:0.9rem;">
                        <i class="fa-solid fa-rotate-right"></i> Refresh Page
                    </button>
                    <button onclick="document.getElementById('micOsUnblockModal').remove()" style="padding:12px 16px; background:#E5E7EB; color:#374151; border:none; border-radius:12px; font-weight:700; cursor:pointer; font-size:0.9rem;">
                        Dismiss
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
}

/** Universal Device Back Button & Navigation Manager (Web + Mobile App) */
window.OhatiNavManager = {
    lastBackPressTime: 0,
    isPaymentProcessing: false,

    init() {
        // Handle Capacitor Native Android Back Button
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.App) {
            try {
                window.Capacitor.Plugins.App.addListener('backButton', () => {
                    this.handleBackPress();
                });
            } catch (e) {}
        }
        // Native Cordova/Capacitor document event listener fallback
        document.addEventListener('backbutton', (e) => {
            if (e) e.preventDefault();
            this.handleBackPress();
        }, false);

        // Handle Browser Back / Forward buttons (pushState/popstate)
        window.addEventListener('popstate', (event) => {
            let targetScreen = event.state && event.state.screenId ? event.state.screenId : null;
            let targetParams = event.state && event.state.params ? event.state.params : {};

            // If state payload is missing/null, derive route from window.location
            if (!targetScreen) {
                const path = decodeURIComponent(window.location.pathname.split('/').pop() || '');
                const search = new URLSearchParams(window.location.search);
                if (path === 'planner.php') targetScreen = 'event';
                else if (path === 'search.php') targetScreen = 'search';
                else if (path === 'detail.php') {
                    targetScreen = 'detail';
                    const vid = parseInt(search.get('id') || search.get('vendor_id'));
                    if (vid) targetParams = { id: vid };
                }
                else if (path === 'chat.php') {
                    targetScreen = 'chat';
                    const vid = parseInt(search.get('vendor_id') || search.get('id'));
                    if (vid) targetParams = { vendor_id: vid };
                }
                else if (path === 'bookings.php') {
                    targetScreen = 'bookings';
                    const bId = parseInt(search.get('id'));
                    if (bId) targetParams = { id: bId };
                }
                else if (path === 'favorites.php') targetScreen = 'favorites';
                else if (path === 'compare.php') targetScreen = 'compare';
                else if (path === 'notifications.php') targetScreen = 'notifications';
                else if (path === 'profile.php') targetScreen = 'profile';
                else if (path === 'vendor-dash.php') targetScreen = 'vendor-dash';
                else if (path === 'promotions.php') targetScreen = 'vendor-ads';
                else if (path === 'help.php') targetScreen = 'help';
                else if (path === 'about.php') targetScreen = 'about';
                else if (path === 'report-issue.php') targetScreen = 'report-issue';
                else if (path === 'blog.php') {
                    const bId = parseInt(search.get('id'));
                    const bSlug = search.get('slug');
                    if (bId || bSlug) {
                        targetScreen = 'blog-detail';
                        targetParams = { id: bId, slug: bSlug };
                    } else {
                        targetScreen = 'blog';
                    }
                }
                else targetScreen = 'home';
            }

            this.handleBackPress(true, targetScreen, targetParams);
        });
    },

    setPaymentProcessing(processing = false) {
        this.isPaymentProcessing = processing;
    },

    handleBackPress(fromPopState = false, targetScreen = null, targetParams = {}) {
        // 1. Payment Processing Guard
        if (this.isPaymentProcessing) {
            showPushNotification("Transaction Processing", "Your payment is currently being processed. Please wait to prevent double charges.");
            if (fromPopState && typeof state !== 'undefined' && state.currentScreen) {
                history.pushState({ screenId: state.currentScreen }, '', window.location.href);
            }
            return false;
        }

        // 2. Active Call Guard (Prevent accidental call drop)
        if (window.OhatiCalling && window.OhatiCalling.currentCall) {
            if (confirm("An active call is in progress. Are you sure you want to end the call?")) {
                window.OhatiCalling.endCall('cancelled');
            } else {
                if (fromPopState && typeof state !== 'undefined' && state.currentScreen) {
                    history.pushState({ screenId: state.currentScreen }, '', window.location.href);
                }
                return false;
            }
        }

        // 3. Open Custom Overlay Modals, Global Modal Root & Lightbox
        const globalRoot = document.getElementById('ohati-global-modal-root');
        if (globalRoot && globalRoot.classList.contains('open')) {
            if (typeof closeConfirmModal === 'function') closeConfirmModal(false);
            if (typeof closeBlogReportModal === 'function') closeBlogReportModal();
            if (typeof closeBlogBlockModal === 'function') closeBlogBlockModal();
            if (typeof closeChatReportModal === 'function') closeChatReportModal();
            if (typeof closeChatBlockModal === 'function') closeChatBlockModal();
            if (fromPopState && typeof state !== 'undefined' && state.currentScreen) {
                history.pushState({ screenId: state.currentScreen }, '', window.location.href);
            }
            return true;
        }

        const customModal = document.querySelector('#account-deletion-custom-modal, #account-deleted-pro-modal, #voiceCallModal, .modal-overlay.open, .blog-modal-backdrop, #lightbox');
        if (customModal) {
            if (typeof closeAccountDeletionModal === 'function') closeAccountDeletionModal();
            if (typeof closeAccountDeletedProModal === 'function') closeAccountDeletedProModal();
            if (typeof closeDocModal === 'function') closeDocModal();
            if (typeof closeBlogReportModal === 'function') closeBlogReportModal();
            if (typeof closeBlogBlockModal === 'function') closeBlogBlockModal();
            if (typeof closeChatReportModal === 'function') closeChatReportModal();
            if (typeof closeChatBlockModal === 'function') closeChatBlockModal();
            if (typeof closeModal === 'function') closeModal();
            if (customModal.parentNode) {
                try { customModal.remove(); } catch(e) { customModal.style.display = 'none'; }
            }
            if (fromPopState && typeof state !== 'undefined' && state.currentScreen) {
                history.pushState({ screenId: state.currentScreen }, '', window.location.href);
            }
            return true;
        }

        // 4. Open Application Modals / Overlays
        const openModals = document.querySelectorAll('#auth-modal, .auth-modal, .modal-backdrop, #statusActionModal, #vendorActionModal, #bookingDetailsModal, #userDetailsModal, #vendorDetailsModal, #promotionDetailsModal, #issueDetailsModal, #reviewDetailsModal, #trashDetailsModal, #kycDetailsModal');
        let closedModal = false;
        openModals.forEach(m => {
            if (m.style.display !== 'none' && m.style.display !== '') {
                m.style.display = 'none';
                closedModal = true;
            }
        });
        if (closedModal) {
            if (typeof closeModal === 'function') closeModal();
            if (fromPopState && typeof state !== 'undefined' && state.currentScreen) {
                history.pushState({ screenId: state.currentScreen }, '', window.location.href);
            }
            return true;
        }


        // 5. Open Search Filter Overlays / Dropdowns
        const openDropdowns = document.querySelectorAll('.dropdown-menu.show, .filter-overlay.active');
        if (openDropdowns.length > 0) {
            openDropdowns.forEach(d => d.classList.remove('show', 'active'));
            if (fromPopState && typeof state !== 'undefined' && state.currentScreen) {
                history.pushState({ screenId: state.currentScreen }, '', window.location.href);
            }
            return true;
        }

        // 6. Unsaved Form Changes Guard
        if (typeof state !== 'undefined' && state.currentScreen === 'profile-edit' && window.hasUnsavedProfileEdits) {
            if (!confirm("You have unsaved changes on your profile. Are you sure you want to discard them?")) {
                if (fromPopState && state.currentScreen) {
                    history.pushState({ screenId: state.currentScreen }, '', window.location.href);
                }
                return false;
            } else {
                window.hasUnsavedProfileEdits = false;
            }
        }

        // 7. Chat Conversation Screen (Save draft)
        if (typeof state !== 'undefined' && state.currentScreen === 'chat') {
            const chatInput = document.getElementById('chat-input-field');
            if (chatInput && chatInput.value.trim() !== '') {
                sessionStorage.setItem('chat_draft_' + (state.activeChatVendorId || 'default'), chatInput.value);
            }
        }

        // 8. If fromPopState is true, navigate directly to targetScreen
        if (fromPopState && targetScreen && typeof navigateTo === 'function') {
            navigateTo(targetScreen, targetParams || {}, { fromPopState: true, force: true });
            return true;
        }

        // 9. Root Screens (Home or Vendor Dashboard) -> Double Press Exit App
        const currentScreen = typeof state !== 'undefined' ? state.currentScreen : 'home';
        const isRoot = (currentScreen === 'home' || currentScreen === 'vendor-dash' || !currentScreen);
        if (isRoot) {
            const now = Date.now();
            if (now - this.lastBackPressTime < 2000) {
                if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.App) {
                    window.Capacitor.Plugins.App.exitApp();
                }
            } else {
                this.lastBackPressTime = now;
                showPushNotification("Exit Ohati", "Press back again to exit the application.");
            }
            return true;
        }

        // 10. Default Navigation Stack Back
        if (typeof navigateBack === 'function') {
           // Native platform back button default behavior
           navigateBack();
        }
        return false;
    }
};

// ── Cover Photo Cropper Modal ────────────────────────────────────────────────
window.openCoverCropperModal = function(fileOrDataUrl, onCropComplete) {
    const processImage = function(srcUrl) {
        let zoom = 1.0;
        let offsetX = 0;
        let offsetY = 0;
        let isDragging = false;
        let startX = 0;
        let startY = 0;

        const modalDiv = document.createElement('div');
        modalDiv.id = 'coverCropperModal';
        modalDiv.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(8,23,41,0.92); z-index:9999999; display:flex; align-items:center; justify-content:center; padding:16px; box-sizing:border-box; backdrop-filter:blur(6px);';
        
        modalDiv.innerHTML = `
            <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.15); border-radius:20px; width:100%; max-width:580px; padding:24px; box-shadow:0 24px 60px rgba(0,0,0,0.8); color:#FFF; text-align:center;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.25rem; font-weight:800; margin:0; color:#FFF;">
                        <i class="fa-solid fa-crop-simple" style="color:var(--accent, #F2A735); margin-right:8px;"></i> Crop & Position Cover Banner
                    </h3>
                    <button type="button" onclick="closeCoverCropperModal()" style="background:none; border:none; color:#94A3B8; font-size:1.2rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <p style="font-size:0.8rem; color:#94A3B8; margin:0 0 16px 0;">Slide towards <strong style="color:var(--accent, #F2A735);">+</strong> to zoom in. Drag image to reposition inside cover frame.</p>

                <div id="crop-viewport" style="position:relative; width:100%; height:220px; border-radius:14px; overflow:hidden; border:2px solid var(--accent, #F2A735); background:#000; cursor:grab; margin-bottom:14px; user-select:none; display:flex; align-items:center; justify-content:center;">
                    <img id="crop-source-img" src="${srcUrl}" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%) scale(1); max-width:none; max-height:none; opacity:0; transition:opacity 0.15s; pointer-events:none;">
                    <div style="position:absolute; top:10px; left:10px; padding:4px 10px; background:rgba(11,31,58,0.85); border-radius:20px; font-size:0.7rem; font-weight:800; color:#F2A735; border:1px solid rgba(242,167,53,0.3); pointer-events:none;">
                        <i class="fa-solid fa-eye"></i> Cover Banner Frame
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:12px; margin-bottom:18px; background:rgba(255,255,255,0.04); padding:10px 14px; border-radius:12px;">
                    <span style="font-size:0.85rem; font-weight:800; color:#94A3B8;">-</span>
                    <input type="range" id="crop-zoom-slider" min="0.05" max="3.0" step="0.01" value="0.05" style="flex:1; cursor:pointer;">
                    <span style="font-size:1.1rem; font-weight:800; color:var(--accent, #F2A735);">+</span>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn btn-outline btn-full" onclick="closeCoverCropperModal()" style="border-color:rgba(255,255,255,0.2); color:#FFF;">Cancel</button>
                    <button type="button" class="btn btn-primary btn-full" id="btn-do-crop" style="font-weight:800;">
                        <i class="fa-solid fa-check" style="margin-right:6px;"></i> Crop & Apply Cover
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modalDiv);

        const imgEl = document.getElementById('crop-source-img');
        const viewport = document.getElementById('crop-viewport');
        const zoomSlider = document.getElementById('crop-zoom-slider');

        function updateTransform() {
            if (imgEl) {
                imgEl.style.transform = `translate(calc(-50% + ${offsetX}px), calc(-50% + ${offsetY}px)) scale(${zoom})`;
            }
        }

        const loadedMeasurer = new Image();
        loadedMeasurer.onload = function() {
            const nw = loadedMeasurer.naturalWidth || 800;
            const nh = loadedMeasurer.naturalHeight || 600;
            const vpW = viewport.clientWidth || 530;
            const vpH = viewport.clientHeight || 220;

            imgEl.style.width = nw + 'px';
            imgEl.style.height = nh + 'px';

            const scaleW = vpW / nw;
            const scaleH = vpH / nh;
            const containScale = Math.min(scaleW, scaleH);
            const coverScale = Math.max(scaleW, scaleH);

            const minScale = Math.round(containScale * 100) / 100 || 0.05;
            const maxScale = Math.round(Math.max(coverScale * 3, containScale * 4) * 100) / 100 || 3.0;

            zoom = minScale;
            offsetX = 0;
            offsetY = 0;

            if (zoomSlider) {
                zoomSlider.min = minScale;
                zoomSlider.max = maxScale;
                zoomSlider.step = (maxScale - minScale) / 100;
                zoomSlider.value = minScale;
            }

            imgEl.style.opacity = '1';
            updateTransform();
        };
        loadedMeasurer.src = srcUrl;

        if (zoomSlider) {
            zoomSlider.addEventListener('input', function() {
                zoom = parseFloat(this.value);
                updateTransform();
            });
        }

        if (viewport) {
            viewport.addEventListener('mousedown', function(e) {
                isDragging = true;
                startX = e.clientX - offsetX;
                startY = e.clientY - offsetY;
                viewport.style.cursor = 'grabbing';
            });

            window.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                offsetX = e.clientX - startX;
                offsetY = e.clientY - startY;
                updateTransform();
            });

            window.addEventListener('mouseup', function() {
                isDragging = false;
                if (viewport) viewport.style.cursor = 'grab';
            });

            viewport.addEventListener('touchstart', function(e) {
                if (e.touches.length === 1) {
                    isDragging = true;
                    startX = e.touches[0].clientX - offsetX;
                    startY = e.touches[0].clientY - offsetY;
                }
            });

            viewport.addEventListener('touchmove', function(e) {
                if (isDragging && e.touches.length === 1) {
                    offsetX = e.touches[0].clientX - startX;
                    offsetY = e.touches[0].clientY - startY;
                    updateTransform();
                }
            });

            viewport.addEventListener('touchend', function() {
                isDragging = false;
            });
        }

        const cropBtn = document.getElementById('btn-do-crop');
        if (cropBtn) {
            cropBtn.onclick = function() {
                const canvas = document.createElement('canvas');
                canvas.width = 1200;
                canvas.height = 550;
                const ctx = canvas.getContext('2d');

                const loadedImg = new Image();
                loadedImg.crossOrigin = "anonymous";
                loadedImg.onload = function() {
                    ctx.fillStyle = '#0F1923';
                    ctx.fillRect(0, 0, 1200, 550);

                    const vpRect = viewport.getBoundingClientRect();
                    const imgRect = imgEl.getBoundingClientRect();

                    const scaleFactor = 1200 / (vpRect.width || 530);

                    const drawW = imgRect.width * scaleFactor;
                    const drawH = imgRect.height * scaleFactor;

                    const dx = (imgRect.left + (imgRect.width / 2)) - (vpRect.left + (vpRect.width / 2));
                    const dy = (imgRect.top + (imgRect.height / 2)) - (vpRect.top + (vpRect.height / 2));

                    const drawX = (600 + (dx * scaleFactor)) - (drawW / 2);
                    const drawY = (275 + (dy * scaleFactor)) - (drawH / 2);

                    ctx.drawImage(loadedImg, drawX, drawY, drawW, drawH);
                    const croppedDataUrl = canvas.toDataURL('image/jpeg', 0.90);
                    
                    closeCoverCropperModal();
                    if (typeof onCropComplete === 'function') {
                        onCropComplete(croppedDataUrl);
                    }
                };
                loadedImg.src = srcUrl;
            };
        }
    };

    if (typeof fileOrDataUrl === 'string') {
        processImage(fileOrDataUrl);
    } else if (fileOrDataUrl instanceof File || fileOrDataUrl instanceof Blob) {
        const reader = new FileReader();
        reader.onload = function(e) {
            processImage(e.target.result);
        };
        reader.readAsDataURL(fileOrDataUrl);
    }
};

window.closeCoverCropperModal = function() {
    const el = document.getElementById('coverCropperModal');
    if (el) el.remove();
};

// ── Profile Avatar Cropper Modal ─────────────────────────────────────────────
window.openAvatarCropperModal = function(fileOrDataUrl, onCropComplete) {
    const processImage = function(srcUrl) {
        let zoom = 1.0;
        let offsetX = 0;
        let offsetY = 0;
        let isDragging = false;
        let startX = 0;
        let startY = 0;

        const modalDiv = document.createElement('div');
        modalDiv.id = 'avatarCropperModal';
        modalDiv.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(8,23,41,0.92); z-index:9999999; display:flex; align-items:center; justify-content:center; padding:16px; box-sizing:border-box; backdrop-filter:blur(6px);';
        
        modalDiv.innerHTML = `
            <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.15); border-radius:20px; width:100%; max-width:440px; padding:24px; box-shadow:0 24px 60px rgba(0,0,0,0.8); color:#FFF; text-align:center;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.2rem; font-weight:800; margin:0; color:#FFF;">
                        <i class="fa-solid fa-user-gear" style="color:var(--accent, #F2A735); margin-right:8px;"></i> Crop Profile Picture
                    </h3>
                    <button type="button" onclick="closeAvatarCropperModal()" style="background:none; border:none; color:#94A3B8; font-size:1.2rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <p style="font-size:0.8rem; color:#94A3B8; margin:0 0 16px 0;">Slide towards <strong style="color:var(--accent, #F2A735);">+</strong> to zoom in. Drag image to adjust profile position.</p>

                <div style="display:flex; justify-content:center; margin-bottom:16px;">
                    <div id="avatar-crop-viewport" style="position:relative; width:220px; height:220px; border-radius:50%; overflow:hidden; border:4px solid var(--accent, #F2A735); background:#000; cursor:grab; user-select:none; box-shadow:0 10px 30px rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center;">
                        <img id="avatar-crop-source-img" src="${srcUrl}" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%) scale(1); max-width:none; max-height:none; opacity:0; transition:opacity 0.15s; pointer-events:none;">
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:12px; margin-bottom:18px; background:rgba(255,255,255,0.04); padding:10px 14px; border-radius:12px;">
                    <span style="font-size:0.85rem; font-weight:800; color:#94A3B8;">-</span>
                    <input type="range" id="avatar-crop-zoom-slider" min="0.05" max="3.0" step="0.01" value="0.05" style="flex:1; cursor:pointer;">
                    <span style="font-size:1.1rem; font-weight:800; color:var(--accent, #F2A735);">+</span>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn btn-outline btn-full" onclick="closeAvatarCropperModal()" style="border-color:rgba(255,255,255,0.2); color:#FFF;">Cancel</button>
                    <button type="button" class="btn btn-primary btn-full" id="btn-do-avatar-crop" style="font-weight:800;">
                        <i class="fa-solid fa-check" style="margin-right:6px;"></i> Crop & Apply Profile
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modalDiv);

        const imgEl = document.getElementById('avatar-crop-source-img');
        const viewport = document.getElementById('avatar-crop-viewport');
        const zoomSlider = document.getElementById('avatar-crop-zoom-slider');

        function updateTransform() {
            if (imgEl) {
                imgEl.style.transform = `translate(calc(-50% + ${offsetX}px), calc(-50% + ${offsetY}px)) scale(${zoom})`;
            }
        }

        const loadedMeasurer = new Image();
        loadedMeasurer.onload = function() {
            const nw = loadedMeasurer.naturalWidth || 400;
            const nh = loadedMeasurer.naturalHeight || 400;
            const vpW = viewport.clientWidth || 220;
            const vpH = viewport.clientHeight || 220;

            imgEl.style.width = nw + 'px';
            imgEl.style.height = nh + 'px';

            const scaleW = vpW / nw;
            const scaleH = vpH / nh;
            const containScale = Math.min(scaleW, scaleH);
            const coverScale = Math.max(scaleW, scaleH);

            const minScale = Math.round(containScale * 100) / 100 || 0.05;
            const maxScale = Math.round(Math.max(coverScale * 3, containScale * 4) * 100) / 100 || 3.0;

            zoom = minScale;
            offsetX = 0;
            offsetY = 0;

            if (zoomSlider) {
                zoomSlider.min = minScale;
                zoomSlider.max = maxScale;
                zoomSlider.step = (maxScale - minScale) / 100;
                zoomSlider.value = minScale;
            }

            imgEl.style.opacity = '1';
            updateTransform();
        };
        loadedMeasurer.src = srcUrl;

        if (zoomSlider) {
            zoomSlider.addEventListener('input', function() {
                zoom = parseFloat(this.value);
                updateTransform();
            });
        }

        if (viewport) {
            viewport.addEventListener('mousedown', function(e) {
                isDragging = true;
                startX = e.clientX - offsetX;
                startY = e.clientY - offsetY;
                viewport.style.cursor = 'grabbing';
            });

            window.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                offsetX = e.clientX - startX;
                offsetY = e.clientY - startY;
                updateTransform();
            });

            window.addEventListener('mouseup', function() {
                isDragging = false;
                if (viewport) viewport.style.cursor = 'grab';
            });

            viewport.addEventListener('touchstart', function(e) {
                if (e.touches.length === 1) {
                    isDragging = true;
                    startX = e.touches[0].clientX - offsetX;
                    startY = e.touches[0].clientY - offsetY;
                }
            });

            viewport.addEventListener('touchmove', function(e) {
                if (isDragging && e.touches.length === 1) {
                    offsetX = e.touches[0].clientX - startX;
                    offsetY = e.touches[0].clientY - startY;
                    updateTransform();
                }
            });

            viewport.addEventListener('touchend', function() {
                isDragging = false;
            });
        }

        const cropBtn = document.getElementById('btn-do-avatar-crop');
        if (cropBtn) {
            cropBtn.onclick = function() {
                const canvas = document.createElement('canvas');
                canvas.width = 600;
                canvas.height = 600;
                const ctx = canvas.getContext('2d');

                const loadedImg = new Image();
                loadedImg.crossOrigin = "anonymous";
                loadedImg.onload = function() {
                    ctx.fillStyle = '#0F1923';
                    ctx.fillRect(0, 0, 600, 600);

                    const vpRect = viewport.getBoundingClientRect();
                    const imgRect = imgEl.getBoundingClientRect();

                    const scaleFactor = 600 / (vpRect.width || 220);

                    const drawW = imgRect.width * scaleFactor;
                    const drawH = imgRect.height * scaleFactor;

                    const dx = (imgRect.left + (imgRect.width / 2)) - (vpRect.left + (vpRect.width / 2));
                    const dy = (imgRect.top + (imgRect.height / 2)) - (vpRect.top + (vpRect.height / 2));

                    const drawX = (300 + (dx * scaleFactor)) - (drawW / 2);
                    const drawY = (300 + (dy * scaleFactor)) - (drawH / 2);

                    ctx.drawImage(loadedImg, drawX, drawY, drawW, drawH);
                    const croppedDataUrl = canvas.toDataURL('image/jpeg', 0.90);
                    
                    closeAvatarCropperModal();
                    if (typeof onCropComplete === 'function') {
                        onCropComplete(croppedDataUrl);
                    }
                };
                loadedImg.src = srcUrl;
            };
        }
    };

    if (typeof fileOrDataUrl === 'string') {
        processImage(fileOrDataUrl);
    } else if (fileOrDataUrl instanceof File || fileOrDataUrl instanceof Blob) {
        const reader = new FileReader();
        reader.onload = function(e) {
            processImage(e.target.result);
        };
        reader.readAsDataURL(fileOrDataUrl);
    }
};

window.closeAvatarCropperModal = function() {
    const el = document.getElementById('avatarCropperModal');
    if (el) el.remove();
};


window.compressImageFileBeforeUpload = function(file, maxWidth = 1600, maxHeight = 1600, quality = 0.8, callback) {
    if (!file || !file.type || !file.type.startsWith('image/')) {
        if (typeof callback === 'function') callback(file);
        return;
    }
    const reader = new FileReader();
    reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
            let width = img.width;
            let height = img.height;
            if (width > maxWidth || height > maxHeight) {
                if (width > height) {
                    height = Math.round((height * maxWidth) / width);
                    width = maxWidth;
                } else {
                    width = Math.round((width * maxHeight) / height);
                    height = maxHeight;
                }
            }
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);
            canvas.toBlob((blob) => {
                if (blob) {
                    const compressedFile = new File([blob], file.name || 'upload.jpg', { type: 'image/jpeg' });
                    if (typeof callback === 'function') callback(compressedFile);
                } else {
                    if (typeof callback === 'function') callback(file);
                }
            }, 'image/jpeg', quality);
        };
        img.onerror = () => { if (typeof callback === 'function') callback(file); };
        img.src = e.target.result;
    };
    reader.onerror = () => { if (typeof callback === 'function') callback(file); };
    reader.readAsDataURL(file);
};



