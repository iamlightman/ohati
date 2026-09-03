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
    
    // Strict 10 MB limit enforcement
    const max_size = 10 * 1024 * 1024; // 10MB
    if (file.size > max_size) {
        if (typeof showPushNotification === 'function') {
            showPushNotification("Upload Limit", "File size cannot exceed the 10 MB limit.");
        } else {
            alert("File size exceeds maximum allowed limit of 10 MB.");
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
        fileSize: file.size,
        duration: 0,
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
    const isAudio = item.file && item.file.type && item.file.type.startsWith('audio/');

    pendingRow.innerHTML = `
        <div class="msg-bubble msg-user" style="margin:0; min-width:220px; max-width:290px; position:relative; overflow:hidden; background:rgba(27, 43, 75, 0.95); border:1.5px solid ${isFailed ? '#EF4444' : 'var(--accent, #F2A735)'}; border-radius:14px; padding:10px; box-shadow:0 4px 16px rgba(0,0,0,0.18);">
            <div class="upload-preview-box" style="position:relative; border-radius:10px; overflow:hidden; margin-bottom:8px; background:#0F1923; min-height:${item.isImage ? '130px' : '60px'}; display:flex; align-items:center; justify-content:center;">
                ${item.isImage && item.previewSrc ? `
                    <img src="${item.previewSrc}" style="width:100%; max-height:180px; object-fit:cover; display:block; filter:${isFailed ? 'brightness(0.5)' : 'brightness(0.85)'};">
                ` : `
                    <div style="display:flex; align-items:center; gap:10px; padding:12px; width:100%; background:rgba(255,255,255,0.06); border-radius:8px;">
                        <i class="fa-solid ${isAudio ? 'fa-microphone' : 'fa-file-lines'}" style="font-size:1.8rem; color:${isFailed ? '#EF4444' : 'var(--accent, #F2A735)'};"></i>
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

                        const messageType = res.type || (item.isImage ? 'image' : 'file');
                        const fileName = res.name || item.fileName || '';
                        const fileSize = res.size || item.fileSize || 0;
                        const duration = item.duration || 0;

                        API.sendMessage(item.vendorId, res.url, messageType, fileName, fileSize, duration)
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

// ── VOICE RECORDING SUBSYSTEM (60s HARD LIMIT) ────────────────────────────────

window._voiceRecorderState = {
    mediaRecorder: null,
    audioStream: null,
    audioChunks: [],
    audioBlob: null,
    audioUrl: '',
    recordingStartTime: 0,
    timerInterval: null,
    duration: 0,
    isRecording: false,
    isPreviewing: false,
    previewAudio: null,
    isPlayingPreview: false
};

window.startVoiceRecording = async function() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        if (typeof showPushNotification === 'function') {
            showPushNotification("Microphone Error", "Voice recording is not supported on this browser/device.");
        } else alert("Voice recording is not supported on this browser/device.");
        return;
    }

    // Reset existing recorder state cleanly
    window.cancelVoiceRecording();

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const vState = window._voiceRecorderState;
        vState.audioStream = stream;
        vState.audioChunks = [];

        // Codec fallback detection
        let mimeType = 'audio/webm;codecs=opus';
        if (typeof MediaRecorder.isTypeSupported === 'function') {
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) mimeType = 'audio/webm;codecs=opus';
            else if (MediaRecorder.isTypeSupported('audio/mp4')) mimeType = 'audio/mp4';
            else if (MediaRecorder.isTypeSupported('audio/aac')) mimeType = 'audio/aac';
            else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) mimeType = 'audio/ogg;codecs=opus';
            else if (MediaRecorder.isTypeSupported('audio/wav')) mimeType = 'audio/wav';
            else mimeType = '';
        }

        vState.mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);

        vState.mediaRecorder.ondataavailable = function(e) {
            if (e.data && e.data.size > 0) {
                vState.audioChunks.push(e.data);
            }
        };

        vState.mediaRecorder.onstop = function() {
            const blobType = vState.mediaRecorder.mimeType || 'audio/webm';
            vState.audioBlob = new Blob(vState.audioChunks, { type: blobType });
            if (vState.audioUrl) URL.revokeObjectURL(vState.audioUrl);
            vState.audioUrl = URL.createObjectURL(vState.audioBlob);
            window.showVoicePreviewUI();
        };

        vState.mediaRecorder.start(100);
        vState.isRecording = true;
        vState.recordingStartTime = performance.now();

        // Update UI to recording bar
        window.showVoiceRecordingUI();

        // 60-Second Hard Limit Enforcement Timer (checked every 100ms with performance.now())
        vState.timerInterval = setInterval(() => {
            const elapsed = (performance.now() - vState.recordingStartTime) / 1000;
            vState.duration = Math.min(60, Math.floor(elapsed));

            const timerLabel = document.getElementById('voice-timer-label');
            if (timerLabel) {
                const mm = String(Math.floor(vState.duration / 60)).padStart(2, '0');
                const ss = String(vState.duration % 60).padStart(2, '0');
                timerLabel.textContent = `● Recording ${mm}:${ss} / 01:00`;
            }

            // AUTO-STOP CUTOFF AT EXACTLY 60 SECONDS
            if (elapsed >= 60.0) {
                window.stopVoiceRecording(true);
            }
        }, 100);

    } catch (err) {
        console.error("Microphone access error:", err);
        let errMsg = "Microphone access is required to record a voice message.";
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            errMsg = "Microphone permission was denied. Please allow microphone access in your browser settings.";
        }
        if (typeof showPushNotification === 'function') {
            showPushNotification("Microphone Denied", errMsg);
        } else alert(errMsg);
        window.cancelVoiceRecording();
    }
};

