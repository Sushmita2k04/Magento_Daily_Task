define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $section = $(element);

        var $viewport = $section.find('.campaign-section__products');
        var $track = $section.find('.campaign-section__products-list');
        var $prevBtn = $section.find('.campaign-section__arrow--prev');
        var $nextBtn = $section.find('.campaign-section__arrow--next');

        if ($viewport.length && $track.length && $prevBtn.length && $nextBtn.length) {
            var currentIndex = 0;

            function getScrollStep() {
                var $firstCard = $track.find('.campaign-section__product').first();

                if (!$firstCard.length) {
                    return $viewport.innerWidth();
                }

                var cardWidth = $firstCard.outerWidth();
                var gap = parseFloat($track.css('gap')) || 16;

                return cardWidth + gap;
            }

            function getMaxIndex() {
                var step = getScrollStep();
                var viewportWidth = $viewport.innerWidth();
                var trackWidth = $track[0].scrollWidth;

                if (!step) {
                    return 0;
                }

                return Math.max(
                    0,
                    Math.ceil((trackWidth - viewportWidth) / step)
                );
            }

            function updateSlider() {
                var maxIndex = getMaxIndex();
                var step = getScrollStep();

                currentIndex = Math.max(
                    0,
                    Math.min(currentIndex, maxIndex)
                );

                $track.css(
                    'transform',
                    'translateX(-' + (currentIndex * step) + 'px)'
                );

                $prevBtn.prop(
                    'disabled',
                    currentIndex <= 0
                );

                $nextBtn.prop(
                    'disabled',
                    currentIndex >= maxIndex
                );
            }

            $nextBtn.on('click', function (e) {
                e.preventDefault();

                if (currentIndex < getMaxIndex()) {
                    currentIndex++;
                    updateSlider();
                }
            });

            $prevBtn.on('click', function (e) {
                e.preventDefault();

                if (currentIndex > 0) {
                    currentIndex--;
                    updateSlider();
                }
            });

            $(window).on('resize', function () {
                updateSlider();
            });

            updateSlider();
        } else {
            console.log('SLIDER ELEMENT MISSING');
        }

        var $campaign = $section;
        var countdownEnd = $campaign.data('countdown-end');
        var countdownInterval = null;

        function updateCountdown() {
            if (!countdownEnd) {
                console.log('Countdown end missing');
                return;
            }

            var targetTime = new Date(countdownEnd).getTime();

            if (isNaN(targetTime)) {
                return;
            }

            var remaining = Math.max(
                0,
                targetTime - Date.now()
            );

            var totalSeconds = Math.floor(remaining / 1000);

            var days = Math.floor(totalSeconds / 86400);
            var hours = Math.floor((totalSeconds % 86400) / 3600);
            var minutes = Math.floor((totalSeconds % 3600) / 60);
            var seconds = totalSeconds % 60;

            $campaign.find('[data-countdown-days]').text(
                String(days).padStart(2, '0')
            );

            $campaign.find('[data-countdown-hours]').text(
                String(hours).padStart(2, '0')
            );

            $campaign.find('[data-countdown-minutes]').text(
                String(minutes).padStart(2, '0')
            );

            $campaign.find('[data-countdown-seconds]').text(
                String(seconds).padStart(2, '0')
            );

            if (remaining <= 0 && countdownInterval) {
                console.log('COUNTDOWN FINISHED');

                clearInterval(countdownInterval);
                countdownInterval = null;
            }
        }

        if (countdownEnd) {
            updateCountdown();

            countdownInterval = setInterval(
                updateCountdown,
                1000
            );
        }
    };
});