// js/notification.js — Production Notification System Module

window.initNotificationModule = function() {
    if (!state.user) return;
    
    // Poll notifications every 5 seconds
    API.get('notifications').then(notifs => {
        state.notifications = notifs;
        window.updateNotificationBadgeUI(notifs);
    }).catch(() => {});

    setInterval(() => {
        if (!state.user) return;
        API.get('notifications').then(notifs => {
            state.notifications = notifs;
            window.updateNotificationBadgeUI(notifs);
        }).catch(() => {});
    }, 5000);
};

window.updateNotificationBadgeUI = function(notifs) {
    const unread = (notifs || []).filter(n => parseInt(n.is_read) === 0);
    const badges = document.querySelectorAll('.notification-badge-count, #notif-badge-count');
    badges.forEach(b => {
        if (unread.length > 0) {
            b.textContent = unread.length;
            b.style.display = 'inline-flex';
        } else {
            b.style.display = 'none';
        }
    });
};

if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', () => {
        window.initNotificationModule();
    });
}
