/**
 * Product picker: lazy-searching ui-select that also fills the read-only SKU field
 * and shows the already-saved product on edit.
 */
define([
    'underscore',
    'Magento_Ui/js/form/element/ui-select'
], function (_, Select) {
    'use strict';

    return Select.extend({
        defaults: {
            initialLabel: '',
            initialSku: '',
            imports: {
                initialLabel: '${ $.provider }:data.product_label',
                initialSku: '${ $.provider }:data.product_sku'
            }
        },

        /** @inheritdoc */
        initObservable: function () {
            this._super();
            this.observe(['initialLabel', 'initialSku']);

            return this;
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();
            this.seed();
            this.initialLabel.subscribe(this.seed.bind(this));

            return this;
        },

        /** Put the saved product into the option list so its label is shown. */
        seed: function () {
            var id = this.value(),
                label = this.initialLabel(),
                exists;

            id = _.isArray(id) ? id[0] : id;

            if (!id || !label) {
                return;
            }

            exists = _.some(this.cacheOptions.plain, function (opt) {
                return String(opt.value) === String(id);
            });

            if (!exists) {
                this.cacheOptions.plain = [{
                    value: id, label: label, is_active: true, path: label, optgroup: false
                }].concat(this.cacheOptions.plain);
                this.options(this.cacheOptions.plain);
            }
        },

        /** @inheritdoc */
        onUpdate: function () {
            var id = this.value(),
                match;

            this._super();
            id = _.isArray(id) ? id[0] : id;
            match = _.find(this.cacheOptions.plain, function (opt) {
                return String(opt.value) === String(id);
            });

            if (match && match.sku !== undefined) {
                this.source.set('data.sku', match.sku);
            }
        }
    });
});
