// js/helpers.js — Shared Production Helper Utilities for Ohati Marketplace

/** Format numbers into compact form (1.2K, 3.4M) */
function formatCompact(n) {
    const num = parseFloat(n || 0);
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
}

/** Format currency with GHS prefix and two decimals */
function formatCurrency(amount) {
    const val = parseFloat(amount || 0);
    return 'GH₵ ' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Robustly parse database date string into local JS Date object */
function parseAppDate(dateStr) {
    if (!dateStr) return new Date();
    if (dateStr instanceof Date) return dateStr;
    if (typeof dateStr === 'number') return new Date(dateStr);

    let str = String(dateStr).trim();
    // Match "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DDTHH:MM:SS"
    const match = str.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?/);
    if (match) {
        const [, year, month, day, hour, minute, second] = match;
        return new Date(
            parseInt(year, 10),
            parseInt(month, 10) - 1,
            parseInt(day, 10),
            parseInt(hour, 10),
            parseInt(minute, 10),
            parseInt(second || '0', 10)
        );
    }
    const fallback = new Date(str);
    return isNaN(fallback.getTime()) ? new Date() : fallback;
}

/** Format friendly date: "Dec 12, 2027" */
function formatFriendlyDate(dateStr) {
    if (!dateStr) return 'Not set';
    const d = parseAppDate(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

/** Format friendly date and time: "Dec 12, 2027 at 10:35 PM" */
function formatFriendlyDateTime(dateStr) {
    if (!dateStr) return 'Not set';
    const d = parseAppDate(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) + ' at ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/** Format real-time clock timestamp across all screens: "Today, 10:37 PM", "Yesterday, 8:15 AM", or "Jul 29, 2026, 10:37 PM" */
function formatRelativeTime(dateStr) {
    if (!dateStr) return '';
    const d = parseAppDate(dateStr);
    if (isNaN(d.getTime())) return '';

    const now = new Date();
    const timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });

    const isToday = d.toDateString() === now.toDateString();
    if (isToday) {
        let diff = Math.floor((now.getTime() - d.getTime()) / 1000);
        if (diff >= -5 && diff < 60) {
            return `Just now (${timeStr})`;
        }
        return `Today, ${timeStr}`;
    }

    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return `Yesterday, ${timeStr}`;
    }

    const isSameYear = d.getFullYear() === now.getFullYear();
    if (isSameYear) {
        return `${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}, ${timeStr}`;
    }

    return `${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}, ${timeStr}`;
}

/** Global 30-second ticker to keep live relative timestamps fresh on screen */
if (typeof window.liveTimeTicker === 'undefined') {
    window.liveTimeTicker = null;
}
function initLiveTimeTicker() {
    if (window.liveTimeTicker) return;
    window.liveTimeTicker = setInterval(() => {
        const els = document.querySelectorAll('.live-relative-time');
        els.forEach(el => {
            const stamp = el.getAttribute('data-timestamp');
            if (stamp) {
                el.textContent = formatRelativeTime(stamp);
            }
        });
    }, 30000);
}

// Global bindings for backward compatibility
window.formatCompact = formatCompact;
window.formatCurrency = formatCurrency;
window.parseAppDate = parseAppDate;
window.formatFriendlyDate = formatFriendlyDate;
window.formatFriendlyDateTime = formatFriendlyDateTime;
window.formatRelativeTime = formatRelativeTime;
window.initLiveTimeTicker = initLiveTimeTicker;

if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', initLiveTimeTicker);
}
