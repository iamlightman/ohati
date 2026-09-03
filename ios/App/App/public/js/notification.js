// js/notification.js — Centralized Production Notification System Module

(function() {
    let seenNotifIds = new Set();
    let isInitialLoad = true;

    window.stopNotificationPolling = function() {
        if (window._notifInterval) {
            clearInterval(window._notifInterval);
            window._notifInterval = null;
        }
    };

    window.fetchNotifications = function() {
        const user = window.state?.user;
        if (!user || !user.id) {
            window.stopNotificationPolling();
            seenNotifIds.clear();
            isInitialLoad = true;
            window.updateNotificationBadgeUI([]);
            return Promise.resolve([]);
        }

        return API.getNotifications().then(notifs => {
            const list = Array.isArray(notifs) ? notifs : [];
            window.state.notifications = list;
            window.updateNotificationBadgeUI(list);

            const unread = list.filter(n => parseInt(n.is_read) === 0);

            if (isInitialLoad) {
                // On initial load, populate seen set for existing notifications to prevent popup spam
                unread.forEach(n => seenNotifIds.add(Number(n.id)));
                isInitialLoad = false;
            } else {
                // Check for new unread notifications
                unread.forEach(n => {
                    const nid = Number(n.id);
                    if (!seenNotifIds.has(nid)) {
                        seenNotifIds.add(nid);
                        if (typeof showPushNotification === 'function') {
                            showPushNotification(n.title || 'New Notification', n.body || '', 'info');
                        }
                    }
                });
            }

            return list;
        }).catch(() => []);
    };

    window.initNotificationModule = function() {
        window.stopNotificationPolling();
        window.fetchNotifications();

        window._notifInterval = setInterval(() => {
            if (window.state?.user?.id) {
                window.fetchNotifications();
            } else {
                window.stopNotificationPolling();
            }
        }, 10000);
    };

    window.updateNotificationBadgeUI = function(notifs) {
        const unread = (notifs || []).filter(n => parseInt(n.is_read) === 0);
        const badges = document.querySelectorAll('.notification-badge-count, #notif-badge-count, #notif-badge, .notif-badge');
        badges.forEach(b => {
            if (unread.length > 0) {
                b.textContent = unread.length;
                b.style.display = 'flex';
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
})();
