// js/blog.js — Ohati Blog Frontend Renderer & Interactive Controller

// Global blog state cache
window.blogState = {
    currentCategory: 'all',
    searchQuery: '',
    posts: [],
    categories: [],
    heroPost: null,
    activePost: null,
    page: 1,
    limit: 20,
    total: 0
};

// ── 1. BLOG INDEX SCREEN (SECTION-BY-SECTION) ─────────────────────────────
function initBlogScreen(params = {}) {
    const container = document.getElementById('screen-blog');
    if (!container) return;
    container.classList.add('light-theme-forced');

    if (params && params.category) {
        window.blogState.currentCategory = params.category;
    }

    container.innerHTML = `
        <div class="blog-container">
            <!-- SECTION 1: HEADER & SEARCH BAR -->
            <div class="blog-header-section">
                <div class="blog-header-content">
                    <span class="blog-badge"><i class="fa-solid fa-newspaper"></i> OHATI WEDDING & EVENT JOURNAL</span>
                    <h1 class="blog-main-title">Stories, Guides & Inspiration</h1>
                    <p class="blog-main-desc">Expert advice on wedding planning, venue selection, decor trends, catering, and photography in Ghana.</p>
                    
                    <div class="blog-search-box">
                        <i class="fa-solid fa-magnifying-glass search-box-icon"></i>
                        <input type="text" id="blog-search-input" placeholder="Search guides, timelines, decor ideas..." value="${window.blogState.searchQuery || ''}" onkeyup="handleBlogSearchKeyup(event)">
                        <button class="blog-search-btn" onclick="triggerBlogSearch()"><i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: CATEGORY FILTER PILLS -->
            <div class="blog-categories-wrapper" id="blog-categories-bar">
                <button class="blog-cat-pill ${window.blogState.currentCategory === 'all' ? 'active' : ''}" onclick="filterBlogCategory('all')">All Topics</button>
                <button class="blog-cat-pill ${window.blogState.currentCategory === 'Planning & Timeline' ? 'active' : ''}" onclick="filterBlogCategory('Planning & Timeline')">Planning & Timeline</button>
                <button class="blog-cat-pill ${window.blogState.currentCategory === 'Venues & Locations' ? 'active' : ''}" onclick="filterBlogCategory('Venues & Locations')">Venues & Locations</button>
                <button class="blog-cat-pill ${window.blogState.currentCategory === 'Decoration & Styling' ? 'active' : ''}" onclick="filterBlogCategory('Decoration & Styling')">Decoration & Styling</button>
                <button class="blog-cat-pill ${window.blogState.currentCategory === 'Food & Drinks' ? 'active' : ''}" onclick="filterBlogCategory('Food & Drinks')">Food & Drinks</button>
                <button class="blog-cat-pill ${window.blogState.currentCategory === 'Photography & Media' ? 'active' : ''}" onclick="filterBlogCategory('Photography & Media')">Photography & Media</button>
            </div>

            <div id="blog-feed-body">
                <div class="blog-skeleton-loader">
                    ${renderSkeletonCardsHTML(4)}
                </div>
            </div>
        </div>
    `;

    fetchBlogFeedData();
}

function fetchBlogFeedData() {
    const params = {
        page: window.blogState.page || 1,
        limit: window.blogState.limit || 20
    };
    if (window.blogState.currentCategory && window.blogState.currentCategory !== 'all') {
        params.category = window.blogState.currentCategory;
    }
    if (window.blogState.searchQuery) {
        params.search = window.blogState.searchQuery;
    }

    API.getBlogPosts(params)
        .then(res => {
            if (!res || !res.success) {
                document.getElementById('blog-feed-body').innerHTML = `
                    <div class="blog-empty-state">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <h3>Unable to load blog articles</h3>
                        <p>Please check your connection and try again.</p>
                    </div>
                `;
                return;
            }

            window.blogState.posts = res.posts || [];
            window.blogState.heroPost = res.hero_post || (res.posts[0] ?? null);
            window.blogState.total = res.total || res.posts.length || 0;
            window.blogState.page = res.page || 1;
            renderBlogFeedSections();
        })
        .catch(err => {
            console.error("Failed to load blog posts:", err);
        });
}