window.stopVoiceRecording = function(isAutoLimit = false) {
    const vState = window._voiceRecorderState;
    if (!vState.isRecording && !vState.mediaRecorder) return;

    if (vState.timerInterval) {
        clearInterval(vState.timerInterval);
        vState.timerInterval = null;
    }

    if (vState.mediaRecorder && vState.mediaRecorder.state !== 'inactive') {
        vState.mediaRecorder.stop();
    }

    if (vState.audioStream) {
        vState.audioStream.getTracks().forEach(t => t.stop());
        vState.audioStream = null;
    }

    vState.isRecording = false;

    if (isAutoLimit && typeof showPushNotification === 'function') {
        showPushNotification("Voice Recording Limit", "Reached 60 seconds maximum recording limit.");
    }
};

window.cancelVoiceRecording = function() {
    const vState = window._voiceRecorderState;
    
    if (vState.timerInterval) {
        clearInterval(vState.timerInterval);
        vState.timerInterval = null;
    }

    if (vState.mediaRecorder && vState.mediaRecorder.state !== 'inactive') {
        try { vState.mediaRecorder.stop(); } catch(e) {}
    }

    if (vState.audioStream) {
        vState.audioStream.getTracks().forEach(t => t.stop());
        vState.audioStream = null;
    }

    if (vState.previewAudio) {
        vState.previewAudio.pause();
        vState.previewAudio = null;
    }

    if (vState.audioUrl) {
        URL.revokeObjectURL(vState.audioUrl);
        vState.audioUrl = '';
    }

    vState.audioBlob = null;
    vState.audioChunks = [];
    vState.isRecording = false;
    vState.isPreviewing = false;
    vState.duration = 0;

    window.restoreNormalComposerUI();
};

window.rerecordVoiceMessage = function() {
    window.cancelVoiceRecording();
    window.startVoiceRecording();
};

window.showVoiceRecordingUI = function() {
    const composer = document.querySelector('.chat-input-bar');
    if (!composer) return;

    composer.innerHTML = `
        <div id="voice-recording-overlay" style="display:flex; align-items:center; justify-content:space-between; width:100%; background:rgba(239, 68, 68, 0.08); border:1.5px solid #EF4444; border-radius:30px; padding:6px 16px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="voice-timer-label" style="font-size:0.85rem; font-weight:800; color:#EF4444; font-family:monospace;">● Recording 00:00 / 01:00</span>
                <div class="voice-wave-meter" style="display:flex; align-items:center; gap:3px; height:18px;">
                    <div style="width:3px; height:12px; background:#EF4444; border-radius:2px; animation:wave 0.8s ease-in-out infinite;"></div>
                    <div style="width:3px; height:18px; background:#EF4444; border-radius:2px; animation:wave 0.8s ease-in-out infinite 0.2s;"></div>
                    <div style="width:3px; height:8px; background:#EF4444; border-radius:2px; animation:wave 0.8s ease-in-out infinite 0.4s;"></div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <button onclick="cancelVoiceRecording()" title="Cancel Recording" style="background:rgba(239, 68, 68, 0.15); border:none; color:#EF4444; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="fa-solid fa-trash-can"></i></button>
                <button onclick="stopVoiceRecording()" title="Stop & Preview" style="background:#EF4444; border:none; color:#fff; padding:6px 14px; border-radius:20px; font-size:0.8rem; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:6px;"><i class="fa-solid fa-square"></i> Stop</button>
            </div>
        </div>
    `;
};

