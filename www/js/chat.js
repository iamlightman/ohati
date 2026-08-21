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
            if (typeof showPushNotification === 'function') {
                showPushNotification('Send Failed', err.message || 'Failed to send message.');
            }
        });
};

window.triggerChatAttachment = function() {
    const fileInput = document.getElementById('chat-file-input');
    if (fileInput) fileInput.click();
};

// Global pending upload items map for chat retry functionality
window._pendingChatUploads = window._pendingChatUploads || {};

window.handleChatFileSelected = function(inputEl) {
    if (!inputEl || !inputEl.files || inputEl.files.length === 0 || !state.activeChatVendorId) return;
    const file = inputEl.files[0];
    inputEl.value = ''; // Reset input for re-selection
    
    // Validate size (20MB limit)
    const max_size = 20 * 1024 * 1024;
    if (file.size > max_size) {
        if (typeof showPushNotification === 'function') {
            showPushNotification("Upload Limit", "File size cannot exceed 20MB.");
        }
        return;
    }

    const tempId = 'chat_upload_' + Date.now();
    const isImage = !!(file.type && file.type.startsWith('image/'));
    const fileName = file.name || 'Attachment';

    // Save metadata & file reference in global state
    window._pendingChatUploads[tempId] = {
        tempId: tempId,
        file: file,
        vendorId: state.activeChatVendorId,
        isImage: isImage,
        fileName: fileName,
        previewSrc: '',
        status: 'uploading', // 'uploading' | 'failed' | 'done'
        progress: 0,
        errorMsg: ''
    };

    if (isImage) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (window._pendingChatUploads[tempId]) {
                window._pendingChatUploads[tempId].previewSrc = e.target.result;
            }
            window.renderPendingChatBubble(tempId);
            window.startChatFileUpload(tempId);
        };
        reader.readAsDataURL(file);
    } else {
        window.renderPendingChatBubble(tempId);
        window.startChatFileUpload(tempId);
    }
};

window.renderPendingChatBubble = function(tempId) {
    const item = window._pendingChatUploads[tempId];
    if (!item) return;

    const container = document.getElementById('chat-messages-container');
    if (!container) return;

    let pendingRow = document.getElementById(tempId);
    if (!pendingRow) {
        pendingRow = document.createElement('div');
        pendingRow.id = tempId;
        pendingRow.className = 'msg-row outgoing pending-chat-upload';
        pendingRow.style.cssText = 'display:flex; align-items:flex-end; justify-content:flex-end; gap:8px; width:100%; margin-bottom:10px;';
        container.appendChild(pendingRow);
    }

    const isFailed = (item.status === 'failed');

    pendingRow.innerHTML = `
        <div class="msg-bubble msg-user" style="margin:0; min-width:220px; max-width:290px; position:relative; overflow:hidden; background:rgba(27, 43, 75, 0.95); border:1.5px solid ${isFailed ? '#EF4444' : 'var(--accent, #F2A735)'}; border-radius:14px; padding:10px; box-shadow:0 4px 16px rgba(0,0,0,0.18);">
            <div class="upload-preview-box" style="position:relative; border-radius:10px; overflow:hidden; margin-bottom:8px; background:#0F1923; min-height:${item.isImage ? '130px' : 'auto'}; display:flex; align-items:center; justify-content:center;">
                ${item.isImage && item.previewSrc ? `
                    <img src="${item.previewSrc}" style="width:100%; max-height:180px; object-fit:cover; display:block; filter:${isFailed ? 'brightness(0.5)' : 'brightness(0.85)'};">
                ` : `
                    <div style="display:flex; align-items:center; gap:10px; padding:12px; width:100%; background:rgba(255,255,255,0.06); border-radius:8px;">
                        <i class="fa-solid fa-file-lines" style="font-size:1.8rem; color:${isFailed ? '#EF4444' : 'var(--accent, #F2A735)'};"></i>
                        <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:170px; font-size:0.8rem; font-weight:700; color:#fff;">${escapeHtml(item.fileName)}</div>
                    </div>
                `}
                <div id="overlay-${tempId}" style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; background:${isFailed ? 'rgba(15,23,42,0.85)' : 'rgba(0,0,0,0.55)'}; backdrop-filter:blur(3px);">
                    ${isFailed ? `
                        <i class="fa-solid fa-circle-xmark" style="font-size:2rem; color:#EF4444; margin-bottom:4px;"></i>
                        <button onclick="retryChatFileUpload('${tempId}')" style="background:#EF4444; color:#FFF; border:none; padding:7px 16px; border-radius:20px; font-size:0.78rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:6px; box-shadow:0 3px 12px rgba(239,68,68,0.4); transition:all 0.2s;">
                            <i class="fa-solid fa-rotate-right"></i> Retry Upload
                        </button>
                    ` : `
                        <i class="fa-solid fa-spinner fa-spin" style="font-size:1.6rem; color:var(--accent, #F2A735); margin-bottom:2px;"></i>
                        <span id="pct-${tempId}" style="font-size:0.78rem; font-weight:800; color:#fff; text-shadow:0 1px 3px rgba(0,0,0,0.8);">${item.progress || 0}%</span>
                    `}
                </div>
            </div>

            <!-- Progress Bar -->
            <div style="width:100%; height:6px; background:rgba(255,255,255,0.18); border-radius:4px; overflow:hidden; margin-bottom:6px;">
                <div id="bar-${tempId}" style="width:${isFailed ? '100%' : (item.progress || 0) + '%'}; height:100%; background:${isFailed ? '#EF4444' : 'linear-gradient(90deg, var(--accent, #F2A735), #10B981)'}; transition:width 0.15s ease-out;"></div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.7rem; color:rgba(255,255,255,0.9);">
                <span id="status-${tempId}">
                    ${isFailed ? `
                        <span style="color:#EF4444; font-weight:800;"><i class="fa-solid fa-circle-exclamation"></i> Upload Failed</span>
                    ` : `
                        <i class="fa-solid fa-cloud-arrow-up" style="color:var(--accent, #F2A735);"></i> Uploading ${item.progress || 0}%...
                    `}
                </span>
                <span style="font-size:0.65rem; opacity:0.8;">Sending</span>
            </div>
        </div>
    `;

    container.scrollTop = container.scrollHeight;
};

