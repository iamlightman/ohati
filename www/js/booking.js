// js/booking.js — Production Booking Lifecycle Handler for Ohati Marketplace

window.openBookingDetailsModal = function(bid) {
    let booking = (state.bookings && Array.isArray(state.bookings)) ? state.bookings.find(b => Number(b.id) === Number(bid)) : null;
    
    if (!booking) {
        API.getBookings().then(bookings => {
            state.bookings = bookings;
            const found = bookings.find(b => Number(b.id) === Number(bid));
            if (found) window.openBookingDetailsModal(bid);
            else showPushNotification('Error', 'Booking detail not found.');
        });
        return;
    }

    const isVendor = state.user && (state.user.active_role === 'vendor');
    const negPrice = parseFloat(booking.negotiated_price || booking.price || 0);
    const totalPaid = parseFloat(booking.total_paid || 0);
    const remaining = Math.max(0, negPrice - totalPaid);
    const createdTimeStr = formatRelativeTime(booking.created_at || Date.now());
    const refCode = '#OHT-B' + String(booking.id).padStart(5, '0');

    const html = `
        <div style="max-width:480px; margin:0 auto;">
            <!-- Modal Header -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:12px; border-bottom:1px solid var(--gray-200); margin-bottom:12px;">
                <div>
                    <div style="font-size:0.7rem; font-weight:800; color:var(--primary); letter-spacing:0.5px;">${refCode}</div>
                    <h3 style="margin:2px 0 0 0; font-size:1.05rem; font-weight:700;">${isVendor ? (booking.user_name || 'Customer') : (booking.vendor_name || 'Vendor')}</h3>
                    <p style="margin:2px 0 0 0; font-size:0.72rem; color:var(--gray-500);">
                        ${isVendor ? (booking.package_name || booking.event_type || 'Event Package') : (booking.vendor_category + ' • ' + (booking.event_type || 'Event'))}
                    </p>
                </div>
                <span class="booking-status ${booking.status === 'Inquiry' ? 'status-pending' : 'status-confirmed'}">${booking.status}</span>
            </div>

            <!-- Real-Time Timestamp Banner -->
            <div style="background:var(--gray-100); padding:8px 12px; border-radius:8px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; font-size:0.72rem;">
                <span style="color:var(--gray-700); font-weight:600;"><i class="fa-regular fa-clock" style="color:var(--primary);"></i> Requested: <strong>${createdTimeStr}</strong></span>
                <span style="color:var(--gray-500); font-size:0.68rem;">${formatFriendlyDate(booking.created_at)}</span>
            </div>

            <!-- Contact & Booking Grid -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; background:var(--gray-50); padding:12px; border-radius:10px; border:1px solid var(--gray-100); margin-bottom:14px; font-size:0.75rem;">
                <div>
                    <span style="color:var(--gray-500); font-size:0.68rem; display:block;">${isVendor ? 'CUSTOMER CONTACT' : 'VENDOR DETAILS'}</span>
                    <strong style="color:var(--gray-800); font-size:0.8rem;">${isVendor ? booking.user_name : booking.vendor_name}</strong>
                    <div style="margin-top:4px;">
                        <a href="tel:${isVendor ? booking.user_phone : booking.vendor_phone}" style="color:var(--primary); text-decoration:none; display:flex; align-items:center; gap:4px; margin-top:2px;">
                            <i class="fa-solid fa-phone"></i> ${isVendor ? booking.user_phone : booking.vendor_phone}
                        </a>
                        ${booking.user_email ? `<div style="color:var(--gray-600); font-size:0.68rem; margin-top:2px; word-break:break-all;"><i class="fa-solid fa-envelope"></i> ${booking.user_email}</div>` : ''}
                    </div>
                </div>

                <div>
                    <span style="color:var(--gray-500); font-size:0.68rem; display:block;">EVENT & PRICING</span>
                    <div style="margin-top:2px;"><i class="fa-solid fa-calendar-day" style="color:var(--primary);"></i> Date: <strong>${formatFriendlyDate(booking.event_date)}</strong></div>
                    <div style="margin-top:2px;"><i class="fa-solid fa-tag" style="color:var(--primary);"></i> Total: <strong>GH₵ ${negPrice.toLocaleString(undefined,{minimumFractionDigits:2})}</strong></div>
                    <div style="margin-top:2px; font-size:0.7rem; color:var(--primary); font-weight:700;">
                        <i class="fa-solid fa-file-invoice"></i> Invoice Generated
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            ${booking.notes ? `
                <div style="margin-bottom:14px;">
                    <span style="font-size:0.7rem; font-weight:700; color:var(--gray-600); display:block; margin-bottom:4px;">CUSTOMER INSTRUCTIONS / NOTES:</span>
                    <div style="background:var(--gray-100); padding:10px; border-radius:8px; font-size:0.75rem; color:var(--gray-800); border-left:3px solid var(--primary);">
                        ${booking.notes}
                    </div>
                </div>
            ` : ''}

            <!-- Booking Progress Timeline -->
            <div style="font-size:0.7rem; font-weight:700; color:var(--gray-500); margin-bottom:8px; letter-spacing:0.5px; text-transform:uppercase;">STAGES & PROGRESS</div>
            <div class="booking-timeline mb-16" style="margin-left:4px;">
                ${booking.timeline && booking.timeline.length > 0 ? booking.timeline.map((t, idx) => `
                    <div class="booking-stage">
                        <div class="stage-dot ${idx === booking.timeline.length - 1 ? 'active' : 'done'}" style="width:20px; height:20px; font-size:0.6rem;">
                            ${idx === booking.timeline.length - 1 ? '<i class="fa-solid fa-spinner fa-spin"></i>' : '<i class="fa-solid fa-check"></i>'}
                        </div>
                        <div class="stage-info" style="margin-left:12px;">
                            <div class="stage-label" style="font-size:0.78rem; font-weight:700; color:var(--gray-800);">${t.status}</div>
                            <div class="stage-date" style="font-size:0.62rem; color:var(--gray-500);">${formatRelativeTime(t.timestamp)}</div>
                            ${t.notes ? `<div class="stage-note" style="font-size:0.7rem; margin-top:2px;">${t.notes}</div>` : ''}
                        </div>
                    </div>
                `).join('') : '<div class="text-center text-muted text-sm p-8">Inquiry Submitted</div>'}
            </div>

            <!-- Action Buttons Row -->
            <div style="display:flex; flex-direction:column; gap:8px; margin-top:16px;">
                ${isVendor ? `
                    <div style="display:flex; gap:8px;">
                        ${booking.status === 'Inquiry' ? `
                            <button class="btn btn-primary btn-full" onclick="updateBookingStatus(${booking.id}, 'Confirmed'); closeModal();" style="height:36px; font-size:0.78rem;"><i class="fa-solid fa-circle-check"></i> Accept Booking</button>
                            <button class="btn btn-outline btn-full" onclick="updateBookingStatus(${booking.id}, 'Cancelled'); closeModal();" style="height:36px; font-size:0.78rem; color:var(--error); border-color:var(--error);"><i class="fa-solid fa-ban"></i> Decline</button>
                        ` : ''}
                        ${booking.status === 'Confirmed' ? `
                            <button class="btn btn-primary btn-full" onclick="updateBookingStatus(${booking.id}, 'Completed'); closeModal();" style="height:36px; font-size:0.78rem;"><i class="fa-solid fa-flag-checkered"></i> Mark Completed</button>
                        ` : ''}
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-outline btn-full" onclick="startVendorChat(${booking.user_id || booking.vendor_id}); closeModal();" style="height:36px; font-size:0.75rem;"><i class="fa-solid fa-comments"></i> Chat with Client</button>
                        <button class="btn btn-outline btn-full" onclick="openBookingInvoice(${booking.id});" style="height:36px; font-size:0.75rem;"><i class="fa-solid fa-receipt"></i> Invoice</button>
                    </div>
                ` : `
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-outline btn-full" onclick="startVendorChat(${booking.vendor_id}); closeModal();" style="height:36px; font-size:0.75rem;"><i class="fa-solid fa-comments"></i> Chat Vendor</button>
                        <button class="btn btn-outline btn-full" onclick="openBookingInvoice(${booking.id});" style="height:36px; font-size:0.75rem;"><i class="fa-solid fa-receipt"></i> View Invoice</button>
                    </div>
                `}
                <button class="btn btn-primary btn-full" onclick="closeModal();" style="height:36px; font-size:0.8rem; margin-top:4px;">Close</button>
            </div>
        </div>
    `;
    openModal(html);
};

window.openBookingTimelineModal = function(bid) {
    window.openBookingDetailsModal(bid);
};

window.updateBookingStatus = function(bid, newStatus, event) {
    const btn = event?.target || document.querySelector(`button[onclick*="updateBookingStatus(${bid}"]`);
    ActionLock.execute(btn, 'Processing...', async () => {
        const res = await API.post('update_booking', { id: bid, status: newStatus });
        showPushNotification('Status Updated', 'Booking status changed to ' + newStatus);
        const bookings = await API.getBookings();
        state.bookings = bookings;
        if (typeof renderBookingsScreen === 'function') renderBookingsScreen(bookings);
        return res;
    }).catch(err => {
        showPushNotification('Error', err.message || 'Failed to update booking status.');
    });
};
