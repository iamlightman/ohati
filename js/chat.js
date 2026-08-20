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

    const tempId = 'chat_upload_' + Date.now();
    const isImage = file.type && file.type.startsWith('image/');
    const fileName = file.name || 'Attachment';
    const container = document.getElementById('chat-messages-container');

    // Render real-time inline progress bubble inside chat window
    const createPendingBubble = (previewSrc) => {
        if (!container) return;
        const pendingRow = document.createElement('div');
        pendingRow.id = tempId;
        pendingRow.className = 'msg-row outgoing pending-chat-upload';
        pendingRow.style.cssText = 'display:flex; align-items:flex-end; justify-content:flex-end; gap:8px; width:100%; margin-bottom:8px;';
        
        pendingRow.innerHTML = `
            <div class="msg-bubble msg-user" style="margin:0; min-width:210px; max-width:280px; position:relative; overflow:hidden; background:rgba(27, 43, 75, 0.92); border:1.5px solid var(--accent, #F2A735); border-radius:14px; padding:10px; box-shadow:0 4px 16px rgba(0,0,0,0.15);">
                <div class="upload-preview-box" style="position:relative; border-radius:10px; overflow:hidden; margin-bottom:8px; background:#0F1923; min-height:${isImage ? '130px' : 'auto'}; display:flex; align-items:center; justify-content:center;">
                    ${isImage && previewSrc ? `
                        <img src="${previewSrc}" style="width:100%; max-height:180px; object-fit:cover; display:block; filter:brightness(0.75);">
                    ` : `
                        <div style="display:flex; align-items:center; gap:10px; padding:12px; width:100%; background:rgba(255,255,255,0.06); border-radius:8px;">
                            <i class="fa-solid fa-file-lines" style="font-size:1.8rem; color:var(--accent, #F2A735);"></i>
                            <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:160px; font-size:0.78rem; font-weight:700; color:#fff;">${fileName}</div>
                        </div>
                    `}
                    <div id="overlay-${tempId}" style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; background:rgba(0,0,0,0.45); backdrop-filter:blur(2px);">
                        <i class="fa-solid fa-spinner fa-spin" style="font-size:1.6rem; color:var(--accent, #F2A735); margin-bottom:2px;"></i>
                        <span id="pct-${tempId}" style="font-size:0.75rem; font-weight:800; color:#fff; text-shadow:0 1px 3px rgba(0,0,0,0.8);">0%</span>
                    </div>
                </div>

                <!-- Real-time Progress Bar -->
                <div style="width:100%; height:6px; background:rgba(255,255,255,0.18); border-radius:4px; overflow:hidden; margin-bottom:6px;">
                    <div id="bar-${tempId}" style="width:0%; height:100%; background:linear-gradient(90deg, var(--accent, #F2A735), #10B981); transition:width 0.15s ease-out;"></div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.68rem; color:rgba(255,255,255,0.9);">
                    <span id="status-${tempId}"><i class="fa-solid fa-cloud-arrow-up" style="color:var(--accent, #F2A735);"></i> Uploading 0%...</span>
                    <span style="font-size:0.62rem; opacity:0.75;">Sending</span>
                </div>
            </div>
        `;
        container.appendChild(pendingRow);
        container.scrollTop = container.scrollHeight;
    };

    if (isImage) {
        const reader = new FileReader();
        reader.onload = function(e) {
            createPendingBubble(e.target.result);
        };
        reader.readAsDataURL(file);
    } else {
        createPendingBubble('');
    }

    const executeXhrUpload = (fileToUpload) => {
        const formData = new FormData();
        formData.append('file', fileToUpload);
        const token = localStorage.getItem('ohati_auth_token');
        if (token) formData.append('auth_token', token);

        const apiUrl = (window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=upload_chat_file';
        const xhr = new XMLHttpRequest();

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = Math.min(99, Math.round((e.loaded / e.total) * 100));
                const bar = document.getElementById(`bar-${tempId}`);
                const pct = document.getElementById(`pct-${tempId}`);
                const status = document.getElementById(`status-${tempId}`);
                if (bar) bar.style.width = percent + '%';
                if (pct) pct.textContent = percent + '%';
                if (status) status.innerHTML = `<i class="fa-solid fa-cloud-arrow-up" style="color:var(--accent, #F2A735);"></i> Uploading ${percent}%`;
            }
        };

        xhr.onload = function() {
            inputEl.value = '';
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success && res.url) {
                        const bar = document.getElementById(`bar-${tempId}`);
                        const pct = document.getElementById(`pct-${tempId}`);
                        if (bar) bar.style.width = '100%';
                        if (pct) pct.textContent = '100%';

                        API.sendMessage(state.activeChatVendorId, res.url, res.type || (isImage ? 'image' : 'pdf'))
                            .then(() => {
                                const pendingEl = document.getElementById(tempId);
                                if (pendingEl) pendingEl.remove();
                                API.getChatHistory(state.activeChatVendorId).then(history => {
                                    if (typeof updateChatMessages === 'function') updateChatMessages(history);
                                });
                            })
                            .catch(err => {
                                showUploadError(err.message || 'Failed to send message.');
                            });
                    } else {
                        showUploadError(res.error || 'Upload failed');
                    }
                } catch(e) {
                    showUploadError('Invalid response from server');
                }
            } else {
                showUploadError('Server returned error status ' + xhr.status);
            }
        };

        xhr.onerror = function() {
            inputEl.value = '';
            showUploadError('Network connection failed during upload.');
        };

        xhr.open('POST', apiUrl, true);
        if (token) {
            xhr.setRequestHeader('Authorization', `Bearer ${token}`);
        }
        xhr.withCredentials = true;
        xhr.send(formData);
    };

    const showUploadError = (errMsg) => {
        const status = document.getElementById(`status-${tempId}`);
        const overlay = document.getElementById(`overlay-${tempId}`);
        if (status) status.innerHTML = `<span style="color:#EF4444; font-weight:700;"><i class="fa-solid fa-circle-exclamation"></i> Upload Failed</span>`;
        if (overlay) overlay.innerHTML = `<i class="fa-solid fa-circle-xmark" style="font-size:1.8rem; color:#EF4444;"></i><span style="font-size:0.7rem; color:#fff;">Failed</span>`;
        showPushNotification('Upload Error', errMsg);
    };

    if (window.compressImageFileBeforeUpload && isImage) {
        window.compressImageFileBeforeUpload(file, 1600, 1600, 0.8, (compressed) => {
            executeXhrUpload(compressed);
        });
    } else {
        executeXhrUpload(file);
    }
};



window.getBlockedUsers = function() {
    try {
        return JSON.parse(localStorage.getItem('ohati_blocked_users') || '[]');
    } catch(e) { return []; }
};

window._pendingChatBlock = { vendorId: 0, vendorName: '' };
window._pendingChatReport = { vendorId: 0, vendorName: '' };

window.blockVendorUser = function(vendorId, vendorName) {};
function openChatBlockModal(vendorName) {}
function closeChatBlockModal() {}
function executeChatBlockUser() {}
window.reportVendorContent = function(vendorId, vendorName) {};
function openChatReportModal(vendorName) {}
function closeChatReportModal() {}
function submitChatUserReport() {}
