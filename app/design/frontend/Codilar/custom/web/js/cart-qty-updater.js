define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'jquery-ui-modules/widget'
], function ($, customerData) {
    'use strict';

    $.widget('mage.cartQtyUpdater', {
        _create: function () {
            this._bindEvents();
        },

        _bindEvents: function () {
            this.element.on(
                'click',
                '[data-role="qty-increase"], [data-role="qty-decrease"]',
                this._onQtyClick.bind(this)
            );

            this.element.on(
                'change',
                '[data-role="cart-item-qty"]',
                this._submitForm.bind(this)
            );
        },

        _onQtyClick: function (event) {
            event.preventDefault();

            var $button = $(event.currentTarget),
                $wrapper = $button.closest('.cart-product-qty'),
                $input = $wrapper.find('[data-role="cart-item-qty"]'),
                currentQty = parseInt($input.val(), 10) || 1,
                delta = $button.is('[data-role="qty-increase"]') ? 1 : -1,
                newQty = currentQty + delta;

            if (newQty < 1) {
                return;
            }

            $input.val(newQty);
            this._submitForm();
        },

        _submitForm: function () {
            var self = this;

            $.ajax({
                url: this.element.attr('action'),
                type: 'POST',
                data: this.element.serialize(),
                showLoader: true
            })
            .done(function () {
                customerData.invalidate(['cart']);

                customerData.reload(['cart'], true).done(function () {
                    self._refreshTotals();
                });
            })
            .fail(function () {
                console.error('Unable to update cart quantity.');
            });
        },

        _refreshTotals: function () {
            require([
                'Magento_Checkout/js/model/quote',
                'Magento_Checkout/js/action/get-totals'
            ], function (quote, getTotals) {
                getTotals([], true);
            });
        }
    });

    return $.mage.cartQtyUpdater;
});