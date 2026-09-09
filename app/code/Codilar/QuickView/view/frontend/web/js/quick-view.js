define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($, modal) {
    'use strict';

    return function () {

        var $quickViewModal = $('#quick-view-modal');

        if (!$quickViewModal.length) {
            console.error('Quick View: Modal element not found.');
            return;
        }

        /**
         * Initialize Magento Modal
         */
        modal({
            type: 'popup',
            responsive: true,
            innerScroll: false,
            modalClass: 'codilar-quickview-modal',
            title: 'Quick View',
            buttons: []
        }, $quickViewModal);

        /**
         * Quick View Button Click
         */
        $(document).on(
            'click',
            '.codilar-quick-view-button',
            function (e) {

                e.preventDefault();

                var $button = $(this);
                var productId = $button.data('product-id');
                var url = $button.data('url');

                if (!productId || !url) {
                    console.error(
                        'Quick View: Product ID or URL is missing.'
                    );
                    return;
                }

                /**
                 * Hide previous error
                 */
                $quickViewModal
                    .find('.quick-view-error')
                    .hide()
                    .text('');

                /**
                 * AJAX Request
                 */
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: {
                        product_id: productId
                    },
                    dataType: 'json',

                    beforeSend: function () {
                        console.log(
                            'Quick View: Loading product...'
                        );
                    },

                    success: function (response) {

                        console.log(
                            'Quick View Response:',
                            response
                        );

                        if (!response.success || !response.product) {

                            $quickViewModal
                                .find('.quick-view-error')
                                .text(
                                    response.message ||
                                    'Unable to load product details.'
                                )
                                .show();

                            return;
                        }

                        /**
                         * Product Image
                         */
                        $('#quick-view-image').attr(
                            'src',
                            response.product.image
                        );

                        /**
                         * Product Name
                         */
                        $('#quick-view-name').text(
                            response.product.name
                        );

                        /**
                         * Product SKU
                         */
                        $('#quick-view-sku').text(
                            response.product.sku
                        );

                        /**
                         * Product Price
                         */
                        $('#quick-view-price').html(
                            response.product.price
                        );

                        /**
                         * Stock Status
                         */
                        $('#quick-view-stock').text(
                            response.product.stock
                        );

                        /**
                         * Description
                         */
                        $('#quick-view-description').html(
                            response.product.description
                        );

                        /**
                         * Open Magento Modal
                         */
                        $quickViewModal.modal('openModal');
                    },

                    error: function (xhr, status, error) {

                        console.error(
                            'Quick View AJAX Error:',
                            status,
                            error,
                            xhr.responseText
                        );

                        $quickViewModal
                            .find('.quick-view-error')
                            .text(
                                'Unable to load product details.'
                            )
                            .show();
                    }
                });
            }
        );
    };
});