function renderBlogFeedSections() {
    const bodyContainer = document.getElementById('blog-feed-body');
    if (!bodyContainer) return;

    const posts = window.blogState.posts;
    const heroPost = window.blogState.heroPost;

    if (posts.length === 0) {
        bodyContainer.innerHTML = `
            <div class="blog-empty-state">
                <i class="fa-solid fa-newspaper" style="font-size:2.5rem; color:var(--gray-400, #94A3B8); margin-bottom:12px;"></i>
                <h3>No articles found</h3>
                <p>Try searching for a different keyword or select another category topic.</p>
                <button class="btn btn-primary" onclick="filterBlogCategory('all')" style="margin-top:14px;">View All Topics</button>
            </div>
        `;
        return;
    }

    let html = '';

    // SECTION 2: SPOTLIGHT / FEATURED HERO ARTICLE (Only on 'All' category, page 1 and no search)
    if ((!window.blogState.currentCategory || window.blogState.currentCategory === 'all') && !window.blogState.searchQuery && heroPost && window.blogState.page === 1) {
        html += `
            <div class="blog-hero-card" onclick="openBlogArticle(${heroPost.id})">
                <div class="blog-hero-media">
                    <img src="${heroPost.cover_image || window.DEFAULT_BUSINESS_COVER}" alt="${escapeHtml(heroPost.title)} - Ohati Event Guide" title="${escapeHtml(heroPost.title)}" class="blog-hero-img" onerror="this.src=window.DEFAULT_BUSINESS_COVER">
                    <span class="blog-hero-badge"><i class="fa-solid fa-star"></i> FEATURED GUIDE</span>
                </div>
                <div class="blog-hero-content">
                    <div class="blog-meta-top">
                        <span class="blog-cat-tag">${escapeHtml(heroPost.category)}</span>
                        <span class="blog-meta-dot">•</span>
                        <span><i class="fa-regular fa-clock"></i> ${heroPost.reading_time || 4} min read</span>
                    </div>
                    <h2 class="blog-hero-title">${escapeHtml(heroPost.title)}</h2>
                    <p class="blog-hero-excerpt">${escapeHtml(heroPost.subheadline || '')}</p>
                    <div class="blog-hero-footer">
                        <div class="blog-author-info">
                            <img src="${heroPost.author_avatar || window.DEFAULT_USER_AVATAR}" alt="${escapeHtml(heroPost.author_name || 'Author')}" title="${escapeHtml(heroPost.author_name || 'Author')}" class="blog-author-avatar" onerror="this.src=window.DEFAULT_USER_AVATAR">
                            <span>${escapeHtml(heroPost.author_name || 'Ohati Editorial')}</span>
                        </div>
                        <div class="blog-stats-group">
                            <span><i class="fa-solid fa-eye"></i> ${formatCompactNumber(heroPost.views_count)}</span>
                            <span><i class="fa-solid fa-heart" style="color:#EC4899;"></i> ${formatCompactNumber(heroPost.likes_count)}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // SECTION 3: LATEST ARTICLES GRID
    html += `
        <div class="blog-section-header">
            <h2 class="blog-section-title">
                ${window.blogState.currentCategory !== 'all' ? escapeHtml(window.blogState.currentCategory) : 'Latest Articles'}
            </h2>
            <span class="blog-count-badge">${window.blogState.total} ${window.blogState.total === 1 ? 'article' : 'articles'}</span>
        </div>
        
        <div class="blog-grid">
    `;

    posts.forEach(post => {
        // Skip hero post in grid if featured hero is displayed above on page 1
        if (heroPost && post.id === heroPost.id && (!window.blogState.currentCategory || window.blogState.currentCategory === 'all') && !window.blogState.searchQuery && posts.length > 1 && window.blogState.page === 1) {
            return;
        }

        html += `
            <div class="blog-card" onclick="openBlogArticle(${post.id})">
                <div class="blog-card-thumb-box">
                    <img src="${post.cover_image || window.DEFAULT_BUSINESS_COVER}" alt="${escapeHtml(post.title)}" title="${escapeHtml(post.title)}" class="blog-card-thumb" onerror="this.src=window.DEFAULT_BUSINESS_COVER">
                    <span class="blog-card-cat">${escapeHtml(post.category)}</span>
                    ${post.video_url ? '<span class="blog-card-video-icon"><i class="fa-solid fa-play"></i></span>' : ''}
                </div>
                <div class="blog-card-body">
                    <div class="blog-card-meta">
                        <span><i class="fa-regular fa-clock"></i> ${post.reading_time || 4} min read</span>
                        <span>•</span>
                        <span>${formatBlogDate(post.published_at)}</span>
                    </div>
                    <h3 class="blog-card-title">${escapeHtml(post.title)}</h3>
                    <p class="blog-card-excerpt">${escapeHtml(post.subheadline || '')}</p>
                    
                    <div class="blog-card-footer">
                        <div class="blog-card-author">
                            <img src="${post.author_avatar || window.DEFAULT_USER_AVATAR}" class="blog-card-avatar" onerror="this.src=window.DEFAULT_USER_AVATAR">
                            <span>${escapeHtml(post.author_name || 'Ohati Editorial')}</span>
                        </div>
                        <div class="blog-card-stats">
                            <span><i class="fa-solid fa-eye" style="color:#64748B;"></i> ${formatCompactNumber(post.views_count)}</span>
                            <span><i class="fa-solid fa-heart" style="color:#EC4899;"></i> ${formatCompactNumber(post.likes_count)}</span>
                            <span><i class="fa-solid fa-comment" style="color:#8B5CF6;"></i> ${formatCompactNumber(post.comments_count)}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    html += `</div>`;

    // SECTION 4: PAGINATION BAR
    const limit = window.blogState.limit || 20;
    const totalPages = Math.ceil(window.blogState.total / limit);
    if (totalPages > 1) {
        html += `<div class="blog-pagination">`;
        const prevDisabled = window.blogState.page <= 1 ? 'disabled' : '';
        html += `<button class="blog-page-btn" ${prevDisabled} onclick="changeBlogPage(${window.blogState.page - 1})"><i class="fa-solid fa-chevron-left"></i> Prev</button>`;
        
        for (let p = 1; p <= totalPages; p++) {
            const activeClass = p === window.blogState.page ? 'active' : '';
            html += `<button class="blog-page-btn ${activeClass}" onclick="changeBlogPage(${p})">${p}</button>`;
        }

        const nextDisabled = window.blogState.page >= totalPages ? 'disabled' : '';
        html += `<button class="blog-page-btn" ${nextDisabled} onclick="changeBlogPage(${window.blogState.page + 1})">Next <i class="fa-solid fa-chevron-right"></i></button>`;
        html += `</div>`;
    }

    bodyContainer.innerHTML = html;
}

function changeBlogPage(newPage) {
    if (newPage < 1) return;
    window.blogState.page = newPage;
    const bar = document.getElementById('blog-categories-bar');
    if (bar) {
        bar.scrollIntoView({ behavior: 'smooth' });
    }
    fetchBlogFeedData();
}

function handleBlogSearchKeyup(e) {
    if (e.key === 'Enter') {
        triggerBlogSearch();
    }
}

function triggerBlogSearch() {
    const input = document.getElementById('blog-search-input');
    if (input) {
        window.blogState.searchQuery = input.value.trim();
        window.blogState.page = 1;
        fetchBlogFeedData();
    }
}

function filterBlogCategory(cat) {
    window.blogState.currentCategory = cat;
    window.blogState.page = 1;
    document.querySelectorAll('.blog-cat-pill').forEach(el => {
        el.classList.remove('active');
        if (el.innerText.trim().toLowerCase() === cat.toLowerCase() || (cat === 'all' && el.innerText.includes('All'))) {
            el.classList.add('active');
        }
    });
    fetchBlogFeedData();
}

function openBlogArticle(idOrSlug) {
    if (typeof navigateTo === 'function') {
        navigateTo('blog-detail', { id: idOrSlug });
    }
}

// ── 2. BLOG ARTICLE DETAIL SCREEN (SECTION-BY-SECTION) ────────────────────
function initBlogDetailScreen(params = {}) {
    const container = document.getElementById('screen-blog-detail');
    if (!container) return;
    container.classList.add('light-theme-forced');

    const targetId = params.id || state.selectedBlogId || 0;
    const targetSlug = params.slug || '';

    container.innerHTML = `
        <div class="blog-detail-container">
            <div class="blog-detail-skeleton">
                <div class="skeleton skeleton-thumb" style="height:280px; border-radius:16px;"></div>
                <div class="skeleton skeleton-title" style="width:80%; height:28px; margin-top:16px;"></div>
                <div class="skeleton skeleton-text" style="width:60%; height:16px; margin-top:8px;"></div>
            </div>
        </div>
    `;

    API.getBlogPost(targetId || targetSlug)
        .then(res => {
            if (!res || !res.success || !res.post) {
                container.innerHTML = `
                    <div class="blog-detail-container">
                        <div class="blog-empty-state">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size:2.5rem; color:var(--danger, #EF4444); margin-bottom:12px;"></i>
                            <h3>Article Not Found</h3>
                            <p>${res?.error || 'The requested article could not be loaded or has been unpublished.'}</p>
                            <button class="btn btn-primary" onclick="navigateTo('blog')" style="margin-top:14px;"><i class="fa-solid fa-arrow-left"></i> Back to Blog</button>
                        </div>
                    </div>
                `;
                return;
            }

            window.blogState.activePost = res.post;
            renderBlogArticleDetails(res.post, res.comments || [], res.related || []);
        })
        .catch(err => {
            console.error("Failed to load article detail:", err);
        });
}

function renderBlogArticleDetails(post, comments, related) {
    const container = document.getElementById('screen-blog-detail');
    if (!container) return;

    // Video Embed Renderer Logic
    let videoMediaHTML = '';
    if (post.video_url) {
        const url = post.video_url.trim();
        if (url.endsWith('.mp4') || url.includes('video/')) {
            videoMediaHTML = `
                <div class="blog-video-box">
                    <video controls poster="${post.cover_image || ''}" class="blog-mp4-player" playsinline preload="metadata">
                        <source src="${url}" type="video/mp4">
                        Your browser does not support HTML5 video playback.
                    </video>
                    <div class="blog-video-fallback-bar">
                        <span><i class="fa-solid fa-circle-play"></i> Watch High Quality Video</span>
                    </div>
                </div>
            `;
        } else if (url.includes('youtube.com') || url.includes('youtu.be')) {
            let ytId = '';
            if (url.includes('v=')) ytId = url.split('v=')[1].split('&')[0];
            else if (url.includes('youtu.be/')) ytId = url.split('youtu.be/')[1].split('?')[0];
            videoMediaHTML = `
                <div class="blog-video-iframe-wrapper">
                    <iframe src="https://www.youtube.com/embed/${ytId}?autoplay=0&rel=0" frameborder="0" allowfullscreen></iframe>
                </div>
            `;
        } else if (url.includes('vimeo.com')) {
            const vimeoId = url.split('vimeo.com/')[1].split('?')[0];
            videoMediaHTML = `
                <div class="blog-video-iframe-wrapper">
                    <iframe src="https://player.vimeo.com/video/${vimeoId}" frameborder="0" allowfullscreen></iframe>
                </div>
            `;
        } else {
            videoMediaHTML = `
                <div class="blog-video-fallback-card">
                    <i class="fa-solid fa-film" style="font-size:2rem; color:var(--accent, #F2A735);"></i>
                    <div style="flex:1;">
                        <h4 style="margin:0 0 2px 0;">Watch Article Video</h4>
                        <p style="margin:0; font-size:0.8rem; color:var(--gray-500, #64748B);">Click to open video media link</p>
                    </div>
                    <a href="${url}" target="_blank" class="btn btn-primary btn-sm">Watch Video <i class="fa-solid fa-external-link"></i></a>
                </div>
            `;
        }
    }

    container.innerHTML = `
        <div class="blog-detail-container">
            
            <!-- SECTION 1: TOP NAVIGATION & ACTION BAR -->
            <div class="blog-detail-topbar">
                <button class="blog-back-btn" onclick="navigateTo('blog')">
                    <i class="fa-solid fa-chevron-left"></i> <span>Back to Blog</span>
                </button>
                <div class="blog-detail-actions">
                    <button class="blog-action-btn ${post.has_liked ? 'liked' : ''}" id="article-like-btn" onclick="toggleArticleLike(${post.id})">
                        <i class="${post.has_liked ? 'fa-solid' : 'fa-regular'} fa-heart"></i>
                        <span id="article-like-count">${formatCompactNumber(post.likes_count)}</span>
                    </button>
                    <button class="blog-action-btn" onclick="triggerArticleShare(${post.id}, '${escapeHtml(post.title)}')">
                        <i class="fa-solid fa-share-nodes"></i>
                    </button>
                </div>
            </div>

            <!-- SECTION 2: ARTICLE HEADER & META -->
            <div class="blog-article-header">
                <span class="blog-cat-pill active">${escapeHtml(post.category)}</span>
                <h1 class="blog-article-title">${escapeHtml(post.title)}</h1>
                ${post.subheadline ? `<p class="blog-article-subheadline">${escapeHtml(post.subheadline)}</p>` : ''}
                
                <div class="blog-article-author-row">
                    <img src="${post.author_avatar || window.DEFAULT_USER_AVATAR}" class="blog-article-avatar" onerror="this.src=window.DEFAULT_USER_AVATAR">
                    <div>
                        <div class="blog-article-author-name">${escapeHtml(post.author_name || 'Ohati Editorial')}</div>
                        <div class="blog-article-meta-info">
                            <span>Published ${formatBlogDate(post.published_at)}</span>
                            <span>•</span>
                            <span><i class="fa-regular fa-clock"></i> ${post.reading_time || 4} min read</span>
                            <span>•</span>
                            <span><i class="fa-solid fa-eye"></i> ${formatCompactNumber(post.views_count)} views</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: HERO COVER & MULTIMEDIA -->
            ${videoMediaHTML}
            ${!videoMediaHTML && post.cover_image ? `
                <div class="blog-article-cover-box">
                    <img src="${post.cover_image}" alt="${escapeHtml(post.title)}" class="blog-article-cover-img">
                </div>
            ` : ''}

            <!-- SECTION 4: FORMATTED ARTICLE BODY PROSE -->
            <div class="blog-article-content">
                ${post.content}
            </div>

            <!-- SECTION 5: AUTHOR & BRAND HIGHLIGHT -->
            <div class="blog-author-box">
                <img src="${post.author_avatar || window.DEFAULT_USER_AVATAR}" class="blog-author-box-img" onerror="this.src=window.DEFAULT_USER_AVATAR">
                <div class="blog-author-box-info">
                    <h4>Written by ${escapeHtml(post.author_name || 'Chill & Serve Editorial')}</h4>
                    <p>Bringing you trusted insights, planning timelines, and top vendor tips to celebrate your special events in Ghana with confidence.</p>
                    <button class="btn btn-secondary btn-sm" onclick="navigateTo('search')" style="margin-top:8px;">Explore Verified Vendors <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- SECTION 6: INTERACTIVE ENGAGEMENT BAR -->
            <div class="blog-engagement-bar">
                <button class="blog-like-main-btn ${post.has_liked ? 'liked' : ''}" id="article-like-main-btn" onclick="toggleArticleLike(${post.id})">
                    <i class="${post.has_liked ? 'fa-solid' : 'fa-regular'} fa-heart"></i>
                    <span id="article-like-main-count">${formatCompactNumber(post.likes_count)} Likes</span>
                </button>

                <button class="blog-share-main-btn" onclick="triggerArticleShare(${post.id}, '${escapeHtml(post.title)}')">
                    <i class="fa-solid fa-share-nodes"></i> ${formatCompactNumber(post.shares_count || 840)} Shares
                </button>
            </div>

            <!-- SECTION 7: COMMENTS & DISCUSSION -->
            <div class="blog-comments-section">
                <h3 class="blog-comments-title">
                    <i class="fa-solid fa-comments"></i> Discussion (${comments.length})
                </h3>

                <!-- Add Comment Form -->
                <form class="blog-comment-form" onsubmit="submitBlogComment(event, ${post.id})">
                    <textarea id="comment-text-input" placeholder="Share your thoughts or questions about this guide..." required rows="3" class="form-control"></textarea>
                    
                    ${!state.user ? `
                        <div style="display:flex; gap:10px; margin-top:8px;">
                            <input type="text" id="comment-name-input" placeholder="Your Name" class="form-control" style="flex:1;">
                            <input type="email" id="comment-email-input" placeholder="Your Email (Optional)" class="form-control" style="flex:1;">
                        </div>
                    ` : ''}

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                        <span style="font-size:0.75rem; color:var(--gray-500, #64748B);">Be respectful and constructive.</span>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Post Comment</button>
                    </div>
                </form>

                <!-- Comments List -->
                <div class="blog-comments-list" id="blog-comments-list">
                    ${comments.length === 0 ? `
                        <p style="color:var(--gray-500, #64748B); font-size:0.85rem; font-style:italic; margin-top:14px;">No comments yet. Be the first to join the discussion!</p>
                    ` : comments.map(c => {
                        window._myPostedCommentIds = window._myPostedCommentIds || [];
                        const storedGuestName = (sessionStorage.getItem('ohati_guest_author_name') || '').trim().toLowerCase();
                        const currentUserName = (state.user && state.user.name) ? state.user.name.trim().toLowerCase() : storedGuestName;
                        const currentUserId = (state.user && state.user.id) ? state.user.id : 0;
                        const isSelfComment = (currentUserId > 0 && c.user_id == currentUserId) ||
                                              (currentUserName !== '' && c.author_name.trim().toLowerCase() === currentUserName) ||
                                              window._myPostedCommentIds.includes(c.id);

                        return `
                        <div class="blog-comment-item" id="comment-item-${c.id}">
                            <img src="${c.author_avatar || window.DEFAULT_USER_AVATAR}" class="blog-comment-avatar" onerror="this.src=window.DEFAULT_USER_AVATAR">
                            <div class="blog-comment-body">
                                <div class="blog-comment-header">
                                    <div>
                                        <span class="blog-comment-author">${escapeHtml(c.author_name)}</span>
                                        ${isSelfComment ? '<span style="font-size:0.7rem; font-weight:800; color:#3B82F6; background:rgba(59, 130, 246, 0.12); padding:2px 8px; border-radius:10px; margin-left:6px;"><i class="fa-solid fa-user"></i> You</span>' : ''}
                                    </div>
                                    <span class="blog-comment-date">${formatBlogDate(c.created_at)}</span>
                                </div>
                                <div class="blog-comment-text">${escapeHtml(c.comment)}</div>

                                <!-- Action Row -->
                                <div class="blog-comment-actions">
                                    <button class="comment-action-btn ${c.has_liked ? 'liked' : ''}" id="comment-like-btn-${c.id}" onclick="toggleCommentLike(${c.id})">
                                        <i class="${c.has_liked ? 'fa-solid' : 'fa-regular'} fa-heart"></i>
                                        <span id="comment-like-count-${c.id}">${formatCompactNumber(c.likes_count)}</span>
                                    </button>
                                    <button class="comment-action-btn reply-btn" onclick="toggleReplyBox(${c.id})">
                                        <i class="fa-solid fa-reply"></i> Reply
                                    </button>
                                    <button class="comment-action-btn report-btn" onclick="showReportCommentModal(${c.id}, '${escapeHtml(c.author_name).replace(/'/g, "\\'")}')" title="Report Comment" style="margin-left:auto; color:var(--gray-400); font-size:0.75rem;">
                                        <i class="fa-regular fa-flag"></i> Report
                                    </button>
                                </div>

                                <!-- Inline Reply Box -->
                                <div class="comment-reply-form" id="reply-box-${c.id}" style="display:none;">
                                    <div class="reply-form-card">
                                        <div class="reply-header">
                                            <span><i class="fa-solid fa-reply"></i> Replying to <strong>${escapeHtml(c.author_name)}</strong></span>
                                            <button type="button" class="btn-close-reply" onclick="toggleReplyBox(${c.id})"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                        <textarea id="reply-text-${c.id}" placeholder="Write a constructive reply..." rows="2" class="form-control reply-textarea"></textarea>
                                        ${!state.user ? `
                                            <div style="margin-bottom:8px;">
                                                <input type="text" id="reply-name-${c.id}" placeholder="Your Name" class="form-control form-control-sm">
                                            </div>
                                        ` : ''}
                                        <div class="reply-actions-row">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleReplyBox(${c.id})">Cancel</button>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="submitBlogReply(${post.id}, ${c.id})"><i class="fa-solid fa-paper-plane"></i> Post Reply</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Child Replies List -->
                                <div class="comment-replies-list" id="replies-list-${c.id}">
                                    ${c.replies && c.replies.length > 0 ? c.replies.map(r => {
                                        const isSelfReply = (currentUserId > 0 && r.user_id == currentUserId) ||
                                                            (currentUserName !== '' && r.author_name.trim().toLowerCase() === currentUserName) ||
                                                            window._myPostedCommentIds.includes(r.id);

                                        return `
                                        <div class="blog-comment-item reply-item" id="comment-item-${r.id}">
                                            <img src="${r.author_avatar || window.DEFAULT_USER_AVATAR}" class="blog-comment-avatar reply-avatar" onerror="this.src=window.DEFAULT_USER_AVATAR">
                                            <div class="blog-comment-body">
                                                <div class="blog-comment-header">
                                                    <div>
                                                        <span class="blog-comment-author">${escapeHtml(r.author_name)}</span>
                                                        ${isSelfReply ? '<span style="font-size:0.7rem; font-weight:800; color:#3B82F6; background:rgba(59, 130, 246, 0.12); padding:2px 8px; border-radius:10px; margin-left:6px;"><i class="fa-solid fa-user"></i> You</span>' : ''}
                                                    </div>
                                                    <span class="blog-comment-date">${formatBlogDate(r.created_at)}</span>
                                                </div>
                                                <div class="blog-comment-text">${escapeHtml(r.comment)}</div>
                                                <div class="blog-comment-actions">
                                                    <button class="comment-action-btn ${r.has_liked ? 'liked' : ''}" id="comment-like-btn-${r.id}" onclick="toggleCommentLike(${r.id})">
                                                        <i class="${r.has_liked ? 'fa-solid' : 'fa-regular'} fa-heart"></i>
                                                        <span id="comment-like-count-${r.id}">${formatCompactNumber(r.likes_count)}</span>
                                                    </button>
                                                    <button class="comment-action-btn report-btn" onclick="showReportCommentModal(${r.id}, '${escapeHtml(r.author_name).replace(/'/g, "\\'")}')" title="Report Reply" style="margin-left:auto; color:var(--gray-400); font-size:0.75rem;">
                                                        <i class="fa-regular fa-flag"></i> Report
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    }).join('') : ''}
                                </div>

                            </div>
                        </div>
                    `;
                    }).join('')}
                </div>
            </div>

            <!-- SECTION 8: RELATED ARTICLES -->
            ${related.length > 0 ? `
                <div class="blog-related-section">
                    <h3 class="blog-section-title">Related Guides & Articles</h3>
                    <div class="blog-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                        ${related.map(r => `
                            <div class="blog-card" onclick="openBlogArticle(${r.id})">
                                <img src="${r.cover_image || window.DEFAULT_BUSINESS_COVER}" class="blog-card-thumb" onerror="this.src=window.DEFAULT_BUSINESS_COVER">
                                <div class="blog-card-body">
                                    <span class="blog-card-cat">${escapeHtml(r.category)}</span>
                                    <h4 class="blog-card-title" style="font-size:0.95rem;">${escapeHtml(r.title)}</h4>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}

        </div>
    `;
}

// ── 3. INTERACTIVE ACTIONS ─────────────────────────────────────────────────
function toggleArticleLike(postId) {
    API.likeBlogPost(postId)
        .then(res => {
            if (res && res.success) {
                const isLiked = res.liked;
                const count = res.likes_count;

                const topBtn = document.getElementById('article-like-btn');
                const topCount = document.getElementById('article-like-count');
                const mainBtn = document.getElementById('article-like-main-btn');
                const mainCount = document.getElementById('article-like-main-count');

                if (topBtn) {
                    topBtn.classList.toggle('liked', isLiked);
                    const icon = topBtn.querySelector('i');
                    if (icon) icon.className = isLiked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
                }
                if (topCount) topCount.innerText = formatCompactNumber(count);

                if (mainBtn) {
                    mainBtn.classList.toggle('liked', isLiked);
                    const icon = mainBtn.querySelector('i');
                    if (icon) icon.className = isLiked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
                }
                if (mainCount) mainCount.innerText = formatCompactNumber(count) + ' Likes';
            }
        })
        .catch(err => {
            console.error("Like failed:", err);
        });
}

function toggleCommentLike(commentId) {
    API.likeBlogComment(commentId)
        .then(res => {
            if (res && res.success) {
                const btn = document.getElementById('comment-like-btn-' + commentId);
                const countEl = document.getElementById('comment-like-count-' + commentId);

                if (btn) {
                    btn.classList.toggle('liked', res.liked);
                    const icon = btn.querySelector('i');
                    if (icon) icon.className = res.liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
                }
                if (countEl) countEl.innerText = formatCompactNumber(res.likes_count);
            }
        })
        .catch(err => {
            console.error("Comment like failed:", err);
        });
}

function toggleReplyBox(commentId) {
    const box = document.getElementById('reply-box-' + commentId);
    if (box) {
        box.style.display = box.style.display === 'none' ? 'block' : 'none';
        if (box.style.display === 'block') {
            const input = document.getElementById('reply-text-' + commentId);
            if (input) input.focus();
        }
    }
}

function submitBlogReply(postId, parentId) {
    const textInput = document.getElementById('reply-text-' + parentId);
    const commentText = textInput ? textInput.value.trim() : '';
    if (!commentText) return;

    const data = { comment: commentText, parent_id: parentId };
    const nameInput = document.getElementById('reply-name-' + parentId);
    if (nameInput && nameInput.value.trim()) {
        data.author_name = nameInput.value.trim();
        sessionStorage.setItem('ohati_guest_author_name', data.author_name);
    }

    API.addBlogComment(postId, data)
        .then(res => {
            if (res && res.success && res.comment) {
                if (textInput) textInput.value = '';
                toggleReplyBox(parentId);

                window._myPostedCommentIds = window._myPostedCommentIds || [];
                window._myPostedCommentIds.push(res.comment.id);

                // Append new reply live to replies-list
                const list = document.getElementById('replies-list-' + parentId);
                if (list) {
                    const r = res.comment;
                    const newEl = document.createElement('div');
                    newEl.className = 'blog-comment-item reply-item';
                    newEl.id = 'comment-item-' + r.id;
                    newEl.style.animation = 'fadeIn 0.3s ease';
                    newEl.innerHTML = `
                        <img src="${r.author_avatar || window.DEFAULT_USER_AVATAR}" class="blog-comment-avatar reply-avatar" onerror="this.src=window.DEFAULT_USER_AVATAR">
                        <div class="blog-comment-body">
                            <div class="blog-comment-header">
                                <div>
                                    <span class="blog-comment-author">${escapeHtml(r.author_name)}</span>
                                    <span style="font-size:0.7rem; font-weight:800; color:#3B82F6; background:rgba(59, 130, 246, 0.12); padding:2px 8px; border-radius:10px; margin-left:6px;"><i class="fa-solid fa-user"></i> You</span>
                                </div>
                                <span class="blog-comment-date">Just now</span>
                            </div>
                            <div class="blog-comment-text">${escapeHtml(r.comment)}</div>
                            <div class="blog-comment-actions">
                                <button class="comment-action-btn" id="comment-like-btn-${r.id}" onclick="toggleCommentLike(${r.id})">
                                    <i class="fa-regular fa-heart"></i>
                                    <span id="comment-like-count-${r.id}">0</span>
                                </button>
                            </div>
                        </div>
                    `;
                    list.appendChild(newEl);
                }
                if (typeof showPushNotification === 'function') {
                    showPushNotification('Reply Posted', 'Your reply has been posted!');
                }
            } else {
                alert(res?.error || 'Failed to submit reply.');
            }
        })
        .catch(err => {
            console.error("Reply submission failed:", err);
        });
}

function submitBlogComment(e, postId) {
    e.preventDefault();
    const textInput = document.getElementById('comment-text-input');
    const commentText = textInput ? textInput.value.trim() : '';
    if (!commentText) return;

    const data = { comment: commentText };
    const nameInput = document.getElementById('comment-name-input');
    if (nameInput && nameInput.value.trim()) {
        data.author_name = nameInput.value.trim();
        sessionStorage.setItem('ohati_guest_author_name', data.author_name);
    }

    API.addBlogComment(postId, data)
        .then(res => {
            if (res && res.success && res.comment) {
                if (textInput) textInput.value = '';

                window._myPostedCommentIds = window._myPostedCommentIds || [];
                window._myPostedCommentIds.push(res.comment.id);

                // Prepend comment to UI live
                const list = document.getElementById('blog-comments-list');
                if (list) {
                    const c = res.comment;
                    const newEl = document.createElement('div');
                    newEl.className = 'blog-comment-item';
                    newEl.id = 'comment-item-' + c.id;
                    newEl.style.animation = 'fadeIn 0.3s ease';
                    newEl.innerHTML = `
                        <img src="${c.author_avatar || window.DEFAULT_USER_AVATAR}" class="blog-comment-avatar" onerror="this.src=window.DEFAULT_USER_AVATAR">
                        <div class="blog-comment-body">
                            <div class="blog-comment-header">
                                <div>
                                    <span class="blog-comment-author">${escapeHtml(c.author_name)}</span>
                                    <span style="font-size:0.7rem; font-weight:800; color:#3B82F6; background:rgba(59, 130, 246, 0.12); padding:2px 8px; border-radius:10px; margin-left:6px;"><i class="fa-solid fa-user"></i> You</span>
                                </div>
                                <span class="blog-comment-date">Just now</span>
                            </div>
                            <div class="blog-comment-text">${escapeHtml(c.comment)}</div>
                            <div class="blog-comment-actions">
                                <button class="comment-action-btn" id="comment-like-btn-${c.id}" onclick="toggleCommentLike(${c.id})">
                                    <i class="fa-regular fa-heart"></i>
                                    <span id="comment-like-count-${c.id}">0</span>
                                </button>
                                <button class="comment-action-btn" onclick="toggleReplyBox(${c.id})">
                                    <i class="fa-solid fa-reply"></i> Reply
                                </button>
                            </div>
                            <div class="comment-reply-form" id="reply-box-${c.id}" style="display:none;">
                                <textarea id="reply-text-${c.id}" placeholder="Write a reply to ${escapeHtml(c.author_name)}..." rows="2" class="form-control"></textarea>
                                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleReplyBox(${c.id})">Cancel</button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="submitBlogReply(${postId}, ${c.id})"><i class="fa-solid fa-paper-plane"></i> Submit Reply</button>
                                </div>
                            </div>
                            <div class="comment-replies-list" id="replies-list-${c.id}"></div>
                        </div>
                    `;
                    if (list.firstChild) {
                        list.insertBefore(newEl, list.firstChild);
                    } else {
                        list.appendChild(newEl);
                    }
                }
                if (typeof showPushNotification === 'function') {
                    showPushNotification('Comment Posted', 'Thank you! Your comment has been added.');
                }
            } else {
                alert(res?.error || 'Failed to submit comment.');
            }
        })
        .catch(err => {
            console.error("Comment submission failed:", err);
        });
}

function triggerArticleShare(postId, title) {
    const url = window.location.origin + window.location.pathname.replace(/[^\/]*$/, '') + 'blog.php?id=' + postId;

    // Incremental share stat
    API.shareBlogPost(postId)
        .then(() => {
            if (window.blogState.activePost && window.blogState.activePost.id === postId) {
                window.blogState.activePost.shares_count = (window.blogState.activePost.shares_count || 840) + 1;
                const shareBtn = document.querySelector('.blog-share-main-btn');
                if (shareBtn) {
                    shareBtn.innerHTML = `<i class="fa-solid fa-share-nodes"></i> ${formatCompactNumber(window.blogState.activePost.shares_count)} Shares`;
                }
            }
        })
        .catch(() => {});

    if (navigator.share) {
        navigator.share({
            title: title || (window.blogState.activePost ? window.blogState.activePost.title : 'Ohati Wedding Guide'),
            text: title || (window.blogState.activePost ? window.blogState.activePost.title : 'Ohati Wedding Guide'),
            url: url
        }).catch(() => {});
    } else {
        // Fallback: Copy to clipboard + notification
        navigator.clipboard.writeText(url).then(() => {
            if (typeof showPushNotification === 'function') {
                showPushNotification('Link Copied', 'Article URL copied to your clipboard!');
            } else {
                alert('Article link copied to clipboard: ' + url);
            }
        }).catch(() => {
            prompt('Copy article link below:', url);
        });
    }
}

window._pendingReportCommentId = 0;

function reportBlogComment(commentId) {}
function openBlogReportModal(commentId) {}
function closeBlogReportModal() {}
function submitBlogCommentReport() {}
function blockBlogUser(authorName, userId = 0, authorAvatar = '') {}
function openBlogBlockModal(authorName, authorAvatar = '') {}
function closeBlogBlockModal() {}
function executeBlogBlockUser() {}

// ── HELPERS ────────────────────────────────────────────────────────────────
function formatBlogDate(dateStr) {
    if (!dateStr) return 'Recently';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch(e) {
        return dateStr;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderSkeletonCardsHTML(count = 4) {
    let html = '<div class="blog-grid">';
    for (let i = 0; i < count; i++) {
        html += `
            <div class="blog-card" style="pointer-events:none;">
                <div class="skeleton" style="height:180px; width:100%; border-radius:12px 12px 0 0;"></div>
                <div class="blog-card-body" style="padding:16px;">
                    <div class="skeleton skeleton-text" style="width:40%; height:12px; margin-bottom:10px;"></div>
                    <div class="skeleton skeleton-title" style="width:85%; height:18px; margin-bottom:8px;"></div>
                    <div class="skeleton skeleton-text" style="width:95%; height:14px; margin-bottom:6px;"></div>
                    <div class="skeleton skeleton-text" style="width:70%; height:14px;"></div>
                </div>
            </div>
        `;
    }
    html += '</div>';
    return html;
}

function formatCompactNumber(num) {
    num = parseInt(num) || 0;
    if (num >= 1000000) return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    return num.toString();
}

