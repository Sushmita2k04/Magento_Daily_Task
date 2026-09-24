define([], function () {
    'use strict';

    return function (config, element) {
        var wrapper = element;

        if (!wrapper) {
            return;
        }

        var infoButton = wrapper.querySelector(
            '.authentic-info-button'
        );

        var overlay = wrapper.querySelector(
            '.authentic-overlay'
        );

        var drawer = wrapper.querySelector(
            '.authentic-drawer'
        );

        var closeButton = wrapper.querySelector(
            '.authentic-drawer-close'
        );

        if (
            !infoButton ||
            !overlay ||
            !drawer ||
            !closeButton
        ) {
            return;
        }

        if (
            wrapper.getAttribute(
                'data-authentic-initialized'
            ) === 'true'
        ) {
            return;
        }

        wrapper.setAttribute(
            'data-authentic-initialized',
            'true'
        );

        /*
         * Move overlay and drawer to body.
         * This avoids parent overflow/transform problems.
         */
        document.body.appendChild(overlay);
        document.body.appendChild(drawer);

        /*
         * Initial state
         */
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');

        drawer.setAttribute(
            'aria-hidden',
            'true'
        );

        overlay.setAttribute(
            'aria-hidden',
            'true'
        );

        /*
         * Open drawer
         */
        function openDrawer(event) {
            event.preventDefault();

            drawer.classList.add('is-open');
            overlay.classList.add('is-open');

            drawer.setAttribute(
                'aria-hidden',
                'false'
            );

            overlay.setAttribute(
                'aria-hidden',
                'false'
            );

            document.body.classList.add(
                'authentic-drawer-open'
            );
        }

        /*
         * Close drawer
         */
        function closeDrawer(event) {
            if (event) {
                event.preventDefault();
            }

            drawer.classList.remove('is-open');
            overlay.classList.remove('is-open');

            drawer.setAttribute(
                'aria-hidden',
                'true'
            );

            overlay.setAttribute(
                'aria-hidden',
                'true'
            );

            document.body.classList.remove(
                'authentic-drawer-open'
            );
        }

        /*
         * Events
         */
        infoButton.addEventListener(
            'click',
            openDrawer
        );

        closeButton.addEventListener(
            'click',
            closeDrawer
        );

        overlay.addEventListener(
            'click',
            closeDrawer
        );

        document.addEventListener(
            'keydown',
            function (event) {
                if (
                    event.key === 'Escape' &&
                    drawer.classList.contains('is-open')
                ) {
                    closeDrawer();
                }
            }
        );
    };
});
