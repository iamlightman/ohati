// js/notification.js — Production Notification System Module

window.fetchNotifications = function() {
    const uid = state.user?.id || 0;
    const query = uid ? `?user_id=${uid}` : '';
    return API.get('notifications' + query).then(notifs => {
        state.notifications = Array.isArray(notifs) ? notifs : [];
        window.updateNotificationBadgeUI(state.notifications);
        return state.notifications;
    }).catch(() => []);
};

window.initNotificationModule = function() {
    window.fetchNotifications();

    if (!window._notifInterval) {
        window._notifInterval = setInterval(() => {
            window.fetchNotifications();
        }, 10000);
    }
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
