<?php

declare(strict_types=1);

namespace Codilar\MultiCategoryFilter\Model\Layer\Filter;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\Category as MagentoCategoryFilter;
use Magento\Catalog\Model\Layer\Filter\DataProvider\CategoryFactory;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class Category extends MagentoCategoryFilter
{
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        Escaper $escaper,
        CategoryFactory $categoryDataProviderFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $escaper,
            $categoryDataProviderFactory,
            $data
        );
    }

    public function apply(RequestInterface $request): static
    {
        file_put_contents(BP . '/var/log/multi-category-debug.log', "CUSTOM CATEGORY FILTER CALLED\n", FILE_APPEND);
        $logFile = BP . '/var/log/multi-category-debug.log';
        $value = $request->getParam($this->getRequestVar());

        file_put_contents(
            $logFile,
            sprintf(
                "\n--- %s ---\nRequest parameter: %s\n",
                date('Y-m-d H:i:s'),
                var_export($value, true)
            ),
            FILE_APPEND
        );

        if ($value === null || $value === '') {
            return $this;
        }

        $values = is_array($value)
            ? $value
            : explode(',', (string) $value);

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $values),
            static fn(int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return $this;
        }

        $currentCategory = $this->getLayer()->getCurrentCategory();
        $currentPath = (string) $currentCategory->getPath();
        $storeId = (int) $this->getLayer()->getCurrentStore()->getId();

        $validIds = [];
        $validCategories = [];

        foreach ($ids as $id) {
            try {
                $category = $this->categoryRepository->get($id, $storeId);
            } catch (NoSuchEntityException) {
                continue;
            }

            $path = (string) $category->getPath();

            if (
                !$category->getIsActive()
                || !str_starts_with($path, $currentPath . '/')
            ) {
                continue;
            }

            $validIds[] = (int) $category->getId();
            $validCategories[] = $category;
        }

        file_put_contents(
            $logFile,
            sprintf(
                "Current category ID: %s\nCurrent path: %s\nRequested IDs: %s\nValid IDs: %s\n",
                $currentCategory->getId(),
                $currentPath,
                implode(',', $ids),
                implode(',', $validIds)
            ),
            FILE_APPEND
        );

        if ($validIds === []) {
            return $this;
        }

        $collection = $this->getLayer()->getProductCollection();
        $collection->addCategoriesFilter(['eq' => $validIds]);

        file_put_contents(
            $logFile,
            "Generated SQL:\n" . $collection->getSelect()->__toString() . "\n\n",
            FILE_APPEND
        );

        foreach ($validCategories as $category) {
            $this->getLayer()->getState()->addFilter(
                $this->_createItem(
                    $category->getName(),
                    (int) $category->getId()
                )
            );
        }

        return $this;
    }
}
