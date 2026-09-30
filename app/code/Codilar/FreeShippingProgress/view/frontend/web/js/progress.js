define([
    'uiComponent',
    'ko',
    'Magento_Customer/js/customer-data'
], function (
    Component,
    ko,
    customerData
) {
    'use strict';

    return Component.extend({

        defaults: {
            template: 'Codilar_FreeShippingProgress/progress',
            rewards: []
        },

        progressSections: 5,

        initialize: function () {
            this._super();

            this.cart = customerData.get('cart');

            this.rewards = ko.observableArray(
                Array.isArray(this.rewards)
                    ? this.rewards
                    : []
            );

            this.subtotal = ko.pureComputed(function () {
                var cartData = this.cart();
                var subtotal = cartData && cartData.subtotal;

                if (!subtotal) {
                    return 0;
                }

                return Number(
                    String(subtotal).replace(/[^0-9.-]/g, '')
                ) || 0;
            }, this);

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

                if (!rewards.length) {
                    return '';
                }

                nextReward = this.getNextReward();

                if (!nextReward) {
                    return 'All rewards unlocked!';
                }

                return 'Shop $' +
                    this.amountRemaining() +
                    ' more, Unlock ' +
                    nextReward.title;
            }, this);

            this.cartSubscription = this.cart.subscribe(function () {
                this.subtotal();
            }, this);

            return this;
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

        dispose: function () {
            if (this.cartSubscription) {
                this.cartSubscription.dispose();
            }

            if (this.progressPercent) {
                this.progressPercent.dispose();
            }

            if (this.message) {
                this.message.dispose();
            }

            if (this.subtotal) {
                this.subtotal.dispose();
            }

            this._super();
        }
    });
});