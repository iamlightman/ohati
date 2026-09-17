// reels_app.js — Core Controller for OHATI Reels & Social Short-Video Experience (Isolated Demo)
(function() {
    'use strict';

    let reelsData = [];
    let state = null;
    let observer = null;
    let activeReelId = null;
    let isMuted = true; // Default muted for browser autoplay compliance

    // Ensure any auth lock screen or loading overlay is suppressed
    function removeAuthLockOverlays() {
        window.showMandatoryAuthLockScreen = function() {};
        const authOverlay = document.getElementById('mandatory-auth-lock-overlay');
        if (authOverlay) {
            authOverlay.style.display = 'none';
            try { authOverlay.remove(); } catch(e) {}
        }
        const welcomeOverlay = document.getElementById('welcome-popup-overlay');
        if (welcomeOverlay) {
            welcomeOverlay.style.display = 'none';
            try { welcomeOverlay.remove(); } catch(e) {}
        }
        const splashScreen = document.getElementById('screen-loading');
        if (splashScreen) {
            splashScreen.style.display = 'none';
            try { splashScreen.remove(); } catch(e) {}
        }
    }

    // Initialize Reels Application
    function initReelsApp() {
        removeAuthLockOverlays();

        if (!window.OhatiReelsData) {
            console.error('OhatiReelsData script not loaded.');
            return;
        }

        reelsData = window.OhatiReelsData.reels;
        state = window.OhatiReelsData.loadState();

        const reelsSection = document.getElementById('screen-reels');
        if (!reelsSection) return;

        reelsSection.innerHTML = buildReelsMarkup();
        setupIntersectionObserver();
        bindGlobalEvents();

        // Force Reels screen active immediately on load
        showReelsScreen();
    }

    // Build DOM structure for Reels Feed & Modals
    function buildReelsMarkup() {
        return `
            <div class="reels-wrapper">
                <div class="reels-feed" id="reels-feed-container">
                    ${reelsData.map((reel, index) => renderReelCard(reel, index)).join('')}
                </div>
            </div>

            <!-- Comment Sheet Modal -->
            <div class="reels-comment-backdrop" id="reels-comment-backdrop" onclick="closeCommentSheet()">
                <div class="reels-comment-sheet" onclick="event.stopPropagation()">
                    <div class="comment-sheet-header">
                        <span class="comment-sheet-title" id="comment-sheet-title">Comments</span>
                        <button class="comment-sheet-close" onclick="closeCommentSheet()"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="comment-list-container" id="comment-list-container">
                        <!-- Dynamic Comments -->
                    </div>
                    <div class="comment-input-area">
                        <input type="text" class="comment-input" id="reel-comment-input" placeholder="Add a comment on Ohati..." onkeydown="if(event.key==='Enter') submitReelComment()">
                        <button class="comment-send-btn" onclick="submitReelComment()"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>

            <!-- Creator Profile Modal -->
            <div class="reels-profile-modal" id="reels-profile-modal">
                <div class="profile-modal-header">
                    <button class="comment-sheet-close" onclick="closeCreatorProfileModal()"><i class="fa-solid fa-arrow-left"></i></button>
                    <span class="profile-modal-title" id="profile-modal-username">@creator</span>
                    <div style="width:24px;"></div>
                </div>
                <div class="profile-modal-body" id="profile-modal-body">
                    <!-- Dynamic Creator Content -->
                </div>
            </div>
        `;
    }

    // Render individual Reel Card markup
    function renderReelCard(reel, index) {
        const isLiked = !!state.likedReels[reel.id];
        const likeCount = (state.likeCounts[reel.id] !== undefined) ? state.likeCounts[reel.id] : reel.likes;
        const isReposted = !!state.repostedReels[reel.id];
        const repostCount = (state.repostCounts[reel.id] !== undefined) ? state.repostCounts[reel.id] : reel.reposts;
        const isFollowing = !!state.followedCreators[reel.creator.id];
        const commentsCount = (state.comments[reel.id] ? state.comments[reel.id].length : reel.commentsCount);

        return `
            <div class="reel-card" id="reel-card-${reel.id}" data-reel-id="${reel.id}" data-index="${index}">
                <!-- Video & Poster -->
                <div class="reel-video-container" onclick="handleVideoTap('${reel.id}')">
                    <video class="reel-video" id="video-${reel.id}" 
                        src="${reel.videoUrl}" 
                        poster="${reel.coverUrl}"
                        loop 
                        playsinline 
                        preload="${index < 2 ? 'auto' : 'metadata'}" 
                        ${isMuted ? 'muted' : ''}>
                    </video>
                    <img src="${reel.coverUrl}" alt="Poster" class="reel-poster" id="poster-${reel.id}">
                </div>

                <!-- Overlays -->
                <div class="reel-top-gradient"></div>
                <div class="reel-gradient-overlay"></div>

                <!-- Reposted Banner -->
                ${isReposted ? `
                    <div class="reel-reposted-banner">
                        <i class="fa-solid fa-arrows-rotate"></i> Reposted by you
                    </div>
                ` : ''}

                <!-- Mute / Unmute Button -->
                <button class="reel-mute-btn" id="mute-btn-${reel.id}" onclick="toggleMuteState(event, '${reel.id}')">
                    <i class="fa-solid ${isMuted ? 'fa-volume-xmark' : 'fa-volume-high'}"></i>
                </button>

                <!-- Tap Play/Pause Indicator -->
                <div class="reel-tap-indicator" id="tap-indicator-${reel.id}">
                    <i class="fa-solid fa-play"></i>
                </div>

                <!-- Action Rail (Right side — Compact TikTok Layout) -->
                <div class="reel-actions-rail">
                    <!-- Like Button -->
                    <button class="action-item ${isLiked ? 'liked' : ''}" id="like-btn-${reel.id}" onclick="toggleLikeReel('${reel.id}')">
                        <div class="action-btn-circle">
                            <i class="fa-solid fa-heart"></i>
                        </div>
                        <span class="action-count" id="like-count-${reel.id}">${formatCount(likeCount)}</span>
                    </button>

                    <!-- Comment Button -->
                    <button class="action-item" onclick="openCommentSheet('${reel.id}')">
                        <div class="action-btn-circle">
                            <i class="fa-solid fa-comment-dots"></i>
                        </div>
                        <span class="action-count" id="comment-count-${reel.id}">${formatCount(commentsCount)}</span>
                    </button>

                    <!-- Repost Button -->
                    <button class="action-item ${isReposted ? 'reposted' : ''}" id="repost-btn-${reel.id}" onclick="toggleRepostReel('${reel.id}')">
                        <div class="action-btn-circle">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </div>
                        <span class="action-count" id="repost-count-${reel.id}">${formatCount(repostCount)}</span>
                    </button>

                    <!-- Share Button -->
                    <button class="action-item" onclick="shareReel('${reel.id}')">
                        <div class="action-btn-circle">
                            <i class="fa-solid fa-share-nodes"></i>
                        </div>
                        <span class="action-count">Share</span>
                    </button>

                    <!-- Rotating Sound Disc -->
                    <div class="reel-sound-disc" onclick="openCreatorProfile('${reel.creator.id}')">
                        <img src="${reel.creator.avatar}" alt="Audio" onerror="window.handleImageError(this, 'avatar')">
                    </div>
                </div>

                <!-- Creator & Caption (Bottom Left — Pro TikTok Layout) -->
                <div class="reel-creator-info">
                    <div class="creator-header">
                        <div class="creator-avatar-wrap" onclick="openCreatorProfile('${reel.creator.id}')">
                            <img src="${reel.creator.avatar}" alt="${reel.creator.name}" class="creator-avatar" onerror="window.handleImageError(this, 'avatar')">
                        </div>
                        <div class="creator-details">
                            <div class="creator-name-row">
                                <span class="creator-name" onclick="openCreatorProfile('${reel.creator.id}')">
                                    ${reel.creator.name}
                                    ${reel.creator.verified ? '<i class="fa-solid fa-circle-check"></i>' : ''}
                                </span>
                                <button class="btn-follow ${isFollowing ? 'following' : ''}" id="follow-btn-${reel.creator.id}" onclick="toggleFollowCreator(event, '${reel.creator.id}')">
                                    ${isFollowing ? 'Following' : 'Follow'}
                                </button>
                            </div>
                            <div class="creator-username" onclick="openCreatorProfile('${reel.creator.id}')">${reel.creator.username} • ${reel.location}</div>
                        </div>
                    </div>

                    <div class="reel-caption-box" id="caption-box-${reel.id}">
                        <span>${escapeHtml(reel.caption)}</span>
                        <div class="reel-hashtags">${escapeHtml(reel.hashtags)}</div>
                        <button class="caption-toggle-btn" onclick="toggleCaptionExpand(event, '${reel.id}')">
                            <span class="more-lbl">...more</span>
                            <span class="less-lbl"> less</span>
                        </button>
                    </div>

                    <div class="reel-sound-track">
                        <i class="fa-solid fa-music"></i>
                        <span class="sound-title">${escapeHtml(reel.soundTrack)}</span>
                    </div>
                </div>
            </div>
        `;
    }

    // Toggle caption show more / less
    window.toggleCaptionExpand = function(event, reelId) {
        if (event) event.stopPropagation();
        const box = document.getElementById(`caption-box-${reelId}`);
        if (box) {
            box.classList.toggle('expanded');
        }
    };

    // ── INTELLIGENT INTERSECTION OBSERVER LIFECYCLE ENGINE ─────────────────
    function setupIntersectionObserver() {
        const options = {
            root: document.getElementById('reels-feed-container'),
            rootMargin: '0px',
            threshold: 0.65 // Video must be 65% visible to become ACTIVE
        };

        observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const reelId = entry.target.getAttribute('data-reel-id');
                const reelIndex = parseInt(entry.target.getAttribute('data-index'), 10);
                const video = document.getElementById(`video-${reelId}`);
                const poster = document.getElementById(`poster-${reelId}`);

                if (entry.isIntersecting) {
                    activeReelId = reelId;
                    playActiveVideo(video, poster, reelId);
                    manageNearbyVideos(reelIndex);
                } else {
                    pauseInactiveVideo(video);
                }
            });
        }, options);

        const reelCards = document.querySelectorAll('.reel-card');
        reelCards.forEach(card => observer.observe(card));
    }

    // Play active video with browser autoplay promise handling
    function playActiveVideo(video, poster, reelId) {
        if (!video) return;

        if (poster) {
            poster.classList.add('hidden');
        }

        video.muted = isMuted;

        const playPromise = video.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                // Playback started successfully
            }).catch(err => {
                console.warn('Autoplay prevented on reel', reelId, 'Muting and retrying:', err);
                video.muted = true;
                isMuted = true;
                updateAllMuteIcons();
                video.play().catch(e => console.error('Play failed after mute:', e));
            });
        }
    }

    // Pause inactive video cleanly without disturbing nearby DOM state
    function pauseInactiveVideo(video) {
        if (!video) return;
        if (!video.paused) {
            video.pause();
        }
    }

    // Lifecycle Manager: Keeps NEXT and PREVIOUS videos prepared; releases DISTANT videos (3+ cards away)
    function manageNearbyVideos(activeIndex) {
        reelsData.forEach((reel, index) => {
            const distance = Math.abs(index - activeIndex);
            const video = document.getElementById(`video-${reel.id}`);
            if (!video) return;

            if (distance === 1) {
                // NEXT / PREVIOUS: Preload metadata for near-instant playback
                if (video.getAttribute('preload') !== 'auto') {
                    video.setAttribute('preload', 'auto');
                }
            } else if (distance >= 3) {
                // DISTANT: Pause and release heavy playback buffers if needed
                if (!video.paused) {
                    video.pause();
                }
            }
        });
    }

    // ── TAP INTERACTION (PLAY / PAUSE TOGGLE & DOUBLE TAP LIKE) ────────────
    let lastTapTime = 0;
    window.handleVideoTap = function(reelId) {
        const currentTime = new Date().getTime();
        const tapLength = currentTime - lastTapTime;

        if (tapLength < 300 && tapLength > 0) {
            // DOUBLE TAP -> LIKE
            toggleLikeReel(reelId, true);
            showTapHeartAnimation(reelId);
        } else {
            // SINGLE TAP -> PLAY/PAUSE
            togglePlayPause(reelId);
        }
        lastTapTime = currentTime;
    };

    function togglePlayPause(reelId) {
        const video = document.getElementById(`video-${reelId}`);
        const indicator = document.getElementById(`tap-indicator-${reelId}`);
        if (!video) return;

        if (video.paused) {
            video.play();
            if (indicator) {
                indicator.innerHTML = '<i class="fa-solid fa-play"></i>';
                animateTapIndicator(indicator);
            }
        } else {
            video.pause();
            if (indicator) {
                indicator.innerHTML = '<i class="fa-solid fa-pause"></i>';
                animateTapIndicator(indicator);
            }
        }
    }

    function animateTapIndicator(el) {
        el.classList.add('active');
        setTimeout(() => el.classList.remove('active'), 600);
    }

    function showTapHeartAnimation(reelId) {
        const indicator = document.getElementById(`tap-indicator-${reelId}`);
        if (indicator) {
            indicator.innerHTML = '<i class="fa-solid fa-heart" style="color:#ff3b5c;"></i>';
            animateTapIndicator(indicator);
        }
    }

    // ── MUTE / UNMUTE TOGGLE ─────────────────────────────────────────────
    window.toggleMuteState = function(event, reelId) {
        if (event) event.stopPropagation();
        isMuted = !isMuted;

        const allVideos = document.querySelectorAll('.reel-video');
        allVideos.forEach(v => v.muted = isMuted);

        updateAllMuteIcons();
    };

    function updateAllMuteIcons() {
        const muteBtns = document.querySelectorAll('.reel-mute-btn');
        muteBtns.forEach(btn => {
            btn.innerHTML = `<i class="fa-solid ${isMuted ? 'fa-volume-xmark' : 'fa-volume-high'}"></i>`;
        });
    }

    // ── SOCIAL ACTIONS (LIKE, REPOST, FOLLOW, SHARE) ─────────────────────
    window.toggleLikeReel = function(reelId, forceLike) {
        const reel = reelsData.find(r => r.id === reelId);
        if (!reel) return;

        const isLiked = forceLike ? true : !state.likedReels[reelId];
        state.likedReels[reelId] = isLiked;

        let currentCount = state.likeCounts[reelId] !== undefined ? state.likeCounts[reelId] : reel.likes;
        if (isLiked && !forceLike) currentCount += 1;
        else if (!isLiked && !forceLike) currentCount = Math.max(0, currentCount - 1);
        else if (forceLike && !state.likedReels[reelId]) currentCount += 1;

        state.likeCounts[reelId] = currentCount;
        window.OhatiReelsData.saveState(state);

        const btn = document.getElementById(`like-btn-${reelId}`);
        const countEl = document.getElementById(`like-count-${reelId}`);

        if (btn) {
            if (isLiked) btn.classList.add('liked');
            else btn.classList.remove('liked');
        }
        if (countEl) countEl.innerText = formatCount(currentCount);
    };

    window.toggleRepostReel = function(reelId) {
        const reel = reelsData.find(r => r.id === reelId);
        if (!reel) return;

        const isReposted = !state.repostedReels[reelId];
        state.repostedReels[reelId] = isReposted;

        let currentCount = state.repostCounts[reelId] !== undefined ? state.repostCounts[reelId] : reel.reposts;
        currentCount = isReposted ? currentCount + 1 : Math.max(0, currentCount - 1);
        state.repostCounts[reelId] = currentCount;
        window.OhatiReelsData.saveState(state);

        const btn = document.getElementById(`repost-btn-${reelId}`);
        const countEl = document.getElementById(`repost-count-${reelId}`);

        if (btn) {
            if (isReposted) btn.classList.add('reposted');
            else btn.classList.remove('reposted');
        }
        if (countEl) countEl.innerText = formatCount(currentCount);

        if (window.showPushNotification) {
            if (isReposted) window.showPushNotification("Reel Reposted! 🔄", "This Reel is now shared to your profile feed.");
            else window.showPushNotification("Repost Removed", "Undo repost successful.");
        }
    };

    window.toggleFollowCreator = function(event, creatorId) {
        if (event) event.stopPropagation();

        const isFollowing = !state.followedCreators[creatorId];
        state.followedCreators[creatorId] = isFollowing;
        window.OhatiReelsData.saveState(state);

        const btns = document.querySelectorAll(`#follow-btn-${creatorId}`);
        btns.forEach(btn => {
            if (isFollowing) {
                btn.classList.add('following');
                btn.innerText = 'Following';
            } else {
                btn.classList.remove('following');
                btn.innerText = 'Follow';
            }
        });

        if (window.showPushNotification && isFollowing) {
            window.showPushNotification("Following Creator! ✨", "You'll see more updates from this vendor.");
        }
    };

    window.shareReel = function(reelId) {
        const reel = reelsData.find(r => r.id === reelId);
        if (!reel) return;

        const shareData = {
            title: `Check out ${reel.creator.name} on Ohati Reels!`,
            text: reel.caption,
            url: window.location.href
        };

        if (navigator.share) {
            navigator.share(shareData).catch(err => console.log('Share error:', err));
        } else {
            // Copy link fallback
            if (navigator.clipboard) {
                navigator.clipboard.writeText(window.location.href);
            }
            if (window.showPushNotification) {
                window.showPushNotification("Reel Link Copied! 📋", "Link copied to clipboard. Share with your friends!");
            }
        }
    };

    // ── COMMENT MODAL SHEET LOGIC ──────────────────────────────────────────
    let activeCommentReelId = null;

    window.openCommentSheet = function(reelId) {
        activeCommentReelId = reelId;
        const reel = reelsData.find(r => r.id === reelId);
        if (!reel) return;

        const backdrop = document.getElementById('reels-comment-backdrop');
        const container = document.getElementById('comment-list-container');
        const titleEl = document.getElementById('comment-sheet-title');

        const customComments = state.comments[reelId] || [];
        const allComments = [...reel.comments, ...customComments];

        if (titleEl) titleEl.innerText = `Comments (${allComments.length})`;

        if (container) {
            container.innerHTML = allComments.map(c => `
                <div class="comment-item">
                    <img src="${c.avatar || '../img/chill/logo.jpg'}" alt="${c.user}" class="comment-avatar" onerror="window.handleImageError(this, 'avatar')">
                    <div class="comment-body">
                        <div class="comment-user-row">
                            <span class="comment-username">${escapeHtml(c.user)}</span>
                            <span class="comment-time">${escapeHtml(c.time)}</span>
                        </div>
                        <div class="comment-text">${escapeHtml(c.text)}</div>
                    </div>
                </div>
            `).join('');
        }

        if (backdrop) backdrop.classList.add('active');
    };

    window.closeCommentSheet = function() {
        const backdrop = document.getElementById('reels-comment-backdrop');
        if (backdrop) backdrop.classList.remove('active');
    };

    window.submitReelComment = function() {
        const input = document.getElementById('reel-comment-input');
        if (!input || !input.value.trim() || !activeCommentReelId) return;

        const text = input.value.trim();
        const newComment = {
            id: 'c_' + Date.now(),
            user: 'You (Guest User)',
            avatar: '../img/chill/logo.jpg',
            text: text,
            time: 'Just now',
            likes: 0
        };

        if (!state.comments[activeCommentReelId]) {
            state.comments[activeCommentReelId] = [];
        }
        state.comments[activeCommentReelId].push(newComment);
        window.OhatiReelsData.saveState(state);

        input.value = '';
        openCommentSheet(activeCommentReelId); // Refresh comment list

        // Update comment count on reel card
        const reel = reelsData.find(r => r.id === activeCommentReelId);
        const countEl = document.getElementById(`comment-count-${activeCommentReelId}`);
        if (countEl && reel) {
            const total = reel.comments.length + state.comments[activeCommentReelId].length;
            countEl.innerText = formatCount(total);
        }
    };

    // Global Image Error Override: Guarantees 100% Chill & Serve local image fallbacks
    window.DEFAULT_USER_AVATAR = '../img/chill/logo.jpg';
    window.DEFAULT_VENDOR_COVER = '../img/chill/event1.jpg';

    window.handleImageError = function(imgEl, type) {
        if (!imgEl) return;
        imgEl.onerror = null;
        imgEl.src = (type === 'cover' || type === 'vendor' || type === 'reel') ? '../img/chill/event1.jpg' : '../img/chill/logo.jpg';
    };

    // ── CREATOR PROFILE MODAL LOGIC ────────────────────────────────────────
    window.openCreatorProfile = function(creatorId) {
        const reelWithCreator = reelsData.find(r => r.creator.id === creatorId);
        if (!reelWithCreator) return;
        const creator = reelWithCreator.creator;
        const isFollowing = !!state.followedCreators[creator.id];

        const modal = document.getElementById('reels-profile-modal');
        const usernameEl = document.getElementById('profile-modal-username');
        const bodyEl = document.getElementById('profile-modal-body');

        if (usernameEl) usernameEl.innerText = creator.username;

        if (bodyEl) {
            // Display full feed of Chill & Serve Reels in profile grid
            let creatorReels = reelsData.filter(r => r.creator.id === creatorId);
            if (creatorReels.length < 4) {
                creatorReels = reelsData;
            }

            bodyEl.innerHTML = `
                <div class="profile-hero">
                    <img src="${creator.avatar}" alt="${creator.name}" class="profile-large-avatar" onerror="window.handleImageError(this, 'avatar')">
                    <h3 style="margin:0; font-size:1.3rem; color:#fff; font-weight:800;">
                        ${creator.name}
                        ${creator.verified ? '<i class="fa-solid fa-circle-check" style="color:#38bdf8; font-size:1.1rem;"></i>' : ''}
                    </h3>
                    <div style="font-size:0.85rem; color:#94a3b8;">${creator.username}</div>
                    <div style="font-size:0.88rem; color:#cbd5e1; max-width:480px; line-height:1.4; margin-top:4px;">
                        ${escapeHtml(creator.bio)}
                    </div>

                    <div class="profile-stats-row">
                        <div class="profile-stat-item">
                            <span class="stat-num">${creator.followers}</span>
                            <span class="stat-lbl">Followers</span>
                        </div>
                        <div class="profile-stat-item">
                            <span class="stat-num">${creator.following}</span>
                            <span class="stat-lbl">Following</span>
                        </div>
                        <div class="profile-stat-item">
                            <span class="stat-num">${creator.totalLikes}</span>
                            <span class="stat-lbl">Likes</span>
                        </div>
                    </div>

                    <button class="btn-follow ${isFollowing ? 'following' : ''}" style="padding:10px 32px; font-size:0.95rem; border-radius:24px;" id="follow-btn-${creator.id}" onclick="toggleFollowCreator(event, '${creator.id}')">
                        ${isFollowing ? 'Following' : 'Follow'}
                    </button>
                </div>

                <div style="border-top:1px solid rgba(255,255,255,0.1); padding-top:16px;">
                    <div style="font-weight:700; font-size:0.95rem; color:#e2e8f0; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-clapperboard" style="color:#f2a735;"></i> Reels
                    </div>

                    <div class="profile-grid">
                        ${creatorReels.map(r => `
                            <div class="profile-grid-item" onclick="jumpToReel('${r.id}')">
                                <img src="${r.coverUrl}" alt="Reel" class="profile-grid-img" onerror="window.handleImageError(this, 'cover')">
                                <div class="profile-grid-views">
                                    <i class="fa-solid fa-play"></i> ${formatCount(r.views)}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        if (modal) modal.classList.add('active');
    };

    window.closeCreatorProfileModal = function() {
        const modal = document.getElementById('reels-profile-modal');
        if (modal) modal.classList.remove('active');
    };

    window.jumpToReel = function(reelId) {
        closeCreatorProfileModal();
        const reelCard = document.getElementById(`reel-card-${reelId}`);
        if (reelCard) {
            reelCard.scrollIntoView({ behavior: 'smooth' });
        }
    };

    // ── UTILITY HELPERS ──────────────────────────────────────────────────
    function formatCount(num) {
        if (!num) return '0';
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return num.toString();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function bindGlobalEvents() {
        // Register Reels in screen router if navigateTo exists
        if (window.navigateTo) {
            const originalNavigateTo = window.navigateTo;
            window.navigateTo = function(screenId, params, options) {
                if (screenId === 'reels') {
                    showReelsScreen();
                    return;
                }
                originalNavigateTo(screenId, params, options);
            };
        }
    }

    function showReelsScreen() {
        const screens = document.querySelectorAll('.screen');
        screens.forEach(s => s.style.display = 'none');

        const reelsSection = document.getElementById('screen-reels');
        if (reelsSection) {
            reelsSection.style.display = 'block';
            if (activeReelId) {
                const video = document.getElementById(`video-${activeReelId}`);
                if (video) video.play().catch(e => console.log(e));
            }
        }

        // Highlight Desktop Nav & Bottom Nav
        const navItems = document.querySelectorAll('.desktop-nav-item, .nav-item');
        navItems.forEach(item => item.classList.remove('active'));

        const reelsNavBtn = document.getElementById('desktop-nav-reels');
        if (reelsNavBtn) reelsNavBtn.classList.add('active');

        const bottomReelsBtn = document.getElementById('nav-btn-reels');
        if (bottomReelsBtn) bottomReelsBtn.classList.add('active');
    }

    // Run overlay removal immediately and periodically during boot
    removeAuthLockOverlays();
    const cleanTimer = setInterval(removeAuthLockOverlays, 100);
    setTimeout(() => clearInterval(cleanTimer), 3000);

    // Auto-init on DOMContentLoaded and enforce Reels screen priority over default router
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initReelsApp, 20);
        setTimeout(showReelsScreen, 100);
        setTimeout(showReelsScreen, 300);
        setTimeout(showReelsScreen, 800);
    });

    window.addEventListener('load', () => {
        showReelsScreen();
    });

})();
