define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';

    return function (config, element) {
        var $header = $(element),
            $searchButton = $header.find('.search'),
            $searchBox = $header.find('[data-header-search]'),
            $searchInput = $header.find('#header-search-input'),
            cart = customerData.get('cart');

        function updateCartCount(data) {
            var count = parseInt(data.summary_count, 10) || 0,
                $cartCount = $header.find('[data-cart-count]');

            $cartCount.text(count);

            if (count > 0) {
                $cartCount.show();
            } else {
                $cartCount.hide();
            }
        }

        updateCartCount(cart());

        cart.subscribe(function (data) {
            updateCartCount(data);
        });

        $searchButton.on('click', function () {
            var isOpen = $header.hasClass('search-open');

            $header.toggleClass('search-open', !isOpen);

            $(this).attr(
                'aria-expanded',
                !isOpen ? 'true' : 'false'
            );

            if (!isOpen) {
                window.setTimeout(function () {
                    $searchInput.trigger('focus');
                }, 0);
            }
        });
    };
});