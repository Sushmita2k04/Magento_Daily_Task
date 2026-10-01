<?php

declare(strict_types=1);

namespace Codilar\ProductEnquiry\Mail;

use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

class EnquirySender
{
    private const ADMIN_TEMPLATE_ID = 'codilar_product_enquiry_email';
    private const CUSTOMER_TEMPLATE_ID = 'codilar_product_enquiry_customer_email';

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
        $storeId = (int) $store->getId();

        $adminEmail = (string) $store->getConfig(
            'trans_email/ident_general/email'
        );

        $templateVars = [
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_address' => $address,
            'product_sku' => $sku,
            'quantity' => $quantity
        ];

        $this->inlineTranslation->suspend();

        try {
            $this->sendAdminEmail(
                $adminEmail,
                $storeId,
                $templateVars
            );

            $this->sendCustomerEmail(
                $email,
                $storeId,
                $templateVars
            );
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    private function sendAdminEmail(
        string $adminEmail,
        int $storeId,
        array $templateVars
    ): void {
        $transport = $this->transportBuilder
            ->setTemplateIdentifier(self::ADMIN_TEMPLATE_ID)
            ->setTemplateOptions([
                'area' => Area::AREA_FRONTEND,
                'store' => $storeId
            ])
            ->setTemplateVars($templateVars)
            ->setFromByScope('general')
            ->addTo($adminEmail)
            ->getTransport();

        $transport->sendMessage();
    }

    private function sendCustomerEmail(
        string $customerEmail,
        int $storeId,
        array $templateVars
    ): void {
        $transport = $this->transportBuilder
            ->setTemplateIdentifier(self::CUSTOMER_TEMPLATE_ID)
            ->setTemplateOptions([
                'area' => Area::AREA_FRONTEND,
                'store' => $storeId
            ])
            ->setTemplateVars($templateVars)
            ->setFromByScope('general')
            ->addTo($customerEmail)
            ->getTransport();

        $transport->sendMessage();
    }
}