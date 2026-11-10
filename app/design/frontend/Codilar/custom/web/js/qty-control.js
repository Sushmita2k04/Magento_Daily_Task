
define([], function () {
    'use strict';

    return function () {
        document.querySelectorAll('.box-tocart').forEach(function (container) {
            if (container.dataset.qtyControlInitialized) {
                return;
            }

            container.dataset.qtyControlInitialized = 'true';

            var input = container.querySelector('#qty');

            if (!input) {
                return;
            }

            container.querySelector('.qty-minus')?.addEventListener('click', function () {
                var min = Number(input.min || 1);
                var step = Number(input.step || 1);
                var value = Number(input.value || min);

                input.value = Math.max(min, value - step);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            container.querySelector('.qty-plus')?.addEventListener('click', function () {
                var step = Number(input.step || 1);
                var value = Number(input.value || input.min || 1);
                var max = input.max ? Number(input.max) : Infinity;

                input.value = Math.min(max, value + step);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    };
});
