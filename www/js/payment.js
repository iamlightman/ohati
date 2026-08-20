// js/payment.js — Admin-Controlled Manual Payment & Verification Module

window.initiateBookingPayment = function(bid, amount, type = 'deposit') {
    if (typeof showPushNotification === 'function') {
        showPushNotification('Booking Request Confirmed', 'Your booking request and invoice have been generated.');
    }
    return Promise.resolve({ success: true, direct_booking: true });
};

function renderManualPaymentModal(bookingId, reference, amount, details) {
    let modal = document.getElementById('manualPaymentModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'manualPaymentModal';
        modal.style.cssText = `
            position: fixed; inset: 0; z-index: 99999;
            background: rgba(8, 23, 41, 0.75); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            padding: 16px; font-family: system-ui, sans-serif;
        `;
        document.body.appendChild(modal);
    }

    const fmtAmount = parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2 });

    modal.innerHTML = `
        <div style="background: #FFFFFF; border-radius: 16px; max-width: 480px; width: 100%; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3); border: 1px solid #E2E8F0;">
            <div style="background: linear-gradient(135deg, #081729 0%, #1E293B 100%); color: #FFF; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin:0; font-size: 1.15rem; font-weight: 700; color: #F1F5F9;">Complete Payment</h3>
                    <div style="font-size: 0.8rem; color: #94A3B8; margin-top: 2px;">Booking #${bookingId} • Ref: ${escapeHtml(reference)}</div>
                </div>
                <button onclick="closeManualPaymentModal()" style="background: none; border: none; color: #94A3B8; font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="padding: 20px 24px; max-height: 75vh; overflow-y: auto;">
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px; margin-bottom: 20px; text-align: center;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #64748B; font-weight: 600; letter-spacing: 0.5px;">Amount To Transfer</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #081729; margin-top: 4px;">GH₵ ${fmtAmount}</div>
                    <div style="font-size: 0.75rem; color: #10B981; font-weight: 600; margin-top: 4px;"><i class="fa-solid fa-shield-halved"></i> Secured with Ohati Direct Protection</div>
                </div>

                <div style="margin-bottom: 20px;">
                    <div style="font-size: 0.85rem; font-weight: 700; color: #0F172A; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-building-columns" style="color: #38BDF8;"></i> Admin Payment Instructions
                    </div>
                    
                    <div style="display: grid; gap: 10px; font-size: 0.85rem;">
                        <div style="background: #F1F5F9; padding: 10px 14px; border-radius: 8px; display: flex; justify-content: space-between;">
                            <span style="color: #64748B;">Bank Name:</span>
                            <strong style="color: #0F172A;">${escapeHtml(details.bank_name || 'Ecobank Ghana')}</strong>
                        </div>
                        <div style="background: #F1F5F9; padding: 10px 14px; border-radius: 8px; display: flex; justify-content: space-between;">
                            <span style="color: #64748B;">Account Name:</span>
                            <strong style="color: #0F172A;">${escapeHtml(details.account_name || 'Ohati Global')}</strong>
                        </div>
                        <div style="background: #F1F5F9; padding: 10px 14px; border-radius: 8px; display: flex; justify-content: space-between;">
                            <span style="color: #64748B;">Account Number:</span>
                            <strong style="color: #081729; font-family: monospace; font-size: 0.95rem;">${escapeHtml(details.account_number || '1441002939201')}</strong>
                        </div>
                        <div style="background: #FEF3C7; border: 1px solid #FDE68A; padding: 10px 14px; border-radius: 8px; display: flex; justify-content: space-between;">
                            <span style="color: #92400E; font-weight: 600;">Mobile Money:</span>
                            <strong style="color: #78350F; font-family: monospace; font-size: 0.95rem;">${escapeHtml(details.momo_number || '0540477911')} (${escapeHtml(details.momo_name || 'Ohati')})</strong>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-color, #0F172A); margin-bottom: 8px;">
                        Upload Payment Receipt / Transfer Screenshot <span style="color: #EF4444;">*</span>
                    </label>
                    <div id="manualReceiptDropzone" onclick="document.getElementById('manualReceiptFileInput').click()" style="cursor: pointer; padding: 20px; text-align: center; border: 2px dashed var(--accent, #F2A735); border-radius: 12px; background: rgba(242, 167, 53, 0.05); transition: all 0.2s ease;">
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2rem; color: var(--accent, #F2A735); margin-bottom: 8px; display: block;"></i>
                        <strong id="manualReceiptStatusTitle" style="display: block; font-size: 0.9rem; color: var(--text-color, #0F172A);">📎 Tap to Upload Payment Receipt</strong>
                        <span id="manualReceiptStatusSub" style="font-size: 0.75rem; color: var(--gray-500, #64748B);">Upload bank receipt screenshot or MoMo confirmation SMS screenshot (Max 20MB)</span>
                        <input type="file" id="manualReceiptFileInput" accept="image/*,application/pdf" style="display: none;" onchange="handleManualPaymentFileSelected(event)">
                    </div>
                </div>

                <button onclick="submitManualPaymentProof('${escapeHtml(reference)}')" style="width: 100%; padding: 14px; background: var(--primary, #081729); color: #FFF; border: none; border-radius: 10px; font-weight: 700; font-size: 0.95rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa-solid fa-paper-plane"></i> Submit Payment Evidence
                </button>
            </div>
        </div>
    `;

    modal.style.display = 'flex';
}

