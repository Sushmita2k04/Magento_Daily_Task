<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model;

use Codilar\ProductLaunch\Api\Data\LaunchInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Server-side validation with friendly messages. Dates are expected in UTC (Y-m-d H:i:s).
 */
class LaunchValidator
{
    public const MAX_DESCRIPTION = 65000;

    public function __construct(
        private readonly Status $status,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return Phrase[]
     */
    public function validate(LaunchInterface $launch): array
    {
        $errors = [];
        $title = trim((string)$launch->getTitle());
        if ($title === '') {
            $errors[] = __('Please enter a launch title.');
        } elseif (mb_strlen($title) > 255) {
            $errors[] = __('The launch title may not be longer than 255 characters.');
        }

        $productId = $launch->getProductId();
        if (!$productId) {
            // a stored, disabled launch whose product was deleted may still be saved (e.g. to disable it)
            if (!($launch->getLaunchId() && !$launch->getIsActive())) {
                $errors[] = __('Please select the product for this launch.');
            }
        } else {
            try {
                $this->productRepository->getById($productId);
            } catch (NoSuchEntityException) {
                $errors[] = __('The selected product no longer exists. Please choose another product.');
            }
        }

        $start = $this->status->toTimestamp($launch->getStartAt());
        if ($start === null) {
            $errors[] = __('Please enter a valid start date and time.');
        }
        $endRaw = $launch->getEndAt();
        if ($endRaw !== null && trim($endRaw) !== '') {
            $end = $this->status->toTimestamp($endRaw);
            if ($end === null) {
                $errors[] = __('The end date and time is not valid.');
            } elseif ($start !== null && $end <= $start) {
                $errors[] = __('The end date and time must be later than the start date and time.');
            }
        }

        $description = (string)$launch->getDescription();
        if (mb_strlen($description) > self::MAX_DESCRIPTION) {
            $errors[] = __('The description is too long.');
        }

        $urlKey = (string)$launch->getUrlKey();
        if ($urlKey !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $urlKey)) {
            $errors[] = __('The URL key may only contain lowercase letters, numbers and hyphens.');
        } elseif ($urlKey === 'index') {
            $errors[] = __('The URL key "index" is reserved.');
        }

        $banner = (string)$launch->getBanner();
        if ($banner !== '' && (str_contains($banner, '..') || str_contains($banner, '/') || str_contains($banner, '\\'))) {
            $errors[] = __('The banner image reference is invalid.');
        }

        $validStores = array_map('intval', array_keys($this->storeManager->getStores(true)));
        foreach ($launch->getStoreIds() as $storeId) {
            if ($storeId !== 0 && !in_array($storeId, $validStores, true)) {
                $errors[] = __('One of the selected store views does not exist.');
                break;
            }
        }
        return $errors;
    }

    /**
     * @throws ValidatorException
     */
    public function assertValid(LaunchInterface $launch): void
    {
        $errors = $this->validate($launch);
        if ($errors) {
            throw new ValidatorException(__(implode(' ', array_map('strval', $errors))));
        }
    }
}
