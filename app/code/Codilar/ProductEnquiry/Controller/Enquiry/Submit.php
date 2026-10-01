<?php

declare(strict_types=1);

namespace Codilar\ProductEnquiry\Controller\Enquiry;

use Codilar\ProductEnquiry\Logger\Logger;
use Codilar\ProductEnquiry\Mail\EnquirySender;
use Codilar\ProductEnquiry\Model\ProductEnquiryFactory;
use Codilar\ProductEnquiry\Model\ResourceModel\ProductEnquiry as ProductEnquiryResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
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
        private readonly ImageHelper $imageHelper,
        private readonly EmailAddress $emailValidator,
        private readonly Logger $logger,
        private readonly EnquirySender $enquirySender
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

            if ($name === '') {
                return $result->setData([
                    'success' => false,
                    'message' => __('Name is required.')
                ]);
            }

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

            if ($address === '') {
                return $result->setData([
                    'success' => false,
                    'message' => __('Address is required.')
                ]);
            }

            if ($sku === '') {
                return $result->setData([
                    'success' => false,
                    'message' => __('Product SKU is missing.')
                ]);
            }

            if ($quantity < 1) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Quantity must be at least 1.')
                ]);
            }

            try {
                $product = $this->productRepository->get($sku);
            } catch (NoSuchEntityException) {
                return $result->setData([
                    'success' => false,
                    'message' => __('The selected product does not exist.')
                ]);
            }

            if ($product->getTypeId() !== 'simple') {
                return $result->setData([
                    'success' => false,
                    'message' => __(
                        'Product enquiry is only available for simple products.'
                    )
                ]);
            }

            $productName = (string) $product->getName();

            $productImage = $this->imageHelper
                ->init($product, 'product_page_image_large')
                ->getUrl();

            $enquiry = $this->productEnquiryFactory->create();

            $enquiry->setData([
                'name' => $name,
                'email' => $email,
                'address' => $address,
                'sku' => $sku,
                'quantity' => $quantity
            ]);

            $this->productEnquiryResource->save($enquiry);

            $this->logger->info(
                'Product enquiry DB save completed',
                [
                    'entity_id' => $enquiry->getId(),
                    'data' => $enquiry->getData()
                ]
            );

            $this->enquirySender->send(
                $name,
                $email,
                $address,
                $sku,
                $quantity,
                $productName,
                $productImage
            );

            $this->logger->info(
                'Product enquiry submitted',
                [
                    'entity_id' => $enquiry->getId(),
                    'name' => $name,
                    'email' => $email,
                    'address' => $address,
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'product_name' => $productName,
                    'product_image' => $productImage
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