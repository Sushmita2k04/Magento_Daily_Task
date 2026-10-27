
define([
    'jquery'
], function ($) {
    'use strict';

    $(document).ready(function () {
        var $filter = $('.codilar-price-filter').first();

        if (!$filter.length) {
            return;
        }

        var $minSlider = $filter.find('.codilar-price-slider-min');
        var $maxSlider = $filter.find('.codilar-price-slider-max');
        var $minValue = $filter.find('.codilar-price-min');
        var $maxValue = $filter.find('.codilar-price-max');
        var $range = $filter.find('.codilar-price-slider-range');

        var minPrice = Number($filter.attr('data-min-price'));
        var maxPrice = Number($filter.attr('data-max-price'));

        if (
            !Number.isFinite(minPrice) ||
            !Number.isFinite(maxPrice) ||
            maxPrice <= minPrice ||
            !$minSlider.length ||
            !$maxSlider.length
        ) {
            return;
        }

        function formatPrice(value) {
            return '₹ ' + Math.round(value).toLocaleString('en-IN');
        }



        function updateSlider(changedSlider) {
            var min = Number($minSlider.val());
            var max = Number($maxSlider.val());

            // if (min >= max) {
            //     if (changedSlider === $minSlider[0]) {
            //         min = max;
            //     } else {
            //         max = min;
            //     }
            // }

            $minSlider.val(min);
            $maxSlider.val(max);

            $minValue.text(formatPrice(min));
            $maxValue.text(formatPrice(max));

            var left = ((min - minPrice) / (maxPrice - minPrice)) * 100;
            var right = ((max - minPrice) / (maxPrice - minPrice)) * 100;

            $range.css({
                left: left + '%',
                width: (right - left) + '%'
            });
        }

        var timer;

        function applyPriceFilter() {
            var min = Number($minSlider.val());
            var max = Number($maxSlider.val());

            if (min <= minPrice && max >= maxPrice) {
                return;
            }

        }

        var timer;

        function applyPriceFilter() {
            var min = Number($minSlider.val());
            var max = Number($maxSlider.val());

            if (min <= minPrice && max >= maxPrice) {
                return;
            }

            clearTimeout(timer);

            timer = setTimeout(function () {
                var url = new URL(window.location.href);

                url.searchParams.set(
                    'price',
                    Math.floor(min) + '-' + Math.ceil(max)
                );

                url.searchParams.delete('p');

                window.location.href = url.toString();
            }, 400);
        }

        $minSlider.on('input', function () {
            updateSlider(this);
        });

        $maxSlider.on('input', function () {
            updateSlider(this);
        });

        $minSlider.on('change', applyPriceFilter);
        $maxSlider.on('change', applyPriceFilter);

        updateSlider();
    });
});
