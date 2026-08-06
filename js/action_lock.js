// js/action_lock.js — Application-Wide Action Loading & De-Duplication Engine
(function() {
    'use strict';

    window.ActionLock = {
        _activeKeys: new Set(),

        /**
         * Execute an asynchronous action with automatic button locking, spinner, and de-duplication.
         * @param {HTMLElement|EventTarget|string} elementOrSelector - Button, form, or element triggered
         * @param {string} loadingText - Text to display (e.g. "Processing...", "Saving...", "Verifying...")
         * @param {Function} asyncFn - Async function returning a promise
         * @param {Object} options - { keepDisabledOnSuccess: false, icon: 'fa-solid fa-spinner fa-spin', key: null }
         */
        async execute(elementOrSelector, loadingText, asyncFn, options = {}) {
            let el = typeof elementOrSelector === 'string' ? document.querySelector(elementOrSelector) : elementOrSelector;
            if (el && el.target) el = el.target;
            if (el && el.tagName === 'FORM') {
                el = el.querySelector('button[type="submit"], input[type="submit"]') || el;
            }

            // Derive a unique lock key for de-duplication
            const actionKey = options.key || (el ? (el.id || el.name || el.dataset.actionKey || el.innerText.trim() || 'action_' + Math.random()) : 'global_lock');

            if (this._activeKeys.has(actionKey)) {
                console.warn(`[ActionLock] Duplicate action blocked for key: "${actionKey}"`);
                return;
            }

            if (el && (el.dataset.actionLocked === 'true' || el.disabled)) {
                return;
            }

            this._activeKeys.add(actionKey);

            let origContent = '';
            let origDisabled = false;
            let origPointerEvents = '';

            if (el && el.tagName) {
                el.dataset.actionLocked = 'true';
                origDisabled = el.disabled;
                origPointerEvents = el.style.pointerEvents;
                el.disabled = true;
                el.style.pointerEvents = 'none';
                el.setAttribute('aria-disabled', 'true');
                origContent = el.innerHTML;

                const spinnerIcon = options.icon || 'fa-solid fa-spinner fa-spin';
                el.innerHTML = `<i class="${spinnerIcon}" style="margin-right:6px;"></i> ${loadingText}`;
            }

            try {
                const result = await asyncFn();

                if (options.keepDisabledOnSuccess && el) {
                    el.disabled = true;
                    el.style.pointerEvents = 'none';
                    el.innerHTML = options.successContent || `<i class="fa-solid fa-circle-check" style="margin-right:6px;"></i> Completed`;
                } else if (el) {
                    el.disabled = origDisabled;
                    el.style.pointerEvents = origPointerEvents || 'auto';
                    el.removeAttribute('aria-disabled');
                    el.innerHTML = origContent;
                    delete el.dataset.actionLocked;
                }

                return result;
            } catch (err) {
                if (el) {
                    el.disabled = origDisabled;
                    el.style.pointerEvents = origPointerEvents || 'auto';
                    el.removeAttribute('aria-disabled');
                    el.innerHTML = origContent;
                    delete el.dataset.actionLocked;
                }
                throw err;
            } finally {
                this._activeKeys.delete(actionKey);
            }
        }
    };

    // Automatic global click & submit interceptor for buttons/forms with data-loading-text
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('button[data-loading-text], a[data-loading-text], input[type="submit"][data-loading-text]');
        if (!btn || btn.dataset.actionLocked === 'true' || btn.disabled) return;

        const loadingText = btn.dataset.loadingText || 'Processing...';
        const keepDisabled = btn.hasAttribute('data-keep-disabled');

        btn.dataset.actionLocked = 'true';
        btn.disabled = true;
        btn.style.pointerEvents = 'none';
        btn.setAttribute('aria-disabled', 'true');
        const origContent = btn.innerHTML;

        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> ${loadingText}`;

        // Re-enable safety fallback after 12 seconds in case request hangs
        setTimeout(() => {
            if (btn && btn.dataset.actionLocked === 'true' && !keepDisabled) {
                btn.disabled = false;
                btn.style.pointerEvents = 'auto';
                btn.removeAttribute('aria-disabled');
                btn.innerHTML = origContent;
                delete btn.dataset.actionLocked;
            }
        }, 12000);
    }, true);

    // Prevent double Enter key form submissions
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const form = e.target.closest('form');
            if (form && form.dataset.submitting === 'true') {
                e.preventDefault();
                e.stopPropagation();
            }
        }
    }, true);
})();