window.handleManualPaymentFileSelected = function(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    if (file.size > 20 * 1024 * 1024) {
        if (typeof showPushNotification === 'function') showPushNotification("File Too Large", "Receipt file size cannot exceed 20MB.");
        event.target.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        window._manualPaymentReceiptData = e.target.result;
        const titleEl = document.getElementById('manualReceiptStatusTitle');
        const subEl = document.getElementById('manualReceiptStatusSub');
        const dropzone = document.getElementById('manualReceiptDropzone');
        if (titleEl) titleEl.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Receipt Selected: ${escapeHtml(file.name)}`;
        if (subEl) subEl.textContent = `Size: ${(file.size / 1024 / 1024).toFixed(2)} MB - Ready to Submit`;
        if (dropzone) {
            dropzone.style.background = "rgba(16, 185, 129, 0.08)";
            dropzone.style.borderColor = "#10B981";
        }
    };
    reader.readAsDataURL(file);
};

window.closeManualPaymentModal = function() {
    const modal = document.getElementById('manualPaymentModal');
    if (modal) {
        modal.style.display = 'none';
    }
};

window.submitManualPaymentProof = function(reference) {
    const receipt = window._manualPaymentReceiptData || '';

    if (!receipt) {
        if (typeof showPushNotification === 'function') {
            showPushNotification('Receipt Required', 'Please tap to select and upload your payment receipt before submitting.');
        } else {
            alert('Please select your payment receipt file.');
        }
        return;
    }

    if (typeof showPushNotification === 'function') {
        showPushNotification('Submitting Payment', 'Sending evidence for Admin verification...');
    }

    API.post('submit_payment_receipt', {
        booking_id: reference,
        receipt_image: receipt
    })
    .then(res => {
        closeManualPaymentModal();
        window._manualPaymentReceiptData = '';
        if (typeof showPushNotification === 'function') {
            showPushNotification('Submission Received 🎉', 'Payment receipt uploaded successfully! Your booking is now pending Admin verification.');
        } else {
            alert('Payment receipt uploaded successfully! Your booking is now pending Admin verification.');
        }
        if (typeof renderBookings === 'function') {
            renderBookings();
        } else {
            location.reload();
        }
    })
    .catch(err => {
        if (typeof showPushNotification === 'function') {
            showPushNotification('Submission Error', err.message || 'Could not submit payment evidence.');
        } else {
            alert(err.message || 'Could not submit payment evidence.');
        }
    });
};

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
