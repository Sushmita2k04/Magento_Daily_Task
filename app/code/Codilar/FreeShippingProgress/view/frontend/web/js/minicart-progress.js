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
            template: 'Codilar_FreeShippingProgress/minicart-progress',

            rewards: [
                {
                    amount: 500,
                    title: 'Free Delivery',
                    subtitle: 'Auto applied'
                },
                {
                    amount: 750,
                    title: '$50 OFF',
                    subtitle: 'View ›'
                },
                {
                    amount: 1000,
                    title: '$100 OFF',
                    subtitle: 'Coupon'
                },
                {
                    amount: 1500,
                    title: '$150 OFF',
                    subtitle: 'Coupon'
                }
            ]
        },

        progressSections: 5,

        initialize: function () {
            this._super();

            this.subtotal = ko.observable(
                this.getSubtotal()
            );

            this.rewards = ko.observableArray(
                this.rewards
            );

            this.progressPercent = ko.pureComputed(
                this.calculateProgress,
                this
            );

            this.message = ko.pureComputed(
                this.getMessage,
                this
            );

            this.totalsSubscription = quote.totals.subscribe(
                this.updateSubtotal.bind(this)
            );

            return this;
        },

        getSubtotal: function () {
            var totals = quote.totals();

            if (!totals) {
                return 0;
            }

            return Number(totals.subtotal) || 0;
        },

        updateSubtotal: function (totals) {
            if (!totals) {
                return;
            }

            var subtotal = Number(totals.subtotal);

            if (Number.isFinite(subtotal)) {
                this.subtotal(subtotal);
            }
        },

        calculateProgress: function () {
            var subtotal = this.subtotal();
            var rewards = this.rewards();
            var rewardCount = rewards.length;
            var sectionWidth = 100 / this.progressSections;
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

        getNextReward: function () {
            var subtotal = this.subtotal();

            return this.rewards().find(function (reward) {
                return subtotal < Number(reward.amount);
            }) || null;
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

        getMessage: function () {
            var currentReward = this.getCurrentReward();
            var nextReward = this.getNextReward();

            if (!nextReward) {
                return 'All rewards unlocked!';
            }

            if (!currentReward) {
                return 'Shop $' +
                    this.amountRemaining() +
                    ' more, Unlock ' +
                    nextReward.title;
            }

            return currentReward.title +
                ' unlocked! Shop $' +
                this.amountRemaining() +
                ' more, Unlock ' +
                nextReward.title;
        },

        isUnlocked: function (reward) {
            return this.subtotal() >= Number(
                reward.amount
            );
        },

        rewardPosition: function (reward, index) {
            var sectionWidth =
                100 / this.progressSections;

            return (index + 1) * sectionWidth;
        },

        formatPrice: function (amount) {
            return '$' +
                Number(amount || 0).toFixed(2);
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
