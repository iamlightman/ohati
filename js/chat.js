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
    const formData = new FormData();
    formData.append('file', file);

    showPushNotification('Uploading File', 'Sending attachment...');

    const apiUrl = (window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=upload_chat_file';
    fetch(apiUrl, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
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
    })
    .catch(err => {
        showPushNotification('Upload Error', err.message || 'Could not upload attachment.');
    });
};
