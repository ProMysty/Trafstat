/* ====================================================================
   TrafStat — shared front-end behaviour
   - Sidebar drawer toggle (mobile)
   - Show / hide password inputs
   - Client-side confirmation for [data-confirm] forms/buttons
   ==================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // --- Auto-submit forms on change -----------------------------------
        document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
            el.addEventListener('change', function () {
                if (el.form) { el.form.submit(); }
            });
        });

        // --- Print trigger -------------------------------------------------
        document.querySelectorAll('[data-print]').forEach(function (btn) {
            btn.addEventListener('click', function () { window.print(); });
        });

        // --- Close-window trigger ------------------------------------------
        document.querySelectorAll('[data-close-window]').forEach(function (btn) {
            btn.addEventListener('click', function () { window.close(); });
        });

        // --- Sidebar drawer --------------------------------------------
        var sidebar  = document.querySelector('[data-sidebar]');
        var toggle   = document.querySelector('[data-sidebar-toggle]');
        var overlay  = document.querySelector('[data-sidebar-overlay]');

        function closeSidebar() {
            if (sidebar)  sidebar.classList.remove('is-open');
            if (overlay)  overlay.classList.remove('is-open');
        }

        if (toggle && sidebar) {
            toggle.addEventListener('click', function () {
                var isOpen = sidebar.classList.toggle('is-open');
                if (overlay) overlay.classList.toggle('is-open', isOpen);
            });
        }
        if (overlay) overlay.addEventListener('click', closeSidebar);

        // Close drawer when a nav link is clicked on mobile
        document.querySelectorAll('.sidebar-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) closeSidebar();
            });
        });

        // --- Show / hide password -------------------------------------
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-toggle-password');
                var input    = document.getElementById(targetId);
                if (!input) return;
                var showing = input.type === 'text';
                input.type  = showing ? 'password' : 'text';
                var icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-eye',         showing);
                    icon.classList.toggle('bi-eye-slash',  !showing);
                }
            });
        });

        // --- Confirmation prompt --------------------------------------
        document.querySelectorAll('[data-confirm]').forEach(function (element) {
            var handler = function (event) {
                if (!window.confirm(element.getAttribute('data-confirm'))) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            };
            if (element.tagName === 'FORM') {
                element.addEventListener('submit', handler);
            } else {
                element.addEventListener('click', handler);
            }
        });
    });
})();