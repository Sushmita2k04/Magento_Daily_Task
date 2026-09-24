<?php

declare(strict_types=1);

namespace Codilar\ProductEnquiry\Mail;

use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

class EnquirySender
{
    private const TEMPLATE_ID = 'codilar_product_enquiry_email';

    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function send(
        string $name,
        string $email,
        string $address,
        string $sku,
        int $quantity
    ): void {
        $store = $this->storeManager->getStore();

        $this->inlineTranslation->suspend();

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_ID)
                ->setTemplateOptions([
                    'area' => Area::AREA_FRONTEND,
                    'store' => $store->getId()
                ])
                ->setTemplateVars([
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'customer_address' => $address,
                    'product_sku' => $sku,
                    'quantity' => $quantity
                ])
                ->setFromByScope('general')
                ->addTo(
                    $store->getConfig('trans_email/ident_general/email')
                )
                ->getTransport();

            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}