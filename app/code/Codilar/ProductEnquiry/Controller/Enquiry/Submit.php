<?php

declare(strict_types=1);

namespace Codilar\ProductEnquiry\Controller\Enquiry;

use Codilar\ProductEnquiry\Logger\Logger;
use Codilar\ProductEnquiry\Model\ProductEnquiryFactory;
use Codilar\ProductEnquiry\Model\ResourceModel\ProductEnquiry as ProductEnquiryResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validator\EmailAddress;

class Submit implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ProductEnquiryFactory $productEnquiryFactory,
        private readonly ProductEnquiryResource $productEnquiryResource,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EmailAddress $emailValidator,
        private readonly Logger $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $name = trim((string) $this->request->getParam('name'));
            $email = trim((string) $this->request->getParam('email'));
            $address = trim((string) $this->request->getParam('address'));
            $sku = trim((string) $this->request->getParam('sku'));
            $quantity = (int) $this->request->getParam('quantity', 1);

            /*
             * Validate name
             */
            if ($name === '') {
                return $result->setData([
                    'success' => false,
                    'message' => __('Name is required.')
                ]);
            }

            /*
             * Validate email
             */
            if ($email === '') {
                return $result->setData([
                    'success' => false,
                    'message' => __('Email is required.')
                ]);
            }

            if (!$this->emailValidator->isValid($email)) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Please enter a valid email address.')
                ]);
            }

            /*
             * Validate address
             */
            if ($address === '') {
                return $result->setData([
                    'success' => false,
                    'message' => __('Address is required.')
                ]);
            }

            /*
             * Validate SKU
             */
            if ($sku === '') {
                return $result->setData([
                    'success' => false,
                    'message' => __('Product SKU is missing.')
                ]);
            }

            /*
             * Validate quantity
             */
            if ($quantity < 1) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Quantity must be at least 1.')
                ]);
            }

            /*
             * Validate that the submitted SKU actually exists.
             */
            try {
                $product = $this->productRepository->get($sku);
            } catch (NoSuchEntityException) {
                return $result->setData([
                    'success' => false,
                    'message' => __('The selected product does not exist.')
                ]);
            }

            /*
             * This endpoint is only intended for simple products.
             */
            if ($product->getTypeId() !== 'simple') {
                return $result->setData([
                    'success' => false,
                    'message' => __(
                        'Product enquiry is only available for simple products.'
                    )
                ]);
            }

            /*
             * Create enquiry model.
             */
            $enquiry = $this->productEnquiryFactory->create();

            $enquiry->setData([
                'name' => $name,
                'email' => $email,
                'address' => $address,
                'sku' => $sku,
                'quantity' => $quantity
            ]);

            /*
             * Save enquiry into database.
             */
            $this->productEnquiryResource->save($enquiry);

            /*
             * Write enquiry into custom log file.
             */
            $this->logger->info(
                'Product enquiry submitted',
                [
                    'entity_id' => $enquiry->getId(),
                    'name' => $name,
                    'email' => $email,
                    'address' => $address,
                    'sku' => $sku,
                    'quantity' => $quantity
                ]
            );

            return $result->setData([
                'success' => true,
                'message' => __(
                    'Your product enquiry has been submitted successfully.'
                )
            ]);

        } catch (\Throwable $exception) {

            $this->logger->error(
                'Product enquiry submission failed',
                [
                    'exception' => $exception->getMessage()
                ]
            );

            return $result->setData([
                'success' => false,
                'message' => __(
                    'Unable to submit your enquiry right now.'
                )
            ]);
        }
    }
}
