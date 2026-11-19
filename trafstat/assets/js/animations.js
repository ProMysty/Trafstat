/* ====================================================================
   TrafStat — motion runtime
   - Animated numeric counters on [data-count-target]
   - Toast notifications from window.TRAF_FLASHES
   - Scroll reveal on [data-reveal] (used on public pages later)
   - window.trafHideSkeleton(canvas) helper for chart scripts
   Respects prefers-reduced-motion for all JS-driven motion.
   ==================================================================== */
(function () {
    'use strict';

    var prefersReducedMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.addEventListener('DOMContentLoaded', function () {
        initCounters();
        initToasts();
        initReveal();
    });

    /* ---- Animated counters ---------------------------------------- */
    function initCounters() {
        var els = document.querySelectorAll('[data-count-target]');
        els.forEach(function (el) {
            var target = parseInt(el.getAttribute('data-count-target'), 10);
            if (!target || isNaN(target) || target === 0) {
                // Ensure the number renders correctly for 0 / non-numbers
                return;
            }

            if (prefersReducedMotion) {
                // Keep the server-rendered value; no animation
                return;
            }

            var duration = 1100;
            var startTime = null;
            var formatter = function (n) { return n.toLocaleString(); };

            el.textContent = '0';

            function step(timestamp) {
                if (startTime === null) { startTime = timestamp; }
                var progress = Math.min((timestamp - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                var current = Math.floor(target * eased);
                el.textContent = formatter(current);
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    el.textContent = formatter(target);
                }
            }
            requestAnimationFrame(step);
        });
    }

    /* ---- Toasts --------------------------------------------------- */
    function initToasts() {
        var flashes = window.TRAF_FLASHES;
        if (!Array.isArray(flashes) || flashes.length === 0) { return; }

        var container = document.getElementById('traf-toast-container');
        if (!container) { return; }

        flashes.forEach(function (flash, index) {
            var toast = document.createElement('div');
            var type = ['success', 'error', 'warning', 'info'].indexOf(flash.type) >= 0
                ? flash.type
                : 'info';
            toast.className = 'traf-toast traf-toast--' + type;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'polite');

            var icon = 'info-circle-fill';
            if (type === 'success') { icon = 'check-circle-fill'; }
            else if (type === 'error') { icon = 'exclamation-circle-fill'; }
            else if (type === 'warning') { icon = 'exclamation-triangle-fill'; }

            toast.innerHTML =
                '<i class="bi bi-' + icon + ' traf-toast-icon" aria-hidden="true"></i>'
                + '<div class="traf-toast-body">' + escapeHtml(flash.message) + '</div>'
                + '<button type="button" class="traf-toast-close" aria-label="Close notification">'
                +   '<i class="bi bi-x-lg"></i>'
                + '</button>';

            container.appendChild(toast);

            var dismissed = false;
            function dismiss() {
                if (dismissed) { return; }
                dismissed = true;
                toast.classList.add('is-leaving');
                setTimeout(function () {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, prefersReducedMotion ? 0 : 240);
            }

            toast.querySelector('.traf-toast-close')
                .addEventListener('click', dismiss);

            // Stagger auto-dismissal so multiple toasts don't all vanish together
            var dismissDelay = 5000 + index * 500;
            setTimeout(dismiss, dismissDelay);
        });
    }

    /* ---- Scroll reveal -------------------------------------------- */
    function initReveal() {
        var els = document.querySelectorAll('[data-reveal]');
        if (els.length === 0) { return; }

        if (prefersReducedMotion || !('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('is-revealed'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '0px 0px -60px 0px',
            threshold: 0.08
        });

        els.forEach(function (el) { observer.observe(el); });
    }

    /* ---- Global helper for chart scripts -------------------------- */
    window.trafHideSkeleton = function (canvas) {
        if (!canvas) { return; }
        var parent = canvas.closest('.chart-card-body')
                || canvas.closest('.public-chart-container');
        if (!parent) { return; }
        var skeleton = parent.querySelector('.chart-skeleton');
        if (!skeleton) { return; }

        skeleton.classList.add('is-hidden');
        setTimeout(function () {
            if (skeleton.parentNode) {
                skeleton.parentNode.removeChild(skeleton);
            }
        }, prefersReducedMotion ? 0 : 300);
    };

    /* ---- Small utility -------------------------------------------- */
    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }
})();