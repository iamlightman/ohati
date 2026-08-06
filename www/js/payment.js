// js/payment.js — Production Escrow Payments & Financial Module

window.initiateBookingPayment = function(bid, amount, method = 'paystack') {
    if (!bid || !amount || parseFloat(amount) <= 0) {
        showPushNotification('Payment Error', 'Invalid booking or payment amount.');
        return Promise.reject(new Error('Invalid amount'));
    }

    showPushNotification('Processing Payment', 'Connecting to secure escrow gateway...');

    return API.post('record_payment', {
        booking_id: bid,
        amount: amount,
        method: method,
        type: 'deposit',
        provider: method
    })
    .then(res => {
        showPushNotification('Payment Successful', 'GH₵ ' + parseFloat(amount).toLocaleString() + ' deposited safely into escrow.');
        if (typeof showPaymentReceipt === 'function') {
            const booking = (state.bookings || []).find(b => Number(b.id) === Number(bid));
            showPaymentReceipt(booking, amount, 'PAY-' + Date.now().toString(36).toUpperCase());
        }
        return res;
    })
    .catch(err => {
        showPushNotification('Payment Failed', err.message || 'Transaction could not be completed.');
        throw err;
    });
};
