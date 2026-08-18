// js/vendor.js — Production Vendor Management & Dashboard Module

window.initVendorDashboardModule = function() {
    const list = document.getElementById('vendor-bookings-list');
    if (!list) return;

    API.getBookings().then(bookings => {
        state.bookings = bookings;
        if (!bookings || bookings.length === 0) {
            list.innerHTML = `<p class="text-sm text-muted text-center" style="padding:20px;">No bookings found yet.</p>`;
            return;
        }
        list.innerHTML = bookings.map(b => `
            <div class="booking-card" onclick="openBookingDetailsModal(${b.id})" style="padding:14px; margin-bottom:12px; cursor:pointer; border-radius:12px; transition:transform 0.2s ease;">
                <div class="booking-card-header" style="margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div class="booking-vendor-name" style="font-weight:700; font-size:0.92rem;">${b.user_name}</div>
                        <div class="booking-vendor-cat" style="font-size:0.75rem; color:var(--gray-500);">${b.event_type || 'Event'} — ${b.package_name || 'Package'}</div>
                    </div>
                    <span class="booking-status ${b.status === 'Inquiry' ? 'status-pending' : 'status-confirmed'}">${b.status}</span>
                </div>
                <div style="font-size:0.75rem; color:var(--gray-600); display:flex; flex-direction:column; gap:4px; padding-top:6px; border-top:1px dashed var(--gray-200);">
                    <div style="display:flex; justify-content:space-between;">
                        <span><i class="fa-solid fa-phone" style="color:var(--primary);"></i> Phone: <strong>${b.user_phone}</strong></span>
                        <span><i class="fa-solid fa-tag" style="color:var(--primary);"></i> <strong>GH₵ ${parseFloat(b.negotiated_price || b.price || 0).toLocaleString(undefined,{minimumFractionDigits:2})}</strong></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-top:2px;">
                        <span><i class="fa-solid fa-calendar-day" style="color:var(--primary);"></i> Event: <strong>${formatFriendlyDate(b.event_date)}</strong></span>
                        <span style="font-size:0.68rem; color:var(--gray-500);"><i class="fa-regular fa-clock"></i> ${formatRelativeTime(b.created_at)}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }).catch(err => {
        console.error("Vendor dashboard bookings load error:", err);
    });
};

window.toggleVendorAvailability = function(isAvailable) {
    API.post('update_vendor', { availability: isAvailable ? 'Available' : 'Unavailable' })
        .then(res => {
            showPushNotification('Availability Updated', isAvailable ? 'You are now marked as Available.' : 'You are now marked as Unavailable.');
        })
        .catch(err => {
            showPushNotification('Error', err.message || 'Could not update availability status.');
        });
};

window.submitVendorVerificationRequest = function(docType, docNumber) {
    if (!docNumber) {
        showPushNotification('Error', 'Please provide a valid document number.');
        return;
    }
    API.post('submit_profile_change', {
        field_name: 'verification_document',
        old_value: 'Unverified',
        new_value: docType + ': ' + docNumber
    })
    .then(res => {
        showPushNotification('Request Submitted', 'Your ID verification request has been sent for admin review.');
        if (typeof closeModal === 'function') closeModal();
    })
    .catch(err => {
        showPushNotification('Error', err.message || 'Failed to submit verification request.');
    });
};

if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('vendor-bookings-list')) {
            window.initVendorDashboardModule();
        }
    });
}


/* ===== GALLERY PAGINATION & DASHBOARD STATS FILTER HANDLERS ===== */
window.changePublicGalleryPage = function(delta) {
    if (!state._publicGalleryPage) state._publicGalleryPage = 1;
    state._publicGalleryPage += delta;
    if (state._publicGalleryPage < 1) state._publicGalleryPage = 1;
    if (typeof renderVendorDetailsScreen === 'function' && state.selectedVendorId) {
        renderVendorDetailsScreen(state.selectedVendorId);
    }
};

window.filterVendorStats = function(range, btnEl) {
    if (btnEl) {
        const parent = btnEl.parentElement;
        if (parent) {
            parent.querySelectorAll('.date-filter-btn').forEach(b => {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-outline');
            });
            btnEl.classList.remove('btn-outline');
            btnEl.classList.add('btn-primary', 'active');
        }
    }
    if (typeof renderVendorDashboardScreen === 'function') {
        renderVendorDashboardScreen();
    }
};