window.showVoicePreviewUI = function() {
    const vState = window._voiceRecorderState;
    vState.isPreviewing = true;
    const composer = document.querySelector('.chat-input-bar');
    if (!composer) return;

    const mm = String(Math.floor(vState.duration / 60)).padStart(2, '0');
    const ss = String(vState.duration % 60).padStart(2, '0');

    composer.innerHTML = `
        <div id="voice-preview-overlay" style="display:flex; align-items:center; justify-content:space-between; width:100%; background:var(--gray-100, #f1f5f9); border:1.5px solid var(--accent, #F2A735); border-radius:30px; padding:6px 14px;">
            <div style="display:flex; align-items:center; gap:10px; flex:1;">
                <button id="voice-preview-play-btn" onclick="toggleVoicePreviewPlay()" style="background:var(--accent, #F2A735); border:none; color:#0F1923; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:0.9rem;"><i class="fa-solid fa-play"></i></button>
                <span id="voice-preview-duration" style="font-size:0.8rem; font-weight:700; color:var(--gray-800, #1e293b); font-family:monospace;">${mm}:${ss}</span>
                <span style="font-size:0.75rem; color:var(--gray-500); font-weight:600;">Voice Note Preview</span>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <button onclick="rerecordVoiceMessage()" title="Re-record" style="background:rgba(0,0,0,0.06); border:none; color:var(--gray-700); width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="fa-solid fa-rotate-right"></i></button>
                <button onclick="cancelVoiceRecording()" title="Delete Recording" style="background:rgba(239, 68, 68, 0.12); border:none; color:#EF4444; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="fa-solid fa-trash-can"></i></button>
                <button onclick="sendVoiceRecording()" title="Send Voice Note" style="background:var(--accent, #F2A735); border:none; color:#0F1923; padding:7px 18px; border-radius:20px; font-size:0.8rem; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:6px; box-shadow:0 3px 10px rgba(242,167,53,0.3);"><i class="fa-solid fa-paper-plane"></i> Send</button>
            </div>
        </div>
    `;
};

window.toggleVoicePreviewPlay = function() {
    const vState = window._voiceRecorderState;
    if (!vState.audioUrl) return;

    const btn = document.getElementById('voice-preview-play-btn');

    if (vState.previewAudio) {
        if (vState.previewAudio.paused) {
            vState.previewAudio.play();
            if (btn) btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
        } else {
            vState.previewAudio.pause();
            if (btn) btn.innerHTML = '<i class="fa-solid fa-play"></i>';
        }
        return;
    }

    vState.previewAudio = new Audio(vState.audioUrl);
    vState.previewAudio.onended = function() {
        if (btn) btn.innerHTML = '<i class="fa-solid fa-play"></i>';
    };
    vState.previewAudio.play();
    if (btn) btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
};

window.sendVoiceRecording = function() {
    const vState = window._voiceRecorderState;
    if (!vState.audioBlob || !state.activeChatVendorId) return;

    const ext = vState.audioBlob.type.includes('mp4') ? 'm4a' : 'webm';
    const fileName = `voicenote_${Date.now()}.${ext}`;
    const file = new File([vState.audioBlob], fileName, { type: vState.audioBlob.type });

    // Enforce 10 MB limit for voice recordings
    if (file.size > 10 * 1024 * 1024) {
        alert("Voice note exceeds the 10 MB limit.");
        window.cancelVoiceRecording();
        return;
    }

    const tempId = 'chat_upload_' + Date.now();
    window._pendingChatUploads[tempId] = {
        tempId: tempId,
        file: file,
        vendorId: state.activeChatVendorId,
        isImage: false,
        fileName: fileName,
        fileSize: file.size,
        duration: vState.duration || 1,
        previewSrc: '',
        status: 'uploading',
        progress: 0,
        errorMsg: ''
    };

    window.restoreNormalComposerUI();
    window.renderPendingChatBubble(tempId);
    window.startChatFileUpload(tempId);

    // Clean up voice state without canceling pending upload
    vState.audioBlob = null;
    if (vState.audioUrl) URL.revokeObjectURL(vState.audioUrl);
    vState.audioUrl = '';
};

window.restoreNormalComposerUI = function() {
    const composer = document.querySelector('.chat-input-bar');
    if (!composer) return;

    composer.innerHTML = `
        <button class="chat-attach-btn" onclick="triggerChatAttachment()" title="Upload File" style="display:flex; width:36px; height:36px; border-radius:50%; background:var(--gray-100); border:none; color:var(--gray-600); cursor:pointer; align-items:center; justify-content:center; font-size:0.85rem;"><i class="fa-solid fa-paperclip"></i></button>
        <button class="chat-mic-btn" onclick="startVoiceRecording()" title="Record Voice Note" style="display:flex; width:36px; height:36px; border-radius:50%; background:var(--gray-100); border:none; color:var(--gray-600); cursor:pointer; align-items:center; justify-content:center; font-size:0.85rem;"><i class="fa-solid fa-microphone"></i></button>
        <input class="chat-input" placeholder="Type a message..." id="chat-input-field" onkeyup="if(event.key==='Enter') sendTextMessage()">
        <button class="chat-send-btn" onclick="sendTextMessage()"><i class="fa-solid fa-paper-plane"></i></button>
        <input type="file" id="chat-file-input" style="display:none;" onchange="handleChatFileSelected(this)" accept="image/*,audio/*,video/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,text/csv,application/zip">
    `;
};

window.getBlockedUsers = function() {
    try {
        return JSON.parse(localStorage.getItem('ohati_blocked_users') || '[]');
    } catch(e) { return []; }
};

window._pendingChatBlock = { vendorId: 0, vendorName: '' };
window._pendingChatReport = { vendorId: 0, vendorName: '' };
