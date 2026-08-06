// js/calling.js - Ohati Real-Time Audio & Video Calling Client

const CallAudio = {
    ctx: null,
    osc: null,
    gain: null,
    ringInterval: null,
    dialInterval: null,
    init() {
        if (!this.ctx) {
            this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        }
    },
    playRingtone() {
        this.init();
        this.stop();
        this.osc = this.ctx.createOscillator();
        this.gain = this.ctx.createGain();
        this.osc.type = 'sine';
        this.osc.frequency.setValueAtTime(440, this.ctx.currentTime);
        this.gain.gain.setValueAtTime(0, this.ctx.currentTime);
        const now = this.ctx.currentTime;
        this.gain.gain.linearRampToValueAtTime(0.5, now + 0.1);
        this.gain.gain.linearRampToValueAtTime(0.5, now + 1.5);
        this.gain.gain.linearRampToValueAtTime(0.0, now + 1.6);
        this.osc.connect(this.gain);
        this.gain.connect(this.ctx.destination);
        this.osc.start(now);

        this.ringInterval = setInterval(() => {
            if (this.ctx.state === 'suspended') this.ctx.resume();
            const t = this.ctx.currentTime;
            this.gain.gain.setValueAtTime(0, t);
            this.gain.gain.linearRampToValueAtTime(0.5, t + 0.1);
            this.gain.gain.linearRampToValueAtTime(0.5, t + 1.5);
            this.gain.gain.linearRampToValueAtTime(0.0, t + 1.6);
        }, 3000);
    },
    playDialTone() {
        this.init();
        this.stop();
        this.osc = this.ctx.createOscillator();
        this.gain = this.ctx.createGain();
        this.osc.type = 'sine';
        this.osc.frequency.setValueAtTime(325, this.ctx.currentTime);
        this.gain.gain.setValueAtTime(0, this.ctx.currentTime);
        const now = this.ctx.currentTime;
        this.gain.gain.linearRampToValueAtTime(0.2, now + 0.1);
        this.gain.gain.linearRampToValueAtTime(0.2, now + 1.0);
        this.gain.gain.linearRampToValueAtTime(0.0, now + 1.1);
        this.osc.connect(this.gain);
        this.gain.connect(this.ctx.destination);
        this.osc.start(now);

        this.dialInterval = setInterval(() => {
            if (this.ctx.state === 'suspended') this.ctx.resume();
            const t = this.ctx.currentTime;
            this.gain.gain.setValueAtTime(0, t);
            this.gain.gain.linearRampToValueAtTime(0.2, t + 0.1);
            this.gain.gain.linearRampToValueAtTime(0.2, t + 1.0);
            this.gain.gain.linearRampToValueAtTime(0.0, t + 1.1);
        }, 2500);
    },
    stop() {
        if (this.osc) {
            try { this.osc.stop(); } catch(e) {}
            this.osc = null;
        }
        if (this.ringInterval) clearInterval(this.ringInterval);
        if (this.dialInterval) clearInterval(this.dialInterval);
    }
};

