define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils'
], function (
    Component,
    ko,
    quote,
    priceUtils
) {
    'use strict';

    return Component.extend({

        defaults: {
            template: 'Codilar_FreeShippingProgress/progress',
            rewards: [],
            products: [],
            initialSubtotal: 0
        },

        progressSections: 5,

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

            this.products = ko.observableArray(
                Array.isArray(this.products)
                    ? this.products
                    : []
            );

            this.progressPercent = ko.pureComputed(function () {
                var subtotal = this.subtotal();
                var rewards = this.rewards();
                var rewardCount = rewards.length;
                var sectionWidth;
                var currentIndex = -1;
                var firstReward;
                var currentReward;
                var nextReward;
                var range;
                var travelled;
                var sectionProgress;

                if (!rewardCount) {
                    return 0;
                }

                sectionWidth = 100 / this.progressSections;
                firstReward = Number(rewards[0].amount);

                rewards.forEach(function (reward, index) {
                    if (subtotal >= Number(reward.amount)) {
                        currentIndex = index;
                    }
                });

                if (subtotal < firstReward) {
                    sectionProgress = firstReward > 0
                        ? subtotal / firstReward
                        : 0;

                    return Math.max(
                        0,
                        Math.min(
                            sectionProgress * sectionWidth,
                            sectionWidth
                        )
                    );
                }

                if (currentIndex === rewardCount - 1) {
                    return rewardCount * sectionWidth;
                }

                currentReward = Number(
                    rewards[currentIndex].amount
                );

                nextReward = Number(
                    rewards[currentIndex + 1].amount
                );

                range = nextReward - currentReward;
                travelled = subtotal - currentReward;

                sectionProgress = range > 0
                    ? travelled / range
                    : 0;

                return Math.max(
                    0,
                    Math.min(
                        (
                            (currentIndex + 1) *
                            sectionWidth
                        ) +
                        (
                            sectionProgress *
                            sectionWidth
                        ),
                        rewardCount * sectionWidth
                    )
                );
            }, this);

            this.message = ko.pureComputed(function () {
                var rewards = this.rewards();
                var nextReward;
                var currentReward;

                if (!rewards.length) {
                    return '';
                }

                nextReward = this.getNextReward();
                currentReward = this.getCurrentReward();

                if (!nextReward) {
                    return 'All rewards unlocked!';
                }

                if (!currentReward) {
                    return 'Shop $' +
                        this.amountRemaining() +
                        ' more, Unlock ' +
                        nextReward.title;
                }

                return ' Shop $' +
                    this.amountRemaining() +
                    ' more, Unlock ' +
                    nextReward.title;
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

        getNextReward: function () {
            var subtotal = this.subtotal();

            return this.rewards().find(function (reward) {
                return subtotal < Number(reward.amount);
            }) || null;
        },

        getCurrentReward: function () {
            var subtotal = this.subtotal();
            var currentReward = null;

            this.rewards().forEach(function (reward) {
                if (subtotal >= Number(reward.amount)) {
                    currentReward = reward;
                }
            });

            return currentReward;
        },

        amountRemaining: function () {
            var nextReward = this.getNextReward();

            if (!nextReward) {
                return 0;
            }

            return Math.max(
                0,
                Math.ceil(
                    Number(nextReward.amount) -
                    this.subtotal()
                )
            );
        },

        isUnlocked: function (reward) {
            return this.subtotal() >= Number(reward.amount);
        },

        rewardPosition: function (reward, index) {
            var sectionWidth =
                100 / this.progressSections;

            return (index + 1) * sectionWidth;
        },

        isLastReward: function (index) {
            return index === this.rewards().length - 1;
        },

        formatPrice: function (amount) {
            return '$' + Number(amount || 0).toFixed(2);
        },

        dispose: function () {
            if (this.totalsSubscription) {
                this.totalsSubscription.dispose();
            }

            if (this.progressPercent) {
                this.progressPercent.dispose();
            }

            if (this.message) {
                this.message.dispose();
            }

            this._super();
        }
    });
});

