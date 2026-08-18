// js/calling.js — Production Direct Phone App Call System
(function() {
    'use strict';

    window.OhatiCalling = {
        startCall: function(targetId, type = 'voice', nameHint = '', phoneHint = '') {
            window.initiateVoiceCall(targetId, nameHint, phoneHint);
        },
        endCall: function() {
            const el = document.getElementById('phone-dialer-action-modal');
            if (el) el.remove();
        }
    };

    window.showPhoneDialerModal = function(name, phone) {
        const existing = document.getElementById('phone-dialer-action-modal');
        if (existing) existing.remove();

        const cleanPhone = (phone || '').toString().replace(/[^0-9+]/g, '');

        const modal = document.createElement('div');
        modal.id = 'phone-dialer-action-modal';
        modal.className = 'modal-overlay open';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; z-index:100000; padding:20px; animation:fadeIn 0.2s ease;';

        modal.innerHTML = `
            <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.12); border-radius:20px; width:100%; max-width:380px; padding:24px; text-align:center; box-shadow:0 20px 50px rgba(0,0,0,0.6); color:#FFF;">
                <div style="width:64px; height:64px; border-radius:50%; background:rgba(16,185,129,0.15); border:2px solid #10B981; margin:0 auto 16px auto; display:flex; align-items:center; justify-content:center; color:#10B981; font-size:1.6rem;">
                    <i class="fa-solid fa-phone-flip"></i>
                </div>

                <h3 style="font-size:1.25rem; font-weight:700; margin:0 0 6px 0; color:#FFF;">${name || 'Ohati Contact'}</h3>
                <p style="font-size:1.15rem; font-weight:700; color:#10B981; letter-spacing:0.5px; margin:0 0 20px 0;">${phone || 'No phone number'}</p>

                <div style="display:flex; flex-direction:column; gap:12px;">
                    <a href="tel:${cleanPhone}" onclick="document.getElementById('phone-dialer-action-modal')?.remove();" style="width:100%; padding:14px; background:linear-gradient(135deg, #10B981, #059669); color:#FFF; font-weight:700; border-radius:12px; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; font-size:1rem; box-shadow:0 6px 20px rgba(16,185,129,0.35);">
                        <i class="fa-solid fa-phone"></i> Call via Phone App
                    </a>

                    <button onclick="if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText('${cleanPhone}'); if(typeof showPushNotification==='function') showPushNotification('Copied', 'Phone number copied to clipboard.'); else alert('Copied to clipboard'); }" style="width:100%; padding:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#E5E7EB; font-weight:600; border-radius:12px; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; justify-content:center; gap:6px;">
                        <i class="fa-solid fa-copy"></i> Copy Number
                    </button>

                    <button onclick="document.getElementById('phone-dialer-action-modal')?.remove();" style="width:100%; padding:10px; background:none; border:none; color:#9CA3AF; font-weight:600; cursor:pointer; font-size:0.85rem; margin-top:4px;">
                        Cancel
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    };

    window.initiateVoiceCall = function(targetId, nameHint, phoneHint) {
        if (phoneHint && phoneHint.toString().trim()) {
            window.showPhoneDialerModal(nameHint || 'Ohati Contact', phoneHint);
            return;
        }

        if (typeof showPushNotification === 'function') {
            showPushNotification("Connecting...", "Retrieving contact number...");
        }

        const apiUrl = (window.getOhatiApiBaseUrl ? window.getOhatiApiBaseUrl() : 'api.php') + '?action=get_call_number&id=' + (targetId || 0);
        fetch(apiUrl)
            .then(r => r.json())
            .then(res => {
                const phone = res.phone || res.whatsapp || '';
                const name = res.name || nameHint || 'Ohati Contact';
                if (phone) {
                    window.showPhoneDialerModal(name, phone);
                } else {
                    if (typeof showPushNotification === 'function') {
                        showPushNotification("Phone Unavailable", "This user/vendor has not listed a phone number.");
                    } else {
                        alert("This user/vendor has not listed a phone number.");
                    }
                }
            })
            .catch(() => {
                if (typeof showPushNotification === 'function') {
                    showPushNotification("Contact Error", "Could not retrieve phone number.");
                }
            });
    };

    window.endVoiceCall = function() {
        const el = document.getElementById('phone-dialer-action-modal');
        if (el) el.remove();
    };
})();