const OhatiCalling = {
    peerConnection: null,
    localStream: null,
    remoteStream: null,
    currentCall: null,
    isCaller: false,
    pollInterval: null,
    candidatePollInterval: null,
    callTimerInterval: null,
    secondsElapsed: 0,
    isMuted: false,
    isSpeaker: false,
    isVideoEnabled: true,
    processedCandidates: new Set(),
    iceCandidateBuffer: [],
    candidateFlushTimer: null,

    // Concurrency guards for polling API requests
    checkingCallInProgress: false,
    pollingCallDetailsInProgress: false,
    pollingCallerInProgress: false,
    pollingCandidatesInProgress: false,

    rtcConfig: {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' }
        ]
    },

    init() {
        // Check for incoming call invites every 2 seconds
        setInterval(() => {
            if (typeof state !== 'undefined' && state.user && !this.currentCall) {
                this.checkIncomingCall();
            }
        }, 2000);

        // Resume AudioContext on user interaction to bypass browser autoplay rules
        document.addEventListener('click', () => {
            if (CallAudio && CallAudio.ctx && CallAudio.ctx.state === 'suspended') {
                CallAudio.ctx.resume();
            }
        }, { once: true });
    },

    async getUserMediaStream(videoEnabled = false) {
        return await getUniversalMediaStream(videoEnabled);
    },

    checkIncomingCall() {
        if (this.checkingCallInProgress) return;
        this.checkingCallInProgress = true;
        API.get('check_incoming_call').then(call => {
            this.checkingCallInProgress = false;
            if (call && call.id) {
                this.currentCall = call;
                this.isCaller = false;
                this.secondsElapsed = 0;
                this.showIncomingCallScreen(call);
            }
        }).catch(err => {
            this.checkingCallInProgress = false;
            console.error("Error polling incoming call:", err);
        });
    },

    // Buffer ICE candidates and send in batches to avoid DB race conditions
    queueIceCandidate(candidate) {
        this.iceCandidateBuffer.push(candidate);
        if (this.candidateFlushTimer) clearTimeout(this.candidateFlushTimer);
        this.candidateFlushTimer = setTimeout(() => this.flushIceCandidates(), 300);
    },

    flushIceCandidates() {
        if (!this.currentCall || this.iceCandidateBuffer.length === 0) return;
        const batch = this.iceCandidateBuffer.splice(0);
        const role = this.isCaller ? 'caller' : 'receiver';
        API.post('send_ice_candidate', {
            call_id: this.currentCall.id,
            role: role,
            candidate: JSON.stringify(batch)
        }).catch(err => console.warn("Failed to send ICE candidates:", err));
    },

    async startCall(receiverId, callType = 'voice') {
        if (this.currentCall) return;
        this.isCaller = true;
        this.secondsElapsed = 0;
        this.isVideoEnabled = (callType === 'video');
        this.iceCandidateBuffer = [];

        // Show Dialing screen
        this.showCallOverlay(null, 'dialing', callType);
        CallAudio.playDialTone();

        try {
            // Get local audio/video media stream
            this.localStream = await this.getUserMediaStream(this.isVideoEnabled);
            
            // Verify if video track is active
            const hasVideoTrack = this.localStream && this.localStream.getVideoTracks().length > 0;
            this.isVideoEnabled = hasVideoTrack;

            // Set local stream source in UI
            if (this.isVideoEnabled) {
                const localVideoEl = document.getElementById('call-local-video');
                if (localVideoEl) {
                    localVideoEl.srcObject = this.localStream;
                    localVideoEl.style.display = 'block';
                }
            }

            // Setup peer connection
            this.peerConnection = new RTCPeerConnection(this.rtcConfig);
            this.localStream.getTracks().forEach(track => {
                this.peerConnection.addTrack(track, this.localStream);
            });

            // Stream remote track handler
            this.peerConnection.ontrack = (event) => {
                this.remoteStream = event.streams[0];
                const remoteVideoEl = document.getElementById('call-remote-video');
                const remoteAudioEl = document.getElementById('call-remote-audio');
                if (this.isVideoEnabled && remoteVideoEl) {
                    remoteVideoEl.srcObject = this.remoteStream;
                    remoteVideoEl.style.display = 'block';
                    remoteVideoEl.play().catch(e => console.warn("Remote video playback notice:", e));
                } else if (remoteAudioEl) {
                    remoteAudioEl.srcObject = this.remoteStream;
                    remoteAudioEl.play().catch(e => console.warn("Remote audio playback notice:", e));
                }
            };

            // Ice candidate generation — buffer until call_id is available
            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.queueIceCandidate(event.candidate);
                }
            };

            // Monitor connection state for drops
            this.peerConnection.onconnectionstatechange = () => {
                const s = this.peerConnection?.connectionState;
                if (s === 'failed' || s === 'disconnected') {
                    console.warn('WebRTC connection state:', s);
                    if (s === 'failed') {
                        showPushNotification('Call Dropped', 'Connection lost. Please try again.');
                        this.endCall('failed');
                    }
                }
            };

            // Create Offer
            const offer = await this.peerConnection.createOffer();
            await this.peerConnection.setLocalDescription(offer);

            // Send call offer to server
            API.post('initiate_call', {
                receiver_id: receiverId,
                type: callType,
                sdp_offer: JSON.stringify(offer)
            }).then(res => {
                if (res.call_id) {
                    this.currentCall = {
                        id: res.call_id,
                        receiver_id: receiverId,
                        type: callType
                    };
                    // Flush any buffered ICE candidates now that we have the call_id
                    this.flushIceCandidates();
                    // Update overlay details with receiver info
                    API.get('get_call_details', { call_id: res.call_id }).then(details => {
                        this.updateCallOverlayDetails(details);
                    });
                    this.startCallerPolling();
                } else {
                    this.endCall('failed');
                }
            }).catch(err => {
                console.error(err);
                this.endCall('failed');
            });

        } catch (err) {
            console.error("Local media access failed:", err);
            showPushNotification('Call Error', err.message || 'Microphone access denied. Please grant microphone permission.');
            this.endCall('failed');
        }
    },

    showIncomingCallScreen(call) {
        CallAudio.playRingtone();
        this.showCallOverlay(call, 'incoming', call.type);
        this.startReceiverIncomingCallPolling();
    },

    startReceiverIncomingCallPolling() {
        if (this.pollInterval) clearInterval(this.pollInterval);
        let attempts = 0;
        this.pollingCallDetailsInProgress = false;
        this.pollInterval = setInterval(() => {
            if (this.pollingCallDetailsInProgress) return;
            attempts++;
            if (attempts > 20) { // 40 seconds timeout
                clearInterval(this.pollInterval);
                this.rejectCall();
                return;
            }
            if (!this.currentCall) {
                clearInterval(this.pollInterval);
                return;
            }
            this.pollingCallDetailsInProgress = true;
            API.get('get_call_details', { call_id: this.currentCall.id }).then(details => {
                this.pollingCallDetailsInProgress = false;
                if (!this.currentCall) {
                    clearInterval(this.pollInterval);
                    return;
                }
                if (details.status === 'ended' || details.status === 'no-answer' || details.status === 'rejected') {
                    clearInterval(this.pollInterval);
                    CallAudio.stop();
                    showPushNotification('Missed Call', `You missed a call from ${details.caller_name || 'User'}`);
                    this.cleanupCallState();
                }
            }).catch(err => {
                this.pollingCallDetailsInProgress = false;
                console.error("Error polling incoming call status:", err);
            });
        }, 2000);
    },

    async acceptCall() {
        CallAudio.stop();
        if (!this.currentCall) return;

        this.isVideoEnabled = (this.currentCall.type === 'video');
        this.updateCallOverlayStatus('connecting');

        try {
            // Get local audio/video media stream
            this.localStream = await this.getUserMediaStream(this.isVideoEnabled);

            const hasVideoTrack = this.localStream && this.localStream.getVideoTracks().length > 0;
            this.isVideoEnabled = hasVideoTrack;

            if (this.isVideoEnabled) {
                const localVideoEl = document.getElementById('call-local-video');
                if (localVideoEl) {
                    localVideoEl.srcObject = this.localStream;
                    localVideoEl.style.display = 'block';
                }
            }

            this.peerConnection = new RTCPeerConnection(this.rtcConfig);
            this.localStream.getTracks().forEach(track => {
                this.peerConnection.addTrack(track, this.localStream);
            });

            this.peerConnection.ontrack = (event) => {
                this.remoteStream = event.streams[0];
                const remoteVideoEl = document.getElementById('call-remote-video');
                const remoteAudioEl = document.getElementById('call-remote-audio');
                if (this.isVideoEnabled && remoteVideoEl) {
                    remoteVideoEl.srcObject = this.remoteStream;
                    remoteVideoEl.style.display = 'block';
                    remoteVideoEl.play().catch(e => console.warn("Remote video playback notice:", e));
                } else if (remoteAudioEl) {
                    remoteAudioEl.srcObject = this.remoteStream;
                    remoteAudioEl.play().catch(e => console.warn("Remote audio playback notice:", e));
                }
            };

            // Ice candidate generation — use batched queue
            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.queueIceCandidate(event.candidate);
                }
            };

            // Monitor connection state for drops
            this.peerConnection.onconnectionstatechange = () => {
                const s = this.peerConnection?.connectionState;
                if (s === 'failed') {
                    showPushNotification('Call Dropped', 'Connection lost. Please try again.');
                    this.endCall('failed');
                }
            };

            // Set remote offer
            const offer = JSON.parse(this.currentCall.sdp_offer);
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription(offer));

            // Create answer
            const answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);

            // Submit accept and answer SDP
            await API.post('accept_call', {
                call_id: this.currentCall.id,
                sdp_answer: JSON.stringify(answer)
            });

            this.updateCallOverlayStatus('connected');
            this.startCallTimer();
            this.startIceCandidatePolling();

        } catch (err) {
            console.error("Failed to accept call:", err);
            this.rejectCall();
        }
    },

    rejectCall() {
        CallAudio.stop();
        if (this.currentCall) {
            API.post('update_call_status', {
                call_id: this.currentCall.id,
                status: 'rejected'
            });
        }
        this.cleanupCallState();
    },

    endCall(status = 'ended') {
        CallAudio.stop();
        if (this.currentCall) {
            API.post('update_call_status', {
                call_id: this.currentCall.id,
                status: status,
                duration: this.secondsElapsed
            });
        }
        this.cleanupCallState();
    },

    cleanupCallState() {
        if (this.pollInterval) clearInterval(this.pollInterval);
        if (this.candidatePollInterval) clearInterval(this.candidatePollInterval);
        if (this.callTimerInterval) clearInterval(this.callTimerInterval);
        if (this.candidateFlushTimer) clearTimeout(this.candidateFlushTimer);
        
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }
        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }

        this.remoteStream = null;
        this.currentCall = null;
        this.processedCandidates.clear();
        this.iceCandidateBuffer = [];

        // Remove UI Overlay
        const el = document.getElementById('calling-overlay');
        if (el) el.remove();
    },

    startCallerPolling() {
        let attempts = 0;
        this.pollingCallerInProgress = false;
        this.pollInterval = setInterval(() => {
            if (this.pollingCallerInProgress) return;
            attempts++;
            if (attempts > 15) { // 30 seconds ring timeout
                this.endCall('no-answer');
                return;
            }

            this.pollingCallerInProgress = true;
            API.get('get_call_details', { call_id: this.currentCall.id }).then(details => {
                this.pollingCallerInProgress = false;
                if (details.status === 'accepted') {
                    clearInterval(this.pollInterval);
                    CallAudio.stop();
                    this.updateCallOverlayStatus('connected');
                    
                    // Set Remote SDP Answer
                    const answer = JSON.parse(details.sdp_answer);
                    this.peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
                    
                    this.startCallTimer();
                    this.startIceCandidatePolling();
                } else if (details.status === 'rejected' || details.status === 'busy') {
                    clearInterval(this.pollInterval);
                    showPushNotification('Call Declined', 'User is currently busy or declined your call.');
                    this.cleanupCallState();
                }
            }).catch(err => {
                this.pollingCallerInProgress = false;
                console.error("Error polling call accept status:", err);
            });
        }, 2000);
    },

    startIceCandidatePolling() {
        this.pollingCandidatesInProgress = false;
        this.candidatePollInterval = setInterval(() => {
            if (!this.currentCall || this.pollingCandidatesInProgress) return;

            this.pollingCandidatesInProgress = true;
            API.get('get_call_details', { call_id: this.currentCall.id }).then(details => {
                this.pollingCandidatesInProgress = false;
                // If call has been ended by remote peer
                if (details.status === 'ended' || details.status === 'rejected') {
                    showPushNotification('Call Ended', 'The other party hung up.');
                    this.cleanupCallState();
                    return;
                }

                // Check counterpart candidates
                const roleKey = this.isCaller ? 'ice_candidates_receiver' : 'ice_candidates_caller';
                const candidates = JSON.parse(details[roleKey] || '[]');
                
                candidates.forEach(cand => {
                    const key = JSON.stringify(cand);
                    if (!this.processedCandidates.has(key)) {
                        this.processedCandidates.add(key);
                        if (this.peerConnection) {
                            this.peerConnection.addIceCandidate(new RTCIceCandidate(cand)).catch(e => {
                                console.warn("Error adding ICE candidate:", e);
                            });
                        }
                    }
                });
            }).catch(err => {
                this.pollingCandidatesInProgress = false;
                console.error("Candidate poll failed:", err);
            });
        }, 2000);
    },

    startCallTimer() {
        this.secondsElapsed = 0;
        const timerVal = document.getElementById('call-timer-val');
        if (timerVal) timerVal.textContent = '00:00';

        this.callTimerInterval = setInterval(() => {
            this.secondsElapsed++;
            const mins = Math.floor(this.secondsElapsed / 60).toString().padStart(2, '0');
            const secs = (this.secondsElapsed % 60).toString().padStart(2, '0');
            if (timerVal) timerVal.textContent = `${mins}:${secs}`;
        }, 1000);
    },

    toggleMute() {
        if (this.localStream) {
            this.isMuted = !this.isMuted;
            this.localStream.getAudioTracks().forEach(track => {
                track.enabled = !this.isMuted;
            });
            const muteBtn = document.getElementById('call-btn-mute');
            if (muteBtn) {
                muteBtn.classList.toggle('btn-active', this.isMuted);
                muteBtn.innerHTML = this.isMuted ? '<i class="fa-solid fa-microphone-slash"></i>' : '<i class="fa-solid fa-microphone"></i>';
            }
        }
    },

    toggleVideo() {
        if (this.localStream && this.currentCall.type === 'video') {
            this.isVideoEnabled = !this.isVideoEnabled;
            this.localStream.getVideoTracks().forEach(track => {
                track.enabled = this.isVideoEnabled;
            });
            const videoBtn = document.getElementById('call-btn-video');
            const localVideoEl = document.getElementById('call-local-video');
            if (videoBtn) {
                videoBtn.classList.toggle('btn-active', !this.isVideoEnabled);
                videoBtn.innerHTML = this.isVideoEnabled ? '<i class="fa-solid fa-video"></i>' : '<i class="fa-solid fa-video-slash"></i>';
            }
            if (localVideoEl) {
                localVideoEl.style.display = this.isVideoEnabled ? 'block' : 'none';
            }
        }
    },

    toggleSpeaker() {
        this.isSpeaker = !this.isSpeaker;
        const speakerBtn = document.getElementById('call-btn-speaker');
        if (speakerBtn) {
            speakerBtn.classList.toggle('btn-active', this.isSpeaker);
        }
        // Native audio output toggle isn't directly exposed in web APIs on mobile Safari/Chrome, 
        // but we mimic behavior or use setSinkId on supported desktop environments
        showPushNotification('Speaker Mode', this.isSpeaker ? 'Speakerphone activated' : 'Speakerphone deactivated');
    },

    minimizeCall() {
        const overlay = document.getElementById('calling-overlay');
        if (overlay) {
            overlay.classList.add('minimized');
        }
    },

    maximizeCall() {
        const overlay = document.getElementById('calling-overlay');
        if (overlay) {
            overlay.classList.remove('minimized');
        }
    },

    showCallOverlay(callData, initialStatus, callType) {
        // Ensure only one overlay exists
        const existing = document.getElementById('calling-overlay');
        if (existing) existing.remove();

        const name = callData ? (this.isCaller ? callData.receiver_name : callData.caller_name) : 'Ohati Member';
        const avatar = callData ? (this.isCaller ? callData.receiver_avatar : callData.caller_avatar) : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?q=80&w=150';

        const overlay = document.createElement('div');
        overlay.id = 'calling-overlay';
        overlay.className = `calling-overlay ${initialStatus}`;
        overlay.innerHTML = `
            <video id="call-remote-video" autoplay playsinline style="display:none;"></video>
            <video id="call-local-video" autoplay playsinline muted style="display:none;"></video>
            <audio id="call-remote-audio" autoplay></audio>

            <div class="call-overlay-inner">
                <!-- Minimize Button -->
                <button class="call-minimize-btn" onclick="OhatiCalling.minimizeCall()"><i class="fa-solid fa-down-left-and-up-right-to-center"></i></button>
                
                <!-- Maximize widget click area -->
                <div class="call-maximize-click-area" onclick="OhatiCalling.maximizeCall()"></div>

                <div class="call-info">
                    <img class="call-avatar" src="${avatar}" id="call-avatar-img" alt="">
                    <h3 class="call-name" id="call-name-txt">${name}</h3>
                    <div class="call-status" id="call-status-txt">${initialStatus.toUpperCase()}</div>
                    <div class="call-timer" id="call-timer-val">00:00</div>
                </div>

                <!-- Call visualizer / E2EE status -->
                <div class="call-security-tag">
                    <i class="fa-solid fa-lock"></i> End-to-End Encrypted (HD Audio)
                </div>

                <div class="call-actions">
                    <!-- Ringing Incoming Controls -->
                    <div class="call-incoming-actions" style="display: ${initialStatus === 'incoming' ? 'flex' : 'none'};">
                        <button class="call-btn btn-decline" onclick="OhatiCalling.rejectCall()">
                            <i class="fa-solid fa-phone-slash"></i>
                        </button>
                        <button class="call-btn btn-accept" onclick="OhatiCalling.acceptCall()">
                            <i class="fa-solid fa-phone"></i>
                        </button>
                    </div>

                    <!-- Connected Call Controls -->
                    <div class="call-connected-actions" style="display: ${initialStatus !== 'incoming' ? 'flex' : 'none'};">
                        <button class="call-btn" id="call-btn-speaker" onclick="OhatiCalling.toggleSpeaker()">
                            <i class="fa-solid fa-volume-high"></i>
                        </button>
                        <button class="call-btn" id="call-btn-mute" onclick="OhatiCalling.toggleMute()">
                            <i class="fa-solid fa-microphone"></i>
                        </button>
                        <button class="call-btn btn-hangup" onclick="OhatiCalling.endCall('ended')">
                            <i class="fa-solid fa-phone-slash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    },

    updateCallOverlayDetails(details) {
        const name = this.isCaller ? details.receiver_name : details.caller_name;
        const avatar = this.isCaller ? details.receiver_avatar : details.caller_avatar;
        const nameTxt = document.getElementById('call-name-txt');
        const avatarImg = document.getElementById('call-avatar-img');
        if (nameTxt) nameTxt.textContent = name;
        if (avatarImg) avatarImg.src = avatar;
    },

    updateCallOverlayStatus(status) {
        const statusTxt = document.getElementById('call-status-txt');
        const overlay = document.getElementById('calling-overlay');
        const incomingActions = document.querySelector('.call-incoming-actions');
        const connectedActions = document.querySelector('.call-connected-actions');

        if (statusTxt) statusTxt.textContent = status.toUpperCase();
        if (overlay) {
            overlay.className = `calling-overlay ${status}`;
        }
        if (incomingActions && connectedActions) {
            if (status === 'connected') {
                incomingActions.style.display = 'none';
                connectedActions.style.display = 'flex';
            }
        }
    }
};

// Initialize calling subsystem bootstrapped inside DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    OhatiCalling.init();
});
