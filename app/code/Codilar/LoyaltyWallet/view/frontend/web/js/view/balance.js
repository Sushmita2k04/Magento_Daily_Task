define([
    'uiComponent',
    'Magento_Customer/js/customer-data'
], function (Component, customerData) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Codilar_LoyaltyWallet/balance'
        },

        initialize: function () {
            this._super();
            this.loyalty = customerData.get('codilar_loyalty');

            // This theme has no minicart, so nothing else triggers the first load of private sections.
            if (!this.loyalty() || typeof this.loyalty().is_enabled === 'undefined') {
                customerData.reload(['codilar_loyalty'], false);
            }

            return this;
        },

        isVisible: function () {
            var data = this.loyalty();

            return !!(data && data.is_enabled);
        }
    });
});
