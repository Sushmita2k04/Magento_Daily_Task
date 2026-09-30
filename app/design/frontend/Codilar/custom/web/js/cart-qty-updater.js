define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/get-totals',
    'jquery-ui-modules/widget'
], function (
    $,
    customerData,
    getTotals
) {
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
                data: this.element.serialize()
            }).done(function () {
                console.log('Cart quantity updated');

                self._refreshCartItems();
                self._refreshTotals();
                self._refreshCustomerData();
            }).fail(function (xhr) {
                console.error(
                    'Unable to update cart quantity.',
                    xhr
                );
            });
        },

        _refreshCartItems: function () {
            var $currentItems = $('.cart-items-list');

            $.ajax({
                url: window.location.href,
                type: 'GET',
                cache: false
            }).done(function (response) {
                var $response = $('<div>').append(
                    $.parseHTML(response)
                );

                var $newItems = $response.find('.cart-items-list');

                if ($newItems.length && $currentItems.length) {
                    $currentItems.replaceWith($newItems);
                }
            }).fail(function (xhr) {
                console.error(
                    'Unable to refresh cart items.',
                    xhr
                );
            });
        },

        _refreshTotals: function () {
            var deferred = $.Deferred();

            getTotals([], deferred);

            deferred.done(function (totals) {
                console.log('Updated cart totals:', totals);
            });

            deferred.fail(function (error) {
                console.error(
                    'Unable to update cart totals.',
                    error
                );
            });
        },

        _refreshCustomerData: function () {
            customerData.invalidate(['cart']);

            customerData.reload(['cart'], true).done(function (cart) {
                console.log(
                    'Updated customer data:',
                    cart
                );
            });
        }
    });

    return $.mage.cartQtyUpdater;
});