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

        initialize: function () {
            this._super();

            this.cart = customerData.get('cart');

            console.log('Cart data:', this.cart());

            this.subtotal = ko.observable(
                this.getSubtotal()
            );

            this.progressPercent = ko.pureComputed(
                function () {
                    var subtotal = this.subtotal();
                    var rewards = this.rewards;

                    if (!rewards.length) {
                        return 0;
                    }

                    var sectionWidth = 100 / rewards.length;
                    var currentIndex = -1;
                    var firstReward =Number(rewards[0].amount);
                    var currentReward;
                    var nextReward;
                    var range;
                    var travelled;
                    var sectionProgress;

                    rewards.forEach(
                        function (reward, index) {
                            if (
                                subtotal >=
                                Number(reward.amount)
                            ) {
                                currentIndex = index;
                            }
                        }
                    );

                    if (subtotal < firstReward) {
                        sectionProgress =
                            subtotal / firstReward;

                        return Math.max(
                            0,
                            Math.min(
                                sectionProgress *
                                sectionWidth,
                                sectionWidth
                            )
                        );
                    }

                    if (
                        currentIndex ===
                        rewards.length - 1
                    ) {
                        return 100;
                    }

                    currentReward =Number(rewards[currentIndex].amount);

                    nextReward = Number(rewards[currentIndex + 1].amount);

                    range = nextReward - currentReward;

                    travelled =  subtotal - currentReward;

                    sectionProgress =  range > 0
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
                            100
                        )
                    );
                },
                this
            );

            /**
             * Message above progress bar
             */
            this.message = ko.pureComputed(
                function () {
                    var nextReward =
                        this.getNextReward();

                    if (!nextReward) {
                        return 'All rewards unlocked!';
                    }

                    return 'Shop $' +
                        this.amountRemaining() +
                        ' more, Unlock ' +
                        nextReward.title;
                },
                this
            );

            this.cartSubscription =
                this.cart.subscribe(
                    this.updateSubtotal.bind(this)
                );

            return this;
        },

        getSubtotal: function () {
            var cart = this.cart();

            if (!cart) {
                return 0;
            }

            console.log(
                'Cart subtotal:',
                cart.subtotalAmount
            );

            return Number(
                cart.subtotalAmount
            ) || 0;
        },

        updateSubtotal: function () {
            this.subtotal(
                this.getSubtotal()
            );
        },

        getNextReward: function () {
            var subtotal = this.subtotal();

            return this.rewards.find(
                function (reward) {
                    return subtotal <
                        Number(reward.amount);
                }
            ) || null;
        },

        getCurrentReward: function () {
            var subtotal = this.subtotal();
            var currentReward = null;

            this.rewards.forEach(
                function (reward) {
                    if (
                        subtotal >=
                        Number(reward.amount)
                    ) {
                        currentReward = reward;
                    }
                }
            );

            return currentReward;
        },

        amountRemaining: function () {
            var nextReward = this.getNextReward();

            if (!nextReward) {
                return 0;
            }

            return Math.ceil(
                Number(nextReward.amount) -
                this.subtotal()
            );
        },

        isUnlocked: function (reward) {
            return this.subtotal() >=
                Number(reward.amount);
        },

        rewardPosition: function (reward, index) {
            return (
                (index + 1) *
                (100 / this.rewards.length)
            );
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

            this._super();
        }
    });
});
