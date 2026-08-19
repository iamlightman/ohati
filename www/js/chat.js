// js/chat.js — Production Real-Time Messaging & Voice Notes Module

window.initChatModule = function(partnerId) {
    if (state.chatInterval) clearInterval(state.chatInterval);
    state.activeChatVendorId = partnerId;

    // Fetch initial chat history
    API.getChatHistory(partnerId).then(history => {
        if (typeof updateChatMessages === 'function') updateChatMessages(history);
    });

    // Poll for new messages every 3 seconds
    state.chatInterval = setInterval(() => {
        if (!state.activeChatVendorId) return;
        API.getChatHistory(state.activeChatVendorId).then(history => {
            if (typeof updateChatMessages === 'function') updateChatMessages(history);
        });
    }, 3000);
};

window.sendTextMessage = function() {
    const input = document.getElementById('chat-input-field');
    if (!input || !input.value.trim() || !state.activeChatVendorId) return;
    const msg = input.value.trim();
    input.value = '';

    API.sendMessage(state.activeChatVendorId, msg, 'text')
        .then(res => {
            API.getChatHistory(state.activeChatVendorId).then(history => {
                if (typeof updateChatMessages === 'function') updateChatMessages(history);
            });
        })
        .catch(err => {
            showPushNotification('Send Failed', err.message || 'Failed to send message.');
        });
};

window.triggerChatAttachment = function() {
    const fileInput = document.getElementById('chat-file-input');
    if (fileInput) fileInput.click();
};

