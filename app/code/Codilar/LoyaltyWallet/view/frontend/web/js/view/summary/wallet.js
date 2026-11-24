define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote'
], function (Component, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Codilar_LoyaltyWallet/summary/wallet'
        },

        totals: quote.getTotals(),

        /**
         * @return {Object|null} The store wallet segment of the current totals, if any.
         */
        getSegment: function () {
            var segments = this.totals() && this.totals()['total_segments'] || [],
                i;

            for (i = 0; i < segments.length; i++) {
                if (segments[i].code === 'codilar_wallet') {
                    return segments[i];
                }
            }

            return null;
        },

        isDisplayed: function () {
            var segment = this.getSegment();

            return !!segment && Math.abs(parseFloat(segment.value)) > 0;
        },

        getTitle: function () {
            var segment = this.getSegment();

            return segment ? segment.title : '';
        },

        getValue: function () {
            var segment = this.getSegment();

            return segment ? this.getFormattedPrice(segment.value) : '';
        }
    });
});
