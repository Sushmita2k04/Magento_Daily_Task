<?php

declare(strict_types=1);

namespace Codilar\FreeShippingProgress\Block;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;

class Progress extends Template
{
    private const REWARDS = [
        [
            'amount' => 500,
            'title' => 'Free Delivery',
            'subtitle' => 'Auto applied',
            'type' => 'shipping'
        ],
        [
            'amount' => 750,
            'title' => '$50 OFF',
            'subtitle' => 'View ›',
            'type' => 'coupon'
        ],
        [
            'amount' => 1000,
            'title' => '$100 OFF',
            'subtitle' => 'Coupon',
            'type' => 'coupon'
        ],
        [
            'amount' => 1500,
            'title' => '$150 OFF',
            'subtitle' => 'Coupon',
            'type' => 'coupon'
        ]
    ];

    public function __construct(
        Template\Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getRewards(): array
    {
        return self::REWARDS;
    }

    public function getMaxRewardAmount(): float
    {
        if (self::REWARDS === []) {
            return 0.0;
        }

        $lastReward = self::REWARDS[array_key_last(self::REWARDS)];

        return (float) $lastReward['amount'];
    }

    public function getInitialSubtotal(): float
    {
        return (float) $this->checkoutSession
            ->getQuote()
            ->getSubtotal();
    }

    public function getRewardsJson(): string
    {
        return $this->json->serialize(
            $this->getRewards()
        );
    }
}