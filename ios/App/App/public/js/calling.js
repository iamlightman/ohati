// js/calling.js — Audio-Only Voice Calling System for Ohati App

(function() {
    'use strict';

    let localAudioStream = null;
    let peerConnection = null;
    let callTimerInterval = null;
    let callSeconds = 0;
    let currentCallData = null;
    let isMuted = false;

    window.OhatiCalling = {
        startCall: function(vendorId, type = 'voice') {
            window.initiateVoiceCall(vendorId);
        },
        endCall: function() {
            window.endVoiceCall();
        }
    };

    window.initiateVoiceCall = function(vendorId, vendorName, vendorAvatar) {
        if (typeof state !== 'undefined' && state.user === null) {
            if (typeof showPushNotification === 'function') {
                showPushNotification('Authentication Required', 'Please sign in to place a voice call.');
            }
            return;
        }

        currentCallData = {
            vendorId: vendorId,
            name: vendorName || 'Vendor',
            avatar: vendorAvatar || (window.DEFAULT_USER_AVATAR || ''),
            status: 'dialing'
        };

        renderVoiceCallModal('Outgoing Voice Call', 'Ringing...');
        startAudioStream();
    };

    function startAudioStream() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            updateCallStatus('Voice calling is not supported on this browser.');
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true, video: false })
            .then(stream => {
                localAudioStream = stream;
                updateCallStatus('Ringing...');
            })
            .catch(err => {
                console.error('Microphone error:', err);
                updateCallStatus('Microphone permission denied.');
            });
    }

    window.connectVoiceCall = function() {
        if (!currentCallData) return;
        currentCallData.status = 'connected';
        updateCallStatus('Connected');
        startCallTimer();
    };

    function startCallTimer() {
        clearInterval(callTimerInterval);
        callSeconds = 0;
        const timerEl = document.getElementById('voiceCallTimer');
        callTimerInterval = setInterval(() => {
            callSeconds++;
            const mins = String(Math.floor(callSeconds / 60)).padStart(2, '0');
            const secs = String(callSeconds % 60).padStart(2, '0');
            if (timerEl) {
                timerEl.textContent = `${mins}:${secs}`;
            }
        }, 1000);
    }

    function updateCallStatus(text) {
        const statusEl = document.getElementById('voiceCallStatusText');
        if (statusEl) {
            statusEl.textContent = text;
        }
    }

    window.toggleMuteMicrophone = function() {
        if (localAudioStream) {
            const audioTracks = localAudioStream.getAudioTracks();
            if (audioTracks.length > 0) {
                isMuted = !isMuted;
                audioTracks[0].enabled = !isMuted;
                const btn = document.getElementById('btnMuteVoice');
                if (btn) {
                    btn.classList.toggle('active', isMuted);
                    btn.style.background = isMuted ? '#EF4444' : 'rgba(255,255,255,0.15)';
                }
            }
        }
    };

    window.endVoiceCall = function() {
        if (localAudioStream) {
            localAudioStream.getTracks().forEach(track => track.stop());
            localAudioStream = null;
        }
        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }
        clearInterval(callTimerInterval);
        callSeconds = 0;
        isMuted = false;
        currentCallData = null;

        const modal = document.getElementById('voiceCallModal');
        if (modal) {
            modal.style.display = 'none';
            modal.remove();
        }
    };

    function renderVoiceCallModal(title, statusText) {
        let modal = document.getElementById('voiceCallModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'voiceCallModal';
            modal.style.cssText = `
                position: fixed; inset: 0; z-index: 99999;
                background: linear-gradient(135deg, #081729 0%, #0F2942 100%);
                display: flex; flex-direction: column; align-items: center; justify-content: space-between;
                padding: 40px 24px; color: #FFFFFF; font-family: system-ui, sans-serif;
            `;
            document.body.appendChild(modal);
        }

        const avatarUrl = (currentCallData && currentCallData.avatar) ? currentCallData.avatar : (window.DEFAULT_USER_AVATAR || '');
        const vendorName = currentCallData ? currentCallData.name : 'Vendor';

        modal.innerHTML = `
            <div style="text-align: center; margin-top: 20px;">
                <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; font-weight: 600;">Ohati Voice Call</div>
                <h3 style="margin: 8px 0 0 0; font-size: 1.5rem; color: #FFFFFF; font-weight: 700;">${escapeHtml(vendorName)}</h3>
                <div id="voiceCallStatusText" style="margin-top: 6px; font-size: 0.95rem; color: #CBD5E1;">${escapeHtml(statusText)}</div>
                <div id="voiceCallTimer" style="margin-top: 4px; font-size: 1.1rem; font-weight: 700; color: #38BDF8;">00:00</div>
            </div>

            <div style="position: relative; width: 140px; height: 140px; margin: 40px 0;">
                <div style="position: absolute; inset: -10px; border-radius: 50%; border: 2px solid rgba(56, 189, 248, 0.4); animation: pulse 2s infinite;"></div>
                <img src="${escapeHtml(avatarUrl)}" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid #38BDF8; background: #081729;">
            </div>

            <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 20px;">
                <button id="btnMuteVoice" onclick="toggleMuteMicrophone()" style="width: 56px; height: 56px; border-radius: 50%; border: none; background: rgba(255,255,255,0.15); color: #FFF; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" title="Mute Microphone">
                    <i class="fa-solid fa-microphone-slash"></i>
                </button>
                <button onclick="endVoiceCall()" style="width: 68px; height: 68px; border-radius: 50%; border: none; background: #EF4444; color: #FFF; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(239,68,68,0.4);" title="End Call">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
            </div>
        `;

        modal.style.display = 'flex';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
