<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Block\Adminhtml\Launch;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Codilar\ProductLaunch\Model\BannerManager;
use Codilar\ProductLaunch\Model\DateTimeConverter;
use Codilar\ProductLaunch\Model\Status;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Read-only launch details in admin, incl. storefront-visibility diagnostics.
 */
class View extends Template
{
    public function __construct(
        Context $context,
        private readonly Status $status,
        private readonly DateTimeConverter $dateTimeConverter,
        private readonly BannerManager $bannerManager,
        private readonly ProductRepositoryInterface $productRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getLaunch(): LaunchInterface
    {
        return $this->getData('launch');
    }

    public function getStatusLabel(): string
    {
        $launch = $this->getLaunch();
        try {
            $code = $this->status->calculate($launch->getStartAt(), $launch->getEndAt());
        } catch (\InvalidArgumentException) {
            return (string)__('Invalid dates');
        }
        return (string)$this->status->getLabels()[$code];
    }

    public function formatLaunchDate(?string $utc): string
    {
        return $utc ? $this->dateTimeConverter->format($utc) : (string)__('— (no end date)');
    }

    public function getBannerUrl(): ?string
    {
        return $this->bannerManager->getUrl($this->getLaunch()->getBanner());
    }

    /**
     * @return array{name:string,status:string,websites:array}|null
     */
    public function getProductInfo(): ?array
    {
        $id = $this->getLaunch()->getProductId();
        if (!$id) {
            return null;
        }
        try {
            $product = $this->productRepository->getById($id);
        } catch (NoSuchEntityException) {
            return null;
        }
        return [
            'name' => (string)$product->getName(),
            'enabled' => (int)$product->getStatus() === 1,
            'websites' => array_map('intval', (array)$product->getWebsiteIds()),
            'edit_url' => $this->getUrl('catalog/product/edit', ['id' => $id]),
        ];
    }

    public function getEditUrl(): string
    {
        return $this->getUrl('*/*/edit', ['launch_id' => $this->getLaunch()->getLaunchId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    public function isAllowedToEdit(): bool
    {
        return $this->_authorization->isAllowed('Codilar_ProductLaunch::launch_save');
    }
}
