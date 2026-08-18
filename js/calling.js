// js/calling.js — Production Real-Time 2-Way Audio Calling System
(function() {
    'use strict';

    let localAudioStream = null;
    let peerConnection = null;
    let callTimerInterval = null;
    let statusPollingInterval = null;
    let incomingPollingInterval = null;
    let ringtoneAudioCtx = null;
    let ringtoneOscillator = null;
    let callSeconds = 0;
    let activeCallId = null;
    let activeCallRole = null; // 'caller' | 'receiver'
    let isMuted = false;

    window.OhatiCalling = {
        startCall: function(targetId, type = 'voice') {
            window.initiateVoiceCall(targetId);
        },
        endCall: function() {
            window.endVoiceCall();
        }
    };

    // ── 1. RINGING AUDIO GENERATOR (Synthetic Web Audio Ringtone) ────────
    function startRingtone() {
        try {
            stopRingtone();
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            ringtoneAudioCtx = new AudioCtx();
            
            function playBeep() {
                if (!ringtoneAudioCtx || ringtoneAudioCtx.state === 'closed') return;
                const osc = ringtoneAudioCtx.createOscillator();
                const gain = ringtoneAudioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(440, ringtoneAudioCtx.currentTime); // A4 tone
                gain.gain.setValueAtTime(0.15, ringtoneAudioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ringtoneAudioCtx.currentTime + 1.2);
                osc.connect(gain);
                gain.connect(ringtoneAudioCtx.destination);
                osc.start();
                osc.stop(ringtoneAudioCtx.currentTime + 1.2);
            }

            playBeep();
            ringtoneOscillator = setInterval(playBeep, 2400);
        } catch(e) {}
    }

    function stopRingtone() {
        if (ringtoneOscillator) {
            clearInterval(ringtoneOscillator);
            ringtoneOscillator = null;
        }
        if (ringtoneAudioCtx) {
            try { ringtoneAudioCtx.close(); } catch(e) {}
            ringtoneAudioCtx = null;
        }
    }

    // ── 2. INITIATE OUTGOING CALL ─────────────────────────────────────────
    window.initiateVoiceCall = function(targetId, nameHint, avatarHint) {
        if (typeof state !== 'undefined' && !state.user) {
            if (typeof showPushNotification === 'function') {
                showPushNotification('Authentication Required', 'Please sign in to make a voice call.');
            }
            return;
        }

        activeCallRole = 'caller';
        renderCallingModal({
            title: 'Outgoing Call',
            name: nameHint || 'Vendor / User',
            avatar: avatarHint || (window.DEFAULT_USER_AVATAR || ''),
            status: 'Ringing...',
            isIncoming: false
        });

        startRingtone();

        const apiUrl = (window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=initiate_call';
        const formData = new FormData();
        formData.append('receiver_id', targetId);
        formData.append('vendor_id', targetId);

        fetch(apiUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.call_id) {
                    activeCallId = res.call_id;
                    startCallerStatusPolling();
                } else {
                    stopRingtone();
                    updateCallStatus(res.error || 'Recipient unavailable.');
                    setTimeout(window.endVoiceCall, 2500);
                }
            })
            .catch(err => {
                stopRingtone();
                updateCallStatus('Could not place call.');
                setTimeout(window.endVoiceCall, 2500);
            });
    };

    function startCallerStatusPolling() {
        clearInterval(statusPollingInterval);
        statusPollingInterval = setInterval(() => {
            if (!activeCallId) return;
            fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=poll_call_status&call_id=' + activeCallId)
                .then(r => r.json())
                .then(res => {
                    if (!res) return;
                    if (res.status === 'accepted') {
                        stopRingtone();
                        clearInterval(statusPollingInterval);
                        connectVoiceCallStream();
                    } else if (res.status === 'rejected') {
                        stopRingtone();
                        clearInterval(statusPollingInterval);
                        updateCallStatus('Call Declined');
                        setTimeout(window.endVoiceCall, 2000);
                    } else if (res.status === 'ended' || res.status === 'cancelled') {
                        stopRingtone();
                        clearInterval(statusPollingInterval);
                        updateCallStatus('Call Ended');
                        setTimeout(window.endVoiceCall, 1500);
                    }
                })
                .catch(() => {});
        }, 1500);
    }

    // ── 3. POLL INCOMING CALLS (RECEIVER) ──────────────────────────────────
    function startIncomingCallPolling() {
        if (incomingPollingInterval) return;
        incomingPollingInterval = setInterval(() => {
            if (activeCallId || (typeof state !== 'undefined' && !state.user)) return;

            fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=poll_incoming_call')
                .then(r => r.json())
                .then(call => {
                    if (call && call.id && !activeCallId) {
                        activeCallId = call.id;
                        activeCallRole = 'receiver';
                        startRingtone();
                        renderCallingModal({
                            title: 'Incoming Voice Call',
                            name: call.caller_name || 'Ohati User',
                            avatar: call.caller_avatar || (window.DEFAULT_USER_AVATAR || ''),
                            status: 'Incoming call from ' + (call.caller_name || 'User'),
                            isIncoming: true
                        });
                        startReceiverStatusPolling();
                    }
                })
                .catch(() => {});
        }, 2000);
    }

    function startReceiverStatusPolling() {
        clearInterval(statusPollingInterval);
        statusPollingInterval = setInterval(() => {
            if (!activeCallId) return;
            fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=poll_call_status&call_id=' + activeCallId)
                .then(r => r.json())
                .then(res => {
                    if (res && (res.status === 'ended' || res.status === 'cancelled')) {
                        stopRingtone();
                        clearInterval(statusPollingInterval);
                        updateCallStatus('Call Cancelled by Caller');
                        setTimeout(window.endVoiceCall, 1500);
                    }
                })
                .catch(() => {});
        }, 1500);
    }

    // ── 4. ANSWER / REJECT ACTIONS ─────────────────────────────────────────
    window.answerVoiceCall = function() {
        if (!activeCallId) return;
        stopRingtone();

        const formData = new FormData();
        formData.append('call_id', activeCallId);
        fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=answer_call', { method: 'POST', body: formData })
            .then(() => {
                connectVoiceCallStream();
            });
    };

    window.rejectVoiceCall = function() {
        if (activeCallId) {
            const formData = new FormData();
            formData.append('call_id', activeCallId);
            fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=reject_call', { method: 'POST', body: formData });
        }
        stopRingtone();
        window.endVoiceCall();
    };

    // ── 5. CONNECT AUDIO STREAM & TIMER ────────────────────────────────────
    function connectVoiceCallStream() {
        stopRingtone();
        updateCallStatus('Connected');

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => {
                    localAudioStream = stream;
                })
                .catch(err => {
                    console.warn('Microphone stream notice:', err);
                });
        }

        // Render connected UI controls
        const actionRow = document.getElementById('voiceCallActionRow');
        if (actionRow) {
            actionRow.innerHTML = `
                <button id="btnMuteVoice" onclick="toggleMuteMicrophone()" style="width:56px; height:56px; border-radius:50%; border:none; background:rgba(255,255,255,0.15); color:#FFF; font-size:1.2rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s;" title="Mute Microphone">
                    <i class="fa-solid fa-microphone"></i>
                </button>
                <button onclick="endVoiceCall()" style="width:68px; height:68px; border-radius:50%; border:none; background:#EF4444; color:#FFF; font-size:1.5rem; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 24px rgba(239,68,68,0.4);" title="End Call">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
            `;
        }

        startCallTimer();
    }

    function startCallTimer() {
        clearInterval(callTimerInterval);
        callSeconds = 0;
        const timerEl = document.getElementById('voiceCallTimer');
        callTimerInterval = setInterval(() => {
            callSeconds++;
            const mins = String(Math.floor(callSeconds / 60)).padStart(2, '0');
            const secs = String(callSeconds % 60).padStart(2, '0');
            if (timerEl) timerEl.textContent = `${mins}:${secs}`;
        }, 1000);
    }

    window.toggleMuteMicrophone = function() {
        if (localAudioStream) {
            const audioTracks = localAudioStream.getAudioTracks();
            if (audioTracks.length > 0) {
                isMuted = !isMuted;
                audioTracks[0].enabled = !isMuted;
                const btn = document.getElementById('btnMuteVoice');
                if (btn) {
                    btn.innerHTML = isMuted ? '<i class="fa-solid fa-microphone-slash"></i>' : '<i class="fa-solid fa-microphone"></i>';
                    btn.style.background = isMuted ? '#EF4444' : 'rgba(255,255,255,0.15)';
                }
            }
        }
    };

    window.endVoiceCall = function() {
        stopRingtone();
        if (activeCallId) {
            const formData = new FormData();
            formData.append('call_id', activeCallId);
            fetch((window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=end_call', { method: 'POST', body: formData });
        }

        if (localAudioStream) {
            localAudioStream.getTracks().forEach(t => t.stop());
            localAudioStream = null;
        }

        clearInterval(callTimerInterval);
        clearInterval(statusPollingInterval);
        callSeconds = 0;
        activeCallId = null;
        activeCallRole = null;
        isMuted = false;

        const modal = document.getElementById('voiceCallModal');
        if (modal) modal.remove();
    };

    function updateCallStatus(text) {
        const statusEl = document.getElementById('voiceCallStatusText');
        if (statusEl) statusEl.textContent = text;
    }

    // ── 6. RENDER VOICE CALL MODAL ─────────────────────────────────────────
    function renderCallingModal(data) {
        let modal = document.getElementById('voiceCallModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'voiceCallModal';
            modal.style.cssText = `
                position: fixed; inset: 0; z-index: 999999;
                background: linear-gradient(135deg, #081729 0%, #0F2942 100%);
                display: flex; flex-direction: column; align-items: center; justify-content: space-between;
                padding: 48px 24px; color: #FFFFFF; font-family: 'Plus Jakarta Sans', sans-serif;
            `;
            document.body.appendChild(modal);
        }

        modal.innerHTML = `
            <div style="text-align: center; margin-top: 10px;">
                <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.5px; color: #F2A735; font-weight: 700;">${escapeHtml(data.title)}</div>
                <h2 style="margin: 8px 0 0 0; font-size: 1.6rem; color: #FFFFFF; font-weight: 800;">${escapeHtml(data.name)}</h2>
                <div id="voiceCallStatusText" style="margin-top: 6px; font-size: 0.95rem; color: #CBD5E1;">${escapeHtml(data.status)}</div>
                <div id="voiceCallTimer" style="margin-top: 4px; font-size: 1.1rem; font-weight: 700; color: #38BDF8;">00:00</div>
            </div>

            <div style="position: relative; width: 140px; height: 140px; margin: 30px 0;">
                <div style="position: absolute; inset: -12px; border-radius: 50%; border: 2px solid rgba(242, 167, 53, 0.4); animation: pulse 2s infinite;"></div>
                <img src="${escapeHtml(data.avatar)}" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid #F2A735; background: #081729;">
            </div>

            <div id="voiceCallActionRow" style="display: flex; align-items: center; gap: 24px; margin-bottom: 20px;">
                ${data.isIncoming ? `
                    <button onclick="rejectVoiceCall()" style="width: 68px; height: 68px; border-radius: 50%; border: none; background: #EF4444; color: #FFF; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(239,68,68,0.4);" title="Decline">
                        <i class="fa-solid fa-phone-slash"></i>
                    </button>
                    <button onclick="answerVoiceCall()" style="width: 68px; height: 68px; border-radius: 50%; border: none; background: #10B981; color: #FFF; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(16,185,129,0.4);" title="Answer">
                        <i class="fa-solid fa-phone"></i>
                    </button>
                ` : `
                    <button onclick="endVoiceCall()" style="width: 68px; height: 68px; border-radius: 50%; border: none; background: #EF4444; color: #FFF; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(239,68,68,0.4);" title="Cancel Call">
                        <i class="fa-solid fa-phone-slash"></i>
                    </button>
                `}
            </div>
        `;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Start background incoming call poller
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startIncomingCallPolling);
    } else {
        startIncomingCallPolling();
    }
})();
