define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote'
], function (
    Component,
    ko,
    quote
) {
    'use strict';

    return Component.extend({

        defaults: {
            template: 'Codilar_FreeShippingProgress/success-message',
            rewards: [],
            initialSubtotal: 0
        },

        initialize: function () {
            this._super();

            this.subtotal = ko.observable(
                Number(this.initialSubtotal) || 0
            );

            this.rewards = ko.observableArray(
                Array.isArray(this.rewards)
                    ? this.rewards
                    : []
            );

            this.successMessage = ko.pureComputed(function () {
                var subtotal = this.subtotal();
                var unlockedReward = null;

                this.rewards().forEach(function (reward) {
                    if (subtotal >= Number(reward.amount)) {
                        unlockedReward = reward;
                    }
                });

                if (!unlockedReward) {
                    return '';
                }

                return unlockedReward.title + ' unlocked!';
            }, this);

            this.isVisible = ko.pureComputed(function () {
                return this.successMessage() !== '';
            }, this);

            this.updateSubtotal();

            this.totalsSubscription = quote.totals.subscribe(
                this.updateSubtotal.bind(this)
            );

            return this;
        },

        updateSubtotal: function () {
            var totals = quote.totals();
            var subtotal;

            if (!totals) {
                return;
            }

            subtotal = Number(totals.subtotal);

            if (Number.isFinite(subtotal)) {
                this.subtotal(subtotal);
            }
        },

        dispose: function () {
            if (this.totalsSubscription) {
                this.totalsSubscription.dispose();
            }

            if (this.successMessage) {
                this.successMessage.dispose();
            }

            if (this.isVisible) {
                this.isVisible.dispose();
            }

            this._super();
        }
    });
});
