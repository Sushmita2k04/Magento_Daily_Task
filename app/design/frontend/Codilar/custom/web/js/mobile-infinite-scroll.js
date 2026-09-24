define([
    'jquery',
    'mage/apply/main'
], function ($, mage) {
    'use strict';

    var breakpoint = 425;

    function init() {

        console.log('Mobile infinite scroll JS loaded');

        if (window.innerWidth > breakpoint) {
            console.log('Infinite scroll disabled: width > 425px');
            return;
        }

        var $trigger = $('[data-role="mobile-infinite-scroll"]');
        var $productList = $('[data-role="product-list"]');

        console.log('Trigger:', $trigger.length);
        console.log('Product list:', $productList.length);

        if (!$trigger.length || !$productList.length) {
            console.log('Infinite scroll elements not found');
            return;
        }

        var currentPage = parseInt(
            $trigger.attr('data-current-page'),
            10
        );

        var lastPage = parseInt(
            $trigger.attr('data-last-page'),
            10
        );

        console.log('Current page:', currentPage);
        console.log('Last page:', lastPage);

        if (
            isNaN(currentPage) ||
            isNaN(lastPage) ||
            currentPage >= lastPage
        ) {
            console.log('No more pages');
            return;
        }

        var isLoading = false;

        function loadNextPage() {

            if (isLoading || currentPage >= lastPage) {
                return;
            }

            isLoading = true;

            var nextPage = currentPage + 1;

            console.log('Loading page:', nextPage);

            $trigger.addClass('is-loading');

            var url = new URL(window.location.href);

            url.searchParams.set('p', nextPage);

            $.ajax({
                url: url.toString(),
                type: 'GET',
                dataType: 'html'
            })
            .done(function (response) {

                console.log('Page loaded:', nextPage);

                var $response = $('<div>').append(
                    $.parseHTML(response)
                );

                var $newProducts = $response.find(
                    '[data-role="product-list"] > .product-item'
                );

                console.log(
                    'Products received:',
                    $newProducts.length
                );

                if (!$newProducts.length) {

                    console.log('No products found in response');

                    currentPage = lastPage;

                    return;
                }

                /*
                 * Append new products
                 */
                $productList.append($newProducts);

                /*
                 * Initialize Magento JS components
                 * inside newly loaded products.
                 *
                 * This initializes:
                 * - Configurable swatches
                 * - Swatch renderer
                 * - Add to cart
                 * - Other data-mage-init components
                 */
                mage.apply($newProducts[0]);

                console.log(
                    'Magento JS initialized for new products'
                );

                currentPage = nextPage;

                $trigger.attr(
                    'data-current-page',
                    currentPage
                );

                console.log(
                    'Products appended. Current page:',
                    currentPage
                );

                if (currentPage >= lastPage) {

                    console.log('Reached last page');

                    observer.disconnect();

                    $trigger.remove();
                }
            })
            .fail(function (xhr) {

                console.error(
                    'Infinite scroll AJAX error:',
                    xhr.status,
                    xhr.statusText
                );
            })
            .always(function () {

                isLoading = false;

                $trigger.removeClass('is-loading');
            });
        }

        var observer = new IntersectionObserver(
            function (entries) {

                if (
                    entries[0].isIntersecting &&
                    !isLoading
                ) {

                    console.log(
                        'Infinite scroll trigger reached'
                    );

                    loadNextPage();
                }
            },
            {
                root: null,
                rootMargin: '500px 0px',
                threshold: 0
            }
        );

        observer.observe($trigger[0]);

        console.log(
            'IntersectionObserver initialized'
        );
    }

    $(document).ready(function () {
        init();
    });
});
