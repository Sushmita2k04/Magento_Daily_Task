/**
 * Countdown + client-side status flip (the page itself may be served from full page cache).
 * Config: {start: epochSeconds, end: epochSeconds|0, labels: {upcoming, live, completed}}
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $el = $(element),
            $badge = $el.find('[data-role="badge"]').first(),
            $countdown = $el.find('[data-role="countdown"]').first(),
            $note = $el.find('[data-role="note"]').first(),
            timer = null;

        /**
         * @param {Number} n
         * @return {String}
         */
        function pad(n) {
            return n < 10 ? '0' + n : String(n);
        }

        /**
         * @param {String} state
         */
        function setState(state) {
            $el.removeClass('pl-card-upcoming pl-card-live pl-card-completed pl-detail-upcoming ' +
                'pl-detail-live pl-detail-completed')
                .addClass($el.is('.pl-card') ? 'pl-card-' + state : 'pl-detail-' + state);
            $badge.removeClass('pl-badge-upcoming pl-badge-live pl-badge-completed')
                .addClass('pl-badge-' + state)
                .text(config.labels[state] || '');

            if (state !== 'upcoming') {
                $countdown.hide();
            }

            if ($note.length) {
                $note.text(state === 'live' ? config.labels.live : state === 'completed' ? config.labels.completed : '');
            }
        }

        /** @return {String} */
        function currentState() {
            var now = Math.floor(Date.now() / 1000);

            if (now < config.start) {
                return 'upcoming';
            }

            return config.end && now >= config.end ? 'completed' : 'live';
        }

        /** Tick once per second. */
        function tick() {
            var now = Math.floor(Date.now() / 1000),
                state = currentState(),
                left;

            if (state !== 'upcoming') {
                setState(state);

                if (state === 'completed' || !config.end) {
                    clearInterval(timer);
                }

                return;
            }

            left = config.start - now;
            $countdown.find('[data-unit="d"]').text(Math.floor(left / 86400));
            $countdown.find('[data-unit="h"]').text(pad(Math.floor(left % 86400 / 3600)));
            $countdown.find('[data-unit="m"]').text(pad(Math.floor(left % 3600 / 60)));
            $countdown.find('[data-unit="s"]').text(pad(left % 60));
        }

        if (!config.start) {
            return;
        }

        setState(currentState());
        tick();
        timer = setInterval(tick, 1000);
    };
});