window.retryChatFileUpload = function(tempId) {
    const item = window._pendingChatUploads[tempId];
    if (!item) return;

    item.status = 'uploading';
    item.progress = 0;
    window.renderPendingChatBubble(tempId);
    window.startChatFileUpload(tempId);
};

window.startChatFileUpload = function(tempId) {
    const item = window._pendingChatUploads[tempId];
    if (!item || !item.file) return;

    const executeXhr = (fileToUpload) => {
        const formData = new FormData();
        formData.append('file', fileToUpload);
        const token = localStorage.getItem('ohati_auth_token');
        if (token) formData.append('auth_token', token);

        const apiUrl = (window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=upload_chat_file';
        const xhr = new XMLHttpRequest();

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = Math.min(99, Math.round((e.loaded / e.total) * 100));
                item.progress = percent;
                const bar = document.getElementById(`bar-${tempId}`);
                const pct = document.getElementById(`pct-${tempId}`);
                const status = document.getElementById(`status-${tempId}`);
                if (bar) bar.style.width = percent + '%';
                if (pct) pct.textContent = percent + '%';
                if (status) status.innerHTML = `<i class="fa-solid fa-cloud-arrow-up" style="color:var(--accent, #F2A735);"></i> Uploading ${percent}%`;
            }
        };

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success && res.url) {
                        item.status = 'done';
                        const bar = document.getElementById(`bar-${tempId}`);
                        const pct = document.getElementById(`pct-${tempId}`);
                        if (bar) bar.style.width = '100%';
                        if (pct) pct.textContent = '100%';

                        const messageType = res.type || (item.isImage ? 'image' : 'pdf');

                        API.sendMessage(item.vendorId, res.url, messageType)
                            .then(() => {
                                delete window._pendingChatUploads[tempId];
                                const pendingEl = document.getElementById(tempId);
                                if (pendingEl) pendingEl.remove();
                                API.getChatHistory(item.vendorId).then(history => {
                                    if (typeof updateChatMessages === 'function') updateChatMessages(history);
                                });
                            })
                            .catch(err => {
                                markChatUploadFailed(tempId, err.message || 'Failed to send attachment message.');
                            });
                    } else {
                        markChatUploadFailed(tempId, res.error || 'Upload failed');
                    }
                } catch(e) {
                    markChatUploadFailed(tempId, 'Invalid server response');
                }
            } else {
                markChatUploadFailed(tempId, 'Server returned error ' + xhr.status);
            }
        };

        xhr.onerror = function() {
            markChatUploadFailed(tempId, 'Network error during upload');
        };

        xhr.ontimeout = function() {
            markChatUploadFailed(tempId, 'Upload timed out');
        };

        xhr.open('POST', apiUrl, true);
        if (token) {
            xhr.setRequestHeader('Authorization', `Bearer ${token}`);
        }
        xhr.withCredentials = true;
        xhr.send(formData);
    };

    if (window.compressImageFileBeforeUpload && item.isImage) {
        window.compressImageFileBeforeUpload(item.file, 1600, 1600, 0.8, (compressed) => {
            executeXhr(compressed);
        });
    } else {
        executeXhr(item.file);
    }
};

function markChatUploadFailed(tempId, errMsg) {
    const item = window._pendingChatUploads[tempId];
    if (item) {
        item.status = 'failed';
        item.errorMsg = errMsg;
    }
    if (typeof window.renderPendingChatBubble === 'function') {
        window.renderPendingChatBubble(tempId);
    }
    if (typeof showPushNotification === 'function') {
        showPushNotification('Upload Failed', errMsg || 'File upload failed. Tap Retry to try again.');
    }
}

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
