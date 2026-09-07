define([], function () {
    'use strict';

    return function () {
        var wrappers = document.querySelectorAll('.authentic-product-wrapper');

        wrappers.forEach(function (wrapper) {
            var infoButton = wrapper.querySelector('.authentic-info-button');
            var overlay = wrapper.querySelector('.authentic-overlay');
            var drawer = wrapper.querySelector('.authentic-drawer');
            var closeButton = wrapper.querySelector('.authentic-drawer-close');

            if (!infoButton || !overlay || !drawer || !closeButton) {
                return;
            }

            /**
             * Open Drawer Functionality
             */
            function openDrawer() {
                drawer.classList.add('is-open');
                overlay.classList.add('is-open');
                drawer.setAttribute('aria-hidden', 'false');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('authentic-drawer-open');
            }

            /**
             * Close Drawer Functionality
             */
            function closeDrawer() {
                drawer.classList.remove('is-open');
                overlay.classList.remove('is-open');
                drawer.setAttribute('aria-hidden', 'true');
                overlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('authentic-drawer-open');
            }

            // Event Listeners
            infoButton.addEventListener('click', openDrawer);
            closeButton.addEventListener('click', closeDrawer);
            overlay.addEventListener('click', closeDrawer);

            // Close Drawer with Keyboard ESC Key
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
                    closeDrawer();
                }
            });
        });
    };
});
