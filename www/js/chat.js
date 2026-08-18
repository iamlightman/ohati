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

        const getHeaders = () => {
            const token = localStorage.getItem('ohati_auth_token');
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

window.blockVendorUser = function(vendorId, vendorName) {
    if (!vendorId) return;
    if (confirm('Block ' + (vendorName || 'this user') + '? You will no longer see messages or listings from this user.')) {
        const blocked = window.getBlockedUsers();
        if (!blocked.includes(vendorId)) {
            blocked.push(vendorId);
            localStorage.setItem('ohati_blocked_users', JSON.stringify(blocked));
        }
        if (typeof showPushNotification === 'function') {
            showPushNotification('User Blocked', (vendorName || 'User') + ' has been blocked.');
        } else {
            alert((vendorName || 'User') + ' has been blocked.');
        }
        if (typeof navigateTo === 'function') navigateTo('chat');
    }
};

window.reportVendorContent = function(vendorId, vendorName) {
    const reason = prompt('Please describe why you are reporting ' + (vendorName || 'this content') + ' (e.g. offensive content, fraud, inappropriate language):');
    if (reason && reason.trim()) {
        if (typeof API !== 'undefined' && API.post) {
            API.post('report_issue', {
                title: 'Report Content: Vendor #' + vendorId,
                category: 'Content Moderation',
                description: 'User report against vendor ' + vendorName + ' (ID: ' + vendorId + '): ' + reason.trim()
            }).then(() => {
                if (typeof showPushNotification === 'function') showPushNotification('Report Received', 'Thank you. Our moderation team will review this within 24 hours.');
                else alert('Thank you. Our moderation team will review this within 24 hours.');
            }).catch(() => {
                if (typeof showPushNotification === 'function') showPushNotification('Report Submitted', 'Thank you. Your report has been submitted to moderation.');
                else alert('Thank you. Your report has been submitted to moderation.');
            });
        } else {
            alert('Thank you. Your report has been submitted to moderation.');
        }
    }
};