window.handleChatFileSelected = function(inputEl) {
    if (!inputEl.files || inputEl.files.length === 0 || !state.activeChatVendorId) return;
    const file = inputEl.files[0];
    
    // Validate size (20MB limit)
    const max_size = 20 * 1024 * 1024;
    if (file.size > max_size) {
        showPushNotification("Upload Limit", "File size cannot exceed 20MB.");
        inputEl.value = '';
        return;
    }

    showPushNotification('Uploading File', 'Sending attachment...');

    const processUpload = (fileToUpload) => {
        const formData = new FormData();
        formData.append('file', fileToUpload);
        const token = localStorage.getItem('ohati_auth_token');
        if (token) formData.append('auth_token', token);

        const getHeaders = () => {
            return token ? { 'Authorization': `Bearer ${token}` } : {};
        };

        const apiUrl = (window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=upload_chat_file';
        fetch(apiUrl, {
            method: 'POST',
            credentials: 'include',
            headers: getHeaders(),
            body: formData
        })
        .then(r => {
            if (!r.ok) throw new Error("Server error (" + r.status + ")");
            return r.json();
        })
        .then(res => {
            if (res.success) {
                return API.sendMessage(state.activeChatVendorId, res.url, res.type || 'image');
            } else {
                throw new Error(res.error || 'Upload failed');
            }
        })
        .then(() => {
            API.getChatHistory(state.activeChatVendorId).then(history => {
                if (typeof updateChatMessages === 'function') updateChatMessages(history);
            });
            inputEl.value = '';
        })
        .catch(err => {
            showPushNotification('Upload Error', err.message || 'Could not upload attachment.');
            inputEl.value = '';
        });
    };

    if (window.compressImageFileBeforeUpload && file.type && file.type.startsWith('image/')) {
        window.compressImageFileBeforeUpload(file, 1600, 1600, 0.8, (compressed) => {
            processUpload(compressed);
        });
    } else {
        processUpload(file);
    }
};



window.getBlockedUsers = function() {
    try {
        return JSON.parse(localStorage.getItem('ohati_blocked_users') || '[]');
    } catch(e) { return []; }
};

window.openBlockUserModal = function(targetId, targetName, targetAvatar, targetRole) {
    if (!targetId) return;
    if (!state.user) {
        if (typeof openLoginModal === 'function') openLoginModal();
        return;
    }
    
    const blockedList = window.getBlockedUsers();
    const isBlocked = blockedList.includes(targetId) || blockedList.includes(Number(targetId));
    
    if (isBlocked) {
        // Unblock Pop Up Modal
        const html = `
            <div style="padding:28px 22px; text-align:center;">
                <div style="width:68px; height:68px; border-radius:50%; background:rgba(16, 185, 129, 0.12); color:#10B981; display:flex; align-items:center; justify-content:center; font-size:2rem; margin:0 auto 16px; box-shadow:0 6px 18px rgba(16,185,129,0.18);">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <h3 style="margin:0 0 6px 0; font-size:1.25rem; font-weight:800; color:var(--gray-900,#111827);">Unblock ${targetName || 'User'}?</h3>
                <p style="margin:0 0 20px 0; font-size:0.83rem; color:var(--gray-500,#6B7280); line-height:1.5;">
                    Unblocking will allow <strong>${targetName || 'this user'}</strong> to view your profile, send you messages, and appear in your active chats.
                </p>
                <div style="display:flex; gap:10px; margin-top:24px;">
                    <button class="btn btn-outline btn-full" onclick="closeModal()" style="padding:12px; font-weight:700;">Cancel</button>
                    <button class="btn btn-primary btn-full" id="confirm-unblock-btn" onclick="submitUnblockUserAction(${targetId}, '${(targetName||'').replace(/'/g, "\\'")}')" style="padding:12px; font-weight:700; background:#10B981; border-color:#10B981; color:#fff;">
                        <i class="fa-solid fa-unlock" style="margin-right:6px;"></i> Unblock User
                    </button>
                </div>
            </div>
        `;
        openModal(html);
        return;
    }

    const avatarUrl = targetAvatar || "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>";
    const roleLabel = targetRole || 'Vendor';

    const html = `
        <div style="padding:24px 20px; text-align:left;">
            <!-- Header Icon & Title -->
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                <div style="width:48px; height:48px; border-radius:50%; background:rgba(239, 68, 68, 0.12); color:#EF4444; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:var(--gray-900,#111827);">Block & Report User</h3>
                    <p style="margin:2px 0 0 0; font-size:0.75rem; color:var(--gray-500,#6B7280);">Prevent unwanted messages & submit reports</p>
                </div>
            </div>

            <!-- Target User Card -->
            <div style="display:flex; align-items:center; gap:12px; padding:12px; background:var(--gray-100,#F8FAFC); border-radius:14px; margin-bottom:18px; border:1px solid var(--gray-200,#E2E8F0);">
                <img src="${avatarUrl}" style="width:44px; height:44px; border-radius:50%; object-fit:cover;" alt="${targetName || 'User'}">
                <div style="overflow:hidden;">
                    <div style="font-weight:800; font-size:0.92rem; color:var(--gray-900,#111827); text-overflow:ellipsis; white-space:nowrap; overflow:hidden;">${targetName || 'User'}</div>
                    <div style="font-size:0.72rem; color:var(--gray-500,#6B7280); font-weight:600;">${roleLabel} Profile</div>
                </div>
            </div>

            <!-- Block Reason Selection -->
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:0.78rem; font-weight:700; color:var(--gray-700,#374151); margin-bottom:6px;">Select reason for blocking:</label>
                <select id="block-reason-select" style="width:100%; padding:12px; border-radius:12px; border:1px solid var(--gray-300,#CBD5E1); background:#fff; font-size:0.85rem; color:var(--gray-800,#1E293B); outline:none;" onchange="toggleBlockNotesField(this.value)">
                    <option value="Inappropriate Messages or Behavior">Inappropriate Messages or Behavior</option>
                    <option value="Spam or Unsolicited Advertisements">Spam or Unsolicited Advertisements</option>
                    <option value="Suspected Fraud, Scam or Fake Profile">Suspected Fraud, Scam or Fake Profile</option>
                    <option value="Harassment or Offensive Language">Harassment or Offensive Language</option>
                    <option value="No Longer Wish to Communicate">No Longer Wish to Communicate</option>
                    <option value="Other Reason">Other Reason</option>
                </select>
            </div>

            <!-- Additional Notes / Details -->
            <div id="block-notes-wrap" style="margin-bottom:16px; display:none;">
                <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--gray-700,#374151); margin-bottom:4px;">Additional Details (Optional):</label>
                <textarea id="block-reason-notes" rows="2" placeholder="Provide extra details for moderation team..." style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--gray-300,#CBD5E1); font-size:0.82rem; outline:none; box-sizing:border-box; resize:none;"></textarea>
            </div>

            <!-- Checkbox: Report to Admin -->
            <label style="display:flex; align-items:center; gap:8px; font-size:0.78rem; color:var(--gray-700,#374151); margin-bottom:18px; cursor:pointer; font-weight:600;">
                <input type="checkbox" id="block-report-checkbox" checked style="accent-color:#EF4444; width:16px; height:16px; cursor:pointer;">
                <span>Submit a confidential report to Ohati Moderation</span>
            </label>

            <!-- Warning Notice -->
            <div style="background:rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.2); border-radius:12px; padding:10px 12px; margin-bottom:20px; font-size:0.74rem; color:var(--gray-700,#374151); line-height:1.4;">
                <i class="fa-solid fa-shield-halved" style="color:#EF4444; margin-right:6px;"></i>
                This user will be blocked immediately and hidden from your chat inbox and feeds.
            </div>

            <!-- Action Buttons -->
            <div style="display:flex; gap:10px;">
                <button class="btn btn-outline btn-full" onclick="closeModal()" style="padding:12px; font-weight:700;">Cancel</button>
                <button class="btn btn-primary btn-full" id="confirm-block-btn" onclick="submitBlockUserAction(${targetId}, '${(targetName||'').replace(/'/g, "\\'")}')" style="padding:12px; font-weight:700; background:#EF4444; border-color:#EF4444; color:#fff;">
                    <i class="fa-solid fa-ban" style="margin-right:6px;"></i> Block User
                </button>
            </div>
        </div>
    `;

    openModal(html);
};

window.blockVendorUser = function(vendorId, vendorName, avatar, category) {
    window.openBlockUserModal(vendorId, vendorName, avatar, category);
};

window.toggleBlockNotesField = function(val) {
    const wrap = document.getElementById('block-notes-wrap');
    if (wrap) wrap.style.display = (val === 'Other Reason') ? 'block' : 'none';
};

window.submitBlockUserAction = function(targetId, targetName) {
    const btn = document.getElementById('confirm-block-btn');
    const reasonSelect = document.getElementById('block-reason-select');
    const notesInput = document.getElementById('block-reason-notes');
    const reportCb = document.getElementById('block-report-checkbox');

    const reason = reasonSelect ? reasonSelect.value : 'User Blocked';
    const notes = notesInput ? notesInput.value.trim() : '';
    const isReported = reportCb ? reportCb.checked : true;

    ActionLock.execute(btn, 'Blocking...', async () => {
        // Save locally
        const blocked = window.getBlockedUsers();
        if (!blocked.includes(targetId) && !blocked.includes(Number(targetId))) {
            blocked.push(Number(targetId));
            localStorage.setItem('ohati_blocked_users', JSON.stringify(blocked));
        }

        // Notify backend
        try {
            if (typeof API !== 'undefined' && API.post) {
                await API.post('block_user', {
                    target_id: targetId,
                    reason: reason,
                    notes: notes,
                    report: isReported ? 1 : 0
                });
            }
        } catch(e) {}

        showPushNotification('User Blocked', `${targetName || 'User'} has been blocked successfully.`);
        closeModal();

        // Refresh active chat/screen
        if (state.activeChatVendorId == targetId) {
            if (typeof closeActiveChat === 'function') closeActiveChat();
        }
        if (typeof navigateTo === 'function') navigateTo('chat');
    });
};

window.submitUnblockUserAction = function(targetId, targetName) {
    const btn = document.getElementById('confirm-unblock-btn');

    ActionLock.execute(btn, 'Unblocking...', async () => {
        let blocked = window.getBlockedUsers();
        blocked = blocked.filter(id => Number(id) !== Number(targetId));
        localStorage.setItem('ohati_blocked_users', JSON.stringify(blocked));

        try {
            if (typeof API !== 'undefined' && API.post) {
                await API.post('unblock_user', { target_id: targetId });
            }
        } catch(e) {}

        showPushNotification('User Unblocked', `${targetName || 'User'} has been unblocked.`);
        closeModal();

        if (typeof navigateTo === 'function') navigateTo('chat');
    });
};

window.reportVendorContent = function(vendorId, vendorName) {
    window.openBlockUserModal(vendorId, vendorName);
};
