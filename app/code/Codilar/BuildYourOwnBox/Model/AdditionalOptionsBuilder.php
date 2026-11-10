<?php
declare(strict_types=1);

namespace Codilar\BuildYourOwnBox\Model;

use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * Turns a composition snapshot into label/value rows ("additional options") that Magento's stock
 * cart, checkout, order, email, invoice and credit memo renderers already display (and escape).
 */
class AdditionalOptionsBuilder
{
    public function __construct(private readonly PriceCurrencyInterface $priceCurrency)
    {
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function build(BoxComposition $composition): array
    {
        $data = $composition->toArray();
        $currency = (string)($data['currency'] ?? '');
        $money = fn(float $amount): string => $this->priceCurrency->format($amount, false, 2, null, $currency);

        $rows = [];
        foreach ($data['coffee'] as $i => $coffee) {
            $rows[] = ['label' => (string)__('Selected Coffee %1', $i + 1), 'value' => (string)$coffee['name']];
        }
        if (!empty($data['equipment'])) {
            $rows[] = ['label' => (string)__('Equipment'), 'value' => (string)$data['equipment']['name']];
        }
        if (($data['gift_message'] ?? '') !== '') {
            $rows[] = ['label' => (string)__('Gift Message'), 'value' => (string)$data['gift_message']];
        }
        $rows[] = ['label' => (string)__('Box Subtotal'), 'value' => $money((float)$data['subtotal'])];
        $rows[] = ['label' => (string)__('Box Discount'), 'value' => $money((float)$data['discount'])];
        $rows[] = ['label' => (string)__('Box Final Price'), 'value' => $money((float)$data['final_price'])];
        return $rows;
    }
}
